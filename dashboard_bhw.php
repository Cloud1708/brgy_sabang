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
  --base-font-size-root:17px;       /* Slightly larger default for better readability */
  --base-font-size-lg:17.4px;       /* Small bump on very wide screens */
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
.table thead th{font-size:.76rem;}
.table{font-size:.85rem;}

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
.mh-metric-label{font-size:.66rem;font-weight:700;letter-spacing:.11em;text-transform:uppercase;color:#4b5c63;margin-bottom:.55rem;display:flex;align-items:center;gap:.4rem;}
.mh-metric-value{font-size:2.1rem;font-weight:800;margin:0;line-height:1;color:#052e26;}
.mh-metric-sub{font-size:.66rem;color:#607078;margin-top:.3rem;font-weight:600;}

.mh-tabs{background:#f5f8fa;border-radius:999px;padding:.4rem .5rem;display:inline-flex;flex-wrap:wrap;gap:.35rem;}
.mh-tabs .nav-link{border-radius:30px;font-size:.74rem;font-weight:600;padding:.55rem 1rem;color:#335155;background:transparent;border:1px solid transparent;transition:background-color .15s ease, color .15s ease, border-color .15s ease;}
.mh-tabs .nav-link.active{background:#ffffff;border:1px solid var(--border);box-shadow:0 2px 4px rgba(0,0,0,.06);color:#0a5c3d;font-weight:700;}
.mh-tabs .nav-link:hover{background:#e9f4ef;color:#0a5c3d;}

.mh-card{background:var(--surface);border:1px solid var(--border);border-radius:20px;box-shadow:var(--shadow-sm);padding:1.15rem 1.4rem;}
.mh-card-title{font-size:.78rem;font-weight:800;color:#203536;text-transform:uppercase;letter-spacing:.07em;margin:0 0 .4rem;}
.mh-card-sub{font-size:.7rem;color:#5d7077;margin-bottom:1rem;}

/* Smooth content swap for MH panels to avoid flicker */
#mhPanel{min-height:320px;will-change:opacity;backface-visibility:hidden;transform:translateZ(0);transition:opacity .18s ease;}
#mhPanel.is-swapping{opacity:.06;}

.mh-table{width:100%;border-collapse:collapse;font-size:.82rem;}
.mh-table thead th{font-size:.68rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;background:#f1f6f7;padding:.65rem .8rem;color:#2f454b;position:sticky;top:0;z-index:5;}
.mh-table tbody td{padding:.6rem .8rem;border-top:1px solid var(--border);vertical-align:middle;font-weight:500;color:#1b3238;}
.mh-table tbody tr:hover td{background:#f3faf6;}

.mh-progress-wrap{min-width:160px;}
.mh-progress{height:8px;background:#e2ece8;border-radius:10px;overflow:hidden;position:relative;margin:.3rem 0 .2rem;}
.mh-progress-bar{height:100%;background:linear-gradient(90deg,#008c59,#00b073);transition:width .5s ease;}
.mh-progress-bar.risk-high{background:linear-gradient(90deg,#cc2b1f,#e85146);}
.mh-progress-bar.risk-monitor{background:linear-gradient(90deg,#c39106,#ffcb3c);}
.mh-weeks-label{font-size:.66rem;font-weight:600;color:#385558;letter-spacing:.04em;}

.risk-badge{display:inline-flex;align-items:center;font-size:.62rem;font-weight:700;padding:.36rem .6rem;border-radius:16px;letter-spacing:.05em;}
.risk-high{background:#fde0dd;color:#b22218;}
.risk-monitor{background:#fff1cd;color:#8b6100;}
.risk-normal{background:#e1edff;color:#134f9c;}
.risk-na{background:#e9ecef;color:#5a646b;}

.mh-action-btn{background:#eef3f5;border:1px solid #d6e1e6;font-size:.66rem;font-weight:600;padding:.45rem .95rem;border-radius:14px;}
.mh-action-btn:hover{background:#e0eff1;}

.mh-empty{padding:2.2rem 1rem;text-align:center;font-size:.75rem;color:#6a7b82;}

.mh-mini-badges{display:flex;gap:.35rem;flex-wrap:wrap;margin-top:.4rem;}
.mh-flag-chip{background:#fbe4e2;color:#c2271b;font-size:.58rem;font-weight:700;padding:.27rem .5rem;border-radius:6px;letter-spacing:.04em;}

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
.mh-consult-main h6{font-size:.85rem;font-weight:800;letter-spacing:.06em;text-transform:uppercase;margin:0 0 .8rem;color:#1c3536;}
.mh-consult-form label{font-size:.68rem;font-weight:700;letter-spacing:.05em;text-transform:uppercase;margin-bottom:.28rem;color:#355156;}
.mh-consult-form .form-control,.mh-consult-form .form-select{font-size:.82rem;padding:.6rem .75rem;border-radius:.7rem;}
.mh-risks-wrap{display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:.4rem;}
.mh-risk-box{font-size:.58rem;background:#f2f6f7;border:1px solid var(--border);border-radius:8px;padding:.4rem .45rem;
  display:flex;align-items:center;gap:.35rem;}
.mh-risk-box input{margin:0;}
.mh-consults-table{width:100%;border-collapse:collapse;font-size:.8rem;margin-top:.9rem;}
.mh-consults-table thead th{position:sticky;top:0;background:#f2f6f7;padding:.65rem .7rem;font-size:.7rem;font-weight:800;letter-spacing:.06em;color:#274048;text-transform:uppercase;}
.mh-consults-table tbody td{padding:.55rem .6rem;border-top:1px solid var(--border);vertical-align:middle;}
.consult-risk-badge{display:inline-block;font-size:.62rem;font-weight:700;padding:.28rem .55rem;border-radius:14px;letter-spacing:.04em;}
.consult-risk-high{background:#fde0dd;color:#b22218;}
.consult-risk-monitor{background:#fff1cd;color:#8b6100;}
.consult-risk-normal{background:#e1edff;color:#134f9c;}
.mh-inline-hint{font-size:.66rem;color:#607078;font-weight:600;margin-top:.3rem;}
.mh-form-divider{height:1px;background:var(--border);margin:.9rem 0;}
.mh-save-btn{font-size:.7rem;font-weight:700;padding:.55rem 1.1rem;border-radius:.75rem;}
.badge-ga{background:#eaf5f1;color:#0d7c4e;font-size:.62rem;font-weight:700;padding:.28rem .6rem;border-radius:14px;margin-left:.35rem;}
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
.mh-mon-mini h6{font-size:.6rem;font-weight:800;letter-spacing:.08em;margin:0;color:#345056;text-transform:uppercase;}
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
.mh-mon-table{width:100%;border-collapse:collapse;font-size:.74rem;}
.mh-mon-table thead th{background:#f1f6f7;font-size:.62rem;font-weight:800;letter-spacing:.07em;padding:.5rem .6rem;color:#2d444c;position:sticky;top:0;}
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
.mh-post-form .form-control,.mh-post-form .form-select{font-size:.76rem;padding:.52rem .65rem;border-radius:.6rem;}
.mh-post-flags{display:grid;grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:.4rem;margin-top:.2rem;}
.mh-post-flag-box{font-size:.55rem;background:#f2f6f7;border:1px solid var(--border);border-radius:8px;padding:.38rem .45rem;display:flex;align-items:center;gap:.35rem;}
.mh-post-flag-box input{margin:0;}
.mh-post-empty{padding:1.8rem 1rem;text-align:center;font-size:.7rem;color:#6c7c83;}
.mh-post-table-wrap{border:1px solid var(--border);background:#fff;border-radius:18px;padding:.85rem .95rem;}
.mh-post-table{width:100%;border-collapse:collapse;font-size:.72rem;}
.mh-post-table thead th{background:#f1f6f7;font-size:.6rem;font-weight:800;letter-spacing:.08em;padding:.5rem .6rem;color:#2f474d;position:sticky;top:0;}
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

/* ==== Immunization Management UI ==== */
.imm-wrap{margin-top:1rem;}
.imm-head{display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:1.2rem;margin-bottom:1.2rem;}
.imm-title{font-size:1.35rem;font-weight:700;color:#11312a;margin:0;}
.imm-sub{font-size:.78rem;font-weight:500;color:#5e6d75;margin:.25rem 0 0;}
.imm-add-btn{display:inline-flex;align-items:center;gap:.45rem;font-weight:600;border-radius:.9rem;background:#047a4c;border:1px solid #047242;color:#fff;padding:.65rem 1.05rem;font-size:.75rem;}
.imm-add-btn:hover{background:#059a61;border-color:#059a61;color:#fff;}

/* Immunization Cards Search & Pagination */
.imm-cards-controls{display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;gap:1rem;flex-wrap:wrap;}
.imm-cards-search{position:relative;max-width:280px;flex:1;}
.imm-cards-search input{font-size:.7rem;border-radius:14px;padding:.5rem .8rem .5rem 2.2rem;border:1px solid var(--border);width:100%;}
.imm-cards-search .search-icon{position:absolute;left:.7rem;top:50%;transform:translateY(-50%);color:#6a7b82;font-size:.8rem;}
.imm-cards-pagination{display:flex;align-items:center;gap:.5rem;font-size:.65rem;}
.imm-cards-pagination button{background:#f5f8fa;border:1px solid var(--border);padding:.35rem .6rem;border-radius:8px;font-size:.65rem;font-weight:600;color:#355155;}
.imm-cards-pagination button:hover:not(:disabled){background:#e9f4ef;color:#0a5c3d;}
.imm-cards-pagination button.active{background:#0a5c3d;color:#fff;border-color:#0a5c3d;}
.imm-cards-pagination button:disabled{opacity:.5;cursor:not-allowed;}
.imm-cards-page-info{font-size:.6rem;color:#6a7b82;font-weight:600;margin:0 .5rem;}
.imm-cards-page-size{font-size:.65rem;padding:.35rem .5rem;border:1px solid var(--border);border-radius:8px;background:#fff;}

/* Activity Feed Pagination */
.pa-activity-controls{display:flex;justify-content:space-between;align-items:center;margin-bottom:.8rem;gap:1rem;flex-wrap:wrap;}
.pa-activity-pagination{display:flex;align-items:center;gap:.4rem;font-size:.6rem;}
.pa-activity-pagination button{background:#f5f8fa;border:1px solid var(--border);padding:.3rem .5rem;border-radius:6px;font-size:.6rem;font-weight:600;color:#355155;}
.pa-activity-pagination button:hover:not(:disabled){background:#e9f4ef;color:#0a5c3d;}
.pa-activity-pagination button:disabled{opacity:.5;cursor:not-allowed;}
.pa-activity-page-info{font-size:.55rem;color:#6a7b82;font-weight:600;margin:0 .4rem;}
.pa-activity-page-size{font-size:.6rem;padding:.3rem .4rem;border:1px solid var(--border);border-radius:6px;background:#fff;}

.imm-metrics{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1rem;margin-bottom:1.4rem;}
.imm-metric{background:var(--surface);border:1px solid var(--border);border-radius:18px;padding:1rem 1.05rem;display:flex;flex-direction:column;gap:.35rem;position:relative;overflow:hidden;box-shadow:var(--shadow-sm);}
.imm-metric:before{content:"";position:absolute;inset:0;border-radius:inherit;background:radial-gradient(circle at 85% 18%,rgba(0,150,100,.12),transparent 60%);}
.imm-metric-label{font-size:.6rem;font-weight:700;letter-spacing:.11em;text-transform:uppercase;color:#476066;display:flex;align-items:center;gap:.45rem;}
.imm-metric-value{font-size:1.85rem;font-weight:800;line-height:1;color:#053129;}
.imm-metric-sub{font-size:.6rem;font-weight:600;color:#617178;}

.imm-tabs{background:#f5f8fa;border-radius:999px;padding:.45rem .55rem;display:inline-flex;flex-wrap:wrap;gap:.35rem;margin-bottom:1.1rem;}
.imm-tabs .nav-link{border-radius:30px;font-size:.68rem;font-weight:600;padding:.5rem .95rem;color:#355155;background:transparent;border:0;}
.imm-tabs .nav-link.active{background:#ffffff;border:1px solid var(--border);box-shadow:0 2px 4px rgba(0,0,0,.06);color:#0a5c3d;font-weight:700;}
.imm-tabs .nav-link:hover{background:#e9f4ef;color:#0a5c3d;}

.imm-card{background:var(--surface);border:1px solid var(--border);border-radius:20px;padding:1.1rem 1.3rem;box-shadow:var(--shadow-sm);font-size:.78rem;}
.imm-card h6{font-size:.7rem;font-weight:800;letter-spacing:.07em;text-transform:uppercase;margin:0 0 .65rem;color:#23423f;}
.imm-table{width:100%;border-collapse:collapse;font-size:.7rem;}
.imm-table thead th{background:#f1f6f7;font-size:.58rem;font-weight:800;letter-spacing:.07em;padding:.55rem .65rem;color:#2a454a;position:sticky;top:0;}
.imm-table tbody td{padding:.52rem .65rem;border-top:1px solid var(--border);vertical-align:middle;}
.imm-table tbody tr:hover td{background:#f3faf6;}
.imm-badge{display:inline-block;font-size:.53rem;font-weight:700;padding:.3rem .55rem;border-radius:16px;letter-spacing:.05em;}
.imm-badge-overdue{background:#fde0dd;color:#b62218;}
.imm-badge-duesoon{background:#fff1cd;color:#8b6700;}
.imm-badge-ok{background:#e1edff;color:#144f9b;}

.imm-small-muted{font-size:.58rem;color:#67767d;font-weight:600;}
.imm-flex-between{display:flex;justify-content:space-between;align-items:center;gap:.75rem;flex-wrap:wrap;}
.imm-scroll{max-height:420px;overflow:auto;}
.imm-placeholder{padding:2rem 1rem;text-align:center;font-size:.66rem;color:#6a7b82;}

@media (max-width:650px){
  .imm-metric-value{font-size:1.55rem;}
}

/* New small styles for simplified Recent Vaccination Records table */
.imm-recent-wrap h6{font-size:.7rem;font-weight:800;letter-spacing:.07em;text-transform:uppercase;margin:0 0 .45rem;color:#23423f;}
.imm-recent-sub{font-size:.58rem;font-weight:600;color:#67767d;margin-bottom:.6rem;}
.imm-pill-completed{background:#e1edff;color:#134f9c;font-size:.55rem;font-weight:700;padding:.25rem .55rem;border-radius:999px;display:inline-block;letter-spacing:.04em;}
.imm-pill-date{background:#ddf5ea;color:#0b6f46;font-size:.55rem;font-weight:700;padding:.25rem .55rem;border-radius:999px;display:inline-block;letter-spacing:.04em;}

/* New Vaccination Record Entry Form Layout */
.vax-form-grid{
  display:grid;
  grid-template-columns:repeat(auto-fit,minmax(260px,1fr));
  gap:1.4rem 2.2rem;
  margin-top:.4rem;
}
.vax-field-group label.vax-label{
  display:block;
  font-size:.62rem;
  font-weight:700;
  letter-spacing:.06em;
  text-transform:uppercase;
  margin:0 0 .45rem;
  color:#2e4d52;
}
.vax-subtext{
  font-size:.55rem;
  font-weight:500;
  color:#6a7a80;
  margin-top:.35rem;
  line-height:1.25;
}
.vax-form-grid .form-select,
.vax-form-grid .form-control{
  font-size:.75rem;
  padding:.55rem .7rem;
  border-radius:.65rem;
}
.vax-readonly{
  background:#f3f6f7;
  font-weight:600;
  font-size:.72rem;
  padding:.55rem .75rem;
  border:1px solid #d7e1e5;
  border-radius:.65rem;
}
#vaxSiteOtherWrap{display:none;}
.vax-modal-title{
  font-size:.92rem;
  font-weight:700;
  margin:0;
  color:#133630;
}
.vax-modal-sub{
  font-size:.64rem;
  font-weight:600;
  color:#6a7a83;
  margin:2px 0 0;
}
.modal-vax-header{
  border-bottom:1px solid #e1e9ed;
  padding:1rem 1.25rem .9rem;
}
.modal-vax-body{
  padding:1.1rem 1.25rem 1.25rem;
}
.modal-vax-footer{
  border-top:1px solid #e1e9ed;
  padding:.9rem 1.25rem;
}
.btn-vax-save{
  background:#007c4d;
  border:1px solid #007548;
  font-weight:600;
  font-size:.75rem;
  padding:.55rem 1.1rem;
  border-radius:.7rem;
  color:#fff;
}
.btn-vax-save:hover{background:#00935d;border-color:#00935d;color:#fff;}
.btn-vax-cancel{
  font-size:.72rem;
  border-radius:.7rem;
  padding:.55rem 1rem;
}
.vax-error, .vax-ok{
  font-size:.6rem;
  font-weight:600;
}

/* --- Overdue Alerts Card List --- */
.imm-overdue-wrap{margin-top:.6rem;}
.imm-overdue-desc{font-size:.65rem;font-weight:600;color:#637278;margin-bottom:.85rem;}
.imm-overdue-controls{display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;gap:1rem;flex-wrap:wrap;}
.imm-overdue-tabs{display:flex;gap:.35rem;background:#f5f8fa;border-radius:20px;padding:.35rem;}
.imm-overdue-tab{font-size:.62rem;font-weight:600;padding:.4rem .75rem;border-radius:14px;cursor:pointer;border:0;background:transparent;color:#355155;}
.imm-overdue-tab.active{background:#ffffff;color:#0a5c3d;font-weight:700;box-shadow:0 1px 3px rgba(0,0,0,.1);}
.imm-overdue-tab:hover{background:#e9f4ef;color:#0a5c3d;}
.imm-overdue-info{font-size:.6rem;color:#6a7b82;font-weight:600;}
.imm-overdue-list{display:flex;flex-direction:column;gap:1rem;}
.imm-overdue-item{
  background:#fef3f2;
  border:1px solid #f8d2cf;
  border-left:6px solid #c72d20;
  border-radius:18px;
  padding:1rem 1.2rem .95rem;
  display:flex;
  flex-direction:column;
  gap:.55rem;
  position:relative;
  box-shadow:0 1px 2px rgba(0,0,0,.02);
  transition:opacity .3s ease, transform .2s ease;
}
.imm-overdue-item.dismissed{opacity:.6;background:#f8f9fa;border-left-color:#6c757d;border-color:#dee2e6;}
.imm-overdue-item.expired{opacity:.5;background:#fef7f0;border-left-color:#d97706;border-color:#e8d5c4;}
.imm-overdue-head{display:flex;align-items:center;gap:.55rem;font-size:.82rem;font-weight:700;color:#25353a;}
.imm-overdue-head i{color:#c72d20;font-size:1rem;}
.imm-overdue-head.dismissed i{color:#6c757d;}
.imm-overdue-head.expired i{color:#d97706;}
.imm-overdue-badge{
  background:#b91c1c;
  color:#fff;
  font-size:.55rem;
  font-weight:700;
  padding:.38rem .65rem;
  border-radius:999px;
  letter-spacing:.05em;
}
.imm-overdue-badge.dismissed{background:#6c757d;}
.imm-overdue-badge.expired{background:#d97706;}
.imm-overdue-meta{font-size:.62rem;font-weight:600;line-height:1.35;color:#425258;}
.imm-overdue-meta strong{color:#122f33;}
.imm-overdue-actions{
  margin-top:.2rem;
  display:flex;
  gap:.45rem;
  flex-wrap:wrap;
}
.btn-imm-notify,.btn-imm-schedule,.btn-imm-dismiss,.btn-imm-restore{
  font-size:.6rem;
  font-weight:600;
  padding:.46rem .85rem;
  border-radius:20px;
  line-height:1;
  cursor:pointer;
}
.btn-imm-notify{
  background:#eef2f5;
  border:1px solid #d5e0e6;
  color:#27424c;
}
.btn-imm-notify:hover{background:#e1ebef;}
.btn-imm-schedule{
  background:#047a4c;
  border:1px solid #047242;
  color:#fff;
}
.btn-imm-schedule:hover{background:#059a61;border-color:#059a61;color:#fff;}
.btn-imm-dismiss{
  background:#f8f9fa;
  border:1px solid #dee2e6;
  color:#6c757d;
}
.btn-imm-dismiss:hover{background:#e9ecef;color:#495057;}
.btn-imm-restore{
  background:#fef3cd;
  border:1px solid #e8d5c4;
  color:#856404;
}
.btn-imm-restore:hover{background:#fff3cd;color:#664308;}
.imm-empty-cards{
  padding:2.2rem 1rem;
  text-align:center;
  font-size:.68rem;
  font-weight:600;
  color:#6b7a82;
  border:1px dashed #cfd8dd;
  border-radius:16px;
  background:#f8fbfd;
}
.imm-overdue-pagination{
  display:flex;
  justify-content:space-between;
  align-items:center;
  margin-top:1rem;
  font-size:.6rem;
}
.imm-overdue-page-info{color:#6a7b82;font-weight:600;}
.imm-overdue-page-controls{display:flex;align-items:center;gap:.35rem;}
.imm-overdue-page-controls button{
  font-size:.55rem;
  padding:.3rem .6rem;
  border:1px solid var(--border);
  background:#fff;
  color:#355155;
  border-radius:8px;
  cursor:pointer;
}
.imm-overdue-page-controls button:hover{background:#f8f9fa;}
.imm-overdue-page-controls button:disabled{opacity:.5;cursor:not-allowed;}
.imm-overdue-page-controls button.active{background:#0d7c4e;color:#fff;border-color:#0d7c4e;}
.imm-overdue-page-size{font-size:.6rem;margin-left:.75rem;}
.imm-overdue-page-size select{font-size:.6rem;padding:.25rem .4rem;border:1px solid var(--border);border-radius:6px;}

/* Quick Child Registration Form */
.imm-child-reg-card{
  background:var(--surface);
  border:1px solid var(--border);
  border-radius:20px;
  padding:1.3rem 1.4rem 1.5rem;
  box-shadow:var(--shadow-sm);
  margin-bottom:1.2rem;
  font-size:.78rem;
}
.imm-child-reg-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:.95rem;}
.imm-child-reg-head h6{font-size:.7rem;font-weight:800;margin:0;letter-spacing:.07em;text-transform:uppercase;color:#23423f;}
.imm-child-form-grid{
  display:grid;
  grid-template-columns:repeat(auto-fit,minmax(240px,1fr));
  gap:1rem 2rem;
  margin-top:.2rem;
}
.imm-child-form-grid label{
  font-size:.58rem;
  font-weight:700;
  letter-spacing:.06em;
  text-transform:uppercase;
  margin:0 0 .35rem;
  color:#345258;
}
.imm-child-form-grid .form-control,
.imm-child-form-grid .form-select{
  font-size:.72rem;
  padding:.55rem .7rem;
  border-radius:.65rem;
}
.imm-child-divider{
  height:1px;
  background:#e3eaed;
  margin:1.15rem 0 1.2rem;
}
.imm-reg-actions{display:flex;justify-content:flex-end;gap:.6rem;margin-top:.4rem;flex-wrap:wrap;}
#immChildRegToggle.active{background:#ffffff;border:1px solid #0b7a4d;color:#0b7a4d;box-shadow:0 2px 6px -2px rgba(0,110,70,.3);}
.imm-inline-hint{font-size:.55rem;color:#6a7a81;font-weight:600;margin-top:.35rem;}
.imm-msg-ok{font-size:.6rem;font-weight:600;color:#0d7c4e;display:none;}
.imm-msg-err{font-size:.6rem;font-weight:600;color:#b22218;display:none;}

/* Immunization Cards UI */
.imm-cards-wrap{background:var(--surface);border:1px solid var(--border);border-radius:20px;padding:1.15rem 1.3rem;box-shadow:var(--shadow-sm);font-size:.78rem;}
.imm-cards-header h6{font-size:.7rem;font-weight:800;letter-spacing:.07em;text-transform:uppercase;margin:0 0 .4rem;color:#23423f;}
.imm-card-list{display:flex;flex-direction:column;gap:1.1rem;margin-top:.4rem;}
.imm-card-item{border:1px solid #e2e9ed;border-radius:18px;padding:1rem 1.15rem;position:relative;background:#ffffff;display:flex;flex-direction:column;gap:.55rem;}
.imm-card-item:hover{background:#f7faf9;}
.imm-card-title{font-size:.83rem;font-weight:700;color:#153633;margin:0;}
.imm-card-sub{font-size:.6rem;font-weight:600;color:#66757c;}
.imm-progress-bar-wrap{height:8px;background:#e3ebe8;border-radius:8px;overflow:hidden;position:relative;margin:.35rem 0 .15rem;}
.imm-progress-fill{height:100%;background:linear-gradient(90deg,#02784a,#00a866);width:0;transition:width .6s ease;}
.imm-progress-meta{font-size:.58rem;font-weight:600;color:#425256;display:flex;justify-content:space-between;}
.imm-card-actions{position:absolute;top:12px;right:12px;display:flex;gap:.4rem;}
.btn-imm-export{background:#f2f6f5;border:1px solid #d6e2de;font-size:.6rem;font-weight:600;padding:.45rem .75rem;border-radius:14px;display:inline-flex;align-items:center;gap:.35rem;}
.btn-imm-export:hover{background:#e4f2ed;}
.imm-cards-empty{padding:2rem 1rem;text-align:center;font-size:.68rem;font-weight:600;color:#6b7a82;border:1px dashed #cfd8dd;background:#f8fbfd;border-radius:16px;}

/* === Parent Accounts UI === */
.pa-wrap{}
.pa-metrics{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:1rem;margin:1.2rem 0 1.6rem;}
.pa-metric-card{background:var(--surface);border:1px solid var(--border);border-radius:18px;padding:1rem 1.1rem;display:flex;flex-direction:column;gap:.4rem;position:relative;overflow:hidden;font-size:.8rem;box-shadow:var(--shadow-sm);}
.pa-metric-card:before{content:"";position:absolute;inset:0;border-radius:inherit;background:radial-gradient(circle at 82% 18%,rgba(0,150,100,.12),transparent 62%);pointer-events:none;}
.pa-metric-label{font-size:.58rem;font-weight:800;letter-spacing:.12em;text-transform:uppercase;color:#4a6168;display:flex;align-items:center;gap:.4rem;}
.pa-metric-value{font-size:1.8rem;font-weight:800;line-height:1;color:#063129;}
.pa-metric-sub{font-size:.55rem;font-weight:600;color:#617079;margin-top:-2px;}
.pa-metric-icon{font-size:1.05rem;color:#0b7c4c;}

.pa-section-card{background:var(--surface);border:1px solid var(--border);border-radius:20px;padding:1.25rem 1.35rem;margin-bottom:1.4rem;box-shadow:var(--shadow-sm);font-size:.78rem;}
.pa-section-card h6{font-size:.68rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:#22413e;margin:0 0 .75rem;}
.pa-table-wrap{max-height:480px;overflow:auto;}
.pa-table{width:100%;border-collapse:collapse;font-size:.72rem;}
.pa-table thead th{background:var(--table-head);position:sticky;top:0;padding:.58rem .7rem;font-size:.58rem;font-weight:800;letter-spacing:.07em;text-transform:uppercase;color:#2b4550;z-index:2;}
.pa-table tbody td{padding:.6rem .7rem;border-top:1px solid var(--border);vertical-align:middle;font-weight:500;}
.pa-status-active{background:#ddf5ea;color:#0b6f46;padding:.3rem .6rem;border-radius:14px;font-size:.55rem;font-weight:700;letter-spacing:.05em;display:inline-block;}
.pa-status-inactive{background:#f4e1df;color:#b7271c;padding:.3rem .6rem;border-radius:14px;font-size:.55rem;font-weight:700;letter-spacing:.05em;display:inline-block;}
.pa-badge-child{background:#e0edff;color:#124d93;padding:.25rem .55rem;border-radius:999px;font-size:.53rem;font-weight:700;letter-spacing:.04em;display:inline-block;}
.pa-actions .btn{font-size:.6rem;font-weight:600;padding:.35rem .75rem;border-radius:14px;}
.pa-search-row{display:flex;flex-wrap:wrap;gap:.6rem;align-items:center;margin-bottom:.9rem;}
.pa-search-row input{font-size:.66rem;border-radius:14px;padding:.45rem .7rem;max-width:240px;}
.pa-filter-badge{font-size:.53rem;font-weight:600;padding:.3rem .55rem;border:1px solid var(--border);border-radius:20px;cursor:pointer;user-select:none;}
.pa-filter-badge.active{background:#0d7c4e;color:#fff;border-color:#0d7c4e;}
.pa-filter-badge:hover{background:#eef6f2;}

.pa-activity-list{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:.7rem;max-height:260px;overflow:auto;}
.pa-activity-item{font-size:.62rem;font-weight:600;line-height:1.3;border-left:4px solid #0d7c4e;background:#f2faf6;padding:.55rem .7rem;border-radius:10px;border:1px solid #d5e7df;}
.pa-activity-time{display:block;font-size:.52rem;font-weight:700;color:#5e6c72;margin-top:.25rem;}

@media (max-width:820px){
  .pa-metric-value{font-size:1.5rem;}
  .pa-table{font-size:.68rem;}
}

/* ==== Activity Summary (old aggregate) ==== */
.pa-activity-summary{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:.65rem;}
.pa-activity-summary-item{
  background:#f3f9f7;border:1px solid #e1ece7;border-radius:12px;
  padding:.60rem .8rem .55rem;font-size:.6rem;font-weight:600;line-height:1.25;
  display:flex;flex-direction:column;gap:2px;position:relative;
}
.pa-activity-summary-item strong{font-size:.66rem;font-weight:800;color:#134438;letter-spacing:.02em;}
.pa-activity-summary-meta{font-size:.55rem;font-weight:600;color:#4f6269;}
.pa-activity-summary-item:hover{background:#eef7f3;}

/* ==== Detailed Audit Feed Cards ==== */
.pa-activity-feed{display:flex;flex-direction:column;gap:.9rem;margin:0;padding:0;list-style:none;}
.pa-activity-item2{
  background:#ffffff;
  border:1px solid #e3eaed;
  border-radius:14px;
  padding:.95rem 1rem .85rem;
  position:relative;
  font-size:.7rem;
  line-height:1.25;
  box-shadow:0 1px 2px rgba(0,0,0,.03);
  display:flex;
  flex-direction:column;
  gap:.4rem;
}
.pa-activity-item2:hover{background:#f9fbfc;}
.pa-activity-top{display:flex;justify-content:space-between;align-items:flex-start;gap:.75rem;}
.pa-activity-name{font-weight:700;font-size:.72rem;color:#123c33;}
.pa-activity-time{font-size:.58rem;font-weight:600;color:#6c7a80;white-space:nowrap;}
.pa-activity-desc{font-size:.64rem;font-weight:600;color:#2d4d53;}
.pa-activity-ip{font-size:.55rem;font-weight:600;color:#7a888e;margin-top:-4px;}
.pa-activity-badge{
  background:#e1f6ef;color:#0b6f46;
  font-size:.48rem;font-weight:800;
  padding:3px 6px;border-radius:10px;
  letter-spacing:.04em;margin-left:.5rem;
}

/* Health Alerts v2 (Dashboard) */
.ha-list{display:flex;flex-direction:column;gap:1rem;}
.ha-item{
  position:relative;display:flex;gap:.8rem;align-items:flex-start;
  border:1px solid var(--border);border-radius:16px;padding:1rem 1.05rem;
  background:#f9fbfc;box-shadow:0 1px 0 0 #e8eef1;
  overflow:hidden;
}
.ha-item:before{
  content:"";position:absolute;left:0;top:0;bottom:0;width:8px;border-top-left-radius:16px;border-bottom-left-radius:16px;
}
.ha-icon{
  height:38px;width:38px;border-radius:12px;display:flex;align-items:center;justify-content:center;
  font-size:18px;font-weight:700;flex:0 0 38px;
}
.ha-title{font-size:.9rem;font-weight:800;color:#132f31;margin:0 0 2px;}
.ha-desc{font-size:.72rem;color:#42545d;line-height:1.3;}
/* variants */
.ha-danger{background:#fff4f3;border-color:#f0c5c1;}
.ha-danger:before{background:#b42318;}
.ha-danger .ha-icon{background:#f7d1cd;color:#b42318;}

.ha-warn{background:#fff8e7;border-color:#eedca1;}
.ha-warn:before{background:#d39b05;}
.ha-warn .ha-icon{background:#ffe4a6;color:#8b6400;}

.ha-info{background:#f1fbf6;border-color:#bdeace;}
.ha-info:before{background:#0d905c;}
.ha-info .ha-icon{background:#d2f1e2;color:#0b7c4d;}

/* === Parent Registry – Screenshot Layout === */
.pr-layout{display:grid;grid-template-columns:330px 1fr;gap:0;border:1px solid var(--border);border-radius:20px;overflow:hidden;background:#fff;}
@media (max-width:1100px){.pr-layout{grid-template-columns:1fr;}}
.pr-list-panel{display:flex;flex-direction:column;background:#fff;border-right:1px solid var(--border);}
@media (max-width:1100px){.pr-list-panel{border-right:0;border-bottom:1px solid var(--border);}}
.pr-list-head{padding:1.1rem 1rem .9rem;display:flex;flex-direction:column;gap:.75rem;border-bottom:1px solid var(--border);background:#fff;}
.pr-panel-title{font-size:.7rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:#3a565c;}
.pr-add-mother-btn{border:0;background:#007a4e;color:#fff;font-size:.7rem;font-weight:700;padding:.65rem .9rem;border-radius:10px;display:flex;align-items:center;justify-content:center;gap:.45rem;box-shadow:0 2px 4px rgba(0,0,0,.06);}
.pr-add-mother-btn:hover{background:#00935e;color:#fff;}
.pr-search-wrap{position:relative;}
.pr-search-wrap input{font-size:.68rem;border-radius:10px;padding:.52rem .8rem .52rem 2.05rem;border:1px solid var(--border);}
.pr-search-wrap i{position:absolute;left:.65rem;top:50%;transform:translateY(-50%);font-size:.85rem;color:#6c7a82;}
.pr-parent-list{list-style:none;margin:0;padding:0;overflow:auto;flex:1;}
.pr-parent-item{padding:.70rem .9rem .55rem .95rem;border-left:3px solid transparent;cursor:pointer;display:flex;flex-direction:column;gap:2px;font-size:.72rem;border-bottom:1px solid var(--border);background:#fff;transition:.15s;}
.pr-parent-item:last-child{border-bottom:none;}
.pr-parent-item:hover{background:#f5f9fc;}
.pr-parent-item.active{background:#e8f2f9;border-left-color:#0d7c4e;}
.pr-parent-item strong{font-size:.74rem;font-weight:700;color:#123c33;}
.pr-parent-item small{font-size:.56rem;font-weight:600;color:#607078;display:flex;align-items:center;gap:.35rem;}
.pr-parent-item small .dot{height:5px;width:5px;border-radius:50%;background:#6a7d82;display:inline-block;}
.pr-detail-panel{background:#f7f9fa;padding:1.6rem 1.8rem 2rem;display:flex;flex-direction:column;min-height:520px;}
.pr-detail-title{font-size:1.05rem;font-weight:700;color:#123c33;margin:0;}
.pr-detail-meta{font-size:.6rem;font-weight:600;color:#5c6d74;margin-top:.35rem;display:flex;align-items:center;gap:.55rem;}
.pr-card{background:#fff;border:1px solid var(--border);border-radius:14px;padding:1.05rem 1.15rem;display:flex;flex-direction:column;gap:.85rem;}
.pr-card + .pr-card{margin-top:1.1rem;}
.pr-card h6{margin:0;font-size:.68rem;font-weight:800;letter-spacing:.06em;color:#1d3d3a;text-transform:uppercase;}
.pr-info-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:.85rem .9rem;font-size:.63rem;}
.pr-info-grid div span{display:block;font-size:.55rem;font-weight:700;color:#697880;letter-spacing:.04em;margin-bottom:2px;text-transform:uppercase;}
.pr-children-head{display:flex;justify-content:space-between;align-items:center;gap:1rem;}
.pr-add-child-btn{background:#007a4e;border:0;color:#fff;font-size:.6rem;font-weight:600;padding:.48rem .85rem;border-radius:8px;display:inline-flex;align-items:center;gap:.35rem;}
.pr-add-child-btn:hover{background:#009562;color:#fff;}
.pr-child-table{width:100%;border-collapse:collapse;font-size:.64rem;}
.pr-child-table thead th{background:#f2f6f8;font-size:.56rem;font-weight:800;letter-spacing:.07em;padding:.55rem .6rem;color:#2a454d;text-transform:uppercase;position:sticky;top:0;}
.pr-child-table tbody td{padding:.52rem .6rem;border-top:1px solid var(--border);font-weight:600;color:#223c43;}
.pr-empty{padding:1.9rem 1rem;text-align:center;font-size:.64rem;color:#6a7b82;background:#fff;border:1px dashed #cdd7dd;border-radius:14px;}
.pr-badge-min{display:inline-block;font-size:.52rem;font-weight:700;padding:.28rem .55rem;border-radius:999px;background:#e1edff;color:#134f9c;letter-spacing:.04em;}
/* Compact modal reuse */
.pr-modal .modal-content{border-radius:18px;}
.pr-modal label{font-size:.58rem;font-weight:700;letter-spacing:.05em;text-transform:uppercase;margin-bottom:.32rem;color:#345058;}
.pr-modal .form-control,.pr-modal .form-select{font-size:.74rem;padding:.55rem .7rem;border-radius:.65rem;}



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
      <li>
  <a href="#" class="nav-link-modern" data-module="parent_registry" data-label="Parent Registry">
    <span class="icon-wrap"><i class="bi bi-person-lines-fill"></i></span>
    <span>Parent Registry</span>
  </a>
</li>
      <li>
  <a href="#" class="nav-link-modern" data-module="vaccination_entry" data-label="Vaccination Entry">
    <span class="icon-wrap"><i class="bi bi-capsule"></i></span>
    <span>Immunization</span>
  </a>
</li>
      <li><a href="#" class="nav-link-modern" data-module="create_parent_accounts" data-label="Parent Accounts"><span class="icon-wrap"><i class="bi bi-people"></i></span><span>Parent Accounts</span></a></li>
      <li><a href="#" class="nav-link-modern" data-module="health_records_all" data-label="Health Records"><span class="icon-wrap"><i class="bi bi-journal-medical"></i></span><span>Health Records</span></a></li>
      <li><a href="#" class="nav-link-modern" data-module="health_calendar" data-label="Event Scheduling"><span class="icon-wrap"><i class="bi bi-calendar3"></i></span><span>Event Scheduling</span></a></li>
      <li><a href="#" class="nav-link-modern" data-module="report_vaccination_coverage" data-label="Health Reports"><span class="icon-wrap"><i class="bi bi-bar-chart"></i></span><span>Health Reports</span></a></li>
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
const CURRENT_USER_NAME = <?php echo json_encode($username); ?>; // ADDED LINE

const moduleContent=document.getElementById('moduleContent');
const titleEl=document.getElementById('currentModuleTitle');

  const api={
  mothers:'bhw_modules/api_mothers.php',
  maternal:'bhw_modules/api_maternal_patients.php', // NEW
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

// Global helper functions for address formatting and other utilities
function formatAddress(m){
  const parts = [m.house_number,m.street_name,m.purok_name,m.subdivision_name].filter(Boolean).join(', ').replace(/\s*,\s*/g,', ');
  const base = parts ? parts : '';
  const suffix = 'Sabang, Lipa City';
  if(!base){
    return suffix;
  }
  const lower = base.toLowerCase();
  if(lower.includes('sabang') && lower.includes('lipa') && lower.includes('city')){
    // Already present – normalize single spacing + keep original base
    return base;
  }
  return base.replace(/,\s*$/,'') + ', ' + suffix;
}

function formatAgeMonths(mo){
  if(mo==null || isNaN(mo)) return '—';
  const mInt=parseInt(mo,10);
  return mInt===1?'1 month': mInt+' months';
}

function formatShortDate(d){
  const dt=new Date(d+'T00:00:00');
  if(isNaN(dt)) return escapeHtml(d);
  return dt.toLocaleDateString('en-PH',{timeZone:'Asia/Manila',month:'short',day:'numeric',year:'numeric'});
}

function capitalize(s){return (s||'').charAt(0).toUpperCase()+ (s||'').slice(1);}

// Immunization Management - Registered Children panel
function loadChildrenPanel(){
  const panel=document.getElementById('immPanel');
  panel.innerHTML = `
    <div class="imm-card">
      <div class="d-flex justify-content-between align-items-center">
        <div>
          <h6 style="margin:0;font-size:.7rem;font-weight:800;letter-spacing:.07em;text-transform:uppercase;">Registered Children</h6>
          <div class="imm-small-muted">All children enrolled in the immunization program</div>
        </div>
        <div class="d-flex align-items-center gap-2">
          <div class="position-relative">
            <i class="bi bi-search" style="position:absolute;left:8px;top:50%;transform:translateY(-50%);color:#6a7b82;font-size:.8rem;"></i>
            <input type="text" id="childSearch" class="form-control form-control-sm" placeholder="Search child / parent / contact" style="padding-left:28px;min-width:240px;font-size:.7rem;">
          </div>
          <select id="childPageSize" class="form-select form-select-sm" style="width:auto;font-size:.65rem;">
            <option value="10">10</option>
            <option value="20" selected>20</option>
            <option value="50">50</option>
          </select>
        </div>
      </div>

      <div class="imm-scroll mt-2" style="max-height:460px;">
        <table class="imm-table" id="childTable">
          <thead>
            <tr>
              <th>Child Name</th>
              <th>Age</th>
              <th>Gender</th>
              <th>Parent/Guardian</th>
              <th>Contact Number</th>
              <th>Address</th>
              <th>Registered Date</th>
            </tr>
          </thead>
          <tbody>
            <tr><td colspan="7" class="text-center text-muted py-4">Loading...</td></tr>
          </tbody>
        </table>
      </div>

      <div class="d-flex justify-content-between align-items-center mt-3">
        <div class="imm-small-muted" id="childInfo">Loading...</div>
        <nav><ul class="pagination pagination-sm mb-0" id="childPager"></ul></nav>
      </div>
    </div>
  `;

  const tbody = panel.querySelector('#childTable tbody');
  const info  = panel.querySelector('#childInfo');
  const pager = panel.querySelector('#childPager');
  const searchEl = panel.querySelector('#childSearch');
  const pageSizeSel = panel.querySelector('#childPageSize');

  let all=[], filtered=[], page=1, pageSize=+pageSizeSel.value;

  fetchJSON(api.immun+'?children=1').then(j=>{
    if(!j.success){ throw new Error('Load failed'); }
    all = (j.children||[]).map(r=>{
      const addr = buildAddress(r);
      const reg  = r.created_at || '';
      return {...r, _address: addr, _created: reg};
    });
    filtered=[...all];
    page=1;
    render();
  }).catch(err=>{
    tbody.innerHTML = `<tr><td colspan="7" class="text-danger text-center py-4">${escapeHtml(err.message)}</td></tr>`;
    pager.innerHTML='';
    info.textContent='Error';
  });

  function buildAddress(r){
    const parts=[];
    if(r.house_number) parts.push(r.house_number);
    if(r.street_name) parts.push(r.street_name);
    if(r.purok_name) parts.push(r.purok_name);
    if(r.subdivision_name) parts.push(r.subdivision_name);
    // Remove Brgy. prefix to avoid duplicate Sabang
    // if(r.barangay) parts.push('Brgy. '+r.barangay);
    const base = parts.filter(Boolean).join(', ');
    return base ? base + ', Sabang, Lipa City' : 'Sabang, Lipa City';
  }
  function fmtDate(d){
    if(!d) return '—';
    const dt = new Date((d.replace(' ','T'))+'Z'); // tolerate timestamp
    // Fallback if invalid
    if(isNaN(dt)) {
      const dt2 = new Date(d+'T00:00:00');
      if(isNaN(dt2)) return escapeHtml(d);
      return dt2.toLocaleDateString('en-PH',{timeZone:'Asia/Manila',month:'short',day:'numeric',year:'numeric'});
    }
    return dt.toLocaleDateString('en-PH',{timeZone:'Asia/Manila',month:'short',day:'numeric',year:'numeric'});
  }
  function fmtAge(m){
    const n=parseInt(m,10); if(isNaN(n)) return '—';
    return n+' month'+(n===1?'':'s');
  }

  function render(){
    if(!filtered.length){
      tbody.innerHTML = `<tr><td colspan="7" class="text-center text-muted py-4">No registered children.</td></tr>`;
      pager.innerHTML='';
      info.textContent='0 results';
      return;
    }
    const total = filtered.length;
    const totalPages = Math.ceil(total / pageSize);
    if(page>totalPages) page=totalPages;

    const start = (page-1)*pageSize;
    const end   = Math.min(start+pageSize, total);
    const slice = filtered.slice(start, end);

    tbody.innerHTML = slice.map(r=>`
      <tr>
        <td>${escapeHtml(r.full_name||'')}</td>
        <td>${fmtAge(r.age_months)}</td>
        <td>${escapeHtml((r.sex||'').charAt(0).toUpperCase()+ (r.sex||'').slice(1))}</td>
        <td>${escapeHtml(r.mother_name||'')}</td>
        <td>${escapeHtml(r.mother_contact||'')}</td>
        <td>${escapeHtml(r._address||'')}</td>
        <td>${fmtDate(r._created)}</td>
      </tr>
    `).join('');

    info.textContent = `Showing ${start+1}-${end} of ${total}`;
    renderPager(totalPages);
  }

  function renderPager(totalPages){
    if(totalPages<=1){ pager.innerHTML=''; return; }
    let html = `
      <li class="page-item ${page<=1?'disabled':''}">
        <a class="page-link" href="#" data-p="${page-1}" style="font-size:.65rem;">Previous</a>
      </li>`;
    const start = Math.max(1, page-2);
    const end   = Math.min(totalPages, page+2);
    if(start>1){
      html += `<li class="page-item"><a class="page-link" href="#" data-p="1" style="font-size:.65rem;">1</a></li>`;
      if(start>2) html += `<li class="page-item disabled"><span class="page-link" style="font-size:.65rem;">...</span></li>`;
    }
    for(let i=start;i<=end;i++){
      html += `<li class="page-item ${i===page?'active':''}">
        <a class="page-link" href="#" data-p="${i}" style="font-size:.65rem;">${i}</a>
      </li>`;
    }
    if(end<totalPages){
      if(end<totalPages-1) html += `<li class="page-item disabled"><span class="page-link" style="font-size:.65rem;">...</span></li>`;
      html += `<li class="page-item"><a class="page-link" href="#" data-p="${totalPages}" style="font-size:.65rem;">${totalPages}</a></li>`;
    }
    html += `
      <li class="page-item ${page>=totalPages?'disabled':''}">
        <a class="page-link" href="#" data-p="${page+1}" style="font-size:.65rem;">Next</a>
      </li>`;
    pager.innerHTML = html;
    pager.querySelectorAll('a.page-link').forEach(a=>{
      a.addEventListener('click',e=>{
        e.preventDefault();
        const p = parseInt(a.dataset.p,10);
        if(p && p!==page){ page=p; render(); }
      });
    });
  }

  // Search + page size
  let timer=null;
  searchEl.addEventListener('input', ()=>{
    clearTimeout(timer);
    timer = setTimeout(()=>{
      const q = (searchEl.value||'').toLowerCase();
      if(!q) filtered=[...all];
      else {
        filtered = all.filter(r=>{
          const bag = [
            r.full_name||'',
            r.mother_name||'',
            r.mother_contact||'',
            r._address||'',
          ].join(' ').toLowerCase();
          return bag.includes(q);
        });
      }
      page=1; render();
    }, 200);
  });
  pageSizeSel.addEventListener('change', ()=>{
    pageSize = parseInt(pageSizeSel.value,10)||20;
    page=1; render();
  });
}

// Immunization Management - Vaccine Schedule panel
function loadSchedulePanel(){
  const panel=document.getElementById('immPanel');
  panel.innerHTML = `
    <div class="imm-card">
      <div class="d-flex justify-content-between align-items-center">
        <div>
          <h6 style="margin:0;font-size:.7rem;font-weight:800;letter-spacing:.07em;text-transform:uppercase;">Vaccine Schedule Management</h6>
          <div class="imm-small-muted">Age-based immunization recommendations</div>
        </div>
      </div>

      <div class="imm-scroll mt-2" style="max-height:460px;">
        <table class="imm-table" id="scheduleTable">
          <thead>
            <tr>
              <th>Age</th>
              <th>Vaccine</th>
              <th>Dose</th>
              <th>Route</th>
              <th>Site</th>
            </tr>
          </thead>
          <tbody>
            <tr><td colspan="5" class="text-center text-muted py-4">Loading...</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  `;

  const tbody = panel.querySelector('#scheduleTable tbody');

  fetchJSON(api.immun+'?schedule=1').then(j=>{
    if(!j.success){ throw new Error('Load failed'); }
    const scheduleData = j.schedule || [];
    if(!scheduleData.length){
      tbody.innerHTML = `<tr><td colspan="5" class="text-center text-muted py-4">No schedule data available.</td></tr>`;
      return;
    }
    tbody.innerHTML = buildScheduleRows(scheduleData);
  }).catch(err=>{
    tbody.innerHTML = `<tr><td colspan="5" class="text-danger text-center py-4">${escapeHtml(err.message)}</td></tr>`;
  });
}

function buildScheduleRows(rows){
  if(!rows.length) return '';
  return rows.map(r=>{
    const ageLabel = mapAge(r.recommended_age_months);
    const doseText = ordinal(r.dose_number)+' Dose';
    return `<tr>
      <td>${escapeHtml(ageLabel)}</td>
      <td>${escapeHtml(r.vaccine_code)}${r.vaccine_name? ', '+escapeHtml(r.vaccine_name):''}</td>
      <td>${doseText}</td>
      <td>${guessRoute(r.vaccine_code)}</td>
      <td>${guessSite(r.vaccine_code)}</td>
    </tr>`;
  }).join('');
}

function mapAge(m){
  const mm=parseInt(m,10);
  if(mm===0) return 'At Birth';
  if(mm===1) return '6 Weeks';
  if(mm===2) return '10 Weeks';
  if(mm===3) return '14 Weeks';
  if(mm===9) return '9 Months';
  if(mm===12) return '12 Months';
  if(mm>=24 && mm<36) return mm+' Months';
  if(mm>=36 && mm<60) return (mm/12).toFixed(0)+' Years';
  return mm+' Months';
}

function guessRoute(code){
  const c=(code||'').toUpperCase();
  if(['BCG'].includes(c)) return 'Intradermal';
  if(['OPV'].includes(c)) return 'Oral';
  if(['HEPB','PENTA','IPV','PCV','MMR','MCV','TD','HPV'].includes(c)) return 'IM';
  return 'IM';
}

function guessSite(code){
  const c=(code||'').toUpperCase();
  if(['BCG'].includes(c)) return 'Right arm';
  if(['OPV'].includes(c)) return 'Oral';
  if(['HEPB','PENTA','IPV','PCV','MMR','MCV','TD','HPV'].includes(c)) return 'Left arm';
  return 'Left arm';
}

function ordinal(n){n=parseInt(n,10)||0;const s=['th','st','nd','rd'],v=n%100;return n+(s[(v-20)%10]||s[v]||s[0]);}

// Parent Registry helper functions
function motherListItem(m, childCount, active){
  return `
    <li class="pr-parent-item ${active?'active':''}" data-id="${m.mother_id}">
      <strong>${escapeHtml(m.full_name||'')}</strong>
      ${m.contact_number? `<small>${escapeHtml(m.contact_number)}</small>`:''}
      <small><span class="dot"></span>${childCount===1?'1 child':childCount+' children'}</small>
    </li>
  `;
}

function motherDetailHTML(m, kids){
  const address = formatAddress(m);
  const childRows = kids.map(c=>`
    <tr>
      <td>${escapeHtml(c.full_name||'')}</td>
      <td>${formatAgeMonths(c.age_months)}</td>
      <td>${c.sex?capitalize(c.sex):'—'}</td>
      <td>${c.birth_date?formatShortDate(c.birth_date):'—'}</td>
    </tr>
  `).join('');
  return `
    <div>
      <h3 class="pr-detail-title">${escapeHtml(m.full_name||'')}</h3>
      <div class="pr-detail-meta">
        <span>Mother</span>
        ${m.contact_number? `<span>• ${escapeHtml(m.contact_number)}</span>`:''}
        ${m.date_of_birth? `<span>• ${formatShortDate(m.date_of_birth)}</span>`:''}
      </div>

      <div class="pr-card">
        <h6>Parent Information</h6>
        <div class="pr-info-grid">
          <div>
            <span>Contact Number</span>${escapeHtml(m.contact_number||'—')}
          </div>
          <div>
            <span>Relationship</span>Mother
          </div>
          <div>
            <span>Birthday</span>${m.date_of_birth?formatShortDate(m.date_of_birth):'—'}
          </div>
          <div style="grid-column:1/-1;">
            <span>Address</span>${escapeHtml(address)}
          </div>
        </div>
      </div>

      <div class="pr-card">
        <div class="pr-children-head">
          <h6>Children</h6>
          <button class="pr-add-child-btn" data-add-child="${m.mother_id}">
            <i class="bi bi-plus-lg"></i> Add Child
          </button>
        </div>
        <div style="font-size:.58rem;font-weight:600;color:#607078;margin-top:-4px;">
          ${kids.length===1?'1 child registered': kids.length+' children registered'}
        </div>
        ${
          kids.length
            ? `<div class="table-responsive" style="max-height:260px;margin-top:.55rem;">
                <table class="pr-child-table">
                  <thead><tr><th>Child Name</th><th>Age</th><th>Gender</th><th>Date of Birth</th></tr></thead>
                  <tbody>${childRows}</tbody>
                </table>
              </div>`
            : `<div class="pr-empty" style="margin-top:.75rem;">No children linked. Click "Add Child".</div>`
        }
      </div>
    </div>
  `;
}
// Safely parse JSON even when server responds with HTML or empty body – gives a readable error
function parseJSONSafe(resp){
  const ct=(resp.headers.get('content-type')||'').toLowerCase();
  return resp.text().then(txt=>{
    if(!resp.ok){
      const msg=txt && txt.trim()? txt.trim().slice(0,400) : ('HTTP '+resp.status+' '+(resp.statusText||''));
      throw new Error(msg);
    }
    const body=(txt||'').trim();
    // Try JSON parse regardless of content-type first
    try{
      if(body==='') throw new Error('');
      return JSON.parse(body);
    }catch(_){
      if(ct.includes('application/json')){
        throw new Error('Invalid JSON from server. '+(body? body.slice(0,300):'(empty response)'));
      }
      // If it looks like JSON but header is wrong, make one more attempt
      if(body.startsWith('{')||body.startsWith('[')){
        try{ return JSON.parse(body); }catch(e2){ /* fallthrough */ }
      }
      throw new Error('Server did not return JSON. '+(body? body.slice(0,300):'(empty response)'));
    }
  });
}

// Global toast helper (stacked, top-right)
function showToast(message, type = 'info', timeout = 3200) {
  let stack = document.getElementById('bhwToastStack');
  if (!stack) {
    stack = document.createElement('div');
    stack.id = 'bhwToastStack';
    stack.style.position = 'fixed';
    stack.style.top = '16px';
    stack.style.right = '16px';
    stack.style.zIndex = '2000';
    stack.style.display = 'flex';
    stack.style.flexDirection = 'column';
    stack.style.gap = '8px';
    document.body.appendChild(stack);
  }

  const alert = document.createElement('div');
  alert.className = `alert alert-${type} alert-dismissible fade show`;
  alert.role = 'alert';
  alert.style.minWidth = '260px';
  alert.style.boxShadow = '0 8px 24px rgba(0,0,0,.08)';
  alert.innerHTML = `
    <div style="font-weight:700; font-size:.78rem;">${escapeHtml(message)}</div>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  `;
  stack.appendChild(alert);

  const timer = setTimeout(() => {
    try {
      alert.classList.remove('show');
      setTimeout(() => alert.remove(), 150);
    } catch (_) {}
  }, timeout);

  alert.querySelector('.btn-close')?.addEventListener('click', () => clearTimeout(timer));
}

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
<div class="panel-card">
  <div class="panel-title"><i class="bi bi-exclamation-octagon"></i> Health Alerts</div>
  <div class="mini-text text-muted mb-2">High-risk cases requiring attention</div>
  <div class="ha-list">${alerts || '<div class="text-muted" style="font-size:.78rem;">No high-risk cases currently.</div>'}</div>
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
  if(highBP) alerts.push(
    alertBox('High Blood Pressure',
      `Patient: ${escapeHtml(highBP.full_name)} (${highBP.pregnancy_age_weeks||'?'} weeks pregnant)<br>Requires immediate follow-up`,
      'danger','bi-exclamation-triangle')
  );
  const abnormal=detail.find(r=>r.abnormal_presentation==1);
  if(abnormal) alerts.push(
    alertBox('Abnormal Presentation',
      `Patient: ${escapeHtml(abnormal.full_name)} (${abnormal.pregnancy_age_weeks||'?'} weeks pregnant)<br>Breech position detected`,
      'danger','bi-exclamation-triangle')
  );
  const lowHgb=(consults||[]).find(c=>{
    if(!c.hgb_result)return false;
    const v=parseFloat(String(c.hgb_result).replace(/[^\d.]/g,''));return !isNaN(v)&&v<10;
  });
  if(lowHgb) alerts.push(
    alertBox('Low Hemoglobin',
      `Patient: ${escapeHtml(lowHgb.full_name)} (${lowHgb.pregnancy_age_weeks||'?'} weeks pregnant)<br>HGB: ${escapeHtml(lowHgb.hgb_result)} - Monitor closely`,
      'warn','bi-exclamation-circle')
  );
  if(overdueList.length>0) alerts.push(
    alertBox('Vaccination Overdue',
      `${overdueList.length} child${overdueList.length>1?'ren':''} with overdue vaccinations<br>Follow up or notify parents`,
      'info','bi-bell')
  );
  return alerts.join('');
}
function alertBox(title,desc,variant,icon){
  // variant: 'danger' | 'warn' | 'info'
  const v = variant==='danger' ? 'ha-danger' : (variant==='warn' ? 'ha-warn' : 'ha-info');
  return `<div class="ha-item ${v}">
    <div class="ha-icon"><i class="bi ${icon}"></i></div>
    <div class="ha-body">
      <div class="ha-title">${escapeHtml(title)}</div>
      <div class="ha-desc">${desc}</div>
    </div>
  </div>`;
}
    function ordinal(n){n=parseInt(n,10)||0;const s=["th","st","nd","rd"],v=n%100;return n+(s[(v-20)%10]||s[v]||s[0]);}
    function formatDate(d){if(!d)return'';const dt=new Date(d+'T00:00:00');if(isNaN(dt))return escapeHtml(d);return dt.toLocaleDateString('en-PH',{timeZone:'Asia/Manila',month:'short',day:'numeric',year:'numeric'});}
  }).catch(err=>{
    moduleContent.innerHTML='<div class="alert alert-danger">Error: '+escapeHtml(err.message)+'</div>';
  });
}

function renderMaternalHealth(label){
  showLoading(label);

  Promise.allSettled([
    fetchJSON(api.maternal+'?list=1'),          // full mother list with counts
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

<!-- Register Mother Modal (UPDATED TO 2-STEP: Mother Details -> Initial Consultation) -->
<div class="modal fade mh-modal" id="modalRegisterMother" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-xl">
    <div class="modal-content">
      <form id="motherForm" autocomplete="off">
        <div class="modal-header">
          <h5 class="modal-title d-flex align-items-center gap-2">
            <i class="bi bi-person-plus"></i>
            <span id="motherModalTitle">Register New Mother</span>
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <!-- STEP 1: Mother Information -->
        <div class="modal-body" id="motherStep1">
          <div class="row g-3">
            <div class="col-md-4">
              <label>First Name *</label>
              <input name="first_name" class="form-control" required>
            </div>
            <div class="col-md-4">
              <label>Middle Name</label>
              <input name="middle_name" class="form-control">
            </div>
            <div class="col-md-4">
              <label>Last Name *</label>
              <input name="last_name" class="form-control" required>
            </div>

            <div class="col-md-4">
              <label>Date of Birth</label>
              <input type="date" name="date_of_birth" class="form-control">
            </div>
            <div class="col-md-4">
              <label>Contact Number</label>
              <input name="contact_number" class="form-control">
            </div>
            <div class="col-md-4">
              <label>Blood Type</label>
              <input name="blood_type" class="form-control" placeholder="O+ / A- ...">
            </div>

            <div class="col-md-4">
              <label>Gravida</label>
              <input type="number" min="0" name="gravida" class="form-control">
            </div>
            <div class="col-md-4">
              <label>Para</label>
              <input type="number" min="0" name="para" class="form-control">
            </div>
            <div class="col-md-4">
              <label>Emergency Contact Name</label>
              <input name="emergency_contact_name" class="form-control">
            </div>
            <div class="col-md-4">
              <label>Emergency Contact No.</label>
              <input name="emergency_contact_number" class="form-control">
            </div>

            <div class="col-md-4">
              <label>House #</label>
              <input name="house_number" class="form-control">
            </div>
            <div class="col-md-4">
              <label>Street Name</label>
              <input name="street_name" class="form-control">
            </div>
            <div class="col-md-4">
              <label>Purok</label>
              <input name="purok_name" class="form-control" id="purokInput" list="purokOptions" autocomplete="off">
              <datalist id="purokOptions">
                <!-- Options will be populated by JavaScript -->
              </datalist>
            </div>
            <div class="col-md-4">
              <label>Subdivision / Village</label>
              <input name="subdivision_name" class="form-control">
            </div>

            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
            <div class="col-12">
              <div class="form-text">Please ensure no duplicate (first + last name) before proceeding.</div>
              <div class="text-danger small d-none" id="motherError"></div>
              <div class="text-success small d-none" id="motherSuccess">Saved!</div>
            </div>
          </div>
        </div>

        <!-- STEP 2: Initial Consultation (dynamically injected) -->
        <div class="modal-body d-none" id="motherStep2">
          <div id="motherConsultWrapper">
            <div class="text-muted small">Loading consultation form...</div>
          </div>
        </div>

<div class="modal-footer justify-content-between">
  <div class="small fw-semibold" id="motherStepIndicator">Step 1 of 2</div>
  <div class="d-flex flex-column align-items-end" style="flex:1;">
    <div id="motherGlobalMsg" class="text-end mb-2" style="min-height:18px;">
      <span class="text-danger small d-none" id="motherErrGlobal"></span>
      <span class="text-success small d-none" id="motherOkGlobal">Saved!</span>
    </div>
    <div id="motherFooterButtons" class="d-flex gap-2"></div>
  </div>
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

  // IMPORTANT: initialize the two-step mother registration wizard
  initMotherWizard();

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
          <div class="d-flex flex-wrap justify-content-between align-items-center mb-2 gap-2" id="mhPatientsPager" style="display:none;">
            <div style="font-size:.6rem;font-weight:600;color:#5d6a70;" id="mhPatientsRange"></div>
            <div class="d-flex align-items-center gap-1">
              <button class="btn btn-outline-secondary btn-sm" id="mhPatientsPrev" style="font-size:.6rem;padding:.25rem .55rem;">
                <i class="bi bi-chevron-left"></i>
              </button>
              <div id="mhPatientsPageInfo" style="font-size:.6rem;font-weight:600;color:#5d6a70;min-width:90px;text-align:center;"></div>
              <button class="btn btn-outline-secondary btn-sm" id="mhPatientsNext" style="font-size:.6rem;padding:.25rem .55rem;">
                <i class="bi bi-chevron-right"></i>
              </button>
              <select id="mhPatientsPageSize" class="form-select form-select-sm" style="font-size:.6rem;padding:.25rem .4rem;width:auto;">
                <option value="10">10</option>
                <option value="20" selected>20</option>
                <option value="50">50</option>
              </select>
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
              <tbody id="mhPatientsTbody"><tr><td colspan="6" class="mh-empty">Loading mothers...</td></tr></tbody>
            </table>
          </div>
        </div>
      `;
      // Server-side pagination + filtering
      const searchInput = document.getElementById('mhSearch');
      const filterBadges = panel.querySelectorAll('.mh-filter-badge');
      const tbody = document.getElementById('mhPatientsTbody');
      const pagerWrap = document.getElementById('mhPatientsPager');
      const prevBtn = document.getElementById('mhPatientsPrev');
      const nextBtn = document.getElementById('mhPatientsNext');
      const pageInfo = document.getElementById('mhPatientsPageInfo');
      const rangeInfo = document.getElementById('mhPatientsRange');
      const pageSizeSel = document.getElementById('mhPatientsPageSize');

      const pagestate = { page:1, pageSize: parseInt(pageSizeSel.value||'20',10), search:'', risk:'' };
      let currentRows = [];

      function activeRisk(){
        const f = panel.querySelector('.mh-filter-badge.active')?.dataset.filter || 'all';
        return f==='all'?'':f; // '', high, monitor, normal
      }

      function loadMothers(){
        tbody.innerHTML = '<tr><td colspan="6" class="mh-empty">Loading mothers...</td></tr>';
        pagestate.risk = activeRisk();
        const params = new URLSearchParams({list:'1', page:pagestate.page, page_size:pagestate.pageSize});
        if(pagestate.search) params.set('search', pagestate.search);
        if(pagestate.risk) params.set('risk', pagestate.risk);
        fetchJSON(api.maternal+'?'+params.toString()).then(j=>{
          if(!j.success) throw new Error(j.error||'Load failed');
          currentRows = j.mothers||[];
          const seen = new Set();
currentRows = currentRows.filter(r=>{
  if(seen.has(r.mother_id)) return false;
  seen.add(r.mother_id);
  return true;
});
          if(!currentRows.length){
            tbody.innerHTML = '<tr><td colspan="6" class="mh-empty">No mothers found.</td></tr>';
          } else {
            tbody.innerHTML = currentRows.map(renderMotherRow).join('');
          }
          wireViewButtons();
          updatePager(j);
        }).catch(err=>{
          tbody.innerHTML = '<tr><td colspan="6" class="text-danger text-center py-4">Error: '+escapeHtml(err.message)+'</td></tr>';
          if(pagerWrap) pagerWrap.style.display='none';
        });
      }

function renderMotherRow(m){
  // Prefer latest_risk_score (from latest consult), fall back to legacy risk_count
  const riskScore = (m.latest_risk_score !== undefined && m.latest_risk_score !== null)
      ? parseInt(m.latest_risk_score,10)
      : parseInt(m.risk_count||0,10);

  let riskClass='risk-normal', riskLabel='Normal';
  if(riskScore >= 2){ riskClass='risk-high'; riskLabel='High Risk'; }
  else if(riskScore === 1){ riskClass='risk-monitor'; riskLabel='Monitor'; }

  const gaWeeks = (m.pregnancy_age_weeks !== undefined && m.pregnancy_age_weeks !== null)
      ? parseInt(m.pregnancy_age_weeks,10)
      : null;

  const ageVal = m.date_of_birth ? calcAge(m.date_of_birth) : '—';
  const eddTxt = m.expected_delivery_date || '';

  // Progress bar only if GA present
  const gaCell = gaWeeks
    ? `<div class="mh-progress">
         <div class="mh-progress-bar" style="width:${Math.min(100,Math.round((gaWeeks/40)*100))}%;"></div>
       </div>
       <div class="mh-weeks-label">${gaWeeks} weeks</div>`
    : '<span class="text-muted" style="font-size:.64rem;">No data</span>';

  return `<tr>
    <td>${escapeHtml(m.full_name||'')}</td>
    <td>${ageVal||'—'}</td>
    <td>${gaCell}</td>
    <td>${eddTxt? escapeHtml(eddTxt): '<span class="text-muted">—</span>'}</td>
    <td><span class="risk-badge ${riskClass}">${riskLabel}</span></td>
    <td><button class="mh-action-btn btn-view" data-id="${m.mother_id}">
          <i class="bi bi-eye me-1"></i>View
        </button>
    </td>
  </tr>`;
}

      function calcAge(dob){
        const d=new Date(dob+'T00:00:00'); if(isNaN(d)) return '';
        const today=new Date();
        let a=today.getFullYear()-d.getFullYear();
        const mm=today.getMonth()-d.getMonth();
        if(mm<0 || (mm===0 && today.getDate()<d.getDate())) a--;
        return a;
      }

      function updatePager(j){
        const total=j.total_count||0; const totalPages=j.total_pages||1;
        const start = total? ((j.current_page-1)*j.page_size)+1 : 0;
        const end = Math.min(j.current_page*j.page_size,total);
        if(rangeInfo) rangeInfo.textContent = `${start}-${end} of ${total}`;
        if(pageInfo) pageInfo.textContent = `Page ${j.current_page} / ${totalPages}`;
        if(prevBtn) prevBtn.disabled = j.current_page<=1;
        if(nextBtn) nextBtn.disabled = j.current_page>=totalPages;
        if(pagerWrap) pagerWrap.style.display = totalPages>1? 'flex':'none';
      }

      function wireViewButtons(){
        tbody.querySelectorAll('.btn-view').forEach(btn=>{
          btn.addEventListener('click',()=> openMotherModal(btn.dataset.id));
        });
      }

      // Events
      let searchTimer=null;
      searchInput.addEventListener('input',()=>{
        clearTimeout(searchTimer);
        searchTimer=setTimeout(()=>{ pagestate.search=(searchInput.value||'').trim(); pagestate.page=1; loadMothers(); },300);
      });
      filterBadges.forEach(b=>{
        b.addEventListener('click',()=>{
          filterBadges.forEach(x=>x.classList.remove('active'));
          b.classList.add('active'); pagestate.page=1; loadMothers();
        });
      });
      prevBtn.addEventListener('click',()=>{ if(pagestate.page>1){ pagestate.page--; loadMothers(); } });
      nextBtn.addEventListener('click',()=>{ pagestate.page++; loadMothers(); });
      pageSizeSel.addEventListener('change',()=>{ pagestate.pageSize=parseInt(pageSizeSel.value||'20',10); pagestate.page=1; loadMothers(); });

      loadMothers();
    }

    // Tabs switching with debounce + soft fade to prevent flicker/glitch
    (function(){
      const tabsEl = document.getElementById('mhTabs');
      const panelEl = document.getElementById('mhPanel');
      let switching = false; // debounce flag
      let switchTimer = null;
      tabsEl.addEventListener('click',e=>{
        const btn = e.target.closest('.nav-link');
        if(!btn) return;
        if(switching) return; // ignore rapid double click
        switching = true;
        clearTimeout(switchTimer);

        document.querySelectorAll('#mhTabs .nav-link').forEach(b=>b.classList.remove('active'));
        btn.classList.add('active');
        const tab = btn.dataset.tab;

        // apply a short fade class to avoid abrupt reflow
        panelEl.classList.add('is-swapping');

        const exec = ()=>{
          if(tab==='patients') loadPatientList();
          else if(tab==='consults') loadConsultsPanel();
          else if(tab==='monitor') loadMonitorPanel();
          else if(tab==='postnatal') loadPostnatalPanel();
          // small delay to let content paint then remove fade
          requestAnimationFrame(()=>{
            setTimeout(()=>{
              panelEl.classList.remove('is-swapping');
              switching = false;
            }, 60);
          });
        };

        // slight delay before render to ensure CSS class takes effect
        switchTimer = setTimeout(exec, 30);
      });
    })();

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
        wrap.innerHTML = `
  <h6>Consultations - ${escapeHtml(mother.full_name)}
    <span class="badge-ga" id="gaBadge">GA: --</span></h6>
  <div class="row g-4">
    <div class="col-lg-5">
      <form class="mh-consult-form" id="consultForm" autocomplete="off">
        <div class="row g-2">
          <div class="col-12">
            <label>PETSA NG KONSULTASYON *</label>
            <input type="date" name="consultation_date" class="form-control" required value="${new Date().toISOString().slice(0,10)}">
          </div>

          <div class="col-4">
            <label>EDAD</label>
            <input type="number" name="age" class="form-control" placeholder="Auto">
          </div>
          <div class="col-4">
            <label>TAAS (CM)</label>
            <input type="number" step="0.1" name="height_cm" class="form-control">
          </div>
          <div class="col-4">
            <label>TIMBANG (KG)</label>
            <input type="number" step="0.01" name="weight_kg" class="form-control">
          </div>

          <div class="col-4">
            <label>BP (SISTOLIC)</label>
            <input type="number" name="blood_pressure_systolic" class="form-control">
          </div>
          <div class="col-4">
            <label>BP (DIASTOLIC)</label>
            <input type="number" name="blood_pressure_diastolic" class="form-control">
          </div>
          <div class="col-4">
            <label>LINGGO NG PAGBUBUNTIS</label>
            <input type="number" min="0" max="45" name="pregnancy_age_weeks" class="form-control" placeholder="Auto" data-autofill="1">
          </div>

          <div class="col-6">
            <label>HULING REGLA (LMP)</label>
            <input type="date" name="last_menstruation_date" class="form-control">
          </div>
          <div class="col-6">
            <label>TINATAYANG PETSA NG PANGANGANAK (EDD)</label>
            <input type="date" name="expected_delivery_date" class="form-control">
          </div>
          <div class="col-12">
            <div class="mh-inline-hint">
              Awtomatiko mula sa LMP/EDD (maaari mong baguhin ang Linggo ng Pagbubuntis at Edad).
            </div>
          </div>
        </div>

        <div class="mh-form-divider"></div>

        <label style="margin-bottom:.4rem;">MGA PAGSUSURI (LABS)</label>
        <div class="row g-2 mb-2">
          <div class="col-6">
            <input name="hgb_result" class="form-control" placeholder="HGB">
          </div>
          <div class="col-6">
            <input name="urine_result" class="form-control" placeholder="Ihi">
          </div>
          <div class="col-6">
            <input name="vdrl_result" class="form-control" placeholder="VDRL">
          </div>
            <div class="col-6">
            <input name="other_lab_results" class="form-control" placeholder="Ibang resulta ng laboratoryo">
          </div>
        </div>

        <div class="mh-form-divider"></div>
        <label style="margin-bottom:.4rem;">MGA PALATANDAAN NG PANGANIB</label>
        <div class="mh-risks-wrap mb-2">
          ${[
            ['vaginal_bleeding','Pagdurugo sa Puwerta'],
            ['urinary_infection','Impeksiyon sa Ihi'],
            ['high_blood_pressure','Mataas na Presyon'],
            ['fever_38_celsius','Lagnat ≥38°C'],
            ['pallor','Pamumutla'],
            ['abnormal_abdominal_size','Hindi Normal na Laki ng Tiyan'],
            ['abnormal_presentation','Abnormal na Posisyon'],
            ['absent_fetal_heartbeat','Walang Tibok ng Puso'],
            ['swelling','Pamamaga'],
            ['vaginal_infection','Impeksiyon sa Puwerta']
          ].map(([k,l])=>`
            <label class="mh-risk-box">
              <input type="checkbox" name="${k}" value="1">
              <span>${l}</span>
            </label>
          `).join('')}
        </div>

        <!-- Step 1 Buttons -->
        <div class="mt-3 d-flex gap-2" id="consultStep1Buttons">
          <button type="button" class="btn btn-primary" id="consultNextBtn"><i class="bi bi-arrow-right me-1"></i>Next</button>
          <button type="reset" class="btn btn-outline-secondary">I-reset</button>
        </div>
        <div class="small text-danger mt-2 d-none" id="consultErr"></div>
      </form>

      <!-- Step 2: Kilos/Lunas na Ginawa -->
      <form class="mh-consult-form d-none" id="consultFormStep2" autocomplete="off">
        <div class="mh-form-divider"></div>
        <label style="margin-bottom:.4rem; font-weight: 700;">KILOS / LUNAS NA GINAWA</label>
        <div class="row g-2 mb-3">
          <div class="col-md-6">
            <label class="mh-risk-box" style="background:#f2f6f7;border:1px solid #d9e2e6; display:flex; align-items:center; gap:.35rem;">
              <input type="checkbox" name="iron_folate_prescription" value="1" style="margin:0;"> Iron/Folate # Reseta
            </label>
          </div>
          <div class="col-md-6">
            <label class="mh-risk-box" style="background:#f2f6f7;border:1px solid #d9e2e6; display:flex; align-items:center; gap:.35rem;">
              <input type="checkbox" name="additional_iodine" value="1" style="margin:0;"> Dagdag na Iodine sa delikadong lugar
            </label>
          </div>
          <div class="col-md-6">
            <label class="mh-risk-box" style="background:#f2f6f7;border:1px solid #d9e2e6; display:flex; align-items:center; gap:.35rem;">
              <input type="checkbox" name="malaria_prophylaxis" value="1" style="margin:0;"> Malaria Prophylaxis (Oo/Hindi)
            </label>
          </div>
          <div class="col-md-6">
            <label class="mh-risk-box" style="background:#f2f6f7;border:1px solid #d9e2e6; display:flex; align-items:center; gap:.35rem;">
              <input type="checkbox" name="breastfeeding_plan" value="1" style="margin:0;"> Balak Magpasuso ng Nanay (Oo/Hindi)
            </label>
          </div>
          <div class="col-md-6">
            <label class="mh-risk-box" style="background:#f2f6f7;border:1px solid #d9e2e6; display:flex; align-items:center; gap:.35rem;">
              <input type="checkbox" name="danger_advice" value="1" style="margin:0;"> Payo sa 4 na Panganib (Oo/Hindi)
            </label>
          </div>
          <div class="col-md-6">
            <label class="mh-risk-box" style="background:#f2f6f7;border:1px solid #d9e2e6; display:flex; align-items:center; gap:.35rem;">
              <input type="checkbox" name="dental_checkup" value="1" style="margin:0;"> Nagpasuri ng Ngipin (Oo/Hindi)
            </label>
          </div>
          <div class="col-md-6">
            <label class="mh-risk-box" style="background:#f2f6f7;border:1px solid #d9e2e6; display:flex; align-items:center; gap:.35rem;">
              <input type="checkbox" name="emergency_plan" value="1" style="margin:0;"> Planong Pangbiglaan at Lugar ng Panganganakan (Oo/Hindi)
            </label>
          </div>
          <div class="col-md-6">
            <label class="mh-risk-box" style="background:#f2f6f7;border:1px solid #d9e2e6; display:flex; align-items:center; gap:.35rem;">
              <input type="checkbox" name="general_risk" value="1" style="margin:0;"> Panganib (Oo/Hindi)
            </label>
          </div>
          <div class="col-md-6">
            <label style="font-size:.7rem; font-weight:600; margin-bottom:.2rem;">Petsa ng Susunod na Pagdalaw</label>
            <input type="date" name="next_visit_date" class="form-control" style="font-size:.7rem;">
          </div>
        </div>

        <input type="hidden" name="mother_id" value="${activeMotherId}">
        <input type="hidden" name="csrf_token" value="${window.__BHW_CSRF}">
        
        <!-- Step 2 Buttons -->
        <div class="mt-3 d-flex gap-2" id="consultStep2Buttons">
          <button type="button" class="btn btn-outline-secondary" id="consultBackBtn"><i class="bi bi-arrow-left me-1"></i>Back</button>
          <button type="submit" class="btn btn-success mh-save-btn"><i class="bi bi-save me-1"></i>I-save</button>
        </div>
        <div class="small text-danger mt-2 d-none" id="consultErrStep2"></div>
        <div class="small text-success mt-2 d-none" id="consultOk">Nai-save!</div>
      </form>
    </div>
    <div class="col-lg-7">
      <div id="consultListBox">
        <div class="text-muted" style="font-size:.7rem;">Naglo-load ng mga rekord...</div>
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

        // Step navigation for consultation form
        let consultStep = 1;
        const consultForm1 = wrap.querySelector('#consultForm');
        const consultForm2 = wrap.querySelector('#consultFormStep2');
        const nextBtn = wrap.querySelector('#consultNextBtn');
        const backBtn = wrap.querySelector('#consultBackBtn');

        function showConsultStep(step) {
          if (step === 1) {
            consultForm1.classList.remove('d-none');
            consultForm2.classList.add('d-none');
            consultStep = 1;
          } else {
            consultForm1.classList.add('d-none');
            consultForm2.classList.remove('d-none');
            consultStep = 2;
          }
        }

        if (nextBtn) {
          nextBtn.addEventListener('click', () => {
            showConsultStep(2);
          });
        }

        if (backBtn) {
          backBtn.addEventListener('click', () => {
            showConsultStep(1);
          });
        }

        // Handle form submission for both steps
        function handleConsultSubmit(e) {
          e.preventDefault();
          
          // Combine data from both forms
          const fd1 = new FormData(consultForm1);
          const fd2 = new FormData(consultForm2);
          const combinedFd = new FormData();
          
          // Add all data from step 1
          for (let [key, value] of fd1.entries()) {
            combinedFd.append(key, value);
          }
          
          // Add all data from step 2
          for (let [key, value] of fd2.entries()) {
            combinedFd.append(key, value);
          }
          
          // Debug: Log the data being sent
          console.log('Sending data to API:', api.health);
          for (let [key, value] of combinedFd.entries()) {
            console.log(key + ': ' + value);
          }
          
          fetch(api.health,{method:'POST',body:combinedFd})
            .then(response => {
              console.log('Response status:', response.status);
              return response.text();
            })
            .then(text => {
              console.log('Raw response:', text);
              return JSON.parse(text);
            })
            .then(j=>{
              if(!j.success) throw new Error(j.error||'Save failed');
              
              // Clear errors
              consultForm1.querySelector('#consultErr').classList.add('d-none');
              consultForm2.querySelector('#consultErrStep2').classList.add('d-none');
              
              // Show success message
              const okEl = consultForm2.querySelector('#consultOk');
              okEl.classList.remove('d-none');
              setTimeout(()=>okEl.classList.add('d-none'),1500);

              // Reset both forms and go back to step 1
              consultForm1.reset();
              consultForm2.reset();
              showConsultStep(1);

              // Refresh consult list
              return fetchJSON(api.health+`?list=1&mother_id=${activeMotherId}`);
            })
            .then(j=>{ renderConsultTable(j.records||[]); })
            .catch(err=>{
              console.error('Submission error:', err);
              const ce = consultForm2.querySelector('#consultErrStep2');
              ce.textContent=err.message;
              ce.classList.remove('d-none');
            });
        }

        consultForm1.addEventListener('submit', handleConsultSubmit);
        consultForm2.addEventListener('submit', handleConsultSubmit);

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
                  <td>${interventionIcons(r)}</td>
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
                      <th>Date</th><th>GA</th><th>BP</th><th>Wt</th><th>HGB</th><th>Risk</th><th>Flags</th><th>Interventions</th>
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
              outs.push(`<span style="display:inline-block;background:#e7efe9;color:#134a3d;font-size:.56rem;font-weight:700;padding:3px 6px;border-radius:8px;margin:1px;">${map[k]}</span>`);
            }
          });
          return outs.join('');
        }

        function interventionIcons(r){
          const map = {
            iron_folate_prescription:'IRON',
            additional_iodine:'IODINE',
            malaria_prophylaxis:'MALARIA',
            breastfeeding_plan:'BF',
            danger_advice:'ADVICE',
            dental_checkup:'DENTAL',
            emergency_plan:'EMERG',
            general_risk:'RISK'
          };
          const outs=[];
          Object.keys(map).forEach(k=>{
            if(r[k]==1){
              outs.push(`<span style="display:inline-block;background:#fff3cd;color:#856404;font-size:.56rem;font-weight:700;padding:3px 6px;border-radius:8px;margin:1px;" title="${getInterventionTitle(k)}">${map[k]}</span>`);
            }
          });
          return outs.join('');
        }

        function getInterventionTitle(key){
          const titles = {
            iron_folate_prescription:'Iron/Folate Prescription',
            additional_iodine:'Additional Iodine',
            malaria_prophylaxis:'Malaria Prophylaxis',
            breastfeeding_plan:'Breastfeeding Plan',
            danger_advice:'Danger Advice',
            dental_checkup:'Dental Checkup',
            emergency_plan:'Emergency Plan',
            general_risk:'General Risk'
          };
          return titles[key] || key;
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
        .then(parseJSONSafe)
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


    // View mother details (simple placeholder – expand as needed)
    function openMotherModal(mother_id){
      const modal = document.getElementById('modalViewMother');
      const body  = document.getElementById('viewMotherBody');
      const title = document.getElementById('viewMotherTitle');

      // Show modal immediately with loading state
      title.textContent = 'Loading...';
      body.innerHTML = `<div class="py-4 text-center text-muted" style="font-size:.7rem;">
          <span class="spinner-border spinner-border-sm me-2"></span>Loading mother details...
        </div>`;
      bootstrap.Modal.getOrCreateInstance(modal).show();

      fetchJSON(api.maternal+`?detail=1&mother_id=${encodeURIComponent(mother_id)}`)
        .then(j=>{
          if(!j.success) throw new Error(j.error||'Load failed');

          const m = j.mother;
          const latest = j.latest_consultation;
          const riskScore = parseInt(j.latest_risk_score ?? 0, 10);

            let riskClass='risk-normal', riskLabel='Normal';
            if(riskScore >= 2){ riskClass='risk-high'; riskLabel='High Risk'; }
            else if(riskScore === 1){ riskClass='risk-monitor'; riskLabel='Monitor'; }

          title.textContent = m.full_name;

          // Build flag chips
          let flagsHTML = '';
          if(latest){
            const map = {
              vaginal_bleeding:'VB',
              urinary_infection:'UTI',
              high_blood_pressure:'HBP',
              fever_38_celsius:'FEV',
              pallor:'PAL',
              abnormal_abdominal_size:'ABD',
              abnormal_presentation:'PRES',
              absent_fetal_heartbeat:'FHT',
              swelling:'SWL',
              vaginal_infection:'VAG'
            };
            Object.keys(map).forEach(k=>{
              if(parseInt(latest[k])===1){
                flagsHTML += `<span class="mh-flag-chip">${map[k]}</span>`;
              }
            });
          }
          if(!flagsHTML) flagsHTML = '<span class="text-muted" style="font-size:.65rem;">None</span>';

          const gaTxt = latest && latest.pregnancy_age_weeks != null
            ? `${latest.pregnancy_age_weeks} wks`
            : '—';

          const eddTxt = latest?.expected_delivery_date || '—';
          const bpTxt = (latest?.blood_pressure_systolic && latest?.blood_pressure_diastolic)
              ? `${latest.blood_pressure_systolic}/${latest.blood_pressure_diastolic}`
              : '—';

          const hgbTxt = latest?.hgb_result ? escapeHtml(latest.hgb_result) : '—';
          const addressTxt = formatAddress(m);

          body.innerHTML = `
            <div class="row g-3">
              <div class="col-md-4">
                <div class="border rounded p-3 h-100">
                  <h6 class="fw-semibold mb-2" style="font-size:.8rem;">Profile</h6>
                    <p class="mb-1"><strong>Address:</strong> ${escapeHtml(addressTxt)}</p>
                  <p class="mb-1"><strong>Contact:</strong> ${escapeHtml(m.contact_number||'—')}</p>
                  <p class="mb-1"><strong>Gravida / Para:</strong> ${(m.gravida??'—')} / ${(m.para??'—')}</p>
                  <p class="mb-1"><strong>Blood Type:</strong> ${escapeHtml(m.blood_type||'—')}</p>
                  <p class="mb-0"><strong>Emergency:</strong> ${escapeHtml(m.emergency_contact_name||'')}
                    <small class="text-muted">${escapeHtml(m.emergency_contact_number||'')}</small></p>
                </div>
              </div>
              <div class="col-md-8">
                <div class="border rounded p-3 h-100 d-flex flex-column">
                  <h6 class="fw-semibold mb-2 d-flex align-items-center gap-2" style="font-size:.8rem;">
                    Latest Consultation Snapshot
                    ${latest ? `<span class="risk-badge ${riskClass}">${riskLabel}</span>` : ''}
                  </h6>
                  ${
                    latest ? `
                      <div class="row small g-2">
                        <div class="col-6"><strong>Date:</strong> ${escapeHtml(latest.consultation_date||'')}</div>
                        <div class="col-6"><strong>GA:</strong> ${gaTxt}</div>
                        <div class="col-6"><strong>BP:</strong> ${bpTxt}</div>
                        <div class="col-6"><strong>EDD:</strong> ${escapeHtml(eddTxt)}</div>
                        <div class="col-6"><strong>HGB:</strong> ${hgbTxt}</div>
                        <div class="col-12 mt-2">
                          <strong>Risk Flags:</strong><br>${flagsHTML}
                        </div>
                      </div>
                    ` : `
                      <div class="text-muted small">No consultations recorded yet.</div>
                    `
                  }
                  <hr class="my-3">
                  <div class="mt-auto">
                    <button class="btn btn-sm btn-success me-2" id="btnQuickConsult">
                      <i class="bi bi-journal-plus me-1"></i> Add Consultation
                    </button>
                    <button class="btn btn-sm btn-outline-primary" id="btnOpenFullConsults">
                      <i class="bi bi-list-ul me-1"></i> View All Consultations
                    </button>
                  </div>
                </div>
              </div>
            </div>
          `;

          body.querySelector('#btnOpenFullConsults')?.addEventListener('click',()=>{
            bootstrap.Modal.getInstance(modal).hide();
            document.querySelector('#mhTabs .nav-link[data-tab="consults"]')?.click();
          });
          body.querySelector('#btnQuickConsult')?.addEventListener('click',()=>{
            bootstrap.Modal.getInstance(modal).hide();
            document.querySelector('#mhTabs .nav-link[data-tab="consults"]')?.click();
            // Optional: could auto-select mother in Consults tab if you add logic
          });

        })
        .catch(err=>{
          title.textContent = 'Error';
          body.innerHTML = `<div class="text-danger small py-4 text-center">Failed to load details: ${escapeHtml(err.message)}</div>`;
        });
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

/* Immunization Module with UPDATED Vaccination Record Form */
function renderVaccinationEntry(label){
  showLoading(label);
  Promise.allSettled([
    fetchJSON(api.reports+'?vaccination_coverage=1'),
    fetchJSON(api.immun+'?overdue=1'),
    fetchJSON(api.immun+'?schedule=1'),
    fetchJSON(api.notif+'?list=1')
  ]).then(results=>{
    const cov = results[0].value||{};
    const over = results[1].value||{};
    const sched = results[2].value||{};
    const notifs = results[3].value||{};
    if(!cov.success || !over.success || !sched.success){
      moduleContent.innerHTML = '<div class="alert alert-danger small">Failed to load immunization data.</div>';
      return;
    }
    const totalChildren = cov.total_children ?? 0;
    const fullyImm = (cov.fully_immunized_children != null) ? cov.fully_immunized_children : '—';
    const dueSoon = (over.dueSoon||[]).length;
    const overdue = (over.overdue||[]).length;
    const scheduleRaw = sched.schedule||[];

    const scheduleRows = buildScheduleRows(scheduleRaw);
    const overdueRows  = buildOverdueRows(over);
    const notifRows    = buildNotifRows(notifs.notifications||[]);

    moduleContent.innerHTML = `
  <div class="imm-wrap fade-in">
    <div class="imm-head">
      <div>
        <h2 class="imm-title">Immunization Management</h2>
        <p class="imm-sub">Track vaccinations, schedules, and coverage</p>
      </div>
      <div class="d-flex gap-2 flex-wrap">
        <button class="btn btn-outline-success btn-sm" id="immChildRegToggle">
          <i class="bi bi-person-plus me-1"></i> Register Child
        </button>
        <button class="imm-add-btn" id="immRecordBtn"><i class="bi bi-plus-lg"></i> Record Vaccination</button>
      </div>
    </div>

    <!-- QUICK CHILD REGISTRATION (hidden default) -->
    <div id="immChildRegWrap" class="imm-child-reg-card" style="display:none;">
      <div class="imm-child-reg-head">
        <h6>Register New Child</h6>
        <button type="button" class="btn btn-sm btn-outline-secondary" id="immChildRegClose">
          <i class="bi bi-x-lg"></i>
        </button>
      </div>
      <div class="text-muted mb-3" style="font-size:.62rem;font-weight:600;">Quick registration for immunization tracking</div>
      <form id="immChildRegForm" autocomplete="off">
  <div class="imm-child-form-grid">
  <div>
    <label>First Name *</label>
    <input name="first_name" class="form-control" required>
  </div>
  <div>
    <label>Middle Name</label>
    <input name="middle_name" class="form-control">
  </div>
  <div>
    <label>Last Name *</label>
    <input name="last_name" class="form-control" required>
  </div>
  <div>
    <label>Date of Birth *</label>
    <input type="date" name="birth_date" class="form-control" required>
  </div>
  <div>
    <label>Gender *</label>
    <select name="sex" class="form-select" required>
      <option value="">Select</option>
      <option value="male">Male</option>
      <option value="female">Female</option>
    </select>
  </div>
  <div>
    <label>Weight (kg)</label>
    <input name="weight_kg" type="number" step="0.01" class="form-control">
  </div>
  <div>
    <label>Height (cm)</label>
    <input name="height_cm" type="number" step="0.1" class="form-control">
  </div>
  <!-- Parent fields remain below -->
  ...
  </div>
        <div class="imm-child-divider"></div>
        <div class="imm-reg-actions">
          <button type="button" class="btn btn-outline-secondary btn-sm" id="immChildRegCancel">Cancel</button>
          <button type="submit" class="btn btn-success btn-sm" id="immChildRegSubmit">
            <span class="reg-btn-label"><i class="bi bi-person-plus me-1"></i> Register Child</span>
            <span class="reg-btn-spin d-none"><span class="spinner-border spinner-border-sm me-1"></span>Saving</span>
          </button>
        </div>
        <div class="imm-inline-hint mt-2">Parent automatically created if not existing.</div>
        <div class="imm-msg-ok" id="immChildRegOk"><i class="bi bi-check-circle me-1"></i>Saved!</div>
        <div class="imm-msg-err" id="immChildRegErr"></div>
        <input type="hidden" name="csrf_token" value="${window.__BHW_CSRF}">
      </form>
    </div>

    <div class="imm-metrics">
      ${metricCard('Total Children', totalChildren,'Registered for immunization','bi-people')}
      ${metricCard('Fully Immunized', fullyImm,'Completed schedule','bi-clipboard-check')}
      ${metricCard('Due This Week', dueSoon,'Scheduled vaccinations','bi-calendar-week')}
      ${metricCard('Overdue', overdue,'Require follow-up','bi-exclamation-octagon')}
    </div>

    <div class="imm-tabs nav" id="immTabs">
      <button class="nav-link active" data-tab="children">Registered Children</button>
      <button class="nav-link" data-tab="schedule">Vaccine Schedule</button>
      <button class="nav-link" data-tab="records">Vaccination Records</button>
      <button class="nav-link" data-tab="overdue">Overdue Alerts</button>
      <button class="nav-link" data-tab="cards">Immunization Cards</button>
      <button class="nav-link" data-tab="parent_notifs">Parent Notifications</button>
    </div>
    <div id="immPanel"></div>
  </div>

      <!-- Vaccination Record Entry Modal -->
      <div class="modal fade" id="immRecordModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-xl">
          <div class="modal-content">
            <div class="modal-vax-header">
              <div>
                <h5 class="vax-modal-title mb-1">Vaccination Record Entry</h5>
                <div class="vax-modal-sub">Record vaccine administration details</div>
              </div>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="immRecordForm" autocomplete="off">
              <div class="modal-vax-body">
                <div class="vax-form-grid">
                  <div class="vax-field-group">
                    <label class="vax-label">Select Child *</label>
                    <select name="child_id" id="vaxChildSel" class="form-select" required>
                      <option value="">Choose child</option>
                    </select>
                  </div>
                  <div class="vax-field-group">
                    <label class="vax-label">Date of Vaccination *</label>
                    <input type="date" name="vaccination_date" class="form-control" value="${new Date().toISOString().slice(0,10)}" required>
                  </div>

                  <div class="vax-field-group">
                    <label class="vax-label">Vaccine Name *</label>
                    <select name="vaccine_id" id="vaxVaccineSel" class="form-select" required>
                      <option value="">Select vaccine</option>
                    </select>
                  </div>
                  <div class="vax-field-group">
                    <label class="vax-label">Dose Number *</label>
                    <select name="dose_number" id="vaxDoseSel" class="form-select" required>
                      <option value="">Select dose</option>
                    </select>
                  </div>

                  <div class="vax-field-group">
                    <label class="vax-label">Batch/Lot Number</label>
                    <input name="batch_lot_number" class="form-control" placeholder="e.g., HB-2025-089">
                  </div>
                  <div class="vax-field-group">
                    <label class="vax-label">Expiry Date</label>
                    <input type="date" name="vaccine_expiry_date" class="form-control" placeholder="mm/dd/yyyy">
                  </div>

                  <div class="vax-field-group">
                    <label class="vax-label">Vaccination Site</label>
                    <select name="vaccination_site" id="vaxSiteSel" class="form-select">
                      <option value="">Select site</option>
                      <option>Left Deltoid</option>
                      <option>Right Deltoid</option>
                      <option>Left Thigh</option>
                      <option>Right Thigh</option>
                      <option>Oral</option>
                      <option value="OTHER">Other...</option>
                    </select>
                    <div id="vaxSiteOtherWrap" class="mt-2">
                      <input type="text" id="vaxSiteOther" class="form-control" placeholder="Specify site">
                    </div>
                  </div>
                  <div class="vax-field-group">
                    <label class="vax-label">Administered By</label>
                    <div class="vax-readonly">${escapeHtml(CURRENT_USER_NAME)}</div>
                  </div>

                  <div class="vax-field-group">
                    <label class="vax-label">Next Dose Date (if applicable)</label>
                    <input type="date" name="next_dose_due_date" class="form-control" placeholder="mm/dd/yyyy">
                    <div class="vax-subtext">Leave blank to auto-compute (if interval is defined)</div>
                  </div>
                  <div class="vax-field-group">
                    <label class="vax-label">Adverse Reactions (if any)</label>
                    <textarea name="adverse_reactions" rows="2" class="form-control" placeholder="Record any adverse reactions or complications"></textarea>
                  </div>

                  <div class="vax-field-group" style="grid-column:1/-1;">
                    <label class="vax-label">Notes</label>
                    <textarea name="notes" rows="2" class="form-control" placeholder="Optional notes"></textarea>
                  </div>
                </div>
                <input type="hidden" name="csrf_token" value="${window.__BHW_CSRF}">
              </div>
              <div class="modal-vax-footer d-flex justify-content-between align-items-center">
                <div>
                  <span class="text-danger d-none vax-error" id="vaxErr"></span>
                  <span class="text-success d-none vax-ok" id="vaxOk">Saved!</span>
                </div>
                <div class="d-flex gap-2">
                  <button type="button" class="btn btn-outline-secondary btn-vax-cancel" data-bs-dismiss="modal">Cancel</button>
                  <button class="btn-vax-save" id="btnVaxSave" type="submit"><i class="bi bi-save me-1"></i>Save Vaccination Record</button>
                </div>
              </div>
            </form>
          </div>
        </div>
      </div>
    `;

    /* === Quick Child Registration Logic === */
  const regToggleBtn = document.getElementById('immChildRegToggle');
  const regWrap      = document.getElementById('immChildRegWrap');
  const regCloseBtn  = document.getElementById('immChildRegClose');
  const regCancelBtn = document.getElementById('immChildRegCancel');
  const regForm      = document.getElementById('immChildRegForm');
  const regSubmitBtn = document.getElementById('immChildRegSubmit');
  const regOkMsg     = document.getElementById('immChildRegOk');
  const regErrMsg    = document.getElementById('immChildRegErr');

  function toggleRegForm(show){
  const willShow = (typeof show==='boolean')? show : (regWrap.style.display==='none');
  regWrap.style.display = willShow ? 'block':'none';
  regToggleBtn.classList.toggle('active', willShow);
  if(willShow){ regForm.reset(); regOkMsg.style.display='none'; regErrMsg.style.display='none'; }
  }
  regToggleBtn.addEventListener('click',()=>toggleRegForm());
  regCloseBtn.addEventListener('click',()=>toggleRegForm(false));
  regCancelBtn.addEventListener('click',()=>toggleRegForm(false));

  function setRegSaving(on){
  const label=regSubmitBtn.querySelector('.reg-btn-label');
  const spin =regSubmitBtn.querySelector('.reg-btn-spin');
  if(on){label.classList.add('d-none');spin.classList.remove('d-none');regSubmitBtn.disabled=true;}
  else {label.classList.remove('d-none');spin.classList.add('d-none');regSubmitBtn.disabled=false;}
  }

  regForm.addEventListener('submit',e=>{
  e.preventDefault();
  regOkMsg.style.display='none';
  regErrMsg.style.display='none';

  const childName   = regForm.child_full_name.value.trim();
  const dob         = regForm.birth_date.value;
  const sex         = regForm.sex.value;
  const parentName  = regForm.parent_name.value.trim();
  const contact     = regForm.contact_number.value.trim();
  const parentDob   = regForm.parent_date_of_birth.value;
  const emgName     = regForm.emergency_contact_name.value.trim();
  const emgNumber   = regForm.emergency_contact_number.value.trim();
  const purok       = regForm.purok_name.value.trim();
  const address     = regForm.address_details.value.trim();

  if(!childName || !dob || !sex || !parentName || !purok){
    regErrMsg.textContent='Please complete all required (*) fields.';
    regErrMsg.style.display='block';
    return;
  }

  setRegSaving(true);

  // Step 1: find existing mother (basic list)
  fetchJSON(api.mothers+'?list_basic=1').then(list=>{
    let existing = null;
    if(list.success){
      const target = parentName.toLowerCase();
      existing = (list.mothers||[]).find(m=> (m.full_name||'').toLowerCase()===target);
    }

    if(existing){
      return Promise.resolve({mother_id: existing.mother_id, created:false});
    }
    // Step 2: create mother
    const fdMother = new FormData();
    fdMother.append('full_name', parentName);
    fdMother.append('purok_name', purok);
    fdMother.append('contact_number', contact);
    fdMother.append('address_details', address);
    if(parentDob) fdMother.append('date_of_birth', parentDob);
    if(emgName) fdMother.append('emergency_contact_name', emgName);
    if(emgNumber) fdMother.append('emergency_contact_number', emgNumber);
    fdMother.append('csrf_token', window.__BHW_CSRF);
    return fetch(api.mothers,{method:'POST',body:fdMother})
      .then(parseJSONSafe)
      .then(j=>{
        if(!j.success) throw new Error(j.error||'Mother create failed');
        return {mother_id:j.mother_id, created:true};
      });
  }).then(({mother_id})=>{
    // Step 3: create child
    const fdChild = new FormData();
    fdChild.append('add_child','1');
  fdChild.append('first_name', regForm.first_name.value.trim());
  fdChild.append('middle_name', regForm.middle_name.value.trim());
  fdChild.append('last_name', regForm.last_name.value.trim());
  fdChild.append('weight_kg', regForm.weight_kg.value.trim());
  fdChild.append('height_cm', regForm.height_cm.value.trim());
    fdChild.append('sex', sex);
    fdChild.append('birth_date', dob);
    fdChild.append('mother_id', mother_id);
    fdChild.append('csrf_token', window.__BHW_CSRF);
    return fetch(api.immun,{method:'POST',body:fdChild}).then(parseJSONSafe);
  }).then(j=>{
    if(!j.success) throw new Error(j.error||'Child save failed');
    regOkMsg.style.display='block';
    // (Optional) refresh module metrics quickly:
    // Re-run only coverage + overdue counts (lite refresh)
    setTimeout(()=>{ toggleRegForm(false); renderVaccinationEntry(label); },900);
  }).catch(err=>{
    regErrMsg.textContent=err.message;
    regErrMsg.style.display='block';
  }).finally(()=>setRegSaving(false));
});
/* === END Quick Child Registration === */

    // Tab switching
    document.getElementById('immTabs').addEventListener('click',e=>{
      const b=e.target.closest('.nav-link'); if(!b) return;
      document.querySelectorAll('#immTabs .nav-link').forEach(x=>x.classList.remove('active'));
      b.classList.add('active');
      const tab=b.dataset.tab;
      if(tab==='children') loadChildrenPanel();
      else if(tab==='schedule') loadSchedulePanel();
      else if(tab==='records') loadRecordsPanel();
      else if(tab==='overdue') loadOverduePanel();
      else if(tab==='cards') loadCardsPanel();
      else if(tab==='parent_notifs') loadParentNotifPanel();
    });

    /* Open modal */
    document.getElementById('immRecordBtn').addEventListener('click',()=>{
      bootstrap.Modal.getOrCreateInstance(document.getElementById('immRecordModal')).show();
      preloadVaccinationForm();
    });

    /* Form dynamic data caches */
    let vaccineMeta = []; // store list from API for doses_required mapping

    function preloadVaccinationForm(){
      // Children
      fetchJSON(api.immun+'?children=1').then(j=>{
        const sel=document.getElementById('vaxChildSel');
        if(!j.success){ sel.innerHTML='<option value="">Error loading children</option>'; return;}
        sel.innerHTML='<option value="">Choose child</option>'+ (j.children||[]).map(c=>`<option value="${c.child_id}">${escapeHtml(c.full_name)} (${c.age_months}m)</option>`).join('');
      }).catch(()=>{});
      // Vaccines
      fetchJSON(api.immun+'?vaccines=1').then(j=>{
        const sel=document.getElementById('vaxVaccineSel');
        if(!j.success){ sel.innerHTML='<option value="">Error loading vaccines</option>'; return;}
        vaccineMeta = j.vaccines||[];
        sel.innerHTML='<option value="">Select vaccine</option>' + vaccineMeta.map(v=>`<option value="${v.vaccine_id}" data-doses="${v.doses_required||1}" data-interval="${v.interval_between_doses_days||''}">${escapeHtml(v.vaccine_code)} - ${escapeHtml(v.vaccine_name)}</option>`).join('');
      }).catch(()=>{});
    }

    // React to vaccine selection to build dose list
    document.addEventListener('change',e=>{
      if(e.target.id==='vaxVaccineSel'){
        const doseSel=document.getElementById('vaxDoseSel');
        doseSel.innerHTML='<option value="">Select dose</option>';
        const opt=e.target.selectedOptions[0];
        if(!opt) return;
        const doses=parseInt(opt.dataset.doses||'1',10);
        for(let i=1;i<=doses;i++){
          doseSel.insertAdjacentHTML('beforeend',`<option value="${i}">${i}</option>`);
        }
      }
      if(e.target.id==='vaxSiteSel'){
        const otherWrap=document.getElementById('vaxSiteOtherWrap');
        if(e.target.value==='OTHER'){ otherWrap.style.display='block'; }
        else { otherWrap.style.display='none'; document.getElementById('vaxSiteOther').value=''; }
      }
    });

    // Submit vaccination form
    document.getElementById('immRecordForm').addEventListener('submit',e=>{
      e.preventDefault();
      const form=e.target;
      const fd=new FormData(form);

      // If site OTHER chosen, replace vaccination_site with custom value
      const siteSel=form.querySelector('#vaxSiteSel');
      if(siteSel && siteSel.value==='OTHER'){
        const custom=form.querySelector('#vaxSiteOther').value.trim();
        fd.set('vaccination_site', custom);
      }

      // If next dose date left blank, backend will auto-compute; else passes override
      // Expiry date optional (backend will insert only if column exists)

      fetch(api.immun,{method:'POST',body:fd})
        .then(parseJSONSafe)
        .then(j=>{
          if(!j.success) throw new Error(j.error||'Save failed');
          form.querySelector('#vaxErr').classList.add('d-none');
            const ok=form.querySelector('#vaxOk');
            ok.classList.remove('d-none');
            setTimeout(()=>ok.classList.add('d-none'),1400);
          // refresh records tab if active
          const activeTab=document.querySelector('#immTabs .nav-link.active[data-tab="records"]');
          if(activeTab) loadRecordsPanel();
          // Optionally clear some fields
          form.reset();
        }).catch(err=>{
          const er=form.querySelector('#vaxErr');
          er.textContent=err.message;
          er.classList.remove('d-none');
          form.querySelector('#vaxOk').classList.add('d-none');
        });
    });

    /* Panels */
    loadChildrenPanel();

function loadOverduePanel(){
  const panel=document.getElementById('immPanel');
  
  // Pagination state
  let currentPage = 1;
  let pageSize = 10;
  let showType = 'active'; // 'active', 'recycle', 'all'
  let overdueData = null;

  loadOverdueData();

  function loadOverdueData() {
    const loadingHtml = `
      <div class="imm-card">
        <h6>Overdue Vaccination Alerts</h6>
        <div class="imm-small-muted mb-3">Loading...</div>
        <div class="loading-state">
          <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
          </div>
        </div>
      </div>`;
    panel.innerHTML = loadingHtml;

    const params = new URLSearchParams({
      overdue: '1',
      page: currentPage,
      page_size: pageSize,
      show: showType
    });

    fetchJSON(api.immun + '?' + params.toString())
      .then(data => {
        overdueData = data;
        renderOverduePanel();
      })
      .catch(err => {
        panel.innerHTML = `
          <div class="imm-card">
            <h6>Overdue Vaccination Alerts</h6>
            <div class="alert alert-danger">
              <i class="bi bi-exclamation-triangle me-2"></i>
              Error loading overdue alerts: ${escapeHtml(err.message)}
            </div>
          </div>`;
      });
  }

  function renderOverduePanel() {
    if (!overdueData) return;

    const { overdue = [], dueSoon = [], pagination = {} } = overdueData;
    const { page = 1, pageSize: currentPageSize = 10, total = 0, totalPages = 0 } = pagination;

    function fmtDate(d){
      if(!d) return '—';
      const dt=new Date(d+'T00:00:00');
      if(isNaN(dt)) return d;
      return dt.toLocaleDateString('en-PH',{timeZone:'Asia/Manila',month:'short',day:'numeric',year:'numeric'});
    }
    function ordinal(n){n=parseInt(n,10)||0;const s=['th','st','nd','rd'],v=n%100;return n+(s[(v-20)%10]||s[v]||s[0]);}

    const cardsHtml = overdue.map(item=>{
      const days = item.days_overdue!=null ? item.days_overdue : null;
      const status = item.notification_status || 'active';
      
      let badge, itemClass = '', headClass = '', badgeClass = '';
      if (status === 'dismissed') {
        badge = `<span class="imm-overdue-badge dismissed">Dismissed</span>`;
        itemClass = 'dismissed';
        headClass = 'dismissed';
        badgeClass = 'dismissed';
      } else if (status === 'expired') {
        badge = `<span class="imm-overdue-badge expired">Expired</span>`;
        itemClass = 'expired';
        headClass = 'expired';
        badgeClass = 'expired';
      } else {
        badge = days !== null
          ? `<span class="imm-overdue-badge">${days} day${days===1?'':'s'} overdue</span>`
          : `<span class="imm-overdue-badge" style="background:#d97706;">Overdue</span>`;
      }

      const dueTxt = fmtDate(item.due_date);
      const parentLine = item.mother_name
        ? `<strong>Parent:</strong> ${escapeHtml(item.mother_name)}${item.parent_contact? ' - '+escapeHtml(item.parent_contact):''}<br>`
        : '';

      // Action buttons based on status
      let actionButtons = '';
      if (status === 'dismissed' || status === 'expired') {
        actionButtons = `
          <button class="btn-imm-restore" type="button" data-action="restore">
            <i class="bi bi-arrow-clockwise me-1"></i>Restore
          </button>`;
      } else {
        actionButtons = `
          <button class="btn-imm-notify" type="button" data-action="notify">
            <i class="bi bi-bell me-1"></i>Notify Parent
          </button>
          <button class="btn-imm-schedule" type="button" data-action="schedule">
            <i class="bi bi-plus-lg me-1"></i>Schedule / Record
          </button>
          <button class="btn-imm-dismiss" type="button" data-action="dismiss">
            <i class="bi bi-x-lg me-1"></i>Dismiss
          </button>`;
      }

      return `
        <div class="imm-overdue-item ${itemClass}" data-child="${item.child_id}" data-vaccine="${item.vaccine_id}" data-dose="${item.dose_number}">
          <div class="imm-overdue-head ${headClass}">
            <i class="bi bi-exclamation-octagon"></i>
            <span>${escapeHtml(item.child_name)}</span>
            ${badge}
          </div>
          <div class="imm-overdue-meta">
            <strong>Vaccine:</strong> ${escapeHtml(item.vaccine_code)} - ${ordinal(item.dose_number)} Dose<br>
            <strong>Due Date:</strong> ${dueTxt}<br>
            ${parentLine}
            ${status !== 'active' ? `<strong>Status:</strong> ${status.charAt(0).toUpperCase() + status.slice(1)}<br>` : ''}
          </div>
          <div class="imm-overdue-actions">
            ${actionButtons}
          </div>
        </div>`;
    }).join('');

    // Tab counts
    const activeCounts = total; // This will be updated by separate API calls if needed
    const recycleCounts = 0; // Placeholder

    // Pagination info
    const startItem = total > 0 ? ((page - 1) * currentPageSize) + 1 : 0;
    const endItem = Math.min(page * currentPageSize, total);
    const pageInfo = total > 0 
      ? `Showing ${startItem}-${endItem} of ${total} alerts`
      : 'No alerts found';

    // Page controls
    const pageControls = [];
    for (let i = 1; i <= totalPages; i++) {
      if (i === 1 || i === totalPages || (i >= page - 1 && i <= page + 1)) {
        pageControls.push(`<button class="${i === page ? 'active' : ''}" data-page="${i}">${i}</button>`);
      } else if (i === page - 2 || i === page + 2) {
        pageControls.push('<span>...</span>');
      }
    }

    panel.innerHTML=`
      <div class="imm-card">
        <h6>Overdue Vaccination Alerts</h6>
        <div class="imm-small-muted mb-3">Manage overdue and dismissed vaccination notifications</div>
        
        <div class="imm-overdue-controls">
          <div class="imm-overdue-tabs">
            <button class="imm-overdue-tab ${showType === 'active' ? 'active' : ''}" data-show="active">
              Active Alerts
            </button>
            <button class="imm-overdue-tab ${showType === 'recycle' ? 'active' : ''}" data-show="recycle">
              <i class="bi bi-trash me-1"></i>Recycle Bin
            </button>
            <button class="imm-overdue-tab ${showType === 'all' ? 'active' : ''}" data-show="all">
              All
            </button>
          </div>
          <div class="imm-overdue-info">${pageInfo}</div>
        </div>
        
        <div class="imm-overdue-wrap">
          ${cardsHtml || `<div class="imm-empty-cards">
            ${showType === 'active' ? 'No active overdue vaccinations 🎉' : 
              showType === 'recycle' ? 'Recycle bin is empty' : 'No alerts found'}
          </div>`}
        </div>
        
        ${totalPages > 1 ? `
          <div class="imm-overdue-pagination">
            <div class="imm-overdue-page-info">${pageInfo}</div>
            <div class="d-flex align-items-center gap-2">
              <div class="imm-overdue-page-controls">
                <button ${page <= 1 ? 'disabled' : ''} data-page="${page - 1}">Previous</button>
                ${pageControls.join('')}
                <button ${page >= totalPages ? 'disabled' : ''} data-page="${page + 1}">Next</button>
              </div>
              <div class="imm-overdue-page-size">
                <select data-page-size>
                  <option value="5" ${currentPageSize === 5 ? 'selected' : ''}>5 per page</option>
                  <option value="10" ${currentPageSize === 10 ? 'selected' : ''}>10 per page</option>
                  <option value="20" ${currentPageSize === 20 ? 'selected' : ''}>20 per page</option>
                </select>
              </div>
            </div>
          </div>
        ` : ''}
        
        ${dueSoon.length ? `<hr class="mt-4">
          <div style="font-size:.62rem;font-weight:700;letter-spacing:.05em;margin-bottom:.6rem;color:#264846;">Due Soon (Preview)</div>
          <ul style="list-style:none;padding-left:0;margin:0;display:grid;gap:.4rem;font-size:.6rem;font-weight:600;color:#497;">
            ${dueSoon.slice(0,6).map(s=>`<li style="background:#f1f6f7;border:1px solid #dae4e7;padding:.45rem .65rem;border-radius:10px;">
               <strong>${escapeHtml(s.child_name)}</strong> · ${escapeHtml(s.vaccine_code)} ${ordinal(s.dose_number)} – target ${s.target_age_months}m
            </li>`).join('')}
          </ul>`: '' }
      </div>
    `;

    attachOverdueEventHandlers();
  }

  function attachOverdueEventHandlers() {
    // Tab switching
    panel.querySelectorAll('.imm-overdue-tab').forEach(tab => {
      tab.addEventListener('click', () => {
        showType = tab.dataset.show;
        currentPage = 1;
        loadOverdueData();
      });
    });

    // Pagination
    panel.querySelectorAll('[data-page]').forEach(btn => {
      btn.addEventListener('click', () => {
        const newPage = parseInt(btn.dataset.page);
        if (newPage && newPage !== currentPage) {
          currentPage = newPage;
          loadOverdueData();
        }
      });
    });

    // Page size
    const pageSizeSelect = panel.querySelector('[data-page-size]');
    if (pageSizeSelect) {
      pageSizeSelect.addEventListener('change', () => {
        pageSize = parseInt(pageSizeSelect.value);
        currentPage = 1;
        loadOverdueData();
      });
    }

    // Action buttons
    panel.querySelectorAll('.imm-overdue-item').forEach(card=>{
      card.querySelectorAll('button[data-action]').forEach(btn=>{
        btn.addEventListener('click',()=>{
          const action=btn.getAttribute('data-action');
          const childId=card.getAttribute('data-child');
          const vaccineId=card.getAttribute('data-vaccine');
          const dose=card.getAttribute('data-dose');
          
          if(action==='schedule'){
            openVaccinationModalPrefill(childId, vaccineId, dose);
          } else if(action==='notify'){
            notifyParentSingle(card, childId, vaccineId, dose);
          } else if(action==='dismiss'){
            dismissNotification(childId, vaccineId, dose);
          } else if(action==='restore'){
            restoreNotification(childId, vaccineId, dose);
          }
        });
      });
    });
  }

  function dismissNotification(childId, vaccineId, dose) {
    if (!confirm('Are you sure you want to dismiss this overdue alert? It will be moved to the recycle bin.')) return;

    const fd = new FormData();
    fd.append('dismiss_notification', '1');
    fd.append('child_id', childId);
    fd.append('vaccine_id', vaccineId);
    fd.append('dose_number', dose);
    fd.append('csrf_token', window.__BHW_CSRF); // changed: use the global CSRF token

    fetch(api.immun, {method: 'POST', body: fd})
      .then(parseJSONSafe)
      .then(j => {
        if (!j.success) throw new Error(j.error || 'Failed to dismiss notification');
        showAlert('Notification dismissed and moved to recycle bin', 'success');
        loadOverdueData();
      })
      .catch(err => {
        showAlert('Error dismissing notification: ' + err.message, 'danger');
      });
  }

  function restoreNotification(childId, vaccineId, dose) {
    const fd = new FormData();
    fd.append('restore_notification', '1');
    fd.append('child_id', childId);
    fd.append('vaccine_id', vaccineId);
    fd.append('dose_number', dose);
    fd.append('csrf_token', window.__BHW_CSRF); // changed: use the global CSRF token

    fetch(api.immun, {method: 'POST', body: fd})
      .then(parseJSONSafe)
      .then(j => {
        if (!j.success) throw new Error(j.error || 'Failed to restore notification');
        showAlert('Notification restored to active alerts', 'success');
        loadOverdueData();
      })
      .catch(err => {
        showAlert('Error restoring notification: ' + err.message, 'danger');
      });
  }

  function showAlert(message, type = 'info') {
    // Create a temporary alert
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
    alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; max-width: 350px;';
    alertDiv.innerHTML = `
      ${message}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    document.body.appendChild(alertDiv);
    
    // Auto remove after 4 seconds
    setTimeout(() => {
      if (alertDiv.parentNode) {
        alertDiv.remove();
      }
    }, 4000);
  }

  function openVaccinationModalPrefill(childId,vaccineId,dose){
    // Open modal
    const modalEl=document.getElementById('immRecordModal');
    bootstrap.Modal.getOrCreateInstance(modalEl).show();
    // Ensure form data loaded
    preloadVaccinationForm();
    // Wait a tick for dropdowns to populate
    setTimeout(()=>{
      const childSel=document.getElementById('vaxChildSel');
      const vacSel=document.getElementById('vaxVaccineSel');
      const doseSel=document.getElementById('vaxDoseSel');
      if(childSel){ childSel.value=childId; childSel.dispatchEvent(new Event('change',{bubbles:true})); }
      if(vacSel){
        vacSel.value=vaccineId;
        vacSel.dispatchEvent(new Event('change',{bubbles:true}));
        setTimeout(()=>{ if(doseSel) doseSel.value=dose; },50);
      }
    },180);
  }

  function notifyParentSingle(card,childId,vaccineId,dose){
    // Placeholder toast (replace with real API call if you add a single-notify endpoint)
    btnSpinner(card,true);
    fetch(api.notif,{
      method:'POST',
      body:new URLSearchParams({
        csrf_token: window.__BHW_CSRF,
        // If you implemented a single notify action backend:
        // single_vaccine_notify:1,
        // child_id: childId,
        // vaccine_id: vaccineId,
        // dose_number: dose
        generate_notifications:1  // fallback: regenerate all
      })
    }).then(r=>r.json()).then(()=>{
      showTempBadge(card,'Notification queued');
    }).catch(()=>{
      showTempBadge(card,'Failed',true);
    }).finally(()=>btnSpinner(card,false));
  }

  function btnSpinner(card,on){
    const b=card.querySelector('[data-action="notify"]');
    if(!b) return;
    if(on){
      b.dataset.oldText=b.innerHTML;
      b.innerHTML='<span class="spinner-border spinner-border-sm me-1"></span>Sending';
      b.disabled=true;
    } else {
      if(b.dataset.oldText){ b.innerHTML=b.dataset.oldText; }
      b.disabled=false;
    }
  }
  function showTempBadge(card,text,isErr){
    const n=document.createElement('div');
    n.textContent=text;
    n.style.position='absolute';
    n.style.top='8px'; n.style.right='12px';
    n.style.fontSize='.55rem';
    n.style.fontWeight='700';
    n.style.padding='.35rem .55rem';
    n.style.borderRadius='12px';
    n.style.background=isErr?'#b91c1c':'#0d7c4e';
    n.style.color='#fff';
    card.appendChild(n);
    setTimeout(()=>n.remove(),1800);
  }
}

function loadCardsPanel(){
  const panel=document.getElementById('immPanel');
  panel.innerHTML = `
    <div class="imm-cards-wrap">
      <div class="imm-cards-header">
        <h6>Digital Immunization Cards</h6>
        <div class="imm-small-muted mb-2">Generate and export vaccination records</div>
      </div>
      
      <div class="imm-cards-controls">
        <div class="imm-cards-search">
          <i class="bi bi-search search-icon"></i>
          <input type="text" id="cardsSearchInput" placeholder="Search by child name..." autocomplete="off">
        </div>
        <div class="imm-cards-pagination" id="cardsPagination" style="display:none;">
          <button id="cardsPrevBtn" title="Previous page">
            <i class="bi bi-chevron-left"></i>
          </button>
          <div class="imm-cards-page-info" id="cardsPageInfo"></div>
          <button id="cardsNextBtn" title="Next page">
            <i class="bi bi-chevron-right"></i>
          </button>
          <select id="cardsPageSize" class="imm-cards-page-size" title="Items per page">
            <option value="5">5</option>
            <option value="10" selected>10</option>
            <option value="20">20</option>
          </select>
        </div>
      </div>
      
      <div id="immCardsBody" class="py-2">
        <div class="text-center py-4 text-muted" style="font-size:.65rem;">
          <span class="spinner-border spinner-border-sm me-2"></span>Loading immunization cards...
        </div>
      </div>
    </div>
  `;

  // Pagination state
  let currentPage = 1;
  let currentPageSize = 10;
  let currentSearch = '';
  let totalPages = 0;

  // Event listeners
  const searchInput = panel.querySelector('#cardsSearchInput');
  const pagination = panel.querySelector('#cardsPagination');
  const prevBtn = panel.querySelector('#cardsPrevBtn');
  const nextBtn = panel.querySelector('#cardsNextBtn');
  const pageSizeSelect = panel.querySelector('#cardsPageSize');
  const pageInfo = panel.querySelector('#cardsPageInfo');

  let searchTimeout;
  searchInput.addEventListener('input', (e) => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
      currentSearch = e.target.value.trim();
      currentPage = 1;
      loadCards();
    }, 300);
  });

  prevBtn.addEventListener('click', () => {
    if (currentPage > 1) {
      currentPage--;
      loadCards();
    }
  });

  nextBtn.addEventListener('click', () => {
    if (currentPage < totalPages) {
      currentPage++;
      loadCards();
    }
  });

  pageSizeSelect.addEventListener('change', (e) => {
    currentPageSize = parseInt(e.target.value);
    currentPage = 1;
    loadCards();
  });

  function loadCards() {
    const body = panel.querySelector('#immCardsBody');
    body.innerHTML = `
      <div class="text-center py-4 text-muted" style="font-size:.65rem;">
        <span class="spinner-border spinner-border-sm me-2"></span>Loading immunization cards...
      </div>
    `;

    const params = new URLSearchParams({
      cards_summary: '1',
      page: currentPage,
      page_size: currentPageSize
    });

    if (currentSearch) {
      params.set('search', currentSearch);
    }

    fetchJSON(api.immun + '?' + params.toString()).then(j => {
      if (!j.success) {
        throw new Error('Load failed');
      }

      const list = j.cards || [];
      totalPages = j.total_pages || 0;
      
      // Update pagination visibility and info
      if (totalPages > 1 || currentSearch) {
        pagination.style.display = 'flex';
        updatePaginationControls(j);
      } else {
        pagination.style.display = 'none';
      }

      if (!list.length) {
        const emptyMsg = currentSearch 
          ? `No children found matching "${escapeHtml(currentSearch)}"`
          : 'No registered children yet.';
        body.innerHTML = `<div class="imm-cards-empty">${emptyMsg}</div>`;
        return;
      }

      const items = list.map(c => {
        const pct = c.percent_complete || 0;
        const id = c.child_id;
        const comp = c.vaccines_completed + '/' + c.total_vaccines + ' vaccines';
        return `
          <div class="imm-card-item" data-child="${id}">
            <div class="imm-card-actions">
              <button class="btn-imm-export" data-export="${id}" title="Export PDF">
                <i class="bi bi-download"></i> Export PDF
              </button>
            </div>
            <div>
              <p class="imm-card-title mb-0">${escapeHtml(c.full_name)}</p>
              <p class="imm-card-sub mb-1">Date of Birth: ${c.birth_date ? formatShortDate(c.birth_date) : '—'}</p>
            </div>
            <div class="imm-card-sub" style="font-size:.58rem;margin-top:.2rem;">Immunization Progress</div>
            <div class="imm-progress-bar-wrap">
              <div class="imm-progress-fill" style="width:${pct}%;"></div>
            </div>
            <div class="imm-progress-meta">
              <span>${pct}% complete</span>
              <span>${comp}</span>
            </div>
          </div>
        `;
      }).join('');
      
      body.innerHTML = `<div class="imm-card-list">${items}</div>`;

      // Attach export handlers
      body.querySelectorAll('[data-export]').forEach(btn => {
        btn.addEventListener('click', () => {
          const childId = btn.getAttribute('data-export');
          exportChildCard(childId, btn);
        });
      });

    }).catch(err => {
      body.innerHTML = `<div class="text-danger small py-3">Error: ${escapeHtml(err.message)}</div>`;
      pagination.style.display = 'none';
    });
  }

  function updatePaginationControls(data) {
    const { current_page, total_pages, total_count, page_size } = data;
    
    prevBtn.disabled = current_page <= 1;
    nextBtn.disabled = current_page >= total_pages;
    
    const startItem = total_count > 0 ? ((current_page - 1) * page_size) + 1 : 0;
    const endItem = Math.min(current_page * page_size, total_count);
    
    pageInfo.textContent = `${startItem}-${endItem} of ${total_count}`;
    pageSizeSelect.value = page_size;
  }


  // Initial load
  loadCards();

  function exportChildCard(childId, btn){
    // Visual feedback
    const old = btn.innerHTML;
    btn.disabled=true;
    btn.innerHTML='<span class="spinner-border spinner-border-sm me-1"></span>Preparing';

    fetchJSON(api.immun+'?card=1&child_id='+childId).then(j=>{
      if(!j.success) throw new Error('Load card failed');
      openPrintWindow(j);
    }).catch(err=>{
      alert('Export failed: '+err.message);
    }).finally(()=>{
      btn.disabled=false;
      btn.innerHTML=old;
    });
  }

  function openPrintWindow(data){
    const child = data.child;
    const vaccines = data.vaccines || [];
    const win = window.open('', '_blank');
    const rows = vaccines.map(v=>{
      const dosesReq = v.doses_required;
      const given = v.doses || [];
      // Build dose cells
      let cells='';
      for(let i=1;i<=dosesReq;i++){
        const dRec = given.find(g=>g.dose_number==i);
        cells += `<td style="padding:6px 8px;border:1px solid #ccc;font-size:11px;">
          ${dRec ? '<strong>'+formatShortDate(dRec.vaccination_date)+'</strong>' : '<span style="color:#999;">—</span>'}
        </td>`;
      }
      return `
        <tr>
          <td style="padding:6px 8px;border:1px solid #ccc;font-size:11px;font-weight:600;">${escapeHtml(v.vaccine_code)}</td>
          <td style="padding:6px 8px;border:1px solid #ccc;font-size:11px;">${escapeHtml(v.vaccine_name)}</td>
          ${cells}
        </tr>
      `;
    }).join('');
    const totalVac = vaccines.length;
    const completed = vaccines.reduce((a,v)=>{
      const have = (v.doses||[]).length;
      return a + (have >= v.doses_required ? 1 : 0);
    },0);
    const pct = totalVac ? Math.round((completed/totalVac)*100) : 0;

    win.document.write(`
      <html>
        <head>
          <title>Immunization Card - ${escapeHtml(child.full_name)}</title>
          <meta charset="utf-8">
          <style>
            body{font-family:Arial,Helvetica,sans-serif;margin:30px;color:#222;}
            h1{font-size:20px;margin:0 0 4px;}
            .sub{font-size:12px;color:#555;margin:0 0 18px;}
            table{border-collapse:collapse;width:100%;margin-top:10px;}
            th{background:#f1f4f6;padding:6px 8px;border:1px solid #ccc;font-size:11px;text-transform:uppercase;letter-spacing:.05em;}
            .meta{font-size:12px;margin-top:4px;}
            .progress-bar{height:10px;background:#e3ebe8;border-radius:6px;overflow:hidden;margin:10px 0;width:260px;}
            .progress-fill{height:100%;background:linear-gradient(90deg,#02784a,#00a866);width:${pct}%;}
            @media print {
              .no-print{display:none;}
              body{margin:10mm;}
            }
          </style>
        </head>
        <body>
          <div class="no-print" style="text-align:right;margin-bottom:10px;">
            <button onclick="window.print();" style="padding:6px 12px;font-size:12px;">Print / Save PDF</button>
          </div>
          <h1>Immunization Card</h1>
          <p class="sub">
            <strong>Child:</strong> ${escapeHtml(child.full_name)}<br>
            <strong>Sex:</strong> ${escapeHtml(child.sex)} &nbsp; | &nbsp;
            <strong>DOB:</strong> ${child.birth_date ? formatShortDate(child.birth_date) : '—'}
          </p>
          <div class="meta">
            <strong>Completion:</strong> ${completed}/${totalVac} vaccines (${pct}%)
            <div class="progress-bar"><div class="progress-fill" style="width:${pct}%;"></div></div>
          </div>
          <table>
            <thead>
              <tr>
                <th style="width:70px;">Code</th>
                <th>Vaccine Name</th>
                <th colspan="10">Doses (Date Given)</th>
              </tr>
            </thead>
            <tbody>
              ${rows}
            </tbody>
          </table>
          <p style="font-size:10px;color:#777;margin-top:25px;">Generated on ${new Date().toLocaleString('en-PH',{timeZone:'Asia/Manila',dateStyle:'full',timeStyle:'short'})}</p>
        </body>
      </html>
    `);
    win.document.close();
  }
}

// REPLACE the entire loadParentNotifPanel() function with this email-only version

function loadParentNotifPanel(){
  const panel = document.getElementById('immPanel');
  panel.innerHTML = `
    <div class="imm-card" id="pnWrap">
      <h6 class="mb-2">Parent Notification System</h6>
      <div class="imm-small-muted mb-3">Send reminders and alerts to parents/guardians (Email only)</div>
      <form id="pnForm" class="mb-4" autocomplete="off">
        <div class="row g-3">

          <div class="col-md-4">
            <label style="font-size:.55rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;">Notification Type</label>
            <select name="notification_type" id="pnType" class="form-select form-select-sm">
              <option value="vaccine_overdue">Overdue Vaccination Alert</option>
              <option value="vaccine_due">Upcoming Vaccination Reminder</option>
              <option value="general">Custom / General Notice</option>
            </select>
          </div>

          <div class="col-md-4">
            <label style="font-size:.55rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;">Recipients Scope</label>
            <select name="notification_mode" id="pnMode" class="form-select form-select-sm">
              <option value="overdue">All Overdue (auto)</option>
              <option value="due_soon">All Due Soon (auto)</option>
              <option value="selected">Select Children</option>
              <option value="custom">Custom (manual children)</option>
            </select>
          </div>

          <div class="col-md-4 d-flex align-items-end">
            <div class="w-100">
              <div class="imm-small-muted">Delivery: Email (automatic)</div>
              <button type="button" class="btn btn-sm btn-outline-secondary mt-1" id="pnPreviewBtn" style="font-size:.6rem;">
                <i class="bi bi-eye me-1"></i>Preview
              </button>
            </div>
          </div>

          <div class="col-12" id="pnSelectArea" style="display:none;">
            <label style="font-size:.55rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;">Select Children (Ctrl+Click for multiple)</label>
            <select multiple size="6" class="form-select" id="pnChildSelect"></select>
            <div class="imm-small-muted mt-1">Filter:
              <button type="button" class="btn btn-sm btn-outline-success" id="fltOver" style="font-size:.55rem;">Overdue</button>
              <button type="button" class="btn btn-sm btn-outline-warning" id="fltSoon" style="font-size:.55rem;">Due Soon</button>
              <button type="button" class="btn btn-sm btn-outline-secondary" id="fltAll" style="font-size:.55rem;">All</button>
            </div>
          </div>

          <div class="col-12">
            <label style="font-size:.55rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;">Message</label>
            <textarea name="message_template" id="pnMessage" rows="4" class="form-control" style="font-size:.7rem;" required></textarea>
            <div class="d-flex justify-content-between mt-1" style="font-size:.55rem;font-weight:600;">
              <div>Tokens: [[CHILD]] [[VACCINE]] [[DOSE]] [[DUE_DATE]] [[ITEMS]]</div>
              <div id="pnChar" class="text-muted">0 chars</div>
            </div>
          </div>

          <div class="col-12 d-flex align-items-center gap-3">
            <button class="btn btn-success btn-sm" id="pnSendBtn">
              <i class="bi bi-bell me-1"></i> Send Notifications
            </button>
            <span id="pnStatus" style="font-size:.6rem;font-weight:600;"></span>
          </div>
        </div>
        <input type="hidden" name="csrf_token" value="${window.__BHW_CSRF}">
        <input type="hidden" name="action" value="bulk_send">
        <input type="hidden" name="method_email" value="1">
      </form>

      <div style="font-size:.6rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;margin-bottom:.5rem;">Recent Notifications</div>
      <div id="pnRecent" class="imm-scroll" style="max-height:300px;">
        <div class="text-muted" style="font-size:.62rem;">Loading recent batches...</div>
      </div>

      <hr class="my-4">
      <div style="font-size:.6rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;margin-bottom:.5rem;">Raw Notification Log (Latest 120)</div>
      <div class="imm-scroll" style="max-height:260px;">
        <table class="imm-table" id="pnRawTable">
          <thead>
            <tr><th>Created</th><th>Parent</th><th>Child</th><th>Type</th><th>Title</th><th>Method</th><th>Status</th></tr>
          </thead>
          <tbody><tr><td colspan="7" class="text-center text-muted py-3">Loading...</td></tr></tbody>
        </table>
      </div>
    </div>
  `;

  const defaultTemplates = {
    vaccine_overdue: "Reminder: [[CHILD]] has overdue vaccination(s).\n\n[[ITEMS]]\n\nPlease visit the barangay health center. - BHW",
    vaccine_due: "Reminder: [[CHILD]] has upcoming vaccination(s).\n\n[[ITEMS]]\n\nPlease visit the barangay health center. - BHW",
    general: "Barangay Health Advisory for [[CHILD]]:\n\n[[ITEMS]]\n\nPlease visit the health center. - BHW"
  };
  const msgEl = document.getElementById('pnMessage');
  const typeEl = document.getElementById('pnType');
  const modeEl = document.getElementById('pnMode');
  const charEl = document.getElementById('pnChar');
  const selectArea = document.getElementById('pnSelectArea');
  const childSel = document.getElementById('pnChildSelect');
  const statusEl = document.getElementById('pnStatus');
  const sendBtn = document.getElementById('pnSendBtn');
  const previewBtn = document.getElementById('pnPreviewBtn');

  function applyTemplate(){
    if(!msgEl.value.trim()){
      msgEl.value = defaultTemplates[typeEl.value] || '';
    }
    updateChar();
  }
  function updateChar(){ charEl.textContent = msgEl.value.length + ' chars'; }

  typeEl.addEventListener('change',applyTemplate);
  msgEl.addEventListener('input',updateChar);
  applyTemplate();

  modeEl.addEventListener('change',()=>{ selectArea.style.display = (modeEl.value==='selected'||modeEl.value==='custom')?'block':'none'; });

  Promise.allSettled([
    fetchJSON(api.notif+'?candidates=1'),
    fetchJSON(api.notif+'?recent_summary=1'),
    fetchJSON(api.notif+'?list=1')
  ]).then(([candRes,batchRes,listRes])=>{
    const cand = candRes.value?.candidates || {overdue:[],dueSoon:[]};
    const overdue = cand.overdue;
    const dueSoon = cand.dueSoon;
    buildChildSelect(overdue,dueSoon);

    buildRecentBatches(batchRes.value?.batches || []);
    buildRawTable(listRes.value?.notifications || []);
  }).catch(()=>{});

  function buildChildSelect(overList,soonList){
    const all=[...overList.map(r=>({...r,_tag:'overdue'})), ...soonList.map(r=>({...r,_tag:'dueSoon'}))];
    all.sort((a,b)=>a.child_name.localeCompare(b.child_name));
    childSel.innerHTML = all.map(r=>{
      const label = `${r.child_name} • ${r.vaccine_code||''} ${r.dose_number?('Dose '+r.dose_number):''} • ${r._tag==='overdue'?'Overdue':'DueSoon'}`;
      return `<option value="${r.child_id}" data-tag="${r._tag}">${escapeHtml(label)}</option>`;
    }).join('');
    document.getElementById('fltOver').onclick=()=>filterChild('overdue');
    document.getElementById('fltSoon').onclick=()=>filterChild('dueSoon');
    document.getElementById('fltAll').onclick=()=>filterChild('all');
    function filterChild(tag){
      [...childSel.options].forEach(o=>{ o.classList.toggle('d-none', tag!=='all' && o.dataset.tag!==tag); });
    }
  }

  function buildRecentBatches(batches){
    const wrap=document.getElementById('pnRecent');
    if(!batches.length){
      wrap.innerHTML='<div class="text-muted" style="font-size:.62rem;">No recent notification batches.</div>';
      return;
    }
    wrap.innerHTML = batches.map(b=>{
      const span=(t,c)=>`<span style="background:${c};color:#fff;font-size:.48rem;font-weight:700;padding:3px 6px;border-radius:8px;margin-right:4px;">${t}</span>`;
      return `<div style="border:1px solid #e1e7ea;padding:.55rem .7rem;border-radius:12px;margin-bottom:.55rem;font-size:.6rem;">
        <div style="font-weight:700;">Batch ${escapeHtml(b.batch_key||'')}</div>
        <div class="mt-1">
          ${span('Total '+b.total_notifs, '#0d7c4e')}
          ${b.overdue_count>0? span('Overdue '+b.overdue_count,'#c72d20'):''}
          ${b.due_count>0? span('Due '+b.due_count,'#d48a06'):''}
          ${b.email_count>0? span('Email '+b.email_count,'#124f9c'):''}
        </div>
        <div class="text-muted mt-1" style="font-size:.5rem;">${escapeHtml(b.started_at||'')} → ${escapeHtml(b.ended_at||'')}</div>
      </div>`;
    }).join('');
  }

  function buildRawTable(list){
    const body=document.querySelector('#pnRawTable tbody');
    if(!list.length){ body.innerHTML='<tr><td colspan="7" class="text-center text-muted py-3">No notifications.</td></tr>'; return; }
    body.innerHTML = list.slice(0,120).map(n=>{
      const stBadge = n.is_read
        ? '<span class="imm-badge imm-badge-ok">Read</span>'
        : '<span class="imm-badge imm-badge-duesoon">Unread</span>';
      const methods = (n.method_email?'Email':'');
      return `<tr>
        <td>${escapeHtml(n.created_at||'')}</td>
        <td>${escapeHtml(n.parent_username||'')}</td>
        <td>${escapeHtml(n.child_name||'')}</td>
        <td>${escapeHtml(n.notification_type||'')}</td>
        <td>${escapeHtml(n.title||'')}</td>
        <td>${escapeHtml(methods||'-')}</td>
        <td>${stBadge}</td>
      </tr>`;
    }).join('');
  }

  function setBusy(on,label){
    if(on){
      sendBtn.disabled=true;
      sendBtn.innerHTML='<span class="spinner-border spinner-border-sm me-1"></span>'+ (label||'Sending');
    } else {
      sendBtn.disabled=false;
      sendBtn.innerHTML='<i class="bi bi-bell me-1"></i> Send Notifications';
    }
  }
  function setPreviewBusy(on){
    if(on){
      previewBtn.disabled=true;
      previewBtn.innerHTML='<span class="spinner-border spinner-border-sm me-1"></span>Preview';
    } else {
      previewBtn.disabled=false;
      previewBtn.innerHTML='<i class="bi bi-eye me-1"></i>Preview';
    }
  }

  // Use parseJSONSafe here
  previewBtn.addEventListener('click',()=>{
    statusEl.textContent='';
    const fd=new FormData(document.getElementById('pnForm'));
    fd.set('method_email','1');
    fd.set('preview','1');
    adjustForm(fd);
    setPreviewBusy(true);
    fetch(api.notif,{method:'POST',body:fd})
      .then(parseJSONSafe)
      .then(j=>{
        if(!j.success) throw new Error(j.error||'Preview failed');
        const sample=(j.sample||[]).map(s=>`<div style="border:1px solid #e1e7ea;padding:.4rem .6rem;font-size:.6rem;border-radius:8px;margin-bottom:.4rem;"><strong>#${s.parent_user_id}</strong>: ${escapeHtml(s.message)}</div>`).join('');
        statusEl.innerHTML = `<span class="text-success">Preview OK (${j.targets_count} targets)</span>${sample?'<div class="mt-2">'+sample+'</div>':''}`;
      })
      .catch(err=>{ statusEl.innerHTML='<span class="text-danger">'+escapeHtml(err.message)+'</span>'; })
      .finally(()=>setPreviewBusy(false));
  });

  // Use parseJSONSafe here
  document.getElementById('pnForm').addEventListener('submit',e=>{
    e.preventDefault();
    statusEl.textContent='';
    const fd=new FormData(e.target);
    fd.set('method_email','1');
    adjustForm(fd);
    setBusy(true,'Sending');
    fetch(api.notif,{method:'POST',body:fd})
      .then(parseJSONSafe)
      .then(j=>{
        if(!j.success) throw new Error(j.error||'Send failed');
        const sent = (j.emails_sent!=null) ? ` • Emails sent ${j.emails_sent}` : '';
        statusEl.innerHTML = `<span class="text-success">Created ${j.created} (skipped ${j.skipped}) • Batch ${j.batch_key}${sent}</span>`;
        return Promise.all([ fetchJSON(api.notif+'?recent_summary=1'), fetchJSON(api.notif+'?list=1') ]);
      })
      .then(([b,l])=>{
        buildRecentBatches(b.batches||[]);
        buildRawTable(l.notifications||[]);
      })
      .catch(err=>{
        statusEl.innerHTML='<span class="text-danger">'+escapeHtml(err.message)+'</span>';
      })
      .finally(()=>setBusy(false));
  });

  function adjustForm(fd){
    const mode=fd.get('notification_mode');
    if(mode==='selected' || mode==='custom'){
      const selected=[...childSel.options].filter(o=>o.selected && !o.classList.contains('d-none')).map(o=>o.value);
      selected.forEach(cid=>fd.append('child_ids[]',cid));
    }
  }
}

// REPLACE the whole loadRecordsPanel() function inside renderVaccinationEntry with this version
function loadRecordsPanel(){
  const panel=document.getElementById('immPanel');
  panel.innerHTML=`
    <div class="imm-card">
      <div class="imm-recent-wrap">
        <h6>Recent Vaccination Records</h6>
        <div class="imm-recent-sub">All administered vaccines (latest first)</div>
        
        <!-- Search and Controls -->
        <div class="d-flex justify-content-between align-items-center mb-3 gap-2 flex-wrap">
          <div class="d-flex align-items-center gap-2">
            <input type="text" id="recordsSearch" class="form-control form-control-sm" 
                   placeholder="Search child, vaccine, batch..." style="width:220px;font-size:.7rem;">
            <button type="button" id="recordsSearchBtn" class="btn btn-outline-success btn-sm">
              <i class="bi bi-search me-1"></i>Search
            </button>
            <button type="button" id="recordsClearBtn" class="btn btn-outline-secondary btn-sm">Clear</button>
          </div>
          <div class="d-flex align-items-center gap-2">
            <select id="recordsPageSize" class="form-select form-select-sm" style="width:auto;font-size:.65rem;">
              <option value="20">20 per page</option>
              <option value="50">50 per page</option>
              <option value="100">100 per page</option>
            </select>
          </div>
        </div>

        <div class="imm-scroll" style="max-height:430px;">
          <table class="imm-table" id="immRecentTable">
            <thead>
              <tr>
                <th>Child Name</th>
                <th>Vaccine</th>
                <th>Dose</th>
                <th>Date Given</th>
                <th>Batch No.</th>
                <th>Expiry</th>
                <th>Next Dose</th>
              </tr>
            </thead>
            <tbody>
              <tr><td colspan="7" class="text-muted py-4 text-center">Loading...</td></tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div class="d-flex justify-content-between align-items-center mt-3">
          <div class="imm-small-muted" id="recordsInfo">Showing records...</div>
          <nav>
            <ul class="pagination pagination-sm mb-0" id="recordsPagination">
              <!-- Pagination buttons will be inserted here -->
            </ul>
          </nav>
        </div>
      </div>
    </div>`;

  const dosesRequiredMap={};
  scheduleRaw.forEach(r=>{
    if(r.vaccine_id && r.doses_required){dosesRequiredMap[r.vaccine_id]=r.doses_required;}
    else if(r.vaccine_id && !dosesRequiredMap[r.vaccine_id]){dosesRequiredMap[r.vaccine_id]=r.doses_required||0;}
  });

  // Pagination state
  let currentPage = 1;
  let pageSize = 20;
  let searchQuery = '';
  let allRecords = [];
  let filteredRecords = [];

  // Load initial data
  loadRecords();

  // Event listeners
  document.getElementById('recordsSearchBtn').addEventListener('click', performSearch);
  document.getElementById('recordsClearBtn').addEventListener('click', clearSearch);
  document.getElementById('recordsSearch').addEventListener('keypress', e => {
    if (e.key === 'Enter') performSearch();
  });
  document.getElementById('recordsPageSize').addEventListener('change', e => {
    pageSize = parseInt(e.target.value);
    currentPage = 1;
    renderTable();
  });

  function loadRecords() {
    const body = panel.querySelector('#immRecentTable tbody');
    body.innerHTML = '<tr><td colspan="7" class="text-muted py-4 text-center">Loading...</td></tr>';
    
    fetchJSON(api.immun+'?recent_vaccinations=1&limit=500').then(j=>{
      if(!j.success){
        body.innerHTML='<tr><td colspan="7" class="text-danger text-center py-4">Load failed.</td></tr>';
        return;
      }
      allRecords = j.recent_vaccinations || [];
      filteredRecords = [...allRecords];
      currentPage = 1;
      renderTable();
      updateSearchSummary();
    }).catch(err=>{
      body.innerHTML=`<tr><td colspan="7" class="text-danger text-center py-4">Error: ${escapeHtml(err.message)}</td></tr>`;
    });
  }

  function performSearch() {
    searchQuery = document.getElementById('recordsSearch').value.trim().toLowerCase();
    if (searchQuery === '') {
      filteredRecords = [...allRecords];
    } else {
      filteredRecords = allRecords.filter(r => {
        return (r.child_name || '').toLowerCase().includes(searchQuery) ||
               (r.vaccine_code || '').toLowerCase().includes(searchQuery) ||
               (r.vaccine_name || '').toLowerCase().includes(searchQuery) ||
               (r.batch_lot_number || '').toLowerCase().includes(searchQuery);
      });
    }
    currentPage = 1;
    renderTable();
    updateSearchSummary();
  }

  function clearSearch() {
    document.getElementById('recordsSearch').value = '';
    searchQuery = '';
    filteredRecords = [...allRecords];
    currentPage = 1;
    renderTable();
    updateSearchSummary();
  }

  function updateSearchSummary() {
    const summaryEl = panel.querySelector('.imm-recent-sub');
    if (searchQuery) {
      const resultCount = filteredRecords.length;
      summaryEl.innerHTML = `Found ${resultCount} record${resultCount !== 1 ? 's' : ''} matching "<strong>${escapeHtml(searchQuery)}</strong>"`;
      summaryEl.className = 'imm-recent-sub text-info';
    } else {
      summaryEl.innerHTML = 'All administered vaccines (latest first)';
      summaryEl.className = 'imm-recent-sub';
    }
  }

  function renderTable() {
    const body = panel.querySelector('#immRecentTable tbody');
    const infoEl = document.getElementById('recordsInfo');
    const paginationEl = document.getElementById('recordsPagination');

    if (!filteredRecords.length) {
      body.innerHTML = '<tr><td colspan="7" class="text-muted text-center py-4">No vaccination records found.</td></tr>';
      infoEl.textContent = 'No records found';
      paginationEl.innerHTML = '';
      return;
    }

    // Calculate pagination
    const totalRecords = filteredRecords.length;
    const totalPages = Math.ceil(totalRecords / pageSize);
    const startIndex = (currentPage - 1) * pageSize;
    const endIndex = Math.min(startIndex + pageSize, totalRecords);
    const pageRecords = filteredRecords.slice(startIndex, endIndex);

    // Render table rows
    body.innerHTML = pageRecords.map(r => {
      const doseOrd = ordinal(r.dose_number) + ' Dose';
      const dosesReq = dosesRequiredMap[r.vaccine_id] || null;
      const completed = dosesReq && r.dose_number >= dosesReq;
      let nextHtml = '—';
      if (completed) {
        nextHtml = `<span class="imm-pill-completed">Completed</span>`;
      } else if (r.next_dose_due_date) {
        nextHtml = `<span class="imm-pill-date">${formatShortDate(r.next_dose_due_date)}</span>`;
      }

      const expiryHtml = r.vaccine_expiry_date ? formatShortDate(r.vaccine_expiry_date) : '—';

      return `<tr>
        <td>${escapeHtml(r.child_name||'')}</td>
        <td>${escapeHtml(r.vaccine_code||'')}</td>
        <td>${doseOrd}</td>
        <td>${r.vaccination_date?formatShortDate(r.vaccination_date):'—'}</td>
        <td>${escapeHtml(r.batch_lot_number||'')}</td>
        <td>${expiryHtml}</td>
        <td>${nextHtml}</td>
      </tr>`;
    }).join('');

    // Update info text
    infoEl.textContent = `Showing ${startIndex + 1}-${endIndex} of ${totalRecords} records`;

    // Render pagination
    renderPagination(currentPage, totalPages, paginationEl);
  }

  function renderPagination(page, totalPages, container) {
    if (totalPages <= 1) {
      container.innerHTML = '';
      return;
    }

    let html = '';
    
    // Previous button
    html += `<li class="page-item ${page <= 1 ? 'disabled' : ''}">
      <a class="page-link" href="#" data-page="${page - 1}" style="font-size:.65rem;">Previous</a>
    </li>`;

    // Page numbers (show max 5 pages around current)
    const startPage = Math.max(1, page - 2);
    const endPage = Math.min(totalPages, page + 2);

    if (startPage > 1) {
      html += `<li class="page-item"><a class="page-link" href="#" data-page="1" style="font-size:.65rem;">1</a></li>`;
      if (startPage > 2) {
        html += `<li class="page-item disabled"><span class="page-link" style="font-size:.65rem;">...</span></li>`;
      }
    }

    for (let i = startPage; i <= endPage; i++) {
      html += `<li class="page-item ${i === page ? 'active' : ''}">
        <a class="page-link" href="#" data-page="${i}" style="font-size:.65rem;">${i}</a>
      </li>`;
    }

    if (endPage < totalPages) {
      if (endPage < totalPages - 1) {
        html += `<li class="page-item disabled"><span class="page-link" style="font-size:.65rem;">...</span></li>`;
      }
      html += `<li class="page-item"><a class="page-link" href="#" data-page="${totalPages}" style="font-size:.65rem;">${totalPages}</a></li>`;
    }

    // Next button
    html += `<li class="page-item ${page >= totalPages ? 'disabled' : ''}">
      <a class="page-link" href="#" data-page="${page + 1}" style="font-size:.65rem;">Next</a>
    </li>`;

    container.innerHTML = html;

    // Add click listeners
    container.querySelectorAll('a.page-link').forEach(link => {
      link.addEventListener('click', e => {
        e.preventDefault();
        const newPage = parseInt(e.target.dataset.page);
        if (newPage && newPage !== currentPage && newPage >= 1 && newPage <= totalPages) {
          currentPage = newPage;
          renderTable();
        }
      });
    });
  }

  function ordinal(n){n=parseInt(n,10)||0;const s=['th','st','nd','rd'],v=n%100;return n+(s[(v-20)%10]||s[v]||s[0]);}
}


    function metricCard(label,value,sub,icon){
      return `<div class="imm-metric">
        <div class="imm-metric-label"><i class="bi ${icon}"></i>${escapeHtml(label)}</div>
        <div class="imm-metric-value">${escapeHtml(value)}</div>
        <div class="imm-metric-sub">${escapeHtml(sub)}</div>
      </div>`;
    }
    function buildOverdueRows(overObj){
      const overdueArr=overObj.overdue||[];
      const dueSoon=overObj.dueSoon||[];
      const rows=[];
      overdueArr.forEach(o=>rows.push({child:o.child_name,code:o.vaccine_code,dose:o.dose_number,status:'overdue',age:o.target_age_months,current:o.age_months}));
      dueSoon.forEach(o=>rows.push({child:o.child_name,code:o.vaccine_code,dose:o.dose_number,status:'due',age:o.target_age_months,current:o.age_months}));
      if(!rows.length) return '';
      rows.sort((a,b)=>{
        if(a.status!==b.status) return a.status==='overdue'?-1:1;
        return a.age-b.age;
      });
      return rows.map(r=>{
        const badge=r.status==='overdue'
          ? `<span class="imm-badge imm-badge-overdue">Overdue</span>`
          : `<span class="imm-badge imm-badge-duesoon">Due Soon</span>`;
        return `<tr>
          <td>${escapeHtml(r.child)}</td>
          <td>${escapeHtml(r.code)} - ${ordinal(r.dose)} Dose</td>
          <td>${badge}</td>
          <td>${escapeHtml(mapAge(r.age))}</td>
          <td>${r.current}</td>
        </tr>`;
      }).join('');
    }
    function buildNotifRows(list){
      if(!list.length) return '';
      return list.slice(0,120).map(n=>{
        const stBadge = n.is_read
          ? '<span class="imm-badge imm-badge-ok">Read</span>'
          : '<span class="imm-badge imm-badge-duesoon">Unread</span>';
        return `<tr>
          <td>${escapeHtml(n.created_at||'')}</td>
          <td>${escapeHtml(n.parent_username||'')}</td>
          <td>${escapeHtml(n.child_name||'')}</td>
          <td>${escapeHtml(n.notification_type||'')}</td>
          <td>${escapeHtml(n.title||'')}</td>
          <td>${stBadge}</td>
        </tr>`;
      }).join('');
    }
    function ordinal(n){n=parseInt(n,10)||0;const s=['th','st','nd','rd'],v=n%100;return n+(s[(v-20)%10]||s[v]||s[0]);}
  }).catch(err=>{
    moduleContent.innerHTML = `<div class="alert alert-danger small">Error: ${escapeHtml(err.message)}</div>`;
  });
}


/* ================== Parent Account Management Module (MODIFIED) ================== */
function renderCreateParentAccounts(label){
  showLoading(label);

  Promise.allSettled([
    fetchJSON(api.parent+'?list_parents=1'),
    fetchJSON(api.parent+'?activity=1'),
  ]).then(([parentsRes, activityRes])=>{
    const parents = parentsRes.value?.parents || [];
    const activity = activityRes.value?.activity || [];

    const totalAccounts = parents.length;
    const todayStr = new Date().toISOString().slice(0,10);
    const activeToday = parents.filter(p=>{
      const last = p.last_login_at || p.updated_at || p.created_at;
      return last && last.startsWith(todayStr);
    }).length;
    const totalChildrenLinked = parents.reduce((a,p)=> a + (parseInt(p.children_count)||0), 0);
    const ymNow = todayStr.slice(0,7);
    const newThisMonth = parents.filter(p=> (p.created_at||'').startsWith(ymNow)).length;

    moduleContent.innerHTML = `
      <style>
        .pa-form-section-title{font-size:.62rem;font-weight:800;letter-spacing:.12em;text-transform:uppercase;color:#485b62;margin:1.15rem 0 .65rem;}
        #modalParentCreate .modal-content{border-radius:26px;box-shadow:0 20px 55px -15px rgba(0,0,0,.35),0 8px 28px -10px rgba(0,0,0,.3);border:1px solid #e6ecef;}
        #modalParentCreate .modal-header{border-bottom:1px solid #e2e9ed;background:linear-gradient(120deg,#f6faf9,#edf3f5);border-top-left-radius:inherit;border-top-right-radius:inherit;padding:1.25rem 1.55rem .95rem;}
        #modalParentCreate .modal-title{font-size:1.02rem;font-weight:800;color:#11332c;}
        .pa-create-sub{font-size:.68rem;font-weight:600;color:#5a6a70;margin:.3rem 0 0;}
        #modalParentCreate .modal-body{padding:1.25rem 1.55rem 1.6rem;max-height:calc(100vh - 210px);overflow:auto;}
        #modalParentCreate .modal-footer{border-top:1px solid #e2e9ed;padding:1rem 1.55rem;background:#f9fbfc;border-bottom-left-radius:inherit;border-bottom-right-radius:inherit;}
        .pa-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:1rem 1.9rem;}
        .pa-grid label{font-size:.58rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;margin:0 0 .4rem;color:#2d4e53;display:block;}
        .pa-grid .form-control,.pa-grid .form-select{font-size:.72rem;padding:.55rem .7rem;border-radius:.65rem;}
        .pa-children-box{border:1px solid #d9e4e8;background:#f8fbfc;border-radius:16px;padding:1rem 1.05rem;}
        .pa-child-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:.8rem 1rem;border:1px solid #dbe4e8;background:#ffffff;padding:.8rem .85rem;border-radius:12px;position:relative;}
        .pa-child-row + .pa-child-row{margin-top:.75rem;}
        .pa-child-row.existing-child{border-color:#9ddcc0;box-shadow:0 0 0 2px #d9f5e8;}
        .pa-remove-child{position:absolute;top:6px;right:6px;background:#f5e1df;border:1px solid #e3bbb6;color:#b62a1d;font-size:.55rem;font-weight:700;padding:2px 7px;border-radius:18px;cursor:pointer;}
        .pa-remove-child:hover{background:#f1d2cf;}
        .pa-add-child-btn{font-size:.62rem;font-weight:700;border-radius:22px;padding:.5rem 1rem;}
        .pa-cred-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:1.1rem 2.1rem;margin-top:.5rem;}
        .pa-cred-grid label{font-size:.58rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;margin:0 0 .4rem;color:#2d4d53;display:block;}
        .pa-cred-actions{display:flex;gap:.45rem;margin-top:.45rem;}
        .pa-gen-btn{font-size:.58rem;font-weight:700;padding:.42rem .75rem;border-radius:10px;}
        .pa-small-hint{font-size:.52rem;font-weight:600;color:#66747a;margin-top:.3rem;}
        .pa-msg{font-size:.6rem;font-weight:600;margin-top:.7rem;display:none;}
        .pa-msg.ok{color:#0d7c4e;}
        .pa-msg.err{color:#b62419;}
        @media (max-width:600px){
          #modalParentCreate .modal-dialog{margin:0;max-width:100%;height:100%;display:flex;}
          #modalParentCreate .modal-content{border-radius:0;flex:1;}
          #modalParentCreate .modal-body{max-height:calc(100vh - 170px);}
        }
      </style>

      <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
        <div>
          <h2 class="imm-title" style="margin:0 0 .4rem;">Parent Account Management</h2>
          <p class="imm-sub" style="margin:0;">Create & manage parent/guardian access</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
          <button class="btn btn-success btn-sm" id="paShowCreate"><i class="bi bi-plus-lg me-1"></i> New Parent / Guardian</button>
        </div>
      </div>

      <div class="pa-metrics">
        ${metricCard('Total Accounts', totalAccounts, 'Active parent accounts','bi-people')}
        ${metricCard('Active Today', activeToday, 'Logged in today','bi-activity')}
        ${metricCard('Children Linked', totalChildrenLinked, 'Total children','bi-link-45deg')}
        ${metricCard('New This Month', newThisMonth, 'Recently added','bi-plus-circle')}
      </div>

      <div class="pa-section-card">
        <h6>Parent Accounts</h6>
        <div class="pa-search-row">
          <input type="text" id="paSearch" class="form-control" placeholder="Search parent / username / child ...">
          <div class="pa-filter-badge active" data-filter="all">All</div>
            <div class="pa-filter-badge" data-filter="active">Active</div>
          <div class="pa-filter-badge" data-filter="inactive">Inactive</div>
        </div>
        <div class="pa-table-wrap">
          <table class="pa-table" id="paTable">
            <thead>
              <tr>
                <th>Parent Name</th>
                <th>Contact</th>
                <th>Children</th>
                <th>Username</th>
                <th>Status</th>
                <th>Last Active</th>
                <th style="min-width:140px;">Actions</th>
              </tr>
            </thead>
            <tbody>${buildParentRows(parents)}</tbody>
          </table>
        </div>
      </div>

      <div class="pa-section-card">
        <h6 class="mb-1">Recent Account Activity</h6>
        <div class="text-muted" style="font-size:.62rem;margin-top:-2px;margin-bottom:.85rem;font-weight:600;">
          Login history and account actions
        </div>
        <div class="row g-4">
          <div class="col-lg-5">
            <div style="font-size:.62rem;font-weight:700;letter-spacing:.06em;margin-bottom:.55rem;">Account Activity Summary</div>
            <div id="paActivitySummaryWrap">
              <div class="text-muted" style="font-size:.6rem;">Loading summary...</div>
            </div>
          </div>
          <div class="col-lg-7">
            <div style="font-size:.62rem;font-weight:700;letter-spacing:.06em;margin-bottom:.55rem;">Detailed Log</div>
            
            <div class="pa-activity-controls" id="paActivityControls" style="display:none;">
              <div style="font-size:.55rem;color:#6a7b82;font-weight:600;">Activity History</div>
              <div class="pa-activity-pagination" id="paActivityPagination">
                <button id="paActivityPrevBtn" title="Previous page">
                  <i class="bi bi-chevron-left"></i>
                </button>
                <div class="pa-activity-page-info" id="paActivityPageInfo"></div>
                <button id="paActivityNextBtn" title="Next page">
                  <i class="bi bi-chevron-right"></i>
                </button>
                <select id="paActivityPageSize" class="pa-activity-page-size" title="Items per page">
                  <option value="5">5</option>
                  <option value="10" selected>10</option>
                  <option value="20">20</option>
                </select>
              </div>
            </div>
            
            <div id="paActivityFeedWrap">
              <div class="text-muted" style="font-size:.6rem;">Loading recent activity...</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Parent Create Modal -->
      <div class="modal fade" id="modalParentCreate" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-xl">
          <div class="modal-content">
            <div class="modal-header">
              <div>
                <h5 class="modal-title"><i class="bi bi-person-plus me-1"></i> Create Parent/Guardian Account</h5>
                <p class="pa-create-sub mb-0">Register a new parent account with access credentials</p>
              </div>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="paFullCreateForm" autocomplete="off">
              <!-- DATALIST FOR CHILD AUTOCOMPLETE (ADDED) -->
              <datalist id="paChildrenDatalist"></datalist>
              <div class="modal-body">
                <div class="pa-form-section-title">Parent/Guardian Information</div>
                <div class="pa-grid">
                  <div>
                    <label>First Name *</label>
                    <input name="first_name" class="form-control" required>
                  </div>
                  <div>
                    <label>Last Name *</label>
                    <input name="last_name" class="form-control" required>
                  </div>
                  <div>
                    <label>Relationship to Child *</label>
                    <select name="relationship_type" class="form-select" required>
                      <option value="">Select relationship</option>
                      <option value="mother">Mother</option>
                      <option value="father">Father</option>
                      <option value="guardian">Guardian</option>
                      <option value="caregiver">Caregiver</option>
                    </select>
                  </div>
                  <div>
                    <label>Contact Number</label>
                    <input name="contact_number" class="form-control" placeholder="09XX-XXX-XXXX">
                  </div>
                  <div>
                    <label>Email Address (Optional)</label>
                    <input type="email" name="email" class="form-control" placeholder="email@example.com">
                  </div>
                  <!-- REPLACED Address WITH Parent Birthday -->
                  <div>
                    <label>Parent Birthday *</label>
                    <input type="date" name="parent_birth_date" class="form-control" required>
                  </div>
                </div>

                <div class="pa-form-section-title">Link Child to Account</div>
                <div class="pa-children-box">
                  <div id="paChildRows"></div>
                  <button type="button" class="btn btn-outline-success pa-add-child-btn mt-3" id="paAddChildBtn">
                    <i class="bi bi-plus-lg me-1"></i> Add Another Child
                  </button>
                  <div class="pa-small-hint mt-2">Type existing child name to auto-fill DOB & Gender. Click (clear) to revert to manual entry.</div>
                </div>

                <div class="pa-form-section-title">Access Credentials</div>
                <div class="pa-cred-grid">
                  <div>
                    <label>Username *</label>
                    <input name="username" id="paUsername" class="form-control" placeholder="Auto-generated username" required>
                    <div class="pa-cred-actions">
                      <button type="button" class="btn btn-outline-secondary pa-gen-btn" id="paGenUser">Generate</button>
                    </div>
                    <div class="pa-small-hint" id="paUserHint">Format: firstword + first & last letter of surname (e.g. Maria Marvic → mariamc)</div>
                  </div>
                  <div>
                    <label>Password</label>
                    <div class="d-flex align-items-center gap-2">
                      <input name="password" id="paPassword" class="form-control" type="password" placeholder="Auto-generated if blank">
                      <button type="button" class="btn btn-outline-secondary pa-gen-btn" id="paGenPass">Generate</button>
                      <button type="button" class="btn btn-outline-secondary pa-gen-btn" id="paShowPass"><i class="bi bi-eye"></i></button>
                    </div>
                    <div class="pa-small-hint" id="paPassHint">If blank: LastName + birth month (e.g. Marvic02) or secure random if birthday missing.</div>
                  </div>
                </div>

                <input type="hidden" name="csrf_token" value="${window.__BHW_CSRF}">
                <input type="hidden" name="create_parent" value="1">
                <div class="pa-msg ok" id="paMsgOk"><i class="bi bi-check-circle me-1"></i>Saved!</div>
                <div class="pa-msg err" id="paMsgErr"></div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                <button class="btn btn-success btn-sm" id="paSaveBtn"><i class="bi bi-check-circle me-1"></i>Create Account</button>
              </div>
            </form>
          </div>
        </div>
      </div>
    `;

    // Render old summary (aggregate notifications/activity)
    renderActivitySummary(activity);
    function renderActivitySummary(list){
      const wrap=document.getElementById('paActivitySummaryWrap');
      if(!wrap) return;
      if(!list.length){
        wrap.innerHTML='<div class="text-muted" style="font-size:.6rem;">No activity summary.</div>';
        return;
      }
      wrap.innerHTML='<ul class="pa-activity-summary">'+
        list.slice(0,60).map(r=>{
          const last=r.last_notification_date
            ? fmtDateTime(r.last_notification_date)
            : '—';
          const unread=parseInt(r.unread_notifications)||0;
          return `<li class="pa-activity-summary-item">
            <strong>${escapeHtml(r.username)}</strong>
            <div class="pa-activity-summary-meta">
              ${r.children_count||0} child link(s)
            </div>
            <div class="pa-activity-summary-meta">
              Notifs: ${r.total_notifications||0} (Unread ${unread})
              • Last: ${escapeHtml(last)}
            </div>
          </li>`;
        }).join('')+'</ul>';
    }
    function fmtDateTime(iso){
      if(!iso) return '';
      const d=new Date(iso.replace(' ','T'));
      if(isNaN(d)) return escapeHtml(iso);
      return d.toLocaleString('en-PH',{timeZone:'Asia/Manila',month:'short',day:'numeric',hour:'2-digit',minute:'2-digit',hour12:true});
    }

    // Activity pagination state
    let activityCurrentPage = 1;
    let activityPageSize = 10;
    let activityTotalPages = 0;

    // Event listeners for activity pagination
    const activityControls = document.getElementById('paActivityControls');
    const activityPrevBtn = document.getElementById('paActivityPrevBtn');
    const activityNextBtn = document.getElementById('paActivityNextBtn');
    const activityPageSizeSelect = document.getElementById('paActivityPageSize');
    const activityPageInfo = document.getElementById('paActivityPageInfo');

    if (activityPrevBtn) {
      activityPrevBtn.addEventListener('click', () => {
        if (activityCurrentPage > 1) {
          activityCurrentPage--;
          loadActivityFeed();
        }
      });
    }

    if (activityNextBtn) {
      activityNextBtn.addEventListener('click', () => {
        if (activityCurrentPage < activityTotalPages) {
          activityCurrentPage++;
          loadActivityFeed();
        }
      });
    }

    if (activityPageSizeSelect) {
      activityPageSizeSelect.addEventListener('change', (e) => {
        activityPageSize = parseInt(e.target.value);
        activityCurrentPage = 1;
        loadActivityFeed();
      });
    }

    function loadActivityFeed() {
      const wrap = document.getElementById('paActivityFeedWrap');
      if (!wrap) return;
      
      wrap.innerHTML = '<div class="text-muted" style="font-size:.6rem;">Loading recent activity...</div>';

      const params = new URLSearchParams({
        recent_activity: '1',
        page: activityCurrentPage,
        page_size: activityPageSize
      });

      fetchJSON(api.parent + '?' + params.toString())
        .then(j => {
          if (!j.success) {
            wrap.innerHTML = '<div class="text-danger" style="font-size:.6rem;">Failed to load activity.</div>';
            if (activityControls) activityControls.style.display = 'none';
            return;
          }

          const logs = j.recent_activity || [];
          activityTotalPages = j.total_pages || 0;

          // Update pagination visibility and controls
          if (activityTotalPages > 1) {
            if (activityControls) activityControls.style.display = 'flex';
            updateActivityPaginationControls(j);
          } else {
            if (activityControls) activityControls.style.display = 'none';
          }

          if (!logs.length) {
            wrap.innerHTML = '<div class="text-muted" style="font-size:.6rem;">No recent activity.</div>';
            return;
          }

          wrap.innerHTML = '<ul class="pa-activity-feed">' +
            logs.map(l => {
              const fullname = escapeHtml((l.first_name || '') + ' ' + (l.last_name || ''));
              const desc = escapeHtml(l.activity_description || '');
              const child = l.child_name ? ' • ' + escapeHtml(l.child_name) : '';
              const when = relativeTime(l.created_at);
              const ip = l.ip_address ? 'IP: ' + escapeHtml(l.ip_address) : '';
              const badge = actionBadge(l.action_code);
              return `<li class="pa-activity-item2">
                <div class="pa-activity-top">
                  <div>
                    <span class="pa-activity-name">${fullname}</span>
                    ${badge}
                  </div>
                  <span class="pa-activity-time">${when}</span>
                </div>
                <div class="pa-activity-desc">${desc}${child}</div>
                <div class="pa-activity-ip">${ip}</div>
              </li>`;
            }).join('') + '</ul>';
        })
        .catch(() => {
          wrap.innerHTML = '<div class="text-danger" style="font-size:.6rem;">Error loading activity.</div>';
          if (activityControls) activityControls.style.display = 'none';
        });
    }

    function updateActivityPaginationControls(data) {
      const { current_page, total_pages, total_count, page_size } = data;
      
      if (activityPrevBtn) activityPrevBtn.disabled = current_page <= 1;
      if (activityNextBtn) activityNextBtn.disabled = current_page >= total_pages;
      
      const startItem = total_count > 0 ? ((current_page - 1) * page_size) + 1 : 0;
      const endItem = Math.min(current_page * page_size, total_count);
      
      if (activityPageInfo) activityPageInfo.textContent = `${startItem}-${endItem} of ${total_count}`;
      if (activityPageSizeSelect) activityPageSizeSelect.value = page_size;
    }

    // Initial load
    loadActivityFeed();

    function relativeTime(iso){
      if(!iso) return '';
      const d=new Date(iso.replace(' ','T'));
      if(isNaN(d)) return escapeHtml(iso);
      const now=new Date();
      const sec=Math.floor((now-d)/1000);
      if(sec<60) return sec+'s ago';
      const m=Math.floor(sec/60);
      if(m<60) return m+'m ago';
      const h=Math.floor(m/60);
      if(h<24) return h+'h ago';
      const day=Math.floor(h/24);
      if(day===1) return 'Kahapon'; // Yesterday in Filipino
      if(day<7) return day+'d ago';
      const wk=Math.floor(day/7);
      if(wk<5) return wk+'w ago';
      const mo=Math.floor(day/30);
      if(mo<12) return mo+'mo ago';
      const yr=Math.floor(day/365);
      return yr+'y ago';
    }
    function actionBadge(code){
      const map={
        login:'LOGIN',
        view_card:'VIEW',
        download_card:'DOWNLOAD',
        view_record:'VIEW',
        update_profile:'UPDATE',
        create_account:'NEW',
        deactivate_account:'OFF',
        activate_account:'ON',
        reset_password:'RESET'
      };
      const lbl=map[code] || code.toUpperCase().replace(/[^A-Z0-9]/g,'');
      return `<span class="pa-activity-badge">${lbl}</span>`;
    }

    document.getElementById('paShowCreate').addEventListener('click',()=>{
      bootstrap.Modal.getOrCreateInstance(document.getElementById('modalParentCreate')).show();
      initCreateFormLogic();
    });

    function initCreateFormLogic(){
      const form = document.getElementById('paFullCreateForm');
      if(form.dataset.bound==='1') return;
      form.dataset.bound='1';

      const childRowsBox = document.getElementById('paChildRows');
      const addBtn = document.getElementById('paAddChildBtn');
      const genUserBtn = document.getElementById('paGenUser');
      const genPassBtn = document.getElementById('paGenPass');
      const showPassBtn = document.getElementById('paShowPass');
      const passInput = document.getElementById('paPassword');
      const userInput = document.getElementById('paUsername');
      const msgOk = document.getElementById('paMsgOk');
      const msgErr = document.getElementById('paMsgErr');
      const saveBtn = document.getElementById('paSaveBtn');

      /* ===== Child autocomplete (NEW) ===== */
      let childrenCache = null;
      const childrenMap = {};
      function fetchChildrenList(){
        if(childrenCache!==null) return Promise.resolve(childrenCache);
        return fetchJSON(api.immun+'?children=1').then(j=>{
          if(!j.success){childrenCache=[];return childrenCache;}
          childrenCache = j.children||[];
          const dl = document.getElementById('paChildrenDatalist');
            dl.innerHTML = childrenCache.map(c=>{
            childrenMap[c.full_name.toLowerCase()] = c;
            return `<option value="${escapeHtml(c.full_name)}"></option>`;
          }).join('');
          return childrenCache;
        }).catch(()=>{childrenCache=[];return childrenCache;});
      }
      function activateExistingChild(row, child){
        const hidden=row.querySelector('[name=existing_child_id]');
        hidden.value = child.child_id;
        const nameEl=row.querySelector('.pa-child-name');
        const dobEl=row.querySelector('.pa-child-dob');
        const sexEl=row.querySelector('.pa-child-sex');
        dobEl.value = child.birth_date||'';
        sexEl.value = child.sex||'';
        dobEl.readOnly = true;
        sexEl.disabled = true;
        nameEl.classList.add('is-valid');
        row.classList.add('existing-child');
        row.querySelector('[data-existing-note]').style.display='block';
      }
      function clearExistingChild(row){
        const hidden=row.querySelector('[name=existing_child_id]');
        hidden.value='';
        const dobEl=row.querySelector('.pa-child-dob');
        const sexEl=row.querySelector('.pa-child-sex');
        dobEl.readOnly=false;
        sexEl.disabled=false;
        row.querySelector('.pa-child-name').classList.remove('is-valid');
        row.classList.remove('existing-child');
        row.querySelector('[data-existing-note]').style.display='none';
      }

      function addChildRow(pref={}){
        const id='c'+Math.random().toString(36).slice(2,9);
        const html=`
          <div class="pa-child-row" data-row="${id}">
            <button type="button" class="pa-remove-child d-none" data-remove="${id}" title="Remove">&times;</button>
            <input type="hidden" name="existing_child_id" value="">
            <div style="position:relative;">
              <label>Child's Full Name *</label>
              <input class="form-control pa-child-name" name="child_full_name" list="paChildrenDatalist" placeholder="Type to search existing..." required value="${pref.full_name||''}">
              <div class="pa-small-hint" style="display:none;color:#0d6d42;font-size:.5rem;font-weight:700;" data-existing-note>
                Existing child selected
                <button type="button" class="btn btn-sm btn-link p-0 ms-1" data-clear-child style="font-size:.55rem;">(clear)</button>
              </div>
            </div>
            <div>
              <label>Date of Birth *</label>
              <input type="date" class="form-control pa-child-dob" name="child_birth_date" required value="${pref.birth_date||''}">
            </div>
            <div>
              <label>Gender *</label>
              <select class="form-select pa-child-sex" name="child_sex" required>
                <option value="">Select</option>
                <option value="male" ${pref.sex==='male'?'selected':''}>Male</option>
                <option value="female" ${pref.sex==='female'?'selected':''}>Female</option>
              </select>
            </div>
          </div>`;
        childRowsBox.insertAdjacentHTML('beforeend',html);
        refreshRemoveButtons();
        fetchChildrenList();
      }
      function refreshRemoveButtons(){
        const rows=[...childRowsBox.querySelectorAll('.pa-child-row')];
        rows.forEach(r=>{
          const btn=r.querySelector('.pa-remove-child');
          if(rows.length>1) btn.classList.remove('d-none'); else btn.classList.add('d-none');
        });
      }
      childRowsBox.addEventListener('click',e=>{
        if(e.target.matches('[data-clear-child]')){
          const row=e.target.closest('.pa-child-row');
          clearExistingChild(row);
        }
        const b=e.target.closest('[data-remove]'); if(!b) return;
        const id=b.dataset.remove;
        const row=childRowsBox.querySelector(`.pa-child-row[data-row="${id}"]`);
        if(row){row.remove();refreshRemoveButtons();}
      });
      childRowsBox.addEventListener('change',e=>{
        if(!e.target.classList.contains('pa-child-name')) return;
        const row=e.target.closest('.pa-child-row');
        const val=e.target.value.trim().toLowerCase();
        if(val && childrenMap[val]) activateExistingChild(row, childrenMap[val]);
        else clearExistingChild(row);
      });
      addBtn.addEventListener('click',()=>addChildRow());
      addChildRow(); // first row

      /* ===== Username Generation (NEW RULE) ===== */
      genUserBtn.addEventListener('click',()=>{
        const fnRaw=(form.first_name.value||'').trim();
        const lnRaw=(form.last_name.value||'').trim();
        if(!fnRaw || !lnRaw){
          userInput.value='parent_'+Math.random().toString(36).slice(2,6);
          return;
        }
        const firstWord = fnRaw.split(/\s+/)[0].toLowerCase().replace(/[^a-z0-9]/g,'');
        const lastName  = lnRaw.replace(/\s+/g,'').toLowerCase().replace(/[^a-z0-9]/g,'');
        if(!lastName){
          userInput.value = firstWord;
          return;
        }
        const firstLetter = lastName.charAt(0);
        const lastLetter = lastName.charAt(lastName.length-1);
        userInput.value = firstWord + firstLetter + lastLetter; // e.g. mariamc
      });

      /* ===== Password Generation (NEW RULE) ===== */
      function generatePasswordFromBirthday(){
        const lnRaw=(form.last_name.value||'').trim();
        const bday=form.parent_birth_date?.value || '';
        if(!lnRaw || !/^(\d{4})-(\d{2})-(\d{2})$/.test(bday)) return null;
        const month=bday.slice(5,7);
        return lnRaw.replace(/\s+/g,'') + month;
      }
      function generateSecureFallback(){
        const chars='ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%';
        let out=''; for(let i=0;i<12;i++) out+=chars[Math.floor(Math.random()*chars.length)];
        return out;
      }
      function setPasswordAuto(){
        const rulePwd=generatePasswordFromBirthday();
        passInput.value= rulePwd || generateSecureFallback();
      }
      genPassBtn.addEventListener('click',()=>setPasswordAuto());
      form.parent_birth_date.addEventListener('change',()=>{
        if(passInput.value.trim()===''){
          const rulePwd=generatePasswordFromBirthday();
          if(rulePwd) passInput.value=rulePwd;
        }
      });
      showPassBtn.addEventListener('click',()=>{
        passInput.type = passInput.type==='password'?'text':'password';
        showPassBtn.innerHTML = passInput.type==='password'
          ? '<i class="bi bi-eye"></i>'
          : '<i class="bi bi-eye-slash"></i>';
      });

      /* ===== Submit ===== */
      form.addEventListener('submit',e=>{
        e.preventDefault();
        msgOk.style.display='none';
        msgErr.style.display='none';

        const rows=[...childRowsBox.querySelectorAll('.pa-child-row')];
        if(!rows.length){
          msgErr.textContent='Add at least one child.'; msgErr.style.display='block'; return;
        }
        const newChildren=[];
        for(const r of rows){
          const full=r.querySelector('[name=child_full_name]').value.trim();
          const dob=r.querySelector('[name=child_birth_date]').value;
          const sex=r.querySelector('[name=child_sex]').value;
          const existingId=r.querySelector('[name=existing_child_id]').value.trim();
          if(!full||!dob||!sex){
            msgErr.textContent='Complete all child fields.'; msgErr.style.display='block'; return;
          }
          const entry={full_name:full,birth_date:dob,sex};
          if(existingId) entry.child_id = existingId; // preserve link if existing
          newChildren.push(entry);
        }

        const fd=new FormData();
        fd.append('create_parent','1');
        fd.append('csrf_token', window.__BHW_CSRF);
        // updated field list (parent_birth_date instead of address_details)
        ['first_name','last_name','relationship_type','email','contact_number','parent_birth_date','username','password']
          .forEach(k=>{ if(form[k]) fd.append(k, form[k].value); });
        if(!passInput.value.trim()){ // auto-generate if user left blank
          const rulePwd=generatePasswordFromBirthday();
          fd.set('password', rulePwd || generateSecureFallback());
        }
        fd.append('new_children', JSON.stringify(newChildren));

        saveBtn.disabled=true;
        saveBtn.innerHTML='<span class="spinner-border spinner-border-sm me-1"></span>Saving';

        fetch(api.parent,{method:'POST',body:fd})
          .then(r=>r.json())
          .then(j=>{
            if(!j.success) throw new Error(j.error||'Save failed');
            // After JSON success: notify about email delivery status
if (j.email_sent) {
  alert('Account created. Credentials emailed to: ' + (form.email ? form.email.value : ''));
} else if (form.email && form.email.value.trim() !== '') {
  const errDetail = j.email_error ? ('\nReason: ' + j.email_error) : '';
  alert('Account created BUT email sending failed.' + errDetail + '\nPlease give credentials manually.');
}
            msgOk.style.display='block';
            setTimeout(()=>{
              bootstrap.Modal.getInstance(document.getElementById('modalParentCreate')).hide();
              renderCreateParentAccounts(label);
            },650);
          })
          .catch(err=>{
            msgErr.textContent=err.message;
            msgErr.style.display='block';
          })
          .finally(()=>{
            saveBtn.disabled=false;
            saveBtn.innerHTML='<i class="bi bi-check-circle me-1"></i>Create Account';
          });
      });
    }

    /* Filters */
    const searchEl=document.getElementById('paSearch');
    const filterBadges=moduleContent.querySelectorAll('.pa-filter-badge');
    const tableBody=moduleContent.querySelector('#paTable tbody');
    function applyFilter(){
      const q=(searchEl.value||'').toLowerCase();
      const activeFilter = moduleContent.querySelector('.pa-filter-badge.active')?.dataset.filter || 'all';
      [...tableBody.rows].forEach(row=>{
        const txt=row.innerText.toLowerCase();
        let show = txt.includes(q);
        if(show && activeFilter!=='all'){
          const st=row.getAttribute('data-status');
          show = activeFilter==='active' ? st==='1' : st==='0';
        }
        row.classList.toggle('d-none',!show);
      });
    }
    searchEl.addEventListener('input',applyFilter);
    filterBadges.forEach(b=>{
      b.addEventListener('click',()=>{
        filterBadges.forEach(x=>x.classList.remove('active'));
        b.addEventListener('blur',()=>{});
        b.classList.add('active');
        applyFilter();
      });
    });

tableBody.querySelectorAll('[data-action]').forEach(btn=>{
  btn.addEventListener('click',()=>{
    const action=btn.dataset.action;
    const tr = btn.closest('tr');
    const pid = tr?.dataset.id;
    if(!pid) return;

    // Helper to fetch parent object from loaded list
    const parentObj = (parents || []).find(p => String(p.user_id) === String(pid));

    if(action==='toggle')      toggleActive(pid,btn);
    else if(action==='reset')  resetPassword(pid,btn);           // kept for backward compatibility if present
    else if(action==='view')   openParentViewModal(parentObj);
    else if(action==='edit')   openParentEditModal(parentObj, btn);
  });
});

function openParentViewModal(p){
  if(!p){ alert('Parent not found.'); return; }
  let modal = document.getElementById('paViewModal');
  if(!modal){
    document.body.insertAdjacentHTML('beforeend', `
      <div class="modal fade" id="paViewModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content" style="border-radius:18px;">
            <div class="modal-header">
              <h5 class="modal-title" style="font-size:.9rem;font-weight:800;">Parent Details</h5>
              <button class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="paViewBody" style="font-size:.78rem;"></div>
            <div class="modal-footer">
              <button class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
          </div>
        </div>
      </div>
    `);
    modal = document.getElementById('paViewModal');
  }
  const body = document.getElementById('paViewBody');
  const fullName = (p.first_name||'')+' '+(p.last_name||'');
  const lastActive = (p.updated_at || p.created_at || '').replace('T',' ').slice(0,16) || '—';
  body.innerHTML = `
    <div class="mb-2"><strong>Name:</strong> ${escapeHtml(fullName.trim())}</div>
    <div class="row g-2">
      <div class="col-6"><strong>Username:</strong> ${escapeHtml(p.username||'')}</div>
      <div class="col-6"><strong>Status:</strong> ${p.is_active? '<span class="pa-status-active">Active</span>' : '<span class="pa-status-inactive">Inactive</span>'}</div>
      <div class="col-6"><strong>Email:</strong> ${escapeHtml(p.email||'—')}</div>
      <div class="col-6"><strong>Contact:</strong> ${escapeHtml(p.contact_number||'—')}</div>
      <div class="col-12"><strong>Children:</strong> ${escapeHtml(p.children_list||'—')}</div>
      <div class="col-12"><strong>Last Active:</strong> ${escapeHtml(lastActive)}</div>
    </div>
  `;
  bootstrap.Modal.getOrCreateInstance(modal).show();
}

// REPLACE the openParentEditModal() in dashboard_bhw.php with this version

function openParentEditModal(p, triggerBtn){
  if(!p){ alert('Parent not found.'); return; }

  let modal = document.getElementById('paEditModal');
  if(!modal){
    document.body.insertAdjacentHTML('beforeend', `
      <div class="modal fade" id="paEditModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg">
          <div class="modal-content" style="border-radius:18px;">
            <div class="modal-header">
              <h5 class="modal-title" style="font-size:.9rem;font-weight:800;">Edit Parent</h5>
              <button class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="paEditBody" style="font-size:.78rem;">
              <div class="mb-2"><strong>Name:</strong> <span id="paEdName"></span></div>
              <div class="row g-2">
                <div class="col-md-4">
                  <label class="form-label" style="font-size:.62rem;font-weight:700;letter-spacing:.06em;">Username</label>
                  <input class="form-control form-control-sm" id="paEdUser" readonly>
                </div>
                <div class="col-md-4">
                  <label class="form-label" style="font-size:.62rem;font-weight:700;letter-spacing:.06em;">Email</label>
                  <input class="form-control form-control-sm" id="paEdEmail" readonly>
                </div>
                <div class="col-md-4">
                  <label class="form-label" style="font-size:.62rem;font-weight:700;letter-spacing:.06em;">Contact</label>
                  <input class="form-control form-control-sm" id="paEdContact" readonly>
                </div>
              </div>

              <div class="mt-3">
                <button class="btn btn-outline-warning btn-sm" id="paEdResetBtn">
                  <i class="bi bi-key me-1"></i> Reset Password
                </button>
                <div class="small text-muted mt-2">Note: Deactivate/Activate is available via the row toggle button.</div>
              </div>

              <hr class="my-3">

              <!-- Linked Children Section -->
              <div>
                <div class="d-flex justify-content-between align-items-center">
                  <h6 class="m-0" style="font-size:.75rem;font-weight:800;letter-spacing:.06em;text-transform:uppercase;color:#24433f;">
                    Linked Children
                  </h6>
                  <button class="btn btn-sm btn-outline-secondary" id="paReloadLinksBtn" style="font-size:.6rem;">
                    <i class="bi bi-arrow-repeat me-1"></i>Refresh
                  </button>
                </div>
                <div id="paLinkedChildren" class="mt-2">
                  <div class="text-muted" style="font-size:.7rem;">Loading linked children...</div>
                </div>

                <div class="border rounded p-2 mt-3">
                  <div class="row g-2 align-items-end">
                    <div class="col-md-7">
                      <label class="form-label" style="font-size:.62rem;font-weight:700;letter-spacing:.06em;">Select Child to Link</label>
                      <select class="form-select form-select-sm" id="paLinkChildSel">
                        <option value="">Choose child</option>
                      </select>
                    </div>
                    <div class="col-md-3">
                      <label class="form-label" style="font-size:.62rem;font-weight:700;letter-spacing:.06em;">Relationship</label>
                      <select class="form-select form-select-sm" id="paLinkRelSel">
                        <option value="mother">Mother</option>
                        <option value="father">Father</option>
                        <option value="guardian" selected>Guardian</option>
                        <option value="caregiver">Caregiver</option>
                      </select>
                    </div>
                    <div class="col-md-2 text-end">
                      <button class="btn btn-success btn-sm w-100" id="paLinkChildBtn">
                        <i class="bi bi-link-45deg me-1"></i> Link
                      </button>
                    </div>
                  </div>
                  <div class="small text-muted mt-1">Tip: Use the list to link an existing child. Unlink to remove access.</div>
                  <div class="small text-danger mt-1 d-none" id="paLinkErr"></div>
                  <div class="small text-success mt-1 d-none" id="paLinkOk">Linked!</div>
                </div>
              </div>
            </div>
            <div class="modal-footer">
              <button class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
          </div>
        </div>
      </div>
    `);
    modal = document.getElementById('paEditModal');
  }

  // Prefill readonly fields
  document.getElementById('paEdName').textContent = ((p.first_name||'')+' '+(p.last_name||'')).trim();
  document.getElementById('paEdUser').value = p.username || '';
  document.getElementById('paEdEmail').value = p.email || '';
  document.getElementById('paEdContact').value = p.contact_number || '';

  const resetBtn = document.getElementById('paEdResetBtn');
  resetBtn.disabled = false;
  resetBtn.textContent = 'Reset Password';
  resetBtn.onclick = ()=>{
    resetBtn.disabled=true;
    resetBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Resetting';
    resetPassword(p.user_id, resetBtn);
    setTimeout(()=>{ resetBtn.disabled=false; resetBtn.textContent='Reset Password'; }, 1200);
  };

  // Elements
  const childSel = document.getElementById('paLinkChildSel');
  const relSel   = document.getElementById('paLinkRelSel');
  const linkBtn  = document.getElementById('paLinkChildBtn');
  const linkErr  = document.getElementById('paLinkErr');
  const linkOk   = document.getElementById('paLinkOk');
  const listBox  = document.getElementById('paLinkedChildren');

  // Local caches
  let allChildren = null;        // full children list (loaded once)
  let linkedIdSet = new Set();   // currently linked (ACTIVE) child IDs for this parent

  function setLinkBusy(on){
    linkBtn.disabled = !!on;
    linkBtn.innerHTML = on
      ? '<span class="spinner-border spinner-border-sm me-1"></span>Linking'
      : '<i class="bi bi-link-45deg me-1"></i> Link';
  }
  function showLinkMsg(ok,msg){
    if(ok){
      linkErr.classList.add('d-none');
      linkOk.textContent = msg || 'Linked!';
      linkOk.classList.remove('d-none');
      setTimeout(()=>linkOk.classList.add('d-none'), 1400);
    }else{
      linkOk.classList.add('d-none');
      linkErr.textContent = msg || 'Failed';
      linkErr.classList.remove('d-none');
    }
  }
  function fmtShort(d){
    if(!d) return '—';
    const dt = new Date(d+'T00:00:00');
    if(isNaN(dt)) return d;
    return dt.toLocaleDateString('en-PH',{timeZone:'Asia/Manila',month:'short',day:'numeric',year:'numeric'});
  }

  // Build dropdown options from cache while excluding ACTIVE links only
  function refreshChildOptions(){
    if(!Array.isArray(allChildren)) return;
    const options = ['<option value="">Choose child</option>']
      .concat(
        allChildren
          .filter(c => !linkedIdSet.has(parseInt(c.child_id,10))) // hide already-linked (active) only
          .map(c=>{
            const age = (c.age_months!=null ? ` (${c.age_months}m)` : '');
            const bd = c.birth_date ? ` — ${fmtShort(c.birth_date)}` : '';
            return `<option value="${c.child_id}">${escapeHtml(c.full_name)}${age}${bd}</option>`;
          })
      ).join('');
    childSel.innerHTML = options;
  }

  // Load and cache ALL children (once)
  function preloadChildrenList(){
    if(allChildren!==null){ refreshChildOptions(); return; }
    fetchJSON(api.immun+'?children=1').then(j=>{
      if(!j.success) throw new Error('Load children failed');
      allChildren = j.children || [];
      refreshChildOptions();
    }).catch(()=>{ /* ignore */ });
  }

  function renderLinks(list){
    if(!list.length){
      listBox.innerHTML = '<div class="text-muted" style="font-size:.7rem;">No linked children yet.</div>';
      return;
    }
    listBox.innerHTML = list.map(r=>{
      const rel = r.relationship_type ? r.relationship_type.toUpperCase() : '—';
      const age = (r.age_months!=null ? `${r.age_months}m` : '—');
      const inactive = String(r.is_active)!=='1';
      const badge = inactive ? ' • <span class="text-danger">Inactive</span>' : '';
      const actionBtn = inactive
        ? `<button class="btn btn-sm btn-outline-danger" data-remove="${r.child_id}" style="font-size:.6rem;">
             <i class="bi bi-trash me-1"></i> Remove
           </button>`
        : `<button class="btn btn-sm btn-outline-danger" data-unlink="${r.child_id}" style="font-size:.6rem;">
             <i class="bi bi-x-lg me-1"></i> Unlink
           </button>`;
      return `
        <div class="d-flex align-items-center justify-content-between border rounded p-2">
          <div class="d-flex flex-column">
            <div><strong>${escapeHtml(r.full_name||'')}</strong> <span class="text-muted">(${age})</span></div>
            <div class="text-muted" style="font-size:.65rem;">Relationship: ${escapeHtml(rel)}${badge}</div>
          </div>
          <div class="d-flex gap-2">
            ${actionBtn}
          </div>
        </div>
      `;
    }).join('');

    // Wire unlink (for ACTIVE links)
    listBox.querySelectorAll('[data-unlink]').forEach(btn=>{
      btn.addEventListener('click',()=>{
        const cid = parseInt(btn.getAttribute('data-unlink'),10);
        if(!cid) return;
        if(!confirm('Unlink this child from the parent account?')) return;
        btn.disabled = true;
        const fd = new FormData();
        fd.append('csrf_token', window.__BHW_CSRF);
        fd.append('unlink_child', '1');
        fd.append('parent_user_id', p.user_id);
        fd.append('child_id', cid);
        fetch(api.parent,{method:'POST',body:fd})
          .then(r=>r.json())
          .then(j=>{
            if(!j.success) throw new Error(j.error||'Unlink failed');
            loadLinks(); // refresh
          })
          .catch(err=>{ alert(err.message); })
          .finally(()=>{ btn.disabled=false; });
      });
    });

    // Wire remove (for INACTIVE links)
    listBox.querySelectorAll('[data-remove]').forEach(btn=>{
      btn.addEventListener('click',()=>{
        const cid = parseInt(btn.getAttribute('data-remove'),10);
        if(!cid) return;
        if(!confirm('Remove this inactive link permanently?')) return;
        btn.disabled = true;
        const fd = new FormData();
        fd.append('csrf_token', window.__BHW_CSRF);
        fd.append('remove_child_link', '1');
        fd.append('parent_user_id', p.user_id);
        fd.append('child_id', cid);
        fetch(api.parent,{method:'POST',body:fd})
          .then(r=>r.json())
          .then(j=>{
            if(!j.success) throw new Error(j.error||'Remove failed');
            loadLinks(); // refresh list and dropdown
          })
          .catch(err=>{ alert(err.message); })
          .finally(()=>{ btn.disabled=false; });
      });
    });
  }

  function loadLinks(){
    listBox.innerHTML = '<div class="text-muted" style="font-size:.7rem;">Loading linked children...</div>';
    fetchJSON(api.parent+'?children_of_parent='+encodeURIComponent(p.user_id))
      .then(j=>{
        if(!j.success) throw new Error('Load failed');
        const rows = j.children || [];
        // Only ACTIVE links should be filtered out from the dropdown
        linkedIdSet = new Set(rows.filter(r => String(r.is_active)==='1').map(r => parseInt(r.child_id,10)));
        renderLinks(rows);
        refreshChildOptions(); // rebuild dropdown
      })
      .catch(err=>{
        listBox.innerHTML = '<div class="text-danger" style="font-size:.7rem;">Error: '+escapeHtml(err.message)+'</div>';
      });
  }

  // Link button
  linkBtn.addEventListener('click', ()=>{
    linkErr.classList.add('d-none'); linkOk.classList.add('d-none');
    const cid = parseInt(childSel.value||'0',10);
    const rel = relSel.value||'guardian';
    if(!cid){
      showLinkMsg(false,'Please select a child.');
      return;
    }
    setLinkBusy(true);
    const fd = new FormData();
    fd.append('csrf_token', window.__BHW_CSRF);
    fd.append('link_child','1');
    fd.append('parent_user_id', p.user_id);
    fd.append('child_id', cid);
    fd.append('relationship_type', rel);
    fetch(api.parent,{method:'POST',body:fd})
      .then(r=>r.json())
      .then(j=>{
        if(!j.success) throw new Error(j.error||'Link failed');
        showLinkMsg(true,'Linked!');
        childSel.value='';
        loadLinks(); // refresh
      })
      .catch(err=>showLinkMsg(false, err.message))
      .finally(()=>setLinkBusy(false));
  });

  // Manual reload
  document.getElementById('paReloadLinksBtn').addEventListener('click', ()=>{
    loadLinks();
    preloadChildrenList();
  });

  // Init
  preloadChildrenList();
  loadLinks();

  bootstrap.Modal.getOrCreateInstance(modal).show();
}


    function toggleActive(pid, btn){
      const tr = btn.closest('tr');
      const parentName = tr?.querySelector('td:first-child')?.innerText?.trim() || 'Parent';
      const statusCell = tr?.children?.[4]; // 0:Name 1:Contact 2:Children 3:Username 4:Status 5:Last Active 6:Actions
      const iconEl = btn.querySelector('i');

      const fd = new FormData();
      fd.append('toggle_active', pid);
      fd.append('csrf_token', window.__BHW_CSRF);

      btn.disabled = true;

      fetch(api.parent, { method: 'POST', body: fd })
        .then(parseJSONSafe)
        .then(j => {
          if (!j.success) throw new Error(j.error || 'Toggle failed');

          const isActive = j.is_active === 1 || j.is_active === '1' || j.is_active === true;

          // 1) Update Status badge kaagad
          if (statusCell) {
            statusCell.innerHTML = isActive
              ? '<span class="pa-status-active">Active</span>'
              : '<span class="pa-status-inactive">Inactive</span>';
          }

          // 2) I-update ang data-status ng row para sa filtering
          if (tr) tr.setAttribute('data-status', isActive ? '1' : '0');

          // 3) Palitan ang itsura ng toggle button (kulay, icon, title)
          btn.classList.toggle('btn-outline-danger', isActive);
          btn.classList.toggle('btn-outline-success', !isActive);
          if (iconEl) {
            iconEl.className = 'bi ' + (isActive ? 'bi-slash-circle' : 'bi-check-circle');
          }
          btn.title = isActive ? 'Deactivate' : 'Activate';

          // 4) Kung naka-filter sa Active/Inactive, itago agad ang row kapag hindi na pasok
          const activeFilter = moduleContent.querySelector('.pa-filter-badge.active')?.dataset.filter || 'all';
          if (activeFilter === 'active' && !isActive && tr) tr.classList.add('d-none');
          if (activeFilter === 'inactive' && isActive && tr) tr.classList.add('d-none');
          if (activeFilter === 'all' && tr) tr.classList.remove('d-none');

          // 5) Toast feedback
          showToast(`${parentName} ${isActive ? 'activated' : 'deactivated'}`, isActive ? 'success' : 'warning');
        })
        .catch(err => {
          showToast(err.message, 'danger');
        })
        .finally(() => {
          btn.disabled = false;
        });
    }
    function resetPassword(pid,btn){
      if(!confirm('Reset password for this parent account?')) return;
      const fd=new FormData();
      fd.append('reset_password',pid);
      fd.append('csrf_token',window.__BHW_CSRF);
      btn.disabled=true;
      fetch(api.parent,{method:'POST',body:fd})
        .then(r=>r.json()).then(j=>{
          if(!j.success) throw new Error(j.error||'Reset failed');
          alert('New Password: '+j.new_password);
        }).catch(err=>alert(err.message))
        .finally(()=>btn.disabled=false);
    }

    function metricCard(label,value,sub,icon){
      return `<div class="pa-metric-card">
        <div class="pa-metric-label"><i class="bi ${icon} pa-metric-icon"></i>${escapeHtml(label)}</div>
        <div class="pa-metric-value">${value}</div>
        <div class="pa-metric-sub">${escapeHtml(sub)}</div>
      </div>`;
    }

// REPLACE the buildParentRows() inside renderCreateParentAccounts() with this version:
function buildParentRows(list){
  if(!list.length) return `<tr><td colspan="7" class="text-center text-muted py-4">No parent accounts.</td></tr>`;
  return list.map(p=>{
    const status = p.is_active
      ? '<span class="pa-status-active">Active</span>'
      : '<span class="pa-status-inactive">Inactive</span>';
    const childrenBadge = p.children_count
      ? `<span class="pa-badge-child">${p.children_count} child${p.children_count>1?'ren':''}</span>`
      : `<span class="text-muted" style="font-size:.55rem;">None</span>`;
    const lastActive = (p.updated_at || p.created_at || '').replace('T',' ').slice(0,16) || '—';
    const fullName = (p.first_name+' '+p.last_name).trim();

    return `<tr data-id="${p.user_id}" data-status="${p.is_active?1:0}">
      <td><strong>${escapeHtml(fullName)}</strong></td>
      <td>${escapeHtml(p.email||'')}<br>${escapeHtml(p.contact_number||'')}</td>
      <td>${childrenBadge}</td>
      <td>${escapeHtml(p.username||'')}</td>
      <td>${status}</td>
      <td>${escapeHtml(lastActive)}</td>
      <td class="pa-actions">
        <div class="d-flex flex-wrap gap-1">
          <button class="btn btn-outline-primary" data-action="view" title="View"><i class="bi bi-eye"></i></button>
          <button class="btn btn-outline-secondary" data-action="edit" title="Edit"><i class="bi bi-pencil"></i></button>
          <button class="btn btn-outline-${p.is_active?'danger':'success'}" data-action="toggle" title="${p.is_active?'Deactivate':'Activate'}">
            <i class="bi ${p.is_active?'bi-slash-circle':'bi-check-circle'}"></i>
          </button>
        </div>
      </td>
    </tr>`;
  }).join('');
}

    function buildActivity(list){
      if(!list.length) return '';
      return list.slice(0,25).map(r=>{
        const last = r.last_notification_date ? (r.last_notification_date.replace('T',' ').slice(0,16)) : '—';
        const unread = parseInt(r.unread_notifications)||0;
        return `<li class="pa-activity-item">
          <strong>${escapeHtml(r.username)}</strong> • ${r.children_count||0} child link(s)
          <span class="pa-activity-time">
            Notifs: ${r.total_notifications||0} (Unread ${unread}) • Last: ${escapeHtml(last)}
          </span>
        </li>`;
      }).join('');
    }
  }).catch(err=>{
    moduleContent.innerHTML='<div class="alert alert-danger small">Error loading Parent Accounts: '+escapeHtml(err.message)+'</div>';
  });
}

/* ================== Health Records Module (UPDATED: Removed New Entry) ================== */
function renderHealthRecordsAll(label){
  showLoading(label);

  Promise.allSettled([
    fetchJSON(api.health+'?all=1&limit=1000'),
    fetchJSON(api.maternal+'?list=1')
  ]).then(([allRes, mothersRes])=>{
    const records = (allRes.value?.records)||[];
    const mothers = (mothersRes.value?.mothers)||[];

    const totalRecords = records.length;
    const ymNow = new Date().toISOString().slice(0,7);
    const thisMonth = records.filter(r=>(r.consultation_date||'').startsWith(ymNow)).length;
    const uniquePatients = new Set(records.map(r=>r.mother_id)).size;

    records.forEach(r=>{
      r._risk_score =
        (parseInt(r.vaginal_bleeding)||0)+(parseInt(r.urinary_infection)||0)+(parseInt(r.high_blood_pressure)||0)+
        (parseInt(r.fever_38_celsius)||0)+(parseInt(r.pallor)||0)+(parseInt(r.abnormal_abdominal_size)||0)+
        (parseInt(r.abnormal_presentation)||0)+(parseInt(r.absent_fetal_heartbeat)||0)+(parseInt(r.swelling)||0)+
        (parseInt(r.vaginal_infection)||0);
    });
    records.sort((a,b)=> (b.consultation_date||'') > (a.consultation_date||'') ? 1 : -1);

    moduleContent.innerHTML = `
      <div class="fade-in hr-wrap">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
          <div>
            <h2 class="imm-title" style="margin:0 0 .4rem;">Health Records</h2>
            <p class="imm-sub" style="margin:0;">Patient health history and documentation</p>
          </div>
        </div>

        <div class="imm-metrics hr-metrics-mini" style="margin-top:.3rem;">
          ${hrMetricCard('Total Records', totalRecords,'All health records','bi-clipboard-data')}
          ${hrMetricCard('This Month', thisMonth,'New consultations','bi-activity')}
          ${hrMetricCard('Unique Patients', uniquePatients,'Active patients','bi-people')}
        </div>

        <div class="hr-filters card-like" style="background:#ffffff;border:1px solid var(--border);border-radius:18px;padding:1rem 1.15rem;margin:1.2rem 0 1.4rem;">
          <div class="row g-3 align-items-end">
            <div class="col-md-4">
              <label class="hr-flbl">Search Records</label>
              <div class="input-group input-group-sm">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="text" id="hrSearch" class="form-control" placeholder="Search by name, date (YYYY-MM-DD)...">
              </div>
            </div>
            <div class="col-md-3">
              <label class="hr-flbl">Date From</label>
              <input type="date" id="hrDateFrom" class="form-control form-control-sm">
            </div>
            <div class="col-md-3">
              <label class="hr-flbl">Date To</label>
              <input type="date" id="hrDateTo" class="form-control form-control-sm">
            </div>
            <div class="col-md-2">
              <label class="hr-flbl">Risk Level</label>
              <select id="hrRiskFilter" class="form-select form-select-sm">
                <option value="">All</option>
                <option value="high">High (>=2)</option>
                <option value="monitor">Monitor (1)</option>
                <option value="normal">Normal (0)</option>
              </select>
            </div>
          </div>
        </div>

        <div class="nav hr-tabs mb-3" style="background:#f5f8fa;border-radius:999px;padding:.4rem .55rem;display:inline-flex;gap:.4rem;flex-wrap:wrap;">
          <button class="nav-link active" data-hrtab="consults" style="font-size:.68rem;">Consultations</button>
          <button class="nav-link" data-hrtab="history" style="font-size:.68rem;">Patient History</button>
        </div>

        <div id="hrPanel"></div>
      </div>
    `;

    injectHRStyles();

    const panel = document.getElementById('hrPanel');
    renderConsultationsTable();

    document.querySelectorAll('[data-hrtab]').forEach(btn=>{
      btn.addEventListener('click',()=>{
        document.querySelectorAll('[data-hrtab]').forEach(b=>b.classList.remove('active'));
        btn.classList.add('active');
        const tab = btn.getAttribute('data-hrtab');
        if(tab==='consults') renderConsultationsTable();
        else if(tab==='history') renderPatientHistoryView();
      });
    });

    ['hrSearch','hrDateFrom','hrDateTo','hrRiskFilter'].forEach(id=>{
      document.getElementById(id).addEventListener('input',()=> {
        if(document.querySelector('[data-hrtab="consults"].active')) renderConsultationsTable();
        if(document.querySelector('[data-hrtab="history"].active')) renderPatientHistoryView();
      });
    });

    function renderConsultationsTable(){
      const filtered = applyFilters(records);
      panel.innerHTML = `
        <div class="imm-card" style="padding:1.1rem 1.25rem;">
          <h6 class="mb-2" style="font-size:.7rem;font-weight:800;letter-spacing:.07em;text-transform:uppercase;">Consultation Records</h6>
          <div class="imm-small-muted mb-3">All general health assessment records</div>
          <div class="table-responsive" style="max-height:560px;">
            <table class="imm-table" id="hrConsultTable">
              <thead>
                <tr>
                  <th style="min-width:95px;">Date</th>
                  <th>Patient Name</th>
                  <th>Preg Weeks</th>
                  <th>BP</th>
                  <th>Risk</th>
                  <th>Flags</th>
                  <th style="min-width:80px;">Actions</th>
                </tr>
              </thead>
              <tbody>${filtered.length? filtered.map(rowHTML).join(''): `<tr><td colspan="7" class="text-center text-muted py-4">No records found.</td></tr>`}</tbody>
            </table>
          </div>
        </div>
      `;
      panel.querySelectorAll('[data-view]').forEach(btn=>{
        btn.addEventListener('click',()=>{
          const id=parseInt(btn.getAttribute('data-view'),10);
          const rec=records.find(r=>r.health_record_id==id);
            if(rec) openRecordModal(rec);
        });
      });
    }

    function rowHTML(r){
      const riskInfo = computeRiskLevel(r._risk_score);
      return `<tr>
        <td>${escapeHtml(r.consultation_date||'')}</td>
        <td>${escapeHtml(r.full_name||'')}</td>
        <td>${r.pregnancy_age_weeks!=null? r.pregnancy_age_weeks+'w':'—'}</td>
        <td>${(r.blood_pressure_systolic && r.blood_pressure_diastolic)? `${r.blood_pressure_systolic}/${r.blood_pressure_diastolic}`:'—'}</td>
        <td><span class="consult-risk-badge ${riskInfo.cls}" style="font-size:.5rem;">${riskInfo.lbl}</span></td>
        <td>${flagsIcons(r)||'<span class="text-muted" style="font-size:.5rem;">None</span>'}</td>
        <td><button class="btn btn-outline-primary btn-sm" style="font-size:.55rem;padding:.25rem .55rem;" data-view="${r.health_record_id}"><i class="bi bi-eye"></i></button></td>
      </tr>`;
    }

    function renderPatientHistoryView(){
      const opts = mothers
        .sort((a,b)=>a.full_name.localeCompare(b.full_name))
        .map(m=>`<option value="${m.mother_id}">${escapeHtml(m.full_name)}</option>`).join('');
      panel.innerHTML = `
        <div class="imm-card" style="padding:1.15rem 1.25rem;">
          <h6 class="mb-3" style="font-size:.7rem;font-weight:800;letter-spacing:.07em;text-transform:uppercase;">Patient History</h6>
          <div class="row g-3 mb-3 align-items-end">
            <div class="col-md-5">
              <label class="hr-flbl">Select Patient</label>
              <select id="hrPatientSel" class="form-select form-select-sm">
                <option value="">-- Choose Patient --</option>
                ${opts}
              </select>
            </div>
            <div class="col-md-7 text-muted" style="font-size:.6rem;font-weight:600;">
              View all consultations per patient. Filters (search & date range) still apply.
            </div>
          </div>
          <div id="hrHistoryBox" class="text-muted" style="font-size:.65rem;">No patient selected.</div>
        </div>
      `;
      document.getElementById('hrPatientSel').addEventListener('change',()=>{
        const mid = parseInt(document.getElementById('hrPatientSel').value||0,10);
        renderHistory(mid);
      });
    }

    // Replace only the renderHistory(mother_id) function inside renderHealthRecordsAll with this version
function renderHistory(mother_id){
  const box = document.getElementById('hrHistoryBox');
  if(!mother_id){ box.innerHTML='No patient selected.'; return; }

  // keep existing global filters
  const pats = applyFilters(records).filter(r=>r.mother_id==mother_id)
    .sort((a,b)=> (b.consultation_date||'') > (a.consultation_date||'') ? 1 : -1);

  if(!pats.length){
    box.innerHTML='<div class="text-muted" style="font-size:.65rem;">No records for selected patient (with current filters).</div>';
    return;
  }

  // helpers
  function riskInfo(score){
    if(score>=2) return {cls:'consult-risk-high',lbl:'High'};
    if(score===1) return {cls:'consult-risk-monitor',lbl:'Monitor'};
    return {cls:'consult-risk-normal',lbl:'Normal'};
  }
  function hgbBadge(val){
    if(val==null || val==='') return '—';
    const num = parseFloat(String(val).replace(/[^\d.]/g,'')); // tolerant parse
    if(Number.isNaN(num)) return `<span class="imm-badge imm-badge-ok">${escapeHtml(val)}</span>`;
    let cls = 'imm-badge imm-badge-ok', txt = num.toFixed(1);
    if(num < 10) cls = 'imm-badge imm-badge-overdue';      // red
    else if(num < 11) cls = 'imm-badge imm-badge-duesoon'; // yellow
    return `<span class="${cls}" title="HGB">${txt}</span>`;
  }
  function bpCell(r){
    const sys = r.blood_pressure_systolic, dia = r.blood_pressure_diastolic;
    if(!(sys && dia)) return '—';
    const high = (parseInt(sys)>=140 || parseInt(dia)>=90);
    const style = high ? 'color:#b22218;font-weight:700;' : '';
    return `<span style="${style}">${sys}/${dia}</span>`;
  }

  // Build rows with weight delta vs previous record (list is already DESC)
  const rows = pats.map((r,i)=>{
    const prev = pats[i+1]; // previous visit in time (because list is desc)
    const w = (r.weight_kg!=null ? parseFloat(r.weight_kg) : null);
    let wCell = '—';
    if(w!=null){
      let deltaHtml = '';
      if(prev && prev.weight_kg!=null){
        const d = +(w - parseFloat(prev.weight_kg)).toFixed(1);
        if(d!==0){
          const pos = d>0;
          deltaHtml = `<br><small style="font-weight:700;${pos?'color:#0d7c4e;':'color:#b22218;'}">${pos?'+':''}${d}</small>`;
        } else {
          deltaHtml = `<br><small class="text-muted" style="font-weight:700;">0.0</small>`;
        }
      }
      wCell = `${w}${deltaHtml}`;
    }
    const rk = riskInfo(r._risk_score||0);
    const titleGA = [
      r.last_menstruation_date?('LMP: '+r.last_menstruation_date):'',
      r.expected_delivery_date?('EDD: '+r.expected_delivery_date):''
    ].filter(Boolean).join(' • ');
    return `<tr>
      <td>${escapeHtml(r.consultation_date||'')}</td>
      <td title="${escapeHtml(titleGA)}">${r.pregnancy_age_weeks!=null? r.pregnancy_age_weeks+'w':'—'}</td>
      <td>${bpCell(r)}</td>
      <td>${wCell}</td>
      <td>${hgbBadge(r.hgb_result)}</td>
      <td><span class="consult-risk-badge ${rk.cls}" style="font-size:.5rem;">${rk.lbl}</span></td>
      <td>${flagsIcons(r)||''}</td>
      <td><button class="btn btn-outline-primary btn-sm" style="font-size:.5rem;padding:.25rem .5rem;" data-view="${r.health_record_id}"><i class="bi bi-eye"></i></button></td>
    </tr>`;
  }).join('');

  box.innerHTML = `
    <div class="table-responsive" style="max-height:560px;">
      <table class="imm-table">
        <thead>
          <tr>
            <th>Date</th>
            <th>GA</th>
            <th>BP</th>
            <th>Wt (Δ)</th>
            <th>HGB</th>
            <th>Risk</th>
            <th>Flags</th>
            <th>View</th>
          </tr>
        </thead>
        <tbody>${rows}</tbody>
      </table>
    </div>`;

  // wire up view buttons
  box.querySelectorAll('[data-view]').forEach(btn=>{
    btn.addEventListener('click',()=>{
      const rec=records.find(r=>r.health_record_id==btn.getAttribute('data-view'));
      if(rec) openRecordModal(rec);
    });
  });
}

    function applyFilters(list){
      const q=(document.getElementById('hrSearch').value||'').toLowerCase();
      const df=document.getElementById('hrDateFrom').value;
      const dt=document.getElementById('hrDateTo').value;
      const rf=document.getElementById('hrRiskFilter').value;
      return list.filter(r=>{
        if(q){
          const txt=(r.full_name||'')+' '+(r.consultation_date||'');
          if(!txt.toLowerCase().includes(q)) return false;
        }
        if(df && (r.consultation_date||'') < df) return false;
        if(dt && (r.consultation_date||'') > dt) return false;
        if(rf){
          const lvl = riskLevel(r._risk_score);
          if(rf==='high' && lvl!=='high') return false;
          if(rf==='monitor' && lvl!=='monitor') return false;
          if(rf==='normal' && lvl!=='normal') return false;
        }
        return true;
      });
    }

    function flagsIcons(r){
      const map = {
        vaginal_bleeding:'VB', urinary_infection:'UTI', high_blood_pressure:'BP', fever_38_celsius:'FEV',
        pallor:'PAL', abnormal_abdominal_size:'ABD', abnormal_presentation:'PRES', absent_fetal_heartbeat:'FHT',
        swelling:'SWL', vaginal_infection:'VAG'
      };
      const outs=[];
      Object.keys(map).forEach(k=>{
        if(parseInt(r[k])===1){
          outs.push(`<span style="display:inline-block;background:#e7efe9;color:#134a3d;font-size:.48rem;font-weight:700;padding:3px 6px;border-radius:8px;margin:1px;">${map[k]}</span>`);
        }
      });
      return outs.join('');
    }
    function riskLevel(score){
      if(score>=2) return 'high';
      if(score===1) return 'monitor';
      return 'normal';
    }
    function computeRiskLevel(score){
      const lvl=riskLevel(score);
      if(lvl==='high') return {cls:'consult-risk-high',lbl:'High'};
      if(lvl==='monitor') return {cls:'consult-risk-monitor',lbl:'Monitor'};
      return {cls:'consult-risk-normal',lbl:'Normal'};
    }

    function openRecordModal(r){
      let modal=document.getElementById('hrViewModal');
      if(!modal){
        document.body.insertAdjacentHTML('beforeend',`
          <div class="modal fade" id="hrViewModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
              <div class="modal-content" style="border-radius:18px;">
                <div class="modal-header">
                  <h5 class="modal-title" style="font-size:.9rem;font-weight:700;">Consultation Detail</h5>
                  <button class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="hrViewBody" style="font-size:.7rem;"></div>
                <div class="modal-footer">
                  <button class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                </div>
              </div>
            </div>
          </div>`);
        modal=document.getElementById('hrViewModal');
      }
      const riskInfo=computeRiskLevel(r._risk_score);
      document.getElementById('hrViewBody').innerHTML=`
        <div class="mb-2"><strong>Patient:</strong> ${escapeHtml(r.full_name||'')}</div>
        <div class="row g-2">
          <div class="col-6"><strong>Date:</strong> ${escapeHtml(r.consultation_date||'')}</div>
          <div class="col-6"><strong>GA:</strong> ${r.pregnancy_age_weeks!=null? r.pregnancy_age_weeks+' wks':'—'}</div>
          <div class="col-6"><strong>BP:</strong> ${(r.blood_pressure_systolic&&r.blood_pressure_diastolic)? `${r.blood_pressure_systolic}/${r.blood_pressure_diastolic}`:'—'}</div>
          <div class="col-6"><strong>Weight:</strong> ${r.weight_kg!=null? r.weight_kg+' kg':'—'}</div>
          <div class="col-6"><strong>Height:</strong> ${r.height_cm!=null? r.height_cm+' cm':'—'}</div>
          <div class="col-6"><strong>Risk:</strong> <span class="consult-risk-badge ${riskInfo.cls}" style="font-size:.55rem;">${riskInfo.lbl}</span></div>
        </div>
        <div class="mt-3"><strong>Flags:</strong><br>${flagsIcons(r)||'<span class="text-muted">None</span>'}</div>
      `;
      bootstrap.Modal.getOrCreateInstance(modal).show();
    }

    function hrMetricCard(label,value,sub,icon){
      return `<div class="imm-metric">
        <div class="imm-metric-label"><i class="bi ${icon}"></i>${escapeHtml(label)}</div>
        <div class="imm-metric-value">${escapeHtml(value)}</div>
        <div class="imm-metric-sub">${escapeHtml(sub)}</div>
      </div>`;
    }

    function injectHRStyles(){
      if(document.getElementById('hrStyles')) return;
      const css = `
        .hr-flbl{font-size:.55rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;margin-bottom:.3rem;color:#2d4d53;}
        .hr-wrap .imm-metric-value{font-size:1.7rem;}
        .hr-tabs .nav-link{border-radius:30px;font-size:.68rem;font-weight:600;padding:.5rem .95rem;color:#355155;}
        .hr-tabs .nav-link.active{background:#ffffff;border:1px solid var(--border);box-shadow:0 2px 4px rgba(0,0,0,.06);color:#0a5c3d;font-weight:700;}
        .hr-tabs .nav-link:hover{background:#e9f4ef;color:#0a5c3d;}
        .hr-metrics-mini .imm-metric-value{font-size:1.6rem;}
        @media (max-width:700px){
          .hr-wrap .imm-metric-value{font-size:1.35rem;}
        }
      `;
      const style=document.createElement('style');
      style.id='hrStyles';
      style.textContent=css;
      document.head.appendChild(style);
    }

  }).catch(err=>{
    moduleContent.innerHTML = `<div class="alert alert-danger small">Error loading Health Records: ${escapeHtml(err.message)}</div>`;
  });
}

/* ================== Event Scheduling (UPDATED: add Target Participants, no attendees list) ================== */

function renderEventScheduling(label){
  showLoading(label);

  // inject styles (adds form layout + uses existing event styles)
  (function injectEvtCSS(){
    if(document.getElementById('evtStyles')) return;
    const css=`
      .evt-wrap .metric-grid{gap:1rem;}
      .evt-badge{display:inline-block;font-size:.56rem;font-weight:800;padding:.28rem .6rem;border-radius:999px;letter-spacing:.04em;}
      .evt-badge.vaccination{background:#ddf5ea;color:#0b6f46;}
      .evt-badge.health{background:#e1edff;color:#134f9c;}
      .evt-badge.nutrition{background:#fff2cc;color:#8b6400;}
      .evt-badge.feeding{background:#f6eafe;color:#6f2dbd;}
      .evt-badge.weighing{background:#e7f7fb;color:#0b6b88;}
      .evt-badge.general,.evt-badge.other{background:#eef1f4;color:#44525a;}
      .evt-card{border:1px solid var(--border);background:#fff;border-radius:16px;padding:1rem 1.1rem;display:flex;flex-direction:column;gap:.35rem;}
      .evt-title{font-weight:700;color:#123b34;margin:0;font-size:.9rem;}
      .evt-meta{font-size:.68rem;font-weight:600;color:#5a6b71;}
      .evt-actions .btn{font-size:.62rem;font-weight:700;border-radius:14px;padding:.42rem .75rem;}
      .evt-cal{border:1px solid var(--border);background:#fff;border-radius:16px;padding:.9rem;}
      .evt-cal header{display:flex;justify-content:space-between;align-items:center;margin-bottom:.45rem;}
      .evt-cal header .ttl{font-weight:800;color:#123b34;}
      .evt-cal table{width:100%;border-collapse:collapse;table-layout:fixed;}
      .evt-cal th{font-size:.6rem;color:#6a7a81;padding:.35rem 0;font-weight:800;text-transform:uppercase;}
      .evt-cal td{height:38px;padding:.15rem;vertical-align:top;}
      .evt-day{display:flex;align-items:flex-start;justify-content:flex-start;gap:.3rem;border:1px solid transparent;border-radius:10px;cursor:pointer;padding:.3rem .35rem;height:100%;}
      .evt-day:hover{background:#f3faf6;border-color:#d8e7df;}
      .evt-day.is-today{box-shadow:inset 0 0 0 2px #0d7c4e33;}
      .evt-day .num{font-size:.7rem;font-weight:800;color:#1d3436;line-height:1;}
      .evt-dot{height:6px;width:6px;border-radius:50%;background:#0d7c4e;margin-top:.25rem;}
      .evt-day.disabled .num{color:#a7b2b7;}
      .evt-empty{padding:1rem .5rem;text-align:center;font-size:.68rem;color:#6c7a83;border:1px dashed #cfd8dd;border-radius:12px;background:#f8fbfd;}
      .evt-small-muted{font-size:.62rem;font-weight:600;color:#6a7a81;}
      .evt-btn-add{display:inline-flex;align-items:center;gap:.45rem;font-weight:700;border-radius:.9rem;background:#047a4c;border:1px solid #047242;color:#fff;padding:.55rem .95rem;font-size:.74rem;}
      .evt-btn-add:hover{background:#059a61;border-color:#059a61;color:#fff;}
      .evt-form-card{border:1px solid var(--border);background:#fff;border-radius:18px;padding:1.25rem 1.35rem;box-shadow:var(--shadow-sm);}
      .evt-form-title{font-size:.82rem;font-weight:800;letter-spacing:.06em;color:#153036;text-transform:uppercase;margin:0 0 .25rem;}
      .evt-form-sub{font-size:.7rem;color:#65767d;margin-bottom:1rem;font-weight:600;}
      .evt-form-grid{display:grid;grid-template-columns:1fr 1fr;gap:1rem 2.4rem;}
      @media (max-width:900px){ .evt-form-grid{grid-template-columns:1fr;} }
      .evt-form-card label{font-size:.65rem;font-weight:700;letter-spacing:.05em;color:#355156;text-transform:uppercase;margin-bottom:.28rem;}
      .evt-form-card .form-control,.evt-form-card .form-select{font-size:.82rem;border-radius:.7rem;padding:.6rem .75rem;}
      .evt-form-actions{display:flex;gap:.6rem;justify-content:flex-end;border-top:1px solid var(--border);margin-top:1rem;padding-top:1rem;}
      .evt-success{font-size:.7rem;font-weight:700;color:#0d7c4e;display:none;}
      .evt-error{font-size:.7rem;font-weight:700;color:#b22218;display:none;}
      .evt-tabs .nav-link{border-radius:30px;font-size:.7rem;font-weight:600;padding:.5rem .95rem;color:#355155;}
      .evt-tabs .nav-link.active{background:#ffffff;border:1px solid var(--border);box-shadow:0 2px 4px rgba(0,0,0,.06);color:#0a5c3d;font-weight:700;}
      .evt-badge-completed{background:#e1edff;color:#134f9c;font-size:.55rem;font-weight:800;padding:.25rem .5rem;border-radius:999px;letter-spacing:.04em;margin-right:.35rem;}
    `;
    const style=document.createElement('style');
    style.id='evtStyles'; style.textContent=css; document.head.appendChild(style);
  })();

  // Safe local date helpers (avoid UTC shift)
  function pad2(n){ return String(n).padStart(2,'0'); }
  function ymdLocal(d){ return `${d.getFullYear()}-${pad2(d.getMonth()+1)}-${pad2(d.getDate())}`; }
  const fmtMonthKey = (d) => `${d.getFullYear()}-${pad2(d.getMonth()+1)}`;
  const fmtDate      = (d) => ymdLocal(d);

  // state
  const today = new Date(); today.setHours(0,0,0,0);
  let viewMonth = new Date(today); viewMonth.setDate(1);
  let selectedDate = new Date(today);

  // cache month events
  const monthCache = new Map(); // key 'YYYY-MM' -> array of events

  // helpers
  function parseISO(s){ const dt=new Date(s+'T00:00:00'); return isNaN(dt)? null: dt; }
  function timeLabel(t){
    if(!t) return 'All day';
    if(/\d{2}:\d{2}(:\d{2})?$/.test(t)){
      const [hh,mm]=t.split(':'); let h=+hh, m=+mm; const ampm=h>=12?'PM':'AM'; h=h%12||12;
      return `${h}:${String(m).padStart(2,'0')} ${ampm}`;
    }
    return t;
  }
  function badge(type){
    const map = {vaccination:'vaccination',health:'health',nutrition:'nutrition',feeding:'feeding',weighing:'weighing',general:'general',other:'other'};
    const cls = map[type] || 'general';
    const lbl = type==='vaccination'?'Vaccination'
              : type==='health'?'Maternal Health'
              : type==='nutrition'?'Nutrition'
              : type==='feeding'?'Feeding'
              : type==='weighing'?'Weighing'
              : type==='general'?'General':'Other';
    return `<span class="evt-badge ${cls}">${lbl}</span>`;
  }
  function isCompleted(ev){
    if(!ev) return false;
    const st = (ev.status||'').toString().toLowerCase();
    return ev.is_completed==1 || st==='completed' || (ev.completed_at && ev.completed_at!=='0000-00-00 00:00:00') ||
           (ev.event_description && /^\[COMPLETED\]/.test(ev.event_description));
  }

  // fetch events for month
  function loadMonth(monthKey){
    if(monthCache.has(monthKey)) return Promise.resolve(monthCache.get(monthKey));
    return fetchJSON(api.events+'?month='+monthKey).then(j=>{
      if(!j.success) throw new Error('Load failed');
      monthCache.set(monthKey, j.events||[]);
      return monthCache.get(monthKey);
    });
  }

  // load current and next month (for metrics)
  Promise.allSettled([
    loadMonth(fmtMonthKey(viewMonth)),
    (function(){const n=new Date(viewMonth); n.setMonth(n.getMonth()+1); return loadMonth(fmtMonthKey(n));})()
  ]).then(()=>{

    moduleContent.innerHTML = `
      <div class="evt-wrap fade-in">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
          <div>
            <h2 class="imm-title" style="margin:0 0 .3rem;">Health Event Scheduling</h2>
            <div class="imm-sub">Create a new health campaign, visit, or education session</div>
          </div>
          <div>
            <button class="evt-btn-add" id="evtAddBtn" aria-expanded="false"><i class="bi bi-plus-lg"></i> Schedule New Event</button>
          </div>
        </div>

        <!-- Inline form (same as before) -->
        <div class="evt-form-card mb-3" id="evtFormCard" style="display:none;">
          <div class="evt-form-title">Schedule Health Event</div>
          <div class="evt-form-sub">Create a new health campaign, visit, or education session</div>
          <form id="evtForm" autocomplete="off">
            <div class="evt-form-grid">
              <div>
                <div class="mb-3">
                  <label>Event Type *</label>
                  <select name="event_type" class="form-select" required>
                    <option value="">Select event type</option>
                    <option value="vaccination">Vaccination Campaign</option>
                    <option value="health">Maternal Health Visit</option>
                    <option value="general">Health Education Session</option>
                    <option value="general">General Consultation Day</option>
                  </select>
                </div>
                <div class="mb-3">
                  <label>Event Date *</label>
                  <input type="date" name="event_date" class="form-control" required value="${fmtDate(selectedDate)}" placeholder="mm/dd/yyyy">
                </div>
                <div class="mb-3">
                  <label>Location</label>
                  <input name="location" class="form-control" placeholder="e.g., Barangay Health Center">
                </div>
                <div class="mb-1">
                  <label>Description</label>
                  <textarea name="event_description" rows="2" class="form-control" placeholder="Event details, requirements, and instructions"></textarea>
                </div>
              </div>
              <div>
                <div class="mb-3">
                  <label>Event Title *</label>
                  <input name="event_title" class="form-control" required placeholder="e.g., Measles Vaccination Campaign">
                </div>
                <div class="mb-3">
                  <label>Time</label>
                  <input type="time" name="event_time" class="form-control" step="60" placeholder="--:-- --">
                </div>
              </div>
            </div>
            <input type="hidden" name="csrf_token" value="${window.__BHW_CSRF}">
            <input type="hidden" name="create_event" value="1">
            <div class="evt-form-actions">
              <div class="me-auto">
                <span class="evt-success" id="evtOk"><i class="bi bi-check-circle me-1"></i>Saved!</span>
                <span class="evt-error" id="evtErr"></span>
              </div>
              <button type="button" class="btn btn-outline-secondary btn-sm" id="evtCancelBtn">Cancel</button>
              <button class="btn btn-success btn-sm" id="evtSaveBtn"><i class="bi bi-check2-circle me-1"></i>Schedule Event</button>
            </div>
          </form>
        </div>

        <!-- Metrics row -->
        <div class="metric-grid mb-3" id="evtMetrics"></div>

        <!-- Tabs -->
        <div class="evt-tabs nav mb-2">
          <button class="nav-link active" data-tab="calendar">Calendar View</button>
          <button class="nav-link" data-tab="upcoming">Upcoming Events</button>
          <button class="nav-link" data-tab="vaccination">Vaccination Campaigns</button>
          <button class="nav-link" data-tab="maternal">Maternal Visits</button>
        </div>
        <div id="evtPanel"></div>
      </div>
    `;

    updateMetrics();
    renderCalendarTab();

    // tabs wiring
    moduleContent.querySelectorAll('.evt-tabs .nav-link').forEach(b=>{
      b.addEventListener('click',()=>{
        moduleContent.querySelectorAll('.evt-tabs .nav-link').forEach(x=>x.classList.remove('active'));
        b.classList.add('active');
        const t=b.dataset.tab;
        if(t==='calendar') renderCalendarTab();
        if(t==='upcoming') renderUpcomingTab();
        if(t==='maternal') renderFilteredTab('health','Maternal Visits');
        if(t==='vaccination') renderFilteredTab('vaccination','Vaccination Campaigns');
      });
    });

    // Toggle form
    const formCard = document.getElementById('evtFormCard');
    const addBtn = document.getElementById('evtAddBtn');
    addBtn.addEventListener('click',()=>{
      const hidden = formCard.style.display==='none';
      formCard.style.display = hidden ? 'block' : 'none';
      addBtn.setAttribute('aria-expanded', hidden?'true':'false');
      if(hidden){
        const dateEl = document.querySelector('#evtForm [name=event_date]');
        if(dateEl) dateEl.value = fmtDate(selectedDate);
        formCard.scrollIntoView({behavior:'smooth',block:'start'});
        setTimeout(()=>document.querySelector('#evtForm [name=event_title]')?.focus(), 220);
      }
    });

    // Form submit
    const form = document.getElementById('evtForm');
    const err  = document.getElementById('evtErr');
    const ok   = document.getElementById('evtOk');

    document.getElementById('evtCancelBtn').addEventListener('click',()=>{
      form.reset(); err.style.display='none'; ok.style.display='none';
      formCard.style.display='none';
      addBtn.setAttribute('aria-expanded','false');
    });

    form.addEventListener('submit',e=>{
      e.preventDefault();
      err.style.display='none'; ok.style.display='none';
      const fd=new FormData(form);
      fetch(api.events,{method:'POST',body:fd})
        .then(parseJSONSafe)
        .then(j=>{
          if(!j.success) throw new Error(j.error||'Save failed');
          ok.style.display='inline';
          const evDate = fd.get('event_date'); const key = evDate? String(evDate).slice(0,7): fmtMonthKey(viewMonth);
          monthCache.delete(key);
          loadMonth(key).then(()=>{ updateMetrics(); rerenderActiveTab(); });
          const keepType = form.event_type.value;
          const keepDate = form.event_date.value;
          form.reset();
          form.event_type.value = keepType;
          form.event_date.value = keepDate || fmtDate(new Date());
          formCard.style.display='none';
          addBtn.setAttribute('aria-expanded','false');
        })
        .catch(ex=>{ err.textContent=ex.message; err.style.display='inline'; });
    });

  }).catch(err=>{
    moduleContent.innerHTML = `<div class="alert alert-danger small">Error loading events: ${escapeHtml(err.message)}</div>`;
  });

  // utilities
  function gatherAllLoadedEvents(){
    const keys=[fmtMonthKey(viewMonth), (d=>{const n=new Date(viewMonth); n.setMonth(n.getMonth()+1); return fmtMonthKey(n);})()];
    const list=[]; keys.forEach(k=>{ (monthCache.get(k)||[]).forEach(e=>list.push(e)); });
    return list;
  }
  function updateMetrics(){
    const list = gatherAllLoadedEvents();
    const start30 = new Date(today); const end30 = new Date(today); end30.setDate(end30.getDate()+30);
    const thisWeekStart = (function(){ const d=new Date(today); const day = d.getDay(); const diff = (day+6)%7; d.setDate(d.getDate()-diff); return d; })(); // Monday
    const thisWeekEnd = new Date(thisWeekStart); thisWeekEnd.setDate(thisWeekStart.getDate()+6);
    const thisMonthKey = today.toISOString().slice(0,7);

    let next30=0, weekCnt=0, completedThisMonth=0;
    list.forEach(ev=>{
      const dt=parseISO(ev.event_date); if(!dt) return;
      if(dt>=start30 && dt<=end30) next30++;
      if(dt>=thisWeekStart && dt<=thisWeekEnd) weekCnt++;
      const k=ev.event_date.slice(0,7);
      if(k===thisMonthKey && isCompleted(ev)) completedThisMonth++;
    });
    const attendance='—';

    const box=document.getElementById('evtMetrics');
    if(!box) return;
    box.innerHTML = [
      metricCard('Upcoming Events', next30, 'Next 30 days','bi-calendar3'),
      metricCard('This Week', weekCnt, 'Scheduled events','bi-pencil'),
      metricCard('Total Attendance', attendance, 'This month','bi-people'),
      metricCard('Completed', completedThisMonth, 'This month','bi-check2-circle')
    ].join('');
  }
  function metricCard(title,value,sub,icon){
    return `<div class="metric-card">
      <div>
        <div class="metric-title"><i class="bi ${icon}"></i>${escapeHtml(title)}</div>
        <div class="metric-value">${escapeHtml(String(value))}</div>
        <div class="metric-diff" style="color:#607078;font-weight:700;">${escapeHtml(sub)}</div>
      </div>
    </div>`;
  }

  function rerenderActiveTab(){
    const active = moduleContent.querySelector('.evt-tabs .nav-link.active')?.dataset.tab || 'calendar';
    if(active==='calendar') renderCalendarTab();
    else if(active==='upcoming') renderUpcomingTab();
    else if(active==='maternal') renderFilteredTab('health','Maternal Visits');
    else if(active==='vaccination') renderFilteredTab('vaccination','Vaccination Campaigns');
  }

  // Views
  function renderCalendarTab(){
    const panel=document.getElementById('evtPanel');
    panel.innerHTML = `
      <div class="row g-3">
        <div class="col-lg-5">
          <div class="evt-cal">
            <header>
              <button class="btn btn-sm btn-outline-secondary" id="calPrev"><i class="bi bi-chevron-left"></i></button>
              <div class="ttl">${viewMonth.toLocaleString('en-PH',{timeZone:'Asia/Manila',month:'long',year:'numeric'})}</div>
              <button class="btn btn-sm btn-outline-secondary" id="calNext"><i class="bi bi-chevron-right"></i></button>
            </header>
            <div class="evt-small-muted mb-2">Select a date to view events</div>
            <div id="evtCalGrid" class="mb-2"></div>
          </div>
        </div>
        <div class="col-lg-7">
          <div class="evt-card">
            <div class="d-flex justify-content-between align-items-center">
              <div class="evt-title">Events on ${selectedDate.toLocaleDateString('en-PH',{timeZone:'Asia/Manila',day:'2-digit',month:'2-digit',year:'numeric'})}</div>
            </div>
            <div class="evt-small-muted mb-2">Scheduled health activities for this date</div>
            <div id="evtDayList"></div>
          </div>
        </div>
      </div>
    `;
    buildCalendarGrid();
    document.getElementById('calPrev').addEventListener('click',()=>{
      viewMonth.setMonth(viewMonth.getMonth()-1);
      const key=fmtMonthKey(viewMonth);
      loadMonth(key).then(()=>{ buildCalendarGrid(); updateMetrics(); });
    });
    document.getElementById('calNext').addEventListener('click',()=>{
      viewMonth.setMonth(viewMonth.getMonth()+1);
      const key=fmtMonthKey(viewMonth);
      loadMonth(key).then(()=>{ buildCalendarGrid(); updateMetrics(); });
    });
    renderDayList(selectedDate);
  }

  function buildCalendarGrid(){
    const key=fmtMonthKey(viewMonth);
    const events = monthCache.get(key)||[];

    const map=new Map();
    events.forEach(e=>{
      const d=e.event_date; map.set(d,(map.get(d)||0)+1);
    });

    const year=viewMonth.getFullYear(), month=viewMonth.getMonth();
    const first=new Date(year,month,1);
    const startIdx=(first.getDay()+6)%7; // Monday first
    const daysInMonth=new Date(year,month+1,0).getDate();
    const totalCells = Math.ceil((startIdx+daysInMonth)/7)*7;

    const head=['Mo','Tu','We','Th','Fr','Sa','Su'].map(d=>`<th>${d}</th>`).join('');
    let cells='';
    for(let i=0;i<totalCells;i++){
      const dayNum = i - startIdx + 1;
      const dateObj = new Date(year, month, dayNum);
      const inMonth = dayNum>=1 && dayNum<=daysInMonth;
      let cls='evt-day';
      if(!inMonth) cls+=' disabled';
      const isToday = inMonth && fmtDate(dateObj)===fmtDate(today);
      if(isToday) cls+=' is-today';
      const dateStr = fmtDate(dateObj);
      const has = (map.get(dateStr)||0)>0;
      const dot = has? '<span class="evt-dot"></span>':'';
      const num = inMonth? dayNum : (dayNum<1? (new Date(year,month,0).getDate()+dayNum) : (dayNum-daysInMonth));
      cells += `<td><div class="${cls}" data-date="${dateStr}"><span class="num">${num}</span>${dot}</div></td>`;
    }
    const tds = cells.match(/<td>[\s\S]*?<\/td>/g) || [];
    const rowHtml=[];
    for(let r=0;r<tds.length;r+=7){ rowHtml.push('<tr>'+tds.slice(r,r+7).join('')+'</tr>'); }

    document.getElementById('evtCalGrid').innerHTML = `
      <table>
        <thead><tr>${head}</tr></thead>
        <tbody>${rowHtml.join('')}</tbody>
      </table>
    `;
    document.querySelectorAll('.evt-day').forEach(d=>{
      if(d.classList.contains('disabled')) return;
      d.addEventListener('click',()=>{
        selectedDate = parseISO(d.dataset.date);
        document.querySelectorAll('.evt-day').forEach(x=>x.classList.remove('is-selected'));
        d.classList.add('is-selected');
        renderDayList(selectedDate);
        const dateEl = document.querySelector('#evtForm [name=event_date]');
        if(dateEl){ dateEl.value = d.dataset.date; }
      });
    });
  }

  function renderDayList(dateObj){
    const key=fmtMonthKey(dateObj);
    const all = monthCache.get(key)||[];
    const target = fmtDate(dateObj);
    const list = all.filter(e=>e.event_date===target).sort((a,b)=> (a.event_time||'') < (b.event_time||'') ? -1 : 1);
    const box=document.getElementById('evtDayList');
    if(!box) return;
    if(!list.length){ box.innerHTML = `<div class="evt-empty">No events on this date.</div>`; return; }
    box.innerHTML = list.map(cardHTML).join('');
    wireCardActions(box);
  }

  function renderUpcomingTab(){
    const panel=document.getElementById('evtPanel');
    const all=gatherAllLoadedEvents();
    const start=today; const end=new Date(today); end.setDate(end.getDate()+30);
    const list = all.filter(e=>{ const dt=parseISO(e.event_date); return dt && dt>=start && dt<=end; })
      .sort((a,b)=> (a.event_date||'') < (b.event_date||'') ? -1 : (a.event_time||'') < (b.event_time||'') ? -1 : 1);
    panel.innerHTML = `
      <div class="imm-card">
        <h6 style="font-size:.72rem;font-weight:800;letter-spacing:.07em;text-transform:uppercase;">Upcoming Events (Next 30 Days)</h6>
        <div class="imm-small-muted mb-2">Scheduled health activities</div>
        <div id="evtUpList">${list.length? list.map(cardHTML).join('') : `<div class="evt-empty">No upcoming events.</div>`}</div>
      </div>
    `;
    wireCardActions(panel);
  }

  function renderFilteredTab(type,labelText){
    const panel=document.getElementById('evtPanel');
    const all=gatherAllLoadedEvents();
    const list = all.filter(e=> (e.event_type||'')===type)
      .sort((a,b)=> (a.event_date||'') < (b.event_date||'') ? -1 : (a.event_time||'') < (b.event_time||'') ? -1 : 1);
    panel.innerHTML = `
      <div class="imm-card">
        <h6 style="font-size:.72rem;font-weight:800;letter-spacing:.07em;text-transform:uppercase;">${escapeHtml(labelText)}</h6>
        <div class="imm-small-muted mb-2">All scheduled ${escapeHtml(labelText.toLowerCase())}</div>
        <div id="evtFList">${list.length? list.map(cardHTML).join('') : `<div class="evt-empty">No events.</div>`}</div>
      </div>
    `;
    wireCardActions(panel);
  }

  function cardHTML(e){
    const t=timeLabel(e.event_time);
    const dateLbl = (parseISO(e.event_date)||new Date()).toLocaleDateString('en-PH',{timeZone:'Asia/Manila',month:'short',day:'numeric',year:'numeric'});
    const completed = isCompleted(e);
    const compBadge = completed ? `<span class="evt-badge-completed">Completed</span>` : '';
    return `
      <div class="evt-card mb-2" data-id="${e.event_id}" data-date="${escapeHtml(e.event_date||'')}" data-time="${escapeHtml(e.event_time||'')}">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <div class="d-flex align-items-center gap-2">
              <i class="bi bi-calendar2-event" style="color:#0a7b50;"></i>
              <p class="evt-title mb-0">${escapeHtml(e.event_title||'Untitled')}</p>
            </div>
            <div class="evt-meta">${escapeHtml(t)} • ${escapeHtml(e.location||'TBD')}</div>
            <div class="evt-small-muted">Date: ${escapeHtml(dateLbl)}</div>
          </div>
          <div>${compBadge}${badge(e.event_type||'general')}</div>
        </div>
        <div class="evt-actions mt-2 d-flex gap-2 flex-wrap">
          <button class="btn btn-outline-primary btn-sm" data-act="view"><i class="bi bi-eye me-1"></i>View Details</button>
          <button class="btn btn-outline-secondary btn-sm" data-act="resched"><i class="bi bi-calendar-event me-1"></i>Reschedule</button>
          ${completed ? '' : `<button class="btn btn-outline-success btn-sm" data-act="complete"><i class="bi bi-check2-circle me-1"></i>Mark as Complete</button>`}
        </div>
      </div>
    `;
  }

  function wireCardActions(scope){
    scope.querySelectorAll('[data-act="view"]').forEach(b=>{
      b.addEventListener('click',()=>{
        const card=b.closest('.evt-card'); const id=card?.dataset.id;
        const all=[...gatherAllLoadedEvents()];
        const e=all.find(x=>String(x.event_id)===String(id));
        openEventView(e);
      });
    });
    // Reschedule
    scope.querySelectorAll('[data-act="resched"]').forEach(b=>{
      b.addEventListener('click',()=>{
        const card=b.closest('.evt-card');
        const id=card?.dataset.id;
        const curDate=card?.dataset.date || '';
        const curTime=card?.dataset.time || '';
        openRescheduleModal(id, curDate, curTime);
      });
    });
    // Complete
    scope.querySelectorAll('[data-act="complete"]').forEach(b=>{
      b.addEventListener('click',()=>{
        const card=b.closest('.evt-card'); const id=card?.dataset.id;
        if(!id) return;
        const fd=new FormData();
        fd.append('csrf_token', window.__BHW_CSRF);
        fd.append('complete_event', id);
        fetch(api.events,{method:'POST',body:fd})
          .then(parseJSONSafe)
          .then(j=>{
            if(!j.success) throw new Error(j.error||'Failed to mark complete');
            showToast('Event marked as completed','success');
            // refresh month cache for this event’s month
            const date = card?.dataset.date || '';
            const mk = date? date.slice(0,7) : fmtMonthKey(viewMonth);
            monthCache.delete(mk);
            loadMonth(mk).then(()=>{ updateMetrics(); rerenderActiveTab(); });
          })
          .catch(err=> showToast(err.message,'danger'));
      });
    });
  }

  function openEventView(e){
    const modalId='evtViewModal';
    let modal=document.getElementById(modalId);
    if(!modal){
      document.body.insertAdjacentHTML('beforeend',`
        <div class="modal fade" id="${modalId}" tabindex="-1">
          <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius:18px;">
              <div class="modal-header">
                <h5 class="modal-title" style="font-size:.92rem;font-weight:800;">Event Details</h5>
                <button class="btn-close" data-bs-dismiss="modal"></button>
              </div>
              <div class="modal-body" id="evtViewBody" style="font-size:.78rem;"></div>
              <div class="modal-footer">
                <button class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Close</button>
              </div>
            </div>
          </div>
        </div>`);
      modal=document.getElementById(modalId);
    }
    const body=document.getElementById('evtViewBody');
    if(!e){ body.innerHTML='<div class="text-muted small">Event not found.</div>'; }
    else{
      const dtLbl=(parseISO(e.event_date)||new Date()).toLocaleDateString('en-PH',{timeZone:'Asia/Manila',month:'long',day:'numeric',year:'numeric'});
      const comp = isCompleted(e) ? '<span class="evt-badge-completed">Completed</span>' : '';
      body.innerHTML=`
        <div class="mb-2"><strong>Title:</strong> ${escapeHtml(e.event_title||'')}</div>
        <div class="row g-2">
          <div class="col-6"><strong>Date:</strong> ${escapeHtml(dtLbl)}</div>
          <div class="col-6"><strong>Time:</strong> ${escapeHtml(timeLabel(e.event_time)||'—')}</div>
          <div class="col-6"><strong>Type:</strong> ${badge(e.event_type||'general')}</div>
          <div class="col-6"><strong>Status:</strong> ${comp||'<span class="text-muted">Scheduled</span>'}</div>
          <div class="col-12"><strong>Location:</strong> ${escapeHtml(e.location||'—')}</div>
          ${e.event_description ? `<div class="col-12"><strong>Description:</strong> ${escapeHtml(e.event_description)}</div>`:''}
        </div>
      `;
    }
    bootstrap.Modal.getOrCreateInstance(modal).show();
  }

  // Simple reschedule modal
  function openRescheduleModal(eventId, currentDate, currentTime){
    const mid='evtReschedModal';
    let modal=document.getElementById(mid);
    if(!modal){
      document.body.insertAdjacentHTML('beforeend',`
        <div class="modal fade" id="${mid}" tabindex="-1">
          <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius:18px;">
              <div class="modal-header">
                <h5 class="modal-title" style="font-size:.9rem;font-weight:800;">Reschedule Event</h5>
                <button class="btn-close" data-bs-dismiss="modal"></button>
              </div>
              <div class="modal-body">
                <form id="evtReschedForm" class="row g-2">
                  <div class="col-7">
                    <label class="form-label" style="font-size:.62rem;font-weight:700;letter-spacing:.06em;">New Date</label>
                    <input type="date" class="form-control form-control-sm" name="event_date" required>
                  </div>
                  <div class="col-5">
                    <label class="form-label" style="font-size:.62rem;font-weight:700;letter-spacing:.06em;">New Time</label>
                    <input type="time" class="form-control form-control-sm" name="event_time">
                  </div>
                  <div class="col-12 d-flex justify-content-end gap-2 mt-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-success btn-sm" id="evtReschedSave"><i class="bi bi-check2-circle me-1"></i>Save</button>
                  </div>
                  <input type="hidden" name="csrf_token" value="${window.__BHW_CSRF}">
                  <input type="hidden" name="reschedule_event" value="">
                </form>
                <div class="small text-danger mt-2 d-none" id="evtReschedErr"></div>
              </div>
            </div>
          </div>
        </div>`);
      modal=document.getElementById(mid);
    }
    const form = modal.querySelector('#evtReschedForm');
    form.event_date.value = currentDate || fmtDate(new Date());
    form.event_time.value = currentTime || '';
    form.reschedule_event.value = eventId || '';
    const err = modal.querySelector('#evtReschedErr');
    err.classList.add('d-none'); err.textContent='';

    form.onsubmit = (e)=>{
      e.preventDefault();
      const fd=new FormData(form);
      fetch(api.events,{method:'POST',body:fd})
        .then(parseJSONSafe)
        .then(j=>{
          if(!j.success) throw new Error(j.error||'Reschedule failed');
          bootstrap.Modal.getInstance(modal).hide();
          showToast('Event rescheduled','success');
          const mk = (fd.get('event_date')||'').toString().slice(0,7) || fmtMonthKey(viewMonth);
          monthCache.delete(mk);
          loadMonth(mk).then(()=>{ updateMetrics(); rerenderActiveTab(); });
        })
        .catch(ex=>{ err.textContent=ex.message; err.classList.remove('d-none'); });
    };

    bootstrap.Modal.getOrCreateInstance(modal).show();
  }
}


// ================== Health Reports (New) ==================
function renderHealthReports(label){
  showLoading(label);

  const today = new Date();
  const monthKey = today.toISOString().slice(0,7); // YYYY-MM

  Promise.allSettled([
    fetchJSON(api.reports+'?vaccination_coverage=1'),
    fetchJSON(api.reports+'?maternal_stats=1'),
    fetchJSON(api.reports+'?health_risks=1'),
    fetchJSON(api.immun+'?recent_vaccinations=1&limit=400'),
    fetchJSON(api.events+'?month='+monthKey)
  ]).then(([covRes, matRes, riskRes, recentRes, evRes])=>{
    const cov = covRes.value||{};
    const mat = matRes.value||{};
    const risk = riskRes.value||{};
    const recent = recentRes.value||{};
    const ev = evRes.value||{};

    if(!cov.success || !mat.success || !risk.success){
      moduleContent.innerHTML = '<div class="alert alert-danger small">Failed to load reports.</div>';
      return;
    }

    // Campaign reach (sum target_participants kung meron)
    let campaignReach = 0;
    if (Array.isArray(ev.events)) {
      ev.events.forEach(e=>{
        const n = parseInt(e.target_participants ?? 0, 10);
        if(!isNaN(n)) campaignReach += n;
      });
    }

    const totalChildren   = cov.total_children ?? 0;
    const overallCoverage = cov.overall_dose_coverage_pct ?? 0;
    const prenatalVisits  = mat.total_consultations ?? 0; // simpleng bilang
    const safeDeliveries  = (risk.aggregate ? 0 : 0); // walang direct metric; gagamit tayo ng Postnatal proxy sa hinaharap
    // Build immunization trend (huling 6 na buwan)
    const trend = buildMonthlyTrend(recent.recent_vaccinations||[], 6);

    // Top shell
    moduleContent.innerHTML = `
      <div class="fade-in">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
          <div>
            <h2 class="imm-title" style="margin:0 0 .3rem;">Health Reports & Analytics</h2>
            <div class="imm-sub">Comprehensive health statistics and insights</div>
          </div>
          <div class="d-flex gap-2">
            <button class="btn btn-success btn-sm" id="btnExportAll"><i class="bi bi-download me-1"></i> Export All Reports</button>
          </div>
        </div>

        <div class="metric-grid">
          ${repMetricCard('Vaccination Coverage', (overallCoverage||0)+'%', 'bi-graph-up')}
          ${repMetricCard('Prenatal Visits', prenatalVisits, 'bi-activity')}
          ${repMetricCard('Safe Deliveries', safeDeliveries, 'bi-heart')} 
          ${repMetricCard('Campaign Reach', campaignReach, 'bi-broadcast')}
        </div>

        <div class="nav imm-tabs mb-3" id="repTabs">
          <button class="nav-link active" data-tab="vacc">Vaccination Coverage</button>
          <button class="nav-link" data-tab="maternal">Maternal Health</button>
          <button class="nav-link" data-tab="risks">Health Risks</button>
          <button class="nav-link" data-tab="campaign">Campaign Attendance</button>
        </div>

        <div id="repPanel"></div>
      </div>
    `;

    // Default tab
    loadVaccTab();

    document.getElementById('btnExportAll').addEventListener('click',()=>exportAll());

    document.getElementById('repTabs').addEventListener('click',e=>{
      const b=e.target.closest('.nav-link'); if(!b) return;
      document.querySelectorAll('#repTabs .nav-link').forEach(x=>x.classList.remove('active'));
      b.classList.add('active');
      const t=b.dataset.tab;
      if(t==='vacc') loadVaccTab();
      if(t==='maternal') loadMaternalTab();
      if(t==='risks') loadRisksTab();
      if(t==='campaign') loadCampaignTab();
    });

    // ========== Tabs ==========
    function loadVaccTab(){
      const panel=document.getElementById('repPanel');
      const per = cov.per_vaccine||[];
      const bars = per.map(p=>{
        return {
          label: (p.vaccine_code||'').toUpperCase(),
          values: [
            {name:'Completed', value: p.children_completed ?? 0, color:'#0d7c4e'},
            {name:'Any Dose',  value: p.children_with_any_dose ?? 0, color:'#9fd6bf'}
          ]
        };
      });
      const maxVal = Math.max(1, ...bars.flatMap(b=>b.values.map(v=>v.value)));

      panel.innerHTML = `
        <div class="row g-3">
          <div class="col-lg-6">
            <div class="imm-card">
              <div class="d-flex justify-content-between align-items-center">
                <h6>Vaccination Completion Rates</h6>
                <button class="btn btn-sm btn-outline-secondary" id="expVacc"><i class="bi bi-download me-1"></i>Export Report</button>
              </div>
              <div class="imm-small-muted mb-2">Coverage by vaccine type</div>
              <div>${barChartSVG(bars, maxVal)}</div>
            </div>
          </div>
          <div class="col-lg-6">
            <div class="imm-card">
              <div class="d-flex justify-content-between align-items-center">
                <h6>Immunization Trends</h6>
                <button class="btn btn-sm btn-outline-secondary" id="expTrend"><i class="bi bi-download me-1"></i>Export Report</button>
              </div>
              <div class="imm-small-muted mb-2">Monthly vaccination administration</div>
              <div>${lineChartSVG(trend.labels, trend.values, '#0a63c9')}</div>
              <div class="imm-small-muted mt-1">Vaccinations Given</div>
            </div>
          </div>
        </div>
        <div class="imm-card mt-3">
          <h6>Detailed Vaccination Statistics</h6>
          <div class="table-responsive" style="max-height:360px;">
            <table class="imm-table">
              <thead>
                <tr>
                  <th>Vaccine</th>
                  <th>Doses Required</th>
                  <th>Children with Any Dose</th>
                  <th>Completed</th>
                  <th>% Any Coverage</th>
                  <th>% Full Coverage</th>
                </tr>
              </thead>
              <tbody>
                ${per.map(p=>`
                  <tr>
                    <td>${escapeHtml(p.vaccine_code||'')} — ${escapeHtml(p.vaccine_name||'')}</td>
                    <td>${p.doses_required??'—'}</td>
                    <td>${p.children_with_any_dose??0}</td>
                    <td>${p.children_completed??0}</td>
                    <td>${fmtPct(p.any_coverage_pct)}</td>
                    <td>${fmtPct(p.full_coverage_pct)}</td>
                  </tr>
                `).join('')}
              </tbody>
            </table>
          </div>
        </div>
      `;
      document.getElementById('expVacc').addEventListener('click',()=>exportSection(panel.querySelector('.imm-card')));
      document.getElementById('expTrend').addEventListener('click',()=>exportSection(panel.querySelectorAll('.imm-card')[1]));
    }

    function loadMaternalTab(){
      const panel=document.getElementById('repPanel');
      const totalMothers = mat.total_mothers ?? 0;
      const totalConsult = mat.total_consultations ?? 0;
      const risky = mat.mothers_with_risks ?? 0;

      panel.innerHTML = `
        <div class="imm-card">
          <div class="d-flex justify-content-between align-items-center">
            <h6>Maternal Health Summary</h6>
            <button class="btn btn-sm btn-outline-secondary" id="expMat"><i class="bi bi-download me-1"></i>Export Report</button>
          </div>
          <div class="imm-metrics" style="margin-top:.5rem;">
            ${metricMini('Total Mothers', totalMothers,'bi-people')}
            ${metricMini('Consultations', totalConsult,'bi-clipboard2-pulse')}
            ${metricMini('With Risk Flags', risky,'bi-exclamation-triangle')}
          </div>
          <div class="imm-small-muted mt-1">Note: Risk is based on latest consultation per mother.</div>
        </div>
      `;
      document.getElementById('expMat').addEventListener('click',()=>exportSection(panel.querySelector('.imm-card')));
    }

    function loadRisksTab(){
      const panel=document.getElementById('repPanel');
      const agg = risk.aggregate || {};
      const rows = [
        ['Vaginal Bleeding', agg.vb||0],
        ['Urinary Infection', agg.ui||0],
        ['High BP', agg.hbp||0],
        ['Fever ≥38°C', agg.fev||0],
        ['Pallor', agg.pal||0],
        ['Abnormal Abd Size', agg.abd||0],
        ['Abnormal Presentation', agg.pres||0],
        ['Absent Fetal Heartbeat', agg.fht||0],
        ['Swelling', agg.swl||0],
        ['Vaginal Infection', agg.vag||0],
      ];
      const max = Math.max(1, ...rows.map(r=>r[1]));
      const bars = rows.map(([label,val])=>({label, values:[{name:'Cases', value:val, color:'#c72d20'}]}));

      panel.innerHTML = `
        <div class="imm-card">
          <div class="d-flex justify-content-between align-items-center">
            <h6>Health Risk Distribution</h6>
            <button class="btn btn-sm btn-outline-secondary" id="expRisk"><i class="bi bi-download me-1"></i>Export Report</button>
          </div>
          <div class="imm-small-muted mb-2">Latest-record risks (one row per mother)</div>
          <div>${barChartSVG(bars, max)}</div>
        </div>
      `;
      document.getElementById('expRisk').addEventListener('click',()=>exportSection(panel.querySelector('.imm-card')));
    }

    function loadCampaignTab(){
      const panel=document.getElementById('repPanel');
      const events = Array.isArray(ev.events)? ev.events.slice().sort((a,b)=>
        (a.event_date||'') < (b.event_date||'') ? -1 : (a.event_time||'') < (b.event_time||'') ? -1 : 1
      ) : [];
      panel.innerHTML = `
        <div class="imm-card">
          <div class="d-flex justify-content-between align-items-center">
            <h6>Campaign Attendance (This Month)</h6>
            <button class="btn btn-sm btn-outline-secondary" id="expCamp"><i class="bi bi-download me-1"></i>Export Report</button>
          </div>
          <div class="imm-small-muted mb-2">Sum of target participants when available</div>
          <div class="table-responsive" style="max-height:420px;">
            <table class="imm-table">
              <thead>
                <tr><th>Date</th><th>Title</th><th>Type</th><th>Location</th><th>Target Participants</th></tr>
              </thead>
              <tbody>
                ${events.length? events.map(e=>`
                  <tr>
                    <td>${escapeHtml(e.event_date||'')}</td>
                    <td>${escapeHtml(e.event_title||'')}</td>
                    <td>${escapeHtml((e.event_type||'').toUpperCase())}</td>
                    <td>${escapeHtml(e.location||'')}</td>
                    <td>${e.target_participants!=null? escapeHtml(e.target_participants): '—'}</td>
                  </tr>
                `).join('') : `<tr><td colspan="5" class="text-center text-muted py-4">No events this month.</td></tr>`}
              </tbody>
            </table>
          </div>
        </div>
      `;
      document.getElementById('expCamp').addEventListener('click',()=>exportSection(panel.querySelector('.imm-card')));
    }

    // ========== Helpers ==========

    function repMetricCard(title,value,icon){
      return `<div class="imm-metric">
        <div class="imm-metric-label"><i class="bi ${icon}"></i>${escapeHtml(title)}</div>
        <div class="imm-metric-value">${escapeHtml(String(value))}</div>
        <div class="imm-metric-sub"></div>
      </div>`;
    }
    function metricMini(label,val,icon){
      return `<div class="imm-metric">
        <div class="imm-metric-label"><i class="bi ${icon}"></i>${escapeHtml(label)}</div>
        <div class="imm-metric-value">${escapeHtml(String(val))}</div>
      </div>`;
    }
    function fmtPct(v){ if(v==null) return '0%'; const n=+v; return (isNaN(n)?0:n).toFixed(0)+'%'; }

    function buildMonthlyTrend(records, monthsBack){
      // Aggregate by YYYY-MM
      const map = new Map();
      (records||[]).forEach(r=>{
        const k = String(r.vaccination_date||'').slice(0,7);
        if(k.length===7) map.set(k, (map.get(k)||0)+1);
      });
      // last N months ending this month
      const labels=[], values=[];
      const d=new Date(today);
      for(let i=monthsBack-1;i>=0;i--){
        const dt=new Date(d.getFullYear(), d.getMonth()-i, 1);
        const k=dt.toISOString().slice(0,7);
        labels.push(dt.toLocaleString('en-PH',{timeZone:'Asia/Manila',month:'short'}));
        values.push(map.get(k)||0);
      }
      return {labels, values};
    }

    // Simple inline SVG bar chart (grouped or single series)
    function barChartSVG(series, maxVal){
      const W=520, H=240, pad=30, barW=26, gap=18;
      const groupW = (barW * Math.max(1, series[0]?.values.length || 1)) + 8;
      const totalW = series.length * (groupW + gap);
      const viewW = Math.max(W, totalW + pad*2);
      const scale = (v)=> v<=0? 0 : Math.round(((H - pad*2) * v) / maxVal);
      let x=pad, paths=[];
      series.forEach(s=>{
        let bx=x;
        s.values.forEach(v=>{
          const h=scale(v.value);
          const y=H - pad - h;
          paths.push(`<rect x="${bx}" y="${y}" width="${barW}" height="${h}" fill="${v.color}" rx="4"></rect>`);
          bx += barW + 6;
        });
        // label
        paths.push(`<text x="${x + (barW*s.values.length+6*(s.values.length-1))/2}" y="${H-pad+14}" text-anchor="middle" font-size="10" fill="#445">${escapeHtml(s.label||'')}</text>`);
        x += groupW + gap;
      });
      // y axis lines
      const grid=[];
      for(let i=0;i<=4;i++){
        const y=pad + i*((H-pad*2)/4);
        grid.push(`<line x1="${pad}" y1="${y}" x2="${viewW-pad}" y2="${y}" stroke="#e9eef2" stroke-width="1"/>`);
      }
      return `<svg viewBox="0 0 ${viewW} ${H}" preserveAspectRatio="xMinYMin meet" style="width:100%;height:auto;display:block;">
        ${grid.join('')}
        ${paths.join('')}
      </svg>`;
    }

    // Simple inline SVG line chart
    function lineChartSVG(labels, values, color){
      const W=520, H=240, pad=30;
      const maxVal=Math.max(1, ...values);
      const step = (W - pad*2) / Math.max(1, values.length-1);
      const points = values.map((v,i)=>{
        const x=pad + i*step;
        const y=H - pad - ((H - pad*2) * (v/maxVal));
        return [x,y];
      });
      let d='';
      points.forEach((p,i)=>{ d += (i===0?'M':'L')+p[0]+' '+p[1]+' '; });
      const dots = points.map(p=>`<circle cx="${p[0]}" cy="${p[1]}" r="3" fill="${color}"></circle>`).join('');
      // x labels
      const xl = labels.map((lb,i)=>`<text x="${pad + i*step}" y="${H-pad+14}" text-anchor="middle" font-size="10" fill="#445">${escapeHtml(lb)}</text>`).join('');
      // grid
      const grid=[];
      for(let i=0;i<=4;i++){
        const y=pad + i*((H-pad*2)/4);
        grid.push(`<line x1="${pad}" y1="${y}" x2="${W-pad}" y2="${y}" stroke="#e9eef2" stroke-width="1"/>`);
      }
      return `<svg viewBox="0 0 ${W} ${H}" preserveAspectRatio="xMinYMin meet" style="width:100%;height:auto;display:block;">
        ${grid.join('')}
        <path d="${d.trim()}" fill="none" stroke="${color}" stroke-width="2"></path>
        ${dots}
        ${xl}
      </svg>`;
    }

    // Export helpers
    function exportSection(node){
      const win = window.open('', '_blank');
      win.document.write(`<!doctype html><title>Report</title>
        <style>
          body{font-family:Arial,Helvetica,sans-serif;margin:20px;color:#222;}
          h1{font-size:18px;margin:0 0 8px;}
          .meta{font-size:12px;color:#666;margin-bottom:10px;}
          .card{border:1px solid #ddd;border-radius:8px;padding:12px;margin-bottom:12px;}
        </style>
        <h1>Health Report</h1>
        <div class="meta">Generated ${new Date().toLocaleString('en-PH',{timeZone:'Asia/Manila',dateStyle:'medium',timeStyle:'short'})}</div>
        <div class="card">${node.outerHTML}</div>
        <script>window.print();<\/script>
      `);
      win.document.close();
    }
    function exportAll(){
      const win = window.open('', '_blank');
      win.document.write(`<!doctype html><title>Health Reports</title>
        <style>
          body{font-family:Arial,Helvetica,sans-serif;margin:20px;color:#222;}
          h1{font-size:20px;margin:0 0 6px;}
          .meta{font-size:12px;color:#666;margin-bottom:14px;}
          .grid{display:grid;grid-template-columns:1fr;gap:14px;}
          .card{border:1px solid #ddd;border-radius:8px;padding:12px;}
          @media print {.no-print{display:none;}}
        </style>
        <div class="no-print" style="text-align:right;margin-bottom:10px;">
          <button onclick="window.print()">Print / Save PDF</button>
        </div>
        <h1>Health Reports & Analytics</h1>
        <div class="meta">Generated ${new Date().toLocaleString('en-PH',{timeZone:'Asia/Manila',dateStyle:'medium',timeStyle:'short'})}</div>
        <div class="grid" id="repPrint"></div>
        <script>
          const src = ${JSON.stringify(moduleContent.innerHTML)};
          document.getElementById('repPrint').innerHTML = src;
        <\/script>
      `);
      win.document.close();
    }
  }).catch(err=>{
    moduleContent.innerHTML = '<div class="alert alert-danger small">Error: '+escapeHtml(err.message)+'</div>';
  });
}

/* ================== Mother & Child Registry (Simplified – Mothers only) ================== */
function renderParentRegistry(label){
  showLoading(label);

  // Fetch maternal patients (all) + children
  Promise.allSettled([
    fetchJSON(api.maternal+'?list=1&page=1&page_size=1000'),
    fetchJSON(api.immun+'?children=1')
  ]).then(([mRes,cRes])=>{
    const mothers = (mRes.value?.mothers||[]).map(m=>{
      // Ensure consistent full_name field (API already gives full_name)
      m.full_name = m.full_name || [m.first_name,m.middle_name,m.last_name].filter(Boolean).join(' ');
      return m;
    }).sort((a,b)=> (a.full_name||'').localeCompare(b.full_name||''));

    const children = cRes.value?.children || [];
    const childrenByMother = {};
    children.forEach(ch=>{
      if(!childrenByMother[ch.mother_id]) childrenByMother[ch.mother_id]=[];
      childrenByMother[ch.mother_id].push(ch);
    });

    moduleContent.innerHTML = `
      <div class="fade-in">
        <div class="pr-layout">
          <!-- LEFT LIST -->
          <div class="pr-list-panel">
            <div class="pr-list-head">
              <div class="pr-panel-title">Mother & Child Registry</div>
              <div class="pr-search-wrap">
                <i class="bi bi-search"></i>
                <input id="prSearch" type="text" class="form-control" placeholder="Search parents...">
              </div>
            </div>
            <ul class="pr-parent-list" id="prParentList">
              ${
                mothers.length
                  ? mothers.map((m,i)=>motherListItem(m, childrenByMother[m.mother_id]?.length || 0, i===0)).join('')
                  : '<li class="pr-parent-item" style="cursor:default;">No mothers found.</li>'
              }
            </ul>
          </div>

          <!-- RIGHT DETAIL -->
          <div class="pr-detail-panel" id="prDetail">
            ${
              mothers.length
                ? motherDetailHTML(mothers[0], childrenByMother[mothers[0].mother_id]||[])
                : '<div class="pr-empty">No mothers registered in Maternal Health.</div>'
            }
          </div>
        </div>
      </div>

      <!-- Add Child Modal -->
      <!-- Add Child Modal -->
      <div class="modal fade pr-modal" id="prAddChildModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content">
            <form id="prAddChildForm" autocomplete="off">
              <div class="modal-header">
                <h5 class="modal-title" style="font-size:.85rem;font-weight:700;">Add Child</h5>
                <button class="btn-close" data-bs-dismiss="modal"></button>
              </div>
              <div class="modal-body">
                <input type="hidden" name="mother_id" id="prChildMotherId">
                <div class="row g-3">
                  <div class="col-md-4">
                    <label style="font-size:.55rem;font-weight:700;letter-spacing:.05em;text-transform:uppercase;">First Name *</label>
                    <input name="first_name" class="form-control" required>
                  </div>
                  <div class="col-md-4">
                    <label style="font-size:.55rem;font-weight:700;letter-spacing:.05em;text-transform:uppercase;">Middle Name</label>
                    <input name="middle_name" class="form-control">
                  </div>
                  <div class="col-md-4">
                    <label style="font-size:.55rem;font-weight:700;letter-spacing:.05em;text-transform:uppercase;">Last Name *</label>
                    <input name="last_name" class="form-control" required>
                  </div>
                  <div class="col-md-6">
                    <label style="font-size:.55rem;font-weight:700;letter-spacing:.05em;text-transform:uppercase;">Date of Birth *</label>
                    <input name="birth_date" type="date" class="form-control" required>
                  </div>
                  <div class="col-md-6">
                    <label style="font-size:.55rem;font-weight:700;letter-spacing:.05em;text-transform:uppercase;">Gender *</label>
                    <select name="sex" class="form-select" required>
                      <option value="">Select</option>
                      <option value="male">Male</option>
                      <option value="female">Female</option>
                    </select>
                  </div>
                  <div class="col-md-6">
                    <label style="font-size:.55rem;font-weight:700;letter-spacing:.05em;text-transform:uppercase;">Weight (kg)</label>
                    <input name="weight_kg" type="number" step="0.01" class="form-control">
                  </div>
                  <div class="col-md-6">
                    <label style="font-size:.55rem;font-weight:700;letter-spacing:.05em;text-transform:uppercase;">Height (cm)</label>
                    <input name="height_cm" type="number" step="0.1" class="form-control">
                  </div>
                </div>
                <input type="hidden" name="add_child" value="1">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                <div class="small text-danger d-none" id="prChildErr"></div>
                <div class="small text-success d-none" id="prChildOk">Saved!</div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                <button class="btn btn-success btn-sm"><i class="bi bi-save me-1"></i>Save Child</button>
              </div>
            </form>
          </div>
        </div>
      </div>
    `;

    // Wire interactions
    const listEl   = document.getElementById('prParentList');
    const detailEl = document.getElementById('prDetail');
    const searchEl = document.getElementById('prSearch');

    listEl.addEventListener('click', e=>{
      const li = e.target.closest('.pr-parent-item[data-id]');
      if(!li) return;
      listEl.querySelectorAll('.pr-parent-item').forEach(x=>x.classList.remove('active'));
      li.classList.add('active');
      const id = parseInt(li.dataset.id,10);
      const mom = mothers.find(m=>m.mother_id==id);
      detailEl.innerHTML = motherDetailHTML(mom, childrenByMother[id]||[]);
      wireDetailButtons();
    });

    let timer=null;
    searchEl.addEventListener('input', ()=>{
      clearTimeout(timer);
      timer=setTimeout(()=>{
        const q=(searchEl.value||'').toLowerCase();
        listEl.querySelectorAll('.pr-parent-item[data-id]').forEach(li=>{
          const txt=li.innerText.toLowerCase();
            li.classList.toggle('d-none', !txt.includes(q));
        });
      },180);
    });

    function wireDetailButtons(){
      detailEl.querySelectorAll('[data-add-child]').forEach(btn=>{
        btn.addEventListener('click',()=>{
          const mid=btn.getAttribute('data-add-child');
          document.getElementById('prChildMotherId').value=mid;
          const f=document.getElementById('prAddChildForm');
          f.reset();
          document.getElementById('prChildErr').classList.add('d-none');
          document.getElementById('prChildOk').classList.add('d-none');
          bootstrap.Modal.getOrCreateInstance(document.getElementById('prAddChildModal')).show();
        });
      });
    }
    wireDetailButtons();

    // Helper to split child's full name into components for API
    function splitFullName(full){
      const parts=(full||'').trim().split(/\s+/).filter(Boolean);
      if(parts.length===0) return {first_name:'(Unknown)',middle_name:'',last_name:'(Unknown)'};
      if(parts.length===1) return {first_name:parts[0],middle_name:'',last_name:'(Unknown)'};
      if(parts.length===2) return {first_name:parts[0],middle_name:'',last_name:parts[1]};
      return {first_name:parts[0],middle_name:parts.slice(1,-1).join(' '),last_name:parts[parts.length-1]};
    }

    // Add Child submit (enhanced to send first/middle/last)
    document.getElementById('prAddChildForm').addEventListener('submit',e=>{
      e.preventDefault();
      const form=e.target;
      const errEl=document.getElementById('prChildErr');
      const okEl=document.getElementById('prChildOk');
      errEl.classList.add('d-none'); okEl.classList.add('d-none');

      const fd=new FormData(form);
      // Ensure optional numeric fields posted correctly (blank -> remove)
      ['weight_kg','height_cm'].forEach(f=>{
        if(fd.get(f)==='') fd.delete(f);
      });

      fetch(api.immun,{method:'POST',body:fd})
        .then(parseJSONSafe)
        .then(j=>{
          if(!j.success) throw new Error(j.error||'Save failed');
          okEl.classList.remove('d-none');

          const mid=parseInt(fd.get('mother_id'),10);
          childrenByMother[mid]=childrenByMother[mid]||[];
          const first=fd.get('first_name')||'';
          const middle=fd.get('middle_name')||'';
          const last=fd.get('last_name')||'';
          const full=[first,middle,last].filter(Boolean).join(' ');
          childrenByMother[mid].push({
            child_id:j.child_id,
            full_name:full,
            birth_date:fd.get('birth_date'),
            sex:fd.get('sex'),
            age_months:j.age_months,
            weight_kg:fd.get('weight_kg')||null,
            height_cm:fd.get('height_cm')||null
          });

          // Refresh detail pane if same mother is active
          const activeLi=document.querySelector('.pr-parent-item.active');
          if(activeLi && parseInt(activeLi.dataset.id,10)===mid){
            const mom=mothers.find(m=>m.mother_id==mid);
            document.getElementById('prDetail').innerHTML = motherDetailHTML(mom, childrenByMother[mid]);
            wireDetailButtons();
          }

          // Update count badge
          const li=document.querySelector(`.pr-parent-item[data-id="${mid}"]`);
          if(li){
            const count=childrenByMother[mid].length;
            const smalls=[...li.querySelectorAll('small')];
            const lastS=smalls[smalls.length-1];
            if(lastS) lastS.innerHTML='<span class="dot"></span>'+ (count===1?'1 child':count+' children');
          }

          setTimeout(()=> bootstrap.Modal.getInstance(document.getElementById('prAddChildModal')).hide(),900);
        })
        .catch(ex=>{
          errEl.textContent=ex.message;
          errEl.classList.remove('d-none');
        });
    });

  }).catch(err=>{
    moduleContent.innerHTML = '<div class="alert alert-danger small">Error loading Mother & Child Registry: '+escapeHtml(err.message)+'</div>';
  });
}




 /* ===== Replace stubs below with your real full module implementations ===== */
 function renderRecentActivities(l){showLoading(l);moduleContent.innerHTML='<div class="alert alert-info">Paste original Recent Activities code here.</div>';}
 function renderAlertSystem(l){showLoading(l);moduleContent.innerHTML='<div class="alert alert-info">Paste original Alert System code here.</div>';}
 function renderUpcomingImmunizations(l){showLoading(l);moduleContent.innerHTML='<div class="alert alert-secondary">Upcoming Immunizations placeholder.</div>';}
 function renderImmunizationCard(l){showLoading(l);moduleContent.innerHTML='<div class="alert alert-info">Immunization Card - insert original code.</div>';}
 function renderVaccineSchedule(l){showLoading(l);moduleContent.innerHTML='<div class="alert alert-info">Vaccine Schedule - insert original code.</div>';}
 function renderOverdueAlerts(l){showLoading(l);moduleContent.innerHTML='<div class="alert alert-info">Overdue Alerts - insert original code.</div>';}
 function renderParentNotifications(l){showLoading(l);moduleContent.innerHTML='<div class="alert alert-info">Parent Notifications - insert original code.</div>';}
/* ========================================================================== */

/* Immunization Module with robust error handling + graceful fallbacks (Enhanced Override) */
function renderVaccinationEntry(label){
  showLoading(label);

  // Helper: always resolve with { ok:true|false, data|error }
  function safeFetch(url){
    return fetch(url,{headers:{'X-Requested-With':'fetch'}})
      .then(async r=>{
        const text = await r.text();
        let json=null, parseErr=null;
        try{ json = text.trim() ? JSON.parse(text) : {}; }catch(e){ parseErr=e; }
        if(!r.ok){
          return {ok:false,error:`HTTP ${r.status}`,raw:text.slice(0,180)};
        }
        if(parseErr){
          return {ok:false,error:'Invalid JSON',raw:text.slice(0,180)};
        }
        return {ok:(json&&json.success!==false),data:json,error:json.success===false?(json.error||'API error'):null};
      })
      .catch(e=>({ok:false,error:e.message||'Network error'}));
  }

  Promise.all([
    safeFetch(api.reports+'?vaccination_coverage=1'),   // 0
    safeFetch(api.immun+'?overdue=1'),                  // 1
    safeFetch(api.immun+'?schedule=1'),                 // 2
    safeFetch(api.notif+'?list=1')                      // 3 (non‑critical)
  ]).then(res=>{
    const covRes   = res[0];
    const overRes  = res[1];
    const schedRes = res[2];
    const notifRes = res[3];

    // Prepare usable data or safe fallbacks
    const coverage = covRes.ok && covRes.data ? covRes.data : {overall_dose_coverage_pct:0,total_children:0,fully_immunized_children:0};
    const overdue  = overRes.ok && overRes.data ? overRes.data : {overdue:[],dueSoon:[]};
    const schedule = schedRes.ok && schedRes.data ? schedRes.data : {schedule:[]};
    const notif    = notifRes.ok && notifRes.data ? notifRes.data : {notifications:[]};

    // Collect failures for diagnostics
    const failures=[];
    if(!covRes.ok)   failures.push({name:'coverage',detail:covRes.error});
    if(!overRes.ok)  failures.push({name:'overdue',detail:overRes.error});
    if(!schedRes.ok) failures.push({name:'schedule',detail:schedRes.error});

    // UI alert block (only if something failed)
    let alertHTML='';
    if(failures.length){
      alertHTML = `
        <div class="alert alert-warning" style="font-size:.7rem;">
          <strong>Partial load:</strong> ${failures.map(f=>f.name).join(', ')} failed.
          <div style="margin-top:4px;">
            ${failures.map(f=>`<div>• <code>${f.name}</code>: ${escapeHtml(f.detail||'unknown')}</div>`).join('')}
          </div>
          <button id="immRetryBtn" class="btn btn-sm btn-outline-secondary mt-2" style="font-size:.6rem;">
            <i class="bi bi-arrow-clockwise me-1"></i>Retry
          </button>
        </div>`;
    }

    // If ALL three core calls failed, show focused recovery UI
    if(failures.length===3){
      moduleContent.innerHTML = `
        <div class="imm-wrap fade-in">
          <h2 class="imm-title">Immunization Management</h2>
          <p class="imm-sub">Diagnostics</p>
          ${alertHTML}
          <div class="card p-3" style="border:1px solid var(--border);border-radius:16px;font-size:.75rem;">
            <p class="mb-2"><strong>Possible causes:</strong></p>
            <ul style="margin-left:1.1rem;">
              <li>Missing tables (vaccine_types / immunization_schedule / child_immunizations).</li>
              <li>PHP error (check server logs).</li>
              <li>Incorrect path (verify api.immun points to the right file).</li>
            </ul>
            <p class="mb-2"><strong>Quick fix if tables exist but empty:</strong></p>
            <button class="btn btn-success btn-sm" id="immBootstrapVaccines">
              <i class="bi bi-capsule me-1"></i> Load Standard Vaccines
            </button>
            <div id="immBootstrapMsg" class="mt-2 small fw-semibold"></div>
          </div>
        </div>`;
      wireRecoveryButtons();
      return;
    }

    // ---- Normal (or partial) render continues even if some failed ----
    renderImmunizationUI(coverage, overdue, schedule, notif, alertHTML);
    wireRecoveryButtons();

    function wireRecoveryButtons(){
      const retry=document.getElementById('immRetryBtn');
      if(retry){
        retry.addEventListener('click',()=>renderVaccinationEntry(label));
      }
      const boot=document.getElementById('immBootstrapVaccines');
      if(boot){
        boot.addEventListener('click',()=>{
          const msgEl=document.getElementById('immBootstrapMsg');
            msgEl.textContent='Loading...';
          const fd=new FormData();
          fd.append('bulk_add_standard','1');
          fd.append('csrf_token',window.__BHW_CSRF);
          fetch(api.immun,{method:'POST',body:fd})
            .then(parseJSONSafe)
            .then(j=>{
              if(!j.success) throw new Error(j.error||'Insert failed');
              msgEl.innerHTML = `<span class="text-success">Added: ${(j.added||[]).join(', ')||'none'} - Skipped: ${(j.skipped||[]).join(', ')||'none'}</span>`;
              setTimeout(()=>renderVaccinationEntry(label),900);
            })
            .catch(e=>{
              msgEl.innerHTML = `<span class="text-danger">${escapeHtml(e.message)}</span>`;
            });
        });
      }
    }
  }).catch(err=>{
    moduleContent.innerHTML = `
      <div class="alert alert-danger">
        Critical error loading Immunization module: ${escapeHtml(err.message||'Unknown')}
        <button class="btn btn-sm btn-outline-light ms-2" onclick="renderVaccinationEntry('Immunization')">Retry</button>
      </div>`;
  });

  function renderImmunizationUI(cov, over, sched, notif, alertHTML){
    // (Reuse most of your original rendering logic but replace the initial error check)
    const totalChildren = cov.total_children ?? 0;
    const fullyImm = cov.fully_immunized_children ?? 0;
    const dueSoonCount = (over.dueSoon||[]).length;
    const overdueCount = (over.overdue||[]).length;

    moduleContent.innerHTML = `
      <div class="imm-wrap fade-in">
        <div class="imm-head">
          <div>
            <h2 class="imm-title">Immunization Management</h2>
            <p class="imm-sub">Track vaccinations, schedules, and coverage</p>
          </div>
          <div class="d-flex gap-2 flex-wrap">
            <button class="btn btn-outline-success btn-sm" id="immChildRegToggle">
              <i class="bi bi-person-plus me-1"></i> Register Child
            </button>
            <button class="imm-add-btn" id="immRecordBtn"><i class="bi bi-plus-lg"></i> Record Vaccination</button>
          </div>
        </div>
        ${alertHTML||''}
        <div class="imm-metrics">
          ${metricCard('Total Children', totalChildren,'Registered for immunization','bi-people')}
          ${metricCard('Fully Immunized', fullyImm,'Completed schedule','bi-clipboard-check')}
          ${metricCard('Due This Week', dueSoonCount,'Scheduled vaccinations','bi-calendar-week')}
          ${metricCard('Overdue', overdueCount,'Require follow-up','bi-exclamation-octagon')}
        </div>
        <div class="imm-tabs nav" id="immTabs">
          <button class="nav-link active" data-tab="children">Registered Children</button>
          <button class="nav-link" data-tab="schedule">Vaccine Schedule</button>
          <button class="nav-link" data-tab="records">Vaccination Records</button>
          <button class="nav-link" data-tab="overdue">Overdue Alerts</button>
          <button class="nav-link" data-tab="cards">Immunization Cards</button>
          <button class="nav-link" data-tab="parent_notifs">Parent Notifications</button>
        </div>
        <div id="immPanel"></div>
      </div>
      <!-- (Keep existing modals & forms below – omitted here for brevity if unchanged) -->
    `;

    if(typeof loadChildrenPanel==='function') loadChildrenPanel();

    document.getElementById('immRecordBtn')?.addEventListener('click',()=>{
      const m=document.getElementById('immRecordModal');
      if(m) bootstrap.Modal.getOrCreateInstance(m)?.show();
      if(typeof preloadVaccinationForm==='function') preloadVaccinationForm();
    });
    document.getElementById('immTabs')?.addEventListener('click',e=>{
      const b=e.target.closest('.nav-link'); if(!b) return;
      document.querySelectorAll('#immTabs .nav-link').forEach(x=>x.classList.remove('active'));
      b.classList.add('active');
      const tab=b.dataset.tab;
      if(tab==='children' && typeof loadChildrenPanel==='function') loadChildrenPanel();
      else if(tab==='schedule' && typeof loadSchedulePanel==='function') loadSchedulePanel();
      else if(tab==='records' && typeof loadRecordsPanel==='function') loadRecordsPanel();
      else if(tab==='overdue' && typeof loadOverduePanel==='function') loadOverduePanel();
      else if(tab==='cards' && typeof loadCardsPanel==='function') loadCardsPanel();
      else if(tab==='parent_notifs' && typeof loadParentNotifPanel==='function') loadParentNotifPanel();
    });

    function metricCard(label,value,sub,icon){
      return `<div class="imm-metric">\n        <div class="imm-metric-label"><i class="bi ${icon}"></i>${escapeHtml(label)}</div>\n        <div class="imm-metric-value">${escapeHtml(value)}</div>\n        <div class="imm-metric-sub">${escapeHtml(sub)}</div>\n      </div>`;
    }
  }
}


const moduleHandlers={
   health_stats:renderHealthStats,
   recent_activities:renderRecentActivities,
   alert_system:renderAlertSystem,
   upcoming_immunizations:renderUpcomingImmunizations,
   maternal_health:renderMaternalHealth,
   vaccination_entry:renderVaccinationEntry,
   immunization_card:renderImmunizationCard,
   create_parent_accounts: renderCreateParentAccounts,
   health_records_all: renderHealthRecordsAll, 
   health_calendar: renderEventScheduling,    
   report_vaccination_coverage: renderHealthReports, 
   parent_registry: renderParentRegistry,
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


/* === Mother Registration Two-Step Wizard (single source) === */
function initMotherWizard(){
  const modal      = document.getElementById('modalRegisterMother');
  if(!modal) return;

  const form       = document.getElementById('motherForm');
  const step1      = document.getElementById('motherStep1');
  const step2      = document.getElementById('motherStep2');
  const stepLabel  = document.getElementById('motherStepIndicator');
  const footerBox  = document.getElementById('motherFooterButtons');

  // Old messages (Step1 – optional) + new global messages
  const motherErrStep1  = document.getElementById('motherError');
  const motherOkStep1   = document.getElementById('motherSuccess');
  const motherErrGlobal = document.getElementById('motherErrGlobal');
  const motherOkGlobal  = document.getElementById('motherOkGlobal');

  let step = 1;
  let step2Built = false;

  function clearMsgs(){
    [motherErrStep1,motherOkStep1,motherErrGlobal,motherOkGlobal].forEach(el=>{
      if(el) el.classList.add('d-none');
    });
  }

  function showError(msg){
    // If nasa Step 1, gamitin pa rin ang lumang container; else global
    if(step===1 && motherErrStep1){
      motherErrStep1.textContent = msg;
      motherErrStep1.classList.remove('d-none');
    } else if(motherErrGlobal){
      motherErrGlobal.textContent = msg;
      motherErrGlobal.classList.remove('d-none');
    }
  }
  function showOk(msg='Saved!'){
    if(step===1 && motherOkStep1){
      motherOkStep1.textContent = msg;
      motherOkStep1.classList.remove('d-none');
    }
    if(motherOkGlobal){
      motherOkGlobal.textContent = msg;
      motherOkGlobal.classList.remove('d-none');
      setTimeout(()=>motherOkGlobal.classList.add('d-none'),1500);
    }
  }

  function showStep(n){
    step = n;
    clearMsgs();
    if(n===1){
      step1.classList.remove('d-none');
      step2.classList.add('d-none');
      stepLabel.textContent = 'Step 1 of 2';
      footerBox.innerHTML = `
        <button type="button" class="btn btn-success" id="motherNextBtn">
          <span class="d-inline-flex align-items-center gap-1">
            <i class="bi bi-arrow-right-circle"></i> Next
          </span>
        </button>
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
      `;
      footerBox.querySelector('#motherNextBtn').addEventListener('click', onNext);
    } else {
      step1.classList.add('d-none');
      step2.classList.remove('d-none');
      stepLabel.textContent = 'Step 2 of 2';
      footerBox.innerHTML = `
        <button type="button" class="btn btn-outline-secondary" id="motherBackBtn">
          <i class="bi bi-arrow-left"></i> Back
        </button>
        <button type="submit" class="btn btn-success" id="motherSaveBtn">
          <i class="bi bi-save me-1"></i> Save Consultation
        </button>
      `;
      footerBox.querySelector('#motherBackBtn').addEventListener('click', ()=>showStep(1));
      form.addEventListener('submit', onSubmitCombined, { once:true });
    }
  }

async function onNext(){
  clearMsgs();
  const fn = form.first_name.value.trim();
  const ln = form.last_name.value.trim();
  if(!fn || !ln){
    showError('First name at Last name ay required.');
    return;
  }

  try{
    // Quick check kung existing na (first + last only)
    const res = await fetch(api.maternal+'?list_basic=1').then(r=>r.json()).catch(()=>({}));
    if(res.success){
      const exists = (res.mothers||[]).find(m => (m.full_name||'').toLowerCase() === (fn+' '+ln).toLowerCase());
      if(exists){
        // STOP re-registration and redirect user to Consultations tab
        showError('Existing mother na ito. Magdagdag ng consultation sa Consultations tab.');
        showToast('Pumunta sa Consultations tab para magdagdag ng bagong consultation.', 'warning', 4500);

        // Optional: auto-open Consultations tab
        setTimeout(()=>{
          bootstrap.Modal.getInstance(modal)?.hide();
          document.querySelector('#mhTabs .nav-link[data-tab="consults"]')?.click();
        }, 1200);
        return;
      }
    }
  }catch(_){ /* ignore – fallback proceed */ }

  if(!step2Built) buildStep2();
  showStep(2);
}

  function buildStep2(){
    step2Built = true;
    const wrap = document.getElementById('motherConsultWrapper');
    wrap.innerHTML = `
      <div class="row g-3">
        <div class="col-md-3">
          <label>PETSA NG KONSULTASYON *</label>
          <input type="date" name="consultation_date" class="form-control" required value="${new Date().toISOString().slice(0,10)}">
        </div>
        <div class="col-md-2">
          <label>EDAD</label>
          <input type="number" name="age" class="form-control" placeholder="Auto">
        </div>
        <div class="col-md-2">
          <label>TAAS (CM)</label>
          <input type="number" step="0.1" name="height_cm" class="form-control">
        </div>
        <div class="col-md-2">
          <label>TIMBANG (KG)</label>
          <input type="number" step="0.1" name="weight_kg" class="form-control">
        </div>
        <div class="col-md-2">
          <label>Edad ng Pagbubuntis (weeks)</label>
          <input type="number" name="pregnancy_age_weeks" class="form-control" placeholder="Auto" data-autofill="1">
        </div>
        <div class="col-md-3">
          <label>BP Systolic</label>
          <input type="number" name="blood_pressure_systolic" class="form-control">
        </div>
        <div class="col-md-3">
          <label>BP Diastolic</label>
          <input type="number" name="blood_pressure_diastolic" class="form-control">
        </div>
        <div class="col-md-3">
          <label>HULING REGLA (LMP) (LMP)</label>
          <input type="date" name="last_menstruation_date" class="form-control">
        </div>
        <div class="col-md-3">
          <label>TINATAYANG PETSA NG PANGANGANAK (EDD) (EDD)</label>
          <input type="date" name="expected_delivery_date" class="form-control">
        </div>
        <label>MGA PAGSUSURI (LABS)</label>
        <div class="col-md-3">
          <label>HGB</label>
          <input name="hgb_result" class="form-control">
        </div>
        <div class="col-md-3">
          <label>Urine Result</label>
          <input name="urine_result" class="form-control">
        </div>
        <div class="col-md-3">
          <label>VDRL Result</label>
          <input name="vdrl_result" class="form-control">
        </div>
        <div class="col-md-3">
          <label>Other Lab Results</label>
          <input name="other_lab_results" class="form-control">
        </div>
        <div class="col-12">
          <label style="margin-bottom:.3rem;">Mga Palatandaan ng Panganib</label>
          <div class="d-flex flex-wrap gap-2" style="font-size:.65rem;">
            ${[
              ['vaginal_bleeding','Pagdurugo sa Ari'],
              ['urinary_infection','Impeksyon sa Ihi'],
              ['high_blood_pressure','Mataas na Presyon ng Dugo'],
              ['fever_38_celsius','Lagnat ≥38°C'],
              ['pallor','Maputla'],
              ['abnormal_abdominal_size','Hindi Normal na Laki ng Tiyan'],
              ['abnormal_presentation','Hindi Normal na Posisyon'],
              ['absent_fetal_heartbeat','Walang Tibok ng Puso ng Sanggol'],
              ['swelling','Pamamaga'],
              ['vaginal_infection','Impeksyon sa Ari'],
            ].map(([k,l])=>`
              <label class="mh-risk-box" style="background:#f2f6f7;border:1px solid #d9e2e6;">
                <input type="checkbox" name="${k}" value="1" style="margin-right:4px;"> ${l}
              </label>
            `).join('')}
          </div>
        </div>
        
        <!-- Maternal Health Checklist -->
        <div class="col-12 mt-4">
          <label style="margin-bottom:.3rem; font-weight: 700;">KILOS / LUNAS NA GINAWA</label>
          <div class="row g-2" style="font-size:.65rem;">
            <div class="col-md-6">
              <label class="mh-risk-box" style="background:#f2f6f7;border:1px solid #d9e2e6; display:flex; align-items:center; gap:.35rem;">
                <input type="checkbox" name="iron_folate_prescription" value="1" style="margin:0;"> Iron/Folate # Reseta
              </label>
            </div>
            <div class="col-md-6">
              <label class="mh-risk-box" style="background:#f2f6f7;border:1px solid #d9e2e6; display:flex; align-items:center; gap:.35rem;">
                <input type="checkbox" name="additional_iodine" value="1" style="margin:0;"> Dagdag na Iodine sa delikadong lugar
              </label>
            </div>
            <div class="col-md-6">
              <label class="mh-risk-box" style="background:#f2f6f7;border:1px solid #d9e2e6; display:flex; align-items:center; gap:.35rem;">
                <input type="checkbox" name="malaria_prophylaxis" value="1" style="margin:0;"> Malaria Prophylaxis (Oo/Hindi)
              </label>
            </div>
            <div class="col-md-6">
              <label class="mh-risk-box" style="background:#f2f6f7;border:1px solid #d9e2e6; display:flex; align-items:center; gap:.35rem;">
                <input type="checkbox" name="breastfeeding_plan" value="1" style="margin:0;"> Balak Magpasuso ng Nanay (Oo/Hindi)
              </label>
            </div>
            <div class="col-md-6">
              <label class="mh-risk-box" style="background:#f2f6f7;border:1px solid #d9e2e6; display:flex; align-items:center; gap:.35rem;">
                <input type="checkbox" name="danger_advice" value="1" style="margin:0;"> Payo sa 4 na Panganib (Oo/Hindi)
              </label>
            </div>
            <div class="col-md-6">
              <label class="mh-risk-box" style="background:#f2f6f7;border:1px solid #d9e2e6; display:flex; align-items:center; gap:.35rem;">
                <input type="checkbox" name="dental_checkup" value="1" style="margin:0;"> Nagpasuri ng Ngipin (Oo/Hindi)
              </label>
            </div>
            <div class="col-md-6">
              <label class="mh-risk-box" style="background:#f2f6f7;border:1px solid #d9e2e6; display:flex; align-items:center; gap:.35rem;">
                <input type="checkbox" name="emergency_plan" value="1" style="margin:0;"> Planong Pangbiglaan at Lugar ng Panganganakan (Oo/Hindi)
              </label>
            </div>
            <div class="col-md-6">
              <label class="mh-risk-box" style="background:#f2f6f7;border:1px solid #d9e2e6; display:flex; align-items:center; gap:.35rem;">
                <input type="checkbox" name="general_risk" value="1" style="margin:0;"> Panganib (Oo/Hindi)
              </label>
            </div>
            <div class="col-md-6">
              <label style="font-size:.7rem; font-weight:600; margin-bottom:.2rem;">Petsa ng Susunod na Pagdalaw</label>
              <input type="date" name="next_visit_date" class="form-control" style="font-size:.7rem;">
            </div>
          </div>
        </div>
      </div>
      <input type="hidden" name="create_mother_with_consult" value="1">
    `;
    // Auto Age / GA
    const cdEl  = form.querySelector('[name=consultation_date]');
    const lmpEl = form.querySelector('[name=last_menstruation_date]');
    const eddEl = form.querySelector('[name=expected_delivery_date]');
    const gaEl  = form.querySelector('[name=pregnancy_age_weeks]');
    const ageEl = form.querySelector('[name=age]');
    function autoAge(){
      const dob=form.querySelector('[name=date_of_birth]')?.value;
      const cd=cdEl.value;
      if(!dob||!cd) return;
      const dDob=new Date(dob+'T00:00:00'); const dCd=new Date(cd+'T00:00:00');
      if(isNaN(dDob)||isNaN(dCd)) return;
      let a=dCd.getFullYear()-dDob.getFullYear();
      const m=dCd.getMonth()-dDob.getMonth();
      if(m<0||(m===0 && dCd.getDate()<dDob.getDate())) a--;
      if(ageEl.value===''||ageEl.dataset.autofill==='1'){ageEl.value=a;ageEl.dataset.autofill='1';}
    }
    function autoGA(){
      const cd=cdEl.value,lmp=lmpEl.value,edd=eddEl.value;
      if(!cd) return;
      const cdDate=new Date(cd+'T00:00:00'); if(isNaN(cdDate)) return;
      let weeks=null;
      if(lmp){
        const lmpDate=new Date(lmp+'T00:00:00');
        if(!isNaN(lmpDate)){
          const diff=(cdDate-lmpDate)/86400000;
            if(diff>=0) weeks=Math.floor(diff/7);
        }
      } else if(edd){
        const eddDate=new Date(edd+'T00:00:00');
        if(!isNaN(eddDate)){
          const diff=(eddDate-cdDate)/86400000;
          weeks=Math.round(40-(diff/7));
        }
      }
      if(weeks!==null && (gaEl.value===''||gaEl.dataset.autofill==='1')){
        gaEl.value=weeks; gaEl.dataset.autofill='1';
      }
    }
    [cdEl,lmpEl,eddEl].forEach(el=>el.addEventListener('change',()=>{autoAge();autoGA();}));
    gaEl.addEventListener('input',()=>gaEl.dataset.autofill='0');
    ageEl.addEventListener('input',()=>ageEl.dataset.autofill='0');
    autoAge(); autoGA();
  }

  async function onSubmitCombined(e){
    e.preventDefault();
    clearMsgs();

    if(!form.first_name.value.trim() || !form.last_name.value.trim()){
      showError('First name at Last name ay required.');
      return;
    }
    if(!form.consultation_date.value){
      showError('Consultation date ay required.');
      return;
    }

    const saveBtn=document.getElementById('motherSaveBtn');
    const oldHTML=saveBtn.innerHTML;
    saveBtn.disabled=true;
    saveBtn.innerHTML='<span class="spinner-border spinner-border-sm me-1"></span>Saving...';

    try{
      // First, create the mother
      const motherFd = new FormData();
      const motherFields = ['first_name', 'middle_name', 'last_name', 'date_of_birth', 'gravida', 'para', 
                           'contact_number', 'blood_type', 'emergency_contact_name', 'emergency_contact_number',
                           'house_number', 'street_name', 'purok_name', 'subdivision_name', 'csrf_token'];
      
      motherFields.forEach(field => {
        if (form[field]) {
          motherFd.append(field, form[field].value);
        }
      });
      if(!motherFd.get('csrf_token')) motherFd.append('csrf_token', window.__BHW_CSRF);

      const motherResp = await fetch(api.maternal, {method:'POST', body:motherFd});
      const motherTxt = await motherResp.text();
      let motherData;
      try{ motherData = JSON.parse(motherTxt); }catch(_){
        throw new Error('Server did not return valid JSON for mother creation. Raw: '+ motherTxt.slice(0,180));
      }
      if(!motherResp.ok || !motherData.success){
        throw new Error(motherData.error || 'Mother creation failed (HTTP '+motherResp.status+')');
      }

      // Then, create the consultation with intervention data
      const consultFd = new FormData();
      const consultFields = ['consultation_date', 'age', 'height_cm', 'weight_kg', 'blood_pressure_systolic', 
                            'blood_pressure_diastolic', 'pregnancy_age_weeks', 'last_menstruation_date', 
                            'expected_delivery_date', 'hgb_result', 'urine_result', 'vdrl_result', 'other_lab_results',
                            'vaginal_bleeding', 'urinary_infection', 'high_blood_pressure', 'fever_38_celsius', 
                            'pallor', 'abnormal_abdominal_size', 'abnormal_presentation', 'absent_fetal_heartbeat', 
                            'swelling', 'vaginal_infection', 'iron_folate_prescription', 'additional_iodine', 
                            'malaria_prophylaxis', 'breastfeeding_plan', 'danger_advice', 'dental_checkup', 
                            'emergency_plan', 'general_risk', 'next_visit_date', 'csrf_token'];
      
      // Handle regular fields
      const regularFields = ['consultation_date', 'age', 'height_cm', 'weight_kg', 'blood_pressure_systolic', 
                            'blood_pressure_diastolic', 'pregnancy_age_weeks', 'last_menstruation_date', 
                            'expected_delivery_date', 'hgb_result', 'urine_result', 'vdrl_result', 'other_lab_results',
                            'next_visit_date', 'csrf_token'];
      
      regularFields.forEach(field => {
        if (form[field]) {
          consultFd.append(field, form[field].value);
        }
      });
      
      // Handle checkbox fields (risk flags and interventions) - only append if checked
      const checkboxFields = ['vaginal_bleeding', 'urinary_infection', 'high_blood_pressure', 'fever_38_celsius', 
                             'pallor', 'abnormal_abdominal_size', 'abnormal_presentation', 'absent_fetal_heartbeat', 
                             'swelling', 'vaginal_infection', 'iron_folate_prescription', 'additional_iodine', 
                             'malaria_prophylaxis', 'breastfeeding_plan', 'danger_advice', 'dental_checkup', 
                             'emergency_plan', 'general_risk'];
      
      checkboxFields.forEach(field => {
        if (form[field] && form[field].checked) {
          consultFd.append(field, '1');
        }
      });
      consultFd.append('mother_id', motherData.mother_id);
      if(!consultFd.get('csrf_token')) consultFd.append('csrf_token', window.__BHW_CSRF);

      const consultResp = await fetch(api.health, {method:'POST', body:consultFd});
      const consultTxt = await consultResp.text();
      let consultData;
      try{ consultData = JSON.parse(consultTxt); }catch(_){
        throw new Error('Server did not return valid JSON for consultation creation. Raw: '+ consultTxt.slice(0,180));
      }
      if(!consultResp.ok || !consultData.success){
        throw new Error(consultData.error || 'Consultation creation failed (HTTP '+consultResp.status+')');
      }

      showOk('Saved!');
      setTimeout(()=>{
        const inst=bootstrap.Modal.getInstance(modal);
        inst && inst.hide();
        const link=document.querySelector('.nav-link-modern[data-module="maternal_health"]');
        if(link){ setActiveLink(link); loadModule('maternal_health','Maternal Health'); }
        else location.reload();
      },650);
    }catch(ex){
      showError(ex.message);
      // Rebind para pwede ulit mag-submit
      form.addEventListener('submit', onSubmitCombined, { once:true });
    }finally{
      saveBtn.disabled=false;
      saveBtn.innerHTML=oldHTML;
    }
  }

  // Load puroks for dropdown
  function loadPuroks(){
    fetch('bhw_modules/api_puroks.php?list=1')
      .then(response => response.json())
      .then(data => {
        if(data.success && data.puroks){
          const datalist = document.getElementById('purokOptions');
          datalist.innerHTML = '';
          data.puroks.forEach(purok => {
            const option = document.createElement('option');
            option.value = purok.purok_name;
            option.setAttribute('data-purok-id', purok.purok_id);
            datalist.appendChild(option);
          });
        }
      })
      .catch(error => {
        console.error('Error loading puroks:', error);
      });
  }

  // First Next button (initial markup)
  const initNext=document.getElementById('motherNextBtn');
  if(initNext) initNext.addEventListener('click', onNext);

  modal.addEventListener('show.bs.modal',()=>{
    showStep(1);
    clearMsgs();
    loadPuroks();
  });
}

/* Initial load */
loadModule('health_stats','Dashboard');
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>