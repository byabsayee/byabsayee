<?php
$pageTitle  = 'New Return — ' . e($book['name']);
$isSale     = ($type === 'sales_return');
$sym = \App\Helpers\Database::row('SELECT symbol FROM book_currencies WHERE book_id=? AND is_default=1', [$book['id']]);
$sym = $sym['symbol'] ?? '৳';
ob_start();
?>

<div class="page-header">
    <div class="page-header-left">
        <div class="breadcrumb">
            <a href="/books/<?= $book['id'] ?>">Dashboard</a> <span>›</span>
            <a href="/books/<?= $book['id'] ?>/returns">Returns</a> <span>›</span>
            <span>New <?= $isSale ? 'Sales' : 'Purchase' ?> Return</span>
        </div>
        <h1><?= $isSale ? '↩ Sales Return' : '↪ Purchase Return' ?></h1>
        <p style="color:var(--text-muted);font-size:13px">
            <?= $isSale
                ? 'Customer returning goods → stock added back. Discounts kept by business → added as income in Reports.'
                : 'Returning goods to supplier → stock removed. Supplier non-refund → added as loss in Reports.' ?>
        </p>
    </div>
</div>

<form method="POST" action="/books/<?= $book['id'] ?>/returns/create" id="returnForm">
<input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
<input type="hidden" name="type"  value="<?= e($type) ?>">

<div style="display:grid;grid-template-columns:1fr 300px;gap:20px;align-items:start">

<!-- ═══ LEFT ═══ -->
<div style="display:flex;flex-direction:column;gap:16px">

    <!-- Basic info -->
    <div class="card">
        <div class="form-grid">
            <div class="form-group">
                <label>Return Number *</label>
                <input type="text" name="return_no" value="<?= e($returnNo) ?>" required>
            </div>
            <div class="form-group">
                <label>Date *</label>
                <input type="date" name="date" value="<?= date('Y-m-d') ?>" required>
            </div>
        </div>
    </div>

    <!-- Invoice selection -->
    <div class="card">
        <p class="card-title">Select Original <?= $isSale ? 'Sale' : 'Purchase' ?> Invoice</p>
        <p style="font-size:12px;color:var(--text-muted);margin-bottom:12px">
            Choose the invoice these goods were originally <?= $isSale ? 'sold on' : 'purchased from' ?>.
            Items will auto-load.
        </p>
        <select id="invoiceSelect" onchange="loadInvoiceItems(this.value)"
                style="width:100%;padding:9px 12px;border:1.5px solid var(--border);border-radius:8px;font-size:13px;font-family:inherit;outline:none">
            <option value="">— Select Invoice —</option>
            <?php foreach ($invoices as $inv): ?>
            <option value="<?= $inv['id'] ?>">
                <?= e($inv['invoice_no']) ?> | <?= e($inv['party_name'] ?? 'Unknown') ?> | <?= date('d M Y', strtotime($inv['date'])) ?> | <?= $sym.number_format($inv['total'],0) ?>
            </option>
            <?php endforeach; ?>
        </select>
        <div id="invoiceLoadMsg" style="margin-top:8px;font-size:12px;color:var(--text-muted)"></div>
    </div>

    <!-- Items table -->
    <div class="card">
        <p class="card-title">Items Being Returned</p>
        <div style="overflow-x:auto">
        <table style="width:100%;border-collapse:collapse" id="returnItemsTable">
            <thead>
                <tr>
                    <th style="padding:7px;font-size:10px;font-weight:700;text-transform:uppercase;color:var(--text-muted);border-bottom:1px solid var(--border);text-align:left">Item</th>
                    <th style="padding:7px;font-size:10px;font-weight:700;text-transform:uppercase;color:var(--text-muted);border-bottom:1px solid var(--border);width:80px;text-align:right">Orig Price</th>
                    <th style="padding:7px;font-size:10px;font-weight:700;text-transform:uppercase;color:var(--text-muted);border-bottom:1px solid var(--border);width:90px;text-align:right">Return Qty</th>
                    <th style="padding:7px;font-size:10px;font-weight:700;text-transform:uppercase;color:var(--text-muted);border-bottom:1px solid var(--border);width:90px;text-align:right">Line Total</th>
                    <th style="border-bottom:1px solid var(--border);width:30px"></th>
                </tr>
            </thead>
            <tbody id="returnItemsBody">
                <tr id="emptyRow">
                    <td colspan="5" style="padding:24px;text-align:center;color:var(--text-muted);font-size:13px">
                        Select an invoice above to load items, or add items manually below.
                    </td>
                </tr>
            </tbody>
        </table>
        </div>
        <button type="button" onclick="addManualRow()" class="btn btn-sm btn-secondary" style="margin-top:10px">
            <i class="fa-solid fa-plus"></i> Add Item Manually
        </button>
    </div>

    <!-- Remarks -->
    <div class="card">
        <div class="form-group">
            <label>Remarks / Notes</label>
            <textarea name="remarks" placeholder="Reason for return, condition of goods, any other details…" style="min-height:80px"></textarea>
        </div>
    </div>

</div>

<!-- ═══ RIGHT: Summary ═══ -->
<div style="position:sticky;top:20px;display:flex;flex-direction:column;gap:12px">
    <div class="card">
        <p class="card-title">Return Summary</p>
        <div style="display:flex;flex-direction:column;gap:10px;font-size:14px">
            <div style="display:flex;justify-content:space-between">
                <span style="color:var(--text-muted)">Items Subtotal</span>
                <strong id="rSubtotal"><?= $sym ?>0</strong>
            </div>
            <div style="display:flex;justify-content:space-between;align-items:center">
                <div>
                    <label style="color:var(--text-muted)"><?= $isSale ? 'Discount (kept by you)' : 'Non-refunded (loss)' ?></label>
                    <div style="font-size:10px;color:var(--text-muted)"><?= $isSale ? 'Reduces refund → added as income in Reports' : 'Reduces recovery → added as loss in Reports' ?></div>
                </div>
                <input type="number" name="discount" id="rDiscount" value="0" min="0" step="0.01" oninput="recalcReturn()"
                       style="width:90px;padding:5px 8px;border:1.5px solid var(--border);border-radius:7px;font-size:13px;text-align:right;font-family:inherit;outline:none">
            </div>
            <div style="display:flex;justify-content:space-between;align-items:center">
                <div>
                    <label style="color:var(--text-muted)">Delivery / Handling</label>
                    <div style="font-size:10px;color:var(--text-muted)">Added to expenses page</div>
                </div>
                <input type="number" name="delivery_charge" id="rDelivery" value="0" min="0" step="0.01" oninput="recalcReturn()"
                       style="width:90px;padding:5px 8px;border:1.5px solid var(--border);border-radius:7px;font-size:13px;text-align:right;font-family:inherit;outline:none">
            </div>
            <div style="border-top:2px solid var(--border);padding-top:10px;display:flex;justify-content:space-between">
                <strong style="font-size:15px">Total <?= $isSale ? 'Refund' : 'Recovery' ?></strong>
                <strong id="rTotal" style="font-size:18px;color:<?= $isSale ? 'var(--red)' : 'var(--green)' ?>"><?= $sym ?>0</strong>
            </div>
        </div>

        <?php if ($isSale): ?>
        <div style="margin-top:12px;padding:10px;background:var(--amber-light,#fff8e1);border-radius:8px;border:1px solid #ffe082;font-size:12px;color:#856404">
            <i class="fa-solid fa-circle-info"></i>
            Returned goods go back into stock. Any discount you keep is recorded as income in Reports.
        </div>
        <?php else: ?>
        <div style="margin-top:12px;padding:10px;background:#e8f5e9;border-radius:8px;border:1px solid #a5d6a7;font-size:12px;color:#1b5e20">
            <i class="fa-solid fa-circle-info"></i>
            Returned goods are removed from stock. Any amount the supplier doesn't refund is recorded as a loss in Reports.
        </div>
        <?php endif; ?>
    </div>

    <button type="submit" class="btn btn-primary" style="width:100%;height:46px;font-size:15px" id="submitBtn" disabled>
        Save Return
    </button>
    <a href="/books/<?= $book['id'] ?>/returns" class="btn btn-secondary" style="width:100%;text-align:center">Cancel</a>
</div>

</div><!-- grid -->
</form>

<script>
const BOOK_ID = <?= $book['id'] ?>;
const SYM     = '<?= e($sym) ?>';
let rowCount  = 0;

function esc(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

async function loadInvoiceItems(invoiceId) {
    const msg = document.getElementById('invoiceLoadMsg');
    if (!invoiceId) return;
    msg.textContent = 'Loading items…';
    try {
        const res  = await fetch(`/books/${BOOK_ID}/returns/invoice-items?invoice_id=${invoiceId}`);
        const data = await res.json();
        if (data.error) { msg.textContent = data.error; return; }
        msg.textContent = `Loaded ${data.items.length} item(s) from ${esc(data.invoice.invoice_no)}`;
        clearRows();
        data.items.forEach(item => {
            addRow(
                item.description,
                parseFloat(item.unit_price) || 0,
                parseFloat(item.qty)       || 1,
                item.product_id            || ''
            );
        });
        document.getElementById('submitBtn').disabled = false;
    } catch(e) {
        msg.textContent = 'Failed to load items. Check connection.';
    }
}

function clearRows() {
    const tbody = document.getElementById('returnItemsBody');
    tbody.innerHTML = '';
    rowCount = 0;
}

function addRow(name='', price=0, qty=1, pid='') {
    const i = rowCount++;
    const tbody = document.getElementById('returnItemsBody');
    const tr = document.createElement('tr');
    tr.id = 'retRow_'+i;
    tr.style.borderBottom = '1px solid var(--border)';
    tr.innerHTML = `
        <td style="padding:6px 4px">
            <input type="text" name="item_name[]" value="${esc(name)}" placeholder="Item description…" required
                   style="width:100%;padding:5px 7px;border:1.5px solid var(--border);border-radius:7px;font-size:12px;font-family:inherit;outline:none">
            <input type="hidden" name="item_product_id[]" value="${esc(pid)}">
        </td>
        <td style="padding:6px 4px">
            <input type="number" name="item_price[]" id="rprice_${i}" value="${price}" min="0" step="0.01" oninput="recalcReturn()"
                   style="width:100%;padding:5px 7px;border:1.5px solid var(--border);border-radius:7px;font-size:12px;text-align:right;font-family:inherit;outline:none">
        </td>
        <td style="padding:6px 4px">
            <input type="number" name="item_qty[]" id="rqty_${i}" value="${qty}" min="0.001" step="0.001" oninput="recalcReturn()"
                   style="width:100%;padding:5px 7px;border:1.5px solid var(--border);border-radius:7px;font-size:12px;text-align:right;font-family:inherit;outline:none">
        </td>
        <td style="padding:6px 4px;text-align:right;font-weight:600;font-size:12px" id="rline_${i}">0</td>
        <td style="padding:6px 4px;text-align:center">
            <button type="button" onclick="removeRetRow(${i})"
                    style="background:none;border:none;color:var(--red);cursor:pointer;font-size:18px;line-height:1">×</button>
        </td>`;
    tbody.appendChild(tr);
    recalcReturn();
    document.getElementById('submitBtn').disabled = false;
}

function addManualRow() { addRow('', 0, 1, ''); }

function removeRetRow(i) {
    const r = document.getElementById('retRow_'+i);
    if (r) r.remove();
    recalcReturn();
}

function recalcReturn() {
    let sub = 0;
    for (let i = 0; i < rowCount; i++) {
        const pEl = document.getElementById('rprice_'+i);
        const qEl = document.getElementById('rqty_'+i);
        const lEl = document.getElementById('rline_'+i);
        if (!pEl) continue;
        const line = (parseFloat(pEl.value)||0) * (parseFloat(qEl.value)||0);
        if (lEl) lEl.textContent = SYM + line.toFixed(0);
        sub += line;
    }
    const disc     = parseFloat(document.getElementById('rDiscount').value)||0;
    const delivery = parseFloat(document.getElementById('rDelivery').value)||0;
    const total    = Math.max(0, sub - disc + delivery);
    document.getElementById('rSubtotal').textContent = SYM + sub.toFixed(0);
    document.getElementById('rTotal').textContent    = SYM + total.toFixed(0);
}
</script>

<?php $content = ob_get_clean(); require BASE_PATH . '/views/partials/layout.php'; ?>
