<?php
require_once __DIR__.'/../inc/db.php';
require_once __DIR__.'/../inc/auth.php';
require_role(['Admin','BNS']);
header('Content-Type: application/json; charset=utf-8');
if (session_status()===PHP_SESSION_NONE) session_start();

function fail($m,$c=400){ http_response_code($c); echo json_encode(['success'=>false,'error'=>$m]); exit; }
$method = $_SERVER['REQUEST_METHOD'];
$mysqli->set_charset('utf8mb4');

/* Unified provisional classification */
function provisional_classify(float $weightKg, float $lengthCm): ?string {
    if ($weightKg <= 0 || $lengthCm <= 0) return null;
    $m = $lengthCm / 100;
    if ($m <= 0) return null;
    $bmi = $weightKg / ($m*$m);
    if ($bmi < 12.5) return 'SAM';
    if ($bmi < 13.0) return 'MAM';
    if ($bmi < 13.5) return 'UW';
    if ($bmi < 17.5) return 'NOR';
    if ($bmi < 19.0) return 'OW';
    return 'OB';
}

/* Unified CSRF (param or header) */
function csrf_unified(){
    if (session_status() === PHP_SESSION_NONE) session_start();
    $param = $_POST['csrf_token'] ?? '';
    $hdr = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (function_exists('getallheaders') && !$hdr){
        foreach(getallheaders() as $k=>$v){
            if(strcasecmp($k,'X-CSRF-Token')===0){ $hdr=$v; break; }
        }
    }
    $token = $param ?: $hdr;
    if (empty($_SESSION['csrf_token']) || $token === '' || !hash_equals($_SESSION['csrf_token'],$token)){
        fail('CSRF failed',419);
    }
}

if ($method==='GET') {

    if (isset($_GET['classification_summary'])) {
        try {
            $sql = "
                SELECT COALESCE(s.status_code,'UNSET') AS status_code,
                       COUNT(DISTINCT c.child_id)    AS child_count
                FROM children c
                LEFT JOIN (
                    SELECT nr1.child_id, nr1.weighing_date, nr1.wfl_ht_status_id
                    FROM nutrition_records nr1
                    INNER JOIN (
                        SELECT child_id, MAX(weighing_date) AS max_date
                        FROM nutrition_records
                        GROUP BY child_id
                    ) mx ON mx.child_id = nr1.child_id AND mx.max_date = nr1.weighing_date
                ) latest ON latest.child_id = c.child_id
                LEFT JOIN wfl_ht_status_types s ON s.status_id = latest.wfl_ht_status_id
                GROUP BY status_code
            ";
            $res = $mysqli->query($sql);
            $counts = [];
            while ($row = $res->fetch_assoc()) {
                $counts[$row['status_code']] = (int)$row['child_count'];
            }
            $known = ['NOR','MAM','SAM','UW','OW','OB','ST','UNSET'];
            $summary = [];
            foreach ($known as $code) {
                if (isset($counts[$code])) {
                    $summary[] = ['status_code'=>$code, 'child_count'=>$counts[$code]];
                } elseif ($code !== 'UNSET') {
                    $summary[] = ['status_code'=>$code, 'child_count'=>0];
                } elseif (isset($counts['UNSET'])) {
                    $summary[] = ['status_code'=>'UNSET', 'child_count'=>$counts['UNSET']];
                }
            }
            echo json_encode(['success'=>true,'summary'=>$summary]); exit;
        } catch (Throwable $e) {
            fail('Failed to compute classification summary: '.$e->getMessage(),500);
        }
    }

    if (isset($_GET['child_id'])) {
        $cid = (int)$_GET['child_id'];
        if ($cid <= 0) fail('Invalid child_id');
        $sql = "
            SELECT nr.record_id,
                   nr.weighing_date,
                   nr.age_in_months,
                   nr.weight_kg,
                   nr.length_height_cm,
                   nr.remarks,
                   s.status_code,
                   s.status_description
            FROM nutrition_records nr
            LEFT JOIN wfl_ht_status_types s ON s.status_id = nr.wfl_ht_status_id
            WHERE nr.child_id = ?
            ORDER BY nr.weighing_date DESC, nr.record_id DESC
            LIMIT 500
        ";
        $stmt = $mysqli->prepare($sql);
        if(!$stmt) fail('DB prepare failed',500);
        $stmt->bind_param('i',$cid);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows=[]; while($r=$res->fetch_assoc()) $rows[]=$r;
        $stmt->close();
        echo json_encode(['success'=>true,'records'=>$rows]); exit;
    }

    if (isset($_GET['child_records'])) {
        $child_id = isset($_GET['child_id']) ? (int)$_GET['child_id'] : 0;
        if ($child_id <= 0) {
            echo json_encode(['success' => false, 'error' => 'Invalid child ID']);
            exit;
        }
        try {
            $sql = "
                SELECT nr.record_id, 
                       nr.weighing_date, 
                       nr.age_in_months, 
                       nr.weight_kg,
                       nr.length_height_cm, 
                       nr.remarks,
                       nr.created_at,
                       s.status_code, 
                       s.status_description
                FROM nutrition_records nr
                LEFT JOIN wfl_ht_status_types s ON s.status_id = nr.wfl_ht_status_id
                WHERE nr.child_id = ?
                ORDER BY nr.weighing_date DESC, nr.created_at DESC
                LIMIT 50
            ";
            $stmt = $mysqli->prepare($sql);
            if (!$stmt) throw new Exception('Database prepare failed');
            $stmt->bind_param('i', $child_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $records = [];
            while ($row = $result->fetch_assoc()) {
                $records[] = $row;
            }
            $stmt->close();
            echo json_encode([
                'success' => true,
                'records' => $records,
                'total_records' => count($records)
            ]);
            exit;
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => 'Failed to fetch records: ' . $e->getMessage()]);
            exit;
        }
    }

    if (isset($_GET['recent'])) {
        $sql = "
          SELECT nr.record_id, nr.weighing_date, nr.age_in_months, nr.weight_kg,
                 nr.length_height_cm, nr.remarks,
                 c.full_name AS child_name,
                 s.status_code, s.status_description
          FROM nutrition_records nr
          JOIN children c ON c.child_id = nr.child_id
          LEFT JOIN wfl_ht_status_types s ON s.status_id = nr.wfl_ht_status_id
          ORDER BY nr.weighing_date DESC, nr.record_id DESC
          LIMIT 300
        ";
        $res=$mysqli->query($sql);
        $rows=[]; while($r=$res->fetch_assoc()) $rows[]=$r;
        echo json_encode(['success'=>true,'records'=>$rows]); exit;
    }

    if (isset($_GET['classify'])) {
        $w = isset($_GET['weight']) ? (float)$_GET['weight'] : 0;
        $l = isset($_GET['length']) ? (float)$_GET['length'] : 0;
        if ($w <= 0 || $l <= 0) fail('Invalid weight/length');

        $code = provisional_classify($w,$l);
        if (!$code) { echo json_encode(['success'=>true,'status'=>null]); exit; }

        $stmt = $mysqli->prepare("SELECT status_id,status_description FROM wfl_ht_status_types WHERE status_code=? LIMIT 1");
        if(!$stmt) fail('Database prepare failed',500);
        $stmt->bind_param('s',$code);
        $stmt->execute();
        $stmt->bind_result($sid,$sdesc);
        if ($stmt->fetch()) {
            $bmi = $w / pow($l/100,2);
            $stmt->close();
            echo json_encode([
                'success'=>true,
                'status_code'=>$code,
                'status_id'=>$sid,
                'status_description'=>$sdesc,
                'bmi'=>round($bmi,2)
            ]);
            exit;
        }
        $stmt->close();
        echo json_encode([
            'success'=>true,
            'status_code'=>$code,
            'status_id'=>null,
            'status_description'=>null
        ]);
        exit;
    }

    if (isset($_GET['combined'])) {
        $date = $_GET['date'] ?? date('Y-m-d');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date)) $date = date('Y-m-d');
        $sql = "
          SELECT c.child_id,
                 c.full_name AS child_name,
                 c.sex,
                 c.birth_date,
                 TIMESTAMPDIFF(MONTH,c.birth_date, ?) AS age_months,
                 m.full_name AS mother_name,
                 p.purok_name,
                 nr.record_id,
                 nr.weight_kg,
                 nr.length_height_cm,
                 nr.wfl_ht_status_id,
                 s.status_code,
                 s.status_description
          FROM children c
          LEFT JOIN mothers_caregivers m ON m.mother_id = c.mother_id
          LEFT JOIN puroks p ON p.purok_id = m.purok_id
          LEFT JOIN nutrition_records nr
                 ON nr.child_id = c.child_id
                AND nr.weighing_date = ?
          LEFT JOIN wfl_ht_status_types s ON s.status_id = nr.wfl_ht_status_id
          ORDER BY p.purok_name ASC, m.full_name ASC, c.full_name ASC
        ";
        $stmt = $mysqli->prepare($sql);
        if(!$stmt) fail('DB prepare failed');
        $stmt->bind_param('ss',$date,$date);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows=[];
        while($r=$res->fetch_assoc()) $rows[]=$r;
        $stmt->close();
        echo json_encode(['success'=>true,'request_date'=>$date,'children'=>$rows]);
        exit;
    }

    fail('No action',400);
}

if ($method==='POST') {

    $raw = file_get_contents('php://input');
    $maybeJson = trim($raw);
    $isJson = $maybeJson !== '' && (str_starts_with($maybeJson,'{') || str_starts_with($maybeJson,'['));

    // Detect bulk by presence of "bulk" key after decode (don't assume)
    $payload = null;
    if ($isJson) {
        $payload = json_decode($raw,true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $payload = null;
        }
    }

    $isBulk = is_array($payload) && isset($payload['bulk']) && is_array($payload['bulk']);

    if ($isBulk) {
        csrf_unified();
        $records = $payload['bulk'];
        if (!$records) fail('No payload');

        $map=[]; $rs=$mysqli->query("SELECT status_id,status_code FROM wfl_ht_status_types");
        while($x=$rs->fetch_assoc()) $map[$x['status_code']]=$x['status_id'];

        $stmtAge = $mysqli->prepare("SELECT TIMESTAMPDIFF(MONTH,birth_date,?) FROM children WHERE child_id=? LIMIT 1");
        $ins = $mysqli->prepare("
          INSERT INTO nutrition_records
            (child_id,weighing_date,age_in_months,weight_kg,length_height_cm,wfl_ht_status_id,remarks,recorded_by)
          VALUES (?,?,?,?,?,?,?,?)
          ON DUPLICATE KEY UPDATE
            age_in_months=VALUES(age_in_months),
            weight_kg=VALUES(weight_kg),
            length_height_cm=VALUES(length_height_cm),
            wfl_ht_status_id=VALUES(wfl_ht_status_id),
            remarks=VALUES(remarks),
            updated_at=NOW()
        ");
        if(!$stmtAge || !$ins) fail('Prepare failed');

        $saved=$inserted=$updated=0;
        foreach($records as $r){
            $cid = (int)($r['child_id'] ?? 0);
            $date = $r['weighing_date'] ?? '';
            if ($cid<=0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/',$date)) continue;

            $w = isset($r['weight_kg']) && $r['weight_kg']!=='' ? (float)$r['weight_kg'] : null;
            $l = isset($r['length_height_cm']) && $r['length_height_cm']!=='' ? (float)$r['length_height_cm'] : null;

            $status_id = null;
            if (isset($r['wfl_ht_status_id']) && $r['wfl_ht_status_id']!=='') {
                $status_id = (int)$r['wfl_ht_status_id'];
            } elseif ($w && $l) {
                $code = provisional_classify($w,$l);
                if ($code && isset($map[$code])) $status_id = $map[$code];
            }

            $remarks = isset($r['remarks']) ? trim((string)$r['remarks']) : '';
            $rec_by = (int)($_SESSION['user_id'] ?? 0);

            $stmtAge->bind_param('si',$date,$cid);
            $stmtAge->execute();
            $stmtAge->bind_result($age_mo);
            if(!$stmtAge->fetch()){ $stmtAge->free_result(); continue; }
            $stmtAge->free_result();

            $ins->bind_param(
                'isiddisi',
                $cid,$date,$age_mo,$w,$l,$status_id,$remarks,$rec_by
            );
            if($ins->execute()){
                $saved++;
                if ($ins->affected_rows === 1) $inserted++;
                elseif ($ins->affected_rows === 2) $updated++;
            }
        }
        $stmtAge->close(); $ins->close();
        echo json_encode(['success'=>true,'saved'=>$saved,'inserted'=>$inserted,'updated'=>$updated]); exit;
    }

    // Single form submission (x-www-form-urlencoded)
    csrf_unified();

    $child_id = (int)($_POST['child_id'] ?? 0);
    $weighing_date = $_POST['weighing_date'] ?? '';
    $weight = $_POST['weight_kg'] !== '' ? (float)$_POST['weight_kg'] : null;
    $length = $_POST['length_height_cm'] !== '' ? (float)$_POST['length_height_cm'] : null;
    $status_id = ($_POST['wfl_ht_status_id'] ?? '') !== '' ? (int)$_POST['wfl_ht_status_id'] : null;
    $remarks = trim($_POST['remarks'] ?? '');
    $rec_by = (int)($_SESSION['user_id'] ?? 0);

    if ($child_id<=0 || !$weighing_date) fail('Missing required fields');

    if (!$status_id && $weight && $length) {
        $code = provisional_classify($weight,$length);
        if ($code) {
            $st = $mysqli->prepare("SELECT status_id FROM wfl_ht_status_types WHERE status_code=? LIMIT 1");
            $st->bind_param('s',$code);
            $st->execute();
            $st->bind_result($sid);
            if ($st->fetch()) $status_id = $sid;
            $st->close();
        }
    }

    $stmt = $mysqli->prepare("SELECT TIMESTAMPDIFF(MONTH,birth_date,?) FROM children WHERE child_id=? LIMIT 1");
    $stmt->bind_param('si',$weighing_date,$child_id);
    $stmt->execute();
    $stmt->bind_result($age_months);
    if(!$stmt->fetch()) { $stmt->close(); fail('Child not found',404); }
    $stmt->close();

    $stmt = $mysqli->prepare("INSERT INTO nutrition_records (child_id,weighing_date,age_in_months,weight_kg,length_height_cm,wfl_ht_status_id,remarks,recorded_by) VALUES (?,?,?,?,?,?,?,?)");
    if(!$stmt) fail('DB prepare failed');
    $stmt->bind_param(
        'isiddisi',
        $child_id,
        $weighing_date,
        $age_months,
        $weight,
        $length,
        $status_id,
        $remarks,
        $rec_by
    );
    if(!$stmt->execute()) {
        if (strpos($stmt->error,'Duplicate')!==false) fail('Record already exists for that child & date',409);
        fail('Insert failed: '.$stmt->error,500);
    }
    $id = $stmt->insert_id;
    $stmt->close();
    echo json_encode([
        'success'=>true,
        'record_id'=>$id,
        'age_in_months'=>$age_months,
        'auto_status_id'=>$status_id
    ]); exit;
}

fail('Method not allowed',405);