<?php
namespace App\Controllers;
use App\Helpers\Database;

class PosController
{
    public function show(array $params): void
    {
        if (guest()) redirect('/login');
        $book = $this->getBookOrFail($params['id']);

        $products       = Database::query('SELECT id,name,sell_price,stock_qty,unit,product_code FROM products WHERE book_id=? AND deleted_at IS NULL ORDER BY name', [$book['id']]);
        $customers      = Database::query('SELECT id,name,phone FROM customers WHERE book_id=? AND deleted_at IS NULL ORDER BY name', [$book['id']]);
        $paymentMethods = Database::query('SELECT * FROM invoice_method_options WHERE book_id=? AND type="payment" ORDER BY sort_order', [$book['id']]);
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

        $details        = Database::row('SELECT * FROM book_business_details WHERE book_id=?', [$book['id']]);
        $prefix         = $details['invoice_prefix'] ?? 'INV';
        $counter        = $details['invoice_counter'] ?? 1;
        $invoiceNo      = $prefix . '-POS-' . str_pad($counter, 6, '0', STR_PAD_LEFT);
        $customerId     = !empty($_POST['customer_id']) ? (int)$_POST['customer_id'] : null;
        $paymentMethod  = trim($_POST['payment_method'] ?? 'Cash');
        $discount       = (float)($_POST['discount'] ?? 0);
        $rounding       = (float)($_POST['rounding']  ?? 0);
        $currencySymbol = trim($_POST['currency_symbol'] ?? '৳');
        $currencyCode   = trim($_POST['currency_code']   ?? 'BDT');
        $themeColor     = $book['theme_color'] ?? '#1a6b4a';
        $noteCustomer   = trim($_POST['note_customer'] ?? '');

        $itemNames  = $_POST['item_name']       ?? [];
        $itemQtys   = $_POST['item_qty']        ?? [];
        $itemPrices = $_POST['item_price']      ?? [];
        $itemPids   = $_POST['item_product_id'] ?? [];

        $subtotal = 0;
        $items    = [];
        foreach ($itemNames as $i => $itemName) {
            $itemName = trim($itemName);
            if (!$itemName) continue;
            $qty     = (float)($itemQtys[$i]   ?? 1);
            $price   = (float)($itemPrices[$i] ?? 0);
            $pid     = !empty($itemPids[$i])   ? (int)$itemPids[$i] : null;
            $lineTot = $qty * $price;
            $subtotal += $lineTot;
            $items[]  = compact('itemName','qty','price','lineTot','pid');
        }

        if (empty($items)) redirect('/books/'.$book['id'].'/pos', ['error' => 'Add at least one item.']);

        $total = max(0, $subtotal - $discount - $rounding);

        // Generate public token
        $token = bin2hex(random_bytes(20));

        Database::run(
            'INSERT INTO invoices
                (book_id,type,invoice_no,public_token,customer_id,date,
                 subtotal,discount,rounding,tax,total,paid,status,
                 note_customer,payment_method,theme_color,currency_symbol,currency_code,
                 created_by,created_at)
             VALUES (?,?,?,?,?,?,?,?,?,0,?,0,?,?,?,?,?,?,?,?)',
            [
                $book['id'],'pos',$invoiceNo,$token,$customerId,date('Y-m-d'),
                $subtotal,$discount,$rounding,$total,'paid',
                $noteCustomer ?: null,$paymentMethod,$themeColor,$currencySymbol,$currencyCode,
                auth()['id'],now()
            ]
        );
        $invoiceId = Database::lastId();

        foreach ($items as $item) {
            Database::run(
                'INSERT INTO invoice_items (invoice_id,product_id,description,qty,unit_price,discount_pct,line_total)
                 VALUES (?,?,?,?,?,0,?)',
                [$invoiceId,$item['pid'],$item['itemName'],$item['qty'],$item['price'],$item['lineTot']]
            );
            if ($item['pid']) {
                Database::run('UPDATE products SET stock_qty=stock_qty-? WHERE id=? AND book_id=?',
                    [$item['qty'],$item['pid'],$book['id']]);
            }
        }

        Database::run('UPDATE book_business_details SET invoice_counter=invoice_counter+1 WHERE book_id=?', [$book['id']]);

        // Redirect to POS receipt
        redirect('/books/'.$book['id'].'/invoices/'.$invoiceId.'?pos=1',
            ['success' => 'POS sale recorded — '.$invoiceNo]);
    }

        private function getBookOrFail(string $id): array
    {
        $book = book_for_user($id, 'business');
        if (!$book) { http_response_code(404); require BASE_PATH.'/views/errors/404.php'; exit; }
        return $book;
    }
}
