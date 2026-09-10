<?php
session_start();
include __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth_helpers.php';
require_once __DIR__ . '/../config/borrow_request_display_helpers.php';
require_once __DIR__ . '/../config/asset_borrowable_helpers.php';
require_once __DIR__ . '/../config/notification_helpers.php';

bpis_require_login(['Secretary', 'Barangay Captain']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: borrowers2.php");
    exit();
}


$full_name   = trim($_POST['full_name'] ?? '');
$email       = trim($_POST['email'] ?? '');
$contact     = trim($_POST['contact'] ?? '');
$purok       = trim($_POST['address'] ?? '');
$purpose     = trim($_POST['purpose'] ?? '');
$date_needed = trim($_POST['needed_on'] ?? '');
$return_date = trim($_POST['return_date'] ?? '');

$items = $_POST['search_item'] ?? [];
$qtys  = $_POST['quantity'] ?? [];
$asset_ids = $_POST['asset_id'] ?? [];

if (
    $full_name === '' || $email === '' || $contact === '' || $purok === '' ||
    $purpose === '' || $date_needed === '' || $return_date === '' ||
    empty($items) || empty($qtys)
) {
    header("Location: borrowers2.php?add_error=missing_fields");
    exit();
}


$existing_columns = [];
$cols_result = $conn->query("SHOW COLUMNS FROM borrower");
if ($cols_result) {
    while ($col = $cols_result->fetch_assoc()) {
        $existing_columns[$col['Field']] = true;
    }
    $cols_result->free();
} else {
    header("Location: borrowers2.php?add_error=table_not_found");
    exit();
}

$pick_col = function(array $candidates) use ($existing_columns) {
    foreach ($candidates as $candidate) {
        if (isset($existing_columns[$candidate])) {
            return $candidate;
        }
    }
    return null;
};

$col_full_name  = $pick_col(['full_name', 'name']);
$col_email      = $pick_col(['email']);
$col_contact    = $pick_col(['contact_number', 'contact', 'phone']);
$col_address    = $pick_col(['purok', 'address']);
$item_cols      = bpis_borrow_request_item_columns_for_schema($existing_columns);
$col_asset_id   = $pick_col(['asset_id']);
$col_qty        = $pick_col(['quantity', 'qty']);
$col_purpose    = $pick_col(['purpose']);
$col_date_need  = $pick_col(['date_needed', 'needed_on']);
$col_return     = $pick_col(['return_date']);
$col_status     = $pick_col(['status']);

if (!$col_full_name || !$col_contact || !$col_address || (empty($item_cols) && !$col_asset_id) || !$col_qty || !$col_status) {
    header("Location: borrowers2.php?add_error=schema_mismatch");
    exit();
}

$last_borrower_entry_id = 0;
$line_summaries = [];
$treasurer_lines = [];

for ($i = 0; $i < count($items); $i++) {
    $item = trim((string)$items[$i]);
    $qty  = (int)($qtys[$i] ?? 0);
    $asset_id = (int)($asset_ids[$i] ?? 0);

    $has_text = $item !== '';
    $has_asset = $col_asset_id && $asset_id > 0;
    if ((!$has_text && !$has_asset) || $qty <= 0) {
        continue;
    }

    if ($asset_id > 0 && !bpis_asset_id_is_borrowable($conn, $asset_id)) {
        header('Location: borrowers2.php?add_error=not_borrowable');
        exit();
    }

    $insert_columns = [$col_full_name];
    $insert_values = [$full_name];
    $insert_types = "s";

    if ($col_email) { $insert_columns[] = $col_email; $insert_values[] = $email; $insert_types .= "s"; }
    $insert_columns[] = $col_contact; $insert_values[] = $contact; $insert_types .= "s";
    $insert_columns[] = $col_address; $insert_values[] = $purok; $insert_types .= "s";

    foreach ($item_cols as $ic) { $insert_columns[] = $ic; $insert_values[] = $item; $insert_types .= "s"; }

    if ($col_asset_id) { $insert_columns[] = $col_asset_id; $insert_values[] = $asset_id; $insert_types .= "i"; }

    $insert_columns[] = $col_qty; $insert_values[] = $qty; $insert_types .= "i";
    
    if ($col_purpose) { $insert_columns[] = $col_purpose; $insert_values[] = $purpose; $insert_types .= "s"; }
    if ($col_date_need) { $insert_columns[] = $col_date_need; $insert_values[] = $date_needed; $insert_types .= "s"; }
    if ($col_return) { $insert_columns[] = $col_return; $insert_values[] = $return_date; $insert_types .= "s"; }

    $insert_columns[] = $col_status;
    $insert_values[] = "Received"; 
    $insert_types .= "s";

    $placeholders = implode(", ", array_fill(0, count($insert_columns), "?"));

    $sql = "INSERT INTO borrower (" . implode(", ", $insert_columns) . ") VALUES ($placeholders)";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        header("Location: borrowers2.php?add_error=prepare_failed");
        exit();
    }

    $bind_params = [$insert_types];
    foreach ($insert_values as $key => $value) {
        $bind_params[] = &$insert_values[$key];
    }
    call_user_func_array([$stmt, 'bind_param'], $bind_params);
    
    if ($stmt->execute()) {
        $last_borrower_entry_id = (int)$conn->insert_id;

        $line_summaries[] = $qty . ' × ' . $item;

        if ($asset_id > 0) {
            $update_asset = "UPDATE asset SET quantity = quantity - ? WHERE id = ?";
            $upd_stmt = $conn->prepare($update_asset);
            $upd_stmt->bind_param("ii", $qty, $asset_id);
            $upd_stmt->execute();
            $upd_stmt->close();
            $snap = bpis_asset_stock_snapshot($conn, $asset_id);
            $treasurer_lines[] = $qty . ' ' . $snap['label'] . ' borrowed on walk-in. Available stock: ' . $snap['available'] . '.';
        }
    }
    $stmt->close();
}


bpis_notify_admins_by_roles(
    $conn,
    ['Barangay Captain'],
    'New borrower registered',
    'New borrower registered: ' . $full_name . '.',
    'secretary/borrowers2.php',
    'walk_in_registered',
    null,
    $last_borrower_entry_id > 0 ? $last_borrower_entry_id : null
);
$sec_msg = 'Walk-in borrowing registered for ' . $full_name . '.';
if (!empty($line_summaries)) {
    $sec_msg .= ' Items: ' . implode(', ', $line_summaries) . '.';
}
bpis_notify_admins_by_roles(
    $conn,
    ['Secretary'],
    'Walk-in borrowing',
    $sec_msg,
    'secretary/borrowers2.php',
    'walk_in_issued',
    null,
    $last_borrower_entry_id > 0 ? $last_borrower_entry_id : null
);
if (!empty($treasurer_lines)) {
    bpis_notify_admins_by_roles(
        $conn,
        ['Treasurer'],
        'Stock update',
        implode(' ', $treasurer_lines),
        'treasurer/inventory.php',
        'walk_in_stock',
        null,
        $last_borrower_entry_id > 0 ? $last_borrower_entry_id : null
    );
}

$conn->close();
header("Location: borrowers2.php?status=added");
exit();