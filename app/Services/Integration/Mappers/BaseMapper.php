<?php
namespace App\Services\Integration\Mappers;

use App\Services\Integration\Reject;
use App\Services\Integration\Util;

/**
 * An entity mapper knows how to
 *   build()        the shared field set for a local row (null when the row is gone),
 *   apply()        an inbound change from the store onto the book's tables,
 *   snapshotIds()  page through the local rows for the snapshot feed.
 * $conn is always the integration_connections row (its book_id scopes every query).
 */
abstract class BaseMapper
{
    abstract public static function entity(): string;
    abstract public static function build(array $conn, int $id): ?array;
    abstract public static function apply(array $conn, string $op, ?int $id, array $f, array $ctx): ?int;
    /** ids of local rows after $cursor, ascending, for GET snapshot/{entity} */
    abstract public static function snapshotIds(array $conn, int $cursor, int $limit): array;

    /** Fields the store may change here; everything else in an inbound payload is ignored. */
    public static function accepts(): array { return []; }
    /** @return array<int,array{0:string,1:int}> parents that must exist at the store first */
    public static function dependencies(array $conn, int $id): array { return []; }
    public static function cancelFields(): array { return ['is_active' => false]; }
    public static function voidFields(): array { return ['is_active' => false]; }
    /** Singletons (tax, delivery zones) have a derived uuid so both sides agree without a handshake. */
    public static function uuidFor(array $conn, int $id): ?string { return null; }
    /** Sanity-check a built field set before it is sent; return a message to refuse sending it. */
    public static function validate(array $fields): ?string { return null; }
    /** Create-only extras merged into the payload (outside the diffed fields). */
    public static function extra(array $conn, int $id): array { return []; }

    protected static function pick(array $f, array $keys): array { return array_intersect_key($f, array_flip($keys)); }
    protected static function bool($v): int { return in_array($v, [true, 1, '1', 'true'], true) ? 1 : 0; }
    protected static function decimal($v): float
    {
        if (!is_numeric($v)) throw new Reject('invalid_payload', 'A money value is not a number.');
        return round((float)$v, 2);
    }
    protected static function str($v, int $max): ?string
    {
        $v = $v === null ? null : trim((string)$v);
        return ($v === null || $v === '') ? null : mb_substr($v, 0, $max);
    }
    protected static function req(array $f, string $k): void
    {
        if (!array_key_exists($k, $f) || $f[$k] === null || $f[$k] === '') throw new Reject('invalid_payload', "Missing required field '$k'.");
    }
    /** The book's timezone (IANA); orders and payments are dated in it. */
    public static function tz(array $conn): string
    {
        $b = \App\Helpers\Database::row('SELECT timezone FROM books WHERE id=?', [$conn['book_id']]);
        return ($b['timezone'] ?? '') ?: 'Asia/Dhaka';
    }
}
