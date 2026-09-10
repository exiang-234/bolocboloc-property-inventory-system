
<?php
session_start();
include '../config/database.php';
require_once __DIR__ . '/../config/auth_helpers.php';
require_once __DIR__ . '/../config/coa_labels.php';
require_once __DIR__ . '/../config/treasurer_page_helpers.php';
require_once __DIR__ . '/../config/schema_bootstrap.php';
require_once __DIR__ . '/../config/asset_units_helpers.php';
require_once __DIR__ . '/../config/asset_list_helpers.php';
require_once __DIR__ . '/../config/audit_scan_helpers.php';
bpis_require_login(['Treasurer', 'Barangay Captain']);

$bpis_treasurer_nav_active = 'scan';
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

$scan_msg = '';
$scan_count = null;
$found = null;
$scan_asset_row = null;
$tag_raw = trim((string) ($_GET['tag'] ?? $_POST['tag'] ?? ''));
$tag = bpis_parse_scanned_tag($tag_raw);

if ($tag_raw !== '') {
    $lookup = bpis_lookup_asset_by_scanned_tag($conn, $tag_raw);
    $found = $lookup['found'];
    $scan_msg = $lookup['message'];
    $tag = $lookup['tag'];

    if ($found) {
        $asset_id = bpis_resolve_asset_id_from_lookup($found);
        if ($asset_id > 0) {
            $scan_asset_row = bpis_get_asset_inventory_display_row($conn, $asset_id, $tag);
            $admin_id = (int) ($_SESSION['admin_id'] ?? 0);
            $scan_count = bpis_record_audit_unit_scan($conn, $lookup, $admin_id > 0 ? $admin_id : null);
            if (!empty($scan_count['message'])) {
                $scan_msg = (string) $scan_count['message'];
                
                if ($scan_count['property_count'] > 0 && $admin_id > 0) {
                    $asset_name = isset($scan_asset_row['description']) ? $scan_asset_row['description'] : 'Asset';
                    bpis_add_notification($conn, $admin_id, 'Asset Scanned', 
                        "Asset '$asset_name' was successfully scanned for inventory count. Physical count: " . ($scan_count['property_count'] ?? 0), 
                        'success', 'scan.php');
                }
            }
        }
    }
}

$bpis_page_title = 'QR Scan Count';
$bpis_page_heading = 'QR Scan Count';
$bpis_page_subtitle = 'Scan property tags during annual inventory (COA physical count).';
$bpis_extra_head = '<link rel="stylesheet" href="../css/asset_inventory_image.css">
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">';
include __DIR__ . '/../includes/treasurer_layout_start.php';
?>

<style>
    .notification-bell {
        position: relative;
        cursor: pointer;
        transition: transform 0.2s;
        display: inline-block;
        margin-left: 15px;
    }
    .notification-bell:hover {
        transform: scale(1.05);
    }
    .notification-bell i {
        font-size: 20px;
        color: #4b5563;
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
        position: sticky;
        top: 0;
        background: white;
        z-index: 1;
    }
    .notification-header button {
        background: none;
        border: none;
        color: #3b82f6;
        cursor: pointer;
        font-size: 12px;
    }
    .notification-header button:hover {
        text-decoration: underline;
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
        line-height: 1.4;
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
        background: none;
        border: none;
    }
    .notification-bell-container {
        position: relative;
        display: inline-block;
    }
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    @media (max-width: 1024px) {
        .notification-dropdown {
            width: 320px !important;
            max-height: 360px !important;
            border-radius: 10px !important;
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
            padding: 24px !important;
            font-size: 12px !important;
        }
        .mark-all-read {
            font-size: 11px !important;
        }
        .notification-bell i {
            font-size: 18px !important;
        }
        .notification-badge {
            min-width: 16px !important;
            height: 16px !important;
            font-size: 9px !important;
            top: -7px !important;
            right: -7px !important;
        }
        .notification-bell {
            margin-left: 12px !important;
        }
    }

    @media (max-width: 768px) {
        .notification-dropdown {
            width: 280px !important;
            max-height: 320px !important;
            right: -20px !important;
            top: 40px !important;
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
            margin-bottom: 2px !important;
        }
        .notification-message {
            font-size: 9px !important;
            margin-bottom: 2px !important;
        }
        .notification-time {
            font-size: 8px !important;
        }
        .empty-notifications {
            padding: 20px !important;
            font-size: 11px !important;
        }
        .mark-all-read {
            font-size: 10px !important;
        }
        .notification-bell i {
            font-size: 16px !important;
        }
        .notification-badge {
            min-width: 14px !important;
            height: 14px !important;
            font-size: 8px !important;
            top: -6px !important;
            right: -6px !important;
            padding: 0 3px !important;
        }
        .notification-bell {
            margin-left: 10px !important;
        }
    }

    @media (max-width: 480px) {
        .notification-dropdown {
            width: 240px !important;
            max-height: 280px !important;
            right: -10px !important;
            top: 38px !important;
            border-radius: 8px !important;
            box-shadow: 0 8px 20px -5px rgba(0,0,0,0.15) !important;
        }
        .notification-header {
            font-size: 11px !important;
            padding: 6px 10px !important;
        }
        .notification-header button {
            font-size: 9px !important;
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
            margin-bottom: 2px !important;
        }
        .notification-time {
            font-size: 7px !important;
        }
        .empty-notifications {
            padding: 16px !important;
            font-size: 10px !important;
        }
        .mark-all-read {
            font-size: 9px !important;
        }
        .notification-bell i {
            font-size: 14px !important;
        }
        .notification-badge {
            min-width: 12px !important;
            height: 12px !important;
            font-size: 7px !important;
            top: -5px !important;
            right: -5px !important;
            padding: 0 2px !important;
        }
        .notification-bell {
            margin-left: 8px !important;
        }
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.08); }
            100% { transform: scale(1); }
        }
    }

    @media (max-width: 360px) {
        .notification-dropdown {
            width: 200px !important;
            max-height: 240px !important;
            right: -5px !important;
            top: 35px !important;
            border-radius: 6px !important;
        }
        .notification-header {
            font-size: 10px !important;
            padding: 4px 8px !important;
        }
        .notification-header button {
            font-size: 8px !important;
        }
        .notification-item {
            padding: 4px 8px !important;
        }
        .notification-title {
            font-size: 9px !important;
            margin-bottom: 1px !important;
        }
        .notification-message {
            font-size: 7px !important;
            margin-bottom: 1px !important;
        }
        .notification-time {
            font-size: 6px !important;
        }
        .empty-notifications {
            padding: 12px !important;
            font-size: 9px !important;
        }
        .mark-all-read {
            font-size: 8px !important;
        }
        .notification-bell i {
            font-size: 12px !important;
        }
        .notification-badge {
            min-width: 10px !important;
            height: 10px !important;
            font-size: 6px !important;
            top: -4px !important;
            right: -4px !important;
            padding: 0 2px !important;
        }
        .notification-bell {
            margin-left: 6px !important;
        }
    }

    .qr-scan-page {
        min-width: 0;
    }

    .qr-scan-reader {
        aspect-ratio: 4 / 3;
        max-height: 460px;
        min-height: 260px;
    }

    .qr-scan-actions,
    .qr-scan-lookup,
    .qr-scan-links {
        min-width: 0;
    }

    .qr-scan-actions > button,
    .qr-scan-actions > label {
        min-height: 42px;
        justify-content: center;
    }

    .qr-scan-table-wrap {
        max-width: 100%;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    @media (max-width: 768px) {
        .qr-scan-page h3 {
            font-size: 12px !important;
            margin-bottom: 14px !important;
        }

        #scanStatus {
            width: 100% !important;
            font-size: 12px !important;
            padding: 9px 10px !important;
            margin-bottom: 12px !important;
        }

        #reader.qr-scan-reader {
            width: 100% !important;
            max-width: none !important;
            min-height: 240px !important;
            margin-bottom: 12px !important;
            border-radius: 12px !important;
        }

        .qr-scan-actions {
            display: grid !important;
            grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
            gap: 10px !important;
            max-width: none !important;
        }

        .qr-scan-actions > button,
        .qr-scan-actions > label {
            width: 100% !important;
            min-width: 0 !important;
            min-height: 44px !important;
            padding: 10px 12px !important;
            font-size: 12px !important;
            white-space: normal !important;
            line-height: 1.2 !important;
            text-align: center !important;
        }

        .qr-scan-actions .hidden {
            display: none !important;
        }

        .qr-scan-lookup {
            flex-direction: column !important;
            gap: 10px !important;
            max-width: none !important;
        }

        .qr-scan-lookup input,
        .qr-scan-lookup button {
            width: 100% !important;
            min-height: 44px !important;
        }

        .qr-scan-count-card {
            max-width: none !important;
            padding: 12px !important;
        }

        .qr-scan-count-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
            gap: 8px !important;
        }

        .qr-scan-count-grid > div {
            padding: 9px 8px !important;
        }

        .qr-scan-table-wrap table {
            min-width: 760px !important;
            width: max-content !important;
        }

        .qr-scan-table-wrap th,
        .qr-scan-table-wrap td {
            font-size: 12px !important;
            padding: 10px 12px !important;
            line-height: 1.35 !important;
        }

        .qr-scan-links {
            flex-direction: column !important;
            align-items: stretch !important;
            gap: 8px !important;
        }

        .qr-scan-links a {
            min-height: 40px !important;
            align-items: center !important;
            justify-content: center !important;
            border: 1px solid #bfdbfe !important;
            border-radius: 10px !important;
            background: #eff6ff !important;
            padding: 8px 10px !important;
        }
    }

    @media (max-width: 480px) {
        #reader.qr-scan-reader {
            min-height: 220px !important;
            aspect-ratio: 1 / 1 !important;
        }

        .qr-scan-actions {
            grid-template-columns: 1fr !important;
        }

        .qr-scan-count-grid {
            grid-template-columns: 1fr !important;
        }

        .qr-scan-table-wrap table {
            min-width: 700px !important;
        }

        .qr-scan-table-wrap th,
        .qr-scan-table-wrap td {
            font-size: 11px !important;
            padding: 9px 10px !important;
        }
    }
</style>

<div class="qr-scan-page w-full max-w-full">
    <h3 class="text-[13px] font-bold text-gray-800 mb-5 uppercase tracking-wider border-b border-gray-100 pb-2 flex items-center gap-2">
        <i data-lucide="qr-code" class="w-4 h-4 text-blue-500"></i> Scan property tag
    </h3>

    <div id="scanStatus" class="scan-status scan-status-info" role="status"></div>

    <div id="reader" class="qr-scan-reader w-full max-w-2xl border border-gray-300 rounded-xl overflow-hidden mb-3"></div>

    <div class="qr-scan-actions flex flex-wrap gap-2 mb-4 max-w-2xl">
        <button type="button" id="btnStartCamera" class="bg-blue-600 hover:bg-blue-700 text-white font-medium text-sm py-2.5 px-5 rounded-lg shadow-md transition-colors inline-flex items-center gap-2">
            <i data-lucide="camera" class="w-4 h-4"></i> Start camera
        </button>
        <button type="button" id="btnStopCamera" class="border border-gray-300 text-gray-700 font-medium text-sm py-2.5 px-5 rounded-lg hover:bg-gray-50 transition-colors inline-flex items-center gap-2">
            <i data-lucide="square" class="w-4 h-4"></i> Stop
        </button>
        <button type="button" id="btnSwitchCamera" class="hidden border border-gray-300 text-gray-700 font-medium text-sm py-2.5 px-5 rounded-lg hover:bg-gray-50 transition-colors inline-flex items-center gap-2">
            <i data-lucide="refresh-cw" class="w-4 h-4"></i> Switch camera
        </button>
        <label class="border border-blue-200 bg-blue-50 text-blue-800 font-medium text-sm py-2.5 px-5 rounded-lg hover:bg-blue-100 transition-colors inline-flex items-center gap-2 cursor-pointer">
            <i data-lucide="upload" class="w-4 h-4"></i> Upload QR image
            <input type="file" id="qrImageFile" accept="image/*" class="hidden">
        </label>
    </div>

    <form method="get" id="tagLookupForm" class="qr-scan-lookup flex gap-2 mb-4 max-w-2xl">
        <input type="text" name="tag" id="tagInput" value="<?= htmlspecialchars($tag !== '' ? $tag : $tag_raw) ?>" placeholder="e.g. PROP-2026-CAN-001" required class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500" title="Property tag only; extra QR text after | is ignored">
        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium text-sm py-2.5 px-6 rounded-lg shadow-md transition-colors whitespace-nowrap">Lookup</button>
    </form>

    <?php if ($scan_msg): ?>
        <div class="mb-4 rounded-lg border px-4 py-3 text-sm max-w-2xl <?= $found ? 'border-green-200 bg-green-50 text-green-800' : 'border-red-200 bg-red-50 text-red-800' ?>">
            <i class="fa-solid fa-<?= $found ? 'check-circle' : 'exclamation-circle' ?> mr-2"></i>
            <?= htmlspecialchars($scan_msg) ?>
        </div>
    <?php endif; ?>

    <?php if ($scan_asset_row): ?>
        <?php if ($scan_count): ?>
            <div class="qr-scan-count-card mb-4 rounded-lg border px-4 py-3 text-sm max-w-2xl <?= !empty($scan_count['duplicate']) ? 'border-amber-200 bg-amber-50 text-amber-900' : 'border-blue-200 bg-blue-50 text-blue-900' ?>">
                <p class="font-semibold mb-2"><i class="fa-solid fa-chart-simple mr-2"></i>Physical count (Audit)</p>
                <div class="qr-scan-count-grid grid grid-cols-2 sm:grid-cols-4 gap-3 text-center">
                    <div class="bg-white/70 rounded-lg px-3 py-2 border border-blue-100">
                        <p class="text-[10px] uppercase tracking-wide text-gray-500">Property count</p>
                        <p class="text-lg font-bold text-blue-800"><?= (int) ($scan_count['property_count'] ?? 0) ?></p>
                    </div>
                    <div class="bg-white/70 rounded-lg px-3 py-2 border border-orange-100">
                        <p class="text-[10px] uppercase tracking-wide text-gray-500">On-hand count</p>
                        <p class="text-lg font-bold text-orange-700"><?= (int) ($scan_count['on_hand'] ?? 0) ?></p>
                    </div>
                    <div class="bg-white/70 rounded-lg px-3 py-2 border border-gray-100">
                        <p class="text-[10px] uppercase tracking-wide text-gray-500">Shortage / overage</p>
                        <p class="text-lg font-bold <?= (int) ($scan_count['diff_qty'] ?? 0) === 0 ? 'text-green-700' : 'text-red-700' ?>">
                            <?= (int) ($scan_count['diff_qty'] ?? 0) ?>
                        </p>
                    </div>
                    <div class="bg-white/70 rounded-lg px-3 py-2 border border-gray-100">
                        <p class="text-[10px] uppercase tracking-wide text-gray-500">Value diff</p>
                        <p class="text-sm font-bold text-gray-800">₱ <?= number_format((float) ($scan_count['diff_val'] ?? 0), 2) ?></p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        <h4 class="text-[13px] font-bold text-gray-800 mb-3 uppercase tracking-wider">Scanned asset</h4>
        <?php include __DIR__ . '/../includes/treasurer_scan_asset_table.php'; ?>
        <div class="qr-scan-links mt-3 flex flex-wrap gap-3 text-sm">
            <a href="inventory.php" class="text-blue-600 hover:text-blue-700 font-semibold inline-flex items-center gap-1">Open full Inventory <i data-lucide="arrow-right" class="w-4 h-4"></i></a>
            <a href="audit.php<?= !empty($scan_count['asset_id']) ? '?highlight=' . (int) $scan_count['asset_id'] : '' ?>" class="text-blue-600 hover:text-blue-700 font-semibold inline-flex items-center gap-1">Report (RPCPPE) <i data-lucide="arrow-right" class="w-4 h-4"></i></a>
        </div>
    <?php elseif ($tag_raw !== '' && !$found): ?>
        <p class="text-sm text-gray-500 mb-4"><i class="fa-solid fa-search mr-2"></i>Tag not found. Register the asset or check the code.</p>
    <?php endif; ?>
</div>

<script>
let notificationBell, notificationDropdown, notificationBadge, notificationList, markAllReadBtn;

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
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function loadNotifications() {
    fetch('notifications_feed.php', { 
        method: 'GET',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (data.unread_count > 0) {
                if (notificationBadge) {
                    notificationBadge.style.display = 'flex';
                    notificationBadge.textContent = data.unread_count > 99 ? '99+' : data.unread_count;
                }
            } else {
                if (notificationBadge) {
                    notificationBadge.style.display = 'none';
                }
            }
            
            if (notificationList) {
                if (data.notifications && data.notifications.length > 0) {
                    let html = '';
                    data.notifications.forEach(notif => {
                        const unreadClass = notif.is_read == 1 ? '' : 'unread';
                        html += `
                            <div class="notification-item ${unreadClass}" onclick="markAsRead(${notif.id}, '${escapeHtml(notif.link || '')}')">
                                <div class="notification-title">${escapeHtml(notif.title)}</div>
                                <div class="notification-message">${escapeHtml(notif.message)}</div>
                                <div class="notification-time">${formatTime(notif.created_at)}</div>
                            </div>
                        `;
                    });
                    notificationList.innerHTML = html;
                } else {
                    notificationList.innerHTML = '<div class="empty-notifications"><i class="fa-regular fa-bell-slash"></i> No notifications</div>';
                }
            }
        } else {
            console.error('Failed to load notifications:', data.message);
            if (notificationList) {
                notificationList.innerHTML = '<div class="empty-notifications"><i class="fa-solid fa-exclamation-triangle"></i> ' + escapeHtml(data.message || 'Error loading notifications') + '</div>';
            }
        }
    })
    .catch(error => {
        console.error('Error loading notifications:', error);
        if (notificationList) {
            notificationList.innerHTML = '<div class="empty-notifications"><i class="fa-solid fa-exclamation-triangle"></i> Error loading notifications</div>';
        }
    });
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
            if (link && link !== '') {
                window.location.href = link;
            }
        }
    })
    .catch(error => console.error('Error marking as read:', error));
}

document.addEventListener('DOMContentLoaded', function() {
    notificationBell = document.getElementById('notificationBell');
    notificationDropdown = document.getElementById('notificationDropdown');
    notificationBadge = document.getElementById('notificationBadge');
    notificationList = document.getElementById('notificationList');
    markAllReadBtn = document.getElementById('markAllReadBtn');
    
    if (notificationBell) {
        notificationBell.addEventListener('click', function(e) {
            e.stopPropagation();
            if (notificationDropdown) {
                notificationDropdown.classList.toggle('active');
                if (notificationDropdown.classList.contains('active')) {
                    loadNotifications();
                }
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
                if (data.success) {
                    loadNotifications();
                    if (notificationBadge) {
                        notificationBadge.style.display = 'none';
                    }
                }
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
});
</script>

<script src="../js/report_scan.js"></script>
<?php include __DIR__ . '/../includes/treasurer_layout_end.php'; ?>
