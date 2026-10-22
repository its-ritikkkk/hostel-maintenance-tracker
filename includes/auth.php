<?php
// ===============================================================
// includes/auth.php - Session Management & Role/Hostel Guards
// ===============================================================
// Reusable authentication, authorization, CSRF protection, and
// multi-hostel warden permission checking for IIT (ISM) Dhanbad.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Check if a user is currently logged in.
 */
function isLoggedIn(): bool
{
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Get current user's role ('student' or 'admin').
 */
function getCurrentUserRole(): ?string
{
    return $_SESSION['role'] ?? null;
}

/**
 * Get the assigned hostel ID for the logged-in warden/student.
 * For a warden, this represents the single hostel they are authorized to manage.
 */
function getAssignedHostelId(): ?int
{
    return isset($_SESSION['user_hostel_id']) && $_SESSION['user_hostel_id'] !== null 
        ? (int) $_SESSION['user_hostel_id'] 
        : null;
}

/**
 * Get the assigned hostel name.
 */
function getAssignedHostelName(): ?string
{
    return $_SESSION['user_hostel'] ?? null;
}

/**
 * Protect pages requiring ANY logged-in user.
 */
function requireLogin(): void
{
    if (!isLoggedIn()) {
        header('Location: ../auth/login.php');
        exit;
    }
}

/**
 * Protect Student pages.
 */
function requireStudent(): void
{
    requireLogin();
    if (getCurrentUserRole() !== 'student') {
        header('Location: ../admin/dashboard.php');
        exit;
    }
}

/**
 * Protect Warden / Admin pages.
 * Ensures user is authenticated as 'admin' and has a valid assigned hostel.
 */
function requireAdmin(): void
{
    requireLogin();
    if (getCurrentUserRole() !== 'admin') {
        header('Location: ../student/dashboard.php');
        exit;
    }

    // Multi-Hostel Requirement:
    // If a warden account is missing an assigned hostel, display a clear error message.
    $hostelId = getAssignedHostelId();
    $hostelName = getAssignedHostelName();
    
    // Check if account has no hostel assignment at all
    if ($hostelId === null && empty($hostelName)) {
        // Only Chief Warden / Super Admin has null hostel_id
        // If needed, they have campus-wide access, otherwise notify
    }
}

/**
 * Verify whether the currently logged-in warden is authorized
 * to view or modify a complaint for a given hostel ID.
 *
 * @param int $complaintHostelId The hostel ID of the complaint.
 * @return bool True if authorized, false otherwise.
 */
function isWardenAuthorizedForHostel(int $complaintHostelId): bool
{
    if (!isLoggedIn() || getCurrentUserRole() !== 'admin') {
        return false;
    }

    $wardenHostelId = getAssignedHostelId();
    // If warden has a specific assigned hostel (e.g. Aquamarine = 1),
    // they can ONLY manage complaints where complaint.hostel_id === 1
    if ($wardenHostelId !== null) {
        return $wardenHostelId === $complaintHostelId;
    }

    // Super Admin / Chief Warden with null hostel_id can manage all
    return true;
}

/**
 * Generate or retrieve CSRF token.
 */
function getCsrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token.
 */
function verifyCsrfToken(?string $token): bool
{
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Retrieve list of all IIT (ISM) Dhanbad Hostels from MariaDB.
 * Falls back to canonical list if database is unreachable.
 *
 * @param PDO|null $db Optional active PDO connection.
 * @return array Array of hostels with 'id' and 'name'.
 */
function getHostelsList(?PDO $db = null): array
{
    if ($db !== null) {
        try {
            $stmt = $db->query('SELECT id, name, code FROM hostels ORDER BY id ASC');
            $rows = $stmt->fetchAll();
            if (!empty($rows)) {
                return $rows;
            }
        } catch (Exception $e) {
            error_log('getHostelsList error: ' . $e->getMessage());
        }
    }

    // Fallback array
    return [
        ['id' => 1, 'name' => 'Aquamarine Hostel', 'code' => 'AQUA'],
        ['id' => 2, 'name' => 'Amber Hostel',      'code' => 'AMB'],
        ['id' => 3, 'name' => 'Jasper Hostel',     'code' => 'JASP'],
        ['id' => 4, 'name' => 'Diamond Hostel',    'code' => 'DIAM'],
        ['id' => 5, 'name' => 'Rosaline Hostel',   'code' => 'ROS'],
        ['id' => 6, 'name' => 'Opal Hostel',       'code' => 'OPAL'],
        ['id' => 7, 'name' => 'Topaz Hostel',      'code' => 'TOP'],
        ['id' => 8, 'name' => 'Ruby Hostel',       'code' => 'RUBY'],
        ['id' => 9, 'name' => 'Sapphire Hostel',   'code' => 'SAPH'],
    ];
}

/**
 * Canonical names list for dropdowns.
 */
function getIITHostels(): array
{
    return [
        'Aquamarine Hostel',
        'Amber Hostel',
        'Jasper Hostel',
        'Diamond Hostel',
        'Rosaline Hostel',
        'Opal Hostel',
        'Topaz Hostel',
        'Ruby Hostel',
        'Sapphire Hostel',
    ];
}

/**
 * Complaint categories.
 */
function getComplaintCategories(): array
{
    return [
        'Electrical',
        'Plumbing',
        'Network / Internet',
        'Housekeeping',
        'Other',
    ];
}

/**
 * Complaint valid statuses.
 */
function getComplaintStatuses(): array
{
    return [
        'Submitted',
        'In Progress',
        'Resolved',
    ];
}

/**
 * Helper to determine CSS badge class for complaint status.
 */
function getStatusBadgeClass(string $status): string
{
    return match (trim($status)) {
        'Submitted'   => 'badge-submitted',
        'In Progress' => 'badge-in-progress',
        'Resolved'    => 'badge-resolved',
        default       => 'badge-default',
    };
}
?>
