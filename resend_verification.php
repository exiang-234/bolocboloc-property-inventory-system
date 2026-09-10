<?php
session_start();
include 'config/database.php';
require_once __DIR__ . '/config/auth_helpers.php';

$email = strtolower(trim((string) ($_GET['email'] ?? $_POST['email'] ?? '')));
$msg = '';
$err = '';

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$script_dir = dirname($_SERVER['SCRIPT_NAME'] ?? '');
$script_dir = str_replace('\\', '/', $script_dir);
$script_dir = rtrim($script_dir, '/');
$verify_url_base = $scheme . '://' . $host . $script_dir;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!bpis_gmail_is_valid($email)) {
        $err = 'Enter a valid Gmail address (@gmail.com).';
    } else {
        $stmt = $conn->prepare(
            'SELECT id, fullname FROM admin WHERE LOWER(email) = ? AND email_verified = 0 LIMIT 1'
        );
        if ($stmt) {
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $rs = $stmt->get_result();
            $row = $rs ? $rs->fetch_assoc() : null;
            $stmt->close();

            if (!$row) {
                $err = 'No pending registration found for that Gmail, or it is already verified.';
            } elseif (bpis_send_admin_verification_email($conn, (int) $row['id'], $verify_url_base)) {
                $msg = 'Verification email sent to ' . htmlspecialchars($email) . '. Check your inbox and spam folder.';
            } else {
                $err = 'Could not send verification email. Please try again later.';
            }
        }
    }
}

$auth_page_title = 'Resend verification';
$auth_heading = 'Resend verification email';
$auth_lead = 'Enter the Gmail you used during registration. We will send a new verification link (valid 24 hours).';
$auth_show_back = true;
$auth_back_href = 'login.php';
include __DIR__ . '/includes/auth_layout_start.php';
?>
        <?php if ($msg): ?>
            <div class="borrow-alert borrow-alert--success"><?= $msg ?></div>
        <?php endif; ?>
        <?php if ($err): ?>
            <div class="borrow-alert"><strong>Unable to send.</strong> <?= htmlspecialchars($err) ?></div>
        <?php endif; ?>

        <form method="post" action="resend_verification.php" novalidate>
            <div class="borrow-field">
                <label for="email">Gmail address</label>
                <input type="email" id="email" name="email" class="borrow-input"
                       value="<?= htmlspecialchars($email) ?>" placeholder="you@gmail.com" required autocomplete="email">
            </div>
            <button type="submit" class="borrow-btn">Send verification link</button>
        </form>

        <div class="borrow-footer">
            <a href="login.php">Back to sign in</a>
        </div>
<?php include __DIR__ . '/includes/auth_layout_end.php'; ?>
