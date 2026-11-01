<?php
// ====================================================================
// File: api/submit_complaint.php
// Purpose: Handles saving a new student maintenance complaint
// Project: Hostel Maintenance and Complaint Tracker
// Hostel: Aquamarine Hostel, IIT (ISM) Dhanbad
// ====================================================================

session_start();
require_once __DIR__ . '/../config/db.php';

// Detect if request expects JSON (e.g. sent via fetch API)
$isJsonRequest = (
    (!empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) ||
    (!empty($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) ||
    isset($_POST['is_ajax'])
);

// Require authenticated student session to submit complaints
if (empty($_SESSION['student_id'])) {
    if ($isJsonRequest) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Unauthorized: Please log in as a student to submit a complaint.'
        ]);
        exit;
    } else {
        $_SESSION['flash_error'] = 'Please log in to submit a complaint.';
        header('Location: ../student.php');
        exit;
    }
}

// If JSON body was sent, parse it
if (!empty($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true) ?? [];
} else {
    $data = $_POST;
}

// Retrieve student identity strictly from authenticated session
$studentId    = (int)$_SESSION['student_id'];
$name         = $_SESSION['student_name'] ?? trim($data['name'] ?? '');
$admission_no = $_SESSION['student_admission_no'] ?? strtoupper(trim($data['admission_no'] ?? ''));
$email        = $_SESSION['student_email'] ?? trim($data['email'] ?? '');

$title        = trim($data['title'] ?? '');
$category     = trim($data['category'] ?? '');
$description  = trim($data['description'] ?? '');

$allowedCategories = ['Electrical', 'Plumbing', 'Network', 'Housekeeping'];

// Server-side validation
$errors = [];

if (empty($title)) {
    $errors[] = 'Complaint title is required.';
}
if (empty($category) || !in_array($category, $allowedCategories, true)) {
    $errors[] = 'Please select a valid complaint category.';
}
if (empty($description)) {
    $errors[] = 'Complaint description is required.';
}

if (!empty($errors)) {
    if ($isJsonRequest) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => implode(' ', $errors)
        ]);
        exit;
    } else {
        $_SESSION['flash_error'] = implode(' ', $errors);
        header('Location: ../student.php');
        exit;
    }
}

try {
    $pdo = getDbConnection();

    // Insert the complaint strictly linked to authenticated student session ID
    $stmtComplaint = $pdo->prepare(
        'INSERT INTO complaints (student_id, title, description, category, status, created_at) 
         VALUES (?, ?, ?, ?, "Submitted", NOW())'
    );
    $stmtComplaint->execute([$studentId, $title, $description, $category]);
    $complaintId = (int)$pdo->lastInsertId();

    if ($isJsonRequest) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success'      => true,
            'message'      => 'Complaint submitted successfully! Initial status: Submitted.',
            'complaint_id' => $complaintId,
            'complaint'    => [
                'id'           => $complaintId,
                'title'        => $title,
                'description'  => $description,
                'category'     => $category,
                'status'       => 'Submitted',
                'student_name' => $name,
                'admission_no' => $admission_no,
                'created_at'   => date('Y-m-d H:i:s')
            ]
        ]);
        exit;
    } else {
        $_SESSION['flash_success'] = "Complaint Submitted Successfully!";
        header('Location: ../student.php');
        exit;
    }

} catch (PDOException $e) {
    if ($isJsonRequest) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ]);
        exit;
    } else {
        $_SESSION['flash_error'] = 'Could not save complaint due to a database error.';
        header('Location: ../student.php');
        exit;
    }
}
