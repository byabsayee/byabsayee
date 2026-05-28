<?php
// views/books/business.php
// $book, $details, $stats are set by BookController::showBusiness()

$pageTitle = e($details['business_name'] ?? $book['name']) . ' — Byabsayee';
ob_start();

$bookId = $book['id'];

// ── Permission helpers (available from BookController) ────────────────────
$isOwner  = !empty($viewPerms['__owner__']);
$vCan     = function(string $module, string $action) use ($viewPerms, $isOwner): bool {
    if ($isOwner) return true;
    return !empty($viewPerms[$module][$action]);
};

// ── Currency symbol ────────────────────────────────────────────────────────
$defaultCur = \App\Helpers\Database::row(
    'SELECT symbol FROM book_currencies WHERE book_id=? AND is_default=1 LIMIT 1', [$bookId]
);
$sym = $defaultCur['symbol'] ?? '৳';

// ── Current month range ────────────────────────────────────────────────────
$monthStart = date('Y-m-01');
$monthEnd   = date('Y-m-t');

// ── Financial totals (current month) ──────────────────────────────────────
// TOTAL IN = funds(in) + debts taken(=money received) + sales(paid) + purchase returns refunded
// TOTAL OUT = funds(out) + expenses + purchases(paid) + sales returns refunded
// AVAILABLE = Total In - Total Out
// TOTAL DUES = outstanding in dues table (what customers owe) + sales invoice outstanding
// TOTAL DEBTS = outstanding in debts table (what we owe) + purchase invoice outstanding

$totalIn  = 0.0;
$totalOut = 0.0;
$totalDues  = 0.0;
$totalDebts = 0.0;

// Funds IN (current month)
try {
    $r = \App\Helpers\Database::row(
        "SELECT COALESCE(SUM(amount),0) AS n FROM funds WHERE book_id=? AND type='in' AND fund_date BETWEEN ? AND ?",
        [$bookId, $monthStart, $monthEnd]
    );
    $totalIn += (float)($r['n'] ?? 0);
} catch (\Throwable $e) {}

// Debts created this month = cash received as loans
try {
    $r = \App\Helpers\Database::row(
        "SELECT COALESCE(SUM(amount),0) AS n FROM debts WHERE book_id=? AND created_at BETWEEN ? AND ?",
        [$bookId, $monthStart . ' 00:00:00', $monthEnd . ' 23:59:59']
    );
    $totalIn += (float)($r['n'] ?? 0);
} catch (\Throwable $e) {}

// Sales paid this month
try {
    $r = \App\Helpers\Database::row(
        "SELECT COALESCE(SUM(paid),0) AS n FROM invoices WHERE book_id=? AND type='sale' AND deleted_at IS NULL AND date BETWEEN ? AND ?",
        [$bookId, $monthStart, $monthEnd]
    );
    $totalIn += (float)($r['n'] ?? 0);
} catch (\Throwable $e) {}

// Purchase returns refunded this month
try {
    $r = \App\Helpers\Database::row(
        "SELECT COALESCE(SUM(total_refund),0) AS n FROM returns WHERE book_id=? AND type='purchase_return' AND deleted_at IS NULL AND date BETWEEN ? AND ?",
        [$bookId, $monthStart, $monthEnd]
    );
    $totalIn += (float)($r['n'] ?? 0);
} catch (\Throwable $e) {}

// Funds OUT (current month)
try {
    $r = \App\Helpers\Database::row(
        "SELECT COALESCE(SUM(amount),0) AS n FROM funds WHERE book_id=? AND type='out' AND fund_date BETWEEN ? AND ?",
        [$bookId, $monthStart, $monthEnd]
    );
    $totalOut += (float)($r['n'] ?? 0);
} catch (\Throwable $e) {}

// Expenses (current month)
try {
    $r = \App\Helpers\Database::row(
        "SELECT COALESCE(SUM(amount),0) AS n FROM expenses WHERE book_id=? AND expense_date BETWEEN ? AND ?",
        [$bookId, $monthStart, $monthEnd]
    );
    $totalOut += (float)($r['n'] ?? 0);
} catch (\Throwable $e) {}

// Purchases paid this month
try {
    $r = \App\Helpers\Database::row(
        "SELECT COALESCE(SUM(paid),0) AS n FROM invoices WHERE book_id=? AND type='purchase' AND deleted_at IS NULL AND date BETWEEN ? AND ?",
        [$bookId, $monthStart, $monthEnd]
    );
    $totalOut += (float)($r['n'] ?? 0);
} catch (\Throwable $e) {}

// Sales returns refunded this month
try {
    $r = \App\Helpers\Database::row(
        "SELECT COALESCE(SUM(total_refund),0) AS n FROM returns WHERE book_id=? AND type='sales_return' AND deleted_at IS NULL AND date BETWEEN ? AND ?",
        [$bookId, $monthStart, $monthEnd]
    );
    $totalOut += (float)($r['n'] ?? 0);
} catch (\Throwable $e) {}

$availableFunds = $totalIn - $totalOut;

// TOTAL DUES: dues table outstanding + sales invoices outstanding (cumulative)
try {
    $r = \App\Helpers\Database::row(
        "SELECT COALESCE(SUM(amount - paid_amount),0) AS n FROM dues WHERE book_id=? AND status IN ('unpaid','partial')",
        [$bookId]
    );
    $totalDues += (float)($r['n'] ?? 0);
} catch (\Throwable $e) {}

try {
    $r = \App\Helpers\Database::row(
        "SELECT COALESCE(SUM(total - paid),0) AS n FROM invoices WHERE book_id=? AND type='sale' AND status NOT IN ('paid','cancelled') AND deleted_at IS NULL",
        [$bookId]
    );
    $totalDues += (float)($r['n'] ?? 0);
} catch (\Throwable $e) {}

// TOTAL DEBTS: debts table outstanding + purchase invoices outstanding (cumulative)
try {
    $r = \App\Helpers\Database::row(
        "SELECT COALESCE(SUM(amount - paid_amount),0) AS n FROM debts WHERE book_id=? AND status IN ('unpaid','partial')",
        [$bookId]
    );
    $totalDebts += (float)($r['n'] ?? 0);
} catch (\Throwable $e) {}

try {
    $r = \App\Helpers\Database::row(
        "SELECT COALESCE(SUM(total - paid),0) AS n FROM invoices WHERE book_id=? AND type='purchase' AND status NOT IN ('paid','cancelled') AND deleted_at IS NULL",
        [$bookId]
    );
    $totalDebts += (float)($r['n'] ?? 0);
} catch (\Throwable $e) {}

// ── Recent Activity from activity_log table ───────────────────────────────
$recentActivity = \App\Services\ActivityLogger::recent($bookId, 20);

// Add href to each log entry
foreach ($recentActivity as &$act) {
    if (!isset($act['href'])) {
        $action = $act['action'] ?? '';
        $sid    = $act['subject_id'] ?? null;
        if (str_starts_with($action, 'invoice'))   $act['href'] = $sid ? '/books/'.$bookId.'/invoices/'.$sid : '/books/'.$bookId.'/invoices';
        elseif (str_starts_with($action, 'fund'))  $act['href'] = '/books/'.$bookId.'/funds';
        elseif (str_starts_with($action, 'expense')) $act['href'] = '/books/'.$bookId.'/expenses';
        elseif (str_starts_with($action, 'due'))   $act['href'] = '/books/'.$bookId.'/dues';
        elseif (str_starts_with($action, 'debt'))  $act['href'] = '/books/'.$bookId.'/debts';
        elseif (str_starts_with($action, 'customer')) $act['href'] = $sid ? '/books/'.$bookId.'/customers/'.$sid : '/books/'.$bookId.'/customers';
        elseif (str_starts_with($action, 'supplier')) $act['href'] = $sid ? '/books/'.$bookId.'/suppliers/'.$sid : '/books/'.$bookId.'/suppliers';
        elseif (str_starts_with($action, 'product'))  $act['href'] = '/books/'.$bookId.'/products';
        elseif (str_starts_with($action, 'employee')) $act['href'] = $sid ? '/books/'.$bookId.'/employees/'.$sid : '/books/'.$bookId.'/employees';
        elseif (str_starts_with($action, 'return'))   $act['href'] = $sid ? '/books/'.$bookId.'/returns/'.$sid : '/books/'.$bookId.'/returns';
        elseif (str_starts_with($action, 'coupon'))   $act['href'] = '/books/'.$bookId.'/coupons';
        else $act['href'] = '/books/'.$bookId.'/logs';
    }
}
unset($act);
?>

<!-- ── Header ───────────────────────────────────────────────────────────── -->
<div class="biz-header">
    <div class="biz-header-left">
        <?php if (!empty($book['logo'])): ?>
        <img src="<?= asset('uploads/' . $book['logo']) ?>"
             class="biz-logo" onerror="this.style.display='none'" alt="">
        <?php endif; ?>
        <div>
            <h1 class="biz-title">
                <span class="biz-dot" style="background:<?= e($book['color']) ?>"></span>
                <?= e($details['business_name'] ?? $book['name']) ?>
            </h1>
            <div class="biz-sub">Business Book &bull; <?= date('F Y') ?></div>
        </div>
    </div>
    <div class="biz-header-actions">
        <?php if ($vCan('invoices','create')): ?>
        <a href="/books/<?= $bookId ?>/invoices/create?type=sale" class="btn btn-primary">
            <i class="fa-solid fa-plus"></i> Sell
        </a>
        <a href="/books/<?= $bookId ?>/invoices/create?type=purchase" class="btn btn-secondary">
            <i class="fa-solid fa-cart-shopping"></i> Purchase
        </a>
        <?php endif; ?>
        <button class="biz-notif-btn" onclick="openNotifPanel(event)" title="Notifications">
            <i class="fa-solid fa-bell"></i>
        </button>
    </div>
</div>

<!-- ── Financial stat row (current month) ─────────────────────────────── -->
<?php if ($vCan('funds','view') || $vCan('dues','view') || $vCan('debts','view') || $vCan('expenses','view')): ?>
<p class="section-label"><i class="fa-solid fa-rectangle-list"></i> Summary — <?= date('F Y') ?></p>
<div class="dash-stat-grid" style="margin-bottom:12px">
<?php if ($vCan('funds','view') || $vCan('expenses','view')): ?>
    <div class="stat-card" style="border-top:3px solid <?= $availableFunds >= 0 ? 'var(--brand)' : 'var(--red)' ?>">
        <div class="stat-card-icon"
             style="background:<?= $availableFunds >= 0 ? 'var(--brand-light)' : 'var(--red-bg)' ?>;
                    color:<?= $availableFunds >= 0 ? 'var(--brand)' : 'var(--red)' ?>">
            <i class="fa-solid fa-wallet"></i>
        </div>
        <div style="min-width:0;flex:1">
            <div class="stat-label">Available Funds</div>
            <div class="stat-value <?= $availableFunds >= 0 ? 'brand' : 'red' ?>">
                <?= format_money($availableFunds, $sym) ?>
            </div>
            <div style="font-size:10px;color:var(--text-muted);margin-top:2px">In − Out this month</div>
        </div>
    </div>
    <?php endif; // funds/expenses ?>

    <?php if ($vCan('funds','view')): ?>
    <a href="/books/<?= $bookId ?>/funds" class="stat-card stat-card-link"
       style="border-top:3px solid var(--green)">
        <div class="stat-card-icon" style="background:var(--green-bg);color:var(--green)">
            <i class="fa-solid fa-arrow-trend-up"></i>
        </div>
        <div style="min-width:0;flex:1">
            <div class="stat-label">Total In</div>
            <div class="stat-value green"><?= format_money($totalIn, $sym) ?></div>
            <div style="font-size:10px;color:var(--text-muted);margin-top:2px">Funds + Loans + Sales + P.Returns</div>
        </div>
    </a>
    <?php endif; // funds.view ?>

    <?php if ($vCan('expenses','view')): ?>
    <a href="/books/<?= $bookId ?>/expenses" class="stat-card stat-card-link"
       style="border-top:3px solid var(--red)">
        <div class="stat-card-icon" style="background:var(--red-bg);color:var(--red)">
            <i class="fa-solid fa-arrow-trend-down"></i>
        </div>
        <div style="min-width:0;flex:1">
            <div class="stat-label">Total Out</div>
            <div class="stat-value red"><?= format_money($totalOut, $sym) ?></div>
            <div style="font-size:10px;color:var(--text-muted);margin-top:2px">Expenses + Purchases + S.Returns</div>
        </div>
    </a>
    <?php endif; // expenses.view ?>

    <?php if ($vCan('dues','view')): ?>
    <a href="/books/<?= $bookId ?>/dues" class="stat-card stat-card-link"
       style="border-top:3px solid var(--amber)">
        <div class="stat-card-icon" style="background:var(--amber-bg);color:var(--amber)">
            <i class="fa-solid fa-hand-holding-dollar"></i>
        </div>
        <div style="min-width:0;flex:1">
            <div class="stat-label">Total Dues</div>
            <div class="stat-value amber"><?= format_money($totalDues, $sym) ?></div>
            <div style="font-size:10px;color:var(--text-muted);margin-top:2px">Dues + Sales outstanding</div>
        </div>
    </a>
    <?php endif; // dues.view ?>

    <?php if ($vCan('debts','view')): ?>
    <a href="/books/<?= $bookId ?>/debts" class="stat-card stat-card-link"
       style="border-top:3px solid var(--red)">
        <div class="stat-card-icon" style="background:var(--red-bg);color:var(--red)">
            <i class="fa-solid fa-file-circle-minus"></i>
        </div>
        <div style="min-width:0;flex:1">
            <div class="stat-label">Total Debts</div>
            <div class="stat-value red"><?= format_money($totalDebts, $sym) ?></div>
            <div style="font-size:10px;color:var(--text-muted);margin-top:2px">Debts + Purchase outstanding</div>
        </div>
    </a>
    <?php endif; // debts.view ?>

</div>
<?php endif; // any financial perm ?>

<!-- ── Count cards ────────────────────────────────────────────────────── -->
<?php
$countCards = [
    ['perm'=>['invoices','view'],   'href'=>'/sales',      'icon'=>'fa-arrow-trend-up',    'color'=>'#10b981', 'val'=>$stats['sales']??0,     'label'=>'Sales'],
    ['perm'=>['invoices','view'],   'href'=>'/purchases',  'icon'=>'fa-cart-shopping',      'color'=>'#3b82f6', 'val'=>$stats['purchases']??0,  'label'=>'Purchases'],
    ['perm'=>['returns','view'],    'href'=>'/returns',    'icon'=>'fa-rotate-left',         'color'=>'#ff0080', 'val'=>$stats['returns']??0,    'label'=>'Returns'],
    ['perm'=>['products','view'],   'href'=>'/products',   'icon'=>'fa-box',                 'color'=>'#15ff00', 'val'=>$stats['products']??0,   'label'=>'Products'],
    ['perm'=>['customers','view'],  'href'=>'/customers',  'icon'=>'fa-users',               'color'=>'#3b82f6', 'val'=>$stats['customers']??0,  'label'=>'Customers'],
    ['perm'=>['suppliers','view'],  'href'=>'/suppliers',  'icon'=>'fa-user-tie',            'color'=>'#8b5cf6', 'val'=>$stats['suppliers']??0,  'label'=>'Suppliers'],
    ['perm'=>['employees','view'],  'href'=>'/employees',  'icon'=>'fa-id-badge',            'color'=>'#6366f1', 'val'=>$stats['employees']??0,  'label'=>'Employees'],
    ['perm'=>['coupons','view'],    'href'=>'/coupons',    'icon'=>'fa-ticket',              'color'=>'#d4ec69', 'val'=>$stats['coupons']??0,    'label'=>'Coupons'],
    ['perm'=>['deliveries','view'], 'href'=>'/deliveries', 'icon'=>'fa-truck-fast',          'color'=>'#5409aa', 'val'=>0,                       'label'=>'Deliveries', 'soon'=>true],
];
$visibleCountCards = array_filter($countCards, fn($cc) => $vCan($cc['perm'][0], $cc['perm'][1]));
?>
<?php if (!empty($visibleCountCards)): ?>
<div class="dash-count-grid" style="margin-bottom:22px">
<?php foreach ($visibleCountCards as $cc): ?>
    <a href="/books/<?= $bookId ?><?= $cc['href'] ?>" class="count-card<?= !empty($cc['soon']) ? ' nav-item-soon' : '' ?>">
        <i class="fa-solid <?= $cc['icon'] ?>" style="color:<?= $cc['color'] ?>"></i>
        <span class="count-val"><?= number_format((int)$cc['val']) ?></span>
        <span class="count-label"><?= $cc['label'] ?></span>
    </a>
<?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ── Modules ──────────────────────────────────────────────────────────── -->
<?php
$allModules = [
    ['perm'=>['invoices','view'],   'icon'=>'fa-file-invoice',       'color'=>'#10b981','label'=>'Invoices',  'sub'=>'Sales & Purchases'],
    ['perm'=>['products','view'],   'icon'=>'fa-box',                'color'=>'#15ff00','label'=>'Products',  'sub'=>'Inventory'],
    ['perm'=>['funds','view'],      'icon'=>'fa-piggy-bank',         'color'=>'#0ea5e9','label'=>'Funds',     'sub'=>'Cash Flow'],
    ['perm'=>['expenses','view'],   'icon'=>'fa-receipt',            'color'=>'#ef4444','label'=>'Expenses',  'sub'=>'Costs & Spending'],
    ['perm'=>['dues','view'],       'icon'=>'fa-hand-holding-dollar','color'=>'#d97706','label'=>'Dues',      'sub'=>'Owed by Others'],
    ['perm'=>['debts','view'],      'icon'=>'fa-file-circle-minus',  'color'=>'#dc2626','label'=>'Debts',     'sub'=>'Owed to Others'],
    ['perm'=>['customers','view'],  'icon'=>'fa-users',              'color'=>'#3b82f6','label'=>'Customers', 'sub'=>'Client Directory'],
    ['perm'=>['suppliers','view'],  'icon'=>'fa-user-tie',           'color'=>'#8b5cf6','label'=>'Suppliers', 'sub'=>'Vendor Directory'],
    ['perm'=>['employees','view'],  'icon'=>'fa-id-badge',           'color'=>'#6366f1','label'=>'Employees', 'sub'=>'HR & Payroll'],
    ['perm'=>['contacts','view'],   'icon'=>'fa-address-book',       'color'=>'#f97316','label'=>'Contacts',  'sub'=>'Everyone Known'],
    ['perm'=>['returns','view'],    'icon'=>'fa-rotate-left',        'color'=>'#ff0080','label'=>'Returns',   'sub'=>'Returned Goods'],
    ['perm'=>['coupons','view'],    'icon'=>'fa-ticket',             'color'=>'#d4ec69','label'=>'Coupons',   'sub'=>'Discount Codes'],
    ['perm'=>['deliveries','view'], 'icon'=>'fa-truck-fast',         'color'=>'#5409aa','label'=>'Deliveries','sub'=>'Shipment Tracking'],
    ['perm'=>['reports','view'],    'icon'=>'fa-chart-line',         'color'=>'#14b8a6','label'=>'Reports',   'sub'=>'Profit & Loss'],
    ['perm'=>['logs','view'],       'icon'=>'fa-clock-rotate-left',  'color'=>'#64748b','label'=>'Activity Log','sub'=>'Audit Trail'],
];
$visibleModules = array_filter($allModules, fn($m) => $vCan($m['perm'][0], $m['perm'][1]));
?>
<?php if (!empty($visibleModules)): ?>
<p class="section-label"><i class="fa-solid fa-grip"></i> Modules</p>
<div class="modules-grid" style="margin-bottom:22px">
<?php foreach ($visibleModules as $m): ?>
<?php $mUrl = '/books/'.$bookId.'/'.strtolower(str_replace(' ','-',$m['perm'][0])); ?>
<a href="<?= $mUrl ?>" class="module-card">
    <div class="module-icon" style="background:<?= $m['color'] ?>22;color:<?= $m['color'] ?>">
        <i class="fa-solid <?= $m['icon'] ?>"></i>
    </div>
    <div class="module-body">
        <div class="module-label"><?= $m['label'] ?></div>
        <div class="module-sub"><?= $m['sub'] ?></div>
    </div>
</a>
<?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ── Search ──────────────────────────────────────────────────────────── -->
<p class="section-label"><i class="fa-solid fa-magnifying-glass"></i> Discover</p>
<div style="margin-bottom:22px;position:relative;max-width:560px">
    <div style="position:relative">
        <i class="fa-solid fa-magnifying-glass"
           style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--text-muted);font-size:13px;pointer-events:none"></i>
        <input type="text" id="bookSearch"
               placeholder="Search invoices, customers, products, expenses, coupons…"
               oninput="doBookSearch(this.value)" autocomplete="off"
               style="width:100%;padding:10px 12px 10px 36px;border:2px solid var(--border);
                      border-radius:var(--radius);font-size:13.5px;font-family:inherit;
                      outline:none;transition:border-color .15s"
               onfocus="this.style.borderColor='var(--brand)'"
               onblur="this.style.borderColor='var(--border)'">
    </div>
    <div id="searchResults"
         style="display:none;position:absolute;top:calc(100% + 4px);left:0;right:0;
                background:var(--white);border:1px solid var(--border);border-radius:var(--radius);
                box-shadow:var(--shadow-md);max-height:320px;overflow-y:auto;z-index:200"></div>
</div>

<!-- ── Recent Activity ────────────────────────────────────────────────── -->
<?php if ($vCan('logs','view')): ?>
<div style="background:var(--white);border:1px solid var(--border);border-radius:var(--radius-lg);overflow:hidden">
    <a href="/books/<?= $bookId ?>/logs"
       style="display:flex;align-items:center;justify-content:space-between;padding:14px 18px;
              border-bottom:1px solid var(--border);text-decoration:none;color:inherit;
              transition:background .12s"
       onmouseover="this.style.background='var(--bg)'" onmouseout="this.style.background=''">
        <h3 style="font-size:14px;font-weight:700;display:flex;align-items:center;gap:8px;margin:0">
            <i class="fa-solid fa-clock-rotate-left" style="color:var(--brand)"></i>
            Recent Activity
        </h3>
        <span style="font-size:12px;color:var(--brand);display:flex;align-items:center;gap:4px">
            View All Logs <i class="fa-solid fa-arrow-right" style="font-size:10px"></i>
        </span>
    </a>
    <div style="padding:4px 18px 12px">
        <?php if (empty($recentActivity)): ?>
        <div class="empty-state" style="padding:28px 0">
            <div class="empty-icon"><i class="fa-solid fa-clock"></i></div>
            <p>No activities yet. Actions will appear here as they happen.</p>
        </div>
        <?php else: ?>
        <div class="activity-feed">
            <?php foreach ($recentActivity as $act): ?>
            <a href="<?= $act['href'] ?? '#' ?>" class="activity-link" style="text-decoration:none">
            <div class="activity-item">
                <div class="activity-icon" style="color:<?= $act['icon_color'] ?? 'var(--text-muted)' ?>">
                    <i class="fa-solid <?= $act['icon'] ?? 'fa-circle-dot' ?>"></i>
                </div>
                <div class="activity-body">
                    <div class="activity-desc"><?= e($act['description'] ?? $act['action'] ?? 'Activity') ?></div>
                    <div class="activity-meta">
                        <strong><?= e($act['user_name'] ?? 'System') ?></strong>
                        · <?= e(date('d M Y, g:i a', strtotime($act['created_at']))) ?>
                    </div>
                </div>
            </div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; // logs.view ?>

<!-- ── Styles ─────────────────────────────────────────────────────────── -->
<style>
.biz-header{display:flex;align-items:center;justify-content:space-between;gap:14px;margin-bottom:22px;flex-wrap:wrap}
.biz-header-left{display:flex;align-items:center;gap:12px;flex:1;min-width:0}
.biz-logo{height:40px;max-width:100px;object-fit:contain;border-radius:6px}
.biz-title{font-size:20px;font-weight:700;display:flex;align-items:center;gap:8px;margin:0}
.biz-dot{display:inline-block;width:10px;height:10px;border-radius:50%;flex-shrink:0}
.biz-sub{font-size:12px;color:var(--text-muted);margin-top:2px}
.biz-header-actions{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.biz-notif-btn{width:36px;height:36px;border-radius:var(--radius);border:1.5px solid var(--border);background:var(--white);color:var(--text-muted);cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:15px;transition:background .12s,color .12s;flex-shrink:0}
.biz-notif-btn:hover{background:var(--bg);color:var(--brand)}
.dash-stat-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:12px}
.stat-card{background:var(--white);border:1px solid var(--border);border-radius:var(--radius);padding:14px;display:flex;align-items:center;gap:12px}
.stat-card-link{text-decoration:none;color:inherit;transition:box-shadow .15s,transform .15s}
.stat-card-link:hover{box-shadow:var(--shadow-md);transform:translateY(-1px)}
.stat-card-icon{width:38px;height:38px;border-radius:var(--radius);display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0}
.stat-label{font-size:11.5px;color:var(--text-muted);font-weight:600;text-transform:uppercase;letter-spacing:.03em}
.stat-value{font-size:17px;font-weight:800;margin-top:2px}
.stat-value.brand{color:var(--brand)}.stat-value.green{color:var(--green)}.stat-value.red{color:var(--red)}.stat-value.amber{color:var(--amber)}
.dash-count-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(105px,1fr));gap:10px}
.count-card{background:var(--white);border:1px solid var(--border);border-radius:var(--radius);padding:12px 14px;display:flex;flex-direction:column;align-items:center;gap:4px;text-decoration:none;color:inherit;transition:box-shadow .15s,transform .1s;text-align:center}
.count-card:hover{box-shadow:var(--shadow-md);transform:translateY(-1px)}
.count-card i{font-size:18px;margin-bottom:2px}
.count-val{font-size:18px;font-weight:700;color:var(--text)}
.count-label{font-size:11px;color:var(--text-muted);font-weight:500}
.modules-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(185px,1fr));gap:10px}
.module-card{background:var(--white);border:1px solid var(--border);border-radius:var(--radius);padding:13px 15px;display:flex;align-items:center;gap:12px;text-decoration:none;color:inherit;transition:box-shadow .15s,transform .1s}
.module-card:hover{box-shadow:var(--shadow-md);transform:translateY(-1px)}
.module-icon{width:38px;height:38px;border-radius:var(--radius-sm);display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0}
.module-label{font-size:13.5px;font-weight:600}
.module-sub{font-size:11.5px;color:var(--text-muted);margin-top:1px}
</style>

<script>
let _searchTimer;
function doBookSearch(q) {
    clearTimeout(_searchTimer);
    const box = document.getElementById('searchResults');
    if (!q.trim()) { box.style.display = 'none'; return; }
    _searchTimer = setTimeout(() => {
        fetch('/books/<?= (int)$bookId ?>/search?q=' + encodeURIComponent(q))
            .then(r => r.json())
            .then(data => {
                if (!data.results || !data.results.length) {
                    box.innerHTML = '<div style="padding:14px 16px;color:var(--text-muted);font-size:13px">No results found</div>';
                    box.style.display = 'block';
                    return;
                }
                box.innerHTML = data.results.map(r =>
                    `<a href="${r.url}"
                        style="display:flex;align-items:center;gap:10px;padding:10px 14px;
                               text-decoration:none;color:inherit;border-bottom:1px solid var(--border)"
                        onmouseover="this.style.background='var(--bg)'"
                        onmouseout="this.style.background=''">
                        <span style="font-size:10px;font-weight:700;padding:2px 7px;border-radius:10px;
                                     background:var(--bg);color:var(--text-muted);white-space:nowrap;min-width:80px;text-align:center">
                            ${escHtml(r.type)}
                        </span>
                        <span style="font-size:13px">${escHtml(r.label)}</span>
                    </a>`
                ).join('');
                box.style.display = 'block';
            })
            .catch(() => { box.style.display = 'none'; });
    }, 200);
}
document.addEventListener('click', e => {
    if (!e.target.closest('#bookSearch') && !e.target.closest('#searchResults'))
        document.getElementById('searchResults').style.display = 'none';
});
function escHtml(s) {
    const d = document.createElement('div');
    d.appendChild(document.createTextNode(String(s)));
    return d.innerHTML;
}
</script>

<?php $content = ob_get_clean(); require BASE_PATH . '/views/partials/layout.php'; ?>
