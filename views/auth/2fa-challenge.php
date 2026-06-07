<?php
$chosen  = $_SESSION['2fa_chosen_method'] ?? null;
$methods = $_SESSION['2fa_methods'] ?? [$user['two_fa_method'] ?? 'email'];

$methodLabels = ['email'=>'Email OTP','whatsapp'=>'WhatsApp OTP','app'=>'Authenticator App'];
$methodDescs  = ['email'=>'Enter the 6-digit code sent to your email','whatsapp'=>'Enter the 6-digit code sent to your WhatsApp','app'=>'Enter the 6-digit code from your authenticator app'];
$methodIcons  = ['email'=>'fa-envelope','whatsapp'=>'fa-brands fa-whatsapp','app'=>'fa-mobile-screen-button'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Two-Factor Authentication — Byabsayee</title>
    <link rel="icon" href="/favicon.ico">
    <script src="https://kit.fontawesome.com/86c0c1c09a.js" crossorigin="anonymous"></script>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:'DM Sans',system-ui,sans-serif;background:#f5f5f5;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
    .card{background:#fff;border-radius:20px;box-shadow:0 4px 40px rgba(0,0,0,.12);padding:44px 40px;width:100%;max-width:420px}
    .logo{text-align:center;margin-bottom:24px;font-size:16px;font-weight:700;color:#1a6b4a}
    .icon-wrap{width:60px;height:60px;background:rgba(26,107,74,.1);border-radius:16px;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;font-size:24px;color:#1a6b4a}
    h1{font-size:22px;font-weight:700;color:#1a1a1a;text-align:center;margin-bottom:8px}
    .sub{font-size:14px;color:#666;text-align:center;line-height:1.6;margin-bottom:24px}
    /* Method picker */
    .method-list{display:flex;flex-direction:column;gap:10px;margin-bottom:24px}
    .method-btn{display:flex;align-items:center;gap:14px;padding:14px 16px;border:2px solid #eee;border-radius:12px;background:#fafafa;cursor:pointer;width:100%;text-align:left;font-family:inherit;transition:all .15s}
    .method-btn:hover{border-color:#1a6b4a;background:#f0faf5}
    .method-icon{width:40px;height:40px;border-radius:10px;background:#fff;border:1px solid #eee;display:flex;align-items:center;justify-content:center;font-size:18px;color:#1a6b4a;flex-shrink:0}
    .method-label{font-size:14px;font-weight:700;color:#1a1a1a}
    .method-desc{font-size:12px;color:#888;margin-top:1px}
    /* Code entry */
    .code-row{display:flex;gap:8px;justify-content:center;margin-bottom:24px}
    .code-box{width:48px;height:58px;border:2px solid #e0e0e0;border-radius:10px;font-size:24px;font-weight:700;text-align:center;color:#1a1a1a;outline:none;transition:border-color .15s;background:#fff;caret-color:transparent}
    .code-box:focus{border-color:#1a6b4a;box-shadow:0 0 0 3px rgba(26,107,74,.12)}
    input[name=code]{display:none}
    .btn{width:100%;padding:13px;border:none;border-radius:10px;font-size:15px;font-weight:700;cursor:pointer;font-family:inherit;transition:all .2s}
    .btn-primary{background:#1a6b4a;color:#fff}
    .btn-primary:hover{background:#155c3c}
    .resend-row{text-align:center;margin-top:16px;font-size:13px;color:#888}
    .resend-row button{background:none;border:none;color:#1a6b4a;font-weight:700;cursor:pointer;font-size:13px;font-family:inherit}
    .change-method{text-align:center;margin-top:12px}
    .change-method button{background:none;border:none;color:#888;font-size:13px;cursor:pointer;font-family:inherit;text-decoration:underline}
    .err{background:#fff5f5;border:1px solid #fed7d7;border-radius:10px;padding:12px 14px;color:#e53e3e;font-size:13px;margin-bottom:20px;text-align:center}
    .ok{background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;padding:12px 14px;color:#166534;font-size:13px;margin-bottom:20px;text-align:center}
    .back-link{display:block;text-align:center;margin-top:20px;font-size:13px;color:#888;text-decoration:none}
    .back-link:hover{color:#1a6b4a}
    </style>
</head>
<body>
<div class="card">
    <div class="logo"><i class="fa-solid fa-lock"></i> Byabsayee</div>

    <?php if (!empty($_SESSION['2fa_error'])): ?>
    <div class="err"><i class="fa-solid fa-triangle-exclamation"></i> <?= e($_SESSION['2fa_error']) ?><?php unset($_SESSION['2fa_error']); ?></div>
    <?php endif; ?>
    <?php if (!empty($_SESSION['flash_success'])): ?>
    <div class="ok"><i class="fa-solid fa-check-circle"></i> <?= e($_SESSION['flash_success']) ?><?php unset($_SESSION['flash_success']); ?></div>
    <?php endif; ?>

    <?php if (!$chosen && count($methods) > 1): ?>
    <!-- ── Step 1: Choose a method ──────────────────────────────────────── -->
    <div class="icon-wrap"><i class="fa-solid fa-shield-halved"></i></div>
    <h1>Two-Factor Authentication</h1>
    <p class="sub">Choose how you'd like to verify your identity.</p>

    <div class="method-list">
        <?php foreach ($methods as $m): ?>
        <form method="POST" action="/2fa/challenge">
            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="choose_method" value="<?= e($m) ?>">
            <button type="submit" class="method-btn">
                <div class="method-icon"><i class="fa-solid <?= e($methodIcons[$m] ?? 'fa-lock') ?>"></i></div>
                <div>
                    <div class="method-label"><?= e($methodLabels[$m] ?? ucfirst($m)) ?></div>
                    <div class="method-desc"><?= e($methodDescs[$m] ?? '') ?></div>
                </div>
                <i class="fa-solid fa-chevron-right" style="margin-left:auto;color:#ccc;font-size:12px"></i>
            </button>
        </form>
        <?php endforeach; ?>
    </div>

    <?php else: ?>
    <!-- ── Step 2: Enter code ───────────────────────────────────────────── -->
    <div class="icon-wrap"><i class="fa-solid <?= e($methodIcons[$chosen] ?? 'fa-lock') ?>"></i></div>
    <h1><?= e($methodLabels[$chosen] ?? 'Verify') ?></h1>
    <p class="sub"><?= e($methodDescs[$chosen] ?? 'Enter the 6-digit code.') ?></p>

    <form method="POST" action="/2fa/challenge" id="challengeForm">
        <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
        <input type="hidden" name="code" id="hiddenCode">

        <div class="code-row" id="codeRow">
            <?php for ($i = 0; $i < 6; $i++): ?>
            <input type="text" maxlength="1" pattern="[0-9]" inputmode="numeric" class="code-box" autocomplete="off">
            <?php endfor; ?>
        </div>

        <button type="submit" class="btn btn-primary">Verify</button>
    </form>

    <?php if (in_array($chosen, ['email','whatsapp'])): ?>
    <div class="resend-row">
        Didn't receive it?
        <form method="POST" action="/2fa/send-otp" style="display:inline">
            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
            <button type="submit">Resend code</button>
        </form>
    </div>
    <?php endif; ?>

    <?php if (count($methods) > 1): ?>
    <div class="change-method">
        <form method="POST" action="/2fa/challenge">
            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="choose_method" value="">
            <button type="submit" onclick="this.form.elements['choose_method'].value='__reset__';return true;">
                ← Use a different method
            </button>
        </form>
    </div>
    <?php endif; ?>

    <?php endif; ?>

    <a href="/logout" class="back-link"><i class="fa-solid fa-arrow-left" style="margin-right:4px"></i>Back to login</a>
</div>

<script>
// Reset method selection
document.querySelectorAll('form').forEach(f => {
    f.addEventListener('submit', function(e) {
        const cm = this.elements['choose_method'];
        if (cm && cm.value === '__reset__') {
            cm.value = '';
        }
    });
});

const boxes  = document.querySelectorAll('.code-box');
const hidden = document.getElementById('hiddenCode');
const form   = document.getElementById('challengeForm');

if (boxes.length) {
    boxes.forEach((box, i) => {
        box.addEventListener('keydown', e => {
            if (e.key === 'Backspace' && !box.value && i > 0) boxes[i-1].focus();
        });
        box.addEventListener('input', e => {
            box.value = box.value.replace(/\D/g,'').slice(-1);
            if (box.value && i < 5) boxes[i+1].focus();
            updateHidden();
            if (getCode().length === 6) form.submit();
        });
        box.addEventListener('paste', e => {
            const text = (e.clipboardData||window.clipboardData).getData('text').replace(/\D/g,'').slice(0,6);
            if (text.length === 6) {
                boxes.forEach((b,j) => b.value = text[j]||'');
                updateHidden();
                boxes[5].focus();
                setTimeout(() => form.submit(), 80);
            }
            e.preventDefault();
        });
    });
    function getCode() { return [...boxes].map(b=>b.value).join(''); }
    function updateHidden() { if (hidden) hidden.value = getCode(); }
    if (boxes[0]) boxes[0].focus();
}
</script>
</body>
</html>
