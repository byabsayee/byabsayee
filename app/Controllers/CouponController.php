<?php
namespace App\Controllers;
use App\Helpers\Database;

class CouponController
{
    public function index(array $params): void
    {
        if (guest()) redirect('/login');
        $book = $this->getBookOrFail($params['id']);
        if (!book_can($book, 'coupons', 'view')) abort_403();

        $filter = $_GET['filter'] ?? 'all';
        $search = trim($_GET['q'] ?? '');

        $where = ['book_id=?'];
        $bind  = [$book['id']];

        if ($filter === 'active')   { $where[] = 'is_active=1'; }
        if ($filter === 'inactive') { $where[] = 'is_active=0'; }
        if ($search !== '') {
            $where[] = '(name LIKE ? OR code LIKE ?)';
            $bind[]  = "%{$search}%";
            $bind[]  = "%{$search}%";
        }

        $whereSQL = implode(' AND ', $where);
        $coupons  = Database::query(
            "SELECT * FROM coupons WHERE {$whereSQL} ORDER BY is_active DESC, created_at DESC",
            $bind
        );

        $counts = Database::row(
            'SELECT COUNT(*) AS total, SUM(is_active=1) AS active_count, SUM(is_active=0) AS inactive_count
             FROM coupons WHERE book_id=?',
            [$book['id']]
        );

        require BASE_PATH . '/views/business/coupons/index.php';
    }

    public function store(array $params): void
    {
        if (guest()) redirect('/login');
        csrf_verify();
        $book = $this->getBookOrFail($params['id']);
        if (!book_can($book, 'coupons', 'create')) abort_403();

        $name       = trim($_POST['name']           ?? '');
        $code       = strtoupper(trim($_POST['code'] ?? ''));
        $type       = ($_POST['discount_type']      ?? 'fixed') === 'percent' ? 'percent' : 'fixed';
        $value      = (float)($_POST['discount_value'] ?? 0);
        $note       = trim($_POST['note']           ?? '');
        $expiryType = $_POST['expiry_type']         ?? 'none';
        $expiresAt  = null;

        if ($expiryType === 'date' && !empty($_POST['expires_at'])) {
            $expiresAt = date('Y-m-d H:i:s', strtotime($_POST['expires_at']));
        }

        if (!$name || !$code || $value <= 0)
            redirect('/books/'.$book['id'].'/coupons', ['error' => 'Name, code, and discount value are required.']);

        if ($type === 'percent' && $value > 100)
            redirect('/books/'.$book['id'].'/coupons', ['error' => 'Percentage cannot exceed 100%.']);

        if (!preg_match('/^[A-Z0-9\-_]{2,30}$/', $code))
            redirect('/books/'.$book['id'].'/coupons', ['error' => 'Code: 2–30 chars, letters/numbers/hyphens/underscores only.']);

        if (Database::row('SELECT id FROM coupons WHERE book_id=? AND code=?', [$book['id'], $code]))
            redirect('/books/'.$book['id'].'/coupons', ['error' => "Code \"{$code}\" already exists."]);

        Database::run(
            'INSERT INTO coupons (book_id,name,code,discount_type,discount_value,note,is_active,expires_at,created_by,created_at)
             VALUES (?,?,?,?,?,?,1,?,?,?)',
            [$book['id'],$name,$code,$type,$value,$note ?: null,$expiresAt,auth()['id'],now()]
        );

        redirect('/books/'.$book['id'].'/coupons', ['success' => "Coupon \"{$code}\" created."]);
    }

    public function update(array $params): void
    {
        if (guest()) redirect('/login');
        csrf_verify();
        $book   = $this->getBookOrFail($params['id']);
        if (!book_can($book, 'coupons', 'edit')) abort_403();
        $coupon = $this->getCouponOrFail($params['coupon_id'], $book['id']);

        $name       = trim($_POST['name']              ?? '');
        $code       = strtoupper(trim($_POST['code']   ?? ''));
        $type       = ($_POST['discount_type']         ?? 'fixed') === 'percent' ? 'percent' : 'fixed';
        $value      = (float)($_POST['discount_value'] ?? 0);
        $note       = trim($_POST['note']              ?? '');
        $expiryType = $_POST['expiry_type']            ?? 'none';
        $expiresAt  = null;

        if ($expiryType === 'date' && !empty($_POST['expires_at'])) {
            $expiresAt = date('Y-m-d H:i:s', strtotime($_POST['expires_at']));
        }

        if (!$name || !$code || $value <= 0)
            redirect('/books/'.$book['id'].'/coupons', ['error' => 'Name, code, and discount value are required.']);

        if ($type === 'percent' && $value > 100)
            redirect('/books/'.$book['id'].'/coupons', ['error' => 'Percentage cannot exceed 100%.']);

        if (!preg_match('/^[A-Z0-9\-_]{2,30}$/', $code))
            redirect('/books/'.$book['id'].'/coupons', ['error' => 'Code: 2–30 chars, letters/numbers/hyphens/underscores only.']);

        if (Database::row('SELECT id FROM coupons WHERE book_id=? AND code=? AND id!=?', [$book['id'],$code,$coupon['id']]))
            redirect('/books/'.$book['id'].'/coupons', ['error' => "Code \"{$code}\" is already used by another coupon."]);

        Database::run(
            'UPDATE coupons SET name=?,code=?,discount_type=?,discount_value=?,note=?,expires_at=? WHERE id=? AND book_id=?',
            [$name,$code,$type,$value,$note ?: null,$expiresAt,$coupon['id'],$book['id']]
        );

        redirect('/books/'.$book['id'].'/coupons', ['success' => 'Coupon updated.']);
    }

    public function toggle(array $params): void
    {
        if (guest()) redirect('/login');
        csrf_verify();
        $book   = $this->getBookOrFail($params['id']);
        if (!book_can($book, 'coupons', 'edit')) abort_403();
        $coupon = $this->getCouponOrFail($params['coupon_id'], $book['id']);
        $new    = $coupon['is_active'] ? 0 : 1;
        Database::run('UPDATE coupons SET is_active=? WHERE id=? AND book_id=?', [$new,$coupon['id'],$book['id']]);
        $msg = $new ? "Coupon \"{$coupon['code']}\" activated." : "Coupon \"{$coupon['code']}\" deactivated.";
        redirect('/books/'.$book['id'].'/coupons', ['success' => $msg]);
    }

    public function delete(array $params): void
    {
        if (guest()) redirect('/login');
        csrf_verify();
        $book = $this->getBookOrFail($params['id']);
        if (!book_can($book, 'coupons', 'delete')) abort_403();
        Database::run('DELETE FROM coupons WHERE id=? AND book_id=?', [$params['coupon_id'],$book['id']]);
        redirect('/books/'.$book['id'].'/coupons', ['success' => 'Coupon deleted.']);
    }

    public function printCoupons(array $params): void
    {
        if (guest()) redirect('/login');
        $book = $this->getBookOrFail($params['id']);
        if (!book_can($book, 'coupons', 'view')) abort_403();

        $ids = $_GET['ids'] ?? '';
        if ($ids) {
            $idList  = array_filter(array_map('intval', explode(',', $ids)));
            $ph      = implode(',', array_fill(0, count($idList), '?'));
            $coupons = $idList
                ? Database::query("SELECT * FROM coupons WHERE book_id=? AND id IN ({$ph})", array_merge([$book['id']],$idList))
                : [];
        } else {
            $coupons = Database::query(
                'SELECT * FROM coupons WHERE book_id=? AND is_active=1 ORDER BY created_at DESC',
                [$book['id']]
            );
        }

        $details    = Database::row('SELECT * FROM book_business_details WHERE book_id=?', [$book['id']]);
        $themeColor = $book['theme_color'] ?? '#1a6b4a';
        $bizName    = $details['business_name'] ?? $book['name'];
        $logoUrl = !empty($book['logo']) ? '/uploads/'.$book['logo'] : null;

        require BASE_PATH . '/views/business/coupons/print.php';
    }

    // AJAX — called from invoice create to validate a coupon code
    public function validateAjax(array $params): void
    {
        if (guest()) { http_response_code(401); echo json_encode(['error'=>'Unauthenticated']); exit; }
        header('Content-Type: application/json');

        $book     = $this->getBookOrFail($params['id']);
        if (!book_can($book, 'coupons', 'view')) abort_403();
        $code     = strtoupper(trim($_GET['code'] ?? ''));
        $subtotal = (float)($_GET['subtotal'] ?? 0);

        if (!$code) { echo json_encode(['error'=>'No code provided.']); exit; }

        $coupon = Database::row(
            'SELECT * FROM coupons WHERE book_id=? AND code=?',
            [$book['id'], $code]
        );

        if (!$coupon) { echo json_encode(['error'=>'Coupon not found.']); exit; }
        if (!$coupon['is_active']) { echo json_encode(['error'=>'This coupon is inactive.']); exit; }

        if ($coupon['expires_at'] && strtotime($coupon['expires_at']) < time()) {
            echo json_encode(['error'=>'Coupon expired on '.date('d M Y, h:i A', strtotime($coupon['expires_at'])).'.']);
            exit;
        }

        $discount = $coupon['discount_type'] === 'percent'
            ? round($subtotal * $coupon['discount_value'] / 100, 2)
            : min((float)$coupon['discount_value'], $subtotal);

        echo json_encode([
            'ok'             => true,
            'name'           => $coupon['name'],
            'discount_type'  => $coupon['discount_type'],
            'discount_value' => (float)$coupon['discount_value'],
            'discount'       => $discount,
            'expires_at'     => $coupon['expires_at'],
        ]);
        exit;
    }

    // Static helper — used by InvoiceController::store()
    public static function validate(int $bookId, string $code, float $subtotal): ?array
    {
        $coupon = Database::row(
            'SELECT * FROM coupons WHERE book_id=? AND code=? AND is_active=1',
            [$bookId, strtoupper($code)]
        );
        if (!$coupon) return null;
        if ($coupon['expires_at'] && strtotime($coupon['expires_at']) < time())
            return ['expired'=>true,'coupon'=>$coupon];
        $discount = $coupon['discount_type'] === 'percent'
            ? round($subtotal * $coupon['discount_value'] / 100, 2)
            : min((float)$coupon['discount_value'], $subtotal);
        return ['coupon'=>$coupon,'discount'=>$discount];
    }

    private function getCouponOrFail(string $couponId, int $bookId): array
    {
        $c = Database::row('SELECT * FROM coupons WHERE id=? AND book_id=?', [$couponId,$bookId]);
        if (!$c) { http_response_code(404); require BASE_PATH.'/views/errors/404.php'; exit; }
        return $c;
    }

        private function getBookOrFail(string $id): array
    {
        $book = book_for_user($id, 'business');
        if (!$book) { http_response_code(404); require BASE_PATH.'/views/errors/404.php'; exit; }
        return $book;
    }
}
