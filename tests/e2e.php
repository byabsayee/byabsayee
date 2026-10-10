<?php
// Phase 2 end-to-end checks against a real MariaDB. php tests/e2e.php
putenv('DB_HOST=127.0.0.1'); putenv('DB_NAME=byab'); putenv('DB_USER=b'); putenv('DB_PASS=b');
$pdo = new PDO('mysql:host=127.0.0.1;dbname=byab;charset=utf8mb4', 'b', 'b', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$q = fn($sql, $p = []) => ($s = $pdo->prepare($sql)) && $s->execute($p) ? $s->fetchAll() : [];
$one = fn($sql, $p = []) => ($q($sql, $p)[0] ?? null);
$ok = 0; $bad = 0;
function check(string $name, $cond, $detail = '') { global $ok, $bad; if ($cond) { $ok++; echo "  ✓ $name\n"; } else { $bad++; echo "  ✗ $name  $detail\n"; } }

// ---- seed ----
foreach (['users','books','customers','suppliers','products','invoices'] as $t) $pdo->exec("DELETE FROM $t");
$pdo->exec("INSERT INTO users (id,name,email,password_hash) VALUES (1,'Owner','o@x.t','x')");
$pdo->exec("INSERT INTO books (id,user_id,name,type) VALUES (1,1,'Shop','business')");
$pdo->exec("INSERT INTO book_business_details (book_id,invoice_prefix,invoice_counter,invoice_counter_purchase) VALUES (1,'INV',1,1) ON DUPLICATE KEY UPDATE invoice_counter=1");
$pdo->exec("INSERT INTO customers (id,book_id,name,phone,points) VALUES (1,1,'Rahim','01711111111',50),(2,1,'Karim','01722222222',0)");
$pdo->exec("INSERT INTO suppliers (id,book_id,name) VALUES (1,1,'Sup One')");
$pdo->exec("INSERT INTO products (id,book_id,name,unit,buy_price,sell_price,stock_qty) VALUES (1,1,'Widget','pcs',60,100,10),(2,1,'Gadget','pcs',30,50,5)");
$pdo->exec("INSERT INTO product_batches (product_id,book_id,initial_qty,remaining_qty,buy_price,sell_price) VALUES (1,1,10,10,60,100),(2,1,5,5,30,50)");
@mkdir('/tmp/sess'); 
session_save_path('/tmp/sess'); session_name('byabsayee_session'); session_id('testsess'); session_start();
$_SESSION = ['user' => ['id' => 1, 'name' => 'Owner', 'email' => 'o@x.t'], '_csrf_token' => 'tok']; session_write_close();

function req(string $method, string $uri, array $post = []): array {
    $cmd = 'php -d session.save_path=/tmp/sess -d display_errors=0 -d error_log=/tmp/php_err.log ' . escapeshellarg(__DIR__ . '/req.php') . ' ' . $method . ' ' . escapeshellarg($uri) . ' ' . escapeshellarg(json_encode($post)) . ' 2>&1';
    $out = shell_exec('DB_HOST=127.0.0.1 DB_NAME=byab DB_USER=b DB_PASS=b ' . $cmd);
    preg_match('/@@RESULT (.*)/', $out, $m);
    $r = $m ? json_decode($m[1], true) : ['code' => 0, 'loc' => null, 'flash' => $out];
    $r['body'] = $out; return $r;
}
function inv(array $o = []): array {
    return array_merge(['type'=>'sale','invoice_no'=>'T-'.mt_rand(1000,9999),'date'=>'2026-10-01','customer_id'=>1,
        'item_name'=>['Widget'],'item_qty'=>[2],'item_price'=>[100],'item_product_id'=>[1],'item_variant'=>[''],
        'discount'=>0,'delivery_charge'=>0,'handling_charge'=>0,'tax'=>0,'delivery_type'=>'own'], $o);
}
$stock = fn($pid) => (float)$one('SELECT stock_qty s FROM products WHERE id=?', [$pid])['s'];
$pts   = fn($cid) => (int)$one('SELECT points p FROM customers WHERE id=?', [$cid])['p'];

echo "\n[1] Sale invoice: transaction, stock, due mirror\n";
$r = req('POST', '/books/1/invoices/create', inv(['invoice_no'=>'S-1']));
$i1 = $one("SELECT * FROM invoices WHERE invoice_no='S-1'");
check('invoice saved', (bool)$i1, json_encode($r['flash']));
check('total = 200', $i1 && (float)$i1['total'] == 200);
check('stock 10 → 8', $stock(1) == 8, (string)$stock(1));
$due = $one('SELECT * FROM dues WHERE invoice_id=?', [$i1['id']]);
check('due created = 200 unpaid', $due && (float)$due['amount'] == 200 && $due['status'] === 'unpaid');

echo "\n[2] Not enough stock: nothing saved\n";
$r = req('POST', '/books/1/invoices/create', inv(['invoice_no'=>'S-BIG','item_qty'=>[99]]));
check('refused with message', stripos(json_encode($r['flash']), 'Not enough stock') !== false, json_encode($r['flash']));
check('no invoice row', !$one("SELECT id FROM invoices WHERE invoice_no='S-BIG'"));
check('stock unchanged', $stock(1) == 8);

echo "\n[3] Duplicate invoice number refused\n";
$r = req('POST', '/books/1/invoices/create', inv(['invoice_no'=>'S-1']));
check('duplicate refused', stripos(json_encode($r['flash']), 'already used') !== false, json_encode($r['flash']));

echo "\n[4] Pay through the INVOICE → due follows, points cumulative\n";
req('POST', "/books/1/invoices/{$i1['id']}/payment", ['amount'=>60,'method'=>'cash']);
$i = $one('SELECT * FROM invoices WHERE id=?', [$i1['id']]); $d = $one('SELECT * FROM dues WHERE invoice_id=?', [$i1['id']]);
check('invoice paid 60, partial', (float)$i['paid'] == 60 && $i['status'] === 'partial');
check('due paid 60, partial', (float)$d['paid_amount'] == 60 && $d['status'] === 'partial');
check('0 points yet (60 < 100)', $pts(1) == 50, (string)$pts(1));
req('POST', "/books/1/invoices/{$i1['id']}/payment", ['amount'=>60,'method'=>'cash']);
check('cumulative 120 → 1 point earned (old code: 0)', $pts(1) == 51, (string)$pts(1));

echo "\n[5] Pay through the DUE → invoice follows\n";
$d = $one('SELECT * FROM dues WHERE invoice_id=?', [$i1['id']]);
req('POST', "/books/1/dues/{$d['id']}/pay", ['amount'=>80,'payment_method'=>'bkash']);
$i = $one('SELECT * FROM invoices WHERE id=?', [$i1['id']]); $d = $one('SELECT * FROM dues WHERE id=?', [$d['id']]);
check('invoice now paid 200', (float)$i['paid'] == 200 && $i['status'] === 'paid', json_encode($i['paid'].' '.$i['status']));
check('due paid', $d['status'] === 'paid' && (float)$d['paid_amount'] == 200);
check('payment row exists for the due payment', (bool)$one("SELECT id FROM payments WHERE invoice_id=? AND method='bkash'", [$i1['id']]));
check('2 points total earned (200/100)', $pts(1) == 52, (string)$pts(1));
$r = req('POST', "/books/1/invoices/{$i1['id']}/payment", ['amount'=>10,'method'=>'cash']);
check('overpay refused', stripos(json_encode($r['flash']), 'error') !== false);
$r = req('POST', "/books/1/dues/{$d['id']}/delete");
check('cannot delete an invoice-linked due', stripos(json_encode($r['flash']), 'belongs to an invoice') !== false);

echo "\n[6] Edit invoice: qty 2→3, other customer, stock + due + points follow\n";
$r = req('POST', "/books/1/invoices/{$i1['id']}/edit", inv(['invoice_no'=>'S-1','item_qty'=>[3],'customer_id'=>2]));
$i = $one('SELECT * FROM invoices WHERE id=?', [$i1['id']]); $d = $one('SELECT * FROM dues WHERE invoice_id=?', [$i1['id']]);
check('edit saved', $i && (float)$i['total'] == 300, json_encode($r['flash']));
check('stock 8 → 7 (only the difference moved)', $stock(1) == 7, (string)$stock(1));
check('due amount 300, partial, now Karim', (float)$d['amount'] == 300 && $d['status'] === 'partial' && (int)$d['customer_id'] == 2);
check('points moved: Rahim back to 50, Karim 2', $pts(1) == 50 && $pts(2) == 2, $pts(1).'/'.$pts(2));
$r = req('POST', "/books/1/invoices/{$i1['id']}/edit", inv(['invoice_no'=>'S-1','item_qty'=>[1],'customer_id'=>2]));
check('cannot lower total below amount paid', stripos(json_encode($r['flash']), 'already paid') !== false, json_encode($r['flash']));
check('…and nothing changed', $stock(1) == 7);

echo "\n[7] Sales return: validation + credit vs refund\n";
req('POST', '/books/1/invoices/create', inv(['invoice_no'=>'S-2','item_qty'=>[4]]));   // 400 unpaid, Rahim
$i2 = $one("SELECT * FROM invoices WHERE invoice_no='S-2'");
$ret = ['type'=>'sales_return','invoice_id'=>$i2['id'],'date'=>'2026-10-02','item_name'=>['Widget'],'item_qty'=>[5],'item_price'=>[100],'item_product_id'=>[1],'discount'=>0,'delivery_charge'=>0];
$r = req('POST', '/books/1/returns/create', $ret);
check('returning more than bought refused', stripos(json_encode($r['flash']), 'left to return') !== false, json_encode($r['flash']));
$ret['item_qty'] = [1];
$s0 = $stock(1);
req('POST', '/books/1/returns/create', $ret);
$rt = $one("SELECT * FROM returns WHERE invoice_id=? ", [$i2['id']]);
check('return saved, stock +1', $rt && $stock(1) == $s0 + 1);
check('whole 100 credited off the due (unpaid invoice), no cash refund', (float)$rt['due_adjustment'] == 100 && (float)$rt['cash_refund'] == 0, json_encode($rt));
$i = $one('SELECT * FROM invoices WHERE id=?', [$i2['id']]); $d = $one('SELECT * FROM dues WHERE invoice_id=?', [$i2['id']]);
check('invoice shows 100 settled, due = 300 left', (float)$i['paid'] == 100 && (float)$d['paid_amount'] == 100);
check('no points for a credit', $pts(1) == 50);
$rid = $rt['id'];
req('POST', "/books/1/returns/$rid/delete");
$i = $one('SELECT * FROM invoices WHERE id=?', [$i2['id']]); $d = $one('SELECT * FROM dues WHERE invoice_id=?', [$i2['id']]);
check('delete return: stock back to before', $stock(1) == $s0);
check('delete return: due re-opened', (float)$i['paid'] == 0 && (float)$d['paid_amount'] == 0 && $d['status'] === 'unpaid', json_encode([$i['paid'], $d]));

echo "\n[8] 3rd-party delivery expense follows the invoice\n";
req('POST', '/books/1/invoices/create', inv(['invoice_no'=>'S-3','delivery_charge'=>40,'delivery_type'=>'other']));
$i3 = $one("SELECT * FROM invoices WHERE invoice_no='S-3'");
$e = $q("SELECT * FROM expenses WHERE source_table='invoices' AND source_id=?", [$i3['id']]);
check('one linked expense of 40', count($e) == 1 && (float)$e[0]['amount'] == 40);
req('POST', "/books/1/invoices/{$i3['id']}/edit", inv(['invoice_no'=>'S-3','delivery_charge'=>55,'delivery_type'=>'other']));
$e = $q("SELECT * FROM expenses WHERE source_table='invoices' AND source_id=?", [$i3['id']]);
check('edit → expense 55 (not duplicated)', count($e) == 1 && (float)$e[0]['amount'] == 55);
$r = req('POST', "/books/1/expenses/{$e[0]['id']}/delete");
check('auto expense cannot be deleted by hand', (bool)$one('SELECT id FROM expenses WHERE id=?', [$e[0]['id']]));
$s1 = $stock(1);
req('POST', "/books/1/invoices/{$i3['id']}/delete");
check('delete invoice: expense gone', !$q("SELECT id FROM expenses WHERE source_table='invoices' AND source_id=?", [$i3['id']]));
check('delete invoice: stock restored (+2)', $stock(1) == $s1 + 2, (string)$stock(1));
check('delete invoice: due cancelled', ($x = $one('SELECT status FROM dues WHERE invoice_id=?', [$i3['id']])) && $x['status'] === 'cancelled');

echo "\n[9] Delete a paid invoice: points taken back\n";
$before = $pts(1);
req('POST', '/books/1/invoices/create', inv(['invoice_no'=>'S-4','item_qty'=>[3]]));       // 300
$i4 = $one("SELECT * FROM invoices WHERE invoice_no='S-4'");
req('POST', "/books/1/invoices/{$i4['id']}/payment", ['amount'=>300,'method'=>'cash']);
check('+3 points', $pts(1) == $before + 3, (string)$pts(1));
req('POST', "/books/1/invoices/{$i4['id']}/delete");
check('points back to before', $pts(1) == $before, (string)$pts(1));
check('invoice soft-deleted', (bool)$one('SELECT id FROM invoices WHERE id=? AND deleted_at IS NOT NULL', [$i4['id']]));

echo "\n[10] Points spent as a discount: balance enforced, refunded on delete\n";
$r = req('POST', '/books/1/invoices/create', inv(['invoice_no'=>'S-5','points_discount'=>9999]));
check('more points than the customer has → refused', isset($r['flash']['error']) && !$one("SELECT id FROM invoices WHERE invoice_no='S-5'"), json_encode($r['flash']));
$b = $pts(1);
req('POST', '/books/1/invoices/create', inv(['invoice_no'=>'S-6','points_discount'=>20]));
$i6 = $one("SELECT * FROM invoices WHERE invoice_no='S-6'");
check('spent 20 → total 180', $i6 && (float)$i6['total'] == 180 && $pts(1) == $b - 20, json_encode([$i6['total'] ?? null, $pts(1)]));
req('POST', "/books/1/invoices/{$i6['id']}/delete");
check('delete refunds the 20 points', $pts(1) == $b, (string)$pts(1));

echo "\n[11] POS: real payment, strict stock\n";
$s = $stock(2);
req('POST', '/books/1/pos', ['customer_id'=>'','payment_method'=>'Cash','discount'=>0,'rounding'=>0,'item_name'=>['Gadget'],'item_qty'=>[2],'item_price'=>[50],'item_product_id'=>[2]]);
$p = $one("SELECT * FROM invoices WHERE type='pos' ORDER BY id DESC LIMIT 1");
check('POS saved paid=total=100', $p && (float)$p['paid'] == 100 && $p['status'] === 'paid', json_encode($p));
check('POS has a payment row', $p && (bool)$one('SELECT id FROM payments WHERE invoice_id=?', [$p['id']]));
check('stock 5 → 3', $stock(2) == $s - 2);
$r = req('POST', '/books/1/pos', ['payment_method'=>'Cash','item_name'=>['Gadget'],'item_qty'=>[50],'item_price'=>[50],'item_product_id'=>[2]]);
check('POS refuses overselling', stripos(json_encode($r['flash']), 'Not enough stock') !== false);

echo "\n[12] Purchase invoice + debt, edit, stock guard\n";
$pu = ['type'=>'purchase','invoice_no'=>'P-1','date'=>'2026-10-01','supplier_id'=>1,'item_name'=>['Widget'],'item_qty'=>[5],'item_price'=>[55],'item_product_id'=>[1],'discount'=>0];
$s = $stock(1);
req('POST', '/books/1/invoices/create', $pu);
$p1 = $one("SELECT * FROM invoices WHERE invoice_no='P-1'");
$debt = $one('SELECT * FROM debts WHERE invoice_id=?', [$p1['id']]);
check('purchase: stock +5, debt 275', $stock(1) == $s + 5 && $debt && (float)$debt['amount'] == 275);
$d = $debt;
req('POST', "/books/1/debts/{$d['id']}/pay", ['amount'=>100,'payment_method'=>'cash']);
$p1 = $one('SELECT * FROM invoices WHERE id=?', [$p1['id']]);
check('paying the debt updates the purchase invoice', (float)$p1['paid'] == 100 && $p1['status'] === 'partial');
req('POST', "/books/1/invoices/{$p1['id']}/edit", array_merge($pu, ['item_qty'=>[3]]));
$p1 = $one('SELECT * FROM invoices WHERE id=?', [$p1['id']]); $debt = $one('SELECT * FROM debts WHERE id=?', [$d['id']]);
check('edit purchase 5→3: stock −2, debt 165', $stock(1) == $s + 3 && (float)$debt['amount'] == 165, $stock(1).' '.json_encode($debt['amount']));

echo "\n[13] Salary: one payment = one expense, linked\n";
$pdo->exec("INSERT INTO employees (id,book_id,name,salary,salary_type,status) VALUES (1,1,'Emp One',5000,'monthly','active')");
req('POST', '/books/1/employees/1/salary/pay', ['amount'=>5000,'payment_method'=>'cash','period_label'=>'Oct']);
$sp = $one('SELECT * FROM employee_salary_payments WHERE employee_id=1');
$ex = $q("SELECT * FROM expenses WHERE source_table='employee_salary_payments'");
check('salary payment + exactly one linked expense', $sp && count($ex) == 1 && (int)$sp['expense_id'] == (int)$ex[0]['id'], json_encode([$sp,$ex]));
req('POST', "/books/1/employees/1/salary/{$sp['id']}/delete");
check('removing the payment removes its expense', !$q("SELECT id FROM expenses WHERE source_table='employee_salary_payments'") && !$one('SELECT id FROM employee_salary_payments WHERE employee_id=1'));

echo "\n[14] Deleting a customer with open dues warns\n";
$r = req('POST', '/books/1/customers/2/delete');
check('refused without confirmation (Karim has open dues)', !$one('SELECT id FROM customers WHERE id=2 AND deleted_at IS NOT NULL'), json_encode($r['flash']));
req('POST', '/books/1/customers/2/delete', ['confirm_open'=>1]);
check('deleted after confirmation', (bool)$one('SELECT id FROM customers WHERE id=2 AND deleted_at IS NOT NULL'));

echo "\n[15] Edit page renders\n";
$r = req('GET', "/books/1/invoices/{$i2['id']}/edit");
check('edit form 200 + prefilled', strpos($r['body'], '404') === false && in_array($r['code'], [200, false], true) && strpos($r['body'], 'value="'.$i2['invoice_no'].'"') !== false && strpos($r['body'], 'EDIT_ITEMS') !== false, substr($r['body'], 0, 300));
$r = req('GET', "/books/1/invoices/{$i2['id']}");
check('invoice page shows Edit button', strpos($r['body'], '/edit') !== false);
$r = req('GET', '/books/1/invoices/create?type=sale');
check('new-invoice form still renders', in_array($r['code'], [200, false], true) && strpos($r['body'], 'Save Invoice') !== false);

echo "\n== $ok passed, $bad failed ==\n";
exit($bad ? 1 : 0);
