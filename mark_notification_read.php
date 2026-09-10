<?php
require_once __DIR__ . '/config/bpis_session.php';
bpis_start_session();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth_helpers.php';

bpis_require_login_json(['Secretary', 'Treasurer', 'Barangay Captain']);

if (!($conn instanceof mysqli) || $conn->connect_error) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'db_error'], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

if (isset($_POST['id'])) {
    $id = (int)$_POST['id'];
    $admin_id = (int)$_SESSION['admin_id'];
    $is_read = isset($_POST['is_read']) ? (int)$_POST['is_read'] : 1;
    $is_read = $is_read === 0 ? 0 : 1;
    $stmt = $conn->prepare("UPDATE notifications SET is_read = ? WHERE id = ? AND admin_id = ?");
    if (!$stmt) {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'db_error'], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }
    $stmt->bind_param("iii", $is_read, $id, $admin_id);
    $result = $stmt->execute();
    $stmt->close();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => (bool)$result], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
} else {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
}
