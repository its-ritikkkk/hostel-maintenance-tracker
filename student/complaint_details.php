<?php
// ===============================================================
// student/complaint_details.php - Complaint Detail & Timeline View
// ===============================================================
// Full view of an individual complaint, status timeline, and remarks.
// Implements strict student ownership checks to protect privacy.

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

requireStudent();

$complaintId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$studentId = (int) $_SESSION['user_id'];

if ($complaintId <= 0) {
    $_SESSION['flash_error'] = 'Invalid complaint ID.';
    header('Location: my_complaints.php');
    exit;
}

$complaint = null;
$updates = [];

try {
    $db = getDbConnection();

    // Query complaint and enforce student ownership
    $stmt = $db->prepare(
        'SELECT c.*, u.name AS student_name, u.email AS student_email
         FROM complaints c
         JOIN users u ON c.student_id = u.id
         WHERE c.id = :id AND c.student_id = :student_id
         LIMIT 1'
    );
    $stmt->execute(['id' => $complaintId, 'student_id' => $studentId]);
    $complaint = $stmt->fetch();

    if (!$complaint) {
        $_SESSION['flash_error'] = 'Complaint not found or you do not have permission to view it.';
        header('Location: my_complaints.php');
        exit;
    }

    // Query status update history timeline
    $updatesStmt = $db->prepare(
        'SELECT cu.*, u.name AS updated_by_name
         FROM complaint_updates cu
         JOIN users u ON cu.updated_by = u.id
         WHERE cu.complaint_id = :complaint_id
         ORDER BY cu.created_at ASC'
    );
    $updatesStmt->execute(['complaint_id' => $complaintId]);
    $updates = $updatesStmt->fetchAll();
} catch (PDOException $e) {
    error_log('complaint_details error: ' . $e->getMessage());
    $_SESSION['flash_error'] = 'Failed to load complaint details.';
    header('Location: my_complaints.php');
    exit;
}

$pageTitle = 'Complaint #' . $complaintId . ' Details';
$pageScript = 'complaints.js';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header" id="singleComplaintDetail" data-complaint-id="<?= (int) $complaint['id'] ?>">
    <div class="page-title-group">
        <h2>Complaint #<?= (int) $complaint['id'] ?></h2>
        <p><?= htmlspecialchars($complaint['title']) ?></p>
    </div>
    <div class="page-actions">
        <!-- Live status sync indicator -->
        <span class="sync-indicator" id="syncIndicator" title="Status updates will reflect here live every 5s">
            <span class="sync-dot"></span>
            <span>Live Sync (5s)</span>
        </span>
        <a href="my_complaints.php" class="btn btn-outline">&larr; Back to My Complaints</a>
    </div>
</div>

<div class="detail-grid">
    <!-- Main Left Column: Description & Updates Timeline -->
    <div>
        <div class="detail-card">
            <h3>Issue Details</h3>
            
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

            <div style="margin-top: 1.25rem;">
                <label class="meta-label">Detailed Description:</label>
                <div class="description-box">
                    <?= htmlspecialchars($complaint['description']) ?>
                </div>
            </div>

            <!-- Admin Remarks Box -->
            <div style="margin-top: 1.25rem;">
                <label class="meta-label">Admin / Maintenance Team Remarks:</label>
                <div class="description-box" style="border-left-color: var(--secondary); background: #fffbeb;">
                    <span id="adminRemarksText">
                        <?= !empty($complaint['admin_remarks']) 
                            ? htmlspecialchars($complaint['admin_remarks']) 
                            : 'No remarks recorded yet. The hostel administration will update this upon review.' ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Status Change Timeline -->
        <div class="detail-card">
            <h3>Status History & Resolution Timeline</h3>
            
            <div class="timeline">
                <!-- Initial Submission Node -->
                <div class="timeline-item">
                    <div class="timeline-dot"></div>
                    <div class="timeline-content">
                        <div class="timeline-title">
                            <span>Ticket Lodged (Submitted)</span>
                            <span class="timeline-time"><?= date('M d, Y h:i A', strtotime($complaint['created_at'])) ?></span>
                        </div>
                        <p class="timeline-desc">Maintenance ticket created by student and queued for review.</p>
                    </div>
                </div>

                <!-- Subsequent Status Updates -->
                <?php if (!empty($updates)): ?>
                    <?php foreach ($updates as $u): ?>
                        <div class="timeline-item">
                            <div class="timeline-dot" style="background-color: var(--secondary);"></div>
                            <div class="timeline-content">
                                <div class="timeline-title">
                                    <span>
                                        Status updated to 
                                        <span class="badge <?= getStatusBadgeClass($u['new_status']) ?>">
                                            <?= htmlspecialchars($u['new_status']) ?>
                                        </span>
                                    </span>
                                    <span class="timeline-time"><?= date('M d, Y h:i A', strtotime($u['created_at'])) ?></span>
                                </div>
                                <p class="timeline-desc">
                                    <?= htmlspecialchars($u['remarks'] ?? 'Status updated by administrator.') ?>
                                </p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Right Sidebar Column: Metadata Card -->
    <div>
        <div class="detail-card">
            <h3>Ticket Information</h3>

            <div class="meta-row">
                <span class="meta-label">Ticket ID:</span>
                <span class="meta-value"><strong>#<?= (int) $complaint['id'] ?></strong></span>
            </div>

            <div class="meta-row">
                <span class="meta-label">Submitted On:</span>
                <span class="meta-value"><?= date('M d, Y', strtotime($complaint['created_at'])) ?></span>
            </div>

            <div class="meta-row">
                <span class="meta-label">Time:</span>
                <span class="meta-value"><?= date('h:i A', strtotime($complaint['created_at'])) ?></span>
            </div>

            <div class="meta-row">
                <span class="meta-label">Last Activity:</span>
                <span class="meta-value"><?= date('M d, Y h:i A', strtotime($complaint['updated_at'])) ?></span>
            </div>

            <div class="meta-row">
                <span class="meta-label">Institution:</span>
                <span class="meta-value">IIT (ISM) Dhanbad</span>
            </div>

            <div style="margin-top: 1.5rem;">
                <a href="create_complaint.php" class="btn btn-outline" style="width: 100%;">
                    + File Another Complaint
                </a>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
