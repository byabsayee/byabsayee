<?php
$pageTitle = 'Contacts — ' . e($book['name']);
ob_start();
?>

<div class="page-header">
    <div class="page-header-left">
        <div class="breadcrumb">
            <a href="/books/<?= $book['id'] ?>">Books</a> <span>›</span>
            <span>Contacts</span>
        </div>
        <h1><i class="fa-solid fa-address-book" style="color:var(--brand)"></i> Contacts</h1>
        <p><?= $totalCount ?> contact<?= $totalCount !== 1 ? 's' : '' ?> across all types</p>
    </div>
</div>

<!-- FILTER TABS + SEARCH -->
<div class="lm-controls">
    <div class="lm-search-wrap">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="text" class="lm-search" id="conTableSearch" placeholder="Search name, phone, email…">
        <button class="lm-search-clear" id="conTableClear"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <select class="lm-select" id="conTableSort">
        <option value="az">Name A–Z</option>
        <option value="za">Name Z–A</option>
    </select>
</div>
<div class="lm-filter-pills">
    <span style="font-size:12px;font-weight:600;color:var(--text-muted)">Type:</span>
    <button class="btn btn-sm btn-primary" data-contact-tab="all" onclick="filterTab('all')">
        All <span class="badge badge-gray" style="margin-left:4px"><?= $totalCount ?></span>
    </button>
    <button class="btn btn-sm btn-secondary" data-contact-tab="customer" onclick="filterTab('customer')">
        <i class="fa-solid fa-users"></i> Customers <span class="badge badge-gray" style="margin-left:4px"><?= count($customers) ?></span>
    </button>
    <button class="btn btn-sm btn-secondary" data-contact-tab="supplier" onclick="filterTab('supplier')">
        <i class="fa-solid fa-user-tie"></i> Suppliers <span class="badge badge-gray" style="margin-left:4px"><?= count($suppliers) ?></span>
    </button>
    <button class="btn btn-sm btn-secondary" data-contact-tab="employee" onclick="filterTab('employee')">
        <i class="fa-solid fa-id-badge"></i> Employees <span class="badge badge-gray" style="margin-left:4px"><?= count($employees) ?></span>
    </button>
</div>

<!-- ALL CONTACTS (unified table) -->
<?php
$allContacts = [];
foreach ($customers as $c) {
    $allContacts[] = [
        'type'    => 'customer',
        'id'      => $c['id'],
        'name'    => $c['name'],
        'phone'   => $c['phone'] ?? '',
        'email'   => $c['email'] ?? '',
        'company' => '',
        'label'   => $c['invoice_count'] . ' invoice' . ($c['invoice_count']!=1?'s':''),
        'amount'  => $c['total_billed'] ?? 0,
        'url'     => '/books/'.$book['id'].'/customers/'.$c['id'],
    ];
}
foreach ($suppliers as $s) {
    $allContacts[] = [
        'type'    => 'supplier',
        'id'      => $s['id'],
        'name'    => $s['name'],
        'phone'   => $s['phone'] ?? '',
        'email'   => $s['email'] ?? '',
        'company' => $s['company'] ?? '',
        'label'   => $s['invoice_count'] . ' purchase' . ($s['invoice_count']!=1?'s':''),
        'amount'  => $s['total_billed'] ?? 0,
        'url'     => '/books/'.$book['id'].'/suppliers/'.$s['id'],
    ];
}
foreach ($employees as $e) {
    $allContacts[] = [
        'type'    => 'employee',
        'id'      => $e['id'],
        'name'    => $e['name'],
        'phone'   => $e['phone'] ?? '',
        'email'   => $e['email'] ?? '',
        'company' => $e['designation_name'] ?? $e['department'] ?? '',
        'label'   => ucfirst($e['status']),
        'amount'  => null,
        'url'     => '/books/'.$book['id'].'/employees/'.$e['id'],
    ];
}
usort($allContacts, fn($a,$b) => strcasecmp($a['name'], $b['name']));

$typeColors = ['customer'=>'blue','supplier'=>'amber','employee'=>'green'];
$typeIcons  = ['customer'=>'fa-users','supplier'=>'fa-user-tie','employee'=>'fa-id-badge'];
?>

<?php if (empty($allContacts)): ?>
<div class="table-wrap">
    <div class="empty-state">
        <div class="empty-icon">📋</div>
        <h3>No contacts yet</h3>
        <p>Contacts are automatically added when you create customers, suppliers, or employees.</p>
        <div style="display:flex;gap:8px;justify-content:center;margin-top:12px">
        <button class="btn btn-secondary btn-sm" data-modal="printModal" title="Print PDF">
            <i class="fa-solid fa-print"></i> Print
        </button>
            <a href="/books/<?= $book['id'] ?>/customers" class="btn btn-secondary">+ Customer</a>
            <a href="/books/<?= $book['id'] ?>/suppliers" class="btn btn-secondary">+ Supplier</a>
            <a href="/books/<?= $book['id'] ?>/employees" class="btn btn-secondary">+ Employee</a>
        </div>
    </div>
</div>
<?php else: ?>
<div class="table-wrap">
    <table id="contactsTable">
        <thead>
            <tr>
                <th>Name</th>
                <th>Type</th>
                <th>Phone</th>
                <th>Email</th>
                <th>Company / Role</th>
                <th>Info</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($allContacts as $c): ?>
        <tr data-type="<?= $c['type'] ?>" data-search="<?= strtolower(e($c['name'].' '.$c['phone'].' '.$c['email'].' '.$c['company'])) ?>">
            <td>
                <a href="<?= $c['url'] ?>" style="font-weight:500;color:var(--brand);text-decoration:none">
                    <?= e($c['name']) ?>
                </a>
            </td>
            <td>
                <span class="badge badge-<?= $typeColors[$c['type']] ?>">
                    <i class="fa-solid <?= $typeIcons[$c['type']] ?>"></i>
                    <?= ucfirst($c['type']) ?>
                </span>
            </td>
            <td class="td-muted"><?= $c['phone'] ? e($c['phone']) : '—' ?></td>
            <td class="td-muted"><?= $c['email'] ? e($c['email']) : '—' ?></td>
            <td class="td-muted"><?= $c['company'] ? e($c['company']) : '—' ?></td>
            <td class="td-muted"><?= e($c['label']) ?></td>
            <td>
                <a href="<?= $c['url'] ?>" class="btn btn-sm btn-secondary">View</a>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<div id="conTablePager"></div>
<?php endif; ?>

<script>
var currentTab = 'all';

function filterTab(tab) {
    currentTab = tab;
    document.querySelectorAll('[data-contact-tab]').forEach(function(b) {
        var t = b.getAttribute('data-contact-tab');
        b.classList.toggle('btn-primary', t === tab);
        b.classList.toggle('btn-secondary', t !== tab);
    });
    applyFilter();
}

function searchContacts(q) {
    var sc = document.getElementById('conTableClear');
    if(sc) sc.classList.toggle('visible', (q||'').length > 0);
    applyFilter(q);
}

function applyFilter(q) {
    q = (q || document.getElementById('conTableSearch').value || '').toLowerCase().trim();
    document.querySelectorAll('#contactsTable tbody tr').forEach(row => {
        const typeMatch   = currentTab === 'all' || row.dataset.type === currentTab;
        const searchMatch = !q || row.dataset.search.includes(q);
        row.style.display = (typeMatch && searchMatch) ? '' : 'none';
    });
}

// Wire up new search input
document.addEventListener('DOMContentLoaded', function() {
    var si = document.getElementById('conTableSearch');
    var sc = document.getElementById('conTableClear');
    if (si) {
        si.addEventListener('input', function() { searchContacts(this.value); });
        if (sc) sc.addEventListener('click', function() { si.value=''; searchContacts(''); });
    }
});
</script>

<?php
$content = ob_get_clean();
$printCategory = 'contacts';
require BASE_PATH . '/views/partials/print-modal.php';
require BASE_PATH . '/views/partials/layout.php';
?>
