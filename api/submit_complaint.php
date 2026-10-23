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

// If JSON body was sent, parse it
if (!empty($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true) ?? [];
} else {
    $data = $_POST;
}

// Retrieve and sanitize inputs
$name         = trim($data['name'] ?? '');
$admission_no = strtoupper(trim($data['admission_no'] ?? ''));
$email        = trim($data['email'] ?? '');
$title        = trim($data['title'] ?? '');
$category     = trim($data['category'] ?? '');
$description  = trim($data['description'] ?? '');

$allowedCategories = ['Electrical', 'Plumbing', 'Network', 'Housekeeping'];

// Server-side validation
$errors = [];

if (empty($name)) {
    $errors[] = 'Student name is required.';
}
if (empty($admission_no)) {
    $errors[] = 'Admission number is required.';
}
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'A valid email address is required.';
}
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

    // Step 1: Look up existing student by Admission Number
    $stmtStudent = $pdo->prepare('SELECT id, name, email FROM students WHERE admission_no = ?');
    $stmtStudent->execute([$admission_no]);
    $student = $stmtStudent->fetch();

    if ($student) {
        $studentId = (int)$student['id'];
        // Keep student record up to date with any newly entered name/email
        $stmtUpdateStudent = $pdo->prepare('UPDATE students SET name = ?, email = ? WHERE id = ?');
        $stmtUpdateStudent->execute([$name, $email, $studentId]);
    } else {
        // Register new student in minimal students table
        $stmtInsertStudent = $pdo->prepare('INSERT INTO students (name, admission_no, email) VALUES (?, ?, ?)');
        $stmtInsertStudent->execute([$name, $admission_no, $email]);
        $studentId = (int)$pdo->lastInsertId();
    }

    // Step 2: Insert the complaint with initial status 'Submitted'
    $stmtComplaint = $pdo->prepare(
        'INSERT INTO complaints (student_id, title, description, category, status, created_at) 
         VALUES (?, ?, ?, ?, "Submitted", NOW())'
    );
    $stmtComplaint->execute([$studentId, $title, $description, $category]);
    $complaintId = (int)$pdo->lastInsertId();

    // Store student identity in session so their dashboard stays in context
    $_SESSION['student_admission_no'] = $admission_no;
    $_SESSION['student_name'] = $name;
    $_SESSION['student_email'] = $email;
    $_SESSION['student_id'] = $studentId;

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
        $_SESSION['flash_success'] = "Complaint #{$complaintId} submitted successfully! Status: Submitted.";
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
?>
