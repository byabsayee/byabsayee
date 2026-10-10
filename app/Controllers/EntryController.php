<?php
// =============================================================================
// app/Controllers/EntryController.php — personal-book entries (money in / money out)
// =============================================================================

namespace App\Controllers;

use App\Helpers\Database;

class EntryController
{
    private const MAX_AMOUNT = 9999999999999.99;   // decimal(15,2)

    // =========================================================================
    // EDIT ENTRY  →  POST /books/{id}/entries/{entry_id}/edit
    // =========================================================================
    public function update(array $params): void
    {
        if (guest()) redirect('/login');
        csrf_verify();

        $book = $this->getBookOrFail($params['id']);
        $back = '/books/' . $book['id'];

        $entry = Database::row(
            'SELECT * FROM entries WHERE id = ? AND book_id = ? AND deleted_at IS NULL',
            [$params['entry_id'], $book['id']]
        );
        if (!$entry) redirect($back, ['error' => 'Entry not found.']);

        $in = $this->readInput($book, $back, $entry);

        // Preserve existing attachments; optionally drop some / append a new one
        $attachments = json_decode($entry['attachments'] ?? 'null', true) ?: [];

        $remove = array_map('strval', (array)($_POST['remove_attachments'] ?? []));
        if ($remove) {
            foreach ($attachments as $a) {
                if (in_array($a, $remove, true)) $this->deleteUpload((string)$a);
            }
            $attachments = array_values(array_filter($attachments, fn($a) => !in_array($a, $remove, true)));
        }

        if (!empty($_FILES['attachment']['name']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
            $uploaded = $this->handleUpload($_FILES['attachment']);
            if ($uploaded) $attachments[] = $uploaded;
            else flash('warning', 'Entry saved but the attachment could not be uploaded. Check file type and size (max 10MB).');
        }

        Database::run(
            'UPDATE entries SET type=?, title=?, description=?, amount=?, entry_date=?, entry_time=?, contact_id=?, attachments=?, updated_at=? WHERE id=?',
            [$in['type'], $in['title'], $in['desc'] ?: null, $in['amount'], $in['date'], $in['time'], $in['contact_id'],
             $attachments ? json_encode(array_values($attachments)) : null, now(), $entry['id']]
        );

        redirect($back, ['success' => 'Entry updated.']);
    }

    // =========================================================================
    // ADD ENTRY  →  POST /books/{id}/entries/add
    // =========================================================================
    public function store(array $params): void
    {
        if (guest()) redirect('/login');
        csrf_verify();

        $book = $this->getBookOrFail($params['id']);
        $back = '/books/' . $book['id'];
        $in   = $this->readInput($book, $back, null);

        $attachments = [];
        if (!empty($_FILES['attachment']['name']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
            $uploaded = $this->handleUpload($_FILES['attachment']);
            if ($uploaded) $attachments[] = $uploaded;
            else flash('warning', 'Entry saved but the attachment could not be uploaded. Check file type and size (max 10MB).');
        }

        Database::run(
            'INSERT INTO entries (book_id, contact_id, type, title, description, amount, entry_date, entry_time, attachments, created_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$book['id'], $in['contact_id'], $in['type'], $in['title'], $in['desc'] ?: null, $in['amount'], $in['date'], $in['time'],
             $attachments ? json_encode($attachments) : null, auth()['id'], now()]
        );

        redirect($back, ['success' => 'Entry added.']);
    }

    // =========================================================================
    // DELETE ENTRY  →  POST /books/{id}/entries/{entry_id}/delete
    // =========================================================================
    public function delete(array $params): void
    {
        if (guest()) redirect('/login');
        csrf_verify();

        $book = $this->getBookOrFail($params['id']);

        $entry = Database::row(
            'SELECT * FROM entries WHERE id = ? AND book_id = ? AND deleted_at IS NULL',
            [$params['entry_id'], $book['id']]
        );
        if (!$entry) redirect('/books/' . $book['id'], ['error' => 'Entry not found.']);

        Database::run('UPDATE entries SET deleted_at = ? WHERE id = ?', [now(), $entry['id']]);

        redirect('/books/' . $book['id'], ['success' => 'Entry deleted.']);
    }

    // =========================================================================
    // PRIVATE: validated form input (shared by add + edit)
    // =========================================================================
    private function readInput(array $book, string $back, ?array $existing): array
    {
        $type   = $_POST['type'] ?? ($existing['type'] ?? 'in');
        $title  = trim((string)($_POST['title'] ?? ''));
        $amount = round((float)($_POST['amount'] ?? 0), 2);
        $desc   = trim((string)($_POST['description'] ?? ''));

        if ($title === '')                            redirect($back, ['error' => 'Please enter a title for the entry.']);
        if ($amount <= 0)                             redirect($back, ['error' => 'Amount must be greater than zero.']);
        if ($amount > self::MAX_AMOUNT)               redirect($back, ['error' => 'That amount is too large.']);
        if (!in_array($type, ['in', 'out'], true))    redirect($back, ['error' => 'Invalid entry type.']);

        // A malformed date used to reach MariaDB and crash the request with a 500 (nothing saved, form lost).
        $date = valid_date($_POST['date'] ?? null, $existing['entry_date'] ?? date('Y-m-d'));
        $time = array_key_exists('time', $_POST) ? valid_time($_POST['time']) : valid_time($existing['entry_time'] ?? null);

        // The contact must belong to THIS book (a posted id from another book is ignored).
        $contactId = !empty($_POST['contact_id']) ? (int)$_POST['contact_id'] : null;
        if ($contactId && !Database::row('SELECT id FROM contacts WHERE id=? AND book_id=? AND deleted_at IS NULL', [$contactId, $book['id']])) {
            $contactId = null;
        }

        return ['type' => $type, 'title' => mb_substr($title, 0, 255), 'amount' => $amount, 'desc' => $desc,
                'date' => $date, 'time' => $time, 'contact_id' => $contactId];
    }

    // =========================================================================
    // PRIVATE: upload handling
    // =========================================================================
    private function handleUpload(array $file): ?string
    {
        $allowed  = config('upload.allowed');
        $maxSize  = config('upload.max_size');
        $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowed, true)) return null;
        if ($file['size'] > $maxSize)        return null;
        if ($file['error'] !== 0)            return null;

        $uploadPath = config('upload.path') . '/attachments';
        if (!is_dir($uploadPath)) mkdir($uploadPath, 0755, true);

        $filename = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $dest     = $uploadPath . '/' . $filename;

        return move_uploaded_file($file['tmp_name'], $dest) ? 'attachments/' . $filename : null;
    }

    /** Remove an attachment file we stored (only ever inside uploads/attachments/). */
    private function deleteUpload(string $rel): void
    {
        if (!preg_match('#^attachments/[A-Za-z0-9_.-]+$#', $rel)) return;
        $path = config('upload.path') . '/' . $rel;
        if (is_file($path)) @unlink($path);
    }

    // =========================================================================
    // PRIVATE: entries live in personal books and are managed by their owner
    // =========================================================================
    private function getBookOrFail(string $id): array
    {
        $book = book_for_user($id);

        if (!$book) {
            http_response_code(404);
            require BASE_PATH . '/views/errors/404.php';
            exit;
        }
        // Business books keep their money in invoices/expenses/funds — never in free-form entries.
        if ($book['type'] !== 'personal') {
            redirect('/books/' . $book['id'], ['error' => 'Entries are only available in personal books.']);
        }
        if (empty(book_member_perms($book)['__owner__'])) abort_403();
        return $book;
    }
}
