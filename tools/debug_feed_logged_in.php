<?php
require_once __DIR__ . '/_cli_only.php';
require_once __DIR__ . '/../config/bpis_session.php';
bpis_start_session();
$_SESSION['admin_id'] = 11;
$_SESSION['role'] = 'Secretary';
require __DIR__ . '/../notifications_feed.php';
