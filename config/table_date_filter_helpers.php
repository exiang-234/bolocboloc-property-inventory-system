<?php
/**
 * Normalized Y-m-d for table row filtering (client-side).
 */

if (!function_exists('bpis_row_date_attr')) {
    /**
     * @param array<string,mixed> $row
     * @param list<string> $keys Try in order; first parseable date wins
     */
    function bpis_row_date_attr(array $row, array $keys): string {
        foreach ($keys as $k) {
            if (empty($row[$k])) {
                continue;
            }
            $raw = trim((string)$row[$k]);
            if ($raw === '') {
                continue;
            }
            $ts = strtotime($raw);
            if ($ts === false || $ts <= 0) {
                continue;
            }
            $ymd = date('Y-m-d', $ts);
            if ($ymd === '1970-01-01') {
                continue;
            }
            return ' data-row-date="' . htmlspecialchars($ymd, ENT_QUOTES, 'UTF-8') . '"';
        }
        return '';
    }
}
