<?php
namespace App\Services\Integration\Mappers;

use App\Helpers\Database;
use App\Services\Integration\{Links, Reject};

class CategoryMapper extends BaseMapper
{
    public static function entity(): string { return 'category'; }
    public static function accepts(): array { return ['name', 'description', 'sort_order', 'is_active', 'parent_uuid']; }

    public static function dependencies(array $conn, int $id): array
    {
        $r = Database::row('SELECT parent_id FROM categories WHERE id=? AND book_id=?', [$id, $conn['book_id']]);
        return ($r && $r['parent_id']) ? [['category', (int)$r['parent_id']]] : [];
    }

    public static function build(array $conn, int $id): ?array
    {
        $c = Database::row('SELECT * FROM categories WHERE id=? AND book_id=?', [$id, $conn['book_id']]);
        if (!$c) return null;
        return ['name' => $c['name'], 'description' => $c['description'], 'sort_order' => (int)$c['sort_order'], 'is_active' => (bool)$c['is_active'],
            'parent_uuid' => !empty($c['parent_id']) ? Links::uuidFor($conn['id'], 'category', (int)$c['parent_id']) : null];
    }

    public static function snapshotIds(array $conn, int $cursor, int $limit): array
    {
        return array_map('intval', array_column(Database::query('SELECT id FROM categories WHERE book_id=? AND id>? ORDER BY id LIMIT ' . (int)$limit, [$conn['book_id'], $cursor]), 'id'));
    }

    public static function apply(array $conn, string $op, ?int $id, array $f, array $ctx): ?int
    {
        $bid = (int)$conn['book_id'];
        if ($op === 'archive' || $op === 'restore') {
            if ($id) Database::run('UPDATE categories SET is_active=? WHERE id=? AND book_id=?', [$op === 'restore' ? 1 : 0, $id, $bid]);
            return $id;
        }
        $f = self::pick($f, self::accepts());
        $parent = null;
        if (array_key_exists('parent_uuid', $f) && $f['parent_uuid'] !== null) {
            $parent = Links::localFor($conn['id'], 'category', $f['parent_uuid']);
            if (!$parent) throw new Reject('dependency_missing', 'The parent category is not known yet.', true);
            if ($id && $parent === $id) throw new Reject('invalid_payload', 'A category cannot be its own parent.');
        }
        if (!$id) {
            self::req($f, 'name');
            $name = self::str($f['name'], 100);
            // Same name under the same parent = same category: link instead of duplicating.
            $same = Database::row('SELECT id FROM categories WHERE book_id=? AND LOWER(name)=LOWER(?) AND parent_id <=> ? LIMIT 1', [$bid, $name, $parent]);
            if ($same) return (int)$same['id'];
            Database::run('INSERT INTO categories (book_id,parent_id,name,description,sort_order,is_active,created_at) VALUES (?,?,?,?,?,?,?)',
                [$bid, $parent, $name, self::str($f['description'] ?? null, 5000), (int)($f['sort_order'] ?? 0), self::bool($f['is_active'] ?? true), now()]);
            return Database::lastId();
        }
        $set = []; $v = [];
        if (isset($f['name'])) { $set[] = 'name=?'; $v[] = self::str($f['name'], 100) ?? 'Category'; }
        if (array_key_exists('description', $f)) { $set[] = 'description=?'; $v[] = self::str($f['description'], 5000); }
        if (isset($f['sort_order'])) { $set[] = 'sort_order=?'; $v[] = (int)$f['sort_order']; }
        if (isset($f['is_active'])) { $set[] = 'is_active=?'; $v[] = self::bool($f['is_active']); }
        if (array_key_exists('parent_uuid', $f)) { $set[] = 'parent_id=?'; $v[] = $parent; }
        if ($set) { $v[] = $id; $v[] = $bid; Database::run('UPDATE categories SET ' . implode(',', $set) . ' WHERE id=? AND book_id=?', $v); }
        return $id;
    }
}
