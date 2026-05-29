<?php
$pageTitle = 'Purchases — ' . e($book['name']);
$summary   = $summary ?? ['total_purchases'=>0,'paid'=>0,'due'=>0,'count'=>0];
ob_start();
?>
<div class="page-header">
    <div class="page-header-left">
        <div class="breadcrumb">
            <a href="/books/<?= $book['id'] ?>">Books</a> <span>›</span>
            <span>Purchases</span>
        </div>
        <h1><i class="fa-solid fa-cart-shopping" style="color:var(--brand)"></i> Purchases</h1>
        <p>Purchase bills, supplier invoices and stock records</p>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
        <button class="btn btn-secondary btn-sm" data-modal="printModal" title="Print PDF">
            <i class="fa-solid fa-print"></i> Print
        </button>
        <a href="/books/<?= $book['id'] ?>/invoices/create?type=purchase" class="btn btn-primary">
            <i class="fa-solid fa-plus"></i> New Purchase
        </a>
    </div>
</div>

<!-- Summary -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:14px;margin-bottom:20px">
    <div class="card" style="text-align:center">
        <div style="font-size:11px;font-weight:700;text-transform:uppercase;color:var(--text-muted);letter-spacing:.5px"><i class="fa-solid fa-cart-flatbed"></i> Total Purchases</div>
        <div style="font-size:22px;font-weight:800;color:var(--red);margin-top:4px"><?= format_money($summary['total_purchases'] ?? 0) ?></div>
    </div>
    <div class="card" style="text-align:center">
        <div style="font-size:11px;font-weight:700;text-transform:uppercase;color:var(--text-muted);letter-spacing:.5px"><i class="fa-solid fa-check-double"></i> Paid</div>
        <div style="font-size:22px;font-weight:800;color:var(--brand);margin-top:4px"><?= format_money($summary['paid'] ?? 0) ?></div>
    </div>
    <div class="card" style="text-align:center">
        <div style="font-size:11px;font-weight:700;text-transform:uppercase;color:var(--text-muted);letter-spacing:.5px"><i class="fa-solid fa-clock"></i> Due</div>
        <div style="font-size:22px;font-weight:800;color:var(--red);margin-top:4px"><?= format_money($summary['due'] ?? 0) ?></div>
    </div>
    <div class="card" style="text-align:center">
        <div style="font-size:11px;font-weight:700;text-transform:uppercase;color:var(--text-muted);letter-spacing:.5px"><i class="fa-solid fa-file-invoice"></i> Bills</div>
        <div style="font-size:22px;font-weight:800;margin-top:4px"><?= (int)($summary['count'] ?? 0) ?></div>
    </div>
</div>

<!-- Controls -->
<div class="lm-controls">
    <div class="lm-search-wrap">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="text" class="lm-search" id="invSearch" placeholder="Search invoice no., supplier name…">
        <button class="lm-search-clear" id="invClear"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <select class="lm-select" id="invSort" title="Sort">
        <option value="date-desc">Newest First</option>
        <option value="date-asc">Oldest First</option>
        <option value="amount-desc">Highest Amount</option>
        <option value="amount-asc">Lowest Amount</option>
        <option value="party-asc">Supplier A–Z</option>
        <option value="inv-asc">Invoice No.</option>
    </select>
</div>

<!-- Status filter pills -->
<div class="lm-filter-pills">
    <span style="font-size:12px;font-weight:600;color:var(--text-muted)">Status:</span>
    <button class="btn btn-sm btn-primary"   data-sf="all">All</button>
    <button class="btn btn-sm btn-secondary" data-sf="draft">Draft</button>
    <button class="btn btn-sm btn-secondary" data-sf="sent">Sent</button>
    <button class="btn btn-sm btn-secondary" data-sf="partial">Partial</button>
    <button class="btn btn-sm btn-secondary" data-sf="paid">Paid</button>
    <button class="btn btn-sm btn-secondary" data-sf="overdue">Overdue</button>
</div>

<!-- Month nav -->
<div style="display:flex;align-items:center;gap:8px;margin-bottom:14px;flex-wrap:wrap">
    <a href="?month=<?= $prevMonth ?>&status=<?= $status ?>" class="btn btn-secondary btn-sm">
        <i class="fa-solid fa-chevron-left"></i>
    </a>
    <div style="text-align:center;min-width:140px">
        <div style="font-weight:600;font-size:14px"><?= date('F Y', strtotime($month.'-01')) ?></div>
        <div style="font-size:11px;color:var(--text-muted)"><?= count($invoices) ?> purchases</div>
    </div>
    <a href="?month=<?= $nextMonth ?>&status=<?= $status ?>"
       class="btn btn-secondary btn-sm <?= $isCurrent ? 'disabled' : '' ?>"
       style="<?= $isCurrent ? 'opacity:.35;pointer-events:none' : '' ?>">
        <i class="fa-solid fa-chevron-right"></i>
    </a>
</div>

<?php if (empty($invoices)): ?>
<div class="table-wrap">
    <div class="empty-state">
        <div class="empty-icon"><i class="fa-solid fa-cart-shopping"></i></div>
        <h3>No purchases yet</h3>
        <p>Record your first purchase bill to track stock and supplier payments.</p>
        <a href="/books/<?= $book['id'] ?>/invoices/create?type=purchase" class="btn btn-primary">
            <i class="fa-solid fa-plus"></i> New Purchase
        </a>
    </div>
</div>
<?php else: ?>
<div class="table-wrap">
    <table id="invTable">
        <thead>
            <tr>
                <th data-sort="0">Invoice #</th>
                <th data-sort="1">Supplier</th>
                <th data-sort="2">Date</th>
                <th data-sort="3">Due Date</th>
                <th>Status</th>
                <th data-sort="5" style="text-align:right">Total</th>
                <th data-sort="6" style="text-align:right">Paid</th>
                <th data-sort="7" style="text-align:right">Due</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($invoices as $inv):
            $sc  = ['draft'=>'gray','sent'=>'blue','partial'=>'amber','paid'=>'green','overdue'=>'red','cancelled'=>'gray'][$inv['status']] ?? 'gray';
            $due = (float)($inv['total'] ?? 0) - (float)($inv['paid'] ?? 0);
        ?>
        <tr data-status="<?= e($inv['status']) ?>" data-date="<?= e($inv['date'] ?? '') ?>">
            <td><a href="/books/<?= $book['id'] ?>/invoices/<?= $inv['id'] ?>" style="font-weight:600;color:var(--brand);text-decoration:none"><?= e($inv['invoice_no']) ?></a></td>
            <td class="td-muted"><?= e($inv['supplier_name'] ?? '—') ?></td>
            <td class="td-muted" data-date="<?= e($inv['date'] ?? '') ?>"><?= $inv['date'] ? format_date($inv['date']) : '—' ?></td>
            <td class="td-muted"><?= ($inv['due_date'] ?? '') ? format_date($inv['due_date']) : '—' ?></td>
            <td><span class="badge badge-<?= $sc ?>"><?= ucfirst($inv['status']) ?></span></td>
            <td style="text-align:right" class="td-amount"><?= format_money($inv['total'] ?? 0) ?></td>
            <td style="text-align:right" class="td-amount in"><?= format_money($inv['paid'] ?? 0) ?></td>
            <td style="text-align:right" class="td-amount <?= $due>0?'out':'' ?>"><?= format_money($due) ?></td>
            <td style="white-space:nowrap">
                <a href="/books/<?= $book['id'] ?>/invoices/<?= $inv['id'] ?>" class="btn btn-sm btn-secondary" title="View"><i class="fa-solid fa-eye"></i></a>
                <a href="/books/<?= $book['id'] ?>/invoices/<?= $inv['id'] ?>/pdf" class="btn btn-sm btn-secondary" title="PDF" target="_blank"><i class="fa-solid fa-print"></i></a>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<div id="invPager"></div>
<?php endif; ?>

<script>
(function(){
    var allRows = [], statusF = 'all', searchQ = '', sortKey = 'date-desc';
    var perPage = 20, curPage = 1;

    function init() {
        allRows = Array.from(document.querySelectorAll('#invTable tbody tr'));
        var si = document.getElementById('invSearch'), sc = document.getElementById('invClear');
        if (si) {
            si.addEventListener('input', function() { searchQ=this.value.toLowerCase().trim(); sc.classList.toggle('visible',searchQ.length>0); curPage=1; render(); });
            sc.addEventListener('click', function() { si.value=''; searchQ=''; sc.classList.remove('visible'); curPage=1; render(); });
        }
        var ss = document.getElementById('invSort');
        if (ss) ss.addEventListener('change', function() { sortKey=this.value; curPage=1; render(); });
        document.querySelectorAll('[data-sf]').forEach(function(b) {
            b.addEventListener('click', function() {
                statusF=this.getAttribute('data-sf');
                document.querySelectorAll('[data-sf]').forEach(function(x){ x.classList.remove('btn-primary'); x.classList.add('btn-secondary'); });
                this.classList.add('btn-primary'); this.classList.remove('btn-secondary');
                curPage=1; render();
            });
        });
        render();
    }

    function parseAmt(row,col) { var c=row.querySelectorAll('td')[col]; return c?parseFloat(c.textContent.replace(/[^0-9.]/g,''))||0:0; }
    function parseDate(row) { return new Date(row.getAttribute('data-date')||0); }
    function monthKey(row) { var d=parseDate(row); return isNaN(d)?null:d.getFullYear()+'-'+d.getMonth(); }
    function monthLabel(row) { var d=parseDate(row); return isNaN(d)?null:d.toLocaleDateString('en-GB',{month:'long',year:'numeric'}); }

    function render() {
        var filtered = allRows.filter(function(row) {
            if (statusF!=='all' && row.getAttribute('data-status')!==statusF) return false;
            if (searchQ && row.textContent.toLowerCase().indexOf(searchQ)===-1) return false;
            return true;
        });
        filtered.sort(function(a,b) {
            if (sortKey==='date-desc') return parseDate(b)-parseDate(a);
            if (sortKey==='date-asc')  return parseDate(a)-parseDate(b);
            if (sortKey==='amount-desc') return parseAmt(b,5)-parseAmt(a,5);
            if (sortKey==='amount-asc')  return parseAmt(a,5)-parseAmt(b,5);
            var pa=(a.querySelectorAll('td')[1]||{}).textContent||'', pb=(b.querySelectorAll('td')[1]||{}).textContent||'';
            if (sortKey==='party-asc') return pa.localeCompare(pb);
            var ia=(a.querySelectorAll('td')[0]||{}).textContent||'', ib=(b.querySelectorAll('td')[0]||{}).textContent||'';
            if (sortKey==='inv-asc') return ia.localeCompare(ib,undefined,{numeric:true});
            return 0;
        });
        var total=filtered.length, pp=perPage==='all'?Infinity:parseInt(perPage);
        var tpg=pp===Infinity?1:Math.max(1,Math.ceil(total/pp));
        if (curPage>tpg) curPage=tpg; if (curPage<1) curPage=1;
        var s=pp===Infinity?0:(curPage-1)*pp, e=pp===Infinity?total:Math.min(s+pp,total);
        var pageRows=filtered.slice(s,e);
        var tbody=document.querySelector('#invTable tbody'), colC=document.querySelector('#invTable thead tr').children.length;
        while(tbody.firstChild) tbody.removeChild(tbody.firstChild);
        if (pageRows.length===0) {
            var noR=document.createElement('tr'); noR.className='lm-no-results';
            var noD=document.createElement('td'); noD.setAttribute('colspan',colC);
            noD.textContent=searchQ?'No purchases match "'+searchQ+'"':'No purchases match the selected filters.';
            noR.appendChild(noD); tbody.appendChild(noR);
        } else {
            var lastM=null;
            pageRows.forEach(function(row) {
                var mk=monthKey(row), ml=monthLabel(row);
                if (mk&&mk!==lastM){lastM=mk;var sep=document.createElement('tr');sep.className='month-sep';var std=document.createElement('td');std.setAttribute('colspan',colC);std.textContent=ml;sep.appendChild(std);tbody.appendChild(sep);}
                tbody.appendChild(row);
            });
        }
        renderPager(total,tpg,s,e,pp);
    }

    function renderPager(total,tpg,s,e,pp) {
        var el=document.getElementById('invPager'); if(!el) return; el.innerHTML='';
        var wrap=document.createElement('div'); wrap.className='lm-pagination';
        var info=document.createElement('div'); info.className='lm-page-info';
        info.textContent=total===0?'No results':pp===Infinity?'Showing all '+total+' records':'Showing '+(s+1)+'–'+e+' of '+total;
        wrap.appendChild(info);
        if (tpg>1) {
            var pages=document.createElement('div'); pages.className='lm-pages';
            function mkBtn(lbl,pg){var btn=document.createElement('button');btn.className='lm-page-btn';if(pg===curPage)btn.classList.add('active');btn.textContent=lbl;if(pg)btn.addEventListener('click',function(){curPage=pg;render();});return btn;}
            if(curPage>1)pages.appendChild(mkBtn('‹',curPage-1));
            var ns=[];if(tpg<=7){for(var i=1;i<=tpg;i++)ns.push(i);}else{ns.push(1);if(curPage>3)ns.push('…');for(var i=Math.max(2,curPage-1);i<=Math.min(tpg-1,curPage+1);i++)ns.push(i);if(curPage<tpg-2)ns.push('…');ns.push(tpg);}
            ns.forEach(function(p){if(p==='…'){var b=mkBtn('…',0);b.classList.add('lm-ellipsis');pages.appendChild(b);}else pages.appendChild(mkBtn(p,p));});
            if(curPage<tpg)pages.appendChild(mkBtn('›',curPage+1));
            wrap.appendChild(pages);
        }
        var ppW=document.createElement('div');ppW.className='lm-per-page-wrap';
        var sl=document.createElement('select');sl.className='lm-select';sl.style.padding='4px 8px';sl.style.margin='0 4px';
        [20,50,100,'all'].forEach(function(v){var o=document.createElement('option');o.value=v;o.textContent=v==='all'?'All':v;if((pp===Infinity&&v==='all')||pp===v)o.selected=true;sl.appendChild(o);});
        sl.addEventListener('change',function(){perPage=sl.value;curPage=1;render();});
        ppW.appendChild(document.createTextNode('Show '));ppW.appendChild(sl);ppW.appendChild(document.createTextNode(' per page'));
        wrap.appendChild(ppW);el.appendChild(wrap);
    }

    document.addEventListener('DOMContentLoaded', function() { init(); });
})();
</script>

<?php
$content = ob_get_clean();
ob_start();
$printCategory = 'invoices';
require BASE_PATH . '/views/partials/print-modal.php';
$content .= ob_get_clean();
require BASE_PATH . '/views/partials/layout.php';
