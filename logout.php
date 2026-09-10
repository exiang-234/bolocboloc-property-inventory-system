<?php
session_start();
require_once __DIR__ . '/config/auth_helpers.php';
bpis_clear_admin_session();
$_SESSION = [];

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();

header('Location: ' . bpis_login_url());
exit();
?>