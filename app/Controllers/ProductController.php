<?php
namespace App\Controllers;
use App\Helpers\Database;

class ProductController
{
    public function index(array $params): void
    {
        if (guest()) redirect('/login');
        $book   = $this->getBookOrFail($params['id']);
        if (!book_can($book, 'products', 'view')) abort_403();
        $search = trim($_GET['q']      ?? '');
        $filter = $_GET['filter']      ?? 'all';
        $catId  = !empty($_GET['cat']) ? (int)$_GET['cat'] : 0;

        $sql = 'SELECT p.*, c.name AS category_name
                FROM products p
                LEFT JOIN categories c ON c.id=p.category_id
                WHERE p.book_id=? AND p.deleted_at IS NULL';
        $p = [$book['id']];

        if ($search) {
            $sql .= ' AND (p.name LIKE ? OR p.sku LIKE ? OR p.product_code LIKE ? OR p.barcode LIKE ?)';
            $like = '%'.$search.'%';
            $p    = array_merge($p, [$like,$like,$like,$like]);
        }
        if ($catId)            $sql .= ' AND p.category_id='.intval($catId);
        if ($filter === 'low') $sql .= ' AND p.stock_qty<=p.low_stock_alert AND p.stock_qty>0';
        if ($filter === 'out') $sql .= ' AND p.stock_qty<=0';
        $sql .= ' ORDER BY p.name';

        $products   = Database::query($sql, $p);
        $categories = Database::query('SELECT * FROM categories WHERE book_id=? AND parent_id IS NULL ORDER BY name',[$book['id']]);
        $summary    = Database::row(
            'SELECT COUNT(*) AS total_products,
                    COALESCE(SUM(stock_qty*buy_price),0) AS stock_value,
                    SUM(CASE WHEN stock_qty<=0 THEN 1 ELSE 0 END) AS out_of_stock,
                    SUM(CASE WHEN stock_qty<=low_stock_alert AND stock_qty>0 THEN 1 ELSE 0 END) AS low_stock
             FROM products WHERE book_id=? AND deleted_at IS NULL',
            [$book['id']]
        );

        // Load batches for each product (for expanded view)
        $details = Database::row('SELECT * FROM book_business_details WHERE book_id=?', [$book['id']]);
        $inventoryMethod = $details['inventory_method'] ?? 'FIFO';
        $batchesByProduct = [];
        try {
            $allBatches = Database::query(
                'SELECT * FROM product_batches WHERE book_id=? ORDER BY product_id, created_at ASC',
                [$book['id']]
            );
            foreach ($allBatches as $b) {
                $batchesByProduct[$b['product_id']][] = $b;
            }
        } catch (\Throwable $e) {}

        require BASE_PATH . '/views/business/products/index.php';
    }

    public function store(array $params): void
    {
        if (guest()) redirect('/login');
        csrf_verify();
        $book = $this->getBookOrFail($params['id']);
        if (!book_can($book, 'products', 'create')) abort_403();

        $name = trim($_POST['name'] ?? '');
        if (!$name) redirect('/books/'.$book['id'].'/products', ['error' => 'Product name is required.']);

        $image = null;
        if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] === 0) {
            $image = $this->handleImageUpload($_FILES['image']);
        }

        // Auto-generate barcode placeholder; will be updated after insert
        Database::run(
            'INSERT INTO products
                (book_id,category_id,name,sku,barcode,unit,buy_price,sell_price,
                 stock_qty,low_stock_alert,description,image,created_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [
                $book['id'],
                !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null,
                $name,
                trim($_POST['sku']     ?? '') ?: null,
                null,  // barcode set below
                trim($_POST['unit']    ?? 'pcs'),
                (float)($_POST['buy_price']       ?? 0),
                (float)($_POST['sell_price']      ?? 0),
                (float)($_POST['stock_qty']       ?? 0),
                (float)($_POST['low_stock_alert'] ?? 5),
                trim($_POST['description'] ?? '') ?: null,
                $image, now()
            ]
        );
        $productId = Database::lastId();

        // Auto product code
        $code = 'PRD-' . str_pad($productId, 5, '0', STR_PAD_LEFT);
        // Auto barcode: BC + bookId(3) + productId(6) = 11 chars
        $autoBarcode = 'BC'
            . str_pad($book['id'], 3, '0', STR_PAD_LEFT)
            . str_pad($productId, 6, '0', STR_PAD_LEFT);

        $userBarcode = trim($_POST['barcode'] ?? '') ?: $autoBarcode;
        // Ensure uniqueness
        $existing = Database::row('SELECT id FROM products WHERE barcode=? AND id!=?', [$userBarcode, $productId]);
        if ($existing) $userBarcode = $autoBarcode;

        Database::run('UPDATE products SET product_code=?, barcode=? WHERE id=?', [$code, $userBarcode, $productId]);

        // Create initial batch if stock > 0
        $initQty = (float)($_POST['stock_qty'] ?? 0);
        $buyPrice = (float)($_POST['buy_price'] ?? 0);
        if ($initQty > 0) {
            try {
                $batchBarcode = $userBarcode; // first batch uses the product barcode
                Database::run(
                    'INSERT INTO product_batches (product_id,book_id,barcode,buy_price,sell_price,initial_qty,remaining_qty,created_at)
                     VALUES (?,?,?,?,0,?,?,?)',
                    [$productId, $book['id'], $batchBarcode, $buyPrice, $initQty, $initQty, now()]
                );
            } catch (\Throwable $e) {}
        }

        $this->saveVariants($productId, $_POST['variants'] ?? []);
        $this->handleCategory($book['id'], $_POST, $productId);

        redirect('/books/'.$book['id'].'/products', ['success' => '"'.$name.'" added. Code: '.$code.' | Barcode: '.$userBarcode]);
    }

    public function update(array $params): void
    {
        if (guest()) redirect('/login');
        csrf_verify();
        $book    = $this->getBookOrFail($params['id']);
        if (!book_can($book, 'products', 'edit')) abort_403();
        $product = $this->getProductOrFail($params['product_id'], $book['id']);

        $name = trim($_POST['name'] ?? '');
        if (!$name) redirect('/books/'.$book['id'].'/products', ['error' => 'Product name is required.']);

        $image = $product['image'];
        if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] === 0) {
            $new = $this->handleImageUpload($_FILES['image']);
            if ($new) $image = $new;
        }

        // Keep existing barcode if no new one provided
        $barcode = trim($_POST['barcode'] ?? '') ?: $product['barcode'];

        Database::run(
            'UPDATE products SET category_id=?,name=?,sku=?,barcode=?,unit=?,
             buy_price=?,sell_price=?,low_stock_alert=?,description=?,image=? WHERE id=?',
            [
                !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null,
                $name,
                trim($_POST['sku']     ?? '') ?: null,
                $barcode,
                trim($_POST['unit']    ?? 'pcs'),
                (float)($_POST['buy_price']       ?? 0),
                (float)($_POST['sell_price']      ?? 0),
                (float)($_POST['low_stock_alert'] ?? 5),
                trim($_POST['description'] ?? '') ?: null,
                $image, $product['id']
            ]
        );

        Database::run('DELETE FROM product_variants WHERE product_id=?', [$product['id']]);
        $this->saveVariants($product['id'], $_POST['variants'] ?? []);

        redirect('/books/'.$book['id'].'/products', ['success' => '"'.$name.'" updated.']);
    }

    public function adjustStock(array $params): void
    {
        if (guest()) redirect('/login');
        csrf_verify();
        $book    = $this->getBookOrFail($params['id']);
        if (!book_can($book, 'products', 'adjust_stock')) abort_403();
        $product = $this->getProductOrFail($params['product_id'], $book['id']);

        $type = $_POST['adjust_type'] ?? 'add';
        $qty  = abs((float)($_POST['qty'] ?? 0));
        $note = trim($_POST['note'] ?? '');

        if ($qty <= 0) redirect('/books/'.$book['id'].'/products', ['error' => 'Quantity must be greater than zero.']);

        $newQty = $type === 'add'
            ? $product['stock_qty'] + $qty
            : max(0, $product['stock_qty'] - $qty);

        Database::run('UPDATE products SET stock_qty=? WHERE id=?', [$newQty, $product['id']]);
        Database::run(
            'INSERT INTO stock_adjustments (product_id,type,qty,note,created_by,created_at) VALUES (?,?,?,?,?,?)',
            [$product['id'],$type,$qty,$note ?: null,auth()['id'],now()]
        );

        // Also adjust latest batch
        if ($type === 'add') {
            try {
                $batchCount = Database::row('SELECT COUNT(*)+1 AS n FROM product_batches WHERE product_id=?', [$product['id']]);
                $n = (int)($batchCount['n'] ?? 1);
                $barcode = 'BC'
                    . str_pad($book['id'], 3, '0', STR_PAD_LEFT)
                    . str_pad($product['id'], 5, '0', STR_PAD_LEFT)
                    . str_pad($n, 4, '0', STR_PAD_LEFT);
                $check = Database::row('SELECT id FROM product_batches WHERE barcode=?', [$barcode]);
                if ($check) $barcode .= rand(10,99);
                Database::run(
                    'INSERT INTO product_batches (product_id,book_id,barcode,buy_price,sell_price,initial_qty,remaining_qty,created_at)
                     VALUES (?,?,?,?,0,?,?,?)',
                    [$product['id'], $book['id'], $barcode, $product['buy_price'], $qty, $qty, now()]
                );
            } catch (\Throwable $e) {}
        }

        redirect('/books/'.$book['id'].'/products', [
            'success' => 'Stock updated: "'.$product['name'].'". New qty: '.$newQty
        ]);
    }

    public function delete(array $params): void
    {
        if (guest()) redirect('/login');
        csrf_verify();
        $book    = $this->getBookOrFail($params['id']);
        if (!book_can($book, 'products', 'delete')) abort_403();
        $product = $this->getProductOrFail($params['product_id'], $book['id']);
        Database::run('UPDATE products SET deleted_at=? WHERE id=?', [now(), $product['id']]);
        redirect('/books/'.$book['id'].'/products', ['success' => '"'.$product['name'].'" deleted.']);
    }

    public function storeCategory(array $params): void
    {
        if (guest()) redirect('/login');
        csrf_verify();
        $book = $this->getBookOrFail($params['id']);
        if (!book_can($book, 'products', 'create')) abort_403();
        $name     = trim($_POST['name']      ?? '');
        $parentId = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
        if (!$name) redirect('/books/'.$book['id'].'/products', ['error' => 'Category name is required.']);
        Database::run(
            'INSERT INTO categories (book_id,parent_id,name,created_at) VALUES (?,?,?,?)',
            [$book['id'], $parentId, $name, now()]
        );
        redirect('/books/'.$book['id'].'/products', ['success' => 'Category "'.$name.'" created.']);
    }

    // ── Barcode print page ────────────────────────────────────────────────────
    public function barcodes(array $params): void
    {
        if (guest()) redirect('/login');
        $book = $this->getBookOrFail($params['id']);
        if (!book_can($book, 'products', 'view')) abort_403();
        $productId = (int)($_GET['product_id'] ?? 0);

        if ($productId) {
            $products = [Database::row('SELECT * FROM products WHERE id=? AND book_id=? AND deleted_at IS NULL', [$productId, $book['id']])];
            $products = array_filter($products);
        } else {
            $products = Database::query('SELECT * FROM products WHERE book_id=? AND deleted_at IS NULL ORDER BY name', [$book['id']]);
        }

        // Load batches per product
        $batchesByProduct = [];
        try {
            foreach ($products as $prod) {
                $batches = Database::query(
                    'SELECT * FROM product_batches WHERE product_id=? ORDER BY created_at ASC',
                    [$prod['id']]
                );
                $batchesByProduct[$prod['id']] = $batches;
            }
        } catch (\Throwable $e) {}

        $paperWidth = (int)($_GET['w'] ?? 58);
        $details    = Database::row('SELECT * FROM book_business_details WHERE book_id=?', [$book['id']]);
        require BASE_PATH . '/views/business/products/barcodes.php';
    }

    public function lookup(array $params): void
    {
        if (guest()) json_response(['error' => 'Unauthorized'], 401);
        $book = $this->getBookOrFail($params['id']);
        if (!book_can($book, 'products', 'view')) abort_403();

        $q = trim($_GET['q'] ?? '');
        if (!$q) json_response(['error' => 'No query'], 400);

        // Also search by batch barcode
        $product = Database::row(
            'SELECT * FROM products
             WHERE book_id=? AND deleted_at IS NULL
               AND (product_code=? OR barcode=? OR sku=?)',
            [$book['id'], $q, $q, $q]
        );

        // If not found by product barcode, try batch barcode
        if (!$product) {
            try {
                $batch = Database::row(
                    'SELECT pb.*, p.* FROM product_batches pb
                     JOIN products p ON p.id=pb.product_id
                     WHERE pb.barcode=? AND pb.book_id=?',
                    [$q, $book['id']]
                );
                if ($batch) {
                    // Return product with batch info
                    $product = Database::row('SELECT * FROM products WHERE id=?', [$batch['product_id']]);
                }
            } catch (\Throwable $e) {}
        }

        if (!$product) json_response(['found' => false]);

        $variants = Database::query('SELECT * FROM product_variants WHERE product_id=?', [$product['id']]);
        try {
            $batches = Database::query(
                'SELECT * FROM product_batches WHERE product_id=? AND remaining_qty>0 ORDER BY created_at ASC',
                [$product['id']]
            );
        } catch (\Throwable $e) { $batches = []; }
        json_response(['found' => true, 'product' => $product, 'variants' => $variants, 'batches' => $batches]);
    }

    private function saveVariants(int $productId, array $variants): void
    {
        foreach ($variants as $v) {
            $label = trim($v['label'] ?? '');
            $value = trim($v['value'] ?? '');
            if (!$label || !$value) continue;
            Database::run(
                'INSERT INTO product_variants (product_id,label,value,sku,price_adj,stock_qty) VALUES (?,?,?,?,?,?)',
                [
                    $productId, $label, $value,
                    trim($v['sku']       ?? '') ?: null,
                    (float)($v['price_adj'] ?? 0),
                    (float)($v['stock_qty'] ?? 0),
                ]
            );
        }
    }

    private function handleCategory(int $bookId, array $post, int $productId): void
    {
        $newCat = trim($post['new_category'] ?? '');
        if ($newCat) {
            Database::run('INSERT INTO categories (book_id,name,created_at) VALUES (?,?,?)',
                [$bookId, $newCat, now()]);
            $catId = Database::lastId();
            Database::run('UPDATE products SET category_id=? WHERE id=?', [$catId, $productId]);
        }
    }

    private function handleImageUpload(array $file): ?string
    {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext,['jpg','jpeg','png','webp']) || $file['size'] > 5*1024*1024) return null;
        $dir = config('upload.path').'/products';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        if (!is_writable($dir)) return null;
        $filename = date('Ymd_His').'_'.bin2hex(random_bytes(4)).'.'.$ext;
        return move_uploaded_file($file['tmp_name'], $dir.'/'.$filename) ? 'products/'.$filename : null;
    }

        private function getBookOrFail(string $id): array
    {
        $book = book_for_user($id, 'business');
        if (!$book) { http_response_code(404); require BASE_PATH.'/views/errors/404.php'; exit; }
        return $book;
    }

    private function getProductOrFail(string $pid, int $bookId): array
    {
        $p = Database::row('SELECT * FROM products WHERE id=? AND book_id=? AND deleted_at IS NULL', [$pid,$bookId]);
        if (!$p) { http_response_code(404); require BASE_PATH.'/views/errors/404.php'; exit; }
        return $p;
    }
}
