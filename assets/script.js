// ====================================================================
// File: assets/script.js
// Purpose: Simple, clean JavaScript for live updates, filters, and modal
// Project: Hostel Maintenance and Complaint Tracker
// ====================================================================

document.addEventListener('DOMContentLoaded', function () {
    // ----------------------------------------------------------------
    // 1. Warden Quick Status Update (Via dropdown change)
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
    // 2. Complaint Details Modal (View button popup)
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
