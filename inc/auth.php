<?php
if (session_status() === PHP_SESSION_NONE) {
    // Unify cookie scope across all pages
    session_set_cookie_params([
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

/*
 * I-define mo ang base URL path ng project mo.
 * Kung ang URL mo ay http://localhost/index.php
 * ibig sabihin base path = /
 */

 define('APP_BASE_PATH', '/brgy_sabangbackup/');  // IMPORTANT

// Canonical role names
const ROLE_CANON = ['Admin','BHW','BNS','Parent'];

// Optional: common aliases if your DB/login uses different labels/ids
const ROLE_ALIASES = [
    'ADMIN'   => 'Admin',
    'BHW'     => 'BHW',
    'BNS'     => 'BNS',
    'PARENT'  => 'Parent',
    'BARANGAY HEALTH WORKER' => 'BHW',
    'HEALTH WORKER'          => 'BHW',
    'HEALTHWORKER'           => 'BHW',
    'BARANGAY NUTRITION SCHOLAR' => 'BNS',
];

// Optional: map numeric role_ids to names if your login stores an int
const ROLE_ID_MAP = [
    1 => 'Admin',
    2 => 'BHW',
    3 => 'BNS',
    4 => 'Parent',
];

function normalize_role($raw) {
    if ($raw === null) return null;

    // Numeric role id?
    if (is_int($raw) || (is_string($raw) && ctype_digit($raw))) {
        $id = (int)$raw;
        return ROLE_ID_MAP[$id] ?? null;
    }

    // String: trim/case-normalize
    $s = strtoupper(trim((string)$raw));
    if (isset(ROLE_ALIASES[$s])) return ROLE_ALIASES[$s];

    // If it already matches canonical after proper casing
    $canon = ucfirst(strtolower($s));
    return in_array($canon, ROLE_CANON, true) ? $canon : null;
}

function require_role(array $allowed) {
    // Normalize allowed list (Admin/BHW/BNS/Parent)
    $allowedNorm = array_values(array_filter(array_map('normalize_role', $allowed)));

    // If the page is a staff workspace (BHW or BNS), also allow Admin.
    // This keeps the UI consistent so Admin can open those workspaces.
    if (in_array('BHW', $allowedNorm, true) || in_array('BNS', $allowedNorm, true)) {
        if (!in_array('Admin', $allowedNorm, true)) {
            $allowedNorm[] = 'Admin';
        }
    }

    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
        header('Location: ' . APP_BASE_PATH . 'staff_login?unauthorized=1');
        exit;
    }

    $current = normalize_role($_SESSION['role']);
    if (!$current || !in_array($current, $allowedNorm, true)) {
        // Keep UI consistent: redirect instead of plain text
        header('Location: ' . APP_BASE_PATH . 'staff_login?forbidden=1');
        exit;
    }
}

function redirect_by_role(string $role) {
    $canon = normalize_role($role);
    switch ($canon) {
        case 'Admin':
            header('Location: ' . APP_BASE_PATH . 'dashboard_admin'); break;
        case 'BHW':
            header('Location: ' . APP_BASE_PATH . 'dashboard_bhw'); break;
        case 'BNS':
            header('Location: ' . APP_BASE_PATH . 'dashboard_bns'); break;
        case 'Parent':
            header('Location: ' . APP_BASE_PATH . 'parent_portal'); break;
        default:
            header('Location: ' . APP_BASE_PATH . 'staff_login?error=role');
    }
    exit;
}