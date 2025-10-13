<?php
// Prevent any output before JSON response
ob_start();

// Start session first
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Handle database connection errors gracefully
try {
    require_once __DIR__.'/../inc/db.php';
    if (!$mysqli) {
        throw new Exception('Database connection failed');
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

require_once __DIR__.'/../inc/auth.php';

// Check authentication and return JSON error if not authenticated
if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}
if (!in_array($_SESSION['role'], ['BHW', 'Admin'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit;
}
header('Content-Type: application/json; charset=utf-8');

ini_set('display_errors','0');
error_reporting(E_ALL);

// Clear any output buffer content
ob_clean();

function fail($m,$c=400){ http_response_code($c); echo json_encode(['success'=>false,'error'=>$m]); exit; }
function nz($s){ $s=trim((string)$s); return $s===''? null : $s; }
function has_col($table,$col){
    global $mysqli;
    try {
        $res = $mysqli->query("SHOW COLUMNS FROM $table LIKE '$col'");
        return $res && $res->num_rows > 0;
    } catch (Exception $e) {
        return false;
    }
}

$method = $_SERVER['REQUEST_METHOD'];

function display_name($row){
    return trim(
        implode(' ', array_filter([
            $row['first_name'] ?? '',
            $row['middle_name'] ?? '',
            $row['last_name'] ?? ''
        ]))
    );
}

/**
 * Enhanced duplicate finder to reduce accidental duplicate mothers.
 * Logic:
 * 1. If DOB provided:
 *      a. Try exact match (first,last,dob)
 *      b. If none, fall back to first,last only (covers older record without DOB).
 * 2. If DOB NOT provided: first,last only.
 * Returns row or null.
 */
function find_existing_mother($first_name,$last_name,$date_of_birth){
    global $mysqli;
    $first = trim($first_name);
    $last  = trim($last_name);
    if($first==='' || $last==='') return null;

    $run = function($withDob) use($mysqli,$first,$last,$date_of_birth){
        if($withDob){
            $sql="SELECT * FROM maternal_patients
                  WHERE LOWER(first_name)=LOWER(?) AND LOWER(last_name)=LOWER(?) AND date_of_birth=?
                  LIMIT 1";
            $stmt=$mysqli->prepare($sql);
            $stmt->bind_param('sss',$first,$last,$date_of_birth);
        }else{
            $sql="SELECT * FROM maternal_patients
                  WHERE LOWER(first_name)=LOWER(?) AND LOWER(last_name)=LOWER(?)
                  ORDER BY mother_id DESC
                  LIMIT 1";
            $stmt=$mysqli->prepare($sql);
            $stmt->bind_param('ss',$first,$last);
        }
        if(!$stmt->execute()) return null;
        $res=$stmt->get_result();
        $row=$res->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    };

    if($date_of_birth){
        $row=$run(true);
        if($row) return $row;
        return $run(false);
    }
    return $run(false);
}

/**
 * Update only blank (NULL or '') fields in an existing mother record.
 * Safe so UI stays consistent (no overwriting user-entered data).
 * Returns number of fields updated.
 */
function enrich_mother_if_blank($mother_row, $newData){
    global $mysqli;
    if(!$mother_row || empty($mother_row['mother_id'])) return 0;
    $mother_id = (int)$mother_row['mother_id'];

    $setParts = [];
    $types = '';
    $vals  = [];

    foreach($newData as $col=>$val){
        if($val===null || $val==='') continue;
        if(!array_key_exists($col,$mother_row)) continue;
        if($mother_row[$col]===null || $mother_row[$col]===''){
            $setParts[]="$col=?";
            $types .= is_int($val)?'i':'s';
            $vals[]=$val;
        }
    }
    if(!$setParts) return 0;

    $sql="UPDATE maternal_patients SET ".implode(',',$setParts)." WHERE mother_id=?";
    $types.='i';
    $vals[]=$mother_id;

    $stmt=$mysqli->prepare($sql);
    $stmt->bind_param($types, ...$vals);
    $stmt->execute();
    $aff = $stmt->affected_rows;
    $stmt->close();
    return $aff;
}

/* ---- Column existence helpers (cached) ---- */
function table_columns($table){
    static $cache = [];
    if(isset($cache[$table])) return $cache[$table];
    global $mysqli;
    $cols = [];
    if($res = $mysqli->query("SHOW COLUMNS FROM `$table`")){
        while($r=$res->fetch_assoc()){
            $cols[strtolower($r['Field'])] = true;
        }
    }
    $cache[$table] = $cols;
    return $cols;
}

function valid_date_or_null($d){
    if(!$d) return null;
    return preg_match('/^\d{4}-\d{2}-\d{2}$/',$d)? $d : null;
}
function risk_flag_value($key){
    return isset($_POST[$key]) ? 1 : 0;
}

function get_notes_value($key){
    return nz($_POST[$key] ?? '');
}

/* ======================= GET ======================= */
if ($method === 'GET') {
    try {

    /* Detail endpoint */
    if (isset($_GET['detail'])) {
        $mother_id = (int)($_GET['mother_id'] ?? 0);
        if ($mother_id <= 0) {
            fail('Invalid mother_id', 422);
        }

        // Prioritize direct purok_name field over purok_id join since purok_id is often NULL
        $purok_join = '';
        $purok_col = has_col('maternal_patients','purok_name') ? ', mp.purok_name' : '';
        $stmt = $mysqli->prepare("
            SELECT mp.mother_id, mp.first_name, mp.middle_name, mp.last_name,
                   mp.date_of_birth, mp.gravida, mp.para, mp.blood_type,
                   mp.emergency_contact_name, mp.emergency_contact_number,
                   mp.contact_number, mp.house_number, mp.street_name{$purok_col}, mp.subdivision_name
            FROM maternal_patients mp{$purok_join}
            WHERE mp.mother_id = ?
            LIMIT 1
        ");
        $stmt->bind_param('i', $mother_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $mother = $res->fetch_assoc();
        $stmt->close();

        if (!$mother) {
            fail('Mother not found', 404);
        }
        $mother['full_name'] = display_name($mother);

        $sqlLatest = "
            SELECT hr.*
            FROM health_records hr
            WHERE hr.mother_id = ?
            ORDER BY hr.consultation_date DESC, hr.health_record_id DESC
            LIMIT 1
        ";
        $stmt2 = $mysqli->prepare($sqlLatest);
        $stmt2->bind_param('i', $mother_id);
        $stmt2->execute();
        $res2 = $stmt2->get_result();
        $latest = $res2->fetch_assoc();
        $stmt2->close();
        
        // Add LMP and EDD from latest health record to mother data
        if ($latest) {
            $mother['last_menstruation_date'] = $latest['last_menstruation_date'];
            $mother['expected_delivery_date'] = $latest['expected_delivery_date'];
        }

        $latest_risk_score = null;
        $risk_flags = [];
        if ($latest) {
            $fields = [
                'vaginal_bleeding','urinary_infection','high_blood_pressure','fever_38_celsius','pallor',
                'abnormal_abdominal_size','abnormal_presentation','absent_fetal_heartbeat','swelling','vaginal_infection'
            ];
            $latest_risk_score = 0;
            foreach ($fields as $f) {
                $val = (int)($latest[$f] ?? 0);
                $latest_risk_score += $val;
                $risk_flags[$f] = $val;
            }
            $latest['latest_risk_score'] = $latest_risk_score;
        }

        echo json_encode([
            'success' => true,
            'mother' => $mother,
            'latest_consultation' => $latest ?: null,
            'latest_risk_score' => $latest_risk_score,
            'risk_flags' => $risk_flags
        ]);
        exit;
    }

    if (isset($_GET['list'])) {
        $page = max(1,(int)($_GET['page'] ?? 1));
        $pageSize = max(5,min(100,(int)($_GET['page_size'] ?? 20)));
        $offset = ($page-1)*$pageSize;
        $search = trim($_GET['search'] ?? '');
        $riskFilter = trim($_GET['risk'] ?? '');

        $where = [];
        $params = '';
        $vals = [];

        if($search!==''){
            $where[] = "(first_name LIKE ? OR middle_name LIKE ? OR last_name LIKE ?)";
            $like = '%'.$search.'%';
            $params .= 'sss';
            $vals[] = $like; $vals[] = $like; $vals[] = $like;
        }

        $whereSql = $where? ('WHERE '.implode(' AND ',$where)) : '';

        $countSql = "SELECT COUNT(*) AS total FROM maternal_patients $whereSql";
        if($params){
            $cStmt=$mysqli->prepare($countSql);
            $cStmt->bind_param($params,...$vals);
            $cStmt->execute();
            $cRes=$cStmt->get_result();
            $total = (int)($cRes->fetch_assoc()['total'] ?? 0);
            $cStmt->close();
        } else {
            $cRes=$mysqli->query($countSql);
            $total = (int)($cRes->fetch_assoc()['total'] ?? 0);
        }
        $totalPages = $pageSize? (int)ceil($total/$pageSize) : 1;

        // FIXED: previous join caused duplicates when multiple consults share the same date.
        // Prioritize direct purok_name field over purok_id join since purok_id is often NULL
        $purok_join = '';
        $purok_col = has_col('maternal_patients','purok_name') ? ', mp.purok_name' : '';
        $sql = "
  SELECT mp.mother_id,
         mp.first_name, mp.middle_name, mp.last_name,
         mp.date_of_birth,
         mp.gravida, mp.para, mp.blood_type,
         mp.emergency_contact_name, mp.emergency_contact_number,
         mp.contact_number,
         mp.house_number, mp.street_name{$purok_col}, mp.subdivision_name,

         (
           SELECT COUNT(*) FROM health_records hr_all
           WHERE hr_all.mother_id = mp.mother_id
         ) AS records_count,

         (
           SELECT COUNT(*) FROM health_records hr_r
           WHERE hr_r.mother_id = mp.mother_id
             AND (
               hr_r.vaginal_bleeding=1 OR hr_r.urinary_infection=1 OR hr_r.high_blood_pressure=1
               OR hr_r.fever_38_celsius=1 OR hr_r.pallor=1 OR hr_r.abnormal_abdominal_size=1
               OR hr_r.abnormal_presentation=1 OR hr_r.absent_fetal_heartbeat=1
               OR hr_r.swelling=1 OR hr_r.vaginal_infection=1
             )
         ) AS risk_count,

         lt.consultation_date AS latest_consultation_date,
         lt.pregnancy_age_weeks,
         lt.last_menstruation_date,
         lt.expected_delivery_date,
         (
           IFNULL(lt.vaginal_bleeding,0) + IFNULL(lt.urinary_infection,0) + IFNULL(lt.high_blood_pressure,0) +
           IFNULL(lt.fever_38_celsius,0) + IFNULL(lt.pallor,0) + IFNULL(lt.abnormal_abdominal_size,0) +
           IFNULL(lt.abnormal_presentation,0) + IFNULL(lt.absent_fetal_heartbeat,0) +
           IFNULL(lt.swelling,0) + IFNULL(lt.vaginal_infection,0)
         ) AS latest_risk_score
  FROM maternal_patients mp{$purok_join}
  LEFT JOIN (
     SELECT hr.*
     FROM health_records hr
     WHERE hr.health_record_id = (
        SELECT hr2.health_record_id
        FROM health_records hr2
        WHERE hr2.mother_id = hr.mother_id
        ORDER BY hr2.consultation_date DESC, hr2.health_record_id DESC
        LIMIT 1
     )
  ) lt ON lt.mother_id = mp.mother_id
  $whereSql
  ORDER BY mp.created_at DESC
  LIMIT ? OFFSET ?
";

        $paramsPage = $params . 'ii';
        $valsPage = array_merge($vals, [$pageSize,$offset]);
        $stmt=$mysqli->prepare($sql);
        if($paramsPage){
            $stmt->bind_param($paramsPage,...$valsPage);
        }
        $stmt->execute();
        $res=$stmt->get_result();
        $rows=[];
        while($res && $r=$res->fetch_assoc()){
            $r['full_name'] = display_name($r);
            $rows[]=$r;
        }
        $stmt->close();

        if($riskFilter){
            $rows = array_values(array_filter($rows,function($r) use ($riskFilter){
                $score = (int)($r['risk_count'] ?? 0);
                if($riskFilter==='high') return $score>=2;
                if($riskFilter==='monitor') return $score===1;
                if($riskFilter==='normal') return $score===0;
                return true;
            }));
        }

        echo json_encode([
            'success'=>true,
            'mothers'=>$rows,
            'total_count'=>$total,
            'current_page'=>$page,
            'page_size'=>$pageSize,
            'total_pages'=>$totalPages
        ]);
        exit;
    }

    if (isset($_GET['list_basic'])) {
        $rows=[];
        $res=$mysqli->query("SELECT mother_id, first_name,middle_name,last_name FROM maternal_patients ORDER BY last_name, first_name ASC");
        while($r=$res->fetch_assoc()){
            $r['full_name'] = display_name($r);
            $rows[]=$r;
        }
        echo json_encode(['success'=>true,'mothers'=>$rows]);
        exit;
    }

    fail('Unknown GET action',404);
    
    } catch (Exception $e) {
        fail('Server error: ' . $e->getMessage(), 500);
    }
}

/* ======================= POST ======================= */
if ($method === 'POST') {
    try {
    if (empty($_POST['csrf_token']) || empty($_SESSION['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        fail('CSRF failed',419);
    }

    $user_id = (int)($_SESSION['user_id'] ?? 0);
    if($user_id <= 0){
        fail('Not authenticated (no user id in session). Please re-login.',401);
    }

    /* Combined creation of mother + first consultation (with duplicate check) */
    if(isset($_POST['create_mother_with_consult'])){
        $first_name  = nz($_POST['first_name'] ?? '');
        $middle_name = nz($_POST['middle_name'] ?? '');
        $last_name   = nz($_POST['last_name'] ?? '');
        if(!$first_name || !$last_name){
            fail('First name at Last name ay required.');
        }

        $date_of_birth   = valid_date_or_null(nz($_POST['date_of_birth'] ?? ''));
        $gravida         = ($_POST['gravida'] !== '' && isset($_POST['gravida'])) ? (int)$_POST['gravida'] : null;
        $para            = ($_POST['para'] !== '' && isset($_POST['para'])) ? (int)$_POST['para'] : null;
        $contact_number  = nz($_POST['contact_number'] ?? '');
        $blood_type      = nz($_POST['blood_type'] ?? '');
        $emg_name        = nz($_POST['emergency_contact_name'] ?? '');
        $emg_number      = nz($_POST['emergency_contact_number'] ?? '');
        $house_number    = nz($_POST['house_number'] ?? '');
        $street_name     = nz($_POST['street_name'] ?? '');
        $purok_name      = nz($_POST['purok_name'] ?? '');
        $subdivision     = nz($_POST['subdivision_name'] ?? '');

        // Consultation fields
        $consultation_date      = valid_date_or_null(nz($_POST['consultation_date'] ?? '')) ?: date('Y-m-d');
        $age                    = ($_POST['age'] !== '' && isset($_POST['age'])) ? (int)$_POST['age'] : null;
        $height_cm              = ($_POST['height_cm'] !== '' && isset($_POST['height_cm'])) ? (float)$_POST['height_cm'] : null;
        $weight_kg              = ($_POST['weight_kg'] !== '' && isset($_POST['weight_kg'])) ? (float)$_POST['weight_kg'] : null;
        $bp_sys                 = ($_POST['blood_pressure_systolic'] !== '' && isset($_POST['blood_pressure_systolic'])) ? (int)$_POST['blood_pressure_systolic'] : null;
        $bp_dia                 = ($_POST['blood_pressure_diastolic'] !== '' && isset($_POST['blood_pressure_diastolic'])) ? (int)$_POST['blood_pressure_diastolic'] : null;
        $pregnancy_age_weeks    = ($_POST['pregnancy_age_weeks'] !== '' && isset($_POST['pregnancy_age_weeks'])) ? (int)$_POST['pregnancy_age_weeks'] : null;
        // LMP and EDD are collected during consultations
        $last_menstruation_date = valid_date_or_null(nz($_POST['last_menstruation_date'] ?? ''));
        $expected_delivery_date = valid_date_or_null(nz($_POST['expected_delivery_date'] ?? ''));
        $hgb_result             = nz($_POST['hgb_result'] ?? '');
        $urine_result           = nz($_POST['urine_result'] ?? '');
        $vdrl_result            = nz($_POST['vdrl_result'] ?? '');
        $other_lab_results      = nz($_POST['other_lab_results'] ?? '');

        $riskFields = [
            'vaginal_bleeding','urinary_infection','high_blood_pressure','fever_38_celsius','pallor',
            'abnormal_abdominal_size','abnormal_presentation','absent_fetal_heartbeat','swelling','vaginal_infection'
        ];
        
        $checkboxFields = [
            'iron_folate_prescription','additional_iodine','malaria_prophylaxis','breastfeeding_plan',
            'danger_advice','dental_checkup','emergency_plan','general_risk'
        ];
        
        $notesFields = [
            'iron_folate_notes','additional_iodine_notes','malaria_prophylaxis_notes','breastfeeding_plan_notes',
            'danger_advice_notes','dental_checkup_notes','emergency_plan_notes','general_risk_notes'
        ];

        // DUPLICATE CHECK (enhanced)
        $existing = find_existing_mother($first_name,$last_name,$date_of_birth);

        /* ========= PATCHED EXISTING-MOTHER BRANCH (supports optional overwrite_latest flag) ========= */
        if($existing){

            $updateData = [
                'date_of_birth'            => $date_of_birth,
                'middle_name'              => $middle_name,
                'gravida'                  => $gravida,
                'para'                     => $para,
                'contact_number'           => $contact_number,
                'blood_type'               => $blood_type,
                'emergency_contact_name'   => $emg_name,
                'emergency_contact_number' => $emg_number,
                'house_number'             => $house_number,
                'street_name'              => $street_name,
                'subdivision_name'         => $subdivision
            ];
            if(has_col('maternal_patients','purok_name')){
                $updateData['purok_name'] = $purok_name;
            }
            $updatedCols = enrich_mother_if_blank($existing, $updateData);

            $mother_id = (int)$existing['mother_id'];

            // If client passes overwrite_latest=1 we UPDATE the most recent record instead of INSERT (OPTIONAL mode)
            $overwrite = isset($_POST['overwrite_latest']) && $_POST['overwrite_latest']=='1';

            if($overwrite){
                $stmtL = $mysqli->prepare("SELECT health_record_id FROM health_records WHERE mother_id=? ORDER BY consultation_date DESC, health_record_id DESC LIMIT 1");
                $stmtL->bind_param('i',$mother_id);
                $stmtL->execute();
                $resL = $stmtL->get_result();
                $last = $resL->fetch_assoc();
                $stmtL->close();

                if($last){
                    $fields = [
                        'consultation_date'=>$consultation_date,
                        'age'=>$age,
                        'height_cm'=>$height_cm,
                        'weight_kg'=>$weight_kg,
                        'blood_pressure_systolic'=>$bp_sys,
                        'blood_pressure_diastolic'=>$bp_dia,
                        'pregnancy_age_weeks'=>$pregnancy_age_weeks,
                        'last_menstruation_date'=>$last_menstruation_date,
                        'expected_delivery_date'=>$expected_delivery_date,
                        'hgb_result'=>$hgb_result,
                        'urine_result'=>$urine_result,
                        'vdrl_result'=>$vdrl_result,
                        'other_lab_results'=>$other_lab_results
                    ];
                    foreach($riskFields as $rf){
                        $fields[$rf] = risk_flag_value($rf);
                    }
                    foreach($checkboxFields as $cf){
                        $fields[$cf] = risk_flag_value($cf);
                    }
                    foreach($notesFields as $nf){
                        $fields[$nf] = get_notes_value($nf);
                    }
                    $sets=[]; $types=''; $vals=[];
                    foreach($fields as $col=>$val){
                        $sets[]="$col=?";
                        if(is_int($val)) $types.='i';
                        else if(is_float($val)) $types.='d';
                        else $types.='s';
                        $vals[]=$val;
                    }
                    $types.='i';
                    $vals[]=(int)$last['health_record_id'];
                    $sqlU="UPDATE health_records SET ".implode(',',$sets)." WHERE health_record_id=?";
                    $stmtU=$mysqli->prepare($sqlU);
                    if(!$stmtU) fail('Overwrite prepare failed: '.$mysqli->error,500);
                    $stmtU->bind_param($types, ...$vals);
                    if(!$stmtU->execute()) fail('Overwrite failed: '.$stmtU->error,500);
                    $stmtU->close();

                    echo json_encode([
                        'success'=>true,
                        'combined'=>true,
                        'mother_existing'=>true,
                        'mother_id'=>$mother_id,
                        'consultation_id'=>$last['health_record_id'],
                        'overwritten'=>true,
                        'updated_fields'=>$updatedCols
                    ]);
                    exit;
                }
                // if no existing consult, fall through to insert new
            }

            // DEFAULT: INSERT new consultation (keeps full history)
            $cCols = [
                'mother_id','consultation_date','age','height_cm','weight_kg',
                'blood_pressure_systolic','blood_pressure_diastolic','pregnancy_age_weeks',
                'last_menstruation_date','expected_delivery_date','hgb_result','urine_result',
                'vdrl_result','other_lab_results','recorded_by'
            ];
            $cVals = [
                $mother_id,
                $consultation_date,
                $age,
                $height_cm,
                $weight_kg,
                $bp_sys,
                $bp_dia,
                $pregnancy_age_weeks,
                $last_menstruation_date,
                $expected_delivery_date,
                $hgb_result,
                $urine_result,
                $vdrl_result,
                $other_lab_results,
                $user_id
            ];
            if(has_col('health_records','created_by')){
                $cCols[]='created_by'; $cVals[]=$user_id;
            }
            foreach($riskFields as $rf){
                $cCols[]=$rf;
                $cVals[]=risk_flag_value($rf);
            }
            foreach($checkboxFields as $cf){
                $cCols[]=$cf;
                $cVals[]=risk_flag_value($cf);
            }
            foreach($notesFields as $nf){
                $cCols[]=$nf;
                $cVals[]=get_notes_value($nf);
            }
            $cTypes=''; $bindVals=[];
            foreach($cVals as $v){
                if(is_int($v)) $cTypes.='i';
                else if(is_float($v)) $cTypes.='d';
                else $cTypes.='s';
                $bindVals[]=$v;
            }
            $placeholders = implode(',', array_fill(0,count($cCols),'?'));
            $cSql="INSERT INTO health_records (".implode(',',$cCols).") VALUES ($placeholders)";
            $stmt2=$mysqli->prepare($cSql);
            if(!$stmt2) fail('Prepare consult failed: '.$mysqli->error,500);
            $bind2=[]; $bind2[]=&$cTypes;
            foreach($bindVals as $k=>$v){ $bind2[]=&$bindVals[$k]; }
            call_user_func_array([$stmt2,'bind_param'],$bind2);
            if(!$stmt2->execute()) fail('Insert consult failed: '.$stmt2->error,500);
            $consult_id = $stmt2->insert_id;
            $stmt2->close();

            echo json_encode([
                'success'=>true,
                'combined'=>true,
                'mother_existing'=>true,
                'mother_id'=>$mother_id,
                'consultation_id'=>$consult_id,
                'overwritten'=>false,
                'updated_fields'=>$updatedCols
            ]);
            exit;
        }
        /* ========= END PATCH ========= */

        // NEW mother + consult
        $mysqli->begin_transaction();
        try{
            $cols=[]; $ph=[]; $vals=[]; $types='';
            $add=function($col,$val,$type) use (&$cols,&$ph,&$vals,&$types){
                if($val===null){
                    $cols[]=$col; $ph[]='NULL';
                }else{
                    $cols[]=$col; $ph[]='?'; $vals[]=$val; $types.=$type;
                }
            };
            $add('first_name',$first_name,'s');
            $add('middle_name',$middle_name,'s');
            $add('last_name',$last_name,'s');
            $add('contact_number',$contact_number,'s');
            $add('date_of_birth',$date_of_birth,'s');
            $add('gravida',$gravida,'i');
            $add('para',$para,'i');
            $add('blood_type',$blood_type,'s');
            $add('emergency_contact_name',$emg_name,'s');
            $add('emergency_contact_number',$emg_number,'s');
            $add('house_number',$house_number,'s');
            $add('street_name',$street_name,'s');
            if(has_col('maternal_patients','purok_name')){
                $add('purok_name',$purok_name,'s');
            }
            $add('subdivision_name',$subdivision,'s');
            if(has_col('maternal_patients','created_by')){
                $add('created_by',$user_id,'i');
            }

            $sql="INSERT INTO maternal_patients (".implode(',',$cols).") VALUES (".implode(',',$ph).")";
            $stmt=$mysqli->prepare($sql);
            if(!$stmt) throw new Exception('Prepare mother failed: '.$mysqli->error);
            if($vals){
                $bind=[]; $bind[]=&$types;
                foreach($vals as $k=>$v){ $bind[]=&$vals[$k]; }
                call_user_func_array([$stmt,'bind_param'],$bind);
            }
            if(!$stmt->execute()) throw new Exception('Insert mother failed: '.$stmt->error);
            $mother_id = $stmt->insert_id;
            $stmt->close();

            $cCols = [
                'mother_id','consultation_date','age','height_cm','weight_kg',
                'blood_pressure_systolic','blood_pressure_diastolic','pregnancy_age_weeks',
                'last_menstruation_date','expected_delivery_date','hgb_result','urine_result',
                'vdrl_result','other_lab_results'
            ];
            $cVals = [
                $mother_id,
                $consultation_date,
                $age,
                $height_cm,
                $weight_kg,
                $bp_sys,
                $bp_dia,
                $pregnancy_age_weeks,
                $last_menstruation_date,
                $expected_delivery_date,
                $hgb_result,
                $urine_result,
                $vdrl_result,
                $other_lab_results
            ];

            $cCols[]='recorded_by';
            $cVals[]=$user_id;

            if(has_col('health_records','created_by')){
                $cCols[]='created_by';
                $cVals[]=$user_id;
            }
            foreach($riskFields as $rf){
                $cCols[]=$rf;
                $cVals[]=risk_flag_value($rf);
            }
            foreach($checkboxFields as $cf){
                $cCols[]=$cf;
                $cVals[]=risk_flag_value($cf);
            }
            foreach($notesFields as $nf){
                $cCols[]=$nf;
                $cVals[]=get_notes_value($nf);
            }
            $cTypes=''; $bindVals=[];
            foreach($cVals as $v){
                if(is_int($v)) $cTypes.='i';
                else if(is_float($v)) $cTypes.='d';
                else $cTypes.='s';
                $bindVals[]=$v;
            }
            $placeholders = implode(',', array_fill(0,count($cCols),'?'));
            $cSql="INSERT INTO health_records (".implode(',',$cCols).") VALUES ($placeholders)";
            $stmt2=$mysqli->prepare($cSql);
            if(!$stmt2) throw new Exception('Prepare consult failed: '.$mysqli->error);
            $bind2=[]; $bind2[]=&$cTypes;
            foreach($bindVals as $k=>$v){ $bind2[]=&$bindVals[$k]; }
            call_user_func_array([$stmt2,'bind_param'],$bind2);
            if(!$stmt2->execute()) throw new Exception('Insert consult failed: '.$stmt2->error);
            $consult_id = $stmt2->insert_id;
            $stmt2->close();

            $mysqli->commit();
            echo json_encode([
                'success'=>true,
                'combined'=>true,
                'mother_existing'=>false,
                'mother_id'=>$mother_id,
                'consultation_id'=>$consult_id
            ]);
            exit;

        }catch(Exception $e){
            $mysqli->rollback();
            fail($e->getMessage(),500);
        }
    }

    /* -------- Single mother creation (step1 only) with duplicate check -------- */
    $first_name  = nz($_POST['first_name'] ?? '');
    $middle_name = nz($_POST['middle_name'] ?? '');
    $last_name   = nz($_POST['last_name'] ?? '');
    $contact_number  = nz($_POST['contact_number'] ?? '');

    $date_of_birth   = nz($_POST['date_of_birth'] ?? '');
    if ($date_of_birth && !preg_match('/^\d{4}-\d{2}-\d{2}$/',$date_of_birth)) $date_of_birth = null;

    $gravida         = ($_POST['gravida'] !== '' && isset($_POST['gravida'])) ? (int)$_POST['gravida'] : null;
    $para            = ($_POST['para'] !== '' && isset($_POST['para'])) ? (int)$_POST['para'] : null;
    $blood_type      = nz($_POST['blood_type'] ?? '');
    $emg_name        = nz($_POST['emergency_contact_name'] ?? '');
    $emg_number      = nz($_POST['emergency_contact_number'] ?? '');
    $house_number    = nz($_POST['house_number'] ?? '');
    $street_name     = nz($_POST['street_name'] ?? '');
    $purok_name      = nz($_POST['purok_name'] ?? '');
    $subdivision     = nz($_POST['subdivision_name'] ?? '');

    if (!$first_name || !$last_name) fail('First name at Last name ay required.');

    $existing = find_existing_mother($first_name,$last_name,$date_of_birth);
    if($existing){
        $updates = [];
        $paramsU = ''; $valsU = [];
        $map = [
            'date_of_birth'=>$date_of_birth,
            'middle_name'=>$middle_name,
            'contact_number'=>$contact_number,
            'gravida'=>$gravida,
            'para'=>$para,
            'blood_type'=>$blood_type,
            'emergency_contact_name'=>$emg_name,
            'emergency_contact_number'=>$emg_number,
            'house_number'=>$house_number,
            'street_name'=>$street_name,
            'subdivision_name'=>$subdivision
        ];
        if(has_col('maternal_patients','purok_name')){
            $map['purok_name'] = $purok_name;
        }
        foreach($map as $col=>$val){
            if($val!==null && $val!=='' && (!isset($existing[$col]) || $existing[$col]==='' || $existing[$col]===null)){
                $updates[]="$col=?";
                $paramsU .= (is_int($val)?'i':'s');
                $valsU[]=$val;
            }
        }
        if(!empty($updates)){
            $sqlU="UPDATE maternal_patients SET ".implode(',',$updates)." WHERE mother_id=?";
            $paramsU.='i';
            $valsU[]=$existing['mother_id'];
            $stmtU=$mysqli->prepare($sqlU);
            $stmtU->bind_param($paramsU, ...$valsU);
            $stmtU->execute();
            $stmtU->close();
        }
        echo json_encode([
            'success'=>true,
            'existing'=>true,
            'mother_id'=>$existing['mother_id'],
            'updated_fields'=>count($updates)
        ]);
        exit;
    }

    $cols=[]; $ph=[]; $vals=[]; $types='';
    $add=function($col,$val,$type) use (&$cols,&$ph,&$vals,&$types){
        if($val===null){
            $cols[]=$col; $ph[]='NULL';
        }else{
            $cols[]=$col; $ph[]='?'; $vals[]=$val; $types.=$type;
        }
    };
    $add('first_name',$first_name,'s');
    $add('middle_name',$middle_name,'s');
    $add('last_name',$last_name,'s');
    $add('contact_number',$contact_number,'s');
    $add('date_of_birth',$date_of_birth,'s');
    $add('gravida',$gravida,'i');
    $add('para',$para,'i');
    $add('blood_type',$blood_type,'s');
    $add('emergency_contact_name',$emg_name,'s');
    $add('emergency_contact_number',$emg_number,'s');
    $add('house_number',$house_number,'s');
    $add('street_name',$street_name,'s');
    if(has_col('maternal_patients','purok_name')){
        $add('purok_name',$purok_name,'s');
    }
    $add('subdivision_name',$subdivision,'s');
    if(has_col('maternal_patients','created_by')){
        $add('created_by',$user_id,'i');
    }

    $sql="INSERT INTO maternal_patients (".implode(',',$cols).") VALUES (".implode(',',$ph).")";
    $stmt=$mysqli->prepare($sql);
    if(!$stmt) fail('Prepare failed: '.$mysqli->error,500);
    if($vals){
        $bind=[]; $bind[]=&$types;
        foreach($vals as $k=>$v){ $bind[]=&$vals[$k]; }
        call_user_func_array([$stmt,'bind_param'],$bind);
    }
    if(!$stmt->execute()) fail('Insert failed: '.$stmt->error,500);
    $mid=$stmt->insert_id;
    $stmt->close();

    echo json_encode(['success'=>true,'existing'=>false,'mother_id'=>$mid]);
    exit;
    
    } catch (Exception $e) {
        fail('Server error: ' . $e->getMessage(), 500);
    }
}

fail('Invalid method',405);

/*
NOTE (Optional DB hard protection):
You can add a unique index to further prevent duplicates at the database level:
ALTER TABLE maternal_patients
  ADD UNIQUE KEY uniq_mother_name_dob (LOWER(first_name), LOWER(last_name), date_of_birth);

MySQL doesn’t allow function-based indexes prior to 8.0 generated columns; instead you can:
ALTER TABLE maternal_patients
  ADD COLUMN first_name_lc VARCHAR(191) GENERATED ALWAYS AS (LOWER(first_name)) STORED,
  ADD COLUMN last_name_lc  VARCHAR(191) GENERATED ALWAYS AS (LOWER(last_name)) STORED,
  ADD UNIQUE KEY uniq_mother_name_dob (first_name_lc,last_name_lc,date_of_birth);

Added feature: overwrite_latest (optional POST flag inside create_mother_with_consult)
If overwrite_latest=1 AND the mother already exists, the latest consultation
will be UPDATED instead of inserting a new health_records row. Default behavior
(when flag absent or 0) still INSERTS a new consultation to preserve full history.
*/
?>