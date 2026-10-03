<?php
namespace App\Services\Integration;

/** Reads the STORE's paged snapshot feed (signed GET snapshot/{entity}); used by history import and reconcile. */
final class Feed
{
    /** @return array{items:array,cursor:string,more:bool,error:?string} */
    public static function page(array $conn, string $entity, string $cursor = '', int $limit = 100): array
    {
        $limit = max(1, min(200, $limit));
        $q = 'snapshot/' . $entity . '?limit=' . $limit . ($cursor !== '' ? '&cursor=' . rawurlencode($cursor) : '');
        $r = Http::store($conn, 'GET', $q, null, ['timeout' => 30]);
        if (!$r['ok'] || !is_array($r['json']) || !isset($r['json']['items'])) {
            return ['items' => [], 'cursor' => $cursor, 'more' => false, 'error' => $r['error'] ?: ('The website answered HTTP ' . $r['status'] . '.')];
        }
        $items = array_values(array_filter((array)$r['json']['items'], fn ($i) => is_array($i) && Util::isUuid($i['entity_uuid'] ?? null)));
        $next = $r['json']['cursor'] ?? $cursor;
        $more = !empty($r['json']['has_more']) && (string)$next !== $cursor;   // a feed that doesn't advance can't loop us forever
        return ['items' => $items, 'cursor' => (string)$next, 'more' => $more, 'error' => null];
    }

    /** Every item of one entity (capped, so a huge store can't hang a web request). @return array{0:array,1:?string,2:bool} items, error, truncated */
    public static function all(array $conn, string $entity, int $maxPages = 60, ?float $deadline = null): array
    {
        $out = []; $cursor = ''; $trunc = false;
        for ($i = 0; $i < $maxPages; $i++) {
            if ($deadline !== null && microtime(true) > $deadline) { $trunc = true; break; }
            $p = self::page($conn, $entity, $cursor, 200);
            if ($p['error']) return [$out, $p['error'], false];
            foreach ($p['items'] as $it) $out[] = $it;
            if (!$p['more']) return [$out, null, false];
            $cursor = $p['cursor'];
        }
        return [$out, null, true];
    }
}
