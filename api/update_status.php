<?php
// ====================================================================
// File: api/update_status.php
// Purpose: Allows Warden to update the status of a hostel complaint
// Project: Hostel Maintenance and Complaint Tracker
// Hostel: Aquamarine Hostel, IIT (ISM) Dhanbad
// ====================================================================

session_start();
require_once __DIR__ . '/../config/db.php';

// Detect if request expects JSON
$isJsonRequest = (
    (!empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) ||
    (!empty($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) ||
    isset($_POST['is_ajax'])
);

// Read JSON input if sent via fetch(..., { headers: {'Content-Type': 'application/json'} })
if (!empty($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true) ?? [];
} else {
    $data = $_POST;
}

$complaintId = isset($data['complaint_id']) ? (int)$data['complaint_id'] : 0;
$newStatus   = trim($data['status'] ?? '');

$allowedStatuses = ['Submitted', 'In Progress', 'Resolved'];

// Validation
if ($complaintId <= 0 || !in_array($newStatus, $allowedStatuses, true)) {
    if ($isJsonRequest) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid complaint ID or status value. Allowed: Submitted, In Progress, Resolved.'
        ]);
        exit;
    } else {
        $_SESSION['flash_error'] = 'Invalid status update request.';
        header('Location: ../warden.php');
        exit;
    }
}

try {
    $pdo = getDbConnection();

    // Verify complaint exists
    $checkStmt = $pdo->prepare('SELECT id, status FROM complaints WHERE id = ?');
    $checkStmt->execute([$complaintId]);
    $complaint = $checkStmt->fetch();

    if (!$complaint) {
        if ($isJsonRequest) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Complaint not found.']);
            exit;
        } else {
            $_SESSION['flash_error'] = 'Complaint not found.';
            header('Location: ../warden.php');
            exit;
        }
    }

    // Update status in MariaDB
    $updateStmt = $pdo->prepare('UPDATE complaints SET status = ? WHERE id = ?');
    $updateStmt->execute([$newStatus, $complaintId]);

    if ($isJsonRequest) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success'      => true,
            'message'      => "Complaint #{$complaintId} status updated to '{$newStatus}'.",
            'complaint_id' => $complaintId,
            'status'       => $newStatus
        ]);
        exit;
    } else {
        $_SESSION['flash_success'] = "Complaint #{$complaintId} status updated to '{$newStatus}'.";
        header('Location: ../warden.php');
        exit;
    }

} catch (PDOException $e) {
    if ($isJsonRequest) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Database error updating status: ' . $e->getMessage()
        ]);
        exit;
    } else {
        $_SESSION['flash_error'] = 'Database error updating status.';
        header('Location: ../warden.php');
        exit;
    }
}
?>
