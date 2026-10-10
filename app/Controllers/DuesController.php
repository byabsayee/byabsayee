<?php
namespace App\Controllers;
use App\Helpers\Database;
use App\Services\ActivityLogger;
use App\Services\LedgerService;

class DuesController
{
    public function index(array $params): void
    {
        if (guest()) redirect('/login');
        $book = $this->getBookOrFail($params['id']);
        if (!book_can($book, 'dues', 'view')) abort_403();

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
            $where[] = '(c.name LIKE ? OR c.phone LIKE ? OR d.title LIKE ?)';
            $bind[]  = "%{$search}%";
            $bind[]  = "%{$search}%";
            $bind[]  = "%{$search}%";
        }

        $whereSQL = implode(' AND ', $where);

        $dues = Database::query(
            "SELECT d.*,
                    c.name  AS customer_name,
                    c.phone AS customer_phone,
                    c.photo AS customer_photo,
                    i.invoice_no,
                    bc.symbol AS currency_symbol
             FROM dues d
             LEFT JOIN customers      c  ON c.id  = d.customer_id
             LEFT JOIN invoices       i  ON i.id  = d.invoice_id
             LEFT JOIN book_currencies bc ON bc.book_id = d.book_id AND bc.is_default = 1
             WHERE {$whereSQL}
             ORDER BY d.status ASC, d.created_at DESC",
            $bind
        );

        $summary = Database::row(
            "SELECT
                COALESCE(SUM(CASE WHEN status IN ('unpaid','partial') THEN amount - paid_amount ELSE 0 END), 0) AS outstanding,
                COALESCE(SUM(CASE WHEN status<>'cancelled' THEN paid_amount ELSE 0 END), 0) AS total_collected,
                COUNT(*) AS total_count,
                SUM(status='unpaid')  AS unpaid_count,
                SUM(status='partial') AS partial_count,
                SUM(status='paid')    AS paid_count
             FROM dues WHERE book_id=?",
            [$book['id']]
        );

        $defaultCurrency = Database::row(
            'SELECT symbol FROM book_currencies WHERE book_id=? AND is_default=1 LIMIT 1',
            [$book['id']]
        );
        $symbol = $defaultCurrency['symbol'] ?? '৳';

        $customers = Database::query(
            'SELECT id, name, phone FROM customers WHERE book_id=? AND deleted_at IS NULL ORDER BY name',
            [$book['id']]
        );

        require BASE_PATH . '/views/business/dues/index.php';
    }

    public function store(array $params): void
    {
        if (guest()) redirect('/login');
        csrf_verify();
        $book = $this->getBookOrFail($params['id']);
        if (!book_can($book, 'dues', 'create')) abort_403();

        $customerId = (int)($_POST['customer_id'] ?? 0);
        $amount     = (float)($_POST['amount'] ?? 0);
        $title      = trim($_POST['title'] ?? '');
        $dueDate    = valid_date($_POST['due_date'] ?? null);
        $note       = trim($_POST['note'] ?? '');

        if (!$customerId || $amount <= 0 || !$title) {
            redirect('/books/'.$book['id'].'/dues', ['error' => 'Please select a customer from the dropdown, enter a title, and set an amount.']);
        }

        $customer = Database::row(
            'SELECT id FROM customers WHERE id=? AND book_id=? AND deleted_at IS NULL',
            [$customerId, $book['id']]
        );
        if (!$customer) {
            redirect('/books/'.$book['id'].'/dues', ['error' => 'Customer not found.']);
        }

        Database::run(
            'INSERT INTO dues (book_id, customer_id, title, amount, paid_amount, due_date, note, status, created_by, created_at)
             VALUES (?,?,?,?,0,?,?,?,?,?)',
            [$book['id'], $customerId, $title, $amount, $dueDate, $note ?: null, 'unpaid', auth()['id'], now()]
        );
        $dueId = Database::lastId();

        ActivityLogger::write($book['id'], auth()['id'], 'due.created', 'Due', $dueId,
            "Due created — {$title} — {$amount}",
            null, ['title'=>$title,'amount'=>$amount,'customer_id'=>$customerId]);

        redirect('/books/'.$book['id'].'/dues', ['success' => 'Due added.']);
    }

    public function update(array $params): void
    {
        if (guest()) redirect('/login');
        csrf_verify();
        $book = $this->getBookOrFail($params['id']);
        if (!book_can($book, 'dues', 'edit')) abort_403();
        $due  = $this->getDueOrFail($params['due_id'], $book['id']);

        // Only allow editing if not fully paid or cancelled
        if (in_array($due['status'], ['paid', 'cancelled'])) {
            redirect('/books/'.$book['id'].'/dues', ['error' => 'Cannot edit a paid or cancelled due.']);
        }

        if (!empty($due['invoice_id'])) {
            // Invoice-linked: the amount and title come from the invoice (edit the invoice to change them). Date and note stay editable here.
            $dd = valid_date($_POST['due_date'] ?? null);
            Database::transaction(function () use ($due, $dd, $book) {
                if ($dd !== ($due['due_date'] ?? null)) Database::run('UPDATE invoices SET due_date=? WHERE id=?', [$dd, $due['invoice_id']]);
                Database::run('UPDATE dues SET due_date=?, note=?, updated_at=? WHERE id=? AND book_id=?', [$dd, trim($_POST['note'] ?? '') ?: null, now(), $due['id'], $book['id']]);
            });
            redirect('/books/'.$book['id'].'/dues', ['success' => 'Saved. The amount follows the invoice — edit the invoice to change it.']);
        }

        $title   = trim($_POST['title'] ?? '');
        $amount  = (float)($_POST['amount'] ?? 0);
        $dueDate = valid_date($_POST['due_date'] ?? null);
        $note    = trim($_POST['note'] ?? '');

        if (!$title || $amount <= 0) {
            redirect('/books/'.$book['id'].'/dues', ['error' => 'Title and amount are required.']);
        }

        // Recalculate status based on paid amount vs new total
        $paid      = (float)$due['paid_amount'];
        $newStatus = $due['status'];
        if ($paid >= $amount - 0.001) {
            $newStatus = 'paid';
        } elseif ($paid > 0) {
            $newStatus = 'partial';
        } else {
            $newStatus = 'unpaid';
        }

        ActivityLogger::write($book['id'], auth()['id'], 'due.updated', 'Due', (int)$due['id'],
            "Due updated — {$title} — {$amount}",
            ['title'=>$due['title'],'amount'=>$due['amount']],
            ['title'=>$title,'amount'=>$amount,'status'=>$newStatus]);

        Database::run(
            'UPDATE dues SET title=?, amount=?, due_date=?, note=?, status=?, updated_at=? WHERE id=? AND book_id=?',
            [$title, $amount, $dueDate, $note ?: null, $newStatus, now(), $due['id'], $book['id']]
        );

        redirect('/books/'.$book['id'].'/dues', ['success' => 'Due updated.']);
    }

    public function recordPayment(array $params): void
    {
        if (guest()) redirect('/login');
        csrf_verify();
        $book = $this->getBookOrFail($params['id']);
        if (!book_can($book, 'dues', 'pay')) abort_403();
        $due  = $this->getDueOrFail($params['due_id'], $book['id']);
        $back = '/books/'.$book['id'].'/dues';

        if (in_array($due['status'], ['paid', 'cancelled'])) redirect($back, ['error' => 'This due is already settled or cancelled.']);
        $remaining = round((float)$due['amount'] - (float)$due['paid_amount'], 2);
        $amount    = round(min((float)($_POST['amount'] ?? 0), $remaining), 2);
        if ($amount <= 0) redirect($back, ['error' => 'Invalid payment amount.']);
        $method = trim($_POST['payment_method'] ?? 'cash') ?: 'cash';
        $note   = trim($_POST['note'] ?? '') ?: null;

        try {
            Database::transaction(function () use ($due, $book, $amount, $method, $note) {
                if (!empty($due['invoice_id'])) {
                    // A due that belongs to an invoice is paid THROUGH the invoice: the payment lands on the invoice, the due mirrors it.
                    LedgerService::addPayment((int)$due['invoice_id'], $amount, $method, $note, auth()['id'], 'due');
                    Database::run('INSERT INTO due_payments (due_id, book_id, amount, payment_method, note, paid_by, paid_at) VALUES (?,?,?,?,?,?,?)',
                        [$due['id'], $book['id'], $amount, $method, $note, auth()['id'], now()]);
                } else {
                    $newPaid = round((float)$due['paid_amount'] + $amount, 2);
                    $st = $newPaid >= (float)$due['amount'] - 0.004 ? 'paid' : 'partial';
                    Database::run('UPDATE dues SET paid_amount=?, status=?, updated_at=? WHERE id=?', [$newPaid, $st, now(), $due['id']]);
                    Database::run('INSERT INTO due_payments (due_id, book_id, amount, payment_method, note, paid_by, paid_at) VALUES (?,?,?,?,?,?,?)',
                        [$due['id'], $book['id'], $amount, $method, $note, auth()['id'], now()]);
                }
            });
        } catch (\RuntimeException $e) {
            redirect($back, ['error' => $e->getMessage()]);
        }

        $fresh = Database::row('SELECT paid_amount,status FROM dues WHERE id=?', [$due['id']]);
        ActivityLogger::write($book['id'], auth()['id'], 'due.payment', 'Due', (int)$due['id'],
            "Due payment recorded — {$due['title']} — {$amount} (status: {$fresh['status']})",
            ['paid_amount'=>$due['paid_amount'],'status'=>$due['status']],
            ['paid_amount'=>$fresh['paid_amount'],'status'=>$fresh['status'],'payment'=>$amount]);

        redirect($back, ['success' => format_money($amount).' recorded'.(!empty($due['invoice_id']) ? ' — the invoice shows it too.' : '.')]);
    }

    public function cancel(array $params): void
    {
        if (guest()) redirect('/login');
        csrf_verify();
        $book = $this->getBookOrFail($params['id']);
        if (!book_can($book, 'dues', 'edit')) abort_403();
        $due  = $this->getDueOrFail($params['due_id'], $book['id']);
        if (!empty($due['invoice_id'])) redirect('/books/'.$book['id'].'/dues', ['error' => 'This belongs to an invoice — cancel or delete the invoice itself and this follows.']);

        Database::run(
            "UPDATE dues SET status='cancelled', updated_at=? WHERE id=?",
            [now(), $due['id']]
        );

        ActivityLogger::write($book['id'], auth()['id'], 'due.cancelled', 'Due', (int)$due['id'],
            "Due cancelled — {$due['title']}",
            ['status'=>$due['status']], ['status'=>'cancelled']);

        redirect('/books/'.$book['id'].'/dues', ['success' => 'Due cancelled.']);
    }

    public function delete(array $params): void
    {
        if (guest()) redirect('/login');
        csrf_verify();
        $book = $this->getBookOrFail($params['id']);
        if (!book_can($book, 'dues', 'delete')) abort_403();
        $due  = Database::row('SELECT * FROM dues WHERE id=? AND book_id=?', [$params['due_id'], $book['id']]);
        if ($due && !empty($due['invoice_id'])) redirect('/books/'.$book['id'].'/dues', ['error' => 'This belongs to an invoice — delete the invoice itself and this follows.']);

        ActivityLogger::write($book['id'], auth()['id'], 'due.deleted', 'Due', (int)($due['id'] ?? $params['due_id']),
            "Due deleted — " . ($due['title'] ?? 'unknown') . ' — ' . ($due['amount'] ?? 0),
            $due ? ['title'=>$due['title'],'amount'=>$due['amount']] : null);

        Database::run('DELETE FROM dues WHERE id=? AND book_id=?', [$params['due_id'], $book['id']]);
        redirect('/books/'.$book['id'].'/dues', ['success' => 'Due deleted.']);
    }

    /** Kept for existing callers (online orders): the ledger mirrors the invoice into its due. */
    public static function createFromInvoice(array $invoice): void
    {
        \App\Services\LedgerService::mirrorSettlement(Database::row('SELECT * FROM invoices WHERE id=?', [$invoice['id']]) ?? $invoice);
    }

    public static function syncFromInvoicePayment(int $invoiceId, float $newPaidTotal): void
    {
        $inv = Database::row('SELECT * FROM invoices WHERE id=?', [$invoiceId]);
        if ($inv) \App\Services\LedgerService::mirrorSettlement($inv);
    }

    private function getBookOrFail(string $id): array
    {
        $book = book_for_user($id, 'business');
        if (!$book) { http_response_code(404); require BASE_PATH.'/views/errors/404.php'; exit; }
        return $book;
    }

    private function getDueOrFail(string $dueId, int $bookId): array
    {
        $due = Database::row('SELECT * FROM dues WHERE id=? AND book_id=?', [$dueId, $bookId]);
        if (!$due) { http_response_code(404); require BASE_PATH.'/views/errors/404.php'; exit; }
        return $due;
    }
}
