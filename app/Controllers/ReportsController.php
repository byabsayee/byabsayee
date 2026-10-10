<?php
namespace App\Controllers;
use App\Helpers\Database;
use App\Services\ReportService;

class ReportsController
{
    public function index(array $params): void
    {
        if (guest()) redirect('/login');
        $book = $this->getBookOrFail($params['id']);
        if (!book_can($book, 'reports', 'view')) abort_403();

        $month = $_GET['month'] ?? date('Y-m');
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) $month = date('Y-m');
        $typeFilter = $_GET['type'] ?? 'all';
        if (!in_array($typeFilter, ['all', 'in', 'out'], true)) $typeFilter = 'all';
        $dateFrom   = $month . '-01';
        $dateTo     = date('Y-m-t', strtotime($dateFrom));
        $bid        = (int)$book['id'];

        $sym = ReportService::symbol($bid);

        // One service, one set of rules: cash in/out is the cash ledger, the operating result is what was booked,
        // the position is what is still owed. See app/Services/ReportService.php.
        $allEntries = ReportService::ledger($bid, $dateFrom, $dateTo);
        $cash       = ReportService::cashTotals($allEntries);
        $totalIn    = $cash['in'];
        $totalOut   = $cash['out'];
        $operating  = ReportService::operating($bid, $dateFrom, $dateTo);
        $position   = ReportService::position($bid);
        $categories = ReportService::byCategory($allEntries);
        $daily      = ReportService::byDay($allEntries);
        $opening    = ReportService::cash($bid, '2000-01-01', date('Y-m-d', strtotime($dateFrom . ' -1 day')));
        $openingBalance = $opening['net'];

        $entries = $typeFilter === 'all'
            ? $allEntries
            : array_values(array_filter($allEntries, fn($e) => $e['direction'] === $typeFilter));

        require BASE_PATH . '/views/business/reports/index.php';
    }

    private function getBookOrFail(string $id): array
    {
        try {
            $book = Database::row(
                'SELECT * FROM books WHERE id=? AND deleted_at IS NULL AND (user_id=? OR EXISTS(
                    SELECT 1 FROM book_members WHERE book_id=books.id AND user_id=? AND status="active"
                ))',
                [$id, auth()['id'], auth()['id']]
            );
        } catch (\Throwable $e) {
            $book = Database::row(
                'SELECT * FROM books WHERE id=? AND user_id=? AND deleted_at IS NULL',
                [$id, auth()['id']]
            );
        }
        if (!$book) { http_response_code(404); require BASE_PATH.'/views/errors/404.php'; exit; }
        return $book;
    }
}
