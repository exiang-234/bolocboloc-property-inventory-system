<?php
session_start();
include __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth_helpers.php';

bpis_require_login(['Treasurer', 'Barangay Captain']);

if (isset($_POST['save_audit'])) {
    $id = $_POST['asset_id'];
    $condition = $_POST['item_condition'];
    $current_date = date("Y-m-d");
    $unit_val = (float)$row['unit_value'];
    $short_qty = $on_hand - $qty_card;
    $short_val = $short_qty * $unit_val;

    $sql = "UPDATE asset SET item_condition = ?, date_acquired = ? WHERE id = ?";
    
    $stmt = mysqli_prepare($conn, $sql);
    
    mysqli_stmt_bind_param($stmt, "ssi", $condition, $current_date, $id);

    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['message'] = "Audit saved successfully!";
        header("Location: dashboard.php"); 
    } else {
        echo "Error updating record: " . mysqli_error($conn);
    }

    mysqli_stmt_close($stmt);
    mysqli_close($conn);
}
?>