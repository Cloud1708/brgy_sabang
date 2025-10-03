<?php
require_once __DIR__.'/../inc/db.php';
require_once __DIR__.'/../inc/auth.php';
require_role(['BHW']); // adjust if only Admin should create

if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');

function fail($m,$c=400){ http_response_code($c); echo json_encode(['success'=>false,'error'=>$m]); exit; }
function ok($d=[]){ echo json_encode(array_merge(['success'=>true],$d)); exit; }

$method = $_SERVER['REQUEST_METHOD'];

function require_csrf(){
    if (empty($_POST['csrf_token']) || empty($_SESSION['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        fail('CSRF failed',419);
    }
}

if ($method === 'GET') {
    // Expect ?month=YYYY-MM  (defaults to current month)
    $month = preg_replace('/[^0-9\-]/','', $_GET['month'] ?? date('Y-m'));
    if (!preg_match('/^\d{4}-\d{2}$/',$month)) fail('Invalid month');
    $start = $month.'-01';
    $end   = date('Y-m-d', strtotime($start.' +1 month'));
    $rows=[];
    $stmt=$mysqli->prepare("
        SELECT event_id,event_title,event_type,event_date,event_time,location
        FROM events
        WHERE event_date >= ? AND event_date < ?
        ORDER BY event_date, event_time, event_id
        LIMIT 1000
    ");
    $stmt->bind_param('ss',$start,$end);
    $stmt->execute(); $res=$stmt->get_result();
    while($r=$res->fetch_assoc()) $rows[]=$r;
    $stmt->close();
    ok(['month'=>$month,'events'=>$rows]);
}

if ($method === 'POST') {
    require_csrf();
    if (isset($_POST['create_event'])) {
        $title = trim($_POST['event_title'] ?? '');
        $type  = $_POST['event_type'] ?? '';
        $date  = $_POST['event_date'] ?? '';
        $time  = $_POST['event_time'] ?? '';
        $loc   = trim($_POST['location'] ?? '');
        $desc  = trim($_POST['event_description'] ?? '');
        $creator = (int)($_SESSION['user_id'] ?? 0);

        $validTypes = ['health','nutrition','vaccination','feeding','weighing','general','other'];
        if ($title==='' || !preg_match('/^\d{4}-\d{2}-\d{2}$/',$date) || !in_array($type,$validTypes,true)){
            fail('Kulangan / invalid fields.');
        }
        if ($time !== '' && !preg_match('/^\d{2}:\d{2}(:\d{2})?$/',$time)) $time = null;

        $stmt=$mysqli->prepare("INSERT INTO events (event_title,event_description,event_type,event_date,event_time,location,created_by) VALUES (?,?,?,?,?,?,?)");
        $stmt->bind_param('ssssssi',$title,$desc,$type,$date,$time,$loc,$creator);
        if(!$stmt->execute()) fail('Insert failed: '.$stmt->error,500);
        $id=$stmt->insert_id;
        $stmt->close();
        ok(['event_id'=>$id]);
    }
    fail('Unknown POST action',400);
}

fail('Invalid method',405);