<?php
namespace App\Services\Integration;

use App\Helpers\Database;

/**
 * The only thing the rest of Byabsayee knows about the integration: a handful of one-line calls after a change.
 * Every method is a no-op when the book has no active link, and can never throw into the caller's request.
 */
final class Hooks
{
    private static function conn(int $bookId): ?array { return Conn::linkedForBook($bookId); }

    private static function safe(callable $fn): void
    {
        try { $fn(); } catch (\Throwable $e) { error_log('[integration hook] ' . $e->getMessage() . ' @' . $e->getFile() . ':' . $e->getLine()); }
    }

    public static function emit(int $bookId, string $entity, int $id, string $op = 'auto'): void
    {
        self::safe(function () use ($bookId, $entity, $id, $op) { if ($c = self::conn($bookId)) Outbox::emit($c, $entity, $id, $op); });
    }

    /** A coupon that the store knows is archived, not deleted (D8). @return bool true = handled, the caller must not DELETE */
    public static function couponDelete(int $bookId, int $couponId): bool
    {
        $handled = false;
        self::safe(function () use ($bookId, $couponId, &$handled) {
            $c = self::conn($bookId);
            if (!$c) return;
            $l = Links::byLocal((int)$c['id'], 'coupon', $couponId);
            if (!$l) return;
            Database::run('UPDATE coupons SET is_active=0 WHERE id=? AND book_id=?', [$couponId, $bookId]);
            Outbox::emit($c, 'coupon', $couponId, 'archive');
            $handled = true;
        });
        return $handled;
    }

    /** Stock movement the store must hear about (sales/returns/purchases/adjustments that are not online orders). */
    public static function stock(int $bookId, int $productId, float $delta, string $reason, ?string $note = null): void
    {
        self::safe(function () use ($bookId, $productId, $delta, $reason, $note) {
            if (!($c = self::conn($bookId))) return;
            $d = (int)round($delta);
            if ($d !== 0) Outbox::emitStock($c, $productId, $d, $reason, $note);
        });
    }

    private static function isOnline(array $inv): bool { return ($inv['source'] ?? null) === 'online_store' || !empty($inv['sync_to_store']); }

    /** After an invoice (sale / purchase / POS) was created and its own stock effects were applied. */
    public static function invoiceCreated(int $invoiceId): void
    {
        self::safe(function () use ($invoiceId) {
            $inv = Database::row('SELECT * FROM invoices WHERE id=?', [$invoiceId]);
            if (!$inv || !($c = self::conn((int)$inv['book_id']))) return;
            if ($inv['type'] === 'sale' && self::isOnline($inv)) { Outbox::emit($c, 'order', $invoiceId, 'create'); return; }
            if (!in_array($inv['type'], ['sale', 'pos', 'purchase'], true)) return;
            $sign = $inv['type'] === 'purchase' ? 1 : -1; $reason = $inv['type'] === 'purchase' ? 'purchase' : 'sale';
            foreach (Database::query('SELECT product_id, SUM(qty) q FROM invoice_items WHERE invoice_id=? AND product_id IS NOT NULL GROUP BY product_id', [$invoiceId]) as $it) {
                self::stock((int)$inv['book_id'], (int)$it['product_id'], $sign * (float)$it['q'], $reason, $inv['invoice_no']);
            }
        });
    }

    /**
     * An invoice is being deleted from the book. Undoes its effect on stock/dues (this was missing before) and
     * tells the store. Online orders are cancelled and stay on record (D8). @return bool true when the invoice must stay visible
     */
    public static function invoiceDeleting(array $inv): bool
    {
        $keep = false;
        try {
            $bid = (int)$inv['book_id'];
            $c = self::conn($bid);
            $online = self::isOnline($inv);
            $pdo = Database::get();
            $pdo->beginTransaction();
            try {
                $stockBack = [];
                if (in_array($inv['type'], ['sale', 'pos'], true)) {
                    if (!empty($inv['stock_deducted']) && $inv['status'] !== 'cancelled') {
                        foreach (Database::query('SELECT product_id, SUM(qty) q FROM invoice_items WHERE invoice_id=? AND product_id IS NOT NULL GROUP BY product_id', [$inv['id']]) as $it) $stockBack[(int)$it['product_id']] = (float)$it['q'];
                    }
                    OrderService::cancel((int)$inv['id'], 'deleted');   // restocks, voids payments, cancels the due, clears report entries
                } elseif ($inv['type'] === 'purchase') {
                    foreach (Database::query('SELECT product_id, SUM(qty) q FROM invoice_items WHERE invoice_id=? AND product_id IS NOT NULL GROUP BY product_id', [$inv['id']]) as $it) {
                        \App\Services\InventoryService::remove($bid, (int)$it['product_id'], (float)$it['q']);
                        $stockBack[(int)$it['product_id']] = -(float)$it['q'];
                    }
                    try { Database::run("UPDATE debts SET status='cancelled' WHERE invoice_id=?", [$inv['id']]); } catch (\Throwable $e) {}
                    Database::run('DELETE FROM report_entries WHERE source_table="invoices" AND source_id=?', [$inv['id']]);
                }
                $pdo->commit();
            } catch (\Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
            if ($c) {
                if ($online && $inv['type'] === 'sale') { Outbox::emit($c, 'order', (int)$inv['id'], 'cancel'); $keep = true; }
                else foreach ($stockBack as $pid => $q) self::stock($bid, $pid, $q > 0 ? $q : $q, $q > 0 ? 'sale_cancel' : 'manual_adjustment', 'Deleted ' . $inv['invoice_no']);
            } elseif ($online && $inv['type'] === 'sale') $keep = true;
        } catch (\Throwable $e) { error_log('[integration invoiceDeleting] ' . $e->getMessage()); }
        return $keep;
    }

    public static function payment(int $paymentId): void
    {
        self::safe(function () use ($paymentId) {
            $p = Database::row('SELECT p.id, i.book_id, i.source, i.sync_to_store, i.type FROM payments p JOIN invoices i ON i.id=p.invoice_id WHERE p.id=?', [$paymentId]);
            if ($p && $p['type'] === 'sale' && self::isOnline($p) && ($c = self::conn((int)$p['book_id']))) Outbox::emit($c, 'payment', $paymentId, 'create');
        });
    }

    public static function returnCreated(int $returnId): void
    {
        self::safe(function () use ($returnId) {
            $r = Database::row('SELECT r.*, i.source, i.sync_to_store FROM returns r LEFT JOIN invoices i ON i.id=r.invoice_id WHERE r.id=?', [$returnId]);
            if (!$r || !($c = self::conn((int)$r['book_id']))) return;
            if ($r['type'] === 'sales_return' && $r['invoice_id'] && self::isOnline($r)) { Outbox::emit($c, 'return', $returnId, 'create'); return; }
            foreach (Database::query('SELECT product_id, SUM(qty) q FROM return_items WHERE return_id=? AND product_id IS NOT NULL GROUP BY product_id', [$returnId]) as $it) {
                self::stock((int)$r['book_id'], (int)$it['product_id'], ($r['type'] === 'sales_return' ? 1 : -1) * (float)$it['q'], $r['type'] === 'sales_return' ? 'return' : 'manual_adjustment', $r['return_no']);
            }
        });
    }

    /** Reverse the stock effect of a deleted return (the screen used to leave stock untouched). */
    public static function returnDeleting(array $r): void
    {
        self::safe(function () use ($r) {
            $bid = (int)$r['book_id'];
            $inv = $r['invoice_id'] ? Database::row('SELECT source, sync_to_store FROM invoices WHERE id=?', [$r['invoice_id']]) : null;
            foreach (Database::query('SELECT product_id, SUM(qty) q FROM return_items WHERE return_id=? AND product_id IS NOT NULL GROUP BY product_id', [$r['id']]) as $it) {
                $q = (float)$it['q'];
                if ($r['type'] === 'sales_return') { \App\Services\InventoryService::remove($bid, (int)$it['product_id'], $q); self::stock($bid, (int)$it['product_id'], -$q, 'manual_adjustment', 'Deleted ' . $r['return_no']); }
                else { \App\Services\InventoryService::receive($bid, (int)$it['product_id'], $q); self::stock($bid, (int)$it['product_id'], $q, 'manual_adjustment', 'Deleted ' . $r['return_no']); }
            }
        });
    }

    /** Whether a customer can be sent as an online order (the store insists on a name and a phone). */
    public static function customerCanOrder(int $customerId): bool
    {
        $c = Database::row('SELECT name, phone FROM customers WHERE id=?', [$customerId]);
        return $c && trim((string)$c['name']) !== '' && Util::phoneNorm($c['phone']) !== null;
    }

    public static function active(int $bookId): bool { try { return self::conn($bookId) !== null; } catch (\Throwable $e) { return false; } }
}
