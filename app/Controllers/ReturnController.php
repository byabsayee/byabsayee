<?php
namespace App\Controllers;
use App\Helpers\Database;
use App\Services\ActivityLogger;
use App\Services\LedgerService;
use App\Services\InsufficientStockException;

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
                 WHERE i.book_id=? AND i.type IN ("sale","pos") AND i.deleted_at IS NULL AND i.status<>"cancelled"
                 ORDER BY i.date DESC LIMIT 200',
                [$book['id']]
            );
        } else {
            $invoices = Database::query(
                'SELECT i.id, i.invoice_no, i.date, i.total, s.name AS party_name
                 FROM invoices i
                 LEFT JOIN suppliers s ON i.supplier_id = s.id
                 WHERE i.book_id=? AND i.type="purchase" AND i.deleted_at IS NULL AND i.status<>"cancelled"
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

        $type      = ($_POST['type'] ?? 'sales_return') === 'purchase_return' ? 'purchase_return' : 'sales_return';
        $back      = '/books/'.$book['id'].'/returns/create?type='.$type;
        $invoiceId = !empty($_POST['invoice_id']) ? (int)$_POST['invoice_id'] : null;
        $date      = (isset($_POST['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['date'])) ? $_POST['date'] : date('Y-m-d');
        $discount  = max(0, round((float)($_POST['discount']        ?? 0), 2));
        $delivery  = max(0, round((float)($_POST['delivery_charge'] ?? 0), 2));
        $remarks   = trim($_POST['remarks'] ?? '');

        $itemNames  = $_POST['item_name']       ?? [];
        $itemQtys   = $_POST['item_qty']        ?? [];
        $itemPrices = $_POST['item_price']      ?? [];
        $itemPids   = $_POST['item_product_id'] ?? [];

        $subtotal = 0.0; $items = [];
        foreach ($itemNames as $i => $name) {
            $name = trim($name);
            if ($name === '') continue;
            $qty = (float)($itemQtys[$i] ?? 0); $price = (float)($itemPrices[$i] ?? 0);
            if ($qty <= 0)  redirect($back, ['error' => "“{$name}”: the returned quantity must be above zero."]);
            if ($price < 0) redirect($back, ['error' => "“{$name}”: the price cannot be negative."]);
            $line = round($qty * $price, 2);
            $subtotal += $line;
            $items[] = ['name' => mb_substr($name, 0, 255), 'qty' => $qty, 'price' => $price, 'pid' => !empty($itemPids[$i]) ? (int)$itemPids[$i] : null, 'line' => $line];
        }
        if (!$items) redirect($back, ['error' => 'Add at least one item.']);
        if ($discount > $subtotal + 0.004) redirect($back, ['error' => 'The amount you keep back is more than the goods are worth.']);
        // delivery/handling is part of what is paid back (it is NOT also booked as an expense — that used to count it twice)
        $totalRefund = round(max(0, $subtotal - $discount + $delivery), 2);

        try {
            $returnId = Database::transaction(function () use ($book, $type, $invoiceId, $date, $discount, $delivery, $remarks, $items, $subtotal, $totalRefund) {
                $customerId = $supplierId = null; $inv = null;
                if ($invoiceId) {
                    $inv = Database::row('SELECT * FROM invoices WHERE id=? AND book_id=? AND deleted_at IS NULL FOR UPDATE', [$invoiceId, $book['id']]);
                    if (!$inv) throw new \DomainException('That invoice no longer exists.');
                    if ($inv['status'] === 'cancelled') throw new \DomainException('That invoice is cancelled — nothing can be returned against it.');
                    $wantType = $type === 'sales_return' ? ['sale', 'pos'] : ['purchase'];
                    if (!in_array($inv['type'], $wantType, true)) throw new \DomainException('A ' . ($type === 'sales_return' ? 'sales' : 'purchase') . ' return must be made against a ' . ($type === 'sales_return' ? 'sales' : 'purchase') . ' invoice.');
                    $customerId = $inv['customer_id']; $supplierId = $inv['supplier_id'];
                    $this->checkQuantities($inv, $type, $items);
                }

                $returnNo = trim($_POST['return_no'] ?? '');
                if ($returnNo === '' || Database::row('SELECT id FROM returns WHERE book_id=? AND return_no=?', [$book['id'], $returnNo]))
                    $returnNo = \App\Services\Integration\OrderService::nextReturnNo((int)$book['id']);   // never two returns with the same number

                Database::run(
                    'INSERT INTO returns
                        (book_id,invoice_id,type,return_no,date,customer_id,supplier_id,
                         subtotal,discount,delivery_charge,total_refund,remarks,status,created_by,created_at)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
                    [$book['id'], $invoiceId, $type, $returnNo, $date, $customerId, $supplierId,
                     $subtotal, $discount, $delivery, $totalRefund, $remarks ?: null, 'completed', auth()['id'], now()]
                );
                $returnId = (int)Database::lastId();

                // goods: a sales return puts stock back, a purchase return takes it out (and refuses to go below what is on hand)
                $old = []; $new = [];
                foreach ($items as $it) {
                    Database::run('INSERT INTO return_items (return_id,product_id,description,qty,unit_price,line_total) VALUES (?,?,?,?,?,?)',
                        [$returnId, $it['pid'], $it['name'], $it['qty'], $it['price'], $it['line']]);
                    if ($it['pid']) { if ($type === 'sales_return') $old[$it['pid']] = ($old[$it['pid']] ?? 0) + $it['qty']; else $new[$it['pid']] = ($new[$it['pid']] ?? 0) + $it['qty']; }
                }
                foreach ($old as $pid => $q) \App\Services\InventoryService::receive((int)$book['id'], $pid, $q);
                foreach ($new as $pid => $q) {
                    if (\App\Services\InventoryService::onHand((int)$book['id'], $pid) + 1e-9 < $q) throw new InsufficientStockException($pid, \App\Services\InventoryService::onHand((int)$book['id'], $pid), $q);
                    \App\Services\InventoryService::remove((int)$book['id'], $pid, $q);
                }

                // money: first cancel what is still owed on the invoice, pay out only the rest
                $split = LedgerService::creditReturn($invoiceId, $returnId, $returnNo, $totalRefund, auth()['id']);
                Database::run('UPDATE returns SET due_adjustment=?, cash_refund=? WHERE id=?', [$split['due_adjustment'], $split['cash_refund'], $returnId]);

                // cash-flow rows: only the cash that actually moves (the credited part just reduces the due/debt)
                $this->writeReportRows($book, $type, $returnId, $returnNo, $date, $split['cash_refund'], $discount);
                return $returnId;
            });
        } catch (InsufficientStockException $e) {
            $n = Database::row('SELECT name FROM products WHERE id=?', [$e->productId])['name'] ?? 'A product';
            redirect($back, ['error' => "Not enough stock of “{$n}” to send back: {$e->have} on hand, {$e->need} to return. Nothing was saved."]);
        } catch (\DomainException $e) {
            redirect($back, ['error' => $e->getMessage()]);
        }

        $r = Database::row('SELECT * FROM returns WHERE id=?', [$returnId]);
        ActivityLogger::write($book['id'], auth()['id'], 'return.created', 'Return', $returnId,
            "Return recorded — {$r['return_no']} — " . ($type === 'sales_return' ? 'Sales Return' : 'Purchase Return') . " — {$totalRefund}",
            null, ['return_no'=>$r['return_no'],'type'=>$type,'total_refund'=>$totalRefund,'credited'=>$r['due_adjustment'],'cash'=>$r['cash_refund']]);

        \App\Services\Integration\Hooks::returnCreated($returnId);
        $msg = 'Return '.$r['return_no'].' recorded.';
        if ((float)$r['due_adjustment'] > 0) $msg .= ' '.format_money((float)$r['due_adjustment']).' was taken off what was still owed on the invoice'.((float)$r['cash_refund'] > 0 ? ', '.format_money((float)$r['cash_refund']).' is paid out.' : '.');
        redirect('/books/'.$book['id'].'/returns', ['success' => $msg]);
    }

    /** Cannot return more than was sold/bought (counting earlier returns of the same invoice). */
    private function checkQuantities(array $inv, string $type, array $items): void
    {
        $key = fn($pid, $desc) => $pid ? 'p' . $pid : 'd' . mb_strtolower(trim($desc));
        $bought = [];
        foreach (Database::query('SELECT product_id, description, qty FROM invoice_items WHERE invoice_id=?', [$inv['id']]) as $r) $bought[$key($r['product_id'], $r['description'])] = ($bought[$key($r['product_id'], $r['description'])] ?? 0) + (float)$r['qty'];
        $given = [];
        foreach (Database::query("SELECT ri.product_id, ri.description, ri.qty FROM return_items ri JOIN returns r ON r.id=ri.return_id WHERE r.invoice_id=? AND r.type=? AND r.deleted_at IS NULL", [$inv['id'], $type]) as $r) $given[$key($r['product_id'], $r['description'])] = ($given[$key($r['product_id'], $r['description'])] ?? 0) + (float)$r['qty'];
        $asking = [];
        foreach ($items as $it) {
            $k = $key($it['pid'], $it['name']);
            if (!isset($bought[$k])) throw new \DomainException("“{$it['name']}” is not on invoice {$inv['invoice_no']}.");
            $asking[$k] = ($asking[$k] ?? 0) + $it['qty'];
            $left = $bought[$k] - ($given[$k] ?? 0);
            if ($asking[$k] > $left + 0.0005) throw new \DomainException("“{$it['name']}”: only " . rtrim(rtrim(number_format($left, 3, '.', ''), '0'), '.') . ' left to return (' . rtrim(rtrim(number_format($bought[$k], 3, '.', ''), '0'), '.') . ' on the invoice, ' . rtrim(rtrim(number_format($given[$k] ?? 0, 3, '.', ''), '0'), '.') . ' already returned).');
        }
    }

    private function writeReportRows(array $book, string $type, int $returnId, string $returnNo, string $date, float $cash, float $discount): void
    {
        Database::run('DELETE FROM report_entries WHERE source_table="returns" AND source_id=?', [$returnId]);
        if ($cash > 0) {
            Database::run('INSERT INTO report_entries (book_id,type,category,amount,description,source_table,source_id,date,created_at) VALUES (?,?,?,?,?,?,?,?,?)',
                [$book['id'], $type === 'sales_return' ? 'out' : 'in', $type, $cash,
                 ($type === 'sales_return' ? 'Sales return refund' : 'Purchase return recovery') . ' — ' . $returnNo, 'returns', $returnId, $date, now()]);
        }
        if ($discount > 0) {
            Database::run('INSERT INTO report_entries (book_id,type,category,amount,description,source_table,source_id,date,created_at) VALUES (?,?,?,?,?,?,?,?,?)',
                [$book['id'], $type === 'sales_return' ? 'in' : 'out', $type === 'sales_return' ? 'return_discount_kept' : 'return_loss', $discount,
                 ($type === 'sales_return' ? 'Non-refunded amount kept from sales return ' : 'Non-recovered amount on purchase return ') . $returnNo, 'returns', $returnId, $date, now()]);
        }
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

        try {
            Database::transaction(function () use ($return, $book) {
                $r = Database::row('SELECT * FROM returns WHERE id=? AND deleted_at IS NULL FOR UPDATE', [$return['id']]);
                if (!$r) throw new \DomainException('This return was already deleted.');
                // undo goods: a deleted sales return takes the goods back out; a deleted purchase return puts them back
                \App\Services\Integration\Hooks::returnDeleting($r);
                LedgerService::reverseReturnCredit((int)$r['id']);                 // the credit on the invoice/due/debt is given back
                LedgerService::dropOwnedExpenses((int)$book['id'], 'returns', (int)$r['id']);   // (old versions booked delivery as an expense)
                Database::run('DELETE FROM report_entries WHERE source_table="returns" AND source_id=?', [$r['id']]);
                Database::run('UPDATE returns SET deleted_at=? WHERE id=?', [now(), $r['id']]);
            });
        } catch (\DomainException $e) {
            redirect('/books/'.$book['id'].'/returns', ['error' => $e->getMessage()]);
        } catch (\Throwable $e) {
            error_log('[return delete] ' . $e->getMessage() . ' @' . $e->getFile() . ':' . $e->getLine());
            redirect('/books/'.$book['id'].'/returns/'.$return['id'], ['error' => 'The return could not be deleted and nothing was changed: ' . $e->getMessage()]);
        }

        ActivityLogger::write($book['id'], auth()['id'], 'return.deleted', 'Return', (int)$return['id'],
            "Return deleted — " . ($return['return_no'] ?? '#'.$return['id']) . " — {$return['total_refund']}",
            ['return_no'=>$return['return_no'],'type'=>$return['type'],'total_refund'=>$return['total_refund']]);
        redirect('/books/'.$book['id'].'/returns', ['success' => 'Return deleted — stock and the invoice balance are back as they were.']);
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
