<?php
$pageTitle = 'Set New Password — Byabsayee';
$token = $_GET['token'] ?? '';
ob_start();
?>

<h1 class="form-title">Set new password</h1>
<p class="form-sub">Choose a strong password for your account.</p>

<form action="/reset-password" method="POST" class="auth-form">
    <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
    <input type="hidden" name="token" value="<?= e($token) ?>">

    <div class="field">
        <label for="password">New password</label>
        <div class="password-wrap">
            <input type="password" id="password" name="password" placeholder="At least 8 characters" required autocomplete="new-password">
            <button type="button" class="toggle-pw" onclick="togglePassword(this)" aria-label="Show password">
                <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" width="18" height="18">
                    <path d="M2 10s3-6 8-6 8 6 8 6-3 6-8 6-8-6-8-6z"/>
                    <circle cx="10" cy="10" r="2.5"/>
                </svg>
            </button>
        </div>
    </div>

    <div class="field">
        <label for="password_confirm">Confirm new password</label>
        <input type="password" id="password_confirm" name="password_confirm" placeholder="Repeat password" required autocomplete="new-password">
    </div>

    <button type="submit" class="btn-primary">Update password</button>
</form>

<script>
function togglePassword(btn) {
    const input = btn.previousElementSibling;
    input.type = input.type === 'password' ? 'text' : 'password';
    btn.style.opacity = input.type === 'text' ? '1' : '0.4';
}
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
?>
