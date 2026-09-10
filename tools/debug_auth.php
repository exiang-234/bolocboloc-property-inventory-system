<?php
require_once __DIR__ . '/_cli_only.php';
session_start();
$_SESSION['admin_id'] = 11;
$_SESSION['role'] = 'Secretary';
var_dump($_SESSION);
$_SERVER['SCRIPT_NAME'] = '/BPIS/notifications_feed.php';
require_once __DIR__ . '/../config/auth_helpers.php';
bpis_require_login_json(['Secretary', 'Treasurer', 'Barangay Captain']);
echo "PASSED AUTH\n";
