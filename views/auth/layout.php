<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? 'Byabsayee') ?></title>
    <link rel="apple-touch-icon" sizes="180x180" href="<?= asset('apple-touch-icon.png') ?>">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= asset('favicon-32x32.png') ?>">
    <link rel="icon" type="image/png" sizes="16x16" href="<?= asset('favicon-16x16.png') ?>">
    <link rel="shortcut icon" href="<?= asset('favicon.ico') ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Bengali:wght@400;600&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('css/auth.css') ?>">
</head>
<body>

<div class="auth-page">

    <!-- Left panel: branding -->
    <div class="auth-brand">
        <div class="brand-content">
            <div class="logo">
                <span class="logo-icon">৳</span>
                <span class="logo-text">Byabsayee</span>
            </div>
            <p class="brand-tagline">ব্যবসার হিসাব, একটি জায়গায়।<br><small>Track every taka. Know your business.</small></p>
            <div class="brand-features">
                <div class="feat"><span>✓</span> Personal &amp; business books</div>
                <div class="feat"><span>✓</span> Invoices with PDF &amp; POS</div>
                <div class="feat"><span>✓</span> Inventory &amp; stock tracking</div>
                <div class="feat"><span>✓</span> Employee &amp; payroll management</div>
            </div>
        </div>
    </div>

    <!-- Right panel: the form -->
    <div class="auth-form-panel">
        <div class="auth-form-wrap">

            <!-- Flash messages (success or error from redirects) -->
            <?php if ($msg = flash('error')): ?>
                <div class="alert alert-error"><?= e($msg) ?></div>
            <?php endif; ?>
            <?php if ($msg = flash('success')): ?>
                <div class="alert alert-success"><?= e($msg) ?></div>
            <?php endif; ?>

            <!-- The actual form content is injected here -->
            <?= $content ?? '' ?>

        </div>
    </div>

</div>

</body>
</html>