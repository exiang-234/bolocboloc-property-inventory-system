<?php
require_once __DIR__ . '/../config/bpis_session.php';
bpis_start_session();
include '../config/database.php';
require_once __DIR__ . '/../config/auth_helpers.php';
require_once __DIR__ . '/../config/borrower_list_helpers.php';
require_once __DIR__ . '/../config/notification_helpers.php';
bpis_require_login(['Secretary', 'Barangay Captain']);

$bpis_secretary_nav_active = 'blocklist';

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['unblock_id'])) {
    $unblock_id = (int) $_POST['unblock_id'];
    if ($unblock_id > 0 && bpis_table_exists($conn, 'borrower_blocklist')) {
        $stmt = $conn->prepare('DELETE FROM borrower_blocklist WHERE id = ?');
        if ($stmt) {
            $stmt->bind_param('i', $unblock_id);
            $stmt->execute();
            $stmt->close();
            $msg = 'Borrower has been unblocked and removed from the blocklist.';
            
            bpis_notify_admins_by_roles(
                $conn,
                ['Barangay Captain', 'Treasurer'],
                'Borrower unblocked',
                'A borrower has been removed from the blocklist.',
                'secretary/blocklist.php',
                'blocklist_unblock_' . time(),
                null,
                null
            );
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['block_name'], $_POST['reason'])) {
    $name = trim((string) $_POST['block_name']);
    $email = strtolower(trim((string) ($_POST['block_email'] ?? '')));
    $contact = trim((string) ($_POST['block_contact'] ?? ''));
    $reason = trim((string) $_POST['reason']);
    $by = (int) ($_SESSION['admin_id'] ?? 0);
    if ($name !== '' && $reason !== '' && bpis_table_exists($conn, 'borrower_blocklist')) {
        if (bpis_borrower_is_blocked($conn, $name, $email)) {
            $msg = 'This borrower is already on the blocklist.';
        } else {
            $stmt = $conn->prepare('INSERT INTO borrower_blocklist (full_name, email, contact_number, reason, blocked_by) VALUES (?,?,?,?,?)');
            if ($stmt) {
                $stmt->bind_param('ssssi', $name, $email, $contact, $reason, $by);
                $stmt->execute();
                $stmt->close();
                $msg = 'Borrower added to blocklist.';
                
                bpis_notify_admins_by_roles(
                    $conn,
                    ['Barangay Captain', 'Treasurer'],
                    'New borrower blocked',
                    'A borrower "' . htmlspecialchars($name) . '" has been added to the blocklist. Reason: ' . htmlspecialchars($reason),
                    'secretary/blocklist.php',
                    'blocklist_new_' . time(),
                    null,
                    null
                );
            }
        }
    }
}

$list = [];
if (bpis_table_exists($conn, 'borrower_blocklist')) {
    $rs = mysqli_query($conn, 'SELECT * FROM borrower_blocklist ORDER BY blocked_at DESC');
    if ($rs) {
        while ($row = mysqli_fetch_assoc($rs)) {
            $list[] = $row;
        }
    }
}

$all_borrowers = bpis_list_all_borrowers($conn);
$filtered_borrowers = array_filter($all_borrowers, function($borrower) {
    return empty($borrower['is_blocked']);
});
$borrower_count = count($filtered_borrowers);

$current_view = $_COOKIE['blocklist_view'] ?? 'grid';

$bpis_page_title = 'Borrower Blocklist';
$bpis_page_heading = 'Borrower Blocklist';
$bpis_page_subtitle = 'Browse all borrowers, search live, and block those who do not return items.';
$bpis_skip_content_panel = true;
$bpis_extra_head = '<link rel="stylesheet" href="../css/stat_cards.css"><link rel="stylesheet" href="../css/table_date_filter.css">
<style>
    .view-toggle {
        display: flex;
        gap: 10px;
        align-items: center;
    }
    .view-toggle button {
        padding: 8px 16px;
        background: #dc2626;
        color: white;
        border: 1px solid #dc2626;
        border-radius: 6px;
        cursor: pointer;
        font-size: 13px;
        font-weight: 500;
        transition: all 0.2s;
    }
    .view-toggle button.active {
        background: #174C7D;
        color: white;
        border-color: #174C7D;
    }
    .view-toggle button:hover:not(.active) {
        background: #b91c1c;
        border-color: #b91c1c;
    }

    .borrower-list-view {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }
    .borrower-list-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 15px;
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        transition: all 0.2s;
        flex-wrap: wrap;
    }
    .borrower-list-item:hover {
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    .borrower-list-info {
        flex: 1;
        display: flex;
        align-items: center;
        gap: 15px;
        min-width: 0;
    }
    .borrower-list-details {
        flex: 1;
        min-width: 0;
    }
    .borrower-list-name {
        font-weight: 600;
        font-size: 16px;
        margin-bottom: 5px;
    }
    .borrower-list-meta {
        font-size: 12px;
        color: #64748b;
        word-break: break-word;
    }
    .borrower-list-actions {
        display: flex;
        gap: 10px;
        flex-shrink: 0;
    }
    .btn-unblock {
        background: #10b981;
        color: white;
        padding: 6px 12px;
        border-radius: 6px;
        border: none;
        cursor: pointer;
        font-size: 12px;
        font-weight: 500;
    }
    .btn-unblock:hover {
        background: #059669;
    }
    .btn-block {
        background: #dc2626;
        color: white;
        padding: 6px 12px;
        border-radius: 6px;
        border: none;
        cursor: pointer;
        font-size: 12px;
        font-weight: 500;
    }
    .btn-block:hover:not(:disabled) {
        background: #b91c1c;
    }
    .btn-block:disabled {
        background: #9ca3af;
        cursor: not-allowed;
    }
    
    .borrower-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 20px;
    }
    .borrower-card.is-hidden {
        display: none;
    }
    
    .unblock-modal-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.5);
        z-index: 9999;
        align-items: center;
        justify-content: center;
    }
    .unblock-modal {
        background: white;
        border-radius: 12px;
        padding: 24px;
        max-width: 400px;
        width: 90%;
        box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1);
        margin: 16px;
    }
    .unblock-modal h3 {
        margin-bottom: 16px;
        color: #174C7D;
    }
    .unblock-modal-buttons {
        display: flex;
        gap: 12px;
        justify-content: flex-end;
        margin-top: 20px;
    }
    .unblock-modal-buttons button {
        padding: 8px 20px;
        border-radius: 6px;
        border: none;
        cursor: pointer;
        font-weight: 500;
        font-size: 14px;
    }
    .unblock-modal-buttons .btn-cancel {
        background: #9ca3af;
        color: white;
    }
    .unblock-modal-buttons .btn-confirm {
        background: #10b981;
        color: white;
    }
    .flash-banner {
        padding: 12px;
        border-radius: 8px;
        margin-bottom: 20px;
        font-size: 14px;
    }
    .flash-banner.success {
        background: #d1fae5;
        color: #065f46;
        border: 1px solid #a7f3d0;
    }
    .flash-banner.error {
        background: #fee2e2;
        color: #991b1b;
        border: 1px solid #fecaca;
    }

    .blocklist-directory-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 20px;
        margin-bottom: 20px;
        flex-wrap: wrap;
    }
    .blocklist-directory-header > div:first-child {
        flex: 1;
        min-width: 200px;
    }
    .blocklist-directory-header > div:last-child {
        display: flex;
        gap: 15px;
        align-items: center;
        flex-wrap: wrap;
    }
    .blocklist-search-wrap {
        min-width: 200px;
    }
    .blocklist-search-wrap .search-container {
        position: relative;
        display: flex;
        align-items: center;
        width: 100%;
    }
    .blocklist-search-wrap .search-icon {
        position: absolute;
        left: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #94a3b8;
        pointer-events: none;
        width: 18px;
        height: 18px;
    }
    .blocklist-search-wrap .search-icon svg {
        width: 18px;
        height: 18px;
        stroke: #94a3b8;
    }
    .blocklist-search-wrap input {
        width: 100%;
        padding: 10px 12px 10px 42px;
        border: 1.5px solid #e2e8f0;
        border-radius: 10px;
        font-size: 14px;
        background: #ffffff;
        transition: all 0.2s ease;
        color: #0f172a;
    }
    .blocklist-search-wrap input::placeholder {
        color: #94a3b8;
        font-weight: 400;
        letter-spacing: 0.3px;
    }
    .blocklist-search-wrap input:focus {
        outline: none;
        border-color: #174C7D;
        box-shadow: 0 0 0 3px rgba(23, 76, 125, 0.1);
    }
    .blocklist-no-match {
        display: none;
        padding: 20px;
        text-align: center;
        color: #64748b;
        font-style: italic;
    }
    .blocklist-no-match.visible {
        display: block;
    }
    .blocklist-section-title {
        font-size: 18px;
        font-weight: 700;
        color: #174C7D;
        margin: 40px 0 16px 0;
        display: block;
        clear: both;
    }
    .table-section {
        background: white;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        margin-bottom: 20px;
        overflow-x: auto;
    }
    .space-y-3 > * + * {
        margin-top: 12px;
    }
    .borrower-avatar {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        object-fit: cover;
        flex-shrink: 0;
    }
    .borrower-card {
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 16px;
        text-align: center;
        transition: all 0.2s;
    }
    .borrower-card:hover {
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        transform: translateY(-2px);
    }
    .borrower-card .borrower-avatar {
        width: 64px;
        height: 64px;
        margin: 0 auto 12px;
    }
    .borrower-card-body {
        display: flex;
        flex-direction: column;
        align-items: center;
    }
    .borrower-card-name {
        font-weight: 600;
        font-size: 15px;
        margin-bottom: 4px;
    }
    .borrower-card-meta {
        font-size: 12px;
        color: #64748b;
        word-break: break-word;
        width: 100%;
        text-align: center;
    }
    .borrower-card-badges {
        margin: 8px 0;
    }
    .borrower-card .btn-block-prefill {
        background: #dc2626;
        color: white;
        padding: 6px 16px;
        border-radius: 6px;
        border: none;
        cursor: pointer;
        font-size: 12px;
        font-weight: 500;
        transition: background 0.2s;
        margin-top: 8px;
    }
    .borrower-card .btn-block-prefill:hover {
        background: #b91c1c;
    }

    @media (max-width: 1024px) {
        .borrower-grid {
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 16px;
        }
        .borrower-card {
            padding: 14px;
        }
        .borrower-card .borrower-avatar {
            width: 56px;
            height: 56px;
        }
        .blocklist-search-wrap {
            min-width: 180px;
        }
        .view-toggle button {
            padding: 6px 14px;
            font-size: 12px;
        }
        .table-section {
            padding: 16px;
        }
        .blocklist-section-title {
            font-size: 17px;
        }
    }

    @media (max-width: 768px) {
        .blocklist-directory-header {
            flex-direction: column;
            align-items: stretch;
        }
        .blocklist-directory-header > div:last-child {
            flex-direction: column;
            align-items: stretch;
        }
        .blocklist-search-wrap {
            min-width: unset;
        }
        .blocklist-search-wrap .search-container {
            width: 100%;
        }
        .blocklist-search-wrap input {
            padding: 8px 10px 8px 36px;
            font-size: 13px;
            border-radius: 8px;
        }
        .blocklist-search-wrap .search-icon {
            left: 10px;
            width: 16px;
            height: 16px;
        }
        .blocklist-search-wrap .search-icon svg {
            width: 16px;
            height: 16px;
        }
        .view-toggle {
            justify-content: center;
        }
        .view-toggle button {
            flex: 1;
            text-align: center;
            padding: 8px 12px;
            font-size: 12px;
        }
        .borrower-grid {
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 12px;
        }
        .borrower-list-item {
            flex-direction: column;
            align-items: stretch;
            gap: 10px;
        }
        .borrower-list-info {
            flex-direction: column;
            align-items: center;
            text-align: center;
        }
        .borrower-list-actions {
            justify-content: center;
        }
        .borrower-list-name {
            font-size: 14px;
        }
        .borrower-list-meta {
            font-size: 11px;
        }
        .table-section {
            padding: 12px;
            overflow-x: auto;
        }
        .secretary-data-table {
            font-size: 12px;
            min-width: 600px;
        }
        .secretary-data-table th,
        .secretary-data-table td {
            padding: 8px 6px;
        }
        .borrower-card .borrower-avatar {
            width: 48px;
            height: 48px;
        }
        .borrower-card-name {
            font-size: 14px;
        }
        .blocklist-section-title {
            font-size: 16px;
            margin: 24px 0 12px;
        }
        .unblock-modal {
            padding: 16px;
            margin: 12px;
            width: 95%;
        }
        .unblock-modal h3 {
            font-size: 16px;
        }
        .unblock-modal-buttons {
            flex-direction: column;
        }
        .unblock-modal-buttons button {
            width: 100%;
            padding: 10px;
        }
        .flash-banner {
            font-size: 13px;
            padding: 10px;
        }
    }

    @media (max-width: 600px) {
        .borrower-grid {
            grid-template-columns: repeat(auto-fill, minmax(170px, 1fr));
            gap: 10px;
        }
        .borrower-card {
            padding: 12px;
            border-radius: 10px;
        }
        .borrower-card .borrower-avatar {
            width: 42px;
            height: 42px;
            margin-bottom: 8px;
        }
        .borrower-card-name {
            font-size: 13px;
        }
        .borrower-card-meta {
            font-size: 11px;
        }
        .borrower-card .btn-block-prefill {
            font-size: 11px;
            padding: 4px 12px;
        }
        .blocklist-directory-header h3 {
            font-size: 14px;
        }
        .blocklist-directory-header p {
            font-size: 11px;
        }
        .blocklist-search-wrap input {
            font-size: 13px;
            padding: 6px 10px 6px 34px;
        }
        .blocklist-search-wrap .search-icon {
            left: 8px;
            width: 16px;
            height: 16px;
        }
        .blocklist-search-wrap .search-icon svg {
            width: 16px;
            height: 16px;
        }
        .view-toggle button {
            font-size: 11px;
            padding: 6px 10px;
        }
        .blocklist-section-title {
            font-size: 15px;
            margin: 20px 0 10px;
        }
    }

    @media (max-width: 480px) {
        .borrower-grid {
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }
        .borrower-list-item {
            padding: 12px;
        }
        .borrower-list-info {
            gap: 10px;
        }
        .borrower-avatar {
            width: 36px;
            height: 36px;
        }
        .borrower-card .borrower-avatar {
            width: 40px;
            height: 40px;
        }
        .borrower-card-body .btn-block-prefill {
            font-size: 11px;
            padding: 4px 12px;
        }
        .view-toggle button {
            font-size: 11px;
            padding: 6px 10px;
        }
        .flash-banner {
            font-size: 13px;
            padding: 10px;
        }
        .blocklist-section-title {
            font-size: 14px;
            margin: 16px 0 8px;
        }
        .secretary-data-table {
            font-size: 11px;
            min-width: 500px;
        }
        .secretary-data-table th,
        .secretary-data-table td {
            padding: 6px 4px;
        }
        .unblock-modal {
            padding: 14px;
            margin: 8px;
        }
        .unblock-modal h3 {
            font-size: 15px;
        }
        .unblock-modal-buttons button {
            font-size: 13px;
            padding: 8px 12px;
        }
        .borrower-list-name {
            font-size: 13px;
        }
        .borrower-list-meta {
            font-size: 10px;
        }
        .blocklist-search-wrap input {
            font-size: 12px;
            padding: 5px 8px 5px 32px;
        }
        .blocklist-search-wrap .search-icon {
            left: 6px;
            width: 14px;
            height: 14px;
        }
        .blocklist-search-wrap .search-icon svg {
            width: 14px;
            height: 14px;
        }
    }

    @media (max-width: 400px) {
        .borrower-grid {
            grid-template-columns: 1fr;
            gap: 8px;
        }
        .borrower-card {
            padding: 10px;
            border-radius: 8px;
            display: flex;
            flex-direction: row;
            text-align: left;
            align-items: center;
        }
        .borrower-card-body {
            align-items: flex-start;
            text-align: left;
        }
        .borrower-card .borrower-avatar {
            width: 36px;
            height: 36px;
            margin: 0 10px 0 0;
            flex-shrink: 0;
        }
        .borrower-card-name {
            font-size: 12px;
        }
        .borrower-card-meta {
            font-size: 10px;
        }
        .borrower-card .btn-block-prefill {
            font-size: 10px;
            padding: 3px 10px;
            margin-top: 4px;
        }
        .borrower-card-badges {
            margin: 4px 0;
        }
        .borrower-list-item {
            padding: 10px;
        }
        .borrower-list-actions button {
            font-size: 11px;
            padding: 4px 10px;
        }
        .blocklist-section-title {
            font-size: 13px;
            margin: 14px 0 6px;
        }
        .view-toggle button {
            font-size: 10px;
            padding: 5px 8px;
        }
        .blocklist-search-wrap input {
            padding: 5px 8px 5px 30px;
            font-size: 11px;
            border-radius: 6px;
        }
        .blocklist-search-wrap .search-icon {
            left: 6px;
            width: 12px;
            height: 12px;
        }
        .blocklist-search-wrap .search-icon svg {
            width: 12px;
            height: 12px;
        }
    }

    @media (max-width: 360px) {
        .borrower-grid {
            grid-template-columns: 1fr;
            gap: 6px;
        }
        .borrower-card {
            padding: 8px;
            border-radius: 6px;
        }
        .borrower-card .borrower-avatar {
            width: 30px;
            height: 30px;
            margin-right: 8px;
        }
        .borrower-card-name {
            font-size: 11px;
        }
        .borrower-card-meta {
            font-size: 9px;
        }
        .borrower-card .btn-block-prefill {
            font-size: 9px;
            padding: 2px 8px;
        }
        .borrower-list-item {
            padding: 8px;
        }
        .borrower-list-actions button {
            font-size: 10px;
            padding: 3px 8px;
        }
        .blocklist-directory-header h3 {
            font-size: 13px;
        }
        .blocklist-directory-header p {
            font-size: 10px;
        }
        .blocklist-search-wrap input {
            font-size: 10px;
            padding: 4px 6px 4px 28px;
            border-radius: 4px;
        }
        .blocklist-search-wrap .search-icon {
            left: 5px;
            width: 11px;
            height: 11px;
        }
        .blocklist-search-wrap .search-icon svg {
            width: 11px;
            height: 11px;
        }
        .view-toggle button {
            font-size: 9px;
            padding: 4px 6px;
        }
        .flash-banner {
            font-size: 12px;
            padding: 8px;
        }
        .blocklist-section-title {
            font-size: 12px;
        }
    }
</style>';
include __DIR__ . '/../includes/secretary_layout_start.php';
?>
            <?php if ($msg): ?>
                <div class="flash-banner <?= str_contains($msg, 'already') || str_contains($msg, 'unblocked') ? 'success' : 'success' ?>">
                    <?= htmlspecialchars($msg) ?>
                </div>
            <?php endif; ?>

            <section class="table-section blocklist-directory">
                <div class="blocklist-directory-header">
                    <div>
                        <h3>All borrowers</h3>
                        <p><?= (int) $borrower_count ?> registered — search by name, email, contact, or purok</p>
                    </div>
                    <div>
                        <div class="blocklist-search-wrap">
                            <div class="search-container">
                                <span class="search-icon">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                    </svg>
                                </span>
                                <input type="search" id="borrowerLiveSearch" placeholder="Live search borrowers…" autocomplete="off" aria-label="Search borrowers">
                            </div>
                        </div>
                        <div class="view-toggle">
                            <button id="gridViewBtn" class="<?= $current_view === 'grid' ? 'active' : '' ?>">Grid View</button>
                            <button id="listViewBtn" class="<?= $current_view === 'list' ? 'active' : '' ?>">List View</button>
                        </div>
                    </div>
                </div>

                <div id="gridViewContainer" class="borrower-grid" style="display: <?= $current_view === 'grid' ? 'grid' : 'none' ?>;">
                    <?php foreach ($filtered_borrowers as $b):
                        $avatar = bpis_borrower_avatar_url($b['full_name'], $b['photo_path'] ?: null);
                        $search_blob = strtolower(implode(' ', [
                            $b['full_name'],
                            $b['email'],
                            $b['contact_number'],
                            $b['purok'],
                        ]));
                        ?>
                        <article
                            class="borrower-card"
                            data-search="<?= htmlspecialchars($search_blob, ENT_QUOTES, 'UTF-8') ?>"
                            data-borrower-name="<?= htmlspecialchars($b['full_name']) ?>"
                            data-borrower-email="<?= htmlspecialchars($b['email']) ?>"
                            data-borrower-contact="<?= htmlspecialchars($b['contact_number']) ?>"
                        >
                            <img src="<?= htmlspecialchars($avatar) ?>" alt="" class="borrower-avatar" loading="lazy"
                                 onerror="this.onerror=null;this.src='https://ui-avatars.com/api/?name=<?= rawurlencode($b['full_name']) ?>&background=174C7D&color=fff&size=128';">
                            <div class="borrower-card-body">
                                <p class="borrower-card-name"><?= htmlspecialchars($b['full_name']) ?></p>
                                <?php if ($b['email'] !== ''): ?>
                                    <p class="borrower-card-meta"><?= htmlspecialchars($b['email']) ?></p>
                                <?php endif; ?>
                                <?php if ($b['contact_number'] !== ''): ?>
                                    <p class="borrower-card-meta"><?= htmlspecialchars($b['contact_number']) ?></p>
                                <?php endif; ?>
                                <?php if ($b['purok'] !== ''): ?>
                                    <p class="borrower-card-meta">Purok <?= htmlspecialchars($b['purok']) ?></p>
                                <?php endif; ?>
                                <div class="borrower-card-badges">
                                </div>
                                <button type="button" class="btn-block-prefill"
                                    data-name="<?= htmlspecialchars($b['full_name'], ENT_QUOTES, 'UTF-8') ?>"
                                    data-email="<?= htmlspecialchars($b['email'], ENT_QUOTES, 'UTF-8') ?>"
                                    data-contact="<?= htmlspecialchars($b['contact_number'], ENT_QUOTES, 'UTF-8') ?>">
                                    Block this borrower
                                </button>
                            </div>
                        </article>
                    <?php endforeach; ?>
                    <?php if (!$filtered_borrowers): ?>
                        <p class="blocklist-no-match visible" style="grid-column:1/-1;">No borrowers in the system yet.</p>
                    <?php endif; ?>
                </div>

                <div id="listViewContainer" class="borrower-list-view" style="display: <?= $current_view === 'list' ? 'flex' : 'none' ?>;">
                    <?php foreach ($filtered_borrowers as $b):
                        $avatar = bpis_borrower_avatar_url($b['full_name'], $b['photo_path'] ?: null);
                        $search_blob = strtolower(implode(' ', [
                            $b['full_name'],
                            $b['email'],
                            $b['contact_number'],
                            $b['purok'],
                        ]));
                        ?>
                        <div class="borrower-list-item"
                             data-search="<?= htmlspecialchars($search_blob, ENT_QUOTES, 'UTF-8') ?>">
                            <div class="borrower-list-info">
                                <img src="<?= htmlspecialchars($avatar) ?>" alt="" class="borrower-avatar" style="width: 48px; height: 48px; border-radius: 50%; object-fit: cover;"
                                     onerror="this.onerror=null;this.src='https://ui-avatars.com/api/?name=<?= rawurlencode($b['full_name']) ?>&background=174C7D&color=fff&size=128';">
                                <div class="borrower-list-details">
                                    <div class="borrower-list-name">
                                        <?= htmlspecialchars($b['full_name']) ?>
                                    </div>
                                    <div class="borrower-list-meta">
                                        <?php if ($b['email'] !== ''): ?>
                                            <?= htmlspecialchars($b['email']) ?> •
                                        <?php endif; ?>
                                        <?php if ($b['contact_number'] !== ''): ?>
                                            <?= htmlspecialchars($b['contact_number']) ?> •
                                        <?php endif; ?>
                                        <?php if ($b['purok'] !== ''): ?>
                                            Purok <?= htmlspecialchars($b['purok']) ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="borrower-list-actions">
                                <button type="button" class="btn-block-prefill"
                                    data-name="<?= htmlspecialchars($b['full_name'], ENT_QUOTES, 'UTF-8') ?>"
                                    data-email="<?= htmlspecialchars($b['email'], ENT_QUOTES, 'UTF-8') ?>"
                                    data-contact="<?= htmlspecialchars($b['contact_number'], ENT_QUOTES, 'UTF-8') ?>">
                                    Block
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (!$filtered_borrowers): ?>
                        <p class="blocklist-no-match visible" style="text-align:center;">No borrowers in the system yet.</p>
                    <?php endif; ?>
                </div>
                <p class="blocklist-no-match" id="borrowerNoMatch">No borrowers match your search.</p>
            </section>

            <form method="post" id="blockBorrowerForm" class="table-section space-y-3">
                <h3 class="blocklist-section-title">Block a borrower</h3>
                <div>
                    <label class="block text-[13px] font-semibold text-gray-700 mb-1.5">Full name <span class="text-red-500">*</span></label>
                    <input name="block_name" id="block_name" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" required>
                </div>
                <div>
                    <label class="block text-[13px] font-semibold text-gray-700 mb-1.5">Email</label>
                    <input type="email" name="block_email" id="block_email" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-[13px] font-semibold text-gray-700 mb-1.5">Contact</label>
                    <input name="block_contact" id="block_contact" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-[13px] font-semibold text-gray-700 mb-1.5">Reason <span class="text-red-500">*</span></label>
                    <textarea name="reason" id="block_reason" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" rows="2" required placeholder="e.g. Did not return borrowed items by due date"></textarea>
                </div>
                <button type="submit" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg font-semibold text-sm">Add to blocklist</button>
            </form>

            <div class="table-section overflow-x-auto">
                <h3 class="blocklist-section-title">Active blocklist</h3>
                <table class="secretary-data-table w-full text-sm">
                    <thead>
                        <tr>
                            <th style="width:56px;"></th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Contact</th>
                            <th>Reason</th>
                            <th>Blocked</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="blocklistTableBody">
                    <?php foreach ($list as $b):
                        $avatar = bpis_borrower_avatar_url((string) $b['full_name'], null);
                        ?>
                        <tr class="blocklist-row" data-search="<?= htmlspecialchars(strtolower(implode(' ', [$b['full_name'], $b['email'] ?? '', $b['contact_number'] ?? '', $b['reason'] ?? ''])), ENT_QUOTES, 'UTF-8') ?>">
                            <td>
                                <img src="<?= htmlspecialchars($avatar) ?>" alt="" class="borrower-avatar" style="width:40px;height:40px;" loading="lazy"
                                     onerror="this.onerror=null;this.src='https://ui-avatars.com/api/?name=<?= rawurlencode($b['full_name']) ?>&background=991b1b&color=fff&size=80';">
                            </td>
                            <td style="text-align:left;font-weight:600;"><?= htmlspecialchars($b['full_name']) ?></td>
                            <td><?= htmlspecialchars($b['email'] ?? '') ?></td>
                            <td><?= htmlspecialchars($b['contact_number'] ?? '') ?></td>
                            <td style="text-align:left;"><?= htmlspecialchars($b['reason']) ?></td>
                            <td class="text-gray-500"><?= htmlspecialchars($b['blocked_at'] ?? '') ?></td>
                            <td>
                                <form method="post" style="margin: 0;" class="unblock-form">
                                    <input type="hidden" name="unblock_id" value="<?= (int) $b['id'] ?>">
                                    <button type="button" class="btn-unblock" style="padding: 4px 12px; font-size: 11px;">Unblock</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$list): ?>
                        <tr id="blocklistEmptyRow"><td colspan="7" style="padding:32px;color:#888;font-style:italic;">No blocked borrowers yet.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
                <p class="blocklist-no-match" id="blocklistNoMatch">No blocklist entries match your search.</p>
            </div>

<div id="unblockModal" class="unblock-modal-overlay">
    <div class="unblock-modal">
        <h3>Confirm Unblock</h3>
        <p>Are you sure you want to unblock this borrower? They will be able to borrow items again.</p>
        <div class="unblock-modal-buttons">
            <button type="button" class="btn-cancel">Cancel</button>
            <button type="button" class="btn-confirm">Confirm Unblock</button>
        </div>
    </div>
</div>

<?php
$bpis_layout_footer_scripts = <<<'HTML'
<script>
(function () {
    const searchInput = document.getElementById('borrowerLiveSearch');
    const gridCards = document.querySelectorAll('#gridViewContainer .borrower-card');
    const listItems = document.querySelectorAll('#listViewContainer .borrower-list-item');
    const noMatch = document.getElementById('borrowerNoMatch');
    const blocklistRows = document.querySelectorAll('#blocklistTableBody .blocklist-row');
    const blocklistNoMatch = document.getElementById('blocklistNoMatch');
    const form = document.getElementById('blockBorrowerForm');
    const gridViewBtn = document.getElementById('gridViewBtn');
    const listViewBtn = document.getElementById('listViewBtn');
    const gridContainer = document.getElementById('gridViewContainer');
    const listContainer = document.getElementById('listViewContainer');
    let pendingUnblockForm = null;

    function setView(view) {
        if (view === 'grid') {
            gridContainer.style.display = 'grid';
            listContainer.style.display = 'none';
            gridViewBtn.classList.add('active');
            listViewBtn.classList.remove('active');
            document.cookie = "blocklist_view=grid; path=/; max-age=31536000";
        } else {
            gridContainer.style.display = 'none';
            listContainer.style.display = 'flex';
            listViewBtn.classList.add('active');
            gridViewBtn.classList.remove('active');
            document.cookie = "blocklist_view=list; path=/; max-age=31536000";
        }

        if (searchInput) {
            runSearch();
        }
    }

    if (gridViewBtn && listViewBtn) {
        gridViewBtn.addEventListener('click', () => setView('grid'));
        listViewBtn.addEventListener('click', () => setView('list'));
    }

    function runSearch() {
        const q = (searchInput && searchInput.value ? searchInput.value : '').trim().toLowerCase();
        
        let cardVisible = 0;
        gridCards.forEach(function (el) {
            const blob = (el.getAttribute('data-search') || '').toLowerCase();
            const show = q === '' || blob.indexOf(q) !== -1;
            el.classList.toggle('is-hidden', !show);
            if (show) cardVisible++;
        });
        
        let listVisible = 0;
        listItems.forEach(function (el) {
            const blob = (el.getAttribute('data-search') || '').toLowerCase();
            const show = q === '' || blob.indexOf(q) !== -1;
            el.style.display = show ? '' : 'none';
            if (show) listVisible++;
        });
        
        const totalVisible = (gridContainer.style.display === 'grid' ? cardVisible : listVisible);
        if (noMatch) {
            noMatch.classList.toggle('visible', q !== '' && totalVisible === 0 && (gridCards.length > 0 || listItems.length > 0));
        }
        
        let rowVisible = 0;
        blocklistRows.forEach(function (el) {
            const blob = (el.getAttribute('data-search') || '').toLowerCase();
            const show = q === '' || blob.indexOf(q) !== -1;
            el.style.display = show ? '' : 'none';
            if (show) rowVisible++;
        });
        if (blocklistNoMatch) {
            blocklistNoMatch.classList.toggle('visible', q !== '' && rowVisible === 0 && blocklistRows.length > 0);
        }
    }

    if (searchInput) {
        searchInput.addEventListener('input', runSearch);
    }

    const unblockModal = document.getElementById('unblockModal');
    const confirmUnblockBtn = document.querySelector('#unblockModal .btn-confirm');
    const cancelUnblockBtn = document.querySelector('#unblockModal .btn-cancel');

    function showUnblockModal(formElement) {
        pendingUnblockForm = formElement;
        if (unblockModal) {
            unblockModal.style.display = 'flex';
        }
    }

    if (confirmUnblockBtn) {
        confirmUnblockBtn.addEventListener('click', function() {
            if (pendingUnblockForm) {
                pendingUnblockForm.submit();
            }
            if (unblockModal) {
                unblockModal.style.display = 'none';
            }
            pendingUnblockForm = null;
        });
    }

    if (cancelUnblockBtn) {
        cancelUnblockBtn.addEventListener('click', function() {
            if (unblockModal) {
                unblockModal.style.display = 'none';
            }
            pendingUnblockForm = null;
        });
    }

    if (unblockModal) {
        unblockModal.addEventListener('click', function(e) {
            if (e.target === unblockModal) {
                unblockModal.style.display = 'none';
                pendingUnblockForm = null;
            }
        });
    }

    document.querySelectorAll('.btn-block-prefill').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (btn.disabled || !form) {
                return;
            }
            document.getElementById('block_name').value = btn.getAttribute('data-name') || '';
            document.getElementById('block_email').value = btn.getAttribute('data-email') || '';
            document.getElementById('block_contact').value = btn.getAttribute('data-contact') || '';
            const reason = document.getElementById('block_reason');
            if (reason && !reason.value.trim()) {
                reason.value = 'Did not return borrowed item(s) by the agreed return date.';
            }
            form.scrollIntoView({ behavior: 'smooth', block: 'start' });
            document.getElementById('block_name').focus();
        });
    });

    document.querySelectorAll('.btn-unblock').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const formElement = this.closest('form');
            if (formElement) {
                showUnblockModal(formElement);
            } else {
                const newForm = document.createElement('form');
                newForm.method = 'POST';
                newForm.style.display = 'none';
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'unblock_id';
                input.value = btn.getAttribute('data-borrower-id');
                newForm.appendChild(input);
                document.body.appendChild(newForm);
                showUnblockModal(newForm);
            }
        });
    });
})();
</script>
HTML;
include __DIR__ . '/../includes/secretary_layout_end.php';
?>