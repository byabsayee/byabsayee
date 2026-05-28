<?php
/**
 * Email template: Password reset link
 *
 * Variables:
 *   $name    — recipient's name
 *   $link    — full reset URL (expires in 1 hour)
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
<title>Reset your password</title>
</head>
<body style="margin:0;padding:0;background:#f4f6fb;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6fb;padding:32px 16px">
<tr><td align="center">
  <table width="100%" style="max-width:520px;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.07)">

    <!-- Header -->
    <tr>
      <td style="background:<?= $brand ?>;padding:28px 36px;text-align:center">
        <p style="margin:0;font-size:20px;font-weight:700;color:#fff;letter-spacing:-0.3px"><?= htmlspecialchars($appName) ?></p>
        <p style="margin:6px 0 0;font-size:12px;color:rgba(255,255,255,.7)">Password Reset</p>
      </td>
    </tr>

    <!-- Body -->
    <tr>
      <td style="padding:36px 36px 28px">
        <p style="margin:0 0 8px;font-size:18px;font-weight:600;color:#1e293b">Forgot your password?</p>
        <p style="margin:0 0 20px;font-size:14px;color:#475569;line-height:1.7">
          Hi <?= htmlspecialchars($name) ?>,<br>
          We received a request to reset the password for your <?= htmlspecialchars($appName) ?> account.
          Click the button below to choose a new password.
        </p>

        <!-- CTA -->
        <table width="100%" cellpadding="0" cellspacing="0">
          <tr><td align="center">
            <a href="<?= htmlspecialchars($link) ?>"
               style="display:inline-block;background:<?= $brand ?>;color:#fff;font-size:15px;font-weight:600;text-decoration:none;padding:13px 32px;border-radius:8px">
              Reset Password →
            </a>
          </td></tr>
        </table>

        <p style="margin:24px 0 0;font-size:13px;color:#64748b;line-height:1.6">
          ⏱ This link <strong>expires in 1 hour</strong>. If you didn't request a password reset, you can safely ignore this email — your password won't change.
        </p>

        <p style="margin:20px 0 0;font-size:12px;color:#94a3b8;line-height:1.6">
          If the button doesn't work, copy and paste this link into your browser:<br>
          <a href="<?= htmlspecialchars($link) ?>" style="color:<?= $brand ?>;word-break:break-all"><?= htmlspecialchars($link) ?></a>
        </p>
      </td>
    </tr>

    <tr><td style="padding:0 36px"><hr style="border:none;border-top:1px solid #e2e8f0;margin:0"></td></tr>

    <!-- Footer -->
    <tr>
      <td style="padding:20px 36px;text-align:center">
        <p style="margin:0;font-size:11px;color:#94a3b8">
          Sent by <?= htmlspecialchars($appName) ?> ·
          <a href="<?= htmlspecialchars($appUrl) ?>" style="color:<?= $brand ?>"><?= htmlspecialchars($appUrl) ?></a>
        </p>
      </td>
    </tr>

  </table>
</td></tr>
</table>
</body>
</html>
