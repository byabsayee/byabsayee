<?php
$pageTitle = 'Online store — ' . e($book['name']);
$st = $conn['status'] ?? null;
$badge = ['pending' => ['Waiting for the website', '#b45309'], 'verifying' => ['Verifying', '#b45309'], 'active' => ['Connected', '#15803d'], 'paused' => ['Paused', '#6b7280'], 'revoked' => ['Disconnected', '#b91c1c']];
$entityNames = ['category' => 'Category', 'product' => 'Product', 'customer' => 'Customer', 'order' => 'Order', 'payment' => 'Payment', 'payment_method' => 'Payment method', 'stock_movement' => 'Stock movement', 'coupon' => 'Coupon', 'tax' => 'Tax', 'delivery_charge' => 'Delivery', 'return' => 'Return'];
$csrf = '<input type="hidden" name="_csrf" value="' . csrf_token() . '">';
$base = '/books/' . $book['id'] . '/integrations';
ob_start();
?>
<style>
.int-card{background:var(--card-bg,#fff);border:1px solid var(--border,#e5e7eb);border-radius:12px;padding:20px;margin-bottom:18px}
.int-card h2{font-size:1.05rem;margin:0 0 4px}.int-card p.sub{margin:0 0 14px;color:var(--text-muted,#6b7280);font-size:.9rem}
.int-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:12px}
.int-stat{border:1px solid var(--border,#e5e7eb);border-radius:10px;padding:12px}.int-stat b{display:block;font-size:1.4rem}.int-stat span{font-size:.8rem;color:var(--text-muted,#6b7280)}
.int-pill{display:inline-block;padding:3px 10px;border-radius:999px;font-size:.78rem;font-weight:700;color:#fff}
.int-row{display:flex;flex-wrap:wrap;gap:8px;align-items:center}.int-row form{margin:0}
.int-code{font-family:ui-monospace,Menlo,monospace;background:var(--bg-subtle,#f3f4f6);border:1px solid var(--border,#e5e7eb);border-radius:8px;padding:8px 10px;word-break:break-all;font-size:.85rem;display:block}
.int-table{width:100%;border-collapse:collapse;font-size:.86rem}.int-table th,.int-table td{text-align:left;padding:7px 8px;border-bottom:1px solid var(--border,#e5e7eb);vertical-align:top}
.int-warn{background:#fffbeb;border:1px solid #fcd34d;color:#92400e;border-radius:10px;padding:12px 14px;margin-bottom:16px;font-size:.9rem}
.int-form label{display:block;font-weight:600;margin:12px 0 4px}.int-form input[type=text],.int-form input[type=number],.int-form input[type=url]{width:100%;max-width:460px}
.int-opt{display:flex;gap:8px;align-items:flex-start;font-weight:400!important;margin:6px 0!important}.int-opt small{display:block;color:var(--text-muted,#6b7280)}
.int-two{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px}
</style>

<div class="page-header"><div class="page-header-left">
  <div class="breadcrumb"><a href="/books/<?= $book['id'] ?>">Books</a> <span>›</span> <span>Online store</span></div>
  <h1><i class="fa-solid fa-link" style="color:var(--brand)"></i> Online store
    <?php if ($st): ?><span class="int-pill" style="background:<?= $badge[$st][1] ?>;margin-left:8px"><?= $badge[$st][0] ?></span><?php endif; ?></h1>
  <p>Keep products, stock, customers, orders and payments in step with your website — in both directions.</p>
</div></div>

<?php if ($weakKey): ?><div class="int-warn"><b>Set a secret key first.</b> The server has no <code>INTEGRATION_SECRET_KEY</code> (or a real <code>APP_KEY</code>), so stored connection secrets would be protected by a well-known default. Add one to your <code>.env</code> before connecting a website, and never change it afterwards (changing it means re-pairing).</div><?php endif; ?>

<?php if (!empty($creds)): ?>
<div class="int-card" style="border-color:#15803d">
  <h2>Pair the website — shown only once</h2>
  <p class="sub">In the website's admin go to <b>ERP → Connect</b>, enter this book's address and the pairing code. The code works once and expires in 30 minutes.</p>
  <label>Book address</label><code class="int-code"><?= e(rtrim(config('url', ''), '/') ?: 'https://YOUR-BOOK-DOMAIN') ?></code>
  <label style="margin-top:10px;display:block;font-weight:600">Pairing code</label><code class="int-code" style="font-size:1.3rem;letter-spacing:2px"><?= e($creds['pairing_code']) ?></code>
  <details style="margin-top:12px"><summary>Manual setup (if the code can't be used)</summary>
    <p class="sub" style="margin-top:8px">Enter these on the website instead. Keep them secret — they won't be shown again.</p>
    <label style="display:block;font-weight:600">Connection ID</label><code class="int-code"><?= e($creds['connection_id']) ?></code>
    <label style="display:block;font-weight:600;margin-top:8px">API key</label><code class="int-code"><?= e($creds['api_key']) ?></code>
    <label style="display:block;font-weight:600;margin-top:8px">Secret: website → book</label><code class="int-code"><?= e($creds['secrets']['site_to_book']) ?></code>
    <label style="display:block;font-weight:600;margin-top:8px">Secret: book → website</label><code class="int-code"><?= e($creds['secrets']['book_to_site']) ?></code>
  </details>
</div>
<?php endif; ?>

<?php if (!$conn || $st === 'revoked'): ?>
<div class="int-card">
  <h2><?= $conn ? 'Reconnect ' . e($conn['site_domain']) : 'Connect a website' ?></h2>
  <p class="sub"><?= $conn ? 'This book was linked to this website before; reconnecting resumes where it left off and nothing is duplicated.' : 'One website connects to exactly one book. The website needs its own HTTPS domain or subdomain (for example <b>shop.example.com</b>).' ?></p>
  <?php if (!book_can($book, 'integrations', 'manage')): ?><p>You can view this page but not change it.</p><?php else: ?>
  <form method="post" action="<?= $base ?>/connect" class="int-form"><?= $csrf ?>
    <label>Website address</label>
    <input type="url" name="site_url" placeholder="https://shop.example.com" value="<?= $conn ? 'https://' . e($conn['site_domain']) : '' ?>" <?= $conn ? 'readonly' : '' ?> required>
    <label>Whose currency, timezone and tax settings win?</label>
    <label class="int-opt"><input type="radio" name="authority" value="book" checked> <span>This book<small>The website adopts <?= e($bookCurrency['code']) ?>, this book's timezone and the tax settings below.</small></span></label>
    <label class="int-opt"><input type="radio" name="authority" value="site" <?= $hasInvoices ? 'disabled' : '' ?>> <span>The website<small><?= $hasInvoices ? 'Not available: this book already has invoices, and currency is never converted automatically.' : 'The book adopts the website\'s currency, timezone and tax.' ?></small></span></label>
    <label>What should sync?</label>
    <?php foreach (['catalog' => 'Products & categories', 'stock' => 'Stock movements', 'customers' => 'Customers', 'orders' => 'Orders & returns', 'payments' => 'Payments & payment methods', 'money' => 'Coupons, tax & delivery charges'] as $k => $l): ?>
      <label class="int-opt"><input type="checkbox" name="scopes[]" value="<?= $k ?>" checked> <span><?= $l ?></span></label>
    <?php endforeach; ?>
    <?php if (!$conn): ?><details style="margin-top:10px"><summary>Tax for online orders (when this book is authoritative)</summary>
      <label class="int-opt"><input type="checkbox" name="tax_enabled" value="1"> <span>Charge tax</span></label>
      <label>Rate (%)</label><input type="number" step="0.001" min="0" max="100" name="tax_rate" value="0">
      <label class="int-opt"><input type="checkbox" name="tax_inclusive" value="1"> <span>Prices already include tax</span></label>
      <label>Label</label><input type="text" name="tax_label" value="Tax" maxlength="40">
    </details><?php endif; ?>
    <p style="margin-top:16px"><button class="btn btn-primary" type="submit"><?= $conn ? 'Reconnect' : 'Create link & get pairing code' ?></button></p>
  </form>
  <?php endif; ?>
</div>
<?php if ($conn && book_can($book, 'integrations', 'manage')): ?>
<div class="int-card"><h2>Connect a different website instead</h2><p class="sub">Removing the link forgets the pairing history with <?= e($conn['site_domain']) ?>. Your products, orders and customers stay in the book.</p>
  <form method="post" action="<?= $base ?>/remove" onsubmit="return confirm('Remove the link to <?= e($conn['site_domain']) ?>? Its pairing history is forgotten; book records stay.')"><?= $csrf ?><button class="btn btn-danger btn-sm" type="submit">Remove link</button></form></div>
<?php endif; ?>
<?php endif; ?>

<?php if ($conn && $st !== 'revoked'): ?>
<div class="int-card">
  <h2><?= e($conn['site_domain']) ?></h2>
  <p class="sub">Authority: <b><?= $conn['authority'] === 'book' ? 'this book' : 'the website' ?></b> · Last sync: <?= $conn['last_sync_at'] ? e(fmt_datetime($conn['last_sync_at'])) . ' UTC' : 'never' ?>
    <?php if (!empty($conn['last_error'])): ?><br><span style="color:#b91c1c">Last problem: <?= e($conn['last_error']) ?></span><?php endif; ?></p>
  <?php if ($counts): ?><div class="int-grid" style="margin-bottom:14px">
    <div class="int-stat"><b><?= $counts['pending'] ?></b><span>waiting to send</span></div>
    <div class="int-stat"><b style="color:<?= $counts['dead'] ? '#b91c1c' : 'inherit' ?>"><?= $counts['dead'] ?></b><span>failed (need you)</span></div>
    <div class="int-stat"><b style="color:<?= $counts['open_conflicts'] ? '#b45309' : 'inherit' ?>"><?= $counts['open_conflicts'] ?></b><span>to review</span></div>
    <div class="int-stat"><b><?= $counts['done'] ?></b><span>delivered</span></div></div><?php endif; ?>
  <?php if (in_array($st, ['pending', 'verifying'], true)): ?>
    <p class="sub"><?= $st === 'pending' ? 'Waiting for the website to pair using the code above. If you lost it, disconnect and reconnect to get a new one.' : 'The website has paired; the book is confirming it controls the domain.' ?></p>
  <?php endif; ?>
  <?php if (book_can($book, 'integrations', 'manage')): ?>
  <div class="int-row">
    <?php if ($st === 'verifying'): ?><form method="post" action="<?= $base ?>/verify"><?= $csrf ?><button class="btn btn-primary btn-sm">Verify now</button></form><?php endif; ?>
    <?php if ($st === 'active'): ?><form method="post" action="<?= $base ?>/sync"><?= $csrf ?><button class="btn btn-primary btn-sm">Sync now</button></form>
      <form method="post" action="<?= $base ?>/pause"><?= $csrf ?><button class="btn btn-secondary btn-sm">Pause</button></form><?php endif; ?>
    <?php if ($st === 'paused'): ?><form method="post" action="<?= $base ?>/resume"><?= $csrf ?><button class="btn btn-primary btn-sm">Resume</button></form><?php endif; ?>
    <?php if (in_array($st, ['active', 'paused'], true)): ?><form method="post" action="<?= $base ?>/rotate" onsubmit="return confirm('Generate new keys and give them to the website now?')"><?= $csrf ?><button class="btn btn-secondary btn-sm">Rotate keys</button></form><?php endif; ?>
    <form method="post" action="<?= $base ?>/disconnect" onsubmit="return confirm('Disconnect from <?= e($conn['site_domain']) ?>? Records stay; you can reconnect the same website later.')"><?= $csrf ?><button class="btn btn-danger btn-sm">Disconnect</button></form>
  </div><?php endif; ?>
</div>

<?php if (in_array($st, ['active', 'paused'], true) && book_can($book, 'integrations', 'manage')): $t = $shared['tax']; $d = $shared['delivery']; ?>
<div class="int-card"><h2>Tax &amp; delivery charges</h2><p class="sub">Shared with the website. Changes made on either side are synced; the newest change wins.</p>
  <form method="post" action="<?= $base ?>/settings" class="int-form"><?= $csrf ?>
    <div class="int-two">
      <div><label class="int-opt"><input type="checkbox" name="tax_enabled" value="1" <?= $t['enabled'] ? 'checked' : '' ?>> <span>Charge tax</span></label>
        <label>Rate (%)</label><input type="number" step="0.001" min="0" max="100" name="tax_rate" value="<?= e($t['rate']) ?>">
        <label class="int-opt"><input type="checkbox" name="tax_inclusive" value="1" <?= $t['inclusive'] ? 'checked' : '' ?>> <span>Prices include tax</span></label>
        <label>Label</label><input type="text" name="tax_label" value="<?= e($t['label']) ?>" maxlength="40"></div>
      <div><label>Inside Dhaka</label><input type="number" step="0.01" min="0" name="d_inside_dhaka" value="<?= e($d['inside_dhaka']) ?>">
        <label>Dhaka suburbs</label><input type="number" step="0.01" min="0" name="d_suburbs" value="<?= e($d['suburbs']) ?>">
        <label>Outside Dhaka</label><input type="number" step="0.01" min="0" name="d_outside_dhaka" value="<?= e($d['outside_dhaka']) ?>">
        <label>Free weight (kg)</label><input type="number" step="0.01" min="0" name="d_free_weight_kg" value="<?= e($d['free_weight_kg']) ?>">
        <label>Extra per kg</label><input type="number" step="0.01" min="0" name="d_extra_per_kg" value="<?= e($d['extra_per_kg']) ?>"></div>
    </div><p style="margin-top:14px"><button class="btn btn-primary btn-sm">Save &amp; send</button></p></form></div>
<?php endif; ?>


<?php if (in_array($st, ['active', 'paused'], true)): $canM = book_can($book, 'integrations', 'manage'); ?>
<div class="int-card"><h2>Reconcile</h2>
  <p class="sub">Every hour the book compares itself with the website. A stock difference is fixed with a clearly marked adjustment (the book's quantity wins); anything else is only reported.
    <?= !empty($conn['last_reconcile_at']) ? 'Last run: ' . e(fmt_datetime($conn['last_reconcile_at'])) . ' UTC.' : 'It has not run yet.' ?></p>
  <?php if ($recon): ?>
    <?php if (!$recon['ok']): ?><p style="color:#b91c1c">The last run could not finish: <?= e($recon['error']) ?></p>
    <?php else: ?><p><b><?= (int)$recon['drift_total'] ?></b> difference(s) found · <b><?= (int)$recon['stock']['corrected'] ?></b> stock correction(s)<?= !empty($recon['stock']['deferred']) ? ' · stock fixes waited because changes were still in flight' : '' ?><?= !empty($recon['truncated']) ? ' · very large store: only part was checked' : '' ?></p>
      <?php foreach ($recon['entities'] as $en => $r): if (!($r['missing_at_book_n'] || $r['missing_at_store_n'] || $r['different_n'])) continue; ?>
        <p style="margin:4px 0"><b><?= e($entityNames[$en] ?? $en) ?></b>:
          <?php if ($r['missing_at_book_n']): ?>only on the website <?= (int)$r['missing_at_book_n'] ?> (<?= e(implode(', ', $r['missing_at_book'])) ?>) · <?php endif; ?>
          <?php if ($r['missing_at_store_n']): ?>sent but missing on the website <?= (int)$r['missing_at_store_n'] ?> (<?= e(implode(', ', $r['missing_at_store'])) ?>) · <?php endif; ?>
          <?php if ($r['different_n']): ?>different values <?= (int)$r['different_n'] ?> (<?= e(implode('; ', $r['different'])) ?>)<?php endif; ?></p>
      <?php endforeach; ?>
      <?php foreach ($recon['stock']['drift'] as $d): ?><p style="margin:4px 0"><small>Stock — <?= e($d['product']) ?>: book <?= (int)$d['book'] ?>, website <?= (int)$d['store'] ?> <?= $d['corrected'] ? '→ corrected' : '(not changed yet)' ?></small></p><?php endforeach; ?>
    <?php endif; ?>
  <?php endif; ?>
  <?php if ($canM && $st === 'active'): ?><form method="post" action="<?= $base ?>/reconcile"><?= $csrf ?><button class="btn btn-secondary btn-sm">Reconcile now</button></form><?php endif; ?>
</div>

<div class="int-card" id="import"><h2>History import <small style="font-weight:400">(optional)</small></h2>
  <p class="sub">Bring past sales and customers across, in either direction. Always starts with a dry run that writes nothing. Imported history never changes current stock, and one batch can be rolled back.</p>
  <?php if ($batch): $pl = json_decode($batch['plan'] ?? '[]', true) ?: []; $pg = json_decode($batch['progress'] ?? '[]', true) ?: []; ?>
    <div style="border:1px solid var(--border,#e5e7eb);border-radius:10px;padding:14px;margin-bottom:14px">
      <b>Batch #<?= (int)$batch['id'] ?></b> · <?= $batch['direction'] === 'book_to_store' ? 'book → website' : 'website → book' ?> · <?= e(str_replace(',', ' + ', $batch['entities'])) ?>
      <?= $batch['date_from'] || $batch['date_to'] ? ' · ' . e(($batch['date_from'] ?: 'beginning') . ' to ' . ($batch['date_to'] ?: 'now')) : ' · all dates' ?> · <b><?= e(str_replace('_', ' ', $batch['status'])) ?></b>
      <?php if (!empty($pl['counts'])): ?><div class="int-grid" style="margin:10px 0"><?php foreach ($pl['counts'] as $k => $v): ?><div class="int-stat"><b><?= (int)$v ?></b><span><?= e(str_replace('_', ' ', $k)) ?></span></div><?php endforeach; ?></div><?php endif; ?>
      <?php foreach ($pl['warnings'] ?? [] as $w): ?><p style="margin:4px 0"><small>• <?= e($w) ?></small></p><?php endforeach; ?>
      <?php if ($batch['status'] !== 'dry_run'): ?><p style="margin:10px 0 4px"><b><?= (int)($pg['done'] ?? 0) ?></b> done · <?= (int)($pg['skipped'] ?? 0) ?> skipped · <span style="color:<?= !empty($pg['failed']) ? '#b91c1c' : 'inherit' ?>"><?= (int)($pg['failed'] ?? 0) ?> failed</span><?= !empty($pg['fatal']) ? ' · <span style="color:#b91c1c">' . e($pg['fatal']) . '</span>' : '' ?></p>
        <?php foreach (array_slice((array)($pg['errors'] ?? []), -5) as $er): ?><p style="margin:2px 0;color:#b91c1c"><small><?= e($er) ?></small></p><?php endforeach; ?><?php endif; ?>
      <?php if ($canM): ?><div class="int-row" style="margin-top:10px">
        <?php $ia = $base . '/import/' . (int)$batch['id']; $btn = fn ($a, $label, $cls = 'btn-secondary', $confirm = '') => '<form method="post" action="' . $ia . '/' . $a . '"' . ($confirm ? ' onsubmit="return confirm(\'' . $confirm . '\')"' : '') . '>' . $csrf . '<button class="btn ' . $cls . ' btn-sm">' . $label . '</button></form>'; ?>
        <?php if ($batch['status'] === 'dry_run') echo $btn('start', 'Start the import', 'btn-primary', 'Start the real import now?');
              elseif ($batch['status'] === 'running') echo $btn('step', 'Do a bit more now', 'btn-primary') . $btn('pause', 'Pause');
              elseif ($batch['status'] === 'paused') echo $btn('resume', 'Resume', 'btn-primary');
              if (in_array($batch['status'], ['done', 'paused', 'failed'], true)) echo $btn('rollback', 'Roll back this batch', 'btn-danger', 'Undo exactly this batch?'); ?>
      </div><?php endif; ?>
    </div>
  <?php endif; ?>
  <?php if ($canM): ?>
  <form method="post" action="<?= $base ?>/import/plan" class="int-form"><?= $csrf ?>
    <label>Direction</label>
    <label class="int-opt"><input type="radio" name="direction" value="store_to_book" checked> <span>Website → this book<small>Past online orders (with payments and returns) and customers.</small></span></label>
    <label class="int-opt"><input type="radio" name="direction" value="book_to_store"> <span>This book → website<small>Past sales to customers who have a name and phone, plus customers.</small></span></label>
    <label>What</label>
    <label class="int-opt"><input type="checkbox" name="entities[]" value="customers" checked> <span>Customers</span></label>
    <label class="int-opt"><input type="checkbox" name="entities[]" value="orders" checked> <span>Orders / sales</span></label>
    <div class="int-two"><div><label>From (optional)</label><input type="date" name="date_from"></div><div><label>To (optional)</label><input type="date" name="date_to"></div></div>
    <p style="margin-top:14px"><button class="btn btn-secondary btn-sm">Run a dry run</button></p>
  </form>
  <?php endif; ?>
  <?php if ($batches): ?><table class="int-table" style="margin-top:12px"><tr><th>Batch</th><th>Direction</th><th>Status</th><th></th></tr>
    <?php foreach ($batches as $x): ?><tr><td>#<?= (int)$x['id'] ?> <small><?= e(fmt_datetime($x['created_at'])) ?></small></td><td><?= $x['direction'] === 'book_to_store' ? 'book → website' : 'website → book' ?></td><td><?= e(str_replace('_', ' ', $x['status'])) ?></td><td><a href="<?= $base ?>?batch=<?= (int)$x['id'] ?>#import">Open</a></td></tr><?php endforeach; ?></table><?php endif; ?>
</div>
<?php endif; ?>

<?php if ($conflicts): ?>
<div class="int-card"><h2>Needs your review (<?= count($conflicts) ?>)</h2><p class="sub">The sync refused to guess. Nothing was changed for these.</p>
<table class="int-table"><tr><th>What</th><th>Why</th><th></th></tr>
<?php foreach ($conflicts as $c): $loc = json_decode($c['local_data'] ?? '[]', true) ?: []; ?>
<tr><td><b><?= e($entityNames[$c['entity']] ?? $c['entity']) ?></b><br><small><?= e(str_replace('_', ' ', $c['kind'])) ?> · <?= e(fmt_datetime($c['created_at'])) ?></small></td>
<td><?= e($c['note']) ?><?php if ($c['kind'] === 'customer_match' && !empty($loc['name'])): ?><br><small>Existing customer: <?= e($loc['name']) ?> <?= e($loc['phone'] ?? '') ?></small><?php endif; ?></td>
<td><?php if (book_can($book, 'integrations', 'manage')): ?><form method="post" action="<?= $base ?>/conflicts/<?= (int)$c['id'] ?>/resolve" class="int-row"><?= $csrf ?>
  <?php if ($c['kind'] === 'customer_match'): ?><button name="action" value="link" class="btn btn-primary btn-sm">Same person — link</button><button name="action" value="separate" class="btn btn-secondary btn-sm">Different person</button>
  <?php else: ?><button name="action" value="dismiss" class="btn btn-secondary btn-sm">Mark reviewed</button><?php endif; ?></form><?php endif; ?></td></tr>
<?php endforeach; ?></table></div>
<?php endif; ?>

<?php if ($dead): ?>
<div class="int-card"><h2>Failed to send (<?= count($dead) ?>)</h2><p class="sub">These gave up after several tries or were refused for good.</p>
<table class="int-table"><tr><th>What</th><th>Error</th><th></th></tr>
<?php foreach ($dead as $r): ?><tr><td><?= e($entityNames[$r['entity']] ?? $r['entity']) ?> · <?= e($r['op']) ?><br><small><?= e(fmt_datetime($r['created_at'])) ?></small></td><td><?= e($r['last_error']) ?></td>
<td><?php if (book_can($book, 'integrations', 'manage')): ?><form method="post" action="<?= $base ?>/queue/retry" style="display:inline"><?= $csrf ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-secondary btn-sm">Retry</button></form>
<form method="post" action="<?= $base ?>/queue/discard" style="display:inline"><?= $csrf ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-secondary btn-sm">Discard</button></form><?php endif; ?></td></tr><?php endforeach; ?></table>
<?php if (book_can($book, 'integrations', 'manage') && count($dead) > 1): ?><form method="post" action="<?= $base ?>/queue/retry" style="margin-top:10px"><?= $csrf ?><button class="btn btn-primary btn-sm">Retry all</button></form><?php endif; ?></div>
<?php endif; ?>

<?php if ($log): ?>
<div class="int-card"><h2>Recent activity</h2><p class="sub">Personal details are masked in this log.</p>
<table class="int-table"><?php foreach ($log as $l): ?><tr><td style="white-space:nowrap"><small><?= e(fmt_datetime($l['created_at'])) ?></small></td>
<td><?= $l['direction'] === 'in' ? '⬇ from site' : ($l['direction'] === 'out' ? '⬆ to site' : '⚙ system') ?></td><td style="color:<?= $l['ok'] ? 'inherit' : '#b91c1c' ?>"><?= e($l['summary']) ?></td></tr><?php endforeach; ?></table></div>
<?php endif; ?>
<?php endif; ?>

<?php $content = ob_get_clean(); require BASE_PATH . '/views/partials/layout.php'; ?>
