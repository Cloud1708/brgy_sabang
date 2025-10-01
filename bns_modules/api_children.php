<?php
require_once __DIR__.'/../inc/db.php';
require_once __DIR__.'/../inc/auth.php';
require_role(['BNS']);
header('Content-Type: application/json; charset=utf-8');
if (session_status()===PHP_SESSION_NONE) session_start();

function fail($m,$c=400){ http_response_code($c); echo json_encode(['success'=>false,'error'=>$m]); exit; }
$method = $_SERVER['REQUEST_METHOD'];

if ($method==='GET') {
    if (isset($_GET['list_basic'])) {
        $res = $mysqli->query("SELECT child_id, full_name FROM children ORDER BY full_name ASC LIMIT 800");
        $rows=[]; while($r=$res->fetch_assoc()) $rows[]=$r;
        echo json_encode(['success'=>true,'children'=>$rows]); exit;
    }
    if (isset($_GET['list'])) {
        $sql = "
          SELECT c.child_id, c.full_name, c.sex, c.birth_date,
                 TIMESTAMPDIFF(MONTH, c.birth_date, CURDATE()) AS age_months,
                 m.full_name AS mother_name,
                 (SELECT nr.weighing_date FROM nutrition_records nr
                  WHERE nr.child_id=c.child_id
                  ORDER BY nr.weighing_date DESC LIMIT 1) AS last_weighing_date
          FROM children c
          LEFT JOIN mothers_caregivers m ON m.mother_id = c.mother_id
          ORDER BY c.created_at DESC
          LIMIT 800
        ";
        $res=$mysqli->query($sql);
        $rows=[]; while($r=$res->fetch_assoc()) $rows[]=$r;
        echo json_encode(['success'=>true,'children'=>$rows]); exit;
    }
    echo json_encode(['success'=>true,'message'=>'No action']); exit;
}

if ($method==='POST') {
    if (empty($_POST['csrf_token']) || empty($_SESSION['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        fail('CSRF failed',419);
    }
    $full_name = trim($_POST['full_name'] ?? '');
    $sex = $_POST['sex'] ?? '';
    $birth_date = $_POST['birth_date'] ?? '';
    $mother_id = (int)($_POST['mother_id'] ?? 0);
    $creator = (int)($_SESSION['user_id'] ?? 0);

    if ($full_name==='' || !in_array($sex,['male','female'],true) || !$birth_date || $mother_id<=0) {
        fail('Missing or invalid fields');
    }

    $stmt = $mysqli->prepare("INSERT INTO children (full_name,sex,birth_date,mother_id,created_by) VALUES (?,?,?,?,?)");
    if(!$stmt) fail('DB prepare failed');
    $stmt->bind_param('sssii',$full_name,$sex,$birth_date,$mother_id,$creator);
    if(!$stmt->execute()) fail('Insert failed: '.$stmt->error,500);
    $id = $stmt->insert_id;
    $stmt->close();
    echo json_encode(['success'=>true,'child_id'=>$id,'message'=>'Child added']); exit;
}

fail('Method not allowed',405);