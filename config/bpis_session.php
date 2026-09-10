<?php

/**
 * Shared session bootstrap — cookie path scoped to the BPIS app folder.
 */
if (!function_exists('bpis_start_session')) {
    function bpis_start_session(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        if (!function_exists('bpis_app_base_path')) {
            require_once __DIR__ . '/auth_helpers.php';
        }

        $base = bpis_app_base_path();
        $cookiePath = '/';
        if ($base !== '' && $base !== '/') {
            $cookiePath = rtrim($base, '/') . '/';
        }

        session_set_cookie_params([
            'lifetime' => 0,
            'path' => $cookiePath,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}
