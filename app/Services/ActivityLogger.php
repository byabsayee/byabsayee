<?php
namespace App\Services;

use App\Helpers\Database;

/**
 * ActivityLogger
 *
 * Records every significant action in the `activity_log` table.
 * Fails silently so it never breaks the main application.
 *
 * Usage:
 *   ActivityLogger::write($bookId, auth()['id'], 'invoice.created', 'Invoice', $id, 'Invoice #000123 created');
 */
class ActivityLogger
{
    // ─────────────────────────────────────────────────────────────────────────
    //  Write an activity log entry
    // ─────────────────────────────────────────────────────────────────────────
    public static function write(
        ?int    $bookId,
        ?int    $userId,
        string  $action,
        ?string $subjectType = null,
        ?int    $subjectId   = null,
        string  $description = '',
        ?array  $oldData     = null,
        ?array  $newData     = null
    ): void {
        try {
            Database::run(
                'INSERT INTO activity_log
                    (book_id, user_id, action, subject_type, subject_id,
                     description, old_data, new_data, ip_address, user_agent, created_at)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?)',
                [
                    $bookId,
                    $userId,
                    $action,
                    $subjectType,
                    $subjectId,
                    $description,
                    $oldData !== null ? json_encode($oldData, JSON_UNESCAPED_UNICODE) : null,
                    $newData !== null ? json_encode($newData, JSON_UNESCAPED_UNICODE) : null,
                    self::resolveIp(),
                    substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
                    now(),
                ]
            );
        } catch (\Throwable $e) {
            error_log('[ActivityLogger] ' . $e->getMessage());
        }
    }

    // Convenient instance-style call (some controllers may use this)
    public function log(
        ?int    $bookId,
        ?int    $userId,
        string  $action,
        ?string $subjectType = null,
        ?int    $subjectId   = null,
        string  $description = ''
    ): void {
        self::write($bookId, $userId, $action, $subjectType, $subjectId, $description);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Fetch recent log for the activity feed (book dashboard)
    // ─────────────────────────────────────────────────────────────────────────
    public static function recent(int $bookId, int $limit = 20): array
    {
        try {
            $rows = Database::query(
                "SELECT al.*, u.name AS user_name
                 FROM activity_log al
                 LEFT JOIN users u ON u.id = al.user_id
                 WHERE al.book_id = ?
                 ORDER BY al.created_at DESC
                 LIMIT ?",
                [$bookId, $limit]
            );
            return array_map([self::class, 'decorate'], $rows);
        } catch (\Throwable $e) {
            return [];
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Map action string → Font Awesome icon + colour class
    // ─────────────────────────────────────────────────────────────────────────
    private static function decorate(array $row): array
    {
        $map = [
            'invoice.created'    => ['fa-file-invoice',        'var(--blue)'],
            'invoice.updated'    => ['fa-file-pen',            'var(--amber)'],
            'invoice.deleted'    => ['fa-file-circle-xmark',   'var(--red)'],
            'invoice.paid'       => ['fa-circle-check',        'var(--green)'],
            'invoice.payment'    => ['fa-money-bill',          'var(--green)'],
            'due.created'        => ['fa-hand-holding-dollar', 'var(--amber)'],
            'due.payment'        => ['fa-money-bill-wave',     'var(--green)'],
            'due.updated'        => ['fa-pen',                 'var(--amber)'],
            'due.cancelled'      => ['fa-ban',                 'var(--text-muted)'],
            'due.deleted'        => ['fa-trash',               'var(--red)'],
            'debt.created'       => ['fa-file-circle-minus',   'var(--red)'],
            'debt.payment'       => ['fa-money-bill-wave',     'var(--green)'],
            'debt.updated'       => ['fa-pen',                 'var(--amber)'],
            'debt.cancelled'     => ['fa-ban',                 'var(--text-muted)'],
            'debt.deleted'       => ['fa-trash',               'var(--red)'],
            'fund.in'            => ['fa-circle-arrow-down',   'var(--green)'],
            'fund.out'           => ['fa-circle-arrow-up',     'var(--red)'],
            'fund.updated'       => ['fa-pen',                 'var(--amber)'],
            'fund.deleted'       => ['fa-trash',               'var(--red)'],
            'expense.created'    => ['fa-receipt',             'var(--amber)'],
            'expense.updated'    => ['fa-pen',                 'var(--amber)'],
            'expense.deleted'    => ['fa-trash',               'var(--red)'],
            'customer.created'   => ['fa-user-plus',           'var(--blue)'],
            'customer.updated'   => ['fa-user-pen',            'var(--amber)'],
            'customer.deleted'   => ['fa-user-xmark',          'var(--red)'],
            'supplier.created'   => ['fa-truck',               'var(--blue)'],
            'supplier.updated'   => ['fa-truck',               'var(--amber)'],
            'supplier.deleted'   => ['fa-truck',               'var(--red)'],
            'product.created'    => ['fa-box',                 'var(--blue)'],
            'product.updated'    => ['fa-box-open',            'var(--amber)'],
            'product.deleted'    => ['fa-box-xmark',           'var(--red)'],
            'stock.adjusted'     => ['fa-warehouse',           '#8b5cf6'],
            'return.created'     => ['fa-rotate-left',         'var(--amber)'],
            'return.deleted'     => ['fa-trash',               'var(--red)'],
            'coupon.created'     => ['fa-ticket',              '#d4ec69'],
            'coupon.updated'     => ['fa-ticket',              'var(--amber)'],
            'coupon.deleted'     => ['fa-trash',               'var(--red)'],
            'employee.created'   => ['fa-id-badge',            'var(--blue)'],
            'employee.updated'   => ['fa-id-badge',            'var(--amber)'],
            'employee.deleted'   => ['fa-id-badge',            'var(--red)'],
            'privilege.created'  => ['fa-star',                'var(--green)'],
            'privilege.updated'  => ['fa-star',                'var(--amber)'],
            'privilege.deleted'  => ['fa-star',                'var(--red)'],
            'book.settings'      => ['fa-sliders',             'var(--brand)'],
            'book.created'       => ['fa-book',                'var(--green)'],
            'delivery.created'   => ['fa-truck-fast',          'var(--blue)'],
            'delivery.updated'   => ['fa-truck',               'var(--amber)'],
            'delivery.completed' => ['fa-check',               'var(--green)'],
            'payment.recorded'   => ['fa-money-bill',          'var(--green)'],
            'auth.login'         => ['fa-right-to-bracket',    'var(--green)'],
            'auth.logout'        => ['fa-right-from-bracket',  'var(--text-muted)'],
            'notification.sent'  => ['fa-bell',                'var(--blue)'],
            'notification.read'  => ['fa-bell-slash',          'var(--text-muted)'],
        ];

        [$icon, $color] = $map[$row['action'] ?? ''] ?? ['fa-circle-dot', 'var(--text-muted)'];
        $row['icon']       = $icon;
        $row['icon_color'] = $color;

        if (isset($row['old_data']) && is_string($row['old_data'])) {
            $row['old_data'] = json_decode($row['old_data'], true);
        }
        if (isset($row['new_data']) && is_string($row['new_data'])) {
            $row['new_data'] = json_decode($row['new_data'], true);
        }
        return $row;
    }

    private static function resolveIp(): string
    {
        foreach (['HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $k) {
            if (!empty($_SERVER[$k])) {
                return trim(explode(',', $_SERVER[$k])[0]);
            }
        }
        return '0.0.0.0';
    }
}
