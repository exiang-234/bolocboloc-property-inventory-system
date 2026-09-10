<?php
/**
 * @deprecated Use captain_layout_start.php instead.
 */
$bpis_captain_nav_active = $bpis_captain_nav_active ?? '';
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="../assets/css/secretary-nav.css">
<div class="bpis-secretary-sidebar">
    <div class="bpis-secretary-sidebar-top">
        <div class="bpis-secretary-logo">
            <img src="../images/logo.png" alt="Barangay Logo">
        </div>
        <h2>Barangay Bolocboloc Property Inventory System</h2>
        <?php include __DIR__ . '/captain_sidebar.php'; ?>
    </div>
    <div class="bpis-secretary-sidebar-bottom">Brgy. Bolocboloc, Sibulan<br>Negros Oriental, Philippines, 6201<br>@2026</div>
</div>
