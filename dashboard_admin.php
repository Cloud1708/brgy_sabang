<?php
require_once __DIR__.'/inc/db.php';
require_once __DIR__.'/inc/auth.php';
require_role(['Admin']);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Admin Dashboard</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
<style>
  body,html { height:100%; }
  .layout-wrapper { display:flex; min-height:100vh; overflow:hidden; }
  .sidebar {
    width:270px; flex:0 0 270px; background:#0d2538; color:#fff;
    display:flex; flex-direction:column;
  }
  .sidebar .brand {
    padding:1.1rem 1.25rem; font-weight:600; font-size:1rem;
    display:flex; gap:.75rem; align-items:center;
    background:rgba(255,255,255,.06);
  }
  .menu-section-title {
    font-size:.65rem; letter-spacing:.08em; text-transform:uppercase;
    opacity:.55; padding:.75rem 1.25rem .35rem;
  }
  .nav-menu { list-style:none; margin:0; padding:0 0 1rem; }
  .nav-menu li a {
    display:flex; align-items:center; gap:.75rem; padding:.55rem 1.25rem;
    font-size:.85rem; color:#dbe4ec; border-left:3px solid transparent;
    text-decoration:none; transition:.15s;
  }
  .nav-menu li a .bi { font-size:1rem; opacity:.85; }
  .nav-menu li a:hover { background:rgba(255,255,255,.07); color:#fff; }
  .nav-menu li a.active {
    background:linear-gradient(90deg,#1469ff22,#1469ff08);
    border-left-color:#0d6efd; color:#fff; font-weight:600;
  }
  .sidebar-footer {
    margin-top:auto; padding:.85rem 1.25rem; font-size:.7rem; opacity:.55;
  }
  .content-area { flex:1; min-width:0; background:#f1f3f7; display:flex; flex-direction:column; }
  .topbar {
    background:#fff; border-bottom:1px solid #e3e8ef;
    padding:.65rem 1.5rem; display:flex; align-items:center; gap:1rem;
  }
  .page-title { font-size:.95rem; font-weight:600; margin:0; }
  @media (max-width: 991.98px) {
    .sidebar {
      position:fixed; z-index:1045; transform:translateX(-100%); transition:.3s;
      height:100%; top:0; left:0;
    }
    .sidebar.show { transform:translateX(0); }
    .topbar { position:fixed; top:0; left:0; right:0; z-index:1020; }
    .content-area { padding-top:56px; }
  }
  .loading-state {
    padding:2.5rem 1.5rem; text-align:center; color:#6c757d;
  }
  .table-sm td, .table-sm th { vertical-align:middle; }
  .form-help { font-size:.7rem; opacity:.7; }
  .role-row.editing { background:#fffbe8; }
</style>
</head>
<body class="dashboard-body">
<div class="layout-wrapper">
  <aside class="sidebar">
    <div class="brand">
      <i class="bi bi-speedometer2"></i>
      <span>Admin Dashboard</span>
    </div>

    <!-- ACCOUNT MANAGEMENT -->
    <div class="menu-section-title">Account Management</div>
    <ul class="nav-menu">
      <li><a href="#" class="active" data-module="create_bhw" data-label="Create BHW Accounts"><i class="bi bi-hospital"></i> <span>Create BHW Accounts</span></a></li>
      <li><a href="#" data-module="create_bns" data-label="Create BNS Accounts"><i class="bi bi-heart-pulse"></i> <span>Create BNS Accounts</span></a></li>
      <li><a href="#" data-module="user_mgmt" data-label="User Management"><i class="bi bi-people"></i> <span>User Management</span></a></li>
      <li><a href="#" data-module="acct_log" data-label="Account Creation Log"><i class="bi bi-journal-text"></i> <span>Account Creation Log</span></a></li>
      <li><a href="#" data-module="role_permissions" data-label="Role Permissions"><i class="bi bi-shield-lock"></i> <span>Role Permissions</span></a></li>
    </ul>

    <!-- SYSTEM REPORTS -->
    <div class="menu-section-title">System Reports</div>
    <ul class="nav-menu">
      <li><a href="#" data-module="nutrition_report" data-label="Nutrition Status Overview"><i class="bi bi-apple"></i> <span>Nutrition Status Overview</span></a></li>
      <li><a href="#" data-module="vaccination_report" data-label="Vaccination Coverage Report"><i class="bi bi-capsule"></i> <span>Vaccination Coverage</span></a></li>
      <li><a href="#" data-module="maternal_report" data-label="Maternal Health Statistics"><i class="bi bi-person-heart"></i> <span>Maternal Health Stats</span></a></li>
      <li><a href="#" data-module="growth_trends" data-label="Child Growth Trends"><i class="bi bi-bar-chart-line"></i> <span>Child Growth Trends</span></a></li>
      <li><a href="#" data-module="supplementation" data-label="Supplementation Compliance"><i class="bi bi-droplet-half"></i> <span>Supplementation</span></a></li>
    </ul>

    <!-- EVENT MANAGEMENT -->
    <div class="menu-section-title">Event Management</div>
    <ul class="nav-menu">
      <li><a href="#" data-module="calendar" data-label="Community Calendar"><i class="bi bi-calendar3"></i> <span>Community Calendar</span></a></li>
      <li><a href="#" data-module="announcements" data-label="System-wide Announcements"><i class="bi bi-megaphone"></i> <span>Announcements</span></a></li>
      <li><a href="#" data-module="scheduling" data-label="Event Scheduling"><i class="bi bi-calendar-plus"></i> <span>Event Scheduling</span></a></li>
    </ul>

    <div class="mt-2 px-3">
      <a href="logout.php" class="btn btn-sm btn-outline-light w-100">
        <i class="bi bi-box-arrow-right me-1"></i> Logout
      </a>
    </div>
    <div class="sidebar-footer">
      Barangay-Level Access<br>
      <span class="text-white-50">© <?php echo date('Y'); ?></span>
    </div>
  </aside>

  <div class="content-area">
    <div class="topbar">
      <button class="btn btn-outline-secondary btn-sm d-lg-none" id="sidebarToggle"><i class="bi bi-list"></i></button>
      <h1 class="page-title mb-0" id="currentModuleTitle">Create BHW Accounts</h1>
      <span class="badge bg-primary ms-auto d-none d-lg-inline">Admin</span>
    </div>
    <main class="p-4">
      <div id="moduleContent" class="bg-transparent">
        <div class="loading-state">
          <div class="spinner-border text-primary mb-3"></div>
          <div class="small">Loading module…</div>
        </div>
      </div>
    </main>
  </div>
</div>

<script>
  window.__ADMIN_CSRF = "<?php echo htmlspecialchars($csrf); ?>";
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/admin.js"></script>
</body>
</html>