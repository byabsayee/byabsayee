<?php
/**
 * Byabsayee — Migration: online-store integration, phase 2 (history import + Book-side reconcile)
 *
 * Adds sync_import_batches, invoices.import_batch, customers.import_batch and the reconcile result columns.
 * Safe to re-run (checks information_schema / SHOW COLUMNS first; no ADD COLUMN IF NOT EXISTS).
 * Run inside the app container:   php public/migrate_integrations_phase2.php
 * DELETE THIS FILE after running (same convention as the other migrate_*.php scripts).
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("Run this from the command line: php public/migrate_integrations_phase2.php\n"); }
define('BASE_PATH', __DIR__ . '/..');
$env = BASE_PATH . '/.env';
if (file_exists($env)) {
    foreach (file($env, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
        [$k, $v] = explode('=', $line, 2);
        if (getenv(trim($k)) === false) putenv(trim($k) . '=' . trim($v));
    }
}
$pdo = new PDO('mysql:host=' . (getenv('DB_HOST') ?: 'mariadb') . ';port=' . (getenv('DB_PORT') ?: '3306') . ';dbname=' . (getenv('DB_NAME') ?: 'byabsayee_db') . ';charset=utf8mb4',
    getenv('DB_USER') ?: 'byabsayee_user', getenv('DB_PASS') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$failed = 0;
function say(string $m): void { echo $m . "\n"; }
function has_table(PDO $pdo, string $t): bool { $s = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?'); $s->execute([$t]); return (bool)$s->fetchColumn(); }
function has_col(PDO $pdo, string $t, string $c): bool { $s = $pdo->prepare("SHOW COLUMNS FROM `$t` LIKE ?"); $s->execute([$c]); return (bool)$s->fetch(); }
function step(PDO $pdo, string $sql, string $what): void { global $failed; try { $pdo->exec($sql); say("  ok   $what"); } catch (Throwable $e) { $failed++; say("  FAIL $what — " . $e->getMessage()); } }
function add_col(PDO $pdo, string $t, string $c, string $def): void {
    if (!has_table($pdo, $t)) { say("  skip $t.$c (no table $t)"); return; }
    if (has_col($pdo, $t, $c)) { say("  --   $t.$c already exists"); return; }
    step($pdo, "ALTER TABLE `$t` ADD COLUMN `$c` $def", "add $t.$c");
}

say('== Phase 2: history import + reconcile ==');
if (!has_table($pdo, 'integration_connections')) { say('Run public/migrate_integrations.php first.'); exit(1); }

if (has_table($pdo, 'sync_import_batches')) say('  --   table sync_import_batches already exists');
else step($pdo, "CREATE TABLE `sync_import_batches` (
    `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `conn_id`     INT UNSIGNED NOT NULL,
    `direction`   ENUM('book_to_store','store_to_book') NOT NULL,
    `entities`    VARCHAR(60) NOT NULL,
    `date_from`   DATE NULL,
    `date_to`     DATE NULL,
    `status`      ENUM('dry_run','running','paused','done','failed','rolled_back') NOT NULL DEFAULT 'dry_run',
    `plan`        MEDIUMTEXT NULL,
    `progress`    MEDIUMTEXT NULL,
    `cursor_json` MEDIUMTEXT NULL,
    `created_by`  VARCHAR(120) NULL,
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `started_at`  DATETIME NULL,
    `finished_at` DATETIME NULL,
    KEY `idx_conn` (`conn_id`,`status`),
    FOREIGN KEY (`conn_id`) REFERENCES `integration_connections`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", 'create table sync_import_batches');

add_col($pdo, 'invoices',  'import_batch', 'INT UNSIGNED NULL DEFAULT NULL');
add_col($pdo, 'customers', 'import_batch', 'INT UNSIGNED NULL DEFAULT NULL');
add_col($pdo, 'integration_connections', 'last_reconcile',    'MEDIUMTEXT NULL DEFAULT NULL');
add_col($pdo, 'integration_connections', 'last_reconcile_at', 'DATETIME NULL DEFAULT NULL');

say($failed ? "\nFinished with $failed failed step(s) — read the FAIL lines above." : "\nDone. You can delete public/migrate_integrations_phase2.php now.");
exit($failed ? 1 : 0);
