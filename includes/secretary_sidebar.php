<?php
$bpis_secretary_nav_active = $bpis_secretary_nav_active ?? '';
$bpis_nav_prefix = $bpis_nav_prefix ?? '';
?>
<nav class="sidebar-nav">
    <a href="<?= $bpis_nav_prefix ?>dashboard.php" class="nav-item<?= $bpis_secretary_nav_active === 'dashboard' ? ' active' : '' ?>"><i class="fa-solid fa-house"></i>Dashboard</a>
    <a href="<?= $bpis_nav_prefix ?>requests.php" class="nav-item<?= $bpis_secretary_nav_active === 'requests' ? ' active' : '' ?>"><i class="fa-solid fa-clipboard-list"></i>Requests</a>
    <a href="<?= $bpis_nav_prefix ?>borrowers.php" class="nav-item<?= $bpis_secretary_nav_active === 'borrowers' ? ' active' : '' ?>"><i class="fa-solid fa-users"></i>Borrowers</a>
    <a href="<?= $bpis_nav_prefix ?>overdue.php" class="nav-item<?= $bpis_secretary_nav_active === 'overdue' ? ' active' : '' ?>"><i class="fa-solid fa-triangle-exclamation"></i>Overdue</a>
    <a href="<?= $bpis_nav_prefix ?>blocklist.php" class="nav-item<?= $bpis_secretary_nav_active === 'blocklist' ? ' active' : '' ?>"><i class="fa-solid fa-ban"></i>Blocklist</a>
</nav>
