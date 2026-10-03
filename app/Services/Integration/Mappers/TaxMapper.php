<?php
namespace App\Services\Integration\Mappers;

use App\Services\Integration\{Conn, Reject, Util};

/** One shared tax setting kept on the connection. Its uuid is derived from the connection id so both sides agree. */
class TaxMapper extends BaseMapper
{
    public static function entity(): string { return 'tax'; }
    public static function uuid(array $conn): string { return Util::uuid5($conn['connection_id'], 'tax'); }
    public static function uuidFor(array $conn, int $id): ?string { return $id === 1 ? self::uuid($conn) : null; }

    public static function build(array $conn, int $id): ?array
    {
        if ($id !== 1) return null;
        $t = Conn::shared($conn)['tax'];
        return ['enabled' => (bool)$t['enabled'], 'rate' => number_format((float)$t['rate'], 3, '.', ''), 'inclusive' => (bool)$t['inclusive'], 'label' => (string)$t['label']];
    }

    public static function snapshotIds(array $conn, int $cursor, int $limit): array { return $cursor < 1 ? [1] : []; }

    public static function apply(array $conn, string $op, ?int $id, array $f, array $ctx): ?int
    {
        $s = Conn::shared($conn); $t = $s['tax'];
        if (isset($f['enabled'])) $t['enabled'] = (bool)self::bool($f['enabled']);
        if (isset($f['rate'])) {
            if (!is_numeric($f['rate']) || $f['rate'] < 0 || $f['rate'] > 100) throw new Reject('invalid_payload', 'Tax rate must be between 0 and 100.');
            $t['rate'] = number_format((float)$f['rate'], 3, '.', '');
        }
        if (isset($f['inclusive'])) $t['inclusive'] = (bool)self::bool($f['inclusive']);
        if (isset($f['label'])) $t['label'] = (string)self::str($f['label'], 40);
        $s['tax'] = $t;
        Conn::setShared((int)$conn['id'], $s);
        return 1;
    }
}
