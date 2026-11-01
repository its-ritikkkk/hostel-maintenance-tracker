// ====================================================================
// File: assets/script.js
// Purpose: Simple, clean JavaScript for live updates, filters, and modal
// Project: Hostel Maintenance and Complaint Tracker
// ====================================================================

document.addEventListener('DOMContentLoaded', function () {
    // ----------------------------------------------------------------
    // 1. Helper: Update Warden Metric Cards Dynamically
    // ----------------------------------------------------------------
    function updateMetricCards() {
        let submitted = 0;
        let inProgress = 0;
        let resolved = 0;
        const rows = document.querySelectorAll('.complaint-row');

        rows.forEach(row => {
            const status = row.dataset.status;
            if (status === 'Submitted') submitted++;
            else if (status === 'In Progress') inProgress++;
            else if (status === 'Resolved') resolved++;
        });

        const statTotal = document.getElementById('statTotal');
        const statSubmitted = document.getElementById('statSubmitted');
        const statInProgress = document.getElementById('statInProgress');
        const statResolved = document.getElementById('statResolved');

        if (statTotal) statTotal.textContent = rows.length;
        if (statSubmitted) statSubmitted.textContent = submitted;
        if (statInProgress) statInProgress.textContent = inProgress;
        if (statResolved) statResolved.textContent = resolved;
    }

    // ----------------------------------------------------------------
    // 2. Helper: Synchronize Status Across All DOM Elements
    // ----------------------------------------------------------------
    function syncComplaintStatus(complaintId, newStatus) {
        // A. Table Status Badge
        const badge = document.querySelector(`.status-badge[data-complaint-id="${complaintId}"]`);
        if (badge && badge.textContent.trim() !== newStatus) {
            badge.textContent = newStatus;
            badge.className = 'badge status-badge status-' + newStatus.toLowerCase().replace(' ', '-');
        }

        // B. Row data-status attribute (used for filtering and metric calculations)
        const row = document.querySelector(`tr[data-complaint-id="${complaintId}"]`);
        if (row && row.dataset.status !== newStatus) {
            row.dataset.status = newStatus;
        }

        // C. Warden dropdown select
        const select = document.querySelector(`.warden-status-select[data-complaint-id="${complaintId}"]`);
        if (select && select.value !== newStatus) {
            select.value = newStatus;
        }

        // D. View Details button dataset
        const viewBtn = document.querySelector(`.btn-view-complaint[data-id="${complaintId}"]`);
        if (viewBtn) {
            viewBtn.dataset.status = newStatus;
        }

        // E. Details Modal if currently open for this ticket
        const modal = document.getElementById('complaintModal');
        const modalId = document.getElementById('modalComplaintId');
        const formattedId = '#CMP-' + String(complaintId).padStart(3, '0');
        if (modal && modal.classList.contains('active') && modalId && modalId.textContent === formattedId) {
            const modalStatus = document.getElementById('modalStatus');
            if (modalStatus) {
                modalStatus.textContent = newStatus;
                modalStatus.className = 'badge status-' + newStatus.toLowerCase().replace(' ', '-');
            }
        }
    }

    // ----------------------------------------------------------------
    // 3. Filter Complaints by Category and Status
    // ----------------------------------------------------------------
    const catFilter = document.getElementById('filterCategory');
    const statusFilter = document.getElementById('filterStatus');
    const countEl = document.getElementById('visibleComplaintsCount');

    function applyFilters() {
        const selectedCat = catFilter ? catFilter.value : '';
        const selectedStatus = statusFilter ? statusFilter.value : '';
        let visibleCount = 0;

        document.querySelectorAll('.complaint-row').forEach(row => {
            const rowCat = row.dataset.category || '';
            const rowStatus = row.dataset.status || '';

            const matchCat = !selectedCat || rowCat === selectedCat;
            const matchStatus = !selectedStatus || rowStatus === selectedStatus;

            if (matchCat && matchStatus) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });

        if (countEl) countEl.textContent = visibleCount;
    }

    if (catFilter) catFilter.addEventListener('change', applyFilters);
    if (statusFilter) statusFilter.addEventListener('change', applyFilters);

    // ----------------------------------------------------------------
    // 4. Warden Quick Status Update (Via dropdown change)
    // ----------------------------------------------------------------
    document.querySelectorAll('.warden-status-select').forEach(select => {
        select.addEventListener('change', function () {
            const complaintId = this.dataset.complaintId;
            const newStatus = this.value;

            fetch('api/update_status.php', {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ complaint_id: complaintId, status: newStatus })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    syncComplaintStatus(complaintId, newStatus);
                    applyFilters();
                    updateMetricCards();
                } else {
                    alert('Could not update status: ' + data.message);
                }
            })
            .catch(() => alert('Network error updating status.'));
        });
    });

    // ----------------------------------------------------------------
    // 5. Asynchronous Status Refresh (Polls every 5 seconds)
    // ----------------------------------------------------------------
    const complaintsTable = document.getElementById('complaintsTable');
    if (complaintsTable) {
        setInterval(function () {
            fetch('api/get_statuses.php')
                .then(res => res.json())
                .then(data => {
                    if (data.success && Array.isArray(data.statuses)) {
                        let changed = false;
                        data.statuses.forEach(item => {
                            const row = document.querySelector(`tr[data-complaint-id="${item.id}"]`);
                            if (row && row.dataset.status !== item.status) {
                                syncComplaintStatus(item.id, item.status);
                                changed = true;
                            }
                        });
                        if (changed) {
                            applyFilters();
                            updateMetricCards();
                        }
                    }
                })
                .catch(() => { /* silent fail on network hiccups */ });
        }, 5000);
    }

    // ----------------------------------------------------------------
    // 6. Complaint Details Modal (View button popup)
    // ----------------------------------------------------------------
    const modal = document.getElementById('complaintModal');
    const closeBtn = document.getElementById('closeModalBtn');

    document.querySelectorAll('.btn-view-complaint').forEach(btn => {
        btn.addEventListener('click', function () {
            if (!modal) return;
            const d = this.dataset;

            document.getElementById('modalTitle').textContent = d.title || 'Complaint Details';
            document.getElementById('modalComplaintId').textContent = '#CMP-' + String(d.id).padStart(3, '0');
            document.getElementById('modalStudent').textContent = (d.studentName || '') + ' (' + (d.admissionNo || '') + ')';
            document.getElementById('modalCategory').textContent = d.category || '';
            document.getElementById('modalDate').textContent = d.date || '';
            document.getElementById('modalDescription').textContent = d.description || '';

            const statusBadge = document.getElementById('modalStatus');
            if (statusBadge) {
                statusBadge.textContent = d.status || '';
                statusBadge.className = 'badge status-' + (d.status || '').toLowerCase().replace(' ', '-');
            }

            modal.classList.add('active');
        });
    });

    if (closeBtn) closeBtn.addEventListener('click', () => modal.classList.remove('active'));
    if (modal) modal.addEventListener('click', (e) => { if (e.target === modal) modal.classList.remove('active'); });
});
