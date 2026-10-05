<?php
namespace App\Services\Integration;

use App\Helpers\Database;

/**
 * Announces records that existed before an entity type joined the sync (or before the link was made).
 * Runs from the worker a few rows at a time, so linking an old book never needs a manual step.
 */
final class Backfill
{
    public static function run(array $conn, int $limit = 25): int
    {
        $n = 0;
        if (Conn::scopeAllows($conn, 'staff')) {
            $rows = Database::query('SELECT e.id FROM employees e WHERE e.book_id=? AND e.deleted_at IS NULL AND TRIM(e.name)<>"" AND NOT EXISTS
                (SELECT 1 FROM sync_links l WHERE l.conn_id=? AND l.entity="staff" AND l.local_id=e.id AND l.last_payload IS NOT NULL) ORDER BY e.id LIMIT ' . (int)$limit, [$conn['book_id'], $conn['id']]);
            foreach ($rows as $r) if (Outbox::emit($conn, 'staff', (int)$r['id'], 'create')) $n++;
        }
        return $n;
    }
}
