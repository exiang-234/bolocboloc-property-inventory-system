<?php

require_once __DIR__ . '/schema_bootstrap.php';
require_once __DIR__ . '/asset_list_helpers.php';

if (!function_exists('bpis_ensure_audit_scan_schema')) {
    function bpis_ensure_audit_scan_schema(mysqli $conn): void
    {
        if (!bpis_table_exists($conn, 'audit_unit_scans')) {
            mysqli_query($conn, "CREATE TABLE audit_unit_scans (
                id INT(11) NOT NULL AUTO_INCREMENT,
                asset_id INT(11) NOT NULL,
                unit_tag VARCHAR(120) NOT NULL,
                admin_id INT(11) NULL DEFAULT NULL,
                scanned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_audit_unit_tag (unit_tag),
                KEY idx_audit_asset (asset_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }
    }
}

if (!function_exists('bpis_audit_property_count')) {
    /**
     * Balance per card quantity for RPCPPE (registered units on record).
     *
     * @param array<string,mixed> $asset_row
     */
    function bpis_audit_property_count(mysqli $conn, array $asset_row): int
    {
        $asset_id = (int) ($asset_row['id'] ?? 0);
        $qty = (int) ($asset_row['quantity'] ?? 0);
        if ($qty <= 0) {
            $qty = (int) preg_replace('/\D/', '', (string) ($asset_row['article'] ?? '0'));
        }
        if ($qty <= 0) {
            $qty = 1;
        }

        if ($asset_id > 0 && bpis_table_exists($conn, 'asset_units')) {
            $rs = mysqli_query($conn, "SELECT COUNT(*) AS c FROM asset_units WHERE asset_id = {$asset_id}");
            if ($rs && ($row = mysqli_fetch_assoc($rs))) {
                $unit_count = (int) ($row['c'] ?? 0);
                if ($unit_count > 0) {
                    return max($qty, $unit_count);
                }
            }
        }

        return $qty;
    }
}

if (!function_exists('bpis_audit_on_hand_count')) {
    /** Distinct unit tags scanned for this asset during physical count. */
    function bpis_audit_on_hand_count(mysqli $conn, int $asset_id): int
    {
        if ($asset_id <= 0) {
            return 0;
        }
        bpis_ensure_audit_scan_schema($conn);
        $rs = mysqli_query($conn, "SELECT COUNT(*) AS c FROM audit_unit_scans WHERE asset_id = {$asset_id}");
        if ($rs && ($row = mysqli_fetch_assoc($rs))) {
            return max(0, (int) ($row['c'] ?? 0));
        }
        return 0;
    }
}

if (!function_exists('bpis_sync_asset_audit_counts')) {
    /**
     * Persist on-hand / shortage values on the asset row from scan totals.
     *
     * @param array<string,mixed> $asset_row
     * @return array{property_count:int,on_hand:int,diff_qty:int,diff_val:float,val_card:float}
     */
    function bpis_sync_asset_audit_counts(mysqli $conn, array $asset_row): array
    {
        $asset_id = (int) ($asset_row['id'] ?? 0);
        $property_count = bpis_audit_property_count($conn, $asset_row);
        $on_hand = bpis_audit_on_hand_count($conn, $asset_id);
        $unit_val = (float) ($asset_row['unit_value'] ?? 0);
        $diff_qty = $on_hand - $property_count;
        $diff_val = round($diff_qty * $unit_val, 2);
        $val_card = round($property_count * $unit_val, 2);

        if ($asset_id > 0) {
            $stmt = mysqli_prepare(
                $conn,
                'UPDATE asset SET on_hand_qty = ?, shortage_overage_qty = ?, shortage_overage_value = ? WHERE id = ?'
            );
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'iidi', $on_hand, $diff_qty, $diff_val, $asset_id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }
        }

        return [
            'property_count' => $property_count,
            'on_hand' => $on_hand,
            'diff_qty' => $diff_qty,
            'diff_val' => $diff_val,
            'val_card' => $val_card,
        ];
    }
}

if (!function_exists('bpis_audit_asset_count_summary')) {
    /**
     * @param array<string,mixed> $asset_row
     * @return array{property_count:int,on_hand:int,diff_qty:int,diff_val:float,val_card:float}
     */
    function bpis_audit_asset_count_summary(mysqli $conn, array $asset_row): array
    {
        $asset_id = (int) ($asset_row['id'] ?? 0);
        $property_count = bpis_audit_property_count($conn, $asset_row);
        $on_hand = bpis_audit_on_hand_count($conn, $asset_id);
        $unit_val = (float) ($asset_row['unit_value'] ?? 0);
        $diff_qty = $on_hand - $property_count;
        $diff_val = round($diff_qty * $unit_val, 2);
        $val_card = round($property_count * $unit_val, 2);

        return [
            'property_count' => $property_count,
            'on_hand' => $on_hand,
            'diff_qty' => $diff_qty,
            'diff_val' => $diff_val,
            'val_card' => $val_card,
        ];
    }
}

if (!function_exists('bpis_record_audit_unit_scan')) {
    /**
     * Record one QR scan for physical count and sync audit totals on the asset.
     *
     * @param array{found:?array,tag:string,tag_raw:string,match_type:string,message:string} $lookup
     * @return array{
     *   ok:bool,
     *   duplicate:bool,
     *   message:string,
     *   asset_id:int,
     *   unit_tag:string,
     *   property_count:int,
     *   on_hand:int,
     *   diff_qty:int,
     *   diff_val:float
     * }
     */
    function bpis_record_audit_unit_scan(mysqli $conn, array $lookup, ?int $admin_id = null): array
    {
        bpis_ensure_audit_scan_schema($conn);

        $found = $lookup['found'] ?? null;
        $tag = trim((string) ($lookup['tag'] ?? ''));
        $asset_id = $found ? bpis_resolve_asset_id_from_lookup($found) : 0;

        $empty = [
            'ok' => false,
            'duplicate' => false,
            'message' => 'Unable to record scan.',
            'asset_id' => 0,
            'unit_tag' => $tag,
            'property_count' => 0,
            'on_hand' => 0,
            'diff_qty' => 0,
            'diff_val' => 0.0,
        ];

        if (!$found || $asset_id <= 0 || $tag === '') {
            $empty['message'] = 'No asset found for this tag.';
            return $empty;
        }

        $stmt = mysqli_prepare($conn, 'SELECT id FROM audit_unit_scans WHERE unit_tag = ? LIMIT 1');
        if (!$stmt) {
            $empty['message'] = 'Could not verify scan history.';
            return $empty;
        }
        mysqli_stmt_bind_param($stmt, 's', $tag);
        mysqli_stmt_execute($stmt);
        $dup_rs = mysqli_stmt_get_result($stmt);
        $is_duplicate = $dup_rs && mysqli_num_rows($dup_rs) > 0;
        mysqli_stmt_close($stmt);

        if (!$is_duplicate) {
            $insert = mysqli_prepare(
                $conn,
                'INSERT INTO audit_unit_scans (asset_id, unit_tag, admin_id) VALUES (?, ?, ?)'
            );
            if (!$insert) {
                $empty['message'] = 'Could not save scan.';
                return $empty;
            }
            mysqli_stmt_bind_param($insert, 'isi', $asset_id, $tag, $admin_id);
            if (!mysqli_stmt_execute($insert)) {
                mysqli_stmt_close($insert);
                $empty['message'] = 'Could not save scan.';
                return $empty;
            }
            mysqli_stmt_close($insert);
        }

        $asset_stmt = mysqli_prepare($conn, 'SELECT * FROM asset WHERE id = ? LIMIT 1');
        if (!$asset_stmt) {
            $empty['message'] = 'Asset record not found.';
            return $empty;
        }
        mysqli_stmt_bind_param($asset_stmt, 'i', $asset_id);
        mysqli_stmt_execute($asset_stmt);
        $asset_rs = mysqli_stmt_get_result($asset_stmt);
        $asset_row = $asset_rs ? mysqli_fetch_assoc($asset_rs) : null;
        mysqli_stmt_close($asset_stmt);

        if (!$asset_row) {
            $empty['message'] = 'Asset record not found.';
            return $empty;
        }

        $counts = bpis_sync_asset_audit_counts($conn, $asset_row);

        return [
            'ok' => true,
            'duplicate' => $is_duplicate,
            'message' => $is_duplicate
                ? 'This tag was already counted. Audit totals refreshed.'
                : 'Scan recorded. Physical count updated in Audit.',
            'asset_id' => $asset_id,
            'unit_tag' => $tag,
            'property_count' => $counts['property_count'],
            'on_hand' => $counts['on_hand'],
            'diff_qty' => $counts['diff_qty'],
            'diff_val' => $counts['diff_val'],
        ];
    }
}
