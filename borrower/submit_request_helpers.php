<?php

require_once __DIR__ . '/../config/borrow_request_display_helpers.php';
require_once __DIR__ . '/../config/asset_borrowable_helpers.php';
require_once __DIR__ . '/../config/auth_helpers.php';

if (!function_exists('bpis_upload_public_url')) {
    function bpis_upload_public_url(string $relative_path, string $prefix = '../'): string
    {
        $relative_path = trim($relative_path);
        if ($relative_path === '') {
            return '';
        }
        if (preg_match('#^https?://#i', $relative_path)) {
            return $relative_path;
        }

        return $prefix . ltrim(str_replace('\\', '/', $relative_path), '/');
    }
}

if (!function_exists('bpis_borrower_valid_id_paths_for_request')) {
    /**
     * @return array{front: string, back: string}
     */
    function bpis_borrower_valid_id_paths_for_request(mysqli $conn, array $request): array
    {
        $empty = ['front' => '', 'back' => ''];
        if (!bpis_table_exists($conn, 'borrower_profiles')) {
            return $empty;
        }

        $has_front = bpis_column_exists($conn, 'borrower_profiles', 'id_document_path');
        $has_back = bpis_column_exists($conn, 'borrower_profiles', 'id_document_back_path');
        if (!$has_front && !$has_back) {
            return $empty;
        }

        $cols = ['id'];
        if ($has_front) {
            $cols[] = 'id_document_path';
        }
        if ($has_back) {
            $cols[] = 'id_document_back_path';
        }

        $fetch_row = function (?int $profile_id, string $email = '') use ($conn, $cols): ?array {
            if ($profile_id > 0) {
                $stmt = $conn->prepare('SELECT ' . implode(', ', $cols) . ' FROM borrower_profiles WHERE id = ? LIMIT 1');
                if ($stmt) {
                    $stmt->bind_param('i', $profile_id);
                    $stmt->execute();
                    $rs = $stmt->get_result();
                    $row = ($rs && ($r = $rs->fetch_assoc())) ? $r : null;
                    $stmt->close();
                    if ($row) {
                        return $row;
                    }
                }
            }
            if ($email === '') {
                return null;
            }
            $stmt = $conn->prepare('SELECT ' . implode(', ', $cols) . ' FROM borrower_profiles WHERE LOWER(TRIM(email)) = ? LIMIT 1');
            if (!$stmt) {
                return null;
            }
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $rs = $stmt->get_result();
            $row = ($rs && ($r = $rs->fetch_assoc())) ? $r : null;
            $stmt->close();

            return $row;
        };

        $profile_id = (int) ($request['borrower_profile_id'] ?? 0);
        $email = strtolower(trim((string) ($request['email'] ?? '')));
        $row = $fetch_row($profile_id, $email);
        if (!$row) {
            return $empty;
        }

        return [
            'front' => trim((string) ($row['id_document_path'] ?? '')),
            'back' => trim((string) ($row['id_document_back_path'] ?? '')),
        ];
    }
}

if (!function_exists('bpis_borrower_valid_id_path_for_request')) {
    function bpis_borrower_valid_id_path_for_request(mysqli $conn, array $request): string
    {
        $paths = bpis_borrower_valid_id_paths_for_request($conn, $request);

        return $paths['front'];
    }
}

if (!function_exists('bpis_render_valid_id_links')) {
    function bpis_render_valid_id_links(string $front_path, string $back_path, string $url_prefix = '../'): string
    {
        $parts = [];
        foreach (['front' => $front_path, 'back' => $back_path] as $label => $path) {
            $path = trim($path);
            if ($path === '') {
                continue;
            }
            $url = bpis_upload_public_url($path, $url_prefix);
            if ($url === '') {
                continue;
            }
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $is_image = in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true);
            $title = $label === 'front' ? 'ID front' : 'ID back';
            $caption = $label === 'front' ? 'Front' : 'Back';
            if ($is_image) {
                $parts[] = '<a href="' . htmlspecialchars($url, ENT_QUOTES) . '" target="_blank" rel="noopener" class="bpis-valid-id-link" title="' . htmlspecialchars($title, ENT_QUOTES) . '">'
                    . '<span class="bpis-valid-id-caption">' . htmlspecialchars($caption) . '</span>'
                    . '<img src="' . htmlspecialchars($url, ENT_QUOTES) . '" alt="' . htmlspecialchars($title, ENT_QUOTES) . '" class="bpis-valid-id-thumb">'
                    . '</a>';
            } else {
                $parts[] = '<a href="' . htmlspecialchars($url, ENT_QUOTES) . '" target="_blank" rel="noopener" class="bpis-valid-id-link" title="' . htmlspecialchars($title, ENT_QUOTES) . '">'
                    . htmlspecialchars($caption . ' (PDF)') . '</a>';
            }
        }

        if ($parts === []) {
            return '<span style="color:#94a3b8;font-size:12px;">Not uploaded</span>';
        }

        return '<div class="bpis-valid-id-stack">' . implode('', $parts) . '</div>';
    }
}

if (!function_exists('bpis_upsert_borrower_profile')) {
    function bpis_upsert_borrower_profile(
        mysqli $conn,
        string $full_name,
        string $email,
        string $purok = '',
        string $contact = '',
        string $id_document_path = '',
        string $id_document_back_path = ''
    ): int {
        if (!bpis_table_exists($conn, 'borrower_profiles') || $email === '') {
            return 0;
        }
        $email = strtolower(trim($email));
        $full_name = trim($full_name);
        $id_document_path = trim($id_document_path);
        $id_document_back_path = trim($id_document_back_path);
        $has_front_col = bpis_column_exists($conn, 'borrower_profiles', 'id_document_path');
        $has_back_col = bpis_column_exists($conn, 'borrower_profiles', 'id_document_back_path');

        $stmt = $conn->prepare('SELECT id FROM borrower_profiles WHERE email = ? LIMIT 1');
        if (!$stmt) {
            return 0;
        }
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $stmt->bind_result($pid);
        $found = $stmt->fetch() ? (int) $pid : 0;
        $stmt->close();

        if ($found > 0) {
            $set = ['full_name = ?', 'purok = ?', 'contact_number = ?'];
            $types = 'sss';
            $values = [$full_name, $purok, $contact];
            if ($has_front_col && $id_document_path !== '') {
                $set[] = 'id_document_path = ?';
                $types .= 's';
                $values[] = $id_document_path;
            }
            if ($has_back_col && $id_document_back_path !== '') {
                $set[] = 'id_document_back_path = ?';
                $types .= 's';
                $values[] = $id_document_back_path;
            }
            $types .= 'i';
            $values[] = $found;
            $upd = $conn->prepare('UPDATE borrower_profiles SET ' . implode(', ', $set) . ' WHERE id = ?');
            if ($upd) {
                $upd->bind_param($types, ...$values);
                $upd->execute();
                $upd->close();
            }

            return $found;
        }

        $insert_cols = ['full_name', 'email', 'contact_number', 'purok'];
        $insert_vals = [$full_name, $email, $contact, $purok];
        $insert_types = 'ssss';
        if ($has_front_col) {
            $insert_cols[] = 'id_document_path';
            $insert_vals[] = $id_document_path;
            $insert_types .= 's';
        }
        if ($has_back_col) {
            $insert_cols[] = 'id_document_back_path';
            $insert_vals[] = $id_document_back_path;
            $insert_types .= 's';
        }
        $insert_cols[] = 'vetting_status';
        $insert_vals[] = 'Pending';
        $insert_types .= 's';

        $placeholders = implode(', ', array_fill(0, count($insert_cols), '?'));
        $ins = $conn->prepare(
            'INSERT INTO borrower_profiles (' . implode(', ', $insert_cols) . ') VALUES (' . $placeholders . ')'
        );
        if (!$ins) {
            return 0;
        }
        $ins->bind_param($insert_types, ...$insert_vals);
        $ins->execute();
        $new_id = (int) $conn->insert_id;
        $ins->close();

        return $new_id;
    }
}

if (!function_exists('bpis_notify_all_admins')) {
    function bpis_notify_all_admins($conn, $title, $message, $link = null, $relationship = null, $borrowing_request_id = null, $borrower_id = null) {
        $admins = mysqli_query($conn, "SELECT id FROM admin");
        if (!$admins) {
            return;
        }
        $stmt = $conn->prepare("INSERT INTO notifications (admin_id, title, message, relationship, link, borrowing_request_id, borrower_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
        if (!$stmt) {
            return;
        }
        $rel = ($relationship !== null && $relationship !== '') ? (string)$relationship : '';
        $br_req = ($borrowing_request_id !== null && (int)$borrowing_request_id > 0) ? (int)$borrowing_request_id : null;
        $br_bor = ($borrower_id !== null && (int)$borrower_id > 0) ? (int)$borrower_id : null;
        while ($row = mysqli_fetch_assoc($admins)) {
            $admin_id = (int)$row['id'];
            $stmt->bind_param("issssii", $admin_id, $title, $message, $rel, $link, $br_req, $br_bor);
            $stmt->execute();
        }
        $stmt->close();
    }
}

if (!function_exists('bpis_find_asset_id_by_description')) {
    function bpis_find_asset_id_by_description($conn, $description) {
        $d = trim((string)$description);
        if ($d === '') {
            return 0;
        }
        $stmt = mysqli_prepare($conn, "SELECT id FROM asset WHERE description = ? LIMIT 1");
        if (!$stmt) {
            return 0;
        }
        mysqli_stmt_bind_param($stmt, "s", $d);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $id);
        $found = mysqli_stmt_fetch($stmt) ? (int)$id : 0;
        mysqli_stmt_close($stmt);
        if ($found > 0 && !bpis_asset_id_is_borrowable($conn, $found)) {
            return 0;
        }
        return $found;
    }
}

if (!function_exists('bpis_insert_borrowing_request_row')) {
  
    function bpis_insert_borrowing_request_row(
        $conn,
        $full_name,
        $email,
        $contact,
        $purok,
        $item_label,
        $qty,
        $purpose,
        $date_needed,
        $return_date,
        $asset_id = 0,
        $borrower_profile_id = 0,
        $batch_id = ''
    ) {
        $existing_columns = [];
        $cols_result = mysqli_query($conn, "SHOW COLUMNS FROM borrowing_requests");
        if ($cols_result) {
            while ($col = mysqli_fetch_assoc($cols_result)) {
                $existing_columns[$col['Field']] = true;
            }
        }

        $pick = function (array $candidates) use ($existing_columns) {
            foreach ($candidates as $candidate) {
                if (isset($existing_columns[$candidate])) {
                    return $candidate;
                }
            }
            return null;
        };

        $col_full_name = $pick(['full_name']);
        $col_email = $pick(['email']);
        $col_contact = $pick(['contact_number', 'contact']);
        $col_address = $pick(['purok', 'address']);
        $item_cols = bpis_borrow_request_item_columns_for_schema($existing_columns);
        $col_asset_id = $pick(['asset_id']);
        $col_qty = $pick(['quantity', 'qty']);
        $col_purpose = $pick(['purpose']);
        $col_date_need = $pick(['date_needed', 'needed_on']);
        $col_return = $pick(['return_date']);
        $col_status = $pick(['status']);

        if (!$col_full_name || !$col_contact || !$col_address || !$col_qty || !$col_purpose || !$col_date_need || !$col_return || !$col_status) {
            return 0;
        }
        if (empty($item_cols) && !$col_asset_id) {
            return 0;
        }
        $has_text = trim((string)$item_label) !== '';
        $has_asset = $col_asset_id && (int)$asset_id > 0;
        if (!$has_text && !$has_asset) {
            return 0;
        }

        $insert_columns = [$col_full_name];
        $insert_values = [$full_name];
        $insert_types = 's';

        if ($col_email) {
            $insert_columns[] = $col_email;
            $insert_values[] = $email;
            $insert_types .= 's';
        }

        $insert_columns[] = $col_contact;
        $insert_values[] = $contact;
        $insert_types .= 's';

        $insert_columns[] = $col_address;
        $insert_values[] = $purok;
        $insert_types .= 's';

        foreach ($item_cols as $ic) {
            $insert_columns[] = $ic;
            $insert_values[] = $item_label;
            $insert_types .= 's';
        }

        if ($col_asset_id && (int)$asset_id > 0) {
            $insert_columns[] = $col_asset_id;
            $insert_values[] = (int)$asset_id;
            $insert_types .= 'i';
        }

        $insert_columns[] = $col_qty;
        $insert_values[] = $qty;
        $insert_types .= 'i';

        $insert_columns[] = $col_purpose;
        $insert_values[] = $purpose;
        $insert_types .= 's';

        $insert_columns[] = $col_date_need;
        $insert_values[] = $date_needed;
        $insert_types .= 's';

        $insert_columns[] = $col_return;
        $insert_values[] = $return_date;
        $insert_types .= 's';

        $insert_columns[] = $col_status;
        $insert_values[] = 'Pending';
        $insert_types .= 's';

        if (isset($existing_columns['vetting_status'])) {
            $insert_columns[] = 'vetting_status';
            $insert_values[] = 'Pending';
            $insert_types .= 's';
        }
        if (isset($existing_columns['borrower_profile_id']) && (int) $borrower_profile_id > 0) {
            $insert_columns[] = 'borrower_profile_id';
            $insert_values[] = (int) $borrower_profile_id;
            $insert_types .= 'i';
        }
        if (isset($existing_columns['batch_id']) && trim((string) $batch_id) !== '') {
            $insert_columns[] = 'batch_id';
            $insert_values[] = trim((string) $batch_id);
            $insert_types .= 's';
        }

        $placeholders = implode(', ', array_fill(0, count($insert_columns), '?'));
        $sql = 'INSERT INTO borrowing_requests (' . implode(', ', $insert_columns) . ') VALUES (' . $placeholders . ')';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return 0;
        }

        $bind_params = [$insert_types];
        foreach ($insert_values as $key => $value) {
            $bind_params[] = &$insert_values[$key];
        }
        call_user_func_array([$stmt, 'bind_param'], $bind_params);

        if (!mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            return 0;
        }
        $new_id = (int)mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);
        return $new_id;
    }
}
