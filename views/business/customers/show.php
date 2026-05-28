<?php
$pageTitle = e($customer['name']) . ' — Byabsayee';

$assignedPrivileges = \App\Helpers\Database::query(
    'SELECT privilege_id FROM customer_privilege_assignments WHERE customer_id=?',
    [$customer['id']]
);
$assignedIds = array_column($assignedPrivileges, 'privilege_id');
$assignedPrivDetails = [];
foreach ($assignedIds as $pid) {
    $p = \App\Helpers\Database::row('SELECT * FROM customer_privileges WHERE id=?', [$pid]);
    if ($p) $assignedPrivDetails[] = $p;
}

// Build unified activity rows
$activity = [];
foreach ($invoices as $inv) {
    $due = ($inv['total'] ?? 0) - ($inv['paid'] ?? 0);
    $activity[] = [
        'sort'   => $inv['date'] ?? $inv['created_at'],
        'type'   => 'invoice',
        'ref'    => $inv['invoice_no'],
        'date'   => $inv['date'],
        'status' => $inv['status'],
        'amount' => $inv['total'],
        'due'    => $due,
        'url'    => '/books/'.$book['id'].'/invoices/'.$inv['id'],
        'raw'    => $inv,
    ];
}
foreach ($dues as $d) {
    $remaining = ($d['amount']??0) - ($d['paid_amount']??0);
    $activity[] = [
        'sort'   => $d['created_at'],
        'type'   => 'due',
        'ref'    => $d['title'],
        'date'   => $d['due_date'] ?? $d['created_at'],
        'status' => $d['status'] ?? 'unpaid',
        'amount' => $d['amount'] ?? 0,
        'due'    => $remaining,
        'url'    => '/books/'.$book['id'].'/dues',
        'raw'    => $d,
    ];
}
foreach ($returns as $r) {
    $activity[] = [
        'sort'   => $r['date'] ?? $r['created_at'],
        'type'   => 'return',
        'ref'    => $r['return_no'] ?? '#'.$r['id'],
        'date'   => $r['date'],
        'status' => 'returned',
        'amount' => $r['total_refund'],
        'due'    => 0,
        'url'    => '/books/'.$book['id'].'/returns/'.$r['id'],
        'raw'    => $r,
    ];
}
usort($activity, fn($a,$b) => strcmp($b['sort']??'', $a['sort']??''));

ob_start();
?>

<div class="page-header">
    <div class="page-header-left">
        <div class="breadcrumb">
            <a href="/books/<?= $book['id'] ?>">Dashboard</a> <span>›</span>
            <a href="/books/<?= $book['id'] ?>/customers">Customers</a> <span>›</span>
            <span><?= e($customer['name']) ?></span>
        </div>
        <h1><?= e($customer['name']) ?></h1>
        <div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:4px">
            <?php if ($customer['points'] > 0): ?><span class="badge badge-amber"><?= $customer['points'] ?> pts</span><?php endif; ?>
            <?php foreach ($assignedPrivDetails as $priv): ?>
            <span class="badge badge-green"><?= e($priv['name']) ?> — <?= $priv['discount_type']==='percent' ? $priv['discount_value'].'%' : '৳'.number_format($priv['discount_value'],2) ?> off</span>
            <?php endforeach; ?>
        </div>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
        <a href="/books/<?= $book['id'] ?>/invoices/create?type=sale&customer_id=<?= $customer['id'] ?>" class="btn btn-primary">+ New Invoice</a>
        <button class="btn btn-secondary" data-modal="editCustomerModal">Edit</button>
        <form method="POST" action="/books/<?= $book['id'] ?>/customers/<?= $customer['id'] ?>/delete" data-confirm="Delete <?= e($customer['name']) ?>?">
            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
            <button class="btn btn-danger">Delete</button>
        </form>
    </div>
</div>

<div class="stat-grid" style="max-width:700px;grid-template-columns:repeat(4,1fr)">
    <div class="stat-card"><div class="stat-label">Total Billed</div><div class="stat-value brand"><?= format_money($totals['total_billed']) ?></div></div>
    <div class="stat-card"><div class="stat-label">Total Paid</div><div class="stat-value green"><?= format_money($totals['total_paid']) ?></div></div>
    <div class="stat-card"><div class="stat-label">Outstanding</div><div class="stat-value <?= $totals['total_due']>0?'red':'green' ?>"><?= format_money($totals['total_due']) ?></div></div>
    <div class="stat-card"><div class="stat-label">Loyalty Points</div><div class="stat-value" style="color:var(--accent)"><?= $customer['points'] ?></div></div>
</div>

<div style="display:grid;grid-template-columns:280px 1fr;gap:16px;align-items:start">

    <!-- LEFT -->
    <div style="display:flex;flex-direction:column;gap:12px">
        <div class="card">
            <p class="card-title">Contact Details</p>
            <div style="font-size:13px;display:flex;flex-direction:column;gap:7px">
                <?php if ($customer['phone']): ?><div><span style="color:var(--text-muted)">Phone:</span> <?= e($customer['phone']) ?></div><?php endif; ?>
                <?php if ($customer['email']): ?><div><span style="color:var(--text-muted)">Email:</span> <?= e($customer['email']) ?></div><?php endif; ?>
                <?php if ($customer['address']): ?><div><span style="color:var(--text-muted)">Address:</span> <?= e($customer['address']) ?></div><?php endif; ?>
                <?php if ($customer['notes']): ?><div><span style="color:var(--text-muted)">Notes:</span> <?= e($customer['notes']) ?></div><?php endif; ?>
                <div class="td-muted">Since <?= format_date($customer['created_at']) ?></div>
            </div>
        </div>

        <div class="card">
            <p class="card-title">Privileges &amp; Discounts</p>
            <?php if (empty($privileges)): ?>
                <p style="font-size:13px;color:var(--text-muted);margin-bottom:10px">No privileges defined.</p>
                <a href="/books/<?= $book['id'] ?>/privileges" class="btn btn-sm btn-secondary">Create Privileges &rarr;</a>
            <?php else: ?>
            <form method="POST" action="/books/<?= $book['id'] ?>/customers/<?= $customer['id'] ?>/privileges">
                <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                <div style="display:flex;flex-direction:column;gap:8px;margin-bottom:12px">
                <?php foreach ($privileges as $priv): ?>
                    <label style="display:flex;align-items:center;gap:10px;cursor:pointer;font-size:13px;padding:7px 10px;border-radius:8px;border:1.5px solid <?= in_array($priv['id'],$assignedIds)?'var(--brand)':'var(--border)' ?>;background:<?= in_array($priv['id'],$assignedIds)?'var(--brand-light)':'transparent' ?>">
                        <input type="checkbox" name="privilege_ids[]" value="<?= $priv['id'] ?>" <?= in_array($priv['id'],$assignedIds)?'checked':'' ?>
                               onchange="this.closest('label').style.borderColor=this.checked?'var(--brand)':'var(--border)';this.closest('label').style.background=this.checked?'var(--brand-light)':'transparent'"
                               style="width:16px;height:16px;accent-color:var(--brand)">
                        <div>
                            <div style="font-weight:500"><?= e($priv['name']) ?></div>
                            <div style="font-size:11px;color:var(--text-muted)"><?= $priv['discount_type']==='percent' ? $priv['discount_value'].'% discount' : '৳'.number_format($priv['discount_value'],2).' fixed' ?></div>
                        </div>
                    </label>
                <?php endforeach; ?>
                </div>
                <button type="submit" class="btn btn-sm btn-primary">Save</button>
            </form>
            <?php endif; ?>
        </div>

        <div class="card">
            <p class="card-title">Loyalty Points</p>
            <div style="font-size:28px;font-weight:700;color:var(--accent)"><?= $customer['points'] ?></div>
            <div style="font-size:12px;color:var(--text-muted);margin-top:2px">1 point per ৳100 · 1 point = ৳1 discount</div>
        </div>
    </div>

    <!-- RIGHT: unified activity table -->
    <div>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;flex-wrap:wrap;gap:8px">
            <div style="display:flex;gap:4px;flex-wrap:wrap" id="activityFilters">
                <button class="filter-btn active" onclick="filterActivity('all',this)">
                    All <span class="badge badge-gray" style="margin-left:4px"><?= count($activity) ?></span>
                </button>
                <button class="filter-btn" onclick="filterActivity('invoice',this)">
                    <i class="fa-solid fa-file-invoice"></i> Invoices <span class="badge badge-gray" style="margin-left:4px"><?= count($invoices) ?></span>
                </button>
                <button class="filter-btn" onclick="filterActivity('due',this)">
                    <i class="fa-solid fa-hand-holding-dollar"></i> Dues <span class="badge badge-gray" style="margin-left:4px"><?= count($dues) ?></span>
                </button>
                <button class="filter-btn" onclick="filterActivity('return',this)">
                    <i class="fa-solid fa-rotate-left"></i> Returns <span class="badge badge-gray" style="margin-left:4px"><?= count($returns) ?></span>
                </button>
            </div>
            <a href="/books/<?= $book['id'] ?>/returns/create?type=sales_return&customer_id=<?= $customer['id'] ?>" class="btn btn-sm btn-secondary">+ Return</a>
        </div>

        <?php if (empty($activity)): ?>
        <div class="table-wrap"><div class="empty-state" style="padding:30px">
            <p>No activity yet.</p>
            <a href="/books/<?= $book['id'] ?>/invoices/create?type=sale&customer_id=<?= $customer['id'] ?>" class="btn btn-primary" style="margin-top:10px">+ Create Invoice</a>
        </div></div>
        <?php else: ?>
        <div class="table-wrap">
            <table id="activityTable">
                <thead><tr>
                    <th>Type</th><th>Reference</th><th>Date</th><th>Status</th>
                    <th style="text-align:right">Amount</th><th style="text-align:right">Due / Refund</th><th></th>
                </tr></thead>
                <tbody>
                <?php foreach ($activity as $row):
                    $typeColor = ['invoice'=>'blue','due'=>'amber','return'=>'red'][$row['type']] ?? 'gray';
                    $typeIcon  = ['invoice'=>'fa-file-invoice','due'=>'fa-hand-holding-dollar','return'=>'fa-rotate-left'][$row['type']] ?? 'fa-circle';

                    $statusColors = [
                        'draft'=>'gray','sent'=>'blue','partial'=>'amber','paid'=>'green',
                        'overdue'=>'red','cancelled'=>'gray',
                        'unpaid'=>'amber','returned'=>'blue',
                    ];
                    $sc = $statusColors[$row['status']] ?? 'gray';
                ?>
                <tr data-type="<?= $row['type'] ?>">
                    <td>
                        <span class="badge badge-<?= $typeColor ?>" style="font-size:10px">
                            <i class="fa-solid <?= $typeIcon ?>"></i> <?= ucfirst($row['type']) ?>
                        </span>
                    </td>
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

<!-- EDIT MODAL -->
<div class="modal-backdrop" id="editCustomerModal">
    <div class="modal">
        <div class="modal-title">Edit Customer</div>
        <form method="POST" action="/books/<?= $book['id'] ?>/customers/<?= $customer['id'] ?>/edit">
            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
            <div class="form-grid" style="gap:12px">
                <div class="form-group full"><label>Name *</label><input type="text" name="name" value="<?= e($customer['name']) ?>" required></div>
                <div class="form-group"><label>Phone</label><input type="text" name="phone" value="<?= e($customer['phone']??'') ?>"></div>
                <div class="form-group"><label>Email</label><input type="email" name="email" value="<?= e($customer['email']??'') ?>"></div>
                <div class="form-group full"><label>Address</label><textarea name="address" style="min-height:56px"><?= e($customer['address']??'') ?></textarea></div>
                <div class="form-group full"><label>Notes</label><textarea name="notes" style="min-height:48px"><?= e($customer['notes']??'') ?></textarea></div>
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
