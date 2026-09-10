<?php
session_start();

include __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth_helpers.php';
require_once __DIR__ . '/../config/asset_list_helpers.php';

bpis_require_login(['Barangay Captain']);

$type = strtolower(trim((string) ($_GET['type'] ?? 'borrowing')));
if (!in_array($type, ['inventory', 'borrowing'], true)) {
    $type = 'borrowing';
}

$month = trim((string) ($_GET['month'] ?? date('Y-m')));
if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
    $month = date('Y-m');
}

$month_start = $month . '-01';
$period = DateTime::createFromFormat('Y-m-d', $month_start) ?: new DateTime('first day of this month');
$month_end = $period->format('Y-m-t');
$month_label = $period->format('F Y');

$rows = [];
$title = $type === 'inventory'
    ? 'Barangay Inventory Summary'
    : 'Barangay Borrowing Activity Summary';

if ($type === 'inventory') {
    $stmt = $conn->prepare(
        'SELECT article, description, category, property_number, unit_measure, date_acquired, unit_value, remarks, quantity, status
         FROM asset
         WHERE date_acquired IS NOT NULL AND date_acquired >= ? AND date_acquired <= ?
         ORDER BY date_acquired DESC, id DESC'
    );
    if ($stmt) {
        $stmt->bind_param('ss', $month_start, $month_end);
        $stmt->execute();
        $rs = $stmt->get_result();
        while ($rs && ($row = $rs->fetch_assoc())) {
            $rows[] = $row;
        }
        $stmt->close();
    }
} else {
    $stmt = $conn->prepare(
        'SELECT b.id, b.request_id, b.asset_id, b.full_name, b.contact, b.address, b.qty, b.purpose,
                b.needed_on, b.return_date, b.status, b.created_at,
                a.description AS asset_desc, a.article AS asset_article
         FROM borrower b
         LEFT JOIN asset a ON a.id = b.asset_id
         WHERE COALESCE(b.needed_on, DATE(b.created_at)) >= ? AND COALESCE(b.needed_on, DATE(b.created_at)) <= ?
         ORDER BY COALESCE(b.needed_on, DATE(b.created_at)) DESC, b.id DESC'
    );
    if ($stmt) {
        $stmt->bind_param('ss', $month_start, $month_end);
        $stmt->execute();
        $rs = $stmt->get_result();
        while ($rs && ($row = $rs->fetch_assoc())) {
            $rows[] = $row;
        }
        $stmt->close();
    }
}

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?> — <?= htmlspecialchars($month_label) ?></title>
    <link rel="icon" type="image/x-icon" href="<?= bpis_app_base_path() ?>/favicon.ico">
    <style>
    body { 
        font-family: Segoe UI, Roboto, Arial, sans-serif; 
        margin: 24px; 
        color: #111; 
    }
    h1 { 
        font-size: 20px; 
        margin: 0 0 4px; 
    }
    p.meta { 
        color: #555; 
        font-size: 13px; 
        margin: 0 0 20px; 
    }
    table { 
        width: 100%; 
        border-collapse: collapse; 
        font-size: 11px; 
    }
    th, td { 
        border: 1px solid #333; 
        padding: 8px; 
        text-align: left; 
        vertical-align: top; 
    }
    th { 
        background: #f1f5f9; 
        text-transform: uppercase; 
        font-size: 10px; 
    }
    .empty { 
        padding: 32px; 
        text-align: center; 
        color: #666; 
        font-style: italic; 
    }
    .toolbar { 
        margin-bottom: 16px; 
    }
    .toolbar button { 
        background: #2563eb; 
        color: #fff; 
        border: none; 
        padding: 10px 18px; 
        border-radius: 8px; 
        font-size: 13px; 
        cursor: pointer; 
    }
    @media print { 
        .toolbar { 
            display: none; 
        } 
        body { 
            margin: 12px; 
        } 
    }

    @media (max-width: 1024px) {
        body {
            margin: 20px;
        }
        h1 {
            font-size: 18px;
        }
        table {
            font-size: 10px;
        }
        th, td {
            padding: 6px;
            font-size: 10px;
        }
        th {
            font-size: 9px;
        }
        .toolbar button {
            padding: 8px 14px;
            font-size: 12px;
        }
    }

    @media (max-width: 768px) {
        body {
            margin: 16px;
        }
        h1 {
            font-size: 17px;
        }
        p.meta {
            font-size: 12px;
            margin: 0 0 14px;
        }
        table {
            font-size: 9px;
            display: block;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        th, td {
            padding: 5px 6px;
            font-size: 9px;
            min-width: 80px;
            white-space: nowrap;
        }
        th {
            font-size: 8px;
        }
        .empty {
            padding: 24px;
            font-size: 12px;
        }
        .toolbar {
            margin-bottom: 12px;
        }
        .toolbar button {
            padding: 8px 12px;
            font-size: 11px;
            width: 100%;
        }
    }

    @media (max-width: 480px) {
        body {
            margin: 10px;
        }
        h1 {
            font-size: 15px;
        }
        p.meta {
            font-size: 11px;
            margin: 0 0 10px;
        }
        table {
            font-size: 8px;
        }
        th, td {
            padding: 4px 4px;
            font-size: 8px;
            min-width: 60px;
        }
        th {
            font-size: 7px;
        }
        .empty {
            padding: 16px;
            font-size: 11px;
        }
        .toolbar {
            margin-bottom: 10px;
        }
        .toolbar button {
            padding: 6px 10px;
            font-size: 10px;
            border-radius: 6px;
        }
    }

    @media (max-width: 360px) {
        body {
            margin: 6px;
        }
        h1 {
            font-size: 13px;
        }
        p.meta {
            font-size: 10px;
            margin: 0 0 8px;
        }
        table {
            font-size: 7px;
        }
        th, td {
            padding: 3px 3px;
            font-size: 7px;
            min-width: 50px;
        }
        th {
            font-size: 6px;
        }
        .empty {
            padding: 12px;
            font-size: 10px;
        }
        .toolbar button {
            padding: 5px 8px;
            font-size: 9px;
            border-radius: 4px;
        }
    }

    @media print {
        .toolbar { 
            display: none; 
        } 
        body { 
            margin: 12px; 
        }
        table {
            font-size: 10px;
        }
        th, td {
            padding: 4px;
            font-size: 10px;
        }
    }
</style>
</head>
<body>
    <div class="toolbar">
        <button type="button" onclick="window.print()">Print / Save as PDF</button>
    </div>
    <h1><?= htmlspecialchars($title) ?></h1>
    <p class="meta">Period: <?= htmlspecialchars($month_label) ?> (<?= htmlspecialchars($month_start) ?> to <?= htmlspecialchars($month_end) ?>)</p>

    <?php if (!$rows): ?>
        <p class="empty">No records found for this month.</p>
    <?php elseif ($type === 'inventory'): ?>
        <table>
            <thead>
                <tr>
                    <th>Article</th>
                    <th>Description</th>
                    <th>Category</th>
                    <th>Property No.</th>
                    <th>UOM</th>
                    <th>Date Acquired</th>
                    <th>Unit Value</th>
                    <th>Remarks</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars((string) ($row['article'] ?? '')) ?></td>
                        <td><?= htmlspecialchars((string) ($row['description'] ?? '')) ?></td>
                        <td><?= htmlspecialchars((string) ($row['category'] ?? '')) ?></td>
                        <td><?= htmlspecialchars((string) ($row['property_number'] ?? '')) ?></td>
                        <td><?= htmlspecialchars((string) ($row['unit_measure'] ?? '')) ?></td>
                        <td><?= !empty($row['date_acquired']) ? htmlspecialchars(date('F d, Y', strtotime((string) $row['date_acquired']))) : '' ?></td>
                        <td>₱ <?= htmlspecialchars(number_format((float) ($row['unit_value'] ?? 0), 2)) ?></td>
                        <td><?= htmlspecialchars(strtoupper((string) ($row['remarks'] ?? ''))) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Borrower</th>
                    <th>Address</th>
                    <th>Contact</th>
                    <th>Item</th>
                    <th>Qty</th>
                    <th>Needed On</th>
                    <th>Purpose</th>
                    <th>Return Date</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <?php
                    $item = trim((string) ($row['asset_desc'] ?? ''));
                    if ($item === '') {
                        $item = trim((string) ($row['asset_article'] ?? ''));
                    }
                    $addr = trim((string) ($row['address'] ?? ''));
                    if ($addr !== '' && !str_starts_with(strtolower($addr), 'purok')) {
                        $addr = 'Purok ' . $addr;
                    }
                    ?>
                    <tr>
                        <td>B-<?= htmlspecialchars(str_pad((string) ($row['request_id'] ?? $row['id'] ?? 0), 4, '0', STR_PAD_LEFT)) ?></td>
                        <td><?= htmlspecialchars((string) ($row['full_name'] ?? '')) ?></td>
                        <td><?= htmlspecialchars($addr) ?></td>
                        <td><?= htmlspecialchars((string) ($row['contact'] ?? '')) ?></td>
                        <td><?= htmlspecialchars($item) ?></td>
                        <td><?= htmlspecialchars((string) ($row['qty'] ?? '1')) ?></td>
                        <td><?= !empty($row['needed_on']) ? htmlspecialchars(date('F d, Y', strtotime((string) $row['needed_on']))) : '' ?></td>
                        <td><?= htmlspecialchars((string) ($row['purpose'] ?? '')) ?></td>
                        <td><?= !empty($row['return_date']) ? htmlspecialchars(date('F d, Y', strtotime((string) $row['return_date']))) : '' ?></td>
                        <td><?= htmlspecialchars((string) ($row['status'] ?? '')) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</body>
</html>