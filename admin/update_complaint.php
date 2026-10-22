<?php
// ===============================================================
// admin/update_complaint.php - Warden Complaint Management View
// ===============================================================
// Detailed view of a complaint. Strictly verifies that the complaint
// belongs to the logged-in warden's assigned hostel before allowing
// viewing or status updates.

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

requireAdmin();

$complaintId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($complaintId <= 0) {
    $_SESSION['flash_error'] = 'Invalid complaint ID.';
    header('Location: manage_complaints.php');
    exit;
}

$wardenHostelId = getAssignedHostelId();
$wardenHostelName = getAssignedHostelName();

$complaint = null;
$updates = [];
$statuses = getComplaintStatuses();
$fallbackError = '';

try {
    $db = getDbConnection();

    // 1. Fetch complaint
    $stmt = $db->prepare(
        'SELECT 
            c.*, 
            u.name AS student_name, 
            u.email AS student_email,
            u.phone AS student_phone
         FROM complaints c
         JOIN users u ON c.student_id = u.id
         WHERE c.id = :id
         LIMIT 1'
    );
    $stmt->execute(['id' => $complaintId]);
    $complaint = $stmt->fetch();

    if (!$complaint) {
        $_SESSION['flash_error'] = 'Complaint not found.';
        header('Location: manage_complaints.php');
        exit;
    }

    // 2. SERVER-SIDE WARDEN HOSTEL VERIFICATION (SECURITY REQUIREMENT)
    // If a warden attempts to view a complaint belonging to another hostel,
    // deny access immediately.
    if ($wardenHostelId !== null && (int) $complaint['hostel_id'] !== $wardenHostelId) {
        $_SESSION['flash_error'] = 'Access Denied: You are authorized to manage complaints for ' . htmlspecialchars($wardenHostelName) . ' only. Complaint #' . $complaintId . ' belongs to ' . htmlspecialchars($complaint['hostel']) . '.';
        header('Location: manage_complaints.php');
        exit;
    }

    // Handle fallback standard POST submission (if JS disabled)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
        $csrfToken = $_POST['csrf_token'] ?? '';
        if (!verifyCsrfToken($csrfToken)) {
            $fallbackError = 'Invalid security token. Please refresh.';
        } else {
            $newStatus = trim($_POST['status'] ?? '');
            $remarks = trim($_POST['remarks'] ?? '');

            if (!in_array($newStatus, $statuses, true)) {
                $fallbackError = 'Invalid status selected.';
            } else {
                $updateStmt = $db->prepare(
                    'UPDATE complaints 
                     SET status = :status,
                         admin_remarks = CASE WHEN :remarks != "" THEN :remarks_val ELSE admin_remarks END,
                         updated_at = CURRENT_TIMESTAMP
                     WHERE id = :id AND (:hostel_check IS NULL OR hostel_id = :hostel_id)'
                );
                $updateStmt->execute([
                    'status'       => $newStatus,
                    'remarks'      => $remarks,
                    'remarks_val'  => $remarks,
                    'id'           => $complaintId,
                    'hostel_check' => $wardenHostelId,
                    'hostel_id'    => $wardenHostelId,
                ]);

                $logStmt = $db->prepare(
                    'INSERT INTO complaint_updates (complaint_id, updated_by, old_status, new_status, remarks)
                     VALUES (:cid, :uid, :old_s, :new_s, :rem)'
                );
                $logStmt->execute([
                    'cid'   => $complaintId,
                    'uid'   => (int) $_SESSION['user_id'],
                    'old_s' => $complaint['status'],
                    'new_s' => $newStatus,
                    'rem'   => !empty($remarks) ? $remarks : ('Status updated to ' . $newStatus),
                ]);

                $_SESSION['flash_success'] = 'Complaint #' . $complaintId . ' updated to ' . $newStatus . ' successfully!';
                header('Location: update_complaint.php?id=' . $complaintId);
                exit;
            }
        }
    }

    // Fetch status history timeline
    $updatesStmt = $db->prepare(
        'SELECT cu.*, u.name AS updated_by_name
         FROM complaint_updates cu
         JOIN users u ON cu.updated_by = u.id
         WHERE cu.complaint_id = :cid
         ORDER BY cu.created_at ASC'
    );
    $updatesStmt->execute(['cid' => $complaintId]);
    $updates = $updatesStmt->fetchAll();
} catch (PDOException $e) {
    error_log('Admin update_complaint error: ' . $e->getMessage());
    $_SESSION['flash_error'] = 'Database error while loading complaint details.';
    header('Location: manage_complaints.php');
    exit;
}

$pageTitle = 'Manage Complaint #' . $complaintId . ' (' . htmlspecialchars($complaint['hostel']) . ')';
$pageScript = 'admin.js';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header" id="singleComplaintDetail" data-complaint-id="<?= (int) $complaint['id'] ?>">
    <div class="page-title-group">
        <h2>Manage Complaint #<?= (int) $complaint['id'] ?></h2>
        <p>
            <span class="badge" style="background: var(--primary-light); color: var(--primary); font-weight: 700;">
                🏛️ <?= htmlspecialchars($complaint['hostel']) ?>
            </span>
            <span style="color: var(--text-muted); margin-left: 0.5rem;">
                <?= htmlspecialchars($complaint['title']) ?>
            </span>
        </p>
    </div>
    <div class="page-actions">
        <a href="manage_complaints.php" class="btn btn-outline">&larr; Back to <?= htmlspecialchars($wardenHostelName ?? 'Hostel') ?> Complaints</a>
    </div>
</div>

<?php if (!empty($fallbackError)): ?>
    <div class="alert alert-error"><?= htmlspecialchars($fallbackError) ?></div>
<?php endif; ?>

<div class="detail-grid">
    <div>
        <!-- Action Card -->
        <div class="detail-card" style="border-top: 4px solid var(--secondary);">
            <h3>Warden Action & Status Update</h3>
            <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1.25rem;">
                Authorized action for <strong><?= htmlspecialchars($wardenHostelName ?? $complaint['hostel']) ?></strong>. Update status and append remarks for the student.
            </p>

            <div id="wardenUpdateFeedback"></div>

            <form id="adminUpdateStatusForm" method="POST" action="update_complaint.php?id=<?= (int) $complaint['id'] ?>">
                <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
                <input type="hidden" name="complaint_id" id="formComplaintId" value="<?= (int) $complaint['id'] ?>">
                <input type="hidden" name="current_status" value="<?= htmlspecialchars($complaint['status']) ?>">

                <div class="form-group">
                    <label for="statusSelect" class="form-label">
                        Update Status <span class="required">*</span>
                    </label>
                    <select id="statusSelect" name="status" class="form-control" style="font-weight: 600;" required>
                        <?php foreach ($statuses as $st): ?>
                            <option value="<?= htmlspecialchars($st) ?>" <?= $complaint['status'] === $st ? 'selected' : '' ?>>
                                <?= htmlspecialchars($st) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="remarksInput" class="form-label">
                        Warden Remarks / Resolution Notes
                    </label>
                    <textarea 
                        id="remarksInput" 
                        name="remarks" 
                        class="form-control" 
                        placeholder="e.g. Electrician dispatched to Aquamarine room A-304. Parts replaced."
                        rows="3"
                    ><?= htmlspecialchars($complaint['admin_remarks'] ?? '') ?></textarea>
                    <span class="form-hint">These notes will be displayed live on the student's dashboard</span>
                </div>

                <div style="display: flex; gap: 0.75rem;">
                    <button type="submit" class="btn btn-primary" id="btnSaveStatus">
                        Save Status & Remarks (AJAX)
                    </button>
                </div>
            </form>
        </div>

        <!-- Issue Details Card -->
        <div class="detail-card">
            <h3>Issue Description</h3>

            <div class="description-box">
                <?= htmlspecialchars($complaint['description']) ?>
            </div>

            <div class="meta-row">
                <span class="meta-label">Category:</span>
                <span class="meta-value">
                    <span class="cat-tag"><?= htmlspecialchars($complaint['category']) ?></span>
                </span>
            </div>

            <div class="meta-row">
                <span class="meta-label">Hostel & Room:</span>
                <span class="meta-value">
                    <strong><?= htmlspecialchars($complaint['hostel']) ?></strong>, Room <?= htmlspecialchars($complaint['room_number']) ?>
                </span>
            </div>

            <div class="meta-row">
                <span class="meta-label">Current Status:</span>
                <span class="meta-value">
                    <span id="status-badge-<?= (int) $complaint['id'] ?>" class="badge <?= getStatusBadgeClass($complaint['status']) ?>">
                        <?= htmlspecialchars($complaint['status']) ?>
                    </span>
                </span>
            </div>
        </div>

        <!-- Status Change Timeline -->
        <div class="detail-card">
            <h3>Status Updates History</h3>
            
            <div class="timeline" id="adminTimeline">
                <div class="timeline-item">
                    <div class="timeline-dot"></div>
                    <div class="timeline-content">
                        <div class="timeline-title">
                            <span>Ticket Lodged (Submitted)</span>
                            <span class="timeline-time"><?= date('M d, Y h:i A', strtotime($complaint['created_at'])) ?></span>
                        </div>
                        <p class="timeline-desc">Initial ticket logged into system.</p>
                    </div>
                </div>

                <?php foreach ($updates as $u): ?>
                    <div class="timeline-item">
                        <div class="timeline-dot" style="background-color: var(--secondary);"></div>
                        <div class="timeline-content">
                            <div class="timeline-title">
                                <span>
                                    Changed to 
                                    <span class="badge <?= getStatusBadgeClass($u['new_status']) ?>">
                                        <?= htmlspecialchars($u['new_status']) ?>
                                    </span>
                                    by <?= htmlspecialchars($u['updated_by_name']) ?>
                                </span>
                                <span class="timeline-time"><?= date('M d, Y h:i A', strtotime($u['created_at'])) ?></span>
                            </div>
                            <p class="timeline-desc">
                                <?= htmlspecialchars($u['remarks'] ?? 'No remarks provided.') ?>
                            </p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Right Column: Student Profile & System Metadata -->
    <div>
        <div class="detail-card">
            <h3>Student Profile</h3>

            <div class="meta-row">
                <span class="meta-label">Name:</span>
                <span class="meta-value"><strong><?= htmlspecialchars($complaint['student_name']) ?></strong></span>
            </div>

            <div class="meta-row">
                <span class="meta-label">Email:</span>
                <span class="meta-value" style="word-break: break-all;">
                    <a href="mailto:<?= htmlspecialchars($complaint['student_email']) ?>">
                        <?= htmlspecialchars($complaint['student_email']) ?>
                    </a>
                </span>
            </div>

            <div class="meta-row">
                <span class="meta-label">Phone:</span>
                <span class="meta-value">
                    <?= !empty($complaint['student_phone']) ? htmlspecialchars($complaint['student_phone']) : 'Not provided' ?>
                </span>
            </div>

            <div class="meta-row">
                <span class="meta-label">Hostel:</span>
                <span class="meta-value"><strong><?= htmlspecialchars($complaint['hostel']) ?></strong></span>
            </div>

            <div class="meta-row">
                <span class="meta-label">Room Number:</span>
                <span class="meta-value"><?= htmlspecialchars($complaint['room_number']) ?></span>
            </div>
        </div>

        <div class="detail-card">
            <h3>System Information</h3>

            <div class="meta-row">
                <span class="meta-label">Created At:</span>
                <span class="meta-value"><?= date('M d, Y h:i A', strtotime($complaint['created_at'])) ?></span>
            </div>

            <div class="meta-row">
                <span class="meta-label">Last Updated:</span>
                <span class="meta-value" id="labelLastUpdated"><?= date('M d, Y h:i A', strtotime($complaint['updated_at'])) ?></span>
            </div>

            <div class="meta-row">
                <span class="meta-label">Institution:</span>
                <span class="meta-value">IIT (ISM) Dhanbad</span>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('adminUpdateStatusForm').addEventListener('submit', async function(e) {
    e.preventDefault();

    const btn = document.getElementById('btnSaveStatus');
    const originalText = btn ? btn.innerHTML : 'Save Status & Remarks (AJAX)';
    const feedbackBox = document.getElementById('wardenUpdateFeedback');
    if (feedbackBox) feedbackBox.innerHTML = '';

    const complaintIdEl = document.getElementById('formComplaintId');
    const statusSelect = document.getElementById('statusSelect');
    const remarksInput = document.getElementById('remarksInput');

    const complaintId = complaintIdEl ? parseInt(complaintIdEl.value, 10) : 0;
    const newStatus = statusSelect ? statusSelect.value : '';
    const remarks = remarksInput ? remarksInput.value.trim() : '';

    if (!complaintId || !newStatus) {
        showToast('Please select a valid status.', 'error');
        return;
    }

    if (btn) {
        btn.classList.add('loading');
        btn.disabled = true;
        btn.innerHTML = 'Updating Status...';
    }

    try {
        const baseUrl = (window.APP_CONFIG && window.APP_CONFIG.baseUrl !== undefined) 
            ? window.APP_CONFIG.baseUrl 
            : (window.location.pathname.includes('/tracker') ? '/tracker' : '');
        const csrfToken = (window.APP_CONFIG && window.APP_CONFIG.csrfToken) 
            || (document.querySelector('input[name="csrf_token"]') ? document.querySelector('input[name="csrf_token"]').value : '');

        const endpoint = `${baseUrl}/api/update_status.php`;

        const res = await apiFetch(endpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                complaint_id: complaintId,
                status: newStatus,
                remarks: remarks,
                csrf_token: csrfToken
            })
        });

        if (res && res.data && res.data.success) {
            showToast(res.data.message || `Status updated to ${newStatus} successfully!`, 'success');

            if (feedbackBox) {
                feedbackBox.innerHTML = `
                    <div class="alert alert-success" style="margin-bottom: 1rem;">
                        <strong>✅ Status Updated:</strong> Complaint #${complaintId} changed to <strong>${escapeHtml(newStatus)}</strong>.
                    </div>
                `;
            }

            // Update badge on the page
            const badge = document.getElementById(`status-badge-${complaintId}`);
            const badgeClass = (res.data && res.data.badge_class) ? res.data.badge_class : getStatusBadgeClass(newStatus);
            if (badge) {
                badge.textContent = newStatus;
                badge.className = `badge ${badgeClass}`;
                badge.style.transform = 'scale(1.15)';
                setTimeout(() => { badge.style.transform = 'scale(1)'; }, 300);
            }

            // Update last updated label
            const lastUpdated = document.getElementById('labelLastUpdated');
            if (lastUpdated && res.data && res.data.updated_at) {
                lastUpdated.textContent = formatDateTime(res.data.updated_at);
            }

            // Append item to timeline
            const timeline = document.getElementById('adminTimeline');
            if (timeline) {
                const newItem = document.createElement('div');
                newItem.className = 'timeline-item';
                newItem.innerHTML = `
                    <div class="timeline-dot" style="background-color: var(--secondary);"></div>
                    <div class="timeline-content" style="background-color: #ecfdf5;">
                        <div class="timeline-title">
                            <span>
                                Changed to 
                                <span class="badge ${badgeClass}">
                                    ${escapeHtml(newStatus)}
                                </span>
                                by Warden
                            </span>
                            <span class="timeline-time">Just now</span>
                        </div>
                        <p class="timeline-desc">
                            ${escapeHtml(remarks || `Status changed to ${newStatus}`)}
                        </p>
                    </div>
                `;
                timeline.appendChild(newItem);
            }
        } else {
            const errorMsg = (res && res.data && res.data.message) 
                ? res.data.message 
                : 'Failed to update status. Please try again.';
            showToast(errorMsg, 'error');
            if (feedbackBox) {
                feedbackBox.innerHTML = `<div class="alert alert-error" style="margin-bottom: 1rem;"><strong>Error:</strong> ${escapeHtml(errorMsg)}</div>`;
            }
        }
    } catch (err) {
        console.error('Update status error:', err);
        const errorMsg = 'An unexpected error occurred while updating status. Please try again.';
        showToast(errorMsg, 'error');
        if (feedbackBox) {
            feedbackBox.innerHTML = `<div class="alert alert-error" style="margin-bottom: 1rem;"><strong>Error:</strong> ${escapeHtml(errorMsg)}</div>`;
        }
    } finally {
        if (btn) {
            btn.classList.remove('loading');
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
