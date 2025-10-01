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
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>BHW Dashboard</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
<style>
  html, body { height:100%; overflow:hidden; }
  body.dashboard-body { background:#f2f6f9; }
  .layout-wrapper { display:flex; height:100vh; width:100%; overflow:hidden; }
  .sidebar { width:275px; flex:0 0 275px; background:#0d3042; color:#e6f2f7; display:flex; flex-direction:column; position:relative; overflow-y:auto; overflow-x:hidden; scrollbar-width:thin; }
  .sidebar::-webkit-scrollbar { width:7px; }
  .sidebar::-webkit-scrollbar-track { background:#0d3042; }
  .sidebar::-webkit-scrollbar-thumb { background:#1996c6aa; border-radius:4px; }
  .sidebar::-webkit-scrollbar-thumb:hover { background:#25b9ef; }
  .brand { display:flex; gap:.75rem; align-items:flex-start; padding:1.05rem 1.15rem .95rem; background:rgba(255,255,255,.07); font-size:.83rem; line-height:1.25; font-weight:600; }
  .brand .emoji { font-size:1.65rem; }
  .brand small { display:block; font-weight:400; opacity:.85; font-size:.62rem; margin-top:.2rem; letter-spacing:.5px; }
  .menu-section-title { font-size:.60rem; letter-spacing:.085em; text-transform:uppercase; opacity:.55; padding:.75rem 1.05rem .45rem; font-weight:600; }
  .nav-menu { list-style:none; margin:0; padding:0 .45rem .9rem; }
  .nav-menu li a { display:flex; align-items:center; gap:.65rem; padding:.52rem .95rem; font-size:.78rem; color:#cddbe2; text-decoration:none; border-left:3px solid transparent; border-radius:.35rem; transition:.12s; line-height:1.15; }
  .nav-menu li a:hover { background:rgba(255,255,255,.09); color:#fff; }
  .nav-menu li a.active { background:linear-gradient(90deg,#1ca3d833,#1ca3d814); border-left-color:#1ca3d8; color:#fff; font-weight:600; }
  .sidebar-footer { margin-top:auto; padding:.75rem 1rem .95rem; font-size:.6rem; opacity:.55; }
  .content-area { flex:1; min-width:0; display:flex; flex-direction:column; overflow:hidden; position:relative; }
  .topbar { background:#ffffff; border-bottom:1px solid #dbe5ec; padding:.6rem 1.3rem; display:flex; align-items:center; gap:1rem; flex-shrink:0; }
  .page-title { font-size:.92rem; font-weight:600; margin:0; color:#0f3a51; }
  .badge-role { background:#1ca3d8; }
  main { flex:1; display:flex; flex-direction:column; overflow:hidden; }
  #moduleContent { flex:1; overflow:auto; -webkit-overflow-scrolling:touch; padding-right:.25rem; }
  #moduleContent::-webkit-scrollbar { width:8px; }
  #moduleContent::-webkit-scrollbar-track { background:transparent; }
  #moduleContent::-webkit-scrollbar-thumb { background:#c2d4dd; border-radius:4px; }
  #moduleContent::-webkit-scrollbar-thumb:hover { background:#a7bec9; }
  .loading-state { padding:2.3rem 1.5rem; text-align:center; color:#6c757d; }
  .module-hint { font-size:.68rem; background:#ffffff; border:1px dashed #9cc9da; padding:.6rem .75rem; border-radius:.5rem; margin-bottom:1.15rem; color:#1c566f; }
  .mobile-close { position:absolute; top:.55rem; right:.55rem; background:transparent; color:#fff; border:none; font-size:1.25rem; display:none; }
  .sidebar.show .mobile-close { display:block; }
  @media (max-width: 991.98px) {
    html,body { overflow:hidden; }
    .sidebar { position:fixed; z-index:1045; transform:translateX(-100%); transition:.33s; top:0; left:0; bottom:0; }
    .sidebar.show { transform:translateX(0); box-shadow:0 0 0 400vmax rgba(0,0,0,.35); }
    .topbar { position:fixed; top:0; left:0; right:0; z-index:1020; }
    .content-area { padding-top:56px; }
  }
  .risk-flag { font-size:.6rem; margin:2px 2px; }
  .ga-badge { font-size:.6rem; }
  .modal-add-child .modal-dialog { max-width:700px; }
  .modal-add-child .form-section-title { font-size:.65rem; text-transform:uppercase; letter-spacing:.05em; color:#0f3a51; margin:0 0 .35rem; font-weight:600; opacity:.8; }
  /* Vaccine master list */
  .small-label { font-size:.65rem; font-weight:600; letter-spacing:.05em; text-transform:uppercase; opacity:.75; }
  .editing-row { background:#fff9e0 !important; }
  .table-fixed-head thead th { position:sticky; top:0; background:#f8fafc; z-index:5; }
</style>
</head>
<body class="dashboard-body">
<div class="layout-wrapper">
  <aside class="sidebar" id="sidebar">
    <button class="mobile-close" id="closeSidebar" aria-label="Close sidebar"><i class="bi bi-x-lg"></i></button>
    <div class="brand">
      <div class="emoji">👩‍⚕️</div>
      <div>Barangay Health Worker (BHW)<small>Health &amp; Immunization Focused</small></div>
    </div>

    <div class="menu-section-title">Overview</div>
    <ul class="nav-menu">
      <li><a href="#" class="active" data-module="dashboard_home" data-label="Dashboard"><i class="bi bi-house-door"></i><span>Dashboard</span></a></li>
      <li><a href="#" data-module="health_stats" data-label="Health Statistics"><i class="bi bi-activity"></i><span>Health Statistics</span></a></li>
      <li><a href="#" data-module="upcoming_immunizations" data-label="Upcoming Immunizations"><i class="bi bi-capsule-pill"></i><span>Upcoming Immunizations</span></a></li>
      <li><a href="#" data-module="recent_activities" data-label="Recent Activities"><i class="bi bi-clock-history"></i><span>Recent Activities</span></a></li>
      <li><a href="#" data-module="alert_system" data-label="Alert System"><i class="bi bi-exclamation-triangle"></i><span>Alert System</span></a></li>
    </ul>

    <div class="menu-section-title">🤱 Maternal Health</div>
    <ul class="nav-menu">
      <li><a href="#" data-module="mother_registration" data-label="Mother Registration"><i class="bi bi-person-plus"></i><span>Mother Registration</span></a></li>
      <li><a href="#" data-module="prenatal_consultations" data-label="Prenatal Consultations"><i class="bi bi-journal-medical"></i><span>Prenatal Consultations</span></a></li>
      <li><a href="#" data-module="health_risk_assessment" data-label="Health Risk Assessment"><i class="bi bi-heart-pulse"></i><span>Health Risk Assessment</span></a></li>
      <li><a href="#" data-module="postnatal_care" data-label="Postnatal Care"><i class="bi bi-bandaid"></i><span>Postnatal Care</span></a></li>
    </ul>

    <div class="menu-section-title">💉 Immunization</div>
    <ul class="nav-menu" id="immunizationMenu">
      <li><a href="#" data-module="vaccination_entry" data-label="Vaccination Record Entry"><i class="bi bi-syringe"></i><span>Vaccination Record Entry</span></a></li>
      <li><a href="#" data-module="immunization_card" data-label="Immunization Card Generation"><i class="bi bi-card-heading"></i><span>Immunization Card Generation</span></a></li>
      <li><a href="#" data-module="vaccine_schedule" data-label="Vaccine Schedule Management"><i class="bi bi-calendar2-week"></i><span>Vaccine Schedule Management</span></a></li>
      <li><a href="#" data-module="overdue_alerts" data-label="Overdue Vaccination Alerts"><i class="bi bi-bell"></i><span>Overdue Vaccination Alerts</span></a></li>
      <li><a href="#" data-module="parent_notifications" data-label="Parent Notification System"><i class="bi bi-chat-left-text"></i><span>Parent Notification System</span></a></li>
    </ul>

    <div class="menu-section-title">👨‍👩‍👧‍👦 Parent Accounts</div>
    <ul class="nav-menu">
      <li><a href="#" data-module="create_parent_accounts" data-label="Create Parent/Guardian Accounts"><i class="bi bi-person-badge"></i><span>Create Parent/Guardian Accounts</span></a></li>
      <li><a href="#" data-module="link_child_parent" data-label="Link Child to Parent"><i class="bi bi-link-45deg"></i><span>Link Child to Parent</span></a></li>
      <li><a href="#" data-module="access_credentials" data-label="Access Credential Management"><i class="bi bi-key"></i><span>Access Credential Management</span></a></li>
      <li><a href="#" data-module="account_activity" data-label="Account Activity Tracking"><i class="bi bi-graph-up-arrow"></i><span>Account Activity Tracking</span></a></li>
    </ul>

    <div class="menu-section-title">📅 Health Events</div>
    <ul class="nav-menu">
      <li><a href="#" data-module="vaccination_campaigns" data-label="Vaccination Campaigns"><i class="bi bi-megaphone"></i><span>Vaccination Campaigns</span></a></li>
      <li><a href="#" data-module="health_education_sessions" data-label="Health Education Sessions"><i class="bi bi-mortarboard"></i><span>Health Education Sessions</span></a></li>
      <li><a href="#" data-module="maternal_health_visits" data-label="Maternal Health Visits"><i class="bi bi-calendar2-check"></i><span>Maternal Health Visits</span></a></li>
      <li><a href="#" data-module="health_calendar" data-label="Health Calendar"><i class="bi bi-calendar3"></i><span>Health Calendar</span></a></li>
    </ul>

    <div class="menu-section-title">📊 Reports</div>
    <ul class="nav-menu">
      <li><a href="#" data-module="report_vaccination_coverage" data-label="Vaccination Coverage"><i class="bi bi-pie-chart"></i><span>Vaccination Coverage</span></a></li>
      <li><a href="#" data-module="report_maternal_statistics" data-label="Maternal Health Statistics"><i class="bi bi-bar-chart-line"></i><span>Maternal Health Statistics</span></a></li>
      <li><a href="#" data-module="report_health_risks" data-label="Health Risk Reports"><i class="bi bi-activity"></i><span>Health Risk Reports</span></a></li>
      <li><a href="#" data-module="report_attendance_logs" data-label="Attendance Logs"><i class="bi bi-journal-text"></i><span>Attendance Logs</span></a></li>
    </ul>

    <div class="mt-2 px-3">
      <a href="logout.php" class="btn btn-sm btn-outline-light w-100"><i class="bi bi-box-arrow-right me-1"></i> Logout</a>
    </div>
    <div class="sidebar-footer">
      Barangay Health System<br><span class="text-white-50">&copy; <?php echo date('Y'); ?></span>
    </div>
  </aside>

  <div class="content-area">
    <div class="topbar">
      <button class="btn btn-outline-primary btn-sm d-lg-none" id="sidebarToggle"><i class="bi bi-list"></i></button>
      <h1 class="page-title mb-0" id="currentModuleTitle">Dashboard</h1>
      <span class="badge badge-role ms-auto d-none d-lg-inline"><i class="bi bi-person-badge me-1"></i> BHW</span>
    </div>
    <main class="p-4">
      <div id="moduleContent">
        <div class="module-hint">Piliin ang isang module para mag-encode o mag-manage ng maternal health at immunization data.</div>
        <div class="loading-state">
          <div class="spinner-border text-primary mb-3"></div>
          <div class="small">Ready. Choose a module.</div>
        </div>
      </div>
    </main>
  </div>
</div>

<script>
  window.__BHW_CSRF = "<?php echo htmlspecialchars($csrf); ?>";
  const moduleContent = document.getElementById('moduleContent');
  const titleEl = document.getElementById('currentModuleTitle');

  const api = {
    mothers: 'bhw_modules/api_mothers.php',
    health:  'bhw_modules/api_health_records.php',
    immun:   'bhw_modules/api_immunization.php',
    notif:   'bhw_modules/api_notifications.php',
    caps:    'bhw_modules/api_capabilities.php',
    parent:  'bhw_modules/api_parent_accounts.php'  
  };

  fetch(api.caps).then(r=>r.json()).then(j=>{
    if(!j.success) return;
    const feats=j.features||{};
    ['vaccination_entry','immunization_card','vaccine_schedule','overdue_alerts','parent_notifications'].forEach(m=>{
      if(!feats[m]){
        const link=document.querySelector(`a[data-module="${m}"]`);
        if(link) link.closest('li')?.remove();
      }
    });
    const menu=document.getElementById('immunizationMenu');
    if(menu && menu.querySelectorAll('li').length===0){
      const head=menu.previousElementSibling;
      menu.remove(); if(head?.classList.contains('menu-section-title')) head.remove();
    }
  });

  function escapeHtml(s){
    if(s===undefined||s===null) return '';
    return s.toString().replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
      .replace(/"/g,'&quot;').replace(/'/g,'&#39;');
  }
  function setActive(link){
    document.querySelectorAll('.nav-menu a.active').forEach(a=>a.classList.remove('active'));
    link.classList.add('active');
  }
  function showLoading(lbl){
    moduleContent.innerHTML = `<div class="loading-state"><div class="spinner-border text-primary mb-3"></div><div class="small">Loading ${escapeHtml(lbl)}...</div></div>`;
  }
  function fetchJSON(url,opt={}) {
    opt.headers = Object.assign({'X-Requested-With':'fetch'},opt.headers||{});
    return fetch(url,opt).then(r=>{ if(!r.ok) throw new Error('HTTP '+r.status); return r.json(); });
  }

  const simplePlaceholders = {
    dashboard_home:{
      html:`<div class="row g-3">
        <div class="col-md-6 col-xl-3"><div class="card shadow-sm border-0"><div class="card-body small"><div class="fw-semibold mb-1">Maternal Records</div><div class="display-6 fw-bold">—</div><div class="text-secondary mt-1">Total consultations</div></div></div></div>
        <div class="col-md-6 col-xl-3"><div class="card shadow-sm border-0"><div class="card-body small"><div class="fw-semibold mb-1">Registered Mothers</div><div class="display-6 fw-bold">—</div><div class="text-secondary mt-1">Profiles</div></div></div></div>
        <div class="col-md-6 col-xl-3"><div class="card shadow-sm border-0"><div class="card-body small"><div class="fw-semibold mb-1">Active Risks</div><div class="display-6 fw-bold">—</div><div class="text-secondary mt-1">Latest flagged</div></div></div></div>
        <div class="col-md-6 col-xl-3"><div class="card shadow-sm border-0"><div class="card-body small"><div class="fw-semibold mb-1">Today</div><div class="display-6 fw-bold">—</div><div class="text-secondary mt-1">New entries</div></div></div></div>
        <div class="col-12"><div class="card border-0 shadow-sm"><div class="card-body small">
          <h6 class="fw-semibold mb-3">Quick Tips</h6>
          <ul class="small ps-3 mb-0">
            <li>I-record agad ang prenatal visit pagkatapos ng konsultasyon.</li>
            <li>Gamitin ang risk module para sa agarang follow-up.</li>
            <li>Siguraduhin ang tamang LMP at EDD para accurate na pregnancy age.</li>
          </ul>
        </div></div></div>
      </div>`
    }
  };

  /* ================= Mother Registration ================= */
  function renderMotherRegistration(label){
    showLoading(label);
    fetchJSON(api.mothers+'?list=1').then(data=>{
      moduleContent.innerHTML=`<div class="row g-3">
        <div class="col-lg-4">
          <div class="card shadow-sm border-0 mb-3"><div class="card-body small">
            <h6 class="fw-semibold mb-3">Add Mother / Caregiver</h6>
            <form id="motherForm">
              <div class="mb-2"><label class="form-label small mb-1">Full Name</label><input type="text" name="full_name" class="form-control form-control-sm" required></div>
              <div class="mb-2"><label class="form-label small mb-1">Purok Name</label><input type="text" name="purok_name" class="form-control form-control-sm" required placeholder="Hal: Purok 1"><div class="form-text">Auto-add kapag bago.</div></div>
              <div class="mb-2"><label class="form-label small mb-1">Address Details</label><textarea name="address_details" rows="2" class="form-control form-control-sm"></textarea></div>
              <div class="mb-2"><label class="form-label small mb-1">Contact Number</label><input type="text" name="contact_number" class="form-control form-control-sm"></div>
              <input type="hidden" name="csrf_token" value="${window.__BHW_CSRF}">
              <div class="d-grid"><button class="btn btn-primary btn-sm">Save</button></div>
              <div class="form-text text-success mt-1 d-none" id="motherSuccess">Saved!</div>
              <div class="form-text text-danger mt-1 d-none" id="motherError"></div>
            </form>
          </div></div>
          <div class="alert alert-info small mb-0">Tip: Iwasan ang dobleng pangalan. I-check muna sa list bago mag-add.</div>
        </div>
        <div class="col-lg-8">
          <div class="card shadow-sm border-0"><div class="card-body small">
            <h6 class="fw-semibold mb-3">Mothers / Caregivers</h6>
            <div class="table-responsive" style="max-height:470px; overflow:auto;">
              <table class="table table-sm table-hover mb-0 align-middle">
                <thead class="table-light"><tr><th>Name</th><th>Purok</th><th>Contact</th><th>Records</th><th>Last Consult</th><th>Risks</th></tr></thead>
                <tbody>
                  ${data.mothers.map(m=>`<tr>
                    <td>${escapeHtml(m.full_name)}</td>
                    <td>${escapeHtml(m.purok_name||'')}</td>
                    <td>${escapeHtml(m.contact_number||'')}</td>
                    <td>${m.records_count}</td>
                    <td><small>${m.last_consultation_date||'<span class="text-muted">—</span>'}</small></td>
                    <td>${m.risk_count>0?'<span class="badge bg-danger-subtle text-danger">'+m.risk_count+'</span>':'<span class="text-muted">0</span>'}</td>
                  </tr>`).join('')}
                  ${data.mothers.length===0?'<tr><td colspan="6" class="text-center small text-muted">No records.</td></tr>':''}
                </tbody>
              </table>
            </div>
          </div></div>
        </div>
      </div>`;
      document.getElementById('motherForm').addEventListener('submit',e=>{
        e.preventDefault();
        const fd=new FormData(e.target);
        fetch(api.mothers,{method:'POST',body:fd}).then(r=>r.json()).then(j=>{
          if(!j.success) throw new Error(j.error||'Error');
          document.getElementById('motherSuccess').classList.remove('d-none');
          document.getElementById('motherError').classList.add('d-none');
          e.target.reset(); renderMotherRegistration(label);
        }).catch(err=>{
          const el=document.getElementById('motherError'); el.textContent=err.message; el.classList.remove('d-none');
        });
      });
    }).catch(err=> moduleContent.innerHTML=`<div class="alert alert-danger small">Error: ${escapeHtml(err.message)}</div>`);
  }

  function computePregWeeks(cd,lmp,edd){
    if(!cd) return '';
    const c=new Date(cd+'T00:00:00');
    if(lmp){const L=new Date(lmp+'T00:00:00');const d=Math.floor((c-L)/86400000); if(d<0)return''; return Math.floor(d/7);}
    if(edd){const E=new Date(edd+'T00:00:00');const d=Math.floor((E-c)/86400000); return Math.round(40 - d/7);}
    return '';
  }

  function renderPrenatalConsultations(label){
    showLoading(label);
    fetchJSON(api.mothers+'?list_basic=1').then(mothers=>{
      moduleContent.innerHTML=`<div class="row g-3 prenatal-wrapper">
        <div class="col-xl-4">
          <div class="card shadow-sm border-0 mb-3"><div class="card-body small">
            <h6 class="fw-semibold mb-3">Add Consultation</h6>
            <form id="consultForm">
              <div class="mb-2"><label class="form-label small mb-1">Mother</label><select name="mother_id" class="form-select form-select-sm" required id="motherSelect"><option value="">Select...</option>${mothers.mothers.map(m=>`<option value="${m.mother_id}">${escapeHtml(m.full_name)}</option>`).join('')}</select></div>
              <div class="mb-2"><label class="form-label small mb-1">Consultation Date</label><input type="date" name="consultation_date" id="consultDate" class="form-control form-control-sm" required value="<?= date('Y-m-d'); ?>"></div>
              <div class="row g-2">
                <div class="col-4"><label class="form-label small mb-1">Age</label><input type="number" name="age" class="form-control form-control-sm" min="10"></div>
                <div class="col-4"><label class="form-label small mb-1">Ht (cm)</label><input type="number" step="0.1" name="height_cm" class="form-control form-control-sm"></div>
                <div class="col-4"><label class="form-label small mb-1">Wt (kg)</label><input type="number" step="0.1" name="weight_kg" class="form-control form-control-sm"></div>
              </div>
              <div class="row g-2 mt-1">
                <div class="col-6"><label class="form-label small mb-1">BP Sys</label><input type="number" name="blood_pressure_systolic" class="form-control form-control-sm"></div>
                <div class="col-6"><label class="form-label small mb-1">BP Dia</label><input type="number" name="blood_pressure_diastolic" class="form-control form-control-sm"></div>
              </div>
              <div class="row g-2 mt-1">
                <div class="col-6"><label class="form-label small mb-1">LMP</label><input type="date" name="last_menstruation_date" id="lmpDate" class="form-control form-control-sm"></div>
                <div class="col-6"><label class="form-label small mb-1">EDD</label><input type="date" name="expected_delivery_date" id="eddDate" class="form-control form-control-sm"></div>
              </div>
              <div class="mt-1"><label class="form-label small mb-1 d-flex justify-content-between"><span>Pregnancy Age (weeks)</span><span class="text-muted small" id="gaAutoNote"></span></label><input type="number" name="pregnancy_age_weeks" id="pregWeeks" class="form-control form-control-sm" placeholder="Auto"><div class="form-text">Auto-filled mula LMP o EDD; puwedeng i-override.</div></div>
              <div class="mt-2">
                <label class="form-label small mb-1">Labs</label>
                <input type="text" name="hgb_result" class="form-control form-control-sm mb-1" placeholder="HGB">
                <input type="text" name="urine_result" class="form-control form-control-sm mb-1" placeholder="Urine">
                <input type="text" name="vdrl_result" class="form-control form-control-sm mb-1" placeholder="VDRL">
                <textarea name="other_lab_results" rows="2" class="form-control form-control-sm" placeholder="Other lab results"></textarea>
              </div>
              <div class="mt-2">
                <label class="form-label small mb-1 d-block">Risk Flags</label>
                <div class="row g-1 small">
                  ${[['vaginal_bleeding','Vaginal Bleeding'],['urinary_infection','Urinary Infection'],['fever_38_celsius','Fever ≥38°C'],['pallor','Pallor'],['abnormal_abdominal_size','Abnormal Abd Size'],['abnormal_presentation','Abnormal Presentation'],['absent_fetal_heartbeat','No Fetal Heartbeat'],['swelling','Swelling'],['vaginal_infection','Vaginal Infection']].map(f=>`<div class="col-6"><label class="form-check mb-0"><input type="checkbox" class="form-check-input" name="${f[0]}"><span class="form-check-label">${f[1]}</span></label></div>`).join('')}
                </div>
                <div class="form-text">High BP auto-flag kapag systolic ≥140 o diastolic ≥90.</div>
              </div>
              <input type="hidden" name="csrf_token" value="${window.__BHW_CSRF}">
              <div class="d-grid mt-3"><button class="btn btn-primary btn-sm">Save Consultation</button></div>
              <div class="form-text text-success mt-1 d-none" id="consultSuccess">Saved!</div>
              <div class="form-text text-danger mt-1 d-none" id="consultError"></div>
            </form>
          </div></div>
          <div class="alert alert-warning small mb-3">Pumili ng mother para makita ang consultation history.</div>
        </div>
        <div class="col-xl-8">
          <div id="consultHistory" class="h-100">
            <div class="card border-0 shadow-sm"><div class="card-body small">
              <h6 class="fw-semibold mb-2">Consultation History</h6>
              <p class="text-muted mb-0">Wala pang napiling mother.</p>
            </div></div>
          </div>
        </div>
      </div>`;
      const form=document.getElementById('consultForm');
      const motherSelect=document.getElementById('motherSelect');
      const consultDate=document.getElementById('consultDate');
      const lmpDate=document.getElementById('lmpDate');
      const eddDate=document.getElementById('eddDate');
      const pregWeeks=document.getElementById('pregWeeks');
      const gaNote=document.getElementById('gaAutoNote');
      function refreshGA(){ const v=computePregWeeks(consultDate.value,lmpDate.value,eddDate.value);
        if(v!=='' && (pregWeeks.value===''||pregWeeks.dataset.autofill==='1')){ pregWeeks.value=v; pregWeeks.dataset.autofill='1'; gaNote.textContent='auto'; }
        else if(v===''){ if(pregWeeks.dataset.autofill==='1') pregWeeks.value=''; gaNote.textContent=''; }
      }
      [consultDate,lmpDate,eddDate].forEach(el=>el.addEventListener('change',refreshGA));
      pregWeeks.addEventListener('input',()=>{pregWeeks.dataset.autofill='0'; gaNote.textContent='manual';});
      motherSelect.addEventListener('change',()=>{ const id=motherSelect.value; if(!id){ document.getElementById('consultHistory').innerHTML='<div class="card border-0 shadow-sm"><div class="card-body small"><h6 class="fw-semibold mb-2">Consultation History</h6><p class="text-muted mb-0">Wala pang napiling mother.</p></div></div>'; return;} loadConsultations(id); });
      form.addEventListener('submit',e=>{
        e.preventDefault();
        const fd=new FormData(form);
        fetch(api.health,{method:'POST',body:fd}).then(r=>r.json()).then(j=>{
          if(!j.success) throw new Error(j.error||'Error');
          document.getElementById('consultSuccess').classList.remove('d-none');
          document.getElementById('consultError').classList.add('d-none');
          const mid=motherSelect.value; if(mid) loadConsultations(mid);
          form.reset(); pregWeeks.dataset.autofill='0'; gaNote.textContent='';
        }).catch(err=>{
          const el=document.getElementById('consultError'); el.textContent=err.message; el.classList.remove('d-none');
        });
      });
    }).catch(err=> moduleContent.innerHTML=`<div class="alert alert-danger small">Error: ${escapeHtml(err.message)}</div>`);
  }

  function loadConsultations(mother_id){
    const container=document.getElementById('consultHistory');
    container.innerHTML=`<div class="card border-0 shadow-sm"><div class="card-body small"><h6 class="fw-semibold mb-2">Consultation History</h6><div class="text-center py-4"><div class="spinner-border text-primary"></div><div class="small mt-2">Loading records...</div></div></div></div>`;
    fetchJSON(api.health+'?list=1&mother_id='+mother_id).then(j=>{
      container.innerHTML=`<div class="card border-0 shadow-sm h-100"><div class="card-body small d-flex flex-column"><h6 class="fw-semibold mb-3">Consultation History</h6><div class="table-responsive" style="max-height:520px; overflow:auto;"><table class="table table-sm table-hover mb-0"><thead class="table-light"><tr><th>Date</th><th>GA</th><th>Ht</th><th>Wt</th><th>BP</th><th>Risks</th><th>Labs</th></tr></thead><tbody>${
        j.records.map(r=>{
          const riskMap={vaginal_bleeding:'VB',urinary_infection:'UI',high_blood_pressure:'HBP',fever_38_celsius:'FEV',pallor:'PAL',abnormal_abdominal_size:'ABD',abnormal_presentation:'PRES',absent_fetal_heartbeat:'FHT',swelling:'SWL',vaginal_infection:'VAG'};
          const risks=[]; Object.keys(riskMap).forEach(k=>{ if(r[k]==1) risks.push(`<span class="badge text-bg-danger-subtle text-danger border-0 risk-flag">${riskMap[k]}</span>`); });
          let gaBadge='<span class="text-muted">—</span>';
          if(r.pregnancy_age_weeks!==null){
            const ga=parseInt(r.pregnancy_age_weeks,10);
            if(ga>=37) gaBadge=`<span class="badge bg-danger ga-badge">${ga}w</span>`;
            else if(ga>=34) gaBadge=`<span class="badge bg-warning text-dark ga-badge">${ga}w</span>`;
            else gaBadge=`<span class="badge bg-secondary-subtle text-dark ga-badge">${ga}w</span>`;
          }
          return `<tr><td><small>${r.consultation_date}</small></td><td>${gaBadge}</td><td>${r.height_cm??''}</td><td>${r.weight_kg??''}</td><td>${(r.blood_pressure_systolic||'')&&(r.blood_pressure_diastolic||'')?`${r.blood_pressure_systolic}/${r.blood_pressure_diastolic}`:''}</td><td>${risks.join('')||'<span class="text-muted">None</span>'}</td><td><small>${escapeHtml(r.hgb_result||'')}</small></td></tr>`;
        }).join('')} ${j.records.length===0?'<tr><td colspan="7" class="text-center small text-muted">No records.</td></tr>':''}</tbody></table></div></div></div>`;
    }).catch(err=>{
      container.innerHTML=`<div class="alert alert-danger small mb-0">Error loading: ${escapeHtml(err.message)}</div>`;
    });
  }

  function renderRiskAssessment(label){
    showLoading(label);
    fetchJSON(api.health+'?risk_summary=1').then(j=>{
      moduleContent.innerHTML=`<div class="card border-0 shadow-sm"><div class="card-body small"><h6 class="fw-semibold mb-3">${escapeHtml(label)}</h6><p class="text-muted mb-3">Latest consultation per mother na may active risk flags.</p><div class="table-responsive" style="max-height:520px; overflow:auto;"><table class="table table-sm table-hover mb-0 align-middle"><thead class="table-light"><tr><th>Mother</th><th>Date</th><th>GA</th><th>Risk Flags</th><th>Score</th></tr></thead><tbody>${
        j.risks.map(r=>{
          const map={vaginal_bleeding:'Vaginal Bleeding',urinary_infection:'Urinary Infection',high_blood_pressure:'High BP',fever_38_celsius:'Fever ≥38°C',pallor:'Pallor',abnormal_abdominal_size:'Abnormal Abd Size',abnormal_presentation:'Abnormal Presentation',absent_fetal_heartbeat:'No Fetal Heartbeat',swelling:'Swelling',vaginal_infection:'Vaginal Infection'};
          const flags=[]; Object.keys(map).forEach(k=>{ if(r[k]==1) flags.push(`<span class="badge rounded-pill text-bg-danger-subtle text-danger border-0 me-1 mb-1">${map[k]}</span>`); });
          let gaCell='<span class="text-muted">—</span>';
          if(r.pregnancy_age_weeks!==null){
            const ga=parseInt(r.pregnancy_age_weeks,10);
            if(ga>=37) gaCell=`<span class="badge bg-danger">${ga}w</span>`;
            else if(ga>=34) gaCell=`<span class="badge bg-warning text-dark">${ga}w</span>`;
            else gaCell=`<span class="badge bg-secondary-subtle text-dark">${ga}w</span>`;
          }
          return `<tr><td>${escapeHtml(r.full_name)}</td><td><small>${r.consultation_date}</small></td><td>${gaCell}</td><td style="max-width:340px;">${flags.join('')||'<span class="text-muted">None</span>'}</td><td><span class="badge bg-danger">${r.risk_score}</span></td></tr>`;
        }).join('')} ${j.risks.length===0?'<tr><td colspan="5" class="text-center small text-muted">No active risk flags.</td></tr>':''}</tbody></table></div></div></div>`;
    }).catch(err=>{
      moduleContent.innerHTML=`<div class="alert alert-danger small">Error: ${escapeHtml(err.message)}</div>`;
    });
  }

  /* ================= Vaccination Entry (Static + Auto-create) ================= */
  function renderVaccinationEntry(label){
    showLoading(label);
    Promise.all([
      fetchJSON(api.immun+'?children=1'),
      fetchJSON(api.immun+'?vaccines=1'),
      fetchJSON(api.mothers+'?list_basic=1')
    ]).then(([children,vaccines,mothers])=>{
      const staticVaccines = [
        {code:'BCG',name:'BCG Vaccine',cat:'Infant / Early Childhood',doses:1},
        {code:'HEPB',name:'Hepatitis B Vaccine',cat:'Infant / Early Childhood',doses:3},
        {code:'PENTA',name:'Pentavalent Vaccine (DPT-Hep B-HIB)',cat:'Infant / Early Childhood',doses:3},
        {code:'OPV',name:'Oral Polio Vaccine (OPV)',cat:'Infant / Early Childhood',doses:3},
        {code:'IPV',name:'Inactivated Polio Vaccine (IPV)',cat:'Infant / Early Childhood',doses:2},
        {code:'PCV',name:'Pneumococcal Conjugate Vaccine (PCV)',cat:'Infant / Early Childhood',doses:3},
        {code:'MMR',name:'Measles, Mumps, Rubella Vaccine (MMR)',cat:'Infant / Early Childhood',doses:2},
        {code:'MCV',name:'Measles Containing Vaccine (MCV) MR/MMR Booster',cat:'School-Aged Children',doses:1},
        {code:'TD',name:'Tetanus Diphtheria (TD)',cat:'School-Aged Children',doses:2},
        {code:'HPV',name:'Human Papillomavirus Vaccine (HPV)',cat:'School-Aged Children',doses:2}
      ];
      const apiMap={};
      (vaccines.vaccines||[]).forEach(v=>{
        apiMap[(v.vaccine_code||'').toUpperCase()]={
          id:v.vaccine_id,code:v.vaccine_code,name:v.vaccine_name,
          cat:v.vaccine_category||'Other',doses:v.doses_required||1
        };
      });
      staticVaccines.forEach(s=>{ if(!apiMap[s.code]) apiMap[s.code]={id:null,code:s.code,name:s.name,cat:s.cat,doses:s.doses}; });
      const grouped={};
      Object.values(apiMap).forEach(v=>{ if(!grouped[v.cat]) grouped[v.cat]=[]; grouped[v.cat].push(v); });
      Object.keys(grouped).forEach(cat=>grouped[cat].sort((a,b)=>a.name.localeCompare(b.name)));
      const orderedCats=Object.keys(grouped).sort((a,b)=>a.localeCompare(b));

      moduleContent.innerHTML=`<div class="d-flex flex-wrap gap-2 align-items-center mb-3">
        <h6 class="mb-0 fw-semibold">${escapeHtml(label)}</h6>
        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalAddChild"><i class="bi bi-person-plus"></i> Add Child</button>
        <button class="btn btn-sm btn-outline-secondary" id="refreshChildListBtn"><i class="bi bi-arrow-clockwise"></i> Refresh</button>
      </div>
      <div class="row g-3">
        <div class="col-xl-4 col-xxl-3">
          <div class="card shadow-sm border-0"><div class="card-body small">
            <h6 class="fw-semibold mb-3">Add Vaccination</h6>
            <form id="vaccForm">
              <div class="mb-2">
                <label class="form-label small mb-1">Child</label>
                <select name="child_id" class="form-select form-select-sm" required id="vaccChildSel">
                  <option value="">Select...</option>
                  ${children.children.map(c=>`<option value="${c.child_id}">${escapeHtml(c.full_name)} (${c.age_months}m)</option>`).join('')}
                </select>
              </div>
              <div class="mb-2">
                <label class="form-label small mb-1 d-flex justify-content-between"><span>Vaccine</span><span class="text-muted small">Grouped</span></label>
                <select class="form-select form-select-sm" required id="vaccineSel">
                  <option value="">Select...</option>
                  ${orderedCats.map(cat=>`
                    <optgroup label="${escapeHtml(cat)}">
                      ${grouped[cat].map(v=>{
                        const value=v.id?v.id:v.code;
                        return `<option value="${value}" data-code="${escapeHtml(v.code)}" data-name="${escapeHtml(v.name)}" data-cat="${escapeHtml(v.cat)}" data-doses="${v.doses}">
                          ${escapeHtml(v.name)} (${escapeHtml(v.code)})
                        </option>`;
                      }).join('')}
                    </optgroup>
                  `).join('')}
                </select>
              </div>
              <div class="mb-2">
                <label class="form-label small mb-1">Dose #</label>
                <select name="dose_number" class="form-select form-select-sm" required id="doseSel"><option value="">Select vaccine first</option></select>
              </div>
              <div class="row g-2">
                <div class="col-6 mb-2"><label class="form-label small mb-1">Date</label><input type="date" name="vaccination_date" class="form-control form-control-sm" required value="<?= date('Y-m-d'); ?>"></div>
                <div class="col-6 mb-2"><label class="form-label small mb-1">Site</label><input type="text" name="vaccination_site" class="form-control form-control-sm"></div>
              </div>
              <div class="mb-2"><label class="form-label small mb-1">Batch / Lot #</label><input type="text" name="batch_lot_number" class="form-control form-control-sm"></div>
              <div class="mb-2"><label class="form-label small mb-1">Adverse Reactions</label><input type="text" name="adverse_reactions" class="form-control form-control-sm"></div>
              <div class="mb-2"><label class="form-label small mb-1">Notes</label><textarea name="notes" rows="2" class="form-control form-control-sm"></textarea></div>
              <input type="hidden" name="csrf_token" value="${window.__BHW_CSRF}">
              <div class="d-grid"><button class="btn btn-primary btn-sm">Save Vaccination</button></div>
              <div class="form-text text-success mt-1 d-none" id="vaccSuccess">Saved!</div>
              <div class="form-text text-danger mt-1 d-none" id="vaccError"></div>
            </form>
            <div class="alert alert-info mt-3 mb-0 small">Static vaccines merged; kung wala pa sa DB auto-create sa save.</div>
          </div></div>
        </div>
        <div class="col-xl-8 col-xxl-9">
          <div class="card shadow-sm border-0 h-100"><div class="card-body small d-flex flex-column">
            <h6 class="fw-semibold mb-3">Child Immunization Records</h6>
            <div id="vaccRecordsPlaceholder" class="text-muted small">Pumili ng child para makita ang records.</div>
            <div id="vaccRecords" class="d-none"></div>
          </div></div>
        </div>
      </div>
      <div class="modal fade modal-add-child" id="modalAddChild" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg"><div class="modal-content">
          <div class="modal-header py-2"><h6 class="modal-title small mb-0"><i class="bi bi-person-plus me-1"></i> Add Child</h6><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
          <div class="modal-body small">
            <form id="childForm">
              <p class="form-section-title">Basic Details</p>
              <div class="mb-2"><label class="form-label small mb-1">Full Name</label><input type="text" name="full_name" class="form-control form-control-sm" required></div>
              <div class="row g-2">
                <div class="col-md-6 mb-2"><label class="form-label small mb-1">Sex</label><select name="sex" class="form-select form-select-sm" required><option value="">Select...</option><option value="male">Male</option><option value="female">Female</option></select></div>
                <div class="col-md-6 mb-2"><label class="form-label small mb-1">Birth Date</label><input type="date" name="birth_date" class="form-control form-control-sm" required></div>
              </div>
              <div class="mb-3"><label class="form-label small mb-1">Mother / Caregiver</label><select name="mother_id" class="form-select form-select-sm" required>
                <option value="">Select...</option>
                ${mothers.mothers.map(m=>`<option value="${m.mother_id}">${escapeHtml(m.full_name)}</option>`).join('')}
              </select></div>
              <input type="hidden" name="csrf_token" value="${window.__BHW_CSRF}">
              <input type="hidden" name="add_child" value="1">
              <div class="d-grid"><button class="btn btn-primary btn-sm"><i class="bi bi-save me-1"></i>Save Child</button></div>
              <div class="form-text text-success mt-2 d-none" id="childAddSuccess">Saved!</div>
              <div class="form-text text-danger mt-2 d-none" id="childAddError"></div>
            </form>
            <hr class="my-3">
            <div class="text-muted small">Kapag nasave, automatic na ise-select ang bata at lalabas ang kanyang vaccination records.</div>
          </div>
        </div></div>
      </div>`;

      const vaccineSel=document.getElementById('vaccineSel');
      const doseSel=document.getElementById('doseSel');
      vaccineSel.addEventListener('change',()=>{
        doseSel.innerHTML='<option value="">Select...</option>';
        const opt=vaccineSel.selectedOptions[0]; if(!opt) return;
        const d=parseInt(opt.dataset.doses||'1',10);
        for(let i=1;i<=d;i++) doseSel.insertAdjacentHTML('beforeend',`<option value="${i}">${i}</option>`);
      });

      const childSelect=document.getElementById('vaccChildSel');
      function refreshChildOptions(selected=null){
        fetchJSON(api.immun+'?children=1').then(ch=>{
          childSelect.innerHTML='<option value="">Select...</option>';
          ch.children.forEach(c=>{
            const o=document.createElement('option');
            o.value=c.child_id;
            o.textContent=`${c.full_name} (${c.age_months}m)`;
            if(selected && parseInt(selected,10)===parseInt(c.child_id,10)) o.selected=true;
            childSelect.appendChild(o);
          });
          if(selected) loadChildVaccRecords(selected);
        });
      }
      document.getElementById('refreshChildListBtn').addEventListener('click',()=>refreshChildOptions());

      const childForm=document.getElementById('childForm');
      childForm.addEventListener('submit',e=>{
        e.preventDefault();
        const fd=new FormData(childForm);
        fetch(api.immun,{method:'POST',body:fd})
          .then(r=>r.json())
          .then(j=>{
            if(!j.success) throw new Error(j.error||'Error');
            document.getElementById('childAddSuccess').classList.remove('d-none');
            document.getElementById('childAddError').classList.add('d-none');
            const newId=j.child_id;
            setTimeout(()=>{
              bootstrap.Modal.getOrCreateInstance(document.getElementById('modalAddChild')).hide();
              childForm.reset();
              refreshChildOptions(newId);
            },550);
          })
          .catch(err=>{
            const el=document.getElementById('childAddError');
            el.textContent=err.message;
            el.classList.remove('d-none');
          });
      });

      childSelect.addEventListener('change',e=>{
        const cid=e.target.value;
        if(!cid){
          document.getElementById('vaccRecords').classList.add('d-none');
          document.getElementById('vaccRecordsPlaceholder').classList.remove('d-none');
          return;
        }
        loadChildVaccRecords(cid);
      });

      function loadChildVaccRecords(child_id){
        const wrap=document.getElementById('vaccRecords');
        wrap.innerHTML='<div class="text-center py-3"><div class="spinner-border spinner-border-sm text-primary"></div></div>';
        fetchJSON(api.immun+'?records=1&child_id='+child_id).then(j=>{
          if(!j.success) throw new Error(j.error||'Error');
          wrap.classList.remove('d-none');
          document.getElementById('vaccRecordsPlaceholder').classList.add('d-none');
          wrap.innerHTML=`<div class="table-responsive" style="max-height:520px; overflow:auto;">
            <table class="table table-sm table-hover mb-0 align-middle">
              <thead class="table-light"><tr><th>Vaccine</th><th>Dose</th><th>Date</th><th>Next Due</th><th>Site</th><th>Batch</th></tr></thead>
              <tbody>
                ${j.records.map(r=>`
                  <tr>
                    <td>${escapeHtml(r.vaccine_name)} <span class="text-muted">(${escapeHtml(r.vaccine_code)})</span></td>
                    <td>${r.dose_number}</td>
                    <td><small>${r.vaccination_date}</small></td>
                    <td><small>${r.next_dose_due_date||'<span class="text-muted">—</span>'}</small></td>
                    <td>${escapeHtml(r.vaccination_site||'')}</td>
                    <td>${escapeHtml(r.batch_lot_number||'')}</td>
                  </tr>`).join('')}
                ${j.records.length===0?'<tr><td colspan="6" class="text-center small text-muted">No records.</td></tr>':''}
              </tbody>
            </table>
          </div>`;
        }).catch(err=>{
          wrap.innerHTML=`<div class="text-danger small">Error: ${escapeHtml(err.message)}</div>`;
        });
      }

      document.getElementById('vaccForm').addEventListener('submit',e=>{
        e.preventDefault();
        const fd=new FormData(e.target);
        const opt=vaccineSel.selectedOptions[0];
        if(opt){
          const value=opt.value;
            if(!/^[0-9]+$/.test(value)){
              fd.set('vaccine_id','');
              fd.append('vaccine_code',opt.dataset.code);
              fd.append('vaccine_name',opt.dataset.name);
              let catGuess='infant';
              if(opt.dataset.cat.toLowerCase().includes('school')) catGuess='child';
              if(opt.dataset.cat.toLowerCase().includes('booster')) catGuess='booster';
              fd.append('vaccine_category',catGuess);
              fd.append('doses_required',opt.dataset.doses);
            } else fd.set('vaccine_id',value);
        }
        fetch(api.immun,{method:'POST',body:fd}).then(r=>r.json()).then(j=>{
          if(!j.success) throw new Error(j.error||'Error');
          document.getElementById('vaccSuccess').classList.remove('d-none');
          document.getElementById('vaccError').classList.add('d-none');
          const cid=childSelect.value;
          if(cid) loadChildVaccRecords(cid);
          e.target.reset();
          doseSel.innerHTML='<option value="">Select vaccine first</option>';
        }).catch(err=>{
          const el=document.getElementById('vaccError');
          el.textContent=err.message;
          el.classList.remove('d-none');
        });
      });
    }).catch(err=>{
      moduleContent.innerHTML=`<div class="alert alert-danger small">Error: ${escapeHtml(err.message)}</div>`;
    });
  }

  /* ================= Immunization Card ================= */
  function renderImmunizationCard(label){
    showLoading(label);
    fetchJSON(api.immun+'?children=1').then(ch=>{
      moduleContent.innerHTML=`<div class="card border-0 shadow-sm"><div class="card-body small">
        <h6 class="fw-semibold mb-3">${escapeHtml(label)}</h6>
        <select id="cardChild" class="form-select form-select-sm mb-2">
          <option value="">Select Child...</option>
          ${ch.children.map(c=>`<option value="${c.child_id}">${escapeHtml(c.full_name)} (${c.age_months}m)</option>`).join('')}
        </select>
        <div id="cardDisplay" class="text-muted small">Pumili muna ng child.</div>
      </div></div>`;
      const cardChild=document.getElementById('cardChild');
      const cardDisplay=document.getElementById('cardDisplay');
      cardChild.addEventListener('change',()=>{
        const cid=cardChild.value;
        if(!cid){ cardDisplay.innerHTML='Pumili muna ng child.'; return; }
        cardDisplay.innerHTML='<div class="py-4 text-center"><div class="spinner-border text-primary"></div></div>';
        fetchJSON(api.immun+'?card=1&child_id='+cid).then(j=>{
          if(!j.success) throw new Error(j.error||'Error');
          const c=j.child;
          const maxDoses=Math.max(...j.vaccines.map(v=>v.doses_required||1),1);
          const rows=j.vaccines.map(v=>{
            const given={}; v.doses.forEach(d=>given[d.dose_number]=d.vaccination_date);
            const cells=Array.from({length:maxDoses}).map((_,i)=>{
              const dn=i+1;
              if(dn>v.doses_required) return '<td class="text-center text-muted">—</td>';
              return `<td class="text-center">${given[dn]?`<span class="badge bg-success-subtle text-success">${given[dn]}</span>`:'<span class="text-muted">—</span>'}</td>`;
            }).join('');
            return `<tr><td>${escapeHtml(v.vaccine_name)}<br><small class="text-muted">${escapeHtml(v.vaccine_code)}</small></td><td>${escapeHtml(v.vaccine_category)}</td>${cells}</tr>`;
          }).join('');
          cardDisplay.innerHTML=`<div class="mb-3"><strong>${escapeHtml(c.full_name)}</strong> <span class="text-muted">Birth: ${c.birth_date} | Sex: ${c.sex}</span></div>
          <div class="table-responsive"><table class="table table-sm table-bordered align-middle"><thead class="table-light"><tr><th>Vaccine</th><th>Category</th>${Array.from({length:maxDoses}).map((_,i)=>`<th>Dose ${i+1}</th>`).join('')}</tr></thead><tbody>${rows}</tbody></table></div>`;
        }).catch(err=>{
          cardDisplay.innerHTML=`<div class="text-danger small">Error: ${escapeHtml(err.message)}</div>`;
        });
      });
    }).catch(err=> moduleContent.innerHTML=`<div class="alert alert-danger small">Error: ${escapeHtml(err.message)}</div>`);
  }

  /* ================= Vaccine Schedule Management (Master List CRUD) ================= */
  function renderVaccineSchedule(label){
    showLoading(label);
    fetchJSON(api.immun+'?vaccines=1').then(j=>{
      if(!j.success) throw new Error(j.error||'Error');
      const vaccines=j.vaccines||[];
      moduleContent.innerHTML=`
        <div class="card border-0 shadow-sm mb-3">
          <div class="card-body small">
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-2">
              <h6 class="fw-semibold mb-0">${escapeHtml(label)}</h6>
              <input type="text" id="vaxSearch" class="form-control form-control-sm" style="max-width:220px;" placeholder="Search code / name">
            </div>
            <form id="vaccineForm" class="row g-2 align-items-end">
              <input type="hidden" name="csrf_token" value="${window.__BHW_CSRF}">
              <input type="hidden" name="add_update_vaccine" value="1">
              <input type="hidden" name="vaccine_id" id="vf_id">
              <div class="col-md-2"><label class="small-label mb-1">Code *</label><input type="text" name="vaccine_code" id="vf_code" class="form-control form-control-sm" required></div>
              <div class="col-md-3"><label class="small-label mb-1">Name *</label><input type="text" name="vaccine_name" id="vf_name" class="form-control form-control-sm" required></div>
              <div class="col-md-3"><label class="small-label mb-1">Description</label><input type="text" name="vaccine_description" id="vf_desc" class="form-control form-control-sm"></div>
              <div class="col-md-2"><label class="small-label mb-1">Target Age Group</label><input type="text" name="target_age_group" id="vf_tag" class="form-control form-control-sm" placeholder="e.g. 0-12m"></div>
              <div class="col-md-2"><label class="small-label mb-1">Category *</label><select name="vaccine_category" id="vf_cat" class="form-select form-select-sm" required>
                <option value="birth">birth</option><option value="infant" selected>infant</option><option value="child">child</option><option value="booster">booster</option><option value="adult">adult</option>
              </select></div>
              <div class="col-md-1"><label class="small-label mb-1">Doses *</label><input type="number" min="1" name="doses_required" id="vf_doses" class="form-control form-control-sm" required value="1"></div>
              <div class="col-md-2"><label class="small-label mb-1">Interval (days)</label><input type="number" min="0" name="interval_between_doses_days" id="vf_interval" class="form-control form-control-sm" placeholder="Optional"></div>
              <div class="col-md-2 d-grid"><label class="small-label mb-1 invisible">.</label><button class="btn btn-sm btn-primary"><i class="bi bi-save"></i> <span id="vf_btn_lbl">Add</span></button></div>
              <div class="col-md-2 d-grid"><label class="small-label mb-1 invisible">.</label><button type="button" class="btn btn-sm btn-outline-secondary" id="vf_reset"><i class="bi bi-arrow-counterclockwise"></i> Reset</button></div>
              <div class="col-12"><div id="vf_msg" class="small mt-1 text-muted">Fill fields then click Add.</div></div>
            </form>
          </div>
        </div>
        <div class="card border-0 shadow-sm">
          <div class="card-body small">
            <h6 class="fw-semibold mb-2">Vaccine Master List</h6>
            <div class="table-responsive" style="max-height:520px;">
              <table class="table table-sm table-hover table-fixed-head align-middle mb-0" id="vaxTable">
                <thead class="table-light">
                  <tr>
                    <th>Code</th><th>Name</th><th>Description</th><th>Target Age Group</th><th>Category</th><th>Doses</th><th>Interval (days)</th><th style="min-width:110px;">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  ${vaccines.length ? vaccines.map(v=>`
                    <tr data-id="${v.vaccine_id}">
                      <td class="fw-semibold">${escapeHtml(v.vaccine_code)}</td>
                      <td>${escapeHtml(v.vaccine_name)}</td>
                      <td>${escapeHtml(v.vaccine_description||'')}</td>
                      <td>${escapeHtml(v.target_age_group||'')}</td>
                      <td><span class="badge bg-info-subtle text-dark border">${escapeHtml(v.vaccine_category)}</span></td>
                      <td>${v.doses_required}</td>
                      <td>${v.interval_between_doses_days===null?'—':v.interval_between_doses_days}</td>
                      <td>
                        <button class="btn btn-sm btn-outline-primary btn-edit" data-id="${v.vaccine_id}" title="Edit"><i class="bi bi-pencil"></i></button>
                        <button class="btn btn-sm btn-outline-danger btn-del" data-id="${v.vaccine_id}" title="Delete"><i class="bi bi-trash"></i></button>
                      </td>
                    </tr>`).join('') : `<tr><td colspan="8" class="text-center text-muted small">No active vaccines.</td></tr>`}
                </tbody>
              </table>
            </div>
          </div>
        </div>`;

      const form=document.getElementById('vaccineForm');
      const msg=document.getElementById('vf_msg');
      const btnLbl=document.getElementById('vf_btn_lbl');
      const resetBtn=document.getElementById('vf_reset');
      const search=document.getElementById('vaxSearch');

      function clearForm(){
        form.reset();
        document.getElementById('vf_id').value='';
        btnLbl.textContent='Add';
        msg.className='small mt-1 text-muted';
        msg.textContent='Fill fields then click Add.';
        document.querySelectorAll('#vaxTable tbody tr').forEach(tr=>tr.classList.remove('editing-row'));
      }
      resetBtn.addEventListener('click',clearForm);

      form.addEventListener('submit',e=>{
        e.preventDefault();
        msg.className='small mt-1 text-muted';
        msg.textContent='Saving...';
        const fd=new FormData(form);
        fetch(api.immun,{method:'POST',body:fd})
          .then(r=>r.json()).then(j=>{
            if(!j.success) throw new Error(j.error||'Save failed');
            msg.className='small mt-1 text-success';
            msg.textContent=j.mode==='updated'?'Updated successfully.':'Added successfully.';
            setTimeout(()=>renderVaccineSchedule(label),350);
          }).catch(err=>{
            msg.className='small mt-1 text-danger';
            msg.textContent=err.message;
          });
      });

      document.querySelectorAll('.btn-edit').forEach(btn=>{
        btn.addEventListener('click',()=>{
          const tr=btn.closest('tr');
          document.querySelectorAll('#vaxTable tbody tr').forEach(r=>r.classList.remove('editing-row'));
          tr.classList.add('editing-row');
          document.getElementById('vf_id').value=tr.dataset.id;
          document.getElementById('vf_code').value=tr.children[0].textContent.trim();
          document.getElementById('vf_name').value=tr.children[1].textContent.trim();
          document.getElementById('vf_desc').value=tr.children[2].textContent.trim();
          document.getElementById('vf_tag').value=tr.children[3].textContent.trim();
          document.getElementById('vf_cat').value=tr.children[4].innerText.trim();
          document.getElementById('vf_doses').value=tr.children[5].textContent.trim();
          const intervalTxt=tr.children[6].textContent.trim();
          document.getElementById('vf_interval').value=intervalTxt==='—'?'':intervalTxt;
          btnLbl.textContent='Update';
          msg.className='small mt-1 text-warning';
          msg.textContent='Editing vaccine ID '+tr.dataset.id;
        });
      });

      document.querySelectorAll('.btn-del').forEach(btn=>{
        btn.addEventListener('click',()=>{
          if(!confirm('Delete this vaccine? (Must have no child immunization records)')) return;
          const fd=new FormData();
          fd.append('delete_vaccine_id', btn.dataset.id);
          fd.append('csrf_token', window.__BHW_CSRF);
          fetch(api.immun,{method:'POST',body:fd}).then(r=>r.json())
            .then(j=>{
              if(!j.success) throw new Error(j.error||'Delete failed');
              renderVaccineSchedule(label);
            }).catch(err=>alert(err.message));
        });
      });

      search.addEventListener('input',()=>{
        const q=search.value.toLowerCase();
        document.querySelectorAll('#vaxTable tbody tr').forEach(tr=>{
          if(!q){ tr.classList.remove('d-none'); return; }
          const text=tr.innerText.toLowerCase();
          tr.classList.toggle('d-none', !text.includes(q));
        });
      });
    }).catch(err=>{
      moduleContent.innerHTML=`<div class="alert alert-danger small">Error loading: ${escapeHtml(err.message)}</div>`;
    });
  }

  function renderOverdueAlerts(label){
    showLoading(label);
    fetchJSON(api.immun+'?overdue=1').then(j=>{
      moduleContent.innerHTML=`<div class="row g-3"><div class="col-12">
        <div class="card border-0 shadow-sm mb-3"><div class="card-body small">
          <h6 class="fw-semibold mb-2">${escapeHtml(label)} - Overdue</h6>
          <div class="table-responsive" style="max-height:300px; overflow:auto;">
            <table class="table table-sm table-hover mb-0">
              <thead class="table-light"><tr><th>Child</th><th>Age (m)</th><th>Vaccine</th><th>Dose</th><th>Target Age</th></tr></thead>
              <tbody>${
                j.overdue.map(o=>`<tr class="table-danger"><td>${escapeHtml(o.child_name)}</td><td>${o.age_months}</td><td>${escapeHtml(o.vaccine_code)}</td><td>${o.dose_number}</td><td>${o.target_age_months}</td></tr>`).join('')
              }${j.overdue.length===0?'<tr><td colspan="5" class="text-center small text-muted">None overdue.</td></tr>':''}</tbody>
            </table>
          </div>
        </div></div>
        <div class="card border-0 shadow-sm"><div class="card-body small">
          <h6 class="fw-semibold mb-2">Due Soon (within 1 month)</h6>
          <div class="table-responsive" style="max-height:300px; overflow:auto;">
            <table class="table table-sm table-hover mb-0">
              <thead class="table-light"><tr><th>Child</th><th>Age (m)</th><th>Vaccine</th><th>Dose</th><th>Target Age</th></tr></thead>
              <tbody>${
                j.dueSoon.map(o=>`<tr class="table-warning"><td>${escapeHtml(o.child_name)}</td><td>${o.age_months}</td><td>${escapeHtml(o.vaccine_code)}</td><td>${o.dose_number}</td><td>${o.target_age_months}</td></tr>`).join('')
              }${j.dueSoon.length===0?'<tr><td colspan="5" class="text-center small text-muted">None due soon.</td></tr>':''}</tbody>
            </table>
          </div>
        </div></div></div>`;
    }).catch(err=> moduleContent.innerHTML=`<div class="alert alert-danger small">Error: ${escapeHtml(err.message)}</div>`);
  }

function renderParentNotifications(label){
  showLoading(label);
  fetchJSON(api.notif+'?list=1').then(j=>{
    const rows = j.notifications||[];
    moduleContent.innerHTML=`<div class="card border-0 shadow-sm"><div class="card-body small">
      <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
        <h6 class="fw-semibold mb-0">${escapeHtml(label)}</h6>
        <div class="ms-auto d-flex flex-wrap gap-2">
          <select id="notifFilter" class="form-select form-select-sm" style="width:170px;">
            <option value="">All Types</option>
            <option value="vaccine_due">Vaccine Due Soon</option>
            <option value="vaccine_overdue">Vaccine Overdue</option>
            <option value="appointment_reminder">Appointment</option>
            <option value="general">General</option>
          </select>
          <button class="btn btn-sm btn-outline-secondary" id="btnRefresh" title="Refresh"><i class="bi bi-arrow-clockwise"></i></button>
          <button class="btn btn-sm btn-outline-primary" id="btnGenerate" title="Generate from schedule"><i class="bi bi-gear"></i> Generate</button>
          <button class="btn btn-sm btn-outline-success" id="btnMarkAll"><i class="bi bi-check2-all"></i> Mark All Read</button>
        </div>
      </div>
      <div id="notifStats" class="mb-2 text-muted small">${rows.length} notification(s)</div>
      <div class="table-responsive" style="max-height:520px; overflow:auto;">
        <table class="table table-sm table-hover mb-0" id="notifTable">
          <thead class="table-light">
            <tr><th>Date</th><th>Child</th><th>Parent</th><th>Type</th><th>Title</th><th>Due</th><th>Status</th><th></th></tr>
          </thead>
          <tbody>
            ${
              rows.length
                ? rows.map(n=>`
                  <tr data-type="${escapeHtml(n.notification_type)}" data-id="${n.notification_id}" class="${n.is_read?'':'table-warning'}">
                    <td><small>${n.created_at}</small></td>
                    <td>${escapeHtml(n.child_name)}</td>
                    <td>${escapeHtml(n.parent_username)}</td>
                    <td>${escapeHtml(n.notification_type)}</td>
                    <td>${escapeHtml(n.title)}</td>
                    <td>${n.due_date||'<span class="text-muted">—</span>'}</td>
                    <td>${n.is_read?'Read':'Unread'}</td>
                    <td><button class="btn btn-sm btn-outline-success btn-mark-read" ${n.is_read?'disabled':''} title="Mark read"><i class="bi bi-check"></i></button></td>
                  </tr>`).join('')
                : `<tr><td colspan="8" class="text-center small text-muted">No notifications.</td></tr>`
            }
          </tbody>
        </table>
      </div>
    </div></div>`;

    const filter = document.getElementById('notifFilter');
    filter.addEventListener('change',()=>{
      const val=filter.value;
      let visible=0;
      document.querySelectorAll('#notifTable tbody tr').forEach(tr=>{
        if(!val){ tr.classList.remove('d-none'); visible++; return; }
        const t = tr.dataset.type;
        const show = (t===val);
        tr.classList.toggle('d-none', !show);
        if(show) visible++;
      });
      document.getElementById('notifStats').textContent = visible+' notification(s)';
    });

    document.querySelectorAll('.btn-mark-read').forEach(btn=>{
      btn.addEventListener('click',()=>{
        const tr = btn.closest('tr');
        const id = tr.dataset.id;
        const fd = new FormData();
        fd.append('csrf_token',window.__BHW_CSRF);
        fd.append('mark_read',id);
        fetch(api.notif,{method:'POST',body:fd}).then(r=>r.json()).then(j=>{
          if(j.success){
            tr.classList.remove('table-warning');
            tr.cells[6].innerHTML='Read';
            btn.disabled=true;
          }
        });
      });
    });

    document.getElementById('btnRefresh').addEventListener('click',()=>renderParentNotifications(label));

    document.getElementById('btnGenerate').addEventListener('click',()=>{
      if(!confirm('Generate notifications (due + overdue) now?')) return;
      fetchJSON(api.notif+'?generate=1').then(g=>{
        alert('Generated: '+g.stats.added+' added, '+g.stats.skipped+' skipped.');
        renderParentNotifications(label);
      }).catch(err=>alert(err.message));
    });

    document.getElementById('btnMarkAll').addEventListener('click',()=>{
      if(!confirm('Mark ALL notifications as read?')) return;
      const fd=new FormData();
      fd.append('csrf_token',window.__BHW_CSRF);
      fd.append('mark_all_read','1');
      fetch(api.notif,{method:'POST',body:fd}).then(r=>r.json()).then(j=>{
        if(j.success){
          alert('Marked '+j.marked_read+' as read.');
          renderParentNotifications(label);
        }
      });
    });

  }).catch(err=>{
    moduleContent.innerHTML=`<div class="alert alert-danger small">Error: ${escapeHtml(err.message)}</div>`;
  });
}

  /* ================= Parent Accounts: Create ================= */
  function renderCreateParentAccounts(label){
    showLoading(label);
    Promise.all([
      fetchJSON(api.parent+'?children_basic=1'),
      fetchJSON(api.parent+'?list_parents=1')
    ]).then(([children, parents])=>{
      moduleContent.innerHTML=`
        <div class="row g-3">
          <div class="col-lg-4">
            <div class="card border-0 shadow-sm mb-3">
              <div class="card-body small">
                <h6 class="fw-semibold mb-3">${escapeHtml(label)}</h6>
                <form id="createParentForm">
                  <div class="mb-2"><label class="form-label small mb-1">Username *</label><input name="username" class="form-control form-control-sm" required></div>
                  <div class="mb-2"><label class="form-label small mb-1">Email</label><input type="email" name="email" class="form-control form-control-sm"></div>
                  <div class="row g-2">
                    <div class="col-6 mb-2"><label class="form-label small mb-1">First Name *</label><input name="first_name" class="form-control form-control-sm" required></div>
                    <div class="col-6 mb-2"><label class="form-label small mb-1">Last Name *</label><input name="last_name" class="form-control form-control-sm" required></div>
                  </div>
                  <div class="mb-2">
                    <label class="form-label small mb-1">Relationship *</label>
                    <select name="relationship_type" class="form-select form-select-sm" required>
                      <option value="">Select...</option>
                      <option value="mother">Mother</option>
                      <option value="father">Father</option>
                      <option value="guardian">Guardian</option>
                      <option value="caregiver">Caregiver</option>
                    </select>
                  </div>
                  <div class="mb-2">
                    <label class="form-label small mb-1">Child *</label>
                    <select name="child_id" class="form-select form-select-sm" required>
                      <option value="">Select...</option>
                      ${children.children.map(c=>`<option value="${c.child_id}">${escapeHtml(c.full_name)} (${c.age_months}m)</option>`).join('')}
                    </select>
                  </div>
                  <div class="mb-2">
                    <label class="form-label small mb-1 d-flex justify-content-between">
                      <span>Password</span><span class="text-muted">Blank = auto-generate</span>
                    </label>
                    <input type="text" name="password" class="form-control form-control-sm" placeholder="(Optional)">
                  </div>
                  <input type="hidden" name="csrf_token" value="${window.__BHW_CSRF}">
                  <input type="hidden" name="create_parent" value="1">
                  <div class="d-grid mt-2"><button class="btn btn-primary btn-sm">Create Account</button></div>
                  <div class="form-text text-success mt-2 d-none" id="parentCreateSuccess"></div>
                  <div class="form-text text-danger mt-2 d-none" id="parentCreateError"></div>
                </form>
                <div class="alert alert-info mt-3 mb-0 small">Note: Auto-generated password ibabalik dito. Ibigay sa magulang nang personal.</div>
              </div>
            </div>
          </div>
          <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
              <div class="card-body small">
                <h6 class="fw-semibold mb-3">Existing Parent Accounts</h6>
                <div class="table-responsive" style="max-height:520px; overflow:auto;">
                  <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light">
                      <tr><th>User</th><th>Name</th><th>Children</th><th>Active</th><th>Created</th></tr>
                    </thead>
                    <tbody>
                      ${parents.parents.map(p=>`
                        <tr>
                          <td class="fw-semibold">${escapeHtml(p.username)}</td>
                          <td>${escapeHtml(p.first_name+' '+p.last_name)}</td>
                          <td><small>${p.children_list?escapeHtml(p.children_list):'<span class="text-muted">—</span>'}</small></td>
                          <td>${p.is_active?'<span class="badge bg-success">Yes</span>':'<span class="badge bg-secondary">No</span>'}</td>
                          <td><small>${p.created_at}</small></td>
                        </tr>`).join('')}
                      ${parents.parents.length===0?'<tr><td colspan="5" class="text-center small text-muted">No parent accounts yet.</td></tr>':''}
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        </div>`;
      document.getElementById('createParentForm').addEventListener('submit',e=>{
        e.preventDefault();
        const fd=new FormData(e.target);
        fetch(api.parent,{method:'POST',body:fd})
          .then(r=>r.json())
          .then(j=>{
            if(!j.success) throw new Error(j.error||'Error');
            const ok=document.getElementById('parentCreateSuccess');
            ok.innerHTML='Created! Username: <strong>'+escapeHtml(j.username)+'</strong>'+(j.auto_generated_password?'<br>Auto Password: <span class="generated-pass">'+escapeHtml(j.auto_generated_password)+'</span>':'');
            ok.classList.remove('d-none');
            document.getElementById('parentCreateError').classList.add('d-none');
            e.target.reset();
            renderCreateParentAccounts(label);
          }).catch(err=>{
            const el=document.getElementById('parentCreateError');
            el.textContent=err.message;
            el.classList.remove('d-none');
          });
      });
    }).catch(err=>{
      moduleContent.innerHTML=`<div class="alert alert-danger small">Error: ${escapeHtml(err.message)}</div>`;
    });
  }

   /* ================= Link Child to Parent ================= */
  function renderLinkChildParent(label){
    showLoading(label);
    Promise.all([
      fetchJSON(api.parent+'?list_parents=1'),
      fetchJSON(api.parent+'?children_basic=1'),
      fetchJSON(api.parent+'?links=1')
    ]).then(([parents,children,links])=>{
      moduleContent.innerHTML=`
        <div class="row g-3">
          <div class="col-lg-4">
            <div class="card border-0 shadow-sm mb-3">
              <div class="card-body small">
                <h6 class="fw-semibold mb-3">${escapeHtml(label)}</h6>
                <form id="linkForm">
                  <div class="mb-2"><label class="form-label small mb-1">Parent *</label>
                    <select name="parent_user_id" class="form-select form-select-sm" required>
                      <option value="">Select...</option>
                      ${parents.parents.map(p=>`<option value="${p.user_id}">${escapeHtml(p.username)} (${p.children_count})</option>`).join('')}
                    </select>
                  </div>
                  <div class="mb-2"><label class="form-label small mb-1">Child *</label>
                    <select name="child_id" class="form-select form-select-sm" required>
                      <option value="">Select...</option>
                      ${children.children.map(c=>`<option value="${c.child_id}">${escapeHtml(c.full_name)} (${c.age_months}m)</option>`).join('')}
                    </select>
                  </div>
                  <div class="mb-2">
                    <label class="form-label small mb-1">Relationship *</label>
                    <select name="relationship_type" class="form-select form-select-sm" required>
                      <option value="">Select...</option>
                      <option value="mother">Mother</option>
                      <option value="father">Father</option>
                      <option value="guardian">Guardian</option>
                      <option value="caregiver">Caregiver</option>
                    </select>
                  </div>
                  <input type="hidden" name="csrf_token" value="${window.__BHW_CSRF}">
                  <input type="hidden" name="link_child" value="1">
                  <div class="d-grid"><button class="btn btn-primary btn-sm">Link</button></div>
                  <div class="form-text text-success mt-2 d-none" id="linkSuccess">Linked!</div>
                  <div class="form-text text-danger mt-2 d-none" id="linkError"></div>
                </form>
                <div class="alert alert-info small mt-3 mb-0">Kapag existing ngunit inactive, mare-reactivate.</div>
              </div>
            </div>
          </div>
          <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
              <div class="card-body small">
                <h6 class="fw-semibold mb-3">Parent-Child Links</h6>
                <div class="table-responsive" style="max-height:520px; overflow:auto;">
                  <table class="table table-sm table-hover mb-0 align-middle">
                    <thead class="table-light">
                      <tr><th>Parent</th><th>Child</th><th>Relationship</th><th>Active</th><th>Granted</th><th></th></tr>
                    </thead>
                    <tbody>
                      ${links.links.map(l=>`
                        <tr>
                          <td>${escapeHtml(l.parent_username)}</td>
                          <td>${escapeHtml(l.child_name)}</td>
                          <td>${escapeHtml(l.relationship_type)}</td>
                          <td>${l.is_active?'<span class="badge bg-success">Yes</span>':'<span class="badge bg-secondary">No</span>'}</td>
                          <td><small>${l.granted_date}</small></td>
                          <td>
                            ${l.is_active?`<button class="btn btn-sm btn-outline-danger btn-unlink" data-id="${l.access_id}"><i class="bi bi-x"></i></button>`:''}
                          </td>
                        </tr>`).join('')}
                      ${links.links.length===0?'<tr><td colspan="6" class="text-center small text-muted">No links yet.</td></tr>':''}
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        </div>`;
      document.getElementById('linkForm').addEventListener('submit',e=>{
        e.preventDefault();
        const fd=new FormData(e.target);
        fetch(api.parent,{method:'POST',body:fd})
          .then(r=>r.json()).then(j=>{
            if(!j.success) throw new Error(j.error||'Error');
            document.getElementById('linkSuccess').classList.remove('d-none');
            document.getElementById('linkError').classList.add('d-none');
            e.target.reset();
            renderLinkChildParent(label);
          }).catch(err=>{
            const el=document.getElementById('linkError');
            el.textContent=err.message;
            el.classList.remove('d-none');
          });
      });
      document.querySelectorAll('.btn-unlink').forEach(btn=>{
        btn.addEventListener('click',()=>{
          if(!confirm('Unlink this child?')) return;
          const fd=new FormData();
            fd.append('csrf_token',window.__BHW_CSRF);
            fd.append('unlink_access_id',btn.dataset.id);
          fetch(api.parent,{method:'POST',body:fd})
            .then(r=>r.json()).then(j=>{
              if(j.success) renderLinkChildParent(label);
            }).catch(err=>alert(err.message));
        });
      });
    }).catch(err=>{
      moduleContent.innerHTML=`<div class="alert alert-danger small">Error: ${escapeHtml(err.message)}</div>`;
    });
  }

    /* ================= Access Credential Management ================= */
  function renderAccessCredentials(label){
    showLoading(label);
    fetchJSON(api.parent+'?list_parents=1').then(par=>{
      moduleContent.innerHTML=`
        <div class="card border-0 shadow-sm">
          <div class="card-body small">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <h6 class="fw-semibold mb-0">${escapeHtml(label)}</h6>
              <button class="btn btn-sm btn-outline-secondary" id="refreshParents"><i class="bi bi-arrow-clockwise"></i></button>
            </div>
            <div id="credMsg" class="small text-muted mb-2">Manage passwords & status.</div>
            <div class="table-responsive" style="max-height:560px; overflow:auto;">
              <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light">
                  <tr><th>User</th><th>Name</th><th>Children</th><th>Status</th><th>Created</th><th style="min-width:180px;">Actions</th></tr>
                </thead>
                <tbody id="credBody">
                  ${par.parents.map(p=>`
                    <tr data-id="${p.user_id}">
                      <td class="fw-semibold">${escapeHtml(p.username)}</td>
                      <td>${escapeHtml(p.first_name+' '+p.last_name)}</td>
                      <td><small>${p.children_list?escapeHtml(p.children_list):'<span class="text-muted">—</span>'}</small></td>
                      <td>${p.is_active?'<span class="badge bg-success">Active</span>':'<span class="badge bg-secondary">Inactive</span>'}</td>
                      <td><small>${p.created_at}</small></td>
                      <td>
                        <div class="btn-group btn-group-sm">
                          <button class="btn btn-outline-primary btn-reset" title="Reset Password"><i class="bi bi-key"></i></button>
                          <button class="btn btn-outline-warning btn-toggle" title="Toggle Active"><i class="bi bi-power"></i></button>
                        </div>
                        <div class="small mt-1 text-success d-none pass-display"></div>
                      </td>
                    </tr>`).join('')}
                  ${par.parents.length===0?'<tr><td colspan="6" class="text-center small text-muted">None.</td></tr>':''}
                </tbody>
              </table>
            </div>
          </div>
        </div>`;
      document.getElementById('refreshParents').addEventListener('click',()=>renderAccessCredentials(label));
      document.querySelectorAll('.btn-reset').forEach(btn=>{
        btn.addEventListener('click',()=>{
          if(!confirm('Reset password for this parent?')) return;
          const tr=btn.closest('tr');
          const id=tr.dataset.id;
          const fd=new FormData();
          fd.append('csrf_token',window.__BHW_CSRF);
          fd.append('reset_password',id);
          fetch(api.parent,{method:'POST',body:fd})
            .then(r=>r.json()).then(j=>{
              if(!j.success) throw new Error(j.error||'Error');
              const disp=tr.querySelector('.pass-display');
              disp.textContent='New: '+j.new_password;
              disp.classList.remove('d-none');
            }).catch(err=>alert(err.message));
        });
      });
      document.querySelectorAll('.btn-toggle').forEach(btn=>{
        btn.addEventListener('click',()=>{
          if(!confirm('Toggle active status?')) return;
          const tr=btn.closest('tr');
          const id=tr.dataset.id;
          const fd=new FormData();
          fd.append('csrf_token',window.__BHW_CSRF);
          fd.append('toggle_active',id);
          fetch(api.parent,{method:'POST',body:fd})
            .then(r=>r.json()).then(j=>{
              if(j.success) renderAccessCredentials(label);
            }).catch(err=>alert(err.message));
        });
      });
    }).catch(err=>{
      moduleContent.innerHTML=`<div class="alert alert-danger small">Error: ${escapeHtml(err.message)}</div>`;
    });
  }

    /* ================= Account Activity Tracking ================= */
  function renderAccountActivity(label){
    showLoading(label);
    fetchJSON(api.parent+'?activity=1').then(act=>{
      moduleContent.innerHTML=`
        <div class="card border-0 shadow-sm">
          <div class="card-body small">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <h6 class="fw-semibold mb-0">${escapeHtml(label)}</h6>
              <button class="btn btn-sm btn-outline-secondary" id="btnRefreshAct"><i class="bi bi-arrow-clockwise"></i></button>
            </div>
            <p class="text-muted mb-2 small">Summary of parent engagement (based on notifications & linked children).</p>
            <div class="table-responsive" style="max-height:560px; overflow:auto;">
              <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light">
                  <tr><th>Parent</th><th>Children</th><th>Total Notifs</th><th>Unread</th><th>Last Notification</th></tr>
                </thead>
                <tbody>
                  ${act.activity.map(a=>`
                    <tr>
                      <td class="fw-semibold">${escapeHtml(a.username)}</td>
                      <td>${a.children_count}</td>
                      <td>${a.total_notifications}</td>
                      <td>${a.unread_notifications>0?'<span class="badge bg-warning text-dark">'+a.unread_notifications+'</span>':'0'}</td>
                      <td><small>${a.last_notification_date||'<span class="text-muted">—</span>'}</small></td>
                    </tr>`).join('')}
                  ${act.activity.length===0?'<tr><td colspan="5" class="text-center small text-muted">No parent accounts.</td></tr>':''}
                </tbody>
              </table>
            </div>
          </div>
        </div>`;
      document.getElementById('btnRefreshAct').addEventListener('click',()=>renderAccountActivity(label));
    }).catch(err=>{
      moduleContent.innerHTML=`<div class="alert alert-danger small">Error: ${escapeHtml(err.message)}</div>`;
    });
  }

  function placeholderModule(label){
    moduleContent.innerHTML=`<div class="card border-0 shadow-sm"><div class="card-body small"><h6 class="fw-semibold mb-2">${escapeHtml(label)}</h6><p class="text-muted mb-0">Implementation pending.</p></div></div>`;
  }

  const moduleHandlers = {
    mother_registration: renderMotherRegistration,
    prenatal_consultations: renderPrenatalConsultations,
    health_risk_assessment: renderRiskAssessment,
    vaccination_entry: renderVaccinationEntry,
    immunization_card: renderImmunizationCard,
    vaccine_schedule: renderVaccineSchedule,
    overdue_alerts: renderOverdueAlerts,
    parent_notifications: renderParentNotifications,
    create_parent_accounts: renderCreateParentAccounts,
    link_child_parent: renderLinkChildParent,
    access_credentials: renderAccessCredentials,
    account_activity: renderAccountActivity
  };


  function loadModule(mod,label){
    titleEl.textContent=label;
    if(mod==='dashboard_home'){ moduleContent.innerHTML=simplePlaceholders.dashboard_home.html; return; }
    if(moduleHandlers[mod]) moduleHandlers[mod](label);
    else moduleContent.innerHTML=`<div class="card border-0 shadow-sm"><div class="card-body small"><h6 class="fw-semibold mb-2">${escapeHtml(label)}</h6><p class="text-muted mb-0">Implementation pending.</p></div></div>`;
    moduleContent.scrollTop=0;
  }

  document.querySelectorAll('.nav-menu a[data-module]').forEach(a=>{
    a.addEventListener('click',e=>{
      e.preventDefault();
      setActive(a);
      loadModule(a.dataset.module,a.dataset.label);
      if(window.innerWidth<992) document.getElementById('sidebar').classList.remove('show');
    });
  });

  const sidebar=document.getElementById('sidebar');
  document.getElementById('sidebarToggle').addEventListener('click',()=>sidebar.classList.toggle('show'));
  document.getElementById('closeSidebar').addEventListener('click',()=>sidebar.classList.remove('show'));
  document.addEventListener('click',e=>{
    if(window.innerWidth>=992) return;
    if(sidebar.classList.contains('show') && !sidebar.contains(e.target) && !e.target.closest('#sidebarToggle')){
      sidebar.classList.remove('show');
    }
  });

  // Initial load
  loadModule('dashboard_home','Dashboard');
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>