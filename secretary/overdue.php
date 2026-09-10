<?php
require_once __DIR__ . '/../config/bpis_session.php';
bpis_start_session();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth_helpers.php';
require_once __DIR__ . '/../config/borrow_request_display_helpers.php';
require_once __DIR__ . '/../config/table_date_filter_helpers.php';
require_once __DIR__ . '/../config/notification_helpers.php';
bpis_require_login(['Secretary', 'Barangay Captain']);

// Graceful DB handling — prevents white screen if DB is down
$req_stats = ['approved_total' => 0, 'pending_total' => 0];
$borrower_stats = ['total_borrowers' => 0, 'received_total' => 0, 'overdue_total' => 0];
$overdue_logs = [];
$today = date('Y-m-d');
$db_error = null;

if (!isset($conn) || !($conn instanceof mysqli) || $conn->connect_error) {
    $db_error = 'Database unavailable — showing empty state.';
    $total_requests = 0;
    $total_pending = 0;
    $total_borrowers = 0;
    $total_overdue = 0;
} else {
    try {
        $req_stats_query = "SELECT 
            COUNT(CASE WHEN status = 'Approved' THEN 1 END) as approved_total,
            COUNT(CASE WHEN status = 'Pending' THEN 1 END) as pending_total
            FROM borrowing_requests";
        $req_stats_result = @mysqli_query($conn, $req_stats_query);
        if ($req_stats_result) {
            $tmp = mysqli_fetch_assoc($req_stats_result);
            if ($tmp) $req_stats = $tmp;
        }

        $borrower_stats_query = "SELECT 
            COUNT(*) as total_borrowers,
            COUNT(CASE WHEN status = 'Received' THEN 1 END) as received_total,
            COUNT(CASE WHEN status = 'Received' AND return_date < CURRENT_DATE THEN 1 END) as overdue_total
            FROM borrower";
        $borrower_stats_result = @mysqli_query($conn, $borrower_stats_query);
        if ($borrower_stats_result) {
            $tmp = mysqli_fetch_assoc($borrower_stats_result);
            if ($tmp) $borrower_stats = $tmp;
        }

        $total_requests  = (int) ($req_stats['approved_total'] ?? 0);
        $total_pending   = (int) ($req_stats['pending_total'] ?? 0);
        $total_borrowers = (int) ($borrower_stats['total_borrowers'] ?? 0);
        $total_overdue   = (int) ($borrower_stats['overdue_total'] ?? 0);

        $overdue_query = "SELECT * 
                          FROM borrower
                          WHERE status = 'Received' 
                          AND return_date < '" . mysqli_real_escape_string($conn, $today) . "' 
                          ORDER BY return_date ASC";
        $overdue_result = @mysqli_query($conn, $overdue_query);

        if ($overdue_result) {
            while ($row = mysqli_fetch_assoc($overdue_result)) {
                try {
                    $ret_date = new DateTime($row['return_date'] ?? $today);
                    $curr_date = new DateTime($today);
                    $interval = $ret_date->diff($curr_date);
                    $days_delayed = $interval->days;
                } catch (Throwable $e) {
                    $days_delayed = 0;
                }
                $overdue_logs[] = [
                    'id' => 'B-' . str_pad($row['request_id'] ?? $row['id'] ?? 0, 4, '0', STR_PAD_LEFT),
                    'borrower' => $row['full_name'] ?? '',
                    'address' => 'Purok ' . ($row['address'] ?? $row['purok'] ?? ''),
                    'contact' => $row['contact'] ?? $row['contact_number'] ?? '',
                    'item' => function_exists('bpis_borrow_request_item_label') ? bpis_borrow_request_item_label($conn, $row) : ($row['item'] ?? ''),
                    'qty' => $row['qty'] ?? $row['quantity'] ?? '1',
                    'needed_on' => !empty($row['needed_on'] ?? $row['date_needed'] ?? '') ? date('M d, Y', strtotime($row['needed_on'] ?? $row['date_needed'])) : 'N/A',
                    'purpose' => $row['purpose'] ?? '',
                    'return_date' => !empty($row['return_date'] ?? '') ? date('M d, Y', strtotime($row['return_date'])) : 'N/A',
                    'return_date_raw' => $row['return_date'] ?? '',
                    'needed_raw' => $row['needed_on'] ?? $row['date_needed'] ?? '',
                    'warning' => $days_delayed . ' day(s) return delay',
                ];
            }
        }

        if (count($overdue_logs) > 0) {
            $cnt = count($overdue_logs);
            @mysqli_query($conn, "DELETE FROM notifications WHERE relationship IN ('overdue_digest_sec','overdue_digest_tre','overdue_digest_cap')");
            try {
                bpis_notify_admins_by_roles($conn, ['Secretary'], 'Overdue alert', 'Borrowing overdue: ' . $cnt . ' item(s) not yet returned.', 'secretary/overdue.php', 'overdue_digest_sec', null, null);
                bpis_notify_admins_by_roles($conn, ['Treasurer'], 'Overdue inventory', 'Overdue: ' . $cnt . ' active loan(s) past return date.', 'secretary/overdue.php', 'overdue_digest_tre', null, null);
                bpis_notify_admins_by_roles($conn, ['Barangay Captain'], 'Borrowing activity', 'Borrowing activity: ' . $cnt . ' overdue return(s) need follow-up.', 'secretary/overdue.php', 'overdue_digest_cap', null, null);
            } catch (Throwable $e) {
                error_log("Overdue notify failed: " . $e->getMessage());
            }
        }
    } catch (Throwable $e) {
        error_log("Overdue page error: " . $e->getMessage());
        $db_error = 'Could not load data — please refresh.';
        $total_requests = (int) ($req_stats['approved_total'] ?? 0);
        $total_pending = (int) ($req_stats['pending_total'] ?? 0);
        $total_borrowers = (int) ($borrower_stats['total_borrowers'] ?? 0);
        $total_overdue = (int) ($borrower_stats['overdue_total'] ?? 0);
    }
}
// Ensure variables defined for layout even if DB failed
if (!isset($total_requests)) $total_requests = 0;
if (!isset($total_pending)) $total_pending = 0;
if (!isset($total_borrowers)) $total_borrowers = 0;
if (!isset($total_overdue)) $total_overdue = 0;
if (!isset($overdue_logs)) $overdue_logs = [];

$bpis_secretary_nav_active = 'overdue';
$bpis_skip_content_panel = true;
$bpis_page_title = 'Overdue Borrowers';
$bpis_page_heading = 'Overdue Borrowers';
$bpis_page_subtitle = 'Track borrowers past their return date.';
$bpis_extra_head = '<link rel="stylesheet" href="../css/stat_cards.css"><link rel="stylesheet" href="../css/table_date_filter.css">';
include __DIR__ . '/../includes/secretary_layout_start.php';
?>
        <style>
            .overdue-table-toolbar {
                display: grid;
                grid-template-columns: minmax(0, 1fr) minmax(240px, 320px);
                align-items: center;
                gap: 14px;
            }

            .overdue-date-controls {
                display: flex;
                align-items: center;
                flex-wrap: wrap;
                gap: 10px;
                min-width: 0;
            }

            .overdue-date-controls label {
                white-space: nowrap;
            }

            .overdue-search {
                justify-self: end;
                width: 100%;
                max-width: 320px;
            }

            .overdue-search input {
                width: 100%;
            }

            .overdue-table-scroll {
                width: 100%;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                scrollbar-width: thin;
            }

            .overdue-table-scroll .secretary-data-table {
                min-width: 980px;
            }

            .overdue-table-scroll .secretary-data-table thead th {
                position: sticky;
                top: 0;
                z-index: 2;
                background: #f8fafc;
                box-shadow: inset 0 -1px 0 #e5e7eb;
            }

            @media (max-width: 1024px) {
                .overdue-table-toolbar {
                    grid-template-columns: 1fr;
                    align-items: stretch;
                }

                .overdue-search {
                    justify-self: stretch;
                    max-width: none;
                }
            }

            @media (max-width: 768px) {
                .overdue-table-toolbar.bpis-date-filter-bar {
                    display: flex !important;
                    flex-direction: column !important;
                    align-items: stretch !important;
                    gap: 10px !important;
                }

                .overdue-date-controls {
                    width: 100%;
                    display: grid !important;
                    grid-template-columns: 1fr 1fr;
                    gap: 8px !important;
                }

                .overdue-date-controls .bpis-date-filter-label,
                .overdue-date-controls .bpis-date-clear-btn {
                    grid-column: 1 / -1;
                    width: 100%;
                }

                .overdue-date-controls label {
                    width: 100%;
                    display: flex !important;
                    align-items: center !important;
                    justify-content: space-between !important;
                    gap: 8px !important;
                }

                .overdue-date-controls input[type="date"] {
                    width: min(100%, 155px) !important;
                }

                .overdue-search,
                .overdue-search input {
                    width: 100% !important;
                    max-width: none !important;
                }

                .overdue-table-scroll {
                    max-height: calc(100dvh - 330px);
                    min-height: 260px;
                    overflow: auto;
                    overscroll-behavior: contain;
                }
            }

            @media (max-width: 480px) {
                .overdue-date-controls {
                    grid-template-columns: 1fr;
                }

                .overdue-date-controls input[type="date"] {
                    width: min(100%, 180px) !important;
                }

                .overdue-table-scroll {
                    max-height: calc(100dvh - 350px);
                    min-height: 230px;
                }
            }
        </style>
        <div class="cards-container">
            <div class="card card-blue"><p>Total Requests</p><div class="card-number"><?= (int) $total_requests ?></div></div>
            <div class="card card-yellow"><p>Total Pending</p><div class="card-number"><?= (int) $total_pending ?></div></div>
            <div class="card card-green"><p>Total Borrowers</p><div class="card-number"><?= (int) $total_borrowers ?></div></div>
            <div class="card card-red"><p>Overdue</p><div class="card-number"><?= (int) $total_overdue ?></div></div>
        </div>
        <?php if (!empty($db_error)): ?>
            <div class="flash-banner error" style="margin-bottom:16px;"><?= htmlspecialchars($db_error) ?> Please try refreshing or contact admin if this persists.</div>
        <?php endif; ?>
        
        <div class="table-section" data-bpis-date-filter-scope>
            <div class="content-subheader">
                <h3>Recent Overdue/s</h3>
            </div>
            <div class="bpis-date-filter-bar overdue-table-toolbar">
                <div class="overdue-date-controls">
                <span class="bpis-date-filter-label">Filter by date</span>
                    <label>From <input type="date" data-bpis-date-from aria-label="Filter from date"></label>
                    <label>To <input type="date" data-bpis-date-to aria-label="Filter to date"></label>
                    <button type="button" class="bpis-date-clear-btn" data-bpis-date-clear>Clear dates</button>
            </div>
             <div class="search-container overdue-search">
                <span class="search-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </span>
                <input type="text" id="searchInput" placeholder="Search borrower...">
                </div>
            </div>
            <div class="overdue-table-scroll">
            <table id="overdueTable" class="secretary-data-table">
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
                        <th>Warning</th>
                    </tr>
                </thead>
                <tbody>
    <?php if (empty($overdue_logs)): ?>
        <tr>
                            <td colspan="10" style="text-align:center;padding:40px;color:#888;font-style:italic;">No overdue returns yet.</td>
        </tr>
    <?php else: ?>
                        <tr id="noMatchRow" style="display:none;">
                            <td colspan="10" style="text-align:center;padding:40px;color:#666;">No result found.</td>
        </tr>
        <?php foreach ($overdue_logs as $log): ?>
            <tr class="data-row"<?= bpis_row_date_attr(['return_date' => $log['return_date_raw'] ?? '', 'needed_on' => $log['needed_raw'] ?? ''], ['return_date', 'needed_on']) ?>>
                <td class="row-id"><?= htmlspecialchars($log['id']) ?></td>
                <td><?= htmlspecialchars($log['borrower']) ?></td>
                <td><?= htmlspecialchars($log['address']) ?></td>
                <td><?= htmlspecialchars($log['contact']) ?></td>
                <td><?= htmlspecialchars($log['item']) ?></td>
                <td><?= htmlspecialchars($log['qty']) ?></td>
                <td><?= htmlspecialchars($log['needed_on']) ?></td>
                <td><?= htmlspecialchars($log['purpose']) ?></td>
                <td><?= htmlspecialchars($log['return_date']) ?></td>
                <td><span class="warning-text"><?= htmlspecialchars($log['warning']) ?></span></td>
            </tr>
        <?php endforeach; ?>
    <?php endif; ?>
</tbody>
            </table>
            </div>
        </div>
<?php
$bpis_layout_footer_scripts = <<<'HTML'
      <script src="../js/table_date_filter.js"></script>
      <script>
        function searchOverdueTable() {
    const input = document.getElementById('searchInput');
    const filter = input.value.toLowerCase();
            let visibleRows = 0;
    document.querySelectorAll('#overdueTable tbody .data-row').forEach(function (row) {
        const textOk = row.textContent.toLowerCase().indexOf(filter) > -1;
                row.dataset.bpisSearchHidden = textOk ? '' : '1';
                if (typeof window.bpisSyncRowDisplay === 'function') {
                    window.bpisSyncRowDisplay(row);
                } else {
                    row.style.display = textOk ? '' : 'none';
                }
                if (textOk) visibleRows++;
            });
            const noMatchRow = document.getElementById('noMatchRow');
            if (noMatchRow) {
        noMatchRow.style.display = visibleRows === 0 && filter !== '' ? '' : 'none';
            }
        }
        document.getElementById('searchInput').addEventListener('keyup', searchOverdueTable);
    </script>
HTML;
include __DIR__ . '/../includes/secretary_layout_end.php';
