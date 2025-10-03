(function(){
  const apiBase = 'parent_api.php';
  const navLinks = document.querySelectorAll('.pp-nav a[data-panel]');
  const content = document.getElementById('panelContent');
  const title   = document.getElementById('panelTitle');
  const sidebar = document.getElementById('sidebar');

  document.getElementById('sidebarToggle')?.addEventListener('click',()=>sidebar.classList.toggle('show'));
  document.addEventListener('click',e=>{
    if(window.innerWidth>900) return;
    if(sidebar.classList.contains('show') && !sidebar.contains(e.target) && !e.target.closest('#sidebarToggle')){
      sidebar.classList.remove('show');
    }
  });

  function setActive(panel){
    navLinks.forEach(a=>a.classList.toggle('active', a.dataset.panel===panel));
  }
  navLinks.forEach(a=>{
    a.addEventListener('click',e=>{
      e.preventDefault();
      const p=a.dataset.panel;
      loadPanel(p);
    });
  });

  function loading(msg='Loading...'){
    content.innerHTML = `<div class="pp-loading"><div class="spinner-border text-success"></div><div class="small text-muted mt-2">${escapeHtml(msg)}</div></div>`;
  }
  function escapeHtml(s){return (s??'').toString().replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));}
  function fetchJSON(u){return fetch(u,{credentials:'same-origin'}).then(r=>{if(!r.ok)throw new Error('HTTP '+r.status);return r.json();});}

  /* Panels */
  function loadPanel(panel){
    setActive(panel);
    switch(panel){
      case 'dashboard': title.textContent='My Children Dashboard'; renderDashboard(); break;
      case 'immunization': title.textContent='Immunization Tracking'; renderImmunization(); break;
      case 'growth': title.textContent='Growth & Nutrition Monitoring'; renderGrowth(); break;
      case 'notifications': title.textContent='Notification Center'; renderNotifications(); break;
      case 'appointments': title.textContent='Appointment Management'; renderAppointments(); break;
      case 'settings': title.textContent='Account Settings'; renderSettings(); break;
      default: title.textContent='My Children Dashboard'; renderDashboard();
    }
  }

  let cachedChildren = null;
  function loadChildren(){
    return (cachedChildren
      ? Promise.resolve(cachedChildren)
      : fetchJSON(apiBase+'?children=1').then(j=>{ if(!j.success) throw new Error(j.error||'Load failed'); cachedChildren=j.children||[]; return cachedChildren; }));
  }

  function renderDashboard(){
    loading('Loading children...');
    loadChildren().then(children=>{
      if(!children.length){
        content.innerHTML = `<div class="pp-panel"><h6>My Children</h6><div class="notice-empty">Wala pang naka-link na bata sa account mo. Makipag-ugnayan sa BHW.</div></div>`;
        return;
      }

      const metrics = children.map(c=>{
        return `<div class="pp-metric">
          <h5>${escapeHtml(c.full_name)}</h5>
          <div class="val">${c.age_months}m</div>
          <small>Age in months</small>
        </div>`;
      }).join('');

      content.innerHTML = `
        <div class="pp-panel">
          <h6>Child Health Overview</h6>
          <div class="pp-metrics">${metrics}</div>
          <p class="small text-muted mb-2">Select a child below for detailed vaccination & growth stats.</p>
          <div class="table-responsive">
            <table class="table-mini">
              <thead><tr><th>Child</th><th>Sex</th><th>Birth Date</th><th>Age (m)</th><th>Actions</th></tr></thead>
              <tbody>
                ${children.map(c=>`
                  <tr>
                    <td>${escapeHtml(c.full_name)}</td>
                    <td>${escapeHtml(c.sex)}</td>
                    <td>${escapeHtml(c.birth_date)}</td>
                    <td>${c.age_months}</td>
                    <td>
                      <button class="btn btn-sm btn-outline-success" data-view="immun" data-id="${c.child_id}"><i class="bi bi-syringe"></i></button>
                      <button class="btn btn-sm btn-outline-primary" data-view="growth" data-id="${c.child_id}"><i class="bi bi-graph-up"></i></button>
                    </td>
                  </tr>
                `).join('')}
              </tbody>
            </table>
          </div>
        </div>
      `;

      content.querySelectorAll('[data-view]').forEach(btn=>{
        btn.addEventListener('click',()=>{
          const id=btn.getAttribute('data-id');
            if(btn.dataset.view==='immun'){ loadPanel('immunization'); showImmunChild(id); }
            else if(btn.dataset.view==='growth'){ loadPanel('growth'); showGrowthChild(id); }
        });
      });
    }).catch(err=>{
      content.innerHTML=`<div class="pp-panel"><h6>Error</h6><div class="text-danger small">${escapeHtml(err.message)}</div></div>`;
    });
  }

  function renderImmunization(){
    loading('Loading immunization data...');
    loadChildren().then(children=>{
      if(!children.length){
        content.innerHTML='<div class="pp-panel"><h6>Immunization</h6><div class="notice-empty">Walang bata.</div></div>';return;
      }
      const list = children.map(c=>`<button class="btn btn-outline-secondary btn-sm me-2 mb-2" data-child="${c.child_id}">${escapeHtml(c.full_name)}</button>`).join('');
      content.innerHTML = `
        <div class="pp-panel">
          <h6>Digital Immunization Card</h6>
          <div class="mb-2">${list}</div>
          <div id="immunChildArea" class="small text-muted">Select a child to view card.</div>
        </div>
      `;
      content.querySelectorAll('[data-child]').forEach(b=>{
        b.addEventListener('click',()=>showImmunChild(b.dataset.child));
      });
    });
  }

  function showImmunChild(childId){
    const area = document.getElementById('immunChildArea');
    if(!area) return;
    area.innerHTML='<div class="small text-muted">Loading...</div>';
    Promise.all([
      fetchJSON(apiBase+'?immunization_card='+childId),
      fetchJSON(apiBase+'?summary='+childId),
      fetchJSON(apiBase+'?vaccination_timeline='+childId)
    ]).then(([cardRes, summaryRes, tlRes])=>{
      if(!cardRes.success||!summaryRes.success||!tlRes.success) throw new Error('Load failed');
      const pct = summaryRes.vaccination_completion_pct ?? 0;
      const bar = `<div class="progress" style="height:10px;"><div class="progress-bar bg-success" style="width:${pct}%;"></div></div><small class="text-muted">${pct}% complete</small>`;
      const cardRows = cardRes.card.map(r=>`
        <tr>
          <td>${escapeHtml(r.vaccine_code)}</td>
          <td>${escapeHtml(r.vaccine_name)}</td>
          <td>${r.dose_number ?? '-'}</td>
          <td>${r.vaccination_date ?? '-'}</td>
          <td>${r.next_dose_due_date ?? '-'}</td>
        </tr>
      `).join('');
      const timeline = tlRes.timeline.map(r=>`
        <tr>
          <td>${escapeHtml(r.vaccine_code)}</td>
          <td>${r.dose_number}</td>
          <td>${r.vaccination_date}</td>
          <td>${r.next_dose_due_date ?? '-'}</td>
          <td>${escapeHtml(r.batch_lot_number||'')}</td>
        </tr>`).join('');
      area.innerHTML = `
        <div class="mb-3">
          <h6 class="mb-1">Completion Status</h6>
          ${bar}
        </div>
        <div class="mb-3">
          <h6 class="mb-1">Immunization Card</h6>
          <div class="table-responsive" style="max-height:280px;">
            <table class="table-mini">
              <thead><tr><th>Code</th><th>Vaccine</th><th>Dose</th><th>Date Given</th><th>Next Due</th></tr></thead>
              <tbody>${cardRows || '<tr><td colspan="5" class="text-center text-muted">No data</td></tr>'}</tbody>
            </table>
          </div>
        </div>
        <div>
          <h6 class="mb-1">Vaccination History</h6>
          <div class="table-responsive" style="max-height:240px;">
            <table class="table-mini">
              <thead><tr><th>Code</th><th>Dose</th><th>Date</th><th>Next Dose</th><th>Batch</th></tr></thead>
              <tbody>${timeline || '<tr><td colspan="5" class="text-center text-muted">None</td></tr>'}</tbody>
            </table>
          </div>
        </div>
      `;
    }).catch(err=>{
      area.innerHTML='<div class="text-danger small">'+escapeHtml(err.message)+'</div>';
    });
  }

  function renderGrowth(){
    loading('Preparing growth view...');
    loadChildren().then(children=>{
      if(!children.length){
        content.innerHTML = '<div class="pp-panel"><h6>Growth Monitoring</h6><div class="notice-empty">No children.</div></div>';return;
      }
      const btns = children.map(c=>`<button class="btn btn-outline-secondary btn-sm me-2 mb-2" data-gc="${c.child_id}">${escapeHtml(c.full_name)}</button>`).join('');
      content.innerHTML=`
        <div class="pp-panel">
          <h6>Growth & Nutrition</h6>
          <div>${btns}</div>
          <div id="growthArea" class="small text-muted mt-2">Select a child.</div>
        </div>`;
      content.querySelectorAll('[data-gc]').forEach(b=>{
        b.addEventListener('click',()=>showGrowthChild(b.dataset.gc));
      });
    });
  }

  function showGrowthChild(childId){
    const area=document.getElementById('growthArea');
    area.innerHTML='Loading growth history...';
    fetchJSON(apiBase+'?growth='+childId).then(j=>{
      if(!j.success) throw new Error(j.error||'Load failed');
      const rows=j.growth||[];
      if(!rows.length){ area.innerHTML='<div class="notice-empty">No growth records.</div>'; return; }
      const tbl=rows.map(r=>`
        <tr>
          <td>${escapeHtml(r.weighing_date)}</td>
          <td>${r.age_in_months}</td>
          <td>${r.weight_kg ?? ''}</td>
          <td>${r.length_height_cm ?? ''}</td>
          <td>${escapeHtml(r.status_code||'')}</td>
        </tr>`).join('');
      area.innerHTML=`
        <div class="mb-3">
          <h6 class="mb-1">Measurement History</h6>
          <div class="table-responsive" style="max-height:340px;">
            <table class="table-mini">
              <thead><tr><th>Date</th><th>Age(m)</th><th>Weight(kg)</th><th>Height(cm)</th><th>Status</th></tr></thead>
              <tbody>${tbl}</tbody>
            </table>
          </div>
        </div>
      `;
    }).catch(err=>{
      area.innerHTML='<div class="text-danger small">'+escapeHtml(err.message)+'</div>';
    });
  }

  function renderNotifications(){
    loading('Loading notifications...');
    fetchJSON(apiBase+'?notifications=1').then(j=>{
      if(!j.success) throw new Error(j.error||'Load failed');
      const list=j.notifications||[];
      content.innerHTML=`
        <div class="pp-panel">
          <h6>Notification Center</h6>
          <div class="table-responsive" style="max-height:420px;">
            <table class="table-mini">
              <thead><tr><th>Type</th><th>Title</th><th>Due</th><th>Status</th><th>Created</th></tr></thead>
              <tbody>${
                list.length? list.map(n=>`
                  <tr>
                    <td>${escapeHtml(n.notification_type)}</td>
                    <td>${escapeHtml(n.title)}</td>
                    <td>${n.due_date ?? '-'}</td>
                    <td>${n.is_read?'<span class="badge-soft">Read</span>':'<span class="badge-soft" style="background:#ffe6e2;color:#b6402d;">Unread</span>'}</td>
                    <td>${escapeHtml(n.created_at)}</td>
                  </tr>
                `).join('') : '<tr><td colspan="5" class="text-center text-muted">No notifications.</td></tr>'
              }</tbody>
            </table>
          </div>
        </div>
      `;
    }).catch(err=>{
      content.innerHTML='<div class="pp-panel"><h6>Error</h6><div class="text-danger small">'+escapeHtml(err.message)+'</div></div>';
    });
  }

  function renderAppointments(){
    loading('Loading appointments...');
    loadChildren().then(children=>{
      if(!children.length){
        content.innerHTML='<div class="pp-panel"><h6>Appointments</h6><div class="notice-empty">No children.</div></div>';return;
      }
      const btns=children.map(c=>`<button class="btn btn-outline-secondary btn-sm me-2 mb-2" data-app="${c.child_id}">${escapeHtml(c.full_name)}</button>`).join('');
      content.innerHTML=`
        <div class="pp-panel">
          <h6>Appointment Management</h6>
          <p class="small text-muted mb-2">Next vaccination & follow-up schedule (derived from recorded future dose dates).</p>
          <div>${btns}</div>
          <div id="appArea" class="small text-muted mt-2">Select a child.</div>
        </div>`;
      content.querySelectorAll('[data-app]').forEach(b=>{
        b.addEventListener('click',()=>showAppointmentsChild(b.dataset.app));
      });
    });
  }

  function showAppointmentsChild(childId){
    const area=document.getElementById('appArea');
    area.innerHTML='Loading...';
    fetchJSON(apiBase+'?appointments='+childId).then(j=>{
      if(!j.success) throw new Error('Load failed');
      const up=j.upcoming||[];
      if(!up.length){ area.innerHTML='<div class="notice-empty">No upcoming scheduled doses.</div>'; return; }
      area.innerHTML=`
        <div class="table-responsive" style="max-height:300px;">
          <table class="table-mini">
            <thead><tr><th>Vaccine</th><th>Dose</th><th>Next Date</th></tr></thead>
            <tbody>${up.map(x=>`
              <tr><td>${escapeHtml(x.vaccine_code)}</td><td>${x.dose_number}</td><td>${x.next_dose_due_date}</td></tr>
            `).join('')}</tbody>
          </table>
        </div>
      `;
    }).catch(err=>{
      area.innerHTML='<div class="text-danger small">'+escapeHtml(err.message)+'</div>';
    });
  }

  function renderSettings(){
    content.innerHTML=`
      <div class="pp-panel">
        <h6>Account Settings</h6>
        <p class="small text-muted">Placeholders – implement actual update endpoints if required.</p>
        <ul class="small">
          <li>Profile Management</li>
          <li>Contact Information</li>
          <li>Password Management (request reset from BHW)</li>
          <li>Notification Preferences (future feature)</li>
        </ul>
      </div>
    `;
  }

  // INITIAL
  loadPanel('dashboard');
})();