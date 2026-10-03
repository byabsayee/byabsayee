<?php
namespace App\Services\Integration\Mappers;

use App\Helpers\Database;
use App\Services\Integration\{ConflictResult, Util};

class CustomerMapper extends BaseMapper
{
    public static function entity(): string { return 'customer'; }
    public static function accepts(): array { return ['name', 'email', 'phone', 'line1', 'city', 'state', 'zip', 'is_archived']; }
    public static function cancelFields(): array { return ['is_archived' => true]; }

    public static function build(array $conn, int $id): ?array
    {
        $c = Database::row('SELECT * FROM customers WHERE id=? AND book_id=?', [$id, $conn['book_id']]);
        if (!$c) return null;
        return ['name' => $c['name'], 'email' => $c['email'], 'phone' => $c['phone'], 'line1' => $c['address'], 'city' => $c['city'] ?? null,
            'state' => $c['state'] ?? null, 'zip' => $c['zip'] ?? null, 'is_archived' => !empty($c['deleted_at'])];
    }

    public static function snapshotIds(array $conn, int $cursor, int $limit): array
    {
        return array_map('intval', array_column(Database::query('SELECT id FROM customers WHERE book_id=? AND id>? ORDER BY id LIMIT ' . (int)$limit, [$conn['book_id'], $cursor]), 'id'));
    }

    /** Phone first (normalised), then email — D14. Returns the matching live customer row or null. */
    public static function findMatch(int $bookId, ?string $phone, ?string $email): ?array
    {
        $norm = Util::phoneNorm($phone);
        $email = $email ? strtolower(trim($email)) : null;
        if ($norm) {
            $tail = substr($norm, -8);
            foreach (Database::query("SELECT * FROM customers WHERE book_id=? AND deleted_at IS NULL AND phone LIKE ?", [$bookId, '%' . $tail . '%']) as $c) {
                if (Util::phoneNorm($c['phone']) === $norm) return $c;
            }
        }
        if ($email) {
            $c = Database::row('SELECT * FROM customers WHERE book_id=? AND deleted_at IS NULL AND LOWER(email)=? LIMIT 1', [$bookId, $email]);
            if ($c) return $c;
        }
        return null;
    }

    public static function apply(array $conn, string $op, ?int $id, array $f, array $ctx): ?int
    {
        $bid = (int)$conn['book_id'];
        if ($op === 'archive' || $op === 'restore') {
            if ($id) Database::run('UPDATE customers SET deleted_at=? WHERE id=? AND book_id=?', [$op === 'archive' ? now() : null, $id, $bid]);
            return $id;
        }
        $f = self::pick($f, self::accepts());
        if (!$id) {
            $email = isset($f['email']) ? strtolower(trim((string)$f['email'])) : null;
            $match = self::findMatch($bid, $f['phone'] ?? null, $email);
            if ($match && empty($ctx['force_new'])) {
                throw new ConflictResult('customer_match', 'A customer with the same phone/email already exists here — confirm whether they are the same person.',
                    ['id' => (int)$match['id'], 'name' => $match['name'], 'email' => $match['email'], 'phone' => $match['phone']], $f);
            }
            self::req($f, 'name');
            Database::run('INSERT INTO customers (book_id,name,phone,email,address,city,state,zip,created_at) VALUES (?,?,?,?,?,?,?,?,?)',
                [$bid, self::str($f['name'], 120), self::str($f['phone'] ?? null, 30), $email ?: null, self::str($f['line1'] ?? null, 500),
                 self::str($f['city'] ?? null, 100), self::str($f['state'] ?? null, 100), self::str($f['zip'] ?? null, 20), now()]);
            return Database::lastId();
        }
        $set = []; $v = [];
        if (isset($f['name'])) { $set[] = 'name=?'; $v[] = self::str($f['name'], 120) ?? 'Customer'; }
        if (array_key_exists('email', $f)) { $set[] = 'email=?'; $v[] = $f['email'] ? strtolower(trim((string)$f['email'])) : null; }
        if (array_key_exists('phone', $f)) { $set[] = 'phone=?'; $v[] = self::str($f['phone'], 30); }
        foreach (['line1' => ['address', 500], 'city' => ['city', 100], 'state' => ['state', 100], 'zip' => ['zip', 20]] as $k => [$col, $max]) {
            if (array_key_exists($k, $f)) { $set[] = "$col=?"; $v[] = self::str($f[$k], $max); }
        }
        if (isset($f['is_archived'])) { $set[] = 'deleted_at=?'; $v[] = self::bool($f['is_archived']) ? now() : null; }
        if ($set) { $v[] = $id; $v[] = $bid; Database::run('UPDATE customers SET ' . implode(',', $set) . ' WHERE id=? AND book_id=?', $v); }
        return $id;
    }
}
