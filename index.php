<?php
// ====================================================================
// File: index.php
// Purpose: Main landing page for Hostel Maintenance and Complaint Tracker
// Project: College Web Development Lab (P4)
// Hostel: Aquamarine Hostel, IIT (ISM) Dhanbad
// ====================================================================

session_start();
require_once __DIR__ . '/config/db.php';

$pdo = getDbConnection();

// Fetch summary metrics for Aquamarine Hostel
$totalCount = $pdo->query('SELECT COUNT(*) FROM complaints')->fetchColumn();
$submittedCount = $pdo->query("SELECT COUNT(*) FROM complaints WHERE status = 'Submitted'")->fetchColumn();
$inProgressCount = $pdo->query("SELECT COUNT(*) FROM complaints WHERE status = 'In Progress'")->fetchColumn();
$resolvedCount = $pdo->query("SELECT COUNT(*) FROM complaints WHERE status = 'Resolved'")->fetchColumn();

// Fetch recent 6 complaints with student info
$stmtRecent = $pdo->query('
    SELECT c.id, c.title, c.description, c.category, c.status, c.created_at,
           s.name AS student_name, s.admission_no
    FROM complaints c
    JOIN students s ON c.student_id = s.id
    ORDER BY c.created_at DESC, c.id DESC
    LIMIT 6
');
$recentComplaints = $stmtRecent->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hostel Maintenance and Complaint Tracker | Aquamarine Hostel, IIT (ISM) Dhanbad</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>

    <!-- Header Bar with Fixed Hostel Name -->
    <header class="header-bar">
        <div class="header-container">
            <div class="brand-info">
                <h1>🔧 Hostel Maintenance and Complaint Tracker</h1>
                <span class="hostel-badge">📍 Aquamarine Hostel, IIT (ISM) Dhanbad</span>
            </div>
            <nav class="nav-links">
                <a href="index.php" class="nav-link active">Home</a>
                <a href="student.php" class="nav-link">Student Portal</a>
                <a href="warden.php" class="nav-link">Warden Dashboard</a>
            </nav>
        </div>
    </header>

    <!-- Main Content Area -->
    <main class="main-wrapper">

        <!-- Hero Section -->
        <section class="hero-banner">
            <h2>Welcome to Aquamarine Hostel Helpdesk</h2>
            <p>
                A centralized maintenance tracking system for students and administration of Aquamarine Hostel, 
                IIT (ISM) Dhanbad. Submit issues, track resolutions, and monitor live status updates asynchronously.
            </p>
            <div style="display: flex; justify-content: center; gap: 1rem; flex-wrap: wrap;">
                <a href="student.php" class="btn btn-primary">📝 Submit or View Complaint</a>
                <a href="warden.php" class="btn btn-secondary">🛡️ Warden Login / Dashboard</a>
            </div>
        </section>

        <!-- Metrics Overview Grid -->
        <section class="stats-grid">
            <div class="stat-card total">
                <span class="stat-label">Total Complaints</span>
                <span class="stat-number"><?php echo (int)$totalCount; ?></span>
            </div>
            <div class="stat-card submitted">
                <span class="stat-label">Submitted (Pending)</span>
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

        <!-- Role Portals Grid -->
        <section class="portal-cards-grid">
            <div class="portal-card">
                <div>
                    <h3>🎓 Student Portal</h3>
                    <p>
                        Are you an Aquamarine Hostel resident facing electrical, plumbing, network, or housekeeping issues? 
                        Lodge a complaint in seconds and watch your resolution progress live without manual page reloads.
                    </p>
                </div>
                <a href="student.php" class="btn btn-primary">Enter Student Portal &rarr;</a>
            </div>

            <div class="portal-card">
                <div>
                    <h3>👔 Warden (Admin) Portal</h3>
                    <p>
                        Administer all complaints logged for Aquamarine Hostel. Filter by category, review resident issue descriptions, 
                        and update work statuses from <em>Submitted</em> to <em>In Progress</em> or <em>Resolved</em>.
                    </p>
                </div>
                <a href="warden.php" class="btn btn-secondary">Enter Warden Dashboard &rarr;</a>
            </div>
        </section>

        <!-- Recent Complaints Section with Live Auto-Refresh -->
        <section style="margin-top: 2.5rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; flex-wrap: wrap; gap: 0.5rem;">
                <h3 style="color: #0f172a; font-size: 1.25rem;">📋 Recent Hostel Complaints</h3>
                <div class="refresh-status-box">
                    <span class="pulse-dot"></span>
                    <span>Live Auto-Refresh (5s)</span>
                    <span style="color: #94a3b8;">|</span>
                    <span>Synced: <span id="lastSyncTime"><?php echo date('H:i:s'); ?></span></span>
                </div>
            </div>

            <div class="table-responsive">
                <table class="complaint-table" id="complaintsTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Complaint Details</th>
                            <th>Category</th>
                            <th>Student</th>
                            <th>Date Logged</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentComplaints)): ?>
                            <tr>
                                <td colspan="6" class="empty-state">
                                    <p>No complaints submitted yet for Aquamarine Hostel.</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recentComplaints as $row): 
                                $catClass = 'cat-' . strtolower($row['category']);
                                $statusClass = 'status-' . strtolower(str_replace(' ', '-', $row['status']));
                            ?>
                                <tr class="complaint-row" data-complaint-id="<?php echo (int)$row['id']; ?>" data-category="<?php echo htmlspecialchars($row['category']); ?>" data-status="<?php echo htmlspecialchars($row['status']); ?>">
                                    <td class="complaint-id">#CMP-<?php echo str_pad($row['id'], 3, '0', STR_PAD_LEFT); ?></td>
                                    <td class="complaint-title-cell">
                                        <?php echo htmlspecialchars($row['title']); ?>
                                        <div class="complaint-desc-snippet">
                                            <?php echo htmlspecialchars(mb_strimwidth($row['description'], 0, 75, '...')); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="category-badge <?php echo $catClass; ?>">
                                            <?php echo htmlspecialchars($row['category']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($row['student_name']); ?></strong>
                                        <div style="font-size: 0.8rem; color: #64748b;"><?php echo htmlspecialchars($row['admission_no']); ?></div>
                                    </td>
                                    <td style="color: #64748b; font-size: 0.85rem;">
                                        <?php echo date('d M Y, h:i A', strtotime($row['created_at'])); ?>
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
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

    </main>

    <!-- Footer -->
    <footer class="footer-bar">
        <p>
            <strong>Hostel Maintenance and Complaint Tracker</strong> &bull; Aquamarine Hostel, IIT (ISM) Dhanbad<br>
            Developed with plain HTML, CSS, JavaScript, PHP, and MariaDB.
        </p>
    </footer>

    <script src="assets/script.js"></script>
</body>
</html>
