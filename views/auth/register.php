<?php
$pageTitle = 'Create Account — Byabsayee';
ob_start();
?>

<h1 class="form-title">Create your account</h1>
<p class="form-sub">Free forever. No credit card needed.</p>

<form action="/register" method="POST" class="auth-form" novalidate>
    <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">

    <div class="field">
        <label for="name">Full name</label>
        <input
            type="text"
            id="name"
            name="name"
            value="<?= old('name') ?>"
            placeholder="Rahim Uddin"
            autocomplete="name"
            required
        >
    </div>

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
        <label for="password">Password</label>
        <div class="password-wrap">
            <input
                type="password"
                id="password"
                name="password"
                placeholder="At least 8 characters"
                autocomplete="new-password"
                required
                oninput="checkStrength(this.value)"
            >
            <button type="button" class="toggle-pw" onclick="togglePassword(this)" aria-label="Show password">
                <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" width="18" height="18">
                    <path d="M2 10s3-6 8-6 8 6 8 6-3 6-8 6-8-6-8-6z"/>
                    <circle cx="10" cy="10" r="2.5"/>
                </svg>
            </button>
        </div>
        <!-- Password strength bar -->
        <div class="strength-bar" id="strength-bar">
            <div class="strength-fill" id="strength-fill"></div>
        </div>
        <span class="strength-label" id="strength-label"></span>
    </div>

    <div class="field">
        <label for="password_confirm">Confirm password</label>
        <input
            type="password"
            id="password_confirm"
            name="password_confirm"
            placeholder="Repeat your password"
            autocomplete="new-password"
            required
        >
    </div>

    <p class="terms-note">
        By registering, you agree to our <a href="/terms">Terms of Service</a> and <a href="/privacy">Privacy Policy</a>.
    </p>

    <button type="submit" class="btn-primary">Create account</button>
</form>

<p class="form-footer">
    Already have an account? <a href="/login">Log in</a>
</p>

<script>
function togglePassword(btn) {
    const input = btn.previousElementSibling;
    const isHidden = input.type === 'password';
    input.type = isHidden ? 'text' : 'password';
    btn.style.opacity = isHidden ? '1' : '0.4';
}

function checkStrength(pw) {
    const fill  = document.getElementById('strength-fill');
    const label = document.getElementById('strength-label');
    let score = 0;
    if (pw.length >= 8)  score++;
    if (pw.length >= 12) score++;
    if (/[A-Z]/.test(pw) && /[a-z]/.test(pw)) score++;
    if (/[0-9]/.test(pw)) score++;
    if (/[^A-Za-z0-9]/.test(pw)) score++;

    const levels = [
        { label: '',        color: 'transparent', w: '0%'   },
        { label: 'Weak',    color: '#e53e3e',      w: '25%'  },
        { label: 'Fair',    color: '#dd6b20',      w: '50%'  },
        { label: 'Good',    color: '#d69e2e',      w: '75%'  },
        { label: 'Strong',  color: '#38a169',      w: '100%' },
    ];
    const level = levels[Math.min(score, 4)];
    fill.style.width      = level.w;
    fill.style.background = level.color;
    label.textContent     = level.label;
    label.style.color     = level.color;
}
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
?>
