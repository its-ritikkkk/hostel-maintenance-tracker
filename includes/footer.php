<?php
// ===============================================================
// includes/footer.php - Global Page Footer Component
// ===============================================================
// Closes the main wrapper, provides Toast container, loads JavaScript
// modules, and renders the institutional footer.
?>
    </main>

    <!-- Toast Notification Container for Asynchronous Feedback -->
    <div id="toastContainer" aria-live="polite" aria-atomic="true"></div>

    <!-- Institutional Footer -->
    <footer class="site-footer">
        <div class="footer-container">
            <div>
                <p class="footer-institution">Indian Institute of Technology (Indian School of Mines), Dhanbad</p>
                <p class="footer-sub">Hostel Maintenance & Complaint Tracker • Web Development Lab Project</p>
            </div>
            <div>
                <p class="footer-sub">Chief Warden Office & Hall Management Council • <?= date('Y') ?></p>
            </div>
        </div>
    </footer>

    <!-- Shared Core Client Scripts -->
    <script src="<?= BASE_URL ?>/assets/js/main.js?v=<?= time() ?>"></script>

    <!-- Optional Page-Specific Script -->
    <?php if (isset($pageScript)): ?>
        <script src="<?= BASE_URL ?>/assets/js/<?= htmlspecialchars($pageScript) ?>?v=<?= time() ?>"></script>
    <?php endif; ?>
</body>
</html>
