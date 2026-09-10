<?php

$bpis_assets = $bpis_assets ?? [];
$bpis_asset_picker_id = $bpis_asset_picker_id ?? 'assetPicker';
$bpis_selected_asset_id = (int) ($bpis_selected_asset_id ?? 0);
$bpis_asset_input_name = $bpis_asset_input_name ?? 'asset_id';
$bpis_asset_picker_required = !isset($bpis_asset_picker_required) || $bpis_asset_picker_required;
$bpis_asset_picker_label = $bpis_asset_picker_label ?? 'Asset';
$bpis_asset_picker_extra = $bpis_asset_picker_extra ?? '';
$bpis_asset_picker_collapsible = $bpis_asset_picker_collapsible ?? false;
$bpis_asset_picker_collapsed = $bpis_asset_picker_collapsed ?? true;

$bpis_picker_selected = null;
foreach ($bpis_assets as $bpis_a) {
    if ((int) ($bpis_a['id'] ?? 0) === $bpis_selected_asset_id) {
        $bpis_picker_selected = $bpis_a;
        break;
    }
}

static $bpis_asset_picker_assets_loaded = false;
if (!$bpis_asset_picker_assets_loaded) {
    $bpis_asset_picker_assets_loaded = true;
    echo '<link rel="stylesheet" href="../css/asset_picker.css">' . "\n";
}
?>
<div class="bpis-asset-picker<?= $bpis_picker_selected ? '' : ' bpis-asset-picker-placeholder' ?><?= $bpis_asset_picker_collapsible ? ' bpis-asset-picker-collapsible' : '' ?>" id="<?= htmlspecialchars($bpis_asset_picker_id, ENT_QUOTES, 'UTF-8') ?>" data-bpis-asset-picker>
    <div class="bpis-asset-picker-header">
        <label class="block text-[13px] font-semibold text-gray-700 mb-1.5">
            <?= htmlspecialchars($bpis_asset_picker_label, ENT_QUOTES, 'UTF-8') ?>
            <?php if ($bpis_asset_picker_required): ?><span class="text-red-500">*</span><?php endif; ?>
        </label>
        <?php if ($bpis_asset_picker_collapsible): ?>
            <button type="button" class="bpis-asset-picker-toggle" aria-expanded="<?= $bpis_asset_picker_collapsed ? 'false' : 'true' ?>" data-picker-toggle>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" class="bpis-asset-picker-chevron">
                    <path d="<?= $bpis_asset_picker_collapsed ? 'M6 9l6 6 6-6' : 'M18 15l-6-6-6 6' ?>"/>
                </svg>
                <span class="bpis-asset-picker-toggle-label">
                    <?= $bpis_asset_picker_collapsed ? 'Show' : 'Hide' ?>
                </span>
            </button>
        <?php endif; ?>
    </div>
    <?= $bpis_asset_picker_extra ?>

    <div class="bpis-asset-picker-body" data-picker-body style="<?= $bpis_asset_picker_collapsible && $bpis_asset_picker_collapsed ? 'display:none;' : '' ?>">
        <select
            name="<?= htmlspecialchars($bpis_asset_input_name, ENT_QUOTES, 'UTF-8') ?>"
            id="<?= htmlspecialchars($bpis_asset_picker_id, ENT_QUOTES, 'UTF-8') ?>_native"
            class="bpis-asset-picker-native"
            <?= $bpis_asset_picker_required ? ' required' : '' ?>
            tabindex="-1"
            aria-hidden="true"
        >
            <option value="">Select asset</option>
            <?php foreach ($bpis_assets as $bpis_a):
                $bpis_aid = (int) ($bpis_a['id'] ?? 0);
                $bpis_sel = ($bpis_selected_asset_id > 0 && $bpis_aid === $bpis_selected_asset_id) ? ' selected' : '';
                $bpis_nbv = (float) ($bpis_a['net_book_value'] ?? $bpis_a['unit_value'] ?? 0);
                $bpis_acc = (float) ($bpis_a['accumulated_depreciation'] ?? 0);
            ?>
                <option
                    value="<?= $bpis_aid ?>"
                    <?= $bpis_sel ?>
                    data-photo="<?= htmlspecialchars((string) ($bpis_a['photo_url'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                    data-name="<?= htmlspecialchars((string) ($bpis_a['asset_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                    data-description="<?= htmlspecialchars((string) ($bpis_a['description_text'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                    data-property="<?= htmlspecialchars((string) ($bpis_a['property_number'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                    data-unit-value="<?= (float) ($bpis_a['unit_value'] ?? 0) ?>"
                    data-accumulated="<?= $bpis_acc ?>"
                    data-nbv="<?= $bpis_nbv ?>"
                ><?= htmlspecialchars($bpis_a['display_label'] ?? '', ENT_QUOTES, 'UTF-8') ?></option>
            <?php endforeach; ?>
        </select>

        <button type="button" class="bpis-asset-picker-trigger" aria-haspopup="listbox" aria-expanded="false">
            <img
                src="<?= htmlspecialchars($bpis_picker_selected ? (string) $bpis_picker_selected['photo_url'] : bpis_asset_placeholder_image_url(), ENT_QUOTES, 'UTF-8') ?>"
                alt=""
                class="bpis-asset-picker-thumb"
                data-picker-thumb
            >
            <span class="bpis-asset-picker-text">
                <span class="bpis-asset-picker-name" data-picker-name>
                    <?= $bpis_picker_selected
                        ? htmlspecialchars((string) $bpis_picker_selected['asset_name'], ENT_QUOTES, 'UTF-8')
                        : 'Select an asset…' ?>
                </span>
                <span class="bpis-asset-picker-desc" data-picker-desc>
                    <?php if ($bpis_picker_selected): ?>
                        <?= htmlspecialchars(
                            (string) ($bpis_picker_selected['description_text'] !== ''
                                ? $bpis_picker_selected['description_text']
                                : 'No description'),
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    <?php else: ?>
                        Choose from the list to see image and details
                    <?php endif; ?>
                </span>
                <span class="bpis-asset-picker-meta" data-picker-meta>
                    <?php if ($bpis_picker_selected && !empty($bpis_picker_selected['property_number'])): ?>
                        <?= htmlspecialchars((string) $bpis_picker_selected['property_number'], ENT_QUOTES, 'UTF-8') ?>
                    <?php endif; ?>
                </span>
            </span>
            <svg class="bpis-asset-picker-chevron" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>
        </button>

        <div class="bpis-asset-picker-panel" role="listbox">
            <div class="bpis-asset-picker-search-wrap">
                <input type="search" class="bpis-asset-picker-search" placeholder="Search by name or description…" autocomplete="off" data-picker-search>
            </div>
            <ul class="bpis-asset-picker-list custom-scrollbar" data-picker-list>
                <?php if (!$bpis_assets): ?>
                    <li class="bpis-asset-picker-empty">No assets registered yet.</li>
                <?php else: ?>
                    <?php foreach ($bpis_assets as $bpis_a):
                        $bpis_aid = (int) ($bpis_a['id'] ?? 0);
                        $bpis_is_sel = ($bpis_selected_asset_id > 0 && $bpis_aid === $bpis_selected_asset_id);
                        $bpis_desc = trim((string) ($bpis_a['description_text'] ?? ''));
                        $bpis_prop = trim((string) ($bpis_a['property_number'] ?? ''));
                    ?>
                        <li
                            class="bpis-asset-picker-option<?= $bpis_is_sel ? ' is-selected' : '' ?>"
                            role="option"
                            data-id="<?= $bpis_aid ?>"
                            data-photo="<?= htmlspecialchars((string) ($bpis_a['photo_url'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                            data-name="<?= htmlspecialchars((string) ($bpis_a['asset_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                            data-description="<?= htmlspecialchars($bpis_desc, ENT_QUOTES, 'UTF-8') ?>"
                            data-property="<?= htmlspecialchars($bpis_prop, ENT_QUOTES, 'UTF-8') ?>"
                            data-unit-value="<?= (float) ($bpis_a['unit_value'] ?? 0) ?>"
                            data-accumulated="<?= (float) ($bpis_a['accumulated_depreciation'] ?? 0) ?>"
                            data-nbv="<?= (float) ($bpis_a['net_book_value'] ?? $bpis_a['unit_value'] ?? 0) ?>"
                            aria-selected="<?= $bpis_is_sel ? 'true' : 'false' ?>"
                        >
                            <img src="<?= htmlspecialchars((string) ($bpis_a['photo_url'] ?? bpis_asset_placeholder_image_url()), ENT_QUOTES, 'UTF-8') ?>" alt="" class="bpis-asset-picker-thumb bpis-asset-picker-thumb-sm">
                            <span class="bpis-asset-picker-text">
                                <span class="bpis-asset-picker-name"><?= htmlspecialchars((string) ($bpis_a['asset_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="bpis-asset-picker-desc"><?= htmlspecialchars($bpis_desc !== '' ? $bpis_desc : '—', ENT_QUOTES, 'UTF-8') ?></span>
                                <?php if ($bpis_prop !== ''): ?>
                                    <span class="bpis-asset-picker-meta"><?= htmlspecialchars($bpis_prop, ENT_QUOTES, 'UTF-8') ?></span>
                                <?php endif; ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</div>

<style>
.bpis-asset-picker-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 10px;
    flex-wrap: wrap;
}

.bpis-asset-picker-header label {
    margin-bottom: 0 !important;
}

.bpis-asset-picker-toggle {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: none;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    padding: 4px 10px;
    font-size: 12px;
    font-weight: 500;
    color: #4b5563;
    cursor: pointer;
    transition: all 0.2s;
    flex-shrink: 0;
    height: 32px;
}

.bpis-asset-picker-toggle:hover {
    background: #f3f4f6;
    border-color: #9ca3af;
}

.bpis-asset-picker-toggle .bpis-asset-picker-chevron {
    transition: transform 0.3s ease;
}

.bpis-asset-picker-toggle[aria-expanded="true"] .bpis-asset-picker-chevron {
    transform: rotate(180deg);
}

.bpis-asset-picker-body {
    transition: all 0.3s ease;
    overflow: hidden;
}

.bpis-asset-picker-body.bpis-collapsed {
    display: none;
}

@media (max-width: 480px) {
    .bpis-asset-picker-header {
        flex-direction: column;
        align-items: stretch;
        gap: 6px;
    }
    .bpis-asset-picker-toggle {
        align-self: flex-start;
        font-size: 11px;
        padding: 3px 8px;
        height: 28px;
    }
}

@media (max-width: 360px) {
    .bpis-asset-picker-toggle {
        font-size: 10px;
        padding: 2px 6px;
        height: 24px;
    }
    .bpis-asset-picker-toggle svg {
        width: 12px;
        height: 12px;
    }
}
</style>

<script>
(function() {
    const picker = document.querySelector('#<?= htmlspecialchars($bpis_asset_picker_id, ENT_QUOTES, 'UTF-8') ?>');
    if (!picker) return;

    const toggleBtn = picker.querySelector('[data-picker-toggle]');
    const body = picker.querySelector('[data-picker-body]');

    if (!toggleBtn || !body) return;

    function togglePicker() {
        const isOpen = body.style.display !== 'none';
        body.style.display = isOpen ? 'none' : 'block';
        toggleBtn.setAttribute('aria-expanded', !isOpen);
        
        const chevron = toggleBtn.querySelector('.bpis-asset-picker-chevron');
        const label = toggleBtn.querySelector('.bpis-asset-picker-toggle-label');
        
        if (chevron) {
            const path = chevron.querySelector('path');
            if (path) {
                path.setAttribute('d', isOpen ? 'M6 9l6 6 6-6' : 'M18 15l-6-6-6 6');
            }
        }
        
        if (label) {
            label.textContent = isOpen ? 'Show' : 'Hide';
        }
    }

    toggleBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        togglePicker();
    });
})();
</script>

<?php
static $bpis_asset_picker_script_loaded = false;
if (!$bpis_asset_picker_script_loaded) {
    $bpis_asset_picker_script_loaded = true;
    echo '<script src="../js/asset_picker.js"></script>' . "\n";
}
?>