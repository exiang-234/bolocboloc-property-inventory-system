<?php
session_start();
include 'config/database.php';
require_once __DIR__ . '/config/auth_helpers.php';

$token = trim((string) ($_GET['token'] ?? ''));
$status = 'invalid';
$message = 'This verification link is invalid or has expired.';

if ($token !== '' && bpis_column_exists($conn, 'admin', 'email_verify_token')) {
    $stmt = $conn->prepare(
        "SELECT id, fullname, email FROM admin
         WHERE email_verify_token = ?
           AND email_verified = 0
           AND (email_verify_expires_at IS NULL OR email_verify_expires_at >= NOW())
         LIMIT 1"
    );
    if ($stmt) {
        $stmt->bind_param('s', $token);
        $stmt->execute();
        $rs = $stmt->get_result();
        $row = $rs ? $rs->fetch_assoc() : null;
        $stmt->close();

        if ($row) {
            $admin_id = (int) ($row['id'] ?? 0);
            $upd = $conn->prepare(
                'UPDATE admin SET email_verified = 1, email_verify_token = NULL, email_verify_expires_at = NULL WHERE id = ?'
            );
            if ($upd) {
                $upd->bind_param('i', $admin_id);
                if ($upd->execute()) {
                    $status = 'success';
                    $message = 'Your Gmail has been verified. You can now sign in to BPIS.';
                }
                $upd->close();
            }
        }
    }
}

$auth_page_title = 'Email verification';
$auth_heading = $status === 'success' ? 'Email verified' : 'Verification failed';
$auth_lead = $message;
include __DIR__ . '/includes/auth_layout_start.php';
?>
        <div class="borrow-alert<?= $status === 'success' ? ' borrow-alert--success' : '' ?>">
            <?= htmlspecialchars($message) ?>
        </div>

        <div class="borrow-footer borrow-footer--split">
            <?php if ($status === 'success'): ?>
                <a href="login.php?verified=1">Go to sign in</a>
            <?php else: ?>
                <a href="resend_verification.php">Resend verification</a>
                <a href="register.php">Register</a>
            <?php endif; ?>
        </div>
<?php include __DIR__ . '/includes/auth_layout_end.php'; ?>