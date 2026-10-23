<?php
// ====================================================================
// File: warden.php
// Purpose: Warden (Admin) Dashboard to view, filter, and update complaints
// Project: College Web Development Lab (P4)
// Hostel: Aquamarine Hostel, IIT (ISM) Dhanbad
// ====================================================================

session_start();
require_once __DIR__ . '/config/db.php';

$pdo = getDbConnection();

// Flash messages
$flashSuccess = $_SESSION['flash_success'] ?? '';
$flashError = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// Fetch summary metrics
$totalCount = $pdo->query('SELECT COUNT(*) FROM complaints')->fetchColumn();
$submittedCount = $pdo->query("SELECT COUNT(*) FROM complaints WHERE status = 'Submitted'")->fetchColumn();
$inProgressCount = $pdo->query("SELECT COUNT(*) FROM complaints WHERE status = 'In Progress'")->fetchColumn();
$resolvedCount = $pdo->query("SELECT COUNT(*) FROM complaints WHERE status = 'Resolved'")->fetchColumn();

// Fetch all complaints for Aquamarine Hostel joined with students
$stmt = $pdo->query('
    SELECT c.id, c.title, c.description, c.category, c.status, c.created_at,
           s.name AS student_name, s.admission_no, s.email AS student_email
    FROM complaints c
    JOIN students s ON c.student_id = s.id
    ORDER BY c.created_at DESC, c.id DESC
');
$allComplaints = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Warden Dashboard | Hostel Maintenance Tracker - Aquamarine Hostel, IIT (ISM) Dhanbad</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>

    <!-- Header Bar -->
    <header class="header-bar">
        <div class="header-container">
            <div class="brand-info">
                <h1>🛡️ Warden Administration Dashboard</h1>
                <span class="hostel-badge">📍 Aquamarine Hostel, IIT (ISM) Dhanbad</span>
            </div>
            <nav class="nav-links">
                <a href="index.php" class="nav-link">Home</a>
                <a href="student.php" class="nav-link">Student Portal</a>
                <a href="warden.php" class="nav-link active">Warden Dashboard</a>
            </nav>
        </div>
    </header>

    <!-- Main Content Area -->
    <main class="main-wrapper">

        <!-- Flash Notifications -->
        <?php if (!empty($flashSuccess)): ?>
            <div class="alert alert-success">
                <span>✅ <?php echo htmlspecialchars($flashSuccess); ?></span>
            </div>
        <?php endif; ?>

        <?php if (!empty($flashError)): ?>
            <div class="alert alert-error">
                <span>⚠️ <?php echo htmlspecialchars($flashError); ?></span>
            </div>
        <?php endif; ?>

        <!-- Warden Greeting & Info -->
        <div style="margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: flex-end; flex-wrap: wrap; gap: 1rem;">
            <div>
                <h2 style="font-size: 1.5rem; color: #0f172a;">Aquamarine Hostel Helpdesk Management</h2>
                <p style="color: #64748b; font-size: 0.95rem;">
                    Review incoming student maintenance tickets and update their progress. Status changes persist in MariaDB and refresh live for students.
                </p>
            </div>
            <!-- Live Auto-Refresh Status Pill -->
            <div class="refresh-status-box">
                <span class="pulse-dot"></span>
                <span>Live Auto-Refresh (5s)</span>
                <span style="color: #94a3b8;">|</span>
                <span>Synced: <span id="lastSyncTime"><?php echo date('H:i:s'); ?></span></span>
            </div>
        </div>

        <!-- Metrics Cards -->
        <section class="stats-grid">
            <div class="stat-card total">
                <span class="stat-label">Total Registered</span>
                <span class="stat-number"><?php echo (int)$totalCount; ?></span>
            </div>
            <div class="stat-card submitted">
                <span class="stat-label">Action Required (Submitted)</span>
                <span class="stat-number"><?php echo (int)$submittedCount; ?></span>
            </div>
            <div class="stat-card in-progress">
                <span class="stat-label">In Progress</span>
                <span class="stat-number"><?php echo (int)$inProgressCount; ?></span>
            </div>
            <div class="stat-card resolved">
                <span class="stat-label">Resolved</span>
                <span class="stat-number"><?php echo (int)$resolvedCount; ?></span>
            </div>
        </section>

        <!-- Complaints Table & Filter Bar -->
        <section>
            <!-- Filter Bar -->
            <div class="filter-bar">
                <div class="filter-controls">
                    <!-- Category Filter -->
                    <div class="filter-group">
                        <label for="categoryFilter">Category:</label>
                        <select id="categoryFilter" class="filter-select">
                            <option value="All">All Categories</option>
                            <option value="Electrical">Electrical</option>
                            <option value="Plumbing">Plumbing</option>
                            <option value="Network">Network</option>
                            <option value="Housekeeping">Housekeeping</option>
                        </select>
                    </div>

                    <!-- Status Filter -->
                    <div class="filter-group">
                        <label for="statusFilter">Status:</label>
                        <select id="statusFilter" class="filter-select">
                            <option value="All">All Statuses</option>
                            <option value="Submitted">Submitted</option>
                            <option value="In Progress">In Progress</option>
                            <option value="Resolved">Resolved</option>
                        </select>
                    </div>

                    <!-- Search Filter -->
                    <div class="filter-group">
                        <input 
                            type="text" 
                            id="searchFilter" 
                            class="filter-select" 
                            placeholder="🔍 Search student, title, id..." 
                            style="min-width: 220px;"
                        >
                    </div>
                </div>

                <div style="font-size: 0.9rem; color: #475569; font-weight: 500;">
                    Showing <span id="visibleComplaintsCount" style="font-weight: 700; color: #0f172a;"><?php echo count($allComplaints); ?></span> of <?php echo count($allComplaints); ?> complaints
                </div>
            </div>

            <!-- Complaints Table -->
            <div class="table-responsive">
                <table class="complaint-table" id="complaintsTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Date</th>
                            <th>Student Resident</th>
                            <th>Category</th>
                            <th>Title & Description</th>
                            <th>Current Status</th>
                            <th>Update Status (Warden)</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($allComplaints)): ?>
                            <tr>
                                <td colspan="8" class="empty-state">
                                    <p>No complaints submitted yet for Aquamarine Hostel.</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($allComplaints as $row): 
                                $catClass = 'cat-' . strtolower($row['category']);
                                $statusClass = 'status-' . strtolower(str_replace(' ', '-', $row['status']));
                            ?>
                                <tr 
                                    class="complaint-row" 
                                    data-complaint-id="<?php echo (int)$row['id']; ?>" 
                                    data-category="<?php echo htmlspecialchars($row['category']); ?>" 
                                    data-status="<?php echo htmlspecialchars($row['status']); ?>"
                                >
                                    <td class="complaint-id">#CMP-<?php echo str_pad($row['id'], 3, '0', STR_PAD_LEFT); ?></td>
                                    <td style="color: #64748b; font-size: 0.85rem; white-space: nowrap;">
                                        <?php echo date('d M Y, h:i A', strtotime($row['created_at'])); ?>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($row['student_name']); ?></strong>
                                        <div style="font-size: 0.8rem; color: #0284c7; font-weight: 600;"><?php echo htmlspecialchars($row['admission_no']); ?></div>
                                        <div style="font-size: 0.75rem; color: #64748b;"><?php echo htmlspecialchars($row['student_email']); ?></div>
                                    </td>
                                    <td>
                                        <span class="category-badge <?php echo $catClass; ?>">
                                            <?php echo htmlspecialchars($row['category']); ?>
                                        </span>
                                    </td>
                                    <td class="complaint-title-cell">
                                        <?php echo htmlspecialchars($row['title']); ?>
                                        <div class="complaint-desc-snippet">
                                            <?php echo htmlspecialchars(mb_strimwidth($row['description'], 0, 80, '...')); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span 
                                            class="badge status-badge <?php echo $statusClass; ?>" 
                                            data-complaint-id="<?php echo (int)$row['id']; ?>"
                                            data-current-status="<?php echo htmlspecialchars($row['status']); ?>"
                                        >
                                            <?php echo htmlspecialchars($row['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <!-- Interactive Status Dropdown for Warden -->
                                        <select 
                                            class="filter-select warden-status-select" 
                                            data-complaint-id="<?php echo (int)$row['id']; ?>"
                                            data-original-status="<?php echo htmlspecialchars($row['status']); ?>"
                                            style="font-weight: 600; padding: 0.35rem 0.65rem;"
                                        >
                                            <option value="Submitted" <?php if ($row['status'] === 'Submitted') echo 'selected'; ?>>Submitted</option>
                                            <option value="In Progress" <?php if ($row['status'] === 'In Progress') echo 'selected'; ?>>In Progress</option>
                                            <option value="Resolved" <?php if ($row['status'] === 'Resolved') echo 'selected'; ?>>Resolved</option>
                                        </select>
                                    </td>
                                    <td>
                                        <button 
                                            type="button"
                                            class="btn btn-secondary btn-sm btn-view-complaint"
                                            data-id="<?php echo (int)$row['id']; ?>"
                                            data-title="<?php echo htmlspecialchars($row['title']); ?>"
                                            data-category="<?php echo htmlspecialchars($row['category']); ?>"
                                            data-status="<?php echo htmlspecialchars($row['status']); ?>"
                                            data-date="<?php echo date('d M Y, h:i A', strtotime($row['created_at'])); ?>"
                                            data-student-name="<?php echo htmlspecialchars($row['student_name']); ?>"
                                            data-admission-no="<?php echo htmlspecialchars($row['admission_no']); ?>"
                                            data-description="<?php echo htmlspecialchars($row['description']); ?>"
                                        >
                                            🔍 View
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <tr id="noFilterResultsRow" style="display: none;">
                                <td colspan="8" class="empty-state">
                                    <p>No complaints match the selected filter criteria.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

    </main>

    <!-- Complaint Details Modal -->
    <div class="modal-overlay" id="complaintModal">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h3 id="modalTitle">Complaint Details</h3>
                    <span style="font-size: 0.85rem; color: #64748b;" id="modalComplaintId">#CMP-000</span>
                </div>
                <button type="button" class="modal-close-btn" id="closeModalBtn">&times;</button>
            </div>
            <div class="modal-body">
                <div class="detail-row">
                    <span class="label">Hostel:</span>
                    <span>Aquamarine Hostel, IIT (ISM) Dhanbad</span>
                </div>
                <div class="detail-row">
                    <span class="label">Submitted By:</span>
                    <span id="modalStudent"></span>
                </div>
                <div class="detail-row">
                    <span class="label">Category:</span>
                    <span id="modalCategory"></span>
                </div>
                <div class="detail-row">
                    <span class="label">Current Status:</span>
                    <span id="modalStatus" class="badge"></span>
                </div>
                <div class="detail-row">
                    <span class="label">Date Logged:</span>
                    <span id="modalDate"></span>
                </div>
                <div>
                    <span class="label">Full Description:</span>
                    <div class="detail-desc-box" id="modalDescription"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer-bar">
        <p>
            <strong>Hostel Maintenance and Complaint Tracker</strong> &bull; Aquamarine Hostel, IIT (ISM) Dhanbad<br>
            Warden Administration &bull; MariaDB Status Persistence &bull; Asynchronous Polling
        </p>
    </footer>

    <script src="assets/script.js"></script>
</body>
</html>
