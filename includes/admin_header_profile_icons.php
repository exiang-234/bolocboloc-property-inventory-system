<?php
$bpis_profile_link = $bpis_profile_link ?? '../profile.php';
$bpis_avatar_fallback = $bpis_avatar_fallback ?? '../images/profile_logo.png';
$bpis_notifications_icon = $bpis_notifications_icon ?? '../images/notification.png';
$bpis_notifications_css = $bpis_notifications_css ?? '../css/bpis_notifications.css';
$bpis_header_avatar = $bpis_header_avatar ?? $bpis_avatar_fallback;
$bpis_profile_nav_active = !empty($bpis_profile_nav_active);
if (empty($GLOBALS['bpis_notifications_css_linked'])) {
    $GLOBALS['bpis_notifications_css_linked'] = true;
    echo '<link rel="stylesheet" href="' . htmlspecialchars($bpis_notifications_css, ENT_QUOTES, 'UTF-8') . '">' . "\n";
}
include __DIR__ . '/bpis_app_meta.php';
if (!function_exists('bpis_app_base_path')) {
    require_once __DIR__ . '/../config/auth_helpers.php';
}
$bpis_api_base = rtrim(bpis_app_base_path(), '/');
if (empty($GLOBALS['bpis_api_script_linked'])) {
    $GLOBALS['bpis_api_script_linked'] = true;
    $bpis_api = [
        'feed' => $bpis_api_base . '/notifications_feed.php',
        'markRead' => $bpis_api_base . '/mark_notification_read.php',
        'markAll' => $bpis_api_base . '/mark_all_notifications_read.php',
    ];
    echo '<script>window.BPIS_API=' . json_encode($bpis_api, JSON_UNESCAPED_SLASHES) . ';</script>' . "\n";
}
?>
            <div class="header-icons">
                <button type="button" class="bpis-notif-trigger" aria-label="Notifications" aria-haspopup="dialog" aria-expanded="false">
                    <img src="<?= htmlspecialchars($bpis_notifications_icon) ?>" alt="" class="icon-box">
                </button>
                <img src="<?= htmlspecialchars($bpis_header_avatar) ?>" alt="" class="icon-circle" id="profileTrigger"
                     onerror="this.onerror=null;this.src='<?= htmlspecialchars($bpis_avatar_fallback, ENT_QUOTES) ?>';">
                <div class="profile-dropdown" id="profileMenu" role="menu" aria-label="Account menu">
                    <div class="bpis-profile-dropdown-head profile-user-info">
                        <div class="bpis-profile-dropdown-avatar">
                            <img src="<?= htmlspecialchars($bpis_header_avatar) ?>" alt=""
                                 onerror="this.onerror=null;this.src='<?= htmlspecialchars($bpis_avatar_fallback, ENT_QUOTES) ?>';">
                        </div>
                        <div class="bpis-profile-dropdown-meta">
                            <h4 class="bpis-profile-dropdown-name"><?= htmlspecialchars($user_fullname ?? 'User') ?></h4>
                            <p class="bpis-profile-dropdown-role"><?= htmlspecialchars($user_role ?? '') ?></p>
                        </div>
                    </div>
                    <nav class="bpis-profile-dropdown-nav profile-links">
                        <a href="<?= htmlspecialchars($bpis_profile_link) ?>"
                           class="bpis-profile-dropdown-item<?= $bpis_profile_nav_active ? ' active' : '' ?>"
                           role="menuitem">
                            <i class="fa-regular fa-user" aria-hidden="true"></i>
                            <span>Profile</span>
                        </a>
                        <button type="button" class="bpis-profile-dropdown-item bpis-theme-toggle-item" id="dropdownThemeToggle" role="menuitem">
                            <span class="theme-switch-wrap">
                                <i class="fa-solid fa-moon" aria-hidden="true"></i>
                                <span class="theme-switch-text">Dark Mode</span>
                            </span>
                            <span class="theme-switch-track"><span class="theme-switch-thumb"></span></span>
                        </button>
                        <a href="#" class="bpis-profile-dropdown-item bpis-profile-dropdown-signout sign-out" id="logoutBtn" role="menuitem">
                            <i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i>
                            <span>Sign out</span>
                        </a>
                    </nav>
                </div>
            </div>
