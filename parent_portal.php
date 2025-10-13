<?php
date_default_timezone_set('Asia/Manila');
session_start();
 
// Get current view from URL parameter, default to dashboard
$current_view = isset($_GET['view']) ? $_GET['view'] : 'dashboard';
 
// Validate view
$valid_views = ['dashboard', 'immunization', 'growth', 'notifications', 'appointments', 'account'];
if (!in_array($current_view, $valid_views)) {
    $current_view = 'dashboard';
}
 
// User data (in real app, this would come from database)
$user = [
    'first_name' => 'Sarah',
    'last_name' => 'Johnson',
    'role' => 'Parent/Guardian',
    'initials' => 'SJ',
    'notification_count' => 5
];
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