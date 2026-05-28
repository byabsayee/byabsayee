<?php
/**
 * Email template: Invitation to join a book
 *
 * Variables available:
 *   $inviterName   — name of person who sent the invite
 *   $bookName      — business/book name
 *   $designation   — role/designation (may be empty)
 *   $inviteLink    — full URL to the accept/reject page
 *   $appName       — app name (Byabsayee)
 *   $appUrl        — base URL of the app
 *   $expiryDate    — human-readable expiry date
 */

$appName  = $appName  ?? 'Byabsayee';
$appUrl   = $appUrl   ?? (getenv('APP_URL') ?: 'https://byabsayee.com');
$brand    = '#4F7CFF';
$bg       = '#f4f6fb';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>You've been invited to <?= htmlspecialchars($bookName) ?></title>
</head>
<body style="margin:0;padding:0;background:<?= $bg ?>;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif">

<table width="100%" cellpadding="0" cellspacing="0" style="background:<?= $bg ?>;padding:32px 16px">
<tr><td align="center">

    <!-- Card -->
    <table width="100%" style="max-width:520px;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.07)">

        <!-- Header -->
        <tr>
            <td style="background:<?= $brand ?>;padding:28px 36px;text-align:center">
                <p style="margin:0;font-size:22px;font-weight:700;color:#ffffff;letter-spacing:-0.3px">
                    <?= htmlspecialchars($appName) ?>
                </p>
                <p style="margin:6px 0 0;font-size:13px;color:rgba(255,255,255,.75)">
                    Smart business books
                </p>
            </td>
        </tr>

        <!-- Body -->
        <tr>
            <td style="padding:36px 36px 28px">
                <p style="margin:0 0 20px;font-size:18px;font-weight:600;color:#1e293b">
                    You've been invited! 🎉
                </p>

                <p style="margin:0 0 16px;font-size:15px;color:#475569;line-height:1.6">
                    <strong><?= htmlspecialchars($inviterName) ?></strong> has invited you to join
                    <strong>"<?= htmlspecialchars($bookName) ?>"</strong>
                    <?php if (!empty($designation)): ?>
                    as <strong><?= htmlspecialchars($designation) ?></strong>
                    <?php endif; ?>
                    on <?= htmlspecialchars($appName) ?>.
                </p>

                <p style="margin:0 0 28px;font-size:14px;color:#64748b;line-height:1.6">
                    Click the button below to view your invitation and choose to accept or decline.
                    This invitation expires on <strong><?= htmlspecialchars($expiryDate ?? 'in 7 days') ?></strong>.
                </p>

                <!-- CTA Button -->
                <table width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td align="center">
                            <a href="<?= htmlspecialchars($inviteLink) ?>"
                               style="display:inline-block;background:<?= $brand ?>;color:#ffffff;font-size:15px;font-weight:600;text-decoration:none;padding:13px 32px;border-radius:8px;letter-spacing:0.1px">
                                View Invitation →
                            </a>
                        </td>
                    </tr>
                </table>

                <p style="margin:24px 0 0;font-size:12px;color:#94a3b8;line-height:1.6">
                    If the button doesn't work, copy and paste this link into your browser:<br>
                    <a href="<?= htmlspecialchars($inviteLink) ?>" style="color:<?= $brand ?>;word-break:break-all">
                        <?= htmlspecialchars($inviteLink) ?>
                    </a>
                </p>
            </td>
        </tr>

        <!-- Divider -->
        <tr><td style="padding:0 36px"><hr style="border:none;border-top:1px solid #e2e8f0;margin:0"></td></tr>

        <!-- Footer -->
        <tr>
            <td style="padding:20px 36px;text-align:center">
                <p style="margin:0;font-size:12px;color:#94a3b8;line-height:1.7">
                    You received this because someone invited you to <?= htmlspecialchars($appName) ?>.<br>
                    If you don't have an account, <a href="<?= htmlspecialchars($appUrl) ?>/register" style="color:<?= $brand ?>">sign up here</a>.<br>
                    <a href="<?= htmlspecialchars($appUrl) ?>" style="color:<?= $brand ?>"><?= htmlspecialchars($appUrl) ?></a>
                </p>
            </td>
        </tr>

    </table>
    <!-- /Card -->

</td></tr>
</table>

</body>
</html>
