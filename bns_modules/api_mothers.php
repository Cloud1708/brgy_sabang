<?php
require_once __DIR__.'/../inc/db.php';
require_once __DIR__.'/../inc/auth.php';
require_role(['BNS']);

header('Content-Type: application/json; charset=utf-8');
if (session_status() === PHP_SESSION_NONE) session_start();

function fail($msg,$code=400){
  http_response_code($code);
  echo json_encode(['success'=>false,'error'=>$msg]); exit;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Basic list for dropdowns in other modules (unchanged)
    if (isset($_GET['list_basic'])) {
        $res = $GLOBALS['mysqli']->query("
          SELECT mother_id, full_name
          FROM mothers_caregivers
          ORDER BY full_name ASC
          LIMIT 500
        ");
        $rows=[];
        while($r=$res->fetch_assoc()) $rows[]=$r;
        echo json_encode(['success'=>true,'mothers'=>$rows]); exit;
    }

    // Full list (with counts)
    if (isset($_GET['list'])) {
        $sql = "
          SELECT m.mother_id, m.full_name, m.contact_number, m.created_at,
                 p.purok_name,
                 (SELECT COUNT(*) FROM children c WHERE c.mother_id = m.mother_id) AS children_count
          FROM mothers_caregivers m
          LEFT JOIN puroks p ON p.purok_id = m.purok_id
          ORDER BY m.created_at DESC
          LIMIT 500
        ";
        $res = $mysqli->query($sql);
        $rows=[];
        while($r=$res->fetch_assoc()) $rows[]=$r;
        echo json_encode(['success'=>true,'mothers'=>$rows]); exit;
    }

    echo json_encode(['success'=>true,'message'=>'No action']); exit;
}

if ($method === 'POST') {
    if (empty($_POST['csrf_token']) || empty($_SESSION['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        fail('CSRF failed',419);
    }
    $full_name = trim($_POST['full_name'] ?? '');
    $purok_name = trim($_POST['purok_name'] ?? '');
    $address = trim($_POST['address_details'] ?? '');
    $contact = trim($_POST['contact_number'] ?? '');
    $user_id = (int)($_SESSION['user_id'] ?? 0);

    if ($full_name === '' || $purok_name === '') fail('Full name at Purok name ay kailangan.');

    // Kunin barangay ng naka-login na BNS (fallback: 'Barangay')
    $barangay = 'Barangay';
    $qb = $mysqli->prepare("SELECT barangay FROM users WHERE user_id=? LIMIT 1");
    if ($qb) {
        $qb->bind_param('i',$user_id);
        $qb->execute();
        $qb->bind_result($bgy);
        if ($qb->fetch() && $bgy) $barangay = $bgy;
        $qb->close();
    }

    // Hanapin kung may existing purok
    $purok_id = null;
    $ps = $mysqli->prepare("SELECT purok_id FROM puroks WHERE LOWER(purok_name)=LOWER(?) LIMIT 1");
    if ($ps) {
        $ps->bind_param('s',$purok_name);
        $ps->execute();
        $ps->bind_result($pid);
        if ($ps->fetch()) $purok_id = $pid;
        $ps->close();
    }

    // Kung wala pa, insert
    if (!$purok_id) {
        $insP = $mysqli->prepare("INSERT INTO puroks (purok_name, barangay) VALUES (?,?)");
        if(!$insP) fail('Purok insert prepare error');
        $insP->bind_param('ss',$purok_name,$barangay);
        if(!$insP->execute()) fail('Insert purok failed: '.$insP->error,500);
        $purok_id = $insP->insert_id;
        $insP->close();
    }

    $stmt = $mysqli->prepare("INSERT INTO mothers_caregivers (full_name,purok_id,address_details,contact_number,created_by) VALUES (?,?,?,?,?)");
    if (!$stmt) fail('DB prepare error');
    $stmt->bind_param('sisss',$full_name,$purok_id,$address,$contact,$user_id);
    if(!$stmt->execute()) fail('Insert failed: '.$stmt->error,500);
    $stmt->close();
    echo json_encode(['success'=>true,'message'=>'Mother/Caregiver added','purok_id'=>$purok_id]); exit;
}

fail('Method not allowed',405);