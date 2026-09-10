<?php
/**
 * Resolve borrowing_requests item text for display and know which columns store item labels.
 */

if (!function_exists('bpis_borrow_request_item_columns_for_schema')) {
    /**
     * Column names on borrowing_requests that store the human-readable item label (may coexist).
     *
     * @param array<string,bool> $existing_columns Keys from SHOW COLUMNS (Field => true)
     * @return list<string>
     */
    function bpis_borrow_request_item_columns_for_schema(array $existing_columns) {
        $names = [];
        foreach (['search_item', 'item', 'item_name', 'borrow_item', 'requested_item'] as $c) {
            if (!empty($existing_columns[$c])) {
                $names[] = $c;
            }
        }
        return $names;
    }
}

if (!function_exists('bpis_borrow_request_item_label')) {
    /**
     * Best-effort label for secretary/treasurer tables: text columns first, then asset.description.
     *
     * @param mysqli|null $conn
     * @param array<string,mixed> $row Row from borrowing_requests
     */
    function bpis_borrow_request_item_label($conn, array $row) {
        foreach (['search_item', 'item', 'item_name', 'borrow_item', 'requested_item', 'description', 'item_description'] as $k) {
            if (isset($row[$k])) {
                $t = trim((string)$row[$k]);
                if ($t !== '') {
                    return $t;
                }
            }
        }
        $aid = (int)($row['asset_id'] ?? 0);
        if ($aid > 0 && $conn instanceof mysqli) {
            $aid = (int)$aid;
            $q = mysqli_query($conn, "SELECT description, article FROM asset WHERE id = {$aid} LIMIT 1");
            if ($q && ($a = mysqli_fetch_assoc($q))) {
                $d = trim((string)($a['description'] ?? ''));
                if ($d !== '') {
                    return $d;
                }
                return trim((string)($a['article'] ?? ''));
            }
        }
        return '';
    }
}
