<?php
require_once __DIR__ . '/_cli_only.php';
$_SERVER['SCRIPT_NAME'] = '/BPIS/notifications_feed.php';
ob_start();
include __DIR__ . '/../notifications_feed.php';
$out = ob_get_clean();
echo "LENGTH=" . strlen($out) . "\n";
echo "HEX_START=" . bin2hex(substr($out, 0, 40)) . "\n";
echo "BODY=" . $out . "\n";
$j = json_decode($out, true);
echo "JSON_OK=" . (is_array($j) && !empty($j['success']) ? 'yes' : 'no') . "\n";
if (is_array($j)) {
    echo "unread=" . ($j['unread_count'] ?? '?') . " count=" . count($j['notifications'] ?? []) . "\n";
}
