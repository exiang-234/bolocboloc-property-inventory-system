<?php

require_once __DIR__ . '/asset_list_helpers.php';
require_once __DIR__ . '/asset_units_helpers.php';
require_once __DIR__ . '/upload_helpers.php';
require_once __DIR__ . '/coa_labels.php';

if (!function_exists('bpis_inventory_category_options')) {
    function bpis_inventory_category_options(): array
    {
        return [
            'Land & Improvements',
            'Buildings & Structures',
            'Machinery',
            'Equipment',
            'IT Equipment',
            'Office Equipment',
            'Furniture & Fixtures',
            'Motor Vehicles / Delivery Truck',
            'Tools',
        ];
    }
}

if (!function_exists('bpis_get_acquisition_modes')) {
    function bpis_get_acquisition_modes(): array
    {
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
}

if (!function_exists('bpis_load_acquisition_data_for_edit')) {
    function bpis_load_acquisition_data_for_edit(mysqli $conn, int $asset_id): array
    {
        $query = "SELECT * FROM asset_acquisition_details WHERE asset_id = $asset_id LIMIT 1";
        $result = mysqli_query($conn, $query);
        if ($result && mysqli_num_rows($result) > 0) {
            return mysqli_fetch_assoc($result);
        }
        return [];
    }
}

if (!function_exists('bpis_update_acquisition_details')) {
    function bpis_update_acquisition_details(mysqli $conn, int $asset_id, string $acquisition_mode, array $data): bool
    {
        $existing_query = "SELECT id FROM asset_acquisition_details WHERE asset_id = $asset_id LIMIT 1";
        $existing_result = mysqli_query($conn, $existing_query);
        $exists = ($existing_result && mysqli_num_rows($existing_result) > 0);
        
        $acquisition_modes = bpis_get_acquisition_modes();
        $mode_fields = $acquisition_modes[$acquisition_mode]['fields'] ?? [];
        
        if ($exists) {
            $update_fields = [];
            $update_values = [];
            $types = '';
            
            foreach ($mode_fields as $field) {
                switch($field) {
                    case 'cheque_number':
                        $update_fields[] = "cheque_number = ?";
                        $update_values[] = !empty($data['cheque_number']) ? $data['cheque_number'] : null;
                        $types .= 's';
                        break;
                    case 'voucher_number':
                        $update_fields[] = "voucher_number = ?";
                        $update_values[] = !empty($data['voucher_number']) ? $data['voucher_number'] : null;
                        $types .= 's';
                        break;
                    case 'cash_amount':
                        $update_fields[] = "cash_amount = ?";
                        $update_values[] = isset($data['cash_amount']) && $data['cash_amount'] > 0 ? (float)$data['cash_amount'] : 0;
                        $types .= 'd';
                        break;
                    case 'shop_name':
                        $update_fields[] = "shop_name = ?";
                        $update_values[] = !empty($data['shop_name']) ? $data['shop_name'] : null;
                        $types .= 's';
                        break;
                    case 'purchaser_name':
                        $update_fields[] = "purchaser_name = ?";
                        $update_values[] = !empty($data['purchaser_name']) ? $data['purchaser_name'] : null;
                        $types .= 's';
                        break;
                    case 'donor_name':
                        $update_fields[] = "donor_name = ?";
                        $update_values[] = !empty($data['donor_name']) ? $data['donor_name'] : null;
                        $types .= 's';
                        break;
                    case 'donation_date':
                        $update_fields[] = "donation_date = ?";
                        $update_values[] = !empty($data['donation_date']) ? $data['donation_date'] : null;
                        $types .= 's';
                        break;
                    case 'transfer_from_agency':
                        $update_fields[] = "transfer_from_agency = ?";
                        $update_values[] = !empty($data['transfer_from_agency']) ? $data['transfer_from_agency'] : null;
                        $types .= 's';
                        break;
                    case 'transfer_date':
                        $update_fields[] = "transfer_date = ?";
                        $update_values[] = !empty($data['transfer_date']) ? $data['transfer_date'] : null;
                        $types .= 's';
                        break;
                    case 'contractor_name':
                        $update_fields[] = "contractor_name = ?";
                        $update_values[] = !empty($data['contractor_name']) ? $data['contractor_name'] : null;
                        $types .= 's';
                        break;
                    case 'contract_amount':
                        $update_fields[] = "contract_amount = ?";
                        $update_values[] = isset($data['contract_amount']) && $data['contract_amount'] > 0 ? (float)$data['contract_amount'] : 0;
                        $types .= 'd';
                        break;
                    case 'lessor_name':
                        $update_fields[] = "lessor_name = ?";
                        $update_values[] = !empty($data['lessor_name']) ? $data['lessor_name'] : null;
                        $types .= 's';
                        break;
                    case 'lease_period_start':
                        $update_fields[] = "lease_period_start = ?";
                        $update_values[] = !empty($data['lease_period_start']) ? $data['lease_period_start'] : null;
                        $types .= 's';
                        break;
                    case 'lease_period_end':
                        $update_fields[] = "lease_period_end = ?";
                        $update_values[] = !empty($data['lease_period_end']) ? $data['lease_period_end'] : null;
                        $types .= 's';
                        break;
                    case 'previous_owner':
                        $update_fields[] = "previous_owner = ?";
                        $update_values[] = !empty($data['previous_owner']) ? $data['previous_owner'] : null;
                        $types .= 's';
                        break;
                    case 'repossession_date':
                        $update_fields[] = "repossession_date = ?";
                        $update_values[] = !empty($data['repossession_date']) ? $data['repossession_date'] : null;
                        $types .= 's';
                        break;
                    case 'seizure_authority':
                        $update_fields[] = "seizure_authority = ?";
                        $update_values[] = !empty($data['seizure_authority']) ? $data['seizure_authority'] : null;
                        $types .= 's';
                        break;
                    case 'seizure_date':
                        $update_fields[] = "seizure_date = ?";
                        $update_values[] = !empty($data['seizure_date']) ? $data['seizure_date'] : null;
                        $types .= 's';
                        break;
                    case 'seller_name':
                        $update_fields[] = "seller_name = ?";
                        $update_values[] = !empty($data['seller_name']) ? $data['seller_name'] : null;
                        $types .= 's';
                        break;
                    case 'negotiation_date':
                        $update_fields[] = "negotiation_date = ?";
                        $update_values[] = !empty($data['negotiation_date']) ? $data['negotiation_date'] : null;
                        $types .= 's';
                        break;
                    case 'aid_agency':
                        $update_fields[] = "aid_agency = ?";
                        $update_values[] = !empty($data['aid_agency']) ? $data['aid_agency'] : null;
                        $types .= 's';
                        break;
                    case 'aid_type':
                        $update_fields[] = "aid_type = ?";
                        $update_values[] = !empty($data['aid_type']) ? $data['aid_type'] : null;
                        $types .= 's';
                        break;
                    case 'aid_date':
                        $update_fields[] = "aid_date = ?";
                        $update_values[] = !empty($data['aid_date']) ? $data['aid_date'] : null;
                        $types .= 's';
                        break;
                }
            }
            
            $all_fields = ['cheque_number', 'voucher_number', 'cash_amount', 'shop_name', 'purchaser_name',
                           'donor_name', 'donation_date', 'transfer_from_agency', 'transfer_date', 
                           'contractor_name', 'contract_amount', 'lessor_name', 'lease_period_start', 
                           'lease_period_end', 'previous_owner', 'repossession_date', 'seizure_authority', 
                           'seizure_date', 'seller_name', 'negotiation_date', 'aid_agency', 'aid_type', 'aid_date'];
            
            foreach ($all_fields as $field) {
                if (!in_array($field, $mode_fields)) {
                    $update_fields[] = "$field = NULL";
                }
            }
            
            if (!empty($update_fields)) {
                $update_values[] = $asset_id;
                $types .= 'i';
                $sql = "UPDATE asset_acquisition_details SET " . implode(', ', $update_fields) . " WHERE asset_id = ?";
                $stmt = mysqli_prepare($conn, $sql);
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, $types, ...$update_values);
                    return mysqli_stmt_execute($stmt);
                }
            }
            return false;
        } else {
            $columns = ['asset_id', 'acquisition_mode'];
            $values = [$asset_id, $acquisition_mode];
            $types = ['i', 's'];
            
            foreach ($mode_fields as $field) {
                switch($field) {
                    case 'cheque_number':
                        if (!empty($data['cheque_number'])) {
                            $columns[] = 'cheque_number';
                            $values[] = $data['cheque_number'];
                            $types[] = 's';
                        }
                        break;
                    case 'voucher_number':
                        if (!empty($data['voucher_number'])) {
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
                        if (!empty($data['shop_name'])) {
                            $columns[] = 'shop_name';
                            $values[] = $data['shop_name'];
                            $types[] = 's';
                        }
                        break;
                    case 'purchaser_name':
                        if (!empty($data['purchaser_name'])) {
                            $columns[] = 'purchaser_name';
                            $values[] = $data['purchaser_name'];
                            $types[] = 's';
                        }
                        break;
                    case 'donor_name':
                        if (!empty($data['donor_name'])) {
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
                    case 'transfer_from_agency':
                        if (!empty($data['transfer_from_agency'])) {
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
                    case 'contractor_name':
                        if (!empty($data['contractor_name'])) {
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
                    case 'lessor_name':
                        if (!empty($data['lessor_name'])) {
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
                    case 'previous_owner':
                        if (!empty($data['previous_owner'])) {
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
                    case 'seizure_authority':
                        if (!empty($data['seizure_authority'])) {
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
                    case 'seller_name':
                        if (!empty($data['seller_name'])) {
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
                    case 'aid_agency':
                        if (!empty($data['aid_agency'])) {
                            $columns[] = 'aid_agency';
                            $values[] = $data['aid_agency'];
                            $types[] = 's';
                        }
                        break;
                    case 'aid_type':
                        if (!empty($data['aid_type'])) {
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
                }
            }
            
            $placeholders = array_fill(0, count($columns), '?');
            $sql = "INSERT INTO asset_acquisition_details (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
            $stmt = mysqli_prepare($conn, $sql);
            if ($stmt) {
                $types_str = implode('', $types);
                mysqli_stmt_bind_param($stmt, $types_str, ...$values);
                return mysqli_stmt_execute($stmt);
            }
            return false;
        }
    }
}

if (!function_exists('bpis_asset_edit_payload')) {
    function bpis_asset_edit_payload(mysqli $conn, array $row, ?array $photo_map = null): array
    {
        $id = (int) ($row['id'] ?? 0);
        $date = (string) ($row['date_acquired'] ?? '');
        if ($date !== '' && strlen($date) >= 10) {
            $date = substr($date, 0, 10);
        }
        
        $acq_data = bpis_load_acquisition_data_for_edit($conn, $id);
        
        return [
            'id' => $id,
            'article' => (string) ($row['article'] ?? ''),
            'description' => (string) ($row['description'] ?? ''),
            'quantity' => max(1, (int) ($row['quantity'] ?? 1)),
            'category' => (string) ($row['category'] ?? ''),
            'property_number' => (string) ($row['property_number'] ?? ''),
            'unit_measure' => (string) ($row['unit_measure'] ?? 'pc'),
            'date_acquired' => $date,
            'unit_value' => (float) ($row['unit_value'] ?? 0),
            'remarks' => (string) ($row['remarks'] ?? 'SERVICEABLE'),
            'status' => (string) ($row['status'] ?? 'Available'),
            'asset_cluster' => (string) ($row['asset_cluster'] ?? 'Movable Assets'),
            'location' => (string) ($row['location'] ?? ''),
            'brand' => (string) ($row['brand'] ?? ''),
            'model' => (string) ($row['model'] ?? ''),
            'audit_remarks' => (string) ($row['audit_remarks'] ?? ''),
            'acquisition_mode' => (string) ($row['acquisition_mode'] ?? 'Purchased'),
            'cheque_number' => $acq_data['cheque_number'] ?? '',
            'voucher_number' => $acq_data['voucher_number'] ?? '',
            'cash_amount' => (float) ($acq_data['cash_amount'] ?? 0),
            'shop_name' => $acq_data['shop_name'] ?? '',
            'purchaser_name' => $acq_data['purchaser_name'] ?? '',
            'donor_name' => $acq_data['donor_name'] ?? '',
            'donation_date' => $acq_data['donation_date'] ?? '',
            'transfer_from_agency' => $acq_data['transfer_from_agency'] ?? '',
            'transfer_date' => $acq_data['transfer_date'] ?? '',
            'contractor_name' => $acq_data['contractor_name'] ?? '',
            'contract_amount' => (float) ($acq_data['contract_amount'] ?? 0),
            'lessor_name' => $acq_data['lessor_name'] ?? '',
            'lease_period_start' => $acq_data['lease_period_start'] ?? '',
            'lease_period_end' => $acq_data['lease_period_end'] ?? '',
            'previous_owner' => $acq_data['previous_owner'] ?? '',
            'repossession_date' => $acq_data['repossession_date'] ?? '',
            'seizure_authority' => $acq_data['seizure_authority'] ?? '',
            'seizure_date' => $acq_data['seizure_date'] ?? '',
            'seller_name' => $acq_data['seller_name'] ?? '',
            'negotiation_date' => $acq_data['negotiation_date'] ?? '',
            'aid_agency' => $acq_data['aid_agency'] ?? '',
            'aid_type' => $acq_data['aid_type'] ?? '',
            'aid_date' => $acq_data['aid_date'] ?? '',
            'photo_url' => bpis_asset_photo_url($conn, $id, $photo_map),
            'units_edit' => bpis_load_asset_units_for_edit($conn, $row),
        ];
    }
}

if (!function_exists('bpis_process_inventory_asset_edit')) {
    function bpis_process_inventory_asset_edit(mysqli $conn, int $asset_id, array $post, array $files): array
    {
        if ($asset_id <= 0) {
            return ['ok' => false, 'error' => 'Invalid asset.'];
        }

        $editArticle = trim((string) ($post['edit_article'] ?? ''));
        $editDescription = trim((string) ($post['edit_description'] ?? ''));
        $editCategory = trim((string) ($post['edit_category'] ?? ''));
        $editPropNum = trim((string) ($post['edit_prop_num'] ?? ''));
        $editUom = trim((string) ($post['edit_uom'] ?? ''));
        $editDate = trim((string) ($post['edit_date_acquired'] ?? ''));
        $editRemarks = strtoupper(trim((string) ($post['edit_remarks'] ?? '')));
        $editStatus = trim((string) ($post['edit_status'] ?? 'Available'));
        $editAuditRemarks = trim((string) ($post['edit_audit_remarks'] ?? ''));
        $editLocation = trim((string) ($post['edit_location'] ?? ''));
        $editCluster = trim((string) ($post['edit_asset_cluster'] ?? 'Movable Assets'));
        $editQuantity = isset($post['edit_quantity']) ? (int) $post['edit_quantity'] : 1;
        $editUnitValue = isset($post['edit_unit_value']) ? (float) $post['edit_unit_value'] : 0.0;
        $editBrand = trim((string) ($post['edit_brand'] ?? ''));
        $editModel = trim((string) ($post['edit_model'] ?? ''));
        $editAcq = trim((string) ($post['edit_acquisition_mode'] ?? 'Purchased'));
        $editCheque = trim((string) ($post['edit_cheque_number'] ?? ''));
        $editVoucher = trim((string) ($post['edit_voucher_number'] ?? ''));
        $editCash = isset($post['edit_cash_amount']) ? (float) $post['edit_cash_amount'] : 0.0;
        $editShop = trim((string) ($post['edit_shop_name'] ?? ''));
        $editPurchaser = trim((string) ($post['edit_purchaser_name'] ?? ''));
        
        $editDonor = trim((string) ($post['edit_donor_name'] ?? ''));
        $editDonationDate = trim((string) ($post['edit_donation_date'] ?? ''));
        $editTransferFrom = trim((string) ($post['edit_transfer_from_agency'] ?? ''));
        $editTransferDate = trim((string) ($post['edit_transfer_date'] ?? ''));
        $editContractor = trim((string) ($post['edit_contractor_name'] ?? ''));
        $editContractAmount = isset($post['edit_contract_amount']) ? (float) $post['edit_contract_amount'] : 0.0;
        $editLessor = trim((string) ($post['edit_lessor_name'] ?? ''));
        $editLeaseStart = trim((string) ($post['edit_lease_period_start'] ?? ''));
        $editLeaseEnd = trim((string) ($post['edit_lease_period_end'] ?? ''));
        $editPrevOwner = trim((string) ($post['edit_previous_owner'] ?? ''));
        $editRepossessionDate = trim((string) ($post['edit_repossession_date'] ?? ''));
        $editSeizureAuthority = trim((string) ($post['edit_seizure_authority'] ?? ''));
        $editSeizureDate = trim((string) ($post['edit_seizure_date'] ?? ''));
        $editSeller = trim((string) ($post['edit_seller_name'] ?? ''));
        $editNegotiationDate = trim((string) ($post['edit_negotiation_date'] ?? ''));
        $editAidAgency = trim((string) ($post['edit_aid_agency'] ?? ''));
        $editAidType = trim((string) ($post['edit_aid_type'] ?? ''));
        $editAidDate = trim((string) ($post['edit_aid_date'] ?? ''));

        if ($editQuantity < 1) {
            $editQuantity = 1;
        }
        if ($editArticle === '') {
            return ['ok' => false, 'error' => 'Article is required.'];
        }
        if ($editDate === '') {
            $editDate = date('Y-m-d');
        }
        if ($editStatus === '') {
            $editStatus = 'Available';
        }

        $allowedRemarks = ['SERVICEABLE', 'UNDER REPAIR', 'UNSERVICEABLE/DISPOSE'];
        if (!in_array($editRemarks, $allowedRemarks, true)) {
            $editRemarks = 'SERVICEABLE';
        }
        $editItemCondition = $editRemarks;

        $unit_ids = isset($post['edit_unit_id']) && is_array($post['edit_unit_id']) ? $post['edit_unit_id'] : [];
        $unit_conditions = isset($post['edit_unit_condition']) && is_array($post['edit_unit_condition']) ? $post['edit_unit_condition'] : [];
        $unit_tags = isset($post['edit_unit_tag']) && is_array($post['edit_unit_tag']) ? $post['edit_unit_tag'] : [];
        if ($unit_ids !== []) {
            $saved_conditions = bpis_save_asset_unit_conditions($conn, $asset_id, $unit_ids, $unit_conditions, $unit_tags);
            if ($saved_conditions !== []) {
                $editRemarks = bpis_derive_asset_condition_from_units($saved_conditions, $editRemarks);
                $editItemCondition = $editRemarks;
            }
        }

        $set_parts = [
            'article = ?', 'quantity = ?', 'description = ?', 'category = ?', 'property_number = ?',
            'unit_measure = ?', 'date_acquired = ?', 'unit_value = ?', 'remarks = ?', 'item_condition = ?',
            'status = ?',
        ];
        $types = 'sisssssdsss';
        $params = [
            $editArticle, $editQuantity, $editDescription, $editCategory, $editPropNum,
            $editUom, $editDate, $editUnitValue, $editRemarks, $editItemCondition, $editStatus,
        ];

        $optional = [
            'audit_remarks' => ['s', $editAuditRemarks],
            'location' => ['s', $editLocation],
            'asset_cluster' => ['s', $editCluster !== '' ? $editCluster : 'Movable Assets'],
            'brand' => ['s', bpis_normalize_brand_model_value($editBrand)],
            'model' => ['s', bpis_normalize_brand_model_value($editModel)],
            'acquisition_mode' => ['s', $editAcq !== '' ? $editAcq : 'Purchased'],
        ];

        foreach ($optional as $col => $pair) {
            if (bpis_column_exists($conn, 'asset', $col)) {
                $set_parts[] = "{$col} = ?";
                $types .= $pair[0];
                $params[] = $pair[1];
            }
        }

        if (bpis_column_exists($conn, 'asset', 'photo_path')) {
            $new_photo = bpis_store_upload('edit_asset_photo', 'assets');
            if ($new_photo !== null && $new_photo !== '') {
                $set_parts[] = 'photo_path = ?';
                $types .= 's';
                $params[] = $new_photo;
            }
        }

        $types .= 'i';
        $params[] = $asset_id;
        $sql = 'UPDATE asset SET ' . implode(', ', $set_parts) . ' WHERE id = ?';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return ['ok' => false, 'error' => 'Could not prepare update.'];
        }

        $bind = [$types];
        foreach ($params as $k => $v) {
            $bind[] = &$params[$k];
        }
        call_user_func_array([$stmt, 'bind_param'], $bind);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        if (!$ok) {
            return ['ok' => false, 'error' => 'Could not save asset.'];
        }

        $acquisition_data = [
            'cheque_number' => $editCheque,
            'voucher_number' => $editVoucher,
            'cash_amount' => $editCash,
            'shop_name' => $editShop,
            'purchaser_name' => $editPurchaser,
            'donor_name' => $editDonor,
            'donation_date' => $editDonationDate,
            'transfer_from_agency' => $editTransferFrom,
            'transfer_date' => $editTransferDate,
            'contractor_name' => $editContractor,
            'contract_amount' => $editContractAmount,
            'lessor_name' => $editLessor,
            'lease_period_start' => $editLeaseStart,
            'lease_period_end' => $editLeaseEnd,
            'previous_owner' => $editPrevOwner,
            'repossession_date' => $editRepossessionDate,
            'seizure_authority' => $editSeizureAuthority,
            'seizure_date' => $editSeizureDate,
            'seller_name' => $editSeller,
            'negotiation_date' => $editNegotiationDate,
            'aid_agency' => $editAidAgency,
            'aid_type' => $editAidType,
            'aid_date' => $editAidDate
        ];
        
        bpis_update_acquisition_details($conn, $asset_id, $editAcq, $acquisition_data);

        if ($unit_ids === [] && $editQuantity === 1 && bpis_table_exists($conn, 'asset_units') && bpis_column_exists($conn, 'asset_units', 'unit_condition')) {
            $cond = mysqli_real_escape_string($conn, $editRemarks);
            mysqli_query($conn, "UPDATE asset_units SET unit_condition = '{$cond}' WHERE asset_id = {$asset_id}");
        }

        $total = $editQuantity * $editUnitValue;
        mysqli_query($conn, "UPDATE asset SET card_value = {$total}, total_cost = {$total} WHERE id = {$asset_id}");

        return ['ok' => true];
    }
}