<?php
$pageTitle = 'Invoices — ' . e($book['name']);
$summary = $summary ?? ['total_sales'=>0,'collected'=>0,'due'=>0,'total_purchases'=>0];
$returnsSummary = $returnsSummary ?? ['sales_refunds'=>0,'purchase_refunds'=>0,'total_count'=>0];
ob_start();

// Build a merged unified list: invoices + returns, sorted by date desc
$mergedRows = [];

foreach ($invoices as $inv) {
    $mergedRows[] = array_merge($inv, ['_row_kind' => 'invoice']);
}
foreach (($returnsForMonth ?? []) as $ret) {
    $mergedRows[] = array_merge($ret, ['_row_kind' => 'return']);
}

// Sort merged list by date desc, then id desc
usort($mergedRows, function($a, $b) {
    $da = $a['date'] ?? $a['created_at'] ?? '';
    $db = $b['date'] ?? $b['created_at'] ?? '';
    if ($da !== $db) return strcmp($db, $da);
    return (int)($b['id'] ?? 0) - (int)($a['id'] ?? 0);
});
?>
<div class="page-header">
    <div class="page-header-left">
        <div class="breadcrumb">
            <a href="/books/<?= $book['id'] ?>">Books</a> <span>›</span>
            <span>Invoices</span>
        </div>
        <h1><i class="fa-solid fa-file-invoice" style="color:var(--brand)"></i> Invoices</h1>
        <p>Sales, purchases and returns — all in one place</p>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
        <button class="btn btn-secondary btn-sm" data-modal="printModal" title="Print PDF">
            <i class="fa-solid fa-print"></i> Print
        </button>
        <a href="/books/<?= $book['id'] ?>/returns/create" class="btn btn-secondary btn-sm">
            <i class="fa-solid fa-rotate-left"></i> Return
        </a>
        <a href="/books/<?= $book['id'] ?>/invoices/create?type=sale" class="btn btn-primary">
            <i class="fa-solid fa-plus"></i> Sale Invoice
        </a>
        <a href="/books/<?= $book['id'] ?>/invoices/create?type=purchase" class="btn btn-secondary">
            <i class="fa-solid fa-cart-shopping"></i> Purchase
        </a>
    </div>
</div>

<!-- Summary -->
<div class="stat-grid" style="grid-template-columns:repeat(auto-fit,minmax(148px,1fr));max-width:1020px;margin-bottom:20px">
    <div class="stat-card">
        <div class="stat-label"><i class="fa-solid fa-arrow-up-right-dots"></i> Total Sales</div>
        <div class="stat-value green"><?= format_money($summary['total_sales'] ?? 0) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label"><i class="fa-solid fa-check-double"></i> Collected</div>
        <div class="stat-value brand"><?= format_money($summary['collected'] ?? 0) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label"><i class="fa-solid fa-clock"></i> Due</div>
        <div class="stat-value red"><?= format_money($summary['due'] ?? 0) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label"><i class="fa-solid fa-cart-flatbed"></i> Total Purchases</div>
        <div class="stat-value red"><?= format_money($summary['total_purchases'] ?? 0) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label"><i class="fa-solid fa-rotate-left" style="color:var(--amber)"></i> Sales Refunds</div>
        <div class="stat-value amber"><?= format_money($returnsSummary['sales_refunds'] ?? 0) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label"><i class="fa-solid fa-rotate-right" style="color:var(--blue)"></i> Purchase Refunds</div>
        <div class="stat-value" style="color:var(--blue)"><?= format_money($returnsSummary['purchase_refunds'] ?? 0) ?></div>
    </div>
</div>

<!-- Controls -->
<div class="lm-controls">
    <div class="lm-search-wrap">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="text" class="lm-search" id="invSearch" placeholder="Search invoice no., party name, return no.…">
        <button class="lm-search-clear" id="invClear"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <select class="lm-select" id="invSort" title="Sort">
        <option value="date-desc">Newest First</option>
        <option value="date-asc">Oldest First</option>
        <option value="amount-desc">Highest Amount</option>
        <option value="amount-asc">Lowest Amount</option>
        <option value="party-asc">Party A–Z</option>
        <option value="inv-asc">Invoice No.</option>
    </select>
</div>

<!-- Filter pills -->
<div class="lm-filter-pills">
    <span style="font-size:12px;font-weight:600;color:var(--text-muted)">Type:</span>
    <button class="btn btn-sm btn-primary"   data-tf="all">All</button>
    <button class="btn btn-sm btn-secondary" data-tf="sale">Sales</button>
    <button class="btn btn-sm btn-secondary" data-tf="purchase">Purchases</button>
    <button class="btn btn-sm btn-secondary" data-tf="sales_return">S. Returns</button>
    <button class="btn btn-sm btn-secondary" data-tf="purchase_return">P. Returns</button>
    <span style="font-size:12px;font-weight:600;color:var(--text-muted);margin-left:8px">Status:</span>
    <button class="btn btn-sm btn-primary"   data-sf="all">All</button>
    <button class="btn btn-sm btn-secondary" data-sf="draft">Draft</button>
    <button class="btn btn-sm btn-secondary" data-sf="sent">Sent</button>
    <button class="btn btn-sm btn-secondary" data-sf="partial">Partial</button>
    <button class="btn btn-sm btn-secondary" data-sf="paid">Paid</button>
    <button class="btn btn-sm btn-secondary" data-sf="overdue">Overdue</button>
</div>

<!-- Month navigation -->
<div style="display:flex;align-items:center;gap:8px;margin-bottom:14px;flex-wrap:wrap;margin-top:8px">
    <a href="?month=<?= $prevMonth ?>&type=<?= $type ?>&status=<?= $status ?>" class="btn btn-secondary btn-sm">
        <i class="fa-solid fa-chevron-left"></i>
    </a>
    <div style="text-align:center;min-width:160px">
        <div style="font-weight:600;font-size:14px"><?= date('F Y', strtotime($month.'-01')) ?></div>
        <div style="font-size:11px;color:var(--text-muted)"><?= count($invoices) ?> invoice<?= count($invoices)!==1?'s':'' ?><?php if (!empty($returnsForMonth)): ?>, <?= count($returnsForMonth) ?> return<?= count($returnsForMonth)!==1?'s':'' ?><?php endif; ?></div>
    </div>
    <a href="?month=<?= $nextMonth ?>&type=<?= $type ?>&status=<?= $status ?>"
       class="btn btn-secondary btn-sm <?= $isCurrent ? 'disabled' : '' ?>"
       style="<?= $isCurrent ? 'opacity:.35;pointer-events:none' : '' ?>">
        <i class="fa-solid fa-chevron-right"></i>
    </a>
</div>

<?php if (empty($mergedRows)): ?>
<div class="table-wrap">
    <div class="empty-state">
        <div class="empty-icon"><i class="fa-solid fa-file-invoice"></i></div>
        <h3>No records this month</h3>
        <p>Create your first sale or purchase invoice to get started.</p>
    </div>
</div>
<?php else: ?>
<div class="table-wrap">
    <table id="invTable">
        <thead>
            <tr>
                <th data-sort="0">#</th>
                <th>Type</th>
                <th data-sort="2">Party</th>
                <th data-sort="3">Date</th>
                <th data-sort="4">Due / Ref</th>
                <th>Status</th>
                <th data-sort="6" style="text-align:right">Amount</th>
                <th data-sort="7" style="text-align:right">Paid / Refund</th>
                <th data-sort="8" style="text-align:right">Balance</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($mergedRows as $row):
            $kind = $row['_row_kind'];
            if ($kind === 'return'):
                $party      = $row['customer_name'] ?? $row['supplier_name'] ?? '—';
                $typeKey    = $row['type'] ?? '';
                $typeBadge  = $typeKey === 'sales_return' ? 'badge-amber' : 'badge-indigo';
                $typeLabel  = $typeKey === 'sales_return' ? '↩ Sales Ret.' : '↩ Pur. Ret.';
                $refund     = (float)($row['total_refund'] ?? 0);
                $origInvNo  = $row['orig_invoice_no'] ?? '—';
                $rowDate    = $row['date'] ?? '';
        ?>
        <tr data-type="<?= e($typeKey) ?>" data-status="return" data-date="<?= e($rowDate) ?>" class="return-row">
            <td>
                <a href="/books/<?= $book['id'] ?>/returns/<?= $row['id'] ?>"
                   style="font-weight:600;color:var(--amber,#d97706);text-decoration:none">
                    <?= e($row['return_no'] ?? '#'.$row['id']) ?>
                </a>
            </td>
            <td><span class="badge <?= $typeBadge ?>"><?= $typeLabel ?></span></td>
            <td class="td-muted"><?= e($party) ?></td>
            <td class="td-muted" data-date="<?= e($rowDate) ?>"><?= $rowDate ? format_date($rowDate) : '—' ?></td>
            <td class="td-muted" style="font-size:11.5px">Ref: <?= e($origInvNo) ?></td>
            <td><span class="badge badge-gray">Return</span></td>
            <td style="text-align:right" class="td-amount"><?= format_money($refund) ?></td>
            <td style="text-align:right;font-weight:600;color:var(--red)"><?= format_money($refund) ?></td>
            <td style="text-align:right" class="td-muted">—</td>
            <td style="white-space:nowrap">
                <a href="/books/<?= $book['id'] ?>/returns/<?= $row['id'] ?>" class="btn btn-sm btn-secondary" title="View"><i class="fa-solid fa-eye"></i></a>
            </td>
        </tr>
        <?php else:
            $sc    = ['draft'=>'gray','sent'=>'blue','partial'=>'amber','paid'=>'green','overdue'=>'red','cancelled'=>'gray'][$row['status']] ?? 'gray';
            $due   = (float)($row['total'] ?? 0) - (float)($row['paid'] ?? 0);
            $party = $row['customer_name'] ?? $row['supplier_name'] ?? '—';
        ?>
        <tr data-type="<?= e($row['type']) ?>" data-status="<?= e($row['status']) ?>" data-date="<?= e($row['date'] ?? '') ?>">
            <td><a href="/books/<?= $book['id'] ?>/invoices/<?= $row['id'] ?>" style="font-weight:600;color:var(--brand);text-decoration:none"><?= e($row['invoice_no']) ?></a></td>
            <td><span class="badge <?= $row['type']==='sale'?'badge-green':'badge-blue' ?>"><?= $row['type']==='sale'?'Sale':'Purchase' ?></span></td>
            <td class="td-muted"><?= e($party) ?></td>
            <td class="td-muted" data-date="<?= e($row['date'] ?? '') ?>"><?= ($row['date'] ?? '') ? format_date($row['date']) : '—' ?></td>
            <td class="td-muted"><?= ($row['due_date'] ?? '') ? format_date($row['due_date']) : '—' ?></td>
            <td><span class="badge badge-<?= $sc ?>"><?= ucfirst($row['status']) ?></span></td>
            <td style="text-align:right" class="td-amount"><?= format_money($row['total'] ?? 0) ?></td>
            <td style="text-align:right" class="td-amount in"><?= format_money($row['paid'] ?? 0) ?></td>
            <td style="text-align:right" class="td-amount <?= $due>0?'out':'' ?>"><?= format_money($due) ?></td>
            <td style="white-space:nowrap">
                <a href="/books/<?= $book['id'] ?>/invoices/<?= $row['id'] ?>" class="btn btn-sm btn-secondary" title="View"><i class="fa-solid fa-eye"></i></a>
                <a href="/books/<?= $book['id'] ?>/invoices/<?= $row['id'] ?>/pdf" class="btn btn-sm btn-secondary" title="PDF" target="_blank"><i class="fa-solid fa-print"></i></a>
            </td>
        </tr>
        <?php endif; endforeach; ?>
        </tbody>
    </table>
</div>
<div id="invPager"></div>
<?php endif; ?>

<style>
.return-row { background: color-mix(in srgb, var(--amber, #d97706) 4%, transparent); }
.return-row:hover { background: color-mix(in srgb, var(--amber, #d97706) 9%, transparent) !important; }
.badge-indigo { background: rgba(99,102,241,.12); color: #6366f1; }
</style>

<script>
(function(){
    var TABLE_ID = 'invTable';
    var allRows  = [];
    var typeF = 'all', statusF = 'all', searchQ = '', sortKey = 'date-desc';
    var perPage = 20, curPage = 1;

    function init() {
        allRows = Array.from(document.querySelectorAll('#' + TABLE_ID + ' tbody tr'));

        var si = document.getElementById('invSearch');
        var sc = document.getElementById('invClear');
        if (si) {
            si.addEventListener('input', function() {
                searchQ = this.value.toLowerCase().trim();
                sc.classList.toggle('visible', searchQ.length > 0);
                curPage = 1; render();
            });
            sc.addEventListener('click', function() { si.value = ''; searchQ = ''; sc.classList.remove('visible'); curPage=1; render(); });
        }

        var sortSel = document.getElementById('invSort');
        if (sortSel) sortSel.addEventListener('change', function() { sortKey = this.value; curPage=1; render(); });

        document.querySelectorAll('[data-tf]').forEach(function(b) {
            b.addEventListener('click', function() {
                typeF = this.getAttribute('data-tf');
                document.querySelectorAll('[data-tf]').forEach(function(x){ x.classList.remove('btn-primary'); x.classList.add('btn-secondary'); });
                this.classList.add('btn-primary'); this.classList.remove('btn-secondary');
                curPage=1; render();
            });
        });

        document.querySelectorAll('[data-sf]').forEach(function(b) {
            b.addEventListener('click', function() {
                statusF = this.getAttribute('data-sf');
                document.querySelectorAll('[data-sf]').forEach(function(x){ x.classList.remove('btn-primary'); x.classList.add('btn-secondary'); });
                this.classList.add('btn-primary'); this.classList.remove('btn-secondary');
                curPage=1; render();
            });
        });

        render();
    }

    function parseAmt(row, col) { var c=row.querySelectorAll('td')[col]; return c?parseFloat(c.textContent.replace(/[^0-9.]/g,''))||0:0; }
    function parseRowDate(row) { return new Date(row.getAttribute('data-date')||0); }

    function render() {
        var filtered = allRows.filter(function(row) {
            var rt = row.getAttribute('data-type') || '';
            if (typeF !== 'all') {
                // Map filter pills to row data-type
                if (typeF === 'sale' && rt !== 'sale') return false;
                if (typeF === 'purchase' && rt !== 'purchase') return false;
                if (typeF === 'sales_return' && rt !== 'sales_return') return false;
                if (typeF === 'purchase_return' && rt !== 'purchase_return') return false;
            }
            if (statusF !== 'all') {
                var rs = row.getAttribute('data-status') || '';
                // Returns have status "return" – when filtering by invoice status, hide returns unless filter is "all"
                if (statusF !== 'all' && rs === 'return') return false;
                if (statusF !== 'all' && rs !== statusF) return false;
            }
            if (searchQ && row.textContent.toLowerCase().indexOf(searchQ) === -1) return false;
            return true;
        });

        filtered.sort(function(a, b) {
            if (sortKey==='date-desc') return parseRowDate(b)-parseRowDate(a);
            if (sortKey==='date-asc')  return parseRowDate(a)-parseRowDate(b);
            if (sortKey==='amount-desc') return parseAmt(b,6)-parseAmt(a,6);
            if (sortKey==='amount-asc')  return parseAmt(a,6)-parseAmt(b,6);
            var pa=(a.querySelectorAll('td')[2]||{}).textContent||'';
            var pb=(b.querySelectorAll('td')[2]||{}).textContent||'';
            if (sortKey==='party-asc') return pa.localeCompare(pb);
            var ia=(a.querySelectorAll('td')[0]||{}).textContent||'';
            var ib=(b.querySelectorAll('td')[0]||{}).textContent||'';
            if (sortKey==='inv-asc') return ia.localeCompare(ib,undefined,{numeric:true});
            return 0;
        });

        var total=filtered.length, pp=perPage==='all'?Infinity:parseInt(perPage);
        var tpg=pp===Infinity?1:Math.max(1,Math.ceil(total/pp));
        if (curPage>tpg) curPage=tpg; if (curPage<1) curPage=1;
        var s=pp===Infinity?0:(curPage-1)*pp, e=pp===Infinity?total:Math.min(s+pp,total);
        var pageRows=filtered.slice(s,e);

        var tbody=document.querySelector('#'+TABLE_ID+' tbody');
        var colC=document.querySelector('#'+TABLE_ID+' thead tr').children.length;
        while(tbody.firstChild) tbody.removeChild(tbody.firstChild);

        if (pageRows.length===0) {
            var noR=document.createElement('tr'); noR.className='lm-no-results';
            var noD=document.createElement('td'); noD.setAttribute('colspan',colC);
            noD.textContent=searchQ?'No records match "'+searchQ+'".':'No records match the selected filters.';
            noR.appendChild(noD); tbody.appendChild(noR);
        } else {
            var lastM=null;
            pageRows.forEach(function(row) {
                var d=parseRowDate(row), mk=isNaN(d)?null:d.getFullYear()+'-'+d.getMonth();
                var ml=isNaN(d)?null:d.toLocaleDateString('en-GB',{month:'long',year:'numeric'});
                if (mk && mk!==lastM) {
                    lastM=mk;
                    var sep=document.createElement('tr'); sep.className='month-sep';
                    var std=document.createElement('td'); std.setAttribute('colspan',colC); std.textContent=ml;
                    sep.appendChild(std); tbody.appendChild(sep);
                }
                tbody.appendChild(row);
            });
        }
        renderPager(total,tpg,s,e,pp);
    }

    function renderPager(total,tpg,s,e,pp) {
        var el=document.getElementById('invPager'); if(!el) return;
        el.innerHTML='';
        var wrap=document.createElement('div'); wrap.className='lm-pagination';
        var info=document.createElement('div'); info.className='lm-page-info';
        info.textContent=total===0?'No results':pp===Infinity?'All '+total+' records':'Showing '+(s+1)+'–'+e+' of '+total;
        wrap.appendChild(info);
        if (tpg>1) {
            var pages=document.createElement('div'); pages.className='lm-pages';
            function mkBtn(lbl,pg){var btn=document.createElement('button');btn.className='lm-page-btn';if(pg===curPage)btn.classList.add('active');btn.textContent=lbl;if(pg)btn.addEventListener('click',function(){curPage=pg;render();});return btn;}
            if(curPage>1) pages.appendChild(mkBtn('‹',curPage-1));
            var ns=[]; if(tpg<=7){for(var i=1;i<=tpg;i++)ns.push(i);}
            else{ns.push(1);if(curPage>3)ns.push('…');for(var i=Math.max(2,curPage-1);i<=Math.min(tpg-1,curPage+1);i++)ns.push(i);if(curPage<tpg-2)ns.push('…');ns.push(tpg);}
            ns.forEach(function(p){if(p==='…'){var b=mkBtn('…',0);b.classList.add('lm-ellipsis');pages.appendChild(b);}else pages.appendChild(mkBtn(p,p));});
            if(curPage<tpg) pages.appendChild(mkBtn('›',curPage+1));
            wrap.appendChild(pages);
        }
        var ppW=document.createElement('div'); ppW.className='lm-per-page-wrap';
        var sl=document.createElement('select'); sl.className='lm-select'; sl.style.padding='4px 8px'; sl.style.margin='0 4px';
        [20,50,100,'all'].forEach(function(v){var o=document.createElement('option');o.value=v;o.textContent=v==='all'?'All':v;if((pp===Infinity&&v==='all')||pp===v)o.selected=true;sl.appendChild(o);});
        sl.addEventListener('change',function(){perPage=sl.value;curPage=1;render();});
        ppW.appendChild(document.createTextNode('Show ')); ppW.appendChild(sl); ppW.appendChild(document.createTextNode(' per page'));
        wrap.appendChild(ppW); el.appendChild(wrap);
    }

    document.addEventListener('DOMContentLoaded', function(){ init(); });
})();
</script>

<?php
$content = ob_get_clean();
ob_start();
$printCategory = 'invoices';
require BASE_PATH . '/views/partials/print-modal.php';
$content .= ob_get_clean();
require BASE_PATH . '/views/partials/layout.php'; ?>
