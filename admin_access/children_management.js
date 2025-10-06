// admin_access/children_management.js
// Module: Children Management (BNS)
// Uses admin_access/api_children_admin.php for list/get/register/update

(function(){
    const panel = document.getElementById('dynamicSectionPanel');
    if(!panel){
        console.warn('[ChildrenManagement] panel not found');
        return;
    }

    const API_ENDPOINT = 'admin_access/api_children_admin.php';

    const App = {
        state: {
            list: [],
            loading: false,
            selected: null,
            filter: ''
        },

        init(){
            panel.innerHTML = this.layout();
            this.bindBaseEvents();
            this.loadList();
        },

        layout(){
            return `
              <div class="panel-header mb-2">
                <h6 class="mb-1">Children Management</h6>
                <p class="mb-0">Register, view & update children records</p>
              </div>
              <div class="border rounded p-3 bg-white">
                <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
                  <button class="btn btn-success btn-sm" id="cmAddBtn"><i class="bi bi-person-plus"></i> Register Child</button>
                  <input type="text" class="form-control form-control-sm" style="max-width:240px" id="cmSearch" placeholder="Search name / mother / purok">
                  <button class="btn btn-outline-secondary btn-sm" id="cmRefresh" title="Refresh"><i class="bi bi-arrow-repeat"></i></button>
                </div>
                <div id="cmTableWrap" class="table-responsive" style="min-height:180px;">
                  ${AdminAPI.spinner('md','Loading children...')}
                </div>
              </div>

              <!-- View / Edit Modal -->
              <div class="modal fade" id="cmChildModal" tabindex="-1">
                <div class="modal-dialog modal-lg modal-dialog-scrollable">
                  <div class="modal-content">
                    <div class="modal-header">
                      <h5 class="modal-title" id="cmChildModalTitle">Child Details</h5>
                      <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body" id="cmChildModalBody">...</div>
                  </div>
                </div>
              </div>

              <!-- Register Modal -->
              <div class="modal fade" id="cmRegisterModal" tabindex="-1">
                <div class="modal-dialog modal-lg modal-dialog-scrollable">
                  <div class="modal-content">
                    <form id="cmRegisterForm">
                      <div class="modal-header">
                        <h5 class="modal-title">Register New Child</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                      </div>
                      <div class="modal-body">
                        <div class="row g-3">
                          <div class="col-md-6">
                            <h6 class="mb-2">Child Information</h6>
                            <label class="form-label small fw-semibold">Full Name *</label>
                            <input name="child_full_name" required class="form-control form-control-sm">
                            <label class="form-label small fw-semibold mt-2">Sex *</label>
                            <select name="child_sex" required class="form-select form-select-sm">
                              <option value="">--</option>
                              <option value="male">Male</option>
                              <option value="female">Female</option>
                            </select>
                            <label class="form-label small fw-semibold mt-2">Birth Date *</label>
                            <input type="date" name="child_birth_date" required class="form-control form-control-sm">
                          </div>
                          <div class="col-md-6">
                            <h6 class="mb-2">Mother / Caregiver</h6>
                            <label class="form-label small fw-semibold">Full Name *</label>
                            <input name="mother_full_name" required class="form-control form-control-sm">
                            <label class="form-label small fw-semibold mt-2">Contact Number *</label>
                            <input name="mother_contact" required class="form-control form-control-sm">
                            <label class="form-label small fw-semibold mt-2">Purok ID *</label>
                            <input name="mother_purok_id" required type="number" min="1" class="form-control form-control-sm">
                            <label class="form-label small fw-semibold mt-2">Address Details</label>
                            <textarea name="mother_address" class="form-control form-control-sm" rows="2"></textarea>
                            <small class="text-muted d-block mt-1">Emergency contacts can be added later (not in this quick form).</small>
                          </div>
                        </div>
                      </div>
                      <div class="modal-footer">
                        <button class="btn btn-success btn-sm" type="submit"><i class="bi bi-check2-circle"></i> Save</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                      </div>
                    </form>
                  </div>
                </div>
              </div>
            `;
        },

        bindBaseEvents(){
            panel.querySelector('#cmAddBtn').addEventListener('click', () => {
                panel.querySelector('#cmRegisterForm').reset();
                new bootstrap.Modal(document.getElementById('cmRegisterModal')).show();
            });
            panel.querySelector('#cmRefresh').addEventListener('click', ()=> this.loadList());
            panel.querySelector('#cmSearch').addEventListener('input', e => {
                this.state.filter = e.target.value.toLowerCase();
                this.renderTable();
            });
            panel.querySelector('#cmRegisterForm').addEventListener('submit', e => {
                e.preventDefault();
                this.registerChild(new FormData(e.target));
            });
        },

        async loadList(){
            this.state.loading = true;
            const wrap = panel.querySelector('#cmTableWrap');
            wrap.innerHTML = AdminAPI.spinner('md','Loading children...');
            try{
                // use explicit action=list for clarity
                const data = await AdminAPI.get(API_ENDPOINT, { action:'list' });
                this.state.list = data.children || [];
                this.state.loading = false;
                this.renderTable();
            }catch(err){
                wrap.innerHTML = this.renderHttpError(err);
            }
        },

        renderHttpError(err){
            let msg = err.message || 'Error';
            // Trim huge HTML content if present
            if (msg.length > 800) msg = msg.substring(0,780)+'...';
            return `<div class="text-danger small py-3" style="white-space:pre-wrap">${msg}</div>`;
        },

        renderTable(){
            const wrap = panel.querySelector('#cmTableWrap');
            if(this.state.loading){
                wrap.innerHTML = AdminAPI.spinner('sm','Loading...');
                return;
            }
            const rows = this.state.list
                .filter(r=>{
                    if(!this.state.filter) return true;
                    const needle = this.state.filter;
                    return (r.full_name||'').toLowerCase().includes(needle) ||
                           (r.mother_name||'').toLowerCase().includes(needle) ||
                           (r.purok_name||'').toLowerCase().includes(needle);
                })
                .map(r=>`
                  <tr>
                    <td>${this.escape(r.full_name)}</td>
                    <td>${r.sex==='male'?'M':'F'}</td>
                    <td>${this.escape(r.birth_date_formatted || '')}</td>
                    <td>${this.escape(r.mother_name)}</td>
                    <td>${this.escape(r.purok_name)}</td>
                    <td><span class="badge bg-${this.statusColor(r.nutrition_status)}">${this.escape(r.nutrition_status)}</span></td>
                    <td>${this.escape(r.last_weighing_formatted)}</td>
                    <td>
                      <button class="btn btn-sm btn-outline-primary" data-cmid="${r.child_id}">View</button>
                      <button class="btn btn-sm btn-outline-secondary" data-edit="${r.child_id}">Edit</button>
                    </td>
                  </tr>
                `).join('');
            wrap.innerHTML = `
              <table class="table table-sm align-middle table-hover mb-0">
                <thead class="table-light">
                  <tr>
                    <th>Name</th><th>Sex</th><th>Birth</th><th>Mother</th><th>Purok</th><th>Status</th><th>Last Weighing</th><th style="width:120px;">Actions</th>
                  </tr>
                </thead>
                <tbody>${rows || '<tr><td colspan="8" class="text-center text-muted small py-4">No records</td></tr>'}</tbody>
              </table>
            `;
            wrap.querySelectorAll('[data-cmid]').forEach(btn=>{
                btn.addEventListener('click', ()=> this.openChild(btn.getAttribute('data-cmid')));
            });
            wrap.querySelectorAll('[data-edit]').forEach(btn=>{
                btn.addEventListener('click', ()=> this.openEdit(btn.getAttribute('data-edit')));
            });
        },

        statusColor(code){
            if(!code) return 'secondary';
            code = code.toUpperCase();
            if (code==='NOR' || code.includes('NORMAL')) return 'success';
            if (code==='SAM' || code.includes('SEV')) return 'danger';
            if (code==='MAM' || code==='UW' || code.includes('UNDER')) return 'warning';
            if (code==='OW' || code==='OB') return 'info';
            return 'secondary';
        },

        async openChild(id){
            const body = panel.querySelector('#cmChildModalBody');
            body.innerHTML = AdminAPI.spinner('sm','Loading child...');
            const modal = new bootstrap.Modal(document.getElementById('cmChildModal'));
            modal.show();
            try{
                const data = await AdminAPI.get(API_ENDPOINT, { action:'get', child_id:id });
                if(!data.child){
                    body.innerHTML = `<div class="text-danger small">Child not found</div>`;
                    return;
                }
                const c = data.child;
                body.innerHTML = `
                  <div class="row g-3">
                    <div class="col-md-6">
                      <h6>Child</h6>
                      <div><strong>Name:</strong> ${this.escape(c.full_name)}</div>
                      <div><strong>Sex:</strong> ${this.escape(c.sex)}</div>
                      <div><strong>Birth Date:</strong> ${this.escape(c.birth_date)}</div>
                      <div><strong>Created:</strong> ${this.escape(c.created_at || '')}</div>
                    </div>
                    <div class="col-md-6">
                      <h6>Mother / Caregiver</h6>
                      <div><strong>Name:</strong> ${this.escape(c.mother_name||'')}</div>
                      <div><strong>Contact:</strong> ${this.escape(c.mother_contact||'')}</div>
                      <div><strong>Purok:</strong> ${this.escape(c.purok_name||'')}</div>
                      <div><strong>Address:</strong> ${this.escape(c.address_details||'')}</div>
                    </div>
                  </div>
                  <div class="mt-3">
                    <button class="btn btn-outline-secondary btn-sm" data-edit="${c.child_id}"><i class="bi bi-pencil"></i> Edit</button>
                  </div>
                `;
                body.querySelector('[data-edit]')?.addEventListener('click', ()=>{
                    modal.hide();
                    this.openEdit(c.child_id);
                });
            }catch(err){
                body.innerHTML = this.renderHttpError(err);
            }
        },

        async openEdit(id){
            try{
                const data = await AdminAPI.get(API_ENDPOINT, { action:'get', child_id:id });
                if(!data.child){
                    AdminAPI.showError('Child not found');
                    return;
                }
                const c = data.child;
                const body = panel.querySelector('#cmChildModalBody');
                body.innerHTML = `
                  <form id="cmEditForm">
                    <input type="hidden" name="child_id" value="${c.child_id}">
                    <div class="row g-3">
                      <div class="col-md-6">
                        <h6>Child</h6>
                        <label class="form-label small fw-semibold">Full Name *</label>
                        <input name="full_name" required class="form-control form-control-sm" value="${this.escape(c.full_name)}">
                        <label class="form-label small fw-semibold mt-2">Sex *</label>
                        <select name="sex" required class="form-select form-select-sm">
                          <option value="male" ${c.sex==='male'?'selected':''}>Male</option>
                          <option value="female" ${c.sex==='female'?'selected':''}>Female</option>
                        </select>
                        <label class="form-label small fw-semibold mt-2">Birth Date *</label>
                        <input type="date" name="birth_date" required class="form-control form-control-sm" value="${c.birth_date}">
                      </div>
                      <div class="col-md-6">
                        <h6>Mother / Caregiver</h6>
                        <label class="form-label small fw-semibold">Mother Name</label>
                        <input name="mother_name" class="form-control form-control-sm" value="${this.escape(c.mother_name||'')}">
                        <label class="form-label small fw-semibold mt-2">Contact</label>
                        <input name="mother_contact" class="form-control form-control-sm" value="${this.escape(c.mother_contact||'')}">
                        <label class="form-label small fw-semibold mt-2">Address</label>
                        <textarea name="address_details" class="form-control form-control-sm" rows="2">${this.escape(c.address_details||'')}</textarea>
                        <label class="form-label small fw-semibold mt-2">Purok Name</label>
                        <input name="purok_name" class="form-control form-control-sm" value="${this.escape(c.purok_name||'')}">
                        <small class="text-muted">If a new purok name is entered it will be created automatically.</small>
                      </div>
                    </div>
                    <div class="mt-3">
                      <button class="btn btn-success btn-sm"><i class="bi bi-check2"></i> Save Changes</button>
                      <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                    </div>
                  </form>
                `;
                const modalEl = document.getElementById('cmChildModal');
                const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
                modal.show();
                body.querySelector('#cmEditForm').addEventListener('submit', e=>{
                    e.preventDefault();
                    this.saveEdit(new FormData(e.target), modal);
                });
            }catch(err){
                AdminAPI.showError(err.message);
            }
        },

        async saveEdit(fd, modal){
            const payload = {};
            fd.forEach((v,k)=> payload[k]=v);
            try{
                await AdminAPI.postJSON(`${API_ENDPOINT}?action=update`, payload, 'POST');
                AdminAPI.showSuccess('Child updated');
                modal.hide();
                this.loadList();
            }catch(err){
                AdminAPI.showError(err.message);
            }
        },

        async registerChild(fd){
            const payload = {
                child:{
                    full_name: fd.get('child_full_name'),
                    sex: fd.get('child_sex'),
                    birth_date: fd.get('child_birth_date')
                },
                mother_caregiver:{
                    full_name: fd.get('mother_full_name'),
                    contact_number: fd.get('mother_contact'),
                    purok_id: fd.get('mother_purok_id'),
                    address_details: fd.get('mother_address')
                }
            };
            try{
                await AdminAPI.postJSON(`${API_ENDPOINT}?action=register`, payload, 'POST');
                AdminAPI.showSuccess('Child registered successfully');
                bootstrap.Modal.getInstance(document.getElementById('cmRegisterModal'))?.hide();
                this.loadList();
            }catch(err){
                AdminAPI.showError(err.message);
            }
        },

        escape(str){
            if(str===null||str===undefined) return '';
            return String(str)
              .replace(/&/g,'&amp;')
              .replace(/</g,'&lt;')
              .replace(/>/g,'&gt;')
              .replace(/"/g,'&quot;')
              .replace(/'/g,'&#39;');
        }
    };

    window.ChildrenManagementApp = App;
    App.init();
})();