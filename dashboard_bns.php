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
/* Nutrition Data Entry Form Styles */
.form-section {
  background: var(--surface);
  border: 1px solid var(--border-soft);
  border-radius: 12px;
  padding: 1.2rem;
  margin-bottom: 1.5rem;
  box-shadow: var(--shadow-sm);
}

.form-section-header {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  margin-bottom: 1rem;
  padding-bottom: 0.8rem;
  border-bottom: 1px solid var(--border-soft);
}

.form-section-icon {
  width: 24px;
  height: 24px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.9rem;
  border-radius: 6px;
}

.form-section-icon.address {
  background: #ffe4e4;
  color: #b02020;
}

.form-section-icon.mother {
  background: #ffecc7;
  color: #8d5b00;
}

.form-section-icon.child {
  background: #ffebc9;
  color: #845900;
}

.form-section-title {
  font-size: 0.85rem;
  font-weight: 700;
  color: var(--text);
  margin: 0;
}

.form-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 1.2rem;
}

.form-group {
  display: flex;
  flex-direction: column;
}

.form-label {
  font-size: 0.7rem;
  font-weight: 600;
  color: var(--text);
  margin-bottom: 0.4rem;
}

.form-control, .form-select {
  font-size: 0.72rem;
  padding: 0.65rem 0.85rem;
  border: 1px solid var(--border-soft);
  border-radius: 8px;
  background: var(--surface);
  transition: border-color 0.15s ease;
}

.form-control:focus, .form-select:focus {
  outline: none;
  border-color: var(--green-accent);
  box-shadow: 0 0 0 2px rgba(7, 122, 68, 0.1);
}

.form-control::placeholder {
  color: var(--muted);
  font-style: italic;
}

.date-input {
  position: relative;
}

.date-input input[type="date"] {
  padding-right: 2.5rem;
}

.date-input::after {
  content: '\f4c3';
  font-family: 'Bootstrap Icons';
  position: absolute;
  right: 0.8rem;
  top: 50%;
  transform: translateY(-50%);
  color: var(--muted);
  pointer-events: none;
  font-size: 0.8rem;
}

.page-header {
  display: flex;
  align-items: center;
  gap: 0.8rem;
  margin-bottom: 1.5rem;
  padding-bottom: 1rem;
  border-bottom: 1px solid var(--border-soft);
}

.page-header-icon {
  width: 40px;
  height: 40px;
  background: var(--surface-soft);
  border: 1px solid var(--border-soft);
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.2rem;
  color: var(--muted);
}

.page-header-text h1 {
  font-size: 1.35rem;
  font-weight: 700;
  color: var(--text);
  margin: 0;
}

.page-header-text p {
  font-size: 0.75rem;
  color: var(--muted);
  margin: 0;
  font-weight: 500;
}
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

/* Global font scaling utility (apply a class to <body>) */
body.font-xs { --base-font-size:14px; }
body.font-sm { --base-font-size:15px; }
body.font-md { --base-font-size:16px; } /* default */
body.font-lg { --base-font-size:17px; }
body.font-xl { --base-font-size:18px; }

/* Optional: fine‑tune key UI elements to track base scale */
body.font-xs .stat-val{font-size:1.85rem;}
body.font-sm .stat-val{font-size:1.9rem;}
body.font-md .stat-val{font-size:2rem;}
body.font-lg .stat-val{font-size:2.1rem;}
body.font-xl .stat-val{font-size:2.2rem;}

body.font-xs h1.page-title{font-size:1.2rem;}
body.font-sm h1.page-title{font-size:1.28rem;}
body.font-lg h1.page-title{font-size:1.42rem;}
body.font-xl h1.page-title{font-size:1.5rem;}

/* Navigation link sizing tweaks */
body.font-xs .nav-link-bns{font-size:.74rem;}
body.font-sm .nav-link-bns{font-size:.77rem;}
body.font-lg .nav-link-bns{font-size:.83rem;}
body.font-xl .nav-link-bns{font-size:.86rem;}

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

/* Sidebar logout button */
.sidebar-logout{
  margin:.25rem 1rem 1rem;
}
.sidebar-logout .btn{
  padding:.55rem .9rem;
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
/* Calendar Widget Styles */
.calendar-widget {
  font-size: 0.7rem;
}

.calendar-days-header {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 2px;
  margin-bottom: 0.5rem;
}

.calendar-day-header {
  text-align: center;
  font-size: 0.6rem;
  font-weight: 600;
  color: var(--muted);
  padding: 0.3rem;
}

.calendar-days {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 2px;
}

.calendar-day {
  text-align: center;
  padding: 0.4rem;
  font-size: 0.65rem;
  font-weight: 500;
  cursor: pointer;
  border-radius: 4px;
  transition: background-color 0.15s ease;
}

.calendar-day:hover {
  background: var(--surface-soft);
}

.calendar-day.prev-month,
.calendar-day.next-month {
  color: var(--muted);
  opacity: 0.5;
}

.calendar-day.current-day {
  background: var(--green);
  color: white;
  font-weight: 700;
}

/* Event List Styles */
.event-list {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.event-item {
  padding: 1rem;
  border: 1px solid var(--border-soft);
  border-radius: 12px;
  background: var(--surface);
  transition: box-shadow 0.15s ease;
}

.event-item:hover {
  box-shadow: var(--shadow-sm);
}

.event-icon {
  width: 32px;
  height: 32px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.9rem;
}

.event-title {
  font-size: 0.75rem;
  font-weight: 700;
  color: var(--text);
  margin: 0 0 0.3rem 0;
}

.event-details {
  display: flex;
  gap: 1rem;
  font-size: 0.6rem;
  color: var(--muted);
}

.event-details span {
  display: flex;
  align-items: center;
  gap: 0.3rem;
}

.event-badge {
  font-size: 0.55rem;
  font-weight: 600;
  padding: 0.3rem 0.6rem;
  border-radius: 12px;
  white-space: nowrap;
}

.event-badge.opt-plus {
  background: #e8f5ea;
  color: #077a44;
}

.event-badge.weighing {
  background: #e1f1ff;
  color: #1c79d0;
}

.event-badge.education {
  background: #f3e8ff;
  color: #a259c6;
}

.event-badge.feeding {
  background: #ffecc7;
  color: #f4a400;
}

/* Responsive SVG charts (keeps aspect ratio, no stretching) */
.svg-chart{width:100%;height:auto;display:block;aspect-ratio:16/9;}
.svg-chart.sm{aspect-ratio:25/14;} /* for smaller sparkline-style charts */

/* Event status pill */
.status-pill{
  font-size:.6rem;
  font-weight:700;
  padding:.28rem .6rem;
  border-radius:999px;
  border:1px solid transparent;
  line-height:1;
  white-space:nowrap;
}
.status-pill.scheduled{
  background:#e8f5ea;      /* light green */
  color:#077a44;           /* green */
  border-color:#077a4422;
}
.status-pill.completed{
  background:#e7f0ff;      /* light blue */
  color:#1c79d0;           /* blue */
  border-color:#1c79d022;
}

</style>
</head>
<body class="font-lg">
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

    <div class="sidebar-logout" style="padding:1rem 1.1rem;">
      <a href="logout.php" class="btn btn-outline-danger w-100" style="font-size:.7rem;font-weight:600;border-radius:10px;">
        <i class="bi bi-box-arrow-right me-1"></i> Logout
      </a>
    </div>
    <div class="sidebar-footer">
      Powered by Barangay Health System<br>&copy; <?php echo date('Y'); ?>
    </div>
  </aside>

  <!-- Main content area -->
  <div class="content-area">
<!-- REPLACE the whole header.topbar with this simplified version -->
<header class="topbar">
  <button class="btn btn-outline-success btn-sm btn-toggle" id="sidebarToggle" aria-label="Toggle sidebar">
    <i class="bi bi-list"></i>
  </button>

  <!-- spacer so user-chip stays on the right -->
  <div class="ms-auto"></div>

  <div class="user-chip" aria-label="User profile">
    <div class="user-avatar"><?php echo htmlspecialchars($initials); ?></div>
    <div class="d-flex flex-column lh-1">
      <span style="font-size:.7rem;font-weight:700;"><?php echo htmlspecialchars($userFull); ?></span>
      <small style="font-size:.55rem;color:#6a7a6d;font-weight:600;">BNS</small>
    </div>
    <i class="bi bi-chevron-down ms-1" style="font-size:.62rem;opacity:.55;"></i>
  </div>
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
  events: 'bns_modules/api_events.php',
  supplementation: 'bns_modules/api_supplementation.php'
};

function fetchJSON(u,o={}){o.headers=Object.assign({'X-Requested-With':'fetch','X-CSRF-Token':window.__BNS_CSRF, 'Accept':'application/json'},o.headers||{});return fetch(u,o).then(r=>{if(!r.ok)throw new Error('HTTP '+r.status);return r.json();});}
function escapeHtml(s){if(s==null)return'';return s.toString().replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');}
function setActive(el){document.querySelectorAll('.nav-link-bns.active').forEach(a=>a.classList.remove('active'));el.classList.add('active');}
function showLoading(label){moduleContent.innerHTML=`<div class="loading-state"><div class="spinner"></div><div>Loading ${escapeHtml(label)}...</div></div>`;}

function renderDashboardHome(label){
  showLoading(label);
  
  Promise.all([
    fetchJSON(api.children+'?action=list').catch(err => {
      console.error('Children API error:', err);
      return { children: [] };
    }),
    fetchJSON(api.nutrition+'?classification_summary=1').catch(err => {
      console.error('Nutrition API error:', err);
      return { summary: [] };
    }),
    fetchJSON(api.nutrition+'?recent=1').catch(err => {
      console.error('Recent API error:', err);
      return { records: [] };
    })
  ]).then(([childRes, classRes, recentRes]) => {
    const children = childRes.children || [];
    const classification = classRes.summary || [];
    const recent = recentRes.records || [];
    const total = children.length;
    const malCodes = new Set(['SAM','MAM','UW']);
    let normal = 0, mal = 0, mam = 0, sam = 0;
    
    classification.forEach(c => {
      const cnt = parseInt(c.child_count || 0, 10);
      if(c.status_code === 'NOR') normal += cnt;
      if(malCodes.has(c.status_code)) mal += cnt;
      if(c.status_code === 'MAM') mam = cnt;
      if(c.status_code === 'SAM') sam = cnt;
    });
    
    // Update sidebar stats
    document.getElementById('qsChildren').textContent = total;
    document.getElementById('qsMal').textContent = mal;
    document.getElementById('qsNormal').textContent = normal;

    const priority = [];
    const seen = new Set();
    recent.forEach(r => {
      if(malCodes.has(r.status_code) && !seen.has(r.child_name)){
        priority.push(r);
        seen.add(r.child_name);
      }
    });

    const trendSvg = buildTrend(recent);

    moduleContent.innerHTML = `
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
                '<div class="text-muted" style="font-size:.55rem;">No data available.</div>'
              }
            </div>
          </div>
        </div>
      </div>
    `;
  }).catch(err => {
    console.error('Dashboard error:', err);
    moduleContent.innerHTML = `
      <div class="alert alert-danger" style="font-size:.7rem;">
        <i class="bi bi-exclamation-triangle me-2"></i>
        <strong>Error loading dashboard:</strong> ${escapeHtml(err.message)}
        <br><small>Please check your network connection and try refreshing the page.</small>
      </div>
    `;
  });

  // Helper functions (keep these the same)
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
    const map = {};
    recent.forEach(r => {
      if(!r.weighing_date) return;
      const ym = r.weighing_date.slice(0,7);
      if(!map[ym]) map[ym] = {NOR:0};
      if(r.status_code === 'NOR') map[ym].NOR++;
    });
    
    const arr = Object.entries(map).sort((a,b) => a[0] > b[0] ? 1 : -1).slice(-6)
      .map(([ym,o]) => ({label:ym.slice(5), value:o.NOR}));
    
    if(!arr.length) return `<div class="chart-placeholder">No trend data available</div>`;
    
    const max = Math.max(...arr.map(d => d.value)) || 1;
    const pts = arr.map((d,i) => {
      const x = (i/(arr.length-1)) * 100;
      const y = 100 - (d.value/max) * 85 - 7;
      return {x, y, label: d.label};
    });
    
    const poly = pts.map(p => `${p.x},${p.y}`).join(' ');
    const circles = pts.map(p => `<circle cx="${p.x}" cy="${p.y}" r="2" fill="#0b7a43"></circle>`).join('');
    
    return `<div style="width:100%;position:relative;">
      <svg viewBox="0 0 100 100" preserveAspectRatio="none" style="width:100%;height:140px;">
        <polyline fill="none" stroke="#0b7a43" stroke-width="1.4" points="${poly}" />
        ${circles}
      </svg>
      <div class="d-flex justify-content-between" style="margin-top:-10px;">
        ${pts.map(p => `<span style="font-size:.5rem;color:#637668;">${p.label}</span>`).join('')}
      </div>
    </div>`;
  }
}

/* Placeholder modules (replace with real content later) */
function renderChildrenModule(label) {
  showLoading(label);
  
  // Fetch real data from API
  fetchJSON(api.children + '?action=list')
    .then(response => {
      if (!response.success) {
        throw new Error(response.error || 'Failed to fetch children data');
      }
      
      const children = response.children || [];

      window.__childrenCache = children; // cache for tab switching back to Child Database
      
      moduleContent.innerHTML = `
<div class="d-flex justify-content-between align-items-start mb-3">
  <div>
    <h1 class="page-title mb-1" style="font-size:1.35rem;font-weight:700;color:#0a3a1e;">
      👶 Children Management
    </h1>
    <p class="text-muted mb-0" style="font-size:.75rem;font-weight:500;">Manage child records and profiles</p>
  </div>
</div>

      <!-- Tab Navigation - Removed Mother-Child Linking Tab -->
      <div class="mb-3">
        <ul class="nav nav-tabs" style="border-bottom:2px solid var(--border-soft);">
          <li class="nav-item">
            <a class="nav-link active children-tab" href="#" data-tab="database" style="font-size:.75rem;font-weight:600;color:var(--green);border-bottom:2px solid var(--green);background:none;border-left:none;border-right:none;border-top:none;padding:.75rem 1.2rem;">
              Child Database
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link children-tab" href="#" data-tab="profiles" style="font-size:.75rem;font-weight:600;color:var(--muted);border:none;background:none;padding:.75rem 1.2rem;">
              Profile Management
            </a>
          </li>
        </ul>
      </div>

      <!-- Search & Filter Section -->
      <div class="tile mb-4">
        <div class="tile-header mb-3">
          <h5 style="font-size:.72rem;font-weight:800;color:#18432b;margin:0;">SEARCH & FILTER</h5>
        </div>
        
        <div class="row g-3 align-items-end">
          <!-- Search Input -->
          <div class="col-md-3">
            <label class="form-label" style="font-size:.65rem;font-weight:600;color:var(--muted);margin-bottom:.4rem;">Search</label>
            <div class="position-relative">
              <i class="bi bi-search position-absolute" style="left:.8rem;top:50%;transform:translateY(-50%);font-size:.75rem;color:var(--muted);"></i>
              <input type="text" class="form-control" id="childSearchInput" placeholder="Name, mother..." style="font-size:.7rem;padding:.6rem .8rem .6rem 2.2rem;border:1px solid var(--border-soft);border-radius:8px;background:var(--surface);">
            </div>
          </div>

          <!-- Nutrition Status Dropdown -->
          <div class="col-md-3">
            <label class="form-label" style="font-size:.65rem;font-weight:600;color:var(--muted);margin-bottom:.4rem;">Nutrition Status</label>
            <select class="form-select" id="nutritionStatusFilter" style="font-size:.7rem;padding:.6rem .8rem;border:1px solid var(--border-soft);border-radius:8px;background:var(--surface);">
              <option value="">All Status</option>
              <option value="NOR">Normal (NOR)</option>
              <option value="UW">Underweight (UW)</option>
              <option value="MAM">Moderate Acute Malnutrition (MAM)</option>
              <option value="SAM">Severe Acute Malnutrition (SAM)</option>
              <option value="OW">Overweight (OW)</option>
              <option value="OB">Obese (OB)</option>
            </select>
          </div>

          <!-- Purok Dropdown -->
          <div class="col-md-3">
            <label class="form-label" style="font-size:.65rem;font-weight:600;color:var(--muted);margin-bottom:.4rem;">Purok</label>
            <select class="form-select" id="purokFilter" style="font-size:.7rem;padding:.6rem .8rem;border:1px solid var(--border-soft);border-radius:8px;background:var(--surface);">
              <option value="">All Purok</option>
              <option value="Purok 1">Purok 1</option>
              <option value="Purok 2">Purok 2</option>
              <option value="Purok 3">Purok 3</option>
              <option value="Purok 4">Purok 4</option>
              <option value="Purok 5">Purok 5</option>
            </select>
          </div>

          <!-- Export Button -->
          <div class="col-md-3 d-flex justify-content-end">
            <button class="btn btn-outline-success" onclick="exportChildrenData()" style="font-size:.65rem;font-weight:600;padding:.6rem 1rem;border-radius:8px;border:1px solid var(--green);color:var(--green);">
              <i class="bi bi-download me-1"></i> Export CSV
            </button>
          </div>
        </div>
      </div>

      <!-- Tab Content - DEFAULT TO CHILD DATABASE -->
      <div id="children-tab-content">
        ${renderChildrenTable(children)}
      </div>
    </div>
  `;

      // Add search and filter functionality
      setupChildrenFilters(children);
      
      // Add tab switching functionality (updated to handle only 2 tabs)
      setupChildrenTabsUpdated();
    })
    .catch(error => {
      console.error('Error fetching children data:', error);
      moduleContent.innerHTML = `
        <div class="alert alert-danger" style="font-size:.7rem;">
          <i class="bi bi-exclamation-triangle me-2"></i>
          Error loading children data: ${escapeHtml(error.message)}
        </div>
      `;
    });
}

// Updated tab switching function (removed Mother-Child Linking functionality)
function setupChildrenTabsUpdated() {
  document.querySelectorAll('.children-tab').forEach(tab => {
    tab.addEventListener('click', (e) => {
      e.preventDefault();

      // Update active styles
      document.querySelectorAll('.children-tab').forEach(t => {
        t.classList.remove('active');
        t.style.color = 'var(--muted)';
        t.style.borderBottom = 'none';
      });
      tab.classList.add('active');
      tab.style.color = 'var(--green)';
      tab.style.borderBottom = '2px solid var(--green)';

      const tabType = tab.dataset.tab;
      const contentArea = document.getElementById('children-tab-content');

      if (tabType === 'profiles') {
        // Render Profile Management shell then load list
        contentArea.innerHTML = renderProfileManagementShell();
        fetchJSON(api.children + '?action=list')
          .then(res => populateProfileList(res.children || []))
          .catch(err => {
            console.error('Profile Management load error:', err);
            contentArea.innerHTML = `
              <div class="tile">
                <div class="text-center py-5" style="color:#dc3545;font-size:.7rem;">
                  <i class="bi bi-exclamation-triangle" style="font-size:2rem;opacity:.5;"></i>
                  <div class="mt-2">Error loading profiles</div>
                </div>
              </div>`;
          });
        return;
      }

      if (tabType === 'database') {
        // Re-render the child database from cache, no full module reload
        const list = window.__childrenCache || [];
        contentArea.innerHTML = renderChildrenTable(list);
        // Ensure the Search & Filter (above the tabs) still works with the same list
        setupChildrenFilters(list);
        return;
      }

      // Fallback (shouldn't be needed, pero safe)
      renderChildrenModule('Children Management');
    });
  });
}

function renderChildrenTable(children) {
  const totalChildren = children.length;
  
  // REPLACE the empty-state in renderChildrenTable(children)
if (totalChildren === 0) {
  return `
    <div class="tile">
      <div class="text-center py-5">
        <i class="bi bi-people text-muted" style="font-size:3rem;opacity:0.3;"></i>
        <h6 class="mt-3 mb-1" style="font-size:.8rem;font-weight:600;">No Children Found</h6>
        <p class="text-muted small mb-0" style="font-size:.65rem;">No children available. Try adjusting your filters.</p>
      </div>
    </div>
  `;
}
  
  return `
    <!-- Child Registry Header -->
    <div class="d-flex justify-content-between align-items-center mb-3">
      <div>
        <h6 style="font-size:.8rem;font-weight:700;color:#18432b;margin:0;">Child Registry</h6>
        <p class="text-muted mb-0" style="font-size:.65rem;">${totalChildren} children found</p>
      </div>
      <div class="text-muted" style="font-size:.65rem;font-weight:600;">
        ${totalChildren} Total
      </div>
    </div>

    <!-- Data Table -->
    <div class="tile" style="padding:0;overflow:hidden;">
      <div class="table-responsive">
        <table class="table table-hover mb-0" style="font-size:.7rem;">
          <thead style="background:#f8faf9;border-bottom:1px solid var(--border-soft);">
            <tr>
              <th style="padding:.75rem .8rem;font-size:.65rem;font-weight:700;color:#344f3a;border:none;">Child Name</th>
              <th style="padding:.75rem .8rem;font-size:.65rem;font-weight:700;color:#344f3a;border:none;">Sex</th>
              <th style="padding:.75rem .8rem;font-size:.65rem;font-weight:700;color:#344f3a;border:none;">Birth Date</th>
              <th style="padding:.75rem .8rem;font-size:.65rem;font-weight:700;color:#344f3a;border:none;">Purok</th>
              <th style="padding:.75rem .8rem;font-size:.65rem;font-weight:700;color:#344f3a;border:none;">Mother/Caregiver</th>
              <th style="padding:.75rem .8rem;font-size:.65rem;font-weight:700;color:#344f3a;border:none;">Nutrition Status</th>
              <th style="padding:.75rem .8rem;font-size:.65rem;font-weight:700;color:#344f3a;border:none;">Last Weighing</th>
            </tr>
          </thead>
          <tbody>
            ${children.map(child => renderChildRow(child)).join('')}
          </tbody>
        </table>
      </div>
    </div>
  `;
}function renderChildrenTable(children) {
  const totalChildren = children.length;
  
  if (totalChildren === 0) {
    return `
      <div class="tile">
        <div class="text-center py-5">
          <i class="bi bi-people text-muted" style="font-size:3rem;opacity:0.3;"></i>
          <h6 class="mt-3 mb-1" style="font-size:.8rem;font-weight:600;">No Children Found</h6>
          <p class="text-muted small mb-0" style="font-size:.65rem;">No children have been registered yet.</p>
          <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#registerChildModal">
            <i class="bi bi-plus-lg me-1"></i> Register First Child
          </button>
        </div>
      </div>
    `;
  }
  
  return `
    <!-- Child Registry Header -->
    <div class="d-flex justify-content-between align-items-center mb-3">
      <div>
        <h6 style="font-size:.8rem;font-weight:700;color:#18432b;margin:0;">Child Registry</h6>
        <p class="text-muted mb-0" style="font-size:.65rem;">${totalChildren} children found</p>
      </div>
      <div class="text-muted" style="font-size:.65rem;font-weight:600;">
        ${totalChildren} Total
      </div>
    </div>

    <!-- Data Table -->
    <div class="tile" style="padding:0;overflow:hidden;">
      <div class="table-responsive">
        <table class="table table-hover mb-0" style="font-size:.7rem;">
          <thead style="background:#f8faf9;border-bottom:1px solid var(--border-soft);">
            <tr>
              <th style="padding:.75rem .8rem;font-size:.65rem;font-weight:700;color:#344f3a;border:none;">Child Name</th>
              <th style="padding:.75rem .8rem;font-size:.65rem;font-weight:700;color:#344f3a;border:none;">Sex</th>
              <th style="padding:.75rem .8rem;font-size:.65rem;font-weight:700;color:#344f3a;border:none;">Birth Date</th>
              <th style="padding:.75rem .8rem;font-size:.65rem;font-weight:700;color:#344f3a;border:none;">Purok</th>
              <th style="padding:.75rem .8rem;font-size:.65rem;font-weight:700;color:#344f3a;border:none;">Mother/Caregiver</th>
              <th style="padding:.75rem .8rem;font-size:.65rem;font-weight:700;color:#344f3a;border:none;">Nutrition Status</th>
              <th style="padding:.75rem .8rem;font-size:.65rem;font-weight:700;color:#344f3a;border:none;">Last Weighing</th>
            </tr>
          </thead>
          <tbody>
            ${children.map(child => renderChildRow(child)).join('')}
          </tbody>
        </table>
      </div>
    </div>
  `;
}

function renderChildRow(child) {
  const statusBadge = getNutritionStatusBadge(child.nutrition_status);
  const sexIcon = child.sex === 'male' ? 'bi-gender-male' : 'bi-gender-female';
  const sexColor = child.sex === 'male' ? '#1c79d0' : '#e91e63';
  
  return `
    <tr style="border-bottom:1px solid #f0f4f1;" data-child-id="${child.child_id}">
      <td style="padding:.8rem;border:none;">
        <div class="d-flex align-items-center gap-2">
          <div style="width:24px;height:24px;background:#e8f5ea;border-radius:50%;display:flex;align-items:center;justify-content:center;">
            <i class="bi ${sexIcon}" style="font-size:.7rem;color:${sexColor};"></i>
          </div>
          <span style="font-weight:600;color:#1e3e27;">${escapeHtml(child.full_name)}</span>
        </div>
      </td>
      <td style="padding:.8rem;border:none;color:#586c5d;">${escapeHtml(child.sex)}</td>
      <td style="padding:.8rem;border:none;color:#586c5d;">${child.birth_date_formatted}</td>
      <td style="padding:.8rem;border:none;color:#586c5d;">${escapeHtml(child.purok_name || 'Not Set')}</td>
      <td style="padding:.8rem;border:none;">
        <div>
          <div style="font-size:.7rem;font-weight:600;color:#1e3e27;">${escapeHtml(child.mother_name)}</div>
          <div style="font-size:.6rem;color:#586c5d;">${escapeHtml(child.mother_contact || 'No contact')}</div>
        </div>
      </td>
      <td style="padding:.8rem;border:none;">
        ${statusBadge}
      </td>
      <td style="padding:.8rem;border:none;color:#586c5d;">${child.last_weighing_formatted}</td>
    </tr>
  `;
}

function getNutritionStatusBadge(status) {
  const statusMap = {
    'NOR': { class: 'badge-NOR', text: 'Normal' },
    'MAM': { class: 'badge-MAM', text: 'MAM' },
    'SAM': { class: 'badge-SAM', text: 'SAM' },
    'UW': { class: 'badge-UW', text: 'Underweight' },
    'OW': { class: 'badge-OW', text: 'Overweight' },
    'OB': { class: 'badge-OB', text: 'Obese' },
    'Not Available': { class: 'badge-status', text: 'Not Available' }
  };
  
  const statusInfo = statusMap[status] || { class: 'badge-status', text: status };
  return `<span class="badge-status ${statusInfo.class}">${statusInfo.text}</span>`;
}

function setupChildrenFilters(allChildren) {
  const searchInput = document.getElementById('childSearchInput');
  const statusFilter = document.getElementById('nutritionStatusFilter');
  const purokFilter = document.getElementById('purokFilter');
  
  function filterChildren() {
    const searchTerm = searchInput.value.toLowerCase();
    const statusValue = statusFilter.value;
    const purokValue = purokFilter.value;
    
    const filteredChildren = allChildren.filter(child => {
      const matchesSearch = !searchTerm || 
        child.full_name.toLowerCase().includes(searchTerm) ||
        child.mother_name.toLowerCase().includes(searchTerm);
      
      const matchesStatus = !statusValue || child.nutrition_status === statusValue;
      const matchesPurok = !purokValue || child.purok_name === purokValue;
      
      return matchesSearch && matchesStatus && matchesPurok;
    });
    
    const contentArea = document.getElementById('children-tab-content');
    contentArea.innerHTML = renderChildrenTable(filteredChildren);
  }
  
  searchInput.addEventListener('input', filterChildren);
  statusFilter.addEventListener('change', filterChildren);
  purokFilter.addEventListener('change', filterChildren);
}

function setupChildrenTabs() {
  document.querySelectorAll('.children-tab').forEach(tab => {
    tab.addEventListener('click', (e) => {
      e.preventDefault();
      // Update active tab
      document.querySelectorAll('.children-tab').forEach(t => {
        t.classList.remove('active');
        t.style.color = 'var(--muted)';
        t.style.borderBottom = 'none';
      });
      tab.classList.add('active');
      tab.style.color = 'var(--green)';
      tab.style.borderBottom = '2px solid var(--green)';
      
      // Update content based on tab
      const tabType = tab.dataset.tab;
      const contentArea = document.getElementById('children-tab-content');
      
      switch(tabType) {
        case 'profiles':
          contentArea.innerHTML = `
            <div class="tile">
              <div class="text-center py-4">
                <i class="bi bi-person-vcard text-muted" style="font-size:2rem;"></i>
                <h6 class="mt-2 mb-1" style="font-size:.75rem;font-weight:600;">Profile Management</h6>
                <p class="text-muted small mb-0" style="font-size:.65rem;">Manage individual child profiles and details</p>
              </div>
            </div>
          `;
          break;
        case 'linking':
          contentArea.innerHTML = `
            <div class="tile">
              <div class="text-center py-4">
                <i class="bi bi-diagram-3 text-muted" style="font-size:2rem;"></i>
                <h6 class="mt-2 mb-1" style="font-size:.75rem;font-weight:600;">Mother-Child Linking</h6>
                <p class="text-muted small mb-0" style="font-size:.65rem;">Link children to their mothers/caregivers</p>
              </div>
            </div>
          `;
          break;
        default: // database
          // Re-fetch and display current data
          renderChildrenModule('Children Management');
          break;
      }
    });
  });
}

// Helper functions for child actions
// REPLACE: viewChild/editChild placeholders with modal implementations
async function viewChild(childId) {
  try {
    const res = await fetchJSON(`${api.children}?action=get&child_id=${childId}`);
    if (!res.success || !res.child) throw new Error(res.error || 'Not found');
    const c = res.child;

    const body = document.querySelector('#childProfileViewModal .modal-body');
    body.innerHTML = `
      <div class="row g-3">
        <div class="col-12">
          <div class="d-flex align-items-center gap-2" style="margin-bottom:.4rem;">
            <div style="width:28px;height:28px;border-radius:8px;background:#e8f5ea;display:flex;align-items:center;justify-content:center;">
              <i class="bi ${c.sex==='male'?'bi-gender-male':'bi-gender-female'}" style="color:${c.sex==='male'?'#1c79d0':'#e91e63'}"></i>
            </div>
            <div>
              <div style="font-weight:800;color:#18432b;">${escapeHtml(c.full_name)}</div>
              <div class="text-muted" style="font-size:.65rem;">${escapeHtml(c.purok_name||'Not Set')}</div>
            </div>
          </div>
        </div>
        <div class="col-12 col-md-6">
          <div class="tile-sub">Child</div>
          <div style="font-size:.7rem;"><strong>Sex:</strong> ${escapeHtml(c.sex)}</div>
          <div style="font-size:.7rem;"><strong>Birth Date:</strong> ${escapeHtml(c.birth_date||'—')}</div>
        </div>
        <div class="col-12 col-md-6">
          <div class="tile-sub">Mother/Caregiver</div>
          <div style="font-size:.7rem;"><strong>Name:</strong> ${escapeHtml(c.mother_name||'—')}</div>
          <div style="font-size:.7rem;"><strong>Contact:</strong> ${escapeHtml(c.mother_contact||'—')}</div>
          <div style="font-size:.7rem;"><strong>Address:</strong> ${escapeHtml(c.address_details||'—')}</div>
        </div>
      </div>
    `;

    const modal = new bootstrap.Modal(document.getElementById('childProfileViewModal'));
    modal.show();
  } catch (e) {
    alert('Error loading child: ' + (e.message || e));
  }
}

// REPLACE the entire editChild(childId) function with this version
async function editChild(childId) {
  try {
    const res = await fetchJSON(`${api.children}?action=get&child_id=${childId}`);
    if (!res.success || !res.child) throw new Error(res.error || 'Not found');
    const c = res.child;

    // Prefill form
    const form = document.getElementById('childProfileEditForm');
    form.reset();
    form.querySelector('[name="child_id"]').value = c.child_id;
    form.querySelector('[name="full_name"]').value = c.full_name || '';
    form.querySelector('[name="sex"]').value = c.sex || '';
    form.querySelector('[name="birth_date"]').value = c.birth_date || '';
    form.querySelector('[name="mother_name"]').value = c.mother_name || '';
    form.querySelector('[name="mother_contact"]').value = c.mother_contact || '';
    form.querySelector('[name="address_details"]').value = c.address_details || '';
    form.querySelector('[name="purok_name"]').value = c.purok_name || '';

    const saveBtn = document.getElementById('saveChildEditBtn');
    // Guard against multiple bindings
    if (saveBtn.__handlerRef) {
      saveBtn.removeEventListener('click', saveBtn.__handlerRef);
    }

    const onSave = async () => {
      const payload = {
        child_id: Number(form.querySelector('[name="child_id"]').value),
        full_name: form.querySelector('[name="full_name"]').value.trim(),
        sex: form.querySelector('[name="sex"]').value,
        birth_date: form.querySelector('[name="birth_date"]').value,
        mother_name: form.querySelector('[name="mother_name"]').value.trim(),
        mother_contact: form.querySelector('[name="mother_contact"]').value.trim(),
        address_details: form.querySelector('[name="address_details"]').value.trim(),
        purok_name: form.querySelector('[name="purok_name"]').value.trim()
      };

      // Simple validation
      const missing = [];
      if (!payload.full_name) missing.push('Child: Full Name');
      if (!payload.sex) missing.push('Child: Sex');
      if (!payload.birth_date) missing.push('Child: Birth Date');
      if (missing.length) {
        alert('Please fill in: \n• ' + missing.join('\n• '));
        return;
      }

      // Busy state
      saveBtn.disabled = true;
      saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving...';

      try {
        const up = await fetchJSON(`${api.children}?action=update`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        });
        if (!up.success) throw new Error(up.error || 'Update failed');

        // Success UX
        alert('✅ Profile updated successfully.');

        // Close modal
        const modal = bootstrap.Modal.getInstance(document.getElementById('childProfileEditModal'));
        modal?.hide();

        // Refresh UI panels consistently
        try {
          // a) If details panel is visible, refresh it
          await loadProfileDetails(payload.child_id);
        } catch (e) {}

        // b) If Children Management module is open, re-render list
        if (document.getElementById('children-tab-content')) {
          renderChildrenModule('Children Management');
        }
      } catch (err) {
        console.error(err);
        alert('❌ Error: ' + (err.message || err));
      } finally {
        // Reset button safely (modal might be closed already)
        const btn = document.getElementById('saveChildEditBtn');
        if (btn) {
          btn.disabled = false;
          btn.innerHTML = '<i class="bi bi-save me-1"></i> Save Changes';
        }
      }
    };

    saveBtn.addEventListener('click', onSave);
    saveBtn.__handlerRef = onSave;

    const modal = new bootstrap.Modal(document.getElementById('childProfileEditModal'));
    modal.show();
  } catch (e) {
    alert('Error loading child: ' + (e.message || e));
  }
}

function exportChildrenData() {
  console.log('Export children data');
  // Implement export functionality
}

let __WEIGH_SELECTED_CHILD_ID = null;
let __WEIGH_ALL_CHILDREN = [];

// Small styles for the left list to mimic the BHW side registry
(function ensureWeighingStyles(){
  const css = `
    .child-list-wrap{display:flex;flex-direction:column;gap:.25rem;max-height:calc(100vh - 320px);overflow:auto;}
    .child-list-item{
      border:1px solid var(--border-soft);
      background:#fff;
      border-radius:10px;
      padding:.55rem .7rem;
      cursor:pointer;
      display:flex;align-items:center;gap:.55rem;
      transition:background .15s,border-color .15s;
    }
    .child-list-item:hover{background:#f6faf7;border-color:#dfe8e3;}
    .child-list-item.active{background:#e8f5ea;border-color:#bfe3cd;}
    .child-list-item .avatar{
      width:30px;height:30px;border-radius:9px;
      background:#e8f5ea;display:flex;align-items:center;justify-content:center;
      font-size:.8rem;color:#0b7a43;flex:0 0 30px;
    }
    .child-list-item .meta{display:flex;flex-direction:column;min-width:0;}
    .child-list-item .meta .name{font-weight:700;color:#1e3e27;font-size:.72rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
    .child-list-item .meta .sub{font-size:.6rem;color:#6a7a6d;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
    .pane-note{font-size:.62rem;color:#6a7a6d;}
  `;
  if (!document.getElementById('weighingSplitCSS')) {
    const style = document.createElement('style');
    style.id = 'weighingSplitCSS';
    style.textContent = css;
    document.head.appendChild(style);
  }
})();

function renderWeighingModuleSplit(label){
  titleEl.textContent = label || 'Nutrition Data Entry';
  showLoading('Nutrition Data Entry');

  // Build the split-view shell
  moduleContent.innerHTML = `
    <div class="fade-in">
      <div class="page-header">
        <div class="page-header-icon"><i class="bi bi-clipboard2-data"></i></div>
        <div class="page-header-text">
          <h1>Nutrition Data Entry</h1>
          <p>Record comprehensive nutrition and growth measurements</p>
        </div>
      </div>

      <div class="row g-3">
        <!-- Left Pane: Children list -->
        <div class="col-12 col-lg-4">
          <div class="tile" style="padding:1rem;">
            <div class="tile-header mb-2">
              <h5 style="font-size:.72rem;font-weight:800;color:#18432b;margin:0;">CHILDREN REGISTRY</h5>
            </div>
            <div class="position-relative mb-2">
              <i class="bi bi-search position-absolute" style="left:.8rem;top:50%;transform:translateY(-50%);font-size:.8rem;color:#7b8c7f;"></i>
              <input id="weighChildSearch" type="text" class="form-control" placeholder="Search child or mother/caregiver..." style="padding-left:2.2rem;font-size:.72rem;">
            </div>
            <div class="pane-note mb-2">Select a child to view details and add a weighing record</div>
            <div id="weighChildList" class="child-list-wrap">
              <div class="text-center py-3" style="color:var(--muted);font-size:.65rem;">
                <span class="spinner-border spinner-border-sm me-2"></span>Loading children...
              </div>
            </div>
          </div>
        </div>

        <!-- Right Pane: Details + New record + History -->
        <div class="col-12 col-lg-8">
          <div id="weighRightPane">
            <div class="tile">
              <div class="text-center py-5" style="color:var(--muted);font-size:.7rem;">
                <i class="bi bi-person-vcard" style="font-size:2.4rem;opacity:.35;"></i>
                <div class="mt-2">Select a child on the left to view details</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  `;

  // Load children list then render items
  fetchJSON(api.children + '?action=list')
    .then(res=>{
      const children = res.children || [];
      __WEIGH_ALL_CHILDREN = children;
      renderWeighingChildrenList(children);
      // Auto-select first child for faster flow
      if (children.length) selectWeighChild(children[0].child_id);
    })
    .catch(err=>{
      console.error('Children load error', err);
      document.getElementById('weighChildList').innerHTML = `
        <div class="text-center py-3" style="color:#dc3545;font-size:.65rem;">
          <i class="bi bi-exclamation-triangle" style="font-size:1.2rem;"></i>
          <div class="mt-1">Failed to load children</div>
        </div>
      `;
    });

  // Wire search
  document.getElementById('weighChildSearch').addEventListener('input', ()=>{
    const q = document.getElementById('weighChildSearch').value.toLowerCase();
    const filtered = __WEIGH_ALL_CHILDREN.filter(c=>{
      return (c.full_name||'').toLowerCase().includes(q)
          || (c.mother_name||'').toLowerCase().includes(q)
          || (c.purok_name||'').toLowerCase().includes(q);
    });
    renderWeighingChildrenList(filtered);
  });
}

function renderWeighingChildrenList(list){
  const host = document.getElementById('weighChildList');
  if (!list.length) {
    host.innerHTML = `
      <div class="text-center py-4" style="color:var(--muted);font-size:.65rem;">
        <i class="bi bi-people" style="font-size:2rem;opacity:.35;"></i>
        <div class="mt-2">No children found</div>
      </div>
    `;
    return;
  }

  host.innerHTML = list.map(c=>{
    const sexIcon = c.sex==='male' ? 'bi-gender-male' : 'bi-gender-female';
    const sexColor = c.sex==='male' ? '#1c79d0' : '#e91e63';
    return `
      <div class="child-list-item ${c.child_id===__WEIGH_SELECTED_CHILD_ID?'active':''}" data-id="${c.child_id}">
        <div class="avatar"><i class="bi ${sexIcon}" style="color:${sexColor}"></i></div>
        <div class="meta">
          <div class="name">${escapeHtml(c.full_name)}</div>
          <div class="sub">${escapeHtml(c.mother_name||'')}</div>
        </div>
      </div>
    `;
  }).join('');

  host.querySelectorAll('.child-list-item').forEach(el=>{
    el.addEventListener('click', ()=> {
      const id = parseInt(el.getAttribute('data-id'),10);
      selectWeighChild(id);
    });
  });
}

async function selectWeighChild(childId){
  __WEIGH_SELECTED_CHILD_ID = childId;

  // Highlight selection in list
  document.querySelectorAll('.child-list-item').forEach(x=>{
    x.classList.toggle('active', parseInt(x.getAttribute('data-id'),10)===childId);
  });

  // Load details for right pane
  await loadWeighRightPane(childId);
}

async function loadWeighRightPane(childId){
  const pane = document.getElementById('weighRightPane');
  pane.innerHTML = `
    <div class="tile">
      <div class="text-center py-3" style="color:var(--muted);font-size:.65rem;">
        <span class="spinner-border spinner-border-sm me-2"></span>Loading child profile...
      </div>
    </div>
  `;

  try{
    const res = await fetchJSON(`${api.children}?action=get&child_id=${childId}`);
    if(!res.success || !res.child) throw new Error(res.error || 'Not found');
    const c = res.child;

    // Details card
    const details = `
      <div class="tile" style="margin-bottom:1rem;">
        <div class="d-flex align-items-start justify-content-between">
          <div>
            <div class="tile-header">
              <h5 style="font-size:.78rem;font-weight:800;color:#18432b;margin:0;display:flex;gap:.4rem;align-items:center;">
                <i class="bi bi-person-badge text-success"></i> ${escapeHtml(c.full_name)}
              </h5>
            </div>
            <div class="pane-note">Purok: <strong>${escapeHtml(c.purok_name||'Not Set')}</strong></div>
          </div>
          <span class="badge-status ${c.sex==='male'?'badge-OW':''}" style="font-size:.62rem;">${escapeHtml(c.sex||'')}</span>
        </div>
        <div class="row g-2 mt-2" style="font-size:.7rem;">
          <div class="col-md-6"><strong>Birth Date:</strong> ${escapeHtml(c.birth_date||'—')}</div>
          <div class="col-md-6"><strong>Mother/Caregiver:</strong> ${escapeHtml(c.mother_name||'—')}</div>
          <div class="col-md-6"><strong>Contact:</strong> ${escapeHtml(c.mother_contact||'—')}</div>
          <div class="col-md-6"><strong>Address:</strong> ${escapeHtml(c.address_details||'—')}</div>
        </div>
      </div>
    `;

    // New Weighing form (auto-calc reuses your helper)
    const today = new Date().toLocaleDateString('en-CA', {timeZone:'Asia/Manila'});
    const form = `
      <div class="form-section">
        <div class="form-section-header">
          <div class="form-section-icon" style="background:#e8f5ea;color:#0b7a43;"><i class="bi bi-clipboard-data"></i></div>
          <h3 class="form-section-title">📊 New Weighing Record</h3>
        </div>
        <p class="pane-note">The record will be saved for: <strong>${escapeHtml(c.full_name)}</strong></p>

        <div class="form-grid">
          <div class="form-group">
        <label class="form-label">Date of Weighing *</label>
        <div class="date-input">
          <!-- UPDATED: min/max bound to today -->
          <input type="date" class="form-control" id="weighingDate" value="${today}" min="${today}" max="${today}" required>
        </div>
      </div>
          <div class="form-group">
            <label class="form-label">Weight (kg) *</label>
            <input type="number" step="0.1" class="form-control" id="childWeight" placeholder="0.0" required>
          </div>
          <div class="form-group">
            <label class="form-label">Height/Length (cm) *</label>
            <input type="number" step="0.1" class="form-control" id="childHeight" placeholder="0.0" required>
          </div>
          <div class="form-group">
            <label class="form-label">WFL/H Assessment</label>
            <input type="text" class="form-control" id="nutritionStatus" placeholder="Auto-calculated when weight and height are entered" readonly>
            <input type="hidden" id="nutritionStatusId" name="wfl_ht_status_id">
          </div>
          <div class="form-group">
            <label class="form-label">Remarks</label>
            <textarea class="form-control" id="remarks" rows="2" placeholder="Additional notes or observations"></textarea>
          </div>
        </div>

        <div class="d-flex justify-content-end mt-3">
          <button class="btn btn-success" id="saveNutritionRecord" style="font-size:.7rem;font-weight:600;padding:.6rem 1.2rem;border-radius:8px;">
            <i class="bi bi-plus-lg me-1"></i> Add Weighing Record
          </button>
        </div>
      </div>
    `;

    // History table placeholder; actual rows loaded by loadPreviousRecords(childId)
    const history = `
      <div class="form-section" style="margin-top:1rem;">
        <div class="form-section-header">
          <div class="form-section-icon" style="background:#e1f1ff;color:#1c79d0;"><i class="bi bi-clipboard-data"></i></div>
          <h3 class="form-section-title">📋 Previous Weighing Records</h3>
        </div>
        <div class="table-responsive" id="previousRecordsContainer">
          <div class="text-center py-3" style="color:var(--muted);font-size:.65rem;">
            <span class="spinner-border spinner-border-sm me-2"></span>Loading previous records...
          </div>
        </div>
      </div>
    `;

    document.getElementById('weighRightPane').innerHTML = details + form + history;
lockWeighingDateToToday(); // NEW: enforce today-only date
setupAutoCalculation();
wireWeighingSave(childId);
loadPreviousRecords(childId);

  }catch(e){
    console.error(e);
    document.getElementById('weighRightPane').innerHTML = `
      <div class="tile">
        <div class="text-center py-4" style="color:#dc3545;font-size:.7rem;">
          <i class="bi bi-exclamation-triangle" style="font-size:2rem;opacity:.5;"></i>
          <div class="mt-1">Error loading child profile</div>
        </div>
      </div>
    `;
  }
}

function wireWeighingSave(childId){
  const btn = document.getElementById('saveNutritionRecord');
  if (!btn) return;

  if (btn.__handlerRef) {
    btn.removeEventListener('click', btn.__handlerRef);
  }

  const onSave = async ()=>{
    // Pull values
    const weighingDate = document.getElementById('weighingDate')?.value;
    const weight       = document.getElementById('childWeight')?.value;
    const height       = document.getElementById('childHeight')?.value;
    const statusId     = document.getElementById('nutritionStatusId')?.value || '';
    const remarks      = document.getElementById('remarks')?.value || '';

    // Frontend validation for weighing date
    const today = new Date().toLocaleDateString('en-CA', { timeZone: 'Asia/Manila' });
    if (weighingDate !== today) {
      alert('Date of Weighing must be today.');
      return;
    }

    // Validate
    const missing = [];
    if (!weighingDate) missing.push('Date of Weighing');
    if (!weight)       missing.push('Weight (kg)');
    if (!height)       missing.push('Height/Length (cm)');
    if (missing.length){
      alert('Please fill in: ' + missing.join(', '));
      return;
    }

    // Busy
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving...';

    try{
      // API expects form fields with CSRF (you already have window.__BNS_CSRF)
      const fd = new FormData();
      fd.append('csrf_token', window.__BNS_CSRF);
      fd.append('child_id', childId);
      fd.append('weighing_date', weighingDate);
      fd.append('weight_kg', weight);
      fd.append('length_height_cm', height);
      if (statusId) fd.append('wfl_ht_status_id', statusId);
      fd.append('remarks', remarks);

      const res = await fetch(api.nutrition, {
        method: 'POST',
        headers: { 'X-CSRF-Token': window.__BNS_CSRF },
        body: fd
      }).then(r=>r.json());

      if (!res.success) throw new Error(res.error || 'Save failed');

      // Success
      alert('✅ Weighing record saved.');
      // Clear weight/height/remarks; keep date
      document.getElementById('childWeight').value = '';
      document.getElementById('childHeight').value = '';
      document.getElementById('remarks').value = '';
      const ns = document.getElementById('nutritionStatus');
      const nsid = document.getElementById('nutritionStatusId');
      if (ns) {
        ns.value = 'Auto-calculated when weight and height are entered';
        ns.style.cssText = `
          font-size:.72rem;padding:.65rem .85rem;border:1px solid var(--border-soft);
          border-radius:8px;background:#f8f9fa;color:var(--muted);font-style:italic;font-weight:normal;
        `;
      }
      if (nsid) nsid.value = '';

      // Reload history for this child
      loadPreviousRecords(childId);

    }catch(err){
      console.error(err);
      alert('❌ Error saving record: ' + (err.message || err));
    }finally{
      btn.disabled = false;
      btn.innerHTML = '<i class="bi bi-plus-lg me-1"></i> Add Weighing Record';
    }
  };

  btn.addEventListener('click', onSave);
  btn.__handlerRef = onSave;
}

// Initialize nutrition data entry functionality
function initializeNutritionDataEntry() {
  // Load children for selection
  loadChildrenForSelection();
  
  // Set today's date as default
  const today = new Date().toLocaleDateString('en-CA'); // YYYY-MM-DD format
  document.getElementById('weighingDate').value = today;
  
  // Setup child selection handler
  setupChildSelectionHandler();
  
  // Setup save record handler
  setupSaveRecordHandler();
}

// Load children for the dropdown
// Load children for the dropdown - Updated to show only child names
function loadChildrenForSelection() {
  fetchJSON(api.children + '?action=list')
    .then(response => {
      if (response.success) {
        const childSelect = document.getElementById('childSelect');
        const children = response.children || [];
        
        childSelect.innerHTML = '<option value="">Select a child</option>';
        children.forEach(child => {
          const option = document.createElement('option');
          option.value = child.child_id;
          // Show only child name, not mother's name
          option.textContent = child.full_name;
          option.dataset.childData = JSON.stringify(child);
          childSelect.appendChild(option);
        });
      }
    })
    .catch(error => {
      console.error('Error loading children:', error);
    });
}

// Calculate age in months from birth date
// Fixed age calculation function
function calculateAgeInMonths(birthDate) {
  if (!birthDate) return 0;
  
  // Use Philippine timezone for accurate calculation
  const today = new Date();
  const birth = new Date(birthDate);
  
  // Calculate the difference in months
  let months = (today.getFullYear() - birth.getFullYear()) * 12;
  months -= birth.getMonth();
  months += today.getMonth();
  
  // Adjust if the day hasn't occurred yet this month
  if (today.getDate() < birth.getDate()) {
    months--;
  }
  
  return Math.max(0, months);
}

// Updated populate child information function with better error handling
function populateChildInfo(childData) {
  console.log('Child data received:', childData); // Debug log
  
  document.getElementById('childFullName').value = childData.full_name || '';
  document.getElementById('childSex').value = childData.sex || '';
  
  // Format birth date for display
  const birthDate = childData.birth_date;
  if (birthDate) {
    // Convert to proper date format for display
    const formattedDate = new Date(birthDate).toLocaleDateString('en-PH');
    document.getElementById('childBirthDate').value = formattedDate;
    
    // Calculate and display current age in months
    const ageInMonths = calculateAgeInMonths(birthDate);
    document.getElementById('childAge').value = ageInMonths.toString();
  } else {
    document.getElementById('childBirthDate').value = '';
    document.getElementById('childAge').value = '';
  }
  
  document.getElementById('motherName').value = childData.mother_name || '';
}

// Updated child selection handler with better data handling
function setupChildSelectionHandler() {
  const childSelect = document.getElementById('childSelect');
  
  childSelect.addEventListener('change', function() {
    const selectedValue = this.value;
    
    if (selectedValue) {
      const selectedOption = this.options[this.selectedIndex];
      
      try {
        const childData = JSON.parse(selectedOption.dataset.childData);
        console.log('Selected child data:', childData); // Debug log
        
        populateChildInfo(childData);
        loadPreviousRecords(childData.child_id);
      } catch (error) {
        console.error('Error parsing child data:', error);
        clearChildInfo();
        clearPreviousRecords();
      }
    } else {
      clearChildInfo();
      clearPreviousRecords();
    }
  });
}

function renderProfileManagementShell() {
  return `
    <div class="row g-3 fade-in">
      <!-- Left: list only (no search) -->
      <div class="col-12 col-lg-6">
        <div class="tile" id="pmListTile" style="padding:0;overflow:hidden;">
          <div class="table-responsive" id="pmListContainer">
            <div class="text-center py-3" style="color:var(--muted);font-size:.65rem;">
              <div class="spinner-border spinner-border-sm me-2" role="status" style="width:1rem;height:1rem;border-width:2px;"></div>
              Loading list...
            </div>
          </div>
        </div>
      </div>

      <!-- Right: profile details -->
      <div class="col-12 col-lg-6">
        <div class="tile" id="pmDetailsTile">
          <div class="tile-header">
            <h5 style="font-size:.72rem;font-weight:800;color:#18432b;margin:0;">PROFILE DETAILS</h5>
          </div>
          <div class="text-center py-5" id="pmDetailsPlaceholder" style="color:var(--muted);font-size:.7rem;">
            <i class="bi bi-person-vcard" style="font-size:2.4rem;opacity:.35;"></i>
            <div class="mt-2">Select a child to view profile</div>
          </div>
          <div id="pmDetailsContent" style="display:none;"></div>
        </div>
      </div>
    </div>
  `;
}

function populateProfileList(children) {
  const el = document.getElementById('pmListContainer');
  if (!children.length) {
    el.innerHTML = `
      <div class="text-center py-4" style="color:var(--muted);font-size:.65rem;">
        <i class="bi bi-people" style="font-size:2rem;opacity:.35;"></i>
        <div class="mt-2">No children found</div>
      </div>`;
    return;
  }

  el.innerHTML = `
    <table class="table table-hover mb-0" style="font-size:.7rem;">
      <thead style="background:#f8faf9;border-bottom:1px solid var(--border-soft);">
        <tr>
          <th style="padding:.7rem .8rem;border:none;font-size:.65rem;color:#344f3a;">Child</th>
          <th style="padding:.7rem .8rem;border:none;font-size:.65rem;color:#344f3a;">Sex</th>
          <th style="padding:.7rem .8rem;border:none;font-size:.65rem;color:#344f3a;">Age (mo)</th>
          <th style="padding:.7rem .8rem;border:none;font-size:.65rem;color:#344f3a;">Purok</th>
          <th style="padding:.7rem .8rem;border:none;font-size:.65rem;color:#344f3a;width:120px;">Actions</th>
        </tr>
      </thead>
      <tbody>
        ${children.map(c => {
          const sexIcon = c.sex === 'male' ? 'bi-gender-male' : 'bi-gender-female';
          const sexColor = c.sex === 'male' ? '#1c79d0' : '#e91e63';
          return `
            <tr style="border-bottom:1px solid #f0f4f1;">
              <td style="padding:.75rem .8rem;border:none;">
                <div class="d-flex align-items-center gap-2">
                  <div style="width:22px;height:22px;background:#e8f5ea;border-radius:50%;display:flex;align-items:center;justify-content:center;">
                    <i class="bi ${sexIcon}" style="font-size:.65rem;color:${sexColor};"></i>
                  </div>
                  <div class="d-flex flex-column">
                    <button type="button" class="btn p-0 text-start pm-child-link" data-id="${c.child_id}" 
                            style="line-height:1.1;background:none;border:none;color:#1e3e27;font-weight:700;font-size:.72rem;">
                      ${escapeHtml(c.full_name)}
                    </button>
                    <small style="color:#6a7a6d;">${escapeHtml(c.mother_name || '')}</small>
                  </div>
                </div>
              </td>
              <td style="padding:.75rem .8rem;border:none;color:#586c5d;">${escapeHtml(c.sex)}</td>
              <td style="padding:.75rem .8rem;border:none;color:#586c5d;">${c.current_age_months ?? '—'}</td>
              <td style="padding:.75rem .8rem;border:none;color:#586c5d;">${escapeHtml(c.purok_name || 'Not Set')}</td>
              <td style="padding:.6rem .8rem;border:none;">
                <div class="d-flex align-items-center gap-2">
                  <button class="btn btn-sm btn-outline-success" style="padding:.3rem .6rem;border-radius:8px;font-size:.6rem;"
                          data-action="pm-edit" data-id="${c.child_id}">
                    <i class="bi bi-pencil me-1"></i> Edit
                  </button>
                </div>
              </td>
            </tr>
          `;
        }).join('')}
      </tbody>
    </table>
  `;

  // Name click -> load details on the right panel
  el.querySelectorAll('.pm-child-link').forEach(btn => {
    btn.addEventListener('click', async () => {
      const id = parseInt(btn.dataset.id, 10);
      await loadProfileDetails(id);
      // optional: scroll into view for smaller screens
      document.getElementById('pmDetailsTile')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
  });

  // Keep only Edit in Actions
  el.querySelectorAll('button[data-action="pm-edit"]').forEach(btn => {
    btn.addEventListener('click', async () => {
      const id = parseInt(btn.dataset.id, 10);
      editChild(id);
    });
  });
}

// ADD: Load details to the right panel (read-only)
async function loadProfileDetails(childId) {
  const ph = document.getElementById('pmDetailsPlaceholder');
  const box = document.getElementById('pmDetailsContent');
  ph.style.display = 'block';
  box.style.display = 'none';
  ph.innerHTML = `
    <div class="py-3" style="color:var(--muted);font-size:.65rem;">
      <span class="spinner-border spinner-border-sm me-2"></span>Loading profile...
    </div>
  `;

  try {
    const res = await fetchJSON(`${api.children}?action=get&child_id=${childId}`);
    if (!res.success || !res.child) throw new Error(res.error || 'Failed to load');
    const c = res.child;

    const field = (label, val) => `
      <div class="d-flex justify-content-between align-items-center" style="padding:.45rem .65rem;border:1px solid #e9efeb;border-radius:8px;background:#fbfdfb;margin-bottom:.4rem;">
        <span style="font-size:.62rem;color:#5f7464;font-weight:700;">${label}</span>
        <span style="font-size:.7rem;color:#1e3e27;font-weight:700;">${escapeHtml(val ?? '—')}</span>
      </div>
    `;

    box.innerHTML = `
      <div class="tile-sub" style="margin-bottom:.5rem;">Child Information</div>
      ${field('Full Name', c.full_name)}
      ${field('Sex', c.sex)}
      ${field('Birth Date', c.birth_date)}
      ${field('Purok', c.purok_name || 'Not Set')}

      <div class="tile-sub" style="margin:.8rem 0 .5rem;">Mother/Caregiver</div>
      ${field('Full Name', c.mother_name)}
      ${field('Contact', c.mother_contact || '—')}
      ${field('Address', c.address_details || '—')}
    `;

    ph.style.display = 'none';
    box.style.display = 'block';
  } catch (e) {
    console.error(e);
    ph.style.display = 'block';
    box.style.display = 'none';
    ph.innerHTML = `
      <div class="py-3" style="color:#dc3545;font-size:.65rem;">
        <i class="bi bi-exclamation-triangle me-1"></i>Error loading profile
      </div>`;
  }
}

// Updated load children function to ensure data is properly stored
function loadChildrenForSelection() {
  fetchJSON(api.children + '?action=list')
    .then(response => {
      console.log('Children API response:', response); // Debug log
      
      if (response.success) {
        const childSelect = document.getElementById('childSelect');
        const children = response.children || [];
        
        console.log('Children data:', children); // Debug log
        
        childSelect.innerHTML = '<option value="">Select a child</option>';
        children.forEach(child => {
          const option = document.createElement('option');
          option.value = child.child_id;
          option.textContent = child.full_name;
          
          // Ensure all necessary data is included
          const childDataForStorage = {
            child_id: child.child_id,
            full_name: child.full_name,
            sex: child.sex,
            birth_date: child.birth_date,
            mother_name: child.mother_name,
            current_age_months: child.current_age_months
          };
          
          option.dataset.childData = JSON.stringify(childDataForStorage);
          childSelect.appendChild(option);
        });
      } else {
        console.error('Failed to load children:', response.error);
      }
    })
    .catch(error => {
      console.error('Error loading children:', error);
    });
}

// Updated form section with consistent styling
function renderChildInformationSection() {
  return `
    <!-- Child Information Section -->
    <div class="form-section">
      <div class="form-section-header">
        <div class="form-section-icon child">
          <i class="bi bi-person-plus"></i>
        </div>
        <h3 class="form-section-title">Child Information</h3>
      </div>
      <div class="form-grid">
        <div class="form-group">
          <label class="form-label">Select Child</label>
          <select class="form-select" id="childSelect" required>
            <option value="">Select a child</option>
            <!-- Children options will be populated dynamically -->
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Full Name</label>
          <input type="text" class="form-control" id="childFullName" placeholder="Child's complete name" readonly>
        </div>
        <div class="form-group">
          <label class="form-label">Sex</label>
          <input type="text" class="form-control" id="childSex" readonly>
        </div>
        <div class="form-group">
          <label class="form-label">Birth Date</label>
          <input type="text" class="form-control" id="childBirthDate" readonly>
        </div>
        <div class="form-group">
          <label class="form-label">Current Age (months)</label>
          <input type="text" class="form-control" id="childAge" readonly placeholder="Age will be calculated automatically">
        </div>
        <div class="form-group">
          <label class="form-label">Mother/Caregiver</label>
          <input type="text" class="form-control" id="motherName" readonly>
        </div>
      </div>
    </div>
  `;
}

// Alternative approach: Use the age from the API response directly
function populateChildInfoFromAPI(childData) {
  document.getElementById('childFullName').value = childData.full_name || '';
  document.getElementById('childSex').value = childData.sex || '';
  
  // Format birth date for display
  if (childData.birth_date) {
    const formattedDate = new Date(childData.birth_date).toLocaleDateString('en-PH');
    document.getElementById('childBirthDate').value = formattedDate;
  } else {
    document.getElementById('childBirthDate').value = '';
  }
  
  // Use the age from API if available, otherwise calculate it
  let ageInMonths = '';
  if (childData.current_age_months !== undefined && childData.current_age_months !== null) {
    ageInMonths = childData.current_age_months.toString();
  } else if (childData.birth_date) {
    ageInMonths = calculateAgeInMonths(childData.birth_date).toString();
  }
  
  document.getElementById('childAge').value = ageInMonths;
  document.getElementById('motherName').value = childData.mother_name || '';
}

// Updated populate child information function
function populateChildInfo(childData) {
  document.getElementById('childFullName').value = childData.full_name || '';
  document.getElementById('childSex').value = childData.sex || '';
  document.getElementById('childBirthDate').value = childData.birth_date || '';
  
  // Calculate and display current age in months
  const ageInMonths = calculateAgeInMonths(childData.birth_date);
  document.getElementById('childAge').value = ageInMonths ? `${ageInMonths} months` : '';
  
  document.getElementById('motherName').value = childData.mother_name || '';
}

// Updated renderWeighingModule function with consistent UI
function renderWeighingModule(label) {
  showLoading(label);
  setTimeout(() => {
    moduleContent.innerHTML = `
      <div class="fade-in">
        <!-- Page Header -->
        <div class="page-header">
          <div class="page-header-icon">
            <i class="bi bi-clipboard2-data"></i>
          </div>
          <div class="page-header-text">
            <h1>Nutrition Data Entry</h1>
            <p>Record comprehensive nutrition and growth measurements</p>
          </div>
        </div>

        <!-- Form Container -->
        <div class="form-container">
          <!-- Child Information Section -->
          <div class="form-section">
            <div class="form-section-header">
              <div class="form-section-icon child">
                <i class="bi bi-person-plus"></i>
              </div>
              <h3 class="form-section-title">Child Information</h3>
            </div>
            <div class="form-grid">
              <div class="form-group">
                <label class="form-label">Select Child</label>
                <select class="form-select" id="childSelect" required style="font-size:.72rem;padding:.65rem .85rem;border:1px solid var(--border-soft);border-radius:8px;background:var(--surface);">
                  <option value="">Select a child</option>
                  <!-- Children options will be populated dynamically -->
                </select>
              </div>
              <div class="form-group">
                <label class="form-label">Full Name</label>
                <input type="text" class="form-control" id="childFullName" placeholder="Child's complete name" readonly style="font-size:.72rem;padding:.65rem .85rem;border:1px solid var(--border-soft);border-radius:8px;background:#f8f9fa;">
              </div>
              <div class="form-group">
                <label class="form-label">Sex</label>
                <input type="text" class="form-control" id="childSex" readonly style="font-size:.72rem;padding:.65rem .85rem;border:1px solid var(--border-soft);border-radius:8px;background:#f8f9fa;">
              </div>
              <div class="form-group">
                <label class="form-label">Birth Date</label>
                <input type="text" class="form-control" id="childBirthDate" readonly style="font-size:.72rem;padding:.65rem .85rem;border:1px solid var(--border-soft);border-radius:8px;background:#f8f9fa;">
              </div>
              <div class="form-group">
                <label class="form-label">Current Age (months)</label>
                <input type="text" class="form-control" id="childAge" readonly style="font-size:.72rem;padding:.65rem .85rem;border:1px solid var(--border-soft);border-radius:8px;background:#f8f9fa;">
              </div>
              <div class="form-group">
                <label class="form-label">Mother/Caregiver</label>
                <input type="text" class="form-control" id="motherName" readonly style="font-size:.72rem;padding:.65rem .85rem;border:1px solid var(--border-soft);border-radius:8px;background:#f8f9fa;">
              </div>
            </div>
          </div>

          <!-- Weighing Sessions Section -->
          <div class="form-section">
            <div class="form-section-header">
              <div class="form-section-icon" style="background:#e8f5ea;color:#0b7a43;">
                <i class="bi bi-clipboard-data"></i>
              </div>
              <h3 class="form-section-title">📊 New Weighing Record</h3>
            </div>
            <p style="font-size:.65rem;color:var(--muted);margin:0 0 1rem;font-weight:500;">Record new measurement data for the selected child</p>
            
            <!-- Weighing Form -->
            <div class="form-grid">
              <div class="form-group">
                <label class="form-label">Date of Weighing *</label>
                <div class="date-input">
                  <input type="date" class="form-control" id="weighingDate" required style="font-size:.72rem;padding:.65rem .85rem;border:1px solid var(--border-soft);border-radius:8px;">
                </div>
              </div>
              <div class="form-group">
                <label class="form-label">Weight (kg) *</label>
                <input type="number" step="0.1" class="form-control" id="childWeight" placeholder="Enter weight in kg" required style="font-size:.72rem;padding:.65rem .85rem;border:1px solid var(--border-soft);border-radius:8px;">
              </div>
              <div class="form-group">
                <label class="form-label">Height/Length (cm) *</label>
                <input type="number" step="0.1" class="form-control" id="childHeight" placeholder="Enter height in cm" required style="font-size:.72rem;padding:.65rem .85rem;border:1px solid var(--border-soft);border-radius:8px;">
              </div>
              <div class="form-group">
                <label class="form-label">WFL/H Assessment</label>
                <select class="form-select" id="nutritionStatus" style="font-size:.72rem;padding:.65rem .85rem;border:1px solid var(--border-soft);border-radius:8px;">
                  <option value="">Auto-calculate based on measurements</option>
                  <option value="1">Normal (NOR)</option>
                  <option value="2">Moderate Acute Malnutrition (MAM)</option>
                  <option value="3">Severe Acute Malnutrition (SAM)</option>
                  <option value="4">Overweight (OW)</option>
                  <option value="5">Obese (OB)</option>
                  <option value="6">Stunted (ST)</option>
                  <option value="7">Underweight (UW)</option>
                </select>
              </div>
              <div class="form-group">
                <label class="form-label">Remarks</label>
                <textarea class="form-control" id="remarks" placeholder="Additional notes or observations" rows="3" style="font-size:.72rem;padding:.65rem .85rem;border:1px solid var(--border-soft);border-radius:8px;"></textarea>
              </div>
            </div>

            <!-- Add Record Button -->
            <div class="d-flex justify-content-end mt-3">
              <button class="btn btn-success" id="saveNutritionRecord" style="font-size:.7rem;font-weight:600;padding:.6rem 1.2rem;border-radius:8px;box-shadow:0 2px 6px -2px rgba(20,104,60,.5);">
                <i class="bi bi-plus-lg me-1"></i> Add Weighing Record
              </button>
            </div>
          </div>

          <!-- Previous Records Section -->
          <div class="form-section">
            <div class="form-section-header">
              <div class="form-section-icon" style="background:#e1f1ff;color:#1c79d0;">
                <i class="bi bi-clipboard-data"></i>
              </div>
              <h3 class="form-section-title">📋 Previous Weighing Records</h3>
            </div>
            <p style="font-size:.65rem;color:var(--muted);margin:0 0 1rem;font-weight:500;">Historical measurement data for the selected child</p>
            
            <!-- Previous Records Table -->
            <div class="table-responsive" id="previousRecordsContainer">
              <div class="text-center py-4" style="color:var(--muted);font-size:.65rem;">
                <i class="bi bi-person-check" style="font-size:2rem;opacity:0.3;"></i>
                <p style="margin:.5rem 0 0;">Select a child to view their weighing history</p>
              </div>
            </div>
          </div>

          <!-- Nutrition Classification Guide -->
          <div class="form-section" style="background:#f0f8f1;border:1px solid #d3e8d9;">
            <div class="form-section-header">
              <div class="form-section-icon" style="background:#e8f5ea;color:#0b7a43;">
                <i class="bi bi-info-circle"></i>
              </div>
              <h3 class="form-section-title">Nutrition Classification Guide</h3>
            </div>
            
            <div style="display: flex; flex-direction: column; gap: 0.4rem;">
              <div style="display: flex; align-items: center; gap: 0.7rem;">
                <span class="badge-status badge-NOR" style="min-width:70px;text-align:center;">Normal</span>
                <span style="font-size:.65rem;color:#15692d;font-weight:600;">Healthy weight for age and height</span>
              </div>
              <div style="display: flex; align-items: center; gap: 0.7rem;">
                <span class="badge-status badge-MAM" style="min-width:70px;text-align:center;">MAM</span>
                <span style="font-size:.65rem;color:#845900;font-weight:600;">Moderate Acute Malnutrition - requires monitoring</span>
              </div>
              <div style="display: flex; align-items: center; gap: 0.7rem;">
                <span class="badge-status badge-SAM" style="min-width:70px;text-align:center;">SAM</span>
                <span style="font-size:.65rem;color:#b02020;font-weight:600;">Severe Acute Malnutrition - requires urgent intervention</span>
              </div>
              <div style="display: flex; align-items: center; gap: 0.7rem;">
                <span class="badge-status badge-UW" style="min-width:70px;text-align:center;">Underweight</span>
                <span style="font-size:.65rem;color:#7c5100;font-weight:600;">Below normal weight for age</span>
              </div>
              <div style="display: flex; align-items: center; gap: 0.7rem;">
                <span class="badge-status" style="background:#ff6b6b;color:#fff;min-width:70px;text-align:center;">Stunted</span>
                <span style="font-size:.65rem;color:#b02020;font-weight:600;">Below normal height for age</span>
              </div>
              <div style="display: flex; align-items: center; gap: 0.7rem;">
                <span class="badge-status badge-OW" style="background:#a259c6;color:#fff;min-width:70px;text-align:center;">Overweight/Obese</span>
                <span style="font-size:.65rem;color:#105694;font-weight:600;">Above normal weight for height</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    `;

    // Initialize the module functionality
    initializeNutritionDataEntry();
  }, 100);
}

// Load previous records for selected child - Updated to show real data
function loadPreviousRecords(childId) {
  const container = document.getElementById('previousRecordsContainer');
  container.innerHTML = `
    <div class="text-center py-3" style="color:var(--muted);font-size:.65rem;">
      <div class="spinner-border spinner-border-sm me-2" role="status"></div>
      Loading previous records...
    </div>
  `;
  
  // Fetch actual nutrition records for this child
  fetch(`${api.nutrition}?child_id=${childId}`, {
    headers: {
      'X-CSRF-Token': window.__BNS_CSRF
    }
  })
  .then(response => response.json())
  .then(data => {
    if (data.success && data.records && data.records.length > 0) {
      const records = data.records;
      container.innerHTML = `
        <table class="table table-hover mb-0" style="font-size:.7rem;">
          <thead style="background:#f8faf9;border-bottom:1px solid var(--border-soft);">
            <tr>
              <th style="padding:.75rem .8rem;font-size:.65rem;font-weight:700;color:#344f3a;border:none;">Date</th>
              <th style="padding:.75rem .8rem;font-size:.65rem;font-weight:700;color:#344f3a;border:none;">Age (months)</th>
              <th style="padding:.75rem .8rem;font-size:.65rem;font-weight:700;color:#344f3a;border:none;">Weight (kg)</th>
              <th style="padding:.75rem .8rem;font-size:.65rem;font-weight:700;color:#344f3a;border:none;">Height (cm)</th>
              <th style="padding:.75rem .8rem;font-size:.65rem;font-weight:700;color:#344f3a;border:none;">Status</th>
              <th style="padding:.75rem .8rem;font-size:.65rem;font-weight:700;color:#344f3a;border:none;">Remarks</th>
            </tr>
          </thead>
          <tbody>
            ${records.map(record => `
              <tr style="border-bottom:1px solid #f0f4f1;">
                <td style="padding:.8rem;border:none;">${new Date(record.weighing_date).toLocaleDateString('en-PH')}</td>
                <td style="padding:.8rem;border:none;">${record.age_in_months}</td>
                <td style="padding:.8rem;border:none;">${record.weight_kg || 'N/A'}</td>
                <td style="padding:.8rem;border:none;">${record.length_height_cm || 'N/A'}</td>
                <td style="padding:.8rem;border:none;">${record.status_code ? `<span class="badge-status badge-${record.status_code}">${record.status_code}</span>` : 'N/A'}</td>
                <td style="padding:.8rem;border:none;">${record.remarks || '-'}</td>
              </tr>
            `).join('')}
          </tbody>
        </table>
      `;
    } else {
      container.innerHTML = `
        <div class="text-center py-4" style="color:var(--muted);font-size:.65rem;">
          <i class="bi bi-clipboard-x" style="font-size:2rem;opacity:0.3;"></i>
          <p style="margin:.5rem 0 0;">No previous records found for this child</p>
        </div>
      `;
    }
  })
  .catch(error => {
    console.error('Error loading previous records:', error);
    container.innerHTML = `
      <div class="text-center py-4" style="color:var(--red);font-size:.65rem;">
        <i class="bi bi-exclamation-triangle" style="font-size:2rem;opacity:0.5;"></i>
        <p style="margin:.5rem 0 0;">Error loading previous records</p>
      </div>
    `;
  });
}

// Clear child information fields
function clearChildInfo() {
  document.getElementById('childFullName').value = '';
  document.getElementById('childSex').value = '';
  document.getElementById('childBirthDate').value = '';
  document.getElementById('childAge').value = '';
  document.getElementById('motherName').value = '';
}

// Clear previous records display
// Clear previous records display - consistent with your UI
function clearPreviousRecords() {
  const container = document.getElementById('previousRecordsContainer');
  container.innerHTML = `
    <div class="text-center py-4" style="color:var(--muted);font-size:.65rem;">
      <i class="bi bi-person-check" style="font-size:2rem;opacity:0.3;"></i>
      <p style="margin:.5rem 0 0;">Select a child to view their weighing history</p>
    </div>
  `;
}

// Setup child selection handler
function setupChildSelectionHandler() {
  const childSelect = document.getElementById('childSelect');
  
  childSelect.addEventListener('change', function() {
    const selectedOption = this.options[this.selectedIndex];
    
    if (selectedOption.value) {
      const childData = JSON.parse(selectedOption.dataset.childData);
      populateChildInfo(childData);
      loadPreviousRecords(childData.child_id);
    } else {
      clearChildInfo();
      clearPreviousRecords();
    }
  });
}

// Populate child information fields
function populateChildInfo(childData) {
  document.getElementById('childFullName').value = childData.full_name || '';
  document.getElementById('childSex').value = childData.sex || '';
  document.getElementById('childBirthDate').value = childData.birth_date || '';
  document.getElementById('childAge').value = childData.current_age_months || '';
  document.getElementById('motherName').value = childData.mother_name || '';
}

// Clear child information fields
function clearChildInfo() {
  document.getElementById('childFullName').value = '';
  document.getElementById('childSex').value = '';
  document.getElementById('childBirthDate').value = '';
  document.getElementById('childAge').value = '';
  document.getElementById('motherName').value = '';
}

// Load previous records for selected child
function loadPreviousRecords(childId) {
  // This would fetch nutrition records for the specific child
  const container = document.getElementById('previousRecordsContainer');
  container.innerHTML = `
    <div class="text-center py-3" style="color:var(--muted);font-size:.65rem;">
      <div class="spinner-border spinner-border-sm me-2" role="status"></div>
      Loading previous records...
    </div>
  `;
  
  // Simulate loading previous records - replace with actual API call
  setTimeout(() => {
    container.innerHTML = `
      <table class="table table-hover mb-0" style="font-size:.7rem;">
        <thead style="background:#f8faf9;border-bottom:1px solid var(--border-soft);">
          <tr>
            <th style="padding:.75rem .8rem;font-size:.65rem;font-weight:700;color:#344f3a;border:none;">Date</th>
            <th style="padding:.75rem .8rem;font-size:.65rem;font-weight:700;color:#344f3a;border:none;">Age (months)</th>
            <th style="padding:.75rem .8rem;font-size:.65rem;font-weight:700;color:#344f3a;border:none;">Weight (kg)</th>
            <th style="padding:.75rem .8rem;font-size:.65rem;font-weight:700;color:#344f3a;border:none;">Height (cm)</th>
            <th style="padding:.75rem .8rem;font-size:.65rem;font-weight:700;color:#344f3a;border:none;">Status</th>
            <th style="padding:.75rem .8rem;font-size:.65rem;font-weight:700;color:#344f3a;border:none;">Remarks</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td colspan="6" style="padding:2rem;text-align:center;color:var(--muted);font-size:.65rem;">
              No previous records found for this child
            </td>
          </tr>
        </tbody>
      </table>
    `;
  }, 1000);
}

// Clear previous records display
function clearPreviousRecords() {
  const container = document.getElementById('previousRecordsContainer');
  container.innerHTML = `
    <div class="text-center py-4" style="color:var(--muted);font-size:.65rem;">
      Select a child to view their weighing history
    </div>
  `;
}

// REPLACE the existing setupSaveRecordHandler() with this safe version
function setupSaveRecordHandler() {
  const saveBtn = document.getElementById('saveNutritionRecord');
  if (!saveBtn) return;

  saveBtn.addEventListener('click', function() {
    const childId = document.getElementById('childSelect')?.value;
    const weighingDate = document.getElementById('weighingDate')?.value;
    const weight = document.getElementById('childWeight')?.value;
    const height = document.getElementById('childHeight')?.value;
    const status = document.getElementById('nutritionStatusId')?.value || document.getElementById('nutritionStatus')?.value;
    const remarks = document.getElementById('remarks')?.value || '';

    // Frontend validation for weighing date
    const today = new Date().toLocaleDateString('en-CA', { timeZone: 'Asia/Manila' });
    if (weighingDate !== today) {
      alert('Date of Weighing must be today.');
      return;
    }

    // Validation
    if (!childId) { alert('Please select a child'); return; }
    if (!weighingDate || !weight || !height) {
      alert('Please fill in all required fields (Date, Weight, Height)');
      return;
    }

    // Show loading state
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving...';

    // Prepare data for submission
    const formData = new FormData();
    formData.append('csrf_token', window.__BNS_CSRF);
    formData.append('child_id', childId);
    formData.append('weighing_date', weighingDate);
    formData.append('weight_kg', weight);
    formData.append('length_height_cm', height);
    if (status) formData.append('wfl_ht_status_id', status);
    formData.append('remarks', remarks);

    // Submit
    fetch(api.nutrition, {
      method: 'POST',
      headers: { 'X-CSRF-Token': window.__BNS_CSRF },
      body: formData
    })
    .then(r => r.json())
    .then(data => {
      if (!data.success) throw new Error(data.error || 'Unknown error');

      alert('✅ Nutrition record saved successfully!');

      // Clear form fields
      const weightEl = document.getElementById('childWeight');
      const heightEl = document.getElementById('childHeight');
      const statusEl = document.getElementById('nutritionStatus');
      const statusIdEl = document.getElementById('nutritionStatusId');
      const remarksEl = document.getElementById('remarks');
      if (weightEl) weightEl.value = '';
      if (heightEl) heightEl.value = '';
      if (statusEl) {
        // If it's a readonly text field version
        if (statusEl.tagName === 'INPUT') {
          statusEl.value = 'Auto-calculated when weight and height are entered';
          statusEl.style.cssText = `
            font-size:.72rem;padding:.65rem .85rem;border:1px solid var(--border-soft);
            border-radius:8px;background:#f8f9fa;color:var(--muted);font-style:italic;font-weight:normal;
          `;
        } else {
          statusEl.value = '';
        }
      }
      if (statusIdEl) statusIdEl.value = '';
      if (remarksEl) remarksEl.value = '';

      // Safely refresh visible panels without throwing
      try {
        // If the per-child history panel exists, refresh it
        if (childId && document.getElementById('previousRecordsContainer')) {
          loadPreviousRecords(parseInt(childId, 10));
        }
        // If the "All Weighing Records" table exists, refresh it
        if (document.getElementById('allRecordsContainer')) {
          loadAllWeighingRecords();
        }
      } catch (e) {
        console.warn('Post-save UI refresh skipped:', e);
      }
    })
    .catch(error => {
      console.error('Error saving nutrition record:', error);
      alert('❌ Error saving record: ' + (error.message || error));
    })
    .finally(() => {
      // Re-query in case the DOM was re-rendered during save
      const btn = document.getElementById('saveNutritionRecord');
      if (btn) {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-plus-lg me-1"></i> Add Weighing Record';
      }
    });
  });
}

// REPLACE this whole function
// REPLACE the whole renderNutritionClassificationModule() with this version (Growth Insights instead of charts)
function renderNutritionClassificationModule(label){
  showLoading(label);
 
  Promise.all([
    fetchJSON(api.children + '?action=list').catch(()=>({children:[]})),
    fetchJSON(api.nutrition + '?classification_summary=1').catch(()=>({summary:[]})),
    fetchJSON(api.nutrition + '?recent=1').catch(()=>({records:[]}))
  ]).then(([childRes, classRes, recentRes]) => {
    const children = childRes.children || [];
    const summary = classRes.summary || [];
    const recent = recentRes.records || [];
 
    const counts = Object.fromEntries(summary.map(s => [s.status_code, Number(s.child_count||0)]));
    const normalCount = counts.NOR || 0;
    const belowNormal = (counts.SAM||0) + (counts.MAM||0) + (counts.UW||0);
 
    const {stableCount, improvedCount} = computeStabilityAndImprovement(recent);
    const firstChildId = children[0]?.child_id || null;
 
    moduleContent.innerHTML = `
      <div class="fade-in">
        <div class="page-header">
          <div class="page-header-icon"><i class="bi bi-clipboard2-pulse"></i></div>
          <div class="page-header-text">
            <h1>Growth Monitoring</h1>
            <p>Track child development and nutrition status trends</p>
          </div>
        </div>
 
        <!-- Overview Cards -->
        <div class="stat-grid" style="margin-top:.4rem;">
          ${statCard('Normal Growth', normalCount, '↑ vs last month','green', true)}
          ${statCard('Below Normal', belowNormal, '↓ vs last month','amber', false)}
          ${statCard('Stable Cases', stableCount, 'No change','blue', false)}
          ${statCard('Improved', improvedCount, 'This month','green', false)}
        </div>
 
        <!-- Tabs -->
        <div class="mb-3">
          <ul class="nav nav-tabs" style="border-bottom:2px solid var(--border-soft);">
            <li class="nav-item">
              <a class="nav-link active gm-tab" href="#" data-tab="individual" style="font-size:.75rem;font-weight:600;color:var(--green);border-bottom:2px solid var(--green);background:none;border-left:none;border-right:none;border-top:none;padding:.75rem 1.2rem;">
                Individual Child
              </a>
            </li>
            <li class="nav-item"><a class="nav-link gm-tab" href="#" data-tab="population" style="font-size:.75rem;font-weight:600;color:var(--muted);border:none;background:none;padding:.75rem 1.2rem;">Population Trends</a></li>
            <li class="nav-item"><a class="nav-link gm-tab" href="#" data-tab="wfl" style="font-size:.75rem;font-weight:600;color:var(--muted);border:none;background:none;padding:.75rem 1.2rem;">WFL/H Assessment</a></li>
            <li class="nav-item"><a class="nav-link gm-tab" href="#" data-tab="progress" style="font-size:.75rem;font-weight:600;color:var(--muted);border:none;background:none;padding:.75rem 1.2rem;">Progress Documentation</a></li>
          </ul>
        </div>
 
        <!-- Tab Content -->
        <div id="gm-tab-content">
          ${renderIndividualChildPanel(children, firstChildId)}
        </div>
      </div>
    `;
 
    // Wire up tabs
    document.querySelectorAll('.gm-tab').forEach(tab=>{
      tab.addEventListener('click',e=>{
        e.preventDefault();
        document.querySelectorAll('.gm-tab').forEach(t=>{t.classList.remove('active');t.style.color='var(--muted)';t.style.borderBottom='none';});
        tab.classList.add('active');
        tab.style.color='var(--green)';
        tab.style.borderBottom='2px solid var(--green)';
 
        const type = tab.dataset.tab;
        const container = document.getElementById('gm-tab-content');
        if(type==='individual'){
          container.innerHTML = renderIndividualChildPanel(children, document.getElementById('gmChildSelect')?.value || firstChildId);
          attachIndividualHandlers(children);
          const id = parseInt(document.getElementById('gmChildSelect').value,10);
          if(id) loadAndRenderChildSeries(id);
        } else if (type==='population') {
          container.innerHTML = renderPopulationPanel(summary, recent, children);
        } else if (type==='wfl') {
          container.innerHTML = renderWFLPanel(summary);
          } else {
          container.innerHTML = renderProgressDocsPanel(children, recent);
          attachProgressHandlersProgress();
        }
      });
    });
 
    // Initialize Individual panel
    attachIndividualHandlers(children);
    if(firstChildId) loadAndRenderChildSeries(firstChildId);
 
    // ===== Helpers (scoped to this module) =====
 
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
      if(/Normal Growth/i.test(t)) return 'bi-emoji-smile';
      if(/Below Normal/i.test(t)) return 'bi-activity';
      if(/Stable/i.test(t)) return 'bi-arrow-left-right';
      if(/Improved/i.test(t)) return 'bi-graph-up-arrow';
      return 'bi-circle';
    }
    function computeStabilityAndImprovement(recent){
      const map = new Map();
      recent.forEach(r=>{
        const k = r.child_name;
        if(!map.has(k)) map.set(k, []);
        const arr = map.get(k);
        if(arr.length<2) arr.push(r);
      });
      let stable=0, improved=0;
      map.forEach(arr=>{
        if(arr.length<2) return;
        const [latest, prev] = arr; // latest first
        if(latest.status_code && prev.status_code){
          if(latest.status_code===prev.status_code) stable++;
          const mal = new Set(['SAM','MAM','UW']);
          if(mal.has(prev.status_code) && latest.status_code==='NOR') improved++;
        }
      });
      return {stableCount:stable, improvedCount:improved};
    }
 
    // UI renderers for tabs
    function renderIndividualChildPanel(children, selectedId){
      const options = children.map(c=>`<option value="${c.child_id}" ${String(c.child_id)===String(selectedId)?'selected':''}>${escapeHtml(c.full_name)}</option>`).join('');
      return `
        <div class="tile">
          <div class="d-flex align-items-center justify-content-between mb-3">
            <div>
              <h5 style="font-size:.75rem;font-weight:800;display:flex;align-items:center;gap:.4rem;margin:0;color:#18432b;">
                <i class="bi bi-activity text-success"></i> Growth Insights
              </h5>
              <p class="tile-sub" style="margin:.2rem 0 0;">Quick analysis from the child’s latest records</p>
            </div>
            <div class="d-flex align-items-center gap-2">
              <select id="gmChildSelect" class="form-select" style="font-size:.72rem;min-width:220px;">${options}</select>
              <button id="gmExportBtn" class="btn btn-outline-success btn-sm" style="font-size:.65rem;font-weight:600;border-radius:8px;">
                <i class="bi bi-download me-1"></i> Export
              </button>
            </div>
          </div>
 
          <!-- Status strip -->
          <div id="gmStatusStrip" style="background:#eaf5ee;border:1px solid var(--border-soft);border-radius:10px;padding:.75rem 1rem;margin-bottom:1.1rem;display:flex;gap:1rem;flex-wrap:wrap;">
            <div style="min-width:180px;">
              <div style="font-size:.62rem;color:#5f7464;">Current Status</div>
              <div id="gmCurrentStatus" class="badge-status" style="display:inline-block;margin-top:.2rem;">—</div>
            </div>
            <div style="min-width:180px;">
              <div style="font-size:.62rem;color:#5f7464;">Latest Weight</div>
              <div id="gmLatestWeight" style="font-size:.75rem;font-weight:700;">—</div>
            </div>
            <div style="min-width:180px;">
              <div style="font-size:.62rem;color:#5f7464;">Latest Height</div>
              <div id="gmLatestHeight" style="font-size:.75rem;font-weight:700;">—</div>
            </div>
          </div>
 
          <!-- Insights grid -->
          <div id="gmInsights" class="row g-2 mb-3">
            <!-- Filled dynamically -->
          </div>
 
          <!-- Classification history -->
          <div>
            <div style="font-size:.72rem;font-weight:700;color:#18432b;margin:0 0 .4rem;">Classification History</div>
            <div id="gmHistoryChips" style="display:flex;flex-wrap:wrap;gap:.4rem;">
              <!-- Filled dynamically -->
            </div>
          </div>
        </div>
      `;
    }
 
// REPLACE this function inside renderNutritionClassificationModule(...)
function renderPopulationPanel(summary, recent, children){
  // Build monthly aggregates (last 6 months), dedup per child per month (latest-only)
  const agg = aggregateMonthlyCounts(recent, 6);
  const chartHtml = buildStackedBarChart(agg); // 100% stacked bars (percent)
  const kpis = computePopulationKPIs(summary, recent, children || []);
 
  return `
    <div class="tile">
      <div class="tile-header">
        <h5><i class="bi bi-graph-up text-success"></i> Population-Level Nutrition Trends</h5>
      </div>
      <p class="tile-sub">6-month overview of nutrition status distribution</p>
 
      <!-- Legend -->
      <div class="d-flex align-items-center gap-3 mb-2" aria-label="Legend" style="flex-wrap:wrap;">
        <span class="d-inline-flex align-items-center gap-2" style="font-size:.62rem;font-weight:700;color:#18432b;">
          <span style="width:12px;height:12px;background:#0b7a43;border-radius:3px;display:inline-block;"></span> Normal
        </span>
        <span class="d-inline-flex align-items-center gap-2" style="font-size:.62rem;font-weight:700;color:#18432b;">
          <span style="width:12px;height:12px;background:#f4a400;border-radius:3px;display:inline-block;"></span> Below Normal
        </span>
        <span class="d-inline-flex align-items-center gap-2" style="font-size:.62rem;font-weight:700;color:#18432b;">
          <span style="width:12px;height:12px;background:#8e44ad;border-radius:3px;display:inline-block;"></span> Above Normal
        </span>
      </div>
 
      ${chartHtml}
 
      <!-- KPI cards -->
      <div class="row g-3 mt-3">
        ${kpiCard('Improvement Rate', kpis.improvementRate, 'Children moved to normal status')}
        ${kpiCard('At Risk', kpis.atRisk, 'Require close monitoring')}
        ${kpiCard('Coverage', kpis.coverage, 'Children monitored regularly')}
      </div>
    </div>
  `;
 
  // ---------- Helpers ----------
 
  function kpiCard(title, value, desc){
    return `
      <div class="col-12 col-md-4">
        <div class="tile" style="min-height:110px;">
          <div style="font-size:.62rem;color:#5f7464;font-weight:700;letter-spacing:.06em;text-transform:uppercase;">${escapeHtml(title)}</div>
          <div style="font-size:1.6rem;font-weight:800;color:#0b7a43;line-height:1;margin-top:.2rem;">${value}</div>
          <div style="font-size:.62rem;color:#586c5d;margin-top:.3rem;">${escapeHtml(desc)}</div>
        </div>
      </div>
    `;
  }
 
  // Data: last N months, latest record per child per month
  function aggregateMonthlyCounts(recentRecords, lastN=6){
    const perMonth = new Map(); // ym -> Map(child -> status)
    const sorted = (recentRecords||[]).slice().sort((a,b)=>{
      return String(a.weighing_date||'').localeCompare(String(b.weighing_date||'')); // oldest -> newest
    });
 
    for(const r of sorted){
      const d = r.weighing_date; if(!d) continue;
      const ym = d.slice(0,7); // YYYY-MM
      if(!perMonth.has(ym)) perMonth.set(ym, new Map());
      const key = r.child_name || `#${r.child_id||0}`;
      perMonth.get(ym).set(key, r.status_code || 'UNSET'); // latest wins
    }
 
    const allYms = Array.from(perMonth.keys()).sort();
    const yms = allYms.slice(-lastN);
 
    const labels = [];
    const normal = [];
    const below = [];
    const above = [];
    const isBelow = (c)=> c==='SAM' || c==='MAM' || c==='UW';
    const isAbove = (c)=> c==='OW' || c==='OB';
 
    for(const ym of yms){
      labels.push(formatYm(ym));
      let n=0,b=0,a=0;
      perMonth.get(ym).forEach(code=>{
        if(code==='NOR') n++;
        else if(isBelow(code)) b++;
        else if(isAbove(code)) a++;
      });
      normal.push(n);
      below.push(b);
      above.push(a);
    }
 
    return { labels, normal, below, above };
  }
 
  function formatYm(ym){
    const [Y,M] = ym.split('-').map(Number);
    const d = new Date(Y, M-1, 1);
    return d.toLocaleDateString('en-PH',{month:'short', year:'numeric'});
  }
 
  // 100% stacked bars (percent)
// REPLACE the whole buildStackedBarChart(data) inside renderPopulationPanel(...)
function buildStackedBarChart(data){
  if(!data.labels.length){
    return `<div class="chart-placeholder">No trend data available</div>`;
  }

  const labels = data.labels;
  const series = [
    { key:'normal', label:'Normal',       color:'#0b7a43', values:data.normal },
    { key:'below',  label:'Below Normal', color:'#f4a400', values:data.below  },
    { key:'above',  label:'Above Normal', color:'#8e44ad', values:data.above  }
  ];

  const percents = labels.map((_,i)=>{
    const total = series.reduce((s,sv)=>s+(sv.values[i]||0),0);
    const pct = total ? series.map(sv => (sv.values[i]||0)/total) : series.map(()=>0);
    return { total, pct };
  });

  // ViewBox keeps a stable coordinate system; we let the browser scale uniformly
  const VB = { w: 120, h: 70 };
  const pad = { l: 10, r: 4, t: 6, b: 16 };
  const chartW = VB.w - pad.l - pad.r;
  const chartH = VB.h - pad.t - pad.b;

  const groupCount = labels.length;
  const barWidth = chartW / groupCount * 0.6;
  const step = chartW / groupCount;

  const ticks = [0,25,50,75,100];
  const yForPct = (p)=> pad.t + chartH * (1 - p);

  let bars = '';
  labels.forEach((lab, i)=>{
    const x = pad.l + i*step + (step - barWidth)/2;
    let yCursor = pad.t + chartH; // bottom
    // bottom -> top order
    series.forEach((sv, idx)=>{
      const h = chartH * (percents[i].pct[idx] || 0);
      const y = yCursor - h;
      const title = `${sv.label}: ${((percents[i].pct[idx]||0)*100).toFixed(1)}% (${lab})`;
      bars += `<rect x="${x.toFixed(2)}" y="${y.toFixed(2)}" width="${barWidth.toFixed(2)}" height="${h.toFixed(2)}"
                 fill="${sv.color}" rx="1" ry="1"><title>${title}</title></rect>`;
      yCursor = y;
    });
  });

  const grids = ticks.map(t=>{
    const y = yForPct(t/100).toFixed(2);
    return `<line x1="${pad.l}" y1="${y}" x2="${VB.w-pad.r}" y2="${y}" stroke="#e6ede9" stroke-width="0.4"
                 vector-effect="non-scaling-stroke" />
            <text x="${pad.l-1.8}" y="${(+y)+1.8}" font-size="2.6" fill="#637668" text-anchor="end">${t}%</text>`;
  }).join('');

  const xlabels = labels.map((lab,i)=>{
    const x = (pad.l + i*step + step/2).toFixed(2);
    const y = (VB.h - 2.5).toFixed(2);
    return `<text x="${x}" y="${y}" font-size="2.6" fill="#637668" text-anchor="middle">${lab}</text>`;
  }).join('');

  return `
    <div style="width:100%;position:relative;">
      <svg class="svg-chart" viewBox="0 0 ${VB.w} ${VB.h}" preserveAspectRatio="xMidYMid meet"
           style="border:1px solid var(--border-soft);border-radius:12px;background:#fff;">
        ${grids}
        ${bars}
        ${xlabels}
      </svg>
    </div>
  `;
}
 
  // KPI computations (unchanged logic)
  function computePopulationKPIs(summary, recentRecords, childrenList){
    const counts = Object.fromEntries((summary||[]).map(s=>[s.status_code, Number(s.child_count||0)]));
    const normal = counts.NOR || 0;
    const below = (counts.SAM||0)+(counts.MAM||0)+(counts.UW||0);
    const above = (counts.OW||0)+(counts.OB||0);
    const denom = normal+below+above;
    const atRiskPct = denom ? (below/denom*100) : null;
 
    const { improved, pairs } = computeImprovement(recentRecords||[]);
    const improvementPct = pairs ? (improved/pairs*100) : null;
 
    const today = new Date();
    const coverageDen = (childrenList||[]).length || 0;
    const coverageNum = (childrenList||[]).reduce((acc,c)=>{
      const d = (c.last_weighing_date && c.last_weighing_date!=='Never') ? new Date(c.last_weighing_date+'T00:00:00') : null;
      if(!d) return acc;
      const days = Math.round((today - d)/(1000*60*60*24));
      return acc + (days<=45 ? 1 : 0);
    },0);
    const coveragePct = coverageDen ? (coverageNum/coverageDen*100) : null;
 
    return {
      improvementRate: formatPct(improvementPct),
      atRisk: formatPct(atRiskPct),
      coverage: formatPct(coveragePct)
    };
  }
 
  function computeImprovement(recentRecords){
    const map = new Map();
    const mal = new Set(['SAM','MAM','UW']);
    for(const r of (recentRecords||[])){
      const k = r.child_name || `#${r.child_id||0}`;
      if(!map.has(k)) map.set(k, []);
      const arr = map.get(k);
      if(arr.length<2) arr.push(r); // expect recent feed desc; ok even if not perfect
    }
    let improved=0, pairs=0;
    map.forEach(arr=>{
      if(arr.length<2) return;
      const [latest, prev] = arr;
      if(latest?.status_code && prev?.status_code){
        pairs++;
        if(mal.has(prev.status_code) && latest.status_code==='NOR') improved++;
      }
    });
    return { improved, pairs };
  }
 
  function formatPct(n){
    if(n==null || !isFinite(n)) return '—';
    return `${(Math.round(n*10)/10).toFixed(1)}%`;
  }
}
 
// Replace the entire renderWFLPanel(...) with this version.
 
function renderWFLPanel(summary){
  // Map summary into WFL/H categories like in the screenshot
  const counts = toCounts(summary || []);
  const categories = [
    { code:'SAM', label:'Severely Wasted', color:'#d23d3d' },
    { code:'MAM', label:'Wasted',          color:'#f4a400' },
    { code:'NOR', label:'Normal',          color:'#0b7a43' },
    { code:'OW',  label:'Overweight',      color:'#1c79d0' },
    { code:'OB',  label:'Obese',           color:'#8e44ad' }
  ].map(c => ({ ...c, count: counts[c.code] || 0 }));
 
  const total = categories.reduce((s,c)=>s+c.count,0);
 
  const chartHtml = total ? horizontalBars(categories) : `<div class="chart-placeholder">No WFL/H data available</div>`;
  const listHtml = total ? breakdownList(categories, total) : '';
 
  return `
    <div class="tile">
      <div class="tile-header">
        <h5><i class="bi bi-scales text-success"></i> Weight-for-Length/Height Assessment</h5>
      </div>
      <p class="tile-sub">Auto-generated WFL/H classifications</p>
 
      ${chartHtml}
 
      <div class="mt-3">
        ${listHtml}
      </div>
    </div>
  `;
 
  // ---- Helpers ----
 
  function toCounts(summaryArr){
    const map = { SAM:0, MAM:0, NOR:0, OW:0, OB:0 };
    summaryArr.forEach(s=>{
      const code = s.status_code;
      if(map.hasOwnProperty(code)){
        map[code] += Number(s.child_count||0);
      }
    });
    return map;
  }
 
  // Horizontal bar chart SVG (clean, ticks, left labels)
  function horizontalBars(items){
  const maxVal = Math.max(1, ...items.map(i=>i.count));
  const VB = { w: 120, h: 70 }; // logical canvas
  const pad = { l: 34, r: 6, t: 8, b: 10 };
  const chartW = VB.w - pad.l - pad.r;
  const chartH = VB.h - pad.t - pad.b;

  const bandH = chartH / items.length;
  const ticks = 4; // 4 gridlines between 0 and max

  // Gridlines and x-ticks
  let grid = '';
  for(let i=0; i<=ticks; i++){
    const x = pad.l + (chartW * i / ticks);
    grid += `<line x1="${x.toFixed(2)}" y1="${pad.t}" x2="${x.toFixed(2)}" y2="${(VB.h-pad.b).toFixed(2)}"
               stroke="#e6ede9" stroke-width="0.4" vector-effect="non-scaling-stroke" />`;
  }

  // Bars + tracks + y labels
  let rows = '';
  items.forEach((it, idx)=>{
    const y = pad.t + idx*bandH + bandH*0.18;
    const h = bandH*0.64;

    const w = chartW * (it.count / maxVal);
    const x = pad.l;

    // Track background
    rows += `<rect x="${x}" y="${y}" width="${chartW}" height="${h}" rx="1.5" ry="1.5" fill="#f4f7f5"></rect>`;
    // Value bar
    rows += `<rect x="${x}" y="${y}" width="${w.toFixed(2)}" height="${h}" rx="1.5" ry="1.5" fill="${it.color}">
               <title>${it.label}\nCount: ${it.count}</title>
             </rect>`;
    // Left-side labels
    rows += `<text x="${(pad.l-2)}" y="${(y+h/2+1.2).toFixed(2)}" font-size="2.9" fill="#5e7264" text-anchor="end">${it.label}</text>`;
  });

  return `
    <div style="width:100%;position:relative;">
      <svg class="svg-chart" viewBox="0 0 ${VB.w} ${VB.h}" preserveAspectRatio="xMidYMid meet"
           style="border:1px solid var(--border-soft);border-radius:12px;background:#ffffff;">
        ${grid}
        ${rows}
      </svg>
    </div>
  `;
}
 
  // Breakdown list like the screenshot
  function breakdownList(items, total){
    const row = (it)=>{
      const pct = total ? (it.count/total*100) : 0;
      const pctTxt = formatPct(pct);
      const countTxt = `${it.count} ${it.count===1?'child':'children'}`;
      return `
        <div class="d-flex align-items-center justify-content-between"
             style="background:#fbfdfb;border:1px solid #e4ebe5;border-radius:10px;padding:.6rem .75rem;margin-bottom:.5rem;">
          <div class="d-flex align-items-center gap-2">
            <span style="width:12px;height:12px;border-radius:3px;display:inline-block;background:${it.color};"></span>
            <span style="font-size:.72rem;font-weight:700;color:#1e3e27;">${escapeHtml(it.label)}</span>
          </div>
          <div class="d-flex align-items-center gap-3" style="font-size:.65rem;font-weight:700;">
            <span style="color:#1e3e27;">${countTxt}</span>
            <span style="color:#5f7464;">${pctTxt}</span>
          </div>
        </div>
      `;
    };
    return items.map(row).join('');
  }
 
  function formatPct(n){
    if(n==null || !isFinite(n)) return '—';
    return `${(Math.round(n*10)/10).toFixed(1)}%`;
  }
}
 
//    Keep these functions inside renderNutritionClassificationModule(...)
 
function renderProgressDocsPanel(children, recent){
  // Build per-child before/after using the two most recent records in `recent` feed
  // Map children by full_name to get child_id and purok
  const childMap = new Map((children||[]).map(c => [c.full_name, { id:c.child_id, purok:c.purok_name || 'Not Set' }]));
 
  // recent is desc by date; collect latest two per child
  const byChild = new Map();
  (recent||[]).forEach(r=>{
    const key = r.child_name;
    if(!key) return;
    const arr = byChild.get(key) || [];
    if (arr.length < 2) arr.push(r); // keep only latest 2 (desc order in feed)
    byChild.set(key, arr);
  });
 
  const mal = new Set(['SAM','MAM','UW']);
  const items = [];
  byChild.forEach((arr, name)=>{
    if (arr.length < 2) return;
    const after = arr[0]; // latest
    const before = arr[1]; // previous
    const meta = childMap.get(name) || { id:null, purok:'Not Set' };
 
    let change = 'Unchanged';
    if (before.status_code && after.status_code) {
      if (mal.has(before.status_code) && after.status_code === 'NOR') change = 'Improved';
      else if (before.status_code === 'NOR' && mal.has(after.status_code)) change = 'Worsened';
      else if (before.status_code !== after.status_code) change = 'Changed';
    }
 
    items.push({
      name,
      child_id: meta.id,
      purok: meta.purok,
      before, after, change
    });
  });
 
  // Sort: Improved first, then Changed, Unchanged
  const rank = {Improved:0, Changed:1, Unchanged:2, Worsened:-1};
  items.sort((a,b)=>{
    const ra = (a.change in rank)?rank[a.change]:1;
    const rb = (b.change in rank)?rank[b.change]:1;
    if (ra !== rb) return ra - rb;
    // tie-break by name
    return a.name.localeCompare(b.name);
  });
 
  // Keep for export/use
  window.__progressItems = items;
 
  const header = `
    <div class="tile">
      <div class="d-flex align-items-start justify-content-between">
        <div>
          <div class="tile-header">
            <h5><i class="bi bi-journal-text text-success"></i> Intervention Progress Documentation</h5>
          </div>
          <p class="tile-sub">Before and after tracking of interventions</p>
        </div>
        <button id="progExportBtn" class="btn btn-outline-success btn-sm" style="font-size:.65rem;font-weight:700;border-radius:10px;">
          <i class="bi bi-download me-1"></i> Export Report
        </button>
      </div>
    </div>
  `;
 
  const list = items.length ? items.map((it, idx)=>progressCard(it, idx)).join('') :
    `<div class="tile"><div class="text-center py-5" style="color:var(--muted);font-size:.7rem;">
      <i class="bi bi-emoji-neutral" style="font-size:2rem;opacity:.4;"></i>
      <div class="mt-2">Not enough data yet. Add at least 2 weighing records per child to see progress.</div>
    </div></div>`;
 
  return `
    ${header}
    ${list}
  `;
 
  // ---------- helpers ----------
 
  function progressCard(it, idx){
    const latestCode = it.after?.status_code || '';
    const latestChip = latestCode ? `<span class="badge-status badge-${esc(latestCode)}" style="padding:.28rem .55rem;border-radius:12px;font-size:.55rem;font-weight:800;text-transform:uppercase;">${prettyStatus(latestCode)}</span>` : '';
 
    return `
      <div class="tile" style="padding:0;">
        <div style="padding:1rem 1rem .4rem;border-bottom:1px solid var(--border-soft);display:flex;align-items:center;justify-content:space-between;">
          <div>
            <div style="font-size:.8rem;font-weight:700;color:#18432b;">${esc(it.name)}</div>
            <div style="font-size:.62rem;color:#6a7a6d;">${esc(it.purok)}</div>
          </div>
          ${latestChip}
        </div>
 
        <!-- Before/After row -->
        <div class="row g-0" style="padding: .65rem;">
          <div class="col-12 col-lg-6">
            ${beforeAfterBox('Before Intervention', it.before)}
          </div>
          <div class="col-12 col-lg-6">
            ${beforeAfterBox('After Intervention', it.after)}
          </div>
        </div>
 
        <!-- Details toggle -->
        <div style="border-top:1px solid var(--border-soft);padding:.45rem .8rem;">
          <button class="prog-toggle btn btn-link p-0" data-index="${idx}" data-child-id="${it.child_id||''}" style="font-size:.7rem;font-weight:700;text-decoration:none;color:#1e3e27;">
            <i class="bi bi-eye me-2" aria-hidden="true"></i> View Details
          </button>
        </div>
 
        <div id="prog-det-${idx}" class="prog-details" style="display:none;padding:.5rem .8rem .9rem;"></div>
      </div>
    `;
  }
 
  function beforeAfterBox(title, r){
    const ok = !!r;
    const bg = '#eaf5ee';
    return `
      <div style="background:${bg};border:1px solid var(--border-soft);border-radius:10px;padding:.7rem .9rem;margin:.25rem;">
        <div style="font-size:.6rem;color:#5f7464;font-weight:800;letter-spacing:.05em;text-transform:uppercase;margin-bottom:.25rem;">
          ${esc(title)}
        </div>
        <div style="font-size:.7rem;color:#1e3e27;">
          <div><span style="color:#5f7464;">Date:</span> ${ok?fmtDate(r.weighing_date):'—'}</div>
          <div><span style="color:#5f7464;">Weight:</span> ${ok?fmtNum(r.weight_kg,'kg'):'—'}</div>
          <div><span style="color:#5f7464;">Height:</span> ${ok?fmtNum(r.length_height_cm,'cm'):'—'}</div>
          <div><span style="color:#5f7464;">Status:</span> ${ok?prettyStatus(r.status_code):'—'}</div>
        </div>
      </div>
    `;
  }
 
  function fmtDate(d){
    if(!d) return '—';
    try { return new Date(d+'T00:00:00').toLocaleDateString('en-PH'); } catch(e){ return d; }
  }
  function fmtNum(v,suffix){
    if(v==null || v==='') return '—';
    const n = Number(v);
    if (!isFinite(n)) return '—';
    const val = Math.round(n*10)/10;
    return `${val} ${suffix}`;
  }
  function prettyStatus(code){
    const map = {
      NOR: 'Normal',
      MAM: 'MAM',
      SAM: 'SAM',
      UW:  'Underweight',
      OW:  'Overweight',
      OB:  'Obese',
      ST:  'Stunted',
      UNSET:'Not Set'
    };
    return map[code] || code || '—';
  }
  function esc(s){ return (s??'').toString().replace(/[&<>"']/g, m=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[m])); }
}
 
// Wire up details toggles and export, runs after renderProgressDocsPanel(...)
function attachProgressHandlersProgress(){
  // Export CSV
  const exportBtn = document.getElementById('progExportBtn');
  if (exportBtn) {
    exportBtn.addEventListener('click', ()=>{
      const items = window.__progressItems || [];
      if (!items.length) { alert('No data to export'); return; }
      const rows = [];
      rows.push(['Child','Purok','Before Date','Before Weight (kg)','Before Height (cm)','Before Status','After Date','After Weight (kg)','After Height (cm)','After Status','Change']);
      items.forEach(it=>{
        const b=it.before||{}, a=it.after||{};
        rows.push([
          it.name,
          it.purok,
          b.weighing_date||'',
          b.weight_kg??'',
          b.length_height_cm??'',
          b.status_code||'',
          a.weighing_date||'',
          a.weight_kg??'',
          a.length_height_cm??'',
          a.status_code||'',
          it.change||''
        ]);
      });
      const csv = rows.map(r=>r.map(v=>`"${String(v).replace(/"/g,'""')}"`).join(',')).join('\n');
      const blob = new Blob([csv],{type:'text/csv;charset=utf-8;'});
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `progress_report_${new Date().toISOString().slice(0,10)}.csv`;
      a.click();
      URL.revokeObjectURL(url);
    });
  }
 
  // Details toggles (fetch last records on demand)
  document.querySelectorAll('.prog-toggle').forEach(btn=>{
    btn.addEventListener('click', async ()=>{
      const idx = btn.dataset.index;
      const childId = parseInt(btn.dataset.childId||'0',10);
      const panel = document.getElementById(`prog-det-${idx}`);
      if (!panel) return;
 
      // Toggle if already loaded
      if (panel.dataset.loaded === '1') {
        panel.style.display = (panel.style.display==='none' || !panel.style.display) ? 'block' : 'none';
        return;
      }
 
      // Load on first open
      if (!childId) {
        panel.innerHTML = `<div class="text-muted" style="font-size:.65rem;">Details unavailable (no child ID)</div>`;
        panel.dataset.loaded = '1';
        panel.style.display = 'block';
        return;
      }
 
      panel.innerHTML = `<div class="text-muted" style="font-size:.65rem;"><span class="spinner-border spinner-border-sm me-2"></span>Loading details...</div>`;
      panel.style.display = 'block';
 
      try{
        const res = await fetch(`${api.nutrition}?child_id=${childId}`, {
          headers: {'X-CSRF-Token': window.__BNS_CSRF, 'X-Requested-With':'XMLHttpRequest'}
        });
        if(!res.ok) throw new Error('HTTP '+res.status);
        const data = await res.json();
        const rows = (data.records||[]).slice(0,6); // last 6 (API returns desc order)
        if(!rows.length){
          panel.innerHTML = `<div class="text-muted" style="font-size:.65rem;">No history found.</div>`;
        } else {
          panel.innerHTML = `
            <div class="table-responsive">
              <table class="table table-sm mb-0" style="font-size:.65rem;">
                <thead>
                  <tr>
                    <th style="border:none;">Date</th>
                    <th style="border:none;">Age (mo)</th>
                    <th style="border:none;">Weight</th>
                    <th style="border:none;">Height</th>
                    <th style="border:none;">Status</th>
                    <th style="border:none;">Remarks</th>
                  </tr>
                </thead>
                <tbody>
                  ${rows.map(r=>`
                    <tr>
                      <td style="border-top:1px solid #f0f4f1;">${r.weighing_date ? new Date(r.weighing_date+'T00:00:00').toLocaleDateString('en-PH') : '—'}</td>
                      <td style="border-top:1px solid #f0f4f1;">${r.age_in_months ?? '—'}</td>
                      <td style="border-top:1px solid #f0f4f1;">${r.weight_kg ?? '—'}</td>
                      <td style="border-top:1px solid #f0f4f1;">${r.length_height_cm ?? '—'}</td>
                      <td style="border-top:1px solid #f0f4f1;">${r.status_code ? `<span class="badge-status badge-${r.status_code}">${r.status_code}</span>` : '—'}</td>
                      <td style="border-top:1px solid #f0f4f1;">${(r.remarks||'-').replace(/</g,'&lt;')}</td>
                    </tr>
                  `).join('')}
                </tbody>
              </table>
            </div>
          `;
        }
        panel.dataset.loaded = '1';
      } catch(e){
        console.error('Details load error', e);
        panel.innerHTML = `<div class="text-danger" style="font-size:.65rem;">Error loading details.</div>`;
        panel.dataset.loaded = '1';
      }
    });
  });
}
    function attachIndividualHandlers(children){
      const sel = document.getElementById('gmChildSelect');
      const btn = document.getElementById('gmExportBtn');
      if(sel){
        sel.addEventListener('change', ()=> loadAndRenderChildSeries(parseInt(sel.value,10)));
      }
      if(btn){
        btn.addEventListener('click', ()=>{
          const id = parseInt(document.getElementById('gmChildSelect').value,10);
          if(!id) return;
          exportChildSeriesCSV(id);
        });
      }
    }
 
    function loadAndRenderChildSeries(childId){
      const insightsEl = document.getElementById('gmInsights');
      const historyEl = document.getElementById('gmHistoryChips');
      const statusEl = document.getElementById('gmCurrentStatus');
      const wEl = document.getElementById('gmLatestWeight');
      const hEl = document.getElementById('gmLatestHeight');
 
      if (insightsEl) insightsEl.innerHTML = `
        <div class="col-12 text-muted" style="font-size:.65rem;">
          <span class="spinner-border spinner-border-sm me-2"></span>Loading child data...
        </div>`;
 
      fetchJSON(`${api.nutrition}?child_id=${childId}`).then(data=>{
        let records = (data.records||[]).slice().reverse(); // oldest -> newest
 
        if(!records.length){
          statusEl.textContent = '—'; wEl.textContent='—'; hEl.textContent='—';
          statusEl.className = 'badge-status';
          if (insightsEl) insightsEl.innerHTML = `
            <div class="col-12 text-center py-4" style="color:var(--muted);font-size:.65rem;">No history found</div>`;
          if (historyEl) historyEl.innerHTML = '';
          return;
        }
 
        const latest = records[records.length-1];
        const prev = records.length>1 ? records[records.length-2] : null;
 
        // Status strip
        statusEl.className = `badge-status ${latest.status_code?('badge-'+latest.status_code):''}`;
        statusEl.textContent = latest.status_code || 'Not Available';
        wEl.textContent = latest.weight_kg ? `${latest.weight_kg} kg` : '—';
        hEl.textContent = latest.length_height_cm ? `${latest.length_height_cm} cm` : '—';
 
        // Insights
        const toDate = (s)=> new Date(s+'T00:00:00');
        const daysBetween = (a,b)=> Math.max(0, Math.round((a-b)/(1000*60*60*24)));
        const today = new Date();
        const lastDate = latest.weighing_date ? toDate(latest.weighing_date) : null;
        const daysSince = lastDate ? daysBetween(today, lastDate) : null;
 
        const bmi = (latest.weight_kg && latest.length_height_cm)
          ? +(latest.weight_kg / Math.pow(latest.length_height_cm/100,2)).toFixed(2)
          : null;
 
        let wVel = null, hVel = null, wArrow='→', hArrow='→';
        if (prev && prev.weighing_date) {
          const d1 = toDate(prev.weighing_date);
          const d2 = toDate(latest.weighing_date);
          const dDays = Math.max(1, daysBetween(d2, d1)); // avoid div by zero
          const months = dDays/30;
          if (prev.weight_kg!=null && latest.weight_kg!=null) {
            wVel = (latest.weight_kg - prev.weight_kg)/months;
            wArrow = wVel > 0.05 ? '↑' : (wVel < -0.05 ? '↓' : '→');
          }
          if (prev.length_height_cm!=null && latest.length_height_cm!=null) {
            hVel = (latest.length_height_cm - prev.length_height_cm)/months;
            hArrow = hVel > 0.2 ? '↑' : (hVel < -0.2 ? '↓' : '→');
          }
        }
 
        // Consecutive in current status
        let consecutive = 0;
        for (let i = records.length-1; i>=0; i--) {
          const s = records[i].status_code || '';
          if (!s || s !== (latest.status_code||'')) break;
          consecutive++;
        }
 
        // Build insights cards
        const card = (title, value, sub='', color='var(--text)') => `
          <div class="col-12 col-sm-6 col-lg-3">
            <div style="background:#fff;border:1px solid var(--border-soft);border-radius:12px;padding:.8rem;box-shadow:var(--shadow-sm);height:100%;">
              <div style="font-size:.6rem;color:#5f7464;font-weight:700;letter-spacing:.04em;text-transform:uppercase;">${title}</div>
              <div style="font-size:1.15rem;font-weight:800;color:${color};line-height:1.15;margin-top:.2rem;">${value}</div>
              ${sub?`<div style="font-size:.6rem;color:#586c5d;margin-top:.15rem;">${sub}</div>`:''}
            </div>
          </div>`;
 
        const fmt = (n,dec=2)=> (n==null || !isFinite(n)) ? '—' : (Math.round(n*Math.pow(10,dec))/Math.pow(10,dec)).toFixed(dec);
 
        const insightsHtml = [
          card('BMI Now', bmi!=null?`${fmt(bmi,2)}`:'—', (latest.weight_kg&&latest.length_height_cm)?'kg/m²':'', '#0b7a43'),
          card('Weight Velocity', wVel!=null?`${fmt(wVel,2)} kg/mo ${wArrow}`:'—', prev?'vs last record':'Need ≥2 records', wVel!=null?(wVel>0?'#0b7a43':(wVel<0?'#b02020':'#845900')):'var(--text)'),
          card('Height Velocity', hVel!=null?`${fmt(hVel,2)} cm/mo ${hArrow}`:'—', prev?'vs last record':'Need ≥2 records', hVel!=null?(hVel>0?'#0b7a43':(hVel<0?'#b02020':'#845900')):'var(--text)'),
          card('Days Since Last Weigh', daysSince!=null?`${daysSince} day${daysSince===1?'':'s'}`:'—', lastDate?new Date(lastDate).toLocaleDateString('en-PH'):'', daysSince!=null?(daysSince<=30?'#0b7a43':(daysSince<=45?'#f4a400':'#b02020')):'var(--text)'),
          card('Consecutive in Status', `${consecutive}`, latest.status_code?escapeHtml(latest.status_code):'', '#1c79d0')
        ].join('');
 
        if (insightsEl) insightsEl.innerHTML = insightsHtml;
 
        // History chips (last 6)
        const last6 = records.slice(-6);
        const chip = (r) => {
          const label = r.weighing_date ? (new Date(r.weighing_date+'T00:00:00')).toLocaleDateString('en-PH',{month:'short'}) : '';
          const sc = r.status_code || 'UNSET';
          const cls = `badge-status ${r.status_code?('badge-'+sc):''}`;
          return `<span title="${r.weighing_date||''}" style="display:inline-flex;align-items:center;gap:.35rem;background:#f6faf7;border:1px solid var(--border-soft);border-radius:999px;padding:.25rem .5rem;font-size:.58rem;font-weight:700;">
            <span class="${cls}" style="padding:.2rem .45rem;border-radius:8px;">${escapeHtml(sc)}</span>
            <span style="color:#637668;">${label}</span>
          </span>`;
        };
        if (historyEl) historyEl.innerHTML = last6.map(chip).join('');
 
      }).catch(()=>{
        if (insightsEl) insightsEl.innerHTML = `<div class="col-12 text-center py-4" style="color:var(--red);font-size:.65rem;">Error loading data</div>`;
        if (historyEl) historyEl.innerHTML = '';
      });
    }
 
    // Population trend (NOR-only trend as in dashboard)
    function buildPopTrend(recent){
      const map = {};
      recent.forEach(r => {
        if(!r.weighing_date) return;
        const ym = r.weighing_date.slice(0,7);
        if(!map[ym]) map[ym] = {NOR:0};
        if(r.status_code === 'NOR') map[ym].NOR++;
      });
      const arr = Object.entries(map)
        .sort((a,b) => a[0] > b[0] ? 1 : -1)
        .slice(-6)
        .map(([ym,o]) => ({label: ym.slice(5), value: o.NOR}));
      if(!arr.length) return `<div class="chart-placeholder">No trend data available</div>`;
 
      const max = Math.max(...arr.map(d => d.value)) || 1;
      const pts = arr.map((d,i) => {
        const x = (i/(arr.length-1)) * 100;
        const y = 100 - (d.value/max) * 85 - 7;
        return {x, y, label: d.label};
      });
      const poly = pts.map(p => `${p.x},${p.y}`).join(' ');
      const circles = pts.map(p => `<circle cx="${p.x}" cy="${p.y}" r="2" fill="#0b7a43"></circle>`).join('');
      return `<div style="width:100%;position:relative;">
        <svg viewBox="0 0 100 100" preserveAspectRatio="none" style="width:100%;height:140px;">
          <polyline fill="none" stroke="#0b7a43" stroke-width="1.4" points="${poly}" />
          ${circles}
        </svg>
        <div class="d-flex justify-content-between" style="margin-top:-10px;">
          ${pts.map(p => `<span style="font-size:.5rem;color:#637668;">${p.label}</span>`).join('')}
        </div>
      </div>`;
    }
 
    function exportChildSeriesCSV(childId){
      fetchJSON(`${api.nutrition}?child_id=${childId}`).then(data=>{
        const rows = data.records||[];
        if(!rows.length){ alert('No records to export'); return; }
        const header = ['Date','Age (months)','Weight (kg)','Height (cm)','Status','Remarks'];
        const csv = [header].concat(rows.slice().reverse().map(r=>[
          r.weighing_date, r.age_in_months, r.weight_kg||'', r.length_height_cm||'', r.status_code||'', (r.remarks||'').replaceAll('\n',' ')
        ])).map(row=>row.map(v=>`"${String(v).replace(/"/g,'""')}"`).join(',')).join('\n');
 
        const blob = new Blob([csv], {type:'text/csv;charset=utf-8;'});
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `growth_${childId}.csv`;
        a.click();
        URL.revokeObjectURL(url);
      });
    }
  }).catch(err=>{
    console.error('Growth Monitoring error:', err);
    moduleContent.innerHTML = `
      <div class="alert alert-danger" style="font-size:.7rem;">
        <i class="bi bi-exclamation-triangle me-2"></i>
        <strong>Error loading Growth Monitoring:</strong> ${escapeHtml(err.message||String(err))}
      </div>
    `;
  });
}

// REPLACE the whole renderFeedingProgramsModule(...) with this version
// REPLACE the whole renderFeedingProgramsModule(...) with this version
// REPLACE the whole renderFeedingProgramsModule(...) with this version
function renderFeedingProgramsModule(label) {
  showLoading(label);

  // State
  let allSuppRecords = [];
  let currentFilters = { q: '', type: '', status: '' };
  let currentView = 'table'; // 'table' | 'schedule'

  // Helpers
  const typeIcon = (t) => {
    if (/vitamin/i.test(t)) return {icon:'bi-capsule', color:'#f4a400'};
    if (/iron/i.test(t))    return {icon:'bi-heart-pulse', color:'#d23d3d'};
    if (/deworm/i.test(t))  return {icon:'bi-shield-check', color:'#077a44'};
    return {icon:'bi-capsule', color:'#077a44'};
  };
  const formatDate = (d) => d ? new Date(d).toLocaleDateString('en-PH',{timeZone:'Asia/Manila'}) : '—';
  const statusBadge = (s) => {
    if (s === 'overdue') return `<span class="badge-status" style="background:#ffe4e4;color:#b02020;">OVERDUE</span>`;
    return `<span class="badge-status badge-NOR">COMPLETED</span>`;
  };
  const daysDisplay = (n) => {
    if (n == null) return '—';
    if (n < 0) return `<span style="color:#dc3545;font-weight:600;">${Math.abs(n)} day${Math.abs(n)===1?'':'s'}</span>`;
    return `<span style="color:#0b7a43;font-weight:600;">${n} day${n===1?'':'s'}</span>`;
  };

  function renderTable(records) {
    return `
      <div class="tile" style="padding:0;overflow:hidden;">
        <div class="table-responsive">
          <table class="table table-hover mb-0" style="font-size:.7rem;">
            <thead style="background:#f8faf9;border-bottom:1px solid var(--border-soft);">
              <tr>
                <th style="padding:.75rem .8rem;font-size:.65rem;font-weight:700;color:#344f3a;border:none;">Child Name</th>
                <th style="padding:.75rem .8rem;font-size:.65rem;font-weight:700;color:#344f3a;border:none;">Supplement Type</th>
                <th style="padding:.75rem .8rem;font-size:.65rem;font-weight:700;color:#344f3a;border:none;">Date Given</th>
                <th style="padding:.75rem .8rem;font-size:.65rem;font-weight:700;color:#344f3a;border:none;">Next Due Date</th>
                <th style="padding:.75rem .8rem;font-size:.65rem;font-weight:700;color:#344f3a;border:none;">Days Until Due</th>
                <th style="padding:.75rem .8rem;font-size:.65rem;font-weight:700;color:#344f3a;border:none;">Status</th>
                <th style="padding:.75rem .8rem;font-size:.65rem;font-weight:700;color:#344f3a;border:none;min-width:180px;">Notes</th>
                <th style="padding:.75rem .8rem;font-size:.65rem;font-weight:700;color:#344f3a;border:none;">Actions</th>
              </tr>
            </thead>
            <tbody>
              ${records.map(r => {
                const ico = typeIcon(r.supplement_type || '');
                const safeNotes = r.notes ? escapeHtml(r.notes) : '';
                return `
                  <tr style="border-bottom:1px solid #f0f4f1;">
                    <td style="padding:.8rem;border:none;">
                      <div class="d-flex align-items-center gap-2">
                        <div style="width:24px;height:24px;background:#e8f5ea;border-radius:50%;display:flex;align-items:center;justify-content:center;">
                          <i class="bi bi-check" style="font-size:.7rem;color:#0b7a43;"></i>
                        </div>
                        <span style="font-weight:600;color:#1e3e27;">${escapeHtml(r.child_name || 'Unknown')}</span>
                      </div>
                    </td>
                    <td style="padding:.8rem;border:none;">
                      <div class="d-flex align-items-center gap-2">
                        <i class="bi ${ico.icon}" style="color:${ico.color};font-size:.8rem;"></i>
                        <span style="color:#586c5d;">${escapeHtml(r.supplement_type)}</span>
                      </div>
                    </td>
                    <td style="padding:.8rem;border:none;color:#586c5d;">${formatDate(r.supplement_date)}</td>
                    <td style="padding:.8rem;border:none;color:#586c5d;">${formatDate(r.next_due_date)}</td>
                    <td style="padding:.8rem;border:none;">${daysDisplay(r.days_until_due)}</td>
                    <td style="padding:.8rem;border:none;">${statusBadge(r.status)}</td>
                    <td style="padding:.8rem;border:none;color:#586c5d;">
                      ${safeNotes
                        ? `<div title="${safeNotes}" style="display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;white-space:pre-wrap;line-height:1.2;">${safeNotes}</div>`
                        : '—'}
                    </td>
                    <td style="padding:.8rem;border:none;">
                      <button class="btn btn-sm btn-outline-primary" data-supp-id="${r.supplement_id}" style="padding:.3rem .6rem;border:1px solid #1c79d0;background:#fff;border-radius:6px;font-size:.6rem;color:#1c79d0;">
                        Update
                      </button>
                    </td>
                  </tr>
                `;
              }).join('')}
            </tbody>
          </table>
        </div>
      </div>
    `;
  }

  function applyFilters() {
    if (currentView === 'schedule') {
      renderSchedulePanel();
      return;
    }
    const searchTerm = currentFilters.q.toLowerCase();
    const out = allSuppRecords.filter(r => {
      const matchesQ = !searchTerm || (r.child_name && r.child_name.toLowerCase().includes(searchTerm));
      const matchesType = !currentFilters.type || (r.supplement_type === currentFilters.type);
      const matchesStatus = !currentFilters.status || (r.status === currentFilters.status);
      return matchesQ && matchesType && matchesStatus;
    });
    document.getElementById('suppRecordsCount')?.replaceChildren(document.createTextNode(`${out.length} record${out.length!==1?'s':''} found`));
    document.getElementById('suppRecordsContainer').innerHTML = out.length ? renderTable(out) : `
      <div class="tile" style="padding:2rem;text-align:center;">
        <i class="bi bi-clipboard-x text-muted" style="font-size:2.2rem;opacity:.3;"></i>
        <div class="mt-2" style="font-size:.7rem;color:var(--muted);">No records found</div>
      </div>`;
  }

  // Schedule view (unchanged from previous version)
  function renderSchedulePanel() {
    const items = (allSuppRecords || [])
      .filter(r => r.next_due_date)
      .sort((a,b) => new Date(a.next_due_date) - new Date(b.next_due_date));

    document.getElementById('suppRecordsCount')?.replaceChildren(
      document.createTextNode(`${items.length} due item${items.length!==1?'s':''}`)
    );

    const itemRow = (r) => {
      const isOverdue = r.status === 'overdue';
      const badge = isOverdue
        ? `<span class="badge-status" style="background:#ff6b6b;color:#fff;">Overdue</span>`
        : (Number.isFinite(r.days_until_due) ? 
           `<span class="badge-status" style="background:#e8f5ea;color:#077a44;">In ${r.days_until_due} day${r.days_until_due===1?'':'s'}</span>` : '');

      return `
        <div class="d-flex align-items-center justify-content-between"
             style="background:#fff;border:1px solid #e9efeb;border-radius:12px;padding:.8rem 1rem;margin-bottom:.6rem;">
          <div class="d-flex align-items-center" style="gap:.7rem;">
            <div style="width:32px;height:32px;border-radius:10px;background:#e8f5ea;display:flex;align-items:center;justify-content:center;">
              <i class="bi bi-calendar-event" style="color:#077a44;"></i>
            </div>
            <div>
              <div style="font-size:.78rem;font-weight:700;color:#18432b;line-height:1;">${escapeHtml(r.child_name || 'Unknown')}</div>
              <div style="font-size:.62rem;color:#586c5d;">${escapeHtml(r.supplement_type)} - Due: ${formatDate(r.next_due_date)}</div>
            </div>
          </div>
          <div>${badge}</div>
        </div>
      `;
    };

    document.getElementById('suppRecordsContainer').innerHTML = `
      <div class="tile">
        <div class="tile-header">
          <h5><i class="bi bi-calendar3 text-success"></i> Supplementation Schedule</h5>
        </div>
        <p class="tile-sub">Upcoming due dates and reminders</p>
        ${ items.length ? items.map(itemRow).join('') :
          `<div class="text-center py-4">
             <i class="bi bi-inbox text-muted" style="font-size:2rem;opacity:.35;"></i>
             <p class="mt-2" style="font-size:.65rem;color:var(--muted);">No upcoming or overdue supplementation schedules</p>
           </div>`}
      </div>
    `;
  }

  function loadSuppRecords() {
    const url = `${api.supplementation}?list=1`;
    return fetchJSON(url)
      .then(res => {
        if (!res.success) throw new Error(res.error || 'Failed to load supplementation records');
        allSuppRecords = res.records || [];
        if (currentView === 'schedule') {
          renderSchedulePanel();
        } else {
          applyFilters();
        }
      })
      .catch(err => {
        console.error(err);
        document.getElementById('suppRecordsContainer').innerHTML = `
          <div class="text-center py-4" style="color:#dc3545;font-size:.65rem;">
            <i class="bi bi-exclamation-triangle" style="font-size:2rem;opacity:.5;color:#dc3545;"></i>
            <p style="margin:.5rem 0 0;color:#dc3545;">Error loading supplementation records</p>
          </div>
        `;
      });
  }

  // Render shell
  setTimeout(() => {
    moduleContent.innerHTML = `
      <div class="fade-in">
        <div class="d-flex justify-content-between align-items-start mb-3">
          <div>
            <h1 class="page-title mb-1" style="font-size:1.35rem;font-weight:700;color:#0a3a1e;">
              💊 Supplementation Management
            </h1>
            <p class="text-muted mb-0" style="font-size:.75rem;font-weight:500;">Track vitamin A, iron, and deworming programs</p>
          </div>
        </div>

        <!-- Tabs -->
        <div class="mb-3">
          <ul class="nav nav-tabs" style="border-bottom:2px solid var(--border-soft);">
            <li class="nav-item"><a class="nav-link active supplement-tab" href="#" data-tab="all" style="font-size:.75rem;font-weight:600;color:var(--green);border-bottom:2px solid var(--green);background:none;border-left:none;border-right:none;border-top:none;padding:.75rem 1.2rem;">All Records</a></li>
            <li class="nav-item"><a class="nav-link supplement-tab" href="#" data-tab="vitamin-a" style="font-size:.75rem;font-weight:600;color:var(--muted);border:none;background:none;padding:.75rem 1.2rem;">Vitamin A</a></li>
            <li class="nav-item"><a class="nav-link supplement-tab" href="#" data-tab="iron" style="font-size:.75rem;font-weight:600;color:var(--muted);border:none;background:none;padding:.75rem 1.2rem;">Iron</a></li>
            <li class="nav-item"><a class="nav-link supplement-tab" href="#" data-tab="deworming" style="font-size:.75rem;font-weight:600;color:var(--muted);border:none;background:none;padding:.75rem 1.2rem;">Deworming</a></li>
            <li class="nav-item"><a class="nav-link supplement-tab" href="#" data-tab="schedule" style="font-size:.75rem;font-weight:600;color:var(--muted);border:none;background:none;padding:.75rem 1.2rem;">Schedule</a></li>
          </ul>
        </div>

        <!-- Search & Filter -->
        <div class="tile mb-4">
          <div class="tile-header mb-3">
            <h5 style="font-size:.72rem;font-weight:800;color:#18432b;margin:0;">Search & Filter</h5>
          </div>
          <div class="row g-3 align-items-end">
            <div class="col-md-3">
              <label class="form-label" style="font-size:.65rem;font-weight:600;color:var(--muted);margin-bottom:.4rem;">Search Child</label>
              <div class="position-relative">
                <i class="bi bi-search position-absolute" style="left:.8rem;top:50%;transform:translateY(-50%);font-size:.75rem;color:var(--muted);"></i>
                <input type="text" class="form-control" id="suppSearchInput" placeholder="Child name..." style="font-size:.7rem;padding:.6rem .8rem .6rem 2.2rem;border:1px solid var(--border-soft);border-radius:8px;background:var(--surface);">
              </div>
            </div>
            <div class="col-md-3">
              <label class="form-label" style="font-size:.65rem;font-weight:600;color:var(--muted);margin-bottom:.4rem;">Supplement Type</label>
              <select class="form-select" id="suppTypeFilter" style="font-size:.7rem;padding:.6rem .8rem;border:1px solid var(--border-soft);border-radius:8px;background:var(--surface);">
                <option value="">All Types</option>
                <option value="Vitamin A">Vitamin A</option>
                <option value="Iron">Iron</option>
                <option value="Deworming">Deworming</option>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label" style="font-size:.65rem;font-weight:600;color:var(--muted);margin-bottom:.4rem;">Status</label>
              <select class="form-select" id="suppStatusFilter" style="font-size:.7rem;padding:.6rem .8rem;border:1px solid var(--border-soft);border-radius:8px;background:var(--surface);">
                <option value="">All Status</option>
                <option value="completed">Completed</option>
                <option value="overdue">Overdue</option>
              </select>
            </div>
            <div class="col-md-3 d-flex justify-content-end">
              <button class="btn btn-success" id="openSuppModalBtn" data-bs-toggle="modal" data-bs-target="#supplementationRecordModal" style="font-size:.65rem;font-weight:600;padding:.6rem 1rem;border-radius:8px;">
                <i class="bi bi-plus-lg me-1"></i> Add Record
              </button>
            </div>
          </div>
        </div>

        <!-- Records -->
        <div class="d-flex justify-content-between align-items-center mb-2">
          <div>
            <h6 style="font-size:.8rem;font-weight:700;color:var(--green);margin:0;">Distribution Records</h6>
            <p class="text-muted mb-0" id="suppRecordsCount" style="font-size:.65rem;">—</p>
          </div>
          <div class="text-muted" style="font-size:.65rem;"><i class="bi bi-download me-1"></i></div>
        </div>

        <div id="suppRecordsContainer">
          <div class="text-center py-3" style="color:var(--muted);font-size:.65rem;">
            <div class="spinner-border spinner-border-sm me-2" role="status" style="width:1rem;height:1rem;border-width:2px;"></div>
            Loading distribution records...
          </div>
        </div>
      </div>
    `;

    // Wire filters
    document.getElementById('suppSearchInput').addEventListener('input', e => {
      currentFilters.q = e.target.value;
      applyFilters();
    });

    // Tabs + Type filter sync
    function setSuppTabActive(key){
      document.querySelectorAll('.supplement-tab').forEach(t=>{
        t.classList.remove('active');
        t.style.color = 'var(--muted)';
        t.style.borderBottom = 'none';
      });
      const el = document.querySelector(`.supplement-tab[data-tab="${key}"]`);
      if (el) {
        el.classList.add('active');
        el.style.color = 'var(--green)';
        el.style.borderBottom = '2px solid var(--green)';
      }
    }

    function setTypeAndFilter(typeLabel){
      currentView = 'table';
      currentFilters.type = typeLabel;
      const sel = document.getElementById('suppTypeFilter');
      if (sel) sel.value = typeLabel;

      const reverseMap = {
        '': 'all',
        'Vitamin A': 'vitamin-a',
        'Iron': 'iron',
        'Deworming': 'deworming'
      };
      setSuppTabActive(reverseMap[typeLabel] || 'all');
      applyFilters();
    }

    document.querySelectorAll('.supplement-tab').forEach(tab=>{
      tab.addEventListener('click', (e)=>{
        e.preventDefault();
        const which = tab.dataset.tab; // 'all' | 'vitamin-a' | 'iron' | 'deworming' | 'schedule'
        if (which === 'schedule') {
          currentView = 'schedule';
          setSuppTabActive('schedule');
          renderSchedulePanel();
          return;
        }
        const map = {
          'all': '',
          'vitamin-a': 'Vitamin A',
          'iron': 'Iron',
          'deworming': 'Deworming'
        };
        setTypeAndFilter(map[which] ?? '');
      });
    });

    document.getElementById('suppTypeFilter')?.addEventListener('change', e=>{
      const typeLabel = e.target.value;
      setTypeAndFilter(typeLabel);
    });

    document.getElementById('suppStatusFilter').addEventListener('change', e => {
      currentFilters.status = e.target.value;
      applyFilters();
    });

    // Prepare modal each time it opens (Create mode)
    const modalEl = document.getElementById('supplementationRecordModal');
    modalEl?.addEventListener('show.bs.modal', () => {
      const mode = modalEl.dataset.mode || 'create';
      if (mode === 'edit') return; // huwag i-reset kapag edit

      fetchJSON(api.children+'?action=list')
        .then(res => {
          const sel = document.getElementById('suppChildSelect');
          sel.innerHTML = '<option value="">Select child</option>';
          (res.children||[]).forEach(c => {
            const opt = document.createElement('option');
            opt.value = c.child_id;
            opt.textContent = c.full_name;
            sel.appendChild(opt);
          });
          const today = new Date().toLocaleDateString('en-CA',{timeZone:'Asia/Manila'});
          document.getElementById('suppDate').value = today;
          document.getElementById('suppNextDue').value = '';
          document.getElementById('suppDosage').value = '';
          document.getElementById('suppNotes').value = '';
          document.getElementById('suppType').value = '';
          // Enable fields for Create
          ['suppChildSelect','suppType','suppDate'].forEach(id=>{
            const el = document.getElementById(id);
            if (el) el.disabled = false;
          });
          const title = document.getElementById('supplementationRecordModalLabel');
          if (title) title.textContent = 'Add Supplementation Record';
          const saveBtn = document.getElementById('saveSuppRecordBtn');
          if (saveBtn) saveBtn.innerHTML = '<i class="bi bi-save me-1"></i> Save Record';
        })
        .catch(()=>{});
    });

    // Auto-suggest next due date based on type
    document.addEventListener('change', (e) => {
      if (e.target && e.target.id === 'suppType') {
        const t = e.target.value;
        const d = document.getElementById('suppDate').value;
        if (!d) return;
        const base = new Date(d);
        if (t === 'Vitamin A' || t === 'Deworming') {
          base.setMonth(base.getMonth()+6);
        } else if (t === 'Iron') {
          base.setMonth(base.getMonth()+3);
        } else {
          return;
        }
        const ph = new Date(base.toLocaleString('en-US',{timeZone:'Asia/Manila'}));
        document.getElementById('suppNextDue').value = ph.toISOString().split('T')[0];
      }
    });

    // SAVE (Create or Update) — single handler with mode check
    const saveBtnEl = document.getElementById('saveSuppRecordBtn');
    if (saveBtnEl) {
      if (saveBtnEl.__handlerRef) {
        saveBtnEl.removeEventListener('click', saveBtnEl.__handlerRef);
      }

      const onSaveClick = () => {
        const modal = document.getElementById('supplementationRecordModal');
        const mode = modal?.dataset.mode || 'create';

        if (saveBtnEl.dataset.busy === '1') return;

        // Common fields
        const dosage   = document.getElementById('suppDosage').value || null;
        const nextDue  = document.getElementById('suppNextDue').value || null;
        const notes    = document.getElementById('suppNotes').value || null;

        if (mode === 'edit') {
          // UPDATE (PUT) — dosage/next_due_date/notes lang ang ina-allow ng API
          const id = parseInt(modal.dataset.suppId || '0', 10);
          if (!id) { alert('Invalid record'); return; }

          saveBtnEl.dataset.busy = '1';
          saveBtnEl.disabled = true;
          saveBtnEl.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Updating...';

          const payload = { dosage, next_due_date: nextDue, notes };
          fetchJSON(api.supplementation + '?id=' + id, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
          })
          .then(res => {
            if (!res.success) throw new Error(res.error || 'Failed to update');
            const bs = bootstrap.Modal.getInstance(modal);
            bs?.hide();
            return loadSuppRecords();
          })
          .catch(err => {
            console.error(err);
            alert('❌ Error updating record: ' + (err.message || err));
          })
          .finally(() => {
            saveBtnEl.dataset.busy = '0';
            saveBtnEl.disabled = false;
            saveBtnEl.innerHTML = '<i class="bi bi-save me-1"></i> Save Record';
          });

          return;
        }

        // CREATE (POST)
        const payload = {
          child_id: parseInt(document.getElementById('suppChildSelect').value || '0', 10),
          supplement_type: document.getElementById('suppType').value,
          supplement_date: document.getElementById('suppDate').value,
          dosage,
          next_due_date: nextDue,
          notes
        };

        const missing = [];
        if (!payload.child_id) missing.push('Child');
        if (!payload.supplement_type) missing.push('Supplement Type');
        if (!payload.supplement_date) missing.push('Date Given');
        if (missing.length) { alert('Please fill in: ' + missing.join(', ')); return; }

        saveBtnEl.dataset.busy = '1';
        saveBtnEl.disabled = true;
        saveBtnEl.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving...';

        fetchJSON(api.supplementation, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        })
        .then(res => {
          if (!res.success) throw new Error(res.error || 'Failed to save');
          const bs = bootstrap.Modal.getInstance(document.getElementById('supplementationRecordModal'));
          bs?.hide();
          return loadSuppRecords();
        })
        .catch(err => {
          console.error(err);
          alert('❌ Error saving record: ' + (err.message || err));
        })
        .finally(() => {
          saveBtnEl.dataset.busy = '0';
          saveBtnEl.disabled = false;
          saveBtnEl.innerHTML = '<i class="bi bi-save me-1"></i> Save Record';
        });
      };

      saveBtnEl.addEventListener('click', onSaveClick);
      saveBtnEl.__handlerRef = onSaveClick;
    }

    // Reset modal state after close (so consistent sa UI kapag Create ulit)
    const supModal = document.getElementById('supplementationRecordModal');
    supModal?.addEventListener('hidden.bs.modal', ()=>{
      supModal.dataset.mode = 'create';
      supModal.dataset.suppId = '';
      ['suppChildSelect','suppType','suppDate'].forEach(id=>{
        const el = document.getElementById(id);
        if (el) el.disabled = false;
      });
      const title = document.getElementById('supplementationRecordModalLabel');
      if (title) title.textContent = 'Add Supplementation Record';
      const saveBtn = document.getElementById('saveSuppRecordBtn');
      if (saveBtn) saveBtn.innerHTML = '<i class="bi bi-save me-1"></i> Save Record';
    });

    // Global delegated click for Update buttons (bind once)
    if (!document.__suppUpdateHandlerBound) {
      document.addEventListener('click', (e)=>{
        const btn = e.target.closest('button[data-supp-id]');
        if (!btn) return;
        const id = parseInt(btn.getAttribute('data-supp-id')||'0',10);
        const rec = (allSuppRecords || []).find(r => Number(r.supplement_id) === id);
        if (!rec) { alert('Record not found.'); return; }
        openSuppEditModal(rec);
      });
      document.__suppUpdateHandlerBound = true;
    }

    // Initial load
    loadSuppRecords();
  }, 50);

  // Open modal in EDIT mode, prefill fields, at i-disable ang hindi puwedeng baguhin
  function openSuppEditModal(rec){
    const modal = document.getElementById('supplementationRecordModal');
    modal.dataset.mode = 'edit';
    modal.dataset.suppId = String(rec.supplement_id);

    // Title at Save label
    const title = document.getElementById('supplementationRecordModalLabel');
    if (title) title.textContent = 'Update Supplementation Record';
    const saveBtn = document.getElementById('saveSuppRecordBtn');
    if (saveBtn) saveBtn.innerHTML = '<i class="bi bi-save me-1"></i> Update Record';

    // Load children list then set value, pero disabled ang child/type/date
    fetchJSON(api.children+'?action=list')
      .then(res=>{
        const sel = document.getElementById('suppChildSelect');
        sel.innerHTML = '<option value="">Select child</option>';
        (res.children||[]).forEach(c=>{
          const opt = document.createElement('option');
          opt.value = c.child_id;
          opt.textContent = c.full_name;
          sel.appendChild(opt);
        });
        sel.value = String(rec.child_id);
        ['suppChildSelect','suppType','suppDate'].forEach(id=>{
          const el = document.getElementById(id);
          if (el) el.disabled = true;
        });
        document.getElementById('suppType').value = rec.supplement_type || '';
        document.getElementById('suppDate').value = rec.supplement_date || '';
        document.getElementById('suppDosage').value = rec.dosage || '';
        document.getElementById('suppNextDue').value = rec.next_due_date || '';
        document.getElementById('suppNotes').value = rec.notes || '';
        const bs = new bootstrap.Modal(modal);
        bs.show();
      })
      .catch(()=>{
        // Fallback kahit hindi ma-load ang list, still show modal
        document.getElementById('suppChildSelect').innerHTML = `<option value="${rec.child_id}">${escapeHtml(rec.child_name||'Child')}</option>`;
        ['suppChildSelect','suppType','suppDate'].forEach(id=>{
          const el = document.getElementById(id);
          if (el) el.disabled = true;
        });
        document.getElementById('suppType').value = rec.supplement_type || '';
        document.getElementById('suppDate').value = rec.supplement_date || '';
        document.getElementById('suppDosage').value = rec.dosage || '';
        document.getElementById('suppNextDue').value = rec.next_due_date || '';
        document.getElementById('suppNotes').value = rec.notes || '';
        const bs = new bootstrap.Modal(modal);
        bs.show();
      });
  }
}

function renderNutritionCalendarModule(label) {
  showLoading(label);
  
  // Initialize calendar state
  let currentCalendarDate = new Date();
  // Set to Philippines timezone
  currentCalendarDate = new Date(currentCalendarDate.toLocaleString('en-US', {timeZone: 'Asia/Manila'}));
  
  // Fetch real events data from the API
  fetchJSON(api.events + '?action=list')
    .then(response => {
      if (!response.success) {
        throw new Error(response.error || 'Failed to fetch events data');
      }
      
      const events = response.events || [];

      window.__eventsById = new Map();
events.forEach(ev => window.__eventsById.set(ev.event_id, ev));
      
      moduleContent.innerHTML = `
        <div class="fade-in">
          <!-- Page Header -->
          <div class="d-flex justify-content-between align-items-start mb-3">
            <div>
              <h1 class="page-title mb-1" style="font-size:1.35rem;font-weight:700;color:#0a3a1e;">
                📅 Nutrition Event Scheduling
              </h1>
              <p class="text-muted mb-0" style="font-size:.75rem;font-weight:500;">Plan and track nutrition sessions and activities</p>
            </div>
            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#scheduleEventModal" style="font-size:.7rem;font-weight:600;padding:.6rem 1.05rem;border-radius:11px;box-shadow:0 2px 6px -2px rgba(20,104,60,.5);">
              <i class="bi bi-plus-lg me-1"></i> Schedule Event
            </button>
          </div>

          <!-- Tab Navigation -->
          <div class="mb-3">
            <ul class="nav nav-tabs" style="border-bottom:2px solid var(--border-soft);">
              <li class="nav-item">
                <a class="nav-link active calendar-tab" href="#" data-tab="calendar" style="font-size:.75rem;font-weight:600;color:var(--green);border-bottom:2px solid var(--green);background:none;border-left:none;border-right:none;border-top:none;padding:.75rem 1.2rem;">
                  Calendar View
                </a>
              </li>
              <li class="nav-item">
                <a class="nav-link calendar-tab" href="#" data-tab="health" style="font-size:.75rem;font-weight:600;color:var(--muted);border:none;background:none;padding:.75rem 1.2rem;">
                  Health Sessions
                </a>
              </li>
              <li class="nav-item">
                <a class="nav-link calendar-tab" href="#" data-tab="feeding" style="font-size:.75rem;font-weight:600;color:var(--muted);border:none;background:none;padding:.75rem 1.2rem;">
                  Feeding Programs
                </a>
              </li>
              <li class="nav-item">
                <a class="nav-link calendar-tab" href="#" data-tab="weighing" style="font-size:.75rem;font-weight:600;color:var(--muted);border:none;background:none;padding:.75rem 1.2rem;">
                  Weighing Schedules
                </a>
              </li>
              <li class="nav-item">
                <a class="nav-link calendar-tab" href="#" data-tab="nutrition" style="font-size:.75rem;font-weight:600;color:var(--muted);border:none;background:none;padding:.75rem 1.2rem;">
                  Nutrition Education
                </a>
              </li>
            </ul>
          </div>

          <!-- Calendar Content -->
          <div id="calendar-tab-content">
            ${renderCalendarView(events, currentCalendarDate)}
          </div>
        </div>
      `;

      // Add tab switching functionality
      setupCalendarTabs(events, currentCalendarDate);
      
      // Setup calendar navigation
      setupCalendarNavigation(events, currentCalendarDate);
    })
    .catch(error => {
      console.error('Error fetching events data:', error);
      moduleContent.innerHTML = `
        <div class="alert alert-danger" style="font-size:.7rem;">
          <i class="bi bi-exclamation-triangle me-2"></i>
          Error loading events data: ${escapeHtml(error.message)}
        </div>
      `;
    });

  // Helper function to render calendar view with functional navigation
  function renderCalendarView(events, calendarDate) {
    const phToday = new Date(new Date().toLocaleString('en-US', {timeZone: 'Asia/Manila'}));
    const selectedDate = phToday.toLocaleDateString('en-PH', {
      timeZone: 'Asia/Manila',
      year: 'numeric',
      month: 'long',
      day: 'numeric'
    });
    
    const todayEvents = events.filter(event => {
      const eventDate = new Date(event.event_date).toLocaleDateString('en-PH', {
        timeZone: 'Asia/Manila'
      });
      const todayDate = phToday.toLocaleDateString('en-PH', {
        timeZone: 'Asia/Manila'
      });
      return eventDate === todayDate;
    });

    const upcomingEvents = events.filter(event => {
      const eventDate = new Date(event.event_date);
      const phEventDate = new Date(eventDate.toLocaleString('en-US', {timeZone: 'Asia/Manila'}));
      return phEventDate >= phToday;
    }).sort((a, b) => new Date(a.event_date) - new Date(b.event_date));

    return `
      <div class="row g-3">
        <!-- Left Calendar Panel -->
        <div class="col-md-4">
          <!-- Nutrition Calendar -->
          <div class="tile mb-3">
            <div class="tile-header mb-3">
              <h5 style="font-size:.72rem;font-weight:800;color:#18432b;margin:0;">NUTRITION CALENDAR</h5>
            </div>
            <p style="font-size:.65rem;color:var(--muted);margin:0 0 1rem;font-weight:500;">Select a date to view events</p>
            
            <!-- Calendar Widget -->
            <div class="calendar-widget">
              <!-- Calendar Header with functional navigation -->
              <div class="d-flex justify-content-between align-items-center mb-3">
                <button class="btn btn-sm btn-outline-secondary" id="prevMonthBtn" style="font-size:.6rem;padding:.3rem .6rem;border-radius:6px;border:1px solid var(--border-soft);background:var(--surface);">
                  <i class="bi bi-chevron-left"></i>
                </button>
                <h6 id="calendarMonthYear" style="font-size:.75rem;font-weight:700;color:#18432b;margin:0;">
                  ${calendarDate.toLocaleDateString('en-PH', { 
                    timeZone: 'Asia/Manila',
                    month: 'long', 
                    year: 'numeric' 
                  })}
                </h6>
                <button class="btn btn-sm btn-outline-secondary" id="nextMonthBtn" style="font-size:.6rem;padding:.3rem .6rem;border-radius:6px;border:1px solid var(--border-soft);background:var(--surface);">
                  <i class="bi bi-chevron-right"></i>
                </button>
              </div>
              
              <!-- Calendar Grid -->
              <div class="calendar-grid">
                <!-- Calendar Header Days -->
                <div class="calendar-days-header">
                  <div class="calendar-day-header">Su</div>
                  <div class="calendar-day-header">Mo</div>
                  <div class="calendar-day-header">Tu</div>
                  <div class="calendar-day-header">We</div>
                  <div class="calendar-day-header">Th</div>
                  <div class="calendar-day-header">Fr</div>
                  <div class="calendar-day-header">Sa</div>
                </div>
                
                <!-- Calendar Days -->
                <div class="calendar-days" id="calendarDays">
                  ${generateCalendarDays(events, calendarDate)}
                </div>
              </div>
            </div>
          </div>

          <!-- Updated Legend to match UI exactly -->
          <div class="tile">
            <div class="tile-header mb-3">
              <h5 style="font-size:.72rem;font-weight:800;color:#18432b;margin:0;">LEGEND:</h5>
            </div>
            
            <div style="display: flex; flex-direction: column; gap: 0.5rem;">
              <div class="d-flex align-items-center gap-2">
                <div style="width:12px;height:12px;background:#077a44;border-radius:3px;"></div>
                <span style="font-size:.65rem;color:#586c5d;">Health</span>
              </div>
              <div class="d-flex align-items-center gap-2">
                <div style="width:12px;height:12px;background:#f4a400;border-radius:3px;"></div>
                <span style="font-size:.65rem;color:#586c5d;">Feeding Program</span>
              </div>
              <div class="d-flex align-items-center gap-2">
                <div style="width:12px;height:12px;background:#1c79d0;border-radius:3px;"></div>
                <span style="font-size:.65rem;color:#586c5d;">Weighing</span>
              </div>
              <div class="d-flex align-items-center gap-2">
                <div style="width:12px;height:12px;background:#a259c6;border-radius:3px;"></div>
                <span style="font-size:.65rem;color:#586c5d;">Nutrition Education</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Right Events Panel -->
        <div class="col-md-8">
          <!-- Events for Selected Date -->
          <div class="tile mb-3">
            <div class="tile-header mb-3">
              <div>
                <h5 style="font-size:.72rem;font-weight:800;color:#18432b;margin:0;">EVENTS ON ${selectedDate.toUpperCase()}</h5>
                <p style="font-size:.6rem;color:var(--muted);margin:.2rem 0 0;">${todayEvents.length} event(s) scheduled</p>
              </div>
            </div>
            
            ${todayEvents.length > 0 ? 
              `<div class="event-list">${todayEvents.map(event => renderEventItem(event)).join('')}</div>` :
              `<div class="text-center py-4">
                <i class="bi bi-calendar-x text-muted" style="font-size:2rem;opacity:0.5;"></i>
                <p style="font-size:.65rem;color:var(--muted);margin:.5rem 0 0;">No events scheduled for this date</p>
              </div>`
            }
          </div>

          <!-- All Upcoming Events -->
          <div class="tile">
            <div class="tile-header mb-3">
              <div class="d-flex justify-content-between align-items-center w-100">
                <div>
                  <h5 style="font-size:.72rem;font-weight:800;color:#18432b;margin:0;">ALL UPCOMING EVENTS</h5>
                  <p style="font-size:.6rem;color:var(--muted);margin:.2rem 0 0;">Scheduled nutrition activities</p>
                </div>
                <a href="#" style="font-size:.6rem;color:var(--green);text-decoration:none;font-weight:600;">All Events</a>
              </div>
            </div>

            <!-- Event List -->
            <div class="event-list">
              ${upcomingEvents.length > 0 ? upcomingEvents.map(event => renderEventItem(event)).join('') : 
                '<div class="text-center py-4"><i class="bi bi-calendar-x text-muted" style="font-size:2rem;opacity:0.5;"></i><p style="font-size:.65rem;color:var(--muted);margin:.5rem 0 0;">No upcoming events scheduled</p></div>'
              }
            </div>
          </div>
        </div>
      </div>
    `;
  }

  // Helper function to setup calendar navigation functionality
  function setupCalendarNavigation(events, currentDate) {
    const prevBtn = document.getElementById('prevMonthBtn');
    const nextBtn = document.getElementById('nextMonthBtn');
    const monthYearDisplay = document.getElementById('calendarMonthYear');
    const calendarDaysContainer = document.getElementById('calendarDays');
    
    let calendarDate = new Date(currentDate);
    
    function updateCalendar() {
      // Update month/year display
      monthYearDisplay.textContent = calendarDate.toLocaleDateString('en-PH', { 
        timeZone: 'Asia/Manila',
        month: 'long', 
        year: 'numeric' 
      });
      
      // Update calendar days
      calendarDaysContainer.innerHTML = generateCalendarDays(events, calendarDate);
      
      // Add click handlers to calendar days
      setupCalendarDayClickHandlers(events, calendarDate);
    }
    
    // Previous month button
    if (prevBtn) {
      prevBtn.addEventListener('click', (e) => {
        e.preventDefault();
        calendarDate.setMonth(calendarDate.getMonth() - 1);
        updateCalendar();
      });
    }
    
    // Next month button
    if (nextBtn) {
      nextBtn.addEventListener('click', (e) => {
        e.preventDefault();
        calendarDate.setMonth(calendarDate.getMonth() + 1);
        updateCalendar();
      });
    }
    
    // Initial setup of day click handlers
    setupCalendarDayClickHandlers(events, calendarDate);
  }
  
  // Helper function to setup calendar day click handlers
  function setupCalendarDayClickHandlers(events, calendarDate) {
    document.querySelectorAll('.calendar-day:not(.prev-month):not(.next-month)').forEach(dayElement => {
      dayElement.addEventListener('click', function() {
        // Remove previous selection
        document.querySelectorAll('.calendar-day.selected').forEach(el => el.classList.remove('selected'));
        
        // Add selection to clicked day
        this.classList.add('selected');
        
        // Get the selected date
        const selectedDay = parseInt(this.textContent);
        const selectedDate = new Date(calendarDate.getFullYear(), calendarDate.getMonth(), selectedDay);
        
        // Update events display for selected date
        updateSelectedDateEvents(events, selectedDate);
      });
    });
  }
  
  // Helper function to update events display for selected date
  function updateSelectedDateEvents(events, selectedDate) {
    const formattedDate = selectedDate.toLocaleDateString('en-PH', {
      timeZone: 'Asia/Manila',
      year: 'numeric',
      month: 'long',
      day: 'numeric'
    });
    
    const selectedDateEvents = events.filter(event => {
      const eventDate = new Date(event.event_date);
      return eventDate.toDateString() === selectedDate.toDateString();
    });
    
    // Find the events section and update it
    const eventsSection = document.querySelector('.tile .tile-header h5');
    const eventsCount = document.querySelector('.tile .tile-header p');
    const eventsList = eventsSection?.closest('.tile').querySelector('.event-list, .text-center');
    
    if (eventsSection && eventsCount && eventsList) {
      eventsSection.textContent = `EVENTS ON ${formattedDate.toUpperCase()}`;
      eventsCount.textContent = `${selectedDateEvents.length} event(s) scheduled`;
      
      if (selectedDateEvents.length > 0) {
        eventsList.innerHTML = selectedDateEvents.map(event => renderEventItem(event)).join('');
        eventsList.className = 'event-list';
      } else {
        eventsList.innerHTML = `
          <div class="text-center py-4">
            <i class="bi bi-calendar-x text-muted" style="font-size:2rem;opacity:0.5;"></i>
            <p style="font-size:.65rem;color:var(--muted);margin:.5rem 0 0;">No events scheduled for this date</p>
          </div>
        `;
        eventsList.className = 'text-center py-4';
      }
    }
  }

  // Helper function to generate calendar days with proper styling
  function generateCalendarDays(events, calendarDate) {
    const phToday = new Date(new Date().toLocaleString('en-US', {timeZone: 'Asia/Manila'}));
    const currentMonth = calendarDate.getMonth();
    const currentYear = calendarDate.getFullYear();
    
    // Create a map of dates with events
    const eventDates = new Map();
    events.forEach(event => {
      const eventDate = new Date(event.event_date);
      const dateKey = `${eventDate.getFullYear()}-${eventDate.getMonth()}-${eventDate.getDate()}`;
      if (!eventDates.has(dateKey)) {
        eventDates.set(dateKey, []);
      }
      eventDates.get(dateKey).push(event);
    });

    const daysInMonth = new Date(currentYear, currentMonth + 1, 0).getDate();
    const firstDay = new Date(currentYear, currentMonth, 1).getDay();
    
    let daysHTML = '';
    
    // Previous month days
    const prevMonthDays = new Date(currentYear, currentMonth, 0).getDate();
    for (let i = firstDay - 1; i >= 0; i--) {
      daysHTML += `<div class="calendar-day prev-month">${prevMonthDays - i}</div>`;
    }
    
    // Current month days
    for (let day = 1; day <= daysInMonth; day++) {
      const dateKey = `${currentYear}-${currentMonth}-${day}`;
      const hasEvents = eventDates.has(dateKey);
      
      // Check if this is today
      const isToday = (day === phToday.getDate() && 
                     currentMonth === phToday.getMonth() && 
                     currentYear === phToday.getFullYear());
      
      let dayClasses = 'calendar-day';
      if (isToday) dayClasses += ' current-day';
      
      let dayStyle = '';
      if (hasEvents) {
        dayStyle = 'style="position:relative;"';
        // Add a small indicator for events
        const eventIndicator = '<div style="position:absolute;bottom:2px;right:2px;width:4px;height:4px;background:#077a44;border-radius:50%;"></div>';
        daysHTML += `<div class="${dayClasses}" ${dayStyle} data-has-events="true">${day}${eventIndicator}</div>`;
      } else {
        daysHTML += `<div class="${dayClasses}">${day}</div>`;
      }
    }
    
    // Next month days to fill the grid
    const totalCells = Math.ceil((firstDay + daysInMonth) / 7) * 7;
    const remainingCells = totalCells - (firstDay + daysInMonth);
    for (let day = 1; day <= remainingCells; day++) {
      daysHTML += `<div class="calendar-day next-month">${day}</div>`;
    }
    
    return daysHTML;
  }

  // Add CSS for selected calendar day
  const style = document.createElement('style');
  style.textContent = `
    .calendar-day.selected {
      background: var(--green) !important;
      color: white !important;
      font-weight: 700;
    }
    .calendar-day:hover:not(.prev-month):not(.next-month) {
      background: var(--surface-soft);
      cursor: pointer;
    }
  `;
  document.head.appendChild(style);

// REPLACE the existing renderEventItem(event) function with this version.

function renderEventItem(event) {
  const eventDate = new Date(event.event_date);
  const formattedDate = eventDate.toLocaleDateString('en-PH', {
    timeZone: 'Asia/Manila',
    month: 'short',
    day: 'numeric',
    year: 'numeric'
  });

  const eventTime = event.event_time
    ? new Date(`2000-01-01T${event.event_time}`).toLocaleTimeString('en-PH', {
        hour: 'numeric',
        minute: '2-digit',
        hour12: true
      })
    : 'Time TBD';

  const eventTypeConfig = {
    'health':    { icon: 'bi-clipboard-data',  color: '#077a44', bg: '#e8f5ea', badge: 'Health' },
    'nutrition': { icon: 'bi-book',            color: '#a259c6', bg: '#f3e8ff', badge: 'Nutrition Education' },
    'feeding':   { icon: 'bi-cup-hot',         color: '#f4a400', bg: '#ffecc7', badge: 'Feeding Program' },
    'weighing':  { icon: 'bi-clipboard2-data', color: '#1c79d0', bg: '#e1f1ff', badge: 'Weighing' }
  };

  const cfg = eventTypeConfig[event.event_type] || eventTypeConfig['health'];
  const completed = Number(event.is_completed || 0) === 1;

  const statusPill = `<span class="status-pill ${completed ? 'completed' : 'scheduled'}">${completed ? 'Completed' : 'Scheduled'}</span>`;

  // NEW: Edit button added; existing delegated handler already supports data-ev-action="edit"
  const actions = `
    <div class="d-flex align-items-center gap-2">
      <button class="btn btn-sm btn-outline-primary"
              data-ev-action="edit" data-ev-id="${event.event_id}"
              style="font-size:.6rem;border-radius:8px;">
        <i class="bi bi-pencil me-1"></i> Edit
      </button>
      <button class="btn btn-sm btn-outline-secondary"
              data-ev-action="reschedule" data-ev-id="${event.event_id}"
              style="font-size:.6rem;border-radius:8px;">
        <i class="bi bi-calendar2-event me-1"></i> Reschedule
      </button>
      ${completed ? '' : `
        <button class="btn btn-sm btn-success"
                data-ev-action="complete" data-ev-id="${event.event_id}"
                style="font-size:.6rem;border-radius:8px;">
          <i class="bi bi-check2-circle me-1"></i> Mark as Complete
        </button>
      `}
    </div>
  `;

  return `
    <div class="event-item" data-ev="${event.event_id}">
      <div class="d-flex align-items-center gap-3">
        <div class="event-icon" style="background:${cfg.bg};">
          <i class="${cfg.icon}" style="color:${cfg.color};"></i>
        </div>
        <div class="flex-grow-1">
          <h6 class="event-title" style="display:flex;align-items:center;gap:.5rem;">
            ${escapeHtml(event.event_title)}
            ${statusPill}
          </h6>
          <div class="event-details">
            <span><i class="bi bi-calendar3"></i> ${formattedDate}</span>
            <span><i class="bi bi-clock"></i> ${eventTime}</span>
            <span><i class="bi bi-geo-alt"></i> ${escapeHtml(event.location || 'Location TBD')}</span>
          </div>
        </div>
        <div class="d-flex align-items-center" style="gap:.5rem;">
          <span class="event-badge" style="background:${cfg.bg};color:${cfg.color};">${cfg.badge}</span>
          ${actions}
        </div>
      </div>
    </div>
  `;
}
/* --- 2) ADD this delegated click handler inside renderNutritionCalendarModule(...), right after we render the shell the first time --- */
// Ensure we can prefill modal from list memory
window.__eventsById = window.__eventsById || new Map();

// Wire once per page lifetime
if (!window.__eventActionsWired) {
  document.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-ev-action]');
    if (!btn) return;
    const id = parseInt(btn.getAttribute('data-ev-id') || '0', 10);
    const action = btn.getAttribute('data-ev-action');

    if (!id || !action) return;

    if (action === 'complete') {
      btn.disabled = true;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Completing...';
      fetchJSON('bns_modules/api_events.php?action=complete', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ event_id: id })
      })
      .then(res => {
        if (!res.success) throw new Error(res.error || 'Failed');
        // Reload the module view
        loadModule('nutrition_calendar','Event Scheduling');
      })
      .catch(err => {
        alert('Error: ' + (err.message || err));
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check2-circle me-1"></i> Mark as Completed';
      });
    }

    if (action === 'edit' || action === 'reschedule') {
      const ev = window.__eventsById.get(id);
      openEventModal(action === 'edit' ? 'edit' : 'reschedule', ev || { event_id: id });
    }
  });
  window.__eventActionsWired = true;
}

/* --- 3) REPLACE the "Schedule Event Modal functionality" DOMContentLoaded block at the bottom with this version --- */
(function wireEventModal(){
  const scheduleEventModal = document.getElementById('scheduleEventModal');
  const saveEventBtn = document.getElementById('saveEventBtn');
  const scheduleEventForm = document.getElementById('scheduleEventForm');

  if (!scheduleEventModal || !saveEventBtn || !scheduleEventForm) return;

  // Flag so the legacy wiring at the bottom won’t attach again
  window.__eventModalEnhanced = true;

  // Inputs
  const titleInput   = scheduleEventForm.querySelector('input[name="event_title"]');
  const typeSelect   = scheduleEventForm.querySelector('select[name="event_type"]');
  const descInput    = scheduleEventForm.querySelector('textarea[name="event_description"]');
  const dateInput    = scheduleEventForm.querySelector('input[name="event_date"]');
  const timeInput    = scheduleEventForm.querySelector('input[name="event_time"]');
  const locInput     = scheduleEventForm.querySelector('input[name="location"]');
  const audInput     = scheduleEventForm.querySelector('input[name="target_audience"]');
  const pubSelect    = scheduleEventForm.querySelector('select[name="is_published"]');

  // Min date = today
  if (dateInput) {
    const today = new Date().toISOString().split('T')[0];
    dateInput.setAttribute('min', today);
  }

  // Global open function for Create/Edit/Reschedule
  window.openEventModal = function(mode, eventData) {
    const m = mode || 'create';
    scheduleEventModal.dataset.mode = m;
    scheduleEventModal.dataset.eventId = eventData?.event_id || '';

    // Header title
    const header = document.getElementById('scheduleEventModalLabel');
    if (header) {
      header.textContent =
        m === 'edit'       ? 'Edit Event'
      : m === 'reschedule' ? 'Reschedule Event'
                           : 'Schedule New Event';
    }

    // Prefill
    if (m === 'create') {
      scheduleEventForm.reset();
      if (dateInput) dateInput.value = new Date().toISOString().split('T')[0];
    } else {
      if (titleInput) titleInput.value = eventData.event_title || '';
      if (typeSelect) typeSelect.value = eventData.event_type || '';
      if (descInput)  descInput.value  = eventData.event_description || '';
      if (dateInput)  dateInput.value  = eventData.event_date || '';
      if (timeInput)  timeInput.value  = eventData.event_time || '';
      if (locInput)   locInput.value   = eventData.location || '';
      if (audInput)   audInput.value   = eventData.target_audience || '';
      if (pubSelect)  pubSelect.value  = String(eventData.is_published ?? 1);
    }

    // NEW: Mode-based enable/disable
    const setDisabled = (els, disabled) => els.forEach(el => el && (el.disabled = disabled));
    const scheduleEls = [dateInput, timeInput, locInput];                // Schedule Information
    const infoEls     = [titleInput, typeSelect, descInput, audInput, pubSelect]; // Event Details + Additional Info

    if (m === 'edit') {
      // In EDIT mode: schedule info cannot be edited
      setDisabled(scheduleEls, true);    // disable Event Date, Event Time, Location
      setDisabled(infoEls, false);       // allow editing of title, type, description, audience, publish status
    } else if (m === 'reschedule') {
      // In RESCHEDULE mode: only date/time can be changed
      setDisabled([locInput], true);     // Location stays locked
      setDisabled(infoEls, true);        // Other details locked
      setDisabled([dateInput, timeInput], false); // Date & Time editable
    } else {
      // In CREATE mode: everything editable
      setDisabled(scheduleEls, false);
      setDisabled(infoEls, false);
    }

    // Button label
    if (saveEventBtn) {
      saveEventBtn.textContent =
        m === 'edit'       ? 'Save Changes'
      : m === 'reschedule' ? 'Save New Schedule'
                           : 'Schedule Event';
    }

    // Show modal
    const modal = new bootstrap.Modal(scheduleEventModal);
    modal.show();
  };

  // Save handler (Create/Edit/Reschedule)
  if (saveEventBtn) {
    if (saveEventBtn.__handlerRef) {
      saveEventBtn.removeEventListener('click', saveEventBtn.__handlerRef);
    }
    const onSave = () => {
      const mode = scheduleEventModal?.dataset.mode || 'create';
      const eid  = parseInt(scheduleEventModal?.dataset.eventId || '0', 10);

      // Validate
      const missing = [];
      if (!dateInput.value) missing.push('Event Date');
      if (!timeInput.value) missing.push('Event Time');
      if (mode !== 'reschedule') {
        if (!titleInput.value) missing.push('Event Title');
        if (!typeSelect.value) missing.push('Event Type');
        if (!locInput.value)   missing.push('Location');
      }
      if (missing.length) {
        alert('Please fill in: ' + missing.join(', '));
        return;
      }

      const makeBusy = (b, label) => {
        saveEventBtn.disabled = b;
        saveEventBtn.innerHTML = b
          ? `<span class="spinner-border spinner-border-sm me-2"></span>${label}`
          : (mode==='edit' ? '<i class="bi bi-save me-1"></i> Save Changes'
            : mode==='reschedule' ? '<i class="bi bi-calendar2-event me-1"></i> Save New Schedule'
            : '<i class="bi bi-calendar-plus me-1"></i> Schedule Event');
      };

      const payload = {
        event_id: eid,
        event_title: titleInput.value,
        event_type: typeSelect.value,
        event_description: descInput.value,
        event_date: dateInput.value,
        event_time: timeInput.value,
        location: locInput.value,
        target_audience: audInput.value,
        is_published: parseInt(pubSelect.value || '1', 10)
      };

      let url = 'bns_modules/api_events.php?action=create';
      if (mode === 'edit') url = 'bns_modules/api_events.php?action=update';
      if (mode === 'reschedule') url = 'bns_modules/api_events.php?action=reschedule';

      // Narrow payload for reschedule
      const finalPayload = (mode === 'reschedule')
        ? { event_id: eid, event_date: payload.event_date, event_time: payload.event_time }
        : payload;

      makeBusy(true, mode==='edit' ? 'Saving...' : (mode==='reschedule' ? 'Rescheduling...' : 'Scheduling...'));

      fetchJSON(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(finalPayload)
      })
      .then(res => {
        if (!res.success) throw new Error(res.error || 'Failed');
        // Close modal and refresh
        const modal = bootstrap.Modal.getInstance(scheduleEventModal);
        modal?.hide();
        loadModule('nutrition_calendar','Event Scheduling');
      })
      .catch(err => {
        console.error(err);
        alert('❌ ' + (err.message || err));
      })
      .finally(() => makeBusy(false));
    };
    saveEventBtn.addEventListener('click', onSave);
    saveEventBtn.__handlerRef = onSave;
  }

  // Reset form when modal is closed
  if (scheduleEventModal) {
    scheduleEventModal.addEventListener('hidden.bs.modal', function() {
      scheduleEventForm.reset();
      delete scheduleEventModal.dataset.mode;
      delete scheduleEventModal.dataset.eventId;
      if (saveEventBtn) {
        saveEventBtn.disabled = false;
        saveEventBtn.innerHTML = '<i class="bi bi-calendar-plus me-1"></i> Schedule Event';
      }
      // Re-enable all fields by default
      scheduleEventForm.querySelectorAll('input, select, textarea').forEach(el => el.disabled = false);
    });
  }

  // Auto-populate title when creating
  if (typeSelect && titleInput) {
    typeSelect.addEventListener('change', function() {
      if ((scheduleEventModal?.dataset.mode || 'create') !== 'create') return;
      const map = {
        'health': 'Health Consultation Session',
        'nutrition': 'Nutrition Education Seminar',
        'feeding': 'Supplementary Feeding Program',
        'weighing': 'Monthly Weighing Session',
        'general': 'Community Health Meeting',
        'other': 'Special Health Activity'
      };
      if (!titleInput.value.trim() && this.value && map[this.value]) {
        titleInput.value = map[this.value];
      }
    });
  }
})();

  // Helper function to setup tab switching (same as before but with updated function signature)
  function setupCalendarTabs(events, currentDate) {
    document.querySelectorAll('.calendar-tab').forEach(tab => {
      tab.addEventListener('click', (e) => {
        e.preventDefault();
        
        // Update active tab
        document.querySelectorAll('.calendar-tab').forEach(t => {
          t.classList.remove('active');
          t.style.color = 'var(--muted)';
          t.style.borderBottom = 'none';
        });
        
        tab.classList.add('active');
        tab.style.color = 'var(--green)';
        tab.style.borderBottom = '2px solid var(--green)';
        
        // Update content based on tab
        const tabType = tab.dataset.tab;
        const contentArea = document.getElementById('calendar-tab-content');
        
        switch(tabType) {
          case 'health':
            contentArea.innerHTML = renderEventTypeView(events, 'health', 'Health Sessions', '🏥');
            break;
          case 'feeding':
            contentArea.innerHTML = renderEventTypeView(events, 'feeding', 'Feeding Programs', '🍽️');
            break;
          case 'weighing':
            contentArea.innerHTML = renderEventTypeView(events, 'weighing', 'Weighing Schedules', '⚖️');
            break;
          case 'nutrition':
            contentArea.innerHTML = renderEventTypeView(events, 'nutrition', 'Nutrition Education', '📚');
            break;
          default: // 'calendar'
            contentArea.innerHTML = renderCalendarView(events, currentDate);
            // Re-setup navigation after re-rendering
            setTimeout(() => setupCalendarNavigation(events, currentDate), 100);
            break;
        }
      });
    });
  }

  // Helper function to render events filtered by type (same as before)
  function renderEventTypeView(events, eventType, title, icon) {
    const filteredEvents = events.filter(event => event.event_type === eventType);
    
    return `
      <div>
        <div class="d-flex justify-content-between align-items-start mb-3">
          <div>
            <h6 style="font-size:.8rem;font-weight:700;color:var(--green);margin:0;display:flex;align-items:center;gap:.5rem;">
              ${icon} ${title}
            </h6>
            <p class="text-muted mb-0" style="font-size:.65rem;">${filteredEvents.length} event(s) scheduled</p>
          </div>
        </div>

        <div class="tile">
          ${filteredEvents.length > 0 ? 
            filteredEvents.map(event => renderEventItem(event)).join('') :
            `<div class="text-center py-5">
              <i class="bi bi-calendar-x text-muted" style="font-size:3rem;opacity:0.3;"></i>
              <h6 class="mt-3 mb-1" style="font-size:.8rem;font-weight:600;">No ${title} Scheduled</h6>
              <p class="text-muted small mb-0" style="font-size:.65rem;">No events of this type have been scheduled yet.</p>
            </div>`
          }
        </div>
      </div>
    `;
  }
}

function renderMothersModule(label){ showLoading(label); moduleContent.innerHTML='<div class="tile fade-in"><h5 style="font-size:.68rem;">Mothers Module</h5><p class="small-note">Placeholder.</p></div>'; }



/* Replace the entire renderReportModule(...) function with this updated version. */

function renderReportModule(label) {
  showLoading(label);

  // State
  let allChildren = [];
  let recentRecords = [];
  let suppRecords = [];
  let selectedYM = toYM(new Date()); // 'YYYY-MM' (PH time)
  let monthsList = buildMonthList(12); // last 12 months

  // Load data
  Promise.all([
    fetchJSON(api.children + '?action=list').catch(() => ({children: []})),
    fetchJSON(api.nutrition + '?recent=1').catch(() => ({records: []})),
    fetchJSON(api.supplementation + '?list=1').catch(() => ({records: []}))
  ]).then(([childRes, recentRes, suppRes]) => {
    allChildren = childRes.children || [];
    recentRecords = recentRes.records || [];
    suppRecords = suppRes.records || [];
    renderShell(); // initial render
    wireEvents();  // interactions
  }).catch(err => {
    console.error('Nutrition Reports error:', err);
    moduleContent.innerHTML = `
      <div class="alert alert-danger" style="font-size:.7rem;">
        <i class="bi bi-exclamation-triangle me-2"></i>
        <strong>Failed to load Nutrition Reports:</strong> ${escapeHtml(err.message||String(err))}
      </div>
    `;
  });

  // ---------- Renderers ----------

  function renderShell() {
    const meta = computeSummary(allChildren, recentRecords, selectedYM);
    const purokAgg = aggregateByPurok(allChildren, selectedYM);

    moduleContent.innerHTML = `
      <div class="fade-in">
        <!-- Page header -->
        <div class="d-flex justify-content-between align-items-start mb-3">
          <div>
            <h1 class="page-title mb-1" style="font-size:1.35rem;font-weight:700;color:#0a3a1e;">
              Nutrition Reports
            </h1>
            <p class="text-muted mb-0" style="font-size:.75rem;font-weight:500;">Comprehensive nutrition monitoring reports and analytics</p>
          </div>
          <div class="d-flex align-items-center gap-2">
            <div>
              <select id="nrMonthSelect" class="form-select" style="font-size:.7rem;border-radius:10px;">
                ${monthsList.map(m => `
                  <option value="${m.value}" ${m.value===selectedYM?'selected':''}>${m.label}</option>
                `).join('')}
              </select>
            </div>
            <button id="nrExportAllBtn" class="btn btn-outline-success" style="font-size:.7rem;font-weight:600;border-radius:10px;">
              <i class="bi bi-download me-1"></i> Export All Reports
            </button>
          </div>
        </div>

        <!-- Summary cards -->
        <div class="stat-grid" style="margin-top:.2rem;">
          ${summaryCard('Total Children', String(meta.totalChildren), 'In monitoring program', 'bi-people-fill')}
          ${summaryCard('Normal Rate', meta.normalRateText, 'Healthy nutrition status', 'bi-graph-up')}
          ${summaryCard('Intervention Cases', String(meta.interventionCases), 'Under intervention', 'bi-clipboard-pulse')}
          ${summaryCard('Recovery Rate', meta.recoveryRateText, 'Successful interventions', 'bi-graph-up-arrow')}
        </div>

        <!-- Tabs -->
        <div class="mb-3">
          <ul class="nav nav-tabs" style="border-bottom:2px solid var(--border-soft);">
            <li class="nav-item">
              <a class="nav-link active nr-tab" href="#" data-tab="growth" style="font-size:.75rem;font-weight:600;color:var(--green);border-bottom:2px solid var(--green);background:none;border-left:none;border-right:none;border-top:none;padding:.75rem 1.2rem;">
                Growth Monitoring
              </a>
            </li>
            <li class="nav-item"><a class="nav-link nr-tab" href="#" data-tab="status" style="font-size:.75rem;font-weight:600;color:var(--muted);border:none;background:none;padding:.75rem 1.2rem;">Nutrition Status</a></li>
            <li class="nav-item"><a class="nav-link nr-tab" href="#" data-tab="supp" style="font-size:.75rem;font-weight:600;color:var(--muted);border:none;background:none;padding:.75rem 1.2rem;">Supplementation</a></li>
            <li class="nav-item"><a class="nav-link nr-tab" href="#" data-tab="interv" style="font-size:.75rem;font-weight:600;color:var(--muted);border:none;background:none;padding:.75rem 1.2rem;">Interventions</a></li>
          </ul>
        </div>

        <!-- Tab content -->
        <div id="nrTabContent">
          ${renderGrowthTab(purokAgg)}
        </div>
      </div>
    `;

    // Bind growth export on initial render
    document.getElementById('nrExportChartBtn')?.addEventListener('click', () => {
      printSection(document.getElementById('growthResultsTile'));
    });
    document.getElementById('nrExportAllBtn')?.addEventListener('click', () => {
      printSection(document.querySelector('#moduleContent'));
    });
  }

  function renderGrowthTab(purokAgg) {
    const chartHtml = buildGroupedBarChart(purokAgg);
    const tableHtml = buildPurokTable(purokAgg);

    return `
      <div class="tile" id="growthResultsTile">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <div>
            <div class="tile-header">
              <h5><i class="bi bi-bar-chart-line text-success"></i> Growth Monitoring Results</h5>
            </div>
            <p class="tile-sub">Aggregated child development data by purok</p>
          </div>
          <button id="nrExportChartBtn" class="btn btn-outline-success btn-sm" style="font-size:.65rem;font-weight:700;border-radius:10px;">
            <i class="bi bi-file-earmark-arrow-down me-1"></i> Export PDF
          </button>
        </div>

        ${chartHtml}

        <div class="mt-3">${legendRow()}</div>

        <div class="mt-3">${tableHtml}</div>
      </div>
    `;
  }

  // NEW: Nutrition Status tab (donut + detailed breakdown + insights)
  function renderStatusTab(dist) {
    const hasData = dist.total > 0;
    const donut = hasData ? buildDonutChart(dist) : `<div class="chart-placeholder">No data available</div>`;
    const breakdown = hasData ? buildStatusBreakdown(dist) : '';
    const insights = buildStatusInsights(dist);

    return `
      <div class="tile" id="statusDistributionTile">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <div>
            <div class="tile-header">
              <h5><i class="bi bi-activity text-success"></i> Nutrition Status Distribution</h5>
            </div>
            <p class="tile-sub">Classification breakdown for monitored children</p>
          </div>
          <button id="nrStatusExportBtn" class="btn btn-outline-success btn-sm" style="font-size:.65rem;font-weight:700;border-radius:10px;">
            <i class="bi bi-download me-1"></i> Export CSV
          </button>
        </div>

        <div class="row g-3">
          <div class="col-12 col-lg-6">
            <div class="tile-sub" style="margin-bottom:.4rem;">Visual Distribution</div>
            ${donut}
          </div>
          <div class="col-12 col-lg-6">
            <div class="tile-sub" style="margin-bottom:.4rem;">Detailed Breakdown</div>
            ${breakdown}
          </div>
        </div>

        <div class="mt-3">
          ${insights}
        </div>
      </div>
    `;
  }

  // UPDATED: Supplementation tab (KPIs + donut by type + breakdown only; removed monthly table)
  function renderSuppTab(sAgg) {
    const dist = {
      total: sAgg.total,
      items: [
        { label: 'Vitamin A', color: '#f4a400', count: sAgg.byType['Vitamin A'] || 0 },
        { label: 'Iron',      color: '#d23d3d', count: sAgg.byType['Iron'] || 0 },
        { label: 'Deworming', color: '#077a44', count: sAgg.byType['Deworming'] || 0 }
      ]
    };
    dist.items.forEach(i => i.pct = dist.total ? i.count / dist.total : 0);

    const donut = dist.total ? buildDonutChart(dist) : `<div class="chart-placeholder">No supplementation data for this month</div>`;

    const kpiCard = (title, val, sub='') => `
      <div class="col-6 col-lg-3">
        <div class="tile" style="min-height:110px;">
          <div style="font-size:.62rem;color:#5f7464;font-weight:800;letter-spacing:.06em;text-transform:uppercase;">${escapeHtml(title)}</div>
          <div style="font-size:1.6rem;font-weight:800;color:#0b7a43;line-height:1;margin-top:.25rem;">${escapeHtml(val)}</div>
          ${sub?`<div style="font-size:.62rem;color:#586c5d;margin-top:.28rem;">${escapeHtml(sub)}</div>`:''}
        </div>
      </div>
    `;

    return `
      <div>
        <div class="row g-3">
          ${kpiCard('Total Given', String(sAgg.total), monthsList.find(m=>m.value===selectedYM)?.label || '')}
          ${kpiCard('Unique Children', String(sAgg.uniqueChildren))}
          ${kpiCard('Overdue', String(sAgg.overdue), 'Follow-up needed')}
          ${kpiCard('Upcoming (≤30d)', String(sAgg.upcomingWithin30), 'Next due soon')}
        </div>

        <div class="tile mt-3" id="suppOverviewTile">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <div>
              <div class="tile-header">
                <h5><i class="bi bi-capsule text-success"></i> Supplementation Overview</h5>
              </div>
              <p class="tile-sub">Distribution by supplement type</p>
            </div>
            <div class="d-flex gap-2">
              <button id="nrSuppPrintBtn" class="btn btn-outline-success btn-sm" style="font-size:.65rem;font-weight:700;border-radius:10px;">
                <i class="bi bi-file-earmark-arrow-down me-1"></i> Export PDF
              </button>
              <button id="nrSuppExportBtn" class="btn btn-outline-success btn-sm" style="font-size:.65rem;font-weight:700;border-radius:10px;">
                <i class="bi bi-download me-1"></i> Export CSV
              </button>
            </div>
          </div>

          <div class="row g-3">
            <div class="col-12 col-lg-6">
              ${donut}
            </div>
            <div class="col-12 col-lg-6">
              ${buildSuppBreakdown(dist)}
            </div>
          </div>
        </div>
      </div>
    `;
  }

  // NEW: Interventions tab (KPIs + table + export), follows the reference layout
  function renderIntervTab(iAgg) {
    const kpi = (title, value, sub, color) => `
      <div class="col-12 col-md-4">
        <div class="tile" style="min-height:110px;">
          <div style="font-size:.72rem;font-weight:700;color:#18432b;">${escapeHtml(title)}</div>
          <div style="font-size:1.6rem;font-weight:800;color:${color};line-height:1;margin-top:.25rem;">${value}</div>
          <div style="font-size:.62rem;color:#586c5d;margin-top:.3rem;">${escapeHtml(sub)}</div>
        </div>
      </div>
    `;

    const badge = (code) => code
      ? `<span class="badge-status badge-${escapeHtml(code)}" style="font-size:.6rem;">${escapeHtml(prettyStatus(code))}</span>`
      : '<span class="badge-status" style="font-size:.6rem;">—</span>';

    const progressChip = (p) => {
      const map = {
        Recovered: {c:'#0b7a43', bg:'#e8f5ea'},
        Improved:  {c:'#f4a400', bg:'#fff2cf'},
        Stable:    {c:'#1c79d0', bg:'#e7f1ff'},
        Worsened:  {c:'#b02020', bg:'#ffe4e4'}
      };
      const cfg = map[p] || {c:'var(--text)', bg:'#f6faf7'};
      return `<span style="display:inline-block;padding:.25rem .6rem;border-radius:999px;font-size:.6rem;font-weight:700;color:${cfg.c};background:${cfg.bg};border:1px solid ${cfg.c}22;">${p}</span>`;
    };

    const rowsHtml = (iAgg.rows||[]).map(r => `
      <tr style="border-bottom:1px solid #f0f4f1;">
        <td style="padding:.65rem .8rem;border:none;font-weight:600;color:#1e3e27;">${escapeHtml(r.child_name)}</td>
        <td style="padding:.65rem .8rem;border:none;">${badge(r.initial_status)}</td>
        <td style="padding:.65rem .8rem;border:none;">${badge(r.current_status)}</td>
        <td style="padding:.65rem .8rem;border:none;">${progressChip(r.progress)}</td>
        <td style="padding:.65rem .8rem;border:none;color:#586c5d;">${r.duration_text}</td>
        <td style="padding:.65rem .8rem;border:none;">
          <button class="btn btn-sm btn-outline-secondary" style="padding:.3rem .6rem;border-radius:6px;font-size:.6rem;">View Details</button>
        </td>
      </tr>
    `).join('');

    return `
      <div class="tile" id="interventionsTile">
        <div class="d-flex align-items-start justify-content-between mb-2">
          <div>
            <div class="tile-header">
              <h5><i class="bi bi-bullseye text-success"></i> Malnutrition Intervention Outcomes</h5>
            </div>
            <p class="tile-sub">Improvement tracking and case follow-up</p>
          </div>
          <button id="nrIntervExportBtn" class="btn btn-outline-success btn-sm" style="font-size:.65rem;font-weight:700;border-radius:10px;">
            <i class="bi bi-download me-1"></i> Export Cases
          </button>
        </div>

        <div class="row g-3">
          ${kpi('Recovered', iAgg.recovered, 'Moved to normal status', '#0b7a43')}
          ${kpi('Improving', iAgg.improving, 'Positive progress', '#f4a400')}
          ${kpi('Stable/Ongoing', iAgg.stable, 'Continued intervention needed', '#1c79d0')}
        </div>

        <div class="table-responsive mt-3">
          <table class="table table-hover mb-0" style="font-size:.7rem;">
            <thead style="background:#f8faf9;border-bottom:1px solid var(--border-soft);">
              <tr>
                <th style="padding:.6rem .8rem;border:none;">Child Name</th>
                <th style="padding:.6rem .8rem;border:none;">Initial Status</th>
                <th style="padding:.6rem .8rem;border:none;">Current Status</th>
                <th style="padding:.6rem .8rem;border:none;">Progress</th>
                <th style="padding:.6rem .8rem;border:none;">Duration</th>
                <th style="padding:.6rem .8rem;border:none;">Actions</th>
              </tr>
            </thead>
            <tbody>
              ${rowsHtml || `<tr><td colspan="6" class="text-center text-muted" style="font-size:.65rem;padding:1rem;">No cases to display</td></tr>`}
            </tbody>
          </table>
        </div>
      </div>
    `;
  }

  // ---------- Event wiring ----------

  function wireEvents() {
    // Month selector
    document.getElementById('nrMonthSelect')?.addEventListener('change', (e) => {
      selectedYM = e.target.value;
      renderShell();
      wireEvents();
    });

    // Tabs
    document.querySelectorAll('.nr-tab').forEach(tab => {
      tab.addEventListener('click', (e) => {
        e.preventDefault();
        document.querySelectorAll('.nr-tab').forEach(t => {
          t.classList.remove('active');
          t.style.color = 'var(--muted)';
          t.style.borderBottom = 'none';
        });
        tab.classList.add('active');
        tab.style.color = 'var(--green)';
        tab.style.borderBottom = '2px solid var(--green)';

        const content = document.getElementById('nrTabContent');
        const which = tab.dataset.tab;

        if (which === 'growth') {
          const purokAgg = aggregateByPurok(allChildren, selectedYM);
          content.innerHTML = renderGrowthTab(purokAgg);
          document.getElementById('nrExportChartBtn')?.addEventListener('click', () => {
            printSection(document.getElementById('growthResultsTile'));
          });
        } else if (which === 'status') {
          const dist = computeStatusDistribution(allChildren, selectedYM);
          content.innerHTML = renderStatusTab(dist);
          wireStatusExport(dist);
        } else if (which === 'supp') {
          const sAgg = computeSuppAggregates(suppRecords, selectedYM);
          content.innerHTML = renderSuppTab(sAgg);
          wireSuppExports(sAgg);
        } else if (which === 'interv') {
          const iAgg = computeInterventions(recentRecords);
          content.innerHTML = renderIntervTab(iAgg);
          wireIntervExport(iAgg);
        } else {
          content.innerHTML = renderPlaceholder(which);
        }

        // Top-level export (all)
        document.getElementById('nrExportAllBtn')?.addEventListener('click', () => {
          printSection(document.querySelector('#moduleContent'));
        });
      });
    });

    // Export buttons initially (growth default)
    document.getElementById('nrExportAllBtn')?.addEventListener('click', () => {
      printSection(document.querySelector('#moduleContent'));
    });
    document.getElementById('nrExportChartBtn')?.addEventListener('click', () => {
      printSection(document.getElementById('growthResultsTile'));
    });
  }

  function wireStatusExport(dist){
    document.getElementById('nrStatusExportBtn')?.addEventListener('click', ()=>{
      const rows = [['Status','Count','Percent']];
      dist.items.forEach(it=>{
        rows.push([it.label, it.count, `${(Math.round(it.pct*1000)/10).toFixed(1)}%`]);
      });
      rows.push(['Total', dist.total, '100.0%']);
      const csv = rows.map(r=>r.map(v=>`"${String(v).replace(/"/g,'""')}"`).join(',')).join('\n');
      const blob = new Blob([csv],{type:'text/csv;charset=utf-8;'});
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `nutrition_status_${selectedYM}.csv`;
      a.click();
      URL.revokeObjectURL(url);
    });
  }

  function wireSuppExports(sAgg){
    // CSV of monthly records (kept on Export button, even if table is hidden)
    document.getElementById('nrSuppExportBtn')?.addEventListener('click', ()=>{
      const rows = [['Child Name','Supplement','Date Given','Next Due','Days Until Due','Status','Dosage','Notes']];
      (sAgg.rows||[]).forEach(r=>{
        rows.push([
          r.child_name||'',
          r.supplement_type||'',
          r.supplement_date||'',
          r.next_due_date||'',
          (r.days_until_due==null?'':r.days_until_due),
          r.status||'',
          r.dosage||'',
          (r.notes||'').replace(/\r?\n/g,' ')
        ]);
      });
      const csv = rows.map(r=>r.map(v=>`"${String(v).replace(/"/g,'""')}"`).join(',')).join('\n');
      const blob = new Blob([csv],{type:'text/csv;charset=utf-8;'});
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `supplementation_${selectedYM}.csv`;
      a.click();
      URL.revokeObjectURL(url);
    });

    // Print/PDF of overview tile
    document.getElementById('nrSuppPrintBtn')?.addEventListener('click', ()=>{
      printSection(document.getElementById('suppOverviewTile'));
    });
  }

  function wireIntervExport(iAgg){
    document.getElementById('nrIntervExportBtn')?.addEventListener('click', ()=>{
      const rows = [['Child Name','Initial Status','Current Status','Progress','Duration (months)']];
      (iAgg.rows||[]).forEach(r=>{
        rows.push([
          r.child_name,
          r.initial_status||'',
          r.current_status||'',
          r.progress||'',
          r.duration_months!=null ? r.duration_months : ''
        ]);
      });
      const csv = rows.map(r=>r.map(v=>`"${String(v).replace(/"/g,'""')}"`).join(',')).join('\n');
      const blob = new Blob([csv],{type:'text/csv;charset=utf-8;'});
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `intervention_cases_${new Date().toISOString().slice(0,10)}.csv`;
      a.click();
      URL.revokeObjectURL(url);
    });
  }

  // ---------- Helpers (UI pieces) ----------

  function summaryCard(title, value, desc, icon) {
    return `
      <div class="stat-card">
        <div class="stat-title"><i class="bi ${icon}"></i>${escapeHtml(title)}</div>
        <div class="stat-val">${escapeHtml(value)}</div>
        <div class="stat-desc">${escapeHtml(desc)}</div>
      </div>
    `;
  }

  function legendRow() {
    // Order: MAM (orange), Normal (green), SAM (red)
    return `
    <div class="d-flex align-items-center gap-3" style="flex-wrap:wrap;font-size:.62rem;font-weight:700;color:#18432b;">
      <span class="d-inline-flex align-items-center gap-2">
        <span style="width:12px;height:12px;background:#f4a400;border-radius:3px;display:inline-block;"></span> MAM
      </span>
      <span class="d-inline-flex align-items-center gap-2">
        <span style="width:12px;height:12px;background:#0b7a43;border-radius:3px;display:inline-block;"></span> Normal
      </span>
      <span class="d-inline-flex align-items-center gap-2">
        <span style="width:12px;height:12px;background:#d23d3d;border-radius:3px;display:inline-block;"></span> SAM
      </span>
    </div>
  `;
  }

  function buildPurokTable(rows) {
    if (!rows.length) {
      return `<div class="text-center py-4 text-muted" style="font-size:.65rem;">
        <i class="bi bi-inbox" style="font-size:2rem;opacity:.35;"></i>
        <p class="mt-2 mb-0">No data to display for the selected month</p>
      </div>`;
    }
    // colored pills for counts
    const pill = (val, color) => `
      <span style="
        display:inline-block;min-width:28px;text-align:center;
        padding:.2rem .5rem;border-radius:999px;font-size:.62rem;font-weight:800;
        color:#fff;background:${color};
      ">
        ${val}
      </span>`;
    return `
      <div class="table-responsive">
        <table class="table table-hover mb-0" style="font-size:.7rem;">
          <thead style="background:#f8faf9;border-bottom:1px solid var(--border-soft);">
            <tr>
              <th style="padding:.6rem .8rem;border:none;">Purok</th>
              <th style="padding:.6rem .8rem;border:none;">Total Children</th>
              <th style="padding:.6rem .8rem;border:none;">Normal</th>
              <th style="padding:.6rem .8rem;border:none;">MAM</th>
              <th style="padding:.6rem .8rem;border:none;">SAM</th>
              <th style="padding:.6rem .8rem;border:none;">Normal Rate</th>
            </tr>
          </thead>
          <tbody>
            ${rows.map(r => `
              <tr style="border-bottom:1px solid #f0f4f1;">
                <td style="padding:.65rem .8rem;border:none;">${escapeHtml(r.purok)}</td>
                <td style="padding:.65rem .8rem;border:none;">${r.total}</td>
                <td style="padding:.65rem .8rem;border:none;">${pill(r.normal, '#0b7a43')}</td>
                <td style="padding:.65rem .8rem;border:none;">${pill(r.mam,    '#f4a400')}</td>
                <td style="padding:.65rem .8rem;border:none;">${pill(r.sam,    '#d23d3d')}</td>
                <td style="padding:.65rem .8rem;border:none;">
                  <div class="d-flex align-items-center gap-2">
                    <div style="flex:1;height:6px;background:#eef4ef;border-radius:6px;overflow:hidden;">
                      <span style="display:block;height:100%;background:#0b7a43;width:${r.ratePct}%;"></span>
                    </div>
                    <span style="font-size:.66rem;font-weight:700;color:#1e3e27;">${r.ratePctText}</span>
                  </div>
                </td>
              </tr>
            `).join('')}
          </tbody>
        </table>
      </div>
    `;
  }

  function buildGroupedBarChart(rows) {
    if (!rows.length) {
      return `<div class="chart-placeholder">No trend data available</div>`;
    }

    // Chart dimensions (logical units)
    const VB = { w: 140, h: 80 };
    const pad = { l: 10, r: 4, t: 8, b: 18 };
    const cw = VB.w - pad.l - pad.r;
    const ch = VB.h - pad.t - pad.b;

    const labels = rows.map(r => r.purok);
    const series = [
      { key: 'normal', color: '#0b7a43' },
      { key: 'mam',    color: '#f4a400' },
      { key: 'sam',    color: '#d23d3d' }
    ];

    const maxVal = Math.max(1, ...rows.map(r => Math.max(r.normal, r.mam, r.sam)));
    const groupWidth = cw / labels.length;
    const barWidth = groupWidth * 0.18; // each series bar
    const gapBetweenBars = barWidth * 0.25;

    // Gridlines (y=0..max 4 ticks)
    let grid = '';
    const ticks = 4;
    for (let i = 0; i <= ticks; i++) {
      const y = pad.t + ch - (i / ticks) * ch;
      grid += `<line x1="${pad.l}" y1="${y.toFixed(2)}" x2="${(VB.w - pad.r).toFixed(2)}" y2="${y.toFixed(2)}" stroke="#e6ede9" stroke-width="0.4" vector-effect="non-scaling-stroke"></line>`;
    }

    // Bars
    let bars = '';
    labels.forEach((lab, i) => {
      const xBase = pad.l + i * groupWidth + groupWidth * 0.15;
      const vals = rows[i];
      series.forEach((s, k) => {
        const val = vals[s.key] || 0;
        const h = (val / maxVal) * ch;
        const x = xBase + k * (barWidth + gapBetweenBars);
        const y = pad.t + ch - h;
        const title = `${s.key.toUpperCase()} ${val} (${lab})`;
        bars += `<rect x="${x.toFixed(2)}" y="${y.toFixed(2)}" width="${barWidth.toFixed(2)}" height="${h.toFixed(2)}" fill="${s.color}" rx="1" ry="1"><title>${title}</title></rect>`;
      });
    });

    // X labels
    const xlabels = labels.map((lab, i) => {
      const x = pad.l + i * groupWidth + groupWidth * 0.3;
      const y = VB.h - 4;
      return `<text x="${x.toFixed(2)}" y="${y.toFixed(2)}" font-size="2.6" fill="#637668">${escapeHtml(lab)}</text>`;
    }).join('');

    return `
      <div style="width:100%;position:relative;">
        <svg class="svg-chart" viewBox="0 0 ${VB.w} ${VB.h}" preserveAspectRatio="xMidYMid meet"
             style="border:1px solid var(--border-soft);border-radius:12px;background:#fff;">
          ${grid}
          ${bars}
          ${xlabels}
        </svg>
      </div>
    `;
  }

  function renderPlaceholder(which) {
    const titles = {
      status: 'Nutrition Status',
      supp: 'Supplementation',
      interv: 'Interventions'
    };
    const icons = {
      status: 'bi-activity',
      supp: 'bi-capsule-pill',
      interv: 'bi-heart-pulse'
    };
    return `
      <div class="tile">
        <div class="tile-header">
          <h5><i class="bi ${icons[which]||'bi-info-circle'} text-success"></i> ${titles[which]||'Section'}</h5>
        </div>
        <div class="text-center py-5" style="color:var(--muted);font-size:.7rem;">
          <i class="bi bi-hourglass-split" style="font-size:2rem;opacity:.35;"></i>
          <div class="mt-2">This section will be available in the next update.</div>
        </div>
      </div>
    `;
  }

  // ---------- Helpers (data/logic) ----------

  function toYM(d) {
    const ph = new Date(d.toLocaleString('en-US', { timeZone: 'Asia/Manila' }));
    const y = ph.getFullYear();
    const m = String(ph.getMonth() + 1).padStart(2, '0');
    return `${y}-${m}`;
  }

  function buildMonthList(n = 12) {
    const out = [];
    const now = new Date();
    for (let i = 0; i < n; i++) {
      const d = new Date(now.getFullYear(), now.getMonth() - i, 1);
      const value = toYM(d);
      const label = d.toLocaleDateString('en-PH', { timeZone: 'Asia/Manila', month: 'long', year: 'numeric' });
      out.push({ value, label });
    }
    return out;
  }

  function isInSelectedMonth(child, ym) {
    const d = child.last_weighing_date;
    if (!d || d === 'Never') return false;
    try { return String(d).slice(0, 7) === ym; } catch { return false; }
  }

  function aggregateByPurok(children, ym) {
    // filter to records weighed in selected month; if none match, fall back to latest status
    const filtered = children.filter(c => isInSelectedMonth(c, ym));
    const arr = (filtered.length ? filtered : children).slice();

    const byPurok = new Map();
    arr.forEach(c => {
      const purok = c.purok_name || 'Not Set';
      const bucket = byPurok.get(purok) || { purok, total: 0, normal: 0, mam: 0, sam: 0 };
      bucket.total += 1;
      const s = c.nutrition_status || '';
      if (s === 'NOR') bucket.normal += 1;
      else if (s === 'MAM') bucket.mam += 1;
      else if (s === 'SAM') bucket.sam += 1;
      byPurok.set(purok, bucket);
    });

    const out = Array.from(byPurok.values()).sort((a, b) => a.purok.localeCompare(b.purok));
    out.forEach(r => {
      const rate = r.total ? (r.normal / r.total) * 100 : 0;
      r.ratePct = Math.round(rate);
      r.ratePctText = `${r.ratePct.toFixed(0)}%`;
    });
    return out;
  }

  function computeSummary(children, recent, ym) {
    const totalChildren = children.length;

    const inMonth = children.filter(c => isInSelectedMonth(c, ym));
    const base = (inMonth.length ? inMonth : children);

    const normal = base.filter(c => c.nutrition_status === 'NOR').length;
    const mam = base.filter(c => c.nutrition_status === 'MAM').length;
    const sam = base.filter(c => c.nutrition_status === 'SAM').length;
    const uw  = base.filter(c => c.nutrition_status === 'UW').length;

    const denom = base.length || 1;
    const normalRate = (normal / denom) * 100;
    const interventionCases = mam + sam + uw;

    // Recovery Rate: among children whose latest record is in selected month
    const { improved, pairs } = computeImprovementForMonth(recent || [], ym);
    const recoveryRate = pairs ? (improved / pairs) * 100 : null;

    return {
      totalChildren,
      normalRateText: `${(Math.round(normalRate * 10) / 10).toFixed(1)}%`,
      interventionCases,
      recoveryRateText: recoveryRate == null ? '—' : `${(Math.round(recoveryRate * 10) / 10).toFixed(1)}%`
    };
  }

  function computeImprovementForMonth(recent, ym) {
    const byChild = new Map();
    (recent || []).forEach(r => {
      const k = r.child_name; if (!k) return;
      if (!byChild.has(k)) byChild.set(k, []);
      const arr = byChild.get(k);
      if (arr.length < 2) arr.push(r); // recent feed is DESC
    });

    let improved = 0, pairs = 0;
    const mal = new Set(['SAM', 'MAM', 'UW']);
    byChild.forEach(arr => {
      if (arr.length < 2) return;
      const [latest, prev] = arr; // latest first
      if (!latest.weighing_date) return;
      const latestYM = String(latest.weighing_date).slice(0, 7);
      if (latestYM !== ym) return;
      if (latest.status_code && prev.status_code) {
        pairs++;
        if (mal.has(prev.status_code) && latest.status_code === 'NOR') improved++;
      }
    });
    return { improved, pairs };
  }

  // Compute status distribution (like screenshot)
  function computeStatusDistribution(children, ym){
    const inMonth = children.filter(c => isInSelectedMonth(c, ym));
    const base = (inMonth.length ? inMonth : children).slice();

    let NOR=0, MAM=0, SAM=0, UW=0, ST=0, OW=0, OB=0;
    base.forEach(c=>{
      switch(c.nutrition_status){
        case 'NOR': NOR++; break;
        case 'MAM': MAM++; break;
        case 'SAM': SAM++; break;
        case 'UW':  UW++;  break;
        case 'ST':  ST++;  break;
        case 'OW':  OW++;  break;
        case 'OB':  OB++;  break;
      }
    });
    const OVER = OW + OB;

    const items = [
      { code:'NOR', label:'Normal',      color:'#0b7a43', count:NOR },
      { code:'MAM', label:'MAM',         color:'#f4a400', count:MAM },
      { code:'SAM', label:'SAM',         color:'#d23d3d', count:SAM },
      { code:'UW',  label:'Underweight', color:'#ffb84d', count:UW },
      { code:'ST',  label:'Stunted',     color:'#ff6b6b', count:ST },
      { code:'OVR', label:'Overweight',  color:'#8e44ad', count:OVER }
    ];
    const total = items.reduce((s,i)=>s+i.count,0);
    items.forEach(i=> i.pct = total? (i.count/total) : 0);

    return { total, items };
  }

  // Donut chart builder with labels + percents
  function buildDonutChart(dist){
    const VB = { w: 120, h: 80 };
    const cx = 40, cy = 40;
    const R = 26, r = 15;
    let angle = -Math.PI/2; // start at top
    const arcs = [];
    const labels = [];

    function arcPath(cx,cy,R,r,start,end){
      const large = (end-start) > Math.PI ? 1 : 0;
      const x0 = cx + R*Math.cos(start), y0 = cy + R*Math.sin(start);
      const x1 = cx + R*Math.cos(end),   y1 = cy + R*Math.sin(end);
      const x2 = cx + r*Math.cos(end),   y2 = cy + r*Math.sin(end);
      const x3 = cx + r*Math.cos(start), y3 = cy + r*Math.sin(start);
      return [
        `M ${x0.toFixed(3)} ${y0.toFixed(3)}`,
        `A ${R} ${R} 0 ${large} 1 ${x1.toFixed(3)} ${y1.toFixed(3)}`,
        `L ${x2.toFixed(3)} ${y2.toFixed(3)}`,
        `A ${r} ${r} 0 ${large} 0 ${x3.toFixed(3)} ${y3.toFixed(3)}`,
        'Z'
      ].join(' ');
    }

    dist.items.filter(i=>i.count>0).forEach(i=>{
      const sweep = i.pct * Math.PI*2;
      const start = angle;
      const end = angle + sweep;
      angle = end;

      arcs.push(`<path d="${arcPath(cx,cy,R,r,start,end)}" fill="${i.color}"><title>${i.label}: ${(i.pct*100).toFixed(1)}%</title></path>`);

      // label on ring centroid
      const mid = (start+end)/2;
      const lx = cx + (R+10)*Math.cos(mid);
      const ly = cy + (R+10)*Math.sin(mid);
      const anchor = (Math.cos(mid) >= 0) ? 'start' : 'end';
      labels.push(`<text x="${lx.toFixed(2)}" y="${ly.toFixed(2)}" font-size="3" fill="${i.color}" text-anchor="${anchor}" dominant-baseline="middle">${escapeHtml(i.label)} ${(i.pct*100).toFixed(0)}%</text>`);
    });

    // Center text (largest slice label)
    const top = dist.items.slice().sort((a,b)=>b.count-a.count)[0] || {label:'—', pct:0};
    const centerText = `
      <text x="${cx}" y="${cy-2}" font-size="4.8" text-anchor="middle" fill="#0b7a43" font-weight="700">${(top.pct*100).toFixed(0)}%</text>
      <text x="${cx}" y="${cy+4.2}" font-size="3" text-anchor="middle" fill="#5f7464">${escapeHtml(top.label)}</text>
    `;

    return `
      <div style="width:100%;position:relative;">
        <svg class="svg-chart" viewBox="0 0 ${VB.w} ${VB.h}" preserveAspectRatio="xMidYMid meet"
             style="border:1px solid var(--border-soft);border-radius:12px;background:#fff;">
          ${arcs.join('')}
          ${centerText}
          ${labels.join('')}
        </svg>
      </div>
    `;
  }

  // Right-side detailed rows with progress bars
  function buildStatusBreakdown(dist){
    const rows = dist.items.filter(i=>i.count>0);
    if (!rows.length) return `<div class="text-muted" style="font-size:.65rem;">No data available</div>`;

    const barRow = (it)=>{
      const pct100 = (it.pct*100);
      return `
        <div class="mb-2" role="group" aria-label="${escapeHtml(it.label)}">
          <div class="d-flex align-items-center justify-content-between mb-1">
            <div class="d-flex align-items-center gap-2">
              <span style="width:10px;height:10px;border-radius:3px;background:${it.color};display:inline-block;"></span>
              <span style="font-size:.72rem;font-weight:700;color:#18432b;">${escapeHtml(it.label)}</span>
            </div>
            <div style="font-size:.62rem;color:#6a7a6d;">${it.count} ${it.count===1?'child':'children'}</div>
          </div>
          <div style="height:8px;background:#ecf3ee;border-radius:10px;overflow:hidden;">
            <span style="display:block;height:100%;background:${it.color};width:${pct100.toFixed(1)}%;"></span>
          </div>
          <div style="font-size:.58rem;color:#6a7a6d;margin-top:.25rem;">${pct100.toFixed(1)}% of total</div>
        </div>
      `;
    };

    return rows.map(barRow).join('');
  }

  // Supplementation breakdown (re-uses the same style)
  function buildSuppBreakdown(dist){
    return buildStatusBreakdown(dist);
  }

  // Supplementation aggregations for selected month
  function computeSuppAggregates(all, ym){
    const norm = (t) => {
      const s = String(t||'').toLowerCase();
      if (s.includes('vit')) return 'Vitamin A';
      if (s.includes('iron')) return 'Iron';
      if (s.includes('deworm')) return 'Deworming';
      return (t||'');
    };
    const rows = (all||[]).filter(r => {
      const d = r.supplement_date ? String(r.supplement_date).slice(0,7) : '';
      return d === ym;
    }).sort((a,b)=>String(b.supplement_date||'').localeCompare(String(a.supplement_date||'')));

    const byType = {'Vitamin A':0,'Iron':0,'Deworming':0};
    let overdue = 0;
    let upcomingWithin30 = 0;
    const childSet = new Set();

    rows.forEach(r=>{
      const t = norm(r.supplement_type);
      if (byType[t]!=null) byType[t] += 1;
      if (r.status === 'overdue') overdue++;
      if (Number.isFinite(r.days_until_due) && r.days_until_due >= 0 && r.days_until_due <= 30) upcomingWithin30++;
      if (r.child_id) childSet.add(r.child_id);
    });

    return {
      total: rows.length,
      rows,
      byType,
      overdue,
      upcomingWithin30,
      uniqueChildren: childSet.size
    };
  }

  // Compute Interventions aggregates from the recent feed (desc by date)
  function computeInterventions(recent){
    const mal = new Set(['SAM','MAM','UW']);
    const sevRank = code => (code==='SAM'?3:(code==='MAM'?2:(code==='UW'?1:(code==='NOR'?0:-1))));

    const map = new Map(); // child -> [latest, prev]
    (recent||[]).forEach(r=>{
      const k = r.child_name || `#${r.child_id||0}`;
      if(!map.has(k)) map.set(k, []);
      const arr = map.get(k);
      if (arr.length < 2) arr.push(r);
    });

    const rows = [];
    let recovered=0, improving=0, stable=0;
    map.forEach((arr, child)=>{
      if (arr.length < 2) return;
      const latest = arr[0], prev = arr[1];
      const a = latest.status_code || '';
      const b = prev.status_code || '';

      // we only consider those involved in malnutrition pathway
      if (!(mal.has(a) || mal.has(b) || a==='NOR' || b==='NOR')) return;

      let progress = 'Stable';
      if (mal.has(b) && a === 'NOR') progress = 'Recovered';
      else if (mal.has(b) && mal.has(a)) {
        const prevSev = sevRank(b), curSev = sevRank(a);
        if (curSev < prevSev) progress = 'Improved';
        else if (curSev > prevSev) progress = 'Worsened';
        else progress = 'Stable';
      } else if (b==='NOR' && mal.has(a)) {
        progress = 'Worsened';
      }

      if (progress === 'Recovered') recovered++;
      else if (progress === 'Improved') improving++;
      else if (progress === 'Stable') stable++;

      const dur = monthsBetween(prev.weighing_date, latest.weighing_date);
      rows.push({
        child_name: child,
        initial_status: b || '',
        current_status: a || '',
        progress,
        duration_months: dur,
        duration_text: dur != null ? `${dur} month${dur===1?'':'s'}` : '—'
      });
    });

    // Sort: Recovered -> Improved -> Stable -> Worsened
    const pOrder = {Recovered:0, Improved:1, Stable:2, Worsened:3};
    rows.sort((x,y)=>{
      const dx = (x.progress in pOrder)?pOrder[x.progress]:9;
      const dy = (y.progress in pOrder)?pOrder[y.progress]:9;
      if (dx!==dy) return dx-dy;
      return x.child_name.localeCompare(y.child_name);
    });

    return { recovered, improving, stable, rows };
  }

  function monthsBetween(d1, d2){
    if (!d1 || !d2) return null;
    try{
      const a = new Date(d1+'T00:00:00');
      const b = new Date(d2+'T00:00:00');
      let months = (b.getFullYear()-a.getFullYear())*12 + (b.getMonth()-a.getMonth());
      if (b.getDate() < a.getDate()) months--;
      return Math.max(0, months);
    }catch{ return null; }
  }

  function prettyStatus(code){
    const map = {
      NOR: 'Normal',
      MAM: 'MAM',
      SAM: 'SAM',
      UW:  'Underweight',
      OW:  'Overweight',
      OB:  'Obese',
      ST:  'Stunted',
      UNSET:'Not Set'
    };
    return map[code] || code || '—';
  }

  // Key insights box (status)
  function buildStatusInsights(dist){
    const find = codeOrLabel => {
      return dist.items.find(i => i.code===codeOrLabel || i.label===codeOrLabel) || {count:0,pct:0};
    };
    const nor = find('NOR');
    const mam = find('MAM');
    const sam = find('SAM');
    const uw  = find('UW');
    const st  = find('ST');
    const atRiskCount = mam.count + sam.count + uw.count + st.count;

    return `
      <div style="background:#eaf5ee;border:1px solid #d3e8d9;border-radius:12px;padding:.8rem 1rem;">
        <div style="font-size:.72rem;font-weight:800;color:#18432b;margin-bottom:.35rem;">Key Insights</div>
        <ul style="margin:0;padding-left:1rem;font-size:.65rem;color:#1e3e27;">
          <li><span style="color:#0b7a43;">${(nor.pct*100).toFixed(1)}%</span> of children have normal nutrition status</li>
          <li><span style="color:#f4a400;">${(mam.pct*100).toFixed(1)}%</span> classified as MAM - moderate acute malnutrition</li>
          <li><span style="color:#d23d3d;">${(sam.pct*100).toFixed(1)}%</span> classified as SAM - severe acute malnutrition</li>
          <li>Intervention focus needed for <strong>${atRiskCount}</strong> children total</li>
        </ul>
      </div>
    `;
  }

  // Print a specific section (basic PDF via print dialog)
  function printSection(el) {
    if (!el) return;
    const win = window.open('', '_blank', 'width=1024,height=768');
    const styles = Array.from(document.querySelectorAll('link[rel="stylesheet"], style'))
      .map(s => s.outerHTML).join('\n');
    win.document.write(`
      <html>
        <head>
          <title>Nutrition Reports</title>
          ${styles}
          <style>
            body { font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; padding: 16px; }
            .tile { box-shadow:none !important; }
            .svg-chart { width:100%; height:auto; }
          </style>
        </head>
        <body>${el.outerHTML}</body>
      </html>
    `);
    win.document.close();
    win.focus();
    setTimeout(() => { win.print(); win.close(); }, 350);
  }
}



// Fixed auto-calculation that actually works
// Fixed auto-calculation consistent with UI and database structure
// Fixed auto-calculation that matches your exact UI
function autoCalculateWFLAssessment() {
  const weightInput = document.getElementById('childWeight');
  const heightInput = document.getElementById('childHeight');
  const assessmentInput = document.getElementById('nutritionStatus');
  const assessmentIdInput = document.getElementById('nutritionStatusId');
  
  if (!weightInput || !heightInput || !assessmentInput) {
    console.error('Required input fields not found');
    return;
  }
  
  const weight = parseFloat(weightInput.value);
  const height = parseFloat(heightInput.value);
  
  console.log('Auto-calculating with weight:', weight, 'height:', height);
  
  if (weight && height && weight > 0 && height > 0) {
    // Show calculating status - exactly matching your form field style
    assessmentInput.value = 'Calculating...';
    assessmentInput.style.cssText = `
      font-size: .72rem;
      padding: .65rem .85rem;
      border: 1px solid var(--border-soft);
      border-radius: 8px;
      background: var(--surface);
      color: var(--muted);
      font-style: italic;
      font-weight: 500;
    `;
    
    // Fixed API URL
    const url = `${api.nutrition}?classify=1&weight=${weight}&length=${height}`;
    console.log('Calling API:', url);
    
    fetch(url, {
      method: 'GET',
      headers: {
        'X-CSRF-Token': window.__BNS_CSRF,
        'X-Requested-With': 'XMLHttpRequest'
      }
    })
    .then(response => {
      console.log('API response status:', response.status);
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }
      return response.json();
    })
    .then(data => {
      console.log('Classification result:', data);
      
      if (data.success && data.status_code) {
        // Success - use your exact badge styling
        const statusInfo = getConsistentStatusDisplay(data.status_code, data.status_description);
        
        assessmentInput.value = statusInfo.displayText;
        assessmentInput.style.cssText = `
          font-size: .72rem;
          padding: .65rem .85rem;
          border: 1px solid var(--border-soft);
          border-radius: 8px;
          background: ${statusInfo.background};
          color: ${statusInfo.color};
          font-style: normal;
          font-weight: 600;
        `;
        
        // Store the status ID for form submission
        if (assessmentIdInput && data.status_id) {
          assessmentIdInput.value = data.status_id;
        }
        
        console.log('Assessment calculated successfully:', statusInfo.displayText);
      } else {
        // Error state - consistent with your error styling
        assessmentInput.value = 'Unable to calculate';
        assessmentInput.style.cssText = `
          font-size: .72rem;
          padding: .65rem .85rem;
          border: 1px solid #dc3545;
          border-radius: 8px;
          background: #fff5f5;
          color: #dc3545;
          font-style: italic;
          font-weight: normal;
        `;
        
        if (assessmentIdInput) {
          assessmentIdInput.value = '';
        }
        
        console.error('API returned error:', data);
      }
    })
    .catch(error => {
      console.error('Error calculating assessment:', error);
      // Error state matching your existing "Calculation error" style
      assessmentInput.value = 'Calculation error';
      assessmentInput.style.cssText = `
        font-size: .72rem;
        padding: .65rem .85rem;
        border: 1px solid #dc3545;
        border-radius: 8px;
        background: #fff5f5;
        color: #dc3545;
        font-style: italic;
        font-weight: normal;
      `;
      
      if (assessmentIdInput) {
        assessmentIdInput.value = '';
      }
    });
  } else {
    // Clear state - back to placeholder styling exactly like your form
    assessmentInput.value = 'Auto-calculated when weight and height are entered';
    assessmentInput.style.cssText = `
      font-size: .72rem;
      padding: .65rem .85rem;
      border: 1px solid var(--border-soft);
      border-radius: 8px;
      background: #f8f9fa;
      color: var(--muted);
      font-style: italic;
      font-weight: normal;
    `;
    
    if (assessmentIdInput) {
      assessmentIdInput.value = '';
    }
  }
}

// Helper function using your exact badge colors from your UI
function getConsistentStatusDisplay(statusCode, statusDescription) {
  const statusMap = {
    'NOR': { 
      displayText: 'Normal (NOR)', 
      color: '#15692d', 
      background: '#dff4e4' 
    },
    'MAM': { 
      displayText: 'Moderate Acute Malnutrition (MAM)', 
      color: '#845900', 
      background: '#ffebc9' 
    },
    'SAM': { 
      displayText: 'Severe Acute Malnutrition (SAM)', 
      color: '#b02020', 
      background: '#ffdcdc' 
    },
    'UW': { 
      displayText: 'Underweight (UW)', 
      color: '#7c5100', 
      background: '#fff0d6' 
    },
    'OW': { 
      displayText: 'Overweight (OW)', 
      color: '#105694', 
      background: '#e1f1ff' 
    },
    'OB': { 
      displayText: 'Obese (OB)', 
      color: '#105694', 
      background: '#e1f1ff' 
    },
    'ST': { 
      displayText: 'Stunted (ST)', 
      color: '#b02020', 
      background: '#ffdcdc' 
    }
  };
  
  return statusMap[statusCode] || { 
    displayText: statusDescription || statusCode, 
    color: 'var(--text)', 
    background: '#f8f9fa' 
  };
}

// Helper function to get consistent status display based on your database
function getStatusDisplay(statusCode, statusDescription) {
  const statusMap = {
    'NOR': { 
      text: 'Normal (NOR)', 
      color: '#15692d', 
      background: '#dff4e4' 
    },
    'MAM': { 
      text: 'Moderate Acute Malnutrition (MAM)', 
      color: '#845900', 
      background: '#ffebc9' 
    },
    'SAM': { 
      text: 'Severe Acute Malnutrition (SAM)', 
      color: '#b02020', 
      background: '#ffdcdc' 
    },
    'OW': { 
      text: 'Overweight (OW)', 
      color: '#105694', 
      background: '#e1f1ff' 
    },
    'OB': { 
      text: 'Obese (OB)', 
      color: '#105694', 
      background: '#e1f1ff' 
    },
    'ST': { 
      text: 'Stunted (ST)', 
      color: '#b02020', 
      background: '#ffdcdc' 
    },
    'UW': { 
      text: 'Underweight (UW)', 
      color: '#7c5100', 
      background: '#fff0d6' 
    }
  };
  
  return statusMap[statusCode] || { 
    text: statusDescription || statusCode, 
    color: 'var(--text)', 
    background: '#f8f9fa' 
  };
}

// Helper: lock the weighing date to today (PH time) and prevent changes
function lockWeighingDateToToday() {
  const el = document.getElementById('weighingDate');
  if (!el) return;
  const today = new Date().toLocaleDateString('en-CA', { timeZone: 'Asia/Manila' });
  el.value = today;
  el.min = today;
  el.max = today;
  // If user tries to change (via typing or picker), snap back to today
  const enforce = () => { if (el.value !== today) el.value = today; };
  el.addEventListener('change', enforce);
  el.addEventListener('input', enforce);
}

// Optional: Call locker after any dynamic render that includes #weighingDate
document.addEventListener('DOMContentLoaded', ()=> {
  // if date input exists on initial load
  lockWeighingDateToToday();
});

// Proper event listener setup that actually works
function setupAutoCalculation() {
  console.log('Setting up auto-calculation...');
  
  // Wait for DOM to be ready
  setTimeout(() => {
    const weightInput = document.getElementById('childWeight');
    const heightInput = document.getElementById('childHeight');
    
    if (!weightInput || !heightInput) {
      console.error('Weight or height input not found!');
      return;
    }
    
    console.log('Found weight and height inputs, adding event listeners...');
    
    // Remove any existing listeners to prevent duplicates
    weightInput.removeEventListener('input', handleWeightHeightChange);
    heightInput.removeEventListener('input', handleWeightHeightChange);
    weightInput.removeEventListener('blur', autoCalculateWFLAssessment);
    heightInput.removeEventListener('blur', autoCalculateWFLAssessment);
    
    // Add new event listeners
    weightInput.addEventListener('input', handleWeightHeightChange);
    heightInput.addEventListener('input', handleWeightHeightChange);
    weightInput.addEventListener('blur', autoCalculateWFLAssessment);
    heightInput.addEventListener('blur', autoCalculateWFLAssessment);
    
    console.log('Event listeners added successfully');
  }, 200);
}

// Debounced handler for input events
let calculationTimeout;
function handleWeightHeightChange() {
  console.log('Weight or height changed, scheduling calculation...');
  
  // Clear existing timeout
  if (calculationTimeout) {
    clearTimeout(calculationTimeout);
  }
  
  // Schedule calculation after user stops typing
  calculationTimeout = setTimeout(() => {
    console.log('Executing delayed calculation...');
    autoCalculateWFLAssessment();
  }, 500);
}

// Updated form rendering with consistent UI
// Updated weighing module that loads all records initially
function renderWeighingModule(label) {
  showLoading(label);
  setTimeout(() => {
    moduleContent.innerHTML = `
      <div class="fade-in">
        <!-- Page Header -->
        <div class="page-header">
          <div class="page-header-icon">
            <i class="bi bi-clipboard2-data"></i>
          </div>
          <div class="page-header-text">
            <h1>Nutrition Data Entry</h1>
            <p>Record comprehensive nutrition and growth measurements</p>
          </div>
        </div>

        <!-- Form Container -->
        <div class="form-container">
          <!-- Child Information Section -->
          <div class="form-section">
            <div class="form-section-header">
              <div class="form-section-icon child">
                <i class="bi bi-person-plus"></i>
              </div>
              <h3 class="form-section-title">Child Information</h3>
            </div>
            <div class="form-grid">
              <div class="form-group">
                <label class="form-label">Select Child</label>
                <select class="form-select" id="childSelect" required>
                  <option value="">Select a child</option>
                </select>
              </div>
              <div class="form-group">
                <label class="form-label">Full Name</label>
                <input type="text" class="form-control" id="childFullName" readonly>
              </div>
              <div class="form-group">
                <label class="form-label">Sex</label>
                <input type="text" class="form-control" id="childSex" readonly>
              </div>
              <div class="form-group">
                <label class="form-label">Birth Date</label>
                <input type="text" class="form-control" id="childBirthDate" readonly>
              </div>
              <div class="form-group">
                <label class="form-label">Current Age (months)</label>
                <input type="text" class="form-control" id="childAge" readonly>
              </div>
              <div class="form-group">
                <label class="form-label">Mother/Caregiver</label>
                <input type="text" class="form-control" id="motherName" readonly>
              </div>
            </div>
          </div>

          <!-- New Weighing Record Section -->
          <div class="form-section">
            <div class="form-section-header">
              <div class="form-section-icon" style="background:#e8f5ea;color:#0b7a43;">
                <i class="bi bi-clipboard-data"></i>
              </div>
              <h3 class="form-section-title">📊 New Weighing Record</h3>
            </div>
            <p style="font-size:.65rem;color:var(--muted);margin:0 0 1rem;font-weight:500;">Record new measurement data for the selected child</p>
            
            <div class="form-grid">
              <div class="form-group">
                <label class="form-label">Date of Weighing *</label>
                <div class="date-input">
                  <input type="date" class="form-control" id="weighingDate" required>
                </div>
              </div>
              <div class="form-group">
                <label class="form-label">Weight (kg) *</label>
                <input type="number" step="0.1" class="form-control" id="childWeight" 
                       placeholder="Enter weight in kg" required>
              </div>
              <div class="form-group">
                <label class="form-label">Height/Length (cm) *</label>
                <input type="number" step="0.1" class="form-control" id="childHeight" 
                       placeholder="Enter height in cm" required>
              </div>
              <div class="form-group">
                <label class="form-label">WFL/H Assessment</label>
                <input type="text" class="form-control" id="nutritionStatus" 
                       placeholder="Auto-calculated when weight and height are entered" 
                       readonly>
                <input type="hidden" id="nutritionStatusId" name="wfl_ht_status_id">
              </div>
              <div class="form-group">
                <label class="form-label">Remarks</label>
                <textarea class="form-control" id="remarks" 
                          placeholder="Additional notes or observations" rows="3"></textarea>
              </div>
            </div>

            <div class="d-flex justify-content-end mt-3">
              <button class="btn btn-success" id="saveNutritionRecord" 
                      style="font-size:.7rem;font-weight:600;padding:.6rem 1.2rem;border-radius:8px;box-shadow:0 2px 6px -2px rgba(20,104,60,.5);">
                <i class="bi bi-plus-lg me-1"></i> Add Weighing Record
              </button>
            </div>
          </div>

          <!-- All Previous Records Section - Load immediately -->
          <div class="form-section">
            <div class="form-section-header">
              <div class="form-section-icon" style="background:#e1f1ff;color:#1c79d0;">
                <i class="bi bi-clipboard-data"></i>
              </div>
              <h3 class="form-section-title">📋 All Weighing Records</h3>
            </div>
            <p style="font-size:.65rem;color:var(--muted);margin:0 0 1rem;font-weight:500;">All nutrition measurement records in the system</p>
            
            <!-- Search and Filter -->
            <div class="row g-3 mb-3">
              <div class="col-md-4">
                <div class="position-relative">
                  <i class="bi bi-search position-absolute" style="left:.8rem;top:50%;transform:translateY(-50%);font-size:.75rem;color:var(--muted);"></i>
                  <input type="text" class="form-control" id="recordsSearchInput" 
                         placeholder="Search child name..." 
                         style="font-size:.7rem;padding:.6rem .8rem .6rem 2.2rem;border:1px solid var(--border-soft);border-radius:8px;background:var(--surface);">
                </div>
              </div>
              <div class="col-md-3">
                <select class="form-select" id="statusFilter" 
                        style="font-size:.7rem;padding:.6rem .8rem;border:1px solid var(--border-soft);border-radius:8px;background:var(--surface);">
                  <option value="">All Status</option>
                  <option value="NOR">Normal (NOR)</option>
                  <option value="MAM">MAM</option>
                  <option value="SAM">SAM</option>
                  <option value="UW">Underweight</option>
                  <option value="OW">Overweight</option>
                  <option value="OB">Obese</option>
                  <option value="ST">Stunted</option>
                </select>
              </div>
              <div class="col-md-3">
                <input type="date" class="form-control" id="dateFilter" 
                       placeholder="Filter by date"
                       style="font-size:.7rem;padding:.6rem .8rem;border:1px solid var(--border-soft);border-radius:8px;background:var(--surface);">
              </div>
              <div class="col-md-2">
                <button class="btn btn-outline-secondary w-100" onclick="clearFilters()" 
                        style="font-size:.65rem;font-weight:600;padding:.6rem;border-radius:8px;">
                  Clear Filters
                </button>
              </div>
            </div>
            
            <div class="table-responsive" id="allRecordsContainer">
              <div class="text-center py-3" style="color:var(--muted);font-size:.65rem;">
                <div class="spinner-border spinner-border-sm me-2" role="status" style="width:1rem;height:1rem;border-width:2px;"></div>
                Loading all weighing records...
              </div>
            </div>
          </div>

          <!-- Nutrition Classification Guide -->
          <div class="form-section" style="background:#f0f8f1;border:1px solid #d3e8d9;">
            <div class="form-section-header">
              <div class="form-section-icon" style="background:#e8f5ea;color:#0b7a43;">
                <i class="bi bi-info-circle"></i>
              </div>
              <h3 class="form-section-title">Nutrition Classification Guide</h3>
            </div>
            
            <div style="display: flex; flex-direction: column; gap: 0.4rem;">
              <div style="display: flex; align-items: center; gap: 0.7rem;">
                <span class="badge-status badge-NOR" style="min-width:70px;text-align:center;">Normal</span>
                <span style="font-size:.65rem;color:#15692d;font-weight:600;">Healthy weight for age and height</span>
              </div>
              <div style="display: flex; align-items: center; gap: 0.7rem;">
                <span class="badge-status badge-MAM" style="min-width:70px;text-align:center;">MAM</span>
                <span style="font-size:.65rem;color:#845900;font-weight:600;">Moderate Acute Malnutrition</span>
              </div>
              <div style="display: flex; align-items: center; gap: 0.7rem;">
                <span class="badge-status badge-SAM" style="min-width:70px;text-align:center;">SAM</span>
                <span style="font-size:.65rem;color:#b02020;font-weight:600;">Severe Acute Malnutrition</span>
              </div>
              <div style="display: flex; align-items: center; gap: 0.7rem;">
                <span class="badge-status badge-UW" style="min-width:70px;text-align:center;">Underweight</span>
                <span style="font-size:.65rem;color:#7c5100;font-weight:600;">Below normal weight for age</span>
              </div>
              <div style="display: flex; align-items: center; gap: 0.7rem;">
                <span class="badge-status badge-OW" style="min-width:70px;text-align:center;">Overweight</span>
                <span style="font-size:.65rem;color:#105694;font-weight:600;">Above normal weight</span>
              </div>
              <div style="display: flex; align-items: center; gap: 0.7rem;">
                <span class="badge-status badge-OB" style="min-width:70px;text-align:center;">Obese</span>
                <span style="font-size:.65rem;color:#105694;font-weight:600;">Significantly above normal weight</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    `;

    // Initialize functionality
    initializeNutritionDataEntry();
    
    // Load all records immediately when page loads
    loadAllWeighingRecords();
  }, 100);
}

// Updated initialization function
function initializeNutritionDataEntry() {
  console.log('Initializing nutrition data entry...');
  
  // Load children for selection
  loadChildrenForSelection();
  
  // Set today's date as default (Philippine timezone)
  const today = new Date().toLocaleDateString('en-CA', {timeZone: 'Asia/Manila'});
  const d = document.getElementById('weighingDate');
  if (d) {
    d.value = today;
    d.min = today;
    d.max = today;
  }
  
  // Setup other handlers
  setupChildSelectionHandler();
  setupSaveRecordHandler();
  
  // Setup auto-calculation (most important - do this last)
  setupAutoCalculation();
}

// Debug function to test if auto-calculation works
function testAutoCalculation() {
  console.log('Testing auto-calculation...');
  
  // Set test values
  const weightInput = document.getElementById('childWeight');
  const heightInput = document.getElementById('childHeight');
  
  if (weightInput && heightInput) {
    weightInput.value = '10.5';
    heightInput.value = '75';
    
    console.log('Test values set, triggering calculation...');
    autoCalculateWFLAssessment();
  } else {
    console.error('Could not find weight/height inputs for testing');
  }
}

// Load previous records for selected child - Updated to fetch real data from nutrition_records
function loadPreviousRecords(childId) {
  const container = document.getElementById('previousRecordsContainer');
  
  // Show loading state with consistent UI styling
  container.innerHTML = `
    <div class="text-center py-3" style="color:var(--muted);font-size:.65rem;">
      <div class="spinner-border spinner-border-sm me-2" role="status" style="width:1rem;height:1rem;border-width:2px;"></div>
      Loading previous records...
    </div>
  `;
  
  // Fetch actual nutrition records for this child from the API
  fetch(`${api.nutrition}?child_records=1&child_id=${childId}`, {
    method: 'GET',
    headers: {
      'X-CSRF-Token': window.__BNS_CSRF,
      'X-Requested-With': 'XMLHttpRequest',
      'Accept': 'application/json'
    }
  })
  .then(response => {
    if (!response.ok) {
      throw new Error(`HTTP ${response.status}`);
    }
    return response.json();
  })
  .then(data => {
    console.log('Previous records data:', data);
    
    if (data.success && data.records && data.records.length > 0) {
      const records = data.records;
      
      // Render table with consistent UI styling
      container.innerHTML = `
        <table class="table table-hover mb-0" style="font-size:.7rem;">
          <thead style="background:#f8faf9;border-bottom:1px solid var(--border-soft);">
            <tr>
              <th style="padding:.75rem .8rem;font-size:.65rem;font-weight:700;color:#344f3a;border:none;">Date</th>
              <th style="padding:.75rem .8rem;font-size:.65rem;font-weight:700;color:#344f3a;border:none;">Age (months)</th>
              <th style="padding:.75rem .8rem;font-size:.65rem;font-weight:700;color:#344f3a;border:none;">Weight (kg)</th>
              <th style="padding:.75rem .8rem;font-size:.65rem;font-weight:700;color:#344f3a;border:none;">Height (cm)</th>
              <th style="padding:.75rem .8rem;font-size:.65rem;font-weight:700;color:#344f3a;border:none;">Status</th>
              <th style="padding:.75rem .8rem;font-size:.65rem;font-weight:700;color:#344f3a;border:none;">Remarks</th>
            </tr>
          </thead>
          <tbody>
            ${records.map(record => renderPreviousRecordRow(record)).join('')}
          </tbody>
        </table>
      `;
    } else {
      // No records found - consistent with your UI
      container.innerHTML = `
        <div class="text-center py-4" style="color:var(--muted);font-size:.65rem;">
          <i class="bi bi-clipboard-x" style="font-size:2rem;opacity:0.3;"></i>
          <p style="margin:.5rem 0 0;">No previous records found for this child</p>
        </div>
      `;
    }
  })
  .catch(error => {
    console.error('Error loading previous records:', error);
    
    // Error state - consistent with your error styling
    container.innerHTML = `
      <div class="text-center py-4" style="color:#dc3545;font-size:.65rem;">
        <i class="bi bi-exclamation-triangle" style="font-size:2rem;opacity:0.5;color:#dc3545;"></i>
        <p style="margin:.5rem 0 0;color:#dc3545;">Error loading previous records</p>
      </div>
    `;
  });
}

// Helper function to render individual record rows with consistent UI
function renderPreviousRecordRow(record) {
  // Format date to be consistent with your UI
  const weighingDate = record.weighing_date ? 
    new Date(record.weighing_date).toLocaleDateString('en-PH', {
      timeZone: 'Asia/Manila',
      year: 'numeric',
      month: 'numeric',
      day: 'numeric'
    }) : 'N/A';
  
  // Get status badge with consistent styling
  const statusBadge = record.status_code ? 
    `<span class="badge-status badge-${record.status_code}" style="font-size:.55rem;font-weight:600;padding:.25rem .5rem;border-radius:8px;">${record.status_code}</span>` : 
    '<span style="color:var(--muted);font-size:.6rem;">N/A</span>';
  
  return `
    <tr style="border-bottom:1px solid #f0f4f1;">
      <td style="padding:.8rem;border:none;color:#586c5d;font-weight:500;">${weighingDate}</td>
      <td style="padding:.8rem;border:none;color:#586c5d;font-weight:500;">${record.age_in_months || 'N/A'}</td>
      <td style="padding:.8rem;border:none;color:#586c5d;font-weight:500;">${record.weight_kg || 'N/A'}</td>
      <td style="padding:.8rem;border:none;color:#586c5d;font-weight:500;">${record.length_height_cm || 'N/A'}</td>
      <td style="padding:.8rem;border:none;">${statusBadge}</td>
      <td style="padding:.8rem;border:none;color:#586c5d;font-size:.6rem;">${record.remarks || '-'}</td>
    </tr>
  `;
}

// Load all weighing records immediately when page loads - consistent with UI
function loadAllWeighingRecords() {
  const container = document.getElementById('allRecordsContainer');
  
  // Show loading state
  container.innerHTML = `
    <div class="text-center py-3" style="color:var(--muted);font-size:.65rem;">
      <div class="spinner-border spinner-border-sm me-2" role="status" style="width:1rem;height:1rem;border-width:2px;"></div>
      Loading all weighing records...
    </div>
  `;
  
  // Fetch all nutrition records
  fetch(`${api.nutrition}?recent=1`, {
    method: 'GET',
    headers: {
      'X-CSRF-Token': window.__BNS_CSRF,
      'X-Requested-With': 'XMLHttpRequest',
      'Accept': 'application/json'
    }
  })
  .then(response => {
    if (!response.ok) {
      throw new Error(`HTTP ${response.status}`);
    }
    return response.json();
  })
  .then(data => {
    console.log('All records data:', data);
    
    if (data.success && data.records && data.records.length > 0) {
      const records = data.records;
      
      // Store records globally for filtering
      window.allWeighingRecords = records;
      
      // Render table with consistent UI styling
      renderAllRecordsTable(records);
      
      // Setup search and filter functionality
      setupRecordsFiltering();
      
    } else {
      // No records found - consistent with your UI
      container.innerHTML = `
        <div class="text-center py-5" style="color:var(--muted);font-size:.65rem;">
          <i class="bi bi-clipboard-x" style="font-size:3rem;opacity:0.3;"></i>
          <h6 class="mt-3 mb-1" style="font-size:.8rem;font-weight:600;">No Weighing Records Found</h6>
          <p class="text-muted small mb-0" style="font-size:.65rem;">No nutrition records have been created yet.</p>
        </div>
      `;
    }
  })
  .catch(error => {
    console.error('Error loading all records:', error);
    
    // Error state - consistent with your error styling
    container.innerHTML = `
      <div class="text-center py-4" style="color:#dc3545;font-size:.65rem;">
        <i class="bi bi-exclamation-triangle" style="font-size:2rem;opacity:0.5;color:#dc3545;"></i>
        <p style="margin:.5rem 0 0;color:#dc3545;">Error loading weighing records</p>
      </div>
    `;
  });
}

// Render all records table with consistent UI styling
function renderAllRecordsTable(records) {
  const container = document.getElementById('allRecordsContainer');
  
  container.innerHTML = `
    <table class="table table-hover mb-0" style="font-size:.7rem;">
      <thead style="background:#f8faf9;border-bottom:1px solid var(--border-soft);">
        <tr>
          <th style="padding:.75rem .8rem;font-size:.65rem;font-weight:700;color:#344f3a;border:none;">Child Name</th>
          <th style="padding:.75rem .8rem;font-size:.65rem;font-weight:700;color:#344f3a;border:none;">Date</th>
          <th style="padding:.75rem .8rem;font-size:.65rem;font-weight:700;color:#344f3a;border:none;">Age (months)</th>
          <th style="padding:.75rem .8rem;font-size:.65rem;font-weight:700;color:#344f3a;border:none;">Weight (kg)</th>
          <th style="padding:.75rem .8rem;font-size:.65rem;font-weight:700;color:#344f3a;border:none;">Height (cm)</th>
          <th style="padding:.75rem .8rem;font-size:.65rem;font-weight:700;color:#344f3a;border:none;">Status</th>
          <th style="padding:.75rem .8rem;font-size:.65rem;font-weight:700;color:#344f3a;border:none;">Remarks</th>
        </tr>
      </thead>
      <tbody>
        ${records.map(record => renderWeighingRecordRow(record)).join('')}
      </tbody>
    </table>
  `;
}

// Helper function to render individual weighing record rows
function renderWeighingRecordRow(record) {
  // Format date consistently
  const weighingDate = record.weighing_date ? 
    new Date(record.weighing_date).toLocaleDateString('en-PH', {
      timeZone: 'Asia/Manila',
      year: 'numeric',
      month: 'numeric',
      day: 'numeric'
    }) : 'N/A';
  
  // Get status badge with consistent styling
  const statusBadge = record.status_code ? 
    `<span class="badge-status badge-${record.status_code}" style="font-size:.55rem;font-weight:600;padding:.25rem .5rem;border-radius:8px;">${record.status_code}</span>` : 
    '<span style="color:var(--muted);font-size:.6rem;">N/A</span>';
  
  return `
    <tr style="border-bottom:1px solid #f0f4f1;">
      <td style="padding:.8rem;border:none;">
        <div style="font-weight:600;color:#1e3e27;font-size:.7rem;">${escapeHtml(record.child_name || 'Unknown')}</div>
      </td>
      <td style="padding:.8rem;border:none;color:#586c5d;font-weight:500;">${weighingDate}</td>
      <td style="padding:.8rem;border:none;color:#586c5d;font-weight:500;">${record.age_in_months || 'N/A'}</td>
      <td style="padding:.8rem;border:none;color:#586c5d;font-weight:500;">${record.weight_kg || 'N/A'}</td>
      <td style="padding:.8rem;border:none;color:#586c5d;font-weight:500;">${record.length_height_cm || 'N/A'}</td>
      <td style="padding:.8rem;border:none;">${statusBadge}</td>
      <td style="padding:.8rem;border:none;color:#586c5d;font-size:.6rem;">${escapeHtml(record.remarks || '-')}</td>
    </tr>
  `;
}

// Setup search and filter functionality
function setupRecordsFiltering() {
  const searchInput = document.getElementById('recordsSearchInput');
  const statusFilter = document.getElementById('statusFilter');
  const dateFilter = document.getElementById('dateFilter');
  
  function filterRecords() {
    if (!window.allWeighingRecords) return;
    
    const searchTerm = searchInput.value.toLowerCase();
    const statusValue = statusFilter.value;
    const dateValue = dateFilter.value;
    
    const filteredRecords = window.allWeighingRecords.filter(record => {
      const matchesSearch = !searchTerm || 
        (record.child_name && record.child_name.toLowerCase().includes(searchTerm));
      
      const matchesStatus = !statusValue || record.status_code === statusValue;
      
      const matchesDate = !dateValue || 
        (record.weighing_date && record.weighing_date.startsWith(dateValue));
      
      return matchesSearch && matchesStatus && matchesDate;
    });
    
    renderAllRecordsTable(filteredRecords);
  }
  
  searchInput.addEventListener('input', filterRecords);
  statusFilter.addEventListener('change', filterRecords);
  dateFilter.addEventListener('change', filterRecords);
}

// Clear filters function
function clearFilters() {
  document.getElementById('recordsSearchInput').value = '';
  document.getElementById('statusFilter').value = '';
  document.getElementById('dateFilter').value = '';
  
  if (window.allWeighingRecords) {
    renderAllRecordsTable(window.allWeighingRecords);
  }
}

/* Module map */
const handlers={
  dashboard_home:renderDashboardHome,
  child_profiles:renderChildrenModule,
  weighing_sessions:renderWeighingModuleSplit, // <- point to split layout
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

<!-- Schedule Event Modal -->
<div class="modal fade" id="scheduleEventModal" tabindex="-1" aria-labelledby="scheduleEventModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content" style="border-radius:16px;border:1px solid var(--border-soft);box-shadow:0 10px 40px -10px rgba(15,32,23,.15);">
      <!-- Modal Header -->
      <div class="modal-header" style="border-bottom:1px solid var(--border-soft);padding:1.2rem 1.5rem;">
        <div>
          <h5 class="modal-title" id="scheduleEventModalLabel" style="font-size:.9rem;font-weight:700;color:var(--text);margin:0;">Schedule New Event</h5>
          <p class="text-muted mb-0" style="font-size:.65rem;margin-top:.2rem;">Plan nutrition sessions and activities</p>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" style="font-size:.8rem;"></button>
      </div>

      <!-- Modal Body -->
      <div class="modal-body" style="padding:1.5rem;">
        <form id="scheduleEventForm">
          <!-- Event Details Section -->
          <div class="form-section">
            <div class="form-section-header">
              <div class="form-section-icon" style="background:#e8f5ea;color:#0b7a43;">
                <i class="bi bi-calendar-event"></i>
              </div>
              <h3 class="form-section-title">Event Details</h3>
            </div>
            <div class="form-grid">
              <div class="form-group">
                <label class="form-label">Event Title *</label>
                <input type="text" class="form-control" name="event_title" placeholder="Enter event title" maxlength="255" required>
              </div>
              <div class="form-group">
                <label class="form-label">Event Type *</label>
                <select class="form-select" name="event_type" required>
                  <option value="">Select event type</option>
                  <option value="health">Health</option>
                  <option value="feeding">Feeding Program</option>
                  <option value="weighing">Weighing</option>
                  <option value="nutrition">Nutrition Education</option>
                </select>
              </div>
              <div class="form-group">
                <label class="form-label">Event Description</label>
                <textarea class="form-control" name="event_description" placeholder="Brief description of the event" rows="3" maxlength="500"></textarea>
              </div>
            </div>
          </div>

          <!-- Schedule Information Section -->
          <div class="form-section">
            <div class="form-section-header">
              <div class="form-section-icon" style="background:#e1f1ff;color:#1c79d0;">
                <i class="bi bi-clock"></i>
              </div>
              <h3 class="form-section-title">Schedule Information</h3>
            </div>
            <div class="form-grid">
              <div class="form-group">
                <label class="form-label">Event Date *</label>
                <div class="date-input">
                  <input type="date" class="form-control" name="event_date" required>
                </div>
              </div>
              <div class="form-group">
                <label class="form-label">Event Time *</label>
                <input type="time" class="form-control" name="event_time" required>
              </div>
              <div class="form-group">
                <label class="form-label">Location *</label>
                <input type="text" class="form-control" name="location" placeholder="Event venue" maxlength="255" required>
              </div>
            </div>
          </div>

          <!-- Additional Information Section -->
          <div class="form-section">
            <div class="form-section-header">
              <div class="form-section-icon" style="background:#f3e8ff;color:#a259c6;">
                <i class="bi bi-people"></i>
              </div>
              <h3 class="form-section-title">Additional Information</h3>
            </div>
            <div class="form-grid">
              <div class="form-group">
                <label class="form-label">Target Audience</label>
                <input type="text" class="form-control" name="target_audience" placeholder="Who should attend? (e.g., Children 0-5 years, Pregnant mothers)" maxlength="255">
              </div>
              <div class="form-group">
                <label class="form-label">Publication Status</label>
                <select class="form-select" name="is_published">
                  <option value="1">Published (Visible to public)</option>
                  <option value="0">Draft (Not visible yet)</option>
                </select>
              </div>
            </div>
          </div>
        </form>
      </div>

      <!-- Modal Footer -->
      <div class="modal-footer" style="border-top:1px solid var(--border-soft);padding:1rem 1.5rem;">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" style="font-size:.7rem;font-weight:600;padding:.6rem 1.2rem;border-radius:8px;">Cancel</button>
        <button type="button" class="btn btn-success" id="saveEventBtn" style="font-size:.7rem;font-weight:600;padding:.6rem 1.2rem;border-radius:8px;box-shadow:0 2px 6px -2px rgba(20,104,60,.5);">
          <i class="bi bi-calendar-plus me-1"></i> Schedule Event
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Supplementation Add Record Modal -->
<div class="modal fade" id="supplementationRecordModal" tabindex="-1" aria-labelledby="supplementationRecordModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content" style="border-radius:16px;border:1px solid var(--border-soft);box-shadow:0 10px 40px -10px rgba(15,32,23,.15);">
      <div class="modal-header" style="border-bottom:1px solid var(--border-soft);padding:1.2rem 1.5rem;">
        <div>
          <h5 class="modal-title" id="supplementationRecordModalLabel" style="font-size:.9rem;font-weight:700;color:var(--text);margin:0;">Add Supplementation Record</h5>
          <p class="text-muted mb-0" style="font-size:.65rem;margin-top:.2rem;">Record Vitamin A, Iron, or Deworming distributions</p>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" style="font-size:.8rem;"></button>
      </div>
      <div class="modal-body" style="padding:1.5rem;">
        <form id="suppRecordForm">
          <div class="form-section">
            <div class="form-section-header">
              <div class="form-section-icon" style="background:#e8f5ea;color:#0b7a43;">
                <i class="bi bi-capsule"></i>
              </div>
              <h3 class="form-section-title">Record Details</h3>
            </div>
            <div class="form-grid">
              <div class="form-group">
                <label class="form-label">Child</label>
                <select class="form-select" id="suppChildSelect" required>
                  <option value="">Select child</option>
                </select>
              </div>
              <div class="form-group">
                <label class="form-label">Supplement Type</label>
                <select class="form-select" id="suppType" required>
                  <option value="">Select type</option>
                  <option value="Vitamin A">Vitamin A</option>
                  <option value="Iron">Iron</option>
                  <option value="Deworming">Deworming</option>
                </select>
              </div>
              <div class="form-group">
                <label class="form-label">Date Given</label>
                <div class="date-input">
                  <input type="date" class="form-control" id="suppDate" required>
                </div>
              </div>
              <div class="form-group">
                <label class="form-label">Dosage</label>
                <input type="text" class="form-control" id="suppDosage" placeholder="e.g., 200,000 IU">
              </div>
              <div class="form-group">
                <label class="form-label">Next Due Date</label>
                <div class="date-input">
                  <input type="date" class="form-control" id="suppNextDue" placeholder="Auto-suggested based on type">
                </div>
              </div>
              <div class="form-group">
                <label class="form-label">Notes</label>
                <textarea class="form-control" id="suppNotes" rows="3" placeholder="Additional notes..."></textarea>
              </div>
            </div>
          </div>
        </form>
      </div>
      <div class="modal-footer" style="border-top:1px solid var(--border-soft);padding:1rem 1.5rem;">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" style="font-size:.7rem;font-weight:600;padding:.6rem 1.2rem;border-radius:8px;">Cancel</button>
        <button type="button" class="btn btn-success" id="saveSuppRecordBtn" style="font-size:.7rem;font-weight:600;padding:.6rem 1.2rem;border-radius:8px;box-shadow:0 2px 6px -2px rgba(20,104,60,.5);">
          <i class="bi bi-save me-1"></i> Save Record
        </button>
      </div>
    </div>
  </div>
</div>

<!-- View Child Modal -->
<div class="modal fade" id="childProfileViewModal" tabindex="-1" aria-labelledby="childProfileViewModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content" style="border-radius:16px;border:1px solid var(--border-soft);box-shadow:0 10px 40px -10px rgba(15,32,23,.15);">
      <div class="modal-header" style="border-bottom:1px solid var(--border-soft);padding:1.1rem 1.3rem;">
        <h5 class="modal-title" id="childProfileViewModalLabel" style="font-size:.9rem;font-weight:700;color:var(--text);">Child Profile</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" style="font-size:.8rem;"></button>
      </div>
      <div class="modal-body" style="padding:1.1rem 1.3rem;">
        <div class="text-center py-3" style="color:var(--muted);font-size:.65rem;">
          <div class="spinner-border spinner-border-sm me-2" role="status"></div> Loading...
        </div>
      </div>
      <div class="modal-footer" style="border-top:1px solid var(--border-soft);padding: .8rem 1.1rem;">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" style="font-size:.7rem;font-weight:600;border-radius:10px;">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- Edit Child Modal (frontend only; backend update not yet wired) -->
<div class="modal fade" id="childProfileEditModal" tabindex="-1" aria-labelledby="childProfileEditModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content" style="border-radius:16px;border:1px solid var(--border-soft);box-shadow:0 10px 40px -10px rgba(15,32,23,.15);">
      <div class="modal-header" style="border-bottom:1px solid var(--border-soft);padding:1.1rem 1.3rem;">
        <h5 class="modal-title" id="childProfileEditModalLabel" style="font-size:.9rem;font-weight:700;color:var(--text);">Edit Child Profile</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" style="font-size:.8rem;"></button>
      </div>
      <div class="modal-body" style="padding:1.1rem 1.3rem;">
        <form id="childProfileEditForm">
          <input type="hidden" name="child_id">

          <div class="row g-3">
            <div class="col-12 col-md-6">
              <div class="tile-sub">Child</div>
              <div class="mb-2">
                <label class="form-label">Full Name</label>
                <input type="text" class="form-control" name="full_name" />
              </div>
              <div class="mb-2">
                <label class="form-label">Sex</label>
                <select class="form-select" name="sex">
                  <option value="">Select sex</option>
                  <option value="male">Male</option>
                  <option value="female">Female</option>
                </select>
              </div>
              <div class="mb-2">
                <label class="form-label">Birth Date</label>
                <input type="date" class="form-control" name="birth_date" />
              </div>
            </div>

            <div class="col-12 col-md-6">
              <div class="tile-sub">Mother/Caregiver</div>
              <div class="mb-2">
                <label class="form-label">Full Name</label>
                <input type="text" class="form-control" name="mother_name" />
              </div>
              <div class="mb-2">
                <label class="form-label">Contact</label>
                <input type="text" class="form-control" name="mother_contact" />
              </div>
              <div class="mb-2">
                <label class="form-label">Address</label>
                <input type="text" class="form-control" name="address_details" />
              </div>
              <div class="mb-2">
                <label class="form-label">Purok</label>
                <input type="text" class="form-control" name="purok_name" placeholder="e.g., Purok 1" />
              </div>
            </div>
          </div>
        </form>
        <p class="text-muted mt-2 mb-0" style="font-size:.62rem;">Note: Saving requires backend update endpoint (api_children.php?action=update).</p>
      </div>
      <div class="modal-footer" style="border-top:1px solid var(--border-soft);padding: .8rem 1.1rem;">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" style="font-size:.7rem;font-weight:600;border-radius:10px;">Cancel</button>
        <button type="button" class="btn btn-success" id="saveChildEditBtn" style="font-size:.7rem;font-weight:600;border-radius:10px;">
          <i class="bi bi-save me-1"></i> Save Changes
        </button>
      </div>
    </div>
  </div>
</div>

</body>
</html>