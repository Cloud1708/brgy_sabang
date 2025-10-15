<?php
require_once __DIR__.'/../inc/db.php';
require_once __DIR__.'/../inc/auth.php'; // for redirect_by_role
 
session_start();
 
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: '.APP_BASE_PATH.'index'); exit;
}
 
if (empty($_POST['csrf_token']) || empty($_SESSION['parent_csrf']) ||
    !hash_equals($_SESSION['parent_csrf'], $_POST['csrf_token'])) {
    header('Location: '.APP_BASE_PATH.'index?error=csrf'); exit;
}
 
$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';
 
if ($username === '' || $password === '') {
    header('Location: '.APP_BASE_PATH.'index?error=1'); exit;
}
 
$stmt = $mysqli->prepare("
    SELECT u.user_id,u.username,u.password_hash,r.role_name,
           CONCAT(u.first_name,' ',u.last_name) full_name
    FROM users u
    JOIN roles r ON r.role_id = u.role_id
    WHERE u.username=? AND r.role_name='Parent' AND u.is_active=1
    LIMIT 1
");
if(!$stmt){ header('Location: '.APP_BASE_PATH.'index?error=db'); exit; }
 
$stmt->bind_param('s',$username);
$stmt->execute();
$res = $stmt->get_result();
$user = $res->fetch_assoc();
$stmt->close();
 
if (!$user || !password_verify($password, $user['password_hash'])) {
    header('Location: '.APP_BASE_PATH.'index?error=1'); exit;
}
 
$_SESSION['user_id'] = (int)$user['user_id'];
$_SESSION['role']    = $user['role_name'];
$_SESSION['username']= $user['username'];
$_SESSION['full_name']= $user['full_name'];
// Reuse existing redirect logic
redirect_by_role('Parent');