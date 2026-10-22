<?php
// ===============================================================
// student/dashboard.php - Student Portal Dashboard
// ===============================================================
// Overview of the logged-in student's maintenance requests,
// quick stats, recent complaints, and real-time status tracking.

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

// Ensure user is an authenticated student
requireStudent();

$studentId = (int) $_SESSION['user_id'];
$studentName = $_SESSION['user_name'] ?? 'Student';
$studentHostel = $_SESSION['user_hostel'] ?? 'Hostel';
$studentRoom = $_SESSION['user_room'] ?? '';

$totalCount = 0;
$submittedCount = 0;
$inProgressCount = 0;
$resolvedCount = 0;
$recentComplaints = [];

try {
    $db = getDbConnection();

    // Query high-level counts for this specific student
    $countStmt = $db->prepare(
        'SELECT 
            COUNT(*) AS total,
            SUM(CASE WHEN status = "Submitted" THEN 1 ELSE 0 END) AS submitted,
            SUM(CASE WHEN status = "In Progress" THEN 1 ELSE 0 END) AS in_progress,
            SUM(CASE WHEN status = "Resolved" THEN 1 ELSE 0 END) AS resolved
         FROM complaints 
         WHERE student_id = :student_id'
    );
    $countStmt->execute(['student_id' => $studentId]);
    $counts = $countStmt->fetch();

    $totalCount = (int) ($counts['total'] ?? 0);
    $submittedCount = (int) ($counts['submitted'] ?? 0);
    $inProgressCount = (int) ($counts['in_progress'] ?? 0);
    $resolvedCount = (int) ($counts['resolved'] ?? 0);

    // Fetch up to 5 most recent complaints
    $recentStmt = $db->prepare(
        'SELECT id, title, description, category, hostel, room_number, status, created_at
         FROM complaints
         WHERE student_id = :student_id
         ORDER BY created_at DESC
         LIMIT 5'
    );
    $recentStmt->execute(['student_id' => $studentId]);
    $recentComplaints = $recentStmt->fetchAll();
} catch (PDOException $e) {
    error_log('Student dashboard query error: ' . $e->getMessage());
}

$pageTitle = 'Student Dashboard';
$pageScript = 'complaints.js';
include __DIR__ . '/../includes/header.php';
?>

<!-- Dashboard Header -->
<div class="page-header">
    <div class="page-title-group">
        <h2>Welcome, <?= htmlspecialchars($studentName) ?></h2>
        <p>IIT (ISM) Dhanbad Resident • <?= htmlspecialchars($studentHostel) ?><?= $studentRoom ? ', Room ' . htmlspecialchars($studentRoom) : '' ?></p>
    </div>
    <div class="page-actions">
        <!-- Live status sync indicator -->
        <span class="sync-indicator" id="syncIndicator" title="Statuses refresh automatically every 5 seconds">
            <span class="sync-dot"></span>
            <span>Live Auto-Sync (5s)</span>
        </span>
        <a href="create_complaint.php" class="btn btn-secondary">
            + Lodge New Complaint
        </a>
    </div>
</div>

<!-- Statistics Cards Grid -->
<div class="stats-grid">
    <div class="stat-card total">
        <div class="stat-info">
            <p>Total Lodged</p>
            <h3><?= $totalCount ?></h3>
        </div>
        <div class="stat-icon">📑</div>
    </div>
    <div class="stat-card submitted">
        <div class="stat-info">
            <p>Submitted</p>
            <h3><?= $submittedCount ?></h3>
        </div>
        <div class="stat-icon">⏳</div>
    </div>
    <div class="stat-card inprogress">
        <div class="stat-info">
            <p>In Progress</p>
            <h3><?= $inProgressCount ?></h3>
        </div>
        <div class="stat-icon">🔧</div>
    </div>
    <div class="stat-card resolved">
        <div class="stat-info">
            <p>Resolved</p>
            <h3><?= $resolvedCount ?></h3>
        </div>
        <div class="stat-icon">✅</div>
    </div>
</div>

<!-- Recent Complaints Section -->
<div class="table-card">
    <div class="table-header">
        <h3>Recent Maintenance Requests</h3>
        <a href="my_complaints.php" class="btn btn-sm btn-outline">
            View All Complaints &rarr;
        </a>
    </div>

    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Complaint ID</th>
                    <th>Issue Title</th>
                    <th>Category</th>
                    <th>Hostel & Room</th>
                    <th>Current Status</th>
                    <th>Submission Date</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody id="complaintsTableBody">
                <?php if (empty($recentComplaints)): ?>
                    <tr>
                        <td colspan="7" class="empty-state">
                            <div class="empty-state-icon">📝</div>
                            <h4>No Complaints Lodged Yet</h4>
                            <p>You have not reported any maintenance issues. Click below to submit your first request.</p>
                            <a href="create_complaint.php" class="btn btn-primary btn-sm" style="margin-top: 0.75rem;">
                                Lodge Complaint
                            </a>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($recentComplaints as $c): ?>
                        <tr id="complaint-row-<?= (int) $c['id'] ?>">
                            <td><strong>#<?= (int) $c['id'] ?></strong></td>
                            <td class="complaint-title-cell">
                                <a href="complaint_details.php?id=<?= (int) $c['id'] ?>" class="complaint-title-link">
                                    <?= htmlspecialchars($c['title']) ?>
                                </a>
                                <span class="complaint-meta-sub">
                                    <?= htmlspecialchars(mb_substr($c['description'], 0, 65)) ?><?= mb_strlen($c['description']) > 65 ? '...' : '' ?>
                                </span>
                            </td>
                            <td>
                                <span class="cat-tag"><?= htmlspecialchars($c['category']) ?></span>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($c['hostel']) ?></strong><br>
                                <span style="font-size: 0.78rem; color: var(--text-muted);"><?= htmlspecialchars($c['room_number']) ?></span>
                            </td>
                            <td>
                                <span id="status-badge-<?= (int) $c['id'] ?>" class="badge <?= getStatusBadgeClass($c['status']) ?>">
                                    <?= htmlspecialchars($c['status']) ?>
                                </span>
                            </td>
                            <td style="font-size: 0.82rem; color: var(--text-muted);">
                                <?= date('M d, Y h:i A', strtotime($c['created_at'])) ?>
                            </td>
                            <td>
                                <a href="complaint_details.php?id=<?= (int) $c['id'] ?>" class="btn btn-sm btn-outline">
                                    View
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
