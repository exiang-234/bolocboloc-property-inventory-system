<?php

require_once __DIR__ . '/schema_bootstrap.php';
require_once __DIR__ . '/upload_helpers.php';
require_once __DIR__ . '/asset_list_helpers.php';

if (!function_exists('bpis_is_placeholder_brand_model')) {
    /** True when value is a placeholder (N/A, N/s, etc.), not a real brand/model. */
    function bpis_is_placeholder_brand_model(?string $value): bool
    {
        $v = strtolower(trim((string) $value));
        if ($v === '') {
            return true;
        }
        static $exact = [
            'n/a', 'na', 'n.a', 'n.a.', 'n/s', 'ns', 'none', '-', '—',
            'unknown', 'not applicable', 'tbd', 'nil', 'null',
        ];
        if (in_array($v, $exact, true)) {
            return true;
        }
        return (bool) preg_match('/^n\/?\s*a\.?$/i', $v);
    }
}

if (!function_exists('bpis_normalize_brand_model_value')) {
    function bpis_normalize_brand_model_value(?string $value): string
    {
        $value = trim((string) $value);
        return bpis_is_placeholder_brand_model($value) ? '' : $value;
    }
}

if (!function_exists('bpis_format_brand_model_display')) {
    function bpis_format_brand_model_display(?string $brand, ?string $model): string
    {
        $b = bpis_normalize_brand_model_value($brand);
        $m = bpis_normalize_brand_model_value($model);
        if ($b === '' && $m === '') {
            return '—';
        }
        if ($b !== '' && $m !== '') {
            return $b . ' / ' . $m;
        }
        return $b !== '' ? $b : $m;
    }
}

if (!function_exists('bpis_build_qr_payload')) {
    function bpis_build_qr_payload(string $tag, string $brand = '', string $model = ''): string
    {
        $tag = trim($tag);
        $brand = bpis_normalize_brand_model_value($brand);
        $model = bpis_normalize_brand_model_value($model);
        if ($brand === '' && $model === '') {
            return $tag;
        }
        return $tag . '|' . $brand . '|' . $model;
    }
}

if (!function_exists('bpis_normalize_placeholder_brand_model_in_db')) {
    /** One-time cleanup: convert N/A-style placeholders to empty strings. */
    function bpis_normalize_placeholder_brand_model_in_db(mysqli $conn): void
    {
        static $done = false;
        if ($done || !$conn) {
            return;
        }
        $done = true;

        foreach (['asset', 'asset_units'] as $table) {
            if (!bpis_table_exists($conn, $table)) {
                continue;
            }
            foreach (['brand', 'model'] as $col) {
                if (!bpis_column_exists($conn, $table, $col)) {
                    continue;
                }
                mysqli_query(
                    $conn,
                    "UPDATE `{$table}` SET `{$col}` = '' WHERE `{$col}` IS NOT NULL AND TRIM(`{$col}`) <> '' AND (
                        LOWER(TRIM(`{$col}`)) IN ('n/a','na','n.a.','n.a','n/s','ns','none','unknown','tbd','nil','-','—')
                        OR LOWER(TRIM(`{$col}`)) REGEXP '^n[[:space:]]*/?[[:space:]]*a\\.?$'
                    )"
                );
            }
        }
    }
}

if (!function_exists('bpis_parse_scanned_tag')) {
    /**
     * QR codes encode "UNIT_TAG|brand|model" — return the property/unit tag only.
     */
    function bpis_parse_scanned_tag(string $raw): string
    {
        $raw = trim(urldecode($raw));
        if ($raw === '') {
            return '';
        }
        if (str_contains($raw, '|')) {
            $parts = explode('|', $raw, 3);
            $raw = trim($parts[0]);
        }
        return trim($raw);
    }
}

if (!function_exists('bpis_property_prefix_from_unit_tag')) {
    /** PROP-2026-CAN-001 → PROP-2026-CAN */
    function bpis_property_prefix_from_unit_tag(string $unit_tag): string
    {
        $unit_tag = trim($unit_tag);
        if (preg_match('/^(.+)-\d{3}$/', $unit_tag, $m)) {
            return $m[1];
        }
        return $unit_tag;
    }
}

if (!function_exists('bpis_lookup_asset_by_scanned_tag')) {
    /**
     * Resolve a scanned QR / manual tag to an asset (+ unit when applicable).
     *
     * @return array{found: ?array, tag: string, tag_raw: string, match_type: string, message: string}
     */
    function bpis_lookup_asset_by_scanned_tag(mysqli $conn, string $raw): array
    {
        $tag_raw = trim($raw);
        $tag = bpis_parse_scanned_tag($tag_raw);
        $out = [
            'found' => null,
            'tag' => $tag,
            'tag_raw' => $tag_raw,
            'match_type' => '',
            'message' => '',
        ];

        if ($tag === '' || !bpis_table_exists($conn, 'asset')) {
            $out['message'] = 'No tag provided.';
            return $out;
        }

        $safe_tag = mysqli_real_escape_string($conn, $tag);
        $prefix = mysqli_real_escape_string($conn, bpis_property_prefix_from_unit_tag($tag));

        if (bpis_table_exists($conn, 'asset_units')) {
            $unit_rs = mysqli_query(
                $conn,
                "SELECT u.*, a.description, a.brand, a.model, a.location, a.unit_value, a.audit_remarks, a.remarks, a.property_number, a.id AS asset_id
                 FROM asset_units u
                 JOIN asset a ON a.id = u.asset_id
                 WHERE u.unit_tag = '{$safe_tag}'
                 LIMIT 1"
            );
            if ($unit_rs && mysqli_num_rows($unit_rs) > 0) {
                $out['found'] = mysqli_fetch_assoc($unit_rs);
                $out['match_type'] = 'unit';
                $out['message'] = 'Unit tag matched.';
                return $out;
            }
        }

        $asset_queries = [
            "SELECT * FROM asset WHERE property_number = '{$safe_tag}' LIMIT 1",
            "SELECT * FROM asset WHERE property_number = '{$prefix}' LIMIT 1",
            "SELECT * FROM asset WHERE property_number LIKE '{$prefix}-%' LIMIT 1",
        ];

        foreach ($asset_queries as $sql) {
            $rs = mysqli_query($conn, $sql);
            if ($rs && mysqli_num_rows($rs) > 0) {
                $out['found'] = mysqli_fetch_assoc($rs);
                $out['match_type'] = 'asset';
                $out['message'] = 'Asset matched for annual count.';
                return $out;
            }
        }

        $out['message'] = 'No asset found for tag: ' . $tag;
        if ($tag_raw !== $tag) {
            $out['message'] .= ' (from scan: ' . $tag_raw . ')';
        }
        return $out;
    }
}

if (!function_exists('bpis_create_asset_units_for_asset')) {
    /**
     * Create one asset_units row per quantity with optional per-unit photo upload.
     *
     * @param array|null $files Typically $_FILES['unit_photo'] with ['name'=>[], 'tmp_name'=>[], ...]
     */
    function bpis_create_asset_units_for_asset(
        mysqli $conn,
        int $asset_id,
        string $property_prefix,
        int $quantity,
        string $brand,
        string $model,
        string $location,
        ?array $files = null,
        ?string $default_photo_path = null,
        ?array $unit_photo_paths = null
    ): void {
        if ($asset_id <= 0 || !bpis_table_exists($conn, 'asset_units')) {
            return;
        }
        if ($quantity < 1) {
            $quantity = 1;
        }
        $brand = trim($brand);
        $model = trim($model);
        $location = trim($location);
        $prefix = trim($property_prefix);
        if ($prefix === '') {
            $prefix = 'PROP';
        }

        $stmt = $conn->prepare(
            'INSERT INTO asset_units (asset_id, unit_tag, brand, model, photo_path, location, unit_condition)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        if (!$stmt) {
            return;
        }

        for ($i = 1; $i <= $quantity; $i++) {
            $tag = $prefix . '-' . str_pad((string) $i, 3, '0', STR_PAD_LEFT);
            $photo_path = null;
            $idx = $i - 1;

            if ($unit_photo_paths && !empty($unit_photo_paths[$idx]) && is_string($unit_photo_paths[$idx])) {
                $photo_path = trim($unit_photo_paths[$idx]);
            } elseif ($files && isset($files['tmp_name']) && is_array($files['tmp_name'])) {
                if (!empty($files['tmp_name'][$idx]) && is_uploaded_file($files['tmp_name'][$idx])) {
                    $single = [
                        'name' => $files['name'][$idx] ?? '',
                        'type' => $files['type'][$idx] ?? '',
                        'tmp_name' => $files['tmp_name'][$idx],
                        'error' => $files['error'][$idx] ?? UPLOAD_ERR_OK,
                        'size' => $files['size'][$idx] ?? 0,
                    ];
                    $_FILES['__bpis_unit_photo'] = $single;
                    $photo_path = bpis_store_upload('__bpis_unit_photo', 'units');
                    unset($_FILES['__bpis_unit_photo']);
                }
            }

            if ($photo_path === null && $default_photo_path !== null && trim($default_photo_path) !== '') {
                $photo_path = $default_photo_path;
            }

            $photo_sql = $photo_path ? $photo_path : '';
            $condition = 'SERVICEABLE';
            $stmt->bind_param('issssss', $asset_id, $tag, $brand, $model, $photo_sql, $location, $condition);
            $stmt->execute();
        }
        $stmt->close();
        bpis_sync_asset_photo_from_units($conn, $asset_id);
    }
}

if (!function_exists('bpis_unit_condition_options')) {
  /** @return list<string> */
    function bpis_unit_condition_options(): array
    {
        return ['SERVICEABLE', 'UNDER REPAIR', 'UNSERVICEABLE/DISPOSE'];
    }
}

if (!function_exists('bpis_normalize_unit_condition')) {
    function bpis_normalize_unit_condition(?string $value): string
    {
        $r = strtoupper(trim((string) $value));
        if ($r === 'UNSERVICEABLE' || $r === 'DISPOSED' || $r === 'DISPOSE' || str_contains($r, 'UNSERVICEABLE')) {
            return 'UNSERVICEABLE/DISPOSE';
        }
        if ($r === 'UNDER REPAIR') {
            return 'UNDER REPAIR';
        }
        return 'SERVICEABLE';
    }
}

if (!function_exists('bpis_asset_unit_availability_stats')) {
    /**
     * Per-unit condition counts and borrowable availability.
     *
     * @param array<string,mixed> $asset_row
     * @return array{
     *   total:int,
     *   serviceable:int,
     *   under_repair:int,
     *   unavailable:int,
     *   borrowed:int,
     *   available:int
     * }
     */
    function bpis_asset_unit_availability_stats(mysqli $conn, array $asset_row): array
    {
        $qty = (int) ($asset_row['quantity'] ?? 0);
        if ($qty <= 0) {
            $fallback = (int) preg_replace('/\D/', '', (string) ($asset_row['article'] ?? '0'));
            $qty = $fallback > 0 ? $fallback : 1;
        }
        $borrowed = max(0, (int) ($asset_row['borrowed_qty'] ?? 0));
        $asset_id = (int) ($asset_row['id'] ?? 0);

        $stats = [
            'total' => $qty,
            'serviceable' => 0,
            'under_repair' => 0,
            'unavailable' => 0,
            'borrowed' => $borrowed,
            'available' => 0,
        ];

        if ($asset_id > 0 && bpis_table_exists($conn, 'asset_units') && bpis_column_exists($conn, 'asset_units', 'unit_condition')) {
            $rs = mysqli_query($conn, "SELECT unit_condition FROM asset_units WHERE asset_id = {$asset_id}");
            if ($rs && mysqli_num_rows($rs) > 0) {
                while ($u = mysqli_fetch_assoc($rs)) {
                    $cond = bpis_normalize_unit_condition($u['unit_condition'] ?? 'SERVICEABLE');
                    if ($cond === 'UNSERVICEABLE/DISPOSE') {
                        $stats['unavailable']++;
                    } elseif ($cond === 'UNDER REPAIR') {
                        $stats['under_repair']++;
                    } else {
                        $stats['serviceable']++;
                    }
                }
                $counted = $stats['serviceable'] + $stats['under_repair'] + $stats['unavailable'];
                if ($counted < $qty) {
                    $default = bpis_normalize_unit_condition((string) ($asset_row['remarks'] ?? $asset_row['item_condition'] ?? 'SERVICEABLE'));
                    $missing = $qty - $counted;
                    if ($default === 'UNSERVICEABLE/DISPOSE') {
                        $stats['unavailable'] += $missing;
                    } elseif ($default === 'UNDER REPAIR') {
                        $stats['under_repair'] += $missing;
                    } else {
                        $stats['serviceable'] += $missing;
                    }
                } elseif ($counted > $qty) {
                    $stats['total'] = $counted;
                    $qty = $counted;
                }
                $stats['available'] = max(0, $stats['serviceable'] - $borrowed);

                return $stats;
            }
        }

        $asset_cond = bpis_normalize_unit_condition((string) ($asset_row['remarks'] ?? $asset_row['item_condition'] ?? 'SERVICEABLE'));
        if ($asset_cond === 'UNSERVICEABLE/DISPOSE') {
            $stats['unavailable'] = $qty;
        } elseif ($asset_cond === 'UNDER REPAIR') {
            $stats['under_repair'] = $qty;
        } else {
            $stats['serviceable'] = $qty;
        }
        $stats['available'] = max(0, $stats['serviceable'] - $borrowed);

        return $stats;
    }
}

if (!function_exists('bpis_format_asset_status_text')) {
    /**
     * Inventory status column, e.g. "28 Available / 2 Used / 2 Under Repair".
     *
     * @param array<string,mixed> $asset_row
     */
    function bpis_format_asset_status_text(mysqli $conn, array $asset_row, bool $borrowable_category = true): string
    {
        $stats = bpis_asset_unit_availability_stats($conn, $asset_row);

        if (!$borrowable_category) {
            $db_status = trim((string) ($asset_row['status'] ?? ''));
            if ($stats['serviceable'] <= 0 && $stats['unavailable'] > 0) {
                return 'Not Available';
            }
            if ($stats['available'] <= 0 && $stats['under_repair'] > 0 && $stats['borrowed'] === 0) {
                return $stats['under_repair'] . ' Under Repair';
            }

            return $db_status !== '' ? $db_status : 'In Use';
        }

        if ($stats['serviceable'] <= 0 && $stats['unavailable'] > 0) {
            return 'Not Available';
        }

        $parts = [$stats['available'] . ' Available'];
        if ($stats['borrowed'] > 0) {
            $parts[] = $stats['borrowed'] . ' Used';
        }
        if ($stats['under_repair'] > 0) {
            $parts[] = $stats['under_repair'] . ' Under Repair';
        }
        if ($stats['unavailable'] > 0) {
            $parts[] = $stats['unavailable'] . ' Unavailable';
        }

        return implode(' / ', $parts);
    }
}

if (!function_exists('bpis_format_asset_remarks_text')) {
    /**
     * Inventory remarks column: single label when uniform, otherwise per-condition counts.
     * e.g. "28 Serviceable / 2 Under Repair" when only some units are under repair.
     *
     * @param array<string,mixed> $asset_row
     */
    function bpis_format_asset_remarks_text(mysqli $conn, array $asset_row): string
    {
        $stats = bpis_asset_unit_availability_stats($conn, $asset_row);
        $kinds = 0;
        if ($stats['serviceable'] > 0) {
            $kinds++;
        }
        if ($stats['under_repair'] > 0) {
            $kinds++;
        }
        if ($stats['unavailable'] > 0) {
            $kinds++;
        }

        if ($kinds <= 1) {
            if ($stats['unavailable'] > 0) {
                return 'UNSERVICEABLE/DISPOSE';
            }
            if ($stats['under_repair'] > 0) {
                return 'UNDER REPAIR';
            }
            return 'SERVICEABLE';
        }

        $parts = [];
        if ($stats['serviceable'] > 0) {
            $parts[] = $stats['serviceable'] . ' Serviceable';
        }
        if ($stats['under_repair'] > 0) {
            $parts[] = $stats['under_repair'] . ' Under Repair';
        }
        if ($stats['unavailable'] > 0) {
            $parts[] = $stats['unavailable'] . ' Unavailable';
        }

        return implode(' / ', $parts);
    }
}

if (!function_exists('bpis_asset_remarks_display_label')) {
    /** Short label for table cells (e.g. UNSERVICEABLE instead of UNSERVICEABLE/DISPOSE). */
    function bpis_asset_remarks_display_label(string $remarks_display): string
    {
        if ($remarks_display === 'UNSERVICEABLE/DISPOSE') {
            return 'UNSERVICEABLE';
        }
        return $remarks_display;
    }
}

if (!function_exists('bpis_asset_remarks_cell_class')) {
    function bpis_asset_remarks_cell_class(string $remarks_display): string
    {
        $upper = strtoupper($remarks_display);
        if ($upper === 'SERVICEABLE') {
            return 'text-green-600';
        }
        if ($upper === 'UNDER REPAIR') {
            return 'text-amber-600';
        }
        if (strpos($upper, 'UNSERVICEABLE') !== false && strpos($remarks_display, '/') === false) {
            return 'text-red-500';
        }
        if (strpos($remarks_display, '/') !== false) {
            return 'text-amber-700';
        }
        return 'text-gray-800';
    }
}

if (!function_exists('bpis_derive_asset_condition_from_units')) {
    /**
     * Worst-condition wins for asset-level remarks / item_condition summary.
     *
     * @param list<string> $unit_conditions
     */
    function bpis_derive_asset_condition_from_units(array $unit_conditions, string $fallback = 'SERVICEABLE'): string
    {
        if (!$unit_conditions) {
            return bpis_normalize_unit_condition($fallback);
        }
        $normalized = array_map('bpis_normalize_unit_condition', $unit_conditions);
        if (in_array('UNSERVICEABLE/DISPOSE', $normalized, true)) {
            return 'UNSERVICEABLE/DISPOSE';
        }
        if (in_array('UNDER REPAIR', $normalized, true)) {
            return 'UNDER REPAIR';
        }
        return 'SERVICEABLE';
    }
}

if (!function_exists('bpis_load_asset_units_for_edit')) {
    /**
     * Units for treasurer edit modal (per-pc condition).
     *
     * @return list<array{id:int,unit_tag:string,unit_condition:string}>
     */
    function bpis_load_asset_units_for_edit(mysqli $conn, array $asset_row): array
    {
        $asset_id = (int) ($asset_row['id'] ?? 0);
        $qty = max(1, (int) ($asset_row['quantity'] ?? 1));
        $prefix = trim((string) ($asset_row['property_number'] ?? 'PROP'));
        $default = bpis_normalize_unit_condition((string) ($asset_row['remarks'] ?? $asset_row['item_condition'] ?? 'SERVICEABLE'));
        $units = [];

        if ($asset_id > 0 && bpis_table_exists($conn, 'asset_units')) {
            $cols = 'id, unit_tag';
            if (bpis_column_exists($conn, 'asset_units', 'unit_condition')) {
                $cols .= ', unit_condition';
            }
            $rs = mysqli_query($conn, "SELECT {$cols} FROM asset_units WHERE asset_id = {$asset_id} ORDER BY id ASC");
            if ($rs) {
                while ($u = mysqli_fetch_assoc($rs)) {
                    $units[] = [
                        'id' => (int) ($u['id'] ?? 0),
                        'unit_tag' => (string) ($u['unit_tag'] ?? ''),
                        'unit_condition' => bpis_normalize_unit_condition($u['unit_condition'] ?? $default),
                    ];
                }
            }
        }

        if (count($units) < $qty) {
            $existing_tags = array_column($units, 'unit_tag');
            for ($i = 1; $i <= $qty; $i++) {
                $tag = $prefix . '-' . str_pad((string) $i, 3, '0', STR_PAD_LEFT);
                if (in_array($tag, $existing_tags, true)) {
                    continue;
                }
                $units[] = [
                    'id' => 0,
                    'unit_tag' => $tag,
                    'unit_condition' => $default,
                ];
            }
        }

        usort($units, static function ($a, $b) {
            return strcmp((string) ($a['unit_tag'] ?? ''), (string) ($b['unit_tag'] ?? ''));
        });

        return array_slice($units, 0, $qty);
    }
}

if (!function_exists('bpis_save_asset_unit_conditions')) {
    /**
     * @param list<int|string> $unit_ids
     * @param list<string> $conditions
     * @param list<string> $unit_tags
     */
    function bpis_save_asset_unit_conditions(
        mysqli $conn,
        int $asset_id,
        array $unit_ids,
        array $conditions,
        array $unit_tags = []
    ): array {
        if ($asset_id <= 0 || !bpis_table_exists($conn, 'asset_units') || !bpis_column_exists($conn, 'asset_units', 'unit_condition')) {
            return [];
        }

        $saved = [];
        $asset_rs = mysqli_query($conn, "SELECT property_number, brand, model, location FROM asset WHERE id = {$asset_id} LIMIT 1");
        $asset_meta = $asset_rs ? mysqli_fetch_assoc($asset_rs) : [];
        $brand = trim((string) ($asset_meta['brand'] ?? ''));
        $model = trim((string) ($asset_meta['model'] ?? ''));
        $location = trim((string) ($asset_meta['location'] ?? ''));

        foreach ($unit_ids as $idx => $unit_id_raw) {
            $unit_id = (int) $unit_id_raw;
            $cond = bpis_normalize_unit_condition($conditions[$idx] ?? 'SERVICEABLE');
            $tag = trim((string) ($unit_tags[$idx] ?? ''));
            $saved[] = $cond;

            if ($unit_id > 0) {
                $stmt = $conn->prepare('UPDATE asset_units SET unit_condition = ? WHERE id = ? AND asset_id = ?');
                if ($stmt) {
                    $stmt->bind_param('sii', $cond, $unit_id, $asset_id);
                    $stmt->execute();
                    $stmt->close();
                }
                continue;
            }

            if ($tag === '') {
                continue;
            }

            $safe_tag = mysqli_real_escape_string($conn, $tag);
            $check = mysqli_query($conn, "SELECT id FROM asset_units WHERE asset_id = {$asset_id} AND unit_tag = '{$safe_tag}' LIMIT 1");
            if ($check && ($existing = mysqli_fetch_assoc($check))) {
                $eid = (int) ($existing['id'] ?? 0);
                if ($eid > 0) {
                    $stmt = $conn->prepare('UPDATE asset_units SET unit_condition = ? WHERE id = ? AND asset_id = ?');
                    if ($stmt) {
                        $stmt->bind_param('sii', $cond, $eid, $asset_id);
                        $stmt->execute();
                        $stmt->close();
                    }
                }
                continue;
            }

            $stmt = $conn->prepare(
                'INSERT INTO asset_units (asset_id, unit_tag, brand, model, photo_path, location, unit_condition)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            if ($stmt) {
                $empty_photo = '';
                $stmt->bind_param('issssss', $asset_id, $tag, $brand, $model, $empty_photo, $location, $cond);
                $stmt->execute();
                $stmt->close();
            }
        }

        return $saved;
    }
}

if (!function_exists('bpis_load_asset_subitems')) {
    function bpis_load_asset_subitems(mysqli $conn, array $asset_row): array
    {
        $asset_id = (int) ($asset_row['id'] ?? 0);
        $qty = (int) ($asset_row['quantity'] ?? 1);
        if ($qty < 1) {
            $qty = 1;
        }
        $articleLabel = bpis_asset_name($asset_row);
        $descriptionText = trim((string) ($asset_row['description'] ?? ''));
        $brand = (string) ($asset_row['brand'] ?? '');
        $model = (string) ($asset_row['model'] ?? '');
        $prefix = (string) ($asset_row['property_number'] ?? 'PROP');
        $default_photo = trim((string) ($asset_row['photo_path'] ?? ''));
        $subitems = [];

        $append_subitem = static function (
            string $tag,
            string $b,
            string $m,
            string $unit_photo = ''
        ) use (
            $articleLabel,
            $descriptionText,
            $default_photo
        ): array {
            $photo_path = trim($unit_photo) !== '' ? trim($unit_photo) : $default_photo;
            $photo_url = bpis_public_upload_url($photo_path !== '' ? $photo_path : null)
                ?: bpis_asset_placeholder_image_url();

            return [
                'name' => $articleLabel,
                'description' => $descriptionText,
                'brand' => bpis_normalize_brand_model_value($b),
                'model' => bpis_normalize_brand_model_value($m),
                'code' => $tag,
                'qr_data' => bpis_build_qr_payload($tag, $b, $m),
                'photo_url' => $photo_url,
            ];
        };

        if ($asset_id > 0 && bpis_table_exists($conn, 'asset_units')) {
            $unit_cols = 'unit_tag, brand, model';
            if (bpis_column_exists($conn, 'asset_units', 'photo_path')) {
                $unit_cols .= ', photo_path';
            }
            $rs = mysqli_query($conn, "SELECT {$unit_cols} FROM asset_units WHERE asset_id = {$asset_id} ORDER BY id ASC");
            if ($rs && mysqli_num_rows($rs) > 0) {
                while ($u = mysqli_fetch_assoc($rs)) {
                    $tag = (string) ($u['unit_tag'] ?? '');
                    $b = trim((string) ($u['brand'] ?? '')) ?: $brand;
                    $m = trim((string) ($u['model'] ?? '')) ?: $model;
                    $unit_photo = trim((string) ($u['photo_path'] ?? ''));
                    $subitems[] = $append_subitem($tag, $b, $m, $unit_photo);
                }
                return $subitems;
            }
        }

        for ($i = 1; $i <= $qty; $i++) {
            $tag = $prefix . '-' . str_pad((string) $i, 3, '0', STR_PAD_LEFT);
            $subitems[] = $append_subitem($tag, $brand, $model, '');
        }
        return $subitems;
    }
}

if (!function_exists('bpis_backfill_asset_units')) {
    /**
     * Create asset_units rows for assets that have none yet.
     *
     * @return array{created:int, skipped:int, assets:int}
     */
    function bpis_backfill_asset_units(mysqli $conn, bool $dry_run = false): array
    {
        $result = ['created' => 0, 'skipped' => 0, 'assets' => 0];
        if (!bpis_table_exists($conn, 'asset_units') || !bpis_table_exists($conn, 'asset')) {
            return $result;
        }

        $sql = "SELECT a.id, a.property_number, a.quantity, a.brand, a.model, a.location,
                       (SELECT COUNT(*) FROM asset_units u WHERE u.asset_id = a.id) AS unit_count
                FROM asset a
                HAVING unit_count = 0
                ORDER BY a.id ASC";
        $rs = mysqli_query($conn, $sql);
        if (!$rs) {
            return $result;
        }

        while ($row = mysqli_fetch_assoc($rs)) {
            $result['assets']++;
            $asset_id = (int) ($row['id'] ?? 0);
            $quantity = (int) ($row['quantity'] ?? 1);
            if ($quantity < 1) {
                $quantity = 1;
            }
            $brand = trim((string) ($row['brand'] ?? ''));
            $model = trim((string) ($row['model'] ?? ''));
            $location = trim((string) ($row['location'] ?? ''));
            $prefix = trim((string) ($row['property_number'] ?? ''));
            if ($prefix === '') {
                $prefix = 'PROP-' . $asset_id;
            }

            if ($dry_run) {
                $result['created'] += $quantity;
                continue;
            }

            bpis_create_asset_units_for_asset($conn, $asset_id, $prefix, $quantity, $brand, $model, $location, null);
            $result['created'] += $quantity;
        }

        return $result;
    }
}
