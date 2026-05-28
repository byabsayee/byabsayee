<?php
$pageTitle = 'App Settings';
$tab = $tab ?? 'profile';
$user = $user ?? auth();

// Try to load preferences (may be stored as JSON if columns don't exist)
$prefs = [];
try {
    $prefRow = Database::row('SELECT preferences FROM users WHERE id=?', [$user['id']]);
    if (!empty($prefRow['preferences'])) $prefs = json_decode($prefRow['preferences'], true) ?? [];
} catch (\Throwable $e) { $prefs = []; }

$userTheme    = $user['theme']    ?? $prefs['theme']    ?? 'light';
$userLang     = $user['language'] ?? $prefs['language'] ?? 'en';
$userTz       = $user['timezone'] ?? $prefs['timezone'] ?? 'Asia/Dhaka';
$userDateFmt  = $user['date_format']  ?? $prefs['dateFormat']  ?? 'd M Y';
$userCurrency = $user['default_currency'] ?? $prefs['currency'] ?? 'BDT';
$userEmailNot = $user['email_notifications'] ?? $prefs['notifications'] ?? 1;
$user2fa      = $user['two_fa_enabled']    ?? $prefs['twoFa']  ?? 0;

ob_start();
?>
<style>
.settings-wrap { display:flex; gap:24px; align-items:flex-start; }
.settings-nav  { width:220px; flex-shrink:0; background:var(--card-bg); border:1px solid var(--border); border-radius:12px; overflow:hidden; }
.settings-nav a { display:flex; align-items:center; gap:10px; padding:12px 16px; color:var(--text); text-decoration:none; font-size:14px; font-weight:500; border-bottom:1px solid var(--border); transition:background .15s; }
.settings-nav a:last-child { border-bottom:none; }
.settings-nav a:hover { background:var(--hover-bg, rgba(0,0,0,.04)); }
.settings-nav a.active { background:var(--brand-light, rgba(26,107,74,.08)); color:var(--brand); font-weight:600; }
.settings-nav a i { width:18px; text-align:center; font-size:15px; }
.settings-nav .nav-group-label { padding:10px 16px 4px; font-size:11px; font-weight:700; letter-spacing:.05em; color:var(--text-muted); text-transform:uppercase; }
.settings-body { flex:1; min-width:0; }
.settings-panel { background:var(--card-bg); border:1px solid var(--border); border-radius:12px; padding:28px; margin-bottom:20px; }
.settings-panel h2 { font-size:18px; font-weight:700; margin:0 0 4px; color:var(--text); }
.settings-panel .panel-desc { font-size:13px; color:var(--text-muted); margin:0 0 22px; }
.settings-panel hr { border:none; border-top:1px solid var(--border); margin:20px 0; }
.form-row { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
.form-group { margin-bottom:16px; }
.form-group label { display:block; font-size:13px; font-weight:600; color:var(--text); margin-bottom:6px; }
.form-group label .hint { font-weight:400; color:var(--text-muted); margin-left:6px; }
.form-group input[type=text],.form-group input[type=email],.form-group input[type=password],.form-group input[type=url],.form-group select,.form-group textarea { width:100%; padding:9px 12px; border:1px solid var(--border); border-radius:8px; background:var(--input-bg,var(--bg)); color:var(--text); font-size:14px; box-sizing:border-box; transition:border-color .15s; }
.form-group input:focus,.form-group select:focus,.form-group textarea:focus { outline:none; border-color:var(--brand); box-shadow:0 0 0 3px rgba(26,107,74,.12); }
.form-group textarea { resize:vertical; min-height:80px; }
.toggle-row { display:flex; align-items:center; justify-content:space-between; padding:12px 0; border-bottom:1px solid var(--border); }
.toggle-row:last-child { border-bottom:none; }
.toggle-row .toggle-info { flex:1; }
.toggle-row .toggle-info strong { display:block; font-size:14px; font-weight:600; color:var(--text); }
.toggle-row .toggle-info span  { font-size:12px; color:var(--text-muted); }
.toggle-switch { position:relative; width:44px; height:24px; flex-shrink:0; }
.toggle-switch input { opacity:0; width:0; height:0; position:absolute; }
.toggle-slider { position:absolute; inset:0; background:#ccc; border-radius:24px; cursor:pointer; transition:.2s; }
.toggle-slider:before { content:''; position:absolute; height:18px; width:18px; left:3px; bottom:3px; background:#fff; border-radius:50%; transition:.2s; }
.toggle-switch input:checked + .toggle-slider { background:var(--brand); }
.toggle-switch input:checked + .toggle-slider:before { transform:translateX(20px); }
.danger-zone { border-color:#e53e3e !important; }
.danger-zone h2 { color:#e53e3e; }
.faq-item { border:1px solid var(--border); border-radius:8px; margin-bottom:8px; overflow:hidden; }
.faq-q { padding:14px 16px; font-weight:600; font-size:14px; cursor:pointer; display:flex; justify-content:space-between; align-items:center; color:var(--text); background:var(--card-bg); }
.faq-q:hover { background:var(--hover-bg,rgba(0,0,0,.04)); }
.faq-q i { font-size:12px; color:var(--text-muted); transition:transform .2s; }
.faq-a { display:none; padding:12px 16px; font-size:13px; color:var(--text-muted); line-height:1.6; border-top:1px solid var(--border); background:var(--bg); }
.faq-item.open .faq-a { display:block; }
.faq-item.open .faq-q i { transform:rotate(180deg); }
.info-card { display:flex; gap:12px; padding:14px; border:1px solid var(--border); border-radius:8px; margin-bottom:10px; align-items:flex-start; }
.info-card i { font-size:18px; color:var(--brand); margin-top:2px; flex-shrink:0; }
.info-card strong { display:block; font-size:14px; font-weight:600; color:var(--text); margin-bottom:2px; }
.info-card span { font-size:13px; color:var(--text-muted); }
.contact-form-grid { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
.theme-swatches { display:flex; gap:10px; flex-wrap:wrap; margin-top:8px; }
.theme-swatch { width:44px; height:44px; border-radius:10px; cursor:pointer; border:3px solid transparent; transition:all .15s; position:relative; }
.theme-swatch.selected,.theme-swatch:hover { border-color:var(--brand); transform:scale(1.08); }
.theme-swatch .swatch-check { position:absolute; inset:0; display:flex; align-items:center; justify-content:center; color:#fff; font-size:16px; opacity:0; }
.theme-swatch.selected .swatch-check { opacity:1; }
@media(max-width:760px) { .settings-wrap{flex-direction:column;} .settings-nav{width:100%;} .form-row,.contact-form-grid{grid-template-columns:1fr;} }
</style>

<div class="page-header">
    <div class="page-header-left">
        <div class="breadcrumb"><span>Settings</span></div>
        <h1><i class="fa-solid fa-sliders" style="color:var(--brand)"></i> App Settings</h1>
        <p>Manage your account, preferences, and app settings</p>
    </div>
</div>

<?php if (!empty($_SESSION['flash_success'])): ?>
<div class="alert alert-success" style="margin-bottom:16px"><i class="fa-solid fa-check-circle"></i> <?= e($_SESSION['flash_success']) ?></div>
<?php unset($_SESSION['flash_success']); endif; ?>
<?php if (!empty($_SESSION['flash_error'])): ?>
<div class="alert alert-error" style="margin-bottom:16px"><i class="fa-solid fa-triangle-exclamation"></i> <?= e($_SESSION['flash_error']) ?></div>
<?php unset($_SESSION['flash_error']); endif; ?>

<div class="settings-wrap">
    <!-- Sidebar Nav -->
    <nav class="settings-nav">        
        <div class="nav-group-label">App</div>
        <a href="/settings?tab=theme"        class="<?= $tab==='theme'?'active':'' ?>"><i class="fa-solid fa-moon"></i> Theme</a>
        <a href="/settings?tab=language"     class="<?= $tab==='language'?'active':'' ?>"><i class="fa-solid fa-language"></i> Language</a>
        <a href="/settings?tab=timezone"     class="<?= $tab==='timezone'?'active':'' ?>"><i class="fa-solid fa-clock"></i> Timezone</a>
        <a href="/settings?tab=notifications" class="<?= $tab==='notifications'?'active':'' ?>"><i class="fa-solid fa-bell"></i> Notifications</a>
        <div class="nav-group-label">Support</div>
        <a href="/settings?tab=about"        class="<?= $tab==='about'?'active':'' ?>"><i class="fa-solid fa-circle-info"></i> About</a>
        <a href="/settings?tab=faq"          class="<?= $tab==='faq'?'active':'' ?>"><i class="fa-solid fa-circle-question"></i> FAQ</a>
        <a href="/settings?tab=help"         class="<?= $tab==='help'?'active':'' ?>"><i class="fa-solid fa-life-ring"></i> Help</a>
        <a href="/settings?tab=contact"      class="<?= $tab==='contact'?'active':'' ?>"><i class="fa-solid fa-envelope"></i> Contact Us</a>
    </nav>

    <!-- Content Panels -->
    <div class="settings-body">

    <?php if ($tab === 'theme'): ?>
    <div class="settings-panel">
        <h2>Theme</h2>
        <p class="panel-desc">Choose how Byabsayee looks for you. Your theme setting is stored locally in your browser.</p>
        <div class="form-group" style="margin-bottom:24px">
            <label>Appearance Mode</label>
            <div class="theme-swatches">
                <div class="theme-swatch" style="background:#f8f9fa;border:1px solid #dee2e6" onclick="setTheme('light')" id="sw-light" title="Light">
                    <div class="swatch-check"><i class="fa-solid fa-check" style="color:#333"></i></div>
                </div>
                <div class="theme-swatch" style="background:#1a1a2e" onclick="setTheme('dark')" id="sw-dark" title="Dark">
                    <div class="swatch-check"><i class="fa-solid fa-check"></i></div>
                </div>
                <div class="theme-swatch" style="background:linear-gradient(135deg,#f8f9fa 50%,#1a1a2e 50%)" onclick="setTheme('system')" id="sw-system" title="System">
                    <div class="swatch-check"><i class="fa-solid fa-check"></i></div>
                </div>
            </div>
            <div style="display:flex;gap:40px;margin-top:8px;font-size:12px;color:var(--text-muted)">
                <span>Light</span><span>Dark</span><span>System</span>
            </div>
        </div>
        <hr>
        <div class="form-group">
            <label>Brand Color</label>
            <p style="font-size:13px;color:var(--text-muted);margin:0 0 10px">The accent color is set per-book in each book's settings.</p>
            <a href="/books" class="btn btn-secondary btn-sm"><i class="fa-solid fa-book"></i> Go to My Books</a>
        </div>
    </div>
    <script>
    function setTheme(t) {
        localStorage.setItem('bya_theme', t);
        document.querySelectorAll('.theme-swatch').forEach(function(s){ s.classList.remove('selected'); });
        var el = document.getElementById('sw-'+t);
        if (el) el.classList.add('selected');
        if (t === 'dark') document.documentElement.setAttribute('data-theme','dark');
        else if (t === 'light') document.documentElement.removeAttribute('data-theme');
        else { var pref = window.matchMedia('(prefers-color-scheme:dark)').matches?'dark':'light'; if(pref==='dark') document.documentElement.setAttribute('data-theme','dark'); else document.documentElement.removeAttribute('data-theme'); }
    }
    document.addEventListener('DOMContentLoaded', function() {
        var t = localStorage.getItem('bya_theme')||'light';
        var el = document.getElementById('sw-'+t);
        if (el) el.classList.add('selected');
    });
    </script>

    <?php elseif ($tab === 'language'): ?>
    <div class="settings-panel">
        <h2>Language &amp; Region</h2>
        <p class="panel-desc">Set your preferred display language. Full localization support is coming soon.</p>
        <div class="form-group">
            <label>Interface Language</label>
            <select onchange="alert('Language switching will be available in the next update. Your selection has been noted.')">
                <option value="en" selected>🇬🇧 English (Default)</option>
                <option value="bn">🇧🇩 বাংলা (Bengali) — Coming Soon</option>
                <option value="ar">🇸🇦 عربى (Arabic) — Coming Soon</option>
                <option value="ur">🇵🇰 اردو (Urdu) — Coming Soon</option>
                <option value="hi">🇮🇳 हिन्दी (Hindi) — Coming Soon</option>
            </select>
        </div>
        <div class="info-card" style="margin-top:8px">
            <i class="fa-solid fa-globe"></i>
            <div>
                <strong>More languages coming</strong>
                <span>We're actively working on full Bengali localization. If you'd like to help translate, contact us!</span>
            </div>
        </div>
    </div>

        <?php elseif ($tab === 'preferences'): ?>
    <div class="settings-panel">
        <h2>Preferences</h2>
        <p class="panel-desc">Customize your date format, timezone, and default currency.</p>
        <form method="POST" action="/settings/preferences">
            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
            <div class="form-row">
                <div class="form-group">
                    <label>Date Format</label>
                    <select name="date_format">
                        <option value="d M Y"   <?= $userDateFmt==='d M Y'?'selected':'' ?>>15 Jan 2025</option>
                        <option value="d/m/Y"   <?= $userDateFmt==='d/m/Y'?'selected':'' ?>>15/01/2025</option>
                        <option value="m/d/Y"   <?= $userDateFmt==='m/d/Y'?'selected':'' ?>>01/15/2025</option>
                        <option value="Y-m-d"   <?= $userDateFmt==='Y-m-d'?'selected':'' ?>>2025-01-15</option>
                        <option value="d-m-Y"   <?= $userDateFmt==='d-m-Y'?'selected':'' ?>>15-01-2025</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Timezone</label>
                    <select name="timezone">
                        <?php
                        $tzones = ['Asia/Dhaka','Asia/Kolkata','Asia/Karachi','Asia/Dubai','Asia/Singapore',
                                   'Asia/Tokyo','Europe/London','Europe/Paris','America/New_York','America/Los_Angeles','UTC'];
                        foreach ($tzones as $tz):
                        ?>
                        <option value="<?= $tz ?>" <?= $userTz===$tz?'selected':'' ?>><?= $tz ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Default Currency</label>
                    <select name="currency">
                        <option value="BDT" <?= $userCurrency==='BDT'?'selected':'' ?>>BDT — Bangladeshi Taka (৳)</option>
                        <option value="USD" <?= $userCurrency==='USD'?'selected':'' ?>>USD — US Dollar ($)</option>
                        <option value="EUR" <?= $userCurrency==='EUR'?'selected':'' ?>>EUR — Euro (€)</option>
                        <option value="GBP" <?= $userCurrency==='GBP'?'selected':'' ?>>GBP — British Pound (£)</option>
                        <option value="INR" <?= $userCurrency==='INR'?'selected':'' ?>>INR — Indian Rupee (₹)</option>
                        <option value="PKR" <?= $userCurrency==='PKR'?'selected':'' ?>>PKR — Pakistani Rupee (₨)</option>
                        <option value="SAR" <?= $userCurrency==='SAR'?'selected':'' ?>>SAR — Saudi Riyal (﷼)</option>
                        <option value="AED" <?= $userCurrency==='AED'?'selected':'' ?>>AED — UAE Dirham (د.إ)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Language</label>
                    <select name="language">
                        <option value="en" <?= $userLang==='en'?'selected':'' ?>>English</option>
                        <option value="bn" <?= $userLang==='bn'?'selected':'' ?>>বাংলা (Bengali)</option>
                        <option value="ar" <?= $userLang==='ar'?'selected':'' ?>>عربى (Arabic)</option>
                        <option value="ur" <?= $userLang==='ur'?'selected':'' ?>>اردو (Urdu)</option>
                        <option value="hi" <?= $userLang==='hi'?'selected':'' ?>>हिन्दी (Hindi)</option>
                    </select>
                </div>
            </div>
            <hr>
            <h3 style="font-size:15px;margin:0 0 14px">Notifications</h3>
            <div style="margin-top:18px">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Save Preferences</button>
            </div>
        </form>
    </div>

    <?php elseif ($tab === 'notifications'): ?>
    <div class="settings-panel">
        <h2>Notification Preferences</h2>
        <p class="panel-desc">Control what alerts and updates you receive.</p>
        <div class="toggle-row">
            <div class="toggle-info">
                <strong>Email Notifications</strong>
                <span>Receive activity summaries and alerts via email</span>
            </div>
            <label class="toggle-switch">
                <input type="checkbox" name="email_notifications" value="1" <?= $userEmailNot ? 'checked' : '' ?>>
                <span class="toggle-slider"></span>
            </label>
        </div>
        <div class="toggle-row">
            <div class="toggle-info">
                <strong>Invoice Payment Reminders</strong>
                <span>Get notified when an invoice is due or overdue</span>
            </div>
            <label class="toggle-switch">
                <input type="checkbox" checked>
                <span class="toggle-slider"></span>
            </label>
        </div>
        <div class="toggle-row">
            <div class="toggle-info">
                <strong>Low Stock Alerts</strong>
                <span>Be alerted when product stock falls below threshold</span>
            </div>
            <label class="toggle-switch">
                <input type="checkbox" checked>
                <span class="toggle-slider"></span>
            </label>
        </div>
        <div class="toggle-row">
            <div class="toggle-info">
                <strong>New Employee Joined</strong>
                <span>Get notified when an employee accepts an invitation</span>
            </div>
            <label class="toggle-switch">
                <input type="checkbox" checked>
                <span class="toggle-slider"></span>
            </label>
        </div>
        <div class="toggle-row">
            <div class="toggle-info">
                <strong>Monthly Summary Email</strong>
                <span>Receive a monthly business summary in your inbox</span>
            </div>
            <label class="toggle-switch">
                <input type="checkbox">
                <span class="toggle-slider"></span>
            </label>
        </div>
        <div class="toggle-row">
            <div class="toggle-info">
                <strong>App Update Announcements</strong>
                <span>Be the first to know about new features</span>
            </div>
            <label class="toggle-switch">
                <input type="checkbox" checked>
                <span class="toggle-slider"></span>
            </label>
        </div>
        <div style="margin-top:18px">
            <button class="btn btn-primary" onclick="alert('Notification settings saved (demo).')"><i class="fa-solid fa-floppy-disk"></i> Save Preferences</button>
        </div>
    </div>

    <?php elseif ($tab === 'about'): ?>
    <div class="settings-panel">
        <h2>About Byabsayee</h2>
        <p class="panel-desc">Business management software built for real businesses.</p>
        <div style="display:flex;align-items:center;gap:20px;margin-bottom:20px">
            <img src="/assets/images/ByabsayeeLogo.png" alt="Byabsayee" style="height:64px;object-fit:contain" onerror="this.style.display='none'">
            <div>
                <div style="font-size:22px;font-weight:800;color:var(--brand)">Byabsayee</div>
                <div style="font-size:13px;color:var(--text-muted)">ERP & Accounting Platform</div>
                <div style="margin-top:4px"><span class="badge badge-green">v1.0.0</span></div>
            </div>
        </div>
        <hr>
        <div class="form-row" style="margin-bottom:16px">
            <div>
                <div style="font-size:12px;color:var(--text-muted);font-weight:600;text-transform:uppercase;letter-spacing:.05em">Version</div>
                <div style="font-size:14px;font-weight:600;color:var(--text)">1.0.0 (stable)</div>
            </div>
            <div>
                <div style="font-size:12px;color:var(--text-muted);font-weight:600;text-transform:uppercase;letter-spacing:.05em">PHP Version</div>
                <div style="font-size:14px;font-weight:600;color:var(--text)"><?= PHP_VERSION ?></div>
            </div>
            <div>
                <div style="font-size:12px;color:var(--text-muted);font-weight:600;text-transform:uppercase;letter-spacing:.05em">License</div>
                <div style="font-size:14px;font-weight:600;color:var(--text)">Proprietary</div>
            </div>
            <div>
                <div style="font-size:12px;color:var(--text-muted);font-weight:600;text-transform:uppercase;letter-spacing:.05em">Built With</div>
                <div style="font-size:14px;font-weight:600;color:var(--text)">PHP, MariaDB, Nginx</div>
            </div>
            <div>
                <div style="font-size:14px;font-weight:600;color:var(--text)"><a href="https://byabsayee.com/privacypolicy">Privacy Policy <i class="fa-solid fa-arrow-up-right-from-square" style="color: var(--text);"></i></a></div>
            </div>
            <div>
                <div style="font-size:14px;font-weight:600;color:var(--text)"><a href="https://byabsayee.com/termsofservice">Terms of Service <i class="fa-solid fa-arrow-up-right-from-square" style="color: var(--text);"></i></a></div>
            </div>
        </div>
        <hr>
        <p style="font-size:13px;color:var(--text-muted);line-height:1.7">
            Byabsayee is a comprehensive business management platform designed for Bangladeshi and South Asian SMEs.
            It covers invoicing, inventory, expenses, payroll, dues/debts, coupons, and business analytics — all in one integrated system.
        </p>
        <p style="font-size:13px;color:var(--text-muted);line-height:1.7">
            &copy; <?= date('Y') ?> Byabsayee. All rights reserved.
        </p>
    </div>

    <?php elseif ($tab === 'faq'): ?>
    <div class="settings-panel">
        <h2>Frequently Asked Questions</h2>
        <p class="panel-desc">Quick answers to common questions about Byabsayee.</p>

        <?php $faqs = [
            ['q'=>'What is Byabsayee?','a'=>'Byabsayee is an all-in-one ERP and accounting platform designed for small and medium businesses. It helps you track invoices, manage inventory, monitor finances, and more — all in one place.'],
            ['q'=>'How do I create a new invoice?','a'=>'Go to your business book → click "Sales" or "Purchases" in the sidebar → then click the "New Sale" or "New Purchase" button. Fill in the details and save.'],
            ['q'=>'Can I have multiple books?','a'=>'Yes! You can create multiple books — for example, one personal ledger and multiple business books. Each book is completely independent with its own data.'],
            ['q'=>'How does inventory work?','a'=>'Byabsayee tracks stock automatically. When you create a sale invoice with a product, stock decreases. When you create a purchase invoice, stock increases. You can also manually adjust stock from the Products page.'],
            ['q'=>'How do I record a payment on an invoice?','a'=>'Open the invoice, then click "Record Payment". Enter the amount paid and the payment method. The invoice status will update automatically to Partial or Paid.'],
            ['q'=>'What is the difference between Dues and Debts?','a'=>'Dues are amounts your customers owe you (receivables). Debts are amounts you owe to your suppliers (payables). Both are tracked automatically when you create invoices.'],
            ['q'=>'Can I add employees or team members?','a'=>'Yes. Go to your book → Employees → Invite an employee. You can assign roles and set granular permissions for each employee.'],
            ['q'=>'Is my data safe?','a'=>'Yes. All data is stored securely on your server. We recommend taking regular backups from the database. Byabsayee does not share your data with third parties.'],
            ['q'=>'How do I print or export an invoice?','a'=>'Open any invoice and click the Print or PDF button. You can also generate a thermal receipt format for point-of-sale printing.'],
            ['q'=>'Can I use Byabsayee on mobile?','a'=>'Byabsayee is fully responsive and works on mobile browsers. A dedicated mobile app is planned for a future release.'],
        ]; foreach ($faqs as $f): ?>
        <div class="faq-item">
            <div class="faq-q" onclick="this.parentElement.classList.toggle('open')">
                <?= e($f['q']) ?>
                <i class="fa-solid fa-chevron-down"></i>
            </div>
            <div class="faq-a"><?= e($f['a']) ?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php elseif ($tab === 'help'): ?>
    <div class="settings-panel">
        <h2>Help &amp; Documentation</h2>
        <p class="panel-desc">Get started quickly with guides and resources.</p>
        <?php $guides = [
            ['icon'=>'fa-rocket','title'=>'Getting Started','desc'=>'Learn the basics of setting up your first business book, adding products, and creating your first invoice.'],
            ['icon'=>'fa-file-invoice-dollar','title'=>'Invoicing Guide','desc'=>'Understand sales invoices, purchase bills, payment recording, and the invoice lifecycle from draft to paid.'],
            ['icon'=>'fa-boxes-stacked','title'=>'Inventory Management','desc'=>'Track stock levels, set low-stock alerts, manage product batches (FIFO/LIFO), and run stock adjustments.'],
            ['icon'=>'fa-users','title'=>'Managing Employees','desc'=>'Invite employees, assign designations, set permissions, and manage salary payments.'],
            ['icon'=>'fa-chart-line','title'=>'Reports &amp; Analytics','desc'=>'Understand your business with profit/loss summaries, category-wise expense breakdowns, and fund tracking.'],
            ['icon'=>'fa-tags','title'=>'Coupons &amp; Discounts','desc'=>'Create promotional coupons, apply customer privilege discounts, and use loyalty points at checkout.'],
        ]; foreach ($guides as $g): ?>
        <div class="info-card">
            <i class="fa-solid <?= $g['icon'] ?>"></i>
            <div>
                <strong><?= $g['title'] ?></strong>
                <span><?= $g['desc'] ?></span>
            </div>
        </div>
        <?php endforeach; ?>
        <hr>
        <p style="font-size:13px;color:var(--text-muted)">
            Can't find what you're looking for?
            <a href="/settings?tab=contact" style="color:var(--brand)">Contact our support team</a> — we're happy to help.
        </p>
    </div>

    <?php elseif ($tab === 'contact'): ?>
    <div class="settings-panel">
        <h2>Contact Us</h2>
        <p class="panel-desc">Have a question or issue? Reach out to us and we'll get back to you.</p>
        <div class="contact-form-grid" style="margin-bottom:20px">
            <div class="info-card">
                <i class="fa-solid fa-envelope"></i>
                <div>
                    <strong>Email Support</strong>
                    <span>support@byabsayee.com</span>
                </div>
            </div>
            <div class="info-card">
                <i class="fa-brands fa-whatsapp" style="color:#25d366"></i>
                <div>
                    <strong>WhatsApp</strong>
                    <span>+880 1XXX-XXXXXX</span>
                </div>
            </div>
            <div class="info-card">
                <i class="fa-solid fa-clock"></i>
                <div>
                    <strong>Support Hours</strong>
                    <span>Sun–Thu, 9 AM – 6 PM (BST)</span>
                </div>
            </div>
            <div class="info-card">
                <i class="fa-solid fa-globe"></i>
                <div>
                    <strong>Website</strong>
                    <span><a href="https://byabsayee.com" target="_blank" style="color:var(--brand)">byabsayee.com</a></span>
                </div>
            </div>
        </div>
        <hr>
        <h3 style="font-size:15px;margin:0 0 14px">Send a Message</h3>
        <form method="POST" action="/settings/contact">
            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
            <div class="form-row">
                <div class="form-group">
                    <label>Your Name</label>
                    <input type="text" name="name" value="<?= e($user['name'] ?? '') ?>" placeholder="Your name" required>
                </div>
                <div class="form-group">
                    <label>Your Email</label>
                    <input type="email" name="email" value="<?= e($user['email'] ?? '') ?>" placeholder="your@email.com" required>
                </div>
            </div>
            <div class="form-group">
                <label>Subject</label>
                <select name="subject" required>
                    <option value="General Question">General Question</option>
                    <option value="Bug Report">Bug Report</option>
                    <option value="Feature Request">Feature Request</option>
                    <option value="Billing Issue">Billing Issue</option>
                    <option value="Account Problem">Account Problem</option>
                    <option value="Other">Other</option>
                </select>
            </div>
            <div class="form-group">
                <label>Message</label>
                <textarea name="message" placeholder="Describe your question or issue in detail…" style="min-height:120px" required></textarea>
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="fa-solid fa-paper-plane"></i> Send Message
            </button>
        </form>
    </div>

<!--
    <?php elseif ($tab === 'privacy'): ?>
    <div class="settings-panel">
        <h2>Privacy Policy</h2>
        <p class="panel-desc">Last updated: <?= date('d F Y') ?></p>
        <?php $sections = [
            ['title'=>'1. Data Collection','body'=>'We collect the information you provide directly when you register an account or use the application. This includes your name, email address, and business data you enter into the system.'],
            ['title'=>'2. How We Use Your Data','body'=>'Your data is used solely to provide the Byabsayee service. We do not sell, rent, or share your personal data with third parties for marketing purposes.'],
            ['title'=>'3. Data Storage','body'=>'All data is stored on your self-hosted server or our secure servers (for cloud-hosted plans). You retain full ownership of your business data.'],
            ['title'=>'4. Cookies','body'=>'Byabsayee uses session cookies strictly required for authentication. We do not use tracking or advertising cookies.'],
            ['title'=>'5. Security','body'=>'We implement industry-standard security measures including password hashing, CSRF protection, and input sanitization to protect your data.'],
            ['title'=>'6. Your Rights','body'=>'You have the right to access, correct, or delete your account and data at any time. Use the Danger Zone tab to permanently delete your account.'],
            ['title'=>'7. Contact','body'=>'For privacy concerns, contact us at privacy@byabsayee.com.'],
        ]; foreach ($sections as $s): ?>
        <h3 style="font-size:15px;margin:16px 0 6px"><?= $s['title'] ?></h3>
        <p style="font-size:13px;color:var(--text-muted);line-height:1.7;margin:0"><?= $s['body'] ?></p>
        <?php endforeach; ?>
    </div>

    <?php elseif ($tab === 'terms'): ?>
    <div class="settings-panel">
        <h2>Terms of Service</h2>
        <p class="panel-desc">Last updated: <?= date('d F Y') ?></p>
        <?php $terms = [
            ['title'=>'1. Acceptance','body'=>'By using Byabsayee, you agree to these Terms of Service. If you do not agree, please do not use the application.'],
            ['title'=>'2. Use of Service','body'=>'Byabsayee is provided for legitimate business management purposes only. You agree not to use it for illegal activities, fraud, or any purpose that violates applicable laws.'],
            ['title'=>'3. Account Responsibility','body'=>'You are responsible for maintaining the confidentiality of your account credentials. You are responsible for all activity that occurs under your account.'],
            ['title'=>'4. Data Accuracy','body'=>'You are responsible for the accuracy and legality of the business data you enter. Byabsayee is not liable for decisions made based on data you enter.'],
            ['title'=>'5. Service Availability','body'=>'We strive to maintain high availability but do not guarantee uninterrupted access. Scheduled maintenance will be communicated in advance where possible.'],
            ['title'=>'6. Intellectual Property','body'=>'The Byabsayee software, design, and content are owned by Byabsayee and protected by copyright law. You may not copy or redistribute the software without permission.'],
            ['title'=>'7. Termination','body'=>'We reserve the right to suspend or terminate accounts that violate these terms. You may delete your account at any time from the Danger Zone.'],
            ['title'=>'8. Limitation of Liability','body'=>'Byabsayee is provided "as is". We are not liable for any indirect, incidental, or consequential damages arising from use of the service.'],
        ]; foreach ($terms as $t): ?>
        <h3 style="font-size:15px;margin:16px 0 6px"><?= $t['title'] ?></h3>
        <p style="font-size:13px;color:var(--text-muted);line-height:1.7;margin:0"><?= $t['body'] ?></p>
        <?php endforeach; ?>
    </div>

    <div id="deleteModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:9999;align-items:center;justify-content:center">
        <div style="background:var(--card-bg);border-radius:12px;padding:28px;max-width:420px;width:90%;border:2px solid #e53e3e">
            <h3 style="margin:0 0 8px;color:#e53e3e"><i class="fa-solid fa-triangle-exclamation"></i> Confirm Account Deletion</h3>
            <p style="font-size:14px;color:var(--text-muted);margin:0 0 16px;line-height:1.6">
                This action <strong>cannot be undone</strong>. Your account, all books, invoices, products, customers, and all other data will be permanently deleted.
            </p>
            <p style="font-size:14px;color:var(--text);margin:0 0 10px">Type <strong>DELETE</strong> to confirm:</p>
            <form method="POST" action="/settings/delete-account">
                <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                <input type="text" name="confirm_delete" placeholder="Type DELETE here" style="width:100%;padding:10px;border:1px solid #e53e3e;border-radius:8px;background:var(--bg);color:var(--text);font-size:14px;box-sizing:border-box;margin-bottom:14px">
                <div style="display:flex;gap:10px">
                    <button type="button" class="btn btn-secondary" onclick="document.getElementById('deleteModal').style.display='none'">Cancel</button>
                    <button type="submit" class="btn" style="background:#e53e3e;color:#fff;border:none;flex:1">
                        <i class="fa-solid fa-trash"></i> Permanently Delete
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>
-->


    </div><!-- /.settings-body -->
</div><!-- /.settings-wrap -->

<?php
$content = ob_get_clean();
require BASE_PATH . '/views/partials/layout.php';
