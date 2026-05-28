<?php
$pageTitle = 'New Invoice — ' . e($book['name']);
$isSale    = ($type !== 'purchase');
$sym       = $defaultCurrency['symbol'] ?? '৳';
ob_start();
?>

<div class="page-header">
    <div class="page-header-left">
        <div class="breadcrumb">
            <a href="/books/<?= $book['id'] ?>">Dashboard</a> <span>›</span>
            <a href="/books/<?= $book['id'] ?>/invoices">Invoices</a> <span>›</span>
            <span>New <?= $isSale ? 'Sale' : 'Purchase' ?></span>
        </div>
        <h1>New <?= $isSale ? 'Sale Invoice' : 'Purchase Invoice' ?></h1>
    </div>
</div>

<form method="POST" action="/books/<?= $book['id'] ?>/invoices/create"
      id="invoiceForm" enctype="multipart/form-data">
<input type="hidden" name="_csrf"           value="<?= csrf_token() ?>">
<input type="hidden" name="type"            value="<?= e($type) ?>">
<input type="hidden" name="currency_symbol" id="currencySymbol" value="<?= e($sym) ?>">
<input type="hidden" name="currency_code"   id="currencyCode"   value="<?= e($defaultCurrency['code']??'BDT') ?>">

<div style="display:grid;grid-template-columns:1fr 300px;gap:20px;align-items:start">

<!-- ══ LEFT ══ -->
<div style="display:flex;flex-direction:column;gap:16px">

    <!-- Header fields -->
    <div class="card">
        <div class="form-grid">
            <div class="form-group">
                <label>Invoice Number *</label>
                <input type="text" name="invoice_no" value="<?= e($invoiceNo) ?>" required>
            </div>
            <div class="form-group">
                <label>Currency</label>
                <select id="currencySelect" onchange="setCurrency(this)">
                    <?php foreach ($currencies as $c): ?>
                    <option value="<?= e($c['symbol']) ?>" data-code="<?= e($c['code']) ?>"
                            <?= $c['is_default'] ? 'selected' : '' ?>>
                        <?= e($c['code']) ?> (<?= e($c['symbol']) ?>)
                    </option>
                    <?php endforeach; ?>
                    <?php if (empty($currencies)): ?>
                    <option value="৳" data-code="BDT" selected>BDT (৳)</option>
                    <?php endif; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Date *</label>
                <input type="date" name="date" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="form-group">
                <label>Due Date</label>
                <input type="date" name="due_date">
            </div>

            <?php if ($isSale): ?>
            <div class="form-group full">
                <label>Customer</label>
                <!-- Search-enabled customer select -->
                <input type="text" id="customerSearch"
                       placeholder="Search by name or phone…"
                       oninput="searchParty(this.value,'customer')"
                       autocomplete="off"
                       style="width:100%;padding:8px 12px;border:1.5px solid var(--border);border-radius:8px;font-size:13px;font-family:inherit;outline:none;margin-bottom:6px">
                <div id="customerResults" style="display:none;border:1px solid var(--border);border-radius:8px;overflow:hidden;max-height:160px;overflow-y:auto;background:#fff;margin-bottom:6px"></div>
                <select name="customer_id" id="customerSel" onchange="customerSelected(this)"
                        style="width:100%;padding:8px 10px;border:1.5px solid var(--border);border-radius:8px;font-size:13px;font-family:inherit;outline:none">
                    <option value="">— Walk-in Customer —</option>
                    <?php foreach ($customers as $c): ?>
                    <option value="<?= $c['id'] ?>" data-points="<?= $c['points'] ?>"
                            <?= ($_GET['customer_id']??'')==$c['id']?'selected':'' ?>>
                        <?= e($c['name']) ?><?= $c['phone'] ? ' — '.$c['phone'] : '' ?> (<?= $c['points'] ?> pts)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Delivery Method</label>
                <select name="delivery_method">
                    <option value="">— None —</option>
                    <?php foreach ($deliveryMethods as $m): ?>
                    <option value="<?= e($m['label']) ?>"><?= e($m['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Payment Method</label>
                <select name="payment_method">
                    <option value="">— None —</option>
                    <?php foreach ($paymentMethods as $m): ?>
                    <option value="<?= e($m['label']) ?>"><?= e($m['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php else: ?>
            <div class="form-group full">
                <label>Supplier</label>
                <input type="text" id="supplierSearch"
                       placeholder="Search by name or company…"
                       oninput="searchParty(this.value,'supplier')"
                       autocomplete="off"
                       style="width:100%;padding:8px 12px;border:1.5px solid var(--border);border-radius:8px;font-size:13px;font-family:inherit;outline:none;margin-bottom:6px">
                <div id="supplierResults" style="display:none;border:1px solid var(--border);border-radius:8px;overflow:hidden;max-height:160px;overflow-y:auto;background:#fff;margin-bottom:6px"></div>
                <select name="supplier_id" id="supplierSel"
                        style="width:100%;padding:8px 10px;border:1.5px solid var(--border);border-radius:8px;font-size:13px;font-family:inherit;outline:none">
                    <option value="">— Select Supplier —</option>
                    <?php foreach ($suppliers as $s): ?>
                    <option value="<?= $s['id'] ?>"
                            <?= ($_GET['supplier_id']??'')==$s['id']?'selected':'' ?>>
                        <?= e($s['name']) ?><?= $s['company'] ? ' ('.$s['company'].')' : '' ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Product search bar -->
    <div class="card" style="padding:12px">
        <input type="text" id="productSearch"
               placeholder="Scan barcode or type product code / name to add…"
               oninput="searchProduct(this.value)"
               autocomplete="off"
               style="width:100%;padding:9px 12px;border:1.5px solid var(--brand);border-radius:8px;font-size:13px;font-family:inherit;outline:none">
        <div id="productResults" style="display:none;margin-top:6px;border:1px solid var(--border);border-radius:8px;overflow:hidden;max-height:200px;overflow-y:auto;background:#fff;box-shadow:0 4px 12px rgba(0,0,0,.07)"></div>
    </div>

    <!-- Line items -->
    <div class="card">
        <p class="card-title">Items</p>
        <div style="overflow-x:auto">
        <table style="width:100%;border-collapse:collapse" id="itemsTable">
            <thead>
                <tr>
                    <th style="padding:6px;font-size:10px;font-weight:700;text-transform:uppercase;color:var(--text-muted);border-bottom:1px solid var(--border);text-align:left">Product</th>
                    <th style="padding:6px;font-size:10px;font-weight:700;text-transform:uppercase;color:var(--text-muted);border-bottom:1px solid var(--border);width:90px">Variant</th>
                    <th style="padding:6px;font-size:10px;font-weight:700;text-transform:uppercase;color:var(--text-muted);border-bottom:1px solid var(--border);width:60px;text-align:right">Qty</th>
                    <th style="padding:6px;font-size:10px;font-weight:700;text-transform:uppercase;color:var(--text-muted);border-bottom:1px solid var(--border);width:90px;text-align:right">Price</th>
                    <th style="padding:6px;font-size:10px;font-weight:700;text-transform:uppercase;color:var(--text-muted);border-bottom:1px solid var(--border);width:90px;text-align:right">Total</th>
                    <th style="border-bottom:1px solid var(--border);width:26px"></th>
                </tr>
            </thead>
            <tbody id="itemsBody"></tbody>
        </table>
        </div>
        <button type="button" onclick="addRow()" class="btn btn-sm btn-secondary" style="margin-top:10px">+ Add Item</button>
    </div>

    <!-- Notes -->
    <div class="card">
        <div class="form-grid">
            <div class="form-group">
                <label>Customer Note</label>
                <textarea name="note_customer" placeholder="e.g. Thank you!" style="min-height:60px"></textarea>
            </div>
            <div class="form-group">
                <label>Seller Note</label>
                <textarea name="note_seller" placeholder="e.g. No refund after 7 days" style="min-height:60px"></textarea>
            </div>
        </div>
    </div>

    <?php if (!$isSale): ?>
    <!-- Attachment — purchase invoices only -->
    <div class="card">
        <p class="card-title">Supplier Invoice Attachment</p>
        <p style="font-size:12px;color:var(--text-muted);margin-bottom:10px">Attach the invoice you received from your supplier (PDF or image)</p>
        <input type="file" name="attachment" accept=".pdf,.jpg,.jpeg,.png,.webp">
    </div>
    <?php endif; ?>

</div><!-- end LEFT -->

<!-- ══ RIGHT: summary ══ -->
<div style="position:sticky;top:20px;display:flex;flex-direction:column;gap:12px">
    <div class="card">
        <p class="card-title">Summary</p>
        <div style="display:flex;flex-direction:column;gap:9px;font-size:14px">
            <div style="display:flex;justify-content:space-between">
                <span style="color:var(--text-muted)">Subtotal</span>
                <strong id="summarySubtotal">0.00</strong>
            </div>
            <div style="display:flex;justify-content:space-between;align-items:center">
                <label style="color:var(--text-muted)">Discount</label>
                <input type="number" name="discount" id="inp_discount" value="0" min="0" step="0.01" oninput="recalc()" class="summary-input">
            </div>
            <?php if ($isSale): ?>
            <!-- Privilege Discount (read-only, auto-applied per customer) -->
            <div id="privilege_row" style="display:none;justify-content:space-between;align-items:center">
                <div>
                    <label style="color:var(--text-muted)">Privilege</label>
                    <div id="privilege_label" style="font-size:11px;color:var(--green);font-weight:600"></div>
                </div>
                <strong id="privilege_discount_display" style="color:var(--green)">-0.00</strong>
            </div>
            <input type="hidden" name="privilege_discount" id="inp_privilege_discount" value="0">
            <!-- Coupon Code -->
            <div>
                <div style="display:flex;justify-content:space-between;align-items:center;gap:6px">
                    <label style="color:var(--text-muted);white-space:nowrap">Coupon Code</label>
                    <div style="display:flex;gap:4px;align-items:center">
                        <input type="text" id="couponCodeInput" name="coupon_code"
                               placeholder="Enter code…"
                               style="width:100px;padding:5px 8px;border:1.5px solid var(--border);border-radius:7px;font-size:12px;font-family:inherit;text-align:right;outline:none;text-transform:uppercase"
                               oninput="this.value=this.value.toUpperCase()">
                        <button type="button" onclick="applyCoupon()" class="btn btn-sm btn-secondary" style="padding:5px 8px;font-size:12px">Apply</button>
                    </div>
                </div>
                <div id="couponStatus" style="font-size:11px;margin-top:4px;text-align:right;min-height:15px"></div>
                <input type="hidden" name="coupon_discount" id="inp_coupon_discount" value="0">
                <div id="coupon_discount_row" style="display:none;justify-content:space-between;margin-top:2px">
                    <span style="color:var(--text-muted);font-size:13px">Coupon Discount</span>
                    <strong id="couponDiscountDisplay" style="font-size:13px;color:var(--green)">-0.00</strong>
                </div>
            </div>
            <div style="display:flex;justify-content:space-between;align-items:center">
                <div>
                    <label style="color:var(--text-muted)">Use Points</label>
                    <label style="display:inline-flex;align-items:center;gap:4px;cursor:pointer;margin-left:8px">
                        <input type="checkbox" id="usePointsToggle" onchange="togglePoints()" style="accent-color:var(--brand)">
                        <span id="availablePoints" style="font-size:11px;color:var(--text-muted)"></span>
                    </label>
                </div>
                <input type="number" name="points_discount" id="inp_points" value="0" min="0" step="1" oninput="recalc()" class="summary-input" disabled>
            </div>
            <div style="display:flex;justify-content:space-between;align-items:center">
                <label style="color:var(--text-muted)">Delivery Charge</label>
                <input type="number" name="delivery_charge" id="inp_delivery" value="0" min="0" step="0.01" oninput="recalc()" class="summary-input">
            </div>
            <div style="display:flex;justify-content:space-between;align-items:center">
                <label style="color:var(--text-muted)">Handling Charge</label>
                <input type="number" name="handling_charge" id="inp_handling" value="0" min="0" step="0.01" oninput="recalc()" class="summary-input">
            </div>
            <div style="padding:8px 10px;background:var(--bg);border-radius:8px;border:1px solid var(--border)">
                <div style="font-size:11px;font-weight:700;color:var(--text-muted);margin-bottom:6px">DELIVERY TYPE</div>
                <div style="display:flex;gap:12px">
                    <label style="display:flex;align-items:center;gap:5px;cursor:pointer;font-size:12px">
                        <input type="radio" name="delivery_type" value="own" checked style="accent-color:var(--brand)">
                        <span><strong>Own</strong> <span style="color:var(--text-muted)">(income)</span></span>
                    </label>
                    <label style="display:flex;align-items:center;gap:5px;cursor:pointer;font-size:12px">
                        <input type="radio" name="delivery_type" value="other" style="accent-color:var(--brand)">
                        <span><strong>3rd Party</strong> <span style="color:var(--text-muted)">(→ expense)</span></span>
                    </label>
                </div>
            </div>
            <?php endif; ?>
            <div style="display:flex;justify-content:space-between;align-items:center">
                <label style="color:var(--text-muted)">Tax</label>
                <input type="number" name="tax" id="inp_tax" value="0" min="0" step="0.01" oninput="recalc()" class="summary-input">
            </div>
            <div style="display:flex;justify-content:space-between;align-items:center">
                <div>
                    <label style="color:var(--text-muted)">Round Down</label>
                    <div style="font-size:10px;color:var(--text-muted)">removes cents from total</div>
                </div>
                <label style="display:inline-flex;align-items:center;gap:6px;cursor:pointer">
                    <input type="checkbox" name="rounding_enabled" id="chk_rounding" onchange="recalc()" style="accent-color:var(--brand);width:16px;height:16px">
                    <span id="roundingDisplay" style="font-size:12px;color:var(--text-muted)">off</span>
                </label>
            </div>
            <div style="border-top:2px solid var(--border);padding-top:10px;display:flex;justify-content:space-between">
                <strong style="font-size:16px">Grand Total</strong>
                <strong id="summaryTotal" style="font-size:18px;color:var(--brand)">0.00</strong>
            </div>
        </div>
    </div>
    <button type="submit" class="btn btn-primary" style="width:100%;height:46px;font-size:15px">Save Invoice</button>
    <a href="/books/<?= $book['id'] ?>/invoices" class="btn btn-secondary" style="width:100%;text-align:center">Cancel</a>
</div>

</div>
</form>

<style>
.summary-input{width:95px;padding:5px 8px;border:1.5px solid var(--border);border-radius:7px;font-size:13px;font-family:inherit;text-align:right;outline:none}
.summary-input:disabled{background:var(--bg);color:var(--text-muted)}
.summary-input:focus{border-color:var(--brand)}
</style>

<script>
// ── Data ──────────────────────────────────────────────────────────────────────
const PRODUCTS = <?= json_encode(array_map(function($p) use ($isSale, $inventoryMethod) {
    $batches = $p['batches'] ?? [];
    // For FIFO pick oldest batch, LIFO pick newest — only batches with remaining stock
    $activeBatches = array_filter($batches, fn($b) => (float)$b['remaining_qty'] > 0);
    $activeBatches = array_values($activeBatches); // re-index
    if ($inventoryMethod === 'LIFO') {
        $activeBatches = array_reverse($activeBatches);
    }
    $batchSellPrice = null;
    if (!empty($activeBatches[0]['sell_price']) && (float)$activeBatches[0]['sell_price'] > 0) {
        $batchSellPrice = (float)$activeBatches[0]['sell_price'];
    }
    return [
        'id'       => $p['id'],
        'name'     => $p['name'],
        'code'     => $p['product_code'] ?? '',
        'price'    => $isSale
                        ? ($batchSellPrice ?? (float)$p['sell_price'])
                        : (float)$p['buy_price'],
        'unit'     => $p['unit'],
        'stock'    => (float)$p['stock_qty'],
        'variants' => \App\Helpers\Database::query('SELECT * FROM product_variants WHERE product_id=?', [$p['id']]),
    ];
}, $products), JSON_UNESCAPED_UNICODE) ?>;

const CUSTOMERS = <?= json_encode(array_map(function($c) {
    // Parse GROUP_CONCAT'd privilege columns
    $privIds    = !empty($c['privilege_ids'])    ? explode(',',   $c['privilege_ids'])    : [];
    $privNames  = !empty($c['privilege_names'])  ? explode('||',  $c['privilege_names'])  : [];
    $discTypes  = !empty($c['discount_types'])   ? explode(',',   $c['discount_types'])   : [];
    $discValues = !empty($c['discount_values'])  ? explode(',',   $c['discount_values'])  : [];
    $privileges = [];
    foreach ($privIds as $i => $pid) {
        $privileges[] = [
            'id'             => $pid,
            'name'           => $privNames[$i]  ?? '',
            'discount_type'  => $discTypes[$i]  ?? 'percent',
            'discount_value' => (float)($discValues[$i] ?? 0),
        ];
    }
    return [
        'id'         => $c['id'],
        'name'       => $c['name'],
        'phone'      => $c['phone'] ?? '',
        'points'     => $c['points'],
        'privileges' => $privileges,
    ];
}, $customers), JSON_UNESCAPED_UNICODE) ?>;
const SUPPLIERS = <?= json_encode(array_map(fn($s) => ['id'=>$s['id'],'name'=>$s['name'],'company'=>$s['company']??''], $suppliers), JSON_UNESCAPED_UNICODE) ?>;

let rowCount    = 0;
let currentSym  = '<?= e($sym) ?>';
let customerPts = 0;
window._customerPrivileges = [];

function esc(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

// ── Currency ──────────────────────────────────────────────────────────────────
function setCurrency(sel) {
    currentSym = sel.value;
    document.getElementById('currencySymbol').value = currentSym;
    document.getElementById('currencyCode').value   = sel.options[sel.selectedIndex].dataset.code;
    recalc();
}

// ── Party search (customer / supplier) ───────────────────────────────────────
function searchParty(q, type) {
    const resultsId = type === 'customer' ? 'customerResults' : 'supplierResults';
    const selectId  = type === 'customer' ? 'customerSel'     : 'supplierSel';
    const list      = type === 'customer' ? CUSTOMERS : SUPPLIERS;
    const box       = document.getElementById(resultsId);
    q = q.trim().toLowerCase();
    if (!q) { box.style.display='none'; return; }

    const matches = list.filter(p =>
        p.name.toLowerCase().includes(q) ||
        (p.phone && p.phone.includes(q)) ||
        (p.company && p.company.toLowerCase().includes(q))
    ).slice(0,8);

    if (!matches.length) { box.style.display='none'; return; }

    box.innerHTML = matches.map(p => `
        <div onclick="selectParty(${p.id},'${type}')"
             style="padding:9px 12px;cursor:pointer;border-bottom:1px solid var(--border);font-size:13px"
             onmouseover="this.style.background='var(--bg)'" onmouseout="this.style.background=''">
            <strong>${esc(p.name)}</strong>
            ${p.phone ? '<span style="color:var(--text-muted);margin-left:8px;font-size:12px">'+esc(p.phone)+'</span>' : ''}
            ${p.company ? '<span style="color:var(--text-muted);margin-left:8px;font-size:12px">'+esc(p.company)+'</span>' : ''}
        </div>`
    ).join('');
    box.style.display = 'block';
}

function selectParty(id, type) {
    const selectId  = type === 'customer' ? 'customerSel'     : 'supplierSel';
    const searchId  = type === 'customer' ? 'customerSearch'   : 'supplierSearch';
    const resultsId = type === 'customer' ? 'customerResults' : 'supplierResults';
    const sel = document.getElementById(selectId);
    sel.value = id;
    if (type === 'customer') customerSelected(sel);
    document.getElementById(searchId).value = '';
    document.getElementById(resultsId).style.display = 'none';
}

document.addEventListener('click', e => {
    ['customerResults','supplierResults','productResults'].forEach(id => {
        const box = document.getElementById(id);
        if (box && !e.target.closest('#'+id) && !e.target.closest('#'+(id.replace('Results','Search').replace('product','product'))))
            box.style.display = 'none';
    });
});

// ── Customer points + privileges (multiple) ───────────────────────────────────
function customerSelected(sel) {
    const id  = parseInt(sel.value);
    const cus = CUSTOMERS.find(c => c.id === id);
    customerPts = cus ? cus.points : 0;
    const el = document.getElementById('availablePoints');
    if (el) el.textContent = customerPts > 0 ? `(${customerPts} pts)` : '';

    const privRow  = document.getElementById('privilege_row');
    const privLabel= document.getElementById('privilege_label');
    if (cus && cus.privileges && cus.privileges.length > 0) {
        if (privRow) privRow.style.display = 'flex';
        if (privLabel) privLabel.textContent = cus.privileges.map(p =>
            p.name + (p.discount_type === 'percent'
                ? ` (${p.discount_value}% off)`
                : ` (-${currentSym}${p.discount_value})`)
        ).join(' + ');
        window._customerPrivileges = cus.privileges;
    } else {
        if (privRow) privRow.style.display = 'none';
        const privDisc = document.getElementById('inp_privilege_discount');
        if (privDisc) privDisc.value = '0';
        window._customerPrivileges = [];
    }
    recalc();
}

function togglePoints() {
    const cb  = document.getElementById('usePointsToggle');
    const inp = document.getElementById('inp_points');
    if (!inp) return;
    inp.disabled = !cb.checked;
    inp.value    = cb.checked ? customerPts : 0;
    recalc();
}

// ── Product search ────────────────────────────────────────────────────────────
function searchProduct(q) {
    const box = document.getElementById('productResults');
    q = q.trim().toLowerCase();
    if (!q) { box.style.display='none'; return; }

    const matches = PRODUCTS.filter(p =>
        p.name.toLowerCase().includes(q) ||
        (p.code && p.code.toLowerCase().includes(q))
    ).slice(0,8);

    if (!matches.length) { box.style.display='none'; return; }

    box.innerHTML = matches.map(p => `
        <div onclick="addProductRow(${p.id})"
             style="padding:9px 12px;cursor:pointer;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;font-size:13px"
             onmouseover="this.style.background='var(--bg)'" onmouseout="this.style.background=''">
            <span><strong>${esc(p.name)}</strong> <span style="font-size:11px;color:var(--text-muted)">[${esc(p.code)}]</span></span>
            <strong style="color:var(--brand)">${currentSym}${p.price.toFixed(0)}</strong>
        </div>`
    ).join('');
    box.style.display = 'block';
}

function addProductRow(pid) {
    const p = PRODUCTS.find(x => x.id === pid);
    if (!p) return;
    addRow(p.name, 1, p.price, 0, pid, '', p.variants || []);
    document.getElementById('productSearch').value = '';
    document.getElementById('productResults').style.display = 'none';
    document.getElementById('productSearch').focus();
}

// ── Add item row ──────────────────────────────────────────────────────────────
function addRow(name='', qty=1, price=0, disc=0, pid='', variant='', variants=[]) {
    const i     = rowCount++;
    const tbody = document.getElementById('itemsBody');
    const tr    = document.createElement('tr');
    tr.id = 'row_'+i;

    // Build variant options
    let varOpts = '<option value="">—</option>';
    if (variants.length) {
        // Group by label
        const groups = {};
        variants.forEach(v => {
            if (!groups[v.label]) groups[v.label] = [];
            groups[v.label].push(v.value);
        });
        Object.entries(groups).forEach(([label, vals]) => {
            varOpts += `<optgroup label="${esc(label)}">`;
            vals.forEach(v => { varOpts += `<option value="${esc(label+': '+v)}" ${variant===label+': '+v?'selected':''}>${esc(v)}</option>`; });
            varOpts += '</optgroup>';
        });
    }

    tr.innerHTML = `
        <td style="padding:5px 4px">
            <div style="font-weight:500;font-size:13px">${esc(name) || '<span style="color:var(--text-muted)">Custom item</span>'}</div>
            <input type="text" name="item_name[]" id="iname_${i}" value="${esc(name)}"
                   placeholder="Item description…" required oninput="recalc()"
                   style="margin-top:3px;width:100%;padding:5px 6px;border:1.5px solid var(--border);border-radius:7px;font-size:12px;font-family:inherit;outline:none">
            <input type="hidden" name="item_product_id[]" value="${pid}">
        </td>
        <td style="padding:5px 4px">
            ${variants.length ? `
            <select name="item_variant[]"
                    style="width:100%;padding:5px 6px;border:1.5px solid var(--border);border-radius:7px;font-size:12px;font-family:inherit;outline:none">
                ${varOpts}
            </select>` : `
            <input type="text" name="item_variant[]" value="${esc(variant)}" placeholder="—"
                   style="width:100%;padding:5px 6px;border:1.5px solid var(--border);border-radius:7px;font-size:12px;font-family:inherit;outline:none">`}
        </td>
        <td style="padding:5px 4px">
            <input type="number" name="item_qty[]" id="iqty_${i}" value="${qty}"
                   min="0.001" step="0.001" oninput="recalc()"
                   style="width:100%;padding:5px 6px;border:1.5px solid var(--border);border-radius:7px;font-size:12px;text-align:right;font-family:inherit;outline:none">
        </td>
        <td style="padding:5px 4px">
            <input type="number" name="item_price[]" id="iprice_${i}" value="${price}"
                   min="0" step="0.01" oninput="recalc()"
                   style="width:100%;padding:5px 6px;border:1.5px solid var(--border);border-radius:7px;font-size:12px;text-align:right;font-family:inherit;outline:none">
        </td>
        <td style="padding:5px 4px;text-align:right;font-weight:600;font-size:12px" id="iline_${i}">0.00</td>
        <td style="padding:5px 4px;text-align:center">
            <button type="button" onclick="removeRow(${i})"
                    style="background:none;border:none;color:var(--red);cursor:pointer;font-size:20px;line-height:1">×</button>
        </td>`;
    tbody.appendChild(tr);
    recalc();
}

function removeRow(i) {
    const r = document.getElementById('row_'+i);
    if (r) r.remove();
    recalc();
}

function recalc() {
    let subtotal = 0;
    for (let i = 0; i < rowCount; i++) {
        const qEl = document.getElementById('iqty_'+i);
        const pEl = document.getElementById('iprice_'+i);
        const dEl = document.getElementById('idisc_'+i);  // may not exist
        const lEl = document.getElementById('iline_'+i);
        if (!qEl) continue;
        const discPct = dEl ? (parseFloat(dEl.value)||0) : 0;
        const line = (parseFloat(qEl.value)||0)*(parseFloat(pEl.value)||0)*(1-discPct/100);
        if (lEl) lEl.textContent = currentSym+line.toFixed(0);
        subtotal += line;
    }

    // Privilege discounts — sum ALL privileges the customer has
    let privilegeAmt = 0;
    const privs = window._customerPrivileges || [];
    if (privs.length > 0) {
        for (const priv of privs) {
            if (priv.discount_type === 'percent') {
                privilegeAmt += subtotal * priv.discount_value / 100;
            } else {
                privilegeAmt += priv.discount_value;
            }
        }
        privilegeAmt = Math.min(privilegeAmt, subtotal);
        const privDisc = document.getElementById('inp_privilege_discount');
        const privDisp = document.getElementById('privilege_discount_display');
        if (privDisc) privDisc.value = privilegeAmt.toFixed(2);
        if (privDisp) privDisp.textContent = '-' + currentSym + privilegeAmt.toFixed(0);
    }

    const disc     = parseFloat(document.getElementById('inp_discount')?.value)||0;
    const points   = parseFloat(document.getElementById('inp_points')?.value)||0;
    const couponD  = parseFloat(document.getElementById('inp_coupon_discount')?.value)||0;
    const delivery = parseFloat(document.getElementById('inp_delivery')?.value)||0;
    const handling = parseFloat(document.getElementById('inp_handling')?.value)||0;
    const tax      = parseFloat(document.getElementById('inp_tax')?.value)||0;
    const base     = subtotal - disc - points - couponD - privilegeAmt + delivery + handling + tax;
    const chkRound = document.getElementById('chk_rounding');
    let   rounding = 0;
    if (chkRound && chkRound.checked) {
        rounding = base - Math.floor(base);
        const rd = document.getElementById('roundingDisplay');
        if (rd) rd.textContent = rounding > 0 ? '-'+currentSym+rounding.toFixed(2) : 'none';
    } else {
        const rd = document.getElementById('roundingDisplay');
        if (rd) rd.textContent = 'off';
    }
    const total = Math.max(0, base - rounding);
    document.getElementById('summarySubtotal').textContent = currentSym+subtotal.toFixed(0);
    document.getElementById('summaryTotal').textContent    = currentSym+total.toFixed(0);

    // Re-apply coupon if subtotal changed (for percent coupons)
    if (window._appliedCoupon && window._appliedCoupon.discount_type === 'percent') {
        const newDisc = Math.min(subtotal * window._appliedCoupon.discount_value / 100, subtotal);
        document.getElementById('inp_coupon_discount').value = newDisc.toFixed(2);
        const dd = document.getElementById('couponDiscountDisplay');
        if (dd) dd.textContent = '-' + currentSym + newDisc.toFixed(2);
    }
}

// ── Coupon ────────────────────────────────────────────────────────────────────
window._appliedCoupon = null;

async function applyCoupon() {
    const code = document.getElementById('couponCodeInput')?.value?.trim();
    const statusEl = document.getElementById('couponStatus');
    const rowEl    = document.getElementById('coupon_discount_row');
    const discEl   = document.getElementById('inp_coupon_discount');
    const dispEl   = document.getElementById('couponDiscountDisplay');

    if (!code) {
        // Clear coupon
        window._appliedCoupon = null;
        if (discEl) discEl.value = '0';
        if (rowEl)  rowEl.style.display = 'none';
        if (statusEl) statusEl.innerHTML = '';
        recalc();
        return;
    }

    let subtotal = 0;
    for (let i = 0; i < rowCount; i++) {
        const qEl = document.getElementById('iqty_'+i);
        const pEl = document.getElementById('iprice_'+i);
        const dEl = document.getElementById('idisc_'+i);
        if (!qEl) continue;
        subtotal += (parseFloat(qEl.value)||0)*(parseFloat(pEl.value)||0)*(1-(parseFloat(dEl.value)||0)/100);
    }

    if (statusEl) statusEl.innerHTML = '<span style="color:#888">Checking…</span>';

    try {
        const res  = await fetch(`/books/<?= $book['id'] ?>/coupons/validate?code=${encodeURIComponent(code)}&subtotal=${subtotal}`);
        const data = await res.json();

        if (data.error) {
            window._appliedCoupon = null;
            if (discEl) discEl.value = '0';
            if (rowEl)  rowEl.style.display = 'none';
            if (statusEl) statusEl.innerHTML = `<span style="color:var(--red)"><i class="fa-solid fa-circle-xmark"></i> ${data.error}</span>`;
            recalc();
            return;
        }

        window._appliedCoupon = data;
        if (discEl) discEl.value = data.discount.toFixed(2);
        if (rowEl)  rowEl.style.display = 'flex';
        if (dispEl) dispEl.textContent  = '-' + currentSym + data.discount.toFixed(2);

        const label = data.discount_type === 'percent'
            ? `${data.discount_value}% off`
            : `${currentSym}${data.discount_value} off`;
        if (statusEl) statusEl.innerHTML = `<span style="color:var(--green)"><i class="fa-solid fa-circle-check"></i> <strong>${data.name}</strong> — ${label} applied!</span>`;

        recalc();
    } catch(e) {
        if (statusEl) statusEl.innerHTML = '<span style="color:var(--red)">Connection error. Try again.</span>';
    }
}

// Allow pressing Enter in coupon field
document.getElementById('couponCodeInput')?.addEventListener('keydown', e => {
    if (e.key === 'Enter') { e.preventDefault(); applyCoupon(); }
});

// Start with one empty row
addRow();
// Trigger privilege/points load if customer is pre-selected
const _preSelCust = document.getElementById('customerSel');
if (_preSelCust && _preSelCust.value) customerSelected(_preSelCust);
</script>

<?php $content = ob_get_clean(); require BASE_PATH . '/views/partials/layout.php'; ?>
