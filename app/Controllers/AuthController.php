<?php
// =============================================================================
// app/Controllers/AuthController.php
// =============================================================================
// Handles everything related to user identity:
//   - Show and process the login form
//   - Show and process the registration form
//   - Logout
//   - Email verification
//   - Password reset
// =============================================================================

namespace App\Controllers;

use App\Helpers\Database;
use App\Helpers\Mailer;

class AuthController
{
    // =========================================================================
    // SHOW LOGIN PAGE  →  GET /login
    // =========================================================================
    public function showLogin(): void
    {
        // If already logged in, go to dashboard
        if (auth()) redirect('/books');

        require BASE_PATH . '/views/auth/login.php';
    }

    // =========================================================================
    // PROCESS LOGIN FORM  →  POST /login
    // =========================================================================
    public function login(): void
    {
        csrf_verify();  // Check the CSRF token (security)

        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember = isset($_POST['remember']);

        // --- Basic validation ---
        if (!$email || !$password) {
            set_old(['email' => $email]);
            redirect('/login', ['error' => 'Please enter your email and password.']);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            set_old(['email' => $email]);
            redirect('/login', ['error' => 'Please enter a valid email address.']);
        }

        // --- Find user in database ---
        $user = Database::row(
            'SELECT * FROM users WHERE email = ? AND deleted_at IS NULL',
            [$email]
        );

        // --- Check password ---
        // password_verify() safely checks the hashed password stored in DB
        if (!$user || !password_verify($password, $user['password_hash'])) {
            set_old(['email' => $email]);
            redirect('/login', ['error' => 'Incorrect email or password.']);
        }

        // --- Check email is verified ---
        if (!$user['email_verified_at']) {
            set_old(['email' => $email]);
            redirect('/login', ['error' => 'Please verify your email address first. Check your inbox.']);
        }

        // --- Check account is active ---
        if ($user['status'] !== 'active') {
            redirect('/login', ['error' => 'Your account has been suspended. Please contact support.']);
        }

        // --- Login successful: store user in session ---
        // We store only what we need, not the password hash
        $_SESSION['user'] = [
            'id'     => $user['id'],
            'name'   => $user['name'],
            'email'  => $user['email'],
            'avatar' => $user['avatar'] ?? null,
        ];

        // --- Update last login time ---
        Database::run(
            'UPDATE users SET last_login_at = ? WHERE id = ?',
            [now(), $user['id']]
        );

        // --- Record session for active-sessions tracking ---
        try {
            $ua     = substr($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown', 0, 255);
            $ip     = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
            $sessId = session_id();
            Database::run(
                'INSERT INTO user_sessions (user_id, session_id, ip_address, user_agent, last_active_at)
                 VALUES (?,?,?,?,NOW())
                 ON DUPLICATE KEY UPDATE last_active_at=NOW(), ip_address=VALUES(ip_address)',
                [$user['id'], $sessId, $ip, $ua]
            );
        } catch (\Throwable $e) { /* table may not exist yet */ }

        // --- Remember me: extend session lifetime ---
        if ($remember) {
            $token = generate_token();
            Database::run(
                'INSERT INTO remember_tokens (user_id, token, expires_at) VALUES (?, ?, ?)',
                [$user['id'], hash('sha256', $token), date('Y-m-d H:i:s', strtotime('+30 days'))]
            );
            setcookie('remember_token', $token, time() + (30 * 24 * 60 * 60), '/', '', true, true);
        }

        // --- 2FA gate ---
        if (!empty($user['two_fa_enabled'])) {
            // Load all enabled methods for this user
            $methods = [];
            try {
                $rows = Database::query(
                    'SELECT method FROM user_2fa_methods WHERE user_id=? AND is_enabled=1',
                    [$user['id']]
                );
                $methods = array_column($rows, 'method');
            } catch (\Throwable $e) {}

            // Fallback to legacy single-method column if new table returned nothing
            if (empty($methods) && !empty($user['two_fa_method'])) {
                $methods = [$user['two_fa_method']];
            }

            if (!empty($methods)) {
                $_SESSION['2fa_user_id']       = $user['id'];
                $_SESSION['2fa_methods']       = $methods;
                $_SESSION['2fa_chosen_method'] = null;
                unset($_SESSION['user']);
                redirect('/2fa/challenge');
            }
        }

        redirect('/books');
    }

    // =========================================================================
    // LOGOUT  →  GET /logout
    // =========================================================================
    public function logout(): void
    {
        // Clear remember token cookie if it exists
        if (isset($_COOKIE['remember_token'])) {
            Database::run(
                'DELETE FROM remember_tokens WHERE token = ?',
                [hash('sha256', $_COOKIE['remember_token'])]
            );
            setcookie('remember_token', '', time() - 3600, '/', '', true, true);
        }

        // Remove session tracking row
        try {
            Database::run('DELETE FROM user_sessions WHERE session_id=?', [session_id()]);
        } catch (\Throwable $e) {}

        // Destroy and immediately restart a clean session so CSRF token
        // is available on the login page (session_destroy wipes $_SESSION).
        session_unset();
        session_destroy();
        session_start();
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));

        redirect('/login', ['success' => 'You have been logged out.']);
    }

    // =========================================================================
    // SHOW REGISTER PAGE  →  GET /register
    // =========================================================================
    public function showRegister(): void
    {
        if (auth()) redirect('/books');
        require BASE_PATH . '/views/auth/register.php';
    }

    // =========================================================================
    // PROCESS REGISTRATION  →  POST /register
    // =========================================================================
    public function register(): void
    {
        csrf_verify();

        $name     = trim($_POST['name'] ?? '');
        $email    = trim(strtolower($_POST['email'] ?? ''));
        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['password_confirm'] ?? '';

        // --- Validation ---
        $errors = [];

        if (mb_strlen($name) < 2) {
            $errors[] = 'Name must be at least 2 characters.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        }
        if (mb_strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        }
        if ($password !== $confirm) {
            $errors[] = 'Passwords do not match.';
        }

        // Check if email is already taken
        $existing = Database::row('SELECT id FROM users WHERE email = ?', [$email]);
        if ($existing) {
            $errors[] = 'This email address is already registered.';
        }

        if ($errors) {
            set_old(['name' => $name, 'email' => $email]);
            redirect('/register', ['error' => implode(' ', $errors)]);
        }

        // --- Create the user ---
        // password_hash() uses bcrypt — never store plain text passwords!
        $hash  = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $token = generate_token();

        Database::run(
            'INSERT INTO users (name, email, password_hash, verification_token, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?)',
            [$name, $email, $hash, $token, 'pending', now()]
        );

        $userId = Database::lastId();

        // --- Send verification email ---
        $this->sendVerificationEmail($email, $name, $token);

        redirect('/login', ['success' => 'Account created! Please check your email to verify your account.']);
    }

    // =========================================================================
    // VERIFY EMAIL  →  GET /verify-email?token=xxxx
    // =========================================================================
    public function verifyEmail(): void
    {
        $token = $_GET['token'] ?? '';

        if (!$token) {
            redirect('/login', ['error' => 'Invalid verification link.']);
        }

        $user = Database::row(
            'SELECT * FROM users WHERE verification_token = ? AND email_verified_at IS NULL',
            [$token]
        );

        if (!$user) {
            redirect('/login', ['error' => 'This verification link is invalid or has already been used.']);
        }

        // Mark email as verified
        Database::run(
            'UPDATE users SET email_verified_at = ?, verification_token = NULL, status = ? WHERE id = ?',
            [now(), 'active', $user['id']]
        );

        redirect('/login', ['success' => 'Email verified! You can now log in.']);
    }

    // =========================================================================
    // SHOW FORGOT PASSWORD PAGE  →  GET /forgot-password
    // =========================================================================
    public function showForgotPassword(): void
    {
        require BASE_PATH . '/views/auth/forgot-password.php';
    }

    // =========================================================================
    // SEND PASSWORD RESET LINK  →  POST /forgot-password
    // =========================================================================
    public function sendResetLink(): void
    {
        csrf_verify();

        $email = trim(strtolower($_POST['email'] ?? ''));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            redirect('/forgot-password', ['error' => 'Please enter a valid email address.']);
        }

        $user = Database::row('SELECT * FROM users WHERE email = ?', [$email]);

        // Always show success even if email not found (prevents email enumeration)
        if ($user) {
            $token   = generate_token();
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

            // Delete old tokens for this user
            Database::run('DELETE FROM password_resets WHERE email = ?', [$email]);

            // Store new token
            Database::run(
                'INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)',
                [$email, hash('sha256', $token), $expires]
            );

            $this->sendPasswordResetEmail($email, $user['name'], $token);
        }

        redirect('/forgot-password', ['success' => 'If that email is registered, you will receive a reset link shortly.']);
    }

    // =========================================================================
    // SHOW RESET PASSWORD PAGE  →  GET /reset-password?token=xxxx
    // =========================================================================
    public function showResetPassword(): void
    {
        $token = $_GET['token'] ?? '';

        $reset = Database::row(
            'SELECT * FROM password_resets WHERE token = ? AND expires_at > ?',
            [hash('sha256', $token), now()]
        );

        if (!$reset) {
            redirect('/forgot-password', ['error' => 'This reset link is invalid or has expired.']);
        }

        require BASE_PATH . '/views/auth/reset-password.php';
    }

    // =========================================================================
    // PROCESS RESET PASSWORD  →  POST /reset-password
    // =========================================================================
    public function resetPassword(): void
    {
        csrf_verify();

        $token    = $_POST['token'] ?? '';
        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['password_confirm'] ?? '';

        if (mb_strlen($password) < 8) {
            redirect('/reset-password?token=' . $token, ['error' => 'Password must be at least 8 characters.']);
        }
        if ($password !== $confirm) {
            redirect('/reset-password?token=' . $token, ['error' => 'Passwords do not match.']);
        }

        $reset = Database::row(
            'SELECT * FROM password_resets WHERE token = ? AND expires_at > ?',
            [hash('sha256', $token), now()]
        );

        if (!$reset) {
            redirect('/forgot-password', ['error' => 'This reset link is invalid or has expired.']);
        }

        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

        Database::run(
            'UPDATE users SET password_hash = ? WHERE email = ?',
            [$hash, $reset['email']]
        );

        Database::run('DELETE FROM password_resets WHERE email = ?', [$reset['email']]);

        redirect('/login', ['success' => 'Password updated! You can now log in.']);
    }

    // =========================================================================
    // PRIVATE: Send verification email
    // =========================================================================
    private function sendVerificationEmail(string $email, string $name, string $token): void
    {
        $sent = Mailer::sendVerificationLink($email, $name, $token);
        if (!$sent) {
            error_log("[AuthController] Failed to send verification email to {$email}");
        }
    }

    // =========================================================================
    // PRIVATE: Send password reset email
    // =========================================================================
    private function sendPasswordResetEmail(string $email, string $name, string $token): void
    {
        $link    = rtrim(getenv('APP_URL') ?: 'https://byabsayee.com', '/') . '/reset-password?token=' . urlencode($token);
        $appName = getenv('APP_NAME') ?: 'Byabsayee';
        $subject = "[{$appName}] Reset your password";
        $html    = Mailer::render('password-reset', [
            'name'    => $name,
            'link'    => $link,
            'appName' => $appName,
            'appUrl'  => rtrim(getenv('APP_URL') ?: 'https://byabsayee.com', '/'),
        ]);
        $sent = Mailer::send($email, $subject, $html);
        if (!$sent) {
            error_log("[AuthController] Failed to send password reset email to {$email}");
        }
    }
}
