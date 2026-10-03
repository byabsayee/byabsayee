<?php
namespace App\Services\Integration\Mappers;

use App\Helpers\Database;
use App\Services\{InventoryService, InsufficientStockException};
use App\Services\Integration\{Links, Reject};

/**
 * Stock travels as movements (deltas), never as absolute overwrites (D4). The book keeps one total per product
 * (a product's variants are the store's business; the book tracks the sum), and its own history in stock_adjustments.
 */
class StockMapper extends BaseMapper
{
    public static function entity(): string { return 'stock_movement'; }
    public static function cancelFields(): array { return []; }
    public static function build(array $conn, int $id): ?array { return null; }
    public static function snapshotIds(array $conn, int $cursor, int $limit): array { return []; }

    public static function apply(array $conn, string $op, ?int $id, array $f, array $ctx): ?int
    {
        if ($id) return $id;                       // a movement never changes once recorded
        self::req($f, 'product_uuid'); self::req($f, 'delta');
        $reason = (string)($f['reason'] ?? 'manual_adjustment');
        if (!in_array($reason, ['sale', 'sale_cancel', 'return', 'manual_adjustment', 'purchase', 'reconciliation_adjustment'], true)) throw new Reject('invalid_payload', 'Unknown stock reason.');
        $pid = Links::localFor($conn['id'], 'product', $f['product_uuid']);
        if (!$pid) throw new Reject('dependency_missing', 'The product is not known yet.', true);
        $delta = (int)$f['delta'];
        if ($delta === 0) throw new Reject('invalid_payload', 'A stock movement needs a non-zero delta.');
        $bid = (int)$conn['book_id'];
        $note = 'Online store (' . $reason . ')' . (!empty($f['note']) ? ': ' . self::str($f['note'], 200) : '');
        try {
            if ($delta > 0) InventoryService::receive($bid, $pid, (float)$delta);
            elseif ($reason === 'sale') InventoryService::sell($bid, $pid, (float)-$delta, true);
            else InventoryService::remove($bid, $pid, (float)-$delta);
        } catch (InsufficientStockException $e) { throw new Reject('insufficient_stock', $e->getMessage()); }
        Database::run('INSERT INTO stock_adjustments (product_id,type,qty,note,created_by,created_at) VALUES (?,?,?,?,NULL,?)', [$pid, $delta > 0 ? 'add' : 'remove', abs($delta), $note, now()]);
        return Database::lastId();
    }
}
