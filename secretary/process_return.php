<?php
session_start();
include __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth_helpers.php';
require_once __DIR__ . '/../config/borrow_request_display_helpers.php';
require_once __DIR__ . '/../config/notification_helpers.php';

bpis_require_login(['Secretary', 'Barangay Captain']);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

$borrower_id = (int)($_POST['b_id'] ?? $_POST['request_id'] ?? 0);
if ($borrower_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Missing borrower reference.']);
    exit;
}

$stmt = $conn->prepare('SELECT id, request_id, asset_id, qty, status, full_name FROM borrower WHERE id = ? LIMIT 1');
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Database error.']);
    exit;
}
$stmt->bind_param('i', $borrower_id);
$stmt->execute();
$res = $stmt->get_result();
if (!$res || $res->num_rows === 0) {
    $stmt->close();
    echo json_encode(['success' => false, 'message' => 'Borrower record not found.']);
    exit;
}
$row = $res->fetch_assoc();
$stmt->close();

$current_status = strtolower((string)($row['status'] ?? ''));
if ($current_status === 'returned') {
    echo json_encode(['success' => true, 'message' => 'Already marked as returned.']);
    exit;
}

$asset_id = (int)($row['asset_id'] ?? 0);
$borrowed = (int)($row['qty'] ?? 0);
if ($borrowed < 1) {
    $borrowed = 1;
}

$return_qty = isset($_POST['return_qty']) ? (int)$_POST['return_qty'] : $borrowed;
if ($return_qty < 1) {
    $return_qty = 1;
}
if ($return_qty > $borrowed) {
    $return_qty = $borrowed;
}

$remaining = $borrowed - $return_qty;
$new_status = $remaining > 0 ? 'Received' : 'Returned';

if ($asset_id > 0) {
    $update_asset = "UPDATE asset SET quantity = quantity + ? WHERE id = ?";
    $upd_stmt = $conn->prepare($update_asset);
    if ($upd_stmt) {
        $upd_stmt->bind_param("ii", $return_qty, $asset_id);
        $upd_stmt->execute();
        $upd_stmt->close();
    }
}

$upd = $conn->prepare('UPDATE borrower SET qty = ?, status = ? WHERE id = ?');
if (!$upd) {
    echo json_encode(['success' => false, 'message' => 'Could not prepare update.']);
    exit;
}
$upd->bind_param('isi', $remaining, $new_status, $borrower_id);
$ok = $upd->execute();
$upd->close();

if (!$ok) {
    echo json_encode(['success' => false, 'message' => 'Error updating borrower: ' . $conn->error]);
    exit;
}

$req_id = (int)($row['request_id'] ?? 0);
if ($req_id > 0 && $new_status === 'Returned') {
    $u = $conn->prepare("UPDATE borrowing_requests SET status = 'Returned' WHERE id = ?");
    if ($u) {
        $u->bind_param('i', $req_id);
        $u->execute();
        $u->close();
    }
}

$full_name = trim((string) ($row['full_name'] ?? 'Borrower'));
$item_label = bpis_borrow_request_item_label($conn, $row);
$snap = $asset_id > 0 ? bpis_asset_stock_snapshot($conn, $asset_id) : ['label' => $item_label, 'available' => 0];

if ($new_status === 'Returned') {
    bpis_notify_admins_by_roles(
        $conn,
        ['Secretary'],
        'Items returned',
        $return_qty . ' ' . $snap['label'] . ' have been returned by ' . $full_name . '.',
        'secretary/borrowers2.php',
        'registered_full_return',
        $req_id > 0 ? $req_id : null,
        $borrower_id
    );
} else {
    bpis_notify_admins_by_roles(
        $conn,
        ['Secretary'],
        'Return recorded',
        $return_qty . ' ' . $snap['label'] . ' returned for ' . $full_name . '. ' . $remaining . ' unit(s) still on loan.',
        'secretary/borrowers2.php',
        'registered_partial_return',
        $req_id > 0 ? $req_id : null,
        $borrower_id
    );
}
bpis_notify_admins_by_roles(
    $conn,
    ['Treasurer'],
    'Stock update',
    $return_qty . ' ' . $snap['label'] . ' returned. Available stock: ' . $snap['available'] . '.',
    'treasurer/inventory.php',
    'stock_return_registered',
    $req_id > 0 ? $req_id : null,
    $borrower_id
);
bpis_notify_admins_by_roles(
    $conn,
    ['Barangay Captain'],
    'Borrowing activity',
    'Borrowing activity: ' . $full_name . ' returned ' . $return_qty . ' ' . $snap['label'] . '.',
    'captain/borrowing_logs.php',
    'registered_return_overview',
    $req_id > 0 ? $req_id : null,
    $borrower_id
);

$_SESSION['msg'] = $new_status === 'Returned'
    ? 'Item marked as Returned and inventory updated successfully.'
    : "Recorded return of {$return_qty} unit(s). {$remaining} unit(s) still on loan.";

echo json_encode(['success' => true, 'message' => $_SESSION['msg'], 'new_status' => $new_status, 'new_qty' => $remaining]);
exit;