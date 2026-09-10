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

$registered_borrowers = [];
$borrow_query = "SELECT * FROM borrower ORDER BY id DESC"; 
$borrow_result = mysqli_query($conn, $borrow_query);

if ($borrow_result) {
    while ($row = mysqli_fetch_assoc($borrow_result)) {
        $registered_borrowers[] = $row;
    }
}

$asset_descriptions = [];
$asset_result = mysqli_query($conn, "SELECT id, description FROM asset");
if ($asset_result) {
    while ($asset = mysqli_fetch_assoc($asset_result)) {
        $asset_descriptions[(int)$asset['id']] = $asset['description'] ?? '';
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
    <title>Borrowers Logs</title>
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
        padding: 25px 35px; 
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
        margin-bottom: 20px; 
        border: 1px solid #eee; 
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
        font-size: 13px; 
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
    code { 
        background: #f1f5f9; 
        padding: 2px 6px; 
        border-radius: 4px; 
        color: #475569; 
        font-size: 12px; 
    }
    .captain-borrowing-toolbar {
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
    }
    .captain-borrowing-toolbar .bpis-date-filter-label {
        flex: 0 0 auto;
    }
    .captain-borrowing-export {
        margin-left: auto;
    }
    .captain-borrowing-scroll {
        width: 100%;
        overflow: auto;
        -webkit-overflow-scrolling: touch;
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
            padding: 20px 25px;
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
        table {
            font-size: 12px;
        }
        th {
            font-size: 12px;
            padding: 8px;
        }
        td {
            font-size: 11px;
            padding: 10px 8px;
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
            padding: 15px 15px;
        }
        .header {
            flex-wrap: wrap;
            gap: 10px;
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
        .table-section {
            padding: 12px;
            overflow-x: auto;
        }
        .captain-borrowing-toolbar {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            width: 100%;
        }
        .captain-borrowing-toolbar .bpis-date-filter-label,
        .captain-borrowing-toolbar .bpis-date-clear-btn,
        .captain-borrowing-toolbar .captain-borrowing-export {
            grid-column: 1 / -1;
        }
        .captain-borrowing-toolbar label {
            width: 100%;
            justify-content: space-between;
        }
        .captain-borrowing-toolbar input[type="date"] {
            width: min(100%, 160px);
        }
        .captain-borrowing-export {
            margin-left: 0 !important;
            width: 100%;
            justify-content: center;
            min-height: 40px;
        }
        .captain-borrowing-scroll {
            max-height: calc(100dvh - 260px);
            overflow-x: auto;
            overflow-y: auto;
        }
        .table-section h3 {
            font-size: 14px;
        }
        table {
            font-size: 11px;
            min-width: 500px;
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
            padding: 10px 10px;
        }
        .header {
            flex-direction: column;
            align-items: flex-start;
            gap: 8px;
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
        .table-section {
            padding: 10px;
            border-radius: 8px;
        }
        .captain-borrowing-toolbar {
            grid-template-columns: 1fr;
            gap: 8px;
        }
        .captain-borrowing-toolbar input[type="date"] {
            width: min(58vw, 150px);
        }
        .captain-borrowing-scroll {
            max-height: calc(100dvh - 235px);
        }
        .table-section h3 {
            font-size: 13px;
        }
        table {
            font-size: 10px;
            min-width: 400px;
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
            top: 48px;
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
        code {
            font-size: 10px;
            padding: 1px 4px;
        }
        .highlight-text {
            font-size: 11px;
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
            padding: 8px 6px;
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
        .table-section {
            padding: 8px;
            border-radius: 6px;
        }
        .captain-borrowing-scroll {
            max-height: calc(100dvh - 215px);
        }
        .captain-borrowing-export {
            font-size: 11px !important;
            padding: 7px 10px !important;
        }
        .table-section h3 {
            font-size: 12px;
        }
        table {
            font-size: 9px;
            min-width: 320px;
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
            top: 42px;
            padding-top: 14px;
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
    }
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
             $bpis_captain_nav_active = 'borrowing';
             include __DIR__ . '/../includes/captain_sidebar.php';
             ?>
        </div>
        <div class="sidebar-bottom">Brgy. Bolocboloc, Sibulan<br>Negros Oriental, Philippines 6201<br>@2026</div>
    </div>

    <div class="sidebar-overlay" id="sidebarOverlay" aria-hidden="true"></div>

    <div class="main-content">
        <div class="header">
            <button type="button" id="mobileNavToggle" class="mobile-nav-toggle" aria-label="Toggle navigation" aria-expanded="false">
                <i class="fa-solid fa-bars" aria-hidden="true"></i>
            </button>
            
            <div>
                <h1>Borrowers Logs</h1>
                <p>Tracking of borrowed barangay equipment and return statuses.</p>
            </div>
<?php include __DIR__ . '/../includes/admin_header_profile_icons.php'; ?>
            
</div>
         <div class="table-section captain-borrowing-card" data-bpis-date-filter-scope>
            <div class="bpis-date-filter-bar captain-borrowing-toolbar">
                    <span class="bpis-date-filter-label">Filter by date</span>
                    <label>From <input type="date" data-bpis-date-from aria-label="Filter from date"></label>
                    <label>To <input type="date" data-bpis-date-to aria-label="Filter to date"></label>
            <button type="button" class="bpis-date-clear-btn" data-bpis-date-clear>Clear dates</button>
             <a href="export_summary_report.php?type=borrowing&amp;month=<?= htmlspecialchars(date('Y-m'), ENT_QUOTES, 'UTF-8') ?>"
                target="_blank" rel="noopener"
                id="exportSummaryBtn"
                class="captain-borrowing-export ml-auto bg-[#3B82F6] hover:bg-blue-600 text-white font-medium text-sm py-2.5 px-6 rounded-lg shadow-sm transition-colors flex items-center gap-2 whitespace-nowrap no-underline">
                <i data-lucide="file-text" class="w-4 h-4"></i>
                Export Monthly Summary
            </a>
</div>
      <div class="captain-borrowing-scroll">
      <table>
                <thead>
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
                        <td colspan="10" style="padding:40px; color:#888;font-style: italic;">No registered borrowers found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($registered_borrowers as $borrower): ?>
                        <tr class="bor-log-row"<?= bpis_row_date_attr($borrower, ['needed_on', 'date_needed', 'return_date']) ?>>
                            <td>B-<?= str_pad($borrower['request_id'] ?? 0, 4, '0', STR_PAD_LEFT) ?></td>
                            <td>
                                <span class="borrower-name"><?= htmlspecialchars($borrower['full_name'] ?? '') ?></span>
                            </td>
                            <td><?= htmlspecialchars((str_starts_with(strtolower(trim((string)($borrower['address'] ?? ''))), 'purok') ? '' : 'Purok ') . ($borrower['address'] ?? '')) ?></td>
                            <td><?= htmlspecialchars($borrower['contact'] ?? '') ?></td>
                            <td><?= htmlspecialchars($borrower['item'] ?? ($asset_descriptions[(int)($borrower['asset_id'] ?? 0)] ?? '')) ?></td>
                            <td><?= htmlspecialchars($borrower['qty'] ?? $borrower['quantity'] ?? '1') ?></td>
                            <td>
                                <?= !empty($borrower['needed_on'] ?? $borrower['date_needed']) 
                                    ? htmlspecialchars(date("F d, Y", strtotime($borrower['needed_on'] ?? $borrower['date_needed']))) 
                                    : '' ?>
                            </td>
                            <td><?= htmlspecialchars($borrower['purpose'] ?? '') ?></td>
                            <td>
                                <?= !empty($borrower['return_date']) 
                                    ? htmlspecialchars(date("F d, Y", strtotime($borrower['return_date']))) 
                                    : '' ?>
                            </td>
                            <td>
                                <?php
                                $borrow_status = (string) ($borrower['status'] ?? '');
                                if ($borrow_status === 'Returned'): ?>
                                    <span class="status-item-returned">Item Returned</span>
                                <?php elseif ($borrow_status === 'Received'): ?>
                                    <span class="status-badge status-borrowed">Received</span>
                                <?php elseif ($borrow_status === 'Overdue'): ?>
                                    <span class="status-badge" style="color:#b45309;font-weight:700;font-size:11px;">Overdue</span>
                                <?php else: ?>
                                    <span style="color: #1e40af; font-weight: bold; font-size: 11px;"><?= htmlspecialchars(strtoupper($borrow_status)) ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
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

        logoutOverlay.addEventListener('click', function(e) {
            if (e.target === logoutOverlay) {
                logoutOverlay.style.display = 'none';
            }
        });
        lucide.createIcons();
    </script>
<script src="../realtime_notifications.js"></script>
<script src="../js/captain_mobile_sidebar.js"></script>
</body>
</html>
