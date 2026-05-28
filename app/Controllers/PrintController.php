<?php
// =============================================================================
// app/Controllers/PrintController.php — PDF Print Reports for All Categories
// =============================================================================
// Route: GET /books/{id}/print/{category}?mode=days|date|month|year&value=...
//
// mode=days   value=30        → last 30 days, grouped by day
// mode=date   value=2025-01-15 → single day, all individual entries
// mode=month  value=2025-01    → whole month, grouped by day
// mode=year   value=2025       → whole year, grouped by month
// =============================================================================

namespace App\Controllers;

use App\Helpers\Database;

class PrintController
{
    // Valid categories this controller handles
    private const CATEGORIES = [
        'invoices', 'products', 'funds', 'expenses', 'dues', 'debts',
        'customers', 'suppliers', 'employees', 'contacts', 'coupons',
        'returns', 'privileges', 'reports',
    ];

    // =========================================================================
    // ENTRY POINT
    // =========================================================================
    public function generate(array $params): void
    {
        if (guest()) { http_response_code(403); echo 'Unauthorized'; exit; }

        $book     = $this->getBookOrFail($params['id']);
        $category = $params['category'] ?? '';

        if (!in_array($category, self::CATEGORIES, true)) {
            http_response_code(404); echo 'Unknown category.'; exit;
        }

        // ── Business details ──────────────────────────────────────────────
        $details = [];
        try { $details = Database::row('SELECT * FROM book_business_details WHERE book_id=?', [$book['id']]) ?: []; }
        catch (\Throwable $e) { $details = []; }

        $sym = '৳';
        try {
            $cur = Database::row('SELECT symbol FROM book_currencies WHERE book_id=? AND is_default=1', [$book['id']]);
            if ($cur && $cur['symbol']) {
                // Decode any HTML entities (e.g. &#2547; → ৳) so mPDF renders the raw character
                $sym = html_entity_decode($cur['symbol'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
        } catch (\Throwable $e) {}

        // ── Printer employee code ─────────────────────────────────────────
        $printerEmpCode = null;
        try {
            $emp = Database::row(
                'SELECT emp_code FROM employees WHERE book_id=? AND user_id=? AND deleted_at IS NULL LIMIT 1',
                [$book['id'], auth()['id']]
            );
            if ($emp && !empty($emp['emp_code'])) $printerEmpCode = $emp['emp_code'];
        } catch (\Throwable $e) {}

        // ── Date range ────────────────────────────────────────────────────
        $mode  = $_GET['mode']  ?? 'month';
        $value = $_GET['value'] ?? date('Y-m');
        [$dateFrom, $dateTo, $periodLabel] = $this->getDateRange($mode, $value);

        // ── Fetch data ────────────────────────────────────────────────────
        [$columns, $rows, $title, $groupedRows] = $this->fetchData(
            $category, $book, $details, $sym, $mode, $dateFrom, $dateTo
        );

        // ── Generate PDF ──────────────────────────────────────────────────
        $this->renderPdf(
            $book, $details, $sym,
            $category, $title,
            $columns, $rows, $groupedRows,
            $mode, $periodLabel, $dateFrom, $dateTo, $printerEmpCode
        );
    }

    // =========================================================================
    // DATE RANGE CALCULATION
    // =========================================================================
    private function getDateRange(string $mode, string $value): array
    {
        switch ($mode) {
            case 'days':
                $n = max(1, (int)$value);
                $dateTo   = date('Y-m-d');
                $dateFrom = date('Y-m-d', strtotime("-{$n} days"));
                $label    = "Last {$n} Days (" . date('d M Y', strtotime($dateFrom)) . " – " . date('d M Y') . ")";
                break;
            case 'date':
                $dateFrom = $dateTo = date('Y-m-d', strtotime($value));
                $label    = date('l, d F Y', strtotime($dateFrom));
                break;
            case 'month':
                $dateFrom = date('Y-m-01', strtotime($value . '-01'));
                $dateTo   = date('Y-m-t',  strtotime($dateFrom));
                $label    = date('F Y',    strtotime($dateFrom));
                break;
            case 'year':
                $year     = (int)$value;
                $dateFrom = "{$year}-01-01";
                $dateTo   = "{$year}-12-31";
                $label    = (string)$year;
                break;
            case 'all':
                $dateFrom = '2000-01-01';
                $dateTo   = date('Y-m-d');
                $label    = 'All Time';
                break;
            default:
                $dateFrom = date('Y-m-01');
                $dateTo   = date('Y-m-t');
                $label    = date('F Y');
        }
        return [$dateFrom, $dateTo, $label];
    }

    // =========================================================================
    // DATA FETCHING — dispatches to per-category methods
    // =========================================================================
    private function fetchData(
        string $category, array $book, array $details, string $sym,
        string $mode, string $dateFrom, string $dateTo
    ): array {
        $method = 'fetch' . ucfirst($category);
        if (method_exists($this, $method)) {
            return $this->$method($book, $details, $sym, $mode, $dateFrom, $dateTo);
        }
        return [[], [], ucfirst($category), []];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // INVOICES
    // ─────────────────────────────────────────────────────────────────────────
    private function fetchInvoices(array $book, array $details, string $sym, string $mode, string $from, string $to): array
    {
        $title   = 'Invoice Report';
        $columns = ['Date', 'Invoice No', 'Type', 'Customer / Supplier', 'Subtotal', 'Discount', 'Total', 'Paid', 'Balance', 'Status'];

        $rows = Database::query(
            "SELECT i.date, i.invoice_no, i.type,
                    COALESCE(c.name, s.name, 'Walk-in') AS party,
                    i.subtotal, i.discount,
                    i.total, i.paid,
                    (i.total - i.paid) AS balance,
                    i.status
             FROM invoices i
             LEFT JOIN customers c ON c.id = i.customer_id
             LEFT JOIN suppliers s ON s.id = i.supplier_id
             WHERE i.book_id=? AND i.deleted_at IS NULL AND i.date BETWEEN ? AND ?
             ORDER BY i.date DESC, i.id DESC",
            [$book['id'], $from, $to]
        );

        $formatted = [];
        foreach ($rows as $r) {
            $formatted[] = [
                date('d M Y', strtotime($r['date'])),
                $r['invoice_no'],
                ucfirst($r['type']),
                $r['party'],
                $sym . number_format((float)$r['subtotal'], 2),
                $sym . number_format((float)$r['discount'], 2),
                $sym . number_format((float)$r['total'], 2),
                $sym . number_format((float)$r['paid'], 2),
                $sym . number_format((float)$r['balance'], 2),
                ucfirst($r['status']),
            ];
        }

        $grouped = $this->groupRows($rows, $mode, $sym,
            fn($r) => (float)$r['total'],
            fn($r) => (float)$r['paid'],
            fn($r) => (float)($r['total'] - $r['paid']),
            fn($r) => $r['date']
        );

        return [$columns, $formatted, $title, $grouped];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PRODUCTS
    // ─────────────────────────────────────────────────────────────────────────
    private function fetchProducts(array $book, array $details, string $sym, string $mode, string $from, string $to): array
    {
        $title   = 'Products Inventory Report';
        $columns = ['Product Name', 'Category', 'Unit', 'SKU / Code', 'Stock Qty', 'Buy Price', 'Sell Price', 'Stock Value', 'Status'];

        $rows = Database::query(
            "SELECT p.name, c.name AS category_name, p.unit, p.sku,
                    p.stock_qty, p.buy_price, p.sell_price,
                    (p.stock_qty * p.buy_price) AS stock_value,
                    p.low_stock_alert
             FROM products p
             LEFT JOIN categories c ON c.id = p.category_id
             WHERE p.book_id=? AND p.deleted_at IS NULL
             ORDER BY p.name",
            [$book['id']]
        );

        $formatted = [];
        foreach ($rows as $r) {
            $qty    = (int)$r['stock_qty'];
            $status = $qty <= 0 ? 'Out of Stock' : ($qty <= (int)$r['low_stock_alert'] ? 'Low Stock' : 'In Stock');
            $formatted[] = [
                $r['name'],
                $r['category_name'] ?? '—',
                $r['unit']          ?? '—',
                $r['sku']           ?? '—',
                number_format($qty),
                $sym . number_format((float)$r['buy_price'], 2),
                $sym . number_format((float)$r['sell_price'], 2),
                $sym . number_format((float)$r['stock_value'], 2),
                $status,
            ];
        }

        // Summary
        $totStockVal = array_sum(array_column($rows, 'stock_value'));
        $grouped = [
            ['label' => 'Summary', 'rows' => [
                ['Total Products', count($rows), '—', '—', '—', '—', '—', $sym . number_format($totStockVal, 2), '—']
            ]]
        ];

        return [$columns, $formatted, $title, $grouped];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // FUNDS
    // ─────────────────────────────────────────────────────────────────────────
    private function fetchFunds(array $book, array $details, string $sym, string $mode, string $from, string $to): array
    {
        $title   = 'Funds Report';
        $columns = ['Date', 'Title / Source', 'Type', 'Amount', 'Note'];

        $rows = Database::query(
            "SELECT fund_date AS date, COALESCE(title,'—') AS title, type, amount, note
             FROM funds WHERE book_id=? AND fund_date BETWEEN ? AND ?
             ORDER BY fund_date DESC, id DESC",
            [$book['id'], $from, $to]
        );

        $formatted = [];
        foreach ($rows as $r) {
            $formatted[] = [
                date('d M Y', strtotime($r['date'])),
                $r['title'],
                $r['type'] === 'in' ? 'Received' : 'Withdrawn',
                $sym . number_format((float)$r['amount'], 2),
                $r['note'] ?? '—',
            ];
        }

        $grouped = $this->groupRows($rows, $mode, $sym,
            fn($r) => $r['type'] === 'in'  ? (float)$r['amount'] : 0,
            fn($r) => $r['type'] === 'out' ? (float)$r['amount'] : 0,
            null,
            fn($r) => $r['date']
        );

        return [$columns, $formatted, $title, $grouped];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // EXPENSES
    // ─────────────────────────────────────────────────────────────────────────
    private function fetchExpenses(array $book, array $details, string $sym, string $mode, string $from, string $to): array
    {
        $title   = 'Expenses Report';
        $columns = ['Date', 'Title', 'Category', 'Paid To', 'Amount', 'Note'];

        $rows = Database::query(
            "SELECT e.expense_date AS date, e.title, ec.name AS category_name,
                    e.paid_to, e.amount, e.note
             FROM expenses e
             LEFT JOIN expense_categories ec ON ec.id = e.category_id
             WHERE e.book_id=? AND e.expense_date BETWEEN ? AND ?
             ORDER BY e.expense_date DESC, e.id DESC",
            [$book['id'], $from, $to]
        );

        $formatted = [];
        foreach ($rows as $r) {
            $formatted[] = [
                date('d M Y', strtotime($r['date'])),
                $r['title'],
                $r['category_name'] ?? 'General',
                $r['paid_to']       ?? '—',
                $sym . number_format((float)$r['amount'], 2),
                $r['note']          ?? '—',
            ];
        }

        $grouped = $this->groupRows($rows, $mode, $sym,
            null,
            fn($r) => (float)$r['amount'],
            null,
            fn($r) => $r['date']
        );

        return [$columns, $formatted, $title, $grouped];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // DUES
    // ─────────────────────────────────────────────────────────────────────────
    private function fetchDues(array $book, array $details, string $sym, string $mode, string $from, string $to): array
    {
        $title   = 'Dues Report';
        $columns = ['Due Date', 'Customer', 'Title', 'Total Amount', 'Paid', 'Balance', 'Status'];

        $rows = Database::query(
            "SELECT COALESCE(d.due_date, DATE(d.created_at)) AS date,
                    COALESCE(c.name,'Unknown') AS customer_name,
                    d.title, d.amount, d.paid_amount,
                    (d.amount - d.paid_amount) AS balance, d.status
             FROM dues d
             LEFT JOIN customers c ON c.id = d.customer_id
             WHERE d.book_id=? AND (d.due_date BETWEEN ? AND ? OR (d.due_date IS NULL AND DATE(d.created_at) BETWEEN ? AND ?))
             ORDER BY d.status ASC, d.due_date ASC",
            [$book['id'], $from, $to, $from, $to]
        );

        $formatted = [];
        foreach ($rows as $r) {
            $formatted[] = [
                $r['date'] ? date('d M Y', strtotime($r['date'])) : '—',
                $r['customer_name'],
                $r['title'],
                $sym . number_format((float)$r['amount'], 2),
                $sym . number_format((float)$r['paid_amount'], 2),
                $sym . number_format((float)$r['balance'], 2),
                ucfirst($r['status']),
            ];
        }

        $grouped = $this->groupRows($rows, $mode, $sym,
            fn($r) => (float)$r['amount'],
            fn($r) => (float)$r['paid_amount'],
            fn($r) => (float)$r['balance'],
            fn($r) => $r['date'] ?? date('Y-m-d')
        );

        return [$columns, $formatted, $title, $grouped];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // DEBTS
    // ─────────────────────────────────────────────────────────────────────────
    private function fetchDebts(array $book, array $details, string $sym, string $mode, string $from, string $to): array
    {
        $title   = 'Debts Report';
        $columns = ['Due Date', 'Party / Creditor', 'Title', 'Total Amount', 'Paid', 'Balance', 'Status'];

        $rows = Database::query(
            "SELECT COALESCE(d.due_date, DATE(d.created_at)) AS date,
                    COALESCE(d.party,'Unknown') AS party,
                    d.title, d.amount, d.paid_amount,
                    (d.amount - d.paid_amount) AS balance, d.status
             FROM debts d
             WHERE d.book_id=? AND (d.due_date BETWEEN ? AND ? OR (d.due_date IS NULL AND DATE(d.created_at) BETWEEN ? AND ?))
             ORDER BY d.status ASC, d.due_date ASC",
            [$book['id'], $from, $to, $from, $to]
        );

        $formatted = [];
        foreach ($rows as $r) {
            $formatted[] = [
                $r['date'] ? date('d M Y', strtotime($r['date'])) : '—',
                $r['party'],
                $r['title'],
                $sym . number_format((float)$r['amount'], 2),
                $sym . number_format((float)$r['paid_amount'], 2),
                $sym . number_format((float)$r['balance'], 2),
                ucfirst($r['status']),
            ];
        }

        $grouped = $this->groupRows($rows, $mode, $sym,
            fn($r) => (float)$r['amount'],
            fn($r) => (float)$r['paid_amount'],
            fn($r) => (float)$r['balance'],
            fn($r) => $r['date'] ?? date('Y-m-d')
        );

        return [$columns, $formatted, $title, $grouped];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CUSTOMERS
    // ─────────────────────────────────────────────────────────────────────────
    private function fetchCustomers(array $book, array $details, string $sym, string $mode, string $from, string $to): array
    {
        $title   = 'Customers Report';
        $columns = ['Name', 'Phone', 'Email', 'Address', 'Privilege', 'Loyalty Pts', 'Invoices', 'Total Billed', 'Total Paid', 'Outstanding'];

        $rows = Database::query(
            "SELECT c.name, c.phone, c.email, c.address,
                    cp.name AS privilege_name,
                    c.points,
                    COUNT(DISTINCT i.id) AS invoice_count,
                    COALESCE(SUM(i.total),0) AS total_billed,
                    COALESCE(SUM(i.paid),0)  AS total_paid,
                    COALESCE(SUM(i.total)-SUM(i.paid),0) AS outstanding
             FROM customers c
             LEFT JOIN customer_privileges cp ON cp.id = c.privilege_id
             LEFT JOIN invoices i ON i.customer_id=c.id AND i.deleted_at IS NULL
             WHERE c.book_id=? AND c.deleted_at IS NULL
             GROUP BY c.id ORDER BY c.name",
            [$book['id']]
        );

        $formatted = [];
        foreach ($rows as $r) {
            $formatted[] = [
                $r['name'],
                $r['phone']          ?? '—',
                $r['email']          ?? '—',
                $r['address']        ?? '—',
                $r['privilege_name'] ?? '—',
                number_format((int)$r['points']),
                number_format((int)$r['invoice_count']),
                $sym . number_format((float)$r['total_billed'], 2),
                $sym . number_format((float)$r['total_paid'], 2),
                $sym . number_format((float)$r['outstanding'], 2),
            ];
        }

        $totBilled  = array_sum(array_column($rows, 'total_billed'));
        $totPaid    = array_sum(array_column($rows, 'total_paid'));
        $totOutstanding = array_sum(array_column($rows, 'outstanding'));
        $grouped = [['label' => 'Summary — All Customers', 'rows' => [
            ['Total', '', '', '', '', '', count($rows),
             $sym . number_format($totBilled, 2),
             $sym . number_format($totPaid, 2),
             $sym . number_format($totOutstanding, 2)]
        ]]];

        return [$columns, $formatted, $title, $grouped];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // SUPPLIERS
    // ─────────────────────────────────────────────────────────────────────────
    private function fetchSuppliers(array $book, array $details, string $sym, string $mode, string $from, string $to): array
    {
        $title   = 'Suppliers Report';
        $columns = ['Name', 'Company', 'Phone', 'Email', 'Address', 'Invoices', 'Total Billed', 'Total Paid', 'Outstanding'];

        $rows = Database::query(
            "SELECT s.name, s.company, s.phone, s.email, s.address,
                    COUNT(DISTINCT i.id) AS invoice_count,
                    COALESCE(SUM(i.total),0) AS total_billed,
                    COALESCE(SUM(i.paid),0)  AS total_paid,
                    COALESCE(SUM(i.total)-SUM(i.paid),0) AS outstanding
             FROM suppliers s
             LEFT JOIN invoices i ON i.supplier_id=s.id AND i.deleted_at IS NULL
             WHERE s.book_id=? AND s.deleted_at IS NULL
             GROUP BY s.id ORDER BY s.name",
            [$book['id']]
        );

        $formatted = [];
        foreach ($rows as $r) {
            $formatted[] = [
                $r['name'],
                $r['company']  ?? '—',
                $r['phone']    ?? '—',
                $r['email']    ?? '—',
                $r['address']  ?? '—',
                number_format((int)$r['invoice_count']),
                $sym . number_format((float)$r['total_billed'], 2),
                $sym . number_format((float)$r['total_paid'], 2),
                $sym . number_format((float)$r['outstanding'], 2),
            ];
        }

        $totBilled = array_sum(array_column($rows, 'total_billed'));
        $totPaid   = array_sum(array_column($rows, 'total_paid'));
        $grouped   = [['label' => 'Summary', 'rows' => [
            ['Total ' . count($rows) . ' suppliers', '', '', '', '',
             array_sum(array_column($rows, 'invoice_count')),
             $sym . number_format($totBilled, 2),
             $sym . number_format($totPaid, 2),
             $sym . number_format($totBilled - $totPaid, 2)]
        ]]];

        return [$columns, $formatted, $title, $grouped];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // EMPLOYEES
    // ─────────────────────────────────────────────────────────────────────────
    private function fetchEmployees(array $book, array $details, string $sym, string $mode, string $from, string $to): array
    {
        $title   = 'Employees Report';
        $columns = ['Emp ID', 'Name', 'Designation', 'Phone', 'Email', 'Join Date', 'Salary', 'Total Paid', 'Status'];

        $rows = Database::query(
            "SELECT e.emp_code, e.name, e.designation_name, e.department, e.phone, e.email,
                    e.join_date, e.salary, e.salary_type, e.status,
                    COALESCE(SUM(sp.amount),0) AS total_paid_salary
             FROM employees e
             LEFT JOIN employee_salary_payments sp ON sp.employee_id = e.id AND sp.book_id = e.book_id
             WHERE e.book_id=? AND e.deleted_at IS NULL
             GROUP BY e.id ORDER BY e.name",
            [$book['id']]
        );

        $formatted = [];
        foreach ($rows as $r) {
            $desig = trim(implode(' / ', array_filter([$r['designation_name'], $r['department']]))) ?: '—';
            $formatted[] = [
                $r['emp_code'] ?? '—',
                $r['name'],
                $desig,
                $r['phone']        ?? '—',
                $r['email']        ?? '—',
                $r['join_date']    ? date('d M Y', strtotime($r['join_date'])) : '—',
                $sym . number_format((float)$r['salary'], 2),
                $sym . number_format((float)$r['total_paid_salary'], 2),
                ucfirst($r['status'] ?? 'active'),
            ];
        }

        $totSalary = array_sum(array_column($rows, 'salary'));
        $totPaid   = array_sum(array_column($rows, 'total_paid_salary'));
        $grouped   = [['label' => 'Summary', 'rows' => [
            ['Total ' . count($rows) . ' employees', '', '', '', '',
             $sym . number_format($totSalary, 2),
             $sym . number_format($totPaid, 2),
             '']
        ]]];

        return [$columns, $formatted, $title, $grouped];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CONTACTS
    // ─────────────────────────────────────────────────────────────────────────
    private function fetchContacts(array $book, array $details, string $sym, string $mode, string $from, string $to): array
    {
        $title   = 'Contacts Report';
        $columns = ['Name', 'Phone', 'Email', 'Address', 'Notes'];

        $rows = Database::query(
            "SELECT name, phone, email, address, notes
             FROM contacts WHERE book_id=? ORDER BY name",
            [$book['id']]
        );

        $formatted = [];
        foreach ($rows as $r) {
            $formatted[] = [
                $r['name'],
                $r['phone']   ?? '—',
                $r['email']   ?? '—',
                $r['address'] ?? '—',
                $r['notes']   ?? '—',
            ];
        }

        $grouped = [['label' => 'Summary', 'rows' => [
            [count($rows) . ' total contacts', '', '', '', '']
        ]]];

        return [$columns, $formatted, $title, $grouped];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // COUPONS
    // ─────────────────────────────────────────────────────────────────────────
    private function fetchCoupons(array $book, array $details, string $sym, string $mode, string $from, string $to): array
    {
        $title   = 'Coupons Report';
        $columns = ['Code', 'Name', 'Discount Type', 'Discount Value', 'Min. Order', 'Max Uses', 'Used', 'Expires', 'Status'];

        $rows = Database::query(
            "SELECT id, code, name, discount_type, discount_value,
                    note, is_active, expires_at, created_at
             FROM coupons WHERE book_id=? ORDER BY is_active DESC, created_at DESC",
            [$book['id']]
        );

        $formatted = [];
        foreach ($rows as $r) {
            $disc = $r['discount_type'] === 'percent'
                ? $r['discount_value'] . '%'
                : $sym . number_format((float)$r['discount_value'], 2);
            $formatted[] = [
                $r['code'],
                $r['name'],
                ucfirst($r['discount_type']),
                $disc,
                $r['note']       ? $r['note']    : '—',
                $r['expires_at'] ? date('d M Y', strtotime($r['expires_at'])) : 'No expiry',
                $r['is_active']  ? 'Active' : 'Inactive',
            ];
        }

        $activeCount = count(array_filter($rows, fn($r) => $r['is_active']));
        $grouped = [['label' => 'Summary', 'rows' => [
            [count($rows) . ' total coupons', $activeCount . ' active', '', '', '', '', '']
        ]]];

        $columns = ['Code', 'Name', 'Discount Type', 'Discount Value', 'Note', 'Expires', 'Status'];

        return [$columns, $formatted, $title, $grouped];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // RETURNS
    // ─────────────────────────────────────────────────────────────────────────
    private function fetchReturns(array $book, array $details, string $sym, string $mode, string $from, string $to): array
    {
        $title   = 'Returns Report';
        $columns = ['Date', 'Return No', 'Type', 'Original Invoice', 'Customer / Supplier', 'Total Refund', 'Reason'];

        $rows = Database::query(
            "SELECT r.date, r.return_no, r.type,
                    i.invoice_no AS orig_invoice,
                    COALESCE(c.name, s.name, 'Unknown') AS party,
                    r.total_refund, r.remarks AS reason
             FROM returns r
             LEFT JOIN invoices i  ON i.id  = r.invoice_id
             LEFT JOIN customers c ON c.id  = r.customer_id
             LEFT JOIN suppliers s ON s.id  = r.supplier_id
             WHERE r.book_id=? AND r.deleted_at IS NULL AND r.date BETWEEN ? AND ?
             ORDER BY r.date DESC, r.id DESC",
            [$book['id'], $from, $to]
        );

        $formatted = [];
        foreach ($rows as $r) {
            $formatted[] = [
                date('d M Y', strtotime($r['date'])),
                $r['return_no'],
                $r['type'] === 'sales_return' ? 'Sales Return' : 'Purchase Return',
                $r['orig_invoice'] ?? '—',
                $r['party'],
                $sym . number_format((float)$r['total_refund'], 2),
                $r['reason'] ?? '—',
            ];
        }

        $grouped = $this->groupRows($rows, $mode, $sym,
            null,
            fn($r) => (float)$r['total_refund'],
            null,
            fn($r) => $r['date']
        );

        return [$columns, $formatted, $title, $grouped];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PRIVILEGES
    // ─────────────────────────────────────────────────────────────────────────
    private function fetchPrivileges(array $book, array $details, string $sym, string $mode, string $from, string $to): array
    {
        $title   = 'Customer Privileges Report';
        $columns = ['Privilege Name', 'Discount Type', 'Discount Value', 'Customers Assigned', 'Description'];

        $rows = Database::query(
            "SELECT p.name, p.discount_type, p.discount_value, p.description,
                    COUNT(c.id) AS customer_count
             FROM customer_privileges p
             LEFT JOIN customers c ON c.privilege_id = p.id AND c.deleted_at IS NULL
             WHERE p.book_id=?
             GROUP BY p.id ORDER BY p.name",
            [$book['id']]
        );

        $formatted = [];
        foreach ($rows as $r) {
            $disc = $r['discount_type'] === 'percent'
                ? $r['discount_value'] . '%'
                : $sym . number_format((float)$r['discount_value'], 2);
            $formatted[] = [
                $r['name'],
                ucfirst($r['discount_type']),
                $disc,
                number_format((int)$r['customer_count']),
                $r['description'] ?? '—',
            ];
        }

        $grouped = [['label' => 'Summary', 'rows' => [
            [count($rows) . ' privilege tiers',
             array_sum(array_column($rows, 'customer_count')) . ' customers assigned',
             '', '', '']
        ]]];

        return [$columns, $formatted, $title, $grouped];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // REPORTS (combined ledger — same as ReportsController)
    // ─────────────────────────────────────────────────────────────────────────
    private function fetchReports(array $book, array $details, string $sym, string $mode, string $from, string $to): array
    {
        $title   = 'Financial Ledger Report';
        $columns = ['Date', 'Category', 'Reference / Description', 'Party', 'Income', 'Expense'];

        $entries = [];

        $queries = [
            // Sale invoices (IN)
            ["SELECT i.date, 'Sale Invoice' AS category, i.invoice_no AS ref,
                     COALESCE(c.name,'Walk-in') AS party, 'in' AS dir, i.total AS amount
              FROM invoices i LEFT JOIN customers c ON c.id=i.customer_id
              WHERE i.book_id=? AND i.type='sale' AND i.deleted_at IS NULL AND i.date BETWEEN ? AND ?",
             [$book['id'], $from, $to]],
            // Purchase invoices (OUT)
            ["SELECT i.date, 'Purchase Invoice' AS category, i.invoice_no AS ref,
                     COALESCE(s.name,'Unknown') AS party, 'out' AS dir, i.total AS amount
              FROM invoices i LEFT JOIN suppliers s ON s.id=i.supplier_id
              WHERE i.book_id=? AND i.type='purchase' AND i.deleted_at IS NULL AND i.date BETWEEN ? AND ?",
             [$book['id'], $from, $to]],
            // Sales returns (OUT)
            ["SELECT r.date, 'Sales Return' AS category, r.return_no AS ref,
                     COALESCE(c.name,'Unknown') AS party, 'out' AS dir, r.total_refund AS amount
              FROM returns r LEFT JOIN customers c ON c.id=r.customer_id
              WHERE r.book_id=? AND r.type='sales_return' AND r.deleted_at IS NULL AND r.date BETWEEN ? AND ?",
             [$book['id'], $from, $to]],
            // Purchase returns (IN)
            ["SELECT r.date, 'Purchase Return' AS category, r.return_no AS ref,
                     COALESCE(s.name,'Unknown') AS party, 'in' AS dir, r.total_refund AS amount
              FROM returns r LEFT JOIN suppliers s ON s.id=r.supplier_id
              WHERE r.book_id=? AND r.type='purchase_return' AND r.deleted_at IS NULL AND r.date BETWEEN ? AND ?",
             [$book['id'], $from, $to]],
            // Expenses (OUT)
            ["SELECT e.expense_date AS date, CONCAT('Expense: ', COALESCE(ec.name,'General')) AS category,
                     e.title AS ref, COALESCE(e.paid_to,'—') AS party, 'out' AS dir, e.amount
              FROM expenses e LEFT JOIN expense_categories ec ON ec.id=e.category_id
              WHERE e.book_id=? AND e.expense_date BETWEEN ? AND ?",
             [$book['id'], $from, $to]],
            // Funds IN
            ["SELECT fund_date AS date, 'Fund Received' AS category,
                     COALESCE(title,'Fund') AS ref, '—' AS party, 'in' AS dir, amount
              FROM funds WHERE book_id=? AND type='in' AND fund_date BETWEEN ? AND ?",
             [$book['id'], $from, $to]],
            // Funds OUT
            ["SELECT fund_date AS date, 'Fund Withdrawn' AS category,
                     COALESCE(title,'Withdrawal') AS ref, '—' AS party, 'out' AS dir, amount
              FROM funds WHERE book_id=? AND type='out' AND fund_date BETWEEN ? AND ?",
             [$book['id'], $from, $to]],
            // Due payments (IN)
            ["SELECT DATE(dp.paid_at) AS date, 'Due Payment' AS category,
                     CONCAT('Due: ', d.title) AS ref,
                     COALESCE(c.name,'Unknown') AS party, 'in' AS dir, dp.amount
              FROM due_payments dp JOIN dues d ON d.id=dp.due_id
              LEFT JOIN customers c ON c.id=d.customer_id
              WHERE dp.book_id=? AND DATE(dp.paid_at) BETWEEN ? AND ?",
             [$book['id'], $from, $to]],
            // Debt payments (OUT)
            ["SELECT DATE(dp.paid_at) AS date, 'Debt Repayment' AS category,
                     CONCAT('Debt: ', d.title) AS ref,
                     COALESCE(d.party,'—') AS party, 'out' AS dir, dp.amount
              FROM debt_payments dp JOIN debts d ON d.id=dp.debt_id
              WHERE dp.book_id=? AND DATE(dp.paid_at) BETWEEN ? AND ?",
             [$book['id'], $from, $to]],
        ];

        foreach ($queries as [$sql, $bind]) {
            try {
                foreach (Database::query($sql, $bind) as $r) $entries[] = $r;
            } catch (\Throwable $e) {}
        }

        // Salary payments (OUT)
        try {
            foreach (Database::query(
                "SELECT DATE(sp.created_at) AS date, 'Salary Payment' AS category,
                        CONCAT('Salary: ', em.name) AS ref,
                        em.name AS party, 'out' AS dir, sp.amount
                 FROM employee_salary_payments sp JOIN employees em ON em.id=sp.employee_id
                 WHERE sp.book_id=? AND DATE(sp.created_at) BETWEEN ? AND ?",
                [$book['id'], $from, $to]
            ) as $r) $entries[] = $r;
        } catch (\Throwable $e) {}

        usort($entries, fn($a, $b) => strcmp($b['date'], $a['date']));

        $formatted = [];
        foreach ($entries as $e) {
            $isIn = $e['dir'] === 'in';
            $formatted[] = [
                date('d M Y', strtotime($e['date'])),
                $e['category'],
                $e['ref'],
                $e['party'],
                $isIn  ? $sym . number_format((float)$e['amount'], 2) : '',
                !$isIn ? $sym . number_format((float)$e['amount'], 2) : '',
            ];
        }

        $totalIn  = array_sum(array_map(fn($e) => $e['dir']==='in'  ? (float)$e['amount'] : 0, $entries));
        $totalOut = array_sum(array_map(fn($e) => $e['dir']==='out' ? (float)$e['amount'] : 0, $entries));
        $net      = $totalIn - $totalOut;

        $grouped = $this->groupRows($entries, $mode, $sym,
            fn($r) => $r['dir']==='in'  ? (float)$r['amount'] : 0,
            fn($r) => $r['dir']==='out' ? (float)$r['amount'] : 0,
            fn($r) => ($r['dir']==='in' ? 1 : -1) * (float)$r['amount'],
            fn($r) => $r['date']
        );

        return [$columns, $formatted, $title, $grouped];
    }

    // =========================================================================
    // GROUP ROWS BY DAY OR MONTH (for month/year/days modes)
    // =========================================================================
    private function groupRows(
        array $rows, string $mode, string $sym,
        ?\Closure $getIn, ?\Closure $getOut, ?\Closure $getNet,
        \Closure $getDate
    ): array {
        if ($mode === 'date') return []; // single day = show all rows, no grouping needed

        $buckets = [];
        foreach ($rows as $r) {
            $dateStr = $getDate($r);
            if (!$dateStr) continue;
            $key = ($mode === 'year')
                ? date('Y-m', strtotime($dateStr))
                : date('Y-m-d', strtotime($dateStr));
            $buckets[$key]['in']    = ($buckets[$key]['in']  ?? 0) + ($getIn  ? $getIn($r)  : 0);
            $buckets[$key]['out']   = ($buckets[$key]['out'] ?? 0) + ($getOut ? $getOut($r) : 0);
            $buckets[$key]['count'] = ($buckets[$key]['count'] ?? 0) + 1;
        }
        ksort($buckets);

        $result = [];
        $grandIn = $grandOut = 0;
        foreach ($buckets as $key => $data) {
            $label = ($mode === 'year')
                ? date('F Y', strtotime($key . '-01'))
                : date('d F Y', strtotime($key));
            $in  = $data['in'];
            $out = $data['out'];
            $net = ($getNet !== null) ? ($in - $out) : null;
            $grandIn  += $in;
            $grandOut += $out;

            $row = [$label, $data['count'] . ' entries'];
            if ($getIn)  $row[] = $sym . number_format($in, 2);
            if ($getOut) $row[] = $sym . number_format($out, 2);
            if ($getNet !== null) $row[] = ($net >= 0 ? '+' : '') . $sym . number_format(abs($net), 2);

            $result[] = $row;
        }

        // Grand total row
        if (!empty($result)) {
            $totalRow = ['TOTAL', count($rows) . ' entries'];
            if ($getIn)  $totalRow[] = $sym . number_format($grandIn, 2);
            if ($getOut) $totalRow[] = $sym . number_format($grandOut, 2);
            if ($getNet !== null) {
                $gnet = $grandIn - $grandOut;
                $totalRow[] = ($gnet >= 0 ? '+' : '') . $sym . number_format(abs($gnet), 2);
            }
            $totalRow['__is_total__'] = true;
            $result[] = $totalRow;
        }

        $headers = ['Period', 'Count'];
        if ($getIn)  $headers[] = 'Income';
        if ($getOut) $headers[] = 'Expense';
        if ($getNet !== null) $headers[] = 'Net';

        return [['headers' => $headers, 'rows' => $result]];
    }

    // =========================================================================
    // PDF RENDERING
    // =========================================================================
    private function renderPdf(
        array $book, array $details, string $sym,
        string $category, string $title,
        array $columns, array $rows, array $groupedRows,
        string $mode, string $periodLabel,
        string $dateFrom, string $dateTo,
        ?string $printerEmpCode = null
    ): void {
        $businessName  = $details['business_name'] ?? $book['name'];
        $businessPhone = $details['phone']   ?? $book['phone']  ?? '';
        $businessEmail = $details['email']   ?? $book['email']  ?? '';
        $businessAddr  = $details['address'] ?? $book['address'] ?? '';
        $generatedBy   = auth()['name'] ?? 'Staff';
        $generatedAt   = date('d M Y') . ' at ' . date('g:i A');

        $logoHtml = '';
        if (!empty($book['logo'])) {
            $logoPath = config('upload.path') . '/' . $book['logo'];
            if (file_exists($logoPath)) {
                $logoHtml = '<img src="' . htmlspecialchars($logoPath) . '" style="max-height:50px;max-width:120px;object-fit:contain">';
            }
        }

        // Static (list) categories always show all detail rows; no time-grouping
        $staticCategories = ['products', 'customers', 'suppliers', 'employees', 'contacts', 'coupons', 'privileges'];
        $isStatic = in_array($category, $staticCategories);

        if ($isStatic) {
            $showDetail  = true;
            $showGrouped = !empty($groupedRows); // still show their summary block
        } else {
            $showDetail  = in_array($mode, ['date', 'days', 'all']);
            $showGrouped = in_array($mode, ['days', 'month', 'year']) && !empty($groupedRows);
        }

        $catTitle = ucwords(str_replace('_', ' ', $category));
        $html = $this->buildPdfHtml(
            $businessName, $businessPhone, $businessEmail, $businessAddr,
            $logoHtml, $title, $periodLabel, $catTitle,
            $columns, $rows, $groupedRows,
            $showDetail, $showGrouped,
            $generatedBy, $generatedAt, $sym, $mode, $printerEmpCode
        );

        try {
            $mpdf = new \Mpdf\Mpdf([
                'mode'             => 'utf-8',
                'format'           => 'A4-L',
                'margin_top'       => 25,
                'margin_bottom'    => 22,
                'margin_left'      => 12,
                'margin_right'     => 12,
                'tempDir'          => sys_get_temp_dir(),
                // mPDF font registry keys are lowercase with no spaces
                'default_font'     => 'dejavusans',
                // Allow fallback to other fonts for glyphs (e.g. ৳) not in the primary font
                'useSubstitutions' => true,
            ]);

            $mpdf->SetTitle($title . ' — ' . $periodLabel);
            $mpdf->SetAuthor('Byabsayee');
            // Watermark text disabled — footer branding is sufficient
            $mpdf->showWatermarkText = false;
            $mpdf->WriteHTML($html);

            $safeName   = preg_replace('/[^a-z0-9\-]/i', '-', $catTitle);
            $safePeriod = preg_replace('/[^a-z0-9\-]/i', '-', $periodLabel);
            $filename   = strtolower($safeName . '-' . $safePeriod) . '.pdf';

            $mpdf->Output($filename, 'I');
        } catch (\Error $e) {
            header('Content-Type: text/html; charset=utf-8');
            echo '<div style="font-family:sans-serif;padding:40px;max-width:600px">'
                . '<h3 style="color:#c00">PDF Library Not Available</h3>'
                . '<p>mPDF could not be loaded: <code>' . htmlspecialchars($e->getMessage()) . '</code></p>'
                . '<p>Run <code>composer install</code> in the project root to install dependencies.</p>'
                . '</div>';
        } catch (\Throwable $e) {
            error_log('PrintController mPDF error: ' . $e->getMessage());
            header('Content-Type: text/plain; charset=utf-8');
            echo 'PDF generation failed: ' . htmlspecialchars($e->getMessage());
        }
        exit;
    }

    // =========================================================================
    // BUILD PDF HTML
    // =========================================================================
    private function buildPdfHtml(
        string $bizName, string $bizPhone, string $bizEmail, string $bizAddr,
        string $logoHtml, string $title, string $period, string $catTitle,
        array $columns, array $rows, array $groupedRows,
        bool $showDetail, bool $showGrouped,
        string $generatedBy, string $generatedAt, string $sym, string $mode,
        ?string $printerEmpCode = null
    ): string {
        $h = fn(string $s) => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

        // ── Header ────────────────────────────────────────────────────────
        $addrLine = implode(' · ', array_filter([
            trim($bizAddr), $bizPhone, $bizEmail
        ]));

        $headerHtml = '
        <table style="width:100%;border-bottom:2px solid #1a6b4a;padding-bottom:8px;margin-bottom:12px">
        <tr>
            <td style="vertical-align:middle;width:60%">
                ' . ($logoHtml ? '<div style="margin-bottom:4px">' . $logoHtml . '</div>' : '') . '
                <div style="font-size:17px;font-weight:800;color:#1a1a1a">' . $h($bizName) . '</div>
                ' . ($addrLine ? '<div style="font-size:10px;color:#555;margin-top:2px">' . $h($addrLine) . '</div>' : '') . '
            </td>
            <td style="text-align:right;vertical-align:middle">
                <div style="font-size:15px;font-weight:700;color:#1a6b4a">' . $h($title) . '</div>
                <div style="font-size:12px;color:#333;margin-top:3px">Period: <strong>' . $h($period) . '</strong></div>
            </td>
        </tr>
        </table>';

        // ── Grouped summary table ─────────────────────────────────────────
        $groupHtml = '';
        if ($showGrouped && !empty($groupedRows)) {
            foreach ($groupedRows as $group) {
                if (empty($group['rows'])) continue;
                $headers = $group['headers'] ?? [];
                $groupHtml .= '<div style="margin-bottom:16px">';
                if (!empty($group['label'])) {
                    $groupHtml .= '<div style="font-size:12px;font-weight:700;color:#1a6b4a;margin-bottom:4px;text-transform:uppercase;letter-spacing:.5px">'
                        . $h($group['label'] ?? '') . '</div>';
                }
                if (!empty($headers)) {
                    $groupHtml .= '<table style="width:100%;border-collapse:collapse;font-size:11px">';
                    $groupHtml .= '<thead><tr style="background:#1a6b4a;color:#fff">';
                    foreach ($headers as $hdr) {
                        $groupHtml .= '<th style="padding:6px 8px;text-align:' . (in_array($hdr,['Income','Expense','Net','Amount']) ? 'right' : 'left') . '">' . $h($hdr) . '</th>';
                    }
                    $groupHtml .= '</tr></thead><tbody>';
                    $odd = false;
                    foreach ($group['rows'] as $i => $row) {
                        $isTotal = !empty($row['__is_total__']);
                        if ($isTotal) {
                            unset($row['__is_total__']);
                            $row = array_values($row);
                        }
                        $bg = $isTotal ? '#f0fdf4' : ($odd ? '#f9f9f9' : '#fff');
                        $fw = $isTotal ? '800' : '400';
                        $groupHtml .= '<tr style="background:' . $bg . ';border-bottom:1px solid #eee;font-weight:' . $fw . '">';
                        foreach ((array)$row as $j => $cell) {
                            $align = ($j >= 2) ? 'right' : 'left';
                            $groupHtml .= '<td style="padding:5px 8px;text-align:' . $align . '">' . $h((string)$cell) . '</td>';
                        }
                        $groupHtml .= '</tr>';
                        $odd = !$odd;
                    }
                    $groupHtml .= '</tbody></table>';
                }
                $groupHtml .= '</div>';
            }
        }

        // ── Detail rows table ─────────────────────────────────────────────
        $detailHtml = '';
        if ($showDetail && !empty($rows)) {
            $numCols = count($columns);
            $detailHtml .= '<div style="margin-top:' . ($groupHtml ? '20px' : '0') . '">';
            if ($groupHtml) {
                $detailHtml .= '<div style="font-size:12px;font-weight:700;color:#1a6b4a;margin-bottom:6px;text-transform:uppercase;letter-spacing:.5px">Detailed Entries</div>';
            }
            $detailHtml .= '<table style="width:100%;border-collapse:collapse;font-size:10.5px">';
            $detailHtml .= '<thead><tr style="background:#1a6b4a;color:#fff">';
            foreach ($columns as $col) {
                $isNum = in_array($col, ['Total','Paid','Balance','Amount','Income','Expense','Net','Stock Value','Buy Price','Sell Price','Total Billed','Total Paid','Outstanding','Total Amount']);
                $detailHtml .= '<th style="padding:6px 7px;text-align:' . ($isNum ? 'right' : 'left') . ';white-space:nowrap">' . $h($col) . '</th>';
            }
            $detailHtml .= '</tr></thead><tbody>';

            $odd = false;
            foreach ($rows as $row) {
                $bg = $odd ? '#f9f9f9' : '#fff';
                $detailHtml .= '<tr style="background:' . $bg . ';border-bottom:1px solid #eee">';
                foreach (array_values((array)$row) as $j => $cell) {
                    $colName = $columns[$j] ?? '';
                    $isNum   = in_array($colName, ['Total','Paid','Balance','Amount','Income','Expense','Net','Stock Value','Buy Price','Sell Price','Total Billed','Total Paid','Outstanding','Total Amount']);
                    $detailHtml .= '<td style="padding:4px 7px;text-align:' . ($isNum ? 'right' : 'left') . '">' . $h((string)$cell) . '</td>';
                }
                $detailHtml .= '</tr>';
                $odd = !$odd;
            }
            $detailHtml .= '</tbody></table>';
            $detailHtml .= '</div>';
        } elseif ($showDetail && empty($rows)) {
            $detailHtml = '<div style="text-align:center;padding:30px;color:#888;font-size:13px">No records found for this period.</div>';
        }

        // ── Footer ────────────────────────────────────────────────────────
        $printerLabel = $generatedBy;
        if ($printerEmpCode) $printerLabel .= ' (' . $printerEmpCode . ')';
        $footerHtml = '<div style="text-align:center;font-size:9px;color:#888;border-top:1px solid #eee;padding-top:7px;margin-top:18px">
            Generated using <strong style="color:#1a6b4a">Byabsayee</strong> (<span style="color:#1a6b4a">https://byabsayee.com</span>)
            by ' . $h($printerLabel) . ' on ' . $h($generatedAt) . '
        </div>';

        // ── Full HTML ─────────────────────────────────────────────────────
        return '<!DOCTYPE html>
<html><head><meta charset="UTF-8">
<style>
* { box-sizing:border-box; margin:0; padding:0; }
body { font-family:dejavusans,sans-serif; font-size:11px; color:#1a1a1a; background:#fff; }
table { border-collapse:collapse; }
</style>
</head><body>
' . $headerHtml
  . $groupHtml
  . $detailHtml
  . $footerHtml . '
</body></html>';
    }

    // =========================================================================
    // HELPERS
    // =========================================================================
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
        if (!$book) { http_response_code(404); echo 'Book not found.'; exit; }
        return $book;
    }
}
