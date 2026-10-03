<?php
namespace App\Services\Integration\Mappers;

use App\Helpers\Database;
use App\Services\Integration\{ConflictResult, Reject, Util};

class CouponMapper extends BaseMapper
{
    public static function entity(): string { return 'coupon'; }
    public static function accepts(): array { return ['code', 'type', 'value', 'max_discount', 'min_subtotal', 'starts_at', 'expires_at', 'usage_limit', 'per_customer_limit', 'is_active', 'note']; }

    public static function build(array $conn, int $id): ?array
    {
        $c = Database::row('SELECT * FROM coupons WHERE id=? AND book_id=?', [$id, $conn['book_id']]);
        if (!$c) return null;
        $tz = self::tz($conn);
        return ['code' => $c['code'], 'type' => $c['discount_type'], 'value' => Util::money($c['discount_value']),
            'max_discount' => $c['max_discount'] !== null ? Util::money($c['max_discount']) : null, 'min_subtotal' => Util::money($c['min_subtotal']),
            'starts_at' => ($u = Util::fromZone($c['starts_at'], $tz)) ? Util::isoFromDb($u) : null,
            'expires_at' => ($u = Util::fromZone($c['expires_at'], $tz)) ? Util::isoFromDb($u) : null,
            'usage_limit' => $c['usage_limit'] !== null ? (int)$c['usage_limit'] : null, 'per_customer_limit' => $c['per_customer_limit'] !== null ? (int)$c['per_customer_limit'] : null,
            'is_active' => (bool)$c['is_active'], 'note' => $c['note']];
    }

    public static function snapshotIds(array $conn, int $cursor, int $limit): array
    {
        return array_map('intval', array_column(Database::query('SELECT id FROM coupons WHERE book_id=? AND id>? ORDER BY id LIMIT ' . (int)$limit, [$conn['book_id'], $cursor]), 'id'));
    }

    public static function apply(array $conn, string $op, ?int $id, array $f, array $ctx): ?int
    {
        $bid = (int)$conn['book_id']; $tz = self::tz($conn);
        if ($op === 'archive' || $op === 'restore') {
            if ($id) Database::run('UPDATE coupons SET is_active=? WHERE id=? AND book_id=?', [$op === 'restore' ? 1 : 0, $id, $bid]);
            return $id;
        }
        $f = self::pick($f, self::accepts());
        if (isset($f['type']) && !in_array($f['type'], ['percent', 'fixed'], true)) throw new Reject('invalid_payload', 'Coupon type must be percent or fixed.');
        if (isset($f['code'])) {
            $f['code'] = strtoupper(preg_replace('/\s+/', '', (string)$f['code']));
            $dup = Database::row('SELECT id FROM coupons WHERE book_id=? AND code=? AND id<>?', [$bid, $f['code'], $id ?: 0]);
            if ($dup) {
                if (!$id) return (int)$dup['id'];            // same code on create = same coupon: link it
                throw new ConflictResult('other', 'Another coupon here already uses the code ' . $f['code'] . '.', null, $f);
            }
        }
        $local = fn ($iso) => ($u = Util::dbFromIso($iso)) ? Util::toZone($u, $tz) : null;
        $cols = [
            'code' => fn ($v) => self::str($v, 30), 'discount_type' => fn ($v) => $v, 'discount_value' => fn ($v) => self::decimal($v),
            'max_discount' => fn ($v) => $v === null ? null : self::decimal($v), 'min_subtotal' => fn ($v) => $v === null ? 0 : self::decimal($v),
            'starts_at' => $local, 'expires_at' => $local,
            'usage_limit' => fn ($v) => $v === null ? null : max(0, (int)$v), 'per_customer_limit' => fn ($v) => $v === null ? null : max(0, (int)$v),
            'is_active' => fn ($v) => self::bool($v), 'note' => fn ($v) => self::str($v, 2000),
        ];
        $src = ['code' => 'code', 'discount_type' => 'type', 'discount_value' => 'value', 'max_discount' => 'max_discount', 'min_subtotal' => 'min_subtotal',
            'starts_at' => 'starts_at', 'expires_at' => 'expires_at', 'usage_limit' => 'usage_limit', 'per_customer_limit' => 'per_customer_limit', 'is_active' => 'is_active', 'note' => 'note'];
        if (!$id) {
            self::req($f, 'code'); self::req($f, 'type'); self::req($f, 'value');
            $names = ['book_id', 'name', 'created_at']; $vals = [$bid, self::str($f['note'] ?? null, 120) ?: $f['code'], now()];
            foreach ($cols as $col => $fn) if (array_key_exists($src[$col], $f)) { $names[] = $col; $vals[] = $fn($f[$src[$col]]); }
            Database::run('INSERT INTO coupons (' . implode(',', $names) . ') VALUES (' . implode(',', array_fill(0, count($names), '?')) . ')', $vals);
            return Database::lastId();
        }
        $set = []; $vals = [];
        foreach ($cols as $col => $fn) if (array_key_exists($src[$col], $f)) { $set[] = "$col=?"; $vals[] = $fn($f[$src[$col]]); }
        if ($set) { $vals[] = $id; $vals[] = $bid; Database::run('UPDATE coupons SET ' . implode(',', $set) . ' WHERE id=? AND book_id=?', $vals); }
        return $id;
    }
}
