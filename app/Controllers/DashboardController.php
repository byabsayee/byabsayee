<?php
namespace App\Controllers;
use App\Helpers\Database;

class DashboardController
{
    public function index(): void
    {
        if (guest()) redirect('/login');

        $userId = auth()['id'];

        // For personal books: total_in/out = entries
        // For business books: total_in = paid sales, total_out = paid purchases
        // Includes both owned books AND books where user is an active member
        $books = Database::query(
            'SELECT b.*,
                CASE
                    WHEN b.type = "personal" THEN
                        COALESCE((SELECT SUM(e.amount) FROM entries e WHERE e.book_id=b.id AND e.type="in"  AND e.deleted_at IS NULL),0)
                    ELSE
                        COALESCE((SELECT SUM(i.total) FROM invoices i WHERE i.book_id=b.id AND i.type="sale"     AND i.status="paid" AND i.deleted_at IS NULL),0)
                END AS total_in,
                CASE
                    WHEN b.type = "personal" THEN
                        COALESCE((SELECT SUM(e.amount) FROM entries e WHERE e.book_id=b.id AND e.type="out" AND e.deleted_at IS NULL),0)
                    ELSE
                        COALESCE((SELECT SUM(i.total) FROM invoices i WHERE i.book_id=b.id AND i.type="purchase" AND i.status="paid" AND i.deleted_at IS NULL),0)
                END AS total_out,
                (b.user_id = ?) AS is_owner
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

        require BASE_PATH . '/views/dashboard/index.php';
    }
}
