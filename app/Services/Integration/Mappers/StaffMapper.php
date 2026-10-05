<?php
namespace App\Services\Integration\Mappers;

use App\Helpers\Database;
use App\Services\Integration\Util;

/**
 * Staff: Byabsayee employees <-> the store's staff accounts. Profile only — never salary, bank details,
 * ID documents, notes, permissions or passwords. A Byabsayee employee may also have a Byabsayee login; that is
 * unrelated and untouched. Shared fields: name, email, phone, address, is_active. title/department/joined_on travel
 * to the store for display but the store never changes them.
 */
class StaffMapper extends BaseMapper
{
    public static function entity(): string { return 'staff'; }
    public static function accepts(): array { return ['name', 'email', 'phone', 'address', 'is_active']; }
    public static function cancelFields(): array { return ['is_active' => false]; }
    public static function validate(array $f): ?string { return trim((string)($f['name'] ?? '')) === '' ? 'A staff member needs a name.' : null; }

    public static function build(array $conn, int $id): ?array
    {
        $e = Database::row('SELECT * FROM employees WHERE id=? AND book_id=? AND deleted_at IS NULL', [$id, $conn['book_id']]);
        if (!$e) return null;
        return ['name' => $e['name'], 'email' => $e['email'] ? strtolower(trim($e['email'])) : null, 'phone' => $e['phone'], 'address' => $e['address'],
            'is_active' => $e['status'] === 'active', 'title' => $e['designation_name'] ?? null, 'department' => $e['department'] ?? null,
            'joined_on' => !empty($e['join_date']) ? substr((string)$e['join_date'], 0, 10) : null];
    }

    public static function snapshotIds(array $conn, int $cursor, int $limit): array
    {
        return array_map('intval', array_column(Database::query('SELECT id FROM employees WHERE book_id=? AND deleted_at IS NULL AND id>? ORDER BY id LIMIT ' . (int)$limit, [$conn['book_id'], $cursor]), 'id'));
    }

    /** Same person already here? email first, then normalised phone. */
    public static function findMatch(int $bookId, ?string $phone, ?string $email): ?array
    {
        $email = $email ? strtolower(trim($email)) : null;
        if ($email) { $r = Database::row('SELECT * FROM employees WHERE book_id=? AND deleted_at IS NULL AND LOWER(email)=? LIMIT 1', [$bookId, $email]); if ($r) return $r; }
        $norm = Util::phoneNorm($phone);
        if ($norm) {
            foreach (Database::query('SELECT * FROM employees WHERE book_id=? AND deleted_at IS NULL AND phone LIKE ?', [$bookId, '%' . substr($norm, -8) . '%']) as $r) if (Util::phoneNorm($r['phone']) === $norm) return $r;
        }
        return null;
    }

    public static function apply(array $conn, string $op, ?int $id, array $f, array $ctx): ?int
    {
        $bid = (int)$conn['book_id'];
        if ($op === 'archive' || $op === 'restore') {
            if ($id) Database::run('UPDATE employees SET status=? WHERE id=? AND book_id=? AND status<>"terminated"', [$op === 'archive' ? 'inactive' : 'active', $id, $bid]);
            return $id;
        }
        $f = self::pick($f, self::accepts());
        if (!$id) {
            $email = isset($f['email']) ? strtolower(trim((string)$f['email'])) : null;
            if ($m = self::findMatch($bid, $f['phone'] ?? null, $email)) { $id = (int)$m['id']; }   // same person: link, never duplicate
            else {
                self::req($f, 'name');
                $last = Database::row('SELECT emp_code FROM employees WHERE book_id=? AND emp_code IS NOT NULL ORDER BY id DESC LIMIT 1', [$bid]);
                $n = $last && preg_match('/(\d+)$/', (string)$last['emp_code'], $mm) ? ((int)$mm[1] + 1) : 1;
                Database::run('INSERT INTO employees (book_id, emp_code, name, phone, email, address, status, created_at) VALUES (?,?,?,?,?,?,?,?)',
                    [$bid, 'EMP-' . str_pad((string)$n, 4, '0', STR_PAD_LEFT), self::str($f['name'], 120), self::str($f['phone'] ?? null, 30), $email ?: null, self::str($f['address'] ?? null, 500),
                     array_key_exists('is_active', $f) && !self::bool($f['is_active']) ? 'inactive' : 'active', now()]);
                return Database::lastId();
            }
        }
        $set = []; $v = [];
        if (isset($f['name'])) { $set[] = 'name=?'; $v[] = self::str($f['name'], 120) ?? 'Staff'; }
        if (array_key_exists('email', $f)) { $set[] = 'email=?'; $v[] = $f['email'] ? strtolower(trim((string)$f['email'])) : null; }
        if (array_key_exists('phone', $f)) { $set[] = 'phone=?'; $v[] = self::str($f['phone'], 30); }
        if (array_key_exists('address', $f)) { $set[] = 'address=?'; $v[] = self::str($f['address'], 500); }
        if (array_key_exists('is_active', $f)) { $set[] = 'status=IF(status="terminated","terminated",?)'; $v[] = self::bool($f['is_active']) ? 'active' : 'inactive'; }
        if ($set) { $v[] = $id; $v[] = $bid; Database::run('UPDATE employees SET ' . implode(',', $set) . ' WHERE id=? AND book_id=?', $v); }
        return $id;
    }
}
