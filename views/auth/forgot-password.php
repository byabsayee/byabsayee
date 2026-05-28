<?php
// ---- forgot-password.php ----
$pageTitle = 'Forgot Password — Byabsayee';
ob_start();
?>

<h1 class="form-title">Reset your password</h1>
<p class="form-sub">Enter your email and we'll send you a reset link.</p>

<form action="/forgot-password" method="POST" class="auth-form">
    <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">

    <div class="field">
        <label for="email">Email address</label>
        <input type="email" id="email" name="email" placeholder="you@example.com" required autocomplete="email">
    </div>

    <button type="submit" class="btn-primary">Send reset link</button>
</form>

<p class="form-footer"><a href="/login">← Back to login</a></p>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
?>
