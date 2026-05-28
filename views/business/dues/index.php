<?php
$pageTitle = 'Dues — ' . e($book['name']);
ob_start();
?>

<div class="page-header">
    <div class="page-header-left">
        <div class="breadcrumb">
            <a href="/books/<?= $book['id'] ?>">Dashboard</a> <span>›</span>
            <span>Dues</span>
        </div>
        <h1><i class="fa-solid fa-hand-holding-dollar" style="color:var(--brand)"></i> Dues</h1>
        <p>Money customers owe your business</p>
    </div>
    <button class="btn btn-secondary btn-sm" data-modal="printModal" title="Print PDF">
            <i class="fa-solid fa-print"></i> Print
        </button>
    <button class="btn btn-primary" data-modal="addDueModal">
        <i class="fa-solid fa-plus"></i> Add Due
    </button>
</div>

<!-- Summary -->
<div class="stat-grid" style="grid-template-columns:repeat(auto-fit,minmax(140px,1fr));max-width:720px;margin-bottom:22px">
    <div class="stat-card" style="border-left:4px solid var(--red)">
        <div class="stat-label"><i class="fa-solid fa-circle-exclamation" style="color:var(--red)"></i> Outstanding</div>
        <div class="stat-value red"><?= format_money((float)($summary['outstanding'] ?? 0), $symbol) ?></div>
        <div class="stat-sub"><?= (int)($summary['unpaid_count'] ?? 0) + (int)($summary['partial_count'] ?? 0) ?> unpaid</div>
    </div>
    <div class="stat-card" style="border-left:4px solid var(--green)">
        <div class="stat-label"><i class="fa-solid fa-money-bill-wave" style="color:var(--green)"></i> Collected</div>
        <div class="stat-value green"><?= format_money((float)($summary['total_collected'] ?? 0), $symbol) ?></div>
        <div class="stat-sub"><?= (int)($summary['paid_count'] ?? 0) ?> fully paid</div>
    </div>
    <div class="stat-card" style="border-left:4px solid var(--amber)">
        <div class="stat-label"><i class="fa-solid fa-hourglass-half" style="color:var(--amber)"></i> Partial</div>
        <div class="stat-value amber"><?= (int)($summary['partial_count'] ?? 0) ?></div>
        <div class="stat-sub">partially paid</div>
    </div>
    <div class="stat-card">
        <div class="stat-label"><i class="fa-solid fa-list"></i> Total Dues</div>
        <div class="stat-value brand"><?= (int)($summary['total_count'] ?? 0) ?></div>
        <div class="stat-sub">all records</div>
    </div>
</div>

<!-- Dues table -->
<!-- LM Controls -->
<div class="lm-controls">
    <div class="lm-search-wrap">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="text" class="lm-search" id="duesTableSearch" placeholder="Search party, invoice, note…">
        <button class="lm-search-clear" id="duesTableClear"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <select class="lm-select" id="duesTableSort">
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
        <div style="font-size:11px;color:var(--text-muted)"><?= count($dues) ?> dues</div>
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
<?php if (empty($dues)): ?>
<div class="table-wrap">
    <div class="empty-state">
        <div class="empty-icon"><i class="fa-solid fa-hand-holding-dollar"></i></div>
        <h3>No dues found</h3>
        <p><?= $search ? 'Try a different search.' : 'Dues are created automatically when a sale invoice is marked unpaid, or add one manually.' ?></p>
    </div>
</div>
<?php else: ?>
<div class="table-wrap">
    <table id="duesTable">
        <thead>
            <tr>
                <th>Customer</th>
                <th>Title / Invoice</th>
                <th>Amount</th>
                <th>Paid</th>
                <th>Remaining</th>
                <th>Due Date</th>
                <th>Status</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($dues as $due):
            $remaining = (float)$due['amount'] - (float)$due['paid_amount'];
            $sym       = $due['currency_symbol'] ?? $symbol;
            $pct       = $due['amount'] > 0 ? min(100, round($due['paid_amount'] / $due['amount'] * 100)) : 0;
            $statusMap = [
                'unpaid'    => ['badge-red',   'Unpaid'],
                'partial'   => ['badge-amber', 'Partial'],
                'paid'      => ['badge-green', 'Paid'],
                'cancelled' => ['badge-gray',  'Cancelled'],
            ];
            [$badgeClass, $badgeLabel] = $statusMap[$due['status']] ?? ['badge-gray', ucfirst($due['status'])];
        ?>
        <tr data-date="<?= $due['due_date'] ?? '' ?>" data-filter="<?= e($due['status']) ?>">
            <td>
                <div style="display:flex;align-items:center;gap:8px">
                    <?php if (!empty($due['customer_photo'])): ?>
                    <img src="<?= asset('uploads/'.$due['customer_photo']) ?>"
                         style="width:28px;height:28px;border-radius:50%;object-fit:cover;flex-shrink:0" alt="">
                    <?php else: ?>
                    <div style="width:28px;height:28px;border-radius:50%;background:var(--brand-light);color:var(--brand);display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;flex-shrink:0">
                        <?= mb_strtoupper(mb_substr($due['customer_name']??'C',0,1)) ?>
                    </div>
                    <?php endif; ?>
                    <div>
                        <div style="font-weight:600;font-size:13px"><?= e($due['customer_name'] ?? '—') ?></div>
                        <?php if (!empty($due['customer_phone'])): ?>
                        <div class="td-muted"><?= e($due['customer_phone']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </td>
            <td>
                <div style="font-weight:500"><?= e($due['title']) ?></div>
                <?php if (!empty($due['invoice_no']) && !empty($due['invoice_id'])): ?>
                <div class="td-muted">
                    <a href="/books/<?= $book['id'] ?>/invoices/<?= (int)$due['invoice_id'] ?>"
                       style="color:var(--brand);text-decoration:none;font-size:12px"
                       title="View Invoice">
                        <i class="fa-solid fa-file-invoice fa-xs"></i> <?= e($due['invoice_no']) ?>
                    </a>
                </div>
                <?php elseif (!empty($due['invoice_no'])): ?>
                <div class="td-muted"><i class="fa-solid fa-file-invoice fa-xs"></i> <?= e($due['invoice_no']) ?></div>
                <?php endif; ?>
                <?php if (!empty($due['note'])): ?>
                <div class="td-muted" style="font-size:11px;font-style:italic"><?= e($due['note']) ?></div>
                <?php endif; ?>
            </td>
            <td style="font-weight:600"><?= format_money((float)$due['amount'], $sym) ?></td>
            <td>
                <div style="color:var(--green);font-weight:600"><?= format_money((float)$due['paid_amount'], $sym) ?></div>
                <div style="height:3px;background:var(--border);border-radius:99px;width:70px;margin-top:3px">
                    <div style="height:100%;border-radius:99px;width:<?= $pct ?>%;background:<?= $due['status']==='paid'?'var(--green)':'var(--amber)' ?>"></div>
                </div>
            </td>
            <td>
                <?php if ($remaining > 0.001): ?>
                <span style="color:var(--red);font-weight:700"><?= format_money($remaining, $sym) ?></span>
                <?php else: ?>
                <span style="color:var(--green)"><i class="fa-solid fa-check"></i> Settled</span>
                <?php endif; ?>
            </td>
            <td class="td-muted">
                <?php if (!empty($due['due_date'])):
                    $dueDate = new DateTime($due['due_date']);
                    $today   = new DateTime('today');
                    $overdue = $dueDate < $today && !in_array($due['status'], ['paid','cancelled']);
                ?>
                <span <?= $overdue ? 'style="color:var(--red);font-weight:600"' : '' ?>>
                    <?= format_date($due['due_date']) ?>
                    <?php if ($overdue): ?>
                    <br><small><?= (int)$today->diff($dueDate)->days ?> days overdue</small>
                    <?php endif; ?>
                </span>
                <?php else: ?>—<?php endif; ?>
            </td>
            <td><span class="badge <?= $badgeClass ?>"><?= $badgeLabel ?></span></td>
            <td style="white-space:nowrap;text-align:right">
                <?php if (!in_array($due['status'], ['paid','cancelled'])): ?>
                <button class="btn btn-sm btn-secondary"
                        onclick="openPayModal(<?= $due['id'] ?>,'<?= e(addslashes($due['title'])) ?>',<?= $remaining ?>,'<?= e($sym) ?>')"
                        title="Record payment">
                    <i class="fa-solid fa-money-bill-wave" style="color:var(--green)"></i>
                </button>
                <button class="btn btn-sm btn-secondary" title="Edit"
                        onclick="openDueEdit(<?= $due['id'] ?>,'<?= e(addslashes($due['title'])) ?>',<?= (float)$due['amount'] ?>,'<?= $due['due_date'] ?? '' ?>','<?= e(addslashes($due['note'] ?? '')) ?>')">
                    <i class="fa-solid fa-pen"></i>
                </button>
                <form method="POST" action="/books/<?= $book['id'] ?>/dues/<?= $due['id'] ?>/cancel"
                      style="display:inline" onsubmit="return confirm('Cancel this due?')">
                    <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                    <button class="btn btn-sm btn-secondary" title="Cancel due">
                        <i class="fa-solid fa-ban" style="color:var(--amber)"></i>
                    </button>
                </form>
                <?php endif; ?>
                <form method="POST" action="/books/<?= $book['id'] ?>/dues/<?= $due['id'] ?>/delete"
                      style="display:inline" onsubmit="return confirm('Permanently delete this due record?')">
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
<div id="duesPager"></div>


<!-- ══ RECORD PAYMENT MODAL ══ -->
<div class="modal-backdrop" id="payModal">
    <div class="modal">
        <div class="modal-title"><i class="fa-solid fa-money-bill-wave" style="color:var(--green)"></i> Record Payment</div>
        <form id="payModalForm" method="POST">
            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
            <div class="form-grid" style="gap:12px">
                <div class="form-group full">
                    <label id="payModalLabel" style="font-weight:700;color:var(--brand)">Due title</label>
                </div>
                <div class="form-group">
                    <label>Amount *</label>
                    <input type="number" name="amount" id="payAmount" min="0.01" step="0.01" required placeholder="0.00">
                    <small class="form-hint" id="payRemaining"></small>
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
                    <textarea name="note" style="min-height:50px" placeholder="Any note…"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-close-modal>Cancel</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-check"></i> Save Payment
                </button>
            </div>
        </form>
    </div>
</div>


<!-- ══ EDIT DUE MODAL ══ -->
<div class="modal-backdrop" id="editDueModal">
    <div class="modal">
        <div class="modal-title"><i class="fa-solid fa-pen" style="color:var(--brand)"></i> Edit Due</div>
        <form id="editDueForm" method="POST">
            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
            <div class="form-grid" style="gap:12px">
                <div class="form-group full">
                    <label>Title *</label>
                    <input type="text" name="title" id="editDueTitle" required>
                </div>
                <div class="form-group">
                    <label>Amount *</label>
                    <input type="number" name="amount" id="editDueAmount" min="0.01" step="0.01" required>
                </div>
                <div class="form-group">
                    <label>Due Date</label>
                    <input type="date" name="due_date" id="editDueDueDate">
                </div>
                <div class="form-group full">
                    <label>Note (optional)</label>
                    <textarea name="note" id="editDueNote" style="min-height:50px"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-close-modal>Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> Save Changes</button>
            </div>
        </form>
    </div>
</div>


<!-- ══ ADD DUE MODAL ══ -->
<div class="modal-backdrop" id="addDueModal">
    <div class="modal">
        <div class="modal-title"><i class="fa-solid fa-hand-holding-dollar" style="color:var(--amber)"></i> Add Manual Due</div>
        <form method="POST" action="/books/<?= $book['id'] ?>/dues/add"
              onsubmit="
                if (!document.getElementById('dueCustomerId').value) {
                    document.getElementById('dueCustomerSearch').style.border='2px solid var(--red)';
                    document.getElementById('dueCustomerSearch').focus();
                    document.getElementById('dueCustomerHint').style.display='block';
                    return false;
                }
              ">
            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
            <div class="form-grid" style="gap:12px">
                <div class="form-group full" style="position:relative">
                    <label>Customer *</label>
                    <input type="text" id="dueCustomerSearch" autocomplete="off"
                           placeholder="Search by name or phone…"
                           oninput="searchDueCustomer(this.value); this.style.border=''; document.getElementById('dueCustomerHint').style.display='none';">
                    <input type="hidden" name="customer_id" id="dueCustomerId">
                    <div id="dueCustomerDropdown" class="autocomplete-dropdown" style="display:none"></div>
                    <div id="dueCustomerHint" style="display:none;color:var(--red);font-size:12px;margin-top:4px">⚠ Please search and <strong>click</strong> a customer from the list.</div>
                </div>
                <div class="form-group full">
                    <label>Title *</label>
                    <input type="text" name="title" required placeholder="e.g. Unpaid balance from last order">
                </div>
                <div class="form-group">
                    <label>Amount *</label>
                    <input type="number" name="amount" min="0.01" step="0.01" required placeholder="0.00">
                </div>
                <div class="form-group">
                    <label>Due Date</label>
                    <input type="date" name="due_date">
                </div>
                <div class="form-group full">
                    <label>Note (optional)</label>
                    <textarea name="note" style="min-height:50px" placeholder="Any details…"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-close-modal>Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Add Due</button>
            </div>
        </form>
    </div>
</div>

<style>
.autocomplete-dropdown{position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid var(--border);border-radius:var(--radius);box-shadow:0 6px 20px rgba(0,0,0,.1);max-height:180px;overflow-y:auto;z-index:500}
.autocomplete-item{padding:8px 12px;cursor:pointer;font-size:13px}
.autocomplete-item:hover{background:var(--bg)}
.autocomplete-item small{color:var(--text-muted);display:block;font-size:11px}
.stat-sub{font-size:11px;color:var(--text-muted);margin-top:2px}
.amber{color:var(--amber)!important}
.badge-amber{background:var(--amber-bg,#fff8e1);color:var(--amber,#f59e0b)}
.badge-gray{background:#f3f4f6;color:#6b7280}
</style>

<script>
// ── Pay modal ──────────────────────────────────────────────────────────────
function openPayModal(dueId, title, remaining, sym) {
    document.getElementById('payModalForm').action = '/books/<?= $book['id'] ?>/dues/' + dueId + '/pay';
    document.getElementById('payModalLabel').textContent = title;
    document.getElementById('payAmount').value = remaining.toFixed(2);
    document.getElementById('payAmount').max   = remaining;
    document.getElementById('payRemaining').textContent = 'Remaining: ' + sym + remaining.toFixed(2);
    document.getElementById('payModal').classList.add('open');
}

// ── Edit due modal ─────────────────────────────────────────────────────────
function openDueEdit(id, title, amount, dueDate, note) {
    document.getElementById('editDueForm').action = '/books/<?= $book['id'] ?>/dues/' + id + '/edit';
    document.getElementById('editDueTitle').value   = title;
    document.getElementById('editDueAmount').value  = amount;
    document.getElementById('editDueDueDate').value = dueDate;
    document.getElementById('editDueNote').value    = note;
    document.getElementById('editDueModal').classList.add('open');
}

// ── Customer autocomplete ──────────────────────────────────────────────────
<?php
$customersJson = json_encode(array_map(fn($c) => [
    'id'    => $c['id'],
    'name'  => $c['name'],
    'phone' => $c['phone'] ?? '',
], $customers), JSON_UNESCAPED_UNICODE);
?>
const DUE_CUSTOMERS = <?= $customersJson ?>;

function searchDueCustomer(q) {
    const dd = document.getElementById('dueCustomerDropdown');
    if (!q.trim()) { dd.style.display='none'; return; }
    const matches = DUE_CUSTOMERS.filter(c =>
        c.name.toLowerCase().includes(q.toLowerCase()) ||
        (c.phone && c.phone.includes(q))
    ).slice(0, 8);
    if (!matches.length) { dd.style.display='none'; return; }
    dd.innerHTML = matches.map(c =>
        `<div class="autocomplete-item" onclick="selectDueCustomer(${c.id},'${c.name.replace(/'/g,"\\'")}')">
            ${c.name}<small>${c.phone || ''}</small>
        </div>`
    ).join('');
    dd.style.display='block';
}

function selectDueCustomer(id, name) {
    document.getElementById('dueCustomerSearch').value = name;
    document.getElementById('dueCustomerId').value     = id;
    document.getElementById('dueCustomerDropdown').style.display = 'none';
}

document.addEventListener('click', function(e) {
    if (!e.target.closest('#dueCustomerSearch') && !e.target.closest('#dueCustomerDropdown')) {
        document.getElementById('dueCustomerDropdown').style.display = 'none';
    }
});
</script>


<script>
(function(){
var allRows=[],filterF='all',searchQ='',sortKey='date-desc',perPage=20,curPage=1;
function init(){
    allRows=Array.from(document.querySelectorAll('#duesTable tbody tr'));
    var si=document.getElementById('duesTableSearch'),sc=document.getElementById('duesTableClear');
    if(si){si.addEventListener('input',function(){searchQ=this.value.toLowerCase().trim();sc.classList.toggle('visible',searchQ.length>0);curPage=1;render();});sc.addEventListener('click',function(){si.value='';searchQ='';sc.classList.remove('visible');curPage=1;render();});}
    var ss=document.getElementById('duesTableSort');if(ss)ss.addEventListener('change',function(){sortKey=this.value;curPage=1;render();});
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
    var tbody=document.querySelector('#duesTable tbody'),colC=(document.querySelector('#duesTable thead tr')||{}).children.length||6;
    while(tbody.firstChild)tbody.removeChild(tbody.firstChild);
    if(f.length===0){var nr=document.createElement('tr');nr.className='lm-no-results';var nd=document.createElement('td');nd.setAttribute('colspan',colC);nd.textContent='No records match.';nr.appendChild(nd);tbody.appendChild(nr);}
    else{var lastM=null;f.slice(s,e2).forEach(function(row){
        var d=parseD(row);
        if(d.getTime()>0){var mk=d.getFullYear()+'-'+d.getMonth();if(mk!==lastM){lastM=mk;var sep=document.createElement('tr');sep.className='month-sep';var std=document.createElement('td');std.setAttribute('colspan',colC);std.textContent=d.toLocaleDateString('en-GB',{month:'long',year:'numeric'});sep.appendChild(std);tbody.appendChild(sep);}}
        tbody.appendChild(row);
    });}
    renderPager(document.getElementById('duesPager'),total,tpg,s,e2,pp);
}
function renderPager(el,total,tpg,s,e2,pp){if(!el)return;el.innerHTML='';var wrap=document.createElement('div');wrap.className='lm-pagination';var info=document.createElement('div');info.className='lm-page-info';info.textContent=total===0?'No results':pp===Infinity?'All '+total+' records':'Showing '+(s+1)+'–'+e2+' of '+total;wrap.appendChild(info);if(tpg>1){var pages=document.createElement('div');pages.className='lm-pages';function mkB(l,pg){var b=document.createElement('button');b.className='lm-page-btn';if(pg===curPage)b.classList.add('active');b.textContent=l;if(pg)b.addEventListener('click',function(){curPage=pg;render();});return b;}if(curPage>1)pages.appendChild(mkB('‹',curPage-1));var ns=[];if(tpg<=7){for(var i=1;i<=tpg;i++)ns.push(i);}else{ns=[1];if(curPage>3)ns.push('…');for(var i=Math.max(2,curPage-1);i<=Math.min(tpg-1,curPage+1);i++)ns.push(i);if(curPage<tpg-2)ns.push('…');ns.push(tpg);}ns.forEach(function(p){var b=mkB(p,p==='…'?0:p);if(p==='…')b.classList.add('lm-ellipsis');pages.appendChild(b);});if(curPage<tpg)pages.appendChild(mkB('›',curPage+1));wrap.appendChild(pages);}var ppW=document.createElement('div');ppW.className='lm-per-page-wrap';var sl=document.createElement('select');sl.className='lm-select';sl.style.padding='4px 8px';sl.style.margin='0 4px';[20,50,100,'all'].forEach(function(v){var o=document.createElement('option');o.value=v;o.textContent=v==='all'?'All':v;if((pp===Infinity&&v==='all')||pp===v)o.selected=true;sl.appendChild(o);});sl.addEventListener('change',function(){perPage=sl.value;curPage=1;render();});ppW.appendChild(document.createTextNode('Show '));ppW.appendChild(sl);ppW.appendChild(document.createTextNode(' per page'));wrap.appendChild(ppW);el.appendChild(wrap);}
document.addEventListener('DOMContentLoaded',function(){init();});
})();
</script>
<?php
$content = ob_get_clean();
ob_start();
$printCategory = 'dues';
require BASE_PATH . '/views/partials/print-modal.php';
$content .= ob_get_clean();
require BASE_PATH . '/views/partials/layout.php'; ?>
