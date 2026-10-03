<?php
namespace App\Services;

use App\Helpers\Database;

/**
 * One place for stock-ledger changes so every code path (invoices, POS, returns, adjustments and the
 * online-store sync) keeps products.stock_qty and the FIFO/LIFO layers in product_batches consistent.
 *
 * products.stock_qty is the quantity of record; product_batches are the cost layers drawn down in the
 * book's inventory method order. Nothing here silently clamps at zero unless the caller asks for it.
 */
final class InventoryService
{
    public static function method(int $bookId): string
    {
        $d = Database::row('SELECT inventory_method FROM book_business_details WHERE book_id=?', [$bookId]);
        return ($d['inventory_method'] ?? 'FIFO') === 'LIFO' ? 'LIFO' : 'FIFO';
    }

    /** Take $qty out of the cost layers (oldest first for FIFO, newest first for LIFO). Does not touch stock_qty. */
    public static function drawBatches(int $productId, int $bookId, float $qty, ?string $method = null): void
    {
        $order = (($method ?? self::method($bookId)) === 'FIFO') ? 'ASC' : 'DESC';
        $batches = Database::query(
            "SELECT id,remaining_qty FROM product_batches WHERE product_id=? AND book_id=? AND remaining_qty>0 ORDER BY created_at {$order}, id {$order}",
            [$productId, $bookId]
        );
        $remaining = $qty;
        foreach ($batches as $b) {
            if ($remaining <= 0) break;
            $take = min((float)$b['remaining_qty'], $remaining);
            Database::run('UPDATE product_batches SET remaining_qty=remaining_qty-? WHERE id=?', [$take, $b['id']]);
            $remaining -= $take;
        }
    }

    /**
     * A sale: take stock out. With $strict the product row is locked and the sale is refused (exception) when
     * there is not enough — never silently clamped. Without $strict it behaves like the legacy screens (floors at 0).
     */
    public static function sell(int $bookId, int $productId, float $qty, bool $strict = false): void
    {
        if ($qty <= 0) return;
        $p = Database::row('SELECT stock_qty FROM products WHERE id=? AND book_id=? FOR UPDATE', [$productId, $bookId]);
        if (!$p) return;
        if ($strict && (float)$p['stock_qty'] + 1e-9 < $qty) throw new InsufficientStockException($productId, (float)$p['stock_qty'], $qty);
        self::drawBatches($productId, $bookId, $qty);
        if ($strict) Database::run('UPDATE products SET stock_qty=stock_qty-? WHERE id=? AND book_id=?', [$qty, $productId, $bookId]);
        else         Database::run('UPDATE products SET stock_qty=GREATEST(0,stock_qty-?) WHERE id=? AND book_id=?', [$qty, $productId, $bookId]);
    }

    /** Put stock back / receive stock: a new cost layer plus the quantity of record. */
    public static function receive(int $bookId, int $productId, float $qty, ?float $buyPrice = null): void
    {
        if ($qty <= 0) return;
        $p = Database::row('SELECT buy_price FROM products WHERE id=? AND book_id=? FOR UPDATE', [$productId, $bookId]);
        if (!$p) return;
        $price = $buyPrice ?? (float)$p['buy_price'];
        $n = (int)(Database::row('SELECT COUNT(*)+1 AS n FROM product_batches WHERE product_id=?', [$productId])['n'] ?? 1);
        $barcode = 'BC' . str_pad((string)$bookId, 3, '0', STR_PAD_LEFT) . str_pad((string)$productId, 5, '0', STR_PAD_LEFT) . str_pad((string)$n, 4, '0', STR_PAD_LEFT);
        if (Database::row('SELECT id FROM product_batches WHERE barcode=?', [$barcode])) $barcode .= random_int(10, 99);
        Database::run(
            'INSERT INTO product_batches (product_id,book_id,barcode,buy_price,sell_price,initial_qty,remaining_qty,created_at) VALUES (?,?,?,?,0,?,?,?)',
            [$productId, $bookId, $barcode, $price, $qty, $qty, now()]
        );
        Database::run('UPDATE products SET stock_qty=stock_qty+? WHERE id=? AND book_id=?', [$qty, $productId, $bookId]);
    }

    /** A manual removal (adjustment, purchase return): draws the layers and lowers the quantity; may go below zero so drift is visible. */
    public static function remove(int $bookId, int $productId, float $qty): void
    {
        if ($qty <= 0) return;
        Database::run('SELECT 1 FROM products WHERE id=? AND book_id=? FOR UPDATE', [$productId, $bookId]);
        self::drawBatches($productId, $bookId, $qty);
        Database::run('UPDATE products SET stock_qty=stock_qty-? WHERE id=? AND book_id=?', [$qty, $productId, $bookId]);
    }

    public static function onHand(int $bookId, int $productId): float
    {
        $p = Database::row('SELECT stock_qty FROM products WHERE id=? AND book_id=?', [$productId, $bookId]);
        return $p ? (float)$p['stock_qty'] : 0.0;
    }
}
