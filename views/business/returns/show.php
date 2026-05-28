<?php
$pageTitle = 'Return ' . e($return['return_no']) . ' — ' . e($book['name']);
$sym = \App\Helpers\Database::row('SELECT symbol FROM book_currencies WHERE book_id=? AND is_default=1', [$book['id']]);
$sym = $sym['symbol'] ?? '৳';
$isSale = $return['type'] === 'sales_return';
$party  = $isSale ? $customer : $supplier;
ob_start();
?>

<div class="page-header">
    <div class="page-header-left">
        <div class="breadcrumb">
            <a href="/books/<?= $book['id'] ?>">Dashboard</a> <span>›</span>
            <a href="/books/<?= $book['id'] ?>/returns">Returns</a> <span>›</span>
            <span><?= e($return['return_no']) ?></span>
        </div>
        <h1><?= $isSale ? '↩ Sales Return' : '↪ Purchase Return' ?> — <?= e($return['return_no']) ?></h1>
    </div>
    <div style="display:flex;gap:8px">
        <form method="POST" action="/books/<?= $book['id'] ?>/returns/<?= $return['id'] ?>/delete"
              onsubmit="return confirm('Delete this return? Stock changes will NOT be reversed.')">
            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
            <button class="btn btn-secondary" style="color:var(--red)"><i class="fa-solid fa-trash"></i> Delete</button>
        </form>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 300px;gap:20px">

<div style="display:flex;flex-direction:column;gap:16px">
    <!-- Party info -->
    <div class="card">
        <div class="form-grid">
            <div>
                <div style="font-size:11px;font-weight:700;text-transform:uppercase;color:var(--text-muted);margin-bottom:4px"><?= $isSale ? 'Customer' : 'Supplier' ?></div>
                <div style="font-size:15px;font-weight:600"><?= e($party['name'] ?? 'Unknown') ?></div>
                <?php if (!empty($party['phone'])): ?>
                <div style="font-size:12px;color:var(--text-muted)"><?= e($party['phone']) ?></div>
                <?php endif; ?>
            </div>
            <div>
                <div style="font-size:11px;font-weight:700;text-transform:uppercase;color:var(--text-muted);margin-bottom:4px">Original Invoice</div>
                <?php if ($invoice): ?>
                <a href="/books/<?= $book['id'] ?>/invoices/<?= $invoice['id'] ?>" style="font-size:15px;font-weight:600;color:var(--brand)">
                    <?= e($invoice['invoice_no']) ?>
                </a>
                <div style="font-size:12px;color:var(--text-muted)"><?= date('d M Y', strtotime($invoice['date'])) ?></div>
                <?php else: ?><span style="color:var(--text-muted)">—</span><?php endif; ?>
            </div>
            <div>
                <div style="font-size:11px;font-weight:700;text-transform:uppercase;color:var(--text-muted);margin-bottom:4px">Return Date</div>
                <div style="font-size:15px;font-weight:600"><?= date('d M Y', strtotime($return['date'])) ?></div>
            </div>
        </div>
    </div>

    <!-- Items -->
    <div class="card">
        <p class="card-title">Returned Items</p>
        <table style="width:100%;border-collapse:collapse">
            <thead>
                <tr style="background:var(--bg)">
                    <th style="padding:8px 10px;font-size:11px;font-weight:700;text-transform:uppercase;color:var(--text-muted);text-align:left;border-bottom:1px solid var(--border)">Item</th>
                    <th style="padding:8px 10px;font-size:11px;font-weight:700;text-transform:uppercase;color:var(--text-muted);text-align:right;border-bottom:1px solid var(--border);width:80px">Qty</th>
                    <th style="padding:8px 10px;font-size:11px;font-weight:700;text-transform:uppercase;color:var(--text-muted);text-align:right;border-bottom:1px solid var(--border);width:90px">Unit Price</th>
                    <th style="padding:8px 10px;font-size:11px;font-weight:700;text-transform:uppercase;color:var(--text-muted);text-align:right;border-bottom:1px solid var(--border);width:90px">Total</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($items as $item): ?>
            <tr style="border-bottom:1px solid var(--border)">
                <td style="padding:9px 10px;font-size:13px">
                    <?= e($item['description']) ?>
                    <?php if (!empty($item['product_name'])): ?>
                    <div style="font-size:11px;color:var(--text-muted)"><?= e($item['product_name']) ?></div>
                    <?php endif; ?>
                </td>
                <td style="padding:9px 10px;text-align:right;font-size:13px"><?= rtrim(rtrim(number_format((float)$item['qty'],3,'.',''),'0'),'.') ?></td>
                <td style="padding:9px 10px;text-align:right;font-size:13px"><?= $sym.number_format($item['unit_price'],0) ?></td>
                <td style="padding:9px 10px;text-align:right;font-size:13px;font-weight:600"><?= $sym.number_format($item['line_total'],0) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($return['remarks']): ?>
    <div class="card">
        <p class="card-title">Remarks</p>
        <p style="font-size:14px;color:var(--text-muted)"><?= nl2br(e($return['remarks'])) ?></p>
    </div>
    <?php endif; ?>
</div>

<!-- RIGHT -->
<div style="display:flex;flex-direction:column;gap:12px">
    <div class="card">
        <p class="card-title">Summary</p>
        <div style="display:flex;flex-direction:column;gap:8px;font-size:14px">
            <?php
            $rows = [
                ['Subtotal', $sym.number_format($return['subtotal'],0), ''],
                ['Discount kept / Loss', $return['discount']>0 ? '-'.$sym.number_format($return['discount'],0) : '—', 'var(--red)'],
                ['Delivery / Handling', $return['delivery_charge']>0 ? '+'.$sym.number_format($return['delivery_charge'],0) : '—', ''],
            ];
            foreach ($rows as [$label,$val,$clr]):
            ?>
            <div style="display:flex;justify-content:space-between">
                <span style="color:var(--text-muted)"><?= $label ?></span>
                <span <?= $clr ? 'style="color:'.$clr.'"' : '' ?>><?= $val ?></span>
            </div>
            <?php endforeach; ?>
            <div style="border-top:2px solid var(--border);padding-top:10px;display:flex;justify-content:space-between">
                <strong>Total Refund</strong>
                <strong style="font-size:18px;color:<?= $isSale?'var(--red)':'var(--green)' ?>"><?= $sym.number_format($return['total_refund'],0) ?></strong>
            </div>
        </div>
    </div>

    <div class="card" style="font-size:12px;color:var(--text-muted)">
        <p class="card-title">Stock Impact</p>
        <?php if ($isSale): ?>
        <p><i class="fa-solid fa-arrow-up" style="color:var(--green)"></i> Items added back to stock</p>
        <?php else: ?>
        <p><i class="fa-solid fa-arrow-down" style="color:var(--red)"></i> Items removed from stock</p>
        <?php endif; ?>
        <?php if ($return['discount'] > 0): ?>
        <p style="margin-top:6px">
            <i class="fa-solid fa-chart-line" style="color:var(--brand)"></i>
            <?= $isSale ? 'Non-refunded amount recorded as income in Reports' : 'Non-recovered amount recorded as loss in Reports' ?>
        </p>
        <?php endif; ?>
        <?php if ($return['delivery_charge'] > 0): ?>
        <p style="margin-top:6px">
            <i class="fa-solid fa-receipt" style="color:var(--amber)"></i>
            Delivery charge added to Expenses
        </p>
        <?php endif; ?>
    </div>
</div>

</div>

<?php $content = ob_get_clean(); require BASE_PATH . '/views/partials/layout.php'; ?>
