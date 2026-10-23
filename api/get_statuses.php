<?php
// ====================================================================
// File: api/get_statuses.php
// Purpose: Returns latest complaint statuses as lightweight JSON for
//          asynchronous periodic refresh without full page reloads.
// Project: Hostel Maintenance and Complaint Tracker
// Hostel: Aquamarine Hostel, IIT (ISM) Dhanbad
// ====================================================================

session_start();
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = getDbConnection();

    $admission_no = isset($_GET['admission_no']) ? strtoupper(trim($_GET['admission_no'])) : '';

    // If an admission number is supplied, return statuses for that student
    // Otherwise return statuses for all complaints (used by Warden dashboard)
    if (!empty($admission_no)) {
        $stmt = $pdo->prepare(
            'SELECT c.id, c.status 
             FROM complaints c 
             JOIN students s ON c.student_id = s.id 
             WHERE s.admission_no = ?'
        );
        $stmt->execute([$admission_no]);
    } else {
        $stmt = $pdo->query('SELECT id, status FROM complaints');
    }

    $statuses = $stmt->fetchAll();

    echo json_encode([
        'success'   => true,
        'timestamp' => date('H:i:s'),
        'count'     => count($statuses),
        'statuses'  => $statuses
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error retrieving statuses: ' . $e->getMessage()
    ]);
}
?>
