<?php
require_once __DIR__.'/../inc/db.php';
require_once __DIR__.'/../inc/auth.php';
require_role(['Admin','BHW']);

if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');

ini_set('display_errors','0');
error_reporting(E_ALL);

function fail($m,$c=400,$extra=null){
    http_response_code($c);
    $out = ['success'=>false,'error'=>$m];
    if ($extra!==null) $out['details']=$extra;
    echo json_encode($out);
    exit;
}

function csrf_check(){
    $post = $_POST['csrf_token'] ?? '';
    $hdr  = '';
    if (function_exists('getallheaders')) {
        foreach (getallheaders() as $k=>$v){
            if (strcasecmp($k,'X-CSRF-Token')===0){ $hdr=$v; break; }
        }
    }
    $token = $post ?: $hdr;
    if (empty($_SESSION['csrf_token']) || $token==='' || !hash_equals($_SESSION['csrf_token'],$token)) {
        fail('CSRF failed',419);
    }
}

function table_exists(mysqli $mysqli,string $table): bool {
    $t = $mysqli->real_escape_string($table);
    $res = $mysqli->query("SHOW TABLES LIKE '$t'");
    return $res && $res->num_rows>0;
}

function column_set(mysqli $mysqli,string $table): array {
    $cols=[];
    $t = $mysqli->real_escape_string($table);
    if ($res=$mysqli->query("SHOW COLUMNS FROM `$t`")) {
        while($r=$res->fetch_assoc()) $cols[$r['Field']]=true;
    }
    return $cols;
}

// Determine actual mother table
$motherTable = null;
if (table_exists($mysqli,'maternal_patients')) {
    $motherTable = 'maternal_patients';
} elseif (table_exists($mysqli,'mothers_caregivers')) {
    $motherTable = 'mothers_caregivers';
} else {
    fail('No maternal patients table found (neither maternal_patients nor mothers_caregivers).',500);
}
$cols = column_set($mysqli,$motherTable);

$hasFullNameCol = isset($cols['full_name']);
$hasPurok       = isset($cols['purok_id']);
$hasFirst       = isset($cols['first_name']);
$hasMiddle      = isset($cols['middle_name']);
$hasLast        = isset($cols['last_name']);
$hasLegacyFull  = isset($cols['legacy_full_name_backup']);

$fullNameExpr = $hasFullNameCol
    ? 'm.full_name'
    : 'TRIM(CONCAT_WS(" ", m.first_name, m.middle_name, m.last_name))';

$method = $_SERVER['REQUEST_METHOD'];

// Optional diagnostics
if ($method==='GET' && isset($_GET['diag'])) {
    echo json_encode([
        'success'=>true,
        'active_table'=>$motherTable,
        'columns'=>array_keys($cols),
        'using_full_name_expr'=>$fullNameExpr,
        'has_full_name_column'=>$hasFullNameCol,
        'has_purok'=>$hasPurok,
        'csrf_present'=>!empty($_SESSION['csrf_token'])
    ]);
    exit;
}

// ================== GET ==================
if ($method==='GET') {

    // List (paginated & filter)
    if (isset($_GET['list'])) {
        $page     = max(1,(int)($_GET['page'] ?? 1));
        $pageSize = max(5,min(100,(int)($_GET['page_size'] ?? 20)));
        $offset   = ($page-1)*$pageSize;
        $search   = trim($_GET['search'] ?? '');
        $riskFilter = trim($_GET['risk'] ?? '');

        $where = [];
        $bindTypes = '';
        $bindVals = [];

        if ($search!=='') {
            if ($hasPurok) {
                $where[] = "( {$fullNameExpr} LIKE ? OR p.purok_name LIKE ? )";
                $like = '%'.$search.'%';
                $bindTypes .= 'ss';
                $bindVals[] = $like; $bindVals[] = $like;
            } else {
                $where[] = "( {$fullNameExpr} LIKE ? )";
                $like = '%'.$search.'%';
                $bindTypes .= 's';
                $bindVals[] = $like;
            }
        }

        $whereSql = $where ? 'WHERE '.implode(' AND ',$where) : '';
        $purokJoin = $hasPurok ? 'LEFT JOIN puroks p ON p.purok_id=m.purok_id' : '';

        // Count
        $countSql = "SELECT COUNT(*) AS total
                     FROM `$motherTable` m
                     $purokJoin
                     $whereSql";
        $countStmt = $mysqli->prepare($countSql);
        if(!$countStmt) fail('Prepare failed (count): '.$mysqli->error,500);
        if ($bindTypes) $countStmt->bind_param($bindTypes,...$bindVals);
        $countStmt->execute();
        $countRes = $countStmt->get_result();
        $total = (int)($countRes->fetch_assoc()['total'] ?? 0);
        $countStmt->close();

        $totalPages = $pageSize? (int)ceil($total/$pageSize) : 1;

        $healthRiskExpr = "(SELECT COUNT(*) FROM health_records hr
                            WHERE hr.mother_id = m.mother_id
                              AND (hr.vaginal_bleeding=1 OR hr.urinary_infection=1 OR hr.high_blood_pressure=1
                                OR hr.fever_38_celsius=1 OR hr.pallor=1 OR hr.abnormal_abdominal_size=1
                                OR hr.abnormal_presentation=1 OR hr.absent_fetal_heartbeat=1
                                OR hr.swelling=1 OR hr.vaginal_infection=1)
                           ) AS risk_count";

        $selectCols = [
            'm.mother_id',
            "{$fullNameExpr} AS full_name",
        ];

        foreach (['date_of_birth','gravida','para','blood_type','emergency_contact_name',
                  'emergency_contact_number','address_details','contact_number'] as $c) {
            if (isset($cols[$c])) $selectCols[] = "m.$c";
        }

        $selectCols[] = $hasPurok ? 'p.purok_name' : 'NULL AS purok_name';
        $selectCols[] = "(SELECT COUNT(*) FROM health_records hr2 WHERE hr2.mother_id=m.mother_id) AS records_count";
        $selectCols[] = "(SELECT MAX(consultation_date) FROM health_records hr3 WHERE hr3.mother_id=m.mother_id) AS last_consultation_date";
        $selectCols[] = $healthRiskExpr;

        $listSql = "SELECT ".implode(",",$selectCols)."
                    FROM `$motherTable` m
                    $purokJoin
                    $whereSql
                    ORDER BY m.mother_id DESC
                    LIMIT ? OFFSET ?";
        $listStmt = $mysqli->prepare($listSql);
        if(!$listStmt) fail('Prepare failed (list): '.$mysqli->error,500);

        $bindTypesList = $bindTypes . 'ii';
        $bindValsList  = array_merge($bindVals, [$pageSize,$offset]);
        $listStmt->bind_param($bindTypesList,...$bindValsList);
        $listStmt->execute();
        $res = $listStmt->get_result();
        $rows=[];
        while($r=$res->fetch_assoc()) $rows[]=$r;
        $listStmt->close();

        if ($riskFilter!=='') {
            $rows = array_values(array_filter($rows,function($r) use($riskFilter){
                $riskCount = (int)($r['risk_count'] ?? 0);
                if ($riskFilter==='high') return $riskCount>=2;
                if ($riskFilter==='monitor') return $riskCount===1;
                if ($riskFilter==='normal') return $riskCount===0;
                return true;
            }));
        }

        echo json_encode([
            'success'=>true,
            'mothers'=>$rows,
            'total_count'=>$total,
            'current_page'=>$page,
            'page_size'=>$pageSize,
            'total_pages'=>$totalPages,
            'active_table'=>$motherTable
        ]);
        exit;
    }

    // Light list for dropdowns
    if (isset($_GET['list_basic'])) {
        $purokJoin = $hasPurok ? 'LEFT JOIN puroks p ON p.purok_id=m.purok_id' : '';
        $sql = "SELECT m.mother_id, {$fullNameExpr} AS full_name
                FROM `$motherTable` m
                $purokJoin
                ORDER BY full_name ASC";
        $res = $mysqli->query($sql);
        if(!$res) fail('Query failed: '.$mysqli->error,500);
        $rows=[];
        while($r=$res->fetch_assoc()) $rows[]=$r;
        echo json_encode(['success'=>true,'mothers'=>$rows,'active_table'=>$motherTable]);
        exit;
    }

    fail('Unknown GET action',404);
}

// ================== POST (create) ==================
if ($method==='POST') {
    csrf_check();

    // Input map (form sends single full_name)
    $inputMap = [
        'full_name'                => ['required'=>true],
        'purok_name'               => ['required'=>false],
        'address_details'          => ['required'=>false],
        'contact_number'           => ['required'=>false],
        'date_of_birth'            => ['required'=>false, 'validate'=>'date'],
        'gravida'                  => ['required'=>false, 'type'=>'int'],
        'para'                     => ['required'=>false, 'type'=>'int'],
        'blood_type'               => ['required'=>false],
        'emergency_contact_name'   => ['required'=>false],
        'emergency_contact_number' => ['required'=>false]
    ];

    $data = [];
    foreach($inputMap as $field=>$meta){
        $val = trim($_POST[$field] ?? '');
        if ($meta['required'] && $val==='') {
            fail("Missing required field: $field",422);
        }
        if ($val==='') $val=null;

        if ($val!==null && isset($meta['validate']) && $meta['validate']==='date') {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/',$val)) $val=null;
        }
        if ($val!==null && isset($meta['type']) && $meta['type']==='int') {
            $val = (int)$val;
        }
        $data[$field] = $val;
    }

    // Parse full name if needed
    $fullNameInput = $data['full_name'];

    $nameParts = ['first'=>null,'middle'=>null,'last'=>null];
    if (!$hasFullNameCol) {
        // Simple heuristic: first token -> first_name, last token -> last_name, middle tokens joined -> middle_name
        $tokens = preg_split('/\s+/',$fullNameInput,-1,PREG_SPLIT_NO_EMPTY);
        if (count($tokens)===1) {
            $nameParts['first'] = $tokens[0];
        } elseif (count($tokens)===2) {
            $nameParts['first'] = $tokens[0];
            $nameParts['last']  = $tokens[1];
        } else {
            $nameParts['first']  = array_shift($tokens);
            $nameParts['last']   = array_pop($tokens);
            $nameParts['middle'] = implode(' ',$tokens);
        }
    }

    // Purok handling only if table supports it
    $purok_id = null;
    $purok_name = $data['purok_name'] ?? null;
    if ($purok_name===null || $purok_name==='') $purok_name='Unassigned';

    if ($hasPurok) {
        $ps = $mysqli->prepare("SELECT purok_id FROM puroks WHERE purok_name=? LIMIT 1");
        $ps->bind_param('s',$purok_name);
        $ps->execute(); $ps->bind_result($pid);
        if ($ps->fetch()) $purok_id=$pid;
        $ps->close();
        if(!$purok_id){
            $barangay='Sabang';
            $insP = $mysqli->prepare("INSERT INTO puroks (purok_name,barangay) VALUES (?,?)");
            $insP->bind_param('ss',$purok_name,$barangay);
            if(!$insP->execute()) fail('Purok insert failed: '.$insP->error,500);
            $purok_id=$insP->insert_id;
            $insP->close();
        }
    }

    $created_by = (int)($_SESSION['user_id'] ?? 0);
    if ($created_by<=0) $created_by = null;

    $insertCols = [];
    $placeholders = [];
    $types = '';
    $vals = [];

    if ($hasFullNameCol) {
        $insertCols[]='full_name'; $placeholders[]='?'; $types.='s'; $vals[]=$fullNameInput;
    } else {
        // Insert name parts if columns exist
        if ($hasFirst) {
            $insertCols[]='first_name'; $placeholders[]='?'; $types.='s'; $vals[]=$nameParts['first'];
        }
        if ($hasMiddle) {
            if ($nameParts['middle']===null) {
                $insertCols[]='middle_name'; $placeholders[]='NULL';
            } else {
                $insertCols[]='middle_name'; $placeholders[]='?'; $types.='s'; $vals[]=$nameParts['middle'];
            }
        }
        if ($hasLast) {
            if ($nameParts['last']===null) {
                $insertCols[]='last_name'; $placeholders[]='NULL';
            } else {
                $insertCols[]='last_name'; $placeholders[]='?'; $types.='s'; $vals[]=$nameParts['last'];
            }
        }
        if ($hasLegacyFull) {
            $insertCols[]='legacy_full_name_backup'; $placeholders[]='?'; $types.='s'; $vals[]=$fullNameInput;
        }
    }

    if ($hasPurok && $purok_id!==null) {
        $insertCols[]='purok_id'; $placeholders[]='?'; $types.='i'; $vals[]=$purok_id;
    }

    $optionalMapping = [
        'address_details'=>'address_details',
        'contact_number'=>'contact_number',
        'date_of_birth'=>'date_of_birth',
        'gravida'=>'gravida',
        'para'=>'para',
        'blood_type'=>'blood_type',
        'emergency_contact_name'=>'emergency_contact_name',
        'emergency_contact_number'=>'emergency_contact_number'
    ];
    foreach($optionalMapping as $in=>$colName){
        if (isset($cols[$colName]) && array_key_exists($in,$data)) {
            $val = $data[$in];
            if ($val===null){
                $insertCols[]=$colName; $placeholders[]='NULL';
            } else {
                $insertCols[]=$colName; $placeholders[]='?';
                if (in_array($colName,['gravida','para'])) { $types.='i'; $vals[]=(int)$val; }
                else { $types.='s'; $vals[]=$val; }
            }
        }
    }

    if (isset($cols['created_by'])) {
        if ($created_by===null) {
            $insertCols[]='created_by'; $placeholders[]='NULL';
        } else {
            $insertCols[]='created_by'; $placeholders[]='?'; $types.='i'; $vals[]=$created_by;
        }
    }

    if (!$insertCols) fail('Nothing to insert; table structure mismatch.',500);

    $sql = "INSERT INTO `$motherTable` (".implode(',',$insertCols).") VALUES (".implode(',',$placeholders).")";
    $stmt = $mysqli->prepare($sql);
    if(!$stmt) fail('Prepare failed: '.$mysqli->error,500);

    if ($types!=='') {
        $bind = [];
        $bind[] = &$types;
        foreach($vals as $k=>$v){ $bind[] = &$vals[$k]; }
        call_user_func_array([$stmt,'bind_param'],$bind);
    }

    if (!$stmt->execute()) {
        fail('Insert failed: '.$stmt->error,500,['sql'=>$sql]);
    }
    $newId = $stmt->insert_id;
    $stmt->close();

    echo json_encode([
        'success'=>true,
        'mother_id'=>$newId,
        'active_table'=>$motherTable
    ]);
    exit;
}

fail('Invalid method',405);