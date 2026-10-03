<?php
namespace App\Services\Integration\Mappers;

use App\Helpers\Database;

/** Payment methods live in invoice_method_options (type='payment'). The label doubles as the "fund name". */
class PaymentMethodMapper extends BaseMapper
{
    public static function entity(): string { return 'payment_method'; }
    public static function accepts(): array { return ['name', 'is_active', 'sort_order', 'fund_name']; }

    public static function build(array $conn, int $id): ?array
    {
        $m = Database::row('SELECT * FROM invoice_method_options WHERE id=? AND book_id=? AND type="payment"', [$id, $conn['book_id']]);
        if (!$m) return null;
        $kind = $m['kind'] ?: (preg_match('/cash on delivery|^cod$/i', $m['label']) ? 'cod' : 'manual');
        $code = $m['code'] ?: (preg_replace('/[^a-z0-9_]/', '', strtolower(str_replace(' ', '_', $m['label']))) ?: 'method' . $m['id']);
        return ['code' => $code, 'name' => $m['label'], 'kind' => $kind, 'is_active' => (bool)$m['is_active'], 'sort_order' => (int)$m['sort_order']];
    }

    public static function snapshotIds(array $conn, int $cursor, int $limit): array
    {
        return array_map('intval', array_column(Database::query('SELECT id FROM invoice_method_options WHERE book_id=? AND type="payment" AND id>? ORDER BY id LIMIT ' . (int)$limit, [$conn['book_id'], $cursor]), 'id'));
    }

    /** The invoice/payment label for a method id (what payments.method and invoices.payment_method store). */
    public static function labelFor(array $conn, ?int $id): ?string
    {
        if (!$id) return null;
        $r = Database::row('SELECT label FROM invoice_method_options WHERE id=? AND book_id=? AND type="payment"', [$id, $conn['book_id']]);
        return $r['label'] ?? null;
    }

    public static function apply(array $conn, string $op, ?int $id, array $f, array $ctx): ?int
    {
        $bid = (int)$conn['book_id'];
        if ($op === 'archive' || $op === 'restore') {
            if ($id) Database::run('UPDATE invoice_method_options SET is_active=? WHERE id=? AND book_id=?', [$op === 'restore' ? 1 : 0, $id, $bid]);
            return $id;
        }
        if (!$id) {
            self::req($f, 'name'); self::req($f, 'code');
            $name = self::str($f['name'], 120);
            $code = preg_replace('/[^a-z0-9_]/', '', strtolower((string)$f['code'])) ?: 'method';
            $same = Database::row('SELECT id FROM invoice_method_options WHERE book_id=? AND type="payment" AND (LOWER(label)=LOWER(?) OR code=?) LIMIT 1', [$bid, $name, $code]);
            if ($same) {                       // same name or code = same method: link instead of duplicating
                Database::run('UPDATE invoice_method_options SET code=COALESCE(code,?), kind=COALESCE(kind,?) WHERE id=?', [$code, in_array($f['kind'] ?? '', ['cod', 'manual', 'gateway'], true) ? $f['kind'] : 'manual', $same['id']]);
                return (int)$same['id'];
            }
            $kind = in_array($f['kind'] ?? '', ['cod', 'manual', 'gateway'], true) ? $f['kind'] : 'manual';
            Database::run('INSERT INTO invoice_method_options (book_id,type,label,sort_order,is_active,code,kind) VALUES (?,?,?,?,?,?,?)',
                [$bid, 'payment', $name, (int)($f['sort_order'] ?? 0), self::bool($f['is_active'] ?? true), $code, $kind]);
            return Database::lastId();
        }
        $f = self::pick($f, self::accepts());
        $set = []; $v = [];
        if (isset($f['name'])) {
            $set[] = 'label=?'; $v[] = self::str($f['name'], 120) ?? 'Payment';
        }
        if (isset($f['is_active'])) { $set[] = 'is_active=?'; $v[] = self::bool($f['is_active']); }
        if (isset($f['sort_order'])) { $set[] = 'sort_order=?'; $v[] = (int)$f['sort_order']; }
        if ($set) { $v[] = $id; $v[] = $bid; Database::run('UPDATE invoice_method_options SET ' . implode(',', $set) . ' WHERE id=? AND book_id=?', $v); }
        return $id;     // fund_name is the label here; there is no separate fund account to rename
    }
}
