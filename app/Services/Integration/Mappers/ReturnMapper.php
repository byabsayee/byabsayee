<?php
namespace App\Services\Integration\Mappers;

use App\Helpers\Database;
use App\Services\Integration\{ConflictResult, Links, OrderService, Reject, Util};

/** A recorded return is final; the store has no edit for it. */
class ReturnMapper extends BaseMapper
{
    public static function entity(): string { return 'return'; }
    public static function cancelFields(): array { return []; }

    public static function dependencies(array $conn, int $id): array
    {
        $r = Database::row('SELECT invoice_id, refund_method FROM returns WHERE id=? AND book_id=?', [$id, $conn['book_id']]);
        return ($r && $r['invoice_id']) ? [['order', (int)$r['invoice_id']]] : [];
    }

    public static function build(array $conn, int $id): ?array
    {
        $r = Database::row('SELECT * FROM returns WHERE id=? AND book_id=? AND type="sales_return"', [$id, $conn['book_id']]);
        if (!$r || !$r['invoice_id']) return null;
        $items = [];
        foreach (Database::query('SELECT * FROM return_items WHERE return_id=? ORDER BY id', [$id]) as $n => $i) {
            $items[] = ['line' => $n + 1, 'product_uuid' => $i['product_id'] ? Links::uuidFor($conn['id'], 'product', (int)$i['product_id']) : null, 'variant_uuid' => null,
                'quantity' => max(1, (int)round((float)$i['qty'])), 'restock' => true];
        }
        $m = $r['refund_method'] ? Database::row('SELECT id FROM invoice_method_options WHERE book_id=? AND type="payment" AND LOWER(label)=LOWER(?)', [$conn['book_id'], $r['refund_method']]) : null;
        return ['order_uuid' => Links::uuidFor($conn['id'], 'order', (int)$r['invoice_id']), 'reason' => $r['remarks'], 'refund_amount' => Util::money($r['total_refund']),
            'refund_method_uuid' => $m ? Links::uuidFor($conn['id'], 'payment_method', (int)$m['id']) : null,
            'returned_at' => Util::isoFromDb(Util::fromZone($r['created_at'], self::tz($conn))), 'items' => $items];
    }

    public static function snapshotIds(array $conn, int $cursor, int $limit): array
    {
        return array_map('intval', array_column(Database::query(
            'SELECT r.id FROM returns r JOIN invoices i ON i.id=r.invoice_id WHERE r.book_id=? AND r.type="sales_return" AND r.deleted_at IS NULL AND (i.source="online_store" OR i.sync_to_store=1) AND r.id>? ORDER BY r.id LIMIT ' . (int)$limit,
            [$conn['book_id'], $cursor]), 'id'));
    }

    public static function apply(array $conn, string $op, ?int $id, array $f, array $ctx): ?int
    {
        if ($id) return $id;
        self::req($f, 'order_uuid'); self::req($f, 'items');
        $oid = Links::localFor($conn['id'], 'order', $f['order_uuid']);
        if (!$oid) throw new Reject('dependency_missing', 'The order for this return is not known yet.', true);
        $items = [];
        foreach ((array)$f['items'] as $i) {
            $pid = !empty($i['product_uuid']) ? Links::localFor($conn['id'], 'product', $i['product_uuid']) : null;
            if (!$pid) throw new ConflictResult('other', 'A returned item is not linked to a product here.', null, $f);
            $items[] = ['product_id' => $pid, 'qty' => (float)(int)($i['quantity'] ?? 0), 'restock' => !empty($i['restock'])];
        }
        $label = null;
        if (!empty($f['refund_method_uuid'])) $label = PaymentMethodMapper::labelFor($conn, Links::localFor($conn['id'], 'payment_method', $f['refund_method_uuid']));
        try { return OrderService::createReturn($conn, $oid, $items, self::str($f['reason'] ?? null, 255), (float)($f['refund_amount'] ?? 0), $label, Util::dbFromIso($f['returned_at'] ?? null)); }
        catch (\RuntimeException $e) { throw new ConflictResult('other', $e->getMessage(), null, $f); }
    }
}
