<?php
require_once __DIR__ . '/../config/bpis_session.php';
bpis_start_session();
include '../config/database.php';
require_once __DIR__ . '/../config/auth_helpers.php';
require_once __DIR__ . '/../config/borrow_request_display_helpers.php';
require_once __DIR__ . '/../config/table_date_filter_helpers.php';
require_once __DIR__ . '/../config/secretary_page_helpers.php';
bpis_require_login(['Secretary', 'Barangay Captain']);

$bpis_u = bpis_secretary_load_user($conn);
$user_fullname = $bpis_u['fullname'];
$user_role = $bpis_u['role'];

$total_assets = 0;
$working_count = 0;
$repair_count = 0;
$dispose_count = 0;
$inventory_total_value = 0.0;
$inventory_fixed = 0;
$inventory_movable = 0;
$asset_stats_query = "SELECT
    COUNT(*) AS total_count,
    COUNT(CASE WHEN remarks = 'SERVICEABLE' OR item_condition = 'SERVICEABLE' THEN 1 END) AS serviceable_total,
    COUNT(CASE WHEN remarks = 'UNDER REPAIR' OR item_condition = 'UNDER REPAIR' THEN 1 END) AS repair_total,
    COUNT(CASE WHEN remarks = 'UNSERVICEABLE/DISPOSE' OR item_condition = 'UNSERVICEABLE/DISPOSE' THEN 1 END) AS dispose_total,
    COALESCE(SUM(total_cost), 0) AS total_value
    FROM asset";
$asset_stats_result = mysqli_query($conn, $asset_stats_query);
if ($asset_stats_result && ($asset_stats = mysqli_fetch_assoc($asset_stats_result))) {
    $total_assets = (int) ($asset_stats['total_count'] ?? 0);
    $working_count = (int) ($asset_stats['serviceable_total'] ?? 0);
    $repair_count = (int) ($asset_stats['repair_total'] ?? 0);
    $dispose_count = (int) ($asset_stats['dispose_total'] ?? 0);
    $inventory_total_value = (float) ($asset_stats['total_value'] ?? 0);
}
if (function_exists('bpis_column_exists') && bpis_column_exists($conn, 'asset', 'asset_cluster')) {
    $fr = mysqli_query($conn, "SELECT COUNT(*) AS c FROM asset WHERE asset_cluster = 'Fixed Assets'");
    if ($fr && ($r = mysqli_fetch_assoc($fr))) {
        $inventory_fixed = (int) ($r['c'] ?? 0);
    }
    $mr = mysqli_query($conn, "SELECT COUNT(*) AS c FROM asset WHERE asset_cluster = 'Movable Assets'");
    if ($mr && ($r = mysqli_fetch_assoc($mr))) {
        $inventory_movable = (int) ($r['c'] ?? 0);
    }
}
$inventory_updated_at = date('Y-m-d H:i:s');

$req_stats_query = "SELECT 
    COUNT(CASE WHEN status = 'Approved' THEN 1 END) as approved_total,
    COUNT(CASE WHEN status = 'Pending' THEN 1 END) as pending_total
    FROM borrowing_requests";
$req_stats_result = mysqli_query($conn, $req_stats_query);
$req_stats = mysqli_fetch_assoc($req_stats_result);

$borrower_stats_query = "SELECT 
    COUNT(*) as total_borrowers,
    COUNT(CASE WHEN status = 'Received' THEN 1 END) as received_total,
    COUNT(CASE WHEN status = 'Received' AND return_date < CURRENT_DATE THEN 1 END) as overdue_total
    FROM borrower";
$borrower_stats_result = mysqli_query($conn, $borrower_stats_query);
$borrower_stats = mysqli_fetch_assoc($borrower_stats_result);

$total_requests  = $req_stats['approved_total'] ?? 0;
$total_pending   = $req_stats['pending_total'] ?? 0;
$total_borrowers = $borrower_stats['total_borrowers'] ?? 0;
$total_overdue   = $borrower_stats['overdue_total'] ?? 0;

$borrowing_requests = [];
$req_query = "SELECT * FROM borrowing_requests WHERE status = 'Pending' ORDER BY id DESC LIMIT 5";
$req_result = mysqli_query($conn, $req_query);
if ($req_result) {
    while ($row = mysqli_fetch_assoc($req_result)) {
        $borrowing_requests[] = $row;
    }
}

$registered_borrowers = [];
$borrower_query = 'SELECT * FROM borrower ORDER BY id DESC LIMIT 5';
$borrower_result = mysqli_query($conn, $borrower_query);
if ($borrower_result) {
    while ($row = mysqli_fetch_assoc($borrower_result)) {
        $registered_borrowers[] = $row;
    }
}

$ph_now = new DateTime('now', new DateTimeZone('Asia/Manila'));
$hour_now = (int) $ph_now->format('G');
if ($hour_now < 12) {
    $greeting = 'Good morning';
} elseif ($hour_now < 18) {
    $greeting = 'Good afternoon';
} else {
    $greeting = 'Good evening';
}

$bpis_secretary_nav_active = 'dashboard';
$bpis_skip_content_panel = true;
$bpis_page_title = 'Summary Dashboard';
$bpis_page_heading = $greeting . ' Secretary, ' . $user_fullname . '.';
$bpis_page_subtitle = 'Overview of pending tasks and recent activity.';
$bpis_extra_head = '<link rel="stylesheet" href="../css/stat_cards.css"><link rel="stylesheet" href="../css/table_date_filter.css"><style>
/* Remove empty flex gap below tables on dashboard — match Requests fix for all dashboards */
.secretary-page-scroll{flex:0 1 auto !important; min-height:auto !important; height:auto !important; padding-bottom:0 !important; overflow:visible !important;}
.main-content{height:auto !important; min-height:0 !important; overflow:visible !important;}
body{height:auto !important; min-height:100vh !important; overflow:auto !important;}
.table-section:last-of-type{margin-bottom:0 !important;}
</style>';
include __DIR__ . '/../includes/secretary_layout_start.php';
?>
        <div class="cards-container">
            <div class="card card-blue"><p>Total Request</p><div class="card-number"><?= $total_requests ?></div></div>
            <div class="card card-yellow"><p>Total Pending</p><div class="card-number"><?= $total_pending ?></div></div>
            <div class="card card-green"><p>Total Borrowers</p><div class="card-number"><?= $total_borrowers ?></div></div>
            <div class="card card-red"><p>Overdue</p><div class="card-number"><?= $total_overdue ?></div></div>
        </div>

        <div class="table-section" data-bpis-date-filter-scope>
            <h3>Recent Borrowing Requests</h3>
            <table>
                <thead>
                    <tr>
                        <th>Borrower</th>
                        <th>Item</th>
                        <th>Request Date</th>
                        <th>Return Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($borrowing_requests)): ?>
                        <tr><td colspan="5" style="text-align:center;color:#888;font-style:italic;padding:30px;">No pending borrowing requests found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($borrowing_requests as $request): ?>
                            <tr class="request-row" data-status="<?= strtolower(htmlspecialchars($request['status'] ?? '')) ?>"<?= bpis_row_date_attr($request, ['created_at', 'date_needed', 'needed_on', 'return_date']) ?>>
                                <td><span class="borrower-name"><?= htmlspecialchars($request['full_name'] ?? $request['borrower'] ?? '') ?></span></td>
                                <td><?= htmlspecialchars(bpis_borrow_request_item_label($conn, $request)) ?></td>
                                <td><?= !empty($request['date_needed'] ?? $request['request_date'] ?? '') ? date('M d, Y', strtotime($request['date_needed'] ?? $request['request_date'])) : 'N/A' ?></td>
                                <td><?= !empty($request['return_date'] ?? '') ? date('M d, Y', strtotime($request['return_date'])) : 'N/A' ?></td>
                                <td><span class="status-text status-pending"><?= strtoupper(htmlspecialchars($request['status'])) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            <a href="requests.php" class="view-all-link">View all requests →</a>
        </div>

        <div class="table-section" data-bpis-date-filter-scope>
            <h3>Recent Registered Borrowers</h3>
            <table>
                <thead>
                    <tr>
                        <th>Borrower</th>
                        <th>Item</th>
                        <th>Qty</th>
                        <th>Purpose</th>
                        <th>Return Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($registered_borrowers)): ?>
                        <tr><td colspan="5" style="text-align:center;color:#888;font-style:italic;padding:30px;">No registered borrowers yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($registered_borrowers as $borrower): ?>
                            <tr class="borrower-row" data-status="<?= strtolower(htmlspecialchars($borrower['status'] ?? '')) ?>"<?= bpis_row_date_attr($borrower, ['return_date', 'needed_on', 'date_needed', 'created_at']) ?>>
                                <td><span class="borrower-name"><?= htmlspecialchars($borrower['full_name'] ?? $borrower['borrower'] ?? '') ?></span></td>
                                <td><?= htmlspecialchars(bpis_borrow_request_item_label($conn, $borrower)) ?></td>
                                <td><?= htmlspecialchars($borrower['qty'] ?? $borrower['quantity'] ?? '1') ?></td>
                                <td><?= htmlspecialchars($borrower['purpose'] ?? 'General') ?></td>
                                <td><?= !empty($borrower['return_date'] ?? '') ? date('M d, Y', strtotime($borrower['return_date'])) : 'N/A' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            <a href="borrowers2.php" class="view-all-link">View all borrowers →</a>
        </div>
<?php
$bpis_layout_footer_scripts = <<<'HTML'
<script src="../js/table_date_filter.js"></script>
HTML;
include __DIR__ . '/../includes/secretary_layout_end.php';