<?php
require_once __DIR__.'/../inc/db.php';
require_once __DIR__.'/../inc/auth.php';
require_role(['BHW']);

if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');

/*
  Simple, minimal fields version
  Extra maternal fields captured:
    - date_of_birth
    - gravida
    - para
    - blood_type
    - emergency_contact_name
    - emergency_contact_number
*/

ini_set('display_errors','0');  // prevent HTML warnings breaking JSON
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

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {

    if (isset($_GET['list'])) {
        $rows = [];
        $sql = "
          SELECT m.mother_id,
                 m.full_name,
                 m.date_of_birth,
                 m.gravida,
                 m.para,
                 m.blood_type,
                 m.emergency_contact_name,
                 m.emergency_contact_number,
                 m.address_details,
                 m.contact_number,
                 p.purok_name,
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
          LEFT JOIN puroks p ON p.purok_id = m.purok_id
          ORDER BY m.created_at DESC
          LIMIT 600
        ";
        $res = $mysqli->query($sql);
        while($res && $r = $res->fetch_assoc()){
            $rows[] = $r;
        }
        echo json_encode(['success'=>true,'mothers'=>$rows]);
        exit;
    }

    if (isset($_GET['list_basic'])) {
        $rows=[];
        $res = $mysqli->query("SELECT mother_id, full_name FROM mothers_caregivers ORDER BY full_name ASC");
        while($r=$res->fetch_assoc()) $rows[]=$r;
        echo json_encode(['success'=>true,'mothers'=>$rows]);
        exit;
    }

    fail('Unknown GET action',404);
}

if ($method === 'POST') {

    if (empty($_POST['csrf_token']) || empty($_SESSION['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        fail('CSRF failed',419);
    }

    $full_name       = nz($_POST['full_name'] ?? '');
    $purok_name      = nz($_POST['purok_name'] ?? '');
    $address_details = nz($_POST['address_details'] ?? '');
    $contact_number  = nz($_POST['contact_number'] ?? '');
    $user_id         = (int)($_SESSION['user_id'] ?? 0);

    $date_of_birth   = nz($_POST['date_of_birth'] ?? '');
    if ($date_of_birth && !preg_match('/^\d{4}-\d{2}-\d{2}$/',$date_of_birth)) $date_of_birth = null;
    $gravida         = (isset($_POST['gravida']) && $_POST['gravida'] !== '') ? (int)$_POST['gravida'] : null;
    $para            = (isset($_POST['para']) && $_POST['para'] !== '') ? (int)$_POST['para'] : null;
    $blood_type      = nz($_POST['blood_type'] ?? '');
    $emg_name        = nz($_POST['emergency_contact_name'] ?? '');
    $emg_number      = nz($_POST['emergency_contact_number'] ?? '');

    if (!$full_name || !$purok_name) {
        fail('Full name at Purok ay required.');
    }

    // Ensure / find purok
    $purok_id = null;
    $stmt = $mysqli->prepare("SELECT purok_id FROM puroks WHERE purok_name=? LIMIT 1");
    $stmt->bind_param('s',$purok_name);
    $stmt->execute();
    $stmt->bind_result($pid);
    if($stmt->fetch()) $purok_id = $pid;
    $stmt->close();

    if(!$purok_id){
        $barangay = 'Sabang';
        $ins=$mysqli->prepare("INSERT INTO puroks (purok_name, barangay) VALUES (?,?)");
        $ins->bind_param('ss',$purok_name,$barangay);
        if(!$ins->execute()) fail('Purok insert failed: '.$ins->error,500);
        $purok_id=$ins->insert_id;
        $ins->close();
    }

    $sql="INSERT INTO mothers_caregivers
        (full_name,purok_id,address_details,contact_number,
         date_of_birth,gravida,para,blood_type,
         emergency_contact_name,emergency_contact_number,created_by)
        VALUES (?,?,?,?,?,?,?,?,?,?,?)";

    $ins2=$mysqli->prepare($sql);
    if(!$ins2) fail('Prepare failed: '.$mysqli->error,500);

    // Types: s i s s s i i s s s i
    $types="sisssiisssi";
    $ins2->bind_param(
        $types,
        $full_name,$purok_id,$address_details,$contact_number,
        $date_of_birth,$gravida,$para,$blood_type,
        $emg_name,$emg_number,$user_id
    );

    if(!$ins2->execute()){
        fail('Insert failed: '.$ins2->error,500);
    }
    $mid=$ins2->insert_id;
    $ins2->close();

    echo json_encode(['success'=>true,'mother_id'=>$mid]);
    exit;
}

fail('Invalid method',405);