<?php
namespace App\Controllers;
use App\Helpers\Database;
use App\Services\ActivityLogger;

class CustomerController
{
    public function index(array $params): void
    {
        if (guest()) redirect('/login');
        $book   = $this->getBookOrFail($params['id']);
        if (!book_can($book, 'customers', 'view')) abort_403();
        $search = trim($_GET['q'] ?? '');

        $sql = 'SELECT c.*,
                    COUNT(DISTINCT i.id) AS invoice_count,
                    COALESCE(SUM(i.total),0) AS total_billed,
                    COALESCE(SUM(i.paid),0)  AS total_paid
                FROM customers c
                LEFT JOIN invoices i ON i.customer_id=c.id AND i.deleted_at IS NULL
                WHERE c.book_id=? AND c.deleted_at IS NULL';
        $p = [$book['id']];

        if ($search) {
            $sql .= ' AND (c.name LIKE ? OR c.phone LIKE ? OR c.email LIKE ?)';
            $like = '%'.$search.'%';
            $p    = array_merge($p, [$like, $like, $like]);
        }

        $sql .= ' GROUP BY c.id ORDER BY c.name';
        $customers = Database::query($sql, $p);

        $privileges = Database::query(
            'SELECT p.*, COUNT(c2.id) AS customer_count
             FROM customer_privileges p
             LEFT JOIN customers c2 ON c2.privilege_id=p.id AND c2.deleted_at IS NULL
             WHERE p.book_id=?
             GROUP BY p.id ORDER BY p.name',
            [$book['id']]
        );

        require BASE_PATH . '/views/business/customers/index.php';
    }

    public function show(array $params): void
    {
        if (guest()) redirect('/login');
        $book     = $this->getBookOrFail($params['id']);
        if (!book_can($book, 'customers', 'view')) abort_403();
        $customer = $this->getCustomerOrFail($params['customer_id'], $book['id']);

        $invoices = Database::query(
            'SELECT * FROM invoices WHERE customer_id=? AND book_id=? AND deleted_at IS NULL ORDER BY date DESC',
            [$customer['id'], $book['id']]
        );
        $totals = Database::row(
            'SELECT COALESCE(SUM(total),0) AS total_billed,
                    COALESCE(SUM(paid),0)  AS total_paid,
                    COALESCE(SUM(total)-SUM(paid),0) AS total_due
             FROM invoices WHERE customer_id=? AND book_id=? AND deleted_at IS NULL',
            [$customer['id'], $book['id']]
        );

        // Dues for this customer
        $dues = [];
        try {
            $dues = Database::query(
                'SELECT * FROM dues WHERE customer_id=? AND book_id=? ORDER BY created_at DESC',
                [$customer['id'], $book['id']]
            );
        } catch (\Throwable $e) {}

        // Returns for this customer
        $returns = [];
        try {
            $returns = Database::query(
                'SELECT r.*, i.invoice_no AS orig_invoice_no
                 FROM returns r
                 LEFT JOIN invoices i ON r.invoice_id = i.id
                 WHERE r.customer_id=? AND r.book_id=? AND r.deleted_at IS NULL
                 ORDER BY r.date DESC',
                [$customer['id'], $book['id']]
            );
        } catch (\Throwable $e) {}

        // Privileges
        $privileges = Database::query(
            'SELECT * FROM customer_privileges WHERE book_id=? ORDER BY name',
            [$book['id']]
        );

        require BASE_PATH . '/views/business/customers/show.php';
    }

    public function store(array $params): void
    {
        if (guest()) redirect('/login');
        csrf_verify();
        $book = $this->getBookOrFail($params['id']);
        if (!book_can($book, 'customers', 'create')) abort_403();

        $name = trim($_POST['name'] ?? '');
        if (!$name) redirect('/books/'.$book['id'].'/customers', ['error' => 'Name is required.']);

        Database::run(
            'INSERT INTO customers (book_id,name,phone,email,address,notes,created_at)
             VALUES (?,?,?,?,?,?,?)',
            [$book['id'], $name,
             trim($_POST['phone']   ?? '') ?: null,
             trim($_POST['email']   ?? '') ?: null,
             trim($_POST['address'] ?? '') ?: null,
             trim($_POST['notes']   ?? '') ?: null,
             now()]
        );
        $custId = Database::lastId();
        ActivityLogger::write($book['id'], auth()['id'], 'customer.created', 'Customer', $custId,
            "Customer added — {$name}", null, ['name'=>$name]);
        redirect('/books/'.$book['id'].'/customers', ['success' => $name.' added.']);
    }

    public function update(array $params): void
    {
        if (guest()) redirect('/login');
        csrf_verify();
        $book     = $this->getBookOrFail($params['id']);
        if (!book_can($book, 'customers', 'edit')) abort_403();
        $customer = $this->getCustomerOrFail($params['customer_id'], $book['id']);

        $name = trim($_POST['name'] ?? '');
        if (!$name) redirect('/books/'.$book['id'].'/customers/'.$customer['id'], ['error' => 'Name is required.']);

        ActivityLogger::write($book['id'], auth()['id'], 'customer.updated', 'Customer', (int)$customer['id'],
            "Customer updated — {$name}",
            ['name'=>$customer['name']], ['name'=>$name]);
        Database::run(
            'UPDATE customers SET name=?,phone=?,email=?,address=?,notes=? WHERE id=?',
            [$name,
             trim($_POST['phone']   ?? '') ?: null,
             trim($_POST['email']   ?? '') ?: null,
             trim($_POST['address'] ?? '') ?: null,
             trim($_POST['notes']   ?? '') ?: null,
             $customer['id']]
        );
        redirect('/books/'.$book['id'].'/customers/'.$customer['id'], ['success' => 'Customer updated.']);
    }

    public function delete(array $params): void
    {
        if (guest()) redirect('/login');
        csrf_verify();
        $book     = $this->getBookOrFail($params['id']);
        if (!book_can($book, 'customers', 'delete')) abort_403();
        $customer = $this->getCustomerOrFail($params['customer_id'], $book['id']);

        ActivityLogger::write($book['id'], auth()['id'], 'customer.deleted', 'Customer', (int)$customer['id'],
            "Customer deleted — {$customer['name']}", ['name'=>$customer['name']]);
        Database::run('UPDATE customers SET deleted_at=? WHERE id=?', [now(), $customer['id']]);
        redirect('/books/'.$book['id'].'/customers', ['success' => $customer['name'].' deleted.']);
    }

    // JSON search endpoint — used by Dues "Add Due" modal
    public function search(array $params): void
    {
        if (guest()) json_response(['error' => 'Unauthorized'], 401);
        $book = $this->getBookOrFail($params['id']);
        if (!book_can($book, 'customers', 'view')) abort_403();
        $q    = trim($_GET['q'] ?? '');

        if (strlen($q) < 2) {
            json_response([]);
        }

        $customers = Database::query(
            'SELECT id, name, phone FROM customers
             WHERE book_id=? AND deleted_at IS NULL
               AND (name LIKE ? OR phone LIKE ?)
             ORDER BY name LIMIT 10',
            [$book['id'], "%{$q}%", "%{$q}%"]
        );

        json_response($customers);
    }

    // Update multi-privileges — POST /books/{id}/customers/{customer_id}/privileges
    public function updatePrivileges(array $params): void
    {
        if (guest()) redirect('/login');
        csrf_verify();
        $book     = $this->getBookOrFail($params['id']);
        if (!book_can($book, 'customers', 'edit')) abort_403();
        $customer = $this->getCustomerOrFail($params['customer_id'], $book['id']);

        // Try both table names (original uses customer_privileges as junction,
        // addons schema uses customer_privilege_assignments)
        try {
            Database::run(
                'DELETE FROM customer_privilege_assignments WHERE customer_id=?',
                [$customer['id']]
            );
        } catch (\Throwable $e) {
            // Table may not exist yet — ignore
        }

        $selected = $_POST['privilege_ids'] ?? [];
        foreach ($selected as $privId) {
            $privId = (int)$privId;
            if (!$privId) continue;
            $priv = Database::row(
                'SELECT id FROM customer_privileges WHERE id=? AND book_id=?',
                [$privId, $book['id']]
            );
            if ($priv) {
                try {
                    Database::run(
                        'INSERT IGNORE INTO customer_privilege_assignments (customer_id,privilege_id) VALUES (?,?)',
                        [$customer['id'], $privId]
                    );
                } catch (\Throwable $e) {}
            }
        }

        redirect('/books/'.$book['id'].'/customers/'.$customer['id'], ['success' => 'Privileges updated.']);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

        private function getBookOrFail(string $id): array
    {
        $book = book_for_user($id, 'business');
        if (!$book) { http_response_code(404); require BASE_PATH.'/views/errors/404.php'; exit; }
        return $book;
    }

    private function getCustomerOrFail(string $cid, int $bookId): array
    {
        $c = Database::row(
            'SELECT * FROM customers WHERE id=? AND book_id=? AND deleted_at IS NULL',
            [$cid, $bookId]
        );
        if (!$c) { http_response_code(404); require BASE_PATH.'/views/errors/404.php'; exit; }
        return $c;
    }
}