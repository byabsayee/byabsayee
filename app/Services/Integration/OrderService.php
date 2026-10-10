<?php
namespace App\Services\Integration;

use App\Helpers\Database;
use App\Services\{ActivityLogger, InventoryService, InsufficientStockException};
use App\Controllers\DuesController;

/**
 * Accounting side effects of an online order, written once so the sync and the book's own screens agree.
 * An online order is an ordinary sales invoice (invoices.source='online_store' when it came from the store),
 * so dues, reports, PDFs and the dashboard keep working with no special cases.
 *
 * Callers wrap these in a DB transaction. Nothing here emits sync events (loop prevention): the caller decides.
 */
final class OrderService
{
    public const FULFILMENT = ['placed', 'processing', 'shipped', 'delivered', 'cancelled'];

    // ─── numbering ────────────────────────────────────────────────────────────

    public static function nextInvoiceNo(int $bookId): string
    {
        $d = Database::row('SELECT invoice_prefix, invoice_counter FROM book_business_details WHERE book_id=? FOR UPDATE', [$bookId]);
        if ($d) {
            $no = ($d['invoice_prefix'] ?: 'INV') . '-' . str_pad((string)$d['invoice_counter'], 6, '0', STR_PAD_LEFT);
            Database::run('UPDATE book_business_details SET invoice_counter=invoice_counter+1 WHERE book_id=?', [$bookId]);
            return $no;
        }
        $n = (int)(Database::row("SELECT COUNT(*)+1 AS n FROM invoices WHERE book_id=? AND type='sale'", [$bookId])['n'] ?? 1);
        return 'INV-' . str_pad((string)$n, 6, '0', STR_PAD_LEFT);
    }

    public static function nextReturnNo(int $bookId): string
    {
        $n = (int)(Database::row('SELECT COUNT(*)+1 AS n FROM returns WHERE book_id=?', [$bookId])['n'] ?? 1);
        do {
            $no = 'RET-' . str_pad((string)$n++, 5, '0', STR_PAD_LEFT);
        } while (Database::row('SELECT 1 FROM returns WHERE book_id=? AND return_no=?', [$bookId, $no]));
        return $no;
    }

    // ─── totals integrity (to the paisa) ──────────────────────────────────────

    /** @return array{0:float,1:float,2:float,3:float,4:float,5:bool} subtotal, discount, tax, delivery, total, inclusive */
    public static function checkTotals(array $f, ?array $fallback = null): array
    {
        $get = fn ($k) => array_key_exists($k, $f) ? $f[$k] : ($fallback[$k] ?? null);
        $items = $f['items'] ?? null;
        $sum = 0.0;
        if (is_array($items)) {
            foreach ($items as $i) {
                if (!isset($i['price'], $i['quantity']) || (int)$i['quantity'] < 1) throw new Reject('invalid_payload', 'An order line is missing price or quantity.');
                $line = round((float)$i['price'] * (int)$i['quantity'], 2);
                if (isset($i['subtotal']) && abs((float)$i['subtotal'] - $line) > 0.004) throw new ConflictResult('totals', 'A line subtotal does not equal price × quantity.', null, $f);
                $sum += $line;
            }
        }
        $subtotal = round((float)$get('subtotal'), 2); $discount = round((float)($get('discount') ?? 0), 2); $tax = round((float)($get('tax') ?? 0), 2);
        $delivery = round((float)($get('delivery_charge') ?? 0), 2); $total = round((float)$get('total'), 2); $incl = !empty($get('tax_inclusive'));
        if (is_array($items) && abs($sum - $subtotal) > 0.004) throw new ConflictResult('totals', 'Line items add up to ' . Util::money($sum) . ' but the subtotal says ' . Util::money($subtotal) . '.', null, $f);
        $expected = round($subtotal - $discount + ($incl ? 0 : $tax) + $delivery, 2);
        if (abs($expected - $total) > 0.004) throw new ConflictResult('totals', 'Subtotal, discount, tax and delivery give ' . Util::money($expected) . ' but the total says ' . Util::money($total) . '. Totals must match to the paisa.', null, $f);
        return [$subtotal, $discount, $tax, $delivery, $total, $incl];
    }

    // ─── create ───────────────────────────────────────────────────────────────

    /** Resolve order lines to local products. @return array<int,array{0:?int,1:array}> */
    private static function resolveLines(array $conn, array $items): array
    {
        $out = [];
        foreach ($items as $i) {
            $pid = null;
            if (!empty($i['product_uuid'])) {
                $pid = Links::localFor($conn['id'], 'product', $i['product_uuid']);
                if (!$pid) throw new Reject('dependency_missing', 'A product on this order is not known here yet.', true);
            }
            $out[] = [$pid, $i];
        }
        return $out;
    }

    private static function insertLines(int $invoiceId, array $lines): void
    {
        foreach ($lines as [$pid, $i]) {
            $qty = (int)$i['quantity']; $price = round((float)$i['price'], 2);
            Database::run('INSERT INTO invoice_items (invoice_id,product_id,description,variant,qty,unit_price,discount_pct,line_total) VALUES (?,?,?,?,?,?,0,?)',
                [$invoiceId, $pid, mb_substr((string)($i['name'] ?? 'Item'), 0, 255) ?: 'Item', ($i['variant_label'] ?? null) ? mb_substr((string)$i['variant_label'], 0, 120) : null, $qty, $price, round($price * $qty, 2)]);
        }
    }

    private static function itemsMeta(array $items): array
    {
        $m = [];
        foreach ($items as $i) $m[] = ['line' => $i['line'] ?? null, 'variant_uuid' => $i['variant_uuid'] ?? null, 'sku' => $i['sku'] ?? null,
            'variant_label' => $i['variant_label'] ?? null, 'is_preorder' => !empty($i['is_preorder'])];
        return $m;
    }

    private static function sellLines(int $bookId, array $lines): void
    {
        foreach ($lines as [$pid, $i]) {
            if (!$pid) continue;
            try { InventoryService::sell($bookId, $pid, (float)(int)$i['quantity'], true); }
            catch (InsufficientStockException $e) {
                $n = Database::row('SELECT name FROM products WHERE id=?', [$pid])['name'] ?? 'a product';
                throw new Reject('insufficient_stock', "{$n}: " . $e->getMessage());
            }
        }
    }

    /** Store -> book: a new order becomes a sales invoice. @return int invoice id */
    public static function createFromOrder(array $conn, array $f, array $ctx): int
    {
        $bid = (int)$conn['book_id']; $tz = Mappers\BaseMapperTz::tz($conn);
        foreach (['number', 'total', 'subtotal', 'items'] as $k) if (!array_key_exists($k, $f) || $f[$k] === null || $f[$k] === '') throw new Reject('invalid_payload', "Missing required field '$k'.");
        if (!is_array($f['items']) || !$f['items']) throw new Reject('invalid_payload', 'An order needs at least one item.');
        $sh = $f['shipping'] ?? ($f['contact'] ?? []);
        if (empty($sh['name'] ?? ($f['contact']['name'] ?? null)) || empty($sh['phone'] ?? ($f['contact']['phone'] ?? null))) throw new Reject('invalid_payload', 'The order needs a recipient name and phone.');
        [$subtotal, $discount, $tax, $delivery, $total, $incl] = self::checkTotals($f);

        $number = mb_substr(trim((string)$f['number']), 0, 60);
        if (Database::row('SELECT id FROM invoices WHERE book_id=? AND external_number=? AND source="online_store"', [$bid, $number])) throw new Reject('duplicate_number', "An order numbered {$number} already exists here.");

        $cur = Conn::bookCurrency(Database::row('SELECT * FROM books WHERE id=?', [$bid]));
        $code = strtoupper((string)($f['currency'] ?? $cur['code']));
        $symbol = $cur['symbol'];
        if ($code !== $cur['code']) {
            $other = Database::row('SELECT symbol FROM book_currencies WHERE book_id=? AND code=?', [$bid, $code]);
            if (!$other) throw new ConflictResult('currency', "The order is in {$code} but this book works in {$cur['code']}. No automatic conversion is done.", null, $f);
            $symbol = $other['symbol'];
        }

        $lines = self::resolveLines($conn, $f['items']);
        $custId = null;
        if (!empty($f['customer_uuid'])) {
            $custId = Links::localFor($conn['id'], 'customer', $f['customer_uuid']);
            if (!$custId) throw new Reject('dependency_missing', 'The customer on this order is not known here yet.', true);
        }
        $pmLabel = null;
        if (!empty($f['payment_method_uuid'])) {
            $pm = Links::localFor($conn['id'], 'payment_method', $f['payment_method_uuid']);
            if (!$pm) throw new Reject('dependency_missing', 'The payment method on this order is not known here yet.', true);
            $pmLabel = Mappers\PaymentMethodMapper::labelFor($conn, $pm);
        }
        if (!empty($f['coupon_uuid']) && !Links::localFor($conn['id'], 'coupon', $f['coupon_uuid'])) throw new Reject('dependency_missing', 'The coupon on this order is not known here yet.', true);

        $fulfil = $f['fulfilment_status'] ?? 'placed';
        if (!in_array($fulfil, self::FULFILMENT, true)) throw new Reject('invalid_payload', 'Unknown fulfilment_status.');
        $imp = $ctx['event']['payload']['import'] ?? ($f['import'] ?? null);
        $imported = !empty($imp);
        $cancelled = $fulfil === 'cancelled';
        $deduct = !$imported && !$cancelled;
        if ($deduct) self::sellLines($bid, $lines);

        $placedUtc = Util::dbFromIso($f['placed_at'] ?? null) ?: gmdate('Y-m-d H:i:s');
        $local = Util::toZone($placedUtc, $tz) ?: date('Y-m-d H:i:s');
        $couponCode = !empty($f['coupon_code']) ? strtoupper(mb_substr((string)$f['coupon_code'], 0, 30)) : null;
        $book = Database::row('SELECT theme_color FROM books WHERE id=?', [$bid]);
        $invoiceNo = self::nextInvoiceNo($bid);
        $meta = ['contact' => $f['contact'] ?? null, 'shipping' => $f['shipping'] ?? null, 'billing' => $f['billing'] ?? null, 'delivery_area' => $f['delivery_area'] ?? 'inside_dhaka',
            'placed_at' => $f['placed_at'] ?? null, 'coupon_uuid' => $f['coupon_uuid'] ?? null, 'items' => self::itemsMeta($f['items']), 'import' => $imported ? $imp : null];

        Database::run(
            'INSERT INTO invoices (book_id,type,invoice_no,customer_id,date,subtotal,discount,points_discount,coupon_code,coupon_discount,privilege_discount,delivery_charge,handling_charge,
                delivery_type,rounding,tax,total,paid,status,note_customer,payment_method,theme_color,currency_symbol,currency_code,public_token,created_by,created_at,
                source,fulfilment_status,external_number,tax_inclusive,stock_deducted,order_meta)
             VALUES (?,?,?,?,?,?,?,0,?,?,0,?,0,"own",0,?,?,0,?,?,?,?,?,?,?,NULL,?,"online_store",?,?,?,?,?)',
            [$bid, 'sale', $invoiceNo, $custId, substr($local, 0, 10), $subtotal, $couponCode ? 0 : $discount, $couponCode, $couponCode ? $discount : 0, $delivery,
             $tax, $total, $cancelled ? 'cancelled' : 'sent', self::note($f['notes'] ?? null), $pmLabel, $book['theme_color'] ?? '#1a6b4a', $symbol, $code, bin2hex(random_bytes(20)), $local,
             $fulfil, $number, $incl ? 1 : 0, $deduct ? 1 : 0, Util::json($meta)]
        );
        $invoiceId = Database::lastId();
        self::insertLines($invoiceId, $lines);

        if (!$cancelled) {
            $row = Database::row('SELECT * FROM invoices WHERE id=?', [$invoiceId]);
            if ($custId) DuesController::createFromInvoice($row);
            Database::run('INSERT INTO report_entries (book_id,type,category,amount,description,source_table,source_id,date,created_at) VALUES (?,?,?,?,?,?,?,?,?)',
                [$bid, 'in', 'invoice_sale', $total, 'Online order ' . $number, 'invoices', $invoiceId, substr($local, 0, 10), $local]);
        }
        ActivityLogger::write($bid, null, 'invoice.created', 'Invoice', $invoiceId, "Online store order {$number} received — {$invoiceNo} — {$conn['site_domain']}", null, ['invoice_no' => $invoiceNo, 'external' => $number]);
        return $invoiceId;
    }

    private static function note($v): ?string { $v = $v === null ? null : trim((string)$v); return $v === '' ? null : mb_substr($v, 0, 2000); }

    // ─── update / cancel / revive ─────────────────────────────────────────────

    public static function isLocked(array $inv): bool { return (float)$inv['paid'] > 0 || ($inv['fulfilment_status'] ?? '') === 'delivered'; }

    /** Units already put back by a recorded sales return, per product, so a cancel doesn't restock them twice. */
    private static function returnedQty(int $invoiceId): array
    {
        $out = [];
        foreach (Database::query('SELECT ri.product_id, SUM(ri.qty) q FROM return_items ri JOIN returns r ON r.id=ri.return_id
                                  WHERE r.invoice_id=? AND r.type="sales_return" AND r.deleted_at IS NULL AND ri.product_id IS NOT NULL GROUP BY ri.product_id', [$invoiceId]) as $r) $out[(int)$r['product_id']] = (float)$r['q'];
        return $out;
    }

    /** Put the invoice's stock back (net of returned units). */
    private static function restock(array $inv): void
    {
        $returned = self::returnedQty((int)$inv['id']);
        foreach (Database::query('SELECT product_id, SUM(qty) q FROM invoice_items WHERE invoice_id=? AND product_id IS NOT NULL GROUP BY product_id', [$inv['id']]) as $it) {
            $pid = (int)$it['product_id'];
            $q = (float)$it['q'] - ($returned[$pid] ?? 0.0);
            if ($q > 0) InventoryService::receive((int)$inv['book_id'], $pid, $q);
        }
    }

    /** @return bool true when something changed */
    public static function cancel(int $invoiceId, string $by = 'sync'): bool
    {
        $inv = Database::row('SELECT * FROM invoices WHERE id=? FOR UPDATE', [$invoiceId]);
        if (!$inv || $inv['status'] === 'cancelled') return false;
        if (!empty($inv['stock_deducted'])) self::restock($inv);
        foreach (Database::query('SELECT * FROM payments WHERE invoice_id=? AND status="recorded"', [$invoiceId]) as $p) self::voidRow($inv, $p, false);
        Database::run('UPDATE invoices SET status="cancelled", fulfilment_status=IF(source IS NULL, fulfilment_status, "cancelled"), paid=0, stock_deducted=0 WHERE id=?', [$invoiceId]);
        Database::run('UPDATE dues SET status="cancelled", updated_at=? WHERE invoice_id=? AND status<>"cancelled"', [now(), $invoiceId]);
        Database::run('DELETE FROM report_entries WHERE source_table="invoices" AND source_id=?', [$invoiceId]);
        \App\Services\LedgerService::syncPoints($invoiceId);        // a cancelled sale keeps no loyalty points
        ActivityLogger::write((int)$inv['book_id'], null, 'invoice.updated', 'Invoice', $invoiceId, "Invoice cancelled — {$inv['invoice_no']} ({$by})", ['status' => $inv['status']], ['status' => 'cancelled']);
        return true;
    }

    /** A cancelled order comes back: take its stock out again (refused when short), reopen the due. */
    public static function revive(array $conn, int $invoiceId): void
    {
        $inv = Database::row('SELECT * FROM invoices WHERE id=? FOR UPDATE', [$invoiceId]);
        if (!$inv || $inv['status'] !== 'cancelled') return;
        $bid = (int)$inv['book_id'];
        $returned = self::returnedQty($invoiceId);
        foreach (Database::query('SELECT product_id, SUM(qty) q FROM invoice_items WHERE invoice_id=? AND product_id IS NOT NULL GROUP BY product_id', [$invoiceId]) as $it) {
            $q = (float)$it['q'] - ($returned[(int)$it['product_id']] ?? 0.0);
            if ($q <= 0) continue;
            try { InventoryService::sell($bid, (int)$it['product_id'], $q, true); }
            catch (InsufficientStockException $e) { throw new Reject('insufficient_stock', 'Not enough stock to reopen this order: ' . $e->getMessage()); }
        }
        Database::run('UPDATE invoices SET status="sent", paid=0, stock_deducted=1 WHERE id=?', [$invoiceId]);
        Database::run('UPDATE dues SET status="unpaid", paid_amount=0, updated_at=? WHERE invoice_id=?', [now(), $invoiceId]);
        $inv = Database::row('SELECT * FROM invoices WHERE id=?', [$invoiceId]);
        if ($inv['customer_id']) DuesController::createFromInvoice($inv);
        Database::run('INSERT INTO report_entries (book_id,type,category,amount,description,source_table,source_id,date,created_at) VALUES (?,?,?,?,?,?,?,?,?)',
            [$bid, 'in', 'invoice_sale', $inv['total'], 'Online order ' . ($inv['external_number'] ?: $inv['invoice_no']), 'invoices', $invoiceId, $inv['date'], now()]);
    }

    /** Store -> book: an update to an existing order. Money/items changes are refused once the order is locked. */
    public static function updateFromOrder(array $conn, int $id, array $f, array $ctx, callable $onLocked): int
    {
        $bid = (int)$conn['book_id'];
        $inv = Database::row('SELECT * FROM invoices WHERE id=? AND book_id=? FOR UPDATE', [$id, $bid]);
        if (!$inv) throw new Reject('unknown_entity', 'The order no longer exists here.');
        $meta = Util::jsonDecode($inv['order_meta']);
        $moneyKeys = ['items', 'subtotal', 'discount', 'tax', 'tax_inclusive', 'delivery_charge', 'total', 'coupon_code', 'coupon_uuid'];
        $blocked = array_intersect_key($f, array_flip($moneyKeys));
        if ($blocked) {
            if (self::isLocked($inv) || $inv['status'] === 'cancelled') $onLocked($blocked);
            else { self::applyMoney($conn, $inv, $f); $inv = Database::row('SELECT * FROM invoices WHERE id=?', [$id]); $meta = Util::jsonDecode($inv['order_meta']); }
        }
        $changedMeta = false;
        foreach (['shipping', 'billing', 'contact', 'delivery_area'] as $k) {
            if (array_key_exists($k, $f)) {
                $meta[$k] = is_array($f[$k]) && is_array($meta[$k] ?? null) ? array_merge($meta[$k], $f[$k]) : $f[$k];
                $changedMeta = true;
            }
        }
        if ($changedMeta) Database::run('UPDATE invoices SET order_meta=? WHERE id=?', [Util::json($meta), $id]);
        if (array_key_exists('notes', $f)) Database::run('UPDATE invoices SET note_customer=? WHERE id=?', [self::note($f['notes']), $id]);
        if (isset($f['fulfilment_status'])) {
            $new = $f['fulfilment_status'];
            if (!in_array($new, self::FULFILMENT, true)) throw new Reject('invalid_payload', 'Unknown fulfilment_status.');
            self::setFulfilment($conn, $id, $new);
        }
        return $id;
    }

    /** Move an order along its fulfilment states; cancelling/reviving does the full accounting. */
    public static function setFulfilment(array $conn, int $id, string $new): void
    {
        $inv = Database::row('SELECT * FROM invoices WHERE id=? FOR UPDATE', [$id]);
        if (!$inv) return;
        if ($new === 'cancelled') { self::cancel($id, 'fulfilment'); return; }
        if ($inv['status'] === 'cancelled') self::revive($conn, $id);
        Database::run('UPDATE invoices SET fulfilment_status=? WHERE id=?', [$new, $id]);
    }

    private static function applyMoney(array $conn, array $inv, array $f): void
    {
        $bid = (int)$conn['book_id']; $id = (int)$inv['id'];
        $cur = [];
        foreach (Mappers\OrderMapper::moneyView($inv) as $k => $v) $cur[$k] = $v;
        [$subtotal, $discount, $tax, $delivery, $total, $incl] = self::checkTotals(array_merge($cur, $f));
        if (isset($f['items']) && is_array($f['items'])) {
            $lines = self::resolveLines($conn, $f['items']);
            if (!empty($inv['stock_deducted'])) self::restock($inv);
            Database::run('DELETE FROM invoice_items WHERE invoice_id=?', [$id]);
            self::insertLines($id, $lines);
            if (!empty($inv['stock_deducted'])) self::sellLines($bid, $lines);
            $meta = Util::jsonDecode($inv['order_meta']); $meta['items'] = self::itemsMeta($f['items']);
            Database::run('UPDATE invoices SET order_meta=? WHERE id=?', [Util::json($meta), $id]);
        }
        $code = array_key_exists('coupon_code', $f) ? (empty($f['coupon_code']) ? null : strtoupper(mb_substr((string)$f['coupon_code'], 0, 30))) : $inv['coupon_code'];
        Database::run('UPDATE invoices SET subtotal=?, discount=?, coupon_code=?, coupon_discount=?, tax=?, tax_inclusive=?, delivery_charge=?, total=? WHERE id=?',
            [$subtotal, $code ? 0 : $discount, $code, $code ? $discount : 0, $tax, $incl ? 1 : 0, $delivery, $total, $id]);
        Database::run('UPDATE dues SET amount=?, updated_at=? WHERE invoice_id=? AND status<>"cancelled"', [$total, now(), $id]);
        Database::run('UPDATE report_entries SET amount=? WHERE source_table="invoices" AND source_id=?', [$total, $id]);
    }

    // ─── payments ─────────────────────────────────────────────────────────────

    /** @throws \RuntimeException when the payment can't be recorded (cancelled order, overpayment) */
    public static function recordPayment(array $conn, int $invoiceId, float $amount, ?string $label, ?string $paidAtUtc, ?string $reference, ?string $note): int
    {
        $inv = Database::row('SELECT * FROM invoices WHERE id=? AND book_id=? FOR UPDATE', [$invoiceId, $conn['book_id']]);
        if (!$inv) throw new \RuntimeException('The order does not exist here.');
        if ($inv['status'] === 'cancelled') throw new \RuntimeException('The order is cancelled — payments cannot be recorded.');
        if ($amount <= 0) throw new \RuntimeException('A payment must be greater than zero.');
        $due = round((float)$inv['total'] - (float)$inv['paid'], 2);
        if ($amount - $due > 0.004) throw new \RuntimeException('The payment (' . Util::money($amount) . ') is more than the amount due (' . Util::money($due) . ').');
        $tz = Mappers\BaseMapperTz::tz($conn);
        $paidUtc = $paidAtUtc ?: gmdate('Y-m-d H:i:s');
        $localDate = substr((string)Util::toZone($paidUtc, $tz), 0, 10);
        Database::run('INSERT INTO payments (invoice_id,amount,method,date,note,created_at,status,paid_at,reference) VALUES (?,?,?,?,?,?,"recorded",?,?)',
            [$invoiceId, $amount, mb_substr($label ?: 'Online payment', 0, 60), $localDate, $note ?: null, now(), $paidUtc, $reference ? mb_substr($reference, 0, 120) : null]);
        $pid = Database::lastId();
        self::refreshPaid($inv, (float)$inv['paid'] + $amount);
        \App\Services\LedgerService::syncPoints($invoiceId);   // cumulative: 1 point per 100 paid, nothing lost to per-payment rounding
        ActivityLogger::write((int)$inv['book_id'], null, 'invoice.payment', 'Invoice', $invoiceId, "Payment recorded — {$inv['invoice_no']} — {$amount} via " . ($label ?: 'online'), ['paid' => $inv['paid']], ['paid' => (float)$inv['paid'] + $amount]);
        return $pid;
    }

    private static function refreshPaid(array $inv, float $newPaid): void
    {
        $newPaid = max(0, round($newPaid, 2));
        $status = $newPaid + 0.004 >= (float)$inv['total'] ? 'paid' : ($newPaid > 0 ? 'partial' : (in_array($inv['status'], ['draft'], true) ? 'draft' : 'sent'));
        if ($inv['status'] === 'cancelled') $status = 'cancelled';
        Database::run('UPDATE invoices SET paid=?, status=? WHERE id=?', [$newPaid, $status, $inv['id']]);
        if ($inv['customer_id'] && $inv['type'] === 'sale') DuesController::syncFromInvoicePayment((int)$inv['id'], $newPaid);
    }

    private static function voidRow(array $inv, array $p, bool $refresh): void
    {
        Database::run('UPDATE payments SET status="void" WHERE id=?', [$p['id']]);
        if ($refresh) { $fresh = Database::row('SELECT * FROM invoices WHERE id=?', [$inv['id']]); self::refreshPaid($fresh, (float)$fresh['paid'] - (float)$p['amount']); \App\Services\LedgerService::syncPoints((int)$inv['id']); }
    }

    public static function voidPayment(int $paymentId): void
    {
        $p = Database::row('SELECT * FROM payments WHERE id=? FOR UPDATE', [$paymentId]);
        if (!$p || $p['status'] === 'void') return;
        $inv = Database::row('SELECT * FROM invoices WHERE id=? FOR UPDATE', [$p['invoice_id']]);
        if ($inv) self::voidRow($inv, $p, true);
        else Database::run('UPDATE payments SET status="void" WHERE id=?', [$paymentId]);
    }

    // ─── returns ──────────────────────────────────────────────────────────────

    /**
     * @param array<int,array{product_id:int,qty:float,restock:bool}> $items
     * @throws \RuntimeException when the return is not possible (more than was bought, cancelled order)
     */
    public static function createReturn(array $conn, int $invoiceId, array $items, ?string $reason, float $refund, ?string $refundLabel, ?string $returnedUtc): int
    {
        $bid = (int)$conn['book_id'];
        $inv = Database::row('SELECT * FROM invoices WHERE id=? AND book_id=? FOR UPDATE', [$invoiceId, $bid]);
        if (!$inv) throw new \RuntimeException('The order does not exist here.');
        if ($inv['status'] === 'cancelled') throw new \RuntimeException('The order is cancelled — nothing can be returned.');
        $returned = self::returnedQty($invoiceId);
        $subtotal = 0.0; $rows = [];
        $perProduct = [];
        foreach ($items as $it) {
            if ($it['qty'] <= 0) throw new \RuntimeException('A returned quantity must be at least 1.');
            $line = Database::row('SELECT SUM(qty) q, MAX(unit_price) price, MAX(description) d FROM invoice_items WHERE invoice_id=? AND product_id=?', [$invoiceId, $it['product_id']]);
            if (!$line || $line['q'] === null) throw new \RuntimeException('A returned item is not on the order here.');
            $perProduct[$it['product_id']] = ($perProduct[$it['product_id']] ?? 0) + $it['qty'];
            if ($perProduct[$it['product_id']] + ($returned[$it['product_id']] ?? 0) - (float)$line['q'] > 0.0009) throw new \RuntimeException("More of “{$line['d']}” is being returned than was bought.");
            $price = (float)$line['price'];
            $subtotal += $it['qty'] * $price;
            $rows[] = [$it, $line['d'], $price];
        }
        $subtotal = round($subtotal, 2);
        $discount = $refund < $subtotal ? round($subtotal - $refund, 2) : 0.0;     // the part not refunded
        $extra    = $refund > $subtotal ? round($refund - $subtotal, 2) : 0.0;     // e.g. delivery given back
        $tz = Mappers\BaseMapperTz::tz($conn);
        $when = $returnedUtc ?: gmdate('Y-m-d H:i:s');
        $date = substr((string)Util::toZone($when, $tz), 0, 10);
        $no = self::nextReturnNo($bid);
        Database::run('INSERT INTO returns (book_id,invoice_id,type,return_no,date,customer_id,supplier_id,subtotal,discount,delivery_charge,total_refund,remarks,status,created_by,created_at,refund_method)
                       VALUES (?,?,?,?,?,?,NULL,?,?,?,?,?,?,NULL,?,?)',
            [$bid, $invoiceId, 'sales_return', $no, $date, $inv['customer_id'], $subtotal, $discount, $extra, $refund, $reason, 'completed', now(), $refundLabel]);
        $rid = Database::lastId();
        foreach ($rows as [$it, $desc, $price]) {
            Database::run('INSERT INTO return_items (return_id,product_id,description,qty,unit_price,line_total) VALUES (?,?,?,?,?,?)', [$rid, $it['product_id'], $desc, $it['qty'], $price, round($it['qty'] * $price, 2)]);
            if ($it['restock']) InventoryService::receive($bid, $it['product_id'], (float)$it['qty']);
        }
        Database::run('INSERT INTO report_entries (book_id,type,category,amount,description,source_table,source_id,date,created_at) VALUES (?,?,?,?,?,?,?,?,?)',
            [$bid, 'out', 'sales_return', $refund, 'Sales return refund — ' . $no, 'returns', $rid, $date, now()]);
        ActivityLogger::write($bid, null, 'return.created', 'Return', $rid, "Return recorded — {$no} — Sales Return — {$refund}", null, ['return_no' => $no, 'total_refund' => $refund]);
        return $rid;
    }
}
