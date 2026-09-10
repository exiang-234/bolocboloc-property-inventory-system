<?php

require_once __DIR__ . '/auth_helpers.php';

if (!function_exists('bpis_store_upload')) {
    function bpis_store_upload(string $field, string $subdir = 'assets'): ?string
    {

        if (empty($_FILES[$field]['tmp_name']) || !is_uploaded_file($_FILES[$field]['tmp_name'])) {
            return '';
        }
        
        $ext = strtolower(pathinfo((string) $_FILES[$field]['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
        
        if (!in_array($ext, $allowed, true)) {
            return '';
        }
        

        $upload_dir = dirname(__DIR__) . '/uploads/' . trim($subdir, '/');
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $name = date('Ymd_His') . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $dest = $upload_dir . '/' . $name;
        
        if (move_uploaded_file($_FILES[$field]['tmp_name'], $dest)) {

            return 'uploads/' . $subdir . '/' . $name;
        }
        
        return '';
    }
}

if (!function_exists('bpis_get_photo_url')) {
    function bpis_get_photo_url($path) {
        if (empty($path)) {
            return '';
        }

        return bpis_url((string) $path);
    }
}
?>