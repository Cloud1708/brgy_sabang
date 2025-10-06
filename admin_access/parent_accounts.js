// admin_access/parent_accounts.js
// Parent Accounts management UI (create parent, list parents, activity & audit logs, manage links).
// PATCH (2025-10-05): Explicitly send csrf_token on every POST + improved error handling for CSRF failures.

const ParentAccountsApp = {
    state: {
        tab: 'parents',
        parents: [],
        activity: [],
        recent: [],
        loading: false,
        creating: false,
        childrenBasic: [],
        showChildPicker: false,
        newChildren: [],
        linkParentId: null,
        linkChildren: [],
    },

    async init() {
        const panel = this.ensurePanel();
        if (!panel) return;
        this.renderBase(panel);
        await this.loadTab();
    },

    ensurePanel() {
        let panel = document.querySelector('.main-inner .panel');
        if (!panel) {
            panel = document.createElement('div');
            panel.className = 'panel';
            document.querySelector('.main-inner')?.appendChild(panel);
        }
        return panel;
    },

    renderBase(panel) {
        panel.innerHTML = `
          <div class="panel-header mb-3">
            <h6>Parent Account Management</h6>
            <p>Manage parent portal accounts, child links, and audit activity.</p>
          </div>
          <div class="d-flex flex-wrap gap-2 mb-3">
            ${['parents','activity','audit'].map(t=>`
              <button class="btn btn-sm ${this.state.tab===t?'btn-success':'btn-outline-secondary'}" data-tab="${t}">
                ${this.icon(t)} ${this.label(t)}
              </button>
            `).join('')}
            <div class="ms-auto">
              <button class="btn btn-success btn-sm" data-action="createParent"><i class="bi bi-person-plus"></i> New Parent</button>
            </div>
          </div>
          <div id="parentAccountsContent">${AdminAPI.spinner('md','Loading...')}</div>
          ${this.modalMarkup()}
        `;

        panel.addEventListener('click', async e => {
            const tabBtn = e.target.closest('[data-tab]');
            if (tabBtn) {
                this.state.tab = tabBtn.getAttribute('data-tab');
                panel.querySelectorAll('[data-tab]').forEach(b=>{
                    b.classList.toggle('btn-success', b===tabBtn);
                    b.classList.toggle('btn-outline-secondary', b!==tabBtn);
                });
                await this.loadTab();
            }
            const act = e.target.closest('[data-action]');
            if (act) {
                const action = act.getAttribute('data-action');
                if (action === 'createParent') this.showCreateModal();
                if (action === 'resetPwd') this.resetPassword(act.dataset.user);
                if (action === 'toggleActive') this.toggleActive(act.dataset.user);
                if (action === 'showLinkChildren') this.showLinkChildren(act.dataset.user);
                if (action === 'unlinkChild') this.unlinkChild(act.dataset.user, act.dataset.child);
            }
        });

        panel.addEventListener('submit', e => {
            if (e.target.matches('#formCreateParent')) {
                e.preventDefault();
                this.submitParent(e.target);
            }
            if (e.target.matches('#formLinkChildren')) {
                e.preventDefault();
                this.submitLinkChildren(e.target);
            }
        });

        panel.addEventListener('click', e => {
            if (e.target.matches('[data-action=addNewChildRow]')) {
                this.addNewChildRow();
            }
            if (e.target.matches('[data-action=removeChildRow]')) {
                const idx = parseInt(e.target.getAttribute('data-index'),10);
                this.removeNewChildRow(idx);
            }
        });
    },

    icon(tab){
        switch(tab){
            case 'parents': return '<i class="bi bi-people"></i>';
            case 'activity': return '<i class="bi bi-activity"></i>';
            case 'audit': return '<i class="bi bi-clock-history"></i>';
        }
        return '';
    },
    label(tab){
        return {
            parents:'Parent Accounts',
            activity:'Engagement',
            audit:'Recent Audit'
        }[tab] || tab;
    },

    modalMarkup() {
        return `
          <div class="modal fade" id="modalCreateParent" tabindex="-1">
            <div class="modal-dialog modal-lg">
              <form id="formCreateParent" class="modal-content">
                <div class="modal-header">
                  <h5 class="modal-title">Create Parent Account</h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                  <div class="row g-2">
                    <div class="col-md-4">
                      <label class="form-label required">Username</label>
                      <input type="text" name="username" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                      <label class="form-label">Email</label>
                      <input type="email" name="email" class="form-control">
                    </div>
                    <div class="col-md-4">
                      <label class="form-label">Password</label>
                      <input type="text" name="password" class="form-control" placeholder="Leave blank to auto-generate">
                    </div>
                    <div class="col-md-4">
                      <label class="form-label required">First Name</label>
                      <input type="text" name="first_name" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                      <label class="form-label required">Last Name</label>
                      <input type="text" name="last_name" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                      <label class="form-label required">Relationship</label>
                      <select name="relationship_type" class="form-select" required>
                        <option value="mother">mother</option>
                        <option value="father">father</option>
                        <option value="guardian">guardian</option>
                        <option value="caregiver">caregiver</option>
                      </select>
                    </div>
                    <div class="col-md-4">
                      <label class="form-label">Parent Birth Date</label>
                      <input type="date" name="parent_birth_date" class="form-control">
                    </div>
                  </div>
                  <hr>
                  <h6 class="mb-2">Link Existing Child (optional)</h6>
                  <div class="mb-2">
                    <input type="number" name="child_id" class="form-control" placeholder="Existing Child ID">
                  </div>
                  <div class="alert alert-info small mb-2">
                    For new mother accounts, you can create new child records below. Only allowed if relationship = mother.
                  </div>
                  <div id="newChildrenWrapper">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                      <h6 class="mb-0">New Children</h6>
                      <button class="btn btn-sm btn-outline-success" data-action="addNewChildRow" type="button">
                        <i class="bi bi-plus-circle"></i> Add
                      </button>
                    </div>
                    <div id="newChildrenRows" class="small text-muted">No new children added.</div>
                  </div>
                </div>
                <div class="modal-footer">
                  <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancel</button>
                  <button class="btn btn-success" type="submit">Create Account</button>
                </div>
              </form>
            </div>
          </div>

          <div class="modal fade" id="modalLinkChildren" tabindex="-1">
            <div class="modal-dialog">
              <form id="formLinkChildren" class="modal-content">
                <div class="modal-header">
                  <h5 class="modal-title">Link Children to Parent</h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                  <input type="hidden" name="parent_user_id" value="">
                  <div class="mb-2">
                    <label class="form-label">Child ID</label>
                    <input type="number" name="child_id" class="form-control" placeholder="Child ID">
                    <div class="form-text">Provide one child ID at a time.</div>
                  </div>
                  <div class="mb-2">
                    <label class="form-label">Relationship</label>
                    <select class="form-select" name="relationship_type">
                      <option value="mother">mother</option>
                      <option value="father">father</option>
                      <option value="guardian" selected>guardian</option>
                      <option value="caregiver">caregiver</option>
                    </select>
                  </div>
                  <div class="alert alert-secondary small">
                    To unlink a child, use the Unlink action in the parent list.
                  </div>
                </div>
                <div class="modal-footer">
                  <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancel</button>
                  <button class="btn btn-success" type="submit">Link Child</button>
                </div>
              </form>
            </div>
          </div>
        `;
    },

    async loadTab() {
        const container = document.getElementById('parentAccountsContent');
        if (container) container.innerHTML = AdminAPI.spinner('md','Loading ...');
        try {
            if (this.state.tab === 'parents') {
                const data = await AdminAPI.get('admin_access/api_parent_accounts_admin.php', { list_parents: 1 });
                this.state.parents = data.parents || [];
                await this.fetchChildrenBasic();
                this.renderParents();
            } else if (this.state.tab === 'activity') {
                const data = await AdminAPI.get('admin_access/api_parent_accounts_admin.php', { activity: 1 });
                this.state.activity = data.activity || [];
                this.renderActivity();
            } else if (this.state.tab === 'audit') {
                const data = await AdminAPI.get('admin_access/api_parent_accounts_admin.php', { recent_activity: 1, page_size: 50 });
                this.state.recent = data.recent_activity || [];
                this.renderAudit();
            }
        } catch (e) {
            if (container) container.innerHTML = `<div class="alert alert-danger">Error: ${e.message}</div>`;
        }
    },

    async fetchChildrenBasic() {
        try {
            const data = await AdminAPI.get('admin_access/api_parent_accounts_admin.php', { children_basic: 1 });
            this.state.childrenBasic = data.children || [];
        } catch {
            // silent
        }
    },

    renderParents() {
        const container = document.getElementById('parentAccountsContent');
        if (!container) return;
        container.innerHTML = `
          <div class="table-responsive">
            <table class="data-table">
              <thead>
          <tr>
            <th>ID</th>
            <th>Username</th>
            <th>Name</th>
            <th>Email</th>
            <th>Children</th>
            <th>Created</th>
            <th>Status</th>
            <th>Parent Account Actions</th>
          </tr>
              </thead>
              <tbody>
          ${this.state.parents.map(p=>{
            const active = String(p.is_active) === '1'; // explicit 1/0 check
            return `
            <tr>
              <td>${p.user_id}</td>
              <td>${p.username}</td>
              <td>${(p.first_name||'')+' '+(p.last_name||'')}</td>
              <td>${p.email||'-'}</td>
              <td style="font-size:.65rem">${p.children_list || '-'}</td>
              <td>${AdminAPI.formatDateTime(p.created_at)}</td>
              <td><span class="badge ${active?'bg-success':'bg-danger'}">${active?'Active':'Inactive'}</span></td>
              <td style="white-space:nowrap">
                <button class="btn btn-sm btn-outline-secondary my-1" data-action="resetPwd" data-user="${p.user_id}">Reset Password</button>
                <button class="btn btn-sm btn-outline-warning my-1" data-action="toggleActive" data-user="${p.user_id}">Toggle Status</button><br>
                <button class="btn btn-sm btn-outline-primary my-1" data-action="showLinkChildren" data-user="${p.user_id}">Link Child</button>
                ${p.children_count>0 ? this.renderUnlinkMenu(p) : ''}
              </td>
            </tr>
            `;
          }).join('') || '<tr><td colspan="8">No parent accounts.</td></tr>'}
              </tbody>
            </table>
          </div>
        `;
    },

    renderUnlinkMenu(parent) {
        if (!parent.children_list) return '';
        const parts = parent.children_list.split(';').map(s=>s.trim()).filter(Boolean);
        return parts.slice(0,2).map(c=>{
            // Without reliable child ID parsing we use a manual unlink via prompt.
            return `<button class="btn btn-sm btn-outline-danger px-3 my-1 text-decoration-none" title="Unlink child (enter ID when prompted)" data-action="unlinkChild" data-user="${parent.user_id}" data-child="0">Unlink Child</button>`;
        }).join(' ');
    },

    renderActivity() {
        const container = document.getElementById('parentAccountsContent');
        if (!container) return;
        container.innerHTML = `
          <div class="table-responsive">
            <table class="data-table">
              <thead>
                <tr><th>ID</th><th>Username</th><th>Total Notifications</th><th>Unread</th><th>Children</th><th>Last Notification</th></tr>
              </thead>
              <tbody>
              ${this.state.activity.map(a=>`
                <tr>
                  <td>${a.user_id}</td>
                  <td>${a.username}</td>
                  <td>${a.total_notifications||0}</td>
                  <td>${a.unread_notifications||0}</td>
                  <td>${a.children_count||0}</td>
                  <td>${a.last_notification_date ? AdminAPI.formatDateTime(a.last_notification_date) : '-'}</td>
                </tr>
              `).join('') || '<tr><td colspan="6">No activity.</td></tr>'}
              </tbody>
            </table>
          </div>
        `;
    },

    renderAudit() {
        const container = document.getElementById('parentAccountsContent');
        if (!container) return;
        container.innerHTML = `
          <div class="table-responsive">
            <table class="data-table">
              <thead>
                <tr>
                  <th>Log ID</th><th>Parent</th><th>Action</th><th>Child</th><th>Description</th><th>IP</th><th>When</th>
                </tr>
              </thead>
              <tbody>
                ${this.state.recent.map(r=>`
                  <tr>
                    <td>${r.log_id}</td>
                    <td>${r.username}</td>
                    <td>${r.action_code}</td>
                    <td>${r.child_name || (r.child_id||'-')}</td>
                    <td style="font-size:.7rem">${r.activity_description || ''}</td>
                    <td>${r.ip_address || '-'}</td>
                    <td>${AdminAPI.formatDateTime(r.created_at)}</td>
                  </tr>
                `).join('') || '<tr><td colspan="7">No audit records.</td></tr>'}
              </tbody>
            </table>
          </div>
        `;
    },

    showCreateModal() {
        const modal = document.getElementById('modalCreateParent');
        if (!modal) return;
        modal.querySelector('form').reset();
        this.state.newChildren = [];
        this.renderNewChildrenRows();
        new bootstrap.Modal(modal).show();
    },

    addNewChildRow() {
        this.state.newChildren.push({ full_name:'', birth_date:'', sex:'male' });
        this.renderNewChildrenRows();
    },

    removeNewChildRow(idx) {
        this.state.newChildren.splice(idx,1);
        this.renderNewChildrenRows();
    },

    renderNewChildrenRows() {
        const wrap = document.getElementById('newChildrenRows');
        if (!wrap) return;
        if (!this.state.newChildren.length) {
            wrap.innerHTML = 'No new children added.';
            return;
        }
        wrap.innerHTML = this.state.newChildren.map((c,i)=>`
          <div class="border rounded p-2 mb-2">
            <div class="d-flex justify-content-between">
              <strong>Child ${i+1}</strong>
              <button type="button" class="btn btn-sm btn-outline-danger" data-action="removeChildRow" data-index="${i}">
                <i class="bi bi-x"></i>
              </button>
            </div>
            <div class="row g-1 mt-1">
              <div class="col-md-5">
                <input type="text" class="form-control form-control-sm" placeholder="Full Name"
                  data-field="full_name" data-index="${i}" value="${c.full_name}">
              </div>
              <div class="col-md-4">
                <input type="date" class="form-control form-control-sm" data-field="birth_date" data-index="${i}" value="${c.birth_date}">
              </div>
              <div class="col-md-3">
                <select class="form-select form-select-sm" data-field="sex" data-index="${i}">
                  <option value="male" ${c.sex==='male'?'selected':''}>male</option>
                  <option value="female" ${c.sex==='female'?'selected':''}>female</option>
                </select>
              </div>
            </div>
          </div>
        `).join('');

        wrap.querySelectorAll('[data-field]').forEach(inp=>{
            inp.addEventListener('change', ()=>{
                const idx = parseInt(inp.getAttribute('data-index'),10);
                const field = inp.getAttribute('data-field');
                this.state.newChildren[idx][field] = inp.value;
            });
        });
    },

    async submitParent(form) {
        if (this.state.creating) return;
        const fd = new FormData(form);
        const relationship = fd.get('relationship_type');
        const newChildren = this.state.newChildren
            .filter(c=>c.full_name && c.birth_date && c.sex)
            .map(c=>({full_name:c.full_name.trim(), birth_date:c.birth_date.trim(), sex:c.sex}));

        if (newChildren.length && relationship !== 'mother') {
            AdminAPI.showError('Only a mother relationship can create new child records.');
            return;
        }

        if (newChildren.length) {
            fd.append('new_children', JSON.stringify(newChildren));
        }

        fd.append('create_parent', 1);
        fd.append('csrf_token', AdminAPI.csrf);
        this.state.creating = true;

        try {
            const res = await AdminAPI.post('admin_access/api_parent_accounts_admin.php', Object.fromEntries(fd.entries()));
            if (res.success) {
                AdminAPI.showSuccess('Parent account created. ' + (res.auto_generated_password ? 'Auto Password: '+res.auto_generated_password : ''));
                bootstrap.Modal.getInstance(document.getElementById('modalCreateParent')).hide();
                await this.loadTab();
            }
        } catch (e) {
            this.handleApiError('Create failed', e);
        } finally {
            this.state.creating = false;
        }
    },

    async resetPassword(userId) {
        if (!await AdminAPI.confirm('Reset password for user #' + userId + '?')) return;
        try {
            const res = await AdminAPI.post('admin_access/api_parent_accounts_admin.php', {
                reset_password: userId,
                csrf_token: AdminAPI.csrf
            });
            if (res.success) {
                AdminAPI.showSuccess('Password reset. New password: ' + res.new_password);
            }
        } catch (e) {
            this.handleApiError('Reset failed', e);
        }
    },

    async toggleActive(userId) {
        try {
            const res = await AdminAPI.post('admin_access/api_parent_accounts_admin.php', {
                toggle_active: userId,
                csrf_token: AdminAPI.csrf
            });
            if (res.success) {
                AdminAPI.showSuccess('Status changed.');
                await this.loadTab();
            }
        } catch (e) {
            this.handleApiError('Toggle failed', e);
        }
    },

    showLinkChildren(userId) {
        const modal = document.getElementById('modalLinkChildren');
        if (!modal) return;
        modal.querySelector('[name=parent_user_id]').value = userId;
        modal.querySelector('form').reset();
        new bootstrap.Modal(modal).show();
    },

    async submitLinkChildren(form) {
        const fd = new FormData(form);
        fd.append('link_child', 1);
        fd.append('csrf_token', AdminAPI.csrf);
        try {
            const res = await AdminAPI.post('admin_access/api_parent_accounts_admin.php', Object.fromEntries(fd.entries()));
            if (res.success) {
                AdminAPI.showSuccess('Child linked.');
                bootstrap.Modal.getInstance(document.getElementById('modalLinkChildren')).hide();
                await this.loadTab();
            }
        } catch (e) {
            this.handleApiError('Link failed', e);
        }
    },

    async unlinkChild(userId, childId) {
        if (!childId || childId === '0') {
            // Prompt for ID if not captured
            const manual = prompt('Enter exact child ID to unlink:','');
            if(!manual) return;
            childId = manual;
        }
        if (!await AdminAPI.confirm(`Unlink child ${childId} from parent ${userId}?`)) return;
        try {
            const res = await AdminAPI.post('admin_access/api_parent_accounts_admin.php', {
                unlink_child: 1,
                parent_user_id: userId,
                child_id: childId,
                csrf_token: AdminAPI.csrf
            });
            if (res.success) {
                AdminAPI.showSuccess('Child unlinked.');
                await this.loadTab();
            }
        } catch (e) {
            this.handleApiError('Unlink failed', e);
        }
    },

    handleApiError(prefix, err){
        const msg = err && err.message ? err.message : 'Unknown error';
        if (/CSRF failed/i.test(msg)) {
            AdminAPI.showError(`${prefix}: ${msg}\nPlease reload the page to refresh your session token.`);
        } else {
            AdminAPI.showError(`${prefix}: ${msg}`);
        }
    }
};

if (window.location.search.includes('section=parent_accounts')) {
    document.addEventListener('DOMContentLoaded', ()=>ParentAccountsApp.init());
}