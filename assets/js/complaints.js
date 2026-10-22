/**
 * ===============================================================
 * IIT (ISM) Dhanbad - Hostel Maintenance and Complaint Tracker
 * Student Complaints Script (assets/js/complaints.js)
 * ===============================================================
 * Implements:
 * 1. Asynchronous complaint lodging with Fetch API
 * 2. Asynchronous complaint filtering (category & status)
 * 3. 5-Second periodic status refresh without full page reload
 * 4. Proper timer management to prevent duplicate background intervals
 */

// Global timer reference to avoid duplicate timers
if (window.hostelTrackerTimer) {
    clearInterval(window.hostelTrackerTimer);
    window.hostelTrackerTimer = null;
}

document.addEventListener('DOMContentLoaded', () => {
    initComplaintSubmissionForm();
    initComplaintFilters();
    initRealtimeStatusPolling();
});

/**
 * 1. Asynchronous Complaint Submission via Fetch API
 */
function initComplaintSubmissionForm() {
    const form = document.getElementById('createComplaintForm');
    if (!form) return;

    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn ? submitBtn.innerHTML : 'Submit Maintenance Complaint';
        const feedbackBox = document.getElementById('submissionFeedback');
        if (feedbackBox) feedbackBox.innerHTML = '';

        // Collect form data
        const formData = new FormData(form);
        const title = (formData.get('title') || '').trim();
        const category = (formData.get('category') || '').trim();
        const hostel = (formData.get('hostel') || '').trim();
        const room_number = (formData.get('room_number') || '').trim();
        const description = (formData.get('description') || '').trim();
        const csrf_token = formData.get('csrf_token') || (window.APP_CONFIG && window.APP_CONFIG.csrfToken) || '';

        // Basic client-side validation
        if (!title || !category || !hostel || !room_number || !description) {
            const errorMsg = 'Please fill in all required fields marked with *.';
            showToast(errorMsg, 'error');
            if (feedbackBox) {
                feedbackBox.innerHTML = `<div class="alert alert-error">${escapeHtml(errorMsg)}</div>`;
                feedbackBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
            return;
        }

        if (title.length < 5 || title.length > 150) {
            const errorMsg = 'Complaint title must be between 5 and 150 characters.';
            showToast(errorMsg, 'error');
            if (feedbackBox) {
                feedbackBox.innerHTML = `<div class="alert alert-error">${escapeHtml(errorMsg)}</div>`;
                feedbackBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
            return;
        }

        if (description.length < 10) {
            const errorMsg = 'Please provide a more detailed description (at least 10 characters).';
            showToast(errorMsg, 'error');
            if (feedbackBox) {
                feedbackBox.innerHTML = `<div class="alert alert-error">${escapeHtml(errorMsg)}</div>`;
                feedbackBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
            return;
        }

        // Show loading state
        if (submitBtn) {
            submitBtn.classList.add('loading');
            submitBtn.disabled = true;
            submitBtn.innerHTML = 'Submitting Request...';
        }

        try {
            const payload = {
                csrf_token,
                title,
                category,
                hostel,
                room_number,
                description
            };

            const baseUrl = (window.APP_CONFIG && window.APP_CONFIG.baseUrl !== undefined) 
                ? window.APP_CONFIG.baseUrl 
                : (window.location.pathname.includes('/tracker') ? '/tracker' : '');
            const endpoint = `${baseUrl}/api/submit_complaint.php`;

            const res = await apiFetch(endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });

            if (res && res.data && res.data.success) {
                showToast(res.data.message || 'Complaint submitted successfully!', 'success');
                form.reset();

                // Display success message banner with redirect links
                if (feedbackBox) {
                    feedbackBox.innerHTML = `
                        <div class="alert alert-success" style="margin-bottom: 1.5rem;">
                            <div>
                                <strong>✅ Success!</strong> ${escapeHtml(res.data.message)}<br>
                                Initial status is automatically set to <strong>Submitted</strong>.
                            </div>
                            <div style="margin-top: 0.75rem; display: flex; gap: 0.5rem; flex-wrap: wrap;">
                                <a href="${baseUrl}/student/complaint_details.php?id=${res.data.complaint_id}" class="btn btn-sm btn-primary">
                                    View Complaint #${res.data.complaint_id} &rarr;
                                </a>
                                <a href="${baseUrl}/student/my_complaints.php" class="btn btn-sm btn-outline">
                                    View All My Complaints
                                </a>
                            </div>
                        </div>
                    `;
                    feedbackBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                } else {
                    setTimeout(() => {
                        window.location.href = `${baseUrl}/student/my_complaints.php`;
                    }, 1200);
                }
            } else {
                const errorMsg = (res && res.data && res.data.message) 
                    ? res.data.message 
                    : 'Failed to submit complaint. Please try again.';
                showToast(errorMsg, 'error');
                if (feedbackBox) {
                    feedbackBox.innerHTML = `<div class="alert alert-error" style="margin-bottom: 1.5rem;"><strong>Error:</strong> ${escapeHtml(errorMsg)}</div>`;
                    feedbackBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }
            }
        } catch (err) {
            console.error('Complaint submit error:', err);
            const errorMsg = 'An unexpected error occurred while submitting your complaint. Please try again.';
            showToast(errorMsg, 'error');
            if (feedbackBox) {
                feedbackBox.innerHTML = `<div class="alert alert-error" style="margin-bottom: 1.5rem;"><strong>Error:</strong> ${escapeHtml(errorMsg)}</div>`;
            }
        } finally {
            // ALWAYS reset button state, preventing any infinite loading spinner
            if (submitBtn) {
                submitBtn.classList.remove('loading');
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        }
    });
}

/**
 * 2. Asynchronous Complaint Filtering without Page Reload
 */
function initComplaintFilters() {
    const filterForm = document.getElementById('complaintFilterForm');
    if (!filterForm) return;

    const statusSelect = document.getElementById('filterStatus');
    const categorySelect = document.getElementById('filterCategory');
    const searchInput = document.getElementById('filterSearch');

    const handleFilterChange = async () => {
        const status = statusSelect ? statusSelect.value : '';
        const category = categorySelect ? categorySelect.value : '';
        const search = searchInput ? searchInput.value.trim() : '';

        await reloadComplaintsTable({ status, category, search });
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

    // Reset button
    const resetBtn = document.getElementById('filterResetBtn');
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
 * Reload complaints table via Fetch API
 */
async function reloadComplaintsTable(filters = {}) {
    const tbody = document.getElementById('complaintsTableBody');
    if (!tbody) return;

    const countBadge = document.getElementById('tableCountBadge');
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
                    <td colspan="7" class="empty-state">
                        <div class="empty-state-icon">📭</div>
                        <h4>No complaints found</h4>
                        <p>No complaints match your current filter criteria.</p>
                    </td>
                </tr>
            `;
            return;
        }

        tbody.innerHTML = complaints.map(c => `
            <tr id="complaint-row-${c.id}">
                <td><strong>#${c.id}</strong></td>
                <td class="complaint-title-cell">
                    <a href="${window.APP_CONFIG.baseUrl}/student/complaint_details.php?id=${c.id}" class="complaint-title-link">
                        ${escapeHtml(c.title)}
                    </a>
                    <span class="complaint-meta-sub">${escapeHtml(c.description.substring(0, 65))}${c.description.length > 65 ? '...' : ''}</span>
                </td>
                <td><span class="cat-tag">${escapeHtml(c.category)}</span></td>
                <td>
                    <strong>${escapeHtml(c.hostel)}</strong><br>
                    <span style="font-size:0.78rem; color:var(--text-muted);">${escapeHtml(c.room_number)}</span>
                </td>
                <td>
                    <span id="status-badge-${c.id}" class="badge ${getStatusBadgeClass(c.status)}">
                        ${escapeHtml(c.status)}
                    </span>
                </td>
                <td style="font-size:0.8rem; color:var(--text-muted);">
                    ${formatDateTime(c.created_at)}
                </td>
                <td>
                    <a href="${window.APP_CONFIG.baseUrl}/student/complaint_details.php?id=${c.id}" class="btn btn-sm btn-outline">
                        View
                    </a>
                </td>
            </tr>
        `).join('');
    }
}

/**
 * 3. Asynchronous 5-Second Status Refresh (Essential Requirement)
 * Checks for status changes from the server every 5 seconds and updates the UI in place.
 */
function initRealtimeStatusPolling() {
    const tableBody = document.getElementById('complaintsTableBody');
    const singleComplaintDetail = document.getElementById('singleComplaintDetail');

    // Only run polling on pages that display complaints or complaint details
    if (!tableBody && !singleComplaintDetail) return;

    const pollIntervalMs = 5000; // 5 Seconds

    window.hostelTrackerTimer = setInterval(async () => {
        // Collect complaint IDs present in the DOM
        let ids = [];
        if (tableBody) {
            const rows = tableBody.querySelectorAll('tr[id^="complaint-row-"]');
            rows.forEach(r => {
                const id = r.id.replace('complaint-row-', '');
                if (id) ids.push(id);
            });
        } else if (singleComplaintDetail) {
            const id = singleComplaintDetail.getAttribute('data-complaint-id');
            if (id) ids.push(id);
        }

        if (ids.length === 0) return;

        const syncIndicator = document.getElementById('syncIndicator');
        if (syncIndicator) syncIndicator.classList.add('syncing');

        const endpoint = `${window.APP_CONFIG.baseUrl}/api/get_status_updates.php?ids=${ids.join(',')}`;
        const res = await apiFetch(endpoint);

        if (syncIndicator) syncIndicator.classList.remove('syncing');

        if (res.ok && res.data.success && res.data.statuses) {
            const statuses = res.data.statuses;

            for (const [id, data] of Object.entries(statuses)) {
                const badge = document.getElementById(`status-badge-${id}`);
                if (badge) {
                    const currentStatus = badge.textContent.trim();
                    if (currentStatus !== data.status) {
                        // Status changed asynchronously!
                        badge.textContent = data.status;
                        badge.className = `badge ${data.badge_class}`;

                        // Visual highlight animation so user immediately notices the live change
                        badge.style.transform = 'scale(1.2)';
                        badge.style.transition = 'transform 0.3s ease';
                        setTimeout(() => { badge.style.transform = 'scale(1)'; }, 400);

                        const row = document.getElementById(`complaint-row-${id}`);
                        if (row) {
                            row.style.backgroundColor = '#ecfdf5';
                            setTimeout(() => { row.style.backgroundColor = ''; }, 1500);
                        }

                        showToast(`Complaint #${id} status changed to: ${data.status}`, 'success');

                        // If admin remarks were updated on single detail page
                        const remarksBox = document.getElementById('adminRemarksText');
                        if (remarksBox && data.admin_remarks) {
                            remarksBox.textContent = data.admin_remarks;
                        }
                    }
                }
            }
        }
    }, pollIntervalMs);
}

// Clean up timer if user navigates away
window.addEventListener('beforeunload', () => {
    if (window.hostelTrackerTimer) {
        clearInterval(window.hostelTrackerTimer);
        window.hostelTrackerTimer = null;
    }
});
