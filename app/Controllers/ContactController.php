<?php
// =============================================================================
// app/Controllers/ContactController.php
// =============================================================================

namespace App\Controllers;

use App\Helpers\Database;

class ContactController
{
    // =========================================================================
    // ADD CONTACT  →  POST /books/{id}/contacts/add
    // =========================================================================
    public function store(array $params): void
    {
        if (guest()) redirect('/login');
        csrf_verify();

        $book = $this->getBookOrFail($params['id']);

        $name    = trim($_POST['name'] ?? '');
        $phone   = trim($_POST['phone'] ?? '');
        $email   = trim($_POST['email'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $notes   = trim($_POST['notes'] ?? '');

        if (!$name) {
            redirect('/books/' . $book['id'] . '/contacts', ['error' => 'Contact name is required.']);
        }

        Database::run(
            'INSERT INTO contacts (book_id, name, phone, email, address, notes, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$book['id'], $name, $phone ?: null, $email ?: null, $address ?: null, $notes ?: null, now()]
        );

        redirect('/books/' . $book['id'] . '/contacts', ['success' => $name . ' added to contacts.']);
    }

    // =========================================================================
    // LIST CONTACTS  →  GET /books/{id}/contacts
    // =========================================================================
    public function index(array $params): void
    {
        if (guest()) redirect('/login');

        $book = $this->getBookOrFail($params['id']);

        // Business books get unified contacts view
        if ($book['type'] === 'business') {
            $customers = \App\Helpers\Database::query(
                'SELECT c.*,
                    COUNT(DISTINCT i.id) AS invoice_count,
                    COALESCE(SUM(i.total),0) AS total_billed
                 FROM customers c
                 LEFT JOIN invoices i ON i.customer_id=c.id AND i.deleted_at IS NULL
                 WHERE c.book_id=? AND c.deleted_at IS NULL
                 GROUP BY c.id ORDER BY c.name',
                [$book['id']]
            );

            $suppliers = \App\Helpers\Database::query(
                'SELECT s.*,
                    COUNT(DISTINCT i.id) AS invoice_count,
                    COALESCE(SUM(i.total),0) AS total_billed
                 FROM suppliers s
                 LEFT JOIN invoices i ON i.supplier_id=s.id AND i.deleted_at IS NULL
                 WHERE s.book_id=? AND s.deleted_at IS NULL
                 GROUP BY s.id ORDER BY s.name',
                [$book['id']]
            );

            $employees = \App\Helpers\Database::query(
                'SELECT * FROM employees WHERE book_id=? AND deleted_at IS NULL ORDER BY name',
                [$book['id']]
            );

            $activeTab  = $_GET['type'] ?? 'all';
            $totalCount = count($customers) + count($suppliers) + count($employees);

            require BASE_PATH . '/views/business/contacts/index.php';
            return;
        }

        // Personal books: original contacts view
        $contacts = \App\Helpers\Database::query(
            'SELECT c.*,
                COUNT(e.id) AS entry_count,
                COALESCE(SUM(CASE WHEN e.type="in"  THEN e.amount ELSE 0 END),0) AS total_in,
                COALESCE(SUM(CASE WHEN e.type="out" THEN e.amount ELSE 0 END),0) AS total_out
             FROM contacts c
             LEFT JOIN entries e ON e.contact_id = c.id AND e.deleted_at IS NULL
             WHERE c.book_id = ? AND c.deleted_at IS NULL
             GROUP BY c.id
             ORDER BY c.name',
            [$book['id']]
        );

        require BASE_PATH . '/views/books/contacts.php';
    }

    // =========================================================================
    // EDIT CONTACT  →  POST /books/{id}/contacts/{contact_id}/edit
    // =========================================================================
    public function update(array $params): void
    {
        if (guest()) redirect('/login');
        csrf_verify();

        $book = $this->getBookOrFail($params['id']);

        $name    = trim($_POST['name'] ?? '');
        $phone   = trim($_POST['phone'] ?? '');
        $email   = trim($_POST['email'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $notes   = trim($_POST['notes'] ?? '');

        if (!$name) {
            redirect('/books/' . $book['id'] . '/contacts', ['error' => 'Contact name is required.']);
        }

        Database::run(
            'UPDATE contacts SET name=?, phone=?, email=?, address=?, notes=? WHERE id=? AND book_id=?',
            [$name, $phone ?: null, $email ?: null, $address ?: null, $notes ?: null,
             $params['contact_id'], $book['id']]
        );

        redirect('/books/' . $book['id'] . '/contacts', ['success' => e($name) . ' updated.']);
    }

    // =========================================================================
    // DELETE CONTACT  →  POST /books/{id}/contacts/{contact_id}/delete
    // =========================================================================
    public function delete(array $params): void
    {
        if (guest()) redirect('/login');
        csrf_verify();

        $book = $this->getBookOrFail($params['id']);

        Database::run(
            'UPDATE contacts SET deleted_at = ? WHERE id = ? AND book_id = ?',
            [now(), $params['contact_id'], $book['id']]
        );

        redirect('/books/' . $book['id'] . '/contacts', ['success' => 'Contact deleted.']);
    }

    // =========================================================================
    // PRIVATE
    // =========================================================================
    private function getBookOrFail(string $id): array
    {
        $book = Database::row(
            'SELECT * FROM books WHERE id = ? AND deleted_at IS NULL AND (user_id = ? OR EXISTS(
                SELECT 1 FROM book_members WHERE book_id=books.id AND user_id=? AND status="active"
            ))',
            [$id, auth()['id'], auth()['id']]
        );
        if (!$book) {
            http_response_code(404);
            require BASE_PATH . '/views/errors/404.php';
            exit;
        }
        return $book;
    }
}
