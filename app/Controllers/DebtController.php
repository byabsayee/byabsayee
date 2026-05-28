<?php
namespace App\Controllers;
use App\Helpers\Database;
use App\Services\ActivityLogger;

class DebtController
{
    public function index(array $params): void
    {
        if (guest()) redirect('/login');
        $book = $this->getBookOrFail($params['id']);
        if (!book_can($book, 'debts', 'view')) abort_403();

        $filter = $_GET['filter'] ?? 'all';
        $search = trim($_GET['q'] ?? '');
        $month     = $_GET['month'] ?? date('Y-m');
        $dateFrom  = $month . '-01';
        $dateTo    = date('Y-m-t', strtotime($dateFrom));
        $prevMonth = date('Y-m', strtotime($dateFrom . ' -1 month'));
        $nextMonth = date('Y-m', strtotime($dateFrom . ' +1 month'));
        $isCurrent = ($month === date('Y-m'));

        $where = ['d.book_id=?', '(d.due_date IS NULL OR d.due_date BETWEEN ? AND ?)'];
        $bind  = [$book['id'], $dateFrom, $dateTo];

        if ($filter !== 'all') {
            $where[] = 'd.status=?';
            $bind[]  = $filter;
        }
        if ($search !== '') {
            $where[] = '(d.title LIKE ? OR d.party LIKE ?)';
            $bind[]  = "%{$search}%";
            $bind[]  = "%{$search}%";
        }

        $whereSQL = implode(' AND ', $where);

        $debts = Database::query(
            "SELECT d.*
             FROM debts d
             WHERE {$whereSQL}
             ORDER BY
                 FIELD(d.status,'unpaid','partial','paid','cancelled'),
                 d.due_date IS NULL ASC,
                 d.due_date ASC,
                 d.created_at DESC",
            $bind
        );

        // Fetch recent payments for each debt for the timeline
        $debtIds   = array_column($debts, 'id');
        $payments  = [];
        if (!empty($debtIds)) {
            $placeholders = implode(',', array_fill(0, count($debtIds), '?'));
            $payments = Database::query(
                "SELECT * FROM debt_payments WHERE debt_id IN ($placeholders) ORDER BY paid_at DESC",
                $debtIds
            );
        }
        // Group by debt_id
        $paymentsByDebt = [];
        foreach ($payments as $p) {
            $paymentsByDebt[$p['debt_id']][] = $p;
        }

        $summary = Database::row(
            "SELECT
                COALESCE(SUM(CASE WHEN status IN ('unpaid','partial') THEN amount - paid_amount ELSE 0 END), 0) AS outstanding,
                COALESCE(SUM(paid_amount), 0) AS total_paid,
                COALESCE(SUM(amount),      0) AS total_debt,
                COUNT(*)              AS total_count,
                SUM(status='unpaid')  AS unpaid_count,
                SUM(status='partial') AS partial_count,
                SUM(status='paid')    AS paid_count
             FROM debts WHERE book_id=?",
            [$book['id']]
        );

        $defaultCurrency = Database::row(
            'SELECT symbol FROM book_currencies WHERE book_id=? AND is_default=1 LIMIT 1',
            [$book['id']]
        );
        $symbol = $defaultCurrency['symbol'] ?? '৳';

        require BASE_PATH . '/views/business/debts/index.php';
    }

    public function store(array $params): void
    {
        if (guest()) redirect('/login');
        csrf_verify();
        $book = $this->getBookOrFail($params['id']);
        if (!book_can($book, 'debts', 'create')) abort_403();

        $title   = trim($_POST['title']   ?? '');
        $party   = trim($_POST['party']   ?? '');
        $amount  = (float)($_POST['amount']  ?? 0);
        $dueDate = $_POST['due_date'] ?: null;
        $note    = trim($_POST['note']    ?? '');

        if (!$title || $amount <= 0) {
            redirect('/books/'.$book['id'].'/debts', ['error' => 'Title and amount are required.']);
        }

        Database::run(
            'INSERT INTO debts (book_id, title, party, amount, paid_amount, due_date, note, status, created_by, created_at)
             VALUES (?,?,?,?,0,?,?,"unpaid",?,?)',
            [$book['id'], $title, $party ?: null, $amount, $dueDate, $note ?: null, auth()['id'], now()]
        );
        $debtId = Database::lastId();

        ActivityLogger::write($book['id'], auth()['id'], 'debt.created', 'Debt', $debtId,
            "Debt recorded — {$title} — {$amount}" . ($party ? " (party: {$party})" : ''),
            null, ['title'=>$title,'amount'=>$amount,'party'=>$party]);

        redirect('/books/'.$book['id'].'/debts', ['success' => 'Debt recorded.']);
    }

    public function update(array $params): void
    {
        if (guest()) redirect('/login');
        csrf_verify();
        $book = $this->getBookOrFail($params['id']);
        if (!book_can($book, 'debts', 'edit')) abort_403();
        $debt = $this->getDebtOrFail($params['debt_id'], $book['id']);

        if (in_array($debt['status'], ['paid', 'cancelled'])) {
            redirect('/books/'.$book['id'].'/debts', ['error' => 'Cannot edit a paid or cancelled debt.']);
        }

        $title   = trim($_POST['title']   ?? '');
        $party   = trim($_POST['party']   ?? '');
        $amount  = (float)($_POST['amount']  ?? 0);
        $dueDate = $_POST['due_date'] ?: null;
        $note    = trim($_POST['note']    ?? '');

        if (!$title || $amount <= 0) {
            redirect('/books/'.$book['id'].'/debts', ['error' => 'Title and amount are required.']);
        }

        $paid = (float)$debt['paid_amount'];
        if ($paid >= $amount - 0.001) {
            $newStatus = 'paid';
        } elseif ($paid > 0) {
            $newStatus = 'partial';
        } else {
            $newStatus = 'unpaid';
        }

        ActivityLogger::write($book['id'], auth()['id'], 'debt.updated', 'Debt', (int)$debt['id'],
            "Debt updated — {$title} — {$amount}",
            ['title'=>$debt['title'],'amount'=>$debt['amount']],
            ['title'=>$title,'amount'=>$amount,'status'=>$newStatus]);

        Database::run(
            'UPDATE debts SET title=?, party=?, amount=?, due_date=?, note=?, status=?, updated_at=? WHERE id=? AND book_id=?',
            [$title, $party ?: null, $amount, $dueDate, $note ?: null, $newStatus, now(), $debt['id'], $book['id']]
        );

        redirect('/books/'.$book['id'].'/debts', ['success' => 'Debt updated.']);
    }

    public function recordPayment(array $params): void
    {
        if (guest()) redirect('/login');
        csrf_verify();
        $book = $this->getBookOrFail($params['id']);
        if (!book_can($book, 'debts', 'pay')) abort_403();
        $debt = $this->getDebtOrFail($params['debt_id'], $book['id']);

        if (in_array($debt['status'], ['paid', 'cancelled'])) {
            redirect('/books/'.$book['id'].'/debts', ['error' => 'This debt is already settled or cancelled.']);
        }

        $remaining = (float)$debt['amount'] - (float)$debt['paid_amount'];
        $amount    = min((float)($_POST['amount'] ?? 0), $remaining);

        if ($amount <= 0) {
            redirect('/books/'.$book['id'].'/debts', ['error' => 'Invalid payment amount.']);
        }

        $newPaid   = (float)$debt['paid_amount'] + $amount;
        $newStatus = $newPaid >= ((float)$debt['amount'] - 0.001) ? 'paid' : 'partial';

        Database::run(
            'UPDATE debts SET paid_amount=?, status=?, updated_at=? WHERE id=?',
            [$newPaid, $newStatus, now(), $debt['id']]
        );

        Database::run(
            'INSERT INTO debt_payments (debt_id, book_id, amount, payment_method, note, paid_by, paid_at)
             VALUES (?,?,?,?,?,?,?)',
            [
                $debt['id'], $book['id'], $amount,
                trim($_POST['payment_method'] ?? 'cash'),
                trim($_POST['note'] ?? '') ?: null,
                auth()['id'], now()
            ]
        );

        ActivityLogger::write($book['id'], auth()['id'], 'debt.payment', 'Debt', (int)$debt['id'],
            "Debt payment — {$debt['title']} — {$amount} (status: {$newStatus})",
            ['paid_amount'=>$debt['paid_amount'],'status'=>$debt['status']],
            ['paid_amount'=>$newPaid,'status'=>$newStatus,'payment'=>$amount]);

        redirect('/books/'.$book['id'].'/debts', ['success' => 'Payment of '.format_money($amount).' recorded.']);
    }

    public function cancel(array $params): void
    {
        if (guest()) redirect('/login');
        csrf_verify();
        $book = $this->getBookOrFail($params['id']);
        if (!book_can($book, 'debts', 'edit')) abort_403();
        $debt = $this->getDebtOrFail($params['debt_id'], $book['id']);

        Database::run(
            "UPDATE debts SET status='cancelled', updated_at=? WHERE id=?",
            [now(), $debt['id']]
        );

        ActivityLogger::write($book['id'], auth()['id'], 'debt.cancelled', 'Debt', (int)$debt['id'],
            "Debt cancelled — {$debt['title']}",
            ['status'=>$debt['status']], ['status'=>'cancelled']);

        redirect('/books/'.$book['id'].'/debts', ['success' => 'Debt cancelled.']);
    }

    public function delete(array $params): void
    {
        if (guest()) redirect('/login');
        csrf_verify();
        $book = $this->getBookOrFail($params['id']);
        if (!book_can($book, 'debts', 'delete')) abort_403();
        $debt = Database::row('SELECT * FROM debts WHERE id=? AND book_id=?', [$params['debt_id'], $book['id']]);

        ActivityLogger::write($book['id'], auth()['id'], 'debt.deleted', 'Debt', (int)($debt['id'] ?? $params['debt_id']),
            "Debt deleted — " . ($debt['title'] ?? 'unknown') . ' — ' . ($debt['amount'] ?? 0),
            $debt ? ['title'=>$debt['title'],'amount'=>$debt['amount']] : null);

        Database::run('DELETE FROM debts WHERE id=? AND book_id=?', [$params['debt_id'], $book['id']]);
        redirect('/books/'.$book['id'].'/debts', ['success' => 'Debt deleted.']);
    }

    public static function createFromInvoice(array $invoice): void
    {
        if (empty($invoice['supplier_id'])) return;
        try {
            $existing = Database::row(
                'SELECT id FROM debts WHERE invoice_id=? AND book_id=?',
                [$invoice['id'], $invoice['book_id']]
            );
            if ($existing) return;

            $supplierRow = Database::row('SELECT name FROM suppliers WHERE id=?', [$invoice['supplier_id']]);
            $party = $supplierRow['name'] ?? null;

            Database::run(
                'INSERT INTO debts (book_id, supplier_id, invoice_id, title, party, amount, paid_amount, status, created_by, created_at)
                 VALUES (?,?,?,?,?,?,0,?,?,?)',
                [
                    $invoice['book_id'],
                    $invoice['supplier_id'],
                    $invoice['id'],
                    'Invoice #' . $invoice['invoice_no'],
                    $party,
                    $invoice['total'],
                    'unpaid',
                    (function_exists('auth') ? (auth()['id'] ?? null) : null),
                    (function_exists('now') ? now() : date('Y-m-d H:i:s'))
                ]
            );
        } catch (\Throwable $e) {
            error_log('[DebtController::createFromInvoice] ' . $e->getMessage());
        }
    }

    public static function syncFromInvoicePayment(int $invoiceId, float $newPaidTotal): void
    {
        try {
            $debt = Database::row('SELECT * FROM debts WHERE invoice_id=? AND status != ?', [$invoiceId, 'cancelled']);
            if (!$debt) return;

            if ($newPaidTotal <= 0) {
                Database::run("UPDATE debts SET paid_amount=0, status='unpaid', updated_at=? WHERE id=?",
                    [date('Y-m-d H:i:s'), $debt['id']]);
            } elseif ($newPaidTotal >= ((float)$debt['amount'] - 0.001)) {
                Database::run("UPDATE debts SET paid_amount=?, status='paid', updated_at=? WHERE id=?",
                    [(float)$debt['amount'], date('Y-m-d H:i:s'), $debt['id']]);
            } else {
                Database::run("UPDATE debts SET paid_amount=?, status='partial', updated_at=? WHERE id=?",
                    [$newPaidTotal, date('Y-m-d H:i:s'), $debt['id']]);
            }
        } catch (\Throwable $e) {
            error_log('[DebtController::syncFromInvoicePayment] ' . $e->getMessage());
        }
    }

    private function getDebtOrFail(string $debtId, int $bookId): array
    {
        $debt = Database::row('SELECT * FROM debts WHERE id=? AND book_id=?', [$debtId, $bookId]);
        if (!$debt) { http_response_code(404); require BASE_PATH.'/views/errors/404.php'; exit; }
        return $debt;
    }

        private function getBookOrFail(string $id): array
    {
        $book = book_for_user($id, 'business');
        if (!$book) { http_response_code(404); require BASE_PATH.'/views/errors/404.php'; exit; }
        return $book;
    }
}
