// admin_access/nutrition_data_entry.js
// Adjusted paths to admin_access/api_nutrition_admin.php

(function(){
    const panel = document.getElementById('dynamicSectionPanel');
    if(!panel){ console.warn('[NutritionDataEntry] panel missing'); return; }

    const API_ENDPOINT = 'admin_access/api_nutrition_admin.php';

    const App = {
        state:{
            summary: [],
            recent: [],
            childHistory: [],
            selectedChild: null,
            loadingSummary: false,
            loadingRecent: false,
        },

        init(){
            panel.innerHTML = this.layout();
            this.bind();
            this.loadSummary();
            this.loadRecent();
        },

        layout(){
            return `
              <div class="panel-header mb-3">
                <h6>Nutrition Data Entry</h6>
                <p>Record and review child nutrition measurements</p>
              </div>
              <div class="mb-4">
                <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
                  <button class="btn btn-success btn-sm" id="ndAddBtn"><i class="bi bi-plus-circle"></i> Add Record</button>
                  <input type="number" min="1" placeholder="Child ID" class="form-control form-control-sm" style="width:130px" id="ndChildId">
                  <button class="btn btn-outline-secondary btn-sm" id="ndLoadChild"><i class="bi bi-search"></i> History</button>
                </div>
                <div class="row g-3">
                  <div class="col-md-4">
                    <div class="border rounded p-2 h-100">
                      <h6 class="small fw-semibold mb-2">Classification Summary</h6>
                      <div id="ndSummaryBox">${AdminAPI.spinner('sm','Loading summary...')}</div>
                    </div>
                  </div>
                  <div class="col-md-8">
                    <div class="border rounded p-2 h-100">
                      <h6 class="small fw-semibold mb-2">Recent Measurements</h6>
                      <div id="ndRecentBox" style="min-height:150px">${AdminAPI.spinner('sm','Loading recent records...')}</div>
                    </div>
                  </div>
                </div>
              </div>
              <div id="ndChildHistoryWrap"></div>
              ${this.addModal()}
            `;
        },

        addModal(){
            return `
              <div class="modal fade" id="ndAddModal" tabindex="-1">
                <div class="modal-dialog modal-lg">
                  <div class="modal-content">
                    <form id="ndAddForm">
                      <div class="modal-header">
                        <h5 class="modal-title">Add Nutrition Record</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                      </div>
                      <div class="modal-body">
                        <div class="row g-3">
                          <div class="col-md-4">
                            <label class="form-label small fw-semibold">Child ID *</label>
                            <input required name="child_id" type="number" min="1" class="form-control form-control-sm">
                          </div>
                          <div class="col-md-4">
                            <label class="form-label small fw-semibold">Weighing Date *</label>
                            <input required name="weighing_date" type="date" class="form-control form-control-sm">
                          </div>
                          <div class="col-md-4">
                            <label class="form-label small fw-semibold">Age (auto)</label>
                            <input disabled id="ndAutoAge" class="form-control form-control-sm">
                          </div>
                          <div class="col-md-4">
                            <label class="form-label small fw-semibold mt-2">Weight (kg)</label>
                            <input step="0.01" name="weight_kg" id="ndWeight" class="form-control form-control-sm">
                          </div>
                          <div class="col-md-4">
                            <label class="form-label small fw-semibold mt-2">Length/Height (cm)</label>
                            <input step="0.1" name="length_height_cm" id="ndLength" class="form-control form-control-sm">
                          </div>
                          <div class="col-md-4">
                            <label class="form-label small fw-semibold mt-2">Status (auto)</label>
                            <input id="ndAutoStatus" class="form-control form-control-sm" disabled>
                          </div>
                          <div class="col-12">
                            <label class="form-label small fw-semibold mt-2">Remarks</label>
                            <textarea name="remarks" class="form-control form-control-sm" rows="2"></textarea>
                          </div>
                        </div>
                        <small class="text-muted d-block mt-2">Status auto-filled using provisional BMI classification if weight & length are provided.</small>
                      </div>
                      <div class="modal-footer">
                        <button class="btn btn-success btn-sm" type="submit"><i class="bi bi-check2-circle"></i> Save Record</button>
                        <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-dismiss="modal">Cancel</button>
                      </div>
                    </form>
                  </div>
                </div>
              </div>
            `;
        },

        bind(){
            panel.querySelector('#ndAddBtn').addEventListener('click', ()=>{
                panel.querySelector('#ndAddForm').reset();
                panel.querySelector('#ndAutoAge').value = '';
                panel.querySelector('#ndAutoStatus').value = '';
                new bootstrap.Modal(document.getElementById('ndAddModal')).show();
            });
            panel.querySelector('#ndLoadChild').addEventListener('click', ()=>{
                const id = parseInt(panel.querySelector('#ndChildId').value,10);
                if(!id){ AdminAPI.showError('Enter Child ID'); return; }
                this.loadChildHistory(id);
            });
            panel.querySelector('#ndAddForm').addEventListener('submit', e=>{
                e.preventDefault();
                this.submitRecord(new FormData(e.target));
            });
            ['ndWeight','ndLength'].forEach(id=>{
                panel.addEventListener('input', (ev)=>{
                    if(ev.target.id===id) this.tryAutoClassify();
                });
            });
        },

        async loadSummary(){
            const box = panel.querySelector('#ndSummaryBox');
            try{
                const data = await AdminAPI.get(API_ENDPOINT, { classification_summary:1 });
                this.state.summary = data.summary || [];
                box.innerHTML = this.renderSummary();
            }catch(err){
                box.innerHTML = `<div class="text-danger small">${err.message}</div>`;
            }
        },

        renderSummary(){
            if(!this.state.summary.length) return '<div class="small text-muted">No data</div>';
            return `
              <table class="table table-sm mb-0">
                <thead><tr><th>Status</th><th class="text-end">Children</th></tr></thead>
                <tbody>
                  ${this.state.summary.map(r=>`
                    <tr>
                      <td><span class="badge bg-${this.statusColor(r.status_code)}">${r.status_code}</span></td>
                      <td class="text-end">${r.child_count}</td>
                    </tr>
                  `).join('')}
                </tbody>
              </table>
            `;
        },

        async loadRecent(){
            const box = panel.querySelector('#ndRecentBox');
            try{
                const data = await AdminAPI.get(API_ENDPOINT, { recent:1 });
                this.state.recent = data.records || [];
                box.innerHTML = this.renderRecent();
            }catch(err){
                box.innerHTML = `<div class="text-danger small">${err.message}</div>`;
            }
        },

        renderRecent(){
            if(!this.state.recent.length) return '<div class="small text-muted">No recent entries</div>';
            return `
              <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                  <tr><th>Date</th><th>Child</th><th>Age (mo)</th><th>W (kg)</th><th>L/H (cm)</th><th>Status</th><th>Remarks</th></tr>
                </thead>
                <tbody>
                  ${this.state.recent.slice(0,25).map(r=>`
                    <tr>
                      <td>${this.escape(r.weighing_date)}</td>
                      <td>${this.escape(r.child_name)}</td>
                      <td>${r.age_in_months ?? ''}</td>
                      <td>${r.weight_kg ?? ''}</td>
                      <td>${r.length_height_cm ?? ''}</td>
                      <td><span class="badge bg-${this.statusColor(r.status_code)}">${this.escape(r.status_code||'')}</span></td>
                      <td>${this.escape(r.remarks||'')}</td>
                    </tr>
                  `).join('')}
                </tbody>
              </table>
            `;
        },

        async loadChildHistory(id){
            const wrap = panel.querySelector('#ndChildHistoryWrap');
            wrap.innerHTML = AdminAPI.spinner('sm','Loading child nutrition history...');
            try{
                const data = await AdminAPI.get(API_ENDPOINT, { child_id:id });
                const rows = (data.records||[]).map(r=>`
                  <tr>
                    <td>${this.escape(r.weighing_date)}</td>
                    <td>${r.age_in_months ?? ''}</td>
                    <td>${r.weight_kg ?? ''}</td>
                    <td>${r.length_height_cm ?? ''}</td>
                    <td><span class="badge bg-${this.statusColor(r.status_code)}">${this.escape(r.status_code||'')}</span></td>
                    <td>${this.escape(r.remarks||'')}</td>
                  </tr>
                `).join('');
                wrap.innerHTML = `
                  <h6 class="mb-2">Child #${id} Nutrition History</h6>
                  <div class="table-responsive">
                    <table class="table table-sm">
                      <thead class="table-light">
                        <tr><th>Date</th><th>Age (mo)</th><th>W (kg)</th><th>L/H (cm)</th><th>Status</th><th>Remarks</th></tr>
                      </thead>
                      <tbody>${rows || '<tr><td colspan="6" class="text-center small text-muted">No records</td></tr>'}</tbody>
                    </table>
                  </div>
                `;
            }catch(err){
                wrap.innerHTML = `<div class="text-danger small">${err.message}</div>`;
            }
        },

        async submitRecord(fd){
            try{
                const obj = {};
                fd.forEach((v,k)=> obj[k]=v);
                obj.csrf_token = AdminAPI.csrf;
                await AdminAPI.post(API_ENDPOINT, obj);
                AdminAPI.showSuccess('Record saved');
                bootstrap.Modal.getInstance(document.getElementById('ndAddModal'))?.hide();
                this.loadRecent();
                if(panel.querySelector('#ndChildId').value == obj.child_id){
                    this.loadChildHistory(obj.child_id);
                }
            }catch(err){
                AdminAPI.showError(err.message);
            }
        },

        async tryAutoClassify(){
            const w = parseFloat(panel.querySelector('#ndWeight').value);
            const l = parseFloat(panel.querySelector('#ndLength').value);
            if(!w || !l) return;
            try{
                const data = await AdminAPI.get(API_ENDPOINT, {
                    classify:1, weight:w, length:l
                });
                if(data.status_code){
                    panel.querySelector('#ndAutoStatus').value = `${data.status_code} (${data.bmi ?? ''})`;
                }
            }catch{}
        },

        statusColor(code){
            if(!code) return 'secondary';
            code = code.toUpperCase();
            if (code==='NOR') return 'success';
            if (code==='SAM') return 'danger';
            if (code==='MAM' || code==='UW') return 'warning';
            if (code==='OW' || code==='OB') return 'info';
            return 'secondary';
        },

        escape(s){
            if(s===null||s===undefined) return '';
            return String(s)
              .replace(/&/g,'&amp;')
              .replace(/</g,'&lt;')
              .replace(/>/g,'&gt;')
              .replace(/"/g,'&quot;')
              .replace(/'/g,'&#39;');
        }
    };

    window.NutritionDataEntryApp = App;
    App.init();
})();