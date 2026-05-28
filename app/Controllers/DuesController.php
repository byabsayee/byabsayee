<?php
namespace App\Controllers;
use App\Helpers\Database;
use App\Services\ActivityLogger;

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
                COALESCE(SUM(paid_amount), 0) AS total_collected,
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
        $dueDate    = $_POST['due_date'] ?: null;
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

        $title   = trim($_POST['title'] ?? '');
        $amount  = (float)($_POST['amount'] ?? 0);
        $dueDate = $_POST['due_date'] ?: null;
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

        $amount    = (float)($_POST['amount'] ?? 0);
        $remaining = (float)$due['amount'] - (float)$due['paid_amount'];
        $amount    = min($amount, $remaining);

        if ($amount <= 0) {
            redirect('/books/'.$book['id'].'/dues', ['error' => 'Invalid payment amount.']);
        }

        $newPaid   = (float)$due['paid_amount'] + $amount;
        $newStatus = $newPaid >= ((float)$due['amount'] - 0.001) ? 'paid' : 'partial';

        Database::run(
            'UPDATE dues SET paid_amount=?, status=?, updated_at=? WHERE id=?',
            [$newPaid, $newStatus, now(), $due['id']]
        );

        Database::run(
            'INSERT INTO due_payments (due_id, book_id, amount, payment_method, note, paid_by, paid_at)
             VALUES (?,?,?,?,?,?,?)',
            [$due['id'], $book['id'], $amount,
             trim($_POST['payment_method'] ?? 'cash'),
             trim($_POST['note'] ?? '') ?: null,
             auth()['id'], now()]
        );

        ActivityLogger::write($book['id'], auth()['id'], 'due.payment', 'Due', (int)$due['id'],
            "Due payment recorded — {$due['title']} — {$amount} (status: {$newStatus})",
            ['paid_amount'=>$due['paid_amount'],'status'=>$due['status']],
            ['paid_amount'=>$newPaid,'status'=>$newStatus,'payment'=>$amount]);

        redirect('/books/'.$book['id'].'/dues', ['success' => format_money($amount).' recorded.']);
    }

    public function cancel(array $params): void
    {
        if (guest()) redirect('/login');
        csrf_verify();
        $book = $this->getBookOrFail($params['id']);
        if (!book_can($book, 'dues', 'edit')) abort_403();
        $due  = $this->getDueOrFail($params['due_id'], $book['id']);

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

        ActivityLogger::write($book['id'], auth()['id'], 'due.deleted', 'Due', (int)($due['id'] ?? $params['due_id']),
            "Due deleted — " . ($due['title'] ?? 'unknown') . ' — ' . ($due['amount'] ?? 0),
            $due ? ['title'=>$due['title'],'amount'=>$due['amount']] : null);

        Database::run('DELETE FROM dues WHERE id=? AND book_id=?', [$params['due_id'], $book['id']]);
        redirect('/books/'.$book['id'].'/dues', ['success' => 'Due deleted.']);
    }

    public static function createFromInvoice(array $invoice): void
    {
        if (empty($invoice['customer_id'])) return;
        try {
            $existing = Database::row(
                'SELECT id FROM dues WHERE invoice_id=? AND book_id=?',
                [$invoice['id'], $invoice['book_id']]
            );
            if ($existing) return;

            Database::run(
                'INSERT INTO dues (book_id, customer_id, invoice_id, title, amount, paid_amount, status, created_by, created_at)
                 VALUES (?,?,?,?,?,0,?,?,?)',
                [
                    $invoice['book_id'],
                    $invoice['customer_id'],
                    $invoice['id'],
                    'Invoice #' . $invoice['invoice_no'],
                    $invoice['total'],
                    'unpaid',
                    auth()['id'] ?? null,
                    now()
                ]
            );
        } catch (\Throwable $e) {
            error_log('[DuesController::createFromInvoice] ' . $e->getMessage());
        }
    }

    public static function syncFromInvoicePayment(int $invoiceId, float $newPaidTotal): void
    {
        try {
            $due = Database::row("SELECT * FROM dues WHERE invoice_id=? AND status != 'cancelled'", [$invoiceId]);
            if (!$due) return;

            if ($newPaidTotal <= 0) {
                Database::run("UPDATE dues SET paid_amount=0, status='unpaid', updated_at=? WHERE id=?",
                    [date('Y-m-d H:i:s'), $due['id']]);
            } elseif ($newPaidTotal >= ((float)$due['amount'] - 0.001)) {
                Database::run("UPDATE dues SET paid_amount=?, status='paid', updated_at=? WHERE id=?",
                    [(float)$due['amount'], date('Y-m-d H:i:s'), $due['id']]);
            } else {
                Database::run("UPDATE dues SET paid_amount=?, status='partial', updated_at=? WHERE id=?",
                    [$newPaidTotal, date('Y-m-d H:i:s'), $due['id']]);
            }
        } catch (\Throwable $e) {
            error_log('[DuesController::syncFromInvoicePayment] ' . $e->getMessage());
        }
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
