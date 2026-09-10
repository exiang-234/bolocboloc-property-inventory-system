<?php
session_start();
include __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth_helpers.php';
require_once __DIR__ . '/../config/table_date_filter_helpers.php';
require_once __DIR__ . '/../config/asset_list_helpers.php';
require_once __DIR__ . '/../config/audit_scan_helpers.php';

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

$rpcppe_meta = [
    'barangay' => 'BOLOCBOLOC',
    'city_municipality' => 'SIBULAN',
    'province' => 'NEGROS ORIENTAL',
    'as_of' => date('F Y'),
    'accountable_officer' => 'VIRGIELYN U. GATELA',
    'accountable_designation' => 'BRGY. TREASURER',
    'prepared_by' => $user_fullname,
    'prepared_title' => 'Barangay Treasurer',
    'certified_by' => '',
    'certified_title' => 'Head, Inventory Committee',
    'approved_by' => '',
    'approved_title' => 'Barangay Captain',
];

$assets = [];
$highlight_asset_id = isset($_GET['highlight']) ? (int) $_GET['highlight'] : 0;
$success_msg = isset($_GET['success']) ? 'Audit counts saved successfully!' : null;

bpis_ensure_audit_scan_schema($conn);

$asset_query = 'SELECT * FROM asset ORDER BY description ASC, id ASC';
if (isset($conn)) {
    $asset_result = mysqli_query($conn, $asset_query);

    if ($asset_result && mysqli_num_rows($asset_result) > 0) {
        while ($row = mysqli_fetch_assoc($asset_result)) {
            $counts = bpis_audit_asset_count_summary($conn, $row);
            $unit_val_numeric = (float)($row['unit_value'] ?? 0);

            $assets[] = [
                "id" => (int)($row['id'] ?? 0),
                "article" => $row['article'] ?? '0',
                "description" => $row['description'] ?? '',
                "prop_num" => $row['property_number'] ?? '',
                "uom" => $row['unit_measure'] ?? '',
                "category" => $row['category'] ?? '',
                "unit_value_raw" => $unit_val_numeric,
                "unit_value" => number_format($unit_val_numeric, 2),
                "qty_card" => $counts['property_count'],
                "val_card" => number_format($counts['val_card'], 2),
                "on_hand" => $counts['on_hand'],
                "diff_qty" => $counts['diff_qty'],
                "diff_val" => number_format($counts['diff_val'], 2),
                "remarks" => bpis_format_asset_remarks_display($row),
                "date_acquired" => $row['date_acquired'] ?? ''
            ];
        }
    }
}
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
    <title>Physical Count Report Dashboard</title>
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
    .header p { 
        font-size: 13px; 
        color: #666; 
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
    .audit-input[readonly] { 
        background-color: #f8fafc; 
        color: #64748b; 
        cursor: not-allowed; 
        border-style: dashed; 
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
        border-bottom: 3px solid #174C7D; }
    .nav-link.inactive { color: #94a3b8; }
    .nav-link.inactive:hover { color: #64748b; }
    .toast-container {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 3000;
        display: none;
        animation: slideIn 0.3s ease-out;
    }

    .toast {
        background-color: white;
        border-radius: 12px;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        width: 320px;
        overflow: hidden;
        border: 1px solid #e2e8f0;
    }

    .toast-header {
        background-color: #166534; 
        color: white;
        padding: 10px 15px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-weight: 600;
        font-size: 14px;
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
    .toast-body {
        padding: 15px;
        color: #475569;
        font-size: 13px;
    }

    @keyframes slideIn {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }

    @keyframes fadeOut {
        from { opacity: 1; }
        to { opacity: 0; }
    }

    #rpcppePrintArea {
        display: none;
    }

    #rpcppePrintArea .rpcppe-doc {
        font-family: 'Times New Roman', Times, serif;
        color: #000;
        font-size: 11px; 
        line-height: 1.4;
    }

    #rpcppePrintArea .rpcppe-topline {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 8px;
        font-size: 11px;
        text-transform: uppercase;
    }

    #rpcppePrintArea .rpcppe-topline .lbl {
        font-weight: 700;
    }

    #rpcppePrintArea .rpcppe-title-wrap {
        text-align: center;
        margin: 8px 0 12px;
    }

    #rpcppePrintArea .rpcppe-title-wrap h1 {
        font-size: 16px;
        font-weight: 700;
        text-transform: uppercase;
        margin: 0 0 4px;
    }

    #rpcppePrintArea .rpcppe-title-wrap .as-of {
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
        margin: 0;
    }

    #rpcppePrintArea .rpcppe-accountability {
        text-align: center;
        font-size: 11px;
        margin: 0 0 12px;
        padding: 0 8px;
    }

    #rpcppePrintArea .rpcppe-accountability strong { font-weight: 700; }

    #rpcppePrintArea table.rpcppe-pdf-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 6px;
        font-size: 11px;
        table-layout: fixed;
    }

    #rpcppePrintArea table.rpcppe-pdf-table th,
    #rpcppePrintArea table.rpcppe-pdf-table td {
        border: 1px solid #000;
        padding: 2px 3px;        
        text-align: center;
        vertical-align: middle;
        word-wrap: break-word;
        font-size: 10px;          
        line-height: 1.2;        
    }

    #rpcppePrintArea table.rpcppe-pdf-table thead th {
        background: #fff;
        font-weight: 700;
        text-transform: uppercase;
        text-align: center;
        font-size: 10px;
    }

    #rpcppePrintArea table.rpcppe-pdf-table thead tr.rpcppe-col-num th {
        font-size: 8px;
        padding: 2px 3px;
    }

    #rpcppePrintArea table.rpcppe-pdf-table .ta-l { text-align: left; }
    #rpcppePrintArea table.rpcppe-pdf-table .ta-c { text-align: center; }
    #rpcppePrintArea table.rpcppe-pdf-table .ta-r { text-align: right; }

    #rpcppePrintArea table.rpcppe-sig-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 18px;
        font-size: 11px;
        table-layout: fixed;
    }

    #rpcppePrintArea table.rpcppe-sig-table td {
        border: none;
        vertical-align: top;
        padding: 8px 12px;
        width: 33.33%;
        text-align: center;
    }

    #rpcppePrintArea .rpcppe-sig-label {
        font-weight: 700;
        margin-bottom: 8px;
    }

    #rpcppePrintArea .rpcppe-sig-name {
        font-weight: 700;
        text-transform: uppercase;
        border-bottom: 1px solid #000;
        min-height: 18px;
        margin: 0 8px 6px;
        padding-bottom: 2px;
        font-size: 11px;
    }

    #rpcppePrintArea .rpcppe-sig-role {
        font-size: 8px;
        margin-top: 6px;
    }

    @media print {
        @page {
            size: landscape;
            margin: 10mm;
        }
        body * {
            visibility: hidden !important;
        }
        #rpcppePrintArea,
        #rpcppePrintArea * {
            visibility: visible !important;
        }
        #rpcppePrintArea {
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
    .table-container{
        width: 100%;
        overflow-x: auto;
        overflow-y: auto;
        flex-grow: 1;
        position: relative;
    }

    .table-container table{
        min-width: 1400px; 
    }
    .table-container{
        scroll-behavior: smooth;
    }
    .audit-row-highlight {
        background-color: #fff7ed !important;
        box-shadow: inset 0 0 0 2px #fb923c;
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
        .table-container table {
            min-width: 1200px !important;
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
        .table-container table {
            min-width: 1000px !important;
        }
        .toast {
            width: 280px !important;
        }
        .toast-header {
            font-size: 13px !important;
        }
        .toast-body {
            font-size: 12px !important;
        }
        .custom-scrollbar::-webkit-scrollbar {
            width: 6px !important;
            height: 6px !important;
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
        .table-container table {
            min-width: 800px !important;
        }
        #rpcppePrintArea .rpcppe-title-wrap h1 {
            font-size: 14px !important;
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
        .table-container table {
            min-width: 700px !important;
        }
        .toast-container {
            top: 12px !important;
            right: 12px !important;
        }
        .toast {
            width: 260px !important;
        }
        .nav-link {
            font-size: 12px !important;
            padding-bottom: 6px !important;
        }
        #rpcppePrintArea .rpcppe-topline {
            font-size: 10px !important;
            flex-direction: column !important;
            align-items: stretch !important;
            gap: 4px !important;
        }
        #rpcppePrintArea .rpcppe-title-wrap h1 {
            font-size: 13px !important;
        }
        #rpcppePrintArea .rpcppe-title-wrap .as-of {
            font-size: 10px !important;
        }
        #rpcppePrintArea table.rpcppe-pdf-table th,
        #rpcppePrintArea table.rpcppe-pdf-table td {
            font-size: 8px !important;
            padding: 2px !important;
        }
        #rpcppePrintArea table.rpcppe-pdf-table thead th {
            font-size: 8px !important;
        }
        #rpcppePrintArea table.rpcppe-sig-table td {
            padding: 4px 6px !important;
        }
        #rpcppePrintArea .rpcppe-sig-name {
            font-size: 9px !important;
        }
        #rpcppePrintArea .rpcppe-sig-role {
            font-size: 7px !important;
        }
        @media print {
            @page {
                size: portrait !important;
                margin: 6mm !important;
            }
            #rpcppePrintArea {
                padding: 4mm 4mm !important;
            }
            #rpcppePrintArea table.rpcppe-pdf-table {
                font-size: 8px !important;
            }
            #rpcppePrintArea table.rpcppe-pdf-table th,
            #rpcppePrintArea table.rpcppe-pdf-table td {
                font-size: 7px !important;
                padding: 1px 2px !important;
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
        .table-container table {
            min-width: 600px !important;
        }
        .toast {
            width: 240px !important;
        }
        .toast-header {
            font-size: 12px !important;
            padding: 8px 12px !important;
        }
        .toast-body {
            font-size: 11px !important;
            padding: 12px !important;
        }
        #rpcppePrintArea table.rpcppe-pdf-table {
            font-size: 9px !important;
        }
        #rpcppePrintArea table.rpcppe-pdf-table th,
        #rpcppePrintArea table.rpcppe-pdf-table td {
            font-size: 7px !important;
            padding: 1px 2px !important;
        }
        .audit-input {
            font-size: 10px !important;
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
        .table-container table {
            min-width: 500px !important;
        }
        .toast-container {
            top: 8px !important;
            right: 8px !important;
        }
        .toast {
            width: 200px !important;
        }
        .toast-header {
            font-size: 11px !important;
            padding: 6px 10px !important;
        }
        .toast-body {
            font-size: 10px !important;
            padding: 10px !important;
        }
        .nav-link {
            font-size: 11px !important;
            padding-bottom: 4px !important;
        }
        .custom-scrollbar::-webkit-scrollbar {
            width: 4px !important;
            height: 4px !important;
        }
        #rpcppePrintArea .rpcppe-title-wrap h1 {
            font-size: 12px !important;
        }
        #rpcppePrintArea table.rpcppe-pdf-table {
            font-size: 8px !important;
        }
        #rpcppePrintArea table.rpcppe-pdf-table th,
        #rpcppePrintArea table.rpcppe-pdf-table td {
            font-size: 6px !important;
            padding: 1px !important;
        }
        #rpcppePrintArea .rpcppe-sig-name {
            font-size: 8px !important;
            min-height: 14px !important;
        }
        #rpcppePrintArea .rpcppe-sig-role {
            font-size: 6px !important;
        }
        @media print {
            #rpcppePrintArea table.rpcppe-pdf-table th,
            #rpcppePrintArea table.rpcppe-pdf-table td {
                font-size: 6px !important;
            }
            #rpcppePrintArea .rpcppe-title-wrap h1 {
                font-size: 11px !important;
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
        .table-container table {
            min-width: 400px !important;
        }
        #rpcppePrintArea table.rpcppe-pdf-table {
            font-size: 7px !important;
        }
        #rpcppePrintArea table.rpcppe-pdf-table th,
        #rpcppePrintArea table.rpcppe-pdf-table td {
            font-size: 5px !important;
        }
        #rpcppePrintArea .rpcppe-title-wrap h1 {
            font-size: 11px !important;
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
        .table-container table {
            min-width: 350px !important;
        }
        .toast {
            width: 180px !important;
        }
        .toast-header {
            font-size: 10px !important;
            padding: 5px 8px !important;
        }
        .toast-body {
            font-size: 9px !important;
            padding: 8px !important;
        }
        #rpcppePrintArea table.rpcppe-pdf-table {
            font-size: 6px !important;
        }
        #rpcppePrintArea table.rpcppe-pdf-table th,
        #rpcppePrintArea table.rpcppe-pdf-table td {
            font-size: 5px !important;
            padding: 1px !important;
        }
        #rpcppePrintArea .rpcppe-title-wrap h1 {
            font-size: 10px !important;
        }
        @media print {
            #rpcppePrintArea {
                padding: 2mm !important;
            }
            #rpcppePrintArea table.rpcppe-pdf-table th,
            #rpcppePrintArea table.rpcppe-pdf-table td {
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
        #btnExportRpcppePdf {
            width: 100% !important;
            justify-content: center !important;
        }
        .table-container {
            max-width: 100% !important;
            overflow-x: auto !important;
            -webkit-overflow-scrolling: touch !important;
        }
        #auditTable {
            min-width: 980px !important;
            width: max-content !important;
            font-size: 12px !important;
            line-height: 1.35 !important;
        }
        #auditTable thead,
        #auditTable th {
            font-size: 11px !important;
            line-height: 1.25 !important;
        }
        #auditTable th {
            padding: 9px 8px !important;
        }
        #auditTable tbody,
        #auditTable td {
            font-size: 12px !important;
            line-height: 1.35 !important;
        }
        #auditTable td {
            padding: 10px 8px !important;
        }
        #auditTable .audit-input {
            min-width: 92px !important;
            font-size: 12px !important;
            line-height: 1.3 !important;
            padding: 6px 7px !important;
        }
        #auditTable .asset-description {
            max-width: 220px !important;
        }
    }

    @media (max-width: 480px) {
        #auditTable {
            min-width: 920px !important;
        }
        #auditTable thead,
        #auditTable th {
            font-size: 10px !important;
        }
        #auditTable tbody,
        #auditTable td,
        #auditTable .audit-input {
            font-size: 11px !important;
        }
        #auditTable th {
            padding: 8px 7px !important;
        }
        #auditTable td {
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
    <script type="application/json" id="bpisRpcppeMeta"><?php
        echo json_encode($rpcppe_meta, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
    ?></script>

<?php include __DIR__ . '/../includes/bpis_logout_modal.php'; ?>

    <div class="sidebar">
        <div class="sidebar-top">
            <button type="button" id="mobileNavClose" class="mobile-nav-close" aria-label="Close navigation">
                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </button>
            <div class="logo-container"><img src="../images/logo.png" alt="Logo" class="logo-img"></div>
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
                <h1>Inventory Report</h1>
                <p>Count of Property, Plant and Equipment</p>
            </div>
<?php include __DIR__ . '/../includes/admin_header_profile_icons.php'; ?>
        </div>

     <div class="flex justify-between items-center pt-6 mb-6 border-gray-100 pb-4">
        <div class="flex gap-8">
            <a href="../treasurer/audit.php" class="nav-link active font-semibold text-blue-600 border-b-2 border-blue-600 pb-1">Physical Count</a>
            <a href="../treasurer/audit2.php" class="nav-link inactive text-gray-500 hover:text-gray-700">Inventory & Inspection (unserviceable)</a>
        </div>

        <div class="flex items-center gap-3">
            <div class="relative w-[300px]">
                <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                    <i data-lucide="search" class="w-4 h-4 text-gray-400"></i>
                </div>
                <input type="text" id="searchInput" class="bg-white border border-gray-300 text-sm rounded-lg block w-full pl-10 px-3 py-2.5 outline-none shadow-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500" placeholder="Search article...">
            </div>
            <button type="button" id="btnExportRpcppePdf" class="bg-blue-600 hover:bg-blue-700 text-white font-medium text-sm py-2.5 px-6 rounded-lg shadow-md transition-colors flex items-center gap-2 whitespace-nowrap">
                <i data-lucide="download" class="w-4 h-4"></i> Export PDF
            </button>
        </div>
    </div>

       <div class="flex-grow bg-white border border-gray-300 rounded-xl flex flex-col min-h-0 w-full overflow-hidden shadow-sm" data-bpis-date-filter-scope>
            <div class="table-container custom-scrollbar">
                <table class="text-left border-collapse whitespace-nowrap" id="auditTable">
                    <thead class="sticky top-0 z-10">
                        <tr class="bg-gray-50 text-[11px] font-bold text-gray-600 uppercase border-b border-gray-200">
                            <th class="py-3 px-4 border-r">Article</th>
                            <th class="py-3 px-4 border-r">Description</th>
                            <th class="py-3 px-4 border-r">Property No.</th>
                            <th class="py-3 px-4 border-r">Unit</th>
                            <th class="py-3 px-4 border-r">Unit Cost/Value</th>
                            <th class="py-3 px-4 border-r text-center bg-blue-50/50" colspan="2">Balance Per Card</th>
                            <th class="py-3 px-4 border-r text-center bg-orange-50/50">On Hand Count</th>
                            <th class="py-3 px-4 border-r text-center" colspan="2">Shortage/Overage</th>
                            <th class="py-3 px-4">Remarks</th>
                        </tr>
                    </thead>
                    <tbody class="text-[13px]">
                        <?php if(empty($assets)): ?>
                            <tr><td colspan="11" class="py-10 text-center text-gray-500">No assets available for audit.</td></tr>
                        <?php else: ?>
                            <?php foreach ($assets as $asset): ?>
                            <tr class="border-b border-gray-50 hover:bg-gray-50/80 transition-colors asset-row<?= $highlight_asset_id === (int) $asset['id'] ? ' audit-row-highlight' : '' ?>" data-asset-id="<?= (int) $asset['id'] ?>" id="audit-row-<?= (int) $asset['id'] ?>" data-category="<?= htmlspecialchars(strtolower($asset['category'])) ?>"<?= bpis_row_date_attr(['date_acquired' => $asset['date_acquired'] ?? ''], ['date_acquired']) ?>>
                                <td class="py-4 px-4 font-bold text-gray-900 border-r article-name"><?= htmlspecialchars($asset['article']) ?></td>
                                <td class="py-4 px-4 text-gray-600 border-r max-w-xs truncate asset-description"><?= htmlspecialchars($asset['description']) ?></td>
                                <td class="py-4 px-4 font-mono text-blue-700 border-r prop-num"><?= htmlspecialchars($asset['prop_num']) ?></td>
                                <td class="py-4 px-4 text-center border-r"><?= htmlspecialchars($asset['uom']) ?></td>
                                <td class="py-4 px-4 text-right border-r unit-val">₱<?= $asset['unit_value'] ?></td>
                                
                                <td class="py-4 px-2 border-r bg-blue-50/10">
                                    <input type="hidden" name="audit[<?= (int)$asset['id'] ?>][qty_card]" value="<?= $asset['qty_card'] ?>">
                                    <input type="hidden" name="audit[<?= (int)$asset['id'] ?>][unit_val_hidden]" value="<?= $asset['unit_value_raw'] ?>">
                                    <input type="number" class="audit-input font-bold qty-card" value="<?= $asset['qty_card'] ?>" readonly>
                                </td>
                                <td class="py-4 px-2 border-r bg-blue-50/10">
                                    <input type="text" class="audit-input font-bold" value="₱<?= $asset['val_card'] ?>" readonly>
                                </td>
                                
                                <td class="py-4 px-2 border-r bg-orange-50/10">
                                    <input type="number" class="audit-input border-orange-200 focus:ring-orange-400 bg-white actual-count" value="<?= $asset['on_hand'] ?>" placeholder="0" readonly>
                                </td>

                                <td class="py-4 px-2 border-r">
                                    <input type="number" class="audit-input diff-qty text-gray-400" value="<?= $asset['diff_qty'] ?>" placeholder="0" readonly>
                                </td>
                                <td class="py-4 px-2 border-r">
                                    <input type="text" class="audit-input diff-val text-gray-400" value="<?= $asset['diff_val'] ?>" placeholder="₱0.00" readonly>
                                </td>

                                <td class="py-4 px-4 font-bold">
                                    <?= htmlspecialchars($asset['remarks']) ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-6 flex justify-between items-center shrink-0">
            <p class="text-xs text-gray-500 italic">*Audit for property, plant and equipment counts.</p>
        </div>
    </div>

    <div id="rpcppePrintArea" aria-hidden="true"></div>

  <script src="../js/table_date_filter.js"></script>
  <script>
        const profileTrigger = document.getElementById('profileTrigger');
        const profileMenu = document.getElementById('profileMenu');
        const logoutBtn = document.getElementById('logoutBtn');
        const logoutOverlay = document.getElementById('logoutOverlay');
        const confirmLogoutAction = document.getElementById('confirmLogoutAction');
        const cancelLogoutAction = document.getElementById('cancelLogoutAction');
        const searchInput = document.getElementById('searchInput');
        const rpcppePrintArea = document.getElementById('rpcppePrintArea');
        const btnExportRpcppePdf = document.getElementById('btnExportRpcppePdf');

        function escapeHtml(s) {
            return String(s ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }

        function inputVal(row, sel) {
            const el = row.querySelector(sel);
            return el ? (el.value != null ? el.value : el.innerText) : '';
        }

        function selectText(row, sel) {
            const el = row.querySelector(sel);
            if (!el || el.tagName !== 'SELECT') return '';
            const opt = el.options[el.selectedIndex];
            return opt ? opt.text : '';
        }

        function loadRpcppeMeta() {
            var el = document.getElementById('bpisRpcppeMeta');
            if (!el) return {};
            try {
                return JSON.parse(el.textContent);
            } catch (e) {
                return {};
            }
        }

        function rpcppeSigName(val) {
            var v = (val != null && String(val).trim() !== '') ? String(val).trim() : '';
            return '<div class="rpcppe-sig-name">' + (v ? escapeHtml(v) : '&nbsp;') + '</div>';
        }

        function buildRpcppePrintDocument() {
            var meta = loadRpcppeMeta();
            var barangay = meta.barangay || '';
            var city = meta.city_municipality || '';
            var prov = meta.province || '';
            var asOf = meta.as_of || '';
            var officer = meta.accountable_officer || '';
            var desig = meta.accountable_designation || '';
            var preparedBy = meta.prepared_by || document.body.dataset.bpisUser || '';
            var preparedTitle = meta.prepared_title || 'Barangay Treasurer';
            var certifiedBy = meta.certified_by || '';
            var certifiedTitle = meta.certified_title || 'Head, Inventory Committee';
            var approvedBy = meta.approved_by || '';
            var approvedTitle = meta.approved_title || 'Barangay Captain';

            var accountability =
                '<p class="rpcppe-accountability">For which <strong>' +
                escapeHtml(officer) +
                '</strong> (Name of Accountable Officer) <strong>' +
                escapeHtml(desig) +
                '</strong> (Official Designation) is accountable.</p>';

            var thead =
                '<thead>' +
                '<tr>' +
                '<th rowspan="2">ARTICLE</th>' +
                '<th rowspan="2">DESCRIPTION</th>' +
                '<th rowspan="2">PROPERTY<br>NUMBER</th>' +
                '<th rowspan="2">UNIT OF<br>MEASURE</th>' +
                '<th rowspan="2">UNIT<br>VALUE</th>' +
                '<th rowspan="2">BALANCE PER CARD<br>(Quantity)</th>' +
                '<th rowspan="2">ON HAND PER COUNT<br>(QUANTITY)</th>' +
                '<th colspan="2">SHORTAGE/OVERAGE</th>' +
                '<th rowspan="2">REMARKS</th>' +
                '</tr>' +
                '<tr>' +
                '<th>Quantity</th>' +
                '<th>Value</th>' +
                '</tr>' +
                '<tr class="rpcppe-col-num">' +
                '<th>1</th><th>2</th><th>3</th><th>4</th><th>5</th>' +
                '<th>6</th><th>7</th><th>8</th><th>9</th><th>10</th>' +
                '</tr>' +
                '</thead>';

            var bodyRows = '';
            document.querySelectorAll('tr.asset-row').forEach(function (tr) {
                if (tr.offsetParent === null) return;

                var articleTxt = (tr.querySelector('.article-name') && tr.querySelector('.article-name').innerText || '').trim();
                var descTxt = (tr.querySelector('.asset-description') && tr.querySelector('.asset-description').innerText || '').trim();
                var prop = (tr.querySelector('.prop-num') && tr.querySelector('.prop-num').innerText || '').trim();
                var uom = (tr.querySelectorAll('td')[3] && tr.querySelectorAll('td')[3].innerText || '').trim();
                var unitVal = (tr.querySelector('.unit-val') && tr.querySelector('.unit-val').innerText || '').trim();
                var qtyCard = inputVal(tr, '.qty-card');
                var actual = inputVal(tr, '.actual-count');
                var diffQty = inputVal(tr, '.diff-qty');
                var diffVal = inputVal(tr, '.diff-val');
                var remarks = selectText(tr, '.remarks-select');

                var articleCol = (String(qtyCard || '').trim() + ' ' + uom).trim().toUpperCase();
                var descriptionCol = descTxt
                    ? (articleTxt ? articleTxt + ' — ' + descTxt : descTxt)
                    : articleTxt;

                bodyRows +=
                    '<tr>' +
                    '<td class="ta-l">' + escapeHtml(articleCol) + '</td>' +
                    '<td class="ta-l">' + escapeHtml(descriptionCol) + '</td>' +
                    '<td class="ta-l">' + escapeHtml(prop) + '</td>' +
                    '<td class="ta-c">' + escapeHtml(uom) + '</td>' +
                    '<td class="ta-r">' + escapeHtml(unitVal) + '</td>' +
                    '<td class="ta-r">' + escapeHtml(qtyCard) + '</td>' +
                    '<td class="ta-r">' + escapeHtml(actual) + '</td>' +
                    '<td class="ta-r">' + escapeHtml(diffQty) + '</td>' +
                    '<td class="ta-r">' + escapeHtml(diffVal) + '</td>' +
                    '<td class="ta-c">' + escapeHtml(remarks) + '</td>' +
                    '</tr>';
            });

            if (!bodyRows) {
                bodyRows =
                    '<tr><td colspan="10" class="ta-c" style="padding:12px;">No assets match the current search and date filters.</td></tr>';
            }

            var sigBlock =
                '<table class="rpcppe-sig-table">' +
                '<tr>' +
                '<tr>' +
               '<div class="rpcppe-sig-label">Prepared by:</div>' +
                rpcppeSigName(meta.treasurer_name || '') +
                '<div class="rpcppe-sig-role">Barangay Treasurer</div>' +
                '</tr>' +
                '<td>' +
                '<div class="rpcppe-sig-label">Certified Correct by:</div>' +
                rpcppeSigName(certifiedBy) +
                '<div class="rpcppe-sig-role">' + escapeHtml(certifiedTitle) + '</div>' +
                '</td>' +
                '<td>' +
                '<div class="rpcppe-sig-label">Approved by:</div>' +
                rpcppeSigName(approvedBy) +
                '<div class="rpcppe-sig-role">' + escapeHtml(approvedTitle) + '</div>' +
                '</tr>' +
                '</tr>' +
                '</table>';

            return (
                '<div class="rpcppe-doc">' +
                '<div class="rpcppe-topline">' +
                '<div><span class="lbl">Barangay: </span>' + escapeHtml(barangay) + '</div>' +
                '<div style="text-align:right">' +
                '<div><span class="lbl">City/Municipality: </span>' + escapeHtml(city) + '</div>' +
                '<div><span class="lbl">Province: </span>' + escapeHtml(prov) + '</div>' +
                '</div>' +
                '</div>' +
                '<div class="rpcppe-title-wrap">' +
                '<h1>Report on the Physical Count of Property, Plant and Equipment</h1>' +
                '<p class="as-of">AS of ' + escapeHtml(asOf) + '</p>' +
                '</div>' +
                accountability +
                '<table class="rpcppe-pdf-table">' +
                thead +
                '<tbody>' +
                bodyRows +
                '</tbody></table>' +
                sigBlock +
                '</div>'
            );
        }

        if (btnExportRpcppePdf && rpcppePrintArea) {
            btnExportRpcppePdf.addEventListener('click', function () {
                rpcppePrintArea.innerHTML = buildRpcppePrintDocument();
                window.print();
            });
        }

        searchInput.addEventListener('input', function() {
            const filter = this.value.toLowerCase().trim();
            document.querySelectorAll('.asset-row').forEach(row => {
                const article = row.querySelector('.article-name').innerText.toLowerCase();
                const description = row.querySelector('.asset-description').innerText.toLowerCase();
                const propNum = row.querySelector('.prop-num').innerText.toLowerCase();
                const category = row.getAttribute('data-category') || "";

                const matchesSearch = article.includes(filter) ||
                                      description.includes(filter) ||
                                      propNum.includes(filter) ||
                                      category.includes(filter);

                row.dataset.bpisSearchHidden = matchesSearch ? '' : '1';
                if (typeof window.bpisSyncRowDisplay === 'function') {
                    window.bpisSyncRowDisplay(row);
                } else {
                    row.style.display = matchesSearch ? '' : 'none';
                }
            });
        });

        profileTrigger.addEventListener('click', (e) => { e.stopPropagation(); profileMenu.classList.toggle('active'); });
        document.addEventListener('click', (e) => { if (!profileMenu.contains(e.target)) profileMenu.classList.remove('active'); });
        logoutBtn.addEventListener('click', (e) => { e.preventDefault(); logoutOverlay.style.display = 'flex'; });
        cancelLogoutAction.addEventListener('click', () => { logoutOverlay.style.display = 'none'; });
        confirmLogoutAction.addEventListener('click', () => { window.location.href = "../logout.php"; });

        lucide.createIcons();

        const highlightAssetId = <?= (int) $highlight_asset_id ?>;
        if (highlightAssetId > 0) {
            const highlightRow = document.getElementById('audit-row-' + highlightAssetId);
            if (highlightRow) {
                highlightRow.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }
    </script>
<script src="../realtime_notifications.js"></script>
<script src="../js/treasurer_mobile_sidebar.js"></script>
</body>
</html>
