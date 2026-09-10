<?php
session_start();
include __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth_helpers.php';
require_once __DIR__ . '/../config/coa_labels.php';
require_once __DIR__ . '/../config/asset_units_helpers.php';
require_once __DIR__ . '/../config/asset_borrowable_helpers.php';
require_once __DIR__ . '/../config/asset_list_helpers.php';
require_once __DIR__ . '/../config/asset_edit_helpers.php';
require_once __DIR__ . '/../config/upload_helpers.php';
require_once __DIR__ . '/../config/schema_bootstrap.php';
require_once __DIR__ . '/../config/table_date_filter_helpers.php';

bpis_require_login(['Treasurer', 'Barangay Captain']);

$bpis_treasurer_nav_active = 'inventory';
$user_fullname = 'User';
$user_role = (string) ($_SESSION['role'] ?? '');
$admin_id = (int) $_SESSION['admin_id'];
$query = "SELECT fullname, role FROM admin WHERE id = '$admin_id'";
$result = mysqli_query($conn, $query);
if ($result && mysqli_num_rows($result) > 0) {
    $user = mysqli_fetch_assoc($result);
    $user_fullname = $user['fullname'];
    $user_role = $user['role'];
}
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
    
    $details = [];
    
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($conn)) {
    $postAction = $_POST['action'] ?? '';
    $postAssetId = isset($_POST['asset_id']) ? (int)$_POST['asset_id'] : 0;

    if ($postAction === 'delete_asset' && $postAssetId > 0) {
        $assetLabel = '';
        $assetInfoStmt = mysqli_prepare($conn, "SELECT description, article FROM asset WHERE id = ? LIMIT 1");
        if ($assetInfoStmt) {
            mysqli_stmt_bind_param($assetInfoStmt, "i", $postAssetId);
            mysqli_stmt_execute($assetInfoStmt);
            mysqli_stmt_bind_result($assetInfoStmt, $assetDesc, $assetArticle);
            if (mysqli_stmt_fetch($assetInfoStmt)) {
                $assetLabel = trim((string)$assetDesc) !== '' ? (string)$assetDesc : (string)$assetArticle;
            }
            mysqli_stmt_close($assetInfoStmt);
        }

        $unlinkSql = "UPDATE borrower SET asset_id = NULL";
        $hasItemCol = false;
        $borrowerCols = mysqli_query($conn, "SHOW COLUMNS FROM borrower");
        if ($borrowerCols) {
            while ($bc = mysqli_fetch_assoc($borrowerCols)) {
                if (($bc['Field'] ?? '') === 'item') {
                    $hasItemCol = true;
                    break;
                }
            }
        }
        if ($hasItemCol) {
            $unlinkSql .= ", item = COALESCE(NULLIF(item, ''), ?)";
        }
        $unlinkSql .= " WHERE asset_id = ?";

        $unlinkStmt = mysqli_prepare($conn, $unlinkSql);
        if ($unlinkStmt) {
            try {
                if ($hasItemCol) {
                    mysqli_stmt_bind_param($unlinkStmt, "si", $assetLabel, $postAssetId);
                } else {
                    mysqli_stmt_bind_param($unlinkStmt, "i", $postAssetId);
                }
                mysqli_stmt_execute($unlinkStmt);
            } catch (Throwable $e) {
                mysqli_stmt_close($unlinkStmt);
                header("Location: inventory.php?msg=unlink_failed");
                exit();
            }
            mysqli_stmt_close($unlinkStmt);
        }

        $reqColumns = [];
        $reqColsRs = mysqli_query($conn, "SHOW COLUMNS FROM borrowing_requests");
        if ($reqColsRs) {
            while ($rc = mysqli_fetch_assoc($reqColsRs)) {
                $reqColumns[$rc['Field']] = true;
            }
        }
        if (isset($reqColumns['asset_id'])) {
            $reqSetParts = ["asset_id = NULL"];
            $reqTypes = "";
            $reqParams = [];

            if (isset($reqColumns['item'])) {
                $reqSetParts[] = "item = COALESCE(NULLIF(item, ''), ?)";
                $reqTypes .= "s";
                $reqParams[] = $assetLabel;
            }
            if (isset($reqColumns['search_item'])) {
                $reqSetParts[] = "search_item = COALESCE(NULLIF(search_item, ''), ?)";
                $reqTypes .= "s";
                $reqParams[] = $assetLabel;
            }

            $reqSql = "UPDATE borrowing_requests SET " . implode(", ", $reqSetParts) . " WHERE asset_id = ?";
            $reqTypes .= "i";
            $reqParams[] = $postAssetId;
            $reqStmt = mysqli_prepare($conn, $reqSql);
            if ($reqStmt) {
                try {
                    $bindArgs = [$reqTypes];
                    foreach ($reqParams as $k => $v) {
                        $bindArgs[] = &$reqParams[$k];
                    }
                    call_user_func_array('mysqli_stmt_bind_param', array_merge([$reqStmt], $bindArgs));
                    mysqli_stmt_execute($reqStmt);
                } catch (Throwable $e) {
                    mysqli_stmt_close($reqStmt);
                    header("Location: inventory.php?msg=unlink_failed");
                    exit();
                }
                mysqli_stmt_close($reqStmt);
            }
        }

        $stmt = mysqli_prepare($conn, "DELETE FROM asset WHERE id = ?");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "i", $postAssetId);
            try {
                mysqli_stmt_execute($stmt);
            } catch (Throwable $e) {
                mysqli_stmt_close($stmt);
                header("Location: inventory.php?msg=delete_failed");
                exit();
            }
            mysqli_stmt_close($stmt);
        }
        header("Location: inventory.php?msg=deleted");
        exit();
    }

    if ($postAction === 'edit_asset' && $postAssetId > 0) {
        $edit_result = bpis_process_inventory_asset_edit($conn, $postAssetId, $_POST, $_FILES);
        if (!empty($edit_result['ok'])) {
            header('Location: inventory.php?msg=updated');
            exit();
        }
        $_SESSION['inventory_edit_error'] = (string) ($edit_result['error'] ?? 'Could not save asset.');
        header('Location: inventory.php?edit_error=1');
        exit();
    }
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
                "units_edit" => bpis_load_asset_units_for_edit($conn, $row),
                "edit_payload" => [
                    "id" => (int)$row['id'],
                    "article" => $row['article'],
                    "description" => $row['description'],
                    "quantity" => $qty,
                    "category" => $row['category'],
                    "property_number" => $row['property_number'],
                    "unit_measure" => $row['unit_measure'],
                    "date_acquired" => $row['date_acquired'],
                    "unit_value" => $row['unit_value'],
                    "remarks" => $row['remarks'],
                    "status" => $row['status'] ?? 'Available',
                    "asset_cluster" => $row['asset_cluster'] ?? 'Movable Assets',
                    "location" => $row['location'] ?? '',
                    "brand" => $row['brand'] ?? '',
                    "model" => $row['model'] ?? '',
                    "audit_remarks" => $row['audit_remarks'] ?? '',
                    "photo_url" => '',
                    "units_edit" => bpis_load_asset_units_for_edit($conn, $row),
                    "acquisition_mode" => $acquisition_mode,
                    "cheque_number" => $acq_detail_data['cheque_number'] ?? '',
                    "voucher_number" => $acq_detail_data['voucher_number'] ?? '',
                    "cash_amount" => $acq_detail_data['cash_amount'] ?? '',
                    "shop_name" => $acq_detail_data['shop_name'] ?? '',
                    "purchaser_name" => $acq_detail_data['purchaser_name'] ?? '',
                    "donor_name" => $acq_detail_data['donor_name'] ?? '',
                    "donation_date" => $acq_detail_data['donation_date'] ?? '',
                    "transfer_from_agency" => $acq_detail_data['transfer_from_agency'] ?? '',
                    "transfer_date" => $acq_detail_data['transfer_date'] ?? '',
                    "contractor_name" => $acq_detail_data['contractor_name'] ?? '',
                    "contract_amount" => $acq_detail_data['contract_amount'] ?? '',
                    "lessor_name" => $acq_detail_data['lessor_name'] ?? '',
                    "lease_period_start" => $acq_detail_data['lease_period_start'] ?? '',
                    "lease_period_end" => $acq_detail_data['lease_period_end'] ?? '',
                    "previous_owner" => $acq_detail_data['previous_owner'] ?? '',
                    "repossession_date" => $acq_detail_data['repossession_date'] ?? '',
                    "seizure_authority" => $acq_detail_data['seizure_authority'] ?? '',
                    "seizure_date" => $acq_detail_data['seizure_date'] ?? '',
                    "seller_name" => $acq_detail_data['seller_name'] ?? '',
                    "negotiation_date" => $acq_detail_data['negotiation_date'] ?? '',
                    "aid_agency" => $acq_detail_data['aid_agency'] ?? '',
                    "aid_type" => $acq_detail_data['aid_type'] ?? '',
                    "aid_date" => $acq_detail_data['aid_date'] ?? '',
                ],
                "acquisition_display" => bpis_get_acquisition_display($conn, $row),
                "acquisition_attachments" => bpis_get_acquisition_attachments($conn, $row),
            ];
        }
    }
}

$asset_photo_map = bpis_get_asset_photo_map($conn, array_column($assets, 'id'));

foreach ($assets as $key => $asset) {
    $assets[$key]['edit_payload']['photo_url'] = bpis_asset_photo_url($conn, (int)$asset['id'], $asset_photo_map);
}
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
    <link rel="stylesheet" href="../css/asset_edit_modal.css">
    <link rel="stylesheet" href="../css/sidebar_nav_transition.css">
    <script src="../js/sidebar_nav_transition.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php include __DIR__ . '/../includes/bpis_app_meta.php'; ?>
    <title>Asset Inventory Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.sheetjs.com/xlsx-0.20.2/package/dist/xlsx.full.min.js"></script>
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
        min-width: 0; 
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

    .btn-edit-custom { 
        background: linear-gradient(135deg, #174C7D 0%, #1d5f94 100%); 
        color: #fff; 
        border: none; 
        padding: 8px 18px; 
        border-radius: 8px; 
        cursor: pointer; 
        font-weight: 600; 
        font-size: 12px; 
        transition: all 0.2s; 
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        outline: none;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }

    .btn-edit-custom:hover { 
        background: linear-gradient(135deg, #1d5f94 0%, #236ea8 100%); 
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(23, 76, 125, 0.2);
    }

    .btn-edit-custom:active { 
        transform: scale(0.98); 
    }

    .btn-confirm {
        background-color: #3B82F6;
        color: white;
        border: none;
        padding: 12px 35px;
        border-radius: 6px;
        font-weight: 600;
        font-size: 14px;
        cursor: pointer;
        transition: background 0.2s;
        flex: 1;
    }

    .btn-confirm:hover {
        background-color: #2563eb;
    }

    .btn-cancel {
        background-color: transparent;
        color: #3B82F6;
        border: 2px solid #3B82F6;
        padding: 12px 35px;
        border-radius: 6px;
        font-weight: 600;
        font-size: 14px;
        cursor: pointer;
        transition: all 0.2s;
        flex: 1;
    }

    .btn-cancel:hover {
        background-color: #f0f7ff;
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
        height: 100dvh;
        background-color: rgba(0, 0, 0, 0.95);
        z-index: 9999;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: max(12px, env(safe-area-inset-top)) max(12px, env(safe-area-inset-right)) max(12px, env(safe-area-inset-bottom)) max(12px, env(safe-area-inset-left));
        overflow: hidden;
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
        max-height: calc(100vh - 96px);
        max-height: calc(100dvh - 96px);
    }

    .attachment-gallery-image {
        max-width: 90vw;
        max-height: calc(100vh - 104px);
        max-height: calc(100dvh - 104px);
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
            top: max(10px, env(safe-area-inset-top));
            right: max(10px, env(safe-area-inset-right));
            width: 40px;
            height: 40px;
            font-size: 24px;
        }

        .attachment-gallery-download {
            top: max(10px, env(safe-area-inset-top));
            right: calc(max(10px, env(safe-area-inset-right)) + 50px);
            width: 40px;
            height: 40px;
            font-size: 16px;
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

        #qrModal .qr-modal-header {
            padding: 12px 14px !important;
            align-items: flex-start !important;
            gap: 10px !important;
        }

        #qrModalTitle {
            font-size: 15px !important;
            line-height: 1.25 !important;
            overflow-wrap: anywhere !important;
        }

        #qrModal .qr-modal-subtitle {
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

        #qrModalBody .qr-modal-list {
            gap: 10px !important;
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
            grid-row: 1 !important;
            width: 34px !important;
            height: 34px !important;
            padding: 0 !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
        }

        #qrModal .qr-modal-footer {
            padding: 12px 14px !important;
            padding-bottom: max(12px, env(safe-area-inset-bottom)) !important;
        }

        #printAllBtn {
            width: 100% !important;
            justify-content: center !important;
            min-height: 40px !important;
        }
    }
    
    .asset-btn {
        border: none;
        cursor: pointer;
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
    
    .mode-fields-group {
        background: #f9fafb;
        border-radius: 12px;
        padding: 16px;
        margin-top: 16px;
        border: 1px solid #e5e7eb;
    }
    
    .mode-fields-group h4 {
        font-size: 12px;
        font-weight: 600;
        color: #374151;
        margin-bottom: 12px;
        padding-bottom: 8px;
        border-bottom: 1px solid #e5e7eb;
    }
    
    .acq-required {
        color: #dc2626 !important;
        font-weight: 700;
        margin-left: 3px;
    }
    
    .bpis-disabled-input {
        background-color: #f3f4f6 !important;
        cursor: not-allowed !important;
        opacity: 0.7;
    }

    @media (max-width: 1200px) {
        .sidebar {
            width: 210px !important;
            padding: 22px 14px !important;
        }
        .sidebar h2 {
            font-size: 15px !important;
            margin-bottom: 24px !important;
        }
        .main-content {
            padding: 22px 28px !important;
        }
        .table-responsive-container {
            max-height: 450px !important;
        }
        .acquisition-mode-cell {
            min-width: 180px !important;
            max-width: 280px !important;
        }
        .acquisition-attachments-cell {
            min-width: 150px !important;
            max-width: 240px !important;
        }
        .acquisition-attachments-cell img {
            width: 110px !important;
            height: 110px !important;
        }
    }

    @media (max-width: 1024px) {
        .sidebar {
            width: 200px !important;
            padding: 20px 12px !important;
        }
        .sidebar h2 {
            font-size: 14px !important;
            margin-bottom: 20px !important;
        }
        .nav-item {
            font-size: 12px !important;
            padding: 10px 12px !important;
        }
        .main-content {
            margin-left: 200px !important;
            padding: 20px 25px !important;
        }
        .header h1 {
            font-size: 20px !important;
        }
        .logo-img {
            width: 60px !important;
            height: 60px !important;
        }
        .icon-circle {
            width: 38px !important;
            height: 38px !important;
        }
        .icon-box {
            width: 28px !important;
            height: 28px !important;
        }
        .btn-edit-custom {
            font-size: 11px !important;
            padding: 6px 14px !important;
        }
        .table-responsive-container {
            max-height: 400px !important;
        }
        th {
            font-size: 10px !important;
            padding: 10px 8px !important;
        }
        td {
            font-size: 11px !important;
            padding: 12px 8px !important;
        }
        .acquisition-mode-cell {
            min-width: 150px !important;
            max-width: 240px !important;
        }
        .acquisition-attachments-cell {
            min-width: 120px !important;
            max-width: 200px !important;
        }
        .acquisition-attachments-cell img {
            width: 90px !important;
            height: 90px !important;
        }
        .acquisition-attachments-cell a {
            width: 50px !important;
            padding: 6px !important;
        }
        .acquisition-attachments-cell i {
            font-size: 22px !important;
        }
        .custom-scrollbar::-webkit-scrollbar {
            width: 5px !important;
            height: 5px !important;
        }
    }

    @media (max-width: 900px) {
        .sidebar {
            width: 180px !important;
            padding: 16px 10px !important;
        }
        .sidebar h2 {
            font-size: 13px !important;
            margin-bottom: 16px !important;
        }
        .nav-item {
            font-size: 11px !important;
            padding: 8px 10px !important;
            border-radius: 8px !important;
        }
        .nav-item i {
            width: 16px !important;
            font-size: 13px !important;
            margin-right: 8px !important;
        }
        .main-content {
            margin-left: 180px !important;
            padding: 16px 20px !important;
        }
        .header h1 {
            font-size: 19px !important;
        }
        .logo-img {
            width: 52px !important;
            height: 52px !important;
        }
        .profile-dropdown {
            width: 200px !important;
        }
        .btn-confirm,
        .btn-cancel {
            font-size: 13px !important;
            padding: 10px 28px !important;
        }
    }

    @media (max-width: 768px) {
        .sidebar {
            width: 60px !important;
            padding: 15px 8px !important;
        }
        .sidebar h2 {
            display: none !important;
        }
        .sidebar-bottom {
            display: none !important;
        }
        .logo-img {
            width: 40px !important;
            height: 40px !important;
        }
        .nav-item {
            font-size: 11px !important;
            padding: 8px 6px !important;
            text-align: center !important;
            border-radius: 8px !important;
        }
        .nav-item i {
            margin-right: 0 !important;
            width: 20px !important;
            font-size: 16px !important;
        }
        .nav-item span {
            display: none !important;
        }
        .nav-item:hover,
        .nav-item.active {
            transform: none !important;
        }
        .main-content {
            margin-left: 60px !important;
            padding: 15px 15px !important;
        }
        .header {
            flex-wrap: wrap !important;
            gap: 10px !important;
            margin-bottom: 18px !important;
        }
        .header h1 {
            font-size: 18px !important;
        }
        .header p {
            font-size: 12px !important;
        }
        .header-icons {
            gap: 12px !important;
        }
        .icon-circle {
            width: 34px !important;
            height: 34px !important;
        }
        .icon-box {
            width: 26px !important;
            height: 26px !important;
        }
        .profile-dropdown {
            width: 180px !important;
            right: -10px !important;
        }
        .profile-user-info img {
            width: 48px !important;
            height: 48px !important;
        }
        .profile-user-info h4 {
            font-size: 13px !important;
        }
        .profile-links a {
            font-size: 12px !important;
            padding: 10px 16px !important;
        }
        .btn-edit-custom {
            font-size: 10px !important;
            padding: 5px 10px !important;
            border-radius: 6px !important;
        }
        .btn-confirm,
        .btn-cancel {
            font-size: 12px !important;
            padding: 8px 20px !important;
            border-radius: 4px !important;
        }
        .table-responsive-container {
            max-height: 350px !important;
        }
        th {
            font-size: 9px !important;
            padding: 8px 6px !important;
        }
        td {
            font-size: 10px !important;
            padding: 10px 6px !important;
        }
        .acquisition-mode-cell {
            min-width: 120px !important;
            max-width: 180px !important;
        }
        .acquisition-attachments-cell {
            min-width: 100px !important;
            max-width: 160px !important;
        }
        .acquisition-attachments-cell img {
            width: 70px !important;
            height: 70px !important;
        }
        .acquisition-attachments-cell a {
            width: 40px !important;
            padding: 4px !important;
        }
        .acquisition-attachments-cell i {
            font-size: 18px !important;
        }
        .mode-fields-group {
            padding: 12px !important;
            border-radius: 8px !important;
        }
        .mode-fields-group h4 {
            font-size: 11px !important;
        }
        .attachment-gallery-prev,
        .attachment-gallery-next {
            font-size: 32px !important;
            padding: 12px 10px !important;
        }
        .attachment-gallery-prev {
            left: 10px !important;
        }
        .attachment-gallery-next {
            right: 10px !important;
        }
        .attachment-gallery-close {
            top: 10px !important;
            right: 10px !important;
            width: 40px !important;
            height: 40px !important;
            font-size: 24px !important;
        }
        .attachment-gallery-download {
            top: 10px !important;
            right: 60px !important;
            width: 40px !important;
            height: 40px !important;
            font-size: 16px !important;
        }
    }

    @media (max-width: 600px) {
        .sidebar {
            width: 55px !important;
            padding: 12px 6px !important;
        }
        .logo-img {
            width: 34px !important;
            height: 34px !important;
        }
        .nav-item {
            font-size: 10px !important;
            padding: 6px 4px !important;
            border-radius: 6px !important;
        }
        .nav-item i {
            font-size: 14px !important;
            width: 18px !important;
        }
        .main-content {
            margin-left: 55px !important;
            padding: 12px 12px !important;
        }
        .header h1 {
            font-size: 17px !important;
        }
        .header p {
            font-size: 11px !important;
        }
        .header-icons {
            gap: 10px !important;
        }
        .icon-circle {
            width: 30px !important;
            height: 30px !important;
        }
        .icon-box {
            width: 24px !important;
            height: 24px !important;
        }
        .table-responsive-container {
            max-height: 300px !important;
        }
        th {
            font-size: 8px !important;
            padding: 6px 4px !important;
        }
        td {
            font-size: 9px !important;
            padding: 8px 4px !important;
        }
        .acquisition-mode-cell {
            min-width: 100px !important;
            max-width: 140px !important;
        }
        .acquisition-attachments-cell {
            min-width: 80px !important;
            max-width: 120px !important;
        }
        .acquisition-attachments-cell img {
            width: 55px !important;
            height: 55px !important;
        }
        .acquisition-attachments-cell a {
            width: 32px !important;
            padding: 3px !important;
        }
        .acquisition-attachments-cell i {
            font-size: 14px !important;
        }
        .btn-edit-custom {
            font-size: 9px !important;
            padding: 4px 8px !important;
            border-radius: 4px !important;
            gap: 4px !important;
        }
        .btn-confirm,
        .btn-cancel {
            font-size: 11px !important;
            padding: 6px 16px !important;
        }
    }

    @media (max-width: 480px) {
        .sidebar {
            width: 50px !important;
            padding: 10px 4px !important;
        }
        .logo-img {
            width: 30px !important;
            height: 30px !important;
        }
        .nav-item {
            font-size: 10px !important;
            padding: 6px 4px !important;
            border-radius: 6px !important;
        }
        .nav-item i {
            font-size: 14px !important;
            width: 18px !important;
        }
        .main-content {
            margin-left: 50px !important;
            padding: 10px 10px !important;
        }
        .header {
            flex-direction: column !important;
            align-items: flex-start !important;
            gap: 8px !important;
            margin-bottom: 14px !important;
        }
        .header h1 {
            font-size: 16px !important;
        }
        .header p {
            font-size: 11px !important;
        }
        .header-icons {
            align-self: flex-end !important;
            gap: 8px !important;
        }
        .icon-circle {
            width: 28px !important;
            height: 28px !important;
        }
        .icon-box {
            width: 22px !important;
            height: 22px !important;
        }
        .profile-dropdown {
            width: 160px !important;
            right: -5px !important;
            top: 48px !important;
        }
        .profile-user-info img {
            width: 40px !important;
            height: 40px !important;
        }
        .profile-user-info h4 {
            font-size: 12px !important;
        }
        .profile-links a {
            font-size: 11px !important;
            padding: 8px 14px !important;
        }
        .btn-edit-custom {
            font-size: 8px !important;
            padding: 3px 6px !important;
            border-radius: 3px !important;
            gap: 3px !important;
        }
        .btn-confirm,
        .btn-cancel {
            font-size: 10px !important;
            padding: 5px 12px !important;
            border-radius: 3px !important;
        }
        .table-responsive-container {
            max-height: 250px !important;
            border-radius: 6px !important;
        }
        th {
            font-size: 7px !important;
            padding: 4px 3px !important;
        }
        td {
            font-size: 8px !important;
            padding: 6px 3px !important;
        }
        .acquisition-mode-cell {
            min-width: 80px !important;
            max-width: 110px !important;
        }
        .acquisition-attachments-cell {
            min-width: 60px !important;
            max-width: 90px !important;
        }
        .acquisition-attachments-cell img {
            width: 45px !important;
            height: 45px !important;
        }
        .acquisition-attachments-cell a {
            width: 26px !important;
            padding: 2px !important;
        }
        .acquisition-attachments-cell i {
            font-size: 12px !important;
        }
        .mode-fields-group {
            padding: 8px !important;
            border-radius: 6px !important;
        }
        .mode-fields-group h4 {
            font-size: 10px !important;
            margin-bottom: 8px !important;
        }
        .acq-required {
            font-size: 10px !important;
        }
        .attachment-gallery-prev,
        .attachment-gallery-next {
            font-size: 24px !important;
            padding: 8px 6px !important;
        }
        .attachment-gallery-close {
            width: 32px !important;
            height: 32px !important;
            font-size: 18px !important;
            padding: 8px !important;
        }
        .attachment-gallery-download {
            width: 32px !important;
            height: 32px !important;
            font-size: 14px !important;
            padding: 8px !important;
            right: 45px !important;
        }
        .attachment-gallery-counter {
            font-size: 12px !important;
        }
        .attachment-gallery-caption {
            font-size: 10px !important;
        }
    }

    @media (max-width: 400px) {
        .sidebar {
            width: 45px !important;
            padding: 8px 3px !important;
        }
        .logo-img {
            width: 26px !important;
            height: 26px !important;
        }
        .nav-item {
            font-size: 9px !important;
            padding: 5px 3px !important;
            border-radius: 4px !important;
        }
        .nav-item i {
            font-size: 12px !important;
            width: 16px !important;
        }
        .main-content {
            margin-left: 45px !important;
            padding: 8px 8px !important;
        }
        .header h1 {
            font-size: 15px !important;
        }
        .header p {
            font-size: 10px !important;
        }
        .header-icons {
            gap: 6px !important;
        }
        .icon-circle {
            width: 26px !important;
            height: 26px !important;
        }
        .icon-box {
            width: 20px !important;
            height: 20px !important;
        }
        .btn-edit-custom {
            font-size: 7px !important;
            padding: 2px 5px !important;
            border-radius: 2px !important;
        }
        .table-responsive-container {
            max-height: 200px !important;
        }
        th {
            font-size: 6px !important;
            padding: 3px 2px !important;
        }
        td {
            font-size: 7px !important;
            padding: 4px 2px !important;
        }
        .acquisition-mode-cell {
            min-width: 60px !important;
            max-width: 90px !important;
        }
        .acquisition-attachments-cell {
            min-width: 50px !important;
            max-width: 70px !important;
        }
        .acquisition-attachments-cell img {
            width: 35px !important;
            height: 35px !important;
        }
    }

    @media (max-width: 360px) {
        .sidebar {
            width: 40px !important;
            padding: 8px 2px !important;
        }
        .logo-img {
            width: 24px !important;
            height: 24px !important;
        }
        .nav-item {
            font-size: 9px !important;
            padding: 4px 2px !important;
            border-radius: 4px !important;
        }
        .nav-item i {
            font-size: 12px !important;
            width: 16px !important;
        }
        .main-content {
            margin-left: 40px !important;
            padding: 8px 6px !important;
        }
        .header h1 {
            font-size: 14px !important;
        }
        .header p {
            font-size: 10px !important;
        }
        .header-icons {
            gap: 6px !important;
        }
        .icon-circle {
            width: 24px !important;
            height: 24px !important;
        }
        .icon-box {
            width: 18px !important;
            height: 18px !important;
        }
        .profile-dropdown {
            width: 140px !important;
            right: 0 !important;
            top: 42px !important;
            padding-top: 14px !important;
        }
        .profile-user-info img {
            width: 34px !important;
            height: 34px !important;
        }
        .profile-user-info h4 {
            font-size: 11px !important;
        }
        .profile-links a {
            font-size: 10px !important;
            padding: 6px 12px !important;
        }
        .btn-edit-custom {
            font-size: 6px !important;
            padding: 2px 4px !important;
        }
        .btn-confirm,
        .btn-cancel {
            font-size: 9px !important;
            padding: 4px 10px !important;
        }
        .table-responsive-container {
            max-height: 180px !important;
        }
        th {
            font-size: 5px !important;
            padding: 2px 1px !important;
        }
        td {
            font-size: 6px !important;
            padding: 3px 1px !important;
        }
        .acquisition-mode-cell {
            min-width: 50px !important;
            max-width: 70px !important;
        }
        .acquisition-attachments-cell {
            min-width: 40px !important;
            max-width: 60px !important;
        }
        .acquisition-attachments-cell img {
            width: 28px !important;
            height: 28px !important;
        }
        .mode-fields-group {
            padding: 6px !important;
        }
        .mode-fields-group h4 {
            font-size: 9px !important;
        }
        .attachment-gallery-prev,
        .attachment-gallery-next {
            font-size: 18px !important;
            padding: 6px 4px !important;
        }
        .attachment-gallery-close {
            width: 28px !important;
            height: 28px !important;
            font-size: 14px !important;
            padding: 6px !important;
        }
        .attachment-gallery-download {
            width: 28px !important;
            height: 28px !important;
            font-size: 12px !important;
            padding: 6px !important;
            right: 38px !important;
        }
    }

    @media (max-width: 768px) {
        #assetTable {
            font-size: 12px !important;
            line-height: 1.35 !important;
        }
        #assetTable th {
            font-size: 11px !important;
            line-height: 1.25 !important;
            padding: 9px 8px !important;
        }
        #assetTable td {
            font-size: 12px !important;
            line-height: 1.35 !important;
            padding: 10px 8px !important;
        }
        #assetTable tbody {
            font-size: 12px !important;
        }
        #assetTable .text-\[9px\],
        #assetTable .text-\[10px\],
        #assetTable .text-\[11px\],
        #assetTable .text-\[12px\],
        #assetTable .text-xs {
            font-size: 12px !important;
            line-height: 1.35 !important;
        }
        #assetTable .acquisition-mode-cell {
            min-width: 170px !important;
            max-width: 260px !important;
        }
        #assetTable .acquisition-attachments-cell {
            min-width: 120px !important;
            max-width: 180px !important;
        }
        #assetTable .asset-actions-cell {
            min-width: 120px !important;
            width: 120px !important;
            padding-left: 10px !important;
            padding-right: 10px !important;
        }
        #assetTable .asset-actions-cell .btn-edit-custom {
            min-width: 88px !important;
            min-height: 40px !important;
            padding: 9px 14px !important;
            font-size: 12px !important;
            border-radius: 8px !important;
            gap: 6px !important;
        }
        #assetTable .asset-actions-cell .btn-edit-custom i {
            font-size: 13px !important;
        }
    }

    @media (max-width: 480px) {
        #assetTable th {
            font-size: 10px !important;
            padding: 8px 7px !important;
        }
        #assetTable td,
        #assetTable tbody,
        #assetTable .text-\[9px\],
        #assetTable .text-\[10px\],
        #assetTable .text-\[11px\],
        #assetTable .text-\[12px\],
        #assetTable .text-xs {
            font-size: 11px !important;
            line-height: 1.35 !important;
        }
        #assetTable td {
            padding: 9px 7px !important;
        }
        #assetTable .asset-actions-cell {
            min-width: 116px !important;
            width: 116px !important;
        }
        #assetTable .asset-actions-cell .btn-edit-custom {
            min-width: 88px !important;
            min-height: 40px !important;
            padding: 9px 13px !important;
            font-size: 12px !important;
        }
    }

    .inventory-table-card,
    .inventory-table-scroll {
        min-height: 0;
    }

    @media (max-width: 768px) {
        body {
            overflow-y: auto !important;
        }

        .main-content {
            height: auto !important;
            min-height: 100dvh !important;
            overflow: visible !important;
        }

        .inventory-table-card {
            flex: 0 0 auto !important;
            height: calc(100dvh - 250px) !important;
            min-height: 420px !important;
            max-height: none !important;
            overflow: hidden !important;
        }

        .inventory-table-scroll {
            flex: 1 1 auto !important;
            height: 100% !important;
            min-height: 0 !important;
            max-height: none !important;
            overflow: auto !important;
            -webkit-overflow-scrolling: touch !important;
            overscroll-behavior: contain;
            padding-bottom: 24px;
        }
    }

    @media (max-width: 420px) {
        .inventory-table-card {
            height: calc(100dvh - 235px) !important;
            min-height: 390px !important;
        }
    }
</style>
    <link rel="stylesheet" href="../css/treasurer_mobile_sidebar.css">
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
            <h2>Barangay Bolocboloc Property Inventory System</h2>
            <?php include __DIR__ . '/../includes/treasurer_sidebar.php'; ?>
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
                <h1>Asset Master Inventory</h1>
                <p>View and manage all registered properties and their current status.</p>
                <?php if (!empty($_GET['edit_error']) && !empty($_SESSION['inventory_edit_error'])): ?>
                    <div class="mt-3 px-4 py-2 rounded-lg bg-red-50 border border-red-200 text-red-800 text-sm" role="alert">
                        <?= htmlspecialchars((string) $_SESSION['inventory_edit_error']) ?>
                    </div>
                    <?php unset($_SESSION['inventory_edit_error']); ?>
                <?php endif; ?>
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
                <button id="exportReportBtn" class="bg-[#3B82F6] hover:bg-blue-600 text-white font-medium text-sm py-2.5 px-6 rounded-lg shadow-sm transition-colors flex items-center justify-center gap-2 whitespace-nowrap w-full sm:w-auto">
                    <i data-lucide="download" class="w-4 h-4"></i> Export to Excel
                </button>
            </div>
        </div>

        <div class="inventory-table-card flex-grow bg-white border border-gray-300 rounded-xl flex flex-col min-h-0 w-full overflow-hidden shadow-sm" data-bpis-date-filter-scope>
            <div class="px-4 pt-3 pb-2 border-b border-gray-100 shrink-0 bg-white">
                <div class="bpis-date-filter-bar" style="margin-bottom:0;">
                    <span class="bpis-date-filter-label">Filter by date acquired</span>
                    <label>From <input type="date" data-bpis-date-from aria-label="Filter from date"></label>
                    <label>To <input type="date" data-bpis-date-to aria-label="Filter to date"></label>
                    <button type="button" class="bpis-date-clear-btn" data-bpis-date-clear>Clear dates</button>
                </div>
            </div>
            <div class="inventory-table-scroll overflow-x-auto overflow-y-auto custom-scrollbar flex-grow">
                <table class="w-full min-w-max text-left border-collapse whitespace-nowrap" id="assetTable">
                    <thead class="sticky top-0 z-10 shadow-sm">
                        <tr class="bg-white">
                            <th class="py-4 px-6 font-semibold text-gray-500 text-[13px] uppercase border-b border-gray-200 text-center">QR CODE</th>
                            <th class="py-4 px-6 font-semibold text-gray-500 text-[13px] uppercase border-b border-gray-200 text-center">IMAGE</th>
                            <th class="py-4 px-6 font-semibold text-gray-500 text-[13px] uppercase border-b border-gray-200">ARTICLE</th>
                            <th class="py-4 px-6 font-semibold text-gray-500 text-[13px] uppercase border-b border-gray-200">DESCRIPTION</th>
                            <th class="py-4 px-6 font-semibold text-gray-500 text-[13px] uppercase border-b border-gray-200">CATEGORY</th>
                            <th class="py-4 px-6 font-semibold text-gray-500 text-[13px] uppercase border-b border-gray-200">CLUSTER</th>
                            <th class="py-4 px-6 font-semibold text-gray-500 text-[13px] uppercase border-b border-gray-200">LOCATION</th>
                            <th class="py-4 px-6 font-semibold text-gray-500 text-[13px] uppercase border-b border-gray-200">PROPERTY NUMBER</th>
                            <th class="py-4 px-6 font-semibold text-gray-500 text-[13px] uppercase border-b border-gray-200">UOM</th>
                            <th class="py-4 px-6 font-semibold text-gray-500 text-[13px] uppercase border-b border-gray-200">DATE ACQUIRED</th>
                            <th class="py-4 px-6 font-semibold text-gray-500 text-[13px] uppercase border-b border-gray-200">UNIT COST/VALUE</th>
                            <th class="py-4 px-6 font-semibold text-gray-500 text-[13px] uppercase border-b border-gray-200">ACQUISITION MODE</th>
                            <th class="py-4 px-6 font-semibold text-gray-500 text-[13px] uppercase border-b border-gray-200">ATTACHMENTS</th>
                            <th class="py-4 px-6 font-semibold text-gray-500 text-[13px] uppercase border-b border-gray-200">ACCOUNTABLE OFFICER</th>
                            <th class="py-4 px-6 font-semibold text-gray-500 text-[13px] uppercase border-b border-gray-200 text-center">REMARKS</th>
                            <th class="py-4 px-6 font-semibold text-gray-500 text-[13px] uppercase border-b border-gray-200 text-center">STATUS</th>
                            <th class="py-4 px-6 font-semibold text-gray-500 text-[13px] uppercase border-b border-gray-200 text-center asset-actions-cell">ACTIONS</th>
                          </tr>
                    </thead>
                    <tbody class="text-[12px]">
                        <?php if(empty($assets)): ?>
                            <tr id="noResultsRow">
                                <td colspan="17" class="py-10 text-center text-gray-500 font-medium">No assets found in this category.</td>
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
                                data-photo-url="<?= htmlspecialchars(bpis_asset_photo_url($conn, (int) $asset['id'], $asset_photo_map), ENT_QUOTES) ?>"
                                data-asset-edit="<?= htmlspecialchars(json_encode($asset['edit_payload'] ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8') ?>">
                                
                                <td class="py-4 px-6 text-center asset-actions-cell">
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
                                <td class="py-4 px-6 text-center">
                                    <div class="flex items-center justify-center gap-2">
                                        <button type="button" class="edit-asset-btn btn-edit-custom">
                                            <i class="fa-solid fa-pen-to-square"></i> Edit
                                        </button>
                                    </div>
                                  </td>
                              </tr>
                            <?php endforeach; ?>
                            <tr id="searchNoResults" class="hidden">
                                <td colspan="17" class="py-10 text-center text-gray-500 font-medium text-[12px]">No assets match your search criteria.</td>
                              </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <form id="assetActionForm" method="POST" enctype="multipart/form-data" class="hidden">
        <input type="hidden" name="action" id="assetActionType">
        <input type="hidden" name="asset_id" id="assetActionId">
        <input type="hidden" name="edit_article" id="editArticleField">
        <input type="hidden" name="edit_quantity" id="editQuantityField">
        <input type="hidden" name="edit_description" id="editDescriptionField">
        <input type="hidden" name="edit_category" id="editCategoryField">
        <input type="hidden" name="edit_prop_num" id="editPropNumField">
        <input type="hidden" name="edit_uom" id="editUomField">
        <input type="hidden" name="edit_date_acquired" id="editDateAcquiredField">
        <input type="hidden" name="edit_unit_value" id="editUnitValueField">
        <input type="hidden" name="edit_remarks" id="editRemarksField">
        <input type="hidden" name="edit_audit_remarks" id="editAuditRemarksField">
        <input type="hidden" name="edit_location" id="editLocationField">
        <input type="hidden" name="edit_asset_cluster" id="editAssetClusterField">
        <input type="hidden" name="edit_status" id="editStatusField">
        <input type="hidden" name="edit_brand" id="editBrandField">
        <input type="hidden" name="edit_model" id="editModelField">
        <input type="hidden" name="edit_acquisition_mode" id="editAcquisitionModeField">
        <input type="hidden" name="edit_cheque_number" id="editChequeField">
        <input type="hidden" name="edit_voucher_number" id="editVoucherField">
        <input type="hidden" name="edit_cash_amount" id="editCashField">
        <input type="hidden" name="edit_shop_name" id="editShopField">
        <input type="hidden" name="edit_purchaser_name" id="editPurchaserField">
        <input type="hidden" name="edit_donor_name" id="editDonorField">
        <input type="hidden" name="edit_donation_date" id="editDonationDateField">
        <input type="hidden" name="edit_transfer_from_agency" id="editTransferFromField">
        <input type="hidden" name="edit_transfer_date" id="editTransferDateField">
        <input type="hidden" name="edit_contractor_name" id="editContractorField">
        <input type="hidden" name="edit_contract_amount" id="editContractAmountField">
        <input type="hidden" name="edit_lessor_name" id="editLessorField">
        <input type="hidden" name="edit_lease_period_start" id="editLeaseStartField">
        <input type="hidden" name="edit_lease_period_end" id="editLeaseEndField">
        <input type="hidden" name="edit_previous_owner" id="editPrevOwnerField">
        <input type="hidden" name="edit_repossession_date" id="editRepossessionDateField">
        <input type="hidden" name="edit_seizure_authority" id="editSeizureAuthorityField">
        <input type="hidden" name="edit_seizure_date" id="editSeizureDateField">
        <input type="hidden" name="edit_seller_name" id="editSellerField">
        <input type="hidden" name="edit_negotiation_date" id="editNegotiationDateField">
        <input type="hidden" name="edit_aid_agency" id="editAidAgencyField">
        <input type="hidden" name="edit_aid_type" id="editAidTypeField">
        <input type="hidden" name="edit_aid_date" id="editAidDateField">
        <div id="editUnitConditionFields"></div>
    </form>

    <div id="editAssetModalOverlay" class="asset-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="editAssetModalTitle">
        <div class="asset-modal bpis-edit-asset-modal">
            <header class="bpis-edit-asset-header">
                <div class="bpis-edit-asset-header-main">
                    <span class="bpis-edit-asset-header-icon" aria-hidden="true"><i class="fa-solid fa-pen-to-square"></i></span>
                    <div>
                        <h2 id="editAssetModalTitle">Edit Asset</h2>
                        <p class="bpis-edit-asset-header-sub" id="editAssetModalSubtitle">Update inventory details for this property.</p>
                    </div>
                </div>
                <button type="button" id="closeEditAssetModal" class="bpis-edit-asset-close" aria-label="Close edit dialog">&times;</button>
            </header>
            <div class="asset-modal-body bpis-edit-asset-body">
                <div class="bpis-edit-asset-hero" id="editAssetHero">
                    <div class="bpis-edit-asset-hero-photo-wrap">
                        <img id="editAssetModalPhoto" class="bpis-edit-asset-hero-photo" src="<?= htmlspecialchars(bpis_asset_placeholder_image_url(), ENT_QUOTES) ?>" alt="">
                    </div>
                    <div class="bpis-edit-asset-hero-meta">
                        <p class="bpis-edit-asset-hero-name" id="editAssetHeroName">—</p>
                        <p class="bpis-edit-asset-hero-detail" id="editAssetHeroDetail">—</p>
                        <p class="bpis-edit-asset-hero-prop" id="editAssetHeroProp"></p>
                    </div>
                </div>
                <section class="bpis-edit-asset-section" aria-labelledby="editAssetSectionItem">
                    <h3 class="bpis-edit-asset-section-title" id="editAssetSectionItem">Item details</h3>
                    <div class="bpis-edit-asset-grid">
                        <div class="bpis-edit-asset-field">
                            <label class="bpis-edit-asset-label" for="modalEditArticle">Article</label>
                            <input id="modalEditArticle" class="asset-modal-input bpis-edit-asset-input" type="text" autocomplete="off">
                        </div>
                        <div class="bpis-edit-asset-field">
                            <label class="bpis-edit-asset-label" for="modalEditQuantity">Quantity</label>
                            <input id="modalEditQuantity" class="asset-modal-input bpis-edit-asset-input" type="number" min="1">
                        </div>
                        <div class="bpis-edit-asset-field full">
                            <label class="bpis-edit-asset-label" for="modalEditDescription">Description <span class="bpis-edit-asset-label-hint">(asset name)</span></label>
                            <input id="modalEditDescription" class="asset-modal-input bpis-edit-asset-input bpis-edit-asset-input--highlight" type="text" autocomplete="off">
                        </div>
                    </div>
                </section>
                <section class="bpis-edit-asset-section" aria-labelledby="editAssetSectionProperty">
                    <h3 class="bpis-edit-asset-section-title" id="editAssetSectionProperty">Property identification</h3>
                    <div class="bpis-edit-asset-grid">
                        <div class="bpis-edit-asset-field">
                            <label class="bpis-edit-asset-label" for="modalEditCategory">Category</label>
                            <select id="modalEditCategory" class="asset-modal-input bpis-edit-asset-input">
                                <option value="">Select category</option>
                                <?php foreach (bpis_inventory_category_options() as $cat_opt): ?>
                                    <option value="<?= htmlspecialchars($cat_opt) ?>"><?= htmlspecialchars($cat_opt) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="bpis-edit-asset-field">
                            <label class="bpis-edit-asset-label" for="modalEditPropNum">Property number prefix</label>
                            <input id="modalEditPropNum" class="asset-modal-input bpis-edit-asset-input" type="text" autocomplete="off">
                        </div>
                        <div class="bpis-edit-asset-field">
                            <label class="bpis-edit-asset-label" for="modalEditUom">Unit of measure</label>
                            <select id="modalEditUom" class="asset-modal-input bpis-edit-asset-input">
                                <option value="pc">pc</option>
                                <option value="unit">unit</option>
                                <option value="set">set</option>
                            </select>
                        </div>
                    </div>
                </section>
                <section class="bpis-edit-asset-section" aria-labelledby="editAssetSectionAcq">
                    <h3 class="bpis-edit-asset-section-title" id="editAssetSectionAcq">Acquisition &amp; status</h3>
                    <div class="bpis-edit-asset-grid">
                        <div class="bpis-edit-asset-field">
                            <label class="bpis-edit-asset-label" for="modalEditDateAcquired">Date acquired</label>
                            <input id="modalEditDateAcquired" class="asset-modal-input bpis-edit-asset-input" type="date">
                        </div>
                        <div class="bpis-edit-asset-field">
                            <label class="bpis-edit-asset-label" for="modalEditUnitValue">Unit cost / value (₱)</label>
                            <input id="modalEditUnitValue" class="asset-modal-input bpis-edit-asset-input" type="number" min="0" step="0.01" placeholder="0.00">
                        </div>
                        <div class="bpis-edit-asset-field" id="editOverallConditionWrap">
                            <label class="bpis-edit-asset-label" for="modalEditRemarks">Initial remarks</label>
                            <select id="modalEditRemarks" class="asset-modal-input bpis-edit-asset-input bpis-edit-asset-input--condition">
                                <option value="SERVICEABLE">SERVICEABLE</option>
                                <option value="UNDER REPAIR">UNDER REPAIR</option>
                                <option value="UNSERVICEABLE/DISPOSE">UNSERVICEABLE/DISPOSE</option>
                            </select>
                            <p class="bpis-edit-field-hint" id="editOverallConditionHint">Used when quantity is 1.</p>
                        </div>
                        <div class="bpis-edit-asset-field">
                            <label class="bpis-edit-asset-label" for="modalEditStatus">Initial status</label>
                            <select id="modalEditStatus" class="asset-modal-input bpis-edit-asset-input">
                                <option value="Available">Available</option>
                                <option value="In Use">In Use</option>
                            </select>
                        </div>
                        <div class="bpis-edit-asset-field full hidden" id="editBulkConditionWrap">
                            <label class="bpis-edit-asset-label" for="modalEditBulkCondition">Apply condition to all units</label>
                            <div class="bpis-edit-bulk-condition-row">
                                <select id="modalEditBulkCondition" class="asset-modal-input bpis-edit-asset-input bpis-edit-asset-input--condition">
                                    <option value="SERVICEABLE">SERVICEABLE</option>
                                    <option value="UNDER REPAIR">UNDER REPAIR</option>
                                    <option value="UNSERVICEABLE/DISPOSE">UNSERVICEABLE/DISPOSE</option>
                                </select>
                                <button type="button" id="applyBulkConditionBtn" class="bpis-edit-bulk-apply-btn">Apply to all</button>
                            </div>
                        </div>
                        <div class="bpis-edit-asset-field">
                            <label class="bpis-edit-asset-label" for="modalEditCluster">Asset cluster</label>
                            <select id="modalEditCluster" class="asset-modal-input bpis-edit-asset-input">
                                <option value="Fixed Assets">Fixed Assets</option>
                                <option value="Movable Assets">Movable Assets</option>
                            </select>
                        </div>
                        <div class="bpis-edit-asset-field full">
                            <label class="bpis-edit-asset-label" for="modalEditLocation">Assigned location</label>
                            <input id="modalEditLocation" class="asset-modal-input bpis-edit-asset-input" type="text" placeholder="Barangay Hall — Storage A" autocomplete="off">
                        </div>
                        <div class="bpis-edit-asset-field">
                            <label class="bpis-edit-asset-label" for="modalEditBrand">Brand</label>
                            <input id="modalEditBrand" class="asset-modal-input bpis-edit-asset-input" type="text" placeholder="e.g. Samsung" autocomplete="off">
                        </div>
                        <div class="bpis-edit-asset-field">
                            <label class="bpis-edit-asset-label" for="modalEditModel">Model</label>
                            <input id="modalEditModel" class="asset-modal-input bpis-edit-asset-input" type="text" placeholder="e.g. XR-200" autocomplete="off">
                        </div>
                        <div class="bpis-edit-asset-field full">
                            <label class="bpis-edit-asset-label" for="modalEditAuditRemarks">Audit / condition remarks</label>
                            <input id="modalEditAuditRemarks" class="asset-modal-input bpis-edit-asset-input" type="text" autocomplete="off">
                        </div>
                        <div class="bpis-edit-asset-field">
                            <label class="bpis-edit-asset-label" for="modalEditAcquisition">Acquisition mode</label>
                            <select id="modalEditAcquisition" class="asset-modal-input bpis-edit-asset-input">
                                <?php foreach (bpis_get_acquisition_modes() as $key => $mode): ?>
                                    <option value="<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($mode['label']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div id="editPurchasedSection" class="mode-fields-group hidden">
                            <h4><i class="fa-solid fa-receipt"></i> Purchase Details</h4>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div><label class="bpis-edit-asset-label">Cheque no.</label><input type="text" id="modalEditCheque" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"></div>
                                <div><label class="bpis-edit-asset-label">Voucher no.</label><input type="text" id="modalEditVoucher" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"></div>
                                <div><label class="bpis-edit-asset-label">Cash (₱)</label><input type="number" step="0.01" min="0" id="modalEditCash" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"></div>
                                <div><label class="bpis-edit-asset-label">Shop</label><input type="text" id="modalEditShop" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"></div>
                                <div><label class="bpis-edit-asset-label">Purchaser</label><input type="text" id="modalEditPurchaser" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"></div>
                            </div>
                        </div>
                        
                        <div id="editDonatedSection" class="mode-fields-group hidden">
                            <h4><i class="fa-solid fa-gift"></i> Donation Details</h4>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div><label class="bpis-edit-asset-label">Donor name</label><input type="text" id="modalEditDonor" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"></div>
                                <div><label class="bpis-edit-asset-label">Donation date</label><input type="date" id="modalEditDonationDate" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"></div>
                            </div>
                        </div>
                        
                        <div id="editTransferredSection" class="mode-fields-group hidden">
                            <h4><i class="fa-solid fa-exchange-alt"></i> Transfer Details</h4>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div><label class="bpis-edit-asset-label">Transfer from agency</label><input type="text" id="modalEditTransferFrom" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"></div>
                                <div><label class="bpis-edit-asset-label">Transfer date</label><input type="date" id="modalEditTransferDate" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"></div>
                            </div>
                        </div>
                        
                        <div id="editConstructedSection" class="mode-fields-group hidden">
                            <h4><i class="fa-solid fa-hard-hat"></i> Construction Details</h4>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div><label class="bpis-edit-asset-label">Contractor name</label><input type="text" id="modalEditContractor" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"></div>
                                <div><label class="bpis-edit-asset-label">Contract amount (₱)</label><input type="number" step="0.01" min="0" id="modalEditContractAmount" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"></div>
                            </div>
                        </div>
                        
                        <div id="editLeasedSection" class="mode-fields-group hidden">
                            <h4><i class="fa-solid fa-file-signature"></i> Lease Details</h4>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div><label class="bpis-edit-asset-label">Lessor name</label><input type="text" id="modalEditLessor" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"></div>
                                <div><label class="bpis-edit-asset-label">Lease period start</label><input type="date" id="modalEditLeaseStart" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"></div>
                                <div><label class="bpis-edit-asset-label">Lease period end</label><input type="date" id="modalEditLeaseEnd" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"></div>
                            </div>
                        </div>
                        
                        <div id="editRepossessedSection" class="mode-fields-group hidden">
                            <h4><i class="fa-solid fa-gavel"></i> Repossession Details</h4>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div><label class="bpis-edit-asset-label">Previous owner</label><input type="text" id="modalEditPrevOwner" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"></div>
                                <div><label class="bpis-edit-asset-label">Repossession date</label><input type="date" id="modalEditRepossessionDate" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"></div>
                            </div>
                        </div>
                        
                        <div id="editSeizedSection" class="mode-fields-group hidden">
                            <h4><i class="fa-solid fa-handcuffs"></i> Seizure Details</h4>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div><label class="bpis-edit-asset-label">Seizure authority</label><input type="text" id="modalEditSeizureAuthority" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"></div>
                                <div><label class="bpis-edit-asset-label">Seizure date</label><input type="date" id="modalEditSeizureDate" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"></div>
                            </div>
                        </div>
                        
                        <div id="editNegotiatedSection" class="mode-fields-group hidden">
                            <h4><i class="fa-solid fa-handshake"></i> Negotiated Sale Details</h4>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div><label class="bpis-edit-asset-label">Seller name</label><input type="text" id="modalEditSeller" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"></div>
                                <div><label class="bpis-edit-asset-label">Negotiation date</label><input type="date" id="modalEditNegotiationDate" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"></div>
                            </div>
                        </div>
                        
                        <div id="editAidSection" class="mode-fields-group hidden">
                            <h4><i class="fa-solid fa-hands-helping"></i> Aid Details</h4>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div><label class="bpis-edit-asset-label">Aid agency</label><input type="text" id="modalEditAidAgency" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"></div>
                                <div><label class="bpis-edit-asset-label">Aid type</label>
                                    <select id="modalEditAidType" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                                        <option value="">Select aid type</option>
                                        <option value="Foreign Aid">Foreign Aid</option>
                                        <option value="National Aid">National Aid</option>
                                        <option value="Local Aid">Local Aid</option>
                                        <option value="Private Donation">Private Donation</option>
                                    </select>
                                </div>
                                <div><label class="bpis-edit-asset-label">Aid date</label><input type="date" id="modalEditAidDate" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"></div>
                            </div>
                        </div>
                    </div>
                </section>
                <section class="bpis-edit-asset-section hidden" id="editUnitConditionsSection" aria-labelledby="editUnitConditionsTitle">
                    <h3 class="bpis-edit-asset-section-title" id="editUnitConditionsTitle">Condition per unit (pc)</h3>
                    <p class="bpis-edit-field-hint">Set the condition for each piece. Inventory summary uses the worst condition among all units.</p>
                    <div id="editUnitConditionsList" class="bpis-edit-unit-conditions-list"></div>
                </section>
            </div>
            <footer class="asset-modal-footer bpis-edit-asset-footer">
                <button type="button" id="saveEditAssetBtn" class="asset-btn asset-btn-primary">
                    <i class="fa-solid fa-check" aria-hidden="true"></i> Save changes
                </button>
                <button type="button" id="cancelEditAssetBtn" class="asset-btn asset-btn-secondary">Cancel</button>
            </footer>
        </div>
    </div>

    <div id="deleteAssetModalOverlay" class="asset-modal-overlay" role="alertdialog" aria-modal="true" aria-labelledby="deleteAssetModalTitle">
        <div class="asset-modal bpis-delete-asset-modal">
            <header class="bpis-delete-asset-header">
                <div class="bpis-delete-asset-icon" aria-hidden="true"><i class="fa-solid fa-trash-can"></i></div>
                <h3 id="deleteAssetModalTitle">Delete asset?</h3>
            </header>
            <div class="asset-modal-body bpis-delete-asset-body">
                <p>This will permanently remove <span id="deleteAssetName" class="bpis-delete-asset-name"></span> from inventory. This cannot be undone.</p>
            </div>
            <footer class="asset-modal-footer bpis-delete-asset-footer">
                <button type="button" id="confirmDeleteAssetBtn" class="asset-btn asset-btn-danger">
                    <i class="fa-solid fa-trash-can" aria-hidden="true"></i> Delete
                </button>
                <button type="button" id="cancelDeleteAssetBtn" class="asset-btn asset-btn-secondary">Cancel</button>
                <button type="button" id="closeDeleteAssetModal" class="sr-only" aria-label="Close">Close</button>
            </footer>
        </div>
    </div>

    <div id="qrModal" class="hidden fixed inset-0 bg-black bg-opacity-40 z-[2500] flex items-center justify-center backdrop-blur-sm transition-opacity">
        <div class="bg-white rounded-xl shadow-2xl w-[580px] max-w-[94%] flex flex-col max-h-[85vh]">
            <div class="qr-modal-header px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-gray-50 rounded-t-xl shrink-0">
                <div>
                    <h3 class="font-bold text-lg text-gray-800" id="qrModalTitle">Asset QR Codes</h3>
                    <p class="qr-modal-subtitle text-xs text-gray-500 mt-0.5">Scan or print the QR codes below. Click a unit photo to preview it larger.</p>
                </div>
                <button id="closeQrModal" class="text-gray-400 hover:text-red-500 bg-white p-1.5 rounded-md shadow-sm border border-gray-200 transition-colors">
                    <i data-lucide="x" class="w-4 h-4"></i>
                </button>
            </div>
            <div class="px-6 py-4 overflow-y-auto custom-scrollbar flex-grow" id="qrModalBody"></div>
            <div class="qr-modal-footer px-6 py-4 border-t border-gray-100 bg-gray-50 rounded-b-xl flex justify-end shrink-0">
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
        'Purchased': {
            label: 'Purchased',
            fields: ['cheque_number', 'voucher_number', 'cash_amount', 'shop_name', 'purchaser_name']
        },
        'Donated': {
            label: 'Donated',
            fields: ['donor_name', 'donation_date']
        },
        'Transferred': {
            label: 'Transferred from other agency',
            fields: ['transfer_from_agency', 'transfer_date']
        },
        'Constructed/Built': {
            label: 'Constructed/Built',
            fields: ['contractor_name', 'contract_amount']
        },
        'Repossessed': {
            label: 'Repossessed',
            fields: ['previous_owner', 'repossession_date']
        },
        'Seized/Forfeited': {
            label: 'Seized/Forfeited',
            fields: ['seizure_authority', 'seizure_date']
        },
        'Received as aid': {
            label: 'Received as aid (Foreign/National)',
            fields: ['aid_agency', 'aid_type', 'aid_date']
        },
        'Leased': {
            label: 'Leased',
            fields: ['lessor_name', 'lease_period_start', 'lease_period_end']
        },
        'Negotiated': {
            label: 'Negotiated Sale',
            fields: ['seller_name', 'negotiation_date']
        }
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
    window.location.href = 'inventory.php' + (qs ? '?' + qs : '');
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

const exportReportBtn = document.getElementById('exportReportBtn');

if (exportReportBtn) {
    exportReportBtn.addEventListener('click', function() {
        const rows = document.querySelectorAll('#assetTable tbody tr.asset-row:not(.hidden)');
        
        if (rows.length === 0) {
            alert('No data to export.');
            return;
        }
        
        const headers = [
            'ARTICLE', 'DESCRIPTION', 'CATEGORY', 'CLUSTER', 
            'LOCATION', 'PROPERTY NUMBER', 'UOM', 'DATE ACQUIRED', 
            'UNIT COST/VALUE', 'ACQUISITION MODE', 'REMARKS', 'STATUS'
        ];
        
        const excelData = [headers];
        
        rows.forEach(row => {
            const rowData = [];
            const cells = row.querySelectorAll('td');
            const indicesToInclude = [2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 14, 15];
            
            indicesToInclude.forEach(idx => {
                if (cells[idx]) {
                    let cellText = '';
                    
                    if (idx === 11) {
                        const modeCell = cells[idx];
                        const modeLabel = modeCell.querySelector('.text-blue-600');
                        if (modeLabel) {
                            cellText = modeLabel.innerText.trim();
                            const details = modeCell.innerText.replace(modeLabel.innerText, '').trim();
                            if (details && details !== 'No additional details') {
                                cellText += ' - ' + details.replace(/\n/g, ', ').replace(/\s+/g, ' ');
                            }
                        } else {
                            cellText = modeCell ? modeCell.innerText.trim().replace(/\n/g, ' ').replace(/\s+/g, ' ') : '';
                        }
                    }
                    else if (idx === 15) {
                        const statusText = cells[idx] ? cells[idx].innerText.trim() : '';
                        const match = statusText.match(/(\d+)\s+Serviceable/);
                        cellText = match ? match[1] + ' Serviceable' : statusText;
                    }
                    else {
                        cellText = cells[idx] ? cells[idx].innerText.trim() : '';
                    }
                    
                    cellText = cellText.replace(/\n/g, ' ').replace(/\r/g, ' ').replace(/\s+/g, ' ').trim();
                    rowData.push(cellText);
                } else {
                    rowData.push('');
                }
            });
            
            excelData.push(rowData);
        });
        
        const ws = XLSX.utils.aoa_to_sheet(excelData);
        
        ws['!cols'] = [
            {wch: 20}, {wch: 35}, {wch: 25}, {wch: 18},
            {wch: 25}, {wch: 20}, {wch: 10}, {wch: 15},
            {wch: 18}, {wch: 45}, {wch: 20}, {wch: 15}
        ];
        
        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, 'Asset Inventory');
        
        const now = new Date();
        const dateStr = now.toISOString().slice(0, 19).replace(/:/g, '-');
        const filename = `asset_inventory_${dateStr}.xlsx`;
        
        XLSX.writeFile(wb, filename);
    });
}

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

        let htmlContent = '<div class="qr-modal-list flex flex-col gap-3">';
        subitems.forEach((item, index) => {
            const qrPayload = item.qr_data || item.code;
            const qrUrl = `https://api.qrserver.com/v1/create-qr-code/?size=120x120&data=${encodeURIComponent(qrPayload)}`;
            const displayName = escHtml(item.description || item.name || '');
            const tagCode = escHtml(formatPropertyId(item.code));
            const meta = itemBrandModel(item);
            const hasUnitPhoto = String(item.photo_url || '').trim() !== '';
            
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

const assetActionForm = document.getElementById('assetActionForm');
const assetActionType = document.getElementById('assetActionType');
const assetActionId = document.getElementById('assetActionId');

const editAssetModalOverlay = document.getElementById('editAssetModalOverlay');
const deleteAssetModalOverlay = document.getElementById('deleteAssetModalOverlay');
const deleteAssetName = document.getElementById('deleteAssetName');
let currentModalAssetId = '';

const editAssetModalSubtitle = document.getElementById('editAssetModalSubtitle');
const editAssetModalPhoto = document.getElementById('editAssetModalPhoto');
const editAssetHeroName = document.getElementById('editAssetHeroName');
const editAssetHeroDetail = document.getElementById('editAssetHeroDetail');
const editAssetHeroProp = document.getElementById('editAssetHeroProp');
const assetPhotoPlaceholder = <?= json_encode(bpis_asset_placeholder_image_url(), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

const modalFields = {
    article: document.getElementById('modalEditArticle'),
    quantity: document.getElementById('modalEditQuantity'),
    description: document.getElementById('modalEditDescription'),
    category: document.getElementById('modalEditCategory'),
    propNum: document.getElementById('modalEditPropNum'),
    uom: document.getElementById('modalEditUom'),
    dateAcquired: document.getElementById('modalEditDateAcquired'),
    unitValue: document.getElementById('modalEditUnitValue'),
    remarks: document.getElementById('modalEditRemarks'),
    status: document.getElementById('modalEditStatus'),
    cluster: document.getElementById('modalEditCluster'),
    location: document.getElementById('modalEditLocation'),
    brand: document.getElementById('modalEditBrand'),
    model: document.getElementById('modalEditModel'),
    auditRemarks: document.getElementById('modalEditAuditRemarks'),
    acquisition: document.getElementById('modalEditAcquisition'),
    cheque: document.getElementById('modalEditCheque'),
    voucher: document.getElementById('modalEditVoucher'),
    cash: document.getElementById('modalEditCash'),
    shop: document.getElementById('modalEditShop'),
    purchaser: document.getElementById('modalEditPurchaser'),
    donor: document.getElementById('modalEditDonor'),
    donationDate: document.getElementById('modalEditDonationDate'),
    transferFrom: document.getElementById('modalEditTransferFrom'),
    transferDate: document.getElementById('modalEditTransferDate'),
    contractor: document.getElementById('modalEditContractor'),
    contractAmount: document.getElementById('modalEditContractAmount'),
    lessor: document.getElementById('modalEditLessor'),
    leaseStart: document.getElementById('modalEditLeaseStart'),
    leaseEnd: document.getElementById('modalEditLeaseEnd'),
    prevOwner: document.getElementById('modalEditPrevOwner'),
    repossessionDate: document.getElementById('modalEditRepossessionDate'),
    seizureAuthority: document.getElementById('modalEditSeizureAuthority'),
    seizureDate: document.getElementById('modalEditSeizureDate'),
    seller: document.getElementById('modalEditSeller'),
    negotiationDate: document.getElementById('modalEditNegotiationDate'),
    aidAgency: document.getElementById('modalEditAidAgency'),
    aidType: document.getElementById('modalEditAidType'),
    aidDate: document.getElementById('modalEditAidDate')
};

function formatDateForInput(value) {
    const d = String(value || '').trim();
    if (!d) return '';
    return d.length >= 10 ? d.slice(0, 10) : d;
}

function toggleEditAcquisitionSections() {
    const mode = modalFields.acquisition ? modalFields.acquisition.value : '';
    
    document.getElementById('editPurchasedSection').classList.add('hidden');
    document.getElementById('editDonatedSection').classList.add('hidden');
    document.getElementById('editTransferredSection').classList.add('hidden');
    document.getElementById('editConstructedSection').classList.add('hidden');
    document.getElementById('editLeasedSection').classList.add('hidden');
    document.getElementById('editRepossessedSection').classList.add('hidden');
    document.getElementById('editSeizedSection').classList.add('hidden');
    document.getElementById('editNegotiatedSection').classList.add('hidden');
    document.getElementById('editAidSection').classList.add('hidden');
    
    if (mode === 'Purchased') {
        document.getElementById('editPurchasedSection').classList.remove('hidden');
    } else if (mode === 'Donated') {
        document.getElementById('editDonatedSection').classList.remove('hidden');
    } else if (mode === 'Transferred') {
        document.getElementById('editTransferredSection').classList.remove('hidden');
    } else if (mode === 'Constructed/Built') {
        document.getElementById('editConstructedSection').classList.remove('hidden');
    } else if (mode === 'Leased') {
        document.getElementById('editLeasedSection').classList.remove('hidden');
    } else if (mode === 'Repossessed') {
        document.getElementById('editRepossessedSection').classList.remove('hidden');
    } else if (mode === 'Seized/Forfeited') {
        document.getElementById('editSeizedSection').classList.remove('hidden');
    } else if (mode === 'Negotiated') {
        document.getElementById('editNegotiatedSection').classList.remove('hidden');
    } else if (mode === 'Received as aid') {
        document.getElementById('editAidSection').classList.remove('hidden');
    }
}

function populateAcquisitionDropdownFromDatabase(acquisitionMode) {
    const acquisitionSelect = modalFields.acquisition;
    if (!acquisitionSelect) return;
    
    acquisitionSelect.innerHTML = '';
    
    if (!acquisitionMode) {
        const option = document.createElement('option');
        option.value = '';
        option.textContent = 'No acquisition mode set';
        option.selected = true;
        acquisitionSelect.appendChild(option);
        acquisitionSelect.disabled = true;
        acquisitionSelect.classList.add('bpis-disabled-input', 'bg-gray-100', 'cursor-not-allowed');
        return;
    }
    
    const modeLabels = {
        'Purchased': 'Purchased',
        'Donated': 'Donated',
        'Transferred': 'Transferred from other agency',
        'Constructed/Built': 'Constructed/Built',
        'Repossessed': 'Repossessed',
        'Seized/Forfeited': 'Seized/Forfeited',
        'Received as aid': 'Received as aid (Foreign/National)',
        'Leased': 'Leased',
        'Negotiated': 'Negotiated Sale'
    };
    
    const modeLabel = modeLabels[acquisitionMode] || acquisitionMode;
    
    const option = document.createElement('option');
    option.value = acquisitionMode;
    option.textContent = modeLabel;
    option.selected = true;
    acquisitionSelect.appendChild(option);
    
    acquisitionSelect.disabled = true;
    acquisitionSelect.classList.add('bpis-disabled-input', 'bg-gray-100', 'cursor-not-allowed');
}

function populateEditModal(data) {
    if (!data || !data.id) return;
    currentModalAssetId = String(data.id);

    modalFields.article.value = data.article || '';
    modalFields.description.value = data.description || '';
    modalFields.quantity.value = String(data.quantity || 1);
    modalFields.category.value = data.category || '';
    modalFields.propNum.value = data.property_number || '';
    modalFields.uom.value = data.unit_measure || 'pc';
    modalFields.dateAcquired.value = formatDateForInput(data.date_acquired);
    modalFields.unitValue.value = String(data.unit_value ?? '0');
    modalFields.remarks.value = normalizeRemarksForSelect(data.remarks || 'SERVICEABLE');
    syncConditionSelectStyle();
    if (modalFields.status) modalFields.status.value = data.status || 'Available';
    if (modalFields.cluster) modalFields.cluster.value = data.asset_cluster || 'Movable Assets';
    if (modalFields.location) modalFields.location.value = data.location || '';
    if (modalFields.brand) modalFields.brand.value = data.brand || '';
    if (modalFields.model) modalFields.model.value = data.model || '';
    if (modalFields.auditRemarks) modalFields.auditRemarks.value = data.audit_remarks || '';
    
    const acquisitionMode = data.acquisition_mode || '';
    
    populateAcquisitionDropdownFromDatabase(acquisitionMode);
    
    if (acquisitionMode === 'Purchased') {
        if (modalFields.cheque) modalFields.cheque.value = data.cheque_number || '';
        if (modalFields.voucher) modalFields.voucher.value = data.voucher_number || '';
        if (modalFields.cash) modalFields.cash.value = String(data.cash_amount ?? '0');
        if (modalFields.shop) modalFields.shop.value = data.shop_name || '';
        if (modalFields.purchaser) modalFields.purchaser.value = data.purchaser_name || '';
    } else if (acquisitionMode === 'Donated') {
        if (modalFields.donor) modalFields.donor.value = data.donor_name || '';
        if (modalFields.donationDate) modalFields.donationDate.value = formatDateForInput(data.donation_date);
    } else if (acquisitionMode === 'Transferred') {
        if (modalFields.transferFrom) modalFields.transferFrom.value = data.transfer_from_agency || '';
        if (modalFields.transferDate) modalFields.transferDate.value = formatDateForInput(data.transfer_date);
    } else if (acquisitionMode === 'Constructed/Built') {
        if (modalFields.contractor) modalFields.contractor.value = data.contractor_name || '';
        if (modalFields.contractAmount) modalFields.contractAmount.value = String(data.contract_amount ?? '0');
    } else if (acquisitionMode === 'Leased') {
        if (modalFields.lessor) modalFields.lessor.value = data.lessor_name || '';
        if (modalFields.leaseStart) modalFields.leaseStart.value = formatDateForInput(data.lease_period_start);
        if (modalFields.leaseEnd) modalFields.leaseEnd.value = formatDateForInput(data.lease_period_end);
    } else if (acquisitionMode === 'Repossessed') {
        if (modalFields.prevOwner) modalFields.prevOwner.value = data.previous_owner || '';
        if (modalFields.repossessionDate) modalFields.repossessionDate.value = formatDateForInput(data.repossession_date);
    } else if (acquisitionMode === 'Seized/Forfeited') {
        if (modalFields.seizureAuthority) modalFields.seizureAuthority.value = data.seizure_authority || '';
        if (modalFields.seizureDate) modalFields.seizureDate.value = formatDateForInput(data.seizure_date);
    } else if (acquisitionMode === 'Negotiated') {
        if (modalFields.seller) modalFields.seller.value = data.seller_name || '';
        if (modalFields.negotiationDate) modalFields.negotiationDate.value = formatDateForInput(data.negotiation_date);
    } else if (acquisitionMode === 'Received as aid') {
        if (modalFields.aidAgency) modalFields.aidAgency.value = data.aid_agency || '';
        if (modalFields.aidType) modalFields.aidType.value = data.aid_type || '';
        if (modalFields.aidDate) modalFields.aidDate.value = formatDateForInput(data.aid_date);
    }

    const displayName = String(data.description || data.article || 'Asset').trim();
    const article = String(data.article || '').trim();
    const qty = String(data.quantity || '1').trim();
    const uom = String(data.unit_measure || 'pc').trim();
    const propNum = String(data.property_number || '').trim();
    let photoUrl = String(data.photo_url || assetPhotoPlaceholder).trim();
    
    if (photoUrl && !photoUrl.startsWith(BPIS_BASE_PREFIX) && !photoUrl.startsWith('http')) {
        photoUrl = photoUrl.replace(/^\/+/, '');
        if (photoUrl.startsWith('BPIS/')) {
            photoUrl = '/' + photoUrl;
        } else {
            photoUrl = BPIS_BASE_PREFIX + photoUrl;
        }
    }

    if (editAssetModalSubtitle) editAssetModalSubtitle.textContent = 'Editing: ' + displayName;
    if (editAssetModalPhoto) {
        editAssetModalPhoto.src = photoUrl;
        editAssetModalPhoto.alt = displayName;
    }
    if (editAssetHeroName) editAssetHeroName.textContent = displayName;
    if (editAssetHeroDetail) {
        const parts = [];
        if (article && article !== displayName) parts.push('Article: ' + article);
        parts.push(qty + ' ' + uom);
        editAssetHeroDetail.textContent = parts.join(' · ');
    }
    if (editAssetHeroProp) editAssetHeroProp.textContent = propNum ? 'Property no. ' + propNum : '';

    renderUnitConditions(data.units_edit || [], qty);
    if (modalEditBulkCondition) {
        modalEditBulkCondition.value = normalizeRemarksForSelect(data.remarks || 'SERVICEABLE');
    }
    
    toggleEditAcquisitionSections();
}

function assetRowDisplayName(row) {
    const desc = String(row.dataset.description || '').trim();
    const art = String(row.dataset.article || '').trim();
    return desc || art || 'Unnamed asset';
}

function syncConditionSelectStyle() {
    if (!modalFields.remarks) return;
    modalFields.remarks.setAttribute('data-condition', modalFields.remarks.value);
}

if (modalFields.remarks) {
    modalFields.remarks.addEventListener('change', syncConditionSelectStyle);
}

function normalizeRemarksForSelect(raw) {
    const r = String(raw || '').toUpperCase().trim();
    if (r === 'UNSERVICEABLE' || r === 'DISPOSED' || r === 'DISPOSE' || r.indexOf('UNSERVICEABLE') !== -1) {
        return 'UNSERVICEABLE/DISPOSE';
    }
    if (r === 'UNDER REPAIR') {
        return 'UNDER REPAIR';
    }
    return 'SERVICEABLE';
}

const editUnitConditionsSection = document.getElementById('editUnitConditionsSection');
const editUnitConditionsList = document.getElementById('editUnitConditionsList');
const editOverallConditionWrap = document.getElementById('editOverallConditionWrap');
const editBulkConditionWrap = document.getElementById('editBulkConditionWrap');
const modalEditBulkCondition = document.getElementById('modalEditBulkCondition');
const applyBulkConditionBtn = document.getElementById('applyBulkConditionBtn');
const editUnitConditionFields = document.getElementById('editUnitConditionFields');
const CONDITION_OPTIONS = ['SERVICEABLE', 'UNDER REPAIR', 'UNSERVICEABLE/DISPOSE'];

function conditionSelectHtml(selected, extraClass) {
    const cls = 'asset-modal-input bpis-edit-asset-input bpis-edit-asset-input--condition edit-unit-condition-select ' + (extraClass || '');
    let html = '<select class="' + cls + '">';
    CONDITION_OPTIONS.forEach(function (opt) {
        const sel = opt === selected ? ' selected' : '';
        html += '<option value="' + opt + '"' + sel + '>' + opt + '</option>';
    });
    html += '</select>';
    return html;
}

function renderUnitConditions(units, quantity) {
    const qty = parseInt(quantity, 10) || 1;
    const list = Array.isArray(units) ? units : [];
    const multi = qty > 1;

    if (editOverallConditionWrap) {
        editOverallConditionWrap.classList.toggle('hidden', multi);
    }
    if (editBulkConditionWrap) {
        editBulkConditionWrap.classList.toggle('hidden', !multi);
    }
    if (editUnitConditionsSection) {
        editUnitConditionsSection.classList.toggle('hidden', !multi);
    }
    if (!editUnitConditionsList) {
        return;
    }
    editUnitConditionsList.innerHTML = '';

    if (!multi) {
        return;
    }

    list.forEach(function (unit) {
        const tag = String(unit.unit_tag || '').trim();
        const unitId = parseInt(unit.id, 10) || 0;
        const cond = normalizeRemarksForSelect(unit.unit_condition || 'SERVICEABLE');
        const row = document.createElement('div');
        row.className = 'bpis-edit-unit-condition-row';
        row.innerHTML =
            '<span class="bpis-edit-unit-condition-tag" title="' + tag.replace(/"/g, '&quot;') + '">' + tag.replace(/</g, '&lt;') + '</span>' +
            conditionSelectHtml(cond, '');
        const sel = row.querySelector('select');
        if (sel) {
            sel.dataset.unitId = String(unitId);
            sel.dataset.unitTag = tag;
            sel.addEventListener('change', syncConditionSelectStyle);
        }
        editUnitConditionsList.appendChild(row);
    });
}

function syncUnitConditionHiddenFields() {
    if (!editUnitConditionFields) return;
    editUnitConditionFields.innerHTML = '';
    const qty = parseInt(modalFields.quantity.value, 10) || 1;
    if (qty <= 1) {
        return;
    }
    document.querySelectorAll('#editUnitConditionsList .edit-unit-condition-select').forEach(function (sel) {
        const id = document.createElement('input');
        id.type = 'hidden';
        id.name = 'edit_unit_id[]';
        id.value = sel.dataset.unitId || '0';
        const tag = document.createElement('input');
        tag.type = 'hidden';
        tag.name = 'edit_unit_tag[]';
        tag.value = sel.dataset.unitTag || '';
        const cond = document.createElement('input');
        cond.type = 'hidden';
        cond.name = 'edit_unit_condition[]';
        cond.value = sel.value;
        editUnitConditionFields.appendChild(id);
        editUnitConditionFields.appendChild(tag);
        editUnitConditionFields.appendChild(cond);
    });
}

if (applyBulkConditionBtn && modalEditBulkCondition) {
    applyBulkConditionBtn.addEventListener('click', function () {
        const val = modalEditBulkCondition.value;
        document.querySelectorAll('#editUnitConditionsList .edit-unit-condition-select').forEach(function (sel) {
            sel.value = val;
            sel.setAttribute('data-condition', val);
        });
    });
}

if (modalFields.quantity) {
    modalFields.quantity.addEventListener('change', function () {
        let units = [];
        try {
            const row = document.querySelector('.asset-row[data-asset-id="' + currentModalAssetId + '"]');
            const data = row ? JSON.parse(row.getAttribute('data-asset-edit') || '{}') : {};
            units = data.units_edit || [];
        } catch (e) {
            units = [];
        }
        renderUnitConditions(units, modalFields.quantity.value);
    });
}

function openOverlay(el) { if (el) el.style.display = 'flex'; }
function closeOverlay(el) { if (el) el.style.display = 'none'; }

document.querySelectorAll('.edit-asset-btn').forEach(btn => {
    btn.addEventListener('click', function () {
        const row = this.closest('.asset-row');
        if (!row) return;
        let data = null;
        try {
            data = JSON.parse(row.getAttribute('data-asset-edit') || '{}');
        } catch (err) {
            data = null;
        }
        if (!data || !data.id) return;
        populateEditModal(data);
        openOverlay(editAssetModalOverlay);
    });
});

document.getElementById('saveEditAssetBtn').addEventListener('click', function () {
    if (!currentModalAssetId) return;
    const quantityNum = parseInt(modalFields.quantity.value, 10);
    const unitValueNum = parseFloat(modalFields.unitValue.value);
    if (!modalFields.article.value.trim()) return alert('Article is required.');
    if (Number.isNaN(quantityNum) || quantityNum < 1) return alert('Quantity must be at least 1.');
    if (!modalFields.category.value.trim()) return alert('Category is required.');
    if (!modalFields.propNum.value.trim()) return alert('Property Number is required.');
    if (!modalFields.uom.value.trim()) return alert('Unit of Measure is required.');
    if (!modalFields.dateAcquired.value.trim()) return alert('Date acquired is required.');
    if (Number.isNaN(unitValueNum) || unitValueNum < 0) return alert('Unit Value must be a valid number.');
    
    assetActionType.value = 'edit_asset';
    assetActionId.value = currentModalAssetId;
    document.getElementById('editArticleField').value = modalFields.article.value.trim();
    document.getElementById('editQuantityField').value = String(quantityNum);
    document.getElementById('editDescriptionField').value = modalFields.description.value.trim();
    document.getElementById('editCategoryField').value = modalFields.category.value.trim();
    document.getElementById('editPropNumField').value = modalFields.propNum.value.trim();
    document.getElementById('editUomField').value = modalFields.uom.value.trim();
    document.getElementById('editDateAcquiredField').value = modalFields.dateAcquired.value.trim();
    document.getElementById('editUnitValueField').value = String(unitValueNum);
    const qty = parseInt(modalFields.quantity.value, 10) || 1;
    if (qty > 1) {
        syncUnitConditionHiddenFields();
        const conditions = [];
        document.querySelectorAll('#editUnitConditionsList .edit-unit-condition-select').forEach(function (sel) {
            conditions.push(sel.value);
        });
        document.getElementById('editRemarksField').value = deriveWorstCondition(conditions);
    } else {
        if (editUnitConditionFields) editUnitConditionFields.innerHTML = '';
        document.getElementById('editRemarksField').value = modalFields.remarks.value;
    }
    document.getElementById('editAuditRemarksField').value = modalFields.auditRemarks ? modalFields.auditRemarks.value.trim() : '';
    document.getElementById('editLocationField').value = modalFields.location ? modalFields.location.value.trim() : '';
    document.getElementById('editAssetClusterField').value = modalFields.cluster ? modalFields.cluster.value : 'Movable Assets';
    document.getElementById('editStatusField').value = modalFields.status ? modalFields.status.value : 'Available';
    document.getElementById('editBrandField').value = modalFields.brand ? modalFields.brand.value.trim() : '';
    document.getElementById('editModelField').value = modalFields.model ? modalFields.model.value.trim() : '';
    document.getElementById('editAcquisitionModeField').value = modalFields.acquisition ? modalFields.acquisition.value : '';
    document.getElementById('editChequeField').value = modalFields.cheque ? modalFields.cheque.value.trim() : '';
    document.getElementById('editVoucherField').value = modalFields.voucher ? modalFields.voucher.value.trim() : '';
    document.getElementById('editCashField').value = modalFields.cash ? modalFields.cash.value : '0';
    document.getElementById('editShopField').value = modalFields.shop ? modalFields.shop.value.trim() : '';
    document.getElementById('editPurchaserField').value = modalFields.purchaser ? modalFields.purchaser.value.trim() : '';
    
    document.getElementById('editDonorField').value = modalFields.donor ? modalFields.donor.value.trim() : '';
    document.getElementById('editDonationDateField').value = modalFields.donationDate ? modalFields.donationDate.value.trim() : '';
    document.getElementById('editTransferFromField').value = modalFields.transferFrom ? modalFields.transferFrom.value.trim() : '';
    document.getElementById('editTransferDateField').value = modalFields.transferDate ? modalFields.transferDate.value.trim() : '';
    document.getElementById('editContractorField').value = modalFields.contractor ? modalFields.contractor.value.trim() : '';
    document.getElementById('editContractAmountField').value = modalFields.contractAmount ? modalFields.contractAmount.value : '0';
    document.getElementById('editLessorField').value = modalFields.lessor ? modalFields.lessor.value.trim() : '';
    document.getElementById('editLeaseStartField').value = modalFields.leaseStart ? modalFields.leaseStart.value.trim() : '';
    document.getElementById('editLeaseEndField').value = modalFields.leaseEnd ? modalFields.leaseEnd.value.trim() : '';
    document.getElementById('editPrevOwnerField').value = modalFields.prevOwner ? modalFields.prevOwner.value.trim() : '';
    document.getElementById('editRepossessionDateField').value = modalFields.repossessionDate ? modalFields.repossessionDate.value.trim() : '';
    document.getElementById('editSeizureAuthorityField').value = modalFields.seizureAuthority ? modalFields.seizureAuthority.value.trim() : '';
    document.getElementById('editSeizureDateField').value = modalFields.seizureDate ? modalFields.seizureDate.value.trim() : '';
    document.getElementById('editSellerField').value = modalFields.seller ? modalFields.seller.value.trim() : '';
    document.getElementById('editNegotiationDateField').value = modalFields.negotiationDate ? modalFields.negotiationDate.value.trim() : '';
    document.getElementById('editAidAgencyField').value = modalFields.aidAgency ? modalFields.aidAgency.value.trim() : '';
    document.getElementById('editAidTypeField').value = modalFields.aidType ? modalFields.aidType.value.trim() : '';
    document.getElementById('editAidDateField').value = modalFields.aidDate ? modalFields.aidDate.value.trim() : '';
    
    assetActionForm.submit();
});

function deriveWorstCondition(conditions) {
    const list = (conditions || []).map(normalizeRemarksForSelect);
    if (list.indexOf('UNSERVICEABLE/DISPOSE') !== -1) return 'UNSERVICEABLE/DISPOSE';
    if (list.indexOf('UNDER REPAIR') !== -1) return 'UNDER REPAIR';
    return 'SERVICEABLE';
}

document.getElementById('confirmDeleteAssetBtn').addEventListener('click', function () {
    if (!currentModalAssetId) return;
    assetActionType.value = 'delete_asset';
    assetActionId.value = currentModalAssetId;
    assetActionForm.submit();
});

document.getElementById('closeEditAssetModal').addEventListener('click', () => closeOverlay(editAssetModalOverlay));
document.getElementById('cancelEditAssetBtn').addEventListener('click', () => closeOverlay(editAssetModalOverlay));
document.getElementById('closeDeleteAssetModal').addEventListener('click', () => closeOverlay(deleteAssetModalOverlay));
document.getElementById('cancelDeleteAssetBtn').addEventListener('click', () => closeOverlay(deleteAssetModalOverlay));

editAssetModalOverlay.addEventListener('click', function (e) {
    if (e.target === editAssetModalOverlay) closeOverlay(editAssetModalOverlay);
});
deleteAssetModalOverlay.addEventListener('click', function (e) {
    if (e.target === deleteAssetModalOverlay) closeOverlay(deleteAssetModalOverlay);
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

function closeQrModal() {
    qrModal.classList.add('hidden');
    document.body.classList.remove('qr-modal-open');
}

document.getElementById('closeQrModal').addEventListener('click', closeQrModal);
qrModal.addEventListener('click', function (event) {
    if (event.target === qrModal) {
        closeQrModal();
    }
});
document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape' && !qrModal.classList.contains('hidden')) {
        closeQrModal();
    }
});

lucide.createIcons();
</script>
<script src="../realtime_notifications.js"></script>
<script src="../js/treasurer_mobile_sidebar.js"></script>
<script>
(function () {
    const m = new URLSearchParams(window.location.search).get('msg');
    if (m === 'unlink_failed') {
        alert('Cannot unlink borrower records from this asset. Please contact admin.');
    } else if (m === 'delete_failed') {
        alert('Unable to delete asset right now. Please try again.');
    }
})();
</script>
</body>
</html>
