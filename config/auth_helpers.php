<?php

require_once __DIR__ . '/schema_bootstrap.php';

if (!function_exists('bpis_app_base_path')) {
    /**
     * URL path the app is mounted at, with no trailing slash.
     *
     * Derived by comparing the running script's location on disk with its URL,
     * so the app works wherever it is served from: '/BPIS' under
     * htdocs/BPIS/, and '' when the app folder is itself the document root
     * (php -S, a vhost, or a subdomain). Do not hardcode '/BPIS' anywhere —
     * use bpis_url() to build links and asset URLs.
     */
    function bpis_app_base_path(): string
    {
        static $base = null;
        if ($base !== null) {
            return $base;
        }

        $norm = static function (string $p): string {
            return rtrim(str_replace('\\', '/', $p), '/');
        };

        $app_root = $norm(dirname(__DIR__));
        $script_file = (string) ($_SERVER['SCRIPT_FILENAME'] ?? '');
        $script_file = $norm($script_file !== '' ? (realpath($script_file) ?: $script_file) : '');
        $script_name = $norm((string) ($_SERVER['SCRIPT_NAME'] ?? ''));

        if ($script_file !== '' && $script_name !== ''
            && stripos($script_file, $app_root . '/') === 0) {
            // e.g. '/secretary/dashboard.php' — the part of the URL below the app root.
            $relative = substr($script_file, strlen($app_root));
            if ($relative !== '' && substr($script_name, -strlen($relative)) === $relative) {
                return $base = substr($script_name, 0, strlen($script_name) - strlen($relative));
            }
        }

        // Fall back to the legacy behaviour: look for a literal BPIS segment.
        $parts = array_values(array_filter(explode('/', trim($script_name, '/')), 'strlen'));
        foreach ($parts as $idx => $part) {
            if (strcasecmp($part, 'BPIS') === 0) {
                return $base = '/' . implode('/', array_slice($parts, 0, $idx + 1));
            }
        }

        return $base = '';
    }
}

if (!function_exists('bpis_url')) {
    /** Build an absolute URL path for an app-relative path, e.g. 'css/auth_flow.css'. */
    function bpis_url(string $path = ''): string
    {
        return bpis_app_base_path() . '/' . ltrim($path, '/');
    }
}

if (!function_exists('bpis_login_url')) {
    function bpis_login_url(array $query = []): string
    {
        $url = bpis_app_base_path() . '/login.php';
        if ($query !== []) {
            $url .= '?' . http_build_query($query);
        }

        return $url;
    }
}

if (!function_exists('bpis_dashboard_url_for_role')) {
    function bpis_dashboard_url_for_role(?string $role = null): string
    {
        $role = $role ?? (string) ($_SESSION['role'] ?? '');
        $base = bpis_app_base_path();

        return match ($role) {
            'Secretary' => $base . '/secretary/dashboard.php',
            'Treasurer' => $base . '/treasurer/dashboard.php',
            'Barangay Captain' => $base . '/captain/dashboard.php',
            default => bpis_login_url(),
        };
    }
}

if (!function_exists('bpis_clear_admin_session')) {
    function bpis_clear_admin_session(): void
    {
        foreach ([
            'admin_id',
            'email',
            'username',
            'role',
            'is_super_admin',
            'profile_fullname',
            'fullname',
        ] as $key) {
            unset($_SESSION[$key]);
        }
    }
}

if (!function_exists('bpis_require_login')) {
    function bpis_require_login(string|array|null $allowed_roles = null): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        if (empty($_SESSION['admin_id'])) {
            header('Location: ' . bpis_login_url());
            exit;
        }
        if ($allowed_roles === null) {
            return;
        }
        $roles = is_array($allowed_roles) ? $allowed_roles : [$allowed_roles];
        $role = (string) ($_SESSION['role'] ?? '');
        if (!in_array($role, $roles, true)) {
            bpis_clear_admin_session();
            header('Location: ' . bpis_login_url(['error' => 'wrong_role']));
            exit;
        }
    }
}

if (!function_exists('bpis_require_login_json')) {
    function bpis_require_login_json(string|array|null $allowed_roles = null): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        header('Content-Type: application/json');
        if (empty($_SESSION['admin_id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'login_required']);
            exit;
        }
        if ($allowed_roles === null) {
            return;
        }
        $roles = is_array($allowed_roles) ? $allowed_roles : [$allowed_roles];
        $role = (string) ($_SESSION['role'] ?? '');
        if (!in_array($role, $roles, true)) {
            bpis_clear_admin_session();
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'wrong_role']);
            exit;
        }
    }
}

if (!function_exists('bpis_is_super_admin')) {
    /** Barangay Captain is super admin per project policy. */
    function bpis_is_super_admin(): bool
    {
        if (!empty($_SESSION['is_super_admin'])) {
            return true;
        }
        return (string) ($_SESSION['role'] ?? '') === 'Barangay Captain';
    }
}

if (!function_exists('bpis_require_super_admin')) {
    function bpis_require_super_admin(): void
    {
        bpis_require_login(['Barangay Captain']);
        if (!bpis_is_super_admin()) {
            bpis_clear_admin_session();
            header('Location: ' . bpis_login_url(['error' => 'wrong_role']));
            exit;
        }
    }
}

if (!function_exists('bpis_username_is_formal_format')) {
    /**
     * Formal username: name.role@domain (e.g. cdsedano.student@asiancollege.edu.ph)
     */
    function bpis_username_is_formal_format(string $username): bool
    {
        $username = strtolower(trim($username));
        if ($username === '') {
            return false;
        }
        return (bool) preg_match(
            '/^[a-z0-9][a-z0-9._-]*\.[a-z0-9][a-z0-9._-]*@[a-z0-9][a-z0-9.-]+\.[a-z]{2,}$/i',
            $username
        );
    }
}

if (!function_exists('bpis_load_admin_session_flags')) {
    function bpis_load_admin_session_flags(mysqli $conn, array $admin_row): void
    {
        $_SESSION['admin_id'] = (int) ($admin_row['id'] ?? 0);
        $email = strtolower(trim((string) ($admin_row['email'] ?? '')));
        $_SESSION['email'] = $email;
        $_SESSION['username'] = $email !== '' ? $email : (string) ($admin_row['username'] ?? '');
        $_SESSION['role'] = (string) ($admin_row['role'] ?? '');
        $_SESSION['is_super_admin'] = ((int) ($admin_row['is_super_admin'] ?? 0) === 1)
            || ((string) ($admin_row['role'] ?? '') === 'Barangay Captain');
    }
}

if (!function_exists('bpis_admin_find_by_email')) {
    function bpis_admin_find_by_email(mysqli $conn, string $email): ?array
    {
        $email = strtolower(trim($email));
        if ($email === '' || !bpis_column_exists($conn, 'admin', 'email')) {
            return null;
        }
        $stmt = $conn->prepare('SELECT * FROM admin WHERE LOWER(email) = ? LIMIT 1');
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $rs = $stmt->get_result();
        $row = $rs ? $rs->fetch_assoc() : null;
        $stmt->close();
        return $row ?: null;
    }
}

if (!function_exists('bpis_gmail_is_valid')) {
    function bpis_gmail_is_valid(string $email): bool
    {
        $email = strtolower(trim($email));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        $domain = substr(strrchr($email, '@') ?: '', 1);
        return in_array($domain, ['gmail.com', 'googlemail.com'], true);
    }
}

if (!function_exists('bpis_admin_email_verification_required')) {
    function bpis_admin_email_verification_required(mysqli $conn, array $admin_row): bool
    {
        if (!bpis_column_exists($conn, 'admin', 'email_verified')) {
            return false;
        }
        if ((int) ($admin_row['email_verified'] ?? 0) === 1) {
            return false;
        }
        $email = trim((string) ($admin_row['email'] ?? ''));
        return $email !== '';
    }
}

if (!function_exists('bpis_issue_admin_email_verification')) {
    /** @return string|false verification token */
    function bpis_issue_admin_email_verification(mysqli $conn, int $admin_id)
    {
        if ($admin_id <= 0 || !bpis_column_exists($conn, 'admin', 'email_verify_token')) {
            return false;
        }
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', time() + 86400);
        $stmt = $conn->prepare(
            'UPDATE admin SET email_verify_token = ?, email_verify_expires_at = ? WHERE id = ?'
        );
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('ssi', $token, $expires, $admin_id);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok ? $token : false;
    }
}

if (!function_exists('bpis_send_admin_verification_email')) {
    function bpis_send_admin_verification_email(
        mysqli $conn,
        int $admin_id,
        string $verify_url_base
    ): bool {
        require_once __DIR__ . '/email_templates.php';
        require_once __DIR__ . '/mail_helpers.php';

        $stmt = $conn->prepare('SELECT fullname, email FROM admin WHERE id = ? LIMIT 1');
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('i', $admin_id);
        $stmt->execute();
        $rs = $stmt->get_result();
        $row = $rs ? $rs->fetch_assoc() : null;
        $stmt->close();
        if (!$row) {
            return false;
        }

        $email = strtolower(trim((string) ($row['email'] ?? '')));
        if (!bpis_gmail_is_valid($email)) {
            return false;
        }

        $token = bpis_issue_admin_email_verification($conn, $admin_id);
        if ($token === false) {
            return false;
        }

        $verify_url = rtrim($verify_url_base, '/') . '/verify_email.php?token=' . urlencode($token);
        $html = bpis_registration_verify_email_html(
            (string) ($row['fullname'] ?? 'Admin'),
            $verify_url
        );

        return bpis_send_html_mail(
            $email,
            (string) ($row['fullname'] ?? ''),
            'Verify your BPIS admin registration',
            $html
        );
    }
}

if (!function_exists('bpis_borrower_is_blocked')) {
    function bpis_borrower_is_blocked(mysqli $conn, string $full_name, string $email = ''): bool
    {
        if (!bpis_table_exists($conn, 'borrower_blocklist')) {
            return false;
        }
        $name = trim($full_name);
        $email = strtolower(trim($email));
        if ($name === '' && $email === '') {
            return false;
        }
        if ($email !== '') {
            $stmt = $conn->prepare(
                "SELECT id FROM borrower_blocklist WHERE is_active = 1 AND (LOWER(email) = ? OR LOWER(full_name) = LOWER(?)) LIMIT 1"
            );
            if ($stmt) {
                $stmt->bind_param('ss', $email, $name);
                $stmt->execute();
                $stmt->store_result();
                $blocked = $stmt->num_rows > 0;
                $stmt->close();
                if ($blocked) {
                    return true;
                }
            }
        }
        $stmt = $conn->prepare(
            "SELECT id FROM borrower_blocklist WHERE is_active = 1 AND LOWER(full_name) = LOWER(?) LIMIT 1"
        );
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('s', $name);
        $stmt->execute();
        $stmt->store_result();
        $blocked = $stmt->num_rows > 0;
        $stmt->close();
        return $blocked;
    }
}
