<?php
namespace App\Controllers;
use App\Helpers\Database;
use App\Services\ActivityLogger;

class SupplierController
{
    public function index(array $params): void
    {
        if (guest()) redirect('/login');
        $book   = $this->getBookOrFail($params['id']);
        if (!book_can($book, 'suppliers', 'view')) abort_403();
        $search = trim($_GET['q'] ?? '');

        $sql = 'SELECT s.*,
                    COUNT(DISTINCT i.id) AS invoice_count,
                    COALESCE(SUM(i.total),0) AS total_billed,
                    COALESCE(SUM(i.paid),0)  AS total_paid
                FROM suppliers s
                LEFT JOIN invoices i ON i.supplier_id=s.id AND i.deleted_at IS NULL
                WHERE s.book_id=? AND s.deleted_at IS NULL';
        $p = [$book['id']];

        if ($search) {
            $sql .= ' AND (s.name LIKE ? OR s.phone LIKE ? OR s.company LIKE ?)';
            $like = '%'.$search.'%';
            $p    = array_merge($p, [$like, $like, $like]);
        }

        $sql .= ' GROUP BY s.id ORDER BY s.name';
        $suppliers = Database::query($sql, $p);

        require BASE_PATH . '/views/business/suppliers/index.php';
    }

    public function show(array $params): void
    {
        if (guest()) redirect('/login');
        $book     = $this->getBookOrFail($params['id']);
        if (!book_can($book, 'suppliers', 'view')) abort_403();
        $supplier = $this->getSupplierOrFail($params['supplier_id'], $book['id']);

        $invoices = Database::query(
            'SELECT * FROM invoices WHERE supplier_id=? AND book_id=? AND deleted_at IS NULL ORDER BY date DESC',
            [$supplier['id'], $book['id']]
        );

        $totals = Database::row(
            'SELECT COALESCE(SUM(total),0) AS total_billed,
                    COALESCE(SUM(paid),0)  AS total_paid,
                    COALESCE(SUM(total)-SUM(paid),0) AS total_due
             FROM invoices WHERE supplier_id=? AND book_id=? AND deleted_at IS NULL',
            [$supplier['id'], $book['id']]
        );

        // Debts for this supplier
        $debts = [];
        try {
            $debts = Database::query(
                'SELECT * FROM debts WHERE supplier_id=? AND book_id=? ORDER BY created_at DESC',
                [$supplier['id'], $book['id']]
            );
        } catch (\Throwable $e) {}

        // Returns for this supplier
        $returns = [];
        try {
            $returns = Database::query(
                'SELECT r.*, i.invoice_no AS orig_invoice_no
                 FROM returns r
                 LEFT JOIN invoices i ON r.invoice_id = i.id
                 WHERE r.supplier_id=? AND r.book_id=? AND r.deleted_at IS NULL
                 ORDER BY r.date DESC',
                [$supplier['id'], $book['id']]
            );
        } catch (\Throwable $e) {}

        require BASE_PATH . '/views/business/suppliers/show.php';
    }

    public function store(array $params): void
    {
        if (guest()) redirect('/login');
        csrf_verify();
        $book = $this->getBookOrFail($params['id']);
        if (!book_can($book, 'suppliers', 'create')) abort_403();

        $name = trim($_POST['name'] ?? '');
        if (!$name) redirect('/books/'.$book['id'].'/suppliers', ['error' => 'Name is required.']);

        Database::run(
            'INSERT INTO suppliers (book_id,name,company,phone,email,address,notes,created_at) VALUES (?,?,?,?,?,?,?,?)',
            [$book['id'], $name,
             trim($_POST['company'] ?? '') ?: null,
             trim($_POST['phone']   ?? '') ?: null,
             trim($_POST['email']   ?? '') ?: null,
             trim($_POST['address'] ?? '') ?: null,
             trim($_POST['notes']   ?? '') ?: null,
             now()]
        );
        $supId = Database::lastId();
        ActivityLogger::write($book['id'], auth()['id'], 'supplier.created', 'Supplier', $supId,
            "Supplier added — {$name}", null, ['name'=>$name]);
        redirect('/books/'.$book['id'].'/suppliers', ['success' => $name.' added.']);
    }

    public function update(array $params): void
    {
        if (guest()) redirect('/login');
        csrf_verify();
        $book     = $this->getBookOrFail($params['id']);
        if (!book_can($book, 'suppliers', 'edit')) abort_403();
        $supplier = $this->getSupplierOrFail($params['supplier_id'], $book['id']);
        $newName  = trim($_POST['name'] ?? $supplier['name']);

        ActivityLogger::write($book['id'], auth()['id'], 'supplier.updated', 'Supplier', (int)$supplier['id'],
            "Supplier updated — {$newName}",
            ['name'=>$supplier['name']], ['name'=>$newName]);
        Database::run(
            'UPDATE suppliers SET name=?,company=?,phone=?,email=?,address=?,notes=? WHERE id=?',
            [$newName,
             trim($_POST['company'] ?? '') ?: null,
             trim($_POST['phone']   ?? '') ?: null,
             trim($_POST['email']   ?? '') ?: null,
             trim($_POST['address'] ?? '') ?: null,
             trim($_POST['notes']   ?? '') ?: null,
             $supplier['id']]
        );
        redirect('/books/'.$book['id'].'/suppliers/'.$supplier['id'], ['success' => 'Supplier updated.']);
    }

    public function delete(array $params): void
    {
        if (guest()) redirect('/login');
        csrf_verify();
        $book     = $this->getBookOrFail($params['id']);
        if (!book_can($book, 'suppliers', 'delete')) abort_403();
        $supplier = $this->getSupplierOrFail($params['supplier_id'], $book['id']);

        ActivityLogger::write($book['id'], auth()['id'], 'supplier.deleted', 'Supplier', (int)$supplier['id'],
            "Supplier deleted — {$supplier['name']}", ['name'=>$supplier['name']]);
        Database::run('UPDATE suppliers SET deleted_at=? WHERE id=?', [now(), $supplier['id']]);
        redirect('/books/'.$book['id'].'/suppliers', ['success' => $supplier['name'].' deleted.']);
    }

        private function getBookOrFail(string $id): array
    {
        $book = book_for_user($id, 'business');
        if (!$book) { http_response_code(404); require BASE_PATH.'/views/errors/404.php'; exit; }
        return $book;
    }

    private function getSupplierOrFail(string $sid, int $bookId): array
    {
        $s = Database::row('SELECT * FROM suppliers WHERE id=? AND book_id=? AND deleted_at IS NULL', [$sid, $bookId]);
        if (!$s) { http_response_code(404); require BASE_PATH.'/views/errors/404.php'; exit; }
        return $s;
    }
}
