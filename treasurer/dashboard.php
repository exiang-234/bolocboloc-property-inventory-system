<?php
session_start();

include __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth_helpers.php';
require_once __DIR__ . '/../config/admin_profile_helpers.php';
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
$hp = bpis_admin_header_profile($conn, '../');
$bpis_header_avatar = $hp['avatar_url'];
$user_fullname = $hp['fullname'] ?: $user_fullname;
$user_role = $hp['role'] ?: $user_role;

$stats_query = "SELECT 
    COUNT(*) as total_count,
    COUNT(CASE WHEN item_condition = 'SERVICEABLE' THEN 1 END) as serviceable_total,
    COUNT(CASE WHEN item_condition = 'UNDER REPAIR' THEN 1 END) as repair_total,
    COUNT(CASE WHEN item_condition = 'UNSERVICEABLE/DISPOSE' THEN 1 END) as dispose_total
    FROM asset";

$stats_result = mysqli_query($conn, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);


$total_assets  = $stats['total_count'] ?? 0;
$working_count = $stats['serviceable_total'] ?? 0;
$repair_count  = $stats['repair_total'] ?? 0;
$dispose_count = $stats['dispose_total'] ?? 0;

$inventory_total_value = 0.0;
$inventory_fixed = 0;
$inventory_movable = 0;
$value_rs = mysqli_query($conn, 'SELECT COALESCE(SUM(total_cost), 0) AS v FROM asset');
if ($value_rs && ($vr = mysqli_fetch_assoc($value_rs))) {
    $inventory_total_value = (float) ($vr['v'] ?? 0);
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
$audit_activities = [];
$recent_query = "SELECT property_number, description, item_condition, date_acquired 
                 FROM asset 
                 ORDER BY id DESC LIMIT 5";
$recent_result = mysqli_query($conn, $recent_query);

if ($recent_result) {
    while ($row = mysqli_fetch_assoc($recent_result)) {
        $audit_activities[] = [
            'property_no' => $row['property_number'],
            'description' => $row['description'],
            'condition'   => $row['item_condition'],
            'date'        => !empty($row['date_acquired']) ? date("m/d/Y", strtotime($row['date_acquired'])) : 'N/A',
            'date_raw'    => $row['date_acquired'] ?? '',
        ];
    }
}

$categories = [];
$cat_query = "SELECT category, COUNT(*) as count FROM asset GROUP BY category";
$cat_result = mysqli_query($conn, $cat_query);

if ($cat_result) {
    while ($row = mysqli_fetch_assoc($cat_result)) {
        $cat_name = !empty($row['category']) ? $row['category'] : 'Uncategorized';
        $categories[$cat_name] = $row['count'];
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="../css/profile_dropdown.css">
    <link rel="stylesheet" href="../css/logout_modal.css">
    <link rel="stylesheet" href="../css/sidebar_nav_transition.css">
    <script src="../js/sidebar_nav_transition.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php include __DIR__ . '/../includes/bpis_app_meta.php'; ?>
    <title>Summary Dashboard</title>
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
        padding: 25px 45px 20px 45px;
        height: auto;
        min-height: auto;
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
    /* Mobile nav toggle */
    .mobile-nav-toggle {
        display: none;
        background: transparent;
        border: none;
        color: #174C7D;
        font-size: 18px;
        cursor: pointer;
    }
    /* ensure toggle aligns with header and is tappable */
    .mobile-nav-toggle { padding: 6px; border-radius: 8px; position: relative; z-index: 60; }
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

    .nav-label { margin-left: 8px; display: inline-block; }
    .mobile-nav-close { display: none; background: transparent; border: none; color: white; font-size: 20px; position: absolute; left: 12px; top: 12px; z-index: 40; padding: 6px; }

    .table-section { 
        background-color: white; 
        border-radius: 10px; 
        padding: 20px; 
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.02); 
        margin-bottom: 20px; 
        border: 1px solid #eee; 
    }
    .table-section h3 { 
        font-size: 15px; 
        font-weight: 700; 
        margin-bottom: 15px; 
        color: #111; 
    }
    .table-section:last-child{
        margin-bottom: 0;
        padding-bottom: 20px;
    }
    table { 
        width: 100%; 
        border-collapse: collapse; 
        font-size: 13px; 
    }
    th { 
        text-align: left; 
        color: #888; 
        padding: 8px 10px; 
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
    .asset-code { 
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
    .category-table { 
        max-width: 400px; 
    }
    .category-count { 
        text-align: right; 
        font-weight: 700; 
        color: #174C7D; 
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
            padding: 22px 28px 45px 28px !important;
        }
        .table-section {
            padding: 16px 16px 32px 16px !important;
        }
        .category-table {
            max-width: 350px !important;
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
            padding: 20px 25px 40px 25px !important;
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
        .table-section {
            padding: 14px 14px 28px 14px !important;
            border-radius: 8px !important;
        }
        .table-section h3 {
            font-size: 14px !important;
        }
        th {
            font-size: 12px !important;
            padding: 6px 8px !important;
        }
        td {
            font-size: 11px !important;
            padding: 10px 8px !important;
        }
        .category-table {
            max-width: 300px !important;
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
            padding: 16px 20px 35px 20px !important;
        }
        .header h1 {
            font-size: 19px !important;
        }
        .logo-img {
            width: 52px !important;
            height: 52px !important;
        }
        .profile-dropdown {
            width: 200px !important;
        }
        .table-section {
            padding: 12px 12px 24px 12px !important;
            border-radius: 8px !important;
        }
        .table-section h3 {
            font-size: 13px !important;
            margin-bottom: 12px !important;
        }
        th {
            font-size: 11px !important;
            padding: 5px 6px !important;
        }
        td {
            font-size: 11px !important;
            padding: 8px 6px !important;
        }
        .category-table {
            max-width: 100% !important;
        }
        .view-all-link {
            font-size: 11px !important;
            margin-top: 12px !important;
        }
    }

    @media (max-width: 768px) {
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
        .table-section {
            padding: 10px 10px 20px 10px !important;
            border-radius: 6px !important;
            overflow-x: auto !important;
        }
        .table-section h3 {
            font-size: 12px !important;
            margin-bottom: 10px !important;
        }
        table {
            font-size: 11px !important;
            min-width: 500px !important;
        }
        th {
            font-size: 10px !important;
            padding: 4px 5px !important;
        }
        td {
            font-size: 10px !important;
            padding: 6px 5px !important;
        }
        .category-table {
            max-width: 100% !important;
        }
        .view-all-link {
            font-size: 10px !important;
            margin-top: 10px !important;
        }
        .asset-code {
            font-size: 10px !important;
        }
    }

    @media (max-width: 600px) {
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
        .table-section {
            padding: 8px 8px 16px 8px !important;
        }
        .table-section h3 {
            font-size: 11px !important;
        }
        table {
            font-size: 10px !important;
            min-width: 400px !important;
        }
        th {
            font-size: 9px !important;
            padding: 3px 4px !important;
        }
        td {
            font-size: 9px !important;
            padding: 5px 4px !important;
        }
        .view-all-link {
            font-size: 9px !important;
            margin-top: 8px !important;
        }
    }

    @media (max-width: 480px) {
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
        .table-section {
            padding: 6px 6px 14px 6px !important;
            border-radius: 4px !important;
        }
        .table-section h3 {
            font-size: 10px !important;
            margin-bottom: 8px !important;
        }
        table {
            font-size: 9px !important;
            min-width: 350px !important;
        }
        th {
            font-size: 8px !important;
            padding: 2px 3px !important;
        }
        td {
            font-size: 8px !important;
            padding: 4px 3px !important;
        }
        .view-all-link {
            font-size: 8px !important;
            margin-top: 6px !important;
        }
        .asset-code {
            font-size: 8px !important;
        }
        .category-count {
            font-size: 8px !important;
        }
    }

    @media (max-width: 400px) {
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
        .table-section {
            padding: 4px 4px 12px 4px !important;
        }
        table {
            min-width: 300px !important;
        }
        th {
            font-size: 7px !important;
            padding: 2px 2px !important;
        }
        td {
            font-size: 7px !important;
            padding: 3px 2px !important;
        }
    }

    @media (max-width: 360px) {
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
        .table-section {
            padding: 4px 4px 10px 4px !important;
        }
        .table-section h3 {
            font-size: 9px !important;
            margin-bottom: 6px !important;
        }
        table {
            font-size: 8px !important;
            min-width: 280px !important;
        }
        th {
            font-size: 6px !important;
            padding: 1px 2px !important;
        }
        td {
            font-size: 6px !important;
            padding: 2px 2px !important;
        }
        .view-all-link {
            font-size: 7px !important;
            margin-top: 4px !important;
        }
        .asset-code {
            font-size: 7px !important;
        }
        .category-count {
            font-size: 7px !important;
        }
    }
    @media (min-width: 769px) and (max-width: 1024px) {
        .sidebar {
            width: 200px !important;
            padding: 20px 12px !important;
            transform: none !important;
            visibility: visible !important;
        }
        .sidebar h2,
        .sidebar-bottom,
        .nav-label {
            display: block !important;
        }
        .nav-item span {
            display: inline-block !important;
        }
        .sidebar-overlay,
        body.sidebar-open .sidebar-overlay {
            display: none !important;
            opacity: 0 !important;
            pointer-events: none !important;
        }
        .mobile-nav-toggle,
        .mobile-nav-close {
            display: none !important;
        }
        .main-content,
        body.sidebar-open .main-content,
        body:not(.sidebar-open) .main-content {
            margin-left: 200px !important;
            padding: 20px 25px 40px !important;
        }
    }

    @media (max-width: 420px) {
        .header h1 {
            font-size: 16px !important;
        }
        .header p {
            font-size: 11px !important;
        }
        .mobile-nav-toggle {
            width: 38px !important;
            height: 38px !important;
            flex-basis: 38px !important;
        }
        .header-icons {
            gap: 7px !important;
        }
    }
    </style>
<style>
/* Remove empty flex gap below tables on all dashboards */
.main-content{height:auto !important; min-height:auto !important; padding-bottom:20px !important; overflow:visible !important;}
body{height:auto !important; min-height:100vh !important; overflow:auto !important;}
.table-section{margin-bottom:20px !important;}
.table-section:last-child{margin-bottom:0 !important; padding-bottom:20px !important;}
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
    <link rel="stylesheet" href="../css/treasurer_mobile_sidebar.css">
    <style>
        @media (max-width: 768px) {
            body.sidebar-open .mobile-nav-close {
                left: auto !important;
                right: 12px !important;
                top: 12px !important;
                width: 40px !important;
                height: 40px !important;
                padding: 0 !important;
                border-radius: 10px !important;
                z-index: 95 !important;
            }
        }

        @media (max-width: 420px) {
            body.sidebar-open .mobile-nav-close {
                right: 10px !important;
                top: 10px !important;
                width: 38px !important;
                height: 38px !important;
            }
        }
    </style>
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
            <div class="logo-container"><img src="../images/logo.png" alt="Barangay Logo" class="logo-img"></div>
            <h2>Barangay Bolocboloc Property Inventory System</h2>
            <?php
            $bpis_treasurer_nav_active = 'dashboard';
            include __DIR__ . '/../includes/treasurer_sidebar.php';
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
                <h1><?= $greeting ?> Treasurer, <?= htmlspecialchars($user_fullname) ?>.</h1>
                <p>Summary of barangay assets and recent activities.</p>
            </div>
<?php include __DIR__ . '/../includes/admin_header_profile_icons.php'; ?>
        </div>

        <div class="cards-container">
            <div class="card card-blue">
                <p>Total Assets</p>
                <div class="card-number" id="liveTotalAssets"><?= (int) $total_assets ?></div>
            </div>

            <div class="card card-yellow">
                <p>Serviceable</p>
                <div class="card-number" id="liveServiceableAssets"><?= $working_count ?></div>
            </div>

            <div class="card card-green">
                <p>Under Repair</p>
                <div class="card-number" id="liveRepairAssets"><?= $repair_count ?></div>
            </div>

            <div class="card card-red">
                <p>Unserviceable/Dispose</p>
                <div class="card-number" id="liveUnserviceableAssets"><?= $dispose_count ?></div>
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
            <h3>Recent Audit Activity</h3>
            <table>
                <thead>
                    <tr>
                        <th>Property Number</th>
                        <th>Description</th>
                        <th>Condition</th>
                        <th>Date Acquired</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($audit_activities)): ?>
                        <tr><td colspan="4" style="text-align: center;">No recent activities found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($audit_activities as $activity): ?>
                            <tr class="audit-act-row"<?= bpis_row_date_attr(['date_acquired' => $activity['date_raw'] ?? ''], ['date_acquired']) ?>>
                                <td>
                                    <span class="asset-code"><?= htmlspecialchars($activity['property_no']) ?></span>
                                </td>
                                <td><?= htmlspecialchars($activity['description']) ?></td>
                                <td><?= htmlspecialchars($activity['condition']) ?></td>
                                <td><?= htmlspecialchars($activity['date']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            <a href="../treasurer/audit.php" class="view-all-link">View all activity →</a>
        </div>

        <div class="table-section" >
            <h3>By Category</h3>
            <table class="category-table" >
                <thead>
                    <tr>
                        <th>Category Name</th>
                        <th style="text-align: right;">Count</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categories as $categoryName => $count): ?>
                        <tr>
                            <td><?= htmlspecialchars($categoryName) ?></td>
                            <td class="category-count"><?= $count ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

     <script src="../js/table_date_filter.js"></script>
     <script>
        (function() {
            const profileTrigger = document.getElementById('profileTrigger');
            const profileMenu = document.getElementById('profileMenu');
            const logoutBtn = document.getElementById('logoutBtn');
            const logoutOverlay = document.getElementById('logoutOverlay');
            const confirmLogoutAction = document.getElementById('confirmLogoutAction');
            const cancelLogoutAction = document.getElementById('cancelLogoutAction');

            if (profileTrigger && profileMenu) {
                profileTrigger.addEventListener('click', function(e) {
                    e.stopPropagation();
                    profileMenu.classList.toggle('active');
                });
                document.addEventListener('click', function(e) {
                    if (!profileMenu.contains(e.target) && e.target !== profileTrigger) {
                        profileMenu.classList.remove('active');
                    }
                });
            }

            if (logoutBtn && logoutOverlay) {
                logoutBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    logoutOverlay.style.display = 'flex';
                    if (profileMenu) profileMenu.classList.remove('active');
                });
            }

            if (cancelLogoutAction && logoutOverlay) {
                cancelLogoutAction.addEventListener('click', function() {
                    logoutOverlay.style.display = 'none';
                });
            }

            if (confirmLogoutAction) {
                confirmLogoutAction.addEventListener('click', function() {
                    window.location.href = "../logout.php";
                });
            }

            if (logoutOverlay) {
                logoutOverlay.addEventListener('click', function(e) {
                    if (e.target === logoutOverlay) {
                        logoutOverlay.style.display = 'none';
                    }
                });
            }
        })();
        function refreshAssetSummary() {
            fetch('assets_summary_feed.php', { credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    if (!d.success) return;
                    var el = document.getElementById('liveTotalAssets');
                    var val = document.getElementById('liveTotalValue');
                    var fixed = document.getElementById('liveFixedAssets');
                    var movable = document.getElementById('liveMovableAssets');
                    var updated = document.getElementById('liveUpdatedAt');
                    var serviceable = document.getElementById('liveServiceableAssets');
                    var repair = document.getElementById('liveRepairAssets');
                    var unserviceable = document.getElementById('liveUnserviceableAssets');
                    if (el) el.textContent = d.total_assets;
                    if (serviceable) serviceable.textContent = d.serviceable;
                    if (repair) repair.textContent = d.under_repair;
                    if (unserviceable) unserviceable.textContent = d.unserviceable;
                    if (val) val.textContent = '₱' + d.total_value;
                    if (fixed) fixed.textContent = d.fixed_assets;
                    if (movable) movable.textContent = d.movable_assets;
                    if (updated) updated.textContent = d.updated_at;
                })
                .catch(function (err) { console.warn('Treasurer asset summary refresh failed:', err); });
        }
        refreshAssetSummary();
        setInterval(refreshAssetSummary, 30000);
    </script>
<script src="../realtime_notifications.js"></script>
<script src="../js/treasurer_mobile_sidebar.js"></script>
</body>
</html>
