<?php
require_once __DIR__ . '/_cli_only.php';

$_SERVER['SCRIPT_NAME'] = '/BPIS/notifications_feed.php';
$_COOKIE = [];

session_id('bpistest123');
session_start();
$_SESSION['admin_id'] = 11;
$_SESSION['role'] = 'Secretary';
session_write_close();

unset($_SESSION);
session_id('bpistest123');
ob_start();
include __DIR__ . '/../notifications_feed.php';
$out = ob_get_clean();
echo "OUT:\n" . $out . "\n";
$j = json_decode($out, true);
echo "JSON_OK=" . (is_array($j) && !empty($j['success']) ? 'yes unread=' . ($j['unread_count'] ?? 0) : 'no err=' . json_last_error_msg()) . "\n";
