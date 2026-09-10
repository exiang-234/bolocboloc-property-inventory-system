<?php


$is_cli = PHP_SAPI === 'cli';
$dry_run = false;

if ($is_cli) {
    $dry_run = in_array('--dry-run', $argv ?? [], true);
} else {
    session_start();
    $dry_run = isset($_GET['dry_run']);
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth_helpers.php';
require_once __DIR__ . '/../config/asset_units_helpers.php';

if (!$is_cli) {
    bpis_require_login(['Treasurer', 'Barangay Captain']);
}

$result = bpis_backfill_asset_units($conn, $dry_run);

if ($is_cli) {
    $mode = $dry_run ? 'DRY RUN' : 'APPLIED';
    echo "[{$mode}] Assets without units: {$result['assets']}\n";
    echo "Unit rows " . ($dry_run ? 'would be created' : 'created') . ": {$result['created']}\n";
    exit(0);
}

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Backfill asset units</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 p-6">
<div class="max-w-lg mx-auto bg-white rounded-2xl shadow p-6">
    <h1 class="text-xl font-bold mb-2">Backfill asset units</h1>
    <p class="text-sm text-slate-600 mb-4">
        Creates one <code class="bg-slate-100 px-1 rounded">asset_units</code> row per quantity for assets that do not have any units yet.
    </p>
    <?php if ($dry_run): ?>
        <p class="text-amber-700 text-sm mb-3 font-semibold">Dry run — no database changes were made.</p>
    <?php else: ?>
        <p class="text-green-700 text-sm mb-3 font-semibold">Backfill completed.</p>
    <?php endif; ?>
    <ul class="text-sm space-y-1 mb-6">
        <li>Assets processed: <strong><?= (int) $result['assets'] ?></strong></li>
        <li>Unit rows <?= $dry_run ? 'that would be created' : 'created' ?>: <strong><?= (int) $result['created'] ?></strong></li>
    </ul>
    <div class="flex flex-wrap gap-3 text-sm">
        <?php if ($dry_run): ?>
            <a href="backfill_asset_units.php" class="bg-blue-600 text-white px-4 py-2 rounded-lg font-semibold">Run for real</a>
        <?php else: ?>
            <a href="backfill_asset_units.php?dry_run=1" class="border border-slate-300 px-4 py-2 rounded-lg font-semibold">Dry run again</a>
        <?php endif; ?>
        <a href="../treasurer/inventory.php" class="text-blue-600 font-semibold py-2">← Inventory</a>
    </div>
</div>
</body>
</html>
