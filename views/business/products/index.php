<?php
$pageTitle = 'Products — ' . e($book['name']);
ob_start();
?>

<div class="page-header">
    <div class="page-header-left">
        <div class="breadcrumb">
            <a href="/books/<?= $book['id'] ?>">Dashboard</a> <span>›</span>
            <span>Products</span>
        </div>
        <h1><i class="fa-solid fa-box" style="color:var(--brand)"></i> Products & Stock</h1>
        <p>Add, edit, remove products and keep track of all of them &nbsp;
            <span style="font-size:11px;background:var(--brand);color:#fff;border-radius:20px;padding:2px 9px;font-weight:700">
                <?= $inventoryMethod ?? 'FIFO' ?> mode
            </span>
        </p>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
        <button class="btn btn-secondary" data-modal="addCategoryModal">+ Category</button>
        <button class="btn btn-secondary btn-sm" data-modal="printModal" title="Print PDF">
            <i class="fa-solid fa-print"></i> Print
        </button>
        <button class="btn btn-primary"   data-modal="addProductModal">+ Add Product</button>
    </div>
</div>

<!-- Summary -->
<div class="stat-grid" style="grid-template-columns:repeat(4,1fr);max-width:700px;margin-bottom:20px">
    <div class="stat-card"><div class="stat-label">Products</div><div class="stat-value brand"><?= $summary['total_products'] ?></div></div>
    <div class="stat-card"><div class="stat-label">Stock Value</div><div class="stat-value brand"><?= format_money($summary['stock_value']) ?></div></div>
    <div class="stat-card"><div class="stat-label">Low Stock</div><div class="stat-value <?= $summary['low_stock']>0?'red':'green' ?>"><?= $summary['low_stock'] ?></div></div>
    <div class="stat-card"><div class="stat-label">Out of Stock</div><div class="stat-value <?= $summary['out_of_stock']>0?'red':'green' ?>"><?= $summary['out_of_stock'] ?></div></div>
</div>

<!-- Controls -->
<div class="lm-controls">
    <div class="lm-search-wrap">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="text" class="lm-search" id="prodSearch" placeholder="Search name, code, barcode…">
        <button class="lm-search-clear" id="prodClear"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <select class="lm-select" id="prodSort" title="Sort products">
        <option value="name-asc">Name A–Z</option>
        <option value="name-desc">Name Z–A</option>
        <option value="stock-desc">Stock High–Low</option>
        <option value="stock-asc">Stock Low–High</option>
        <option value="sell-desc">Price High–Low</option>
        <option value="sell-asc">Price Low–High</option>
    </select>
    <?php if (!empty($categories)): ?>
    <select class="lm-select" id="prodCat" onchange="window.location.href='?cat='+this.value" title="Filter by category">
        <option value="0" <?= empty($_GET['cat'])?'selected':'' ?>>All Categories</option>
        <?php foreach ($categories as $cat): ?>
        <option value="<?= $cat['id'] ?>" <?= (($_GET['cat']??0)==$cat['id'])?'selected':'' ?>><?= e($cat['name']) ?></option>
        <?php endforeach; ?>
    </select>
    <?php endif; ?>
</div>
<div class="lm-filter-pills">
    <span style="font-size:12px;font-weight:600;color:var(--text-muted)">Stock:</span>
    <button class="btn btn-sm btn-primary" data-pf="all">All</button>
    <button class="btn btn-sm btn-secondary" data-pf="ok">In Stock</button>
    <button class="btn btn-sm btn-secondary" data-pf="low">Low Stock</button>
    <button class="btn btn-sm btn-secondary" data-pf="out">Out of Stock</button>
</div>

<?php if (empty($products)): ?>
<div class="table-wrap">
    <div class="empty-state">
        <div class="empty-icon">📦</div>
        <h3>No products yet</h3>
        <p>Add products to track inventory and use them in invoices.</p>
    </div>
</div>
<?php else: ?>
<div class="table-wrap">
    <table id="prodTable">
        <thead>
            <tr>
                <th data-sort="0">Code</th>
                <th>Product</th>
                <th>Category</th>
                <th style="text-align:right">Buy</th>
                <th style="text-align:right">Sell</th>
                <th style="text-align:right">Stock</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($products as $p):
            $variants = \App\Helpers\Database::query(
                'SELECT * FROM product_variants WHERE product_id=? ORDER BY label,value', [$p['id']]
            );
            $batches = $batchesByProduct[$p['id']] ?? [];
            $activeBatches = array_values(array_filter($batches, fn($b) => (float)$b['remaining_qty'] > 0));
            if ($inventoryMethod === 'LIFO') $activeBatches = array_reverse($activeBatches);

            // Decide rendering mode: per-batch rows or single product row
            $showBatchRows = count($activeBatches) > 1;

            if (!$showBatchRows):
                // ── Single row (no batches or only one active batch) ──────────────────
                $displayStock = count($activeBatches) === 1 ? (float)$activeBatches[0]['remaining_qty'] : (float)$p['stock_qty'];
                $displayBuy   = count($activeBatches) === 1 ? (float)$activeBatches[0]['buy_price']     : (float)$p['buy_price'];
                $displayBarcode = count($activeBatches) === 1 ? ($activeBatches[0]['barcode'] ?? $p['barcode']) : $p['barcode'];
                $stockStatus  = $displayStock <= 0
                    ? ['label'=>'Out','class'=>'badge-red']
                    : ($displayStock <= $p['low_stock_alert']
                        ? ['label'=>'Low','class'=>'badge-amber']
                        : ['label'=>'OK','class'=>'badge-green']);
        ?>
        <tr data-stock="<?= $stockStatus['label']==='Out'?'out':($stockStatus['label']==='Low'?'low':'ok') ?>">
            <td>
                <span style="font-family:monospace;font-size:11px;background:var(--bg);padding:2px 6px;border-radius:4px;border:1px solid var(--border)">
                    <?= e($p['product_code'] ?? 'PRD-?') ?>
                </span>
                <?php if ($displayBarcode): ?>
                <div style="font-size:10px;color:var(--text-muted);margin-top:2px"><?= e($displayBarcode) ?></div>
                <?php endif; ?>
            </td>
            <td>
                <div style="font-weight:500"><?= e($p['name']) ?></div>
                <?php if (!empty($variants)): ?>
                <div style="display:flex;flex-wrap:wrap;gap:3px;margin-top:3px">
                    <?php foreach (array_slice($variants,0,4) as $v): ?>
                    <span style="font-size:10px;background:var(--blue-bg);color:var(--blue);padding:1px 5px;border-radius:10px">
                        <?= e($v['label']) ?>: <?= e($v['value']) ?>
                    </span>
                    <?php endforeach; ?>
                    <?php if (count($variants) > 4): ?><span style="font-size:10px;color:var(--text-muted)">+<?= count($variants)-4 ?> more</span><?php endif; ?>
                </div>
                <?php endif; ?>
            </td>
            <td class="td-muted"><?= $p['category_name'] ? e($p['category_name']) : '—' ?></td>
            <td style="text-align:right" class="td-amount"><?= format_money($displayBuy) ?></td>
            <td style="text-align:right" class="td-amount"><?= format_money($p['sell_price']) ?></td>
            <td style="text-align:right;font-weight:600">
                <?= rtrim(rtrim(number_format($displayStock,3),'0'),'.') ?> <?= e($p['unit']) ?>
            </td>
            <td><span class="badge <?= $stockStatus['class'] ?>"><?= $stockStatus['label'] ?></span></td>
            <td style="white-space:nowrap">
                <button class="btn btn-sm btn-secondary" title="Adjust"
                        onclick="openAdjust(<?= $p['id'] ?>,'<?= e(addslashes($p['name'])) ?>',<?= $displayStock ?>)">
                    <i class="fa-solid fa-sliders"></i>
                </button>
                <button class="btn btn-sm btn-secondary" title="Edit"
                        onclick="openEdit(<?= htmlspecialchars(json_encode($p),ENT_QUOTES) ?>,<?= htmlspecialchars(json_encode($variants),ENT_QUOTES) ?>)">
                    <i class="fa-solid fa-pen"></i>
                </button>
                <a href="/books/<?= $book['id'] ?>/products/barcodes?product_id=<?= $p['id'] ?>"
                   title="Print Barcode" target="_blank" class="btn btn-sm btn-secondary">
                    <i class="fa-solid fa-barcode"></i>
                </a>
                <form method="POST" action="/books/<?= $book['id'] ?>/products/<?= $p['id'] ?>/delete"
                      style="display:inline" data-confirm="Delete &quot;<?= e($p['name']) ?>&quot;?">
                    <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                    <button class="btn btn-sm btn-danger" title="Delete"><i class="fa-solid fa-trash" style="color:#fff"></i></button>
                </form>
            </td>
        </tr>

            <?php else:
                // ── Multiple batch rows — one full row per active batch ────────────────
                foreach ($activeBatches as $bIdx => $bat):
                    $isNext     = $bIdx === 0;
                    $bStock     = (float)$bat['remaining_qty'];
                    $stockStatus = $bStock <= 0
                        ? ['label'=>'Out','class'=>'badge-red']
                        : ($bStock <= $p['low_stock_alert']
                            ? ['label'=>'Low','class'=>'badge-amber']
                            : ['label'=>'OK','class'=>'badge-green']);
                    $bBarcode = $bat['barcode'] ?? ($p['barcode'] ? $p['barcode'].'-B'.($bIdx+1) : null);
            ?>
        <tr data-stock="<?= $stockStatus['label']==='Out'?'out':($stockStatus['label']==='Low'?'low':'ok') ?>"
            style="<?= $isNext ? 'border-left:3px solid var(--green)' : 'border-left:3px solid transparent' ?>">
            <td>
                <span style="font-family:monospace;font-size:11px;background:var(--bg);padding:2px 6px;border-radius:4px;border:1px solid var(--border)">
                    <?= e($p['product_code'] ?? 'PRD-?') ?>
                </span>
                <?php if ($bBarcode): ?>
                <div style="font-size:10px;color:var(--text-muted);margin-top:2px;font-family:monospace"><?= e($bBarcode) ?></div>
                <?php endif; ?>
                <?php if ($isNext): ?>
                <div style="margin-top:2px">
                    <span style="font-size:9px;background:var(--green);color:#fff;border-radius:8px;padding:1px 5px;font-weight:700"><?= $inventoryMethod ?> NEXT</span>
                </div>
                <?php endif; ?>
            </td>
            <td>
                <div style="font-weight:500"><?= e($p['name']) ?></div>
                <?php if (!empty($variants)): ?>
                <div style="display:flex;flex-wrap:wrap;gap:3px;margin-top:3px">
                    <?php foreach (array_slice($variants,0,3) as $v): ?>
                    <span style="font-size:10px;background:var(--blue-bg);color:var(--blue);padding:1px 5px;border-radius:10px">
                        <?= e($v['label']) ?>: <?= e($v['value']) ?>
                    </span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <div style="font-size:10px;color:var(--text-muted);margin-top:2px">
                    Batch <?= $bIdx+1 ?> · Added <?= date('d M Y', strtotime($bat['created_at'])) ?>
                </div>
            </td>
            <td class="td-muted"><?= $p['category_name'] ? e($p['category_name']) : '—' ?></td>
            <td style="text-align:right" class="td-amount"><?= format_money($bat['buy_price']) ?></td>
            <td style="text-align:right" class="td-amount"><?= format_money($p['sell_price']) ?></td>
            <td style="text-align:right;font-weight:600">
                <?= rtrim(rtrim(number_format($bStock,3),'0'),'.') ?> <?= e($p['unit']) ?>
            </td>
            <td><span class="badge <?= $stockStatus['class'] ?>"><?= $stockStatus['label'] ?></span></td>
            <td style="white-space:nowrap">
                <button class="btn btn-sm btn-secondary" title="Adjust"
                        onclick="openAdjust(<?= $p['id'] ?>,'<?= e(addslashes($p['name'])) ?> (Batch <?= $bIdx+1 ?>)',<?= $bStock ?>)">
                    <i class="fa-solid fa-sliders"></i>
                </button>
                <button class="btn btn-sm btn-secondary" title="Edit Product"
                        onclick="openEdit(<?= htmlspecialchars(json_encode($p),ENT_QUOTES) ?>,<?= htmlspecialchars(json_encode($variants),ENT_QUOTES) ?>)">
                    <i class="fa-solid fa-pen"></i>
                </button>
                <a href="/books/<?= $book['id'] ?>/products/barcodes?product_id=<?= $p['id'] ?>"
                   title="Print Barcode" target="_blank" class="btn btn-sm btn-secondary">
                    <i class="fa-solid fa-barcode"></i>
                </a>
                <?php if ($bIdx === count($activeBatches)-1): // Delete only on last batch row ?>
                <form method="POST" action="/books/<?= $book['id'] ?>/products/<?= $p['id'] ?>/delete"
                      style="display:inline" data-confirm="Delete &quot;<?= e($p['name']) ?>&quot; and ALL its batches?">
                    <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                    <button class="btn btn-sm btn-danger" title="Delete Product"><i class="fa-solid fa-trash" style="color:#fff"></i></button>
                </form>
                <?php endif; ?>
            </td>
        </tr>
            <?php endforeach; // activeBatches ?>
            <?php endif; // showBatchRows ?>
        <?php endforeach; // products ?>
        </tbody>
    </table>
</div>
<div id="prodPager"></div>
<?php endif; ?>

<!-- ══ ADD CATEGORY MODAL ══ -->
<div class="modal-backdrop" id="addCategoryModal">
    <div class="modal">
        <div class="modal-title">Add Category</div>
        <form method="POST" action="/books/<?= $book['id'] ?>/products/category/add">
            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
            <div class="form-grid" style="gap:12px">
                <div class="form-group full">
                    <label>Category Name *</label>
                    <input type="text" name="name" placeholder="e.g. Electronics, Clothing" required autofocus>
                </div>
                <div class="form-group full">
                    <label>Parent Category (optional)</label>
                    <select name="parent_id">
                        <option value="">— Top level —</option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>"><?= e($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-close-modal>Cancel</button>
                <button type="submit" class="btn btn-primary">Create Category</button>
            </div>
        </form>
    </div>
</div>

<!-- ══ ADD PRODUCT MODAL ══ -->
<div class="modal-backdrop" id="addProductModal">
    <div class="modal" style="max-width:580px">
        <div class="modal-title">Add Product</div>
        <form method="POST" action="/books/<?= $book['id'] ?>/products/add" enctype="multipart/form-data">
            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
            <div style="max-height:68vh;overflow-y:auto;padding-right:2px">
            <div class="form-grid" style="gap:12px">
                <div class="form-group full">
                    <label>Product Name *</label>
                    <input type="text" name="name" required placeholder="e.g. Messenger Bag">
                </div>
                <div class="form-group">
                    <label>Category</label>
                    <select name="category_id">
                        <option value="">— None —</option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>"><?= e($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Or create new category</label>
                    <input type="text" name="new_category" placeholder="New category name">
                </div>
                <div class="form-group">
                    <label>Barcode (shared by all variants)</label>
                    <input type="text" name="barcode" placeholder="Scan or type barcode">
                </div>
                <div class="form-group">
                    <label>Unit</label>
                    <select name="unit">
                        <?php foreach (['pcs','kg','g','ltr','ml','box','pack','pair','set','m','cm','dozen'] as $u): ?>
                        <option value="<?= $u ?>"><?= $u ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Buy Price</label>
                    <input type="number" name="buy_price"  value="0" step="0.01" min="0">
                </div>
                <div class="form-group">
                    <label>Sell Price</label>
                    <input type="number" name="sell_price" value="0" step="0.01" min="0">
                </div>
                <div class="form-group">
                    <label>Opening Stock</label>
                    <input type="number" name="stock_qty" value="0" step="0.001" min="0">
                </div>
                <div class="form-group">
                    <label>Low Stock Alert</label>
                    <input type="number" name="low_stock_alert" value="5" step="0.001" min="0">
                </div>
                <div class="form-group full">
                    <label>Description</label>
                    <textarea name="description" style="min-height:52px" placeholder="Optional…"></textarea>
                </div>
                <div class="form-group full">
                    <label>Product Image</label>
                    <input type="file" name="image" accept="image/*">
                </div>
                <!-- Variants -->
                <div class="form-group full">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px">
                        <label style="margin:0">Variants (Size / Color / Type)</label>
                        <button type="button" onclick="addVariantRow('variantRows')"
                                class="btn btn-sm btn-secondary">+ Add Variant</button>
                    </div>
                    <div id="variantRows" style="display:flex;flex-direction:column;gap:6px"></div>
                    <div style="font-size:11px;color:var(--text-muted);margin-top:5px">e.g. Label: Color → Value: Red</div>
                </div>
            </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-close-modal>Cancel</button>
                <button type="submit" class="btn btn-primary">Add Product</button>
            </div>
        </form>
    </div>
</div>

<!-- ══ ADJUST STOCK MODAL ══ -->
<div class="modal-backdrop" id="adjustModal">
    <div class="modal">
        <div class="modal-title">Adjust Stock — <span id="adjustName"></span></div>
        <form method="POST" id="adjustForm">
            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
            <div class="form-grid" style="gap:12px">
                <div class="form-group full">
                    <label>Current qty: <strong id="adjustQty"></strong></label>
                </div>
                <!-- Styled toggle buttons -->
                <div class="form-group full">
                    <label style="display:block;margin-bottom:8px">Action</label>
                    <div style="display:flex;gap:10px">
                        <label id="lbl_add" onclick="setAdjType('add')"
                               style="flex:1;display:flex;align-items:center;justify-content:center;gap:8px;padding:10px;border-radius:9px;cursor:pointer;border:2px solid var(--green);background:var(--green-bg);color:var(--green);font-weight:600;font-size:14px">
                            <input type="radio" name="adjust_type" value="add" id="adj_add" checked style="display:none">
                            ＋ Add Stock
                        </label>
                        <label id="lbl_rem" onclick="setAdjType('remove')"
                               style="flex:1;display:flex;align-items:center;justify-content:center;gap:8px;padding:10px;border-radius:9px;cursor:pointer;border:2px solid var(--border);background:transparent;color:var(--text-muted);font-weight:600;font-size:14px">
                            <input type="radio" name="adjust_type" value="remove" id="adj_rem" style="display:none">
                            － Remove Stock
                        </label>
                    </div>
                </div>
                <div class="form-group full">
                    <label>Quantity *</label>
                    <input type="number" name="qty" step="0.001" min="0.001" required placeholder="0"
                           style="font-size:18px;padding:10px 12px">
                </div>
                <div class="form-group full">
                    <label>Note (optional)</label>
                    <input type="text" name="note" placeholder="e.g. Received from supplier">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-close-modal>Cancel</button>
                <button type="submit" class="btn btn-primary">Update Stock</button>
            </div>
        </form>
    </div>
</div>

<!-- ══ EDIT PRODUCT MODAL ══ -->
<div class="modal-backdrop" id="editProductModal">
    <div class="modal" style="max-width:580px">
        <div class="modal-title">Edit Product</div>
        <form method="POST" id="editProductForm" enctype="multipart/form-data">
            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
            <div style="max-height:68vh;overflow-y:auto;padding-right:2px">
            <div class="form-grid" style="gap:12px">
                <div class="form-group full"><label>Product Name *</label><input type="text" name="name" id="ep_name" required></div>
                <div class="form-group">
                    <label>Category</label>
                    <select name="category_id" id="ep_category">
                        <option value="">— None —</option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>"><?= e($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Unit</label>
                    <select name="unit" id="ep_unit">
                        <?php foreach (['pcs','kg','g','ltr','ml','box','pack','pair','set','m','cm','dozen'] as $u): ?>
                        <option value="<?= $u ?>"><?= $u ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group"><label>Barcode</label><input type="text" name="barcode" id="ep_barcode"></div>
                <div class="form-group"><label>Buy Price</label><input type="number" name="buy_price" id="ep_buy" step="0.01" min="0"></div>
                <div class="form-group"><label>Sell Price</label><input type="number" name="sell_price" id="ep_sell" step="0.01" min="0"></div>
                <div class="form-group"><label>Low Stock Alert</label><input type="number" name="low_stock_alert" id="ep_low" step="0.001" min="0"></div>
                <div class="form-group full"><label>Description</label><textarea name="description" id="ep_desc" style="min-height:52px"></textarea></div>
                <div class="form-group full"><label>New Image</label><input type="file" name="image" accept="image/*"></div>
                <!-- Variants in edit -->
                <div class="form-group full">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px">
                        <label style="margin:0">Variants</label>
                        <button type="button" onclick="addVariantRow('editVariantRows')"
                                class="btn btn-sm btn-secondary">+ Add</button>
                    </div>
                    <div id="editVariantRows" style="display:flex;flex-direction:column;gap:6px"></div>
                </div>
            </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-close-modal>Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
// ── Stock adjust toggle ───────────────────────────────────────────────────────
function setAdjType(type) {
    document.getElementById('adj_add').checked = (type === 'add');
    document.getElementById('adj_rem').checked = (type === 'remove');

    const addLbl = document.getElementById('lbl_add');
    const remLbl = document.getElementById('lbl_rem');

    if (type === 'add') {
        addLbl.style.borderColor = 'var(--green)';
        addLbl.style.background  = 'var(--green-bg)';
        addLbl.style.color       = 'var(--green)';
        remLbl.style.borderColor = 'var(--border)';
        remLbl.style.background  = 'transparent';
        remLbl.style.color       = 'var(--text-muted)';
    } else {
        remLbl.style.borderColor = 'var(--red)';
        remLbl.style.background  = 'var(--red-bg)';
        remLbl.style.color       = 'var(--red)';
        addLbl.style.borderColor = 'var(--border)';
        addLbl.style.background  = 'transparent';
        addLbl.style.color       = 'var(--text-muted)';
    }
}

function openAdjust(id, name, qty) {
    document.getElementById('adjustName').textContent = name;
    document.getElementById('adjustQty').textContent  = qty;
    document.getElementById('adjustForm').action =
        '/books/<?= $book['id'] ?>/products/' + id + '/adjust';
    // Reset to add
    setAdjType('add');
    document.getElementById('adjustModal').classList.add('open');
}

// ── Edit product ──────────────────────────────────────────────────────────────
function openEdit(p, variants) {
    document.getElementById('ep_name').value     = p.name;
    document.getElementById('ep_barcode').value  = p.barcode || '';
    document.getElementById('ep_buy').value      = p.buy_price;
    document.getElementById('ep_sell').value     = p.sell_price;
    document.getElementById('ep_low').value      = p.low_stock_alert;
    document.getElementById('ep_desc').value     = p.description || '';
    document.getElementById('ep_unit').value     = p.unit;
    document.getElementById('ep_category').value = p.category_id || '';
    document.getElementById('editProductForm').action =
        '/books/<?= $book['id'] ?>/products/' + p.id + '/edit';

    // Load existing variants
    const container = document.getElementById('editVariantRows');
    container.innerHTML = '';
    editVarIdx = 0;
    if (variants && variants.length) {
        variants.forEach(v => addVariantRow('editVariantRows', v.label, v.value));
    }

    document.getElementById('editProductModal').classList.add('open');
}

// ── Variant rows ──────────────────────────────────────────────────────────────
let varIdx = 0;
let editVarIdx = 0;

function addVariantRow(containerId, label='', value='') {
    const container = document.getElementById(containerId);
    const isEdit    = containerId === 'editVariantRows';
    const i         = isEdit ? editVarIdx++ : varIdx++;
    const prefix    = isEdit ? 'ev' : 'av';

    const div = document.createElement('div');
    div.style.cssText = 'display:grid;grid-template-columns:1fr 1fr auto;gap:8px;align-items:center';
    div.innerHTML = `
        <input type="text" name="variants[${i}][label]" value="${esc(label)}"
               placeholder="Label (e.g. Color)"
               style="padding:6px 8px;border:1.5px solid var(--border);border-radius:8px;font-size:13px;font-family:inherit;outline:none">
        <input type="text" name="variants[${i}][value]" value="${esc(value)}"
               placeholder="Value (e.g. Red)"
               style="padding:6px 8px;border:1.5px solid var(--border);border-radius:8px;font-size:13px;font-family:inherit;outline:none">
        <button type="button" onclick="this.closest('div').remove()"
                style="background:none;border:none;color:var(--red);cursor:pointer;font-size:20px;line-height:1;padding:0 2px">×</button>`;
    container.appendChild(div);
    div.querySelector('input').focus();
}

function esc(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>


<script>
(function(){
    var allRows = [], stockF = 'all', searchQ = '', sortKey = 'name-asc', perPage = 20, curPage = 1;
    function init() {
        allRows = Array.from(document.querySelectorAll('#prodTable tbody tr'));
        var si = document.getElementById('prodSearch'), sc = document.getElementById('prodClear');
        if(si){ si.addEventListener('input',function(){ searchQ=this.value.toLowerCase().trim(); sc.classList.toggle('visible',searchQ.length>0); curPage=1; render(); }); sc.addEventListener('click',function(){ si.value='';searchQ='';sc.classList.remove('visible');curPage=1;render(); }); }
        var ss = document.getElementById('prodSort'); if(ss) ss.addEventListener('change',function(){ sortKey=this.value;curPage=1;render(); });
        document.querySelectorAll('[data-pf]').forEach(function(b){ b.addEventListener('click',function(){ stockF=this.getAttribute('data-pf'); document.querySelectorAll('[data-pf]').forEach(function(x){x.classList.remove('btn-primary');x.classList.add('btn-secondary');}); this.classList.add('btn-primary');this.classList.remove('btn-secondary'); curPage=1;render(); }); });
        render();
    }
    function render() {
        var f = allRows.filter(function(row){
            if(stockF!=='all' && row.getAttribute('data-stock')!==stockF) return false;
            if(searchQ && row.textContent.toLowerCase().indexOf(searchQ)===-1) return false;
            return true;
        });
        f.sort(function(a,b){
            function td(r,i){var c=r.querySelectorAll('td')[i];return c?c.textContent.trim():'';}
            if(sortKey==='name-asc')  return td(a,1).localeCompare(td(b,1));
            if(sortKey==='name-desc') return td(b,1).localeCompare(td(a,1));
            var sa=parseFloat(td(a,5).replace(/[^0-9.]/g,'')||0), sb=parseFloat(td(b,5).replace(/[^0-9.]/g,'')||0);
            if(sortKey==='stock-desc') return sb-sa; if(sortKey==='stock-asc') return sa-sb;
            var pa=parseFloat(td(a,4).replace(/[^0-9.]/g,'')||0), pb=parseFloat(td(b,4).replace(/[^0-9.]/g,'')||0);
            if(sortKey==='sell-desc') return pb-pa; if(sortKey==='sell-asc') return pa-pb;
            return 0;
        });
        var pp=perPage==='all'?Infinity:parseInt(perPage), total=f.length;
        var tpg=pp===Infinity?1:Math.max(1,Math.ceil(total/pp));
        if(curPage>tpg)curPage=tpg; if(curPage<1)curPage=1;
        var s=pp===Infinity?0:(curPage-1)*pp, e=pp===Infinity?total:Math.min(s+pp,total);
        var tbody=document.querySelector('#prodTable tbody'), colC=document.querySelector('#prodTable thead tr').children.length;
        while(tbody.firstChild) tbody.removeChild(tbody.firstChild);
        if(f.length===0){var nr=document.createElement('tr');nr.className='lm-no-results';var nd=document.createElement('td');nd.setAttribute('colspan',colC);nd.textContent='No products match.';nr.appendChild(nd);tbody.appendChild(nr);}
        else f.slice(s,e).forEach(function(r){tbody.appendChild(r);});
        renderPager(document.getElementById('prodPager'),total,tpg,s,e,pp);
    }
    function renderPager(el,total,tpg,s,e,pp){
        if(!el)return; el.innerHTML='';
        var wrap=document.createElement('div');wrap.className='lm-pagination';
        var info=document.createElement('div');info.className='lm-page-info';
        info.textContent=total===0?'No results':pp===Infinity?'Showing all '+total+' products':'Showing '+(s+1)+'\u2013'+e+' of '+total;
        wrap.appendChild(info);
        if(tpg>1){var pages=document.createElement('div');pages.className='lm-pages';
            function mkB(l,pg){var b=document.createElement('button');b.className='lm-page-btn';if(pg===curPage)b.classList.add('active');b.textContent=l;if(pg)b.addEventListener('click',function(){curPage=pg;render();});return b;}
            if(curPage>1)pages.appendChild(mkB('\u2039',curPage-1));
            var ns=tpg<=7?Array.from({length:tpg},(_,i)=>i+1):[1,...(curPage>3?['\u2026']:[]),...Array.from({length:3},(_,i)=>Math.max(2,curPage-1)+i).filter(x=>x>=2&&x<=tpg-1),(curPage<tpg-2?'\u2026':''),tpg];
            ns.filter(Boolean).forEach(function(p){var b=mkB(p==='…'?'…':p,p==='…'?0:p);if(p==='…')b.classList.add('lm-ellipsis');pages.appendChild(b);});
            if(curPage<tpg)pages.appendChild(mkB('\u203a',curPage+1));
            wrap.appendChild(pages);
        }
        var ppW=document.createElement('div');ppW.className='lm-per-page-wrap';
        var sl=document.createElement('select');sl.className='lm-select';sl.style.padding='4px 8px';sl.style.margin='0 4px';
        [20,50,100,'all'].forEach(function(v){var o=document.createElement('option');o.value=v;o.textContent=v==='all'?'All':v;if((pp===Infinity&&v==='all')||pp===v)o.selected=true;sl.appendChild(o);});
        sl.addEventListener('change',function(){perPage=sl.value;curPage=1;render();});
        ppW.appendChild(document.createTextNode('Show '));ppW.appendChild(sl);ppW.appendChild(document.createTextNode(' per page'));
        wrap.appendChild(ppW); el.appendChild(wrap);
    }
    document.addEventListener('DOMContentLoaded',init);
})();
</script>

<?php
$content = ob_get_clean();
ob_start();
$printCategory = 'products';
require BASE_PATH . '/views/partials/print-modal.php';
$content .= ob_get_clean();
require BASE_PATH . '/views/partials/layout.php'; ?>
