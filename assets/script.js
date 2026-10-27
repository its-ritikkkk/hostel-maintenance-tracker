// ====================================================================
// File: assets/script.js
// Purpose: Simple, clean JavaScript for live updates, filters, and modal
// Project: Hostel Maintenance and Complaint Tracker
// ====================================================================

document.addEventListener('DOMContentLoaded', function () {
    // ----------------------------------------------------------------
    // 1. Live Auto-Refresh (Checks for status updates every 5 seconds)
    // ----------------------------------------------------------------
    const complaintsTable = document.getElementById('complaintsTable');

    if (complaintsTable) {
        setInterval(fetchLatestStatuses, 5000);
    }

    function fetchLatestStatuses() {
        const studentAdm = document.body.dataset.studentAdmission || '';
        const url = 'api/get_statuses.php' + (studentAdm ? '?admission_no=' + encodeURIComponent(studentAdm) : '');

        fetch(url)
            .then(res => res.json())
            .then(data => {
                if (!data.success || !Array.isArray(data.statuses)) return;

                // Update last sync time clock
                const timeEl = document.getElementById('lastSyncTime');
                if (timeEl && data.timestamp) timeEl.textContent = data.timestamp;

                // Update each complaint status badge if it changed
                data.statuses.forEach(item => {
                    const badge = document.querySelector(`.status-badge[data-complaint-id="${item.id}"]`);
                    if (badge && badge.textContent.trim() !== item.status) {
                        badge.textContent = item.status;
                        badge.className = 'badge status-badge status-' + item.status.toLowerCase().replace(' ', '-');
                        badge.classList.add('status-updated');
                        setTimeout(() => badge.classList.remove('status-updated'), 1000);
                    }
                });
            })
            .catch(err => console.log('Auto-refresh notice:', err));
    }

    // ----------------------------------------------------------------
    // 2. Warden Quick Status Update (Via dropdown change)
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
                    // Update matching badge in the row
                    const badge = document.querySelector(`.status-badge[data-complaint-id="${complaintId}"]`);
                    if (badge) {
                        badge.textContent = newStatus;
                        badge.className = 'badge status-badge status-' + newStatus.toLowerCase().replace(' ', '-');
                    }
                    // Update row's data-status attribute for filtering
                    const row = document.querySelector(`tr[data-complaint-id="${complaintId}"]`);
                    if (row) row.dataset.status = newStatus;
                } else {
                    alert('Could not update status: ' + data.message);
                }
            })
            .catch(() => alert('Network error updating status.'));
        });
    });

    // ----------------------------------------------------------------
    // 3. Table Filtering & Search
    // ----------------------------------------------------------------
    const catFilter = document.getElementById('categoryFilter');
    const statusFilter = document.getElementById('statusFilter');
    const searchFilter = document.getElementById('searchFilter');

    function applyFilter() {
        const catVal = (catFilter ? catFilter.value : 'All').toLowerCase();
        const statVal = (statusFilter ? statusFilter.value : 'All').toLowerCase();
        const searchVal = (searchFilter ? searchFilter.value : '').toLowerCase().trim();

        const rows = document.querySelectorAll('.complaint-row');
        let visibleCount = 0;

        rows.forEach(row => {
            const rowCat = (row.dataset.category || '').toLowerCase();
            const rowStat = (row.dataset.status || '').toLowerCase();
            const rowText = row.innerText.toLowerCase();

            const matchCat = (catVal === 'all' || rowCat === catVal);
            const matchStat = (statVal === 'all' || rowStat === statVal);
            const matchSearch = (!searchVal || rowText.includes(searchVal));

            if (matchCat && matchStat && matchSearch) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });

        // Update count & empty message
        const countEl = document.getElementById('visibleComplaintsCount');
        if (countEl) countEl.textContent = visibleCount;

        const noResults = document.getElementById('noFilterResultsRow');
        if (noResults) noResults.style.display = (visibleCount === 0 && rows.length > 0) ? '' : 'none';
    }

    if (catFilter) catFilter.addEventListener('change', applyFilter);
    if (statusFilter) statusFilter.addEventListener('change', applyFilter);
    if (searchFilter) searchFilter.addEventListener('input', applyFilter);

    // ----------------------------------------------------------------
    // 4. Complaint Details Modal (View button popup)
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
