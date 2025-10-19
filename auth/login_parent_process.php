<?php
require_once __DIR__.'/../inc/db.php';
require_once __DIR__.'/../inc/auth.php'; // for redirect_by_role
 
session_start(); 

// Function to get client IP address
function getClientIP() {
    $ipKeys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
    foreach ($ipKeys as $key) {
        if (array_key_exists($key, $_SERVER) === true) {
            foreach (explode(',', $_SERVER[$key]) as $ip) {
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                    return $ip;
                }
            }
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

// Function to check if account is locked
function isAccountLocked($mysqli, $username, $ip) {
    $stmt = $mysqli->prepare("
        SELECT attempt_count, locked_until, is_locked 
        FROM login_attempts 
        WHERE username = ? AND ip_address = ?
    ");
    $stmt->bind_param("ss", $username, $ip);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        // Check if still locked
        if ($row['is_locked'] == 1 && $row['locked_until'] && strtotime($row['locked_until']) > time()) {
            return [
                'locked' => true,
                'attempts' => $row['attempt_count'],
                'locked_until' => $row['locked_until']
            ];
        }
        // If lockout expired, reset the attempts
        if ($row['is_locked'] == 1 && $row['locked_until'] && strtotime($row['locked_until']) <= time()) {
            $reset_stmt = $mysqli->prepare("
                UPDATE login_attempts 
                SET attempt_count = 0, is_locked = 0, locked_until = NULL 
                WHERE username = ? AND ip_address = ?
            ");
            $reset_stmt->bind_param("ss", $username, $ip);
            $reset_stmt->execute();
            $reset_stmt->close();
        }
    }
    $stmt->close();
    return ['locked' => false, 'attempts' => $row['attempt_count'] ?? 0];
}

// Function to record failed login attempt
function recordFailedAttempt($mysqli, $username, $ip) {
    $stmt = $mysqli->prepare("
        INSERT INTO login_attempts (username, ip_address, attempt_count, last_attempt) 
        VALUES (?, ?, 1, NOW())
        ON DUPLICATE KEY UPDATE 
        attempt_count = attempt_count + 1, 
        last_attempt = NOW()
    ");
    $stmt->bind_param("ss", $username, $ip);
    $stmt->execute();
    $stmt->close();
    
    // Check if we need to lock the account
    $check_stmt = $mysqli->prepare("
        SELECT attempt_count FROM login_attempts 
        WHERE username = ? AND ip_address = ?
    ");
    $check_stmt->bind_param("ss", $username, $ip);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    $row = $result->fetch_assoc();
    $check_stmt->close();
    
    if ($row && $row['attempt_count'] >= 5) {
        // Lock the account for 5 minutes
        $lock_stmt = $mysqli->prepare("
            UPDATE login_attempts 
            SET is_locked = 1, locked_until = DATE_ADD(NOW(), INTERVAL 5 MINUTE)
            WHERE username = ? AND ip_address = ?
        ");
        $lock_stmt->bind_param("ss", $username, $ip);
        $lock_stmt->execute();
        $lock_stmt->close();
    }
}

// Function to clear failed attempts on successful login
function clearFailedAttempts($mysqli, $username, $ip) {
    $stmt = $mysqli->prepare("
        DELETE FROM login_attempts 
        WHERE username = ? AND ip_address = ?
    ");
    $stmt->bind_param("ss", $username, $ip);
    $stmt->execute();
    $stmt->close();
}
 
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: '.APP_BASE_PATH.'parent_login'); exit;
}
 
if (empty($_POST['csrf_token']) || empty($_SESSION['parent_csrf']) ||
    !hash_equals($_SESSION['parent_csrf'], $_POST['csrf_token'])) {
    header('Location: '.APP_BASE_PATH.'parent_login?error=csrf'); exit;
}
 
$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';
$client_ip = getClientIP();
 
if ($username === '' || $password === '') {
    header('Location: '.APP_BASE_PATH.'parent_login?error=1&username='.urlencode($username)); exit;
}

// Check if account is locked
$lock_status = isAccountLocked($mysqli, $username, $client_ip);

if ($lock_status['locked']) {
    header('Location: '.APP_BASE_PATH.'parent_login?username='.urlencode($username)); exit;
}
 
$stmt = $mysqli->prepare("
    SELECT u.user_id,u.username,u.password_hash,r.role_name,
           CONCAT(u.first_name,' ',u.last_name) full_name
    FROM users u
    JOIN roles r ON r.role_id = u.role_id
    WHERE u.username=? AND r.role_name='Parent' AND u.is_active=1
    LIMIT 1
");
if(!$stmt){ header('Location: '.APP_BASE_PATH.'parent_login?error=db'); exit; }
 
$stmt->bind_param('s',$username);
$stmt->execute();
$res = $stmt->get_result();
$user = $res->fetch_assoc();
$stmt->close();
 
if (!$user || !password_verify($password, $user['password_hash'])) {
    // Record failed attempt
    recordFailedAttempt($mysqli, $username, $client_ip);
    header('Location: '.APP_BASE_PATH.'parent_login?error=1&username='.urlencode($username)); exit;
}

// Clear failed attempts on successful login
clearFailedAttempts($mysqli, $username, $client_ip);
 
$_SESSION['user_id'] = (int)$user['user_id'];
$_SESSION['role']    = $user['role_name'];
$_SESSION['username']= $user['username'];
$_SESSION['full_name']= $user['full_name'];
// Reuse existing redirect logic
redirect_by_role('Parent');