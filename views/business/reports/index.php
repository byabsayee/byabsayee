<?php
$pageTitle = 'Reports — ' . e($book['name']);
ob_start();

$prevMonth = date('Y-m', strtotime($month . '-01 -1 month'));
$nextMonth = date('Y-m', strtotime($month . '-01 +1 month'));
$isCurrent = $month === date('Y-m');

$catMeta = [
    'Sale Invoice'               => ['var(--green)',  'fa-file-invoice-dollar'],
    'Purchase Invoice'           => ['var(--blue)',   'fa-cart-shopping'],
    'Sales Return (Refund)'      => ['var(--red)',    'fa-rotate-left'],
    'Purchase Return (Recovery)' => ['var(--green)',  'fa-truck-ramp-box'],
    'Expense:'                   => ['var(--red)',    'fa-receipt'],
    'Fund Received'              => ['var(--green)',  'fa-piggy-bank'],
    'Fund Withdrawn'             => ['var(--red)',    'fa-circle-arrow-up'],
    'Due Payment'                => ['var(--green)',  'fa-hand-holding-dollar'],
    'Debt Repayment'             => ['var(--red)',    'fa-hand-holding-dollar'],
    'Salary Payment'             => ['var(--amber)',  'fa-user-tie'],
];
function catMeta(string $cat): array {
    global $catMeta;
    foreach ($catMeta as $k => $v) {
        if (str_starts_with($cat, $k)) return $v;
    }
    return ['var(--text-muted)', 'fa-circle'];
}
?>

<div class="page-header">
    <div class="page-header-left">
        <div class="breadcrumb">
            <a href="/books/<?= $book['id'] ?>">Books</a> <span>›</span>
            <span>Reports</span>
        </div>
        <h1><i class="fa-solid fa-chart-line" style="color:var(--brand)"></i> Reports & Ledger</h1>
        <p>Every money-in and money-out transaction for this business.</p>
    </div>
    <div style="display:flex;gap:8px">
        <button class="btn btn-secondary btn-sm" data-modal="printModal">
            <i class="fa-solid fa-print"></i> Print PDF
        </button>
    </div>
</div>

<!-- Summary cards -->
<div class="stat-grid" style="grid-template-columns:repeat(3,1fr);max-width:600px;margin-bottom:20px">
    <div class="stat-card" style="border-top:3px solid var(--green)">
        <div class="stat-label"><i class="fa-solid fa-arrow-down" style="color:var(--green)"></i> Total Income</div>
        <div class="stat-value green"><?= $sym . number_format($totalIn, 0) ?></div>
    </div>
    <div class="stat-card" style="border-top:3px solid var(--red)">
        <div class="stat-label"><i class="fa-solid fa-arrow-up" style="color:var(--red)"></i> Total Outgoing</div>
        <div class="stat-value red"><?= $sym . number_format($totalOut, 0) ?></div>
    </div>
    <div class="stat-card" style="border-top:3px solid var(--brand)">
        <div class="stat-label"><i class="fa-solid fa-scale-balanced" style="color:var(--brand)"></i> Net</div>
        <?php $net = $totalIn - $totalOut; ?>
        <div class="stat-value <?= $net >= 0 ? 'green' : 'red' ?>">
            <?= ($net >= 0 ? '+' : '') . $sym . number_format(abs($net), 0) ?>
        </div>
    </div>
</div>

<!-- Month navigator -->
<div style="display:flex;align-items:center;gap:8px;margin-bottom:14px;flex-wrap:wrap">
    <a href="?month=<?= $prevMonth ?>&type=<?= $typeFilter ?>" class="btn btn-secondary btn-sm">
        <i class="fa-solid fa-chevron-left"></i>
    </a>
    <div style="text-align:center;min-width:140px">
        <div style="font-weight:600;font-size:14px"><?= date('F Y', strtotime($month.'-01')) ?></div>
        <div style="font-size:11px;color:var(--text-muted)"><?= count($entries) ?> transactions</div>
    </div>
    <a href="?month=<?= $nextMonth ?>&type=<?= $typeFilter ?>"
       class="btn btn-secondary btn-sm <?= $isCurrent ? 'disabled' : '' ?>"
       style="<?= $isCurrent ? 'opacity:.35;pointer-events:none' : '' ?>">
        <i class="fa-solid fa-chevron-right"></i>
    </a>
    <div style="margin-left:auto;display:flex;gap:6px;flex-wrap:wrap">
        <?php foreach (['all'=>'All','in'=>'Income','out'=>'Expense'] as $k=>$lbl): ?>
        <a href="?month=<?= $month ?>&type=<?= $k ?>"
           class="btn btn-sm <?= $typeFilter===$k ? 'btn-primary' : 'btn-secondary' ?>"><?= $lbl ?></a>
        <?php endforeach; ?>
    </div>
</div>

<!-- Search + sort controls (client-side within the month) -->
<div class="lm-controls">
    <div class="lm-search-wrap">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="text" class="lm-search" id="repSearch" placeholder="Search ref, party, category…">
        <button class="lm-search-clear" id="repClear"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <select class="lm-select" id="repSort">
        <option value="date-desc">Newest First</option>
        <option value="date-asc">Oldest First</option>
        <option value="amt-desc">Largest Amount</option>
        <option value="amt-asc">Smallest Amount</option>
    </select>
</div>

<?php if (empty($entries)): ?>
<div class="table-wrap">
    <div class="empty-state">
        <div class="empty-icon">📊</div>
        <h3>No transactions this month</h3>
        <p>Nothing recorded for <?= date('F Y', strtotime($month.'-01')) ?>.</p>
    </div>
</div>
<?php else: ?>
<div class="table-wrap" style="padding:0;overflow:hidden">
    <table id="repTable" style="width:100%;border-collapse:collapse">
        <thead>
            <tr>
                <th data-sort="0">Date</th>
                <th>Category</th>
                <th data-sort="2">Ref / Description</th>
                <th data-sort="3">Party</th>
                <th data-sort="4" style="text-align:right;color:var(--green)">In</th>
                <th data-sort="5" style="text-align:right;color:var(--red)">Out</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($entries as $e):
            [$clr, $icon] = catMeta($e['category']);
            $isIn = $e['direction'] === 'in';
            $amt  = (float)$e['amount'];
        ?>
        <tr data-date="<?= e($e['date']) ?>" data-filter="<?= e($e['direction']) ?>"
            onclick="window.location='<?= e($e['href'] ?? '#') ?>'"
            style="cursor:pointer"
            onmouseover="this.style.background='var(--bg)'" onmouseout="this.style.background=''">
            <td class="td-muted" style="white-space:nowrap" data-date="<?= e($e['date']) ?>">
                <?= date('d M', strtotime($e['date'])) ?>
            </td>
            <td>
                <span style="display:inline-flex;align-items:center;gap:6px;font-size:12px;font-weight:600;color:<?= $clr ?>">
                    <i class="fa-solid <?= $icon ?>"></i>
                    <?= e($e['category']) ?>
                </span>
            </td>
            <td style="font-size:13px">
                <div style="font-weight:500"><?= e($e['invoice_no'] ?? '—') ?></div>
            </td>
            <td class="td-muted"><?= e($e['party'] ?? '—') ?></td>
            <td style="text-align:right;font-weight:700;color:var(--green)">
                <?= $isIn ? $sym . number_format($amt, 2) : '' ?>
            </td>
            <td style="text-align:right;font-weight:700;color:var(--red)">
                <?= !$isIn ? $sym . number_format($amt, 2) : '' ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr style="background:var(--bg);border-top:2px solid var(--border)">
                <td colspan="4" style="padding:10px 14px;font-weight:700;font-size:12px;text-transform:uppercase;letter-spacing:.5px;color:var(--text-muted)">
                    Month Total
                </td>
                <td style="padding:10px 14px;text-align:right;font-weight:800;font-size:15px;color:var(--green)">
                    <?= $sym . number_format($totalIn, 2) ?>
                </td>
                <td style="padding:10px 14px;text-align:right;font-weight:800;font-size:15px;color:var(--red)">
                    <?= $sym . number_format($totalOut, 2) ?>
                </td>
            </tr>
        </tfoot>
    </table>
</div>
<div id="repPager"></div>
<?php endif; ?>

<script>
(function(){
    var allRows=[], searchQ='', sortKey='date-desc', perPage=20, curPage=1;

    function init() {
        allRows = Array.from(document.querySelectorAll('#repTable tbody tr'));
        var si=document.getElementById('repSearch'), sc=document.getElementById('repClear');
        if(si){
            si.addEventListener('input',function(){searchQ=this.value.toLowerCase().trim();sc.classList.toggle('visible',searchQ.length>0);curPage=1;render();});
            sc.addEventListener('click',function(){si.value='';searchQ='';sc.classList.remove('visible');curPage=1;render();});
        }
        var ss=document.getElementById('repSort');
        if(ss) ss.addEventListener('change',function(){sortKey=this.value;curPage=1;render();});
        render();
    }

    function parseD(r){var v=r.getAttribute('data-date');return v?new Date(v):new Date(0);}
    function getAmt(r,col){var c=r.querySelectorAll('td')[col];return c?parseFloat(c.textContent.replace(/[^0-9.]/g,'')||0):0;}

    function render(){
        var f=allRows.filter(function(row){
            if(searchQ&&row.textContent.toLowerCase().indexOf(searchQ)===-1)return false;
            return true;
        });
        f.sort(function(a,b){
            if(sortKey==='date-desc')return parseD(b)-parseD(a);
            if(sortKey==='date-asc') return parseD(a)-parseD(b);
            var amtA=Math.max(getAmt(a,4),getAmt(a,5)), amtB=Math.max(getAmt(b,4),getAmt(b,5));
            if(sortKey==='amt-desc')return amtB-amtA;
            if(sortKey==='amt-asc') return amtA-amtB;
            return 0;
        });

        var pp=perPage==='all'?Infinity:parseInt(perPage), total=f.length;
        var tpg=pp===Infinity?1:Math.max(1,Math.ceil(total/pp));
        if(curPage>tpg)curPage=tpg; if(curPage<1)curPage=1;
        var s=pp===Infinity?0:(curPage-1)*pp, e2=pp===Infinity?total:Math.min(s+pp,total);

        var tbody=document.querySelector('#repTable tbody');
        var colC=document.querySelector('#repTable thead tr').children.length;
        while(tbody.firstChild)tbody.removeChild(tbody.firstChild);

        if(f.length===0){
            var nr=document.createElement('tr');nr.className='lm-no-results';
            var nd=document.createElement('td');nd.setAttribute('colspan',colC);
            nd.textContent=searchQ?'No results for "'+searchQ+'"':'No transactions.';
            nr.appendChild(nd);tbody.appendChild(nr);
        } else {
            var lastM=null;
            f.slice(s,e2).forEach(function(row){
                var d=parseD(row);
                if(d.getTime()>0){
                    var mk=d.getFullYear()+'-'+d.getMonth();
                    if(mk!==lastM){
                        lastM=mk;
                        var sep=document.createElement('tr');sep.className='month-sep';
                        var std=document.createElement('td');std.setAttribute('colspan',colC);
                        std.textContent=d.toLocaleDateString('en-GB',{month:'long',year:'numeric'});
                        sep.appendChild(std);tbody.appendChild(sep);
                    }
                }
                tbody.appendChild(row);
            });
        }
        renderPager(total,tpg,s,e2,pp);
    }

    function renderPager(total,tpg,s,e2,pp){
        var el=document.getElementById('repPager');if(!el)return;el.innerHTML='';
        var wrap=document.createElement('div');wrap.className='lm-pagination';
        var info=document.createElement('div');info.className='lm-page-info';
        info.textContent=total===0?'No results':pp===Infinity?'Showing all '+total+' transactions':'Showing '+(s+1)+'–'+e2+' of '+total;
        wrap.appendChild(info);
        if(tpg>1){
            var pages=document.createElement('div');pages.className='lm-pages';
            function mkB(l,pg){var b=document.createElement('button');b.className='lm-page-btn';if(pg===curPage)b.classList.add('active');b.textContent=l;if(pg)b.addEventListener('click',function(){curPage=pg;render();});return b;}
            if(curPage>1)pages.appendChild(mkB('‹',curPage-1));
            var ns=[];if(tpg<=7){for(var i=1;i<=tpg;i++)ns.push(i);}else{ns=[1];if(curPage>3)ns.push('…');for(var i=Math.max(2,curPage-1);i<=Math.min(tpg-1,curPage+1);i++)ns.push(i);if(curPage<tpg-2)ns.push('…');ns.push(tpg);}
            ns.forEach(function(p){var b=mkB(p,p==='…'?0:p);if(p==='…')b.classList.add('lm-ellipsis');pages.appendChild(b);});
            if(curPage<tpg)pages.appendChild(mkB('›',curPage+1));
            wrap.appendChild(pages);
        }
        var ppW=document.createElement('div');ppW.className='lm-per-page-wrap';
        var sl=document.createElement('select');sl.className='lm-select';sl.style.padding='4px 8px';sl.style.margin='0 4px';
        [20,50,100,'all'].forEach(function(v){var o=document.createElement('option');o.value=v;o.textContent=v==='all'?'All':v;if((pp===Infinity&&v==='all')||pp===v)o.selected=true;sl.appendChild(o);});
        sl.addEventListener('change',function(){perPage=sl.value;curPage=1;render();});
        ppW.appendChild(document.createTextNode('Show '));ppW.appendChild(sl);ppW.appendChild(document.createTextNode(' per page'));
        wrap.appendChild(ppW);el.appendChild(wrap);
    }
    document.addEventListener('DOMContentLoaded',init);
})();
</script>

<?php
$content = ob_get_clean();
ob_start();
$printCategory = 'reports';
require BASE_PATH . '/views/partials/print-modal.php';
$content .= ob_get_clean();
require BASE_PATH . '/views/partials/layout.php'; ?>
