<?php
session_start();

include __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth_helpers.php';
require_once __DIR__ . '/../config/coa_labels.php';
require_once __DIR__ . '/../config/upload_helpers.php';
require_once __DIR__ . '/../config/asset_units_helpers.php';
require_once __DIR__ . '/../config/asset_list_helpers.php';
require_once __DIR__ . '/../config/notification_helpers.php';

bpis_require_login(['Treasurer', 'Barangay Captain']);

$bpis_treasurer_nav_active = 'register';
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

$success_msg = false;
$registration_errors = [];

function bpis_reg_required_mark(): string
{
    // Colour comes from .reg-required (--danger-color), which adapts per theme.
    // An inline !important here outranked the stylesheet and left the marker at
    // #dc2626 — only 3.46:1 on the dark form surface.
    return '<span class="reg-required" aria-hidden="true">*</span>';
}

function bpis_get_acquisition_modes() {
    return [
        'Purchased' => [
            'label' => 'Purchased',
            'fields' => ['cheque_number', 'voucher_number', 'cash_amount', 'shop_name', 'purchaser_name', 'purchase_proof', 'service_invoice']
        ],
        'Donated' => [
            'label' => 'Donated',
            'fields' => ['donor_name', 'donation_date', 'deed_of_donation']
        ],
        'Transferred' => [
            'label' => 'Transferred from other agency',
            'fields' => ['transfer_from_agency', 'transfer_date', 'transfer_document']
        ],
        'Constructed/Built' => [
            'label' => 'Constructed/Built',
            'fields' => ['contractor_name', 'contract_amount', 'project_contract']
        ],
        'Repossessed' => [
            'label' => 'Repossessed',
            'fields' => ['previous_owner', 'repossession_date', 'court_order']
        ],
        'Seized/Forfeited' => [
            'label' => 'Seized/Forfeited',
            'fields' => ['seizure_authority', 'seizure_date', 'seizure_document']
        ],
        'Received as aid' => [
            'label' => 'Received as aid (Foreign/National)',
            'fields' => ['aid_agency', 'aid_type', 'aid_date', 'aid_document']
        ],
        'Leased' => [
            'label' => 'Leased',
            'fields' => ['lessor_name', 'lease_period_start', 'lease_period_end', 'lease_contract']
        ],
        'Negotiated' => [
            'label' => 'Negotiated Sale',
            'fields' => ['seller_name', 'negotiation_date', 'sales_agreement']
        ]
    ];
}

function bpis_save_acquisition_details($conn, $asset_id, $acquisition_mode, $data) {
    $table_check = mysqli_query($conn, "SHOW TABLES LIKE 'asset_acquisition_details'");
    if (mysqli_num_rows($table_check) == 0) {
        return false;
    }
    
    $acquisition_modes = bpis_get_acquisition_modes();
    $mode_fields = $acquisition_modes[$acquisition_mode]['fields'] ?? [];
    
    $columns = ['asset_id', 'acquisition_mode'];
    $values = [$asset_id, $acquisition_mode];
    $types = ['i', 's'];
    
    foreach ($mode_fields as $field) {
        switch($field) {
            case 'cheque_number':
                if (!empty($data['cheque_number']) && $data['cheque_number'] !== 'N/A') {
                    $columns[] = 'cheque_number';
                    $values[] = $data['cheque_number'];
                    $types[] = 's';
                }
                break;
            case 'voucher_number':
                if (!empty($data['voucher_number']) && $data['voucher_number'] !== 'N/A') {
                    $columns[] = 'voucher_number';
                    $values[] = $data['voucher_number'];
                    $types[] = 's';
                }
                break;
            case 'cash_amount':
                if (isset($data['cash_amount']) && $data['cash_amount'] > 0) {
                    $columns[] = 'cash_amount';
                    $values[] = (float)$data['cash_amount'];
                    $types[] = 'd';
                }
                break;
            case 'shop_name':
                if (!empty($data['shop_name']) && $data['shop_name'] !== 'N/A') {
                    $columns[] = 'shop_name';
                    $values[] = $data['shop_name'];
                    $types[] = 's';
                }
                break;
            case 'purchaser_name':
                if (!empty($data['purchaser_name']) && $data['purchaser_name'] !== 'N/A') {
                    $columns[] = 'purchaser_name';
                    $values[] = $data['purchaser_name'];
                    $types[] = 's';
                }
                break;
            case 'purchase_proof':
                if (!empty($data['purchase_proof_path'])) {
                    $columns[] = 'purchase_proof_path';
                    $values[] = $data['purchase_proof_path'];
                    $types[] = 's';
                }
                break;
            case 'service_invoice':
                if (!empty($data['service_invoice_path'])) {
                    $columns[] = 'service_invoice_path';
                    $values[] = $data['service_invoice_path'];
                    $types[] = 's';
                }
                break;
            case 'donor_name':
                if (!empty($data['donor_name']) && $data['donor_name'] !== 'N/A') {
                    $columns[] = 'donor_name';
                    $values[] = $data['donor_name'];
                    $types[] = 's';
                }
                break;
            case 'donation_date':
                if (!empty($data['donation_date'])) {
                    $columns[] = 'donation_date';
                    $values[] = $data['donation_date'];
                    $types[] = 's';
                }
                break;
            case 'deed_of_donation':
                if (!empty($data['deed_of_donation_path'])) {
                    $columns[] = 'deed_of_donation_path';
                    $values[] = $data['deed_of_donation_path'];
                    $types[] = 's';
                }
                break;
            case 'transfer_from_agency':
                if (!empty($data['transfer_from_agency']) && $data['transfer_from_agency'] !== 'N/A') {
                    $columns[] = 'transfer_from_agency';
                    $values[] = $data['transfer_from_agency'];
                    $types[] = 's';
                }
                break;
            case 'transfer_date':
                if (!empty($data['transfer_date'])) {
                    $columns[] = 'transfer_date';
                    $values[] = $data['transfer_date'];
                    $types[] = 's';
                }
                break;
            case 'transfer_document':
                if (!empty($data['transfer_document_path'])) {
                    $columns[] = 'transfer_document_path';
                    $values[] = $data['transfer_document_path'];
                    $types[] = 's';
                }
                break;
            case 'contractor_name':
                if (!empty($data['contractor_name']) && $data['contractor_name'] !== 'N/A') {
                    $columns[] = 'contractor_name';
                    $values[] = $data['contractor_name'];
                    $types[] = 's';
                }
                break;
            case 'contract_amount':
                if (isset($data['contract_amount']) && $data['contract_amount'] > 0) {
                    $columns[] = 'contract_amount';
                    $values[] = (float)$data['contract_amount'];
                    $types[] = 'd';
                }
                break;
            case 'project_contract':
                if (!empty($data['project_contract_path'])) {
                    $columns[] = 'project_contract_path';
                    $values[] = $data['project_contract_path'];
                    $types[] = 's';
                }
                break;
            case 'lessor_name':
                if (!empty($data['lessor_name']) && $data['lessor_name'] !== 'N/A') {
                    $columns[] = 'lessor_name';
                    $values[] = $data['lessor_name'];
                    $types[] = 's';
                }
                break;
            case 'lease_period_start':
                if (!empty($data['lease_period_start'])) {
                    $columns[] = 'lease_period_start';
                    $values[] = $data['lease_period_start'];
                    $types[] = 's';
                }
                break;
            case 'lease_period_end':
                if (!empty($data['lease_period_end'])) {
                    $columns[] = 'lease_period_end';
                    $values[] = $data['lease_period_end'];
                    $types[] = 's';
                }
                break;
            case 'lease_contract':
                if (!empty($data['lease_contract_path'])) {
                    $columns[] = 'lease_contract_path';
                    $values[] = $data['lease_contract_path'];
                    $types[] = 's';
                }
                break;
            case 'previous_owner':
                if (!empty($data['previous_owner']) && $data['previous_owner'] !== 'N/A') {
                    $columns[] = 'previous_owner';
                    $values[] = $data['previous_owner'];
                    $types[] = 's';
                }
                break;
            case 'repossession_date':
                if (!empty($data['repossession_date'])) {
                    $columns[] = 'repossession_date';
                    $values[] = $data['repossession_date'];
                    $types[] = 's';
                }
                break;
            case 'court_order':
                if (!empty($data['court_order_path'])) {
                    $columns[] = 'court_order_path';
                    $values[] = $data['court_order_path'];
                    $types[] = 's';
                }
                break;
            case 'seizure_authority':
                if (!empty($data['seizure_authority']) && $data['seizure_authority'] !== 'N/A') {
                    $columns[] = 'seizure_authority';
                    $values[] = $data['seizure_authority'];
                    $types[] = 's';
                }
                break;
            case 'seizure_date':
                if (!empty($data['seizure_date'])) {
                    $columns[] = 'seizure_date';
                    $values[] = $data['seizure_date'];
                    $types[] = 's';
                }
                break;
            case 'seizure_document':
                if (!empty($data['seizure_document_path'])) {
                    $columns[] = 'seizure_document_path';
                    $values[] = $data['seizure_document_path'];
                    $types[] = 's';
                }
                break;
            case 'seller_name':
                if (!empty($data['seller_name']) && $data['seller_name'] !== 'N/A') {
                    $columns[] = 'seller_name';
                    $values[] = $data['seller_name'];
                    $types[] = 's';
                }
                break;
            case 'negotiation_date':
                if (!empty($data['negotiation_date'])) {
                    $columns[] = 'negotiation_date';
                    $values[] = $data['negotiation_date'];
                    $types[] = 's';
                }
                break;
            case 'sales_agreement':
                if (!empty($data['sales_agreement_path'])) {
                    $columns[] = 'sales_agreement_path';
                    $values[] = $data['sales_agreement_path'];
                    $types[] = 's';
                }
                break;
            case 'aid_agency':
                if (!empty($data['aid_agency']) && $data['aid_agency'] !== 'N/A') {
                    $columns[] = 'aid_agency';
                    $values[] = $data['aid_agency'];
                    $types[] = 's';
                }
                break;
            case 'aid_type':
                if (!empty($data['aid_type']) && $data['aid_type'] !== 'N/A') {
                    $columns[] = 'aid_type';
                    $values[] = $data['aid_type'];
                    $types[] = 's';
                }
                break;
            case 'aid_date':
                if (!empty($data['aid_date'])) {
                    $columns[] = 'aid_date';
                    $values[] = $data['aid_date'];
                    $types[] = 's';
                }
                break;
            case 'aid_document':
                if (!empty($data['aid_document_path'])) {
                    $columns[] = 'aid_document_path';
                    $values[] = $data['aid_document_path'];
                    $types[] = 's';
                }
                break;
        }
    }
    
    if (count($columns) <= 2) {
        $columns = ['asset_id', 'acquisition_mode'];
        $values = [$asset_id, $acquisition_mode];
        $types = ['i', 's'];
    }
    
    $placeholders = array_fill(0, count($columns), '?');
    $sql = "INSERT INTO asset_acquisition_details (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
    
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        error_log("Failed to prepare statement: " . mysqli_error($conn));
        return false;
    }
    
    $types_str = implode('', $types);
    mysqli_stmt_bind_param($stmt, $types_str, ...$values);
    
    $result = mysqli_stmt_execute($stmt);
    
    if (!$result) {
        error_log("Failed to execute acquisition details insert: " . mysqli_stmt_error($stmt));
        error_log("SQL: " . $sql);
    }
    
    mysqli_stmt_close($stmt);
    return $result;
}

function bpis_generate_property_number($category, $year = null) {
    if (!$year) {
        $year = date('Y');
    }
    
    $category_codes = [
        'Land & Improvements' => 'LND',
        'Buildings & Structures' => 'BLD',
        'Machinery' => 'MCH',
        'Equipment' => 'EQP',
        'IT Equipment' => 'ITE',
        'Office Equipment' => 'OFE',
        'Furniture & Fixtures' => 'FNF',
        'Motor Vehicles / Delivery Truck' => 'MVH',
        'Tools' => 'TLS'
    ];
    
    $code = $category_codes[$category] ?? 'AST';
    
    global $conn;
    $seq_query = "SELECT COUNT(*) as count FROM asset WHERE category = '$category' AND YEAR(date_acquired) = $year";
    $seq_result = mysqli_query($conn, $seq_query);
    $seq_row = mysqli_fetch_assoc($seq_result);
    $sequence = str_pad(($seq_row['count'] + 1), 3, '0', STR_PAD_LEFT);
    
    return "PROP-{$year}-{$code}-{$sequence}";
}

function bpis_get_unit_measures_by_category($category) {
    $unit_map = [
        'Land & Improvements' => ['sqm' => 'Square Meter', 'ha' => 'Hectare', 'lot' => 'Lot'],
        'Buildings & Structures' => ['sqm' => 'Square Meter', 'unit' => 'Unit'],
        'Machinery' => ['unit' => 'Unit', 'set' => 'Set'],
        'Equipment' => ['unit' => 'Unit', 'set' => 'Set'],
        'IT Equipment' => ['pc' => 'Piece', 'unit' => 'Unit', 'set' => 'Set'],
        'Office Equipment' => ['pc' => 'Piece', 'unit' => 'Unit', 'set' => 'Set'],
        'Furniture & Fixtures' => ['pc' => 'Piece', 'set' => 'Set'],
        'Motor Vehicles / Delivery Truck' => ['unit' => 'Unit', 'vehicle' => 'Vehicle'],
        'Tools' => ['pc' => 'Piece', 'set' => 'Set']
    ];
    
    return $unit_map[$category] ?? ['pc' => 'Piece', 'unit' => 'Unit', 'set' => 'Set'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['article'])) {
    $article_raw = trim((string) ($_POST['article'] ?? ''));
    $description_raw = trim((string) ($_POST['description'] ?? ''));
    $category_raw = trim((string) ($_POST['category'] ?? ''));
    $property_raw = trim((string) ($_POST['property_number'] ?? ''));
    $uom_raw = trim((string) ($_POST['unit_measure'] ?? ''));
    $date_raw = trim((string) ($_POST['date_acquired'] ?? ''));
    $location_raw = trim((string) ($_POST['location'] ?? ''));
    $brand_raw = trim((string) ($_POST['brand'] ?? ''));
    $model_raw = trim((string) ($_POST['model'] ?? ''));
    $audit_raw = trim((string) ($_POST['audit_remarks'] ?? ''));
    $remarks_raw = trim((string) ($_POST['remarks'] ?? ''));
    $cluster_raw = trim((string) ($_POST['asset_cluster'] ?? ''));
    $acq_raw = trim((string) ($_POST['acquisition_mode'] ?? ''));
    $status_raw = trim((string) ($_POST['status'] ?? ''));
    $quantity_check = isset($_POST['quantity']) ? (int) $_POST['quantity'] : 0;
    $unit_value_raw = isset($_POST['unit_value']) ? trim((string) $_POST['unit_value']) : '';
    $accountable_officer_raw = trim((string) ($_POST['accountable_officer'] ?? ''));
    $has_asset_photo = !empty($_FILES['asset_photo']['tmp_name']) && is_uploaded_file($_FILES['asset_photo']['tmp_name']);
    
    $camera_photo = isset($_POST['camera_photo']) ? trim((string) $_POST['camera_photo']) : '';
    if ($camera_photo !== '' && !$has_asset_photo) {
        $image_parts = explode(';base64,', $camera_photo);
        if (count($image_parts) >= 2) {
            $image_base64 = base64_decode($image_parts[1]);
            $finfo = finfo_open();
            $mime_type = finfo_buffer($finfo, $image_base64, FILEINFO_MIME_TYPE);
            finfo_close($finfo);
            
            $extension = 'jpg';
            if ($mime_type === 'image/png') $extension = 'png';
            elseif ($mime_type === 'image/webp') $extension = 'webp';
            elseif ($mime_type === 'image/jpeg') $extension = 'jpg';
            
            $upload_dir = __DIR__ . '/../uploads/assets/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $filename = time() . '_camera_' . bin2hex(random_bytes(8)) . '.' . $extension;
            $filepath = $upload_dir . $filename;
            
            if (file_put_contents($filepath, $image_base64)) {
                $asset_photo_path = 'uploads/assets/' . $filename;
                $has_asset_photo = true;
            }
        }
    }
    
    $unit_camera_photos = isset($_POST['unit_camera_photo']) ? $_POST['unit_camera_photo'] : [];
    $unit_photo_paths = [];
    
    $uploaded_unit_files = isset($_FILES['unit_photo']) ? $_FILES['unit_photo'] : null;
    
    if (!empty($unit_camera_photos)) {
        foreach ($unit_camera_photos as $index => $camera_photo_data) {
            if (!empty($camera_photo_data)) {
                $image_parts = explode(';base64,', $camera_photo_data);
                if (count($image_parts) >= 2) {
                    $image_base64 = base64_decode($image_parts[1]);
                    $finfo = finfo_open();
                    $mime_type = finfo_buffer($finfo, $image_base64, FILEINFO_MIME_TYPE);
                    finfo_close($finfo);
                    
                    $extension = 'jpg';
                    if ($mime_type === 'image/png') $extension = 'png';
                    elseif ($mime_type === 'image/webp') $extension = 'webp';
                    elseif ($mime_type === 'image/jpeg') $extension = 'jpg';
                    
                    $upload_dir = __DIR__ . '/../uploads/units/';
                    if (!file_exists($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }
                    
                    $filename = time() . '_unit_' . $index . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
                    $filepath = $upload_dir . $filename;
                    
                    if (file_put_contents($filepath, $image_base64)) {
                        $unit_photo_paths[$index] = 'uploads/units/' . $filename;
                    }
                }
            }
        }
    }
    
    $cheque_raw = trim((string) ($_POST['cheque_number'] ?? ''));
    $voucher_raw = trim((string) ($_POST['voucher_number'] ?? ''));
    $cash_check = isset($_POST['cash_amount']) ? (string) $_POST['cash_amount'] : '';
    $shop_raw = trim((string) ($_POST['shop_name'] ?? ''));
    $purchaser_raw = trim((string) ($_POST['purchaser_name'] ?? ''));
    $has_purchase_proof = !empty($_FILES['purchase_proof']['tmp_name']) && is_uploaded_file($_FILES['purchase_proof']['tmp_name']);
    $has_service_invoice = !empty($_FILES['service_invoice']['tmp_name']) && is_uploaded_file($_FILES['service_invoice']['tmp_name']);
    
    $donor_name = trim((string) ($_POST['donor_name'] ?? ''));
    $donation_date = trim((string) ($_POST['donation_date'] ?? ''));
    $has_deed_of_donation = !empty($_FILES['deed_of_donation']['tmp_name']) && is_uploaded_file($_FILES['deed_of_donation']['tmp_name']);
    
    $transfer_from_agency = trim((string) ($_POST['transfer_from_agency'] ?? ''));
    $transfer_date = trim((string) ($_POST['transfer_date'] ?? ''));
    $has_transfer_document = !empty($_FILES['transfer_document']['tmp_name']) && is_uploaded_file($_FILES['transfer_document']['tmp_name']);
    
    $contractor_name = trim((string) ($_POST['contractor_name'] ?? ''));
    $contract_amount = trim((string) ($_POST['contract_amount'] ?? ''));
    $has_project_contract = !empty($_FILES['project_contract']['tmp_name']) && is_uploaded_file($_FILES['project_contract']['tmp_name']);
    
    $lessor_name = trim((string) ($_POST['lessor_name'] ?? ''));
    $lease_period_start = trim((string) ($_POST['lease_period_start'] ?? ''));
    $lease_period_end = trim((string) ($_POST['lease_period_end'] ?? ''));
    $has_lease_contract = !empty($_FILES['lease_contract']['tmp_name']) && is_uploaded_file($_FILES['lease_contract']['tmp_name']);
    
    $previous_owner = trim((string) ($_POST['previous_owner'] ?? ''));
    $repossession_date = trim((string) ($_POST['repossession_date'] ?? ''));
    $has_court_order = !empty($_FILES['court_order']['tmp_name']) && is_uploaded_file($_FILES['court_order']['tmp_name']);
    
    $seizure_authority = trim((string) ($_POST['seizure_authority'] ?? ''));
    $seizure_date = trim((string) ($_POST['seizure_date'] ?? ''));
    $has_seizure_document = !empty($_FILES['seizure_document']['tmp_name']) && is_uploaded_file($_FILES['seizure_document']['tmp_name']);
    
    $seller_name = trim((string) ($_POST['seller_name'] ?? ''));
    $negotiation_date = trim((string) ($_POST['negotiation_date'] ?? ''));
    $has_sales_agreement = !empty($_FILES['sales_agreement']['tmp_name']) && is_uploaded_file($_FILES['sales_agreement']['tmp_name']);
    
    $aid_agency = trim((string) ($_POST['aid_agency'] ?? ''));
    $aid_type = trim((string) ($_POST['aid_type'] ?? ''));
    $aid_date = trim((string) ($_POST['aid_date'] ?? ''));
    $has_aid_document = !empty($_FILES['aid_document']['tmp_name']) && is_uploaded_file($_FILES['aid_document']['tmp_name']);

    if ($article_raw === '') {
        $registration_errors[] = 'Article is required.';
    }
    if ($description_raw === '') {
        $registration_errors[] = 'Description is required.';
    }
    if ($quantity_check < 1) {
        $registration_errors[] = 'Quantity must be at least 1.';
    }
    if ($category_raw === '') {
        $registration_errors[] = 'Category is required.';
    }
    if ($property_raw === '') {
        $registration_errors[] = 'Property number prefix is required.';
    }
    if ($uom_raw === '') {
        $registration_errors[] = 'Unit of measure is required.';
    }
    if (!$has_asset_photo) {
        $registration_errors[] = 'Primary asset photo is required.';
    }
    if ($date_raw === '') {
        $registration_errors[] = 'Date acquired is required.';
    }
    if ($unit_value_raw === '' || !is_numeric($unit_value_raw)) {
        $registration_errors[] = 'Unit cost/value is required.';
    }
    if ($remarks_raw === '') {
        $registration_errors[] = 'Initial remarks is required.';
    }
    if ($status_raw === '') {
        $registration_errors[] = 'Initial status is required.';
    }
    if ($cluster_raw === '') {
        $registration_errors[] = 'Asset cluster is required.';
    }
    if ($location_raw === '') {
        $registration_errors[] = 'Assigned location is required.';
    }
    if ($acq_raw === '') {
        $registration_errors[] = 'Acquisition mode is required.';
    }
    if ($accountable_officer_raw === '') {
        $registration_errors[] = 'Accountable officer is required.';
    }

    $acquisition_modes = bpis_get_acquisition_modes();
    $mode_fields = $acquisition_modes[$acq_raw]['fields'] ?? [];
    
    foreach ($mode_fields as $field) {
        switch($field) {
            case 'cheque_number':
                if ($cheque_raw === '') $registration_errors[] = 'Cheque no. is required for purchased assets.';
                break;
            case 'voucher_number':
                if ($voucher_raw === '') $registration_errors[] = 'Voucher no. is required for purchased assets.';
                break;
            case 'cash_amount':
                if ($cash_check === '' || !is_numeric($cash_check)) $registration_errors[] = 'Cash amount is required for purchased assets.';
                break;
            case 'shop_name':
                if ($shop_raw === '') $registration_errors[] = 'Shop is required for purchased assets.';
                break;
            case 'purchaser_name':
                if ($purchaser_raw === '') $registration_errors[] = 'Purchaser is required for purchased assets.';
                break;
            case 'purchase_proof':
                if (!$has_purchase_proof) $registration_errors[] = 'Proof of purchase is required for purchased assets.';
                break;
            case 'service_invoice':
                if (!$has_service_invoice) $registration_errors[] = 'Service invoice is required for purchased assets.';
                break;
            case 'donor_name':
                if ($donor_name === '') $registration_errors[] = 'Donor name is required for donated assets.';
                break;
            case 'donation_date':
                if ($donation_date === '') $registration_errors[] = 'Donation date is required for donated assets.';
                break;
            case 'deed_of_donation':
                if (!$has_deed_of_donation) $registration_errors[] = 'Deed of donation is required for donated assets.';
                break;
            case 'transfer_from_agency':
                if ($transfer_from_agency === '') $registration_errors[] = 'Transferring agency is required for transferred assets.';
                break;
            case 'transfer_date':
                if ($transfer_date === '') $registration_errors[] = 'Transfer date is required for transferred assets.';
                break;
            case 'transfer_document':
                if (!$has_transfer_document) $registration_errors[] = 'Transfer document is required for transferred assets.';
                break;
            case 'contractor_name':
                if ($contractor_name === '') $registration_errors[] = 'Contractor name is required for constructed assets.';
                break;
            case 'contract_amount':
                if ($contract_amount === '' || !is_numeric($contract_amount)) $registration_errors[] = 'Contract amount is required for constructed assets.';
                break;
            case 'project_contract':
                if (!$has_project_contract) $registration_errors[] = 'Project contract is required for constructed assets.';
                break;
            case 'lessor_name':
                if ($lessor_name === '') $registration_errors[] = 'Lessor name is required for leased assets.';
                break;
            case 'lease_period_start':
                if ($lease_period_start === '') $registration_errors[] = 'Lease start date is required for leased assets.';
                break;
            case 'lease_period_end':
                if ($lease_period_end === '') $registration_errors[] = 'Lease end date is required for leased assets.';
                break;
            case 'lease_contract':
                if (!$has_lease_contract) $registration_errors[] = 'Lease contract is required for leased assets.';
                break;
            case 'previous_owner':
                if ($previous_owner === '') $registration_errors[] = 'Previous owner is required for repossessed assets.';
                break;
            case 'repossession_date':
                if ($repossession_date === '') $registration_errors[] = 'Repossession date is required for repossessed assets.';
                break;
            case 'court_order':
                if (!$has_court_order) $registration_errors[] = 'Court order document is required for repossessed assets.';
                break;
            case 'seizure_authority':
                if ($seizure_authority === '') $registration_errors[] = 'Seizure authority is required for seized assets.';
                break;
            case 'seizure_date':
                if ($seizure_date === '') $registration_errors[] = 'Seizure date is required for seized assets.';
                break;
            case 'seizure_document':
                if (!$has_seizure_document) $registration_errors[] = 'Seizure document is required for seized assets.';
                break;
            case 'seller_name':
                if ($seller_name === '') $registration_errors[] = 'Seller name is required for negotiated sale assets.';
                break;
            case 'negotiation_date':
                if ($negotiation_date === '') $registration_errors[] = 'Negotiation date is required for negotiated sale assets.';
                break;
            case 'sales_agreement':
                if (!$has_sales_agreement) $registration_errors[] = 'Sales agreement document is required for negotiated sale assets.';
                break;
            case 'aid_agency':
                if ($aid_agency === '') $registration_errors[] = 'Aid agency is required for aid assets.';
                break;
            case 'aid_type':
                if ($aid_type === '') $registration_errors[] = 'Aid type is required for aid assets.';
                break;
            case 'aid_date':
                if ($aid_date === '') $registration_errors[] = 'Aid date is required for aid assets.';
                break;
            case 'aid_document':
                if (!$has_aid_document) $registration_errors[] = 'Aid document is required for aid assets.';
                break;
        }
    }
    
    if ($brand_raw === '') {
        $brand_raw = '';
    }
    if ($model_raw === '') {
        $model_raw = '';
    }
    if ($audit_raw === '') {
        $audit_raw = 'No audit remarks';
    }

    if (!empty($registration_errors)) {
    } else {

    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $category = mysqli_real_escape_string($conn, $_POST['category']);
    $property_prefix = mysqli_real_escape_string($conn, $_POST['property_number']);
    $unit_measure = mysqli_real_escape_string($conn, $_POST['unit_measure']);
    $date_acquired = mysqli_real_escape_string($conn, $_POST['date_acquired']);
    $remarks = mysqli_real_escape_string($conn, $_POST['remarks']);
    $asset_cluster = mysqli_real_escape_string($conn, $_POST['asset_cluster'] ?? bpis_map_category_to_cluster($category));
    $location = mysqli_real_escape_string($conn, $_POST['location'] ?? '');
    $brand = mysqli_real_escape_string($conn, bpis_normalize_brand_model_value($_POST['brand'] ?? ''));
    $model = mysqli_real_escape_string($conn, bpis_normalize_brand_model_value($_POST['model'] ?? ''));
    $audit_remarks = mysqli_real_escape_string($conn, $_POST['audit_remarks'] ?? 'No audit remarks');
    $acquisition_mode = mysqli_real_escape_string($conn, $_POST['acquisition_mode'] ?? 'Purchased');
    $accountable_officer = mysqli_real_escape_string($conn, $_POST['accountable_officer'] ?? '');
    
    $cheque_number = $cheque_raw ?: 'N/A';
    $voucher_number = $voucher_raw ?: 'N/A';
    $cash_amount = $cash_check !== '' ? (float)$cash_check : 0;
    $shop_name = $shop_raw ?: 'N/A';
    $purchaser_name = $purchaser_raw ?: 'N/A';
    $purchase_proof_path = $has_purchase_proof ? bpis_store_upload('purchase_proof', 'acquisition') : '';
    $service_invoice_path = $has_service_invoice ? bpis_store_upload('service_invoice', 'acquisition') : '';
    
    $donor_name = $donor_name ?: 'N/A';
    $donation_date = $donation_date ?: date('Y-m-d');
    $deed_of_donation_path = $has_deed_of_donation ? bpis_store_upload('deed_of_donation', 'acquisition') : '';
    
    $transfer_from_agency = $transfer_from_agency ?: 'N/A';
    $transfer_date = $transfer_date ?: date('Y-m-d');
    $transfer_document_path = $has_transfer_document ? bpis_store_upload('transfer_document', 'acquisition') : '';
    
    $contractor_name = $contractor_name ?: 'N/A';
    $contract_amount = $contract_amount !== '' ? (float)$contract_amount : 0;
    $project_contract_path = $has_project_contract ? bpis_store_upload('project_contract', 'acquisition') : '';
    
    $lessor_name = $lessor_name ?: 'N/A';
    $lease_period_start = $lease_period_start ?: date('Y-m-d');
    $lease_period_end = $lease_period_end ?: date('Y-m-d', strtotime('+1 year'));
    $lease_contract_path = $has_lease_contract ? bpis_store_upload('lease_contract', 'acquisition') : '';
    
    $previous_owner = $previous_owner ?: 'N/A';
    $repossession_date = $repossession_date ?: date('Y-m-d');
    $court_order_path = $has_court_order ? bpis_store_upload('court_order', 'acquisition') : '';
    
    $seizure_authority = $seizure_authority ?: 'N/A';
    $seizure_date = $seizure_date ?: date('Y-m-d');
    $seizure_document_path = $has_seizure_document ? bpis_store_upload('seizure_document', 'acquisition') : '';
    
    $seller_name = $seller_name ?: 'N/A';
    $negotiation_date = $negotiation_date ?: date('Y-m-d');
    $sales_agreement_path = $has_sales_agreement ? bpis_store_upload('sales_agreement', 'acquisition') : '';
    
    $aid_agency = $aid_agency ?: 'N/A';
    $aid_type = $aid_type ?: 'N/A';
    $aid_date = $aid_date ?: date('Y-m-d');
    $aid_document_path = $has_aid_document ? bpis_store_upload('aid_document', 'acquisition') : '';
    
    if (!isset($asset_photo_path) || $asset_photo_path === '') {
        $asset_photo_path = bpis_store_upload('asset_photo', 'assets') ?? '';
    }
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    $unit_value = (double)$_POST['unit_value'];
    $registered_by = isset($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : 0;

    $article_input = trim($_POST['article']);
    $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;
    if ($quantity < 1) {
        $quantity = 1;
    }
    
    $clean_article = mysqli_real_escape_string($conn, $article_input);

  try {
        $asset_columns = [];
        $asset_column_result = @mysqli_query($conn, "SHOW COLUMNS FROM `asset`");
        if ($asset_column_result) {
            while ($col = mysqli_fetch_assoc($asset_column_result)) {
                if (!empty($col['Field'])) {
                    $asset_columns[$col['Field']] = true;
                }
            }
        }

        $fields = [
            'article',
            'quantity',
            'description',
            'category',
            'property_number',
            'unit_measure',
            'unit_value',
            'date_acquired',
            'remarks',
            'status'
        ];
        $values = [
            "'$clean_article'",
            (string)$quantity,
            "'$description'",
            "'$category'",
            "'$property_prefix'",
            "'$unit_measure'",
            (string)$unit_value,
            "'$date_acquired'",
            "'$remarks'",
            "'$status'"
        ];

        if (isset($asset_columns['registered_by'])) {
            $fields[] = 'registered_by';
            $values[] = (string)$registered_by;
        }
        if (isset($asset_columns['admin_id'])) {
            $fields[] = 'admin_id';
            $values[] = (string)$registered_by;
        }
        $optional_map = [
            'asset_cluster' => "'{$asset_cluster}'",
            'location' => "'{$location}'",
            'brand' => "'{$brand}'",
            'model' => "'{$model}'",
            'audit_remarks' => "'{$audit_remarks}'",
            'acquisition_mode' => "'{$acquisition_mode}'",
            'photo_path' => "'" . mysqli_real_escape_string($conn, $asset_photo_path) . "'",
            'accountable_officer' => "'{$accountable_officer}'",
        ];
        foreach ($optional_map as $col => $val) {
            if (!isset($asset_columns[$col])) {
                continue;
            }
            if ($col === 'photo_path' && $asset_photo_path === '') {
                continue;
            }
            $fields[] = $col;
            $values[] = $val;
        }

        $sql = "INSERT INTO asset (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $values) . ")";
                
        if (mysqli_query($conn, $sql)) {
            $new_asset_id = (int) mysqli_insert_id($conn);
            if ($new_asset_id > 0) {
                
                $acquisition_data = [
                    'cheque_number' => $cheque_number ?? 'N/A',
                    'voucher_number' => $voucher_number ?? 'N/A',
                    'cash_amount' => $cash_amount ?? 0,
                    'shop_name' => $shop_name ?? 'N/A',
                    'purchaser_name' => $purchaser_name ?? 'N/A',
                    'purchase_proof_path' => $purchase_proof_path ?? '',
                    'service_invoice_path' => $service_invoice_path ?? '',
                    'donor_name' => $donor_name ?? 'N/A',
                    'donation_date' => $donation_date ?? date('Y-m-d'),
                    'deed_of_donation_path' => $deed_of_donation_path ?? '',
                    'transfer_from_agency' => $transfer_from_agency ?? 'N/A',
                    'transfer_date' => $transfer_date ?? date('Y-m-d'),
                    'transfer_document_path' => $transfer_document_path ?? '',
                    'contractor_name' => $contractor_name ?? 'N/A',
                    'contract_amount' => $contract_amount ?? 0,
                    'project_contract_path' => $project_contract_path ?? '',
                    'lessor_name' => $lessor_name ?? 'N/A',
                    'lease_period_start' => $lease_period_start ?? date('Y-m-d'),
                    'lease_period_end' => $lease_period_end ?? date('Y-m-d', strtotime('+1 year')),
                    'lease_contract_path' => $lease_contract_path ?? '',
                    'previous_owner' => $previous_owner ?? 'N/A',
                    'repossession_date' => $repossession_date ?? date('Y-m-d'),
                    'court_order_path' => $court_order_path ?? '',
                    'seizure_authority' => $seizure_authority ?? 'N/A',
                    'seizure_date' => $seizure_date ?? date('Y-m-d'),
                    'seizure_document_path' => $seizure_document_path ?? '',
                    'seller_name' => $seller_name ?? 'N/A',
                    'negotiation_date' => $negotiation_date ?? date('Y-m-d'),
                    'sales_agreement_path' => $sales_agreement_path ?? '',
                    'aid_agency' => $aid_agency ?? 'N/A',
                    'aid_type' => $aid_type ?? 'N/A',
                    'aid_date' => $aid_date ?? date('Y-m-d'),
                    'aid_document_path' => $aid_document_path ?? '',
                ];
                
                bpis_save_acquisition_details($conn, $new_asset_id, $acquisition_mode, $acquisition_data);
                
                bpis_create_asset_units_for_asset(
                    $conn,
                    $new_asset_id,
                    $property_prefix,
                    $quantity,
                    $_POST['brand'] ?? '',
                    $_POST['model'] ?? '',
                    $_POST['location'] ?? '',
                    $uploaded_unit_files,
                    $asset_photo_path !== '' ? $asset_photo_path : null,
                    $unit_photo_paths
                );
                if ($asset_photo_path !== '' && bpis_column_exists($conn, 'asset', 'photo_path')) {
                    $upd = $conn->prepare('UPDATE asset SET photo_path = ? WHERE id = ?');
                    if ($upd) {
                        $upd->bind_param('si', $asset_photo_path, $new_asset_id);
                        $upd->execute();
                        $upd->close();
                    }
                }
                bpis_finalize_new_asset_financials($conn, $new_asset_id, $unit_value, $quantity);
                bpis_set_last_registered_asset($new_asset_id);
                try {
                    $notifTitle = 'New asset registered';
                    $notifMsg = 'New asset registered: ' . $clean_article . '.';
                    $notifLink = 'treasurer/inventory.php?asset_id=' . $new_asset_id;
                    bpis_notify_admins_by_roles($conn, ['Secretary'], $notifTitle, $notifMsg, $notifLink, 'new_asset_registered', null, null);
                } catch (Throwable $e) {
                    error_log('BPIS asset registration notify failed: ' . $e->getMessage());
                }
            }
            header("Location: inventory.php?msg=registered");
            exit(); 
        } else {
            throw new Exception(mysqli_error($conn));
        }
        
    } catch (Exception $e) {
        $registration_errors[] = $e->getMessage();
    }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" href="<?= bpis_app_base_path() ?>/images/logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="../css/profile_dropdown.css"><link rel="stylesheet" href="../css/sidebar_nav_transition.css">
    <link rel="stylesheet" href="../css/treasurer_mobile_sidebar.css">
    <script src="../js/sidebar_nav_transition.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php include __DIR__ . '/../includes/bpis_app_meta.php'; ?>
    <title>Asset Registration Dashboard</title>
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
        .sidebar-bottom { 
            font-size: 11px; 
            text-align: center; 
            opacity: 0.88; 
            color: rgba(255, 255, 255, 0.9);
            line-height: 1.4; 
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
        .nav-item i { width: 18px; margin-right: 10px; text-align: center; }
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
        .main-content { 
            flex: 1; 
            margin-left: 230px; 
            padding: 25px 35px; 
            display: flex; 
            flex-direction: column; 
            height: 100vh; 
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
        .profile-dropdown.active { display: block; }
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
        #printArea {
            display: none;
        }
        #qrModal .text-sm {
            font-size: 14px !important;
        }
        #qrModal .tag-text-id {
            font-size: 13px !important;
        }
        #qrModal .tag-text-name {
            font-size: 14px !important;
            font-weight: 600 !important;
        }
        #qrModal .tag-text-meta {
            font-size: 12px !important;
        }
        .qr-tag {
            width: 100%;
            height: auto;
            min-width: 0;
            border: 1px solid #ccc;
            border-radius: 5px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
            background: white;
            padding: 8px;
            box-sizing: border-box;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .qr-tag .qr-image {
            width: 100px;
            height: 100px;
            display: block;
            margin-bottom: 8px;
            align-self: center;
            object-fit: contain;
            flex-shrink: 0;
        }
        .qr-tag .tag-id-text {
            font-size: 9px;
            font-weight: 700;
            color: #1d4ed8;
            text-align: left;
            margin: 4px 0;
            word-break: break-word;
            width: 100%;
            line-height: 1.3;
        }
        .qr-tag .tag-description {
            font-size: 9px;
            font-weight: 500;
            color: #111827;
            text-align: left;
            width: 100%;
            margin: 2px 0;
            word-break: break-word;
            line-height: 1.3;
        }
        .qr-tag .tag-description-label {
            font-weight: 700;
            font-size: 9px;
            color: #374151;
            margin-right: 4px;
        }
        .qr-tag .tag-brand-line {
            font-size: 9px;
            font-weight: 500;
            color: #4b5563;
            text-align: left;
            width: 100%;
            margin: 2px 0;
            word-break: break-word;
            line-height: 1.3;
        }
        .qr-tag .tag-brand-label {
            font-weight: 700;
            font-size: 9px;
            color: #374151;
            margin-right: 4px;
        }
        .qr-tag .tag-model-line {
            font-size: 9px;
            font-weight: 500;
            color: #4b5563;
            text-align: left;
            width: 100%;
            margin: 2px 0;
            word-break: break-word;
            line-height: 1.3;
        }
        .qr-tag .tag-model-label {
            font-weight: 700;
            font-size: 9px;
            color: #374151;
            margin-right: 4px;
        }
        #qrGrid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 12px;
            padding: 10px;
        }
        #qrModal h2 {
            font-size: 20px !important;
        }
        #qrModal p {
            font-size: 14px !important;
        }
        .print-tag {
            padding: 10px !important;
        }
        .print-tag .tag-text-id {
            font-size: 10px !important;
        }
        .print-tag .tag-text-name {
            font-size: 10px !important;
            font-weight: 600 !important;
        }
        .print-tag .tag-text-meta {
            font-size: 10px !important;
        }
        .print-tag .w-16 {
            width: 70px !important;
            height: 70px !important;
        }
        .reg-required {
            color: #ef4444 !important;
            font-weight: 700;
            margin-left: 3px;
            font-size: 14px;
            line-height: 1;
        }
        .reg-label .reg-required {
            color: #dc2626 !important;
        }
        .reg-fields-note {
            font-size: 12px;
            color: #dc2626 !important;
            font-weight: 600;
            margin-bottom: 16px;
        }
        .reg-error-box {
            margin-bottom: 20px;
            padding: 14px 16px;
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 10px;
            color: #b91c1c !important;
            font-size: 13px;
            line-height: 1.45;
        }
        .reg-error-box strong {
            color: #991b1b !important;
        }
        .reg-error-box ul {
            margin: 8px 0 0 18px;
            padding: 0;
        }
        #registrationForm .reg-label:has(.reg-required) {
            color: #374151;
        }
        #registrationForm input:required,
        #registrationForm select:required {
            border-left: 3px solid #ef4444;
        }
        #registrationForm input[type="file"]:required {
            border-left: none;
            outline: 2px solid #fecaca;
            outline-offset: 2px;
            border-radius: 8px;
        }
        .reg-optional {
            color: #6b7280;
            font-size: 11px;
            font-weight: normal;
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
        .camera-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.9);
            z-index: 2000;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
        }
        .camera-container {
            background: #000;
            border-radius: 20px;
            overflow: hidden;
            max-width: 90%;
            max-height: 80vh;
        }
        video {
            width: 100%;
            max-height: 60vh;
            object-fit: cover;
        }
        canvas {
            display: none;
        }
        .camera-controls {
            display: flex;
            gap: 20px;
            margin-top: 20px;
        }
        .cam-btn {
            padding: 12px 24px;
            border-radius: 50px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        .cam-btn-capture {
            background: #3b82f6;
            color: white;
        }
        .cam-btn-capture:hover {
            background: #2563eb;
            transform: scale(1.05);
        }
        .cam-btn-close {
            background: #ef4444;
            color: white;
        }
        .cam-btn-close:hover {
            background: #dc2626;
        }
        .qr-tag-container {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
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
            font-size: 9px;
            font-weight: bold;
            color: #1d4ed8;
            margin: 4px 0;
            text-align: left;
            width: 100%;
            word-break: break-word;
        }
        .qr-tag-card .tag-line {
            font-size: 9px;
            color: #333;
            margin: 2px 0;
            text-align: left;
            width: 100%;
            word-break: break-word;
        }
        .qr-tag-card .tag-line strong {
            font-weight: bold;
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
        @media (max-width: 1200px) {
            #qrGrid {
                grid-template-columns: repeat(4, 1fr);
                gap: 14px;
            }
        }
        @media (max-width: 900px) {
            #qrGrid {
                grid-template-columns: repeat(3, 1fr);
                gap: 12px;
            }
        }
        @media (max-width: 600px) {
            #qrGrid {
                grid-template-columns: repeat(2, 1fr);
                gap: 10px;
            }
        }
        @media (max-width: 768px) {
            body {
                display: block !important;
                min-height: 100vh !important;
                height: auto !important;
                overflow-x: hidden !important;
                overflow-y: auto !important;
            }

            .main-content,
            body.sidebar-open .main-content,
            body:not(.sidebar-open) .main-content {
                height: auto !important;
                min-height: 100vh !important;
                padding: 14px 12px 24px !important;
            }

            #registrationForm {
                min-height: auto !important;
                overflow: visible !important;
                margin-bottom: 0 !important;
                border-radius: 12px !important;
            }

            #registrationForm > .custom-scrollbar {
                overflow: visible !important;
                padding: 16px !important;
            }

            #registrationForm > .border-t {
                flex-direction: column-reverse !important;
                align-items: stretch !important;
                padding: 14px 16px !important;
                gap: 10px !important;
            }

            #registrationForm > .border-t button {
                width: 100% !important;
                justify-content: center !important;
                min-height: 42px !important;
            }

            .mode-fields-group {
                padding: 12px !important;
                border-radius: 10px !important;
            }

            #unitPhotosForm {
                max-height: none !important;
                overflow: visible !important;
                padding-right: 0 !important;
            }

            #qrModal {
                padding: 10px !important;
            }

            #qrModal > div {
                max-height: calc(100vh - 20px) !important;
                border-radius: 12px !important;
            }
        }
</style>
    <link rel="stylesheet" href="../css/logout_modal.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { theme: { extend: { colors: { brand: { 600: '#2563eb', 700: '#1d4ed8' } } } } };
    </script>
    <script src="../tailwind_blue_theme.js"></script>
</head>
<body>
<?php include __DIR__ . '/../includes/bpis_logout_modal.php'; ?>

<div id="printArea"></div>

    <div class="sidebar no-print">
        <div class="sidebar-top">
            <button type="button" id="mobileNavClose" class="mobile-nav-close" aria-label="Close navigation">
                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </button>
            <div class="logo-container">
                <img src="../images/logo.png" alt="Logo" class="logo-img" >
            </div>
            <h2>Barangay Bolocboloc Property Inventory System</h2>
            <?php include __DIR__ . '/../includes/treasurer_sidebar.php'; ?>
        </div>
         <div class="sidebar-bottom">Brgy. Bolocboloc, Sibulan<br>Negros Oriental, Philippines, 6201<br>@2026</div>
    </div>

    <div class="sidebar-overlay no-print" id="sidebarOverlay" aria-hidden="true"></div>

    <div class="main-content no-print">
        <div class="header shrink-0">
            <button type="button" id="mobileNavToggle" class="mobile-nav-toggle" aria-label="Toggle navigation" aria-expanded="false">
                <i class="fa-solid fa-bars" aria-hidden="true"></i>
            </button>
            <div>
                <h1>Asset Registration</h1>
                <p style="font-size: 13px;">Register new properties following COA format.</p>
            </div>
<?php include __DIR__ . '/../includes/admin_header_profile_icons.php'; ?>
        </div>

        <form action="asset_registration.php" method="POST" enctype="multipart/form-data" id="registrationForm" class="flex-grow bg-white border border-gray-200 rounded-xl flex flex-col min-h-0 shadow-sm overflow-hidden mb-2">
            <div class="overflow-y-auto custom-scrollbar flex-grow p-8">
                <?php if (!empty($registration_errors)): ?>
                    <div class="reg-error-box" role="alert">
                        <strong>Please complete all required fields:</strong>
                        <ul>
                            <?php foreach ($registration_errors as $err): ?>
                                <li><?= htmlspecialchars($err) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <div class="mb-8">
                    <h3 class="text-[13px] font-bold text-gray-800 mb-5 uppercase tracking-wider border-b border-gray-100 pb-2 flex items-center gap-2">
                        <i data-lucide="package" class="w-4 h-4 text-blue-500"></i> Property Identification
                    </h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-5 mb-5">
                        <div class="md:col-span-2">
                            <label class="reg-label block text-[13px] font-semibold text-gray-700 mb-1.5">Article <?= bpis_reg_required_mark() ?></label>
                            <input type="text" name="article" id="articleInput" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500" placeholder="e.g. 3 units" required value="<?= htmlspecialchars((string) ($_POST['article'] ?? '')) ?>">
                            <p id="qrNotice" class="text-[10px] text-blue-500 mt-2 font-medium italic"></p>
                        </div>
                        <div class="md:col-span-2">
                            <label class="reg-label block text-[13px] font-semibold text-gray-700 mb-1.5">Description / Specification <?= bpis_reg_required_mark() ?></label>
                            <input type="text" name="description" id="descriptionInput" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500" placeholder="e.g. Plastic Chair" required value="<?= htmlspecialchars((string) ($_POST['description'] ?? '')) ?>">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                        <div>
                            <label class="reg-label block text-[13px] font-semibold text-gray-700 mb-1.5">Quantity <?= bpis_reg_required_mark() ?></label>
                            <input type="number" name="quantity" id="quantityInput" min="1" value="<?= htmlspecialchars((string) ($_POST['quantity'] ?? '1')) ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500" required>
                        </div>
                        <div>
                            <label class="reg-label block text-[13px] font-semibold text-gray-700 mb-1.5">Category <?= bpis_reg_required_mark() ?></label>
                            <select name="category" id="categorySelect" class="bg-white border border-gray-300 text-sm rounded-lg px-4 py-2 w-full outline-none" required>
                                <option value="">Select category</option>
                                <option value="Land & Improvements">Land & Improvements</option>
                                <option value="Buildings & Structures">Buildings & Structures</option>
                                <option value="Machinery">Machinery</option>
                                <option value="Equipment">Equipment</option>
                                <option value="IT Equipment">IT Equipment</option>
                                <option value="Office Equipment">Office Equipment</option>
                                <option value="Furniture & Fixtures">Furniture & Fixtures</option>
                                <option value="Motor Vehicles / Delivery Truck">Motor Vehicles / Delivery Truck</option>
                                <option value="Tools">Tools</option>
                            </select>
                        </div>
                        <div>
                            <label class="reg-label block text-[13px] font-semibold text-gray-700 mb-1.5">Property Number (COA Format) <?= bpis_reg_required_mark() ?></label>
                            <input type="text" name="property_number" id="propNumber" autocomplete="off" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500" placeholder="PROP-2026-OFE-001" required value="<?= htmlspecialchars((string) ($_POST['property_number'] ?? '')) ?>">
                            <p id="propPrefixHint" class="text-[10px] text-gray-500 mt-1.5">Format: PROP-YYYY-CAT-XXX (Auto-generated based on category)</p>
                        </div>
                        <div>
                            <label class="reg-label block text-[13px] font-semibold text-gray-700 mb-1.5">Unit of Measure <?= bpis_reg_required_mark() ?></label>
                            <select name="unit_measure" id="unitMeasureSelect" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white" required>
                                <option value="">Select unit</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="mb-8">
                    <h3 class="text-[13px] font-bold text-gray-800 mb-5 uppercase tracking-wider border-b border-gray-100 pb-2 flex items-center gap-2">
                        <i data-lucide="image" class="w-4 h-4 text-blue-500"></i> Asset Images
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="reg-label block text-[13px] font-semibold text-gray-700 mb-1.5">Primary asset photo <?= bpis_reg_required_mark() ?></label>
                            <p class="text-[11px] text-gray-500 mb-2">Shown in inventory. JPG, PNG, or WebP (max 5 MB recommended).</p>
                            
                            <div class="flex flex-col gap-3">
                                <div class="flex gap-3">
                                    <input type="file" name="asset_photo" id="assetPhotoInput" accept="image/jpeg,image/png,image/webp" class="w-full text-sm file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-blue-50 file:text-blue-700 file:font-medium hover:file:bg-blue-100">
                                    <button type="button" id="openCameraBtn" class="bg-green-600 hover:bg-green-700 text-white text-sm font-medium py-2 px-4 rounded-lg flex items-center gap-2 whitespace-nowrap">
                                        <i class="fa-solid fa-camera"></i> Take Photo
                                    </button>
                                </div>
                                <input type="hidden" name="camera_photo" id="cameraPhotoInput" value="">
                                <div id="assetPhotoPreview" class="mt-3 hidden">
                                    <img id="assetPhotoPreviewImg" src="" alt="Asset preview" class="max-h-40 rounded-lg border border-gray-200 object-contain bg-gray-50">
                                    <button type="button" id="assetPhotoClear" class="mt-2 text-xs text-red-600 hover:text-red-700 font-medium">Remove image</button>
                                </div>
                            </div>
                        </div>
                        <div id="unitPhotosBlock" class="hidden">
                            <label class="block text-[13px] font-semibold text-gray-700 mb-1.5">Per-unit photos <span class="reg-optional">(optional)</span></label>
                            <p class="text-[11px] text-gray-500 mb-2">When quantity is more than 1, you may upload a separate photo for each unit. If omitted, the primary photo is used.</p>
                            <div id="unitPhotosForm" class="space-y-2 max-h-48 overflow-y-auto custom-scrollbar pr-1"></div>
                        </div>
                    </div>
                </div>

                <div class="mb-8">
                    <h3 class="text-[13px] font-bold text-gray-800 mb-5 uppercase tracking-wider border-b border-gray-100 pb-2 flex items-center gap-2">
                        <i data-lucide="shopping-cart" class="w-4 h-4 text-blue-500"></i> Acquisition & Status
                    </h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-5">
                        <div>
                            <label class="reg-label block text-[13px] font-semibold text-gray-700 mb-1.5">Date Acquired <?= bpis_reg_required_mark() ?></label>
                            <input type="date" name="date_acquired" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" required value="<?= htmlspecialchars((string) ($_POST['date_acquired'] ?? '')) ?>">
                        </div>
                        <div>
                            <label class="reg-label block text-[13px] font-semibold text-gray-700 mb-1.5">Unit Cost/Value (₱) <?= bpis_reg_required_mark() ?></label>
                            <input type="number" step="0.01" min="0" name="unit_value" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" placeholder="0.00" required value="<?= htmlspecialchars((string) ($_POST['unit_value'] ?? '')) ?>">
                        </div>
                        <div>
                            <label class="reg-label block text-[13px] font-semibold text-gray-700 mb-1.5">Initial Remarks <?= bpis_reg_required_mark() ?></label>
                            <input type="text" name="remarks" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" value="<?= htmlspecialchars((string) ($_POST['remarks'] ?? 'SERVICEABLE')) ?>" required>
                        </div>
                        <div>
                            <label class="reg-label block text-[13px] font-semibold text-gray-700 mb-1.5">Initial Status <?= bpis_reg_required_mark() ?></label>
                            <select name="status" class="bg-white border border-gray-300 text-sm rounded-lg px-4 py-2 w-full outline-none" required>
                                <option value="Available">Available</option>
                                <option value="In Use">In Use</option>
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-5 mt-5">
                        <div><label class="reg-label block text-[13px] font-semibold text-gray-700 mb-1.5">Asset cluster <?= bpis_reg_required_mark() ?></label>
                            <select name="asset_cluster" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" required>
                                <option value="Fixed Assets"<?= (($_POST['asset_cluster'] ?? 'Movable Assets') === 'Fixed Assets') ? ' selected' : '' ?>>Fixed Assets</option>
                                <option value="Movable Assets"<?= (($_POST['asset_cluster'] ?? 'Movable Assets') !== 'Fixed Assets') ? ' selected' : '' ?>>Movable Assets</option>
                            </select></div>
                        <div class="md:col-span-2"><label class="reg-label block text-[13px] font-semibold text-gray-700 mb-1.5">Assigned location <?= bpis_reg_required_mark() ?></label>
                            <input type="text" name="location" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" placeholder="Barangay Hall — Storage A" required value="<?= htmlspecialchars((string) ($_POST['location'] ?? '')) ?>"></div>
                        <div><label class="reg-label block text-[13px] font-semibold text-gray-700 mb-1.5">Brand <span class="reg-optional">(optional)</span></label>
                            <input type="text" name="brand" id="brandInput" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" placeholder="e.g. Samsung" value="<?= htmlspecialchars((string) ($_POST['brand'] ?? '')) ?>"></div>
                        <div><label class="reg-label block text-[13px] font-semibold text-gray-700 mb-1.5">Model <span class="reg-optional">(optional)</span></label>
                            <input type="text" name="model" id="modelInput" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" placeholder="e.g. XR-200" value="<?= htmlspecialchars((string) ($_POST['model'] ?? '')) ?>"></div>
                        <div><label class="reg-label block text-[13px] font-semibold text-gray-700 mb-1.5">Audit / condition remarks <span class="reg-optional">(optional)</span></label>
                            <input type="text" name="audit_remarks" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" placeholder="No audit remarks" value="<?= htmlspecialchars((string) ($_POST['audit_remarks'] ?? '')) ?>"></div>
                        <div><label class="reg-label block text-[13px] font-semibold text-gray-700 mb-1.5">Accountable Officer <?= bpis_reg_required_mark() ?></label>
                            <input type="text" name="accountable_officer" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" placeholder="Full name of accountable officer" required value="<?= htmlspecialchars((string) ($_POST['accountable_officer'] ?? '')) ?>"></div>
                        <div><label class="reg-label block text-[13px] font-semibold text-gray-700 mb-1.5">Acquisition mode <?= bpis_reg_required_mark() ?></label>
                            <select name="acquisition_mode" id="acquisitionMode" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" required>
                                <option value="">Select acquisition mode</option>
                                <?php foreach (bpis_get_acquisition_modes() as $key => $mode): ?>
                                    <option value="<?= htmlspecialchars($key) ?>"<?= (($_POST['acquisition_mode'] ?? '') === $key) ? ' selected' : '' ?>><?= htmlspecialchars($mode['label']) ?></option>
                                <?php endforeach; ?>
                            </select></div>
                    </div>
                    
                    <div id="purchasedSection" class="mode-fields-group hidden">
                        <h4><i class="fa-solid fa-receipt"></i> Purchase Details</h4>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div><label class="reg-label block text-[13px] font-semibold text-gray-700 mb-1.5">Cheque no. <span class="acq-required"><?= bpis_reg_required_mark() ?></span></label><input type="text" name="cheque_number" id="chequeNumber" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" value="<?= htmlspecialchars((string) ($_POST['cheque_number'] ?? '')) ?>"></div>
                            <div><label class="reg-label block text-[13px] font-semibold text-gray-700 mb-1.5">Voucher no. <span class="acq-required"><?= bpis_reg_required_mark() ?></span></label><input type="text" name="voucher_number" id="voucherNumber" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" value="<?= htmlspecialchars((string) ($_POST['voucher_number'] ?? '')) ?>"></div>
                            <div><label class="reg-label block text-[13px] font-semibold text-gray-700 mb-1.5">Cash (₱) <span class="acq-required"><?= bpis_reg_required_mark() ?></span></label><input type="number" step="0.01" min="0" name="cash_amount" id="cashAmount" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" value="<?= htmlspecialchars((string) ($_POST['cash_amount'] ?? '')) ?>"></div>
                            <div><label class="reg-label block text-[13px] font-semibold text-gray-700 mb-1.5">Shop <span class="acq-required"><?= bpis_reg_required_mark() ?></span></label><input type="text" name="shop_name" id="shopName" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" value="<?= htmlspecialchars((string) ($_POST['shop_name'] ?? '')) ?>"></div>
                            <div><label class="reg-label block text-[13px] font-semibold text-gray-700 mb-1.5">Purchaser <span class="acq-required"><?= bpis_reg_required_mark() ?></span></label><input type="text" name="purchaser_name" id="purchaserName" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" value="<?= htmlspecialchars((string) ($_POST['purchaser_name'] ?? '')) ?>"></div>
                            <div><label class="reg-label block text-[13px] font-semibold text-gray-700 mb-1.5">Proof of purchase <span class="acq-required"><?= bpis_reg_required_mark() ?></span></label><input type="file" name="purchase_proof" id="purchaseProof" accept="image/*,.pdf" class="w-full text-sm"></div>
                            <div><label class="reg-label block text-[13px] font-semibold text-gray-700 mb-1.5">Service invoice <span class="acq-required"><?= bpis_reg_required_mark() ?></span></label><input type="file" name="service_invoice" id="serviceInvoice" accept="image/*,.pdf" class="w-full text-sm"></div>
                        </div>
                    </div>
                    
                    <div id="donatedSection" class="mode-fields-group hidden">
                        <h4><i class="fa-solid fa-gift"></i> Donation Details</h4>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div><label class="reg-label block text-[13px] font-semibold text-gray-700 mb-1.5">Donor name <span class="acq-required"><?= bpis_reg_required_mark() ?></span></label><input type="text" name="donor_name" id="donorName" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" value="<?= htmlspecialchars((string) ($_POST['donor_name'] ?? '')) ?>"></div>
                            <div><label class="reg-label block text-[13px] font-semibold text-gray-700 mb-1.5">Donation date <span class="acq-required"><?= bpis_reg_required_mark() ?></span></label><input type="date" name="donation_date" id="donationDate" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" value="<?= htmlspecialchars((string) ($_POST['donation_date'] ?? '')) ?>"></div>
                            <div><label class="reg-label block text-[13px] font-semibold text-gray-700 mb-1.5">Deed of donation <span class="acq-required"><?= bpis_reg_required_mark() ?></span></label><input type="file" name="deed_of_donation" id="deedOfDonation" accept="image/*,.pdf" class="w-full text-sm"></div>
                        </div>
                    </div>
                    
                    <div id="transferredSection" class="mode-fields-group hidden">
                        <h4><i class="fa-solid fa-exchange-alt"></i> Transfer Details</h4>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div><label class="reg-label block text-[13px] font-semibold text-gray-700 mb-1.5">Transfer from agency <span class="acq-required"><?= bpis_reg_required_mark() ?></span></label><input type="text" name="transfer_from_agency" id="transferFromAgency" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" value="<?= htmlspecialchars((string) ($_POST['transfer_from_agency'] ?? '')) ?>"></div>
                            <div><label class="reg-label block text-[13px] font-semibold text-gray-700 mb-1.5">Transfer date <span class="acq-required"><?= bpis_reg_required_mark() ?></span></label><input type="date" name="transfer_date" id="transferDate" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" value="<?= htmlspecialchars((string) ($_POST['transfer_date'] ?? '')) ?>"></div>
                            <div><label class="reg-label block text-[13px] font-semibold text-gray-700 mb-1.5">Transfer document <span class="acq-required"><?= bpis_reg_required_mark() ?></span></label><input type="file" name="transfer_document" id="transferDocument" accept="image/*,.pdf" class="w-full text-sm"></div>
                        </div>
                    </div>
                    
                    <div id="constructedSection" class="mode-fields-group hidden">
                        <h4><i class="fa-solid fa-hard-hat"></i> Construction Details</h4>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div><label class="reg-label block text-[13px] font-semibold text-gray-700 mb-1.5">Contractor name <span class="acq-required"><?= bpis_reg_required_mark() ?></span></label><input type="text" name="contractor_name" id="contractorName" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" value="<?= htmlspecialchars((string) ($_POST['contractor_name'] ?? '')) ?>"></div>
                            <div><label class="reg-label block text-[13px] font-semibold text-gray-700 mb-1.5">Contract amount (₱) <span class="acq-required"><?= bpis_reg_required_mark() ?></span></label><input type="number" step="0.01" min="0" name="contract_amount" id="contractAmount" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" value="<?= htmlspecialchars((string) ($_POST['contract_amount'] ?? '')) ?>"></div>
                            <div><label class="reg-label block text-[13px] font-semibold text-gray-700 mb-1.5">Project contract <span class="acq-required"><?= bpis_reg_required_mark() ?></span></label><input type="file" name="project_contract" id="projectContract" accept="image/*,.pdf" class="w-full text-sm"></div>
                        </div>
                    </div>
                    
                    <div id="leasedSection" class="mode-fields-group hidden">
                        <h4><i class="fa-solid fa-file-signature"></i> Lease Details</h4>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div><label class="reg-label block text-[13px] font-semibold text-gray-700 mb-1.5">Lessor name <span class="acq-required"><?= bpis_reg_required_mark() ?></span></label><input type="text" name="lessor_name" id="lessorName" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" value="<?= htmlspecialchars((string) ($_POST['lessor_name'] ?? '')) ?>"></div>
                            <div><label class="reg-label block text-[13px] font-semibold text-gray-700 mb-1.5">Lease period start <span class="acq-required"><?= bpis_reg_required_mark() ?></span></label><input type="date" name="lease_period_start" id="leasePeriodStart" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" value="<?= htmlspecialchars((string) ($_POST['lease_period_start'] ?? '')) ?>"></div>
                            <div><label class="reg-label block text-[13px] font-semibold text-gray-700 mb-1.5">Lease period end <span class="acq-required"><?= bpis_reg_required_mark() ?></span></label><input type="date" name="lease_period_end" id="leasePeriodEnd" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" value="<?= htmlspecialchars((string) ($_POST['lease_period_end'] ?? '')) ?>"></div>
                            <div><label class="reg-label block text-[13px] font-semibold text-gray-700 mb-1.5">Lease contract <span class="acq-required"><?= bpis_reg_required_mark() ?></span></label><input type="file" name="lease_contract" id="leaseContract" accept="image/*,.pdf" class="w-full text-sm"></div>
                        </div>
                    </div>
                    
                    <div id="repossessedSection" class="mode-fields-group hidden">
                        <h4><i class="fa-solid fa-gavel"></i> Repossession Details</h4>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div><label class="reg-label block text-[13px] font-semibold text-gray-700 mb-1.5">Previous owner <span class="acq-required"><?= bpis_reg_required_mark() ?></span></label><input type="text" name="previous_owner" id="previousOwner" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" value="<?= htmlspecialchars((string) ($_POST['previous_owner'] ?? '')) ?>"></div>
                            <div><label class="reg-label block text-[13px] font-semibold text-gray-700 mb-1.5">Repossession date <span class="acq-required"><?= bpis_reg_required_mark() ?></span></label><input type="date" name="repossession_date" id="repossessionDate" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" value="<?= htmlspecialchars((string) ($_POST['repossession_date'] ?? '')) ?>"></div>
                            <div><label class="reg-label block text-[13px] font-semibold text-gray-700 mb-1.5">Court order <span class="acq-required"><?= bpis_reg_required_mark() ?></span></label><input type="file" name="court_order" id="courtOrder" accept="image/*,.pdf" class="w-full text-sm"></div>
                        </div>
                    </div>
                    
                    <div id="seizedSection" class="mode-fields-group hidden">
                        <h4><i class="fa-solid fa-handcuffs"></i> Seizure Details</h4>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div><label class="reg-label block text-[13px] font-semibold text-gray-700 mb-1.5">Seizure authority <span class="acq-required"><?= bpis_reg_required_mark() ?></span></label><input type="text" name="seizure_authority" id="seizureAuthority" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" value="<?= htmlspecialchars((string) ($_POST['seizure_authority'] ?? '')) ?>"></div>
                            <div><label class="reg-label block text-[13px] font-semibold text-gray-700 mb-1.5">Seizure date <span class="acq-required"><?= bpis_reg_required_mark() ?></span></label><input type="date" name="seizure_date" id="seizureDate" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" value="<?= htmlspecialchars((string) ($_POST['seizure_date'] ?? '')) ?>"></div>
                            <div><label class="reg-label block text-[13px] font-semibold text-gray-700 mb-1.5">Seizure document <span class="acq-required"><?= bpis_reg_required_mark() ?></span></label><input type="file" name="seizure_document" id="seizureDocument" accept="image/*,.pdf" class="w-full text-sm"></div>
                        </div>
                    </div>
                    
                    <div id="negotiatedSection" class="mode-fields-group hidden">
                        <h4><i class="fa-solid fa-handshake"></i> Negotiated Sale Details</h4>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div><label class="reg-label block text-[13px] font-semibold text-gray-700 mb-1.5">Seller name <span class="acq-required"><?= bpis_reg_required_mark() ?></span></label><input type="text" name="seller_name" id="sellerName" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" value="<?= htmlspecialchars((string) ($_POST['seller_name'] ?? '')) ?>"></div>
                            <div><label class="reg-label block text-[13px] font-semibold text-gray-700 mb-1.5">Negotiation date <span class="acq-required"><?= bpis_reg_required_mark() ?></span></label><input type="date" name="negotiation_date" id="negotiationDate" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" value="<?= htmlspecialchars((string) ($_POST['negotiation_date'] ?? '')) ?>"></div>
                            <div><label class="reg-label block text-[13px] font-semibold text-gray-700 mb-1.5">Sales agreement <span class="acq-required"><?= bpis_reg_required_mark() ?></span></label><input type="file" name="sales_agreement" id="salesAgreement" accept="image/*,.pdf" class="w-full text-sm"></div>
                        </div>
                    </div>
                    
                    <div id="aidSection" class="mode-fields-group hidden">
                        <h4><i class="fa-solid fa-hands-helping"></i> Aid Details</h4>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div><label class="reg-label block text-[13px] font-semibold text-gray-700 mb-1.5">Aid agency <span class="acq-required"><?= bpis_reg_required_mark() ?></span></label><input type="text" name="aid_agency" id="aidAgency" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" value="<?= htmlspecialchars((string) ($_POST['aid_agency'] ?? '')) ?>"></div>
                            <div><label class="reg-label block text-[13px] font-semibold text-gray-700 mb-1.5">Aid type <span class="acq-required"><?= bpis_reg_required_mark() ?></span></label>
                                <select name="aid_type" id="aidType" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                                    <option value="">Select aid type</option>
                                    <option value="Foreign Aid">Foreign Aid</option>
                                    <option value="National Aid">National Aid</option>
                                    <option value="Local Aid">Local Aid</option>
                                    <option value="Private Donation">Private Donation</option>
                                </select>
                            </div>
                            <div><label class="reg-label block text-[13px] font-semibold text-gray-700 mb-1.5">Aid date <span class="acq-required"><?= bpis_reg_required_mark() ?></span></label><input type="date" name="aid_date" id="aidDate" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" value="<?= htmlspecialchars((string) ($_POST['aid_date'] ?? '')) ?>"></div>
                            <div><label class="reg-label block text-[13px] font-semibold text-gray-700 mb-1.5">Aid document <span class="acq-required"><?= bpis_reg_required_mark() ?></span></label><input type="file" name="aid_document" id="aidDocument" accept="image/*,.pdf" class="w-full text-sm"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="border-t border-gray-200 p-6 flex justify-end gap-3 bg-gray-50">
                <button type="reset" class="px-6 py-2.5 border border-gray-300 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-100">Clear</button>
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium text-sm py-2.5 px-8 rounded-lg shadow-sm">Register & Generate QR</button>
            </div>
        </form>
    </div>

    <div id="qrModal" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm z-[1000] flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-7xl max-h-[90vh] flex flex-col">
            <div class="flex justify-between items-center p-6 border-b border-gray-100 no-print">
                <h2 class="text-xl font-bold text-gray-800 flex items-center gap-2"><i data-lucide="qr-code" class="text-blue-600"></i> Generated Property Tags</h2>
                <button type="button" onclick="cancelModal()" class="text-gray-400 hover:text-red-500"><i data-lucide="x"></i></button>
            </div>
            
            <div class="p-6 overflow-y-auto custom-scrollbar flex-grow bg-gray-50/50">
                <p class="text-sm text-gray-600 mb-3">Review generated tags before finishing. Photos were selected on the registration form.</p>
                <div id="unitPhotoPreview" class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-4 hidden"></div>
                <div id="qrGrid" class="grid grid-cols-7 gap-4"></div>
            </div>

            <div class="p-5 border-t border-gray-100 bg-white flex justify-between items-center rounded-b-xl no-print">
                <button id="printAllTagsBtn" class="px-5 py-2.5 border-2 border-gray-200 text-gray-700 font-medium rounded-lg text-sm flex items-center gap-2">
                    <i data-lucide="printer" class="w-4 h-4"></i> Print Tags
                </button>
                <button onclick="closeModal()" class="px-8 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg text-sm">
                    Finish Registration
                </button>
            </div>
        </div>
    </div>

    <div id="cameraModal" class="hidden fixed inset-0 bg-black/90 backdrop-blur-sm z-[2000] flex items-center justify-center flex-col">
        <div class="camera-container bg-black rounded-xl overflow-hidden">
            <video id="cameraVideo" autoplay playsinline class="w-full max-w-2xl"></video>
            <canvas id="cameraCanvas" style="display: none;"></canvas>
        </div>
        <div class="camera-controls mt-5">
            <button id="capturePhotoBtn" class="cam-btn cam-btn-capture">
                <i class="fa-solid fa-camera"></i> Capture
            </button>
            <button id="closeCameraBtn" class="cam-btn cam-btn-close">
                <i class="fa-solid fa-times"></i> Close
            </button>
        </div>
    </div>

 <script>
        const profileTrigger = document.getElementById('profileTrigger');
        const profileMenu = document.getElementById('profileMenu');
        const logoutBtn = document.getElementById('logoutBtn');
        const logoutOverlay = document.getElementById('logoutOverlay');
        const confirmLogoutAction = document.getElementById('confirmLogoutAction');
        const cancelLogoutAction = document.getElementById('cancelLogoutAction');
        
         logoutBtn.addEventListener('click', function(e) {
            e.preventDefault();
            logoutOverlay.style.display = 'flex';
            profileMenu.classList.remove('active');
        });

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

        profileTrigger.addEventListener('click', (e) => { e.stopPropagation(); profileMenu.classList.toggle('active'); });
        document.addEventListener('click', (e) => { if (!profileMenu.contains(e.target)) profileMenu.classList.remove('active'); });
        
        const articleInput = document.getElementById('articleInput');
        const descriptionInput = document.getElementById('descriptionInput');
        const propNumberInput = document.getElementById('propNumber');
        const quantityInput = document.getElementById('quantityInput');
        const categorySelect = document.getElementById('categorySelect');
        const acquisitionMode = document.getElementById('acquisitionMode');
        const registrationForm = document.getElementById('registrationForm');
        const unitMeasureSelect = document.getElementById('unitMeasureSelect');
        
        let currentUnitIndex = null;
        let unitCameraInputs = [];
        let currentQrItems = [];

        const openCameraBtn = document.getElementById('openCameraBtn');
        const cameraModal = document.getElementById('cameraModal');
        const cameraVideo = document.getElementById('cameraVideo');
        const cameraCanvas = document.getElementById('cameraCanvas');
        const capturePhotoBtn = document.getElementById('capturePhotoBtn');
        const closeCameraBtn = document.getElementById('closeCameraBtn');
        const cameraPhotoInput = document.getElementById('cameraPhotoInput');
        
        let mediaStream = null;

        const unitMeasuresByCategory = {
            'Land & Improvements': { 'sqm': 'Square Meter', 'ha': 'Hectare', 'lot': 'Lot' },
            'Buildings & Structures': { 'sqm': 'Square Meter', 'unit': 'Unit' },
            'Machinery': { 'unit': 'Unit', 'set': 'Set' },
            'Equipment': { 'unit': 'Unit', 'set': 'Set' },
            'IT Equipment': { 'pc': 'Piece', 'unit': 'Unit', 'set': 'Set' },
            'Office Equipment': { 'pc': 'Piece', 'unit': 'Unit', 'set': 'Set' },
            'Furniture & Fixtures': { 'pc': 'Piece', 'set': 'Set' },
            'Motor Vehicles / Delivery Truck': { 'unit': 'Unit', 'vehicle': 'Vehicle' },
            'Tools': { 'pc': 'Piece', 'set': 'Set' }
        };

        function updateUnitMeasures() {
            const category = categorySelect.value;
            const select = unitMeasureSelect;
            const currentValue = select.value;
            
            select.innerHTML = '<option value="">Select unit</option>';
            
            if (category && unitMeasuresByCategory[category]) {
                const units = unitMeasuresByCategory[category];
                for (const [value, label] of Object.entries(units)) {
                    const option = document.createElement('option');
                    option.value = value;
                    option.textContent = label;
                    if (value === currentValue) {
                        option.selected = true;
                    }
                    select.appendChild(option);
                }
            } else {
                const defaultUnits = { 'pc': 'Piece', 'unit': 'Unit', 'set': 'Set' };
                for (const [value, label] of Object.entries(defaultUnits)) {
                    const option = document.createElement('option');
                    option.value = value;
                    option.textContent = label;
                    if (value === currentValue) {
                        option.selected = true;
                    }
                    select.appendChild(option);
                }
            }
        }

        categorySelect.addEventListener('change', function() {
            syncPropertyPrefixFromCategory();
            updateUnitMeasures();
        });

        async function startCamera() {
            try {
                if (mediaStream) {
                    mediaStream.getTracks().forEach(track => track.stop());
                }
                mediaStream = await navigator.mediaDevices.getUserMedia({ 
                    video: { 
                        facingMode: 'environment'
                    } 
                });
                cameraVideo.srcObject = mediaStream;
            } catch (err) {
                console.error("Error accessing camera: ", err);
                try {
                    mediaStream = await navigator.mediaDevices.getUserMedia({ video: true });
                    cameraVideo.srcObject = mediaStream;
                } catch (fallbackErr) {
                    alert("Unable to access camera. Please make sure you have granted camera permissions.");
                }
            }
        }

        function stopCamera() {
            if (mediaStream) {
                mediaStream.getTracks().forEach(track => track.stop());
                mediaStream = null;
            }
            cameraVideo.srcObject = null;
        }

        openCameraBtn.addEventListener('click', () => {
            currentUnitIndex = null;
            cameraModal.classList.remove('hidden');
            startCamera();
        });

        capturePhotoBtn.addEventListener('click', () => {
            const context = cameraCanvas.getContext('2d');
            cameraCanvas.width = cameraVideo.videoWidth;
            cameraCanvas.height = cameraVideo.videoHeight;
            context.drawImage(cameraVideo, 0, 0, cameraCanvas.width, cameraCanvas.height);
            
            const imageData = cameraCanvas.toDataURL('image/jpeg', 0.9);
            
            if (currentUnitIndex !== null) {
                const unitCameraInput = document.getElementById(`unit_camera_photo_${currentUnitIndex}`);
                if (unitCameraInput) {
                    unitCameraInput.value = imageData;
                    const previewImg = document.getElementById(`unit_photo_preview_${currentUnitIndex}`);
                    if (previewImg) {
                        previewImg.src = imageData;
                        previewImg.classList.remove('hidden');
                    }
                    const previewContainer = document.getElementById(`unit_photo_preview_container_${currentUnitIndex}`);
                    if (previewContainer) {
                        previewContainer.classList.remove('hidden');
                    }
                    const fileInput = document.getElementById(`unit_photo_${currentUnitIndex}`);
                    if (fileInput) fileInput.value = '';
                }
            } else {
                cameraPhotoInput.value = imageData;
                const assetPhotoPreview = document.getElementById('assetPhotoPreview');
                const assetPhotoPreviewImg = document.getElementById('assetPhotoPreviewImg');
                assetPhotoPreviewImg.src = imageData;
                assetPhotoPreview.classList.remove('hidden');
                const assetPhotoInput = document.getElementById('assetPhotoInput');
                assetPhotoInput.value = '';
            }
            
            cameraModal.classList.add('hidden');
            stopCamera();
            currentUnitIndex = null;
        });

        closeCameraBtn.addEventListener('click', () => {
            cameraModal.classList.add('hidden');
            stopCamera();
            currentUnitIndex = null;
        });

        function generatePropertyNumber(category) {
            const year = new Date().getFullYear();
            const categoryCodes = {
                'Land & Improvements': 'LND',
                'Buildings & Structures': 'BLD',
                'Machinery': 'MCH',
                'Equipment': 'EQP',
                'IT Equipment': 'ITE',
                'Office Equipment': 'OFE',
                'Furniture & Fixtures': 'FNF',
                'Motor Vehicles / Delivery Truck': 'MVH',
                'Tools': 'TLS'
            };
            const code = categoryCodes[category] || 'AST';
            const sequence = '001';
            return `PROP-${year}-${code}-${sequence}`;
        }

        function syncPropertyPrefixFromCategory() {
            if (propNumberInput.dataset.userEdited === '1') return;
            if (categorySelect && categorySelect.value) {
                var prefix = generatePropertyNumber(categorySelect.value);
                propNumberInput.value = prefix;
            }
        }

        function toggleAcquisitionSections() {
            const mode = acquisitionMode.value;
            const sections = {
                'Purchased': 'purchasedSection',
                'Donated': 'donatedSection',
                'Transferred': 'transferredSection',
                'Constructed/Built': 'constructedSection',
                'Leased': 'leasedSection',
                'Repossessed': 'repossessedSection',
                'Seized/Forfeited': 'seizedSection',
                'Negotiated': 'negotiatedSection',
                'Received as aid': 'aidSection'
            };
            
            Object.values(sections).forEach(sectionId => {
                const section = document.getElementById(sectionId);
                if (section) section.classList.add('hidden');
            });
            
            if (sections[mode]) {
                const selectedSection = document.getElementById(sections[mode]);
                if (selectedSection) selectedSection.classList.remove('hidden');
            }
            
            const requiredSpans = document.querySelectorAll('.acq-required');
            const allInputs = document.querySelectorAll('#purchasedSection input, #donatedSection input, #transferredSection input, #constructedSection input, #leasedSection input, #repossessedSection input, #seizedSection input, #negotiatedSection input, #aidSection input, #purchasedSection select, #aidSection select');
            
            allInputs.forEach(input => {
                input.required = false;
            });
            requiredSpans.forEach(span => span.style.display = 'none');
            
            if (sections[mode]) {
                const visibleSection = document.getElementById(sections[mode]);
                const visibleInputs = visibleSection.querySelectorAll('input, select');
                const visibleRequiredSpans = visibleSection.querySelectorAll('.acq-required');
                
                visibleInputs.forEach(input => {
                    if (input.type !== 'file' || input.id.includes('proof') || input.id.includes('document') || input.id.includes('contract') || input.id.includes('agreement')) {
                        input.required = true;
                    }
                });
                visibleRequiredSpans.forEach(span => span.style.display = 'inline');
            }
        }

        propNumberInput.addEventListener('input', function () {
            if (propNumberInput.value.trim() === '') {
                propNumberInput.dataset.userEdited = '';
                syncPropertyPrefixFromCategory();
                return;
            }
            propNumberInput.dataset.userEdited = '1';
        });

        acquisitionMode.addEventListener('change', toggleAcquisitionSections);
        
        registrationForm.addEventListener('reset', function () {
            propNumberInput.dataset.userEdited = '';
            requestAnimationFrame(syncPropertyPrefixFromCategory);
        });

        syncPropertyPrefixFromCategory();
        toggleAcquisitionSections();
        updateUnitMeasures();

        function getQuantityFromInput() {
            const parsed = quantityInput ? parseInt(quantityInput.value, 10) : 1;
            if (isNaN(parsed) || parsed < 1) return 1;
            return parsed;
        }

        const assetPhotoInput = document.getElementById('assetPhotoInput');
        const assetPhotoPreview = document.getElementById('assetPhotoPreview');
        const assetPhotoPreviewImg = document.getElementById('assetPhotoPreviewImg');
        const assetPhotoClear = document.getElementById('assetPhotoClear');
        const unitPhotosBlock = document.getElementById('unitPhotosBlock');
        const unitPhotosForm = document.getElementById('unitPhotosForm');

        function syncUnitPhotoFields() {
            if (!unitPhotosForm || !unitPhotosBlock) return;
            const count = getQuantityFromInput();
            const prefix = (propNumberInput && propNumberInput.value.trim()) ? propNumberInput.value.trim() : 'PROP';
            unitPhotosForm.innerHTML = '';
            if (count <= 1) {
                unitPhotosBlock.classList.add('hidden');
                return;
            }
            unitPhotosBlock.classList.remove('hidden');
            for (let i = 1; i <= count; i++) {
                const tag = `${prefix}-${i.toString().padStart(3, '0')}`;
                const unitIndex = i;
                unitPhotosForm.insertAdjacentHTML('beforeend', `
                    <div class="border border-gray-200 rounded-lg p-3 bg-gray-50/80">
                        <div class="flex justify-between items-center mb-2">
                            <span class="font-semibold text-gray-700 text-sm">${tag}</span>
                        </div>
                        <div class="flex gap-2 items-center">
                            <input type="file" name="unit_photo[]" id="unit_photo_${unitIndex}" accept="image/jpeg,image/png,image/webp" class="flex-1 text-xs">
                            <button type="button" class="unit-camera-btn bg-green-600 hover:bg-green-700 text-white text-xs py-1.5 px-3 rounded-lg flex items-center gap-1" data-unit-index="${unitIndex}">
                                <i class="fa-solid fa-camera text-[10px]"></i> Camera
                            </button>
                        </div>
                        <input type="hidden" name="unit_camera_photo[${unitIndex}]" id="unit_camera_photo_${unitIndex}" value="">
                        <div id="unit_photo_preview_container_${unitIndex}" class="mt-2 hidden">
                            <img id="unit_photo_preview_${unitIndex}" src="" alt="Unit preview" class="max-h-24 rounded-lg border border-gray-200 object-contain bg-gray-50">
                            <button type="button" class="unit-photo-clear mt-1 text-xs text-red-600 hover:text-red-700" data-unit-index="${unitIndex}">Remove</button>
                        </div>
                    </div>
                `);
            }
            
            document.querySelectorAll('.unit-camera-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const unitIndex = parseInt(this.getAttribute('data-unit-index'), 10);
                    currentUnitIndex = unitIndex;
                    cameraModal.classList.remove('hidden');
                    startCamera();
                });
            });
            
            document.querySelectorAll('.unit-photo-clear').forEach(btn => {
                btn.addEventListener('click', function() {
                    const unitIndex = parseInt(this.getAttribute('data-unit-index'), 10);
                    const cameraInput = document.getElementById(`unit_camera_photo_${unitIndex}`);
                    const fileInput = document.getElementById(`unit_photo_${unitIndex}`);
                    const previewImg = document.getElementById(`unit_photo_preview_${unitIndex}`);
                    const previewContainer = document.getElementById(`unit_photo_preview_container_${unitIndex}`);
                    
                    if (cameraInput) cameraInput.value = '';
                    if (fileInput) fileInput.value = '';
                    if (previewImg) previewImg.src = '';
                    if (previewContainer) previewContainer.classList.add('hidden');
                });
            });
            
            for (let i = 1; i <= count; i++) {
                const fileInput = document.getElementById(`unit_photo_${i}`);
                if (fileInput) {
                    fileInput.addEventListener('change', function() {
                        const file = this.files && this.files[0];
                        const previewImg = document.getElementById(`unit_photo_preview_${i}`);
                        const previewContainer = document.getElementById(`unit_photo_preview_container_${i}`);
                        const cameraInput = document.getElementById(`unit_camera_photo_${i}`);
                        
                        if (file) {
                            const reader = new FileReader();
                            reader.onload = function(e) {
                                if (previewImg) previewImg.src = e.target.result;
                                if (previewContainer) previewContainer.classList.remove('hidden');
                            };
                            reader.readAsDataURL(file);
                            if (cameraInput) cameraInput.value = '';
                        } else {
                            if (previewImg) previewImg.src = '';
                            if (previewContainer) previewContainer.classList.add('hidden');
                        }
                    });
                }
            }
        }

        if (assetPhotoInput) {
            assetPhotoInput.addEventListener('change', function () {
                const file = this.files && this.files[0];
                if (!file || !assetPhotoPreview || !assetPhotoPreviewImg) return;
                assetPhotoPreviewImg.src = URL.createObjectURL(file);
                assetPhotoPreview.classList.remove('hidden');
                const cameraPhotoInput = document.getElementById('cameraPhotoInput');
                if (cameraPhotoInput) cameraPhotoInput.value = '';
            });
        }
        if (assetPhotoClear && assetPhotoInput) {
            assetPhotoClear.addEventListener('click', function () {
                assetPhotoInput.value = '';
                const cameraPhotoInput = document.getElementById('cameraPhotoInput');
                if (cameraPhotoInput) cameraPhotoInput.value = '';
                if (assetPhotoPreview) assetPhotoPreview.classList.add('hidden');
                if (assetPhotoPreviewImg) assetPhotoPreviewImg.src = '';
            });
        }
        if (quantityInput) {
            quantityInput.addEventListener('input', syncUnitPhotoFields);
            quantityInput.addEventListener('change', syncUnitPhotoFields);
        }
        if (propNumberInput) {
            propNumberInput.addEventListener('input', syncUnitPhotoFields);
        }
        syncUnitPhotoFields();

        registrationForm.addEventListener('submit', function(e) {
            if (!registrationForm.checkValidity()) {
                e.preventDefault();
                registrationForm.reportValidity();
                return;
            }
            if (!this.dataset.confirmed) {
                e.preventDefault();
                const count = getQuantityFromInput();
                const propDescription = descriptionInput.value.trim();
                showQRModal(count, propDescription);
            }
        });

        function escapeHtml(str) {
            if (!str) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        function truncateText(text, maxLength) {
            if (!text) return '';
            if (text.length <= maxLength) return text;
            return text.substring(0, maxLength - 3) + '...';
        }

        function isPlaceholderBrandModel(v) {
            v = String(v || '').trim().toLowerCase();
            if (!v) return true;
            return /^(n\/?\s*a\.?|n\/s|na|none|unknown|tbd|nil|-)$/.test(v);
        }
        
        function normalizeBrandModel(v) {
            return isPlaceholderBrandModel(v) ? '' : String(v || '').trim();
        }
        
        function buildQrPayload(tag, brand, model) {
            brand = normalizeBrandModel(brand);
            model = normalizeBrandModel(model);
            if (!brand && !model) return tag;
            return tag + '|' + brand + '|' + model;
        }

        function showQRModal(count, description) {
            const grid = document.getElementById('qrGrid');
            const propPrefix = document.getElementById('propNumber').value || 'PROP';
            const brand = document.getElementById('brandInput') ? document.getElementById('brandInput').value.trim() : '';
            const model = document.getElementById('modelInput') ? document.getElementById('modelInput').value.trim() : '';
            grid.innerHTML = '';

            const normalizedBrand = normalizeBrandModel(brand);
            const normalizedModel = normalizeBrandModel(model);
            
            currentQrItems = [];

            for (let i = 1; i <= count; i++) {
                let finalId = count > 1 ? `${propPrefix}-${i.toString().padStart(3, '0')}` : propPrefix;
                
                if (finalId.length > 28) {
                    finalId = finalId.substring(0, 25) + '...';
                }
                
                const qrData = buildQrPayload(finalId, brand, model);
                const qrUrl = `https://api.qrserver.com/v1/create-qr-code/?size=120x120&data=${encodeURIComponent(qrData)}`;
                
                const truncatedDescription = truncateText(description, 45);
                
                currentQrItems.push({
                    code: finalId,
                    qr_data: qrData,
                    description: description,
                    brand: normalizedBrand,
                    model: normalizedModel
                });
                
                let descriptionHtml = '';
                if (truncatedDescription) {
                    descriptionHtml = `<div class="tag-description">
                        <span class="tag-description-label">Description:</span> 
                        <span>${escapeHtml(truncatedDescription)}</span>
                    </div>`;
                }
                
                let brandHtml = '';
                if (normalizedBrand) {
                    brandHtml = `<div class="tag-brand-line">
                        <span class="tag-brand-label">Brand:</span> 
                        <span>${escapeHtml(truncateText(normalizedBrand, 25))}</span>
                    </div>`;
                }
                
                let modelHtml = '';
                if (normalizedModel) {
                    modelHtml = `<div class="tag-model-line">
                        <span class="tag-model-label">Model:</span> 
                        <span>${escapeHtml(truncateText(normalizedModel, 25))}</span>
                    </div>`;
                }

                grid.insertAdjacentHTML('beforeend', `
                <div class="qr-tag">
                    <img src="${qrUrl}" alt="QR" class="qr-image">
                    <div class="tag-id-text" title="${escapeHtml(finalId)}">Id: ${escapeHtml(finalId)}</div>
                    ${descriptionHtml}
                    ${brandHtml}
                    ${modelHtml}
                </div>
            `);
            }
            document.getElementById('qrModal').classList.remove('hidden');
            lucide.createIcons();
        }

        function closeModal() {
            registrationForm.dataset.confirmed = "true";
            registrationForm.submit();
        }

        function cancelModal() {
            document.getElementById('qrModal').classList.add('hidden');
        }

        const printAllTagsBtn = document.getElementById('printAllTagsBtn');
        const printArea = document.getElementById('printArea');
        
        function formatPropertyId(id) {
            return String(id || '').trim();
        }
        
        function itemBrandModel(item) {
            return { brand: item.brand || '', model: item.model || '' };
        }
        
        if (printAllTagsBtn) {
            printAllTagsBtn.addEventListener('click', () => {
                if (!currentQrItems || currentQrItems.length === 0) return;
                
                printArea.innerHTML = '';
                
                const container = document.createElement('div');
                container.className = 'qr-tag-container';
                
                currentQrItems.forEach(item => {
                    const qrPayload = item.qr_data || item.code;
                    const qrUrl = `https://api.qrserver.com/v1/create-qr-code/?size=120x120&data=${encodeURIComponent(qrPayload)}`;
                    const tagCode = escapeHtml(formatPropertyId(item.code));
                    const descriptionText = escapeHtml(item.description || '');
                    const meta = itemBrandModel(item);
                    
                    const tagDiv = document.createElement('div');
                    tagDiv.className = 'qr-tag-card';
                    tagDiv.innerHTML = `
                        <div class="qr-code-wrapper">
                            <img src="${qrUrl}" alt="QR Code" class="qr-code-image" onerror="this.src='data:image/svg+xml,%3Csvg xmlns=\\'http://www.w3.org/2000/svg\\' width=\\'120\\' height=\\'120\\' viewBox=\\'0 0 120 120\\'%3E%3Crect width=\\'120\\' height=\\'120\\' fill=\\'%23f0f0f0\\'/%3E%3Ctext x=\\'60\\' y=\\'60\\' text-anchor=\\'middle\\' dy=\\'.3em\\' fill=\\'%23999\\' font-size=\\'12\\'%3EQR%3C/text%3E%3C/svg%3E'">
                        </div>
                        <div class="tag-id"><strong>ID:</strong> ${tagCode}</div>
                        <div class="tag-line"><strong>Description:</strong> ${descriptionText || '—'}</div>
                        ${meta.brand ? `<div class="tag-line"><strong>Brand:</strong> ${escapeHtml(meta.brand)}</div>` : ''}
                        ${meta.model ? `<div class="tag-line"><strong>Model:</strong> ${escapeHtml(meta.model)}</div>` : ''}
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
        }

        lucide.createIcons();
</script>
<script src="../js/treasurer_mobile_sidebar.js"></script>
<script src="../realtime_notifications.js"></script>
</body>
</html>