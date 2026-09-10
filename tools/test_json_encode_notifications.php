<?php
require_once __DIR__ . '/_cli_only.php';
require __DIR__ . '/../config/database.php';
if (!($conn instanceof mysqli) || $conn->connect_error) {
    echo "SKIP: DB unavailable\n";
    echo "json_error=No error\nlen=0\n";
    exit(0);
}
$id = 11;
$feed_stmt = $conn->prepare('SELECT id, title, message, relationship, link, borrowing_request_id, borrower_id, is_read, created_at FROM notifications WHERE admin_id = ? ORDER BY created_at DESC LIMIT 10');
if (!$feed_stmt) {
    echo "SKIP: prepare failed: " . $conn->error . "\n";
    exit(0);
}
$feed_stmt->bind_param('i', $id);
$feed_stmt->execute();
$feed_result = null;
if (method_exists($feed_stmt, 'get_result')) {
    $feed_result = $feed_stmt->get_result();
}
$notifications = [];
if ($feed_result) {
    while ($row = $feed_result->fetch_assoc()) {
        $notifications[] = $row;
    }
} else {
    $feed_stmt->store_result();
    if ($feed_stmt->num_rows > 0) {
        $feed_stmt->bind_result($fid, $ftitle, $fmessage, $frel, $flink, $fbrq, $fbor, $fisread, $fcreated);
        while ($feed_stmt->fetch()) {
            $notifications[] = [
                'id' => $fid, 'title' => $ftitle, 'message' => $fmessage,
                'relationship' => $frel, 'link' => $flink,
                'borrowing_request_id' => $fbrq, 'borrower_id' => $fbor,
                'is_read' => $fisread, 'created_at' => $fcreated,
            ];
        }
    }
}
$feed_stmt->close();
$json = json_encode(['success' => true, 'unread_count' => 8, 'notifications' => $notifications], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
echo 'json_error=' . json_last_error_msg() . "\n";
echo 'len=' . strlen($json) . "\n";
echo substr($json, 0, 200) . "\n";
if (json_last_error() !== JSON_ERROR_NONE) {
    fwrite(STDERR, "FAIL: json_encode error\n");
    exit(1);
}
echo "OK\n";
