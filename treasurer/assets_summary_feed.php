<?php
session_start();
include __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth_helpers.php';
require_once __DIR__ . '/../config/schema_bootstrap.php';

bpis_require_login_json(['Treasurer', 'Barangay Captain', 'Secretary']);

$total_assets = 0;
$total_value = 0.0;
$fixed = 0;
$movable = 0;
$serviceable = 0;
$under_repair = 0;
$unserviceable = 0;

$rs = mysqli_query($conn, 'SELECT COUNT(*) AS c, COALESCE(SUM(total_cost),0) AS v FROM asset');
if ($rs && ($row = mysqli_fetch_assoc($rs))) {
    $total_assets = (int) $row['c'];
    $total_value = (float) $row['v'];
}

if (bpis_column_exists($conn, 'asset', 'asset_cluster')) {
    $fr = mysqli_query($conn, "SELECT COUNT(*) AS c FROM asset WHERE asset_cluster = 'Fixed Assets'");
    if ($fr && ($r = mysqli_fetch_assoc($fr))) {
        $fixed = (int) $r['c'];
    }
    $mr = mysqli_query($conn, "SELECT COUNT(*) AS c FROM asset WHERE asset_cluster = 'Movable Assets'");
    if ($mr && ($r = mysqli_fetch_assoc($mr))) {
        $movable = (int) $r['c'];
    }
}

$sr = mysqli_query($conn, "SELECT COUNT(*) AS c FROM asset WHERE remarks = 'SERVICEABLE' OR item_condition = 'SERVICEABLE'");
if ($sr && ($r = mysqli_fetch_assoc($sr))) {
    $serviceable = (int) $r['c'];
}
$rr = mysqli_query($conn, "SELECT COUNT(*) AS c FROM asset WHERE remarks = 'UNDER REPAIR' OR item_condition = 'UNDER REPAIR'");
if ($rr && ($r = mysqli_fetch_assoc($rr))) {
    $under_repair = (int) $r['c'];
}
$ur = mysqli_query($conn, "SELECT COUNT(*) AS c FROM asset WHERE remarks = 'UNSERVICEABLE/DISPOSE' OR item_condition = 'UNSERVICEABLE/DISPOSE'");
if ($ur && ($r = mysqli_fetch_assoc($ur))) {
    $unserviceable = (int) $r['c'];
}

echo json_encode([
    'success' => true,
    'total_assets' => $total_assets,
    'total_value' => number_format($total_value, 2),
    'fixed_assets' => $fixed,
    'movable_assets' => $movable,
    'serviceable' => $serviceable,
    'under_repair' => $under_repair,
    'unserviceable' => $unserviceable,
    'updated_at' => date('Y-m-d H:i:s'),
]);
