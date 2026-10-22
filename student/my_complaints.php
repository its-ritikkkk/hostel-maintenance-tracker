<?php
// ===============================================================
// student/my_complaints.php - View and Filter Submitted Complaints
// ===============================================================
// Displays all complaints submitted by the logged-in student with
// category/status filters, search, and 5-second asynchronous live updates.

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

requireStudent();

$studentId = (int) $_SESSION['user_id'];
$categories = getComplaintCategories();
$statuses = getComplaintStatuses();

$statusFilter = trim($_GET['status'] ?? '');
$categoryFilter = trim($_GET['category'] ?? '');
$searchFilter = trim($_GET['search'] ?? '');

$complaints = [];

try {
    $db = getDbConnection();

    $sql = 'SELECT id, title, description, category, hostel, room_number, status, created_at, updated_at
            FROM complaints
            WHERE student_id = :student_id';
    $params = ['student_id' => $studentId];

    if (!empty($statusFilter) && in_array($statusFilter, $statuses, true)) {
        $sql .= ' AND status = :status';
        $params['status'] = $statusFilter;
    }

    if (!empty($categoryFilter) && in_array($categoryFilter, $categories, true)) {
        $sql .= ' AND category = :category';
        $params['category'] = $categoryFilter;
    }

    if (!empty($searchFilter)) {
        $sql .= ' AND (title LIKE :search OR description LIKE :search)';
        $params['search'] = '%' . $searchFilter . '%';
    }

    $sql .= ' ORDER BY created_at DESC';

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $complaints = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('my_complaints fetch error: ' . $e->getMessage());
}

$pageTitle = 'My Complaints';
$pageScript = 'complaints.js';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div class="page-title-group">
        <h2>My Maintenance Complaints</h2>
        <p>IIT (ISM) Dhanbad • Real-time status tracking for residential tickets</p>
    </div>
    <div class="page-actions">
        <!-- Asynchronous live status refresh indicator -->
        <span class="sync-indicator" id="syncIndicator" title="Statuses refresh automatically every 5 seconds without reloading">
            <span class="sync-dot"></span>
            <span>Live Sync (5s)</span>
        </span>
        <a href="create_complaint.php" class="btn btn-secondary">
            + New Complaint
        </a>
    </div>
</div>

<!-- Asynchronous Filter Toolbar -->
<div class="filter-card">
    <form class="filter-form" id="complaintFilterForm" onsubmit="return false;">
        <div class="filter-group">
            <label for="filterStatus">Status</label>
            <select id="filterStatus" class="filter-select">
                <option value="">All Statuses</option>
                <?php foreach ($statuses as $st): ?>
                    <option value="<?= htmlspecialchars($st) ?>" <?= $statusFilter === $st ? 'selected' : '' ?>>
                        <?= htmlspecialchars($st) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="filter-group">
            <label for="filterCategory">Category</label>
            <select id="filterCategory" class="filter-select">
                <option value="">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= htmlspecialchars($cat) ?>" <?= $categoryFilter === $cat ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cat) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="filter-group" style="flex: 2; min-width: 220px;">
            <label for="filterSearch">Search Keywords</label>
            <input 
                type="text" 
                id="filterSearch" 
                class="filter-input" 
                placeholder="Search title or description..." 
                value="<?= htmlspecialchars($searchFilter) ?>"
            >
        </div>

        <div class="filter-actions">
            <button type="button" class="btn btn-outline" id="filterResetBtn">
                Reset
            </button>
        </div>
    </form>
</div>

<!-- Complaints Data Table -->
<div class="table-card">
    <div class="table-header">
        <h3>Submitted Complaints</h3>
        <span class="table-count-badge" id="tableCountBadge">
            <?= count($complaints) ?> complaint<?= count($complaints) === 1 ? '' : 's' ?>
        </span>
    </div>

    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Title & Summary</th>
                    <th>Category</th>
                    <th>Hostel & Room</th>
                    <th>Status</th>
                    <th>Submitted On</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody id="complaintsTableBody">
                <?php if (empty($complaints)): ?>
                    <tr>
                        <td colspan="7" class="empty-state">
                            <div class="empty-state-icon">📭</div>
                            <h4>No Complaints Found</h4>
                            <p>No complaints match your current filter selection.</p>
                            <a href="create_complaint.php" class="btn btn-primary btn-sm" style="margin-top: 0.75rem;">
                                Lodge New Complaint
                            </a>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($complaints as $c): ?>
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
