<?php
$pageTitle = 'Expenses — ' . e($book['name']);
ob_start();
?>

<div class="page-header">
    <div class="page-header-left">
        <div class="breadcrumb">
            <a href="/books/<?= $book['id'] ?>">Books</a> <span>›</span>
            <span>Expenses</span>
        </div>
        <h1><i class="fa-solid fa-receipt" style="color:var(--brand)"></i> Expenses</h1>
        <p>Track all outgoing costs</p>
    </div>
    <div style="display:flex;gap:8px">
        <button class="btn btn-secondary btn-sm" data-modal="printModal" title="Print PDF">
            <i class="fa-solid fa-print"></i> Print
        </button>
        <button class="btn btn-secondary btn-sm" data-modal="addCategoryModal">
            <i class="fa-solid fa-folder-plus"></i> Add Category
        </button>
        <button class="btn btn-primary" data-modal="addExpenseModal">
            <i class="fa-solid fa-plus"></i> Add Expense
        </button>
    </div>
</div>

<!-- LM Controls -->
<div class="lm-controls" style="margin-bottom:8px">
    <div class="lm-search-wrap">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="text" class="lm-search" id="expTableSearch" placeholder="Search title, category, note…">
        <button class="lm-search-clear" id="expTableClear"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <select class="lm-select" id="expTableSort">
        <option value="date-desc">Newest First</option>
        <option value="date-asc">Oldest First</option>
        <option value="amt-desc">Most Expensive</option>
        <option value="amt-asc">Least Expensive</option>
    </select>
</div>
<!-- Month navigator -->
<div style="display:flex;align-items:center;gap:10px;margin-bottom:18px;flex-wrap:wrap">
    <?php
    $prevMonth = date('Y-m', strtotime($month . '-01 -1 month'));
    $nextMonth = date('Y-m', strtotime($month . '-01 +1 month'));
    $isCurrent = $month === date('Y-m');
    ?>
    <a href="?month=<?= $prevMonth ?>&cat=<?= $catId ?>" class="btn btn-secondary btn-sm">
        <i class="fa-solid fa-chevron-left"></i>
    </a>
    <span style="font-weight:600;font-size:14px;min-width:120px;text-align:center">
        <?= date('F Y', strtotime($month . '-01')) ?>
    </span>
    <?php if (!$isCurrent): ?>
    <a href="?month=<?= $nextMonth ?>&cat=<?= $catId ?>" class="btn btn-secondary btn-sm">
        <i class="fa-solid fa-chevron-right"></i>
    </a>
    <?php else: ?>
    <span class="btn btn-secondary btn-sm" style="opacity:.4;cursor:not-allowed">
        <i class="fa-solid fa-chevron-right"></i>
    </span>
    <?php endif; ?>
    <span style="font-size:13px;color:var(--text-muted)">
        Total: <strong style="color:var(--red)"><?= format_money($monthTotal) ?></strong>
    </span>
</div>

<!-- Category filter pills -->
<?php if (!empty($categories)): ?>
<div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:16px">
    <a href="?month=<?= $month ?>&cat=0"
       class="btn btn-sm <?= $catId===0 ? 'btn-primary' : 'btn-secondary' ?>">
        All
    </a>
    <?php foreach ($categories as $cat): ?>
    <a href="?month=<?= $month ?>&cat=<?= $cat['id'] ?>"
       class="btn btn-sm <?= $catId===$cat['id'] ? 'btn-primary' : 'btn-secondary' ?>">
        <i class="fa-solid <?= e($cat['icon'] ?? 'fa-tag') ?>"></i>
        <?= e($cat['name']) ?>
    </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Expense list -->
<?php if (empty($expenses)): ?>
<div class="table-wrap">
    <div class="empty-state">
        <div class="empty-icon"><i class="fa-solid fa-receipt"></i></div>
        <h3>No expenses this month</h3>
        <p>Click "Add Expense" to record an outgoing cost.</p>
    </div>
</div>
<?php else: ?>
<div class="table-wrap">
    <table id="expTable">
        <thead>
            <tr>
                <th>Date</th>
                <th>Title</th>
                <th>Category</th>
                <th>Paid To</th>
                <th>Amount</th>
                <th>Receipt</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($expenses as $exp): ?>
        <tr>
            <td class="td-muted" style="white-space:nowrap"><?= format_date($exp['expense_date']) ?></td>
            <td>
                <div style="font-weight:500"><?= e($exp['title']) ?></div>
                <?php if (!empty($exp['note'])): ?>
                <div class="td-muted"><?= e($exp['note']) ?></div>
                <?php endif; ?>
            </td>
            <td>
                <?php if (!empty($exp['category_name'])): ?>
                <span style="display:inline-flex;align-items:center;gap:5px;background:var(--bg);padding:2px 8px;border-radius:99px;font-size:12px">
                    <i class="fa-solid <?= e($exp['category_icon'] ?? 'fa-tag') ?>" style="font-size:10px;color:var(--text-muted)"></i>
                    <?= e($exp['category_name']) ?>
                </span>
                <?php else: ?>
                <span class="td-muted">—</span>
                <?php endif; ?>
            </td>
            <td class="td-muted"><?= e($exp['paid_to'] ?? '—') ?></td>
            <td style="font-weight:700;color:var(--red)"><?= format_money((float)$exp['amount']) ?></td>
            <td>
                <?php if (!empty($exp['attachment'])): ?>
                <a href="<?= asset('uploads/'.$exp['attachment']) ?>"
                   target="_blank"
                   class="btn btn-sm btn-secondary"
                   title="View receipt">
                    <i class="fa-solid fa-file-image"></i>
                </a>
                <?php else: ?>
                <span class="td-muted">—</span>
                <?php endif; ?>
            </td>
            <td style="text-align:right;white-space:nowrap">
                <button class="btn btn-sm btn-secondary" title="Edit"
                        onclick="openExpenseEdit(
                            <?= $exp['id'] ?>,
                            '<?= e(addslashes($exp['title'])) ?>',
                            <?= (float)$exp['amount'] ?>,
                            '<?= $exp['expense_date'] ?>',
                            <?= $exp['category_id'] ?? 'null' ?>,
                            '<?= e(addslashes($exp['paid_to'] ?? '')) ?>',
                            '<?= e(addslashes($exp['note'] ?? '')) ?>'
                        )">
                    <i class="fa-solid fa-pen"></i>
                </button>
                <form method="POST"
                      action="/books/<?= $book['id'] ?>/expenses/<?= $exp['id'] ?>/delete"
                      style="display:inline"
                      onsubmit="return confirm('Delete this expense?')">
                    <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                    <button class="btn btn-sm btn-secondary" title="Delete">
                        <i class="fa-solid fa-trash" style="color:var(--red)"></i>
                    </button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr style="background:var(--bg)">
                <td colspan="4" style="padding:10px 14px;font-weight:600;text-align:right">
                    Month Total
                </td>
                <td style="padding:10px 14px;font-weight:700;color:var(--red)">
                    <?= format_money($monthTotal) ?>
                </td>
                <td colspan="2"></td>
            </tr>
        </tfoot>
    </table>
</div>
<?php endif; ?>


<!-- ══ ADD EXPENSE MODAL ══ -->
<div class="modal-backdrop" id="addExpenseModal">
    <div class="modal">
        <div class="modal-title">
            <i class="fa-solid fa-receipt" style="color:var(--red)"></i> Add Expense
        </div>
        <form method="POST" action="/books/<?= $book['id'] ?>/expenses/add"
              enctype="multipart/form-data">
            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
            <div class="form-grid" style="gap:12px">
                <div class="form-group full">
                    <label>Title *</label>
                    <input type="text" name="title" required placeholder="e.g. Electricity bill, Office rent…">
                </div>
                <div class="form-group">
                    <label>Amount *</label>
                    <input type="number" name="amount" min="0.01" step="0.01" required placeholder="0.00">
                </div>
                <div class="form-group">
                    <label>Date *</label>
                    <input type="date" name="date" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="form-group">
                    <label>Category</label>
                    <select name="category_id">
                        <option value="">— None —</option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>"><?= e($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Paid To</label>
                    <input type="text" name="paid_to" placeholder="Vendor / person name">
                </div>
                <div class="form-group full">
                    <label>Note</label>
                    <textarea name="note" style="min-height:50px" placeholder="Any details…"></textarea>
                </div>
                <div class="form-group full">
                    <label>Receipt (optional)</label>
                    <input type="file" name="receipt" accept=".jpg,.jpeg,.png,.webp,.pdf">
                    <small class="form-hint">JPG, PNG, PDF — max 10 MB</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-close-modal>Cancel</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-plus"></i> Add Expense
                </button>
            </div>
        </form>
    </div>
</div>


<!-- ══ EDIT EXPENSE MODAL ══ -->
<div class="modal-backdrop" id="editExpenseModal">
    <div class="modal">
        <div class="modal-title">
            <i class="fa-solid fa-pen" style="color:var(--brand)"></i> Edit Expense
        </div>
        <form method="POST" id="editExpenseForm">
            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
            <div class="form-grid" style="gap:12px">
                <div class="form-group full">
                    <label>Title *</label>
                    <input type="text" name="title" id="editExpTitle" required>
                </div>
                <div class="form-group">
                    <label>Amount *</label>
                    <input type="number" name="amount" id="editExpAmount" min="0.01" step="0.01" required>
                </div>
                <div class="form-group">
                    <label>Date *</label>
                    <input type="date" name="date" id="editExpDate" required>
                </div>
                <div class="form-group">
                    <label>Category</label>
                    <select name="category_id" id="editExpCat">
                        <option value="">— None —</option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>"><?= e($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Paid To</label>
                    <input type="text" name="paid_to" id="editExpPaidTo">
                </div>
                <div class="form-group full">
                    <label>Note</label>
                    <textarea name="note" id="editExpNote" style="min-height:50px"></textarea>
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


<!-- ══ ADD CATEGORY MODAL ══ -->
<div class="modal-backdrop" id="addCategoryModal">
    <div class="modal" style="max-width:380px">
        <div class="modal-title">
            <i class="fa-solid fa-folder-plus" style="color:var(--brand)"></i> Add Category
        </div>
        <form method="POST" action="/books/<?= $book['id'] ?>/expenses/category/add">
            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
            <div class="form-grid" style="gap:12px">
                <div class="form-group full">
                    <label>Category Name *</label>
                    <input type="text" name="name" required placeholder="e.g. Rent, Utility, Salary…">
                </div>
                <div class="form-group full">
                    <label>Icon</label>
                    <div class="icon-picker" id="iconPicker">
                        <?php
                        $iconList = [
                            'fa-tag'=>'Tag','fa-bolt'=>'Electric','fa-building'=>'Building',
                            'fa-users'=>'Team','fa-truck'=>'Truck','fa-wrench'=>'Tools',
                            'fa-bullhorn'=>'Marketing','fa-utensils'=>'Food','fa-laptop'=>'Tech',
                            'fa-car'=>'Vehicle','fa-home'=>'Home','fa-heart'=>'Health',
                            'fa-graduation-cap'=>'Education','fa-globe'=>'Internet',
                            'fa-box'=>'Supplies','fa-coffee'=>'Coffee','fa-seedling'=>'Agri',
                        ];
                        foreach ($iconList as $cls => $lbl):
                        ?>
                        <button type="button" class="icon-opt" data-icon="<?= $cls ?>" title="<?= $lbl ?>"
                                onclick="selectIcon(this)">
                            <i class="fa-solid <?= $cls ?>"></i>
                        </button>
                        <?php endforeach; ?>
                    </div>
                    <input type="hidden" name="icon" id="selectedIcon" value="fa-tag">
                    <small class="form-hint" id="selectedIconLabel">Selected: Tag</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-close-modal>Cancel</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-check"></i> Save Category
                </button>
            </div>
        </form>
    </div>
</div>

<style>
.icon-picker { display:flex; flex-wrap:wrap; gap:6px; margin-bottom:6px; }
.icon-opt {
    width:36px; height:36px; border:2px solid var(--border);
    border-radius:var(--radius); background:var(--white); cursor:pointer;
    display:flex; align-items:center; justify-content:center;
    font-size:15px; color:var(--text-muted); transition:all .15s;
}
.icon-opt:hover { border-color:var(--brand); color:var(--brand); }
.icon-opt.selected { border-color:var(--brand); background:var(--brand-light); color:var(--brand); }
</style>

<script>
// Icon picker
document.querySelector('.icon-opt[data-icon="fa-tag"]')?.classList.add('selected');

function selectIcon(btn) {
    document.querySelectorAll('.icon-opt').forEach(b => b.classList.remove('selected'));
    btn.classList.add('selected');
    document.getElementById('selectedIcon').value = btn.dataset.icon;
    document.getElementById('selectedIconLabel').textContent = 'Selected: ' + btn.title;
}

// Edit expense
function openExpenseEdit(id, title, amount, date, catId, paidTo, note) {
    document.getElementById('editExpenseForm').action = '/books/<?= $book['id'] ?>/expenses/' + id + '/edit';
    document.getElementById('editExpTitle').value   = title;
    document.getElementById('editExpAmount').value  = amount;
    document.getElementById('editExpDate').value    = date;
    document.getElementById('editExpPaidTo').value  = paidTo;
    document.getElementById('editExpNote').value    = note;
    const catSel = document.getElementById('editExpCat');
    catSel.value = catId || '';
    document.getElementById('editExpenseModal').classList.add('open');
}
</script>


<script>
(function(){
var allRows=[],searchQ='',sortKey='date-desc',perPage=20,curPage=1;
function init(){
    allRows=Array.from(document.querySelectorAll('#expTable tbody tr:not(.month-total-row)'));
    var si=document.getElementById('expTableSearch'),sc=document.getElementById('expTableClear');
    if(si){si.addEventListener('input',function(){searchQ=this.value.toLowerCase().trim();sc.classList.toggle('visible',searchQ.length>0);curPage=1;render();});sc.addEventListener('click',function(){si.value='';searchQ='';sc.classList.remove('visible');curPage=1;render();});}
    var ss=document.getElementById('expTableSort');if(ss)ss.addEventListener('change',function(){sortKey=this.value;curPage=1;render();});
    render();
}
function parseD(r){var v=r.getAttribute('data-date');return v?new Date(v):new Date(0);}
function getAmt(r){var c=r.querySelectorAll('td')[3];return c?parseFloat(c.textContent.replace(/[^0-9.]/g,'')||0):0;}
function render(){
    var f=allRows.filter(function(row){if(searchQ&&row.textContent.toLowerCase().indexOf(searchQ)===-1)return false;return true;});
    f.sort(function(a,b){
        if(sortKey==='date-desc')return parseD(b)-parseD(a);if(sortKey==='date-asc')return parseD(a)-parseD(b);
        if(sortKey==='amt-desc')return getAmt(b)-getAmt(a);if(sortKey==='amt-asc')return getAmt(a)-getAmt(b);return 0;
    });
    var pp=perPage==='all'?Infinity:parseInt(perPage),total=f.length,tpg=pp===Infinity?1:Math.max(1,Math.ceil(total/pp));
    if(curPage>tpg)curPage=tpg;if(curPage<1)curPage=1;
    var s=pp===Infinity?0:(curPage-1)*pp,e2=pp===Infinity?total:Math.min(s+pp,total);
    var tbody=document.querySelector('#expTable tbody'),colC=(document.querySelector('#expTable thead tr')||{}).children.length||6;
    // hide/show rows (keep month-total-rows but filter them out)
    allRows.forEach(function(r){r.style.display='none';});
    if(f.length===0){/* show no-results */var ex=document.getElementById('expNoResults');if(!ex){ex=document.createElement('tr');ex.id='expNoResults';ex.className='lm-no-results';var nd=document.createElement('td');nd.setAttribute('colspan',colC);nd.textContent='No expenses match.';ex.appendChild(nd);tbody.appendChild(ex);}ex.style.display='';}
    else{var ex=document.getElementById('expNoResults');if(ex)ex.style.display='none';f.slice(s,e2).forEach(function(r){r.style.display='';});}
    var el=document.getElementById('expTablePager');if(el)renderPager(el,total,tpg,s,e2,pp);
}
function renderPager(el,total,tpg,s,e2,pp){el.innerHTML='';var wrap=document.createElement('div');wrap.className='lm-pagination';var info=document.createElement('div');info.className='lm-page-info';info.textContent=total===0?'No results':pp===Infinity?'All '+total:' Showing '+(s+1)+'–'+e2+' of '+total;wrap.appendChild(info);if(tpg>1){var pages=document.createElement('div');pages.className='lm-pages';function mkB(l,pg){var b=document.createElement('button');b.className='lm-page-btn';if(pg===curPage)b.classList.add('active');b.textContent=l;if(pg)b.addEventListener('click',function(){curPage=pg;render();});return b;}if(curPage>1)pages.appendChild(mkB('‹',curPage-1));var ns=[];if(tpg<=7){for(var i=1;i<=tpg;i++)ns.push(i);}else{ns=[1];if(curPage>3)ns.push('…');for(var i=Math.max(2,curPage-1);i<=Math.min(tpg-1,curPage+1);i++)ns.push(i);if(curPage<tpg-2)ns.push('…');ns.push(tpg);}ns.forEach(function(p){var b=mkB(p,p==='…'?0:p);if(p==='…')b.classList.add('lm-ellipsis');pages.appendChild(b);});if(curPage<tpg)pages.appendChild(mkB('›',curPage+1));wrap.appendChild(pages);}var ppW=document.createElement('div');ppW.className='lm-per-page-wrap';var sl=document.createElement('select');sl.className='lm-select';sl.style.padding='4px 8px';sl.style.margin='0 4px';[20,50,100,'all'].forEach(function(v){var o=document.createElement('option');o.value=v;o.textContent=v==='all'?'All':v;if((pp===Infinity&&v==='all')||pp===v)o.selected=true;sl.appendChild(o);});sl.addEventListener('change',function(){perPage=sl.value;curPage=1;render();});ppW.appendChild(document.createTextNode('Show '));ppW.appendChild(sl);ppW.appendChild(document.createTextNode(' per page'));wrap.appendChild(ppW);el.appendChild(wrap);}
document.addEventListener('DOMContentLoaded',init);
})();
</script>
<?php
$content = ob_get_clean();
ob_start();
$printCategory = 'expenses';
require BASE_PATH . '/views/partials/print-modal.php';
$content .= ob_get_clean();
require BASE_PATH . '/views/partials/layout.php'; ?>
