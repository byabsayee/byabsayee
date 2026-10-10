<?php
namespace App\Controllers;
use App\Helpers\Database;
use App\Services\ActivityLogger;
use App\Services\LedgerService;

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
                COALESCE(SUM(CASE WHEN status<>'cancelled' THEN paid_amount ELSE 0 END), 0) AS total_paid,
                COALESCE(SUM(CASE WHEN status<>'cancelled' THEN amount ELSE 0 END), 0) AS total_debt,
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

        if (!empty($debt['invoice_id'])) {
            // Invoice-linked: the amount and title come from the invoice (edit the invoice to change them). Date and note stay editable here.
            $dd = valid_date($_POST['due_date'] ?? null);
            Database::transaction(function () use ($debt, $dd, $book) {
                if ($dd !== ($debt['due_date'] ?? null)) Database::run('UPDATE invoices SET due_date=? WHERE id=?', [$dd, $debt['invoice_id']]);
                Database::run('UPDATE debts SET due_date=?, note=?, updated_at=? WHERE id=? AND book_id=?', [$dd, trim($_POST['note'] ?? '') ?: null, now(), $debt['id'], $book['id']]);
            });
            redirect('/books/'.$book['id'].'/debts', ['success' => 'Saved. The amount follows the invoice — edit the invoice to change it.']);
        }

        $title   = trim($_POST['title']   ?? '');
        $party   = trim($_POST['party']   ?? '');
        $amount  = (float)($_POST['amount']  ?? 0);
        $dueDate = valid_date($_POST['due_date'] ?? null);
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
        $dueDate = valid_date($_POST['due_date'] ?? null);
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
        $back = '/books/'.$book['id'].'/debts';

        if (in_array($debt['status'], ['paid', 'cancelled'])) redirect($back, ['error' => 'This debt is already settled or cancelled.']);
        $remaining = round((float)$debt['amount'] - (float)$debt['paid_amount'], 2);
        $amount    = round(min((float)($_POST['amount'] ?? 0), $remaining), 2);
        if ($amount <= 0) redirect($back, ['error' => 'Invalid payment amount.']);
        $method = trim($_POST['payment_method'] ?? 'cash') ?: 'cash';
        $note   = trim($_POST['note'] ?? '') ?: null;

        try {
            Database::transaction(function () use ($debt, $book, $amount, $method, $note) {
                if (!empty($debt['invoice_id'])) {
                    LedgerService::addPayment((int)$debt['invoice_id'], $amount, $method, $note, auth()['id'], 'debt');
                } else {
                    $newPaid = round((float)$debt['paid_amount'] + $amount, 2);
                    $st = $newPaid >= (float)$debt['amount'] - 0.004 ? 'paid' : 'partial';
                    Database::run('UPDATE debts SET paid_amount=?, status=?, updated_at=? WHERE id=?', [$newPaid, $st, now(), $debt['id']]);
                }
                Database::run('INSERT INTO debt_payments (debt_id, book_id, amount, payment_method, note, paid_by, paid_at) VALUES (?,?,?,?,?,?,?)',
                    [$debt['id'], $book['id'], $amount, $method, $note, auth()['id'], now()]);
            });
        } catch (\RuntimeException $e) {
            redirect($back, ['error' => $e->getMessage()]);
        }

        $fresh = Database::row('SELECT paid_amount,status FROM debts WHERE id=?', [$debt['id']]);
        ActivityLogger::write($book['id'], auth()['id'], 'debt.payment', 'Debt', (int)$debt['id'],
            "Debt payment — {$debt['title']} — {$amount} (status: {$fresh['status']})",
            ['paid_amount'=>$debt['paid_amount'],'status'=>$debt['status']],
            ['paid_amount'=>$fresh['paid_amount'],'status'=>$fresh['status'],'payment'=>$amount]);

        redirect($back, ['success' => 'Payment of '.format_money($amount).' recorded'.(!empty($debt['invoice_id']) ? ' — the purchase invoice shows it too.' : '.')]);
    }

    public function cancel(array $params): void
    {
        if (guest()) redirect('/login');
        csrf_verify();
        $book = $this->getBookOrFail($params['id']);
        if (!book_can($book, 'debts', 'edit')) abort_403();
        $debt = $this->getDebtOrFail($params['debt_id'], $book['id']);
        if (!empty($debt['invoice_id'])) redirect('/books/'.$book['id'].'/debts', ['error' => 'This belongs to an invoice — cancel or delete the invoice itself and this follows.']);

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
        if ($debt && !empty($debt['invoice_id'])) redirect('/books/'.$book['id'].'/debts', ['error' => 'This belongs to an invoice — delete the invoice itself and this follows.']);

        ActivityLogger::write($book['id'], auth()['id'], 'debt.deleted', 'Debt', (int)($debt['id'] ?? $params['debt_id']),
            "Debt deleted — " . ($debt['title'] ?? 'unknown') . ' — ' . ($debt['amount'] ?? 0),
            $debt ? ['title'=>$debt['title'],'amount'=>$debt['amount']] : null);

        Database::run('DELETE FROM debts WHERE id=? AND book_id=?', [$params['debt_id'], $book['id']]);
        redirect('/books/'.$book['id'].'/debts', ['success' => 'Debt deleted.']);
    }

    /** Kept for existing callers (online orders): the ledger mirrors the invoice into its debt. */
    public static function createFromInvoice(array $invoice): void
    {
        \App\Services\LedgerService::mirrorSettlement(Database::row('SELECT * FROM invoices WHERE id=?', [$invoice['id']]) ?? $invoice);
    }

    public static function syncFromInvoicePayment(int $invoiceId, float $newPaidTotal): void
    {
        $inv = Database::row('SELECT * FROM invoices WHERE id=?', [$invoiceId]);
        if ($inv) \App\Services\LedgerService::mirrorSettlement($inv);
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
