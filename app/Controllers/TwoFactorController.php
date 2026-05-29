<?php
namespace App\Controllers;
use App\Helpers\Database;
use App\Helpers\Mailer;

/**
 * TwoFactorController
 * Routes needed (add to routes.php):
 *   GET  /2fa/setup              → showSetup
 *   POST /2fa/setup              → saveSetup
 *   GET  /2fa/challenge          → showChallenge  (login 2FA prompt)
 *   POST /2fa/challenge          → verifyChallenge
 *   POST /2fa/disable            → disable
 *   POST /2fa/send-otp           → sendOtp        (resend OTP)
 *   GET  /verify-email           → verifyEmail    (?token=...)
 *   POST /send-verification      → sendVerification
 */
class TwoFactorController
{
    // ── Setup 2FA ─────────────────────────────────────────────────────────────
    public function showSetup(array $params): void
    {
        if (guest()) redirect('/login');
        $user = auth();

        $method = $_GET['method'] ?? ($user['two_fa_method'] ?? 'email');

        // For TOTP app setup: generate or reuse secret
        $totpSecret = null;
        $totpUri    = null;
        $totpQrUrl  = null;

        if ($method === 'app') {
            // Load or create TOTP secret
            $tfa = Database::row('SELECT * FROM two_factor_auth WHERE user_id=?', [$user['id']]);
            if (!$tfa || !$tfa['secret']) {
                $totpSecret = Mailer::generateTotpSecret();
                Database::run(
                    'INSERT INTO two_factor_auth (user_id, secret) VALUES (?,?)
                     ON DUPLICATE KEY UPDATE secret=VALUES(secret)',
                    [$user['id'], $totpSecret]
                );
            } else {
                $totpSecret = $tfa['secret'];
            }
            $totpUri   = Mailer::getTotpUri($totpSecret, $user['email']);
            // Use Google Charts API for QR code (replace with local library if needed)
            $totpQrUrl = 'https://chart.googleapis.com/chart?chs=220x220&chld=M|0&cht=qr&chl=' . urlencode($totpUri);
        }

        $pageTitle = 'Two-Factor Authentication';
        ob_start();
        require BASE_PATH . '/views/auth/2fa-setup.php';
        $content = ob_get_clean();
        require BASE_PATH . '/views/partials/layout.php';
    }

    public function saveSetup(array $params): void
    {
        if (guest()) redirect('/login');
        csrf_verify();
        $user   = auth();
        $method = $_POST['method'] ?? 'email';

        if (!in_array($method, ['email','whatsapp','app'])) {
            redirect('/2fa/setup', ['error' => 'Invalid 2FA method.']);
        }

        if ($method === 'app') {
            // Verify the entered TOTP code to confirm setup
            $code = trim($_POST['totp_code'] ?? '');
            $tfa  = Database::row('SELECT secret FROM two_factor_auth WHERE user_id=?', [$user['id']]);
            if (!$tfa || !Mailer::verifyTotp($tfa['secret'], $code)) {
                redirect('/2fa/setup?method=app', ['error' => 'Invalid code. Please scan the QR code again and enter the correct code.']);
            }
            Database::run('UPDATE two_factor_auth SET verified=1 WHERE user_id=?', [$user['id']]);
        }

        if ($method === 'whatsapp') {
            $waNum = trim($_POST['whatsapp_number'] ?? '');
            $waCC  = trim($_POST['whatsapp_country'] ?? '+880');
            if (!$waNum) {
                redirect('/2fa/setup?method=whatsapp', ['error' => 'Please enter a WhatsApp number.']);
            }
            Database::run(
                'UPDATE users SET whatsapp_number=?, whatsapp_country_code=? WHERE id=?',
                [$waNum, $waCC, $user['id']]
            );
        }

        // Enable 2FA
        Database::run(
            'UPDATE users SET two_fa_enabled=1, two_fa_method=? WHERE id=?',
            [$method, $user['id']]
        );
        $_SESSION['user'] = Database::row('SELECT * FROM users WHERE id=?', [$user['id']]);

        redirect('/settings?tab=security', ['success' => '2FA has been enabled using ' . ucfirst($method) . '.']);
    }

    // ── 2FA Login Challenge ───────────────────────────────────────────────────
    public function showChallenge(array $params): void
    {
        $userId = $_SESSION['2fa_user_id'] ?? null;
        if (!$userId) redirect('/login');

        $user   = Database::row('SELECT * FROM users WHERE id=?', [$userId]);
        $method = $user['two_fa_method'] ?? 'email';

        // Auto-send OTP for email/whatsapp methods
        if (in_array($method, ['email','whatsapp']) && empty($_SESSION['2fa_otp_sent'])) {
            $this->dispatchOtp($user, $method);
            $_SESSION['2fa_otp_sent'] = true;
        }

        $pageTitle = 'Two-Factor Authentication';
        require BASE_PATH . '/views/auth/2fa-challenge.php';
    }

    public function verifyChallenge(array $params): void
    {
        $userId = $_SESSION['2fa_user_id'] ?? null;
        if (!$userId) redirect('/login');
        csrf_verify();

        $user   = Database::row('SELECT * FROM users WHERE id=?', [$userId]);
        $method = $user['two_fa_method'] ?? 'email';
        $code   = trim($_POST['code'] ?? '');

        $valid = false;

        if ($method === 'app') {
            $tfa   = Database::row('SELECT secret FROM two_factor_auth WHERE user_id=?', [$userId]);
            $valid = $tfa && Mailer::verifyTotp($tfa['secret'], $code);
        } elseif (in_array($method, ['email','whatsapp'])) {
            $tfa   = Database::row('SELECT * FROM two_factor_auth WHERE user_id=?', [$userId]);
            $valid = $tfa
                && $tfa['otp_code'] === $code
                && $tfa['otp_expires']
                && strtotime($tfa['otp_expires']) > time();
            if ($valid) {
                Database::run('UPDATE two_factor_auth SET otp_code=NULL WHERE user_id=?', [$userId]);
            }
        }

        if (!$valid) {
            $_SESSION['2fa_error'] = 'Invalid or expired code. Please try again.';
            redirect('/2fa/challenge');
        }

        // ✅ 2FA passed — complete login
        $_SESSION['user'] = $user;
        unset($_SESSION['2fa_user_id'], $_SESSION['2fa_otp_sent'], $_SESSION['2fa_error']);

        $intended = $_SESSION['intended'] ?? '/books';
        unset($_SESSION['intended']);
        redirect($intended);
    }

    public function sendOtp(array $params): void
    {
        $userId = $_SESSION['2fa_user_id'] ?? null;
        if (!$userId) redirect('/login');
        csrf_verify();

        $user   = Database::row('SELECT * FROM users WHERE id=?', [$userId]);
        $method = $user['two_fa_method'] ?? 'email';
        $this->dispatchOtp($user, $method);

        redirect('/2fa/challenge', ['success' => 'A new code has been sent.']);
    }

    public function disable(array $params): void
    {
        if (guest()) redirect('/login');
        csrf_verify();
        $user = auth();

        // Require password confirmation
        $pass = $_POST['password'] ?? '';
        if (!password_verify($pass, $user['password'])) {
            redirect('/settings?tab=security', ['error' => 'Incorrect password. 2FA was not disabled.']);
        }

        Database::run('UPDATE users SET two_fa_enabled=0, two_fa_method=NULL WHERE id=?', [$user['id']]);
        $_SESSION['user'] = Database::row('SELECT * FROM users WHERE id=?', [$user['id']]);

        redirect('/settings?tab=security', ['success' => '2FA has been disabled.']);
    }

    // ── Email Verification ────────────────────────────────────────────────────
    public function verifyEmail(array $params): void
    {
        $token = $_GET['token'] ?? '';
        if (!$token) redirect('/books', ['error' => 'Invalid verification link.']);

        $row = Database::row(
            'SELECT * FROM email_verifications WHERE token=? AND used_at IS NULL AND expires_at > NOW()',
            [$token]
        );

        if (!$row) {
            $pageTitle = 'Verification Failed';
            ob_start();
            echo '<div style="max-width:480px;margin:60px auto;text-align:center;font-family:system-ui">';
            echo '<h2>⚠️ Link Expired</h2><p>This verification link is invalid or has expired.</p>';
            echo '<a href="/send-verification" style="color:#1a6b4a">Request a new link →</a></div>';
            $content = ob_get_clean();
            require BASE_PATH . '/views/partials/layout.php';
            return;
        }

        Database::run('UPDATE email_verifications SET used_at=NOW() WHERE id=?', [$row['id']]);
        Database::run('UPDATE users SET email_verified=1 WHERE id=?', [$row['user_id']]);

        if (auth() && auth()['id'] == $row['user_id']) {
            $_SESSION['user']['email_verified'] = 1;
        }

        redirect('/books', ['success' => '✅ Email verified successfully!']);
    }

    public function sendVerification(array $params): void
    {
        if (guest()) redirect('/login');
        csrf_verify();
        $user = auth();

        if ($user['email_verified'] ?? false) {
            redirect('/settings?tab=security', ['error' => 'Your email is already verified.']);
        }

        $token = bin2hex(random_bytes(32));
        Database::run(
            'INSERT INTO email_verifications (user_id, token, expires_at) VALUES (?,?,DATE_ADD(NOW(), INTERVAL 24 HOUR))',
            [$user['id'], $token]
        );

        Mailer::sendVerificationLink($user['email'], $user['name'] ?? '', $token, '/verify-2fa-email');

        redirect('/settings?tab=security', ['success' => 'Verification email sent! Check your inbox.']);
    }

    // ── Internal helpers ──────────────────────────────────────────────────────
    private function dispatchOtp(array $user, string $method): void
    {
        $otp     = Mailer::generateOtp();
        $expires = date('Y-m-d H:i:s', time() + 600); // 10 min

        Database::run(
            'INSERT INTO two_factor_auth (user_id, otp_code, otp_expires) VALUES (?,?,?)
             ON DUPLICATE KEY UPDATE otp_code=VALUES(otp_code), otp_expires=VALUES(otp_expires)',
            [$user['id'], $otp, $expires]
        );

        if ($method === 'email') {
            Mailer::sendOtp($user['email'], $user['name'] ?? '', $otp, '2fa');
        } elseif ($method === 'whatsapp') {
            // WhatsApp OTP — see SETUP_GUIDE.md for WhatsApp Business API configuration
            $this->sendWhatsAppOtp($user, $otp);
        }
    }

    private function sendWhatsAppOtp(array $user, string $otp): void
    {
        $apiKey   = getenv('WHATSAPP_API_KEY');
        $apiUrl   = getenv('WHATSAPP_API_URL');
        $fromNum  = getenv('WHATSAPP_PHONE_NUMBER_ID');

        if (!$apiKey || !$apiUrl || !$fromNum) {
            error_log('[WhatsApp] Not configured. See SETUP_GUIDE.md. OTP: '.$otp);
            return;
        }

        $cc    = $user['whatsapp_country_code'] ?? '+880';
        $num   = preg_replace('/\D/', '', $cc . $user['whatsapp_number']);
        $body  = "Your Byabsayee 2FA code is: *{$otp}*\n\nThis code expires in 10 minutes. Do not share it.";

        $payload = json_encode([
            'messaging_product' => 'whatsapp',
            'to'                => $num,
            'type'              => 'text',
            'text'              => ['body' => $body],
        ]);

        $ch = curl_init("{$apiUrl}/v18.0/{$fromNum}/messages");
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
            ],
        ]);
        $result = curl_exec($ch);
        curl_close($ch);

        if (!$result) {
            error_log('[WhatsApp] Failed to send OTP to ' . $num);
        }
    }
}
