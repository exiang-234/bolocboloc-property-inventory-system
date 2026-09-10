<?php
// Localhost DB connection — XAMPP / Laragon default (no InfinityFree)
mysqli_report(MYSQLI_REPORT_OFF);
$conn = null;
try {
    $conn = @new mysqli("127.0.0.1", "root", "", "bpis");
    if ($conn->connect_error) {
        error_log("BPIS DB Connection failed: " . $conn->connect_error);
        $conn = null;
    }
} catch (Throwable $e) {
    error_log("BPIS DB Connection exception: " . $e->getMessage());
    $conn = null;
}

require_once __DIR__ . '/asset_photo_helpers.php';
require_once __DIR__ . '/schema_bootstrap.php';
require_once __DIR__ . '/asset_list_helpers.php';
require_once __DIR__ . '/asset_units_helpers.php';
if ($conn instanceof mysqli && !$conn->connect_error) {
    try {
        bpis_ensure_asset_quantity_column($conn);
        bpis_ensure_asset_photo_column($conn);
        bpis_ensure_requirements_schema($conn);
        bpis_ensure_asset_depreciation_columns($conn);
        bpis_normalize_placeholder_brand_model_in_db($conn);
    } catch (Throwable $e) {
        error_log("BPIS schema bootstrap failed: " . $e->getMessage());
    }
}




