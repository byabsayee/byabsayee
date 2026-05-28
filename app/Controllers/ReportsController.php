<?php
namespace App\Controllers;
use App\Helpers\Database;

class ReportsController
{
    public function index(array $params): void
    {
        if (guest()) redirect('/login');
        $book = $this->getBookOrFail($params['id']);
        if (!book_can($book, 'reports', 'view')) abort_403();

        $month      = $_GET['month'] ?? date('Y-m');
        $typeFilter = $_GET['type']  ?? 'all';
        $dateFrom   = $month . '-01';
        $dateTo     = date('Y-m-t', strtotime($dateFrom));

        $entries = [];
        $sym     = $this->getSym($book['id']);

        // ── Sale Invoices (IN) ──────────────────────────────────────────────
        try {
            $rows = Database::query(
                "SELECT i.id, i.invoice_no, i.date, i.total AS amount,
                        'in' AS direction, 'Sale Invoice' AS category,
                        'invoices' AS src, i.id AS src_id,
                        COALESCE(c.name,'Walk-in') AS party
                 FROM invoices i
                 LEFT JOIN customers c ON c.id=i.customer_id
                 WHERE i.book_id=? AND i.type='sale' AND i.deleted_at IS NULL
                   AND i.date BETWEEN ? AND ?
                 ORDER BY i.date DESC",
                [$book['id'], $dateFrom, $dateTo]
            );
            foreach ($rows as $r)
                $entries[] = array_merge($r, ['href' => '/books/'.$book['id'].'/invoices/'.$r['id']]);
        } catch (\Throwable $e) {}

        // ── Purchase Invoices (OUT) ─────────────────────────────────────────
        try {
            $rows = Database::query(
                "SELECT i.id, i.invoice_no, i.date, i.total AS amount,
                        'out' AS direction, 'Purchase Invoice' AS category,
                        'invoices' AS src, i.id AS src_id,
                        COALESCE(s.name,'Unknown Supplier') AS party
                 FROM invoices i
                 LEFT JOIN suppliers s ON s.id=i.supplier_id
                 WHERE i.book_id=? AND i.type='purchase' AND i.deleted_at IS NULL
                   AND i.date BETWEEN ? AND ?
                 ORDER BY i.date DESC",
                [$book['id'], $dateFrom, $dateTo]
            );
            foreach ($rows as $r)
                $entries[] = array_merge($r, ['href' => '/books/'.$book['id'].'/invoices/'.$r['id']]);
        } catch (\Throwable $e) {}

        // ── Sales Returns — refund given (OUT) ─────────────────────────────
        try {
            $rows = Database::query(
                "SELECT r.id, r.return_no AS invoice_no, r.date,
                        r.total_refund AS amount, 'out' AS direction,
                        'Sales Return (Refund)' AS category,
                        'returns' AS src, r.id AS src_id,
                        COALESCE(c.name,'Unknown') AS party
                 FROM returns r
                 LEFT JOIN customers c ON c.id=r.customer_id
                 WHERE r.book_id=? AND r.type='sales_return' AND r.deleted_at IS NULL
                   AND r.date BETWEEN ? AND ?",
                [$book['id'], $dateFrom, $dateTo]
            );
            foreach ($rows as $r)
                $entries[] = array_merge($r, ['href' => '/books/'.$book['id'].'/returns']);
        } catch (\Throwable $e) {}

        // ── Purchase Returns — money back (IN) ─────────────────────────────
        try {
            $rows = Database::query(
                "SELECT r.id, r.return_no AS invoice_no, r.date,
                        r.total_refund AS amount, 'in' AS direction,
                        'Purchase Return (Recovery)' AS category,
                        'returns' AS src, r.id AS src_id,
                        COALESCE(s.name,'Unknown') AS party
                 FROM returns r
                 LEFT JOIN suppliers s ON s.id=r.supplier_id
                 WHERE r.book_id=? AND r.type='purchase_return' AND r.deleted_at IS NULL
                   AND r.date BETWEEN ? AND ?",
                [$book['id'], $dateFrom, $dateTo]
            );
            foreach ($rows as $r)
                $entries[] = array_merge($r, ['href' => '/books/'.$book['id'].'/returns']);
        } catch (\Throwable $e) {}

        // ── Expenses (OUT) ──────────────────────────────────────────────────
        try {
            $rows = Database::query(
                "SELECT e.id, e.title AS invoice_no, e.expense_date AS date,
                        e.amount, 'out' AS direction,
                        CONCAT('Expense: ', COALESCE(ec.name,'General')) AS category,
                        'expenses' AS src, e.id AS src_id,
                        COALESCE(e.paid_to,'—') AS party
                 FROM expenses e
                 LEFT JOIN expense_categories ec ON ec.id=e.category_id
                 WHERE e.book_id=? AND e.expense_date BETWEEN ? AND ?
                 ORDER BY e.expense_date DESC",
                [$book['id'], $dateFrom, $dateTo]
            );
            foreach ($rows as $r)
                $entries[] = array_merge($r, ['href' => '/books/'.$book['id'].'/expenses']);
        } catch (\Throwable $e) {}

        // ── Funds IN ───────────────────────────────────────────────────────
        try {
            $rows = Database::query(
                "SELECT f.id, COALESCE(f.title,'Fund') AS invoice_no, f.fund_date AS date,
                        f.amount, 'in' AS direction, 'Fund Received' AS category,
                        'funds' AS src, f.id AS src_id, '—' AS party
                 FROM funds f
                 WHERE f.book_id=? AND f.type='in' AND f.fund_date BETWEEN ? AND ?",
                [$book['id'], $dateFrom, $dateTo]
            );
            foreach ($rows as $r)
                $entries[] = array_merge($r, ['href' => '/books/'.$book['id'].'/funds']);
        } catch (\Throwable $e) {}

        // ── Funds OUT ──────────────────────────────────────────────────────
        try {
            $rows = Database::query(
                "SELECT f.id, COALESCE(f.title,'Withdrawal') AS invoice_no, f.fund_date AS date,
                        f.amount, 'out' AS direction, 'Fund Withdrawn' AS category,
                        'funds' AS src, f.id AS src_id, '—' AS party
                 FROM funds f
                 WHERE f.book_id=? AND f.type='out' AND f.fund_date BETWEEN ? AND ?",
                [$book['id'], $dateFrom, $dateTo]
            );
            foreach ($rows as $r)
                $entries[] = array_merge($r, ['href' => '/books/'.$book['id'].'/funds']);
        } catch (\Throwable $e) {}

        // ── Due Payments — customer pays back (IN) ──────────────────────────
        try {
            $rows = Database::query(
                "SELECT dp.id,
                        CONCAT('Due: ', d.title) AS invoice_no,
                        DATE(dp.paid_at) AS date,
                        dp.amount, 'in' AS direction,
                        CONCAT('Due Payment') AS category,
                        'due_payments' AS src, dp.id AS src_id,
                        COALESCE(c.name,'Unknown Customer') AS party
                 FROM due_payments dp
                 JOIN dues d ON d.id = dp.due_id
                 LEFT JOIN customers c ON c.id = d.customer_id
                 WHERE dp.book_id=? AND DATE(dp.paid_at) BETWEEN ? AND ?
                 ORDER BY dp.paid_at DESC",
                [$book['id'], $dateFrom, $dateTo]
            );
            foreach ($rows as $r)
                $entries[] = array_merge($r, ['href' => '/books/'.$book['id'].'/dues']);
        } catch (\Throwable $e) {}

        // ── Debt Payments — you repay creditor (OUT) ───────────────────────
        try {
            $rows = Database::query(
                "SELECT dp.id,
                        CONCAT('Debt: ', d.title) AS invoice_no,
                        DATE(dp.paid_at) AS date,
                        dp.amount, 'out' AS direction,
                        CONCAT('Debt Repayment') AS category,
                        'debt_payments' AS src, dp.id AS src_id,
                        COALESCE(d.party,'—') AS party
                 FROM debt_payments dp
                 JOIN debts d ON d.id = dp.debt_id
                 WHERE dp.book_id=? AND DATE(dp.paid_at) BETWEEN ? AND ?
                 ORDER BY dp.paid_at DESC",
                [$book['id'], $dateFrom, $dateTo]
            );
            foreach ($rows as $r)
                $entries[] = array_merge($r, ['href' => '/books/'.$book['id'].'/debts']);
        } catch (\Throwable $e) {}

        // ── Salary Payments (OUT, via employee_salary_payments) ─────────────
        try {
            $rows = Database::query(
                "SELECT sp.id,
                        CONCAT('Salary: ', em.name) AS invoice_no,
                        DATE(sp.created_at) AS date,
                        sp.amount, 'out' AS direction,
                        'Salary Payment' AS category,
                        'salary_payments' AS src, sp.id AS src_id,
                        em.name AS party
                 FROM employee_salary_payments sp
                 JOIN employees em ON em.id = sp.employee_id
                 WHERE sp.book_id=? AND DATE(sp.created_at) BETWEEN ? AND ?
                 ORDER BY sp.created_at DESC",
                [$book['id'], $dateFrom, $dateTo]
            );
            foreach ($rows as $r)
                $entries[] = array_merge($r, ['href' => '/books/'.$book['id'].'/employees/'.$r['src_id']]);
        } catch (\Throwable $e) {}

        // ── Sort all by date desc ──────────────────────────────────────────
        usort($entries, fn($a, $b) => strcmp($b['date'], $a['date']));

        // ── Totals (unfiltered, for summary cards) ─────────────────────────
        $totalIn  = array_sum(array_map(fn($e) => $e['direction']==='in'  ? (float)$e['amount'] : 0, $entries));
        $totalOut = array_sum(array_map(fn($e) => $e['direction']==='out' ? (float)$e['amount'] : 0, $entries));

        // ── Apply type filter for display ──────────────────────────────────
        if ($typeFilter !== 'all') {
            $entries = array_values(array_filter($entries, fn($e) => $e['direction'] === $typeFilter));
        }

        

        require BASE_PATH . '/views/business/reports/index.php';
    }

    private function getSym(int $bookId): string
    {
        try {
            $r = Database::row('SELECT symbol FROM book_currencies WHERE book_id=? AND is_default=1', [$bookId]);
            return $r['symbol'] ?? '৳';
        } catch (\Throwable $e) { return '৳'; }
    }

    private function getBookOrFail(string $id): array
    {
        try {
            $book = Database::row(
                'SELECT * FROM books WHERE id=? AND deleted_at IS NULL AND (user_id=? OR EXISTS(
                    SELECT 1 FROM book_members WHERE book_id=books.id AND user_id=? AND status="active"
                ))',
                [$id, auth()['id'], auth()['id']]
            );
        } catch (\Throwable $e) {
            $book = Database::row(
                'SELECT * FROM books WHERE id=? AND user_id=? AND deleted_at IS NULL',
                [$id, auth()['id']]
            );
        }
        if (!$book) { http_response_code(404); require BASE_PATH.'/views/errors/404.php'; exit; }
        return $book;
    }
}
