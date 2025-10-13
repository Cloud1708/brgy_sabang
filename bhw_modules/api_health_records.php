<?php
date_default_timezone_set('Asia/Manila');
require_once __DIR__.'/../inc/db.php';
require_once __DIR__.'/../inc/auth.php';
require_role(['BHW']);

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors','0');
error_reporting(E_ALL);

set_error_handler(function($sev,$msg,$file,$line){
    http_response_code(500);
    echo json_encode([
        'success'=>false,
        'error'=>"PHP Error: $msg",
        'file'=>$file,
        'line'=>$line
    ]);
    exit;
});
set_exception_handler(function($ex){
    http_response_code(500);
    echo json_encode([
        'success'=>false,
        'error'=>"Exception: ".$ex->getMessage()
    ]);
    exit;
});

function fail($m,$c=400){
    http_response_code($c);
    echo json_encode(['success'=>false,'error'=>$m]);
    exit;
}

/* ---------- Column existence cache (for graceful rollout) ---------- */
function hr_has_col($col){
    static $cache = null;
    global $mysqli;
    if($cache === null){
        $cache = [];
        $res = $mysqli->query("SHOW COLUMNS FROM health_records");
        if($res){
            while($r=$res->fetch_assoc()){
                $cache[strtolower($r['Field'])] = true;
            }
        }
    }
    return isset($cache[strtolower($col)]);
}

/* Listahan ng intervention flags at katumbas na notes columns */
function hr_intervention_flags() {
    return [
        'iron_folate_prescription',
        'additional_iodine',
        'malaria_prophylaxis',
        'breastfeeding_plan',
        'danger_advice',
        'dental_checkup',
        'emergency_plan',
        'general_risk'
    ];
}
function hr_intervention_notes_cols() {
    return [
        'iron_folate_notes',
        'additional_iodine_notes',
        'malaria_prophylaxis_notes',
        'breastfeeding_plan_notes',
        'danger_advice_notes',
        'dental_checkup_notes',
        'emergency_plan_notes',
        'general_risk_notes'
    ];
}

$method = $_SERVER['REQUEST_METHOD'];

/* =================== GET =================== */
if ($method === 'GET') {

    // Global list of all health records (now includes LMP & EDD and interventions if columns exist)
    if (isset($_GET['all'])) {
        $limit = isset($_GET['limit']) ? max(1,min(1000,(int)$_GET['limit'])) : 500;

        $cols = [
            'hr.health_record_id',
            'hr.mother_id',
            'CONCAT(m.first_name, " ", COALESCE(m.middle_name, ""), " ", m.last_name) as full_name',
            'hr.consultation_date',
            'hr.last_menstruation_date',
            'hr.expected_delivery_date',
            'hr.pregnancy_age_weeks',
            'hr.age',
            'hr.height_cm',
            'hr.weight_kg',
            'hr.blood_pressure_systolic',
            'hr.blood_pressure_diastolic',
            '(hr.vaginal_bleeding + hr.urinary_infection + hr.high_blood_pressure +
              hr.fever_38_celsius + hr.pallor + hr.abnormal_abdominal_size +
              hr.abnormal_presentation + hr.absent_fetal_heartbeat + hr.swelling +
              hr.vaginal_infection) AS risk_score',
            'hr.vaginal_bleeding','hr.urinary_infection','hr.high_blood_pressure',
            'hr.fever_38_celsius','hr.pallor','hr.abnormal_abdominal_size',
            'hr.abnormal_presentation','hr.absent_fetal_heartbeat','hr.swelling',
            'hr.vaginal_infection',
            'hr.hgb_result','hr.urine_result','hr.vdrl_result','hr.other_lab_results',
            'hr.created_at'
        ];

        // Append intervention flag columns if they exist
        foreach (hr_intervention_flags() as $c) {
            if (hr_has_col($c)) $cols[] = "hr.$c";
        }
        // Append intervention notes columns if they exist
        foreach (hr_intervention_notes_cols() as $c) {
            if (hr_has_col($c)) $cols[] = "hr.$c";
        }

        $sql = "
          SELECT ".implode(",", $cols)."
          FROM health_records hr
          JOIN maternal_patients m ON m.mother_id = hr.mother_id
          ORDER BY hr.consultation_date DESC, hr.health_record_id DESC
          LIMIT ?
        ";
        $rows=[];
        $stmt=$mysqli->prepare($sql);
        if(!$stmt) fail('Prepare failed: '.$mysqli->error,500);
        $stmt->bind_param('i',$limit);
        $stmt->execute();
        $res=$stmt->get_result();
        while($r=$res->fetch_assoc()) $rows[]=$r;
        $stmt->close();
        echo json_encode(['success'=>true,'records'=>$rows,'count'=>count($rows)]);
        exit;
    }

    // Consultation history per mother
    if (isset($_GET['list']) && isset($_GET['mother_id'])) {
        $mother_id = (int)$_GET['mother_id'];
        if ($mother_id <= 0) fail('Invalid mother_id');

        $baseCols = [
          'health_record_id','consultation_date','age','height_cm','weight_kg',
          'last_menstruation_date','expected_delivery_date','pregnancy_age_weeks',
          'blood_pressure_systolic','blood_pressure_diastolic',
          'vaginal_bleeding','urinary_infection','high_blood_pressure',
          'fever_38_celsius','pallor','abnormal_abdominal_size',
          'abnormal_presentation','absent_fetal_heartbeat','swelling','vaginal_infection',
          'hgb_result','urine_result','vdrl_result','other_lab_results','created_at'
        ];
        foreach (hr_intervention_flags() as $c) {
            if (hr_has_col($c)) $baseCols[] = $c;
        }
        foreach (hr_intervention_notes_cols() as $c) {
            if (hr_has_col($c)) $baseCols[] = $c;
        }

        $rows = [];
        $stmt = $mysqli->prepare("
          SELECT ".implode(',',$baseCols)."
          FROM health_records
          WHERE mother_id=?
          ORDER BY consultation_date DESC, health_record_id DESC
          LIMIT 300
        ");
        if(!$stmt) fail('Prepare failed: '.$mysqli->error,500);
        $stmt->bind_param('i',$mother_id);
        $stmt->execute();
        $res = $stmt->get_result();
        while($r = $res->fetch_assoc()) $rows[] = $r;
        $stmt->close();

        echo json_encode(['success'=>true,'records'=>$rows]);
        exit;
    }

    // Risk summary (latest risky)
    if (isset($_GET['risk_summary'])) {
        $rows=[];
        $sql = "
          SELECT m.mother_id, CONCAT(m.first_name, ' ', COALESCE(m.middle_name, ''), ' ', m.last_name) as full_name,
            hr.consultation_date, hr.pregnancy_age_weeks,
            (hr.vaginal_bleeding + hr.urinary_infection + hr.high_blood_pressure +
             hr.fever_38_celsius + hr.pallor + hr.abnormal_abdominal_size +
             hr.abnormal_presentation + hr.absent_fetal_heartbeat + hr.swelling +
             hr.vaginal_infection) AS risk_score,
            hr.vaginal_bleeding, hr.urinary_infection, hr.high_blood_pressure,
            hr.fever_38_celsius, hr.pallor, hr.abnormal_abdominal_size,
            hr.abnormal_presentation, hr.absent_fetal_heartbeat, hr.swelling,
            hr.vaginal_infection
         FROM maternal_patients m
          JOIN (
            SELECT x.*
            FROM health_records x
            JOIN (
              SELECT mother_id, MAX(consultation_date) AS max_date
              FROM health_records
              GROUP BY mother_id
            ) r ON r.mother_id = x.mother_id AND r.max_date = x.consultation_date
          ) hr ON hr.mother_id = m.mother_id
          WHERE (hr.vaginal_bleeding=1 OR hr.urinary_infection=1 OR hr.high_blood_pressure=1
             OR hr.fever_38_celsius=1 OR hr.pallor=1 OR hr.abnormal_abdominal_size=1
             OR hr.abnormal_presentation=1 OR hr.absent_fetal_heartbeat=1
             OR hr.swelling=1 OR hr.vaginal_infection=1)
          ORDER BY risk_score DESC, hr.consultation_date DESC
          LIMIT 300
        ";
        $res=$mysqli->query($sql);
        if($res){
            while($r=$res->fetch_assoc()) $rows[]=$r;
        }
        echo json_encode(['success'=>true,'risks'=>$rows]);
        exit;
    }

    // Recent consultations (global latest)
    if (isset($_GET['recent_consults'])) {
        $limit = isset($_GET['limit']) ? max(1,min(50,(int)$_GET['limit'])) : 20;
        $rows=[];
        $stmt = $mysqli->prepare("
          SELECT hr.health_record_id, hr.consultation_date, CONCAT(m.first_name, ' ', COALESCE(m.middle_name, ''), ' ', m.last_name) as full_name,
                 hr.pregnancy_age_weeks,
                 hr.high_blood_pressure, hr.vaginal_bleeding, hr.fever_38_celsius,
                 hr.swelling, hr.urinary_infection
          FROM health_records hr
          JOIN maternal_patients m ON m.mother_id=hr.mother_id
          ORDER BY hr.consultation_date DESC, hr.health_record_id DESC
          LIMIT ?
        ");
        if(!$stmt) fail('Prepare failed: '.$mysqli->error,500);
        $stmt->bind_param('i',$limit);
        $stmt->execute();
        $res=$stmt->get_result();
        while($r=$res->fetch_assoc()) $rows[]=$r;
        $stmt->close();
        echo json_encode(['success'=>true,'recent_consults'=>$rows]);
        exit;
    }

    fail('Unknown GET action',404);
}

/* =================== POST =================== */
if ($method === 'POST') {

    if (empty($_POST['csrf_token']) || empty($_SESSION['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        fail('CSRF failed',419);
    }

    $mother_id = (int)($_POST['mother_id'] ?? 0);
    $consultation_date = $_POST['consultation_date'] ?? '';
    if ($mother_id <= 0 || !$consultation_date) fail('mother_id at consultation_date ay required.');

    $age         = ($_POST['age'] !== '' ? (int)$_POST['age'] : null);
    $height_cm   = ($_POST['height_cm'] !== '' ? (float)$_POST['height_cm'] : null);
    $weight_kg   = ($_POST['weight_kg'] !== '' ? (float)$_POST['weight_kg'] : null);
    // LMP and EDD from consultation form with date validation
    $lmp_raw     = trim($_POST['last_menstruation_date'] ?? '');
    $edd_raw     = trim($_POST['expected_delivery_date'] ?? '');
    
    // Debug: Log received LMP and EDD data
    error_log('Health Records API - LMP raw: ' . $lmp_raw . ', EDD raw: ' . $edd_raw);
    
    $lmp = null;
    if ($lmp_raw !== '') {
        // Validate date format (YYYY-MM-DD)
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $lmp_raw)) {
            $lmp = $lmp_raw;
            error_log('Health Records API - LMP validated and set: ' . $lmp);
        } else {
            error_log('Health Records API - LMP format invalid: ' . $lmp_raw);
        }
    }
    
    $edd = null;
    if ($edd_raw !== '') {
        // Validate date format (YYYY-MM-DD)
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $edd_raw)) {
            $edd = $edd_raw;
            error_log('Health Records API - EDD validated and set: ' . $edd);
        } else {
            error_log('Health Records API - EDD format invalid: ' . $edd_raw);
        }
    }
    $preg_weeks  = ($_POST['pregnancy_age_weeks'] !== '' ? (int)$_POST['pregnancy_age_weeks'] : null);

    $bp_sys      = ($_POST['blood_pressure_systolic'] !== '' ? (int)$_POST['blood_pressure_systolic'] : null);
    $bp_dia      = ($_POST['blood_pressure_diastolic'] !== '' ? (int)$_POST['blood_pressure_diastolic'] : null);

    $flagKeys = [
      'vaginal_bleeding','urinary_infection','high_blood_pressure',
      'fever_38_celsius','pallor','abnormal_abdominal_size',
      'abnormal_presentation','absent_fetal_heartbeat','swelling','vaginal_infection'
    ];
    $flags = [];
    foreach($flagKeys as $k){
        $flags[$k] = isset($_POST[$k]) ? 1 : 0;
    }

    if (($bp_sys !== null && $bp_sys >= 140) || ($bp_dia !== null && $bp_dia >= 90)) {
        $flags['high_blood_pressure'] = 1;
    }

    if ($preg_weeks === null) {
        // Get mother's LMP and EDD from the latest health record
        $stmt = $mysqli->prepare("SELECT last_menstruation_date, expected_delivery_date FROM health_records WHERE mother_id = ? ORDER BY consultation_date DESC LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('i', $mother_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $motherData = $result->num_rows > 0 ? $result->fetch_assoc() : null;
            $stmt->close();
        } else {
            $motherData = null;
        }
        
        $cd = strtotime($consultation_date);
        if ($motherData && $motherData['last_menstruation_date']) {
            $diffDays = floor(($cd - strtotime($motherData['last_menstruation_date']))/86400);
            if ($diffDays >= 0) $preg_weeks = (int)floor($diffDays/7);
        } elseif ($motherData && $motherData['expected_delivery_date']) {
            $diffDaysToEdd = floor((strtotime($motherData['expected_delivery_date']) - $cd)/86400);
            $weeksToEdd = $diffDaysToEdd / 7;
            $preg_weeks = (int)round(40 - $weeksToEdd);
        }
    }

    $hgb       = ($tmp = trim($_POST['hgb_result'] ?? '')) === '' ? null : $tmp;
    $urine     = ($tmp = trim($_POST['urine_result'] ?? '')) === '' ? null : $tmp;
    $vdrl      = ($tmp = trim($_POST['vdrl_result'] ?? '')) === '' ? null : $tmp;
    $other_lab = ($tmp = trim($_POST['other_lab_results'] ?? '')) === '' ? null : $tmp;

    $recorded_by = (int)($_SESSION['user_id'] ?? 0);

    /* -------- INTERVENTION / ACTION FIELDS -------- */
    $actionKeys = hr_intervention_flags();
    $actions = [];
    foreach($actionKeys as $ak){
        $actions[$ak] = isset($_POST[$ak]) ? 1 : 0;
    }

    /* Notes para sa bawat intervention (kung meron) */
    $noteKeys = hr_intervention_notes_cols();
    $notes = [];
    foreach($noteKeys as $nk){
        $val = isset($_POST[$nk]) ? trim($_POST[$nk]) : null;
        if ($val === '') $val = null;
        $notes[$nk] = $val;
    }
    
    // Debug: Log received notes data
    error_log('Health Records API - Notes data received: ' . json_encode($notes));

    $next_visit_date = $_POST['next_visit_date'] ?? null;
    if($next_visit_date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/',$next_visit_date)) $next_visit_date = null;

    $cols = [];
    $ph   = [];
    $vals = [];
    $types= '';

    $add = function($col, $val, $type) use (&$cols,&$ph,&$vals,&$types) {
        if ($val === null) {
            $cols[] = $col;
            $ph[]   = 'NULL';
        } else {
            $cols[] = $col;
            $ph[]   = '?';
            $vals[] = $val;
            $types .= $type;
        }
    };

    $add('mother_id', $mother_id, 'i');
    $add('consultation_date', $consultation_date, 's');

    $add('age', $age, 'i');
    $add('height_cm', $height_cm, 'd');
    $add('last_menstruation_date', $lmp, 's');
    $add('expected_delivery_date', $edd, 's');
    $add('pregnancy_age_weeks', $preg_weeks, 'i');

    foreach([
        'vaginal_bleeding','urinary_infection','weight_kg',
        'blood_pressure_systolic','blood_pressure_diastolic',
        'high_blood_pressure','fever_38_celsius','pallor','abnormal_abdominal_size',
        'abnormal_presentation','absent_fetal_heartbeat','swelling','vaginal_infection'
    ] as $key){
        if ($key === 'weight_kg') {
            $add('weight_kg',$weight_kg,'d');
        } elseif ($key === 'blood_pressure_systolic') {
            $add('blood_pressure_systolic',$bp_sys,'i');
        } elseif ($key === 'blood_pressure_diastolic') {
            $add('blood_pressure_diastolic',$bp_dia,'i');
        } else {
            $add($key,$flags[$key] ?? 0,'i');
        }
    }

    $add('hgb_result',$hgb,'s');
    $add('urine_result',$urine,'s');
    $add('vdrl_result',$vdrl,'s');
    $add('other_lab_results',$other_lab,'s');

    /* Conditionally add intervention flag columns if they exist */
    foreach($actionKeys as $ak){
        if(hr_has_col($ak)) $add($ak, $actions[$ak],'i');
    }
    /* Conditionally add intervention notes columns if they exist */
    foreach($noteKeys as $nk){
        if(hr_has_col($nk)) $add($nk, $notes[$nk],'s');
    }

    if(hr_has_col('next_visit_date')) $add('next_visit_date',$next_visit_date,'s');

    $add('recorded_by',$recorded_by,'i');

    $sql = "INSERT INTO health_records (".implode(',',$cols).") VALUES (".implode(',',$ph).")";
    $stmt = $mysqli->prepare($sql);
    if(!$stmt) fail('Prepare failed: '.$mysqli->error,500);

    if (!empty($vals)) {
        $bindParams = [];
        $bindParams[] = &$types;
        foreach ($vals as $k=>$v){
            $bindParams[] = &$vals[$k];
        }
        call_user_func_array([$stmt,'bind_param'],$bindParams);
    }

    if(!$stmt->execute()){
        fail('Insert error: '.$stmt->error,500);
    }
    $id = $stmt->insert_id;
    $stmt->close();

    echo json_encode([
        'success'=>true,
        'health_record_id'=>$id,
        'computed_pregnancy_weeks'=>$preg_weeks
    ]);
    exit;
}

fail('Invalid method',405);