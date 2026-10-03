<?php
namespace App\Services\Integration;

use App\Helpers\Database;

/** Redacted sync log (30 days of detail, 90 days of summaries — see Worker::prune()). Never throws. */
final class Log
{
    private const REDACT = ['email','customer_email','phone','shipping_phone','billing_phone','name','shipping_name','billing_name','line1','address',
        'city','state','zip','secret','secrets','api_key','signature','authorization','password','token','pairing_code','notes','reference','contact','shipping','billing'];

    public static function redact($v)
    {
        if (!is_array($v)) return $v;
        $out = [];
        foreach ($v as $k => $x) {
            if (is_string($k) && in_array(strtolower($k), self::REDACT, true)) { $out[$k] = '[redacted]'; continue; }
            $out[$k] = self::redact($x);
        }
        return $out;
    }

    public static function write(?int $connId, string $direction, string $kind, string $summary, bool $ok = true, ?string $eventId = null, ?int $http = null, $detail = null): void
    {
        try {
            $d = $detail === null ? null : (is_string($detail) ? $detail : json_encode(self::redact($detail), JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR));
            Database::run(
                'INSERT INTO sync_log (conn_id,direction,kind,event_id,http_status,ok,summary,detail,created_at) VALUES (?,?,?,?,?,?,?,?,UTC_TIMESTAMP())',
                [$connId, $direction, $kind, $eventId, $http, $ok ? 1 : 0, mb_substr($summary, 0, 500), $d !== null ? mb_substr($d, 0, 4000) : null]
            );
        } catch (\Throwable $e) { error_log('[integration log] ' . $e->getMessage()); }
    }
}
