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

$role_id = (int)($_POST['role_id'] ?? 0);
$desc = trim($_POST['role_description'] ?? '');
if ($role_id <= 0) fail('Invalid role id');

$stmt = $mysqli->prepare("UPDATE roles SET role_description=? WHERE role_id=? LIMIT 1");
if (!$stmt) fail('Prepare failed: '.$mysqli->error,500);
$stmt->bind_param('si',$desc,$role_id);
if (!$stmt->execute()) fail('Update failed: '.$stmt->error,500);
$stmt->close();

echo json_encode(['success'=>true]);