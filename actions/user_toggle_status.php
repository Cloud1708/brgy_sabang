<?php
require_once __DIR__.'/../inc/db.php';
require_once __DIR__.'/../inc/auth.php';
require_role(['Admin']);
if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json; charset=utf-8');
set_error_handler(function($s,$m,$f,$l){
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>"PHP Error: $m",'file'=>$f,'line'=>$l]); exit;
});
set_exception_handler(function($e){
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>"Exception: ".$e->getMessage()]); exit;
});

function fail($m,$c=400){ http_response_code($c); echo json_encode(['success'=>false,'error'=>$m]); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail('Invalid method',405);
if (empty($_POST['csrf_token']) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    fail('CSRF failed',419);
}

$user_id = (int)($_POST['user_id'] ?? 0);
if ($user_id <= 0) fail('Invalid user ID');

$q = $mysqli->prepare("
  SELECT u.user_id, r.role_name, u.is_active
  FROM users u
  JOIN roles r ON r.role_id = u.role_id
  WHERE u.user_id=? LIMIT 1
");
if (!$q) fail('Prepare failed: '.$mysqli->error,500);
$q->bind_param('i',$user_id);
$q->execute();
$q->bind_result($uid,$role,$active);
if (!$q->fetch()) fail('User not found',404);
$q->close();

if (!in_array($role,['BHW','BNS','Parent'],true)) fail('Cannot toggle this role',403);

$newStatus = $active ? 0 : 1;
$upd = $mysqli->prepare("UPDATE users SET is_active=? WHERE user_id=? LIMIT 1");
if (!$upd) fail('Prepare failed: '.$mysqli->error,500);
$upd->bind_param('ii',$newStatus,$user_id);
if (!$upd->execute()) fail('Update failed: '.$upd->error,500);
$upd->close();

echo json_encode(['success'=>true,'new_status'=>$newStatus]);