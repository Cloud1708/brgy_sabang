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
  .sidebar { width:275px; flex:0 0 275px; background:#0d3042; color:#e6f2f7; display:flex; flex-direction:column; position:relative; overflow-y:auto; overflow-x:hidden; scrollbar-width:thin; scrollbar-color:#1996c6 #0d3042; }
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
  .nav-menu li a .bi { font-size:.95rem; opacity:.85; }
  .nav-menu li a:hover { background:rgba(255,255,255,.09); color:#fff; }
  .nav-menu li a.active { background:linear-gradient(90deg,#1ca3d833,#1ca3d814); border-left-color:#1ca3d8; color:#fff; font-weight:600; }
  .nav-menu + .menu-section-title { border-top:1px solid rgba(255,255,255,.08); margin-top:.3rem; }
  .sidebar-footer { margin-top:auto; padding:.75rem 1rem .95rem; font-size:.6rem; opacity:.55; }
  .content-area { flex:1; min-width:0; display:flex; flex-direction:column; overflow:hidden; position:relative; }
  .topbar { background:#ffffff; border-bottom:1px solid #dbe5ec; padding:.6rem 1.3rem; display:flex; align-items:center; gap:1rem; flex-shrink:0; }
  .page-title { font-size:.92rem; font-weight:600; margin:0; color:#0f3a51; }
  .badge-role { background:#1ca3d8; }
  main { flex:1; display:flex; flex-direction:column; overflow:hidden; }
  /* CHANGE: allow vertical scroll in module content */
  #moduleContent {
    flex:1;
    overflow:auto;
    -webkit-overflow-scrolling:touch;
    padding-right:.25rem;
  }
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
    .sidebar { position:fixed; z-index:1045; transform:translateX(-100%); transition:.33s cubic-bezier(.4,.0,.2,1); top:0; left:0; bottom:0; box-shadow:0 0 0 400vmax rgba(0,0,0,0);}
    .sidebar.show { transform:translateX(0); box-shadow:0 0 0 400vmax rgba(0,0,0,.35); }
    .topbar { position:fixed; top:0; left:0; right:0; z-index:1020; }
    .content-area { padding-top:56px; }
  }
  .risk-flag { font-size:.6rem; margin:2px 2px; }
  .ga-badge { font-size:.6rem; }
  /* Optional: keep form panel from stretching too wide on very large screens */
  .prenatal-form-card { position:relative; }
</style>
</head>
<body class="dashboard-body">
<div class="layout-wrapper">
  <aside class="sidebar" id="sidebar">
    <button class="mobile-close" id="closeSidebar" aria-label="Close sidebar">
      <i class="bi bi-x-lg"></i>
    </button>
    <div class="brand">
      <div class="emoji">👩‍⚕️</div>
      <div>
        Barangay Health Worker (BHW)
        <small>Health &amp; Immunization Focused</small>
      </div>
    </div>

    <div class="menu-section-title">Overview</div>
    <ul class="nav-menu">
      <li><a href="#" class="active" data-module="dashboard_home" data-label="Dashboard"><i class="bi bi-house-door"></i><span>🏠 Dashboard</span></a></li>
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
    <ul class="nav-menu">
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
      Barangay Health System<br>
      <span class="text-white-50">&copy; <?php echo date('Y'); ?></span>
    </div>
  </aside>

  <div class="content-area">
    <div class="topbar">
      <button class="btn btn-outline-primary btn-sm d-lg-none" id="sidebarToggle">
        <i class="bi bi-list"></i>
      </button>
      <h1 class="page-title mb-0" id="currentModuleTitle">Dashboard</h1>
      <span class="badge badge-role ms-auto d-none d-lg-inline"><i class="bi bi-person-badge me-1"></i> BHW</span>
    </div>
    <main class="p-4">
      <div id="moduleContent">
        <div class="module-hint">
          Piliin ang isang module para mag-encode o mag-manage ng maternal health at immunization data.
        </div>
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
    health: 'bhw_modules/api_health_records.php'
  };

  function escapeHtml(str){
    if(str===undefined||str===null) return '';
    return str.toString().replace(/&/g,'&amp;').replace(/</g,'&lt;')
      .replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
  }
  function setActive(link){
    document.querySelectorAll('.nav-menu a.active').forEach(a=>a.classList.remove('active'));
    link.classList.add('active');
  }
  function showLoading(label){
    moduleContent.innerHTML = `
      <div class="loading-state">
        <div class="spinner-border text-primary mb-3"></div>
        <div class="small">Loading ${escapeHtml(label)}...</div>
      </div>`;
  }
  function fetchJSON(url, options = {}){
    options.headers = Object.assign({'X-Requested-With':'fetch'}, options.headers||{});
    return fetch(url, options).then(r=>{ if(!r.ok) throw new Error('HTTP '+r.status); return r.json(); });
  }

  const simplePlaceholders = {
    dashboard_home: {
      html: `<div class="row g-3">
        <div class="col-md-6 col-xl-3">
          <div class="card shadow-sm border-0"><div class="card-body small">
            <div class="fw-semibold mb-1">Maternal Records</div>
            <div class="display-6 fw-bold" id="statMaternal">—</div>
            <div class="text-secondary mt-1">Total consultations</div>
          </div></div>
        </div>
        <div class="col-md-6 col-xl-3">
          <div class="card shadow-sm border-0"><div class="card-body small">
            <div class="fw-semibold mb-1">Registered Mothers</div>
            <div class="display-6 fw-bold" id="statMothers">—</div>
            <div class="text-secondary mt-1">Profiles</div>
          </div></div>
        </div>
        <div class="col-md-6 col-xl-3">
          <div class="card shadow-sm border-0"><div class="card-body small">
            <div class="fw-semibold mb-1">Active Risks</div>
            <div class="display-6 fw-bold" id="statRisks">—</div>
            <div class="text-secondary mt-1">Latest flagged</div>
          </div></div>
        </div>
        <div class="col-md-6 col-xl-3">
          <div class="card shadow-sm border-0"><div class="card-body small">
            <div class="fw-semibold mb-1">Today</div>
            <div class="display-6 fw-bold">—</div>
            <div class="text-secondary mt-1">New entries</div>
          </div></div>
        </div>
        <div class="col-12">
          <div class="card border-0 shadow-sm">
            <div class="card-body small">
              <h6 class="fw-semibold mb-3">Quick Tips</h6>
              <ul class="small ps-3 mb-0">
                <li>I-record agad ang prenatal visit pagkatapos ng konsultasyon.</li>
                <li>Gamitin ang risk module para sa agarang follow-up.</li>
                <li>Siguraduhin ang tamang LMP at EDD para accurate na pregnancy age.</li>
              </ul>
            </div>
          </div>
        </div>
      </div>`
    }
  };

  function renderMotherRegistration(label){
    showLoading(label);
    fetchJSON(api.mothers+'?list=1')
      .then(data=>{
        moduleContent.innerHTML = `
          <div class="row g-3">
            <div class="col-lg-4">
              <div class="card shadow-sm border-0 mb-3">
                <div class="card-body small">
                  <h6 class="fw-semibold mb-3">Add Mother / Caregiver</h6>
                  <form id="motherForm">
                    <div class="mb-2">
                      <label class="form-label small mb-1">Full Name</label>
                      <input type="text" name="full_name" class="form-control form-control-sm" required>
                    </div>
                    <div class="mb-2">
                      <label class="form-label small mb-1">Purok Name</label>
                      <input type="text" name="purok_name" class="form-control form-control-sm" required placeholder="Hal: Purok 1">
                      <div class="form-text">Auto-add kapag bago.</div>
                    </div>
                    <div class="mb-2">
                      <label class="form-label small mb-1">Address Details</label>
                      <textarea name="address_details" rows="2" class="form-control form-control-sm"></textarea>
                    </div>
                    <div class="mb-2">
                      <label class="form-label small mb-1">Contact Number</label>
                      <input type="text" name="contact_number" class="form-control form-control-sm">
                    </div>
                    <input type="hidden" name="csrf_token" value="${window.__BHW_CSRF}">
                    <div class="d-grid">
                      <button class="btn btn-primary btn-sm">Save</button>
                    </div>
                    <div class="form-text text-success mt-1 d-none" id="motherSuccess">Saved!</div>
                    <div class="form-text text-danger mt-1 d-none" id="motherError"></div>
                  </form>
                </div>
              </div>
              <div class="alert alert-info small mb-0">Tip: Iwasan ang dobleng pangalan. I-check muna sa list bago mag-add.</div>
            </div>
            <div class="col-lg-8">
              <div class="card shadow-sm border-0">
                <div class="card-body small">
                  <h6 class="fw-semibold mb-3">Mothers / Caregivers</h6>
                  <div class="table-responsive" style="max-height:470px; overflow:auto;">
                    <table class="table table-sm table-hover mb-0 align-middle">
                      <thead class="table-light">
                        <tr>
                          <th>Name</th><th>Purok</th><th>Contact</th><th>Records</th><th>Last Consult</th><th>Risks</th>
                        </tr>
                      </thead>
                      <tbody>
                        ${data.mothers.map(m=>`
                          <tr>
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
                </div>
              </div>
            </div>
          </div>`;
        document.getElementById('motherForm').addEventListener('submit', e=>{
          e.preventDefault();
          const fd = new FormData(e.target);
          fetch(api.mothers,{method:'POST',body:fd})
            .then(r=>r.json())
            .then(j=>{
              if(!j.success) throw new Error(j.error||'Error');
              document.getElementById('motherSuccess').classList.remove('d-none');
              document.getElementById('motherError').classList.add('d-none');
              e.target.reset();
              renderMotherRegistration(label);
            })
            .catch(err=>{
              const el=document.getElementById('motherError');
              el.textContent=err.message;
              el.classList.remove('d-none');
            });
        });
      })
      .catch(err=>{
        moduleContent.innerHTML = `<div class="alert alert-danger small">Error: ${escapeHtml(err.message)}</div>`;
      });
  }

  function computePregWeeks(consultDateStr, lmpStr, eddStr) {
    if (!consultDateStr) return '';
    const consult = new Date(consultDateStr+'T00:00:00');
    if (lmpStr) {
      const lmp = new Date(lmpStr+'T00:00:00');
      const diffDays = Math.floor((consult - lmp)/86400000);
      if (diffDays < 0) return '';
      return Math.floor(diffDays / 7);
    }
    if (eddStr) {
      const edd = new Date(eddStr+'T00:00:00');
      const diffToEddDays = Math.floor((edd - consult)/86400000);
      const weeksToEdd = diffToEddDays / 7;
      return Math.round(40 - weeksToEdd);
    }
    return '';
  }

  function renderPrenatalConsultations(label){
    showLoading(label);
    Promise.all([ fetchJSON(api.mothers+'?list_basic=1') ])
      .then(([mothers])=>{
        moduleContent.innerHTML = `
          <div class="row g-3 prenatal-wrapper">
            <div class="col-xl-4">
              <div class="card shadow-sm border-0 mb-3 prenatal-form-card">
                <div class="card-body small">
                  <h6 class="fw-semibold mb-3">Add Consultation</h6>
                  <form id="consultForm">
                    <div class="mb-2">
                      <label class="form-label small mb-1">Mother</label>
                      <select name="mother_id" class="form-select form-select-sm" required id="motherSelect">
                        <option value="">Select...</option>
                        ${mothers.mothers.map(m=>`<option value="${m.mother_id}">${escapeHtml(m.full_name)}</option>`).join('')}
                      </select>
                    </div>
                    <div class="mb-2">
                      <label class="form-label small mb-1">Consultation Date</label>
                      <input type="date" name="consultation_date" id="consultDate" class="form-control form-control-sm" required value="<?= date('Y-m-d'); ?>">
                    </div>
                    <div class="row g-2">
                      <div class="col-4">
                        <label class="form-label small mb-1">Age</label>
                        <input type="number" name="age" class="form-control form-control-sm" min="10">
                      </div>
                      <div class="col-4">
                        <label class="form-label small mb-1">Ht (cm)</label>
                        <input type="number" step="0.1" name="height_cm" class="form-control form-control-sm">
                      </div>
                      <div class="col-4">
                        <label class="form-label small mb-1">Wt (kg)</label>
                        <input type="number" step="0.1" name="weight_kg" class="form-control form-control-sm">
                      </div>
                    </div>
                    <div class="row g-2 mt-1">
                      <div class="col-6">
                        <label class="form-label small mb-1">BP Sys</label>
                        <input type="number" name="blood_pressure_systolic" class="form-control form-control-sm">
                      </div>
                      <div class="col-6">
                        <label class="form-label small mb-1">BP Dia</label>
                        <input type="number" name="blood_pressure_diastolic" class="form-control form-control-sm">
                      </div>
                    </div>
                    <div class="row g-2 mt-1">
                      <div class="col-6">
                        <label class="form-label small mb-1">LMP</label>
                        <input type="date" name="last_menstruation_date" id="lmpDate" class="form-control form-control-sm">
                      </div>
                      <div class="col-6">
                        <label class="form-label small mb-1">EDD</label>
                        <input type="date" name="expected_delivery_date" id="eddDate" class="form-control form-control-sm">
                      </div>
                    </div>
                    <div class="mt-1">
                      <label class="form-label small mb-1 d-flex align-items-center justify-content-between">
                        <span>Pregnancy Age (weeks)</span>
                        <span class="text-muted small" id="gaAutoNote"></span>
                      </label>
                      <input type="number" name="pregnancy_age_weeks" id="pregWeeks" class="form-control form-control-sm" placeholder="Auto">
                      <div class="form-text">Auto-filled mula LMP o EDD; puwede i-override.</div>
                    </div>
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
                        ${[
                          ['vaginal_bleeding','Vaginal Bleeding'],
                          ['urinary_infection','Urinary Infection'],
                          ['fever_38_celsius','Fever ≥38°C'],
                          ['pallor','Pallor'],
                          ['abnormal_abdominal_size','Abnormal Abd Size'],
                          ['abnormal_presentation','Abnormal Presentation'],
                          ['absent_fetal_heartbeat','No Fetal Heartbeat'],
                          ['swelling','Swelling'],
                          ['vaginal_infection','Vaginal Infection']
                        ].map(f=>`
                          <div class="col-6">
                            <label class="form-check mb-0">
                              <input type="checkbox" class="form-check-input" name="${f[0]}">
                              <span class="form-check-label">${f[1]}</span>
                            </label>
                          </div>`).join('')}
                      </div>
                      <div class="form-text">High BP auto-flag kapag systolic ≥140 o diastolic ≥90.</div>
                    </div>
                    <input type="hidden" name="csrf_token" value="${window.__BHW_CSRF}">
                    <div class="d-grid mt-3">
                      <button class="btn btn-primary btn-sm">Save Consultation</button>
                    </div>
                    <div class="form-text text-success mt-1 d-none" id="consultSuccess">Saved!</div>
                    <div class="form-text text-danger mt-1 d-none" id="consultError"></div>
                  </form>
                </div>
              </div>
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

        const form = document.getElementById('consultForm');
        const motherSelect = document.getElementById('motherSelect');
        const consultDate = document.getElementById('consultDate');
        const lmpDate = document.getElementById('lmpDate');
        const eddDate = document.getElementById('eddDate');
        const pregWeeks = document.getElementById('pregWeeks');
        const gaNote = document.getElementById('gaAutoNote');

        function refreshGA(){
          const val = computePregWeeks(consultDate.value, lmpDate.value, eddDate.value);
          if (val !== '' && (pregWeeks.value === '' || pregWeeks.dataset.autofill === '1')) {
            pregWeeks.value = val;
            pregWeeks.dataset.autofill = '1';
            gaNote.textContent = 'auto';
          } else if (val === '') {
            if (pregWeeks.dataset.autofill === '1') pregWeeks.value = '';
            gaNote.textContent = '';
          }
        }
        [consultDate,lmpDate,eddDate].forEach(el=> el.addEventListener('change', refreshGA));
        pregWeeks.addEventListener('input', ()=>{ pregWeeks.dataset.autofill='0'; gaNote.textContent='manual'; });

        motherSelect.addEventListener('change', ()=>{
          const id = motherSelect.value;
          if(!id){
            document.getElementById('consultHistory').innerHTML =
              `<div class="card border-0 shadow-sm"><div class="card-body small"><h6 class="fw-semibold mb-2">Consultation History</h6><p class="text-muted mb-0">Wala pang napiling mother.</p></div></div>`;
            return;
          }
          loadConsultations(id);
        });

        form.addEventListener('submit', e=>{
          e.preventDefault();
          const fd = new FormData(form);
          fetch(api.health,{method:'POST',body:fd})
            .then(r=>r.json())
            .then(j=>{
              if(!j.success) throw new Error(j.error||'Error');
              document.getElementById('consultSuccess').classList.remove('d-none');
              document.getElementById('consultError').classList.add('d-none');
              const mid = motherSelect.value;
              if (mid) loadConsultations(mid);
              form.reset();
              pregWeeks.dataset.autofill='0';
              gaNote.textContent='';
            })
            .catch(err=>{
              const el=document.getElementById('consultError');
              el.textContent = err.message;
              el.classList.remove('d-none');
            });
        });
      })
      .catch(err=>{
        moduleContent.innerHTML = `<div class="alert alert-danger small">Error: ${escapeHtml(err.message)}</div>`;
      });
  }

  function loadConsultations(mother_id){
    const container = document.getElementById('consultHistory');
    container.innerHTML = `
      <div class="card border-0 shadow-sm">
        <div class="card-body small">
          <h6 class="fw-semibold mb-2">Consultation History</h6>
          <div class="text-center py-4">
            <div class="spinner-border text-primary"></div>
            <div class="small mt-2">Loading records...</div>
          </div>
        </div>
      </div>`;
    fetchJSON(api.health+'?list=1&mother_id='+mother_id)
      .then(j=>{
        container.innerHTML = `
          <div class="card border-0 shadow-sm h-100">
            <div class="card-body small d-flex flex-column">
              <h6 class="fw-semibold mb-3">Consultation History</h6>
              <div class="table-responsive" style="max-height:520px; overflow:auto;">
                <table class="table table-sm table-hover mb-0">
                  <thead class="table-light">
                    <tr>
                      <th>Date</th><th>GA</th><th>Ht</th><th>Wt</th><th>BP</th><th>Risks</th><th>Labs</th>
                    </tr>
                  </thead>
                  <tbody>
                    ${j.records.map(r=>{
                      const risks = [];
                      const riskMap = {
                        vaginal_bleeding:'VB', urinary_infection:'UI', high_blood_pressure:'HBP',
                        fever_38_celsius:'FEV', pallor:'PAL', abnormal_abdominal_size:'ABD',
                        abnormal_presentation:'PRES', absent_fetal_heartbeat:'FHT', swelling:'SWL',
                        vaginal_infection:'VAG'
                      };
                      Object.keys(riskMap).forEach(k=>{
                        if(r[k]==1) risks.push(`<span class="badge text-bg-danger-subtle text-danger border-0 risk-flag">${riskMap[k]}</span>`);
                      });
                      let rowClass = '';
                      let gaBadge = '';
                      if (r.pregnancy_age_weeks !== null) {
                        const ga = parseInt(r.pregnancy_age_weeks,10);
                        if (ga >= 37) { rowClass='table-danger'; gaBadge=`<span class="badge bg-danger ga-badge">${ga}w</span>`; }
                        else if (ga >= 34) { rowClass='table-warning'; gaBadge=`<span class="badge bg-warning text-dark ga-badge">${ga}w</span>`; }
                        else gaBadge=`<span class="badge bg-secondary-subtle text-dark ga-badge">${ga}w</span>`;
                      } else gaBadge = '<span class="text-muted">—</span>';
                      return `
                        <tr class="${rowClass}">
                          <td><small>${r.consultation_date}</small></td>
                          <td>${gaBadge}</td>
                          <td>${r.height_cm??''}</td>
                          <td>${r.weight_kg??''}</td>
                          <td>${(r.blood_pressure_systolic||'') && (r.blood_pressure_diastolic||'') ? `${r.blood_pressure_systolic}/${r.blood_pressure_diastolic}`:''}</td>
                          <td>${risks.join('')||'<span class="text-muted">None</span>'}</td>
                          <td><small>${escapeHtml(r.hgb_result||'')}</small></td>
                        </tr>`;
                    }).join('')}
                    ${j.records.length===0?'<tr><td colspan="7" class="text-center small text-muted">No records.</td></tr>':''}
                  </tbody>
                </table>
              </div>
            </div>
          </div>`;
      })
      .catch(err=>{
        container.innerHTML = `<div class="alert alert-danger small mb-0">Error loading: ${escapeHtml(err.message)}</div>`;
      });
  }

  function renderRiskAssessment(label){
    showLoading(label);
    fetchJSON(api.health+'?risk_summary=1')
      .then(j=>{
        moduleContent.innerHTML = `
          <div class="card border-0 shadow-sm">
            <div class="card-body small">
              <h6 class="fw-semibold mb-3">${escapeHtml(label)}</h6>
              <p class="text-muted mb-3">Latest consultation per mother na may active risk flags.</p>
              <div class="table-responsive" style="max-height:520px; overflow:auto;">
                <table class="table table-sm table-hover mb-0 align-middle">
                  <thead class="table-light">
                    <tr>
                      <th>Mother</th><th>Date</th><th>GA</th><th>Risk Flags</th><th>Score</th>
                    </tr>
                  </thead>
                  <tbody>
                    ${j.risks.map(r=>{
                      const flags=[];
                      const map = {
                        vaginal_bleeding:'Vaginal Bleeding', urinary_infection:'Urinary Infection', high_blood_pressure:'High BP',
                        fever_38_celsius:'Fever ≥38°C', pallor:'Pallor', abnormal_abdominal_size:'Abnormal Abd Size',
                        abnormal_presentation:'Abnormal Presentation', absent_fetal_heartbeat:'No Fetal Heartbeat',
                        swelling:'Swelling', vaginal_infection:'Vaginal Infection'
                      };
                      Object.keys(map).forEach(k=>{ if(r[k]==1) flags.push(`<span class="badge rounded-pill text-bg-danger-subtle text-danger border-0 me-1 mb-1">${map[k]}</span>`); });
                      let gaCell = '<span class="text-muted">—</span>';
                      if (r.pregnancy_age_weeks !== null) {
                        const ga = parseInt(r.pregnancy_age_weeks,10);
                        if (ga >= 37) gaCell=`<span class="badge bg-danger">${ga}w</span>`;
                        else if (ga >= 34) gaCell=`<span class="badge bg-warning text-dark">${ga}w</span>`;
                        else gaCell=`<span class="badge bg-secondary-subtle text-dark">${ga}w</span>`;
                      }
                      return `
                        <tr>
                          <td>${escapeHtml(r.full_name)}</td>
                          <td><small>${r.consultation_date}</small></td>
                          <td>${gaCell}</td>
                          <td style="max-width:340px;">${flags.join('') || '<span class="text-muted">None</span>'}</td>
                          <td><span class="badge bg-danger">${r.risk_score}</span></td>
                        </tr>`;
                    }).join('')}
                    ${j.risks.length===0?'<tr><td colspan="5" class="text-center small text-muted">No active risk flags.</td></tr>':''}
                  </tbody>
                </table>
              </div>
            </div>
          </div>`;
      })
      .catch(err=>{
        moduleContent.innerHTML = `<div class="alert alert-danger small">Error: ${escapeHtml(err.message)}</div>`;
      });
  }

  function placeholderModule(label, descItems){
    return `<div class="card border-0 shadow-sm"><div class="card-body small">
      <h6 class="fw-semibold mb-2">${escapeHtml(label)}</h6>
      <p class="text-muted mb-3">Placeholder module (implementation pending).</p>
      ${descItems && descItems.length ? `<ul class="small ps-3 mb-0">${descItems.map(i=>`<li>${escapeHtml(i)}</li>`).join('')}</ul>`:''}
    </div></div>`;
  }

  const moduleHandlers = {
    mother_registration: renderMotherRegistration,
    prenatal_consultations: renderPrenatalConsultations,
    health_risk_assessment: renderRiskAssessment
  };

  function loadModule(mod,label){
    titleEl.textContent = label;
    if (mod === 'dashboard_home') {
      moduleContent.innerHTML = simplePlaceholders.dashboard_home.html;
      return;
    }
    if (moduleHandlers[mod]) moduleHandlers[mod](label);
    else moduleContent.innerHTML = placeholderModule(label, []);
    // Scroll to top each module load
    moduleContent.scrollTop = 0;
  }

  document.querySelectorAll('.nav-menu a[data-module]').forEach(a=>{
    a.addEventListener('click', e=>{
      e.preventDefault();
      setActive(a);
      loadModule(a.dataset.module, a.dataset.label);
      if (window.innerWidth < 992) document.getElementById('sidebar').classList.remove('show');
    });
  });

  const sidebar = document.getElementById('sidebar');
  document.getElementById('sidebarToggle').addEventListener('click', ()=> sidebar.classList.toggle('show'));
  document.getElementById('closeSidebar').addEventListener('click', ()=> sidebar.classList.remove('show'));
  document.addEventListener('click', (e)=>{
    if (window.innerWidth >= 992) return;
    if (sidebar.classList.contains('show') && !sidebar.contains(e.target) && !e.target.closest('#sidebarToggle')) {
      sidebar.classList.remove('show');
    }
  });
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>