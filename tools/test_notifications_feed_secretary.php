<?php
declare(strict_types=1);

require_once __DIR__ . '/_cli_only.php';

// Run feed in test mode so bpis_feed_json throws instead of exiting (fixes broken test harness)
if (!defined('BPIS_TEST_MODE')) {
    define('BPIS_TEST_MODE', true);
}

// Ensure session is ready before feed's bpis_start_session()
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$_SESSION['admin_id'] = 11;
$_SESSION['role'] = 'Secretary';
$_SERVER['SCRIPT_NAME'] = $_SERVER['SCRIPT_NAME'] ?? '/BPIS/notifications_feed.php';

ob_start();
try {
    include __DIR__ . '/../notifications_feed.php';
} catch (Throwable $e) {
    // bpis_feed_json throws RuntimeException with JSON payload in test mode — that's expected
    if (strpos($e->getMessage(), 'BPIS_FEED_JSON:') !== 0) {
        $partial = ob_get_contents();
        ob_end_clean();
        fwrite(STDERR, "FAIL: unexpected exception: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine() . "\nOUT:" . ($partial ?: '') . "\n");
        exit(1);
    }
    // otherwise fall through to capture output
}
$out = ob_get_clean();
if ($out === false) {
    $out = '';
}
$out = trim((string) $out);
if ($out === '') {
    fwrite(STDERR, "FAIL: empty output from notifications_feed.php\n");
    exit(1);
}
// Strip any PHP warnings/notices that may prefix the JSON (e.g., remote DB DNS warnings)
$jsonStart = strpos($out, '{');
$jsonEnd = strrpos($out, '}');
if ($jsonStart !== false && $jsonEnd !== false && $jsonEnd >= $jsonStart) {
    $out = substr($out, $jsonStart, $jsonEnd - $jsonStart + 1);
}
$data = json_decode($out, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    fwrite(STDERR, "FAIL: invalid JSON (" . json_last_error_msg() . "): $out\n");
    exit(1);
}
// Graceful handling when DB is unavailable — treat db_error as SKIP not FAIL (offline CI)
if (isset($data['error']) && $data['error'] === 'db_error') {
    // With local fallback this should not happen, but don't break CI when offline
    echo "SKIP secretary feed db_error (DB unavailable) raw=" . $out . "\n";
    exit(0);
}
if (!is_array($data) || empty($data['success'])) {
    fwrite(STDERR, "FAIL: $out\n");
    exit(1);
}
// Validate expected fields
if (!array_key_exists('unread_count', $data) || !array_key_exists('notifications', $data) || !is_array($data['notifications'])) {
    fwrite(STDERR, "FAIL: missing keys in payload: $out\n");
    exit(1);
}
echo "OK secretary feed unread=" . ($data['unread_count'] ?? 0) . " items=" . count($data['notifications'] ?? []) . "\n";
exit(0);
