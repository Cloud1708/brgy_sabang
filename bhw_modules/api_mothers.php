<?php
require_once __DIR__.'/../inc/db.php';
require_once __DIR__.'/../inc/auth.php';
require_role(['BHW']);

if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');

ini_set('display_errors','0');
error_reporting(E_ALL);

function fail($msg,$code=400){
  http_response_code($code);
  echo json_encode(['success'=>false,'error'=>$msg]);
  exit;
}
function nz($s){
  $s = trim((string)$s);
  return $s === '' ? null : $s;
}
function display_name($r){
    return trim(implode(' ', array_filter([$r['first_name']??'', $r['middle_name']??'', $r['last_name']??''])));
}

$method = $_SERVER['REQUEST_METHOD'];

/* ============== GET ============== */
if ($method === 'GET') {

    if (isset($_GET['list'])) {
        $rows = [];
        $sql = "
          SELECT m.mother_id,
                 m.first_name,m.middle_name,m.last_name,
                 m.date_of_birth,
                 m.gravida,
                 m.para,
                 m.blood_type,
                 m.emergency_contact_name,
                 m.emergency_contact_number,
                 m.contact_number,
                 m.house_number,m.street_name,m.subdivision_name,
                 (
                   SELECT COUNT(*) FROM health_records hr WHERE hr.mother_id = m.mother_id
                 ) AS records_count,
                 (
                   SELECT MAX(consultation_date) FROM health_records hr2 WHERE hr2.mother_id = m.mother_id
                 ) AS last_consultation_date,
                 (
                   SELECT COUNT(*) FROM health_records hr3
                   WHERE hr3.mother_id = m.mother_id
                     AND (
                       hr3.vaginal_bleeding=1 OR hr3.urinary_infection=1 OR hr3.high_blood_pressure=1
                       OR hr3.fever_38_celsius=1 OR hr3.pallor=1 OR hr3.abnormal_abdominal_size=1
                       OR hr3.abnormal_presentation=1 OR hr3.absent_fetal_heartbeat=1
                       OR hr3.swelling=1 OR hr3.vaginal_infection=1
                     )
                 ) AS risk_count
          FROM mothers_caregivers m
          ORDER BY m.created_at DESC
          LIMIT 600
        ";
        $res = $mysqli->query($sql);
        while($res && $r = $res->fetch_assoc()){
            $r['full_name'] = display_name($r);
            $rows[] = $r;
        }
        echo json_encode(['success'=>true,'mothers'=>$rows]);
        exit;
    }

    if (isset($_GET['list_basic'])) {
        $rows=[];
        $res = $mysqli->query("SELECT mother_id, first_name,middle_name,last_name FROM mothers_caregivers ORDER BY last_name,first_name ASC");
        while($r=$res->fetch_assoc()){
            $r['full_name'] = display_name($r);
            $rows[]=$r;
        }
        echo json_encode(['success'=>true,'mothers'=>$rows]);
        exit;
    }

    fail('Unknown GET action',404);
}

/* ============== POST ============== */
if ($method === 'POST') {

    if (empty($_POST['csrf_token']) || empty($_SESSION['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        fail('CSRF failed',419);
    }

    $first_name  = nz($_POST['first_name'] ?? '');
    $middle_name = nz($_POST['middle_name'] ?? '');
    $last_name   = nz($_POST['last_name'] ?? '');
    $contact_number = nz($_POST['contact_number'] ?? '');
    $date_of_birth = nz($_POST['date_of_birth'] ?? '');
    if ($date_of_birth && !preg_match('/^\d{4}-\d{2}-\d{2}$/',$date_of_birth)) $date_of_birth = null;
    $gravida = ($_POST['gravida'] !== '' && isset($_POST['gravida'])) ? (int)$_POST['gravida'] : null;
    $para    = ($_POST['para'] !== '' && isset($_POST['para'])) ? (int)$_POST['para'] : null;
    $blood_type = nz($_POST['blood_type'] ?? '');
    $emg_name   = nz($_POST['emergency_contact_name'] ?? '');
    $emg_number = nz($_POST['emergency_contact_number'] ?? '');

    $house_number = nz($_POST['house_number'] ?? '');
    $street_name  = nz($_POST['street_name'] ?? '');
    $subdivision  = nz($_POST['subdivision_name'] ?? '');

    if(!$first_name || !$last_name){
        fail('First name at Last name ay required.');
    }

    $user_id = (int)($_SESSION['user_id'] ?? 0);

    // Dynamic insert (NULL aware)
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
    $add('subdivision_name',$subdivision,'s');
    $add('created_by',$user_id,'i');

    $sql="INSERT INTO mothers_caregivers (".implode(',',$cols).") VALUES (".implode(',',$ph).")";
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

    echo json_encode(['success'=>true,'mother_id'=>$mid]);
    exit;
}

fail('Invalid method',405);