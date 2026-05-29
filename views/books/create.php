<?php
$pageTitle = 'Create Book — Byabsayee';
ob_start();
?>

<div class="page-header">
    <div class="page-header-left">
        <div class="breadcrumb"><a href="/books">Books</a> <span>›</span> <span>New Book</span></div>
        <h1>Create a Book</h1>
    </div>
</div>

<!-- Type selector -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;max-width:560px;margin-bottom:24px">
    <label class="type-opt" id="opt-personal">
        <input type="radio" name="_type_pick" value="personal" checked onchange="pickType('personal')">
        <div class="type-opt-inner">
            <div class="type-opt-icon">👤</div>
            <div class="type-opt-title">Personal</div>
            <div class="type-opt-desc">Track your own income and expenses.</div>
        </div>
    </label>
    <label class="type-opt" id="opt-business">
        <input type="radio" name="_type_pick" value="business" onchange="pickType('business')">
        <div class="type-opt-inner">
            <div class="type-opt-icon">🏪</div>
            <div class="type-opt-title">Business</div>
            <div class="type-opt-desc">Customers, invoices, stock, employees and more.</div>
        </div>
    </label>
</div>

<form action="/books/create" method="POST" enctype="multipart/form-data" style="max-width:600px">
    <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
    <input type="hidden" name="type" id="bookType" value="personal">

    <div class="card" style="margin-bottom:16px">
        <div class="form-grid">
            <div class="form-group full">
                <label>Book name *</label>
                <input type="text" name="name" value="<?= old('name') ?>"
                       placeholder="e.g. My Savings, Rahim Store 2025" required autofocus>
            </div>
            <div class="form-group">
                <label>Card colour</label>
                <div style="display:flex;gap:8px;align-items:center">
                    <input type="color" name="color" value="#1a6b4a"
                           style="width:42px;height:38px;padding:2px;cursor:pointer;border:1.5px solid var(--border);border-radius:8px;background:none">
                    <span style="font-size:12px;color:var(--text-muted)">Accent on dashboard card</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Business-only fields -->
    <div id="businessFields" style="display:none">
        <div class="card" style="margin-bottom:16px">
            <p class="card-title">Business Details</p>
            <div class="form-grid">
                <div class="form-group full">
                    <label>Business / Shop name</label>
                    <input type="text" name="business_name" placeholder="e.g. Rahim Electronics" value="<?= old('business_name') ?>">
                </div>
                <div class="form-group">
                    <label>Phone</label>
                    <input type="text" name="phone" placeholder="+880 1700 000000" value="<?= old('phone') ?>">
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" placeholder="shop@example.com" value="<?= old('email') ?>">
                </div>
                <div class="form-group full">
                    <label>Address</label>
                    <textarea name="address" placeholder="Shop address…" style="min-height:64px"><?= old('address') ?></textarea>
                </div>
                <div class="form-group">
                    <label>Logo <span style="color:var(--text-muted);font-weight:400">(PNG/JPG, max 2MB)</span></label>
                    <input type="file" name="logo" accept="image/png,image/jpeg,image/webp,image/svg+xml">
                </div>
                <div class="form-group">
                    <label>Invoice number prefix</label>
                    <input type="text" name="invoice_prefix" placeholder="e.g. INV, R-INV, SHOP"
                           value="<?= old('invoice_prefix','INV') ?>"
                           style="text-transform:uppercase" maxlength="10">
                    <small style="color:var(--text-muted);font-size:11px">
                        Invoices will be numbered: PREFIX-0001, PREFIX-0002…
                    </small>
                </div>
                <div class="form-group">
                    <label>Invoice theme colour</label>
                    <div style="display:flex;gap:8px;align-items:center">
                        <input type="color" name="theme_color" value="#1a6b4a"
                               style="width:42px;height:38px;padding:2px;cursor:pointer;border:1.5px solid var(--border);border-radius:8px;background:none">
                        <span style="font-size:12px;color:var(--text-muted)">Used on printed invoices</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div style="display:flex;gap:10px">
        <button type="submit" class="btn btn-primary">Create Book</button>
        <a href="/books" class="btn btn-secondary">Cancel</a>
    </div>
</form>

<style>
.type-opt { cursor:pointer; }
.type-opt input { display:none; }
.type-opt-inner {
    border:2px solid var(--border);
    border-radius:var(--radius-lg);
    padding:18px;
    transition:border-color .15s,background .15s;
    height:100%;
}
.type-opt input:checked + .type-opt-inner { border-color:var(--brand);background:var(--brand-light); }
.type-opt-icon  { font-size:26px;margin-bottom:8px; }
.type-opt-title { font-size:15px;font-weight:600;margin-bottom:4px; }
.type-opt-desc  { font-size:12px;color:var(--text-muted);line-height:1.5; }
</style>

<script>
function pickType(type) {
    document.getElementById('bookType').value = type;
    document.getElementById('businessFields').style.display = type==='business' ? 'block' : 'none';
}
(function(){
    var saved = '<?= old('type','personal') ?>';
    if (saved==='business') { document.querySelector('input[value="business"]').checked=true; pickType('business'); }
})();
</script>

<?php $content = ob_get_clean(); require BASE_PATH . '/views/partials/layout.php'; ?>
