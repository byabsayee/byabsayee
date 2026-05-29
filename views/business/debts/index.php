<?php
$pageTitle = 'Debts — ' . e($book['name']);
ob_start();
?>

<div class="page-header">
    <div class="page-header-left">
        <div class="breadcrumb">
            <a href="/books/<?= $book['id'] ?>">Books</a> <span>›</span>
            <span>Debts</span>
        </div>
        <h1><i class="fa-solid fa-file-circle-minus" style="color:var(--red)"></i> Debts</h1>
        <p>Money your business owes — loans, supplier credit, payables</p>
    </div>
    <button class="btn btn-secondary btn-sm" data-modal="printModal" title="Print PDF">
            <i class="fa-solid fa-print"></i> Print
        </button>
    <button class="btn btn-primary" data-modal="addDebtModal">
        <i class="fa-solid fa-plus"></i> Add Debt
    </button>
</div>

<!-- Summary cards -->
<div class="stat-grid" style="grid-template-columns:repeat(auto-fit,minmax(150px,1fr));max-width:760px;margin-bottom:22px">
    <div class="stat-card" style="border-left:4px solid var(--red)">
        <div class="stat-label"><i class="fa-solid fa-circle-exclamation" style="color:var(--red)"></i> Outstanding</div>
        <div class="stat-value red"><?= format_money((float)($summary['outstanding'] ?? 0), $symbol) ?></div>
        <div class="stat-sub"><?= (int)($summary['unpaid_count']??0) + (int)($summary['partial_count']??0) ?> active</div>
    </div>
    <div class="stat-card" style="border-left:4px solid var(--green)">
        <div class="stat-label"><i class="fa-solid fa-check-circle" style="color:var(--green)"></i> Repaid</div>
        <div class="stat-value green"><?= format_money((float)($summary['total_paid'] ?? 0), $symbol) ?></div>
        <div class="stat-sub"><?= (int)($summary['paid_count']??0) ?> fully settled</div>
    </div>
    <div class="stat-card" style="border-left:4px solid var(--amber)">
        <div class="stat-label"><i class="fa-solid fa-hourglass-half" style="color:var(--amber)"></i> Partial</div>
        <div class="stat-value" style="color:var(--amber)"><?= (int)($summary['partial_count']??0) ?></div>
        <div class="stat-sub">partially repaid</div>
    </div>
    <div class="stat-card">
        <div class="stat-label"><i class="fa-solid fa-list"></i> Total</div>
        <div class="stat-value brand"><?= (int)($summary['total_count']??0) ?></div>
        <div class="stat-sub">all debt records</div>
    </div>
</div>

<!-- Debts -->
<!-- LM Controls -->
<div class="lm-controls">
    <div class="lm-search-wrap">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="text" class="lm-search" id="debtsTableSearch" placeholder="Search creditor, invoice, note…">
        <button class="lm-search-clear" id="debtsTableClear"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <select class="lm-select" id="debtsTableSort">
        <option value="date-desc">Newest First</option>
        <option value="date-asc">Oldest First</option>
        <option value="amt-desc">Most Amount</option>
        <option value="amt-asc">Least Amount</option>
    </select>
</div>
<div style="display:flex;align-items:center;gap:8px;margin-bottom:14px;flex-wrap:wrap">
    <a href="?month=<?= $prevMonth ?>&filter=<?= $filter ?><?= $search ? '&q='.urlencode($search) : '' ?>" class="btn btn-secondary btn-sm">
        <i class="fa-solid fa-chevron-left"></i>
    </a>
    <div style="text-align:center;min-width:140px">
        <div style="font-weight:600;font-size:14px"><?= date('F Y', strtotime($month.'-01')) ?></div>
        <div style="font-size:11px;color:var(--text-muted)"><?= count($debts) ?> debts</div>
    </div>
    <a href="?month=<?= $nextMonth ?>&filter=<?= $filter ?><?= $search ? '&q='.urlencode($search) : '' ?>"
       class="btn btn-secondary btn-sm <?= $isCurrent ? 'disabled' : '' ?>"
       style="<?= $isCurrent ? 'opacity:.35;pointer-events:none' : '' ?>">
        <i class="fa-solid fa-chevron-right"></i>
    </a>
</div>
<div class="lm-filter-pills">
    <span style="font-size:12px;font-weight:600;color:var(--text-muted)">Status:</span>
    <button class="btn btn-sm btn-primary" data-lmf="all">All</button>
    <button class="btn btn-sm btn-secondary" data-lmf="unpaid">Unpaid</button>
    <button class="btn btn-sm btn-secondary" data-lmf="partial">Partial</button>
    <button class="btn btn-sm btn-secondary" data-lmf="paid">Paid</button>
</div>
<?php if (empty($debts)): ?>
<div class="table-wrap">
    <div class="empty-state">
        <div class="empty-icon"><i class="fa-solid fa-file-circle-minus"></i></div>
        <h3>No debts recorded</h3>
        <p>Track loans, supplier credit lines, or any money your business owes.</p>
    </div>
</div>
<?php else: ?>
<div class="table-wrap">
    <table id="debtsTable">
        <thead>
            <tr>
                <th>Title</th>
                <th>Creditor / Party</th>
                <th>Total Owed</th>
                <th>Repaid</th>
                <th>Remaining</th>
                <th>Due Date</th>
                <th>Status</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($debts as $debt):
            $remaining  = (float)$debt['amount'] - (float)$debt['paid_amount'];
            $pct        = $debt['amount'] > 0 ? min(100, round($debt['paid_amount'] / $debt['amount'] * 100)) : 0;
            $statusMap  = [
                'unpaid'    => ['badge-red',   'Unpaid'],
                'partial'   => ['badge-amber', 'Partial'],
                'paid'      => ['badge-green', 'Paid'],
                'cancelled' => ['badge-gray',  'Cancelled'],
            ];
            [$badgeClass, $badgeLabel] = $statusMap[$debt['status']] ?? ['badge-gray', ucfirst($debt['status'])];
        ?>
        <tr data-date="<?= $debt['due_date'] ?? '' ?>" data-filter="<?= e($debt['status']) ?>">
            <td>
                <div style="font-weight:600"><?= e($debt['title']) ?></div>
                <?php if (!empty($debt['invoice_id'])): ?>
                <div style="margin-top:2px">
                    <a href="/books/<?= $book['id'] ?>/invoices/<?= (int)$debt['invoice_id'] ?>"
                       style="color:var(--brand);text-decoration:none;font-size:12px"
                       title="View Purchase Invoice">
                        <i class="fa-solid fa-file-invoice fa-xs"></i> View Invoice
                    </a>
                </div>
                <?php endif; ?>
                <?php if (!empty($debt['note'])): ?>
                <div class="td-muted" style="font-size:11px;font-style:italic;max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= e($debt['note']) ?></div>
                <?php endif; ?>
                <?php if (!empty($paymentsByDebt[$debt['id']])): ?>
                <div style="margin-top:4px">
                    <?php foreach (array_slice($paymentsByDebt[$debt['id']], 0, 2) as $p): ?>
                    <span style="font-size:10px;background:var(--green-bg,#f0fdf4);color:var(--green);padding:1px 6px;border-radius:99px;margin-right:3px;display:inline-block;margin-top:2px">
                        <i class="fa-solid fa-check"></i> <?= format_money((float)$p['amount']) ?> · <?= date('d M', strtotime($p['paid_at'])) ?>
                    </span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </td>
            <td>
                <?php if (!empty($debt['party'])): ?>
                <div style="display:flex;align-items:center;gap:6px">
                    <div style="width:28px;height:28px;border-radius:50%;background:var(--red-bg,#fef2f2);color:var(--red);display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;flex-shrink:0">
                        <?= mb_strtoupper(mb_substr($debt['party'],0,1)) ?>
                    </div>
                    <span style="font-weight:500"><?= e($debt['party']) ?></span>
                </div>
                <?php else: ?>
                <span class="td-muted">—</span>
                <?php endif; ?>
            </td>
            <td style="font-weight:600"><?= format_money((float)$debt['amount'], $symbol) ?></td>
            <td>
                <div style="color:var(--green);font-weight:600"><?= format_money((float)$debt['paid_amount'], $symbol) ?></div>
                <div style="height:3px;background:var(--border);border-radius:99px;width:80px;margin-top:3px">
                    <div style="height:100%;border-radius:99px;width:<?= $pct ?>%;background:<?= $debt['status']==='paid'?'var(--green)':'var(--amber)' ?>"></div>
                </div>
                <div style="font-size:10px;color:var(--text-muted);margin-top:1px"><?= $pct ?>%</div>
            </td>
            <td>
                <?php if ($remaining > 0.001): ?>
                <span style="color:var(--red);font-weight:700"><?= format_money($remaining, $symbol) ?></span>
                <?php else: ?>
                <span style="color:var(--green)"><i class="fa-solid fa-check"></i> Cleared</span>
                <?php endif; ?>
            </td>
            <td class="td-muted">
                <?php if (!empty($debt['due_date'])):
                    $dueDate = new DateTime($debt['due_date']);
                    $today   = new DateTime('today');
                    $overdue = $dueDate < $today && !in_array($debt['status'], ['paid','cancelled']);
                ?>
                <span <?= $overdue ? 'style="color:var(--red);font-weight:600"' : '' ?>>
                    <?= format_date($debt['due_date']) ?>
                    <?php if ($overdue): ?>
                    <br><small><?= (int)$today->diff($dueDate)->days ?> days overdue</small>
                    <?php endif; ?>
                </span>
                <?php else: ?>—<?php endif; ?>
            </td>
            <td><span class="badge <?= $badgeClass ?>"><?= $badgeLabel ?></span></td>
            <td style="white-space:nowrap;text-align:right">
                <?php if (!in_array($debt['status'], ['paid','cancelled'])): ?>
                <button class="btn btn-sm btn-secondary" title="Record repayment"
                        onclick="openDebtPay(<?= $debt['id'] ?>,'<?= e(addslashes($debt['title'])) ?>',<?= $remaining ?>,'<?= $symbol ?>')">
                    <i class="fa-solid fa-money-bill-wave" style="color:var(--green)"></i>
                </button>
                <button class="btn btn-sm btn-secondary" title="Edit"
                        onclick="openDebtEdit(
                            <?= $debt['id'] ?>,
                            '<?= e(addslashes($debt['title'])) ?>',
                            '<?= e(addslashes($debt['party'] ?? '')) ?>',
                            <?= (float)$debt['amount'] ?>,
                            '<?= $debt['due_date'] ?? '' ?>',
                            '<?= e(addslashes($debt['note'] ?? '')) ?>'
                        )">
                    <i class="fa-solid fa-pen"></i>
                </button>
                <form method="POST" action="/books/<?= $book['id'] ?>/debts/<?= $debt['id'] ?>/cancel"
                      style="display:inline" onsubmit="return confirm('Mark this debt as cancelled?')">
                    <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                    <button class="btn btn-sm btn-secondary" title="Cancel debt">
                        <i class="fa-solid fa-ban" style="color:var(--amber)"></i>
                    </button>
                </form>
                <?php endif; ?>
                <form method="POST" action="/books/<?= $book['id'] ?>/debts/<?= $debt['id'] ?>/delete"
                      style="display:inline" onsubmit="return confirm('Delete this debt record permanently?')">
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
<?php endif; ?>
<div id="debtsPager"></div>


<!-- ══ ADD DEBT MODAL ══ -->
<div class="modal-backdrop" id="addDebtModal">
    <div class="modal">
        <div class="modal-title">
            <i class="fa-solid fa-file-circle-minus" style="color:var(--red)"></i> Add Debt
        </div>
        <form method="POST" action="/books/<?= $book['id'] ?>/debts/add">
            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
            <div class="form-grid" style="gap:12px">
                <div class="form-group full">
                    <label>Title *</label>
                    <input type="text" name="title" required placeholder="e.g. Bank loan, Supplier credit, Equipment purchase…">
                </div>
                <div class="form-group">
                    <label>Creditor / Party</label>
                    <input type="text" name="party" placeholder="Bank name, supplier, person…">
                </div>
                <div class="form-group">
                    <label>Total Amount *</label>
                    <input type="number" name="amount" min="0.01" step="0.01" required placeholder="0.00">
                </div>
                <div class="form-group full">
                    <label>Due / Repay Date</label>
                    <input type="date" name="due_date">
                </div>
                <div class="form-group full">
                    <label>Note (optional)</label>
                    <textarea name="note" style="min-height:60px" placeholder="Loan terms, interest rate, reference number…"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-close-modal>Cancel</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-plus"></i> Add Debt
                </button>
            </div>
        </form>
    </div>
</div>


<!-- ══ EDIT DEBT MODAL ══ -->
<div class="modal-backdrop" id="editDebtModal">
    <div class="modal">
        <div class="modal-title">
            <i class="fa-solid fa-pen" style="color:var(--brand)"></i> Edit Debt
        </div>
        <form method="POST" id="editDebtForm">
            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
            <div class="form-grid" style="gap:12px">
                <div class="form-group full">
                    <label>Title *</label>
                    <input type="text" name="title" id="editDebtTitle" required>
                </div>
                <div class="form-group">
                    <label>Creditor / Party</label>
                    <input type="text" name="party" id="editDebtParty">
                </div>
                <div class="form-group">
                    <label>Total Amount *</label>
                    <input type="number" name="amount" id="editDebtAmount" min="0.01" step="0.01" required>
                </div>
                <div class="form-group full">
                    <label>Due / Repay Date</label>
                    <input type="date" name="due_date" id="editDebtDueDate">
                </div>
                <div class="form-group full">
                    <label>Note (optional)</label>
                    <textarea name="note" id="editDebtNote" style="min-height:60px"></textarea>
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


<!-- ══ RECORD REPAYMENT MODAL ══ -->
<div class="modal-backdrop" id="debtPayModal">
    <div class="modal">
        <div class="modal-title">
            <i class="fa-solid fa-money-bill-wave" style="color:var(--green)"></i> Record Repayment
        </div>
        <form method="POST" id="debtPayForm">
            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
            <div class="form-grid" style="gap:12px">
                <div class="form-group full">
                    <div id="debtPayLabel" style="font-weight:600;color:var(--brand);font-size:14px;padding-bottom:4px"></div>
                </div>
                <div class="form-group">
                    <label>Repayment Amount *</label>
                    <input type="number" name="amount" id="debtPayAmount" min="0.01" step="0.01" required placeholder="0.00">
                    <small class="form-hint" id="debtPayRemaining"></small>
                </div>
                <div class="form-group">
                    <label>Payment Method</label>
                    <select name="payment_method">
                        <option value="cash">Cash</option>
                        <option value="bkash">bKash</option>
                        <option value="nagad">Nagad</option>
                        <option value="rocket">Rocket</option>
                        <option value="bank_transfer">Bank Transfer</option>
                        <option value="card">Card</option>
                        <option value="cheque">Cheque</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div class="form-group full">
                    <label>Note (optional)</label>
                    <textarea name="note" style="min-height:50px" placeholder="Reference no., remarks…"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-close-modal>Cancel</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-check"></i> Save Repayment
                </button>
            </div>
        </form>
    </div>
</div>

<style>
.stat-sub{font-size:11px;color:var(--text-muted);margin-top:2px}
.badge-amber{background:#fff8e1;color:var(--amber,#f59e0b)}
.badge-gray{background:#f3f4f6;color:#6b7280}
</style>

<script>
function openDebtPay(id, title, remaining, sym) {
    document.getElementById('debtPayForm').action   = '/books/<?= $book['id'] ?>/debts/' + id + '/pay';
    document.getElementById('debtPayLabel').textContent = title;
    document.getElementById('debtPayAmount').value  = remaining.toFixed(2);
    document.getElementById('debtPayAmount').max    = remaining;
    document.getElementById('debtPayRemaining').textContent = 'Outstanding: ' + sym + remaining.toFixed(2);
    document.getElementById('debtPayModal').classList.add('open');
}

function openDebtEdit(id, title, party, amount, dueDate, note) {
    document.getElementById('editDebtForm').action       = '/books/<?= $book['id'] ?>/debts/' + id + '/edit';
    document.getElementById('editDebtTitle').value       = title;
    document.getElementById('editDebtParty').value       = party;
    document.getElementById('editDebtAmount').value      = amount;
    document.getElementById('editDebtDueDate').value     = dueDate;
    document.getElementById('editDebtNote').value        = note;
    document.getElementById('editDebtModal').classList.add('open');
}
</script>


<script>
(function(){
var allRows=[],filterF='all',searchQ='',sortKey='date-desc',perPage=20,curPage=1;
function init(){
    allRows=Array.from(document.querySelectorAll('#debtsTable tbody tr'));
    var si=document.getElementById('debtsTableSearch'),sc=document.getElementById('debtsTableClear');
    if(si){si.addEventListener('input',function(){searchQ=this.value.toLowerCase().trim();sc.classList.toggle('visible',searchQ.length>0);curPage=1;render();});sc.addEventListener('click',function(){si.value='';searchQ='';sc.classList.remove('visible');curPage=1;render();});}
    var ss=document.getElementById('debtsTableSort');if(ss)ss.addEventListener('change',function(){sortKey=this.value;curPage=1;render();});
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
    var tbody=document.querySelector('#debtsTable tbody'),colC=(document.querySelector('#debtsTable thead tr')||{}).children.length||6;
    while(tbody.firstChild)tbody.removeChild(tbody.firstChild);
    if(f.length===0){var nr=document.createElement('tr');nr.className='lm-no-results';var nd=document.createElement('td');nd.setAttribute('colspan',colC);nd.textContent='No records match.';nr.appendChild(nd);tbody.appendChild(nr);}
    else{var lastM=null;f.slice(s,e2).forEach(function(row){
        var d=parseD(row);
        if(d.getTime()>0){var mk=d.getFullYear()+'-'+d.getMonth();if(mk!==lastM){lastM=mk;var sep=document.createElement('tr');sep.className='month-sep';var std=document.createElement('td');std.setAttribute('colspan',colC);std.textContent=d.toLocaleDateString('en-GB',{month:'long',year:'numeric'});sep.appendChild(std);tbody.appendChild(sep);}}
        tbody.appendChild(row);
    });}
    renderPager(document.getElementById('debtsPager'),total,tpg,s,e2,pp);
}
function renderPager(el,total,tpg,s,e2,pp){if(!el)return;el.innerHTML='';var wrap=document.createElement('div');wrap.className='lm-pagination';var info=document.createElement('div');info.className='lm-page-info';info.textContent=total===0?'No results':pp===Infinity?'All '+total+' records':'Showing '+(s+1)+'–'+e2+' of '+total;wrap.appendChild(info);if(tpg>1){var pages=document.createElement('div');pages.className='lm-pages';function mkB(l,pg){var b=document.createElement('button');b.className='lm-page-btn';if(pg===curPage)b.classList.add('active');b.textContent=l;if(pg)b.addEventListener('click',function(){curPage=pg;render();});return b;}if(curPage>1)pages.appendChild(mkB('‹',curPage-1));var ns=[];if(tpg<=7){for(var i=1;i<=tpg;i++)ns.push(i);}else{ns=[1];if(curPage>3)ns.push('…');for(var i=Math.max(2,curPage-1);i<=Math.min(tpg-1,curPage+1);i++)ns.push(i);if(curPage<tpg-2)ns.push('…');ns.push(tpg);}ns.forEach(function(p){var b=mkB(p,p==='…'?0:p);if(p==='…')b.classList.add('lm-ellipsis');pages.appendChild(b);});if(curPage<tpg)pages.appendChild(mkB('›',curPage+1));wrap.appendChild(pages);}var ppW=document.createElement('div');ppW.className='lm-per-page-wrap';var sl=document.createElement('select');sl.className='lm-select';sl.style.padding='4px 8px';sl.style.margin='0 4px';[20,50,100,'all'].forEach(function(v){var o=document.createElement('option');o.value=v;o.textContent=v==='all'?'All':v;if((pp===Infinity&&v==='all')||pp===v)o.selected=true;sl.appendChild(o);});sl.addEventListener('change',function(){perPage=sl.value;curPage=1;render();});ppW.appendChild(document.createTextNode('Show '));ppW.appendChild(sl);ppW.appendChild(document.createTextNode(' per page'));wrap.appendChild(ppW);el.appendChild(wrap);}
document.addEventListener('DOMContentLoaded',function(){init();});
})();
</script>
<?php
$content = ob_get_clean();
ob_start();
$printCategory = 'debts';
require BASE_PATH . '/views/partials/print-modal.php';
$content .= ob_get_clean();
require BASE_PATH . '/views/partials/layout.php'; ?>
