<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Invoice <?= e($invoice['invoice_no']) ?> — <?= e($invoice['business_name'] ?? $invoice['book_name']) ?></title>
<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
<link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
<link rel="shortcut icon" href="/favicon.ico">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',sans-serif;font-size:14px;color:#1a1a1a;background:#f5f5f5;padding:20px}
.invoice-wrap{max-width:720px;margin:0 auto;background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 2px 20px rgba(0,0,0,.08)}
.inv-header{padding:28px 32px;border-bottom:1px solid #eee;display:flex;justify-content:space-between;align-items:flex-start}
.inv-title{font-size:32px;font-weight:800;letter-spacing:-1px}
.inv-logo img{max-height:60px;max-width:150px;object-fit:contain}
.inv-logo .placeholder{font-size:22px;font-style:italic;color:#ccc;font-family:serif}
.inv-meta{background:#f9f9f9;padding:14px 32px;display:flex;justify-content:space-between;font-size:13px;border-bottom:1px solid #eee}
.inv-meta strong{color:#1a1a1a}
.inv-parties{display:grid;grid-template-columns:1fr 1fr;gap:0;border-bottom:1px solid #eee}
.party{padding:20px 32px;font-size:13px;line-height:1.8}
.party:first-child{border-right:1px solid #eee}
.party-label{font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:1px;margin-bottom:8px}
.party-note{margin-top:10px;font-size:12px;color:#666}
.items-section{padding:0}
table{width:100%;border-collapse:collapse}
thead th{padding:10px 14px;font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.5px;color:#fff;text-align:left;border:none}
tbody td{padding:10px 14px;border-bottom:1px solid #f0f0f0;font-size:13px;vertical-align:middle}
tbody tr:last-child td{border-bottom:none}
tbody tr:nth-child(even){background:#fafafa}
.text-right{text-align:right}
.text-center{text-align:center}
.below-table{padding:16px 32px 20px;display:grid;grid-template-columns:1fr 1fr;gap:20px;border-top:1px solid #eee}
.delivery-info{font-size:13px;line-height:2}
.totals-block{font-size:13px}
.total-row{display:flex;justify-content:space-between;padding:3px 0}
.total-row.grand{font-size:15px;font-weight:800;padding-top:8px;border-top:2px solid #ddd}
.in-text-block{font-size:12px;color:#555;padding:10px 32px;border-top:1px solid #eee;border-bottom:1px solid #eee;background:#fafafa}
.payments-section{padding:16px 32px;border-top:1px solid #eee}
.sig-section{padding:24px 32px;display:flex;justify-content:space-between;border-top:1px solid #eee}
.sig-box{text-align:center;width:40%}
.sig-line{border-top:1.5px solid #1a1a1a;padding-top:6px;margin-top:40px;font-size:12px;font-weight:700}
.footer{text-align:center;padding:14px 20px;font-size:11px;color:#999;border-top:1px solid #eee;background:#f9f9f9}
.footer a{color:#999}
.status-badge{display:inline-block;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600}
.status-paid{background:#f0fdf4;color:#16a34a}
.status-partial{background:#fffbeb;color:#d97706}
.status-overdue{background:#fef2f2;color:#dc2626}
.status-draft,.status-sent{background:#eff6ff;color:#2563eb}
@media(max-width:600px){
    .inv-parties{grid-template-columns:1fr}
    .party:first-child{border-right:none;border-bottom:1px solid #eee}
    .below-table{grid-template-columns:1fr}
    .sig-section{flex-direction:column;gap:30px}
    .sig-box{width:100%}
    body{padding:10px}
}
</style>
</head>
<body>
<?php
$theme   = $invoice['theme_color'] ?? '#1a6b4a';
$sym     = $invoice['currency_symbol'] ?? '৳';
$bizName = $invoice['business_name'] ?? $invoice['book_name'];
$party   = $customer ?? $supplier ?? null;

$subtotal = (float)$invoice['subtotal'];
$discount = (float)$invoice['discount'];
$points   = (float)($invoice['points_discount'] ?? 0);
$delivery = (float)($invoice['delivery_charge'] ?? 0);
$rounding = (float)($invoice['rounding'] ?? 0);
$tax      = (float)$invoice['tax'];
$total    = (float)$invoice['total'];
$paid     = (float)$invoice['paid'];
$due      = $total - $paid;
?>

<div class="invoice-wrap">

    <!-- Header -->
    <div class="inv-header">
        <div>
            <div class="inv-title" style="color:<?= e($theme) ?>">Invoice</div>
            <div style="font-size:12px;color:#888;margin-top:4px">
                <span class="status-badge status-<?= e($invoice['status']) ?>"><?= ucfirst($invoice['status']) ?></span>
            </div>
        </div>
        <div class="inv-logo">
            <?php if (!empty($invoice['book_logo'])): ?>
                <img src="<?= asset('uploads/' . $invoice['book_logo']) ?>"
                     onerror="this.parentNode.innerHTML='<span class=\'placeholder\'>'+<?= json_encode($bizName) ?>+'</span>'">
            <?php else: ?>
                <span class="placeholder"><?= e($bizName) ?></span>
            <?php endif; ?>
        </div>
    </div>

    <!-- Invoice No + Date -->
    <div class="inv-meta" style="border-top:2.5px solid <?= e($theme) ?>;border-bottom:2.5px solid <?= e($theme) ?>">
        <div><strong>Invoice No:</strong> <?= e($invoice['invoice_no']) ?></div>
        <div><strong>Date:</strong> <?= date('d/m/Y', strtotime($invoice['date'])) ?>
            <?php if ($invoice['due_date']): ?>
                &nbsp;&nbsp;<strong>Due:</strong> <?= date('d/m/Y', strtotime($invoice['due_date'])) ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Bill To / Bill From -->
    <div class="inv-parties">
        <div class="party">
            <div class="party-label" style="color:<?= e($theme) ?>">Bill To —</div>
            <?php if ($party): ?>
                <strong><?= e($party['name']) ?></strong><br>
                <?php if ($party['address']): ?><?= e($party['address']) ?><br><?php endif; ?>
                <?php if ($party['phone']): ?><?= e($party['phone']) ?><br><?php endif; ?>
                <?php if ($party['email']): ?><?= e($party['email']) ?><?php endif; ?>
            <?php else: ?>
                <span style="color:#888">Walk-in Customer</span>
            <?php endif; ?>
            <?php if ($invoice['note_customer'] ?? ''): ?>
                <div class="party-note"><strong>Note:</strong> <?= e($invoice['note_customer']) ?></div>
            <?php endif; ?>
        </div>
        <div class="party">
            <div class="party-label" style="color:<?= e($theme) ?>">Bill From —</div>
            <strong><?= e($bizName) ?></strong><br>
            <?php if ($invoice['book_address']): ?><?= e($invoice['book_address']) ?><br><?php endif; ?>
            <?php if ($invoice['book_phone']): ?><?= e($invoice['book_phone']) ?><br><?php endif; ?>
            <?php if ($invoice['book_email']): ?><?= e($invoice['book_email']) ?><?php endif; ?>
            <?php if ($invoice['note_seller'] ?? ''): ?>
                <div class="party-note"><strong>Note:</strong> <?= e($invoice['note_seller']) ?></div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Items table -->
    <div class="items-section">
        <table>
            <thead style="background:<?= e($theme) ?>">
                <tr>
                    <th class="text-center" style="width:36px">NO</th>
                    <th>DESCRIPTION</th>
                    <th class="text-center" style="width:90px">COLOR/SIZE</th>
                    <th class="text-center" style="width:60px">ID</th>
                    <th class="text-right" style="width:50px">QTY</th>
                    <th class="text-right" style="width:90px">UNIT PRICE</th>
                    <th class="text-right" style="width:80px">TOTAL</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($items as $n => $item): ?>
            <tr>
                <td class="text-center" style="color:#888"><?= $n+1 ?></td>
                <td style="font-weight:500"><?= e($item['description']) ?></td>
                <td class="text-center" style="color:#555"><?= $item['variant'] ? e($item['variant']) : '—' ?></td>
                <td class="text-center" style="color:#555;font-size:12px"><?= $item['sku'] ? e($item['sku']) : '—' ?></td>
                <td class="text-right"><?= rtrim(rtrim(number_format($item['qty'],3),'0'),'.') ?></td>
                <td class="text-right"><?= $sym.number_format($item['unit_price'],0) ?></td>
                <td class="text-right" style="font-weight:600"><?= $sym.number_format($item['line_total'],0) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- In text + delivery/totals -->
    <div class="below-table">
        <div class="delivery-info">
            <?php if ($invoice['delivery_method']): ?>
            <div><strong>Delivery Method:</strong> <?= e($invoice['delivery_method']) ?></div>
            <?php endif; ?>
            <?php if ($invoice['payment_method']): ?>
            <div><strong>Payment Method:</strong> <?= e($invoice['payment_method']) ?></div>
            <?php endif; ?>
        </div>

        <div class="totals-block">
            <div class="total-row">
                <span style="color:#666">Subtotal</span>
                <span><?= $sym.number_format($subtotal,0) ?></span>
            </div>
            <?php if ($discount > 0): ?>
            <div class="total-row">
                <span style="color:#666">Discount [<?= round($discount/($subtotal?:1)*100) ?>%]</span>
                <span style="color:#dc2626">(<?= $sym.number_format($discount,0) ?>)</span>
            </div>
            <?php endif; ?>
            <?php if ($points > 0): ?>
            <div class="total-row">
                <span style="color:#666">Points</span>
                <span style="color:#dc2626">(<?= $sym.number_format($points,0) ?>)</span>
            </div>
            <?php endif; ?>
            <?php if ($delivery > 0): ?>
            <div class="total-row">
                <span style="color:#666">Delivery</span>
                <span><?= $sym.number_format($delivery,0) ?></span>
            </div>
            <?php endif; ?>
            <?php if ($rounding > 0): ?>
            <div class="total-row">
                <span style="color:#666">Rounding</span>
                <span style="color:#dc2626">(<?= $sym.number_format($rounding,0) ?>)</span>
            </div>
            <?php endif; ?>
            <div class="total-row grand" style="color:<?= e($theme) ?>">
                <span>Grand Total</span>
                <span><?= $sym.number_format($total,0) ?></span>
            </div>
            <?php if ($paid > 0): ?>
            <div class="total-row" style="color:#16a34a">
                <span>Paid</span>
                <span><?= $sym.number_format($paid,0) ?></span>
            </div>
            <div class="total-row" style="color:<?= $due > 0 ? '#dc2626' : '#16a34a' ?>;font-weight:600">
                <span>Balance Due</span>
                <span><?= $sym.number_format($due,0) ?></span>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Amount in text -->
    <div class="in-text-block">
        <strong>In Words:</strong>
        <?= e(ucfirst($this->numberToWords($total, $invoice['currency_code'] ?? 'BDT'))) ?> Only
    </div>

    <!-- Payment history (if any payments made) -->
    <?php if (!empty($payments)): ?>
    <div class="payments-section">
        <div style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#888;margin-bottom:8px">Payment History</div>
        <?php foreach ($payments as $pmt): ?>
        <div style="display:flex;justify-content:space-between;font-size:13px;padding:4px 0;border-bottom:1px solid #f0f0f0">
            <span><?= format_date($pmt['date']) ?> — <?= e($pmt['method']) ?></span>
            <strong style="color:#16a34a"><?= $sym.number_format($pmt['amount'],0) ?></strong>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Signatures -->
    <div class="sig-section">
        <div class="sig-box">
            <div class="sig-line" style="border-color:<?= e($theme) ?>">
                Buyer (<?= e($party['name'] ?? 'Customer') ?>)
            </div>
        </div>
        <div class="sig-box">
            <div class="sig-line" style="border-color:<?= e($theme) ?>; text-align:right">
                Seller
            </div>
        </div>
    </div>

    <!-- Thank you -->
    <div style="text-align:center;font-style:italic;color:<?= e($theme) ?>;padding:12px 20px;font-size:13px;border-top:2px solid <?= e($theme) ?>">
        It was a pleasure doing business with you, we hope to hear from you soon!
    </div>

    <!-- Footer -->
    <div class="footer">
        This invoice was generated using <a href="https://byabsayee.com">Byabsayee</a>
    </div>

</div>
</body>
</html>
<?php
// Helper method — not a class method here so we use a function
function numberToWords_invoice(float $number, string $currencyCode): string {
    $ones = ['','One','Two','Three','Four','Five','Six','Seven','Eight','Nine',
             'Ten','Eleven','Twelve','Thirteen','Fourteen','Fifteen','Sixteen',
             'Seventeen','Eighteen','Nineteen'];
    $tens = ['','','Twenty','Thirty','Forty','Fifty','Sixty','Seventy','Eighty','Ninety'];

    $n = (int)round($number);
    if ($n === 0) return 'Zero';

    $currencyNames = [
        'BDT' => 'Taka', 'USD' => 'Dollar', 'EUR' => 'Euro',
        'GBP' => 'Pound', 'INR' => 'Rupee', 'SAR' => 'Riyal',
    ];
    $currencyName = $currencyNames[$currencyCode] ?? $currencyCode;

    $convert = function(int $n) use ($ones, $tens, &$convert): string {
        if ($n < 20) return $ones[$n];
        if ($n < 100) return $tens[(int)($n/10)] . ($n%10 ? ' '.$ones[$n%10] : '');
        if ($n < 1000) return $ones[(int)($n/100)].' Hundred'.($n%100 ? ' '.$convert($n%100) : '');
        if ($n < 100000) return $convert((int)($n/1000)).' Thousand'.($n%1000 ? ' '.$convert($n%1000) : '');
        if ($n < 10000000) return $convert((int)($n/100000)).' Lakh'.($n%100000 ? ' '.$convert($n%100000) : '');
        return $convert((int)($n/10000000)).' Crore'.($n%10000000 ? ' '.$convert($n%10000000) : '');
    };

    return $convert($n) . ' ' . $currencyName;
}
?>