<?php
// ====================================================================
// File: warden.php
// Purpose: Warden (Admin) Dashboard with fixed ID/Password authentication
// Hostel: Aquamarine Hostel, IIT (ISM) Dhanbad
// ====================================================================

session_start();
require_once __DIR__ . '/config/db.php';

// Fixed Warden Credentials
$FIXED_WARDEN_ID = 'warden';
$FIXED_WARDEN_PW = 'warden123';

// Action: Warden Logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    unset($_SESSION['warden_logged_in']);
    header('Location: warden.php');
    exit;
}

// Action: Warden Login Submission
$loginError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['warden_login'])) {
    $enteredId = trim($_POST['warden_id'] ?? '');
    $enteredPw = trim($_POST['warden_password'] ?? '');

    if ($enteredId === $FIXED_WARDEN_ID && $enteredPw === $FIXED_WARDEN_PW) {
        // Unset student session when warden logs in
        unset($_SESSION['student_id'], $_SESSION['student_name'], $_SESSION['student_admission_no'], $_SESSION['student_email']);

        $_SESSION['warden_logged_in'] = true;
        header('Location: warden.php');
        exit;
    } else {
        $loginError = 'Invalid Warden ID or Password.';
    }
}

$isWardenLoggedIn = !empty($_SESSION['warden_logged_in']);

// Flash messages
$flashSuccess = $_SESSION['flash_success'] ?? '';
$flashError   = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// If logged in, fetch dashboard complaints and stats
if ($isWardenLoggedIn) {
    $pdo = getDbConnection();

    // Summary metrics
    $totalCount      = $pdo->query('SELECT COUNT(*) FROM complaints')->fetchColumn();
    $submittedCount  = $pdo->query("SELECT COUNT(*) FROM complaints WHERE status = 'Submitted'")->fetchColumn();
    $inProgressCount = $pdo->query("SELECT COUNT(*) FROM complaints WHERE status = 'In Progress'")->fetchColumn();
    $resolvedCount   = $pdo->query("SELECT COUNT(*) FROM complaints WHERE status = 'Resolved'")->fetchColumn();

    // All complaints joined with student info
    $stmt = $pdo->query('
        SELECT c.id, c.title, c.description, c.category, c.status, c.created_at,
               s.name AS student_name, s.admission_no, s.email AS student_email
        FROM complaints c
        JOIN students s ON c.student_id = s.id
        ORDER BY c.created_at DESC, c.id DESC
    ');
    $allComplaints = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Warden Portal | Aquamarine Hostel</title>
    <link rel="stylesheet" href="assets/style.css?v=<?php echo time(); ?>">
</head>
<body>

    <!-- Header Navigation -->
    <header class="header-bar">
        <div class="header-container">
            <div class="brand-info">
                <h1>🛡️ Warden Portal</h1>
                <span class="hostel-badge">Aquamarine Hostel, IIT (ISM)</span>
            </div>
            <nav class="nav-links">
                <a href="student.php" class="nav-link">Student Portal</a>
                <a href="warden.php" class="nav-link active">Warden Portal</a>
                <?php if ($isWardenLoggedIn): ?>
                    <a href="warden.php?action=logout" class="nav-link" style="background-color: #dc2626;">Logout</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>

    <!-- Main Content Area -->
    <main class="main-wrapper <?php echo (!$isWardenLoggedIn) ? 'auth-main' : ''; ?>">

        <?php if (!$isWardenLoggedIn): ?>
            <!-- ========================================== -->
            <!-- WARDEN LOGIN FORM (Fixed Credentials)     -->
            <!-- ========================================== -->
            <?php if ($loginError): ?>
                <div class="alert alert-error" style="width: 480px; margin-bottom: 16px;">⚠️ <?php echo htmlspecialchars($loginError); ?></div>
            <?php endif; ?>
            <?php if ($flashError): ?>
                <div class="alert alert-error" style="width: 480px; margin-bottom: 16px;">⚠️ <?php echo htmlspecialchars($flashError); ?></div>
            <?php endif; ?>

            <div class="auth-card">
                <div class="form-header" style="text-align: center;">
                    <h3>🛡️ Warden Login</h3>
                    <p>Enter the administrative credentials to manage complaints.</p>
                </div>

                <form method="POST" action="warden.php">
                    <input type="hidden" name="warden_login" value="1">

                    <div class="form-group" style="margin-bottom: 14px;">
                        <label for="wardenId" class="form-label">Warden ID *</label>
                        <input type="text" id="wardenId" name="warden_id" class="form-control" placeholder="warden" required autofocus>
                    </div>

                    <div class="form-group" style="margin-bottom: 18px;">
                        <label for="wardenPw" class="form-label">Password *</label>
                        <input type="password" id="wardenPw" name="warden_password" class="form-control" placeholder="••••••••" required>
                    </div>

                    <button type="submit" class="btn btn-primary" style="width: 100vw; max-width: 430px;">
                        Login as Warden &rarr;
                    </button>
                </form>

                
            </div>

        <?php else: ?>
            <!-- ========================================== -->
            <!-- WARDEN DASHBOARD (When Authenticated)      -->
            <!-- ========================================== -->

            <!-- Flash Notifications -->
            <?php if ($flashSuccess): ?>
                <div class="alert alert-success">✅ <?php echo htmlspecialchars($flashSuccess); ?></div>
            <?php endif; ?>
            <?php if ($flashError): ?>
                <div class="alert alert-error">⚠️ <?php echo htmlspecialchars($flashError); ?></div>
            <?php endif; ?>

            <!-- Welcome / Session Banner -->
            <div style="margin-bottom: 20px; margin-top: 20px; ">
            
                    <h1>Hostel Warden</h1>
         
            </div> 

            <!-- Metrics Cards -->
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

            <!-- Complaints List Section -->
            <section>
                <div style="margin-bottom: 1rem;">
                    <h3 style="font-size: 1.15rem; color: #0f172a; margin: 0;">
                        📋 All Hostel Complaints (<strong><?php echo count($allComplaints); ?></strong>)
                    </h3>
                </div>

                <!-- Complaints Table -->
                <div class="table-responsive">
                    <table class="complaint-table" id="complaintsTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Date</th>
                                <th>Student</th>
                                <th>Category</th>
                                <th>Title & Description</th>
                                <th>Status</th>
                                <th>Update Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($allComplaints)): ?>
                                <tr>
                                    <td colspan="8" class="empty-state">No complaints submitted yet for Aquamarine Hostel.</td>
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
                                        <td style="color: #64748b; font-size: 13px; white-space: nowrap;">
                                            <?php echo date('d M Y, h:i A', strtotime($row['created_at'])); ?>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($row['student_name']); ?></strong>
                                            <div style="font-size: 12px; color: #0284c7; font-weight: bold;"><?php echo htmlspecialchars($row['admission_no']); ?></div>
                                        </td>
                                        <td>
                                            <span class="category-badge <?php echo $catClass; ?>">
                                                <?php echo htmlspecialchars($row['category']); ?>
                                            </span>
                                        </td>
                                        <td class="complaint-title-cell">
                                            <?php echo htmlspecialchars($row['title']); ?>
                                            <div class="complaint-desc-snippet">
                                                <?php echo htmlspecialchars(mb_strimwidth($row['description'], 0, 75, '...')); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span 
                                                class="badge status-badge <?php echo $statusClass; ?>" 
                                                data-complaint-id="<?php echo (int)$row['id']; ?>"
                                            >
                                                <?php echo htmlspecialchars($row['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <!-- Interactive Status Dropdown for Warden -->
                                            <select 
                                                class="filter-select warden-status-select" 
                                                data-complaint-id="<?php echo (int)$row['id']; ?>"
                                                style="padding: 4px 8px; font-size: 13px;"
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
                                                View
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

        <?php endif; ?>

    </main>

    <!-- Complaint Details Modal -->
    <div class="modal-overlay" id="complaintModal">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h3 id="modalTitle">Complaint Details</h3>
                    <span style="font-size: 13px; color: #64748b;" id="modalComplaintId">#CMP-000</span>
                </div>
                <button type="button" class="modal-close-btn" id="closeModalBtn">&times;</button>
            </div>
            <div class="modal-body">
                <div class="detail-row">
                    <span class="label">Hostel:</span>
                    <span>Aquamarine Hostel, IIT (ISM) Dhanbad</span>
                </div>
                <div class="detail-row">
                    <span class="label">Student:</span>
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
                    <span class="label">Description:</span>
                    <div class="detail-desc-box" id="modalDescription"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer-bar">
        <p>Hostel Maintenance and Complaint Tracker &bull; Aquamarine Hostel, IIT (ISM) Dhanbad</p>
    </footer>

    <script src="assets/script.js?v=<?php echo time(); ?>"></script>
</body>
</html>
