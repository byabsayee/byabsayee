<?php
namespace App\Controllers;
use App\Helpers\Database;
use App\Services\ActivityLogger;
use App\Services\LedgerService;
use App\Services\InsufficientStockException;
use App\Services\Integration\Hooks;

// Forward declarations so static methods work without full autoload paths
// (these classes live in the same namespace)

class InvoiceController
{
    // ── Sales page (type=sale only) ──────────────────────────────────────────
    public function salesIndex(array $params): void
    {
        if (guest()) redirect('/login');
        $book      = $this->getBookOrFail($params['id']);
        if (!book_can($book, 'invoices', 'view')) abort_403();
        $status    = $_GET['status'] ?? 'all';
        $month     = $_GET['month']  ?? date('Y-m');
        $dateFrom  = $month . '-01';
        $dateTo    = date('Y-m-t', strtotime($dateFrom));
        $prevMonth = date('Y-m', strtotime($dateFrom . ' -1 month'));
        $nextMonth = date('Y-m', strtotime($dateFrom . ' +1 month'));
        $isCurrent = ($month === date('Y-m'));

        $sql = 'SELECT i.*, c.name AS customer_name
                FROM invoices i
                LEFT JOIN customers c ON i.customer_id=c.id
                WHERE i.book_id=? AND i.type="sale" AND i.deleted_at IS NULL
                  AND i.date BETWEEN ? AND ?';
        $p = [$book['id'], $dateFrom, $dateTo];
        if ($status !== 'all') { $sql .= ' AND i.status=?'; $p[] = $status; }
        $sql .= ' ORDER BY i.date DESC, i.id DESC';
        try { $invoices = Database::query($sql, $p); } catch (\Throwable $e) { $invoices = []; }

        try {
            $summary = Database::row(
                "SELECT
                    COALESCE(SUM(CASE WHEN status<>'cancelled' THEN total END),0) AS total_sales,
                    COALESCE(SUM(CASE WHEN status<>'cancelled' THEN paid END),0)  AS collected,
                    COALESCE(SUM(CASE WHEN status NOT IN ('paid','cancelled') THEN (total-paid) ELSE 0 END),0) AS due,
                    COUNT(*) AS count
                 FROM invoices WHERE book_id=? AND type='sale' AND deleted_at IS NULL
                   AND date BETWEEN ? AND ?",
                [$book['id'], $dateFrom, $dateTo]
            );
        } catch (\Throwable $e) {
            $summary = ['total_sales'=>0,'collected'=>0,'due'=>0,'count'=>0];
        }

        require BASE_PATH . '/views/business/sales/index.php';
    }

    // ── Purchases page (type=purchase only) ──────────────────────────────────
    public function purchasesIndex(array $params): void
    {
        if (guest()) redirect('/login');
        $book      = $this->getBookOrFail($params['id']);
        if (!book_can($book, 'invoices', 'view')) abort_403();
        $status    = $_GET['status'] ?? 'all';
        $month     = $_GET['month']  ?? date('Y-m');
        $dateFrom  = $month . '-01';
        $dateTo    = date('Y-m-t', strtotime($dateFrom));
        $prevMonth = date('Y-m', strtotime($dateFrom . ' -1 month'));
        $nextMonth = date('Y-m', strtotime($dateFrom . ' +1 month'));
        $isCurrent = ($month === date('Y-m'));

        $sql = 'SELECT i.*, s.name AS supplier_name
                FROM invoices i
                LEFT JOIN suppliers s ON i.supplier_id=s.id
                WHERE i.book_id=? AND i.type="purchase" AND i.deleted_at IS NULL
                  AND i.date BETWEEN ? AND ?';
        $p = [$book['id'], $dateFrom, $dateTo];
        if ($status !== 'all') { $sql .= ' AND i.status=?'; $p[] = $status; }
        $sql .= ' ORDER BY i.date DESC, i.id DESC';
        try { $invoices = Database::query($sql, $p); } catch (\Throwable $e) { $invoices = []; }

        try {
            $summary = Database::row(
                "SELECT
                    COALESCE(SUM(CASE WHEN status<>'cancelled' THEN total END),0) AS total_purchases,
                    COALESCE(SUM(CASE WHEN status<>'cancelled' THEN paid END),0)  AS paid,
                    COALESCE(SUM(CASE WHEN status NOT IN ('paid','cancelled') THEN (total-paid) ELSE 0 END),0) AS due,
                    COUNT(*) AS count
                 FROM invoices WHERE book_id=? AND type='purchase' AND deleted_at IS NULL
                   AND date BETWEEN ? AND ?",
                [$book['id'], $dateFrom, $dateTo]
            );
        } catch (\Throwable $e) {
            $summary = ['total_purchases'=>0,'paid'=>0,'due'=>0,'count'=>0];
        }

        require BASE_PATH . '/views/business/purchases/index.php';
    }

    public function index(array $params): void
    {
        if (guest()) redirect('/login');
        $book   = $this->getBookOrFail($params['id']);
        if (!book_can($book, 'invoices', 'view')) abort_403();
        // Fetch returns for the combined page
        $returnsForMonth = [];
        $returnsSummary  = ['sales_refunds'=>0,'purchase_refunds'=>0,'total_count'=>0];
        $type   = $_GET['type']   ?? 'all';
        $status = $_GET['status'] ?? 'all';
        $month     = $_GET['month'] ?? date('Y-m');
        $dateFrom  = $month . '-01';
        $dateTo    = date('Y-m-t', strtotime($dateFrom));
        $prevMonth = date('Y-m', strtotime($dateFrom . ' -1 month'));
        $nextMonth = date('Y-m', strtotime($dateFrom . ' +1 month'));
        $isCurrent = ($month === date('Y-m'));

        try {
            $sql = 'SELECT i.*, c.name AS customer_name, s.name AS supplier_name
                    FROM invoices i
                    LEFT JOIN customers c ON i.customer_id=c.id
                    LEFT JOIN suppliers s ON i.supplier_id=s.id
                    WHERE i.book_id=? AND i.deleted_at IS NULL
                      AND i.date BETWEEN ? AND ?';
            $p = [$book['id'], $dateFrom, $dateTo];
            if ($type   !== 'all') { $sql .= ' AND i.type=?';   $p[] = $type;   }
            if ($status !== 'all') { $sql .= ' AND i.status=?'; $p[] = $status; }
            $sql .= ' ORDER BY i.date DESC, i.id DESC';
            $invoices = Database::query($sql, $p);
        } catch (\Throwable $e) {
            error_log('InvoiceController::index invoices: ' . $e->getMessage());
            $invoices = [];
        }

        try {
            $summary = Database::row(
                "SELECT
                    COALESCE(SUM(CASE WHEN type IN ('sale','pos') AND status<>'cancelled' THEN total ELSE 0 END),0) AS total_sales,
                    COALESCE(SUM(CASE WHEN type IN ('sale','pos') AND status<>'cancelled' THEN paid  ELSE 0 END),0) AS collected,
                    COALESCE(SUM(CASE WHEN type IN ('sale','pos') AND status NOT IN ('paid','cancelled') THEN (total-paid) ELSE 0 END),0) AS due,
                    COALESCE(SUM(CASE WHEN type='purchase' AND status<>'cancelled' THEN total ELSE 0 END),0) AS total_purchases
                 FROM invoices WHERE book_id=? AND deleted_at IS NULL
                   AND date BETWEEN ? AND ?",
                [$book['id'], $dateFrom, $dateTo]
            );
        } catch (\Throwable $e) {
            error_log('InvoiceController::index summary: ' . $e->getMessage());
            $summary = ['total_sales'=>0,'collected'=>0,'due'=>0,'total_purchases'=>0];
        }

        // Fetch returns for the month
        try {
            $returnsForMonth = Database::query(
                'SELECT r.*, c.name AS customer_name, s.name AS supplier_name, i.invoice_no AS orig_invoice_no
                 FROM returns r
                 LEFT JOIN customers c ON r.customer_id=c.id
                 LEFT JOIN suppliers s ON r.supplier_id=s.id
                 LEFT JOIN invoices  i ON r.invoice_id=i.id
                 WHERE r.book_id=? AND r.deleted_at IS NULL AND r.date BETWEEN ? AND ?
                 ORDER BY r.date DESC, r.id DESC',
                [$book['id'], $dateFrom, $dateTo]
            );
        } catch (\Throwable $e) { $returnsForMonth = []; }

        try {
            $returnsSummary = Database::row(
                "SELECT COALESCE(SUM(CASE WHEN type='sales_return' THEN total_refund ELSE 0 END),0) AS sales_refunds,
                        COALESCE(SUM(CASE WHEN type='purchase_return' THEN total_refund ELSE 0 END),0) AS purchase_refunds,
                        COUNT(*) AS total_count
                 FROM returns WHERE book_id=? AND deleted_at IS NULL AND date BETWEEN ? AND ?",
                [$book['id'], $dateFrom, $dateTo]
            );
        } catch (\Throwable $e) { $returnsSummary = ['sales_refunds'=>0,'purchase_refunds'=>0,'total_count'=>0]; }

        require BASE_PATH . '/views/business/invoices/index.php';
    }

    public function create(array $params): void
    {
        if (guest()) redirect('/login');
        $book = $this->getBookOrFail($params['id']);
        if (!book_can($book, 'invoices', 'create')) abort_403();
        $type = ($_GET['type'] ?? 'sale') === 'purchase' ? 'purchase' : 'sale';
        $this->renderForm($book, $type, null, []);
    }

    /** Edit an invoice — GET /books/{id}/invoices/{invoice_id}/edit */
    public function edit(array $params): void
    {
        if (guest()) redirect('/login');
        $book = $this->getBookOrFail($params['id']);
        if (!book_can($book, 'invoices', 'edit')) abort_403();
        $invoice = $this->getInvoiceOrFail($params['invoice_id'], $book['id']);
        $url = '/books/'.$book['id'].'/invoices/'.$invoice['id'];
        if ($why = $this->lockedReason($invoice)) redirect($url, ['error' => $why]);
        $items = Database::query('SELECT * FROM invoice_items WHERE invoice_id=? ORDER BY id', [$invoice['id']]);
        $this->renderForm($book, $invoice['type'], $invoice, $items);
    }

    /** Why an invoice cannot be edited here (null = it can). */
    private function lockedReason(array $inv): ?string
    {
        if (!in_array($inv['type'], ['sale', 'purchase'], true)) return 'POS sales cannot be edited — record a return instead.';
        if ($inv['status'] === 'cancelled') return 'A cancelled invoice cannot be edited.';
        if (($inv['source'] ?? null) === 'online_store' || !empty($inv['sync_to_store'])) return 'Online-store orders are managed from the Online Orders screen, so they cannot be edited here.';
        return null;
    }

    private function renderForm(array $book, string $type, ?array $invoice, array $editItems): void
    {
        $isEdit = $invoice !== null;
        try {
            $customers = Database::query(
                'SELECT c.id, c.name, c.phone, c.points,
                        GROUP_CONCAT(cp.id ORDER BY cp.id SEPARATOR ",") AS privilege_ids,
                        GROUP_CONCAT(cp.name ORDER BY cp.id SEPARATOR "||") AS privilege_names,
                        GROUP_CONCAT(cp.discount_type ORDER BY cp.id SEPARATOR ",") AS discount_types,
                        GROUP_CONCAT(cp.discount_value ORDER BY cp.id SEPARATOR ",") AS discount_values
                 FROM customers c
                 LEFT JOIN customer_privilege_assignments cpa ON cpa.customer_id = c.id
                 LEFT JOIN customer_privileges cp ON cp.id = cpa.privilege_id
                 WHERE c.book_id=? AND c.deleted_at IS NULL
                 GROUP BY c.id ORDER BY c.name',
                [$book['id']]
            );
        } catch (\Throwable $e) {
            $customers = Database::query(
                'SELECT id,name,phone,points FROM customers WHERE book_id=? AND deleted_at IS NULL ORDER BY name',
                [$book['id']]
            );
        }
        $suppliers       = Database::query('SELECT id,name,company FROM suppliers WHERE book_id=? AND deleted_at IS NULL ORDER BY name', [$book['id']]);
        $details         = Database::row('SELECT * FROM book_business_details WHERE book_id=?', [$book['id']]);
        $deliveryMethods = Database::query('SELECT * FROM invoice_method_options WHERE book_id=? AND type="delivery" ORDER BY sort_order', [$book['id']]);
        $paymentMethods  = Database::query('SELECT * FROM invoice_method_options WHERE book_id=? AND type="payment" AND is_active=1  ORDER BY sort_order', [$book['id']]);
        $currencies      = Database::query('SELECT * FROM book_currencies WHERE book_id=? ORDER BY is_default DESC, sort_order', [$book['id']]);

        $inventoryMethod = $details['inventory_method'] ?? 'FIFO';
        $rawProducts = Database::query(
            'SELECT id,name,sell_price,buy_price,stock_qty,unit,product_code,sku,barcode
             FROM products WHERE book_id=? AND deleted_at IS NULL ORDER BY name',
            [$book['id']]
        );
        $products = [];
        foreach ($rawProducts as $p) {
            $batchOrder = ($inventoryMethod === 'FIFO') ? 'ASC' : 'DESC';
            try {
                $batches = Database::query(
                    "SELECT * FROM product_batches WHERE product_id=? AND remaining_qty>0 ORDER BY created_at {$batchOrder}",
                    [$p['id']]
                );
            } catch (\Throwable $e) { $batches = []; }
            $p['batches'] = $batches;
            $products[] = $p;
        }

        if ($type === 'purchase') {
            $prefix  = $details['invoice_prefix_purchase'] ?? 'PUR';
            $counter = $details['invoice_counter_purchase'] ?? 1;
        } else {
            $prefix  = $details['invoice_prefix'] ?? 'INV';
            $counter = $details['invoice_counter'] ?? 1;
        }
        $invoiceNo = $isEdit ? $invoice['invoice_no'] : $prefix . '-' . str_pad($counter, 6, '0', STR_PAD_LEFT);

        $defaultCurrency = ['symbol' => '৳', 'code' => 'BDT'];
        foreach ($currencies as $c) { if ($c['is_default']) { $defaultCurrency = $c; break; } }


        if ($isEdit) {
            // What this invoice already holds is available to it again: its own points back to the customer, its own units back on the shelf.
            foreach ($customers as &$cu) { if ((int)$cu['id'] === (int)$invoice['customer_id']) $cu['points'] = (int)$cu['points'] + LedgerService::spentPoints($invoice); }
            unset($cu);
            if ($type === 'sale') {
                $had = LedgerService::qtyByProduct(array_map(fn($i) => ['pid' => $i['product_id'], 'qty' => $i['qty']], $editItems));
                foreach ($products as &$pr) { if (isset($had[(int)$pr['id']])) $pr['stock_qty'] = (float)$pr['stock_qty'] + $had[(int)$pr['id']]; }
                unset($pr);
            }
        }

        require BASE_PATH . '/views/business/invoices/create.php';
    }

    public function store(array $params): void
    {
        if (guest()) redirect('/login');
        csrf_verify();
        $book = $this->getBookOrFail($params['id']);
        if (!book_can($book, 'invoices', 'create')) abort_403();
        $type = ($_POST['type'] ?? 'sale') === 'purchase' ? 'purchase' : 'sale';
        $back = '/books/'.$book['id'].'/invoices/create?type='.$type;
        $moves = [];

        try {
            $invoiceId = Database::transaction(function () use ($book, $type, &$moves, &$f) {
                $f = $this->collect($book, $type, null);

                // Online-store sync: a sale can be flagged to appear as an order on the website (it needs a customer with a phone).
                $syncToStore = $type === 'sale' && !empty($_POST['sync_to_store']) && Hooks::active((int)$book['id']);
                if ($syncToStore && (!$f['customer_id'] || !Hooks::customerCanOrder($f['customer_id'])))
                    throw new \DomainException('To send this sale to the online store, choose a customer who has a phone number.');

                $this->resolvePurchaseProducts($book, $type, $f['items']);
                $moves = LedgerService::applyStockDiff((int)$book['id'], $type, [], LedgerService::qtyByProduct($f['items']), $this->buyPrices($f['items']));
                $this->spendPoints(null, $f);

                Database::run(
                    'INSERT INTO invoices
                        (book_id,type,invoice_no,customer_id,supplier_id,date,due_date,
                         subtotal,discount,points_discount,coupon_code,coupon_discount,privilege_discount,
                         delivery_charge,handling_charge,delivery_type,rounding,tax,
                         total,paid,status,note_customer,note_seller,
                         delivery_method,payment_method,theme_color,currency_symbol,currency_code,
                         public_token,created_by,created_at)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,0,?,?,?,?,?,?,?,?,?,?,?)',
                    [
                        $book['id'],$type,$f['invoice_no'],$f['customer_id'],$f['supplier_id'],$f['date'],$f['due_date'],
                        $f['subtotal'],$f['discount'],$f['points_discount'],$f['coupon_code'] ?: null,$f['coupon_discount'],$f['privilege_discount'],
                        $f['delivery_charge'],$f['handling_charge'],$f['delivery_type'],$f['rounding'],$f['tax'],
                        $f['total'],'draft',
                        $f['note_customer'] ?: null,$f['note_seller'] ?: null,
                        $f['delivery_method'] ?: null,$f['payment_method'] ?: null,
                        $book['theme_color'] ?? '#1a6b4a',$f['currency_symbol'],$f['currency_code'],
                        bin2hex(random_bytes(20)),auth()['id'],now()
                    ]
                );
                $invoiceId = (int)Database::lastId();
                $this->insertItems($invoiceId, $f['items']);

                $counterCol = $type === 'purchase' ? 'invoice_counter_purchase' : 'invoice_counter';
                Database::run("UPDATE book_business_details SET {$counterCol}={$counterCol}+1 WHERE book_id=?", [$book['id']]);
                if ($syncToStore) Database::run('UPDATE invoices SET sync_to_store=1 WHERE id=?', [$invoiceId]);

                $inv = LedgerService::refreshInvoice($invoiceId);        // creates the due (sale+customer) / debt (purchase+supplier)
                LedgerService::syncInvoiceDeliveryExpense($inv, auth()['id']);
                LedgerService::syncReportEntry($inv);
                return $invoiceId;
            });
        } catch (InsufficientStockException $e) {
            redirect($back, ['error' => $this->stockMessage($e)]);
        } catch (\DomainException $e) {
            redirect($back, ['error' => $e->getMessage()]);
        }

        if ($type === 'purchase' && !empty($_FILES['attachment']['name']) && $_FILES['attachment']['error'] === 0) {
            $this->saveAttachment($invoiceId, $_FILES['attachment']);
        }
        Hooks::invoiceCreated($invoiceId);

        ActivityLogger::write($book['id'], auth()['id'], 'invoice.created', 'Invoice', $invoiceId,
            "Invoice created — {$f['invoice_no']} — " . ucfirst($type),
            null, ['invoice_no'=>$f['invoice_no'],'type'=>$type,'total'=>$f['total']]);

        redirect('/books/'.$book['id'].'/invoices/'.$invoiceId, ['success' => 'Invoice '.$f['invoice_no'].' created.']);
    }

    /** Save an edited invoice — POST /books/{id}/invoices/{invoice_id}/edit */
    public function update(array $params): void
    {
        if (guest()) redirect('/login');
        csrf_verify();
        $book = $this->getBookOrFail($params['id']);
        if (!book_can($book, 'invoices', 'edit')) abort_403();
        $invoice = $this->getInvoiceOrFail($params['invoice_id'], $book['id']);
        $url = '/books/'.$book['id'].'/invoices/'.$invoice['id'];
        if ($why = $this->lockedReason($invoice)) redirect($url, ['error' => $why]);
        $moves = [];

        try {
            Database::transaction(function () use ($book, $invoice, &$moves, &$f, &$old) {
                $old = Database::row('SELECT * FROM invoices WHERE id=? AND deleted_at IS NULL FOR UPDATE', [$invoice['id']]);
                if (!$old || $this->lockedReason($old)) throw new \DomainException('This invoice can no longer be edited.');
                $type = $old['type'];
                $f = $this->collect($book, $type, $old);

                if ($f['total'] + 0.004 < (float)$old['paid'])
                    throw new \DomainException('The new total ('.number_format($f['total'], 2).') is less than the '.number_format((float)$old['paid'], 2).' already paid on this invoice. Refund or return instead of lowering it.');

                $oldItems = Database::query('SELECT product_id AS pid, qty, description FROM invoice_items WHERE invoice_id=?', [$old['id']]);
                $this->resolvePurchaseProducts($book, $type, $f['items']);
                $newQty = LedgerService::qtyByProduct($f['items']);

                // you cannot drop below what was already given back through returns
                foreach (LedgerService::returnedQty((int)$old['id'], $type === 'sale' ? 'sales_return' : 'purchase_return') as $pid => $q) {
                    if (($newQty[$pid] ?? 0) + 0.0005 < $q) {
                        $n = Database::row('SELECT name FROM products WHERE id=?', [$pid])['name'] ?? 'a product';
                        throw new \DomainException("“{$n}”: {$q} already went back through a return, so the invoice cannot list fewer than that.");
                    }
                }

                $moves = LedgerService::applyStockDiff((int)$book['id'], $type, LedgerService::qtyByProduct($oldItems), $newQty, $this->buyPrices($f['items']));
                $this->spendPoints($old, $f);
                LedgerService::moveEarnedPoints($old, $f['customer_id']);

                Database::run('DELETE FROM invoice_items WHERE invoice_id=?', [$old['id']]);
                $this->insertItems((int)$old['id'], $f['items']);

                Database::run(
                    'UPDATE invoices SET invoice_no=?,customer_id=?,supplier_id=?,date=?,due_date=?,
                        subtotal=?,discount=?,points_discount=?,coupon_code=?,coupon_discount=?,privilege_discount=?,
                        delivery_charge=?,handling_charge=?,delivery_type=?,rounding=?,tax=?,total=?,
                        note_customer=?,note_seller=?,delivery_method=?,payment_method=?,currency_symbol=?,currency_code=?
                     WHERE id=?',
                    [$f['invoice_no'],$f['customer_id'],$f['supplier_id'],$f['date'],$f['due_date'],
                     $f['subtotal'],$f['discount'],$f['points_discount'],$f['coupon_code'] ?: null,$f['coupon_discount'],$f['privilege_discount'],
                     $f['delivery_charge'],$f['handling_charge'],$f['delivery_type'],$f['rounding'],$f['tax'],$f['total'],
                     $f['note_customer'] ?: null,$f['note_seller'] ?: null,$f['delivery_method'] ?: null,$f['payment_method'] ?: null,
                     $f['currency_symbol'],$f['currency_code'],$old['id']]
                );

                $inv = LedgerService::refreshInvoice((int)$old['id']);   // status, due/debt mirror, loyalty points
                LedgerService::syncInvoiceDeliveryExpense($inv, auth()['id']);
                LedgerService::syncReportEntry($inv);
            });
        } catch (InsufficientStockException $e) {
            redirect($url.'/edit', ['error' => $this->stockMessage($e)]);
        } catch (\DomainException $e) {
            redirect($url.'/edit', ['error' => $e->getMessage()]);
        }

        if ($invoice['type'] === 'purchase' && !empty($_FILES['attachment']['name']) && $_FILES['attachment']['error'] === 0) {
            $this->saveAttachment((int)$invoice['id'], $_FILES['attachment']);
        }
        foreach ($moves as $pid => $delta) Hooks::stock((int)$book['id'], (int)$pid, (float)$delta, $invoice['type'] === 'sale' ? 'manual_adjustment' : 'purchase', 'Edited ' . $f['invoice_no']);

        ActivityLogger::write($book['id'], auth()['id'], 'invoice.updated', 'Invoice', (int)$invoice['id'],
            "Invoice edited — {$f['invoice_no']} — total {$old['total']} → {$f['total']}",
            ['total'=>$old['total'],'customer_id'=>$old['customer_id'],'supplier_id'=>$old['supplier_id']],
            ['total'=>$f['total'],'customer_id'=>$f['customer_id'],'supplier_id'=>$f['supplier_id']]);

        redirect($url, ['success' => 'Invoice '.$f['invoice_no'].' updated — stock, dues, points and reports follow.']);
    }

    // ── form → validated values (shared by create and edit) ──────────────────
    /** @throws \DomainException with a message meant for the person using the form */
    private function collect(array $book, string $type, ?array $existing): array
    {
        $isSale     = $type === 'sale';
        $bookId     = (int)$book['id'];
        $exceptId   = $existing ? (int)$existing['id'] : null;
        $customerId = $isSale && !empty($_POST['customer_id']) ? (int)$_POST['customer_id'] : null;
        $supplierId = !$isSale && !empty($_POST['supplier_id']) ? (int)$_POST['supplier_id'] : null;
        if ($customerId && !Database::row('SELECT id FROM customers WHERE id=? AND book_id=? AND deleted_at IS NULL', [$customerId, $bookId])) throw new \DomainException('That customer no longer exists.');
        if ($supplierId && !Database::row('SELECT id FROM suppliers WHERE id=? AND book_id=? AND deleted_at IS NULL', [$supplierId, $bookId])) throw new \DomainException('That supplier no longer exists.');

        $invoiceNo = trim($_POST['invoice_no'] ?? '');
        if ($invoiceNo === '') throw new \DomainException('An invoice number is required.');
        $dupSql = 'SELECT id FROM invoices WHERE book_id=? AND invoice_no=? AND deleted_at IS NULL' . ($exceptId ? ' AND id<>?' : '');
        if (Database::row($dupSql, $exceptId ? [$bookId, $invoiceNo, $exceptId] : [$bookId, $invoiceNo])) throw new \DomainException("Invoice number {$invoiceNo} is already used — pick another.");

        $okDate = fn($d) => is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d) !== false;
        $date    = $okDate($_POST['date'] ?? null) ? $_POST['date'] : date('Y-m-d');
        $dueDate = $okDate($_POST['due_date'] ?? null) ? $_POST['due_date'] : null;
        $nonNeg  = fn($k) => max(0.0, round((float)($_POST[$k] ?? 0), 2));

        // ── lines ──
        $names = $_POST['item_name'] ?? []; $qtys = $_POST['item_qty'] ?? []; $prices = $_POST['item_price'] ?? [];
        $discs = $_POST['item_discount'] ?? []; $pids = $_POST['item_product_id'] ?? []; $vars = $_POST['item_variant'] ?? [];
        $items = []; $subtotal = 0.0;
        foreach ($names as $i => $nm) {
            $nm = trim((string)$nm);
            if ($nm === '') continue;
            $qty = (float)($qtys[$i] ?? 0); $price = (float)($prices[$i] ?? 0); $disc = (float)($discs[$i] ?? 0);
            if ($qty <= 0)  throw new \DomainException("Quantity for “{$nm}” must be greater than zero.");
            if ($price < 0) throw new \DomainException("Price for “{$nm}” cannot be negative.");
            $disc = min(100, max(0, $disc));
            $line = round($qty * $price * (1 - $disc / 100), 2);
            $subtotal += $line;
            $items[] = ['itemName' => mb_substr($nm, 0, 255), 'qty' => $qty, 'price' => $price, 'discPct' => $disc, 'lineTot' => $line,
                        'pid' => !empty($pids[$i]) ? (int)$pids[$i] : null, 'variant' => trim((string)($vars[$i] ?? ''))];
        }
        if (!$items) throw new \DomainException('Add at least one item.');
        $subtotal = round($subtotal, 2);
        foreach ($items as $it) {
            if ($it['pid'] && !Database::row('SELECT id FROM products WHERE id=? AND book_id=?', [$it['pid'], $bookId])) throw new \DomainException("“{$it['itemName']}” points at a product that does not exist in this book.");
        }

        // ── discounts the server recomputes itself ──
        $privilege = 0.0;
        if ($customerId && $isSale) {
            foreach (Database::query('SELECT cp.* FROM customer_privilege_assignments cpa JOIN customer_privileges cp ON cp.id=cpa.privilege_id WHERE cpa.customer_id=?', [$customerId]) as $pv)
                $privilege += $pv['discount_type'] === 'percent' ? $subtotal * (float)$pv['discount_value'] / 100 : (float)$pv['discount_value'];
            $privilege = round(min($privilege, $subtotal), 2);
        }
        $couponCode = ''; $couponDisc = 0.0;
        $postedCoupon = strtoupper(trim($_POST['coupon_code'] ?? ''));
        if ($postedCoupon !== '' && $isSale) {
            $r = \App\Controllers\CouponController::check($bookId, $postedCoupon, $subtotal, $exceptId);
            if (isset($r['error'])) throw new \DomainException("Coupon “{$postedCoupon}”: " . $r['error']);
            $couponCode = $postedCoupon; $couponDisc = round((float)$r['discount'], 2);
        }
        $discount = $nonNeg('discount');
        $points   = ($customerId && $isSale) ? (float)(int)max(0, (float)($_POST['points_discount'] ?? 0)) : 0.0;
        $delivery = $nonNeg('delivery_charge'); $handling = $nonNeg('handling_charge'); $tax = $nonNeg('tax');
        if ($discount + $points + $couponDisc + $privilege > $subtotal + 0.004) throw new \DomainException('The discounts add up to more than the items are worth.');

        $t = LedgerService::totals($subtotal, $discount, $points, $couponDisc, $privilege, $delivery, $handling, $tax, !empty($_POST['rounding_enabled']));
        $deliveryType = ($_POST['delivery_type'] ?? 'own') === 'other' ? 'other' : 'own';

        return [
            'type' => $type, 'customer_id' => $customerId, 'supplier_id' => $supplierId, 'invoice_no' => mb_substr($invoiceNo, 0, 30),
            'date' => $date, 'due_date' => $dueDate,
            'note_customer' => trim($_POST['note_customer'] ?? ''), 'note_seller' => trim($_POST['note_seller'] ?? ''),
            'delivery_method' => trim($_POST['delivery_method'] ?? ''), 'payment_method' => trim($_POST['payment_method'] ?? ''),
            'currency_symbol' => trim($_POST['currency_symbol'] ?? '৳') ?: '৳', 'currency_code' => trim($_POST['currency_code'] ?? 'BDT') ?: 'BDT',
            'subtotal' => $subtotal, 'discount' => $discount, 'points_discount' => $points,
            'coupon_code' => $couponCode, 'coupon_discount' => $couponDisc, 'privilege_discount' => $privilege,
            'delivery_charge' => $delivery, 'handling_charge' => $handling, 'delivery_type' => $deliveryType, 'tax' => $tax,
            'rounding' => $t['rounding'], 'total' => $t['total'], 'items' => $items,
        ];
    }

    private function insertItems(int $invoiceId, array $items): void
    {
        foreach ($items as $it) {
            Database::run('INSERT INTO invoice_items (invoice_id,product_id,description,variant,qty,unit_price,discount_pct,line_total) VALUES (?,?,?,?,?,?,?,?)',
                [$invoiceId, $it['pid'], $it['itemName'], $it['variant'] ?: null, $it['qty'], $it['price'], $it['discPct'], $it['lineTot']]);
        }
    }

    /** Effective cost per unit of each purchased product (after the line discount), used for cost layers. */
    private function buyPrices(array $items): array
    {
        $out = [];
        foreach ($items as $it) if ($it['pid'] && $it['qty'] > 0) $out[$it['pid']] = round($it['lineTot'] / $it['qty'], 2);
        return $out;
    }

    /** A purchase line with no product picked becomes (or reuses) a product by name, so stock is never lost. Stock itself is added by applyStockDiff. */
    private function resolvePurchaseProducts(array $book, string $type, array &$items): void
    {
        if ($type !== 'purchase') return;
        foreach ($items as &$it) {
            if ($it['pid']) continue;
            $ex = Database::row('SELECT id FROM products WHERE book_id=? AND name=? AND deleted_at IS NULL ORDER BY id LIMIT 1', [$book['id'], $it['itemName']]);
            if ($ex) { $it['pid'] = (int)$ex['id']; continue; }
            Database::run('INSERT INTO products (book_id,name,unit,buy_price,sell_price,stock_qty,low_stock_alert,created_at) VALUES (?,?,?,?,0,0,5,?)',
                [$book['id'], $it['itemName'], 'pcs', $it['price'], now()]);
            $pid = (int)Database::lastId();
            Database::run('UPDATE products SET product_code=?, barcode=? WHERE id=?',
                ['PRD-' . str_pad((string)$pid, 5, '0', STR_PAD_LEFT), 'BC' . str_pad((string)$book['id'], 3, '0', STR_PAD_LEFT) . str_pad((string)$pid, 6, '0', STR_PAD_LEFT), $pid]);
            $it['pid'] = $pid;
        }
        unset($it);
    }

    /** Points a customer spends as a discount: refund the old spend (edit), then take the new one — refused when the balance is not there. */
    private function spendPoints(?array $old, array $f): void
    {
        if ($old && $old['customer_id'] && LedgerService::spentPoints($old) > 0)
            Database::run('UPDATE customers SET points=points+? WHERE id=?', [LedgerService::spentPoints($old), $old['customer_id']]);
        $want = (int)$f['points_discount'];
        if ($want > 0 && $f['customer_id']) {
            $have = (int)(Database::row('SELECT points FROM customers WHERE id=? FOR UPDATE', [$f['customer_id']])['points'] ?? 0);
            if ($want > $have) throw new \DomainException("The customer only has {$have} points to spend (you tried {$want}).");
            Database::run('UPDATE customers SET points=points-? WHERE id=?', [$want, $f['customer_id']]);
        }
    }

    private function stockMessage(InsufficientStockException $e): string
    {
        $n = Database::row('SELECT name FROM products WHERE id=?', [$e->productId])['name'] ?? 'A product';
        return "Not enough stock for “{$n}”: {$e->have} on hand, {$e->need} needed. Nothing was saved.";
    }

    private function saveAttachment(int $invoiceId, array $file): void
    {
        $allowed = ['pdf','jpg','jpeg','png','webp'];
        $ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed) || $file['size'] > 10*1024*1024) return;
        $dir = config('upload.path') . '/attachments';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        if (!is_writable($dir)) return;
        $filename = 'inv_'.$invoiceId.'_'.date('Ymd_His').'_'.bin2hex(random_bytes(4)).'.'.$ext;
        if (move_uploaded_file($file['tmp_name'], $dir.'/'.$filename)) {
            Database::run(
                'INSERT INTO invoice_attachments (invoice_id,filename,path,size,created_at) VALUES (?,?,?,?,?)',
                [$invoiceId,$file['name'],'attachments/'.$filename,$file['size'],now()]
            );
        }
    }

    public function show(array $params): void
    {
        if (guest()) redirect('/login');
        $book    = $this->getBookOrFail($params['id']);
        if (!book_can($book, 'invoices', 'view')) abort_403();
        $invoice = $this->getInvoiceOrFail($params['invoice_id'], $book['id']);
        $items   = Database::query('SELECT * FROM invoice_items WHERE invoice_id=?', [$invoice['id']]);
        $customer= $invoice['customer_id'] ? Database::row('SELECT * FROM customers WHERE id=?', [$invoice['customer_id']]) : null;
        $supplier= $invoice['supplier_id'] ? Database::row('SELECT * FROM suppliers WHERE id=?', [$invoice['supplier_id']]) : null;
        $details = Database::row('SELECT * FROM book_business_details WHERE book_id=?', [$book['id']]);
        require BASE_PATH . '/views/business/invoices/show.php';
    }

    public function pdf(array $params): void
    {
        if (guest()) redirect('/login');
        $book    = $this->getBookOrFail($params['id']);
        if (!book_can($book, 'invoices', 'view')) abort_403();
        $invoice = $this->getInvoiceOrFail($params['invoice_id'], $book['id']);
        $items   = Database::query('SELECT * FROM invoice_items WHERE invoice_id=?', [$invoice['id']]);
        $customer= $invoice['customer_id'] ? Database::row('SELECT * FROM customers WHERE id=?', [$invoice['customer_id']]) : null;
        $supplier= $invoice['supplier_id'] ? Database::row('SELECT * FROM suppliers WHERE id=?', [$invoice['supplier_id']]) : null;
        $details = Database::row('SELECT * FROM book_business_details WHERE book_id=?', [$book['id']]);
        // Shared variables for the print view
        $themeColor = $invoice['theme_color'] ?? $book['theme_color'] ?? '#1a6b4a';
        $bizName    = $details['business_name'] ?? $book['name'];
        $bizAddress = $details['address'] ?? $book['address'] ?? '';
        $bizPhone   = $details['phone']   ?? $book['phone']   ?? '';
        $bizEmail   = $details['email']   ?? $book['email']   ?? '';
        $isPublic   = false;
        require BASE_PATH . '/views/business/invoices/print.php';
    }

    public function thermal(array $params): void
    {
        if (guest()) redirect('/login');
        $book    = $this->getBookOrFail($params['id']);
        if (!book_can($book, 'invoices', 'view')) abort_403();
        $invoice = $this->getInvoiceOrFail($params['invoice_id'], $book['id']);
        $items   = Database::query('SELECT * FROM invoice_items WHERE invoice_id=?', [$invoice['id']]);
        $customer= $invoice['customer_id'] ? Database::row('SELECT * FROM customers WHERE id=?', [$invoice['customer_id']]) : null;
        $supplier= $invoice['supplier_id'] ? Database::row('SELECT * FROM suppliers WHERE id=?', [$invoice['supplier_id']]) : null;
        $details = Database::row('SELECT * FROM book_business_details WHERE book_id=?', [$book['id']]);
        $paperWidth = (int)($_GET['w'] ?? 80);
        $total      = (float)$invoice['total'];
        $curCode    = $invoice['currency_code'] ?? 'BDT';
        require BASE_PATH . '/views/business/invoices/thermal.php';
    }

    public function recordPayment(array $params): void
    {
        if (guest()) redirect('/login');
        csrf_verify();
        $book    = $this->getBookOrFail($params['id']);
        if (!book_can($book, 'invoices', 'record_payment')) abort_403();
        $invoice = $this->getInvoiceOrFail($params['invoice_id'], $book['id']);
        $url     = '/books/'.$book['id'].'/invoices/'.$invoice['id'];

        $amount = round((float)($_POST['amount'] ?? 0), 2);
        $method = trim($_POST['method'] ?? 'cash');
        $note   = trim($_POST['note']   ?? '');
        $date   = (isset($_POST['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['date'])) ? $_POST['date'] : null;
        if ($amount <= 0) redirect($url, ['error' => 'Enter an amount greater than zero.']);

        try {
            $paymentId = Database::transaction(fn() => LedgerService::addPayment((int)$invoice['id'], $amount, $method, $note, auth()['id'], 'invoice', $date));
        } catch (\RuntimeException $e) {
            redirect($url, ['error' => $e->getMessage()]);
        }
        Hooks::payment($paymentId);

        $fresh = Database::row('SELECT paid,status FROM invoices WHERE id=?', [$invoice['id']]);
        ActivityLogger::write($book['id'], auth()['id'], 'invoice.payment', 'Invoice', (int)$invoice['id'],
            "Payment recorded — {$invoice['invoice_no']} — {$amount} via {$method} (status: {$fresh['status']})",
            ['paid'=>$invoice['paid'],'status'=>$invoice['status']],
            ['paid'=>$fresh['paid'],'status'=>$fresh['status'],'payment'=>$amount]);

        redirect($url, ['success' => format_money($amount).' recorded.']);
    }

    public function markSent(array $params): void
    {
        if (guest()) redirect('/login');
        csrf_verify();
        $book    = $this->getBookOrFail($params['id']);
        if (!book_can($book, 'invoices', 'edit')) abort_403();
        $invoice = $this->getInvoiceOrFail($params['invoice_id'], $book['id']);
        Database::run('UPDATE invoices SET status="sent" WHERE id=? AND status="draft"', [$invoice['id']]);
        redirect('/books/'.$book['id'].'/invoices/'.$invoice['id'], ['success' => 'Marked as sent.']);
    }

    public function delete(array $params): void
    {
        if (guest()) redirect('/login');
        csrf_verify();
        $book    = $this->getBookOrFail($params['id']);
        if (!book_can($book, 'invoices', 'delete')) abort_403();
        $invoice = $this->getInvoiceOrFail($params['invoice_id'], $book['id']);
        $url     = '/books/'.$book['id'].'/invoices/'.$invoice['id'];

        // Everything the invoice did is undone together or not at all: stock, payments, due/debt, loyalty points (earned and
        // spent), the delivery expense and its report entry. A failure rolls the whole thing back and says so.
        try {
            $keepOnRecord = Database::transaction(function () use ($invoice, $book) {
                $inv = Database::row('SELECT * FROM invoices WHERE id=? AND deleted_at IS NULL FOR UPDATE', [$invoice['id']]);
                if (!$inv) throw new \DomainException('This invoice was already deleted.');
                $keep = Hooks::invoiceDeleting($inv);            // stock back, payments voided, due/debt cancelled, report entry removed
                Database::run("UPDATE payments SET status='void' WHERE invoice_id=? AND status='recorded'", [$inv['id']]);
                if ($inv['status'] !== 'cancelled' && $inv['customer_id'] && LedgerService::spentPoints($inv) > 0)
                    Database::run('UPDATE customers SET points=points+? WHERE id=?', [LedgerService::spentPoints($inv), $inv['customer_id']]);   // points spent as a discount go back
                LedgerService::dropOwnedExpenses((int)$book['id'], 'invoices', (int)$inv['id']);
                if ($keep) return true;
                Database::run('UPDATE invoices SET deleted_at=? WHERE id=?', [now(), $inv['id']]);
                $gone = Database::row('SELECT * FROM invoices WHERE id=?', [$inv['id']]);
                LedgerService::syncPoints((int)$inv['id']);       // takes back the points this invoice had earned
                LedgerService::mirrorSettlement($gone);
                return false;
            });
        } catch (\DomainException $e) {
            redirect('/books/'.$book['id'].'/invoices', ['error' => $e->getMessage()]);
        } catch (\Throwable $e) {
            error_log('[invoice delete] ' . $e->getMessage() . ' @' . $e->getFile() . ':' . $e->getLine());
            redirect($url, ['error' => 'The invoice could not be deleted, and nothing was changed: ' . $e->getMessage()]);
        }

        ActivityLogger::write($book['id'], auth()['id'], 'invoice.deleted', 'Invoice', (int)$invoice['id'],
            "Invoice deleted — {$invoice['invoice_no']} — " . ucfirst($invoice['type']) . " — {$invoice['total']}",
            ['invoice_no'=>$invoice['invoice_no'],'type'=>$invoice['type'],'total'=>$invoice['total']]);

        if ($keepOnRecord) redirect($url, ['success' => 'Online order cancelled. It stays on record, with its stock returned and payments voided.']);
        redirect('/books/'.$book['id'].'/invoices', ['success' => 'Invoice deleted.']);
    }

    public function uploadAttachment(array $params): void
    {
        if (guest()) redirect("/login");
        csrf_verify();
        $book    = $this->getBookOrFail($params["id"]);
        if (!book_can($book, 'invoices', 'edit')) abort_403();
        $invoice = $this->getInvoiceOrFail($params["invoice_id"], $book["id"]);
        if (!empty($_FILES["attachment"]["name"]) && $_FILES["attachment"]["error"] === 0) {
            $this->saveAttachment($invoice["id"], $_FILES["attachment"]);
            redirect("/books/".$book["id"]."/invoices/".$invoice["id"], ["success" => "Attachment uploaded."]);
        }
        redirect("/books/".$book["id"]."/invoices/".$invoice["id"], ["error" => "No file selected."]);
    }

        private function getBookOrFail(string $id): array
    {
        $book = book_for_user($id, 'business');
        if (!$book) { http_response_code(404); require BASE_PATH.'/views/errors/404.php'; exit; }
        return $book;
    }

    private function getInvoiceOrFail(string $iid, int $bookId): array
    {
        $inv = Database::row('SELECT * FROM invoices WHERE id=? AND book_id=? AND deleted_at IS NULL', [$iid,$bookId]);
        if (!$inv) { http_response_code(404); require BASE_PATH.'/views/errors/404.php'; exit; }
        return $inv;
    }
}
