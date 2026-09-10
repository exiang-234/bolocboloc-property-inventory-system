<?php

require_once __DIR__ . '/schema_bootstrap.php';

if (!function_exists('bpis_borrower_avatar_url')) {
    function bpis_borrower_avatar_url(string $full_name, ?string $photo_path = null): string
    {
        $path = trim((string) $photo_path);
        if ($path !== '') {
            return '../' . ltrim(str_replace('\\', '/', $path), '/');
        }
        $name = rawurlencode($full_name !== '' ? $full_name : 'Borrower');

        return 'https://ui-avatars.com/api/?name=' . $name . '&background=174C7D&color=fff&size=128';
    }
}

if (!function_exists('bpis_list_all_borrowers')) {
    /**
     * Unified borrower directory: profiles + distinct emails from requests and borrower records.
     *
     * @return list<array{key:string,profile_id:int,full_name:string,email:string,contact_number:string,purok:string,photo_path:?string,vetting_status:string,source:string,is_blocked:bool}>
     */
    function bpis_list_all_borrowers(mysqli $conn): array
    {
        $by_key = [];

        $put = static function (array &$map, string $key, array $row): void {
            if ($key === '') {
                return;
            }
            if (!isset($map[$key])) {
                $map[$key] = $row;
                return;
            }
            $existing = $map[$key];
            foreach ($row as $field => $value) {
                if (($existing[$field] ?? '') === '' && (string) $value !== '') {
                    $existing[$field] = $value;
                }
            }
            if ((int) ($row['profile_id'] ?? 0) > (int) ($existing['profile_id'] ?? 0)) {
                $existing['profile_id'] = (int) $row['profile_id'];
                $existing['photo_path'] = $row['photo_path'] ?? $existing['photo_path'];
                $existing['vetting_status'] = $row['vetting_status'] ?? $existing['vetting_status'];
                $existing['source'] = $row['source'] ?? $existing['source'];
            }
            $map[$key] = $existing;
        };

        if (bpis_table_exists($conn, 'borrower_profiles')) {
            $rs = mysqli_query(
                $conn,
                'SELECT id, full_name, email, contact_number, purok, photo_path, vetting_status
                 FROM borrower_profiles
                 ORDER BY full_name ASC'
            );
            if ($rs) {
                while ($row = mysqli_fetch_assoc($rs)) {
                    $email = strtolower(trim((string) ($row['email'] ?? '')));
                    $key = $email !== '' ? 'e:' . $email : 'n:' . strtolower(trim((string) ($row['full_name'] ?? '')));
                    $put($by_key, $key, [
                        'key' => $key,
                        'profile_id' => (int) ($row['id'] ?? 0),
                        'full_name' => trim((string) ($row['full_name'] ?? '')),
                        'email' => $email,
                        'contact_number' => trim((string) ($row['contact_number'] ?? '')),
                        'purok' => trim((string) ($row['purok'] ?? '')),
                        'photo_path' => trim((string) ($row['photo_path'] ?? '')),
                        'vetting_status' => (string) ($row['vetting_status'] ?? 'Pending'),
                        'source' => 'profile',
                        'is_blocked' => false,
                    ]);
                }
            }
        }

        if (bpis_table_exists($conn, 'borrowing_requests')) {
            $contact_col = bpis_column_exists($conn, 'borrowing_requests', 'contact_number')
                ? 'contact_number' : (bpis_column_exists($conn, 'borrowing_requests', 'contact') ? 'contact' : null);
            $addr_col = bpis_column_exists($conn, 'borrowing_requests', 'purok')
                ? 'purok' : (bpis_column_exists($conn, 'borrowing_requests', 'address') ? 'address' : null);
            $cols = ['full_name'];
            if (bpis_column_exists($conn, 'borrowing_requests', 'email')) {
                $cols[] = 'email';
            }
            if ($contact_col) {
                $cols[] = $contact_col . ' AS contact_number';
            }
            if ($addr_col) {
                $cols[] = $addr_col . ' AS purok';
            }
            $sql = 'SELECT ' . implode(', ', $cols) . ' FROM borrowing_requests
                    WHERE TRIM(COALESCE(full_name, \'\')) <> \'\'';
            $rs = mysqli_query($conn, $sql);
            if ($rs) {
                while ($row = mysqli_fetch_assoc($rs)) {
                    $email = strtolower(trim((string) ($row['email'] ?? '')));
                    $name = trim((string) ($row['full_name'] ?? ''));
                    $key = $email !== '' ? 'e:' . $email : 'n:' . strtolower($name);
                    $put($by_key, $key, [
                        'key' => $key,
                        'profile_id' => 0,
                        'full_name' => $name,
                        'email' => $email,
                        'contact_number' => trim((string) ($row['contact_number'] ?? '')),
                        'purok' => trim((string) ($row['purok'] ?? '')),
                        'photo_path' => '',
                        'vetting_status' => '',
                        'source' => 'request',
                        'is_blocked' => false,
                    ]);
                }
            }
        }

        if (bpis_table_exists($conn, 'borrower')) {
            $contact_col = bpis_column_exists($conn, 'borrower', 'contact_number')
                ? 'contact_number' : (bpis_column_exists($conn, 'borrower', 'contact') ? 'contact' : null);
            $addr_col = bpis_column_exists($conn, 'borrower', 'purok')
                ? 'purok' : (bpis_column_exists($conn, 'borrower', 'address') ? 'address' : null);
            $cols = ['full_name'];
            if (bpis_column_exists($conn, 'borrower', 'email')) {
                $cols[] = 'email';
            }
            if ($contact_col) {
                $cols[] = $contact_col . ' AS contact_number';
            }
            if ($addr_col) {
                $cols[] = $addr_col . ' AS purok';
            }
            $sql = 'SELECT ' . implode(', ', $cols) . ' FROM borrower
                    WHERE TRIM(COALESCE(full_name, \'\')) <> \'\'';
            $rs = mysqli_query($conn, $sql);
            if ($rs) {
                while ($row = mysqli_fetch_assoc($rs)) {
                    $email = strtolower(trim((string) ($row['email'] ?? '')));
                    $name = trim((string) ($row['full_name'] ?? ''));
                    $key = $email !== '' ? 'e:' . $email : 'n:' . strtolower($name);
                    $put($by_key, $key, [
                        'key' => $key,
                        'profile_id' => 0,
                        'full_name' => $name,
                        'email' => $email,
                        'contact_number' => trim((string) ($row['contact_number'] ?? '')),
                        'purok' => trim((string) ($row['purok'] ?? '')),
                        'photo_path' => '',
                        'vetting_status' => '',
                        'source' => 'borrower',
                        'is_blocked' => false,
                    ]);
                }
            }
        }

        $blocked_emails = [];
        $blocked_names = [];
        if (bpis_table_exists($conn, 'borrower_blocklist')) {
            $br = mysqli_query($conn, 'SELECT LOWER(TRIM(email)) AS email, LOWER(TRIM(full_name)) AS full_name FROM borrower_blocklist WHERE is_active = 1');
            if ($br) {
                while ($row = mysqli_fetch_assoc($br)) {
                    $em = (string) ($row['email'] ?? '');
                    $nm = (string) ($row['full_name'] ?? '');
                    if ($em !== '') {
                        $blocked_emails[$em] = true;
                    }
                    if ($nm !== '') {
                        $blocked_names[$nm] = true;
                    }
                }
            }
        }

        $list = array_values($by_key);
        foreach ($list as &$item) {
            $em = strtolower(trim((string) ($item['email'] ?? '')));
            $nm = strtolower(trim((string) ($item['full_name'] ?? '')));
            $item['is_blocked'] = ($em !== '' && isset($blocked_emails[$em]))
                || ($nm !== '' && isset($blocked_names[$nm]));
        }
        unset($item);

        usort($list, static function ($a, $b) {
            return strcasecmp((string) ($a['full_name'] ?? ''), (string) ($b['full_name'] ?? ''));
        });

        return $list;
    }
}
