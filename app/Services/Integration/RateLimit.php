<?php
namespace App\Services\Integration;

use App\Helpers\Database;

/** Fixed-window counters in the database (works across php-fpm workers). */
final class RateLimit
{
    /** Returns true while the bucket is within its limit. */
    public static function ok(string $bucket, int $limit, int $windowSeconds = 60): bool
    {
        $win = intdiv(time(), $windowSeconds);
        $b = mb_substr($bucket, 0, 90);
        Database::run('INSERT INTO sync_rate (bucket,win,cnt) VALUES (?,?,1) ON DUPLICATE KEY UPDATE cnt=cnt+1', [$b, $win]);
        $row = Database::row('SELECT cnt FROM sync_rate WHERE bucket=? AND win=?', [$b, $win]);
        if (mt_rand(1, 50) === 1) Database::run('DELETE FROM sync_rate WHERE win < ?', [$win - 5]);
        return (int)($row['cnt'] ?? 0) <= $limit;
    }
}
