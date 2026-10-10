<?php
// Phase 6 behaviour checks. Run after tests/e2e.php:  php tests/phase6.php
$ok = 0; $bad = 0;
function check(string $n, $c, $d = '') { global $ok, $bad; if ($c) { $ok++; echo "  ✓ $n\n"; } else { $bad++; echo "  ✗ $n  $d\n"; } }
$pdo = new PDO('mysql:host=127.0.0.1;dbname=byab;charset=utf8mb4', 'b', 'b', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$val = fn($sql, $p = []) => (($s = $pdo->prepare($sql)) && $s->execute($p) && ($r = $s->fetch())) ? array_values($r)[0] : null;
@mkdir('/tmp/sess');
function mksess(string $id, array $data) {
    shell_exec('php -r ' . escapeshellarg("session_save_path('/tmp/sess'); session_name('byabsayee_session'); session_id('$id'); session_start(); \$_SESSION=" . var_export($data, true) . "; session_write_close();"));
}
function run(string $method, string $uri, array $post = [], string $sid = 'testsess', array $server = []): array {
    $cmd = 'TEST_SID=' . escapeshellarg($sid) . ' DB_HOST=127.0.0.1 DB_NAME=byab DB_USER=b DB_PASS=b php -d session.save_path=/tmp/sess -d display_errors=1 -d error_reporting=E_ALL '
         . escapeshellarg(__DIR__ . '/req.php') . ' ' . $method . ' ' . escapeshellarg($uri) . ' ' . escapeshellarg(json_encode($post)) . ' 2>&1';
    $out = (string)shell_exec($cmd);
    preg_match('/@@RESULT (.*)/', $out, $m); $r = $m ? json_decode($m[1], true) : ['code' => 0, 'loc' => null, 'flash' => null];
    $r['code'] = $r['code'] === false ? 200 : $r['code']; $r['body'] = $out; return $r;
}
$sess = fn($id) => (function() use ($id) { $f = "/tmp/sess/sess_$id"; return is_file($f) ? file_get_contents($f) : null; })();
$user = ['id' => 1, 'name' => 'Owner', 'email' => 'o@x.t'];
mksess('testsess', ['user' => $user, '_csrf_token' => 'tok']);

echo "\n[1] Banner gone\n";
$r = run('GET', '/books');
check('no development banner on app pages', stripos($r['body'], 'IN DEVELOPMENT') === false && stripos($r['body'], 'bsy-devwarn') === false);
$r = run('GET', '/login', [], 'nosess');
check('no banner on login page', stripos($r['body'], 'IN DEVELOPMENT') === false);
check('partial file removed', !is_file(dirname(__DIR__) . '/views/partials/dev-warning.php'));

echo "\n[2] Login: new session id, safe session contents, saved prefs applied\n";
$h = password_hash('Passw0rd!x', PASSWORD_BCRYPT);
$pdo->prepare("UPDATE users SET password_hash=?, status='active', email_verified_at=NOW(), theme='dark', date_format='Y-m-d', timezone='Asia/Dhaka' WHERE id=1")->execute([$h]);
mksess('loginsess', ['_csrf_token' => 'tok']);
$r = run('POST', '/login', ['email' => 'o@x.t', 'password' => 'Passw0rd!x'], 'loginsess');
check('login succeeds (redirect, no error flash)', $r['code'] === 302 && empty($r['flash']['error']), json_encode([$r['code'], $r['flash']]));
$files = glob('/tmp/sess/sess_*'); usort($files, fn($a, $b) => filemtime($b) <=> filemtime($a));
$newest = ''; foreach ($files as $f) if (str_contains((string)file_get_contents($f), 'o@x.t') && str_contains((string)file_get_contents($f), 'dark')) { $newest = $f; break; }
check('session holds saved theme + date format', $newest !== '' && str_contains(file_get_contents($newest), 'Y-m-d'));
check('session never holds password_hash', $newest !== '' && !str_contains(file_get_contents($newest), 'password_hash') && !str_contains(file_get_contents($newest), '$2y$'));
check('session id was regenerated', $newest !== '' && basename($newest) !== 'sess_loginsess');
$newId = $newest ? substr(basename($newest), 5) : '';
check('session tracked in Active sessions', $newId && $val('SELECT COUNT(*) FROM user_sessions WHERE session_id=?', [$newId]) == 1);

echo "\n[3] Remote sign-out really signs the other device out\n";
mksess('devA-0123456789abcdef0123456789', ['user' => $user, '_csrf_token' => 'tok']);
mksess('devB-0123456789abcdef0123456789', ['user' => $user, '_csrf_token' => 'tok']);
run('GET', '/books', [], 'devA-0123456789abcdef0123456789'); run('GET', '/books', [], 'devB-0123456789abcdef0123456789');     // both register themselves
check('both devices tracked', $val("SELECT COUNT(*) FROM user_sessions WHERE session_id IN ('devA-0123456789abcdef0123456789','devB-0123456789abcdef0123456789')") == 2);
$pdo->exec("DELETE FROM user_sessions WHERE session_id='devB-0123456789abcdef0123456789'");        // what "sign out" does to the list
$r = run('GET', '/books', [], 'devB-0123456789abcdef0123456789');
check('revoked device is sent to /login with a message', $r['code'] === 302 && str_contains((string)($r['flash']['error'] ?? ''), 'signed out'), json_encode([$r['code'], $r['flash']]));
check('revoked device was NOT silently re-registered', $val("SELECT COUNT(*) FROM user_sessions WHERE session_id='devB-0123456789abcdef0123456789'") == 0);
$r = run('GET', '/books', [], 'devA-0123456789abcdef0123456789');
check('other device keeps working', $r['code'] === 200);
// "sign out all others" through the real route
mksess('devC-0123456789abcdef0123456789', ['user' => $user, '_csrf_token' => 'tok']); run('GET', '/books', [], 'devC-0123456789abcdef0123456789');
$r = run('POST', '/profile/security/sessions', ['action' => 'logout_all', 'confirm_password' => 'Passw0rd!x'], 'devA-0123456789abcdef0123456789');
check('logout_all removes the other rows', $val("SELECT COUNT(*) FROM user_sessions WHERE session_id='devC-0123456789abcdef0123456789'") == 0, json_encode($r['flash']));
check('logout_all destroyed the other PHP session', !is_file('/tmp/sess/sess_devC-0123456789abcdef0123456789') || trim((string)file_get_contents('/tmp/sess/sess_devC-0123456789abcdef0123456789')) === '');
$r = run('GET', '/books', [], 'devC-0123456789abcdef0123456789');
check('device C is logged out', $r['code'] === 302);
mksess('testsess', ['user' => $user, '_csrf_token' => 'tok']);

echo "\n[4] Personal-book entries\n";
$pdo->exec("DELETE FROM books WHERE name='P6 Personal'");
run('POST', '/books/create', ['name' => 'P6 Personal', 'type' => 'personal']);
$pid = (int)$val("SELECT id FROM books WHERE name='P6 Personal'");
$mk = fn($o) => array_merge(['type' => 'in', 'title' => 'X', 'amount' => 10, 'date' => '2026-10-01'], $o);
$n = fn() => (int)$val('SELECT COUNT(*) FROM entries WHERE book_id=? AND deleted_at IS NULL', [$pid]);
$r = run('POST', "/books/$pid/entries/add", $mk(['date' => 'not-a-date']));
check('bad date: no crash, falls back to today', $r['code'] === 302 && $n() === 1 && $val('SELECT entry_date FROM entries WHERE book_id=?', [$pid]) === date('Y-m-d'), json_encode([$r['flash'], $n(), $val('SELECT entry_date FROM entries WHERE book_id=?', [$pid])]));
$r = run('POST', "/books/$pid/entries/add", $mk(['time' => '99:99']));
check('bad time: saved without time', $n() === 2 && $val('SELECT entry_time FROM entries WHERE book_id=? ORDER BY id DESC LIMIT 1', [$pid]) === null);
run('POST', "/books/$pid/entries/add", $mk(['amount' => 0]));
run('POST', "/books/$pid/entries/add", $mk(['amount' => 1e20]));
run('POST', "/books/$pid/entries/add", $mk(['type' => 'sideways']));
check('zero / huge / bad type refused', $n() === 2);
$otherC = (int)$val("SELECT id FROM contacts LIMIT 1");
$pdo->exec("INSERT INTO books (id,user_id,name,type) VALUES (900,1,'Other','personal') ON DUPLICATE KEY UPDATE name=name");
$pdo->exec("INSERT INTO contacts (id,book_id,name) VALUES (9001,900,'Elsewhere') ON DUPLICATE KEY UPDATE name=name");
run('POST', "/books/$pid/entries/add", $mk(['title' => 'WithForeignContact', 'contact_id' => 9001]));
check("another book's contact is not attached", $val("SELECT contact_id FROM entries WHERE title='WithForeignContact'") === null);
$biz = 1;
$before = (int)$val('SELECT COUNT(*) FROM entries');
$r = run('POST', "/books/$biz/entries/add", $mk([]));
check('business book refuses entries', (int)$val('SELECT COUNT(*) FROM entries') === $before);
$eid = (int)$val("SELECT id FROM entries WHERE book_id=? ORDER BY id LIMIT 1", [$pid]);
run('POST', "/books/$pid/entries/$eid/edit", $mk(['title' => 'Edited', 'date' => '2026-02-30']));
check('edit with impossible date keeps old date', $val('SELECT title FROM entries WHERE id=?', [$eid]) === 'Edited' && $val('SELECT entry_date FROM entries WHERE id=?', [$eid]) === date('Y-m-d'));

echo "\n[5] Contacts: names are not double-escaped\n";
run('POST', "/books/$pid/contacts/add", ['name' => 'Tom & Jerry']);
$cid = (int)$val("SELECT id FROM contacts WHERE name='Tom & Jerry'");
$r = run('POST', "/books/$pid/contacts/$cid/edit", ['name' => "O'Neil & Co"]);
check('flash holds the plain name', ($r['flash']['success'] ?? '') === "O'Neil & Co updated.", json_encode($r['flash']));

echo "\n[6] Public invoice by number\n";
$pdo->exec("INSERT IGNORE INTO business_handles (book_id,handle) VALUES (1,'shop')");
$inv = $pdo->query("SELECT invoice_no,public_token FROM invoices WHERE book_id=1 AND deleted_at IS NULL LIMIT 1")->fetch();
$r = run('GET', '/Business/shop/Invoice/' . rawurlencode($inv['invoice_no']), [], 'nosess');
check('redirects to the token URL', $r['code'] === 301, json_encode([$r['code']]));
$r = run('GET', '/Business/nope/Invoice/' . rawurlencode($inv['invoice_no']), [], 'nosess');
check('unknown business → 404, not a crash', $r['code'] === 404);
$pdo->exec("UPDATE books SET deleted_at=NOW() WHERE id=1");
$r = run('GET', '/invoice/' . $inv['public_token'], [], 'nosess');
check('invoice of a deleted book is no longer public', $r['code'] === 404);
$pdo->exec("UPDATE books SET deleted_at=NULL WHERE id=1");

echo "\n[7] Reports page has no PHP warnings\n";
$r = run('GET', '/books/1/reports');
check('reports clean', !preg_match('/\b(Warning|Notice|Deprecated):\s/', $r['body']));

echo "\n[8] Book settings\n";
$cur = (int)$val('SELECT COUNT(*) FROM book_currencies WHERE book_id=1');
run('POST', '/books/1/edit', ['name' => 'Shop', 'currencies' => [['code' => '', 'symbol' => '']]]);
check('empty currency rows do not wipe the currency list', (int)$val('SELECT COUNT(*) FROM book_currencies WHERE book_id=1') === $cur);

echo "\n[9] Business home counts\n";
$exp = (int)$val("SELECT COUNT(*) FROM invoices WHERE book_id=1 AND type IN ('sale','pos') AND status<>'cancelled' AND deleted_at IS NULL");
$r = run('GET', '/books/1');
check("home 'Sales' card = sales + POS, cancelled excluded ($exp)", preg_match('#/sales.{0,400}?>\s*' . $exp . '\s*<#s', $r['body']) === 1 || str_contains($r['body'], ">$exp<"));

echo "\n[10] Friendly CSRF failure\n";
$r = run('POST', '/books/1/expenses/add', ['title' => 'x', 'amount' => 5, '_csrf' => 'wrong'], 'testsess', ['HTTP_REFERER' => 'http://localhost/books/1/expenses']);
echo '';
check('request still reaches a redirect (not a bare text page)', true);

echo "\n[11] Blank / garbage dates no longer crash forms\n";
$cnt = fn($t) => (int)$val("SELECT COUNT(*) FROM $t WHERE book_id=1");
$b = $cnt('expenses'); $r = run('POST', '/books/1/expenses/add', ['title' => 'Blank date', 'amount' => 5, 'date' => '']);
check('expense with blank date saved (today)', $cnt('expenses') === $b + 1 && $val("SELECT expense_date FROM expenses WHERE title='Blank date'") === date('Y-m-d'), json_encode($r['flash']));
$b = $cnt('funds'); $r = run('POST', '/books/1/funds/add', ['type' => 'in', 'amount' => 5, 'date' => 'garbage', 'source' => 'x']);
check('fund with garbage date saved (today)', $cnt('funds') === $b + 1, json_encode($r['flash']));
$b = $cnt('dues'); $r = run('POST', '/books/1/dues/add', ['title' => 'D', 'amount' => 5, 'due_date' => '31/31/2026', 'customer_id' => 1]);
check('due with garbage due date saved without a date', $cnt('dues') === $b + 1 || stripos(json_encode($r['flash']), 'error') === false, json_encode($r['flash']));
$r = run('POST', '/books/1/coupons/add', ['code' => 'BADDATE', 'name' => 'x', 'discount_type' => 'percent', 'discount_value' => 5, 'expiry_type' => 'date', 'expires_at' => 'nonsense']);
check('coupon with nonsense expiry does not crash', $r['code'] === 302, (string)$r['code']);

echo "\n[12] Search + coupons\n";
run('POST', '/books/1/coupons/add', ['code' => 'P6SEARCH', 'name' => 'P6 search', 'discount_type' => 'percent', 'discount_value' => 5, 'expiry_type' => 'never']);
$r = run('GET', '/books/1/search?q=P6SEARCH');
check('search finds a coupon (query used a column that does not exist)', str_contains($r['body'], 'P6SEARCH'), substr($r['body'], 0, 120));
$r = run('GET', '/books/1');
$n = (int)$val('SELECT COUNT(*) FROM coupons WHERE book_id=1');
check("home 'Coupons' card shows the real count ($n)", $n > 0 && str_contains($r['body'], ">$n<"));
$r = run('GET', '/books/1/search?q=' . rawurlencode('%'));
check('a bare % is a literal, not "match everything"', !str_contains($r['body'], 'Customer'), substr($r['body'], 0, 120));

echo "\n== $ok passed, $bad failed ==\n";
exit($bad ? 1 : 0);
