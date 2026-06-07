<?php
namespace App\Controllers;
use App\Helpers\Database;
use App\Helpers\Mailer;

class TwoFactorController
{
    // ── Login: Show challenge (method picker or code entry) ───────────────────
    public function showChallenge(array $params): void
    {
        $userId = $_SESSION['2fa_user_id'] ?? null;
        if (!$userId) redirect('/login');

        $user    = Database::row('SELECT * FROM users WHERE id=?', [$userId]);
        $methods = $_SESSION['2fa_methods'] ?? [$user['two_fa_method'] ?? 'email'];
        $chosen  = $_SESSION['2fa_chosen_method'] ?? null;

        // If only one method, auto-select it
        if (count($methods) === 1 && !$chosen) {
            $chosen = $methods[0];
            $_SESSION['2fa_chosen_method'] = $chosen;
        }

        // Auto-send OTP when method is chosen and it's email/whatsapp
        if ($chosen && in_array($chosen, ['email','whatsapp']) && empty($_SESSION['2fa_otp_sent'])) {
            $this->dispatchOtp($user, $chosen);
            $_SESSION['2fa_otp_sent'] = true;
        }

        // For TOTP, get the secret
        $totpSecret = null;
        if ($chosen === 'app') {
            try {
                $row = Database::row('SELECT secret FROM user_2fa_methods WHERE user_id=? AND method="app" AND is_enabled=1', [$userId]);
                $totpSecret = $row['secret'] ?? null;
                // Fallback to old table
                if (!$totpSecret) {
                    $old = Database::row('SELECT secret FROM two_factor_auth WHERE user_id=?', [$userId]);
                    $totpSecret = $old['secret'] ?? null;
                }
            } catch (\Throwable $e) {}
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
        $chosen = $_SESSION['2fa_chosen_method'] ?? null;
        $code   = trim($_POST['code'] ?? '');

        // Handle method selection step
        if (isset($_POST['choose_method'])) {
            $method  = $_POST['choose_method'];
            $methods = $_SESSION['2fa_methods'] ?? [];
            // Empty string or __reset__ means "go back to method picker"
            if ($method === '' || $method === '__reset__') {
                $_SESSION['2fa_chosen_method'] = null;
                $_SESSION['2fa_otp_sent']      = false;
                redirect('/2fa/challenge');
            }
            if (!in_array($method, $methods)) redirect('/2fa/challenge');
            $_SESSION['2fa_chosen_method'] = $method;
            $_SESSION['2fa_otp_sent']      = false;
            redirect('/2fa/challenge');
        }

        if (!$chosen) redirect('/2fa/challenge');
        if (!$code)   { $_SESSION['2fa_error'] = 'Please enter the code.'; redirect('/2fa/challenge'); }

        $valid = false;
        if ($chosen === 'app') {
            // Try new table first, then legacy
            $secret = null;
            try { $r = Database::row('SELECT secret FROM user_2fa_methods WHERE user_id=? AND method="app" AND is_enabled=1', [$userId]); $secret = $r['secret'] ?? null; } catch (\Throwable $e) {}
            if (!$secret) { try { $r = Database::row('SELECT secret FROM two_factor_auth WHERE user_id=?', [$userId]); $secret = $r['secret'] ?? null; } catch (\Throwable $e) {} }
            $valid = $secret && Mailer::verifyTotp($secret, $code);
        } elseif (in_array($chosen, ['email','whatsapp'])) {
            // Check new table first
            try {
                $row = Database::row('SELECT otp_code, otp_expires FROM user_2fa_methods WHERE user_id=? AND method=? AND is_enabled=1', [$userId, $chosen]);
                if ($row && $row['otp_code'] === $code && $row['otp_expires'] && strtotime($row['otp_expires']) > time()) {
                    Database::run('UPDATE user_2fa_methods SET otp_code=NULL WHERE user_id=? AND method=?', [$userId, $chosen]);
                    $valid = true;
                }
            } catch (\Throwable $e) {}
            // Fallback legacy
            if (!$valid) {
                try {
                    $tfa = Database::row('SELECT * FROM two_factor_auth WHERE user_id=?', [$userId]);
                    if ($tfa && $tfa['otp_code'] === $code && $tfa['otp_expires'] && strtotime($tfa['otp_expires']) > time()) {
                        Database::run('UPDATE two_factor_auth SET otp_code=NULL WHERE user_id=?', [$userId]);
                        $valid = true;
                    }
                } catch (\Throwable $e) {}
            }
        }

        if (!$valid) {
            $_SESSION['2fa_error'] = 'Invalid or expired code. Please try again.';
            redirect('/2fa/challenge');
        }

        // ✅ 2FA passed — complete login
        $_SESSION['user'] = $user;

        // Record session
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
        } catch (\Throwable $e) {}

        unset($_SESSION['2fa_user_id'], $_SESSION['2fa_methods'], $_SESSION['2fa_chosen_method'],
              $_SESSION['2fa_otp_sent'], $_SESSION['2fa_error']);

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
        $chosen = $_SESSION['2fa_chosen_method'] ?? ($user['two_fa_method'] ?? 'email');
        $this->dispatchOtp($user, $chosen);
        $_SESSION['2fa_otp_sent'] = true;

        redirect('/2fa/challenge', ['success' => 'A new code has been sent.']);
    }

    // ── Profile security: get TOTP setup data (called via GET, returns JSON) ──
    public function getTotpSetup(array $params): void
    {
        if (guest()) { http_response_code(401); echo json_encode(['error'=>'Unauthorized']); exit; }
        $user = auth();

        $secret = null;
        try {
            $row    = Database::row('SELECT secret FROM user_2fa_methods WHERE user_id=? AND method="app"', [$user['id']]);
            $secret = $row['secret'] ?? null;
        } catch (\Throwable $e) {}

        if (!$secret) {
            $secret = Mailer::generateTotpSecret();
            try {
                Database::run(
                    'INSERT INTO user_2fa_methods (user_id, method, secret, is_enabled) VALUES (?,?,?,0)
                     ON DUPLICATE KEY UPDATE secret=VALUES(secret)',
                    [$user['id'], 'app', $secret]
                );
            } catch (\Throwable $e) {}
        }

        $label  = rawurlencode($user['email'] ?? $user['name'] ?? 'User');
        $issuer = rawurlencode('Byabsayee');
        $uri    = "otpauth://totp/{$issuer}:{$label}?secret={$secret}&issuer={$issuer}&algorithm=SHA1&digits=6&period=30";
        $qrDataUri = \App\Helpers\QrCode::dataUri($uri, 200);

        header('Content-Type: application/json');
        echo json_encode(['secret' => $secret, 'qr_data_uri' => $qrDataUri, 'uri' => $uri]);
        exit;
    }



    // ── Email Verification ─────────────────────────────────────────────────────
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
        if (auth() && auth()['id'] == $row['user_id']) $_SESSION['user']['email_verified'] = 1;
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

    // ── Internal helpers ───────────────────────────────────────────────────────
    public function dispatchOtp(array $user, string $method): void
    {
        $otp     = Mailer::generateOtp();
        $expires = date('Y-m-d H:i:s', time() + 600);

        // Store in new table
        try {
            Database::run(
                'UPDATE user_2fa_methods SET otp_code=?, otp_expires=? WHERE user_id=? AND method=?',
                [$otp, $expires, $user['id'], $method]
            );
        } catch (\Throwable $e) {}

        // Also update legacy table as fallback
        try {
            Database::run(
                'INSERT INTO two_factor_auth (user_id, otp_code, otp_expires) VALUES (?,?,?)
                 ON DUPLICATE KEY UPDATE otp_code=VALUES(otp_code), otp_expires=VALUES(otp_expires)',
                [$user['id'], $otp, $expires]
            );
        } catch (\Throwable $e) {}

        if ($method === 'email') {
            Mailer::sendOtp($user['email'], $user['name'] ?? '', $otp, '2fa');
        } elseif ($method === 'whatsapp') {
            $this->sendWhatsAppOtp($user, $otp);
        }
    }

    private function sendWhatsAppOtp(array $user, string $otp): void
    {
        $apiKey  = getenv('WHATSAPP_API_KEY');
        $apiUrl  = getenv('WHATSAPP_API_URL');
        $fromNum = getenv('WHATSAPP_PHONE_NUMBER_ID');
        if (!$apiKey || !$apiUrl || !$fromNum) {
            error_log('[WhatsApp] Not configured. OTP: '.$otp);
            return;
        }
        $cc  = $user['whatsapp_country_code'] ?? '+880';
        $num = preg_replace('/\D/', '', $cc . $user['whatsapp_number']);
        $payload = json_encode([
            'messaging_product' => 'whatsapp',
            'to'   => $num,
            'type' => 'text',
            'text' => ['body' => "Your Byabsayee 2FA code is: *{$otp}*\n\nExpires in 10 minutes. Do not share it."],
        ]);
        $ch = curl_init("{$apiUrl}/v18.0/{$fromNum}/messages");
        curl_setopt_array($ch, [CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>$payload, CURLOPT_RETURNTRANSFER=>true,
            CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$apiKey, 'Content-Type: application/json']]);
        curl_exec($ch);
        curl_close($ch);
    }
}
