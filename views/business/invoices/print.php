<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Invoice <?= e($invoice['invoice_no']) ?> — <?= e($bizName ?? $book['name'] ?? '') ?></title>
<style>
/* ── Reset ─────────────────────────────────────────────────────────────────── */
*{box-sizing:border-box;margin:0;padding:0}
:root{--theme:<?= e($themeColor) ?>}

/* ── Screen wrapper ─────────────────────────────────────────────────────────── */
body{background:#e8e8e8;font-family:'Segoe UI',Arial,sans-serif;color:#1a1a1a;font-size:13px}
.page-wrap{max-width:820px;margin:0 auto;padding:16px}
.top-bar{display:flex;gap:8px;margin-bottom:12px;flex-wrap:wrap;align-items:center}
.top-bar h3{font-size:13px;font-weight:600;color:#444;margin-right:auto}
.tbtn{padding:7px 16px;border-radius:6px;border:none;cursor:pointer;font-size:13px;font-weight:600;display:inline-flex;align-items:center;gap:6px;text-decoration:none}
.tbtn-primary{background:var(--theme);color:#fff}
.tbtn-ghost{background:#fff;color:#333;border:1.5px solid #ccc}
.tbtn-share{background:#f0faf5;color:var(--theme);border:1.5px solid var(--theme)}
.share-box{background:#fff;border-radius:8px;padding:10px 14px;margin-bottom:10px;border:1.5px solid var(--theme);display:none;align-items:center;gap:8px}
.share-box input{flex:1;border:1px solid #ddd;border-radius:6px;padding:6px 10px;font-size:12px;font-family:inherit;outline:none;color:#333}
.share-box button{white-space:nowrap}

/* ── A4 sheet ───────────────────────────────────────────────────────────────── */
.invoice{background:#fff;box-shadow:0 4px 24px rgba(0,0,0,.15);border-radius:4px;padding:28px 32px 32px}
.inv-head{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:10px;gap:16px}
.inv-head-left h1{font-size:44px;font-weight:900;letter-spacing:-2px;line-height:1;color:#111}
.inv-head-left .inv-type{font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--theme);margin-top:2px}
.inv-logo{max-height:72px;max-width:180px;object-fit:contain}
.inv-logo-placeholder{font-size:20px;font-style:italic;color:#ccc;font-family:serif}

.inv-meta{border-top:2.5px solid #111;border-bottom:2.5px solid #111;padding:8px 0;display:flex;justify-content:space-between;font-size:13px;font-weight:700;margin-bottom:0}

.parties{display:grid;grid-template-columns:1fr 1fr;border-bottom:1px solid #ddd;margin-bottom:0}
.party{padding:12px 0;font-size:13px;line-height:1.7}
.party:first-child{padding-right:20px;border-right:1px solid #ddd}
.party:last-child{padding-left:20px}
.party-label{font-size:10px;font-weight:800;letter-spacing:.6px;color:#888;margin-bottom:5px}
.party-name{font-weight:700;font-size:14px}
.party-note{margin-top:8px;font-size:12px;color:#555}

/* Items table */
.items{width:100%;border-collapse:collapse;margin-bottom:0}
.items thead tr{background:var(--theme);color:#fff}
.items thead th{padding:9px 8px;font-size:10px;font-weight:800;letter-spacing:.5px;border:1px solid var(--theme)}
.items tbody td{border:1px solid #ddd;padding:7px 8px;font-size:12.5px}
.items tbody tr:nth-child(even){background:#f9f9f9}
.items tfoot td{border:1px solid #ddd;font-size:12px;padding:5px 8px}

.below-table{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-top:12px}
.methods{font-size:12px;line-height:2}
.totals-table{width:100%;border-collapse:collapse}
.totals-table td{padding:4px 6px;font-size:12px}
.totals-table .grand td{border-top:2px solid var(--theme);padding-top:7px;font-weight:800;font-size:14px;color:var(--theme)}

.in-words{border:1px solid #e0e0e0;border-radius:4px;padding:8px 12px;margin-top:12px;font-size:12px;color:#555;background:#fafafa}
.in-words strong{color:#222}

.sigs{display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin-top:28px;padding-top:12px}
.sig{text-align:center}
.sig-line{border-top:1.5px solid #111;padding-top:6px;font-size:12px;font-weight:700}

.footer-tagline{text-align:center;font-style:italic;color:var(--theme);font-size:12px;margin-top:14px;border-top:2px solid var(--theme);padding-top:8px}
.footer-meta{text-align:center;font-size:10px;color:#aaa;border-top:1.5px solid #ddd;padding-top:7px;margin-top:8px}

.status-badge{display:inline-block;padding:3px 10px;border-radius:20px;font-size:10px;font-weight:800;letter-spacing:.5px;text-transform:uppercase;margin-left:8px;vertical-align:middle}
.paid-badge{background:#d1fae5;color:#065f46}
.due-badge{background:#fee2e2;color:#991b1b}
.partial-badge{background:#fef3c7;color:#92400e}
.draft-badge{background:#f3f4f6;color:#374151}

/* ── Print ──────────────────────────────────────────────────────────────────── */
@media print{
    @page{size:A4;margin:14mm 14mm 20mm}
    body{background:#fff}
    .page-wrap{padding:0;max-width:none}
    .top-bar,.share-box{display:none!important}
    .invoice{box-shadow:none;border-radius:0;padding:0}
}
</style>
</head>
<body>
<?php
// ─── Data helpers ──────────────────────────────────────────────────────────────
$theme      = e($themeColor);
$sym        = e($invoice['currency_symbol'] ?? '৳');
$curCode    = $invoice['currency_code'] ?? 'BDT';
$invoiceNo  = $invoice['invoice_no'];
$isSale     = $invoice['type'] === 'sale';
$party      = $customer ?? $supplier ?? null;

$subtotal  = (float)$invoice['subtotal'];
$discount  = (float)$invoice['discount'];
$points    = (float)($invoice['points_discount']   ?? 0);
$privDisc  = (float)($invoice['privilege_discount'] ?? 0);
$couponD   = (float)($invoice['coupon_discount']    ?? 0);
$delivery  = (float)($invoice['delivery_charge'] ?? 0);
$rounding  = (float)($invoice['rounding'] ?? 0);
$tax       = (float)$invoice['tax'];
$total     = (float)$invoice['total'];
$paid      = (float)$invoice['paid'];
$due      = $total - $paid;

$statusLabel = match($invoice['status'] ?? 'draft') {
    'paid'      => ['Paid',     'paid-badge'],
    'partial'   => ['Partial',  'partial-badge'],
    'sent'      => ['Sent',     'partial-badge'],
    'cancelled' => ['Cancelled','draft-badge'],
    default     => ['Draft',    'draft-badge'],
};

// Logo
$logoHtml = '<span class="inv-logo-placeholder">'. e($bizName) .'</span>';
if (!empty($book['logo'] ?? '')) {
    $logoPath = (defined('BASE_PATH') ? config('upload.path') : '') . '/' . $book['logo'];
    if (!$isPublic && file_exists($logoPath)) {
        $logoHtml = '<img src="'.e($logoPath).'" class="inv-logo" alt="Logo">';
    } elseif ($isPublic) {
        // Serve logo via a data: URI or relative URL if possible
        $logoHtml = '<img src="/uploads/'.e($book['logo']).'" class="inv-logo" alt="Logo">';
    }
}

// QR / share URL
$shareUrl = (defined('BASE_PATH') ? (config('url') ?? '') : '') . '/invoice/' . ($invoice['public_token'] ?? '');

// Number to words

$inWords = invoiceNumToWords((int)round($total), $curCode) . ' Only';
?>

<div class="page-wrap">

<!-- ── Top bar (hidden on print) ─────────────────────────────────────────────── -->
<?php if (!($isPublic ?? false)): ?>
<div class="top-bar">
    <h3>Invoice Preview &amp; Print</h3>
    <a href="javascript:void(0)" class="tbtn tbtn-share" onclick="toggleShare()">
        <i class="fa-solid fa-link"></i> Share Link
    </a>
    <?php if (!empty($invoice['public_token'])): ?>
    <div class="share-box" id="shareBox">
        <input type="text" id="shareUrl" value="<?= e($shareUrl) ?>" readonly onclick="this.select()">
        <button class="tbtn tbtn-share" onclick="copyShareLink()"><i class="fa-solid fa-copy"></i> Copy</button>
        <span id="copyMsg" style="font-size:12px;color:#065f46;display:none">Copied!</span>
    </div>
    <?php endif; ?>
    <a href="/books/<?= $book['id'] ?>/invoices/<?= $invoice['id'] ?>/thermal?w=80" target="_blank" class="tbtn tbtn-ghost">
        <i class="fa-solid fa-print"></i> 58/80mm
    </a>
    <a href="/books/<?= $book['id'] ?>/invoices/<?= $invoice['id'] ?>" class="tbtn tbtn-ghost">
        <i class="fa-solid fa-left-right-long"></i> Back
    </a>
    <button onclick="window.print()" class="tbtn tbtn-primary">
        <i class="fa-solid fa-print"></i> Print / Save PDF
    </button>
</div>
<?php else: ?>
<div class="top-bar">
    <h3><?= e($bizName) ?> — Invoice <?= e($invoiceNo) ?></h3>
    <button onclick="window.print()" class="tbtn tbtn-primary">🖨 Print / Save PDF</button>
</div>
<?php endif; ?>

<!-- ── A4 invoice sheet ────────────────────────────────────────────────────────── -->
<div class="invoice">

    <!-- Header: Title + Logo -->
    <div class="inv-head">
        <div class="inv-head-left">
            <h1>Invoice</h1>
            <div class="inv-type"><?= $isSale ? 'Sales Invoice' : 'Purchase Invoice' ?></div>
            <?php if ($due <= 0 && $paid > 0): ?>
            <span class="status-badge paid-badge">✓ Paid</span>
            <?php elseif ($due > 0 && $paid > 0): ?>
            <span class="status-badge partial-badge">Partial</span>
            <?php elseif ($invoice['status'] === 'draft'): ?>
            <span class="status-badge draft-badge">Draft</span>
            <?php endif; ?>
        </div>
        <div><?= $logoHtml ?></div>
    </div>

    <!-- Invoice No + Date -->
    <div class="inv-meta">
        <span><b>Invoice No:</b> <?= e($invoiceNo) ?></span>
        <span>
            <b>Date:</b> <?= date('d / m / Y', strtotime($invoice['date'])) ?>
            <?php if ($invoice['due_date']): ?>
            &nbsp;&nbsp;<b>Due:</b> <?= date('d / m / Y', strtotime($invoice['due_date'])) ?>
            <?php endif; ?>
        </span>
    </div>

    <!-- Bill To / Bill From -->
    <div class="parties">
        <div class="party">
            <div class="party-label"><?= $isSale ? 'Bill To' : 'From Supplier' ?></div>
            <?php if ($party): ?>
            <div class="party-name"><?= e($party['name']) ?></div>
            <?php if (!empty($party['company'])): ?><div><?= e($party['company']) ?></div><?php endif; ?>
            <?php if (!empty($party['address'])): ?><div><?= e(str_replace("\n",', ',$party['address'])) ?></div><?php endif; ?>
            <?php if (!empty($party['phone'])): ?><div><?= e($party['phone']) ?></div><?php endif; ?>
            <?php if (!empty($party['email'])): ?><div><?= e($party['email']) ?></div><?php endif; ?>
            <?php else: ?><div class="party-name" style="color:#888">Walk-in Customer</div><?php endif; ?>
            <?php if (!empty($invoice['note_customer'])): ?>
            <div class="party-note"><b>Note:</b> <?= e($invoice['note_customer']) ?></div>
            <?php endif; ?>
        </div>
        <div class="party">
            <div class="party-label">Bill From</div>
            <div class="party-name"><?= e($bizName) ?></div>
            <?php if (!empty($bizAddress)): ?><div><?= e(str_replace("\n",', ',$bizAddress)) ?></div><?php endif; ?>
            <?php if (!empty($bizPhone)): ?><div><?= e($bizPhone) ?></div><?php endif; ?>
            <?php if (!empty($bizEmail)): ?><div><?= e($bizEmail) ?></div><?php endif; ?>
            <?php if (!empty($invoice['note_seller'])): ?>
            <div class="party-note"><b>Note:</b> <?= e($invoice['note_seller']) ?></div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Items -->
    <table class="items">
        <thead>
            <tr>
                <th style="width:28px;text-align:center">#</th>
                <th style="text-align:left">Description</th>
                <th style="width:90px;text-align:center">Variant</th>
                <th style="width:40px;text-align:center">Qty</th>
                <th style="width:80px;text-align:right">Unit Price</th>
                <?php if (array_sum(array_column($items,'discount_pct')) > 0): ?>
                <th style="width:50px;text-align:center">Disc%</th>
                <?php endif; ?>
                <th style="width:80px;text-align:right">Total</th>
            </tr>
        </thead>
        <tbody>
        <?php $hasDisc = array_sum(array_column($items,'discount_pct')) > 0; ?>
        <?php foreach ($items as $n => $item):
            $qty = rtrim(rtrim(number_format((float)$item['qty'],3,'.',''),'0'),'.');
        ?>
            <tr>
                <td style="text-align:center;color:#888"><?= $n+1 ?></td>
                <td>
                    <?= e($item['description']) ?>
                    <?php if (!empty($item['sku'])): ?>
                    <span style="font-size:10px;color:#aaa;margin-left:4px">[<?= e($item['sku']) ?>]</span>
                    <?php endif; ?>
                </td>
                <td style="text-align:center;font-size:11px;color:#555"><?= e($item['variant'] ?? '') ?></td>
                <td style="text-align:center"><?= $qty ?></td>
                <td style="text-align:right"><?= $sym.number_format((float)$item['unit_price'],0) ?></td>
                <?php if ($hasDisc): ?>
                <td style="text-align:center;color:#888"><?= (float)$item['discount_pct'] > 0 ? number_format((float)$item['discount_pct'],1).'%' : '—' ?></td>
                <?php endif; ?>
                <td style="text-align:right;font-weight:600"><?= $sym.number_format((float)$item['line_total'],0) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Below table: methods + totals -->
    <div class="below-table">
        <div class="methods">
            <?php if ($invoice['delivery_method']): ?>
            <div><b>Delivery:</b> <?= e($invoice['delivery_method']) ?>
                <?php if (($invoice['delivery_type'] ?? 'own') === 'other'): ?>
                <span style="font-size:10px;color:#888">(3rd party)</span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <?php if ($invoice['payment_method']): ?>
            <div><b>Payment:</b> <?= e($invoice['payment_method']) ?></div>
            <?php endif; ?>
            <?php if ($paid > 0): ?>
            <div style="margin-top:8px;font-size:12px"><b>Paid:</b> <span style="color:#065f46"><?= $sym.number_format($paid,0) ?></span></div>
            <?php if ($due > 0): ?>
            <div><b>Due:</b> <span style="color:#c00;font-weight:700"><?= $sym.number_format($due,0) ?></span></div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
        <div>
            <table class="totals-table">
                <tr><td>Subtotal</td><td style="text-align:right"><?= $sym.number_format($subtotal,0) ?></td></tr>
                <?php if ($discount > 0): ?>
                <tr><td>Discount (<?= round($subtotal>0?$discount/$subtotal*100:0) ?>%)</td>
                    <td style="text-align:right;color:#c00">-<?= $sym.number_format($discount,0) ?></td></tr>
                <?php endif; ?>
                <?php if ($points > 0): ?>
                <tr><td>Points</td><td style="text-align:right;color:#c00">-<?= $sym.number_format($points,0) ?></td></tr>
                <?php endif; ?>
                <?php if ($couponD > 0): ?>
                <tr><td>Coupon<?= $invoice['coupon_code'] ? ' ('.e($invoice['coupon_code']).')' : '' ?></td>
                    <td style="text-align:right;color:#c00">-<?= $sym.number_format($couponD,0) ?></td></tr>
                <?php endif; ?>
                <?php if ($privDisc > 0): ?>
                <tr><td>Privilege Discount</td>
                    <td style="text-align:right;color:#1a6b4a">-<?= $sym.number_format($privDisc,0) ?></td></tr>
                <?php endif; ?>
                <?php if ($delivery > 0): ?>
                <tr><td>Delivery</td><td style="text-align:right">+<?= $sym.number_format($delivery,0) ?></td></tr>
                <?php endif; ?>
                <?php if ($tax > 0): ?>
                <tr><td>Tax</td><td style="text-align:right">+<?= $sym.number_format($tax,0) ?></td></tr>
                <?php endif; ?>
                <?php if ($rounding > 0): ?>
                <tr><td>Rounding</td><td style="text-align:right;color:#888">-<?= $sym.number_format($rounding,2) ?></td></tr>
                <?php endif; ?>
                <tr class="grand">
                    <td><b>GRAND TOTAL</b></td>
                    <td style="text-align:right"><b><?= $sym.number_format($total,0) ?></b></td>
                </tr>
            </table>
        </div>
    </div>

    <!-- Amount in words -->
    <div class="in-words">
        <strong>In Words:</strong> <?= e($inWords) ?>
    </div>

    <!-- Signatures -->
    <div class="sigs">
        <div class="sig"><div class="sig-line">Received By</div></div>
        <div class="sig" style="text-align:center">
            <div style="margin-bottom:6px;font-size:11px;color:#aaa">Scan to verify</div>
            <img src="https://api.qrserver.com/v1/create-qr-code/?size=70x70&data=<?= urlencode($shareUrl) ?>"
                 style="width:70px;height:70px" alt="QR">
        </div>
        <div class="sig" style="text-align:right"><div class="sig-line">Authorised Signature</div></div>
    </div>

    <?php if (!empty($details['footer_note'])): ?>
    <div class="footer-tagline"><?= e($details['footer_note']) ?></div>
    <?php else: ?>
    <div class="footer-tagline">Thank you for your business!</div>
    <?php endif; ?>

    <div class="footer-meta">
        Generated by Byabsayee &nbsp;·&nbsp; <?= e($invoiceNo) ?> &nbsp;·&nbsp; <?= date('d M Y') ?>
    </div>

</div><!-- .invoice -->
</div><!-- .page-wrap -->

<script>
function toggleShare() {
    const box = document.getElementById('shareBox');
    if (!box) return;
    box.style.display = box.style.display === 'flex' ? 'none' : 'flex';
}
function copyShareLink() {
    const input = document.getElementById('shareUrl');
    if (!input) return;
    input.select();
    navigator.clipboard.writeText(input.value).then(() => {
        const msg = document.getElementById('copyMsg');
        if (msg) { msg.style.display='inline'; setTimeout(()=>msg.style.display='none', 2000); }
    }).catch(() => { document.execCommand('copy'); });
}
<?php if (!empty($_GET['autoprint'])): ?>
setTimeout(() => window.print(), 600);
<?php endif; ?>
</script>
</body>
</html>
