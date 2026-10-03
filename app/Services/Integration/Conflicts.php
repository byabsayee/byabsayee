<?php
namespace App\Services\Integration;

use App\Helpers\Database;

/** The conflict queue: things the sync refused to apply silently and a person has to look at. */
final class Conflicts
{
    public static function add(int $connId, string $kind, string $entity, ?string $uuid, ?int $localId, ?string $eventId, $local, $remote, string $note): int
    {
        Database::run('INSERT INTO sync_conflicts (conn_id,kind,entity,entity_uuid,local_id,event_id,local_data,remote_data,note,created_at) VALUES (?,?,?,?,?,?,?,?,?,UTC_TIMESTAMP())',
            [$connId, $kind, $entity, $uuid, $localId, $eventId, $local === null ? null : Util::json($local), $remote === null ? null : Util::json($remote), mb_substr($note, 0, 500)]);
        return Database::lastId();
    }

    public static function open(int $connId, int $limit = 50): array
    {
        return Database::query("SELECT * FROM sync_conflicts WHERE conn_id=? AND status='open' ORDER BY id DESC LIMIT " . (int)$limit, [$connId]);
    }

    public static function resolveRow(int $id, string $resolution, string $by): void
    {
        Database::run("UPDATE sync_conflicts SET status='resolved', resolution=?, resolved_by=?, resolved_at=UTC_TIMESTAMP() WHERE id=?", [$resolution, mb_substr($by, 0, 120), $id]);
    }

    /**
     * Resolve a customer_match conflict: 'link' ties the incoming customer to the existing one,
     * 'separate' keeps them as two people. @return string a message for the screen
     */
    public static function resolveCustomerMatch(array $conn, int $conflictId, string $action, string $by): string
    {
        $c = Database::row("SELECT * FROM sync_conflicts WHERE id=? AND conn_id=? AND kind='customer_match' AND status='open'", [$conflictId, $conn['id']]);
        if (!$c) return 'That conflict was already resolved.';
        $ev = Util::jsonDecode($c['remote_data']); $local = Util::jsonDecode($c['local_data']);
        $uuid = $c['entity_uuid'];
        $pdo = Database::get();
        $pdo->beginTransaction();
        try {
            if ($action === 'link' && !empty($local['id'])) {
                if ($old = Links::byLocal($conn['id'], 'customer', (int)$local['id'])) Database::run('DELETE FROM sync_links WHERE id=?', [$old['id']]);
                if (!Links::get($conn['id'], 'customer', $uuid)) Links::create($conn['id'], 'customer', (int)$local['id'], $uuid);
                $l = Links::get($conn['id'], 'customer', $uuid);
                $built = Mappers\CustomerMapper::build($conn, (int)$local['id']);
                Links::update((int)$l['id'], ['local_id' => (int)$local['id'], 'last_payload' => Util::json($built), 'content_hash' => sha1(Util::json($built)), 'last_synced_at' => Util::utcNow()]);
                $res = 'linked'; $msg = 'Linked to the existing customer.';
            } elseif ($action === 'separate') {
                $f = $ev['fields'] ?? ($ev['payload']['fields'] ?? $ev);
                $id = Applying::run(fn () => Mappers\CustomerMapper::apply($conn, 'create', null, is_array($f) ? $f : [], ['uuid' => $uuid, 'force_new' => true]));
                $l = Links::get($conn['id'], 'customer', $uuid) ?? Links::create($conn['id'], 'customer', $id, $uuid);
                $built = Mappers\CustomerMapper::build($conn, (int)$id);
                Links::update((int)$l['id'], ['local_id' => $id, 'last_payload' => Util::json($built), 'content_hash' => sha1(Util::json($built)), 'last_synced_at' => Util::utcNow()]);
                $res = 'kept_separate'; $msg = 'Kept as a separate customer.';
            } else { $pdo->rollBack(); return 'Choose link or keep separate.'; }
            self::resolveRow($conflictId, $res, $by);
            $pdo->commit();
            return $msg;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            return 'Could not resolve it: ' . $e->getMessage();
        }
    }
}
