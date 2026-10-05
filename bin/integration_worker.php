<?php
/**
 * Online-store integration worker. One pass per run; supervisord loops it every 30 seconds.
 *   - delivers queued events to each active store (with retry/backoff),
 *   - retries domain verification for links stuck in "verifying",
 *   - prunes old log / inbox / outbox rows (nonces and rate counters expire on their own).
 * CLI only.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('BASE_PATH', dirname(__DIR__));
date_default_timezone_set('UTC');
spl_autoload_register(function (string $class): void {
    $f = BASE_PATH . '/app/' . str_replace('\\', '/', preg_replace('/^App\\\\/', '', $class)) . '.php';
    if (file_exists($f)) require_once $f;
});
require_once BASE_PATH . '/app/Helpers/helpers.php';
if (file_exists(BASE_PATH . '/vendor/autoload.php')) require_once BASE_PATH . '/vendor/autoload.php';

use App\Helpers\Database;
use App\Services\Integration\{Backfill, Conn, ImportService, Lifecycle, Outbox, Reconcile};

try {
    foreach (Database::query("SELECT * FROM integration_connections WHERE status IN ('active','verifying')") as $c) {
        try {
            if ($c['status'] === 'verifying') {
                if ((int)$c['verify_attempts'] < 12 && (!$c['next_verify_at'] || $c['next_verify_at'] <= gmdate('Y-m-d H:i:s'))) Lifecycle::verifyAndComplete($c);
            } else {
                try { Backfill::run($c, 25); } catch (Throwable $e) { error_log('[integration backfill] ' . $e->getMessage()); }
                Outbox::flush($c, 10);
                foreach (Database::query("SELECT id FROM sync_import_batches WHERE conn_id=? AND status='running' ORDER BY id", [$c['id']]) as $b) ImportService::step($c, (int)$b['id'], 50);
                if (Reconcile::due($c)) Reconcile::run($c);                  // hourly drift report; stock drift is corrected with a flagged movement
            }
        } catch (Throwable $e) { error_log('[integration worker] conn ' . $c['id'] . ': ' . $e->getMessage()); }
    }
    if (mt_rand(1, 20) === 1) {
        Database::run("DELETE FROM sync_log WHERE created_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 90 DAY)");
        Database::run("UPDATE sync_log SET detail=NULL WHERE detail IS NOT NULL AND created_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 30 DAY)");
        Database::run("DELETE FROM sync_inbox WHERE received_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 30 DAY)");
        Database::run("DELETE FROM sync_outbox WHERE status='done' AND created_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 30 DAY)");
        Database::run("DELETE FROM sync_nonces WHERE expires_at < UTC_TIMESTAMP()");
    }
} catch (Throwable $e) {
    error_log('[integration worker] ' . $e->getMessage());
    exit(1);
}
