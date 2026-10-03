<?php
namespace App\Services\Integration;

use App\Helpers\Database;

/**
 * Connection lifecycle: pending → verifying → active ⇄ paused → revoked (D2/D12).
 *   1. the owner creates the link in the book (domain + authority + scopes) and gets a pairing code;
 *   2. the store calls connect/handshake (pairing code, or signed with manual credentials);
 *   3. the book proves the store controls its domain (GET /.well-known/erp-verify) and completes the link.
 */
final class Lifecycle
{
    public const VERIFY_BACKOFF = [30, 60, 120, 300, 600, 900, 1800];

    // ─── step 1: create / reconnect ───────────────────────────────────────────

    /**
     * @return array{conn:array,credentials:array}|string  an error message on failure
     */
    public static function create(array $book, string $siteUrl, string $authority, array $scopes, ?int $userId, ?array $tax = null)
    {
        $p = Util::parseSiteUrl($siteUrl);
        if (is_string($p)) return $p;
        $host = $p['host'];
        if (!in_array($authority, ['book', 'site'], true)) return 'Choose who is authoritative for currency, timezone and tax.';
        $existing = Conn::forBook((int)$book['id']);
        if ($existing && $existing['status'] !== 'revoked') return 'This book is already linked to a website. Disconnect it first.';
        if ($existing && $existing['site_domain'] !== $host) return 'This book was linked to ' . $existing['site_domain'] . ' before. Remove that old link first (Disconnect → Remove link) to connect a different website.';
        if (($other = Conn::byDomain($host)) && (!$existing || (int)$other['id'] !== (int)$existing['id'])) return $host . ' is already connected to another book. One website connects to exactly one book.';

        $pdo = Database::get();
        $pdo->beginTransaction();
        try {
            if ($existing) {                       // re-link the same pair: the SAME connection_id keeps the store's identity links valid
                $id = (int)$existing['id'];
                Conn::update($id, ['status' => 'pending', 'authority' => $authority, 'scopes' => Util::json(Conn::validScopes($scopes)), 'last_error' => null, 'verified_at' => null,
                    'activated_at' => null, 'verify_attempts' => 0, 'next_verify_at' => null, 'created_by' => $userId]);
            } else {
                Database::run('INSERT INTO integration_connections (book_id,connection_id,site_domain,status,authority,scopes,created_by,created_at) VALUES (?,?,?,?,?,?,?,UTC_TIMESTAMP())',
                    [$book['id'], Util::uuid4(), $host, 'pending', $authority, Util::json(Conn::validScopes($scopes)), $userId]);
                $id = (int)Database::lastId();
            }
            // a reconnect keeps the tax/delivery settings already agreed
            if ($tax !== null && !$existing) { $c = Conn::find($id); $s = Conn::shared($c); $s['tax'] = array_merge($s['tax'], $tax); Conn::setShared($id, $s); }
            $creds = Conn::applyNewCredentials($id);
            $code = Conn::newPairingCode($id);
            $pdo->commit();
        } catch (\Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); return 'Could not create the link: ' . $e->getMessage(); }
        $conn = Conn::find($id);
        Log::write($id, 'system', 'connect', 'Link created for ' . $host . ' (authority: ' . $authority . '). Waiting for the website to pair.');
        return ['conn' => $conn, 'credentials' => ['connection_id' => $conn['connection_id'], 'api_key' => $creds['api_key'], 'secrets' => $creds['secrets'], 'pairing_code' => $code]];
    }

    // ─── step 2: handshake (called by the store) ──────────────────────────────

    /**
     * @param bool $withCredentials true for the pairing-code flow (the store has no credentials yet)
     * @return array the JSON response body
     */
    public static function handshake(array $conn, array $req, bool $withCredentials): array
    {
        $cid = (int)$conn['id'];
        if (!in_array($conn['status'], ['pending', 'verifying'], true)) throw new ApiError('invalid_state', 'This link is not waiting for a website to pair.', 409);
        if (!empty($req['api_version']) && $req['api_version'] !== Util::API_VERSION) throw new ApiError('unsupported_version', 'This book speaks API ' . Util::API_VERSION . '.', 400);
        $site = is_array($req['site'] ?? null) ? $req['site'] : [];
        if (!Util::testMode()) {
            $host = strtolower((string)parse_url((string)($site['url'] ?? ''), PHP_URL_HOST));
            if ($host === '' || $host !== $conn['site_domain']) throw new ApiError('domain_mismatch', 'This link was created for ' . $conn['site_domain'] . ', not ' . ($host ?: 'an unknown site') . '.', 409);
        }
        $book = Database::row('SELECT * FROM books WHERE id=?', [$conn['book_id']]);
        if ($conn['authority'] === 'site') {          // the website wins on currency: never silently re-label an existing ledger
            $code = strtoupper((string)($site['currency_code'] ?? ''));
            $cur = Conn::bookCurrency($book);
            if ($code !== '' && $code !== $cur['code'] && Conn::bookHasInvoices((int)$book['id'])) {
                $msg = "The website uses {$code} but this book already has invoices in {$cur['code']}. No automatic conversion is done — make the book authoritative or use a book without invoices.";
                Conn::update($cid, ['last_error' => mb_substr($msg, 0, 500)]);
                throw new ApiError('currency_conflict', $msg, 409);
            }
        }
        $caps = array_values(array_filter((array)($req['capabilities'] ?? []), 'is_string'));
        Conn::update($cid, ['status' => 'verifying', 'api_version' => Util::API_VERSION, 'peer_module_version' => isset($req['module_version']) ? mb_substr((string)$req['module_version'], 0, 40) : null,
            'peer_capabilities' => Util::json($caps), 'site_info' => Util::json($site), 'last_error' => null, 'verified_at' => null, 'verify_attempts' => 0,
            'next_verify_at' => gmdate('Y-m-d H:i:s', time() + 5), 'pairing_hash' => null, 'pairing_expires_at' => null]);
        $conn = Conn::find($cid);
        Log::write($cid, 'in', 'connect', 'Handshake accepted from ' . $conn['site_domain'] . '.', true, null, 200);
        $out = ['ok' => true, 'connection_id' => $conn['connection_id'], 'api_version' => Util::API_VERSION, 'module_version' => Util::MODULE_VERSION,
            'capabilities' => Util::CAPABILITIES, 'scopes' => Conn::scopes($conn), 'book' => Conn::bookBlock($book, $conn)];
        if ($withCredentials) {
            $out['api_key'] = Conn::apiKey($conn);
            $out['secrets'] = ['site_to_book' => Conn::secretFromSite($conn), 'book_to_site' => Conn::secretToSite($conn)];
        }
        return $out;
    }

    // ─── step 3: prove the domain, then complete ──────────────────────────────

    /** @return array{0:bool,1:string} */
    public static function verifyAndComplete(array $conn): array
    {
        $cid = (int)$conn['id'];
        $conn = Conn::find($cid);
        if (!$conn || !in_array($conn['status'], ['verifying', 'active'], true)) return [false, 'This link is not waiting to be verified.'];
        $lock = Database::row('SELECT GET_LOCK(?,0) l', ['byabsayee_sync_verify_' . $cid]);
        if ((int)($lock['l'] ?? 0) !== 1) return [false, 'Verification is already running.'];
        try {
            $fail = function (string $msg) use ($cid, $conn): array {
                $n = (int)$conn['verify_attempts'] + 1;
                Conn::update($cid, ['last_error' => mb_substr($msg, 0, 500), 'verify_attempts' => $n,
                    'next_verify_at' => gmdate('Y-m-d H:i:s', time() + self::VERIFY_BACKOFF[min($n - 1, count(self::VERIFY_BACKOFF) - 1)])]);
                Log::write($cid, 'system', 'connect', 'Verification failed: ' . $msg, false);
                return [false, $msg];
            };
            $secret = Conn::secretFromSite($conn);
            if (!$secret) return $fail('The signing secret is missing.');
            $token = Util::token(24);
            $res = Http::verifyCall($conn, $token);
            $j = $res['json'];
            if (!$res['ok'] || !is_array($j)) return $fail('The website did not answer the ownership check: ' . ($res['error'] ?: 'no answer') . '. Is the website reachable over HTTPS, and has it been told to pair?');
            if (!hash_equals((string)$conn['connection_id'], (string)($j['connection_id'] ?? '')) || !hash_equals($token, (string)($j['token'] ?? ''))) return $fail('The website answered for a different connection or token.');
            if (!hash_equals(Crypto::verifyProof($secret, $token), strtolower((string)($j['proof'] ?? '')))) return $fail('The ownership proof was wrong — whoever answers on that domain does not hold the secret.');
            Conn::update($cid, ['verified_at' => Util::utcNow()]);

            $book = Database::row('SELECT * FROM books WHERE id=?', [$conn['book_id']]);
            $done = Http::store($conn, 'POST', 'connect/complete', ['authority' => $conn['authority'], 'scopes' => Conn::scopes($conn), 'capabilities' => Util::CAPABILITIES, 'book' => Conn::bookBlock($book, $conn)]);
            if (!$done['ok']) return $fail('The website could not finish linking: ' . ($done['error'] ?: 'HTTP ' . $done['status']));
            $note = '';
            if ($conn['authority'] === 'site') $note = self::adoptSite($conn, $book);
            Conn::update($cid, ['status' => 'active', 'activated_at' => Util::utcNow(), 'last_error' => null, 'verify_attempts' => 0, 'next_verify_at' => null]);
            Log::write($cid, 'system', 'connect', 'Linked and active.' . $note, true);
            return [true, 'Linked. The website now reviews and matches your products and customers before syncing starts.' . $note];
        } finally { Database::run('SELECT RELEASE_LOCK(?)', ['byabsayee_sync_verify_' . $cid]); }
    }

    /** Authority = website: the book takes the website's currency, timezone and tax. */
    private static function adoptSite(array $conn, array $book): string
    {
        $site = Util::jsonDecode($conn['site_info']); $bid = (int)$book['id']; $did = [];
        $code = strtoupper(trim((string)($site['currency_code'] ?? '')));
        if (preg_match('/^[A-Z]{3}$/', $code) && $code !== Conn::bookCurrency($book)['code']) {
            $sym = mb_substr((string)($site['currency_symbol'] ?? $code), 0, 5);
            Database::run('UPDATE book_currencies SET is_default=0 WHERE book_id=?', [$bid]);
            $have = Database::row('SELECT id FROM book_currencies WHERE book_id=? AND code=?', [$bid, $code]);
            if ($have) Database::run('UPDATE book_currencies SET is_default=1, symbol=? WHERE id=?', [$sym, $have['id']]);
            else Database::run('INSERT INTO book_currencies (book_id,code,symbol,name,is_default,sort_order) VALUES (?,?,?,?,1,0)', [$bid, $code, $sym, $code]);
            Database::run('UPDATE books SET currency=?, currency_symbol=? WHERE id=?', [$code, $sym, $bid]);
            $did[] = 'currency';
        }
        $tz = (string)($site['timezone'] ?? '');
        if ($tz !== '' && in_array($tz, timezone_identifiers_list(), true) && $tz !== $book['timezone']) { Database::run('UPDATE books SET timezone=? WHERE id=?', [$tz, $bid]); $did[] = 'timezone'; }
        if (is_array($site['tax'] ?? null)) {
            $t = $site['tax']; $s = Conn::shared($conn);
            $s['tax'] = ['enabled' => !empty($t['enabled']), 'rate' => number_format((float)($t['rate'] ?? 0), 3, '.', ''), 'inclusive' => !empty($t['inclusive']), 'label' => (string)($t['label'] ?? 'Tax')];
            Conn::setShared((int)$conn['id'], $s); $did[] = 'tax';
        }
        return $did ? ' Adopted from the website: ' . implode(', ', $did) . '.' : '';
    }

    // ─── pause / resume / disconnect / rotate ─────────────────────────────────

    public static function pause(array $conn): void
    {
        if ($conn['status'] !== 'active') return;
        Conn::update((int)$conn['id'], ['status' => 'paused']);
        Log::write((int)$conn['id'], 'system', 'state', 'Sync paused.');
    }

    public static function resume(array $conn): void
    {
        if ($conn['status'] !== 'paused') return;
        Conn::update((int)$conn['id'], ['status' => 'active']);
        Log::write((int)$conn['id'], 'system', 'state', 'Sync resumed.');
        Outbox::flush(Conn::find((int)$conn['id']), 2);
    }

    /** Local revoke: credentials are wiped, every record and the identity links stay so the same pair can resume later. */
    public static function markRevoked(array $conn, string $why): void
    {
        Conn::update((int)$conn['id'], ['status' => 'revoked', 'api_key_hash' => null, 'api_key_enc' => null, 'secret_site_to_book_enc' => null, 'secret_book_to_site_enc' => null,
            'pairing_hash' => null, 'pairing_expires_at' => null, 'last_error' => mb_substr($why, 0, 500)]);
        Log::write((int)$conn['id'], 'system', 'state', $why);
    }

    /** Book-initiated unlink. Tells the store when it can be reached. @return string a message for the screen */
    public static function disconnect(array $conn, bool $notify = true): string
    {
        $msg = 'Disconnected.';
        if ($notify && in_array($conn['status'], ['active', 'paused', 'verifying'], true)) {
            $res = Http::store($conn, 'POST', 'disconnect', ['reason' => 'book_disconnect']);
            if (!$res['ok']) $msg = 'Disconnected here, but the website could not be reached to tell it (' . ($res['error'] ?: 'no answer') . '). Disconnect it there too.';
        }
        self::markRevoked($conn, 'Disconnected by a book user.');
        return $msg;
    }

    /** Remove a revoked link completely (forgets the pairing history; the identity links are deleted). */
    public static function remove(array $conn): bool
    {
        if ($conn['status'] !== 'revoked') return false;
        Database::run('DELETE FROM integration_connections WHERE id=?', [$conn['id']]);
        return true;
    }

    /** Book-initiated key rotation: the store swaps to the new credentials, then so do we. @return array{0:bool,1:string} */
    public static function rotate(array $conn): array
    {
        if (!in_array($conn['status'], ['active', 'paused'], true)) return [false, 'Nothing to rotate — not linked.'];
        $key = Crypto::newApiKey(); $s1 = Crypto::newSecret(); $s2 = Crypto::newSecret();
        $res = Http::store($conn, 'POST', 'connect/rotate', ['api_key' => $key, 'secrets' => ['site_to_book' => $s1, 'book_to_site' => $s2]]);
        if (!$res['ok']) return [false, 'The website did not accept the new credentials: ' . ($res['error'] ?: 'no answer')];
        Conn::update((int)$conn['id'], ['api_key_hash' => hash('sha256', $key), 'api_key_enc' => Crypto::enc($key), 'secret_site_to_book_enc' => Crypto::enc($s1), 'secret_book_to_site_enc' => Crypto::enc($s2)]);
        Log::write((int)$conn['id'], 'system', 'rotate', 'Credentials rotated (requested by the book).');
        return [true, 'Credentials rotated.'];
    }

    /** Store-initiated rotation: authenticated with the OLD credentials, returns the new ones. */
    public static function rotateInbound(array $conn): array
    {
        $key = Crypto::newApiKey(); $s1 = Crypto::newSecret(); $s2 = Crypto::newSecret();
        Conn::update((int)$conn['id'], ['api_key_hash' => hash('sha256', $key), 'api_key_enc' => Crypto::enc($key), 'secret_site_to_book_enc' => Crypto::enc($s1), 'secret_book_to_site_enc' => Crypto::enc($s2)]);
        Log::write((int)$conn['id'], 'in', 'rotate', 'Credentials rotated (requested by the website).');
        return ['ok' => true, 'api_key' => $key, 'secrets' => ['site_to_book' => $s1, 'book_to_site' => $s2]];
    }
}
