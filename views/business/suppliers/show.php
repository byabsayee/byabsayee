<?php
$pageTitle = e($supplier['name']) . ' — Byabsayee';

// Build unified activity
$activity = [];
foreach ($invoices as $inv) {
    $due = ($inv['total']??0) - ($inv['paid']??0);
    $activity[] = ['sort'=>$inv['date']??$inv['created_at'],'type'=>'purchase','ref'=>$inv['invoice_no'],'date'=>$inv['date'],'status'=>$inv['status'],'amount'=>$inv['total'],'due'=>$due,'url'=>'/books/'.$book['id'].'/invoices/'.$inv['id']];
}
foreach ($debts as $d) {
    $remaining = ($d['amount']??0) - ($d['paid_amount']??0);
    $activity[] = ['sort'=>$d['created_at'],'type'=>'debt','ref'=>$d['title'],'date'=>$d['due_date']??$d['created_at'],'status'=>$d['status']??'unpaid','amount'=>$d['amount']??0,'due'=>$remaining,'url'=>'/books/'.$book['id'].'/debts'];
}
foreach ($returns as $r) {
    $activity[] = ['sort'=>$r['date']??$r['created_at'],'type'=>'return','ref'=>$r['return_no']??'#'.$r['id'],'date'=>$r['date'],'status'=>'returned','amount'=>$r['total_refund'],'due'=>0,'url'=>'/books/'.$book['id'].'/returns/'.$r['id']];
}
usort($activity, fn($a,$b) => strcmp($b['sort']??'',$a['sort']??''));

ob_start();
?>
<div class="page-header">
    <div class="page-header-left">
        <div class="breadcrumb">
            <a href="/books/<?= $book['id'] ?>">Books</a> <span>›</span>
            <a href="/books/<?= $book['id'] ?>/suppliers">Suppliers</a> <span>›</span>
            <span><?= e($supplier['name']) ?></span>
        </div>
        <h1><?= e($supplier['name']) ?></h1>
        <?php if ($supplier['company']): ?><p style="color:var(--text-muted);margin-top:2px"><?= e($supplier['company']) ?></p><?php endif; ?>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
        <a href="/books/<?= $book['id'] ?>/invoices/create?type=purchase&supplier_id=<?= $supplier['id'] ?>" class="btn btn-primary">+ New Purchase</a>
        <button class="btn btn-secondary" data-modal="editSupplierModal">Edit</button>
        <form method="POST" action="/books/<?= $book['id'] ?>/suppliers/<?= $supplier['id'] ?>/delete" data-confirm="Delete <?= e($supplier['name']) ?>?">
            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
            <button class="btn btn-danger">Delete</button>
        </form>
    </div>
</div>

<div class="stat-grid" style="max-width:560px;grid-template-columns:repeat(3,1fr)">
    <div class="stat-card"><div class="stat-label">Total Purchased</div><div class="stat-value brand"><?= format_money($totals['total_billed']) ?></div></div>
    <div class="stat-card"><div class="stat-label">Total Paid</div><div class="stat-value green"><?= format_money($totals['total_paid']) ?></div></div>
    <div class="stat-card"><div class="stat-label">Outstanding</div><div class="stat-value <?= $totals['total_due']>0?'red':'green' ?>"><?= format_money($totals['total_due']) ?></div></div>
</div>

<div style="display:grid;grid-template-columns:280px 1fr;gap:16px;align-items:start">
    <div class="card">
        <p class="card-title">Contact Details</p>
        <div style="font-size:13px;display:flex;flex-direction:column;gap:8px">
            <?php if ($supplier['phone']): ?><div><span style="color:var(--text-muted)">Phone:</span> <?= e($supplier['phone']) ?></div><?php endif; ?>
            <?php if ($supplier['email']): ?><div><span style="color:var(--text-muted)">Email:</span> <?= e($supplier['email']) ?></div><?php endif; ?>
            <?php if ($supplier['address']): ?><div><span style="color:var(--text-muted)">Address:</span> <?= e($supplier['address']) ?></div><?php endif; ?>
            <?php if ($supplier['notes']): ?><div><span style="color:var(--text-muted)">Notes:</span> <?= e($supplier['notes']) ?></div><?php endif; ?>
            <div class="td-muted">Since <?= format_date($supplier['created_at']) ?></div>
        </div>
    </div>

    <div>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;flex-wrap:wrap;gap:8px">
            <div style="display:flex;gap:4px;flex-wrap:wrap" id="activityFilters">
                <button class="filter-btn active" onclick="filterActivity('all',this)">
                    All <span class="badge badge-gray" style="margin-left:4px"><?= count($activity) ?></span>
                </button>
                <button class="filter-btn" onclick="filterActivity('purchase',this)">
                    <i class="fa-solid fa-cart-shopping"></i> Purchases <span class="badge badge-gray" style="margin-left:4px"><?= count($invoices) ?></span>
                </button>
                <button class="filter-btn" onclick="filterActivity('debt',this)">
                    <i class="fa-solid fa-file-circle-minus"></i> Debts <span class="badge badge-gray" style="margin-left:4px"><?= count($debts) ?></span>
                </button>
                <button class="filter-btn" onclick="filterActivity('return',this)">
                    <i class="fa-solid fa-rotate-left"></i> Returns <span class="badge badge-gray" style="margin-left:4px"><?= count($returns) ?></span>
                </button>
            </div>
            <a href="/books/<?= $book['id'] ?>/returns/create?type=purchase_return&supplier_id=<?= $supplier['id'] ?>" class="btn btn-sm btn-secondary">+ Return</a>
        </div>

        <?php if (empty($activity)): ?>
        <div class="table-wrap"><div class="empty-state" style="padding:30px"><p>No activity yet.</p></div></div>
        <?php else: ?>
        <div class="table-wrap">
            <table id="activityTable">
                <thead><tr><th>Type</th><th>Reference</th><th>Date</th><th>Status</th><th style="text-align:right">Amount</th><th style="text-align:right">Due / Refund</th><th></th></tr></thead>
                <tbody>
                <?php
                $typeColors = ['purchase'=>'blue','debt'=>'amber','return'=>'red'];
                $typeIcons  = ['purchase'=>'fa-cart-shopping','debt'=>'fa-file-circle-minus','return'=>'fa-rotate-left'];
                $statusColors = ['draft'=>'gray','sent'=>'blue','partial'=>'amber','paid'=>'green','overdue'=>'red','cancelled'=>'gray','unpaid'=>'amber','returned'=>'blue'];
                foreach ($activity as $row):
                    $tc = $typeColors[$row['type']] ?? 'gray';
                    $ti = $typeIcons[$row['type']]  ?? 'fa-circle';
                    $sc = $statusColors[$row['status']] ?? 'gray';
                ?>
                <tr data-type="<?= $row['type'] ?>">
                    <td><span class="badge badge-<?= $tc ?>" style="font-size:10px"><i class="fa-solid <?= $ti ?>"></i> <?= ucfirst($row['type']) ?></span></td>
                    <td style="font-weight:500"><?= e($row['ref']) ?></td>
                    <td class="td-muted"><?= $row['date'] ? format_date($row['date']) : '—' ?></td>
                    <td><span class="badge badge-<?= $sc ?>"><?= ucfirst($row['status']) ?></span></td>
                    <td style="text-align:right" class="td-amount"><?= format_money($row['amount']) ?></td>
                    <td style="text-align:right" class="td-amount <?= $row['type']==='return'?'out':($row['due']>0?'out':'') ?>">
                        <?= $row['type']==='return' ? format_money($row['amount']) : format_money($row['due']) ?>
                    </td>
                    <td><a href="<?= $row['url'] ?>" class="btn btn-sm btn-secondary">View</a></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="modal-backdrop" id="editSupplierModal">
    <div class="modal">
        <div class="modal-title">Edit Supplier</div>
        <form method="POST" action="/books/<?= $book['id'] ?>/suppliers/<?= $supplier['id'] ?>/edit">
            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
            <div class="form-grid" style="gap:12px">
                <div class="form-group full"><label>Name *</label><input type="text" name="name" value="<?= e($supplier['name']) ?>" required></div>
                <div class="form-group full"><label>Company</label><input type="text" name="company" value="<?= e($supplier['company']??'') ?>"></div>
                <div class="form-group"><label>Phone</label><input type="text" name="phone" value="<?= e($supplier['phone']??'') ?>"></div>
                <div class="form-group"><label>Email</label><input type="email" name="email" value="<?= e($supplier['email']??'') ?>"></div>
                <div class="form-group full"><label>Address</label><textarea name="address" style="min-height:56px"><?= e($supplier['address']??'') ?></textarea></div>
                <div class="form-group full"><label>Notes</label><textarea name="notes" style="min-height:48px"><?= e($supplier['notes']??'') ?></textarea></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-close-modal>Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function filterActivity(type, btn) {
    document.querySelectorAll('#activityFilters .filter-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    document.querySelectorAll('#activityTable tbody tr').forEach(row => {
        row.style.display = (type === 'all' || row.dataset.type === type) ? '' : 'none';
    });
}
</script>
<?php $content = ob_get_clean(); require BASE_PATH . '/views/partials/layout.php'; ?>
