<?php
session_start();
include __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth_helpers.php';
require_once __DIR__ . '/../config/captain_page_helpers.php';
require_once __DIR__ . '/../config/table_date_filter_helpers.php';
require_once __DIR__ . '/../config/asset_units_helpers.php';
require_once __DIR__ . '/../config/asset_borrowable_helpers.php';
require_once __DIR__ . '/../config/asset_list_helpers.php';
require_once __DIR__ . '/../config/coa_labels.php';
require_once __DIR__ . '/../config/asset_edit_helpers.php';
require_once __DIR__ . '/../config/upload_helpers.php';
require_once __DIR__ . '/../config/schema_bootstrap.php';

bpis_require_login(['Barangay Captain']);

$bpis_u = bpis_captain_load_user($conn);
$user_fullname = $bpis_u['fullname'];
$user_role = $bpis_u['role'];
include __DIR__ . '/../includes/bpis_resolve_header_profile.php';

function bpis_get_acquisition_modes() {
    return [
        'Purchased' => [
            'label' => 'Purchased',
            'fields' => ['cheque_number', 'voucher_number', 'cash_amount', 'shop_name', 'purchaser_name']
        ],
        'Donated' => [
            'label' => 'Donated',
            'fields' => ['donor_name', 'donation_date']
        ],
        'Transferred' => [
            'label' => 'Transferred from other agency',
            'fields' => ['transfer_from_agency', 'transfer_date']
        ],
        'Constructed/Built' => [
            'label' => 'Constructed/Built',
            'fields' => ['contractor_name', 'contract_amount']
        ],
        'Repossessed' => [
            'label' => 'Repossessed',
            'fields' => ['previous_owner', 'repossession_date']
        ],
        'Seized/Forfeited' => [
            'label' => 'Seized/Forfeited',
            'fields' => ['seizure_authority', 'seizure_date']
        ],
        'Received as aid' => [
            'label' => 'Received as aid (Foreign/National)',
            'fields' => ['aid_agency', 'aid_type', 'aid_date']
        ],
        'Leased' => [
            'label' => 'Leased',
            'fields' => ['lessor_name', 'lease_period_start', 'lease_period_end']
        ],
        'Negotiated' => [
            'label' => 'Negotiated Sale',
            'fields' => ['seller_name', 'negotiation_date']
        ]
    ];
}

function bpis_get_acquisition_display($conn, $asset_row) {
    $asset_id = (int)($asset_row['id'] ?? 0);
    
    $acq_query = "SELECT * FROM asset_acquisition_details WHERE asset_id = $asset_id LIMIT 1";
    $acq_result = mysqli_query($conn, $acq_query);
    $acq_data = ($acq_result && mysqli_num_rows($acq_result) > 0) ? mysqli_fetch_assoc($acq_result) : [];
    
    if (empty($acq_data)) {
        return '<div class="text-xs text-gray-400">No acquisition details recorded</div>';
    }
    
    $mode = $acq_data['acquisition_mode'] ?? '';
    
    if (empty($mode)) {
        return '<div class="text-xs text-gray-400">No acquisition mode set</div>';
    }
    
    $modes = bpis_get_acquisition_modes();
    $mode_label = isset($modes[$mode]['label']) ? $modes[$mode]['label'] : $mode;
    
    $display_html = '<div class="text-xs font-semibold text-blue-600 mb-1">' . htmlspecialchars($mode_label) . '</div>';
    
    $mode_fields = isset($modes[$mode]['fields']) ? $modes[$mode]['fields'] : [];
    $detail_items = [];
    
    foreach ($mode_fields as $field) {
        $value = $acq_data[$field] ?? '';
        
        if (empty($value) || $value === 'N/A' || $value === '0000-00-00') {
            continue;
        }
        
        $label = ucfirst(str_replace('_', ' ', $field));
        
        if (in_array($field, ['cash_amount', 'contract_amount']) && is_numeric($value)) {
            $value = '₱' . number_format((float)$value, 2);
        } elseif (in_array($field, ['donation_date', 'transfer_date', 'repossession_date', 'seizure_date', 'negotiation_date', 'aid_date']) && $value !== '0000-00-00') {
            $value = date("M d, Y", strtotime($value));
        } elseif ($field === 'aid_type') {
            $aid_types = [
                'Foreign Aid' => 'Foreign',
                'National Aid' => 'National',
                'Local Aid' => 'Local',
                'Private Donation' => 'Private'
            ];
            $value = $aid_types[$value] ?? $value;
        }
        
        if ($field === 'lease_period_start' && !empty($acq_data['lease_period_end']) && $acq_data['lease_period_end'] !== '0000-00-00') {
            $start = date("M d, Y", strtotime($acq_data['lease_period_start']));
            $end = date("M d, Y", strtotime($acq_data['lease_period_end']));
            $detail_items[] = '<div class="text-xs text-gray-600">Period: ' . htmlspecialchars($start) . ' - ' . htmlspecialchars($end) . '</div>';
            continue;
        } elseif ($field === 'lease_period_end') {
            continue;
        }
        
        $detail_items[] = '<div class="text-xs text-gray-600">' . htmlspecialchars($label) . ': ' . htmlspecialchars($value) . '</div>';
    }
    
    if (!empty($detail_items)) {
        $display_html .= '<div class="space-y-0.5">' . implode('', $detail_items) . '</div>';
    } else {
        $display_html .= '<div class="text-xs text-gray-400">No additional details</div>';
    }
    
    return $display_html;
}

function bpis_get_acquisition_attachments($conn, $asset_row) {
    $asset_id = (int)($asset_row['id'] ?? 0);
    
    $acq_query = "SELECT * FROM asset_acquisition_details WHERE asset_id = $asset_id LIMIT 1";
    $acq_result = mysqli_query($conn, $acq_query);
    $acq_data = ($acq_result && mysqli_num_rows($acq_result) > 0) ? mysqli_fetch_assoc($acq_result) : [];
    
    if (empty($acq_data)) {
        return '<div class="text-xs text-gray-400 text-center py-2">No attachments</div>';
    }
    
    $attachment_fields = [
        'purchase_proof_path' => 'Purchase Proof',
        'service_invoice_path' => 'Service Invoice',
        'deed_of_donation_path' => 'Deed of Donation',
        'transfer_document_path' => 'Transfer Document',
        'project_contract_path' => 'Project Contract',
        'lease_contract_path' => 'Lease Contract',
        'court_order_path' => 'Court Order',
        'seizure_document_path' => 'Seizure Document',
        'sales_agreement_path' => 'Sales Agreement',
        'aid_document_path' => 'Aid Document'
    ];
    
    $attachments = [];
    $base_url = bpis_app_base_path() . '/';
    
    foreach ($attachment_fields as $field => $label) {
        $file_path = $acq_data[$field] ?? '';
        if (!empty($file_path)) {
            $clean_path = ltrim($file_path, '/');
            $full_path = __DIR__ . '/../' . $clean_path;
            if (!file_exists($full_path)) {
                $full_path = __DIR__ . '/../' . $clean_path;
            }
            
            if (file_exists($full_path)) {
                $file_ext = strtolower(pathinfo($clean_path, PATHINFO_EXTENSION));
                $is_image = in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp']);
                $web_path = $base_url . $clean_path;
                
                $attachments[] = [
                    'label' => $label,
                    'path' => $web_path,
                    'is_image' => $is_image,
                    'file_name' => basename($clean_path),
                    'file_ext' => $file_ext
                ];
            }
        }
    }
    
    if (empty($attachments)) {
        return '<div class="text-xs text-gray-400 text-center py-2">No attachments</div>';
    }
    
    $image_attachments = array_filter($attachments, function($a) { return $a['is_image']; });
    $image_count = count($image_attachments);
    
    if ($image_count === 0) {
        return '<div class="text-xs text-gray-400 text-center py-2">No image attachments</div>';
    }
    
    $image_paths = array_values($image_attachments);
    $image_paths_json = json_encode(array_column($image_paths, 'path'));
    $image_labels_json = json_encode(array_column($image_paths, 'label'));
    
    $first_image = $image_paths[0];
    $remaining_count = $image_count - 1;
    
    $html = '<div class="relative inline-block">';
    $html .= '<button type="button" class="block attachment-gallery-btn" 
                   data-images=\'' . htmlspecialchars($image_paths_json, ENT_QUOTES) . '\' 
                   data-labels=\'' . htmlspecialchars($image_labels_json, ENT_QUOTES) . '\' 
                   data-current="0">';
    $html .= '<img src="' . htmlspecialchars($first_image['path']) . '" 
                   alt="' . htmlspecialchars($first_image['label']) . '" 
                   class="w-32 h-32 object-cover rounded-lg border border-gray-200 hover:opacity-80 transition-opacity cursor-pointer shadow-sm" 
                   onerror="this.onerror=null; this.src=\'../images/image-placeholder.png\';">';
    $html .= '</button>';
    
    if ($remaining_count > 0) {
        $html .= '<div class="absolute inset-0 flex items-center justify-center pointer-events-none">';
        $html .= '<span class="bg-black bg-opacity-60 text-white text-lg font-bold px-3 py-1.5 rounded-full">+' . $remaining_count . '</span>';
        $html .= '</div>';
    }
    $html .= '</div>';
    
    return $html;
}

function bpis_format_asset_status_text($conn, $asset_row, $is_borrowable = true) {
    $asset_id = (int)$asset_row['id'];
    
    $unit_query = "SELECT unit_condition FROM asset_units WHERE asset_id = $asset_id";
    $unit_result = mysqli_query($conn, $unit_query);
    
    $serviceable_count = 0;
    
    if ($unit_result && mysqli_num_rows($unit_result) > 0) {
        while ($unit = mysqli_fetch_assoc($unit_result)) {
            $condition = strtoupper(trim($unit['unit_condition'] ?? 'SERVICEABLE'));
            if ($condition === 'SERVICEABLE') {
                $serviceable_count++;
            }
        }
    } else {
        $remarks = strtoupper(trim($asset_row['remarks'] ?? 'SERVICEABLE'));
        if ($remarks === 'SERVICEABLE') {
            $serviceable_count = isset($asset_row['quantity']) ? (int)$asset_row['quantity'] : (int)$asset_row['article'];
        }
    }
    
    return '<span class="text-green-600 font-medium">' . $serviceable_count . ' Serviceable</span>';
}

if (isset($conn)) {
    $sync_query = "SELECT id, article, quantity, unit_value FROM asset";
    $sync_result = mysqli_query($conn, $sync_query);
    
    if ($sync_result && mysqli_num_rows($sync_result) > 0) {
        while ($sync_row = mysqli_fetch_assoc($sync_result)) {
            $asset_id = $sync_row['id'];
            $qty_calc = isset($sync_row['quantity']) ? (float)$sync_row['quantity'] : (float)$sync_row['article'];
            $unit_val_calc = (float)$sync_row['unit_value'];
            
            $total_calculated = $qty_calc * $unit_val_calc;
            
            $update_sql = "UPDATE asset SET 
                           card_value = '$total_calculated', 
                           total_cost = '$total_calculated' 
                           WHERE id = '$asset_id'";
            mysqli_query($conn, $update_sql);
        }
    }
}

$selected_category = isset($_GET['category']) ? mysqli_real_escape_string($conn, $_GET['category']) : '';
$selected_cluster = isset($_GET['cluster']) ? mysqli_real_escape_string($conn, $_GET['cluster']) : '';

$assets = [];

$where = [];
if (!empty($selected_category)) {
    $where[] = "category = '$selected_category'";
}
if (!empty($selected_cluster) && bpis_column_exists($conn, 'asset', 'asset_cluster')) {
    $where[] = "asset_cluster = '$selected_cluster'";
}
$asset_query = 'SELECT * FROM asset' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY id DESC';

if (isset($conn)) {
    $asset_result = mysqli_query($conn, $asset_query);

    if ($asset_result && mysqli_num_rows($asset_result) > 0) {
        while ($row = mysqli_fetch_assoc($asset_result)) {
            $qty = isset($row['quantity']) ? (int)$row['quantity'] : (int)$row['article'];
            
            $db_status = isset($row['status']) && !empty($row['status']) ? $row['status'] : '';
            
            $is_borrowable_category = bpis_asset_category_is_borrowable($row['category'] ?? '');
            $status_text = bpis_format_asset_status_text($conn, $row, $is_borrowable_category);

            $subitems = bpis_load_asset_subitems($conn, $row);

            $unit_val = is_numeric($row['unit_value']) ? number_format((float)$row['unit_value'], 2) : "0.00";
          
            $card_val_display = is_numeric($row['card_value']) ? number_format((float)$row['card_value'], 2) : "0.00";
            
            $remarks_display = bpis_format_asset_remarks_text($conn, $row);

            $modalTitleDesc = !empty($row['description']) ? $row['description'] : $row['article'];
            
            $acq_detail_query = "SELECT * FROM asset_acquisition_details WHERE asset_id = " . (int)$row['id'] . " LIMIT 1";
            $acq_detail_result = mysqli_query($conn, $acq_detail_query);
            $acq_detail_data = ($acq_detail_result && mysqli_num_rows($acq_detail_result) > 0) ? mysqli_fetch_assoc($acq_detail_result) : [];
            
            $acquisition_mode = !empty($acq_detail_data['acquisition_mode']) ? $acq_detail_data['acquisition_mode'] : '';
            
            $assets[] = [
                "id" => (int)$row['id'],
                "title" => htmlspecialchars($modalTitleDesc . ' (' . $qty . ' ' . $row['unit_measure'] . ')'),
                "article" => $row['article'],
                "description" => $row['description'],
                "category" => $row['category'],
                "accountable_officer" => $row['accountable_officer'] ?? '',
                "prop_num" => $row['property_number'],
                "uom" => $row['unit_measure'],
                "quantity" => $qty,
                "date_acquired" => $row['date_acquired'],
                "unit_value" => $unit_val,
                "card_value" => $card_val_display,
                "remarks" => $remarks_display,
                "remarks_label" => bpis_asset_remarks_display_label($remarks_display),
                "status" => $status_text,
                "subitems" => $subitems,
                "location" => (string)($row['location'] ?? ''),
                "asset_cluster" => (string)($row['asset_cluster'] ?? ''),
                "audit_remarks" => (string)($row['audit_remarks'] ?? ''),
                "acquisition_display" => bpis_get_acquisition_display($conn, $row),
                "acquisition_attachments" => bpis_get_acquisition_attachments($conn, $row),
            ];
        }
    }
}

$asset_photo_map = bpis_get_asset_photo_map($conn, array_column($assets, 'id'));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" href="<?= bpis_app_base_path() ?>/images/logo.png">
    <script>var BPIS_BASE_PREFIX = <?= json_encode(bpis_app_base_path() . "/") ?>;</script>
    <link rel="stylesheet" href="../css/table_date_filter.css">
    <link rel="stylesheet" href="../css/asset_inventory_image.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="../css/profile_dropdown.css">
    <link rel="stylesheet" href="../css/sidebar_nav_transition.css">
    <script src="../js/sidebar_nav_transition.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php include __DIR__ . '/../includes/bpis_app_meta.php'; ?>
    <title>Inventory Logs</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
    * { 
        margin: 0; 
        padding: 0; 
        box-sizing: border-box; 
        font-family: 'Segoe UI', Roboto, Arial, sans-serif; 
    }
    body { 
        background-color: #F6F8FC; 
        display: flex; 
        height: 100vh; 
        color: #333; 
        overflow: hidden; 
    }
    .sidebar { 
        width: 230px; 
        background: linear-gradient(180deg, #0f3f69 0%, #174c7d 45%, #1d5f94 100%); 
        color: white; 
        display: flex; 
        flex-direction: column; 
        justify-content: space-between; 
        padding: 25px 15px; 
        position: fixed; 
        height: 100vh; 
        z-index: 10;
        border-right: 1px solid rgba(255, 255, 255, 0.18);
        box-shadow: 6px 0 24px rgba(12, 34, 56, 0.22); 
    }
    .sidebar-top { 
        text-align: center; 
    }
    .logo-container { 
        margin-bottom: 15px; 
        display: flex; 
        justify-content: center; 
    }
    .logo-img { 
        width: 70px; 
        height: 70px; 
        border-radius: 50%; 
        object-fit: contain; 
    }
    .sidebar h2 {
        font-size: 16px; 
        font-weight: 600; 
        line-height: 1.2; 
        margin-bottom: 30px; 
        letter-spacing: 0.5px; 
    }
    .sidebar-nav { 
        display: flex; 
        flex-direction: column; 
        gap: 12px; 
    }
    .nav-item { 
        display: block; 
        color: white; 
        text-decoration: none; 
        padding: 11px 14px; 
        border-radius: 12px; 
        font-size: 13px; 
        font-weight: 600; 
        background: rgba(255, 255, 255, 0.08); 
        border: 1px solid rgba(255, 255, 255, 0.14);
        transition: all 0.2s ease; 
    }
    .nav-item:hover, .nav-item.active { 
        background: linear-gradient(90deg, #3b82f6, #2563eb); 
        border-color: rgba(255, 255, 255, 0.35);
        transform: translateX(2px);
    }
    .nav-item.active {
        border-color: rgba(255, 255, 255, 0.4);
        box-shadow: 0 8px 18px rgba(37, 99, 235, 0.32);
        font-weight: 700;
    }
    .nav-item i { width: 18px; margin-right: 10px; text-align: center; }
    .sidebar-bottom { 
        font-size: 11px; 
        text-align: center; 
        opacity: 0.88; 
        color: rgba(255, 255, 255, 0.9);
        line-height: 1.4; 
    }
    .main-content { 
        flex: 1; 
        margin-left: 230px; 
        padding: 25px 35px; 
        display: flex; 
        flex-direction: column; 
        height: 100vh; 
        min-height: 0;
        min-width: 0; 
        overflow-y: auto;
        overflow-x: hidden;
    }
    .header { 
        display: flex; 
        justify-content: space-between; 
        align-items: center; 
        margin-bottom: 25px; 
    }
    .header h1 { 
        font-size: 22px; 
        font-weight: 700; 
        color: #111; 
    }
    .header p { 
        font-size: 13px; 
        color: #666; 
    } 
    .header-icons { 
        display: flex; 
        align-items: center; 
        gap: 20px; 
        position: relative; 
    }
    .icon-box { 
        width: 32px; 
        height: 32px; 
        cursor: pointer; 
        object-fit: contain; 
    }
    .icon-circle { 
        width: 42px; 
        height: 42px; 
        cursor: pointer; 
        object-fit: cover; 
        border-radius: 50%; 
        border: 2px solid transparent; 
        transition: border 0.2s; 
    }
    .icon-circle:hover { 
        border-color: #3B82F6; 
    }
    
    .profile-dropdown { 
        display: none; 
        position: absolute; 
        top: 55px; 
        right: 0; 
        width: 220px; 
        background-color: #174C7D; 
        border-radius: 12px; 
        box-shadow: 0 10px 25px rgba(0,0,0,0.15); 
        z-index: 1000; 
        overflow: hidden; 
        color: white; 
        padding-top: 20px; 
        animation: fadeIn 0.2s ease-out; 
    }
    @keyframes fadeIn { 
        from { opacity: 0; transform: translateY(-10px); } 
        to { opacity: 1; transform: translateY(0); } 
    }
    .profile-dropdown.active { 
        display: block; 
    }
    .profile-user-info { 
        text-align: center; 
        padding-bottom: 15px; 
        border-bottom: 1px solid rgba(255, 255, 255, 0.1); 
    }
    .profile-user-info img { 
        width: 60px; 
        height: 60px; 
        border-radius: 50%; 
        background-color: white; 
        padding: 2px; 
        margin-bottom: 10px; 
        object-fit: cover; 
        display: inline-block; 
    }
    .profile-user-info h4 { 
        font-size: 14px; 
        font-weight: 600; 
        margin-bottom: 2px; 
    }
    .profile-links a { 
        display: block; 
        padding: 12px 20px; 
        color: white; 
        text-decoration: none; 
        font-size: 13px; 
        text-align: center; 
        transition: background 0.2s; 
    }
    .profile-links a:hover { 
        background-color: #1e3a8a; 
    }
    .profile-links .sign-out { 
        color: #f9e8ea; 
        font-weight: 600; 
    }
    .profile-links .sign-out:hover { 
        background-color: #991b1b; 
        color: white; 
    }

    .custom-scrollbar::-webkit-scrollbar {
        width: 6px;
        height: 6px;
    }
    .custom-scrollbar::-webkit-scrollbar-track {
        background: #f1f1f1;
    }
    .custom-scrollbar::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 10px;
    }
    .custom-scrollbar::-webkit-scrollbar-thumb:hover {
        background: #94a3b8;
    }

    #printArea {
        display: none;
    }
    
    @media print {
            body * {
                visibility: hidden;
            }
            #printArea, #printArea * {
                visibility: visible;
            }
            #printArea {
                display: block !important;
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                background: white;
                margin: 0;
                padding: 0.2in;
                z-index: 99999;
                box-sizing: border-box;
            }
            .qr-tag-container {
                display: grid;
                grid-template-columns: repeat(5, 1fr);
                gap: 0.08in;
                width: 100%;
                page-break-inside: avoid;
            }
            .qr-tag-card {
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: flex-start;
                width: 100%;
                border: 1px solid #000;
                border-radius: 3px;
                padding: 0.05in;
                background: white;
                page-break-inside: avoid;
                break-inside: avoid;
                box-sizing: border-box;
            }
            .qr-tag-card .qr-code-wrapper {
                text-align: center;
                margin-bottom: 0.05in;
                width: 100%;
            }
            .qr-tag-card img.qr-code-image {
                width: 0.9in !important;
                height: 0.9in !important;
                display: block;
                margin: 0 auto;
                object-fit: contain;
            }
            .qr-tag-card .tag-id {
                font-size: 8px;
                font-weight: bold;
                color: #1d4ed8;
                margin: 2px 0;
                line-height: 1.2;
                text-align: left;
                width: 100%;
                word-break: break-word;
            }
            .qr-tag-card .tag-line {
                font-size: 9px;
                color: #333;
                margin: 1px 0;
                line-height: 1.2;
                text-align: left;
                width: 100%;
                word-break: break-word;
            }
            .qr-tag-card .tag-line strong {
                font-weight: bold;
            }
            * {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
        }
    .qr-tag-container {
        display: grid;
        grid-template-columns: repeat(6, 1fr);
        gap: 10px;
        width: 100%;
    }
    
    .qr-tag-card {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: flex-start;
        width: 100%;
        border: 1px solid #ccc;
        border-radius: 5px;
        padding: 8px;
        background: white;
        box-sizing: border-box;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    
    .qr-tag-card .qr-code-wrapper {
        text-align: center;
        margin-bottom: 8px;
        width: 100%;
    }
    
    .qr-tag-card img.qr-code-image {
        width: 100px;
        height: 100px;
        display: block;
        margin: 0 auto;
        object-fit: contain;
    }
    
    .qr-tag-card .tag-id {
        font-size: 10px;
        font-weight: bold;
        color: #1d4ed8;
        margin: 4px 0;
        text-align: left;
        width: 100%;
        word-break: break-word;
    }
    
    .qr-tag-card .tag-line {
        font-size: 10px;
        color: #333;
        margin: 2px 0;
        text-align: left;
        width: 100%;
        word-break: break-word;
    }
    
    .qr-tag-card .tag-line strong {
        font-weight: bold;
    }
    .acquisition-mode-cell {
        min-width: 220px;
        max-width: 320px;
        white-space: normal !important;
        word-break: break-word;
        vertical-align: top;
    }
    
    .acquisition-mode-cell div {
        white-space: normal;
    }
    
    .acquisition-attachments-cell {
        min-width: 180px;
        max-width: 280px;
        white-space: normal !important;
        word-break: break-word;
        vertical-align: top;
    }
    
    .acquisition-attachments-cell .flex {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }
    
    .acquisition-attachments-cell .relative {
        position: relative;
    }
    
    .acquisition-attachments-cell .absolute {
        position: absolute;
    }
    
    .acquisition-attachments-cell img {
        width: 128px;
        height: 128px;
        object-fit: cover;
        border-radius: 8px;
        border: 1px solid #e5e7eb;
        transition: opacity 0.2s;
        cursor: pointer;
    }
    
    .acquisition-attachments-cell img:hover {
        opacity: 0.8;
    }
    
    .acquisition-attachments-cell button {
        background: none;
        border: none;
        padding: 0;
        cursor: pointer;
    }
    
    .acquisition-attachments-cell a {
        text-decoration: none;
        display: flex;
        flex-direction: column;
        align-items: center;
        padding: 8px;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        width: 64px;
        transition: background-color 0.2s;
    }
    
    .acquisition-attachments-cell a:hover {
        background-color: #f9fafb;
    }
    
    .acquisition-attachments-cell i {
        font-size: 28px;
    }

    .attachment-gallery-modal {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.95);
        z-index: 9999;
        display: flex;
        align-items: center;
        justify-content: center;
        visibility: hidden;
        opacity: 0;
        transition: visibility 0.3s, opacity 0.3s;
    }

    .attachment-gallery-modal.active {
        visibility: visible;
        opacity: 1;
    }

    .attachment-gallery-content {
        position: relative;
        max-width: 90%;
        max-height: 90%;
    }

    .attachment-gallery-image {
        max-width: 90vw;
        max-height: 85vh;
        object-fit: contain;
        border-radius: 8px;
        display: block;
        margin: 0 auto;
    }

    .attachment-gallery-close {
        position: fixed;
        top: 20px;
        right: 20px;
        background: rgba(0, 0, 0, 0.6);
        border: none;
        color: white;
        font-size: 32px;
        cursor: pointer;
        padding: 12px 16px;
        border-radius: 50%;
        transition: background 0.2s;
        z-index: 10001;
        width: 56px;
        height: 56px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .attachment-gallery-close:hover {
        background: rgba(255, 255, 255, 0.3);
        transform: scale(1.05);
    }

    .attachment-gallery-prev,
    .attachment-gallery-next {
        position: fixed;
        top: 50%;
        transform: translateY(-50%);
        background: rgba(0, 0, 0, 0.5);
        border: none;
        color: white;
        font-size: 48px;
        cursor: pointer;
        padding: 20px 16px;
        border-radius: 8px;
        transition: background 0.2s;
        z-index: 10000;
    }

    .attachment-gallery-prev:hover,
    .attachment-gallery-next:hover {
        background: rgba(0, 0, 0, 0.8);
    }

    .attachment-gallery-prev {
        left: 20px;
    }

    .attachment-gallery-next {
        right: 20px;
    }

    .attachment-gallery-download {
        position: fixed;
        top: 20px;
        right: 90px;
        background: rgba(0, 0, 0, 0.6);
        border: none;
        color: white;
        font-size: 20px;
        cursor: pointer;
        padding: 12px;
        border-radius: 50%;
        transition: background 0.2s;
        width: 56px;
        height: 56px;
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 10001;
    }

    .attachment-gallery-download:hover {
        background: rgba(255, 255, 255, 0.3);
        transform: scale(1.05);
    }

    .attachment-gallery-counter {
        position: fixed;
        bottom: 20px;
        left: 50%;
        transform: translateX(-50%);
        color: white;
        font-size: 14px;
        background: rgba(0, 0, 0, 0.6);
        padding: 6px 12px;
        border-radius: 20px;
        z-index: 10001;
    }

    .attachment-gallery-caption {
        position: fixed;
        bottom: 60px;
        left: 50%;
        transform: translateX(-50%);
        color: white;
        font-size: 12px;
        background: rgba(0, 0, 0, 0.6);
        padding: 6px 12px;
        border-radius: 20px;
        white-space: nowrap;
        max-width: 80%;
        overflow: hidden;
        text-overflow: ellipsis;
        z-index: 10001;
    }

    @media (max-width: 768px) {
        .attachment-gallery-prev,
        .attachment-gallery-next {
            font-size: 32px;
            padding: 12px 10px;
        }
        
        .attachment-gallery-prev {
            left: 10px;
        }
        
        .attachment-gallery-next {
            right: 10px;
        }

        .attachment-gallery-close {
            top: 10px;
            right: 10px;
            width: 40px;
            height: 40px;
            font-size: 24px;
        }

        .attachment-gallery-download {
            top: 10px;
            right: 60px;
            width: 40px;
            height: 40px;
            font-size: 16px;
        }
    }
    
    #qrModal {
        z-index: 2500;
    }
    
    .asset-btn {
        border: none;
        cursor: pointer;
    }
    .captain-inventory-card {
        flex: 1 1 auto;
        min-height: 420px;
    }
    .captain-inventory-scroll {
        flex: 1 1 auto;
        min-height: 0;
        overscroll-behavior: contain;
    }
    .captain-inventory-table thead th {
        vertical-align: middle;
        white-space: normal;
        line-height: 1.25;
        min-width: 112px;
    }
    .captain-inventory-table thead th.compact-col {
        min-width: 82px;
    }
    .captain-inventory-table thead th.medium-col {
        min-width: 132px;
    }
    .captain-inventory-table thead th.wide-col {
        min-width: 168px;
    }
    .captain-inventory-table tbody td {
        vertical-align: middle;
    }
    .captain-inventory-toolbar {
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
    }
    .captain-inventory-toolbar .bpis-date-filter-label {
        flex: 0 0 auto;
    }
    .captain-inventory-export {
        margin-left: auto;
    }
    table { 
        width: 100%; 
        border-collapse: collapse; 
        font-size: 13px; 
    }
    .table-responsive-container {
        max-height: 500px; 
        overflow-y: auto;
        overflow-x: auto; 
        border: 1px solid #eee;
        border-radius: 8px;
    }
    .table-responsive-container thead th {
        position: sticky;
        top: 0;
        background-color: #fff;
        z-index: 2;
        border-bottom: 2px solid #eee;
    }
    th { 
        text-align: center; 
        color: #888; 
        padding: 12px 10px; 
        font-weight: 600; 
        text-transform: uppercase; 
        font-size: 11px; 
        border-bottom: 1px solid #eee; 
    }
    td { 
        padding: 15px 10px; 
        border-bottom: 1px solid #f9f9f9; 
        color: #444; 
        text-align: center; 
    }

    @media (max-width: 1024px) {
        .sidebar {
            width: 200px;
            padding: 20px 12px;
        }
        .sidebar h2 {
            font-size: 14px;
            margin-bottom: 20px;
        }
        .nav-item {
            font-size: 12px;
            padding: 10px 12px;
        }
        .main-content {
            margin-left: 200px;
            padding: 20px 25px;
        }
        .captain-inventory-card {
            min-height: 380px;
        }
        .header h1 {
            font-size: 20px;
        }
        .logo-img {
            width: 60px;
            height: 60px;
        }
        .table-responsive-container {
            max-height: 400px;
        }
        th {
            padding: 10px 8px;
            font-size: 10px;
        }
        td {
            padding: 12px 8px;
            font-size: 12px;
        }
        .acquisition-mode-cell {
            min-width: 180px;
            max-width: 260px;
        }
        .acquisition-attachments-cell {
            min-width: 150px;
            max-width: 220px;
        }
        .acquisition-attachments-cell img {
            width: 100px;
            height: 100px;
        }
    }

    @media (max-width: 768px) {
        html,
        body {
            height: auto !important;
            min-height: 100vh !important;
            overflow-y: auto !important;
        }
        .sidebar {
            width: 60px;
            padding: 15px 8px;
        }
        .sidebar h2 {
            display: none;
        }
        .sidebar-bottom {
            display: none;
        }
        .logo-img {
            width: 40px;
            height: 40px;
        }
        .nav-item {
            font-size: 11px;
            padding: 8px 6px;
            text-align: center;
            border-radius: 8px;
        }
        .nav-item i {
            margin-right: 0;
            width: 20px;
            font-size: 16px;
        }
        .nav-item span {
            display: none;
        }
        .nav-item:hover {
            transform: none;
        }
        .main-content {
            margin-left: 60px;
            padding: 15px 15px;
            height: auto !important;
            min-height: 100vh;
            min-height: 100dvh;
            overflow: visible !important;
        }
        .captain-inventory-card {
            min-height: 0;
            overflow: visible !important;
        }
        .captain-inventory-scroll {
            min-height: 0;
            max-height: calc(100dvh - 280px) !important;
            overflow-x: auto !important;
            overflow-y: auto !important;
            -webkit-overflow-scrolling: touch;
        }
        .captain-inventory-toolbar {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            width: 100%;
        }
        .captain-inventory-toolbar .bpis-date-filter-label,
        .captain-inventory-toolbar .bpis-date-clear-btn,
        .captain-inventory-toolbar .captain-inventory-export {
            grid-column: 1 / -1;
        }
        .captain-inventory-toolbar label {
            width: 100%;
            justify-content: space-between;
        }
        .captain-inventory-toolbar input[type="date"] {
            width: min(100%, 160px);
        }
        .captain-inventory-export {
            margin-left: 0 !important;
            width: 100%;
            justify-content: center;
            min-height: 40px;
        }
        .header {
            flex-wrap: wrap;
            gap: 10px;
        }
        .header h1 {
            font-size: 18px;
        }
        .header p {
            font-size: 12px;
        }
        .header-icons {
            gap: 12px;
        }
        .icon-circle {
            width: 34px;
            height: 34px;
        }
        .icon-box {
            width: 26px;
            height: 26px;
        }
        .profile-dropdown {
            width: 180px;
            right: -10px;
        }
        .profile-user-info img {
            width: 48px;
            height: 48px;
        }
        .profile-user-info h4 {
            font-size: 13px;
        }
        .profile-links a {
            font-size: 12px;
            padding: 10px 16px;
        }
        .table-responsive-container {
            max-height: 350px;
        }
        th {
            padding: 8px 6px;
            font-size: 9px;
        }
        td {
            padding: 10px 6px;
            font-size: 11px;
        }
        .acquisition-mode-cell {
            min-width: 140px;
            max-width: 200px;
        }
        .acquisition-attachments-cell {
            min-width: 120px;
            max-width: 180px;
        }
        .acquisition-attachments-cell img {
            width: 80px;
            height: 80px;
        }
        .acquisition-attachments-cell a {
            width: 50px;
            padding: 6px;
        }
        .acquisition-attachments-cell i {
            font-size: 22px;
        }

        body.qr-modal-open {
            overflow: hidden !important;
        }
        body.qr-modal-open .mobile-nav-toggle {
            display: none !important;
        }
        #qrModal {
            z-index: 2500 !important;
            padding: max(10px, env(safe-area-inset-top)) max(10px, env(safe-area-inset-right)) max(10px, env(safe-area-inset-bottom)) max(10px, env(safe-area-inset-left)) !important;
            align-items: center !important;
            justify-content: center !important;
            min-height: 100dvh !important;
            overflow: hidden !important;
        }
        #qrModal > div {
            width: min(94vw, 420px) !important;
            max-width: 94vw !important;
            max-height: calc(100vh - 20px) !important;
            max-height: calc(100dvh - 20px) !important;
            border-radius: 12px !important;
            overflow: hidden !important;
        }
        #qrModal .border-b {
            padding: 12px 14px !important;
            align-items: flex-start !important;
            gap: 10px !important;
        }
        #qrModalTitle {
            font-size: 15px !important;
            line-height: 1.25 !important;
            overflow-wrap: anywhere !important;
        }
        #qrModal p.text-xs {
            font-size: 11px !important;
            line-height: 1.35 !important;
        }
        #closeQrModal {
            width: 34px !important;
            height: 34px !important;
            min-width: 34px !important;
            flex: 0 0 34px !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
        }
        #qrModalBody {
            padding: 12px !important;
            min-height: 0 !important;
            overflow-y: auto !important;
            -webkit-overflow-scrolling: touch !important;
        }
        #qrModalBody .qr-modal-item {
            display: grid !important;
            grid-template-columns: 72px minmax(0, 1fr) auto !important;
            align-items: start !important;
            gap: 10px !important;
            padding: 12px !important;
            margin-bottom: 0 !important;
            border-radius: 10px !important;
        }
        #qrModalBody .tag-unit-photo-wrap,
        #qrModalBody .tag-unit-photo {
            width: 56px !important;
            height: 56px !important;
        }
        #qrModalBody .tag-unit-photo-wrap {
            grid-column: 1 !important;
            grid-row: 1 !important;
        }
        #qrModalBody .qr-image-container {
            grid-column: 1 !important;
            grid-row: 1 !important;
        }
        #qrModalBody .qr-modal-item.has-unit-photo .qr-image-container {
            grid-row: 2 !important;
        }
        #qrModalBody .qr-image-container img {
            width: 68px !important;
            height: 68px !important;
        }
        #qrModalBody .item-details {
            min-width: 0 !important;
            grid-column: 2 !important;
            grid-row: 1 / span 2 !important;
        }
        #qrModalBody .property-id {
            font-size: 11px !important;
            line-height: 1.25 !important;
            overflow-wrap: anywhere !important;
        }
        #qrModalBody .description-text {
            font-size: 12px !important;
            line-height: 1.3 !important;
            margin-bottom: 5px !important;
        }
        #qrModalBody .spec-line {
            font-size: 11px !important;
            line-height: 1.3 !important;
            overflow-wrap: anywhere !important;
        }
        #qrModalBody .qr-print-one {
            grid-column: 3 !important;
            grid-row: 1 / span 2 !important;
            align-self: center !important;
            padding: 8px !important;
        }
    }

    @media (max-width: 480px) {
        .sidebar {
            width: 50px;
            padding: 10px 4px;
        }
        .logo-img {
            width: 30px;
            height: 30px;
        }
        .nav-item {
            font-size: 10px;
            padding: 6px 4px;
            border-radius: 6px;
        }
        .nav-item i {
            font-size: 14px;
            width: 18px;
        }
        .main-content {
            margin-left: 50px;
            padding: 10px 10px;
        }
        .captain-inventory-card {
            min-height: 0;
        }
        .captain-inventory-scroll {
            min-height: 0;
            max-height: calc(100dvh - 250px) !important;
        }
        .captain-inventory-toolbar {
            grid-template-columns: 1fr;
            gap: 8px;
        }
        .captain-inventory-toolbar label {
            gap: 8px;
        }
        .captain-inventory-toolbar input[type="date"] {
            width: min(58vw, 170px);
        }
        .header {
            flex-direction: column;
            align-items: flex-start;
            gap: 8px;
        }
        .header h1 {
            font-size: 16px;
        }
        .header p {
            font-size: 11px;
        }
        .header-icons {
            align-self: flex-end;
            gap: 8px;
        }
        .icon-circle {
            width: 28px;
            height: 28px;
        }
        .icon-box {
            width: 22px;
            height: 22px;
        }
        .profile-dropdown {
            width: 160px;
            right: -5px;
            top: 48px;
        }
        .profile-user-info img {
            width: 40px;
            height: 40px;
        }
        .profile-user-info h4 {
            font-size: 12px;
        }
        .profile-links a {
            font-size: 11px;
            padding: 8px 14px;
        }
        .table-responsive-container {
            max-height: 300px;
        }
        th {
            padding: 6px 4px;
            font-size: 8px;
        }
        td {
            padding: 8px 4px;
            font-size: 10px;
        }
        .acquisition-mode-cell {
            min-width: 100px;
            max-width: 160px;
        }
        .acquisition-attachments-cell {
            min-width: 80px;
            max-width: 140px;
        }
        .acquisition-attachments-cell img {
            width: 60px;
            height: 60px;
        }
        .acquisition-attachments-cell a {
            width: 40px;
            padding: 4px;
        }
        .acquisition-attachments-cell i {
            font-size: 18px;
        }
        .attachment-gallery-prev,
        .attachment-gallery-next {
            font-size: 24px;
            padding: 8px 6px;
        }
        .attachment-gallery-close {
            width: 32px;
            height: 32px;
            font-size: 18px;
            padding: 8px;
        }
        .attachment-gallery-download {
            width: 32px;
            height: 32px;
            font-size: 14px;
            padding: 8px;
            right: 45px;
        }
    }

    @media (max-width: 360px) {
        .sidebar {
            width: 40px;
            padding: 8px 2px;
        }
        .logo-img {
            width: 24px;
            height: 24px;
        }
        .nav-item {
            font-size: 9px;
            padding: 4px 2px;
            border-radius: 4px;
        }
        .nav-item i {
            font-size: 12px;
            width: 16px;
        }
        .main-content {
            margin-left: 40px;
            padding: 8px 6px;
        }
        .captain-inventory-card {
            min-height: 0;
        }
        .captain-inventory-scroll {
            min-height: 0;
            max-height: calc(100dvh - 230px) !important;
        }
        .captain-inventory-toolbar input[type="date"] {
            width: min(56vw, 145px);
        }
        .captain-inventory-export {
            font-size: 11px !important;
            padding: 7px 10px !important;
        }
        .header h1 {
            font-size: 14px;
        }
        .header p {
            font-size: 10px;
        }
        .header-icons {
            gap: 6px;
        }
        .icon-circle {
            width: 24px;
            height: 24px;
        }
        .icon-box {
            width: 18px;
            height: 18px;
        }
        .profile-dropdown {
            width: 140px;
            right: 0;
            top: 42px;
            padding-top: 14px;
        }
        .profile-user-info img {
            width: 34px;
            height: 34px;
        }
        .profile-user-info h4 {
            font-size: 11px;
        }
        .profile-links a {
            font-size: 10px;
            padding: 6px 12px;
        }
        .table-responsive-container {
            max-height: 250px;
        }
        th {
            padding: 4px 3px;
            font-size: 7px;
        }
        td {
            padding: 6px 3px;
            font-size: 9px;
        }
        .acquisition-mode-cell {
            min-width: 80px;
            max-width: 120px;
        }
        .acquisition-attachments-cell {
            min-width: 60px;
            max-width: 100px;
        }
        .acquisition-attachments-cell img {
            width: 48px;
            height: 48px;
        }
        .acquisition-attachments-cell a {
            width: 32px;
            padding: 3px;
        }
        .acquisition-attachments-cell i {
            font-size: 14px;
        }
        .attachment-gallery-prev,
        .attachment-gallery-next {
            font-size: 18px;
            padding: 6px 4px;
        }
        .attachment-gallery-close {
            width: 28px;
            height: 28px;
            font-size: 14px;
            padding: 6px;
        }
        .attachment-gallery-download {
            width: 28px;
            height: 28px;
            font-size: 12px;
            padding: 6px;
            right: 38px;
        }
    }
</style>
    <link rel="stylesheet" href="../css/captain_mobile_sidebar.css">
    <link rel="stylesheet" href="../css/logout_modal.css">

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { theme: { extend: { colors: { brand: { 600: '#2563eb', 700: '#1d4ed8' } } } } };
    </script>
    <script src="../tailwind_blue_theme.js"></script>
</head>
<body>
    <div id="printArea"></div>

    <div id="attachmentGalleryModal" class="attachment-gallery-modal">
        <div class="attachment-gallery-content">
            <img id="galleryImage" class="attachment-gallery-image" src="" alt="">
        </div>
        <button class="attachment-gallery-close" id="galleryCloseBtn">&times;</button>
        <button class="attachment-gallery-download" id="galleryDownloadBtn" title="Download image">
            <i class="fa-solid fa-download"></i>
        </button>
        <button class="attachment-gallery-prev" id="galleryPrevBtn">&#10094;</button>
        <button class="attachment-gallery-next" id="galleryNextBtn">&#10095;</button>
        <div class="attachment-gallery-counter" id="galleryCounter"></div>
        <div class="attachment-gallery-caption" id="galleryCaption"></div>
    </div>

<?php include __DIR__ . '/../includes/bpis_logout_modal.php'; ?>
    <div class="sidebar">
        <div class="sidebar-top">
            <button type="button" id="mobileNavClose" class="mobile-nav-close" aria-label="Close navigation">
                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </button>
            <div class="logo-container"><img src="../images/logo.png" alt="Logo" class="logo-img"></div>
            <h2>Barangay Property Inventory System</h2>
            <?php
            $bpis_captain_nav_active = 'inventory';
            include __DIR__ . '/../includes/captain_sidebar.php';
            ?>
        </div>
        <div class="sidebar-bottom">Brgy. Bolocboloc, Sibulan<br>Negros Oriental, Philippines, 6201<br>@2026</div>
    </div>

    <div class="sidebar-overlay" id="sidebarOverlay" aria-hidden="true"></div>

    <div class="main-content">
        <div class="header shrink-0">
            <button type="button" id="mobileNavToggle" class="mobile-nav-toggle" aria-label="Toggle navigation" aria-expanded="false">
                <i class="fa-solid fa-bars" aria-hidden="true"></i>
            </button>
            <div>
                <h1>Inventory Logs</h1>
                <p>Detailed List of all barangay assets and equipment.</p>
            </div>
            <?php include __DIR__ . '/../includes/admin_header_profile_icons.php'; ?>
        </div>

        <div class="flex flex-col md:flex-row justify-between items-center gap-4 mb-6 shrink-0">
            <div class="relative w-full sm:w-48">
                <select id="clusterFilter" class="bg-white border border-gray-300 text-gray-600 text-sm rounded-lg pl-3 pr-8 py-2 w-full outline-none shadow-sm cursor-pointer appearance-none mb-2 sm:mb-0">
                    <option value="" <?= $selected_cluster === '' ? 'selected' : '' ?>>All clusters</option>
                    <option value="Fixed Assets" <?= $selected_cluster === 'Fixed Assets' ? 'selected' : '' ?>>Fixed Assets</option>
                    <option value="Movable Assets" <?= $selected_cluster === 'Movable Assets' ? 'selected' : '' ?>>Movable Assets</option>
                </select>
            </div>
            <div class="relative w-full sm:w-48">
                <select id="categoryFilter" class="bg-white border border-gray-300 text-gray-600 text-sm rounded-lg pl-3 pr-8 py-2 w-full outline-none shadow-sm cursor-pointer appearance-none">
                    <option value="" <?= $selected_category == '' ? 'selected' : '' ?>>All Categories</option>
                    <option value="Land & Improvements" <?= $selected_category == 'Land & Improvements' ? 'selected' : '' ?>>Land & Improvements</option>
                    <option value="Buildings & Structures" <?= $selected_category == 'Buildings & Structures' ? 'selected' : '' ?>>Buildings & Structures</option>
                    <option value="Machinery" <?= $selected_category == 'Machinery' ? 'selected' : '' ?>>Machinery</option>
                    <option value="Equipment" <?= $selected_category == 'Equipment' ? 'selected' : '' ?>>Equipment</option>
                    <option value="IT Equipment" <?= $selected_category == 'IT Equipment' ? 'selected' : '' ?>>IT Equipment & Software</option>
                    <option value="Office Equipment" <?= $selected_category == 'Office Equipment' ? 'selected' : '' ?>>Office Equipment</option>
                    <option value="Furniture & Fixtures" <?= $selected_category == 'Furniture & Fixtures' ? 'selected' : '' ?>>Furniture & Fixtures</option>
                    <option value="Motor Vehicles / Delivery" <?= $selected_category == 'Motor Vehicles / Delivery' ? 'selected' : '' ?>>Motor Vehicles / Delivery Truck</option>
                    <option value="Tools" <?= $selected_category == 'Tools' ? 'selected' : '' ?>>Tools</option>
                </select>
                <div class="absolute inset-y-0 right-0 flex items-center pr-2 pointer-events-none text-gray-400">
                    <i data-lucide="chevron-down" class="w-4 h-4"></i>
                </div>
            </div>
            <div class="flex flex-col sm:flex-row gap-3 w-full md:w-auto">
                <div class="relative w-full sm:w-[300px] lg:w-[350px]">
                    <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                        <i data-lucide="search" class="w-4 h-4 text-gray-400"></i>
                    </div>
                    <input type="text" id="assetSearch" class="bg-white border border-gray-300 text-sm rounded-lg block w-full pl-10 px-3 py-2.5 outline-none shadow-sm" placeholder="Search assets...">
                </div>
            </div>
        </div>

        <div class="captain-inventory-card bg-white border border-gray-300 rounded-xl flex flex-col min-h-0 w-full overflow-hidden shadow-sm" data-bpis-date-filter-scope>
            <div class="px-4 pt-3 pb-2 border-b border-gray-100 shrink-0 bg-white">
                <div class="bpis-date-filter-bar captain-inventory-toolbar" style="margin-bottom:0;">
                    <span class="bpis-date-filter-label">Filter by date acquired</span>
                    <label>From <input type="date" data-bpis-date-from aria-label="Filter from date"></label>
                    <label>To <input type="date" data-bpis-date-to aria-label="Filter to date"></label>
                    <button type="button" class="bpis-date-clear-btn" data-bpis-date-clear>Clear dates</button>
                    <a href="export_summary_report.php?type=inventory&amp;month=<?= htmlspecialchars(date('Y-m'), ENT_QUOTES, 'UTF-8') ?>"
                       target="_blank" rel="noopener"
                       id="exportSummaryBtn"
                       class="captain-inventory-export ml-auto bg-[#3B82F6] hover:bg-blue-600 text-white font-medium text-sm py-2 px-4 rounded-lg shadow-sm transition-colors flex items-center gap-2 whitespace-nowrap no-underline">
                        <i data-lucide="file-text" class="w-4 h-4"></i>
                        Export Monthly Summary
                    </a>
                </div>
            </div>
            <div class="captain-inventory-scroll overflow-x-auto overflow-y-auto custom-scrollbar">
                <table class="captain-inventory-table w-full min-w-max text-left border-collapse whitespace-nowrap" id="assetTable">
                    <thead class="sticky top-0 z-10 shadow-sm">
                        <tr class="bg-white">
                            <th class="compact-col py-4 px-4 font-semibold text-gray-500 text-[13px] uppercase border-b border-gray-200 text-center">QR Code</th>
                            <th class="compact-col py-4 px-4 font-semibold text-gray-500 text-[13px] uppercase border-b border-gray-200 text-center">Image</th>
                            <th class="medium-col py-4 px-5 font-semibold text-gray-500 text-[13px] uppercase border-b border-gray-200">Article</th>
                            <th class="wide-col py-4 px-5 font-semibold text-gray-500 text-[13px] uppercase border-b border-gray-200">Description</th>
                            <th class="medium-col py-4 px-5 font-semibold text-gray-500 text-[13px] uppercase border-b border-gray-200">Category</th>
                            <th class="medium-col py-4 px-5 font-semibold text-gray-500 text-[13px] uppercase border-b border-gray-200">Cluster</th>
                            <th class="medium-col py-4 px-5 font-semibold text-gray-500 text-[13px] uppercase border-b border-gray-200">Location</th>
                            <th class="wide-col py-4 px-5 font-semibold text-gray-500 text-[13px] uppercase border-b border-gray-200">Property Number</th>
                            <th class="compact-col py-4 px-4 font-semibold text-gray-500 text-[13px] uppercase border-b border-gray-200">UOM</th>
                            <th class="medium-col py-4 px-5 font-semibold text-gray-500 text-[13px] uppercase border-b border-gray-200">Date Acquired</th>
                            <th class="medium-col py-4 px-5 font-semibold text-gray-500 text-[13px] uppercase border-b border-gray-200">Unit Cost/Value</th>
                            <th class="wide-col py-4 px-5 font-semibold text-gray-500 text-[13px] uppercase border-b border-gray-200">Acquisition Mode</th>
                            <th class="medium-col py-4 px-5 font-semibold text-gray-500 text-[13px] uppercase border-b border-gray-200">Attachments</th>
                            <th class="wide-col py-4 px-5 font-semibold text-gray-500 text-[13px] uppercase border-b border-gray-200">Accountable Officer</th>
                            <th class="medium-col py-4 px-5 font-semibold text-gray-500 text-[13px] uppercase border-b border-gray-200 text-center">Remarks</th>
                            <th class="compact-col py-4 px-4 font-semibold text-gray-500 text-[13px] uppercase border-b border-gray-200 text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody class="text-[12px]">
                        <?php if(empty($assets)): ?>
                            <tr id="noResultsRow">
                                <td colspan="16" class="py-10 text-center text-gray-500 font-medium">No assets found in this category.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($assets as $asset): ?>
                            <tr class="asset-row border-b border-gray-50 last:border-none hover:bg-gray-50 transition-colors"
                                <?= bpis_row_date_attr(['date_acquired' => $asset['date_acquired'] ?? ''], ['date_acquired']) ?>
                                data-asset-id="<?= (int)$asset['id'] ?>"
                                data-article="<?= htmlspecialchars($asset['article'], ENT_QUOTES) ?>"
                                data-quantity="<?= (int)$asset['quantity'] ?>"
                                data-description="<?= htmlspecialchars($asset['description'], ENT_QUOTES) ?>"
                                data-category="<?= htmlspecialchars($asset['category'], ENT_QUOTES) ?>"
                                data-prop-num="<?= htmlspecialchars($asset['prop_num'], ENT_QUOTES) ?>"
                                data-uom="<?= htmlspecialchars($asset['uom'], ENT_QUOTES) ?>"
                                data-date-acquired="<?= htmlspecialchars($asset['date_acquired'], ENT_QUOTES) ?>"
                                data-unit-value="<?= (float)str_replace(',', '', $asset['unit_value']) ?>"
                                data-remarks="<?= htmlspecialchars($asset['remarks'], ENT_QUOTES) ?>"
                                data-location="<?= htmlspecialchars($asset['location'] ?? '', ENT_QUOTES) ?>"
                                data-asset-cluster="<?= htmlspecialchars($asset['asset_cluster'] ?? '', ENT_QUOTES) ?>"
                                data-audit-remarks="<?= htmlspecialchars($asset['audit_remarks'] ?? '', ENT_QUOTES) ?>"
                                data-photo-url="<?= htmlspecialchars(bpis_asset_photo_url($conn, (int) $asset['id'], $asset_photo_map), ENT_QUOTES) ?>">
                                
                                <td class="py-4 px-6 text-center">
                                    <button class="qr-trigger focus:outline-none relative" 
                                            data-title="<?= htmlspecialchars($asset['title']) ?>" 
                                            data-subitems='<?= htmlspecialchars(json_encode($asset['subitems']), ENT_QUOTES, 'UTF-8') ?>'>
                                        <i data-lucide="qr-code" class="w-5 h-5 text-blue-600 mx-auto cursor-pointer hover:text-blue-800 transition-colors"></i>
                                        <?php if(count($asset['subitems']) > 1): ?>
                                            <span class="absolute -top-2 -right-2 bg-red-500 text-white text-[9px] font-bold px-1.5 py-0.5 rounded-full"><?= count($asset['subitems']) ?></span>
                                        <?php endif; ?>
                                    </button>
                                </td>
                                <td class="py-4 px-6 bpis-asset-photo-cell">
                                    <?= bpis_asset_photo_img($conn, (int) $asset['id'], (string) $asset['description'], 'bpis-asset-thumb', $asset_photo_map) ?>
                                </td>
                                <td class="py-4 px-6 font-bold text-black"><?= htmlspecialchars($asset['article']) ?></td>
                                <td class="py-4 px-6 text-gray-800 asset-desc"><?= htmlspecialchars($asset['description']) ?></td>
                                <td class="py-4 px-6 text-gray-800"><?= htmlspecialchars($asset['category']) ?></td>
                                <td class="py-4 px-6 text-gray-800 text-xs"><?= htmlspecialchars($asset['asset_cluster'] ?? '—') ?></td>
                                <td class="py-4 px-6 text-gray-800 text-xs max-w-[140px] truncate" title="<?= htmlspecialchars($asset['location'] ?? '') ?>"><?= htmlspecialchars($asset['location'] ?? '—') ?></td>
                                <td class="py-4 px-6 text-gray-800 font-medium"><?= htmlspecialchars($asset['prop_num']) ?></td>
                                <td class="py-4 px-6 text-gray-800"><?= htmlspecialchars($asset['uom']) ?></td>
                                <td class="py-4 px-6 text-gray-800">
                                    <?= htmlspecialchars(date("F j, Y", strtotime($asset['date_acquired']))) ?>
                                </td>
                                <td class="py-4 px-6 text-gray-800">₱ <?= htmlspecialchars($asset['unit_value']) ?></td>
                                <td class="py-4 px-6 text-gray-800 text-xs acquisition-mode-cell">
                                    <?= $asset['acquisition_display'] ?>
                                </td>
                                <td class="py-4 px-6 text-gray-800 text-xs acquisition-attachments-cell">
                                    <?= $asset['acquisition_attachments'] ?>
                                </td>
                                <td class="py-4 px-6 text-gray-800 text-center">
                                    <?= htmlspecialchars($asset['accountable_officer'] ?? '—') ?>
                                </td>
                                <td class="py-4 px-6 text-center font-medium text-xs leading-snug <?= bpis_asset_remarks_cell_class($asset['remarks']) ?>">
                                    <?= htmlspecialchars($asset['remarks_label'] ?? $asset['remarks']) ?>
                                </td>
                                <td class="py-4 px-6 text-center font-medium">
                                    <?= $asset['status'] ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <tr id="searchNoResults" class="hidden">
                                <td colspan="16" class="py-10 text-center text-gray-500 font-medium text-[12px]">No assets match your search criteria.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div id="qrModal" class="hidden fixed inset-0 bg-black bg-opacity-40 z-[2500] flex items-center justify-center backdrop-blur-sm transition-opacity" style="z-index: 2500;">
        <div class="bg-white rounded-xl shadow-2xl w-[580px] max-w-[94%] flex flex-col max-h-[85vh]">
            <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-gray-50 rounded-t-xl shrink-0">
                <div>
                    <h3 class="font-bold text-lg text-gray-800" id="qrModalTitle">Asset QR Codes</h3>
                    <p class="text-xs text-gray-500 mt-0.5">Scan or print the QR codes below. Click a unit photo to preview it larger.</p>
                </div>
                <button id="closeQrModal" class="text-gray-400 hover:text-red-500 bg-white p-1.5 rounded-md shadow-sm border border-gray-200 transition-colors">
                    <i data-lucide="x" class="w-4 h-4"></i>
                </button>
            </div>
            <div class="px-6 py-4 overflow-y-auto custom-scrollbar flex-grow" id="qrModalBody"></div>
            <div class="px-6 py-4 border-t border-gray-100 bg-gray-50 rounded-b-xl flex justify-end shrink-0">
                <button id="printAllBtn" class="bg-[#3B82F6] hover:bg-blue-600 text-white text-sm font-medium py-2 px-5 rounded-lg shadow transition-colors flex items-center gap-2">
                    <i data-lucide="printer" class="w-4 h-4"></i> Print All Tags
                </button>
            </div>
        </div>
    </div>

    <div id="bpisImagePreviewOverlay" class="bpis-image-preview-overlay hidden" role="dialog" aria-modal="true" aria-labelledby="bpisImagePreviewCaption">
        <button type="button" id="bpisImagePreviewClose" class="bpis-image-preview-close" aria-label="Close preview">&times;</button>
        <figure class="bpis-image-preview-frame">
            <img id="bpisImagePreviewImg" src="" alt="">
            <figcaption id="bpisImagePreviewCaption" class="bpis-image-preview-caption"></figcaption>
        </figure>
    </div>

    <script src="../js/bpis_image_preview.js"></script>
    <script src="../js/table_date_filter.js"></script>

    <script>
    function bpis_get_acquisition_modes() {
        return {
            'Purchased': { label: 'Purchased', fields: ['cheque_number', 'voucher_number', 'cash_amount', 'shop_name', 'purchaser_name'] },
            'Donated': { label: 'Donated', fields: ['donor_name', 'donation_date'] },
            'Transferred': { label: 'Transferred from other agency', fields: ['transfer_from_agency', 'transfer_date'] },
            'Constructed/Built': { label: 'Constructed/Built', fields: ['contractor_name', 'contract_amount'] },
            'Repossessed': { label: 'Repossessed', fields: ['previous_owner', 'repossession_date'] },
            'Seized/Forfeited': { label: 'Seized/Forfeited', fields: ['seizure_authority', 'seizure_date'] },
            'Received as aid': { label: 'Received as aid (Foreign/National)', fields: ['aid_agency', 'aid_type', 'aid_date'] },
            'Leased': { label: 'Leased', fields: ['lessor_name', 'lease_period_start', 'lease_period_end'] },
            'Negotiated': { label: 'Negotiated Sale', fields: ['seller_name', 'negotiation_date'] }
        };
    }
    </script>

    <script>
    const assetSearch = document.getElementById('assetSearch');
    const searchNoResults = document.getElementById('searchNoResults');

    assetSearch.addEventListener('input', function() {
        const filter = this.value.toLowerCase();
        let hasVisibleRow = false;

        document.querySelectorAll('.asset-row').forEach(row => {
            const description = row.querySelector('.asset-desc').textContent.toLowerCase();
            const textOk = description.includes(filter);
            row.dataset.bpisSearchHidden = textOk ? '' : '1';
            if (typeof window.bpisSyncRowDisplay === 'function') {
                window.bpisSyncRowDisplay(row);
            } else {
                row.classList.toggle('hidden', !textOk);
            }
            if (!row.classList.contains('hidden') && row.style.display !== 'none') {
                hasVisibleRow = true;
            }
        });

        if (searchNoResults) {
            if (!hasVisibleRow && filter !== "") {
                searchNoResults.classList.remove('hidden');
            } else {
                searchNoResults.classList.add('hidden');
            }
        }
    });

    const categoryFilter = document.getElementById('categoryFilter');
    function applyInventoryFilters() {
        const params = new URLSearchParams();
        if (categoryFilter && categoryFilter.value) params.set('category', categoryFilter.value);
        const clusterEl = document.getElementById('clusterFilter');
        if (clusterEl && clusterEl.value) params.set('cluster', clusterEl.value);
        const qs = params.toString();
        window.location.href = 'inventory_logs.php' + (qs ? '?' + qs : '');
    }
    if (categoryFilter) categoryFilter.addEventListener('change', applyInventoryFilters);
    const clusterFilter = document.getElementById('clusterFilter');
    if (clusterFilter) clusterFilter.addEventListener('change', applyInventoryFilters);

    const profileTrigger = document.getElementById('profileTrigger');
    const profileMenu = document.getElementById('profileMenu');
    const logoutBtn = document.getElementById('logoutBtn');
    const logoutOverlay = document.getElementById('logoutOverlay');
    const confirmLogoutAction = document.getElementById('confirmLogoutAction');
    const cancelLogoutAction = document.getElementById('cancelLogoutAction');

    profileTrigger.addEventListener('click', function(e) {
        e.stopPropagation();
        profileMenu.classList.toggle('active');
    });

    document.addEventListener('click', function(e) {
        if (!profileMenu.contains(e.target) && e.target !== profileTrigger) {
            profileMenu.classList.remove('active');
        }
    });

    const handleLogoutRequest = (e) => {
        e.preventDefault();
        logoutOverlay.style.display = 'flex';
        profileMenu.classList.remove('active');
    };

    logoutBtn.addEventListener('click', handleLogoutRequest);

    cancelLogoutAction.addEventListener('click', function() {
        logoutOverlay.style.display = 'none';
    });

    confirmLogoutAction.addEventListener('click', function() {
        window.location.href = "../logout.php";
    });

    logoutOverlay.addEventListener('click', function(e) {
        if (e.target === logoutOverlay) {
            logoutOverlay.style.display = 'none';
        }
    });

    function fixAttachmentImageUrls() {
        document.querySelectorAll('.acquisition-attachments-cell img').forEach(img => {
            let src = img.getAttribute('src');
            if (src && !src.startsWith(BPIS_BASE_PREFIX) && !src.startsWith('http') && !src.startsWith('data:')) {
                src = src.replace(/^\/+/, '');
                if (src.startsWith('BPIS/')) {
                    src = '/' + src;
                } else {
                    src = BPIS_BASE_PREFIX + src;
                }
                img.setAttribute('src', src);
            }
        });
        
        document.querySelectorAll('.acquisition-attachments-cell button[data-bpis-image-preview]').forEach(btn => {
            let previewSrc = btn.getAttribute('data-preview-src');
            if (previewSrc && !previewSrc.startsWith(BPIS_BASE_PREFIX) && !previewSrc.startsWith('http') && !previewSrc.startsWith('data:')) {
                previewSrc = previewSrc.replace(/^\/+/, '');
                if (previewSrc.startsWith('BPIS/')) {
                    previewSrc = '/' + previewSrc;
                } else {
                    previewSrc = BPIS_BASE_PREFIX + previewSrc;
                }
                btn.setAttribute('data-preview-src', previewSrc);
            }
        });
        
        document.querySelectorAll('.acquisition-attachments-cell a').forEach(link => {
            let href = link.getAttribute('href');
            if (href && !href.startsWith(BPIS_BASE_PREFIX) && !href.startsWith('http') && !href.startsWith('#')) {
                href = href.replace(/^\/+/, '');
                if (href.startsWith('BPIS/')) {
                    href = '/' + href;
                } else {
                    href = BPIS_BASE_PREFIX + href;
                }
                link.setAttribute('href', href);
            }
        });
        
        document.querySelectorAll('.bpis-asset-photo-cell img').forEach(img => {
            let src = img.getAttribute('src');
            if (src && !src.startsWith(BPIS_BASE_PREFIX) && !src.startsWith('http') && !src.startsWith('data:') && src !== '../images/image-placeholder.png') {
                src = src.replace(/^\/+/, '');
                if (src.startsWith('BPIS/')) {
                    src = '/' + src;
                } else if (!src.startsWith('images/')) {
                    src = BPIS_BASE_PREFIX + src;
                }
                img.setAttribute('src', src);
            }
        });
    }

    function setHorizontalLayout() {
        document.querySelectorAll('.acquisition-attachments-cell').forEach(cell => {
            const mainContainer = cell.querySelector('.acquisition-attachments-cell > div');
            if (mainContainer) {
                mainContainer.classList.add('flex', 'flex-col', 'gap-2');
            }
            
            const flexContainers = cell.querySelectorAll('.acquisition-attachments-cell .flex');
            
            flexContainers.forEach(container => {
                container.classList.remove('flex-col');
                container.classList.add('flex', 'flex-row', 'items-center', 'justify-start', 'gap-2', 'flex-wrap');
                container.style.flexDirection = 'row';
                container.style.alignItems = 'center';
                container.style.justifyContent = 'flex-start';
                container.style.display = 'flex';
            });
            
            const imageWrappers = cell.querySelectorAll('.acquisition-attachments-cell .relative');
            if (imageWrappers.length > 0 && flexContainers.length === 0) {
                const wrapper = document.createElement('div');
                wrapper.className = 'flex flex-row items-center justify-start gap-2 flex-wrap';
                wrapper.style.flexDirection = 'row';
                wrapper.style.alignItems = 'center';
                wrapper.style.justifyContent = 'flex-start';
                wrapper.style.display = 'flex';
                
                const parent = imageWrappers[0].parentNode;
                while (imageWrappers.length > 0) {
                    wrapper.appendChild(imageWrappers[0]);
                }
                parent.appendChild(wrapper);
            }
            
            cell.querySelectorAll('.acquisition-attachments-cell .relative, .acquisition-attachments-cell .flex-shrink-0').forEach(imgContainer => {
                imgContainer.classList.add('flex-shrink-0');
            });
        });
    }

    function initAttachmentGallery() {
        let galleryImages = [];
        let galleryLabels = [];
        let currentIndex = 0;
        
        const modal = document.getElementById('attachmentGalleryModal');
        const galleryImg = document.getElementById('galleryImage');
        const prevBtn = document.getElementById('galleryPrevBtn');
        const nextBtn = document.getElementById('galleryNextBtn');
        const closeBtn = document.getElementById('galleryCloseBtn');
        const downloadBtn = document.getElementById('galleryDownloadBtn');
        const counter = document.getElementById('galleryCounter');
        const caption = document.getElementById('galleryCaption');
        
        function openGallery(images, labels, index) {
            galleryImages = images;
            galleryLabels = labels;
            currentIndex = index;
            updateImage();
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
        
        function updateImage() {
            if (galleryImages.length > 0 && galleryImages[currentIndex]) {
                galleryImg.src = galleryImages[currentIndex];
                galleryImg.alt = galleryLabels[currentIndex] || 'Attachment ' + (currentIndex + 1);
                counter.textContent = (currentIndex + 1) + ' of ' + galleryImages.length;
                caption.textContent = galleryLabels[currentIndex] || '';
            }
        }
        
        function nextImage() {
            if (currentIndex < galleryImages.length - 1) {
                currentIndex++;
                updateImage();
            }
        }
        
        function prevImage() {
            if (currentIndex > 0) {
                currentIndex--;
                updateImage();
            }
        }
        
        function closeGallery() {
            modal.classList.remove('active');
            document.body.style.overflow = '';
            galleryImages = [];
            galleryLabels = [];
            currentIndex = 0;
        }
        
        function downloadImage() {
            if (galleryImages.length > 0 && galleryImages[currentIndex]) {
                const url = galleryImages[currentIndex];
                const filename = galleryLabels[currentIndex] ? galleryLabels[currentIndex].replace(/[^a-z0-9]/gi, '_').toLowerCase() + '.jpg' : 'attachment.jpg';
                
                fetch(url)
                    .then(response => response.blob())
                    .then(blob => {
                        const link = document.createElement('a');
                        const objectUrl = URL.createObjectURL(blob);
                        link.href = objectUrl;
                        link.download = filename;
                        document.body.appendChild(link);
                        link.click();
                        document.body.removeChild(link);
                        URL.revokeObjectURL(objectUrl);
                    })
                    .catch(err => {
                        window.open(url, '_blank');
                    });
            }
        }
        
        if (prevBtn) prevBtn.addEventListener('click', prevImage);
        if (nextBtn) nextBtn.addEventListener('click', nextImage);
        if (closeBtn) closeBtn.addEventListener('click', closeGallery);
        if (downloadBtn) downloadBtn.addEventListener('click', downloadImage);
        
        document.addEventListener('keydown', function(e) {
            if (modal && modal.classList.contains('active')) {
                if (e.key === 'Escape') {
                    closeGallery();
                } else if (e.key === 'ArrowLeft') {
                    prevImage();
                } else if (e.key === 'ArrowRight') {
                    nextImage();
                }
            }
        });
        
        if (modal) {
            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    closeGallery();
                }
            });
        }
        
        document.querySelectorAll('.attachment-gallery-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                let images = [];
                let labels = [];
                
                try {
                    const imagesAttr = this.getAttribute('data-images');
                    const labelsAttr = this.getAttribute('data-labels');
                    
                    if (imagesAttr) {
                        images = JSON.parse(imagesAttr);
                    }
                    if (labelsAttr) {
                        labels = JSON.parse(labelsAttr);
                    }
                } catch (err) {
                    console.error('Error parsing gallery data:', err);
                }
                
                if (images.length > 0) {
                    const currentIdx = parseInt(this.getAttribute('data-current')) || 0;
                    openGallery(images, labels, currentIdx);
                }
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            fixAttachmentImageUrls();
            setTimeout(setHorizontalLayout, 100);
            initAttachmentGallery();
        });
    } else {
        fixAttachmentImageUrls();
        setTimeout(setHorizontalLayout, 100);
        initAttachmentGallery();
    }

    const observer = new MutationObserver(function(mutations) {
        let needsFix = false;
        mutations.forEach(function(mutation) {
            if (mutation.addedNodes.length) {
                needsFix = true;
            }
        });
        if (needsFix) {
            setTimeout(function() {
                fixAttachmentImageUrls();
                setHorizontalLayout();
                initAttachmentGallery();
            }, 150);
        }
    });
    observer.observe(document.body, { childList: true, subtree: true });

    const qrTriggers = document.querySelectorAll('.qr-trigger');
    const qrModal = document.getElementById('qrModal');
    const qrModalBody = document.getElementById('qrModalBody');
    const qrModalTitle = document.getElementById('qrModalTitle');
    const printArea = document.getElementById('printArea');

    let currentSubitems = [];

    function escHtml(str) {
        return String(str ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function formatPropertyId(id) {
        let formatted = String(id || '').trim();
        formatted = formatted.replace(/\s+/g, '-');
        return formatted;
    }

    function itemBrandModel(item) {
        let brand = String(item.brand || '').trim();
        let model = String(item.model || '').trim();
        if (!brand && !model) {
            const payload = String(item.qr_data || item.code || '');
            const parts = payload.split('|');
            if (parts.length >= 2) brand = String(parts[1] || '').trim();
            if (parts.length >= 3) model = String(parts[2] || '').trim();
        }
        return { brand: brand, model: model };
    }

    function tagUnitPhotoHtml(item, imgClass, previewable) {
        let url = String(item.photo_url || '').trim();
        if (!url) {
            return '';
        }
        if (url && !url.startsWith(BPIS_BASE_PREFIX) && !url.startsWith('http')) {
            url = url.replace(/^\/+/, '');
            if (url.startsWith('BPIS/')) {
                url = '/' + url;
            } else {
                url = BPIS_BASE_PREFIX + url;
            }
        }
        const cls = imgClass || 'tag-unit-photo w-12 h-12 object-cover rounded border border-gray-200 bg-white';
        const caption = String(item.code || item.name || 'Unit photo').trim();
        if (previewable === false) {
            return `<img src="${escHtml(url)}" alt="${escHtml(caption)}" class="${cls} tag-unit-photo-print">`;
        }
        return `<button type="button" class="tag-unit-photo-wrap shrink-0" title="Click to preview"
            data-bpis-image-preview data-preview-src="${escHtml(url)}" data-preview-caption="${escHtml(caption)}">
            <img src="${escHtml(url)}" alt="${escHtml(caption)}" class="${cls}">
        </button>`;
    }

    qrTriggers.forEach(trigger => {
        trigger.addEventListener('click', () => {
            qrModalTitle.textContent = trigger.getAttribute('data-title');
            const subitems = JSON.parse(trigger.getAttribute('data-subitems'));
            currentSubitems = subitems;

            let htmlContent = '<div class="flex flex-col gap-3">';
            subitems.forEach((item, index) => {
                const qrPayload = item.qr_data || item.code;
                const qrUrl = `https://api.qrserver.com/v1/create-qr-code/?size=120x120&data=${encodeURIComponent(qrPayload)}`;
                const displayName = escHtml(item.description || item.name || '');
                const tagCode = escHtml(formatPropertyId(item.code));
                const meta = itemBrandModel(item);
                const hasUnitPhoto = !!(item.photo_url && String(item.photo_url).trim());
                
                htmlContent += `
                    <div class="qr-modal-item${hasUnitPhoto ? ' has-unit-photo' : ''}" style="display:flex;align-items:flex-start;gap:16px;padding:16px;border:1px solid #e5e7eb;border-radius:12px;margin-bottom:12px;background:white;">
                        ${tagUnitPhotoHtml(item)}
                        <div class="qr-image-container" style="flex-shrink:0;">
                            <img src="${qrUrl}" alt="QR ${tagCode}" style="width:80px;height:80px;object-fit:contain;border:1px solid #e5e7eb;border-radius:8px;padding:4px;background:white;">
                        </div>
                        <div class="item-details" style="flex:1;text-align:left;">
                            <div class="property-id" style="font-family:monospace;font-size:13px;font-weight:700;color:#1d4ed8;margin-bottom:6px;">ID: ${tagCode}</div>
                            <div class="description-text" style="font-size:14px;color:#1f2937;margin-bottom:8px;"><span style="font-weight:bold;">Description:</span> ${displayName}</div>
                            ${meta.brand ? `<div class="spec-line" style="font-size:12px;color:#4b5563;margin:4px 0;"><span style="font-weight:bold;">Brand:</span> ${escHtml(meta.brand)}</div>` : ''}
                            ${meta.model ? `<div class="spec-line" style="font-size:12px;color:#4b5563;margin:4px 0;"><span style="font-weight:bold;">Model:</span> ${escHtml(meta.model)}</div>` : ''}
                        </div>
                        <button type="button" class="print-btn qr-print-one" data-index="${index}" title="Print this QR" style="flex-shrink:0;background:#f3f4f6;border:none;padding:8px 12px;border-radius:8px;cursor:pointer;color:#4b5563;transition:all 0.2s;">
                            <i data-lucide="printer" class="w-5 h-5"></i>
                        </button>
                    </div>`;
            });
            qrModalBody.innerHTML = htmlContent + '</div>';
            qrModalBody.querySelectorAll('.qr-print-one').forEach(btn => {
                btn.addEventListener('click', function () {
                    const idx = parseInt(this.getAttribute('data-index'), 10);
                    const item = currentSubitems[idx];
                    if (item) {
                        printSingleTag(item);
                    }
                });
            });
            if (typeof window.bpisBindImagePreviews === 'function') {
                window.bpisBindImagePreviews(qrModalBody);
            }
            lucide.createIcons();
            setTimeout(function() {
                fixAttachmentImageUrls();
                setHorizontalLayout();
                initAttachmentGallery();
            }, 100);
            qrModal.classList.remove('hidden');
            document.body.classList.add('qr-modal-open');
        });
    });

    function printSingleTag(item) {
        const qrPayload = item.qr_data || item.code;
        const qrUrl = `https://api.qrserver.com/v1/create-qr-code/?size=120x120&data=${encodeURIComponent(qrPayload)}`;
        const descriptionText = escHtml(item.description || '');
        const tagCode = escHtml(formatPropertyId(item.code));
        const meta = itemBrandModel(item);
        
        printArea.innerHTML = '';
        const container = document.createElement('div');
        container.className = 'qr-tag-container';
        
        const tagDiv = document.createElement('div');
        tagDiv.className = 'qr-tag-card';
        tagDiv.innerHTML = `
            <div class="qr-code-wrapper">
                <img src="${qrUrl}" alt="QR Code" class="qr-code-image" onerror="this.src='data:image/svg+xml,%3Csvg xmlns=\\'http://www.w3.org/2000/svg\\' width=\\'120\\' height=\\'120\\' viewBox=\\'0 0 120 120\\'%3E%3Crect width=\\'120\\' height=\\'120\\' fill=\\'%23f0f0f0\\'/%3E%3Ctext x=\\'60\\' y=\\'60\\' text-anchor=\\'middle\\' dy=\\'.3em\\' fill=\\'%23999\\' font-size=\\'12\\'%3EQR%3C/text%3E%3C/svg%3E'">
            </div>
            <div class="tag-id"><strong>ID:</strong> ${tagCode}</div>
            <div class="tag-line"><strong>Description:</strong> ${descriptionText || '—'}</div>
            ${meta.brand ? `<div class="tag-line"><strong>Brand:</strong> ${escHtml(meta.brand)}</div>` : ''}
            ${meta.model ? `<div class="tag-line"><strong>Model:</strong> ${escHtml(meta.model)}</div>` : ''}
        `;
        container.appendChild(tagDiv);
        printArea.appendChild(container);
        
        setTimeout(function() {
            window.print();
        }, 300);
    }

    document.getElementById('printAllBtn').addEventListener('click', () => {
        printArea.innerHTML = '';
        
        const container = document.createElement('div');
        container.className = 'qr-tag-container';
        
        currentSubitems.forEach(item => {
            const qrPayload = item.qr_data || item.code;
            const qrUrl = `https://api.qrserver.com/v1/create-qr-code/?size=120x120&data=${encodeURIComponent(qrPayload)}`;
            const tagCode = escHtml(formatPropertyId(item.code));
            const descriptionText = escHtml(item.description || '');
            const meta = itemBrandModel(item);
            
            const tagDiv = document.createElement('div');
            tagDiv.className = 'qr-tag-card';
            tagDiv.innerHTML = `
                <div class="qr-code-wrapper">
                    <img src="${qrUrl}" alt="QR Code" class="qr-code-image" onerror="this.src='data:image/svg+xml,%3Csvg xmlns=\\'http://www.w3.org/2000/svg\\' width=\\'120\\' height=\\'120\\' viewBox=\\'0 0 120 120\\'%3E%3Crect width=\\'120\\' height=\\'120\\' fill=\\'%23f0f0f0\\'/%3E%3Ctext x=\\'60\\' y=\\'60\\' text-anchor=\\'middle\\' dy=\\'.3em\\' fill=\\'%23999\\' font-size=\\'12\\'%3EQR%3C/text%3E%3C/svg%3E'">
                </div>
                <div class="tag-id"><strong>ID:</strong> ${tagCode}</div>
                <div class="tag-line"><strong>Description:</strong> ${descriptionText || '—'}</div>
                ${meta.brand ? `<div class="tag-line"><strong>Brand:</strong> ${escHtml(meta.brand)}</div>` : ''}
                ${meta.model ? `<div class="tag-line"><strong>Model:</strong> ${escHtml(meta.model)}</div>` : ''}
            `;
            container.appendChild(tagDiv);
        });
        
        printArea.appendChild(container);
        
        let imagesLoaded = 0;
        const images = printArea.querySelectorAll('img');
        const totalImages = images.length;
        
        if (totalImages === 0) {
            window.print();
        } else {
            images.forEach(img => {
                if (img.complete) {
                    imagesLoaded++;
                } else {
                    img.addEventListener('load', () => {
                        imagesLoaded++;
                        if (imagesLoaded === totalImages) {
                            setTimeout(() => window.print(), 200);
                        }
                    });
                    img.addEventListener('error', () => {
                        imagesLoaded++;
                        if (imagesLoaded === totalImages) {
                            setTimeout(() => window.print(), 200);
                        }
                    });
                }
            });
            
            if (imagesLoaded === totalImages) {
                setTimeout(() => window.print(), 200);
            }
        }
    });

    document.getElementById('closeQrModal').addEventListener('click', () => {
        qrModal.classList.add('hidden');
        document.body.classList.remove('qr-modal-open');
    });

    qrModal.addEventListener('click', (e) => {
        if (e.target === qrModal) {
            qrModal.classList.add('hidden');
            document.body.classList.remove('qr-modal-open');
        }
    });

    lucide.createIcons();
    </script>
    <script src="../realtime_notifications.js"></script>
    <script src="../js/captain_mobile_sidebar.js"></script>
</body>
</html>