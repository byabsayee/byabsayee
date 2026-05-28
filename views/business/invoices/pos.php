<?php
$pageTitle = 'POS — ' . e($book['name']);
$sym       = $defaultCurrency['symbol'] ?? '৳';
ob_start();
?>

<div class="page-header">
    <div class="page-header-left">
        <div class="breadcrumb">
            <a href="/books/<?= $book['id'] ?>">← <?= e($book['name']) ?></a> <span>›</span>
            <span>POS Quick Sale</span>
        </div>
        <h1>🖨 POS Quick Sale</h1>
        <p>Fast in-person sales — auto-marked as paid</p>
    </div>
    <a href="/books/<?= $book['id'] ?>/invoices/create?type=sale" class="btn btn-secondary">
        Full Invoice Instead
    </a>
</div>

<form method="POST" action="/books/<?= $book['id'] ?>/pos" id="posForm">
<input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
<input type="hidden" name="currency_symbol" value="<?= e($sym) ?>">
<input type="hidden" name="currency_code"   value="<?= e($defaultCurrency['code'] ?? 'BDT') ?>">

<div style="display:grid;grid-template-columns:1fr 300px;gap:20px;align-items:start">

<!-- LEFT: product search + items -->
<div style="display:flex;flex-direction:column;gap:14px">

    <!-- Quick product search -->
    <div class="card" style="padding:14px">
        <div style="position:relative">
            <input type="text" id="posSearch" autofocus
                   placeholder="🔍  Scan barcode or type product code / name…"
                   oninput="posSearchProducts(this.value)"
                   style="width:100%;padding:10px 14px;border:2px solid var(--brand);border-radius:9px;font-size:14px;font-family:inherit;outline:none">
        </div>
        <div id="posResults" style="display:none;margin-top:8px;border:1px solid var(--border);border-radius:8px;overflow:hidden;max-height:220px;overflow-y:auto;background:#fff;box-shadow:0 4px 12px rgba(0,0,0,.08)"></div>
    </div>

    <!-- Cart items -->
    <div class="card">
        <p class="card-title">Cart</p>
        <table style="width:100%;border-collapse:collapse" id="posTable">
            <thead>
                <tr>
                    <th style="padding:7px;font-size:11px;font-weight:600;text-transform:uppercase;color:var(--text-muted);border-bottom:1px solid var(--border);text-align:left">Item</th>
                    <th style="padding:7px;font-size:11px;font-weight:600;text-transform:uppercase;color:var(--text-muted);border-bottom:1px solid var(--border);width:70px;text-align:right">Price</th>
                    <th style="padding:7px;font-size:11px;font-weight:600;text-transform:uppercase;color:var(--text-muted);border-bottom:1px solid var(--border);width:60px;text-align:center">Qty</th>
                    <th style="padding:7px;font-size:11px;font-weight:600;text-transform:uppercase;color:var(--text-muted);border-bottom:1px solid var(--border);width:80px;text-align:right">Total</th>
                    <th style="border-bottom:1px solid var(--border);width:28px"></th>
                </tr>
            </thead>
            <tbody id="posBody">
                <tr id="emptyRow">
                    <td colspan="5" style="padding:30px;text-align:center;color:var(--text-muted);font-size:13px">
                        Search or scan products to add them here
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Customer + Note -->
    <div class="card">
        <div class="form-grid">
            <div class="form-group">
                <label>Customer (optional)</label>
                <select name="customer_id" id="posCustomer">
                    <option value="">— Walk-in —</option>
                    <?php foreach ($customers as $c): ?>
                    <option value="<?= $c['id'] ?>"><?= e($c['name']) ?><?= $c['phone'] ? ' — '.$c['phone'] : '' ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Payment Method</label>
                <select name="payment_method">
                    <?php foreach ($paymentMethods as $m): ?>
                    <option value="<?= e($m['label']) ?>"><?= e($m['label']) ?></option>
                    <?php endforeach; ?>
                    <?php if (empty($paymentMethods)): ?>
                    <option value="Cash">Cash</option>
                    <?php endif; ?>
                </select>
            </div>
            <div class="form-group full">
                <label>Note (optional)</label>
                <input type="text" name="note_customer" placeholder="Any note for the receipt…">
            </div>
        </div>
    </div>
</div>

<!-- RIGHT: totals -->
<div style="position:sticky;top:20px">
    <div class="card">
        <p class="card-title">Total</p>

        <div style="font-size:36px;font-weight:800;color:var(--brand);margin:10px 0;letter-spacing:-1px" id="posTotalDisplay">
            <?= $sym ?>0
        </div>

        <div style="display:flex;flex-direction:column;gap:9px;font-size:14px;margin-bottom:16px">
            <div style="display:flex;justify-content:space-between;align-items:center">
                <span style="color:var(--text-muted)">Discount</span>
                <input type="number" name="discount" id="pos_discount" value="0" min="0" step="0.01"
                       oninput="posRecalc()"
                       style="width:90px;padding:5px 8px;border:1.5px solid var(--border);border-radius:7px;font-size:13px;font-family:inherit;text-align:right;outline:none">
            </div>
            <div style="display:flex;justify-content:space-between;align-items:center">
                <span style="color:var(--text-muted)">Rounding</span>
                <input type="number" name="rounding" id="pos_rounding" value="0" min="0" step="0.01"
                       oninput="posRecalc()"
                       style="width:90px;padding:5px 8px;border:1.5px solid var(--border);border-radius:7px;font-size:13px;font-family:inherit;text-align:right;outline:none">
            </div>
        </div>

        <!-- Cash calculator -->
        <div style="background:var(--bg);border-radius:9px;padding:12px;margin-bottom:14px">
            <label style="font-size:12px;color:var(--text-muted);display:block;margin-bottom:6px">Cash received</label>
            <input type="number" id="cashReceived" placeholder="0" min="0" step="1"
                   oninput="calcChange()"
                   style="width:100%;padding:8px 10px;border:1.5px solid var(--border);border-radius:8px;font-size:16px;font-family:inherit;outline:none">
            <div style="margin-top:8px;display:flex;justify-content:space-between;font-size:14px">
                <span style="color:var(--text-muted)">Change</span>
                <strong id="changeDisplay" style="color:var(--green)">—</strong>
            </div>
        </div>

        <button type="submit" class="btn btn-primary"
                style="width:100%;height:52px;font-size:16px;border-radius:10px"
                id="posSubmitBtn" disabled>
            💳 Confirm Sale
        </button>
        <a href="/books/<?= $book['id'] ?>" class="btn btn-secondary"
           style="width:100%;text-align:center;margin-top:8px">Cancel</a>
    </div>
</div>

</div>
</form>

<script>
const POS_PRODUCTS = <?= json_encode(array_map(fn($p) => [
    'id'    => $p['id'],
    'name'  => $p['name'],
    'code'  => $p['product_code'] ?? '',
    'price' => (float)$p['sell_price'],
    'unit'  => $p['unit'],
    'stock' => (float)$p['stock_qty'],
], $products), JSON_UNESCAPED_UNICODE) ?>;

const SYM = '<?= e($sym) ?>';
let cartItems = {};  // pid/key => {name,price,qty,pid}
let rowIdx = 0;

function esc(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

// ── Search ───────────────────────────────────────────────────────────────────
function posSearchProducts(q) {
    const box = document.getElementById('posResults');
    q = q.trim().toLowerCase();
    if (!q) { box.style.display='none'; return; }

    const matches = POS_PRODUCTS.filter(p =>
        p.name.toLowerCase().includes(q) ||
        (p.code && p.code.toLowerCase().includes(q))
    ).slice(0,10);

    if (!matches.length) { box.style.display='none'; return; }

    box.innerHTML = matches.map(p => `
        <div onclick="addToCart(${p.id})"
             style="padding:10px 14px;cursor:pointer;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;font-size:13px"
             onmouseover="this.style.background='var(--bg)'" onmouseout="this.style.background=''">
            <span><strong>${esc(p.name)}</strong> <span style="color:var(--text-muted);font-size:11px">[${esc(p.code)}]</span></span>
            <strong style="color:var(--brand)">${SYM}${p.price.toFixed(0)}</strong>
        </div>`
    ).join('');
    box.style.display = 'block';
}

// Close search dropdown
document.addEventListener('click', e => {
    if (!e.target.closest('#posSearch') && !e.target.closest('#posResults'))
        document.getElementById('posResults').style.display='none';
});

// ── Add to cart ───────────────────────────────────────────────────────────────
function addToCart(pid) {
    const p = POS_PRODUCTS.find(x => x.id === pid);
    if (!p) return;

    // Clear search
    document.getElementById('posSearch').value = '';
    document.getElementById('posResults').style.display = 'none';
    document.getElementById('posSearch').focus();

    // If already in cart, increment qty
    if (cartItems[pid]) {
        cartItems[pid].qty++;
        document.getElementById('posqty_'+pid).value = cartItems[pid].qty;
        updateLineTotal(pid);
        posRecalc();
        return;
    }

    // New cart row
    cartItems[pid] = { name: p.name, price: p.price, qty: 1, pid: pid };

    // Remove empty row if exists
    const empty = document.getElementById('emptyRow');
    if (empty) empty.remove();

    const tbody = document.getElementById('posBody');
    const tr    = document.createElement('tr');
    tr.id = 'posrow_'+pid;
    tr.innerHTML = `
        <td style="padding:8px 6px;font-size:13px;font-weight:500">${esc(p.name)}
            <input type="hidden" name="item_name[]"       value="${esc(p.name)}">
            <input type="hidden" name="item_product_id[]" value="${pid}">
            <input type="hidden" name="item_price[]"      id="posprice_${pid}" value="${p.price}">
        </td>
        <td style="padding:8px 6px;text-align:right;font-size:13px">${SYM}${p.price.toFixed(0)}</td>
        <td style="padding:8px 6px;text-align:center">
            <div style="display:flex;align-items:center;justify-content:center;gap:4px">
                <button type="button" onclick="changeQty(${pid},-1)"
                        style="width:26px;height:26px;border:1.5px solid var(--border);border-radius:6px;background:#fff;cursor:pointer;font-size:15px;font-weight:700;display:flex;align-items:center;justify-content:center">−</button>
                <input type="number" name="item_qty[]" id="posqty_${pid}" value="1" min="0.001" step="1"
                       oninput="updateQtyInput(${pid})"
                       style="width:40px;text-align:center;padding:4px;border:1.5px solid var(--border);border-radius:6px;font-size:13px;font-family:inherit;outline:none">
                <button type="button" onclick="changeQty(${pid},1)"
                        style="width:26px;height:26px;border:1.5px solid var(--border);border-radius:6px;background:#fff;cursor:pointer;font-size:15px;font-weight:700;display:flex;align-items:center;justify-content:center">+</button>
            </div>
        </td>
        <td style="padding:8px 6px;text-align:right;font-weight:600;font-size:13px" id="posline_${pid}">
            ${SYM}${p.price.toFixed(0)}
        </td>
        <td style="padding:8px 6px;text-align:center">
            <button type="button" onclick="removeFromCart(${pid})"
                    style="background:none;border:none;color:var(--red);cursor:pointer;font-size:20px;line-height:1">×</button>
        </td>`;
    tbody.appendChild(tr);
    posRecalc();
}

function changeQty(pid, delta) {
    if (!cartItems[pid]) return;
    const input = document.getElementById('posqty_'+pid);
    const newQty = Math.max(1, (parseFloat(input.value)||1) + delta);
    input.value = newQty;
    cartItems[pid].qty = newQty;
    updateLineTotal(pid);
    posRecalc();
}

function updateQtyInput(pid) {
    if (!cartItems[pid]) return;
    const q = parseFloat(document.getElementById('posqty_'+pid).value) || 0;
    cartItems[pid].qty = q;
    updateLineTotal(pid);
    posRecalc();
}

function updateLineTotal(pid) {
    const item = cartItems[pid];
    const line = item.price * item.qty;
    const el   = document.getElementById('posline_'+pid);
    if (el) el.textContent = SYM + line.toFixed(0);
}

function removeFromCart(pid) {
    delete cartItems[pid];
    const row = document.getElementById('posrow_'+pid);
    if (row) row.remove();
    if (Object.keys(cartItems).length === 0) {
        document.getElementById('posBody').innerHTML =
            '<tr id="emptyRow"><td colspan="5" style="padding:30px;text-align:center;color:var(--text-muted);font-size:13px">Cart is empty</td></tr>';
    }
    posRecalc();
}

function posRecalc() {
    let subtotal = 0;
    for (const pid in cartItems) {
        subtotal += cartItems[pid].price * cartItems[pid].qty;
    }
    const disc     = parseFloat(document.getElementById('pos_discount').value)||0;
    const rounding = parseFloat(document.getElementById('pos_rounding').value)||0;
    const total    = Math.max(0, subtotal - disc - rounding);

    document.getElementById('posTotalDisplay').textContent = SYM + total.toFixed(0);
    document.getElementById('posSubmitBtn').disabled = Object.keys(cartItems).length === 0;
    calcChange();
}

function calcChange() {
    const totalEl   = document.getElementById('posTotalDisplay');
    const total     = parseFloat(totalEl.textContent.replace(/[^\d.]/g,''))||0;
    const cash      = parseFloat(document.getElementById('cashReceived').value)||0;
    const changeEl  = document.getElementById('changeDisplay');
    if (cash > 0) {
        const change = cash - total;
        changeEl.textContent = SYM + Math.max(0, change).toFixed(0);
        changeEl.style.color = change >= 0 ? 'var(--green)' : 'var(--red)';
    } else {
        changeEl.textContent = '—';
    }
}
</script>

<?php $content = ob_get_clean(); require BASE_PATH . '/views/partials/layout.php'; ?>
