<?php
require_once __DIR__ . '/_cli_only.php';
$_SERVER['SCRIPT_NAME'] = '/BPIS/notifications_feed.php';
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$_SESSION['admin_id'] = 11;
$_SESSION['role'] = 'Secretary';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
ob_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth_helpers.php';
require_once __DIR__ . '/../config/notification_helpers.php';
echo "STEP1\n";
bpis_require_login_json(['Secretary', 'Treasurer', 'Barangay Captain']);
echo "STEP2\n";
$admin_id = 11;
$count_stmt = $conn->prepare('SELECT COUNT(*) AS unread_count FROM notifications WHERE admin_id = ? AND is_read = 0');
echo "STEP3\n";
$count_stmt->bind_param('i', $admin_id);
$count_stmt->execute();
$count_stmt->bind_result($unread_count);
$count_stmt->fetch();
echo "STEP4 unread=$unread_count\n";
while (ob_get_level() > 0) {
    ob_end_clean();
}
echo json_encode(['success' => true, 'unread_count' => (int)$unread_count]);
