<?php

require_once __DIR__ . '/schema_bootstrap.php';
require_once __DIR__ . '/borrow_request_display_helpers.php';
require_once __DIR__ . '/asset_borrowable_helpers.php';
require_once __DIR__ . '/asset_units_helpers.php';
require_once __DIR__ . '/notification_helpers.php';

if (!function_exists('bpis_ensure_borrow_request_batch_column')) {
    function bpis_ensure_borrow_request_batch_column(mysqli $conn): void
    {
        bpis_add_column_if_missing($conn, 'borrowing_requests', 'batch_id', 'VARCHAR(64) NULL DEFAULT NULL');
    }
}

if (!function_exists('bpis_generate_request_batch_id')) {
    function bpis_generate_request_batch_id(): string
    {
        return 'BR-' . date('Ymd') . '-' . bin2hex(random_bytes(4));
    }
}

if (!function_exists('bpis_request_batch_key')) {
    /**
     * Stable key to group rows from one borrower submission.
     *
     * @param array<string,mixed> $row
     */
    function bpis_request_batch_key(array $row): string
    {
        $batch = trim((string) ($row['batch_id'] ?? ''));
        if ($batch !== '') {
            return $batch;
        }

        $email = strtolower(trim((string) ($row['email'] ?? '')));
        $purpose = trim((string) ($row['purpose'] ?? ''));
        $needed = trim((string) ($row['date_needed'] ?? $row['needed_on'] ?? ''));
        $return = trim((string) ($row['return_date'] ?? ''));
        $profile = (int) ($row['borrower_profile_id'] ?? 0);
        $created = strtotime((string) ($row['created_at'] ?? ''));
        $bucket = $created > 0 ? (int) floor($created / 300) : 0;

        return 'legacy-' . md5($email . '|' . $purpose . '|' . $needed . '|' . $return . '|' . $profile . '|' . $bucket);
    }
}

if (!function_exists('bpis_group_pending_borrowing_requests')) {
    /**
     * @return list<array{
     *   batch_key:string,
     *   primary_id:int,
     *   requests:list<array<string,mixed>>,
     *   item_lines:list<array{label:string,qty:int,request_id:int}>
     * }>
     */
    function bpis_group_pending_borrowing_requests(mysqli $conn): array
    {
        bpis_ensure_borrow_request_batch_column($conn);

        $rows = [];
        $rs = mysqli_query($conn, "SELECT * FROM borrowing_requests WHERE status = 'Pending' ORDER BY id ASC");
        if ($rs) {
            while ($row = mysqli_fetch_assoc($rs)) {
                $rows[] = $row;
            }
        }

        $groups = [];
        foreach ($rows as $row) {
            $key = bpis_request_batch_key($row);
            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'batch_key' => $key,
                    'primary_id' => (int) ($row['id'] ?? 0),
                    'requests' => [],
                    'item_lines' => [],
                ];
            }
            $rid = (int) ($row['id'] ?? 0);
            $qty = (int) ($row['qty'] ?? $row['quantity'] ?? 1);
            if ($qty < 1) {
                $qty = 1;
            }
            $groups[$key]['requests'][] = $row;
            $groups[$key]['item_lines'][] = [
                'label' => bpis_borrow_request_item_label($conn, $row),
                'qty' => $qty,
                'request_id' => $rid,
            ];
            if ($rid > 0 && $rid < $groups[$key]['primary_id']) {
                $groups[$key]['primary_id'] = $rid;
            }
        }

        $out = array_values($groups);
        usort($out, static function ($a, $b) {
            return ($b['primary_id'] ?? 0) <=> ($a['primary_id'] ?? 0);
        });

        return $out;
    }
}

if (!function_exists('bpis_count_pending_request_batches')) {
    function bpis_count_pending_request_batches(mysqli $conn): int
    {
        return count(bpis_group_pending_borrowing_requests($conn));
    }
}

if (!function_exists('bpis_get_pending_requests_for_batch')) {
    /**
     * @return list<array<string,mixed>>
     */
    function bpis_get_pending_requests_for_batch(mysqli $conn, string $batch_key): array
    {
        $batch_key = trim($batch_key);
        if ($batch_key === '') {
            return [];
        }

        bpis_ensure_borrow_request_batch_column($conn);

        if (strpos($batch_key, 'legacy-') !== 0) {
            $stmt = mysqli_prepare(
                $conn,
                "SELECT * FROM borrowing_requests WHERE status = 'Pending' AND batch_id = ? ORDER BY id ASC"
            );
            if (!$stmt) {
                return [];
            }
            mysqli_stmt_bind_param($stmt, 's', $batch_key);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            $rows = [];
            if ($res) {
                while ($row = mysqli_fetch_assoc($res)) {
                    $rows[] = $row;
                }
            }
            mysqli_stmt_close($stmt);
            return $rows;
        }

        foreach (bpis_group_pending_borrowing_requests($conn) as $group) {
            if ($group['batch_key'] === $batch_key) {
                return $group['requests'];
            }
        }

        return [];
    }
}

if (!function_exists('bpis_format_batch_items_summary')) {
    /**
     * @param list<array{label:string,qty:int,request_id:int}> $item_lines
     */
    function bpis_format_batch_items_summary(array $item_lines): string
    {
        $parts = [];
        foreach ($item_lines as $line) {
            $label = trim((string) ($line['label'] ?? ''));
            $qty = (int) ($line['qty'] ?? 1);
            if ($label === '') {
                continue;
            }
            $parts[] = $qty . ' × ' . $label;
        }

        return $parts !== [] ? implode('; ', $parts) : '';
    }
}

if (!function_exists('bpis_approve_borrowing_batch')) {
    /**
     * Approve all pending lines in a batch (all-or-nothing on stock check).
     *
     * @return array{ok:bool,error?:string,otp?:int,borrower_name?:string,borrower_email?:string,item_summary?:string,approved_ids?:list<int>}
     */
    function bpis_approve_borrowing_batch(mysqli $conn, string $batch_key): array
    {
        $requests = bpis_get_pending_requests_for_batch($conn, $batch_key);
        if ($requests === []) {
            return ['ok' => false, 'error' => 'not_found'];
        }

        $planned = [];
        $qty_by_asset = [];
        foreach ($requests as $request_row) {
            $requested_qty = (int) ($request_row['qty'] ?? $request_row['quantity'] ?? 1);
            if ($requested_qty < 1) {
                $requested_qty = 1;
            }
            $req_asset_id = (int) ($request_row['asset_id'] ?? 0);
            $req_item = bpis_borrow_request_item_label($conn, $request_row);

            $asset_row = null;
            if ($req_asset_id > 0) {
                $asset_result = mysqli_query($conn, "SELECT * FROM asset WHERE id = {$req_asset_id} LIMIT 1");
                if ($asset_result && mysqli_num_rows($asset_result) > 0) {
                    $asset_row = mysqli_fetch_assoc($asset_result);
                }
            }
            if (!$asset_row && $req_item !== '') {
                $safe_item = mysqli_real_escape_string($conn, $req_item);
                $asset_result = mysqli_query($conn, "SELECT * FROM asset WHERE description = '{$safe_item}' LIMIT 1");
                if ($asset_result && mysqli_num_rows($asset_result) > 0) {
                    $asset_row = mysqli_fetch_assoc($asset_result);
                }
            }

            if (!$asset_row) {
                return ['ok' => false, 'error' => 'no_asset'];
            }
            if (!bpis_asset_row_is_borrowable($asset_row)) {
                return ['ok' => false, 'error' => 'not_borrowable'];
            }

            $asset_id = (int) ($asset_row['id'] ?? 0);
            $qty_by_asset[$asset_id] = ($qty_by_asset[$asset_id] ?? 0) + $requested_qty;

            $planned[] = [
                'request_id' => (int) ($request_row['id'] ?? 0),
                'asset_id' => $asset_id,
                'asset_row' => $asset_row,
                'qty' => $requested_qty,
                'label' => $req_item,
            ];
        }

        $asset_cache = [];
        foreach ($qty_by_asset as $asset_id => $total_needed) {
            foreach ($planned as $p) {
                if ($p['asset_id'] === $asset_id) {
                    $asset_cache[$asset_id] = $p['asset_row'];
                    break;
                }
            }
            if (!isset($asset_cache[$asset_id])) {
                continue;
            }
            $stock_stats = bpis_asset_unit_availability_stats($conn, $asset_cache[$asset_id]);
            if ($stock_stats['available'] < $total_needed) {
                return ['ok' => false, 'error' => 'insufficient_stock'];
            }
        }

        $asset_columns = [];
        $asset_cols_rs = mysqli_query($conn, 'SHOW COLUMNS FROM asset');
        if ($asset_cols_rs) {
            while ($c = mysqli_fetch_assoc($asset_cols_rs)) {
                $asset_columns[$c['Field']] = true;
            }
        }
        $asset_has_borrowed_qty = isset($asset_columns['borrowed_qty']);

        $otp = random_int(100000, 999999);
        $approved_ids = [];
        $item_parts = [];

        $borrowed_delta = [];
        foreach ($planned as $plan) {
            $asset_id = $plan['asset_id'];
            $requested_qty = $plan['qty'];
            $asset_row = $plan['asset_row'];
            $r_id = $plan['request_id'];

            $borrowed_now = (int) bpis_asset_unit_availability_stats($conn, $asset_row)['borrowed'];
            $borrowed_now += (int) ($borrowed_delta[$asset_id] ?? 0);
            $total_qty = (int) ($asset_row['quantity'] ?? 0);
            if ($total_qty <= 0) {
                $fallback_qty = (int) preg_replace('/[^0-9]/', '', (string) ($asset_row['article'] ?? ''));
                $total_qty = $fallback_qty > 0 ? $fallback_qty : 0;
            }

            if ($asset_has_borrowed_qty) {
                $new_borrowed = $borrowed_now + $requested_qty;
                mysqli_query($conn, "UPDATE asset SET borrowed_qty = {$new_borrowed} WHERE id = {$asset_id}");
                $borrowed_delta[$asset_id] = ($borrowed_delta[$asset_id] ?? 0) + $requested_qty;
            } else {
                $new_qty = max(0, $total_qty - $requested_qty);
                mysqli_query($conn, "UPDATE asset SET quantity = {$new_qty} WHERE id = {$asset_id}");
            }

            $otp_esc = (int) $otp;
            mysqli_query($conn, "UPDATE borrowing_requests SET status = 'Approved', otp = '{$otp_esc}' WHERE id = {$r_id}");
            $approved_ids[] = $r_id;
            $item_parts[] = $requested_qty . ' × ' . $plan['label'];

            $snap = bpis_asset_stock_snapshot($conn, $asset_id);
            bpis_notify_admins_by_roles(
                $conn,
                ['Treasurer'],
                'Stock update',
                $requested_qty . ' ' . $snap['label'] . ' borrowed. Available stock: ' . $snap['available'] . '.',
                'treasurer/inventory.php',
                'stock_borrow_approved',
                $r_id,
                null
            );
        }

        $first = $requests[0];
        $borrower_name = (string) ($first['full_name'] ?? 'Borrower');
        $borrower_email = (string) ($first['email'] ?? '');

        bpis_notify_admins_by_roles(
            $conn,
            ['Secretary'],
            'Request approved',
            'Borrow request approved for ' . $borrower_name . ' (' . count($approved_ids) . ' item(s)).',
            'secretary/requests.php',
            'request_approved',
            $approved_ids[0] ?? 0,
            null
        );
        bpis_notify_admins_by_roles(
            $conn,
            ['Barangay Captain'],
            'Borrowing activity',
            'Borrowing activity: ' . $borrower_name . ' approved for ' . count($approved_ids) . ' item(s).',
            'captain/borrowing_logs.php',
            'borrow_approved_overview',
            $approved_ids[0] ?? 0,
            null
        );

        return [
            'ok' => true,
            'otp' => $otp,
            'borrower_name' => $borrower_name,
            'borrower_email' => $borrower_email,
            'item_summary' => implode('; ', $item_parts),
            'approved_ids' => $approved_ids,
        ];
    }
}

if (!function_exists('bpis_reject_borrowing_batch')) {
    /**
     * @return array{ok:bool,borrower_name?:string,borrower_email?:string}
     */
    function bpis_reject_borrowing_batch(mysqli $conn, string $batch_key): array
    {
        $requests = bpis_get_pending_requests_for_batch($conn, $batch_key);
        if ($requests === []) {
            return ['ok' => false];
        }

        $ids = [];
        foreach ($requests as $row) {
            $ids[] = (int) ($row['id'] ?? 0);
        }
        $ids = array_values(array_filter($ids));
        if ($ids === []) {
            return ['ok' => false];
        }

        $id_list = implode(',', $ids);
        mysqli_query($conn, "UPDATE borrowing_requests SET status = 'Rejected' WHERE id IN ({$id_list})");

        $first = $requests[0];
        $borrower_name = (string) ($first['full_name'] ?? 'Borrower');

        bpis_notify_admins_by_roles(
            $conn,
            ['Secretary'],
            'Request declined',
            'Borrow request was not approved for ' . $borrower_name . ' (' . count($ids) . ' item(s)).',
            'secretary/requests.php',
            'request_rejected',
            $ids[0],
            null
        );

        return [
            'ok' => true,
            'borrower_name' => $borrower_name,
            'borrower_email' => (string) ($first['email'] ?? ''),
        ];
    }
}
