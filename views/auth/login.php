<?php
// This file is included by AuthController::showLogin()
// We capture the form HTML into $content, then load the shared layout.

$pageTitle = 'Login — Byabsayee';

ob_start();  // Start capturing output
?>

<h1 class="form-title">Welcome back</h1>
<p class="form-sub">Log in to your Byabsayee account</p>

<form action="/login" method="POST" class="auth-form" novalidate>

    <!-- CSRF token: a hidden field that proves this form came from our site -->
    <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">

    <div class="field">
        <label for="email">Email address</label>
        <input
            type="email"
            id="email"
            name="email"
            value="<?= old('email') ?>"
            placeholder="you@example.com"
            autocomplete="email"
            required
        >
    </div>

    <div class="field">
        <label for="password">
            Password
            <a href="/forgot-password" class="label-link">Forgot password?</a>
        </label>
        <div class="password-wrap">
            <input
                type="password"
                id="password"
                name="password"
                placeholder="••••••••"
                autocomplete="current-password"
                required
            >
            <button type="button" class="toggle-pw" onclick="togglePassword(this)" aria-label="Show password">
                <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" width="18" height="18">
                    <path d="M2 10s3-6 8-6 8 6 8 6-3 6-8 6-8-6-8-6z"/>
                    <circle cx="10" cy="10" r="2.5"/>
                </svg>
            </button>
        </div>
    </div>

    <label class="checkbox-label">
        <input type="checkbox" name="remember" value="1">
        <span>Keep me logged in for 30 days</span>
    </label>

    <button type="submit" class="btn-primary">Log in</button>

</form>

<p class="form-footer">
    Don't have an account? <a href="/register">Create one free</a>
</p>

<script>
function togglePassword(btn) {
    const input = btn.previousElementSibling;
    const isHidden = input.type === 'password';
    input.type = isHidden ? 'text' : 'password';
    btn.style.opacity = isHidden ? '1' : '0.4';
}
</script>

<?php
$content = ob_get_clean();  // Capture what we just output
require __DIR__ . '/layout.php';  // Wrap it in the shared layout
?>
