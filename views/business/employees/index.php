<?php
$pageTitle = 'Employees — ' . e($book['name']);
$isOwner   = $book['user_id'] === auth()['id'];

// Friendly module labels
$moduleLabels = [
    // Financial
    'invoices'            => '🧾 Invoices',
    'funds'               => '🏦 Funds',
    'expenses'            => '🧾 Expenses',
    'dues'                => '📥 Dues',
    'debts'               => '📤 Debts',
    // Inventory & Sales
    'products'            => '📦 Products',
    'returns'             => '↩ Returns',
    'coupons'             => '🎟 Coupons',
    'deliveries'          => '🚚 Deliveries',
    // Contacts
    'customers'           => '👥 Customers',
    'suppliers'           => '🤝 Suppliers',
    'contacts'            => '📋 Contacts',
    // People
    'employees'           => '🪪 Employees',
    // Admin
    'reports'             => '📊 Reports',
    'logs'                => '🕐 Activity Log',
    'book_settings'       => '⚙ Book Settings',
];
$actionLabels = [
    'view'               => 'View',
    'create'             => 'Create',
    'edit'               => 'Edit',
    'delete'             => 'Delete',
    'adjust_stock'       => 'Adjust Stock',
    'pay'                => 'Pay',
    'invite'             => 'Invite',
    'record_payment'     => 'Record Payment',
    'manage_designations'=> 'Manage Designations',
];

// Collect existing departments for datalist autocomplete
$_empDepts = array_unique(array_filter(array_column($employees, 'department')));

ob_start();
?>

<div class="page-header">
    <div class="page-header-left">
        <div class="breadcrumb">
            <a href="/books/<?= $book['id'] ?>">Dashboard</a> <span>›</span>
            <span>Employees</span>
        </div>
        <h1><i class="fa-solid fa-id-badge" style="color:var(--brand)"></i> Employees</h1>
        <p><?= count($employees) ?> employee<?= count($employees) !== 1 ? 's' : '' ?></p>
    </div>
    <?php if ($isOwner): ?>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
        <button class="btn btn-secondary" data-modal="manageDesignationsModal">
            <i class="fa-solid fa-sitemap"></i> Designations
        </button>
        <button class="btn btn-primary" data-modal="inviteModal">
            <i class="fa-solid fa-envelope"></i> Invite User
        </button>
        <button class="btn btn-secondary btn-sm" data-modal="printModal" title="Print PDF">
            <i class="fa-solid fa-print"></i> Print
        </button>
        <button class="btn btn-secondary" data-modal="addEmployeeModal">
            <i class="fa-solid fa-plus"></i> Add Employee
        </button>
    </div>
    <?php endif; ?>
</div>

<!-- PENDING INVITATIONS BANNER -->
<?php if ($isOwner && !empty($pending_invitations)): ?>
<div class="card" style="border-left:4px solid var(--brand);margin-bottom:16px">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
        <p class="card-title" style="margin:0"><i class="fa-solid fa-clock" style="color:var(--accent)"></i> Pending Invitations (<?= count($pending_invitations) ?>)</p>
    </div>
    <div class="table-wrap" style="margin:0">
        <table>
            <thead><tr><th>Email</th><th>Designation</th><th>Sent</th><th>Expires</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($pending_invitations as $inv): ?>
            <tr>
                <td><?= e($inv['email']) ?></td>
                <td><?= $inv['designation_name'] ? '<span class="badge badge-blue">'.e($inv['designation_name']).'</span>' : '<span class="td-muted">—</span>' ?></td>
                <td class="td-muted"><?= format_date($inv['created_at']) ?></td>
                <td class="td-muted <?= strtotime($inv['expires_at']) < time() ? 'red' : '' ?>"><?= format_date($inv['expires_at']) ?></td>
                <td>
                    <form method="POST" action="/books/<?= $book['id'] ?>/employees/invitations/<?= $inv['id'] ?>/cancel"
                          data-confirm="Cancel this invitation to <?= e($inv['email']) ?>?">
                        <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                        <button class="btn btn-sm btn-danger">Cancel</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- LM Controls -->
<div class="lm-controls">
    <div class="lm-search-wrap">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="text" class="lm-search" id="empTableSearch" placeholder="Search name, designation, email…">
        <button class="lm-search-clear" id="empTableClear"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <select class="lm-select" id="empTableSort">
        <option value="az">Name A–Z</option>
        <option value="za">Name Z–A</option>
        <option value="amt-desc">Salary High–Low</option>
        <option value="amt-asc">Salary Low–High</option>
    </select>
</div>
<?php
// Build unique designations and departments for filter pills
$_empDesigs = array_unique(array_filter(array_column($employees, 'designation_name')));
sort($_empDesigs); sort($_empDepts);
?>
<div class="lm-filter-pills">
    <span style="font-size:12px;font-weight:600;color:var(--text-muted)">Status:</span>
    <button class="btn btn-sm btn-primary" data-emp-status="all">All</button>
    <button class="btn btn-sm btn-secondary" data-emp-status="active">Active</button>
    <button class="btn btn-sm btn-secondary" data-emp-status="inactive">Inactive</button>
    <button class="btn btn-sm btn-secondary" data-emp-status="terminated">Terminated</button>
</div>
<?php if (!empty($_empDesigs)): ?>
<div class="lm-filter-pills">
    <span style="font-size:12px;font-weight:600;color:var(--text-muted)">Designation:</span>
    <button class="btn btn-sm btn-primary" data-emp-desig="all">All</button>
    <?php foreach ($_empDesigs as $_d): ?>
    <button class="btn btn-sm btn-secondary" data-emp-desig="<?= e($_d) ?>"><?= e($_d) ?></button>
    <?php endforeach; ?>
</div>
<?php endif; ?>
<?php if (!empty($_empDepts)): ?>
<div class="lm-filter-pills">
    <span style="font-size:12px;font-weight:600;color:var(--text-muted)">Department:</span>
    <button class="btn btn-sm btn-primary" data-emp-dept="all">All</button>
    <?php foreach ($_empDepts as $_dp): ?>
    <button class="btn btn-sm btn-secondary" data-emp-dept="<?= e($_dp) ?>"><?= e($_dp) ?></button>
    <?php endforeach; ?>
</div>
<?php endif; ?>
<!-- EMPLOYEES TABLE -->
<?php if (empty($employees)): ?>
<div class="table-wrap">
    <div class="empty-state">
        <div class="empty-icon">👥</div>
        <h3>No employees yet</h3>
        <p>Add employees or invite Byabsayee users to collaborate on this book.</p>
        <?php if ($isOwner): ?>
        <div style="display:flex;gap:8px;justify-content:center;margin-top:12px">
            <button class="btn btn-primary" data-modal="inviteModal">
                <i class="fa-solid fa-envelope"></i> Invite User
            </button>
            <button class="btn btn-secondary" data-modal="addEmployeeModal">
                <i class="fa-solid fa-plus"></i> Add Employee
            </button>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php else: ?>
<div class="table-wrap">
    <table id="empTable">
        <thead>
            <tr>
                <th>Emp ID</th>
                <th>Name</th>
                <th>Designation</th>
                <th>Department</th>
                <th>Contact</th>
                <th>Status</th>
                <th>App Access</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($employees as $emp): ?>
        <?php
            $sc = ['active'=>'green','inactive'=>'gray','terminated'=>'red'][$emp['status']] ?? 'gray';
            $hasLogin = !empty($emp['user_id']);
        ?>
        <tr>
            <td class="td-muted" style="font-size:11px;font-family:monospace;white-space:nowrap">
                <?= $emp['emp_code'] ? e($emp['emp_code']) : '<span style="color:#ccc">—</span>' ?>
            </td>
            <td>
                <a href="/books/<?= $book['id'] ?>/employees/<?= $emp['id'] ?>" style="font-weight:500;color:var(--brand);text-decoration:none">
                    <?= e($emp['name']) ?>
                </a>
                <?php if ($hasLogin): ?>
                    <span class="badge badge-green" style="font-size:10px;margin-left:4px">Has Login</span>
                <?php endif; ?>
            </td>
            <td>
                <?php if ($emp['user_id'] == $book['user_id']): ?>
                    <span class="badge badge-owner"><i class="fa-solid fa-crown"></i> Owner</span>
                    <?php if ($emp['designation_name'] && strtolower($emp['designation_name']) !== 'owner'): ?>
                    <span class="badge badge-blue" style="margin-left:4px"><?= e($emp['designation_name']) ?></span>
                    <?php endif; ?>
                <?php elseif ($emp['designation_name']): ?>
                    <span class="badge badge-blue"><?= e($emp['designation_name']) ?></span>
                <?php else: ?>
                    <span class="td-muted">—</span>
                <?php endif; ?>
            </td>
            <td class="td-muted"><?= e($emp['department'] ?? '—') ?></td>
            <td class="td-muted">
                <?php if ($emp['phone']): ?><div><?= e($emp['phone']) ?></div><?php endif; ?>
                <?php if ($emp['email']): ?><div style="font-size:11px"><?= e($emp['email']) ?></div><?php endif; ?>
            </td>
            <td><span class="badge badge-<?= $sc ?>"><?= ucfirst($emp['status']) ?></span></td>
            <td>
                <?php if ($hasLogin): ?>
                    <span class="badge badge-green"><i class="fa-solid fa-check"></i> Active</span>
                <?php else: ?>
                    <span class="badge badge-gray">No account</span>
                <?php endif; ?>
            </td>
            <td>
                <a href="/books/<?= $book['id'] ?>/employees/<?= $emp['id'] ?>" class="btn btn-sm btn-secondary">View</a>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<div id="empTablePager"></div>
<?php endif; ?>

<!-- ========== MODALS ========== -->

<!-- INVITE MODAL -->
<?php if ($isOwner): ?>
<div class="modal-backdrop" id="inviteModal">
    <div class="modal" style="max-width:640px">
        <div class="modal-title"><i class="fa-solid fa-envelope" style="color:var(--brand)"></i> Invite User to Book</div>
        <form method="POST" action="/books/<?= $book['id'] ?>/employees/invite">
            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
            <p style="font-size:13px;color:var(--text-muted);margin-bottom:16px">
                The user must already have a Byabsayee account. They will receive an in-app notification and an email to accept or decline.
            </p>
            <div class="form-grid" style="gap:12px;margin-bottom:16px">
                <div class="form-group full">
                    <label>User Email *</label>
                    <input type="email" name="email" required placeholder="user@email.com">
                </div>
                <div class="form-group">
                    <label>Designation (Optional)</label>
                    <select name="designation_id" id="inviteDesigSelect" onchange="loadDesigPerms(this.value,'invite')">
                        <option value="">— Select or set manually —</option>
                        <?php foreach ($designations as $d): ?>
                        <option value="<?= $d['id'] ?>"><?= e($d['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Designation Name <span style="color:var(--text-muted)">(custom)</span></label>
                    <input type="text" name="designation_name" id="inviteDesigName" placeholder="e.g. Cashier, Manager">
                </div>
            </div>

            <!-- PERMISSION MATRIX -->
            <div style="margin-bottom:12px">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
                    <p style="font-weight:600;font-size:13px;margin:0">Permissions</p>
                    <div style="display:flex;gap:8px">
                        <button type="button" onclick="toggleAllPerms('invite',true)"  class="btn btn-sm btn-secondary">All</button>
                        <button type="button" onclick="toggleAllPerms('invite',false)" class="btn btn-sm btn-secondary">None</button>
                    </div>
                </div>
                <div class="perm-grid" id="invite-perm-grid">
                <?php foreach ($modules as $mod => $actions): ?>
                <div class="perm-row">
                    <div class="perm-module"><?= $moduleLabels[$mod] ?? $mod ?></div>
                    <div class="perm-actions">
                    <?php foreach ($actions as $action): ?>
                        <label class="perm-check">
                            <input type="checkbox" name="perm[<?= $mod ?>][<?= $action ?>]"
                                   class="invite-perm" data-mod="<?= $mod ?>" data-action="<?= $action ?>">
                            <?= $actionLabels[$action] ?? $action ?>
                        </label>
                    <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-close-modal>Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-paper-plane"></i> Send Invitation</button>
            </div>
        </form>
    </div>
</div>

<!-- ADD EMPLOYEE (offline) MODAL -->
<div class="modal-backdrop" id="addEmployeeModal">
    <div class="modal">
        <div class="modal-title">Add Employee (Offline)</div>
        <form method="POST" action="/books/<?= $book['id'] ?>/employees/add">
            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
            <div class="form-grid" style="gap:12px">
                <div class="form-group full"><label>Name *</label><input type="text" name="name" required></div>
                <div class="form-group">
                    <label>Designation</label>
                    <select name="designation_id">
                        <option value="">— None —</option>
                        <?php foreach ($designations as $d): ?>
                        <option value="<?= $d['id'] ?>"><?= e($d['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Custom Designation</label>
                    <input type="text" name="designation_name" placeholder="e.g. Cashier">
                </div>
                <div class="form-group"><label>Phone</label><input type="text" name="phone"></div>
                <div class="form-group"><label>Email</label><input type="email" name="email"></div>
                <div class="form-group full"><label>Address</label><textarea name="address" style="min-height:48px"></textarea></div>
                <div class="form-group">
                    <label>Department</label>
                    <input type="text" name="department" list="deptList" autocomplete="off">
                    <datalist id="deptList">
                        <?php foreach ($_empDepts as $dept): ?>
                        <option value="<?= e($dept) ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>
                <div class="form-group"><label>Join Date</label><input type="date" name="join_date"></div>
                <div class="form-group"><label>Salary</label><input type="number" name="salary" step="0.01" min="0"></div>
                <div class="form-group">
                    <label>Salary Type</label>
                    <select name="salary_type">
                        <option value="monthly">Monthly</option>
                        <option value="daily">Daily</option>
                        <option value="hourly">Hourly</option>
                    </select>
                </div>
                <div class="form-group full"><label>Notes</label><textarea name="notes" style="min-height:48px"></textarea></div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-close-modal>Cancel</button>
                <button type="submit" class="btn btn-primary">Add Employee</button>
            </div>
        </form>
    </div>
</div>

<!-- MANAGE DESIGNATIONS MODAL -->
<div class="modal-backdrop" id="manageDesignationsModal">
    <div class="modal" style="max-width:700px">
        <div class="modal-title"><i class="fa-solid fa-sitemap" style="color:var(--brand)"></i> Designations</div>
        <p style="font-size:13px;color:var(--text-muted);margin-bottom:16px">
            Designations are saved permission templates. Assign them to employees for quick setup.
        </p>

        <?php if (!empty($designations)): ?>
        <div style="margin-bottom:20px">
            <p class="section-label">Existing Designations</p>
            <?php foreach ($designations as $d): ?>
            <?php $dp = json_decode($d['permissions'] ?? '{}', true) ?? []; ?>
            <div class="card" style="margin-bottom:8px;padding:12px">
                <div style="display:flex;justify-content:space-between;align-items:center">
                    <div>
                        <span style="font-weight:600"><?= e($d['name']) ?></span>
                        <span class="td-muted" style="margin-left:8px"><?= $d['employee_count'] ?> employee<?= $d['employee_count']!=1?'s':'' ?></span>
                    </div>
                    <div style="display:flex;gap:6px">
                        <button class="btn btn-sm btn-secondary" onclick="openEditDesig(<?= htmlspecialchars(json_encode($d), ENT_QUOTES) ?>)">Edit</button>
                        <form method="POST" action="/books/<?= $book['id'] ?>/employees/designations/<?= $d['id'] ?>/delete"
                              data-confirm="Delete &quot;<?= e($d['name']) ?>&quot; designation?">
                            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                            <button class="btn btn-sm btn-danger">Delete</button>
                        </form>
                    </div>
                </div>
                <div style="margin-top:8px;display:flex;flex-wrap:wrap;gap:4px">
                <?php foreach ($dp as $mod => $actions): ?>
                <?php $granted = array_filter($actions); if (!$granted) continue; ?>
                    <span class="badge badge-green" style="font-size:10px">
                        <?= $moduleLabels[$mod] ?? $mod ?>:
                        <?= implode(', ', array_keys($granted)) ?>
                    </span>
                <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div>
            <p class="section-label">Create New Designation</p>
            <form method="POST" action="/books/<?= $book['id'] ?>/employees/designations/add">
                <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                <div class="form-group" style="margin-bottom:12px">
                    <label>Designation Name *</label>
                    <input type="text" name="name" placeholder="e.g. Cashier, Manager, Accountant" required>
                </div>
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
                    <p style="font-weight:600;font-size:13px;margin:0">Permissions</p>
                    <div style="display:flex;gap:8px">
                        <button type="button" onclick="toggleAllPerms('desig',true)"  class="btn btn-sm btn-secondary">All</button>
                        <button type="button" onclick="toggleAllPerms('desig',false)" class="btn btn-sm btn-secondary">None</button>
                    </div>
                </div>
                <div class="perm-grid" id="desig-perm-grid">
                <?php foreach ($modules as $mod => $actions): ?>
                <div class="perm-row">
                    <div class="perm-module"><?= $moduleLabels[$mod] ?? $mod ?></div>
                    <div class="perm-actions">
                    <?php foreach ($actions as $action): ?>
                        <label class="perm-check">
                            <input type="checkbox" name="perm[<?= $mod ?>][<?= $action ?>]"
                                   class="desig-perm" data-mod="<?= $mod ?>" data-action="<?= $action ?>">
                            <?= $actionLabels[$action] ?? $action ?>
                        </label>
                    <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-close-modal>Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Designation</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- EDIT DESIGNATION MODAL -->
<div class="modal-backdrop" id="editDesigModal">
    <div class="modal" style="max-width:640px">
        <div class="modal-title">Edit Designation</div>
        <form method="POST" action="" id="editDesigForm">
            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
            <div class="form-group" style="margin-bottom:12px">
                <label>Designation Name *</label>
                <input type="text" name="name" id="editDesigName" required>
            </div>
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
                <p style="font-weight:600;font-size:13px;margin:0">Permissions</p>
                <div style="display:flex;gap:8px">
                    <button type="button" onclick="toggleAllPerms('edit-desig',true)"  class="btn btn-sm btn-secondary">All</button>
                    <button type="button" onclick="toggleAllPerms('edit-desig',false)" class="btn btn-sm btn-secondary">None</button>
                </div>
            </div>
            <div class="perm-grid" id="edit-desig-perm-grid">
            <?php foreach ($modules as $mod => $actions): ?>
            <div class="perm-row">
                <div class="perm-module"><?= $moduleLabels[$mod] ?? $mod ?></div>
                <div class="perm-actions">
                <?php foreach ($actions as $action): ?>
                    <label class="perm-check">
                        <input type="checkbox" name="perm[<?= $mod ?>][<?= $action ?>]"
                               class="edit-desig-perm" data-mod="<?= $mod ?>" data-action="<?= $action ?>">
                        <?= $actionLabels[$action] ?? $action ?>
                    </label>
                <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-close-modal>Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>


<script>
(function(){
var allRows=[], statusF='all', desigF='all', deptF='all', searchQ='', sortKey='az', perPage=20, curPage=1;

function init(){
    allRows=Array.from(document.querySelectorAll('#empTable tbody tr'));

    // Search
    var si=document.getElementById('empTableSearch'), sc=document.getElementById('empTableClear');
    if(si){
        si.addEventListener('input',function(){searchQ=this.value.toLowerCase().trim();sc.classList.toggle('visible',searchQ.length>0);curPage=1;render();});
        sc.addEventListener('click',function(){si.value='';searchQ='';sc.classList.remove('visible');curPage=1;render();});
    }

    // Sort
    var ss=document.getElementById('empTableSort');
    if(ss) ss.addEventListener('change',function(){sortKey=this.value;curPage=1;render();});

    // Status pills
    document.querySelectorAll('[data-emp-status]').forEach(function(b){
        b.addEventListener('click',function(){
            statusF=this.getAttribute('data-emp-status');
            document.querySelectorAll('[data-emp-status]').forEach(function(x){x.classList.remove('btn-primary');x.classList.add('btn-secondary');});
            this.classList.add('btn-primary');this.classList.remove('btn-secondary');
            curPage=1;render();
        });
    });

    // Designation pills
    document.querySelectorAll('[data-emp-desig]').forEach(function(b){
        b.addEventListener('click',function(){
            desigF=this.getAttribute('data-emp-desig');
            document.querySelectorAll('[data-emp-desig]').forEach(function(x){x.classList.remove('btn-primary');x.classList.add('btn-secondary');});
            this.classList.add('btn-primary');this.classList.remove('btn-secondary');
            curPage=1;render();
        });
    });

    // Department pills
    document.querySelectorAll('[data-emp-dept]').forEach(function(b){
        b.addEventListener('click',function(){
            deptF=this.getAttribute('data-emp-dept');
            document.querySelectorAll('[data-emp-dept]').forEach(function(x){x.classList.remove('btn-primary');x.classList.add('btn-secondary');});
            this.classList.add('btn-primary');this.classList.remove('btn-secondary');
            curPage=1;render();
        });
    });

    render();
}

function td(r,i){var c=r.querySelectorAll('td')[i];return c?c.textContent.trim():'';}

function render(){
    var f=allRows.filter(function(row){
        if(statusF!=='all'&&row.getAttribute('data-emp-status')!==statusF) return false;
        if(desigF!=='all'&&row.getAttribute('data-emp-desig')!==desigF)   return false;
        if(deptF!=='all'&&row.getAttribute('data-emp-dept')!==deptF)     return false;
        if(searchQ&&row.textContent.toLowerCase().indexOf(searchQ)===-1)   return false;
        return true;
    });

    f.sort(function(a,b){
        if(sortKey==='az')  return td(a,0).localeCompare(td(b,0));
        if(sortKey==='za')  return td(b,0).localeCompare(td(a,0));
        var sa=parseFloat(td(a,5).replace(/[^0-9.]/g,'')||0);
        var sb=parseFloat(td(b,5).replace(/[^0-9.]/g,'')||0);
        if(sortKey==='amt-desc')return sb-sa;
        if(sortKey==='amt-asc') return sa-sb;
        return 0;
    });

    var pp=perPage==='all'?Infinity:parseInt(perPage), total=f.length;
    var tpg=pp===Infinity?1:Math.max(1,Math.ceil(total/pp));
    if(curPage>tpg)curPage=tpg; if(curPage<1)curPage=1;
    var s=pp===Infinity?0:(curPage-1)*pp, e2=pp===Infinity?total:Math.min(s+pp,total);

    var tbody=document.querySelector('#empTable tbody');
    var colC=document.querySelector('#empTable thead tr').children.length;
    while(tbody.firstChild)tbody.removeChild(tbody.firstChild);

    if(f.length===0){
        var nr=document.createElement('tr');nr.className='lm-no-results';
        var nd=document.createElement('td');nd.setAttribute('colspan',colC);
        nd.textContent='No employees match the selected filters.';
        nr.appendChild(nd);tbody.appendChild(nr);
    } else {
        f.slice(s,e2).forEach(function(r){tbody.appendChild(r);});
    }

    renderPager(document.getElementById('empTablePager'),total,tpg,s,e2,pp);
}

function renderPager(el,total,tpg,s,e2,pp){
    if(!el)return;el.innerHTML='';
    var wrap=document.createElement('div');wrap.className='lm-pagination';
    var info=document.createElement('div');info.className='lm-page-info';
    info.textContent=total===0?'No results':pp===Infinity?'All '+total+' employees':'Showing '+(s+1)+'\u2013'+e2+' of '+total;
    wrap.appendChild(info);
    if(tpg>1){
        var pages=document.createElement('div');pages.className='lm-pages';
        function mkB(l,pg){var b=document.createElement('button');b.className='lm-page-btn';if(pg===curPage)b.classList.add('active');b.textContent=l;if(pg)b.addEventListener('click',function(){curPage=pg;render();});return b;}
        if(curPage>1)pages.appendChild(mkB('\u2039',curPage-1));
        var ns=[];if(tpg<=7){for(var i=1;i<=tpg;i++)ns.push(i);}else{ns=[1];if(curPage>3)ns.push('\u2026');for(var i=Math.max(2,curPage-1);i<=Math.min(tpg-1,curPage+1);i++)ns.push(i);if(curPage<tpg-2)ns.push('\u2026');ns.push(tpg);}
        ns.forEach(function(p){var b=mkB(p,p==='\u2026'?0:p);if(p==='\u2026')b.classList.add('lm-ellipsis');pages.appendChild(b);});
        if(curPage<tpg)pages.appendChild(mkB('\u203a',curPage+1));
        wrap.appendChild(pages);
    }
    var ppW=document.createElement('div');ppW.className='lm-per-page-wrap';
    var sl=document.createElement('select');sl.className='lm-select';sl.style.padding='4px 8px';sl.style.margin='0 4px';
    [20,50,100,'all'].forEach(function(v){var o=document.createElement('option');o.value=v;o.textContent=v==='all'?'All':v;if((pp===Infinity&&v==='all')||pp===v)o.selected=true;sl.appendChild(o);});
    sl.addEventListener('change',function(){perPage=sl.value;curPage=1;render();});
    ppW.appendChild(document.createTextNode('Show '));ppW.appendChild(sl);ppW.appendChild(document.createTextNode(' per page'));
    wrap.appendChild(ppW);el.appendChild(wrap);
}

// ── Delete-book permission warning ──────────────────────────────────────────
function wireDeletePermWarning(formEl) {
    if(!formEl) return;
    var cb = formEl.querySelector('input[name="perm[book_settings][delete]"]');
    if(!cb) return;
    var warn = document.createElement('div');
    warn.className = 'delete-perm-warning';
    warn.style.display = 'none';
    warn.innerHTML = '<i class=\'fa-solid fa-triangle-exclamation\'></i>'
        + ' <strong>Warning:</strong> This allows the employee to permanently delete the entire book and all its data. Only grant this to highly trusted people.';
    cb.closest('.perm-check') ? cb.closest('.perm-check').parentNode.appendChild(warn) : cb.parentNode.appendChild(warn);
    cb.addEventListener('change', function(){
        warn.style.display = this.checked ? 'flex' : 'none';
    });
}

// ── toggleAllPerms ────────────────────────────────────────────────────────────
// prefix→modal ID mapping must match actual HTML ids
var _permModalMap = {
    'invite':     'inviteModal',
    'desig':      'manageDesignationsModal',
    'edit-desig': 'editDesigModal',
};
function toggleAllPerms(prefix, val) {
    var modalId = _permModalMap[prefix];
    if (!modalId) return;
    var modal = document.getElementById(modalId);
    if (!modal) return;
    modal.querySelectorAll('input[type="checkbox"][name^="perm["]').forEach(function(cb) {
        cb.checked = val;
        cb.dispatchEvent(new Event('change'));
    });
}

// ── loadDesigPerms ─────────────────────────────────────────────────────────────
// Called when user picks a designation from the dropdown inside invite modal.
// Fetches that designation's saved permissions and checks the right boxes.
function loadDesigPerms(desigId, prefix) {
    var bookId = document.querySelector('meta[name="book-id"]') 
                   ? document.querySelector('meta[name="book-id"]').content 
                   : (window._bookId || '');
    if (!bookId) {
        // Fallback: extract from URL
        var m = window.location.pathname.match(/\/books\/(\d+)/);
        if (m) bookId = m[1];
    }
    if (!desigId || !bookId) return;
    var modalId = _permModalMap[prefix] || (prefix + 'Modal');
    var modal = document.getElementById(modalId);
    if (!modal) return;

    fetch('/books/' + bookId + '/employees/designations/' + desigId + '/permissions')
        .then(function(r){ return r.json(); })
        .then(function(perms) {
            modal.querySelectorAll('input[type="checkbox"][name^="perm["]').forEach(function(cb) {
                var mod = cb.getAttribute('data-mod');
                var act = cb.getAttribute('data-action');
                var checked = !!(perms[mod] && perms[mod][act]);
                cb.checked = checked;
                cb.dispatchEvent(new Event('change'));
            });
        })
        .catch(function(){});
}

document.addEventListener('DOMContentLoaded', function(){
    init();
    // Sync perm-check pill visual state
    document.querySelectorAll('.perm-check').forEach(function(pill) {
        var cb = pill.querySelector('input[type="checkbox"]');
        if (!cb) return;
        function sync() { pill.classList.toggle('checked', cb.checked); }
        sync();
        cb.addEventListener('change', sync);
        pill.addEventListener('click', function(e) {
            if (e.target !== cb) { cb.checked = !cb.checked; cb.dispatchEvent(new Event('change')); }
        });
    });
    // Wire delete-book permission warning on any checkbox that appears
    document.querySelectorAll('input[name="perm[book_settings][delete]"]').forEach(function(cb) {
        var warn = cb.closest('label,li,.perm-check');
        if (!warn) return;
        var el = document.createElement('div');
        el.className = 'delete-perm-warning';
        el.style.cssText = 'display:none;align-items:center;gap:6px;margin-top:6px';
        el.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i>'
            + '<span><strong>Danger:</strong> This lets the employee permanently delete this entire book and all its data.</span>';
        warn.parentNode.insertBefore(el, warn.nextSibling);
        cb.addEventListener('change', function() { el.style.display = this.checked ? 'flex' : 'none'; });
    });
});
})();
</script>

<?php
$content = ob_get_clean();
ob_start();
$printCategory = 'employees';
require BASE_PATH . '/views/partials/print-modal.php';
$content .= ob_get_clean();
require BASE_PATH . '/views/partials/layout.php';
?>
