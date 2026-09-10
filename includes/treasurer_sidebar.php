<?php
$bpis_treasurer_nav_active = $bpis_treasurer_nav_active ?? '';
$bpis_nav_prefix = $bpis_nav_prefix ?? '';
?>
<nav class="sidebar-nav">
    <a href="<?= $bpis_nav_prefix ?>dashboard.php" class="nav-item<?= $bpis_treasurer_nav_active === 'dashboard' ? ' active' : '' ?>"><i class="fa-solid fa-house"></i><span class="nav-label">Dashboard</span></a>
    <a href="<?= $bpis_nav_prefix ?>inventory.php" class="nav-item<?= $bpis_treasurer_nav_active === 'inventory' ? ' active' : '' ?>"><i class="fa-solid fa-boxes-stacked"></i><span class="nav-label">Inventory</span></a>
    <a href="<?= $bpis_nav_prefix ?>asset_registration.php" class="nav-item<?= $bpis_treasurer_nav_active === 'register' ? ' active' : '' ?>"><i class="fa-solid fa-file-circle-plus"></i><span class="nav-label">Asset Registration</span></a>
    <a href="<?= $bpis_nav_prefix ?>depreciation.php" class="nav-item<?= $bpis_treasurer_nav_active === 'depreciation' ? ' active' : '' ?>"><i class="fa-solid fa-chart-line"></i><span class="nav-label">Depreciation</span></a>
    <a href="<?= $bpis_nav_prefix ?>inventory_period.php" class="nav-item<?= $bpis_treasurer_nav_active === 'period' ? ' active' : '' ?>"><i class="fa-solid fa-calendar-days"></i><span class="nav-label">Beginning / Ending</span></a>
    <a href="<?= $bpis_nav_prefix ?>inventory_history.php" class="nav-item<?= $bpis_treasurer_nav_active === 'history' ? ' active' : '' ?>"><i class="fa-solid fa-clock-rotate-left"></i><span class="nav-label">History</span></a>
    <a href="<?= $bpis_nav_prefix ?>audit.php" class="nav-item<?= $bpis_treasurer_nav_active === 'report' ? ' active' : '' ?>"><i class="fa-solid fa-clipboard-check"></i><span class="nav-label">Report</span></a>
    <a href="<?= $bpis_nav_prefix ?>report_scan.php" class="nav-item<?= $bpis_treasurer_nav_active === 'scan' ? ' active' : '' ?>"><i class="fa-solid fa-qrcode"></i><span class="nav-label">QR Scan Count</span></a>
</nav>
