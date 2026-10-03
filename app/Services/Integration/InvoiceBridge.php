<?php
namespace App\Services\Integration;

use App\Helpers\Database;
use App\Services\InvoicePdfService;

/**
 * Lets the connected online store show the Book's own invoice to its customers.
 *
 *  - info():   small, non-sensitive facts the store keeps next to its order (the invoice number)
 *  - render(): the invoice as PDF bytes, produced by the same service as the Book's own PDF
 *
 * Everything is looked up through the connection's order link, so a store can only ever reach invoices of
 * orders it already knows about, inside its own book.
 */
final class InvoiceBridge
{
    /** @return ?array{invoice_no:string,invoice_id:int,book_id:int,status:string,public_url:string} */
    public static function info(array $conn, int $invoiceId): ?array
    {
        $inv = Database::row('SELECT id, book_id, invoice_no, status, public_token FROM invoices WHERE id=? AND book_id=? AND deleted_at IS NULL AND type IN ("sale","pos")', [$invoiceId, $conn['book_id']]);
        if (!$inv || (string)$inv['invoice_no'] === '') return null;
        return [
            'invoice_no' => (string)$inv['invoice_no'], 'invoice_id' => (int)$inv['id'], 'book_id' => (int)$inv['book_id'], 'status' => (string)$inv['status'],
            'public_url' => $inv['public_token'] ? rtrim((string)config('url'), '/') . '/invoice/' . $inv['public_token'] : null,
        ];
    }

    /** Invoice for an order uuid, or null when the store's order has no invoice here (yet). */
    public static function forOrderUuid(array $conn, string $orderUuid): ?array
    {
        $id = Links::localFor((int)$conn['id'], 'order', $orderUuid);
        return $id ? self::info($conn, $id) : null;
    }

    /** @return ?string PDF bytes */
    public static function render(array $conn, int $invoiceId): ?string
    {
        $invoice = Database::row('SELECT * FROM invoices WHERE id=? AND book_id=? AND deleted_at IS NULL', [$invoiceId, $conn['book_id']]);
        if (!$invoice) return null;
        $book     = Database::row('SELECT * FROM books WHERE id=?', [$conn['book_id']]);
        $items    = Database::query('SELECT * FROM invoice_items WHERE invoice_id=?', [$invoice['id']]);
        $customer = $invoice['customer_id'] ? Database::row('SELECT * FROM customers WHERE id=?', [$invoice['customer_id']]) : null;
        $details  = Database::row('SELECT * FROM book_business_details WHERE book_id=?', [$book['id']]);
        $creator  = !empty($invoice['created_by']) ? Database::row('SELECT name FROM users WHERE id=?', [$invoice['created_by']]) : null;
        // An online order is shown to its customer as "Online store", never with the name of a staff member.
        if (($invoice['source'] ?? null) === 'online_store') $creator = ['name' => 'Online store'];
        try {
            return (new InvoicePdfService())->generate($book, $invoice, $items, $customer, null, $details, $creator, true);
        } catch (\Throwable $e) {
            error_log('[integration invoice pdf] ' . $e->getMessage());
            return null;
        }
    }
}
