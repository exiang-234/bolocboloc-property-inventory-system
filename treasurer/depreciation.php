<?php
session_start();
include '../config/database.php';
require_once __DIR__ . '/../config/auth_helpers.php';
require_once __DIR__ . '/../config/coa_labels.php';
require_once __DIR__ . '/../config/treasurer_page_helpers.php';
require_once __DIR__ . '/../config/asset_list_helpers.php';
bpis_require_login(['Treasurer', 'Barangay Captain']);

$bpis_treasurer_nav_active = 'depreciation';
$label_unit = bpis_label_unit_cost_value();
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['asset_id'], $_POST['period_label'])) {
    $asset_id = (int) $_POST['asset_id'];
    $period_label = trim((string) $_POST['period_label']);
    $dep = (float) ($_POST['depreciation_amount'] ?? 0);
    $acc = (float) ($_POST['accumulated_depreciation'] ?? 0);
    $nbv = (float) ($_POST['net_book_value'] ?? 0);
    $admin_id = (int) ($_SESSION['admin_id'] ?? 0);
    $has_error = false;
    $error_message = '';
    
    if ($dep < 0) {
        $has_error = true;
        $error_message = 'Depreciation amount cannot be negative.';
    }
    if ($acc < 0) {
        $has_error = true;
        $error_message = 'Accumulated depreciation cannot be negative.';
    }
    if ($nbv < 0) {
        $has_error = true;
        $error_message = 'Net book value cannot be negative.';
    }
    
    if (!$has_error && $asset_id > 0 && $period_label !== '' && bpis_table_exists($conn, 'asset_depreciation_log')) {
        $stmt = $conn->prepare(
            'INSERT INTO asset_depreciation_log (asset_id, period_label, depreciation_amount, accumulated_depreciation, net_book_value, recorded_by) VALUES (?,?,?,?,?,?)'
        );
        if ($stmt) {
            $stmt->bind_param('isdddi', $asset_id, $period_label, $dep, $acc, $nbv, $admin_id);
            $stmt->execute();
            $stmt->close();
            
            mysqli_query($conn, "UPDATE asset SET accumulated_depreciation = {$acc}, net_book_value = {$nbv} WHERE id = {$asset_id}");
            
            $asset_name_query = mysqli_query($conn, "SELECT description FROM asset WHERE id = $asset_id");
            $asset_name = $asset_name_query ? mysqli_fetch_assoc($asset_name_query)['description'] : 'Asset';
            bpis_add_notification($conn, $admin_id, 'Depreciation Recorded', 
                "Depreciation of ₱" . number_format($dep, 2) . " recorded for $asset_name for period $period_label", 
                'success', 'depreciation.php');
            
            header('Location: depreciation.php?saved=1');
            exit;
        }
    } elseif ($has_error) {
        $_SESSION['depreciation_error'] = $error_message;
        header('Location: depreciation.php?error=1');
        exit;
    }
}

$assets = bpis_list_assets_for_select($conn);
$highlight_asset_id = (int) ($_GET['asset_id'] ?? bpis_peek_last_registered_asset());
$highlight_asset = null;
foreach ($assets as $a) {
    if ((int) ($a['id'] ?? 0) === $highlight_asset_id) {
        $highlight_asset = $a;
        break;
    }
}

$logs = [];
if (bpis_table_exists($conn, 'asset_depreciation_log')) {
    $lr = mysqli_query($conn, 'SELECT l.*, a.description, a.property_number, a.unit_value, a.quantity 
                                FROM asset_depreciation_log l 
                                LEFT JOIN asset a ON a.id = l.asset_id 
                                ORDER BY l.created_at DESC LIMIT 50');
    if ($lr) {
        while ($row = mysqli_fetch_assoc($lr)) {
            $logs[] = $row;
        }
    }
}

$error_msg = $_SESSION['depreciation_error'] ?? '';
unset($_SESSION['depreciation_error']);

$bpis_page_title = 'Inventory Depreciation';
$bpis_page_heading = 'Inventory Depreciation';
$bpis_page_subtitle = 'COA-aligned tracking of accumulated depreciation and net book value.';
include __DIR__ . '/../includes/treasurer_layout_start.php';
?>

<style>
    .current-values-card {
        background: #f0f9ff;
        border: 1px solid #bae6fd;
        border-radius: 12px;
        padding: 16px;
        margin-bottom: 20px;
    }
    .current-values-card h4 {
        font-size: 12px;
        font-weight: 600;
        color: #0369a1;
        margin-bottom: 12px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .value-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 16px;
    }
    .value-item {
        text-align: center;
    }
    .value-label {
        font-size: 11px;
        color: #475569;
        margin-bottom: 4px;
    }
    .value-amount {
        font-size: 16px;
        font-weight: 700;
        color: #0f172a;
    }
    .depreciation-note {
        font-size: 11px;
        color: #64748b;
        margin-top: 8px;
        padding-top: 8px;
        border-top: 1px solid #e2e8f0;
    }
    .success-msg, .error-msg {
        padding: 12px 16px;
        border-radius: 8px;
        margin-bottom: 16px;
    }
    .success-msg { background: #dcfce7; border: 1px solid #bbf7d0; color: #166534; }
    .error-msg { background: #fee2e2; border: 1px solid #fecaca; color: #991b1b; }
    .field-error {
        border-color: #ef4444 !important;
        background-color: #fef2f2 !important;
    }
    .error-hint {
        color: #ef4444;
        font-size: 10px;
        margin-top: 4px;
        display: none;
    }
    .error-hint.show {
        display: block;
    }

    .depreciation-actions {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 12px;
        padding-top: 10px;
    }

    .depreciation-action-btn {
        min-height: 42px;
        justify-content: center;
        white-space: nowrap;
    }
    
    .toast-container {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 3000;
        display: none;
        animation: slideIn 0.3s ease-out;
    }
    .toast {
        background-color: white;
        border-radius: 12px;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        width: 320px;
        overflow: hidden;
        border: 1px solid #e2e8f0;
    }
    .toast-header {
        background-color: #166534;
        color: white;
        padding: 10px 15px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-weight: 600;
        font-size: 14px;
    }
    .toast-body {
        padding: 15px;
        color: #475569;
        font-size: 13px;
    }
    .toast-close {
        background: none;
        border: none;
        color: white;
        cursor: pointer;
        font-size: 18px;
    }
    @keyframes slideIn {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    @keyframes fadeOut {
        from { opacity: 1; }
        to { opacity: 0; }
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
        .current-values-card {
            padding: 14px !important;
            border-radius: 10px !important;
        }
        .value-grid {
            gap: 12px !important;
        }
        .value-amount {
            font-size: 15px !important;
        }
        .value-label {
            font-size: 10px !important;
        }
        .notification-dropdown {
            width: 320px !important;
            max-height: 360px !important;
        }
        .toast {
            width: 280px !important;
        }
        .toast-header {
            font-size: 13px !important;
            padding: 8px 12px !important;
        }
        .toast-body {
            font-size: 12px !important;
            padding: 12px !important;
        }
    }

    @media (max-width: 768px) {
        .current-values-card {
            padding: 12px !important;
            border-radius: 8px !important;
            margin-bottom: 16px !important;
        }
        .current-values-card h4 {
            font-size: 11px !important;
            margin-bottom: 10px !important;
        }
        .value-grid {
            grid-template-columns: repeat(3, 1fr) !important;
            gap: 8px !important;
        }
        .value-amount {
            font-size: 14px !important;
        }
        .value-label {
            font-size: 9px !important;
        }
        .depreciation-note {
            font-size: 10px !important;
            margin-top: 6px !important;
            padding-top: 6px !important;
        }
        .success-msg, .error-msg {
            font-size: 12px !important;
            padding: 10px 14px !important;
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
        .toast-container {
            top: 12px !important;
            right: 12px !important;
        }
        .toast {
            width: 260px !important;
            border-radius: 10px !important;
        }
        .toast-header {
            font-size: 12px !important;
            padding: 8px 12px !important;
        }
        .toast-body {
            font-size: 11px !important;
            padding: 10px 12px !important;
        }
        .toast-close {
            font-size: 16px !important;
        }
        .notification-badge {
            min-width: 16px !important;
            height: 16px !important;
            font-size: 9px !important;
            top: -6px !important;
            right: -6px !important;
        }
        .field-error {
            border-width: 1.5px !important;
        }
        .error-hint {
            font-size: 9px !important;
        }
        .depreciation-actions {
            justify-content: stretch !important;
            gap: 10px !important;
        }
        .depreciation-action-btn {
            flex: 1 1 0 !important;
            padding-left: 12px !important;
            padding-right: 12px !important;
        }
    }

    @media (max-width: 480px) {
        .current-values-card {
            padding: 10px !important;
            border-radius: 6px !important;
            margin-bottom: 12px !important;
        }
        .current-values-card h4 {
            font-size: 10px !important;
            gap: 6px !important;
            margin-bottom: 8px !important;
        }
        .value-grid {
            grid-template-columns: repeat(2, 1fr) !important;
            gap: 6px !important;
        }
        .value-item:last-child {
            grid-column: span 2 !important;
        }
        .value-amount {
            font-size: 13px !important;
        }
        .value-label {
            font-size: 8px !important;
        }
        .depreciation-note {
            font-size: 9px !important;
            margin-top: 4px !important;
            padding-top: 4px !important;
        }
        .success-msg, .error-msg {
            font-size: 11px !important;
            padding: 8px 12px !important;
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
        .toast-container {
            top: 10px !important;
            right: 10px !important;
        }
        .toast {
            width: 220px !important;
            border-radius: 8px !important;
        }
        .toast-header {
            font-size: 11px !important;
            padding: 6px 10px !important;
        }
        .toast-body {
            font-size: 10px !important;
            padding: 8px 10px !important;
        }
        .toast-close {
            font-size: 14px !important;
        }
        .notification-badge {
            min-width: 14px !important;
            height: 14px !important;
            font-size: 8px !important;
            top: -5px !important;
            right: -5px !important;
        }
        .field-error {
            border-width: 1px !important;
        }
        .error-hint {
            font-size: 8px !important;
            margin-top: 2px !important;
        }
        .depreciation-actions {
            flex-direction: column !important;
            align-items: stretch !important;
            gap: 8px !important;
            padding-top: 6px !important;
        }
        .depreciation-action-btn {
            width: 100% !important;
            min-height: 44px !important;
        }
    }

    @media (max-width: 360px) {
        .current-values-card {
            padding: 8px !important;
            border-radius: 4px !important;
        }
        .current-values-card h4 {
            font-size: 9px !important;
            gap: 4px !important;
            margin-bottom: 6px !important;
        }
        .value-grid {
            gap: 4px !important;
        }
        .value-amount {
            font-size: 11px !important;
        }
        .value-label {
            font-size: 7px !important;
        }
        .depreciation-note {
            font-size: 8px !important;
        }
        .success-msg, .error-msg {
            font-size: 10px !important;
            padding: 6px 10px !important;
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
        .toast {
            width: 180px !important;
            border-radius: 6px !important;
        }
        .toast-header {
            font-size: 10px !important;
            padding: 4px 8px !important;
        }
        .toast-body {
            font-size: 9px !important;
            padding: 6px 8px !important;
        }
        .toast-close {
            font-size: 12px !important;
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
    <div id="toastContainer" class="toast-container">
        <div class="toast">
            <div class="toast-header">
                <span><i class="fa-solid fa-circle-check"></i> Notification</span>
                <button class="toast-close" onclick="hideToast()">&times;</button>
            </div>
            <div class="toast-body" id="toastMessage"></div>
        </div>
    </div>

    <?php if (!empty($_GET['saved'])): ?>
        <div class="success-msg">
            <i class="fa-solid fa-check-circle mr-2"></i> Depreciation entry saved successfully! Asset values updated.
        </div>
    <?php endif; ?>
    
    <?php if (!empty($_GET['error']) && $error_msg): ?>
        <div class="error-msg">
            <i class="fa-solid fa-exclamation-triangle mr-2"></i> <?= htmlspecialchars($error_msg) ?>
        </div>
    <?php endif; ?>
    
    <?php if ($highlight_asset): ?>
        <div class="mb-6 rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-900">
            <strong>Newly registered asset</strong> — <?= htmlspecialchars($highlight_asset['display_label']) ?> is selected below. Record depreciation when ready.
        </div>
    <?php endif; ?>

    <form method="post" class="mb-8" id="depreciationForm">
        <h3 class="text-[13px] font-bold text-gray-800 mb-5 uppercase tracking-wider border-b border-gray-100 pb-2 flex items-center gap-2">
            <i data-lucide="chart-line" class="w-4 h-4 text-blue-500"></i> Record depreciation
        </h3>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <div class="md:col-span-2">
                <?php
                $bpis_assets = $assets;
                $bpis_asset_picker_id = 'depreciationAssetPicker';
                $bpis_selected_asset_id = $highlight_asset_id;
                $bpis_asset_input_name = 'asset_id';
                include __DIR__ . '/../includes/treasurer_asset_picker.php';
                ?>
            </div>

            <div class="md:col-span-2 current-values-card" id="currentValuesCard" style="display: none;">
                <h4>
                    <i class="fa-solid fa-chart-simple"></i>
                    Current Asset Values (Before this entry)
                </h4>
                <div class="value-grid">
                    <div class="value-item">
                        <div class="value-label">Total Cost</div>
                        <div class="value-amount" id="currentCostDisplay">₱0.00</div>
                    </div>
                    <div class="value-item">
                        <div class="value-label">Accumulated Depreciation</div>
                        <div class="value-amount" id="currentAccDisplay">₱0.00</div>
                    </div>
                    <div class="value-item">
                        <div class="value-label">Net Book Value</div>
                        <div class="value-amount" id="currentNBVDisplay">₱0.00</div>
                    </div>
                </div>
                <div class="depreciation-note">
                    <i class="fa-solid fa-info-circle mr-1"></i> 
                    After saving, these values will be updated in the asset master record and will appear in the Audit module.
                </div>
            </div>
            
            <div>
                <label class="block text-[13px] font-semibold text-gray-700 mb-1.5">Period label <span class="text-red-500">*</span></label>
                <input type="text" name="period_label" placeholder="Q1 2026 / FY 2026" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                <p class="text-[10px] text-gray-400 mt-1">Example: Q1 2025, Q2 2025, FY 2025</p>
            </div>
            
            <div>
                <label class="block text-[13px] font-semibold text-gray-700 mb-1.5">Depreciation amount <span class="text-red-500">*</span></label>
                <div class="relative">
                    <span class="absolute left-3 top-2 text-gray-500">₱</span>
                    <input type="number" step="0.01" name="depreciation_amount" id="depreciationAmount" required 
                           class="w-full pl-8 pr-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500" 
                           placeholder="0.00">
                </div>
                <div id="depError" class="error-hint"></div>
            </div>
            
            <div>
                <label class="block text-[13px] font-semibold text-gray-700 mb-1.5">Accumulated depreciation <span class="text-red-500">*</span></label>
                <div class="relative">
                    <span class="absolute left-3 top-2 text-gray-500">₱</span>
                    <input type="number" step="0.01" name="accumulated_depreciation" id="accumulatedDepInput" required 
                           class="w-full pl-8 pr-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500" 
                           placeholder="0.00">
                </div>
                <div id="accError" class="error-hint"></div>
            </div>
            
            <div>
                <label class="block text-[13px] font-semibold text-gray-700 mb-1.5">Net book value <span class="text-red-500">*</span></label>
                <div class="relative">
                    <span class="absolute left-3 top-2 text-gray-500">₱</span>
                    <input type="number" step="0.01" name="net_book_value" id="netBookValueInput" required 
                           class="w-full pl-8 pr-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500" 
                           placeholder="0.00">
                </div>
                <div id="nbvError" class="error-hint"></div>
                <p class="text-[10px] text-gray-400 mt-1">Net Book Value = Total Cost - Accumulated Depreciation</p>
            </div>
            
            <div class="md:col-span-2 depreciation-actions">
                <button type="submit" class="depreciation-action-btn bg-blue-600 hover:bg-blue-700 text-white font-medium text-sm py-2.5 px-6 rounded-lg shadow-md transition-colors flex items-center gap-2">
                    <i class="fa-solid fa-save"></i> Save depreciation entry
                </button>
                <button type="button" id="clearBtn" class="depreciation-action-btn bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium text-sm py-2.5 px-6 rounded-lg transition-colors flex items-center">
                    Clear
                </button>
            </div>
        </div>
    </form>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="p-2 bg-blue-100 rounded-lg">
                    <i data-lucide="building-2" class="w-5 h-5 text-blue-600"></i>
                </div>
                <div>
                    <p class="text-xs text-gray-500">Total Assets with Depreciation</p>
                    <p class="text-xl font-bold text-gray-800"><?= count($logs) ?></p>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="p-2 bg-green-100 rounded-lg">
                    <i data-lucide="trending-down" class="w-5 h-5 text-green-600"></i>
                </div>
                <div>
                    <p class="text-xs text-gray-500">Total Depreciation Recorded</p>
                    <p class="text-xl font-bold text-gray-800">
                        <?php 
                        $total_dep = array_sum(array_column($logs, 'depreciation_amount'));
                        echo '₱' . number_format($total_dep, 2);
                        ?>
                    </p>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="p-2 bg-purple-100 rounded-lg">
                    <i data-lucide="calendar" class="w-5 h-5 text-purple-600"></i>
                </div>
                <div>
                    <p class="text-xs text-gray-500">Last Entry Date</p>
                    <p class="text-sm font-medium text-gray-800">
                        <?php 
                        if (!empty($logs)) {
                            echo date('M d, Y', strtotime($logs[0]['created_at'] ?? 'now'));
                        } else {
                            echo 'No entries yet';
                        }
                        ?>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <h3 class="text-[13px] font-bold text-gray-800 mb-4 uppercase tracking-wider border-b border-gray-100 pb-2 flex items-center gap-2">
        <i data-lucide="list" class="w-4 h-4 text-blue-500"></i> Recent entries
        <span class="text-gray-400 text-xs font-normal ml-2">(Last 50 records)</span>
    </h3>
    
    <div class="overflow-x-auto border border-gray-200 rounded-lg">
        <table class="w-full text-left border-collapse text-[13px]">
            <thead>
                <tr class="bg-gray-50 text-[11px] font-bold text-gray-600 uppercase border-b border-gray-200">
                    <th class="py-3 px-4 border-r">Asset</th>
                    <th class="py-3 px-4 border-r">Property #</th>
                    <th class="py-3 px-4 border-r">Period</th>
                    <th class="py-3 px-4 border-r text-right">Depreciation</th>
                    <th class="py-3 px-4 border-r text-right">Accumulated</th>
                    <th class="py-3 px-4 text-right">NBV</th>
                    <th class="py-3 px-4 text-center">Date Recorded</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($logs)): ?>
                    <?php foreach ($logs as $l): ?>
                        <tr class="border-b border-gray-100 hover:bg-gray-50/50">
                            <td class="py-2.5 px-4 font-medium"><?= htmlspecialchars($l['description'] ?? 'Unknown Asset') ?></td>
                            <td class="py-2.5 px-4 text-blue-600 font-mono text-xs"><?= htmlspecialchars($l['property_number'] ?? 'N/A') ?></td>
                            <td class="py-2.5 px-4"><?= htmlspecialchars($l['period_label']) ?></td>
                            <td class="py-2.5 px-4 text-right text-green-600">₱<?= number_format((float) $l['depreciation_amount'], 2) ?></td>
                            <td class="py-2.5 px-4 text-right text-orange-600">₱<?= number_format((float) $l['accumulated_depreciation'], 2) ?></td>
                            <td class="py-2.5 px-4 text-right font-semibold">₱<?= number_format((float) $l['net_book_value'], 2) ?></td>
                            <td class="py-2.5 px-4 text-center text-gray-500 text-xs"><?= date('M d, Y', strtotime($l['created_at'] ?? 'now')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="py-10 text-center text-gray-500">No depreciation entries yet. Select an asset and record your first depreciation entry above.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function showToast(message) {
    const toast = document.getElementById('toastContainer');
    const toastMessage = document.getElementById('toastMessage');
    toastMessage.innerText = message;
    toast.style.display = 'block';
    setTimeout(hideToast, 4000);
}

function hideToast() {
    const toast = document.getElementById('toastContainer');
    toast.style.animation = 'fadeOut 0.3s ease-in forwards';
    setTimeout(() => {
        toast.style.display = 'none';
        toast.style.animation = 'slideIn 0.3s ease-out';
    }, 300);
}

<?php if (!empty($_GET['saved'])): ?>
    showToast("Depreciation entry saved successfully! Asset values updated.");
<?php endif; ?>

<?php if (!empty($_GET['error']) && $error_msg): ?>
    showToast("<?= htmlspecialchars($error_msg) ?>");
<?php endif; ?>

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

(function () {
    const sel = document.getElementById('depreciationAssetPicker_native');
    const acc = document.getElementById('accumulatedDepInput');
    const nbv = document.getElementById('netBookValueInput');
    const depAmount = document.getElementById('depreciationAmount');
    const currentValuesCard = document.getElementById('currentValuesCard');
    const currentCostDisplay = document.getElementById('currentCostDisplay');
    const currentAccDisplay = document.getElementById('currentAccDisplay');
    const currentNBVDisplay = document.getElementById('currentNBVDisplay');
    
    const depError = document.getElementById('depError');
    const accError = document.getElementById('accError');
    const nbvError = document.getElementById('nbvError');
    
    let currentTotalCost = 0;
    
    function formatCurrency(value) {
        return '₱' + parseFloat(value).toLocaleString(undefined, {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }
    
    function clearErrors() {
        if (depError) depError.classList.remove('show');
        if (accError) accError.classList.remove('show');
        if (nbvError) nbvError.classList.remove('show');
        
        const inputs = [depAmount, acc, nbv];
        inputs.forEach(input => {
            if (input) input.classList.remove('field-error');
        });
    }
    
    function showError(input, errorElement, message) {
        if (errorElement) {
            errorElement.textContent = message;
            errorElement.classList.add('show');
        }
        if (input) input.classList.add('field-error');
    }
    
    function validateDepreciation() {
        clearErrors();
        
        const totalCost = currentTotalCost;
        const accumulated = parseFloat(acc.value) || 0;
        const netBookValue = parseFloat(nbv.value) || 0;
        const depreciation = parseFloat(depAmount?.value) || 0;
        
        let isValid = true;
        
        if (depAmount && depreciation < 0) {
            showError(depAmount, depError, 'Depreciation amount cannot be negative.');
            isValid = false;
        }
        
        if (accumulated < 0) {
            showError(acc, accError, 'Accumulated depreciation cannot be negative.');
            isValid = false;
        } else if (totalCost > 0 && accumulated > totalCost) {
            showError(acc, accError, 'Accumulated depreciation cannot exceed total cost (₱' + totalCost.toFixed(2) + ').');
            isValid = false;
        }
        
        if (netBookValue < 0) {
            showError(nbv, nbvError, 'Net book value cannot be negative.');
            isValid = false;
        }
        
        if (totalCost > 0 && isValid) {
            const calculatedNBV = totalCost - accumulated;
            if (Math.abs(netBookValue - calculatedNBV) > 0.01) {
                if (nbv) nbv.value = calculatedNBV.toFixed(2);
            }
        }
        
        return isValid;
    }
    
    if (acc) {
        acc.addEventListener('input', function() {
            const accumulated = parseFloat(this.value) || 0;
            const calculatedNBV = currentTotalCost - accumulated;
            if (nbv && !isNaN(calculatedNBV) && calculatedNBV >= 0) {
                nbv.value = calculatedNBV.toFixed(2);
                if (accumulated <= currentTotalCost && accumulated >= 0) {
                    if (accError) accError.classList.remove('show');
                    acc.classList.remove('field-error');
                }
            }
        });
        acc.addEventListener('blur', validateDepreciation);
    }
    
    if (nbv) {
        nbv.addEventListener('blur', validateDepreciation);
    }
    
    if (depAmount) {
        depAmount.addEventListener('blur', validateDepreciation);
    }
    
    function updateCurrentValuesDisplay() {
        if (!sel || !sel.selectedOptions.length) {
            currentValuesCard.style.display = 'none';
            return;
        }
        const opt = sel.selectedOptions[0];
        if (!opt || !opt.value) {
            currentValuesCard.style.display = 'none';
            return;
        }
        
        currentTotalCost = parseFloat(opt.dataset.unitValue || 0);
        const currentAcc = parseFloat(opt.dataset.accumulated || 0);
        const currentNBV = parseFloat(opt.dataset.nbv || currentTotalCost);
        
        currentCostDisplay.textContent = formatCurrency(currentTotalCost);
        currentAccDisplay.textContent = formatCurrency(currentAcc);
        currentNBVDisplay.textContent = formatCurrency(currentNBV);
        currentValuesCard.style.display = 'block';
        
        if (acc && (acc.value === '' || parseFloat(acc.value) === 0)) {
            acc.value = currentAcc;
        }
        if (nbv && (nbv.value === '' || parseFloat(nbv.value) === 0)) {
            nbv.value = currentNBV;
        }
        
        clearErrors();
    }
    
    if (sel) {
        sel.addEventListener('change', function() {
            updateCurrentValuesDisplay();
        });
        updateCurrentValuesDisplay();
    }
    
    const clearBtn = document.getElementById('clearBtn');
    if (clearBtn) {
        clearBtn.addEventListener('click', function() {
            if (depAmount) depAmount.value = '';
            if (acc) acc.value = '';
            if (nbv) nbv.value = '';
            const periodLabel = document.querySelector('input[name="period_label"]');
            if (periodLabel) periodLabel.value = '';
            
            clearErrors();
            
            updateCurrentValuesDisplay();
        });
    }
    
    const form = document.getElementById('depreciationForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            if (!validateDepreciation()) {
                e.preventDefault();
                const firstError = document.querySelector('.field-error');
                if (firstError) {
                    firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }
        });
    }
})();

</script>
<script src="../realtime_notifications.js"></script>

<?php include __DIR__ . '/../includes/treasurer_layout_end.php'; ?>
