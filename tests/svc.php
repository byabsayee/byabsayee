<?php
// Prints ReportService numbers as JSON for a book/month. usage: php tests/svc.php <bookId> <YYYY-MM>
define('BASE_PATH', dirname(__DIR__));
spl_autoload_register(function (string $c): void { $f = BASE_PATH . '/app/' . str_replace('\\', '/', substr($c, 4)) . '.php'; if (str_starts_with($c, 'App\\') && is_file($f)) require_once $f; });
require_once BASE_PATH . '/app/Helpers/helpers.php';
use App\Services\ReportService as R;
[$_, $bid, $mo] = $argv; $from = "$mo-01"; $to = date('Y-m-t', strtotime($from));
$l = R::ledger((int)$bid, $from, $to);
echo json_encode(['cash' => R::cashTotals($l), 'op' => R::operating((int)$bid, $from, $to), 'pos' => R::position((int)$bid),
                  'cats' => array_keys(R::byCategory($l)), 'n' => count($l), 'bal' => R::balance((int)$bid)]);
