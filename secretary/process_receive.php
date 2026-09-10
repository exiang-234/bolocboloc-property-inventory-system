<?php
session_start();
include __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth_helpers.php';
require_once __DIR__ . '/../config/borrow_request_display_helpers.php';
require_once __DIR__ . '/../config/notification_helpers.php';

bpis_require_login(['Secretary', 'Barangay Captain']);

if (isset($_POST['request_id'])) {
    $request_id = (int)$_POST['request_id'];

    $fetch_stmt = $conn->prepare("SELECT * FROM borrowing_requests WHERE id = ? LIMIT 1");
    $fetch_stmt->bind_param("i", $request_id);
    $fetch_stmt->execute();
    $fetch_result = $fetch_stmt->get_result();

    if ($fetch_result && $fetch_result->num_rows > 0) {
        $data = $fetch_result->fetch_assoc();

        $full_name   = trim((string)($data['full_name'] ?? ''));
        $purok       = trim((string)($data['purok'] ?? $data['address'] ?? ''));
        $contact     = trim((string)($data['contact_number'] ?? $data['contact'] ?? ''));
        $asset_id    = (int)($data['asset_id'] ?? 0);
        $requested_qty = (int)($data['quantity'] ?? $data['qty'] ?? 0);
        
        if ($requested_qty <= 0) {
            $requested_qty = 1;
        }

        $return_qty = isset($_POST['return_qty']) ? (int)$_POST['return_qty'] : $requested_qty;
        if ($return_qty < 1) $return_qty = 1;
        if ($return_qty > $requested_qty) $return_qty = $requested_qty;

        $remaining_after = $requested_qty - $return_qty;

        $qty_col = null;
        if (array_key_exists('quantity', $data)) {
            $qty_col = 'quantity';
        } elseif (array_key_exists('qty', $data)) {
            $qty_col = 'qty';
        }

        $item_name   = bpis_borrow_request_item_label($conn, $data);
        $purpose     = trim((string)($data['purpose'] ?? ''));
        $needed_on   = trim((string)($data['date_needed'] ?? $data['needed_on'] ?? ''));
        $return_date = trim((string)($data['return_date'] ?? ''));
        
        $status = 'Received';

        $insert_stmt = $conn->prepare(
            "INSERT INTO borrower (request_id, asset_id, full_name, contact, address, qty, purpose, needed_on, return_date, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );

        if (!$insert_stmt) {
            $_SESSION['error'] = "Failed to prepare borrowers insert: " . $conn->error;
            header("Location: borrowers.php");
            exit();
        }

        $insert_stmt->bind_param(
            "iisssissss",
            $request_id, $asset_id, $full_name, $contact, $purok,
            $return_qty, $purpose, $needed_on, $return_date, $status
        );

        if ($insert_stmt->execute()) {
            $new_borrower_row_id = (int)$conn->insert_id;

            $asset_row = null;
            if ($asset_id > 0) {
                $asset_rs = mysqli_query($conn, "SELECT * FROM asset WHERE id = {$asset_id} LIMIT 1");
                if ($asset_rs && mysqli_num_rows($asset_rs) > 0) {
                    $asset_row = mysqli_fetch_assoc($asset_rs);
                }
             if ($asset_id > 0 && $return_qty > 0) {
                $deduct_stmt = $conn->prepare("
                    UPDATE asset 
                    SET quantity = quantity - ? 
                    WHERE id = ? AND quantity >= ?
                ");
                $deduct_stmt->bind_param("iii", $return_qty, $asset_id, $return_qty);
                $deduct_stmt->execute();
                $deduct_stmt->close();
            }
            }

            $snap = $asset_id > 0 ? bpis_asset_stock_snapshot($conn, $asset_id) : ['label' => $item_name, 'available' => 0];

            if ($remaining_after > 0) {

                if ($qty_col) {
                    $upd = $conn->prepare("UPDATE borrowing_requests SET `{$qty_col}` = ? WHERE id = ?");
                    $upd->bind_param("ii", $remaining_after, $request_id);
                    $upd->execute();
                    $upd->close();
                }
                $_SESSION['msg'] = "Recorded pickup of {$return_qty} unit(s). {$remaining_after} unit(s) still awaiting pickup.";
                bpis_notify_admins_by_roles(
                    $conn,
                    ['Secretary'],
                    'Partial pickup',
                    $return_qty . ' ' . $snap['label'] . ' received by ' . $full_name . '. ' . $remaining_after . ' unit(s) still approved.',
                    'secretary/borrowers2.php',
                    'item_partial_pickup',
                    $request_id,
                    $new_borrower_row_id
                );
                bpis_notify_admins_by_roles(
                    $conn,
                    ['Treasurer'],
                    'Borrowing update',
                    $return_qty . ' ' . $snap['label'] . ' picked up by ' . $full_name . '. Available stock: ' . $snap['available'] . '.',
                    'treasurer/inventory.php',
                    'stock_partial_pickup',
                    $request_id,
                    $new_borrower_row_id
                );
                bpis_notify_admins_by_roles(
                    $conn,
                    ['Barangay Captain'],
                    'Borrowing activity',
                    'Borrowing activity: partial pickup for ' . $full_name . ' (' . $return_qty . ' ' . $snap['label'] . ').',
                    'captain/borrowing_logs.php',
                    'item_partial_pickup',
                    $request_id,
                    $new_borrower_row_id
                );
            } else {

                $update_stmt = $conn->prepare("UPDATE borrowing_requests SET status = 'Received' WHERE id = ?");
                $update_stmt->bind_param("i", $request_id);
                $update_stmt->execute();
                $update_stmt->close();

                $_SESSION['msg'] = "Item(s) received by borrower. Mark as returned from Registered when items are brought back.";
                bpis_notify_admins_by_roles(
                    $conn,
                    ['Secretary'],
                    'Items picked up',
                    $return_qty . ' ' . $snap['label'] . ' received by ' . $full_name . '.',
                    'secretary/borrowers2.php',
                    'item_picked_up',
                    $request_id,
                    $new_borrower_row_id
                );
                bpis_notify_admins_by_roles(
                    $conn,
                    ['Treasurer'],
                    'Borrowing update',
                    $return_qty . ' ' . $snap['label'] . ' picked up by ' . $full_name . '. Available stock: ' . $snap['available'] . '.',
                    'treasurer/inventory.php',
                    'stock_pickup',
                    $request_id,
                    $new_borrower_row_id
                );
                bpis_notify_admins_by_roles(
                    $conn,
                    ['Barangay Captain'],
                    'Borrowing activity',
                    'Borrowing activity: ' . $full_name . ' picked up ' . $return_qty . ' ' . $snap['label'] . '.',
                    'captain/borrowing_logs.php',
                    'item_picked_up',
                    $request_id,
                    $new_borrower_row_id
                );
            }
        } else {
            $_SESSION['error'] = "Failed to save record: " . $insert_stmt->error;
        }
        $insert_stmt->close();
    } else {
        $_SESSION['error'] = "Request not found.";
    }
    $fetch_stmt->close();
    header("Location: borrowers2.php");
    exit();
}

header("Location: borrowers2.php");
exit();