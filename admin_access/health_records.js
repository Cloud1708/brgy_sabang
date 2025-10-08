// admin_access/health_records.js
// Enhanced maternal health records integration.

const HealthRecordsApp = {
    state: {
        records: [],
        mode: 'all', // all | risk | recent | mother
        loading: false,
        limit: 50,
        motherIdFilter: '',
    },

    async init() {
        const panel = this.ensurePanel();
        if (!panel) return;
        this.renderBase(panel);
        await this.loadAllRecords();
    },

    ensurePanel() {
        // Use first .panel inside main-inner OR create one.
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
                <h6>Health Records Management</h6>
                <p>Manage maternal health consultations and risk assessments</p>
            </div>
            <div class="d-flex flex-wrap gap-2 mb-3">
                <button class="btn btn-success btn-sm" data-action="addRecord">
                    <i class="bi bi-plus-circle"></i> Add Record
                </button>
                <button class="btn btn-outline-secondary btn-sm" data-action="showAll">
                    <i class="bi bi-list"></i> All Records
                </button>
                <button class="btn btn-outline-secondary btn-sm" data-action="showRisk">
                    <i class="bi bi-exclamation-triangle"></i> Risk Summary
                </button>
                <button class="btn btn-outline-secondary btn-sm" data-action="showRecent">
                    <i class="bi bi-clock-history"></i> Recent
                </button>
                <div class="ms-auto d-flex gap-2">
                    <input type="number" min="1" placeholder="Mother ID" class="form-control form-control-sm" style="width:120px" id="hrFilterMotherId">
                    <button class="btn btn-outline-success btn-sm" data-action="filterMother">Filter</button>
                </div>
            </div>
            <div id="healthRecordsContent">${AdminAPI.spinner('md','Loading records...')}</div>
            ${this.modalMarkup()}
        `;

        panel.addEventListener('click', e => {
            const btn = e.target.closest('[data-action]');
            if (!btn) return;
            const act = btn.getAttribute('data-action');
            if (act === 'addRecord') return this.showAddRecordModal();
            if (act === 'showAll') return this.loadAllRecords();
            if (act === 'showRisk') return this.loadRiskSummary();
            if (act === 'showRecent') return this.loadRecentConsults();
            if (act === 'filterMother') {
                const val = document.getElementById('hrFilterMotherId').value.trim();
                if (val) this.loadMotherHistory(val);
            }
        });

        panel.addEventListener('submit', e => {
            if (e.target.matches('#formAddHealthRecord')) {
                e.preventDefault();
                this.submitHealthRecord(e.target);
            }
        });
    },

    modalMarkup() {
        return `
        <div class="modal fade" id="addHealthRecordModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <form id="formAddHealthRecord" class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Add Health Record</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-2">
                            <div class="col-md-3">
                                <label class="form-label required">Mother ID</label>
                                <input type="number" name="mother_id" class="form-control" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label required">Consultation Date</label>
                                <input type="date" name="consultation_date" class="form-control" required>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Age</label>
                                <input type="number" name="age" class="form-control">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Height (cm)</label>
                                <input type="number" step="0.01" name="height_cm" class="form-control">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Weight (kg)</label>
                                <input type="number" step="0.01" name="weight_kg" class="form-control">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">LMP</label>
                                <input type="date" name="last_menstruation_date" class="form-control">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">EDD</label>
                                <input type="date" name="expected_delivery_date" class="form-control">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">BP Systolic</label>
                                <input type="number" name="blood_pressure_systolic" class="form-control">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">BP Diastolic</label>
                                <input type="number" name="blood_pressure_diastolic" class="form-control">
                            </div>
                        </div>
                        <hr class="my-3">
                        <h6 class="mb-2">Risk Indicators</h6>
                        <div class="row g-2 small">
                            ${[
                                ['vaginal_bleeding','Vaginal Bleeding'],
                                ['urinary_infection','Urinary Infection'],
                                ['high_blood_pressure','High Blood Pressure'],
                                ['fever_38_celsius','Fever (>=38°C)'],
                                ['pallor','Pallor'],
                                ['abnormal_abdominal_size','Abnormal Abd. Size'],
                                ['abnormal_presentation','Abnormal Presentation'],
                                ['absent_fetal_heartbeat','Absent Fetal Heartbeat'],
                                ['swelling','Swelling'],
                                ['vaginal_infection','Vaginal Infection']
                            ].map(([k,l])=>`
                              <div class="col-md-4">
                                <div class="form-check">
                                  <input class="form-check-input" type="checkbox" name="${k}" id="${k}">
                                  <label class="form-check-label" for="${k}">${l}</label>
                                </div>
                              </div>
                            `).join('')}
                        </div>
                        <hr class="my-3">
                        <h6 class="mb-2">Lab Results</h6>
                        <div class="row g-2">
                            <div class="col-md-4">
                              <label class="form-label">HGB Result</label>
                              <input type="text" name="hgb_result" class="form-control">
                            </div>
                            <div class="col-md-4">
                              <label class="form-label">Urine Result</label>
                              <input type="text" name="urine_result" class="form-control">
                            </div>
                            <div class="col-md-4">
                              <label class="form-label">VDRL Result</label>
                              <input type="text" name="vdrl_result" class="form-control">
                            </div>
                            <div class="col-12">
                              <label class="form-label">Other Lab Results</label>
                              <textarea name="other_lab_results" class="form-control" rows="2"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Save Record</button>
                    </div>
                </form>
            </div>
        </div>`;
    },

    async loadAllRecords() {
        this.state.loading = true;
        this.state.mode = 'all';
        this.renderLoading();
        try {
            const data = await AdminAPI.get('admin_access/api_health_records_admin.php', { all: 1, limit: this.state.limit });
            this.state.records = data.records || [];
            this.renderRecords();
        } catch (e) {
            AdminAPI.showError('Failed to load health records: ' + e.message);
            this.renderError(e.message);
        } finally {
            this.state.loading = false;
        }
    },

    async loadRiskSummary() {
        this.state.loading = true;
        this.state.mode = 'risk';
        this.renderLoading();
        try {
            const data = await AdminAPI.get('admin_access/api_health_records_admin.php', { risk_summary: 1 });
            this.state.records = data.risks || [];
            this.renderRecords();
        } catch (e) {
            AdminAPI.showError('Failed to load risk summary: ' + e.message);
            this.renderError(e.message);
        } finally {
            this.state.loading = false;
        }
    },

    async loadRecentConsults() {
        this.state.loading = true;
        this.state.mode = 'recent';
        this.renderLoading();
        try {
            const data = await AdminAPI.get('admin_access/api_health_records_admin.php', { recent_consults: 1, limit: 30 });
            this.state.records = data.recent_consults || [];
            this.renderRecords();
        } catch (e) {
            AdminAPI.showError('Failed to load recent consultations: ' + e.message);
            this.renderError(e.message);
        } finally {
            this.state.loading = false;
        }
    },

    async loadMotherHistory(motherId) {
        this.state.loading = true;
        this.state.mode = 'mother';
        this.state.motherIdFilter = motherId;
        this.renderLoading();
        try {
            const data = await AdminAPI.get('admin_access/api_health_records_admin.php', { list: 1, mother_id: motherId });
            this.state.records = data.records || [];
            this.renderRecords(true);
        } catch (e) {
            AdminAPI.showError('Failed to load consultation history: ' + e.message);
            this.renderError(e.message);
        } finally {
            this.state.loading = false;
        }
    },

    renderLoading() {
        const container = document.getElementById('healthRecordsContent');
        if (container) container.innerHTML = AdminAPI.spinner('md','Loading data...');
    },

    renderError(msg) {
        const container = document.getElementById('healthRecordsContent');
        if (container) {
            container.innerHTML = `<div class="alert alert-danger mb-0">Error: ${msg}</div>`;
        }
    },

    riskBadge(score) {
        score = Number(score) || 0;
        const cls = score > 2 ? 'bg-danger' : score > 0 ? 'bg-warning text-dark' : 'bg-success';
        return `<span class="badge ${cls}">${score}</span>`;
    },

    renderRecords(isMotherHistory = false) {
        const container = document.getElementById('healthRecordsContent');
        if (!container) return;

        if (!this.state.records.length) {
            container.innerHTML = `<div class="alert alert-info mb-0">No records found.</div>`;
            return;
        }

        const extraCols = (this.state.mode === 'risk' || this.state.mode === 'all' || isMotherHistory);

        container.innerHTML = `
          <div class="table-responsive">
            <table class="data-table">
              <thead>
                <tr>
                  ${isMotherHistory ? '<th>ID</th>' : '<th>Record ID</th>'}
                  ${!isMotherHistory ? '<th>Mother\'s Name</th>' : ''}
                  <th>Date</th>
                  <th>Preg Weeks</th>
                  <th>BP</th>
                  ${extraCols ? '<th>Risk Score</th>' : ''}
                  <th>Flags</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                ${this.state.records.map(r => {
                    const riskScore = r.risk_score ?? (
                      (r.vaginal_bleeding|0)+(r.urinary_infection|0)+(r.high_blood_pressure|0)+
                      (r.fever_38_celsius|0)+(r.pallor|0)+(r.abnormal_abdominal_size|0)+
                      (r.abnormal_presentation|0)+(r.absent_fetal_heartbeat|0)+(r.swelling|0)+
                      (r.vaginal_infection|0)
                    );
                    const flags = [
                        r.vaginal_bleeding ? 'VB' : '',
                        r.urinary_infection ? 'UI' : '',
                        r.high_blood_pressure ? 'HBP' : '',
                        r.fever_38_celsius ? 'FEV' : '',
                        r.pallor ? 'PAL' : '',
                        r.abnormal_abdominal_size ? 'ABD' : '',
                        r.abnormal_presentation ? 'PRE' : '',
                        r.absent_fetal_heartbeat ? 'AFH' : '',
                        r.swelling ? 'SWL' : '',
                        r.vaginal_infection ? 'VIN' : ''
                    ].filter(Boolean).join(', ');
                    return `
                      <tr>
                        <td>${r.health_record_id ?? '-'}</td>
                        ${!isMotherHistory ? `<td>${r.full_name || ('Mother #'+r.mother_id)}</td>` : ''}
                        <td>${AdminAPI.formatDate(r.consultation_date)}</td>
                        <td>${r.pregnancy_age_weeks ?? 'N/A'}</td>
                        <td>${r.blood_pressure_systolic||'-'}/${r.blood_pressure_diastolic||'-'}</td>
                        ${extraCols ? `<td>${this.riskBadge(riskScore)}</td>` : ''}
                        <td style="font-size:.7rem">${flags || '-'}</td>
                        <td>
                          <a class="btn btn-sm btn-link px-3 bg-primary text-white text-decoration-none" data-record="${r.health_record_id}" data-action="viewDetails">View</a>
                        </td>
                      </tr>
                    `;
                }).join('')}
              </tbody>
            </table>
          </div>
        `;

        container.querySelectorAll('[data-action=viewDetails]').forEach(btn => {
            btn.addEventListener('click', () => {
                const id = btn.getAttribute('data-record');
                this.viewDetails(id);
            });
        });
    },

    showAddRecordModal() {
        const modalEl = document.getElementById('addHealthRecordModal');
        if (!modalEl) return;
        const form = modalEl.querySelector('form');
        form.reset();
        new bootstrap.Modal(modalEl).show();
    },

    async submitHealthRecord(form) {
        const formData = new FormData(form);
        formData.append('csrf_token', AdminAPI.csrf);
        const payload = Object.fromEntries(formData.entries());
        try {
            const result = await AdminAPI.post('admin_access/api_health_records_admin.php', payload);
            if (result.success) {
                AdminAPI.showSuccess('Health record added successfully');
                bootstrap.Modal.getInstance(document.getElementById('addHealthRecordModal')).hide();
                if (this.state.mode === 'mother' && this.state.motherIdFilter) {
                    await this.loadMotherHistory(this.state.motherIdFilter);
                } else {
                    await this.loadAllRecords();
                }
            }
        } catch (error) {
            AdminAPI.showError('Failed to add health record: ' + error.message);
        }
    },

    async viewDetails(recordId) {
        AdminAPI.showInfo('Detailed view not yet implemented (Record ID '+recordId+').');
    }
};

// Auto-initialize on correct section
if (window.location.search.includes('section=health_records')) {
    document.addEventListener('DOMContentLoaded', () => HealthRecordsApp.init());
}