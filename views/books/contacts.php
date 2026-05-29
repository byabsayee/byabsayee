<?php
$pageTitle = 'Contacts — ' . e($book['name']);
ob_start();
?>

<div class="page-header">
    <div class="page-header-left">
        <div class="breadcrumb">
            <a href="/books">Books</a> <span>›</span>
            <a href="/books/<?= $book['id'] ?>"><?= e($book['name']) ?></a> <span>›</span>
            <span>Contacts</span>
        </div>
        <h1><i class="fa-solid fa-address-book" style="color:var(--brand)"></i> Contacts</h1>
        <p><?= count($contacts) ?> contact<?= count($contacts) !== 1 ? 's' : '' ?> in this book</p>
    </div>
    <button class="btn btn-primary" data-modal="addContactModal">
        <svg viewBox="0 0 20 20" fill="currentColor" width="14" height="14"><path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd"/></svg>
        Add Contact
    </button>
</div>

<?php if (empty($contacts)): ?>
<div class="table-wrap">
    <div class="empty-state">
        <div class="empty-icon">👤</div>
        <h3>No contacts yet</h3>
        <p>Add contacts to link them to your entries.</p>
    </div>
</div>
<?php else: ?>
<div class="table-wrap">
    <table>
        <thead>
            <tr>
                <th>Name</th>
                <th>Phone</th>
                <th>Email</th>
                <th>Entries</th>
                <th style="text-align:right">In</th>
                <th style="text-align:right">Out</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($contacts as $c): ?>
        <?php
            $cData = [
                'id'      => $c['id'],
                'name'    => $c['name'],
                'phone'   => $c['phone']   ?? '',
                'email'   => $c['email']   ?? '',
                'address' => $c['address'] ?? '',
                'notes'   => $c['notes']   ?? '',
            ];
        ?>
        <tr data-contact="<?= e(json_encode($cData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP)) ?>">
            <td><span style="font-weight:500"><?= e($c['name']) ?></span></td>
            <td class="td-muted"><?= $c['phone'] ? e($c['phone']) : '—' ?></td>
            <td class="td-muted"><?= $c['email'] ? e($c['email']) : '—' ?></td>
            <td class="td-muted"><?= $c['entry_count'] ?></td>
            <td style="text-align:right" class="td-amount in"><?= format_money($c['total_in']) ?></td>
            <td style="text-align:right" class="td-amount out"><?= format_money($c['total_out']) ?></td>
            <td style="text-align:right;white-space:nowrap">
                <button type="button" class="btn btn-sm btn-secondary btn-edit-contact" title="Edit contact">
                    <svg viewBox="0 0 20 20" fill="currentColor" width="13" height="13"><path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z"/></svg>
                </button>
                <form method="POST"
                      action="/books/<?= $book['id'] ?>/contacts/<?= $c['id'] ?>/delete"
                      style="display:inline"
                      data-confirm="Delete contact &quot;<?= e($c['name']) ?>&quot;?">
                    <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                    <button type="submit" class="btn btn-sm btn-danger" title="Delete contact">
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

<!-- ADD CONTACT MODAL -->
<div class="modal-backdrop" id="addContactModal">
    <div class="modal">
        <div class="modal-title">Add Contact</div>
        <form method="POST" action="/books/<?= $book['id'] ?>/contacts/add">
            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
            <div class="form-grid" style="gap:12px">
                <div class="form-group full">
                    <label>Name *</label>
                    <input type="text" name="name" placeholder="Full name" required>
                </div>
                <div class="form-group">
                    <label>Phone</label>
                    <input type="text" name="phone" placeholder="+880…">
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" placeholder="email@example.com">
                </div>
                <div class="form-group full">
                    <label>Address</label>
                    <textarea name="address" placeholder="Address…" style="min-height:56px"></textarea>
                </div>
                <div class="form-group full">
                    <label>Notes</label>
                    <textarea name="notes" placeholder="Any notes…" style="min-height:56px"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-close-modal>Cancel</button>
                <button type="submit" class="btn btn-primary">Save Contact</button>
            </div>
        </form>
    </div>
</div>

<!-- EDIT CONTACT MODAL -->
<div class="modal-backdrop" id="editContactModal">
    <div class="modal">
        <div class="modal-title">Edit Contact</div>
        <form method="POST" action="" id="editContactForm">
            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
            <div class="form-grid" style="gap:12px">
                <div class="form-group full">
                    <label>Name *</label>
                    <input type="text" id="ec_name" name="name" required>
                </div>
                <div class="form-group">
                    <label>Phone</label>
                    <input type="text" id="ec_phone" name="phone" placeholder="+880…">
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" id="ec_email" name="email" placeholder="email@example.com">
                </div>
                <div class="form-group full">
                    <label>Address</label>
                    <textarea id="ec_address" name="address" style="min-height:56px"></textarea>
                </div>
                <div class="form-group full">
                    <label>Notes</label>
                    <textarea id="ec_notes" name="notes" style="min-height:56px"></textarea>
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
    var editModal = document.getElementById('editContactModal');
    var editForm  = document.getElementById('editContactForm');

    document.querySelectorAll('.btn-edit-contact').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var row = btn.closest('tr');
            var c;
            try { c = JSON.parse(row.dataset.contact); } catch(e) { return; }

            editForm.action = '/books/<?= $book['id'] ?>/contacts/' + c.id + '/edit';
            document.getElementById('ec_name').value    = c.name    || '';
            document.getElementById('ec_phone').value   = c.phone   || '';
            document.getElementById('ec_email').value   = c.email   || '';
            document.getElementById('ec_address').value = c.address || '';
            document.getElementById('ec_notes').value   = c.notes   || '';

            editModal.classList.add('open');
        });
    });
})();
</script>

<?php
$content = ob_get_clean();
require BASE_PATH . '/views/partials/layout.php';
?>
