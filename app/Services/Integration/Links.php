<?php
namespace App\Services\Integration;

use App\Helpers\Database;

/**
 * Identity links: the shared entity_uuid <-> the book's local integer id (D: each side keeps its own ids).
 * A link with last_payload = NULL means "known by id, never sent / never exposed yet".
 */
final class Links
{
    public static function get(int $connId, string $entity, string $uuid): ?array
    {
        return Database::row('SELECT * FROM sync_links WHERE conn_id=? AND entity=? AND entity_uuid=?', [$connId, $entity, $uuid]);
    }

    public static function byLocal(int $connId, string $entity, int $localId): ?array
    {
        return Database::row('SELECT * FROM sync_links WHERE conn_id=? AND entity=? AND local_id=?', [$connId, $entity, $localId]);
    }

    public static function create(int $connId, string $entity, ?int $localId, ?string $uuid = null): array
    {
        $uuid = $uuid ?: Util::uuid4();
        Database::run('INSERT INTO sync_links (conn_id,entity,entity_uuid,local_id,created_at) VALUES (?,?,?,?,UTC_TIMESTAMP())', [$connId, $entity, $uuid, $localId]);
        return self::get($connId, $entity, $uuid);
    }

    /** Shared uuid of a local row; creates the link (without sending anything) when it has none yet. */
    public static function uuidFor(int $connId, string $entity, ?int $localId): ?string
    {
        if (!$localId) return null;
        $l = self::byLocal($connId, $entity, $localId) ?? self::create($connId, $entity, $localId);
        return $l['entity_uuid'];
    }

    public static function localFor(int $connId, string $entity, ?string $uuid): ?int
    {
        if (!$uuid) return null;
        $l = self::get($connId, $entity, $uuid);
        return $l && $l['local_id'] ? (int)$l['local_id'] : null;
    }

    public static function update(int $linkId, array $cols): void
    {
        if (!$cols) return;
        $set = []; $vals = [];
        foreach ($cols as $k => $v) { $set[] = "`$k`=?"; $vals[] = $v; }
        $vals[] = $linkId;
        Database::run('UPDATE sync_links SET ' . implode(',', $set) . ' WHERE id=?', $vals);
    }
}
