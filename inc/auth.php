<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/*
 * I-define mo ang base URL path ng project mo.
 * Kung ang URL mo ay http://localhost/index.php
 * ibig sabihin base path = /
 */
define('APP_BASE_PATH', '/brgy_sabangbackup/');  // IMPORTANT

function require_role(array $allowed) {
    if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
        header('Location: '.APP_BASE_PATH.'staff_login?unauthorized=1');
        exit;
    }
    if (!in_array($_SESSION['role'], $allowed, true)) {
        header('HTTP/1.1 403 Forbidden');
        echo "Access denied.";
        exit;
    }
}

function redirect_by_role(string $role) {
    switch ($role) {
        case 'Admin':
            header('Location: '.APP_BASE_PATH.'dashboard_admin'); break;
        case 'BHW':
            header('Location: '.APP_BASE_PATH.'dashboard_bhw'); break;
        case 'BNS':
            header('Location: '.APP_BASE_PATH.'dashboard_bns'); break;
        case 'Parent': // ADDED
            header('Location: '.APP_BASE_PATH.'parent_portal'); break;
        default:
            header('Location: '.APP_BASE_PATH.'staff_login?error=role');
    }
    exit;
}