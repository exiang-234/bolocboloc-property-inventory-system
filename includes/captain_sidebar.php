<?php
$bpis_captain_nav_active = $bpis_captain_nav_active ?? '';
$bpis_nav_prefix = $bpis_nav_prefix ?? '';
?>
<nav class="sidebar-nav">
    <a href="<?= $bpis_nav_prefix ?>dashboard.php" class="nav-item<?= $bpis_captain_nav_active === 'dashboard' ? ' active' : '' ?>"><i class="fa-solid fa-house"></i>Dashboard</a>
    <a href="<?= $bpis_nav_prefix ?>inventory_logs.php" class="nav-item<?= $bpis_captain_nav_active === 'inventory' ? ' active' : '' ?>"><i class="fa-solid fa-box-archive"></i>Inventory Logs</a>
    <a href="<?= $bpis_nav_prefix ?>borrowing_logs.php" class="nav-item<?= $bpis_captain_nav_active === 'borrowing' ? ' active' : '' ?>"><i class="fa-solid fa-book-open-reader"></i>Borrower Logs</a>
    <?php if (function_exists('bpis_is_super_admin') && bpis_is_super_admin()): ?>
    <a href="<?= $bpis_nav_prefix ?>users.php" class="nav-item<?= $bpis_captain_nav_active === 'users' ? ' active' : '' ?>"><i class="fa-solid fa-user-gear"></i>User Accounts</a>
    <?php endif; ?>
</nav>
