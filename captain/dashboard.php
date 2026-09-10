<?php
session_start();

include __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth_helpers.php';
require_once __DIR__ . '/../config/captain_page_helpers.php';
require_once __DIR__ . '/../config/table_date_filter_helpers.php';

bpis_require_login(['Barangay Captain']);

$bpis_u = bpis_captain_load_user($conn);
$user_fullname = $bpis_u['fullname'];
$user_role = $bpis_u['role'];
include __DIR__ . '/../includes/bpis_resolve_header_profile.php';

$inventory_total_value = 0.0;
$inventory_fixed = 0;
$inventory_movable = 0;
$inventory_asset_count = 0;
$working_count = 0;
$repair_count = 0;
$dispose_count = 0;
$value_rs = mysqli_query($conn, 'SELECT COUNT(*) AS c, COALESCE(SUM(total_cost), 0) AS v FROM asset');
if ($value_rs && ($vr = mysqli_fetch_assoc($value_rs))) {
    $inventory_asset_count = (int) ($vr['c'] ?? 0);
    $inventory_total_value = (float) ($vr['v'] ?? 0);
}
$condition_rs = mysqli_query(
    $conn,
    "SELECT
        COUNT(CASE WHEN remarks = 'SERVICEABLE' OR item_condition = 'SERVICEABLE' THEN 1 END) AS serviceable_total,
        COUNT(CASE WHEN remarks = 'UNDER REPAIR' OR item_condition = 'UNDER REPAIR' THEN 1 END) AS repair_total,
        COUNT(CASE WHEN remarks = 'UNSERVICEABLE/DISPOSE' OR item_condition = 'UNSERVICEABLE/DISPOSE' THEN 1 END) AS dispose_total
     FROM asset"
);
if ($condition_rs && ($cr = mysqli_fetch_assoc($condition_rs))) {
    $working_count = (int) ($cr['serviceable_total'] ?? 0);
    $repair_count = (int) ($cr['repair_total'] ?? 0);
    $dispose_count = (int) ($cr['dispose_total'] ?? 0);
}
if (function_exists('bpis_column_exists') && bpis_column_exists($conn, 'asset', 'asset_cluster')) {
    $fr = mysqli_query($conn, "SELECT COUNT(*) AS c FROM asset WHERE asset_cluster = 'Fixed Assets'");
    if ($fr && ($r = mysqli_fetch_assoc($fr))) {
        $inventory_fixed = (int) ($r['c'] ?? 0);
    }
    $mr = mysqli_query($conn, "SELECT COUNT(*) AS c FROM asset WHERE asset_cluster = 'Movable Assets'");
    if ($mr && ($r = mysqli_fetch_assoc($mr))) {
        $inventory_movable = (int) ($r['c'] ?? 0);
    }
}
$inventory_updated_at = date('Y-m-d H:i:s');

$assets = [];
$asset_query = "SELECT * FROM asset ORDER BY id DESC LIMIT 5";
$asset_result = mysqli_query($conn, $asset_query);
if ($asset_result) {
    while ($row = mysqli_fetch_assoc($asset_result)) {
        $qty = isset($row['quantity']) ? (int)$row['quantity'] : (int)($row['article'] ?? 0);
        $assets[] = [
            "id" => (int) ($row['id'] ?? 0),
            "article" => $row['article'] ?? '',
            "description" => $row['description'] ?? '',
            "category" => $row['category'] ?? '',
            "prop_num" => $row['property_number'] ?? '',
            "uom" => $row['unit_measure'] ?? '',
            "date_acquired" => $row['date_acquired'] ?? '',
            "unit_value" => number_format((float)($row['unit_value'] ?? 0), 2),
            "remarks" => strtoupper($row['remarks'] ?? ''),
            "status" => $qty . " Available"
        ];
    }
}

$asset_photo_map = bpis_get_asset_photo_map($conn, array_column($assets, 'id'));

$registered_borrower_count = 0;
$borrower_count_rs = mysqli_query(
    $conn,
    "SELECT COUNT(DISTINCT full_name) AS c FROM borrower WHERE full_name IS NOT NULL AND TRIM(full_name) <> ''"
);
if ($borrower_count_rs && ($bc = mysqli_fetch_assoc($borrower_count_rs))) {
    $registered_borrower_count = (int) ($bc['c'] ?? 0);
}

$registered_borrowers = [];
$borrow_query = "SELECT * FROM borrower ORDER BY id DESC LIMIT 5";
$borrow_result = mysqli_query($conn, $borrow_query);
if ($borrow_result) {
    while ($row = mysqli_fetch_assoc($borrow_result)) {
        $registered_borrowers[] = $row;
    }
}

$asset_descriptions = [];
$asset_desc_result = mysqli_query($conn, "SELECT id, description FROM asset");
if ($asset_desc_result) {
    while ($asset = mysqli_fetch_assoc($asset_desc_result)) {
        $asset_descriptions[(int)$asset['id']] = $asset['description'] ?? '';
    }
}

$ph_now = new DateTime('now', new DateTimeZone('Asia/Manila'));
$hour_now = (int)$ph_now->format('G');
if ($hour_now < 12) {
    $greeting = 'Good morning';
} elseif ($hour_now < 18) {
    $greeting = 'Good afternoon';
} else {
    $greeting = 'Good evening';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" href="<?= bpis_app_base_path() ?>/images/logo.png">
    <link rel="stylesheet" href="../css/stat_cards.css">
    <link rel="stylesheet" href="../css/table_date_filter.css">
    <link rel="stylesheet" href="../css/asset_inventory_image.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="../css/profile_dropdown.css"><link rel="stylesheet" href="../css/sidebar_nav_transition.css">
    <script src="../js/sidebar_nav_transition.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php include __DIR__ . '/../includes/bpis_app_meta.php'; ?>
    <title>Dashboard</title>
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
        height: auto;
        min-height: 100vh; 
        color: #333; 
        overflow: auto;
    }
    .recent-borrowing-table {
        margin-bottom: 0;
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
    .nav-item:hover { 
        background: linear-gradient(90deg, #3b82f6, #2563eb);
        border-color: rgba(255, 255, 255, 0.35);
        transform: translateX(2px); 
    }
    .nav-item.active { 
        background: linear-gradient(90deg, #3b82f6, #2563eb);
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
        padding: 10px 45px 20px 45px; 
        min-width: 0;
        overflow-x: hidden;
        height: auto;
        min-height: auto;
    }

    .header { 
        position: sticky;
        top: 10px;
        z-index: 100;
        background: #F6F8FC;
        border-radius: 14px;
        padding: 15px 20px;
        display: flex; 
        justify-content: space-between;
        align-items: center; 
        margin-bottom: 20px;
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
    .cards-container{
        position: sticky;
        top: 120px;
        z-index: 99;
        background: #F6F8FC;
        padding: 10px 0 20px 0;
        margin-bottom: 25px;
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
    .icon-circle:hover { 
        border-color: #3B82F6; 
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
        } 
        to { 
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
    .table-section { 
        background-color: white; 
        border-radius: 10px; 
        padding: 20px; 
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.02); 
        border: 1px solid #eee; 
        scroll-margin-top: 10px;
        position: relative;
        z-index: 1;
        margin-bottom: 10px;
    }
    .table-scroll {
        width: 100%;
        overflow-x: auto;
        margin-bottom: 10px; 
        padding-bottom: 0;   
        scrollbar-width: none; 
        -ms-overflow-style: none; 
    }
    .table-scroll table {
        min-width: 1100px;
    }
    .table-scroll::-webkit-scrollbar {
        display: none; 
    }
    .table-section h3 { 
        font-size: 15px; 
        font-weight: 700; 
        margin-bottom: 15px; 
        color: #111;
    }
    table { 
        width: 100%; 
        border-collapse: collapse;
        font-size: 12px;
        margin: 0 0 0 0;
    }
    th { 
        text-align: left; 
        color: #888;
        padding: 10px; 
        font-weight: 600; 
        text-transform: uppercase; 
        font-size: 13px; 
        border-bottom: 1px solid #eee; 
    }
    td { 
        padding: 12px 10px;
        border-bottom: 1px solid #f9f9f9; 
        color: #444; 
        font-size: 12px;
    }
    .highlight-text { 
        font-weight: 600; 
        color: #111;
    }
    .view-all-link { 
        color: #3B82F6; 
        text-decoration: none; 
        font-weight: 600; 
        display: inline-block; 
        margin-top: 15px; 
        font-size: 12px; 
    }
    code {
        background: #f1f5f9; 
        padding: 2px 6px; 
        border-radius: 4px;
        color: #475569; 
        font-size: 12px; 
    }

    @media (max-width: 1024px) {
        .sidebar {
            width: 200px;
            padding: 20px 12px;
        }
        .sidebar h2 {
            font-size: 14px;
            margin-bottom: 20px;
        }
        .nav-item {
            font-size: 12px;
            padding: 10px 12px;
        }
        .main-content {
            margin-left: 200px;
            padding: 10px 20px 60px 20px;
        }
        .header h1 {
            font-size: 20px;
        }
        .logo-img {
            width: 60px;
            height: 60px;
        }
        .table-section {
            padding: 16px;
        }
        .table-scroll table {
            min-width: 900px;
        }
        th {
            font-size: 12px;
            padding: 8px;
        }
        td {
            font-size: 11px;
            padding: 10px 8px;
        }
        .cards-container {
            top: 110px;
            padding: 8px 0 16px 0;
        }
    }

    @media (max-width: 768px) {
        .sidebar {
            width: 60px;
            padding: 15px 8px;
        }
        .sidebar h2 {
            display: none;
        }
        .sidebar-bottom {
            display: none;
        }
        .logo-img {
            width: 40px;
            height: 40px;
        }
        .nav-item {
            font-size: 11px;
            padding: 8px 6px;
            text-align: center;
            border-radius: 8px;
        }
        .nav-item i {
            margin-right: 0;
            width: 20px;
            font-size: 16px;
        }
        .nav-item span {
            display: none;
        }
        .nav-item:hover {
            transform: none;
        }
        .main-content {
            margin-left: 60px;
            padding: 10px 15px 50px 15px;
        }
        .header {
            flex-wrap: wrap;
            gap: 10px;
            padding: 12px 15px;
            top: 8px;
        }
        .header h1 {
            font-size: 18px;
        }
        .header p {
            font-size: 12px;
        }
        .header-icons {
            gap: 12px;
        }
        .icon-circle {
            width: 34px;
            height: 34px;
        }
        .icon-box {
            width: 26px;
            height: 26px;
        }
        .cards-container {
            top: 100px;
            padding: 6px 0 12px 0;
        }
        .table-section {
            padding: 12px;
            border-radius: 8px;
        }
        .table-section h3 {
            font-size: 14px;
        }
        .table-scroll table {
            min-width: 700px;
        }
        th {
            font-size: 11px;
            padding: 6px 8px;
        }
        td {
            font-size: 10px;
            padding: 8px 6px;
        }
        .profile-dropdown {
            width: 180px;
            right: -10px;
            top: 48px;
        }
        .profile-user-info img {
            width: 48px;
            height: 48px;
        }
        .profile-user-info h4 {
            font-size: 13px;
        }
        .profile-links a {
            font-size: 12px;
            padding: 10px 16px;
        }
        .view-all-link {
            font-size: 11px;
        }
        .recent-borrowing-table {
            margin-bottom: 40px;
        }
        code {
            font-size: 10px;
        }
    }

    @media (max-width: 480px) {
        .sidebar {
            width: 50px;
            padding: 10px 4px;
        }
        .logo-img {
            width: 30px;
            height: 30px;
        }
        .nav-item {
            font-size: 10px;
            padding: 6px 4px;
            border-radius: 6px;
        }
        .nav-item i {
            font-size: 14px;
            width: 18px;
        }
        .main-content {
            margin-left: 50px;
            padding: 8px 10px 40px 10px;
        }
        .header {
            flex-direction: column;
            align-items: flex-start;
            gap: 8px;
            padding: 10px 12px;
            top: 6px;
        }
        .header h1 {
            font-size: 16px;
        }
        .header p {
            font-size: 11px;
        }
        .header-icons {
            align-self: flex-end;
            gap: 8px;
        }
        .icon-circle {
            width: 28px;
            height: 28px;
        }
        .icon-box {
            width: 22px;
            height: 22px;
        }
        .cards-container {
            top: 90px;
            padding: 4px 0 10px 0;
        }
        .table-section {
            padding: 10px;
            border-radius: 6px;
        }
        .table-section h3 {
            font-size: 13px;
        }
        .table-scroll table {
            min-width: 500px;
        }
        th {
            font-size: 10px;
            padding: 5px 6px;
        }
        td {
            font-size: 9px;
            padding: 6px 5px;
        }
        .profile-dropdown {
            width: 160px;
            right: -5px;
            top: 42px;
            padding-top: 14px;
        }
        .profile-user-info img {
            width: 40px;
            height: 40px;
        }
        .profile-user-info h4 {
            font-size: 12px;
        }
        .profile-links a {
            font-size: 11px;
            padding: 8px 14px;
        }
        .view-all-link {
            font-size: 10px;
        }
        .recent-borrowing-table {
            margin-bottom: 30px;
        }
        code {
            font-size: 9px;
            padding: 1px 4px;
        }
        .highlight-text {
            font-size: 10px;
        }
    }

    @media (max-width: 360px) {
        .sidebar {
            width: 40px;
            padding: 8px 2px;
        }
        .logo-img {
            width: 24px;
            height: 24px;
        }
        .nav-item {
            font-size: 9px;
            padding: 4px 2px;
            border-radius: 4px;
        }
        .nav-item i {
            font-size: 12px;
            width: 16px;
        }
        .main-content {
            margin-left: 40px;
            padding: 6px 6px 30px 6px;
        }
        .header h1 {
            font-size: 14px;
        }
        .header p {
            font-size: 10px;
        }
        .header-icons {
            gap: 6px;
        }
        .icon-circle {
            width: 24px;
            height: 24px;
        }
        .icon-box {
            width: 18px;
            height: 18px;
        }
        .cards-container {
            top: 80px;
            padding: 2px 0 8px 0;
        }
        .table-section {
            padding: 8px;
            border-radius: 4px;
        }
        .table-section h3 {
            font-size: 12px;
        }
        .table-scroll table {
            min-width: 400px;
        }
        th {
            font-size: 9px;
            padding: 4px 4px;
        }
        td {
            font-size: 8px;
            padding: 4px 4px;
        }
        .profile-dropdown {
            width: 140px;
            right: 0;
            top: 38px;
            padding-top: 12px;
        }
        .profile-user-info img {
            width: 34px;
            height: 34px;
        }
        .profile-user-info h4 {
            font-size: 11px;
        }
        .profile-links a {
            font-size: 10px;
            padding: 6px 12px;
        }
        .view-all-link {
            font-size: 9px;
        }
        .recent-borrowing-table {
            margin-bottom: 0;
        }
        code {
            font-size: 8px;
        }
    }
</style>
<style>
/* Remove empty flex gap below tables on all dashboards */
.main-content{height:auto !important; min-height:auto !important; padding-bottom:20px !important; overflow:visible !important;}
body{height:auto !important; min-height:100vh !important; overflow:auto !important;}
.table-section{margin-bottom:20px !important;}
.table-section:last-child{margin-bottom:0 !important; padding-bottom:20px !important;}
.recent-borrowing-table{margin-bottom:0 !important;}
@media (max-width:1024px){.main-content{padding-bottom:20px !important;}}
@media (max-width:768px){.main-content{padding-bottom:15px !important;}}
@media (max-width:480px){.main-content{padding-bottom:12px !important;}}
@media (max-width:360px){.main-content{padding-bottom:10px !important;}}
</style>
<style>
/* Balanced left+right spacing — content not touching either edge, responsive, keep design */
.main-content{padding-left:45px !important; padding-right:45px !important;}
.cards-container{margin-left:4px !important; margin-right:4px !important;}
.table-section{margin-left:4px !important; margin-right:4px !important;}
.glass-summary-row{margin-left:4px !important; margin-right:4px !important;}
@media (max-width:1200px){.main-content{padding-left:32px !important; padding-right:32px !important;}}
@media (max-width:1024px){.main-content{padding-left:30px !important; padding-right:30px !important;}}
@media (max-width:900px){.main-content{padding-left:26px !important; padding-right:26px !important;}}
@media (max-width:768px){.main-content{padding-left:20px !important; padding-right:20px !important;}}
@media (max-width:480px){.main-content{padding-left:16px !important; padding-right:16px !important;}}
@media (max-width:400px){.main-content{padding-left:14px !important; padding-right:14px !important;}}
</style>
    <link rel="stylesheet" href="../css/captain_mobile_sidebar.css">
    <link rel="stylesheet" href="../css/logout_modal.css">

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { theme: { extend: { colors: { brand: { 600: '#2563eb', 700: '#1d4ed8' } } } } };
    </script>
    <script src="../tailwind_blue_theme.js"></script>
</head>
<body>
<?php include __DIR__ . '/../includes/bpis_logout_modal.php'; ?>

    <div class="sidebar">
        <div class="sidebar-top">
            <button type="button" id="mobileNavClose" class="mobile-nav-close" aria-label="Close navigation">
                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </button>
            <div class="logo-container">
                <img src="../images/logo.png" alt="Barangay Logo" class="logo-img">
            </div>
            <h2>Barangay Bolocboloc Property Inventory System</h2>
            <?php
            $bpis_captain_nav_active = 'dashboard';
            include __DIR__ . '/../includes/captain_sidebar.php';
            ?>
        </div>
        <div class="sidebar-bottom">Brgy. Bolocboloc, Sibulan<br>Negros Oriental, Philippines, 6201<br>@2026</div>
    </div>

    <div class="sidebar-overlay" id="sidebarOverlay" aria-hidden="true"></div>

    <div class="main-content">
        <div class="header">
            <button type="button" id="mobileNavToggle" class="mobile-nav-toggle" aria-label="Toggle navigation" aria-expanded="false">
                <i class="fa-solid fa-bars" aria-hidden="true"></i>
            </button>
            <div>
                <h1><?= $greeting ?> Barangay Captain, <?= htmlspecialchars($user_fullname) ?>.</h1>
                <p>Summary of barangay asset inventory and borrowing logs.</p>
            </div>
<?php include __DIR__ . '/../includes/admin_header_profile_icons.php'; ?>
        </div>

        <div class="cards-container">
            <div class="card card-blue">
                <p>Total Assets</p>
                <div class="card-number" id="liveTotalAssets"><?= (int) $inventory_asset_count ?></div>
            </div>
            <div class="card card-yellow">
                <p>Serviceable</p>
                <div class="card-number" id="liveServiceableAssets"><?= (int) $working_count ?></div>
            </div>
            <div class="card card-green">
                <p>Under Repair</p>
                <div class="card-number" id="liveRepairAssets"><?= (int) $repair_count ?></div>
            </div>
            <div class="card card-red">
                <p>Unserviceable/Dispose</p>
                <div class="card-number" id="liveUnserviceableAssets"><?= (int) $dispose_count ?></div>
            </div>
        </div>

        <div class="glass-summary-row" id="liveAssetGlassBar" aria-live="polite">
            <div class="glass-card glass-card-value">
                <span class="glass-card-icon" aria-hidden="true"><i class="fa-solid fa-coins"></i></span>
                <div class="glass-card-body">
                    <span class="glass-card-label">Total inventory value</span>
                    <span class="glass-card-value" id="liveTotalValue">₱<?= number_format($inventory_total_value, 2) ?></span>
                </div>
            </div>
            <div class="glass-card glass-card-fixed">
                <span class="glass-card-icon" aria-hidden="true"><i class="fa-solid fa-building"></i></span>
                <div class="glass-card-body">
                    <span class="glass-card-label">Fixed assets</span>
                    <span class="glass-card-value" id="liveFixedAssets"><?= (int) $inventory_fixed ?></span>
                </div>
            </div>
            <div class="glass-card glass-card-movable">
                <span class="glass-card-icon" aria-hidden="true"><i class="fa-solid fa-truck"></i></span>
                <div class="glass-card-body">
                    <span class="glass-card-label">Movable assets</span>
                    <span class="glass-card-value" id="liveMovableAssets"><?= (int) $inventory_movable ?></span>
                </div>
            </div>
            <div class="glass-card glass-card-updated">
                <span class="glass-card-icon" aria-hidden="true"><i class="fa-solid fa-clock"></i></span>
                <div class="glass-card-body">
                    <span class="glass-card-label">Last updated</span>
                    <span class="glass-card-value glass-card-value-sm" id="liveUpdatedAt"><?= htmlspecialchars($inventory_updated_at) ?></span>
                </div>
            </div>
        </div>

       <div class="table-section" data-bpis-date-filter-scope>
    <h3>Inventory Logs</h3>
    <div class="table-scroll">
    <table class="w-full min-w-[1200px] text-left border-collapse whitespace-nowrap">
        <thead class="sticky top-0 z-10 shadow-sm">
            <tr class="bg-white">
                <th class="py-4 px-6 font-semibold text-gray-500 text-[13px] uppercase border-b border-gray-200 text-center">IMAGE</th>
                <th class="py-4 px-6 font-semibold text-gray-500 text-[13px] uppercase border-b border-gray-200">ARTICLE</th>
                <th class="py-4 px-6 font-semibold text-gray-500 text-[13px] uppercase border-b border-gray-200">DESCRIPTION</th>
                <th class="py-4 px-6 font-semibold text-gray-500 text-[13px] uppercase border-b border-gray-200">CATEGORY</th>
                <th class="py-4 px-6 font-semibold text-gray-500 text-[13px] uppercase border-b border-gray-200">PROPERTY NUMBER</th>
                <th class="py-4 px-6 font-semibold text-gray-500 text-[13px] uppercase border-b border-gray-200">UOM</th>
                <th class="py-4 px-6 font-semibold text-gray-500 text-[13px] uppercase border-b border-gray-200">DATE ACQUIRED</th>
                <th class="py-4 px-6 font-semibold text-gray-500 text-[13px] uppercase border-b border-gray-200">UNIT VALUE / COST</th>
                <th class="py-4 px-6 font-semibold text-gray-500 text-[13px] uppercase border-b border-gray-200 text-center">REMARKS</th>
            </tr>
        </thead>
        <tbody class="text-sm">
            <?php if(empty($assets)): ?>
                <tr>
                    <td colspan="10" style="text-align: center; color: #888; font-style: italic; padding: 40px;">
                        No asset registered yet.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($assets as $asset): ?>
                    <tr class="cap-inv-row"<?= bpis_row_date_attr($asset, ['date_acquired']) ?>>
                        <td class="bpis-asset-photo-cell"><?= bpis_asset_photo_img($conn, (int) ($asset['id'] ?? 0), (string) ($asset['description'] ?? 'Asset'), 'bpis-asset-thumb', $asset_photo_map) ?></td>
                        <td><?= htmlspecialchars($asset['article']) ?></td>
                        <td><?= htmlspecialchars($asset['description']) ?></td>
                        <td><?= htmlspecialchars($asset['category']) ?></td>
                        <td><?= htmlspecialchars($asset['prop_num']) ?></td>
                        <td><?= htmlspecialchars($asset['uom']) ?></td>
                        <td>
                            <?= !empty($asset['date_acquired']) 
                                ? htmlspecialchars(date("F d, Y", strtotime($asset['date_acquired']))) 
                                : '' ?>
                        </td>
                        <td>₱ <?= htmlspecialchars($asset['unit_value']) ?></td>
                        <td><?= htmlspecialchars($asset['remarks']) ?></td>

                    </tr>
                    <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    <br><a href="inventory_logs.php" class="view-all-link">View all inventory logs →</a>
    </div>
</div>
     <div class="table-section recent-borrowing-table" data-bpis-date-filter-scope>
    <h3>Borrowing Activity Logs</h3>
    <div class="table-scroll" >
    <table class="w-full text-left border-collapse whitespace-nowrap">
        <thead class="sticky top-0 z-10 shadow-sm">
            <tr>
                <th>ID</th>
                <th>Borrower</th>
                <th>Address</th>
                <th>Contact</th>
                <th>Item</th>
                <th>QTY</th>
                <th>Needed On</th>
                <th>Purpose</th>
                <th>Return Date</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($registered_borrowers)): ?>
                <tr>
                    <td colspan="10" style="text-align: center; padding: 40px; color: #888; font-style: italic;">
                        No registered borrowers found.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($registered_borrowers as $borrower): ?>
                    <tr class="cap-bor-row"<?= bpis_row_date_attr($borrower, ['needed_on', 'date_needed', 'return_date']) ?>>
                        <td>B-<?= str_pad($borrower['request_id'] ?? 0, 4, '0', STR_PAD_LEFT) ?></td>
                        <td><?= htmlspecialchars($borrower['full_name'] ?? '') ?></td>
                        <td><?= htmlspecialchars((str_starts_with(strtolower(trim((string)($borrower['address'] ?? ''))), 'purok') ? '' : 'Purok ') . ($borrower['address'] ?? '')) ?></td>
                        <td><?= htmlspecialchars($borrower['contact'] ?? '') ?></td>
                        <td><?= htmlspecialchars($borrower['item'] ?? ($asset_descriptions[(int)($borrower['asset_id'] ?? 0)] ?? '')) ?></td>
                        <td><?= htmlspecialchars($borrower['qty'] ?? '1') ?></td>
                        <td>
                            <?= !empty($borrower['needed_on']) 
                                ? htmlspecialchars(date("F d, Y", strtotime($borrower['needed_on']))) 
                                : '' ?>
                        </td>
                        <td><?= htmlspecialchars($borrower['purpose'] ?? '') ?></td>
                        <td>
                            <?= !empty($borrower['return_date']) 
                                ? htmlspecialchars(date("F d, Y", strtotime($borrower['return_date']))) 
                                : '' ?>
                        </td>
                        <td><?= htmlspecialchars($borrower['status'] ?? '') ?></td>
                    </tr>
                    <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    <br><a href="borrowing_logs.php" class="view-all-link">View all borrowers logs →</a>
    </div>
</div>

      <script src="../js/table_date_filter.js"></script>
      <script>
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

        function refreshAssetSummary() {
            fetch('../treasurer/assets_summary_feed.php', { credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    if (!d.success) return;
                    var total = document.getElementById('liveTotalAssets');
                    var val = document.getElementById('liveTotalValue');
                    var fixed = document.getElementById('liveFixedAssets');
                    var movable = document.getElementById('liveMovableAssets');
                    var updated = document.getElementById('liveUpdatedAt');
                    var serviceable = document.getElementById('liveServiceableAssets');
                    var repair = document.getElementById('liveRepairAssets');
                    var unserviceable = document.getElementById('liveUnserviceableAssets');
                    if (total) total.textContent = d.total_assets;
                    if (serviceable) serviceable.textContent = d.serviceable;
                    if (repair) repair.textContent = d.under_repair;
                    if (unserviceable) unserviceable.textContent = d.unserviceable;
                    if (val) val.textContent = '₱' + d.total_value;
                    if (fixed) fixed.textContent = d.fixed_assets;
                    if (movable) movable.textContent = d.movable_assets;
                    if (updated) updated.textContent = d.updated_at;
                })
                .catch(function (err) { console.warn('Captain asset summary refresh failed:', err); });
        }
        refreshAssetSummary();
        setInterval(refreshAssetSummary, 30000);

        logoutOverlay.addEventListener('click', function(e) {
            if (e.target === logoutOverlay) {
                logoutOverlay.style.display = 'none';
            }
        });
    </script>
<script src="../realtime_notifications.js"></script>
<script src="../js/captain_mobile_sidebar.js"></script>
</body>
</html>
