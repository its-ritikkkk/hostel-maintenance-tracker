<?php
// ===============================================================
// api/get_complaints.php - Asynchronous Complaint Fetching API
// ===============================================================
// Returns complaints filtered by ownership for students, or by
// assigned hostel for wardens. Enforces multi-hostel isolation.

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

// Verify authentication
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized. Please log in to view complaints.'
    ]);
    exit;
}

$role = getCurrentUserRole();
$userId = (int) $_SESSION['user_id'];
$wardenHostelId = getAssignedHostelId();

// Release session lock immediately to avoid blocking concurrent requests
session_write_close();

// Optional filter query parameters from GET request
$statusFilter = trim($_GET['status'] ?? '');
$categoryFilter = trim($_GET['category'] ?? '');
$hostelFilter = trim($_GET['hostel'] ?? '');
$searchFilter = trim($_GET['search'] ?? '');

try {
    $db = getDbConnection();

    $sql = 'SELECT 
                c.id,
                c.student_id,
                u.name AS student_name,
                u.email AS student_email,
                u.phone AS student_phone,
                c.title,
                c.description,
                c.category,
                c.hostel_id,
                c.hostel,
                c.room_number,
                c.status,
                c.admin_remarks,
                c.created_at,
                c.updated_at
            FROM complaints c
            JOIN users u ON c.student_id = u.id
            WHERE 1=1';

    $params = [];

    // Security Rule 1: Students can ONLY view their own complaints
    if ($role === 'student') {
        $sql .= ' AND c.student_id = :student_id';
        $params['student_id'] = $userId;
    }

    // Security Rule 2: Multi-Hostel Warden Isolation
    // Each warden can ONLY view complaints belonging to their assigned hostel.
    // Server enforces this via session; any frontend parameter tampering is ignored.
    if ($role === 'admin') {
        if ($wardenHostelId !== null) {
            $sql .= ' AND c.hostel_id = :warden_hostel_id';
            $params['warden_hostel_id'] = $wardenHostelId;
        } elseif (!empty($hostelFilter)) {
            // Only Super Admin with null hostel_id can filter by arbitrary hostel
            $sql .= ' AND c.hostel = :hostel';
            $params['hostel'] = $hostelFilter;
        }
    }

    // Filter by status if provided
    if (!empty($statusFilter) && in_array($statusFilter, getComplaintStatuses(), true)) {
        $sql .= ' AND c.status = :status';
        $params['status'] = $statusFilter;
    }

    // Filter by category if provided
    if (!empty($categoryFilter) && in_array($categoryFilter, getComplaintCategories(), true)) {
        $sql .= ' AND c.category = :category';
        $params['category'] = $categoryFilter;
    }

    // Search query in title or description
    if (!empty($searchFilter)) {
        $sql .= ' AND (c.title LIKE :search OR c.description LIKE :search OR u.name LIKE :search)';
        $params['search'] = '%' . $searchFilter . '%';
    }

    $sql .= ' ORDER BY c.created_at DESC';

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $complaints = $stmt->fetchAll();

    echo json_encode([
        'success'    => true,
        'count'      => count($complaints),
        'complaints' => $complaints
    ]);
} catch (PDOException $e) {
    error_log('API get_complaints error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to load complaints from the database.'
    ]);
}
?>
