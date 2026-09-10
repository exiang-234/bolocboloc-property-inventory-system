<?php

require_once __DIR__ . '/schema_bootstrap.php';
require_once __DIR__ . '/borrower_list_helpers.php';

if (!function_exists('bpis_borrowing_request_address_column')) {
    function bpis_borrowing_request_address_column(mysqli $conn): ?string
    {
        if (bpis_column_exists($conn, 'borrowing_requests', 'purok')) {
            return 'purok';
        }
        if (bpis_column_exists($conn, 'borrowing_requests', 'address')) {
            return 'address';
        }

        return null;
    }
}

if (!function_exists('bpis_borrowing_request_contact_column')) {
    function bpis_borrowing_request_contact_column(mysqli $conn): ?string
    {
        if (bpis_column_exists($conn, 'borrowing_requests', 'contact_number')) {
            return 'contact_number';
        }
        if (bpis_column_exists($conn, 'borrowing_requests', 'contact')) {
            return 'contact';
        }

        return null;
    }
}

if (!function_exists('bpis_upsert_borrower_profile')) {
    require_once dirname(__DIR__) . '/borrower/submit_request_helpers.php';
}

if (!function_exists('bpis_sync_borrower_profiles_from_requests')) {
    /**
     * Create missing borrower_profiles from borrowing_requests and link borrower_profile_id.
     */
    function bpis_sync_borrower_profiles_from_requests(mysqli $conn): int
    {
        if (!bpis_table_exists($conn, 'borrower_profiles') || !bpis_table_exists($conn, 'borrowing_requests')) {
            return 0;
        }
        if (!bpis_column_exists($conn, 'borrowing_requests', 'email')) {
            return 0;
        }

        $addr_col = bpis_borrowing_request_address_column($conn);
        $contact_col = bpis_borrowing_request_contact_column($conn);
        $cols = ['full_name', 'email'];
        if ($contact_col) {
            $cols[] = $contact_col;
        }
        if ($addr_col) {
            $cols[] = $addr_col;
        }
        if (bpis_column_exists($conn, 'borrowing_requests', 'created_at')) {
            $cols[] = 'created_at';
        }

        $sql = 'SELECT ' . implode(', ', $cols) . " FROM borrowing_requests
                WHERE TRIM(COALESCE(email, '')) <> ''
                ORDER BY created_at DESC";
        $rs = mysqli_query($conn, $sql);
        if (!$rs) {
            return 0;
        }

        $by_email = [];
        while ($row = mysqli_fetch_assoc($rs)) {
            $email = strtolower(trim((string) ($row['email'] ?? '')));
            if ($email === '' || isset($by_email[$email])) {
                continue;
            }
            $by_email[$email] = $row;
        }

        $linked = 0;
        foreach ($by_email as $email => $row) {
            $name = trim((string) ($row['full_name'] ?? ''));
            $contact = trim((string) ($row[$contact_col ?? ''] ?? ''));
            $purok = trim((string) ($row[$addr_col ?? ''] ?? ''));
            if ($name === '') {
                continue;
            }

            $pid = bpis_upsert_borrower_profile($conn, $name, $email, $purok, $contact);
            if ($pid <= 0) {
                continue;
            }

            if (bpis_column_exists($conn, 'borrowing_requests', 'borrower_profile_id')) {
                $stmt = $conn->prepare(
                    'UPDATE borrowing_requests SET borrower_profile_id = ? WHERE LOWER(TRIM(email)) = ? AND (borrower_profile_id IS NULL OR borrower_profile_id = 0)'
                );
                if ($stmt) {
                    $stmt->bind_param('is', $pid, $email);
                    $stmt->execute();
                    $linked += $stmt->affected_rows;
                    $stmt->close();
                }
            } else {
                $linked++;
            }
        }

        return $linked;
    }
}

if (!function_exists('bpis_apply_borrower_vetting')) {
    function bpis_apply_borrower_vetting(mysqli $conn, int $profile_id, string $status, string $notes, int $verified_by): bool
    {
        if ($profile_id <= 0 || !bpis_table_exists($conn, 'borrower_profiles')) {
            return false;
        }
        if (!in_array($status, ['Pending', 'Verified', 'Rejected'], true)) {
            $status = 'Pending';
        }

        $stmt = $conn->prepare(
            'UPDATE borrower_profiles SET vetting_status = ?, vetting_notes = ?, verified_by = ?, verified_at = NOW() WHERE id = ?'
        );
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('ssii', $status, $notes, $verified_by, $profile_id);
        $ok = $stmt->execute();
        $stmt->close();
        if (!$ok) {
            return false;
        }

        if (bpis_column_exists($conn, 'borrowing_requests', 'vetting_status')) {
            if (bpis_column_exists($conn, 'borrowing_requests', 'borrower_profile_id')) {
                $upd = $conn->prepare('UPDATE borrowing_requests SET vetting_status = ? WHERE borrower_profile_id = ?');
                if ($upd) {
                    $upd->bind_param('si', $status, $profile_id);
                    $upd->execute();
                    $upd->close();
                }
            } else {
                $email_stmt = $conn->prepare('SELECT email FROM borrower_profiles WHERE id = ? LIMIT 1');
                if ($email_stmt) {
                    $email_stmt->bind_param('i', $profile_id);
                    $email_stmt->execute();
                    $email_rs = $email_stmt->get_result();
                    $prow = $email_rs ? $email_rs->fetch_assoc() : null;
                    $email_stmt->close();
                    $email = strtolower(trim((string) ($prow['email'] ?? '')));
                    if ($email !== '') {
                        $upd = $conn->prepare('UPDATE borrowing_requests SET vetting_status = ? WHERE LOWER(TRIM(email)) = ?');
                        if ($upd) {
                            $upd->bind_param('ss', $status, $email);
                            $upd->execute();
                            $upd->close();
                        }
                    }
                }
            }
        }

        return true;
    }
}

if (!function_exists('bpis_resolve_profile_id_from_request')) {
    function bpis_resolve_profile_id_from_request(mysqli $conn, int $request_id): int
    {
        if ($request_id <= 0 || !bpis_table_exists($conn, 'borrowing_requests')) {
            return 0;
        }

        $cols = ['id', 'full_name', 'email'];
        $addr = bpis_borrowing_request_address_column($conn);
        $contact = bpis_borrowing_request_contact_column($conn);
        if ($contact) {
            $cols[] = $contact;
        }
        if ($addr) {
            $cols[] = $addr;
        }
        if (bpis_column_exists($conn, 'borrowing_requests', 'borrower_profile_id')) {
            $cols[] = 'borrower_profile_id';
        }

        $stmt = $conn->prepare('SELECT ' . implode(', ', $cols) . ' FROM borrowing_requests WHERE id = ? LIMIT 1');
        if (!$stmt) {
            return 0;
        }
        $stmt->bind_param('i', $request_id);
        $stmt->execute();
        $rs = $stmt->get_result();
        $row = $rs ? $rs->fetch_assoc() : null;
        $stmt->close();
        if (!$row) {
            return 0;
        }

        $existing = (int) ($row['borrower_profile_id'] ?? 0);
        if ($existing > 0) {
            return $existing;
        }

        $email = strtolower(trim((string) ($row['email'] ?? '')));
        $name = trim((string) ($row['full_name'] ?? ''));
        if ($email === '' || $name === '') {
            return 0;
        }

        $contact_val = $contact_col ? trim((string) ($row[$contact_col] ?? '')) : '';
        $purok_val = $addr_col ? trim((string) ($row[$addr_col] ?? '')) : '';
        $pid = bpis_upsert_borrower_profile($conn, $name, $email, $purok_val, $contact_val);

        if ($pid > 0 && bpis_column_exists($conn, 'borrowing_requests', 'borrower_profile_id')) {
            $link = $conn->prepare('UPDATE borrowing_requests SET borrower_profile_id = ? WHERE id = ?');
            if ($link) {
                $link->bind_param('ii', $pid, $request_id);
                $link->execute();
                $link->close();
            }
        }

        return $pid;
    }
}

if (!function_exists('bpis_list_pending_vetting_by_borrower')) {
    /**
     * @return list<array{email:string,full_name:string,contact_number:string,purok:string,profile_id:int,vetting_status:string,request_count:int,latest_at:string,request_ids:int[]}>
     */
    function bpis_list_pending_vetting_by_borrower(mysqli $conn): array
    {
        if (!bpis_table_exists($conn, 'borrowing_requests')) {
            return [];
        }

        $has_vetting = bpis_column_exists($conn, 'borrowing_requests', 'vetting_status');
        $has_profile_id = bpis_column_exists($conn, 'borrowing_requests', 'borrower_profile_id');
        $has_created = bpis_column_exists($conn, 'borrowing_requests', 'created_at');
        $contact_col = bpis_borrowing_request_contact_column($conn);
        $addr_col = bpis_borrowing_request_address_column($conn);

        $where = "TRIM(COALESCE(email, '')) <> ''";
        if ($has_vetting) {
            $where .= " AND vetting_status = 'Pending'";
        }

        $cols = ['id', 'full_name', 'email'];
        if ($has_profile_id) {
            $cols[] = 'borrower_profile_id';
        }
        if ($has_vetting) {
            $cols[] = 'vetting_status';
        }
        if ($has_created) {
            $cols[] = 'created_at';
        }

        $sql = 'SELECT ' . implode(', ', $cols) . " FROM borrowing_requests WHERE {$where} ORDER BY created_at DESC";
        $rs = mysqli_query($conn, $sql);
        if (!$rs) {
            return [];
        }

        $groups = [];
        while ($row = mysqli_fetch_assoc($rs)) {
            $email = strtolower(trim((string) ($row['email'] ?? '')));
            if ($email === '') {
                continue;
            }
            if (!isset($groups[$email])) {
                $groups[$email] = [
                    'email' => $email,
                    'full_name' => trim((string) ($row['full_name'] ?? '')),
                    'contact_number' => '',
                    'purok' => '',
                    'profile_id' => (int) ($row['borrower_profile_id'] ?? 0),
                    'vetting_status' => (string) ($row['vetting_status'] ?? 'Pending'),
                    'request_count' => 0,
                    'latest_at' => (string) ($row['created_at'] ?? ''),
                    'request_ids' => [],
                ];
            }
            $groups[$email]['request_count']++;
            $groups[$email]['request_ids'][] = (int) ($row['id'] ?? 0);
            if (trim((string) ($row['full_name'] ?? '')) !== '') {
                $groups[$email]['full_name'] = trim((string) $row['full_name']);
            }
            if ((int) ($row['borrower_profile_id'] ?? 0) > 0) {
                $groups[$email]['profile_id'] = (int) $row['borrower_profile_id'];
            }
        }

        if ($groups && bpis_table_exists($conn, 'borrower_profiles')) {
            foreach (array_keys($groups) as $email) {
                $stmt = $conn->prepare(
                    'SELECT id, full_name, contact_number, purok, photo_path, vetting_status FROM borrower_profiles WHERE LOWER(email) = ? LIMIT 1'
                );
                if ($stmt) {
                    $stmt->bind_param('s', $email);
                    $stmt->execute();
                    $prs = $stmt->get_result();
                    if ($prs && ($p = $prs->fetch_assoc())) {
                        $groups[$email]['profile_id'] = (int) ($p['id'] ?? 0);
                        $groups[$email]['full_name'] = (string) ($p['full_name'] ?? $groups[$email]['full_name']);
                        $groups[$email]['contact_number'] = (string) ($p['contact_number'] ?? '');
                        $groups[$email]['purok'] = (string) ($p['purok'] ?? '');
                        $groups[$email]['photo_path'] = (string) ($p['photo_path'] ?? '');
                        $groups[$email]['vetting_status'] = (string) ($p['vetting_status'] ?? 'Pending');
                    }
                    $stmt->close();
                }
            }
        }

        $list = array_values($groups);
        usort($list, static function ($a, $b) {
            return strcmp((string) ($b['latest_at'] ?? ''), (string) ($a['latest_at'] ?? ''));
        });

        return $list;
    }
}

if (!function_exists('bpis_list_borrower_profiles_for_vetting')) {
    function bpis_list_borrower_profiles_for_vetting(mysqli $conn, int $limit = 200): array
    {
        if (!bpis_table_exists($conn, 'borrower_profiles')) {
            return [];
        }

        $rs = mysqli_query(
            $conn,
            "SELECT bp.*,
                (SELECT COUNT(*) FROM borrowing_requests br
                 WHERE LOWER(TRIM(br.email)) = LOWER(TRIM(bp.email))) AS request_count
             FROM borrower_profiles bp
             ORDER BY FIELD(bp.vetting_status, 'Pending', 'Rejected', 'Verified'), bp.created_at DESC
             LIMIT " . (int) $limit
        );
        if (!$rs) {
            $rs = mysqli_query($conn, 'SELECT * FROM borrower_profiles ORDER BY created_at DESC LIMIT ' . (int) $limit);
        }
        if (!$rs) {
            return [];
        }
        $out = [];
        while ($row = mysqli_fetch_assoc($rs)) {
            $out[] = $row;
        }

        return $out;
    }
}
