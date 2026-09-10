<?php
// Localhost mail config — fill with your own SMTP/Brevo credentials when needed.
// For local testing without email, you can leave host/username/password empty —
// bpis_send_html_mail() will fail gracefully and log the error.
return [
    'host'       => 'smtp.gmail.com', // e.g. smtp-relay.brevo.com or localhost
    'username'   => '', // your SMTP username
    'password'   => '', // your SMTP password / Brevo api-key
    'api_key'    => '', // Brevo api-key if using Brevo

    'port'       => 587,
    'encryption' => 'tls',

    'from_email' => 'noreply@localhost',
    'from_name'  => 'Barangay Bolocboloc Request Portal (Local)',
];