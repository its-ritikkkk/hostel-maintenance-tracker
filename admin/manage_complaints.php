<?php
// ===============================================================
// admin/manage_complaints.php - Multi-Hostel Complaint Registry
// ===============================================================
// Displays complaints belonging to the authenticated warden's assigned
// hostel with multi-criteria filtering (category, status, search).

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

requireAdmin();

$wardenName = $_SESSION['user_name'] ?? 'Warden';
$wardenHostelId = getAssignedHostelId();
$wardenHostelName = getAssignedHostelName();

$categories = getComplaintCategories();
$statuses = getComplaintStatuses();

$statusFilter = trim($_GET['status'] ?? '');
$categoryFilter = trim($_GET['category'] ?? '');
$searchFilter = trim($_GET['search'] ?? '');

$complaints = [];

try {
    $db = getDbConnection();

    $sql = 'SELECT 
                c.id,
                c.student_id,
                u.name AS student_name,
                u.email AS student_email,
                c.title,
                c.description,
                c.category,
                c.hostel_id,
                c.hostel,
                c.room_number,
                c.status,
                c.created_at
            FROM complaints c
            JOIN users u ON c.student_id = u.id
            WHERE 1=1';

    $params = [];

    // Strictly enforce multi-hostel warden isolation
    if ($wardenHostelId !== null) {
        $sql .= ' AND c.hostel_id = :warden_hostel_id';
        $params['warden_hostel_id'] = $wardenHostelId;
    }

    if (!empty($statusFilter) && in_array($statusFilter, $statuses, true)) {
        $sql .= ' AND c.status = :status';
        $params['status'] = $statusFilter;
    }

    if (!empty($categoryFilter) && in_array($categoryFilter, $categories, true)) {
        $sql .= ' AND c.category = :category';
        $params['category'] = $categoryFilter;
    }

    if (!empty($searchFilter)) {
        $sql .= ' AND (c.title LIKE :search OR c.description LIKE :search OR u.name LIKE :search OR u.email LIKE :search)';
        $params['search'] = '%' . $searchFilter . '%';
    }

    $sql .= ' ORDER BY c.created_at DESC';

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $complaints = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('manage_complaints query error: ' . $e->getMessage());
}

$pageTitle = 'Manage Complaints - ' . ($wardenHostelName ?? 'Hostel');
$pageScript = 'admin.js';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div class="page-title-group">
        <h2><?= htmlspecialchars($wardenHostelName ?? 'Hostel') ?> Complaints Registry</h2>
        <p>IIT (ISM) Dhanbad • Maintenance complaints restricted to your assigned hostel jurisdiction</p>
    </div>
    <div class="page-actions">
        <span class="sync-indicator" id="syncIndicator" title="Live status refresh active every 5s without reloading">
            <span class="sync-dot"></span>
            <span>Live Sync (5s)</span>
        </span>
        <a href="dashboard.php" class="btn btn-outline">&larr; Back to Dashboard</a>
    </div>
</div>

<!-- Filter Card -->
<div class="filter-card">
    <form class="filter-form" id="adminFilterForm" onsubmit="return false;">
        <div class="filter-group">
            <label for="adminFilterStatus">Status</label>
            <select id="adminFilterStatus" class="filter-select">
                <option value="">All Statuses</option>
                <?php foreach ($statuses as $st): ?>
                    <option value="<?= htmlspecialchars($st) ?>" <?= $statusFilter === $st ? 'selected' : '' ?>>
                        <?= htmlspecialchars($st) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="filter-group">
            <label for="adminFilterCategory">Category</label>
            <select id="adminFilterCategory" class="filter-select">
                <option value="">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= htmlspecialchars($cat) ?>" <?= $categoryFilter === $cat ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cat) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="filter-group">
            <label>Assigned Jurisdiction</label>
            <input 
                type="text" 
                class="filter-input" 
                value="<?= htmlspecialchars($wardenHostelName ?? 'All Hostels') ?>" 
                disabled 
                style="background-color: var(--primary-light); color: var(--primary); font-weight: 700;"
            >
        </div>

        <div class="filter-group" style="flex: 2; min-width: 200px;">
            <label for="adminFilterSearch">Search (Title, Description, Student)</label>
            <input 
                type="text" 
                id="adminFilterSearch" 
                class="filter-input" 
                placeholder="Type keywords..." 
                value="<?= htmlspecialchars($searchFilter) ?>"
            >
        </div>

        <div class="filter-actions">
            <button type="button" class="btn btn-outline" id="adminFilterResetBtn">
                Reset
            </button>
        </div>
    </form>
</div>

<!-- Complaints Table Card -->
<div class="table-card">
    <div class="table-header">
        <div>
            <h3><?= htmlspecialchars($wardenHostelName ?? 'Hostel') ?> Complaints</h3>
            <span style="font-size: 0.8rem; color: var(--text-muted);">Inline status updates sync live via Fetch API</span>
        </div>
        <span class="table-count-badge" id="adminTableCountBadge">
            <?= count($complaints) ?> complaint<?= count($complaints) === 1 ? '' : 's' ?>
        </span>
    </div>

    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Student Details</th>
                    <th>Issue Title</th>
                    <th>Category</th>
                    <th>Room</th>
                    <th>Current Status</th>
                    <th>Change Status (AJAX)</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody id="adminComplaintsTableBody">
                <?php if (empty($complaints)): ?>
                    <tr>
                        <td colspan="8" class="empty-state">
                            <div class="empty-state-icon">📋</div>
                            <h4>No Complaints in <?= htmlspecialchars($wardenHostelName ?? 'this hostel') ?></h4>
                            <p>No complaints match your criteria for this hostel.</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($complaints as $c): ?>
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
