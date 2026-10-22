<?php
// ===============================================================
// student/create_complaint.php - Lodge New Maintenance Request
// ===============================================================
// Allows students of IIT (ISM) Dhanbad to report residential issues
// across Electrical, Plumbing, Network / Internet, and Housekeeping categories.
// Submits asynchronously via Fetch API and defaults status to 'Submitted'.

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

requireStudent();

$studentHostel = $_SESSION['user_hostel'] ?? '';
$studentRoom = $_SESSION['user_room'] ?? '';

$hostels = getIITHostels();
$categories = getComplaintCategories();

// Server-side fallback for non-JS standard form submissions
$fallbackError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($csrfToken)) {
        $fallbackError = 'Invalid security token. Please try again.';
    } else {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $hostel = trim($_POST['hostel'] ?? '');
        $room = trim($_POST['room_number'] ?? '');
        $studentId = (int) $_SESSION['user_id'];

        if (empty($title) || empty($description) || empty($category) || empty($hostel) || empty($room)) {
            $fallbackError = 'All required fields marked with * must be filled.';
        } elseif (!in_array($category, $categories, true)) {
            $fallbackError = 'Invalid category selected.';
        } elseif (!in_array($hostel, $hostels, true)) {
            $fallbackError = 'Invalid hostel selected.';
        } else {
            try {
                $db = getDbConnection();

                // Resolve hostel_id
                $hStmt = $db->prepare('SELECT id FROM hostels WHERE name = :name LIMIT 1');
                $hStmt->execute(['name' => $hostel]);
                $hRow = $hStmt->fetch();
                $hostelId = $hRow ? (int) $hRow['id'] : null;

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
                    'room_number' => $room,
                    'status'      => 'Submitted',
                ]);
                $newId = (int) $db->lastInsertId();
                $_SESSION['flash_success'] = 'Complaint #' . $newId . ' lodged successfully with status Submitted!';
                header('Location: my_complaints.php');
                exit;
            } catch (PDOException $e) {
                error_log('Fallback submit error: ' . $e->getMessage());
                $fallbackError = 'Database error while saving complaint.';
            }
        }
    }
}

$pageTitle = 'Lodge Maintenance Complaint';
$pageScript = 'complaints.js';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header" style="max-width: 800px; margin: 0 auto 1.5rem auto;">
    <div class="page-title-group">
        <h2>Lodge Maintenance Complaint</h2>
        <p>IIT (ISM) Dhanbad • Campus Maintenance & Engineering Helpdesk</p>
    </div>
    <div class="page-actions">
        <a href="dashboard.php" class="btn btn-outline btn-sm">&larr; Back to Dashboard</a>
    </div>
</div>

<div class="form-card">
    <div id="submissionFeedback"></div>

    <?php if (!empty($fallbackError)): ?>
        <div class="alert alert-error"><?= htmlspecialchars($fallbackError) ?></div>
    <?php endif; ?>

    <form method="POST" action="create_complaint.php" id="createComplaintForm">
        <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">

        <div class="form-group">
            <label for="title" class="form-label">
                Complaint Title <span class="required">*</span>
            </label>
            <input 
                type="text" 
                id="title" 
                name="title" 
                class="form-control" 
                placeholder="e.g. Fluorescent tube light flickering in study room" 
                maxlength="150" 
                required
            >
            <span class="form-hint">Brief summary of the issue (5 - 150 characters)</span>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="category" class="form-label">
                    Category <span class="required">*</span>
                </label>
                <select id="category" name="category" class="form-control" required>
                    <option value="">-- Select Category --</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= htmlspecialchars($cat) ?>">
                            <?= htmlspecialchars($cat) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <span class="form-hint">Choose service team responsible</span>
            </div>

            <div class="form-group">
                <label for="hostel" class="form-label">
                    Hostel <span class="required">*</span>
                </label>
                <select id="hostel" name="hostel" class="form-control" required>
                    <option value="">-- Select IIT (ISM) Hostel --</option>
                    <?php foreach ($hostels as $h): ?>
                        <option value="<?= htmlspecialchars($h) ?>" <?= $studentHostel === $h ? 'selected' : '' ?>>
                            <?= htmlspecialchars($h) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="room_number" class="form-label">
                    Room Number / Wing <span class="required">*</span>
                </label>
                <input 
                    type="text" 
                    id="room_number" 
                    name="room_number" 
                    class="form-control" 
                    placeholder="e.g. A-304 / 3rd Floor Wing B" 
                    value="<?= htmlspecialchars($studentRoom) ?>" 
                    required
                >
            </div>

            <div class="form-group">
                <label class="form-label">Initial Status</label>
                <div style="padding-top: 0.5rem;">
                    <span class="badge badge-submitted">Submitted</span>
                    <span class="form-hint" style="display:inline; margin-left: 0.5rem;">New tickets automatically receive this status</span>
                </div>
            </div>
        </div>

        <div class="form-group">
            <label for="description" class="form-label">
                Detailed Description <span class="required">*</span>
            </label>
            <textarea 
                id="description" 
                name="description" 
                class="form-control" 
                placeholder="Explain the problem in detail (e.g. exact location in room, when it started, any immediate safety hazard)..." 
                rows="5" 
                required
            ></textarea>
            <span class="form-hint">Provide clear instructions to help technicians diagnose and resolve the issue quickly</span>
        </div>

        <div class="form-footer">
            <a href="dashboard.php" class="btn btn-outline">Cancel</a>
            <button type="submit" class="btn btn-primary" id="submitComplaintBtn">
                Submit Maintenance Complaint
            </button>
        </div>
    </form>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
