<?php
namespace App\Controllers;
use App\Helpers\Database;

class BookSearchController
{
    public function search(array $params): void
    {
        if (guest()) json_response(['error' => 'Unauthorized'], 401);

        $book = Database::row(
            'SELECT * FROM books WHERE id=? AND user_id=? AND deleted_at IS NULL',
            [$params['id'], auth()['id']]
        );
        if (!$book) json_response(['error' => 'Not found'], 404);

        $q    = trim($_GET['q'] ?? '');
        $like = '%' . $q . '%';

        if (strlen($q) < 1) json_response(['results' => []]);

        $results = [];

        if ($book['type'] === 'business') {
            $bookId = $book['id'];

            // Customers
            foreach (Database::query(
                'SELECT id, name, phone FROM customers
                 WHERE book_id=? AND deleted_at IS NULL AND (name LIKE ? OR phone LIKE ? OR email LIKE ?)
                 LIMIT 5',
                [$bookId, $like, $like, $like]
            ) as $c) {
                $results[] = ['type'=>'Customer','label'=>$c['name'].($c['phone']?' — '.$c['phone']:''),'url'=>'/books/'.$bookId.'/customers/'.$c['id']];
            }

            // Suppliers
            foreach (Database::query(
                'SELECT id, name, phone, company FROM suppliers
                 WHERE book_id=? AND deleted_at IS NULL AND (name LIKE ? OR phone LIKE ? OR company LIKE ?)
                 LIMIT 5',
                [$bookId, $like, $like, $like]
            ) as $s) {
                $results[] = ['type'=>'Supplier','label'=>$s['name'].($s['company']?' ('.$s['company'].')':''),'url'=>'/books/'.$bookId.'/suppliers/'.$s['id']];
            }

            // Products
            foreach (Database::query(
                'SELECT id, name, product_code, stock_qty, unit FROM products
                 WHERE book_id=? AND deleted_at IS NULL AND (name LIKE ? OR product_code LIKE ? OR sku LIKE ? OR barcode LIKE ?)
                 LIMIT 5',
                [$bookId, $like, $like, $like, $like]
            ) as $p) {
                $results[] = ['type'=>'Product','label'=>$p['name'].' ['.$p['product_code'].'] stock: '.rtrim(rtrim(number_format($p['stock_qty'],3),'0'),'.').' '.$p['unit'],'url'=>'/books/'.$bookId.'/products'];
            }

            // Invoices
            foreach (Database::query(
                'SELECT i.id, i.invoice_no, i.type, i.total, i.status, c.name AS cname, s.name AS sname
                 FROM invoices i
                 LEFT JOIN customers c ON i.customer_id=c.id
                 LEFT JOIN suppliers s ON i.supplier_id=s.id
                 WHERE i.book_id=? AND i.deleted_at IS NULL AND (i.invoice_no LIKE ? OR c.name LIKE ? OR s.name LIKE ?)
                 LIMIT 5',
                [$bookId, $like, $like, $like]
            ) as $inv) {
                $party = $inv['cname'] ?? $inv['sname'] ?? '';
                $results[] = ['type'=>ucfirst($inv['type']).' Invoice','label'=>$inv['invoice_no'].($party?' — '.$party:'').' ('.$inv['status'].')','url'=>'/books/'.$bookId.'/invoices/'.$inv['id']];
            }

            // Returns
            try {
                foreach (Database::query(
                    'SELECT r.id, r.return_no, r.type, r.total_refund, c.name AS cname, s.name AS sname
                     FROM returns r
                     LEFT JOIN customers c ON r.customer_id=c.id
                     LEFT JOIN suppliers s ON r.supplier_id=s.id
                     WHERE r.book_id=? AND r.deleted_at IS NULL AND (r.return_no LIKE ? OR c.name LIKE ? OR s.name LIKE ?)
                     LIMIT 4',
                    [$bookId, $like, $like, $like]
                ) as $ret) {
                    $party = $ret['cname'] ?? $ret['sname'] ?? '';
                    $label = ($ret['type']==='sales_return'?'Sales Return':'Purchase Return');
                    $results[] = ['type'=>'Return','label'=>($ret['return_no']??'#'.$ret['id']).' '.$label.($party?' — '.$party:''),'url'=>'/books/'.$bookId.'/returns/'.$ret['id']];
                }
            } catch (\Throwable $e) {}

            // Expenses
            try {
                foreach (Database::query(
                    'SELECT id, title, amount, expense_date FROM expenses
                     WHERE book_id=? AND (title LIKE ? OR paid_to LIKE ?)
                     LIMIT 4',
                    [$bookId, $like, $like]
                ) as $exp) {
                    $results[] = ['type'=>'Expense','label'=>$exp['title'].' — '.number_format($exp['amount'],0),'url'=>'/books/'.$bookId.'/expenses'];
                }
            } catch (\Throwable $e) {}

            // Dues
            try {
                foreach (Database::query(
                    'SELECT d.id, d.title, d.amount, d.status, c.name AS cname
                     FROM dues d LEFT JOIN customers c ON d.customer_id=c.id
                     WHERE d.book_id=? AND (d.title LIKE ? OR c.name LIKE ?)
                     LIMIT 4',
                    [$bookId, $like, $like]
                ) as $due) {
                    $results[] = ['type'=>'Due','label'=>$due['title'].($due['cname']?' ('.$due['cname'].')':'').' — '.$due['status'],'url'=>'/books/'.$bookId.'/dues'];
                }
            } catch (\Throwable $e) {}

            // Debts
            try {
                foreach (Database::query(
                    'SELECT d.id, d.title, d.amount, d.status, s.name AS sname
                     FROM debts d LEFT JOIN suppliers s ON d.supplier_id=s.id
                     WHERE d.book_id=? AND (d.title LIKE ? OR d.party LIKE ? OR s.name LIKE ?)
                     LIMIT 4',
                    [$bookId, $like, $like, $like]
                ) as $debt) {
                    $results[] = ['type'=>'Debt','label'=>$debt['title'].($debt['sname']?' ('.$debt['sname'].')':'').' — '.$debt['status'],'url'=>'/books/'.$bookId.'/debts'];
                }
            } catch (\Throwable $e) {}

            // Coupons
            try {
                foreach (Database::query(
                    'SELECT id, name, code, discount_type, discount_value FROM coupons
                     WHERE book_id=? AND deleted_at IS NULL AND (name LIKE ? OR code LIKE ?)
                     LIMIT 4',
                    [$bookId, $like, $like]
                ) as $c) {
                    $results[] = ['type'=>'Coupon','label'=>$c['name'].' ['.$c['code'].'] '.($c['discount_type']==='percent'?$c['discount_value'].'%':'৳'.$c['discount_value']).' off','url'=>'/books/'.$bookId.'/coupons'];
                }
            } catch (\Throwable $e) {}

            // Employees
            try {
                foreach (Database::query(
                    'SELECT id, name, phone, designation_name FROM employees
                     WHERE book_id=? AND deleted_at IS NULL AND (name LIKE ? OR phone LIKE ? OR designation_name LIKE ?)
                     LIMIT 4',
                    [$bookId, $like, $like, $like]
                ) as $emp) {
                    $results[] = ['type'=>'Employee','label'=>$emp['name'].($emp['designation_name']?' — '.$emp['designation_name']:''),'url'=>'/books/'.$bookId.'/employees/'.$emp['id']];
                }
            } catch (\Throwable $e) {}

            // Funds
            try {
                foreach (Database::query(
                    'SELECT id, type, title, amount FROM funds
                     WHERE book_id=? AND title LIKE ?
                     LIMIT 4',
                    [$bookId, $like]
                ) as $f) {
                    $results[] = ['type'=>($f['type']==='in'?'Fund In':'Fund Out'),'label'=>$f['title'].' — '.number_format($f['amount'],0),'url'=>'/books/'.$bookId.'/funds'];
                }
            } catch (\Throwable $e) {}

            // Contacts
            try {
                foreach (Database::query(
                    'SELECT id, name, phone FROM contacts
                     WHERE book_id=? AND deleted_at IS NULL AND (name LIKE ? OR phone LIKE ?)
                     LIMIT 4',
                    [$bookId, $like, $like]
                ) as $c) {
                    $results[] = ['type'=>'Contact','label'=>$c['name'].($c['phone']?' — '.$c['phone']:''),'url'=>'/books/'.$bookId.'/contacts'];
                }
            } catch (\Throwable $e) {}

        } else {
            // Personal book
            foreach (Database::query(
                'SELECT id, name, phone FROM contacts
                 WHERE book_id=? AND deleted_at IS NULL AND (name LIKE ? OR phone LIKE ?)
                 LIMIT 5',
                [$book['id'], $like, $like]
            ) as $c) {
                $results[] = ['type'=>'Contact','label'=>$c['name'].($c['phone']?' — '.$c['phone']:''),'url'=>'/books/'.$book['id'].'/contacts'];
            }

            foreach (Database::query(
                'SELECT id, title, amount, type FROM entries
                 WHERE book_id=? AND deleted_at IS NULL AND title LIKE ?
                 LIMIT 5',
                [$book['id'], $like]
            ) as $e) {
                $results[] = ['type'=>$e['type']==='in'?'Income':'Expense','label'=>$e['title'].' — ৳'.number_format($e['amount'],0),'url'=>'/books/'.$book['id']];
            }
        }

        // Cap total results
        $results = array_slice($results, 0, 30);

        json_response(['results' => $results]);
    }
}
