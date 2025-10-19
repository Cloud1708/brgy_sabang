<?php
date_default_timezone_set('Asia/Manila');
require_once __DIR__.'/inc/auth.php'; // provides APP_BASE_PATH and normalize_role(); starts session if needed

// Determine target before destroying the session
$target = APP_BASE_PATH . 'staff_login';
if (isset($_SESSION['role'])) {
    $role = normalize_role($_SESSION['role']);
    if ($role === 'Parent') {
        $target = APP_BASE_PATH . 'parent_login';
    }
}

// Clear session data and cookie
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time()-42000, $params['path'], $params['domain'] ?? '', $params['secure'] ?? false, $params['httponly'] ?? true);
}
session_destroy();

header('Location: ' . $target);
exit;