<?php
require_once __DIR__ . '/../../config/auth_helpers.php';
$borrow_step = (int) ($borrow_step ?? 1);
$borrow_page_title = $borrow_page_title ?? 'Borrowing Request';
$borrow_heading = $borrow_heading ?? 'Online Borrowing Request';
$borrow_lead = $borrow_lead ?? '';
$borrow_wide = !empty($borrow_wide);
$borrow_show_back = !empty($borrow_show_back);
$borrow_back_href = $borrow_back_href ?? 'step1.php';
?>
<!DOCTYPE html>
<html lang="en" class="borrow-flow-root">
<head>
    <link rel="icon" type="image/png" href="<?= bpis_app_base_path() ?>/images/logo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#f4f6f8">
    <title><?= htmlspecialchars($borrow_page_title) ?></title>
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
    <link rel="stylesheet" href="borrow_form.css">
    <link rel="stylesheet" href="../css/bpis_dark_theme.css">
    <script src="../js/bpis_theme.js"></script>
    <?php if ($borrow_step === 2): ?>
    <link rel="stylesheet" href="../css/asset_inventory_image.css">
    <?php endif; ?>
    <?= $borrow_extra_head ?? '' ?>
</head>
<body class="borrow-flow borrow-request-flow">
<button type="button" class="borrow-theme-toggle" aria-label="Toggle theme" title="Toggle theme (Dark / Light)">
    <i class="fa-solid fa-moon" aria-hidden="true"></i>
</button>
<div class="borrow-page<?= $borrow_wide ? ' borrow-page--wide' : '' ?>">
    <div class="borrow-card">
        <?php if ($borrow_show_back): ?>
            <a href="<?= htmlspecialchars($borrow_back_href) ?>" class="borrow-back" aria-label="Go back">← Back</a>
        <?php endif; ?>

        <div class="borrow-top">
            <div class="borrow-brand">
                <img src="../images/logo.png" alt="" width="44" height="44">
                <span class="borrow-brand-text">Barangay Bolocboloc Property Inventory System</span>
            </div>
            <p class="borrow-step-label">Step <strong><?= $borrow_step ?></strong> of 2</p>
            <div class="borrow-steps" aria-hidden="true">
                <span class="borrow-step<?= $borrow_step > 1 ? ' is-done' : ($borrow_step === 1 ? ' is-active' : '') ?>"></span>
                <span class="borrow-step<?= $borrow_step === 2 ? ' is-active' : '' ?>"></span>
            </div>
            <h1 class="borrow-title"><?= htmlspecialchars($borrow_heading) ?></h1>
            <?php if ($borrow_lead !== ''): ?>
                <p class="borrow-lead"><?= htmlspecialchars($borrow_lead) ?></p>
            <?php endif; ?>
        </div>
