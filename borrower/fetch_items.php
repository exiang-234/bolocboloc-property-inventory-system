<?php

require_once '../config/database.php';
require_once '../config/asset_borrowable_helpers.php';

function bpis_borrower_asset_name(array $row): string
{
    $desc = trim((string) ($row['description'] ?? ''));
    $article = trim((string) ($row['article'] ?? ''));
    if ($desc !== '') {
        return $desc;
    }
    if ($article !== '') {
        return $article;
    }

    return 'Asset #' . (int) ($row['id'] ?? 0);
}

function bpis_fetch_avail_qty(array $row, $conn = null): int
{
    if ($conn instanceof mysqli) {
        return bpis_asset_borrowable_available_qty($conn, $row);
    }

    $qty = isset($row['quantity']) ? (int) $row['quantity'] : 0;
    if ($qty <= 0) {
        $numeric_only = preg_replace('/[^0-9]/', '', (string) ($row['article'] ?? ''));
        $qty = !empty($numeric_only) ? (int) $numeric_only : 0;
    }
    $borrowed = isset($row['borrowed_qty']) ? (int) $row['borrowed_qty'] : 0;
    if ($borrowed < 0) {
        $borrowed = 0;
    }

    return max(0, $qty - $borrowed);
}

function bpis_attr_esc(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

header('Content-Type: text/html; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit;
}

$search = isset($_POST['query']) ? trim((string) $_POST['query']) : '';

try {
    if (!isset($conn)) {
        throw new Exception('Database connection not available.');
    }

    $borrowable = bpis_sql_asset_borrowable_where();
    $categoryFilter = bpis_sql_asset_borrowable_category_clause($conn);
    $orderBy = 'ORDER BY id DESC';

    if ($search === '') {
        $sqlBrowse = "SELECT * FROM asset WHERE 1=1 $borrowable $categoryFilter $orderBy LIMIT 100";
        $result = mysqli_query($conn, $sqlBrowse);
        if (!$result) {
            throw new Exception(mysqli_error($conn));
        }
    } else {
        $like = '%' . mysqli_real_escape_string($conn, $search) . '%';
        $searchParts = [
            "description LIKE '$like'",
            "article LIKE '$like'",
            "category LIKE '$like'",
            "property_number LIKE '$like'",
        ];
        if (function_exists('bpis_column_exists')) {
            if (bpis_column_exists($conn, 'asset', 'brand')) {
                $searchParts[] = "brand LIKE '$like'";
            }
            if (bpis_column_exists($conn, 'asset', 'model')) {
                $searchParts[] = "model LIKE '$like'";
            }
        }
        $sql = 'SELECT * FROM asset
                WHERE (' . implode(' OR ', $searchParts) . ")
                $borrowable
                $categoryFilter
                $orderBy
                LIMIT 100";
        $result = mysqli_query($conn, $sql);
        if (!$result) {
            throw new Exception(mysqli_error($conn));
        }
    }

    $asset_ids = [];
    $rows = [];
    while ($row = mysqli_fetch_assoc($result)) {
        if (!bpis_asset_row_is_borrowable($row)) {
            continue;
        }
        $rows[] = $row;
        $asset_ids[] = (int) ($row['id'] ?? 0);
    }

    if ($rows === []) {
        $allowed = bpis_borrowable_categories_label();
        $msg = $search === ''
            ? 'No borrowable items available. Allowed categories: ' . $allowed . '.'
            : 'No borrowable items found. Allowed categories: ' . $allowed . '.';
        echo '<div class="borrow-loading">' . htmlspecialchars($msg) . '</div>';
        exit;
    }

    $photo_map = bpis_get_asset_photo_map($conn, $asset_ids);

    echo '<div class="borrow-product-grid" role="list">';

    foreach ($rows as $row) {
        $assetName = bpis_borrower_asset_name($row);
        $category = (string) ($row['category'] ?? '');
        $id = (int) ($row['id'] ?? 0);
        $availQty = bpis_fetch_avail_qty($row, $conn);
        if ($availQty < 1) {
            continue;
        }
        $availLabel = (int) $availQty . ' avail.';
        $statusLabel = bpis_asset_remarks_status_label($row);
        $statusClass = $statusLabel === 'UNSERVICEABLE'
            ? 'borrow-product-badge--unserviceable'
            : 'borrow-product-badge--serviceable';

        echo '<article class="borrow-product-card result-item" role="button" tabindex="0"'
            . ' data-value="' . bpis_attr_esc($assetName) . '"'
            . ' data-max-qty="' . (int) $availQty . '"'
            . ' data-asset-id="' . $id . '"'
            . ' aria-label="' . bpis_attr_esc($assetName) . ', ' . bpis_attr_esc($statusLabel) . ', ' . bpis_attr_esc($availLabel) . '">';
        echo '<div class="borrow-product-media">';
        echo bpis_asset_photo_img($conn, $id, $assetName, 'borrow-product-img', $photo_map);
        echo '<span class="borrow-product-badge ' . $statusClass . '">'
            . htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8') . '</span>';
        echo '</div>';
        echo '<div class="borrow-product-body">';
        echo '<h3 class="borrow-product-title">' . htmlspecialchars($assetName, ENT_QUOTES, 'UTF-8') . '</h3>';
        echo '<p class="borrow-product-avail">' . htmlspecialchars($availLabel, ENT_QUOTES, 'UTF-8') . '</p>';
        if ($category !== '') {
            echo '<p class="borrow-product-category">' . htmlspecialchars($category, ENT_QUOTES, 'UTF-8') . '</p>';
        }
        echo '</div></article>';
    }

    echo '</div>';
} catch (Exception $e) {
    echo '<div class="borrow-loading" style="color:#b91c1c;">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
}
