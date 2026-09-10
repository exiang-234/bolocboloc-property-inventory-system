
<?php
session_start();
include __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth_helpers.php';
require_once __DIR__ . '/../config/table_date_filter_helpers.php';

bpis_require_login(['Treasurer', 'Barangay Captain']);

$user_fullname = 'User';
$user_role = (string) ($_SESSION['role'] ?? '');
$admin_id = (int) $_SESSION['admin_id'];
$query = "SELECT fullname, role FROM admin WHERE id = '$admin_id'";
$result = mysqli_query($conn, $query);
if ($result && mysqli_num_rows($result) > 0) {
    $user = mysqli_fetch_assoc($result);
    $user_fullname = $user['fullname'];
    $user_role = $user['role'];
}
include __DIR__ . '/../includes/bpis_resolve_header_profile.php';

function calculate_accumulated_depreciation($acquisition_date, $total_cost) {
    if (empty($acquisition_date) || $total_cost <= 0) return 0;
    
    $acquisition_year = date('Y', strtotime($acquisition_date));
    $current_year = date('Y');
    $years_passed = $current_year - $acquisition_year;
    
    if ($years_passed <= 0) return 0;
    
    $useful_life = 5;
    $annual_depreciation = $total_cost / $useful_life;
    $accumulated = $annual_depreciation * $years_passed;
    
    return min($accumulated, $total_cost);
}

function calculate_appraised_value($net_book_value) {
    return $net_book_value * 0.20;
}

function get_unserviceable_quantity($conn, $asset_id, $total_qty) {
    $query = "SELECT COUNT(*) as unserviceable_count FROM asset_units 
              WHERE asset_id = $asset_id 
              AND UPPER(TRIM(COALESCE(unit_condition,''))) IN ('UNSERVICEABLE', 'UNSERVICEABLE/DISPOSE', 'DISPOSED', 'DISPOSE', 'UNSERVICEABLE/DISPOSED')";
    $result = mysqli_query($conn, $query);
    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        if ($row['unserviceable_count'] > 0) {
            return (int)$row['unserviceable_count'];
        }
    }
    
    $query2 = "SELECT COUNT(*) as unserviceable_count FROM asset_units 
               WHERE asset_id = $asset_id 
               AND LOWER(TRIM(COALESCE(unit_condition,''))) LIKE '%unserviceable%'";
    $result2 = mysqli_query($conn, $query2);
    if ($result2 && mysqli_num_rows($result2) > 0) {
        $row2 = mysqli_fetch_assoc($result2);
        if ($row2['unserviceable_count'] > 0) {
            return (int)$row2['unserviceable_count'];
        }
    }
    
    if ($total_qty == 1) {
        $remarks_query = "SELECT remarks, item_condition FROM asset WHERE id = $asset_id";
        $remarks_result = mysqli_query($conn, $remarks_query);
        if ($remarks_result && mysqli_num_rows($remarks_result) > 0) {
            $asset_row = mysqli_fetch_assoc($remarks_result);
            $remarks = strtoupper(trim($asset_row['remarks'] ?? ''));
            $item_condition = strtoupper(trim($asset_row['item_condition'] ?? ''));
            if (in_array($remarks, ['UNSERVICEABLE', 'UNSERVICEABLE/DISPOSE', 'DISPOSED', 'DISPOSE']) ||
                in_array($item_condition, ['UNSERVICEABLE', 'DISPOSE', 'UNSERVICEABLE/DISPOSE']) ||
                strpos($remarks, 'UNSERVICEABLE') !== false) {
                return $total_qty;
            }
        }
    }
    
    return 0;
}

$save_message = '';
$save_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_audit'])) {
    if (isset($_POST['audit']) && is_array($_POST['audit'])) {
        foreach ($_POST['audit'] as $asset_id => $data) {
            $asset_id = (int)$asset_id;
            $accumulated_depreciation = isset($data['accumulated_depreciation']) ? (float)$data['accumulated_depreciation'] : null;
            $audit_remarks = isset($data['audit_remarks']) ? mysqli_real_escape_string($conn, trim($data['audit_remarks'])) : null;
            
            $update_fields = [];
            if ($accumulated_depreciation !== null) {
                $update_fields[] = "accumulated_depreciation = $accumulated_depreciation";
            }
            if ($audit_remarks !== null) {
                $update_fields[] = "audit_remarks = '$audit_remarks'";
            }
            
            if (!empty($update_fields)) {
                $update_query = "UPDATE asset SET " . implode(', ', $update_fields) . " WHERE id = $asset_id";
                if (mysqli_query($conn, $update_query)) {
                    $save_message = "Changes saved successfully!";
                } else {
                    $save_error = "Error saving changes: " . mysqli_error($conn);
                }
            }
        }
    } else {
        $save_error = "No data to save.";
    }
}

$assets = [];
$total_unserviceable_qty = 0;
$total_unserviceable_value = 0;

$asset_query = "SELECT * FROM asset ORDER BY id DESC";

if (isset($conn)) {
    $asset_result = mysqli_query($conn, $asset_query);

    if ($asset_result && mysqli_num_rows($asset_result) > 0) {
        while ($row = mysqli_fetch_assoc($asset_result)) {
            $total_qty = isset($row['quantity']) ? (int)$row['quantity'] : 1;
            $unit_val_numeric = (float)$row['unit_value'];
            
            $unserviceable_qty = get_unserviceable_quantity($conn, $row['id'], $total_qty);
            
            if ($unserviceable_qty == 0) {
                continue;
            }
            
            $total_cost = $unserviceable_qty * $unit_val_numeric;
            
            $total_unserviceable_qty += $unserviceable_qty;
            $total_unserviceable_value += $total_cost;
            
            $db_acc_depreciation = isset($row['accumulated_depreciation']) && $row['accumulated_depreciation'] > 0 
                ? (float)$row['accumulated_depreciation'] 
                : calculate_accumulated_depreciation($row['date_acquired'], $total_cost);
            
            $net_book_value = $total_cost - $db_acc_depreciation;
            $appraised_value = calculate_appraised_value($net_book_value);
            
            $db_audit_remarks = isset($row['audit_remarks']) && !empty($row['audit_remarks']) 
                ? $row['audit_remarks'] 
                : 'UNSERVICEABLE';

            $assets[] = [
                "id" => (int)$row['id'],
                "date_acquired" => !empty($row['date_acquired']) 
                    ? date("Y", strtotime($row['date_acquired'])) 
                    : 'N/A',
                "date_acquired_raw" => $row['date_acquired'] ?? '',
                "article" => $row['article'],
                "description" => $row['description'],
                "prop_num" => $row['property_number'],
                "qty" => $unserviceable_qty,
                "total_qty" => $total_qty,
                "total_cost" => $total_cost,
                "acc_depreciation" => $db_acc_depreciation,
                "net_book_value" => $net_book_value,
                "appraised_value" => $appraised_value,
                "audit_remarks" => $db_audit_remarks,
            ];
        }
    }
}

$iirup_meta = [
    'barangay' => 'BOLOCBOLOC',
    'city_municipality' => 'SIBULAN',
    'province' => 'NEGROS ORIENTAL',
    'as_of' => date('F Y'),
    'accountable_officer' => 'VIRGIELYN U. GATELA',
    'accountable_designation' => 'BRGY. TREASURER',
    'prepared_by' => $user_fullname,
    'prepared_title' => 'Barangay Record Keeper',
    'city_accountant' => '',
    'punong_barangay' => '',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" href="<?= bpis_app_base_path() ?>/images/logo.png">
    <link rel="stylesheet" href="../css/table_date_filter.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="../css/profile_dropdown.css"><link rel="stylesheet" href="../css/sidebar_nav_transition.css">
    <script src="../js/sidebar_nav_transition.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php include __DIR__ . '/../includes/bpis_app_meta.php'; ?>
    <title>Inventory & Inspection Audit Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
<style>
    * { 
        margin: 0; 
        padding: 0; 
        box-sizing: border-box; 
        font-family: 'Segoe UI', Roboto, Arial, sans-serif; 
    }
    body { 
        background-color: #F6F8FC; 
        display: flex; 
        height: 100vh; 
        color: #333; 
        overflow: hidden; 
    }
    .sidebar { 
        width: 230px; 
        background: linear-gradient(180deg, #0f3f69 0%, #174c7d 45%, #1d5f94 100%); 
        color: white; 
        display: flex; 
        flex-direction: column; 
        justify-content: space-between; 
        padding: 25px 15px; 
        position: fixed; 
        height: 100vh; 
        z-index: 10;
        border-right: 1px solid rgba(255, 255, 255, 0.18);
        box-shadow: 6px 0 24px rgba(12, 34, 56, 0.22); 
    }
    .sidebar-top { 
        text-align: center; 
    }
    .logo-container { 
        margin-bottom: 15px; 
        display: flex; 
        justify-content: center; 
    }
    .logo-img { 
        width: 70px; 
        height: 70px; 
        border-radius: 50%; 
        object-fit: contain; 
    }
    .sidebar h2 { 
        font-size: 16px; 
        font-weight: 600; 
        line-height: 1.2; 
        margin-bottom: 30px; 
        letter-spacing: 0.5px; 
    }
    .sidebar-nav { 
        display: flex; 
        flex-direction: column; 
        gap: 12px; 
    }
    .nav-item { 
        display: block; 
        color: white; 
        text-decoration: none; 
        padding: 11px 14px; 
        border-radius: 12px; 
        font-size: 13px; 
        font-weight: 600; 
        background: rgba(255, 255, 255, 0.08); 
        border: 1px solid rgba(255, 255, 255, 0.14);
        transition: all 0.2s ease; 
    }
    .nav-item i { width: 18px; margin-right: 10px; text-align: center; }
    .nav-item:hover, .nav-item.active { 
        background: linear-gradient(90deg, #3b82f6, #2563eb); 
        border-color: rgba(255, 255, 255, 0.35);
        transform: translateX(2px);
    }
    .nav-item.active {
        border-color: rgba(255, 255, 255, 0.4);
        box-shadow: 0 8px 18px rgba(37, 99, 235, 0.32);
        font-weight: 700;
    }
    .sidebar-bottom { 
        font-size: 11px; 
        text-align: center; 
        opacity: 0.88; 
        color: rgba(255, 255, 255, 0.9);
        line-height: 1.4; 
    }
    .main-content { 
        flex: 1; 
        margin-left: 230px; 
        padding: 25px 35px; 
        display: flex; 
        flex-direction: column; 
        height: 100vh; 
        min-width: 0; 
    }
    .header p { 
        font-size: 13px; 
        color: #666; 
    }
    .header { 
        display: flex; 
        justify-content: space-between; 
        align-items: center;
        margin-bottom: 25px; 
    }
    .header h1 { 
        font-size: 22px; 
        font-weight: 700; 
        color: #111; 
    }
    .header-icons { 
        display: flex; 
        align-items: center; 
        gap: 20px; 
        position: relative; 
    }
    .icon-box { 
        width: 32px; 
        height: 32px; 
        cursor: pointer; 
        object-fit: contain; 
    }
    .icon-circle { 
        width: 42px; 
        height: 42px; 
        cursor: pointer; 
        object-fit: cover; 
        border-radius: 50%; 
        border: 2px solid transparent; 
        transition: border 0.2s; 
    }
    .profile-dropdown { 
        display: none; 
        position: absolute; 
        top: 55px; 
        right: 0; 
        width: 220px; 
        background-color: #174C7D; 
        border-radius: 12px; 
        box-shadow: 0 10px 25px rgba(0,0,0,0.15); 
        z-index: 1000; 
        overflow: hidden; 
        color: white; 
        padding-top: 20px; 
        animation: fadeIn 0.2s ease-out; 
    }
    @keyframes fadeIn { 
        from { 
            opacity: 0; 
            transform: translateY(-10px); 
        } to { 
            opacity: 1; 
            transform: translateY(0); 
        } 
    }
    .profile-dropdown.active { 
        display: block; 
    }
    .profile-user-info { 
        text-align: center; 
        padding-bottom: 15px; 
        border-bottom: 1px solid rgba(255, 255, 255, 0.1); 
    }
    .profile-user-info img { 
        width: 60px; 
        height: 60px; 
        border-radius: 50%; 
        background-color: white; 
        padding: 2px; 
        margin-bottom: 10px; 
        object-fit: cover; 
        display: inline-block; 
    }
    .profile-user-info h4 { 
        font-size: 14px; 
        font-weight: 600; 
        margin-bottom: 2px; 
    }
    .profile-links a { 
        display: block; 
        padding: 12px 20px; 
        color: white; 
        text-decoration: none; 
        font-size: 13px; 
        text-align: center; 
        transition: background 0.2s; 
    }
    .profile-links a:hover { 
        background-color: #1e3a8a; 
    }
    .profile-links .sign-out { 
        color: #f9e8ea; 
        font-weight: 600; 
    }
    .profile-links .sign-out:hover { 
        background-color: #991b1b; 
        color: white; 
    }
    .custom-scrollbar::-webkit-scrollbar { 
        width: 8px; 
        height: 8px; 
    }
    .custom-scrollbar::-webkit-scrollbar-track { 
        background: #f1f5f9; 
        border-radius: 8px; 
    }
    .custom-scrollbar::-webkit-scrollbar-thumb { 
        background: #cbd5e1; 
        border-radius: 8px; 
    }
    .audit-input { 
        width: 100%; 
        background: transparent; 
        border: 1px solid #e2e8f0; 
        border-radius: 4px; 
        padding: 4px; 
        text-align: center; 
        font-size: 12px; 
        outline: none; 
        transition: border-color 0.2s; 
    }
    .audit-input:focus { 
        border-color: #3B82F6; 
        background: #fff; 
    }
    .readonly-input { 
        background-color: #f8fafc; 
        border: 1px solid #e2e8f0; 
        cursor: not-allowed; 
    }
    .nav-link { 
        font-size: 14px; 
        font-weight: 600; 
        padding-bottom: 8px; 
        position: relative; 
        transition: color 0.3s; 
    }
    .nav-link.active { 
        color: #174C7D; 
        border-bottom: 3px solid #174C7D; 
    }
    .nav-link.inactive { 
        color: #94a3b8; 
    }
    .nav-link.inactive:hover { 
        color: #64748b; 
    }
    .summary-card {
        background: linear-gradient(135deg, #174C7D 0%, #1d5f94 100%);
        border-radius: 12px;
        padding: 15px 20px;
        margin-bottom: 20px;
        color: white;
        display: flex;
        justify-content: space-between;
        align-items: center;
        box-shadow: 0 4px 15px rgba(23, 76, 125, 0.25);
    }
    .summary-card h3 {
        font-size: 14px;
        font-weight: 600;
        margin-bottom: 5px;
        opacity: 0.9;
    }
    .summary-card .value {
        font-size: 26px;
        font-weight: 700;
    }
    .summary-card .label {
        font-size: 11px;
        opacity: 0.8;
        letter-spacing: 0.3px;
    }
    .alert-success {
        background-color: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
        border-radius: 8px;
        padding: 12px 20px;
        margin-bottom: 20px;
        font-size: 14px;
    }
    .alert-error {
        background-color: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
        border-radius: 8px;
        padding: 12px 20px;
        margin-bottom: 20px;
        font-size: 14px;
    }
    #iirupPrintArea {
        display: none;
    }

    #iirupPrintArea .iirup-doc {
        font-family: 'Times New Roman', Times, serif;
        color: #000;
        font-size: 11px;
        line-height: 1.4;
    }

    #iirupPrintArea .iirup-topline {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 8px;
        font-size: 11px;
        text-transform: uppercase;
    }

    #iirupPrintArea .iirup-topline .lbl { font-weight: 700; }

    #iirupPrintArea .iirup-title-wrap {
        text-align: center;
        margin: 8px 0 12px;
    }

    #iirupPrintArea .iirup-title-wrap h1 {
        font-size: 16px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.02em;
        margin: 0 0 4px;
    }

    #iirupPrintArea .iirup-title-wrap .as-of {
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
        margin: 0;
    }

    #iirupPrintArea .iirup-accountability {
        text-align: center;
        font-size: 11px;
        margin: 0 0 12px;
        padding: 0 8px;
    }

    #iirupPrintArea .iirup-accountability strong { font-weight: 700; }

    #iirupPrintArea table.iirup-pdf-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 6px;
        font-size: 11px;
        table-layout: fixed;
    }

    #iirupPrintArea table.iirup-pdf-table th,
    #iirupPrintArea table.iirup-pdf-table td {
        border: 1px solid #000;
        padding: 3px 4px;
        text-align: center;
        vertical-align: middle;
        word-wrap: break-word;
        font-size: 10px;
        line-height: 1.3;
    }

    #iirupPrintArea table.iirup-pdf-table td {
        font-size: 10px;
    }

    #iirupPrintArea table.iirup-pdf-table th {
        font-size: 11px;
        font-weight: 700;
    }
    #iirupPrintArea table.iirup-pdf-table thead th {
        background: #fff;
        font-weight: 700;
        text-transform: uppercase;
        text-align: center;
        font-size: 10px;
    }

    #iirupPrintArea table.iirup-pdf-table thead tr.iirup-col-num th {
        font-size: 9px;
        padding: 2px 3px;
    }

    #iirupPrintArea table.iirup-pdf-table .ta-l { text-align: left; }
    #iirupPrintArea table.iirup-pdf-table .ta-c { text-align: center; }
    #iirupPrintArea table.iirup-pdf-table .ta-r { text-align: right; }

    #iirupPrintArea table.iirup-sig-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 18px;
        font-size: 11px;
        table-layout: fixed;
    }

    #iirupPrintArea table.iirup-sig-table td {
        border: none;
        vertical-align: top;
        padding: 8px 10px;
        width: 25%;
        text-align: center;
    }

    #iirupPrintArea .iirup-sig-label {
        font-weight: 700;
        margin-bottom: 6px;
        text-transform: none;
    }

    #iirupPrintArea .iirup-sig-cert {
        font-size: 10px;
        line-height: 1.3;
        text-align: center;
        margin-bottom: 10px;
        min-height: 2.6em;
    }

    #iirupPrintArea .iirup-sig-name {
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        border-bottom: 1px solid #000;
        min-height: 18px;
        margin: 0 6px 6px;
        padding-bottom: 2px;
    }

    #iirupPrintArea .iirup-sig-sub,
    #iirupPrintArea .iirup-sig-role,
    #iirupPrintArea .iirup-sig-date {
        font-size: 10px;
        margin-top: 4px;
    }
    #iirupPrintArea .iirup-approved-wrap {
        margin-top: 20px;
        text-align: center;
        font-size: 11px;
    }

    #iirupPrintArea .iirup-approved-wrap .iirup-sig-label { margin-bottom: 8px; }

    #iirupPrintArea .iirup-approved-inner {
        display: inline-block;
        min-width: 280px;
        text-align: center;
    }

    @media print {
        @page {
            size: landscape;
            margin: 10mm;
        }
        body * {
            visibility: hidden !important;
        }
        #iirupPrintArea,
        #iirupPrintArea * {
            visibility: visible !important;
        }
        #iirupPrintArea {
            display: block !important;
            position: absolute !important;
            left: 0 !important;
            top: 0 !important;
            width: 100% !important;
            background: #fff !important;
            padding: 6mm 8mm !important;
            box-sizing: border-box !important;
        }
    }
    table { 
        width: 100%; 
        border-collapse: collapse; 
        font-size: 12px; 
    }
    thead { 
        text-align: center; 
        color: #888; 
        padding: 7px; 
        font-weight: 600; 
        text-transform: uppercase; 
        font-size: 13px; 
        border-bottom: 1px solid #eee; 
    }
    td { 
        text-align: center; 
        padding: 7px 9px; 
        border-bottom: 1px solid #f9f9f9; 
        color: #444; 
        vertical-align: middle; 
        font-size:12px;
    }

    @media (max-width: 1200px) {
        .sidebar {
            width: 210px !important;
            padding: 22px 14px !important;
        }
        .sidebar h2 {
            font-size: 15px !important;
            margin-bottom: 24px !important;
        }
        .main-content {
            padding: 22px 28px !important;
        }
        .summary-card .value {
            font-size: 24px !important;
        }
        .summary-card {
            padding: 12px 16px !important;
        }
    }

    @media (max-width: 1024px) {
        .sidebar {
            width: 200px !important;
            padding: 20px 12px !important;
        }
        .sidebar h2 {
            font-size: 14px !important;
            margin-bottom: 20px !important;
        }
        .nav-item {
            font-size: 12px !important;
            padding: 10px 12px !important;
        }
        .main-content {
            margin-left: 200px !important;
            padding: 20px 25px !important;
        }
        .header h1 {
            font-size: 20px !important;
        }
        .logo-img {
            width: 60px !important;
            height: 60px !important;
        }
        .icon-circle {
            width: 38px !important;
            height: 38px !important;
        }
        .icon-box {
            width: 28px !important;
            height: 28px !important;
        }
        .summary-card .value {
            font-size: 22px !important;
        }
        .summary-card h3 {
            font-size: 13px !important;
        }
        .custom-scrollbar::-webkit-scrollbar {
            width: 6px !important;
            height: 6px !important;
        }
        #iirupPrintArea .iirup-title-wrap h1 {
            font-size: 14px !important;
        }
    }

    @media (max-width: 900px) {
        .sidebar {
            width: 180px !important;
            padding: 16px 10px !important;
        }
        .sidebar h2 {
            font-size: 13px !important;
            margin-bottom: 16px !important;
        }
        .nav-item {
            font-size: 11px !important;
            padding: 8px 10px !important;
            border-radius: 8px !important;
        }
        .nav-item i {
            width: 16px !important;
            font-size: 13px !important;
            margin-right: 8px !important;
        }
        .main-content {
            margin-left: 180px !important;
            padding: 16px 20px !important;
        }
        .header h1 {
            font-size: 19px !important;
        }
        .logo-img {
            width: 52px !important;
            height: 52px !important;
        }
        .summary-card {
            flex-direction: column !important;
            align-items: flex-start !important;
            gap: 8px !important;
            padding: 12px 16px !important;
        }
        .summary-card .value {
            font-size: 20px !important;
        }
        #iirupPrintArea .iirup-topline {
            font-size: 10px !important;
            flex-direction: column !important;
            align-items: stretch !important;
            gap: 4px !important;
        }
        #iirupPrintArea .iirup-title-wrap h1 {
            font-size: 13px !important;
        }
        #iirupPrintArea .iirup-title-wrap .as-of {
            font-size: 10px !important;
        }
        #iirupPrintArea table.iirup-pdf-table th,
        #iirupPrintArea table.iirup-pdf-table td {
            font-size: 8px !important;
            padding: 2px 3px !important;
        }
        #iirupPrintArea table.iirup-pdf-table thead th {
            font-size: 8px !important;
        }
        #iirupPrintArea table.iirup-sig-table td {
            padding: 4px 6px !important;
        }
        #iirupPrintArea .iirup-sig-name {
            font-size: 9px !important;
            min-height: 14px !important;
        }
        #iirupPrintArea .iirup-sig-role {
            font-size: 8px !important;
        }
        @media print {
            @page {
                size: portrait !important;
                margin: 6mm !important;
            }
            #iirupPrintArea {
                padding: 4mm 4mm !important;
            }
            #iirupPrintArea table.iirup-pdf-table {
                font-size: 8px !important;
            }
            #iirupPrintArea table.iirup-pdf-table th,
            #iirupPrintArea table.iirup-pdf-table td {
                font-size: 7px !important;
                padding: 1px 2px !important;
            }
        }
    }

    @media (max-width: 768px) {
        .sidebar {
            width: 60px !important;
            padding: 15px 8px !important;
        }
        .sidebar h2 {
            display: none !important;
        }
        .sidebar-bottom {
            display: none !important;
        }
        .logo-img {
            width: 40px !important;
            height: 40px !important;
        }
        .nav-item {
            font-size: 11px !important;
            padding: 8px 6px !important;
            text-align: center !important;
            border-radius: 8px !important;
        }
        .nav-item i {
            margin-right: 0 !important;
            width: 20px !important;
            font-size: 16px !important;
        }
        .nav-item span {
            display: none !important;
        }
        .nav-item:hover,
        .nav-item.active {
            transform: none !important;
        }
        .main-content {
            margin-left: 60px !important;
            padding: 15px 15px !important;
        }
        .header {
            flex-wrap: wrap !important;
            gap: 10px !important;
            margin-bottom: 18px !important;
        }
        .header h1 {
            font-size: 18px !important;
        }
        .header p {
            font-size: 12px !important;
        }
        .header-icons {
            gap: 12px !important;
        }
        .icon-circle {
            width: 34px !important;
            height: 34px !important;
        }
        .icon-box {
            width: 26px !important;
            height: 26px !important;
        }
        .profile-dropdown {
            width: 180px !important;
            right: -10px !important;
        }
        .profile-user-info img {
            width: 48px !important;
            height: 48px !important;
        }
        .profile-user-info h4 {
            font-size: 13px !important;
        }
        .profile-links a {
            font-size: 12px !important;
            padding: 10px 16px !important;
        }
        .summary-card {
            flex-direction: column !important;
            align-items: flex-start !important;
            gap: 8px !important;
            padding: 12px 14px !important;
            border-radius: 10px !important;
        }
        .summary-card .value {
            font-size: 22px !important;
        }
        .summary-card h3 {
            font-size: 13px !important;
        }
        .summary-card .label {
            font-size: 10px !important;
        }
        .alert-success,
        .alert-error {
            font-size: 13px !important;
            padding: 10px 16px !important;
        }
        .nav-link {
            font-size: 12px !important;
            padding-bottom: 6px !important;
        }
        #iirupPrintArea .iirup-title-wrap h1 {
            font-size: 12px !important;
        }
        #iirupPrintArea table.iirup-pdf-table th,
        #iirupPrintArea table.iirup-pdf-table td {
            font-size: 7px !important;
            padding: 2px !important;
        }
        #iirupPrintArea table.iirup-pdf-table thead th {
            font-size: 7px !important;
        }
        #iirupPrintArea table.iirup-sig-table td {
            padding: 4px 4px !important;
        }
        #iirupPrintArea .iirup-sig-name {
            font-size: 8px !important;
            min-height: 12px !important;
        }
        #iirupPrintArea .iirup-sig-role {
            font-size: 7px !important;
        }
        td {
            font-size: 11px !important;
            padding: 6px 6px !important;
        }
        thead {
            font-size: 12px !important;
            padding: 5px !important;
        }
        @media print {
            @page {
                size: portrait !important;
                margin: 5mm !important;
            }
            #iirupPrintArea {
                padding: 3mm 3mm !important;
            }
        }
    }

    @media (max-width: 600px) {
        .sidebar {
            width: 55px !important;
            padding: 12px 6px !important;
        }
        .logo-img {
            width: 34px !important;
            height: 34px !important;
        }
        .nav-item {
            font-size: 10px !important;
            padding: 6px 4px !important;
            border-radius: 6px !important;
        }
        .nav-item i {
            font-size: 14px !important;
            width: 18px !important;
        }
        .main-content {
            margin-left: 55px !important;
            padding: 12px 12px !important;
        }
        .header h1 {
            font-size: 17px !important;
        }
        .header p {
            font-size: 11px !important;
        }
        .header-icons {
            gap: 10px !important;
        }
        .icon-circle {
            width: 30px !important;
            height: 30px !important;
        }
        .icon-box {
            width: 24px !important;
            height: 24px !important;
        }
        .summary-card {
            padding: 10px 12px !important;
            border-radius: 8px !important;
        }
        .summary-card .value {
            font-size: 20px !important;
        }
        .summary-card h3 {
            font-size: 12px !important;
        }
        .alert-success,
        .alert-error {
            font-size: 12px !important;
            padding: 8px 12px !important;
        }
        .audit-input {
            font-size: 11px !important;
            padding: 2px !important;
        }
        td {
            font-size: 10px !important;
            padding: 5px 4px !important;
        }
        thead {
            font-size: 11px !important;
            padding: 5px !important;
        }
    }

    @media (max-width: 480px) {
        .sidebar {
            width: 50px !important;
            padding: 10px 4px !important;
        }
        .logo-img {
            width: 30px !important;
            height: 30px !important;
        }
        .nav-item {
            font-size: 10px !important;
            padding: 6px 4px !important;
            border-radius: 6px !important;
        }
        .nav-item i {
            font-size: 14px !important;
            width: 18px !important;
        }
        .main-content {
            margin-left: 50px !important;
            padding: 10px 10px !important;
        }
        .header {
            flex-direction: column !important;
            align-items: flex-start !important;
            gap: 8px !important;
            margin-bottom: 14px !important;
        }
        .header h1 {
            font-size: 16px !important;
        }
        .header p {
            font-size: 11px !important;
        }
        .header-icons {
            align-self: flex-end !important;
            gap: 8px !important;
        }
        .icon-circle {
            width: 28px !important;
            height: 28px !important;
        }
        .icon-box {
            width: 22px !important;
            height: 22px !important;
        }
        .profile-dropdown {
            width: 160px !important;
            right: -5px !important;
            top: 48px !important;
        }
        .profile-user-info img {
            width: 40px !important;
            height: 40px !important;
        }
        .profile-user-info h4 {
            font-size: 12px !important;
        }
        .profile-links a {
            font-size: 11px !important;
            padding: 8px 14px !important;
        }
        .summary-card {
            flex-direction: row !important;
            justify-content: space-between !important;
            align-items: center !important;
            padding: 8px 12px !important;
            border-radius: 8px !important;
        }
        .summary-card .value {
            font-size: 18px !important;
        }
        .summary-card h3 {
            font-size: 11px !important;
        }
        .nav-link {
            font-size: 11px !important;
            padding-bottom: 4px !important;
        }
        .custom-scrollbar::-webkit-scrollbar {
            width: 4px !important;
            height: 4px !important;
        }
        #iirupPrintArea .iirup-title-wrap h1 {
            font-size: 11px !important;
        }
        #iirupPrintArea table.iirup-pdf-table {
            font-size: 8px !important;
        }
        #iirupPrintArea table.iirup-pdf-table th,
        #iirupPrintArea table.iirup-pdf-table td {
            font-size: 6px !important;
            padding: 1px 2px !important;
        }
        #iirupPrintArea table.iirup-pdf-table thead th {
            font-size: 6px !important;
        }
        #iirupPrintArea .iirup-sig-name {
            font-size: 7px !important;
            min-height: 10px !important;
        }
        #iirupPrintArea .iirup-sig-role {
            font-size: 6px !important;
        }
        #iirupPrintArea .iirup-sig-cert {
            font-size: 8px !important;
            min-height: 2em !important;
        }
        @media print {
            #iirupPrintArea table.iirup-pdf-table th,
            #iirupPrintArea table.iirup-pdf-table td {
                font-size: 6px !important;
            }
            #iirupPrintArea .iirup-title-wrap h1 {
                font-size: 10px !important;
            }
        }
    }

    @media (max-width: 400px) {
        .sidebar {
            width: 45px !important;
            padding: 8px 3px !important;
        }
        .logo-img {
            width: 26px !important;
            height: 26px !important;
        }
        .nav-item {
            font-size: 9px !important;
            padding: 5px 3px !important;
            border-radius: 4px !important;
        }
        .nav-item i {
            font-size: 12px !important;
            width: 16px !important;
        }
        .main-content {
            margin-left: 45px !important;
            padding: 8px 8px !important;
        }
        .header h1 {
            font-size: 15px !important;
        }
        .header p {
            font-size: 10px !important;
        }
        .header-icons {
            gap: 6px !important;
        }
        .icon-circle {
            width: 26px !important;
            height: 26px !important;
        }
        .icon-box {
            width: 20px !important;
            height: 20px !important;
        }
        .summary-card {
            padding: 6px 10px !important;
        }
        .summary-card .value {
            font-size: 16px !important;
        }
        .summary-card h3 {
            font-size: 10px !important;
        }
        .summary-card .label {
            font-size: 9px !important;
        }
        td {
            font-size: 9px !important;
            padding: 4px 3px !important;
        }
        thead {
            font-size: 10px !important;
            padding: 4px !important;
        }
    }

    @media (max-width: 360px) {
        .sidebar {
            width: 40px !important;
            padding: 8px 2px !important;
        }
        .logo-img {
            width: 24px !important;
            height: 24px !important;
        }
        .nav-item {
            font-size: 9px !important;
            padding: 4px 2px !important;
            border-radius: 4px !important;
        }
        .nav-item i {
            font-size: 12px !important;
            width: 16px !important;
        }
        .main-content {
            margin-left: 40px !important;
            padding: 8px 6px !important;
        }
        .header h1 {
            font-size: 14px !important;
        }
        .header p {
            font-size: 10px !important;
        }
        .header-icons {
            gap: 6px !important;
        }
        .icon-circle {
            width: 24px !important;
            height: 24px !important;
        }
        .icon-box {
            width: 18px !important;
            height: 18px !important;
        }
        .profile-dropdown {
            width: 140px !important;
            right: 0 !important;
            top: 42px !important;
            padding-top: 14px !important;
        }
        .profile-user-info img {
            width: 34px !important;
            height: 34px !important;
        }
        .profile-user-info h4 {
            font-size: 11px !important;
        }
        .profile-links a {
            font-size: 10px !important;
            padding: 6px 12px !important;
        }
        .summary-card {
            padding: 4px 8px !important;
        }
        .summary-card .value {
            font-size: 14px !important;
        }
        .summary-card h3 {
            font-size: 9px !important;
        }
        .alert-success,
        .alert-error {
            font-size: 11px !important;
            padding: 6px 10px !important;
        }
        #iirupPrintArea table.iirup-pdf-table {
            font-size: 6px !important;
        }
        #iirupPrintArea table.iirup-pdf-table th,
        #iirupPrintArea table.iirup-pdf-table td {
            font-size: 5px !important;
            padding: 1px !important;
        }
        #iirupPrintArea .iirup-title-wrap h1 {
            font-size: 10px !important;
        }
        @media print {
            #iirupPrintArea {
                padding: 2mm !important;
            }
            #iirupPrintArea table.iirup-pdf-table th,
            #iirupPrintArea table.iirup-pdf-table td {
                font-size: 5px !important;
            }
        }
    }

    @media (max-width: 768px) {
        .main-content > .flex.justify-between.items-center.pt-6 {
            flex-direction: column !important;
            align-items: stretch !important;
            gap: 12px !important;
            padding-top: 12px !important;
            margin-bottom: 14px !important;
        }
        .main-content > .flex.justify-between.items-center.pt-6 > .flex {
            flex-wrap: wrap !important;
            gap: 10px !important;
        }
        .main-content > .flex.justify-between.items-center.pt-6 > .flex:last-child {
            flex-direction: column !important;
            align-items: stretch !important;
        }
        .main-content > .flex.justify-between.items-center.pt-6 .relative.w-\[300px\] {
            width: 100% !important;
        }
        #toggleEditBtn,
        #btnExportIirupPdf {
            width: 100% !important;
            justify-content: center !important;
        }
        #audit2Table {
            min-width: 1040px !important;
            width: max-content !important;
            font-size: 12px !important;
            line-height: 1.35 !important;
        }
        #audit2Table thead,
        #audit2Table th {
            font-size: 11px !important;
            line-height: 1.25 !important;
        }
        #audit2Table th {
            padding: 9px 8px !important;
        }
        #audit2Table tbody,
        #audit2Table td {
            font-size: 12px !important;
            line-height: 1.35 !important;
        }
        #audit2Table td {
            padding: 10px 8px !important;
        }
        #audit2Table .audit-input {
            min-width: 110px !important;
            font-size: 12px !important;
            line-height: 1.3 !important;
            padding: 6px 7px !important;
        }
        #audit2Table td:nth-child(8) {
            min-width: 240px !important;
        }
    }

    @media (max-width: 480px) {
        #audit2Table {
            min-width: 980px !important;
        }
        #audit2Table thead,
        #audit2Table th {
            font-size: 10px !important;
        }
        #audit2Table tbody,
        #audit2Table td,
        #audit2Table .audit-input {
            font-size: 11px !important;
        }
        #audit2Table th {
            padding: 8px 7px !important;
        }
        #audit2Table td {
            padding: 9px 7px !important;
        }
    }
</style>
    <link rel="stylesheet" href="../css/treasurer_mobile_sidebar.css">
    <link rel="stylesheet" href="../css/logout_modal.css">

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { theme: { extend: { colors: { brand: { 600: '#2563eb', 700: '#1d4ed8' } } } } };
    </script>
    <script src="../tailwind_blue_theme.js"></script>
</head>
<body data-bpis-user="<?= htmlspecialchars($user_fullname, ENT_QUOTES, 'UTF-8') ?>">
    <script type="application/json" id="bpisIirupMeta"><?php
        echo json_encode($iirup_meta, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
    ?></script>
<?php include __DIR__ . '/../includes/bpis_logout_modal.php'; ?>

    <div class="sidebar">
        <div class="sidebar-top">
            <button type="button" id="mobileNavClose" class="mobile-nav-close" aria-label="Close navigation">
                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </button>
            <div class="logo-container"><img src="../images/logo.png" alt="Logo" class="logo-img" ></div>
            <h2>Barangay Bolocboloc Property Inventory System</h2>
            <?php
            $bpis_treasurer_nav_active = 'report';
            include __DIR__ . '/../includes/treasurer_sidebar.php';
            ?>
        </div>
                <div class="sidebar-bottom">Brgy. Bolocboloc, Sibulan<br>Negros Oriental, Philippines, 6201<br>@2026</div>

    </div>

    <div class="sidebar-overlay" id="sidebarOverlay" aria-hidden="true"></div>

    <div class="main-content">
        <div class="header shrink-0">
            <button type="button" id="mobileNavToggle" class="mobile-nav-toggle" aria-label="Toggle navigation" aria-expanded="false">
                <i class="fa-solid fa-bars" aria-hidden="true"></i>
            </button>
            <div>
                <h1>Inventory Audit</h1>
                <p>Inventory and Inspection of Unserviceable Property (IIRUP)</p>
            </div>
<?php include __DIR__ . '/../includes/admin_header_profile_icons.php'; ?>
        </div>

        <?php if ($save_message): ?>
        <div class="alert-success"><?= htmlspecialchars($save_message) ?></div>
        <?php endif; ?>
        <?php if ($save_error): ?>
        <div class="alert-error"><?= htmlspecialchars($save_error) ?></div>
        <?php endif; ?>

        <div class="summary-card">
            <div>
                <h3>Total Unserviceable Units</h3>
                <div class="value"><?= number_format($total_unserviceable_qty) ?></div>
                <div class="label">Total Quantity</div>
            </div>
            <div>
                <h3>Total Value</h3>
                <div class="value">₱<?= number_format($total_unserviceable_value, 2) ?></div>
                <div class="label">Original Cost</div>
            </div>
            <div>
                <h3>Total Appraised Value</h3>
                <div class="value">₱<?= number_format(array_sum(array_column($assets, 'appraised_value')), 2) ?></div>
                <div class="label">Current Appraisal</div>
            </div>
        </div>

      <div class="flex justify-between items-center pt-6 mb-6 border-gray-100 pb-4">
            <div class="flex gap-8">
                <a href="../treasurer/audit.php" class="nav-link inactive text-gray-500 hover:text-gray-700">Physical Count</a>
                <a href="../treasurer/audit2.php" class="nav-link active font-semibold text-blue-600 border-b-2 border-blue-600 pb-1">Inventory & Inspection (unserviceable)</a>
            </div>

            <div class="flex items-center gap-3">
                 <div class="relative w-[300px]">
                    <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                        <i data-lucide="search" class="w-4 h-4 text-gray-400"></i>
                    </div>
                    <input type="text" id="audit2Search" class="bg-white border border-gray-300 text-sm rounded-lg block w-full pl-10 px-3 py-2.5 outline-none shadow-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500" placeholder="Search keyword...">
                </div>

                <button type="button" id="toggleEditBtn" class="bg-blue-600 hover:bg-blue-700 text-white font-medium text-sm py-2.5 px-6 rounded-lg shadow-md transition-colors flex items-center gap-2 whitespace-nowrap">
                    <i data-lucide="edit" class="w-4 h-4"></i>
                    <span id="btnText">Edit Audit</span>
                </button>

                <button type="button" id="btnExportIirupPdf" class="bg-blue-600 hover:bg-blue-700 text-white font-medium text-sm py-2.5 px-6 rounded-lg shadow-md transition-colors flex items-center gap-2 whitespace-nowrap" title="Opens print dialog">
                    <i data-lucide="download" class="w-4 h-4"></i> Export PDF
                </button>
            </div>
        </div>
        <form id="auditForm" method="POST" action="" class="flex-grow flex flex-col min-h-0">
        <input type="hidden" name="save_audit" value="1">
        <div class="flex-grow bg-white border border-gray-300 rounded-xl flex flex-col min-h-0 w-full overflow-hidden shadow-sm" data-bpis-date-filter-scope>
            <div class="px-4 pt-3 pb-2 border-b border-gray-100 shrink-0 bg-white">
                
            </div>
            <div class="overflow-x-auto overflow-y-auto custom-scrollbar flex-grow">
                <table class="w-full min-w-max text-left border-collapse whitespace-nowrap" id="audit2Table">
                    <thead class="sticky top-0 z-10">
                        <tr class="bg-gray-50 text-[11px] font-bold text-gray-600 uppercase border-b border-gray-200">
                            <th class="py-3 px-4 border-r">Year Acquired</th>
                            <th class="py-3 px-4 border-r">Particulars</th>
                            <th class="py-3 px-4 border-r">Property No.</th>
                            <th class="py-3 px-4 border-r text-center bg-green-50/30">Unserviceable Qty</th>
                            <th class="py-3 px-4 border-r text-right">Total Cost (Unserviceable)</th>
                            <th class="py-3 px-4 border-r text-right bg-blue-50/30">Accum. Depreciation</th>
                            <th class="py-3 px-4 border-r text-right bg-blue-50/30">Net Book Value</th>
                            <th class="py-3 px-8 border-r min-w-[260px]">Remarks</th>
                            <th class="py-3 px-4 border-r text-right bg-orange-50/30">Appraised Value</th>
                            <th class="py-3 px-4 text-center">Disposition</th>
                        </tr>
                    </thead>
                    <tbody class="text-[13px]">
                        <?php if(empty($assets)): ?>
                            <tr>
                                <td colspan="10" class="py-10 text-center text-gray-500">No unserviceable assets found.
                            </tr>
                        <?php else: ?>
                            <?php foreach ($assets as $index => $asset): ?>
                            <tr class="border-b border-gray-50 hover:bg-gray-50/80 transition-colors audit2-row"<?= bpis_row_date_attr(['date_acquired' => $asset['date_acquired_raw'] ?? ''], ['date_acquired']) ?>>
                                <td class="py-4 px-4 border-r text-gray-500"><?= htmlspecialchars($asset['date_acquired']) ?></td>
                                <td class="py-4 px-4 border-r">
                                    <div class="font-bold text-gray-900"><?= htmlspecialchars($asset['description']) ?></div>
                                <td class="py-4 px-4 font-mono text-blue-700 border-r"><?= htmlspecialchars($asset['prop_num']) ?>
                                <td class="py-4 px-4 text-center border-r font-bold text-green-700 bg-green-50/20"><?= number_format($asset['qty']) ?>
                                <td class="py-4 px-4 text-right border-r font-medium text-gray-900">₱<?= number_format($asset['total_cost'], 2) ?>
                                
                                <td class="py-4 px-2 border-r bg-blue-50/10">
                                    <input type="hidden" 
                                        name="audit[<?= $asset['id'] ?>][total_cost]" 
                                        value="<?= $asset['total_cost'] ?>">

                                    <input type="number"
                                        step="0.01"
                                        name="audit[<?= $asset['id'] ?>][accumulated_depreciation]"
                                        class="audit-input text-right dep-input editable-field readonly-input"
                                        data-index="<?= $index ?>"
                                        data-cost="<?= $asset['total_cost'] ?>"
                                        value="<?= $asset['acc_depreciation'] ?>"
                                        readonly>
                                 

                                <td class="py-4 px-2 border-r bg-blue-50/10">
                                   <input type="text"
                                    id="nbv-<?= $index ?>"
                                    class="audit-input text-right readonly-input font-medium text-gray-900"
                                    value="₱<?= number_format($asset['net_book_value'], 2) ?>"
                                    readonly>
                                  
                                
                               <td class="py-4 px-8 border-r min-w-[260px]">
                                   <input type="text"
                                        name="audit[<?= $asset['id'] ?>][audit_remarks]"
                                        class="audit-input text-left py-3 px-3 editable-field readonly-input"
                                        value="<?= htmlspecialchars($asset['audit_remarks']) ?>"
                                        placeholder="Enter remarks..."
                                        readonly>
                                 

                                <td class="py-4 px-2 border-r bg-orange-50/10">
                                   <input type="text"
                                        name="audit[<?= $asset['id'] ?>][appraised_value]"
                                        class="audit-input text-right appraised-input readonly-input"
                                        data-index="<?= $index ?>"
                                        data-nbv="<?= $asset['net_book_value'] ?>"
                                        value="₱<?= number_format($asset['appraised_value'], 2) ?>"
                                        readonly>                             
                                 

                                <td class="py-4 px-4 text-center font-bold text-red-600">
                                    UNSERVICEABLE
                                 
                             </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-6 flex justify-between items-center shrink-0">
            <p class="text-xs text-gray-500 italic">* Audit tracking of disposal and appraisal of unserviceable assets.</p>
        </div>
        </form>
    </div>

    <div id="iirupPrintArea" aria-hidden="true"></div>

    <script src="../js/table_date_filter.js"></script>
    <script>
        const toggleEditBtn = document.getElementById('toggleEditBtn');
        const btnText = document.getElementById('btnText');
        const auditForm = document.getElementById('auditForm');
        let editMode = false;

        toggleEditBtn.addEventListener('click', function () {
            if (!editMode) {
                editMode = true;
                btnText.textContent = 'Save Changes';
                document.querySelectorAll('.editable-field').forEach(field => {
                    field.removeAttribute('readonly');
                    field.classList.remove('readonly-input');
                });
            } else {
                auditForm.submit();
            }
        });

        document.querySelectorAll('.dep-input').forEach(input => {
            input.addEventListener('input', function () {
                const index = this.dataset.index;
                const cost = parseFloat(this.dataset.cost);
                const dep = parseFloat(this.value) || 0;
                const nbv = cost - dep;
                document.getElementById(`nbv-${index}`).value = "₱" + nbv.toLocaleString(undefined, {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
            });
        });

        const profileTrigger = document.getElementById('profileTrigger');
        const profileMenu = document.getElementById('profileMenu');
        const logoutBtn = document.getElementById('logoutBtn');
        const logoutOverlay = document.getElementById('logoutOverlay');
        const confirmLogoutAction = document.getElementById('confirmLogoutAction');
        const cancelLogoutAction = document.getElementById('cancelLogoutAction');


        profileTrigger.addEventListener('click', function(e) {
            e.stopPropagation();
            profileMenu.classList.toggle('active');
        });

        document.addEventListener('click', function(e) {
            if (!profileMenu.contains(e.target) && e.target !== profileTrigger) {
                profileMenu.classList.remove('active');
            }
        });

        logoutBtn.addEventListener('click', function(e) {
            e.preventDefault();
            logoutOverlay.style.display = 'flex';
            profileMenu.classList.remove('active');
        });

        cancelLogoutAction.addEventListener('click', function() {
            logoutOverlay.style.display = 'none';
        });

        confirmLogoutAction.addEventListener('click', function() {
            window.location.href = "../logout.php";
        });

        logoutOverlay.addEventListener('click', function(e) {
            if (e.target === logoutOverlay) {
                logoutOverlay.style.display = 'none';
            }
        });


        const audit2Search = document.getElementById('audit2Search');
        if (audit2Search) {
            audit2Search.addEventListener('input', function() {
                const filter = this.value.toLowerCase();
                document.querySelectorAll('.audit2-row').forEach(function(row) {
                    const textOk = row.textContent.toLowerCase().indexOf(filter) > -1;
                    row.dataset.bpisSearchHidden = textOk ? '' : '1';
                    if (typeof window.bpisSyncRowDisplay === 'function') {
                        window.bpisSyncRowDisplay(row);
                    } else {
                        row.style.display = textOk ? '' : 'none';
                    }
                });
            });
        }

        function iirupHtmlEscape(s) {
            if (s === null || s === undefined) return '';
            return String(s)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }

        function loadIirupMeta() {
            var el = document.getElementById('bpisIirupMeta');
            if (!el) return {};
            try {
                return JSON.parse(el.textContent);
            } catch (e) {
                return {};
            }
        }

        function iirupSigNameBlock(val) {
            var v = (val != null && String(val).trim() !== '') ? String(val).trim() : '';
            return '<div class="iirup-sig-name">' + (v ? iirupHtmlEscape(v) : '&nbsp;') + '</div>';
        }

        function buildIirupPrintDocument() {
            var meta = loadIirupMeta();
            var rows = Array.from(document.querySelectorAll('tr.audit2-row')).filter(function (tr) {
                return tr.offsetParent !== null;
            });

            var bodyRows = '';
            rows.forEach(function (tr) {
                var tds = tr.querySelectorAll('td');
                if (tds.length < 10) return;
                var dateAcq = (tds[0].textContent || '').trim();
                var particEl = tds[1].querySelector('.font-bold');
                var partic = (particEl ? particEl.textContent : tds[1].textContent || '').trim();
                var prop = (tds[2].textContent || '').trim();
                var qty = (tds[3].textContent || '').trim();
                var tot = (tds[4].textContent || '').trim();
                var depIn = tds[5].querySelector('input');
                var dep = (depIn && depIn.value !== '') ? depIn.value : '';
                var nbvIn = tds[6].querySelector('input');
                var nbv = (nbvIn && nbvIn.value !== '') ? nbvIn.value : '';
                var remIn = tds[7].querySelector('input');
                var rem = (remIn && remIn.value !== '') ? remIn.value : '';
                var appIn = tds[8].querySelector('input');
                var app = (appIn && appIn.value !== '') ? appIn.value : '';
                var disp = (tds[9].textContent || '').trim() || 'UNSERVICEABLE';

                bodyRows +=
                    '<tr>' +
                    '<td class="ta-l">' + iirupHtmlEscape(dateAcq) + '</td>' +
                    '<td class="ta-l">' + iirupHtmlEscape(partic) + '</td>' +
                    '<td class="ta-l">' + iirupHtmlEscape(prop) + '</td>' +
                    '<td class="ta-c">' + iirupHtmlEscape(qty) + '</td>' +
                    '<td class="ta-r">' + iirupHtmlEscape(tot) + '</td>' +
                    '<td class="ta-r">' + iirupHtmlEscape(dep) + '</td>' +
                    '<td class="ta-r">' + iirupHtmlEscape(nbv) + '</td>' +
                    '<td class="ta-l">' + iirupHtmlEscape(rem) + '</td>' +
                    '<td class="ta-r">' + iirupHtmlEscape(app) + '</td>' +
                    '<td class="ta-c">' + iirupHtmlEscape(disp) + '</td>' +
                    '</tr>';
            });

            if (!bodyRows) {
                bodyRows =
                    '<tr><td colspan="10" class="ta-c" style="padding:12px;">No unserviceable items match the current search and date filters.</td></tr>';
            }

            var barangay = meta.barangay || '';
            var city = meta.city_municipality || '';
            var prov = meta.province || '';
            var asOf = meta.as_of || '';
            var officer = meta.accountable_officer || '';
            var desig = meta.accountable_designation || '';
            var preparedBy = meta.prepared_by || document.body.getAttribute('data-bpis-user') || '';
            var preparedTitle = meta.prepared_title || 'Barangay Record Keeper';
            var accountant = meta.city_accountant || '';
            var punong = meta.punong_barangay || '';

            var accountability =
                '<p class="iirup-accountability">For which <strong>' +
                iirupHtmlEscape(officer) +
                '</strong> (Name of Accountable Officer) <strong>' +
                iirupHtmlEscape(desig) +
                '</strong> (Official Designation) is accountable.</p>';

            var thead =
                '<thead>' +
                '<tr>' +
                '<th>Year Acquired</th>' +
                '<th>PARTICULARS</th>' +
                '<th>Property No.</th>' +
                '<th>Unserviceable Qty</th>' +
                '<th>Total Cost</th>' +
                '<th>Accumulated Depreciation</th>' +
                '<th>Net Book Value</th>' +
                '<th>Remarks</th>' +
                '<th>Appraised Value</th>' +
                '<th>Disposition</th>' +
                '</tr>' +
                '<tr class="iirup-col-num">' +
                '<th>1</th><th>2</th><th>3</th><th>4</th><th>5</th>' +
                '<th>6</th><th>7</th><th>8</th><th>9</th><th>10</th>' +
                '</tr>' +
                '</thead>';

            var sigBlock =
                '<table class="iirup-sig-table">' +
                '<tr>' +
                '<tr>' +
               '<div class="iirup-sig-label">Prepared by:</div>' +
                iirupSigNameBlock(meta.treasurer_name || '') +
                '<div class="iirup-sig-sub">Signature Over Printed Name</div>' +
                '<div class="iirup-sig-role">Barangay Treasurer</div>' +
                '<div class="iirup-sig-date">Date: _______________</div>' +
                '</td>' +
                '<td>' +
                '<div class="iirup-sig-label">Certified Correct:</div>' +
                iirupSigNameBlock(accountant) +
                '<div class="iirup-sig-sub">Signature Over Printed Name</div>' +
                '<div class="iirup-sig-role">City/Municipality Accountant</div>' +
                '<div class="iirup-sig-date">Date: _______________</div>' +
                '</td>' +
                '<td>' +
                '<div class="iirup-sig-cert">I CERTIFY to have inspected each and every article enumerated in this report</div>' +
                '<div class="iirup-sig-name">&nbsp;</div>' +
                '<div class="iirup-sig-sub">Signature Over Printed Name</div>' +
                '<div class="iirup-sig-role">Authorized Inspector</div>' +
                '</td>' +
                '<td>' +
                '<div class="iirup-sig-cert">I CERTIFY to have witnessed the inspection of the article enumerated In the report</div>' +
                '<div class="iirup-sig-name">&nbsp;</div>' +
                '<div class="iirup-sig-sub">Signature Over Printed Name</div>' +
                '<div class="iirup-sig-role">Authorized Inspector</div>' +
                '</td>' +
                '</tr>' +
                '</table>' +
                '<div class="iirup-approved-wrap">' +
                '<div class="iirup-approved-inner">' +
                '<div class="iirup-sig-label">Approved by:</div>' +
                iirupSigNameBlock(punong) +
                '<div class="iirup-sig-role">Punong Barangay</div>' +
                '<div class="iirup-sig-date">Date: _______________</div>' +
                '</div>' +
                '</div>';

            return (
                '<div class="iirup-doc">' +
                '<div class="iirup-topline">' +
                '<div><span class="lbl">Barangay: </span>' + iirupHtmlEscape(barangay) + '</div>' +
                '<div style="text-align:right">' +
                '<div><span class="lbl">City/Municipality: </span>' + iirupHtmlEscape(city) + '</div>' +
                '<div><span class="lbl">Province: </span>' + iirupHtmlEscape(prov) + '</div>' +
                '</div>' +
                '</div>' +
                '<div class="iirup-title-wrap">' +
                '<h1>Inventory and Inspection Report of Unserviceable Property</h1>' +
                '<p class="as-of">AS of ' + iirupHtmlEscape(asOf) + '</p>' +
                '</div>' +
                accountability +
                '<table class="iirup-pdf-table">' +
                thead +
                '<tbody>' +
                bodyRows +
                '</tbody>' +
                '</table>' +
                sigBlock +
                '</div>'
            );
        }

        var btnExportIirupPdf = document.getElementById('btnExportIirupPdf');
        if (btnExportIirupPdf) {
            btnExportIirupPdf.addEventListener('click', function () {
                var el = document.getElementById('iirupPrintArea');
                if (!el) return;
                el.innerHTML = buildIirupPrintDocument();
                window.print();
            });
        }

        lucide.createIcons();
    </script>
<script src="../realtime_notifications.js"></script>
<script src="../js/treasurer_mobile_sidebar.js"></script>
</body>
</html>
