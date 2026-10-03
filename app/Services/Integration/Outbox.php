<?php
namespace App\Services\Integration;

use App\Helpers\Database;

/**
 * Events are written to the outbox in the same request (and DB transaction) as the business change, then
 * delivered in signed, batched, retried requests (D6: at-least-once, idempotent by event_id, ordered per entity).
 */
final class Outbox
{
    public const BACKOFF = [30, 120, 600, 3600, 21600];          // 30s, 2m, 10m, 1h, 6h, then dead-letter
    public const RETRYABLE = ['unknown_entity', 'dependency_missing', 'busy', 'rate_limited', 'temporarily_unavailable'];
    /** Not the event's fault: the store isn't ready (setup unfinished / paused). Wait without burning attempts. */
    public const HOLD = ['temporarily_unavailable', 'connection_paused'];

    // ─── emit ─────────────────────────────────────────────────────────────────

    /**
     * Queue an event for a local row. $op: 'auto' (create the first time, update afterwards, skipped when nothing
     * changed) or create|archive|restore|cancel|void. A fault here must never break the user's action.
     */
    public static function emit(array $conn, string $entity, int $localId, string $op = 'auto', array $extra = []): ?string
    {
        try { return self::emitRaw($conn, $entity, $localId, $op, $extra); }
        catch (\Throwable $e) {
            error_log('[integration emit] ' . $entity . '#' . $localId . ' ' . $op . ': ' . $e->getMessage() . ' @' . $e->getFile() . ':' . $e->getLine());
            Log::write((int)$conn['id'], 'system', 'emit', 'Could not queue ' . $entity . ' ' . $op . ': ' . $e->getMessage(), false);
            return null;
        }
    }

    private static function emitRaw(array $conn, string $entity, int $localId, string $op, array $extra): ?string
    {
        if (Applying::active() || !in_array($conn['status'], ['active', 'paused'], true) || !Conn::scopeAllows($conn, $entity)) return null;
        $cls  = Registry::mapper($entity);
        $cid  = (int)$conn['id'];
        $full = $cls::build($conn, $localId);
        $uuid = $cls::uuidFor($conn, $localId);
        $link = $uuid ? (Links::get($cid, $entity, $uuid) ?? Links::create($cid, $entity, $localId, $uuid)) : Links::byLocal($conn['id'], $entity, $localId);

        // Something that existed before the connection and was never shared is not ours to announce on a plain edit.
        if (!$link && $op === 'auto') return null;
        if ($full === null) {
            if (!$link || !in_array($op, ['archive', 'void', 'cancel'], true)) return null;
            $full = Util::jsonDecode($link['last_payload']);
        }
        if ($full && ($err = $cls::validate($full))) {
            Conflicts::add($cid, 'totals', $entity, $link['entity_uuid'] ?? null, $localId, null, $full, null, "Not sent to the store: {$err}");
            Log::write($cid, 'system', 'emit', "Refused to send {$entity} #{$localId}: {$err}", false);
            return null;
        }
        $isNew = $link === null || empty($link['last_payload']);
        if ($op === 'create' && $link && !empty($link['last_payload'])) $op = 'update';
        if ($isNew) {
            foreach ($cls::dependencies($conn, $localId) as [$depEntity, $depId]) self::ensureEmitted($conn, $depEntity, (int)$depId);
            if ($link === null) $link = Links::create($cid, $entity, $localId);
        }
        if ($op === 'auto') $op = $isNew ? 'create' : 'update';
        $prev   = Util::jsonDecode($link['last_payload']);
        $fields = $op === 'create' ? $full : Util::diff($prev, $full);
        if ($op === 'archive' || $op === 'restore') $fields = ['is_active' => $op === 'restore'];
        if ($op === 'cancel') $fields = $cls::cancelFields();
        if ($op === 'void')   $fields = $cls::voidFields();
        if ($op === 'update' && !$fields) return null;                  // nothing the store cares about changed
        if ($op === 'create') $extra = array_merge($cls::extra($conn, $localId), $extra);

        $now = Util::nowIso();
        $ts = Util::jsonDecode($link['field_ts']); $sendTs = [];
        foreach (array_keys($fields) as $f) { $ts[$f] = $now; $sendTs[$f] = $now; }
        $version = (int)$link['local_version'] + 1;
        $eventId = Util::uuid4();
        self::insert($conn, [
            'event_id' => $eventId, 'connection_id' => $conn['connection_id'], 'origin' => 'book', 'entity' => $entity, 'entity_uuid' => $link['entity_uuid'],
            'op' => $op, 'version' => $version, 'base_version' => (int)$link['remote_version'], 'occurred_at' => $now,
            'payload' => array_merge(['fields' => $fields, 'field_ts' => (object)$sendTs], $extra),
        ]);
        Links::update((int)$link['id'], ['local_version' => $version, 'last_payload' => Util::json($full), 'field_ts' => Util::json($ts), 'content_hash' => sha1(Util::json($full)),
            'archived' => $op === 'archive' ? 1 : ($op === 'restore' ? 0 : (int)$link['archived'])]);
        self::flushSoon();
        return $eventId;
    }

    /** A stock movement has no table of its own: it is announced directly (reason: sale|sale_cancel|return|purchase|manual_adjustment). */
    public static function emitStock(array $conn, int $productId, int $delta, string $reason, ?string $note = null): ?string
    {
        try {
            if ($delta === 0 || Applying::active() || !in_array($conn['status'], ['active', 'paused'], true) || !Conn::scopeAllows($conn, 'stock_movement')) return null;
            $cid = (int)$conn['id'];
            $plink = Links::byLocal($cid, 'product', $productId);
            if (!$plink || empty($plink['last_payload'])) return null;           // the store doesn't know this product
            $uuid = Util::uuid4(); $now = Util::nowIso();
            Links::create($cid, 'stock_movement', null, $uuid);
            $fields = ['product_uuid' => $plink['entity_uuid'], 'variant_uuid' => null, 'delta' => $delta, 'reason' => $reason, 'note' => $note ? mb_substr($note, 0, 255) : null];
            $ts = []; foreach (array_keys($fields) as $k) $ts[$k] = $now;
            self::insert($conn, ['event_id' => Util::uuid4(), 'connection_id' => $conn['connection_id'], 'origin' => 'book', 'entity' => 'stock_movement', 'entity_uuid' => $uuid,
                'op' => 'create', 'version' => 1, 'base_version' => 0, 'occurred_at' => $now, 'payload' => ['fields' => $fields, 'field_ts' => (object)$ts]]);
            self::flushSoon();
            return $uuid;
        } catch (\Throwable $e) { error_log('[integration emitStock] ' . $e->getMessage()); return null; }
    }

    private static function ensureEmitted(array $conn, string $entity, int $localId): void
    {
        $l = Links::byLocal($conn['id'], $entity, $localId);
        if ($l && !empty($l['last_payload'])) return;
        self::emit($conn, $entity, $localId, 'create');
    }

    private static function insert(array $conn, array $env): void
    {
        Database::run('INSERT INTO sync_outbox (conn_id,event_id,entity,entity_uuid,op,version,envelope,status,next_attempt_at,created_at) VALUES (?,?,?,?,?,?,?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())',
            [$conn['id'], $env['event_id'], $env['entity'], $env['entity_uuid'], $env['op'], $env['version'], Util::json($env), 'pending']);
    }

    /** One opportunistic flush after the user has their page (the worker is the safety net). */
    public static function flushSoon(): void
    {
        static $done = false;
        if ($done || PHP_SAPI === 'cli') return;
        $done = true;
        register_shutdown_function(function () {
            try {
                if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
                if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
                foreach (Database::query("SELECT * FROM integration_connections WHERE status='active'") as $c) self::flush($c, 2);
            } catch (\Throwable $e) { error_log('[integration flushSoon] ' . $e->getMessage()); }
        });
    }

    // ─── flush ────────────────────────────────────────────────────────────────

    public static function backoff(int $attempts): int { return self::BACKOFF[min(max($attempts - 1, 0), count(self::BACKOFF) - 1)]; }

    /** @return array{sent:int,failed:int,dead:int,batches:int} */
    public static function flush(array $conn, int $maxBatches = 5): array
    {
        $stats = ['sent' => 0, 'failed' => 0, 'dead' => 0, 'batches' => 0];
        $cid = (int)$conn['id'];
        $conn = Conn::find($cid) ?? $conn;
        if ($conn['status'] !== 'active') return $stats;
        $lock = 'byabsayee_sync_flush_' . $cid;
        $got = Database::row('SELECT GET_LOCK(?,0) l', [$lock]);
        if ((int)($got['l'] ?? 0) !== 1) return $stats;
        try {
            Database::run("UPDATE sync_outbox SET status='pending' WHERE conn_id=? AND status='sending' AND next_attempt_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 5 MINUTE)", [$cid]);
            for ($i = 0; $i < $maxBatches; $i++) {
                $rows = Database::query("SELECT * FROM sync_outbox WHERE conn_id=? AND status='pending' AND next_attempt_at<=UTC_TIMESTAMP() ORDER BY id ASC LIMIT 200", [$cid]);
                if (!$rows) break;
                $batch = []; $bytes = 0;
                $due = array_flip(array_map('intval', array_column($rows, 'id')));
                foreach ($rows as $r) {
                    // per-entity ordering: never send an event while an earlier one for the same entity is waiting elsewhere
                    $wait = false;
                    foreach (Database::query("SELECT id FROM sync_outbox WHERE conn_id=? AND entity=? AND entity_uuid=? AND id<? AND status IN ('pending','sending')", [$cid, $r['entity'], $r['entity_uuid'], $r['id']]) as $e) {
                        if (!isset($due[(int)$e['id']])) { $wait = true; break; }
                    }
                    if ($wait) continue;
                    $bytes += strlen($r['envelope']);
                    if (count($batch) >= Util::BATCH_MAX || ($batch && $bytes > 900000)) break;
                    $batch[] = $r;
                }
                if (!$batch) break;
                $in = implode(',', array_map('intval', array_column($batch, 'id')));
                Database::run("UPDATE sync_outbox SET status='sending', next_attempt_at=UTC_TIMESTAMP() WHERE id IN ($in)");
                $stats['batches']++;
                if (!self::sendBatch($conn, $batch, $stats)) break;     // store unreachable/refusing: stop and let backoff work
            }
        } finally { Database::run('SELECT RELEASE_LOCK(?)', [$lock]); }
        return $stats;
    }

    private static function sendBatch(array $conn, array $batch, array &$stats): bool
    {
        $cid = (int)$conn['id']; $batchId = Util::uuid4();
        $envs = array_map(fn ($r) => json_decode($r['envelope'], true), $batch);
        $res = Http::store($conn, 'POST', 'events', ['batch_id' => $batchId, 'events' => $envs], ['batch_id' => $batchId]);
        $by = [];
        foreach ((array)($res['json']['results'] ?? []) as $r) if (!empty($r['event_id'])) $by[$r['event_id']] = $r;

        if (!$res['ok'] || !isset($res['json']['results'])) {
            $msg = $res['error'] ?: 'HTTP ' . $res['status'];
            $code = (string)($res['json']['error']['code'] ?? '');
            $hold = in_array($code, self::HOLD, true) || $res['status'] === 503;
            foreach ($batch as $r) $hold ? self::hold($r, $msg) : self::fail($r, $msg, $stats, ($res['status'] === 429 || $res['status'] >= 500 || $res['status'] === 0) ? 'transient' : 'auth');
            Log::write($cid, 'out', 'events', 'Batch of ' . count($batch) . ' failed: ' . $msg, false, null, $res['status'] ?: null, ['batch_id' => $batchId, 'code' => $code]);
            Conn::update($cid, ['last_error' => mb_substr($msg, 0, 500)]);
            if ($res['status'] === 410 || $code === 'connection_revoked') Lifecycle::markRevoked($conn, 'The store revoked this connection.');
            return false;
        }
        foreach ($batch as $r) {
            $x = $by[$r['event_id']] ?? null; $result = (string)($x['result'] ?? '');
            if (in_array($result, ['applied', 'duplicate'], true)) {
                Database::run("UPDATE sync_outbox SET status='done', sent_at=UTC_TIMESTAMP(), last_error=NULL WHERE id=?", [$r['id']]); $stats['sent']++;
            } elseif ($result === 'conflict') {
                Database::run("UPDATE sync_outbox SET status='conflict', sent_at=UTC_TIMESTAMP(), last_error=? WHERE id=?", [mb_substr((string)($x['message'] ?? 'Queued as a conflict at the store.'), 0, 500), $r['id']]);
                Conflicts::add($cid, 'other', $r['entity'], $r['entity_uuid'], null, $r['event_id'], null, json_decode($r['envelope'], true)['payload'] ?? null, 'The store queued this change as a conflict: ' . ($x['message'] ?? 'needs review there.'));
                $stats['sent']++;
            } elseif ($result === 'rejected') {
                $code = (string)($x['code'] ?? 'rejected'); $msg = $code . ': ' . ($x['message'] ?? 'Rejected by the store.');
                if (in_array($code, self::HOLD, true)) self::hold($r, $msg);
                elseif ($code === 'insufficient_stock' && $r['entity'] === 'stock_movement') {
                    // The goods really did leave the book's shelf; the store's cached count is what's wrong. Its next
                    // reconciliation pulls the book's quantity, so this is information, not a failure to retry.
                    Database::run("UPDATE sync_outbox SET status='done', sent_at=UTC_TIMESTAMP(), last_error=? WHERE id=?", [mb_substr($msg, 0, 500), $r['id']]);
                    Conflicts::add($cid, 'stock_drift', 'stock_movement', $r['entity_uuid'], null, $r['event_id'], null, null, 'The store has less stock than the book for a product (' . ($x['message'] ?? 'insufficient stock') . '). Its next reconciliation will correct the store to the book\'s quantity.');
                } elseif ($code === 'insufficient_stock' && $r['entity'] === 'order') {
                    Database::run("UPDATE sync_outbox SET status='done', sent_at=UTC_TIMESTAMP(), last_error=? WHERE id=?", [mb_substr($msg, 0, 500), $r['id']]);
                    Conflicts::add($cid, 'oversold', 'order', $r['entity_uuid'], null, $r['event_id'], null, null, 'The store has less stock than this order needs — ' . ($x['message'] ?? 'insufficient stock') . '. The order exists only in the book.');
                } elseif (!empty($x['retry']) || in_array($code, self::RETRYABLE, true)) self::fail($r, $msg, $stats, 'transient');
                else self::fail($r, $msg, $stats, 'permanent');
            } else self::fail($r, 'The store did not report a result for this event.', $stats, 'transient');
        }
        Conn::update($cid, ['last_sync_at' => Util::utcNow(), 'last_error' => null]);
        Log::write($cid, 'out', 'events', 'Sent ' . count($batch) . ' event(s)', true, null, $res['status'], ['batch_id' => $batchId, 'results' => array_count_values(array_map(fn ($r) => $r['result'] ?? '?', (array)$res['json']['results']))]);
        return true;
    }

    /** The store isn't ready: look again in 5 minutes without using up an attempt. */
    private static function hold(array $row, string $msg): void
    {
        Database::run("UPDATE sync_outbox SET status='pending', last_error=?, next_attempt_at=DATE_ADD(UTC_TIMESTAMP(), INTERVAL 300 SECOND) WHERE id=?", [mb_substr($msg, 0, 500), $row['id']]);
    }

    private static function fail(array $row, string $msg, array &$stats, string $kind): void
    {
        $attempts = (int)$row['attempts'] + 1;
        if ($kind === 'permanent' || $attempts > count(self::BACKOFF)) {
            Database::run("UPDATE sync_outbox SET status='dead', attempts=?, last_error=? WHERE id=?", [$attempts, mb_substr($msg, 0, 500), $row['id']]);
            $stats['dead']++; return;
        }
        Database::run("UPDATE sync_outbox SET status='pending', attempts=?, last_error=?, next_attempt_at=DATE_ADD(UTC_TIMESTAMP(), INTERVAL ? SECOND) WHERE id=?",
            [$attempts, mb_substr($msg, 0, 500), self::backoff($attempts), $row['id']]);
        $stats['failed']++;
    }

    public static function retry(int $connId, int $id): void { Database::run("UPDATE sync_outbox SET status='pending', attempts=0, next_attempt_at=UTC_TIMESTAMP() WHERE id=? AND conn_id=? AND status='dead'", [$id, $connId]); }
    public static function retryAllDead(int $connId): int { return Database::run("UPDATE sync_outbox SET status='pending', attempts=0, next_attempt_at=UTC_TIMESTAMP() WHERE conn_id=? AND status='dead'", [$connId])->rowCount(); }
    /** "Discard" keeps the row (never dropped silently) but takes it out of the queue for good. */
    public static function discard(int $connId, int $id): void { Database::run("UPDATE sync_outbox SET status='done', last_error=CONCAT('[discarded by user] ', COALESCE(last_error,'')) WHERE id=? AND conn_id=? AND status='dead'", [$id, $connId]); }
}
