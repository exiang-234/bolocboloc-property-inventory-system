<?php

/**
 * Per-session CSRF token used to protect state-changing requests.
 *
 * Call bpis_csrf_token() to render the token into a form, and
 * bpis_csrf_check_post() at the top of the handler that acts on it.
 */
if (!function_exists('bpis_csrf_token')) {
    function bpis_csrf_token(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return (string) $_SESSION['csrf_token'];
    }
}

if (!function_exists('bpis_csrf_token_is_valid')) {
    function bpis_csrf_token_is_valid(?string $token): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $expected = (string) ($_SESSION['csrf_token'] ?? '');

        return $expected !== ''
            && is_string($token)
            && hash_equals($expected, $token);
    }
}

if (!function_exists('bpis_csrf_field')) {
    function bpis_csrf_field(): string
    {
        return '<input type="hidden" name="csrf_token" value="'
            . htmlspecialchars(bpis_csrf_token(), ENT_QUOTES) . '">';
    }
}

if (!function_exists('bpis_csrf_check_post')) {
    /** Rejects the request unless it is a POST carrying a valid token. */
    function bpis_csrf_check_post(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'
            || !bpis_csrf_token_is_valid($_POST['csrf_token'] ?? null)) {
            http_response_code(400);
            exit('Invalid or expired request. Please reload the page and try again.');
        }
    }
}
