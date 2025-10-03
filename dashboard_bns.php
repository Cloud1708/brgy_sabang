<?php
require_once __DIR__.'/inc/db.php';
require_once __DIR__.'/inc/auth.php';
require_role(['BNS']);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];
$userFull = $_SESSION['full_name'] ?? 'Nutrition Scholar';
$initials = implode('', array_map(fn($p)=>mb_strtoupper(mb_substr($p,0,1)), array_slice(preg_split('/\s+/', trim($userFull)),0,2)));
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>BNS Dashboard</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

<!-- CLEANED VERSION:
     - Font size scaled to match reference screenshot (medium – readable)
     - Removed zoom controls / modes
     - Removed sidebar scrollbar (hidden) -->
<style>
:root{
  --base-font-size:16px; /* align with BHW scale */
  --bg:#f5f8f6;
  --surface:#ffffff;
  --surface-soft:#f6faf7;
  --border:#dfe5e0;
  --border-soft:#e8eee9;
  --text:#0f2d18;
  --muted:#586c5d;
  --green:#066a3c;
  --green-accent:#077a44;
  --amber:#f4a400;
  --red:#d23d3d;
  --blue:#1c79d0;
  --radius:16px;
  --radius-sm:10px;
  --shadow-sm:0 1px 2px rgba(15,32,23,.06),0 4px 10px -4px rgba(15,32,23,.08);
  --sidebar-width:250px;
  --gradient-card:linear-gradient(135deg,#eef7f2,#ffffff 60%);
}

html{font-size:var(--base-font-size);}
html,body{height:100%;}
body{
  background:var(--bg);
  color:var(--text);
  font-family:system-ui,-apple-system,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif;
  line-height:1.46;
  overflow:hidden;
  -webkit-font-smoothing:antialiased;
}

/* Layout */
.layout-wrapper{display:flex;height:100vh;width:100%;overflow:hidden;}

/* Sidebar (scrollbar hidden) */
.sidebar{
  width:var(--sidebar-width);
  flex:0 0 var(--sidebar-width);
  background:var(--surface);
  border-right:1px solid var(--border-soft);
  display:flex;
  flex-direction:column;
  /* hide scrollbar while still allowing wheel scroll if overflow occurs */
  overflow-y:auto;
  scrollbar-width:none;
}
.sidebar::-webkit-scrollbar{width:0;height:0;display:none;}

.brand{
  display:flex;align-items:center;gap:.85rem;
  padding:1rem 1.05rem .95rem;
  border-bottom:1px solid var(--border-soft);
}
.brand-icon{
  width:54px;height:54px;border-radius:15px;
  background:linear-gradient(135deg,#0a8047,#0fa55e);
  display:flex;align-items:center;justify-content:center;
  color:#fff;font-size:1.35rem;font-weight:600;
  box-shadow:0 5px 14px -5px rgba(10,120,60,.55);
}
.brand-text{font-size:.98rem;font-weight:700;line-height:1.1;}
.brand-text small{display:block;font-size:.6rem;margin-top:2px;font-weight:600;color:#6a7b6e;letter-spacing:.06em;}

.nav-section-title{
  font-size:.58rem;
  font-weight:800;
  letter-spacing:.12em;
  text-transform:uppercase;
  padding:1rem 1rem .55rem;
  color:#66786a;
}

.nav-menu{list-style:none;margin:0;padding:0 .85rem 1.1rem;font-size:.8rem;}
.nav-menu li{margin-bottom:5px;}
.nav-link-bns{
  display:flex;align-items:center;gap:.65rem;
  padding:.72rem .9rem;
  text-decoration:none;
  border-radius:14px;
  font-size:.8rem;
  font-weight:600;
  color:#1f4129;
  transition:.16s background,.16s color;
}
.nav-link-bns .ico{width:22px;display:flex;justify-content:center;font-size:1.05rem;opacity:.85;}
.nav-link-bns:hover{background:#e5f3ea;color:#0b532d;}
.nav-link-bns.active{
  background:#065f33;
  color:#fff;
  box-shadow:0 3px 12px -4px rgba(6,95,51,.55);
}
.nav-link-bns.active .ico{opacity:1;}

.quick-stats-box{
  margin:.75rem 1rem 1.05rem;
  background:var(--surface-soft);
  border:1px solid var(--border-soft);
  border-radius:14px;
  padding:.85rem .95rem;
  font-size:.7rem;
}
.quick-stats-box h6{
  font-size:.6rem;
  font-weight:800;
  letter-spacing:.09em;
  margin:0 0 .55rem;
  text-transform:uppercase;
  color:#344f3a;
}
.qs-row{
  display:flex;justify-content:space-between;align-items:center;
  background:#fff;
  border:1px solid #e3ebe4;
  padding:.5rem .6rem;
  border-radius:9px;
  font-size:.63rem;
  font-weight:600;
  margin-bottom:.45rem;
  line-height:1.25;
}
.qs-row:last-child{margin-bottom:0;}
.qs-row .val{font-weight:700;}

.sidebar-tip{
  margin:0 1rem 1.1rem;
  background:#e6f5ec;
  border:1px solid #d3e8d9;
  border-radius:14px;
  padding:.85rem .9rem;
  font-size:.62rem;
  line-height:1.25;
  color:#184d2b;
}
.sidebar-tip h6{
  margin:0 0 .35rem;
  font-size:.62rem;
  font-weight:700;
  display:flex;
  gap:.45rem;
  align-items:center;
  letter-spacing:.05em;
}

.sidebar-footer{
  margin-top:auto;
  padding:.9rem 1rem 1.05rem;
  font-size:.55rem;
  color:#627567;
  border-top:1px solid var(--border-soft);
}

/* Topbar */
.topbar{
  height:60px;
  background:var(--surface);
  border-bottom:1px solid var(--border-soft);
  display:flex;align-items:center;
  gap:1.1rem;
  padding:0 1.6rem;
  font-size:.78rem;
  flex-shrink:0;
}
.btn-toggle{display:none;}
@media (max-width: 992px){
  .sidebar{
    position:fixed;
    inset:0 auto 0 0;
    transform:translateX(-100%);
    transition:.35s;
    max-width:270px;
    box-shadow:0 0 0 200vmax rgba(0,0,0,0);
  }
  .sidebar.show{
    transform:translateX(0);
    box-shadow:0 0 0 200vmax rgba(0,0,0,.35);
  }
  .btn-toggle{display:inline-flex;}
  .content-area{padding-top:60px;}
  main{padding:1.25rem 1.1rem 2.1rem;}
}

.search-wrap{
  flex:1;
  max-width:560px;
  position:relative;
}
.search-wrap input{
  width:100%;
  background:var(--bg);
  border:1px solid var(--border-soft);
  border-radius:42px;
  padding:.58rem 1rem .58rem 2.3rem;
  font-size:.68rem;
  font-weight:500;
}
.search-wrap input:focus{outline:2px solid var(--green-accent);}
.search-wrap i{
  position:absolute;
  left:.95rem;
  top:50%;
  transform:translateY(-50%);
  font-size:.85rem;
  color:var(--muted);
}

.quick-add-btn{
  background:var(--green-accent);
  color:#fff;
  border:none;
  padding:.6rem 1.05rem;
  font-size:.7rem;
  font-weight:600;
  border-radius:11px;
  display:inline-flex;
  align-items:center;
  gap:.45rem;
  box-shadow:0 2px 6px -2px rgba(20,104,60,.5);
}
.quick-add-btn:hover{background:#0b6b3b;}

.notif-btn{
  width:40px;height:40px;
  border:1px solid var(--border-soft);
  background:var(--bg);
  border-radius:12px;
  display:flex;
  align-items:center;
  justify-content:center;
  position:relative;
  color:var(--green-accent);
  font-size:.95rem;
}
.notif-btn:hover{background:#edf5ef;}
.notif-badge{
  position:absolute;top:-4px;right:-4px;
  background:var(--red);color:#fff;font-size:.5rem;
  font-weight:700;padding:2px 5px;border-radius:11px;line-height:1;
}

.user-chip{
  display:flex;
  align-items:center;
  gap:.6rem;
  padding:.52rem .8rem;
  border:1px solid var(--border-soft);
  background:var(--surface);
  border-radius:36px;
  font-size:.68rem;
  font-weight:600;
  color:#1e3e27;
}
.user-avatar{
  width:36px;height:36px;
  background:var(--green-accent);
  color:#fff;
  display:flex;
  align-items:center;
  justify-content:center;
  border-radius:50%;
  font-weight:700;
  font-size:.78rem;
  letter-spacing:.45px;
}

/* Content */
.content-area{
  flex:1;
  min-width:0;
  display:flex;
  flex-direction:column;
  overflow:hidden;
  background:var(--bg);
}
main{
  flex:1;
  overflow:auto;
  padding:1.55rem 1.9rem 2.2rem;
  scroll-behavior:smooth;
  font-size:.82rem;
}
main::-webkit-scrollbar{width:12px;}
main::-webkit-scrollbar-thumb{background:#c4d0c8;border-radius:8px;}
main::-webkit-scrollbar-thumb:hover{background:#b0c0b6;}

/* Page heading */
h1.page-title{
  font-size:1.35rem; /* match BHW dashboard title sizing */
  font-weight:700;
  letter-spacing:.02em;
  margin:0 0 1.25rem;
  color:#0a3a1e;
}

/* Stat cards */
.stat-grid{
  display:grid;
  grid-template-columns:repeat(auto-fill,minmax(245px,1fr));
  gap:1.1rem;
  margin-bottom:1.4rem;
}
.stat-card{
  background:var(--gradient-card);
  border:1px solid var(--border-soft);
  border-radius:16px;
  padding:.95rem .95rem .9rem;
  min-height:118px;
  display:flex;
  flex-direction:column;
  position:relative;
  box-shadow:var(--shadow-sm);
  overflow:hidden;
}
.stat-card:before{
  content:"";position:absolute;left:0;top:0;bottom:0;width:4px;
  border-top-left-radius:16px;border-bottom-left-radius:16px;
  background:#0b7a43;
}
.stat-card.red:before{background:var(--red);}
.stat-card.amber:before{background:var(--amber);}
.stat-card.blue:before{background:var(--blue);}
.stat-title{
  font-size:.57rem;
  font-weight:800;
  letter-spacing:.12em;
  text-transform:uppercase;
  color:#264d32;
  margin:0 0 .45rem;
  display:flex;
  align-items:center;
  gap:.45rem;
}
.stat-val{
  font-size:2rem; /* match BHW metric number sizing */
  line-height:1.05;
  font-weight:700;
  color:#063b21;
}
.stat-desc{
  font-size:.54rem;
  font-weight:600;
  letter-spacing:.035em;
  margin-top:.4rem;
  color:var(--muted);
}
.stat-pills{
  display:flex;
  flex-wrap:wrap;
  gap:.35rem;
  margin-top:.55rem;
}
.pill{
  background:#eef6f0;
  font-size:.5rem;
  font-weight:700;
  padding:.3rem .6rem;
  border-radius:999px;
  letter-spacing:.04em;
  display:inline-flex;
  align-items:center;
  gap:.3rem;
  color:#0d5330;
}
.pill.red{background:#ffe4e4;color:#a82929;}
.pill.amber{background:#ffecc7;color:#8d5b00;}
.pill.blue{background:#e1f1ff;color:#135b93;}
.progress-thin{height:3px;background:#e2ece6;border-radius:3px;margin-top:.45rem;overflow:hidden;}
.progress-thin span{display:block;height:100%;background:#0b7a43;width:60%;}

/* Alerts / priority panel */
.priority-panel{
  background:var(--surface);
  border:1px solid var(--border-soft);
  border-left:4px solid var(--red);
  border-radius:16px;
  padding:1.05rem 1.05rem .9rem;
  margin-bottom:1.4rem;
}
.priority-panel-header{
  display:flex;
  align-items:flex-start;
  justify-content:space-between;
  margin-bottom:.85rem;
}
.priority-panel-header h4{
  font-size:.78rem;
  font-weight:800;
  letter-spacing:.07em;
  margin:0;
  display:flex;
  gap:.5rem;
  align-items:center;
  color:#143c28;
}
.priority-panel-sub{
  font-size:.55rem;
  color:var(--muted);
  letter-spacing:.03em;
  margin:.15rem 0 0;
  font-weight:600;
}
.case-item{
  display:flex;
  align-items:center;
  justify-content:space-between;
  background:#fff;
  border:1px solid #e9efeb;
  padding:.6rem .75rem;
  border-radius:11px;
  margin-bottom:.5rem;
  font-size:.6rem;
}
.case-item:last-child{margin-bottom:0;}
.case-left{display:flex;align-items:center;gap:.55rem;min-width:0;}
.case-avatar{
  width:32px;height:32px;border-radius:10px;
  background:#ffe1e1;
  color:var(--red);
  display:flex;
  align-items:center;
  justify-content:center;
  font-size:.85rem;
  font-weight:600;
}
.case-info{display:flex;flex-direction:column;min-width:0;}
.case-info .name{
  font-size:.64rem;
  font-weight:700;
  line-height:1.05;
  white-space:nowrap;
  overflow:hidden;
  text-overflow:ellipsis;
}
.case-info .meta{
  font-size:.52rem;
  color:var(--muted);
  margin-top:2px;
  white-space:nowrap;
  overflow:hidden;
  text-overflow:ellipsis;
}
.case-controls{display:flex;align-items:center;gap:.45rem;}
.badge-status{
  font-size:.52rem;
  font-weight:800;
  padding:.38rem .65rem;
  border-radius:14px;
  letter-spacing:.06em;
  text-transform:uppercase;
  background:#e2f1e6;
  color:#135d30;
}
.badge-SAM{background:#ffdcdc;color:#b02020;}
.badge-MAM{background:#ffebc9;color:#845900;}
.badge-UW{background:#fff0d6;color:#7c5100;}
.badge-NOR{background:#dff4e4;color:#15692d;}
.badge-OW,.badge-OB{background:#e1f1ff;color:#105694;}

.btn-view{
  background:var(--bg);
  border:1px solid var(--border-soft);
  font-size:.55rem;
  font-weight:700;
  padding:.42rem .7rem;
  border-radius:9px;
  letter-spacing:.04em;
}
.btn-view:hover{background:#edf4ef;}

/* Lower grid / tiles */
.lower-grid{
  display:grid;
  gap:1.15rem;
  grid-template-columns:repeat(auto-fit,minmax(300px,1fr));
  margin-bottom:1.75rem;
}
.tile{
  background:var(--surface);
  border:1px solid var(--border-soft);
  border-radius:16px;
  padding:1.05rem 1.1rem 1.15rem;
  display:flex;
  flex-direction:column;
  box-shadow:var(--shadow-sm);
  font-size:.76rem;
}
.tile-header{display:flex;align-items:center;gap:.55rem;margin-bottom:.6rem;}
.tile-header h5{
  font-size:.66rem;
  font-weight:800;
  letter-spacing:.07em;
  margin:0;
  display:flex;
  gap:.45rem;
  align-items:center;
  text-transform:uppercase;
  color:#18432b;
}
.tile-sub{
  font-size:.55rem;
  color:var(--muted);
  margin:0 0 .75rem;
  letter-spacing:.035em;
  font-weight:600;
}

/* Distribution rows */
.dist-row{
  display:flex;
  justify-content:space-between;
  align-items:center;
  background:#fbfdfb;
  border:1px solid #e4ebe5;
  padding:.5rem .6rem;
  border-radius:9px;
  font-size:.55rem;
  font-weight:700;
  margin-bottom:.45rem;
  letter-spacing:.03em;
}
.dist-row:last-child{margin-bottom:0;}
.dist-left{display:flex;align-items:center;gap:.45rem;}

/* Trend placeholder */
.chart-placeholder{
  border:1px dashed #c9d8cb;
  background:linear-gradient(135deg,#f9fcfa 0%,#f2f8f4 100%);
  height:140px;
  border-radius:11px;
  display:flex;
  align-items:center;
  justify-content:center;
  color:#647567;
  font-size:.56rem;
  font-weight:600;
}

/* Loading */
.loading-state{
  padding:2.6rem 1.4rem;
  text-align:center;
  color:#546759;
  font-size:.75rem;
}
.spinner{
  width:26px;height:26px;
  border:3px solid #d4e6d5;
  border-top-color:#0b7a43;
  border-radius:50%;
  animation:spin .75s linear infinite;
  margin:0 auto .9rem;
}
@keyframes spin{to{transform:rotate(360deg);}}

/* Fade */
.fade-in{animation:fadeIn .35s ease;}
@keyframes fadeIn{from{opacity:0;transform:translateY(8px);}to{opacity:1;transform:translateY(0);}}

/* Responsive adjustments */
@media (max-width:1250px){ .stat-grid{grid-template-columns:repeat(auto-fill,minmax(220px,1fr));} }
</style>
</head>
<body>
<div class="layout-wrapper">

  <!-- Sidebar -->
  <aside class="sidebar" id="sidebar">
    <div class="brand">
      <div class="brand-icon">🌿</div>
      <div class="brand-text">
        BNS Portal
        <small>Nutrition System</small>
      </div>
    </div>

    <div class="nav-section-title">Navigation</div>
    <ul class="nav-menu">
      <li><a href="#" class="nav-link-bns active" data-module="dashboard_home" data-label="Dashboard"><span class="ico"><i class="bi bi-speedometer2"></i></span><span>Dashboard</span></a></li>
      <li><a href="#" class="nav-link-bns" data-module="child_profiles" data-label="Children Management"><span class="ico"><i class="bi bi-people"></i></span><span>Children Management</span></a></li>
      <li><a href="#" class="nav-link-bns" data-module="weighing_sessions" data-label="Nutrition Data Entry"><span class="ico"><i class="bi bi-clipboard2-data"></i></span><span>Nutrition Data Entry</span></a></li>
      <li><a href="#" class="nav-link-bns" data-module="nutrition_classification" data-label="Growth Monitoring"><span class="ico"><i class="bi bi-graph-up"></i></span><span>Growth Monitoring</span></a></li>
      <li><a href="#" class="nav-link-bns" data-module="feeding_programs" data-label="Supplementation"><span class="ico"><i class="bi bi-capsule-pill"></i></span><span>Supplementation</span></a></li>
      <li><a href="#" class="nav-link-bns" data-module="nutrition_calendar" data-label="Event Scheduling"><span class="ico"><i class="bi bi-calendar3"></i></span><span>Event Scheduling</span></a></li>
      <li><a href="#" class="nav-link-bns" data-module="report_status_distribution" data-label="Nutrition Reports"><span class="ico"><i class="bi bi-file-bar-graph"></i></span><span>Nutrition Reports</span></a></li>
    </ul>

    <div class="quick-stats-box" id="quickStatsBox">
      <h6>Quick Stats</h6>
      <div class="qs-row"><span>Children Monitored</span><span class="val" id="qsChildren">—</span></div>
      <div class="qs-row"><span>Malnutrition Cases</span><span class="val text-warning" id="qsMal">—</span></div>
      <div class="qs-row"><span>Normal Status</span><span class="val text-success" id="qsNormal">—</span></div>
    </div>

    <div class="sidebar-tip">
      <h6><i class="bi bi-heart-pulse-fill text-success"></i> Nutrition Tip</h6>
      Regular weighing helps track child growth and identify malnutrition early.
    </div>

    <div class="sidebar-footer">
      Powered by Barangay Health System<br>&copy; <?php echo date('Y'); ?>
    </div>
  </aside>

  <!-- Main content area -->
  <div class="content-area">
    <header class="topbar">
      <button class="btn btn-outline-success btn-sm btn-toggle" id="sidebarToggle" aria-label="Toggle sidebar"><i class="bi bi-list"></i></button>

      <div class="search-wrap">
        <i class="bi bi-search"></i>
        <input type="text" id="globalSearch" placeholder="Search children, caregivers..." aria-label="Search">
      </div>

      <button class="quick-add-btn" data-module="child_profiles" data-label="Children Management">
        <i class="bi bi-plus-lg"></i> Quick Add Child
      </button>

      <button class="notif-btn" type="button" aria-label="Notifications">
        <i class="bi bi-bell"></i>
        <span class="notif-badge" id="notifBadge" style="display:none;">0</span>
      </button>

      <div class="user-chip" aria-label="User profile">
        <div class="user-avatar"><?php echo htmlspecialchars($initials); ?></div>
        <div class="d-flex flex-column lh-1">
          <span style="font-size:.7rem;font-weight:700;"><?php echo htmlspecialchars($userFull); ?></span>
          <small style="font-size:.55rem;color:#6a7a6d;font-weight:600;">BNS</small>
        </div>
        <i class="bi bi-chevron-down ms-1" style="font-size:.62rem;opacity:.55;"></i>
      </div>

      <a href="logout.php" class="btn btn-outline-danger btn-sm ms-2" style="font-size:.64rem;font-weight:600;border-radius:10px;">
        <i class="bi bi-box-arrow-right me-1"></i> Logout
      </a>
    </header>

    <main id="mainRegion">
      <h1 class="page-title" id="currentModuleTitle">Dashboard</h1>
      <div id="moduleContent">
        <div class="loading-state">
          <div class="spinner"></div>
          <div class="small">Loading dashboard...</div>
        </div>
      </div>
    </main>
  </div>
</div>

<script>
/* BASIC JS (zoom controls removed) */
window.__BNS_CSRF = "<?php echo htmlspecialchars($csrf); ?>";
const moduleContent = document.getElementById('moduleContent');
const titleEl = document.getElementById('currentModuleTitle');

const api = {
  mothers: 'bns_modules/api_mothers.php',
  children: 'bns_modules/api_children.php',
  wfl: 'bns_modules/api_wfl_status_types.php',
  nutrition: 'bns_modules/api_nutrition.php',
  feeding_programs: 'bns_modules/api_feeding_programs.php',
  weighing_schedules: 'bns_modules/api_weighing_schedules.php',
  nutrition_education: 'bns_modules/api_nutrition_education.php',
  events: 'bns_modules/api_events.php'
};

function fetchJSON(u,o={}){o.headers=Object.assign({'X-Requested-With':'fetch','X-CSRF-Token':window.__BNS_CSRF},o.headers||{});return fetch(u,o).then(r=>{if(!r.ok)throw new Error('HTTP '+r.status);return r.json();});}
function escapeHtml(s){if(s==null)return'';return s.toString().replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');}
function setActive(el){document.querySelectorAll('.nav-link-bns.active').forEach(a=>a.classList.remove('active'));el.classList.add('active');}
function showLoading(label){moduleContent.innerHTML=`<div class="loading-state"><div class="spinner"></div><div>Loading ${escapeHtml(label)}...</div></div>`;}

function renderDashboardHome(label){
  showLoading(label);
  Promise.all([
    fetchJSON(api.children+'?list=1'),
    fetchJSON(api.nutrition+'?classification_summary=1'),
    fetchJSON(api.nutrition+'?recent=1')
  ]).then(([childRes,classRes,recentRes])=>{
    const children = childRes.children||[];
    const classification = classRes.summary||[];
    const recent = recentRes.records||[];
    const total = children.length;
    const malCodes=new Set(['SAM','MAM','UW']);
    let normal=0,mal=0,mam=0,sam=0;
    classification.forEach(c=>{
      const cnt=parseInt(c.child_count||0,10);
      if(c.status_code==='NOR') normal+=cnt;
      if(malCodes.has(c.status_code)) mal+=cnt;
      if(c.status_code==='MAM') mam=cnt;
      if(c.status_code==='SAM') sam=cnt;
    });
    document.getElementById('qsChildren').textContent=total;
    document.getElementById('qsMal').textContent=mal;
    document.getElementById('qsNormal').textContent=normal;

    const priority=[];
    const seen=new Set();
    recent.forEach(r=>{
      if(malCodes.has(r.status_code) && !seen.has(r.child_name)){
        priority.push(r);seen.add(r.child_name);
      }
    });

    const trendSvg=buildTrend(recent);

    moduleContent.innerHTML=`
      <div class="fade-in">
        <div class="stat-grid">
          ${statCard('Children Monitored', total,'Active in monitoring program','green', true)}
          ${statCard('Supplements Given','342','This quarter','amber', false,'<span class="pill">Vit A: 120</span><span class="pill">Iron: 142</span><span class="pill">Deworm: 80</span>')}
          ${statCard('Malnutrition Cases', mal,'Requiring intervention','red', false,'<span class="pill amber">MAM: '+mam+'</span><span class="pill red">SAM: '+sam+'</span>')}
          ${statCard('Growth Trend','+5.2%','Normal status increase','blue',false,'<span class="pill blue"><i class="bi bi-arrow-trend-up"></i> Improving</span>')}
        </div>

        <div class="priority-panel">
          <div class="priority-panel-header">
            <div>
              <h4><i class="bi bi-shield-exclamation text-danger"></i> Malnutrition Alerts</h4>
              <p class="priority-panel-sub">Cases requiring urgent intervention</p>
            </div>
            <span class="badge bg-danger-subtle text-danger-emphasis fw-semibold" style="font-size:.55rem;">${priority.length} Case${priority.length!==1?'s':''}</span>
          </div>
          <div>
            ${
              priority.length ? priority.map(p=>priorityItem(p)).join('') :
              `<div class="text-center text-muted" style="font-size:.6rem;padding:.5rem 0;">No priority cases.</div>`
            }
          </div>
        </div>

        <div class="lower-grid">
          <div class="tile">
            <div class="tile-header">
              <h5><i class="bi bi-graph-up-arrow text-success"></i> Growth Monitoring Trends</h5>
            </div>
            <p class="tile-sub">6-month nutrition status comparison</p>
            ${trendSvg}
            <p class="small-note mt-2 mb-0" style="font-size:.55rem;">Relative pattern (NOR counts, sample)</p>
          </div>

          <div class="tile">
            <div class="tile-header">
              <h5><i class="bi bi-pie-chart text-success"></i> Nutrition Status Distribution</h5>
            </div>
            <p class="tile-sub">Current classification breakdown</p>
            <div>
              ${
                classification.map(c=>distRow(c.status_code||'UNSET',c.child_count)).join('') ||
                '<div class="text-muted" style="font-size:.55rem;">No data.</div>'
              }
            </div>
          </div>
        </div>
      </div>
    `;
  }).catch(err=>{
    moduleContent.innerHTML='<div class="alert alert-danger small">Error: '+escapeHtml(err.message)+'</div>';
  });

  function statCard(t,val,desc,color,progress=false,extras=''){
    return `<div class="stat-card ${color}">
      <div class="stat-title"><i class="bi ${iconFor(t)}"></i>${escapeHtml(t)}</div>
      <div class="stat-val">${escapeHtml(val)}</div>
      ${progress?'<div class="progress-thin"><span></span></div>':''}
      <div class="stat-desc">${escapeHtml(desc)}</div>
      ${extras?'<div class="stat-pills">'+extras+'</div>':''}
    </div>`;
  }
  function iconFor(t){
    if(/Children/i.test(t)) return 'bi-people-fill';
    if(/Supplements/i.test(t)) return 'bi-capsule-pill';
    if(/Malnutrition/i.test(t)) return 'bi-exclamation-triangle-fill';
    if(/Growth Trend/i.test(t)) return 'bi-graph-up';
    return 'bi-circle';
  }
  function priorityItem(r){
    return `<div class="case-item">
      <div class="case-left">
        <div class="case-avatar"><i class="bi bi-exclamation"></i></div>
        <div class="case-info">
          <span class="name">${escapeHtml(r.child_name)}</span>
          <span class="meta">${r.age_in_months} mos • Status: ${escapeHtml(r.status_code||'')}</span>
        </div>
      </div>
      <div class="case-controls">
        ${badge(r.status_code)}
        <button class="btn-view">View</button>
      </div>
    </div>`;
  }
  function badge(s){
    if(!s) return `<span class="badge-status">—</span>`;
    return `<span class="badge-status badge-${escapeHtml(s)}">${escapeHtml(s)}</span>`;
  }
  function distRow(code,count){
    return `<div class="dist-row">
      <div class="dist-left">${badge(code)}<span>${escapeHtml(code)}</span></div>
      <span style="font-size:.52rem;font-weight:700;">${count}</span>
    </div>`;
  }
  function buildTrend(recent){
    const map={};
    recent.forEach(r=>{
      if(!r.weighing_date) return;
      const ym=r.weighing_date.slice(0,7);
      if(!map[ym]) map[ym]={NOR:0};
      if(r.status_code==='NOR') map[ym].NOR++;
    });
    const arr=Object.entries(map).sort((a,b)=>a[0]>b[0]?1:-1).slice(-6)
      .map(([ym,o])=>({label:ym.slice(5),value:o.NOR}));
    if(!arr.length) return `<div class="chart-placeholder">No trend</div>`;
    const max=Math.max(...arr.map(d=>d.value))||1;
    const pts=arr.map((d,i)=>{
      const x=(i/(arr.length-1))*100;
      const y=100 - (d.value/max)*85 - 7;
      return {x,y,label:d.label};
    });
    const poly=pts.map(p=>`${p.x},${p.y}`).join(' ');
    const circles=pts.map(p=>`<circle cx="${p.x}" cy="${p.y}" r="2" fill="#0b7a43"></circle>`).join('');
    return `<div style="width:100%;position:relative;">
      <svg viewBox="0 0 100 100" preserveAspectRatio="none" style="width:100%;height:140px;">
        <polyline fill="none" stroke="#0b7a43" stroke-width="1.4" points="${poly}" />
        ${circles}
      </svg>
      <div class="d-flex justify-content-between" style="margin-top:-10px;">
        ${pts.map(p=>`<span style="font-size:.5rem;color:#637668;">${p.label}</span>`).join('')}
      </div>
    </div>`;
  }
}

/* Placeholder modules (replace with real content later) */
function renderChildrenModule(label){ showLoading(label); moduleContent.innerHTML='<div class="tile fade-in"><h5 style="font-size:.68rem;">Children Module</h5><p class="small-note">Insert children management UI here.</p></div>'; }
function renderWeighingModule(label){ showLoading(label); moduleContent.innerHTML='<div class="tile fade-in"><h5 style="font-size:.68rem;">Weighing Module</h5><p class="small-note">Placeholder.</p></div>'; }
function renderNutritionClassificationModule(label){ showLoading(label); fetchJSON(api.nutrition+'?classification_summary=1').then(j=>{ moduleContent.innerHTML='<div class="tile fade-in"><h5 style="font-size:.68rem;">'+escapeHtml(label)+'</h5><pre style="font-size:.55rem;">'+escapeHtml(JSON.stringify(j.summary,null,2))+'</pre></div>'; }).catch(e=> moduleContent.innerHTML='<div class="alert alert-danger small">'+escapeHtml(e.message)+'</div>'); }
function renderFeedingProgramsModule(label){ showLoading(label); moduleContent.innerHTML='<div class="tile fade-in"><h5 style="font-size:.68rem;">Supplementation</h5><p class="small-note">Placeholder.</p></div>'; }
function renderNutritionCalendarModule(label){ showLoading(label); moduleContent.innerHTML='<div class="tile fade-in"><h5 style="font-size:.68rem;">Calendar</h5><p class="small-note">Placeholder.</p></div>'; }
function renderMothersModule(label){ showLoading(label); moduleContent.innerHTML='<div class="tile fade-in"><h5 style="font-size:.68rem;">Mothers Module</h5><p class="small-note">Placeholder.</p></div>'; }
function renderReportModule(label){ renderNutritionClassificationModule(label); }

/* Module map */
const handlers={
  dashboard_home:renderDashboardHome,
  child_profiles:renderChildrenModule,
  weighing_sessions:renderWeighingModule,
  nutrition_classification:renderNutritionClassificationModule,
  feeding_programs:renderFeedingProgramsModule,
  nutrition_calendar:renderNutritionCalendarModule,
  mothers_caregivers:renderMothersModule,
  report_status_distribution:renderReportModule
};
function loadModule(mod,label){
  titleEl.textContent=label;
  (handlers[mod]||(()=>moduleContent.innerHTML='<div class="alert alert-secondary">Module not implemented.</div>'))(label);
  moduleContent.scrollTop=0;
}

/* Navigation */
document.querySelectorAll('.nav-link-bns[data-module]').forEach(a=>{
  a.addEventListener('click',e=>{
    e.preventDefault();
    setActive(a);
    loadModule(a.dataset.module,a.dataset.label||a.textContent.trim());
    if(window.innerWidth<992) document.getElementById('sidebar').classList.remove('show');
  });
});

/* Quick Add Child route */
document.querySelectorAll('[data-module="child_profiles"].quick-add-btn').forEach(btn=>{
  btn.addEventListener('click',e=>{
    e.preventDefault();
    const link=document.querySelector('.nav-link-bns[data-module="child_profiles"]');
    if(link) setActive(link);
    loadModule('child_profiles','Children Management');
  });
});

/* Sidebar mobile toggle */
const sidebar=document.getElementById('sidebar');
document.getElementById('sidebarToggle')?.addEventListener('click',()=>sidebar.classList.toggle('show'));
document.addEventListener('click',e=>{
  if(window.innerWidth>=992) return;
  if(sidebar.classList.contains('show') && !sidebar.contains(e.target) && !e.target.closest('#sidebarToggle')){
    sidebar.classList.remove('show');
  }
});

/* Initial load */
loadModule('dashboard_home','Dashboard');
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>