// admin_access/immunization.js
// Lightweight UI integration for Immunization Management.
// (Unchanged except minor safe checks to work with new structure.)

const ImmunizationApp = {
    state: {
        tab: 'children', // children | overdue | vaccines | recent
        children: [],
        vaccines: [],
        overdue: [],
        overdueDueSoon: [],
        overduePage: 1,
        overdueShow: 'active',
        overduePageSize: 10,
        overduePages: 1,
        recent: [],
        selectedChild: null,
        childCard: null,
        editingVaccine: null,
        loading: false
    },

    async init() {
        const panel = document.querySelector('.main-inner .panel');
        if (!panel) return;
        const existingInfo = panel.innerHTML;
        panel.innerHTML = `
            ${existingInfo}
            <div id="immunizationAppUI" class="mt-4">
                <div class="d-flex flex-wrap gap-2 mb-3">
                    ${['children','overdue','vaccines','recent'].map(t => `
                        <button class="btn btn-sm ${this.state.tab===t?'btn-success':'btn-outline-secondary'}"
                                data-tab="${t}">
                            ${this.iconForTab(t)} ${this.labelForTab(t)}
                        </button>
                    `).join('')}
                    <div class="ms-auto">
                        <button class="btn btn-sm btn-success" data-action="openAddImmunization">
                            <i class="bi bi-plus-circle"></i> Add Immunization
                        </button>
                        <button class="btn btn-sm btn-outline-secondary" data-action="openAddVaccine">
                            <i class="bi bi-capsule"></i> New Vaccine
                        </button>
                    </div>
                </div>
                <div id="immuContent" class="mb-4">${AdminAPI.spinner('md','Loading...')}</div>
            </div>
            ${this.modalMarkup()}
        `;
        this.bindEvents(panel);
        await this.refreshCurrentTab();
    },

    iconForTab(t){
        switch(t){
            case 'children': return '<i class="bi bi-people"></i>';
            case 'overdue': return '<i class="bi bi-exclamation-triangle"></i>';
            case 'vaccines': return '<i class="bi bi-capsule"></i>';
            case 'recent': return '<i class="bi bi-clock-history"></i>';
        }
        return '';
    },
    labelForTab(t){
        return {
            children: 'Children',
            overdue: 'Overdue',
            vaccines: 'Vaccines',
            recent: 'Recent'
        }[t] || t;
    },

    bindEvents(root){
        root.addEventListener('click', async e=>{
            const tabBtn = e.target.closest('[data-tab]');
            if(tabBtn){
                this.state.tab = tabBtn.getAttribute('data-tab');
                root.querySelectorAll('[data-tab]').forEach(b=>{
                    b.classList.toggle('btn-success', b.getAttribute('data-tab')===this.state.tab);
                    b.classList.toggle('btn-outline-secondary', b.getAttribute('data-tab')!==this.state.tab);
                });
                await this.refreshCurrentTab();
            }
            const actBtn = e.target.closest('[data-action]');
            if(actBtn){
                const act = actBtn.getAttribute('data-action');
                if(act==='openAddImmunization') this.openAddImmunizationModal();
                else if(act==='openAddVaccine') this.openAddVaccineModal();
                else if(act==='dismissNotification') this.dismissNotification(actBtn);
                else if(act==='restoreNotification') this.restoreNotification(actBtn);
                else if(act==='editVaccine') this.openEditVaccine(actBtn.dataset.vaccineId);
                else if(act==='deleteVaccine') this.deleteVaccine(actBtn.dataset.vaccineId);
                else if(act==='viewCard') this.loadChildCard(actBtn.dataset.childId);
            }
        });

        // Form submissions
        root.addEventListener('submit', async e=>{
            if(e.target.matches('#formAddImmunization')){
                e.preventDefault();
                await this.submitImmunization(e.target);
            }
            if(e.target.matches('#formAddVaccine')){
                e.preventDefault();
                await this.submitVaccine(e.target);
            }
        });

        // Overdue controls (pagination, filter)
        root.addEventListener('change', async e=>{
            if(e.target.id==='overdueShowSelect'){
                this.state.overdueShow = e.target.value;
                this.state.overduePage = 1;
                await this.fetchOverdue();
                this.render();
            }
            if(e.target.id==='overduePageSelect'){
                this.state.overduePage = parseInt(e.target.value)||1;
                this.render();
            }
        });
    },

    modalMarkup(){
        return `
        <!-- Add Immunization Modal -->
        <div class="modal fade" id="modalAddImmunization" tabindex="-1">
          <div class="modal-dialog modal-lg">
            <form id="formAddImmunization" class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title">Add Immunization Record</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
              </div>
              <div class="modal-body">
                <div class="row g-2">
                  <div class="col-md-4">
                    <label class="form-label required">Child ID</label>
                    <input type="number" name="child_id" class="form-control" required>
                  </div>
                  <div class="col-md-4">
                    <label class="form-label required">Vaccine ID</label>
                    <input type="number" name="vaccine_id" class="form-control" required placeholder="Or leave & use code">
                  </div>
                  <div class="col-md-4">
                    <label class="form-label">Vaccine Code</label>
                    <input type="text" name="vaccine_code" class="form-control" placeholder="BCG / PENTA ...">
                  </div>
                  <div class="col-md-4">
                    <label class="form-label required">Dose #</label>
                    <input type="number" name="dose_number" class="form-control" required min="1">
                  </div>
                  <div class="col-md-4">
                    <label class="form-label required">Vaccination Date</label>
                    <input type="date" name="vaccination_date" class="form-control" required>
                  </div>
                  <div class="col-md-4">
                    <label class="form-label">Next Dose Due (override)</label>
                    <input type="date" name="next_dose_due_date" class="form-control">
                  </div>
                  <div class="col-md-4">
                    <label class="form-label">Batch / Lot #</label>
                    <input type="text" name="batch_lot_number" class="form-control">
                  </div>
                  <div class="col-md-4">
                    <label class="form-label">Site</label>
                    <input type="text" name="vaccination_site" class="form-control" placeholder="Deltoid / Oral ...">
                  </div>
                  <div class="col-md-4">
                    <label class="form-label">Expiry Date</label>
                    <input type="date" name="vaccine_expiry_date" class="form-control">
                  </div>
                  <div class="col-md-12">
                    <label class="form-label">Adverse Reactions</label>
                    <input type="text" name="adverse_reactions" class="form-control">
                  </div>
                  <div class="col-md-12">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-control" rows="2"></textarea>
                  </div>
                </div>
              </div>
              <div class="modal-footer">
                <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-success" type="submit">Save</button>
              </div>
            </form>
          </div>
        </div>

        <!-- Add/Edit Vaccine Modal -->
        <div class="modal fade" id="modalAddVaccine" tabindex="-1">
          <div class="modal-dialog">
            <form id="formAddVaccine" class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title" data-vacc-title>Add Vaccine</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
              </div>
              <div class="modal-body">
                <input type="hidden" name="vaccine_id" value="">
                <div class="mb-2">
                  <label class="form-label required">Vaccine Code</label>
                  <input type="text" name="vaccine_code" class="form-control" required>
                </div>
                <div class="mb-2">
                  <label class="form-label required">Vaccine Name</label>
                  <input type="text" name="vaccine_name" class="form-control" required>
                </div>
                <div class="mb-2">
                  <label class="form-label">Description</label>
                  <textarea name="vaccine_description" class="form-control" rows="2"></textarea>
                </div>
                <div class="mb-2">
                  <label class="form-label">Target Age Group</label>
                  <input type="text" name="target_age_group" class="form-control" placeholder="e.g. 0-12 mos">
                </div>
                <div class="mb-2">
                  <label class="form-label required">Category</label>
                  <select name="vaccine_category" class="form-select" required>
                    <option value="birth">birth</option>
                    <option value="infant">infant</option>
                    <option value="child">child</option>
                    <option value="booster">booster</option>
                    <option value="adult">adult</option>
                  </select>
                </div>
                <div class="mb-2">
                  <label class="form-label required">Doses Required</label>
                  <input type="number" name="doses_required" class="form-control" min="1" value="1" required>
                </div>
                <div class="mb-1">
                  <label class="form-label">Interval Between Doses (days)</label>
                  <input type="number" name="interval_between_doses_days" class="form-control" min="0" placeholder="Optional">
                </div>
              </div>
              <div class="modal-footer">
                <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-success" type="submit">Save</button>
              </div>
            </form>
          </div>
        </div>
        `;
    },

    async refreshCurrentTab(){
        try{
            if(this.state.tab==='children'){
                if(!this.state.children.length) await this.fetchChildren();
            } else if(this.state.tab==='overdue'){
                await this.fetchOverdue();
            } else if(this.state.tab==='vaccines'){
                await this.fetchVaccines();
            } else if(this.state.tab==='recent'){
                await this.fetchRecent();
            }
            this.render();
        }catch(e){
            AdminAPI.showError('Load failed: '+e.message);
        }
    },

    async fetchChildren(){
        const data=await AdminAPI.get('admin_access/api_immunization_admin.php',{children:1});
        this.state.children = data.children || [];
    },
    async fetchVaccines(){
        const data=await AdminAPI.get('admin_access/api_immunization_admin.php',{vaccines:1});
        this.state.vaccines = data.vaccines || [];
    },
    async fetchOverdue(){
        const data=await AdminAPI.get('admin_access/api_immunization_admin.php',{
            overdue:1,
            page:this.state.overduePage,
            page_size:this.state.overduePageSize,
            show:this.state.overdueShow
        });
        this.state.overdue = data.overdue || [];
        this.state.overdueDueSoon = data.dueSoon || [];
        const p = data.pagination || {};
        this.state.overduePages = p.totalPages || 1;
        this.state.overduePage = p.page || 1;
    },
    async fetchRecent(){
        const data=await AdminAPI.get('admin_access/api_immunization_admin.php',{recent_vaccinations:1,limit:25});
        this.state.recent = data.recent_vaccinations || [];
    },
    async loadChildCard(childId){
        try{
            const data=await AdminAPI.get('admin_access/api_immunization_admin.php',{card:1,child_id:childId});
            this.state.selectedChild = data.child || null;
            this.state.childCard = data.vaccines || [];
            this.render();
            window.scrollTo({top:0,behavior:'smooth'});
        }catch(e){
            AdminAPI.showError('Failed to load card: '+e.message);
        }
    },

    render(){
        const c = document.getElementById('immuContent');
        if(!c) return;
        if(this.state.tab==='children') return this.renderChildren(c);
        if(this.state.tab==='overdue') return this.renderOverdue(c);
        if(this.state.tab==='vaccines') return this.renderVaccines(c);
        if(this.state.tab==='recent') return this.renderRecent(c);
    },

    renderChildren(container){
        const card = this.state.selectedChild ? `
            <div class="alert alert-secondary mb-3">
                <strong>Child Card:</strong> ${this.state.selectedChild.full_name} (Age: ${this.state.selectedChild.age_months ?? '?'} mos)
                <button class="btn btn-sm btn-outline-danger ms-2" onclick="ImmunizationApp.clearChildCard()">Close</button>
            </div>
            ${this.renderChildCardTable()}
        ` : '';
        container.innerHTML = `
            ${card}
            <div class="table-responsive">
              <table class="data-table">
                <thead>
                 <tr>
                   <th>ID</th>
                   <th>Name</th>
                   <th>Sex</th>
                   <th>Birth Date</th>
                   <th>Age (mos)</th>
                   <th>Mother</th>
                   <th>Contact</th>
                   <th>Patient's Card</th>
                 </tr>
                </thead>
                <tbody>
                  ${this.state.children.map(ch=>`
                    <tr>
                      <td>${ch.child_id}</td>
                      <td>${ch.full_name}</td>
                      <td>${ch.sex||'-'}</td>
                      <td>${ch.birth_date||'-'}</td>
                      <td>${ch.age_months ?? '-'}</td>
                      <td>${ch.mother_name || '-'}</td>
                      <td>${ch.mother_contact || '-'}</td>
                      <td><button class="btn btn-sm btn-link bg-primary text-white text-decoration-none" data-action="viewCard" data-child-id="${ch.child_id}">View</button></td>
                    </tr>
                  `).join('')}
                </tbody>
              </table>
            </div>
        `;
    },
    renderChildCardTable(){
        if(!this.state.childCard) return '';
        return `
            <div class="table-responsive mb-4">
              <table class="data-table">
                <thead>
                  <tr><th>Vaccine</th><th>Category</th><th>Doses Recorded</th><th>Dose Details</th></tr>
                </thead>
                <tbody>
                  ${this.state.childCard.map(v=>{
                      const doses=(v.doses||[]).map(d=>`${d.dose_number}: ${d.vaccination_date||'-'}${d.next_dose_due_date? ' (next '+d.next_dose_due_date+')':''}`).join('<br>');
                      return `<tr>
                        <td>${v.vaccine_name}</td>
                        <td>${v.vaccine_category||'-'}</td>
                        <td>${(v.doses||[]).length} / ${v.doses_required}</td>
                        <td style="font-size:.75rem">${doses||'-'}</td>
                      </tr>`;
                  }).join('')}
                </tbody>
              </table>
            </div>
        `;
    },
    clearChildCard(){
        this.state.selectedChild=null;
        this.state.childCard=null;
        this.render();
    },

    renderOverdue(container){
        const pagOptions = Array.from({length:this.state.overduePages},(_,i)=>i+1)
            .map(p=>`<option value="${p}" ${p===this.state.overduePage?'selected':''}>Page ${p}</option>`).join('');
        container.innerHTML = `
            <div class="d-flex flex-wrap gap-2 mb-2">
              <div>
                <label class="form-label me-2 mb-0">Show:</label>
                <select id="overdueShowSelect" class="form-select form-select-sm d-inline-block" style="width:auto">
                  <option value="active" ${this.state.overdueShow==='active'?'selected':''}>Active</option>
                  <option value="recycle" ${this.state.overdueShow==='recycle'?'selected':''}>Recycle Bin</option>
                  <option value="all" ${this.state.overdueShow==='all'?'selected':''}>All</option>
                </select>
              </div>
              <div>
                <select id="overduePageSelect" class="form-select form-select-sm d-inline-block" style="width:auto">
                  ${pagOptions}
                </select>
              </div>
            </div>
            <div class="mb-3">
              <strong>Due Soon (${this.state.overdueDueSoon.length}):</strong>
              <div class="table-responsive mt-1" style="max-height:180px;overflow:auto;">
                <table class="data-table">
                  <thead><tr><th>Child</th><th>Vaccine</th><th>Dose</th><th>Due Date</th><th>Target (mos)</th></tr></thead>
                  <tbody>
                  ${this.state.overdueDueSoon.map(o=>`
                    <tr>
                      <td>${o.child_name}</td>
                      <td>${o.vaccine_code}</td>
                      <td>${o.dose_number}</td>
                      <td>${o.due_date || '-'}</td>
                      <td>${o.target_age_months}</td>
                    </tr>`).join('') || '<tr><td colspan="5">None</td></tr>'}
                  </tbody>
                </table>
              </div>
            </div>
            <div class="table-responsive">
              <table class="data-table">
                <thead>
                  <tr>
                    <th>Child</th><th>Vaccine</th><th>Dose</th><th>Age (mos)</th><th>Target</th><th>Days Overdue</th><th>Status</th><th></th>
                  </tr>
                </thead>
                <tbody>
                  ${this.state.overdue.map(o=>`
                    <tr>
                      <td>${o.child_name}</td>
                      <td>${o.vaccine_code}</td>
                      <td>${o.dose_number}</td>
                      <td>${o.age_months}</td>
                      <td>${o.target_age_months}</td>
                      <td>${o.days_overdue ?? '-'}</td>
                      <td>${o.notification_status || 'active'}</td>
                      <td>
                        ${(!o.notification_status || o.notification_status==='active')
                            ? `<button class="btn btn-sm btn-outline-danger" data-action="dismissNotification"
                                 data-child="${o.child_id}" data-vaccine="${o.vaccine_id}" data-dose="${o.dose_number}">Dismiss</button>`
                            : `<button class="btn btn-sm btn-outline-success" data-action="restoreNotification"
                                 data-child="${o.child_id}" data-vaccine="${o.vaccine_id}" data-dose="${o.dose_number}">Restore</button>`}
                      </td>
                    </tr>`).join('') || '<tr><td colspan="8">No overdue items.</td></tr>'}
                </tbody>
              </table>
            </div>
        `;
    },

    renderVaccines(container){
        container.innerHTML = `
            <div class="table-responsive">
              <table class="data-table">
                <thead>
                  <tr><th>ID</th><th>Code</th><th>Name</th><th>Category</th><th>Doses</th><th>Interval (days)</th><th></th></tr>
                </thead>
                <tbody>
                  ${this.state.vaccines.map(v=>`
                    <tr>
                      <td>${v.vaccine_id}</td>
                      <td>${v.vaccine_code}</td>
                      <td>${v.vaccine_name}</td>
                      <td>${v.vaccine_category}</td>
                      <td>${v.doses_required}</td>
                      <td>${v.interval_between_doses_days ?? '-'}</td>
                      <td>
                        <button class="btn btn-sm btn-link" data-action="editVaccine" data-vaccine-id="${v.vaccine_id}">Edit</button>
                        <button class="btn btn-sm btn-link text-danger" data-action="deleteVaccine" data-vaccine-id="${v.vaccine_id}">Delete</button>
                      </td>
                    </tr>`).join('') || '<tr><td colspan="7">No vaccines found.</td></tr>'}
                </tbody>
              </table>
            </div>
        `;
    },

    renderRecent(container){
        container.innerHTML = `
            <div class="table-responsive">
              <table class="data-table">
                <thead>
                  <tr><th>ID</th><th>Date</th><th>Child</th><th>Vaccine</th><th>Dose</th><th>Next Due</th><th>Batch</th></tr>
                </thead>
                <tbody>
                  ${this.state.recent.map(r=>`
                    <tr>
                      <td>${r.immunization_id}</td>
                      <td>${AdminAPI.formatDate(r.vaccination_date)}</td>
                      <td>${r.child_name}</td>
                      <td>${r.vaccine_code}</td>
                      <td>${r.dose_number}</td>
                      <td>${r.next_dose_due_date || '-'}</td>
                      <td>${r.batch_lot_number || '-'}</td>
                    </tr>`).join('') || '<tr><td colspan="7">No recent vaccinations.</td></tr>'}
                </tbody>
              </table>
            </div>
        `;
    },

    openAddImmunizationModal(){
        const m = new bootstrap.Modal(document.getElementById('modalAddImmunization'));
        document.getElementById('formAddImmunization').reset();
        m.show();
    },

    async submitImmunization(form){
        try{
            const fd=new FormData(form);
            fd.append('csrf_token', AdminAPI.csrf);
            const payload=Object.fromEntries(fd.entries());
            const res=await AdminAPI.post('admin_access/api_immunization_admin.php', payload);
            if(res.success){
                AdminAPI.showSuccess('Immunization recorded.');
                bootstrap.Modal.getInstance(document.getElementById('modalAddImmunization')).hide();
                if(this.state.tab==='recent') await this.fetchRecent();
                if(this.state.selectedChild) await this.loadChildCard(this.state.selectedChild.child_id);
                else await this.refreshCurrentTab();
            }
        }catch(e){
            AdminAPI.showError('Save failed: '+e.message);
        }
    },

    openAddVaccineModal(){
        const form=document.getElementById('formAddVaccine');
        form.reset();
        form.querySelector('[name=vaccine_id]').value='';
        form.querySelector('[data-vacc-title]').textContent='Add Vaccine';
        new bootstrap.Modal(document.getElementById('modalAddVaccine')).show();
    },

    openEditVaccine(id){
        const v = this.state.vaccines.find(x=>String(x.vaccine_id)===String(id));
        if(!v) return;
        const form=document.getElementById('formAddVaccine');
        form.reset();
        form.vaccine_id.value=v.vaccine_id;
        form.vaccine_code.value=v.vaccine_code;
        form.vaccine_name.value=v.vaccine_name;
        form.vaccine_description.value=v.vaccine_description||'';
        form.target_age_group.value=v.target_age_group||'';
        form.vaccine_category.value=v.vaccine_category;
        form.doses_required.value=v.doses_required;
        form.interval_between_doses_days.value=v.interval_between_doses_days ?? '';
        form.querySelector('[data-vacc-title]').textContent='Edit Vaccine';
        new bootstrap.Modal(document.getElementById('modalAddVaccine')).show();
    },

    async submitVaccine(form){
        try{
            const fd=new FormData(form);
            fd.append('add_update_vaccine',1);
            fd.append('csrf_token', AdminAPI.csrf);
            const res=await AdminAPI.post('admin_access/api_immunization_admin.php', Object.fromEntries(fd.entries()));
            if(res.success){
                AdminAPI.showSuccess(`Vaccine ${res.mode==='updated'?'updated':'saved'}.`);
                bootstrap.Modal.getInstance(document.getElementById('modalAddVaccine')).hide();
                await this.fetchVaccines();
                if(this.state.tab==='vaccines') this.render();
            }
        }catch(e){
            AdminAPI.showError('Failed to save vaccine: '+e.message);
        }
    },

    async deleteVaccine(id){
        if(!await AdminAPI.confirm('Delete this vaccine? Only allowed if no child immunization records exist.')) return;
        try{
            const res=await AdminAPI.post('admin_access/api_immunization_admin.php',{delete_vaccine_id:id});
            if(res.success){
                AdminAPI.showSuccess('Vaccine deleted.');
                await this.fetchVaccines();
                this.render();
            }
        }catch(e){
            AdminAPI.showError('Delete failed: '+e.message);
        }
    },

    async dismissNotification(btn){
        try{
            const child=btn.dataset.child, vac=btn.dataset.vaccine, dose=btn.dataset.dose;
            await AdminAPI.post('admin_access/api_immunization_admin.php',{
                dismiss_notification:1,
                child_id:child,
                vaccine_id:vac,
                dose_number:dose
            });
            AdminAPI.showSuccess('Dismissed.');
            await this.fetchOverdue();
            this.render();
        }catch(e){
            AdminAPI.showError('Dismiss failed: '+e.message);
        }
    },
    async restoreNotification(btn){
        try{
            const child=btn.dataset.child, vac=btn.dataset.vaccine, dose=btn.dataset.dose;
            await AdminAPI.post('admin_access/api_immunization_admin.php',{
                restore_notification:1,
                child_id:child,
                vaccine_id:vac,
                dose_number:dose
            });
            AdminAPI.showSuccess('Restored.');
            await this.fetchOverdue();
            this.render();
        }catch(e){
            AdminAPI.showError('Restore failed: '+e.message);
        }
    }
};

// Auto-init when on immunization section
if (window.location.search.includes('section=immunization')) {
    document.addEventListener('DOMContentLoaded', ()=>ImmunizationApp.init());
}