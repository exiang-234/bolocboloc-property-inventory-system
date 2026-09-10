<?php

/** COA-aligned labels used across dashboards and reports. */
if (!function_exists('bpis_label_unit_cost_value')) {
    function bpis_label_unit_cost_value(): string
    {
        return 'Unit Cost/Value';
    }
}

if (!function_exists('bpis_label_required_mark')) {
    function bpis_label_required_mark(): string
    {
        return '<span class="text-red-600" aria-hidden="true">*</span>';
    }
}

if (!function_exists('bpis_asset_clusters')) {
    function bpis_asset_clusters(): array
    {
        return ['Fixed Assets', 'Movable Assets'];
    }
}

if (!function_exists('bpis_acquisition_modes')) {
    function bpis_acquisition_modes(): array
    {
        return ['Purchased', 'Donation', 'Transfer', 'Other'];
    }
}

if (!function_exists('bpis_map_category_to_cluster')) {
    /** COA-style default cluster from legacy category. */
    function bpis_map_category_to_cluster(?string $category): string
    {
        $cat = strtolower(trim((string) $category));
        $fixed = ['land', 'buildings', 'building'];
        foreach ($fixed as $f) {
            if ($cat !== '' && strpos($cat, $f) !== false) {
                return 'Fixed Assets';
            }
        }
        return 'Movable Assets';
    }
}
