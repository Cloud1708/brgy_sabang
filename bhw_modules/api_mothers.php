<?php
require_once __DIR__.'/../inc/db.php';
require_once __DIR__.'/../inc/auth.php';
require_role(['BHW']);

if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');

function fail($msg,$code=400){
  http_response_code($code);
  echo json_encode(['success'=>false,'error'=>$msg]);
  exit;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {

    // List mothers with stats
    if (isset($_GET['list'])) {
        $rows = [];
        $sql = "
          SELECT m.mother_id,
                 m.full_name,
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
          LIMIT 500
        ";
        $res = $mysqli->query($sql);
        while($r = $res->fetch_assoc()){
            $rows[] = $r;
        }
        echo json_encode(['success'=>true,'mothers'=>$rows]);
        exit;
    }

    // Basic list (id + full_name) for dropdowns
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
    // CSRF (optional reuse token from session if you want stronger security)
    if (empty($_POST['csrf_token']) || empty($_SESSION['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        fail('CSRF failed',419);
    }

    $full_name = trim($_POST['full_name'] ?? '');
    $purok_name = trim($_POST['purok_name'] ?? '');
    $address_details = trim($_POST['address_details'] ?? '');
    $contact_number = trim($_POST['contact_number'] ?? '');
    $user_id = (int)($_SESSION['user_id'] ?? 0);

    if ($full_name === '' || $purok_name === '') {
        fail('Full name at Purok ay required.');
    }

    // Ensure / find purok
    $purok_id = null;
    $stmt = $mysqli->prepare("SELECT purok_id FROM puroks WHERE purok_name = ? LIMIT 1");
    $stmt->bind_param('s',$purok_name);
    $stmt->execute();
    $stmt->bind_result($pid);
    if ($stmt->fetch()) { $purok_id = $pid; }
    $stmt->close();

    if (!$purok_id) {
        $ins = $mysqli->prepare("INSERT INTO puroks (purok_name, barangay) VALUES (?, ?)");
        $barangay = 'Sabang';
        $ins->bind_param('ss',$purok_name,$barangay);
        if(!$ins->execute()) fail('Purok insert failed: '.$ins->error,500);
        $purok_id = $ins->insert_id;
        $ins->close();
    }

    $ins2 = $mysqli->prepare("
      INSERT INTO mothers_caregivers (full_name,purok_id,address_details,contact_number,created_by)
      VALUES (?,?,?,?,?)
    ");
    $ins2->bind_param('sissi',$full_name,$purok_id,$address_details,$contact_number,$user_id);
    if(!$ins2->execute()) fail('Insert failed: '.$ins2->error,500);
    $mid = $ins2->insert_id;
    $ins2->close();

    echo json_encode(['success'=>true,'mother_id'=>$mid]);
    exit;
}

fail('Invalid method',405);