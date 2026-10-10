<?php
/**
 * Byabsayee — core schema migration (idempotent, CLI only).
 *
 * Brings ANY database — fresh from an old schema.sql, or a live one — up to what the code expects.
 * Safe to run on every start: each step checks SHOW COLUMNS / SHOW TABLES / SHOW INDEX first, so
 * re-running changes nothing. (ADD COLUMN IF NOT EXISTS is deliberately avoided: MariaDB-version trouble.)
 *
 * Run:  php bin/migrate_core.php          (docker-entrypoint.sh does this automatically)
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

define('BASE_PATH', dirname(__DIR__));
$env = BASE_PATH . '/.env';
if (file_exists($env)) {                       // only fills in what the environment does not already provide
    foreach (file($env, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
        [$k, $v] = explode('=', $line, 2);
        if (getenv(trim($k)) === false) putenv(trim($k) . '=' . trim($v));
    }
}
$pdo = new PDO(
    'mysql:host=' . (getenv('DB_HOST') ?: 'mariadb') . ';port=' . (getenv('DB_PORT') ?: '3306') . ';dbname=' . (getenv('DB_NAME') ?: 'byabsayee_db') . ';charset=utf8mb4',
    getenv('DB_USER'), getenv('DB_PASS'),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$failed = 0;
function say(string $m): void { echo $m . PHP_EOL; }
function has_table(PDO $p, string $t): bool { return (bool)$p->query("SHOW TABLES LIKE " . $p->quote($t))->fetchColumn(); }
function has_col(PDO $p, string $t, string $c): bool {
    return has_table($p, $t) && (bool)$p->query("SHOW COLUMNS FROM `$t` LIKE " . $p->quote($c))->fetchColumn();
}
function has_index(PDO $p, string $t, string $i): bool {
    return has_table($p, $t) && (bool)$p->query("SHOW INDEX FROM `$t` WHERE Key_name=" . $p->quote($i))->fetchColumn();
}
function step(PDO $p, string $label, callable $needs, string $sql): void {
    global $failed;
    try {
        if ($needs()) { $p->exec($sql); say("  + $label"); }
    } catch (Throwable $e) { $failed++; say("  ! $label — " . $e->getMessage()); }
}
function add_col(PDO $p, string $t, string $c, string $def): void {
    step($p, "$t.$c", fn() => has_table($p, $t) && !has_col($p, $t, $c), "ALTER TABLE `$t` ADD COLUMN `$c` $def");
}
function add_index(PDO $p, string $t, string $name, string $cols, bool $unique = false): void {
    step($p, "index $t.$name", fn() => has_table($p, $t) && !has_index($p, $t, $name),
        'ALTER TABLE `' . $t . '` ADD ' . ($unique ? 'UNIQUE ' : '') . "INDEX `$name` ($cols)");
}

function ensure_enum_value(PDO $p, string $t, string $c, string $value): void {
    global $failed;
    try {
        if (!has_col($p, $t, $c)) return;
        $row = $p->query("SHOW COLUMNS FROM `$t` LIKE " . $p->quote($c))->fetch(PDO::FETCH_ASSOC);
        if (!preg_match('/^enum\((.*)\)$/i', $row['Type'], $m)) return;
        preg_match_all("/'((?:[^']|'')*)'/", $m[1], $vals);
        $vals = array_map(fn($v) => str_replace("''", "'", $v), $vals[1]);
        if (in_array($value, $vals, true)) return;
        $vals[] = $value;
        $list = implode(',', array_map(fn($v) => $p->quote($v), $vals));
        $null = strtoupper($row['Null']) === 'NO' ? 'NOT NULL' : 'NULL';
        $def  = $row['Default'] !== null ? 'DEFAULT ' . $p->quote($row['Default']) : '';
        $p->exec("ALTER TABLE `$t` MODIFY `$c` ENUM($list) $null $def");
        say("  + $t.$c accepts '$value'");
    } catch (Throwable $e) { $failed++; say("  ! enum $t.$c — " . $e->getMessage()); }
}

say('[migrate_core] checking schema …');

/* ── Columns the code already uses but schema.sql never declared ─────────────────────────────── */
add_col($pdo, 'books',        'address',    'TEXT NULL AFTER `phone`');
add_col($pdo, 'employees',    'emp_code',   'VARCHAR(30) NULL AFTER `book_id`');
add_col($pdo, 'designations', 'updated_at', 'DATETIME NULL DEFAULT NULL');

/* ── ENUM values the code already writes ─────────────────────────────────────────────────────── */
// terminate()/delete() set book_members.status='terminated'; a plain schema import did not allow it.
ensure_enum_value($pdo, 'book_members', 'status', 'terminated');

/* (later phases append their idempotent steps below) */

/* ── Phase 2: data-sync columns ─────────────────────────────────────────────────────────────── */
// expenses created by another record (invoice delivery, return delivery, salary) remember who made them,
// so editing/deleting the parent keeps the expense in step instead of orphaning it.
add_col($pdo, 'expenses', 'source_table', 'VARCHAR(40) NULL');
add_col($pdo, 'expenses', 'source_id',    'INT UNSIGNED NULL');
add_index($pdo, 'expenses', 'idx_exp_source', '`source_table`,`source_id`');
// loyalty points actually granted by an invoice (so edits / deletes / cancels can take back exactly that many)
add_col($pdo, 'invoices', 'points_awarded', 'INT NOT NULL DEFAULT 0');
// part of a return that was netted off the original invoice's due/debt instead of being paid out in cash
add_col($pdo, 'returns',  'due_adjustment', 'DECIMAL(15,2) NOT NULL DEFAULT 0.00');
add_col($pdo, 'returns',  'cash_refund',    'DECIMAL(15,2) NOT NULL DEFAULT 0.00');

/* ── Phase 2: one-time repairs of records that drifted apart (all safe to re-run) ───────────── */
try {
    // 1. points already granted: the sum of whole points per recorded payment (what the old code did)
    $n = $pdo->exec("UPDATE invoices i SET i.points_awarded = (SELECT COALESCE(SUM(FLOOR(p.amount/100)),0) FROM payments p WHERE p.invoice_id=i.id AND p.status='recorded')
                     WHERE i.type='sale' AND i.customer_id IS NOT NULL AND i.points_awarded=0 AND i.paid>0 AND i.status<>'cancelled'");
    if ($n) say("  ~ points_awarded backfilled on $n invoice(s)");

    // 2. POS sales were saved as status=paid but paid=0 with no payment row: make them real
    $pos = $pdo->query("SELECT id,total,date,payment_method FROM invoices WHERE type='pos' AND status='paid' AND paid=0 AND total>0 AND deleted_at IS NULL")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($pos as $r) {
        $pdo->prepare("INSERT INTO payments (invoice_id,amount,method,date,note,status,paid_at) VALUES (?,?,?,?,?,'recorded',?)")
            ->execute([$r['id'], $r['total'], $r['payment_method'] ?: 'cash', $r['date'], 'POS sale (repaired)', $r['date'] . ' 12:00:00']);
        $pdo->prepare("UPDATE invoices SET paid=total WHERE id=?")->execute([$r['id']]);
    }
    if ($pos) say('  ~ ' . count($pos) . ' POS sale(s) now carry their payment');

    // 3. dues/debts tied to an invoice: the larger of "paid on the invoice" and "paid on the due/debt" wins, and both sides are brought to it
    foreach ([['dues', 'due_payments', 'due_id', 'customer_id'], ['debts', 'debt_payments', 'debt_id', 'supplier_id']] as [$tbl, $ptbl, $fk]) {
        $rows = $pdo->query("SELECT x.id xid, x.invoice_id, x.amount, x.paid_amount xpaid, i.total, i.paid ipaid, i.status istatus, i.date idate
                             FROM `$tbl` x JOIN invoices i ON i.id=x.invoice_id
                             WHERE x.status<>'cancelled' AND i.status<>'cancelled' AND i.deleted_at IS NULL AND ABS(x.paid_amount - i.paid) > 0.004")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            $best = min((float)$r['total'], max((float)$r['xpaid'], (float)$r['ipaid']));
            if ($best > (float)$r['ipaid'] + 0.004) {
                $pdo->prepare("INSERT INTO payments (invoice_id,amount,method,date,note,status,paid_at) VALUES (?,?,?,?,?,'recorded',UTC_TIMESTAMP())")
                    ->execute([$r['invoice_id'], round($best - (float)$r['ipaid'], 2), 'cash', date('Y-m-d'), 'Reconciled from ' . ($tbl === 'dues' ? 'due' : 'debt') . ' payments']);
            }
            $inv = $best + 0.004 >= (float)$r['total'] ? 'paid' : ($best > 0 ? 'partial' : (in_array($r['istatus'], ['draft'], true) ? 'draft' : 'sent'));
            $pdo->prepare("UPDATE invoices SET paid=?, status=? WHERE id=?")->execute([$best, $inv, $r['invoice_id']]);
            $xs = $best + 0.004 >= (float)$r['amount'] ? 'paid' : ($best > 0 ? 'partial' : 'unpaid');
            $pdo->prepare("UPDATE `$tbl` SET paid_amount=?, status=? WHERE id=?")->execute([min($best, (float)$r['amount']), $xs, $r['xid']]);
        }
        if ($rows) say('  ~ ' . count($rows) . " $tbl re-synced with their invoice");
    }

    // 3b. any invoice whose "paid" is larger than its payment rows (older imports/edits): add one opening-balance payment so paid can be recomputed safely from payments
    $gap = $pdo->query("SELECT i.id, i.paid - COALESCE((SELECT SUM(p.amount) FROM payments p WHERE p.invoice_id=i.id AND p.status='recorded'),0) AS diff, i.date
                        FROM invoices i WHERE i.type IN ('sale','purchase') AND i.deleted_at IS NULL AND i.status<>'cancelled' AND i.paid>0
                        HAVING diff > 0.004")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($gap as $g) {
        $pdo->prepare("INSERT INTO payments (invoice_id,amount,method,date,note,status,paid_at) VALUES (?,?,?,?,?,'recorded',UTC_TIMESTAMP())")
            ->execute([$g['id'], round((float)$g['diff'], 2), 'cash', $g['date'], 'Opening balance (paid before payment rows existed)']);
    }
    if ($gap) say('  ~ ' . count($gap) . ' invoice(s) given an opening-balance payment row');

    // 4. salary expenses created by the old code: link them to their salary payment
    $n = $pdo->exec("UPDATE expenses e JOIN employee_salary_payments sp ON sp.expense_id=e.id SET e.source_table='employee_salary_payments', e.source_id=sp.id WHERE e.source_table IS NULL");
    if ($n) say("  ~ $n salary expense(s) linked to their payment");
} catch (Throwable $e) { $failed++; say('  ! phase-2 repairs — ' . $e->getMessage()); }



/* ── Helpful indexes (no-ops when present) ──────────────────────────────────────────────────── */
add_index($pdo, 'invoices',   'idx_inv_book_type_date', '`book_id`,`type`,`date`');
add_index($pdo, 'dues',       'idx_dues_invoice',       '`invoice_id`');
add_index($pdo, 'debts',      'idx_debts_invoice',      '`invoice_id`');
add_index($pdo, 'payments',   'idx_pay_invoice',        '`invoice_id`');

say($failed ? "[migrate_core] finished with $failed problem(s) — see lines starting with !" : '[migrate_core] schema is up to date.');
exit($failed ? 1 : 0);
