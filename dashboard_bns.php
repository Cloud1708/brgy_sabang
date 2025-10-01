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
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>BNS Dashboard</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
<style>
  html, body {
    height:100%;
    overflow:hidden; /* Prevent body scroll */
  }
  body.dashboard-body {
    background:#f4f7f4;
  }
  .layout-wrapper {
    display:flex;
    height:100vh; /* Lock full viewport height */
    width:100%;
  }
  .sidebar {
    width:270px;
    flex:0 0 270px;
    background:#0b3d23;
    color:#fff;
    display:flex;
    flex-direction:column;
    overflow-y:auto;          /* Sidebar scroll only */
    overflow-x:hidden;
    scrollbar-width:thin;
    scrollbar-color:#1fae63 #0b3d23;
    position:relative;
  }
  .sidebar::-webkit-scrollbar {
    width:7px;
  }
  .sidebar::-webkit-scrollbar-track {
    background:#0b3d23;
  }
  .sidebar::-webkit-scrollbar-thumb {
    background:#1fae63aa;
    border-radius:4px;
  }
  .sidebar::-webkit-scrollbar-thumb:hover {
    background:#25c775;
  }

  /* Fading hint at bottom when scrollable */
  .sidebar::after {
    content:'';
    position:sticky;
    bottom:0;
    left:0;
    right:0;
    height:22px;
    pointer-events:none;
    background:linear-gradient(to bottom, rgba(11,61,35,0), rgba(11,61,35,0.85));
  }

  .sidebar .brand {
    padding:1.05rem 1.15rem;
    font-weight:600;
    font-size:.95rem;
    display:flex;
    gap:.75rem;
    align-items:flex-start;
    line-height:1.2;
    background:rgba(255,255,255,.08);
  }
  .brand .emoji { font-size:1.45rem; line-height:1; }
  .brand small { font-weight:400; opacity:.85; display:block; font-size:.65rem; margin-top:.15rem; }

  .menu-section-title {
    font-size:.60rem;
    letter-spacing:.09em;
    text-transform:uppercase;
    opacity:.55;
    padding:.75rem 1.1rem .4rem;
    font-weight:600;
    flex-shrink:0;
  }
  .nav-menu {
    list-style:none;
    margin:0;
    padding:0 .3rem .75rem;
  }
  .nav-menu li a {
    display:flex;
    align-items:center;
    gap:.6rem;
    padding:.50rem .95rem;
    font-size:.79rem;
    color:#e3efe8;
    border-left:3px solid transparent;
    text-decoration:none;
    border-radius:.3rem;
    transition:.12s background,.12s color;
  }
  .nav-menu li a .bi { font-size:.95rem; opacity:.85; }
  .nav-menu li a:hover { background:rgba(255,255,255,.10); color:#fff; }
  .nav-menu li a.active {
    background:linear-gradient(90deg,#1fae6333,#1fae6014);
    border-left-color:#28c76f;
    color:#fff;
    font-weight:600;
  }

  .sidebar-footer {
    margin-top:auto;
    padding:.75rem 1rem .95rem;
    font-size:.63rem;
    opacity:.55;
    flex-shrink:0;
  }

  .content-area {
    flex:1;
    min-width:0;
    display:flex;
    flex-direction:column;
    overflow:hidden; /* Prevent main scroll */
    position:relative;
  }
  .topbar {
    background:#ffffff;
    border-bottom:1px solid #dfe7dd;
    padding:.55rem 1.2rem;
    display:flex;
    align-items:center;
    gap:1rem;
    flex-shrink:0;
  }
  main {
    flex:1;
    overflow:hidden; /* Keep static; set to auto if you later want content scroll */
    display:flex;
    flex-direction:column;
  }
  #moduleContent {
    flex:1;
    overflow:auto; /* If you prefer even this NOT to scroll, change to hidden */
  }

  .page-title { font-size:.92rem; font-weight:600; margin:0; color:#114d2c; }
  .badge-role { background:#28c76f; }

  .loading-state { padding:2.7rem 1.5rem; text-align:center; color:#6c757d; }
  .module-hint {
    font-size:.7rem;
    background:#fff;
    border:1px dashed #b7d5c1;
    padding:.6rem .75rem;
    border-radius:.4rem;
    margin-bottom:1.25rem;
    color:#285f3c;
  }
  .status-badge { font-size:.6rem; }

  .mobile-backdrop-close {
    position:absolute;
    top:.55rem;
    right:.55rem;
    border:none;
    background:transparent;
    color:#fff;
    font-size:1.25rem;
    display:none;
  }
  .sidebar.show .mobile-backdrop-close { display:block; }

  @media (max-width: 991.98px) {
    html,body { overflow:hidden; }
    .sidebar {
      position:fixed;
      z-index:1045;
      transform:translateX(-100%);
      transition:.33s cubic-bezier(.4,.0,.2,1);
      height:100vh;
      top:0;
      left:0;
      box-shadow:0 0 0 400vmax rgba(0,0,0,0);
    }
    .sidebar.show {
      transform:translateX(0);
      box-shadow:0 0 0 400vmax rgba(0,0,0,.35);
    }
    .topbar { position:fixed; top:0; left:0; right:0; z-index:1020; }
    .content-area { padding-top:54px; }
  }
</style>
</head>
<body class="dashboard-body">
<div class="layout-wrapper">
  <aside class="sidebar" id="sidebar">
    <button class="mobile-backdrop-close" id="closeSidebar" aria-label="Close sidebar">
      <i class="bi bi-x-lg"></i>
    </button>
    <div class="brand">
      <div class="emoji" aria-hidden="true">🥗</div>
      <div>
        <span>Barangay Nutrition Scholar (BNS)</span>
        <small>Nutrition & Growth Focused</small>
      </div>
    </div>

    <div class="menu-section-title">Overview</div>
    <ul class="nav-menu">
      <li><a href="#" class="active" data-module="dashboard_home" data-label="Dashboard"><i class="bi bi-speedometer2"></i><span>Dashboard</span></a></li>
      <li><a href="#" data-module="nutrition_stats" data-label="Nutrition Statistics"><i class="bi bi-graph-up"></i><span>Nutrition Statistics</span></a></li>
      <li><a href="#" data-module="malnutrition_alerts" data-label="Malnutrition Alerts"><i class="bi bi-exclamation-triangle"></i><span>Malnutrition Alerts</span></a></li>
      <li><a href="#" data-module="growth_overview" data-label="Growth Monitoring Overview"><i class="bi bi-bar-chart-line"></i><span>Growth Monitoring Overview</span></a></li>
      <li><a href="#" data-module="nutrition_events" data-label="Upcoming Activities"><i class="bi bi-calendar-event"></i><span>Upcoming Activities</span></a></li>
    </ul>

    <div class="menu-section-title">Nutrition Data Entry</div>
    <ul class="nav-menu">
      <li><a href="#" data-module="mothers_caregivers" data-label="Mother/Caregiver Details"><i class="bi bi-person-hearts"></i><span>Mother/Caregiver Details</span></a></li>
      <li><a href="#" data-module="child_profiles" data-label="Child Information"><i class="bi bi-people"></i><span>Child Information</span></a></li>
      <li><a href="#" data-module="weighing_sessions" data-label="Weighing Sessions"><i class="bi bi-clipboard2-data"></i><span>Weighing Sessions</span></a></li>
      <li><a href="#" data-module="nutrition_classification" data-label="Nutrition Classification"><i class="bi bi-tags"></i><span>Nutrition Classification</span></a></li>
    </ul>

    <div class="menu-section-title">Nutrition Event Scheduling</div>
    <ul class="nav-menu">
      <li><a href="#" data-module="opt_plus" data-label="OPT Plus Sessions"><i class="bi bi-calendar2-week"></i><span>OPT Plus Sessions</span></a></li>
      <li><a href="#" data-module="feeding_programs" data-label="Feeding Programs"><i class="bi bi-egg-fried"></i><span>Feeding Programs</span></a></li>
      <li><a href="#" data-module="weighing_schedules" data-label="Weighing Schedules"><i class="bi bi-calendar2-check"></i><span>Weighing Schedules</span></a></li>
      <li><a href="#" data-module="nutrition_education" data-label="Nutrition Education"><i class="bi bi-megaphone"></i><span>Nutrition Education</span></a></li>
      <li><a href="#" data-module="nutrition_calendar" data-label="Nutrition Calendar"><i class="bi bi-calendar3"></i><span>Nutrition Calendar</span></a></li>
    </ul>

    <div class="menu-section-title">Nutrition Reports</div>
    <ul class="nav-menu">
      <li><a href="#" data-module="report_growth_results" data-label="Growth Monitoring Results"><i class="bi bi-file-bar-graph"></i><span>Growth Monitoring Results</span></a></li>
      <li><a href="#" data-module="report_status_distribution" data-label="Nutrition Status Distribution"><i class="bi bi-pie-chart"></i><span>Nutrition Status Distribution</span></a></li>
      <li><a href="#" data-module="report_supp_compliance" data-label="Supplementation Compliance"><i class="bi bi-check2-all"></i><span>Supplementation Compliance</span></a></li>
      <li><a href="#" data-module="report_malnutrition_intervention" data-label="Malnutrition Intervention"><i class="bi bi-clipboard-pulse"></i><span>Malnutrition Intervention</span></a></li>
    </ul>

    <div class="mt-2 px-3">
      <a href="logout.php" class="btn btn-sm btn-outline-light w-100">
        <i class="bi bi-box-arrow-right me-1"></i> Logout
      </a>
    </div>
    <div class="sidebar-footer">
      Powered by Barangay Health System<br>
      <span class="text-white-50">&copy; <?php echo date('Y'); ?></span>
    </div>
  </aside>

  <div class="content-area">
    <div class="topbar">
      <button class="btn btn-outline-success btn-sm d-lg-none" id="sidebarToggle">
        <i class="bi bi-list"></i>
      </button>
      <h1 class="page-title mb-0" id="currentModuleTitle">Dashboard</h1>
      <span class="badge badge-role ms-auto d-none d-lg-inline"><i class="bi bi-person-badge me-1"></i> BNS</span>
    </div>
    <main class="p-4">
      <div id="moduleContent" class="bg-transparent">
        <div class="module-hint">
          Piliin ang isang module para mag-encode ng nutrition data. Core tables: mothers_caregivers, children, nutrition_records, wfl_ht_status_types.
        </div>
        <div class="loading-state">
          <div class="spinner-border text-success mb-3"></div>
          <div class="small">Ready. Choose a module.</div>
        </div>
      </div>
    </main>
  </div>
</div>

<script>
  window.__BNS_CSRF = "<?php echo htmlspecialchars($csrf); ?>";
  const moduleContent = document.getElementById('moduleContent');
  const titleEl = document.getElementById('currentModuleTitle');

  const api = {
    mothers: 'bns_modules/api_mothers.php',
    children: 'bns_modules/api_children.php',
    wfl: 'bns_modules/api_wfl_status_types.php',
    nutrition: 'bns_modules/api_nutrition.php'
  };

  function fetchJSON(url, options = {}) {
    options.headers = Object.assign({
      'X-Requested-With':'fetch',
      'X-CSRF-Token': window.__BNS_CSRF
    }, options.headers || {});
    return fetch(url, options).then(r=>{
      if(!r.ok) throw new Error('HTTP '+r.status);
      return r.json();
    });
  }

  function setActive(link){
    document.querySelectorAll('.nav-menu a.active').forEach(a=>a.classList.remove('active'));
    link.classList.add('active');
  }

  function showLoading(label){
    moduleContent.innerHTML = `
      <div class="loading-state">
        <div class="spinner-border text-success mb-3"></div>
        <div class="small">Loading ${label}...</div>
      </div>`;
  }

  function loadModule(mod, label){
    titleEl.textContent = label;
    const handlers = {
      mothers_caregivers: renderMothersModule,
      child_profiles: renderChildrenModule,
      weighing_sessions: renderWeighingModule,
      nutrition_classification: renderNutritionClassificationModule
    };
    if (handlers[mod]) {
      handlers[mod](label);
    } else {
      moduleContent.innerHTML = `<div class="card border-0 shadow-sm">
        <div class="card-body small">
          <h5 class="mb-2">${label}</h5>
          <p>Placeholder module (walang implementation pa).</p>
        </div>
      </div>`;
    }
  }

  function renderMothersModule(label){
    showLoading(label);
    fetch(api.mothers+'?list=1')
      .then(r=>r.json())
      .then(data=>{
        moduleContent.innerHTML = `
          <div class="row g-3">
            <div class="col-lg-4">
              <div class="card shadow-sm border-0 mb-3">
                <div class="card-body">
                  <h6 class="fw-semibold mb-3">Add Mother/Caregiver</h6>
                  <form id="motherForm" class="small">
                    <div class="mb-2">
                      <label class="form-label small mb-1">Full Name</label>
                      <input type="text" name="full_name" class="form-control form-control-sm" required>
                    </div>
                    <div class="mb-2">
                      <label class="form-label small mb-1">Purok (manual input)</label>
                      <input type="text" name="purok_name" class="form-control form-control-sm" required placeholder="Hal: Purok 1 / Riverside">
                      <div class="form-text">I-type lang ang pangalan. Kung bago ito, auto-add sa list.</div>
                    </div>
                    <div class="mb-2">
                      <label class="form-label small mb-1">Address Details</label>
                      <textarea name="address_details" class="form-control form-control-sm" rows="2"></textarea>
                    </div>
                    <div class="mb-2">
                      <label class="form-label small mb-1">Contact Number</label>
                      <input type="text" name="contact_number" class="form-control form-control-sm">
                    </div>
                    <input type="hidden" name="csrf_token" value="${window.__BNS_CSRF}">
                    <div class="d-grid">
                      <button class="btn btn-success btn-sm" type="submit">Save</button>
                    </div>
                    <div class="form-text text-success mt-1 d-none" id="motherSuccess">Saved!</div>
                    <div class="form-text text-danger mt-1 d-none" id="motherError"></div>
                  </form>
                </div>
              </div>
            </div>
            <div class="col-lg-8">
              <div class="card shadow-sm border-0">
                <div class="card-body">
                  <h6 class="fw-semibold mb-3">Mothers / Caregivers</h6>
                  <div class="table-responsive" style="max-height:430px; overflow:auto;">
                    <table class="table table-sm table-hover align-middle mb-0">
                      <thead class="table-light">
                        <tr>
                          <th>Name</th><th>Purok</th><th>Contact</th><th>Children</th><th>Created</th>
                        </tr>
                      </thead>
                      <tbody>
                        ${data.mothers.map(m=>`
                          <tr>
                            <td>${escapeHtml(m.full_name)}</td>
                            <td>${escapeHtml(m.purok_name||'')}</td>
                            <td>${escapeHtml(m.contact_number||'')}</td>
                            <td>${m.children_count}</td>
                            <td><small>${m.created_at}</small></td>
                          </tr>
                        `).join('')}
                        ${data.mothers.length===0?'<tr><td colspan="5" class="text-center small text-muted">No records.</td></tr>':''}
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
          fetch(api.mothers,{method:'POST',body:fd,headers:{'X-Requested-With':'fetch'}})
            .then(r=>{ if(!r.ok) throw new Error('HTTP '+r.status); return r.json(); })
            .then(()=>{
              document.getElementById('motherSuccess').classList.remove('d-none');
              document.getElementById('motherError').classList.add('d-none');
              e.target.reset();
              renderMothersModule(label);
            })
            .catch(err=>{
              const errEl = document.getElementById('motherError');
              errEl.textContent = err.message;
              errEl.classList.remove('d-none');
            });
        });
      })
      .catch(err=>{
        moduleContent.innerHTML = `<div class="alert alert-danger small">Error: ${err.message}</div>`;
      });
  }

  function renderChildrenModule(label){
    showLoading(label);
    Promise.all([
      fetchJSON(api.children+'?list=1'),
      fetchJSON(api.mothers+'?list_basic=1')
    ]).then(([data,mothers])=>{
      moduleContent.innerHTML = `
        <div class="row g-3">
          <div class="col-lg-4">
            <div class="card shadow-sm border-0 mb-3">
              <div class="card-body">
                <h6 class="fw-semibold mb-3">Add Child</h6>
                <form id="childForm" class="small">
                  <div class="mb-2">
                    <label class="form-label small mb-1">Full Name</label>
                    <input type="text" name="full_name" class="form-control form-control-sm" required>
                  </div>
                  <div class="mb-2">
                    <label class="form-label small mb-1">Sex</label>
                    <select name="sex" class="form-select form-select-sm" required>
                      <option value="">Select...</option>
                      <option value="male">Male</option>
                      <option value="female">Female</option>
                    </select>
                  </div>
                  <div class="mb-2">
                    <label class="form-label small mb-1">Birth Date</label>
                    <input type="date" name="birth_date" class="form-control form-control-sm" required>
                  </div>
                  <div class="mb-2">
                    <label class="form-label small mb-1">Mother/Caregiver</label>
                    <select name="mother_id" class="form-select form-select-sm" required>
                      <option value="">Select...</option>
                      ${mothers.mothers.map(m=>`<option value="${m.mother_id}">${escapeHtml(m.full_name)}</option>`).join('')}
                    </select>
                  </div>
                  <input type="hidden" name="csrf_token" value="${window.__BNS_CSRF}">
                  <div class="d-grid">
                    <button class="btn btn-success btn-sm" type="submit">Save</button>
                  </div>
                  <div class="form-text text-success mt-1 d-none" id="childSuccess">Saved!</div>
                  <div class="form-text text-danger mt-1 d-none" id="childError"></div>
                </form>
              </div>
            </div>
          </div>
          <div class="col-lg-8">
            <div class="card shadow-sm border-0">
              <div class="card-body">
                <h6 class="fw-semibold mb-3">Children</h6>
                <div class="table-responsive" style="max-height:430px; overflow:auto;">
                  <table class="table table-sm table-hover mb-0">
                    <thead class="table-light">
                      <tr>
                        <th>Name</th><th>Sex</th><th>Birth Date</th><th>Age (mo)</th><th>Mother</th><th>Last Weigh</th>
                      </tr>
                    </thead>
                    <tbody id="childRows">
                      ${data.children.map(c=>`
                        <tr>
                          <td>${escapeHtml(c.full_name)}</td>
                          <td class="text-capitalize">${c.sex}</td>
                          <td><small>${c.birth_date}</small></td>
                          <td>${c.age_months}</td>
                          <td>${escapeHtml(c.mother_name||'')}</td>
                          <td><small>${c.last_weighing_date||'<span class="text-muted">—</span>'}</small></td>
                        </tr>
                      `).join('')}
                      ${data.children.length===0?'<tr><td colspan="6" class="text-center small text-muted">No children yet.</td></tr>':''}
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        </div>`;
      document.getElementById('childForm').addEventListener('submit', e=>{
        e.preventDefault();
        const fd = new FormData(e.target);
        fetchJSON(api.children, {method:'POST', body:fd})
          .then(()=>{
            document.getElementById('childSuccess').classList.remove('d-none');
            document.getElementById('childError').classList.add('d-none');
            e.target.reset();
            renderChildrenModule(label);
          })
          .catch(err=>{
            const el = document.getElementById('childError');
            el.textContent = err.message;
            el.classList.remove('d-none');
          });
      });
    }).catch(err=>{
      moduleContent.innerHTML = `<div class="alert alert-danger small">Error: ${err.message}</div>`;
    });
  }

  function renderWeighingModule(label){
    showLoading(label);
    Promise.all([
      fetchJSON(api.children+'?list_basic=1'),
      fetchJSON(api.wfl),
      fetchJSON(api.nutrition+'?recent=1')
    ]).then(([children,wfl,records])=>{
      moduleContent.innerHTML = `
        <div class="row g-3">
          <div class="col-lg-4">
            <div class="card border-0 shadow-sm mb-3">
              <div class="card-body small">
                <h6 class="fw-semibold mb-3">Add Weighing</h6>
                <form id="weighForm">
                  <div class="mb-2">
                    <label class="form-label small mb-1">Child</label>
                    <select name="child_id" class="form-select form-select-sm" required>
                      <option value="">Select...</option>
                      ${children.children.map(c=>`<option value="${c.child_id}">${escapeHtml(c.full_name)}</option>`).join('')}
                    </select>
                  </div>
                  <div class="mb-2">
                    <label class="form-label small mb-1">Weighing Date</label>
                    <input type="date" name="weighing_date" class="form-control form-control-sm" required value="<?= date('Y-m-d'); ?>">
                  </div>
                  <div class="mb-2">
                    <label class="form-label small mb-1">Weight (kg)</label>
                    <input type="number" step="0.01" min="0" name="weight_kg" class="form-control form-control-sm" required>
                  </div>
                  <div class="mb-2">
                    <label class="form-label small mb-1">Length/Height (cm)</label>
                    <input type="number" step="0.1" min="0" name="length_height_cm" class="form-control form-control-sm" required>
                  </div>
                  <div class="mb-2">
                    <label class="form-label small mb-1">Remarks</label>
                    <textarea name="remarks" class="form-control form-control-sm" rows="2"></textarea>
                  </div>
                  <input type="hidden" name="csrf_token" value="${window.__BNS_CSRF}">
                  <div class="d-grid">
                    <button class="btn btn-success btn-sm" type="submit">Save Record</button>
                  </div>
                  <div class="form-text text-success mt-1 d-none" id="weighSuccess">Saved!</div>
                  <div class="form-text text-danger mt-1 d-none" id="weighError"></div>
                </form>
              </div>
            </div>
            <div class="alert alert-info small mb-0">
              Age in months auto-computed server-side.
            </div>
          </div>
          <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
              <div class="card-body">
                <h6 class="fw-semibold mb-3">Recent Weighing Records</h6>
                <div class="table-responsive" style="max-height:470px; overflow:auto;">
                  <table class="table table-sm table-hover mb-0">
                    <thead class="table-light">
                      <tr>
                        <th>Date</th><th>Child</th><th>Age (mo)</th><th>Wt (kg)</th><th>Ht (cm)</th><th>Status</th><th>Remarks</th>
                      </tr>
                    </thead>
                    <tbody id="weighRows">
                      ${records.records.map(r=>`
                        <tr>
                          <td><small>${r.weighing_date}</small></td>
                          <td>${escapeHtml(r.child_name)}</td>
                          <td>${r.age_in_months}</td>
                          <td>${r.weight_kg ?? ''}</td>
                          <td>${r.length_height_cm ?? ''}</td>
                          <td>${r.status_code ? `<span class="badge bg-secondary-subtle border text-dark status-badge">${r.status_code}</span>` : '<span class="text-muted">—</span>'}</td>
                          <td><small>${r.remarks?escapeHtml(r.remarks):''}</small></td>
                        </tr>
                      `).join('')}
                      ${records.records.length===0?'<tr><td colspan="7" class="text-center small text-muted">No records.</td></tr>':''}
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        </div>`;
      document.getElementById('weighForm').addEventListener('submit', e=>{
        e.preventDefault();
        const fd = new FormData(e.target);
        fetchJSON(api.nutrition, {method:'POST', body:fd})
          .then(()=>{
            document.getElementById('weighSuccess').classList.remove('d-none');
            document.getElementById('weighError').classList.add('d-none');
            e.target.reset();
            renderWeighingModule(label);
          })
          .catch(err=>{
            const el = document.getElementById('weighError');
            el.textContent = err.message;
            el.classList.remove('d-none');
          });
      });
    }).catch(err=>{
      moduleContent.innerHTML = `<div class="alert alert-danger small">Error: ${err.message}</div>`;
    });
  }

  function renderNutritionClassificationModule(label){
    showLoading(label);
    fetchJSON(api.nutrition+'?classification_summary=1')
      .then(data=>{
        moduleContent.innerHTML = `
          <div class="card border-0 shadow-sm">
            <div class="card-body small">
              <h6 class="fw-semibold mb-3">${label}</h6>
              <p class="text-muted mb-2">Latest status per child distribution (based on most recent nutrition record).</p>
              <div class="row g-3">
                ${data.summary.map(s=>`
                  <div class="col-sm-6 col-md-4 col-lg-3">
                    <div class="p-3 bg-white border rounded h-100">
                      <div class="fw-semibold">${escapeHtml(s.status_code||'UNSET')}</div>
                      <div class="text-muted">${escapeHtml(s.status_description||'No status')}</div>
                      <div class="display-6 fw-semibold mt-2">${s.child_count}</div>
                    </div>
                  </div>
                `).join('')}
                ${data.summary.length===0?'<div class="col-12 text-muted">No data.</div>':''}
              </div>
            </div>
          </div>`;
      })
      .catch(err=>{
        moduleContent.innerHTML = `<div class="alert alert-danger small">Error: ${err.message}</div>`;
      });
  }

  function escapeHtml(str){
    if(str===undefined || str===null) return '';
    return str.toString()
      .replace(/&/g,'&amp;')
      .replace(/</g,'&lt;')
      .replace(/>/g,'&gt;')
      .replace(/"/g,'&quot;')
      .replace(/'/g,'&#39;');
  }

  document.querySelectorAll('.nav-menu a[data-module]').forEach(a=>{
    a.addEventListener('click', e=>{
      e.preventDefault();
      setActive(a);
      loadModule(a.dataset.module, a.dataset.label);
      if (window.innerWidth < 992) {
        document.getElementById('sidebar').classList.remove('show');
      }
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