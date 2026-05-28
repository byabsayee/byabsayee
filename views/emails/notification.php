<?php
$appName = $appName ?? 'Byabsayee';
$appUrl  = $appUrl  ?? (getenv('APP_URL') ?: 'https://byabsayee.com');
$brand   = '#4F7CFF';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($title ?? 'Notification') ?></title>
</head>
<body style="margin:0;padding:0;background:#f4f6fb;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6fb;padding:32px 16px">
<tr><td align="center">
    <table width="100%" style="max-width:520px;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.07)">
        <tr>
            <td style="background:<?= $brand ?>;padding:24px 36px;text-align:center">
                <p style="margin:0;font-size:20px;font-weight:700;color:#fff"><?= htmlspecialchars($appName) ?></p>
            </td>
        </tr>
        <tr>
            <td style="padding:32px 36px">
                <p style="margin:0 0 12px;font-size:17px;font-weight:600;color:#1e293b"><?= htmlspecialchars($title ?? '') ?></p>
                <p style="margin:0 0 20px;font-size:14px;color:#475569;line-height:1.7"><?= nl2br(htmlspecialchars($body ?? '')) ?></p>
                <?php if (!empty($actionUrl) && !empty($actionLabel)): ?>
                <table width="100%" cellpadding="0" cellspacing="0"><tr><td align="center">
                    <a href="<?= htmlspecialchars($actionUrl) ?>" style="display:inline-block;background:<?= $brand ?>;color:#fff;font-size:14px;font-weight:600;text-decoration:none;padding:11px 28px;border-radius:8px">
                        <?= htmlspecialchars($actionLabel) ?> →
                    </a>
                </td></tr></table>
                <?php endif; ?>
            </td>
        </tr>
        <tr><td style="padding:0 36px"><hr style="border:none;border-top:1px solid #e2e8f0;margin:0"></td></tr>
        <tr>
            <td style="padding:16px 36px;text-align:center">
                <p style="margin:0;font-size:11px;color:#94a3b8">
                    Sent via <?= htmlspecialchars($appName) ?> ·
                    <a href="<?= htmlspecialchars($appUrl) ?>" style="color:<?= $brand ?>"><?= htmlspecialchars($appUrl) ?></a>
                </p>
            </td>
        </tr>
    </table>
</td></tr>
</table>
</body>
</html>
