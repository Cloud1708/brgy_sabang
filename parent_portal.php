<?php
session_start();
require_once __DIR__ . '/inc/db.php';
 
// Get current view from URL parameter, default to dashboard
$current_view = isset($_GET['view']) ? $_GET['view'] : 'dashboard';
 
// Validate view
$valid_views = ['dashboard', 'immunization', 'growth', 'notifications', 'appointments', 'account'];
if (!in_array($current_view, $valid_views)) {
    $current_view = 'dashboard';
}

// Build user info from session/DB
$user = [
    'first_name' => '',
    'last_name' => '',
    'role' => 'Parent/Guardian',
    'initials' => 'PP',
    'notification_count' => 0,
];

$uid = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
if ($uid > 0) {
    if ($stmt = $mysqli->prepare("SELECT u.first_name, u.last_name, r.role_name FROM users u JOIN roles r ON r.role_id=u.role_id WHERE u.user_id=? LIMIT 1")) {
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $user['first_name'] = trim($row['first_name'] ?? '');
            $user['last_name']  = trim($row['last_name'] ?? '');
            $user['role']       = $row['role_name'] ?? ($_SESSION['role'] ?? 'Parent/Guardian');
        }
        $stmt->close();
    }
    // Fallback to session full_name if names are empty
    if ($user['first_name'] === '' && $user['last_name'] === '' && !empty($_SESSION['full_name'])) {
        $parts = preg_split('/\s+/', trim((string)$_SESSION['full_name']));
        $user['first_name'] = $parts[0] ?? 'Parent';
        $user['last_name']  = isset($parts[1]) ? $parts[1] : '';
    }
    // Compute initials
    $fi = $user['first_name'] !== '' ? mb_substr($user['first_name'], 0, 1) : '';
    $li = $user['last_name']  !== '' ? mb_substr($user['last_name'], 0, 1)  : '';
    $user['initials'] = strtoupper(($fi . $li) ?: 'PP');

    // Unread notifications count for header badge
    if ($stmt = $mysqli->prepare("SELECT COUNT(*) AS c FROM parent_notifications WHERE parent_user_id=? AND read_at IS NULL")) {
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $user['notification_count'] = (int)($row['c'] ?? 0);
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HealthKids Portal - Child Health Dashboard</title>
   
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
   
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/globals.css">
   
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
   
    <!-- Chart.js for charts -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
   
    <style>
        /* Additional inline styles for custom colors */
        :root {
            --primary: #3b82f6;
            --secondary: #10b981;
            --accent: #f59e0b;
            --destructive: #ef4444;
        }
    </style>
    <?php
    // Ensure CSRF token exists for API POSTs
    if (empty($_SESSION['csrf_token'])) {
        try { $_SESSION['csrf_token'] = bin2hex(random_bytes(16)); }
        catch (Throwable $e) { $_SESSION['csrf_token'] = bin2hex(uniqid('', true)); }
    }
    ?>
    <meta name="csrf-token" content="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
</head>
<body class="min-h-screen" style="background-color: #f8f9fc;">
   
    <?php include 'includes/header.php'; ?>
   
    <div class="flex">
        <?php include 'includes/sidebar.php'; ?>
       
        <!-- Overlay for mobile -->
        <div id="sidebar-overlay" class="hidden fixed inset-0 z-30 bg-black/50 lg:hidden"></div>
       
        <!-- Main Content -->
        <main class="flex-1 p-4 lg:p-6 max-w-7xl mx-auto w-full">
            <?php
            // Include the appropriate view file under views/
            $view_file = __DIR__ . "/views/{$current_view}.php";
            if (file_exists($view_file)) {
                include $view_file;
            } else {
                // fallback to dashboard view
                include __DIR__ . '/views/dashboard.php';
            }
            ?>
        </main>
    </div>
   
    <!-- JavaScript for interactivity -->
    <script src="assets/js/main.js"></script>
   
    <script>
        // Initialize Lucide icons
        lucide.createIcons();
    </script>
</body>
</html>