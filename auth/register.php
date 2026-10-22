<?php
// ===============================================================
// auth/register.php - Student Registration with Hostel Mapping
// ===============================================================
// Registers student accounts for IIT (ISM) Dhanbad and maps them
// to their respective resident hostel.

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

if (isLoggedIn()) {
    header('Location: ../student/dashboard.php');
    exit;
}

$errorMessage = '';
$nameValue = '';
$emailValue = '';
$hostelValue = '';
$roomValue = '';
$phoneValue = '';

$hostels = getIITHostels();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($csrfToken)) {
        $errorMessage = 'Security validation failed. Please try again.';
    } else {
        $nameValue = trim($_POST['name'] ?? '');
        $emailValue = trim($_POST['email'] ?? '');
        $hostelValue = trim($_POST['hostel'] ?? '');
        $roomValue = trim($_POST['room_number'] ?? '');
        $phoneValue = trim($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (empty($nameValue) || empty($emailValue) || empty($hostelValue) || empty($roomValue) || empty($password)) {
            $errorMessage = 'Please fill in all required fields marked with an asterisk (*).';
        } elseif (!filter_var($emailValue, FILTER_VALIDATE_EMAIL)) {
            $errorMessage = 'Please enter a valid institute email address.';
        } elseif (!in_array($hostelValue, $hostels, true)) {
            $errorMessage = 'Please select a valid hostel from the list.';
        } elseif (strlen($password) < 6) {
            $errorMessage = 'Password must be at least 6 characters long.';
        } elseif ($password !== $confirmPassword) {
            $errorMessage = 'Passwords do not match. Please verify and retype.';
        } else {
            try {
                $db = getDbConnection();

                // Check if email already registered
                $checkStmt = $db->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
                $checkStmt->execute(['email' => $emailValue]);
                if ($checkStmt->fetch()) {
                    $errorMessage = 'An account with this email address already exists. Please sign in.';
                } else {
                    // Resolve hostel_id from hostels table
                    $hStmt = $db->prepare('SELECT id FROM hostels WHERE name = :name LIMIT 1');
                    $hStmt->execute(['name' => $hostelValue]);
                    $hostelRow = $hStmt->fetch();
                    $hostelId = $hostelRow ? (int) $hostelRow['id'] : null;

                    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

                    $insertStmt = $db->prepare(
                        'INSERT INTO users (name, email, password_hash, role, hostel_id, hostel, room_number, phone) 
                         VALUES (:name, :email, :password_hash, :role, :hostel_id, :hostel, :room_number, :phone)'
                    );

                    $insertStmt->execute([
                        'name'          => $nameValue,
                        'email'         => $emailValue,
                        'password_hash' => $passwordHash,
                        'role'          => 'student',
                        'hostel_id'     => $hostelId,
                        'hostel'        => $hostelValue,
                        'room_number'   => $roomValue,
                        'phone'         => $phoneValue,
                    ]);

                    $newUserId = (int) $db->lastInsertId();

                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $newUserId;
                    $_SESSION['user_name'] = $nameValue;
                    $_SESSION['user_email'] = $emailValue;
                    $_SESSION['role'] = 'student';
                    $_SESSION['user_hostel_id'] = $hostelId;
                    $_SESSION['user_hostel'] = $hostelValue;
                    $_SESSION['user_room'] = $roomValue;

                    $_SESSION['flash_success'] = 'Registration successful! Welcome to the IIT (ISM) Dhanbad Hostel Tracker.';
                    header('Location: ../student/dashboard.php');
                    exit;
                }
            } catch (PDOException $e) {
                error_log('Registration error: ' . $e->getMessage());
                $errorMessage = 'A database error occurred during registration. Please try again.';
            }
        }
    }
}

$pageTitle = 'Student Registration';
include __DIR__ . '/../includes/header.php';
?>

<div class="auth-wrapper">
    <div class="auth-card" style="max-width: 580px;">
        <div class="auth-header">
            <span class="auth-badge">IIT (ISM) Dhanbad</span>
            <h2>Student Registration</h2>
            <p>Create your student account to submit maintenance requests</p>
        </div>

        <?php if (!empty($errorMessage)): ?>
            <div class="alert alert-error">
                <?= htmlspecialchars($errorMessage) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="register.php" id="registerForm">
            <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">

            <div class="form-group">
                <label for="name" class="form-label">Full Name <span class="required">*</span></label>
                <input 
                    type="text" 
                    id="name" 
                    name="name" 
                    class="form-control" 
                    placeholder="e.g. Vikramaditya Singh" 
                    value="<?= htmlspecialchars($nameValue) ?>" 
                    required
                >
            </div>

            <div class="form-group">
                <label for="email" class="form-label">Institute Email ID <span class="required">*</span></label>
                <input 
                    type="email" 
                    id="email" 
                    name="email" 
                    class="form-control" 
                    placeholder="e.g. vikram.23je0999@iitism.ac.in" 
                    value="<?= htmlspecialchars($emailValue) ?>" 
                    required
                >
                <span class="form-hint">Use your official IIT (ISM) email address</span>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="hostel" class="form-label">Hostel <span class="required">*</span></label>
                    <select id="hostel" name="hostel" class="form-control" required>
                        <option value="">-- Select Hostel --</option>
                        <?php foreach ($hostels as $h): ?>
                            <option value="<?= htmlspecialchars($h) ?>" <?= $hostelValue === $h ? 'selected' : '' ?>>
                                <?= htmlspecialchars($h) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="room_number" class="form-label">Room Number <span class="required">*</span></label>
                    <input 
                        type="text" 
                        id="room_number" 
                        name="room_number" 
                        class="form-control" 
                        placeholder="e.g. A-304 / 215" 
                        value="<?= htmlspecialchars($roomValue) ?>" 
                        required
                    >
                </div>
            </div>

            <div class="form-group">
                <label for="phone" class="form-label">Phone / WhatsApp Number</label>
                <input 
                    type="tel" 
                    id="phone" 
                    name="phone" 
                    class="form-control" 
                    placeholder="e.g. 9876543210" 
                    value="<?= htmlspecialchars($phoneValue) ?>" 
                >
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="password" class="form-label">Password <span class="required">*</span></label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        class="form-control" 
                        placeholder="Min. 6 characters" 
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="confirm_password" class="form-label">Confirm Password <span class="required">*</span></label>
                    <input 
                        type="password" 
                        id="confirm_password" 
                        name="confirm_password" 
                        class="form-control" 
                        placeholder="Re-enter password" 
                        required
                    >
                </div>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 0.5rem;">
                Create Student Account
            </button>
        </form>

        <div class="auth-switch-link">
            Already have an account? <a href="login.php">Sign in here</a>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
