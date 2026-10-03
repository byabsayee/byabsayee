<?php
namespace App\Services\Integration\Mappers;

use App\Helpers\Database;
use App\Services\Integration\{Conflicts, Links, OrderService, Reject, ConflictResult, Util};

/** An order is a sales invoice. Store-origin orders carry source='online_store'; book-origin ones are flagged sync_to_store=1. */
class OrderMapper extends BaseMapper
{
    public static function entity(): string { return 'order'; }
    public static function cancelFields(): array { return ['fulfilment_status' => 'cancelled']; }

    public static function dependencies(array $conn, int $id): array
    {
        $deps = [];
        $inv = Database::row('SELECT customer_id, payment_method, coupon_code FROM invoices WHERE id=? AND book_id=?', [$id, $conn['book_id']]);
        if (!$inv) return [];
        foreach (Database::query('SELECT DISTINCT product_id FROM invoice_items WHERE invoice_id=? AND product_id IS NOT NULL', [$id]) as $r) $deps[] = ['product', (int)$r['product_id']];
        if ($inv['customer_id']) $deps[] = ['customer', (int)$inv['customer_id']];
        if ($inv['payment_method'] && ($pm = self::methodId($conn, $inv['payment_method']))) $deps[] = ['payment_method', $pm];
        if ($inv['coupon_code'] && ($cp = self::couponId($conn, $inv['coupon_code']))) $deps[] = ['coupon', $cp];
        return $deps;
    }

    private static function methodId(array $conn, ?string $label): ?int
    {
        if (!$label) return null;
        $r = Database::row('SELECT id FROM invoice_method_options WHERE book_id=? AND type="payment" AND LOWER(label)=LOWER(?) LIMIT 1', [$conn['book_id'], $label]);
        return $r ? (int)$r['id'] : null;
    }

    private static function couponId(array $conn, ?string $code): ?int
    {
        if (!$code) return null;
        $r = Database::row('SELECT id FROM coupons WHERE book_id=? AND code=? LIMIT 1', [$conn['book_id'], strtoupper($code)]);
        return $r ? (int)$r['id'] : null;
    }

    /** gross lines, and every kind of discount folded into the single "discount" the store understands. */
    public static function moneyView(array $inv): array
    {
        $gross = 0.0;
        foreach (Database::query('SELECT qty,unit_price FROM invoice_items WHERE invoice_id=?', [$inv['id']]) as $it) $gross += round((int)round((float)$it['qty']) * (float)$it['unit_price'], 2);
        $gross = round($gross, 2);
        $disc = round($gross - (float)$inv['subtotal'] + (float)$inv['discount'] + (float)$inv['points_discount'] + (float)$inv['coupon_discount'] + (float)$inv['privilege_discount'] + (float)$inv['rounding'], 2);
        return ['subtotal' => Util::money($gross), 'discount' => Util::money($disc), 'tax' => Util::money($inv['tax']), 'tax_inclusive' => (bool)$inv['tax_inclusive'],
            'delivery_charge' => Util::money((float)$inv['delivery_charge'] + (float)$inv['handling_charge']), 'total' => Util::money($inv['total'])];
    }

    public static function build(array $conn, int $id): ?array
    {
        $inv = Database::row('SELECT * FROM invoices WHERE id=? AND book_id=? AND type IN ("sale","pos")', [$id, $conn['book_id']]);
        if (!$inv) return null;
        $meta = Util::jsonDecode($inv['order_meta']);
        $cust = $inv['customer_id'] ? Database::row('SELECT * FROM customers WHERE id=?', [$inv['customer_id']]) : null;
        $tz = self::tz($conn);
        $contact = $meta['contact'] ?? ($cust ? ['name' => $cust['name'], 'phone' => $cust['phone'], 'email' => $cust['email']] : null);
        $shipping = $meta['shipping'] ?? ($cust ? ['name' => $cust['name'], 'phone' => $cust['phone'], 'line1' => $cust['address'], 'city' => $cust['city'], 'state' => $cust['state'], 'zip' => $cust['zip']] : null);
        $items = [];
        $itemsMeta = $meta['items'] ?? [];
        foreach (Database::query('SELECT ii.*, p.sku AS p_sku FROM invoice_items ii LEFT JOIN products p ON p.id=ii.product_id WHERE ii.invoice_id=? ORDER BY ii.id', [$inv['id']]) as $n => $it) {
            $m = $itemsMeta[$n] ?? [];
            $q = max(1, (int)round((float)$it['qty']));
            $items[] = ['line' => (int)($m['line'] ?? $n + 1), 'product_uuid' => $it['product_id'] ? Links::uuidFor($conn['id'], 'product', (int)$it['product_id']) : null,
                'variant_uuid' => $m['variant_uuid'] ?? null, 'sku' => $m['sku'] ?? $it['p_sku'], 'name' => $it['description'], 'variant_label' => $it['variant'],
                'price' => Util::money($it['unit_price']), 'quantity' => $q, 'subtotal' => Util::money(round((float)$it['unit_price'] * $q, 2)), 'is_preorder' => !empty($m['is_preorder'])];
        }
        $couponUuid = $meta['coupon_uuid'] ?? (($cid = self::couponId($conn, $inv['coupon_code'])) ? Links::uuidFor($conn['id'], 'coupon', $cid) : null);
        $pm = self::methodId($conn, $inv['payment_method']);
        $placedUtc = !empty($meta['placed_at']) ? Util::dbFromIso($meta['placed_at']) : Util::fromZone($inv['created_at'], $tz);
        $fulfil = $inv['status'] === 'cancelled' ? 'cancelled' : ($inv['fulfilment_status'] ?: 'placed');
        return array_merge([
            'number' => $inv['external_number'] ?: $inv['invoice_no'], 'source' => $inv['source'] === 'online_store' ? 'web' : 'book', 'fulfilment_status' => $fulfil,
            'customer_uuid' => $inv['customer_id'] ? Links::uuidFor($conn['id'], 'customer', (int)$inv['customer_id']) : null,
            'contact' => $contact, 'shipping' => $shipping, 'billing' => $meta['billing'] ?? null, 'currency' => $inv['currency_code'],
        ], self::moneyView($inv), [
            'coupon_code' => $inv['coupon_code'], 'coupon_uuid' => $couponUuid, 'delivery_area' => $meta['delivery_area'] ?? 'inside_dhaka',
            'payment_method_uuid' => $pm ? Links::uuidFor($conn['id'], 'payment_method', $pm) : null, 'notes' => $inv['note_customer'],
            'placed_at' => Util::isoFromDb($placedUtc), 'items' => $items,
        ]);
    }

    /** Refuse to send an order whose numbers don't add up to the paisa. @return ?string the problem, or null */
    public static function validate(array $fields): ?string
    {
        try { OrderService::checkTotals($fields); return null; }
        catch (ConflictResult | Reject $e) { return $e->getMessage(); }
    }

    public static function snapshotIds(array $conn, int $cursor, int $limit): array
    {
        return array_map('intval', array_column(Database::query(
            'SELECT id FROM invoices WHERE book_id=? AND type="sale" AND deleted_at IS NULL AND (source="online_store" OR sync_to_store=1) AND id>? ORDER BY id LIMIT ' . (int)$limit,
            [$conn['book_id'], $cursor]), 'id'));
    }

    public static function apply(array $conn, string $op, ?int $id, array $f, array $ctx): ?int
    {
        if ($op === 'cancel' || $op === 'void') {
            if (!$id) return null;
            OrderService::cancel($id, 'store');
            return $id;
        }
        if ($op === 'archive' || $op === 'restore') {
            if ($id) OrderService::setFulfilment($conn, $id, $op === 'archive' ? 'cancelled' : 'placed');
            return $id;
        }
        if (!$id) return OrderService::createFromOrder($conn, $f, $ctx);
        $uuid = $ctx['uuid'] ?? null; $eid = $ctx['event']['event_id'] ?? null; $cid = (int)$conn['id'];
        return OrderService::updateFromOrder($conn, $id, $f, $ctx, function (array $blocked) use ($cid, $uuid, $eid, $id, $conn) {
            Conflicts::add($cid, 'locked', 'order', $uuid, $id, $eid, self::build($conn, $id), $blocked,
                'The store changed the amounts or items of an order that already has payments, is delivered or is cancelled. Not applied — review it.');
        });
    }
}
