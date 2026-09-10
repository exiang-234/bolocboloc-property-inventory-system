<?php
session_start();
include '../config/database.php';
require_once __DIR__ . '/../config/auth_helpers.php';
require_once __DIR__ . '/../config/treasurer_page_helpers.php';
require_once __DIR__ . '/../config/asset_list_helpers.php';
require_once __DIR__ . '/../config/upload_helpers.php';
require_once __DIR__ . '/../config/csrf_helpers.php';
bpis_require_login(['Treasurer', 'Barangay Captain']);

$bpis_treasurer_nav_active = 'history';
$bpis_u = bpis_treasurer_load_user($conn);
$user_fullname = $bpis_u['fullname'];
$user_role = $bpis_u['role'];

function bpis_ensure_history_table($conn) {
    $create_sql = "CREATE TABLE IF NOT EXISTS asset_history_snapshots (
        id INT AUTO_INCREMENT PRIMARY KEY,
        asset_id INT NOT NULL,
        period_type ENUM('Monthly', 'Quarterly', 'Annual') NOT NULL,
        period_label VARCHAR(100) NOT NULL,
        quantity INT NOT NULL,
        condition_note TEXT,
        remarks TEXT,
        photo_path VARCHAR(500),
        snapshot_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_asset (asset_id),
        INDEX idx_period (period_type, period_label),
        FOREIGN KEY (asset_id) REFERENCES asset(id) ON DELETE CASCADE
    )";
    mysqli_query($conn, $create_sql);
}
bpis_ensure_history_table($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'add';
    $snapshot_id = isset($_POST['snapshot_id']) ? (int)$_POST['snapshot_id'] : 0;
    $asset_id = (int) ($_POST['asset_id'] ?? 0);
    $ptype = in_array($_POST['period_type'] ?? '', ['Monthly', 'Quarterly', 'Annual'], true) ? $_POST['period_type'] : 'Quarterly';
    $plabel = trim((string) ($_POST['period_label'] ?? ''));
    $qty = (int) ($_POST['quantity'] ?? 0);
    $cond = trim((string) ($_POST['condition_note'] ?? ''));
    $remarks = trim((string) ($_POST['remarks'] ?? ''));
    
    $photo_path = '';
    if (isset($_FILES['snapshot_photo']) && $_FILES['snapshot_photo']['error'] === UPLOAD_ERR_OK) {
        $photo_path = bpis_store_upload('snapshot_photo', 'history');
        if ($photo_path === null) $photo_path = '';
    }
    
    $camera_photo = isset($_POST['camera_photo']) ? trim((string) $_POST['camera_photo']) : '';
    if ($camera_photo !== '' && $photo_path === '') {
        $image_parts = explode(';base64,', $camera_photo);
        if (count($image_parts) >= 2) {
            $image_base64 = base64_decode($image_parts[1]);
            $finfo = finfo_open();
            $mime_type = finfo_buffer($finfo, $image_base64, FILEINFO_MIME_TYPE);
            finfo_close($finfo);
            
            $extension = 'jpg';
            if ($mime_type === 'image/png') $extension = 'png';
            elseif ($mime_type === 'image/webp') $extension = 'webp';
            
            $upload_dir = __DIR__ . '/../uploads/history/';
            if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);
            
            $filename = time() . '_history_' . bin2hex(random_bytes(8)) . '.' . $extension;
            $filepath = $upload_dir . $filename;
            
            if (file_put_contents($filepath, $image_base64)) {
                $photo_path = 'uploads/history/' . $filename;
            }
        }
    }
    
    if ($asset_id > 0 && $plabel !== '') {
        if ($action === 'edit' && $snapshot_id > 0) {
            $update_sql = "UPDATE asset_history_snapshots SET 
                           period_type = ?, period_label = ?, quantity = ?, 
                           condition_note = ?, remarks = ?";
            $params = [$ptype, $plabel, $qty, $cond, $remarks];
            $types = "ssiss";
            
            if ($photo_path !== '') {
                $update_sql .= ", photo_path = ?";
                $params[] = $photo_path;
                $types .= "s";
            }
            
            $update_sql .= " WHERE id = ?";
            $params[] = $snapshot_id;
            $types .= "i";
            
            $stmt = $conn->prepare($update_sql);
            if ($stmt) {
                $stmt->bind_param($types, ...$params);
                $stmt->execute();
                $stmt->close();
                header('Location: inventory_history.php?edited=1');
                exit;
            }
        } else {
            $stmt = $conn->prepare('INSERT INTO asset_history_snapshots (asset_id, period_type, period_label, quantity, condition_note, remarks, photo_path) VALUES (?,?,?,?,?,?,?)');
            if ($stmt) {
                $stmt->bind_param('ississs', $asset_id, $ptype, $plabel, $qty, $cond, $remarks, $photo_path);
                $stmt->execute();
                $stmt->close();
                header('Location: inventory_history.php?saved=1');
                exit;
            }
        }
    }
}

if (isset($_POST['delete'])) {
    bpis_csrf_check_post();
    $delete_id = (int)$_POST['delete'];
    $stmt = $conn->prepare('DELETE FROM asset_history_snapshots WHERE id = ?');
    if ($stmt) {
        $stmt->bind_param('i', $delete_id);
        $stmt->execute();
        $stmt->close();
    }
    header('Location: inventory_history.php?deleted=1');
    exit;
}

$filter_category = isset($_GET['filter_category']) ? mysqli_real_escape_string($conn, $_GET['filter_category']) : '';
$filter_asset_id = isset($_GET['filter_asset']) ? (int)$_GET['filter_asset'] : 0;
$filter_period_type = isset($_GET['period_type']) ? $_GET['period_type'] : '';
$filter_year = isset($_GET['year']) ? (int)$_GET['year'] : 0;

$history_query = "SELECT h.*, a.description, a.article, a.property_number, a.photo_path as asset_photo, a.category 
                  FROM asset_history_snapshots h 
                  JOIN asset a ON a.id = h.asset_id 
                  WHERE 1=1";
$params = [];
$types = "";

if ($filter_category !== '') {
    $history_query .= " AND a.category = ?";
    $params[] = $filter_category;
    $types .= "s";
}
if ($filter_asset_id > 0) {
    $history_query .= " AND h.asset_id = ?";
    $params[] = $filter_asset_id;
    $types .= "i";
}
if ($filter_period_type !== '') {
    $history_query .= " AND h.period_type = ?";
    $params[] = $filter_period_type;
    $types .= "s";
}
if ($filter_year > 0) {
    $history_query .= " AND YEAR(h.snapshot_at) = ?";
    $params[] = $filter_year;
    $types .= "i";
}

$history_query .= " ORDER BY h.snapshot_at DESC LIMIT 100";

$history = [];
$stmt = $conn->prepare($history_query);
if ($stmt) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $history[] = $row;
    }
    $stmt->close();
}

$display_assets = [];
$all_assets_list = [];

if ($filter_category !== '') {
    $asset_sql = "SELECT id, article, description, property_number, unit_measure, quantity, category FROM asset WHERE category = ? ORDER BY id DESC";
    $asset_stmt = $conn->prepare($asset_sql);
    if ($asset_stmt) {
        $asset_stmt->bind_param("s", $filter_category);
        $asset_stmt->execute();
        $asset_result = $asset_stmt->get_result();
        while ($row = $asset_result->fetch_assoc()) {
            $display_assets[] = [
                'id' => $row['id'],
                'display_label' => $row['description'] . ' (' . $row['quantity'] . ' ' . $row['unit_measure'] . ')',
                'prop_num' => $row['property_number'],
                'category' => $row['category'],
                'quantity' => $row['quantity']
            ];
        }
        $asset_stmt->close();
    }
} else {
    $asset_sql = "SELECT id, article, description, property_number, unit_measure, quantity, category FROM asset ORDER BY id DESC";
    $asset_result = mysqli_query($conn, $asset_sql);
    if ($asset_result) {
        while ($row = mysqli_fetch_assoc($asset_result)) {
            $display_assets[] = [
                'id' => $row['id'],
                'display_label' => $row['description'] . ' (' . $row['quantity'] . ' ' . $row['unit_measure'] . ')',
                'prop_num' => $row['property_number'],
                'category' => $row['category'],
                'quantity' => $row['quantity']
            ];
        }
    }
}

$all_assets_query = "SELECT id, article, description, property_number, unit_measure, quantity, category FROM asset ORDER BY id DESC";
$all_assets_result = mysqli_query($conn, $all_assets_query);
$all_assets = [];
if ($all_assets_result) {
    while ($row = mysqli_fetch_assoc($all_assets_result)) {
        $all_assets[] = [
            'id' => $row['id'],
            'display_label' => $row['description'] . ' (' . $row['quantity'] . ' ' . $row['unit_measure'] . ')',
            'prop_num' => $row['property_number'],
            'category' => $row['category'],
            'quantity' => $row['quantity']
        ];
    }
}

$current_year = date('Y');
$years_from_db = [];
$year_query = "SELECT DISTINCT YEAR(snapshot_at) as yr FROM asset_history_snapshots ORDER BY yr DESC";
$year_result = mysqli_query($conn, $year_query);
if ($year_result) {
    while ($row = mysqli_fetch_assoc($year_result)) {
        $years_from_db[] = $row['yr'];
    }
}
$all_years = array_unique(array_merge(range(1900, $current_year), $years_from_db));
rsort($all_years);

$highlight_asset_id = (int) ($_GET['asset_id'] ?? bpis_peek_last_registered_asset());

$bpis_page_title = 'Asset History Dashboard';
$bpis_page_heading = 'History Dashboard';
$bpis_page_subtitle = 'Track asset condition, quantity, and photos over time (Monthly / Quarterly / Annual)';
include __DIR__ . '/../includes/treasurer_layout_start.php';
?>

<style>
    .history-card {
        transition: transform 0.2s, box-shadow 0.2s;
    }
    .history-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1);
    }
    .condition-badge {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 600;
    }
    .condition-good { background: #dcfce7; color: #166534; }
    .condition-fair { background: #fef9c3; color: #854d0e; }
    .condition-poor { background: #fee2e2; color: #991b1b; }
    .period-tag {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 12px;
        font-size: 10px;
        font-weight: 600;
    }
    .period-monthly { background: #e0e7ff; color: #3730a3; }
    .period-quarterly { background: #dbeafe; color: #1e40af; }
    .period-annual { background: #fce7f3; color: #9d174d; }
    
    .filter-bar {
        background: white;
        border-radius: 12px;
        border: 1px solid #e5e7eb;
        padding: 16px;
    }
    .filter-label {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 13px;
        font-weight: 500;
        color: #374151;
    }
    
    .asset-list-container {
        background: #f9fafb;
        border-radius: 12px;
        padding: 16px;
        margin-top: 16px;
        border: 1px solid #e5e7eb;
        max-height: 400px;
        overflow-y: auto;
    }
    .asset-list-title {
        font-size: 12px;
        font-weight: 600;
        color: #374151;
        margin-bottom: 12px;
        display: flex;
        align-items: center;
        gap: 8px;
        position: sticky;
        top: 0;
        background: #f9fafb;
        padding-bottom: 8px;
    }
    .asset-table {
        width: 100%;
        font-size: 13px;
    }
    .asset-table th {
        text-align: left;
        padding: 8px 8px;
        background: #f3f4f6;
        font-weight: 600;
        color: #374151;
        font-size: 11px;
    }
    .asset-table td {
        padding: 8px 8px;
        border-bottom: 1px solid #e5e7eb;
    }
    .asset-row {
        cursor: pointer;
        transition: background 0.2s;
    }
    .asset-row:hover {
        background: #e0e7ff;
    }
    .asset-row.selected {
        background: #dbeafe;
        border-left: 3px solid #3b82f6;
    }
    .action-btn {
        background: linear-gradient(135deg, #174C7D 0%, #1d5f94 100%);
        color: white;
        padding: 6px 12px;
        border-radius: 6px;
        font-size: 11px;
        font-weight: 500;
        border: none;
        cursor: pointer;
        transition: all 0.2s;
    }
    .action-btn:hover {
        background: linear-gradient(135deg, #1d5f94 0%, #236ea8 100%);
        transform: translateY(-1px);
    }
    .delete-btn {
        background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
        color: white;
        padding: 6px 12px;
        border-radius: 6px;
        font-size: 11px;
        font-weight: 500;
        border: none;
        cursor: pointer;
        margin-left: 8px;
        transition: all 0.2s;
    }
    .delete-btn:hover {
        background: linear-gradient(135deg, #b91c1c 0%, #991b1b 100%);
        transform: translateY(-1px);
    }
    .success-msg, .info-msg, .error-msg {
        padding: 12px 16px;
        border-radius: 8px;
        margin-bottom: 16px;
    }
    .success-msg { background: #dcfce7; border: 1px solid #bbf7d0; color: #166534; }
    .info-msg { background: #dbeafe; border: 1px solid #bfdbfe; color: #1e40af; }
    .error-msg { background: #fee2e2; border: 1px solid #fecaca; color: #991b1b; }

    .delete-history-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.5);
        backdrop-filter: blur(4px);
        -webkit-backdrop-filter: blur(4px);
        z-index: 2500;
        align-items: center;
        justify-content: center;
        padding: 16px;
    }
    .delete-history-overlay.active {
        display: flex;
    }
    .delete-history-modal {
        background: white;
        border-radius: 16px;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        max-width: 420px;
        width: 100%;
        overflow: hidden;
        animation: deleteModalSlideIn 0.25s ease-out;
    }
    @keyframes deleteModalSlideIn {
        from { opacity: 0; transform: translateY(-20px) scale(0.95); }
        to { opacity: 1; transform: translateY(0) scale(1); }
    }
    .delete-history-modal-header {
        display: flex;
        flex-direction: column;
        align-items: center;
        padding: 28px 24px 12px;
    }
    .delete-history-modal-icon {
        width: 56px;
        height: 56px;
        border-radius: 50%;
        background: #fee2e2;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 16px;
    }
    .delete-history-modal-icon i {
        font-size: 22px;
        color: #dc2626;
    }
    .delete-history-modal-header h3 {
        font-size: 18px;
        font-weight: 700;
        color: #111827;
        margin: 0;
    }
    .delete-history-modal-body {
        padding: 8px 24px 20px;
        text-align: center;
    }
    .delete-history-modal-body p {
        font-size: 14px;
        color: #6b7280;
        margin: 0;
        line-height: 1.5;
    }
    .delete-history-modal-footer {
        display: flex;
        gap: 10px;
        padding: 16px 24px 24px;
        justify-content: center;
    }
    .delete-history-modal-footer button {
        padding: 10px 24px;
        border-radius: 10px;
        font-size: 14px;
        font-weight: 600;
        border: none;
        cursor: pointer;
        transition: all 0.2s;
    }
    .btn-delete-cancel {
        background: #f3f4f6;
        color: #374151;
    }
    .btn-delete-cancel:hover {
        background: #e5e7eb;
    }
    .btn-delete-confirm {
        background: #dc2626;
        color: white;
        display: flex;
        align-items: center;
        gap: 6px;
    }
    .btn-delete-confirm:hover {
        background: #b91c1c;
    }

    @media (max-width: 1024px) {
        .filter-bar {
            padding: 14px !important;
            border-radius: 10px !important;
        }
        .filter-label {
            font-size: 12px !important;
        }
        .asset-list-container {
            padding: 14px !important;
            max-height: 360px !important;
        }
        .asset-table {
            font-size: 12px !important;
        }
        .asset-table th {
            font-size: 10px !important;
            padding: 6px 6px !important;
        }
        .asset-table td {
            padding: 6px 6px !important;
        }
        .action-btn, .delete-btn {
            padding: 5px 10px !important;
            font-size: 10px !important;
        }
        .condition-badge {
            font-size: 10px !important;
            padding: 3px 8px !important;
        }
        .period-tag {
            font-size: 9px !important;
            padding: 2px 6px !important;
        }
    }

    @media (max-width: 768px) {
        .filter-bar {
            padding: 12px !important;
            border-radius: 8px !important;
        }
        .filter-label {
            font-size: 11px !important;
            gap: 6px !important;
        }
        .asset-list-container {
            padding: 12px !important;
            max-height: 320px !important;
            border-radius: 10px !important;
        }
        .asset-list-title {
            font-size: 11px !important;
            margin-bottom: 10px !important;
            padding-bottom: 6px !important;
        }
        .asset-table {
            font-size: 11px !important;
            min-width: 400px !important;
        }
        .asset-table th {
            font-size: 9px !important;
            padding: 5px 5px !important;
        }
        .asset-table td {
            padding: 5px 5px !important;
        }
        .asset-row.selected {
            border-left-width: 2px !important;
        }
        .action-btn, .delete-btn {
            padding: 4px 8px !important;
            font-size: 9px !important;
            border-radius: 4px !important;
        }
        .action-btn {
            margin-right: 4px !important;
        }
        .delete-btn {
            margin-left: 4px !important;
        }
        .condition-badge {
            font-size: 9px !important;
            padding: 2px 6px !important;
            border-radius: 14px !important;
        }
        .period-tag {
            font-size: 8px !important;
            padding: 1px 5px !important;
            border-radius: 8px !important;
        }
        .success-msg, .info-msg, .error-msg {
            font-size: 12px !important;
            padding: 10px 14px !important;
            border-radius: 6px !important;
            margin-bottom: 12px !important;
        }
        .delete-history-overlay {
            padding: 12px !important;
        }
        .delete-history-modal {
            max-width: 360px !important;
            border-radius: 12px !important;
        }
        .delete-history-modal-header {
            padding: 20px 20px 8px !important;
        }
        .delete-history-modal-icon {
            width: 48px !important;
            height: 48px !important;
            margin-bottom: 12px !important;
        }
        .delete-history-modal-icon i {
            font-size: 18px !important;
        }
        .delete-history-modal-header h3 {
            font-size: 16px !important;
        }
        .delete-history-modal-body {
            padding: 6px 20px 16px !important;
        }
        .delete-history-modal-body p {
            font-size: 13px !important;
        }
        .delete-history-modal-footer {
            padding: 12px 20px 20px !important;
            gap: 8px !important;
        }
        .delete-history-modal-footer button {
            padding: 8px 18px !important;
            font-size: 13px !important;
            border-radius: 8px !important;
        }
        .history-card:hover {
            transform: translateY(-1px) !important;
        }
    }

    @media (max-width: 480px) {
        .filter-bar {
            padding: 10px !important;
            border-radius: 6px !important;
        }
        .filter-label {
            font-size: 10px !important;
            gap: 4px !important;
        }
        .asset-list-container {
            padding: 10px !important;
            max-height: 280px !important;
            border-radius: 8px !important;
        }
        .asset-list-title {
            font-size: 10px !important;
            margin-bottom: 8px !important;
            padding-bottom: 4px !important;
        }
        .asset-table {
            font-size: 10px !important;
            min-width: 350px !important;
        }
        .asset-table th {
            font-size: 8px !important;
            padding: 4px 4px !important;
        }
        .asset-table td {
            padding: 4px 4px !important;
            font-size: 9px !important;
        }
        .action-btn, .delete-btn {
            padding: 3px 6px !important;
            font-size: 8px !important;
            border-radius: 3px !important;
        }
        .condition-badge {
            font-size: 8px !important;
            padding: 2px 5px !important;
            border-radius: 10px !important;
        }
        .period-tag {
            font-size: 7px !important;
            padding: 1px 4px !important;
            border-radius: 6px !important;
        }
        .success-msg, .info-msg, .error-msg {
            font-size: 11px !important;
            padding: 8px 12px !important;
            border-radius: 4px !important;
            margin-bottom: 10px !important;
        }
        .delete-history-overlay {
            padding: 8px !important;
        }
        .delete-history-modal {
            max-width: 320px !important;
            border-radius: 10px !important;
        }
        .delete-history-modal-header {
            padding: 16px 16px 6px !important;
        }
        .delete-history-modal-icon {
            width: 40px !important;
            height: 40px !important;
            margin-bottom: 10px !important;
        }
        .delete-history-modal-icon i {
            font-size: 16px !important;
        }
        .delete-history-modal-header h3 {
            font-size: 15px !important;
        }
        .delete-history-modal-body {
            padding: 4px 16px 12px !important;
        }
        .delete-history-modal-body p {
            font-size: 12px !important;
        }
        .delete-history-modal-footer {
            padding: 10px 16px 16px !important;
            gap: 6px !important;
            flex-direction: column !important;
        }
        .delete-history-modal-footer button {
            padding: 8px 16px !important;
            font-size: 12px !important;
            border-radius: 6px !important;
            width: 100% !important;
            justify-content: center !important;
        }
        .history-card:hover {
            transform: none !important;
        }
    }

    @media (max-width: 360px) {
        .filter-bar {
            padding: 8px !important;
            border-radius: 4px !important;
        }
        .filter-label {
            font-size: 9px !important;
        }
        .asset-list-container {
            padding: 8px !important;
            max-height: 240px !important;
            border-radius: 6px !important;
        }
        .asset-list-title {
            font-size: 9px !important;
            margin-bottom: 6px !important;
        }
        .asset-table {
            font-size: 9px !important;
            min-width: 300px !important;
        }
        .asset-table th {
            font-size: 7px !important;
            padding: 3px 3px !important;
        }
        .asset-table td {
            padding: 3px 3px !important;
            font-size: 8px !important;
        }
        .action-btn, .delete-btn {
            padding: 2px 5px !important;
            font-size: 7px !important;
        }
        .condition-badge {
            font-size: 7px !important;
            padding: 1px 4px !important;
        }
        .period-tag {
            font-size: 6px !important;
            padding: 1px 3px !important;
        }
        .success-msg, .info-msg, .error-msg {
            font-size: 10px !important;
            padding: 6px 10px !important;
        }
        .delete-history-modal {
            max-width: 280px !important;
        }
        .delete-history-modal-header h3 {
            font-size: 14px !important;
        }
        .delete-history-modal-body p {
            font-size: 11px !important;
        }
        .delete-history-modal-footer button {
            font-size: 11px !important;
            padding: 6px 12px !important;
        }
    }

    @media (max-width: 768px) {
        .asset-list-container {
            overflow-x: auto !important;
            overflow-y: auto !important;
            -webkit-overflow-scrolling: touch !important;
        }
        .asset-table {
            min-width: 720px !important;
            width: max-content !important;
            font-size: 12px !important;
            line-height: 1.35 !important;
        }
        .asset-table th {
            font-size: 11px !important;
            line-height: 1.25 !important;
            padding: 9px 8px !important;
            white-space: nowrap !important;
        }
        .asset-table td {
            font-size: 12px !important;
            line-height: 1.35 !important;
            padding: 10px 8px !important;
            white-space: nowrap !important;
        }
        .asset-table td:nth-child(2) {
            min-width: 240px !important;
            white-space: normal !important;
            overflow-wrap: anywhere !important;
        }
        .asset-table .action-btn,
        .asset-table .delete-btn {
            font-size: 11px !important;
            padding: 6px 10px !important;
        }
    }

    @media (max-width: 480px) {
        .asset-table {
            min-width: 680px !important;
        }
        .asset-table th {
            font-size: 10px !important;
            padding: 8px 7px !important;
        }
        .asset-table td {
            font-size: 11px !important;
            padding: 9px 7px !important;
        }
        .asset-table .action-btn,
        .asset-table .delete-btn {
            font-size: 10px !important;
            padding: 5px 9px !important;
        }
    }
</style>

<div class="space-y-6">
    <?php if (!empty($_GET['saved'])): ?>
        <div class="success-msg">
            <i class="fa-solid fa-check-circle mr-2"></i> History snapshot saved successfully!
        </div>
    <?php endif; ?>
    <?php if (!empty($_GET['edited'])): ?>
        <div class="info-msg">
            <i class="fa-solid fa-pen mr-2"></i> History snapshot updated successfully!
        </div>
    <?php endif; ?>
    <?php if (!empty($_GET['deleted'])): ?>
        <div class="error-msg">
            <i class="fa-solid fa-trash mr-2"></i> History snapshot deleted successfully!
        </div>
    <?php endif; ?>

    <div class="filter-bar">
        <div class="flex flex-wrap items-center gap-4">
            <div class="filter-label">
                <i data-lucide="filter" class="w-4 h-4 text-gray-500"></i>
                <span>Filter History Records:</span>
            </div>
            
            <form method="GET" class="flex flex-wrap gap-3 items-center" id="filterForm">
                <select name="filter_category" class="px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white" id="categoryFilterSelect">
                    <option value="">Select category</option>
                    <option value="Land & Improvements" <?= $filter_category == 'Land & Improvements' ? 'selected' : '' ?>>Land & Improvements</option>
                    <option value="Buildings & Structures" <?= $filter_category == 'Buildings & Structures' ? 'selected' : '' ?>>Buildings & Structures</option>
                    <option value="Machinery" <?= $filter_category == 'Machinery' ? 'selected' : '' ?>>Machinery</option>
                    <option value="Equipment" <?= $filter_category == 'Equipment' ? 'selected' : '' ?>>Equipment</option>
                    <option value="IT Equipment" <?= $filter_category == 'IT Equipment' ? 'selected' : '' ?>>IT Equipment</option>
                    <option value="Office Equipment" <?= $filter_category == 'Office Equipment' ? 'selected' : '' ?>>Office Equipment</option>
                    <option value="Furniture & Fixtures" <?= $filter_category == 'Furniture & Fixtures' ? 'selected' : '' ?>>Furniture & Fixtures</option>
                    <option value="Motor Vehicles / Delivery Truck" <?= $filter_category == 'Motor Vehicles / Delivery Truck' ? 'selected' : '' ?>>Motor Vehicles / Delivery Truck</option>
                    <option value="Tools" <?= $filter_category == 'Tools' ? 'selected' : '' ?>>Tools</option>
                </select>
                
                <select name="filter_asset" class="px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white" id="assetFilterSelect">
                    <option value="0">All Assets</option>
                    <?php foreach ($all_assets as $asset): ?>
                        <?php if ($filter_category === '' || $asset['category'] === $filter_category): ?>
                            <option value="<?= $asset['id'] ?>" <?= $filter_asset_id == $asset['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($asset['display_label']) ?>
                            </option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
                
                <select name="period_type" class="px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white">
                    <option value="">All Periods</option>
                    <option value="Monthly" <?= $filter_period_type === 'Monthly' ? 'selected' : '' ?>>Monthly</option>
                    <option value="Quarterly" <?= $filter_period_type === 'Quarterly' ? 'selected' : '' ?>>Quarterly</option>
                    <option value="Annual" <?= $filter_period_type === 'Annual' ? 'selected' : '' ?>>Annual</option>
                </select>
                
                <select name="year" class="px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white">
                    <option value="0">All Years</option>
                    <?php foreach ($all_years as $year): ?>
                        <option value="<?= $year ?>" <?= $filter_year == $year ? 'selected' : '' ?>><?= $year ?></option>
                    <?php endforeach; ?>
                </select>
                
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium py-2 px-4 rounded-lg transition-colors shadow-sm">
                    Apply Filter
                </button>
                
                <a href="inventory_history.php" class="text-gray-500 hover:text-gray-700 text-sm py-2 px-3">
                    Clear All
                </a>
            </form>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="border-b border-gray-100 px-6 py-4 bg-gray-50/50">
            <h3 class="text-[13px] font-bold text-gray-800 uppercase tracking-wider flex items-center gap-2">
                <i data-lucide="list" class="w-4 h-4 text-blue-500"></i> Asset List
            </h3>
            <p class="text-xs text-gray-500 mt-1">Click on any asset to view its history records, or click "Add History" to create a new snapshot</p>
        </div>
        
        <div class="asset-list-container">
            <div class="asset-list-title">
                <i data-lucide="package" class="w-3.5 h-3.5"></i>
                Assets 
                <?php if ($filter_category): ?>
                    in <span class="text-blue-600 font-bold"><?= htmlspecialchars($filter_category) ?></span>
                <?php else: ?>
                    (All Categories)
                <?php endif; ?>
                <span class="text-gray-400 text-xs ml-2">(<?= count($display_assets) ?> assets found)</span>
            </div>
            
            <?php if (empty($display_assets)): ?>
                <div class="text-center py-8 text-gray-500">
                    <i data-lucide="alert-circle" class="w-10 h-10 mx-auto mb-2 text-gray-300"></i>
                    <p>No assets found in this category.</p>
                    <p class="text-xs mt-1">Make sure assets have the category "<?= htmlspecialchars($filter_category) ?>" assigned.</p>
                </div>
            <?php else: ?>
                <table class="asset-table">
                    <thead>
                        <tr>
                            <th>Property #</th>
                            <th>Description</th>
                            <th>Category</th>
                            <th>Quantity</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($display_assets as $asset): ?>
                            <?php $is_selected = ($filter_asset_id == $asset['id']); ?>
                            <tr class="asset-row <?= $is_selected ? 'selected' : '' ?>" 
                                onclick="window.location.href='?filter_asset=<?= $asset['id'] ?><?= $filter_category ? '&filter_category='.urlencode($filter_category) : '' ?>'">
                                <td><?= htmlspecialchars($asset['prop_num'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($asset['display_label']) ?></td>
                                <td><?= htmlspecialchars($asset['category'] ?? '') ?></td>
                                <td><?= (int)($asset['quantity'] ?? 1) ?></td>
                                <td>
                                    <button class="action-btn" onclick="event.stopPropagation(); showAddForm(<?= $asset['id'] ?>, '<?= addslashes($asset['display_label']) ?>')">
                                        <i class="fa-solid fa-plus"></i> Add History
                                    </button>
                                 </td>
                             </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden" id="snapshotForm">
        <div class="border-b border-gray-100 px-6 py-4 bg-gray-50/50">
            <h3 class="text-[13px] font-bold text-gray-800 uppercase tracking-wider flex items-center gap-2">
                <i data-lucide="clock" class="w-4 h-4 text-blue-500"></i> 
                <span id="formTitle">Add New History Snapshot</span>
            </h3>
            <p class="text-xs text-gray-500 mt-1" id="formSubtitle">Record asset condition, quantity, and photos for monthly, quarterly, or annual reporting</p>
        </div>
        
        <form method="post" enctype="multipart/form-data" class="p-6" id="snapshotFormElement">
            <input type="hidden" name="action" id="formAction" value="add">
            <input type="hidden" name="snapshot_id" id="snapshotId" value="0">
            <input type="hidden" name="asset_id" id="selectedAssetId" value="<?= $filter_asset_id ?>">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div class="md:col-span-2">
                    <label class="block text-[13px] font-semibold text-gray-700 mb-1.5">Selected Asset</label>
                    <div class="px-3 py-2 bg-gray-100 border border-gray-200 rounded-lg text-sm" id="selectedAssetDisplay">
                        <?php
                        if ($filter_asset_id > 0) {
                            $found = false;
                            foreach ($all_assets as $asset) {
                                if ($asset['id'] == $filter_asset_id) {
                                    echo htmlspecialchars($asset['display_label']);
                                    $found = true;
                                    break;
                                }
                            }
                            if (!$found) echo "No asset selected. Please click 'Add History' next to an asset above.";
                        } else {
                            echo "No asset selected. Please click 'Add History' next to an asset above.";
                        }
                        ?>
                    </div>
                </div>
                
                <div>
                    <label class="block text-[13px] font-semibold text-gray-700 mb-1.5">Period Type <span class="text-red-500">*</span></label>
                    <select name="period_type" id="periodType" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 bg-white" required>
                        <option value="Monthly">Monthly</option>
                        <option value="Quarterly">Quarterly</option>
                        <option value="Annual">Annual</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-[13px] font-semibold text-gray-700 mb-1.5">Period Label <span class="text-red-500">*</span></label>
                    <input type="text" name="period_label" id="periodLabel" required placeholder="e.g., January 2024, Q1 2024, or FY 2024" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div>
                    <label class="block text-[13px] font-semibold text-gray-700 mb-1.5">Quantity <span class="text-red-500">*</span></label>
                    <input type="number" name="quantity" id="quantity" required min="1" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div>
                    <label class="block text-[13px] font-semibold text-gray-700 mb-1.5">Condition</label>
                    <select name="condition_note" id="conditionNote" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 bg-white">
                        <option value="">Select condition</option>
                        <option value="Good - Fully operational">Good - Fully operational</option>
                        <option value="Fair - Minor issues">Fair - Minor issues</option>
                        <option value="Poor - Needs repair">Poor - Needs repair</option>
                        <option value="Unserviceable">Unserviceable</option>
                    </select>
                </div>
                
                <div class="md:col-span-2">
                    <label class="block text-[13px] font-semibold text-gray-700 mb-1.5">Remarks / Notes</label>
                    <textarea name="remarks" id="remarks" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500" placeholder="Additional notes about the asset condition..."></textarea>
                </div>
                
                <div class="md:col-span-2">
                    <label class="block text-[13px] font-semibold text-gray-700 mb-1.5">Supporting Photo <span class="text-gray-400 text-xs">(optional)</span></label>
                    <div class="flex gap-3 items-start flex-wrap">
                        <input type="file" name="snapshot_photo" id="snapshotPhoto" accept="image/jpeg,image/png,image/webp" class="text-sm file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-blue-50 file:text-blue-700 file:font-medium hover:file:bg-blue-100">
                        <button type="button" id="openHistoryCameraBtn" class="bg-green-600 hover:bg-green-700 text-white text-sm font-medium py-2 px-4 rounded-lg flex items-center gap-2 shadow-sm transition-colors">
                            <i class="fa-solid fa-camera"></i> Take Photo
                        </button>
                    </div>
                    <input type="hidden" name="camera_photo" id="historyCameraPhotoInput" value="">
                    <div id="historyPhotoPreview" class="mt-3 hidden">
                        <img id="historyPhotoPreviewImg" src="" alt="Preview" class="max-h-32 rounded-lg border border-gray-200 object-contain bg-gray-50">
                        <button type="button" id="historyPhotoClear" class="mt-2 text-xs text-red-600 hover:text-red-700 font-medium">Remove photo</button>
                    </div>
                    <div id="existingPhotoDisplay" class="mt-2 hidden">
                        <p class="text-xs text-gray-500">Current photo:</p>
                        <img id="existingPhotoImg" src="" class="max-h-20 rounded-lg border">
                    </div>
                </div>
                
                <div class="md:col-span-2 pt-2 flex gap-3">
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium text-sm py-2.5 px-6 rounded-lg shadow-md transition-colors flex items-center gap-2">
                        <i class="fa-solid fa-save"></i> <span id="submitBtnText">Save Snapshot</span>
                    </button>
                    <button type="button" id="cancelEditBtn" class="bg-gray-400 hover:bg-gray-500 text-white font-medium text-sm py-2.5 px-6 rounded-lg hidden transition-colors shadow-sm">
                        Cancel Edit
                    </button>
                </div>
            </div>
        </form>
    </div>

    <div>
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-[13px] font-bold text-gray-800 uppercase tracking-wider flex items-center gap-2">
                <i data-lucide="history" class="w-4 h-4 text-blue-500"></i> History Records
                <span class="text-gray-400 text-xs font-normal ml-2">(<?= count($history) ?> records)</span>
            </h3>
        </div>
        
        <?php if (empty($history)): ?>
            <div class="bg-white rounded-xl border border-gray-200 p-12 text-center">
                <i data-lucide="inbox" class="w-12 h-12 text-gray-300 mx-auto mb-3"></i>
                <p class="text-gray-500">No history records found for the selected filters.</p>
                <?php if ($filter_asset_id > 0): ?>
                    <p class="text-gray-400 text-sm mt-2">Click "Add History" to create the first snapshot for this asset.</p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                <?php foreach ($history as $record): ?>
                    <?php
                    $condition_class = 'condition-good';
                    $condition_text = htmlspecialchars($record['condition_note'] ?? 'Not specified');
                    if (stripos($condition_text, 'poor') !== false || stripos($condition_text, 'unserviceable') !== false) {
                        $condition_class = 'condition-poor';
                    } elseif (stripos($condition_text, 'fair') !== false || stripos($condition_text, 'minor') !== false) {
                        $condition_class = 'condition-fair';
                    }
                    
                    if ($record['period_type'] === 'Monthly') {
                        $period_class = 'period-monthly';
                    } elseif ($record['period_type'] === 'Quarterly') {
                        $period_class = 'period-quarterly';
                    } else {
                        $period_class = 'period-annual';
                    }
                    
                    $photo_url = !empty($record['photo_path']) ? bpis_url($record['photo_path']) : ($record['asset_photo'] ? bpis_url($record['asset_photo']) : '');
                    $full_photo_path = $_SERVER['DOCUMENT_ROOT'] . $photo_url;
                    ?>
                    <div class="history-card bg-white rounded-xl border border-gray-200 overflow-hidden shadow-sm hover:shadow-md transition-all">
                        <div class="h-40 bg-gray-100 flex items-center justify-center overflow-hidden">
                            <?php if ($photo_url && file_exists($full_photo_path)): ?>
                                <img src="<?= $photo_url ?>" alt="Asset photo" class="w-full h-full object-cover cursor-pointer" onclick="openPhotoModal('<?= $photo_url ?>', '<?= htmlspecialchars($record['description']) ?>')">
                            <?php else: ?>
                                <div class="flex flex-col items-center text-gray-400">
                                    <i data-lucide="image" class="w-10 h-10 mb-1"></i>
                                    <span class="text-xs">No photo</span>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="p-4">
                            <div class="mb-3">
                                <h4 class="font-semibold text-gray-800 text-sm truncate" title="<?= htmlspecialchars($record['description']) ?>">
                                    <?= htmlspecialchars($record['description']) ?>
                                </h4>
                                <p class="text-[10px] text-gray-400"><?= htmlspecialchars($record['property_number'] ?? 'No property #') ?></p>
                                <p class="text-[10px] text-blue-600 mt-1"><?= htmlspecialchars($record['category'] ?? '') ?></p>
                            </div>
                            
                            <div class="flex items-center justify-between mb-3">
                                <span class="period-tag <?= $period_class ?>"><?= $record['period_type'] ?></span>
                                <span class="text-xs font-medium text-gray-600"><?= htmlspecialchars($record['period_label']) ?></span>
                            </div>
                            
                            <div class="flex items-center gap-2 mb-2 text-sm">
                                <i data-lucide="package" class="w-3.5 h-3.5 text-gray-400"></i>
                                <span class="text-gray-600">Quantity:</span>
                                <span class="font-semibold text-gray-800"><?= (int)$record['quantity'] ?></span>
                            </div>
                            
                            <div class="flex items-center gap-2 mb-2">
                                <i data-lucide="clipboard-check" class="w-3.5 h-3.5 text-gray-400"></i>
                                <span class="text-gray-600 text-sm">Condition:</span>
                                <span class="condition-badge <?= $condition_class ?>"><?= $condition_text ?></span>
                            </div>
                            
                            <?php if (!empty($record['remarks'])): ?>
                                <div class="mt-2 pt-2 border-t border-gray-100">
                                    <div class="flex items-start gap-2">
                                        <i data-lucide="message-square" class="w-3.5 h-3.5 text-gray-400 mt-0.5"></i>
                                        <p class="text-xs text-gray-500 line-clamp-2"><?= nl2br(htmlspecialchars($record['remarks'])) ?></p>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <div class="mt-3 pt-2 flex justify-between items-center border-t border-gray-100">
                                <span class="text-[10px] text-gray-400">
                                    <i data-lucide="calendar" class="w-3 h-3 inline mr-1"></i>
                                    <?= date("M d, Y g:i A", strtotime($record['snapshot_at'])) ?>
                                </span>
                                <div>
                                    <button type="button" class="action-btn" onclick="editSnapshot(<?= $record['id'] ?>, '<?= addslashes($record['period_type']) ?>', '<?= addslashes($record['period_label']) ?>', <?= $record['quantity'] ?>, '<?= addslashes($record['condition_note'] ?? '') ?>', '<?= addslashes($record['remarks'] ?? '') ?>', '<?= addslashes($record['photo_path'] ?? '') ?>')">
                                        <i class="fa-solid fa-edit"></i> Edit
                                    </button>
                                    <button type="button" class="delete-btn" onclick="openDeleteHistoryModal(<?= (int) $record['id'] ?>)">
                                        <i class="fa-solid fa-trash"></i> Delete
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<div id="photoModal" class="hidden fixed inset-0 bg-black/80 z-[2000] flex items-center justify-center p-4" onclick="closePhotoModal()">
    <div class="relative max-w-4xl w-full" onclick="event.stopPropagation()">
        <button onclick="closePhotoModal()" class="absolute -top-10 right-0 text-white hover:text-gray-300 text-2xl">&times;</button>
        <img id="modalPhotoImg" src="" alt="" class="w-full rounded-lg max-h-[80vh] object-contain">
        <p id="modalPhotoCaption" class="text-center text-white mt-3 text-sm"></p>
    </div>
</div>

<div id="deleteHistoryOverlay" class="delete-history-overlay" role="alertdialog" aria-modal="true" aria-labelledby="deleteHistoryModalTitle">
    <div class="delete-history-modal">
        <header class="delete-history-modal-header">
            <div class="delete-history-modal-icon" aria-hidden="true"><i class="fa-solid fa-trash-can"></i></div>
            <h3 id="deleteHistoryModalTitle">Delete history record?</h3>
        </header>
        <div class="delete-history-modal-body">
            <p>Are you sure you want to delete this history record? This action cannot be undone.</p>
        </div>
        <footer class="delete-history-modal-footer">
            <button type="button" class="btn-delete-cancel" id="cancelDeleteHistoryBtn">Cancel</button>
            <button type="button" class="btn-delete-confirm" id="confirmDeleteHistoryBtn">
                <i class="fa-solid fa-trash-can" aria-hidden="true"></i> Delete
            </button>
        </footer>
    </div>
</div>

<div id="historyCameraModal" class="hidden fixed inset-0 bg-black/90 z-[2000] flex items-center justify-center flex-col">
    <div class="bg-black rounded-xl overflow-hidden max-w-md w-full">
        <video id="historyCameraVideo" autoplay playsinline class="w-full"></video>
        <canvas id="historyCameraCanvas" style="display: none;"></canvas>
    </div>
    <div class="flex gap-3 mt-5">
        <button id="captureHistoryPhotoBtn" class="bg-green-600 hover:bg-green-700 text-white font-medium py-2 px-6 rounded-full shadow-sm transition-colors">Capture</button>
        <button id="closeHistoryCameraBtn" class="bg-red-600 hover:bg-red-700 text-white font-medium py-2 px-6 rounded-full shadow-sm transition-colors">Close</button>
    </div>
</div>

<script>
let historyMediaStream = null;
let currentAssetId = <?= $filter_asset_id ?: 0 ?>;

function openPhotoModal(src, caption) {
    const modal = document.getElementById('photoModal');
    const img = document.getElementById('modalPhotoImg');
    const cap = document.getElementById('modalPhotoCaption');
    img.src = src;
    cap.textContent = caption;
    modal.classList.remove('hidden');
}

function closePhotoModal() {
    document.getElementById('photoModal').classList.add('hidden');
}

function showAddForm(assetId, assetName) {
    currentAssetId = assetId;
    document.getElementById('selectedAssetId').value = assetId;
    document.getElementById('selectedAssetDisplay').innerHTML = assetName;
    document.getElementById('formAction').value = 'add';
    document.getElementById('snapshotId').value = '0';
    document.getElementById('formTitle').innerHTML = 'Add New History Snapshot';
    document.getElementById('submitBtnText').innerHTML = 'Save Snapshot';
    document.getElementById('cancelEditBtn').classList.add('hidden');
    
    document.getElementById('periodType').value = 'Monthly';
    document.getElementById('periodLabel').value = '';
    document.getElementById('quantity').value = '';
    document.getElementById('conditionNote').value = '';
    document.getElementById('remarks').value = '';
    document.getElementById('snapshotPhoto').value = '';
    document.getElementById('historyCameraPhotoInput').value = '';
    document.getElementById('historyPhotoPreview').classList.add('hidden');
    document.getElementById('existingPhotoDisplay').classList.add('hidden');
    
    document.getElementById('snapshotForm').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function editSnapshot(id, periodType, periodLabel, quantity, condition, remarks, photoPath) {
    document.getElementById('formAction').value = 'edit';
    document.getElementById('snapshotId').value = id;
    document.getElementById('formTitle').innerHTML = 'Edit History Snapshot';
    document.getElementById('submitBtnText').innerHTML = 'Update Snapshot';
    document.getElementById('cancelEditBtn').classList.remove('hidden');
    
    document.getElementById('periodType').value = periodType;
    document.getElementById('periodLabel').value = periodLabel;
    document.getElementById('quantity').value = quantity;
    document.getElementById('conditionNote').value = condition;
    document.getElementById('remarks').value = remarks;
    
    if (photoPath && photoPath !== '') {
        const existingPhotoDisplay = document.getElementById('existingPhotoDisplay');
        const existingPhotoImg = document.getElementById('existingPhotoImg');
        existingPhotoImg.src = <?= json_encode(bpis_app_base_path() . '/') ?> + photoPath;
        existingPhotoDisplay.classList.remove('hidden');
    } else {
        document.getElementById('existingPhotoDisplay').classList.add('hidden');
    }
    
    document.getElementById('snapshotForm').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function cancelEdit() {
    document.getElementById('formAction').value = 'add';
    document.getElementById('snapshotId').value = '0';
    document.getElementById('formTitle').innerHTML = 'Add New History Snapshot';
    document.getElementById('submitBtnText').innerHTML = 'Save Snapshot';
    document.getElementById('cancelEditBtn').classList.add('hidden');
    document.getElementById('existingPhotoDisplay').classList.add('hidden');
    document.getElementById('periodLabel').value = '';
    document.getElementById('quantity').value = '';
    document.getElementById('conditionNote').value = '';
    document.getElementById('remarks').value = '';
    document.getElementById('historyPhotoPreview').classList.add('hidden');
    document.getElementById('historyCameraPhotoInput').value = '';
}

document.getElementById('cancelEditBtn').addEventListener('click', cancelEdit);

const historyCameraModal = document.getElementById('historyCameraModal');
const historyCameraVideo = document.getElementById('historyCameraVideo');
const historyCameraCanvas = document.getElementById('historyCameraCanvas');
const captureHistoryPhotoBtn = document.getElementById('captureHistoryPhotoBtn');
const closeHistoryCameraBtn = document.getElementById('closeHistoryCameraBtn');
const openHistoryCameraBtn = document.getElementById('openHistoryCameraBtn');
const historyCameraPhotoInput = document.getElementById('historyCameraPhotoInput');
const historyPhotoPreview = document.getElementById('historyPhotoPreview');
const historyPhotoPreviewImg = document.getElementById('historyPhotoPreviewImg');
const historyPhotoClear = document.getElementById('historyPhotoClear');

async function startHistoryCamera() {
    try {
        if (historyMediaStream) {
            historyMediaStream.getTracks().forEach(track => track.stop());
        }
        historyMediaStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: "environment" } });
        historyCameraVideo.srcObject = historyMediaStream;
    } catch (err) {
        console.error("Error accessing camera:", err);
        alert("Unable to access camera. Please make sure you have granted camera permissions.");
    }
}

function stopHistoryCamera() {
    if (historyMediaStream) {
        historyMediaStream.getTracks().forEach(track => track.stop());
        historyMediaStream = null;
    }
    historyCameraVideo.srcObject = null;
}

if (openHistoryCameraBtn) {
    openHistoryCameraBtn.addEventListener('click', () => {
        historyCameraModal.classList.remove('hidden');
        startHistoryCamera();
    });
}

if (captureHistoryPhotoBtn) {
    captureHistoryPhotoBtn.addEventListener('click', () => {
        const context = historyCameraCanvas.getContext('2d');
        historyCameraCanvas.width = historyCameraVideo.videoWidth;
        historyCameraCanvas.height = historyCameraVideo.videoHeight;
        context.drawImage(historyCameraVideo, 0, 0, historyCameraCanvas.width, historyCameraCanvas.height);
        
        const imageData = historyCameraCanvas.toDataURL('image/jpeg', 0.9);
        historyCameraPhotoInput.value = imageData;
        historyPhotoPreviewImg.src = imageData;
        historyPhotoPreview.classList.remove('hidden');
        document.getElementById('existingPhotoDisplay').classList.add('hidden');
        
        historyCameraModal.classList.add('hidden');
        stopHistoryCamera();
    });
}

if (closeHistoryCameraBtn) {
    closeHistoryCameraBtn.addEventListener('click', () => {
        historyCameraModal.classList.add('hidden');
        stopHistoryCamera();
    });
}

if (historyPhotoClear) {
    historyPhotoClear.addEventListener('click', () => {
        historyCameraPhotoInput.value = '';
        historyPhotoPreview.classList.add('hidden');
        historyPhotoPreviewImg.src = '';
    });
}

const snapshotPhotoInput = document.querySelector('input[name="snapshot_photo"]');
if (snapshotPhotoInput) {
    snapshotPhotoInput.addEventListener('change', function() {
        const file = this.files && this.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                historyPhotoPreviewImg.src = e.target.result;
                historyPhotoPreview.classList.remove('hidden');
                historyCameraPhotoInput.value = '';
                document.getElementById('existingPhotoDisplay').classList.add('hidden');
            };
            reader.readAsDataURL(file);
        }
    });
}

const categoryFilter = document.querySelector('select[name="filter_category"]');
const assetFilter = document.querySelector('select[name="filter_asset"]');
const periodFilter = document.querySelector('select[name="period_type"]');
const yearFilter = document.querySelector('select[name="year"]');

function applyFilters() {
    const params = new URLSearchParams();
    if (categoryFilter.value) params.set('filter_category', categoryFilter.value);
    if (assetFilter.value && assetFilter.value !== '0') params.set('filter_asset', assetFilter.value);
    if (periodFilter.value) params.set('period_type', periodFilter.value);
    if (yearFilter.value && yearFilter.value !== '0') params.set('year', yearFilter.value);
    
    window.location.href = '?' + params.toString();
}

if (categoryFilter) categoryFilter.addEventListener('change', applyFilters);
if (assetFilter) assetFilter.addEventListener('change', applyFilters);
if (periodFilter) periodFilter.addEventListener('change', applyFilters);
if (yearFilter) yearFilter.addEventListener('change', applyFilters);

let pendingDeleteId = 0;
const deleteHistoryOverlay = document.getElementById('deleteHistoryOverlay');
const confirmDeleteHistoryBtn = document.getElementById('confirmDeleteHistoryBtn');
const cancelDeleteHistoryBtn = document.getElementById('cancelDeleteHistoryBtn');

function openDeleteHistoryModal(id) {
    pendingDeleteId = id;
    deleteHistoryOverlay.classList.add('active');
}

function closeDeleteHistoryModal() {
    deleteHistoryOverlay.classList.remove('active');
    pendingDeleteId = 0;
}

confirmDeleteHistoryBtn.addEventListener('click', function() {
    if (!pendingDeleteId) {
        return;
    }
    const form = document.createElement('form');
    form.method = 'post';
    form.action = 'inventory_history.php';
    form.innerHTML = '<input type="hidden" name="delete">'
        + <?= json_encode(bpis_csrf_field(), JSON_UNESCAPED_SLASHES) ?>;
    form.elements.delete.value = pendingDeleteId;
    document.body.appendChild(form);
    form.submit();
});

cancelDeleteHistoryBtn.addEventListener('click', closeDeleteHistoryModal);

deleteHistoryOverlay.addEventListener('click', function(e) {
    if (e.target === deleteHistoryOverlay) closeDeleteHistoryModal();
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && deleteHistoryOverlay.classList.contains('active')) {
        closeDeleteHistoryModal();
    }
});

lucide.createIcons();
</script>

<?php include __DIR__ . '/../includes/treasurer_layout_end.php'; ?>