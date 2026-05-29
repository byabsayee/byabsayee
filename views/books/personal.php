<?php
$pageTitle = e($book['name']) . ' — Byabsayee';
$balance   = $totals['total_in'] - $totals['total_out'];
ob_start();
?>

<!-- Page header -->
<div class="page-header">
    <div class="page-header-left">
        <div class="breadcrumb">
            <a href="/books">Books</a>
            <span>›</span>
            <span><?= e($book['name']) ?></span>
        </div>
        <h1 style="display:flex;align-items:center;gap:10px">
            <span style="display:inline-block;width:12px;height:12px;border-radius:50%;background:<?= e($book['color']) ?>;flex-shrink:0"></span>
            <?= e($book['name']) ?>
        </h1>
        <p>Personal book</p>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
        <a href="/books/<?= $book['id'] ?>/contacts" class="btn btn-secondary">
            <svg viewBox="0 0 20 20" fill="currentColor" width="14" height="14"><path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z"/></svg>
            Contacts
        </a>
        <button class="btn btn-primary" data-modal="addEntryModal">
            <svg viewBox="0 0 20 20" fill="currentColor" width="14" height="14"><path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd"/></svg>
            Add Entry
        </button>
        <a href="/books/<?= $book['id'] ?>/edit" class="btn btn-secondary" title="Edit book">
            <svg viewBox="0 0 20 20" fill="currentColor" width="14" height="14"><path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z"/></svg>
        </a>
    </div>
</div>

<!-- Summary cards -->
<div class="stat-grid" style="grid-template-columns:repeat(3,1fr);max-width:600px">
    <div class="stat-card">
        <div class="stat-label">Total Income</div>
        <div class="stat-value green"><?= format_money($totals['total_in']) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Total Expenses</div>
        <div class="stat-value red"><?= format_money($totals['total_out']) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Balance</div>
        <div class="stat-value <?= $balance >= 0 ? 'brand' : 'red' ?>"><?= format_money($balance) ?></div>
    </div>
</div>

<!-- Entries table -->
<p class="section-label">Entries (<?= count($entries) ?>)</p>

<?php if (empty($entries)): ?>
<div class="table-wrap">
    <div class="empty-state">
        <div class="empty-icon">📝</div>
        <h3>No entries yet</h3>
        <p>Click "Add Entry" to record your first income or expense.</p>
    </div>
</div>
<?php else: ?>

<!-- Filter bar -->
<div style="display:flex;gap:8px;margin-bottom:12px;flex-wrap:wrap;align-items:center">
    <button class="btn btn-sm btn-secondary filter-btn active" data-filter="all">All</button>
    <button class="btn btn-sm btn-secondary filter-btn" data-filter="in" style="color:var(--green)">Income</button>
    <button class="btn btn-sm btn-secondary filter-btn" data-filter="out" style="color:var(--red)">Expense</button>
    <div style="flex:1"></div>
    <input type="text" id="entrySearch" placeholder="Search entries…"
           style="padding:6px 12px;border:1.5px solid var(--border);border-radius:8px;font-size:13px;font-family:inherit;outline:none;width:200px">
</div>

<div class="table-wrap">
    <table id="entriesTable">
        <thead>
            <tr>
                <th>Date</th>
                <th>Title</th>
                <th>Contact</th>
                <th>Type</th>
                <th style="text-align:right">Amount</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($entries as $entry): ?>
        <?php
            $entryAttachments = json_decode($entry['attachments'] ?? 'null', true) ?? [];
            $entryData = [
                'id'           => $entry['id'],
                'type'         => $entry['type'],
                'title'        => $entry['title'],
                'amount'       => $entry['amount'],
                'entry_date'   => $entry['entry_date'],
                'entry_time'   => $entry['entry_time'] ?? '',
                'contact_id'   => $entry['contact_id'] ?? '',
                'contact_name' => $entry['contact_name'] ?? '',
                'description'  => $entry['description'] ?? '',
                'attachments'  => $entryAttachments,
            ];
        ?>
        <tr data-type="<?= $entry['type'] ?>"
            data-search="<?= e(strtolower($entry['title'] . ' ' . ($entry['contact_name'] ?? ''))) ?>"
            data-entry="<?= e(json_encode($entryData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP)) ?>">
            <td class="td-muted"><?= format_date($entry['entry_date']) ?></td>
            <td>
                <div style="font-weight:500"><?= e($entry['title']) ?></div>
                <?php if ($entry['description']): ?>
                    <div class="td-muted" style="margin-top:2px;font-size:12px"><?= e(mb_strimwidth($entry['description'], 0, 60, '…')) ?></div>
                <?php endif; ?>
                <?php if (!empty($entryAttachments)): ?>
                    <div style="margin-top:3px">
                        <span style="font-size:11px;color:var(--muted)">📎 <?= count($entryAttachments) ?> attachment<?= count($entryAttachments) > 1 ? 's' : '' ?></span>
                    </div>
                <?php endif; ?>
            </td>
            <td class="td-muted"><?= ($entry['contact_name'] ?? '') ? e($entry['contact_name']) : '—' ?></td>
            <td>
                <?php if ($entry['type'] === 'in'): ?>
                    <span class="badge badge-green">Income</span>
                <?php else: ?>
                    <span class="badge badge-red">Expense</span>
                <?php endif; ?>
            </td>
            <td style="text-align:right">
                <span class="td-amount <?= $entry['type'] ?>">
                    <?= ($entry['type'] === 'in' ? '+' : '−') . ' ' . format_money($entry['amount']) ?>
                </span>
            </td>
            <td style="text-align:right;white-space:nowrap">
                <button type="button" class="btn btn-sm btn-secondary btn-view-entry" title="View entry">
                    <svg viewBox="0 0 20 20" fill="currentColor" width="13" height="13"><path d="M10 12a2 2 0 100-4 2 2 0 000 4z"/><path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd"/></svg>
                </button>
                <button type="button" class="btn btn-sm btn-secondary btn-edit-entry" title="Edit entry">
                    <svg viewBox="0 0 20 20" fill="currentColor" width="13" height="13"><path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z"/></svg>
                </button>
                <form method="POST"
                      action="/books/<?= $book['id'] ?>/entries/<?= $entry['id'] ?>/delete"
                      style="display:inline"
                      data-confirm="Delete this entry?">
                    <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                    <button type="submit" class="btn btn-sm btn-danger" title="Delete entry">
                        <svg viewBox="0 0 20 20" fill="currentColor" width="13" height="13"><path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                    </button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- ===== ADD ENTRY MODAL ===== -->
<div class="modal-backdrop" id="addEntryModal">
    <div class="modal">
        <div class="modal-title">Add Entry</div>

        <form method="POST" action="/books/<?= $book['id'] ?>/entries/add" enctype="multipart/form-data">
            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">

            <div class="form-group" style="margin-bottom:16px">
                <label>Type</label>
                <div class="type-toggle">
                    <input type="radio" name="type" id="type_in"  value="in"  checked>
                    <label for="type_in">&#43; Income</label>
                    <input type="radio" name="type" id="type_out" value="out">
                    <label for="type_out">&#8722; Expense</label>
                </div>
            </div>

            <div class="form-grid" style="gap:12px">
                <div class="form-group full">
                    <label for="e_title">Title *</label>
                    <input type="text" id="e_title" name="title" placeholder="e.g. Salary, Groceries" required>
                </div>
                <div class="form-group">
                    <label for="e_amount">Amount (৳) *</label>
                    <input type="number" id="e_amount" name="amount" placeholder="0.00" step="0.01" min="0.01" required>
                </div>
                <div class="form-group">
                    <label for="e_date">Date *</label>
                    <input type="date" id="e_date" name="date" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="form-group">
                    <label for="e_time">Time (optional)</label>
                    <input type="time" id="e_time" name="time">
                </div>
                <?php if (!empty($contacts)): ?>
                <div class="form-group">
                    <label for="e_contact">Contact (optional)</label>
                    <select id="e_contact" name="contact_id">
                        <option value="">— None —</option>
                        <?php foreach ($contacts as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <div class="form-group full">
                    <label for="e_desc">Description (optional)</label>
                    <textarea id="e_desc" name="description" placeholder="Any notes…" style="min-height:60px"></textarea>
                </div>
                <div class="form-group full">
                    <label for="e_attach">Attachment (optional — image or PDF, max 10MB)</label>
                    <input type="file" id="e_attach" name="attachment"
                           accept=".jpg,.jpeg,.png,.gif,.webp,.pdf" style="font-size:13px">
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-close-modal>Cancel</button>
                <button type="submit" class="btn btn-primary">Save Entry</button>
            </div>
        </form>
    </div>
</div>

<!-- ===== VIEW ENTRY MODAL ===== -->
<div class="modal-backdrop" id="viewEntryModal">
    <div class="modal" style="max-width:520px">
        <div class="modal-title" id="view_modal_title">Entry Details</div>
        <div id="view_modal_body" style="font-size:14px;line-height:1.7"></div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-close-modal>Close</button>
            <button type="button" class="btn btn-primary" id="viewToEditBtn">Edit Entry</button>
        </div>
    </div>
</div>

<!-- ===== EDIT ENTRY MODAL ===== -->
<div class="modal-backdrop" id="editEntryModal">
    <div class="modal" style="max-width:560px">
        <div class="modal-title">Edit Entry</div>

        <form method="POST" action="" id="editEntryForm" enctype="multipart/form-data">
            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">

            <div class="form-group" style="margin-bottom:16px">
                <label>Type</label>
                <div class="type-toggle">
                    <input type="radio" name="type" id="edit_type_in"  value="in">
                    <label for="edit_type_in">&#43; Income</label>
                    <input type="radio" name="type" id="edit_type_out" value="out">
                    <label for="edit_type_out">&#8722; Expense</label>
                </div>
            </div>

            <div class="form-grid" style="gap:12px">
                <div class="form-group full">
                    <label for="edit_title">Title *</label>
                    <input type="text" id="edit_title" name="title" required>
                </div>
                <div class="form-group">
                    <label for="edit_amount">Amount (৳) *</label>
                    <input type="number" id="edit_amount" name="amount" step="0.01" min="0.01" required>
                </div>
                <div class="form-group">
                    <label for="edit_date">Date *</label>
                    <input type="date" id="edit_date" name="date" required>
                </div>
                <div class="form-group">
                    <label for="edit_time">Time (optional)</label>
                    <input type="time" id="edit_time" name="time">
                </div>
                <?php if (!empty($contacts)): ?>
                <div class="form-group">
                    <label for="edit_contact">Contact (optional)</label>
                    <select id="edit_contact" name="contact_id">
                        <option value="">— None —</option>
                        <?php foreach ($contacts as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <div class="form-group full">
                    <label for="edit_desc">Description (optional)</label>
                    <textarea id="edit_desc" name="description" style="min-height:60px"></textarea>
                </div>
                <div class="form-group full" id="edit_existing_attachments" style="display:none"></div>
                <div class="form-group full">
                    <label for="edit_attach">Add Attachment (optional — image or PDF, max 10MB)</label>
                    <input type="file" id="edit_attach" name="attachment"
                           accept=".jpg,.jpeg,.png,.gif,.webp,.pdf" style="font-size:13px">
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
(function() {
    // ── Filters ──────────────────────────────────────────────────────────────
    document.querySelectorAll('.filter-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            var filter = btn.dataset.filter;
            document.querySelectorAll('#entriesTable tbody tr').forEach(function(row) {
                row.style.display = (filter === 'all' || row.dataset.type === filter) ? '' : 'none';
            });
        });
    });

    // ── Search ───────────────────────────────────────────────────────────────
    var searchEl = document.getElementById('entrySearch');
    if (searchEl) searchEl.addEventListener('input', function() {
        var q = this.value.toLowerCase();
        document.querySelectorAll('#entriesTable tbody tr').forEach(function(row) {
            row.style.display = (row.dataset.search || '').includes(q) ? '' : 'none';
        });
    });

    // ── Helpers ──────────────────────────────────────────────────────────────
    function getEntryFromRow(row) {
        try { return JSON.parse(row.dataset.entry); } catch(e) { return null; }
    }

    function fmtMoney(amount) {
        return '৳' + parseFloat(amount).toLocaleString('en-BD', {minimumFractionDigits:2, maximumFractionDigits:2});
    }

    function attachmentLink(path) {
        var name = path.split('/').pop();
        var isPdf = name.toLowerCase().endsWith('.pdf');
        return '<a href="/uploads/' + encodeURI(path) + '" target="_blank" rel="noopener" '
             + 'style="display:inline-flex;align-items:center;gap:5px;font-size:13px;'
             + 'color:var(--brand);text-decoration:none;padding:4px 8px;'
             + 'background:var(--surface);border:1px solid var(--border);'
             + 'border-radius:6px;margin:2px 4px 2px 0">'
             + (isPdf ? '📄' : '🖼️') + ' ' + name + '</a>';
    }

    // ── VIEW modal ───────────────────────────────────────────────────────────
    var viewModal  = document.getElementById('viewEntryModal');
    var viewTitle  = document.getElementById('view_modal_title');
    var viewBody   = document.getElementById('view_modal_body');
    var viewToEdit = document.getElementById('viewToEditBtn');
    var currentEntry = null;

    document.querySelectorAll('.btn-view-entry').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var entry = getEntryFromRow(btn.closest('tr'));
            if (!entry) return;
            currentEntry = entry;

            var typeHtml = entry.type === 'in'
                ? '<span class="badge badge-green">Income</span>'
                : '<span class="badge badge-red">Expense</span>';

            var rows = [
                ['Type',    typeHtml],
                ['Amount',  (entry.type === 'in' ? '+' : '−') + ' ' + fmtMoney(entry.amount)],
                ['Date',    entry.entry_date + (entry.entry_time ? ' &nbsp;' + entry.entry_time : '')],
                ['Contact', entry.contact_name || '—'],
                ['Notes',   entry.description  || '—'],
            ];

            var html = '<table style="width:100%;border-collapse:collapse">';
            rows.forEach(function(r) {
                html += '<tr><td style="padding:7px 0;color:var(--muted);width:90px;vertical-align:top;font-size:13px">'
                      + r[0] + '</td>'
                      + '<td style="padding:7px 0;font-weight:500">' + r[1] + '</td></tr>';
            });
            html += '</table>';

            if (entry.attachments && entry.attachments.length) {
                html += '<div style="margin-top:14px;padding-top:12px;border-top:1px solid var(--border)">'
                      + '<div style="font-size:12px;color:var(--muted);margin-bottom:8px;text-transform:uppercase;letter-spacing:.05em">Attachments</div>';
                entry.attachments.forEach(function(a) { html += attachmentLink(a); });
                html += '</div>';
            }

            viewTitle.textContent = entry.title;
            viewBody.innerHTML = html;
            viewModal.classList.add('open');
        });
    });

    if (viewToEdit) viewToEdit.addEventListener('click', function() {
        viewModal.classList.remove('open');
        if (currentEntry) openEditModal(currentEntry);
    });

    // ── EDIT modal ───────────────────────────────────────────────────────────
    var editModal    = document.getElementById('editEntryModal');
    var editForm     = document.getElementById('editEntryForm');
    var editExisting = document.getElementById('edit_existing_attachments');

    function openEditModal(entry) {
        editForm.action = '/books/<?= $book['id'] ?>/entries/' + entry.id + '/edit';

        document.getElementById('edit_type_in').checked  = (entry.type === 'in');
        document.getElementById('edit_type_out').checked = (entry.type === 'out');
        document.getElementById('edit_title').value  = entry.title  || '';
        document.getElementById('edit_amount').value = entry.amount || '';
        document.getElementById('edit_date').value   = entry.entry_date || '';
        document.getElementById('edit_time').value   = entry.entry_time || '';
        document.getElementById('edit_desc').value   = entry.description || '';

        var contactSel = document.getElementById('edit_contact');
        if (contactSel) contactSel.value = entry.contact_id || '';

        if (editExisting) {
            if (entry.attachments && entry.attachments.length) {
                var html = '<label style="font-size:13px;color:var(--muted);display:block;margin-bottom:6px">Existing Attachments</label>';
                entry.attachments.forEach(function(a) {
                    html += '<div style="display:flex;align-items:center;gap:10px;margin-bottom:6px">'
                          + attachmentLink(a)
                          + '<label style="display:flex;align-items:center;gap:4px;font-size:12px;color:var(--red);cursor:pointer;white-space:nowrap">'
                          + '<input type="checkbox" name="remove_attachments[]" value="' + a + '"> Remove'
                          + '</label></div>';
                });
                editExisting.innerHTML = html;
                editExisting.style.display = '';
            } else {
                editExisting.innerHTML = '';
                editExisting.style.display = 'none';
            }
        }

        // Reset file input
        var fileInput = document.getElementById('edit_attach');
        if (fileInput) fileInput.value = '';

        editModal.classList.add('open');
    }

    document.querySelectorAll('.btn-edit-entry').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var entry = getEntryFromRow(btn.closest('tr'));
            if (entry) openEditModal(entry);
        });
    });
})();
</script>

<?php
$content = ob_get_clean();
require BASE_PATH . '/views/partials/layout.php';
?>
