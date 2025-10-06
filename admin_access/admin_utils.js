// admin_access/admin_utils.js
// Shared helper API layer & UI utilities for admin dashboard sections.
//
// FIX: Removed ES module export syntax to avoid "Unexpected token 'export'"
// when loaded as a classic script. Exposes AdminAPI on window instead.
// PATCHES included:
//  - Base path resolver
//  - get/post/postJSON helpers
//  - Explicit 419 (CSRF) handling
//  - Spinner + alert helpers
//  - Global unhandledrejection listener for CSRF
//
// If you ever want to use ES modules, add type="module" to the script tag
// and you may reintroduce `export { AdminAPI };` plus adjust other scripts
// to import it instead of relying on window.AdminAPI.

(function(){
    const AdminAPI = {
        csrf: window.__ADMIN_CSRF || '',

        basePath: (function () {
            if (window.__APP_BASE) return window.__APP_BASE;
            let path = window.location.pathname || '/';
            if (!path.endsWith('/')) {
                path = path.substring(0, path.lastIndexOf('/') + 1);
            }
            return path;
        })(),

        setCSRF(token) {
            this.csrf = token || '';
        },

        _resolve(endpoint) {
            if (/^https?:\/\//i.test(endpoint)) return new URL(endpoint);
            if (endpoint.startsWith('/')) return new URL(endpoint, window.location.origin);
            return new URL(this.basePath + endpoint.replace(/^\.?\//, ''), window.location.origin);
        },

        async get(endpoint, params = {}) {
            const url = this._resolve(endpoint);
            Object.keys(params).forEach(key => {
                const v = params[key];
                if (v !== undefined && v !== null) url.searchParams.append(key, v);
            });

            let response;
            try {
                console.debug('[AdminAPI GET]', url.href);
                response = await fetch(url.href, {
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        ...(this.csrf ? {'X-CSRF-Token': this.csrf} : {})
                    }
                });
            } catch (netErr) {
                throw new Error('Network error: ' + netErr.message);
            }

            return this._processResponse(response);
        },

        async post(endpoint, data = {}) {
            if (!data.csrf_token) data.csrf_token = this.csrf;

            const formData = new URLSearchParams();
            Object.keys(data).forEach(key => {
                const v = data[key];
                if (v !== null && v !== undefined) formData.append(key, v);
            });

            const url = this._resolve(endpoint);

            let response;
            try {
                console.debug('[AdminAPI POST]', url.href, Object.fromEntries(formData.entries()));
                response = await fetch(url.href, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'Accept': 'application/json',
                        ...(this.csrf ? {'X-CSRF-Token': this.csrf} : {})
                    },
                    body: formData
                });
            } catch (netErr) {
                throw new Error('Network error: ' + netErr.message);
            }

            return this._processResponse(response);
        },

        async postJSON(endpoint, payload = {}, method = 'POST') {
            if (!payload.csrf_token) payload.csrf_token = this.csrf;
            const url = this._resolve(endpoint);
            let response;
            try {
                console.debug(`[AdminAPI ${method} JSON]`, url.href, payload);
                response = await fetch(url.href, {
                    method,
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        ...(this.csrf ? {'X-CSRF-Token': this.csrf} : {})
                    },
                    body: JSON.stringify(payload)
                });
            } catch (e) {
                throw new Error('Network error: ' + e.message);
            }
            return this._processResponse(response);
        },

        async _processResponse(response) {
            let text = '';
            try { text = await response.text(); } catch {}
            let json;
            try { json = text ? JSON.parse(text) : {}; } catch { json = { raw: text }; }

            if (!response.ok) {
                if (response.status === 419 || (json && json.error === 'CSRF failed')) {
                    throw new Error('CSRF failed');
                }
                const msg = json && json.error ? json.error :
                    `HTTP ${response.status}${text ? ' - ' + text : ''}`;
                throw new Error(msg);
            }

            if (json && json.success === false && json.error) {
                if (/CSRF failed/i.test(json.error)) {
                    throw new Error('CSRF failed');
                }
                throw new Error(json.error);
            }
            return json;
        },

        showError(message) { this.showAlert(message, 'danger', 8000); },
        showSuccess(message) { this.showAlert(message, 'success'); },
        showInfo(message) { this.showAlert(message, 'info'); },

        showAlert(message, type = 'info', timeoutMs = 5000) {
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
            alertDiv.innerHTML = `
                <div style="white-space:pre-line">${message}</div>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            const container = document.querySelector('.main-inner');
            if (!container) return;
            container.insertBefore(alertDiv, container.firstChild);
            if (timeoutMs > 0) setTimeout(()=>{ try { alertDiv.remove(); } catch {} }, timeoutMs);
        },

        formatDate(dateStr) {
            if (!dateStr) return 'N/A';
            const date = new Date(dateStr);
            if (isNaN(date.getTime())) return dateStr;
            return date.toLocaleDateString('en-US',{ year:'numeric', month:'short', day:'numeric' });
        },

        formatDateTime(dateStr) {
            if (!dateStr) return 'N/A';
            const date = new Date(dateStr.replace(' ', 'T'));
            if (isNaN(date.getTime())) return dateStr;
            return date.toLocaleString('en-US',{
                year:'numeric', month:'short', day:'numeric', hour:'2-digit', minute:'2-digit'
            });
        },

        calculateAgeMonths(birthDate) {
            const today = new Date();
            const birth = new Date(birthDate);
            if (isNaN(birth.getTime())) return null;
            return (today.getFullYear() - birth.getFullYear()) * 12 +
                today.getMonth() - birth.getMonth() -
                (today.getDate() < birth.getDate() ? 1 : 0);
        },

        spinner(size = 'md', text = '') {
            const px = size === 'sm' ? 16 : size === 'lg' ? 48 : 28;
            return `
                <div class="d-flex flex-column align-items-center justify-content-center py-4 w-100">
                    <div class="spinner-border text-success" style="width:${px}px;height:${px}px" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    ${text ? `<div class="mt-2 small text-muted">${text}</div>` : ''}
                </div>
            `;
        },

        confirm(message) {
            return new Promise(resolve => resolve(window.confirm(message)));
        },

        handleCsrfFailure(context='Request') {
            this.showError(
                `${context} failed due to a CSRF token mismatch.\nReloading will refresh your session token.`
            );
            setTimeout(()=>window.location.reload(), 1500);
        }
    };

    // Global listener for unhandled promise rejections (focus on CSRF).
    window.addEventListener('unhandledrejection', (e)=>{
        if (e && e.reason && /CSRF failed/i.test(e.reason.message || e.reason)) {
            if (window.AdminAPI) window.AdminAPI.handleCsrfFailure('Action');
        }
    });

    // Sync initial token if injected late
    if (window.__ADMIN_CSRF && (!AdminAPI.csrf || AdminAPI.csrf === '')) {
        AdminAPI.setCSRF(window.__ADMIN_CSRF);
        console.debug('[AdminAPI] CSRF token synchronized after load.');
    }

    // Expose globally
    window.AdminAPI = AdminAPI;
})();