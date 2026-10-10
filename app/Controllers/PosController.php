<?php
namespace App\Controllers;
use App\Helpers\Database;
use App\Services\LedgerService;
use App\Services\InsufficientStockException;

class PosController
{
    public function show(array $params): void
    {
        if (guest()) redirect('/login');
        $book = $this->getBookOrFail($params['id']);
        if (!book_can($book, 'invoices', 'create')) abort_403();

        $products       = Database::query('SELECT id,name,sell_price,stock_qty,unit,product_code FROM products WHERE book_id=? AND deleted_at IS NULL ORDER BY name', [$book['id']]);
        $customers      = Database::query('SELECT id,name,phone FROM customers WHERE book_id=? AND deleted_at IS NULL ORDER BY name', [$book['id']]);
        $paymentMethods = Database::query('SELECT * FROM invoice_method_options WHERE book_id=? AND type="payment" AND is_active=1 ORDER BY sort_order', [$book['id']]);
        $details        = Database::row('SELECT * FROM book_business_details WHERE book_id=?', [$book['id']]);
        $currencies     = Database::query('SELECT * FROM book_currencies WHERE book_id=? ORDER BY is_default DESC', [$book['id']]);
        $defaultCurrency= $currencies[0] ?? ['symbol'=>'৳','code'=>'BDT'];

        require BASE_PATH . '/views/business/invoices/pos.php';
    }

    public function store(array $params): void
    {
        if (guest()) redirect('/login');
        csrf_verify();
        $book = $this->getBookOrFail($params['id']);
        if (!book_can($book, 'invoices', 'create')) abort_403();
        $back = '/books/'.$book['id'].'/pos';

        $customerId     = !empty($_POST['customer_id']) ? (int)$_POST['customer_id'] : null;
        $paymentMethod  = trim($_POST['payment_method'] ?? 'Cash') ?: 'Cash';
        $discount       = max(0, round((float)($_POST['discount'] ?? 0), 2));
        $rounding       = max(0, round((float)($_POST['rounding']  ?? 0), 2));
        $currencySymbol = trim($_POST['currency_symbol'] ?? '৳') ?: '৳';
        $currencyCode   = trim($_POST['currency_code']   ?? 'BDT') ?: 'BDT';
        $noteCustomer   = trim($_POST['note_customer'] ?? '');

        $itemNames  = $_POST['item_name']       ?? [];
        $itemQtys   = $_POST['item_qty']        ?? [];
        $itemPrices = $_POST['item_price']      ?? [];
        $itemPids   = $_POST['item_product_id'] ?? [];

        $subtotal = 0.0; $items = [];
        foreach ($itemNames as $i => $itemName) {
            $itemName = trim($itemName);
            if ($itemName === '') continue;
            $qty = (float)($itemQtys[$i] ?? 1); $price = (float)($itemPrices[$i] ?? 0);
            if ($qty <= 0 || $price < 0) redirect($back, ['error' => "“{$itemName}”: quantity must be above zero and price cannot be negative."]);
            $line = round($qty * $price, 2);
            $subtotal += $line;
            $items[] = ['itemName' => $itemName, 'qty' => $qty, 'price' => $price, 'lineTot' => $line, 'pid' => !empty($itemPids[$i]) ? (int)$itemPids[$i] : null];
        }
        if (!$items) redirect($back, ['error' => 'Add at least one item.']);
        if ($discount + $rounding > $subtotal + 0.004) redirect($back, ['error' => 'The discount is more than the sale is worth.']);
        $total = round(max(0, $subtotal - $discount - $rounding), 2);

        try {
            $invoiceId = Database::transaction(function () use ($book, $items, $subtotal, $discount, $rounding, $total, $customerId, $paymentMethod, $currencySymbol, $currencyCode, $noteCustomer) {
                if ($customerId && !Database::row('SELECT id FROM customers WHERE id=? AND book_id=? AND deleted_at IS NULL', [$customerId, $book['id']])) throw new \DomainException('That customer no longer exists.');
                foreach ($items as $it) if ($it['pid'] && !Database::row('SELECT id FROM products WHERE id=? AND book_id=?', [$it['pid'], $book['id']])) throw new \DomainException("“{$it['itemName']}” is not a product of this book.");

                $d = Database::row('SELECT invoice_prefix, invoice_counter FROM book_business_details WHERE book_id=? FOR UPDATE', [$book['id']]);
                $invoiceNo = ($d['invoice_prefix'] ?? 'INV') . '-POS-' . str_pad((string)($d['invoice_counter'] ?? 1), 6, '0', STR_PAD_LEFT);

                // sell strictly first: if any product is short nothing at all is saved
                LedgerService::applyStockDiff((int)$book['id'], 'sale', [], LedgerService::qtyByProduct($items));

                Database::run(
                    'INSERT INTO invoices
                        (book_id,type,invoice_no,public_token,customer_id,date,
                         subtotal,discount,rounding,tax,total,paid,status,
                         note_customer,payment_method,theme_color,currency_symbol,currency_code,
                         created_by,created_at)
                     VALUES (?,?,?,?,?,?,?,?,?,0,?,0,?,?,?,?,?,?,?,?)',
                    [$book['id'],'pos',$invoiceNo,bin2hex(random_bytes(20)),$customerId,date('Y-m-d'),
                     $subtotal,$discount,$rounding,$total,'draft',
                     $noteCustomer ?: null,$paymentMethod,$book['theme_color'] ?? '#1a6b4a',$currencySymbol,$currencyCode,
                     auth()['id'],now()]
                );
                $invoiceId = (int)Database::lastId();
                foreach ($items as $it) {
                    Database::run('INSERT INTO invoice_items (invoice_id,product_id,description,qty,unit_price,discount_pct,line_total) VALUES (?,?,?,?,?,0,?)',
                        [$invoiceId, $it['pid'], $it['itemName'], $it['qty'], $it['price'], $it['lineTot']]);
                }
                Database::run('UPDATE book_business_details SET invoice_counter=invoice_counter+1 WHERE book_id=?', [$book['id']]);

                // A POS sale is paid on the spot — a real payment row, so paid/status/points/reports all agree.
                if ($total > 0) LedgerService::addPayment($invoiceId, $total, $paymentMethod, 'POS sale', auth()['id'], 'pos');
                else Database::run("UPDATE invoices SET status='paid' WHERE id=?", [$invoiceId]);
                return $invoiceId;
            });
        } catch (InsufficientStockException $e) {
            $n = Database::row('SELECT name FROM products WHERE id=?', [$e->productId])['name'] ?? 'A product';
            redirect($back, ['error' => "Not enough stock for “{$n}”: {$e->have} on hand, {$e->need} needed. Nothing was saved."]);
        } catch (\DomainException | \RuntimeException $e) {
            redirect($back, ['error' => $e->getMessage()]);
        }

        \App\Services\Integration\Hooks::invoiceCreated((int)$invoiceId);
        $no = Database::row('SELECT invoice_no FROM invoices WHERE id=?', [$invoiceId])['invoice_no'] ?? '';
        redirect('/books/'.$book['id'].'/invoices/'.$invoiceId.'?pos=1', ['success' => 'POS sale recorded — '.$no]);
    }

        private function getBookOrFail(string $id): array
    {
        $book = book_for_user($id, 'business');
        if (!$book) { http_response_code(404); require BASE_PATH.'/views/errors/404.php'; exit; }
        return $book;
    }
}
