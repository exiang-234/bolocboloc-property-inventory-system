<?php
session_start();
include __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth_helpers.php';
require_once __DIR__ . '/../config/audit_scan_helpers.php';

bpis_require_login(['Treasurer', 'Barangay Captain']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['save_audit']) || empty($_POST['audit']) || !is_array($_POST['audit'])) {
    header('Location: audit.php');
    exit;
}

foreach ($_POST['audit'] as $assetId => $row) {
    if (!is_array($row)) {
        continue;
    }
    $assetId = (int) $assetId;
    if ($assetId <= 0) {
        continue;
    }

    $qtyCard = isset($row['qty_card']) ? (int) $row['qty_card'] : 0;
    $unitVal = isset($row['unit_val_hidden']) ? (float) $row['unit_val_hidden'] : 0.0;
    $actual = isset($row['actual_count']) ? (int) $row['actual_count'] : 0;
    $diffQty = isset($row['diff_qty']) ? (int) $row['diff_qty'] : ($actual - $qtyCard);
    $shortVal = round($diffQty * $unitVal, 2);

    $chk = mysqli_prepare($conn, 'SELECT id FROM asset WHERE id = ? LIMIT 1');
    if (!$chk) {
        continue;
    }
    mysqli_stmt_bind_param($chk, 'i', $assetId);
    mysqli_stmt_execute($chk);
    $res = mysqli_stmt_get_result($chk);
    if (!$res || mysqli_num_rows($res) === 0) {
        mysqli_stmt_close($chk);
        continue;
    }
    mysqli_stmt_close($chk);

    $sql = 'UPDATE asset SET on_hand_qty = ?, shortage_overage_qty = ?, shortage_overage_value = ? WHERE id = ?';
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        continue;
    }
    mysqli_stmt_bind_param($stmt, 'iidi', $actual, $diffQty, $shortVal, $assetId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

mysqli_close($conn);
header('Location: audit.php?success=1');
exit;
