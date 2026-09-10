<?php
$bpis_treasurer_nav_active = $bpis_treasurer_nav_active ?? '';
$links = [
    'dashboard' => ['dashboard.php', 'Dashboard'],
    'inventory' => ['inventory.php', 'Inventory'],
    'register' => ['asset_registration.php', 'Registration'],
    'depreciation' => ['depreciation.php', 'Depreciation'],
    'period' => ['inventory_period.php', 'Beg/End'],
    'history' => ['inventory_history.php', 'History'],
    'report' => ['audit.php', 'Report'],
    'scan' => ['report_scan.php', 'QR Scan'],
];
?>
<nav class="flex flex-wrap gap-2 mb-6 p-3 bg-white border border-slate-200 rounded-xl text-sm">
<?php foreach ($links as $key => $pair): ?>
    <a href="<?= htmlspecialchars($pair[0]) ?>" class="px-3 py-1.5 rounded-lg font-semibold <?= $bpis_treasurer_nav_active === $key ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' ?>"><?= htmlspecialchars($pair[1]) ?></a>
<?php endforeach; ?>
</nav>
