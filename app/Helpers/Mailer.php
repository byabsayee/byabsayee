<?php

namespace App\Helpers;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as MailerException;

class Mailer
{
    /**
     * Send an email.
     *
     * @param  string|array  $to      Email address or ['email'=>..., 'name'=>...]
     * @param  string        $subject
     * @param  string        $html    HTML body
     * @param  string|null   $text    Plain-text fallback (auto-stripped if null)
     * @return bool
     * @throws \RuntimeException on fatal config error
     */
    public static function send(
        string|array $to,
        string $subject,
        string $html,
        ?string $text = null
    ): bool {
        $mail = new PHPMailer(true);

        try {
            // ── Server config ──────────────────────────────────────────────
            $host = getenv('SMTP_HOST') ?: '';
            $user = getenv('SMTP_USER') ?: '';
            $pass = getenv('SMTP_PASS') ?: '';
            $port = (int)(getenv('SMTP_PORT') ?: 587);
            $from = getenv('SMTP_FROM') ?: $user;
            $fromName = getenv('SMTP_FROM_NAME') ?: (getenv('APP_NAME') ?: 'Byabsayee');

            if (!$host || !$user || !$pass) {
                // SMTP not configured — log and bail silently
                error_log('[Mailer] SMTP not configured. Set SMTP_HOST, SMTP_USER, SMTP_PASS in .env');
                return false;
            }

            $mail->isSMTP();
            $mail->Host       = $host;
            $mail->SMTPAuth   = true;
            $mail->Username   = $user;
            $mail->Password   = $pass;
            $mail->Port       = $port;
            $mail->SMTPSecure = $port === 465 ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
            $mail->CharSet    = 'UTF-8';
            $mail->SMTPDebug  = SMTP::DEBUG_SERVER;  // ← add this line temporarily
            $mail->Debugoutput = fn($str, $level) => error_log('[SMTP] ' . trim($str));

            // ── From ───────────────────────────────────────────────────────
            $mail->setFrom($from, $fromName);
            $mail->addReplyTo($from, $fromName);

            // ── To ─────────────────────────────────────────────────────────
            if (is_array($to)) {
                $mail->addAddress($to['email'], $to['name'] ?? '');
            } else {
                $mail->addAddress($to);
            }

            // ── Content ────────────────────────────────────────────────────
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $html;
            $mail->AltBody = $text ?? strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], "\n", $html));

            $mail->send();
            return true;

        } catch (MailerException $e) {
            error_log('[Mailer] Send failed: ' . $mail->ErrorInfo);
            return false;
        } catch (\Throwable $e) {
            error_log('[Mailer] Unexpected error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Render an email template from views/emails/{template}.php
     * and inject variables.
     */
    public static function render(string $template, array $vars = []): string
    {
        $file = BASE_PATH . '/views/emails/' . ltrim($template, '/') . '.php';
        if (!file_exists($file)) {
            throw new \RuntimeException("Email template not found: {$file}");
        }
        extract($vars, EXTR_SKIP);
        ob_start();
        include $file;
        return ob_get_clean();
    }

    // ── OTP Helpers ───────────────────────────────────────────────────────────

    /** Generate a random 6-digit numeric OTP */
    public static function generateOtp(): string
    {
        return str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    /** Send a 6-digit OTP email (verification or 2FA) */
    public static function sendOtp(string $to, string $name, string $otp, string $purpose = 'verify'): bool
    {
        $label   = $purpose === '2fa' ? 'Two-Factor Authentication' : 'Email Verification';
        $subject = "[Byabsayee] Your {$label} Code: {$otp}";
        $html    = self::otpHtml($name, $otp, $label);
        return self::send($to, $subject, $html);
    }

    /** Send an email verification link with a signed token */
    public static function sendVerificationLink(string $to, string $name, string $token, string $path = '/verify-email'): bool
    {
        $url  = rtrim(getenv('APP_URL') ?: 'https://byabsayee.com', '/') . $path . '?token=' . urlencode($token);
        $html = <<<HTML
<!DOCTYPE html><html><head><meta charset="utf-8"></head>
<body style="font-family:system-ui,sans-serif;background:#f5f5f5;padding:40px 20px">
<div style="max-width:480px;margin:0 auto;background:#fff;border-radius:16px;padding:36px;box-shadow:0 2px 20px rgba(0,0,0,.08)">
  <h2 style="font-size:22px;color:#1a1a1a;margin:0 0 8px">Verify your email</h2>
  <p style="color:#555;margin:0 0 24px">Hello {$name},<br>Click the button below to verify your Byabsayee email address.</p>
  <a href="{$url}" style="display:inline-block;background:#1a6b4a;color:#fff;padding:13px 28px;border-radius:10px;text-decoration:none;font-weight:700;font-size:15px">Verify Email →</a>
  <p style="color:#aaa;font-size:12px;margin:20px 0 0">This link expires in 24 hours. If you didn't create an account, ignore this email.</p>
  <hr style="border:none;border-top:1px solid #eee;margin:20px 0">
  <p style="color:#bbb;font-size:12px;margin:0">Byabsayee · byabsayee.com</p>
</div></body></html>
HTML;
        return self::send($to, '[Byabsayee] Verify your email address', $html);
    }

    private static function otpHtml(string $name, string $otp, string $label): string
    {
        return <<<HTML
<!DOCTYPE html><html><head><meta charset="utf-8"></head>
<body style="font-family:system-ui,sans-serif;background:#f5f5f5;padding:40px 20px">
<div style="max-width:480px;margin:0 auto;background:#fff;border-radius:16px;padding:36px;box-shadow:0 2px 20px rgba(0,0,0,.08)">
  <h2 style="font-size:22px;color:#1a1a1a;margin:0 0 8px">{$label}</h2>
  <p style="color:#555;margin:0 0 28px">Hello {$name}, your one-time verification code is:</p>
  <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:12px;padding:24px;text-align:center;margin-bottom:24px">
    <span style="font-size:40px;font-weight:800;letter-spacing:10px;color:#1a6b4a;font-family:'Courier New',monospace">{$otp}</span>
  </div>
  <p style="color:#888;font-size:13px;margin:0">This code <strong>expires in 10 minutes</strong>. Do not share it with anyone.</p>
  <hr style="border:none;border-top:1px solid #eee;margin:24px 0">
  <p style="color:#bbb;font-size:12px;margin:0">Byabsayee Business Platform · byabsayee.com</p>
</div></body></html>
HTML;
    }

    // ── TOTP (Google Authenticator) Helpers ───────────────────────────────────

    /**
     * Generate a base32-encoded TOTP secret (compatible with Google Authenticator).
     * Install OTPHP: composer require spomky-labs/otphp
     * Or use this lightweight built-in fallback.
     */
    public static function generateTotpSecret(): string
    {
        if (class_exists('\OTPHP\TOTP')) {
            return \OTPHP\TOTP::create()->getSecret();
        }
        // Fallback: generate raw random secret (Base32)
        $chars  = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';
        for ($i = 0; $i < 32; $i++) {
            $secret .= $chars[random_int(0, 31)];
        }
        return $secret;
    }

    /**
     * Get the TOTP provisioning URI for QR code generation.
     * Usage: pass the URI to a QR code library or API.
     */
    public static function getTotpUri(string $secret, string $email, string $issuer = 'Byabsayee'): string
    {
        if (class_exists('\OTPHP\TOTP')) {
            $totp = \OTPHP\TOTP::createFromSecret($secret);
            $totp->setLabel($email);
            $totp->setIssuer($issuer);
            return $totp->getProvisioningUri();
        }
        return 'otpauth://totp/' . urlencode($issuer) . ':' . urlencode($email)
            . '?secret=' . $secret . '&issuer=' . urlencode($issuer);
    }

    /**
     * Verify a TOTP code against a secret.
     * Allows 1 step of clock drift in each direction.
     */
    public static function verifyTotp(string $secret, string $code): bool
    {
        if (class_exists('\OTPHP\TOTP')) {
            $totp = \OTPHP\TOTP::createFromSecret($secret);
            return $totp->verify($code, null, 1);
        }
        // Lightweight TOTP verification without library
        $timestamp = (int)(time() / 30);
        for ($offset = -1; $offset <= 1; $offset++) {
            if (self::totp($secret, $timestamp + $offset) === $code) return true;
        }
        return false;
    }

    private static function totp(string $secret, int $timestamp): string
    {
        $base32 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $binary = '';
        $buffer = 0; $bitsLeft = 0;
        foreach (str_split(strtoupper($secret)) as $char) {
            $pos = strpos($base32, $char);
            if ($pos === false) continue;
            $buffer = ($buffer << 5) | $pos;
            $bitsLeft += 5;
            if ($bitsLeft >= 8) { $bitsLeft -= 8; $binary .= chr(($buffer >> $bitsLeft) & 0xFF); }
        }
        $key  = $binary;
        $data = pack('N*', 0) . pack('N*', $timestamp);
        $hash = hash_hmac('sha1', $data, $key, true);
        $offset = ord($hash[19]) & 0xF;
        $code = (
            ((ord($hash[$offset]) & 0x7F) << 24) |
            ((ord($hash[$offset+1]) & 0xFF) << 16) |
            ((ord($hash[$offset+2]) & 0xFF) << 8) |
            (ord($hash[$offset+3]) & 0xFF)
        ) % 1000000;
        return str_pad((string)$code, 6, '0', STR_PAD_LEFT);
    }
}
