<?php
/**
 * Asset inventory photos: asset.photo_path + asset_units.photo_path fallback.
 */

require_once __DIR__ . '/auth_helpers.php';

require_once __DIR__ . '/schema_bootstrap.php';

if (!function_exists('bpis_ensure_asset_quantity_column')) {
    function bpis_ensure_asset_quantity_column(mysqli $conn): void
    {
        if (!$conn) {
            return;
        }
        bpis_add_column_if_missing($conn, 'asset', 'quantity', 'INT NOT NULL DEFAULT 1 AFTER `article`');
    }
}

if (!function_exists('bpis_ensure_asset_photo_column')) {
    function bpis_ensure_asset_photo_column(mysqli $conn): void
    {
        if (!$conn) {
            return;
        }
        bpis_add_column_if_missing($conn, 'asset', 'photo_path', 'VARCHAR(500) NULL DEFAULT NULL');

        static $backfilled = false;
        if ($backfilled || !bpis_table_exists($conn, 'asset_units') || !bpis_column_exists($conn, 'asset', 'photo_path')) {
            return;
        }
        $backfilled = true;
        mysqli_query(
            $conn,
            "UPDATE asset a
             INNER JOIN (
                 SELECT asset_id, MIN(id) AS unit_id
                 FROM asset_units
                 WHERE photo_path IS NOT NULL AND photo_path <> ''
                 GROUP BY asset_id
             ) pick ON pick.asset_id = a.id
             INNER JOIN asset_units u ON u.id = pick.unit_id
             SET a.photo_path = u.photo_path
             WHERE a.photo_path IS NULL OR a.photo_path = ''"
        );
    }
}

if (!function_exists('bpis_drop_asset_photo_column')) {
    /** @deprecated Kept for compatibility; no longer drops the column. */
    function bpis_drop_asset_photo_column($conn): void
    {
        if ($conn) {
            bpis_ensure_asset_photo_column($conn);
        }
    }
}

if (!function_exists('bpis_public_upload_url')) {
    function bpis_public_upload_url(?string $relative_path): ?string
    {
        if ($relative_path === null || trim($relative_path) === '') {
            return null;
        }
        $relative_path = str_replace('\\', '/', trim($relative_path));
        if (preg_match('#^https?://#i', $relative_path)) {
            return $relative_path;
        }
        if ($relative_path[0] === '/') {
            return $relative_path;
        }
        return bpis_url($relative_path);
    }
}

if (!function_exists('bpis_asset_placeholder_image_url')) {
    function bpis_asset_placeholder_image_url(): string
    {
        return bpis_url('images/logo.png');
    }
}

if (!function_exists('bpis_get_asset_photo_map')) {
    /** @return array<int,string> asset_id => relative photo path */
    function bpis_get_asset_photo_map(mysqli $conn, array $asset_ids): array
    {
        $asset_ids = array_values(array_unique(array_filter(array_map('intval', $asset_ids))));
        if (!$asset_ids) {
            return [];
        }

        $map = [];
        $id_list = implode(',', $asset_ids);

        if (bpis_column_exists($conn, 'asset', 'photo_path')) {
            $rs = mysqli_query($conn, "SELECT id, photo_path FROM asset WHERE id IN ({$id_list})");
            if ($rs) {
                while ($row = mysqli_fetch_assoc($rs)) {
                    $path = trim((string) ($row['photo_path'] ?? ''));
                    if ($path !== '') {
                        $map[(int) $row['id']] = $path;
                    }
                }
            }
        }

        $missing = array_values(array_diff($asset_ids, array_keys($map)));
        if ($missing && bpis_table_exists($conn, 'asset_units')) {
            $missing_list = implode(',', array_map('intval', $missing));
            $rs = mysqli_query(
                $conn,
                "SELECT asset_id, photo_path FROM asset_units
                 WHERE asset_id IN ({$missing_list}) AND photo_path IS NOT NULL AND photo_path <> ''
                 ORDER BY id ASC"
            );
            if ($rs) {
                while ($row = mysqli_fetch_assoc($rs)) {
                    $aid = (int) ($row['asset_id'] ?? 0);
                    if ($aid > 0 && !isset($map[$aid])) {
                        $map[$aid] = trim((string) $row['photo_path']);
                    }
                }
            }
        }

        return $map;
    }
}

if (!function_exists('bpis_asset_photo_url')) {
    function bpis_asset_photo_url(mysqli $conn, int $asset_id, ?array $photo_map = null): string
    {
        if ($photo_map === null) {
            $photo_map = bpis_get_asset_photo_map($conn, [$asset_id]);
        }
        $public = bpis_public_upload_url($photo_map[$asset_id] ?? null);
        return $public ?: bpis_asset_placeholder_image_url();
    }
}

if (!function_exists('bpis_asset_photo_img')) {
    function bpis_asset_photo_img(
        mysqli $conn,
        int $asset_id,
        string $alt = 'Asset',
        string $class = 'bpis-asset-thumb',
        ?array $photo_map = null
    ): string {
        $url = bpis_asset_photo_url($conn, $asset_id, $photo_map);
        return '<img src="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" alt="'
            . htmlspecialchars($alt, ENT_QUOTES, 'UTF-8') . '" class="'
            . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '" loading="lazy">';
    }
}

if (!function_exists('bpis_sync_asset_photo_from_units')) {
    function bpis_sync_asset_photo_from_units(mysqli $conn, int $asset_id): void
    {
        if ($asset_id <= 0 || !bpis_column_exists($conn, 'asset', 'photo_path') || !bpis_table_exists($conn, 'asset_units')) {
            return;
        }

        $stmt = $conn->prepare('SELECT photo_path FROM asset WHERE id = ? LIMIT 1');
        if (!$stmt) {
            return;
        }
        $stmt->bind_param('i', $asset_id);
        $stmt->execute();
        $rs = $stmt->get_result();
        $row = $rs ? $rs->fetch_assoc() : null;
        $stmt->close();

        if ($row && trim((string) ($row['photo_path'] ?? '')) !== '') {
            return;
        }

        $unit_rs = mysqli_query(
            $conn,
            "SELECT photo_path FROM asset_units WHERE asset_id = {$asset_id} AND photo_path IS NOT NULL AND photo_path <> '' ORDER BY id ASC LIMIT 1"
        );
        if (!$unit_rs || !($unit = mysqli_fetch_assoc($unit_rs))) {
            return;
        }

        $photo = trim((string) ($unit['photo_path'] ?? ''));
        if ($photo === '') {
            return;
        }

        $upd = $conn->prepare('UPDATE asset SET photo_path = ? WHERE id = ?');
        if ($upd) {
            $upd->bind_param('si', $photo, $asset_id);
            $upd->execute();
            $upd->close();
        }
    }
}
