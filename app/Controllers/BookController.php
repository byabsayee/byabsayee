<?php
namespace App\Controllers;
use App\Helpers\Database;

class BookController
{
    public function index(): void
    {
        if (guest()) redirect('/login');

        $userId = auth()['id'];
        $search = trim($_GET['q'] ?? '');

        $books = Database::query(
            'SELECT b.*,
                CASE
                    WHEN b.type = "personal" THEN
                        COALESCE((SELECT SUM(e.amount) FROM entries e WHERE e.book_id=b.id AND e.type="in"  AND e.deleted_at IS NULL),0)
                    ELSE
                        COALESCE((SELECT SUM(i.total) FROM invoices i WHERE i.book_id=b.id AND i.type="sale"     AND i.status="paid" AND i.deleted_at IS NULL),0)
                END AS total_in,
                CASE
                    WHEN b.type = "personal" THEN
                        COALESCE((SELECT SUM(e.amount) FROM entries e WHERE e.book_id=b.id AND e.type="out" AND e.deleted_at IS NULL),0)
                    ELSE
                        COALESCE((SELECT SUM(i.total) FROM invoices i WHERE i.book_id=b.id AND i.type="purchase" AND i.status="paid" AND i.deleted_at IS NULL),0)
                END AS total_out,
                (b.user_id = ?) AS is_owner
             FROM books b
             WHERE b.deleted_at IS NULL
               AND (
                   b.user_id = ?
                   OR EXISTS (
                       SELECT 1 FROM book_members bm
                       WHERE bm.book_id = b.id AND bm.user_id = ? AND bm.status = "active"
                   )
               )
             ORDER BY is_owner DESC, b.created_at DESC',
            [$userId, $userId, $userId]
        );

        // Apply search filter
        if ($search !== '') {
            $q = mb_strtolower($search);
            $books = array_filter($books, function($b) use ($q) {
                return str_contains(mb_strtolower($b['name']), $q)
                    || str_contains(mb_strtolower($b['type']), $q);
            });
            $books = array_values($books);
        }

        $myBooks     = array_values(array_filter($books, fn($b) => $b['is_owner']));
        $sharedBooks = array_values(array_filter($books, fn($b) => !$b['is_owner']));

        require BASE_PATH . '/views/books/index.php';
    }

    public function create(): void
    {
        if (guest()) redirect('/login');
        require BASE_PATH . '/views/books/create.php';
    }

    public function store(): void
    {
        if (guest()) redirect('/login');
        csrf_verify();

        $name       = trim($_POST['name']       ?? '');
        $type       = $_POST['type']             ?? 'personal';
        $color      = $_POST['color']            ?? '#1a6b4a';
        $themeColor = $_POST['theme_color']      ?? '#1a6b4a';
        $email      = trim($_POST['email']       ?? '');
        $phone      = trim($_POST['phone']       ?? '');
        $address    = trim($_POST['address']     ?? '');

        if (!$name) {
            set_old(['name'=>$name,'type'=>$type]);
            redirect('/books/create', ['error' => 'Please enter a book name.']);
        }
        if (!in_array($type, ['personal','business'])) redirect('/books/create', ['error' => 'Invalid type.']);
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color))      $color      = '#1a6b4a';
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $themeColor)) $themeColor = '#1a6b4a';

        $logo = null;
        if (!empty($_FILES['logo']['name']) && $_FILES['logo']['error'] === 0) {
            $logo = $this->handleLogoUpload($_FILES['logo']);
        }

        Database::run(
            'INSERT INTO books (user_id,name,type,color,theme_color,logo,email,phone,address,created_at)
             VALUES (?,?,?,?,?,?,?,?,?,?)',
            [auth()['id'],$name,$type,$color,$themeColor,
             $logo,$email ?: null,$phone ?: null,$address ?: null,now()]
        );
        $bookId = Database::lastId();

        if ($type === 'business') {
            $businessName     = trim($_POST['business_name']     ?? $name);
            $invoicePrefix    = strtoupper(trim($_POST['invoice_prefix']         ?? 'INV')) ?: 'INV';
            $invoicePrefixPur = strtoupper(trim($_POST['invoice_prefix_purchase']?? 'PUR')) ?: 'PUR';
            $invoiceFont      = trim($_POST['invoice_font']      ?? 'DejaVu Sans');

            Database::run(
                'INSERT INTO book_business_details
                    (book_id,business_name,phone,address,invoice_prefix,invoice_prefix_purchase,invoice_counter,invoice_counter_purchase,invoice_font)
                 VALUES (?,?,?,?,?,?,1,1,?)',
                [$bookId,$businessName,$phone ?: null,$address ?: null,
                 $invoicePrefix,$invoicePrefixPur,$invoiceFont]
            );

            // Seed delivery methods
            foreach (['Home Delivery','Store Pickup','Courier','Express Delivery'] as $i => $m)
                Database::run('INSERT INTO invoice_method_options (book_id,type,label,sort_order) VALUES (?,?,?,?)',[$bookId,'delivery',$m,$i]);

            // Seed payment methods
            foreach (['Cash','Cash on Delivery','bKash','Nagad','Rocket','Card','Bank Transfer','Cheque','Credit'] as $i => $m)
                Database::run('INSERT INTO invoice_method_options (book_id,type,label,sort_order) VALUES (?,?,?,?)',[$bookId,'payment',$m,$i]);

            // Seed currencies from form
            $this->saveCurrencies($bookId, $_POST['currencies'] ?? []);
            if (empty($_POST['currencies'])) {
                // Default BDT
                Database::run('INSERT INTO book_currencies (book_id,code,symbol,name,is_default,sort_order) VALUES (?,?,?,?,1,0)',
                    [$bookId,'BDT','৳','Bangladeshi Taka']);
            }

            // Auto-add book creator as Owner employee
            $user = auth();
            try {
                $alreadyExists = Database::row(
                    'SELECT id FROM employees WHERE book_id=? AND user_id=? AND deleted_at IS NULL',
                    [$bookId, $user['id']]
                );
                if (!$alreadyExists) {
                    Database::run(
                        'INSERT INTO employees (book_id, user_id, emp_code, name, email, designation_name, status, join_date, created_at)
                         VALUES (?,?,?,?,?,?,?,?,?)',
                        [$bookId, $user['id'], 'EMP-0001', $user['name'], $user['email'] ?? null,
                         'Owner', 'active', date('Y-m-d'), now()]
                    );
                } else if (empty($alreadyExists['emp_code'])) {
                    // Back-fill emp_code for existing owner record created without one
                    Database::run(
                        'UPDATE employees SET emp_code=? WHERE book_id=? AND user_id=? AND emp_code IS NULL',
                        ['EMP-0001', $bookId, $user['id']]
                    );
                }
            } catch (\Throwable $e) {
                // employees.designation_name may not exist yet — silently continue
            }
        }

        redirect('/books/'.$bookId, ['success' => 'Book created!']);
    }

    public function show(array $params): void
    {
        if (guest()) redirect('/login');
        $book = $this->getBookOrFail($params['id']);
        $book['type'] === 'personal' ? $this->showPersonal($book) : $this->showBusiness($book);
    }

    public function edit(array $params): void
    {
        if (guest()) redirect('/login');
        $book    = $this->getBookOrFail($params['id']);
        if (!book_can($book, 'book_settings', 'view')) abort_403();
        $details = $book['type'] === 'business'
            ? Database::row('SELECT * FROM book_business_details WHERE book_id=?', [$book['id']])
            : null;
        $deliveryMethods = $book['type'] === 'business'
            ? Database::query('SELECT * FROM invoice_method_options WHERE book_id=? AND type="delivery" ORDER BY sort_order',[$book['id']])
            : [];
        $paymentMethods = $book['type'] === 'business'
            ? Database::query('SELECT * FROM invoice_method_options WHERE book_id=? AND type="payment" ORDER BY sort_order',[$book['id']])
            : [];
        $currencies = $book['type'] === 'business'
            ? Database::query('SELECT * FROM book_currencies WHERE book_id=? ORDER BY is_default DESC,sort_order',[$book['id']])
            : [];
        require BASE_PATH . '/views/books/edit.php';
    }

    public function update(array $params): void
    {
        if (guest()) redirect('/login');
        csrf_verify();
        $book       = $this->getBookOrFail($params['id']);
        if (!book_can($book, 'book_settings', 'edit')) abort_403();
        $name       = trim($_POST['name']       ?? '');
        $color      = $_POST['color']            ?? $book['color'];
        $themeColor = $_POST['theme_color']      ?? ($book['theme_color'] ?? '#1a6b4a');
        $email      = trim($_POST['email']       ?? '');
        $phone      = trim($_POST['phone']       ?? '');
        $address    = trim($_POST['address']     ?? '');

        if (!$name) redirect('/books/'.$book['id'].'/edit', ['error' => 'Name is required.']);
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color))      $color      = $book['color'];
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $themeColor)) $themeColor = $book['theme_color'] ?? '#1a6b4a';

        $logo = $book['logo'];
        if (!empty($_FILES['logo']['name']) && $_FILES['logo']['error'] === 0) {
            $new = $this->handleLogoUpload($_FILES['logo']);
            if ($new) $logo = $new;
        }

        Database::run(
            'UPDATE books SET name=?,color=?,theme_color=?,logo=?,email=?,phone=?,address=? WHERE id=?',
            [$name,$color,$themeColor,$logo,$email ?: null,$phone ?: null,$address ?: null,$book['id']]
        );

        if ($book['type'] === 'business') {
            $businessName     = trim($_POST['business_name']          ?? $name);
            $invoicePrefix    = strtoupper(trim($_POST['invoice_prefix']          ?? 'INV')) ?: 'INV';
            $invoicePrefixPur = strtoupper(trim($_POST['invoice_prefix_purchase'] ?? 'PUR')) ?: 'PUR';
            $invoiceFont      = trim($_POST['invoice_font'] ?? 'DejaVu Sans');

            Database::run(
                'UPDATE book_business_details SET business_name=?,phone=?,address=?,invoice_prefix=?,invoice_prefix_purchase=?,invoice_font=?,inventory_method=? WHERE book_id=?',
                [$businessName,$phone ?: null,$address ?: null,$invoicePrefix,$invoicePrefixPur,$invoiceFont,in_array(strtoupper($_POST['inventory_method']??'FIFO'),['FIFO','LIFO'])?strtoupper($_POST['inventory_method']??'FIFO'):'FIFO',$book['id']]
            );

            // Delivery methods
            if (isset($_POST['delivery_methods'])) {
                Database::run('DELETE FROM invoice_method_options WHERE book_id=? AND type="delivery"',[$book['id']]);
                foreach (array_filter(array_map('trim',$_POST['delivery_methods'])) as $i => $m)
                    Database::run('INSERT INTO invoice_method_options (book_id,type,label,sort_order) VALUES (?,?,?,?)',[$book['id'],'delivery',$m,$i]);
            }
            if (isset($_POST['payment_methods'])) {
                Database::run('DELETE FROM invoice_method_options WHERE book_id=? AND type="payment"',[$book['id']]);
                foreach (array_filter(array_map('trim',$_POST['payment_methods'])) as $i => $m)
                    Database::run('INSERT INTO invoice_method_options (book_id,type,label,sort_order) VALUES (?,?,?,?)',[$book['id'],'payment',$m,$i]);
            }

            // Currencies
            $this->saveCurrencies($book['id'], $_POST['currencies'] ?? []);
        }

        redirect('/books/'.$book['id'], ['success' => 'Book updated.']);
    }

    public function delete(array $params): void
    {
        if (guest()) redirect('/login');
        csrf_verify();
        $book = $this->getBookOrFail($params['id']);

        $user = auth();
        $isOwner = ((int)$book['user_id'] === (int)$user['id']);

        if (!$isOwner) {
            // Check if this member has been explicitly granted book_settings.delete permission
            $member = Database::row(
                'SELECT permissions FROM book_members WHERE book_id=? AND user_id=? AND status="active"',
                [$book['id'], $user['id']]
            );
            $perms = $member ? (json_decode($member['permissions'] ?? '{}', true) ?? []) : [];
            $canDelete = !empty($perms['book_settings']['delete']);

            if (!$canDelete) {
                redirect('/books/'.$book['id'].'/settings', ['error' => 'Only the book owner or an employee with delete permission can delete this book.']);
            }
        }

        Database::run('UPDATE books SET deleted_at=? WHERE id=?', [now(), $book['id']]);
        redirect('/books', ['success' => '"'.$book['name'].'" deleted.']);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function saveCurrencies(int $bookId, array $currencyData): void
    {
        if (empty($currencyData)) return;
        Database::run('DELETE FROM book_currencies WHERE book_id=?', [$bookId]);
        $defaultSet = false;
        foreach ($currencyData as $i => $c) {
            $code    = strtoupper(trim($c['code']   ?? ''));
            $symbol  = trim($c['symbol']             ?? '');
            $cname   = trim($c['name']               ?? '');
            $isDefault = isset($c['is_default']) && !$defaultSet ? 1 : 0;
            if ($isDefault) $defaultSet = true;
            if (!$code || !$symbol) continue;
            Database::run(
                'INSERT INTO book_currencies (book_id,code,symbol,name,is_default,sort_order) VALUES (?,?,?,?,?,?)',
                [$bookId,$code,$symbol,$cname ?: $code,$isDefault,$i]
            );
        }
        // Ensure at least one default
        if (!$defaultSet) {
            Database::run('UPDATE book_currencies SET is_default=1 WHERE book_id=? LIMIT 1', [$bookId]);
        }
    }

    private function showPersonal(array $book): void
    {
        $totals = Database::row(
            'SELECT COALESCE(SUM(CASE WHEN type="in"  THEN amount ELSE 0 END),0) AS total_in,
                    COALESCE(SUM(CASE WHEN type="out" THEN amount ELSE 0 END),0) AS total_out
             FROM entries WHERE book_id=? AND deleted_at IS NULL',[$book['id']]
        );
        $entries  = Database::query(
            'SELECT e.*,c.name AS contact_name FROM entries e
             LEFT JOIN contacts c ON e.contact_id=c.id
             WHERE e.book_id=? AND e.deleted_at IS NULL
             ORDER BY e.entry_date DESC,e.created_at DESC LIMIT 50',[$book['id']]
        );
        $contacts = Database::query(
            'SELECT id,name FROM contacts WHERE book_id=? AND deleted_at IS NULL ORDER BY name',[$book['id']]
        );
        require BASE_PATH . '/views/books/personal.php';
    }

    private function showBusiness(array $book): void
    {
        $details = Database::row('SELECT * FROM book_business_details WHERE book_id=?',[$book['id']]);
        $bid = $book['id'];

        // Counts for count cards
        $salesCount     = 0; $purchasesCount = 0; $returnsCount = 0;
        $employeesCount = 0; $couponsCount   = 0;

        try {
            $invCounts = Database::row(
                'SELECT
                    SUM(CASE WHEN type="sale"     AND deleted_at IS NULL THEN 1 ELSE 0 END) AS sales,
                    SUM(CASE WHEN type="purchase" AND deleted_at IS NULL THEN 1 ELSE 0 END) AS purchases
                 FROM invoices WHERE book_id=?', [$bid]
            );
            $salesCount     = (int)($invCounts['sales']     ?? 0);
            $purchasesCount = (int)($invCounts['purchases'] ?? 0);
        } catch (\Throwable $e) {}

        try {
            $retRow = Database::row('SELECT COUNT(*) AS n FROM returns WHERE book_id=? AND deleted_at IS NULL', [$bid]);
            $returnsCount = (int)($retRow['n'] ?? 0);
        } catch (\Throwable $e) {}

        try {
            $empRow = Database::row('SELECT COUNT(*) AS n FROM employees WHERE book_id=? AND deleted_at IS NULL', [$bid]);
            $employeesCount = (int)($empRow['n'] ?? 0);
        } catch (\Throwable $e) {}

        try {
            $couRow = Database::row('SELECT COUNT(*) AS n FROM coupons WHERE book_id=? AND deleted_at IS NULL', [$bid]);
            $couponsCount = (int)($couRow['n'] ?? 0);
        } catch (\Throwable $e) {}

        $stats = [
            'customers'  => 0,
            'suppliers'  => 0,
            'products'   => 0,
            'invoices'   => $salesCount + $purchasesCount,
            'sales'      => $salesCount,
            'purchases'  => $purchasesCount,
            'returns'    => $returnsCount,
            'employees'  => $employeesCount,
            'coupons'    => $couponsCount,
            'total_sales'     => 0,
            'total_purchases' => 0,
        ];

        try {
            $baseStats = Database::row(
                'SELECT
                    (SELECT COUNT(*) FROM customers WHERE book_id=? AND deleted_at IS NULL) AS customers,
                    (SELECT COUNT(*) FROM suppliers WHERE book_id=? AND deleted_at IS NULL) AS suppliers,
                    (SELECT COUNT(*) FROM products  WHERE book_id=? AND deleted_at IS NULL) AS products,
                    (SELECT COALESCE(SUM(paid),0) FROM invoices WHERE book_id=? AND type="sale"     AND deleted_at IS NULL) AS total_sales,
                    (SELECT COALESCE(SUM(paid),0) FROM invoices WHERE book_id=? AND type="purchase" AND deleted_at IS NULL) AS total_purchases',
                array_fill(0,5,$bid)
            );
            $stats = array_merge($stats, $baseStats ?? []);
        } catch (\Throwable $e) {}

        // Restore computed counts that might have been overwritten
        $stats['sales']     = $salesCount;
        $stats['purchases'] = $purchasesCount;
        $stats['returns']   = $returnsCount;
        $stats['employees'] = $employeesCount;
        $stats['coupons']   = $couponsCount;

        // Pass member permissions to view for conditional rendering
        $viewPerms = book_member_perms($book);

        require BASE_PATH . '/views/books/business.php';
    }

    private function handleLogoUpload(array $file): ?string
    {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext,['jpg','jpeg','png','webp','svg'])) return null;
        if ($file['size'] > 2*1024*1024) return null;

        $uploadPath = config('upload.path');
        $dir = $uploadPath . '/logos';

        if (!is_dir($dir)) {
            if (!mkdir($dir, 0755, true)) {
                error_log('Cannot create logo dir: '.$dir);
                return null;
            }
        }

        if (!is_writable($dir)) {
            error_log('Logo dir not writable: '.$dir);
            return null;
        }

        $filename = 'logo_'.date('Ymd_His').'_'.bin2hex(random_bytes(4)).'.'.$ext;
        $dest     = $dir.'/'.$filename;

        if (move_uploaded_file($file['tmp_name'], $dest)) {
            return 'logos/'.$filename;
        }

        error_log('move_uploaded_file failed: '.$dest);
        return null;
    }

        private function getBookOrFail(string $id): array
    {
        $book = book_for_user($id);
        if (!$book) { http_response_code(404); require BASE_PATH.'/views/errors/404.php'; exit; }
        return $book;
    }
}