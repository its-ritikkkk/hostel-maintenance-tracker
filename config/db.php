<?php
// ====================================================================
// File: config/db.php
// Purpose: Central database connection file for MariaDB/MySQL
// Project: Hostel Maintenance and Complaint Tracker
// Hostel: Aquamarine Hostel, IIT (ISM) Dhanbad
// ====================================================================

// Prevent direct script execution if needed, or define credentials
$db_host = '127.0.0.1';
$db_port = '3306';
$db_name = 'hostel_complaints';
$db_user = 'root';
$db_pass = ''; // Default XAMPP password is blank

/**
 * Creates and returns a PDO connection to the MariaDB database.
 * We use PDO (PHP Data Objects) because it provides secure, prepared
 * statements that prevent SQL Injection attacks.
 *
 * @return PDO The active database connection object
 */
function getDbConnection() {
    global $db_host, $db_port, $db_name, $db_user, $db_pass;

    $dsn = "mysql:host={$db_host};port={$db_port};dbname={$db_name};charset=utf8mb4";

    $options = [
        // Throw exceptions so errors can be caught gracefully
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        // Return query results as associative arrays (e.g. $row['title'])
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        // Turn off emulated prepared statements for genuine SQL injection protection
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        return new PDO($dsn, $db_user, $db_pass, $options);
    } catch (PDOException $e) {
        // Output a friendly error message suitable for lab evaluation
        http_response_code(500);
        die("<h3>Database Connection Error</h3><p>Could not connect to MariaDB: " . htmlspecialchars($e->getMessage()) . "</p><p>Please ensure Apache and MariaDB/MySQL are running in XAMPP.</p>");
    }
}
?>
