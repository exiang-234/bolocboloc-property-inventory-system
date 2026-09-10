<?php

require_once __DIR__ . '/schema_bootstrap.php';
require_once __DIR__ . '/upload_helpers.php';

if (!function_exists('bpis_ensure_admin_profile_columns')) {
    function bpis_ensure_admin_profile_columns(mysqli $conn): void
    {
        bpis_add_column_if_missing($conn, 'admin', 'contact_number', 'VARCHAR(30) NULL DEFAULT NULL');
        bpis_add_column_if_missing($conn, 'admin', 'address', 'VARCHAR(255) NULL DEFAULT NULL');
        bpis_add_column_if_missing($conn, 'admin', 'bio', 'TEXT NULL');
        bpis_add_column_if_missing($conn, 'admin', 'photo_path', 'VARCHAR(500) NULL DEFAULT NULL');
    }
}

if (!function_exists('bpis_admin_dashboard_url')) {
    function bpis_admin_dashboard_url(?string $role = null): string
    {
        $role = $role ?? (string) ($_SESSION['role'] ?? '');
        return match ($role) {
            'Secretary' => 'secretary/dashboard.php',
            'Treasurer' => 'treasurer/dashboard.php',
            'Barangay Captain' => 'captain/dashboard.php',
            default => 'login.php',
        };
    }
}

if (!function_exists('bpis_admin_profile_avatar_src')) {
    /**
     * Avatar URL for admin profile. $path_prefix is prepended to stored uploads
     * (e.g. "../" from treasurer/ or secretary/ pages).
     */
    function bpis_admin_profile_avatar_src(array $profile, string $path_prefix = ''): string
    {
        $path = trim((string) ($profile['photo_path'] ?? ''));
        if ($path !== '') {
            if (preg_match('#^https?://#i', $path)) {
                return $path;
            }
            $path = ltrim(str_replace('\\', '/', $path), '/');

            // The row can outlive the file (restored database, cleared uploads).
            // Fall through to the generated avatar instead of emitting a 404.
            if (!is_file(dirname(__DIR__) . '/' . $path)) {
                $path = '';
            }
        }

        if ($path !== '') {
            $prefix = rtrim(str_replace('\\', '/', $path_prefix), '/');
            if ($prefix !== '' && !str_starts_with($path, $prefix . '/')) {
                return $prefix . '/' . $path;
            }

            return $path;
        }
        $name = rawurlencode((string) ($profile['fullname'] ?? 'User'));

        return 'https://ui-avatars.com/api/?name=' . $name . '&background=174C7D&color=fff&size=256';
    }
}

if (!function_exists('bpis_admin_profile_avatar_url')) {
    function bpis_admin_profile_avatar_url(array $profile): string
    {
        return bpis_admin_profile_avatar_src($profile, '');
    }
}

if (!function_exists('bpis_admin_header_profile')) {
    /** @return array{fullname: string, role: string, avatar_url: string} */
    function bpis_admin_header_profile(mysqli $conn, string $path_prefix = '../'): array
    {
        $admin_id = (int) ($_SESSION['admin_id'] ?? 0);
        $fallback = [
            'fullname' => (string) ($_SESSION['profile_fullname'] ?? $_SESSION['fullname'] ?? 'User'),
            'role' => (string) ($_SESSION['role'] ?? 'Staff'),
            'avatar_url' => rtrim($path_prefix, '/') . '/images/profile_logo.png',
        ];
        if ($admin_id <= 0) {
            return $fallback;
        }
        $profile = bpis_load_admin_profile($conn, $admin_id);
        if (!$profile) {
            return $fallback;
        }

        return [
            'fullname' => (string) ($profile['fullname'] ?? $fallback['fullname']),
            'role' => (string) ($profile['role'] ?? $fallback['role']),
            'avatar_url' => bpis_admin_profile_avatar_src($profile, $path_prefix),
        ];
    }
}

if (!function_exists('bpis_load_admin_profile')) {
    function bpis_load_admin_profile(mysqli $conn, int $admin_id): ?array
    {
        if ($admin_id <= 0) {
            return null;
        }
        bpis_ensure_admin_profile_columns($conn);

        $cols = ['id', 'fullname', 'username', 'role'];
        if (bpis_column_exists($conn, 'admin', 'email')) {
            $cols[] = 'email';
        }
        foreach (['contact_number', 'address', 'bio', 'photo_path'] as $c) {
            if (bpis_column_exists($conn, 'admin', $c)) {
                $cols[] = $c;
            }
        }
        $stmt = $conn->prepare(
            'SELECT ' . implode(', ', $cols) . ' FROM admin WHERE id = ? LIMIT 1'
        );
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('i', $admin_id);
        $stmt->execute();
        $rs = $stmt->get_result();
        $row = $rs ? $rs->fetch_assoc() : null;
        $stmt->close();

        return $row ?: null;
    }
}

if (!function_exists('bpis_save_admin_profile')) {
    function bpis_save_admin_profile(mysqli $conn, int $admin_id, array $data, ?array $files = null): bool
    {
        if ($admin_id <= 0) {
            return false;
        }
        bpis_ensure_admin_profile_columns($conn);

        $fullname = trim((string) ($data['fullname'] ?? ''));
        if ($fullname === '') {
            return false;
        }

        $contact = trim((string) ($data['contact_number'] ?? ''));
        $address = trim((string) ($data['address'] ?? ''));
        $bio = trim((string) ($data['bio'] ?? ''));

        $photo_path = null;
        if ($files && !empty($files['profile_photo']['tmp_name'])) {
            $photo_path = bpis_store_upload('profile_photo', 'profiles');
        }

        if ($photo_path !== null) {
            $stmt = $conn->prepare(
                'UPDATE admin SET fullname = ?, contact_number = ?, address = ?, bio = ?, photo_path = ? WHERE id = ?'
            );
            if (!$stmt) {
                return false;
            }
            $stmt->bind_param('sssssi', $fullname, $contact, $address, $bio, $photo_path, $admin_id);
        } else {
            $stmt = $conn->prepare(
                'UPDATE admin SET fullname = ?, contact_number = ?, address = ?, bio = ? WHERE id = ?'
            );
            if (!$stmt) {
                return false;
            }
            $stmt->bind_param('ssssi', $fullname, $contact, $address, $bio, $admin_id);
        }

        $ok = $stmt->execute();
        $stmt->close();

        if ($ok) {
            $_SESSION['profile_fullname'] = $fullname;
        }

        return $ok;
    }
}
