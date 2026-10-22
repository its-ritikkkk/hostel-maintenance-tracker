<?php
// ===============================================================
// auth/logout.php - Session Termination
// ===============================================================
// Terminates user session securely and clears all authentication state.

require_once __DIR__ . '/../includes/auth.php';

// Unset all session variables
$_SESSION = [];

// Delete session cookie from browser if set
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

// Destroy session on server
session_destroy();

// Start a fresh transient session to set the logout confirmation message
session_start();
$_SESSION['flash_info'] = 'You have successfully signed out of the IIT (ISM) Dhanbad portal.';

// Redirect to login page
header('Location: login.php');
exit;
?>
