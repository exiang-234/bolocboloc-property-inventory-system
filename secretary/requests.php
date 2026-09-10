<?php
require_once __DIR__ . '/../config/bpis_session.php';
bpis_start_session();
include '../config/database.php';
require_once __DIR__ . '/../config/auth_helpers.php';
require_once __DIR__ . '/../config/borrow_request_display_helpers.php';
require_once __DIR__ . '/../config/asset_borrowable_helpers.php';
require_once __DIR__ . '/../borrower/submit_request_helpers.php';
require_once __DIR__ . '/../config/table_date_filter_helpers.php';
require_once __DIR__ . '/../config/email_templates.php';
require_once __DIR__ . '/../config/notification_helpers.php';
require_once __DIR__ . '/../config/borrow_request_batch_helpers.php';
require_once "../src/Exception.php";
require_once "../src/PHPMailer.php";
require_once "../src/SMTP.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function sendBorrowerEmailSMTP($to_email, $to_name, $subject, $html_message) {
    $mail_config = include "../config/mail_config.php";

    if (empty($to_email) || !filter_var($to_email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $mail_config['host'] ?? '';
        $mail->SMTPAuth = true;
        $mail->Username = $mail_config['username'] ?? '';
        $mail->Password = $mail_config['password'] ?? '';
        $mail->Port = (int)($mail_config['port'] ?? 587);
        $mail->SMTPSecure = (($mail_config['encryption'] ?? 'tls') === 'ssl')
            ? PHPMailer::ENCRYPTION_SMTPS
            : PHPMailer::ENCRYPTION_STARTTLS;

        $mail->setFrom(
            $mail_config['from_email'] ?? ($mail_config['username'] ?? 'no-reply@example.com'),
            $mail_config['from_name'] ?? 'Barangay Request Portal'
        );
        $mail->addAddress($to_email, $to_name ?: 'Borrower');
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $html_message;
        $mail->AltBody = strip_tags(str_replace(["<br>", "<br/>", "<br />"], "\n", $html_message));

        return $mail->send();
    } catch (Exception $e) {
        error_log("SMTP mail failed: " . $e->getMessage());
        return false;
    }
}


bpis_require_login(['Secretary', 'Barangay Captain']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_status'])) {
    $n_status = trim((string) $_POST['action_status']);
    $batch_key = trim((string) ($_POST['batch_id'] ?? ''));

    if ($batch_key === '' && isset($_POST['request_id'])) {
        $r_id = (int) $_POST['request_id'];
        $row_rs = mysqli_query($conn, "SELECT * FROM borrowing_requests WHERE id = {$r_id} LIMIT 1");
        if ($row_rs && ($one = mysqli_fetch_assoc($row_rs))) {
            $batch_key = bpis_request_batch_key($one);
        }
    }

    if ($batch_key === '') {
        header('Location: requests.php?error=not_found');
        exit();
    }

    if ($n_status === 'Approved') {
        $result = bpis_approve_borrowing_batch($conn, $batch_key);
        if (!$result['ok']) {
            $err = $result['error'] ?? 'unknown';
            header('Location: requests.php?error=' . urlencode((string) $err));
            exit();
        }
        $borrower_email = (string) ($result['borrower_email'] ?? '');
        $borrower_name = (string) ($result['borrower_name'] ?? 'Borrower');
        $subject = 'Borrowing Request Approved - OTP Code';
        $message = bpis_otp_approval_email_html(
            $borrower_name,
            (string) ($result['otp'] ?? ''),
            (string) ($result['item_summary'] ?? '')
        );
    } elseif ($n_status === 'Rejected') {
        $result = bpis_reject_borrowing_batch($conn, $batch_key);
        if (!$result['ok']) {
            header('Location: requests.php?error=not_found');
            exit();
        }
        $borrower_email = (string) ($result['borrower_email'] ?? '');
        $borrower_name = (string) ($result['borrower_name'] ?? 'Borrower');
        $subject = 'Borrowing Request Update';
        $message = bpis_request_rejected_email_html($borrower_name);
    } else {
        header('Location: requests.php');
        exit();
    }

    if ($borrower_email !== '') {
        if (!sendBorrowerEmailSMTP($borrower_email, $borrower_name, $subject, $message)) {
            error_log('Mail failed to send to ' . $borrower_email);
        }
    }

    header('Location: requests.php?status=updated');
    exit();
}


$req_stats_query = "SELECT 
    COUNT(CASE WHEN status = 'Approved' THEN 1 END) as approved_total
    FROM borrowing_requests";
$req_stats_result = mysqli_query($conn, $req_stats_query);
$req_stats = mysqli_fetch_assoc($req_stats_result);
$total_pending = bpis_count_pending_request_batches($conn);

$borrower_stats_query = "SELECT 
    COUNT(*) as total_borrowers,
    COUNT(CASE WHEN status = 'Received' THEN 1 END) as received_total,
    COUNT(CASE WHEN status = 'Received' AND return_date < CURRENT_DATE THEN 1 END) as overdue_total
    FROM borrower";
$borrower_stats_result = mysqli_query($conn, $borrower_stats_query);
$borrower_stats = mysqli_fetch_assoc($borrower_stats_result);

$total_requests  = $req_stats['approved_total'] ?? 0;
$total_borrowers = $borrower_stats['total_borrowers'] ?? 0;
$total_overdue   = $borrower_stats['overdue_total'] ?? 0;

$pending_batches = bpis_group_pending_borrowing_requests($conn);

$bpis_secretary_nav_active = 'requests';
$bpis_skip_content_panel = true;
$bpis_page_title = 'Borrowing Request Dashboard';
$bpis_page_heading = 'Requests';
$bpis_page_subtitle = 'List of all pending requests.';
$bpis_extra_head = '<link rel="stylesheet" href="../css/stat_cards.css"><link rel="stylesheet" href="../css/table_date_filter.css">
<style>
/* Remove large empty area below table on Requests page (flex-grow container was stretching to 100vh even with 1 row) */
.secretary-page-scroll{flex:0 1 auto !important; min-height:auto !important; height:auto !important; padding-bottom:0 !important; overflow:visible !important;}
.main-content{height:auto !important; min-height:0 !important; overflow:visible !important;}
body{height:auto !important; min-height:100vh !important; overflow:auto !important;}
.table-section{margin-bottom:0 !important;}

.bpis-valid-id-stack {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    align-items: flex-start;
}

.bpis-valid-id-link {
    display: inline-flex;
    flex-direction: column;
    align-items: center;
    gap: 4px;
    font-size: 11px;
    font-weight: 600;
    color: #2563eb;
    text-decoration: none;
    cursor: pointer;
}

.bpis-valid-id-link:hover {
    text-decoration: underline;
}

.bpis-valid-id-caption {
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #64748b;
}

.bpis-valid-id-thumb {
    width: 44px;
    height: 44px;
    object-fit: cover;
    border-radius: 6px;
    border: 1px solid #e2e8f0;
    display: block;
    cursor: pointer;
}

.bpis-batch-items {
    margin: 0;
    padding: 0;
    list-style: none;
    font-size: 12px;
    line-height: 1.45;
}

.bpis-batch-items li {
    padding: 2px 0;
    border-bottom: 1px dashed #e2e8f0;
}

.bpis-batch-items li:last-child {
    border-bottom: none;
}

.bpis-batch-meta {
    font-size: 11px;
    color: #64748b;
    font-weight: 600;
}

.bpis-image-modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.85);
    z-index: 9999;
    justify-content: center;
    align-items: center;
    cursor: pointer;
}

.bpis-image-modal.active {
    display: flex;
}

.bpis-image-modal-content {
    max-width: 90%;
    max-height: 90%;
    object-fit: contain;
    border-radius: 8px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.5);
}

.bpis-image-modal-close {
    position: fixed;
    top: 20px;
    right: 30px;
    font-size: 40px;
    font-weight: bold;
    color: #ffffff;
    cursor: pointer;
    z-index: 10000;
    transition: 0.3s;
}

.bpis-image-modal-close:hover {
    color: #ccc;
    transform: scale(1.1);
}

.logout-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    z-index: 9999;
    justify-content: center;
    align-items: center;
}

.logout-modal {
    background-color: #ffffff;
    padding: 30px;
    border-radius: 8px;
    max-width: 400px;
    width: 90%;
    text-align: center;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.logout-modal h2 {
    margin-top: 0;
    margin-bottom: 15px;
    text-align: center;
    font-size: 20px;
    color: #333;
}

.logout-modal p {
    margin-bottom: 20px;
    text-align: center;
    font-size: 16px;
    color: #555;
    line-height: 1.5;
}

.logout-buttons {
    display: flex;
    gap: 10px;
    justify-content: center;
}

.btn-confirm,
.btn-cancel {
    padding: 10px 24px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 600;
    transition: all 0.3s;
}

.btn-confirm {
    background-color: #2563eb;
    color: #ffffff;
}

.btn-confirm:hover {
    background-color: #1d4ed8;
}

.btn-cancel {
    background-color: #e5e7eb;
    color: #333;
}

.btn-cancel:hover {
    background-color: #d1d5db;
}

.requests-table-scroll {
    width: 100%;
    overflow: auto;
    -webkit-overflow-scrolling: touch;
}

.requests-table-scroll .secretary-data-table {
    min-width: 980px;
}

.requests-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    margin-bottom: 14px;
}

.requests-toolbar .bpis-date-filter-bar {
    flex: 1 1 auto;
    margin-bottom: 0;
}

.requests-date-controls {
    display: flex;
    align-items: center;
    gap: 12px 18px;
    flex-wrap: wrap;
}

.requests-search {
    flex: 0 1 320px;
    justify-content: flex-end;
}

.requests-search input {
    width: 100%;
    min-width: 240px;
}

@media (max-width: 1024px) {
    .requests-toolbar {
        align-items: stretch;
        flex-direction: column;
    }
    .requests-search {
        flex: 1 1 auto;
        width: 100%;
    }
    .requests-search input {
        min-width: 0;
        width: 100%;
    }
    .bpis-valid-id-thumb {
        width: 38px;
        height: 38px;
        border-radius: 5px;
    }
    .bpis-valid-id-caption {
        font-size: 9px;
    }
    .bpis-valid-id-link {
        font-size: 10px;
    }
    .logout-modal {
        padding: 24px;
        max-width: 360px;
    }
    .logout-modal h2 {
        font-size: 18px;
        margin-bottom: 12px;
    }
    .logout-modal p {
        font-size: 14px;
    }
    .btn-confirm,
    .btn-cancel {
        padding: 8px 18px;
        font-size: 13px;
    }
}

@media (max-width: 768px) {
    .requests-date-controls {
        width: 100%;
    }
    .requests-table-scroll {
        max-height: calc(100vh - 310px);
        max-height: calc(100dvh - 310px);
        min-height: 260px;
        overscroll-behavior: contain;
    }
    .requests-table-scroll .secretary-data-table thead th {
        position: sticky;
        top: 0;
        z-index: 3;
        background: #f8fafc;
    }
    .bpis-valid-id-stack {
        gap: 6px;
    }
    .bpis-valid-id-thumb {
        width: 32px;
        height: 32px;
        border-radius: 4px;
    }
    .bpis-valid-id-caption {
        font-size: 8px;
    }
    .bpis-valid-id-link {
        font-size: 9px;
        gap: 3px;
    }
    .bpis-batch-items {
        font-size: 11px;
    }
    .bpis-batch-meta {
        font-size: 10px;
    }
    .bpis-image-modal-content {
        max-width: 95%;
        max-height: 85%;
        border-radius: 6px;
    }
    .bpis-image-modal-close {
        top: 16px;
        right: 20px;
        font-size: 32px;
    }
    .logout-overlay {
        padding: 16px;
    }
    .logout-modal {
        padding: 20px;
        max-width: 320px;
        border-radius: 6px;
    }
    .logout-modal h2 {
        font-size: 17px;
        margin-bottom: 10px;
    }
    .logout-modal p {
        font-size: 13px;
        margin-bottom: 16px;
    }
    .logout-buttons {
        flex-direction: column-reverse;
        gap: 8px;
    }
    .btn-confirm,
    .btn-cancel {
        padding: 8px 16px;
        font-size: 13px;
        width: 100%;
        border-radius: 4px;
    }
}

@media (max-width: 480px) {
    .requests-toolbar {
        gap: 10px;
        margin-bottom: 10px;
    }
    .requests-table-scroll {
        max-height: calc(100vh - 280px);
        max-height: calc(100dvh - 280px);
        min-height: 240px;
    }
    .bpis-valid-id-stack {
        gap: 4px;
    }
    .bpis-valid-id-thumb {
        width: 26px;
        height: 26px;
        border-radius: 3px;
        border-width: 1px;
    }
    .bpis-valid-id-caption {
        font-size: 7px;
        letter-spacing: 0.03em;
    }
    .bpis-valid-id-link {
        font-size: 8px;
        gap: 2px;
    }
    .bpis-batch-items {
        font-size: 10px;
    }
    .bpis-batch-meta {
        font-size: 9px;
    }
    .bpis-batch-items li {
        padding: 1px 0;
    }
    .bpis-image-modal-content {
        max-width: 98%;
        max-height: 80%;
        border-radius: 4px;
    }
    .bpis-image-modal-close {
        top: 12px;
        right: 14px;
        font-size: 28px;
    }
    .logout-modal {
        padding: 16px;
        max-width: 280px;
        border-radius: 4px;
        width: 95%;
    }
    .logout-modal h2 {
        font-size: 15px;
        margin-bottom: 8px;
    }
    .logout-modal p {
        font-size: 12px;
        margin-bottom: 14px;
        line-height: 1.4;
    }
    .logout-buttons {
        gap: 6px;
    }
    .btn-confirm,
    .btn-cancel {
        padding: 6px 12px;
        font-size: 12px;
    }
}

@media (max-width: 360px) {
    .requests-table-scroll {
        max-height: calc(100vh - 250px);
        max-height: calc(100dvh - 250px);
        min-height: 220px;
    }
    .bpis-valid-id-thumb {
        width: 20px;
        height: 20px;
        border-radius: 2px;
    }
    .bpis-valid-id-caption {
        font-size: 6px;
    }
    .bpis-valid-id-link {
        font-size: 7px;
        gap: 2px;
    }
    .bpis-batch-items {
        font-size: 9px;
    }
    .bpis-batch-meta {
        font-size: 8px;
    }
    .bpis-image-modal-close {
        top: 8px;
        right: 10px;
        font-size: 22px;
    }
    .logout-modal {
        padding: 14px;
        max-width: 250px;
    }
    .logout-modal h2 {
        font-size: 14px;
    }
    .logout-modal p {
        font-size: 11px;
    }
    .btn-confirm,
    .btn-cancel {
        padding: 5px 10px;
        font-size: 11px;
    }
}
</style>';
include __DIR__ . '/../includes/secretary_layout_start.php';
?>
        <?php if (isset($_GET['error']) && $_GET['error'] === 'insufficient_stock'): ?>
            <div style="margin-bottom: 14px; padding: 10px 12px; border-radius: 8px; background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; font-size: 13px;">
                Unable to approve request: insufficient stock available in inventory.
            </div>
        <?php elseif (isset($_GET['error']) && $_GET['error'] === 'no_asset'): ?>
            <div style="margin-bottom: 14px; padding: 10px 12px; border-radius: 8px; background: #fff7ed; border: 1px solid #fed7aa; color: #9a3412; font-size: 13px;">
                Unable to approve request: related asset was not found in inventory.
            </div>
        <?php elseif (isset($_GET['error']) && $_GET['error'] === 'not_borrowable'): ?>
            <div style="margin-bottom: 14px; padding: 10px 12px; border-radius: 8px; background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; font-size: 13px;">
                Unable to approve request: item must be serviceable and in an allowed category (<?= htmlspecialchars(bpis_borrowable_categories_label()) ?>).
            </div>
        <?php elseif (isset($_GET['error']) && $_GET['error'] === 'not_found'): ?>
            <div style="margin-bottom: 14px; padding: 10px 12px; border-radius: 8px; background: #fff7ed; border: 1px solid #fed7aa; color: #9a3412; font-size: 13px;">
                Request batch was not found or may have already been processed.
            </div>
        <?php endif; ?>

        <div class="cards-container">
            <div class="card card-blue"><p>Total Request</p><div class="card-number"><?= $total_requests ?></div></div>
            <div class="card card-yellow"><p>Total Pending</p><div class="card-number"><?= $total_pending ?></div></div>
            <div class="card card-green"><p>Total Borrowers</p><div class="card-number"><?= $total_borrowers ?></div></div>
            <div class="card card-red"><p>Overdue </p><div class="card-number"><?= $total_overdue ?></div></div>
        </div>

       <div class="table-section" data-bpis-date-filter-scope>
            <div class="content-subheader">
                 <h3 style="font-weight: bold; color:  #040275; margin:0; font-size:15px;">Pending Request/s</h3>
            </div>
            <div class="requests-toolbar">
                <div class="bpis-date-filter-bar">
                    <div class="requests-date-controls">
                        <span class="bpis-date-filter-label">Filter by date</span>
                        <label>From <input type="date" data-bpis-date-from aria-label="Filter from date"></label>
                        <label>To <input type="date" data-bpis-date-to aria-label="Filter to date"></label>
                        <button type="button" class="bpis-date-clear-btn" data-bpis-date-clear>Clear dates</button>
                    </div>
                </div>
                <div class="search-container requests-search">
                    <span class="search-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </span>
                    <input type="text" id="searchInput" placeholder="Search requests...">
                </div>
            </div>
            
    <div class="requests-table-scroll">
    <table id="requestsTable" class="secretary-data-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Borrower</th>
                <th>Address</th>
                <th>Contact</th>
                <th>Valid ID</th>
                <th>Item</th>
                <th>QTY</th>
                <th>Needed On</th>
                <th>Purpose</th>
                <th>Return Date</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($pending_batches)): ?>
                <tr id="emptyRow">
                    <td colspan="11" style="color: #888; font-size: 13px; font-style: italic; text-align: center; padding: 40px;">
                        No pending requests to display.
                    </td>
                </tr>
            <?php else: ?>
                <tr id="noMatchRow" style="display: none;">
                    <td colspan="11" style="color: #666; font-size: 15px; text-align: center; padding: 40px 0;">
                        No result found.
                    </td>
                </tr>
                <?php foreach ($pending_batches as $batch): ?>
                    <?php
                    $request = $batch['requests'][0];
                    $item_count = count($batch['item_lines']);
                    ?>
                    <tr class="data-row"<?= bpis_row_date_attr($request, ['created_at', 'date_needed', 'needed_on', 'return_date']) ?>>
                        <td class="row-id">
                            R-<?= str_pad((string) $batch['primary_id'], 4, '0', STR_PAD_LEFT) ?>
                            <?php if ($item_count > 1): ?>
                                <span class="bpis-batch-meta block">(<?= (int) $item_count ?> items)</span>
                            <?php endif; ?>
                        </td>
                        <td><span class="borrower-name"><?= htmlspecialchars($request['full_name'] ?? '') ?></span></td>
                        <td><span class="borrower-address">Purok <?= htmlspecialchars($request['address'] ?? $request['purok'] ?? '') ?></span></td>
                        <td><?= htmlspecialchars($request['contact_number'] ?? '') ?></td>
                        <td>
                            <?php
                            $valid_id_paths = bpis_borrower_valid_id_paths_for_request($conn, $request);
                            echo bpis_render_valid_id_links($valid_id_paths['front'], $valid_id_paths['back']);
                            ?>
                        </td>
                        <td>
                            <?php if ($item_count === 1): ?>
                                <?= htmlspecialchars($batch['item_lines'][0]['label']) ?>
                            <?php else: ?>
                                <ul class="bpis-batch-items">
                                    <?php foreach ($batch['item_lines'] as $line): ?>
                                        <li><?= htmlspecialchars($line['label']) ?> <strong>(<?= (int) $line['qty'] ?>)</strong></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($item_count === 1): ?>
                                <?= (int) $batch['item_lines'][0]['qty'] ?>
                            <?php else: ?>
                                <span class="bpis-batch-meta"><?= (int) $item_count ?> lines</span>
                            <?php endif; ?>
                        </td>
                        <td><?= !empty($request['date_needed'] ?? '') ? date('M d, Y', strtotime($request['date_needed'])) : 'N/A' ?></td>
                        <td><?= htmlspecialchars($request['purpose'] ?? '') ?></td>
                        <td><?= !empty($request['return_date'] ?? '') ? date('M d, Y', strtotime($request['return_date'])) : 'N/A' ?></td>
                        <td>
                            <div class="action-container">
                                <form method="POST" style="margin: 0;">
                                    <input type="hidden" name="batch_id" value="<?= htmlspecialchars($batch['batch_key'], ENT_QUOTES) ?>">
                                    <input type="hidden" name="action_status" value="Approved">
                                    <button type="submit" class="btn-approve action-btn" data-action="Approve all items">Approve</button>
                                </form>
                                <form method="POST" style="margin: 0;">
                                    <input type="hidden" name="batch_id" value="<?= htmlspecialchars($batch['batch_key'], ENT_QUOTES) ?>">
                                    <input type="hidden" name="action_status" value="Rejected">
                                    <button type="submit" class="btn-reject action-btn" data-action="Reject all items">Reject</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    </div>
        </div>

<div class="bpis-image-modal" id="imageModal">
    <span class="bpis-image-modal-close" id="imageModalClose">&times;</span>
    <img class="bpis-image-modal-content" id="modalImage" src="" alt="Valid ID">
</div>

<?php
$bpis_layout_footer_scripts = <<<'HTML'
<div class="logout-overlay" id="actionConfirmOverlay">
    <div class="logout-modal">
        <h2 id="actionConfirmTitle">Confirm Action</h2>
        <p id="actionConfirmText">Are you sure you want to proceed?</p>
        <div class="logout-buttons">
            <button type="button" class="btn-confirm" id="confirmActionBtn">Confirm</button>
            <button type="button" class="btn-cancel" id="cancelActionBtn">Cancel</button>
        </div>
    </div>
</div>
<script src="../js/table_date_filter.js"></script>
<script>
const actionConfirmOverlay = document.getElementById('actionConfirmOverlay');
const actionConfirmText = document.getElementById('actionConfirmText');
const confirmActionBtn = document.getElementById('confirmActionBtn');
const cancelActionBtn = document.getElementById('cancelActionBtn');
let pendingActionForm = null;

document.querySelectorAll('.action-btn').forEach((btn) => {
    btn.addEventListener('click', function (e) {
        e.preventDefault();
        pendingActionForm = this.closest('form');
        const actionLabel = this.dataset.action || 'process';
        actionConfirmText.textContent = 'Do you want to ' + actionLabel.toLowerCase() + ' for this request?';
        actionConfirmOverlay.style.display = 'flex';
    });
});

cancelActionBtn.addEventListener('click', function () {
    actionConfirmOverlay.style.display = 'none';
    pendingActionForm = null;
});

confirmActionBtn.addEventListener('click', function () {
    if (pendingActionForm) {
        pendingActionForm.submit();
    }
});

actionConfirmOverlay.addEventListener('click', function (e) {
    if (e.target === actionConfirmOverlay) {
        actionConfirmOverlay.style.display = 'none';
        pendingActionForm = null;
    }
});

const searchInput = document.getElementById('searchInput');
if (searchInput) {
    searchInput.addEventListener('keyup', function () {
        const filter = this.value.toLowerCase();
        const rows = document.querySelectorAll('#requestsTable tbody .data-row');
        const noMatchRow = document.getElementById('noMatchRow');
        let visibleCount = 0;
        rows.forEach((row) => {
            const textOk = row.textContent.toLowerCase().includes(filter);
            row.dataset.bpisSearchHidden = textOk ? '' : '1';
            if (typeof window.bpisSyncRowDisplay === 'function') {
                window.bpisSyncRowDisplay(row);
            } else {
                row.style.display = textOk ? '' : 'none';
            }
            if (row.style.display !== 'none') {
                visibleCount++;
            }
        });
        if (noMatchRow) {
            noMatchRow.style.display = visibleCount === 0 && filter !== '' ? '' : 'none';
        }
    });
}

const modal = document.getElementById('imageModal');
const modalImg = document.getElementById('modalImage');
const closeBtn = document.getElementById('imageModalClose');

document.addEventListener('click', function(e) {
    const target = e.target;
    if (target.classList.contains('bpis-valid-id-thumb')) {
        e.preventDefault();
        const imgSrc = target.getAttribute('src');
        if (imgSrc) {
            modalImg.setAttribute('src', imgSrc);
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
    }
});

function closeImageModal() {
    modal.classList.remove('active');
    document.body.style.overflow = '';
    modalImg.setAttribute('src', '');
}

closeBtn.addEventListener('click', function(e) {
    e.stopPropagation();
    closeImageModal();
});

modal.addEventListener('click', function(e) {
    if (e.target === modal) {
        closeImageModal();
    }
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && modal.classList.contains('active')) {
        closeImageModal();
    }
});
</script>
HTML;
include __DIR__ . '/../includes/secretary_layout_end.php';
?>
