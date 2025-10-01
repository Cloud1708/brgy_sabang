<?php
require_once __DIR__.'/../inc/db.php';
require_once __DIR__.'/../inc/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../staff_login.php');
    exit;
}

session_start();

if (!isset($_POST['csrf_token'], $_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    header('Location: ../staff_login.php?error=csrf');
    exit;
}

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if ($username === '' || $password === '') {
    header('Location: ../staff_login.php?error=1');
    exit;
}

$stmt = $mysqli->prepare("
  SELECT u.user_id, u.password_hash, r.role_name
  FROM users u
  JOIN roles r ON r.role_id = u.role_id
  WHERE u.username = ? AND u.is_active = 1
  LIMIT 1
");
if (!$stmt) {
    header('Location: ../staff_login.php?error=db');
    exit;
}

$stmt->bind_param('s', $username);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user || !password_verify($password, $user['password_hash'])) {
    header('Location: ../staff_login.php?error=1');
    exit;
}

// Accept only staff roles (Admin/BHW/BNS)
if (!in_array($user['role_name'], ['Admin','BHW','BNS'], true)) {
    header('Location: ../staff_login.php?error=role');
    exit;
}

session_regenerate_id(true);
$_SESSION['user_id'] = (int)$user['user_id'];
$_SESSION['role'] = $user['role_name'];
unset($_SESSION['csrf_token']);

redirect_by_role($user['role_name']);