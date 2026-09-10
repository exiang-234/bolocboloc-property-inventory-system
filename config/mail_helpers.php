<?php

require_once __DIR__ . '/../src/Exception.php';
require_once __DIR__ . '/../src/PHPMailer.php';
require_once __DIR__ . '/../src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (!function_exists('bpis_send_html_mail')) {
    function bpis_send_html_mail(string $to_email, string $to_name, string $subject, string $html_message): bool
    {
        if ($to_email === '' || !filter_var($to_email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $mail_config = include __DIR__ . '/mail_config.php';

        // 1. Try sending via Brevo's HTTP API (highly recommended for production/InfinityFree to bypass SMTP port blocks)
        if (isset($mail_config['host']) && str_contains(strtolower($mail_config['host']), 'brevo.com') && !empty($mail_config['password'])) {
            $api_key = $mail_config['password'];
            $sender_email = $mail_config['from_email'] ?? ($mail_config['username'] ?? '');
            $sender_name = $mail_config['from_name'] ?? 'Barangay Bolocboloc Property Inventory System';

            if (!empty($api_key) && !empty($sender_email)) {
                $payload = [
                    'sender' => [
                        'name'  => $sender_name,
                        'email' => $sender_email,
                    ],
                    'to' => [
                        [
                            'email' => $to_email,
                            'name'  => $to_name !== '' ? $to_name : $to_email,
                        ]
                    ],
                    'subject'     => $subject,
                    'htmlContent' => $html_message,
                ];

                $curl = curl_init();
                curl_setopt_array($curl, [
                    CURLOPT_URL            => 'https://api.brevo.com/v3/smtp/email',
                    CURLOPT_POST           => true,
                    CURLOPT_POSTFIELDS     => json_encode($payload),
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT        => 15,
                    CURLOPT_HTTPHEADER     => [
                        'accept: application/json',
                        'api-key: ' . $api_key,
                        'content-type: application/json',
                    ]
                ]);

                $response = curl_exec($curl);
                $err = curl_error($curl);
                $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
                curl_close($curl);

                if (!$err && $http_code >= 200 && $http_code < 300) {
                    return true; // Sent successfully via HTTP API!
                }

                error_log('Brevo API mail failed (HTTP ' . $http_code . '): ' . ($err ?: $response));
            }
        }

        // 2. Fallback to standard SMTP (local or non-blocked environments)
        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = $mail_config['host'] ?? '';
            $mail->SMTPAuth = true;
            $mail->Username = $mail_config['username'] ?? '';
            $mail->Password = $mail_config['password'] ?? '';
            $mail->Port = (int) ($mail_config['port'] ?? 587);
            $mail->SMTPSecure = (($mail_config['encryption'] ?? 'tls') === 'ssl')
                ? PHPMailer::ENCRYPTION_SMTPS
                : PHPMailer::ENCRYPTION_STARTTLS;

            // Bypass SSL certificate verification (required for InfinityFree hosting)
            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer'       => false,
                    'verify_peer_name'  => false,
                    'allow_self_signed' => true,
                ]
            ];

            $mail->setFrom(
                $mail_config['from_email'] ?? ($mail_config['username'] ?? 'no-reply@example.com'),
                $mail_config['from_name'] ?? 'Barangay Bolocboloc Property Inventory System'
            );
            $mail->addAddress($to_email, $to_name !== '' ? $to_name : $to_email);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $html_message;
            $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $html_message));

            return $mail->send();
        } catch (Exception $e) {
            error_log('SMTP mail failed: ' . $e->getMessage());
            return false;
        }
    }
}
