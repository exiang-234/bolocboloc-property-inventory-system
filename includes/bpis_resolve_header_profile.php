<?php

if (!function_exists('bpis_admin_header_profile')) {
    require_once __DIR__ . '/../config/admin_profile_helpers.php';
}
$bpis_header_path_prefix = $bpis_header_path_prefix ?? '../';
if (!isset($bpis_header_avatar) && isset($conn) && $conn instanceof mysqli) {
    $hp = bpis_admin_header_profile($conn, $bpis_header_path_prefix);
    $bpis_header_avatar = $hp['avatar_url'];
    if (!empty($hp['fullname'])) {
        $user_fullname = $hp['fullname'];
    }
    if (!empty($hp['role'])) {
        $user_role = $hp['role'];
    }
}
