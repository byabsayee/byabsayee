<?php
$pageTitle = 'Customer Privileges — ' . e($book['name']);
ob_start();
?>

<div class="page-header">
    <div class="page-header-left">
        <div class="breadcrumb">
            <a href="/books">Books</a> <span>›</span>
            <a href="/books/<?= $book['id'] ?>"><?= e($book['name']) ?></a> <span>›</span>
            <span>Privileges</span>
        </div>
        <h1>Customer Privileges</h1>
        <p>Create discount groups and assign them to customers.</p>
    </div>
    <button class="btn btn-secondary btn-sm" data-modal="printModal" title="Print PDF">
            <i class="fa-solid fa-print"></i> Print
        </button>
    <button class="btn btn-primary" data-modal="addPrivModal">+ New Privilege</button>
</div>

<?php if (empty($privileges)): ?>
<div class="table-wrap">
    <div class="empty-state">
        <div class="empty-icon">🎫</div>
        <h3>No privileges yet</h3>
        <p>Create a privilege like "Relative (2% off)" or "Staff (10% off)" and assign it to customers.</p>
    </div>
</div>
<?php else: ?>
<div class="table-wrap">
    <table>
        <thead>
            <tr>
                <th>Name</th>
                <th>Discount</th>
                <th>Description</th>
                <th>Customers</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($privileges as $priv): ?>
        <tr>
            <td style="font-weight:600"><?= e($priv['name']) ?></td>
            <td>
                <span class="badge badge-green">
                    <?php if ($priv['discount_type'] === 'percent'): ?>
                        <?= $priv['discount_value'] ?>% off
                    <?php else: ?>
                        ৳<?= number_format($priv['discount_value'], 2) ?> off
                    <?php endif; ?>
                </span>
            </td>
            <td class="td-muted"><?= $priv['description'] ? e($priv['description']) : '—' ?></td>
            <td class="td-muted"><?= $priv['customer_count'] ?> customer<?= $priv['customer_count'] != 1 ? 's' : '' ?></td>
            <td style="white-space:nowrap">
                <button class="btn btn-sm btn-secondary"
                        onclick="openEdit(<?= htmlspecialchars(json_encode($priv), ENT_QUOTES) ?>)">Edit</button>
                <form method="POST" action="/books/<?= $book['id'] ?>/privileges/<?= $priv['id'] ?>/delete"
                      style="display:inline"
                      data-confirm="Delete &quot;<?= e($priv['name']) ?>&quot;? Customers with this privilege will lose their discount.">
                    <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                    <button class="btn btn-sm btn-danger">Delete</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- HOW IT WORKS -->
<div class="card" style="margin-top:20px;background:var(--brand-light);border-color:var(--brand)">
    <p class="card-title" style="color:var(--brand)">How privileges work</p>
    <p style="font-size:13px;color:var(--text-muted);line-height:1.7">
        Assign a privilege to a customer on their profile page. When you create an invoice for that customer,
        the discount is shown automatically in the invoice summary — you can apply it manually to the discount field.
        A future update will apply it automatically.
    </p>
</div>

<!-- ADD MODAL -->
<div class="modal-backdrop" id="addPrivModal">
    <div class="modal">
        <div class="modal-title">New Privilege</div>
        <form method="POST" action="/books/<?= $book['id'] ?>/privileges/add">
            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
            <div class="form-grid" style="gap:12px">
                <div class="form-group full">
                    <label>Name *</label>
                    <input type="text" name="name" placeholder="e.g. Relative, Staff, VIP" required>
                </div>
                <div class="form-group">
                    <label>Discount type</label>
                    <select name="discount_type" id="add_dtype" onchange="toggleDiscLabel('add')">
                        <option value="percent">Percentage (%)</option>
                        <option value="fixed">Fixed amount (৳)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label id="add_dlabel">Discount value (%)</label>
                    <input type="number" name="discount_value" value="0" min="0" step="0.01" placeholder="e.g. 5">
                </div>
                <div class="form-group full">
                    <label>Description (optional)</label>
                    <textarea name="description" placeholder="e.g. Family and relatives of the owner" style="min-height:56px"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-close-modal>Cancel</button>
                <button type="submit" class="btn btn-primary">Save Privilege</button>
            </div>
        </form>
    </div>
</div>

<!-- EDIT MODAL -->
<div class="modal-backdrop" id="editPrivModal">
    <div class="modal">
        <div class="modal-title">Edit Privilege</div>
        <form method="POST" id="editPrivForm">
            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
            <div class="form-grid" style="gap:12px">
                <div class="form-group full">
                    <label>Name *</label>
                    <input type="text" name="name" id="ep_name" required>
                </div>
                <div class="form-group">
                    <label>Discount type</label>
                    <select name="discount_type" id="ep_dtype" onchange="toggleDiscLabel('ep')">
                        <option value="percent">Percentage (%)</option>
                        <option value="fixed">Fixed amount (৳)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label id="ep_dlabel">Discount value</label>
                    <input type="number" name="discount_value" id="ep_dvalue" min="0" step="0.01">
                </div>
                <div class="form-group full">
                    <label>Description</label>
                    <textarea name="description" id="ep_desc" style="min-height:56px"></textarea>
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
function toggleDiscLabel(prefix) {
    const type  = document.getElementById(prefix + '_dtype').value;
    const label = document.getElementById(prefix + '_dlabel');
    label.textContent = type === 'percent' ? 'Discount value (%)' : 'Discount value (৳)';
}

function openEdit(p) {
    document.getElementById('ep_name').value   = p.name;
    document.getElementById('ep_dtype').value  = p.discount_type;
    document.getElementById('ep_dvalue').value = p.discount_value;
    document.getElementById('ep_desc').value   = p.description || '';
    document.getElementById('editPrivForm').action =
        '/books/<?= $book['id'] ?>/privileges/' + p.id + '/edit';
    toggleDiscLabel('ep');
    document.getElementById('editPrivModal').classList.add('open');
}
</script>

<?php
$content = ob_get_clean();
ob_start();
$printCategory = 'privileges';
require BASE_PATH . '/views/partials/print-modal.php';
$content .= ob_get_clean();
require BASE_PATH . '/views/partials/layout.php'; ?>
