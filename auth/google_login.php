<?php
session_start();

$config_path = __DIR__ . '/../config/oauth_config.php';
if (!is_file($config_path)) {
    header('Location: ../login.php?error=google_not_configured');
    exit;
}
$oauth = include $config_path;
if (empty($oauth['enabled']) || empty($oauth['client_id'])) {
    header('Location: ../login.php?error=google_not_configured');
    exit;
}

$state = bin2hex(random_bytes(16));
$_SESSION['google_oauth_state'] = $state;

$params = http_build_query([
    'client_id' => $oauth['client_id'],
    'redirect_uri' => $oauth['redirect_uri'],
    'response_type' => 'code',
    'scope' => 'openid email profile',
    'state' => $state,
    'prompt' => 'select_account',
]);

header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . $params);
exit;
