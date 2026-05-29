<?php /* $book, $employees, $designations set by controller */ ?>

<div class="page-header">
    <div class="page-header-left">
        <div class="breadcrumb">
            <a href="/books/<?= $book['id'] ?>">Books</a> <span>›</span>
            <a href="/books/<?= $book['id'] ?>/employees">Employees</a> <span>›</span>
            <span>Send Notification</span>
        </div>
        <h1><i class="fa-solid fa-paper-plane" style="color:var(--brand)"></i> Send Notification</h1>
        <p>Send an in-app message (and optional email) to selected employees.</p>
    </div>
</div>

<div style="max-width:680px">
<form method="POST" action="/books/<?= $book['id'] ?>/notifications/send">
    <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">

    <div class="card" style="margin-bottom:16px">
        <p class="card-title">Message</p>
        <div class="form-group" style="margin-bottom:12px">
            <label>Title *</label>
            <input type="text" name="title" required placeholder="e.g. Team Meeting Tomorrow at 10am">
        </div>
        <div class="form-group" style="margin-bottom:12px">
            <label>Message</label>
            <textarea name="body" style="min-height:90px" placeholder="Optional details…"></textarea>
        </div>
        <div class="form-group" style="margin-bottom:12px">
            <label>Action Link <span style="color:var(--text-muted)">(optional)</span></label>
            <input type="text" name="action_url" placeholder="e.g. /books/<?= $book['id'] ?>/invoices">
            <small style="color:var(--text-muted)">If set, a button will appear in the notification to navigate to this page.</small>
        </div>
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px">
            <input type="checkbox" name="send_email" value="1">
            Also send as email to employees who have email addresses
        </label>
    </div>

    <div class="card" style="margin-bottom:16px">
        <p class="card-title">Recipients</p>

        <div style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap">
            <label class="filter-btn active" id="mode-all-btn" style="cursor:pointer">
                <input type="radio" name="target_mode" value="all" checked style="display:none" onclick="setMode('all')">
                <i class="fa-solid fa-users"></i> All Employees
            </label>
            <?php if (!empty($designations)): ?>
            <label class="filter-btn" id="mode-desig-btn" style="cursor:pointer">
                <input type="radio" name="target_mode" value="designation" style="display:none" onclick="setMode('designation')">
                <i class="fa-solid fa-sitemap"></i> By Designation
            </label>
            <?php endif; ?>
            <label class="filter-btn" id="mode-select-btn" style="cursor:pointer">
                <input type="radio" name="target_mode" value="selected" style="display:none" onclick="setMode('selected')">
                <i class="fa-solid fa-list-check"></i> Select Individually
            </label>
        </div>

        <!-- Designation selector -->
        <div id="desig-picker" style="display:none;margin-bottom:12px">
            <label>Designation</label>
            <select name="designation_name" style="margin-top:4px">
                <option value="">— Choose —</option>
                <?php foreach ($designations as $d): ?>
                <option value="<?= e($d['designation_name']) ?>"><?= e($d['designation_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Individual selector -->
        <div id="individual-picker" style="display:none">
            <?php if (empty($employees)): ?>
                <p style="color:var(--text-muted);font-size:13px">No employees with app access.</p>
            <?php else: ?>
            <div style="display:flex;flex-direction:column;gap:6px;max-height:300px;overflow-y:auto;border:1.5px solid var(--border);border-radius:8px;padding:8px">
                <label style="display:flex;align-items:center;gap:8px;font-size:12px;color:var(--text-muted);cursor:pointer;padding:4px 0;border-bottom:1px solid var(--border)">
                    <input type="checkbox" onchange="document.querySelectorAll('.emp-chk').forEach(c=>c.checked=this.checked)"> Select All
                </label>
                <?php foreach ($employees as $e): ?>
                <label style="display:flex;align-items:center;gap:10px;font-size:13px;cursor:pointer;padding:6px 4px;border-radius:6px;transition:background .1s" onmouseover="this.style.background='var(--brand-light)'" onmouseout="this.style.background=''">
                    <input type="checkbox" name="user_ids[]" value="<?= $e['user_id'] ?>" class="emp-chk" style="width:15px;height:15px;accent-color:var(--brand)">
                    <div>
                        <div style="font-weight:500"><?= e($e['name']) ?></div>
                        <?php if ($e['designation_name']): ?><div style="font-size:11px;color:var(--text-muted)"><?= e($e['designation_name']) ?></div><?php endif; ?>
                    </div>
                    <div style="margin-left:auto;font-size:11px;color:var(--text-muted)"><?= e($e['user_email'] ?? '') ?></div>
                </label>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <div id="all-msg" style="font-size:13px;color:var(--text-muted);margin-top:4px">
            <i class="fa-solid fa-info-circle"></i>
            Notification will be sent to all <?= count($employees) ?> employee<?= count($employees)!==1?'s':'' ?> with app access.
        </div>
    </div>

    <div style="display:flex;gap:10px">
        <a href="/books/<?= $book['id'] ?>/employees" class="btn btn-secondary">Cancel</a>
        <button type="submit" class="btn btn-primary">
            <i class="fa-solid fa-paper-plane"></i> Send Notification
        </button>
    </div>
</form>
</div>

<script>
function setMode(mode) {
    ['all','desig','select'].forEach(m => {
        const btn = document.getElementById('mode-' + (m==='desig'?'desig':'select'===m?'select':'all') + '-btn');
    });
    document.getElementById('desig-picker').style.display        = mode === 'designation' ? '' : 'none';
    document.getElementById('individual-picker').style.display   = mode === 'selected'    ? '' : 'none';
    document.getElementById('all-msg').style.display             = mode === 'all'          ? '' : 'none';

    document.querySelectorAll('[id$="-btn"]').forEach(b => b.classList.remove('active'));
    const map = {all:'mode-all-btn', designation:'mode-desig-btn', selected:'mode-select-btn'};
    const el = document.getElementById(map[mode]);
    if (el) el.classList.add('active');

    // Set hidden radio
    const radios = document.querySelectorAll('[name="target_mode"]');
    radios.forEach(r => { if (r.value === mode) r.checked = true; });
}
</script>
