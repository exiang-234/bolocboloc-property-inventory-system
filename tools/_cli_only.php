<?php

/**
 * Guard for developer/diagnostic scripts.
 *
 * Everything in tools/ is a maintenance script: several of them seed a
 * privileged session or dump database contents, so they must never be
 * reachable over HTTP. Require this file FIRST in every tools/ script.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
