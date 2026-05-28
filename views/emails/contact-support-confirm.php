<?php
/**
 * Email template: Contact Us — confirmation sent to the user who submitted
 *
 * Variables:
 *   $name    — user's name
 *   $subject — subject they chose
 *   $message — their message (shown back for reference)
 *   $appName — e.g. "Byabsayee"
 *   $appUrl  — base URL
 */
$appName = $appName ?? 'Byabsayee';
$appUrl  = $appUrl  ?? (getenv('APP_URL') ?: 'https://byabsayee.com');
$brand   = '#1a6b4a';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>We received your message</title>
</head>
<body style="margin:0;padding:0;background:#f4f6fb;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6fb;padding:32px 16px">
<tr><td align="center">
  <table width="100%" style="max-width:520px;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.07)">

    <!-- Header -->
    <tr>
      <td style="background:<?= $brand ?>;padding:28px 36px;text-align:center">
        <p style="margin:0;font-size:20px;font-weight:700;color:#fff"><?= htmlspecialchars($appName) ?></p>
        <p style="margin:6px 0 0;font-size:12px;color:rgba(255,255,255,.7)">Support</p>
      </td>
    </tr>

    <!-- Body -->
    <tr>
      <td style="padding:32px 36px 24px">
        <p style="margin:0 0 16px;font-size:18px;font-weight:600;color:#1e293b">We got your message ✅</p>
        <p style="margin:0 0 20px;font-size:14px;color:#475569;line-height:1.75">
          Hi <?= htmlspecialchars($name) ?>, thanks for reaching out!
          We've received your message and our team will get back to you as soon as possible, typically within 1–2 business days.
        </p>

        <!-- Their message for reference -->
        <p style="margin:0 0 8px;font-size:12px;font-weight:600;color:#94a3b8;text-transform:uppercase;letter-spacing:.5px">Your message</p>
        <table width="100%" cellpadding="0" cellspacing="0" style="background:#f8fafc;border-radius:8px;margin-bottom:20px">
          <tr>
            <td style="padding:14px 18px">
              <p style="margin:0 0 6px;font-size:13px;color:#64748b"><strong style="color:#1e293b">Subject:</strong> <?= htmlspecialchars($subject) ?></p>
              <p style="margin:0;font-size:13px;color:#475569;line-height:1.7"><?= nl2br(htmlspecialchars($message)) ?></p>
            </td>
          </tr>
        </table>

        <p style="margin:0;font-size:13px;color:#64748b;line-height:1.6">
          If your issue is urgent, you can also reach us on WhatsApp or reply to this email.
        </p>
      </td>
    </tr>

    <tr><td style="padding:0 36px"><hr style="border:none;border-top:1px solid #e2e8f0;margin:0"></td></tr>

    <!-- Footer -->
    <tr>
      <td style="padding:20px 36px;text-align:center">
        <p style="margin:0;font-size:11px;color:#94a3b8">
          <?= htmlspecialchars($appName) ?> ·
          <a href="<?= htmlspecialchars($appUrl) ?>" style="color:<?= $brand ?>"><?= htmlspecialchars($appUrl) ?></a>
        </p>
      </td>
    </tr>

  </table>
</td></tr>
</table>
</body>
</html>
