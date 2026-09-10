<?php

/**
 * Site entry point.
 *
 * Without this the app had no root document: visiting the BPIS folder returned
 * a 404 (or an Apache directory listing). It also gives the public borrowing
 * flow a way in — nothing linked to borrower/step1.php before.
 */
require_once __DIR__ . '/config/bpis_session.php';
bpis_start_session();
require_once __DIR__ . '/config/auth_helpers.php';

// Staff who are already signed in go straight to their own dashboard.
if (!empty($_SESSION['admin_id'])) {
    header('Location: ' . bpis_dashboard_url_for_role());
    exit;
}

$auth_page_title = 'Barangay Bolocboloc Property Inventory System';
$auth_heading = 'Welcome';
$auth_lead = 'Request to borrow barangay property, or sign in to manage the inventory.';
include __DIR__ . '/includes/auth_layout_start.php';
?>
        <div class="borrow-field">
            <a href="borrower/step1.php" class="borrow-btn">Borrow an item</a>
            <p class="borrow-hint">
                For residents. Submit a borrowing request — no account needed.
            </p>
        </div>

        <div class="borrow-divider">or</div>

        <div class="borrow-field">
            <a href="login.php" class="borrow-btn borrow-btn--secondary">Staff sign in</a>
            <p class="borrow-hint">
                For the Barangay Captain, Secretary, and Treasurer.
            </p>
        </div>

        <div class="borrow-footer">
            <a href="register.php">Create a staff account</a>
        </div>
<?php include __DIR__ . '/includes/auth_layout_end.php'; ?>
