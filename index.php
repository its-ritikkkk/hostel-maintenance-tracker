<?php
// ===============================================================
// index.php - Home / Landing Page
// ===============================================================
// Welcome portal for students and hostel wardens of IIT (ISM) Dhanbad.
// Features multi-hostel management across Aquamarine, Amber, Jasper, etc.

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';

$totalComplaints = 0;
$resolvedComplaints = 0;
$activeComplaints = 0;

try {
    $db = getDbConnection();
    $totalComplaints = (int) $db->query('SELECT COUNT(*) FROM complaints')->fetchColumn();
    $resolvedComplaints = (int) $db->query("SELECT COUNT(*) FROM complaints WHERE status = 'Resolved'")->fetchColumn();
    $activeComplaints = (int) $db->query("SELECT COUNT(*) FROM complaints WHERE status IN ('Submitted', 'In Progress')")->fetchColumn();
} catch (Exception $e) {
    error_log('Landing stats error: ' . $e->getMessage());
}

$pageTitle = 'Home';
include __DIR__ . '/includes/header.php';
?>

<div style="max-width: 1000px; margin: 0 auto;">
    <!-- Hero Banner -->
    <div style="background: linear-gradient(135deg, #0f2b5c 0%, #1e3a8a 100%); color: #ffffff; padding: 3rem 2rem; border-radius: var(--radius-lg); box-shadow: var(--shadow-md); margin-bottom: 2.5rem; text-align: center;">
        <span style="background: rgba(217, 119, 6, 0.25); color: #fbbf24; border: 1px solid rgba(251, 191, 36, 0.4); font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; padding: 0.35rem 0.85rem; border-radius: var(--radius-full); display: inline-block; margin-bottom: 1rem;">
            Hostel Affairs & Multi-Warden Desk
        </span>
        <h2 style="font-size: 2.2rem; font-weight: 800; line-height: 1.25; margin-bottom: 0.75rem;">
            Hostel Maintenance & Complaint Tracker
        </h2>
        <p style="font-size: 1.05rem; color: #cbd5e1; max-width: 680px; margin: 0 auto 1.75rem auto;">
            Indian Institute of Technology (Indian School of Mines), Dhanbad. Dedicated warden portals for Aquamarine, Amber, Jasper, and campus hostels with live asynchronous status tracking.
        </p>

        <div style="display: flex; justify-content: center; gap: 1rem; flex-wrap: wrap;">
            <?php if (isLoggedIn()): ?>
                <?php if (getCurrentUserRole() === 'admin'): ?>
                    <a href="<?= BASE_URL ?>/admin/dashboard.php" class="btn btn-secondary" style="font-size: 1rem; padding: 0.65rem 1.5rem;">
                        Go to Warden Dashboard &rarr;
                    </a>
                <?php else: ?>
                    <a href="<?= BASE_URL ?>/student/dashboard.php" class="btn btn-secondary" style="font-size: 1rem; padding: 0.65rem 1.5rem;">
                        Go to Student Dashboard &rarr;
                    </a>
                    <a href="<?= BASE_URL ?>/student/create_complaint.php" class="btn btn-outline" style="color: #ffffff; border-color: rgba(255,255,255,0.4); font-size: 1rem; padding: 0.65rem 1.5rem;">
                        + Lodge Maintenance Request
                    </a>
                <?php endif; ?>
            <?php else: ?>
                <a href="<?= BASE_URL ?>/auth/login.php" class="btn btn-secondary" style="font-size: 1rem; padding: 0.65rem 1.5rem;">
                    Sign In (Warden / Student) &rarr;
                </a>
                <a href="<?= BASE_URL ?>/auth/register.php" class="btn btn-outline" style="color: #ffffff; border-color: rgba(255,255,255,0.4); font-size: 1rem; padding: 0.65rem 1.5rem;">
                    New Student Registration
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Live Stats -->
    <div class="stats-grid">
        <div class="stat-card total">
            <div class="stat-info">
                <p>Campus Complaints</p>
                <h3><?= $totalComplaints ?></h3>
            </div>
            <div class="stat-icon">📋</div>
        </div>
        <div class="stat-card inprogress">
            <div class="stat-info">
                <p>Active Maintenance</p>
                <h3><?= $activeComplaints ?></h3>
            </div>
            <div class="stat-icon">⚙️</div>
        </div>
        <div class="stat-card resolved">
            <div class="stat-info">
                <p>Issues Resolved</p>
                <h3><?= $resolvedComplaints ?></h3>
            </div>
            <div class="stat-icon">✅</div>
        </div>
    </div>

    <!-- Multi-Hostel Architecture Highlights -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem; margin-top: 1.5rem;">
        <div style="background: var(--bg-card); padding: 1.5rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); box-shadow: var(--shadow-sm);">
            <div style="font-size: 2rem; margin-bottom: 0.5rem;">🏛️</div>
            <h3 style="font-size: 1.1rem; color: var(--primary); margin-bottom: 0.4rem;">Dedicated Hostel Wardens</h3>
            <p style="font-size: 0.88rem; color: var(--text-muted);">
                Separate administrative access for Aquamarine, Amber, and Jasper hostels. Each warden is strictly restricted to managing complaints for their assigned hostel.
            </p>
        </div>

        <div style="background: var(--bg-card); padding: 1.5rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); box-shadow: var(--shadow-sm);">
            <div style="font-size: 2rem; margin-bottom: 0.5rem;">🔄</div>
            <h3 style="font-size: 1.1rem; color: var(--primary); margin-bottom: 0.4rem;">Live Asynchronous Sync</h3>
            <p style="font-size: 0.88rem; color: var(--text-muted);">
                Built with Vanilla JavaScript Fetch API and 5-second polling. Status updates (<span class="badge badge-submitted">Submitted</span> &rarr; <span class="badge badge-in-progress">In Progress</span> &rarr; <span class="badge badge-resolved">Resolved</span>) update live without page reload.
            </p>
        </div>

        <div style="background: var(--bg-card); padding: 1.5rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); box-shadow: var(--shadow-sm);">
            <div style="font-size: 2rem; margin-bottom: 0.5rem;">🛡️</div>
            <h3 style="font-size: 1.1rem; color: var(--primary); margin-bottom: 0.4rem;">Server-Side Isolation</h3>
            <p style="font-size: 0.88rem; color: var(--text-muted);">
                Robust security ensuring students only view their tickets, and wardens cannot tamper with or access tickets belonging to other hostels.
            </p>
        </div>
    </div>

    <!-- Multi-Hostel Demo Accounts Box -->
    <div class="demo-accounts-box" style="margin-top: 2rem;">
        <h4>Multi-Hostel Lab Evaluation Credentials</h4>
        <p style="font-size: 0.85rem; color: var(--text-main); margin-bottom: 0.75rem;">
            Pre-configured accounts for testing multi-hostel warden isolation:
        </p>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 0.75rem;">
            <div style="background: #ffffff; padding: 0.65rem 0.85rem; border-radius: 6px; border: 1px solid var(--border-color); font-size: 0.82rem;">
                <strong>Aquamarine Warden:</strong> <code>warden.aquamarine@iitism.ac.in</code><br>
                Password: <code>Warden@123</code>
            </div>
            <div style="background: #ffffff; padding: 0.65rem 0.85rem; border-radius: 6px; border: 1px solid var(--border-color); font-size: 0.82rem;">
                <strong>Amber Warden:</strong> <code>warden.amber@iitism.ac.in</code><br>
                Password: <code>Warden@123</code>
            </div>
            <div style="background: #ffffff; padding: 0.65rem 0.85rem; border-radius: 6px; border: 1px solid var(--border-color); font-size: 0.82rem;">
                <strong>Jasper Warden:</strong> <code>warden.jasper@iitism.ac.in</code><br>
                Password: <code>Warden@123</code>
            </div>
            <div style="background: #ffffff; padding: 0.65rem 0.85rem; border-radius: 6px; border: 1px solid var(--border-color); font-size: 0.82rem;">
                <strong>Student (Aquamarine):</strong> <code>aarav.21je0123@iitism.ac.in</code><br>
                Password: <code>Student@123</code>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
