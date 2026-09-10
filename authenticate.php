<?php

/**
 * Legacy sign-in endpoint.
 *
 * Superseded by login.php. The old implementation queried a `users` table that
 * no longer exists (accounts live in `admin`) and hashed nothing, so every
 * request here ended in a fatal error. Kept only so stale bookmarks and cached
 * form actions land on the real sign-in page instead of a crash.
 */
require_once __DIR__ . '/config/auth_helpers.php';

header('Location: ' . bpis_login_url());
exit;
