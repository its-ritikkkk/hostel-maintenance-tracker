/**
 * ===============================================================
 * IIT (ISM) Dhanbad - Multi-Hostel Warden Management Script
 * File: assets/js/admin.js
 * ===============================================================
 * Implements:
 * 1. Asynchronous status updates via Fetch API without page reload
 * 2. Multi-hostel warden isolation: updates and lists strictly scoped to assigned hostel
 * 3. 5-Second polling for status changes and new tickets
 * 4. Dynamic KPI statistics counter adjustments
 */

if (window.adminTrackerTimer) {
    clearInterval(window.adminTrackerTimer);
    window.adminTrackerTimer = null;
}

document.addEventListener('DOMContentLoaded', () => {
    initInlineStatusChangers();
    initAdminFilters();
    initAdminPolling();
});

/**
 * 1. Asynchronous Status Change Handler for Wardens
 */
function initInlineStatusChangers() {
    document.addEventListener('change', async (e) => {
        if (!e.target.classList.contains('status-select-inline')) return;

        const select = e.target;
        const complaintId = select.getAttribute('data-id');
        const newStatus = select.value;
        const originalStatus = select.getAttribute('data-current') || select.value;

        select.disabled = true;

        try {
            const baseUrl = (window.APP_CONFIG && window.APP_CONFIG.baseUrl !== undefined) 
                ? window.APP_CONFIG.baseUrl 
                : (window.location.pathname.includes('/tracker') ? '/tracker' : '');
            const csrfToken = (window.APP_CONFIG && window.APP_CONFIG.csrfToken) || '';

            const endpoint = `${baseUrl}/api/update_status.php`;

            const res = await apiFetch(endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    complaint_id: parseInt(complaintId, 10),
                    status: newStatus,
                    csrf_token: csrfToken
                })
            });

            if (res && res.data && res.data.success) {
                select.setAttribute('data-current', newStatus);

                const badge = document.getElementById(`status-badge-${complaintId}`);
                const badgeClass = (res.data && res.data.badge_class) ? res.data.badge_class : getStatusBadgeClass(newStatus);
                if (badge) {
                    badge.textContent = newStatus;
                    badge.className = `badge ${badgeClass}`;

                    badge.style.transform = 'scale(1.15)';
                    setTimeout(() => { badge.style.transform = 'scale(1)'; }, 300);
                }

                const row = document.getElementById(`complaint-row-${complaintId}`);
                if (row) {
                    row.style.backgroundColor = '#ecfdf5';
                    setTimeout(() => { row.style.backgroundColor = ''; }, 1200);
                }

                showToast(res.data.message || `Status updated to ${newStatus}`, 'success');
                updateKpiCounters();
            } else {
                select.value = originalStatus;
                const errorMsg = (res && res.data && res.data.message) ? res.data.message : 'Failed to update complaint status.';
                showToast(errorMsg, 'error');
            }
        } catch (err) {
            console.error('Inline status update error:', err);
            select.value = originalStatus;
            showToast('Network error while updating status. Please try again.', 'error');
        } finally {
            select.disabled = false;
        }
    });
}

/**
 * Update Warden KPI Counters dynamically based on visible table data
 */
function updateKpiCounters() {
    const tableBody = document.getElementById('adminComplaintsTableBody');
    if (!tableBody) return;

    let total = 0;
    let submitted = 0;
    let inProgress = 0;
    let resolved = 0;

    const badges = tableBody.querySelectorAll('[id^="status-badge-"]');
    badges.forEach(b => {
        total++;
        const text = b.textContent.trim();
        if (text === 'Submitted') submitted++;
        else if (text === 'In Progress') inProgress++;
        else if (text === 'Resolved') resolved++;
    });

    const totalEl = document.getElementById('statTotal');
    const submittedEl = document.getElementById('statSubmitted');
    const inProgressEl = document.getElementById('statInProgress');
    const resolvedEl = document.getElementById('statResolved');

    if (totalEl) totalEl.textContent = total;
    if (submittedEl) submittedEl.textContent = submitted;
    if (inProgressEl) inProgressEl.textContent = inProgress;
    if (resolvedEl) resolvedEl.textContent = resolved;
}

/**
 * 2. Asynchronous Complaint Filtering for Wardens
 */
function initAdminFilters() {
    const filterForm = document.getElementById('adminFilterForm');
    if (!filterForm) return;

    const statusSelect = document.getElementById('adminFilterStatus');
    const categorySelect = document.getElementById('adminFilterCategory');
    const searchInput = document.getElementById('adminFilterSearch');

    const handleFilterChange = async () => {
        const status = statusSelect ? statusSelect.value : '';
        const category = categorySelect ? categorySelect.value : '';
        const search = searchInput ? searchInput.value.trim() : '';

        await reloadAdminTable({ status, category, search });
    };

    if (statusSelect) statusSelect.addEventListener('change', handleFilterChange);
    if (categorySelect) categorySelect.addEventListener('change', handleFilterChange);
    if (searchInput) {
        let debounceTimer;
        searchInput.addEventListener('input', () => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(handleFilterChange, 350);
        });
    }

    const resetBtn = document.getElementById('adminFilterResetBtn');
    if (resetBtn) {
        resetBtn.addEventListener('click', (e) => {
            e.preventDefault();
            if (statusSelect) statusSelect.value = '';
            if (categorySelect) categorySelect.value = '';
            if (searchInput) searchInput.value = '';
            handleFilterChange();
        });
    }
}

/**
 * Reload Warden complaints table via Fetch API
 */
async function reloadAdminTable(filters = {}) {
    const tbody = document.getElementById('adminComplaintsTableBody');
    if (!tbody) return;

    const countBadge = document.getElementById('adminTableCountBadge');
    const syncIndicator = document.getElementById('syncIndicator');
    if (syncIndicator) syncIndicator.classList.add('syncing');

    const params = new URLSearchParams();
    if (filters.status) params.append('status', filters.status);
    if (filters.category) params.append('category', filters.category);
    if (filters.search) params.append('search', filters.search);

    const url = `${window.APP_CONFIG.baseUrl}/api/get_complaints.php?${params.toString()}`;
    const res = await apiFetch(url);

    if (syncIndicator) syncIndicator.classList.remove('syncing');

    if (res.ok && res.data.success) {
        const complaints = res.data.complaints;

        if (countBadge) {
            countBadge.textContent = `${complaints.length} complaint${complaints.length === 1 ? '' : 's'}`;
        }

        if (complaints.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="8" class="empty-state">
                        <div class="empty-state-icon">📋</div>
                        <h4>No complaints matching criteria</h4>
                        <p>No complaints match your filters for this hostel.</p>
                    </td>
                </tr>
            `;
            return;
        }

        tbody.innerHTML = complaints.map(c => `
            <tr id="complaint-row-${c.id}">
                <td><strong>#${c.id}</strong></td>
                <td>
                    <strong>${escapeHtml(c.student_name)}</strong><br>
                    <span style="font-size:0.75rem; color:var(--text-muted);">${escapeHtml(c.student_email)}</span>
                </td>
                <td class="complaint-title-cell">
                    <a href="${window.APP_CONFIG.baseUrl}/admin/update_complaint.php?id=${c.id}" class="complaint-title-link">
                        ${escapeHtml(c.title)}
                    </a>
                    <span class="complaint-meta-sub">${escapeHtml(c.description.substring(0, 60))}${c.description.length > 60 ? '...' : ''}</span>
                </td>
                <td><span class="cat-tag">${escapeHtml(c.category)}</span></td>
                <td>
                    <strong>Room ${escapeHtml(c.room_number)}</strong>
                </td>
                <td>
                    <span id="status-badge-${c.id}" class="badge ${getStatusBadgeClass(c.status)}">
                        ${escapeHtml(c.status)}
                    </span>
                </td>
                <td>
                    <select class="status-select-inline" data-id="${c.id}" data-current="${escapeHtml(c.status)}">
                        <option value="Submitted" ${c.status === 'Submitted' ? 'selected' : ''}>Submitted</option>
                        <option value="In Progress" ${c.status === 'In Progress' ? 'selected' : ''}>In Progress</option>
                        <option value="Resolved" ${c.status === 'Resolved' ? 'selected' : ''}>Resolved</option>
                    </select>
                </td>
                <td>
                    <a href="${window.APP_CONFIG.baseUrl}/admin/update_complaint.php?id=${c.id}" class="btn btn-sm btn-outline">
                        Manage &rarr;
                    </a>
                </td>
            </tr>
        `).join('');
    }
}

/**
 * 3. 5-Second Real-Time Status Polling for Warden
 */
function initAdminPolling() {
    const tableBody = document.getElementById('adminComplaintsTableBody');
    if (!tableBody) return;

    window.adminTrackerTimer = setInterval(async () => {
        const syncIndicator = document.getElementById('syncIndicator');
        if (syncIndicator) syncIndicator.classList.add('syncing');

        const res = await apiFetch(`${window.APP_CONFIG.baseUrl}/api/get_status_updates.php`);

        if (syncIndicator) syncIndicator.classList.remove('syncing');

        if (res.ok && res.data.success && res.data.statuses) {
            const statuses = res.data.statuses;

            for (const [id, data] of Object.entries(statuses)) {
                const badge = document.getElementById(`status-badge-${id}`);
                const select = document.querySelector(`.status-select-inline[data-id="${id}"]`);

                if (badge && select) {
                    const currentStatus = badge.textContent.trim();
                    if (currentStatus !== data.status) {
                        badge.textContent = data.status;
                        badge.className = `badge ${data.badge_class}`;

                        select.value = data.status;
                        select.setAttribute('data-current', data.status);

                        const row = document.getElementById(`complaint-row-${id}`);
                        if (row) {
                            row.style.backgroundColor = '#ecfdf5';
                            setTimeout(() => { row.style.backgroundColor = ''; }, 1500);
                        }
                    }
                }
            }
        }
    }, 5000);
}

window.addEventListener('beforeunload', () => {
    if (window.adminTrackerTimer) {
        clearInterval(window.adminTrackerTimer);
        window.adminTrackerTimer = null;
    }
});
