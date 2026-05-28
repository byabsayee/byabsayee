<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Barcodes — <?= e($book['name']) ?></title>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jsbarcode/3.11.6/JsBarcode.all.min.js"></script>
<style>
<?php $isNarrow = $paperWidth === 58; ?>
@page { size: <?= $paperWidth ?>mm auto; margin: 4mm; }
* { box-sizing:border-box; margin:0; padding:0; }
body {
    font-family: Arial, sans-serif;
    font-size: 10px;
    background: #f5f5f5;
    padding: 16px;
}
.controls {
    background:#fff;
    border-radius:8px;
    padding:16px;
    margin-bottom:16px;
    display:flex;
    gap:10px;
    flex-wrap:wrap;
    align-items:center;
    box-shadow:0 2px 8px rgba(0,0,0,.08);
}
.barcode-grid {
    display:flex;
    flex-wrap:wrap;
    gap:8px;
}
.barcode-label {
    background:#fff;
    border:1px solid #ddd;
    border-radius:4px;
    padding:6px 8px;
    text-align:center;
    width: <?= $isNarrow ? '150px' : '200px' ?>;
    page-break-inside:avoid;
}
.barcode-label svg { max-width:100%; height:auto; }
.product-name {
    font-size:<?= $isNarrow ? '9px' : '10px' ?>;
    font-weight:bold;
    margin-bottom:3px;
    overflow:hidden;
    text-overflow:ellipsis;
    white-space:nowrap;
}
.barcode-code {
    font-size: 8px;
    color: #555;
    margin-top:2px;
}
.price-tag {
    font-size:<?= $isNarrow ? '10px' : '12px' ?>;
    font-weight:bold;
    color:#1a6b4a;
    margin-top:2px;
}
.batch-badge {
    font-size:8px;
    background:#e3f2fd;
    color:#1565c0;
    border-radius:3px;
    padding:1px 4px;
    display:inline-block;
    margin-bottom:3px;
}
@media print {
    .controls { display:none !important; }
    body { background:#fff; padding:0; }
    .barcode-grid { gap:4px; }
    .barcode-label { border:1px solid #999; }
}
</style>
</head>
<body>

<div class="controls">
    <strong style="font-size:14px">🏷️ Barcode Labels</strong>

    <label style="font-size:13px">
        Copies per label:
        <input type="number" id="copiesInput" value="1" min="1" max="50" style="width:60px;padding:4px 6px;border:1px solid #ddd;border-radius:4px;font-size:13px">
    </label>

    <div style="display:flex;gap:6px">
        <a href="?w=58<?= !empty($_GET['product_id']) ? '&product_id='.(int)$_GET['product_id'] : '' ?>"
           style="padding:6px 12px;background:<?= $paperWidth===58?'#1a6b4a':'#f0f0f0' ?>;color:<?= $paperWidth===58?'#fff':'#333' ?>;border-radius:6px;font-size:12px;text-decoration:none">
            58mm
        </a>
        <a href="?w=80<?= !empty($_GET['product_id']) ? '&product_id='.(int)$_GET['product_id'] : '' ?>"
           style="padding:6px 12px;background:<?= $paperWidth===80?'#1a6b4a':'#f0f0f0' ?>;color:<?= $paperWidth===80?'#fff':'#333' ?>;border-radius:6px;font-size:12px;text-decoration:none">
            80mm
        </a>
    </div>

    <label style="display:flex;align-items:center;gap:6px;font-size:12px;cursor:pointer">
        <input type="checkbox" id="showBatches" onchange="render()" style="accent-color:#1a6b4a">
        Show batch barcodes
    </label>

    <label style="display:flex;align-items:center;gap:6px;font-size:12px;cursor:pointer">
        <input type="checkbox" id="showPrice" checked onchange="render()" style="accent-color:#1a6b4a">
        Show price
    </label>

    <button onclick="window.print()" style="padding:7px 16px;background:#1a6b4a;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:13px">
        🖨️ Print
    </button>

    <a href="/books/<?= $book['id'] ?>/products" style="padding:7px 16px;background:#f0f0f0;color:#333;border-radius:6px;font-size:13px;text-decoration:none">
        ← Products
    </a>
</div>

<div class="barcode-grid" id="barcodeGrid"></div>

<script>
const SYM = '<?= e($details['currency_symbol'] ?? '৳') ?>';
const PRODUCTS = <?= json_encode(
    array_map(fn($p) => [
        'id'       => $p['id'],
        'name'     => $p['name'],
        'code'     => $p['product_code'] ?? $p['sku'] ?? '',
        'barcode'  => $p['barcode'] ?? '',
        'price'    => (float)($p['sell_price'] ?? 0),
        'batches'  => array_map(fn($b) => [
            'barcode'    => $b['barcode'],
            'buy_price'  => (float)$b['buy_price'],
            'remaining'  => (float)$b['remaining_qty'],
        ], $batchesByProduct[$p['id']] ?? [])
    ], $products),
    JSON_UNESCAPED_UNICODE
) ?>;

function render() {
    const grid       = document.getElementById('barcodeGrid');
    const copies     = parseInt(document.getElementById('copiesInput').value) || 1;
    const showBatch  = document.getElementById('showBatches').checked;
    const showPrice  = document.getElementById('showPrice').checked;
    let   html       = '';

    PRODUCTS.forEach(p => {
        if (!p.barcode) return;

        // Main product barcode
        for (let c = 0; c < copies; c++) {
            html += makeLabelHTML(p.name, p.barcode, p.code, showPrice ? p.price : null, null);
        }

        // Batch barcodes
        if (showBatch && p.batches.length) {
            p.batches.forEach((b, bi) => {
                if (!b.barcode || b.barcode === p.barcode) return; // skip if same as product barcode
                for (let c = 0; c < copies; c++) {
                    html += makeLabelHTML(p.name, b.barcode, p.code, showPrice ? b.buy_price : null, `Batch ${bi+1} (${b.remaining} left)`);
                }
            });
        }
    });

    grid.innerHTML = html || '<p style="color:#999;font-size:13px">No products with barcodes found.</p>';

    // Render barcodes with JsBarcode
    grid.querySelectorAll('svg[data-barcode]').forEach(svg => {
        const code = svg.getAttribute('data-barcode');
        try {
            JsBarcode(svg, code, {
                format: 'CODE128',
                width:  1.5,
                height: 40,
                displayValue: true,
                fontSize: 10,
                margin: 2,
                background: '#ffffff',
                lineColor: '#000000',
            });
        } catch(e) {
            svg.outerHTML = `<div style="font-size:9px;color:#c00">Invalid: ${code}</div>`;
        }
    });
}

function makeLabelHTML(name, barcode, code, price, batchLabel) {
    const safeName    = name.length > 22 ? name.slice(0,20)+'..' : name;
    const batchBadge  = batchLabel ? `<div class="batch-badge">${batchLabel}</div>` : '';
    const priceTag    = price !== null ? `<div class="price-tag">${SYM}${Math.round(price).toLocaleString()}</div>` : '';
    return `
        <div class="barcode-label">
            <div class="product-name">${esc(safeName)}</div>
            ${batchBadge}
            <svg data-barcode="${esc(barcode)}"></svg>
            <div class="barcode-code">${esc(code)}</div>
            ${priceTag}
        </div>`;
}

function esc(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// Re-render on copies change
document.getElementById('copiesInput').addEventListener('change', render);
render();
</script>
</body>
</html>
