<?php
namespace App\Services\Integration\Mappers;

use App\Helpers\Database;
use App\Services\Integration\{ConflictResult, Links, OrderService, Util};

/** Recorded payments are never edited: a correction is a void plus a new payment (D8). */
class PaymentMapper extends BaseMapper
{
    public static function entity(): string { return 'payment'; }
    public static function voidFields(): array { return ['status' => 'void']; }

    public static function dependencies(array $conn, int $id): array
    {
        $p = Database::row('SELECT p.invoice_id, p.method FROM payments p JOIN invoices i ON i.id=p.invoice_id WHERE p.id=? AND i.book_id=?', [$id, $conn['book_id']]);
        if (!$p) return [];
        $d = [['order', (int)$p['invoice_id']]];
        if ($m = self::methodId($conn, $p['method'])) $d[] = ['payment_method', $m];
        return $d;
    }

    private static function methodId(array $conn, ?string $label): ?int
    {
        if (!$label) return null;
        $r = Database::row('SELECT id FROM invoice_method_options WHERE book_id=? AND type="payment" AND LOWER(label)=LOWER(?) LIMIT 1', [$conn['book_id'], $label]);
        return $r ? (int)$r['id'] : null;
    }

    public static function build(array $conn, int $id): ?array
    {
        $p = Database::row('SELECT p.*, i.book_id FROM payments p JOIN invoices i ON i.id=p.invoice_id WHERE p.id=? AND i.book_id=?', [$id, $conn['book_id']]);
        if (!$p) return null;
        $m = self::methodId($conn, $p['method']);
        $paid = $p['paid_at'] ?: Util::fromZone($p['created_at'], self::tz($conn));
        return ['order_uuid' => Links::uuidFor($conn['id'], 'order', (int)$p['invoice_id']), 'method_uuid' => $m ? Links::uuidFor($conn['id'], 'payment_method', $m) : null,
            'amount' => Util::money($p['amount']), 'paid_at' => Util::isoFromDb($paid), 'reference' => $p['reference'], 'note' => $p['note'], 'status' => $p['status'] === 'void' ? 'void' : 'recorded'];
    }

    public static function snapshotIds(array $conn, int $cursor, int $limit): array
    {
        return array_map('intval', array_column(Database::query(
            'SELECT p.id FROM payments p JOIN invoices i ON i.id=p.invoice_id WHERE i.book_id=? AND i.type="sale" AND (i.source="online_store" OR i.sync_to_store=1) AND p.id>? ORDER BY p.id LIMIT ' . (int)$limit,
            [$conn['book_id'], $cursor]), 'id'));
    }

    public static function apply(array $conn, string $op, ?int $id, array $f, array $ctx): ?int
    {
        if ($op === 'void' || ($id && ($f['status'] ?? '') === 'void')) { if ($id) OrderService::voidPayment($id); return $id; }
        if ($id) return $id;
        self::req($f, 'order_uuid'); self::req($f, 'amount');
        $oid = Links::localFor($conn['id'], 'order', $f['order_uuid']);
        if (!$oid) throw new \App\Services\Integration\Reject('dependency_missing', 'The order for this payment is not known yet.', true);
        $label = null;
        if (!empty($f['method_uuid'])) {
            $mid = Links::localFor($conn['id'], 'payment_method', $f['method_uuid']);
            if (!$mid) throw new \App\Services\Integration\Reject('dependency_missing', 'The payment method is not known yet.', true);
            $label = PaymentMethodMapper::labelFor($conn, $mid);
        }
        try { return OrderService::recordPayment($conn, $oid, self::decimal($f['amount']), $label, Util::dbFromIso($f['paid_at'] ?? null), self::str($f['reference'] ?? null, 120), self::str($f['note'] ?? null, 255)); }
        catch (\RuntimeException $e) { throw new ConflictResult('locked', $e->getMessage(), null, $f); }
    }
}
