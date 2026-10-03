<?php
namespace App\Services\Integration;

use App\Helpers\Database;
use App\Services\Integration\Mappers as M;

/**
 * The Book's own drift report (§9.3): compares what the website says it holds with what the book holds, per entity,
 * and compares stock per product. The book is the ledger of record, so a stock difference is corrected with a
 * flagged reconciliation_adjustment movement — never a silent overwrite — and only when nothing stock-related is
 * still in flight (otherwise the difference is just reported, it may be about to resolve itself).
 */
final class Reconcile
{
    /** entity => fields compared on both sides (shared fields only) */
    private const COMPARE = [
        'category' => ['name', 'is_active'], 'product' => ['name', 'sku', 'price', 'is_active'], 'customer' => ['name', 'phone', 'email'],
        'payment_method' => ['name', 'is_active'], 'coupon' => ['code', 'type', 'value', 'is_active'], 'order' => ['total', 'fulfilment_status'],
    ];

    private static function label(string $entity, array $f): string
    {
        return (string)($f['name'] ?? $f['code'] ?? $f['number'] ?? $f['sku'] ?? $entity);
    }

    /** @return array the report (also stored on the connection) */
    public static function run(array $conn, bool $correctStock = true): array
    {
        $cid = (int)$conn['id'];
        $rep = ['ok' => true, 'ran_at' => Util::nowIso(), 'error' => null, 'entities' => [], 'stock' => ['checked' => 0, 'corrected' => 0, 'deferred' => false, 'drift' => []], 'drift_total' => 0, 'truncated' => false];
        $lock = 'byabsayee_reconcile_' . $cid;
        $got = Database::row('SELECT GET_LOCK(?,0) l', [$lock]);
        if ((int)($got['l'] ?? 0) !== 1) { $rep['ok'] = false; $rep['error'] = 'A reconcile is already running.'; return $rep; }
        try {
            $inflight = (int)(Database::row("SELECT COUNT(*) c FROM sync_outbox WHERE conn_id=? AND status IN ('pending','sending') AND entity IN ('order','payment','return','stock_movement')", [$cid])['c'] ?? 0) > 0;
            $rep['stock']['deferred'] = $inflight;
            $deadline = microtime(true) + 50;
            $drift = 0;
            foreach (array_keys(self::COMPARE) as $entity) {
                if (!Conn::scopeAllows($conn, $entity)) continue;
                [$items, $err, $trunc] = Feed::all($conn, $entity, 60, $deadline);
                if ($err) { $rep['ok'] = false; $rep['error'] = 'The website did not answer for ' . $entity . ': ' . $err; break; }
                if ($trunc) $rep['truncated'] = true;
                $r = ['store_count' => count($items), 'missing_at_book' => [], 'missing_at_store' => [], 'different' => [], 'missing_at_book_n' => 0, 'missing_at_store_n' => 0, 'different_n' => 0];
                $seen = [];
                foreach ($items as $it) {
                    $u = $it['entity_uuid']; $seen[$u] = true;
                    $f = is_array($it['fields'] ?? null) ? $it['fields'] : [];
                    $link = Links::get($cid, $entity, $u);
                    $lid = $link && $link['local_id'] ? (int)$link['local_id'] : null;
                    if (!$lid) {
                        if (!empty($it['archived'])) continue;
                        $r['missing_at_book_n']++; if (count($r['missing_at_book']) < 8) $r['missing_at_book'][] = self::label($entity, $f);
                        continue;
                    }
                    $cls = Registry::mapper($entity);
                    $mine = $cls::build($conn, $lid);
                    if ($mine === null) continue;
                    $diff = [];
                    foreach (self::COMPARE[$entity] as $k) {
                        if (!array_key_exists($k, $f) || !array_key_exists($k, $mine)) continue;
                        if (Util::json($f[$k]) !== Util::json($mine[$k]) && (string)$f[$k] !== (string)$mine[$k]) $diff[] = $k;
                    }
                    if ($diff) { $r['different_n']++; if (count($r['different']) < 8) $r['different'][] = self::label($entity, $mine) . ' (' . implode(', ', $diff) . ')'; }
                    if ($entity === 'product' && isset($it['stock']['product'])) {
                        $rep['stock']['checked']++;
                        $bookQty = (int)round(\App\Services\InventoryService::onHand((int)$conn['book_id'], $lid));
                        $storeQty = (int)$it['stock']['product'];
                        if ($bookQty !== $storeQty) {
                            $d = ['product' => $mine['name'], 'book' => $bookQty, 'store' => $storeQty, 'corrected' => false];
                            if ($correctStock && !$inflight && Outbox::emitStock($conn, $lid, $bookQty - $storeQty, 'reconciliation_adjustment', 'Reconciliation: matched the book (' . $storeQty . ' → ' . $bookQty . ')')) { $d['corrected'] = true; $rep['stock']['corrected']++; }
                            $rep['stock']['drift'][] = $d;
                        }
                    }
                }
                // things this book SENT (local_version > 0) that the website no longer lists
                foreach (Database::query('SELECT * FROM sync_links WHERE conn_id=? AND entity=? AND local_id IS NOT NULL AND local_version>0 AND archived=0', [$cid, $entity]) as $l) {
                    if (isset($seen[$l['entity_uuid']])) continue;
                    if ($rep['truncated']) continue;       // an incomplete listing proves nothing about absence
                    $payload = Util::jsonDecode($l['last_payload']);
                    $r['missing_at_store_n']++; if (count($r['missing_at_store']) < 8) $r['missing_at_store'][] = self::label($entity, $payload);
                }
                $drift += $r['missing_at_book_n'] + $r['missing_at_store_n'] + $r['different_n'];
                $rep['entities'][$entity] = $r;
            }
            $rep['drift_total'] = $drift + count($rep['stock']['drift']);
        } catch (\Throwable $e) {
            error_log('[integration reconcile] ' . $e->getMessage() . ' @' . $e->getFile() . ':' . $e->getLine());
            $rep['ok'] = false; $rep['error'] = $e->getMessage();
        } finally { Database::run('SELECT RELEASE_LOCK(?)', [$lock]); }
        Conn::update($cid, ['last_reconcile' => Util::json($rep), 'last_reconcile_at' => Util::utcNow()]);
        Log::write($cid, 'system', 'reconcile', $rep['ok'] ? ('Reconciled: ' . $rep['drift_total'] . ' difference(s), ' . $rep['stock']['corrected'] . ' stock correction(s).') : ('Reconcile failed: ' . $rep['error']), $rep['ok'] && $rep['drift_total'] === 0);
        return $rep;
    }

    public static function due(array $conn, int $everySeconds = 3600): bool
    {
        return empty($conn['last_reconcile_at']) || (time() - (int)strtotime($conn['last_reconcile_at'] . ' UTC')) >= $everySeconds;
    }
}
