<?php
session_start();
include '../config/database.php';
require_once __DIR__ . '/../config/auth_helpers.php';

bpis_require_super_admin();

$bpis_captain_nav_active = 'users';
$allowed_roles = ['Barangay Captain', 'Secretary', 'Treasurer'];
$self_id = (int) ($_SESSION['admin_id'] ?? 0);
$msg = '';
$err = '';

function bpis_admin_password_valid(string $password): bool
{
    return (bool) preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,20}$/', $password);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'create') {
        $fullname = trim((string) ($_POST['fullname'] ?? ''));
        $gmail = strtolower(trim((string) ($_POST['gmail'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');
        $role = (string) ($_POST['role'] ?? '');
        $username = $gmail;

        if ($fullname === '' || $gmail === '' || $password === '' || !in_array($role, $allowed_roles, true)) {
            $err = 'All fields are required with a valid role.';
        } elseif (!bpis_gmail_is_valid($gmail)) {
            $err = 'Gmail must be a valid @gmail.com address.';
        } elseif (!bpis_admin_password_valid($password)) {
            $err = 'Password must be 8–20 characters with mixed case, numbers, and symbols.';
        } else {
            $check = $conn->prepare('SELECT id FROM admin WHERE LOWER(email) = ? OR LOWER(username) = ? LIMIT 1');
            $check->bind_param('ss', $gmail, $gmail);
            $check->execute();
            $check->store_result();
            if ($check->num_rows > 0) {
                $err = 'This Gmail is already registered.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $is_super = ($role === 'Barangay Captain') ? 1 : 0;
                $email_verified = 1;
                if (bpis_column_exists($conn, 'admin', 'email')) {
                    if (bpis_column_exists($conn, 'admin', 'is_super_admin')) {
                        $stmt = $conn->prepare('INSERT INTO admin (fullname, username, password, role, email, email_verified, is_super_admin) VALUES (?,?,?,?,?,?,?)');
                        $stmt->bind_param('sssssii', $fullname, $username, $hash, $role, $gmail, $email_verified, $is_super);
                    } else {
                        $stmt = $conn->prepare('INSERT INTO admin (fullname, username, password, role, email, email_verified) VALUES (?,?,?,?,?,?)');
                        $stmt->bind_param('sssssi', $fullname, $username, $hash, $role, $gmail, $email_verified);
                    }
                } elseif (bpis_column_exists($conn, 'admin', 'is_super_admin')) {
                    $stmt = $conn->prepare('INSERT INTO admin (fullname, username, password, role, is_super_admin) VALUES (?,?,?,?,?)');
                    $stmt->bind_param('ssssi', $fullname, $username, $hash, $role, $is_super);
                } else {
                    $stmt = $conn->prepare('INSERT INTO admin (fullname, username, password, role) VALUES (?,?,?,?)');
                    $stmt->bind_param('ssss', $fullname, $username, $hash, $role);
                }
                if ($stmt && $stmt->execute()) {
                    $msg = 'User account created.';
                } else {
                    $err = 'Could not create user account.';
                }
                if ($stmt) {
                    $stmt->close();
                }
            }
            $check->close();
        }
    } elseif ($action === 'update') {
        $admin_id = (int) ($_POST['admin_id'] ?? 0);
        $fullname = trim((string) ($_POST['fullname'] ?? ''));
        $gmail = strtolower(trim((string) ($_POST['gmail'] ?? '')));
        $role = (string) ($_POST['role'] ?? '');
        $username = $gmail;

        if ($admin_id <= 0 || $fullname === '' || $gmail === '' || !in_array($role, $allowed_roles, true)) {
            $err = 'Invalid update request.';
        } elseif (!bpis_gmail_is_valid($gmail)) {
            $err = 'Gmail must be a valid @gmail.com address.';
        } else {
            $check = $conn->prepare('SELECT id FROM admin WHERE (LOWER(email) = ? OR LOWER(username) = ?) AND id <> ? LIMIT 1');
            $check->bind_param('ssi', $gmail, $gmail, $admin_id);
            $check->execute();
            $check->store_result();
            if ($check->num_rows > 0) {
                $err = 'This Gmail is already used by another account.';
            } else {
                $is_super = ($role === 'Barangay Captain') ? 1 : 0;
                if (bpis_column_exists($conn, 'admin', 'email') && bpis_column_exists($conn, 'admin', 'is_super_admin')) {
                    $stmt = $conn->prepare('UPDATE admin SET fullname = ?, username = ?, email = ?, role = ?, is_super_admin = ? WHERE id = ?');
                    $stmt->bind_param('ssssii', $fullname, $username, $gmail, $role, $is_super, $admin_id);
                } elseif (bpis_column_exists($conn, 'admin', 'email')) {
                    $stmt = $conn->prepare('UPDATE admin SET fullname = ?, username = ?, email = ?, role = ? WHERE id = ?');
                    $stmt->bind_param('ssssi', $fullname, $username, $gmail, $role, $admin_id);
                } elseif (bpis_column_exists($conn, 'admin', 'is_super_admin')) {
                    $stmt = $conn->prepare('UPDATE admin SET fullname = ?, username = ?, role = ?, is_super_admin = ? WHERE id = ?');
                    $stmt->bind_param('sssii', $fullname, $username, $role, $is_super, $admin_id);
                } else {
                    $stmt = $conn->prepare('UPDATE admin SET fullname = ?, username = ?, role = ? WHERE id = ?');
                    $stmt->bind_param('sssi', $fullname, $username, $role, $admin_id);
                }
                if ($stmt && $stmt->execute()) {
                    if ($admin_id === $self_id) {
                        $_SESSION['role'] = $role;
                        $_SESSION['email'] = $gmail;
                        $_SESSION['username'] = $gmail;
                        $_SESSION['is_super_admin'] = ($role === 'Barangay Captain');
                    }
                    $msg = 'User account updated.';
                } else {
                    $err = 'Could not update user account.';
                }
                if ($stmt) {
                    $stmt->close();
                }
            }
            $check->close();
        }
    } elseif ($action === 'reset_password') {
        $admin_id = (int) ($_POST['admin_id'] ?? 0);
        $password = (string) ($_POST['password'] ?? '');

        if ($admin_id <= 0 || !bpis_admin_password_valid($password)) {
            $err = 'Enter a valid new password (8–20 chars, mixed case, numbers, symbols).';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare('UPDATE admin SET password = ? WHERE id = ?');
            $stmt->bind_param('si', $hash, $admin_id);
            if ($stmt && $stmt->execute()) {
                $msg = 'Password reset successfully.';
            } else {
                $err = 'Could not reset password.';
            }
            if ($stmt) {
                $stmt->close();
            }
        }
    } elseif ($action === 'delete') {
        $admin_id = (int) ($_POST['admin_id'] ?? 0);
        if ($admin_id <= 0) {
            $err = 'Invalid delete request.';
        } elseif ($admin_id === $self_id) {
            $err = 'You cannot delete your own account while logged in.';
        } else {
            $target = null;
            $rs = mysqli_query($conn, "SELECT id, role FROM admin WHERE id = {$admin_id} LIMIT 1");
            if ($rs) {
                $target = mysqli_fetch_assoc($rs);
            }
            if (!$target) {
                $err = 'User not found.';
            } elseif (($target['role'] ?? '') === 'Barangay Captain') {
                $cap_rs = mysqli_query($conn, "SELECT COUNT(*) AS c FROM admin WHERE role = 'Barangay Captain'");
                $cap_count = $cap_rs ? (int) mysqli_fetch_assoc($cap_rs)['c'] : 1;
                if ($cap_count <= 1) {
                    $err = 'Cannot delete the only Barangay Captain account.';
                }
            }
            if ($err === '') {
                $stmt = $conn->prepare('DELETE FROM admin WHERE id = ?');
                $stmt->bind_param('i', $admin_id);
                if ($stmt && $stmt->execute()) {
                    $msg = 'User account deleted.';
                } else {
                    $err = 'Could not delete user account.';
                }
                if ($stmt) {
                    $stmt->close();
                }
            }
        }
    }
}

$users = [];
$cols = 'id, fullname, username, role, created_at';
if (bpis_column_exists($conn, 'admin', 'google_email')) {
    $cols .= ', google_email';
}
if (bpis_column_exists($conn, 'admin', 'email')) {
    $cols .= ', email';
}
if (bpis_column_exists($conn, 'admin', 'email_verified')) {
    $cols .= ', email_verified';
}
if (bpis_column_exists($conn, 'admin', 'is_super_admin')) {
    $cols .= ', is_super_admin';
}
$rs = mysqli_query($conn, "SELECT {$cols} FROM admin ORDER BY role ASC, fullname ASC");
if ($rs) {
    while ($row = mysqli_fetch_assoc($rs)) {
        $users[] = $row;
    }
}

$bpis_page_title = 'User Accounts';
$bpis_page_heading = 'User Accounts';
$bpis_page_subtitle = 'Manage staff accounts. Each Gmail login must be unique.';
$bpis_captain_nav_active = 'users';
$bpis_extra_head = '<link rel="stylesheet" href="../css/captain_users.css">';
include __DIR__ . '/../includes/captain_layout_start.php';
?>
                <?php if ($msg): ?>
                    <div class="captain-users-flash ok"><?= htmlspecialchars($msg) ?></div>
                <?php endif; ?>
                <?php if ($err): ?>
                    <div class="captain-users-flash err"><?= htmlspecialchars($err) ?></div>
                <?php endif; ?>

                <section class="captain-users-create">
                    <h2>Create account</h2>
                    <form method="post" class="captain-users-form-grid">
                        <input type="hidden" name="action" value="create">
                        <div>
                            <label>Full name <span class="text-red-600">*</span></label>
                            <input name="fullname" required>
                        </div>
                        <div>
                            <label>Gmail (login ID) <span class="text-red-600">*</span></label>
                            <input name="gmail" type="email" placeholder="you@gmail.com" required>
                            <p class="captain-users-hint">Captain-created accounts are verified automatically.</p>
                        </div>
                        <div>
                            <label>Password <span class="text-red-600">*</span></label>
                            <input name="password" type="password" required>
                        </div>
                        <div>
                            <label>Role <span class="text-red-600">*</span></label>
                            <select name="role" required>
                                <option value="">Select role</option>
                                <?php foreach ($allowed_roles as $role): ?>
                                    <option value="<?= htmlspecialchars($role) ?>"><?= htmlspecialchars($role) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="span-2">
                            <button type="submit" class="captain-users-btn captain-users-btn-primary">
                                <i class="fa-solid fa-user-plus" aria-hidden="true"></i>
                                Create user
                            </button>
                        </div>
                    </form>
                </section>

                <section class="captain-users-list">
                    <h2>Existing accounts (<?= count($users) ?>)</h2>
                    <?php if (!$users): ?>
                        <p class="captain-users-empty">No admin accounts found.</p>
                    <?php else: ?>
                        <?php foreach ($users as $u): ?>
                            <?php
                            $uid = (int) $u['id'];
                            $gmail_val = (string) ($u['email'] ?? $u['username'] ?? '');
                            $is_super = !empty($u['is_super_admin']) || ($u['role'] ?? '') === 'Barangay Captain';
                            ?>
                            <article class="captain-user-card">
                                <div class="captain-user-card-head">
                                    <span class="captain-user-card-id">Account #<?= $uid ?></span>
                                    <span class="captain-user-role-badge<?= $is_super ? ' super' : '' ?>"><?= htmlspecialchars($u['role'] ?? '') ?></span>
                                </div>
                                <form method="post" class="captain-user-edit-grid">
                                    <input type="hidden" name="action" value="update">
                                    <input type="hidden" name="admin_id" value="<?= $uid ?>">
                                    <div>
                                        <label>Full name</label>
                                        <input name="fullname" value="<?= htmlspecialchars($u['fullname']) ?>" required>
                                    </div>
                                    <div>
                                        <label>Gmail (login)</label>
                                        <input name="gmail" type="email" value="<?= htmlspecialchars($gmail_val) ?>" required>
                                        <?php if (isset($u['email_verified'])): ?>
                                            <p class="captain-user-meta <?= (int) $u['email_verified'] === 1 ? 'verified' : 'unverified' ?>">
                                                <?= (int) $u['email_verified'] === 1 ? 'Email verified' : 'Email not verified' ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <label>Role</label>
                                        <select name="role" required>
                                            <?php foreach ($allowed_roles as $role): ?>
                                                <option value="<?= htmlspecialchars($role) ?>" <?= ($u['role'] ?? '') === $role ? 'selected' : '' ?>><?= htmlspecialchars($role) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div>
                                        <label>Google OAuth</label>
                                        <input type="text" value="<?= htmlspecialchars($u['google_email'] ?? '—') ?>" readonly disabled>
                                    </div>
                                    <div class="span-2">
                                        <button type="submit" class="captain-users-btn captain-users-btn-save">
                                            <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
                                            Save changes
                                        </button>
                                    </div>
                                </form>
                                <div class="captain-user-actions">
                                    <form method="post" class="captain-user-reset-form">
                                        <input type="hidden" name="action" value="reset_password">
                                        <input type="hidden" name="admin_id" value="<?= $uid ?>">
                                        <input name="password" type="password" placeholder="New password" autocomplete="new-password">
                                        <button type="submit" class="captain-users-btn captain-users-btn-reset">Reset password</button>
                                    </form>
                                    <?php if ($uid !== $self_id): ?>
                                        <form method="post" onsubmit="return confirm('Delete this user account?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="admin_id" value="<?= $uid ?>">
                                            <button type="submit" class="captain-users-btn captain-users-btn-delete">
                                                <i class="fa-solid fa-trash" aria-hidden="true"></i>
                                                Delete
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </section>
<?php include __DIR__ . '/../includes/captain_layout_end.php'; ?>
