<?php
// Apply user's browser timezone (sent via JS cookie)
if (function_exists('set_timezone_from_cookie')) set_timezone_from_cookie();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? 'Byabsayee') ?></title>
    <link rel="apple-touch-icon" sizes="180x180" href="<?= asset('apple-touch-icon.png') ?>">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= asset('favicon-32x32.png') ?>">
    <link rel="icon" type="image/png" sizes="16x16" href="<?= asset('favicon-16x16.png') ?>">
    <link rel="shortcut icon" href="<?= asset('favicon.ico') ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Bengali:wght@400;600&family=DM+Sans:ital,wght@0,400;0,500;0,600;1,400&display=swap" rel="stylesheet">
    <script src="https://kit.fontawesome.com/86c0c1c09a.js" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
</head>
<body>

<?php
$uri           = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$bookMatch     = [];
$inBook        = preg_match('#^/books/(\d+)#', $uri, $bookMatch);
$currentBookId = $inBook ? (int)$bookMatch[1] : null;

$sidebarBook    = null;
$sidebarDetails = null;
$sidebarPerms   = [];   // [] for non-members, ['__owner__'=>true] for owners, array for members
if ($currentBookId) {
    $sidebarBook = book_for_user($currentBookId);
    if ($sidebarBook) {
        $sidebarPerms = book_member_perms($sidebarBook);
        if ($sidebarBook['type'] === 'business') {
            $sidebarDetails = \App\Helpers\Database::row(
                'SELECT * FROM book_business_details WHERE book_id=?', [$currentBookId]
            );
        }
    }
}
$sidebarIsOwner = !empty($sidebarPerms['__owner__']);

// Helper: can current user access a sidebar nav item?
$sidebarCan = function(string $module, string $action) use ($sidebarPerms, $sidebarIsOwner): bool {
    if ($sidebarIsOwner) return true;
    return !empty($sidebarPerms[$module][$action]);
};

// Safe str_contains that handles null — recomputes URI directly (global scope not reliable in requires)
function navActive(string $path): string {
    $u = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
    return str_contains((string)$u, $path) ? 'active' : '';
}
?>

<!-- ===================== SIDEBAR ===================== -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-top">
        <a href="https://www.byabsayee.com" class="sidebar-logo">
            <div class="s-logo-icon">
                <img src="<?= asset('assets/images/ByabsayeeLogo.png') ?>"
                     onerror="this.parentElement.innerHTML='৳'"
                     style="width:20px;height:20px;object-fit:contain">
            </div>
            <span class="s-logo-text">Byabsayee</span>
        </a>
        <button class="sidebar-collapse-btn" id="sidebarCollapseBtn"
                onclick="toggleSidebar()" title="Collapse sidebar" aria-label="Toggle sidebar">
            <i class="fa-solid fa-angles-left" id="sidebarCollapseIcon"></i>
        </button>
    </div>

    <nav class="sidebar-nav">

        <?php if ($sidebarBook): ?>

        <a href="/dashboard" class="nav-item nav-back" data-label="All Books">
            <i class="fa-solid fa-arrow-left"></i> <span class="nav-text">All Books</span>
        </a>

        <div class="sidebar-book-info">
            <?php if (!empty($sidebarBook['logo'])): ?>
            <img src="<?= asset('uploads/'.$sidebarBook['logo']) ?>"
                 class="sidebar-book-logo" onerror="this.style.display='none'">
            <?php endif; ?>
            <div>
                <div class="sidebar-book-name">
                    <?= e($sidebarDetails['business_name'] ?? $sidebarBook['name']) ?>
                </div>
                <div class="sidebar-book-type">
                    <span class="book-dot" style="background:<?= e($sidebarBook['color']) ?>"></span>
                    <?= e(ucfirst($sidebarBook['type'])) ?> book
                </div>
            </div>
        </div>

        <a href="/books/<?= $currentBookId ?>"
           class="nav-item <?= preg_match('#^/books/'.$currentBookId.'$#', $uri) ? 'active' : '' ?>">
            <i class="fa-solid fa-gauge"></i> Dashboard
        </a>

        <?php if ($sidebarBook['type'] === 'business'): ?>

        <?php
        $invSubActive = in_array(true, [
            (bool)navActive('/books/'.$currentBookId.'/invoices'),
            (bool)navActive('/books/'.$currentBookId.'/sales'),
            (bool)navActive('/books/'.$currentBookId.'/purchases'),
            (bool)navActive('/books/'.$currentBookId.'/returns'),
        ]);
        ?>
        <?php if ($sidebarCan('invoices','view') || $sidebarCan('pos','view')): ?>
        <div class="nav-dropdown <?= $invSubActive ? 'open' : '' ?>" id="navInvoices">
            <div class="nav-dropdown-trigger nav-item <?= $invSubActive ? 'active' : '' ?>">
                <span><a href="/books/<?= $currentBookId ?>/invoices"  class="nav-item <?= $uri === '/books/'.$currentBookId.'/invoices' ? 'active' : '' ?>"><i class="fa-solid fa-file-invoice"></i> Invoices</a></span>
                <i onclick="toggleNavDropdown('navInvoices', event)" class="fa-solid fa-chevron-down nav-chevron"></i>
            </div>
            <div class="nav-dropdown-menu">
                <a href="/books/<?= $currentBookId ?>/sales"     class="nav-sub-item <?= str_contains($uri,'/books/'.$currentBookId.'/sales')     ? 'active' : '' ?>">     <i class="fa-solid fa-arrow-trend-up"></i> Sales</a>
                <a href="/books/<?= $currentBookId ?>/purchases" class="nav-sub-item <?= str_contains($uri,'/books/'.$currentBookId.'/purchases') ? 'active' : '' ?>"> <i class="fa-solid fa-cart-shopping"></i> Purchases</a>
                <a href="/books/<?= $currentBookId ?>/returns"   class="nav-sub-item <?= str_contains($uri,'/books/'.$currentBookId.'/returns')   ? 'active' : '' ?>">   <i class="fa-solid fa-rotate-left"></i> Returns</a>
            </div>
        </div>
        <?php endif; ?>
        <?php if ($sidebarCan('products','view')): ?>
        <a href="/books/<?= $currentBookId ?>/products"    class="nav-item <?= navActive('/books/'.$currentBookId.'/products') ?>">     <i class="fa-solid fa-box"></i> Products</a>
        <?php endif; ?>
        <?php if ($sidebarCan('funds','view')): ?>
        <a href="/books/<?= $currentBookId ?>/funds"       class="nav-item <?= navActive('/books/'.$currentBookId.'/funds') ?>">        <i class="fa-solid fa-piggy-bank"></i> Funds</a>
        <?php endif; ?>
        <?php if ($sidebarCan('expenses','view')): ?>
        <a href="/books/<?= $currentBookId ?>/expenses"    class="nav-item <?= navActive('/books/'.$currentBookId.'/expenses') ?>">     <i class="fa-solid fa-receipt"></i> Expenses</a>
        <?php endif; ?>
        <?php if ($sidebarCan('dues','view')): ?>
        <a href="/books/<?= $currentBookId ?>/dues"        class="nav-item <?= navActive('/books/'.$currentBookId.'/dues') ?>">         <i class="fa-solid fa-hand-holding-dollar"></i> Dues</a>
        <?php endif; ?>
        <?php if ($sidebarCan('debts','view')): ?>
        <a href="/books/<?= $currentBookId ?>/debts"       class="nav-item <?= navActive('/books/'.$currentBookId.'/debts') ?>">        <i class="fa-solid fa-file-circle-minus"></i> Debts</a>
        <?php endif; ?>
        <?php if ($sidebarCan('customers','view')): ?>
        <a href="/books/<?= $currentBookId ?>/customers"   class="nav-item <?= navActive('/books/'.$currentBookId.'/customers') ?>">    <i class="fa-solid fa-users"></i> Customers</a>
        <?php endif; ?>
        <?php if ($sidebarCan('suppliers','view')): ?>
        <a href="/books/<?= $currentBookId ?>/suppliers"   class="nav-item <?= navActive('/books/'.$currentBookId.'/suppliers') ?>">    <i class="fa-solid fa-user-tie"></i> Suppliers</a>
        <?php endif; ?>
        <?php if ($sidebarCan('employees','view')): ?>
        <a href="/books/<?= $currentBookId ?>/employees"   class="nav-item <?= navActive('/books/'.$currentBookId.'/employees') ?>">    <i class="fa-solid fa-id-badge"></i> Employees</a>
        <?php endif; ?>
        <?php if ($sidebarCan('contacts','view')): ?>
        <a href="/books/<?= $currentBookId ?>/contacts"    class="nav-item <?= navActive('/books/'.$currentBookId.'/contacts') ?>">     <i class="fa-solid fa-address-book"></i> Contacts</a>
        <?php endif; ?>
        <?php if ($sidebarCan('coupons','view')): ?>
        <a href="/books/<?= $currentBookId ?>/coupons"     class="nav-item <?= navActive('/books/'.$currentBookId.'/coupons') ?>">      <i class="fa-solid fa-ticket"></i> Coupons</a>
        <?php endif; ?>
        <?php if ($sidebarIsOwner): ?>
        <a href="/books/<?= $currentBookId ?>/deliveries"  class="nav-item <?= navActive('/books/'.$currentBookId.'/deliveries') ?>">   <i class="fa-solid fa-truck-fast"></i> Deliveries</a>
        <?php endif; ?>
        <?php if ($sidebarCan('reports','view')): ?>
        <a href="/books/<?= $currentBookId ?>/reports"     class="nav-item <?= navActive('/books/'.$currentBookId.'/reports') ?>">      <i class="fa-solid fa-chart-line"></i> Reports</a>
        <?php endif; ?>
        <?php if ($sidebarCan('logs','view')): ?>
        <a href="/books/<?= $currentBookId ?>/logs"        class="nav-item <?= navActive('/books/'.$currentBookId.'/logs') ?>">          <i class="fa-solid fa-clock-rotate-left"></i> Activity Log</a>
        <?php endif; ?>

        <?php else: ?>
        <a href="/books/<?= $currentBookId ?>/contacts" class="nav-item <?= navActive('/books/'.$currentBookId.'/contacts') ?>">
            <i class="fa-solid fa-address-book"></i> Contacts
        </a>
        <?php endif; ?>

        <?php if ($sidebarIsOwner): ?>
        <a href="/books/<?= $currentBookId ?>/edit" class="nav-item <?= navActive('/books/'.$currentBookId.'/edit') ?>">
            <i class="fa-solid fa-gear"></i> Book Settings
        </a>
        <?php endif; ?>

        <?php else: ?>

        <div class="sidebar-search-wrap">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" id="bookFilterInput" placeholder="Find a book…"
                   oninput="filterBooks(this.value)">
        </div>

        <a href="/dashboard" class="nav-item <?= activePage('dashboard') ?>" data-label="Dashboard">
            <i class="fa-solid fa-gauge"></i> <span class="nav-text">Dashboard</span>
        </a>

        <?php endif; ?>

    </nav>

    <div class="sidebar-bottom">
        <button onclick="openNotifPanel(event)" class="nav-item" style="width:100%;text-align:left;background:none;border:none;cursor:pointer;position:relative">
            <i class="fa-solid fa-bell"></i> Notifications
            <span class="notif-badge" id="sidebarNotifBadge" style="display:none;position:absolute;top:6px;left:22px"></span>
        </button>
        <a href="/settings" class="nav-item" data-label="App Settings">
            <i class="fa-solid fa-sliders"></i> <span class="nav-text">App Settings</span>
        </a>
        <div class="sidebar-user">
            <a href="/profile" class="s-avatar" style="text-decoration:none;color:#fff;display:flex;align-items:center;justify-content:center;flex-shrink:0" title="My Profile">
                <?php if (!empty(auth()['avatar'])): ?>
                <img src="<?= asset('uploads/'.auth()['avatar']) ?>" style="width:100%;height:100%;border-radius:50%;object-fit:cover" onerror="this.style.display='none';this.parentElement.textContent='<?= mb_strtoupper(mb_substr(auth()['name']??'U',0,1)) ?>'">
                <?php else: ?>
                <?= mb_strtoupper(mb_substr(auth()['name'] ?? 'U', 0, 1)) ?>
                <?php endif; ?>
            </a>
            <div class="s-user-info">
                <div class="s-user-name"><a href="/profile" style="color:inherit;text-decoration:none"><?= e(auth()['name'] ?? '') ?></a></div>
                <div class="s-user-email"><?= e(auth()['email'] ?? '') ?></div>
            </div>
            <a href="/logout" title="Log out" class="s-logout-btn">
                <i class="fa-solid fa-right-from-bracket"></i>
            </a>
        </div>
    </div>
</aside>

<!-- ===================== MAIN ===================== -->
<div class="app-main">
    <div class="mobile-topbar">
        <button class="hamburger" onclick="document.getElementById('sidebar').classList.toggle('open')" aria-label="Menu">
            <span></span><span></span><span></span>
        </button>
        <span class="mobile-title"><?= e($pageTitle ?? 'Byabsayee') ?></span>
        <button onclick="openNotifPanel(event)" class="btn btn-secondary" style="position:relative;padding:6px 10px;margin-left:auto;margin-right:8px">
            <i class="fa-solid fa-bell"></i>
            <span class="notif-badge" id="mobileNotifBadge" style="display:none"></span>
        </button>
    </div>

    <div class="app-content">
        <?php if ($msg = flash('error')): ?>
            <div class="flash flash-error"><i class="fa-solid fa-circle-xmark"></i> <?= e($msg) ?></div>
        <?php endif; ?>
        <?php if ($msg = flash('success')): ?>
            <div class="flash flash-success"><i class="fa-solid fa-circle-check"></i> <?= e($msg) ?></div>
        <?php endif; ?>
        <?php if ($msg = flash('warning')): ?>
            <div class="flash flash-warning"><i class="fa-solid fa-triangle-exclamation"></i> <?= e($msg) ?></div>
        <?php endif; ?>

        <?= $content ?? '' ?>
    </div>
</div>

<div class="sidebar-overlay" onclick="document.getElementById('sidebar').classList.remove('open')"></div>

<?php
// Determine if user can send notifications to this book
$canSendNotification = $currentBookId && ($sidebarIsOwner || $sidebarCan('employees', 'invite'));
?>
<!-- ===================== NOTIFICATION PANEL ===================== -->
<div class="notif-backdrop" id="notifBackdrop" onclick="closeNotifPanel(event)">
    <div class="notif-panel">
        <div class="notif-panel-header">
            <span><i class="fa-solid fa-bell"></i> Notifications</span>
            <div style="display:flex;align-items:center;gap:8px">
                <?php if ($canSendNotification): ?>
                <a href="/books/<?= $currentBookId ?>/notifications/send"
                   class="btn btn-sm btn-primary"
                   style="font-size:12px;padding:4px 10px;white-space:nowrap">
                    <i class="fa-solid fa-paper-plane"></i> Send
                </a>
                <?php endif; ?>
                <button onclick="document.getElementById('notifBackdrop').classList.remove('open')" class="notif-close">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
        </div>
        <div class="notif-panel-body" id="notifPanelBody">
            <div class="notif-loading"><i class="fa-solid fa-spinner fa-spin"></i> Loading…</div>
        </div>
    </div>
</div>

<script src="<?= asset('js/app.js') ?>"></script>
<script>
function filterBooks(q) {
    q = q.toLowerCase();
    document.querySelectorAll('.book-card, .book-row').forEach(el => {
        el.style.display = (!q || el.textContent.toLowerCase().includes(q)) ? '' : 'none';
    });
}

let notifLoaded = false;
window._bookId = <?= $currentBookId ? (int)$currentBookId : 'null' ?>;

// ── Sidebar collapse ────────────────────────────────────────────────────────
(function() {
    var PREF_KEY = 'sidebar_collapsed';

    function initTooltips() {
        // Set data-label on every nav-item from its text content (excluding icon text)
        document.querySelectorAll('#sidebar .nav-item').forEach(function(el) {
            if (el.hasAttribute('data-label')) return;
            // Prefer explicit .nav-text span
            var span = el.querySelector('.nav-text');
            if (span) { el.setAttribute('data-label', span.textContent.trim()); return; }
            // Clone and strip icon elements to get clean text
            var clone = el.cloneNode(true);
            clone.querySelectorAll('i, .notif-badge, .nav-chevron, .nav-dropdown-menu').forEach(function(n){ n.remove(); });
            var label = clone.textContent.replace(/\s+/g, ' ').trim();
            if (label) el.setAttribute('data-label', label);
        });
    }

    function applyCollapsed(collapsed, animate) {
        var sb   = document.getElementById('sidebar');
        var icon = document.getElementById('sidebarCollapseIcon');
        var main = document.querySelector('.app-main');
        if (!sb) return;
        if (collapsed) {
            sb.classList.add('collapsed');
            if (icon) icon.className = 'fa-solid fa-angles-right';
            if (main) main.style.marginLeft = 'var(--sidebar-collapsed-w)';
        } else {
            sb.classList.remove('collapsed');
            if (icon) icon.className = 'fa-solid fa-angles-left';
            if (main) main.style.marginLeft = 'var(--sidebar-w)';
        }
    }

    window.toggleSidebar = function() {
        var sb = document.getElementById('sidebar');
        var collapsed = !sb.classList.contains('collapsed');
        try { localStorage.setItem(PREF_KEY, collapsed ? '1' : '0'); } catch(e){}
        applyCollapsed(collapsed, true);
    };

    document.addEventListener('DOMContentLoaded', function() {
        initTooltips();
        var pref = '0';
        try { pref = localStorage.getItem(PREF_KEY) || '0'; } catch(e){}
        // Apply without transition on first load to avoid flash
        var sb = document.getElementById('sidebar');
        if (sb) sb.style.transition = 'none';
        applyCollapsed(pref === '1', false);
        requestAnimationFrame(function() {
            if (sb) sb.style.transition = '';
        });
    });
})();
function openNotifPanel(e) {
    if (e) e.preventDefault();
    document.getElementById('notifBackdrop').classList.add('open');
    if (notifLoaded) return;
    const bookId = <?= $currentBookId ? (int)$currentBookId : 'null' ?>;
    const url = bookId ? '/books/' + bookId + '/notifications' : '/notifications';
    fetch(url)
        .then(r => r.ok ? r.json() : [])
        .then(data => {
            notifLoaded = true;
            const body = document.getElementById('notifPanelBody');
            if (!data.length) {
                body.innerHTML = '<div class="notif-empty"><i class="fa-regular fa-bell-slash"></i><br>No notifications yet.</div>';
                return;
            }

            function renderNotif(n) {
                let actionBtn = '';
                if (n.type === 'invitation' && n.action_url) {
                    actionBtn = `<div style="margin-top:6px"><a href="${escHtml(n.action_url)}" class="btn btn-sm btn-primary" style="font-size:11px;padding:3px 10px">View Invitation</a></div>`;
                } else if (n.action_url) {
                    actionBtn = `<div style="margin-top:6px"><a href="${escHtml(n.action_url)}" class="btn btn-sm btn-secondary" style="font-size:11px;padding:3px 10px">Open</a></div>`;
                }
                const readStyle = n.read ? 'opacity:0.6' : '';
                return `<div class="notif-item notif-${escHtml(n.type)}" style="${readStyle}">
                    <div class="notif-item-title">${escHtml(n.title)}</div>
                    ${n.body ? `<div class="notif-item-body">${escHtml(n.body)}</div>` : ''}
                    <div class="notif-item-meta">${escHtml(n.created_at||'')}</div>
                    ${actionBtn}
                </div>`;
            }

            if (bookId) {
                // Split into book-specific and global/invitation sections
                const bookNotifs   = data.filter(n => n.is_book_notif);
                const globalNotifs = data.filter(n => !n.is_book_notif);
                let html = '';

                if (bookNotifs.length) {
                    html += `<div class="notif-section-label"><i class="fa-solid fa-building"></i> This Book</div>`;
                    html += bookNotifs.map(renderNotif).join('');
                }
                if (globalNotifs.length) {
                    if (bookNotifs.length) {
                        html += `<div class="notif-section-label" style="margin-top:12px"><i class="fa-solid fa-globe"></i> Other</div>`;
                    }
                    html += globalNotifs.map(renderNotif).join('');
                }
                body.innerHTML = html || '<div class="notif-empty"><i class="fa-regular fa-bell-slash"></i><br>No notifications yet.</div>';
            } else {
                body.innerHTML = data.map(renderNotif).join('');
            }
        })
        .catch(() => {
            document.getElementById('notifPanelBody').innerHTML = '<div class="notif-empty">Could not load.</div>';
        });
}
function closeNotifPanel(e) {
    if (e && e.target !== document.getElementById('notifBackdrop')) return;
    document.getElementById('notifBackdrop').classList.remove('open');
    notifLoaded = false; // reset so next open re-fetches (and badge gets cleared)
}
function escHtml(s) {
    const d = document.createElement('div');
    d.appendChild(document.createTextNode(String(s)));
    return d.innerHTML;
}

// ── Nav dropdown ─────────────────────────────────────────────────────────────
function toggleNavDropdown(id, e) {
    e.preventDefault();
    var el = document.getElementById(id);
    if (!el) return;
    el.classList.toggle('open');
}
</script>

<style>
/* Nav dropdown */
.nav-dropdown { }
.nav-dropdown-trigger {
    display: flex;
    align-items: center;
    justify-content: space-between;
    cursor: pointer;
    user-select: none;
}
.nav-dropdown-trigger span { display:flex; align-items:center; gap:8px; }
.nav-chevron { font-size:11px; color:var(--text-muted); transition:transform .2s; flex-shrink:0; }
.nav-dropdown.open .nav-chevron { transform:rotate(180deg); }
.nav-dropdown-menu { display:none; padding:2px 0 4px 0; }
.nav-dropdown.open .nav-dropdown-menu { display:block; }
.nav-sub-item {
    display: flex;
    align-items: center;
    gap: 9px;
    padding: 7px 12px 7px 32px;
    font-size: 13px;
    font-weight: 500;
    color: var(--text-muted);
    text-decoration: none;
    border-radius: 7px;
    margin: 1px 6px;
    transition: background .12s, color .12s;
}
.nav-sub-item i { width:14px; text-align:center; font-size:12px; }
.nav-sub-item:hover { background:var(--hover-bg, rgba(0,0,0,.05)); color:var(--text); }
.nav-sub-item.active { background:var(--brand-light, rgba(26,107,74,.1)); color:var(--brand); font-weight:600; }
</style>
</body>
</html>