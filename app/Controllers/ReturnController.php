<?php
namespace App\Controllers;
use App\Helpers\Database;
use App\Services\ActivityLogger;

class ReturnController
{
    // ── List all returns ──────────────────────────────────────────────────────
    public function index(array $params): void
    {
        if (guest()) redirect('/login');
        $book = $this->getBookOrFail($params['id']);
        if (!book_can($book, 'returns', 'view')) abort_403();

        $type = $_GET['type'] ?? 'all';
        $month     = $_GET['month'] ?? date('Y-m');
        $dateFrom  = $month . '-01';
        $dateTo    = date('Y-m-t', strtotime($dateFrom));
        $prevMonth = date('Y-m', strtotime($dateFrom . ' -1 month'));
        $nextMonth = date('Y-m', strtotime($dateFrom . ' +1 month'));
        $isCurrent = ($month === date('Y-m'));

        $sql  = 'SELECT r.*,
                        c.name AS customer_name,
                        s.name AS supplier_name,
                        i.invoice_no AS orig_invoice_no
                 FROM returns r
                 LEFT JOIN customers c ON r.customer_id = c.id
                 LEFT JOIN suppliers s ON r.supplier_id = s.id
                 LEFT JOIN invoices  i ON r.invoice_id  = i.id
                 WHERE r.book_id=? AND r.deleted_at IS NULL
                   AND r.date BETWEEN ? AND ?';
        $p = [$book['id'], $dateFrom, $dateTo];
        if ($type !== 'all') { $sql .= ' AND r.type=?'; $p[] = $type; }
        $sql .= ' ORDER BY r.date DESC, r.id DESC';

        $returns = Database::query($sql, $p);

        $summary = Database::row(
            'SELECT
                COALESCE(SUM(CASE WHEN type="sales_return"    THEN total_refund ELSE 0 END),0) AS sales_refunds,
                COALESCE(SUM(CASE WHEN type="purchase_return" THEN total_refund ELSE 0 END),0) AS purchase_refunds,
                COUNT(*) AS total_count
             FROM returns WHERE book_id=? AND deleted_at IS NULL',
            [$book['id']]
        );

        require BASE_PATH . '/views/business/returns/index.php';
    }

    // ── Show create form ──────────────────────────────────────────────────────
    public function create(array $params): void
    {
        if (guest()) redirect('/login');
        $book = $this->getBookOrFail($params['id']);
        if (!book_can($book, 'returns', 'create')) abort_403();

        $type = $_GET['type'] ?? 'sales_return';

        // List invoices for selection
        if ($type === 'sales_return') {
            $invoices = Database::query(
                'SELECT i.id, i.invoice_no, i.date, i.total, c.name AS party_name
                 FROM invoices i
                 LEFT JOIN customers c ON i.customer_id = c.id
                 WHERE i.book_id=? AND i.type="sale" AND i.deleted_at IS NULL
                 ORDER BY i.date DESC LIMIT 200',
                [$book['id']]
            );
        } else {
            $invoices = Database::query(
                'SELECT i.id, i.invoice_no, i.date, i.total, s.name AS party_name
                 FROM invoices i
                 LEFT JOIN suppliers s ON i.supplier_id = s.id
                 WHERE i.book_id=? AND i.type="purchase" AND i.deleted_at IS NULL
                 ORDER BY i.date DESC LIMIT 200',
                [$book['id']]
            );
        }

        // Counter
        $lastReturn = Database::row(
            'SELECT COUNT(*) AS n FROM returns WHERE book_id=?', [$book['id']]
        );
        $returnNo = 'RET-' . str_pad((($lastReturn['n'] ?? 0) + 1), 5, '0', STR_PAD_LEFT);

        require BASE_PATH . '/views/business/returns/create.php';
    }

    // ── AJAX: get invoice items for selected invoice ───────────────────────────
    public function getInvoiceItems(array $params): void
    {
        if (guest()) json_response(['error' => 'Unauthorized'], 401);
        $book      = $this->getBookOrFail($params['id']);
        if (!book_can($book, 'returns', 'view')) abort_403();
        $invoiceId = (int)($_GET['invoice_id'] ?? 0);

        $invoice = Database::row(
            'SELECT * FROM invoices WHERE id=? AND book_id=? AND deleted_at IS NULL',
            [$invoiceId, $book['id']]
        );
        if (!$invoice) json_response(['error' => 'Invoice not found'], 404);

        $items = Database::query(
            'SELECT ii.*, p.name AS product_name
             FROM invoice_items ii
             LEFT JOIN products p ON ii.product_id = p.id
             WHERE ii.invoice_id=?',
            [$invoiceId]
        );

        json_response([
            'invoice' => $invoice,
            'items'   => $items,
        ]);
    }

    // ── Store return ──────────────────────────────────────────────────────────
    public function store(array $params): void
    {
        if (guest()) redirect('/login');
        csrf_verify();
        $book = $this->getBookOrFail($params['id']);
        if (!book_can($book, 'returns', 'create')) abort_403();

        $type       = $_POST['type']       ?? 'sales_return';
        $invoiceId  = !empty($_POST['invoice_id']) ? (int)$_POST['invoice_id'] : null;
        $returnNo   = trim($_POST['return_no']     ?? '');
        $date       = $_POST['date']       ?? date('Y-m-d');
        $discount   = (float)($_POST['discount']        ?? 0);
        $delivery   = (float)($_POST['delivery_charge'] ?? 0);
        $remarks    = trim($_POST['remarks'] ?? '');

        // Get party from original invoice
        $customerId = null;
        $supplierId = null;
        if ($invoiceId) {
            $origInv = Database::row('SELECT * FROM invoices WHERE id=? AND book_id=?', [$invoiceId, $book['id']]);
            if ($origInv) {
                $customerId = $origInv['customer_id'] ?? null;
                $supplierId = $origInv['supplier_id'] ?? null;
            }
        }

        $itemNames  = $_POST['item_name']       ?? [];
        $itemQtys   = $_POST['item_qty']        ?? [];
        $itemPrices = $_POST['item_price']      ?? [];
        $itemPids   = $_POST['item_product_id'] ?? [];

        if (empty($itemNames)) {
            redirect('/books/'.$book['id'].'/returns/create?type='.$type, ['error' => 'Add at least one item.']);
        }

        // Compute subtotal
        $subtotal = 0;
        $items    = [];
        foreach ($itemNames as $i => $name) {
            $name  = trim($name);
            if (!$name) continue;
            $qty   = (float)($itemQtys[$i]   ?? 0);
            $price = (float)($itemPrices[$i] ?? 0);
            $pid   = !empty($itemPids[$i])   ? (int)$itemPids[$i] : null;
            $line  = $qty * $price;
            $subtotal += $line;
            $items[] = compact('name', 'qty', 'price', 'pid', 'line');
        }

        $totalRefund = max(0, $subtotal - $discount + $delivery);

        // Insert return record
        Database::run(
            'INSERT INTO returns
                (book_id,invoice_id,type,return_no,date,customer_id,supplier_id,
                 subtotal,discount,delivery_charge,total_refund,remarks,status,created_by,created_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [
                $book['id'], $invoiceId, $type, $returnNo, $date,
                $customerId, $supplierId,
                $subtotal, $discount, $delivery, $totalRefund,
                $remarks ?: null, 'completed', auth()['id'], now()
            ]
        );
        $returnId = Database::lastId();

        // Insert items & update stock
        foreach ($items as $item) {
            Database::run(
                'INSERT INTO return_items (return_id,product_id,description,qty,unit_price,line_total)
                 VALUES (?,?,?,?,?,?)',
                [$returnId, $item['pid'], $item['name'], $item['qty'], $item['price'], $item['line']]
            );

            if ($item['pid']) {
                // Sales return: goods come back → stock goes UP
                // Purchase return: goods leave → stock goes DOWN
                if ($type === 'sales_return') {
                    Database::run(
                        'UPDATE products SET stock_qty=stock_qty+? WHERE id=? AND book_id=?',
                        [$item['qty'], $item['pid'], $book['id']]
                    );
                } else {
                    Database::run(
                        'UPDATE products SET stock_qty=GREATEST(0,stock_qty-?) WHERE id=? AND book_id=?',
                        [$item['qty'], $item['pid'], $book['id']]
                    );
                }
            }
        }

        // Add delivery charge to expenses if any
        if ($delivery > 0) {
            Database::run(
                'INSERT INTO expenses (book_id,title,amount,expense_date,note,created_by,created_at)
                 VALUES (?,?,?,?,?,?,?)',
                [
                    $book['id'],
                    'Delivery charge on return '.$returnNo,
                    $delivery, $date,
                    'Auto-created from return #'.$returnNo,
                    auth()['id'], now()
                ]
            );
        }

        // Record in report_entries
        // The items themselves: sales return = OUT (refund), purchase return = IN (recoup)
        $reportType = ($type === 'sales_return') ? 'out' : 'in';
        Database::run(
            'INSERT INTO report_entries (book_id,type,category,amount,description,source_table,source_id,date,created_at)
             VALUES (?,?,?,?,?,?,?,?,?)',
            [
                $book['id'], $reportType, $type,
                $totalRefund,
                ($type === 'sales_return' ? 'Sales return refund' : 'Purchase return recovery').' — '.$returnNo,
                'returns', $returnId, $date, now()
            ]
        );

        // Non-refund portion (discount) goes to reports as gain/loss
        if ($discount > 0) {
            $discCategory = ($type === 'sales_return') ? 'return_discount_kept' : 'return_loss';
            $discType     = ($type === 'sales_return') ? 'in' : 'out';
            $discDesc     = ($type === 'sales_return')
                ? 'Non-refunded amount kept from sales return '.$returnNo
                : 'Non-recovered amount on purchase return '.$returnNo;
            Database::run(
                'INSERT INTO report_entries (book_id,type,category,amount,description,source_table,source_id,date,created_at)
                 VALUES (?,?,?,?,?,?,?,?,?)',
                [$book['id'], $discType, $discCategory, $discount, $discDesc, 'returns', $returnId, $date, now()]
            );
        }

        ActivityLogger::write($book['id'], auth()['id'], 'return.created', 'Return', $returnId,
            "Return recorded — {$returnNo} — " . ($type === 'sales_return' ? 'Sales Return' : 'Purchase Return') . " — {$totalRefund}",
            null, ['return_no'=>$returnNo,'type'=>$type,'total_refund'=>$totalRefund]);

        redirect('/books/'.$book['id'].'/returns', ['success' => 'Return '.$returnNo.' recorded.']);
    }

    // ── Show single return ────────────────────────────────────────────────────
    public function show(array $params): void
    {
        if (guest()) redirect('/login');
        $book   = $this->getBookOrFail($params['id']);
        if (!book_can($book, 'returns', 'view')) abort_403();
        $return = $this->getReturnOrFail($params['return_id'], $book['id']);
        $items  = Database::query(
            'SELECT ri.*, p.name AS product_name FROM return_items ri
             LEFT JOIN products p ON p.id = ri.product_id
             WHERE ri.return_id=?',
            [$return['id']]
        );
        $invoice  = $return['invoice_id'] ? Database::row('SELECT * FROM invoices WHERE id=?', [$return['invoice_id']]) : null;
        $customer = $return['customer_id'] ? Database::row('SELECT * FROM customers WHERE id=?', [$return['customer_id']]) : null;
        $supplier = $return['supplier_id'] ? Database::row('SELECT * FROM suppliers WHERE id=?', [$return['supplier_id']]) : null;
        require BASE_PATH . '/views/business/returns/show.php';
    }

    // ── Delete ────────────────────────────────────────────────────────────────
    public function delete(array $params): void
    {
        if (guest()) redirect('/login');
        csrf_verify();
        $book   = $this->getBookOrFail($params['id']);
        if (!book_can($book, 'returns', 'delete')) abort_403();
        $return = $this->getReturnOrFail($params['return_id'], $book['id']);

        ActivityLogger::write($book['id'], auth()['id'], 'return.deleted', 'Return', (int)$return['id'],
            "Return deleted — " . ($return['return_no'] ?? '#'.$return['id']) . " — {$return['total_refund']}",
            ['return_no'=>$return['return_no'],'type'=>$return['type'],'total_refund'=>$return['total_refund']]);

        Database::run('UPDATE returns SET deleted_at=? WHERE id=?', [now(), $return['id']]);
        redirect('/books/'.$book['id'].'/returns', ['success' => 'Return deleted.']);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────
        private function getBookOrFail(string $id): array
    {
        $book = book_for_user($id, 'business');
        if (!$book) { http_response_code(404); require BASE_PATH.'/views/errors/404.php'; exit; }
        return $book;
    }

    private function getReturnOrFail(string $rid, int $bookId): array
    {
        $r = Database::row('SELECT * FROM returns WHERE id=? AND book_id=? AND deleted_at IS NULL', [$rid, $bookId]);
        if (!$r) { http_response_code(404); require BASE_PATH.'/views/errors/404.php'; exit; }
        return $r;
    }
}
