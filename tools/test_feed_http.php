<?php

declare(strict_types=1);

require_once __DIR__ . '/_cli_only.php';

$cookieFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'bpis_notif_test_cookies.txt';
@unlink($cookieFile);

$base = 'http://127.0.0.1/BPIS';

require_once __DIR__ . '/../config/database.php';
$rs = mysqli_query($conn, "SELECT id, email, password, role FROM admin WHERE role = 'Secretary' LIMIT 1");
$admin = $rs ? mysqli_fetch_assoc($rs) : null;
if (!$admin) {
    fwrite(STDERR, "No secretary account\n");
    exit(1);
}

$_SERVER['SCRIPT_NAME'] = '/BPIS/notifications_feed.php';
$_SERVER['HTTP_HOST'] = '127.0.0.1';
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$_SESSION['admin_id'] = (int) $admin['id'];
$_SESSION['role'] = 'Secretary';
session_write_close();

$ch = curl_init($base . '/notifications_feed.php');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HEADER => true,
    CURLOPT_COOKIEJAR => $cookieFile,
    CURLOPT_COOKIEFILE => $cookieFile,
]);
// Pass session id manually
curl_setopt($ch, CURLOPT_COOKIE, session_name() . '=' . session_id());

$raw = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP $code\n";
echo "SESSION_ID=" . session_id() . " admin_id=" . $admin['id'] . "\n";
echo "---RAW---\n";
echo $raw . "\n";
echo "---JSON---\n";
$parts = explode("\r\n\r\n", $raw, 2);
$body = $parts[1] ?? $raw;
$j = json_decode($body, true);
echo json_last_error_msg() . "\n";
if (is_array($j)) {
    echo 'success=' . ($j['success'] ? 'yes' : 'no') . ' unread=' . ($j['unread_count'] ?? '?') . "\n";
}
