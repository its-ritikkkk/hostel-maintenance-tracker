<?php
// ===============================================================
// auth/login.php - User & Warden Authentication
// ===============================================================
// Handles login for Students and Hostel Wardens of IIT (ISM) Dhanbad.
// Loads assigned hostel_id into session to enforce multi-hostel isolation.

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

// If already authenticated, redirect to appropriate dashboard
if (isLoggedIn()) {
    if (getCurrentUserRole() === 'admin') {
        header('Location: ../admin/dashboard.php');
    } else {
        header('Location: ../student/dashboard.php');
    }
    exit;
}

$errorMessage = '';
$emailValue = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($csrfToken)) {
        $errorMessage = 'Security token invalid or expired. Please try again.';
    } else {
        $emailValue = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($emailValue) || empty($password)) {
            $errorMessage = 'Please enter both your institute email address and password.';
        } elseif (!filter_var($emailValue, FILTER_VALIDATE_EMAIL)) {
            $errorMessage = 'Please enter a valid institute email address.';
        } else {
            try {
                $db = getDbConnection();

                // Query user and join hostels table to ensure accurate assigned hostel details
                $stmt = $db->prepare(
                    'SELECT 
                        u.id, 
                        u.name, 
                        u.email, 
                        u.password_hash, 
                        u.role, 
                        u.hostel_id, 
                        COALESCE(h.name, u.hostel) AS hostel_name,
                        u.room_number 
                     FROM users u
                     LEFT JOIN hostels h ON u.hostel_id = h.id
                     WHERE u.email = :email 
                     LIMIT 1'
                );
                $stmt->execute(['email' => $emailValue]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['password_hash'])) {
                    // Regenerate session ID to prevent session fixation attacks
                    session_regenerate_id(true);

                    $_SESSION['user_id'] = (int) $user['id'];
                    $_SESSION['user_name'] = $user['name'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['user_hostel_id'] = $user['hostel_id'] !== null ? (int) $user['hostel_id'] : null;
                    $_SESSION['user_hostel'] = $user['hostel_name'];
                    $_SESSION['user_room'] = $user['room_number'];

                    $_SESSION['flash_success'] = 'Welcome back, ' . htmlspecialchars($user['name']) . '!';

                    if ($user['role'] === 'admin') {
                        header('Location: ../admin/dashboard.php');
                    } else {
                        header('Location: ../student/dashboard.php');
                    }
                    exit;
                } else {
                    $errorMessage = 'Invalid email address or password. Please check your credentials.';
                }
            } catch (PDOException $e) {
                error_log('Login database error: ' . $e->getMessage());
                $errorMessage = 'A database error occurred. Please try again later.';
            }
        }
    }
}

$pageTitle = 'Login';
include __DIR__ . '/../includes/header.php';
?>

<div class="auth-wrapper">
    <div class="auth-card" style="max-width: 520px;">
        <div class="auth-header">
            <span class="auth-badge">IIT (ISM) Dhanbad</span>
            <h2>Sign In to Portal</h2>
            <p>Hostel Maintenance & Warden Redressal System</p>
        </div>

        <!-- Multi-Hostel Demo Accounts Box: One-click credentials for lab evaluation -->
        <div class="demo-accounts-box">
            <h4>Multi-Hostel Lab Demo Credentials</h4>
            
            <div style="margin-bottom: 0.6rem; border-bottom: 1px dashed var(--border-color); padding-bottom: 0.5rem;">
                <span style="font-size: 0.76rem; font-weight: 700; color: var(--secondary); text-transform: uppercase;">
                    🏛️ Dedicated Hostel Wardens (Pass: <code>Warden@123</code>)
                </span>
                <div class="demo-row" style="margin-top: 0.25rem;">
                    <span><strong>Aquamarine:</strong> warden.aquamarine@iitism.ac.in</span>
                    <span class="demo-chip" onclick="fillCredentials('warden.aquamarine@iitism.ac.in', 'Warden@123')">Use Aqua</span>
                </div>
                <div class="demo-row">
                    <span><strong>Amber:</strong> warden.amber@iitism.ac.in</span>
                    <span class="demo-chip" onclick="fillCredentials('warden.amber@iitism.ac.in', 'Warden@123')">Use Amber</span>
                </div>
                <div class="demo-row">
                    <span><strong>Jasper:</strong> warden.jasper@iitism.ac.in</span>
                    <span class="demo-chip" onclick="fillCredentials('warden.jasper@iitism.ac.in', 'Warden@123')">Use Jasper</span>
                </div>
            </div>

            <div>
                <span style="font-size: 0.76rem; font-weight: 700; color: var(--primary); text-transform: uppercase;">
                    🎓 Student Accounts (Pass: <code>Student@123</code>)
                </span>
                <div class="demo-row" style="margin-top: 0.25rem;">
                    <span><strong>Aarav (Aquamarine):</strong> aarav.21je0123@iitism.ac.in</span>
                    <span class="demo-chip" onclick="fillCredentials('aarav.21je0123@iitism.ac.in', 'Student@123')">Use Student</span>
                </div>
                <div class="demo-row">
                    <span><strong>Rohit (Amber):</strong> rohit.20je0789@iitism.ac.in</span>
                    <span class="demo-chip" onclick="fillCredentials('rohit.20je0789@iitism.ac.in', 'Student@123')">Use Rohit</span>
                </div>
            </div>
        </div>

        <?php if (!empty($errorMessage)): ?>
            <div class="alert alert-error">
                <?= htmlspecialchars($errorMessage) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php" id="loginForm">
            <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">

            <div class="form-group">
                <label for="email" class="form-label">Institute Email ID <span class="required">*</span></label>
                <input 
                    type="email" 
                    id="email" 
                    name="email" 
                    class="form-control" 
                    placeholder="e.g. warden.aquamarine@iitism.ac.in" 
                    value="<?= htmlspecialchars($emailValue) ?>" 
                    required 
                    autocomplete="email"
                >
            </div>

            <div class="form-group">
                <label for="password" class="form-label">Password <span class="required">*</span></label>
                <input 
                    type="password" 
                    id="password" 
                    name="password" 
                    class="form-control" 
                    placeholder="Enter password" 
                    required 
                    autocomplete="current-password"
                >
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 0.5rem;">
                Sign In
            </button>
        </form>

        <div class="auth-switch-link">
            Are you a new student? <a href="register.php">Create Student Account</a>
        </div>
    </div>
</div>

<script>
function fillCredentials(email, password) {
    document.getElementById('email').value = email;
    document.getElementById('password').value = password;
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
