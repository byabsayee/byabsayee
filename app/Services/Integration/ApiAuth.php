<?php
namespace App\Services\Integration;

use App\Helpers\Database;

/**
 * Authenticates a request coming FROM the store: HTTPS, known connection, API key (Bearer) and a valid,
 * fresh, unreplayed HMAC signature made with the site_to_book secret. Both the key and the signature are
 * required (D12): the key identifies the connection, the signature protects the message.
 */
final class ApiAuth
{
    public const MAX_BODY = 2097152; // 2 MB

    /**
     * @param string[] $allowed connection statuses that may use this route
     * @return array{conn:array,body:string}
     */
    public static function authenticate(array $allowed = ['active', 'paused', 'verifying']): array
    {
        $ip = Util::clientIp();
        if (!RateLimit::ok('ip:' . $ip, 240)) throw new ApiError('rate_limited', 'Too many requests.', 429, ['retry_after' => 60]);
        if (!Util::requestIsHttps() && !Util::testMode()) throw new ApiError('https_required', 'This endpoint only accepts HTTPS.', 400);

        $len = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
        if ($len > self::MAX_BODY) throw new ApiError('payload_too_large', 'Request body too large.', 413);
        $body = (string)file_get_contents('php://input', false, null, 0, self::MAX_BODY + 1);
        if (strlen($body) > self::MAX_BODY) throw new ApiError('payload_too_large', 'Request body too large.', 413);

        $fail = function (string $code, string $msg, int $http = 401) use ($ip): never {
            RateLimit::ok('bad:' . $ip, 30);
            Log::write(null, 'in', 'auth', 'Rejected request: ' . $code, false, null, $http, ['ip' => $ip, 'path' => strtok($_SERVER['REQUEST_URI'] ?? '', '?')]);
            throw new ApiError($code, $msg, $http);
        };

        $cid  = (string)($_SERVER['HTTP_X_CONNECTION_ID'] ?? '');
        $conn = Util::isUuid($cid) ? Conn::byUuid($cid) : null;
        if (!$conn) $fail('unknown_connection', 'Unknown or inactive connection.');
        if ($conn['status'] === 'revoked') throw new ApiError('connection_revoked', 'This connection was revoked.', 410);
        if (!in_array($conn['status'], $allowed, true) && $conn['status'] !== 'paused') $fail('unknown_connection', 'Unknown or inactive connection.');

        $auth = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? ''));
        $key  = stripos($auth, 'Bearer ') === 0 ? trim(substr($auth, 7)) : '';
        if ($key === '' || empty($conn['api_key_hash']) || !hash_equals((string)$conn['api_key_hash'], hash('sha256', $key))) $fail('bad_api_key', 'Invalid API key.');

        $ts = (string)($_SERVER['HTTP_X_TIMESTAMP'] ?? ''); $nonce = (string)($_SERVER['HTTP_X_NONCE'] ?? '');
        $sig = strtolower((string)($_SERVER['HTTP_X_SIGNATURE'] ?? ''));
        if (!ctype_digit($ts) || abs(time() - (int)$ts) > Util::TS_WINDOW) $fail('timestamp_out_of_range', 'Timestamp is outside the allowed window (±5 minutes).');
        if (!preg_match('/^[A-Za-z0-9_\-]{16,64}$/', $nonce)) $fail('bad_nonce', 'Missing or malformed nonce.');
        $secret = Conn::secretFromSite($conn);
        if (!$secret) $fail('not_configured', 'The signing secret is not configured.', 503);

        $expected = Crypto::sign($secret, $_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/', $ts, $nonce, $body);
        if (!hash_equals($expected, $sig)) $fail('bad_signature', 'Signature does not match.');

        // Replay guard: a nonce is accepted once within the window (checked after the signature so junk can't fill the table).
        Database::run('DELETE FROM sync_nonces WHERE expires_at < UTC_TIMESTAMP()');
        try {
            Database::run('INSERT INTO sync_nonces (nonce,expires_at) VALUES (?, DATE_ADD(UTC_TIMESTAMP(), INTERVAL ? SECOND))', [hash('sha256', $cid . $nonce), Util::TS_WINDOW * 2]);
        } catch (\PDOException $e) {
            $fail('replayed', 'This request was already received.');
        }
        if (!RateLimit::ok('conn:' . $cid, 600)) throw new ApiError('rate_limited', 'Too many requests for this connection.', 429, ['retry_after' => 60]);
        if ($conn['status'] === 'paused') throw new ApiError('connection_paused', 'This connection is paused.', 503, ['retry_after' => 300]);
        if (!in_array($conn['status'], $allowed, true)) $fail('unknown_connection', 'Unknown or inactive connection.');
        return ['conn' => $conn, 'body' => $body];
    }
}
