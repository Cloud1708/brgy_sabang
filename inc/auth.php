<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/*
 * I-define mo ang base URL path ng project mo.
 * Kung ang URL mo ay http://localhost/brgy_sabang/index.php
 * ibig sabihin base path = /brgy_sabang/
 *
 * Kung iba folder name mo, palitan mo dito.
 */
define('APP_BASE_PATH', '/brgy_sabang/');  // IMPORTANT: may leading at trailing slash kung gusto mo

function require_role(array $allowed) {
    if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
        header('Location: '.APP_BASE_PATH.'staff_login.php?unauthorized=1');
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
            header('Location: '.APP_BASE_PATH.'dashboard_admin.php'); break;
        case 'BHW':
            header('Location: '.APP_BASE_PATH.'dashboard_bhw.php'); break;
        case 'BNS':
            header('Location: '.APP_BASE_PATH.'dashboard_bns.php'); break;
        default:
            header('Location: '.APP_BASE_PATH.'staff_login.php?error=role');
    }
    exit;
}