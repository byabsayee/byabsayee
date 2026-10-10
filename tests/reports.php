<?php
// Phase 3 report checks against a real MariaDB. php tests/e2e.php
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


$mo = date('Y-m'); $first = date('Y-m-01');
function svc(string $mo): array { return json_decode(shell_exec('DB_HOST=127.0.0.1 DB_NAME=byab DB_USER=b DB_PASS=b php ' . escapeshellarg(__DIR__ . '/svc.php') . " 1 $mo 2>&1"), true) ?: []; }
foreach (['payments','dues','debts','due_payments','debt_payments','returns','expenses','funds','employees'] as $t) { try { $pdo->exec("DELETE FROM $t"); } catch (Throwable $e) {} }

echo "\n[1] Sale: nothing is cash until it is paid\n";
req('POST', '/books/1/invoices/create', inv(['invoice_no'=>'S-1','date'=>$first]));                // 200
$i1 = $one("SELECT * FROM invoices WHERE invoice_no='S-1'");
$s = svc($mo);
check('cash in 0 before any payment', $s['cash']['in'] == 0, json_encode($s));
check('sales booked 200', $s['op']['sales'] == 200);
check('customers owe 200', $s['pos']['receivable'] == 200);

echo "\n[2] Pay via invoice (60) and via the due screen (80): counted once each\n";
req('POST', "/books/1/invoices/{$i1['id']}/payment", ['amount'=>60,'method'=>'cash']);
$d = $one('SELECT * FROM dues WHERE invoice_id=?', [$i1['id']]);
req('POST', "/books/1/dues/{$d['id']}/pay", ['amount'=>80,'payment_method'=>'bkash']);
$s = svc($mo);
check('cash in = 140 (no double count with the due rows)', $s['cash']['in'] == 140, json_encode($s['cash']));
check('owed = 60', $s['pos']['receivable'] == 60, (string)$s['pos']['receivable']);

echo "\n[3] Return credit is not cash; later refund is\n";
req('POST', '/books/1/invoices/create', inv(['invoice_no'=>'S-2','date'=>$first,'item_qty'=>[4]]));   // 400
$i2 = $one("SELECT * FROM invoices WHERE invoice_no='S-2'");
$ret = ['type'=>'sales_return','invoice_id'=>$i2['id'],'date'=>$first,'item_name'=>['Widget'],'item_qty'=>[1],'item_price'=>[100],'item_product_id'=>[1],'discount'=>0,'delivery_charge'=>0];
req('POST', '/books/1/returns/create', $ret);                                                       // credited against the 400 owed
$s = svc($mo);
check('credit return adds no cash in or out', $s['cash']['in'] == 140 && $s['cash']['out'] == 0, json_encode($s['cash']));
check('credit return does not appear as a category', !in_array('Return credit', $s['cats']) && !in_array('Sales Return (Refund)', $s['cats']), json_encode($s['cats']));
check('S-2 owed 300 after credit', $s['pos']['receivable'] == 360, (string)$s['pos']['receivable']);
req('POST', "/books/1/invoices/{$i2['id']}/payment", ['amount'=>300,'method'=>'cash']);
req('POST', '/books/1/returns/create', $ret);                                                       // now fully paid -> cash refund 100
$s = svc($mo);
check('cash in 440 (140 + 300)', $s['cash']['in'] == 440, json_encode($s['cash']));
check('cash refund 100 is cash out', $s['cash']['out'] == 100, json_encode($s['cash']));
check('sales net of returns = 200+400-200 = 400', $s['op']['net_sales'] == 400, json_encode($s['op']));

echo "\n[4] Expense, funds, purchase + debt payment, POS, stand-alone due\n";
req('POST', '/books/1/expenses/add', ['title'=>'Rent','amount'=>50,'date'=>$first]);
req('POST', '/books/1/funds/add', ['type'=>'add','amount'=>1000,'source'=>'Capital','date'=>$first]);
req('POST', '/books/1/funds/add', ['type'=>'withdraw','amount'=>200,'source'=>'Owner draw','date'=>$first]);
$pu = ['type'=>'purchase','invoice_no'=>'P-1','date'=>$first,'supplier_id'=>1,'item_name'=>['Widget'],'item_qty'=>[5],'item_price'=>[55],'item_product_id'=>[1],'discount'=>0];
req('POST', '/books/1/invoices/create', $pu);
$p1 = $one("SELECT * FROM invoices WHERE invoice_no='P-1'"); $debt = $one('SELECT * FROM debts WHERE invoice_id=?', [$p1['id']]);
req('POST', "/books/1/debts/{$debt['id']}/pay", ['amount'=>100,'payment_method'=>'cash']);
req('POST', '/books/1/pos', ['customer_id'=>'','payment_method'=>'Cash','discount'=>0,'rounding'=>0,'item_name'=>['Gadget'],'item_qty'=>[2],'item_price'=>[50],'item_product_id'=>[2]]);
$pdo->exec("INSERT INTO dues (book_id,customer_id,title,amount,paid_amount,status,created_at) VALUES (1,2,'Old balance',500,200,'partial',NOW())");
$did = $pdo->lastInsertId();
$pdo->exec("INSERT INTO due_payments (due_id,book_id,amount,payment_method,paid_at) VALUES ($did,1,200,'cash',NOW())");
$s = svc($mo);
// in: 60+80+300 + POS 100 + stand-alone due 200 + fund 1000 = 1740 ; out: refund 100 + rent 50 + fund 200 + debt 100 = 450
check('cash in = 1740', $s['cash']['in'] == 1740, json_encode($s['cash']));
check('cash out = 450', $s['cash']['out'] == 450, json_encode($s['cash']));
check('net cash = 1290', $s['cash']['net'] == 1290);
check('owed to us = 60 + 300 stand-alone = 360', $s['pos']['receivable'] == 360, (string)$s['pos']['receivable']);
check('we owe = 275 - 100 = 175', $s['pos']['payable'] == 175, (string)$s['pos']['payable']);
check('operating result = 500 net sales(+POS) - 275 - 50', $s['op']['sales'] == 700 && $s['op']['result'] == 175, json_encode($s['op']));
check('all-time balance equals this month (everything is this month)', $s['bal']['net'] == 1290, json_encode($s['bal']));

echo "\n[5] Cancelled / deleted invoices drop out; void payments ignored\n";
req('POST', '/books/1/invoices/create', inv(['invoice_no'=>'S-9','date'=>$first,'item_qty'=>[1]]));
$i9 = $one("SELECT * FROM invoices WHERE invoice_no='S-9'");
req('POST', "/books/1/invoices/{$i9['id']}/payment", ['amount'=>100,'method'=>'cash']);
$s2 = svc($mo);
check('+100 cash while it stands', $s2['cash']['in'] == 1840, json_encode($s2['cash']));
req('POST', "/books/1/invoices/{$i9['id']}/delete");
$s3 = svc($mo);
check('deleting it takes its cash and sales out again', $s3['cash']['in'] == 1740 && $s3['op']['sales'] == 700, json_encode([$s3['cash'], $s3['op']['sales']]));
$pdo->exec("UPDATE payments SET status='void' WHERE invoice_id={$i1['id']} AND amount=60");
check('a void payment is ignored', svc($mo)['cash']['in'] == 1680);
$pdo->exec("UPDATE payments SET status='recorded' WHERE invoice_id={$i1['id']}");

echo "\n[6] Pages render and agree with the service\n";
$r = req('GET', "/books/1/reports?month=$mo");
check('reports 200', in_array($r['code'], [200, false], true), (string)$r['code']);
check('reports shows cash in 1,740', str_contains($r['body'], '1,740'), 'missing');
check('reports shows Cash Balance card', str_contains($r['body'], 'Cash Balance'));
check('no PHP warnings in reports', !str_contains($r['body'], 'Warning:') && !str_contains($r['body'], 'Notice:') && !str_contains($r['body'], 'Deprecated:'));
$r = req('GET', "/books/1/reports?month=garbage");
check('bad month falls back instead of crashing', in_array($r['code'], [200, false], true));
$r = req('GET', "/books/1/reports?month=$mo&type=in");
check('Cash In filter renders', in_array($r['code'], [200, false], true) && str_contains($r['body'], 'Sale Payment'));
$r = req('GET', '/books/1');
check('book home 200', in_array($r['code'], [200, false], true));
check('book home: dues = 360, debts = 175', str_contains($r['body'], '360') && str_contains($r['body'], '175'));
check('book home: available funds 1,290', str_contains($r['body'], '1,290'));
$r = req('GET', '/books');
check('book list 200 and shows cash in 1,740', in_array($r['code'], [200, false], true) && str_contains($r['body'], '1,740'));
$r = req('GET', '/books/1/invoices');
check('invoice list 200', in_array($r['code'], [200, false], true));
$r = req('GET', "/books/1/print/reports?mode=month&value=$mo");
check('print endpoint does not 500', $r['code'] != 500, (string)$r['code']);

echo "\n== $ok passed, $bad failed ==\n";
exit($bad ? 1 : 0);
