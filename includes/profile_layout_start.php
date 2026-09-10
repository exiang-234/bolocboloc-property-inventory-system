<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require_once __DIR__ . '/../config/auth_helpers.php';
bpis_require_login(['Secretary', 'Treasurer', 'Barangay Captain']);

$bpis_page_title = $bpis_page_title ?? 'Profile';
$bpis_user_role = (string) ($_SESSION['role'] ?? '');
$bpis_user_fullname = $bpis_user_fullname ?? 'User';
$bpis_profile_avatar = $bpis_profile_avatar ?? '../images/profile_logo.png';
$bpis_nav_prefix = match ($bpis_user_role) {
    'Secretary' => 'secretary/',
    'Treasurer' => 'treasurer/',
    'Barangay Captain' => 'captain/',
    default => '',
};
$bpis_secretary_nav_active = $bpis_secretary_nav_active ?? '';
$bpis_treasurer_nav_active = $bpis_treasurer_nav_active ?? '';
$bpis_captain_nav_active = $bpis_captain_nav_active ?? '';
$user_fullname = $bpis_user_fullname;
$user_role = $bpis_user_role;
$bpis_header_path_prefix = '';
$bpis_header_avatar = $bpis_profile_avatar;
$bpis_profile_link = 'profile.php';
$bpis_avatar_fallback = 'images/profile_logo.png';
$bpis_notifications_icon = 'images/notification.png';
$bpis_profile_nav_active = true;
$bpis_profile_dropdown_css = 'css/profile_dropdown.css';
$bpis_logout_modal_css = 'css/logout_modal.css';
$bpis_notifications_css = 'css/bpis_notifications.css';
$bpis_mobile_sidebar_css = match ($bpis_user_role) {
    'Secretary' => 'css/secretary_mobile_sidebar.css',
    'Barangay Captain' => 'css/captain_mobile_sidebar.css',
    default => 'css/treasurer_mobile_sidebar.css',
};
$bpis_mobile_sidebar_js = match ($bpis_user_role) {
    'Secretary' => 'js/secretary_mobile_sidebar.js',
    'Barangay Captain' => 'js/captain_mobile_sidebar.js',
    default => 'js/treasurer_mobile_sidebar.js',
};
if (isset($conn) && $conn instanceof mysqli) {
    include __DIR__ . '/bpis_resolve_header_profile.php';
    $bpis_profile_avatar = $bpis_header_avatar;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" href="<?= bpis_app_base_path() ?>/images/logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="css/treasurer_shell.css">
    <?php include __DIR__ . '/bpis_profile_dropdown_head.php'; ?>
    <?php include __DIR__ . '/bpis_logout_modal_head.php'; ?>
    <link rel="stylesheet" href="css/profile_page.css">
    <link rel="stylesheet" href="css/sidebar_nav_transition.css">
    <link rel="stylesheet" href="<?= htmlspecialchars($bpis_mobile_sidebar_css, ENT_QUOTES, 'UTF-8') ?>">
    <script src="js/sidebar_nav_transition.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php include __DIR__ . '/bpis_app_meta.php'; ?>
    <title><?= htmlspecialchars($bpis_page_title) ?></title>
    <?= $bpis_extra_head ?? '' ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="tailwind_blue_theme.js"></script>
    
</head>
<body class="profile-route">
<?php include __DIR__ . '/bpis_logout_modal.php'; ?>

    <div class="sidebar">
        <div class="sidebar-top">
            <button type="button" id="mobileNavClose" class="mobile-nav-close" aria-label="Close navigation">
                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </button>
            <div class="logo-container">
                <img src="images/logo.png" alt="Logo" class="logo-img">
            </div>
            <h2>Barangay Bolocboloc Property Inventory System</h2>
            <?php
            if ($bpis_user_role === 'Secretary') {
                $bpis_nav_prefix = 'secretary/';
                include __DIR__ . '/secretary_sidebar.php';
            } elseif ($bpis_user_role === 'Barangay Captain') {
                $bpis_nav_prefix = 'captain/';
                $bpis_captain_nav_active = $bpis_captain_nav_active ?: '';
                include __DIR__ . '/captain_sidebar.php';
            } else {
                $bpis_nav_prefix = 'treasurer/';
                $bpis_treasurer_nav_active = $bpis_treasurer_nav_active ?: '';
                include __DIR__ . '/treasurer_sidebar.php';
            }
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
                <h1>Profile</h1>
                <p>View and update your account information.</p>
            </div>
<?php include __DIR__ . '/admin_header_profile_icons.php'; ?>
        </div>
        <div class="profile-page-scroll custom-scrollbar flex-grow min-h-0 w-full">
