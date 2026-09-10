<?php
/**
 * Copy to oauth_config.php and set Google Cloud OAuth 2.0 credentials.
 * Enable "Google Identity" / OAuth client (Web application).
 * Authorized redirect URI: http://localhost/BPIS/auth/google_callback.php
 */
return [
    'enabled' => false,
    'client_id' => 'YOUR_GOOGLE_CLIENT_ID.apps.googleusercontent.com',
    'client_secret' => 'YOUR_GOOGLE_CLIENT_SECRET',
    'redirect_uri' => 'http://localhost/BPIS/auth/google_callback.php',
];
