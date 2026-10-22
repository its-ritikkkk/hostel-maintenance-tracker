<?php
// ===============================================================
// api/get_status_updates.php - Polling Endpoint for Status Changes
// ===============================================================
// Returns latest status for complaints in the user's dashboard view.
// Enforces student ownership and warden hostel isolation.

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$role = getCurrentUserRole();
$userId = (int) $_SESSION['user_id'];
$wardenHostelId = getAssignedHostelId();

// Release session lock immediately to avoid blocking concurrent requests
session_write_close();

$idsParam = trim($_GET['ids'] ?? '');

try {
    $db = getDbConnection();

    $sql = 'SELECT id, student_id, hostel_id, status, admin_remarks, updated_at FROM complaints WHERE 1=1';
    $params = [];

    // Security check 1: Students can only observe their own complaints
    if ($role === 'student') {
        $sql .= ' AND student_id = :student_id';
        $params['student_id'] = $userId;
    }

    // Security check 2: Wardens can only observe complaints for their assigned hostel
    if ($role === 'admin' && $wardenHostelId !== null) {
        $sql .= ' AND hostel_id = :warden_hostel_id';
        $params['warden_hostel_id'] = $wardenHostelId;
    }

    // Filter specific IDs if requested by client
    if (!empty($idsParam)) {
        $idList = array_filter(array_map('intval', explode(',', $idsParam)));
        if (!empty($idList)) {
            $placeholders = [];
            foreach ($idList as $idx => $id) {
                $ph = ':id_' . $idx;
                $placeholders[] = $ph;
                $params['id_' . $idx] = $id;
            }
            $sql .= ' AND id IN (' . implode(',', $placeholders) . ')';
        }
    }

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $statusMap = [];
    foreach ($rows as $row) {
        $statusMap[$row['id']] = [
            'id'            => (int) $row['id'],
            'status'        => $row['status'],
            'badge_class'   => getStatusBadgeClass($row['status']),
            'admin_remarks' => $row['admin_remarks'],
            'updated_at'    => $row['updated_at'],
        ];
    }

    echo json_encode([
        'success'   => true,
        'timestamp' => time(),
        'statuses'  => $statusMap
    ]);
} catch (PDOException $e) {
    error_log('API get_status_updates error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to check status updates.']);
}
?>
