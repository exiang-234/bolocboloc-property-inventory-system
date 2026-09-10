<?php

if (!function_exists('bpis_secretary_load_user')) {
    function bpis_secretary_load_user(mysqli $conn): array
    {
        $fullname = 'Guest';
        $role = 'Staff';
        if (!empty($_SESSION['admin_id'])) {
            $admin_id = (int) $_SESSION['admin_id'];
            $stmt = $conn->prepare('SELECT fullname, role FROM admin WHERE id = ? LIMIT 1');
            if ($stmt) {
                $stmt->bind_param('i', $admin_id);
                $stmt->execute();
                $rs = $stmt->get_result();
                if ($rs && ($row = $rs->fetch_assoc())) {
                    $fullname = (string) ($row['fullname'] ?? $fullname);
                    $role = (string) ($row['role'] ?? $role);
                }
                $stmt->close();
            }
        }
        return ['fullname' => $fullname, 'role' => $role];
    }
}
