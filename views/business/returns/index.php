<?php
$pageTitle = 'Returns — ' . e($book['name']);
$sym = \App\Helpers\Database::row('SELECT symbol FROM book_currencies WHERE book_id=? AND is_default=1', [$book['id']]);
$sym = $sym['symbol'] ?? '৳';
$typeFilter = $_GET['type'] ?? 'all';
ob_start();
?>

<div class="page-header">
    <div class="page-header-left">
        <div class="breadcrumb">
            <a href="/books/<?= $book['id'] ?>">Books</a> <span>›</span>
            <span>Returns</span>
        </div>
        <h1><i class="fa-solid fa-rotate-left" style="color:var(--brand)"></i> Returns</h1>
        <p>Sales returns from customers &amp; purchase returns to suppliers</p>
    </div>
    <div style="display:flex;gap:8px">
        <button class="btn btn-secondary btn-sm" data-modal="printModal" title="Print PDF">
            <i class="fa-solid fa-print"></i> Print
        </button>
        <a href="/books/<?= $book['id'] ?>/returns/create?type=sales_return" class="btn btn-primary">
            <i class="fa-solid fa-plus"></i> Sales Return
        </a>
        <a href="/books/<?= $book['id'] ?>/returns/create?type=purchase_return" class="btn btn-secondary">
            <i class="fa-solid fa-truck-ramp-box"></i> Purchase Return
        </a>
    </div>
</div>

<!-- Summary cards -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:14px;margin-bottom:20px">
    <div class="card" style="text-align:center">
        <div style="font-size:11px;font-weight:700;text-transform:uppercase;color:var(--text-muted);letter-spacing:.5px">Sales Refunds Given</div>
        <div style="font-size:22px;font-weight:800;color:var(--red);margin-top:4px"><?= $sym.number_format($summary['sales_refunds'],0) ?></div>
    </div>
    <div class="card" style="text-align:center">
        <div style="font-size:11px;font-weight:700;text-transform:uppercase;color:var(--text-muted);letter-spacing:.5px">Purchase Refunds Received</div>
        <div style="font-size:22px;font-weight:800;color:var(--green);margin-top:4px"><?= $sym.number_format($summary['purchase_refunds'],0) ?></div>
    </div>
    <div class="card" style="text-align:center">
        <div style="font-size:11px;font-weight:700;text-transform:uppercase;color:var(--text-muted);letter-spacing:.5px">Total Returns</div>
        <div style="font-size:22px;font-weight:800;margin-top:4px"><?= (int)$summary['total_count'] ?></div>
    </div>
</div>

<!-- Returns table -->
<div class="card" style="padding:0;overflow:hidden">
    <!-- LM Controls -->
<div class="lm-controls">
    <div class="lm-search-wrap">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="text" class="lm-search" id="retTableSearch" placeholder="Search return no., party…">
        <button class="lm-search-clear" id="retTableClear"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <select class="lm-select" id="retTableSort">
        <option value="date-desc">Newest First</option>
        <option value="date-asc">Oldest First</option>
        <option value="amt-desc">Most Amount</option>
        <option value="amt-asc">Least Amount</option>
    </select>
</div>
<div class="lm-filter-pills">
    <span style="font-size:12px;font-weight:600;color:var(--text-muted)">Type:</span>
    <button class="btn btn-sm btn-primary" data-lmf="all">All</button>
    <button class="btn btn-sm btn-secondary" data-lmf="sales_return">Sales Returns</button>
    <button class="btn btn-sm btn-secondary" data-lmf="purchase_return">Purchase Returns</button>
</div>
<!-- Month navigator -->
<div style="display:flex;align-items:center;gap:8px;margin-bottom:14px;flex-wrap:wrap">
    <a href="?month=<?= $prevMonth ?>&type=<?= $typeFilter ?>" class="btn btn-secondary btn-sm">
        <i class="fa-solid fa-chevron-left"></i>
    </a>
    <div style="text-align:center;min-width:140px">
        <div style="font-weight:600;font-size:14px"><?= date('F Y', strtotime($month.'-01')) ?></div>
        <div style="font-size:11px;color:var(--text-muted)"><?= count($returns) ?> returns</div>
    </div>
    <a href="?month=<?= $nextMonth ?>&type=<?= $typeFilter ?>"
       class="btn btn-secondary btn-sm <?= $isCurrent ? 'disabled' : '' ?>"
       style="<?= $isCurrent ? 'opacity:.35;pointer-events:none' : '' ?>">
        <i class="fa-solid fa-chevron-right"></i>
    </a>
</div>
<?php if (empty($returns)): ?>
    <div style="padding:48px;text-align:center;color:var(--text-muted)">
        <i class="fa-solid fa-rotate-left" style="font-size:40px;opacity:.3;margin-bottom:12px;display:block"></i>
        No returns recorded yet.
    </div>
    <?php else: ?>
    <div style="overflow-x:auto">
    <table id="retTable" style="width:100%;border-collapse:collapse">
        <thead>
            <tr style="background:var(--bg)">
                <th style="padding:10px 14px;font-size:11px;font-weight:700;text-transform:uppercase;color:var(--text-muted);text-align:left;border-bottom:1px solid var(--border)">Return No</th>
                <th style="padding:10px 14px;font-size:11px;font-weight:700;text-transform:uppercase;color:var(--text-muted);text-align:left;border-bottom:1px solid var(--border)">Type</th>
                <th style="padding:10px 14px;font-size:11px;font-weight:700;text-transform:uppercase;color:var(--text-muted);text-align:left;border-bottom:1px solid var(--border)">Party</th>
                <th style="padding:10px 14px;font-size:11px;font-weight:700;text-transform:uppercase;color:var(--text-muted);text-align:left;border-bottom:1px solid var(--border)">Orig. Invoice</th>
                <th style="padding:10px 14px;font-size:11px;font-weight:700;text-transform:uppercase;color:var(--text-muted);text-align:left;border-bottom:1px solid var(--border)">Date</th>
                <th style="padding:10px 14px;font-size:11px;font-weight:700;text-transform:uppercase;color:var(--text-muted);text-align:right;border-bottom:1px solid var(--border)">Subtotal</th>
                <th style="padding:10px 14px;font-size:11px;font-weight:700;text-transform:uppercase;color:var(--text-muted);text-align:right;border-bottom:1px solid var(--border)">Discount</th>
                <th style="padding:10px 14px;font-size:11px;font-weight:700;text-transform:uppercase;color:var(--text-muted);text-align:right;border-bottom:1px solid var(--border)">Refund</th>
                <th style="border-bottom:1px solid var(--border);width:40px">Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($returns as $r): ?>
            <?php
            $isSale  = $r['type'] === 'sales_return';
            $party   = $isSale ? ($r['customer_name'] ?? '—') : ($r['supplier_name'] ?? '—');
            $typeClr = $isSale ? 'var(--amber)' : 'var(--blue)';
            $typeLabel = $isSale ? 'Sales Return' : 'Purchase Return';
            ?>
            <tr style="border-bottom:1px solid var(--border)" onmouseover="this.style.background='var(--bg)'" onmouseout="this.style.background=''" data-date="<?= $r['date'] ?>" data-filter="<?= e($r['type']) ?>">
                <td style="padding:10px 14px">
                    <a href="/books/<?= $book['id'] ?>/returns/<?= $r['id'] ?>" style="font-weight:700;color:var(--brand)">
                        <?= e($r['return_no']) ?>
                    </a>
                </td>
                <td style="padding:10px 14px">
                    <span style="background:<?= $typeClr ?>22;color:<?= $typeClr ?>;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:700">
                        <?= $typeLabel ?>
                    </span>
                </td>
                <td style="padding:10px 14px;font-size:13px"><?= e($party) ?></td>
                <td style="padding:10px 14px;font-size:13px">
                    <?php if ($r['orig_invoice_no']): ?>
                    <a href="/books/<?= $book['id'] ?>/invoices/<?= $r['invoice_id'] ?>" style="color:var(--brand)">
                        <?= e($r['orig_invoice_no']) ?>
                    </a>
                    <?php else: ?>—<?php endif; ?>
                </td>
                <td style="padding:10px 14px;font-size:13px;color:var(--text-muted)"><?= date('d M Y', strtotime($r['date'])) ?></td>
                <td style="padding:10px 14px;text-align:right;font-size:13px"><?= $sym.number_format($r['subtotal'],0) ?></td>
                <td style="padding:10px 14px;text-align:right;font-size:13px;color:var(--red)">
                    <?= $r['discount'] > 0 ? $sym.number_format($r['discount'],0) : '—' ?>
                </td>
                <td style="padding:10px 14px;text-align:right;font-weight:700;font-size:14px;color:<?= $isSale ? 'var(--red)' : 'var(--green)' ?>">
                    <?= $sym.number_format($r['total_refund'],0) ?>
                </td>
                <td style="white-space:nowrap">
                    <a href="/books/<?= $book['id'] ?>/returns/<?= $r['id'] ?>" class="btn btn-sm btn-secondary" title="View"><i class="fa-solid fa-eye"></i></a>
                    <form method="POST" action="/books/<?= $book['id'] ?>/returns/<?= $r['id'] ?>/delete"
                        onsubmit="return confirm('Delete this return? Stock changes will NOT be reversed.')">
                        <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                        <button class="btn btn-sm btn-danger"><i class="fa-solid fa-trash" style="color: #fff;"></i></button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
<div id="retPager"></div>
</div>


<script>
(function(){
var allRows=[],filterF='all',searchQ='',sortKey='date-desc',perPage=20,curPage=1;
function init(){
    allRows=Array.from(document.querySelectorAll('#retTable tbody tr'));
    var si=document.getElementById('retTableSearch'),sc=document.getElementById('retTableClear');
    if(si){si.addEventListener('input',function(){searchQ=this.value.toLowerCase().trim();sc.classList.toggle('visible',searchQ.length>0);curPage=1;render();});sc.addEventListener('click',function(){si.value='';searchQ='';sc.classList.remove('visible');curPage=1;render();});}
    var ss=document.getElementById('retTableSort');if(ss)ss.addEventListener('change',function(){sortKey=this.value;curPage=1;render();});
    document.querySelectorAll('[data-lmf]').forEach(function(b){b.addEventListener('click',function(){filterF=this.getAttribute('data-lmf');document.querySelectorAll('[data-lmf]').forEach(function(x){x.classList.remove('btn-primary');x.classList.add('btn-secondary');});this.classList.add('btn-primary');this.classList.remove('btn-secondary');curPage=1;render();});}); 
    render();
}
function parseD(r){var v=r.getAttribute('data-date');return v?new Date(v):new Date(0);}
function getAmt(r){for(var i=2;i<8;i++){var c=r.querySelectorAll('td')[i];if(c){var n=parseFloat(c.textContent.replace(/[^0-9.]/g,''));if(!isNaN(n)&&n>0)return n;}}return 0;}
function render(){
    var f=allRows.filter(function(row){
        if(filterF!=='all'){var rf=row.getAttribute('data-filter')||'';if(rf.split(',').map(function(s){return s.trim();}).indexOf(filterF)===-1)return false;}
        if(searchQ&&row.textContent.toLowerCase().indexOf(searchQ)===-1)return false;
        return true;
    });
    f.sort(function(a,b){
        var k=sortKey;
        if(k==='date-desc')return parseD(b)-parseD(a);if(k==='date-asc')return parseD(a)-parseD(b);
        if(k==='amt-desc')return getAmt(b)-getAmt(a);if(k==='amt-asc')return getAmt(a)-getAmt(b);
        var ta=a.textContent.trim(),tb=b.textContent.trim();
        if(k==='az')return ta.localeCompare(tb);if(k==='za')return tb.localeCompare(ta);
        return 0;
    });
    var pp=perPage==='all'?Infinity:parseInt(perPage),total=f.length,tpg=pp===Infinity?1:Math.max(1,Math.ceil(total/pp));
    if(curPage>tpg)curPage=tpg;if(curPage<1)curPage=1;
    var s=pp===Infinity?0:(curPage-1)*pp,e2=pp===Infinity?total:Math.min(s+pp,total);
    var tbody=document.querySelector('#retTable tbody'),colC=(document.querySelector('#retTable thead tr')||{}).children.length||6;
    while(tbody.firstChild)tbody.removeChild(tbody.firstChild);
    if(f.length===0){var nr=document.createElement('tr');nr.className='lm-no-results';var nd=document.createElement('td');nd.setAttribute('colspan',colC);nd.textContent='No records match.';nr.appendChild(nd);tbody.appendChild(nr);}
    else{f.slice(s,e2).forEach(function(row){tbody.appendChild(row);});}
    renderPager(document.getElementById('retPager'),total,tpg,s,e2,pp);
}
function renderPager(el,total,tpg,s,e2,pp){if(!el)return;el.innerHTML='';var wrap=document.createElement('div');wrap.className='lm-pagination';var info=document.createElement('div');info.className='lm-page-info';info.textContent=total===0?'No results':pp===Infinity?'All '+total+' records':'Showing '+(s+1)+'–'+e2+' of '+total;wrap.appendChild(info);if(tpg>1){var pages=document.createElement('div');pages.className='lm-pages';function mkB(l,pg){var b=document.createElement('button');b.className='lm-page-btn';if(pg===curPage)b.classList.add('active');b.textContent=l;if(pg)b.addEventListener('click',function(){curPage=pg;render();});return b;}if(curPage>1)pages.appendChild(mkB('‹',curPage-1));var ns=[];if(tpg<=7){for(var i=1;i<=tpg;i++)ns.push(i);}else{ns=[1];if(curPage>3)ns.push('…');for(var i=Math.max(2,curPage-1);i<=Math.min(tpg-1,curPage+1);i++)ns.push(i);if(curPage<tpg-2)ns.push('…');ns.push(tpg);}ns.forEach(function(p){var b=mkB(p,p==='…'?0:p);if(p==='…')b.classList.add('lm-ellipsis');pages.appendChild(b);});if(curPage<tpg)pages.appendChild(mkB('›',curPage+1));wrap.appendChild(pages);}var ppW=document.createElement('div');ppW.className='lm-per-page-wrap';var sl=document.createElement('select');sl.className='lm-select';sl.style.padding='4px 8px';sl.style.margin='0 4px';[20,50,100,'all'].forEach(function(v){var o=document.createElement('option');o.value=v;o.textContent=v==='all'?'All':v;if((pp===Infinity&&v==='all')||pp===v)o.selected=true;sl.appendChild(o);});sl.addEventListener('change',function(){perPage=sl.value;curPage=1;render();});ppW.appendChild(document.createTextNode('Show '));ppW.appendChild(sl);ppW.appendChild(document.createTextNode(' per page'));wrap.appendChild(ppW);el.appendChild(wrap);}
document.addEventListener('DOMContentLoaded',function(){init();});
})();
</script>
<?php
$content = ob_get_clean();
ob_start();
$printCategory = 'returns';
require BASE_PATH . '/views/partials/print-modal.php';
$content .= ob_get_clean();
require BASE_PATH . '/views/partials/layout.php'; ?>
