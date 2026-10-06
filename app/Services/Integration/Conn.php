<?php
namespace App\Services\Integration;

use App\Helpers\Database;

/**
 * The connection row (one per book, D2) and everything that is decided per connection:
 * credentials, scopes, shared tax/delivery configuration and the settings block sent to the store.
 */
final class Conn
{
    public const DEFAULT_SHARED = [
        'tax'      => ['enabled' => false, 'rate' => '0.000', 'inclusive' => false, 'label' => 'Tax'],
        'delivery' => ['inside_dhaka' => '70.00', 'suburbs' => '100.00', 'outside_dhaka' => '130.00', 'free_weight_kg' => '1.00', 'extra_per_kg' => '20.00'],
    ];

    public static function find(int $id): ?array { return Database::row('SELECT * FROM integration_connections WHERE id=?', [$id]); }
    public static function forBook(int $bookId): ?array { return Database::row('SELECT * FROM integration_connections WHERE book_id=?', [$bookId]); }
    public static function byUuid(string $uuid): ?array { return Database::row('SELECT * FROM integration_connections WHERE connection_id=?', [$uuid]); }
    public static function byDomain(string $host): ?array
    {
        self::releaseDeletedBooks(strtolower($host));   // a deleted book must never keep a website to itself
        return Database::row('SELECT * FROM integration_connections WHERE site_domain=?', [strtolower($host)]);
    }

    /**
     * Books are only soft-deleted, so their website link used to stay behind and block that website from connecting to any other book.
     * Removes the links (and their sync bookkeeping, by cascade) of deleted books; optionally only the one for $host.
     */
    public static function releaseDeletedBooks(?string $host = null): void
    {
        try {
            Database::run('DELETE c FROM integration_connections c JOIN books b ON b.id=c.book_id WHERE b.deleted_at IS NOT NULL' . ($host !== null ? ' AND c.site_domain=?' : ''), $host !== null ? [$host] : []);
        } catch (\Throwable $e) { error_log('[integration release] ' . $e->getMessage()); }
    }

    /** Events are queued while a connection is active or paused. Cached per request/book. */
    public static function linkedForBook(int $bookId): ?array
    {
        static $cache = [];
        if (!array_key_exists($bookId, $cache)) {
            try {
                $c = self::forBook($bookId);
                $cache[$bookId] = ($c && in_array($c['status'], ['active', 'paused'], true)) ? $c : null;
            } catch (\Throwable $e) { $cache[$bookId] = null; }   // tables missing (migration not run yet) => integration off
        }
        return $cache[$bookId];
    }

    public static function forgetCache(): void { /* static cache lives per request; nothing to do in web, tests use fresh processes */ }

    public static function update(int $id, array $cols): void
    {
        if (!$cols) return;
        $set = []; $vals = [];
        foreach ($cols as $k => $v) { $set[] = "`$k`=?"; $vals[] = $v; }
        $vals[] = $id;
        Database::run('UPDATE integration_connections SET ' . implode(',', $set) . ' WHERE id=?', $vals);
    }

    // ─── credentials ──────────────────────────────────────────────────────────

    public static function apiKey(array $c): ?string { return Crypto::dec($c['api_key_enc'] ?? null); }
    /** Secret the STORE signs with (site_to_book) — we verify with it. */
    public static function secretFromSite(array $c): ?string { return Crypto::dec($c['secret_site_to_book_enc'] ?? null); }
    /** Secret the BOOK signs with (book_to_site). */
    public static function secretToSite(array $c): ?string { return Crypto::dec($c['secret_book_to_site_enc'] ?? null); }

    /** @return array{api_key:string,secrets:array{site_to_book:string,book_to_site:string}} */
    public static function applyNewCredentials(int $id): array
    {
        $key = Crypto::newApiKey(); $s1 = Crypto::newSecret(); $s2 = Crypto::newSecret();
        self::update($id, ['api_key_hash' => hash('sha256', $key), 'api_key_enc' => Crypto::enc($key),
            'secret_site_to_book_enc' => Crypto::enc($s1), 'secret_book_to_site_enc' => Crypto::enc($s2)]);
        return ['api_key' => $key, 'secrets' => ['site_to_book' => $s1, 'book_to_site' => $s2]];
    }

    /** New pairing code, valid 30 minutes, single use. Returns the plain code (shown once). */
    public static function newPairingCode(int $id): string
    {
        $code = Crypto::newPairingCode();
        self::update($id, ['pairing_hash' => hash('sha256', $code), 'pairing_expires_at' => gmdate('Y-m-d H:i:s', time() + 1800)]);
        return $code;
    }

    // ─── scopes ───────────────────────────────────────────────────────────────

    public static function validScopes($scopes): array
    {
        $out = [];
        foreach ((array)$scopes as $s) if (is_string($s) && isset(Util::SCOPE_ENTITIES[$s])) $out[$s] = $s;
        return array_values($out) ?: array_keys(Util::SCOPE_ENTITIES);
    }

    public static function scopes(array $c): array
    {
        $s = Util::jsonDecode($c['scopes'] ?? null);
        return $s ? $s : array_keys(Util::SCOPE_ENTITIES);
    }

    public static function scopeAllows(array $c, string $entity): bool
    {
        $have = self::scopes($c);
        foreach (Util::SCOPE_ENTITIES as $scope => $ents) if (in_array($entity, $ents, true)) return in_array($scope, $have, true);
        return false;
    }

    public static function peerCan(array $c, string $cap): bool { return in_array($cap, Util::jsonDecode($c['peer_capabilities'] ?? null), true); }

    // ─── shared configuration (tax + delivery singletons) ─────────────────────

    public static function shared(array $c): array
    {
        // Always read the live value: several events in one request update it one after another.
        $fresh = isset($c['id']) ? Database::row('SELECT shared_config FROM integration_connections WHERE id=?', [$c['id']]) : null;
        $cfg = Util::jsonDecode($fresh ? $fresh['shared_config'] : ($c['shared_config'] ?? null));
        return [
            'tax'      => array_merge(self::DEFAULT_SHARED['tax'], (array)($cfg['tax'] ?? [])),
            'delivery' => array_merge(self::DEFAULT_SHARED['delivery'], (array)($cfg['delivery'] ?? [])),
        ];
    }

    public static function setShared(int $id, array $shared): void
    {
        self::update($id, ['shared_config' => Util::json($shared)]);
    }

    // ─── what we tell the store about this book ───────────────────────────────

    public static function bookCurrency(array $book): array
    {
        $d = Database::row('SELECT code,symbol FROM book_currencies WHERE book_id=? AND is_default=1 LIMIT 1', [$book['id']]);
        return ['code' => strtoupper($d['code'] ?? ($book['currency'] ?? 'BDT')), 'symbol' => $d['symbol'] ?? ($book['currency_symbol'] ?? '৳')];
    }

    public static function bookBlock(array $book, array $conn): array
    {
        $cur = self::bookCurrency($book);
        $t = self::shared($conn)['tax'];
        return ['currency_code' => $cur['code'], 'currency_symbol' => $cur['symbol'], 'timezone' => $book['timezone'] ?: 'Asia/Dhaka',
            'tax' => ['enabled' => (bool)$t['enabled'], 'rate' => number_format((float)$t['rate'], 3, '.', ''), 'inclusive' => (bool)$t['inclusive'], 'label' => (string)$t['label']]];
    }

    /** Has this book already posted invoices (so changing its currency would corrupt the books)? */
    public static function bookHasInvoices(int $bookId): bool
    {
        return (bool)Database::row('SELECT 1 FROM invoices WHERE book_id=? AND deleted_at IS NULL LIMIT 1', [$bookId]);
    }

    public static function counts(int $connId): array
    {
        $r = Database::query('SELECT status, COUNT(*) c FROM sync_outbox WHERE conn_id=? GROUP BY status', [$connId]);
        $m = []; foreach ($r as $x) $m[$x['status']] = (int)$x['c'];
        $open = Database::row("SELECT COUNT(*) c FROM sync_conflicts WHERE conn_id=? AND status='open'", [$connId]);
        return ['pending' => ($m['pending'] ?? 0) + ($m['sending'] ?? 0), 'dead' => $m['dead'] ?? 0, 'done' => $m['done'] ?? 0,
            'conflict' => $m['conflict'] ?? 0, 'open_conflicts' => (int)($open['c'] ?? 0)];
    }
}
