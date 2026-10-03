<?php
/**
 * Byabsayee — Migration: Online-store integration (protocol v1)
 *
 * Creates the integration tables and adds the columns the sync needs to existing tables.
 * Safe to re-run: every step checks information_schema first (no ADD COLUMN IF NOT EXISTS).
 *
 * Run (inside the app container):   php public/migrate_integrations.php
 * DELETE THIS FILE after running (same convention as the other migrate_*.php scripts).
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("Run this from the command line: php public/migrate_integrations.php\n"); }

define('BASE_PATH', __DIR__ . '/..');
$env = BASE_PATH . '/.env';
if (file_exists($env)) {
    foreach (file($env, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
        [$k, $v] = explode('=', $line, 2);
        if (getenv(trim($k)) === false) putenv(trim($k) . '=' . trim($v));
    }
}
$pdo = new PDO(
    'mysql:host=' . (getenv('DB_HOST') ?: 'mariadb') . ';port=' . (getenv('DB_PORT') ?: '3306') . ';dbname=' . (getenv('DB_NAME') ?: 'byabsayee_db') . ';charset=utf8mb4',
    getenv('DB_USER') ?: 'byabsayee_user', getenv('DB_PASS') ?: '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$failed = 0;
function say(string $m): void { echo $m . "\n"; }
function has_table(PDO $pdo, string $t): bool { $s = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?'); $s->execute([$t]); return (bool) $s->fetchColumn(); }
function has_col(PDO $pdo, string $t, string $c): bool { $s = $pdo->prepare("SHOW COLUMNS FROM `$t` LIKE ?"); $s->execute([$c]); return (bool) $s->fetch(); }
function has_index(PDO $pdo, string $t, string $i): bool { $s = $pdo->prepare("SHOW INDEX FROM `$t` WHERE Key_name = ?"); $s->execute([$i]); return (bool) $s->fetch(); }
function exec_step(PDO $pdo, string $sql, string $what): void {
    global $failed;
    try { $pdo->exec($sql); say("  ok   $what"); } catch (Throwable $e) { $failed++; say("  FAIL $what — " . $e->getMessage()); }
}
function add_col(PDO $pdo, string $t, string $c, string $def): void {
    if (!has_table($pdo, $t)) { say("  skip $t.$c (table $t does not exist)"); return; }
    if (has_col($pdo, $t, $c)) { say("  --   $t.$c already exists"); return; }
    exec_step($pdo, "ALTER TABLE `$t` ADD COLUMN `$c` $def", "add $t.$c");
}
function add_idx(PDO $pdo, string $t, string $name, string $cols): void {
    if (!has_table($pdo, $t)) return;
    if (has_index($pdo, $t, $name)) return;
    exec_step($pdo, "ALTER TABLE `$t` ADD INDEX `$name` ($cols)", "add index $t.$name");
}
function create_table(PDO $pdo, string $name, string $sql): void {
    if (has_table($pdo, $name)) { say("  --   table $name already exists"); return; }
    exec_step($pdo, $sql, "create table $name");
}

say('== Integration tables ==');

create_table($pdo, 'integration_connections', "CREATE TABLE `integration_connections` (
    `id`                       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `book_id`                  INT UNSIGNED NOT NULL,
    `connection_id`            CHAR(36)     NOT NULL,
    `site_domain`              VARCHAR(190) NOT NULL,
    `status`                   ENUM('pending','verifying','active','paused','revoked') NOT NULL DEFAULT 'pending',
    `authority`                ENUM('book','site') NOT NULL DEFAULT 'book',
    `scopes`                   TEXT NULL,
    `api_key_hash`             CHAR(64) NULL,
    `api_key_enc`              TEXT NULL,
    `secret_site_to_book_enc`  TEXT NULL,
    `secret_book_to_site_enc`  TEXT NULL,
    `pairing_hash`             CHAR(64) NULL,
    `pairing_expires_at`       DATETIME NULL,
    `api_version`              VARCHAR(10) NULL,
    `peer_module_version`      VARCHAR(40) NULL,
    `peer_capabilities`        TEXT NULL,
    `site_info`                MEDIUMTEXT NULL,
    `shared_config`            TEXT NULL,
    `verified_at`              DATETIME NULL,
    `activated_at`             DATETIME NULL,
    `last_sync_at`             DATETIME NULL,
    `last_error`               VARCHAR(500) NULL,
    `verify_attempts`          INT UNSIGNED NOT NULL DEFAULT 0,
    `next_verify_at`           DATETIME NULL,
    `created_by`               INT UNSIGNED NULL,
    `created_at`               DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`               DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_book` (`book_id`),
    UNIQUE KEY `uq_domain` (`site_domain`),
    UNIQUE KEY `uq_connection` (`connection_id`),
    FOREIGN KEY (`book_id`) REFERENCES `books`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

create_table($pdo, 'sync_links', "CREATE TABLE `sync_links` (
    `id`             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `conn_id`        INT UNSIGNED NOT NULL,
    `entity`         VARCHAR(30)  NOT NULL,
    `entity_uuid`    CHAR(36)     NOT NULL,
    `local_id`       INT UNSIGNED NULL,
    `local_version`  INT UNSIGNED NOT NULL DEFAULT 0,
    `remote_version` INT UNSIGNED NOT NULL DEFAULT 0,
    `last_payload`   MEDIUMTEXT NULL,
    `field_ts`       MEDIUMTEXT NULL,
    `content_hash`   CHAR(40) NULL,
    `archived`       TINYINT(1) NOT NULL DEFAULT 0,
    `last_synced_at` DATETIME NULL,
    `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_uuid`  (`conn_id`,`entity`,`entity_uuid`),
    UNIQUE KEY `uq_local` (`conn_id`,`entity`,`local_id`),
    FOREIGN KEY (`conn_id`) REFERENCES `integration_connections`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

create_table($pdo, 'sync_outbox', "CREATE TABLE `sync_outbox` (
    `id`              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `conn_id`         INT UNSIGNED NOT NULL,
    `event_id`        CHAR(36) NOT NULL,
    `entity`          VARCHAR(30) NOT NULL,
    `entity_uuid`     CHAR(36) NOT NULL,
    `op`              VARCHAR(12) NOT NULL,
    `version`         INT UNSIGNED NOT NULL,
    `envelope`        MEDIUMTEXT NOT NULL,
    `status`          ENUM('pending','sending','done','dead','conflict') NOT NULL DEFAULT 'pending',
    `attempts`        INT UNSIGNED NOT NULL DEFAULT 0,
    `next_attempt_at` DATETIME NOT NULL,
    `last_error`      VARCHAR(500) NULL,
    `sent_at`         DATETIME NULL,
    `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_event` (`event_id`),
    KEY `idx_due` (`status`,`next_attempt_at`),
    KEY `idx_entity` (`conn_id`,`entity`,`entity_uuid`,`id`),
    FOREIGN KEY (`conn_id`) REFERENCES `integration_connections`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

create_table($pdo, 'sync_inbox', "CREATE TABLE `sync_inbox` (
    `event_id`    CHAR(36) NOT NULL PRIMARY KEY,
    `conn_id`     INT UNSIGNED NOT NULL,
    `entity`      VARCHAR(30) NOT NULL,
    `entity_uuid` CHAR(36) NOT NULL,
    `op`          VARCHAR(12) NOT NULL,
    `result`      VARCHAR(20) NOT NULL,
    `detail`      VARCHAR(255) NULL,
    `received_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_received` (`received_at`),
    FOREIGN KEY (`conn_id`) REFERENCES `integration_connections`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

create_table($pdo, 'sync_nonces', "CREATE TABLE `sync_nonces` (
    `nonce`      CHAR(64) NOT NULL PRIMARY KEY,
    `expires_at` DATETIME NOT NULL,
    KEY `idx_exp` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

create_table($pdo, 'sync_rate', "CREATE TABLE `sync_rate` (
    `bucket` VARCHAR(90) NOT NULL,
    `win`    INT UNSIGNED NOT NULL,
    `cnt`    INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (`bucket`,`win`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

create_table($pdo, 'sync_log', "CREATE TABLE `sync_log` (
    `id`          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `conn_id`     INT UNSIGNED NULL,
    `direction`   ENUM('in','out','system') NOT NULL DEFAULT 'system',
    `kind`        VARCHAR(30) NOT NULL,
    `event_id`    CHAR(36) NULL,
    `http_status` SMALLINT NULL,
    `ok`          TINYINT(1) NOT NULL DEFAULT 1,
    `summary`     VARCHAR(500) NOT NULL,
    `detail`      TEXT NULL,
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_conn_date` (`conn_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

create_table($pdo, 'sync_conflicts', "CREATE TABLE `sync_conflicts` (
    `id`          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `conn_id`     INT UNSIGNED NOT NULL,
    `kind`        VARCHAR(30) NOT NULL,
    `entity`      VARCHAR(30) NOT NULL,
    `entity_uuid` CHAR(36) NULL,
    `local_id`    INT UNSIGNED NULL,
    `event_id`    CHAR(36) NULL,
    `local_data`  MEDIUMTEXT NULL,
    `remote_data` MEDIUMTEXT NULL,
    `note`        VARCHAR(500) NULL,
    `status`      ENUM('open','resolved') NOT NULL DEFAULT 'open',
    `resolution`  VARCHAR(40) NULL,
    `resolved_by` VARCHAR(120) NULL,
    `resolved_at` DATETIME NULL,
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_status` (`conn_id`,`status`),
    FOREIGN KEY (`conn_id`) REFERENCES `integration_connections`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

say('== Columns on existing tables ==');

// invoices: an order that came from (or goes to) the online store is still an ordinary sales invoice.
add_col($pdo, 'invoices', 'source',            "VARCHAR(20) NULL DEFAULT NULL");
add_col($pdo, 'invoices', 'fulfilment_status', "VARCHAR(20) NULL DEFAULT NULL");
add_col($pdo, 'invoices', 'external_number',   "VARCHAR(60) NULL DEFAULT NULL");
add_col($pdo, 'invoices', 'sync_to_store',     "TINYINT(1) NOT NULL DEFAULT 0");
add_col($pdo, 'invoices', 'tax_inclusive',     "TINYINT(1) NOT NULL DEFAULT 0");
add_col($pdo, 'invoices', 'stock_deducted',    "TINYINT(1) NOT NULL DEFAULT 1");
add_col($pdo, 'invoices', 'order_meta',        "MEDIUMTEXT NULL DEFAULT NULL");
add_idx($pdo, 'invoices', 'idx_source', '`book_id`,`source`');

// payments: recorded/void + exact time + reference (online payments are voided, never edited)
add_col($pdo, 'payments', 'status',    "VARCHAR(10) NOT NULL DEFAULT 'recorded'");
add_col($pdo, 'payments', 'paid_at',   "DATETIME NULL DEFAULT NULL");
add_col($pdo, 'payments', 'reference', "VARCHAR(120) NULL DEFAULT NULL");

// customers: structured address so city/state/zip survive a round trip to the store
add_col($pdo, 'customers', 'city',  "VARCHAR(100) NULL DEFAULT NULL");
add_col($pdo, 'customers', 'state', "VARCHAR(100) NULL DEFAULT NULL");
add_col($pdo, 'customers', 'zip',   "VARCHAR(20)  NULL DEFAULT NULL");

// products / categories: fields the store shares
add_col($pdo, 'products',   'is_active',    "TINYINT(1) NOT NULL DEFAULT 1");
add_col($pdo, 'products',   'weight_grams', "INT UNSIGNED NULL DEFAULT NULL");
add_col($pdo, 'categories', 'description',  "TEXT NULL DEFAULT NULL");
add_col($pdo, 'categories', 'sort_order',   "INT NOT NULL DEFAULT 0");
add_col($pdo, 'categories', 'is_active',    "TINYINT(1) NOT NULL DEFAULT 1");

// coupons: the store's richer rules (the book now enforces them too)
add_col($pdo, 'coupons', 'max_discount',       "DECIMAL(10,2) NULL DEFAULT NULL");
add_col($pdo, 'coupons', 'min_subtotal',       "DECIMAL(15,2) NOT NULL DEFAULT 0.00");
add_col($pdo, 'coupons', 'starts_at',          "DATETIME NULL DEFAULT NULL");
add_col($pdo, 'coupons', 'usage_limit',        "INT UNSIGNED NULL DEFAULT NULL");
add_col($pdo, 'coupons', 'per_customer_limit', "INT UNSIGNED NULL DEFAULT NULL");

// payment methods live in invoice_method_options (type='payment')
add_col($pdo, 'invoice_method_options', 'is_active', "TINYINT(1) NOT NULL DEFAULT 1");
add_col($pdo, 'invoice_method_options', 'code',      "VARCHAR(40) NULL DEFAULT NULL");
add_col($pdo, 'invoice_method_options', 'kind',      "VARCHAR(10) NULL DEFAULT NULL");

// returns: which payment method the refund went out through
add_col($pdo, 'returns', 'refund_method', "VARCHAR(120) NULL DEFAULT NULL");

say($failed ? "\nFinished with $failed failed step(s) — read the FAIL lines above." : "\nDone. You can delete public/migrate_integrations.php now.");
exit($failed ? 1 : 0);
