<?php
// ====================================================================
// File: api/get_complaints.php
// Purpose: Retrieves list of complaints with optional filters
// Project: Hostel Maintenance and Complaint Tracker
// Hostel: Aquamarine Hostel, IIT (ISM) Dhanbad
// ====================================================================

session_start();
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = getDbConnection();

    // Read query filter parameters
    $admission_no = isset($_GET['admission_no']) ? strtoupper(trim($_GET['admission_no'])) : '';
    $category     = isset($_GET['category']) ? trim($_GET['category']) : '';
    $status       = isset($_GET['status']) ? trim($_GET['status']) : '';
    $search       = isset($_GET['search']) ? trim($_GET['search']) : '';

    // Base query joining complaints with students table
    $sql = 'SELECT 
                c.id,
                c.student_id,
                c.title,
                c.description,
                c.category,
                c.status,
                c.created_at,
                s.name AS student_name,
                s.admission_no,
                s.email AS student_email
            FROM complaints c
            JOIN students s ON c.student_id = s.id
            WHERE 1=1';

    $params = [];

    // Filter by student's admission number if provided
    if (!empty($admission_no)) {
        $sql .= ' AND s.admission_no = ?';
        $params[] = $admission_no;
    }

    // Filter by category
    if (!empty($category) && $category !== 'All') {
        $sql .= ' AND c.category = ?';
        $params[] = $category;
    }

    // Filter by status
    if (!empty($status) && $status !== 'All') {
        $sql .= ' AND c.status = ?';
        $params[] = $status;
    }

    // Optional text search
    if (!empty($search)) {
        $sql .= ' AND (c.title LIKE ? OR c.description LIKE ? OR s.name LIKE ? OR s.admission_no LIKE ?)';
        $searchTerm = '%' . $search . '%';
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }

    // Sort newest complaints first
    $sql .= ' ORDER BY c.created_at DESC, c.id DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $complaints = $stmt->fetchAll();

    // Format dates nicely for readable display
    $formatted = array_map(function($item) {
        $timestamp = strtotime($item['created_at']);
        $item['formatted_date'] = date('d M Y, h:i A', $timestamp);
        return $item;
    }, $complaints);

    echo json_encode([
        'success'    => true,
        'count'      => count($formatted),
        'complaints' => $formatted
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Could not fetch complaints: ' . $e->getMessage()
    ]);
}
?>
