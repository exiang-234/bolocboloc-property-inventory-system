<?php
session_start();
include 'config/database.php';
require_once __DIR__ . '/config/auth_helpers.php';

$error_msg = '';
$success_msg = '';

if (isset($_POST['submit'])) {
    $fullname = trim($_POST['full_name'] ?? '');
    $gmail = strtolower(trim($_POST['gmail'] ?? ''));
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    $role = trim($_POST['role'] ?? '');

    if ($fullname === '' || $gmail === '' || $role === '' || $password === '' || $confirm === '') {
        $error_msg = 'Please complete all required fields.';
    } elseif (!bpis_gmail_is_valid($gmail)) {
        $error_msg = 'Please enter a valid Gmail address (example@gmail.com).';
    } elseif ($password !== $confirm) {
        $error_msg = 'Passwords do not match!';
    } else {
        $password_pattern = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,20}$/';

        if (!preg_match($password_pattern, $password)) {
            $error_msg = 'Password must be 8-20 characters long with mixed case, numbers, and symbols.';
        } else {
            $checkUser = $conn->prepare('SELECT id FROM admin WHERE fullname = ? AND LOWER(email) = ? AND role = ?');
            $checkUser->bind_param('sss', $fullname, $gmail, $role);
            $checkUser->execute();
            $resultUser = $checkUser->get_result();

            if ($resultUser->num_rows === 0) {
                $error_msg = 'Error: No administrator found with that name, Gmail, and role.';
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $sql = 'UPDATE admin SET password = ? WHERE fullname = ? AND LOWER(email) = ? AND role = ?';
                $stmt = $conn->prepare($sql);
                $stmt->bind_param('ssss', $hashed_password, $fullname, $gmail, $role);

                if ($stmt->execute()) {
                    $success_msg = 'Password updated successfully. Redirecting to sign in…';
                    $auth_footer_scripts = '<script>setTimeout(function(){ window.location.href = "login.php"; }, 2000);</script>';
                } else {
                    $error_msg = 'Error updating record: ' . $stmt->error;
                }
                $stmt->close();
            }
            $checkUser->close();
        }
    }
    $conn->close();
}

$has_error = $error_msg !== '';
$posted_role = isset($_POST['role']) ? (string) $_POST['role'] : '';

$auth_page_title = 'Reset Password';
$auth_heading = 'Reset Password';
$auth_lead = 'Verify your account details, then set a new password.';
$auth_show_back = true;
$auth_back_href = 'login.php';
$auth_wide = true;
include __DIR__ . '/includes/auth_layout_start.php';
?>
        <?php if ($error_msg): ?>
            <div class="borrow-alert"><strong>Could not update.</strong> <?= htmlspecialchars($error_msg) ?></div>
        <?php endif; ?>

        <?php if ($success_msg): ?>
            <div class="borrow-alert borrow-alert--success"><?= htmlspecialchars($success_msg) ?></div>
        <?php endif; ?>

        <form method="post" action="forgotpass.php" novalidate>
            <div class="borrow-field">
                <label for="full_name">Full Name</label>
                <input type="text" id="full_name" name="full_name" class="borrow-input<?= $has_error ? ' is-invalid' : '' ?>"
                       placeholder="Registered Full Name" required
                       value="<?= isset($_POST['full_name']) ? htmlspecialchars((string) $_POST['full_name']) : '' ?>">
            </div>

            <div class="borrow-field">
                <label for="gmail">Email Address</label>
                <input type="email" id="gmail" name="gmail" class="borrow-input<?= $has_error ? ' is-invalid' : '' ?>"
                       placeholder="you@gmail.com" required autocomplete="email"
                       value="<?= isset($_POST['gmail']) ? htmlspecialchars((string) $_POST['gmail']) : '' ?>">
            </div>

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
            </div>

            <div class="borrow-field">
                <label for="password">New Password</label>
                <div class="borrow-password-wrap">
                    <input type="password" id="password" name="password" class="borrow-input<?= $has_error ? ' is-invalid' : '' ?>"
                           placeholder="8-20 chars, mixed case, number, symbol" required autocomplete="new-password">
                    <button type="button" class="borrow-toggle-pw" aria-label="Show password"
                            onclick="bpisTogglePassword('password', this)">
                        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    </button>
                </div>
            </div>

            <div class="borrow-field">
                <label for="confirm_password">Confirm New Password</label>
                <div class="borrow-password-wrap">
                    <input type="password" id="confirm_password" name="confirm_password" class="borrow-input<?= $has_error ? ' is-invalid' : '' ?>"
                           placeholder="Re-enter New Password" required autocomplete="new-password">
                    <button type="button" class="borrow-toggle-pw" aria-label="Show password"
                            onclick="bpisTogglePassword('confirm_password', this)">
                        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    </button>
                </div>
            </div>

            <button type="submit" name="submit" class="borrow-btn">Update Password</button>
        </form>

        <div class="borrow-footer">
            <a href="login.php">Back to sign in</a>
        </div>
<?php
$auth_footer_scripts = $auth_footer_scripts ?? '';
include __DIR__ . '/includes/auth_layout_end.php';
?>