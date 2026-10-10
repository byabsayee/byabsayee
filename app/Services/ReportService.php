<?php
namespace App\Services;

use App\Helpers\Database;

/**
 * ReportService — the ONE place that turns the books into numbers. Reports, the business home page, the book list and
 * the invoice/return screens all read from here, so the same period always shows the same figure.
 *
 * Rules (decided in Phase 3):
 *  1. CASH basis for money in / money out. Money moves when a payment is recorded, not when an invoice is written.
 *       IN  = payments on sale/POS invoices · payments on stand-alone dues · purchase-return cash recovered · funds in
 *       OUT = payments on purchase invoices · payments on stand-alone debts · sales-return cash refunded · expenses · funds out
 *     (A due/debt that belongs to an invoice is NOT counted again: its payment is already the invoice payment.)
 *  2. Ignored everywhere: payments with status='void', payments with method='Return credit' (it settles what is owed, it is
 *     not cash), deleted and cancelled invoices, deleted returns.
 *  3. ACCRUAL figures (sales / purchases / returns / expenses booked in the period) are reported separately as the
 *     "operating result", and what is still owed in either direction as the "position".
 *  4. Dates are the local business date stored on each record (payments.date, invoices.date, …). paid_at / created_at are
 *     never used for bucketing — they were a mix of UTC and local time.
 */
class ReportService
{
    /** SQL: the cash actually paid out on a return (older rows have only total_refund). */
    private const RETURN_CASH = "CASE WHEN r.cash_refund > 0 OR r.due_adjustment > 0 THEN r.cash_refund ELSE r.total_refund END";

    public static function symbol(int $bookId): string
    {
        try {
            $r = Database::row('SELECT symbol FROM book_currencies WHERE book_id=? AND is_default=1 LIMIT 1', [$bookId]);
            return $r['symbol'] ?? '৳';
        } catch (\Throwable $e) { return '৳'; }
    }

    // ─── cash-flow ledger ─────────────────────────────────────────────────────

    /**
     * Every cash movement of a business book between two dates, newest first.
     * @return array<int,array{date:string,ref:string,amount:float,direction:string,category:string,party:string,href:string,src:string,src_id:int}>
     */
    public static function ledger(int $bookId, string $from, string $to): array
    {
        $b = '/books/' . $bookId;
        $out = [];
        $add = function (array $rows, string $dir, string $category, string $src, callable $href) use (&$out) {
            foreach ($rows as $r) {
                $out[] = [
                    'date' => $r['d'], 'ref' => (string)$r['ref'], 'amount' => (float)$r['amount'], 'direction' => $dir,
                    'category' => is_callable($category) ? $category($r) : $category,
                    'party' => $r['party'] ?? '—', 'href' => $href($r), 'src' => $src, 'src_id' => (int)$r['id'],
                ];
            }
        };

        // Payments on invoices (sale / POS in, purchase out)
        $pay = Database::query(
            "SELECT p.id, p.date AS d, i.id AS inv_id, i.invoice_no AS ref, i.type, p.amount, p.method,
                    COALESCE(c.name, s.name, 'Walk-in') AS party
             FROM payments p
             JOIN invoices i ON i.id = p.invoice_id
             LEFT JOIN customers c ON c.id = i.customer_id
             LEFT JOIN suppliers s ON s.id = i.supplier_id
             WHERE i.book_id=? AND i.deleted_at IS NULL AND i.status<>'cancelled'
               AND p.status='recorded' AND p.method<>'Return credit'
               AND p.date BETWEEN ? AND ?",
            [$bookId, $from, $to]
        );
        foreach ($pay as $r) {
            $purchase = $r['type'] === 'purchase';
            $label = $r['type'] === 'pos' ? 'POS Sale' : ($purchase ? 'Purchase Payment' : 'Sale Payment');
            $out[] = ['date' => $r['d'], 'ref' => $r['ref'], 'amount' => (float)$r['amount'], 'direction' => $purchase ? 'out' : 'in',
                      'category' => $label, 'party' => $r['party'], 'href' => "$b/invoices/" . $r['inv_id'], 'src' => 'payments', 'src_id' => (int)$r['id']];
        }

        // Stand-alone dues / debts (not tied to an invoice) — the payment itself is the cash movement
        $add(Database::query(
            "SELECT dp.id, DATE(dp.paid_at) AS d, CONCAT('Due: ', d.title) AS ref, dp.amount, COALESCE(c.name,'Unknown Customer') AS party
             FROM due_payments dp JOIN dues d ON d.id=dp.due_id LEFT JOIN customers c ON c.id=d.customer_id
             WHERE dp.book_id=? AND d.invoice_id IS NULL AND d.status<>'cancelled'
               AND dp.payment_method<>'Return credit' AND DATE(dp.paid_at) BETWEEN ? AND ?",
            [$bookId, $from, $to]), 'in', 'Due Payment', 'due_payments', fn($r) => "$b/dues");
        $add(Database::query(
            "SELECT dp.id, DATE(dp.paid_at) AS d, CONCAT('Debt: ', d.title) AS ref, dp.amount, COALESCE(d.party,'—') AS party
             FROM debt_payments dp JOIN debts d ON d.id=dp.debt_id
             WHERE dp.book_id=? AND d.invoice_id IS NULL AND d.status<>'cancelled'
               AND dp.payment_method<>'Return credit' AND DATE(dp.paid_at) BETWEEN ? AND ?",
            [$bookId, $from, $to]), 'out', 'Debt Repayment', 'debt_payments', fn($r) => "$b/debts");

        // Returns — only the cash that really moved (what was credited against a balance is not cash)
        $add(Database::query(
            "SELECT r.id, r.date AS d, r.return_no AS ref, (" . self::RETURN_CASH . ") AS amount, COALESCE(c.name,'Unknown') AS party
             FROM returns r LEFT JOIN customers c ON c.id=r.customer_id
             WHERE r.book_id=? AND r.type='sales_return' AND r.deleted_at IS NULL AND r.date BETWEEN ? AND ?
               AND (" . self::RETURN_CASH . ") > 0",
            [$bookId, $from, $to]), 'out', 'Sales Return (Refund)', 'returns', fn($r) => "$b/returns");
        $add(Database::query(
            "SELECT r.id, r.date AS d, r.return_no AS ref, (" . self::RETURN_CASH . ") AS amount, COALESCE(s.name,'Unknown') AS party
             FROM returns r LEFT JOIN suppliers s ON s.id=r.supplier_id
             WHERE r.book_id=? AND r.type='purchase_return' AND r.deleted_at IS NULL AND r.date BETWEEN ? AND ?
               AND (" . self::RETURN_CASH . ") > 0",
            [$bookId, $from, $to]), 'in', 'Purchase Return (Recovery)', 'returns', fn($r) => "$b/returns");

        // Expenses (this includes 3rd-party delivery and salary payments, which own an expense row each)
        $rows = Database::query(
            "SELECT e.id, e.expense_date AS d, e.title AS ref, e.amount, COALESCE(NULLIF(e.paid_to,''),'—') AS party,
                    COALESCE(ec.name,'General') AS cat, e.source_table
             FROM expenses e LEFT JOIN expense_categories ec ON ec.id=e.category_id
             WHERE e.book_id=? AND e.expense_date BETWEEN ? AND ?",
            [$bookId, $from, $to]
        );
        foreach ($rows as $r) {
            $cat = $r['source_table'] === 'employee_salary_payments' ? 'Salary Payment' : 'Expense: ' . $r['cat'];
            $out[] = ['date' => $r['d'], 'ref' => $r['ref'], 'amount' => (float)$r['amount'], 'direction' => 'out', 'category' => $cat,
                      'party' => $r['party'], 'href' => "$b/expenses", 'src' => 'expenses', 'src_id' => (int)$r['id']];
        }

        // Salary payments that never got an expense row (old data) — otherwise they would vanish from the report
        $add(Database::query(
            "SELECT sp.id, sp.created_at AS d0, DATE(sp.created_at) AS d, CONCAT('Salary: ', em.name) AS ref, sp.amount, em.name AS party, sp.employee_id AS emp
             FROM employee_salary_payments sp JOIN employees em ON em.id=sp.employee_id
             WHERE sp.book_id=? AND sp.expense_id IS NULL AND DATE(sp.created_at) BETWEEN ? AND ?",
            [$bookId, $from, $to]), 'out', 'Salary Payment', 'salary_payments', fn($r) => "$b/employees/" . $r['emp']);

        // Owner funds
        foreach (['in' => 'Fund Received', 'out' => 'Fund Withdrawn'] as $dir => $label) {
            $add(Database::query(
                "SELECT f.id, f.fund_date AS d, f.title AS ref, f.amount, '—' AS party FROM funds f
                 WHERE f.book_id=? AND f.type=? AND f.fund_date BETWEEN ? AND ?", [$bookId, $dir, $from, $to]),
                $dir, $label, 'funds', fn($r) => "$b/funds");
        }

        usort($out, fn($a, $b2) => [$b2['date'], $b2['src_id']] <=> [$a['date'], $a['src_id']]);
        return $out;
    }

    /** @return array{in:float,out:float,net:float} */
    public static function cashTotals(array $ledger): array
    {
        $in = $out = 0.0;
        foreach ($ledger as $e) { if ($e['direction'] === 'in') $in += $e['amount']; else $out += $e['amount']; }
        return ['in' => round($in, 2), 'out' => round($out, 2), 'net' => round($in - $out, 2)];
    }

    /** Cash totals for a period without building the whole ledger rows into a page. */
    public static function cash(int $bookId, string $from, string $to): array
    {
        return self::cashTotals(self::ledger($bookId, $from, $to));
    }

    // ─── operating result (accrual) ───────────────────────────────────────────

    /**
     * What was BOOKED in the period, regardless of when the money moves.
     * @return array{sales:float,sales_returns:float,net_sales:float,purchases:float,purchase_returns:float,net_purchases:float,expenses:float,result:float,sale_count:int,purchase_count:int}
     */
    public static function operating(int $bookId, string $from, string $to): array
    {
        $inv = Database::row(
            "SELECT COALESCE(SUM(CASE WHEN type IN ('sale','pos') THEN total END),0) AS sales,
                    COALESCE(SUM(CASE WHEN type='purchase' THEN total END),0) AS purchases,
                    SUM(type IN ('sale','pos')) AS sc, SUM(type='purchase') AS pc
             FROM invoices WHERE book_id=? AND deleted_at IS NULL AND status<>'cancelled' AND date BETWEEN ? AND ?",
            [$bookId, $from, $to]
        );
        $ret = Database::row(
            "SELECT COALESCE(SUM(CASE WHEN type='sales_return' THEN total_refund END),0) AS sr,
                    COALESCE(SUM(CASE WHEN type='purchase_return' THEN total_refund END),0) AS pr
             FROM returns WHERE book_id=? AND deleted_at IS NULL AND date BETWEEN ? AND ?",
            [$bookId, $from, $to]
        );
        $exp = (float)(Database::row('SELECT COALESCE(SUM(amount),0) AS n FROM expenses WHERE book_id=? AND expense_date BETWEEN ? AND ?', [$bookId, $from, $to])['n'] ?? 0);
        $legacySalary = (float)(Database::row('SELECT COALESCE(SUM(amount),0) AS n FROM employee_salary_payments WHERE book_id=? AND expense_id IS NULL AND DATE(created_at) BETWEEN ? AND ?', [$bookId, $from, $to])['n'] ?? 0);
        $exp += $legacySalary;

        $sales = (float)$inv['sales']; $purch = (float)$inv['purchases'];
        $sr = (float)$ret['sr']; $pr = (float)$ret['pr'];
        return [
            'sales' => round($sales, 2), 'sales_returns' => round($sr, 2), 'net_sales' => round($sales - $sr, 2),
            'purchases' => round($purch, 2), 'purchase_returns' => round($pr, 2), 'net_purchases' => round($purch - $pr, 2),
            'expenses' => round($exp, 2),
            'result' => round(($sales - $sr) - ($purch - $pr) - $exp, 2),
            'sale_count' => (int)$inv['sc'], 'purchase_count' => (int)$inv['pc'],
        ];
    }

    // ─── position (as of today) ───────────────────────────────────────────────

    /**
     * What customers still owe / what the business still owes. Invoice balances plus stand-alone dues/debts —
     * invoice-linked dues/debts mirror their invoice, so they are never added on top.
     * @return array{receivable:float,payable:float,receivable_count:int,payable_count:int}
     */
    public static function position(int $bookId): array
    {
        $r = Database::row(
            "SELECT COALESCE(SUM(CASE WHEN type IN ('sale','pos') THEN total-paid END),0) AS rec,
                    COALESCE(SUM(CASE WHEN type='purchase' THEN total-paid END),0) AS pay,
                    SUM(type IN ('sale','pos') AND total-paid>0.004) AS rc, SUM(type='purchase' AND total-paid>0.004) AS pc
             FROM invoices WHERE book_id=? AND deleted_at IS NULL AND status<>'cancelled' AND total-paid>0.004",
            [$bookId]
        );
        $d = Database::row("SELECT COALESCE(SUM(amount-paid_amount),0) AS n, COUNT(*) AS c FROM dues  WHERE book_id=? AND invoice_id IS NULL AND status IN ('unpaid','partial') AND amount-paid_amount>0.004", [$bookId]);
        $t = Database::row("SELECT COALESCE(SUM(amount-paid_amount),0) AS n, COUNT(*) AS c FROM debts WHERE book_id=? AND invoice_id IS NULL AND status IN ('unpaid','partial') AND amount-paid_amount>0.004", [$bookId]);
        return [
            'receivable' => round((float)$r['rec'] + (float)$d['n'], 2), 'payable' => round((float)$r['pay'] + (float)$t['n'], 2),
            'receivable_count' => (int)$r['rc'] + (int)$d['c'], 'payable_count' => (int)$r['pc'] + (int)$t['c'],
        ];
    }

    // ─── breakdowns for the reports page ──────────────────────────────────────

    /** Cash by category: [category => ['in'=>x,'out'=>y,'count'=>n]] sorted by size. */
    public static function byCategory(array $ledger): array
    {
        $g = [];
        foreach ($ledger as $e) {
            $k = str_starts_with($e['category'], 'Expense:') ? 'Expenses' : $e['category'];
            $g[$k] ??= ['in' => 0.0, 'out' => 0.0, 'count' => 0];
            $g[$k][$e['direction']] += $e['amount'];
            $g[$k]['count']++;
        }
        uasort($g, fn($a, $b) => ($b['in'] + $b['out']) <=> ($a['in'] + $a['out']));
        return $g;
    }

    /** Cash in/out per day, for the chart. @return array<string,array{in:float,out:float}> */
    public static function byDay(array $ledger): array
    {
        $g = [];
        foreach ($ledger as $e) {
            $g[$e['date']] ??= ['in' => 0.0, 'out' => 0.0];
            $g[$e['date']][$e['direction']] += $e['amount'];
        }
        ksort($g);
        return $g;
    }

    /** Cash on hand since the beginning up to (and including) $to — what "available funds" means. */
    public static function balance(int $bookId, ?string $to = null): array
    {
        $t = self::cash($bookId, '2000-01-01', $to ?: date('Y-m-d'));
        return $t;
    }

    // ─── book-list figures (all time) ─────────────────────────────────────────

    /** All-time money in/out for any book (personal = its entries, business = cash flow). */
    public static function bookTotals(array $book): array
    {
        if (($book['type'] ?? '') === 'personal') {
            $r = Database::row('SELECT COALESCE(SUM(CASE WHEN type="in" THEN amount END),0) AS i, COALESCE(SUM(CASE WHEN type="out" THEN amount END),0) AS o FROM entries WHERE book_id=? AND deleted_at IS NULL', [$book['id']]);
            return ['in' => (float)$r['i'], 'out' => (float)$r['o']];
        }
        $t = self::balance((int)$book['id'], '2100-01-01');
        return ['in' => $t['in'], 'out' => $t['out']];
    }
}
