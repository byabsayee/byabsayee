<?php

namespace App\Controllers;

use App\Helpers\Database;
use App\Helpers\Mailer;

class NotificationController
{
    // =========================================================================
    // GLOBAL (main dashboard) — all notifications for user
    // GET /notifications
    // =========================================================================
    public function globalIndex(array $params): void
    {
        if (guest()) { echo json_encode([]); exit; }

        $notifs = [];
        try {
            $notifs = Database::query(
                'SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 50',
                [auth()['id']]
            );
        } catch (\Throwable $e) {}

        // Mark all as read
        try {
            Database::run(
                'UPDATE notifications SET read_at=? WHERE user_id=? AND read_at IS NULL',
                [now(), auth()['id']]
            );
        } catch (\Throwable $e) {}

        header('Content-Type: application/json');
        echo json_encode(array_map(fn($n) => self::formatNotif($n), $notifs));
        exit;
    }

    // =========================================================================
    // BOOK notifications — GET /books/{id}/notifications
    // Returns: this book's notifications + global/invitation notifications
    // =========================================================================
    public function bookIndex(array $params): void
    {
        if (guest()) { echo json_encode([]); exit; }

        $book   = $this->getBookOrFail($params['id']);
        $notifs = [];
        try {
            // Return all notifications for this user:
            // - notifications for this specific book (book_id = current)
            // - global notifications (book_id IS NULL)
            // - invitations from other books (type = 'invitation' with different book_id)
            $notifs = Database::query(
                'SELECT * FROM notifications
                 WHERE user_id=?
                   AND (book_id=? OR book_id IS NULL OR type=\'invitation\')
                 ORDER BY created_at DESC LIMIT 60',
                [auth()['id'], $book['id']]
            );
        } catch (\Throwable $e) {}

        // Mark this book's notifications as read
        try {
            Database::run(
                'UPDATE notifications SET read_at=? WHERE (book_id=? OR book_id IS NULL) AND user_id=? AND read_at IS NULL',
                [now(), $book['id'], auth()['id']]
            );
        } catch (\Throwable $e) {}

        header('Content-Type: application/json');
        echo json_encode(array_map(fn($n) => self::formatNotif($n, $book['id']), $notifs));
        exit;
    }

    // =========================================================================
    // UNREAD COUNT — GET /notifications/count
    // =========================================================================
    public function unreadCount(array $params): void
    {
        if (guest()) { echo json_encode(['count' => 0]); exit; }

        $count = 0;
        try {
            $row = Database::row(
                'SELECT COUNT(*) AS c FROM notifications WHERE user_id=? AND read_at IS NULL',
                [auth()['id']]
            );
            $count = (int)($row['c'] ?? 0);
        } catch (\Throwable $e) {}

        header('Content-Type: application/json');
        echo json_encode(['count' => $count]);
        exit;
    }

    // =========================================================================
    // SEND NOTIFICATION TO EMPLOYEES — POST /books/{id}/notifications/send
    // =========================================================================
    public function send(array $params): void
    {
        if (guest()) redirect('/login');
        csrf_verify();

        $book = $this->getBookOrFail($params['id']);
        $this->requireOwnerOrPermission($book, 'employees', 'invite');

        $title     = trim($_POST['title']     ?? '');
        $body      = trim($_POST['body']      ?? '');
        $actionUrl = trim($_POST['action_url'] ?? '') ?: null;
        $sendEmail = !empty($_POST['send_email']);

        if (!$title) redirect('/books/'.$book['id'].'/notifications/send', ['error' => 'Notification title is required.']);

        // Who to send to
        $targets = [];
        $mode    = $_POST['target_mode'] ?? 'selected';

        if ($mode === 'all') {
            // All active members
            try {
                $targets = Database::query(
                    'SELECT bm.user_id, u.email, u.name
                     FROM book_members bm JOIN users u ON u.id=bm.user_id
                     WHERE bm.book_id=? AND bm.status="active"',
                    [$book['id']]
                );
            } catch (\Throwable $e) {}

        } elseif ($mode === 'designation') {
            $desigName = trim($_POST['designation_name'] ?? '');
            try {
                $targets = Database::query(
                    'SELECT bm.user_id, u.email, u.name
                     FROM book_members bm
                     JOIN users u ON u.id=bm.user_id
                     JOIN employees e ON e.user_id=bm.user_id AND e.book_id=bm.book_id
                     WHERE bm.book_id=? AND bm.status="active" AND e.designation_name=?',
                    [$book['id'], $desigName]
                );
            } catch (\Throwable $e) {}

        } else {
            // Selected employees
            $userIds = array_map('intval', (array)($_POST['user_ids'] ?? []));
            foreach ($userIds as $uid) {
                if (!$uid) continue;
                $u = Database::row('SELECT id AS user_id, email, name FROM users WHERE id=?', [$uid]);
                if ($u) $targets[] = $u;
            }
        }

        if (empty($targets)) {
            redirect('/books/'.$book['id'].'/notifications/send', ['error' => 'No recipients selected.']);
        }

        $bookDetails = Database::row('SELECT business_name FROM book_business_details WHERE book_id=?', [$book['id']]);
        $bookName    = $bookDetails['business_name'] ?? $book['name'];
        $appUrl      = getenv('APP_URL') ?: '';
        $appName     = getenv('APP_NAME') ?: 'Byabsayee';

        $sent = 0;
        foreach ($targets as $t) {
            // In-app notification
            try {
                Database::run(
                    'INSERT INTO notifications (user_id, book_id, type, title, body, action_url, created_at)
                     VALUES (?,?,?,?,?,?,?)',
                    [$t['user_id'], $book['id'], 'message', $title, $body, $actionUrl, now()]
                );
                $sent++;
            } catch (\Throwable $e) {}

            // Email
            if ($sendEmail && !empty($t['email'])) {
                try {
                    $html = Mailer::render('notification', [
                        'appName'     => $appName,
                        'appUrl'      => $appUrl,
                        'title'       => $bookName . ': ' . $title,
                        'body'        => $body,
                        'actionUrl'   => $actionUrl ? $appUrl . $actionUrl : null,
                        'actionLabel' => $actionUrl ? 'View' : null,
                    ]);
                    Mailer::send(
                        ['email' => $t['email'], 'name' => $t['name'] ?? ''],
                        '[' . $bookName . '] ' . $title,
                        $html
                    );
                } catch (\Throwable $e) {}
            }
        }

        redirect('/books/'.$book['id'],
            ['success' => "Notification sent to {$sent} employee" . ($sent !== 1 ? 's' : '') . "."]
        );
    }

    // =========================================================================
    // SEND NOTIFICATION PAGE — GET /books/{id}/notifications/send
    // =========================================================================
    public function sendPage(array $params): void
    {
        if (guest()) redirect('/login');

        $book = $this->getBookOrFail($params['id']);
        $this->requireOwnerOrPermission($book, 'employees', 'invite');

        $employees = [];
        try {
            $employees = Database::query(
                'SELECT e.*, bm.status AS member_status, u.email AS user_email
                 FROM employees e
                 LEFT JOIN book_members bm ON bm.book_id=e.book_id AND bm.user_id=e.user_id
                 LEFT JOIN users u ON u.id=e.user_id
                 WHERE e.book_id=? AND e.deleted_at IS NULL AND e.user_id IS NOT NULL
                 ORDER BY e.designation_name, e.name',
                [$book['id']]
            );
        } catch (\Throwable $e) {}

        $designations = [];
        try {
            $designations = Database::query(
                'SELECT DISTINCT designation_name FROM employees
                 WHERE book_id=? AND designation_name IS NOT NULL AND deleted_at IS NULL',
                [$book['id']]
            );
        } catch (\Throwable $e) {}

        $pageTitle = 'Send Notification — ' . e($book['name']);

        ob_start();
        require BASE_PATH . '/views/business/notifications/send.php';
        $content = ob_get_clean();
        require BASE_PATH . '/views/partials/layout.php';
    }

    // =========================================================================
    // HELPERS
    // =========================================================================
    public static function formatNotif(array $n, ?int $contextBookId = null): array
    {
        return [
            'id'              => $n['id'],
            'type'            => $n['type'],
            'title'           => $n['title'],
            'body'            => $n['body'],
            'action_url'      => $n['action_url'],
            'created_at'      => $n['created_at'],
            'read'            => !is_null($n['read_at']),
            'book_id'         => $n['book_id'],
            'is_book_notif'   => $contextBookId && (int)$n['book_id'] === $contextBookId,
        ];
    }

    /**
     * Create a notification for a user (static helper for other controllers)
     */
    public static function create(
        int    $userId,
        string $title,
        string $body    = '',
        string $type    = 'info',
        ?int   $bookId  = null,
        ?string $actionUrl = null
    ): void {
        try {
            Database::run(
                'INSERT INTO notifications (user_id, book_id, type, title, body, action_url, created_at)
                 VALUES (?,?,?,?,?,?,?)',
                [$userId, $bookId, $type, $title, $body, $actionUrl, now()]
            );
        } catch (\Throwable $e) {
            // Silent — notification failure should never crash the app
        }
    }

    private function getBookOrFail(string $id): array
    {
        try {
            $book = Database::row(
                'SELECT * FROM books WHERE id=? AND deleted_at IS NULL AND (user_id=? OR EXISTS(
                    SELECT 1 FROM book_members WHERE book_id=books.id AND user_id=? AND status="active"
                ))',
                [$id, auth()['id'], auth()['id']]
            );
        } catch (\Throwable $e) {
            $book = Database::row(
                'SELECT * FROM books WHERE id=? AND user_id=? AND deleted_at IS NULL',
                [$id, auth()['id']]
            );
        }
        if (!$book) { http_response_code(404); require BASE_PATH.'/views/errors/404.php'; exit; }
        return $book;
    }

    private function requireOwnerOrPermission(array $book, string $module, string $action): void
    {
        if ($book['user_id'] === auth()['id']) return;
        try {
            $member = Database::row(
                'SELECT permissions FROM book_members WHERE book_id=? AND user_id=? AND status="active"',
                [$book['id'], auth()['id']]
            );
            if ($member) {
                $perms = json_decode($member['permissions'], true) ?? [];
                if (!empty($perms[$module][$action])) return;
            }
        } catch (\Throwable $e) {}
        redirect('/books/'.$book['id'], ['error' => 'You do not have permission to do this.']);
    }
}