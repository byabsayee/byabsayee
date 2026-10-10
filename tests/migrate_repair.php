<?php
// Legacy-shaped drift → migrate_core repairs it. php tests/migrate_repair.php
$pdo = new PDO('mysql:host=127.0.0.1;dbname=byab', 'b', 'b', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$ok=0;$bad=0; function check($n,$c,$d=''){global $ok,$bad; if($c){$ok++;echo "  ✓ $n\n";}else{$bad++;echo "  ✗ $n $d\n";}}
foreach (['invoices','expenses','employee_salary_payments','dues','debts','employees'] as $t) $pdo->exec("DELETE FROM $t");
$pdo->exec("INSERT IGNORE INTO users (id,name,email,password_hash) VALUES (1,'O','o@x','x')");
$pdo->exec("INSERT IGNORE INTO books (id,user_id,name,type) VALUES (1,1,'S','business')");
$pdo->exec("INSERT IGNORE INTO customers (id,book_id,name,points) VALUES (1,1,'R',0)");
// old POS: status paid, paid 0, no payment row
$pdo->exec("INSERT INTO invoices (id,book_id,type,invoice_no,date,total,paid,status,payment_method) VALUES (10,1,'pos','POS-1','2026-09-01',150,0,'paid','Cash')");
// old drift: due paid 80 on the due screen, invoice still unpaid
$pdo->exec("INSERT INTO invoices (id,book_id,type,invoice_no,customer_id,date,total,paid,status) VALUES (11,1,'sale','S-11',1,'2026-09-01',200,0,'sent')");
$pdo->exec("INSERT INTO dues (book_id,customer_id,invoice_id,title,amount,paid_amount,status) VALUES (1,1,11,'Invoice #S-11',200,80,'partial')");
// invoice paid 100 by payment row, due still says 0
$pdo->exec("INSERT INTO invoices (id,book_id,type,invoice_no,customer_id,date,total,paid,status) VALUES (12,1,'sale','S-12',1,'2026-09-01',300,100,'partial')");
$pdo->exec("INSERT INTO payments (invoice_id,amount,method,date) VALUES (12,100,'cash','2026-09-01')");
$pdo->exec("INSERT INTO dues (book_id,customer_id,invoice_id,title,amount,paid_amount,status) VALUES (1,1,12,'Invoice #S-12',300,0,'unpaid')");
// old salary: expense + salary payment
$pdo->exec("INSERT INTO employees (id,book_id,name,status) VALUES (1,1,'E','active')");
$pdo->exec("INSERT INTO expenses (id,book_id,title,amount,expense_date) VALUES (50,1,'Salary — E',900,'2026-09-30')");
$pdo->exec("INSERT INTO employee_salary_payments (id,book_id,employee_id,expense_id,amount) VALUES (7,1,1,50,900)");
$out = shell_exec('DB_HOST=127.0.0.1 DB_NAME=byab DB_USER=b DB_PASS=b php bin/migrate_core.php 2>&1'); echo $out;
$one=fn($s)=>$pdo->query($s)->fetch();
$p=$one("select * from invoices where id=10"); check('POS now paid=150', (float)$p['paid']==150);
check('POS has payment row', (bool)$one("select id from payments where invoice_id=10"));
$i=$one("select * from invoices where id=11"); $d=$one("select * from dues where invoice_id=11");
check('drift 11: invoice paid 80 / partial', (float)$i['paid']==80 && $i['status']==='partial', json_encode($i));
check('drift 11: due still 80', (float)$d['paid_amount']==80);
check('drift 11: invoice has a payment row for the 80', (float)$one("select sum(amount) s from payments where invoice_id=11")['s']==80);
$d=$one("select * from dues where invoice_id=12"); check('drift 12: due now 100 partial', (float)$d['paid_amount']==100 && $d['status']==='partial', json_encode($d));
check('points backfilled for S-12 (1 pt)', (int)$one("select points_awarded p from invoices where id=12")['p']==1);
check('salary expense linked to its payment', $one("select source_table t, source_id i from expenses where id=50")==['t'=>'employee_salary_payments','i'=>7]);
$out2 = shell_exec('DB_HOST=127.0.0.1 DB_NAME=byab DB_USER=b DB_PASS=b php bin/migrate_core.php 2>&1');
check('second run is a no-op', strpos($out2,'~')===false && strpos($out2,'!')===false, $out2);
echo "== $ok passed, $bad failed ==\n";
