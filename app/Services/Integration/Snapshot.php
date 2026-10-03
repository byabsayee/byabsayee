<?php
namespace App\Services\Integration;

use App\Helpers\Database;

/** Read-only feeds for the store: GET snapshot/{entity} (listing for matching, reconcile, import) and GET changes (recovery). */
final class Snapshot
{
    public static function page(array $conn, string $entity, int $cursor, int $limit): array
    {
        $limit = max(1, min(200, $limit));
        if (!in_array($entity, Util::ENTITIES, true) || $entity === 'stock_movement') throw new ApiError('unsupported_entity', 'No snapshot for ' . $entity . '.', 404);
        if (!Conn::scopeAllows($conn, $entity)) throw new ApiError('scope_denied', 'The connection has no scope for ' . $entity . '.', 403);
        $cls = Registry::mapper($entity);
        $ids = $cls::snapshotIds($conn, $cursor, $limit + 1);
        $more = count($ids) > $limit;
        $ids = array_slice($ids, 0, $limit);
        $items = [];
        foreach ($ids as $id) if ($it = self::item($conn, $entity, (int)$id)) $items[] = $it;
        $single = in_array($entity, ['tax', 'delivery_charge'], true);
        return ['ok' => true, 'entity' => $entity, 'items' => $items, 'cursor' => $single ? null : ($ids ? (string)end($ids) : (string)$cursor), 'has_more' => $more];
    }

    public static function item(array $conn, string $entity, int $id): ?array
    {
        $cls = Registry::mapper($entity); $cid = (int)$conn['id'];
        $fields = $cls::build($conn, $id);
        if ($fields === null) return null;
        $uuid = $cls::uuidFor($conn, $id) ?? Links::uuidFor($cid, $entity, $id);
        $link = Links::get($cid, $entity, $uuid) ?? Links::create($cid, $entity, $id, $uuid);
        // First exposure: remember what the store was shown so later edits are sent as differences from it.
        if (empty($link['last_payload'])) {
            Links::update((int)$link['id'], ['last_payload' => Util::json($fields), 'content_hash' => sha1(Util::json($fields))]);
            $link = Links::get($cid, $entity, $uuid);
        }
        $archived = (bool)$link['archived'];
        if ($entity === 'product') { $p = Database::row('SELECT deleted_at, stock_qty FROM products WHERE id=?', [$id]); $archived = $archived || !empty($p['deleted_at']); }
        if ($entity === 'customer') $archived = $archived || !empty($fields['is_archived']);
        $item = ['entity_uuid' => $uuid, 'version' => (int)$link['local_version'], 'archived' => $archived, 'content_hash' => sha1(Util::json($fields)), 'fields' => $fields];
        if ($entity === 'product') $item['stock'] = ['product' => (int)round((float)($p['stock_qty'] ?? 0)), 'variants' => (object)[]];
        return $item;
    }

    public static function changes(array $conn, int $cursor, int $limit): array
    {
        $limit = max(1, min(200, $limit));
        $rows = Database::query('SELECT id, envelope FROM sync_outbox WHERE conn_id=? AND id>? ORDER BY id ASC LIMIT ' . ($limit + 1), [$conn['id'], $cursor]);
        $more = count($rows) > $limit; $rows = array_slice($rows, 0, $limit);
        return ['ok' => true, 'events' => array_map(fn ($r) => json_decode($r['envelope'], true), $rows), 'cursor' => $rows ? (string)end($rows)['id'] : (string)$cursor, 'has_more' => $more];
    }

    public static function status(array $conn, array $book): array
    {
        $c = Conn::counts((int)$conn['id']);
        return ['ok' => true, 'api_version' => Util::API_VERSION, 'module_version' => Util::MODULE_VERSION, 'capabilities' => Util::CAPABILITIES, 'connection_status' => $conn['status'],
            'queue' => ['pending' => $c['pending'], 'dead' => $c['dead'], 'done' => $c['done'], 'conflict' => $c['conflict']], 'last_sync_at' => Util::isoFromDb($conn['last_sync_at']),
            'server_time' => Util::nowIso(), 'book' => Conn::bookBlock($book, $conn)];
    }
}
