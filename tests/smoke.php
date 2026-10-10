<?php
// Phase 6 smoke test: seeds extra data through the REAL POST routes, then GETs every page the app has
// and checks (a) no PHP warning/notice/fatal, (b) no blank page, (c) every link/form action on the rendered
// page points at a route that exists for that HTTP method.
//   Run after tests/e2e.php (needs its seed):  php tests/smoke.php
$ok = 0; $bad = 0;
function check(string $n, $c, $d = '') { global $ok, $bad; if ($c) { $ok++; echo "  ✓ $n\n"; } else { $bad++; echo "  ✗ $n  $d\n"; } }
$pdo = new PDO('mysql:host=127.0.0.1;dbname=byab;charset=utf8mb4', 'b', 'b', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$one = fn($sql, $p = []) => (($s = $pdo->prepare($sql)) && $s->execute($p)) ? ($s->fetchAll()[0] ?? null) : null;
$val = fn($sql, $p = []) => ($r = $one($sql, $p)) ? array_values($r)[0] : null;

@mkdir('/tmp/sess');
shell_exec('php -r ' . escapeshellarg("session_save_path('/tmp/sess'); session_name('byabsayee_session'); session_id('testsess'); session_start();\$_SESSION=['user'=>['id'=>1,'name'=>'Owner','email'=>'o@x.t'],'_csrf_token'=>'tok'];session_write_close();"));
function run(string $method, string $uri, array $post = []): array {
    $cmd = 'DB_HOST=127.0.0.1 DB_NAME=byab DB_USER=b DB_PASS=b php -d session.save_path=/tmp/sess -d display_errors=1 -d error_reporting=E_ALL '
         . escapeshellarg(__DIR__ . '/req.php') . ' ' . $method . ' ' . escapeshellarg($uri) . ' ' . escapeshellarg(json_encode($post)) . ' 2>&1';
    $out = (string)shell_exec($cmd);
    preg_match('/@@RESULT (.*)/', $out, $m);
    $r = $m ? json_decode($m[1], true) : ['code' => 0, 'loc' => null, 'flash' => null];
    $r['code'] = $r['code'] === false ? 200 : $r['code'];
    $r['body'] = $out; return $r;
}

// ------------------------------------------------------------------ routes (for link validation)
$routes = [];
foreach (file(dirname(__DIR__) . '/routes.php') as $l)
    if (preg_match("/\\\$router->(get|post)\\(\\s*'([^']+)'/", $l, $m))
        $routes[] = [strtoupper($m[1]), '#^' . preg_replace('/\{[a-z_]+\}/', '[^/]+', $m[2]) . '$#'];
function route_exists(array $routes, string $method, string $path): bool {
    foreach ($routes as [$m, $rx]) if ($m === $method && preg_match($rx, $path)) return true; return false;
}

// ------------------------------------------------------------------ seed extra data through the app itself
echo "\n[seed] extra data via real POST routes\n";
$biz = (int)$val("SELECT id FROM books WHERE type='business' ORDER BY id LIMIT 1");
run('POST', '/books/create', ['name' => 'Smoke Personal', 'type' => 'personal']);
$per = (int)$val("SELECT id FROM books WHERE name='Smoke Personal' ORDER BY id DESC LIMIT 1");
check('personal book created', $per > 0);
run('POST', "/books/$per/contacts/add", ['name' => "O'Brien & Sons", 'phone' => '01700000000']);
$cid = (int)$val('SELECT id FROM contacts WHERE book_id=? ORDER BY id DESC LIMIT 1', [$per]);
check('contact created', $cid > 0);
run('POST', "/books/$per/entries/add", ['type' => 'in',  'title' => 'Salary',  'amount' => 5000, 'date' => date('Y-m-d'), 'contact_id' => $cid]);
run('POST', "/books/$per/entries/add", ['type' => 'out', 'title' => 'Rent',    'amount' => 1200, 'date' => date('Y-m-d')]);
check('2 entries created', (int)$val('SELECT COUNT(*) FROM entries WHERE book_id=? AND deleted_at IS NULL', [$per]) === 2);
run('POST', "/books/$biz/coupons/add", ['code' => 'SMOKE10', 'name' => 'Smoke', 'discount_type' => 'percent', 'discount_value' => 10, 'expiry_type' => 'never']);
run('POST', "/books/$biz/employees/add", ['name' => 'Emp One', 'salary' => 20000, 'salary_type' => 'monthly', 'join_date' => date('Y-m-d'), 'status' => 'active']);
run('POST', "/books/$biz/expenses/add", ['title' => 'Tea', 'amount' => 50, 'date' => date('Y-m-d')]);
run('POST', "/books/$biz/funds/add", ['type' => 'in', 'amount' => 1000, 'date' => date('Y-m-d'), 'source' => 'Owner']);
$pdo->exec("INSERT IGNORE INTO user_handles (user_id,handle) VALUES (1,'owner')");
$pdo->exec("INSERT IGNORE INTO business_handles (book_id,handle) VALUES ($biz,'shop')");
$emp  = (int)$val('SELECT id FROM employees WHERE book_id=? AND deleted_at IS NULL ORDER BY id DESC LIMIT 1', [$biz]);
$sale = (int)$val("SELECT id FROM invoices WHERE book_id=? AND type='sale' AND status<>'cancelled' ORDER BY id LIMIT 1", [$biz]);
$pur  = (int)$val("SELECT id FROM invoices WHERE book_id=? AND type='purchase' ORDER BY id LIMIT 1", [$biz]);
$pos  = (int)$val("SELECT id FROM invoices WHERE book_id=? AND type='pos' ORDER BY id LIMIT 1", [$biz]);
$ret  = (int)$val('SELECT id FROM returns WHERE book_id=? AND deleted_at IS NULL ORDER BY id LIMIT 1', [$biz]);
$tok  = $val('SELECT public_token FROM invoices WHERE id=?', [$sale]);
$ino  = $val('SELECT invoice_no FROM invoices WHERE id=?', [$sale]);
check('seed ids found', $emp && $sale && $pur && $pos && $ret, json_encode(compact('emp', 'sale', 'pur', 'pos', 'ret')));

// ------------------------------------------------------------------ the crawl
$pages = [
  '/books', '/books/create', "/books/$biz", "/books/$per", "/books/$biz/edit", "/books/$per/edit", "/books/$biz/search?q=a", "/books/$per/search?q=Sal",
  "/books/$per/contacts", "/books/$biz/contacts", "/books/$biz/contacts?type=customers", "/books/$biz/customers", "/books/$biz/customers/1", "/books/$biz/customers/search?q=Ra",
  "/books/$biz/suppliers", "/books/$biz/suppliers/1", "/books/$biz/products", "/books/$biz/products?q=Wid", "/books/$biz/products/lookup?q=1", "/books/$biz/products/barcodes",
  "/books/$biz/invoices", "/books/$biz/invoices?status=paid", "/books/$biz/sales", "/books/$biz/purchases", "/books/$biz/invoices/create?type=sale", "/books/$biz/invoices/create?type=purchase",
  "/books/$biz/invoices/$sale", "/books/$biz/invoices/$sale/edit", "/books/$biz/invoices/$pur", "/books/$biz/invoices/$pur/edit", "/books/$biz/invoices/$pos",
  "/books/$biz/invoices/$sale/thermal", "/books/$biz/invoices/$sale/pdf", "/books/$biz/pos", "/books/$biz/returns", "/books/$biz/returns/create", "/books/$biz/returns/invoice-items?invoice_id=$sale", "/books/$biz/returns/$ret",
  "/books/$biz/reports", "/books/$biz/reports?period=month", "/books/$biz/privileges", "/books/$biz/expenses", "/books/$biz/funds", "/books/$biz/dues", "/books/$biz/debts",
  "/books/$biz/coupons", "/books/$biz/coupons/print", "/books/$biz/coupons/validate?code=SMOKE10&subtotal=500", "/books/$biz/employees", "/books/$biz/employees/$emp",
  "/books/$biz/notifications", "/books/$biz/notifications/send", "/notifications", "/notifications/count", "/books/$biz/logs", "/books/$biz/integrations", "/books/$biz/business-profile",
  '/settings', '/profile', '/profile?tab=security', "/books/$biz/deliveries", '/wallet', '/marketplace',
  "/invoice/$tok", "/Business/shop/Invoice/$ino", '/user/@owner', '/business/@shop',
];
foreach (['invoices','products','funds','expenses','dues','debts','customers','suppliers','employees','contacts','coupons','returns','privileges'] as $c) {
    $pages[] = "/books/$biz/print/$c"; $pages[] = "/books/$biz/print/$c?mode=days&value=30";
}
$bad_re = '/(\b(Warning|Notice|Deprecated|Fatal error|Parse error):\s|Uncaught |Stack trace:)[^\n]{0,160}/';
$dead = [];
echo "\n[crawl] " . count($pages) . " pages\n";
foreach ($pages as $uri) {
    $r = run('GET', $uri);
    $body = $r['body'];
    $isJson = str_contains($uri, 'lookup') || str_contains($uri, '/search') || str_contains($uri, 'validate') || str_contains($uri, '/count') || str_contains($uri, 'invoice-items') || $uri === '/notifications' || str_ends_with($uri, '/notifications');
    $isPdf  = str_contains($uri, '/pdf') || str_contains($uri, '/print/');
    $err = preg_match($bad_re, $isPdf ? substr($body, 0, 4000) : $body, $m) ? $m[0] : '';
    $okCode = in_array($r['code'], [200, 301, 302], true) || ($r['code'] === 403 && str_contains($uri, 'deliveries')); 
    check("GET $uri → {$r['code']}", $okCode && $err === '' && ($isJson || $isPdf || in_array($r['code'], [301, 302], true) || strlen($body) > 400), $err ?: ('len=' . strlen($body)));
    if ($r['code'] === 200 && !$isJson && !$isPdf) {
        preg_match_all('/<form[^>]*\saction="([^"]+)"[^>]*>/i', $body, $fa);
        foreach ($fa[1] as $i => $a) {
            $a = html_entity_decode($a); if ($a === '' || $a[0] !== '/' ) continue;
            $meth = preg_match('/method="post"/i', $fa[0][$i]) ? 'POST' : 'GET';
            if (!route_exists($GLOBALS['routes'], $meth, strtok($a, '?'))) $dead["$meth $a"][] = $uri;
        }
        preg_match_all('/\shref="(\/[^"#]*)"/i', $body, $ha);
        foreach ($ha[1] as $a) {
            $a = html_entity_decode($a); $p = strtok($a, '?');
            if (preg_match('#\.(png|ico|css|js|svg|jpg|webp|pdf)$#', $p) || str_starts_with($p, '/uploads/') || $p === '/') continue;
            if (!route_exists($GLOBALS['routes'], 'GET', $p)) $dead["GET $a"][] = $uri;
        }
    }
}
echo "\n[links] every rendered link/form action must hit a registered route\n";
foreach ($dead as $k => $from) check("route exists: $k", false, 'seen on ' . $from[0]);
check('no dead links or form actions', !$dead);
echo "\n== $ok passed, $bad failed ==\n";
exit($bad ? 1 : 0);
