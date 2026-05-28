<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Receipt — <?= e($invoice['invoice_no']) ?></title>
<style>
<?php
$mmW = $paperWidth === 58 ? 48 : 72; // printable width in mm
$pxW = $paperWidth === 58 ? '200px' : '302px';
?>
@page {
    size: <?= $paperWidth ?>mm auto;
    margin: 0;
}
* { box-sizing:border-box; margin:0; padding:0; }
body {
    font-family: 'Courier New', monospace;
    font-size: <?= $paperWidth===58 ? '10px' : '11px' ?>;
    width: <?= $pxW ?>;
    padding: 4px 6px;
    background: #fff;
    color: #000;
}
.center  { text-align:center; }
.right   { text-align:right; }
.bold    { font-weight:bold; }
.line    { border-top:1px dashed #666; margin:4px 0; }
.double  { border-top:2px solid #000; margin:4px 0; }
.biz     { font-size:<?= $paperWidth===58?'12px':'14px'?>; font-weight:bold; text-align:center; }
.total   { font-size:<?= $paperWidth===58?'14px':'16px'?>; font-weight:bold; }
table    { width:100%; border-collapse:collapse; }
td, th   { padding:1px 2px; }
.no-print { display:none; }
.print-btn {
    display:block;
    margin:10px auto;
    padding:8px 24px;
    background:#1a6b4a;
    color:#fff;
    border:none;
    border-radius:6px;
    cursor:pointer;
    font-size:14px;
}
@media print {
    .print-btn, .print-controls { display:none !important; }
    body { width:<?= $mmW ?>mm; }
}
</style>
</head>
<body>

<!-- Print controls (hidden on print) -->
<div class="print-controls" style="margin-bottom:8px;display:flex;gap:6px;flex-wrap:wrap">
    <button class="print-btn" onclick="window.print()"><i class="fa-solid fa-print"></i> Print</button>
    <a href="?w=<?= $paperWidth===58?80:58 ?>" style="display:inline-block;padding:6px 14px;background:#f0f0f0;border-radius:6px;font-size:12px;text-decoration:none;color:#333">
        Switch to <?= $paperWidth===58?'80':'58' ?>mm
    </a>
    <a href="/books/<?= $book['id'] ?>/invoices/<?= $invoice['id'] ?>" style="display:inline-block;padding:6px 14px;background:#f0f0f0;border-radius:6px;font-size:12px;text-decoration:none;color:#333">
        <i class="fa-solid fa-arrow-left-long"></i> Back
    </a>
</div>

<?php
$details = $details ?? [];
$bizName = $details['business_name'] ?? $book['name'];
$phone   = $details['phone'] ?? $book['phone'] ?? '';
$address = $details['address'] ?? '';
$sym     = $invoice['currency_symbol'] ?? '৳';
$party   = $customer ?? $supplier ?? null;
$inWords = invoiceNumToWords((int)round($total), $curCode) . ' Only';
$shareUrl = (defined('BASE_PATH') ? (config('url') ?? '') : '') . '/invoice/' . ($invoice['public_token'] ?? '');
$creator = null;
if ($invoice['created_by'] ?? null) {
    $creator = \App\Helpers\Database::row('SELECT id, name, email FROM users WHERE id=?', [$invoice['created_by']]);
}
$email = $details['email'] ?? $book['email'] ?? '';
?>

<!-- Business header -->
<div class="biz"><?= e($bizName) ?></div>
<?php if ($address): ?>
<div class="center" style="font-size:9px"><?= e(str_replace("\n",', ',$address)) ?></div>
<?php endif; ?>
<?php if ($phone): ?>
<div class="center" style="font-size:9px"><?= e($phone) ?></div>
<?php endif; ?>
<?php if ($email): ?>
<div class="center" style="font-size:9px"><?= e($email) ?></div>
<?php endif; ?>

<div class="line"></div>

<!-- Invoice info -->
<table>
    <tr><td class="bold">Invoice:</td><td class="right"><?= e($invoice['invoice_no']) ?></td></tr>
    <tr><td class="bold">Date:</td><td class="right"><?= date('d/m/Y', strtotime($invoice['date'])) ?></td></tr>
    <?php if ($party): ?>
    <tr><td class="bold">Customer:</td><td class="right"><?= e($party['name']) ?></td></tr>
    <?php endif; ?>
    <?php if ($invoice['payment_method']): ?>
    <tr><td class="bold">Payment:</td><td class="right"><?= e($invoice['payment_method']) ?></td></tr>
    <?php endif; ?>
</table>

<div class="line"></div>

<!-- Items -->
<table>
<thead>
    <tr>
        <th style="text-align:left">Item</th>
        <th style="text-align:right;width:30px">Qty</th>
        <th style="text-align:right;width:55px">Price</th>
        <th style="text-align:right;width:55px">Total</th>
    </tr>
</thead>
<tbody>
<?php foreach ($items as $item):
    $qty = rtrim(rtrim(number_format((float)$item['qty'],3,'.',''),'0'),'.');
    $name = $item['description'];
    if (strlen($name) > 18) $name = substr($name, 0, 16).'..';
?>
<tr>
    <td><?= e($name) ?><?= !empty($item['variant']) ? ' <'.e($item['variant']).'>' : '' ?></td>
    <td class="right"><?= $qty ?></td>
    <td class="right"><?= number_format((float)$item['unit_price'],0) ?></td>
    <td class="right bold"><?= number_format((float)$item['line_total'],0) ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>

<div class="line"></div>

<!-- Totals -->
<table>
<?php if ((float)$invoice['subtotal'] > 0): ?>
<tr><td>Subtotal:</td><td class="right">+<?= $sym.number_format($invoice['subtotal'],0) ?></td></tr>
<?php endif; ?>
<?php if ((float)($invoice['delivery_charge']??0) > 0): ?>
<tr><td>Delivery:</td><td class="right">+<?= $sym.number_format($invoice['delivery_charge'],0) ?></td></tr>
<?php endif; ?>
<?php if ((float)($invoice['handling_charge']??0) > 0): ?>
<tr><td>Handling:</td><td class="right">+<?= $sym.number_format($invoice['handling_charge'],0) ?></td></tr>
<?php endif; ?>
<?php if ((float)($invoice['tax']??0) > 0): ?>
<tr><td>Tax:</td><td class="right">+<?= $sym.number_format($invoice['tax'],0) ?></td></tr>
<?php endif; ?>
<?php if ((float)$invoice['discount'] > 0): ?>
<tr><td>Discount:</td><td class="right">-<?= $sym.number_format($invoice['discount'],0) ?></td></tr>
<?php endif; ?>
<?php if ((float)($invoice['points_discount'] ?? 0) > 0): ?>
<tr><td>Points:</td><td class="right">-<?= $sym.number_format($invoice['points_discount'],0) ?></td></tr>
<?php endif; ?>
<?php if ((float)($invoice['coupon_discount'] ?? 0) > 0): ?>
<tr><td>Coupon:</td><td class="right">-<?= $sym.number_format($invoice['coupon_discount'],0) ?></td></tr>
<?php endif; ?>
<?php if ((float)($invoice['rounding']??0) > 0): ?>
<tr><td>Rounding:</td><td class="right">-<?= $sym.number_format($invoice['rounding'],2) ?></td></tr>
<?php endif; ?>
</table>

<div class="double"></div>

<table>
    <tr>
        <td class="bold total">TOTAL</td>
        <td class="right total"><?= $sym.number_format((float)$invoice['total'],0) ?></td>
    </tr>
    <?php if ((float)$invoice['paid'] > 0): ?>
    <tr><td>Paid:</td><td class="right"><?= $sym.number_format($invoice['paid'],0) ?></td></tr>
    <?php $due = (float)$invoice['total'] - (float)$invoice['paid'];
          if ($due > 0): ?>
    <tr><td class="bold" style="color:#c00">Due:</td><td class="right bold" style="color:#c00"><?= $sym.number_format($due,0) ?></td></tr>
    <?php endif; ?>
    <?php endif; ?>
    <tr><td>In Words:</td><td><?= e($inWords) ?></td></tr>
</table>

<div class="double"></div>

<div class="qr">
    <img src="https://api.qrserver.com/v1/create-qr-code/?size=70x70&data=<?= urlencode($shareUrl) ?>" style="width:70px; height:70px; margin:0 auto; display:block;" alt="QR">
</div>

<div class="double"></div>

<?php if ($invoice['note_customer']): ?>
<div style="font-size:9px"><?= e($invoice['note_customer']) ?></div>
<div class="line"></div>
<?php endif; ?>

<!-- Footer -->
<div class="center" style="font-size:9px;margin-top:4px">Thank you for your purchase!</div>
<?php if (!empty($details['footer_note'])): ?>
<div class="center" style="font-size:8px;margin-top:2px"><?= e($details['footer_note']) ?></div>
<?php endif; ?>
<div class="line"></div>
<div class="center" style="font-size:8px;margin-top:4px;color:#666">Generated using Byabsayee<?= $creator ? ' by '.e($creator['name']) : '' ?> at <?= date('d M Y, h:i A', strtotime($invoice['created_at'])) ?></div>

<script>
// Auto-open print dialog after a short delay (can be disabled by user)
setTimeout(() => {
    if (window.location.search.includes('autoprint=1')) window.print();
}, 500);
</script>
</body>
</html>
