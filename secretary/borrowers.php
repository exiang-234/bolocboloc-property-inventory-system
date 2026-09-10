<?php
require_once __DIR__ . '/../config/bpis_session.php';
bpis_start_session();

include '../config/database.php';
require_once __DIR__ . '/../config/auth_helpers.php';
require_once __DIR__ . '/../config/borrow_request_display_helpers.php';
require_once __DIR__ . '/../config/table_date_filter_helpers.php';

bpis_require_login(['Secretary', 'Barangay Captain']);

$req_stats_query = "SELECT 
    COUNT(CASE WHEN status = 'Approved' THEN 1 END) as approved_total,
    COUNT(CASE WHEN status = 'Pending' THEN 1 END) as pending_total
    FROM borrowing_requests";
$req_stats_result = mysqli_query($conn, $req_stats_query);
$req_stats = mysqli_fetch_assoc($req_stats_result);


$borrower_stats_query = "SELECT 
    COUNT(CASE WHEN status = 'Received' THEN 1 END) as received_total,
    COUNT(CASE WHEN status = 'Received' AND return_date < CURRENT_DATE THEN 1 END) as overdue_total
    FROM borrower";
$borrower_stats_result = mysqli_query($conn, $borrower_stats_query);
$borrower_stats = mysqli_fetch_assoc($borrower_stats_result);

$total_requests  = $req_stats['approved_total'] ?? 0;
$total_pending   = $req_stats['pending_total'] ?? 0;
$total_borrowers = $borrower_stats['received_total'] ?? 0;
$total_overdue   = $borrower_stats['overdue_total'] ?? 0;

$borrowers_count_query = "SELECT COUNT(DISTINCT full_name) as count FROM borrower";
$borrowers_count_result = mysqli_query($conn, $borrowers_count_query);
$borrowers_count_data = mysqli_fetch_assoc($borrowers_count_result);
$total_borrowers_count = $borrowers_count_data['count'] ?? 0;

$registered_borrowers = [];
$borrow_query = "SELECT * FROM borrowing_requests 
                 WHERE status = 'Approved' 
                 ORDER BY id DESC";
$borrow_result = mysqli_query($conn, $borrow_query);

if ($borrow_result) {
    while ($row = mysqli_fetch_assoc($borrow_result)) {
        $registered_borrowers[] = $row;
    }
}

$flash_msg = '';
$flash_err = '';
if (!empty($_SESSION['msg'])) {
    $flash_msg = (string)$_SESSION['msg'];
    unset($_SESSION['msg']);
}
if (!empty($_SESSION['error'])) {
    $flash_err = (string)$_SESSION['error'];
    unset($_SESSION['error']);
}
?>
<?php
$bpis_secretary_nav_active = 'borrowers';
$bpis_skip_content_panel = true;
$bpis_page_title = 'Approved Borrowers Dashboard';
$bpis_page_heading = 'Approved Borrowers';
$bpis_page_subtitle = 'List of all approved borrowers.';
$bpis_extra_head = '<link rel="stylesheet" href="../css/stat_cards.css"><link rel="stylesheet" href="../css/table_date_filter.css">
<style>
.borrowers-table-scroll {
    width: 100%;
    overflow: auto;
    -webkit-overflow-scrolling: touch;
}
.borrowers-table-scroll .secretary-data-table {
    min-width: 980px;
}
.borrowers-tabs-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 0;
}
.borrowers-tabs {
    display: flex;
    gap: 20px;
}
.borrowers-filter-bar {
    align-items: center;
}
.borrowers-search-wrap {
    margin-left: auto;
}
@media (max-width: 768px) {
    .borrowers-tabs-row {
        display: block !important;
        margin-bottom: 12px !important;
    }
    .borrowers-tabs {
        display: grid !important;
        grid-template-columns: 1fr 1fr;
        gap: 8px !important;
        width: 100%;
    }
    .borrowers-tabs .borrowers-tab-link {
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: 40px;
        padding: 9px 10px !important;
        border: 1px solid #dbe3ef;
        border-radius: 8px;
        background: #ffffff;
        font-size: 13px;
        text-align: center;
    }
    .borrowers-tabs .borrowers-tab-link.active {
        background: #eff6ff;
        border-color: #93c5fd;
    }
    .borrowers-filter-bar {
        display: flex !important;
        flex-direction: column !important;
        align-items: stretch !important;
        gap: 10px !important;
    }
    .borrowers-filter-bar .bpis-date-filter-label,
    .borrowers-filter-bar label,
    .borrowers-filter-bar .bpis-date-clear-btn,
    .borrowers-search-wrap,
    .borrowers-search-wrap .search-container,
    .borrowers-search-wrap input {
        width: 100% !important;
        max-width: none !important;
        margin-left: 0 !important;
    }
    .borrowers-table-scroll {
        max-height: calc(100vh - 300px);
        max-height: calc(100dvh - 300px);
        min-height: 260px;
        overscroll-behavior: contain;
    }
    .borrowers-table-scroll .secretary-data-table thead th {
        position: sticky;
        top: 0;
        z-index: 3;
        background: #f8fafc;
    }
}
@media (max-width: 480px) {
    .borrowers-table-scroll {
        max-height: calc(100vh - 270px);
        max-height: calc(100dvh - 270px);
        min-height: 240px;
    }
}
@media (max-width: 360px) {
    .borrowers-table-scroll {
        max-height: calc(100vh - 240px);
        max-height: calc(100dvh - 240px);
        min-height: 220px;
    }
}
</style>';
include __DIR__ . '/../includes/secretary_layout_start.php';
?>
        <?php if ($flash_msg !== ''): ?>
            <div class="flash-banner success"><?= htmlspecialchars($flash_msg) ?></div>
        <?php endif; ?>
        <?php if ($flash_err !== ''): ?>
            <div class="flash-banner error"><?= htmlspecialchars($flash_err) ?></div>
        <?php endif; ?>
        <div class="cards-container">
            <div class="card card-blue"><p>Total Requests</p><div class="card-number"><?= $total_requests ?></div></div>
            <div class="card card-yellow"><p>Total Pending</p><div class="card-number"><?= $total_pending ?></div></div>
            <div class="card card-green"><p>Total Borrowers</p><div class="card-number"><?= $total_borrowers_count ?></div></div>
            <div class="card card-red"><p>Overdue</p><div class="card-number"><?= $total_overdue ?></div></div>
        </div>

        <div class="table-section" data-bpis-date-filter-scope>
            <div class="borrowers-tabs-row" style="margin-bottom: 16px;">
                <div class="borrowers-tabs">
                    <a href="borrowers.php" class="borrowers-tab-link active" style="text-decoration:none;padding:10px 5px;"><h3 style="margin:0;font-size:15px;font-weight:700;color:inherit;display:inline;">Approved</h3></a>
                    <a href="borrowers2.php" class="borrowers-tab-link" style="text-decoration:none;padding:10px 5px;"><h3 style="margin:0;font-size:15px;font-weight:700;color:inherit;display:inline;">Registered</h3></a>
                </div>
            </div>
            <div class="bpis-date-filter-bar borrowers-filter-bar">
                <span class="bpis-date-filter-label">Filter by date</span>
                <label>From <input type="date" data-bpis-date-from aria-label="Filter from date"></label>
                <label>To <input type="date" data-bpis-date-to aria-label="Filter to date"></label>
                <button type="button" class="bpis-date-clear-btn" data-bpis-date-clear>Clear dates</button>
                <div class="search-container borrowers-search-wrap" style="margin-left:auto;">
                <span class="search-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </span>
                <input type="text" id="searchInput" placeholder="Search borrower...">
            </div>
            </div>
          <div class="borrowers-table-scroll">
          <table id="requestsTable" class="secretary-data-table">
    <thead>
        <tr>
            <th>ID</th>
            <th>Borrower</th>
            <th>Address</th>
            <th>Contact</th>
            <th>Item</th>
            <th>QTY</th>
            <th>Needed On</th>
            <th>Purpose</th>
            <th>Return Date</th>
            <th>Code</th>
            <th>Status</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($registered_borrowers)): ?>
            <tr><td colspan="11" style="text-align: center; padding: 40px; color: #888; font-style: italic;">No approved borrowers found.</td></tr>
        <?php else: ?>
            <tr id="noMatchRow" style="display: none;">
                <td colspan="11" style="color: #666; font-size: 11px; text-align: center; padding: 60px 0;">
                    No result found.
                </td>
            </tr>
            <?php foreach ($registered_borrowers as $borrower): ?>
             <tr class="data-row"
    data-bpis-date="<?= !empty($borrower['return_date'])
        ? date('Y-m-d', strtotime($borrower['return_date']))
        : '' ?>">
                    <td>R-<?= str_pad($borrower['id'], 4, '0', STR_PAD_LEFT) ?></td>
                    <td><span class="borrower-name"><?= htmlspecialchars($borrower['full_name'] ?? '') ?></span></td>
                    <td>Purok <?= htmlspecialchars($borrower['purok'] ?? $borrower['address'] ?? '') ?></td>
                    <td><?= htmlspecialchars($borrower['contact_number'] ?? $borrower['contact'] ?? '') ?></td>
                    <td><?= htmlspecialchars(bpis_borrow_request_item_label($conn, $borrower)) ?></td>
                    <td><?= htmlspecialchars($borrower['quantity'] ?? $borrower['qty'] ?? '1') ?></td>
                    <td><?= !empty($borrower['date_needed'] ?? $borrower['needed_on'] ?? '') ? date('M d, Y', strtotime($borrower['date_needed'] ?? $borrower['needed_on'])) : 'N/A' ?></td>
                    <td><?= htmlspecialchars($borrower['purpose'] ?? '') ?></td>
                    <td><?= !empty($borrower['return_date'] ?? '') ? date('M d, Y', strtotime($borrower['return_date'])) : 'N/A' ?></td>
                    <td><code><?= htmlspecialchars($borrower['otp'] ?? 'N/A') ?></code></td>
                    <td>
                        <form method="POST" action="process_receive.php" style="margin: 0;" class="receive-form">
                            <input type="hidden" name="request_id" value="<?= (int)($borrower['id'] ?? 0) ?>">
                            <button type="button" name="action_receive" class="btn-receive open-receive-modal" style="font-size: 11px; font-weight: bold; text-transform: uppercase;">
                                Receive
                            </button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>
        </div>
        </div>
<?php
$bpis_layout_footer_scripts = <<<'HTML'
<div class="logout-overlay" id="receiveOverlay" style="display:none;">
    <div class="logout-modal" style="text-align:left;">
        <h2 style="text-align:center;margin-bottom:10px;">Receive confirmation</h2>
        <p class="receive-modal-note">Enter how many units are being received now. Use less than the approved quantity if the borrower only picks up part of the items.</p>
        <div class="receive-qty-wrap">
            <label for="approvedReceiveQtyInput">Quantity received</label>
            <input type="number" id="approvedReceiveQtyInput" min="1" value="1" inputmode="numeric" autocomplete="off">
        </div>
        <p id="approvedReceiveQtyHint"></p>
        <div class="logout-buttons" style="margin-top:8px;">
            <button type="button" class="btn-confirm" id="confirmApprovedReceiveBtn">Confirm</button>
            <button type="button" class="btn-cancel" id="cancelApprovedReceiveBtn">Cancel</button>
        </div>
    </div>
</div>
<script src="../js/table_date_filter.js"></script>
<script>
document.getElementById('searchInput').addEventListener('keyup', function () {
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
        if (row.style.display !== 'none') visibleCount++;
    });
    if (noMatchRow) {
        noMatchRow.style.display = visibleCount === 0 && filter !== '' ? '' : 'none';
    }
});
const receiveOverlay = document.getElementById('receiveOverlay');
        const approvedReceiveQtyInput = document.getElementById('approvedReceiveQtyInput');
        const approvedReceiveQtyHint = document.getElementById('approvedReceiveQtyHint');
        const confirmApprovedReceiveBtn = document.getElementById('confirmApprovedReceiveBtn');
        const cancelApprovedReceiveBtn = document.getElementById('cancelApprovedReceiveBtn');
        let pendingApprovedReceiveForm = null;
        let pendingApprovedReceiveMaxQty = 1;

        document.querySelectorAll('.open-receive-modal').forEach(function (btn) {
            btn.addEventListener('click', function () {
                pendingApprovedReceiveForm = btn.closest('form');
                if (!pendingApprovedReceiveForm || !receiveOverlay || !approvedReceiveQtyInput || !approvedReceiveQtyHint) {
                    return;
                }
                const tr = pendingApprovedReceiveForm.closest('tr');
                const qtyCell = tr ? tr.querySelector('td:nth-child(6)') : null;
                const raw = qtyCell ? qtyCell.textContent.trim() : '';
                pendingApprovedReceiveMaxQty = parseInt(raw, 10) || 1;
                approvedReceiveQtyInput.max = pendingApprovedReceiveMaxQty;
                approvedReceiveQtyInput.min = 1;
                approvedReceiveQtyInput.value = pendingApprovedReceiveMaxQty;
                approvedReceiveQtyHint.textContent =
                    'Borrowed on this approval: ' + pendingApprovedReceiveMaxQty + ' unit(s).';
                receiveOverlay.style.display = 'flex';
            });
        });

        if (cancelApprovedReceiveBtn && receiveOverlay) {
            cancelApprovedReceiveBtn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                receiveOverlay.style.display = 'none';
            });
        }
        if (receiveOverlay) {
            receiveOverlay.addEventListener('click', function (e) {
                if (e.target === receiveOverlay) {
                    receiveOverlay.style.display = 'none';
                }
            });
        }

        if (confirmApprovedReceiveBtn && approvedReceiveQtyInput && receiveOverlay) {
            confirmApprovedReceiveBtn.addEventListener('click', function () {
                var v = parseInt(approvedReceiveQtyInput.value, 10);
                if (!v || v < 1) {
                    alert('Enter a valid quantity');
                    return;
                }
                if (v > pendingApprovedReceiveMaxQty) {
                    alert('Cannot exceed approved quantity');
                    return;
                }
                if (!pendingApprovedReceiveForm) {
                    alert('No request selected');
                    return;
                }
                var prev = pendingApprovedReceiveForm.querySelector('input[name="return_qty"]');
                if (prev) {
                    prev.remove();
                }
                var hid = document.createElement('input');
                hid.type = 'hidden';
                hid.name = 'return_qty';
                hid.value = String(v);
                pendingApprovedReceiveForm.appendChild(hid);
                receiveOverlay.style.display = 'none';
                pendingApprovedReceiveForm.submit();
            });
        }
</script>
HTML;
include __DIR__ . '/../includes/secretary_layout_end.php';
