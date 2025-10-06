// admin_access/supplementation.js
// Adjusted endpoint path to admin_access/api_supplementation_admin.php

(function(){
    const panel = document.getElementById('dynamicSectionPanel');
    if(!panel){ console.warn('[Supplementation] panel not found'); return; }

    const API_ENDPOINT = 'admin_access/api_supplementation_admin.php';

    const App = {
        state:{ list:[], filter:{ q:'', type:'', status:'' }, loading:false },

        init(){
            panel.innerHTML = this.layout();
            this.bind();
            this.loadList();
        },

        layout(){
            return `
              <div class="panel-header mb-3">
                <h6>Supplementation Records</h6>
                <p>Track vitamin & mineral supplementation activities</p>
              </div>
              <div class="d-flex flex-wrap gap-2 mb-3">
                <button class="btn btn-success btn-sm" id="supAddBtn"><i class="bi bi-plus-circle"></i> New Record</button>
                <input class="form-control form-control-sm" style="max-width:200px" id="supSearch" placeholder="Search child name">
                <select class="form-select form-select-sm" style="max-width:140px" id="supType">
                  <option value="">Type</option><option>Vitamin A</option><option>Iron</option><option>Deworming</option>
                </select>
                <select class="form-select form-select-sm" style="max-width:140px" id="supStatus">
                  <option value="">Status</option><option value="completed">Completed</option><option value="overdue">Overdue</option>
                </select>
                <button class="btn btn-outline-secondary btn-sm" id="supRefresh"><i class="bi bi-arrow-repeat"></i></button>
              </div>
              <div id="supTableWrap" style="min-height:180px">${AdminAPI.spinner('sm','Loading...')}</div>
              ${this.addModal()} ${this.editModal()}
            `;
        },

        addModal(){
            return `
              <div class="modal fade" id="supAddModal" tabindex="-1">
                <div class="modal-dialog">
                  <div class="modal-content">
                    <form id="supAddForm">
                      <div class="modal-header">
                        <h5 class="modal-title">Add Supplementation Record</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                      </div>
                      <div class="modal-body">
                        <label class="form-label small fw-semibold">Child ID *</label>
                        <input required name="child_id" type="number" min="1" class="form-control form-control-sm">
                        <label class="form-label small fw-semibold mt-2">Supplement Type *</label>
                        <select name="supplement_type" required class="form-select form-select-sm">
                          <option value="">--</option><option>Vitamin A</option><option>Iron</option><option>Deworming</option>
                        </select>
                        <label class="form-label small fw-semibold mt-2">Supplement Date *</label>
                        <input type="date" required name="supplement_date" class="form-control form-control-sm">
                        <label class="form-label small fw-semibold mt-2">Dosage</label>
                        <input name="dosage" class="form-control form-control-sm">
                        <label class="form-label small fw-semibold mt-2">Next Due Date</label>
                        <input type="date" name="next_due_date" class="form-control form-control-sm">
                        <label class="form-label small fw-semibold mt-2">Notes</label>
                        <textarea name="notes" class="form-control form-control-sm" rows="2"></textarea>
                      </div>
                      <div class="modal-footer">
                        <button class="btn btn-success btn-sm" type="submit"><i class="bi bi-check2-circle"></i> Save</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                      </div>
                    </form>
                  </div>
                </div>
              </div>`;
        },

        editModal(){
            return `
              <div class="modal fade" id="supEditModal" tabindex="-1">
                <div class="modal-dialog">
                  <div class="modal-content">
                    <form id="supEditForm">
                      <input type="hidden" name="supplement_id">
                      <div class="modal-header">
                        <h5 class="modal-title">Edit Supplementation</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                      </div>
                      <div class="modal-body">
                        <label class="form-label small fw-semibold">Dosage</label>
                        <input name="dosage" class="form-control form-control-sm">
                        <label class="form-label small fw-semibold mt-2">Next Due Date</label>
                        <input type="date" name="next_due_date" class="form-control form-control-sm">
                        <label class="form-label small fw-semibold mt-2">Notes</label>
                        <textarea name="notes" class="form-control form-control-sm" rows="2"></textarea>
                      </div>
                      <div class="modal-footer">
                        <button class="btn btn-success btn-sm" type="submit"><i class="bi bi-check2"></i> Update</button>
                        <button type="button" class="btn btn-outline-danger btn-sm" id="supDeleteBtn"><i class="bi bi-trash"></i> Delete</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                      </div>
                    </form>
                  </div>
                </div>
              </div>`;
        },

        bind(){
            panel.querySelector('#supAddBtn').addEventListener('click', ()=>{
                panel.querySelector('#supAddForm').reset();
                new bootstrap.Modal(document.getElementById('supAddModal')).show();
            });
            panel.querySelector('#supAddForm').addEventListener('submit', e=>{
                e.preventDefault();
                this.createRecord(new FormData(e.target));
            });
            panel.querySelector('#supRefresh').addEventListener('click', ()=> this.loadList());
            panel.querySelector('#supSearch').addEventListener('input', e=>{
                this.state.filter.q = e.target.value;
                this.loadList();
            });
            panel.querySelector('#supType').addEventListener('change', e=>{
                this.state.filter.type = e.target.value;
                this.loadList();
            });
            panel.querySelector('#supStatus').addEventListener('change', e=>{
                this.state.filter.status = e.target.value;
                this.loadList();
            });
            panel.querySelector('#supEditForm').addEventListener('submit', e=>{
                e.preventDefault();
                this.updateRecord(new FormData(e.target));
            });
            panel.querySelector('#supDeleteBtn').addEventListener('click', ()=>{
                const id = panel.querySelector('#supEditForm [name="supplement_id"]').value;
                this.deleteRecord(id);
            });
        },

        async loadList(){
            this.state.loading = true;
            const wrap = panel.querySelector('#supTableWrap');
            wrap.innerHTML = AdminAPI.spinner('sm','Loading...');
            try{
                const params = { list:1 };
                if(this.state.filter.q) params.q = this.state.filter.q;
                if(this.state.filter.type) params.type = this.state.filter.type;
                if(this.state.filter.status) params.status = this.state.filter.status;
                const data = await AdminAPI.get(API_ENDPOINT, params);
                this.state.list = data.records || [];
                this.state.loading = false;
                wrap.innerHTML = this.renderTable();
                wrap.querySelectorAll('[data-edit]').forEach(btn=>{
                    btn.addEventListener('click', ()=> this.openEdit(btn.getAttribute('data-edit')));
                });
            }catch(err){
                wrap.innerHTML = `<div class="text-danger small">${err.message}</div>`;
            }
        },

        renderTable(){
            if(!this.state.list.length) return '<div class="small text-muted">No records found</div>';
            return `
              <table class="table table-sm align-middle table-hover mb-0">
                <thead class="table-light">
                  <tr>
                    <th>Date</th><th>Child</th><th>Type</th><th>Dosage</th><th>Next Due</th><th>Status</th><th>Notes</th><th style="width:60px;"></th>
                  </tr>
                </thead>
                <tbody>
                  ${this.state.list.map(r=>`
                    <tr>
                      <td>${this.escape(r.supplement_date)}</td>
                      <td>${this.escape(r.child_name)}</td>
                      <td>${this.escape(r.supplement_type)}</td>
                      <td>${this.escape(r.dosage||'')}</td>
                      <td>${this.escape(r.next_due_date||'')}</td>
                      <td><span class="badge bg-${r.status==='overdue'?'danger':'success'}">${r.status}</span></td>
                      <td>${this.escape(r.notes||'')}</td>
                      <td><button class="btn btn-outline-primary btn-sm" data-edit="${r.supplement_id}"><i class="bi bi-pencil"></i></button></td>
                    </tr>
                  `).join('')}
                </tbody>
              </table>
            `;
        },

        async createRecord(fd){
            const payload = {};
            fd.forEach((v,k)=> payload[k]=v);
            try{
                await AdminAPI.postJSON(API_ENDPOINT, payload, 'POST');
                AdminAPI.showSuccess('Supplementation record added');
                bootstrap.Modal.getInstance(document.getElementById('supAddModal'))?.hide();
                this.loadList();
            }catch(err){
                AdminAPI.showError(err.message);
            }
        },

        openEdit(id){
            const rec = this.state.list.find(r=> String(r.supplement_id) === String(id));
            if(!rec){
                AdminAPI.showError('Record not found');
                return;
            }
            const form = panel.querySelector('#supEditForm');
            form.reset();
            form.supplement_id.value = rec.supplement_id;
            form.dosage.value = rec.dosage || '';
            form.next_due_date.value = rec.next_due_date || '';
            form.notes.value = rec.notes || '';
            new bootstrap.Modal(document.getElementById('supEditModal')).show();
        },

        async updateRecord(fd){
            const id = fd.get('supplement_id');
            const payload = {
                dosage: fd.get('dosage'),
                next_due_date: fd.get('next_due_date'),
                notes: fd.get('notes')
            };
            try{
                await AdminAPI.postJSON(`${API_ENDPOINT}?id=${id}`, payload, 'PUT');
                AdminAPI.showSuccess('Record updated');
                bootstrap.Modal.getInstance(document.getElementById('supEditModal'))?.hide();
                this.loadList();
            }catch(err){
                AdminAPI.showError(err.message);
            }
        },

        async deleteRecord(id){
            if(!id) return;
            const ok = await AdminAPI.confirm('Delete this supplementation record?');
            if(!ok) return;
            try{
                await AdminAPI.postJSON(API_ENDPOINT, { supplement_id:id }, 'DELETE');
                AdminAPI.showSuccess('Record deleted');
                bootstrap.Modal.getInstance(document.getElementById('supEditModal'))?.hide();
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

    window.SupplementationApp = App;
    App.init();
})();