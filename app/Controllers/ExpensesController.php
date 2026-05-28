<?php
namespace App\Controllers;
use App\Helpers\Database;
use App\Services\ActivityLogger;

class ExpensesController
{
    public function index(array $params): void
    {
        if (guest()) redirect('/login');
        $book = $this->getBookOrFail($params['id']);
        if (!book_can($book, 'expenses', 'view')) abort_403();

        $month    = $_GET['month'] ?? date('Y-m');
        $catId    = (int)($_GET['cat'] ?? 0);
        $dateFrom = $month . '-01';
        $dateTo   = date('Y-m-t', strtotime($dateFrom));

        $categories = Database::query(
            'SELECT * FROM expense_categories WHERE book_id=? AND is_active=1 ORDER BY name',
            [$book['id']]
        );

        $where = ['e.book_id=?', 'e.expense_date BETWEEN ? AND ?'];
        $bind  = [$book['id'], $dateFrom, $dateTo];

        if ($catId > 0) {
            $where[] = 'e.category_id=?';
            $bind[]  = $catId;
        }

        $whereSQL = implode(' AND ', $where);

        $expenses = Database::query(
            "SELECT e.*,
                    e.expense_date AS date,
                    e.attachment   AS receipt,
                    ec.name AS category_name,
                    ec.icon AS category_icon
             FROM expenses e
             LEFT JOIN expense_categories ec ON ec.id = e.category_id
             WHERE {$whereSQL}
             ORDER BY e.expense_date DESC, e.id DESC",
            $bind
        );

        $monthTotal = array_sum(array_column($expenses, 'amount'));

        require BASE_PATH . '/views/business/expenses/index.php';
    }

    public function store(array $params): void
    {
        if (guest()) redirect('/login');
        csrf_verify();
        $book = $this->getBookOrFail($params['id']);
        if (!book_can($book, 'expenses', 'create')) abort_403();

        $title  = trim($_POST['title']  ?? '');
        $amount = (float)($_POST['amount'] ?? 0);
        $date   = $_POST['date'] ?? date('Y-m-d');
        $catId  = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
        $paidTo = trim($_POST['paid_to'] ?? '');
        $note   = trim($_POST['note']   ?? '');

        if (!$title || $amount <= 0) {
            redirect('/books/'.$book['id'].'/expenses', ['error' => 'Title and amount are required.']);
        }

        $attachment = null;
        if (!empty($_FILES['receipt']['name']) && $_FILES['receipt']['error'] === 0) {
            $attachment = $this->handleUpload($_FILES['receipt'], $book['id']);
        }

        Database::run(
            'INSERT INTO expenses (book_id, category_id, title, amount, expense_date, paid_to, note, attachment, created_by, created_at)
             VALUES (?,?,?,?,?,?,?,?,?,?)',
            [$book['id'], $catId, $title, $amount, $date,
             $paidTo ?: null, $note ?: null, $attachment, auth()['id'], now()]
        );
        $expId = Database::lastId();

        ActivityLogger::write($book['id'], auth()['id'], 'expense.created', 'Expense', $expId,
            "Expense recorded — {$title} — {$amount}" . ($paidTo ? " paid to {$paidTo}" : ''),
            null, ['title'=>$title,'amount'=>$amount,'date'=>$date,'paid_to'=>$paidTo]);

        redirect('/books/'.$book['id'].'/expenses', ['success' => 'Expense recorded.']);
    }

    public function update(array $params): void
    {
        if (guest()) redirect('/login');
        csrf_verify();
        $book    = $this->getBookOrFail($params['id']);
        if (!book_can($book, 'expenses', 'edit')) abort_403();
        $expense = $this->getExpenseOrFail($params['expense_id'], $book['id']);

        $title  = trim($_POST['title']  ?? '');
        $amount = (float)($_POST['amount'] ?? 0);
        $date   = $_POST['date'] ?? $expense['expense_date'];
        $catId  = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
        $paidTo = trim($_POST['paid_to'] ?? '');
        $note   = trim($_POST['note']   ?? '');

        if (!$title || $amount <= 0) {
            redirect('/books/'.$book['id'].'/expenses', ['error' => 'Title and amount are required.']);
        }

        ActivityLogger::write($book['id'], auth()['id'], 'expense.updated', 'Expense', (int)$expense['id'],
            "Expense updated — {$title} — {$amount}",
            ['title'=>$expense['title'],'amount'=>$expense['amount']],
            ['title'=>$title,'amount'=>$amount,'date'=>$date]);

        Database::run(
            'UPDATE expenses SET category_id=?, title=?, amount=?, expense_date=?, paid_to=?, note=?, updated_at=?
             WHERE id=? AND book_id=?',
            [$catId, $title, $amount, $date, $paidTo ?: null, $note ?: null, now(),
             $expense['id'], $book['id']]
        );

        redirect('/books/'.$book['id'].'/expenses', ['success' => 'Expense updated.']);
    }

    public function delete(array $params): void
    {
        if (guest()) redirect('/login');
        csrf_verify();
        $book    = $this->getBookOrFail($params['id']);
        if (!book_can($book, 'expenses', 'delete')) abort_403();
        $expense = Database::row('SELECT * FROM expenses WHERE id=? AND book_id=?', [$params['expense_id'], $book['id']]);

        ActivityLogger::write($book['id'], auth()['id'], 'expense.deleted', 'Expense', (int)($expense['id'] ?? $params['expense_id']),
            "Expense deleted — " . ($expense['title'] ?? 'unknown') . ' — ' . ($expense['amount'] ?? 0),
            $expense ? ['title'=>$expense['title'],'amount'=>$expense['amount']] : null);

        Database::run(
            'DELETE FROM expenses WHERE id=? AND book_id=?',
            [$params['expense_id'], $book['id']]
        );

        redirect('/books/'.$book['id'].'/expenses', ['success' => 'Expense deleted.']);
    }

    public function storeCategory(array $params): void
    {
        if (guest()) redirect('/login');
        csrf_verify();
        $book = $this->getBookOrFail($params['id']);
        if (!book_can($book, 'expenses', 'create')) abort_403();

        $name = trim($_POST['name'] ?? '');
        $icon = trim($_POST['icon'] ?? 'fa-tag');

        // Only allow known FA icon names (prevent arbitrary injection)
        $allowedIcons = [
            'fa-tag','fa-bolt','fa-building','fa-users','fa-truck','fa-wrench',
            'fa-bullhorn','fa-ellipsis','fa-utensils','fa-laptop','fa-phone',
            'fa-car','fa-home','fa-heart','fa-shield','fa-graduation-cap',
            'fa-globe','fa-box','fa-tools','fa-paint-brush','fa-seedling',
            'fa-leaf','fa-water','fa-fire','fa-star','fa-coffee','fa-tshirt',
        ];
        if (!in_array($icon, $allowedIcons, true)) {
            $icon = 'fa-tag';
        }

        if (!$name) {
            redirect('/books/'.$book['id'].'/expenses', ['error' => 'Category name is required.']);
        }

        $exists = Database::row(
            'SELECT id FROM expense_categories WHERE book_id=? AND name=?',
            [$book['id'], $name]
        );

        if (!$exists) {
            Database::run(
                'INSERT INTO expense_categories (book_id, name, icon, is_active) VALUES (?,?,?,1)',
                [$book['id'], $name, $icon]
            );
        }

        redirect('/books/'.$book['id'].'/expenses', ['success' => 'Category created.']);
    }

    public static function seedDefaultCategories(int $bookId): void
    {
        $defaults = [
            ['Utility',       'fa-bolt'],
            ['Rent',          'fa-building'],
            ['Salary',        'fa-users'],
            ['Transport',     'fa-truck'],
            ['Maintenance',   'fa-wrench'],
            ['Marketing',     'fa-bullhorn'],
            ['Miscellaneous', 'fa-tag'],
        ];
        foreach ($defaults as [$name, $icon]) {
            $exists = Database::row(
                'SELECT id FROM expense_categories WHERE book_id=? AND name=?',
                [$bookId, $name]
            );
            if (!$exists) {
                Database::run(
                    'INSERT INTO expense_categories (book_id, name, icon, is_active) VALUES (?,?,?,1)',
                    [$bookId, $name, $icon]
                );
            }
        }
    }

    private function getExpenseOrFail(string $expenseId, int $bookId): array
    {
        $exp = Database::row('SELECT * FROM expenses WHERE id=? AND book_id=?', [$expenseId, $bookId]);
        if (!$exp) { http_response_code(404); require BASE_PATH.'/views/errors/404.php'; exit; }
        return $exp;
    }

        private function getBookOrFail(string $id): array
    {
        $book = book_for_user($id, 'business');
        if (!$book) { http_response_code(404); require BASE_PATH.'/views/errors/404.php'; exit; }
        return $book;
    }

    private function handleUpload(array $file, int $bookId): ?string
    {
        $allowed = ['pdf', 'jpg', 'jpeg', 'png', 'webp'];
        $ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed) || $file['size'] > config('upload.max_size')) return null;

        $dir = config('upload.path') . '/expenses/' . $bookId;
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        if (!is_writable($dir)) return null;

        $filename = 'exp_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        if (move_uploaded_file($file['tmp_name'], $dir . '/' . $filename)) {
            return 'expenses/' . $bookId . '/' . $filename;
        }
        return null;
    }
}
