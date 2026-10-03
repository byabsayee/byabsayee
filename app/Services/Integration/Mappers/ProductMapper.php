<?php
namespace App\Services\Integration\Mappers;

use App\Helpers\Database;
use App\Services\InventoryService;
use App\Services\Integration\{Links, Reject, ConflictResult, Util};

class ProductMapper extends BaseMapper
{
    public static function entity(): string { return 'product'; }
    /** Shared with the store. Buy price, barcode, unit, batches, descriptions and photos are never exchanged. */
    public static function accepts(): array { return ['name', 'sku', 'price', 'weight_grams', 'is_active', 'category_uuid']; }

    public static function dependencies(array $conn, int $id): array
    {
        $r = Database::row('SELECT category_id FROM products WHERE id=? AND book_id=?', [$id, $conn['book_id']]);
        return ($r && $r['category_id']) ? [['category', (int)$r['category_id']]] : [];
    }

    public static function build(array $conn, int $id): ?array
    {
        $p = Database::row('SELECT * FROM products WHERE id=? AND book_id=?', [$id, $conn['book_id']]);
        if (!$p) return null;
        return ['sku' => $p['sku'], 'name' => $p['name'], 'price' => Util::money($p['sell_price']),
            'weight_grams' => $p['weight_grams'] !== null ? (int)$p['weight_grams'] : null,
            'is_active' => (bool)$p['is_active'] && empty($p['deleted_at']),
            'category_uuid' => !empty($p['category_id']) ? Links::uuidFor($conn['id'], 'category', (int)$p['category_id']) : null];
    }

    /** Opening quantity travels once, on create, so the store can record an opening balance. */
    public static function extra(array $conn, int $id): array
    {
        $p = Database::row('SELECT stock_qty FROM products WHERE id=? AND book_id=?', [$id, $conn['book_id']]);
        $q = $p ? (int)round((float)$p['stock_qty']) : 0;
        return $q > 0 ? ['opening_stock' => ['product' => $q, 'variants' => (object)[]]] : [];
    }

    public static function snapshotIds(array $conn, int $cursor, int $limit): array
    {
        return array_map('intval', array_column(Database::query('SELECT id FROM products WHERE book_id=? AND id>? ORDER BY id LIMIT ' . (int)$limit, [$conn['book_id'], $cursor]), 'id'));
    }

    private static function codes(int $bookId, int $pid): void
    {
        $code = 'PRD-' . str_pad((string)$pid, 5, '0', STR_PAD_LEFT);
        $bc = 'BC' . str_pad((string)$bookId, 3, '0', STR_PAD_LEFT) . str_pad((string)$pid, 6, '0', STR_PAD_LEFT);
        Database::run('UPDATE products SET product_code=?, barcode=? WHERE id=?', [$code, $bc, $pid]);
    }

    public static function apply(array $conn, string $op, ?int $id, array $f, array $ctx): ?int
    {
        $bid = (int)$conn['book_id'];
        if ($op === 'archive') { if ($id) Database::run('UPDATE products SET is_active=0, deleted_at=? WHERE id=? AND book_id=?', [now(), $id, $bid]); return $id; }
        if ($op === 'restore') { if ($id) Database::run('UPDATE products SET is_active=1, deleted_at=NULL WHERE id=? AND book_id=?', [$id, $bid]); return $id; }
        $f = self::pick($f, self::accepts());
        $cat = null;
        if (array_key_exists('category_uuid', $f) && $f['category_uuid'] !== null) {
            $cat = Links::localFor($conn['id'], 'category', $f['category_uuid']);
            if (!$cat) throw new Reject('dependency_missing', 'The product category is not known yet.', true);
        }
        if (isset($f['sku']) && $f['sku'] !== '') {
            $dup = Database::row('SELECT id FROM products WHERE book_id=? AND sku=? AND id<>? LIMIT 1', [$bid, $f['sku'], $id ?: 0]);
            if ($dup) throw new ConflictResult('other', 'Another product here already uses the SKU ' . $f['sku'] . '.', ['id' => (int)$dup['id']], $f);
        }
        if (!$id) {
            self::req($f, 'name'); self::req($f, 'price');
            Database::run(
                'INSERT INTO products (book_id,category_id,name,sku,barcode,unit,buy_price,sell_price,stock_qty,low_stock_alert,is_active,weight_grams,created_at) VALUES (?,?,?,?,?,?,0,?,0,5,?,?,?)',
                [$bid, $cat, self::str($f['name'], 150), self::str($f['sku'] ?? null, 60), null, 'pcs', self::decimal($f['price']), self::bool($f['is_active'] ?? true),
                 isset($f['weight_grams']) ? max(1, (int)$f['weight_grams']) : null, now()]
            );
            $id = Database::lastId();
            self::codes($bid, $id);
            $os = $ctx['event']['payload']['opening_stock']['product'] ?? null;
            if (is_numeric($os) && (int)$os > 0) InventoryService::receive($bid, $id, (float)(int)$os, 0.0);
            return $id;
        }
        $set = []; $v = [];
        if (array_key_exists('name', $f)) { $set[] = 'name=?'; $v[] = self::str($f['name'], 150) ?? 'Product'; }
        if (array_key_exists('sku', $f)) { $set[] = 'sku=?'; $v[] = self::str($f['sku'], 60); }
        if (isset($f['price'])) { $set[] = 'sell_price=?'; $v[] = self::decimal($f['price']); }
        if (array_key_exists('weight_grams', $f)) { $set[] = 'weight_grams=?'; $v[] = $f['weight_grams'] === null ? null : max(1, (int)$f['weight_grams']); }
        if (isset($f['is_active'])) { $set[] = 'is_active=?'; $v[] = self::bool($f['is_active']); }
        if (array_key_exists('category_uuid', $f)) { $set[] = 'category_id=?'; $v[] = $cat; }
        if ($set) { $v[] = $id; $v[] = $bid; Database::run('UPDATE products SET ' . implode(',', $set) . ' WHERE id=? AND book_id=?', $v); }
        return $id;
    }
}
