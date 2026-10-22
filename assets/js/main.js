/**
 * ===============================================================
 * IIT (ISM) Dhanbad - Hostel Maintenance and Complaint Tracker
 * Shared Client Utilities (assets/js/main.js)
 * ===============================================================
 * Provides reusable functions for:
 * 1. Toast notifications (floating success, error, info messages)
 * 2. HTML escaping to prevent XSS in dynamic renders
 * 3. Fetch API wrapper with automatic CSRF token inclusion
 * 4. Date formatting helpers
 */

// Toast notification helper
function showToast(message, type = 'info', duration = 4000) {
    const container = document.getElementById('toastContainer');
    if (!container) return;

    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    
    // Choose icon
    let icon = 'ℹ️';
    if (type === 'success') icon = '✅';
    if (type === 'error') icon = '⚠️';

    toast.innerHTML = `
        <div style="display:flex; align-items:center; gap:0.5rem;">
            <span>${icon}</span>
            <span>${escapeHtml(message)}</span>
        </div>
        <button type="button" style="background:none; border:none; font-size:1.1rem; cursor:pointer; color:#64748b;" onclick="this.parentElement.remove()">&times;</button>
    `;

    container.appendChild(toast);

    // Auto dismiss
    setTimeout(() => {
        if (toast.parentElement) {
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(10px)';
            toast.style.transition = 'all 0.3s ease';
            setTimeout(() => toast.remove(), 300);
        }
    }, duration);
}

// Escape HTML entities to prevent Cross-Site Scripting (XSS)
function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

// Return human-readable formatted date: e.g. "Oct 22, 2026, 06:14 PM"
function formatDateTime(dateStr) {
    if (!dateStr) return 'N/A';
    const date = new Date(dateStr.replace(' ', 'T'));
    if (isNaN(date.getTime())) return dateStr;
    return date.toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

// Helper to determine status badge CSS class
function getStatusBadgeClass(status) {
    switch (status) {
        case 'Submitted':
            return 'badge-submitted';
        case 'In Progress':
            return 'badge-in-progress';
        case 'Resolved':
            return 'badge-resolved';
        default:
            return 'badge-default';
    }
}

// Fetch API helper that automatically injects CSRF headers, enforces timeouts, and parses JSON safely
async function apiFetch(url, options = {}) {
    const defaultHeaders = {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
    };

    // If CSRF token is available from global APP_CONFIG, attach it
    if (window.APP_CONFIG && window.APP_CONFIG.csrfToken) {
        defaultHeaders['X-CSRF-TOKEN'] = window.APP_CONFIG.csrfToken;
    }

    // Merge custom headers
    options.headers = {
        ...defaultHeaders,
        ...(options.headers || {})
    };

    // 15-second request timeout to prevent indefinite hanging
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), 15000);
    options.signal = controller.signal;

    try {
        const response = await fetch(url, options);
        clearTimeout(timeoutId);

        let data = null;
        const contentType = response.headers.get('content-type') || '';

        if (contentType.includes('application/json')) {
            try {
                data = await response.json();
            } catch (jsonErr) {
                console.warn('JSON parsing warning:', jsonErr);
            }
        }

        // If not JSON or parsing failed, extract text
        if (!data) {
            const rawText = await response.text();
            try {
                data = JSON.parse(rawText);
            } catch {
                data = {
                    success: response.ok,
                    message: response.ok ? 'Operation completed successfully.' : (rawText.trim() || `Request failed with HTTP status ${response.status}`)
                };
            }
        }

        return {
            ok: response.ok,
            status: response.status,
            data: data || { success: response.ok, message: response.ok ? 'Success' : 'Unknown error' }
        };
    } catch (err) {
        clearTimeout(timeoutId);
        const isAbort = err.name === 'AbortError';
        const msg = isAbort 
            ? 'Request timed out after 15 seconds. Please try again.' 
            : 'Network error or server unreachable. Please check connection.';
        console.error('Fetch API error:', err);
        return {
            ok: false,
            status: 0,
            data: { success: false, message: msg }
        };
    }
}
