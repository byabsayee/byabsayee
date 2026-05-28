<?php
namespace App\Controllers;
use App\Helpers\Database;
use App\Services\ActivityLogger;

class BookLogsController
{
    public function index(array $params): void
    {
        if (guest()) redirect('/login');
        $book = $this->getBookOrFail($params['id']);
        if (!book_can($book, 'logs', 'view')) abort_403();

        $page    = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 50;
        $offset  = ($page - 1) * $perPage;
        $filter  = $_GET['filter'] ?? '';

        // Build action→module map so members only see logs for modules they can access
        $actionModuleMap = [
            'invoice'     => 'invoices',
            'due'         => 'dues',
            'debt'        => 'debts',
            'fund'        => 'funds',
            'expense'     => 'expenses',
            'customer'    => 'customers',
            'supplier'    => 'suppliers',
            'product'     => 'products',
            'stock'       => 'products',
            'return'      => 'returns',
            'coupon'      => 'coupons',
            'employee'    => 'employees',
            'member'      => 'employees',
            'privilege'   => 'employees',
            'book'        => 'book_settings',
            'payment'     => 'invoices',
            'auth'        => null,             // always visible
            'notification'=> null,             // always visible
        ];

        $perms = book_member_perms($book);
        $isOwner = !empty($perms['__owner__']);

        try {
            $sql = "SELECT al.*, u.name AS user_name
                    FROM activity_log al
                    LEFT JOIN users u ON u.id = al.user_id
                    WHERE al.book_id = ?";
            $params_q = [$book['id']];

            // For non-owners: restrict to actions in permitted modules
            if (!$isOwner) {
                $allowedPrefixes = [];
                foreach ($actionModuleMap as $prefix => $module) {
                    if ($module === null || !empty($perms[$module]['view'])) {
                        $allowedPrefixes[] = $prefix;
                    }
                }
                if (!empty($allowedPrefixes)) {
                    $likeParts = array_map(fn($p) => "al.action LIKE ?", $allowedPrefixes);
                    $sql .= ' AND (' . implode(' OR ', $likeParts) . ')';
                    foreach ($allowedPrefixes as $p) $params_q[] = $p . '%';
                } else {
                    $sql .= ' AND 1=0'; // no permissions at all
                }
            }

            if ($filter) {
                $sql .= " AND al.action LIKE ?";
                $params_q[] = $filter . '%';
            }
            $sql .= " ORDER BY al.created_at DESC LIMIT ? OFFSET ?";
            $params_q[] = $perPage;
            $params_q[] = $offset;

            $logs = Database::query($sql, $params_q);

            // Build matching count query
            $countSql    = "SELECT COUNT(*) AS n FROM activity_log al WHERE al.book_id=?";
            $countParams = [$book['id']];
            if (!$isOwner) {
                if (!empty($allowedPrefixes)) {
                    $likeParts2 = array_map(fn($p) => "al.action LIKE ?", $allowedPrefixes);
                    $countSql .= ' AND (' . implode(' OR ', $likeParts2) . ')';
                    foreach ($allowedPrefixes as $p) $countParams[] = $p . '%';
                } else {
                    $countSql .= ' AND 1=0';
                }
            }
            if ($filter) { $countSql .= ' AND al.action LIKE ?'; $countParams[] = $filter . '%'; }
            $totalRow = Database::row($countSql, $countParams);
            $total = (int)($totalRow['n'] ?? 0);
            $totalPages = max(1, (int)ceil($total / $perPage));
        } catch (\Throwable $e) {
            $logs = [];
            $total = 0;
            $totalPages = 1;
        }

        // Decorate logs with icons
        $logs = array_map(function($row) use ($book) {
            $map = [
                'invoice.created'    => ['fa-file-invoice',        'var(--blue)',   'Invoice Created'],
                'invoice.updated'    => ['fa-file-pen',            'var(--amber)',  'Invoice Updated'],
                'invoice.deleted'    => ['fa-file-circle-xmark',   'var(--red)',    'Invoice Deleted'],
                'invoice.paid'       => ['fa-circle-check',        'var(--green)',  'Invoice Paid'],
                'invoice.payment'    => ['fa-money-bill',          'var(--green)',  'Payment Recorded'],
                'due.created'        => ['fa-hand-holding-dollar', 'var(--amber)',  'Due Created'],
                'due.payment'        => ['fa-money-bill-wave',     'var(--green)',  'Due Payment'],
                'due.cancelled'      => ['fa-ban',                 'var(--text-muted)', 'Due Cancelled'],
                'due.updated'        => ['fa-pen',                 'var(--amber)',  'Due Updated'],
                'due.deleted'        => ['fa-trash',               'var(--red)',    'Due Deleted'],
                'debt.created'       => ['fa-file-circle-minus',   'var(--red)',    'Debt Created'],
                'debt.payment'       => ['fa-money-bill-wave',     'var(--green)',  'Debt Payment'],
                'debt.updated'       => ['fa-pen',                 'var(--amber)',  'Debt Updated'],
                'debt.deleted'       => ['fa-trash',               'var(--red)',    'Debt Deleted'],
                'debt.cancelled'     => ['fa-ban',                 'var(--text-muted)', 'Debt Cancelled'],
                'fund.in'            => ['fa-circle-arrow-down',   'var(--green)',  'Fund Received'],
                'fund.out'           => ['fa-circle-arrow-up',     'var(--red)',    'Fund Withdrawn'],
                'fund.updated'       => ['fa-pen',                 'var(--amber)',  'Fund Updated'],
                'fund.deleted'       => ['fa-trash',               'var(--red)',    'Fund Deleted'],
                'expense.created'    => ['fa-receipt',             'var(--amber)',  'Expense Added'],
                'expense.updated'    => ['fa-pen',                 'var(--amber)',  'Expense Updated'],
                'expense.deleted'    => ['fa-trash',               'var(--red)',    'Expense Deleted'],
                'customer.created'   => ['fa-user-plus',           'var(--blue)',   'Customer Added'],
                'customer.updated'   => ['fa-user-pen',            'var(--amber)',  'Customer Updated'],
                'customer.deleted'   => ['fa-user-xmark',          'var(--red)',    'Customer Deleted'],
                'supplier.created'   => ['fa-truck',               'var(--blue)',   'Supplier Added'],
                'supplier.updated'   => ['fa-truck',               'var(--amber)',  'Supplier Updated'],
                'supplier.deleted'   => ['fa-truck',               'var(--red)',    'Supplier Deleted'],
                'product.created'    => ['fa-box',                 'var(--blue)',   'Product Added'],
                'product.updated'    => ['fa-box-open',            'var(--amber)',  'Product Updated'],
                'product.deleted'    => ['fa-box-xmark',           'var(--red)',    'Product Deleted'],
                'stock.adjusted'     => ['fa-warehouse',           '#8b5cf6',      'Stock Adjusted'],
                'return.created'     => ['fa-rotate-left',         'var(--amber)',  'Return Created'],
                'return.deleted'     => ['fa-trash',               'var(--red)',    'Return Deleted'],
                'coupon.created'     => ['fa-ticket',              '#d4ec69',      'Coupon Created'],
                'coupon.updated'     => ['fa-ticket',              'var(--amber)',  'Coupon Updated'],
                'coupon.deleted'     => ['fa-trash',               'var(--red)',    'Coupon Deleted'],
                'employee.created'   => ['fa-id-badge',            'var(--blue)',   'Employee Added'],
                'employee.updated'   => ['fa-id-badge',            'var(--amber)',  'Employee Updated'],
                'employee.deleted'   => ['fa-id-badge',            'var(--red)',    'Employee Removed'],
                'privilege.created'  => ['fa-star',                'var(--green)',  'Privilege Created'],
                'privilege.updated'  => ['fa-star',                'var(--amber)',  'Privilege Updated'],
                'privilege.deleted'  => ['fa-star',                'var(--red)',    'Privilege Deleted'],
                'book.settings'      => ['fa-sliders',             'var(--brand)',  'Settings Updated'],
                'book.created'       => ['fa-book',                'var(--green)',  'Book Created'],
                'payment.recorded'   => ['fa-money-bill',          'var(--green)',  'Payment Recorded'],
                'auth.login'         => ['fa-right-to-bracket',    'var(--green)',  'Logged In'],
                'auth.logout'        => ['fa-right-from-bracket',  'var(--text-muted)', 'Logged Out'],
                'notification.sent'  => ['fa-bell',                'var(--blue)',   'Notification Sent'],
                'member.joined'      => ['fa-user-check',         'var(--green)',  'Member Joined'],
                'member.invited'     => ['fa-envelope',           'var(--blue)',   'Invitation Sent'],
                'employee.terminated'=> ['fa-user-slash',         'var(--red)',    'Employee Terminated'],
                'employee.reinstated'=> ['fa-user-check',         'var(--green)',  'Employee Reinstated'],
            ];
            $action = $row['action'] ?? '';
            [$icon, $color, $label] = $map[$action] ?? ['fa-circle-dot', 'var(--text-muted)', ucfirst(str_replace('.', ' ', $action))];
            $row['icon']       = $icon;
            $row['icon_color'] = $color;
            $row['action_label'] = $label;

            // Build a click-through URL for log entries with a subject
            $bookId    = (int)($row['book_id'] ?? 0);
            $subjectId = (int)($row['subject_id'] ?? 0);
            $prefix    = explode('.', $action)[0] ?? '';
            $row['link_url'] = null;
            if ($bookId) {
                // Modules with individual show pages
                $showRoutes = [
                    'invoice'  => "/books/$bookId/invoices/$subjectId",
                    'customer' => "/books/$bookId/customers/$subjectId",
                    'supplier' => "/books/$bookId/suppliers/$subjectId",
                    'employee' => "/books/$bookId/employees/$subjectId",
                    'member'   => "/books/$bookId/employees/$subjectId",
                    'return'   => "/books/$bookId/returns/$subjectId",
                ];
                // Modules that only have list pages
                $listRoutes = [
                    'due'       => "/books/$bookId/dues",
                    'debt'      => "/books/$bookId/debts",
                    'fund'      => "/books/$bookId/funds",
                    'expense'   => "/books/$bookId/expenses",
                    'product'   => "/books/$bookId/products",
                    'stock'     => "/books/$bookId/products",
                    'coupon'    => "/books/$bookId/coupons",
                    'privilege' => "/books/$bookId/privileges",
                    'payment'   => "/books/$bookId/invoices",
                    'book'      => "/books/$bookId/edit",
                ];
                if ($subjectId && isset($showRoutes[$prefix])) {
                    $row['link_url'] = $showRoutes[$prefix];
                } elseif (isset($listRoutes[$prefix])) {
                    $row['link_url'] = $listRoutes[$prefix];
                }
            }

            // Parse JSON fields
            if (isset($row['old_data']) && is_string($row['old_data'])) {
                $row['old_data'] = json_decode($row['old_data'], true);
            }
            if (isset($row['new_data']) && is_string($row['new_data'])) {
                $row['new_data'] = json_decode($row['new_data'], true);
            }
            return $row;
        }, $logs);

        // Unique action types for filter
        $actionTypes = [];
        try {
            $actionTypes = Database::query(
                "SELECT DISTINCT action FROM activity_log WHERE book_id=? ORDER BY action",
                [$book['id']]
            );
        } catch (\Throwable $e) {}

        require BASE_PATH . '/views/books/logs.php';
    }

        private function getBookOrFail(string $id): array
    {
        $book = book_for_user($id);
        if (!$book) { http_response_code(404); require BASE_PATH.'/views/errors/404.php'; exit; }
        return $book;
    }
}
