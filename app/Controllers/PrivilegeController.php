<?php
namespace App\Controllers;
use App\Helpers\Database;

class PrivilegeController
{
    // List + manage privileges  →  GET /books/{id}/privileges
    public function index(array $params): void
    {
        if (guest()) redirect('/login');
        $book       = $this->getBookOrFail($params['id']);
        $privileges = Database::query(
            'SELECT p.*, COUNT(c.id) AS customer_count
             FROM customer_privileges p
             LEFT JOIN customers c ON c.privilege_id=p.id AND c.deleted_at IS NULL
             WHERE p.book_id=?
             GROUP BY p.id ORDER BY p.name',
            [$book['id']]
        );
        require BASE_PATH . '/views/business/privileges/index.php';
    }

    // Add  →  POST /books/{id}/privileges/add
    public function store(array $params): void
    {
        if (guest()) redirect('/login');
        csrf_verify();
        $book = $this->getBookOrFail($params['id']);

        $name          = trim($_POST['name']           ?? '');
        $discountType  = $_POST['discount_type']       ?? 'percent';
        $discountValue = (float)($_POST['discount_value'] ?? 0);
        $description   = trim($_POST['description']    ?? '');

        if (!$name) redirect('/books/'.$book['id'].'/customers', ['error' => 'Name is required.']);
        if (!in_array($discountType, ['percent','fixed'])) $discountType = 'percent';
        if ($discountType === 'percent' && $discountValue > 100) $discountValue = 100;

        Database::run(
            'INSERT INTO customer_privileges (book_id,name,discount_type,discount_value,description,created_at)
             VALUES (?,?,?,?,?,?)',
            [$book['id'], $name, $discountType, $discountValue, $description ?: null, now()]
        );

        redirect('/books/'.$book['id'].'/customers', ['success' => '"'.$name.'" privilege created.']);
    }

    // Update  →  POST /books/{id}/privileges/{priv_id}/edit
    public function update(array $params): void
    {
        if (guest()) redirect('/login');
        csrf_verify();
        $book = $this->getBookOrFail($params['id']);
        $priv = $this->getPrivOrFail($params['priv_id'], $book['id']);

        $name          = trim($_POST['name']           ?? '');
        $discountType  = $_POST['discount_type']       ?? 'percent';
        $discountValue = (float)($_POST['discount_value'] ?? 0);
        $description   = trim($_POST['description']    ?? '');

        if (!$name) redirect('/books/'.$book['id'].'/customers', ['error' => 'Name is required.']);

        Database::run(
            'UPDATE customer_privileges SET name=?,discount_type=?,discount_value=?,description=? WHERE id=?',
            [$name, $discountType, $discountValue, $description ?: null, $priv['id']]
        );

        redirect('/books/'.$book['id'].'/customers', ['success' => 'Privilege updated.']);
    }

    // Delete  →  POST /books/{id}/privileges/{priv_id}/delete
    public function delete(array $params): void
    {
        if (guest()) redirect('/login');
        csrf_verify();
        $book = $this->getBookOrFail($params['id']);
        $priv = $this->getPrivOrFail($params['priv_id'], $book['id']);

        // Remove privilege from all customers first
        Database::run('UPDATE customers SET privilege_id=NULL WHERE privilege_id=?', [$priv['id']]);
        Database::run('DELETE FROM customer_privileges WHERE id=?', [$priv['id']]);

        redirect('/books/'.$book['id'].'/customers', ['success' => '"'.$priv['name'].'" deleted.']);
    }

        private function getBookOrFail(string $id): array
    {
        $book = book_for_user($id, 'business');
        if (!$book) { http_response_code(404); require BASE_PATH.'/views/errors/404.php'; exit; }
        return $book;
    }

    private function getPrivOrFail(string $pid, int $bookId): array
    {
        $p = Database::row('SELECT * FROM customer_privileges WHERE id=? AND book_id=?', [$pid, $bookId]);
        if (!$p) { http_response_code(404); require BASE_PATH.'/views/errors/404.php'; exit; }
        return $p;
    }
}
