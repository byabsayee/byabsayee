<?php
$pageTitle = e($employee['name']) . ' — Employees — ' . e($book['name']);
$isOwner   = $book['user_id'] === auth()['id'];

$moduleLabels = [
    'invoices'      => ['label'=>'Invoices',      'icon'=>'fa-file-invoice'],
    'pos'           => ['label'=>'POS',            'icon'=>'fa-cash-register'],
    'products'      => ['label'=>'Products',       'icon'=>'fa-box'],
    'funds'         => ['label'=>'Funds',          'icon'=>'fa-piggy-bank'],
    'expenses'      => ['label'=>'Expenses',       'icon'=>'fa-receipt'],
    'dues'          => ['label'=>'Dues',           'icon'=>'fa-hand-holding-dollar'],
    'debts'         => ['label'=>'Debts',          'icon'=>'fa-file-circle-minus'],
    'customers'     => ['label'=>'Customers',      'icon'=>'fa-users'],
    'suppliers'     => ['label'=>'Suppliers',      'icon'=>'fa-user-tie'],
    'employees'     => ['label'=>'Employees',      'icon'=>'fa-id-badge'],
    'contacts'      => ['label'=>'Contacts',       'icon'=>'fa-address-book'],
    'coupons'       => ['label'=>'Coupons',        'icon'=>'fa-ticket'],
    'returns'       => ['label'=>'Returns',        'icon'=>'fa-rotate-left'],
    'deliveries'    => ['label'=>'Deliveries',     'icon'=>'fa-truck-fast'],
    'reports'       => ['label'=>'Reports',        'icon'=>'fa-chart-line'],
    'privileges'    => ['label'=>'Privileges',     'icon'=>'fa-star'],
    'book_settings' => ['label'=>'Book Settings',  'icon'=>'fa-gear'],
];
$actionLabels = ['view'=>'View','create'=>'Create','edit'=>'Edit','delete'=>'Delete',
                 'adjust_stock'=>'Adjust Stock','pay'=>'Pay','invite'=>'Invite'];

$currentPerms = [];
if ($member) {
    $currentPerms = json_decode($member['permissions'] ?? '{}', true) ?? [];
}

// Salary history
$salaryHistory = [];
$totalSalaryPaid = 0;
try {
    $salaryHistory = \App\Helpers\Database::query(
        'SELECT sp.*, e.title AS expense_title
         FROM employee_salary_payments sp
         LEFT JOIN expenses e ON e.id = sp.expense_id
         WHERE sp.employee_id=? AND sp.book_id=?
         ORDER BY sp.created_at DESC',
        [$employee['id'], $book['id']]
    );
    $totalSalaryPaid = array_sum(array_column($salaryHistory, 'amount'));
} catch (\Throwable $e) {}

ob_start();
?>

<div class="page-header">
    <div class="page-header-left">
        <div class="breadcrumb">
            <a href="/books/<?= $book['id'] ?>">Dashboard</a> <span>›</span>
            <a href="/books/<?= $book['id'] ?>/employees">Employees</a> <span>›</span>
            <span><?= e($employee['name']) ?></span>
        </div>
        <h1><?= e($employee['name']) ?></h1>
        <div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:4px">
            <?php if (!empty($employee['emp_code'])): ?>
                <span class="badge badge-gray" style="font-family:monospace;letter-spacing:.5px"><?= e($employee['emp_code']) ?></span>
            <?php endif; ?>
            <?php if ($employee['designation_name']): ?>
                <span class="badge badge-blue"><?= e($employee['designation_name']) ?></span>
            <?php endif; ?>
            <?php $sc = ['active'=>'green','inactive'=>'gray','terminated'=>'red'][$employee['status']] ?? 'gray'; ?>
            <span class="badge badge-<?= $sc ?>"><?= ucfirst($employee['status']) ?></span>
            <?php if ($employee['user_id']): ?>
                <span class="badge badge-green"><i class="fa-solid fa-link"></i> Has Account</span>
            <?php else: ?>
                <span class="badge badge-gray">No account</span>
            <?php endif; ?>
        </div>
    </div>
    <?php if ($isOwner): ?>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
        <button class="btn btn-secondary" data-modal="editEmployeeModal">Edit</button>
        <?php if ((int)$employee['user_id'] !== (int)$book['user_id']): ?>
            <?php if ($employee['status'] !== 'terminated'): ?>
            <button class="btn btn-danger" data-modal="terminateModal"
                    style="background:var(--red-bg);color:var(--red);border-color:var(--red)">
                <i class="fa-solid fa-user-slash"></i> Terminate
            </button>
            <?php else: ?>
            <form method="POST" action="/books/<?= $book['id'] ?>/employees/<?= $employee['id'] ?>/reinstate"
                  data-confirm="Reinstate <?= e($employee['name']) ?>? This will restore their book access.">
                <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                <button class="btn btn-secondary" style="color:var(--green)">
                    <i class="fa-solid fa-user-check"></i> Reinstate
                </button>
            </form>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php endif; ?>

<!-- TERMINATE MODAL -->
<div class="modal-backdrop" id="terminateModal">
    <div class="modal" style="max-width:420px">
        <div class="modal-title" style="color:var(--red)">
            <i class="fa-solid fa-user-slash"></i> Terminate <?= e($employee['name']) ?>
        </div>
        <p style="font-size:13px;color:var(--text-muted);margin-bottom:16px">
            This will permanently revoke their access to this book. The employee record will remain in your list.
            They will receive a notification.
        </p>
        <form method="POST" action="/books/<?= $book['id'] ?>/employees/<?= $employee['id'] ?>/terminate">
            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
            <div class="form-group" style="margin-bottom:16px">
                <label>Reason <span style="color:var(--text-muted)">(optional, will be sent to employee)</span></label>
                <textarea name="reason" rows="2" style="min-height:56px"
                          placeholder="e.g. End of contract, Performance issues…"></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-close-modal>Cancel</button>
                <button type="submit" class="btn btn-danger">
                    <i class="fa-solid fa-user-slash"></i> Confirm Termination
                </button>
            </div>
        </form>
    </div>
</div>
</div>

<div style="display:grid;grid-template-columns:290px 1fr;gap:16px;align-items:start">

    <!-- LEFT COLUMN -->
    <div style="display:flex;flex-direction:column;gap:12px">

        <!-- Contact / Details -->
        <div class="card">
            <p class="card-title">Details</p>
            <div style="font-size:13px;display:flex;flex-direction:column;gap:7px">
                <?php if ($employee['phone']): ?><div><span style="color:var(--text-muted)">Phone:</span> <?= e($employee['phone']) ?></div><?php endif; ?>
                <?php if ($employee['email']): ?><div><span style="color:var(--text-muted)">Email:</span> <?= e($employee['email']) ?></div><?php endif; ?>
                <?php if ($employee['address']): ?><div><span style="color:var(--text-muted)">Address:</span> <?= e($employee['address']) ?></div><?php endif; ?>
                <?php if ($employee['department']): ?><div><span style="color:var(--text-muted)">Department:</span> <?= e($employee['department']) ?></div><?php endif; ?>
                <?php if ($employee['join_date']): ?><div><span style="color:var(--text-muted)">Joined:</span> <?= format_date($employee['join_date']) ?></div><?php endif; ?>
                <?php if ($employee['notes']): ?><div><span style="color:var(--text-muted)">Notes:</span> <?= e($employee['notes']) ?></div><?php endif; ?>
            </div>
        </div>

        <!-- Salary card -->
        <div class="card">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
                <p class="card-title" style="margin:0">Salary</p>
                <?php if ($isOwner && $employee['salary']): ?>
                <button class="btn btn-sm btn-primary" data-modal="paySalaryModal">Pay</button>
                <?php endif; ?>
            </div>
            <?php if ($employee['salary']): ?>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:12px">
                <div style="background:var(--surface);border-radius:8px;padding:10px;text-align:center">
                    <div style="font-size:11px;color:var(--text-muted)">Monthly Salary</div>
                    <div style="font-size:16px;font-weight:700;color:var(--brand)"><?= format_money($employee['salary']) ?></div>
                </div>
                <div style="background:var(--surface);border-radius:8px;padding:10px;text-align:center">
                    <div style="font-size:11px;color:var(--text-muted)">Total Paid</div>
                    <div style="font-size:16px;font-weight:700;color:var(--green)"><?= format_money($totalSalaryPaid) ?></div>
                </div>
            </div>
            <?php if (!empty($salaryHistory)): ?>
            <p style="font-size:11px;color:var(--text-muted);margin-bottom:6px">Recent payments</p>
            <?php foreach (array_slice($salaryHistory, 0, 4) as $sp): ?>
            <div style="display:flex;justify-content:space-between;align-items:center;font-size:12px;padding:5px 0;border-bottom:1px solid var(--border)">
                <div>
                    <div style="font-weight:500"><?= e($sp['period_label'] ?? format_date($sp['created_at'])) ?></div>
                    <div style="color:var(--text-muted)"><?= ucfirst($sp['payment_method']) ?></div>
                </div>
                <div style="font-weight:600;color:var(--green)"><?= format_money($sp['amount']) ?></div>
            </div>
            <?php endforeach; ?>
            <?php if (count($salaryHistory) > 4): ?>
            <p style="font-size:11px;color:var(--text-muted);margin-top:6px"><?= count($salaryHistory)-4 ?> more payments…</p>
            <?php endif; ?>
            <?php else: ?>
            <p style="font-size:12px;color:var(--text-muted)">No payments recorded yet.</p>
            <?php endif; ?>
            <?php else: ?>
            <p style="font-size:13px;color:var(--text-muted);margin-bottom:8px">No salary set. Edit employee to add salary.</p>
            <?php endif; ?>
        </div>

        <!-- App Access -->
        <div class="card">
            <p class="card-title">App Access</p>
            <?php if ($employee['user_id'] && $member): ?>
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px">
                    <div class="s-avatar" style="width:32px;height:32px;font-size:12px;flex-shrink:0"><?= mb_strtoupper(mb_substr($employee['name'],0,1)) ?></div>
                    <div>
                        <div style="font-size:13px;font-weight:600"><?= e($employee['name']) ?></div>
                        <div style="font-size:11px;color:var(--text-muted)"><?= e($employee['email'] ?? '') ?></div>
                    </div>
                </div>
                <div style="font-size:12px">Status: <span class="badge badge-<?= $member['status']==='active'?'green':'gray' ?>"><?= ucfirst($member['status']) ?></span></div>
                <?php if ($member['designation_name']): ?>
                <div style="font-size:12px;margin-top:4px">Role: <span class="badge badge-blue"><?= e($member['designation_name']) ?></span></div>
                <?php endif; ?>
            <?php elseif ($employee['user_id']): ?>
                <p style="font-size:13px;color:var(--text-muted);margin-bottom:10px">Account linked, invitation may be pending.</p>
            <?php else: ?>
                <p style="font-size:13px;color:var(--text-muted);margin-bottom:10px">This employee does not have a Byabsayee account linked.</p>
                <?php if ($isOwner): ?>
                <button class="btn btn-sm btn-secondary" data-modal="sendInviteModal">
                    <i class="fa-solid fa-envelope"></i> Send Invitation
                </button>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- RIGHT: permissions -->
    <div>
        <?php if ($isOwner && $employee['user_id'] && $member && $member['status'] === 'active'): ?>
        <form method="POST" action="/books/<?= $book['id'] ?>/employees/<?= $employee['id'] ?>/permissions">
            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
                <p class="section-label" style="margin:0">Permissions</p>
                <div style="display:flex;gap:8px;align-items:center">
                    <?php if (!empty($designations)): ?>
                    <select onchange="applyDesigPerms(this.value)" style="font-size:12px;padding:4px 8px;border:1.5px solid var(--border);border-radius:6px">
                        <option value="">Apply Designation…</option>
                        <?php foreach ($designations as $d): ?>
                        <option value="<?= $d['id'] ?>"><?= e($d['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php endif; ?>
                    <button type="button" onclick="toggleAll(true)"  class="btn btn-sm btn-secondary">All</button>
                    <button type="button" onclick="toggleAll(false)" class="btn btn-sm btn-secondary">None</button>
                    <button type="submit" class="btn btn-sm btn-primary">Save</button>
                </div>
            </div>
            <div class="perm-grid">
            <?php foreach ($modules as $mod => $actions): ?>
            <?php $ml = $moduleLabels[$mod] ?? ['label'=>$mod,'icon'=>'fa-circle']; ?>
            <div class="perm-row">
                <div class="perm-module"><i class="fa-solid <?= $ml['icon'] ?>"></i> <?= $ml['label'] ?></div>
                <div class="perm-actions">
                <?php foreach ($actions as $action): ?>
                <?php $checked = !empty($currentPerms[$mod][$action]); ?>
                    <label class="perm-check <?= $checked ? 'checked' : '' ?>">
                        <input type="checkbox" name="perm[<?= $mod ?>][<?= $action ?>]"
                               class="emp-perm" data-mod="<?= $mod ?>" data-action="<?= $action ?>"
                               <?= $checked ? 'checked' : '' ?>
                               onchange="this.closest('label').classList.toggle('checked', this.checked)">
                        <?= $actionLabels[$action] ?? $action ?>
                    </label>
                <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
            </div>
        </form>
        <?php elseif (!$employee['user_id']): ?>
        <div class="card"><div class="empty-state" style="padding:40px">
            <div class="empty-icon"><i class="fa-solid fa-lock" style="font-size:32px;color:var(--text-muted)"></i></div>
            <h3>No App Permissions</h3>
            <p>Send an invitation to set up permissions once they join.</p>
        </div></div>
        <?php elseif ($member && $member['status'] !== 'active'): ?>
        <div class="card"><div class="empty-state" style="padding:40px">
            <div class="empty-icon"><i class="fa-solid fa-ban" style="font-size:32px;color:var(--red)"></i></div>
            <h3>Access Revoked</h3>
            <p>Restore access to re-enable permissions.</p>
        </div></div>
        <?php else: ?>
        <div class="card"><div class="empty-state" style="padding:40px">
            <div class="empty-icon"><i class="fa-solid fa-clock" style="font-size:32px;color:var(--accent)"></i></div>
            <h3>Invitation Pending</h3>
            <p>Waiting for the user to accept the invitation.</p>
        </div></div>
        <?php endif; ?>
    </div>
</div>

<!-- ── MODALS ─────────────────────────────────────────────── -->

<?php if ($isOwner): ?>

<!-- EDIT EMPLOYEE -->
<div class="modal-backdrop" id="editEmployeeModal">
    <div class="modal">
        <div class="modal-title">Edit Employee</div>
        <form method="POST" action="/books/<?= $book['id'] ?>/employees/<?= $employee['id'] ?>/edit">
            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
            <div class="form-grid" style="gap:12px">
                <div class="form-group full"><label>Name *</label><input type="text" name="name" value="<?= e($employee['name']) ?>" required></div>
                <div class="form-group">
                    <label>Designation</label>
                    <select name="designation_id">
                        <option value="">— None —</option>
                        <?php foreach ($designations as $d): ?>
                        <option value="<?= $d['id'] ?>" <?= $employee['designation_id']==$d['id']?'selected':'' ?>><?= e($d['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group"><label>Custom Designation</label><input type="text" name="designation_name" value="<?= e($employee['designation_name']??'') ?>"></div>
                <div class="form-group"><label>Phone</label><input type="text" name="phone" value="<?= e($employee['phone']??'') ?>"></div>
                <div class="form-group"><label>Email</label><input type="email" name="email" value="<?= e($employee['email']??'') ?>"></div>
                <div class="form-group full"><label>Address</label><textarea name="address" style="min-height:48px"><?= e($employee['address']??'') ?></textarea></div>
                <div class="form-group"><label>Department</label><input type="text" name="department" value="<?= e($employee['department']??'') ?>"></div>
                <div class="form-group"><label>Join Date</label><input type="date" name="join_date" value="<?= e($employee['join_date']??'') ?>"></div>
                <div class="form-group"><label>Salary</label><input type="number" name="salary" step="0.01" min="0" value="<?= e($employee['salary']??'') ?>"></div>
                <div class="form-group">
                    <label>Salary Type</label>
                    <select name="salary_type">
                        <?php foreach (['monthly','daily','hourly'] as $st): ?>
                        <option value="<?= $st ?>" <?= ($employee['salary_type']??'')===$st?'selected':'' ?>><?= ucfirst($st) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group full"><label>Notes</label><textarea name="notes" style="min-height:48px"><?= e($employee['notes']??'') ?></textarea></div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status">
                        <?php foreach (['active','inactive','terminated'] as $st): ?>
                        <option value="<?= $st ?>" <?= $employee['status']===$st?'selected':'' ?>><?= ucfirst($st) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-close-modal>Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- PAY SALARY -->
<?php if ($employee['salary']): ?>
<div class="modal-backdrop" id="paySalaryModal">
    <div class="modal" style="max-width:460px">
        <div class="modal-title"><i class="fa-solid fa-money-bill-wave" style="color:var(--green)"></i> Pay Salary</div>
        <form method="POST" action="/books/<?= $book['id'] ?>/employees/<?= $employee['id'] ?>/salary/pay">
            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
            <div class="form-grid" style="gap:12px">
                <div class="form-group">
                    <label>Amount *</label>
                    <input type="number" name="amount" step="0.01" min="0.01"
                           value="<?= e($employee['salary']??'') ?>" required>
                </div>
                <div class="form-group">
                    <label>Period (e.g. May 2026)</label>
                    <input type="text" name="period_label" placeholder="<?= date('F Y') ?>">
                </div>
                <div class="form-group">
                    <label>From</label>
                    <input type="date" name="period_from">
                </div>
                <div class="form-group">
                    <label>To</label>
                    <input type="date" name="period_to">
                </div>
                <div class="form-group">
                    <label>Payment Method</label>
                    <select name="payment_method">
                        <option value="cash">Cash</option>
                        <option value="bank_transfer">Bank Transfer</option>
                        <option value="mobile_banking">Mobile Banking</option>
                        <option value="cheque">Cheque</option>
                    </select>
                </div>
                <div class="form-group full">
                    <label>Note</label>
                    <input type="text" name="note" placeholder="Optional">
                </div>
            </div>
            <p style="font-size:12px;color:var(--text-muted);margin-top:8px;margin-bottom:0">
                <i class="fa-solid fa-info-circle"></i> This will also create an entry in Expenses.
            </p>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-close-modal>Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> Confirm Payment</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- SEND INVITE (for employees without account) -->
<?php if (!$employee['user_id']): ?>
<div class="modal-backdrop" id="sendInviteModal">
    <div class="modal" style="max-width:460px">
        <div class="modal-title"><i class="fa-solid fa-envelope" style="color:var(--brand)"></i> Send Invitation</div>
        <form method="POST" action="/books/<?= $book['id'] ?>/employees/<?= $employee['id'] ?>/send-invite">
            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
            <p style="font-size:13px;color:var(--text-muted);margin-bottom:16px">
                The user must already have a Byabsayee account. They'll get an in-app notification and email.
            </p>
            <div class="form-group">
                <label>Email Address *</label>
                <input type="email" name="email" value="<?= e($employee['email'] ?? '') ?>" required
                       placeholder="their Byabsayee account email">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-close-modal>Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-paper-plane"></i> Send Invitation</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php endif; // isOwner ?>

<script>
function toggleAll(val) {
    document.querySelectorAll('.emp-perm').forEach(cb => {
        cb.checked = val;
        cb.closest('label').classList.toggle('checked', val);
    });
}
function applyDesigPerms(desigId) {
    if (!desigId) return;
    fetch('/books/<?= $book['id'] ?>/employees/designations/' + desigId + '/permissions')
        .then(r => r.json())
        .then(perms => {
            document.querySelectorAll('.emp-perm').forEach(cb => {
                const val = !!(perms[cb.dataset.mod] && perms[cb.dataset.mod][cb.dataset.action]);
                cb.checked = val;
                cb.closest('label').classList.toggle('checked', val);
            });
        }).catch(() => {});
}
</script>

<?php
$content = ob_get_clean();
require BASE_PATH . '/views/partials/layout.php';
?>
