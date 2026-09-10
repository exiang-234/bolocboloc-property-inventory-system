<?php

require_once __DIR__ . '/schema_bootstrap.php';
require_once __DIR__ . '/asset_photo_helpers.php';

if (!function_exists('bpis_ensure_asset_depreciation_columns')) {
    function bpis_ensure_asset_depreciation_columns(mysqli $conn): void
    {
        bpis_add_column_if_missing($conn, 'asset', 'accumulated_depreciation', 'DECIMAL(15,2) NOT NULL DEFAULT 0.00');
        bpis_add_column_if_missing($conn, 'asset', 'net_book_value', 'DECIMAL(15,2) NULL DEFAULT NULL');
        bpis_add_column_if_missing($conn, 'asset', 'total_cost', 'DECIMAL(15,2) NULL DEFAULT NULL');
    }
}

if (!function_exists('bpis_asset_display_label')) {
    function bpis_asset_display_label(array $row): string
    {
        $desc = trim((string) ($row['description'] ?? ''));
        $article = trim((string) ($row['article'] ?? ''));
        $prop = trim((string) ($row['property_number'] ?? ''));
        $id = (int) ($row['id'] ?? 0);

        $label = $desc !== '' ? $desc : ($article !== '' ? $article : ('Asset #' . $id));
        if ($prop !== '') {
            $label .= ' (' . $prop . ')';
        }
        return $label;
    }
}

if (!function_exists('bpis_list_assets_for_select')) {
    /**
     * All assets for dropdowns (newest registered first).
     *
     * @return list<array<string,mixed>>
     */
    function bpis_list_assets_for_select(mysqli $conn): array
    {
        if (!$conn || !bpis_table_exists($conn, 'asset')) {
            return [];
        }

        bpis_ensure_asset_depreciation_columns($conn);

        $select = ['id', 'description', 'article', 'unit_value', 'property_number'];
        foreach (['quantity', 'accumulated_depreciation', 'net_book_value', 'total_cost', 'created_at'] as $col) {
            if (bpis_column_exists($conn, 'asset', $col)) {
                $select[] = $col;
            }
        }

        $sql = 'SELECT ' . implode(', ', $select) . ' FROM asset ORDER BY id DESC';
        $rs = mysqli_query($conn, $sql);
        if (!$rs) {
            $sql = 'SELECT id, description, article, unit_value, property_number FROM asset ORDER BY id DESC';
            $rs = mysqli_query($conn, $sql);
        }
        if (!$rs) {
            return [];
        }

        $assets = [];
        while ($row = mysqli_fetch_assoc($rs)) {
            $assets[] = $row;
        }
        return bpis_enrich_assets_for_picker($conn, $assets);
    }
}

if (!function_exists('bpis_asset_name')) {
    /** Primary inventory label: article first, then description. */
    function bpis_asset_name(array $row): string
    {
        $article = trim((string) ($row['article'] ?? ''));
        $desc = trim((string) ($row['description'] ?? ''));
        if ($article !== '') {
            return $article;
        }
        if ($desc !== '') {
            return $desc;
        }
        return 'Asset #' . (int) ($row['id'] ?? 0);
    }
}

if (!function_exists('bpis_asset_qr_modal_title')) {
    /** QR modal heading, e.g. "Canopy (20 pc)". */
    function bpis_asset_qr_modal_title(array $row): string
    {
        $name = bpis_asset_name($row);
        $qty = isset($row['quantity']) ? (int) $row['quantity'] : 1;
        if ($qty < 1) {
            $qty = 1;
        }
        $uom = trim((string) ($row['unit_measure'] ?? $row['uom'] ?? 'pc'));
        if ($uom === '') {
            $uom = 'pc';
        }

        return $name . ' (' . $qty . ' ' . $uom . ')';
    }
}

if (!function_exists('bpis_enrich_assets_for_picker')) {
    /**
     * @param list<array<string,mixed>> $assets
     * @return list<array<string,mixed>>
     */
    function bpis_enrich_assets_for_picker(mysqli $conn, array $assets): array
    {
        if (!$assets) {
            return [];
        }

        $ids = array_map(static fn ($a) => (int) ($a['id'] ?? 0), $assets);
        $photo_map = bpis_get_asset_photo_map($conn, $ids);

        foreach ($assets as &$row) {
            $id = (int) ($row['id'] ?? 0);
            $row['display_label'] = bpis_asset_display_label($row);
            $row['asset_name'] = bpis_asset_name($row);
            $row['description_text'] = trim((string) ($row['description'] ?? ''));
            $row['photo_url'] = bpis_asset_photo_url($conn, $id, $photo_map);
        }
        unset($row);

        return $assets;
    }
}

if (!function_exists('bpis_resolve_asset_id_from_lookup')) {
    function bpis_resolve_asset_id_from_lookup(array $found): int
    {
        return (int) ($found['asset_id'] ?? $found['id'] ?? 0);
    }
}

if (!function_exists('bpis_format_asset_remarks_display')) {
    function bpis_format_asset_remarks_display(array $row): string
    {
        $rawRemarks = strtoupper(trim((string) ($row['remarks'] ?? '')));
        if ($rawRemarks === 'UNSERVICEABLE' || $rawRemarks === 'DISPOSED' || $rawRemarks === 'DISPOSE'
            || str_contains($rawRemarks, 'UNSERVICEABLE') || str_contains($rawRemarks, 'DISPOSE')) {
            return 'UNSERVICEABLE';
        }
        if ($rawRemarks === 'UNDER REPAIR') {
            return 'UNDER REPAIR';
        }
        if ($rawRemarks !== '') {
            return $rawRemarks;
        }
        $rawItemCond = strtoupper(trim((string) ($row['item_condition'] ?? '')));
        if ($rawItemCond !== '') {
            return $rawItemCond;
        }
        return 'SERVICEABLE';
    }
}

if (!function_exists('bpis_get_asset_inventory_display_row')) {
    /**
     * Inventory-style row for QR scan / display (matches treasurer/inventory.php columns).
     *
     * @return ?array<string,mixed>
     */
    function bpis_get_asset_inventory_display_row(mysqli $conn, int $asset_id, ?string $scanned_unit_tag = null): ?array
    {
        if ($asset_id <= 0) {
            return null;
        }
        $stmt = $conn->prepare('SELECT * FROM asset WHERE id = ? LIMIT 1');
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('i', $asset_id);
        $stmt->execute();
        $rs = $stmt->get_result();
        $row = $rs ? $rs->fetch_assoc() : null;
        $stmt->close();
        if (!$row) {
            return null;
        }

        $qty = max(1, (int) ($row['quantity'] ?? 1));
        $unit_val = number_format((float) ($row['unit_value'] ?? 0), 2);
        $date_acquired = (string) ($row['date_acquired'] ?? '');
        $date_display = $date_acquired !== '' && strtotime($date_acquired)
            ? date('F j, Y', strtotime($date_acquired))
            : '—';
        $remarks = bpis_format_asset_remarks_display($row);
        $remarks_class = str_contains($remarks, 'UNSERVICEABLE') ? 'text-red-500'
            : ($remarks === 'SERVICEABLE' ? 'text-green-600' : 'text-gray-800');
        $photo_map = bpis_get_asset_photo_map($conn, [$asset_id]);

        return [
            'id' => $asset_id,
            'article' => (string) ($row['article'] ?? ''),
            'description' => (string) ($row['description'] ?? ''),
            'category' => (string) ($row['category'] ?? ''),
            'asset_cluster' => (string) ($row['asset_cluster'] ?? '—'),
            'location' => (string) ($row['location'] ?? '—'),
            'property_number' => (string) ($row['property_number'] ?? ''),
            'unit_tag' => trim((string) $scanned_unit_tag),
            'uom' => (string) ($row['unit_measure'] ?? 'pc'),
            'date_acquired' => $date_display,
            'unit_value' => $unit_val,
            'unit_value_label' => bpis_label_unit_cost_value(),
            'remarks' => $remarks,
            'remarks_class' => $remarks_class,
            'status' => (string) ($row['status'] ?? 'Available'),
            'photo_html' => bpis_asset_photo_img(
                $conn,
                $asset_id,
                (string) ($row['description'] ?? 'Asset'),
                'bpis-asset-thumb',
                $photo_map
            ),
        ];
    }
}

if (!function_exists('bpis_finalize_new_asset_financials')) {
    /** Set total_cost and initial NBV after registration so utility pages can use the asset immediately. */
    function bpis_finalize_new_asset_financials(
        mysqli $conn,
        int $asset_id,
        float $unit_value,
        int $quantity
    ): void {
        if ($asset_id <= 0) {
            return;
        }

        bpis_ensure_asset_depreciation_columns($conn);
        $qty = max(1, $quantity);
        $total = round($unit_value * $qty, 2);

        $sets = [];
        if (bpis_column_exists($conn, 'asset', 'total_cost')) {
            $sets[] = "total_cost = {$total}";
        }
        if (bpis_column_exists($conn, 'asset', 'card_value')) {
            $sets[] = "card_value = {$total}";
        }
        if (bpis_column_exists($conn, 'asset', 'accumulated_depreciation')) {
            $sets[] = 'accumulated_depreciation = 0';
        }
        if (bpis_column_exists($conn, 'asset', 'net_book_value')) {
            $sets[] = "net_book_value = {$total}";
        }

        if ($sets) {
            mysqli_query($conn, 'UPDATE asset SET ' . implode(', ', $sets) . " WHERE id = {$asset_id}");
        }
    }
}

if (!function_exists('bpis_set_last_registered_asset')) {
    function bpis_set_last_registered_asset(int $asset_id): void
    {
        if ($asset_id > 0) {
            $_SESSION['bpis_last_registered_asset_id'] = $asset_id;
        }
    }
}

if (!function_exists('bpis_take_last_registered_asset')) {
    /** Returns and clears the last registered asset id (one-time highlight). */
    function bpis_take_last_registered_asset(): int
    {
        $id = (int) ($_SESSION['bpis_last_registered_asset_id'] ?? 0);
        unset($_SESSION['bpis_last_registered_asset_id']);
        return $id;
    }
}

if (!function_exists('bpis_peek_last_registered_asset')) {
    function bpis_peek_last_registered_asset(): int
    {
        return (int) ($_SESSION['bpis_last_registered_asset_id'] ?? 0);
    }
}
