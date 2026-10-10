<?php
namespace App\Controllers;
use App\Helpers\Database;

class DashboardController
{
    public function index(): void
    {
        if (guest()) redirect('/login');

        $userId = auth()['id'];

        // Includes both owned books AND books where the user is an active member. Figures come from ReportService
        // (personal = its entries, business = cash flow) so they match the book list and the Reports page.
        $books = Database::query(
            'SELECT b.*, (b.user_id = ?) AS is_owner
             FROM books b
             WHERE b.deleted_at IS NULL
               AND (
                   b.user_id = ?
                   OR EXISTS (
                       SELECT 1 FROM book_members bm
                       WHERE bm.book_id = b.id AND bm.user_id = ? AND bm.status = "active"
                   )
               )
             ORDER BY is_owner DESC, b.created_at DESC',
            [$userId, $userId, $userId]
        );
        foreach ($books as &$bk) {
            $t = \App\Services\ReportService::bookTotals($bk);
            $bk['total_in']  = $t['in'];
            $bk['total_out'] = $t['out'];
        }
        unset($bk);

        require BASE_PATH . '/views/dashboard/index.php';
    }
}
