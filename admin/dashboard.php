<?php
// ===============================================================
// admin/dashboard.php - Multi-Hostel Warden Dashboard
// ===============================================================
// Dynamic warden control center. Automatically binds to the logged-in
// warden's assigned hostel and displays complaints exclusively for that hostel.

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

requireAdmin();

$wardenName = $_SESSION['user_name'] ?? 'Warden';
$wardenHostelId = getAssignedHostelId();
$wardenHostelName = getAssignedHostelName();

// Requirement 4.7: If an account is not assigned to a valid hostel, deny access
// and display a helpful error message.
if ($wardenHostelId === null && empty($wardenHostelName)) {
    $pageTitle = 'Unassigned Warden Account';
    include __DIR__ . '/../includes/header.php';
    echo '
    <div style="max-width: 650px; margin: 3rem auto; text-align: center; background: #ffffff; padding: 2.5rem; border-radius: 12px; border: 1px solid var(--border-color); box-shadow: var(--shadow-md);">
        <div style="font-size: 3rem; margin-bottom: 1rem;">⚠️</div>
        <h2 style="color: #dc2626; margin-bottom: 0.75rem;">Hostel Assignment Required</h2>
        <p style="color: var(--text-muted); font-size: 0.95rem; margin-bottom: 1.5rem;">
            Your account is currently not assigned to an active hostel. Each warden account must be linked to exactly one hostel (e.g., Aquamarine, Amber, or Jasper) before accessing this dashboard.
        </p>
        <p style="font-size: 0.85rem; color: #64748b; margin-bottom: 1.5rem;">
            Please contact the Chief Warden Office or sign in using a pre-assigned warden demo account.
        </p>
        <a href="../auth/logout.php" class="btn btn-primary">Sign Out & Try Again</a>
    </div>';
    include __DIR__ . '/../includes/footer.php';
    exit;
}

$totalCount = 0;
$submittedCount = 0;
$inProgressCount = 0;
$resolvedCount = 0;
$recentComplaints = [];

try {
    $db = getDbConnection();

    // Query statistics strictly filtered by the warden's assigned hostel
    $countSql = 'SELECT 
                    COUNT(*) AS total,
                    SUM(CASE WHEN status = "Submitted" THEN 1 ELSE 0 END) AS submitted,
                    SUM(CASE WHEN status = "In Progress" THEN 1 ELSE 0 END) AS in_progress,
                    SUM(CASE WHEN status = "Resolved" THEN 1 ELSE 0 END) AS resolved
                 FROM complaints 
                 WHERE 1=1';
    $params = [];

    if ($wardenHostelId !== null) {
        $countSql .= ' AND hostel_id = :hostel_id';
        $params['hostel_id'] = $wardenHostelId;
    }

    $countStmt = $db->prepare($countSql);
    $countStmt->execute($params);
    $counts = $countStmt->fetch();

    $totalCount = (int) ($counts['total'] ?? 0);
    $submittedCount = (int) ($counts['submitted'] ?? 0);
    $inProgressCount = (int) ($counts['in_progress'] ?? 0);
    $resolvedCount = (int) ($counts['resolved'] ?? 0);

    // Query recent complaints strictly for this hostel
    $recentSql = 'SELECT 
                    c.id,
                    c.student_id,
                    u.name AS student_name,
                    u.email AS student_email,
                    c.title,
                    c.description,
                    c.category,
                    c.hostel,
                    c.room_number,
                    c.status,
                    c.created_at
                  FROM complaints c
                  JOIN users u ON c.student_id = u.id
                  WHERE 1=1';
    
    if ($wardenHostelId !== null) {
        $recentSql .= ' AND c.hostel_id = :hostel_id';
    }
    
    $recentSql .= ' ORDER BY c.created_at DESC LIMIT 8';

    $recentStmt = $db->prepare($recentSql);
    $recentStmt->execute($params);
    $recentComplaints = $recentStmt->fetchAll();
} catch (PDOException $e) {
    error_log('Warden dashboard query error: ' . $e->getMessage());
}

$pageTitle = 'Warden Dashboard - ' . ($wardenHostelName ?? 'Hostel');
$pageScript = 'admin.js';
include __DIR__ . '/../includes/header.php';
?>

<!-- Warden Header with Assigned Hostel Banner -->
<div class="page-header">
    <div class="page-title-group">
        <h2><?= htmlspecialchars($wardenName) ?></h2>
        <p style="margin-top: 0.35rem;">
            <span style="display: inline-flex; align-items: center; gap: 0.4rem; background: var(--primary-light); color: var(--primary); padding: 0.25rem 0.75rem; border-radius: var(--radius-full); font-weight: 700; font-size: 0.85rem;">
                🏛️ Assigned Hostel: <?= htmlspecialchars($wardenHostelName ?? 'All Hostels') ?>
            </span>
            <span style="color: var(--text-muted); font-size: 0.85rem; margin-left: 0.5rem;">
                IIT (ISM) Dhanbad Residential Helpdesk
            </span>
        </p>
    </div>
    <div class="page-actions">
        <span class="sync-indicator" id="syncIndicator" title="Live status refresh active every 5s">
            <span class="sync-dot"></span>
            <span>Live Sync (5s)</span>
        </span>
        <a href="manage_complaints.php" class="btn btn-secondary">
            Manage All <?= htmlspecialchars($wardenHostelName ?? '') ?> Complaints &rarr;
        </a>
    </div>
</div>

<!-- KPI Statistics Grid for This Hostel -->
<div class="stats-grid">
    <div class="stat-card total">
        <div class="stat-info">
            <p><?= htmlspecialchars($wardenHostelName ?? '') ?> Total</p>
            <h3 id="statTotal"><?= $totalCount ?></h3>
        </div>
        <div class="stat-icon">📊</div>
    </div>
    <div class="stat-card submitted">
        <div class="stat-info">
            <p>Submitted (Pending)</p>
            <h3 id="statSubmitted"><?= $submittedCount ?></h3>
        </div>
        <div class="stat-icon">⏳</div>
    </div>
    <div class="stat-card inprogress">
        <div class="stat-info">
            <p>In Progress</p>
            <h3 id="statInProgress"><?= $inProgressCount ?></h3>
        </div>
        <div class="stat-icon">🔧</div>
    </div>
    <div class="stat-card resolved">
        <div class="stat-info">
            <p>Resolved</p>
            <h3 id="statResolved"><?= $resolvedCount ?></h3>
        </div>
        <div class="stat-icon">✅</div>
    </div>
</div>

<!-- Recent Complaints Section for Assigned Hostel -->
<div class="table-card">
    <div class="table-header">
        <div>
            <h3>Recent Maintenance Requests — <?= htmlspecialchars($wardenHostelName ?? 'Assigned Hostel') ?></h3>
            <p style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.2rem;">
                Change status directly using the dropdown to update asynchronously via Fetch API
            </p>
        </div>
        <a href="manage_complaints.php" class="btn btn-sm btn-outline">
            Full Registry &rarr;
        </a>
    </div>

    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Student Details</th>
                    <th>Issue Title</th>
                    <th>Category</th>
                    <th>Room Number</th>
                    <th>Current Status</th>
                    <th>Update Status (AJAX)</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody id="adminComplaintsTableBody">
                <?php if (empty($recentComplaints)): ?>
                    <tr>
                        <td colspan="8" class="empty-state">
                            <div class="empty-state-icon">📋</div>
                            <h4>No Complaints for <?= htmlspecialchars($wardenHostelName ?? 'this hostel') ?></h4>
                            <p>There are currently no maintenance complaints logged for this hostel.</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($recentComplaints as $c): ?>
                        <tr id="complaint-row-<?= (int) $c['id'] ?>">
                            <td><strong>#<?= (int) $c['id'] ?></strong></td>
                            <td>
                                <strong><?= htmlspecialchars($c['student_name']) ?></strong><br>
                                <span style="font-size: 0.75rem; color: var(--text-muted);"><?= htmlspecialchars($c['student_email']) ?></span>
                            </td>
                            <td class="complaint-title-cell">
                                <a href="update_complaint.php?id=<?= (int) $c['id'] ?>" class="complaint-title-link">
                                    <?= htmlspecialchars($c['title']) ?>
                                </a>
                                <span class="complaint-meta-sub">
                                    <?= htmlspecialchars(mb_substr($c['description'], 0, 60)) ?><?= mb_strlen($c['description']) > 60 ? '...' : '' ?>
                                </span>
                            </td>
                            <td>
                                <span class="cat-tag"><?= htmlspecialchars($c['category']) ?></span>
                            </td>
                            <td>
                                <strong>Room <?= htmlspecialchars($c['room_number']) ?></strong>
                            </td>
                            <td>
                                <span id="status-badge-<?= (int) $c['id'] ?>" class="badge <?= getStatusBadgeClass($c['status']) ?>">
                                    <?= htmlspecialchars($c['status']) ?>
                                </span>
                            </td>
                            <td>
                                <!-- Inline AJAX Status Changer Dropdown -->
                                <select class="status-select-inline" data-id="<?= (int) $c['id'] ?>" data-current="<?= htmlspecialchars($c['status']) ?>">
                                    <option value="Submitted" <?= $c['status'] === 'Submitted' ? 'selected' : '' ?>>Submitted</option>
                                    <option value="In Progress" <?= $c['status'] === 'In Progress' ? 'selected' : '' ?>>In Progress</option>
                                    <option value="Resolved" <?= $c['status'] === 'Resolved' ? 'selected' : '' ?>>Resolved</option>
                                </select>
                            </td>
                            <td>
                                <a href="update_complaint.php?id=<?= (int) $c['id'] ?>" class="btn btn-sm btn-outline">
                                    Manage &rarr;
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
