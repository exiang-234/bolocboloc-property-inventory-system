<?php
session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/submit_request_helpers.php';
require_once __DIR__ . '/../config/notification_helpers.php';
require_once __DIR__ . '/../config/auth_helpers.php';

if (!isset($_SESSION['full_name'])) {
    header('Location: step1.php');
    exit();
}

if (empty($_SESSION['valid_id_attached_confirmed'])) {
    $_SESSION['step1_error'] = 'Please attach the front and back of your valid ID before continuing.';
    header('Location: step1.php');
    exit();
}
$valid_id_front = (string) ($_SESSION['valid_id_front_path'] ?? $_SESSION['valid_id_path'] ?? '');
$valid_id_back = (string) ($_SESSION['valid_id_back_path'] ?? '');
if ($valid_id_front === '' || $valid_id_back === '') {
    header('Location: step1.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim((string) $_SESSION['full_name']);
    $email = trim((string) ($_SESSION['email'] ?? ''));
    $purok = trim((string) ($_POST['purok'] ?? ''));
    $contact_number = trim((string) ($_POST['contact_number'] ?? ''));
    $purpose = trim((string) ($_POST['purpose'] ?? ''));
    $date_needed = trim((string) ($_POST['date_needed'] ?? ''));
    $return_date = trim((string) ($_POST['return_date'] ?? ''));
    $search_items = $_POST['search_item'] ?? [];
    $quantities = $_POST['quantity'] ?? [];
    $asset_ids = $_POST['asset_id'] ?? [];

    if ($full_name === '' || $email === '' || $purok === '' || $contact_number === '' || $purpose === '' || $date_needed === '' || $return_date === '') {
        $_SESSION['borrow_error'] = 'Please complete all required fields.';
        header('Location: step2.php');
        exit();
    }

    if (bpis_borrower_is_blocked($conn, $full_name, $email)) {
        $_SESSION['borrow_error'] = 'You cannot submit a request. Your account is on the blocklist for unreturned items. Please contact the barangay secretary.';
        header('Location: step2.php');
        exit();
    }

    $borrower_profile_id = bpis_upsert_borrower_profile(
        $conn,
        $full_name,
        $email,
        $purok,
        $contact_number,
        $valid_id_front,
        $valid_id_back
    );

    if (!is_array($search_items) || !is_array($quantities) || count($search_items) === 0) {
        $_SESSION['borrow_error'] = 'Please add at least one item to borrow.';
        header('Location: step2.php');
        exit();
    }

    require_once __DIR__ . '/../config/borrow_request_batch_helpers.php';
    bpis_ensure_borrow_request_batch_column($conn);
    $batch_id = bpis_generate_request_batch_id();

    $last_request_id = 0;
    $any_saved = false;
    $saved_items = [];

    for ($i = 0; $i < count($search_items); $i++) {
        $item = trim((string) $search_items[$i]);
        $qty = (int) ($quantities[$i] ?? 0);
        if ($item === '' || $qty <= 0) {
            continue;
        }

        $asset_id = isset($asset_ids[$i]) ? (int) $asset_ids[$i] : 0;
        if ($asset_id <= 0) {
            $asset_id = bpis_find_asset_id_by_description($conn, $item);
        }
        if ($asset_id > 0 && !bpis_asset_id_is_borrowable($conn, $asset_id)) {
            $_SESSION['borrow_error'] = 'Only serviceable items in these categories may be borrowed: '
                . bpis_borrowable_categories_label() . '.';
            header('Location: step2.php');
            exit();
        }
        $insert_id = bpis_insert_borrowing_request_row(
            $conn,
            $full_name,
            $email,
            $contact_number,
            $purok,
            $item,
            $qty,
            $purpose,
            $date_needed,
            $return_date,
            $asset_id,
            $borrower_profile_id,
            $batch_id
        );

        if ($insert_id > 0) {
            $any_saved = true;
            $last_request_id = $insert_id;
            $saved_items[] = $qty . ' × ' . $item;
        }
    }

    if ($any_saved) {
        $item_count = count($saved_items);
        $summary = $item_count === 1
            ? $saved_items[0]
            : $item_count . ' items: ' . implode('; ', $saved_items);
        bpis_notify_admins_by_roles(
            $conn,
            ['Secretary', 'Barangay Captain'],
            'New borrow request',
            $full_name . ' submitted a borrowing request (' . $summary . ').',
            'secretary/requests.php',
            'new_borrow_request',
            $last_request_id,
            null
        );
    }

    if (!$any_saved) {
        $_SESSION['borrow_error'] = 'Could not save your request. The database may need updating—please contact the barangay office.';
        header('Location: step2.php');
        exit();
    }

    unset(
        $_SESSION['full_name'],
        $_SESSION['email'],
        $_SESSION['valid_id_path'],
        $_SESSION['valid_id_front_path'],
        $_SESSION['valid_id_back_path'],
        $_SESSION['borrow_error']
    );
    header('Location: success.php');
    exit();
}

$borrow_error = '';
if (!empty($_SESSION['borrow_error'])) {
    $borrow_error = (string) $_SESSION['borrow_error'];
    unset($_SESSION['borrow_error']);
}

$borrow_step = 2;
$borrow_page_title = 'Request Information';
$borrow_heading = 'Request details';
$borrow_lead = 'Add items, dates, and purpose for your borrowing request.';
$borrow_wide = true;
$borrow_show_back = true;
$borrow_back_href = 'step1.php';
$borrow_extra_head = '<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>';
include __DIR__ . '/includes/borrow_layout_start.php';
?>
        <?php if ($borrow_error !== ''): ?>
            <div class="borrow-alert" role="alert"><?= htmlspecialchars($borrow_error) ?></div>
        <?php endif; ?>

        <form method="post" action="step2.php" id="borrowForm">
            <div class="borrow-grid-2">
                <div class="borrow-field">
                    <label for="purok">Purok</label>
                    <input type="text" id="purok" name="purok" class="borrow-input" required>
                </div>
                <div class="borrow-field">
                    <label for="contact_number">Contact number</label>
                    <input type="tel" id="contact_number" name="contact_number" class="borrow-input"
                           placeholder="09XX XXX XXXX" inputmode="tel" required>
                </div>
                <div class="borrow-field borrow-field--full">
                <label for="barangay">Barangay</label>
                    <input type="text" id="barangay" name="barangay" class="borrow-input"
                           value="Bolocboloc, Sibulan, Negros Oriental" readonly>
            </div>
            </div>

            <div class="borrow-field" style="margin-top: 8px;">
                <p class="borrow-section-title">Items to borrow</p>
                <p class="borrow-section-hint">Tap a product, enter quantity, then Add to cart. Use the filter to search the catalog.</p>

                <div class="borrow-panel">
                    <div class="borrow-panel-head">
                        <span>Available items</span>
                        <span class="borrow-panel-note">Tap a product to select</span>
                    </div>
                    <div class="borrow-filter">
                        <label for="search_input">Filter (optional)</label>
                        <input type="text" id="search_input" class="borrow-input"
                               placeholder="Search item name or category…" autocomplete="off">
                    </div>
                    <div id="browse_items_container" class="borrow-scroll">
                        <div class="borrow-loading">Loading items…</div>
                    </div>
                </div>

                <input type="hidden" id="max_quantity" value="0">

                <p id="selected_item_line" class="borrow-selected" aria-live="polite">
                    Selected: <strong id="selected_item_name"></strong>
                </p>

                <div class="borrow-add-row">
                    <div class="borrow-field">
                        <label for="request_qty">Quantity</label>
                        <input type="number" id="request_qty" class="borrow-input" min="1" placeholder="1">
                    </div>
                    <button type="button" id="add_item_btn" class="borrow-btn borrow-btn--sm">Add item</button>
                </div>

                <p id="qty_warning" class="borrow-qty-warning" role="alert"></p>
                <div id="added_items_list"></div>
                <div id="hidden_inputs_container"></div>
            </div>

            <div class="borrow-field">
                <label for="purpose">Purpose of borrowing</label>
                <textarea id="purpose" name="purpose" class="borrow-textarea"
                          placeholder="Briefly describe why you need these items…" required></textarea>
            </div>

            <div class="borrow-grid-2">
                <div class="borrow-field">
                    <label for="date_needed">Date needed</label>
                    <input type="date" id="date_needed" name="date_needed" class="borrow-input" required>
                </div>
                <div class="borrow-field">
                    <label for="return_date">Return date</label>
                    <input type="date" id="return_date" name="return_date" class="borrow-input" required>
            </div>
            </div>

            <button type="submit" class="borrow-btn">Submit request</button>
        </form>
<?php
$borrow_footer_scripts = <<<'HTML'
<script>
$(function () {
    var addedItems = [];
    var pickedAssetId = 0;
    var selectedItemName = '';

    function escAttr(s) {
        return String(s).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    function escapeHtml(s) {
        return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    function fetchItemResults(query) {
        $('#browse_items_container').html('<div class="borrow-loading">Loading items…</div>');
        $.ajax({
            url: 'fetch_items.php',
            method: 'POST',
            data: { query: query },
            success: function (data) {
                $('#browse_items_container').html(data);
            },
            error: function () {
                $('#browse_items_container').html('<div class="borrow-loading" style="color:#b91c1c;">Unable to load items. Please refresh and try again.</div>');
            }
        });
    }

    fetchItemResults('');

    $('#search_input').on('input', function () {
        pickedAssetId = 0;
        selectedItemName = '';
        $('#selected_item_line').removeClass('is-visible');
        $('#max_quantity').val(0);
        $('.borrow-product-card.result-item').removeClass('selected');
        $('#qty_warning').removeClass('is-visible').text('');
        fetchItemResults($(this).val().trim());
    });

    function selectProductCard($card) {
        $('.borrow-product-card.result-item').removeClass('selected');
        $card.addClass('selected');

        var selectedVal = $card.attr('data-value') || '';
            if (!selectedVal) {
            selectedVal = $card.find('.borrow-product-title').first().text().trim();
        }
        pickedAssetId = parseInt($card.attr('data-asset-id'), 10) || 0;
        selectedItemName = selectedVal;

        $('#selected_item_name').text(selectedVal);
        $('#selected_item_line').addClass('is-visible');
        $('#max_quantity').val($card.attr('data-max-qty') || 0);
        checkQuantity();
    }

    $(document).on('click', '.borrow-product-card.result-item', function () {
        selectProductCard($(this));
    });

    $(document).on('keydown', '.borrow-product-card.result-item', function (e) {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            selectProductCard($(this));
        }
    });

    $('#request_qty').on('input', checkQuantity);

    function checkQuantity() {
        var maxQty = parseInt($('#max_quantity').val(), 10) || 0;
        var val = $('#request_qty').val();
        var reqQty = parseInt(val, 10) || 0;
        var $warn = $('#qty_warning');

        if (maxQty > 0 && val !== '' && reqQty > maxQty) {
            $warn.text('Only ' + maxQty + ' available.').addClass('is-visible');
            return false;
        }
        $warn.removeClass('is-visible').text('');
        return true;
    }

    $('#add_item_btn').on('click', function () {
        var itemName = selectedItemName.trim();
        var qtyInput = $('#request_qty').val();
        var reqQty = parseInt(qtyInput, 10) || 0;
        var maxQty = parseInt($('#max_quantity').val(), 10) || 0;

        if (!itemName || maxQty <= 0) {
            alert('Select a product from the grid first (available quantity must be greater than zero).');
            return;
        }
        if (qtyInput === '' || reqQty <= 0) {
            alert('Enter a valid quantity of at least 1.');
            return;
        }
        if (!checkQuantity()) {
            alert('Insufficient quantity. Only ' + maxQty + ' available.');
            return;
        }
        if (addedItems.some(function (item) { return item.name === itemName; })) {
            alert('This item is already in your list. Remove it first to change quantity.');
            return;
        }

        addedItems.push({ name: itemName, qty: reqQty, assetId: pickedAssetId });
        pickedAssetId = 0;
        selectedItemName = '';
        $('#search_input').val('');
        $('#request_qty').val('');
        $('#max_quantity').val(0);
        $('#qty_warning').removeClass('is-visible');
        $('#selected_item_line').removeClass('is-visible');
        $('.borrow-product-card.result-item').removeClass('selected');
        fetchItemResults('');
        renderAddedItems();
    });

    function renderAddedItems() {
        var hiddenInputs = '';
        addedItems.forEach(function (item) {
            hiddenInputs += '<input type="hidden" name="search_item[]" value="' + escAttr(item.name) + '">';
            hiddenInputs += '<input type="hidden" name="quantity[]" value="' + escAttr(String(item.qty)) + '">';
            hiddenInputs += '<input type="hidden" name="asset_id[]" value="' + escAttr(String(parseInt(item.assetId, 10) || 0)) + '">';
        });

        if (addedItems.length === 0) {
            $('#added_items_list').html('');
            $('#hidden_inputs_container').html('');
            return;
        }

        var html = '<div class="borrow-added-table-wrap"><table class="borrow-added-table"><thead><tr>';
        html += '<th>#</th><th>Item</th><th>Qty</th><th></th></tr></thead><tbody>';
        addedItems.forEach(function (item, index) {
            html += '<tr><td>' + (index + 1) + '</td>';
            html += '<td>' + escapeHtml(item.name) + '</td>';
            html += '<td>' + escapeHtml(String(item.qty)) + '</td>';
            html += '<td><button type="button" class="borrow-remove-btn" data-index="' + index + '">Remove</button></td></tr>';
        });
        html += '</tbody></table></div>';

        $('#added_items_list').html(html);
        $('#hidden_inputs_container').html(hiddenInputs);
    }

    $(document).on('click', '.borrow-remove-btn', function () {
        addedItems.splice($(this).data('index'), 1);
        renderAddedItems();
    });

    $('#borrowForm').on('submit', function (e) {
        if (addedItems.length === 0) {
            e.preventDefault();
            alert('Add at least one item before submitting.');
            $('#search_input').focus();
        }
    });
});
</script>
HTML;
include __DIR__ . '/includes/borrow_layout_end.php';
