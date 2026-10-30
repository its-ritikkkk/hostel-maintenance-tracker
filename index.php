<?php
// ====================================================================
// File: index.php
// Purpose: Redirects root access directly to Student Portal or Warden Portal
// Hostel: Aquamarine Hostel, IIT (ISM) Dhanbad
// ====================================================================

session_start();

if (!empty($_SESSION['warden_logged_in'])) {
    header('Location: warden.php');
} else {
    header('Location: student.php');
}
exit;
