<?php
// ===============================================================
// includes/header.php - Global Page Header Component
// ===============================================================
// Sets up HTML5 head, loads CSS stylesheets, manages BASE_URL,
// and displays session flash messages.

require_once __DIR__ . '/auth.php';

// Detect and define BASE_URL dynamically for robust routing
if (!defined('BASE_URL')) {
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    if (strpos($scriptName, '/tracker') !== false) {
        define('BASE_URL', '/tracker');
    } else {
        define('BASE_URL', '');
    }
}

// Default page title if not set by individual page
$titleText = isset($pageTitle) 
    ? htmlspecialchars($pageTitle) . ' | IIT (ISM) Dhanbad Hostel Tracker'
    : 'IIT (ISM) Dhanbad - Hostel Maintenance & Complaint Tracker';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Hostel Maintenance and Complaint Redressal Tracker for Indian Institute of Technology (Indian School of Mines), Dhanbad.">
    <title><?= $titleText ?></title>

    <!-- Global Stylesheets -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=<?= time() ?>">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/dashboard.css?v=<?= time() ?>">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/forms.css?v=<?= time() ?>">

    <!-- Pass BASE_URL to JavaScript for dynamic Fetch API calls -->
    <script>
        window.APP_CONFIG = {
            baseUrl: "<?= BASE_URL ?>",
            csrfToken: "<?= getCsrfToken() ?>",
            userRole: "<?= getCurrentUserRole() ?? 'guest' ?>"
        };
    </script>
</head>
<body>
    <!-- Top Navigation -->
    <?php include __DIR__ . '/navbar.php'; ?>

    <!-- Main Content Container -->
    <main class="main-wrapper">
        <!-- Session Flash Messages -->
        <?php if (!empty($_SESSION['flash_success'])): ?>
            <div class="alert alert-success" id="flashMessage">
                <span><?= htmlspecialchars($_SESSION['flash_success']) ?></span>
                <button type="button" class="btn btn-sm btn-outline" onclick="this.parentElement.remove()" style="padding: 0 0.4rem;">&times;</button>
            </div>
            <?php unset($_SESSION['flash_success']); ?>
        <?php endif; ?>

        <?php if (!empty($_SESSION['flash_error'])): ?>
            <div class="alert alert-error" id="flashMessage">
                <span><?= htmlspecialchars($_SESSION['flash_error']) ?></span>
                <button type="button" class="btn btn-sm btn-outline" onclick="this.parentElement.remove()" style="padding: 0 0.4rem;">&times;</button>
            </div>
            <?php unset($_SESSION['flash_error']); ?>
        <?php endif; ?>

        <?php if (!empty($_SESSION['flash_info'])): ?>
            <div class="alert alert-info" id="flashMessage">
                <span><?= htmlspecialchars($_SESSION['flash_info']) ?></span>
                <button type="button" class="btn btn-sm btn-outline" onclick="this.parentElement.remove()" style="padding: 0 0.4rem;">&times;</button>
            </div>
            <?php unset($_SESSION['flash_info']); ?>
        <?php endif; ?>
