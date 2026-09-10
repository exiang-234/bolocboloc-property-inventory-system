<?php

if (empty($scan_asset_row)) {
    return;
}
$row = $scan_asset_row;
?>
<div class="mb-4 border border-gray-300 rounded-xl overflow-hidden shadow-sm bg-white w-full">
    <?php if (!empty($row['unit_tag']) && ($row['unit_tag'] !== ($row['property_number'] ?? ''))): ?>
        <div class="px-4 py-2 bg-blue-50 border-b border-blue-100 text-xs text-blue-800">
            <strong>Scanned unit tag:</strong> <?= htmlspecialchars($row['unit_tag']) ?>
        </div>
    <?php endif; ?>
    <div class="qr-scan-table-wrap overflow-x-auto custom-scrollbar">
        <table class="w-full min-w-max text-left border-collapse whitespace-nowrap text-[12px]">
            <thead>
                <tr class="bg-gray-50">
                    <th class="py-3 px-4 font-semibold text-gray-500 text-[11px] uppercase border-b border-gray-200 text-center">Image</th>
                    <th class="py-3 px-4 font-semibold text-gray-500 text-[11px] uppercase border-b border-gray-200">Article</th>
                    <th class="py-3 px-4 font-semibold text-gray-500 text-[11px] uppercase border-b border-gray-200">Description</th>
                    <th class="py-3 px-4 font-semibold text-gray-500 text-[11px] uppercase border-b border-gray-200">Category</th>
                    <th class="py-3 px-4 font-semibold text-gray-500 text-[11px] uppercase border-b border-gray-200">Cluster</th>
                    <th class="py-3 px-4 font-semibold text-gray-500 text-[11px] uppercase border-b border-gray-200">Location</th>
                    <th class="py-3 px-4 font-semibold text-gray-500 text-[11px] uppercase border-b border-gray-200">Property Number</th>
                    <th class="py-3 px-4 font-semibold text-gray-500 text-[11px] uppercase border-b border-gray-200">UOM</th>
                    <th class="py-3 px-4 font-semibold text-gray-500 text-[11px] uppercase border-b border-gray-200">Date Acquired</th>
                    <th class="py-3 px-4 font-semibold text-gray-500 text-[11px] uppercase border-b border-gray-200"><?= htmlspecialchars($row['unit_value_label'] ?? 'Unit Cost/Value') ?></th>
                    <th class="py-3 px-4 font-semibold text-gray-500 text-[11px] uppercase border-b border-gray-200 text-center">Remarks</th>
                </tr>
            </thead>
            <tbody>
                <tr class="bg-white border-b border-gray-100">
                    <td class="py-3 px-4 text-center bpis-asset-photo-cell"><?= $row['photo_html'] ?? '' ?></td>
                    <td class="py-3 px-4 font-bold text-gray-900"><?= htmlspecialchars($row['article']) ?></td>
                    <td class="py-3 px-4 text-gray-800"><?= htmlspecialchars($row['description']) ?></td>
                    <td class="py-3 px-4 text-gray-800"><?= htmlspecialchars($row['category']) ?></td>
                    <td class="py-3 px-4 text-gray-800 text-xs"><?= htmlspecialchars($row['asset_cluster']) ?></td>
                    <td class="py-3 px-4 text-gray-800 text-xs max-w-[160px] truncate" title="<?= htmlspecialchars($row['location']) ?>"><?= htmlspecialchars($row['location']) ?></td>
                    <td class="py-3 px-4 text-gray-800 font-medium">
                        <?= htmlspecialchars($row['property_number']) ?>
                        <?php if (!empty($row['unit_tag'])): ?>
                            <span class="block text-[10px] text-gray-500 font-normal"><?= htmlspecialchars($row['unit_tag']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="py-3 px-4 text-gray-800"><?= htmlspecialchars($row['uom']) ?></td>
                    <td class="py-3 px-4 text-gray-800"><?= htmlspecialchars($row['date_acquired']) ?></td>
                    <td class="py-3 px-4 text-gray-800">₱ <?= htmlspecialchars($row['unit_value']) ?></td>
                    <td class="py-3 px-4 text-center font-medium <?= htmlspecialchars($row['remarks_class'] ?? 'text-gray-800') ?>"><?= htmlspecialchars($row['remarks']) ?></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
