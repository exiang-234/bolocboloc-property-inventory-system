<?php
session_start();
include 'config/database.php';
require_once __DIR__ . '/config/auth_helpers.php';

$error_msg = '';
$success_msg = '';
$pending_gmail = '';
$registration_complete = false;

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$script_dir = dirname($_SERVER['SCRIPT_NAME'] ?? '');
$script_dir = str_replace('\\', '/', $script_dir);
$script_dir = rtrim($script_dir, '/');
$verify_url_base = $scheme . '://' . $host . $script_dir;

$allowed_roles = ['Barangay Captain', 'Secretary', 'Treasurer'];

if (isset($_POST['submit'])) {
    $fullname = trim((string) ($_POST['full_name'] ?? ''));
    $gmail = strtolower(trim((string) ($_POST['gmail'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['confirm_password'] ?? '');
    $role = (string) ($_POST['role'] ?? '');
    $username = $gmail;

    if ($role === '') {
        $error_msg = 'Please select a role profile.';
    } elseif (!in_array($role, $allowed_roles, true)) {
        $error_msg = 'Please select a valid role profile.';
    } elseif ($fullname === '') {
        $error_msg = 'Please enter your full name.';
    } elseif (!bpis_gmail_is_valid($gmail)) {
        $error_msg = 'Please enter a valid Email address (example@gmail.com).';
    } elseif ($password !== $confirm) {
        $error_msg = 'Passwords do not match!';
    } else {
        $password_pattern = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,20}$/';

        if (!preg_match($password_pattern, $password)) {
            $error_msg = 'Password must be 8-20 characters long with mixed case, numbers, and symbols.';
        } else {
            $checkGmail = $conn->prepare('SELECT id FROM admin WHERE LOWER(email) = ? OR LOWER(username) = ? LIMIT 1');
            $checkGmail->bind_param('ss', $gmail, $gmail);
            $checkGmail->execute();
            $gmailResult = $checkGmail->get_result();

            // "Single account per role is ONLY allowed" — enforce it here, not just in the hint.
            $checkRole = $conn->prepare('SELECT id FROM admin WHERE role = ? LIMIT 1');
            $checkRole->bind_param('s', $role);
            $checkRole->execute();
            $roleResult = $checkRole->get_result();
            $role_taken = $roleResult && $roleResult->num_rows > 0;
            $checkRole->close();

            if ($gmailResult && $gmailResult->num_rows > 0) {
                $error_msg = 'Error: This Email is already registered.';
            } elseif ($role_taken) {
                $error_msg = 'A ' . $role . ' account already exists. Only one account per role is allowed — '
                    . 'ask the Barangay Captain to manage it from the user management page.';
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $is_super = ($role === 'Barangay Captain') ? 1 : 0;
                $email_verified = 0;

                if (bpis_column_exists($conn, 'admin', 'email')) {
                    if (bpis_column_exists($conn, 'admin', 'is_super_admin')) {
                        $sql = 'INSERT INTO admin (fullname, username, password, role, email, email_verified, is_super_admin) VALUES (?, ?, ?, ?, ?, ?, ?)';
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param('sssssii', $fullname, $username, $hashed_password, $role, $gmail, $email_verified, $is_super);
                    } else {
                        $sql = 'INSERT INTO admin (fullname, username, password, role, email, email_verified) VALUES (?, ?, ?, ?, ?, ?)';
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param('sssssi', $fullname, $username, $hashed_password, $role, $gmail, $email_verified);
                    }
                } elseif (bpis_column_exists($conn, 'admin', 'is_super_admin')) {
                    $sql = 'INSERT INTO admin (fullname, username, password, role, is_super_admin) VALUES (?, ?, ?, ?, ?)';
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param('ssssi', $fullname, $username, $hashed_password, $role, $is_super);
                } else {
                    $sql = 'INSERT INTO admin (fullname, username, password, role) VALUES (?, ?, ?, ?)';
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param('ssss', $fullname, $username, $hashed_password, $role);
                }

                if ($stmt && $stmt->execute()) {
                    $admin_id = (int) $conn->insert_id;
                    $mail_sent = bpis_send_admin_verification_email($conn, $admin_id, $verify_url_base);
                    $pending_gmail = $gmail;
                    if ($mail_sent) {
                        $success_msg = 'Registration saved! We sent a verification link to <strong>' . htmlspecialchars($gmail) . '</strong>. Open your Gmail and click the link to activate your account before logging in.';
                    } else {
                        $success_msg = 'Registration saved, but the verification email could not be sent. <a href="resend_verification.php?email=' . urlencode($gmail) . '">Resend verification email</a>.';
                    }
                    $registration_complete = true;
                } else {
                    if ($stmt) {
                        error_log('BPIS register failed: ' . $stmt->error);
                    }
                    $error_msg = 'Could not register account. Please try again.';
                }
                if ($stmt) {
                    $stmt->close();
                }
            }
            $checkGmail->close();
        }
    }
    $conn->close();
}

$has_error = $error_msg !== '';
$posted_role = (!$registration_complete && isset($_POST['role'])) ? (string) $_POST['role'] : '';

$auth_page_title = 'Register';
$auth_heading = 'Create Staff Account';
$auth_lead = 'Register as Treasurer, Secretary, or Barangay Captain. Each person needs a unique Email.';
$auth_wide = true;
include __DIR__ . '/includes/auth_layout_start.php';
?>
        <?php if ($error_msg): ?>
            <div class="borrow-alert"><strong>Registration issue.</strong> <?= htmlspecialchars($error_msg) ?></div>
        <?php endif; ?>

        <?php if ($success_msg): ?>
            <div class="borrow-alert borrow-alert--success"><?= $success_msg ?></div>
            <?php if ($pending_gmail): ?>
                <p class="borrow-hint" style="margin-bottom:18px;">
                    Did not receive it? <a href="resend_verification.php?email=<?= urlencode($pending_gmail) ?>">Resend verification email</a>
                </p>
            <?php endif; ?>
        <?php endif; ?>

        <?php if (!$registration_complete): ?>
        <form method="post" action="register.php" novalidate>
            <div class="borrow-field">
                <label for="role">Role</label>
                <input type="hidden" id="role" name="role" value="<?= htmlspecialchars($posted_role) ?>" required>
                <div class="borrow-role-grid">
                    <button type="button" class="borrow-role-btn<?= $posted_role === 'Barangay Captain' ? ' is-active' : '' ?>" data-role="Barangay Captain">
                        <span class="borrow-role-icon" aria-hidden="true">👤</span>
                        <span>Barangay Captain</span>
                    </button>
                    <button type="button" class="borrow-role-btn<?= $posted_role === 'Secretary' ? ' is-active' : '' ?>" data-role="Secretary">
                        <span class="borrow-role-icon" aria-hidden="true">👤</span>
                        <span>Secretary</span>
                    </button>
                    <button type="button" class="borrow-role-btn<?= $posted_role === 'Treasurer' ? ' is-active' : '' ?>" data-role="Treasurer">
                        <span class="borrow-role-icon" aria-hidden="true">👤</span>
                        <span>Treasurer</span>
                    </button>
                </div>
                <p class="borrow-hint">Single account per role is ONLY allowed. Each person must use a unique Email.</p>
            </div>

            <div class="borrow-field">
                <label for="full_name">Full Name</label>
                <input type="text" id="full_name" name="full_name" class="borrow-input<?= $has_error ? ' is-invalid' : '' ?>"
                       placeholder="First Name, Middle Initial, Last Name" required
                       value="<?= (!$registration_complete && isset($_POST['full_name'])) ? htmlspecialchars((string) $_POST['full_name']) : '' ?>">
            </div>

            <div class="borrow-field">
                <label for="gmail">Email Address</label>
                <input type="email" id="gmail" name="gmail" class="borrow-input<?= $has_error ? ' is-invalid' : '' ?>"
                       placeholder="you@gmail.com" required autocomplete="email" inputmode="email"
                       value="<?= (!$registration_complete && isset($_POST['gmail'])) ? htmlspecialchars((string) $_POST['gmail']) : '' ?>">
                <p class="borrow-hint">This Email is your login ID. A verification link will be sent before you can sign in.</p>
            </div>

            <div class="borrow-field">
                <label for="password">Password</label>
                <div class="borrow-password-wrap">
                    <input type="password" id="password" name="password" class="borrow-input<?= $has_error ? ' is-invalid' : '' ?>"
                           placeholder="8-20 chars, mixed case, number, symbol" required autocomplete="new-password">
                    <button type="button" class="borrow-toggle-pw" aria-label="Show password"
                            onclick="bpisTogglePassword('password', this)">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    </button>
                </div>
            </div>

            <div class="borrow-field">
                <label for="confirm_password">Confirm Password</label>
                <div class="borrow-password-wrap">
                    <input type="password" id="confirm_password" name="confirm_password" class="borrow-input<?= $has_error ? ' is-invalid' : '' ?>"
                           placeholder="Re-enter Password" required autocomplete="new-password">
                    <button type="button" class="borrow-toggle-pw" aria-label="Show password"
                            onclick="bpisTogglePassword('confirm_password', this)">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    </button>
                </div>
            </div>

            <button type="submit" name="submit" class="borrow-btn">Register Account</button>
        </form>
        <?php endif; ?>

        <div class="borrow-footer">
            Already registered? <a href="login.php">Back to sign in</a>
        </div>
<?php include __DIR__ . '/includes/auth_layout_end.php'; ?>