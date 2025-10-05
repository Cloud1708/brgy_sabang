// admin_access/admin_utils.js
// Shared helper API layer & UI utilities for admin dashboard sections.
// PATCH: Added basePath handling so relative endpoints work from subdirectories.

const AdminAPI = {
    csrf: window.__ADMIN_CSRF || '',

    // Determine the directory (with trailing slash) of the current page.
    // e.g.  /project/sub/  from  /project/sub/dashboard_admin.php
    basePath: (function () {
        if (window.__APP_BASE) return window.__APP_BASE; // Allow manual override if ever set.
        let path = window.location.pathname || '/';
        if (!path.endsWith('/')) {
            path = path.substring(0, path.lastIndexOf('/') + 1);
        }
        return path;
    })(),

    setCSRF(token) {
        this.csrf = token;
    },

    // Internal: normalize endpoint into a full URL respecting subdirectory deployment.
    _resolve(endpoint) {
        // Full URL already?
        if (/^https?:\/\//i.test(endpoint)) {
            return new URL(endpoint);
        }
        // Absolute path from domain root
        if (endpoint.startsWith('/')) {
            return new URL(endpoint, window.location.origin);
        }
        // Otherwise treat as relative to basePath (project folder)
        return new URL(this.basePath + endpoint.replace(/^\.?\//, ''), window.location.origin);
    },

    async get(endpoint, params = {}) {
        const url = this._resolve(endpoint);
        Object.keys(params).forEach(key => {
            const v = params[key];
            if (v !== undefined && v !== null) {
                url.searchParams.append(key, v);
            }
        });

        let response;
        try {
            console.debug('[AdminAPI GET]', url.href);
            response = await fetch(url.href, {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' }
            });
        } catch (netErr) {
            throw new Error('Network error: ' + netErr.message);
        }

        if (!response.ok) {
            let text;
            try { text = await response.text(); } catch {}
            throw new Error(`HTTP ${response.status}${text ? ' - ' + text : ''}`);
        }

        let json;
        try {
            json = await response.json();
        } catch {
            throw new Error('Invalid JSON response');
        }
        if (json && json.success === false && json.error) {
            throw new Error(json.error);
        }
        return json;
    },

    async post(endpoint, data = {}) {
        if (!data.csrf_token) data.csrf_token = this.csrf;

        const formData = new URLSearchParams();
        Object.keys(data).forEach(key => {
            const v = data[key];
            if (v !== null && v !== undefined) {
                formData.append(key, v);
            }
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
                    'Accept': 'application/json'
                },
                body: formData
            });
        } catch (netErr) {
            throw new Error('Network error: ' + netErr.message);
        }

        if (!response.ok) {
            let text;
            try { text = await response.text(); } catch {}
            throw new Error(`HTTP ${response.status}${text ? ' - ' + text : ''}`);
        }

        let json;
        try {
            json = await response.json();
        } catch {
            throw new Error('Invalid JSON response');
        }
        if (json && json.success === false && json.error) {
            throw new Error(json.error);
        }
        return json;
    },

    showError(message) {
        this.showAlert(message, 'danger');
    },

    showSuccess(message) {
        this.showAlert(message, 'success');
    },

    showInfo(message) {
        this.showAlert(message, 'info');
    },

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

        if (timeoutMs > 0) {
            setTimeout(() => {
                try { alertDiv.remove(); } catch {}
            }, timeoutMs);
        }
    },

    formatDate(dateStr) {
        if (!dateStr) return 'N/A';
        const date = new Date(dateStr);
        if (isNaN(date.getTime())) return dateStr;
        return date.toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });
    },

    formatDateTime(dateStr) {
        if (!dateStr) return 'N/A';
        const date = new Date(dateStr.replace(' ', 'T'));
        if (isNaN(date.getTime())) return dateStr;
        return date.toLocaleString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
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
        return new Promise(resolve => {
            const ok = window.confirm(message);
            resolve(ok);
        });
    }
};

// If CSRF was set after this file loaded, sync it.
if (window.__ADMIN_CSRF && (!AdminAPI.csrf || AdminAPI.csrf === '')) {
    AdminAPI.setCSRF(window.__ADMIN_CSRF);
    console.debug('[AdminAPI] CSRF token synchronized after load.');
}