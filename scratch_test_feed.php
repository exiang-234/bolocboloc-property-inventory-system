<?php

/**
 * Diagnostic harness for notifications_feed.php.
 *
 * This seeds a Secretary session, so it must never be reachable over HTTP —
 * run it from the command line only:
 *
 *     php scratch_test_feed.php
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

ini_set('display_errors', 1);
ini_set('log_errors', 0);
error_reporting(E_ALL);

session_start();
$_SESSION['admin_id'] = 11;
$_SESSION['role'] = 'Secretary';

try {
    include __DIR__ . '/notifications_feed.php';
} catch (Throwable $t) {
    echo "EXCEPT: " . $t->getMessage() . " in " . $t->getFile() . " on line " . $t->getLine() . "\n";
}
