<?php
// ====================================================================
// File: student.php
// Purpose: Student Portal with Signup, Login, and Session management
// Hostel: Aquamarine Hostel, IIT (ISM) Dhanbad
// ====================================================================

session_start();
require_once __DIR__ . '/config/db.php';

$pdo = getDbConnection();

// Action: Student Logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    unset($_SESSION['student_id'], $_SESSION['student_name'], $_SESSION['student_admission_no'], $_SESSION['student_email']);
    $_SESSION['flash_success'] = 'You have logged out successfully.';
    header('Location: student.php');
    exit;
}

// Action: Handle Student Login or Signup form submissions
$authError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['auth_type'])) {
    $authType = $_POST['auth_type'];

    // 1. STUDENT LOGIN
    if ($authType === 'login') {
        $adm = strtoupper(trim($_POST['admission_no'] ?? ''));
        $pwd = trim($_POST['password'] ?? '');

        if (!empty($adm) && !empty($pwd)) {
            $stmt = $pdo->prepare('SELECT id, name, admission_no, email, password FROM students WHERE admission_no = ?');
            $stmt->execute([$adm]);
            $student = $stmt->fetch();

            // Verify password using password_verify (no default pass)
            if ($student && !empty($student['password']) && password_verify($pwd, $student['password'])) {
                // Unset warden session if active
                unset($_SESSION['warden_logged_in']);

                $_SESSION['student_id']           = (int)$student['id'];
                $_SESSION['student_name']         = $student['name'];
                $_SESSION['student_admission_no'] = $student['admission_no'];
                $_SESSION['student_email']        = $student['email'];
                $_SESSION['flash_success']        = 'Welcome back, ' . $student['name'] . '!';
                header('Location: student.php');
                exit;
            } else {
                $authError = 'Invalid Admission Number or Password.';
            }
        } else {
            $authError = 'Please enter both Admission Number and Password.';
        }
    }

    // 2. STUDENT SIGNUP
    if ($authType === 'signup') {
        $name  = trim($_POST['name'] ?? '');
        $adm   = strtoupper(trim($_POST['admission_no'] ?? ''));
        $email = trim($_POST['email'] ?? '');
        $pwd   = trim($_POST['password'] ?? '');

        if (!empty($name) && !empty($adm) && !empty($email) && !empty($pwd)) {
            // Check if admission number is already registered
            $checkStmt = $pdo->prepare('SELECT id FROM students WHERE admission_no = ?');
            $checkStmt->execute([$adm]);

            if ($checkStmt->fetch()) {
                $authError = 'This Admission Number is already registered. Please Login.';
            } else {
                // Hash password and insert into students table
                $hashedPw = password_hash($pwd, PASSWORD_DEFAULT);
                $insertStmt = $pdo->prepare('INSERT INTO students (name, admission_no, email, password) VALUES (?, ?, ?, ?)');
                $insertStmt->execute([$name, $adm, $email, $hashedPw]);
                $newStudentId = (int)$pdo->lastInsertId();

                // Unset warden session if active
                unset($_SESSION['warden_logged_in']);

                // Save in session immediately
                $_SESSION['student_id']           = $newStudentId;
                $_SESSION['student_name']         = $name;
                $_SESSION['student_admission_no'] = $adm;
                $_SESSION['student_email']        = $email;
                $_SESSION['flash_success']        = 'Account created successfully! Welcome, ' . $name . '.';
                header('Location: student.php');
                exit;
            }
        } else {
            $authError = 'All fields are required to sign up.';
        }
    }
}

// Check logged in status
$isStudentLoggedIn = !empty($_SESSION['student_id']);
$currentStudentId  = $_SESSION['student_id'] ?? null;
$currentStudentName = $_SESSION['student_name'] ?? '';
$currentAdmissionNo = $_SESSION['student_admission_no'] ?? '';
$currentStudentEmail = $_SESSION['student_email'] ?? '';

// Flash alerts
$flashSuccess = $_SESSION['flash_success'] ?? '';
$flashError   = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// Retrieve complaints list:
// If logged in -> show that student's complaints
// If not logged in -> show all hostel complaints as public tracker
if ($isStudentLoggedIn) {
    $stmt = $pdo->prepare('
        SELECT c.id, c.title, c.description, c.category, c.status, c.created_at,
               s.name AS student_name, s.admission_no, s.email AS student_email
        FROM complaints c
        JOIN students s ON c.student_id = s.id
        WHERE c.student_id = ?
        ORDER BY c.created_at DESC, c.id DESC
    ');
    $stmt->execute([$currentStudentId]);
    $pageTitle = "My Maintenance Complaints";
} else {
    $stmt = $pdo->query('
        SELECT c.id, c.title, c.description, c.category, c.status, c.created_at,
               s.name AS student_name, s.admission_no, s.email AS student_email
        FROM complaints c
        JOIN students s ON c.student_id = s.id
        ORDER BY c.created_at DESC, c.id DESC
    ');
    $pageTitle = "Public Hostel Complaints Tracker";
}
$complaintsList = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Portal | Aquamarine Hostel</title>
    <link rel="stylesheet" href="assets/style.css?v=<?php echo time(); ?>">
</head>
<body data-student-admission="<?php echo htmlspecialchars($currentAdmissionNo); ?>">

    <!-- Top Navigation Header -->
    <header class="header-bar">
        <div class="header-container">
            <div class="brand-info">
                <h1>🎓 Student Portal</h1>
                <span class="hostel-badge">Aquamarine Hostel, IIT (ISM)</span>
            </div>
            <nav class="nav-links">
                <a href="index.php" class="nav-link">Home</a>
                <a href="student.php" class="nav-link active">Student Portal</a>
                <a href="warden.php" class="nav-link">Warden Dashboard</a>
                <?php if ($isStudentLoggedIn): ?>
                    <a href="student.php?action=logout" class="nav-link" style="background-color: #dc2626;">Logout</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>

    <!-- Main Content Area -->
    <main class="main-wrapper">

        <!-- Flash Notifications -->
        <?php if ($flashSuccess): ?>
            <div class="alert alert-success">✅ <?php echo htmlspecialchars($flashSuccess); ?></div>
        <?php endif; ?>
        <?php if ($flashError): ?>
            <div class="alert alert-error">⚠️ <?php echo htmlspecialchars($flashError); ?></div>
        <?php endif; ?>
        <?php if ($authError): ?>
            <div class="alert alert-error">⚠️ <?php echo htmlspecialchars($authError); ?></div>
        <?php endif; ?>

        <?php if (!$isStudentLoggedIn): ?>
            <!-- ========================================== -->
            <!-- STUDENT AUTH CARD (Login / Sign Up)        -->
            <!-- ========================================== -->
            <div class="auth-card">
                <div class="auth-tabs">
                    <button type="button" class="auth-tab-btn active" id="tabBtnLogin" onclick="switchAuthTab('login')">
                        Student Login
                    </button>
                    <button type="button" class="auth-tab-btn" id="tabBtnSignup" onclick="switchAuthTab('signup')">
                        New Student Sign Up
                    </button>
                </div>

                <!-- 1. Login Form -->
                <form id="studentLoginForm" method="POST" action="student.php">
                    <input type="hidden" name="auth_type" value="login">

                    <div class="form-group" style="margin-bottom: 14px;">
                        <label for="loginAdm" class="form-label">Admission Number *</label>
                        <input type="text" id="loginAdm" name="admission_no" class="form-control" placeholder="e.g. 23JE0501" required>
                    </div>

                    <div class="form-group" style="margin-bottom: 18px;">
                        <label for="loginPw" class="form-label">Password *</label>
                        <input type="password" id="loginPw" name="password" class="form-control" placeholder="••••••••" required>
                    </div>

                    <button type="submit" class="btn btn-primary" style="width: 100vw; max-width: 430px;">
                        Login to Student Portal &rarr;
                    </button>

                    <div class="auth-hint">
                        New student? Click <strong>"New Student Sign Up"</strong> above to register your account.
                    </div>
                </form>

                <!-- 2. Signup Form -->
                <form id="studentSignupForm" method="POST" action="student.php" style="display: none;">
                    <input type="hidden" name="auth_type" value="signup">

                    <div class="form-group" style="margin-bottom: 12px;">
                        <label for="signupName" class="form-label">Full Name *</label>
                        <input type="text" id="signupName" name="name" class="form-control" placeholder="e.g. Ritik Kumar" required>
                    </div>

                    <div class="form-group" style="margin-bottom: 12px;">
                        <label for="signupAdm" class="form-label">Admission Number *</label>
                        <input type="text" id="signupAdm" name="admission_no" class="form-control" placeholder="e.g. 23JE0501" required>
                    </div>

                    <div class="form-group" style="margin-bottom: 12px;">
                        <label for="signupEmail" class="form-label">Institute Email *</label>
                        <input type="email" id="signupEmail" name="email" class="form-control" placeholder="e.g. student@iitism.ac.in" required>
                    </div>

                    <div class="form-group" style="margin-bottom: 18px;">
                        <label for="signupPw" class="form-label">Create Password *</label>
                        <input type="password" id="signupPw" name="password" class="form-control" placeholder="Choose a password" required>
                    </div>

                    <button type="submit" class="btn btn-primary" style="width: 100vw; max-width: 430px;">
                        Create Account & Login &rarr;
                    </button>
                </form>
            </div>

            <script>
                function switchAuthTab(tab) {
                    const loginForm = document.getElementById('studentLoginForm');
                    const signupForm = document.getElementById('studentSignupForm');
                    const btnLogin = document.getElementById('tabBtnLogin');
                    const btnSignup = document.getElementById('tabBtnSignup');

                    if (tab === 'login') {
                        loginForm.style.display = 'block';
                        signupForm.style.display = 'none';
                        btnLogin.classList.add('active');
                        btnSignup.classList.remove('active');
                    } else {
                        loginForm.style.display = 'none';
                        signupForm.style.display = 'block';
                        btnSignup.classList.add('active');
                        btnLogin.classList.remove('active');
                    }
                }
            </script>

        <?php else: ?>
            <!-- ========================================== -->
            <!-- LOGGED-IN STUDENT DASHBOARD               -->
            <!-- ========================================== -->

            <!-- Session Bar -->
            <div class="student-bar">
                <div>
                    Logged in as: <strong><?php echo htmlspecialchars($currentStudentName); ?></strong> 
                    (Admission No: <strong><?php echo htmlspecialchars($currentAdmissionNo); ?></strong> &bull; <?php echo htmlspecialchars($currentStudentEmail); ?>)
                </div>
                <div>
                    <a href="student.php?action=logout" class="btn btn-secondary btn-sm" style="color: #dc2626;">Logout</a>
                </div>
            </div>

            <!-- Lodge Complaint Form -->
            <section class="form-card">
                <div class="form-header">
                    <h3>📝 Lodge a Maintenance Complaint</h3>
                    <p>Your student details are automatically linked from your session.</p>
                </div>

                <form id="complaintForm" method="POST" action="api/submit_complaint.php">
                    <!-- Pre-filled student fields from session -->
                    <input type="hidden" id="studentName" name="name" value="<?php echo htmlspecialchars($currentStudentName); ?>">
                    <input type="hidden" id="admissionNo" name="admission_no" value="<?php echo htmlspecialchars($currentAdmissionNo); ?>">
                    <input type="hidden" id="studentEmail" name="email" value="<?php echo htmlspecialchars($currentStudentEmail); ?>">

                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Student Name</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($currentStudentName); ?>" readonly style="background-color: #f8fafc;">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Admission Number</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($currentAdmissionNo); ?>" readonly style="background-color: #f8fafc;">
                        </div>

                        <div class="form-group">
                            <label for="complaintCategory" class="form-label">Maintenance Category *</label>
                            <select id="complaintCategory" name="category" class="form-control" required>
                                <option value="">-- Select Category --</option>
                                <option value="Electrical">⚡ Electrical (Fan, Light, Switch)</option>
                                <option value="Plumbing">🚰 Plumbing (Tap, Pipe, Washroom)</option>
                                <option value="Network">🌐 Network (Wi-Fi, LAN)</option>
                                <option value="Housekeeping">🧹 Housekeeping (Cleaning, Waste)</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="complaintTitle" class="form-label">Room / Location & Issue *</label>
                            <input type="text" id="complaintTitle" name="title" class="form-control" placeholder="e.g. Room B-204 Ceiling Fan Regulator" required>
                        </div>

                        <div class="form-group full-width">
                            <label for="complaintDescription" class="form-label">Detailed Description *</label>
                            <textarea id="complaintDescription" name="description" class="form-control" rows="3" placeholder="Describe the maintenance issue clearly..." required></textarea>
                        </div>
                    </div>

                    <div style="text-align: right;">
                        <button type="submit" class="btn btn-primary">Submit Complaint &rarr;</button>
                    </div>
                </form>
            </section>

        <?php endif; ?>

        <!-- Complaints List Section (Always Visible) -->
        <section>
            <!-- Filter Bar -->
            <div class="filter-bar">
                <div class="filter-controls">
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

                    <div class="filter-group">
                        <label for="statusFilter">Status:</label>
                        <select id="statusFilter" class="filter-select">
                            <option value="All">All Statuses</option>
                            <option value="Submitted">Submitted</option>
                            <option value="In Progress">In Progress</option>
                            <option value="Resolved">Resolved</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <input type="text" id="searchFilter" class="filter-select" placeholder="🔍 Search title or text...">
                    </div>
                </div>

                <div class="refresh-status-box">
                    <span class="pulse-dot"></span>
                    <span>Live Auto-Refresh (5s)</span>
                    <span style="color: #cbd5e1;">|</span>
                    <span>Synced: <span id="lastSyncTime"><?php echo date('H:i:s'); ?></span></span>
                </div>
            </div>

            <!-- Table Header -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                <h3 style="font-size: 18px; color: #0f172a;">
                    📋 <?php echo $pageTitle; ?> (<span id="visibleComplaintsCount"><?php echo count($complaintsList); ?></span>)
                </h3>
            </div>

            <!-- Table -->
            <div class="table-responsive">
                <table class="complaint-table" id="complaintsTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Title & Description</th>
                            <th>Category</th>
                            <th>Student</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($complaintsList)): ?>
                            <tr>
                                <td colspan="7" class="empty-state">
                                    <?php echo $isStudentLoggedIn ? "You haven't submitted any complaints yet." : "No complaints found."; ?>
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
                                        <div style="font-size: 12px; color: #64748b;"><?php echo htmlspecialchars($row['admission_no']); ?></div>
                                    </td>
                                    <td style="color: #64748b; font-size: 13px; white-space: nowrap;">
                                        <?php echo date('d M Y, h:i A', strtotime($row['created_at'])); ?>
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
                            <tr id="noFilterResultsRow" style="display: none;">
                                <td colspan="7" class="empty-state">No complaints match your filter criteria.</td>
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
