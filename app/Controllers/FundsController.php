<?php
namespace App\Controllers;
use App\Helpers\Database;
use App\Services\ActivityLogger;

class FundsController
{
    public function index(array $params): void
    {
        if (guest()) redirect('/login');
        $book = $this->getBookOrFail($params['id']);
        if (!book_can($book, 'funds', 'view')) abort_403();

        $month     = $_GET['month'] ?? date('Y-m');
        $dateFrom  = $month . '-01';
        $dateTo    = date('Y-m-t', strtotime($dateFrom));
        $prevMonth = date('Y-m', strtotime($dateFrom . ' -1 month'));
        $nextMonth = date('Y-m', strtotime($dateFrom . ' +1 month'));
        $isCurrent = ($month === date('Y-m'));

        $transactions = Database::query(
            "SELECT f.*, f.fund_date AS date, f.title AS source
             FROM funds f
             WHERE f.book_id=? AND f.fund_date BETWEEN ? AND ?
             ORDER BY f.fund_date DESC, f.id DESC",
            [$book['id'], $dateFrom, $dateTo]
        );

        $totals = Database::row(
            "SELECT
                COALESCE(SUM(CASE WHEN type='in'  THEN amount ELSE 0 END), 0) AS total_added,
                COALESCE(SUM(CASE WHEN type='out' THEN amount ELSE 0 END), 0) AS total_withdrawn
             FROM funds WHERE book_id=? AND fund_date BETWEEN ? AND ?",
            [$book['id'], $dateFrom, $dateTo]
        );

        require BASE_PATH . '/views/business/funds/index.php';
    }

    public function store(array $params): void
    {
        if (guest()) redirect('/login');
        csrf_verify();
        $book = $this->getBookOrFail($params['id']);
        if (!book_can($book, 'funds', 'create')) abort_403();

        $type   = (($_POST['type'] ?? 'add') === 'withdraw') ? 'out' : 'in';
        $amount = (float)($_POST['amount'] ?? 0);
        $source = trim($_POST['source'] ?? '');
        $date   = $_POST['date'] ?? date('Y-m-d');
        $note   = trim($_POST['note'] ?? '');

        if ($amount <= 0) {
            redirect('/books/'.$book['id'].'/funds', ['error' => 'Amount must be greater than zero.']);
        }
        if (!$source) {
            redirect('/books/'.$book['id'].'/funds', ['error' => 'Please enter a source / reason.']);
        }

        Database::run(
            'INSERT INTO funds (book_id, type, title, amount, fund_date, note, created_by, created_at)
             VALUES (?,?,?,?,?,?,?,?)',
            [$book['id'], $type, $source, $amount, $date, $note ?: null, auth()['id'], now()]
        );
        $fundId = Database::lastId();

        ActivityLogger::write(
            $book['id'], auth()['id'],
            $type === 'in' ? 'fund.in' : 'fund.out',
            'Fund', $fundId,
            ($type === 'in' ? 'Fund received' : 'Fund withdrawn') . " — {$source} — {$amount}",
            null,
            ['type'=>$type,'title'=>$source,'amount'=>$amount,'date'=>$date]
        );

        $label = $type === 'in' ? 'Funds added.' : 'Withdrawal recorded.';
        redirect('/books/'.$book['id'].'/funds', ['success' => $label]);
    }

    public function update(array $params): void
    {
        if (guest()) redirect('/login');
        csrf_verify();
        $book = $this->getBookOrFail($params['id']);
        if (!book_can($book, 'funds', 'edit')) abort_403();
        $fund = $this->getFundOrFail($params['fund_id'], $book['id']);

        $typeRaw = $_POST['type'] ?? $fund['type'];
        $type    = ($typeRaw === 'withdraw' || $typeRaw === 'out') ? 'out' : 'in';
        $amount  = (float)($_POST['amount'] ?? 0);
        $source  = trim($_POST['source'] ?? '');
        $date    = $_POST['date'] ?? $fund['fund_date'];
        $note    = trim($_POST['note'] ?? '');

        if ($amount <= 0) {
            redirect('/books/'.$book['id'].'/funds', ['error' => 'Amount must be greater than zero.']);
        }
        if (!$source) {
            redirect('/books/'.$book['id'].'/funds', ['error' => 'Source / reason is required.']);
        }

        ActivityLogger::write(
            $book['id'], auth()['id'], 'fund.updated',
            'Fund', (int)$fund['id'],
            "Fund updated — {$source} — {$amount}",
            ['type'=>$fund['type'],'title'=>$fund['title'],'amount'=>$fund['amount']],
            ['type'=>$type,'title'=>$source,'amount'=>$amount,'date'=>$date]
        );

        Database::run(
            'UPDATE funds SET type=?, title=?, amount=?, fund_date=?, note=?, updated_at=? WHERE id=? AND book_id=?',
            [$type, $source, $amount, $date, $note ?: null, now(), $fund['id'], $book['id']]
        );

        redirect('/books/'.$book['id'].'/funds', ['success' => 'Transaction updated.']);
    }

    public function delete(array $params): void
    {
        if (guest()) redirect('/login');
        csrf_verify();
        $book = $this->getBookOrFail($params['id']);
        if (!book_can($book, 'funds', 'delete')) abort_403();
        $fund = $this->getFundOrFail($params['fund_id'], $book['id']);

        ActivityLogger::write(
            $book['id'], auth()['id'], 'fund.deleted',
            'Fund', (int)$fund['id'],
            "Fund deleted — {$fund['title']} — {$fund['amount']}",
            ['type'=>$fund['type'],'title'=>$fund['title'],'amount'=>$fund['amount']]
        );

        Database::run('DELETE FROM funds WHERE id=? AND book_id=?', [$params['fund_id'], $book['id']]);
        redirect('/books/'.$book['id'].'/funds', ['success' => 'Transaction deleted.']);
    }

    private function getFundOrFail(string $fundId, int $bookId): array
    {
        $fund = Database::row('SELECT * FROM funds WHERE id=? AND book_id=?', [$fundId, $bookId]);
        if (!$fund) { http_response_code(404); require BASE_PATH.'/views/errors/404.php'; exit; }
        return $fund;
    }

        private function getBookOrFail(string $id): array
    {
        $book = book_for_user($id, 'business');
        if (!$book) { http_response_code(404); require BASE_PATH.'/views/errors/404.php'; exit; }
        return $book;
    }
}
