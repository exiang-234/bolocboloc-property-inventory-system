<?php

function bpis_get_unread_notification_count($conn, $admin_id) {
    $admin_id = (int) $admin_id;
    if ($admin_id <= 0) {
        return 0;
    }
    
    $table_check = mysqli_query($conn, "SHOW TABLES LIKE 'notifications'");
    if (mysqli_num_rows($table_check) == 0) {
        return 0;
    }
    
    $query = "SELECT COUNT(*) as count FROM notifications WHERE admin_id = $admin_id AND is_read = 0";
    $result = mysqli_query($conn, $query);
    if ($result && $row = mysqli_fetch_assoc($result)) {
        return (int) $row['count'];
    }
    return 0;
}

function bpis_get_notifications($conn, $admin_id, $limit = 50) {
    $admin_id = (int) $admin_id;
    if ($admin_id <= 0) {
        return [];
    }
    
    $limit = (int) $limit;
    $query = "SELECT * FROM notifications WHERE admin_id = $admin_id ORDER BY created_at DESC LIMIT $limit";
    $result = mysqli_query($conn, $query);
    
    $notifications = [];
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $notifications[] = $row;
        }
    }
    return $notifications;
}

function bpis_mark_notification_read($conn, $notification_id, $admin_id) {
    $notification_id = (int) $notification_id;
    $admin_id = (int) $admin_id;
    
    if ($notification_id <= 0 || $admin_id <= 0) {
        return false;
    }
    
    $query = "UPDATE notifications SET is_read = 1 WHERE id = $notification_id AND admin_id = $admin_id";
    return mysqli_query($conn, $query);
}

function bpis_mark_all_notifications_read($conn, $admin_id) {
    $admin_id = (int) $admin_id;
    if ($admin_id <= 0) {
        return false;
    }
    
    $query = "UPDATE notifications SET is_read = 1 WHERE admin_id = $admin_id AND is_read = 0";
    return mysqli_query($conn, $query);
}

if (!function_exists('bpis_notify_admins_by_roles')) {
    function bpis_notify_admins_by_roles(
        mysqli $conn,
        array $role_names,
        string $title,
        string $message,
        ?string $link = null,
        ?string $relationship = null,
        $borrowing_request_id = null,
        $borrower_id = null
    ): void {
        $uniq = [];
        foreach ($role_names as $r) {
            $r = trim((string) $r);
            if ($r !== '') {
                $uniq[$r] = true;
            }
        }

        if (isset($uniq['Secretary']) || isset($uniq['Treasurer'])) {
            $uniq['Barangay Captain'] = true;
        }

        $role_list = array_keys($uniq);
        if ($role_list === []) {
            return;
        }

        $ph = implode(',', array_fill(0, count($role_list), '?'));
        $types = str_repeat('s', count($role_list));
        $sql = "SELECT id FROM admin WHERE role IN ($ph)";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return;
        }
        $stmt->bind_param($types, ...$role_list);
        $stmt->execute();
        $admin_ids = [];
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $aid = 0;
            $stmt->bind_result($aid);
            while ($stmt->fetch()) {
                if ((int) $aid > 0) {
                    $admin_ids[] = (int) $aid;
                }
            }
        }
        $stmt->close();

        if ($admin_ids === []) {
            return;
        }

        $rel = ($relationship !== null && $relationship !== '') ? (string) $relationship : '';
        $br_req_sql = ($borrowing_request_id !== null && (int) $borrowing_request_id > 0)
            ? (string) (int) $borrowing_request_id
            : 'NULL';
        $br_bor_sql = ($borrower_id !== null && (int) $borrower_id > 0)
            ? (string) (int) $borrower_id
            : 'NULL';

        $et = mysqli_real_escape_string($conn, $title);
        $em = mysqli_real_escape_string($conn, $message);
        $er = mysqli_real_escape_string($conn, $rel);
        $el = mysqli_real_escape_string($conn, (string) ($link ?? ''));

        foreach ($admin_ids as $admin_id) {
            if ($admin_id <= 0) {
                continue;
            }
            $sql = "INSERT INTO notifications (admin_id, title, message, relationship, link, borrowing_request_id, borrower_id) VALUES ($admin_id, '$et', '$em', '$er', '$el', $br_req_sql, $br_bor_sql)";
            mysqli_query($conn, $sql);
        }
    }
}

if (!function_exists('bpis_mirror_staff_notifications_to_captains')) {
    function bpis_mirror_staff_notifications_to_captains(mysqli $conn): void
    {
        mysqli_query($conn, "
            INSERT INTO notifications (
                admin_id,
                title,
                message,
                relationship,
                borrowing_request_id,
                borrower_id,
                link,
                is_read,
                created_at
            )
            SELECT
                captains.id,
                staff_notifications.title,
                staff_notifications.message,
                staff_notifications.relationship,
                staff_notifications.borrowing_request_id,
                staff_notifications.borrower_id,
                staff_notifications.link,
                0,
                staff_notifications.created_at
            FROM admin captains
            JOIN (
                SELECT
                    n.title,
                    n.message,
                    n.relationship,
                    n.borrowing_request_id,
                    n.borrower_id,
                    n.link,
                    n.created_at
                FROM notifications n
                JOIN admin staff_admin ON staff_admin.id = n.admin_id
                WHERE staff_admin.role IN ('Secretary', 'Treasurer')
                GROUP BY
                    n.title,
                    n.message,
                    COALESCE(n.relationship, ''),
                    COALESCE(n.borrowing_request_id, 0),
                    COALESCE(n.borrower_id, 0),
                    COALESCE(n.link, ''),
                    n.created_at
            ) staff_notifications
            WHERE captains.role = 'Barangay Captain'
              AND NOT EXISTS (
                  SELECT 1
                  FROM notifications existing
                  WHERE existing.admin_id = captains.id
                    AND existing.title = staff_notifications.title
                    AND existing.message = staff_notifications.message
                    AND COALESCE(existing.relationship, '') = COALESCE(staff_notifications.relationship, '')
                    AND COALESCE(existing.borrowing_request_id, 0) = COALESCE(staff_notifications.borrowing_request_id, 0)
                    AND COALESCE(existing.borrower_id, 0) = COALESCE(staff_notifications.borrower_id, 0)
                    AND COALESCE(existing.link, '') = COALESCE(staff_notifications.link, '')
                    AND existing.created_at = staff_notifications.created_at
              )
        ");
    }
}

if (!function_exists('bpis_asset_stock_snapshot')) {
    function bpis_asset_stock_snapshot(mysqli $conn, int $asset_id): array
    {
        if ($asset_id <= 0) {
            return ['label' => 'Item', 'available' => 0, 'quantity' => 0, 'borrowed' => 0, 'has_borrowed_qty' => false];
        }
        $aid = (int) $asset_id;
        $rs = mysqli_query($conn, "SELECT * FROM asset WHERE id = $aid LIMIT 1");
        $row = $rs ? mysqli_fetch_assoc($rs) : null;
        if (!$row) {
            return ['label' => 'Item', 'available' => 0, 'quantity' => 0, 'borrowed' => 0, 'has_borrowed_qty' => false];
        }
        $hasBorrowed = array_key_exists('borrowed_qty', $row);
        require_once __DIR__ . '/asset_units_helpers.php';
        $stats = bpis_asset_unit_availability_stats($conn, $row);
        $qty = $stats['total'];
        $borrowed = $stats['borrowed'];
        $available = $stats['available'];
        $label = trim((string) ($row['description'] ?? ''));
        if ($label === '') {
            $label = trim((string) ($row['article'] ?? 'Item'));
        }
        if ($label === '') {
            $label = 'Item';
        }

        return [
            'label' => $label,
            'available' => $available,
            'quantity' => $qty,
            'borrowed' => $borrowed,
            'has_borrowed_qty' => $hasBorrowed,
        ];
    }
}