<?php

session_start();
require_once __DIR__ . '/../config/auth_helpers.php';
bpis_require_login(['Secretary', 'Barangay Captain']);
header('Location: requests.php');
exit;
