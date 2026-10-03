<?php
namespace App\Services\Integration;

/**
 * AES-256-GCM for secrets at rest, HMAC signing for the wire.
 * Key material: INTEGRATION_SECRET_KEY (env, recommended) or APP_KEY. Changing it later means every
 * connection must be re-paired, so set it once in production.
 */
final class Crypto
{
    private static function key(): string
    {
        $env = (string)(getenv('INTEGRATION_SECRET_KEY') ?: '');
        $src = $env !== '' ? $env : (string)config('key', 'changeme');
        return hash('sha256', 'integration-v1|' . $src, true);
    }

    public static function usesDefaultKey(): bool
    {
        return (string)(getenv('INTEGRATION_SECRET_KEY') ?: '') === '' && in_array((string)config('key', 'changeme'), ['', 'changeme'], true);
    }

    public static function enc(string $plain): string
    {
        $iv = random_bytes(12); $tag = '';
        $ct = openssl_encrypt($plain, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag);
        return 'v1:' . base64_encode($iv . $tag . $ct);
    }

    public static function dec(?string $blob): ?string
    {
        if (!$blob || strncmp($blob, 'v1:', 3) !== 0) return null;
        $raw = base64_decode(substr($blob, 3), true);
        if ($raw === false || strlen($raw) < 29) return null;
        $pt = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
        return $pt === false ? null : $pt;
    }

    /** HMAC-SHA256(secret, METHOD \n target \n timestamp \n nonce \n sha256(body)); target = path plus ?query as sent. */
    public static function sign(string $secret, string $method, string $target, string $timestamp, string $nonce, string $body): string
    {
        return hash_hmac('sha256', strtoupper($method) . "\n" . $target . "\n" . $timestamp . "\n" . $nonce . "\n" . hash('sha256', $body), $secret);
    }

    /** Proof returned by the store for the domain-ownership callback. */
    public static function verifyProof(string $secret, string $token): string
    {
        return hash_hmac('sha256', "erp-verify\n" . $token, $secret);
    }

    public static function newApiKey(): string { return Util::token(36); }
    public static function newSecret(): string { return Util::token(36); }
    public static function newPairingCode(): string
    {
        // 12 chars, no ambiguous characters, grouped for typing: ABCD-EFGH-JKMN
        $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789'; $out = '';
        for ($i = 0; $i < 12; $i++) $out .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        return implode('-', str_split($out, 4));
    }
}
