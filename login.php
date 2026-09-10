<?php
// Log problems, never render them: this page is public, and PHP errors leak
// file paths and database details to anyone who can reach the login form.
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/config/bpis_session.php';
bpis_start_session();
require_once __DIR__ . '/config/database.php'; 
require_once __DIR__ . '/config/auth_helpers.php';

if (!empty($_SESSION['admin_id']) && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . bpis_dashboard_url_for_role());
    exit;
}

$error_msg = '';
$login_success_msg = !empty($_GET['verified']) ? 'Email verified successfully. You may now log in.' : '';
if (!empty($_GET['error'])) {
    $map = [
        'google_not_configured' => 'Google sign-in is not configured yet. Ask the administrator to set up config/oauth_config.php.',
        'google_no_account' => 'No BPIS account is linked to this Google email. Register first or contact the Barangay Captain.',
        'google_denied' => 'Google sign-in was cancelled.',
        'wrong_role' => 'That page is for a different role. Sign in with the correct account to continue.',
    ];
    $error_msg = $map[$_GET['error']] ?? 'Sign-in failed. Please try again.';
    if (!empty($_GET['email']) && $_GET['error'] === 'google_no_account') {
        $error_msg .= ' (' . htmlspecialchars((string) $_GET['email']) . ')';
    }
}

if (isset($_POST['submit'])) {
    $gmail = strtolower(trim((string) ($_POST['gmail'] ?? '')));
    $password = $_POST['password'] ?? '';

    if ($gmail === '') {
        $error_msg = 'Please enter your Email address.';
    } elseif (!bpis_gmail_is_valid($gmail)) {
        $error_msg = 'Please enter a valid Email address (example@gmail.com).';
    } else {
        $user = bpis_admin_find_by_email($conn, $gmail);

        if ($user && password_verify($password, $user['password'] ?? '')) {
            if (bpis_admin_email_verification_required($conn, $user)) {
                $error_msg = 'Please verify your Email before logging in. Check your inbox or ';
                $error_msg .= '<a href="resend_verification.php?email=' . urlencode($gmail) . '">resend the verification link</a>.';
            } else {
                bpis_load_admin_session_flags($conn, $user);

                $role = $user['role'] ?? '';
                if ($role === 'Secretary') {
                    header('Location: secretary/dashboard.php');
                    exit();
                }
                if ($role === 'Treasurer') {
                    header('Location: treasurer/dashboard.php');
                    exit();
                }
                if ($role === 'Barangay Captain') {
                    header('Location: captain/dashboard.php');
                    exit();
                }
                $error_msg = 'Your account role is not assigned to a dashboard. Please contact the administrator.';
                unset($_SESSION['username'], $_SESSION['email'], $_SESSION['role'], $_SESSION['admin_id']);
            }
        } else {
            $error_msg = 'Invalid Email or password.';
        }
    }
}

$oauth_cfg = is_file(__DIR__ . '/config/oauth_config.php') ? include __DIR__ . '/config/oauth_config.php' : ['enabled' => false];
$has_error = $error_msg !== '';
$gmail_value = isset($_POST['gmail']) ? htmlspecialchars((string) $_POST['gmail']) : '';

$auth_page_title = 'Sign in';
$auth_heading = 'Staff Sign In';
$auth_lead = 'Sign in with your registered Email and password.';
$auth_illustration = 'images/Computer login-amico.svg';
$auth_body_class = 'borrow-auth-flow--login';
include __DIR__ . '/includes/auth_layout_start.php';
?>
        <?php if ($login_success_msg !== ''): ?>
            <div class="borrow-alert borrow-alert--success"><?= htmlspecialchars($login_success_msg) ?></div>
        <?php endif; ?>

        <?php if ($error_msg !== ''): ?>
            <div class="borrow-alert"><strong>Unable to sign in.</strong> <?= $error_msg ?></div>
        <?php endif; ?>

        <form method="post" action="login.php" novalidate>
            <div class="borrow-field">
                <label for="gmail">Email Address</label>
                <input type="email" id="gmail" name="gmail" class="borrow-input<?= $has_error ? ' is-invalid' : '' ?>"
                       placeholder="you@gmail.com" required autocomplete="email" inputmode="email"
                       value="<?= $gmail_value ?>">
            </div>

            <div class="borrow-field">
                <label for="password">Password</label>
                <div class="borrow-password-wrap">
                    <input type="password" id="password" name="password" class="borrow-input<?= $has_error ? ' is-invalid' : '' ?>"
                           placeholder="Enter password" required autocomplete="current-password">
                    <button type="button" class="borrow-toggle-pw" aria-label="Show password"
                            onclick="bpisTogglePassword('password', this)">
                        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    </button>
                </div>
            </div>

            <button type="submit" name="submit" class="borrow-btn">Sign in</button>
        </form>

        <?php if (!empty($oauth_cfg['enabled'])): ?>
            <div class="borrow-divider">or</div>
            <a href="auth/google_login.php" class="borrow-google-btn">
                <img src="https://www.gstatic.com/firebasejs/ui/2.0.0/images/auth/google.svg" width="20" height="20" alt="">
                Sign in with Google
            </a>
        <?php endif; ?>

        <div class="borrow-footer borrow-footer--split">
            <a href="forgotpass.php">Forgot password?</a>
            <a href="register.php">Create an account</a>
        </div>
<?php include __DIR__ . '/includes/auth_layout_end.php'; ?>