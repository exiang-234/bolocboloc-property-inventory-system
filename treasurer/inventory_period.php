<?php
session_start();
include '../config/database.php';
require_once __DIR__ . '/../config/auth_helpers.php';
require_once __DIR__ . '/../config/coa_labels.php';
require_once __DIR__ . '/../config/treasurer_page_helpers.php';
require_once __DIR__ . '/../config/csrf_helpers.php';
bpis_require_login(['Treasurer', 'Barangay Captain']);

$bpis_treasurer_nav_active = 'period';
$bpis_u = bpis_treasurer_load_user($conn);
$user_fullname = $bpis_u['fullname'];
$user_role = $bpis_u['role'];

function bpis_create_notifications_table($conn) {
    $create_sql = "CREATE TABLE IF NOT EXISTS admin_notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        type ENUM('info', 'success', 'warning', 'danger') DEFAULT 'info',
        is_read TINYINT DEFAULT 0,
        link VARCHAR(500),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user (user_id),
        INDEX idx_read (is_read),
        INDEX idx_created (created_at)
    )";
    mysqli_query($conn, $create_sql);
}
bpis_create_notifications_table($conn);

function bpis_add_notification($conn, $user_id, $title, $message, $type = 'info', $link = '') {
    $stmt = $conn->prepare("INSERT INTO admin_notifications (user_id, title, message, type, link) VALUES (?, ?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param('issss', $user_id, $title, $message, $type, $link);
        $stmt->execute();
        $stmt->close();
        return true;
    }
    return false;
}

function bpis_ensure_periods_table($conn) {
    $create_sql = "CREATE TABLE IF NOT EXISTS inventory_periods (
        id INT AUTO_INCREMENT PRIMARY KEY,
        period_type ENUM('Monthly', 'Quarterly', 'Annual') NOT NULL,
        period_label VARCHAR(100) NOT NULL,
        period_start DATE NOT NULL,
        period_end DATE NOT NULL,
        beginning_qty INT NOT NULL DEFAULT 0,
        ending_qty INT NOT NULL DEFAULT 0,
        beginning_value DECIMAL(15,2) NOT NULL DEFAULT 0,
        ending_value DECIMAL(15,2) NOT NULL DEFAULT 0,
        net_change_qty INT DEFAULT 0,
        net_change_value DECIMAL(15,2) DEFAULT 0,
        remarks TEXT,
        created_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_period (period_type, period_label)
    )";
    mysqli_query($conn, $create_sql);

    $required_columns = [
        'period_type' => "ENUM('Monthly', 'Quarterly', 'Annual') NOT NULL DEFAULT 'Annual'",
        'period_label' => "VARCHAR(100) NOT NULL",
        'beginning_qty' => 'INT NOT NULL DEFAULT 0',
        'ending_qty' => 'INT NOT NULL DEFAULT 0',
        'beginning_value' => 'DECIMAL(15,2) NOT NULL DEFAULT 0',
        'ending_value' => 'DECIMAL(15,2) NOT NULL DEFAULT 0',
        'net_change_qty' => 'INT DEFAULT 0',
        'net_change_value' => 'DECIMAL(15,2) DEFAULT 0',
        'remarks' => 'TEXT',
        'created_by' => 'INT'
    ];

    foreach ($required_columns as $col => $definition) {
        $col_esc = mysqli_real_escape_string($conn, $col);
        $q = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inventory_periods' AND COLUMN_NAME = '" . $col_esc . "' LIMIT 1";
        $res = mysqli_query($conn, $q);
        if (!$res || mysqli_num_rows($res) === 0) {
            $alter = "ALTER TABLE inventory_periods ADD COLUMN {$col} {$definition}";
            mysqli_query($conn, $alter);
        }
    }

    $idxRes = mysqli_query($conn, "SHOW INDEX FROM inventory_periods WHERE Key_name = 'idx_period'");
    if (!$idxRes || mysqli_num_rows($idxRes) === 0) {
        mysqli_query($conn, "ALTER TABLE inventory_periods ADD INDEX idx_period (period_type, period_label)");
    }
}
bpis_ensure_periods_table($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && bpis_table_exists($conn, 'inventory_periods')) {
    $type = in_array($_POST['period_type'] ?? '', ['Monthly', 'Quarterly', 'Annual'], true) ? $_POST['period_type'] : 'Annual';
    $label = trim((string) ($_POST['period_label'] ?? ''));
    $start = (string) ($_POST['period_start'] ?? '');
    $end = (string) ($_POST['period_end'] ?? '');
    $bq = (int) ($_POST['beginning_qty'] ?? 0);
    $eq = (int) ($_POST['ending_qty'] ?? 0);
    $bv = (float) ($_POST['beginning_value'] ?? 0);
    $ev = (float) ($_POST['ending_value'] ?? 0);
    $remarks = trim((string) ($_POST['remarks'] ?? ''));
    $by = (int) ($_SESSION['admin_id'] ?? 0);
    
    $net_qty = $eq - $bq;
    $net_value = $ev - $bv;
    
    if ($label !== '' && $start !== '' && $end !== '') {
        $stmt = $conn->prepare('INSERT INTO inventory_periods (period_type, period_label, period_start, period_end, beginning_qty, ending_qty, beginning_value, ending_value, net_change_qty, net_change_value, remarks, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
        if ($stmt) {
            $stmt->bind_param('ssssiiddiids', $type, $label, $start, $end, $bq, $eq, $bv, $ev, $net_qty, $net_value, $remarks, $by);
            $stmt->execute();
            $stmt->close();
            
            bpis_add_notification($conn, $by, 'Inventory Period Saved', 
                "Inventory period '$label' ($type) has been recorded with beginning value ₱" . number_format($bv, 2) . " and ending value ₱" . number_format($ev, 2), 
                'success', 'inventory_period.php');
            
            header('Location: inventory_period.php?saved=1');
            exit;
        }
    }
}

if (isset($_POST['delete'])) {
    bpis_csrf_check_post();
    $delete_id = (int)$_POST['delete'];
    $stmt = $conn->prepare('DELETE FROM inventory_periods WHERE id = ?');
    if ($stmt) {
        $stmt->bind_param('i', $delete_id);
        $stmt->execute();
        $stmt->close();
    }

    $admin_id = (int) ($_SESSION['admin_id'] ?? 0);
    bpis_add_notification($conn, $admin_id, 'Inventory Period Deleted', 
        "An inventory period record has been deleted from the system.", 
        'warning', 'inventory_period.php');
    
    header('Location: inventory_period.php?deleted=1');
    exit;
}

$periods = [];
if (bpis_table_exists($conn, 'inventory_periods')) {
    $rs = mysqli_query($conn, 'SELECT * FROM inventory_periods ORDER BY period_end DESC LIMIT 50');
    if ($rs) {
        while ($row = mysqli_fetch_assoc($rs)) {
            $periods[] = $row;
        }
    }
}

$bpis_page_title = 'Beginning / Ending Inventory';
$bpis_page_heading = 'Beginning & Ending Inventory';
$bpis_page_subtitle = 'COA inventory period summary (Monthly, Quarterly, or Annual).';
include __DIR__ . '/../includes/treasurer_layout_start.php';
?>

<style>
    .net-positive { color: #059669; }
    .net-negative { color: #dc2626; }
    .net-neutral { color: #6b7280; }
    .stats-card {
        background: white;
        border-radius: 12px;
        border: 1px solid #e5e7eb;
        padding: 16px;
    }
    .stats-number {
        font-size: 20px;
        font-weight: 700;
    }
    .success-msg, .info-msg, .error-msg {
        padding: 12px 16px;
        border-radius: 8px;
        margin-bottom: 16px;
    }
    .success-msg { background: #dcfce7; border: 1px solid #bbf7d0; color: #166534; }
    .info-msg { background: #dbeafe; border: 1px solid #bfdbfe; color: #1e40af; }
    .error-msg { background: #fee2e2; border: 1px solid #fecaca; color: #991b1b; }
    
    .periods-table-container {
        overflow-x: auto;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
    }
    .periods-table {
        width: 100%;
        border-collapse: collapse;
    }
    .periods-table thead th {
        background: #f8fafc;
        padding: 10px 12px;
        font-size: 13px;
        font-weight: 600;
        text-transform: uppercase;
        color: #475569;
        border-bottom: 1px solid #e2e8f0;
        border-right: 1px solid #e2e8f0;
    }
    .periods-table thead th:last-child {
        border-right: none;
    }
    .periods-table tbody td {
        padding: 8px 12px;
        font-size: 12px;
        border-bottom: 1px solid #f1f5f9;
        border-right: 1px solid #f1f5f9;
        vertical-align: middle;
    }
    .periods-table tbody td:last-child {
        border-right: none;
    }
    .periods-table tbody td.date-cell {
        font-size: 12px;
        font-family: monospace;
    }
    .periods-table tbody tr:hover {
        background: #f8fafc;
    }
    .period-badge {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 20px;
        font-size: 10px;
        font-weight: 600;
    }
    .period-badge-monthly { background: #e0e7ff; color: #3730a3; }
    .period-badge-quarterly { background: #dbeafe; color: #1e40af; }
    .period-badge-annual { background: #dcfce7; color: #166534; }
    .delete-link {
        color: #ef4444;
        text-decoration: none;
        font-size: 12px;
    }
    .delete-link:hover {
        color: #dc2626;
    }
    
    .notification-bell {
        position: relative;
        cursor: pointer;
        transition: transform 0.2s;
    }
    .notification-bell:hover {
        transform: scale(1.05);
    }
    .notification-badge {
        position: absolute;
        top: -8px;
        right: -8px;
        background-color: #ef4444;
        color: white;
        border-radius: 50%;
        min-width: 18px;
        height: 18px;
        font-size: 10px;
        font-weight: bold;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 0 4px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.2);
        animation: pulse 1.5s infinite;
    }
    @keyframes pulse {
        0% { transform: scale(1); }
        50% { transform: scale(1.1); }
        100% { transform: scale(1); }
    }
    .notification-dropdown {
        position: absolute;
        top: 45px;
        right: 0;
        width: 350px;
        max-height: 400px;
        overflow-y: auto;
        background: white;
        border-radius: 12px;
        box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1);
        z-index: 1000;
        display: none;
        border: 1px solid #e2e8f0;
    }
    .notification-dropdown.active {
        display: block;
        animation: fadeIn 0.2s ease-out;
    }
    .notification-header {
        padding: 12px 16px;
        border-bottom: 1px solid #e2e8f0;
        font-weight: 600;
        font-size: 14px;
        color: #1e293b;
        background: #f8fafc;
        border-radius: 12px 12px 0 0;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .notification-header button {
        background: none;
        border: none;
        color: #3b82f6;
        cursor: pointer;
        font-size: 12px;
    }
    .notification-item {
        padding: 12px 16px;
        border-bottom: 1px solid #f1f5f9;
        transition: background 0.2s;
        cursor: pointer;
    }
    .notification-item:hover {
        background: #f8fafc;
    }
    .notification-item.unread {
        background: #eff6ff;
    }
    .notification-title {
        font-size: 13px;
        font-weight: 600;
        color: #0f172a;
        margin-bottom: 4px;
    }
    .notification-message {
        font-size: 11px;
        color: #64748b;
        margin-bottom: 4px;
    }
    .notification-time {
        font-size: 10px;
        color: #94a3b8;
    }
    .empty-notifications {
        padding: 30px;
        text-align: center;
        color: #94a3b8;
        font-size: 13px;
    }
    .mark-all-read {
        color: #3b82f6;
        font-size: 12px;
        cursor: pointer;
    }

    @media (max-width: 1024px) {
        .stats-card {
            padding: 14px !important;
            border-radius: 10px !important;
        }
        .stats-number {
            font-size: 18px !important;
        }
        .periods-table-container {
            border-radius: 6px !important;
        }
        .periods-table thead th {
            font-size: 12px !important;
            padding: 8px 10px !important;
        }
        .periods-table tbody td {
            font-size: 11px !important;
            padding: 6px 10px !important;
        }
        .periods-table tbody td.date-cell {
            font-size: 11px !important;
        }
        .period-badge {
            font-size: 9px !important;
            padding: 2px 6px !important;
        }
        .delete-link {
            font-size: 11px !important;
        }
        .notification-dropdown {
            width: 320px !important;
            max-height: 360px !important;
        }
        .success-msg, .info-msg, .error-msg {
            font-size: 13px !important;
            padding: 10px 14px !important;
        }
    }

    @media (max-width: 768px) {
        .stats-card {
            padding: 12px !important;
            border-radius: 8px !important;
        }
        .stats-number {
            font-size: 16px !important;
        }
        .periods-table-container {
            border-radius: 6px !important;
            border-width: 1px !important;
        }
        .periods-table thead th {
            font-size: 11px !important;
            padding: 6px 8px !important;
        }
        .periods-table tbody td {
            font-size: 10px !important;
            padding: 5px 8px !important;
        }
        .periods-table tbody td.date-cell {
            font-size: 10px !important;
        }
        .period-badge {
            font-size: 8px !important;
            padding: 1px 5px !important;
            border-radius: 14px !important;
        }
        .delete-link {
            font-size: 10px !important;
        }
        .success-msg, .info-msg, .error-msg {
            font-size: 12px !important;
            padding: 8px 12px !important;
            border-radius: 6px !important;
            margin-bottom: 12px !important;
        }
        .notification-dropdown {
            width: 280px !important;
            max-height: 320px !important;
            right: -20px !important;
            top: 40px !important;
        }
        .notification-header {
            font-size: 13px !important;
            padding: 10px 14px !important;
        }
        .notification-header button {
            font-size: 11px !important;
        }
        .notification-item {
            padding: 10px 14px !important;
        }
        .notification-title {
            font-size: 12px !important;
        }
        .notification-message {
            font-size: 10px !important;
        }
        .notification-time {
            font-size: 9px !important;
        }
        .empty-notifications {
            padding: 20px !important;
            font-size: 12px !important;
        }
        .mark-all-read {
            font-size: 11px !important;
        }
        .notification-badge {
            min-width: 16px !important;
            height: 16px !important;
            font-size: 9px !important;
            top: -6px !important;
            right: -6px !important;
        }
    }

    @media (max-width: 480px) {
        .stats-card {
            padding: 10px !important;
            border-radius: 6px !important;
        }
        .stats-number {
            font-size: 14px !important;
        }
        .periods-table-container {
            border-radius: 4px !important;
        }
        .periods-table thead th {
            font-size: 10px !important;
            padding: 4px 6px !important;
        }
        .periods-table tbody td {
            font-size: 9px !important;
            padding: 4px 6px !important;
        }
        .periods-table tbody td.date-cell {
            font-size: 9px !important;
        }
        .period-badge {
            font-size: 7px !important;
            padding: 1px 4px !important;
            border-radius: 10px !important;
        }
        .delete-link {
            font-size: 9px !important;
        }
        .success-msg, .info-msg, .error-msg {
            font-size: 11px !important;
            padding: 6px 10px !important;
            border-radius: 4px !important;
            margin-bottom: 10px !important;
        }
        .notification-dropdown {
            width: 240px !important;
            max-height: 280px !important;
            right: -10px !important;
            top: 38px !important;
            border-radius: 10px !important;
        }
        .notification-header {
            font-size: 12px !important;
            padding: 8px 12px !important;
        }
        .notification-header button {
            font-size: 10px !important;
        }
        .notification-item {
            padding: 8px 12px !important;
        }
        .notification-title {
            font-size: 11px !important;
        }
        .notification-message {
            font-size: 9px !important;
        }
        .notification-time {
            font-size: 8px !important;
        }
        .empty-notifications {
            padding: 16px !important;
            font-size: 11px !important;
        }
        .mark-all-read {
            font-size: 10px !important;
        }
        .notification-badge {
            min-width: 14px !important;
            height: 14px !important;
            font-size: 8px !important;
            top: -5px !important;
            right: -5px !important;
        }
    }

    @media (max-width: 360px) {
        .stats-card {
            padding: 8px !important;
            border-radius: 4px !important;
        }
        .stats-number {
            font-size: 12px !important;
        }
        .periods-table thead th {
            font-size: 9px !important;
            padding: 3px 4px !important;
        }
        .periods-table tbody td {
            font-size: 8px !important;
            padding: 3px 4px !important;
        }
        .periods-table tbody td.date-cell {
            font-size: 8px !important;
        }
        .period-badge {
            font-size: 6px !important;
            padding: 1px 3px !important;
            border-radius: 8px !important;
        }
        .delete-link {
            font-size: 8px !important;
        }
        .success-msg, .info-msg, .error-msg {
            font-size: 10px !important;
            padding: 4px 8px !important;
        }
        .notification-dropdown {
            width: 200px !important;
            max-height: 240px !important;
            right: -5px !important;
            top: 35px !important;
            border-radius: 8px !important;
        }
        .notification-header {
            font-size: 11px !important;
            padding: 6px 10px !important;
        }
        .notification-item {
            padding: 6px 10px !important;
        }
        .notification-title {
            font-size: 10px !important;
            margin-bottom: 2px !important;
        }
        .notification-message {
            font-size: 8px !important;
        }
        .notification-time {
            font-size: 7px !important;
        }
        .empty-notifications {
            padding: 12px !important;
            font-size: 10px !important;
        }
        .mark-all-read {
            font-size: 9px !important;
        }
        .notification-badge {
            min-width: 12px !important;
            height: 12px !important;
            font-size: 7px !important;
            top: -4px !important;
            right: -4px !important;
        }
    }
</style>

<div class="space-y-6">
    <?php if (!empty($_GET['saved'])): ?>
        <div class="success-msg">
            <i class="fa-solid fa-check-circle mr-2"></i> Period saved successfully!
        </div>
    <?php endif; ?>
    <?php if (!empty($_GET['deleted'])): ?>
        <div class="info-msg">
            <i class="fa-solid fa-trash mr-2"></i> Period deleted successfully.
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="stats-card">
            <p class="text-xs text-gray-500">Total Periods</p>
            <p class="stats-number text-gray-800"><?= count($periods) ?></p>
        </div>
        <div class="stats-card">
            <p class="text-xs text-gray-500">Total Beginning Value</p>
            <p class="stats-number text-blue-600">₱<?= number_format(array_sum(array_column($periods, 'beginning_value')), 2) ?></p>
        </div>
        <div class="stats-card">
            <p class="text-xs text-gray-500">Total Ending Value</p>
            <p class="stats-number text-green-600">₱<?= number_format(array_sum(array_column($periods, 'ending_value')), 2) ?></p>
        </div>
        <div class="stats-card">
            <p class="text-xs text-gray-500">Net Change</p>
            <?php 
            $total_net = array_sum(array_column($periods, 'net_change_value'));
            $net_class = $total_net > 0 ? 'net-positive' : ($total_net < 0 ? 'net-negative' : 'net-neutral');
            ?>
            <p class="stats-number <?= $net_class ?>">₱<?= number_format($total_net, 2) ?></p>
        </div>
    </div>

    <form method="post" class="mb-8 bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="border-b border-gray-100 px-6 py-4 bg-gray-50/50">
            <h3 class="text-[13px] font-bold text-gray-800 uppercase tracking-wider flex items-center gap-2">
                <i data-lucide="calendar-days" class="w-4 h-4 text-blue-500"></i> Add New Period
            </h3>
            <p class="text-xs text-gray-500 mt-1">Record beginning and ending inventory for Monthly, Quarterly, or Annual periods</p>
        </div>
        
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label class="block text-[13px] font-semibold text-gray-700 mb-1.5">Period type <span class="text-red-500">*</span></label>
                    <select name="period_type" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                        <option value="Monthly">Monthly</option>
                        <option value="Quarterly">Quarterly</option>
                        <option value="Annual">Annual</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[13px] font-semibold text-gray-700 mb-1.5">Period label <span class="text-red-500">*</span></label>
                    <input name="period_label" placeholder="e.g., Q1 2025, January 2025, FY 2025" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-[13px] font-semibold text-gray-700 mb-1.5">Period start <span class="text-red-500">*</span></label>
                    <input type="date" name="period_start" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-[13px] font-semibold text-gray-700 mb-1.5">Period end <span class="text-red-500">*</span></label>
                    <input type="date" name="period_end" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-[13px] font-semibold text-gray-700 mb-1.5">Beginning quantity <span class="text-red-500">*</span></label>
                    <input type="number" name="beginning_qty" id="beginningQty" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-[13px] font-semibold text-gray-700 mb-1.5">Ending quantity <span class="text-red-500">*</span></label>
                    <input type="number" name="ending_qty" id="endingQty" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                    <p id="qtyChangeHint" class="text-[10px] mt-1"></p>
                </div>
                <div>
                    <label class="block text-[13px] font-semibold text-gray-700 mb-1.5">Beginning value (₱) <span class="text-red-500">*</span></label>
                    <div class="relative">
                        <span class="absolute left-3 top-2 text-gray-500">₱</span>
                        <input type="number" step="0.01" name="beginning_value" id="beginningValue" required class="w-full pl-8 pr-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>
                <div>
                    <label class="block text-[13px] font-semibold text-gray-700 mb-1.5">Ending value (₱) <span class="text-red-500">*</span></label>
                    <div class="relative">
                        <span class="absolute left-3 top-2 text-gray-500">₱</span>
                        <input type="number" step="0.01" name="ending_value" id="endingValue" required class="w-full pl-8 pr-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                    </div>
                    <p id="valueChangeHint" class="text-[10px] mt-1"></p>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-[13px] font-semibold text-gray-700 mb-1.5">Remarks / Notes</label>
                    <textarea name="remarks" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500" placeholder="Additional notes about inventory changes during this period..."></textarea>
                </div>
                <div class="md:col-span-2 pt-2">
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium text-sm py-2.5 px-6 rounded-lg shadow-md transition-colors flex items-center gap-2">
                        <i class="fa-solid fa-save"></i> Save Period
                    </button>
                </div>
            </div>
        </div>
    </form>

    <div>
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-[13px] font-bold text-gray-800 uppercase tracking-wider flex items-center gap-2">
                <i data-lucide="table" class="w-4 h-4 text-blue-500"></i> INVENTORY PERIODS 
                <span class="text-gray-400 text-xs font-normal ml-2">(<?= count($periods) ?> RECORDS)</span>
            </h3>
        </div>
        
        <?php if (empty($periods)): ?>
            <div class="bg-white rounded-xl border border-gray-200 p-12 text-center">
                <i data-lucide="inbox" class="w-12 h-12 text-gray-300 mx-auto mb-3"></i>
                <p class="text-gray-500">No inventory periods recorded yet. Add your first period above.</p>
            </div>
        <?php else: ?>
            <div class="periods-table-container">
                <table class="periods-table">
                    <thead>
                        <tr>
                            <th>LABEL</th>
                            <th>TYPE</th>
                            <th>START DATE</th>
                            <th>END DATE</th>
                            <th>BEGINNING QTY</th>
                            <th>ENDING QTY</th>
                            <th>NET CHANGE</th>
                            <th>BEGINNING VALUE</th>
                            <th>ENDING VALUE</th>
                            <th>NET CHANGE</th>
                            <th>ACTIONS</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($periods as $p): 
                            $period_type = isset($p['period_type']) && $p['period_type'] !== null ? $p['period_type'] : (isset($p['type']) ? $p['type'] : 'Annual');
                            $period_label = isset($p['period_label']) && $p['period_label'] !== null ? $p['period_label'] : (isset($p['label']) ? $p['label'] : '');
                            $net_qty = (isset($p['ending_qty']) ? $p['ending_qty'] : 0) - (isset($p['beginning_qty']) ? $p['beginning_qty'] : 0);
                            $net_value = (isset($p['ending_value']) ? $p['ending_value'] : 0) - (isset($p['beginning_value']) ? $p['beginning_value'] : 0);
                            $qty_class = $net_qty > 0 ? 'net-positive' : ($net_qty < 0 ? 'net-negative' : 'net-neutral');
                            $val_class = $net_value > 0 ? 'net-positive' : ($net_value < 0 ? 'net-negative' : 'net-neutral');
                            
                            $badge_class = 'period-badge';
                            if ($period_type == 'Monthly') $badge_class .= ' period-badge-monthly';
                            elseif ($period_type == 'Quarterly') $badge_class .= ' period-badge-quarterly';
                            else $badge_class .= ' period-badge-annual';
                        ?>
                            <tr>
                                <td class="font-medium"><?= htmlspecialchars($period_label) ?></td>
                                <td>
                                    <span class="<?= $badge_class ?>"><?= htmlspecialchars($period_type) ?></span>
                                </td>
                                <td class="date-cell"><?= date('M d, Y', strtotime($p['period_start'])) ?></td>
                                <td class="date-cell"><?= date('M d, Y', strtotime($p['period_end'])) ?></td>
                                <td class="text-right"><?= number_format($p['beginning_qty'] ?? 0) ?></td>
                                <td class="text-right"><?= number_format($p['ending_qty'] ?? 0) ?></td>
                                <td class="text-right <?= $qty_class ?>"><?= ($net_qty > 0 ? '+' : '') . number_format($net_qty) ?></td>
                                <td class="text-right">₱<?= number_format($p['beginning_value'] ?? 0, 2) ?></td>
                                <td class="text-right">₱<?= number_format($p['ending_value'] ?? 0, 2) ?></td>
                                <td class="text-right <?= $val_class ?>"><?= ($net_value > 0 ? '+' : '') ?>₱<?= number_format(abs($net_value), 2) ?></td>
                                <td class="text-center">
                                    <a href="#" class="delete-link" data-delete-id="<?= $p['id'] ?>" data-delete-label="<?= htmlspecialchars($period_label) ?>">
                                        <i class="fa-solid fa-trash"></i> Delete
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
const beginningQty = document.getElementById('beginningQty');
const endingQty = document.getElementById('endingQty');
const beginningValue = document.getElementById('beginningValue');
const endingValue = document.getElementById('endingValue');
const qtyHint = document.getElementById('qtyChangeHint');
const valueHint = document.getElementById('valueChangeHint');

function updateHints() {
    if (beginningQty && endingQty) {
        const bq = parseInt(beginningQty.value) || 0;
        const eq = parseInt(endingQty.value) || 0;
        const diff = eq - bq;
        if (qtyHint) {
            if (diff > 0) {
                qtyHint.innerHTML = '<span class="text-green-600">📈 +' + diff + ' units added</span>';
            } else if (diff < 0) {
                qtyHint.innerHTML = '<span class="text-red-600">📉 ' + diff + ' units removed</span>';
            } else {
                qtyHint.innerHTML = '<span class="text-gray-500">➖ No change in quantity</span>';
            }
        }
    }
    
    if (beginningValue && endingValue) {
        const bv = parseFloat(beginningValue.value) || 0;
        const ev = parseFloat(endingValue.value) || 0;
        const diff = ev - bv;
        if (valueHint) {
            if (diff > 0) {
                valueHint.innerHTML = '<span class="text-green-600">📈 +₱' + diff.toFixed(2) + ' value increase</span>';
            } else if (diff < 0) {
                valueHint.innerHTML = '<span class="text-red-600">📉 -₱' + Math.abs(diff).toFixed(2) + ' value decrease</span>';
            } else {
                valueHint.innerHTML = '<span class="text-gray-500">➖ No value change</span>';
            }
        }
    }
}

if (beginningQty) beginningQty.addEventListener('input', updateHints);
if (endingQty) endingQty.addEventListener('input', updateHints);
if (beginningValue) beginningValue.addEventListener('input', updateHints);
if (endingValue) endingValue.addEventListener('input', updateHints);

const notificationBell = document.getElementById('notificationBell');
const notificationDropdown = document.getElementById('notificationDropdown');
const notificationBadge = document.getElementById('notificationBadge');
const notificationList = document.getElementById('notificationList');
const markAllReadBtn = document.getElementById('markAllReadBtn');

function formatTime(timestamp) {
    const date = new Date(timestamp);
    const now = new Date();
    const diffMins = Math.floor((now - date) / 60000);
    if (diffMins < 1) return 'Just now';
    if (diffMins < 60) return `${diffMins} min ago`;
    if (diffMins < 1440) return `${Math.floor(diffMins / 60)} hours ago`;
    return date.toLocaleDateString();
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function loadNotifications() {
    fetch('get_notifications.php', { credentials: 'same-origin' })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (data.unread_count > 0) {
                    notificationBadge.style.display = 'flex';
                    notificationBadge.textContent = data.unread_count > 99 ? '99+' : data.unread_count;
                } else {
                    notificationBadge.style.display = 'none';
                }
                
                if (data.notifications && data.notifications.length > 0) {
                    let html = '';
                    data.notifications.forEach(notif => {
                        const unreadClass = notif.is_read ? '' : 'unread';
                        html += `
                            <div class="notification-item ${unreadClass}" onclick="markAsRead(${notif.id}, '${notif.link}')">
                                <div class="notification-title">${escapeHtml(notif.title)}</div>
                                <div class="notification-message">${escapeHtml(notif.message)}</div>
                                <div class="notification-time">${formatTime(notif.created_at)}</div>
                            </div>
                        `;
                    });
                    notificationList.innerHTML = html;
                } else {
                    notificationList.innerHTML = '<div class="empty-notifications">No notifications</div>';
                }
            }
        })
        .catch(error => console.error('Error loading notifications:', error));
}

function markAsRead(id, link) {
    fetch('mark_notification_read.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'notification_id=' + id,
        credentials: 'same-origin'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadNotifications();
            if (link) window.location.href = link;
        }
    })
    .catch(error => console.error('Error marking as read:', error));
}

if (notificationBell) {
    notificationBell.addEventListener('click', function(e) {
        e.stopPropagation();
        notificationDropdown.classList.toggle('active');
        if (notificationDropdown.classList.contains('active')) {
            loadNotifications();
        }
    });
}

if (markAllReadBtn) {
    markAllReadBtn.addEventListener('click', function() {
        fetch('mark_all_notifications_read.php', { 
            method: 'POST',
            credentials: 'same-origin'
        })
        .then(response => response.json())
        .then(data => { 
            if (data.success) loadNotifications(); 
        })
        .catch(error => console.error('Error marking all as read:', error));
    });
}

document.addEventListener('click', function(e) {
    if (notificationDropdown && notificationBell && 
        !notificationDropdown.contains(e.target) && 
        !notificationBell.contains(e.target)) {
        notificationDropdown.classList.remove('active');
    }
});

loadNotifications();
setInterval(loadNotifications, 30000);

    lucide.createIcons();

    (function () {
        var deleteModal = document.createElement('div');
        deleteModal.id = 'bp_delete_modal';
        deleteModal.style.display = 'none';
        deleteModal.style.position = 'fixed';
        deleteModal.style.inset = '0';
        deleteModal.style.alignItems = 'center';
        deleteModal.style.justifyContent = 'center';
        deleteModal.style.zIndex = '2200';
        deleteModal.innerHTML = '\n            <div style="position:fixed;inset:0;background:rgba(0,0,0,0.4);"></div>\n            <div role="dialog" aria-modal="true" style="background:#fff;border-radius:10px;padding:18px;max-width:420px;width:90%;box-shadow:0 10px 30px rgba(2,6,23,0.4);position:relative;z-index:1;">\n                <h3 style="margin:0 0 8px;font-size:16px;font-weight:700;color:#111827;">Delete this period record?</h3>\n                <p id="bp_delete_modal_msg" style="margin:0 0 14px;color:#374151;font-size:13px;">Are you sure you want to delete this inventory period? This action cannot be undone.</p>\n                <div style="display:flex;justify-content:flex-end;gap:10px;">\n                    <button id="bp_delete_cancel" style="background:#fff;border:1px solid #d1d5db;padding:8px 12px;border-radius:8px;cursor:pointer">Cancel</button>\n                    <button id="bp_delete_confirm" style="background:#ef4444;color:#fff;border:none;padding:8px 12px;border-radius:8px;cursor:pointer">Delete</button>\n                </div>\n            </div>';
        document.body.appendChild(deleteModal);

        var currentDeleteId = 0;

        function openDeleteModal(id, label) {
            currentDeleteId = id || 0;
            var msg = document.getElementById('bp_delete_modal_msg');
            if (msg) {
                msg.textContent = label ? ('Delete period "' + label + '"?') : 'Delete this inventory period?';
            }
            deleteModal.style.display = 'flex';
        }

        function closeDeleteModal() {
            deleteModal.style.display = 'none';
            currentDeleteId = 0;
        }

        document.addEventListener('click', function (e) {
            var el = e.target.closest && e.target.closest('.delete-link');
            if (!el) return;
            e.preventDefault();
            var id = el.getAttribute('data-delete-id');
            var label = el.getAttribute('data-delete-label') || '';
            openDeleteModal(id, label);
        });

        document.getElementById('bp_delete_cancel').addEventListener('click', function () { closeDeleteModal(); });
        document.getElementById('bp_delete_confirm').addEventListener('click', function () {
            if (currentDeleteId) {
                var form = document.createElement('form');
                form.method = 'post';
                form.action = 'inventory_period.php';
                form.innerHTML = '<input type="hidden" name="delete">'
                    + <?= json_encode(bpis_csrf_field(), JSON_UNESCAPED_SLASHES) ?>;
                form.elements.delete.value = currentDeleteId;
                document.body.appendChild(form);
                form.submit();
            } else {
                closeDeleteModal();
            }
        });

        deleteModal.addEventListener('click', function (e) {
            if (e.target === deleteModal) closeDeleteModal();
        });
    })();
</script>

<?php include __DIR__ . '/../includes/treasurer_layout_end.php'; ?>