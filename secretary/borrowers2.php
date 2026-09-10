<?php
require_once __DIR__ . '/../config/bpis_session.php';
bpis_start_session();

include '../config/database.php';
require_once __DIR__ . '/../config/auth_helpers.php';
require_once __DIR__ . '/../config/borrow_request_display_helpers.php';
require_once __DIR__ . '/../config/table_date_filter_helpers.php';
require_once __DIR__ . '/../config/asset_photo_helpers.php';
require_once __DIR__ . '/../config/asset_borrowable_helpers.php';
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
$add_error = $_GET['add_error'] ?? '';

$registered_borrowers = [];
$borrow_query = "SELECT * FROM borrower ORDER BY id DESC"; 
$borrow_result = mysqli_query($conn, $borrow_query);

if ($borrow_result) {
    while ($row = mysqli_fetch_assoc($borrow_result)) {
        $registered_borrowers[] = $row;
    }
}

$available_assets = [];
$asset_query = 'SELECT * FROM asset WHERE 1=1 '
    . bpis_sql_asset_borrowable_where()
    . bpis_sql_asset_borrowable_category_clause($conn)
    . ' ORDER BY description ASC';
$asset_result = mysqli_query($conn, $asset_query);
if ($asset_result) {
    while ($row = mysqli_fetch_assoc($asset_result)) {
        if (!bpis_asset_row_is_borrowable($row)) {
            continue;
        }
        $avail = bpis_asset_borrowable_available_qty($conn, $row);
        if ($avail < 1) {
            continue;
        }
        $row['bpis_available_qty'] = $avail;
        $available_assets[] = $row;
    }
}

$asset_photo_map = bpis_get_asset_photo_map($conn, array_column($available_assets, 'id'));
?>
<?php
$bpis_secretary_nav_active = 'borrowers';
$bpis_skip_content_panel = true;
$bpis_page_title = 'Registered Borrowers';
$bpis_page_heading = 'Registered Borrowers';
$bpis_page_subtitle = 'List of all registered borrowers.';
$bpis_extra_head = '<link rel="stylesheet" href="../css/stat_cards.css"><link rel="stylesheet" href="../css/table_date_filter.css"><link rel="stylesheet" href="../css/secretary_borrowers2.css"><script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<style>

    .item-list-table .bpis-asset-thumb {
        width: 40px !important;
        height: 40px !important;
        object-fit: cover !important;
        border-radius: 4px !important;
    }
    .item-list-table .cell-image {
        width: 50px !important;
        text-align: center !important;
    }
    .item-list-table td.cell-image img {
        max-width: 40px !important;
        max-height: 40px !important;
        width: auto !important;
        height: auto !important;
    }

    @media (max-width: 1024px) {
        .item-list-table .bpis-asset-thumb {
            width: 34px !important;
            height: 34px !important;
            border-radius: 3px !important;
        }
        .item-list-table .cell-image {
            width: 44px !important;
        }
        .item-list-table td.cell-image img {
            max-width: 34px !important;
            max-height: 34px !important;
        }
    }

    @media (max-width: 768px) {
        .item-list-table .bpis-asset-thumb {
            width: 30px !important;
            height: 30px !important;
            border-radius: 3px !important;
        }
        .item-list-table .cell-image {
            width: 38px !important;
        }
        .item-list-table td.cell-image img {
            max-width: 30px !important;
            max-height: 30px !important;
        }
    }

    @media (max-width: 600px) {
        .item-list-table .bpis-asset-thumb {
            width: 26px !important;
            height: 26px !important;
            border-radius: 2px !important;
        }
        .item-list-table .cell-image {
            width: 34px !important;
        }
        .item-list-table td.cell-image img {
            max-width: 26px !important;
            max-height: 26px !important;
        }
    }

    @media (max-width: 480px) {
        .item-list-table .bpis-asset-thumb {
            width: 22px !important;
            height: 22px !important;
            border-radius: 2px !important;
        }
        .item-list-table .cell-image {
            width: 30px !important;
        }
        .item-list-table td.cell-image img {
            max-width: 22px !important;
            max-height: 22px !important;
        }
    }

    @media (max-width: 400px) {
        .item-list-table .bpis-asset-thumb {
            width: 18px !important;
            height: 18px !important;
            border-radius: 2px !important;
        }
        .item-list-table .cell-image {
            width: 26px !important;
        }
        .item-list-table td.cell-image img {
            max-width: 18px !important;
            max-height: 18px !important;
        }
    }

    @media (max-width: 360px) {
        .item-list-table .bpis-asset-thumb {
            width: 16px !important;
            height: 16px !important;
            border-radius: 1px !important;
        }
        .item-list-table .cell-image {
            width: 22px !important;
        }
        .item-list-table td.cell-image img {
            max-width: 16px !important;
            max-height: 16px !important;
        }
    }
</style>';
include __DIR__ . '/../includes/secretary_layout_start.php';
?>
        <?php if ($add_error !== ''): ?>
            <div style="margin-bottom:15px;padding:10px 14px;border-radius:8px;background:#fee2e2;color:#b91c1c;border:1px solid #fecaca;font-size:13px;">
                <?php
                    if ($add_error === 'missing_fields') {
                        echo "Please complete all borrower fields and add at least one item.";
                    } elseif ($add_error === 'schema_mismatch') {
                        echo "Unable to save request due to database column mismatch. Please contact the developer.";
                    } elseif ($add_error === 'not_borrowable') {
                        echo 'Only serviceable items in these categories may be borrowed: '
                            . htmlspecialchars(bpis_borrowable_categories_label()) . '.';
                    } else {
                        echo "Unable to submit request. Please try again.";
                    }
                ?>
            </div>
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
                    <a href="borrowers.php" class="borrowers-tab-link" style="text-decoration: none; padding: 10px 5px;"><h3 style="margin:0;font-size:15px;font-weight:700;color:inherit;display:inline;">Approved</h3></a>
                    <a href="borrowers2.php" class="borrowers-tab-link active" style="text-decoration: none; padding: 10px 5px;"><h3 style="margin:0;font-size:15px;font-weight:700;color:inherit;display:inline;">Registered</h3></a>
                </div>
            </div>
        <div class="bpis-date-filter-bar borrowers-filter-bar" style="display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap;">

            <div class="borrowers-date-controls" style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                <span class="bpis-date-filter-label">Filter by date</span>

                <label>
                    From <input type="date" data-bpis-date-from aria-label="Filter from date">
                </label>

                <label>
                    To <input type="date" data-bpis-date-to aria-label="Filter to date">
                </label>

                <button type="button" class="bpis-date-clear-btn" data-bpis-date-clear>
                    Clear dates
                </button>
            </div>

            <div class="header-actions borrowers-header-actions"
                style="display:flex; align-items:center; gap:10px; margin-left:auto;">

                <div class="search-container">
                    <span class="search-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </span>

                    <input type="text"
                        id="searchInput"
                        placeholder="Search borrower..."
                        style="width: 230px">
                </div>

                <button type="button"
                        class="add-btn"
                        onclick="openAddModal()"
                        style="width: 150px; font-size: 13px;">
                    + Add Borrower
                </button>

            </div>
           

        </div>
            <div class="table-responsive-container borrowers-registered-scroll">
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
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($registered_borrowers)): ?>
                    <tr><td colspan="10" style="padding:40px; color:#888;font-style: italic;">No registered borrowers found.</td></tr>
                <?php else: ?>
                        <tr id="noMatchRow" style="display: none;">
                            <td colspan="10" style="color: #666; font-size: 13px; text-align: center; padding: 60px 0;">
                                No result found.
                            </td>
                        </tr>
                    <?php foreach ($registered_borrowers as $borrower): ?>
                        <tr class="data-row" data-status="<?= strtolower(htmlspecialchars($borrower['status'] ?? '')) ?>"<?= bpis_row_date_attr($borrower, ['needed_on', 'date_needed', 'return_date', 'created_at']) ?>>
                            <td>B-<?= str_pad($borrower['request_id'] ?? 0, 4, '0', STR_PAD_LEFT) ?></td>
                            <td><span class="borrower-name"><?= htmlspecialchars($borrower['full_name'] ?? '') ?></span></td>
                            <td><?= htmlspecialchars((str_starts_with(strtolower(trim((string)($borrower['address'] ?? $borrower['purok'] ?? ''))), 'purok') ? '' : 'Purok ') . ($borrower['address'] ?? $borrower['purok'] ?? '')) ?></td>
                            <td><?= htmlspecialchars($borrower['contact'] ?? $borrower['contact_number'] ?? '') ?></td>
                            <td><?= htmlspecialchars(bpis_borrow_request_item_label($conn, $borrower)) ?></td>
                            <td><?= htmlspecialchars($borrower['qty'] ?? $borrower['quantity'] ?? '1') ?></td>
                            <td><?= !empty($borrower['needed_on'] ?? $borrower['date_needed'] ?? '') ? date('M d, Y', strtotime($borrower['needed_on'] ?? $borrower['date_needed'])) : 'N/A' ?></td>
                            <td><?= htmlspecialchars($borrower['purpose'] ?? '') ?></td>
                            <td><?= !empty($borrower['return_date'] ?? '') ? date('M d, Y', strtotime($borrower['return_date'])) : 'N/A' ?></td>
                            <td>
                                <?php
                                $row_status = strtolower((string)($borrower['status'] ?? ''));
                                if ($row_status === 'returned'): ?>
                                    <span class="status-item-returned">Returned</span>
                                <?php elseif (in_array($row_status, ['received', 'overdue'], true)): ?>
                                <form method="POST" action="process_receive.php" style="margin: 0;" class="receive-form">
                                    <input type="hidden" name="b_id" value="<?= (int)($borrower['id'] ?? 0) ?>">
                                    <button type="button" name="action_receive" class="btn-receive open-receive-modal">
                                        Return
                                    </button>
                                </form>
                                <?php else: ?>
                                    <span style="color:#888;font-size:13px;"><?= htmlspecialchars($borrower['status'] ?? '—') ?></span>
                                <?php endif; ?>
                            </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        </div>
        </div>
    </div>

    <div id="addBorrowerModal" class="modal-overlay">
        <div class="modal-content borrower-modal">
            <button type="button" class="close-modal-btn" onclick="closeAddModal()">
                <span aria-hidden="true">&times;</span>
            </button>
            <div class="borrower-modal-header">
                <h2>Add Borrower</h2>
            </div>
            <div class="borrower-modal-body">
                <form id="addBorrowerForm" action="process_add_borrower.php" method="POST">
                    <div class="modal-section">
                        <div class="modal-section-title">Borrower Information</div>
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Full Name</label>
                                <input type="text" name="full_name" required placeholder="Enter name">
                            </div>
                            <div class="form-group">
                                <label>Contact Number</label>
                                <input type="text" name="contact" required placeholder="Enter contact">
                            </div>
                            <div class="form-group">
                                <label>Email Address</label>
                                <input type="email" name="email" required placeholder="example@email.com">
                            </div>
                            <div class="form-group">
                                <label>Address (Purok)</label>
                                <input type="text" name="address" required placeholder="e.g. 1, 2, 3">
                            </div>
                        </div>
                    </div>

                    <div class="modal-section">
                        <div class="modal-section-title">Items Requested</div>
                        <div class="form-group full-width">
                            <div class="inline-add-row">
                                <div class="search-col">
                                    <input type="text" id="search_input" placeholder="Search items..." autocomplete="off" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:6px;">
                                    <div id="item-results"></div>
                                    <input type="hidden" id="selected_item_id">
                                </div>
                                <div class="qty-col">
                                    <input type="number" id="request_qty" min="1" placeholder="Qty" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:6px;">
                                </div>
                                <div class="btn-col">
                                    <button type="button" id="add_item_btn" class="btn-add-inline">Add</button>
                                </div>
                            </div>
                            <small id="qty_warning" style="color: red; display: none; margin-top: 5px;">Quantity exceeds available stock!</small>
                            <div class="item-helper-text">Click any row from the table below to auto-fill item and stock quantity.</div>
                            <div class="item-list-wrap">
                                <table class="item-list-table">
                                    <thead>
                                        <tr>
                                            <th class="col-image">IMAGE</th>
                                            <th>DESCRIPTION</th>
                                            <th>CATEGORY</th>
                                            <th>PROPERTY NUMBER</th>
                                            <th>UOM</th>
                                            <th>DATE ACQUIRED</th>
                                            <th>UNIT VALUE / COST</th>
                                            <th>REMARKS</th>
                                            <th>STATUS</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($available_assets)): ?>
                                            <tr><td colspan="9" style="text-align:center;color:#94a3b8;">No serviceable items found.</td></tr>
                                        <?php else: ?>
                                            <?php foreach ($available_assets as $asset): ?>
                                                <?php
                                                    $avail_qty = (int) ($asset['bpis_available_qty'] ?? bpis_asset_borrowable_available_qty($conn, $asset));
                                                    $description = $asset['description'] ?? '';
                                                    $total_qty = (int)($asset['quantity'] ?? 0);
                                                    $borrowed_qty = $total_qty - $avail_qty;
                                                ?>
                                                <tr class="item-row-select" data-asset-id="<?php echo (int)($asset['id'] ?? 0); ?>" data-item="<?php echo htmlspecialchars($description); ?>" data-max-qty="<?php echo $avail_qty; ?>" data-total-qty="<?php echo $total_qty; ?>" data-borrowed-qty="<?php echo $borrowed_qty; ?>">
                                                    <td class="cell-image"><?= bpis_asset_photo_img($conn, (int)($asset['id'] ?? 0), $description, 'bpis-asset-thumb', $asset_photo_map) ?></td>
                                                    <td><?php echo htmlspecialchars($description); ?></td>
                                                    <td><?php echo htmlspecialchars($asset['category'] ?? ''); ?></td>
                                                    <td><?php echo htmlspecialchars($asset['property_number'] ?? ''); ?></td>
                                                    <td><?php echo htmlspecialchars($asset['unit_measure'] ?? ''); ?></td>
                                                    <td><?php echo !empty($asset['date_acquired']) ? htmlspecialchars(date('Y-m-d', strtotime($asset['date_acquired']))) : ''; ?></td>
                                                    <td><?php echo "₱ " . number_format((float)($asset['unit_value'] ?? 0), 2); ?></td>
                                                    <td><?php echo htmlspecialchars($asset['remarks'] ?? ''); ?></td>
                                                    <td><?php echo $avail_qty . " Available"; ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="staged-items-container" id="stagedItemsList">
                            <div class="placeholder-text">No items added yet.</div>
                        </div>
                        <div id="hiddenStagedInputs"></div>
                    </div>

                    <div class="modal-section">
                        <div class="modal-section-title">Schedule and Purpose</div>
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Needed On</label>
                                <input type="date" name="needed_on" required>
                            </div>
                            <div class="form-group">
                                <label>Return Date</label>
                                <input type="date" name="return_date" required>
                            </div>
                            <div class="form-group full-width">
                                <label>Purpose</label>
                                <input type="text" name="purpose" required placeholder="State the purpose">
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="modal-submit-btn">Register Borrower</button>
                </form>
            </div>
        </div>
    </div>
    <div id="popupToast" class="popup-toast"></div>

    
<?php
$bpis_layout_footer_scripts = <<<'HTML'
<div class="logout-overlay" id="receiveOverlay">
        <div class="logout-modal receive-modal">
            <h2>Returned Confirmation</h2>
            <p class="receive-modal-note" id="receiveMessage">Enter how many units are being returned now. Use less than the borrowed quantity if the borrower only brought back part of the items.</p>
            <div class="receive-qty-wrap">
                <label for="receiveQtyInput">Quantity returned</label>
                <input type="number" id="receiveQtyInput" min="1" value="1" inputmode="numeric" autocomplete="off">
            </div>
            <p id="receiveQtyHint"></p>
            <div class="logout-buttons">
                <button type="button" class="btn-confirm" id="confirmReceiveAction">Confirm</button>
                <button type="button" class="btn-cancel" id="cancelReceiveAction">Cancel</button>
            </div>
    <script src="../js/table_date_filter.js"></script>
    <script>
        const receiveOverlay = document.getElementById('receiveOverlay');
        const confirmReceiveAction = document.getElementById('confirmReceiveAction');
        const cancelReceiveAction = document.getElementById('cancelReceiveAction');
        const receiveQtyInput = document.getElementById('receiveQtyInput');
        const receiveQtyHint = document.getElementById('receiveQtyHint');
        let pendingReceiveForm = null;
        let pendingReceiveMaxQty = 1;
        function openAddModal() { document.getElementById('addBorrowerModal').style.display = 'flex'; }
        function closeAddModal() { document.getElementById('addBorrowerModal').style.display = 'none'; }
        
        
        $('#confirmLogoutAction').click(function() { 
            window.location.href = "../login.php"; 
        });

        
        function refreshBorrowersTableFilters() {
            var searchVal = ($("#searchInput").val() || '').toLowerCase();
            var statusFilter = ($("#borrowerFilter").val() || 'all').toLowerCase();
            var visibleRows = 0;
            $(".data-row").each(function() {
                var tr = this;
                var textOk = $(tr).text().toLowerCase().indexOf(searchVal) > -1;
                var st = (tr.dataset.status || '').toLowerCase();
                var statusOk = statusFilter === 'all' || st === statusFilter;
                tr.dataset.bpisSearchHidden = (textOk && statusOk) ? '' : '1';
                if (typeof window.bpisSyncRowDisplay === 'function') {
                    window.bpisSyncRowDisplay(tr);
                } else {
                    tr.style.display = (textOk && statusOk) ? '' : 'none';
                }
                if (tr.style.display !== 'none') visibleRows++;
            });
            $("#noMatchRow").toggle(visibleRows === 0);
        }

        $("#searchInput").on("keyup", refreshBorrowersTableFilters);

        $("#borrowerFilter").on("change", refreshBorrowersTableFilters);

        document.querySelectorAll('[data-bpis-date-filter-scope]').forEach(function(scope) {
            scope.addEventListener('bpis-date-filter-changed', refreshBorrowersTableFilters);
        });

        function showPopup(message) {
            const toast = $('#popupToast');
            toast.stop(true, true).text(message).fadeIn(180);
            setTimeout(() => toast.fadeOut(220), 2200);
        }

        let selectedMaxQty = 0;
        let selectedAssetId = 0;
        let stagedItems = [];

        $('#search_input').on('keyup', function() {
        const value = $(this).val().toLowerCase();
        $('.item-row-select').filter(function() {
            $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
        });
    });

        $('.item-row-select').on('click', function() {
            const itemName = $(this).data('item') || '';
            const maxQty = parseInt($(this).data('max-qty')) || 0;
            const assetId = parseInt($(this).data('asset-id')) || 0;
            
            if (stagedItems.some(item => item.id === assetId)) {
                showPopup('This item is already in your list.');
                return;
            }
            
            selectedMaxQty = maxQty;
            selectedAssetId = assetId;
            $('#search_input').val(itemName);
            $('#selected_item_id').val(assetId);
            $('#qty_warning').hide();
            $('#request_qty').focus();
            
            const statusCell = $(this).find('td:last-child');
            statusCell.text(maxQty + ' Available');
        });

        $('#request_qty').on('input', function() {
            const reqQty = parseInt($(this).val()) || 0;
            if (selectedMaxQty > 0 && reqQty > selectedMaxQty) {
                $('#qty_warning').text('Quantity exceeds available stock!').show();
            } else {
                $('#qty_warning').hide();
            }
        });

        $('#add_item_btn').on('click', function() {
            const itemName = ($('#search_input').val() || '').trim();
            const reqQty = parseInt($('#request_qty').val()) || 0;

            if (!itemName || selectedAssetId <= 0) {
                showPopup('Please select an item from the list.');
                return;
            }
            if (reqQty <= 0) {
                showPopup('Please enter a valid quantity.');
                return;
            }
            if (selectedMaxQty > 0 && reqQty > selectedMaxQty) {
                $('#qty_warning').text('Quantity exceeds available stock!').show();
                return;
            }
            if (stagedItems.some(item => item.id === selectedAssetId)) {
                showPopup('Item already added. Remove it first to change quantity.');
                return;
            }

            stagedItems.push({ id: selectedAssetId, name: itemName, qty: reqQty, maxQty: selectedMaxQty });
            renderStagedItems();

            $('#search_input').val('');
            $('#selected_item_id').val('');
            $('#request_qty').val('');
            selectedMaxQty = 0;
            selectedAssetId = 0;
            $('#qty_warning').hide();
        });

        $(document).on('click', '.btn-remove-item', function() {
            const idx = parseInt($(this).data('index'));
            stagedItems.splice(idx, 1);
            renderStagedItems();
        });

        function renderStagedItems() {
            let cards = '';
            let hidden = '';

            if (stagedItems.length === 0) {
                cards = '<div class="placeholder-text">No items added yet.</div>';
            } else {
                stagedItems.forEach((item, index) => {
                    const remaining = item.maxQty - item.qty;
                    cards += `
                        <div class="staged-item-card">
                            <div class="staged-item-info"><strong>${item.name}</strong> (Qty: ${item.qty} | Available: ${remaining})</div>
                            <button type="button" class="btn-remove-item" data-index="${index}">Remove</button>
                        </div>
                    `;
                    hidden += `
                        <input type="hidden" name="asset_id[]" value="${item.id}">
                        <input type="hidden" name="search_item[]" value="${item.name}">
                        <input type="hidden" name="quantity[]" value="${item.qty}">
                    `;
                });
            }

            $('#stagedItemsList').html(cards);
            $('#hiddenStagedInputs').html(hidden);
        }

        $('#addBorrowerForm').on('submit', function(e) {
            if (stagedItems.length === 0 && ($('#search_input').val() || '').trim() !== '' && ($('#request_qty').val() || '').trim() !== '') {
                $('#add_item_btn').trigger('click');
            }

            if (stagedItems.length === 0) {
                e.preventDefault();
                showPopup('Please add at least one item. Type qty and click Add.');
            }
        });
        
        document.querySelectorAll('.open-receive-modal').forEach(btn => {
    btn.addEventListener('click', () => {
        pendingReceiveForm = btn.closest('form');

        const tr = pendingReceiveForm.closest('tr');
        const qtyCell = tr.querySelector('td:nth-child(6)');
        const raw = qtyCell.textContent.trim();

        pendingReceiveMaxQty = parseInt(raw, 10) || 1;

        receiveQtyInput.max = pendingReceiveMaxQty;
        receiveQtyInput.value = pendingReceiveMaxQty;

        receiveQtyHint.textContent =
            'Borrowed on this approval: ' + pendingReceiveMaxQty + ' unit(s).';

        receiveOverlay.style.display = 'flex';
    });
});
if (confirmReceiveAction) {
confirmReceiveAction.addEventListener('click', () => {
    let v = parseInt(receiveQtyInput.value, 10);

    if (!v || v < 1) {
        alert('Enter valid quantity');
        return;
    }

    if (v > pendingReceiveMaxQty) {
        alert('Cannot exceed borrowed quantity');
        return;
    }

    if (!pendingReceiveForm) {
        alert('No row selected.');
        return;
    }

    const formData = new FormData(pendingReceiveForm);
    formData.append('return_qty', v);

    fetch('../secretary/process_return.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {

            const tr = pendingReceiveForm.closest('tr');
            const statusCell = tr.querySelector('td:last-child');
            const qtyCell = tr.querySelector('td:nth-child(6)');

            if (data.new_status === 'Returned') {
            statusCell.innerHTML = `
                <span class="status-item-returned">Returned</span>
            `;
                pendingReceiveForm.remove();
                tr.dataset.status = 'returned';
            } else {
                if (qtyCell && typeof data.new_qty !== 'undefined') {
                    qtyCell.textContent = String(data.new_qty);
                }
                tr.dataset.status = 'received';
            }

            receiveOverlay.style.display = 'none';

        } else {
            alert(data.message || 'Failed to update');
        }
    })
    .catch(err => {
        console.error(err);
        alert('Server error');
    });
});
}

        if (cancelReceiveAction && receiveOverlay) {
            cancelReceiveAction.addEventListener('click', function (e) {
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
    </script>
HTML;
include __DIR__ . '/../includes/secretary_layout_end.php';
?>
