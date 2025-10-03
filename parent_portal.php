<?php
require_once __DIR__.'/inc/db.php';
require_once __DIR__.'/inc/auth.php';
require_role(['Parent']);

if (session_status()===PHP_SESSION_NONE) session_start();
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token']=bin2hex(random_bytes(16));

$parentName = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Parent User';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Parent / Guardian Portal</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link rel="stylesheet" href="assets/css/parent_portal.css">
</head>
<body>
<div class="pp-layout">
  <aside class="pp-sidebar" id="sidebar">
    <div class="pp-brand">
      <div class="pp-brand-icon"><i class="bi bi-people-fill"></i></div>
      <div class="pp-brand-text">
        <strong>Parent Portal</strong>
        <small>Barangay Health</small>
      </div>
    </div>
    <nav class="pp-nav">
      <a href="#" data-panel="dashboard" class="active"><i class="bi bi-house"></i> <span>My Children</span></a>
      <a href="#" data-panel="immunization"><i class="bi bi-syringe"></i> <span>Immunization</span></a>
      <a href="#" data-panel="growth"><i class="bi bi-graph-up"></i> <span>Growth & Nutrition</span></a>
      <a href="#" data-panel="notifications"><i class="bi bi-bell"></i> <span>Notifications</span></a>
      <a href="#" data-panel="appointments"><i class="bi bi-calendar-event"></i> <span>Appointments</span></a>
      <a href="#" data-panel="settings"><i class="bi bi-person-gear"></i> <span>Account Settings</span></a>
    </nav>
    <div class="mt-auto p-3 text-center small text-muted">
      <div>&copy; <?= date('Y'); ?> Barangay Sabang</div>
      <a href="logout.php" class="btn btn-sm btn-outline-danger mt-2 w-100"><i class="bi bi-box-arrow-right"></i> Logout</a>
    </div>
  </aside>

  <div class="pp-main">
    <header class="pp-topbar">
      <button class="btn btn-outline-secondary btn-sm d-lg-none" id="sidebarToggle"><i class="bi bi-list"></i></button>
      <h1 class="pp-title" id="panelTitle">My Children Dashboard</h1>
      <div class="ms-auto d-flex align-items-center gap-3">
        <div class="pp-user-chip">
          <div class="pp-avatar">
            <?php
              $parts = preg_split('/\s+/',trim($parentName));
              echo strtoupper(substr($parts[0]??'P',0,1).(isset($parts[1])?substr($parts[1],0,1):''));
            ?>
          </div>
          <div class="d-flex flex-column">
            <strong class="small"><?= htmlspecialchars($parentName) ?></strong>
            <small class="text-muted">Parent</small>
          </div>
        </div>
      </div>
    </header>

    <main class="pp-content" id="panelContent">
      <div class="pp-loading">
        <div class="spinner-border text-success"></div>
        <div class="small text-muted mt-2">Loading dashboard...</div>
      </div>
    </main>
  </div>
</div>

<script>
window.__PARENT_CSRF = <?= json_encode($_SESSION['csrf_token']); ?>;
</script>
<script src="assets/js/parent_portal.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>