<?php
// ===============================================================
// api/submit_complaint.php - Asynchronous Complaint Submission API
// ===============================================================
// Receives complaint data from Fetch API, validates inputs, associates
// the complaint with the selected hostel ID, and inserts with status 'Submitted'.

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

// Only authenticated students can submit complaints
if (!isLoggedIn() || getCurrentUserRole() !== 'student') {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized: Only logged-in students can lodge complaints.'
    ]);
    exit;
}

$rawBody = file_get_contents('php://input');
$input = json_decode($rawBody, true);
if (!is_array($input)) {
    $input = $_POST;
}

// 1. Verify CSRF Token (support JSON body, POST form-data, and HTTP headers)
$token = $input['csrf_token'] 
    ?? $_POST['csrf_token'] 
    ?? $_SERVER['HTTP_X_CSRF_TOKEN'] 
    ?? $_SERVER['HTTP_CSRF_TOKEN'] 
    ?? null;

if (!verifyCsrfToken($token)) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid or expired security token. Please refresh the page.'
    ]);
    exit;
}

// 2. Retrieve inputs and release session lock
$studentId = (int) $_SESSION['user_id'];
session_write_close();
$title = trim($input['title'] ?? '');
$description = trim($input['description'] ?? '');
$category = trim($input['category'] ?? '');
$hostel = trim($input['hostel'] ?? '');
$roomNumber = trim($input['room_number'] ?? '');

$validCategories = getComplaintCategories();
$validHostels = getIITHostels();

// 3. Validation checks
if (empty($title) || empty($description) || empty($category) || empty($hostel) || empty($roomNumber)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'All fields (title, description, category, hostel, room number) are required.'
    ]);
    exit;
}

if (strlen($title) < 5 || strlen($title) > 150) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Complaint title must be between 5 and 150 characters.'
    ]);
    exit;
}

if (strlen($description) < 10) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Please provide a more detailed description (at least 10 characters).'
    ]);
    exit;
}

if (!in_array($category, $validCategories, true)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Please select a valid complaint category.'
    ]);
    exit;
}

if (!in_array($hostel, $validHostels, true)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Please select a valid IIT (ISM) Dhanbad hostel.'
    ]);
    exit;
}

try {
    $db = getDbConnection();

    // 4. Resolve the relational hostel_id
    $hStmt = $db->prepare('SELECT id FROM hostels WHERE name = :name LIMIT 1');
    $hStmt->execute(['name' => $hostel]);
    $hostelRow = $hStmt->fetch();
    $hostelId = $hostelRow ? (int) $hostelRow['id'] : null;

    // 5. Insert complaint with guaranteed initial status 'Submitted' and associated hostel_id
    $stmt = $db->prepare(
        'INSERT INTO complaints (student_id, title, description, category, hostel_id, hostel, room_number, status)
         VALUES (:student_id, :title, :description, :category, :hostel_id, :hostel, :room_number, :status)'
    );

    $stmt->execute([
        'student_id'  => $studentId,
        'title'       => $title,
        'description' => $description,
        'category'    => $category,
        'hostel_id'   => $hostelId,
        'hostel'      => $hostel,
        'room_number' => $roomNumber,
        'status'      => 'Submitted',
    ]);

    $newId = (int) $db->lastInsertId();

    $fetchStmt = $db->prepare('SELECT * FROM complaints WHERE id = :id');
    $fetchStmt->execute(['id' => $newId]);
    $newComplaint = $fetchStmt->fetch();

    http_response_code(201);
    echo json_encode([
        'success'      => true,
        'message'      => 'Complaint #' . $newId . ' submitted successfully to ' . $hostel . ' maintenance!',
        'complaint_id' => $newId,
        'complaint'    => $newComplaint
    ]);
} catch (PDOException $e) {
    error_log('API submit_complaint error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error while saving your complaint. Please try again.'
    ]);
}
?>
