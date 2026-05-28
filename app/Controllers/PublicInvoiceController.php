<?php
namespace App\Controllers;
use App\Helpers\Database;

/**
 * Public shareable invoice — /invoice/{token}
 * Uses the same full-size print view as the private invoice print page.
 * No login required.
 */
class PublicInvoiceController
{
    public function show(array $params): void
    {
        $token = $params['token'] ?? '';

        $invoice = Database::row(
            'SELECT i.*, b.id AS book_id, b.name AS book_name,
                    b.logo, b.phone AS book_phone, b.email AS book_email,
                    b.address AS book_address, b.theme_color,
                    bd.business_name, bd.address AS biz_address,
                    bd.phone AS biz_phone, bd.footer_note
             FROM invoices i
             JOIN books b ON b.id = i.book_id
             LEFT JOIN book_business_details bd ON bd.book_id = b.id
             WHERE i.public_token = ? AND i.deleted_at IS NULL',
            [$token]
        );

        if (!$invoice) {
            http_response_code(404);
            echo '<!DOCTYPE html><html><head><meta charset="UTF-8">
                  <title>Not Found</title>
                  <style>body{font-family:sans-serif;text-align:center;padding:80px;color:#555}
                  h2{font-size:24px;margin-bottom:12px}</style></head><body>
                  <h2>Invoice Not Found</h2>
                  <p>This link is invalid or has expired.</p></body></html>';
            return;
        }

        $items    = Database::query('SELECT * FROM invoice_items WHERE invoice_id=?', [$invoice['id']]);
        $customer = $invoice['customer_id'] ? Database::row('SELECT * FROM customers WHERE id=?', [$invoice['customer_id']]) : null;
        $supplier = $invoice['supplier_id'] ? Database::row('SELECT * FROM suppliers WHERE id=?', [$invoice['supplier_id']]) : null;
        $details  = Database::row('SELECT * FROM book_business_details WHERE book_id=?', [$invoice['book_id']]);

        // Build variables the print view expects
        $book = [
            'id'          => $invoice['book_id'],
            'name'        => $invoice['book_name'],
            'logo'        => $invoice['logo'] ?? '',
            'phone'       => $invoice['book_phone'] ?? '',
            'email'       => $invoice['book_email'] ?? '',
            'address'     => $invoice['book_address'] ?? '',
            'theme_color' => $invoice['theme_color'] ?? '#1a6b4a',
        ];
        $themeColor = $invoice['theme_color'] ?? '#1a6b4a';
        $bizName    = $invoice['business_name'] ?? $invoice['book_name'];
        $bizAddress = $invoice['biz_address'] ?? $invoice['book_address'] ?? '';
        $bizPhone   = $invoice['biz_phone']   ?? $invoice['book_phone']   ?? '';
        $bizEmail   = $details['email'] ?? '';
        $isPublic   = true;   // hides the back button, shows minimal top bar

        require BASE_PATH . '/views/business/invoices/print.php';
    }

    // Legacy slug-based URL — redirect to token URL
    public function showByNo(array $params): void
    {
        $slug      = $params['slug']       ?? '';
        $invoiceNo = $params['invoice_no'] ?? '';

        $invoice = Database::row(
            'SELECT i.public_token FROM invoices i
             JOIN books b ON b.id=i.book_id
             WHERE i.invoice_no=? AND b.slug=? AND i.deleted_at IS NULL',
            [$invoiceNo, $slug]
        );

        if ($invoice && !empty($invoice['public_token'])) {
            header('Location: /invoice/' . $invoice['public_token'], true, 301);
            exit;
        }
        http_response_code(404);
        echo 'Invoice not found.';
    }
}
