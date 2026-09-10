<?php

require_once __DIR__ . '/asset_units_helpers.php';

if (!function_exists('bpis_borrowable_asset_categories')) {
    /**
     * Movable categories borrowers may request online.
     *
     * @return list<string>
     */
    function bpis_borrowable_asset_categories(): array
    {
        return [
            'Tools',
            'Furniture & Fixtures',
            'Equipment',
        ];
    }
}

if (!function_exists('bpis_borrowable_categories_label')) {
    /** Human-readable list for borrower UI messages. */
    function bpis_borrowable_categories_label(): string
    {
        return implode(', ', bpis_borrowable_asset_categories());
    }
}

if (!function_exists('bpis_asset_category_is_borrowable')) {
    function bpis_asset_category_is_borrowable(?string $category): bool
    {
        return in_array(trim((string) $category), bpis_borrowable_asset_categories(), true);
    }
}

if (!function_exists('bpis_sql_asset_borrowable_category_clause')) {
    /** SQL AND fragment: category IN (borrowable list). */
    function bpis_sql_asset_borrowable_category_clause(mysqli $conn): string
    {
        $parts = [];
        foreach (bpis_borrowable_asset_categories() as $cat) {
            $parts[] = "'" . mysqli_real_escape_string($conn, $cat) . "'";
        }
        if ($parts === []) {
            return '';
        }

        return ' AND category IN (' . implode(', ', $parts) . ') ';
    }
}

if (!function_exists('bpis_asset_remarks_indicate_unavailable')) {
    /** Same rule as treasurer inventory availability (remarks / article, not item_condition alone). */
    function bpis_asset_remarks_indicate_unavailable(array   $row): bool
    {
        $article = strtoupper(trim((string) ($row['article'] ?? '')));
        if ($article === 'UNSERVICEABLE') {
            return true;
        }

        $r = strtoupper(trim((string) ($row['remarks'] ?? '')));
        if ($r === 'UNSERVICEABLE' || $r === 'DISPOSED' || $r === 'DISPOSE') {
            return true;
        }
        if ($r !== '' && (strpos($r, 'UNSERVICEABLE') !== false || strpos($r, 'DISPOSE') !== false)) {
            return true;
        }

        return false;
    }
}

if (!function_exists('bpis_normalize_registration_remarks')) {
    function bpis_normalize_registration_remarks(?string $remarks): string
    {
        $r = strtoupper(trim((string) $remarks));
        if ($r === 'UNDER REPAIR') {
            return 'UNDER REPAIR';
        }

        return 'SERVICEABLE';
    }
}

if (!function_exists('bpis_asset_row_is_borrowable')) {
    /**
     * @param array<string,mixed> $row Asset row (category, article, remarks, …)
     */
    function bpis_asset_row_is_borrowable(array $row): bool
    {
        if (!bpis_asset_category_is_borrowable($row['category'] ?? '')) {
            return false;
        }

        return !bpis_asset_remarks_indicate_unavailable($row);
    }
}

if (!function_exists('bpis_asset_id_is_borrowable')) {
    function bpis_asset_id_is_borrowable($conn, int $id): bool
    {
        if ($id <= 0 || !$conn) {
            return false;
        }
        $stmt = mysqli_prepare($conn, 'SELECT * FROM asset WHERE id = ? LIMIT 1');
        if (!$stmt) {
            return false;
        }
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        if (!$row) {
            return false;
        }

        return bpis_asset_row_is_borrowable($row);
    }
}

if (!function_exists('bpis_sql_asset_borrowable_where')) {
    /**
     * SQL AND-clause: exclude unserviceable remarks/article (aligned with inventory list).
     */
    function bpis_sql_asset_borrowable_where(bool $has_item_condition = false): string
    {
        unset($has_item_condition);

        $remarksCond = "
            UPPER(TRIM(COALESCE(remarks,''))) IN ('UNSERVICEABLE','UNSERVICEABLE/DISPOSE','DISPOSED','DISPOSE')
            OR UPPER(TRIM(COALESCE(remarks,''))) LIKE '%UNSERVICEABLE%'
            OR UPPER(TRIM(COALESCE(remarks,''))) LIKE '%DISPOSE%'
        ";
        $articleCond = " OR UPPER(TRIM(COALESCE(article,''))) = 'UNSERVICEABLE' ";

        return ' AND NOT (' . $remarksCond . $articleCond . ') ';
    }
}

if (!function_exists('bpis_asset_borrowable_available_qty')) {
    /**
     * Units that are serviceable and not already borrowed out.
     * Formula: asset.quantity - SUM(borrower.qty) WHERE status != 'Returned'
     *
     * @param array<string,mixed> $row
     */
    function bpis_asset_borrowable_available_qty(mysqli $conn, array $row): int
    {
        if (!bpis_asset_row_is_borrowable($row)) {
            return 0;
        }
        
        $asset_id = (int)($row['id'] ?? 0);
        if ($asset_id <= 0) {
            return 0;
        }
        
        $total_qty = (int)($row['quantity'] ?? 0);
        
        $sql = "SELECT COALESCE(SUM(qty), 0) as borrowed_total 
                FROM borrower 
                WHERE asset_id = ? 
                AND status NOT IN ('Returned')";
        
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return $total_qty;
        }
        mysqli_stmt_bind_param($stmt, "i", $asset_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $data = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        
        $borrowed_total = (int)($data['borrowed_total'] ?? 0);
        $available = max(0, $total_qty - $borrowed_total);
        
        return $available;
    }
}

if (!function_exists('bpis_asset_remarks_status_label')) {
    /**
     * Display label for borrower catalog badge: SERVICEABLE or UNSERVICEABLE.
     */
    function bpis_asset_remarks_status_label(array $row): string
    {
        if (bpis_asset_remarks_indicate_unavailable($row)) {
            return 'UNSERVICEABLE';
        }

        $remarks = strtoupper(trim((string) ($row['remarks'] ?? '')));
        $condition = strtoupper(trim((string) ($row['item_condition'] ?? '')));

        if ($remarks === 'SERVICEABLE' || $condition === 'SERVICEABLE') {
            return 'SERVICEABLE';
        }

        return bpis_asset_row_is_borrowable($row) ? 'SERVICEABLE' : 'UNSERVICEABLE';
    }
}