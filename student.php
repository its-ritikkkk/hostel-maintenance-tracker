<?php
// ====================================================================
// File: student.php
// Purpose: Student complaint submission and tracking interface
// Project: College Web Development Lab (P4)
// Hostel: Aquamarine Hostel, IIT (ISM) Dhanbad
// ====================================================================

session_start();
require_once __DIR__ . '/config/db.php';

$pdo = getDbConnection();

// Check if student wants to switch or logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    unset($_SESSION['student_admission_no']);
    unset($_SESSION['student_name']);
    unset($_SESSION['student_email']);
    unset($_SESSION['student_id']);
    header('Location: student.php');
    exit;
}

// Check if student identity was passed in GET (for quick demo selection)
if (!empty($_GET['admission_no'])) {
    $admQuery = strtoupper(trim($_GET['admission_no']));
    $stmtFind = $pdo->prepare('SELECT id, name, admission_no, email FROM students WHERE admission_no = ?');
    $stmtFind->execute([$admQuery]);
    $foundStudent = $stmtFind->fetch();

    if ($foundStudent) {
        $_SESSION['student_admission_no'] = $foundStudent['admission_no'];
        $_SESSION['student_name'] = $foundStudent['name'];
        $_SESSION['student_email'] = $foundStudent['email'];
        $_SESSION['student_id'] = $foundStudent['id'];
    } else {
        // If it's a new admission number without student record yet, keep in session
        $_SESSION['student_admission_no'] = $admQuery;
    }
}

// Current logged in student information
$currentAdmissionNo = $_SESSION['student_admission_no'] ?? '';
$currentStudentName = $_SESSION['student_name'] ?? '';
$currentStudentEmail = $_SESSION['student_email'] ?? '';

// Flash notifications
$flashSuccess = $_SESSION['flash_success'] ?? '';
$flashError = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// Retrieve complaints:
// If student is identified, fetch their complaints; otherwise fetch recent complaints for Aquamarine Hostel
if (!empty($currentAdmissionNo)) {
    $stmtComplaints = $pdo->prepare('
        SELECT c.id, c.title, c.description, c.category, c.status, c.created_at,
               s.name AS student_name, s.admission_no, s.email AS student_email
        FROM complaints c
        JOIN students s ON c.student_id = s.id
        WHERE s.admission_no = ?
        ORDER BY c.created_at DESC, c.id DESC
    ');
    $stmtComplaints->execute([$currentAdmissionNo]);
    $complaintsList = $stmtComplaints->fetchAll();
    $pageTitle = "My Complaints (" . htmlspecialchars($currentAdmissionNo) . ")";
} else {
    // If not logged in yet, show all Aquamarine hostel complaints so student can see public tracker
    $stmtComplaints = $pdo->query('
        SELECT c.id, c.title, c.description, c.category, c.status, c.created_at,
               s.name AS student_name, s.admission_no, s.email AS student_email
        FROM complaints c
        JOIN students s ON c.student_id = s.id
        ORDER BY c.created_at DESC, c.id DESC
    ');
    $complaintsList = $stmtComplaints->fetchAll();
    $pageTitle = "All Aquamarine Hostel Complaints";
}

// Fetch list of sample students for 1-click lab demonstration
$sampleStudents = $pdo->query('SELECT id, name, admission_no, email FROM students LIMIT 5')->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Portal | Hostel Maintenance Tracker - Aquamarine Hostel, IIT (ISM) Dhanbad</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body data-student-admission="<?php echo htmlspecialchars($currentAdmissionNo); ?>">

    <!-- Top Header Bar -->
    <header class="header-bar">
        <div class="header-container">
            <div class="brand-info">
                <h1>🎓 Student Complaint Portal</h1>
                <span class="hostel-badge">📍 Aquamarine Hostel, IIT (ISM) Dhanbad</span>
            </div>
            <nav class="nav-links">
                <a href="index.php" class="nav-link">Home</a>
                <a href="student.php" class="nav-link active">Student Portal</a>
                <a href="warden.php" class="nav-link">Warden Dashboard</a>
            </nav>
        </div>
    </header>

    <!-- Main Content -->
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

        <!-- Student Identification / Quick Switch Bar -->
        <section class="student-bar">
            <div class="student-bar-info">
                <?php if (!empty($currentAdmissionNo)): ?>
                    <span>Identified Student: <strong><?php echo htmlspecialchars($currentStudentName ?: 'Student'); ?></strong> (Adm No: <strong><?php echo htmlspecialchars($currentAdmissionNo); ?></strong>)</span>
                <?php else: ?>
                    <span>Viewing public complaints list. Select or enter your Admission Number to filter your complaints.</span>
                <?php endif; ?>
            </div>

            <div style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
                <?php if (!empty($currentAdmissionNo)): ?>
                    <a href="student.php?action=logout" class="btn btn-secondary btn-sm">Clear / View All</a>
                <?php endif; ?>
                <!-- Quick Demo Switch Dropdown -->
                <form method="GET" action="student.php" style="display: inline-flex; gap: 0.35rem; align-items: center;">
                    <label for="switchAdm" style="font-size: 0.85rem; color: #64748b;">Quick Student:</label>
                    <select id="switchAdm" name="admission_no" class="filter-select" onchange="this.form.submit()">
                        <option value="">-- Choose Student --</option>
                        <?php foreach ($sampleStudents as $s): ?>
                            <option value="<?php echo htmlspecialchars($s['admission_no']); ?>" <?php if ($currentAdmissionNo === $s['admission_no']) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($s['name']) . ' (' . htmlspecialchars($s['admission_no']) . ')'; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
        </section>

        <!-- Complaint Submission Form -->
        <section class="form-card">
            <div class="form-header">
                <h3>📝 Submit a New Hostel Maintenance Complaint</h3>
                <p>All complaints are routed directly to the Aquamarine Hostel Warden. Initial status is set to <strong>Submitted</strong>.</p>
            </div>

            <form id="complaintForm" method="POST" action="api/submit_complaint.php">
                <div class="form-grid">
                    <!-- Student Name -->
                    <div class="form-group">
                        <label for="studentName" class="form-label">Full Name *</label>
                        <input 
                            type="text" 
                            id="studentName" 
                            name="name" 
                            class="form-control" 
                            placeholder="e.g. Ritik Kumar" 
                            value="<?php echo htmlspecialchars($currentStudentName); ?>" 
                            required
                        >
                    </div>

                    <!-- Admission Number -->
                    <div class="form-group">
                        <label for="admissionNo" class="form-label">Admission Number *</label>
                        <input 
                            type="text" 
                            id="admissionNo" 
                            name="admission_no" 
                            class="form-control" 
                            placeholder="e.g. 23JE0501" 
                            value="<?php echo htmlspecialchars($currentAdmissionNo); ?>" 
                            required
                        >
                    </div>

                    <!-- Email Address -->
                    <div class="form-group">
                        <label for="studentEmail" class="form-label">Institute Email *</label>
                        <input 
                            type="email" 
                            id="studentEmail" 
                            name="email" 
                            class="form-control" 
                            placeholder="e.g. student@iitism.ac.in" 
                            value="<?php echo htmlspecialchars($currentStudentEmail); ?>" 
                            required
                        >
                    </div>

                    <!-- Category Selection -->
                    <div class="form-group">
                        <label for="complaintCategory" class="form-label">Maintenance Category *</label>
                        <select id="complaintCategory" name="category" class="form-control" required>
                            <option value="">-- Select Category --</option>
                            <option value="Electrical">⚡ Electrical (Fan, Light, Switch, Geyser)</option>
                            <option value="Plumbing">🚰 Plumbing (Tap, Pipe, Drainage, Washroom)</option>
                            <option value="Network">🌐 Network (LAN port, Wi-Fi connectivity)</option>
                            <option value="Housekeeping">🧹 Housekeeping (Cleaning, Waste, Dustbin)</option>
                        </select>
                    </div>

                    <!-- Complaint Title -->
                    <div class="form-group full-width">
                        <label for="complaintTitle" class="form-label">Complaint Title *</label>
                        <input 
                            type="text" 
                            id="complaintTitle" 
                            name="title" 
                            class="form-control" 
                            placeholder="e.g. Room B-204 Ceiling Fan Regulator Broken" 
                            required
                        >
                    </div>

                    <!-- Detailed Description -->
                    <div class="form-group full-width">
                        <label for="complaintDescription" class="form-label">Detailed Description *</label>
                        <textarea 
                            id="complaintDescription" 
                            name="description" 
                            class="form-control" 
                            rows="3" 
                            placeholder="Please describe the maintenance issue with room/location details..." 
                            required
                        ></textarea>
                    </div>
                </div>

                <div style="display: flex; justify-content: flex-end;">
                    <button type="submit" class="btn btn-primary">
                        🚀 Submit Maintenance Request
                    </button>
                </div>
            </form>
        </section>

        <!-- Complaints List & Filter Section -->
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
                            placeholder="🔍 Filter by title or text..." 
                            style="min-width: 180px;"
                        >
                    </div>
                </div>

                <!-- Live Auto-Refresh Status Pill -->
                <div class="refresh-status-box">
                    <span class="pulse-dot"></span>
                    <span>Live Auto-Refresh (5s)</span>
                    <span style="color: #94a3b8;">|</span>
                    <span>Synced: <span id="lastSyncTime"><?php echo date('H:i:s'); ?></span></span>
                </div>
            </div>

            <!-- Complaints Table Header with Count -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                <h3 style="font-size: 1.2rem; color: #0f172a;">
                    📋 <?php echo $pageTitle; ?> (<span id="visibleComplaintsCount"><?php echo count($complaintsList); ?></span>)
                </h3>
            </div>

            <!-- Complaints Table -->
            <div class="table-responsive">
                <table class="complaint-table" id="complaintsTable">
                    <thead>
                        <tr>
                            <th>Complaint ID</th>
                            <th>Title & Description</th>
                            <th>Category</th>
                            <th>Student</th>
                            <th>Date & Time</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($complaintsList)): ?>
                            <tr>
                                <td colspan="7" class="empty-state">
                                    <p>No complaints found matching your criteria.</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($complaintsList as $row): 
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
                                    <td class="complaint-title-cell">
                                        <?php echo htmlspecialchars($row['title']); ?>
                                        <div class="complaint-desc-snippet">
                                            <?php echo htmlspecialchars(mb_strimwidth($row['description'], 0, 80, '...')); ?>
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
                                    <td style="color: #64748b; font-size: 0.85rem; white-space: nowrap;">
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
                                <td colspan="7" class="empty-state">
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
            Real-time status updates via JavaScript <code>fetch()</code> and MariaDB.
        </p>
    </footer>

    <script src="assets/script.js"></script>
</body>
</html>
