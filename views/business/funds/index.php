<?php
$pageTitle = 'Funds — ' . e($book['name']);
ob_start();
?>

<div class="page-header">
    <div class="page-header-left">
        <div class="breadcrumb">
            <a href="/books/<?= $book['id'] ?>">Dashboard</a> <span>›</span>
            <span>Funds</span>
        </div>
        <h1><i class="fa-solid fa-piggy-bank" style="color:var(--brand)"></i> Funds</h1>
        <p>Track money coming in and going out of the business account</p>
    </div>
    <div style="display:flex;gap:8px">
        <button class="btn btn-secondary" data-modal="withdrawModal">
            <i class="fa-solid fa-circle-arrow-up" style="color:var(--red)"></i> Withdraw
        </button>
        <button class="btn btn-secondary btn-sm" data-modal="printModal" title="Print PDF">
            <i class="fa-solid fa-print"></i> Print
        </button>
        <button class="btn btn-primary" data-modal="addFundsModal">
            <i class="fa-solid fa-circle-arrow-down"></i> Add Funds
        </button>
    </div>
</div>

<!-- Summary cards -->
<div class="stat-grid" style="grid-template-columns:repeat(auto-fit,minmax(160px,1fr));max-width:640px;margin-bottom:22px">
    <div class="stat-card" style="border-top:3px solid var(--green)">
        <div class="stat-label"><i class="fa-solid fa-circle-arrow-down" style="color:var(--green)"></i> Total Added</div>
        <div class="stat-value green"><?= format_money((float)($totals['total_added'] ?? 0)) ?></div>
    </div>
    <div class="stat-card" style="border-top:3px solid var(--red)">
        <div class="stat-label"><i class="fa-solid fa-circle-arrow-up" style="color:var(--red)"></i> Total Withdrawn</div>
        <div class="stat-value red"><?= format_money((float)($totals['total_withdrawn'] ?? 0)) ?></div>
    </div>
    <div class="stat-card" style="border-top:3px solid var(--brand)">
        <div class="stat-label"><i class="fa-solid fa-wallet" style="color:var(--brand)"></i> Net Balance</div>
        <?php $net = (float)($totals['total_added'] ?? 0) - (float)($totals['total_withdrawn'] ?? 0); ?>
        <div class="stat-value <?= $net >= 0 ? 'brand' : 'red' ?>">
            <?= format_money($net) ?>
        </div>
    </div>
</div>

<!-- Controls -->
<div class="lm-controls">
    <div class="lm-search-wrap">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="text" class="lm-search" id="fundsSearch" placeholder="Search title, note…">
        <button class="lm-search-clear" id="fundsClear"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <select class="lm-select" id="fundsSort">
        <option value="date-desc">Newest First</option>
        <option value="date-asc">Oldest First</option>
        <option value="amt-desc">Most Amount</option>
        <option value="amt-asc">Least Amount</option>
    </select>
</div>
<div class="lm-filter-pills">
    <span style="font-size:12px;font-weight:600;color:var(--text-muted)">Type:</span>
    <button class="btn btn-sm btn-primary" data-fuf="all">All</button>
    <button class="btn btn-sm btn-secondary" data-fuf="in">Added</button>
    <button class="btn btn-sm btn-secondary" data-fuf="out">Withdrawn</button>
</div>

<!-- Transactions table -->
<div style="display:flex;align-items:center;gap:8px;margin-bottom:14px;flex-wrap:wrap">
    <a href="?month=<?= $prevMonth ?>" class="btn btn-secondary btn-sm">
        <i class="fa-solid fa-chevron-left"></i>
    </a>
    <div style="text-align:center;min-width:140px">
        <div style="font-weight:600;font-size:14px"><?= date('F Y', strtotime($month.'-01')) ?></div>
        <div style="font-size:11px;color:var(--text-muted)"><?= count($transactions) ?> transactions</div>
    </div>
    <a href="?month=<?= $nextMonth ?>"
       class="btn btn-secondary btn-sm <?= $isCurrent ? 'disabled' : '' ?>"
       style="<?= $isCurrent ? 'opacity:.35;pointer-events:none' : '' ?>">
        <i class="fa-solid fa-chevron-right"></i>
    </a>
</div>

<?php if (empty($transactions)): ?>
<div class="table-wrap">
    <div class="empty-state">
        <div class="empty-icon"><i class="fa-solid fa-piggy-bank"></i></div>
        <h3>No fund transactions yet</h3>
        <p>Click "Add Funds" to record money brought into the business.</p>
    </div>
</div>
<?php else: ?>
<div class="table-wrap">
    <table id="fundsTable">
        <thead>
            <tr>
                <th data-sort="0">Date</th>
                <th>Source / Reason</th>
                <th>Type</th>
                <th>Amount</th>
                <th>Note</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($transactions as $tx): ?>
        <tr data-type="<?= $tx['type'] ?>" data-date="<?= $tx['fund_date'] ?>">
            <td class="td-muted" style="white-space:nowrap">
                <?= format_date($tx['fund_date']) ?>
            </td>
            <td style="font-weight:500"><?= e($tx['title']) ?></td>
            <td>
                <?php if ($tx['type'] === 'in'): ?>
                <span class="badge badge-green">
                    <i class="fa-solid fa-circle-arrow-down"></i> Added
                </span>
                <?php else: ?>
                <span class="badge badge-red">
                    <i class="fa-solid fa-circle-arrow-up"></i> Withdrawn
                </span>
                <?php endif; ?>
            </td>
            <td style="font-weight:700;color:<?= $tx['type']==='in' ? 'var(--green)' : 'var(--red)' ?>">
                <?= $tx['type']==='in' ? '+' : '−' ?><?= format_money((float)$tx['amount']) ?>
            </td>
            <td class="td-muted"><?= e($tx['note'] ?? '—') ?></td>
            <td style="text-align:right;white-space:nowrap">
                <button class="btn btn-sm btn-secondary"
                        title="Edit"
                        onclick="openFundEdit(<?= $tx['id'] ?>,<?= $tx['type']==='in'?'\'in\'':'\'out\'' ?>,'<?= e(addslashes($tx['title'])) ?>',<?= (float)$tx['amount'] ?>,'<?= $tx['fund_date'] ?>','<?= e(addslashes($tx['note'] ?? '')) ?>')">
                    <i class="fa-solid fa-pen"></i>
                </button>
                <form method="POST"
                      action="/books/<?= $book['id'] ?>/funds/<?= $tx['id'] ?>/delete"
                      style="display:inline"
                      onsubmit="return confirm('Delete this transaction?')">
                    <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                    <button class="btn btn-sm btn-secondary" title="Delete">
                        <i class="fa-solid fa-trash" style="color:var(--red)"></i>
                    </button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<div id="fundsPager"></div>
<?php endif; ?>


<!-- ══ ADD FUNDS MODAL ══ -->
<div class="modal-backdrop" id="addFundsModal">
    <div class="modal" style="max-width:420px">
        <div class="modal-title">
            <i class="fa-solid fa-circle-arrow-down" style="color:var(--green)"></i> Add Funds
        </div>
        <form method="POST" action="/books/<?= $book['id'] ?>/funds/add">
            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="type" value="add">
            <div class="form-grid" style="gap:12px">
                <div class="form-group full">
                    <label>Source / Reason *</label>
                    <input type="text" name="source" required placeholder="e.g. Owner deposit, Loan received…">
                </div>
                <div class="form-group">
                    <label>Amount *</label>
                    <input type="number" name="amount" min="0.01" step="0.01" required placeholder="0.00">
                </div>
                <div class="form-group">
                    <label>Date *</label>
                    <input type="date" name="date" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="form-group full">
                    <label>Note</label>
                    <textarea name="note" style="min-height:50px" placeholder="Any additional details…"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-close-modal>Cancel</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-check"></i> Add Funds
                </button>
            </div>
        </form>
    </div>
</div>


<!-- ══ WITHDRAW MODAL ══ -->
<div class="modal-backdrop" id="withdrawModal">
    <div class="modal" style="max-width:420px">
        <div class="modal-title">
            <i class="fa-solid fa-circle-arrow-up" style="color:var(--red)"></i> Withdraw Funds
        </div>
        <form method="POST" action="/books/<?= $book['id'] ?>/funds/add">
            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="type" value="withdraw">
            <div class="form-grid" style="gap:12px">
                <div class="form-group full">
                    <label>Reason *</label>
                    <input type="text" name="source" required placeholder="e.g. Owner withdrawal, Loan repayment…">
                </div>
                <div class="form-group">
                    <label>Amount *</label>
                    <input type="number" name="amount" min="0.01" step="0.01" required placeholder="0.00">
                </div>
                <div class="form-group">
                    <label>Date *</label>
                    <input type="date" name="date" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="form-group full">
                    <label>Note</label>
                    <textarea name="note" style="min-height:50px" placeholder="Any additional details…"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-close-modal>Cancel</button>
                <button type="submit" class="btn btn-danger">
                    <i class="fa-solid fa-circle-arrow-up"></i> Withdraw
                </button>
            </div>
        </form>
    </div>
</div>


<!-- ══ EDIT FUND MODAL ══ -->
<div class="modal-backdrop" id="editFundModal">
    <div class="modal" style="max-width:420px">
        <div class="modal-title">
            <i class="fa-solid fa-pen" style="color:var(--brand)"></i> Edit Transaction
        </div>
        <form method="POST" id="editFundForm">
            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="type" id="editFundType">
            <div class="form-grid" style="gap:12px">
                <div class="form-group full">
                    <label>Source / Reason *</label>
                    <input type="text" name="source" id="editFundSource" required>
                </div>
                <div class="form-group">
                    <label>Amount *</label>
                    <input type="number" name="amount" id="editFundAmount" min="0.01" step="0.01" required>
                </div>
                <div class="form-group">
                    <label>Date *</label>
                    <input type="date" name="date" id="editFundDate" required>
                </div>
                <div class="form-group full">
                    <label>Note</label>
                    <textarea name="note" id="editFundNote" style="min-height:50px"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-close-modal>Cancel</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-check"></i> Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openFundEdit(id, type, source, amount, date, note) {
    document.getElementById('editFundForm').action = '/books/<?= $book['id'] ?>/funds/' + id + '/edit';
    document.getElementById('editFundType').value   = type;
    document.getElementById('editFundSource').value = source;
    document.getElementById('editFundAmount').value = amount;
    document.getElementById('editFundDate').value   = date;
    document.getElementById('editFundNote').value   = note;
    document.getElementById('editFundModal').classList.add('open');
}
</script>


<script>
(function(){
var allRows=[],typeF='all',searchQ='',sortKey='date-desc',perPage=20,curPage=1;
function init(){
    allRows=Array.from(document.querySelectorAll('#fundsTable tbody tr'));
    var si=document.getElementById('fundsSearch'),sc=document.getElementById('fundsClear');
    if(si){si.addEventListener('input',function(){searchQ=this.value.toLowerCase().trim();sc.classList.toggle('visible',searchQ.length>0);curPage=1;render();});sc.addEventListener('click',function(){si.value='';searchQ='';sc.classList.remove('visible');curPage=1;render();});}
    var ss=document.getElementById('fundsSort');if(ss)ss.addEventListener('change',function(){sortKey=this.value;curPage=1;render();});
    document.querySelectorAll('[data-fuf]').forEach(function(b){b.addEventListener('click',function(){typeF=this.getAttribute('data-fuf');document.querySelectorAll('[data-fuf]').forEach(function(x){x.classList.remove('btn-primary');x.classList.add('btn-secondary');});this.classList.add('btn-primary');this.classList.remove('btn-secondary');curPage=1;render();});});
    render();
}
function td(r,i){var c=r.querySelectorAll('td')[i];return c?c.textContent.trim():'';}
function parseD(r){return new Date(r.getAttribute('data-date')||0);}
function render(){
    var f=allRows.filter(function(row){
        if(typeF!=='all'&&row.getAttribute('data-type')!==typeF)return false;
        if(searchQ&&row.textContent.toLowerCase().indexOf(searchQ)===-1)return false;
        return true;
    });
    f.sort(function(a,b){
        if(sortKey==='date-desc')return parseD(b)-parseD(a);
        if(sortKey==='date-asc')return parseD(a)-parseD(b);
        var na=parseFloat(td(a,3).replace(/[^0-9.]/g,'')||0),nb=parseFloat(td(b,3).replace(/[^0-9.]/g,'')||0);
        if(sortKey==='amt-desc')return nb-na;if(sortKey==='amt-asc')return na-nb;
        return 0;
    });
    var pp=perPage==='all'?Infinity:parseInt(perPage),total=f.length,tpg=pp===Infinity?1:Math.max(1,Math.ceil(total/pp));
    if(curPage>tpg)curPage=tpg;if(curPage<1)curPage=1;
    var s=pp===Infinity?0:(curPage-1)*pp,e=pp===Infinity?total:Math.min(s+pp,total);
    var tbody=document.querySelector('#fundsTable tbody'),colC=document.querySelector('#fundsTable thead tr').children.length;
    while(tbody.firstChild)tbody.removeChild(tbody.firstChild);
    if(f.length===0){var nr=document.createElement('tr');nr.className='lm-no-results';var nd=document.createElement('td');nd.setAttribute('colspan',colC);nd.textContent='No fund entries match.';nr.appendChild(nd);tbody.appendChild(nr);}
    else{var lastM=null;f.slice(s,e).forEach(function(row){
        var d=parseD(row);if(!isNaN(d)){var mk=d.getFullYear()+'-'+d.getMonth();if(mk!==lastM){lastM=mk;var sep=document.createElement('tr');sep.className='month-sep';var std=document.createElement('td');std.setAttribute('colspan',colC);std.textContent=d.toLocaleDateString('en-GB',{month:'long',year:'numeric'});sep.appendChild(std);tbody.appendChild(sep);}}
        tbody.appendChild(row);
    });}
    renderPager(document.getElementById('fundsPager'),total,tpg,s,e,pp);
}
function renderPager(el,total,tpg,s,e,pp){if(!el)return;el.innerHTML='';var wrap=document.createElement('div');wrap.className='lm-pagination';var info=document.createElement('div');info.className='lm-page-info';info.textContent=total===0?'No results':pp===Infinity?'Showing all '+total+' records':'Showing '+(s+1)+'\u2013'+e+' of '+total;wrap.appendChild(info);if(tpg>1){var pages=document.createElement('div');pages.className='lm-pages';function mkB(l,pg){var b=document.createElement('button');b.className='lm-page-btn';if(pg===curPage)b.classList.add('active');b.textContent=l;if(pg)b.addEventListener('click',function(){curPage=pg;render();});return b;}if(curPage>1)pages.appendChild(mkB('\u2039',curPage-1));var ns=[];if(tpg<=7){for(var i=1;i<=tpg;i++)ns.push(i);}else{ns=[1];if(curPage>3)ns.push('\u2026');for(var i=Math.max(2,curPage-1);i<=Math.min(tpg-1,curPage+1);i++)ns.push(i);if(curPage<tpg-2)ns.push('\u2026');ns.push(tpg);}ns.forEach(function(p){var b=mkB(p,p==='\u2026'?0:p);if(p==='\u2026')b.classList.add('lm-ellipsis');pages.appendChild(b);});if(curPage<tpg)pages.appendChild(mkB('\u203a',curPage+1));wrap.appendChild(pages);}var ppW=document.createElement('div');ppW.className='lm-per-page-wrap';var sl=document.createElement('select');sl.className='lm-select';sl.style.padding='4px 8px';sl.style.margin='0 4px';[20,50,100,'all'].forEach(function(v){var o=document.createElement('option');o.value=v;o.textContent=v==='all'?'All':v;if((pp===Infinity&&v==='all')||pp===v)o.selected=true;sl.appendChild(o);});sl.addEventListener('change',function(){perPage=sl.value;curPage=1;render();});ppW.appendChild(document.createTextNode('Show '));ppW.appendChild(sl);ppW.appendChild(document.createTextNode(' per page'));wrap.appendChild(ppW);el.appendChild(wrap);}
document.addEventListener('DOMContentLoaded',function(){init();});
})();
</script>
<?php
$content = ob_get_clean();
ob_start();
$printCategory = 'funds';
require BASE_PATH . '/views/partials/print-modal.php';
$content .= ob_get_clean();
require BASE_PATH . '/views/partials/layout.php'; ?>
