<?php
namespace App\Services\Integration\Mappers;

use App\Services\Integration\{Conn, Reject, Util};

/** Delivery charge settings: three zones (ids 1..3) kept on the connection, uuids derived from the connection id. */
class DeliveryMapper extends BaseMapper
{
    public const ZONES = [1 => 'inside_dhaka', 2 => 'suburbs', 3 => 'outside_dhaka'];
    public const LABELS = ['inside_dhaka' => 'Inside Dhaka', 'suburbs' => 'Dhaka suburbs', 'outside_dhaka' => 'Outside Dhaka'];

    public static function entity(): string { return 'delivery_charge'; }
    public static function uuid(array $conn, int $i): string { return Util::uuid5($conn['connection_id'], 'delivery:' . self::ZONES[$i]); }
    public static function uuidFor(array $conn, int $id): ?string { return isset(self::ZONES[$id]) ? self::uuid($conn, $id) : null; }

    public static function build(array $conn, int $id): ?array
    {
        $z = self::ZONES[$id] ?? null;
        if (!$z) return null;
        $d = Conn::shared($conn)['delivery'];
        return ['zone' => $z, 'label' => self::LABELS[$z], 'base_fee' => Util::money($d[$z]), 'free_weight_kg' => Util::money($d['free_weight_kg']), 'extra_per_kg' => Util::money($d['extra_per_kg'])];
    }

    public static function snapshotIds(array $conn, int $cursor, int $limit): array { return $cursor < 1 ? [1, 2, 3] : []; }

    public static function apply(array $conn, string $op, ?int $id, array $f, array $ctx): ?int
    {
        $z = self::ZONES[$id] ?? null;
        if (!$z) throw new Reject('unknown_entity', 'Unknown delivery zone.');
        $s = Conn::shared($conn); $d = $s['delivery'];
        foreach (['base_fee' => $z, 'free_weight_kg' => 'free_weight_kg', 'extra_per_kg' => 'extra_per_kg'] as $k => $key) {
            if (!isset($f[$k])) continue;
            if (!is_numeric($f[$k]) || $f[$k] < 0) throw new Reject('invalid_payload', "$k must be a non-negative number.");
            $d[$key] = Util::money($f[$k]);
        }
        $s['delivery'] = $d;
        Conn::setShared((int)$conn['id'], $s);
        return $id;
    }
}
