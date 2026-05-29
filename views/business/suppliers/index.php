<?php
$pageTitle = 'Suppliers — ' . e($book['name']);
ob_start();
?>
<div class="page-header">
    <div class="page-header-left">
        <div class="breadcrumb">
            <a href="/books">Books</a> <span>›</span>
            <a href="/books/<?= $book['id'] ?>"><?= e($book['name']) ?></a> <span>›</span>
            <span>Suppliers</span>
        </div>
        <h1><i class="fa-solid fa-truck" style="color:var(--brand)"></i> Suppliers</h1>
        <p>Add, edit, remove suppliers and keep track of all of them</p>
        <p><?= count($suppliers) ?> supplier<?= count($suppliers) !== 1 ? 's' : '' ?></p>
    </div>
        <button class="btn btn-secondary btn-sm" data-modal="printModal" title="Print PDF">
            <i class="fa-solid fa-print"></i> Print
        </button>
        <button class="btn btn-primary" data-modal="addSupplierModal">+ Add Supplier</button>
</div>
<!-- LM Controls -->
<div class="lm-controls">
    <div class="lm-search-wrap">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="text" class="lm-search" id="suppTableSearch" placeholder="Search name, company, phone, email…">
        <button class="lm-search-clear" id="suppTableClear"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <select class="lm-select" id="suppTableSort">
        <option value="az">Name A–Z</option>
        <option value="za">Name Z–A</option>
        <option value="amt-desc">Most Amount</option>
        <option value="amt-asc">Least Amount</option>
    </select>
</div>

<?php if (empty($suppliers)): ?>
<div class="table-wrap">
    <div class="empty-state">
        <div class="empty-icon">🏭</div>
        <h3>No suppliers yet</h3>
        <p>Add suppliers to track your purchases.</p>
    </div>
</div>
<?php else: ?>
<div class="table-wrap">
    <table id="suppTable">
        <thead>
            <tr>
                <th>Name</th>
                <th>Company</th>
                <th>Phone</th>
                <th>Invoices</th>
                <th style="text-align:right">Total Purchased</th>
                <th style="text-align:right">Paid</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($suppliers as $s): ?>
        <tr>
            <td>
                <a href="/books/<?= $book['id'] ?>/suppliers/<?= $s['id'] ?>"
                   style="font-weight:500;color:var(--brand);text-decoration:none"><?= e($s['name']) ?></a>
            </td>
            <td class="td-muted"><?= $s['company'] ? e($s['company']) : '—' ?></td>
            <td class="td-muted"><?= $s['phone']   ? e($s['phone'])   : '—' ?></td>
            <td class="td-muted"><?= $s['invoice_count'] ?></td>
            <td style="text-align:right" class="td-amount"><?= format_money($s['total_billed']) ?></td>
            <td style="text-align:right" class="td-amount in"><?= format_money($s['total_paid']) ?></td>
            <td style="white-space:nowrap">
                <a href="/books/<?= $book['id'] ?>/suppliers/<?= $s['id'] ?>" title="View" class="btn btn-sm btn-secondary"><i class="fa-solid fa-eye"></i></a>
                <button class="btn btn-sm btn-secondary" title="Edit" data-modal="editSupplierModal"><i class="fa-solid fa-pen"></i></button>
                <form method="POST" action="/books/<?= $book['id'] ?>/suppliers/<?= $s['id'] ?>/delete" style="display: inline;"
                data-confirm="Delete <?= e($s['name']) ?>?">
                <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                <button class="btn btn-sm btn-danger" title="Delete"><i class="fa-solid fa-trash" style="color: #fff;"></i></button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
<div id="suppPager"></div>

<!-- ADD SUPPLIER MODAL -->
<div class="modal-backdrop" id="addSupplierModal">
    <div class="modal">
        <div class="modal-title">Add Supplier</div>
        <form method="POST" action="/books/<?= $book['id'] ?>/suppliers/add">
            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
            <div class="form-grid" style="gap:12px">
                <div class="form-group full"><label>Name *</label><input type="text" name="name" required placeholder="Contact name"></div>
                <div class="form-group full"><label>Company</label><input type="text" name="company" placeholder="Company name"></div>
                <div class="form-group"><label>Phone</label><input type="text" name="phone" placeholder="+880…"></div>
                <div class="form-group"><label>Email</label><input type="email" name="email"></div>
                <div class="form-group full"><label>Address</label><textarea name="address" style="min-height:56px"></textarea></div>
                <div class="form-group full"><label>Notes</label><textarea name="notes" style="min-height:48px"></textarea></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-close-modal>Cancel</button>
                <button type="submit" class="btn btn-primary">Save Supplier</button>
            </div>
        </form>
    </div>
</div>

<!-- EDIT MODAL -->
<div class="modal-backdrop" id="editSupplierModal">
    <div class="modal">
        <div class="modal-title">Edit Supplier</div>
        <form method="POST" action="/books/<?= $book['id'] ?>/suppliers/<?= $s['id'] ?>/edit">
            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
            <div class="form-grid" style="gap:12px">
                <div class="form-group full"><label>Name *</label><input type="text" name="name" value="<?= e($s['name']) ?>" required></div>
                <div class="form-group full"><label>Company</label><input type="text" name="company" value="<?= e($s['company'] ?? '') ?>"></div>
                <div class="form-group"><label>Phone</label><input type="text" name="phone" value="<?= e($s['phone'] ?? '') ?>"></div>
                <div class="form-group"><label>Email</label><input type="email" name="email" value="<?= e($s['email'] ?? '') ?>"></div>
                <div class="form-group full"><label>Address</label><textarea name="address" style="min-height:56px"><?= e($s['address'] ?? '') ?></textarea></div>
                <div class="form-group full"><label>Notes</label><textarea name="notes" style="min-height:48px"><?= e($s['notes'] ?? '') ?></textarea></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-close-modal>Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>


<script>
(function(){
var allRows=[],filterF='all',searchQ='',sortKey='az',perPage=20,curPage=1;
function init(){
    allRows=Array.from(document.querySelectorAll('#suppTable tbody tr'));
    var si=document.getElementById('suppTableSearch'),sc=document.getElementById('suppTableClear');
    if(si){si.addEventListener('input',function(){searchQ=this.value.toLowerCase().trim();sc.classList.toggle('visible',searchQ.length>0);curPage=1;render();});sc.addEventListener('click',function(){si.value='';searchQ='';sc.classList.remove('visible');curPage=1;render();});}
    var ss=document.getElementById('suppTableSort');if(ss)ss.addEventListener('change',function(){sortKey=this.value;curPage=1;render();});
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
    var tbody=document.querySelector('#suppTable tbody'),colC=(document.querySelector('#suppTable thead tr')||{}).children.length||6;
    while(tbody.firstChild)tbody.removeChild(tbody.firstChild);
    if(f.length===0){var nr=document.createElement('tr');nr.className='lm-no-results';var nd=document.createElement('td');nd.setAttribute('colspan',colC);nd.textContent='No records match.';nr.appendChild(nd);tbody.appendChild(nr);}
    else{var lastM=null;f.slice(s,e2).forEach(function(row){
        var d=parseD(row);
        if(d.getTime()>0){var mk=d.getFullYear()+'-'+d.getMonth();if(mk!==lastM){lastM=mk;var sep=document.createElement('tr');sep.className='month-sep';var std=document.createElement('td');std.setAttribute('colspan',colC);std.textContent=d.toLocaleDateString('en-GB',{month:'long',year:'numeric'});sep.appendChild(std);tbody.appendChild(sep);}}
        tbody.appendChild(row);
    });}
    renderPager(document.getElementById('suppPager'),total,tpg,s,e2,pp);
}
function renderPager(el,total,tpg,s,e2,pp){if(!el)return;el.innerHTML='';var wrap=document.createElement('div');wrap.className='lm-pagination';var info=document.createElement('div');info.className='lm-page-info';info.textContent=total===0?'No results':pp===Infinity?'All '+total+' records':'Showing '+(s+1)+'–'+e2+' of '+total;wrap.appendChild(info);if(tpg>1){var pages=document.createElement('div');pages.className='lm-pages';function mkB(l,pg){var b=document.createElement('button');b.className='lm-page-btn';if(pg===curPage)b.classList.add('active');b.textContent=l;if(pg)b.addEventListener('click',function(){curPage=pg;render();});return b;}if(curPage>1)pages.appendChild(mkB('‹',curPage-1));var ns=[];if(tpg<=7){for(var i=1;i<=tpg;i++)ns.push(i);}else{ns=[1];if(curPage>3)ns.push('…');for(var i=Math.max(2,curPage-1);i<=Math.min(tpg-1,curPage+1);i++)ns.push(i);if(curPage<tpg-2)ns.push('…');ns.push(tpg);}ns.forEach(function(p){var b=mkB(p,p==='…'?0:p);if(p==='…')b.classList.add('lm-ellipsis');pages.appendChild(b);});if(curPage<tpg)pages.appendChild(mkB('›',curPage+1));wrap.appendChild(pages);}var ppW=document.createElement('div');ppW.className='lm-per-page-wrap';var sl=document.createElement('select');sl.className='lm-select';sl.style.padding='4px 8px';sl.style.margin='0 4px';[20,50,100,'all'].forEach(function(v){var o=document.createElement('option');o.value=v;o.textContent=v==='all'?'All':v;if((pp===Infinity&&v==='all')||pp===v)o.selected=true;sl.appendChild(o);});sl.addEventListener('change',function(){perPage=sl.value;curPage=1;render();});ppW.appendChild(document.createTextNode('Show '));ppW.appendChild(sl);ppW.appendChild(document.createTextNode(' per page'));wrap.appendChild(ppW);el.appendChild(wrap);}
document.addEventListener('DOMContentLoaded',init);
})();
</script>
<?php
$content = ob_get_clean();
ob_start();
$printCategory = 'suppliers';
require BASE_PATH . '/views/partials/print-modal.php';
$content .= ob_get_clean();
require BASE_PATH . '/views/partials/layout.php'; ?>
