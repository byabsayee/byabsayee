<?php
namespace App\Services;

use App\Helpers\Database;

/**
 * One place that keeps related records in step.
 *
 *   invoice  ⇄  payments  ⇄  due (customer owes) / debt (we owe supplier)  ⇄  loyalty points
 *   invoice  →  delivery expense, report entry
 *   return   →  stock, due/debt reduction, refund expense
 *
 * Rule of thumb: for anything tied to an invoice, `invoices.paid` and the `payments` rows are the truth.
 * The linked due/debt only MIRRORS them (amount = invoice total, paid_amount = invoice paid), and paying the
 * due/debt from its own screen is routed through here so the invoice moves too. Nothing here swallows errors:
 * callers run it inside Database::transaction() so a failure rolls everything back.
 */
final class LedgerService
{
    // ─── numbers ──────────────────────────────────────────────────────────────

    public static function money(float $v): float { return round($v, 2); }

    /** Invoice status from paid/total. Keeps draft/sent/overdue when nothing was paid; never un-cancels. */
    public static function statusFor(string $current, float $paid, float $total): string
    {
        if ($current === 'cancelled') return 'cancelled';
        if ($total > 0 && $paid + 0.004 >= $total) return 'paid';
        if ($paid > 0.004) return 'partial';
        return in_array($current, ['paid', 'partial'], true) ? 'sent' : $current;
    }

    /** Shared by create and edit so both always compute totals the same way. */
    public static function totals(float $subtotal, float $discount, float $points, float $coupon, float $privilege,
                                  float $delivery, float $handling, float $tax, bool $rounding): array
    {
        $base = $subtotal - $discount - $points - $coupon - $privilege + $delivery + $handling + $tax;
        $round = $rounding ? max(0, $base - floor($base)) : 0.0;
        return ['rounding' => round($round, 4), 'total' => round(max(0, $base - $round), 2)];
    }

    // ─── payments ─────────────────────────────────────────────────────────────

    /**
     * Record money against an invoice and carry it everywhere it belongs (invoice paid/status, the linked due or
     * debt and its timeline, loyalty points). $by is where it was entered: 'invoice' | 'due' | 'debt' | 'pos'.
     * @return int payments.id
     */
    public static function addPayment(int $invoiceId, float $amount, string $method, ?string $note, ?int $userId, string $by = 'invoice', ?string $date = null, ?string $reference = null): int
    {
        $amount = self::money($amount);
        if ($amount <= 0) throw new \RuntimeException('A payment must be greater than zero.');
        $inv = Database::row('SELECT * FROM invoices WHERE id=? AND deleted_at IS NULL FOR UPDATE', [$invoiceId]);
        if (!$inv) throw new \RuntimeException('Invoice not found.');
        if ($inv['status'] === 'cancelled') throw new \RuntimeException('This invoice is cancelled — payments cannot be recorded.');
        $left = self::money((float)$inv['total'] - (float)$inv['paid']);
        if ($amount - $left > 0.004) throw new \RuntimeException('That is more than the ' . number_format($left, 2) . ' still owed on this invoice.');

        $method = mb_substr(trim($method) ?: 'cash', 0, 60);
        $date   = $date ?: date('Y-m-d');
        Database::run('INSERT INTO payments (invoice_id,amount,method,date,note,created_at,status,paid_at,reference) VALUES (?,?,?,?,?,?,"recorded",UTC_TIMESTAMP(),?)',
            [$invoiceId, $amount, $method, $date, $note ?: null, now(), $reference]);
        $paymentId = (int)Database::lastId();

        self::refreshInvoice($invoiceId);

        // the due/debt's own timeline shows every payment, wherever it was entered (the screen it came from writes its own row)
        if ($by !== 'due' && $inv['type'] === 'sale' && ($due = Database::row("SELECT id FROM dues WHERE invoice_id=? AND status<>'cancelled'", [$invoiceId]))) {
            Database::run('INSERT INTO due_payments (due_id,book_id,amount,payment_method,note,paid_by,paid_at) VALUES (?,?,?,?,?,?,?)',
                [$due['id'], $inv['book_id'], $amount, $method, $note ?: null, $userId, now()]);
        }
        if ($by !== 'debt' && $inv['type'] === 'purchase' && ($debt = Database::row("SELECT id FROM debts WHERE invoice_id=? AND status<>'cancelled'", [$invoiceId]))) {
            Database::run('INSERT INTO debt_payments (debt_id,book_id,amount,payment_method,note,paid_by,paid_at) VALUES (?,?,?,?,?,?,?)',
                [$debt['id'], $inv['book_id'], $amount, $method, $note ?: null, $userId, now()]);
        }
        return $paymentId;
    }

    /**
     * Recompute paid + status of an invoice from its recorded payments, then mirror it to the due/debt and
     * re-balance loyalty points. Safe to call any time (after edits, voids, returns).
     */
    public static function refreshInvoice(int $invoiceId): array
    {
        $inv = Database::row('SELECT * FROM invoices WHERE id=? FOR UPDATE', [$invoiceId]);
        if (!$inv) throw new \RuntimeException('Invoice not found.');
        $paid = self::money((float)(Database::row('SELECT COALESCE(SUM(amount),0) s FROM payments WHERE invoice_id=? AND status="recorded"', [$invoiceId])['s'] ?? 0));
        $paid = min($paid, (float)$inv['total']);
        $status = self::statusFor($inv['status'], $paid, (float)$inv['total']);
        Database::run('UPDATE invoices SET paid=?, status=? WHERE id=?', [$paid, $status, $invoiceId]);
        $inv['paid'] = $paid; $inv['status'] = $status;
        self::mirrorSettlement($inv);
        self::syncPoints($invoiceId);
        return $inv;
    }

    /** Make the linked due/debt equal to the invoice (amount, paid, status, party). Creates it when the invoice needs one. */
    public static function mirrorSettlement(array $inv): void
    {
        $id = (int)$inv['id'];
        $total = (float)$inv['total']; $paid = (float)$inv['paid'];
        $cancelled = $inv['status'] === 'cancelled' || !empty($inv['deleted_at']);

        if ($inv['type'] === 'sale') {
            $due = Database::row('SELECT * FROM dues WHERE invoice_id=? ORDER BY id LIMIT 1 FOR UPDATE', [$id]);
            if ($cancelled || empty($inv['customer_id'])) {
                if ($due && $due['status'] !== 'cancelled') Database::run("UPDATE dues SET status='cancelled', updated_at=? WHERE id=?", [now(), $due['id']]);
                return;
            }
            $st = $paid + 0.004 >= $total ? 'paid' : ($paid > 0.004 ? 'partial' : 'unpaid');
            if ($due) {
                Database::run('UPDATE dues SET customer_id=?, title=?, amount=?, paid_amount=?, status=?, due_date=?, updated_at=? WHERE id=?',
                    [$inv['customer_id'], 'Invoice #' . $inv['invoice_no'], $total, min($paid, $total), $st, $inv['due_date'], now(), $due['id']]);
            } else {
                Database::run('INSERT INTO dues (book_id,customer_id,invoice_id,title,amount,paid_amount,due_date,status,created_by,created_at) VALUES (?,?,?,?,?,?,?,?,?,?)',
                    [$inv['book_id'], $inv['customer_id'], $id, 'Invoice #' . $inv['invoice_no'], $total, min($paid, $total), $inv['due_date'], $st, $inv['created_by'], now()]);
            }
        } elseif ($inv['type'] === 'purchase') {
            $debt = Database::row('SELECT * FROM debts WHERE invoice_id=? ORDER BY id LIMIT 1 FOR UPDATE', [$id]);
            if ($cancelled || empty($inv['supplier_id'])) {
                if ($debt && $debt['status'] !== 'cancelled') Database::run("UPDATE debts SET status='cancelled', updated_at=? WHERE id=?", [now(), $debt['id']]);
                return;
            }
            $party = Database::row('SELECT name FROM suppliers WHERE id=?', [$inv['supplier_id']])['name'] ?? null;
            $st = $paid + 0.004 >= $total ? 'paid' : ($paid > 0.004 ? 'partial' : 'unpaid');
            if ($debt) {
                Database::run('UPDATE debts SET supplier_id=?, title=?, party=?, amount=?, paid_amount=?, status=?, due_date=?, updated_at=? WHERE id=?',
                    [$inv['supplier_id'], 'Invoice #' . $inv['invoice_no'], $party, $total, min($paid, $total), $st, $inv['due_date'], now(), $debt['id']]);
            } else {
                Database::run('INSERT INTO debts (book_id,supplier_id,invoice_id,title,party,amount,paid_amount,due_date,status,created_by,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?)',
                    [$inv['book_id'], $inv['supplier_id'], $id, 'Invoice #' . $inv['invoice_no'], $party, $total, min($paid, $total), $inv['due_date'], $st, $inv['created_by'], now()]);
            }
        }
    }

    // ─── loyalty points ───────────────────────────────────────────────────────

    /**
     * A sale earns 1 point per 100 paid, counted on the CUMULATIVE amount (not per payment — that lost the remainder
     * every time). invoices.points_awarded remembers what was granted, so edits, voids and deletes take back
     * exactly that and a customer change moves the points to the new customer.
     */
    public static function syncPoints(int $invoiceId): void
    {
        $inv = Database::row('SELECT id,type,customer_id,status,deleted_at,points_awarded FROM invoices WHERE id=?', [$invoiceId]);
        if (!$inv || !in_array($inv['type'], ['sale', 'pos'], true)) return;
        $holder = $inv['customer_id'];
        $cash = (float)(Database::row("SELECT COALESCE(SUM(amount),0) s FROM payments WHERE invoice_id=? AND status='recorded' AND (reference IS NULL OR reference NOT LIKE 'return:%')", [$invoiceId])['s'] ?? 0);
        $target = ($holder && $inv['status'] !== 'cancelled' && empty($inv['deleted_at'])) ? (int)floor($cash / 100) : 0;
        $had = (int)$inv['points_awarded'];
        if ($target === $had) return;
        if ($holder) Database::run('UPDATE customers SET points=GREATEST(0,points+?) WHERE id=?', [$target - $had, $holder]);
        Database::run('UPDATE invoices SET points_awarded=? WHERE id=?', [$target, $invoiceId]);
    }

    /** When the customer on an invoice changes: take the earned points off the old one before the row is updated. */
    public static function moveEarnedPoints(array $oldInv, ?int $newCustomerId): void
    {
        if (!in_array($oldInv['type'], ['sale', 'pos'], true) || (int)$oldInv['customer_id'] === (int)$newCustomerId) return;
        $had = (int)$oldInv['points_awarded'];
        if ($had > 0 && $oldInv['customer_id']) Database::run('UPDATE customers SET points=GREATEST(0,points-?) WHERE id=?', [$had, $oldInv['customer_id']]);
        Database::run('UPDATE invoices SET points_awarded=0 WHERE id=?', [$oldInv['id']]);
    }

    /** Points the customer spent as a discount on this invoice (1 point = 1 currency unit). */
    public static function spentPoints(array $inv): int { return (int)round((float)($inv['points_discount'] ?? 0)); }

    // ─── expenses owned by another record ─────────────────────────────────────

    /** Create, update or remove the expense that belongs to (table,id). Pass $amount<=0 to remove it. @return ?int expense id */
    public static function syncOwnedExpense(int $bookId, string $table, int $id, float $amount, string $title, string $date, ?string $note, ?int $userId, ?int $categoryId = null, ?string $paidTo = null): ?int
    {
        $ex = Database::row('SELECT id FROM expenses WHERE book_id=? AND source_table=? AND source_id=? ORDER BY id LIMIT 1 FOR UPDATE', [$bookId, $table, $id]);
        if ($amount <= 0) {
            if ($ex) Database::run('DELETE FROM expenses WHERE id=?', [$ex['id']]);
            return null;
        }
        if ($ex) {
            Database::run('UPDATE expenses SET title=?, amount=?, expense_date=?, note=?, updated_at=? WHERE id=?', [$title, $amount, $date, $note, now(), $ex['id']]);
            return (int)$ex['id'];
        }
        Database::run('INSERT INTO expenses (book_id,category_id,title,amount,expense_date,paid_to,note,created_by,created_at,source_table,source_id) VALUES (?,?,?,?,?,?,?,?,?,?,?)',
            [$bookId, $categoryId, $title, $amount, $date, $paidTo, $note, $userId, now(), $table, $id]);
        return (int)Database::lastId();
    }

    public static function dropOwnedExpenses(int $bookId, string $table, int $id): void
    {
        Database::run('DELETE FROM expenses WHERE book_id=? AND source_table=? AND source_id=?', [$bookId, $table, $id]);
    }

    /** The "Delivery (3rd party)" expense of an invoice exists only while the invoice says a third party delivered it. */
    public static function syncInvoiceDeliveryExpense(array $inv, ?int $userId): void
    {
        $on = $inv['type'] === 'sale' && ($inv['delivery_type'] ?? '') === 'other' && (float)$inv['delivery_charge'] > 0 && $inv['status'] !== 'cancelled' && empty($inv['deleted_at']);
        self::syncOwnedExpense((int)$inv['book_id'], 'invoices', (int)$inv['id'], $on ? (float)$inv['delivery_charge'] : 0,
            'Delivery (3rd party) — Invoice ' . $inv['invoice_no'], $inv['date'], 'Auto-created from invoice #' . $inv['invoice_no'], $userId);
    }

    /** The cash-flow row the old report screens read. One row per invoice, always matching the invoice. */
    public static function syncReportEntry(array $inv): void
    {
        $bid = (int)$inv['book_id']; $id = (int)$inv['id'];
        Database::run('DELETE FROM report_entries WHERE source_table="invoices" AND source_id=?', [$id]);
        if ($inv['status'] === 'cancelled' || !empty($inv['deleted_at']) || !in_array($inv['type'], ['sale', 'purchase'], true)) return;
        $isSale = $inv['type'] === 'sale';
        Database::run('INSERT INTO report_entries (book_id,type,category,amount,description,source_table,source_id,date,created_at) VALUES (?,?,?,?,?,?,?,?,?)',
            [$bid, $isSale ? 'in' : 'out', $isSale ? 'invoice_sale' : 'invoice_purchase', $inv['total'], ($isSale ? 'Sale' : 'Purchase') . ' invoice ' . $inv['invoice_no'], 'invoices', $id, $inv['date'], now()]);
    }

    // ─── stock ────────────────────────────────────────────────────────────────

    /** @return array<int,float> product_id => qty for a list of ['pid'=>?,'qty'=>x] */
    public static function qtyByProduct(array $items): array
    {
        $out = [];
        foreach ($items as $it) {
            $pid = (int)($it['pid'] ?? $it['product_id'] ?? 0);
            if ($pid) $out[$pid] = ($out[$pid] ?? 0.0) + (float)$it['qty'];
        }
        return $out;
    }

    /**
     * Move stock from one set of lines to another (create = old empty, delete = new empty, edit = both) touching only the
     * difference. Sales are strict (a short product raises InsufficientStockException and nothing is saved).
     * @return array<int,float> product_id => signed change to stock on hand (for the online-store hook)
     */
    public static function applyStockDiff(int $bookId, string $type, array $old, array $new, array $buyPrices = []): array
    {
        $moves = [];
        foreach (array_unique(array_merge(array_keys($old), array_keys($new))) as $pid) {
            $delta = ($new[$pid] ?? 0.0) - ($old[$pid] ?? 0.0);          // + = more goods on the invoice
            if (abs($delta) < 0.0005) continue;
            if ($type === 'sale') {
                if ($delta > 0) InventoryService::sell($bookId, $pid, $delta, true);
                else            InventoryService::receive($bookId, $pid, -$delta);
                $moves[$pid] = -$delta;
            } else {                                                       // purchase: more on the invoice = more received
                if ($delta > 0) InventoryService::receive($bookId, $pid, $delta, $buyPrices[$pid] ?? null);
                else {
                    if (InventoryService::onHand($bookId, $pid) + 1e-9 < -$delta) throw new InsufficientStockException($pid, InventoryService::onHand($bookId, $pid), -$delta);
                    InventoryService::remove($bookId, $pid, -$delta);
                }
                $moves[$pid] = $delta;
            }
        }
        return $moves;
    }

    // ─── returns ──────────────────────────────────────────────────────────────

    /** Quantity already returned per product for an invoice (excluding one return when editing/deleting it). */
    public static function returnedQty(int $invoiceId, string $returnType, ?int $exceptReturnId = null): array
    {
        $out = [];
        $sql = 'SELECT ri.product_id, SUM(ri.qty) q FROM return_items ri JOIN returns r ON r.id=ri.return_id
                WHERE r.invoice_id=? AND r.type=? AND r.deleted_at IS NULL AND ri.product_id IS NOT NULL' . ($exceptReturnId ? ' AND r.id<>?' : '') . ' GROUP BY ri.product_id';
        foreach (Database::query($sql, $exceptReturnId ? [$invoiceId, $returnType, $exceptReturnId] : [$invoiceId, $returnType]) as $r) $out[(int)$r['product_id']] = (float)$r['q'];
        return $out;
    }

    /**
     * A return against an invoice that still has money outstanding first cancels what the party still owes — you don't
     * pay cash back for goods that were never paid for. The cancelled part is booked as a "Return credit" payment on the
     * invoice (so invoice, due/debt and timeline all agree); only the rest is paid out in cash.
     * @return array{due_adjustment:float,cash_refund:float}
     */
    public static function creditReturn(?int $invoiceId, int $returnId, string $returnNo, float $refund, ?int $userId): array
    {
        $refund = self::money($refund);
        if (!$invoiceId || $refund <= 0) return ['due_adjustment' => 0.0, 'cash_refund' => $refund];
        $inv = Database::row('SELECT * FROM invoices WHERE id=? AND deleted_at IS NULL FOR UPDATE', [$invoiceId]);
        if (!$inv || $inv['status'] === 'cancelled' || !in_array($inv['type'], ['sale', 'purchase'], true)) return ['due_adjustment' => 0.0, 'cash_refund' => $refund];
        $outstanding = self::money((float)$inv['total'] - (float)$inv['paid']);
        $credit = $outstanding > 0.004 ? self::money(min($refund, $outstanding)) : 0.0;
        if ($credit > 0) self::addPayment($invoiceId, $credit, 'Return credit', 'Credited by return ' . $returnNo, $userId, 'invoice', null, 'return:' . $returnId);
        return ['due_adjustment' => $credit, 'cash_refund' => self::money($refund - $credit)];
    }

    /** Deleting a return gives the credit back: the credit payment is voided and the invoice/due/debt re-open. */
    public static function reverseReturnCredit(int $returnId): void
    {
        foreach (Database::query("SELECT p.*, i.type FROM payments p JOIN invoices i ON i.id=p.invoice_id WHERE p.reference=? AND p.status='recorded'", ['return:' . $returnId]) as $p) {
            Database::run("UPDATE payments SET status='void' WHERE id=?", [$p['id']]);
            $tbl = $p['type'] === 'sale' ? ['due_payments', 'dues', 'due_id'] : ['debt_payments', 'debts', 'debt_id'];
            $x = Database::row("SELECT id FROM `{$tbl[1]}` WHERE invoice_id=? ORDER BY id LIMIT 1", [$p['invoice_id']]);
            if ($x) Database::run("DELETE FROM `{$tbl[0]}` WHERE `{$tbl[2]}`=? AND payment_method='Return credit' AND ABS(amount-?)<0.005 ORDER BY id DESC LIMIT 1", [$x['id'], $p['amount']]);
            self::refreshInvoice((int)$p['invoice_id']);
        }
    }

    // ─── open balances (used to warn before deleting a party) ─────────────────

    /** What a customer still owes (dues) or what we still owe a supplier (debts). @return array{amount:float,count:int} */
    public static function openBalance(string $party, int $partyId): array
    {
        [$tbl, $col] = $party === 'customer' ? ['dues', 'customer_id'] : ['debts', 'supplier_id'];
        $r = Database::row("SELECT COALESCE(SUM(amount-paid_amount),0) a, COUNT(*) c FROM `$tbl` WHERE `$col`=? AND status IN ('unpaid','partial') AND amount-paid_amount>0.004", [$partyId]);
        return ['amount' => self::money((float)($r['a'] ?? 0)), 'count' => (int)($r['c'] ?? 0)];
    }
}
