<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    require_once __DIR__ . '/../config/bpis_session.php';
    bpis_start_session();
}
require_once __DIR__ . '/../config/auth_helpers.php';
bpis_require_login(['Treasurer', 'Barangay Captain']);
require_once __DIR__ . '/../config/treasurer_page_helpers.php';
$bpis_page_title = $bpis_page_title ?? 'Treasurer';
$bpis_page_heading = $bpis_page_heading ?? $bpis_page_title;
$bpis_page_subtitle = $bpis_page_subtitle ?? '';
$bpis_extra_head = $bpis_extra_head ?? '';
$bpis_skip_content_panel = !empty($bpis_skip_content_panel);

if (!isset($user_fullname) || !isset($user_role)) {
    if (isset($conn) && $conn instanceof mysqli) {
        $bpis_u = bpis_treasurer_load_user($conn);
        $user_fullname = $bpis_u['fullname'];
        $user_role = $bpis_u['role'];
    } else {
        $user_fullname = $user_fullname ?? 'Treasurer';
        $user_role = $user_role ?? 'Staff';
    }
}
include __DIR__ . '/bpis_resolve_header_profile.php';
$user_fullname = $user_fullname ?? 'Treasurer';
$user_role = $user_role ?? 'Staff';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" href="<?= bpis_app_base_path() ?>/images/logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="../css/treasurer_shell.css">
    <link rel="stylesheet" href="../css/treasurer_mobile_sidebar.css">
    <?php include __DIR__ . '/bpis_profile_dropdown_head.php'; ?>
    <?php include __DIR__ . '/bpis_logout_modal_head.php'; ?>
    <link rel="stylesheet" href="../css/sidebar_nav_transition.css">
    <script src="../js/sidebar_nav_transition.js"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php include __DIR__ . '/bpis_app_meta.php'; ?>
    <title><?= htmlspecialchars($bpis_page_title) ?></title>
    <?= $bpis_extra_head ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { theme: { extend: { colors: { brand: { 600: '#2563eb', 700: '#1d4ed8' } } } } };
    </script>
    <script src="../tailwind_blue_theme.js"></script>
</head>
<body>
<?php include __DIR__ . '/bpis_logout_modal.php'; ?>

    <div class="sidebar">
        <div class="sidebar-top">
            <button type="button" id="mobileNavClose" class="mobile-nav-close" aria-label="Close navigation">
                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </button>
            <div class="logo-container">
                <img src="../images/logo.png" alt="Logo" class="logo-img">
            </div>
            <h2>Barangay Bolocboloc Property Inventory System</h2>
            <?php include __DIR__ . '/treasurer_sidebar.php'; ?>
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
                <h1><?= htmlspecialchars($bpis_page_heading) ?></h1>
                <?php if ($bpis_page_subtitle !== ''): ?>
                    <p><?= htmlspecialchars($bpis_page_subtitle) ?></p>
                <?php endif; ?>
            </div>
<?php include __DIR__ . '/admin_header_profile_icons.php'; ?>
        </div>
<?php if (!$bpis_skip_content_panel): ?>
        <div class="flex-grow bg-white border border-gray-300 rounded-xl flex flex-col min-h-0 w-full overflow-hidden shadow-sm mb-2">
            <div class="overflow-y-auto custom-scrollbar flex-grow p-6 md:p-8">
<?php endif; ?>
