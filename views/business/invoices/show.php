<?php
$pageTitle = e($invoice['invoice_no']) . ' — Byabsayee';
$due       = $invoice['total'] - $invoice['paid'];
$sym       = $invoice['currency_symbol'] ?? '৳';
$statusColors = [
    'draft'     => ['badge-gray',  'Draft'],
    'sent'      => ['badge-blue',  'Sent'],
    'partial'   => ['badge-amber', 'Partial'],
    'paid'      => ['badge-green', 'Paid'],
    'overdue'   => ['badge-red',   'Overdue'],
    'cancelled' => ['badge-gray',  'Cancelled'],
];
[$sc, $sl] = $statusColors[$invoice['status']] ?? ['badge-gray','Unknown'];

// Load extra data
$attachments = \App\Helpers\Database::query(
    'SELECT * FROM invoice_attachments WHERE invoice_id=? ORDER BY created_at',
    [$invoice['id']]
);

$payments = \App\Helpers\Database::query(
    'SELECT * FROM payments WHERE invoice_id=? ORDER BY date ASC, created_at ASC',
    [$invoice['id']]
);

$creator = null;
if ($invoice['created_by'] ?? null) {
    $creator = \App\Helpers\Database::row('SELECT id, name, email FROM users WHERE id=?', [$invoice['created_by']]);
}

$assignedPrivs = [];
if ($customer) {
    $assignedPrivs = \App\Helpers\Database::query(
        'SELECT cp.* FROM customer_privilege_assignments cpa
         JOIN customer_privileges cp ON cp.id=cpa.privilege_id
         WHERE cpa.customer_id=?',
        [$customer['id']]
    );
}

$paymentMethodOpts = \App\Helpers\Database::query(
    'SELECT * FROM invoice_method_options WHERE book_id=? AND type="payment" ORDER BY sort_order',
    [$book['id']]
);

$couponDiscount    = (float)($invoice['coupon_discount']    ?? 0);
$privilegeDiscount = (float)($invoice['privilege_discount'] ?? 0);
$couponCode     = $invoice['coupon_code'] ?? '';
$handlingCharge = (float)($invoice['handling_charge'] ?? 0);
$pointsDiscount = (float)($invoice['points_discount'] ?? 0);
$deliveryCharge = (float)($invoice['delivery_charge'] ?? 0);
$rounding       = (float)($invoice['rounding'] ?? 0);

ob_start();
?>

<div class="page-header">
    <div class="page-header-left">
        <div class="breadcrumb">
            <a href="/books/<?= $book['id'] ?>">Books</a> <span>›</span>
            <a href="/books/<?= $book['id'] ?>/invoices">Invoices</a> <span>›</span>
            <span><?= e($invoice['invoice_no']) ?></span>
        </div>
        <h1 style="display:flex;align-items:center;gap:10px">
            <?= e($invoice['invoice_no']) ?>
            <span class="badge <?= $sc ?>"><?= $sl ?></span>
            <?php if ($invoice['type'] === 'pos'): ?>
            <span class="badge badge-blue">POS</span>
            <?php endif; ?>
        </h1>
        <p><?= ucfirst($invoice['type']) ?> · <?= format_date($invoice['date']) ?></p>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
        <a href="/books/<?= $book['id'] ?>/invoices/<?= $invoice['id'] ?>/pdf"
           class="btn btn-primary" target="_blank"><i class="fa-solid fa-print"></i> Print / PDF</a>
        <a href="/books/<?= $book['id'] ?>/invoices/<?= $invoice['id'] ?>/thermal?w=58"
           class="btn btn-secondary" target="_blank"><i class="fa-solid fa-print"></i> 58/80mm</a>
        <?php if ($invoice['status'] === 'draft'): ?>
        <form method="POST" action="/books/<?= $book['id'] ?>/invoices/<?= $invoice['id'] ?>/sent">
            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
            <button class="btn btn-secondary">Mark Sent</button>
        </form>
        <?php endif; ?>
        <?php if ($due > 0 && $invoice['status'] !== 'cancelled'): ?>
        <button class="btn btn-primary" data-modal="paymentModal">Record Payment</button>
        <?php endif; ?>
        <form method="POST" action="/books/<?= $book['id'] ?>/invoices/<?= $invoice['id'] ?>/delete"
              data-confirm="Delete invoice <?= e($invoice['invoice_no']) ?>?">
            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
            <button class="btn btn-danger">Delete</button>
        </form>
    </div>
</div>

<!-- Privilege hint -->
<?php if (!empty($assignedPrivs) && in_array($invoice['type'], ['sale','pos'])): ?>
<div style="background:var(--green-bg);border:1px solid #bbf7d0;border-radius:var(--radius);padding:10px 14px;margin-bottom:16px;font-size:13px;color:var(--green)">
    <i class="fa-solid fa-ticket"></i> <strong><?= e($customer['name']) ?></strong> has:
    <?php foreach ($assignedPrivs as $priv): ?>
        <strong><?= e($priv['name']) ?></strong>
        (<?= $priv['discount_type']==='percent' ? $priv['discount_value'].'%' : $sym.number_format($priv['discount_value'],2) ?> off)
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;align-items:start">

<!-- ═══ LEFT ═══ -->
<div style="display:flex;flex-direction:column;gap:16px">

    <!-- Business + Party -->
    <div class="card">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
            <div>
                <p class="card-label">From</p>
                <div style="font-size:13px;line-height:1.9">
                    <strong><?= e($details['business_name'] ?? $book['name']) ?></strong><br>
                    <?php if ($book['phone'] ?? $details['phone'] ?? ''): ?>
                        <span style="color:var(--text-muted)"><i class="fa-solid fa-phone"></i></span> <?= e($book['phone'] ?? $details['phone']) ?><br>
                    <?php endif; ?>
                    <?php if ($book['email'] ?? $details['email'] ?? ''): ?>
                        <span style="color:var(--text-muted)"><i class="fa-regular fa-envelope"></i></span> <?= e($book['email'] ?? $details['email']) ?><br>
                    <?php endif; ?>
                    <?php if ($book['address'] ?? $details['address'] ?? ''): ?>
                        <span style="color:var(--text-muted)"><i class="fa-solid fa-location-dot"></i></span> <?= e($book['address'] ?? $details['address']) ?>
                    <?php endif; ?>
                </div>
            </div>
            <div>
                <p class="card-label">
                    <?= in_array($invoice['type'],['sale','pos']) ? 'Bill To' : 'From Supplier' ?>
                </p>
                <div style="font-size:13px;line-height:1.9">
                    <?php if ($customer): ?>
                        <strong><?= e($customer['name']) ?></strong><br>
                        <?php if ($customer['phone']): ?><span style="color:var(--text-muted)"><i class="fa-solid fa-phone"></i></span> <?= e($customer['phone']) ?><br><?php endif; ?>
                        <?php if ($customer['email'] ?? ''): ?><span style="color:var(--text-muted)"><i class="fa-regular fa-envelope"></i></span> <?= e($customer['email']) ?><br><?php endif; ?>
                        <?php if ($customer['address']): ?><span style="color:var(--text-muted)"><i class="fa-solid fa-location-dot"></i></span> <?= e($customer['address']) ?><?php endif; ?>
                    <?php elseif ($supplier): ?>
                        <strong><?= e($supplier['name']) ?></strong><br>
                        <?php if ($supplier['company']): ?><?= e($supplier['company']) ?><br><?php endif; ?>
                        <?php if ($supplier['phone']): ?><span style="color:var(--text-muted)"><i class="fa-solid fa-phone"></i></span> <?= e($supplier['phone']) ?><?php endif; ?>
                    <?php else: ?>
                        <span style="color:var(--text-muted)">Walk-in Customer</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Items table -->
    <div class="card">
        <p class="card-title" style="margin-bottom:12px">Items</p>
        <div class="table-wrap" style="border:none;border-radius:0;margin:0 -20px">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Item</th>
                        <th>Variant</th>
                        <th>ID</th>
                        <th style="text-align:right">Qty</th>
                        <th style="text-align:right">Price</th>
                        <th style="text-align:right">Total</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($items as $n => $item):
                    $productCode = '';
                    if ($item['product_id']) {
                        $prod = \App\Helpers\Database::row('SELECT product_code FROM products WHERE id=?', [$item['product_id']]);
                        $productCode = $prod['product_code'] ?? '';
                    }
                ?>
                <tr>
                    <td class="td-muted"><?= $n+1 ?></td>
                    <td style="font-weight:500"><?= e($item['description']) ?></td>
                    <td class="td-muted"><?= $item['variant'] ? e($item['variant']) : '—' ?></td>
                    <td>
                        <?php if ($productCode): ?>
                        <span style="font-family:monospace;font-size:11px;background:var(--bg);padding:1px 5px;border-radius:4px;border:1px solid var(--border)"><?= e($productCode) ?></span>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                    <td style="text-align:right" class="td-muted"><?= rtrim(rtrim(number_format($item['qty'],3),'0'),'.') ?></td>
                    <td style="text-align:right"><?= $sym.number_format($item['unit_price'],0) ?></td>
                    <td style="text-align:right;font-weight:600"><?= $sym.number_format($item['line_total'],0) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Totals -->
        <div style="display:flex;justify-content:flex-end;margin-top:16px">
            <div style="width:280px;font-size:13px;display:flex;flex-direction:column;gap:6px">
                <div style="display:flex;justify-content:space-between">
                    <span style="color:var(--text-muted)">Subtotal</span>
                    <span><?= $sym.number_format($invoice['subtotal'],0) ?></span>
                </div>
                <?php if ($deliveryCharge>0): ?>
                <div style="display:flex;justify-content:space-between">
                    <span style="color:var(--text-muted)">Delivery</span>
                    <span>+ <?= $sym.number_format($deliveryCharge,0) ?></span>
                </div>
                <?php endif; ?>
                <?php if ($handlingCharge>0): ?>
                <div style="display:flex;justify-content:space-between">
                    <span style="color:var(--text-muted)">Handling</span>
                    <span>+ <?= $sym.number_format($handlingCharge,0) ?></span>
                </div>
                <?php endif; ?>
                <?php if ($invoice['tax']>0): ?>
                <div style="display:flex;justify-content:space-between">
                    <span style="color:var(--text-muted)">Tax</span>
                    <span>+ <?= $sym.number_format($invoice['tax'],0) ?></span>
                </div>
                <?php endif; ?>
                <?php if ($invoice['discount']>0): ?>
                <div style="display:flex;justify-content:space-between">
                    <span style="color:var(--text-muted)">Discount</span>
                    <span style="color:var(--red)">− <?= $sym.number_format($invoice['discount'],0) ?></span>
                </div>
                <?php endif; ?>
                <?php if ($pointsDiscount>0): ?>
                <div style="display:flex;justify-content:space-between">
                    <span style="color:var(--text-muted)">Points Discount</span>
                    <span style="color:var(--red)">− <?= $sym.number_format($pointsDiscount,0) ?></span>
                </div>
                <?php endif; ?>
                <?php if ($couponDiscount>0): ?>
                <div style="display:flex;justify-content:space-between">
                    <span style="color:var(--text-muted)">
                        Coupon<?= $couponCode ? ' <code style="font-size:11px;background:var(--bg);padding:1px 5px;border-radius:4px;border:1px solid var(--border)">'.e($couponCode).'</code>' : '' ?>
                    </span>
                    <span style="color:var(--red)">− <?= $sym.number_format($couponDiscount,0) ?></span>
                </div>
                <?php endif; ?>
                <?php if ($privilegeDiscount>0): ?>
                <div style="display:flex;justify-content:space-between">
                    <span style="color:var(--text-muted)">Privilege Discount
                        <?php if (!empty($assignedPrivs[0])): ?>
                        <span style="font-size:11px;background:var(--green-bg,#f0fdf4);color:var(--green);padding:1px 6px;border-radius:10px;margin-left:4px"><?= e($assignedPrivs[0]['name']) ?></span>
                        <?php endif; ?>
                    </span>
                    <span style="color:var(--green)">− <?= $sym.number_format($privilegeDiscount,0) ?></span>
                </div>
                <?php endif; ?>
                <?php if ($rounding>0): ?>
                <div style="display:flex;justify-content:space-between">
                    <span style="color:var(--text-muted)">Rounding</span>
                    <span style="color:var(--red)">− <?= $sym.number_format($rounding,2) ?></span>
                </div>
                <?php endif; ?>
                <div style="display:flex;justify-content:space-between;border-top:2px solid var(--border);padding-top:8px;font-size:16px;font-weight:700">
                    <span>Grand Total</span>
                    <span style="color:var(--brand)"><?= $sym.number_format($invoice['total'],0) ?></span>
                </div>
                <div style="display:flex;justify-content:space-between">
                    <span style="color:var(--text-muted)">Paid</span>
                    <span style="color:var(--green);font-weight:600"><?= $sym.number_format($invoice['paid'],0) ?></span>
                </div>
                <div style="display:flex;justify-content:space-between;font-weight:600">
                    <span>Balance Due</span>
                    <span style="color:<?= $due>0?'var(--red)':'var(--green)' ?>"><?= $sym.number_format($due,0) ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Payment History -->
    <?php if (!empty($payments)): ?>
    <div class="card">
        <p class="card-title" style="margin-bottom:12px">Payment History</p>
        <div class="table-wrap" style="border:none;border-radius:0;margin:0 -20px">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Date</th>
                        <th>Method</th>
                        <th>Note</th>
                        <th style="text-align:right">Amount</th>
                    </tr>
                </thead>
                <tbody>
                <?php $runningTotal = 0; foreach ($payments as $pi => $p): $runningTotal += $p['amount']; ?>
                <tr>
                    <td class="td-muted"><?= $pi+1 ?></td>
                    <td><?= format_date($p['date']) ?></td>
                    <td>
                        <span style="background:var(--bg);border:1px solid var(--border);border-radius:5px;padding:2px 8px;font-size:12px">
                            <?= e($p['method']) ?>
                        </span>
                    </td>
                    <td class="td-muted"><?= $p['note'] ? e($p['note']) : '—' ?></td>
                    <td style="text-align:right;font-weight:600;color:var(--green)"><?= $sym.number_format($p['amount'],0) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="font-weight:700;font-size:13px">
                        <td colspan="4" style="text-align:right;padding-right:12px;color:var(--text-muted)">Total Paid</td>
                        <td style="text-align:right;color:var(--green)"><?= $sym.number_format($invoice['paid'],0) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Delivery + Payment method -->
    <?php if ($invoice['delivery_method'] || $invoice['payment_method'] || $invoice['delivery_type'] ?? ''): ?>
    <div class="card">
        <p class="card-title" style="margin-bottom:10px">Logistics</p>
        <div style="display:flex;gap:24px;flex-wrap:wrap;font-size:13px">
            <?php if ($invoice['delivery_method']): ?>
            <div><span style="color:var(--text-muted)">Delivery Method:</span> <strong><?= e($invoice['delivery_method']) ?></strong></div>
            <?php endif; ?>
            <?php if ($invoice['delivery_type'] ?? ''): ?>
            <div><span style="color:var(--text-muted)">Delivery Type:</span> <strong><?= e($invoice['delivery_type']) ?></strong></div>
            <?php endif; ?>
            <?php if ($invoice['payment_method']): ?>
            <div><span style="color:var(--text-muted)">Payment Method:</span> <strong><?= e($invoice['payment_method']) ?></strong></div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Notes -->
    <?php
    $noteCustomer = $invoice['note_customer'] ?? $invoice['notes'] ?? '';
    $noteSeller   = $invoice['note_seller'] ?? '';
    if ($noteCustomer || $noteSeller):
    ?>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <?php if ($noteCustomer): ?>
        <div class="card">
            <p class="card-title">Customer Note</p>
            <p style="font-size:13px;color:var(--text-muted);margin-top:6px"><?= nl2br(e($noteCustomer)) ?></p>
        </div>
        <?php endif; ?>
        <?php if ($noteSeller): ?>
        <div class="card">
            <p class="card-title">Seller Note</p>
            <p style="font-size:13px;color:var(--text-muted);margin-top:6px"><?= nl2br(e($noteSeller)) ?></p>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Attachments -->
    <?php if (!empty($attachments)): ?>
    <div class="card">
        <p class="card-title" style="margin-bottom:10px">Attachments</p>
        <div style="display:flex;flex-direction:column;gap:8px">
            <?php foreach ($attachments as $att): ?>
            <div style="display:flex;align-items:center;justify-content:space-between;padding:8px 12px;background:var(--bg);border-radius:8px;border:1px solid var(--border)">
                <div style="font-size:13px">
                    📎 <?= e($att['filename']) ?>
                    <span style="color:var(--text-muted);font-size:11px;margin-left:8px"><?= number_format($att['size']/1024,1) ?> KB</span>
                </div>
                <a href="<?= asset('uploads/'.$att['path']) ?>" target="_blank" class="btn btn-sm btn-secondary">View</a>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Add attachment (purchase) -->
    <?php if ($invoice['type'] === 'purchase'): ?>
    <div class="card">
        <p class="card-title" style="margin-bottom:10px">Add Attachment</p>
        <form method="POST" action="/books/<?= $book['id'] ?>/invoices/<?= $invoice['id'] ?>/attachment"
              enctype="multipart/form-data" style="display:flex;gap:10px;align-items:flex-end">
            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
            <div class="form-group" style="flex:1;margin:0">
                <input type="file" name="attachment" accept=".pdf,.jpg,.jpeg,.png,.webp" style="width:100%">
            </div>
            <button type="submit" class="btn btn-secondary">Upload</button>
        </form>
    </div>
    <?php endif; ?>

</div><!-- end LEFT -->

<!-- ═══ RIGHT ═══ -->
<div style="display:flex;flex-direction:column;gap:12px">

    <!-- Payment summary card -->
    <div class="card">
        <p class="card-title">Payment Summary</p>
        <div style="display:flex;flex-direction:column;gap:10px;margin-top:10px">
            <div class="stat-card" style="border:none;padding:0">
                <div class="stat-label">Grand Total</div>
                <div class="stat-value brand" style="font-size:22px"><?= $sym.number_format($invoice['total'],0) ?></div>
            </div>
            <div style="display:flex;gap:10px">
                <div style="flex:1;background:var(--green-bg);border-radius:8px;padding:10px">
                    <div style="font-size:11px;color:var(--green);font-weight:600;margin-bottom:2px">PAID</div>
                    <div style="font-size:15px;font-weight:600;color:var(--green)"><?= $sym.number_format($invoice['paid'],0) ?></div>
                </div>
                <div style="flex:1;background:<?= $due>0?'var(--red-bg)':'var(--green-bg)' ?>;border-radius:8px;padding:10px">
                    <div style="font-size:11px;color:<?= $due>0?'var(--red)':'var(--green)' ?>;font-weight:600;margin-bottom:2px">DUE</div>
                    <div style="font-size:15px;font-weight:600;color:<?= $due>0?'var(--red)':'var(--green)' ?>"><?= $sym.number_format($due,0) ?></div>
                </div>
            </div>
            <?php if ($due > 0 && $invoice['status'] !== 'cancelled'): ?>
            <button class="btn btn-primary" style="width:100%" data-modal="paymentModal">+ Record Payment</button>
            <?php else: ?>
            <div style="text-align:center;font-size:13px;color:var(--green);font-weight:500"><i class="fa-solid fa-check"></i> Fully Paid</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Invoice details -->
    <div class="card">
        <p class="card-title">Invoice Details</p>
        <div style="font-size:13px;display:flex;flex-direction:column;gap:8px;margin-top:10px">
            <div class="info-row"><span>Invoice No</span> <strong><?= e($invoice['invoice_no']) ?></strong></div>
            <div class="info-row"><span>Type</span> <strong><?= ucfirst($invoice['type']) ?></strong></div>
            <div class="info-row"><span>Status</span> <span class="badge <?= $sc ?>"><?= $sl ?></span></div>
            <div class="info-row"><span>Date</span> <strong><?= format_date($invoice['date']) ?></strong></div>
            <?php if ($invoice['due_date']): ?>
            <div class="info-row">
                <span>Due Date</span>
                <strong style="color:<?= strtotime($invoice['due_date'])<time()&&$due>0?'var(--red)':'inherit' ?>">
                    <?= format_date($invoice['due_date']) ?>
                </strong>
            </div>
            <?php endif; ?>
            <div class="info-row"><span>Currency</span> <strong><?= e($invoice['currency_code']??'BDT') ?> (<?= e($sym) ?>)</strong></div>
            <?php if ($invoice['theme_color'] ?? ''): ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Discounts & extras summary -->
    <?php if ($couponCode || $pointsDiscount > 0 || $handlingCharge > 0): ?>
    <div class="card">
        <p class="card-title">Discounts & Extras</p>
        <div style="font-size:13px;display:flex;flex-direction:column;gap:8px;margin-top:10px">
            <?php if ($couponCode): ?>
            <div class="info-row">
                <span>Coupon Code</span>
                <code style="background:var(--bg);padding:2px 8px;border-radius:5px;border:1px solid var(--border);font-size:12px;font-weight:700;color:var(--brand)"><?= e($couponCode) ?></code>
            </div>
            <div class="info-row">
                <span>Coupon Discount</span>
                <strong style="color:var(--red)">− <?= $sym.number_format($couponDiscount,2) ?></strong>
            </div>
            <?php endif; ?>
            <?php if ($pointsDiscount > 0): ?>
            <div class="info-row"><span>Points Discount</span> <strong style="color:var(--red)">− <?= $sym.number_format($pointsDiscount,2) ?></strong></div>
            <?php endif; ?>
            <?php if ($handlingCharge > 0): ?>
            <div class="info-row"><span>Handling Charge</span> <strong>+ <?= $sym.number_format($handlingCharge,2) ?></strong></div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Created by / audit -->
    <div class="card">
        <p class="card-title">Audit Info</p>
        <div style="font-size:13px;display:flex;flex-direction:column;gap:8px;margin-top:10px">
            <?php if ($creator): ?>
            <div class="info-row">
                <span>Created By</span>
                <strong><?= e($creator['name']) ?></strong>
            </div>
            <div class="info-row">
                <span>User Email</span>
                <span style="color:var(--text-muted)"><?= e($creator['email']) ?></span>
            </div>
            <?php endif; ?>
            <div class="info-row">
                <span>Created At</span>
                <strong><?= date('d M Y, h:i A', strtotime($invoice['created_at'])) ?></strong>
            </div>
            <?php if ($invoice['updated_at'] ?? ''): ?>
            <div class="info-row">
                <span>Last Updated</span>
                <span style="color:var(--text-muted)"><?= date('d M Y, h:i A', strtotime($invoice['updated_at'])) ?></span>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Customer loyalty -->
    <?php if ($customer): ?>
    <div class="card">
        <p class="card-title">Customer</p>
        <div style="font-size:13px;display:flex;flex-direction:column;gap:8px;margin-top:10px">
            <div class="info-row"><span>Name</span> <strong><?= e($customer['name']) ?></strong></div>
            <?php if ($customer['phone']): ?>
            <div class="info-row"><span>Phone</span> <span><?= e($customer['phone']) ?></span></div>
            <?php endif; ?>
            <?php if ($customer['email'] ?? ''): ?>
            <div class="info-row"><span>Email</span> <span style="color:var(--text-muted)"><?= e($customer['email']) ?></span></div>
            <?php endif; ?>
            <?php if ($customer['points'] ?? 0): ?>
            <div class="info-row">
                <span>Loyalty Points</span>
                <strong style="color:var(--accent)"><?= number_format($customer['points']) ?> pts</strong>
            </div>
            <?php endif; ?>
            <div style="margin-top:4px">
                <a href="/books/<?= $book['id'] ?>/customers/<?= $customer['id'] ?>" class="btn btn-sm btn-secondary" style="width:100%;justify-content:center">
                    View Customer Profile <i class="fa-solid fa-arrow-right-long"></i>
                </a>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Public link -->
    <?php if ($invoice['public_token'] ?? ''): ?>
    <div class="card">
        <p class="card-title">Public Link</p>
        <p style="font-size:12px;color:var(--text-muted);margin-bottom:8px">Share with customer — no login needed</p>
        <a href="<?= asset('invoice/'.$invoice['public_token']) ?>" target="_blank"
           class="btn btn-sm btn-secondary" style="width:100%;justify-content:center"><i class="fa-solid fa-link"></i> View Public Invoice</a>
    </div>
    <?php endif; ?>

</div><!-- end RIGHT -->
</div><!-- end grid -->

<!-- PAYMENT MODAL -->
<div class="modal-backdrop" id="paymentModal">
    <div class="modal">
        <div class="modal-title">Record Payment</div>
        <form method="POST" action="/books/<?= $book['id'] ?>/invoices/<?= $invoice['id'] ?>/payment">
            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
            <div class="form-grid" style="gap:12px">
                <div class="form-group full">
                    <label>Amount — Due: <?= $sym.number_format($due,0) ?></label>
                    <input type="number" name="amount" value="<?= $due ?>"
                           min="0.01" step="0.01" max="<?= $due ?>" required>
                </div>
                <div class="form-group full">
                    <label>Payment Method</label>
                    <select name="method">
                        <?php foreach ($paymentMethodOpts as $pm): ?>
                        <option value="<?= e($pm['label']) ?>"><?= e($pm['label']) ?></option>
                        <?php endforeach; ?>
                        <?php if (empty($paymentMethodOpts)): ?>
                        <option value="Cash">Cash</option>
                        <option value="bKash">bKash</option>
                        <option value="Bank Transfer">Bank Transfer</option>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="form-group full">
                    <label>Note (optional)</label>
                    <input type="text" name="note" placeholder="e.g. Cash received">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-close-modal>Cancel</button>
                <button type="submit" class="btn btn-primary">Save Payment</button>
            </div>
        </form>
    </div>
</div>

<style>
.card-label {
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    color: var(--text-muted);
    margin-bottom: 8px;
}
.info-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 8px;
    padding: 4px 0;
    border-bottom: 1px solid var(--border);
}
.info-row:last-child { border-bottom: none; }
.info-row > span:first-child { color: var(--text-muted); flex-shrink: 0; }
</style>

<?php $content = ob_get_clean(); require BASE_PATH . '/views/partials/layout.php'; ?>