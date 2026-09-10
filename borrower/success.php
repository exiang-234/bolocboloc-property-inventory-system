<?php require_once __DIR__ . '/../config/auth_helpers.php'; ?>
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
    <title>Request submitted</title>
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
</head>
<body class="borrow-flow borrow-flow--success">
    <button type="button" class="borrow-theme-toggle" aria-label="Toggle theme" title="Toggle theme (Dark / Light)">
        <i class="fa-solid fa-moon" aria-hidden="true"></i>
    </button>
    <div class="borrow-page">
        <div class="borrow-card borrow-success-card">
            <div class="borrow-brand">
                <img src="../images/logo.png" alt="" width="44" height="44">
                <span class="borrow-brand-text"> Barangay Bolocboloc Property Inventory System</span>
            </div>

            <div class="borrow-success-icon" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M20 6L9 17l-5-5"/>
                </svg>
            </div>

            <h1 class="borrow-success-title">Request submitted</h1>
            <p class="borrow-success-message">
                Your borrowing request has been sent to the barangay office. Please check your email for confirmation and further instructions.
            </p>

            <div class="borrow-success-tips">
                <strong>What happens next?</strong>
                The secretary will review your request. You may be contacted for approval or pickup details. Keep your contact number available.
            </div>

            <div class="borrow-success-actions">
                <a href="step1.php" class="borrow-btn">Submit another request</a>
            </div>
        </div>
    </div>
</body>
</html>
