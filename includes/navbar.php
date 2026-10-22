<?php
// ===============================================================
// includes/navbar.php - Multi-Hostel Aware Navigation Bar
// ===============================================================
// Renders responsive navigation with role badges and assigned hostel labels.

$currentRole = getCurrentUserRole();
$isLoggedIn = isLoggedIn();
$userName = $_SESSION['user_name'] ?? 'User';
$userHostel = $_SESSION['user_hostel'] ?? '';
$userRoom = $_SESSION['user_room'] ?? '';
$currentScript = basename($_SERVER['SCRIPT_NAME']);
?>
<header class="site-header">
    <div class="nav-container">
        <!-- Brand & College Identity -->
        <a href="<?= BASE_URL ?>/index.php" class="brand-wrapper">
            <span class="brand-logo-badge">IIT ISM</span>
            <div class="brand-title-group">
                <h1>Hostel Maintenance Tracker</h1>
                <p>IIT (ISM) Dhanbad • Campus Portal</p>
            </div>
        </a>

        <!-- Navigation Links -->
        <nav>
            <ul class="nav-links">
                <?php if (!$isLoggedIn): ?>
                    <!-- Guest Navigation -->
                    <li><a href="<?= BASE_URL ?>/index.php" class="<?= $currentScript === 'index.php' ? 'active' : '' ?>">Home</a></li>
                    <li><a href="<?= BASE_URL ?>/auth/login.php" class="<?= $currentScript === 'login.php' ? 'active' : '' ?>">Login</a></li>
                    <li><a href="<?= BASE_URL ?>/auth/register.php" class="<?= $currentScript === 'register.php' ? 'active' : '' ?>">Student Register</a></li>
                <?php elseif ($currentRole === 'student'): ?>
                    <!-- Student Navigation -->
                    <li><a href="<?= BASE_URL ?>/student/dashboard.php" class="<?= $currentScript === 'dashboard.php' ? 'active' : '' ?>">Dashboard</a></li>
                    <li><a href="<?= BASE_URL ?>/student/create_complaint.php" class="<?= $currentScript === 'create_complaint.php' ? 'active' : '' ?>">+ New Complaint</a></li>
                    <li><a href="<?= BASE_URL ?>/student/my_complaints.php" class="<?= $currentScript === 'my_complaints.php' ? 'active' : '' ?>">My Complaints</a></li>
                    <li>
                        <span class="user-badge">
                            <strong><?= htmlspecialchars($userName) ?></strong>
                            <?php if ($userHostel): ?>
                                <span>(<?= htmlspecialchars($userHostel) ?><?= $userRoom ? ' - ' . htmlspecialchars($userRoom) : '' ?>)</span>
                            <?php endif; ?>
                            <span class="user-role-tag">Student</span>
                        </span>
                    </li>
                    <li><a href="<?= BASE_URL ?>/auth/logout.php" class="btn-nav-logout">Logout</a></li>
                <?php elseif ($currentRole === 'admin'): ?>
                    <!-- Warden / Administrator Navigation -->
                    <li><a href="<?= BASE_URL ?>/admin/dashboard.php" class="<?= $currentScript === 'dashboard.php' ? 'active' : '' ?>">Warden Dashboard</a></li>
                    <li><a href="<?= BASE_URL ?>/admin/manage_complaints.php" class="<?= $currentScript === 'manage_complaints.php' ? 'active' : '' ?>">Hostel Complaints</a></li>
                    <li>
                        <span class="user-badge">
                            <strong><?= htmlspecialchars($userName) ?></strong>
                            <?php if ($userHostel): ?>
                                <span style="color: #fde68a; font-weight: 600;">(<?= htmlspecialchars($userHostel) ?>)</span>
                            <?php endif; ?>
                            <span class="user-role-tag">Warden</span>
                        </span>
                    </li>
                    <li><a href="<?= BASE_URL ?>/auth/logout.php" class="btn-nav-logout">Logout</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
</header>
