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

/* ------------------------------------------------------------------
   Section routing (top-level only, like in the screenshot)
-------------------------------------------------------------------*/
$validSections = ['control-panel','accounts','reports','events'];
$section = $_GET['section'] ?? ($_SESSION['active_section'] ?? 'control-panel');
if (!in_array($section,$validSections)) $section = 'control-panel';
$_SESSION['active_section'] = $section;

/* ------------------------------------------------------------------
   Fake / sample data for Control Panel (replace with real queries)
-------------------------------------------------------------------*/
if ($section === 'control-panel') {
    // Example metrics (replace with real queries)
    $activeUsers      = (int)($mysqli->query("SELECT COUNT(*) FROM users WHERE is_active=1")->fetch_row()[0] ?? 0);
    $childCount       = (int)($mysqli->query("SELECT COUNT(*) FROM children")->fetch_row()[0] ?? 0);
    $nutritionCount   = (int)($mysqli->query("SELECT COUNT(*) FROM nutrition_records")->fetch_row()[0] ?? 0);
    $healthCount      = (int)($mysqli->query("SELECT COUNT(*) FROM health_records")->fetch_row()[0] ?? 0);
    $totalRecords     = $childCount + $nutritionCount + $healthCount;
    $systemUptimePct  = 99.8;
    $avgResponseMs    = 142;

    // Recent user / system activity (sample from account_creation_log + system line)
    $activity = [];
    $logs = $mysqli->query("
        SELECT CONCAT(u.username,' (',l.account_type,')') actor,
               'Added new account' AS action,
               l.created_at AS created_at
        FROM account_creation_log l
        JOIN users u ON u.user_id = l.created_by_user_id
        ORDER BY l.created_at DESC
        LIMIT 4
    ");
    if ($logs) {
        while($r=$logs->fetch_assoc()) {
            $activity[] = [
                'user' => $r['actor'],
                'action' => $r['action'],
                'time' => $r['created_at'],
                'status' => 'success'
            ];
        }
    }
    // Add some demo rows if less than 4
    $activity[] = ['user'=>'Ana Reyes (BHW)','action'=>'Logged in to system','time'=>date('Y-m-d H:i:s',strtotime('-32 minutes')),'status'=>'info'];
    $activity[] = ['user'=>'Carlos Mendoza (BNS)','action'=>'Generated monthly report','time'=>date('Y-m-d H:i:s',strtotime('-1 hour')),'status'=>'success'];
    $activity[] = ['user'=>'System','action'=>'Automatic backup completed','time'=>date('Y-m-d H:i:s',strtotime('-2 hours')),'status'=>'system'];

    // Helper for relative time
    function rel_time($ts) {
        $diff = time() - strtotime($ts);
        if ($diff < 60) return $diff.'s ago';
        if ($diff < 3600) return floor($diff/60).' min ago';
        if ($diff < 86400) return floor($diff/3600).' hr ago';
        return date('M j, H:i', strtotime($ts));
    }
}

$currentUsername = $_SESSION['username'] ?? 'Admin User';
$currentRoleName = $_SESSION['role_name'] ?? 'System Administrator';
function initials($name){
    $p = preg_split('/\s+/',$name);
    if (!$p) return 'AD';
    if (count($p)===1) return strtoupper(substr($p[0],0,2));
    return strtoupper(substr($p[0],0,1).substr(end($p),0,1));
}
$initials = initials($currentUsername);

$titles = [
    'control-panel'=>'Control Panel',
    'accounts'=>'Account Management',
    'reports'=>'System Reports',
    'events'=>'Event Management'
];
$descs = [
    'control-panel'=>'Monitor system performance and user activity',
    'accounts'=>'Manage BHW / BNS user accounts',
    'reports'=>'View health and nutrition statistics',
    'events'=>'Schedule and manage community events'
];
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title><?php echo htmlspecialchars($titles[$section]); ?> - Admin</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

<style>
/* Layout + Theme */
:root {
  --sidebar-width:220px;
  --green:#047857;
  --green-soft:#e8f9f1;
  --sidebar-border:#e5e9ed;
  --surface:#ffffff;
  --surface-alt:#f6f8fa;
  --border-color:#e2e7ec;
  --text-soft:#5f6b76;
  --radius-md:16px;
  --radius-sm:8px;
  --badge-bg:#eef1f4;
  --shadow-xs:0 1px 2px rgba(0,0,0,.04);
  --shadow-sm:0 1px 3px rgba(0,0,0,.08),0 0 0 1px rgba(0,0,0,.02);
}
html,body { height:100%; background:#f4f6f8; font-family:system-ui,-apple-system,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif; font-size:17px; }
.layout { display:flex; min-height:100vh; }

/* Sidebar */
.sidebar {
  width:var(--sidebar-width);
  flex:0 0 var(--sidebar-width);
  background:#fff;
  border-right:1px solid var(--sidebar-border);
  display:flex; flex-direction:column;
  position:fixed; left:0; top:0; bottom:0; height:100vh;
  overflow:hidden; /* keep sidebar from scrolling with page */
}
.brand {
  padding:.85rem 1rem .65rem;
  border-bottom:1px solid var(--sidebar-border);
}
.brand h1 {
  font-size:1.1rem; margin:0; font-weight:600; color:var(--green);
}
.brand small {
  font-size:.6rem; letter-spacing:.08em; text-transform:uppercase; color:#6a7680;
}

.nav-section { padding:1rem .75rem .5rem; }
.nav-section + .nav-section { padding-top:.25rem; }
.nav-list { list-style:none; margin:0; padding:0; }
.nav-list li { margin-bottom:3px; }
.nav-list a {
  display:flex; align-items:center; gap:.6rem;
  text-decoration:none;
  font-size:.85rem;
  padding:.6rem .7rem;
  border-radius:12px;
  color:#1b2830;
  font-weight:500;
  position:relative;
  transition:.15s;
}
.nav-list a .bi { font-size:1rem; opacity:.75; }
.nav-list a:hover { background:#f2f6f8; }
.nav-list a.active {
  background:var(--green-soft);
  color:#033d29;
  font-weight:600;
}
.nav-list a.active::before {
  content:"";
  position:absolute;
  left:6px; top:50%; transform:translateY(-50%);
  width:4px; height:20px; border-radius:2px;
  background:var(--green);
}

.sidebar-footer {
  margin-top:auto;
  padding:.85rem .75rem;
  font-size:.6rem;
  line-height:1.2;
  text-align:center;
  background:linear-gradient(135deg,#f0faf5,#f1f7ff);
  border-top:1px solid var(--sidebar-border);
  color:#33504a;
}

/* Topbar */
.topbar {
  height:60px;
  background:#fff;
  border-bottom:1px solid var(--border-color);
  display:flex; align-items:center;
  padding:.55rem 1.25rem;
  gap:1rem;
  position:sticky; top:0; z-index:1040; /* keep nav visible while scrolling */
}
.topbar .page-title {
  font-size:1.35rem; font-weight:600; color:var(--green);
  margin:0;
}

.user-chip {
  display:flex; align-items:center; gap:.6rem;
  background:#f1f4f6;
  padding:.35rem .75rem .35rem .4rem;
  border-radius:32px;
}
.user-chip .avatar {
  width:34px; height:34px;
  background:#047857; color:#fff; border-radius:50%;
  display:flex; align-items:center; justify-content:center;
  font-size:.75rem; font-weight:600;
}
.user-chip small { display:block; line-height:1.05; }

/* Main content container */
.main { flex:1; display:flex; flex-direction:column; min-width:0; margin-left:var(--sidebar-width); }
.main-inner { padding:1.4rem 1.8rem 2.2rem; }

/* Metric Cards */
.metrics-row {
  display:flex; gap:1.25rem; flex-wrap:wrap;
}
.metric-card {
  flex:1 1 200px;
  background:#fff;
  border:1px solid #e2e7ec;
  border-radius:18px;
  padding:.95rem 1rem 1rem;
  position:relative;
  min-width:190px;
  display:flex;
  flex-direction:column;
  gap:.45rem;
  box-shadow:var(--shadow-xs);
}
.metric-card .title {
  font-size:.62rem; letter-spacing:.1em; text-transform:uppercase;
  font-weight:600; color:#5e6a73; margin:0;
}
.metric-card .value {
  font-size:2rem; font-weight:800; color:#16242e; line-height:1;
}
.metric-card .delta {
  font-size:.6rem; font-weight:700; background:#eceff2; color:#1b2932;
  padding:.28rem .55rem; border-radius:12px; display:inline-block;
}
.metric-icon {
  position:absolute;
  top:.75rem; right:.75rem;
  width:40px; height:40px;
  background:#e6f0ff;
  color:#275dd4; display:flex; align-items:center; justify-content:center;
  border-radius:12px;
  font-size:1.1rem;
}
.metric-icon.green { background:#e6f7ed; color:#08704b; }
.metric-icon.up { background:#e6f7ed; color:#08704b; }
.metric-icon.purple { background:#efe6ff; color:#6e3fce; }

/* Panels */
.panel {
  background:#fff;
  border:1px solid #e2e7ec;
  border-radius:18px;
  padding:1.1rem 1.25rem;
  box-shadow:var(--shadow-xs);
}
.panel + .panel { margin-top:1.25rem; }
.panel-header h6 {
  font-size:.9rem;
  font-weight:600;
  margin:0;
}
.panel-header p {
  font-size:.68rem;
  margin:.2rem 0 0;
  color:var(--text-soft);
}

/* Activity table */
.table-activity {
  width:100%; border-collapse:collapse; font-size:.86rem;
}
.table-activity th, .table-activity td {
  padding:.6rem .7rem;
}
.table-activity thead th {
  font-size:.66rem; text-transform:uppercase; letter-spacing:.08em;
  font-weight:600; color:#59656f;
  background:#f3f6f8;
  border-bottom:1px solid #e3e7eb;
}
.table-activity tbody tr { border-bottom:1px solid #eff2f4; }
.table-activity tbody tr:last-child { border-bottom:none; }

.badge-soft {
  font-size:.58rem; font-weight:600;
  letter-spacing:.03em;
  padding:.32rem .55rem;
  border-radius:14px;
  background:#e8edf2;
  color:#2b3b46;
  display:inline-block;
}
.badge-soft.success { background:#dcf7e9; color:#046b3e; }
.badge-soft.info { background:#e1ebff; color:#1053c2; }
.badge-soft.system { background:#eceff2; color:#46545e; }

/* Progress slim */
.progress-slim {
  height:6px; background:#e3e7eb; border-radius:4px;
  overflow:hidden; position:relative;
}
.progress-slim .bar {
  height:100%; background:linear-gradient(90deg,#047857,#089867);
}

@media (max-width: 900px) {
  .sidebar { position:fixed; left:0; top:0; bottom:0; z-index:1040; transform:translateX(-100%); transition:.25s; }
  .sidebar.show { transform:translateX(0); }
  .topbar { position:sticky; top:0; z-index:1030; }
  .main { margin-left:0; }
  .main-inner { padding:1.2rem 1rem 2rem; }
  .metrics-row { gap:.9rem; }
  .metric-card { min-width:calc(50% - .9rem); }
}
</style>
</head>
<body>

<div class="layout">

  <!-- Sidebar -->
  <aside class="sidebar" id="sidebar">
    <div class="brand">
      <h1>Barangay San Isidro</h1>
      <small>Health &amp; Nutrition Management System</small>
    </div>

    <div class="nav-section">
      <ul class="nav-list">
        <li><a class="<?php echo $section==='control-panel'?'active':''; ?>" href="?section=control-panel"><i class="bi bi-grid"></i><span>Control Panel</span></a></li>
        <li><a class="<?php echo $section==='accounts'?'active':''; ?>" href="?section=accounts"><i class="bi bi-people"></i><span>Account Management</span></a></li>
        <li><a class="<?php echo $section==='reports'?'active':''; ?>" href="?section=reports"><i class="bi bi-file-bar-graph"></i><span>System Reports</span></a></li>
        <li><a class="<?php echo $section==='events'?'active':''; ?>" href="?section=events"><i class="bi bi-calendar-event"></i><span>Event Management</span></a></li>
      </ul>
    </div>

    <div class="sidebar-footer">
      <div>Barangay Health System</div>
      <div>Version 2.0.1</div>
      <div class="mt-1">&copy; <?php echo date('Y'); ?></div>
      <a href="logout.php" class="btn btn-success btn-sm w-100 mt-2">
        <i class="bi bi-box-arrow-right me-1"></i> Logout
      </a>
    </div>
  </aside>

  <!-- Main -->
  <div class="main">
    <div class="topbar">
      <button class="btn btn-outline-secondary btn-sm d-lg-none" id="sidebarToggle"><i class="bi bi-list"></i></button>
      <h1 class="page-title mb-0">
        <?php echo htmlspecialchars($titles[$section]); ?>
      </h1>
      <div class="ms-auto d-flex align-items-center gap-3">
        <div class="position-relative">
          <button class="btn btn-link text-decoration-none p-0 position-relative" style="color:#1c2a32;">
            <i class="bi bi-bell" style="font-size:1.1rem;"></i>
            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:.55rem;">3</span>
          </button>
        </div>
        <div class="user-chip">
          <div class="avatar"><?php echo htmlspecialchars($initials); ?></div>
          <div class="d-none d-sm-flex flex-column">
            <small class="fw-semibold" style="font-size:.63rem;"><?php echo htmlspecialchars($currentUsername); ?></small>
            <small style="font-size:.55rem; opacity:.65;"><?php echo htmlspecialchars($currentRoleName); ?></small>
          </div>
          <i class="bi bi-chevron-down" style="font-size:.7rem; opacity:.55;"></i>
        </div>
      </div>
    </div>

    <div class="main-inner">
      <div class="mb-4">
        <p class="mb-1" style="font-size:.8rem; font-weight:600; color:#0a583c;">
          <?php echo htmlspecialchars($titles[$section]); ?>
        </p>
        <p class="mb-0" style="font-size:.7rem; color:#5c6872;">
          <?php echo htmlspecialchars($descs[$section]); ?>
        </p>
      </div>

      <?php if ($section==='control-panel'): ?>
      <!-- SYSTEM OVERVIEW -->
      <div class="mb-4">
        <h6 class="mb-3" style="font-size:.75rem; font-weight:600; color:#162630;">System Overview</h6>
        <div class="metrics-row">
          <div class="metric-card">
            <p class="title">Active Users</p>
            <div class="value"><?php echo $activeUsers; ?></div>
            <span class="delta">+12%</span>
            <div class="metric-icon"><i class="bi bi-people"></i></div>
          </div>
          <div class="metric-card">
            <p class="title">Total Records</p>
            <div class="value"><?php echo number_format($totalRecords); ?></div>
            <span class="delta">+8%</span>
            <div class="metric-icon green"><i class="bi bi-database"></i></div>
          </div>
            <div class="metric-card">
            <p class="title">System Uptime</p>
            <div class="value"><?php echo number_format($systemUptimePct,1); ?>%</div>
            <span class="delta">Stable</span>
            <div class="metric-icon up"><i class="bi bi-graph-up"></i></div>
          </div>
          <div class="metric-card">
            <p class="title">Avg Response Time</p>
            <div class="value"><?php echo (int)$avgResponseMs; ?>ms</div>
            <span class="delta">-5%</span>
            <div class="metric-icon purple"><i class="bi bi-clock-history"></i></div>
          </div>
        </div>
      </div>

      <!-- USER ACTIVITY -->
      <div class="panel mb-4">
        <div class="panel-header mb-2">
          <h6>User Activity Monitoring</h6>
          <p>Recent system usage and login logs</p>
        </div>
        <div class="table-responsive">
          <table class="table-activity mb-0">
            <thead>
              <tr>
                <th style="width:240px;">User</th>
                <th>Action</th>
                <th style="width:160px;">Time</th>
                <th style="width:100px;">Status</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($activity as $row):
                $class = 'badge-soft';
                if ($row['status']==='success') $class.=' success';
                elseif ($row['status']==='info') $class.=' info';
                elseif ($row['status']==='system') $class.=' system';
              ?>
              <tr>
                <td><?php echo htmlspecialchars($row['user']); ?></td>
                <td><?php echo htmlspecialchars($row['action']); ?></td>
                <td class="text-muted"><?php echo htmlspecialchars(rel_time($row['time'])); ?></td>
                <td><span class="<?php echo $class; ?>"><?php echo htmlspecialchars($row['status']); ?></span></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- DATABASE HEALTH -->
      <div class="panel">
        <div class="panel-header mb-3">
          <h6 class="d-flex align-items-center gap-2 mb-0"><i class="bi bi-hdd-stack" style="font-size:1rem;"></i> Database Health</h6>
          <p class="mb-0">Performance indicators and system resources</p>
        </div>
        <div class="row g-4">
          <div class="col-md-5">
            <div class="mb-3">
              <div class="d-flex justify-content-between align-items-center mb-1" style="font-size:.63rem;font-weight:600;">
                <span>CPU Usage</span>
                <span class="text-muted" style="font-size:.6rem;">34% <span class="badge-soft success">Good</span></span>
              </div>
              <div class="progress-slim"><div class="bar" style="width:34%;"></div></div>
            </div>
            <div class="mb-3">
              <div class="d-flex justify-content-between align-items-center mb-1" style="font-size:.63rem;font-weight:600;">
                <span>Memory Usage</span>
                <span class="text-muted" style="font-size:.6rem;">57% <span class="badge-soft info">Normal</span></span>
              </div>
              <div class="progress-slim"><div class="bar" style="width:57%;background:linear-gradient(90deg,#1d5ed5,#3f89ff);"></div></div>
            </div>
            <p class="text-muted mb-0" style="font-size:.55rem;">Replace with real metrics (cron job or monitoring endpoint).</p>
          </div>
          <div class="col-md-7">
            <?php
              $distTotal = max($totalRecords,1);
              $tbls = [
                ['label'=>'Children','count'=>$childCount,'grad'=>'linear-gradient(90deg,#047857,#07a46e)'],
                ['label'=>'Nutrition Records','count'=>$nutritionCount,'grad'=>'linear-gradient(90deg,#1d60d3,#4da2ff)'],
                ['label'=>'Health Records','count'=>$healthCount,'grad'=>'linear-gradient(90deg,#d97706,#f59e0b)'],
              ];
            ?>
            <table class="table-activity mb-0">
              <thead><tr><th>Table</th><th style="width:120px;">Records</th><th style="width:160px;">Distribution</th></tr></thead>
              <tbody>
                <?php foreach($tbls as $t):
                  $pct = round(($t['count']/$distTotal)*100,1);
                ?>
                <tr>
                  <td><?php echo htmlspecialchars($t['label']); ?></td>
                  <td><?php echo number_format($t['count']); ?></td>
                  <td>
                    <div class="progress-slim mb-1" style="background:#edf1f3;">
                      <div class="bar" style="width:<?php echo $pct; ?>%;background:<?php echo $t['grad']; ?>"></div>
                    </div>
                    <span class="text-muted" style="font-size:.55rem;"><?php echo $pct; ?>%</span>
                  </td>
                </tr>
                <?php endforeach;?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <?php elseif ($section==='accounts'): ?>
        <div class="panel">
          <h6 class="mb-2">Account Management</h6>
          <p class="text-muted small mb-0">Placeholder section. Add account creation & user list here.</p>
        </div>
      <?php elseif ($section==='reports'): ?>
        <div class="panel">
          <h6 class="mb-2">System Reports</h6>
          <p class="text-muted small mb-0">Placeholder section. Integrate nutrition / vaccination / maternal reports.</p>
        </div>
      <?php else: ?>
        <div class="panel">
          <h6 class="mb-2">Event Management</h6>
          <p class="text-muted small mb-0">Placeholder section. Add calendar & scheduling tools here.</p>
        </div>
      <?php endif; ?>

    </div>
  </div>
</div>

<script>
  window.__ADMIN_CSRF = "<?php echo htmlspecialchars($csrf); ?>";
  document.getElementById('sidebarToggle')?.addEventListener('click', function(){
    document.getElementById('sidebar').classList.toggle('show');
  });
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>