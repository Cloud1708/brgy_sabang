// admin_access/maternal_patients.js
// Maternal Patients management UI.

const MaternalPatientsApp = {
    state: {
        mothers: [],
        page: 1,
        pageSize: 20,
        total: 0,
        totalPages: 1,
        search: '',
        risk: '',
        loading: false,
        creating: false
    },

    async init() {
        const panel = this.ensurePanel();
        if (!panel) return;
        this.renderBase(panel);
        await this.loadList();
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
            <h6>Maternal Patients</h6>
            <p>Manage maternal patient profiles and risk monitoring</p>
          </div>
          <div class="d-flex flex-wrap gap-2 mb-3">
            <button class="btn btn-success btn-sm" data-action="addMother">
              <i class="bi bi-plus-circle"></i> Add Patient
            </button>
            <input type="text" placeholder="Search name or purok" class="form-control form-control-sm" style="width:220px" id="mpSearch">
            <select class="form-select form-select-sm" style="width:140px" id="mpRisk">
              <option value="">All Risk</option>
              <option value="high">High Risk (>=2)</option>
              <option value="monitor">Monitor (1)</option>
              <option value="normal">Normal (0)</option>
            </select>
            <button class="btn btn-outline-secondary btn-sm" data-action="applyFilter"><i class="bi bi-search"></i></button>
            <div class="ms-auto">
              <select class="form-select form-select-sm" style="width:120px" id="mpPageSize">
                <option value="10">10 / page</option>
                <option value="20" selected>20 / page</option>
                <option value="50">50 / page</option>
              </select>
            </div>
          </div>
          <div id="maternalPatientsContent">${AdminAPI.spinner('md','Loading mothers...')}</div>
          ${this.modalMarkup()}
        `;

        panel.addEventListener('click', e => {
            const btn = e.target.closest('[data-action]');
            if (!btn) return;
            const act = btn.getAttribute('data-action');
            if (act === 'addMother') this.showAddModal();
            if (act === 'applyFilter') {
                this.state.search = document.getElementById('mpSearch').value.trim();
                this.state.risk = document.getElementById('mpRisk').value;
                this.state.page = 1;
                this.loadList();
            }
            if (act === 'gotoPage') {
                const p = parseInt(btn.getAttribute('data-page'),10);
                if (p && p!==this.state.page) {
                    this.state.page = p;
                    this.loadList();
                }
            }
        });

        panel.addEventListener('change', e => {
            if (e.target.id === 'mpPageSize') {
                this.state.pageSize = parseInt(e.target.value,10) || 20;
                this.state.page = 1;
                this.loadList();
            }
        });

        panel.addEventListener('submit', e => {
            if (e.target.matches('#formAddMaternalPatient')) {
                e.preventDefault();
                this.submitMother(e.target);
            }
        });
    },

    modalMarkup() {
        return `
        <div class="modal fade" id="modalAddMother" tabindex="-1">
          <div class="modal-dialog modal-lg">
            <form id="formAddMaternalPatient" class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title">Add Maternal Patient</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
              </div>
              <div class="modal-body">
                <div class="row g-2">
                  <div class="col-md-6">
                    <label class="form-label required">Full Name</label>
                    <input type="text" name="full_name" class="form-control" required>
                  </div>
                  <div class="col-md-3">
                    <label class="form-label">Purok Name</label>
                    <input type="text" name="purok_name" class="form-control" placeholder="Purok 1">
                  </div>
                  <div class="col-md-3">
                    <label class="form-label">Birth Date</label>
                    <input type="date" name="date_of_birth" class="form-control">
                  </div>
                  <div class="col-md-3">
                    <label class="form-label">Gravida</label>
                    <input type="number" name="gravida" class="form-control" min="0">
                  </div>
                  <div class="col-md-3">
                    <label class="form-label">Para</label>
                    <input type="number" name="para" class="form-control" min="0">
                  </div>
                  <div class="col-md-3">
                    <label class="form-label">Blood Type</label>
                    <input type="text" name="blood_type" class="form-control" placeholder="O+">
                  </div>
                  <div class="col-md-3">
                    <label class="form-label">Contact #</label>
                    <input type="text" name="contact_number" class="form-control" placeholder="09...">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Address Details</label>
                    <input type="text" name="address_details" class="form-control">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Emergency Contact Name</label>
                    <input type="text" name="emergency_contact_name" class="form-control">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Emergency Contact Number</label>
                    <input type="text" name="emergency_contact_number" class="form-control">
                  </div>
                </div>
              </div>
              <div class="modal-footer">
                <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-success" type="submit">Save</button>
              </div>
            </form>
          </div>
        </div>`;
    },

    async loadList() {
        this.state.loading = true;
        const content = document.getElementById('maternalPatientsContent');
        if (content) content.innerHTML = AdminAPI.spinner('md','Loading mothers...');
        try {
            const params = {
                list: 1,
                page: this.state.page,
                page_size: this.state.pageSize
            };
            if (this.state.search) params.search = this.state.search;
            if (this.state.risk) params.risk = this.state.risk;

            const data = await AdminAPI.get('admin_access/api_maternal_patients_admin.php', params);
            this.state.mothers = data.mothers || [];
            this.state.total = data.total_count || 0;
            this.state.totalPages = data.total_pages || 1;
            this.renderList();
        } catch (e) {
            if (content) content.innerHTML = `<div class="alert alert-danger">Error: ${e.message}</div>`;
        } finally {
            this.state.loading = false;
        }
    },

    renderList() {
        const content = document.getElementById('maternalPatientsContent');
        if (!content) return;

        if (!this.state.mothers.length) {
            content.innerHTML = `<div class="alert alert-info mb-0">No patients found.</div>`;
            return;
        }

        const pages = this.paginationBar();

        content.innerHTML = `
          <div class="d-flex justify-content-between align-items-center mb-2">
            <div class="small text-muted">
              Page ${this.state.page} of ${this.state.totalPages} • ${this.state.total} total
              ${this.state.risk ? ` • Filter: ${this.state.risk}` : ''}
              ${this.state.search ? ` • Search: "${this.state.search}"` : ''}
            </div>
            ${pages}
          </div>
          <div class="table-responsive">
            <table class="data-table">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Name</th>
                  <th>Purok</th>
                  <th>Records</th>
                  <th>Last Consult</th>
                  <th>Risk Count</th>
                  <th>Mother's Record</th>
                </tr>
              </thead>
              <tbody>
                ${this.state.mothers.map(m=>`
                    <tr>
                      <td>${m.mother_id}</td>
                      <td>${m.full_name}</td>
                      <td>${m.purok_name || '-'}</td>
                      <td>${m.records_count || 0}</td>
                      <td>${m.last_consultation_date ? AdminAPI.formatDate(m.last_consultation_date) : '-'}</td>
                      <td>${this.riskBadge(m.risk_count||0)}</td>
                      <td>
                        <a class="btn btn-sm btn-link px-3 bg-primary text-white text-decoration-none" href="?section=health_records" title="View Records for mother ${m.mother_id}">View</a>
                      </td>
                    </tr>
                `).join('')}
              </tbody>
            </table>
          </div>
          <div class="mt-2 d-flex justify-content-end">
            ${pages}
          </div>
        `;
    },

    riskBadge(count) {
        const c = Number(count)||0;
        const cls = c >=2 ? 'bg-danger' : c===1 ? 'bg-warning text-dark' : 'bg-success';
        return `<span class="badge ${cls}">${c}</span>`;
    },

    paginationBar() {
        if (this.state.totalPages <= 1) return '';
        const maxLinks = 7;
        let start = Math.max(1, this.state.page - Math.floor(maxLinks/2));
        let end = start + maxLinks - 1;
        if (end > this.state.totalPages) {
            end = this.state.totalPages;
            start = Math.max(1, end - maxLinks + 1);
        }
        let links = [];
        if (this.state.page > 1) {
            links.push(`<button class="btn btn-sm btn-outline-secondary" data-action="gotoPage" data-page="${this.state.page-1}">&laquo;</button>`);
        }
        for (let p=start; p<=end; p++) {
            links.push(`<button class="btn btn-sm ${p===this.state.page?'btn-success':'btn-outline-secondary'}" data-action="gotoPage" data-page="${p}">${p}</button>`);
        }
        if (this.state.page < this.state.totalPages) {
            links.push(`<button class="btn btn-sm btn-outline-secondary" data-action="gotoPage" data-page="${this.state.page+1}">&raquo;</button>`);
        }
        return `<div class="d-inline-flex gap-1 flex-wrap">${links.join('')}</div>`;
    },

    showAddModal() {
        const modalEl = document.getElementById('modalAddMother');
        if (!modalEl) return;
        modalEl.querySelector('form').reset();
        new bootstrap.Modal(modalEl).show();
    },

    async submitMother(form) {
        if (this.state.creating) return;
        const fd = new FormData(form);
        fd.append('csrf_token', AdminAPI.csrf);
        this.state.creating = true;
        try {
            const res = await AdminAPI.post('admin_access/api_maternal_patients_admin.php', Object.fromEntries(fd.entries()));
            if (res.success) {
                AdminAPI.showSuccess('Maternal patient added.');
                bootstrap.Modal.getInstance(document.getElementById('modalAddMother')).hide();
                this.state.page = 1;
                await this.loadList();
            }
        } catch (e) {
            AdminAPI.showError('Add failed: ' + e.message);
        } finally {
            this.state.creating = false;
        }
    }
};

if (window.location.search.includes('section=maternal_patients')) {
    document.addEventListener('DOMContentLoaded', () => MaternalPatientsApp.init());
}