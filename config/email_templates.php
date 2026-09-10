<?php

/**
 * HTML fragments for transactional mail (table layout for Gmail / mobile).
 */

function bpis_mail_esc(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

/**
 * Approval email with OTP for claiming items at the barangay office.
 */
function bpis_otp_approval_email_html(string $borrower_name, string $otp, string $item_summary = ''): string
{
    $name = bpis_mail_esc($borrower_name);
    $code = preg_replace('/\D/', '', $otp);
    if (strlen($code) !== 6) {
        $code = bpis_mail_esc($otp);
        $code_spaced = $code;
    } else {
        $code_spaced = bpis_mail_esc(substr($code, 0, 3) . ' ' . substr($code, 3, 3));
    }
    $item_block = '';
    if ($item_summary !== '') {
        $item = bpis_mail_esc($item_summary);
        $item_block = <<<HTML
<tr>
  <td style="padding:0 32px 20px 32px;font-family:Segoe UI,Roboto,Helvetica,Arial,sans-serif;font-size:15px;line-height:1.55;color:#334155;">
    <strong style="color:#0f172a;">Requested item</strong><br>
    <span style="color:#475569;">{$item}</span>
  </td>
</tr>
HTML;
    }

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Request approved</title>
</head>
<body style="margin:0;padding:0;background-color:#e8eef5;">
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#e8eef5;padding:24px 12px;">
    <tr>
      <td align="center">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:560px;background-color:#ffffff;border-radius:14px;overflow:hidden;box-shadow:0 12px 40px rgba(15,63,105,0.12);">
          <tr>
            <td style="background-color:#0f3f69;background:linear-gradient(135deg,#0f3f69 0%,#1d5f94 100%);padding:28px 32px;">
              <p style="margin:0;font-family:Segoe UI,Roboto,Helvetica,Arial,sans-serif;font-size:13px;font-weight:600;letter-spacing:0.12em;text-transform:uppercase;color:rgba(255,255,255,0.85);">Barangay borrowing</p>
              <h1 style="margin:10px 0 0 0;font-family:Segoe UI,Roboto,Helvetica,Arial,sans-serif;font-size:22px;font-weight:700;line-height:1.25;color:#ffffff;">Your request is approved</h1>
            </td>
          </tr>
          <tr>
            <td style="padding:28px 32px 8px 32px;font-family:Segoe UI,Roboto,Helvetica,Arial,sans-serif;font-size:16px;line-height:1.6;color:#1e293b;">
              Hi <strong style="color:#0f172a;">{$name}</strong>,
            </td>
          </tr>
          <tr>
            <td style="padding:0 32px 20px 32px;font-family:Segoe UI,Roboto,Helvetica,Arial,sans-serif;font-size:15px;line-height:1.6;color:#475569;">
              Good news — your borrowing request has been <strong style="color:#15803d;">approved</strong>. Use the verification code below when you visit the office to pick up your item.
            </td>
          </tr>
          {$item_block}
          <tr>
            <td style="padding:8px 32px 28px 32px;" align="center">
              <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="width:100%;max-width:320px;background-color:#f0f7ff;border:1px solid #bfdbfe;border-radius:12px;">
                <tr>
                  <td style="padding:18px 16px;text-align:center;font-family:Consolas,ui-monospace,monospace;font-size:32px;font-weight:700;letter-spacing:0.28em;color:#0f3f69;">
                    {$code_spaced}
                  </td>
                </tr>
                <tr>
                  <td style="padding:0 16px 16px 16px;text-align:center;font-family:Segoe UI,Roboto,Helvetica,Arial,sans-serif;font-size:12px;line-height:1.45;color:#64748b;">
                    One-time code · valid for pickup at the barangay office
                  </td>
                </tr>
              </table>
            </td>
          </tr>
          <tr>
            <td style="padding:0 32px 24px 32px;font-family:Segoe UI,Roboto,Helvetica,Arial,sans-serif;font-size:14px;line-height:1.55;color:#64748b;">
              Present this code to the staff. If you did not request a borrowing, you can ignore this message.
            </td>
          </tr>
          <tr>
            <td style="padding:16px 32px 24px 32px;border-top:1px solid #e2e8f0;background-color:#f8fafc;">
              <p style="margin:0;font-family:Segoe UI,Roboto,Helvetica,Arial,sans-serif;font-size:12px;line-height:1.5;color:#94a3b8;text-align:center;">
                This is an automated message from the Barangay Request Portal.<br>Please do not reply to this email.
              </p>
            </td>
          </tr>
        </table>
        <p style="margin:16px 0 0 0;font-family:Segoe UI,Roboto,Helvetica,Arial,sans-serif;font-size:11px;color:#94a3b8;text-align:center;">
          Brgy. Bolocboloc · Sibulan, Negros Oriental
        </p>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;
}

/**
 * Plain rejection notice (optional visual parity with approval mail).
 */
function bpis_request_rejected_email_html(string $borrower_name): string
{
    $name = bpis_mail_esc($borrower_name);

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Request update</title>
</head>
<body style="margin:0;padding:0;background-color:#e8eef5;">
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#e8eef5;padding:24px 12px;">
    <tr>
      <td align="center">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:560px;background-color:#ffffff;border-radius:14px;overflow:hidden;box-shadow:0 12px 40px rgba(15,63,105,0.12);">
          <tr>
            <td style="background-color:#64748b;background:linear-gradient(135deg,#64748b 0%,#475569 100%);padding:28px 32px;">
              <p style="margin:0;font-family:Segoe UI,Roboto,Helvetica,Arial,sans-serif;font-size:13px;font-weight:600;letter-spacing:0.12em;text-transform:uppercase;color:rgba(255,255,255,0.88);">Barangay borrowing</p>
              <h1 style="margin:10px 0 0 0;font-family:Segoe UI,Roboto,Helvetica,Arial,sans-serif;font-size:22px;font-weight:700;line-height:1.25;color:#ffffff;">Request update</h1>
            </td>
          </tr>
          <tr>
            <td style="padding:28px 32px;font-family:Segoe UI,Roboto,Helvetica,Arial,sans-serif;font-size:16px;line-height:1.65;color:#334155;">
              Hi <strong style="color:#0f172a;">{$name}</strong>,<br><br>
              We regret to inform you that your borrowing request could not be approved at this time. If you have questions, please visit or contact the barangay office.
            </td>
          </tr>
          <tr>
            <td style="padding:16px 32px 24px 32px;border-top:1px solid #e2e8f0;background-color:#f8fafc;">
              <p style="margin:0;font-family:Segoe UI,Roboto,Helvetica,Arial,sans-serif;font-size:12px;line-height:1.5;color:#94a3b8;text-align:center;">
                This is an automated message. Please do not reply to this email.
              </p>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;
}

/**
 * Admin registration — verify Gmail before login is allowed.
 */
function bpis_registration_verify_email_html(string $admin_name, string $verify_url): string
{
    $name = bpis_mail_esc($admin_name);
    $url = bpis_mail_esc($verify_url);

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Verify your email</title>
</head>
<body style="margin:0;padding:0;background-color:#e8eef5;">
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#e8eef5;padding:24px 12px;">
    <tr>
      <td align="center">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:560px;background-color:#ffffff;border-radius:14px;overflow:hidden;box-shadow:0 12px 40px rgba(15,63,105,0.12);">
          <tr>
            <td style="background-color:#0f3f69;background:linear-gradient(135deg,#0f3f69 0%,#1d5f94 100%);padding:28px 32px;">
              <p style="margin:0;font-family:Segoe UI,Roboto,Helvetica,Arial,sans-serif;font-size:13px;font-weight:600;letter-spacing:0.12em;text-transform:uppercase;color:rgba(255,255,255,0.85);">BPIS Admin</p>
              <h1 style="margin:10px 0 0 0;font-family:Segoe UI,Roboto,Helvetica,Arial,sans-serif;font-size:22px;font-weight:700;line-height:1.25;color:#ffffff;">Verify your Gmail</h1>
            </td>
          </tr>
          <tr>
            <td style="padding:28px 32px 12px 32px;font-family:Segoe UI,Roboto,Helvetica,Arial,sans-serif;font-size:16px;line-height:1.6;color:#1e293b;">
              Hi <strong style="color:#0f172a;">{$name}</strong>,
            </td>
          </tr>
          <tr>
            <td style="padding:0 32px 24px 32px;font-family:Segoe UI,Roboto,Helvetica,Arial,sans-serif;font-size:15px;line-height:1.6;color:#475569;">
              Thanks for registering with the Barangay Property Inventory System. Please confirm your Gmail address to activate your admin account.
            </td>
          </tr>
          <tr>
            <td style="padding:0 32px 28px 32px;" align="center">
              <a href="{$url}" style="display:inline-block;background:linear-gradient(90deg,#2563eb,#3b82f6);color:#ffffff;text-decoration:none;font-family:Segoe UI,Roboto,Helvetica,Arial,sans-serif;font-size:16px;font-weight:700;padding:14px 28px;border-radius:12px;">Verify email address</a>
            </td>
          </tr>
          <tr>
            <td style="padding:0 32px 24px 32px;font-family:Segoe UI,Roboto,Helvetica,Arial,sans-serif;font-size:13px;line-height:1.55;color:#64748b;">
              This link expires in 24 hours. If the button does not work, copy and paste this URL into your browser:<br>
              <span style="word-break:break-all;color:#2563eb;">{$url}</span>
            </td>
          </tr>
          <tr>
            <td style="padding:16px 32px 24px 32px;border-top:1px solid #e2e8f0;background-color:#f8fafc;">
              <p style="margin:0;font-family:Segoe UI,Roboto,Helvetica,Arial,sans-serif;font-size:12px;line-height:1.5;color:#94a3b8;text-align:center;">
                If you did not register for BPIS, you can ignore this email.
              </p>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;
}
