<?php

require_once __DIR__ . '/config/bpis_session.php';
bpis_start_session();

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth_helpers.php';

/**
 * @param array<string,mixed> $payload
 */
function bpis_feed_json(array $payload, int $status = 200): void
{
    http_response_code($status);
    // headers may already be sent in CLI test harness — suppress warning
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    echo $json;
    if (defined('BPIS_TEST_MODE') && BPIS_TEST_MODE) {
        // In test mode throw instead of exiting so harness can inspect output without terminating
        throw new RuntimeException('BPIS_FEED_JSON:' . $json, $status);
    }
    exit;
}

$allowed_roles = ['Secretary', 'Treasurer', 'Barangay Captain'];
$admin_id = (int) ($_SESSION['admin_id'] ?? 0);
$user_role = (string) ($_SESSION['role'] ?? '');

if ($admin_id <= 0) {
    bpis_feed_json(['success' => false, 'error' => 'login_required'], 401);
}

if (!in_array($user_role, $allowed_roles, true)) {
    bpis_feed_json(['success' => false, 'error' => 'wrong_role'], 403);
}

$unread_count = 0;
$notifications = [];

if (!($conn instanceof mysqli) || $conn->connect_error) {
    bpis_feed_json(['success' => false, 'error' => 'db_error'], 500);
}

$count_stmt = $conn->prepare('SELECT COUNT(*) AS unread_count FROM notifications WHERE admin_id = ? AND is_read = 0');
if (!$count_stmt) {
    bpis_feed_json(['success' => false, 'error' => 'db_error'], 500);
}
$count_stmt->bind_param('i', $admin_id);
$count_stmt->execute();
$count_stmt->bind_result($unread_count);
$count_stmt->fetch();
$unread_count = (int) $unread_count;
$count_stmt->close();

$feed_stmt = $conn->prepare('
    SELECT id, title, message, relationship, link, borrowing_request_id, borrower_id, is_read, created_at
    FROM notifications
    WHERE admin_id = ?
    ORDER BY created_at DESC
    LIMIT 10
');
if (!$feed_stmt) {
    bpis_feed_json(['success' => false, 'error' => 'db_error'], 500);
}
$feed_stmt->bind_param('i', $admin_id);
$feed_stmt->execute();
$feed_result = null;
if (method_exists($feed_stmt, 'get_result')) {
    $feed_result = $feed_stmt->get_result();
}
if ($feed_result) {
    while ($row = $feed_result->fetch_assoc()) {
        $notifications[] = $row;
    }
} else {
    // Fallback for environments without mysqlnd or when get_result fails
    $feed_stmt->store_result();
    if ($feed_stmt->num_rows > 0) {
        $feed_stmt->bind_result($fid, $ftitle, $fmessage, $frel, $flink, $fbrq, $fbor, $fisread, $fcreated);
        while ($feed_stmt->fetch()) {
            $notifications[] = [
                'id' => $fid,
                'title' => $ftitle,
                'message' => $fmessage,
                'relationship' => $frel,
                'link' => $flink,
                'borrowing_request_id' => $fbrq,
                'borrower_id' => $fbor,
                'is_read' => $fisread,
                'created_at' => $fcreated,
            ];
        }
    }
}
$feed_stmt->close();

bpis_feed_json([
    'success' => true,
    'unread_count' => $unread_count,
    'notifications' => $notifications,
]);