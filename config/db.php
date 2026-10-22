<?php
// ===============================================================
// config/db.php - Database Connection Configuration
// ===============================================================
// This file connects our PHP application to the MariaDB / MySQL database
// using PHP Data Objects (PDO). PDO provides a secure, consistent interface
// with built-in prepared statements to protect against SQL Injection.

function getDbConnection(): PDO
{
    // Database credentials (default for XAMPP is root with empty password)
    $host    = getenv('DB_HOST') ?: '127.0.0.1';
    $port    = getenv('DB_PORT') ?: '3306';
    $db      = getenv('DB_NAME') ?: 'hostel_tracker';
    $user    = getenv('DB_USER') ?: 'root';
    $pass    = getenv('DB_PASS') ?: '';
    $charset = 'utf8mb4';

    // DSN (Data Source Name) string defining driver, host, database name and character set
    $dsn = "mysql:host={$host};port={$port};dbname={$db};charset={$charset}";

    // PDO configuration options:
    // 1. ATTR_ERRMODE => ERRMODE_EXCEPTION: Throws exceptions on errors so we can catch them cleanly.
    // 2. ATTR_DEFAULT_FETCH_MODE => FETCH_ASSOC: Fetches query rows as associative arrays (e.g., $row['title']).
    // 3. ATTR_EMULATE_PREPARES => false: Enforces real prepared statements in MySQL for strict security.
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        return new PDO($dsn, $user, $pass, $options);
    } catch (PDOException $e) {
        // In a college practical or production environment, we do not expose raw passwords
        // or sensitive internal paths to the browser.
        error_log('Database connection error: ' . $e->getMessage());
        die('Database connection failed. Please ensure Apache and MySQL / MariaDB are running in XAMPP.');
    }
}
?>
