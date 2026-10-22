<?php
// ===============================================================
// api/get_complaint.php - Single Complaint Details API
// ===============================================================
// Fetches full details and status update timeline for a single complaint.
// Enforces student ownership and multi-hostel warden isolation.

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$complaintId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($complaintId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid complaint ID provided.']);
    exit;
}

$role = getCurrentUserRole();
$userId = (int) $_SESSION['user_id'];
$wardenHostelId = getAssignedHostelId();

try {
    $db = getDbConnection();

    $stmt = $db->prepare(
        'SELECT 
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
        WHERE c.id = :id
        LIMIT 1'
    );
    $stmt->execute(['id' => $complaintId]);
    $complaint = $stmt->fetch();

    if (!$complaint) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Complaint not found.']);
        exit;
    }

    // Authorization Rule 1: Students can only view their own complaints
    if ($role === 'student' && (int) $complaint['student_id'] !== $userId) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Forbidden: You do not have permission to view this complaint.']);
        exit;
    }

    // Authorization Rule 2: Wardens can only view complaints for their assigned hostel
    if ($role === 'admin' && $wardenHostelId !== null) {
        if ((int) $complaint['hostel_id'] !== $wardenHostelId) {
            http_response_code(403);
            echo json_encode([
                'success' => false, 
                'message' => 'Forbidden: You are only authorized to manage complaints for your assigned hostel.'
            ]);
            exit;
        }
    }

    // Fetch timeline updates
    $updatesStmt = $db->prepare(
        'SELECT 
            cu.id,
            cu.old_status,
            cu.new_status,
            cu.remarks,
            cu.created_at,
            u.name AS updated_by_name
        FROM complaint_updates cu
        JOIN users u ON cu.updated_by = u.id
        WHERE cu.complaint_id = :complaint_id
        ORDER BY cu.created_at ASC'
    );
    $updatesStmt->execute(['complaint_id' => $complaintId]);
    $updates = $updatesStmt->fetchAll();

    echo json_encode([
        'success'   => true,
        'complaint' => $complaint,
        'updates'   => $updates
    ]);
} catch (PDOException $e) {
    error_log('API get_complaint error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error while fetching complaint details.']);
}
?>
