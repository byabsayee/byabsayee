<?php
/**
 * Email template: Contact Us — sent to the support inbox
 *
 * Variables:
 *   $senderName   — user's name
 *   $senderEmail  — user's email
 *   $subject      — selected subject
 *   $message      — message body
 *   $appName      — e.g. "Byabsayee"
 *   $appUrl       — base URL
 *   $userId       — logged-in user ID (for reference)
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
<title>New support message</title>
</head>
<body style="margin:0;padding:0;background:#f4f6fb;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6fb;padding:32px 16px">
<tr><td align="center">
  <table width="100%" style="max-width:560px;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.07)">

    <!-- Header -->
    <tr>
      <td style="background:<?= $brand ?>;padding:24px 36px">
        <p style="margin:0;font-size:18px;font-weight:700;color:#fff"><?= htmlspecialchars($appName) ?> · New Support Message</p>
        <p style="margin:4px 0 0;font-size:12px;color:rgba(255,255,255,.75)">Submitted via the in-app Contact Us form</p>
      </td>
    </tr>

    <!-- Meta row -->
    <tr>
      <td style="padding:24px 36px 0">
        <table width="100%" cellpadding="0" cellspacing="0" style="background:#f8fafc;border-radius:8px;padding:16px">
          <tr>
            <td style="font-size:13px;color:#64748b;padding:4px 0"><strong style="color:#1e293b">From:</strong> <?= htmlspecialchars($senderName) ?> &lt;<?= htmlspecialchars($senderEmail) ?>&gt;</td>
          </tr>
          <tr>
            <td style="font-size:13px;color:#64748b;padding:4px 0"><strong style="color:#1e293b">Subject:</strong> <?= htmlspecialchars($subject) ?></td>
          </tr>
          <tr>
            <td style="font-size:13px;color:#64748b;padding:4px 0"><strong style="color:#1e293b">User ID:</strong> #<?= (int)($userId ?? 0) ?></td>
          </tr>
          <tr>
            <td style="font-size:13px;color:#64748b;padding:4px 0"><strong style="color:#1e293b">Sent at:</strong> <?= date('d M Y, H:i') ?> (server time)</td>
          </tr>
        </table>
      </td>
    </tr>

    <!-- Message -->
    <tr>
      <td style="padding:24px 36px">
        <p style="margin:0 0 10px;font-size:13px;font-weight:600;color:#1e293b;text-transform:uppercase;letter-spacing:.5px">Message</p>
        <div style="font-size:14px;color:#334155;line-height:1.75;background:#f8fafc;border-left:3px solid <?= $brand ?>;padding:16px 20px;border-radius:0 8px 8px 0">
          <?= nl2br(htmlspecialchars($message)) ?>
        </div>
      </td>
    </tr>

    <!-- Reply hint -->
    <tr>
      <td style="padding:0 36px 28px">
        <p style="margin:0;font-size:12px;color:#94a3b8">
          Reply directly to this email to respond to <strong><?= htmlspecialchars($senderName) ?></strong> at <?= htmlspecialchars($senderEmail) ?>.
        </p>
      </td>
    </tr>

    <tr><td style="padding:0 36px"><hr style="border:none;border-top:1px solid #e2e8f0;margin:0"></td></tr>
    <tr>
      <td style="padding:16px 36px;text-align:center">
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
