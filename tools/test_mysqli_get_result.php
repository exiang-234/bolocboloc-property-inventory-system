<?php
require_once __DIR__ . '/_cli_only.php';
require __DIR__ . '/../config/database.php';
if (!($conn instanceof mysqli) || $conn->connect_error) {
    echo "SKIP: DB unavailable (" . ($conn ? $conn->connect_error : 'null conn') . ")\n";
    exit(0);
}
$stmt = $conn->prepare('SELECT COUNT(*) AS c FROM notifications WHERE admin_id = ?');
if (!$stmt) {
    echo "SKIP: prepare failed: " . $conn->error . "\n";
    exit(0);
}
$id = 11;
$stmt->bind_param('i', $id);
$stmt->execute();
$result = $stmt->get_result();
if ($result === false) {
    // Fallback for missing mysqlnd — use bind_result
    $stmt->bind_result($c);
    $stmt->fetch();
    echo "c=$c\n";
    $stmt->close();
    echo "OK (fallback)\n";
    exit(0);
}
print_r($result->fetch_assoc());
$stmt->close();
echo "OK\n";
