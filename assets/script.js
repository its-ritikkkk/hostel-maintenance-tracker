// ====================================================================
// File: assets/script.js
// Purpose: Client-side interactions and Asynchronous Status Refresh
// Project: Hostel Maintenance and Complaint Tracker
// Hostel: Aquamarine Hostel, IIT (ISM) Dhanbad
// ====================================================================

document.addEventListener('DOMContentLoaded', function () {
    // ---------------------------------------------------------------
    // 1. Initialize Auto-Refresh Timer (Every 5 seconds)
    // ---------------------------------------------------------------
    const autoRefreshInterval = 5000; // 5000 milliseconds = 5 seconds

    // Check if the page has a complaints table or list to refresh
    const complaintsTable = document.getElementById('complaintsTable');
    if (complaintsTable) {
        // Run refresh once on page load, then periodically every 5 seconds
        setInterval(refreshComplaintStatuses, autoRefreshInterval);
    }

    // ---------------------------------------------------------------
    // 2. Asynchronous Status Refresh Function
    // ---------------------------------------------------------------
    /**
     * Polls the server asynchronously via fetch() to get the latest
     * status of each complaint from MariaDB. Updates the DOM in real
     * time without reloading the page.
     */
    function refreshComplaintStatuses() {
        // Read optional admission number if present (on student dashboard)
        const studentAdmissionNo = document.body.dataset.studentAdmission || '';
        let url = 'api/get_statuses.php';
        if (studentAdmissionNo) {
            url += '?admission_no=' + encodeURIComponent(studentAdmissionNo);
        }

        fetch(url)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network error fetching statuses');
                }
                return response.json();
            })
            .then(data => {
                if (!data.success || !Array.isArray(data.statuses)) {
                    return;
                }

                // Update the "Last synced" timestamp indicator
                const syncTimeEl = document.getElementById('lastSyncTime');
                if (syncTimeEl && data.timestamp) {
                    syncTimeEl.textContent = data.timestamp;
                }

                // Loop through returned statuses and update matching table rows
                data.statuses.forEach(item => {
                    const complaintId = item.id;
                    const newStatus = item.status;

                    // Locate row or badge element by data attribute
                    const row = document.querySelector(`tr[data-complaint-id="${complaintId}"]`);
                    const badge = document.querySelector(`.status-badge[data-complaint-id="${complaintId}"]`);

                    if (badge) {
                        const currentStatus = badge.getAttribute('data-current-status');

                        // Only animate and update if the status has actually changed
                        if (currentStatus !== newStatus) {
                            badge.textContent = newStatus;
                            badge.setAttribute('data-current-status', newStatus);

                            // Update CSS status class
                            badge.className = 'badge status-badge ' + getStatusClass(newStatus);

                            // Add visual pulse highlight animation
                            badge.classList.add('status-updated');
                            setTimeout(() => {
                                badge.classList.remove('status-updated');
                            }, 1000);

                            // If this is the Warden dashboard with a status dropdown, sync the dropdown value
                            if (row) {
                                const selectEl = row.querySelector('.warden-status-select');
                                if (selectEl && selectEl.value !== newStatus) {
                                    selectEl.value = newStatus;
                                }
                            }
                        }
                    }
                });
            })
            .catch(error => {
                console.warn('Auto-refresh status check warning:', error.message);
            });
    }

    // Helper: Map status text to CSS badge class
    function getStatusClass(status) {
        switch (status) {
            case 'Submitted':
                return 'status-submitted';
            case 'In Progress':
                return 'status-in-progress';
            case 'Resolved':
                return 'status-resolved';
            default:
                return 'status-submitted';
        }
    }

    // ---------------------------------------------------------------
    // 3. Warden: Asynchronous Status Update Handler
    // ---------------------------------------------------------------
    const wardenSelects = document.querySelectorAll('.warden-status-select');
    wardenSelects.forEach(select => {
        select.addEventListener('change', function () {
            const complaintId = this.dataset.complaintId;
            const newStatus = this.value;
            const originalStatus = this.dataset.originalStatus || this.value;

            // Immediately send asynchronous update to PHP backend
            fetch('api/update_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    complaint_id: complaintId,
                    status: newStatus
                })
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update badge in this row
                        const badge = document.querySelector(`.status-badge[data-complaint-id="${complaintId}"]`);
                        if (badge) {
                            badge.textContent = newStatus;
                            badge.setAttribute('data-current-status', newStatus);
                            badge.className = 'badge status-badge ' + getStatusClass(newStatus);
                            badge.classList.add('status-updated');
                            setTimeout(() => badge.classList.remove('status-updated'), 1000);
                        }
                        this.dataset.originalStatus = newStatus;
                        showToast(`Complaint #${complaintId} status updated to '${newStatus}'!`, 'success');
                    } else {
                        alert('Could not update status: ' + (data.message || 'Unknown error'));
                        this.value = originalStatus; // Revert selection
                    }
                })
                .catch(err => {
                    alert('Error connecting to server. Status not updated.');
                    this.value = originalStatus;
                });
        });
    });

    // ---------------------------------------------------------------
    // 4. Student: Asynchronous Complaint Submission (Optional Enhanced UX)
    // ---------------------------------------------------------------
    const complaintForm = document.getElementById('complaintForm');
    if (complaintForm) {
        complaintForm.addEventListener('submit', function (e) {
            // Check client-side validation
            const name = document.getElementById('studentName')?.value.trim();
            const admissionNo = document.getElementById('admissionNo')?.value.trim();
            const email = document.getElementById('studentEmail')?.value.trim();
            const title = document.getElementById('complaintTitle')?.value.trim();
            const category = document.getElementById('complaintCategory')?.value;
            const description = document.getElementById('complaintDescription')?.value.trim();

            if (!name || !admissionNo || !email || !title || !category || !description) {
                alert('Please fill in all required fields before submitting.');
                e.preventDefault();
                return;
            }

            // If browser supports fetch, submit asynchronously for instant UI update
            e.preventDefault();

            const submitBtn = complaintForm.querySelector('button[type="submit"]');
            const originalBtnText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = 'Submitting...';

            const payload = {
                name: name,
                admission_no: admissionNo,
                email: email,
                title: title,
                category: category,
                description: description
            };

            fetch('api/submit_complaint.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(payload)
            })
                .then(res => res.json())
                .then(result => {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalBtnText;

                    if (result.success) {
                        // Reset form fields except student details
                        document.getElementById('complaintTitle').value = '';
                        document.getElementById('complaintDescription').value = '';
                        document.getElementById('complaintCategory').selectedIndex = 0;

                        showToast(result.message, 'success');

                        // Refresh complaints list or reload cleanly so student sees their new complaint
                        setTimeout(() => {
                            window.location.href = 'student.php?admission_no=' + encodeURIComponent(admissionNo);
                        }, 700);
                    } else {
                        alert(result.message || 'Error submitting complaint.');
                    }
                })
                .catch(err => {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalBtnText;
                    // Fallback to normal form submit if fetch fails
                    complaintForm.submit();
                });
        });
    }

    // ---------------------------------------------------------------
    // 5. Client-Side Instant Filtering by Category & Status
    // ---------------------------------------------------------------
    const categoryFilter = document.getElementById('categoryFilter');
    const statusFilter = document.getElementById('statusFilter');
    const searchFilter = document.getElementById('searchFilter');

    function applyFilters() {
        const selectedCat = categoryFilter ? categoryFilter.value.toLowerCase() : 'all';
        const selectedStatus = statusFilter ? statusFilter.value.toLowerCase() : 'all';
        const searchVal = searchFilter ? searchFilter.value.toLowerCase().trim() : '';

        const rows = document.querySelectorAll('#complaintsTable tbody tr.complaint-row');
        let visibleCount = 0;

        rows.forEach(row => {
            const rowCat = (row.dataset.category || '').toLowerCase();
            const rowStatus = (row.dataset.status || '').toLowerCase();
            const rowText = row.innerText.toLowerCase();

            const matchCat = (selectedCat === 'all' || rowCat === selectedCat);
            const matchStatus = (selectedStatus === 'all' || rowStatus === selectedStatus);
            const matchSearch = (!searchVal || rowText.includes(searchVal));

            if (matchCat && matchStatus && matchSearch) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });

        // Toggle empty filter state message
        const emptyRow = document.getElementById('noFilterResultsRow');
        if (emptyRow) {
            emptyRow.style.display = (visibleCount === 0 && rows.length > 0) ? '' : 'none';
        }

        const countDisplay = document.getElementById('visibleComplaintsCount');
        if (countDisplay) {
            countDisplay.textContent = visibleCount;
        }
    }

    if (categoryFilter) categoryFilter.addEventListener('change', applyFilters);
    if (statusFilter) statusFilter.addEventListener('change', applyFilters);
    if (searchFilter) searchFilter.addEventListener('input', applyFilters);

    // ---------------------------------------------------------------
    // 6. View Complaint Details Modal
    // ---------------------------------------------------------------
    const modal = document.getElementById('complaintModal');
    const modalCloseBtn = document.getElementById('closeModalBtn');

    if (modal && modalCloseBtn) {
        modalCloseBtn.addEventListener('click', () => {
            modal.classList.remove('active');
        });

        // Close on clicking outside modal content
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.classList.remove('active');
            }
        });
    }

    // Attach click listeners to "View" buttons
    const viewButtons = document.querySelectorAll('.btn-view-complaint');
    viewButtons.forEach(btn => {
        btn.addEventListener('click', function () {
            const id = this.dataset.id;
            const title = this.dataset.title;
            const category = this.dataset.category;
            const status = this.dataset.status;
            const description = this.dataset.description;
            const date = this.dataset.date;
            const studentName = this.dataset.studentName || 'N/A';
            const admissionNo = this.dataset.admissionNo || 'N/A';

            document.getElementById('modalComplaintId').textContent = '#' + id;
            document.getElementById('modalTitle').textContent = title;
            document.getElementById('modalCategory').textContent = category;
            document.getElementById('modalStatus').textContent = status;
            document.getElementById('modalStatus').className = 'badge ' + getStatusClass(status);
            document.getElementById('modalDate').textContent = date;
            document.getElementById('modalStudent').textContent = `${studentName} (${admissionNo})`;
            document.getElementById('modalDescription').textContent = description;

            if (modal) {
                modal.classList.add('active');
            }
        });
    });

    // ---------------------------------------------------------------
    // 7. Toast Notification Utility
    // ---------------------------------------------------------------
    function showToast(message, type = 'info') {
        let toast = document.getElementById('toastNotice');
        if (!toast) {
            toast = document.createElement('div');
            toast.id = 'toastNotice';
            toast.style.position = 'fixed';
            toast.style.bottom = '20px';
            toast.style.right = '20px';
            toast.style.padding = '12px 20px';
            toast.style.borderRadius = '8px';
            toast.style.color = '#fff';
            toast.style.fontWeight = '600';
            toast.style.fontSize = '0.9rem';
            toast.style.zIndex = '9999';
            toast.style.boxShadow = '0 4px 14px rgba(0,0,0,0.2)';
            toast.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
            document.body.appendChild(toast);
        }

        toast.style.backgroundColor = (type === 'success') ? '#10b981' : '#2563eb';
        toast.textContent = message;
        toast.style.opacity = '1';
        toast.style.transform = 'translateY(0)';

        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(10px)';
        }, 3500);
    }
});
