<?php
session_start();
include __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth_helpers.php';

$config_path = __DIR__ . '/../config/oauth_config.php';
if (!is_file($config_path)) {
    header('Location: ../login.php?error=google_not_configured');
    exit;
}
$oauth = include $config_path;

$state = (string) ($_GET['state'] ?? '');
if ($state === '' || $state !== (string) ($_SESSION['google_oauth_state'] ?? '')) {
    header('Location: ../login.php?error=google_state');
    exit;
}
unset($_SESSION['google_oauth_state']);

$code = (string) ($_GET['code'] ?? '');
if ($code === '') {
    header('Location: ../login.php?error=google_denied');
    exit;
}

$token_body = http_build_query([
    'code' => $code,
    'client_id' => $oauth['client_id'],
    'client_secret' => $oauth['client_secret'],
    'redirect_uri' => $oauth['redirect_uri'],
    'grant_type' => 'authorization_code',
]);

$ch = curl_init('https://oauth2.googleapis.com/token');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $token_body,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
]);
$token_json = curl_exec($ch);
curl_close($ch);
$token = json_decode((string) $token_json, true);
$access = (string) ($token['access_token'] ?? '');
if ($access === '') {
    header('Location: ../login.php?error=google_token');
    exit;
}

$ch = curl_init('https://www.googleapis.com/oauth2/v2/userinfo');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $access],
]);
$user_json = curl_exec($ch);
curl_close($ch);
$profile = json_decode((string) $user_json, true);
$google_id = (string) ($profile['id'] ?? '');
$google_email = strtolower(trim((string) ($profile['email'] ?? '')));

if ($google_id === '' || $google_email === '') {
    header('Location: ../login.php?error=google_profile');
    exit;
}

$stmt = $conn->prepare('SELECT * FROM admin WHERE google_id = ? OR google_email = ? OR username = ? LIMIT 1');
$stmt->bind_param('sss', $google_id, $google_email, $google_email);
$stmt->execute();
$res = $stmt->get_result();
$user = $res ? $res->fetch_assoc() : null;
$stmt->close();

if (!$user) {
    header('Location: ../login.php?error=google_no_account&email=' . urlencode($google_email));
    exit;
}

if (bpis_column_exists($conn, 'admin', 'google_id')) {
    $uid = (int) $user['id'];
    $upd = $conn->prepare('UPDATE admin SET google_id = ?, google_email = ? WHERE id = ?');
    if ($upd) {
        $upd->bind_param('ssi', $google_id, $google_email, $uid);
        $upd->execute();
        $upd->close();
    }
}

bpis_load_admin_session_flags($conn, $user);
$role = (string) ($user['role'] ?? '');
if ($role === 'Secretary') {
    header('Location: ../secretary/dashboard.php');
} elseif ($role === 'Treasurer') {
    header('Location: ../treasurer/dashboard.php');
} elseif ($role === 'Barangay Captain') {
    header('Location: ../captain/dashboard.php');
} else {
    header('Location: ../login.php?error=role');
}
exit;
