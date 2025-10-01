// Updated to safely handle non-JSON responses
document.addEventListener('DOMContentLoaded', () => {
  const sidebar = document.querySelector('.sidebar');
  const toggleBtn = document.getElementById('sidebarToggle');
  const links = document.querySelectorAll('.nav-menu a[data-module]');
  const content = document.getElementById('moduleContent');
  const titleEl = document.getElementById('currentModuleTitle');

  if (toggleBtn) {
    toggleBtn.addEventListener('click', () => sidebar.classList.toggle('show'));
  }

  async function safeJson(res) {
    const ct = res.headers.get('content-type') || '';
    const text = await res.text();
    if (ct.includes('application/json')) {
      try { return JSON.parse(text); }
      catch(e){ throw new Error('Invalid JSON: '+e.message+' | Raw: '+text.slice(0,180)); }
    }
    throw new Error('Expected JSON, got: '+ct+' | Raw: '+text.slice(0,180));
  }

  function setActive(link) {
    links.forEach(a => a.classList.remove('active'));
    link.classList.add('active');
    titleEl.textContent = link.dataset.label || 'Module';
  }

  async function loadModule(key, linkEl) {
    content.innerHTML = '<div class="loading-state"><div class="spinner-border text-primary mb-3"></div><div class="small">Loading '+key+'…</div></div>';
    try {
      const res = await fetch('modules/admin/' + key + '.php', {credentials:'same-origin'});
      if (!res.ok) throw new Error('HTTP '+res.status);
      const html = await res.text();
      content.innerHTML = html;
      initModuleScripts(key);
    } catch (e) {
      content.innerHTML = '<div class="alert alert-danger small">Failed to load module: '+e.message+'</div>';
    }
    if (linkEl) setActive(linkEl);
    if (window.innerWidth < 992) sidebar.classList.remove('show');
  }

  function initModuleScripts(key) {
    if (key === 'create_bhw' || key === 'create_bns') {
      const form = document.getElementById('staffCreateForm');
      if (form) {
        form.addEventListener('submit', async (e) => {
          e.preventDefault();
          if (!form.checkValidity()) {
            form.classList.add('was-validated');
            return;
          }
            const fd = new FormData(form);
            fd.append('csrf_token', window.__ADMIN_CSRF);
          const btn = form.querySelector('button[type=submit]');
          btn.disabled = true;
          btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving…';
          const alertBox = document.getElementById('createStaffAlert');
          try {
            const res = await fetch('actions/create_staff.php', { method:'POST', body:fd });
            let data;
            try { data = await safeJson(res); }
            catch(jsonErr){
              alertBox.className = 'alert alert-danger small';
              alertBox.textContent = 'Server returned non-JSON: '+jsonErr.message;
              alertBox.classList.remove('d-none');
              throw jsonErr;
            }
            if (data.success) {
              alertBox.className = 'alert alert-success small';
              alertBox.textContent = data.message;
              form.reset();
              form.classList.remove('was-validated');
            } else {
              alertBox.className = 'alert alert-danger small';
              alertBox.textContent = data.error || 'Failed.';
            }
            alertBox.classList.remove('d-none');
          } catch(err) {
            console.error(err);
          } finally {
            btn.disabled = false;
            btn.textContent = (form.querySelector('input[name=role]')?.value === 'BNS')
              ? 'Create Account'
              : 'Create Account';
          }
        });
      }
    }
    if (key === 'user_mgmt') {
      document.querySelectorAll('.btn-toggle-active').forEach(btn => {
        btn.addEventListener('click', async () => {
          if (!confirm('Toggle activation for this user?')) return;
          const fd = new FormData();
          fd.append('user_id', btn.dataset.id);
          fd.append('csrf_token', window.__ADMIN_CSRF);
          btn.disabled = true;
          btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
          try {
            const res = await fetch('actions/user_toggle_status.php', { method:'POST', body:fd });
            const data = await safeJson(res);
            if (data.success) {
              loadModule('user_mgmt', document.querySelector('a[data-module="user_mgmt"]'));
            } else {
              alert(data.error || 'Error.');
            }
          } catch(e) {
            alert('Toggle failed: '+e.message);
            btn.disabled = false;
            btn.textContent = 'Retry';
          }
        });
      });
    }
    if (key === 'role_permissions') {
      document.querySelectorAll('.btn-edit-role').forEach(btn => {
        btn.addEventListener('click', () => {
          const row = btn.closest('tr');
          row.classList.add('editing');
          row.querySelector('.role-desc-display').classList.add('d-none');
          row.querySelector('.role-desc-edit').classList.remove('d-none');
        });
      });
      document.querySelectorAll('.btn-cancel-role').forEach(btn => {
        btn.addEventListener('click', () => {
          const row = btn.closest('tr');
          row.classList.remove('editing');
          row.querySelector('.role-desc-display').classList.remove('d-none');
          row.querySelector('.role-desc-edit').classList.add('d-none');
        });
      });
      document.querySelectorAll('.btn-save-role').forEach(btn => {
        btn.addEventListener('click', async () => {
          const row = btn.closest('tr');
          const id = row.dataset.id;
          const desc = row.querySelector('.role-desc-input').value;
          const fd = new FormData();
          fd.append('role_id', id);
          fd.append('role_description', desc);
          fd.append('csrf_token', window.__ADMIN_CSRF);
          btn.disabled = true;
          btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
          try {
            const res = await fetch('actions/role_update.php', { method:'POST', body:fd });
            const data = await safeJson(res);
            if (data.success) {
              loadModule('role_permissions', document.querySelector('a[data-module="role_permissions"]'));
            } else {
              alert(data.error || 'Failed.');
              btn.disabled = false;
              btn.textContent = 'Save';
            }
          } catch(e) {
            alert('Save failed: '+e.message);
            btn.disabled = false;
            btn.textContent = 'Save';
          }
        });
      });
    }
  }

  links.forEach(a => {
    a.addEventListener('click', e => {
      e.preventDefault();
      loadModule(a.dataset.module, a);
    });
  });
  const first = document.querySelector('.nav-menu a.active');
  if (first) loadModule(first.dataset.module, first);
});