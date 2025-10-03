<?php
require_once __DIR__.'/inc/db.php';
require_once __DIR__.'/inc/auth.php';
require_role(['BHW']);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];
$username = $_SESSION['username'] ?? 'Health Worker';
$firstName = explode(' ', trim($username))[0];
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>BHW Dashboard</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
/* =========================================
   TYPO SCALE (Adjust here for global size)
   ========================================= */
:root{
  --base-font-size-root:16px;       /* Align base size with BNS (Bootstrap default 16px) */
  --base-font-size-lg:16.2px;       /* Slight bump only on very wide screens */
  --zoom-step:0px;                  /* dynamic pixel addition via controls (persisted) */

  --font-sans:system-ui,-apple-system,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif;

  --sidebar-bg:#ffffff;
  --sidebar-border:#d3dbe1;
  --sidebar-accent:#026a44;
  --sidebar-accent-hover:#008257;
  --sidebar-text:#142a33;
  --sidebar-muted:#62747f;

  --surface:#ffffff;
  --border:#dee4ea;
  --bg:#f1f5f7;
  --radius:16px;

  --focus:#009860;
  --danger:#b81f14;
  --danger-bg:#fde5e2;
  --warn:#8b6400;
  --warn-bg:#fff2cc;
  --info:#0a63c9;
  --info-bg:#e0efff;
  --success:#0b6f46;
  --success-bg:#ddf5ea;
  --text-muted:#58656e;

  --shadow-sm:0 1px 2px rgba(15,23,42,.07),0 2px 4px rgba(15,23,42,.06);
  --shadow-md:0 6px 18px -3px rgba(15,23,42,.12),0 12px 32px -6px rgba(15,23,42,.08);
  --gradient-accent:linear-gradient(90deg,#00905c,#00b274);
  --table-head:#f0f5f7;
  --line-height:1.42;
}

/* Optional body classes to jump bigger instantly */
body.size-xl{ --base-font-size-root:18.2px; }
body.size-xxl{ --base-font-size-root:19.5px; }

html{
  font-size:calc(var(--base-font-size-root) + var(--zoom-step));
}
@media (min-width:1500px){
  html{ font-size:calc(var(--base-font-size-lg) + var(--zoom-step)); }
}
html,body{height:100%;}
body{
  background:var(--bg);
  font-family:var(--font-sans);
  line-height:var(--line-height);
  -webkit-font-smoothing:antialiased;
  text-rendering:optimizeLegibility;
  overflow:hidden;
}

/* Layout */
.layout-wrapper{display:flex;height:100vh;width:100%;overflow:hidden;}
.sidebar-modern{
  width:270px;flex:0 0 270px;background:var(--sidebar-bg);border-right:1px solid var(--sidebar-border);
  display:flex;flex-direction:column;z-index:40;
  font-size:0.92rem;
}
.brand-block{
  padding:1.2rem 1.2rem 1.1rem;border-bottom:1px solid var(--sidebar-border);display:flex;align-items:center;gap:1rem;
}
.brand-icon{
  height:54px;width:54px;border-radius:17px;display:flex;align-items:center;justify-content:center;
  background:linear-gradient(135deg,#00a066,#00b676);color:#fff;font-size:24px;font-weight:600;
  box-shadow:0 4px 12px -2px rgba(0,160,102,.45);
}
.brand-text{font-size:1.05rem;font-weight:700;color:var(--sidebar-text);line-height:1.1;}
.brand-text small{display:block;font-size:.67rem;font-weight:600;color:var(--sidebar-muted);letter-spacing:.05em;margin-top:4px;}

.nav-section-title{
  font-size:.68rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;
  color:var(--sidebar-muted);padding:1.15rem 1.2rem .55rem;
}

.nav-list{list-style:none;margin:0;padding:0 1rem 1rem;}
.nav-list li{margin-bottom:4px;}
.nav-link-modern{
  display:flex;align-items:center;gap:1rem;padding:.85rem 1.05rem .85rem 1.15rem;
  text-decoration:none;border-radius:1rem;font-size:.85rem;font-weight:600;color:var(--sidebar-text);
  transition:.16s background,.16s color;
  position:relative;
}
.nav-link-modern .icon-wrap{width:24px;display:flex;justify-content:center;opacity:.85;font-size:18px;}
.nav-link-modern:hover{background:#e6f6ef;color:#025534;}
.nav-link-modern.active{
  background:var(--sidebar-accent);color:#fff;box-shadow:0 4px 18px -4px rgba(0,106,80,.55);
}
.nav-link-modern.active .icon-wrap{opacity:1;}

.sidebar-footer-box{
  margin-top:auto;padding:1.25rem 1.15rem;border-top:1px solid var(--sidebar-border);font-size:.7rem;
}
.system-status{
  font-size:.72rem;border:1px solid var(--border);background:var(--surface);padding:.85rem .95rem;border-radius:1rem;
  display:flex;flex-direction:column;gap:.55rem;line-height:1.25;
}
.system-status .dot{height:11px;width:11px;border-radius:50%;margin-right:.5rem;}
.status-ok{background:#07a466;}

.sidebar-logout{padding:1rem 1.1rem;}
.sidebar-logout .btn{
  font-size:.75rem;font-weight:600;padding:.7rem .9rem;border-radius:.7rem;
}

/* Topbar */
.topbar-modern{
  height:70px;background:var(--surface);border-bottom:1px solid var(--border);display:flex;align-items:center;
  padding:0 1.9rem;gap:1.35rem;flex-shrink:0;
  font-size:.95rem;
}
.greet strong{font-size:1.05rem;font-weight:700;color:#142f2a;}
.greet span{font-size:.72rem;color:var(--text-muted);font-weight:500;}

.user-chip{
  display:flex;align-items:center;gap:.75rem;padding:.65rem 1.05rem;border:1px solid var(--border);background:var(--surface);
  border-radius:34px;font-size:.78rem;font-weight:600;color:#1e343a;line-height:1.15;
}
.user-avatar{
  height:38px;width:38px;border-radius:50%;background:#0d8455;color:#fff;display:flex;align-items:center;justify-content:center;
  font-weight:700;font-size:.95rem;letter-spacing:.5px;
}
.notif-btn{
  background:transparent;border:1px solid var(--border);position:relative;padding:.55rem .7rem;border-radius:14px;font-size:19px;color:#234;
}
.notif-btn:hover{background:#f2f7f9;}
.notif-btn .badge-count{
  position:absolute;top:-4px;right:-4px;background:#cf271c;color:#fff;font-size:.6rem;line-height:1;
  padding:2px 6px;border-radius:11px;font-weight:700;box-shadow:0 0 0 2px var(--surface);
}
.mobile-toggle-btn{display:none;}

/* Content */
.content-area{flex:1;min-width:0;display:flex;flex-direction:column;overflow:hidden;}
main#mainRegion{flex:1;display:flex;flex-direction:column;overflow:hidden;}
#moduleContent{
  flex:1;overflow:auto;padding:2rem 2.3rem 3rem;scroll-behavior:smooth;
  font-size:.95rem;
}
#moduleContent::-webkit-scrollbar{width:12px;}
#moduleContent::-webkit-scrollbar-thumb{background:#bbc6cc;border-radius:8px;}
#moduleContent::-webkit-scrollbar-thumb:hover{background:#a1adb4;}

/* Dashboard Headings */
.dashboard-welcome{font-size:1.35rem;font-weight:800;color:#063024;letter-spacing:.5px;margin-bottom:.35rem;}
.dashboard-sub{font-size:.85rem;color:#5f6e78;margin-bottom:1.5rem;font-weight:500;}

/* Metric Grid */
.metric-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(255px,1fr));gap:1.55rem;margin-bottom:1.8rem;}
.metric-card{
  background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:1.25rem 1.3rem;
  display:flex;flex-direction:column;justify-content:space-between;position:relative;box-shadow:var(--shadow-sm);
  min-height:140px;overflow:hidden;
}
.metric-card:before{
  content:"";position:absolute;inset:0;border-radius:inherit;
  background:radial-gradient(circle at 88% 18%,rgba(0,160,110,.15),transparent 60%);
  pointer-events:none;
}
.metric-title{
  font-size:.78rem;font-weight:800;color:#31474e;letter-spacing:.07em;text-transform:uppercase;margin:0 0 .75rem;
  display:flex;align-items:center;gap:.6rem;
}
.metric-title i{font-size:1.15rem;opacity:.95;}
.metric-value{font-size:2rem;font-weight:800;color:#052d25;line-height:1;}
.metric-diff{font-size:.74rem;font-weight:700;margin-top:.55rem;letter-spacing:.04em;}
.diff-up{color:#067c4b;}
.diff-down{color:#b21919;}
.progress-mini{height:9px;background:#e1ece6;border-radius:16px;margin-top:.75rem;overflow:hidden;}
.progress-mini span{display:block;height:100%;background:var(--gradient-accent);border-radius:inherit;transition:width .55s ease;}

/* Panels */
.dashboard-panels{display:grid;grid-template-columns:1fr 420px;gap:1.7rem;}
@media (max-width:1400px){.dashboard-panels{grid-template-columns:1fr 380px;}}
@media (max-width:1200px){.dashboard-panels{grid-template-columns:1fr;}}
.panel-card{
  background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);box-shadow:var(--shadow-sm);
  padding:1.45rem 1.55rem 1.55rem;display:flex;flex-direction:column;font-size:.9rem;
}
.panel-title{
  font-size:.82rem;font-weight:800;letter-spacing:.06em;color:#153036;text-transform:uppercase;
  margin:0 0 1rem;display:flex;align-items:center;gap:.65rem;
}
.panel-title i{font-size:1.05rem;color:#0a7b50;}
.panel-card .mini-text{font-size:.72rem;}

/* Table */
.table-clean{width:100%;border-collapse:collapse;font-size:.82rem;}
.table-clean thead th{
  font-weight:800;color:#2e4048;font-size:.7rem;text-transform:uppercase;letter-spacing:.09em;
  padding:.75rem .85rem;background:var(--table-head);position:sticky;top:0;z-index:2;
}
.table-clean tbody td{
  padding:.72rem .85rem;border-top:1px solid var(--border);vertical-align:middle;font-weight:500;color:#172d35;
}
.table-clean tbody tr{min-height:50px;}
.table-clean tbody tr:hover td{background:#f2faf6;}

/* Status Badges */
.status-badge{
  display:inline-flex;align-items:center;gap:.45rem;font-size:.68rem;font-weight:800;padding:.48rem .8rem;
  border-radius:30px;letter-spacing:.05em;text-transform:uppercase;
}
.st-overdue{background:var(--danger-bg);color:var(--danger);}
.st-due{background:var(--warn-bg);color:var(--warn);}
.st-sched{background:#e0edff;color:#154f95;}
.status-badge i{font-size:.85rem;}

/* Alerts */
.alert-stack{display:flex;flex-direction:column;gap:1.25rem;}
.alert-box{
  display:flex;gap:1.15rem;padding:1.15rem 1.25rem;border-radius:20px;border:1px solid var(--border);background:#f9fbfc;
  position:relative;overflow:hidden;box-shadow:0 1px 0 0 #e8eef1;font-size:.85rem;
}
.alert-box.danger{background:#fff4f3;border-color:#f0c5c1;}
.alert-box.danger:before{content:"";position:absolute;left:0;top:0;bottom:0;width:6px;background:var(--danger);border-top-left-radius:inherit;border-bottom-left-radius:inherit;}
.alert-box.warn{background:#fff8e7;border-color:#eedca1;}
.alert-box.warn:before{content:"";position:absolute;left:0;top:0;bottom:0;width:6px;background:#d39b05;border-top-left-radius:inherit;border-bottom-left-radius:inherit;}
.alert-box.info{background:#f1fbf6;border-color:#bdeace;}
.alert-box.info:before{content:"";position:absolute;left:0;top:0;bottom:0;width:6px;background:#0d905c;border-top-left-radius:inherit;border-bottom-left-radius:inherit;}
.alert-icon{
  height:46px;width:46px;border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:22px;font-weight:600;
}
.alert-icon.danger{background:#f7d1cd;color:#b42318;}
.alert-icon.warn{background:#ffe4a6;color:#8b6400;}
.alert-icon.info{background:#d2f1e2;color:#0b7c4d;}
.alert-content{flex:1;min-width:0;}
.alert-title{font-size:.9rem;font-weight:800;color:#132f31;margin:0 0 5px;letter-spacing:.02em;}
.alert-desc{font-size:.72rem;color:#42545d;line-height:1.3;}

/* Loading */
.loading-state{
  padding:3rem 1.5rem;text-align:center;color:#4f5d65;font-size:.95rem;
}
.loading-state .spinner-border{width:2.4rem;height:2.4rem;}

/* Generic */
.link-clean{color:#0a7d50;text-decoration:none;font-weight:700;font-size:.72rem;}
.link-clean:hover{text-decoration:underline;}
.text-muted{color:var(--text-muted)!important;}
.table thead th{font-size:.72rem;}
.table{font-size:.8rem;}

/* Focus */
:focus-visible{outline:3px solid var(--focus);outline-offset:3px;}

/* Mobile */
@media (max-width: 991.98px){
  .sidebar-modern{
    position:fixed;top:0;left:0;bottom:0;transform:translateX(-100%);transition:.35s;
    box-shadow:0 0 0 400vmax rgba(0,0,0,.35);
  }
  .sidebar-modern.show{transform:translateX(0);}
  .topbar-modern{position:fixed;top:0;left:0;right:0;z-index:50;}
  .content-area{padding-top:70px;}
  #moduleContent{padding:1.5rem 1.3rem 2.4rem;}
  .mobile-toggle-btn{display:inline-flex;}
}

/* Fade */
.fade-in{animation:fadeIn .5s ease;}
@keyframes fadeIn{from{opacity:0;transform:translateY(12px);}to{opacity:1;transform:translateY(0);}}

/* Zoom Control */
.zoom-controls{
  position:fixed;bottom:14px;right:14px;z-index:100;display:flex;flex-direction:column;gap:.35rem;
}
.zoom-controls button{
  background:var(--surface);border:1px solid var(--border);border-radius:10px;
  font-size:.85rem;font-weight:700;padding:.55rem .75rem;color:#1c2f36;min-width:46px;
  box-shadow:var(--shadow-sm);
}
.zoom-controls button:hover{background:#eef5f2;}
.zoom-controls button:active{transform:translateY(1px);}
/* === Maternal Health New UI === */
.mh-title{font-size:1.35rem;font-weight:700;color:#11312a;margin:0;}
.mh-sub{font-size:.8rem;color:#5e6d75;font-weight:500;margin:.3rem 0 0;}
.mh-header{border-bottom:1px solid var(--border);padding-bottom:1.05rem;margin-bottom:1.4rem;}
.mh-add-btn{display:inline-flex;align-items:center;gap:.45rem;font-weight:600;border-radius:.9rem;background:#047a4c;border:1px solid #047242;}
.mh-add-btn:hover{background:#059a61;border-color:#059a61;}

.mh-metrics{margin-bottom:1.4rem;}
.mh-metric-card{background:var(--surface);border:1px solid var(--border);border-radius:18px;padding:1rem 1.1rem;box-shadow:var(--shadow-sm);height:100%;display:flex;flex-direction:column;justify-content:center;position:relative;overflow:hidden;}
.mh-metric-card:before{content:"";position:absolute;inset:0;border-radius:inherit;background:radial-gradient(circle at 85% 20%,rgba(0,150,100,.12),transparent 60%);pointer-events:none;}
.mh-metric-label{font-size:.62rem;font-weight:700;letter-spacing:.11em;text-transform:uppercase;color:#4b5c63;margin-bottom:.55rem;display:flex;align-items:center;gap:.4rem;}
.mh-metric-value{font-size:2.1rem;font-weight:800;margin:0;line-height:1;color:#052e26;}
.mh-metric-sub{font-size:.63rem;color:#607078;margin-top:.3rem;font-weight:600;}

.mh-tabs{background:#f5f8fa;border-radius:999px;padding:.4rem .5rem;display:inline-flex;flex-wrap:wrap;gap:.35rem;}
.mh-tabs .nav-link{border-radius:30px;font-size:.7rem;font-weight:600;padding:.55rem 1rem;color:#335155;background:transparent;border:0;}
.mh-tabs .nav-link.active{background:#ffffff;border:1px solid var(--border);box-shadow:0 2px 4px rgba(0,0,0,.06);color:#0a5c3d;font-weight:700;}
.mh-tabs .nav-link:hover{background:#e9f4ef;color:#0a5c3d;}

.mh-card{background:var(--surface);border:1px solid var(--border);border-radius:20px;box-shadow:var(--shadow-sm);padding:1.15rem 1.4rem;}
.mh-card-title{font-size:.78rem;font-weight:800;color:#203536;text-transform:uppercase;letter-spacing:.07em;margin:0 0 .4rem;}
.mh-card-sub{font-size:.66rem;color:#5d7077;margin-bottom:1rem;}

.mh-table{width:100%;border-collapse:collapse;font-size:.78rem;}
.mh-table thead th{font-size:.65rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;background:#f1f6f7;padding:.65rem .8rem;color:#2f454b;position:sticky;top:0;z-index:5;}
.mh-table tbody td{padding:.6rem .8rem;border-top:1px solid var(--border);vertical-align:middle;font-weight:500;color:#1b3238;}
.mh-table tbody tr:hover td{background:#f3faf6;}

.mh-progress-wrap{min-width:160px;}
.mh-progress{height:8px;background:#e2ece8;border-radius:10px;overflow:hidden;position:relative;margin:.3rem 0 .2rem;}
.mh-progress-bar{height:100%;background:linear-gradient(90deg,#008c59,#00b073);transition:width .5s ease;}
.mh-progress-bar.risk-high{background:linear-gradient(90deg,#cc2b1f,#e85146);}
.mh-progress-bar.risk-monitor{background:linear-gradient(90deg,#c39106,#ffcb3c);}
.mh-weeks-label{font-size:.62rem;font-weight:600;color:#385558;letter-spacing:.04em;}

.risk-badge{display:inline-flex;align-items:center;font-size:.58rem;font-weight:700;padding:.32rem .55rem;border-radius:16px;letter-spacing:.05em;}
.risk-high{background:#fde0dd;color:#b22218;}
.risk-monitor{background:#fff1cd;color:#8b6100;}
.risk-normal{background:#e1edff;color:#134f9c;}
.risk-na{background:#e9ecef;color:#5a646b;}

.mh-action-btn{background:#eef3f5;border:1px solid #d6e1e6;font-size:.62rem;font-weight:600;padding:.4rem .9rem;border-radius:14px;}
.mh-action-btn:hover{background:#e0eff1;}

.mh-empty{padding:2.2rem 1rem;text-align:center;font-size:.75rem;color:#6a7b82;}

.mh-mini-badges{display:flex;gap:.35rem;flex-wrap:wrap;margin-top:.4rem;}
.mh-flag-chip{background:#fbe4e2;color:#c2271b;font-size:.55rem;font-weight:700;padding:.25rem .45rem;border-radius:6px;letter-spacing:.04em;}

.mh-modal .modal-content{border-radius:20px;}
.mh-modal .modal-title{font-size:.9rem;font-weight:700;}
.mh-modal label{font-size:.65rem;font-weight:600;letter-spacing:.05em;color:#34525a;text-transform:uppercase;margin-bottom:.25rem;}
.mh-modal .form-control,.mh-modal .form-select{font-size:.8rem;border-radius:.7rem;padding:.55rem .75rem;}
.mh-modal .form-text{font-size:.6rem;}

.mh-search-wrap{display:flex;gap:.75rem;flex-wrap:wrap;align-items:center;margin-bottom:1rem;}
.mh-search-wrap input{max-width:240px;font-size:.75rem;border-radius:14px;padding:.55rem .8rem;}

.mh-filter-badge{font-size:.55rem;font-weight:600;padding:.3rem .55rem;border:1px solid var(--border);border-radius:20px;cursor:pointer;user-select:none;}
.mh-filter-badge.active{background:#0d7c4e;color:#fff;border-color:#0d7c4e;}
.mh-filter-badge:hover{background:#e8f6f0;}

@media (max-width: 900px){
  .mh-progress-wrap{min-width:120px;}
  .mh-table thead th:nth-child(3),
  .mh-table tbody td:nth-child(3){min-width:160px;}
}

/* Smooth fade for row injection */
.mh-fade-in{animation:mhFade .4s ease;}
@keyframes mhFade{from{opacity:0;transform:translateY(6px);}to{opacity:1;transform:translateY(0);}}

/* === Maternal Health Consultations UI === */
.mh-consult-layout{display:grid;grid-template-columns:310px 1fr;gap:1.35rem;margin-top:1.2rem;}
@media (max-width:1100px){.mh-consult-layout{grid-template-columns:1fr;}}
.mh-mother-list{background:var(--surface);border:1px solid var(--border);border-radius:18px;padding:1rem 1rem 1.1rem;
  display:flex;flex-direction:column;gap:.75rem;max-height:640px;}
.mh-mother-list h6{font-size:.7rem;font-weight:800;letter-spacing:.07em;margin:0 0 .3rem;color:#24433f;text-transform:uppercase;}
.mh-mother-search{font-size:.72rem;border-radius:14px;padding:.5rem .8rem;}
.mh-mother-scroll{overflow:auto;flex:1;scrollbar-width:thin;}
.mh-mother-item{padding:.55rem .65rem;border:1px solid transparent;border-radius:10px;font-size:.72rem;
  display:flex;flex-direction:column;gap:2px;cursor:pointer;}
.mh-mother-item:hover{background:#eef7f3;}
.mh-mother-item.active{background:#0d7c4e;color:#fff;border-color:#0d7c4e;}
.mh-mother-item small{font-size:.58rem;opacity:.75;}
.mh-consult-main{background:var(--surface);border:1px solid var(--border);border-radius:20px;padding:1.2rem 1.35rem;box-shadow:var(--shadow-sm);}
.mh-consult-main h6{font-size:.78rem;font-weight:800;letter-spacing:.06em;text-transform:uppercase;margin:0 0 .8rem;color:#1c3536;}
.mh-consult-form label{font-size:.6rem;font-weight:700;letter-spacing:.05em;text-transform:uppercase;margin-bottom:.25rem;color:#355156;}
.mh-consult-form .form-control,.mh-consult-form .form-select{font-size:.74rem;padding:.5rem .65rem;border-radius:.65rem;}
.mh-risks-wrap{display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:.4rem;}
.mh-risk-box{font-size:.58rem;background:#f2f6f7;border:1px solid var(--border);border-radius:8px;padding:.4rem .45rem;
  display:flex;align-items:center;gap:.35rem;}
.mh-risk-box input{margin:0;}
.mh-consults-table{width:100%;border-collapse:collapse;font-size:.72rem;margin-top:.9rem;}
.mh-consults-table thead th{position:sticky;top:0;background:#f2f6f7;padding:.55rem .6rem;font-size:.62rem;font-weight:700;letter-spacing:.06em;color:#274048;text-transform:uppercase;}
.mh-consults-table tbody td{padding:.55rem .6rem;border-top:1px solid var(--border);vertical-align:middle;}
.consult-risk-badge{display:inline-block;font-size:.55rem;font-weight:700;padding:.25rem .5rem;border-radius:14px;letter-spacing:.04em;}
.consult-risk-high{background:#fde0dd;color:#b22218;}
.consult-risk-monitor{background:#fff1cd;color:#8b6100;}
.consult-risk-normal{background:#e1edff;color:#134f9c;}
.mh-inline-hint{font-size:.55rem;color:#607078;font-weight:600;margin-top:.25rem;}
.mh-form-divider{height:1px;background:var(--border);margin:.9rem 0;}
.mh-save-btn{font-size:.7rem;font-weight:700;padding:.55rem 1.1rem;border-radius:.75rem;}
.badge-ga{background:#eaf5f1;color:#0d7c4e;font-size:.55rem;font-weight:700;padding:.25rem .55rem;border-radius:14px;margin-left:.35rem;}
.mh-consult-form .trio-row .form-control{font-size:.72rem;}
@media (max-width:600px){
  .mh-consult-form .trio-row > div{flex:0 0 100%!important;}
}

/* === Pregnancy Monitoring UI === */
.mh-monitor-layout{display:grid;grid-template-columns:310px 1fr;gap:1.35rem;margin-top:1.2rem;}
@media (max-width:1100px){.mh-monitor-layout{grid-template-columns:1fr;}}
.mh-mon-main{background:var(--surface);border:1px solid var(--border);border-radius:20px;padding:1.15rem 1.35rem;box-shadow:var(--shadow-sm);}

.mh-mon-head h6{font-size:.8rem;font-weight:800;letter-spacing:.07em;margin:0;color:#153433;text-transform:uppercase;}
.mh-mon-summary{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:.75rem;margin:1rem 0 1.15rem;}
.mh-mon-card{border:1px solid var(--border);background:#f9fbfc;border-radius:14px;padding:.55rem .65rem;display:flex;flex-direction:column;gap:2px;position:relative;overflow:hidden;}
.mh-mon-card:before{content:"";position:absolute;inset:0;border-radius:inherit;background:radial-gradient(circle at 85% 18%,rgba(0,150,100,.12),transparent 60%);pointer-events:none;}
.mh-mon-label{font-size:.55rem;font-weight:700;letter-spacing:.08em;color:#526369;text-transform:uppercase;}
.mh-mon-value{font-size:1.25rem;font-weight:800;line-height:1;color:#0a372d;}
.mh-mon-sub{font-size:.55rem;font-weight:600;color:#687880;}
.mh-mon-risk-high{background:#fde0dd;}
.mh-mon-risk-monitor{background:#fff3d1;}
.mh-mon-progress-wrap{margin-bottom:1rem;}
.mh-mon-progress-label{display:flex;justify-content:space-between;font-size:.6rem;font-weight:600;color:#465e63;margin-bottom:4px;}
.mh-mon-progress{height:10px;border-radius:20px;background:#e3ece8;overflow:hidden;}
.mh-mon-bar{height:100%;background:linear-gradient(90deg,#008752,#00b872);width:0;transition:width .6s ease;}
.mh-mon-bar.high{background:linear-gradient(90deg,#cc2b1f,#e85146);}
.mh-mon-bar.monitor{background:linear-gradient(90deg,#c39106,#ffcb3c);}

.mh-mon-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:.9rem;margin:0 0 1.2rem;}
.mh-mon-mini{border:1px solid var(--border);background:#ffffff;border-radius:14px;padding:.7rem .75rem;display:flex;flex-direction:column;gap:4px;}
.mh-mon-mini h6{font-size:.55rem;font-weight:800;letter-spacing:.08em;margin:0;color:#345056;text-transform:uppercase;}
.mh-mon-mini .val{font-size:1rem;font-weight:700;color:#12342f;line-height:1;}
.mh-mon-mini small{font-size:.55rem;font-weight:600;color:#6a7b75;}

.mh-mon-trends{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:1rem;margin-bottom:1.3rem;}
.mh-trend-box{border:1px solid var(--border);background:#ffffff;border-radius:16px;padding:.75rem .8rem;display:flex;flex-direction:column;gap:.35rem;}
.mh-trend-box h6{font-size:.62rem;font-weight:800;letter-spacing:.07em;color:#2d4c4f;margin:0;text-transform:uppercase;}
.sparkline{height:60px;width:100%;display:block;}
.sparkline path{fill:none;stroke-width:2;vector-effect:non-scaling-stroke;}
.sparkline .axis{stroke:#b9c7cd;stroke-width:.8;stroke-dasharray:2 3;}
.mh-trend-legend{font-size:.55rem;font-weight:600;color:#5c6d74;display:flex;gap:.75rem;flex-wrap:wrap;}
.mh-trend-legend span:before{content:"";display:inline-block;width:14px;height:6px;border-radius:3px;margin-right:4px;vertical-align:middle;}

.mh-mon-table-wrap{border:1px solid var(--border);border-radius:18px;padding:.85rem .95rem;background:#ffffff;}
.mh-mon-table{width:100%;border-collapse:collapse;font-size:.7rem;}
.mh-mon-table thead th{background:#f1f6f7;font-size:.58rem;font-weight:800;letter-spacing:.07em;padding:.45rem .55rem;color:#2d444c;position:sticky;top:0;}
.mh-mon-table tbody td{padding:.45rem .55rem;border-top:1px solid var(--border);vertical-align:middle;}
.mh-delta-plus{color:#0d7c4e;font-weight:700;}
.mh-delta-minus{color:#b22218;font-weight:700;}

.mh-mon-empty{padding:2rem 1rem;text-align:center;font-size:.7rem;color:#6c7c83;}
.mh-risk-chip{display:inline-block;font-size:.48rem;font-weight:700;padding:2px 5px;border-radius:8px;margin:1px;}
.mh-risk-chip.on{background:#ffe0de;color:#b62218;}
.mh-risk-chip.off{background:#e2e8eb;color:#5a686f;}

.mh-mon-actions{display:flex;gap:.55rem;flex-wrap:wrap;margin-top:.6rem;}
.mh-mon-actions button{font-size:.6rem;font-weight:600;border-radius:14px;padding:.4rem .75rem;}

@media (max-width:600px){
  .mh-mon-summary{grid-template-columns:repeat(auto-fit,minmax(120px,1fr));}
  .mh-mon-grid{grid-template-columns:repeat(auto-fit,minmax(160px,1fr));}
}

/* === Postnatal Care UI === */
.mh-post-layout{display:grid;grid-template-columns:310px 1fr;gap:1.35rem;margin-top:1.2rem;}
@media (max-width:1100px){.mh-post-layout{grid-template-columns:1fr;}}
.mh-post-main{background:var(--surface);border:1px solid var(--border);border-radius:20px;padding:1.2rem 1.35rem;box-shadow:var(--shadow-sm);}
.mh-post-head h6{font-size:.78rem;font-weight:800;letter-spacing:.07em;margin:0;text-transform:uppercase;color:#183536;}
.mh-post-summary{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:.75rem;margin:1rem 0 1.2rem;}
.mh-post-card{border:1px solid var(--border);background:#f9fbfc;border-radius:14px;padding:.6rem .7rem;display:flex;flex-direction:column;gap:2px;position:relative;overflow:hidden;}
.mh-post-card:before{content:"";position:absolute;inset:0;border-radius:inherit;background:radial-gradient(circle at 85% 18%,rgba(0,150,100,.12),transparent 60%);pointer-events:none;}
.mh-post-label{font-size:.55rem;font-weight:700;letter-spacing:.08em;color:#5a6b70;text-transform:uppercase;}
.mh-post-value{font-size:1.15rem;font-weight:800;line-height:1;color:#07362d;}
.mh-post-sub{font-size:.55rem;font-weight:600;color:#6a7b75;}
.mh-post-risk-high{background:#fde0dd;}
.mh-post-risk-monitor{background:#fff3d1;}
.mh-post-form label{font-size:.58rem;font-weight:700;letter-spacing:.05em;text-transform:uppercase;margin-bottom:.22rem;color:#355056;}
.mh-post-form .form-control,.mh-post-form .form-select{font-size:.72rem;padding:.48rem .6rem;border-radius:.6rem;}
.mh-post-flags{display:grid;grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:.4rem;margin-top:.2rem;}
.mh-post-flag-box{font-size:.55rem;background:#f2f6f7;border:1px solid var(--border);border-radius:8px;padding:.38rem .45rem;display:flex;align-items:center;gap:.35rem;}
.mh-post-flag-box input{margin:0;}
.mh-post-empty{padding:1.8rem 1rem;text-align:center;font-size:.7rem;color:#6c7c83;}
.mh-post-table-wrap{border:1px solid var(--border);background:#fff;border-radius:18px;padding:.85rem .95rem;}
.mh-post-table{width:100%;border-collapse:collapse;font-size:.68rem;}
.mh-post-table thead th{background:#f1f6f7;font-size:.56rem;font-weight:800;letter-spacing:.08em;padding:.45rem .55rem;color:#2f474d;position:sticky;top:0;}
.mh-post-table tbody td{padding:.48rem .55rem;border-top:1px solid var(--border);vertical-align:middle;}
.mh-post-badge{display:inline-block;font-size:.5rem;font-weight:700;padding:.28rem .55rem;border-radius:12px;letter-spacing:.04em;}
.mh-post-risk-high-badge{background:#fde0dd;color:#b22218;}
.mh-post-risk-monitor-badge{background:#fff1cd;color:#8b6100;}
.mh-post-risk-normal-badge{background:#e1edff;color:#134f9c;}
.mh-post-actions{display:flex;gap:.55rem;flex-wrap:wrap;margin-top:.5rem;}
.mh-post-actions button{font-size:.6rem;font-weight:600;border-radius:14px;padding:.4rem .75rem;}
.mh-post-mini-legend{font-size:.5rem;font-weight:600;color:#607078;display:flex;flex-wrap:wrap;gap:.5rem;margin:.4rem 0;}
@media (max-width:600px){
  .mh-post-summary{grid-template-columns:repeat(auto-fit,minmax(120px,1fr));}
}

</style>
</head>
<body class="dashboard-body">
<div class="layout-wrapper">
  <!-- Sidebar -->
  <aside class="sidebar-modern" id="sidebar">
    <div class="brand-block">
      <div class="brand-icon"><i class="bi bi-heart-pulse"></i></div>
      <div class="brand-text">BHW Portal<small>Health Dashboard</small></div>
    </div>
    <div class="nav-section-title">Navigation</div>
    <ul class="nav-list">
      <li><a href="#" class="nav-link-modern active" data-module="health_stats" data-label="Dashboard"><span class="icon-wrap"><i class="bi bi-grid-1x2"></i></span><span>Dashboard</span></a></li>
      <li><a href="#" class="nav-link-modern" data-module="maternal_health" data-label="Maternal Health"><span class="icon-wrap"><i class="bi bi-person-heart"></i></span><span>Maternal Health</span></a></li>
      <li><a href="#" class="nav-link-modern" data-module="vaccination_entry" data-label="Vaccination Entry"><span class="icon-wrap"><i class="bi bi-syringe"></i></span><span>Immunization</span></a></li>
      <li><a href="#" class="nav-link-modern" data-module="create_parent_accounts" data-label="Parent Accounts"><span class="icon-wrap"><i class="bi bi-people"></i></span><span>Parent Accounts</span></a></li>
      <li><a href="#" class="nav-link-modern" data-module="health_records_all" data-label="Health Records"><span class="icon-wrap"><i class="bi bi-journal-medical"></i></span><span>Health Records</span></a></li>
      <li><a href="#" class="nav-link-modern" data-module="health_calendar" data-label="Event Scheduling"><span class="icon-wrap"><i class="bi bi-calendar3"></i></span><span>Event Scheduling</span></a></li>
      <li><a href="#" class="nav-link-modern" data-module="report_vaccination_coverage" data-label="Health Reports"><span class="icon-wrap"><i class="bi bi-bar-chart"></i></span><span>Health Reports</span></a></li>
      <li><a href="#" class="nav-link-modern" data-module="alert_system" data-label="Alert System"><span class="icon-wrap"><i class="bi bi-exclamation-triangle"></i></span><span>Alerts</span></a></li>
      <li><a href="#" class="nav-link-modern" data-module="parent_notifications" data-label="Parent Notification System"><span class="icon-wrap"><i class="bi bi-bell"></i></span><span>Notifications</span></a></li>
    </ul>
    <div class="sidebar-footer-box">
      <div class="system-status">
        <div class="d-flex align-items-center">
          <span class="dot status-ok"></span><strong>All Systems Operational</strong>
        </div>
        <div class="text-secondary" style="font-size:.65rem;">Up to date: <?php echo date('M j, Y'); ?></div>
      </div>
    </div>
    <div class="sidebar-logout">
      <a href="logout.php" class="btn btn-outline-danger w-100"><i class="bi bi-box-arrow-right me-1"></i> Logout</a>
    </div>
  </aside>

  <!-- Main Content -->
  <div class="content-area">
    <div class="topbar-modern">
      <button class="btn btn-outline-secondary btn-sm mobile-toggle-btn" id="sidebarToggle"><i class="bi bi-list"></i></button>
      <div class="greet">
        <strong id="currentModuleTitle">Dashboard</strong>
        <span>Barangay Health Center</span>
      </div>
      <div class="ms-auto d-flex align-items-center gap-3">
        <button class="notif-btn" id="notifBtn">
          <i class="bi bi-bell"></i>
          <span class="badge-count d-none" id="notifCount">0</span>
        </button>
        <div class="user-chip">
          <div class="user-avatar"><?php echo strtoupper(substr($firstName,0,1)); ?></div>
          <div class="d-flex flex-column lh-1">
            <span style="font-size:.78rem;font-weight:700;"><?php echo htmlspecialchars($username); ?></span>
            <small style="font-size:.63rem;color:#6a7a83;font-weight:600;">Barangay Health Worker</small>
          </div>
          <i class="bi bi-chevron-down ms-1" style="font-size:.7rem;opacity:.55;"></i>
        </div>
      </div>
    </div>
    <main id="mainRegion">
      <div id="moduleContent">
        <div class="loading-state">
          <div class="spinner-border text-success mb-3"></div>
          <div>Loading dashboard...</div>
        </div>
      </div>
    </main>
  </div>
</div>

<!-- Zoom Controls -->
<div class="zoom-controls" id="zoomControls">
  <button id="zoomIn" title="Larger (A+)">A+</button>
  <button id="zoomOut" title="Smaller (A-)">A-</button>
  <button id="zoomReset" title="Reset Size">Reset</button>
</div>

<script>
/* CSRF */
window.__BHW_CSRF = "<?php echo htmlspecialchars($csrf); ?>";
const moduleContent=document.getElementById('moduleContent');
const titleEl=document.getElementById('currentModuleTitle');

const api={
  mothers:'bhw_modules/api_mothers.php',
  health:'bhw_modules/api_health_records.php',
  postnatal:'bhw_modules/api_postnatal.php',
  immun:'bhw_modules/api_immunization.php',
  notif:'bhw_modules/api_notifications.php',
  caps:'bhw_modules/api_capabilities.php',
  parent:'bhw_modules/api_parent_accounts.php',
  events:'bhw_modules/api_events.php',
  reports:'bhw_modules/api_reports.php',
};

function escapeHtml(s){if(s==null)return'';return s.toString().replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');}
function fetchJSON(u,o={}){o.headers=Object.assign({'X-Requested-With':'fetch'},o.headers||{});return fetch(u,o).then(r=>{if(!r.ok)throw new Error('HTTP '+r.status);return r.json();});}
function setActiveLink(el){document.querySelectorAll('.nav-link-modern.active').forEach(a=>a.classList.remove('active'));el.classList.add('active');}
function showLoading(label){moduleContent.innerHTML=`<div class="loading-state"><div class="spinner-border text-success mb-3"></div><div>Loading ${escapeHtml(label)}...</div></div>`;}

/* Enlarged Dashboard Rendering */
function renderHealthStats(label){
  showLoading(label);
  Promise.allSettled([
    fetchJSON(api.reports+'?maternal_stats=1'),
    fetchJSON(api.reports+'?vaccination_coverage=1'),
    fetchJSON(api.reports+'?health_risks=1'),
    fetchJSON(api.immun+'?overdue=1'),
    fetchJSON(api.health+'?recent_consults=1&limit=60')
  ]).then(res=>{
    const ms=res[0].value||{},vc=res[1].value||{},hr=res[2].value||{},od=res[3].value||{},rc=res[4].value||{};
    if(!ms.success||!vc.success||!hr.success||!od.success){
      moduleContent.innerHTML='<div class="alert alert-danger">Incomplete data. Please refresh.</div>';return;
    }
    const coverage=vc.overall_dose_coverage_pct;
    const upcoming=buildUpcoming(upcomingMerge(od.overdue||[],od.dueSoon||[]).slice(0,9));
    const alerts=buildAlerts(hr,od.overdue||[],rc.recent_consults||[]);
    moduleContent.innerHTML=`
      <div class="fade-in">
        <div class="dashboard-welcome">Welcome Back, <?php echo htmlspecialchars($firstName); ?>!</div>
        <div class="dashboard-sub">Here’s what’s happening in your barangay today</div>
        <div class="metric-grid">
          ${metricCard('Maternal Cases', ms.total_mothers,'+3 from last month','bi-person-heart','moms')}
          ${metricCard('Vaccinations Given', vc.total_administered_doses,'+12 this week','bi-syringe','imm')}
          ${metricCard('Consultations', ms.total_consultations,'+8 this week','bi-activity','consult')}
          ${metricCard('Coverage Rate', coverage+'%','', 'bi-graph-up','coverage',coverage)}
        </div>
        <div class="dashboard-panels">
          <div class="panel-card">
            <div class="panel-title"><i class="bi bi-calendar-event"></i> Upcoming Immunizations</div>
            <div class="mini-text text-muted mb-3">Due and overdue vaccinations requiring attention</div>
            <div class="table-responsive" style="max-height:420px;">
              <table class="table-clean">
                <thead><tr><th>Child Name</th><th>Vaccine</th><th>Due Date</th><th>Status</th></tr></thead>
                <tbody>${upcoming||'<tr><td colspan="4" class="text-center text-muted py-4">No upcoming items.</td></tr>'}</tbody>
              </table>
            </div>
            <div class="text-end mt-3">
              <a href="#" class="link-clean" data-jump="overdue_alerts">Manage schedule →</a>
            </div>
          </div>
          <div class="panel-card">
            <div class="panel-title"><i class="bi bi-exclamation-octagon"></i> Health Alerts</div>
            <div class="alert-stack">${alerts||'<div class="text-muted" style="font-size:.78rem;">No high-risk cases currently.</div>'}</div>
            <div class="text-end mt-3">
              <a href="#" class="link-clean" data-jump="alert_system">View detailed alerts →</a>
            </div>
          </div>
        </div>
      </div>`;
    moduleContent.querySelectorAll('[data-jump]').forEach(a=>{
      a.addEventListener('click',e=>{
        e.preventDefault();
        const mod=a.getAttribute('data-jump');
        const link=document.querySelector(`.nav-link-modern[data-module="${mod}"]`);
        if(link){setActiveLink(link);loadModule(mod, link.dataset.label||link.textContent.trim());}
      });
    });

    function metricCard(title,value,diff,icon,type,progress=null){
      const diffClass=diff.startsWith('+')?'diff-up':'diff-down';
      const progressBar=(type==='coverage'&&progress!==null)?
        `<div class="progress-mini"><span style="width:${Math.min(100,parseFloat(progress)||0)}%"></span></div>`:'';
      return `<div class="metric-card">
        <div>
          <div class="metric-title"><i class="bi ${icon}"></i>${escapeHtml(title)}</div>
          <div class="metric-value">${value}</div>
          ${progressBar}
          ${diff?`<div class="metric-diff ${diffClass}">${escapeHtml(diff)}</div>`:''}
        </div>
      </div>`;
    }
    function upcomingMerge(overdue,dueSoon){
      const list=[],today=new Date();
      overdue.forEach(o=>list.push({child:o.child_name,vaccine:o.vaccine_code+' - '+ordinal(o.dose_number)+' Dose',due:o.due_date||null,status:'overdue'}));
      dueSoon.forEach(o=>{
        const due=o.due_date||null;
        let status='scheduled';
        if(due){
          const diff=(new Date(due)-today)/86400000;
          if(diff<0)status='overdue'; else if(diff<=10)status='due-soon';
        }
        list.push({child:o.child_name,vaccine:o.vaccine_code+' - '+ordinal(o.dose_number)+' Dose', due, status});
      });
      return list.sort((a,b)=>{
        if(a.status==='overdue'&&b.status!=='overdue')return -1;
        if(b.status==='overdue'&&a.status!=='overdue')return 1;
        return (new Date(a.due||'2100-01-01')) - (new Date(b.due||'2100-01-01'));
      });
    }
    function buildUpcoming(items){
      return items.map(i=>{
        let cls='st-sched',lbl='Scheduled';
        if(i.status==='overdue'){cls='st-overdue';lbl='Overdue';}
        else if(i.status==='due-soon'){cls='st-due';lbl='Due Soon';}
        return `<tr>
          <td>${escapeHtml(i.child)}</td>
          <td>${escapeHtml(i.vaccine)}</td>
          <td>${i.due?formatDate(i.due):'<span class="text-muted">—</span>'}</td>
          <td><span class="status-badge ${cls}"><i class="bi bi-clock"></i>${lbl}</span></td>
        </tr>`;
      }).join('');
    }
    function buildAlerts(hrData,overdueList,consults){
      const alerts=[], detail=hrData.details||[];
      const highBP=detail.find(r=>r.high_blood_pressure==1);
      if(highBP) alerts.push(alertBox('High Blood Pressure',`Patient: ${escapeHtml(highBP.full_name)} (${highBP.pregnancy_age_weeks||'?'} wks)<br>Requires follow-up`,'danger','bi-activity'));
      const abnormal=detail.find(r=>r.abnormal_presentation==1);
      if(abnormal) alerts.push(alertBox('Abnormal Presentation',`Patient: ${escapeHtml(abnormal.full_name)} (${abnormal.pregnancy_age_weeks||'?'} wks)<br>Breech/abnormal`,'danger','bi-person-exclamation'));
      const lowHgb=(consults||[]).find(c=>{
        if(!c.hgb_result)return false;
        const v=parseFloat(String(c.hgb_result).replace(/[^\d.]/g,''));return !isNaN(v)&&v<10;
      });
      if(lowHgb) alerts.push(alertBox('Low Hemoglobin',`Patient: ${escapeHtml(lowHgb.full_name)} (${lowHgb.pregnancy_age_weeks||'?'} wks)<br>HGB ${escapeHtml(lowHgb.hgb_result)} - monitor`,'warn','bi-droplet-half'));
      if(overdueList.length>0) alerts.push(alertBox('Vaccination Overdue',`${overdueList.length} child${overdueList.length>1?'ren':''} overdue`,'warn','bi-bell'));
      return alerts.join('');
    }
    function alertBox(title,desc,variant,icon){
      const variantClass=variant==='danger'?'danger':(variant==='warn'?'warn':'info');
      return `<div class="alert-box ${variantClass}">
        <div class="alert-icon ${variantClass}"><i class="bi ${icon}"></i></div>
        <div class="alert-content">
          <div class="alert-title">${escapeHtml(title)}</div>
          <div class="alert-desc">${desc}</div>
        </div>
      </div>`;
    }
    function ordinal(n){n=parseInt(n,10)||0;const s=["th","st","nd","rd"],v=n%100;return n+(s[(v-20)%10]||s[v]||s[0]);}
    function formatDate(d){if(!d)return'';const dt=new Date(d+'T00:00:00');if(isNaN(dt))return escapeHtml(d);return dt.toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'});}
  }).catch(err=>{
    moduleContent.innerHTML='<div class="alert alert-danger">Error: '+escapeHtml(err.message)+'</div>';
  });
}

function renderMaternalHealth(label){
  showLoading(label);

  Promise.allSettled([
    fetchJSON(api.mothers+'?list=1'),          // full mother list with counts
    fetchJSON(api.health+'?risk_summary=1'),   // latest consult + risk per mother
    fetchJSON(api.postnatal+'?followups=1')    // optional; if not implemented will just fail silently
  ]).then(results=>{
    const mothersFull = results[0].value?.mothers || [];
    const riskRows    = results[1].value?.risks || [];
    const postnatalData = (results[2].status==='fulfilled' && results[2].value?.followups) ? results[2].value.followups : [];

    // Build quick maps
    const riskMap = {};
    riskRows.forEach(r=> riskMap[r.mother_id] = r);

    // Compute metrics
    const activeCases = mothersFull.length;
    const highRisk = riskRows.filter(r=> (parseInt(r.risk_score||0,10)) >= 2).length;

    const today = new Date();
    const thisMonth = today.getMonth();
    const thisYear  = today.getFullYear();

    function parseDate(d){
      if(!d) return null;
      const dt = new Date(d+'T00:00:00');
      return isNaN(dt)? null : dt;
    }

    let dueThisMonth = 0;
    mothersFull.forEach(m=>{
      const latest = riskMap[m.mother_id];
      const edd = latest?.expected_delivery_date || latest?.edd || m.expected_delivery_date;
      const dt  = parseDate(edd);
      if(dt && dt.getMonth()===thisMonth && dt.getFullYear()===thisYear){
        dueThisMonth++;
      }
    });

    // Postnatal follow-ups (fallback heuristic if endpoint missing)
    let postnatalCount;
    if(postnatalData.length){
      postnatalCount = postnatalData.filter(v=> v.needs_followup==1).length;
    } else {
      postnatalCount = riskRows.filter(r=>{
        // Consider GA >= 40 or past EDD as needing postnatal follow-up
        const ga = parseInt(r.pregnancy_age_weeks||0,10);
        const edd = parseDate(r.expected_delivery_date);
        return ga>=40 || (edd && edd < today);
      }).length;
    }

    // Patient list rows
    const patientRowsHtml = mothersFull.map(m=>{
      const latest = riskMap[m.mother_id];
      const riskScore = parseInt(latest?.risk_score||0,10);
      const gaWeeks = latest?.pregnancy_age_weeks !== null && latest?.pregnancy_age_weeks !== undefined
        ? parseInt(latest.pregnancy_age_weeks,10)
        : null;

      let riskLevelClass='risk-normal', riskLabel='Normal';
      if(riskScore >= 2){ riskLevelClass='risk-high'; riskLabel='High Risk'; }
      else if(riskScore === 1){ riskLevelClass='risk-monitor'; riskLabel='Monitor'; }

      // Progress
      let pct = gaWeeks? Math.min(100, Math.round((gaWeeks/40)*100)) : 0;
      if(pct<0) pct=0;

      // EDD
      let eddTxt = latest?.expected_delivery_date || latest?.edd || m.expected_delivery_date || '';
      if(!eddTxt && gaWeeks && latest?.consultation_date){
        // approximate EDD = consultation_date + (40 - GA) weeks
        const consultDate = parseDate(latest.consultation_date);
        if(consultDate){
          const approx = new Date(consultDate.getTime() + (40 - gaWeeks)*7*86400000);
            eddTxt = approx.toISOString().slice(0,10);
        }
      }

      const ageVal = m.date_of_birth ? calcAge(m.date_of_birth) : '';
      function calcAge(dob){
        const d=new Date(dob+'T00:00:00'); if(isNaN(d)) return '';
        let a=today.getFullYear()-d.getFullYear();
        const mm=today.getMonth()-d.getMonth();
        if(mm<0 || (mm===0 && today.getDate()<d.getDate())) a--;
        return a;
      }

      return `
        <tr class="mh-fade-in">
          <td>${escapeHtml(m.full_name)}</td>
          <td>${ageVal || '—'}</td>
          <td class="mh-progress-wrap">
            ${gaWeeks?`
              <div class="mh-progress">
                 <div class="mh-progress-bar ${riskLevelClass.replace('risk-','risk-')}" style="width:${pct}%;"></div>
              </div>
              <div class="mh-weeks-label">${gaWeeks} weeks</div>
            `:'<span class="text-muted" style="font-size:.64rem;">No data</span>'}
          </td>
          <td>${eddTxt?escapeHtml(eddTxt):'<span class="text-muted">—</span>'}</td>
          <td><span class="risk-badge ${riskLevelClass}">${riskLabel}</span></td>
          <td>
            <button class="mh-action-btn btn-view" data-id="${m.mother_id}"><i class="bi bi-eye me-1"></i>View</button>
          </td>
        </tr>`;
    }).join('');

    moduleContent.innerHTML = `
      <div class="mh-module-wrapper">
        <div class="mh-header d-flex flex-wrap justify-content-between align-items-start gap-3">
          <div>
            <h2 class="mh-title">Maternal Health Management</h2>
            <p class="mh-sub">Track prenatal, pregnancy, and postnatal care</p>
          </div>
          <button class="btn btn-success mh-add-btn" id="btnRegisterMother">
            <i class="bi bi-plus-lg"></i> Register New Mother
          </button>
        </div>

        <div class="mh-metrics row g-3">
          <div class="col-sm-6 col-xl-3">
            <div class="mh-metric-card">
              <div class="mh-metric-label"><i class="bi bi-person-heart"></i> Active Cases</div>
              <div class="mh-metric-value">${activeCases}</div>
              <div class="mh-metric-sub">Pregnant mothers</div>
            </div>
          </div>
          <div class="col-sm-6 col-xl-3">
            <div class="mh-metric-card">
              <div class="mh-metric-label"><i class="bi bi-exclamation-triangle"></i> High Risk</div>
              <div class="mh-metric-value">${highRisk}</div>
              <div class="mh-metric-sub">Require attention</div>
            </div>
          </div>
            <div class="col-sm-6 col-xl-3">
            <div class="mh-metric-card">
              <div class="mh-metric-label"><i class="bi bi-calendar-event"></i> Due This Month</div>
              <div class="mh-metric-value">${dueThisMonth}</div>
              <div class="mh-metric-sub">Expected deliveries</div>
            </div>
          </div>
          <div class="col-sm-6 col-xl-3">
            <div class="mh-metric-card">
              <div class="mh-metric-label"><i class="bi bi-activity"></i> Postnatal Care</div>
              <div class="mh-metric-value">${postnatalCount}</div>
              <div class="mh-metric-sub">Follow-ups needed</div>
            </div>
          </div>
        </div>

        <div class="mh-tabs nav" id="mhTabs">
          <button class="nav-link active" data-tab="patients">Patient List</button>
          <button class="nav-link" data-tab="consults">Consultations</button>
          <button class="nav-link" data-tab="monitor">Pregnancy Monitoring</button>
          <button class="nav-link" data-tab="postnatal">Postnatal Care</button>
        </div>

        <div id="mhPanel"></div>
      </div>

      <!-- Register Mother Modal -->
      <div class="modal fade mh-modal" id="modalRegisterMother" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg">
          <div class="modal-content">
            <form id="motherForm">
              <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-person-plus me-1"></i> Register New Mother</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
              </div>
              <div class="modal-body row g-3">
                <div class="col-md-6">
                  <label>Full Name *</label>
                  <input name="full_name" class="form-control" required>
                </div>
                <div class="col-md-6">
                  <label>Purok *</label>
                  <input name="purok_name" class="form-control" required placeholder="e.g. Purok 1">
                </div>
                <div class="col-md-6">
                  <label>Date of Birth</label>
                  <input type="date" name="date_of_birth" class="form-control">
                </div>
                <div class="col-md-6">
                  <label>Contact Number</label>
                  <input name="contact_number" class="form-control">
                </div>
                <div class="col-md-6">
                  <label>Gravida</label>
                  <input type="number" min="0" name="gravida" class="form-control">
                </div>
                <div class="col-md-6">
                  <label>Para</label>
                  <input type="number" min="0" name="para" class="form-control">
                </div>
                <div class="col-md-6">
                  <label>Blood Type</label>
                  <input name="blood_type" class="form-control" placeholder="O+ / A- ...">
                </div>
                <div class="col-md-6">
                  <label>Emergency Contact Name</label>
                  <input name="emergency_contact_name" class="form-control">
                </div>
                <div class="col-md-6">
                  <label>Emergency Contact No.</label>
                  <input name="emergency_contact_number" class="form-control">
                </div>
                <div class="col-12">
                  <label>Address / Additional Info</label>
                  <textarea name="address_details" rows="2" class="form-control" placeholder="House # / Landmark"></textarea>
                </div>
                <input type="hidden" name="csrf_token" value="${window.__BHW_CSRF}">
                <div class="col-12">
                  <div class="form-text">Ensure no duplicate name before saving.</div>
                  <div class="text-danger small d-none" id="motherError"></div>
                  <div class="text-success small d-none" id="motherSuccess">Saved!</div>
                </div>
              </div>
              <div class="modal-footer">
                <button class="btn btn-success"><i class="bi bi-save me-1"></i> Save</button>
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
              </div>
            </form>
          </div>
        </div>
      </div>

      <!-- View Mother Modal Placeholder -->
      <div class="modal fade mh-modal" id="modalViewMother" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-xl">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title" id="viewMotherTitle">Mother Details</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="viewMotherBody">
              <div class="text-center py-4 text-muted">Select a mother from the Patient List.</div>
            </div>
            <div class="modal-footer">
              <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
            </div>
          </div>
        </div>
      </div>
    `;

    // Insert Patient List panel by default
    loadPatientList();

    function loadPatientList(){
      const panel = document.getElementById('mhPanel');
      panel.innerHTML = `
        <div class="mh-card">
          <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
            <div>
              <h6 class="mh-card-title mb-1">Registered Mothers</h6>
              <div class="mh-card-sub">All active maternal health cases</div>
            </div>
            <div class="mh-search-wrap">
              <input class="form-control" placeholder="Search name / purok..." id="mhSearch">
              <div class="mh-filter-badge active" data-filter="all">All</div>
              <div class="mh-filter-badge" data-filter="high">High</div>
              <div class="mh-filter-badge" data-filter="monitor">Monitor</div>
              <div class="mh-filter-badge" data-filter="normal">Normal</div>
            </div>
          </div>
          <div class="table-responsive" style="max-height:560px;">
            <table class="mh-table" id="mhPatientsTable">
              <thead>
                <tr>
                  <th>Name</th>
                  <th>Age</th>
                  <th>Pregnancy Age</th>
                  <th>EDD</th>
                  <th>Risk Level</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>${patientRowsHtml || `<tr><td colspan="6" class="mh-empty">No mothers registered yet.</td></tr>`}</tbody>
            </table>
          </div>
        </div>
      `;

      // Search + filter
      const searchInput = document.getElementById('mhSearch');
      const filterBadges = panel.querySelectorAll('.mh-filter-badge');
      const rows = [...panel.querySelectorAll('#mhPatientsTable tbody tr')];

      function applyFilter(){
        const q = (searchInput.value||'').toLowerCase();
        const activeFilter = panel.querySelector('.mh-filter-badge.active')?.dataset.filter || 'all';
        rows.forEach(r=>{
          let show = true;
            if(q){
              const text = r.innerText.toLowerCase();
              show = text.includes(q);
            }
          if(show && activeFilter!=='all'){
            const badge = r.querySelector('.risk-badge');
            if(activeFilter==='high') show = badge?.classList.contains('risk-high');
            if(activeFilter==='monitor') show = badge?.classList.contains('risk-monitor');
            if(activeFilter==='normal') show = badge?.classList.contains('risk-normal');
          }
          r.classList.toggle('d-none', !show);
        });
      }
      searchInput.addEventListener('input', applyFilter);
      filterBadges.forEach(b=>{
        b.addEventListener('click',()=>{
          filterBadges.forEach(x=>x.classList.remove('active'));
          b.classList.add('active');
          applyFilter();
        });
      });

      // View buttons
      panel.querySelectorAll('.btn-view').forEach(btn=>{
        btn.addEventListener('click',()=>{
          const id = btn.dataset.id;
          openMotherModal(id);
        });
      });
    }

    // Tabs switching (stubs for now – plug original internal logic if desired)
document.getElementById('mhTabs').addEventListener('click',e=>{
  const btn = e.target.closest('.nav-link');
  if(!btn) return;
  document.querySelectorAll('#mhTabs .nav-link').forEach(b=>b.classList.remove('active'));
  btn.classList.add('active');
  const tab = btn.dataset.tab;
  if(tab==='patients') loadPatientList();
  else if(tab==='consults') loadConsultsPanel();
  else if(tab==='monitor') loadMonitorPanel();
  else if(tab==='postnatal') loadPostnatalPanel();
});

    function placeholderPanel(title, note){
      return `<div class="mh-card"><h6 class="mh-card-title mb-1">${title}</h6>
      <div class="mh-card-sub mb-3">${note}</div>
      <div class="text-muted" style="font-size:.75rem;">(Implement by integrating your previous sub-module logic here.)</div>
      </div>`;
    }

    function loadConsultsPanel(){
      const panel = document.getElementById('mhPanel');
      const mothers = mothersFull.slice().sort((a,b)=>a.full_name.localeCompare(b.full_name));
      let activeMotherId = mothers.length ? mothers[0].mother_id : null;

      panel.innerHTML = `
        <div class="mh-consult-layout">
          <div class="mh-mother-list">
            <h6>Mothers</h6>
            <input type="text" class="form-control mh-mother-search" id="mhMotherSearch" placeholder="Search mother / purok">
            <div class="mh-mother-scroll" id="mhMotherList">
              ${mothers.map(m=>`
                <div class="mh-mother-item ${m.mother_id===activeMotherId?'active':''}" data-id="${m.mother_id}">
                  <span>${escapeHtml(m.full_name)}</span>
                  <small>${escapeHtml(m.purok_name||'')}</small>
                </div>
              `).join('')}
            </div>
          </div>
          <div class="mh-consult-main" id="mhConsultMain">
            <div class="text-muted" style="font-size:.7rem;">Loading consultations...</div>
          </div>
        </div>
      `;

      const motherListEl = panel.querySelector('#mhMotherList');
      const searchInput = panel.querySelector('#mhMotherSearch');

      function filterMothers(){
        const q = (searchInput.value||'').toLowerCase();
        motherListEl.querySelectorAll('.mh-mother-item').forEach(it=>{
          const txt = it.innerText.toLowerCase();
          it.classList.toggle('d-none', !txt.includes(q));
        });
      }
      searchInput.addEventListener('input',filterMothers);

      motherListEl.addEventListener('click',e=>{
        const item = e.target.closest('.mh-mother-item');
        if(!item) return;
        motherListEl.querySelectorAll('.mh-mother-item').forEach(i=>i.classList.remove('active'));
        item.classList.add('active');
        activeMotherId = parseInt(item.dataset.id,10);
        loadMotherConsultations();
      });

      if(activeMotherId) loadMotherConsultations();

      function loadMotherConsultations(){
        const mother = mothers.find(m=>m.mother_id==activeMotherId);
        const wrap = panel.querySelector('#mhConsultMain');
        wrap.innerHTML = `<h6>Consultations - ${escapeHtml(mother.full_name)}
          <span class="badge-ga" id="gaBadge">GA: --</span></h6>
          <div class="row g-4">
            <div class="col-lg-5">
              <form class="mh-consult-form" id="consultForm" autocomplete="off">
                <div class="row g-2">
                  <div class="col-12">
                    <label>Consultation Date *</label>
                    <input type="date" name="consultation_date" class="form-control" required value="${new Date().toISOString().slice(0,10)}">
                  </div>

                  <div class="col-4">
                    <label>Age</label>
                    <input type="number" name="age" class="form-control" placeholder="Auto">
                  </div>
                  <div class="col-4">
                    <label>Ht (cm)</label>
                    <input type="number" step="0.1" name="height_cm" class="form-control">
                  </div>
                  <div class="col-4">
                    <label>Wt (kg)</label>
                    <input type="number" step="0.01" name="weight_kg" class="form-control">
                  </div>

                  <div class="col-4">
                    <label>BP Sys</label>
                    <input type="number" name="blood_pressure_systolic" class="form-control">
                  </div>
                  <div class="col-4">
                    <label>BP Dia</label>
                    <input type="number" name="blood_pressure_diastolic" class="form-control">
                  </div>
                  <div class="col-4">
                    <label>Preg Weeks</label>
                    <input type="number" min="0" max="45" name="pregnancy_age_weeks" class="form-control" placeholder="Auto" data-autofill="1">
                  </div>

                  <div class="col-6">
                    <label>LMP</label>
                    <input type="date" name="last_menstruation_date" class="form-control">
                  </div>
                  <div class="col-6">
                    <label>EDD</label>
                    <input type="date" name="expected_delivery_date" class="form-control">
                  </div>
                  <div class="col-12">
                    <div class="mh-inline-hint">Auto mula LMP/EDD (pwede i-override ang Pregnancy Weeks & Age).</div>
                  </div>
                </div>

                <div class="mh-form-divider"></div>

                <label style="margin-bottom:.4rem;">Labs</label>
                <div class="row g-2 mb-2">
                  <div class="col-6">
                    <input name="hgb_result" class="form-control" placeholder="HGB">
                  </div>
                  <div class="col-6">
                    <input name="urine_result" class="form-control" placeholder="Urine">
                  </div>
                  <div class="col-6">
                    <input name="vdrl_result" class="form-control" placeholder="VDRL">
                  </div>
                  <div class="col-6">
                    <input name="other_lab_results" class="form-control" placeholder="Other lab results">
                  </div>
                </div>

                <div class="mh-form-divider"></div>
                <label style="margin-bottom:.4rem;">Risk Flags</label>
                <div class="mh-risks-wrap mb-2">
                  ${[
                    ['vaginal_bleeding','Vaginal Bleeding'],
                    ['urinary_infection','Urinary Infection'],
                    ['high_blood_pressure','High BP'],
                    ['fever_38_celsius','Fever ≥38°C'],
                    ['pallor','Pallor'],
                    ['abnormal_abdominal_size','Abnormal Abd Size'],
                    ['abnormal_presentation','Abnormal Presentation'],
                    ['absent_fetal_heartbeat','No Fetal Heartbeat'],
                    ['swelling','Swelling'],
                    ['vaginal_infection','Vag Infection']
                  ].map(([k,l])=>`
                    <label class="mh-risk-box">
                      <input type="checkbox" name="${k}" value="1">
                      <span>${l}</span>
                    </label>
                  `).join('')}
                </div>

                <input type="hidden" name="mother_id" value="${activeMotherId}">
                <input type="hidden" name="csrf_token" value="${window.__BHW_CSRF}">
                <div class="mt-3 d-flex gap-2">
                  <button class="btn btn-success mh-save-btn"><i class="bi bi-save me-1"></i>Save</button>
                  <button type="reset" class="btn btn-outline-secondary mh-save-btn">Reset</button>
                </div>
                <div class="small text-danger mt-2 d-none" id="consultErr"></div>
                <div class="small text-success mt-2 d-none" id="consultOk">Saved!</div>
              </form>
            </div>
            <div class="col-lg-7">
              <div id="consultListBox">
                <div class="text-muted" style="font-size:.7rem;">Loading records...</div>
              </div>
            </div>
          </div>
        `;

        // Load existing consultation list
        fetchJSON(api.health+`?list=1&mother_id=${activeMotherId}`)
          .then(j=>{ if(!j.success) throw new Error('Load failed'); renderConsultTable(j.records||[]); })
          .catch(err=>{
            panel.querySelector('#consultListBox').innerHTML = `<div class="text-danger small">Error loading consultations: ${escapeHtml(err.message)}</div>`;
          });

        const form = wrap.querySelector('#consultForm');
        const cdEl  = form.querySelector('[name=consultation_date]');
        const lmpEl = form.querySelector('[name=last_menstruation_date]');
        const eddEl = form.querySelector('[name=expected_delivery_date]');
        const gaEl  = form.querySelector('[name=pregnancy_age_weeks]');
        const ageEl = form.querySelector('[name=age]');
        const gaBadge = wrap.querySelector('#gaBadge');

        function autoAge(){
          if(!mother?.date_of_birth) return;
            const cd = cdEl.value;
            if(!cd) return;
            const dob = new Date(mother.date_of_birth+'T00:00:00');
            const cdt = new Date(cd+'T00:00:00');
            if(isNaN(dob)||isNaN(cdt)) return;
            let age = cdt.getFullYear()-dob.getFullYear();
            const m = cdt.getMonth()-dob.getMonth();
            if(m<0||(m===0 && cdt.getDate()<dob.getDate())) age--;
            if(ageEl.value==='' || ageEl.dataset.autofill==='1'){
              ageEl.value = age;
              ageEl.dataset.autofill='1';
            }
        }

        function autoGA(){
          const lmp = lmpEl.value;
          const edd = eddEl.value;
          const cd  = cdEl.value;
          if(!cd){ gaBadge.textContent='GA: --'; return; }
          let weeks=null;
          const cdDate = new Date(cd+'T00:00:00');
          if(lmp){
            const lmpDate = new Date(lmp+'T00:00:00');
            const diff = (cdDate - lmpDate)/(1000*60*60*24);
            if(diff>=0) weeks = Math.floor(diff/7);
          } else if(edd){
            const eddDate = new Date(edd+'T00:00:00');
            const diff = (eddDate - cdDate)/(1000*60*60*24);
            const wToEdd = diff/7;
            weeks = Math.round(40 - wToEdd);
          }
          if(weeks!==null && (gaEl.value==='' || gaEl.dataset.autofill==='1')){
            gaEl.value = weeks;
            gaEl.dataset.autofill='1';
          }
          gaBadge.textContent = 'GA: ' + (weeks!==null? weeks+' wks':'--');
        }

        [cdEl,lmpEl,eddEl].forEach(el=>el.addEventListener('change',()=>{
          autoGA(); autoAge();
        }));
        gaEl.addEventListener('input',()=>{ gaEl.dataset.autofill='0'; gaBadge.textContent='GA: '+(gaEl.value?gaEl.value+' wks':'--'); });
        ageEl.addEventListener('input',()=>{ ageEl.dataset.autofill='0'; });

        // Initial auto fill
        autoAge(); autoGA();

        form.addEventListener('submit',e=>{
          e.preventDefault();
          const fd = new FormData(form);
          fetch(api.health,{method:'POST',body:fd})
            .then(r=>r.json())
            .then(j=>{
              if(!j.success) throw new Error(j.error||'Save failed');
              form.querySelector('#consultErr').classList.add('d-none');
              const okEl=form.querySelector('#consultOk');
              okEl.classList.remove('d-none');
              setTimeout(()=>okEl.classList.add('d-none'),1500);

              // Refresh consult list
              return fetchJSON(api.health+`?list=1&mother_id=${activeMotherId}`);
            })
            .then(j=>{ renderConsultTable(j.records||[]); })
            .catch(err=>{
              const ce=form.querySelector('#consultErr');
              ce.textContent=err.message;
              ce.classList.remove('d-none');
            });
        });

        function renderConsultTable(records){
          const box = panel.querySelector('#consultListBox');
          if(!records.length){
            box.innerHTML='<div class="text-muted" style="font-size:.7rem;">No consultations yet.</div>';
            return;
          }
            let rows = records.map(r=>{
              const riskScore =
                (parseInt(r.vaginal_bleeding)+parseInt(r.urinary_infection)+parseInt(r.high_blood_pressure)+
                 parseInt(r.fever_38_celsius)+parseInt(r.pallor)+parseInt(r.abnormal_abdominal_size)+
                 parseInt(r.abnormal_presentation)+parseInt(r.absent_fetal_heartbeat)+parseInt(r.swelling)+
                 parseInt(r.vaginal_infection));
              let riskCls='consult-risk-normal', riskLbl='Normal';
              if(riskScore>=2){ riskCls='consult-risk-high'; riskLbl='High'; }
              else if(riskScore===1){ riskCls='consult-risk-monitor'; riskLbl='Monitor'; }
              return `
                <tr>
                  <td>${escapeHtml(r.consultation_date||'')}</td>
                  <td>${r.pregnancy_age_weeks!=null? r.pregnancy_age_weeks+'w':''}</td>
                  <td>${(r.blood_pressure_systolic && r.blood_pressure_diastolic)? `${r.blood_pressure_systolic}/${r.blood_pressure_diastolic}`:''}</td>
                  <td>${r.weight_kg!=null? escapeHtml(r.weight_kg):''}</td>
                  <td>${r.hgb_result?escapeHtml(r.hgb_result):''}</td>
                  <td><span class="consult-risk-badge ${riskCls}">${riskLbl}</span></td>
                  <td>${flagsIcons(r)}</td>
                </tr>
              `;
            }).join('');
            box.innerHTML = `
              <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="m-0" style="font-size:.7rem;">Consultation History</h6>
                <span class="text-muted" style="font-size:.6rem;">${records.length} record(s)</span>
              </div>
              <div class="table-responsive" style="max-height:460px;">
                <table class="mh-consults-table">
                  <thead>
                    <tr>
                      <th>Date</th><th>GA</th><th>BP</th><th>Wt</th><th>HGB</th><th>Risk</th><th>Flags</th>
                    </tr>
                  </thead>
                  <tbody>${rows}</tbody>
                </table>
              </div>
            `;
        }

        function flagsIcons(r){
          const map = {
            vaginal_bleeding:'VB',
            urinary_infection:'UTI',
            high_blood_pressure:'BP',
            fever_38_celsius:'FEV',
            pallor:'PAL',
            abnormal_abdominal_size:'ABD',
            abnormal_presentation:'PRES',
            absent_fetal_heartbeat:'FHT',
            swelling:'SWL',
            vaginal_infection:'VAG'
          };
          const outs=[];
          Object.keys(map).forEach(k=>{
            if(r[k]==1){
              outs.push(`<span style="display:inline-block;background:#e7efe9;color:#134a3d;font-size:.5rem;font-weight:700;padding:2px 5px;border-radius:8px;margin:1px;">${map[k]}</span>`);
            }
          });
          return outs.join('');
        }
      } // end loadMotherConsultations
    } // end loadConsultsPanel

    function loadMonitorPanel(){
      const panel = document.getElementById('mhPanel');
      const mothers = mothersFull.slice().sort((a,b)=>a.full_name.localeCompare(b.full_name));
      let activeMotherId = mothers.length ? mothers[0].mother_id : null;

      panel.innerHTML = `
        <div class="mh-monitor-layout">
          <div class="mh-mother-list">
            <h6>Mothers</h6>
            <input type="text" class="form-control mh-mother-search" id="mhMonMotherSearch" placeholder="Search mother / purok">
            <div class="mh-mother-scroll" id="mhMonMotherList">
              ${mothers.map(m=>`
                <div class="mh-mother-item ${m.mother_id===activeMotherId?'active':''}" data-id="${m.mother_id}">
                  <span>${escapeHtml(m.full_name)}</span>
                  <small>${escapeHtml(m.purok_name||'')}</small>
                </div>
              `).join('')}
            </div>
          </div>
          <div class="mh-mon-main" id="mhMonMain">
            <div class="text-muted" style="font-size:.7rem;">Select mother or loading...</div>
          </div>
        </div>
      `;

      const motherListEl = panel.querySelector('#mhMonMotherList');
      const searchInput = panel.querySelector('#mhMonMotherSearch');

      function filterMothers(){
        const q=(searchInput.value||'').toLowerCase();
        motherListEl.querySelectorAll('.mh-mother-item').forEach(it=>{
          const txt=it.innerText.toLowerCase();
          it.classList.toggle('d-none', !txt.includes(q));
        });
      }
      searchInput.addEventListener('input',filterMothers);

      motherListEl.addEventListener('click',e=>{
        const item=e.target.closest('.mh-mother-item');
        if(!item) return;
        motherListEl.querySelectorAll('.mh-mother-item').forEach(i=>i.classList.remove('active'));
        item.classList.add('active');
        activeMotherId=parseInt(item.dataset.id,10);
        loadMonitoring();
      });

      if(activeMotherId) loadMonitoring();

      function loadMonitoring(){
        const mother = mothers.find(m=>m.mother_id==activeMotherId);
        const box = panel.querySelector('#mhMonMain');
        box.innerHTML = `<div class="text-muted" style="font-size:.7rem;">Loading monitoring data...</div>`;
        fetchJSON(api.health+`?list=1&mother_id=${activeMotherId}`).then(j=>{
          if(!j.success) throw new Error('Load failed');
          renderMonitoring(mother,j.records||[]);
        }).catch(err=>{
          box.innerHTML = `<div class="text-danger small">Error loading: ${escapeHtml(err.message)}</div>`;
        });
      }

      function renderMonitoring(mother,records){
        const box = panel.querySelector('#mhMonMain');
        if(!records.length){
          box.innerHTML = `
            <div class="mh-mon-head d-flex justify-content-between align-items-center mb-2">
              <h6>Pregnancy Monitoring - ${escapeHtml(mother.full_name)}</h6>
              <div class="mh-mon-actions">
                <button class="btn btn-sm btn-outline-success" id="goAddConsult"><i class="bi bi-plus-lg me-1"></i>Add Consultation</button>
              </div>
            </div>
            <div class="mh-mon-empty">
              Walang consultation records. Magdagdag muna ng consultation para makita ang monitoring timeline.
            </div>`;
          box.querySelector('#goAddConsult')?.addEventListener('click',()=>{
            const tabBtn=document.querySelector('#mhTabs .nav-link[data-tab="consults"]');
            tabBtn?.click();
          });
          return;
        }

        // sort ascending by consultation_date
        records.sort((a,b)=>{
          return (a.consultation_date||'') < (b.consultation_date||'') ? -1
               : (a.consultation_date||'') > (b.consultation_date||'') ? 1 : a.health_record_id - b.health_record_id;
        });

        // compute risk score per record
        records.forEach(r=>{
          r._risk_score =
            ['vaginal_bleeding','urinary_infection','high_blood_pressure','fever_38_celsius','pallor',
             'abnormal_abdominal_size','abnormal_presentation','absent_fetal_heartbeat','swelling','vaginal_infection']
             .reduce((acc,k)=>acc + (parseInt(r[k])||0),0);
        });

        const first = records[0];
        const latest = records[records.length-1];

        function num(v){const n=parseFloat(v);return isNaN(n)?null:n;}
        const firstWeight = num(first.weight_kg);
        const latestWeight = num(latest.weight_kg);
        const weightGain = (firstWeight!=null && latestWeight!=null) ? (latestWeight - firstWeight) : null;

        const systolics = records.map(r=>num(r.blood_pressure_systolic)).filter(v=>v!=null);
        const diastolics = records.map(r=>num(r.blood_pressure_diastolic)).filter(v=>v!=null);
        const avgSys = systolics.length? Math.round(systolics.reduce((a,b)=>a+b,0)/systolics.length) : null;
        const avgDia = diastolics.length? Math.round(diastolics.reduce((a,b)=>a+b,0)/diastolics.length) : null;

        const currentGA = latest.pregnancy_age_weeks!=null ? parseInt(latest.pregnancy_age_weeks,10) : null;
        const weeksToEDD = currentGA!=null ? (40 - currentGA) : null;
        const riskScoreLatest = latest._risk_score;
        let riskClass='risk-normal', riskLabel='Normal';
        if(riskScoreLatest>=2){riskClass='risk-high';riskLabel='High';}
        else if(riskScoreLatest===1){riskClass='risk-monitor';riskLabel='Monitor';}

        const progressPct = currentGA!=null ? Math.min(100, Math.max(0, Math.round((currentGA/40)*100))) : 0;

        const flagsCount = {
          vaginal_bleeding:0, urinary_infection:0, high_blood_pressure:0, fever_38_celsius:0,
          pallor:0, abnormal_abdominal_size:0, abnormal_presentation:0, absent_fetal_heartbeat:0,
          swelling:0, vaginal_infection:0
        };
        records.forEach(r=>{
          Object.keys(flagsCount).forEach(k=>{ if(parseInt(r[k])===1) flagsCount[k]++; });
        });

        const weightSeries = records.map(r=>num(r.weight_kg)).filter(v=>v!=null);
        const sysSeries = systolics;

        // Sparkline SVG builders
        function sparkline(data,color){
          if(!data.length) return '<svg class="sparkline"></svg>';
            const w=260,h=60,pad=4;
            const min=Math.min(...data), max=Math.max(...data);
            const range = (max-min)||1;
            const step = (w - pad*2) / Math.max(1,data.length-1);
            let d='';
            data.forEach((v,i)=>{
              const x = pad + i*step;
              const y = h - pad - ((v-min)/range)*(h-pad*2);
              d += (i===0?'M':'L')+x+' '+y+' ';
            });
            return `<svg class="sparkline" viewBox="0 0 ${w} ${h}" preserveAspectRatio="none">
              <path class="axis" d="M${pad} ${h-pad}H${w-pad}"></path>
              <path d="${d.trim()}" stroke="${color}" fill="none"></path>
            </svg>`;
        }

        // Build risk chip legend counts
        function riskChips(){
          const mapLabels={
            vaginal_bleeding:'VB', urinary_infection:'UTI', high_blood_pressure:'HBP', fever_38_celsius:'FEV',
            pallor:'PAL', abnormal_abdominal_size:'ABD', abnormal_presentation:'PRES', absent_fetal_heartbeat:'FHT',
            swelling:'SWL', vaginal_infection:'VAG'
          };
          return Object.keys(flagsCount).map(k=>{
            const on = flagsCount[k]>0;
            return `<span class="mh-risk-chip ${on?'on':'off'}" title="${mapLabels[k]}: ${flagsCount[k]} rec(s)">${mapLabels[k]}</span>`;
          }).join('');
        }

        // Table rows with deltas
        let prev=null;
        const tableRows = records.map(r=>{
          const w = num(r.weight_kg);
          const sys = num(r.blood_pressure_systolic);
          const dia = num(r.blood_pressure_diastolic);
          let dW='', dBP='';
          if(prev){
            if(w!=null && prev.w!=null){
              const diff = +(w - prev.w).toFixed(2);
              dW = diff===0?'0': (diff>0?`<span class="mh-delta-plus">+${diff}</span>`:`<span class="mh-delta-minus">${diff}</span>`);
            }
            if(sys!=null && dia!=null && prev.sys!=null && prev.dia!=null){
              const dS = sys - prev.sys;
              const dD = dia - prev.dia;
              const part=(x)=> x===0?'0':(x>0?`<span class="mh-delta-plus">+${x}</span>`:`<span class="mh-delta-minus">${x}</span>`);
              dBP = `${part(dS)}/${part(dD)}`;
            }
          }
          prev={w,sys,dia};

            const rs = r._risk_score;
            let rCls='consult-risk-normal', rLbl='Normal';
            if(rs>=2){rCls='consult-risk-high';rLbl='High';}
            else if(rs===1){rCls='consult-risk-monitor';rLbl='Monitor';}

          return `<tr>
            <td>${escapeHtml(r.consultation_date||'')}</td>
            <td>${r.pregnancy_age_weeks!=null? r.pregnancy_age_weeks:'—'}</td>
            <td>${w!=null? w:'—'} ${dW?'<br><small>'+dW+'</small>':''}</td>
            <td>${(sys!=null&&dia!=null)? `${sys}/${dia}`:'—'} ${dBP?'<br><small>'+dBP+'</small>':''}</td>
            <td><span class="consult-risk-badge ${rCls}" style="font-size:.5rem;">${rLbl}</span></td>
          </tr>`;
        }).join('');

        box.innerHTML = `
          <div class="mh-mon-head d-flex flex-wrap justify-content-between align-items-center">
            <h6>Pregnancy Monitoring - ${escapeHtml(mother.full_name)}</h6>
            <div class="mh-mon-actions">
              <button class="btn btn-sm btn-outline-success" id="monAddConsult"><i class="bi bi-plus-lg me-1"></i>Add Consultation</button>
              <button class="btn btn-sm btn-outline-primary" id="monViewConsults"><i class="bi bi-list-ul me-1"></i>Consult History</button>
            </div>
          </div>

          <div class="mh-mon-summary">
            <div class="mh-mon-card">
              <div class="mh-mon-label">Current GA</div>
              <div class="mh-mon-value">${currentGA!=null? currentGA+'w':'—'}</div>
              <div class="mh-mon-sub">Gestational Age</div>
            </div>
            <div class="mh-mon-card">
              <div class="mh-mon-label">Weeks to EDD</div>
              <div class="mh-mon-value">${weeksToEDD!=null? weeksToEDD:'—'}</div>
              <div class="mh-mon-sub">Remaining</div>
            </div>
            <div class="mh-mon-card">
              <div class="mh-mon-label">Consults</div>
              <div class="mh-mon-value">${records.length}</div>
              <div class="mh-mon-sub">Total</div>
            </div>
            <div class="mh-mon-card ${riskClass==='risk-high'?'mh-mon-risk-high':(riskClass==='risk-monitor'?'mh-mon-risk-monitor':'')}">
              <div class="mh-mon-label">Risk Level</div>
              <div class="mh-mon-value" style="font-size:1.05rem;">${riskLabel}</div>
              <div class="mh-mon-sub">Latest Assessment</div>
            </div>
          </div>

          <div class="mh-mon-progress-wrap">
            <div class="mh-mon-progress-label">
              <span>Pregnancy Progress</span>
              <span>${currentGA!=null? progressPct+'%':''}</span>
            </div>
            <div class="mh-mon-progress">
              <div class="mh-mon-bar ${riskClass.replace('risk-','')}" style="width:${progressPct}%;"></div>
            </div>
          </div>

          <div class="mh-mon-grid">
            <div class="mh-mon-mini">
              <h6>Weight Gain</h6>
              <div class="val">${weightGain!=null? (weightGain>0? '+'+weightGain.toFixed(1): weightGain.toFixed(1))+' kg':'—'}</div>
              <small>From first recorded (${firstWeight!=null?firstWeight+'kg':'?'})</small>
            </div>
            <div class="mh-mon-mini">
              <h6>Avg BP</h6>
              <div class="val">${avgSys!=null && avgDia!=null? `${avgSys}/${avgDia}`:'—'}</div>
              <small>Across records</small>
            </div>
            <div class="mh-mon-mini">
              <h6>Latest Weight</h6>
              <div class="val">${latestWeight!=null? latestWeight+' kg':'—'}</div>
              <small>${escapeHtml(latest.consultation_date||'')}</small>
            </div>
            <div class="mh-mon-mini">
              <h6>Latest BP</h6>
              <div class="val">${(latest.blood_pressure_systolic && latest.blood_pressure_diastolic)? `${latest.blood_pressure_systolic}/${latest.blood_pressure_diastolic}`:'—'}</div>
              <small>${escapeHtml(latest.consultation_date||'')}</small>
            </div>
            <div class="mh-mon-mini">
              <h6>Risk Flags Seen</h6>
              <div class="val">${Object.values(flagsCount).reduce((a,b)=>a+(b>0?1:0),0)}</div>
              <small>Distinct types</small>
            </div>
            <div class="mh-mon-mini">
              <h6>Flag Instances</h6>
              <div class="val">${Object.values(flagsCount).reduce((a,b)=>a+b,0)}</div>
              <small>Total occurrences</small>
            </div>
          </div>

          <div class="mh-trend-legend mb-2">
            <span><strong>Risk Flags:</strong> ${riskChips()}</span>
          </div>

          <div class="mh-mon-trends">
            <div class="mh-trend-box">
              <h6>Weight Trend</h6>
              ${sparkline(weightSeries,'#0d7c4e')}
              <div class="mh-trend-legend">
                <span style="--c:#0d7c4e;"><span style="background:#0d7c4e;"></span>Weight (kg)</span>
              </div>
            </div>
            <div class="mh-trend-box">
              <h6>Blood Pressure (Systolic)</h6>
              ${sparkline(sysSeries,'#cc2b1f')}
              <div class="mh-trend-legend">
                <span style="--c:#cc2b1f;"><span style="background:#cc2b1f;"></span>Systolic (mmHg)</span>
              </div>
            </div>
          </div>

          <div class="mh-mon-table-wrap">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <div style="font-size:.62rem;font-weight:800;letter-spacing:.07em;text-transform:uppercase;color:#2e4a4d;">Progression</div>
              <div style="font-size:.55rem;" class="text-muted">${records.length} record(s)</div>
            </div>
            <div class="table-responsive" style="max-height:340px;">
              <table class="mh-mon-table">
                <thead>
                  <tr>
                    <th>Date</th>
                    <th>GA (wks)</th>
                    <th>Weight (Δ)</th>
                    <th>BP (Δ)</th>
                    <th>Risk</th>
                  </tr>
                </thead>
                <tbody>${tableRows}</tbody>
              </table>
            </div>
          </div>
        `;

        // Actions
        box.querySelector('#monAddConsult')?.addEventListener('click',()=>{
          const tabBtn=document.querySelector('#mhTabs .nav-link[data-tab="consults"]');
          tabBtn?.click();
        });
        box.querySelector('#monViewConsults')?.addEventListener('click',()=>{
          const tabBtn=document.querySelector('#mhTabs .nav-link[data-tab="consults"]');
          tabBtn?.click();
        });
      }
    }
    
/* REPLACE the old loadPostnatalPanel() placeholder with this full implementation */
function loadPostnatalPanel(){
  const panel = document.getElementById('mhPanel');
  const mothers = mothersFull.slice().sort((a,b)=>a.full_name.localeCompare(b.full_name));
  let activeMotherId = mothers.length ? mothers[0].mother_id : null;

  panel.innerHTML = `
    <div class="mh-post-layout">
      <div class="mh-mother-list">
        <h6>Mothers</h6>
        <input type="text" class="form-control mh-mother-search" id="mhPostMotherSearch" placeholder="Search mother / purok">
        <div class="mh-mother-scroll" id="mhPostMotherList">
          ${mothers.map(m=>`
            <div class="mh-mother-item ${m.mother_id===activeMotherId?'active':''}" data-id="${m.mother_id}">
              <span>${escapeHtml(m.full_name)}</span>
              <small>${escapeHtml(m.purok_name||'')}</small>
            </div>
          `).join('')}
        </div>
      </div>
      <div class="mh-post-main" id="mhPostMain">
        <div class="text-muted" style="font-size:.7rem;">${activeMotherId?'Loading postnatal data...':'No mothers.'}</div>
      </div>
    </div>
  `;

  const motherListEl = panel.querySelector('#mhPostMotherList');
  const searchInput  = panel.querySelector('#mhPostMotherSearch');

  function filterMothers(){
    const q=(searchInput.value||'').toLowerCase();
    motherListEl.querySelectorAll('.mh-mother-item').forEach(it=>{
      const txt=it.innerText.toLowerCase();
      it.classList.toggle('d-none', !txt.includes(q));
    });
  }
  searchInput.addEventListener('input',filterMothers);

  motherListEl.addEventListener('click',e=>{
    const item=e.target.closest('.mh-mother-item');
    if(!item)return;
    motherListEl.querySelectorAll('.mh-mother-item').forEach(i=>i.classList.remove('active'));
    item.classList.add('active');
    activeMotherId=parseInt(item.dataset.id,10);
    loadPostnatal();
  });

  if(activeMotherId) loadPostnatal();

  function loadPostnatal(){
    const mother = mothers.find(m=>m.mother_id==activeMotherId);
    const box = panel.querySelector('#mhPostMain');
    box.innerHTML = `<div class="text-muted" style="font-size:.7rem;">Loading postnatal visits...</div>`;
    Promise.all([
      fetchJSON(api.postnatal+`?list=1&mother_id=${activeMotherId}`),
      fetchJSON(api.postnatal+`?children_of=${activeMotherId}`)
    ]).then(([visitsRes,childRes])=>{
      if(!visitsRes.success) throw new Error('Load failed');
      const visits = visitsRes.visits||[];
      const children = childRes.children||[];
      renderPostnatal(mother,visits,children);
    }).catch(err=>{
      box.innerHTML = `<div class="text-danger small">Error loading: ${escapeHtml(err.message)}</div>`;
    });
  }

  function renderPostnatal(mother,visits,children){
    const box = panel.querySelector('#mhPostMain');

    // Sort ascending by visit_date then ID
    visits.sort((a,b)=>{
      if(a.visit_date<b.visit_date) return -1;
      if(a.visit_date>b.visit_date) return 1;
      return a.postnatal_visit_id - b.postnatal_visit_id;
    });

    const latest = visits.length? visits[visits.length-1]: null;

    // Danger score = fever + foul_lochia + mastitis + postpartum_depression + swelling (already computed in API summary logic)
    function dangerScore(v){
      return ['fever','foul_lochia','mastitis','postpartum_depression','swelling']
        .reduce((acc,k)=>acc + (parseInt(v[k])||0),0);
    }

    const dangerCounts = {fever:0,foul_lochia:0,mastitis:0,postpartum_depression:0,swelling:0};
    visits.forEach(v=>{
      Object.keys(dangerCounts).forEach(k=>{
        if(parseInt(v[k])===1) dangerCounts[k]++; 
      });
    });

    const latestScore = latest? dangerScore(latest) : 0;
    let riskClassCard='', riskBadgeClass='';
    let riskLabel='Normal';
    if(latestScore>=2){ riskLabel='High'; riskClassCard='mh-post-risk-high'; riskBadgeClass='mh-post-risk-high-badge'; }
    else if(latestScore===1){ riskLabel='Monitor'; riskClassCard='mh-post-risk-monitor'; riskBadgeClass='mh-post-risk-monitor-badge'; }
    else { riskBadgeClass='mh-post-risk-normal-badge'; }

    const latestPPDay = latest?.postpartum_day!=null ? latest.postpartum_day : '—';
    const totalVisits = visits.length;

    const distinctDangerTypes = Object.values(dangerCounts).reduce((a,b)=>a+(b>0?1:0),0);
    const totalDangerInstances = Object.values(dangerCounts).reduce((a,b)=>a+b,0);

    function dangerLegend(){
      const mapLbl={
        fever:'Fever', foul_lochia:'Foul Lochia', mastitis:'Mastitis',
        postpartum_depression:'PP Depression', swelling:'Swelling'
      };
      return Object.keys(dangerCounts).map(k=>{
        const on = dangerCounts[k]>0;
        return `<span style="display:inline-block;font-size:.5rem;font-weight:700;padding:2px 6px;border-radius:10px;margin:1px;
          background:${on?'#ffe0de':'#e2e8eb'};color:${on?'#b22218':'#58656b'};" title="${mapLbl[k]}: ${dangerCounts[k]}">
          ${mapLbl[k].split(' ').map(w=>w[0]).join('')}
        </span>`;
      }).join('');
    }

    // Build history table rows
    const tableRows = visits.map(v=>{
      const dScore = dangerScore(v);
      let bClass='mh-post-risk-normal-badge', rLbl='Normal';
      if(dScore>=2){bClass='mh-post-risk-high-badge'; rLbl='High';}
      else if(dScore===1){bClass='mh-post-risk-monitor-badge'; rLbl='Monitor';}
      const bp=(v.bp_systolic && v.bp_diastolic)?`${v.bp_systolic}/${v.bp_diastolic}`:'';
      const flags = ['fever','foul_lochia','mastitis','postpartum_depression','swelling']
        .filter(k=>parseInt(v[k])===1)
        .map(k=>k.replace('postpartum_depression','pp_dep'))
        .map(x=>`<span style="display:inline-block;background:#e7efe9;color:#134a3d;font-size:.48rem;font-weight:700;padding:2px 5px;border-radius:8px;margin:1px;">${x.toUpperCase()}</span>`)
        .join('');
      return `<tr>
        <td>${escapeHtml(v.visit_date||'')}</td>
        <td>${v.postpartum_day!=null? v.postpartum_day:'—'}</td>
        <td>${bp||'—'}</td>
        <td>${v.temperature_c!=null? escapeHtml(v.temperature_c):'—'}</td>
        <td><span class="mh-post-badge ${bClass}">${rLbl}</span></td>
        <td>${flags||'<span class="text-muted" style="font-size:.5rem;">None</span>'}</td>
      </tr>`;
    }).join('');

    // Child select options
    const childOptions = `<option value="">(Optional) Select Child</option>` +
      children.map(c=>`<option value="${c.child_id}">${escapeHtml(c.full_name)} (${c.birth_date})</option>`).join('');

    // Form (Add Visit)
    const formHtml = `
      <form class="mh-post-form" id="postnatalForm" autocomplete="off">
        <div class="row g-2">
          <div class="col-6">
            <label>Visit Date *</label>
            <input type="date" name="visit_date" class="form-control" required value="${new Date().toISOString().slice(0,10)}">
          </div>
          <div class="col-6">
            <label>Delivery Date</label>
            <input type="date" name="delivery_date" class="form-control">
          </div>
          <div class="col-12">
            <label>Child</label>
            <select name="child_id" class="form-select">
              ${childOptions}
            </select>
          </div>
          <div class="col-4">
            <label>BP Sys</label>
            <input type="number" name="bp_systolic" class="form-control">
          </div>
            <div class="col-4">
            <label>BP Dia</label>
            <input type="number" name="bp_diastolic" class="form-control">
          </div>
          <div class="col-4">
            <label>Temp (°C)</label>
            <input type="number" step="0.1" name="temperature_c" class="form-control">
          </div>
          <div class="col-6">
            <label>Lochia Status</label>
            <input name="lochia_status" class="form-control" placeholder="e.g. normal/scant">
          </div>
          <div class="col-6">
            <label>Breastfeeding</label>
            <input name="breastfeeding_status" class="form-control" placeholder="exclusive / mixed / none">
          </div>
          <div class="col-12">
            <label style="margin-bottom:.35rem;">Danger Signs</label>
            <div class="mh-post-flags">
              ${[
                ['fever','Fever'],
                ['foul_lochia','Foul Lochia'],
                ['mastitis','Mastitis'],
                ['postpartum_depression','PP Depression'],
                ['swelling','Swelling']
              ].map(([k,l])=>`
                <label class="mh-post-flag-box">
                  <input type="checkbox" name="${k}" value="1">
                  <span>${l}</span>
                </label>`).join('')}
            </div>
          </div>
          <div class="col-12">
            <label>Other Findings</label>
            <input name="other_findings" class="form-control">
          </div>
          <div class="col-12">
            <label>Notes</label>
            <textarea name="notes" rows="2" class="form-control"></textarea>
          </div>
        </div>
        <input type="hidden" name="mother_id" value="${mother.mother_id}">
        <input type="hidden" name="add_visit" value="1">
        <input type="hidden" name="csrf_token" value="${window.__BHW_CSRF}">
        <div class="mt-3 d-flex gap-2">
          <button class="btn btn-success btn-sm"><i class="bi bi-save me-1"></i>Save Visit</button>
          <button type="reset" class="btn btn-outline-secondary btn-sm">Reset</button>
        </div>
        <div class="small text-danger mt-2 d-none" id="postErr"></div>
        <div class="small text-success mt-2 d-none" id="postOk">Saved!</div>
        <div class="mt-2 mh-post-mini-legend">
          <span><strong>Legend:</strong> Fever, Foul Lochia, Mastitis, PP Depression, Swelling</span>
        </div>
      </form>
    `;

    if(!visits.length){
      box.innerHTML=`
        <div class="mh-post-head d-flex justify-content-between align-items-center mb-2">
          <h6>Postnatal Care - ${escapeHtml(mother.full_name)}</h6>
        </div>
        <div class="mh-post-summary">
          <div class="mh-post-card"><div class="mh-post-label">Visits</div><div class="mh-post-value">0</div><div class="mh-post-sub">No records</div></div>
          <div class="mh-post-card"><div class="mh-post-label">Danger Flags</div><div class="mh-post-value">0</div><div class="mh-post-sub">None yet</div></div>
          <div class="mh-post-card"><div class="mh-post-label">Children</div><div class="mh-post-value">${children.length}</div><div class="mh-post-sub">Linked</div></div>
          <div class="mh-post-card"><div class="mh-post-label">Latest Day</div><div class="mh-post-value">—</div><div class="mh-post-sub">Postpartum</div></div>
        </div>
        <div class="mh-post-empty">
          Walang postnatal visit records pa.<br>Mag-save ng unang visit gamit ang form sa ibaba.
        </div>
        <hr>
        ${formHtml}
      `;
      attachPostForm(box,mother);
      return;
    }

    box.innerHTML = `
      <div class="mh-post-head d-flex flex-wrap justify-content-between align-items-center">
        <h6>Postnatal Care - ${escapeHtml(mother.full_name)}</h6>
        <div class="mh-post-actions">
          <button class="btn btn-sm btn-outline-primary" id="btnScrollHistory"><i class="bi bi-list-ul me-1"></i>History</button>
        </div>
      </div>

      <div class="mh-post-summary">
        <div class="mh-post-card">
          <div class="mh-post-label">Latest Day</div>
          <div class="mh-post-value">${latestPPDay}</div>
          <div class="mh-post-sub">Postpartum Day</div>
        </div>
        <div class="mh-post-card">
          <div class="mh-post-label">Visits</div>
          <div class="mh-post-value">${totalVisits}</div>
          <div class="mh-post-sub">Total</div>
        </div>
        <div class="mh-post-card">
          <div class="mh-post-label">Danger Types</div>
          <div class="mh-post-value">${distinctDangerTypes}</div>
          <div class="mh-post-sub">Seen</div>
        </div>
        <div class="mh-post-card">
          <div class="mh-post-label">Flag Inst.</div>
          <div class="mh-post-value">${totalDangerInstances}</div>
          <div class="mh-post-sub">Occurrences</div>
        </div>
        <div class="mh-post-card">
          <div class="mh-post-label">Children</div>
          <div class="mh-post-value">${children.length}</div>
          <div class="mh-post-sub">Linked</div>
        </div>
        <div class="mh-post-card ${riskClassCard}">
          <div class="mh-post-label">Risk</div>
          <div class="mh-post-value" style="font-size:1.05rem;">${riskLabel}</div>
          <div class="mh-post-sub">Latest Visit</div>
        </div>
      </div>

      <div class="mb-2" style="font-size:.55rem;font-weight:600;color:#5a6b71;">
        Danger Flag Summary: ${dangerLegend()}
      </div>

      <div class="row g-4">
        <div class="col-lg-5">
          <h6 style="font-size:.63rem;font-weight:800;letter-spacing:.07em;text-transform:uppercase;margin:0 0 .5rem;color:#2a474d;">Add Postnatal Visit</h6>
          ${formHtml}
        </div>
        <div class="col-lg-7">
          <div class="mh-post-table-wrap" id="postHistoryBox">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <div style="font-size:.6rem;font-weight:800;letter-spacing:.07em;text-transform:uppercase;">Visit History</div>
              <div style="font-size:.55rem;" class="text-muted">${visits.length} record(s)</div>
            </div>
            <div class="table-responsive" style="max-height:420px;">
              <table class="mh-post-table">
                <thead>
                  <tr>
                    <th>Visit Date</th>
                    <th>PP Day</th>
                    <th>BP</th>
                    <th>Temp</th>
                    <th>Risk</th>
                    <th>Flags</th>
                  </tr>
                </thead>
                <tbody>${tableRows}</tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    `;

    attachPostForm(box,mother);

    box.querySelector('#btnScrollHistory')?.addEventListener('click',()=>{
      const h = box.querySelector('#postHistoryBox');
      if(h) h.scrollIntoView({behavior:'smooth',block:'start'});
    });
  }

  function attachPostForm(container,mother){
    const form = container.querySelector('#postnatalForm');
    if(!form) return;
    const deliveryEl = form.querySelector('[name=delivery_date]');
    const visitEl    = form.querySelector('[name=visit_date]');
    // Auto compute postpartum day (display only – optional enhancement)
    function computePPDay(){
      const d = deliveryEl.value;
      const v = visitEl.value;
      if(!d||!v) return;
      const dd=new Date(d+'T00:00:00');
      const vd=new Date(v+'T00:00:00');
      if(isNaN(dd)||isNaN(vd)) return;
      const diff=Math.floor((vd-dd)/(1000*60*60*24));
      // We could show a small hint
      let hint = form.querySelector('#ppDayHint');
      if(!hint){
        hint=document.createElement('div');
        hint.id='ppDayHint';
        hint.style.fontSize='.55rem';
        hint.style.fontWeight='600';
        hint.style.color='#607078';
        hint.style.marginTop='4px';
        visitEl.closest('.col-6')?.appendChild(hint);
      }
      hint.textContent='Computed PP Day: '+ (diff>=0?diff:'—');
    }
    [deliveryEl,visitEl].forEach(el=>el.addEventListener('change',computePPDay));

    form.addEventListener('submit',e=>{
      e.preventDefault();
      const fd=new FormData(form);
      // POST add_visit
      fetch(api.postnatal,{method:'POST',body:fd})
        .then(r=>r.json())
        .then(j=>{
          if(!j.success) throw new Error(j.error||'Save failed');
          form.querySelector('#postErr')?.classList.add('d-none');
          const ok=form.querySelector('#postOk');
            ok?.classList.remove('d-none');
          setTimeout(()=>ok?.classList.add('d-none'),1500);
          // Reload mother panel
          loadPostnatal();
        })
        .catch(err=>{
          const er=form.querySelector('#postErr');
          if(er){
            er.textContent=err.message;
            er.classList.remove('d-none');
          }
        });
    });
  }
}

    // Register Mother
    document.getElementById('btnRegisterMother').addEventListener('click',()=>{
      const modalEl = document.getElementById('modalRegisterMother');
      bootstrap.Modal.getOrCreateInstance(modalEl).show();
    });
    document.getElementById('motherForm').addEventListener('submit',e=>{
      e.preventDefault();
      const form = e.target;
      const fd = new FormData(form);
      fetch(api.mothers,{method:'POST',body:fd})
        .then(r=>r.json())
        .then(j=>{
          if(!j.success) throw new Error(j.error||'Save failed');
          document.getElementById('motherSuccess').classList.remove('d-none');
          document.getElementById('motherError').classList.add('d-none');
          setTimeout(()=>{
            bootstrap.Modal.getInstance(document.getElementById('modalRegisterMother')).hide();
            renderMaternalHealth(label);
          },650);
        }).catch(err=>{
          const el=document.getElementById('motherError');
          el.textContent=err.message;
          el.classList.remove('d-none');
        });
    });

    // View mother details (simple placeholder – expand as needed)
    function openMotherModal(mother_id){
      const modal = document.getElementById('modalViewMother');
      const body  = document.getElementById('viewMotherBody');
      const title = document.getElementById('viewMotherTitle');
      const mother = mothersFull.find(m=>String(m.mother_id)===String(mother_id));
      const latest = riskMap[mother_id];
      if(!mother){
        body.innerHTML='<div class="text-danger small">Mother not found.</div>';
      } else {
        title.textContent = mother.full_name;
        body.innerHTML = `
          <div class="row g-3">
            <div class="col-md-4">
              <div class="border rounded p-3 h-100">
                <h6 class="fw-semibold mb-2" style="font-size:.8rem;">Profile</h6>
                <p class="mb-1"><strong>Purok:</strong> ${escapeHtml(mother.purok_name||'')}</p>
                <p class="mb-1"><strong>Contact:</strong> ${escapeHtml(mother.contact_number||'—')}</p>
                <p class="mb-1"><strong>Gravida / Para:</strong> ${(mother.gravida??'—')} / ${(mother.para??'—')}</p>
                <p class="mb-1"><strong>Blood Type:</strong> ${escapeHtml(mother.blood_type||'—')}</p>
                <p class="mb-0"><strong>Emergency:</strong> ${escapeHtml(mother.emergency_contact_name||'')} <small class="text-muted">${escapeHtml(mother.emergency_contact_number||'')}</small></p>
              </div>
            </div>
            <div class="col-md-8">
              <div class="border rounded p-3">
                <h6 class="fw-semibold mb-2" style="font-size:.8rem;">Latest Consultation Snapshot</h6>
                ${latest ? `
                  <div class="row small g-2">
                    <div class="col-6"><strong>Date:</strong> ${latest.consultation_date||'—'}</div>
                    <div class="col-6"><strong>GA:</strong> ${latest.pregnancy_age_weeks!==null?latest.pregnancy_age_weeks+' wks':'—'}</div>
                    <div class="col-6"><strong>BP:</strong> ${(latest.blood_pressure_systolic||'') && (latest.blood_pressure_diastolic||'')?`${latest.blood_pressure_systolic}/${latest.blood_pressure_diastolic}`:'—'}</div>
                    <div class="col-6"><strong>EDD:</strong> ${latest.expected_delivery_date||'—'}</div>
                    <div class="col-12 mt-2">
                      <strong>Risk Flags:</strong><br>
                      ${buildFlagChips(latest) || '<span class="text-muted">None</span>'}
                    </div>
                  </div>
                `: '<div class="text-muted small">No consultations recorded yet.</div>'}
                <hr>
                <div>
                  <button class="btn btn-sm btn-success" id="btnQuickConsult"><i class="bi bi-journal-plus me-1"></i> Add Consultation</button>
                  <button class="btn btn-sm btn-outline-primary" id="btnOpenFullConsults"><i class="bi bi-list-ul me-1"></i> View All Consultations</button>
                </div>
              </div>
            </div>
          </div>
        `;
        // Quick actions (switch tabs)
        body.querySelector('#btnOpenFullConsults')?.addEventListener('click',()=>{
          bootstrap.Modal.getInstance(modal).hide();
          const tabBtn = document.querySelector('#mhTabs .nav-link[data-tab="consults"]');
          tabBtn?.click();
        });
      }
      bootstrap.Modal.getOrCreateInstance(modal).show();
    }

    function buildFlagChips(r){
      const map = {
        vaginal_bleeding:'VB',
        urinary_infection:'UI',
        high_blood_pressure:'HBP',
        fever_38_celsius:'FEV',
        pallor:'PAL',
        abnormal_abdominal_size:'ABD',
        abnormal_presentation:'PRES',
        absent_fetal_heartbeat:'FHT',
        swelling:'SWL',
        vaginal_infection:'VAG'
      };
      const chips = [];
      Object.keys(map).forEach(k=>{
        if(r[k]==1){
          chips.push(`<span class="mh-flag-chip">${map[k]}</span>`);
        }
      });
      return chips.join('');
    }

  }).catch(err=>{
    moduleContent.innerHTML = `<div class="alert alert-danger small">Error loading: ${escapeHtml(err.message)}</div>`;
  });
}

/* ===== Replace stubs below with your real full module implementations ===== */
function renderRecentActivities(l){showLoading(l);moduleContent.innerHTML='<div class="alert alert-info">Paste original Recent Activities code here.</div>';}
function renderAlertSystem(l){showLoading(l);moduleContent.innerHTML='<div class="alert alert-info">Paste original Alert System code here.</div>';}
function renderUpcomingImmunizations(l){showLoading(l);moduleContent.innerHTML='<div class="alert alert-secondary">Upcoming Immunizations placeholder.</div>';}
function renderVaccinationEntry(l){showLoading(l);moduleContent.innerHTML='<div class="alert alert-info">Vaccination Entry - insert original code.</div>';}
function renderImmunizationCard(l){showLoading(l);moduleContent.innerHTML='<div class="alert alert-info">Immunization Card - insert original code.</div>';}
function renderVaccineSchedule(l){showLoading(l);moduleContent.innerHTML='<div class="alert alert-info">Vaccine Schedule - insert original code.</div>';}
function renderOverdueAlerts(l){showLoading(l);moduleContent.innerHTML='<div class="alert alert-info">Overdue Alerts - insert original code.</div>';}
function renderParentNotifications(l){showLoading(l);moduleContent.innerHTML='<div class="alert alert-info">Parent Notifications - insert original code.</div>';}
function renderCreateParentAccounts(l){showLoading(l);moduleContent.innerHTML='<div class="alert alert-info">Parent Accounts - insert original code.</div>';}
function renderLinkChildParent(l){showLoading(l);moduleContent.innerHTML='<div class="alert alert-info">Link Child - insert original code.</div>';}
function renderAccessCredentials(l){showLoading(l);moduleContent.innerHTML='<div class="alert alert-info">Access Credentials - insert original code.</div>';}
function renderAccountActivity(l){showLoading(l);moduleContent.innerHTML='<div class="alert alert-info">Account Activity - insert original code.</div>';}
function renderHealthCalendar(l){showLoading(l);moduleContent.innerHTML='<div class="alert alert-info">Health Calendar - insert original code.</div>';}
function renderReportVaccinationCoverage(l){showLoading(l);moduleContent.innerHTML='<div class="alert alert-info">Coverage Report - insert original code.</div>';}
function renderReportMaternalStatistics(l){showLoading(l);moduleContent.innerHTML='<div class="alert alert-info">Maternal Stats Report - insert original code.</div>';}
function renderReportHealthRisks(l){showLoading(l);moduleContent.innerHTML='<div class="alert alert-info">Health Risks Report - insert original code.</div>';}
function renderHealthRecordsAll(l){showLoading(l);moduleContent.innerHTML='<div class="alert alert-info">Health Records - insert original code.</div>';}
/* ========================================================================== */

const moduleHandlers={
  health_stats:renderHealthStats,
  recent_activities:renderRecentActivities,
  alert_system:renderAlertSystem,
  upcoming_immunizations:renderUpcomingImmunizations,
  maternal_health:renderMaternalHealth,
  vaccination_entry:renderVaccinationEntry,
  immunization_card:renderImmunizationCard,
  vaccine_schedule:renderVaccineSchedule,
  overdue_alerts:renderOverdueAlerts,
  parent_notifications:renderParentNotifications,
  create_parent_accounts:renderCreateParentAccounts,
  link_child_parent:renderLinkChildParent,
  access_credentials:renderAccessCredentials,
  account_activity:renderAccountActivity,
  health_calendar:renderHealthCalendar,
  report_vaccination_coverage:renderReportVaccinationCoverage,
  report_maternal_statistics:renderReportMaternalStatistics,
  report_health_risks:renderReportHealthRisks,
  health_records_all:renderHealthRecordsAll
};

function loadModule(mod,label){
  titleEl.textContent=label;
  (moduleHandlers[mod]||function(l){showLoading(l);moduleContent.innerHTML='<div class="alert alert-secondary">Module not implemented.</div>';})(label);
  moduleContent.scrollTop=0;
}

/* Sidebar interactions */
document.querySelectorAll('.nav-link-modern[data-module]').forEach(link=>{
  link.addEventListener('click',e=>{
    e.preventDefault();
    setActiveLink(link);
    loadModule(link.dataset.module, link.dataset.label||link.textContent.trim());
    if(window.innerWidth<992) document.getElementById('sidebar').classList.remove('show');
  });
});

const sidebar=document.getElementById('sidebar');
document.getElementById('sidebarToggle')?.addEventListener('click',()=>sidebar.classList.toggle('show'));
document.addEventListener('click',e=>{
  if(window.innerWidth>=992) return;
  if(sidebar.classList.contains('show') && !sidebar.contains(e.target) && !e.target.closest('#sidebarToggle')){
    sidebar.classList.remove('show');
  }
});

/* Capability-based hiding */
fetch(api.caps).then(r=>r.json()).then(j=>{
  if(!j.success)return;
  const feats=j.features||{};
  ['vaccination_entry','immunization_card','vaccine_schedule','overdue_alerts','parent_notifications']
    .forEach(m=>{
      if(!feats[m]){
        const l=document.querySelector(`.nav-link-modern[data-module="${m}"]`);
        if(l) l.closest('li').remove();
      }
    });
}).catch(()=>{});

/* Font Zoom Controls */
const zoomKey='bhw_zoom_px';
function applyZoom(px){
  document.documentElement.style.setProperty('--zoom-step', px+'px');
}
function currentZoom(){return parseFloat(getComputedStyle(document.documentElement).getPropertyValue('--zoom-step'))||0;}
(function initZoom(){
  const stored=localStorage.getItem(zoomKey);
  if(stored) applyZoom(parseFloat(stored));
  document.getElementById('zoomIn').addEventListener('click',()=>{
    let z=currentZoom()+1; if(z>6) z=6; applyZoom(z); localStorage.setItem(zoomKey,z);
  });
  document.getElementById('zoomOut').addEventListener('click',()=>{
    let z=currentZoom()-1; if(z<-2) z=-2; applyZoom(z); localStorage.setItem(zoomKey,z);
  });
  document.getElementById('zoomReset').addEventListener('click',()=>{
    applyZoom(0); localStorage.removeItem(zoomKey);
  });
})();

/* Initial load */
loadModule('health_stats','Dashboard');
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>