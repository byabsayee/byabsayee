<?php
namespace App\Services\Integration;

use App\Helpers\Database;
use App\Services\Integration\Mappers as M;

/**
 * Optional history import (D7/§16), both directions, with a dry run, batches, pause/resume and rollback of exactly one batch.
 *
 *  book_to_store  the book's past sales (and their payments/returns) and customers are announced to the website with
 *                 payload.import, so the website records them WITHOUT touching its stock.
 *  store_to_book  the book pulls the website's past orders/customers from its snapshot feed and records them as invoices
 *                 WITHOUT touching stock (stock_deducted=0) and without queuing anything back (Applying).
 *
 * Imported history never changes current quantities. Current stock is kept right by the normal movements + reconcile.
 */
final class ImportService
{
    public const ENTITIES = ['customers', 'orders'];

    public static function get(int $connId, int $id): ?array { return Database::row('SELECT * FROM sync_import_batches WHERE id=? AND conn_id=?', [$id, $connId]); }
    public static function recent(int $connId, int $n = 8): array { return Database::query('SELECT * FROM sync_import_batches WHERE conn_id=? ORDER BY id DESC LIMIT ' . (int)$n, [$connId]); }

    private static function range(?string $from, ?string $to): array
    {
        return [$from ? $from . ' 00:00:00' : null, $to ? $to . ' 23:59:59' : null];
    }

    // ─── plan (dry run: writes nothing except the plan row) ───────────────────

    /** @return array{0:bool,1:string,2:int} ok, message, batch id */
    public static function plan(array $conn, string $direction, array $entities, ?string $from, ?string $to, string $by): array
    {
        $cid = (int)$conn['id'];
        if ($conn['status'] !== 'active') return [false, 'The link must be active (connected and verified) before importing history.', 0];
        if (!in_array($direction, ['book_to_store', 'store_to_book'], true)) return [false, 'Choose a direction.', 0];
        $entities = array_values(array_intersect($entities, self::ENTITIES));
        if (!$entities) return [false, 'Choose what to import.', 0];
        foreach ([$from, $to] as $v) if ($v && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) return [false, 'Dates must look like 2026-01-31.', 0];
        if ($from && $to && $from > $to) return [false, 'The start date is after the end date.', 0];
        if (Database::row("SELECT 1 FROM sync_import_batches WHERE conn_id=? AND status IN ('running','paused')", [$cid])) return [false, 'Another import is still running or paused. Finish or roll it back first.', 0];
        if (!Conn::scopeAllows($conn, 'order') && in_array('orders', $entities, true)) return [false, 'This link has no permission to sync orders.', 0];
        if (!Conn::scopeAllows($conn, 'customer') && in_array('customers', $entities, true)) return [false, 'This link has no permission to sync customers.', 0];

        $plan = ['counts' => [], 'warnings' => []];
        try {
            $err = $direction === 'book_to_store' ? self::planBookToStore($conn, $entities, $from, $to, $plan) : self::planStoreToBook($conn, $entities, $from, $to, $plan);
        } catch (\Throwable $e) { error_log('[integration import plan] ' . $e->getMessage()); $err = 'The dry run failed: ' . $e->getMessage(); }
        if ($err) return [false, $err, 0];

        Database::run('INSERT INTO sync_import_batches (conn_id,direction,entities,date_from,date_to,status,plan,created_by,created_at) VALUES (?,?,?,?,?,?,?,?,UTC_TIMESTAMP())',
            [$cid, $direction, implode(',', $entities), $from ?: null, $to ?: null, 'dry_run', Util::json($plan), mb_substr($by, 0, 120)]);
        $id = (int)Database::lastId();
        Log::write($cid, 'system', 'import', 'Dry run #' . $id . ' (' . $direction . '): ' . json_encode($plan['counts']));
        return [true, 'Dry run finished. Nothing was written.', $id];
    }

    /** Book invoices that could be sent: live sales that never reached the store. */
    private static function candidateInvoiceSql(?string $from, ?string $to, array &$params): string
    {
        $w = 'i.book_id=? AND i.type="sale" AND i.deleted_at IS NULL AND i.source IS NULL AND i.sync_to_store=0 AND i.status<>"draft"
              AND NOT EXISTS (SELECT 1 FROM sync_links l WHERE l.conn_id=? AND l.entity="order" AND l.local_id=i.id AND l.last_payload IS NOT NULL)';
        if ($from) { $w .= ' AND i.date>=?'; $params[] = $from; }
        if ($to)   { $w .= ' AND i.date<=?'; $params[] = $to; }
        return $w;
    }

    private static function planBookToStore(array $conn, array $entities, ?string $from, ?string $to, array &$plan): ?string
    {
        $bid = (int)$conn['book_id']; $cid = (int)$conn['id'];
        if (in_array('orders', $entities, true)) {
            $params = [$bid, $cid];
            $w = self::candidateInvoiceSql($from, $to, $params);
            $ids = array_map('intval', array_column(Database::query("SELECT i.id FROM invoices i WHERE $w ORDER BY i.id LIMIT 20000", $params), 'id'));
            $ok = 0; $noCustomer = 0; $badTotals = 0; $cancelled = 0;
            foreach ($ids as $id) {
                $inv = Database::row('SELECT customer_id,status FROM invoices WHERE id=?', [$id]);
                if (!$inv['customer_id'] || !Hooks::customerCanOrder((int)$inv['customer_id'])) { $noCustomer++; continue; }
                $f = M\OrderMapper::build($conn, $id);
                if ($f === null || M\OrderMapper::validate($f)) { $badTotals++; continue; }
                if ($inv['status'] === 'cancelled') $cancelled++;
                $ok++;
            }
            $plan['counts'] += ['orders_to_send' => $ok, 'orders_without_customer_phone' => $noCustomer, 'orders_bad_totals' => $badTotals];
            if ($cancelled) $plan['counts']['orders_cancelled_included'] = $cancelled;
            if ($noCustomer) $plan['warnings'][] = $noCustomer . ' sale(s) have no customer with a name and phone number. The website needs both, so they will be skipped.';
            if ($badTotals) $plan['warnings'][] = $badTotals . ' sale(s) have totals that do not add up to the paisa and will be skipped.';
            $plan['warnings'][] = 'Products used by these sales that the website does not know yet are created there automatically (with their current quantity as the opening balance).';
            $plan['warnings'][] = 'Imported sales never change the website\'s stock. Sales sent this way are flagged as online orders from now on, so later payments, returns and cancellations sync too.';
        }
        if (in_array('customers', $entities, true)) {
            $n = (int)(Database::row('SELECT COUNT(*) c FROM customers c WHERE c.book_id=? AND c.deleted_at IS NULL AND c.phone IS NOT NULL AND c.phone<>"" AND NOT EXISTS (SELECT 1 FROM sync_links l WHERE l.conn_id=? AND l.entity="customer" AND l.local_id=c.id AND l.last_payload IS NOT NULL)', [$bid, $cid])['c'] ?? 0);
            $plan['counts']['customers_to_send'] = $n;
            $plan['warnings'][] = 'Customers sharing a phone or email with one the website already has are not merged: the website queues them for its owner to review.';
        }
        $plan['warnings'][] = 'Rolling this batch back removes the imported orders from the website. Customers that were sent stay there (they are harmless on their own).';
        return null;
    }

    private static function planStoreToBook(array $conn, array $entities, ?string $from, ?string $to, array &$plan): ?string
    {
        $bid = (int)$conn['book_id']; $cid = (int)$conn['id'];
        [$fromDb, $toDb] = self::range($from, $to);
        $deadline = microtime(true) + 40;
        $trunc = false;
        if (in_array('orders', $entities, true)) {
            [$items, $err, $t] = Feed::all($conn, 'order', 100, $deadline); $trunc = $trunc || $t;
            if ($err) return 'Could not read the website\'s orders: ' . $err;
            $new = 0; $known = 0; $clash = 0; $bad = 0; $missing = 0; $currency = 0; $skippedDates = 0;
            $bookCur = Conn::bookCurrency(Database::row('SELECT * FROM books WHERE id=?', [$bid]));
            foreach ($items as $it) {
                $f = is_array($it['fields'] ?? null) ? $it['fields'] : [];
                $placed = Util::dbFromIso($f['placed_at'] ?? null);
                if (($fromDb && $placed && $placed < $fromDb) || ($toDb && $placed && $placed > $toDb)) { $skippedDates++; continue; }
                if (Links::localFor($cid, 'order', $it['entity_uuid'])) { $known++; continue; }
                $number = mb_substr(trim((string)($f['number'] ?? '')), 0, 60);
                if ($number === '' || Database::row('SELECT 1 FROM invoices WHERE book_id=? AND external_number=? AND source="online_store"', [$bid, $number])) { $clash++; continue; }
                try { OrderService::checkTotals($f); } catch (\Throwable $e) { $bad++; continue; }
                if (strtoupper((string)($f['currency'] ?? $bookCur['code'])) !== $bookCur['code']) { $currency++; continue; }
                $miss = false;
                foreach ((array)($f['items'] ?? []) as $li) if (!empty($li['product_uuid']) && !Links::localFor($cid, 'product', $li['product_uuid'])) { $miss = true; break; }
                if ($miss) { $missing++; continue; }
                $new++;
            }
            $plan['counts'] += ['orders_to_create' => $new, 'orders_already_here' => $known, 'orders_number_clash' => $clash, 'orders_bad_totals' => $bad, 'orders_other_currency' => $currency, 'orders_missing_products' => $missing];
            if ($clash) $plan['warnings'][] = $clash . ' order(s) use a number that already exists here and will be skipped.';
            if ($bad) $plan['warnings'][] = $bad . ' order(s) have totals that do not add up and will be skipped.';
            if ($currency) $plan['warnings'][] = $currency . ' order(s) are in a different currency than this book. No conversion is ever done, so they will be skipped.';
            if ($missing) $plan['warnings'][] = $missing . ' order(s) use products that are not linked to this book yet and will be skipped. Match products first (Settings → Online store, or sync them), then import again.';
            $plan['warnings'][] = 'Imported orders never change this book\'s stock. Their payments and returns come with them; they appear in invoices, dues and reports like any sale.';
        }
        if (in_array('customers', $entities, true)) {
            [$items, $err, $t] = Feed::all($conn, 'customer', 100, $deadline); $trunc = $trunc || $t;
            if ($err) return 'Could not read the website\'s customers: ' . $err;
            $new = 0; $match = 0; $known = 0;
            foreach ($items as $it) {
                $f = is_array($it['fields'] ?? null) ? $it['fields'] : [];
                if (Links::localFor($cid, 'customer', $it['entity_uuid'])) { $known++; continue; }
                if (!empty($f['is_archived'])) continue;
                M\CustomerMapper::findMatch($bid, $f['phone'] ?? null, $f['email'] ?? null) ? $match++ : $new++;
            }
            $plan['counts'] += ['customers_to_create' => $new, 'customers_need_review' => $match, 'customers_already_here' => $known];
            if ($match) $plan['warnings'][] = $match . ' customer(s) share a phone/email with someone already in this book. They are not merged — they go to the review list.';
        }
        if ($trunc) $plan['warnings'][] = 'The website has a very large history; this dry run only looked at the first part of it. The real import still processes everything.';
        return null;
    }

    // ─── lifecycle ────────────────────────────────────────────────────────────

    public static function start(array $conn, int $id): string
    {
        $b = self::get((int)$conn['id'], $id);
        if (!$b || $b['status'] !== 'dry_run') return 'Only a fresh dry run can be started.';
        if ($conn['status'] !== 'active') return 'The link must be active to import.';
        if (Database::row("SELECT 1 FROM sync_import_batches WHERE conn_id=? AND status IN ('running','paused')", [$conn['id']])) return 'Another import is still running or paused.';
        Database::run("UPDATE sync_import_batches SET status='running', started_at=UTC_TIMESTAMP(), progress=?, cursor_json=? WHERE id=?",
            [Util::json(['done' => 0, 'skipped' => 0, 'failed' => 0, 'ids' => ['orders' => [], 'customers' => [], 'payments' => [], 'returns' => []], 'errors' => []]), Util::json(['stage' => 'customers', 'cursor' => '', 'last_id' => 0]), $id]);
        Log::write((int)$conn['id'], 'system', 'import', 'Import #' . $id . ' started.');
        return 'Import started.';
    }

    public static function pause(int $connId, int $id): void { Database::run("UPDATE sync_import_batches SET status='paused' WHERE id=? AND conn_id=? AND status='running'", [$id, $connId]); }
    public static function resume(int $connId, int $id): void { Database::run("UPDATE sync_import_batches SET status='running' WHERE id=? AND conn_id=? AND status='paused'", [$id, $connId]); }

    /** Processes one chunk of a running batch. Returns the batch row. */
    public static function step(array $conn, int $id, int $chunk = 25): ?array
    {
        $cid = (int)$conn['id'];
        $b = self::get($cid, $id);
        if (!$b || $b['status'] !== 'running') return $b;
        $lock = 'byabsayee_import_' . $id;
        $got = Database::row('SELECT GET_LOCK(?,0) l', [$lock]);
        if ((int)($got['l'] ?? 0) !== 1) return $b;
        try {
            $conn = Conn::find($cid) ?? $conn;
            if ($conn['status'] !== 'active') return $b;
            $prog = Util::jsonDecode($b['progress']) ?: ['done' => 0, 'skipped' => 0, 'failed' => 0, 'ids' => [], 'errors' => []];
            foreach (['orders', 'customers', 'payments', 'returns'] as $k) $prog['ids'][$k] = $prog['ids'][$k] ?? [];
            $cur = Util::jsonDecode($b['cursor_json']);
            $ents = explode(',', $b['entities']);
            $finished = $b['direction'] === 'book_to_store' ? self::stepBookToStore($conn, $b, $ents, $prog, $cur, $chunk) : self::stepStoreToBook($conn, $b, $ents, $prog, $cur, $chunk);
            $prog['errors'] = array_slice($prog['errors'], -30);
            Database::run('UPDATE sync_import_batches SET progress=?, cursor_json=?, status=?, finished_at=? WHERE id=?',
                [Util::json($prog), Util::json($cur), $finished ? 'done' : 'running', $finished ? Util::utcNow() : null, $id]);
            if ($finished) Log::write($cid, 'system', 'import', "Import #{$id} finished: {$prog['done']} done, {$prog['skipped']} skipped, {$prog['failed']} failed.");
        } catch (\Throwable $e) {
            error_log('[integration import] ' . $e->getMessage() . ' @' . $e->getFile() . ':' . $e->getLine());
            $prog = Util::jsonDecode(self::get($cid, $id)['progress'] ?? null); $prog['fatal'] = $e->getMessage();
            Database::run("UPDATE sync_import_batches SET status='failed', progress=? WHERE id=?", [Util::json($prog), $id]);
            Log::write($cid, 'system', 'import', "Import #{$id} failed: " . $e->getMessage(), false);
        } finally { Database::run('SELECT RELEASE_LOCK(?)', [$lock]); }
        return self::get($cid, $id);
    }

    // ── book → store ──

    private static function stepBookToStore(array $conn, array $b, array $ents, array &$prog, array &$cur, int $chunk): bool
    {
        $cid = (int)$conn['id']; $bid = (int)$conn['book_id']; $batch = (int)$b['id'];
        $imp = ['batch' => $batch, 'stock' => false];
        $left = $chunk;
        if (($cur['stage'] ?? 'customers') === 'customers') {
            if (!in_array('customers', $ents, true)) { $cur['stage'] = 'orders'; $cur['last_id'] = 0; }
            else {
                $rows = Database::query('SELECT c.id FROM customers c WHERE c.book_id=? AND c.id>? AND c.deleted_at IS NULL AND c.phone IS NOT NULL AND c.phone<>"" AND NOT EXISTS (SELECT 1 FROM sync_links l WHERE l.conn_id=? AND l.entity="customer" AND l.local_id=c.id AND l.last_payload IS NOT NULL) ORDER BY c.id LIMIT ' . (int)$left, [$bid, (int)($cur['last_id'] ?? 0), $cid]);
                foreach ($rows as $r) {
                    $cust = (int)$r['id'];
                    if (Outbox::emit($conn, 'customer', $cust, 'create', ['import' => $imp])) { $prog['done']++; $prog['ids']['customers'][] = $cust; Database::run('UPDATE customers SET import_batch=? WHERE id=?', [$batch, $cust]); }
                    else $prog['skipped']++;
                    $cur['last_id'] = $cust; $left--;
                }
                if (count($rows) < $chunk) { $cur['stage'] = 'orders'; $cur['last_id'] = 0; }
            }
        }
        if ($left > 0 && ($cur['stage'] ?? '') === 'orders') {
            if (!in_array('orders', $ents, true)) { $cur['stage'] = 'done'; }
            else {
                $params = [$bid, $cid];
                $w = self::candidateInvoiceSql($b['date_from'], $b['date_to'], $params);
                $params[] = (int)($cur['last_id'] ?? 0);
                $rows = Database::query("SELECT i.id, i.customer_id FROM invoices i WHERE $w AND i.id>? ORDER BY i.id LIMIT " . (int)$left, $params);
                foreach ($rows as $r) {
                    $inv = (int)$r['id']; $cur['last_id'] = $inv; $left--;
                    if (!$r['customer_id'] || !Hooks::customerCanOrder((int)$r['customer_id'])) { $prog['skipped']++; continue; }
                    $pdo = Database::get(); $pushed = false;
                    try {
                        $pdo->beginTransaction();
                        Database::run('UPDATE invoices SET sync_to_store=1, import_batch=? WHERE id=?', [$batch, $inv]);
                        // the customer first, as part of the same history (so it isn't announced as a "live" customer)
                        if (!Links::byLocal($cid, 'customer', (int)$r['customer_id']) || empty(Links::byLocal($cid, 'customer', (int)$r['customer_id'])['last_payload']))
                            Outbox::emit($conn, 'customer', (int)$r['customer_id'], 'create', ['import' => $imp]);
                        if (!Outbox::emit($conn, 'order', $inv, 'create', ['import' => $imp])) throw new \RuntimeException('The order could not be queued (see the activity log).');
                        $prog['ids']['orders'][] = $inv; $pushed = true;
                        foreach (Database::query('SELECT id FROM payments WHERE invoice_id=? AND status="recorded"', [$inv]) as $p) { if (Outbox::emit($conn, 'payment', (int)$p['id'], 'create', ['import' => $imp])) $prog['ids']['payments'][] = (int)$p['id']; }
                        foreach (Database::query('SELECT id FROM returns WHERE invoice_id=? AND type="sales_return" AND deleted_at IS NULL', [$inv]) as $rt) { if (Outbox::emit($conn, 'return', (int)$rt['id'], 'create', ['import' => $imp])) $prog['ids']['returns'][] = (int)$rt['id']; }
                        $pdo->commit();
                        $prog['done']++;
                    } catch (\Throwable $e) {
                        if ($pdo->inTransaction()) $pdo->rollBack();
                        if ($pushed) array_pop($prog['ids']['orders']);
                        $prog['failed']++; $prog['errors'][] = 'Sale #' . $inv . ': ' . $e->getMessage();
                    }
                }
                if (count($rows) < $chunk) $cur['stage'] = 'done';
            }
        }
        return ($cur['stage'] ?? '') === 'done';
    }

    // ── store → book ──

    private static function stepStoreToBook(array $conn, array $b, array $ents, array &$prog, array &$cur, int $chunk): bool
    {
        $cid = (int)$conn['id']; $batch = (int)$b['id'];
        [$fromDb, $toDb] = self::range($b['date_from'], $b['date_to']);
        $order = ['customers', 'orders', 'payments', 'returns'];
        $entityOf = ['customers' => 'customer', 'orders' => 'order', 'payments' => 'payment', 'returns' => 'return'];
        $stage = $cur['stage'] ?? 'customers';
        while (true) {
            $want = $stage === 'customers' ? in_array('customers', $ents, true) : in_array('orders', $ents, true);
            if ($stage === 'done') return true;
            if (!$want) { $stage = $order[array_search($stage, $order, true) + 1] ?? 'done'; $cur['cursor'] = ''; continue; }
            break;
        }
        $page = Feed::page($conn, $entityOf[$stage], (string)($cur['cursor'] ?? ''), $chunk);
        if ($page['error']) throw new \RuntimeException('The website did not answer: ' . $page['error']);
        $pdo = Database::get();
        foreach ($page['items'] as $it) {
            $u = $it['entity_uuid']; $f = is_array($it['fields'] ?? null) ? $it['fields'] : [];
            if (Links::localFor($cid, $entityOf[$stage], $u)) { $prog['skipped']++; continue; }
            try {
                if ($stage === 'customers' && !empty($f['is_archived'])) { $prog['skipped']++; continue; }
                if ($stage === 'orders') {
                    $placed = Util::dbFromIso($f['placed_at'] ?? null);
                    if (($fromDb && $placed && $placed < $fromDb) || ($toDb && $placed && $placed > $toDb)) continue;
                }
                if (in_array($stage, ['payments', 'returns'], true)) {
                    if (($f['status'] ?? 'recorded') === 'void') { $prog['skipped']++; continue; }
                    $oid = Links::localFor($cid, 'order', (string)($f['order_uuid'] ?? ''));
                    $inv = $oid ? Database::row('SELECT import_batch FROM invoices WHERE id=?', [$oid]) : null;
                    if (!$inv || (int)$inv['import_batch'] !== $batch) continue;      // only the history this batch brought in
                }
                $imp = ['batch' => $batch, 'stock' => false, 'origin' => 'book_pull'];
                $fake = ['event_id' => Util::uuid4(), 'entity' => $entityOf[$stage], 'entity_uuid' => $u, 'version' => (int)($it['version'] ?? 1), 'occurred_at' => Util::nowIso(), 'payload' => ['fields' => $f, 'field_ts' => (object)[], 'import' => $imp]];
                $cls = Registry::mapper($entityOf[$stage]);
                $GLOBALS['__integration_conn_id'] = $cid;
                $pdo->beginTransaction();
                $lid = Applying::run(fn () => $cls::apply($conn, 'create', null, $f, ['uuid' => $u, 'event' => $fake, 'force_new' => false]));
                if (!$lid) throw new \RuntimeException('Nothing was created.');
                $link = Links::create($cid, $entityOf[$stage], (int)$lid, $u);
                $built = $cls::build($conn, (int)$lid);
                Links::update((int)$link['id'], ['remote_version' => (int)($it['version'] ?? 0), 'last_payload' => $built !== null ? Util::json($built) : null, 'content_hash' => $built !== null ? sha1(Util::json($built)) : null, 'last_synced_at' => Util::utcNow()]);
                if ($stage === 'orders') Database::run('UPDATE invoices SET import_batch=? WHERE id=?', [$batch, (int)$lid]);
                if ($stage === 'customers') Database::run('UPDATE customers SET import_batch=? WHERE id=?', [$batch, (int)$lid]);
                $pdo->commit();
                $prog['done']++; $prog['ids'][$stage][] = (int)$lid;
            } catch (ConflictResult $c) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $kind = $c->kind;
                $cidc = Conflicts::add($cid, $kind, $entityOf[$stage], $u, null, null, $c->local, $kind === 'customer_match' ? ['fields' => $f] : $c->remote, $c->getMessage());
                if ($kind === 'customer_match') Database::run('UPDATE sync_conflicts SET remote_data=? WHERE id=?', [Util::json(['fields' => $f]), $cidc]);
                $prog['skipped']++;
            } catch (Reject $r) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $prog['skipped']++; $prog['errors'][] = $entityOf[$stage] . ' ' . substr($u, 0, 8) . ': ' . $r->getMessage();
            } catch (\Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $prog['failed']++; $prog['errors'][] = $entityOf[$stage] . ' ' . substr($u, 0, 8) . ': ' . $e->getMessage();
            }
        }
        $cur['stage'] = $stage;
        if ($page['more']) { $cur['cursor'] = $page['cursor']; return false; }
        $next = $order[array_search($stage, $order, true) + 1] ?? 'done';
        $cur['stage'] = $next; $cur['cursor'] = '';
        return $next === 'done';
    }

    // ─── rollback of exactly one batch ────────────────────────────────────────

    public static function rollback(array $conn, int $id, string $by): string
    {
        $cid = (int)$conn['id'];
        $b = self::get($cid, $id);
        if (!$b || !in_array($b['status'], ['done', 'paused', 'failed', 'running'], true)) return 'This batch cannot be rolled back.';
        $lock = 'byabsayee_import_' . $id;
        $got = Database::row('SELECT GET_LOCK(?,0) l', [$lock]);
        if ((int)($got['l'] ?? 0) !== 1) return 'The import is busy right now — try again in a moment.';
        $prog = Util::jsonDecode($b['progress']); $n = 0;
        try {
            if ($b['direction'] === 'book_to_store') {
                foreach ((array)($prog['ids']['orders'] ?? []) as $inv) {
                    $inv = (int)$inv;
                    Outbox::emit($conn, 'order', $inv, 'cancel', ['import' => ['batch' => $id, 'stock' => false, 'rollback' => true]]);
                    $paymentIds = array_column(Database::query('SELECT id FROM payments WHERE invoice_id=?', [$inv]), 'id');
                    $returnIds  = array_column(Database::query('SELECT id FROM returns WHERE invoice_id=?', [$inv]), 'id');
                    foreach ($paymentIds as $x) Database::run('DELETE FROM sync_links WHERE conn_id=? AND entity="payment" AND local_id=?', [$cid, $x]);
                    foreach ($returnIds as $x)  Database::run('DELETE FROM sync_links WHERE conn_id=? AND entity="return" AND local_id=?', [$cid, $x]);
                    Database::run('DELETE FROM sync_links WHERE conn_id=? AND entity="order" AND local_id=?', [$cid, $inv]);
                    Database::run('UPDATE invoices SET sync_to_store=0, import_batch=NULL WHERE id=? AND import_batch=?', [$inv, $id]);
                    $n++;
                }
                Database::run('UPDATE customers SET import_batch=NULL WHERE import_batch=? AND book_id=?', [$id, $conn['book_id']]);
            } else {
                $pdo = Database::get(); $pdo->beginTransaction();
                try {
                    foreach (array_reverse((array)($prog['ids']['orders'] ?? [])) as $inv) {
                        $row = Database::row('SELECT id FROM invoices WHERE id=? AND book_id=? AND import_batch=?', [(int)$inv, $conn['book_id'], $id]);
                        if (!$row) continue;
                        Applying::run(fn () => OrderService::cancel((int)$inv, 'import rollback'));   // keeps the record (D8); stock was never taken, so none comes back
                        $n++;
                    }
                    foreach ((array)($prog['ids']['customers'] ?? []) as $c) Database::run('UPDATE customers SET deleted_at=? WHERE id=? AND book_id=? AND import_batch=?', [now(), (int)$c, $conn['book_id'], $id]);
                    $pdo->commit();
                } catch (\Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
            }
            Database::run("UPDATE sync_import_batches SET status='rolled_back', finished_at=UTC_TIMESTAMP() WHERE id=?", [$id]);
        } catch (\Throwable $e) {
            error_log('[integration import rollback] ' . $e->getMessage());
            return 'Rollback failed: ' . $e->getMessage();
        } finally { Database::run('SELECT RELEASE_LOCK(?)', [$lock]); }
        Log::write($cid, 'system', 'import', "Import #{$id} rolled back by {$by} ({$n} order(s)).");
        return $b['direction'] === 'book_to_store'
            ? "Rolled back {$n} order(s): the website is being told to remove them."
            : "Rolled back {$n} order(s): they are cancelled in this book (records kept) and the imported customers are archived.";
    }
}
