<?php
if (!empty($GLOBALS['bpis_app_meta_linked'])) {
    return;
}
$GLOBALS['bpis_app_meta_linked'] = true;

if (!function_exists('bpis_app_base_path')) {
    require_once __DIR__ . '/../config/auth_helpers.php';
}
$bpis_base = bpis_app_base_path();
$bpis_feed = rtrim($bpis_base, '/') . '/notifications_feed.php';
$bpis_mark_read = rtrim($bpis_base, '/') . '/mark_notification_read.php';
$bpis_mark_all = rtrim($bpis_base, '/') . '/mark_all_notifications_read.php';
?>
<meta name="bpis-base" content="<?= htmlspecialchars($bpis_base, ENT_QUOTES, 'UTF-8') ?>">
<meta name="bpis-notifications-feed" content="<?= htmlspecialchars($bpis_feed, ENT_QUOTES, 'UTF-8') ?>">
<meta name="bpis-mark-notification-read" content="<?= htmlspecialchars($bpis_mark_read, ENT_QUOTES, 'UTF-8') ?>">
<meta name="bpis-mark-all-notifications-read" content="<?= htmlspecialchars($bpis_mark_all, ENT_QUOTES, 'UTF-8') ?>">
<script>
(function() {
    try {
        var t = localStorage.getItem('bpis_theme');
        if (!t && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            t = 'dark';
        }
        if (t === 'dark') {
            document.documentElement.classList.add('dark');
            document.documentElement.setAttribute('data-theme', 'dark');
        } else {
            document.documentElement.classList.remove('dark');
            document.documentElement.setAttribute('data-theme', 'light');
        }
    } catch(e) { console.warn('BPIS theme init failed:', e); }
})();
</script>
<link rel="stylesheet" href="<?= htmlspecialchars(rtrim($bpis_base, '/') . '/css/bpis_dark_theme.css?v=20250906pervariant', ENT_QUOTES, 'UTF-8') ?>">
<script src="<?= htmlspecialchars(rtrim($bpis_base, '/') . '/js/bpis_theme.js?v=20250906pervariant', ENT_QUOTES, 'UTF-8') ?>"></script>
<link rel="stylesheet" href="<?= htmlspecialchars(rtrim($bpis_base, '/') . '/css/stat_cards.css?v=20250906pervariant', ENT_QUOTES, 'UTF-8') ?>">
<style id="bpis-theme-critical">
/* Painted before the stylesheet lands so dark mode never flashes white. */
html[data-theme="dark"], html.dark { background: #0b1329 !important; }
html[data-theme="dark"] body, html.dark body, body.dark-mode, body.dark {
    background: #0b1329 !important;
    color: #f8fafc !important;
}
/* Stat card numbers: one rule per colour, resolved through the theme tokens
   so each theme gets a shade that actually reads on its own card background.
   CSS alone covers cards added to the DOM later — no scripted repainting. */
.card.card-blue .card-number   { color: var(--stat-blue, #1d4ed8) !important; }
.card.card-yellow .card-number { color: var(--stat-yellow, #d97706) !important; }
.card.card-green .card-number  { color: var(--stat-green, #059669) !important; }
.card.card-red .card-number    { color: var(--stat-red, #dc2626) !important; }
.card .card-number { opacity: 1 !important; visibility: visible !important; }
html[data-theme="dark"] .cards-container > .card p,
html.dark .cards-container > .card p { color: var(--text-secondary, #cbd5e1) !important; }
</style>
