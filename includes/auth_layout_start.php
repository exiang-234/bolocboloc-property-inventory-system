<?php

require_once __DIR__ . '/../config/auth_helpers.php';
$bpis_base = bpis_app_base_path();

$auth_page_title = $auth_page_title ?? 'BPIS';
$auth_illustration = $auth_illustration ?? '';
$auth_body_class = trim((string) ($auth_body_class ?? ''));
$auth_heading = $auth_heading ?? 'Bolocboloc Property Inventory System';
$auth_lead = $auth_lead ?? '';
$auth_step = (int) ($auth_step ?? 0);
$auth_total_steps = max(1, (int) ($auth_total_steps ?? 1));
$auth_show_back = !empty($auth_show_back);
$auth_back_href = $auth_back_href ?? 'login.php';
$auth_wide = !empty($auth_wide);
$auth_show_steps = $auth_step > 0 && $auth_total_steps > 1;
$auth_use_hero = $auth_illustration !== '';
?>
<!DOCTYPE html>
<html lang="en" class="borrow-flow-root">
<head>
    <link rel="icon" type="image/png" href="<?= $bpis_base ?>/images/logo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#f4f6f8">
    <title><?= htmlspecialchars($auth_page_title) ?></title>
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
        } catch(e) { console.warn('Theme init failed:', e); }
    })();
    </script>
    <link rel="stylesheet" href="<?= $bpis_base ?>/borrower/borrow_form.css?v=20260909b">
    <link rel="stylesheet" href="<?= $bpis_base ?>/css/auth_flow.css?v=20260909b">
    <link rel="stylesheet" href="<?= $bpis_base ?>/css/bpis_dark_theme.css?v=20260909b">
    <script src="<?= $bpis_base ?>/js/bpis_theme.js"></script>
    <?= $auth_extra_head ?? '' ?>
</head>
<body class="borrow-flow borrow-auth-flow<?= $auth_body_class !== '' ? ' ' . htmlspecialchars($auth_body_class, ENT_QUOTES) : '' ?>">
<button type="button" class="borrow-theme-toggle" aria-label="Toggle theme" title="Toggle theme (Dark / Light)">
    <i class="fa-solid fa-moon" aria-hidden="true"></i>
</button>
<div class="borrow-page<?= $auth_wide ? ' borrow-page--wide' : '' ?><?= $auth_use_hero ? ' borrow-page--hero-auth' : '' ?>">
    <div class="borrow-card<?= $auth_use_hero ? ' borrow-card--login-hero' : '' ?>">
<?php if ($auth_use_hero): ?>
        <div class="auth-hero-inner">
            <section class="auth-hero-panel" aria-hidden="true">
                <img class="auth-hero-art" src="<?= htmlspecialchars($auth_illustration, ENT_QUOTES) ?>" alt="" width="400" height="300" decoding="async">
            </section>
            <div class="auth-hero-body">
<?php endif; ?>
        <?php if ($auth_show_back): ?>
            <a href="<?= htmlspecialchars($auth_back_href) ?>" class="borrow-back" aria-label="Go back">← Back</a>
        <?php endif; ?>

        <div class="borrow-top<?= $auth_use_hero ? ' borrow-top--card' : '' ?>">
            <div class="borrow-brand borrow-brand--auth">
                <img src="<?= $bpis_base ?>/images/logo.png" alt="Barangay Bolocboloc" width="96" height="96" decoding="async">
                <span class="borrow-brand-text">Barangay Bolocboloc Property Inventory System</span>
            </div>
            <?php if ($auth_show_steps): ?>
                <p class="borrow-step-label">Step <strong><?= $auth_step ?></strong> of <?= $auth_total_steps ?></p>
                <div class="borrow-steps" aria-hidden="true">
                    <?php for ($i = 1; $i <= $auth_total_steps; $i++): ?>
                        <?php
                        $stepClass = 'borrow-step';
                        if ($i < $auth_step) {
                            $stepClass .= ' is-done';
                        } elseif ($i === $auth_step) {
                            $stepClass .= ' is-active';
                        }
                        ?>
                        <span class="<?= $stepClass ?>"></span>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
            <h1 class="borrow-title"><?= htmlspecialchars($auth_heading) ?></h1>
            <?php if ($auth_lead !== ''): ?>
                <p class="borrow-lead"><?= htmlspecialchars($auth_lead) ?></p>
            <?php endif; ?>
        </div>
