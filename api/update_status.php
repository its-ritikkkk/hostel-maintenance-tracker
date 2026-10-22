<?php
// ===============================================================
// api/update_status.php - Asynchronous Warden Status Update API
// ===============================================================
// Allows authenticated wardens to update complaint statuses
// (Submitted -> In Progress -> Resolved) and append remarks.
// Strictly enforces that wardens can ONLY update their assigned hostel.

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

// 1. Authorization Check: Role must be admin/warden
if (!isLoggedIn() || getCurrentUserRole() !== 'admin') {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Forbidden: Only authenticated wardens can update complaint statuses.'
    ]);
    exit;
}

$wardenHostelId = getAssignedHostelId();
$adminUserId = (int) $_SESSION['user_id'];

// Support JSON body and POST
$rawBody = file_get_contents('php://input');
$input = json_decode($rawBody, true);
if (!is_array($input)) {
    $input = $_POST;
}

// 2. CSRF Token Verification (support JSON body, POST form-data, and HTTP headers)
$token = $input['csrf_token'] 
    ?? $_POST['csrf_token'] 
    ?? $_SERVER['HTTP_X_CSRF_TOKEN'] 
    ?? $_SERVER['HTTP_CSRF_TOKEN'] 
    ?? null;

if (!verifyCsrfToken($token)) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Security token invalid or expired. Please refresh the page.'
    ]);
    exit;
}

// Release session lock so other requests proceed without blocking
session_write_close();

// 3. Validate Inputs
$complaintId = isset($input['complaint_id']) ? (int) $input['complaint_id'] : 0;
$newStatus = trim($input['status'] ?? '');
$remarks = trim($input['remarks'] ?? '');

$validStatuses = getComplaintStatuses();

if ($complaintId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid complaint ID.']);
    exit;
}

if (!in_array($newStatus, $validStatuses, true)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid status. Permitted statuses are: ' . implode(', ', $validStatuses)
    ]);
    exit;
}

try {
    $db = getDbConnection();

    // 4. Fetch complaint to verify existence and hostel ownership
    $fetchStmt = $db->prepare('SELECT id, status, hostel_id, hostel FROM complaints WHERE id = :id LIMIT 1');
    $fetchStmt->execute(['id' => $complaintId]);
    $complaint = $fetchStmt->fetch();

    if (!$complaint) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Complaint not found.']);
        exit;
    }

    // 5. SERVER-SIDE WARDEN HOSTEL VERIFICATION (MANDATORY REQUIREMENT)
    // Even if an attacker or warden changes the complaint ID in the request,
    // this check guarantees they cannot alter tickets from another hostel.
    if ($wardenHostelId !== null && (int) $complaint['hostel_id'] !== $wardenHostelId) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Forbidden: You do not have permission to modify complaints for ' . $complaint['hostel'] . '.'
        ]);
        exit;
    }

    $oldStatus = $complaint['status'];

    // 6. Update complaint status
    $updateStmt = $db->prepare(
        'UPDATE complaints 
         SET status = :status, 
             admin_remarks = CASE WHEN :remarks != "" THEN :remarks_val ELSE admin_remarks END,
             updated_at = CURRENT_TIMESTAMP
         WHERE id = :id'
    );
    $updateStmt->execute([
        'status'      => $newStatus,
        'remarks'     => $remarks,
        'remarks_val' => $remarks,
        'id'          => $complaintId,
    ]);

    // 7. Insert audit record in complaint_updates
    $logStmt = $db->prepare(
        'INSERT INTO complaint_updates (complaint_id, updated_by, old_status, new_status, remarks)
         VALUES (:complaint_id, :updated_by, :old_status, :new_status, :remarks)'
    );
    $logStmt->execute([
        'complaint_id' => $complaintId,
        'updated_by'   => $adminUserId,
        'old_status'   => $oldStatus,
        'new_status'   => $newStatus,
        'remarks'      => !empty($remarks) ? $remarks : ('Status changed to ' . $newStatus),
    ]);

    echo json_encode([
        'success'      => true,
        'message'      => 'Complaint #' . $complaintId . ' updated to ' . $newStatus . ' successfully.',
        'complaint_id' => $complaintId,
        'old_status'   => $oldStatus,
        'status'       => $newStatus,
        'badge_class'  => getStatusBadgeClass($newStatus),
        'updated_at'   => date('Y-m-d H:i:s'),
    ]);
} catch (PDOException $e) {
    error_log('API update_status error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error while updating complaint status.'
    ]);
}
?>
