<?php

require_once __DIR__ . '/asset_list_helpers.php';
require_once __DIR__ . '/asset_units_helpers.php';

if (!function_exists('bpis_asset_view_payload')) {
    /**
     * Full read-only asset details for Treasurer view modal.
     *
     * @param array<string,mixed> $row Asset row from SELECT *
     * @return array<string,mixed>
     */
    function bpis_asset_view_payload(mysqli $conn, array $row, ?array $photo_map = null): array
    {
        $id = (int) ($row['id'] ?? 0);
        $qty = max(1, (int) ($row['quantity'] ?? 1));
        $unit_value = (float) ($row['unit_value'] ?? 0);
        $cash = (float) ($row['cash_amount'] ?? 0);
        $date_raw = (string) ($row['date_acquired'] ?? '');
        $date_display = $date_raw !== '' ? date('F j, Y', strtotime($date_raw)) : '—';

        $brand = bpis_normalize_brand_model_value($row['brand'] ?? '');
        $model = bpis_normalize_brand_model_value($row['model'] ?? '');

        $units = [];
        foreach (bpis_load_asset_subitems($conn, $row) as $unit) {
            $units[] = [
                'unit_tag' => (string) ($unit['code'] ?? ''),
                'photo_url' => (string) ($unit['photo_url'] ?? bpis_asset_placeholder_image_url()),
            ];
        }

        return [
            'id' => $id,
            'article' => (string) ($row['article'] ?? ''),
            'description' => (string) ($row['description'] ?? ''),
            'quantity' => $qty,
            'category' => (string) ($row['category'] ?? ''),
            'property_number' => (string) ($row['property_number'] ?? ''),
            'unit_measure' => (string) ($row['unit_measure'] ?? 'pc'),
            'date_acquired' => $date_raw,
            'date_acquired_display' => $date_display,
            'unit_value' => number_format($unit_value, 2),
            'remarks' => (string) ($row['remarks'] ?? ''),
            'status' => (string) ($row['status'] ?? ''),
            'asset_cluster' => (string) ($row['asset_cluster'] ?? ''),
            'location' => (string) ($row['location'] ?? ''),
            'brand' => $brand,
            'model' => $model,
            'brand_model' => bpis_format_brand_model_display($brand, $model),
            'audit_remarks' => (string) ($row['audit_remarks'] ?? ''),
            'acquisition_mode' => (string) ($row['acquisition_mode'] ?? ''),
            'cheque_number' => (string) ($row['cheque_number'] ?? ''),
            'voucher_number' => (string) ($row['voucher_number'] ?? ''),
            'cash_amount' => number_format($cash, 2),
            'shop_name' => (string) ($row['shop_name'] ?? ''),
            'purchaser_name' => (string) ($row['purchaser_name'] ?? ''),
            'photo_url' => bpis_asset_photo_url($conn, $id, $photo_map),
            'purchase_proof_url' => bpis_public_upload_url($row['purchase_proof_path'] ?? null) ?? '',
            'service_invoice_url' => bpis_public_upload_url($row['service_invoice_path'] ?? null) ?? '',
            'units' => $units,
        ];
    }
}
