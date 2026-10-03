<?php
namespace App\Services\Integration;

use App\Helpers\Database;

/**
 * Inbound side: applies signed events from the store exactly once (D6), resolves per-field conflicts
 * (newest wins; an exact tie goes to the BOOK, as the protocol documents) and answers per event.
 */
final class Inbox
{
    /** @return array{0:array,1:array} [accepted fields, accepted field timestamps] */
    private static function lww(array $conn, ?array $link, array $fields, array $fieldTs, string $occurredAt, string $entity, string $uuid): array
    {
        $localTs = $link ? Util::jsonDecode($link['field_ts']) : [];
        $prev    = $link ? Util::jsonDecode($link['last_payload']) : [];
        $accepted = []; $acceptedTs = [];
        foreach ($fields as $k => $v) {
            $remote = Util::ts($fieldTs[$k] ?? $occurredAt) ?? 0.0;
            $mine = Util::ts($localTs[$k] ?? null);
            if ($mine !== null && $mine >= $remote) {      // newer (or tied) local change keeps its value
                Log::write((int)$GLOBALS['__integration_conn_id'], 'in', 'conflict', "Kept the newer book value of $entity.$k", true, null, null, ['entity' => $entity, 'uuid' => $uuid, 'field' => $k]);
                continue;
            }
            if ($mine !== null && array_key_exists($k, $prev) && Util::json($prev[$k]) !== Util::json($v)) {
                Log::write((int)$GLOBALS['__integration_conn_id'], 'in', 'overwrite', "The store's value replaced the book's for $entity.$k", true, null, null, ['entity' => $entity, 'uuid' => $uuid, 'field' => $k, 'old' => $prev[$k] ?? null, 'new' => $v]);
            }
            $accepted[$k] = $v; $acceptedTs[$k] = $fieldTs[$k] ?? $occurredAt;
        }
        return [$accepted, $acceptedTs];
    }

    /** @return array{event_id:?string,result:string,code?:string,message?:string,retry?:bool} */
    public static function one(array $conn, $ev): array
    {
        $GLOBALS['__integration_conn_id'] = (int)$conn['id'];
        $cid = (int)$conn['id'];
        $eid = is_array($ev) && isset($ev['event_id']) && is_string($ev['event_id']) ? $ev['event_id'] : null;
        $rej = fn (string $code, string $msg, bool $retry = false) => ['event_id' => $eid, 'result' => 'rejected', 'code' => $code, 'message' => $msg, 'retry' => $retry];

        if (!is_array($ev) || !Util::isUuid($eid)) return $rej('invalid_envelope', 'event_id must be a UUID.');
        foreach (['connection_id', 'origin', 'entity', 'entity_uuid', 'op', 'version', 'occurred_at', 'payload'] as $k) if (!array_key_exists($k, $ev)) return $rej('invalid_envelope', "Missing '$k'.");
        if (!hash_equals((string)$conn['connection_id'], (string)$ev['connection_id'])) return $rej('unknown_connection', 'connection_id does not match.');
        if ($ev['origin'] !== 'site') return $rej('invalid_envelope', "origin must be 'site'.");
        if (!is_string($ev['entity']) || !in_array($ev['entity'], Util::ENTITIES, true)) return $rej('unsupported_entity', 'Unknown entity.');
        if (!is_string($ev['op']) || !in_array($ev['op'], Util::OPS, true)) return $rej('invalid_envelope', 'Unknown op.');
        if (!Util::isUuid($ev['entity_uuid'])) return $rej('invalid_envelope', 'entity_uuid must be a UUID.');
        if (!is_int($ev['version']) || $ev['version'] < 1) return $rej('invalid_envelope', 'version must be a positive integer.');
        if (!is_array($ev['payload']) || Util::ts((string)$ev['occurred_at']) === null) return $rej('invalid_envelope', 'payload/occurred_at invalid.');
        if (!Conn::scopeAllows($conn, $ev['entity'])) return $rej('scope_denied', 'The connection has no scope for ' . $ev['entity'] . '.');
        if ($conn['status'] !== 'active') return $rej('connection_paused', 'This connection is not active.', true);

        if (Database::row('SELECT 1 FROM sync_inbox WHERE event_id=?', [$eid])) return ['event_id' => $eid, 'result' => 'duplicate'];

        $entity = $ev['entity']; $uuid = $ev['entity_uuid']; $op = $ev['op'];
        $cls = Registry::mapper($entity);
        $fields  = is_array($ev['payload']['fields'] ?? null) ? $ev['payload']['fields'] : [];
        $fieldTs = is_array($ev['payload']['field_ts'] ?? null) ? $ev['payload']['field_ts'] : [];
        $pdo = Database::get();

        try {
            $pdo->beginTransaction();
            $newId = null;
            Applying::run(function () use (&$newId, $conn, $cid, $entity, $uuid, &$op, $cls, $fields, $fieldTs, $ev, $eid, $pdo) {
                $link = Links::get($cid, $entity, $uuid);
                if (!$link) {
                    $fixed = null;                                       // singletons (tax, delivery zones) have derived uuids
                    foreach ([1, 2, 3] as $i) if ($cls::uuidFor($conn, $i) === $uuid) { $fixed = $i; break; }
                    if ($fixed) $link = Links::create($cid, $entity, $fixed, $uuid);
                }
                if (!$link) {
                    if ($op !== 'create') throw new Reject('unknown_entity', "The book has no $entity with that id yet (send it as a create first).", true);
                    $link = Links::create($cid, $entity, null, $uuid);
                }
                $localId = $link['local_id'] ? (int)$link['local_id'] : null;
                if ($op === 'create' && $localId) $op = 'update';           // a second "create" for something we already have is an update
                [$accepted, $acceptedTs] = in_array($op, ['create', 'update'], true) ? self::lww($conn, $link, $fields, $fieldTs, (string)$ev['occurred_at'], $entity, $uuid) : [$fields, []];
                $newId = $cls::apply($conn, $op, $localId, $accepted, ['uuid' => $uuid, 'event' => $ev, 'link' => $link]);

                $built = $newId ? $cls::build($conn, (int)$newId) : null;
                $ts = Util::jsonDecode($link['field_ts']);
                foreach ($acceptedTs as $k => $t) $ts[$k] = $t;
                Links::update((int)$link['id'], [
                    'local_id' => $newId ?: $localId, 'remote_version' => max((int)$link['remote_version'], (int)$ev['version']),
                    'last_payload' => $built !== null ? Util::json($built) : $link['last_payload'], 'content_hash' => $built !== null ? sha1(Util::json($built)) : $link['content_hash'],
                    'field_ts' => Util::json($ts), 'archived' => $op === 'archive' ? 1 : ($op === 'restore' ? 0 : (int)$link['archived']), 'last_synced_at' => Util::utcNow(),
                ]);
                Database::run("INSERT INTO sync_inbox (event_id,conn_id,entity,entity_uuid,op,result,received_at) VALUES (?,?,?,?,?,'applied',UTC_TIMESTAMP())", [$eid, $cid, $entity, $uuid, $ev['op']]);
            });
            $pdo->commit();
            $res = ['event_id' => $eid, 'result' => 'applied'];
            // An order becomes a sales invoice here: tell the store its invoice number in the same answer, so it can show the
            // Book's invoice to its customers without another round trip. Additive field; stores that don't know it ignore it.
            if ($entity === 'order' && $newId) {
                try { if ($inv = InvoiceBridge::info($conn, (int)$newId)) $res['invoice'] = $inv; } catch (\Throwable $e) { /* never fail an applied event over this */ }
            }
            return $res;
        } catch (ConflictResult $c) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $conflictId = Conflicts::add($cid, $c->kind, $entity, $uuid, null, $eid, $c->local, $c->kind === 'customer_match' ? $ev['payload'] : $c->remote, $c->getMessage());
            // keep the event body for customer matches so the screen can finish the job later
            if ($c->kind === 'customer_match') Database::run('UPDATE sync_conflicts SET remote_data=? WHERE id=?', [Util::json(['fields' => $c->remote]), $conflictId]);
            Database::run("INSERT IGNORE INTO sync_inbox (event_id,conn_id,entity,entity_uuid,op,result,detail,received_at) VALUES (?,?,?,?,?,'conflict',?,UTC_TIMESTAMP())", [$eid, $cid, $entity, $uuid, $ev['op'], 'conflict #' . $conflictId]);
            Log::write($cid, 'in', 'conflict', 'Queued a ' . $c->kind . ' conflict for ' . $entity . ': ' . $c->getMessage(), false, $eid);
            return ['event_id' => $eid, 'result' => 'conflict', 'message' => $c->getMessage(), 'conflict_id' => $conflictId];
        } catch (Reject $r) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            Log::write($cid, 'in', 'reject', "Rejected $entity/$op: " . $r->getMessage(), false, $eid, null, ['code' => $r->errCode]);
            return $rej($r->errCode, $r->getMessage(), $r->retry);
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('[integration inbound] ' . $e->getMessage() . ' @' . $e->getFile() . ':' . $e->getLine());
            Log::write($cid, 'in', 'error', "Failed to apply $entity/$op: " . $e->getMessage(), false, $eid);
            return $rej('apply_failed', 'The book could not apply this event.', true);
        }
    }

    /** POST events. @return array the response body */
    public static function batch(array $conn, array $body): array
    {
        $events = $body['events'] ?? null;
        if (!is_array($events) || !array_is_list($events)) throw new ApiError('invalid_request', "Body must contain an 'events' array.", 400);
        if (count($events) > Util::BATCH_MAX) throw new ApiError('batch_too_large', 'At most ' . Util::BATCH_MAX . ' events per request.', 413);
        $results = [];
        foreach ($events as $ev) $results[] = self::one($conn, $ev);
        $applied = count(array_filter($results, fn ($r) => $r['result'] === 'applied'));
        if ($applied) Conn::update((int)$conn['id'], ['last_sync_at' => Util::utcNow(), 'last_error' => null]);
        Log::write((int)$conn['id'], 'in', 'events', 'Received ' . count($events) . ' event(s): ' . json_encode(array_count_values(array_column($results, 'result'))), true, null, 200, ['batch_id' => $body['batch_id'] ?? null]);
        return ['ok' => true, 'batch_id' => $body['batch_id'] ?? null, 'results' => $results];
    }
}
