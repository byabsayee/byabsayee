// app.js — Byabsayee client-side helpers

/* ============================================================
   TIMEZONE DETECTION
   Detects browser timezone, stores in cookie so PHP can use it.
   Fixes the 6-hour offset issue (server UTC vs local time).
   ============================================================ */
(function() {
    try {
        var tz = Intl.DateTimeFormat().resolvedOptions().timeZone;
        if (tz) {
            document.cookie = 'byabsayee_tz=' + encodeURIComponent(tz) + '; path=/; max-age=31536000; SameSite=Lax';
        }
    } catch(e) {}
})();

/* ============================================================
   DOM READY — modals, flash, confirms
   ============================================================ */
document.addEventListener('DOMContentLoaded', function () {

    // Delete confirmation
    document.querySelectorAll('[data-confirm]').forEach(function (el) {
        el.addEventListener('click', function (e) {
            if (!confirm(el.dataset.confirm || 'Are you sure?')) {
                e.preventDefault();
            }
        });
    });

    // Auto-dismiss flash messages after 4 seconds
    document.querySelectorAll('.flash').forEach(function (el) {
        setTimeout(function () {
            el.style.transition = 'opacity 0.4s';
            el.style.opacity = '0';
            setTimeout(function () { el.remove(); }, 400);
        }, 4000);
    });

    // Modal open/close
    document.querySelectorAll('[data-modal]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var target = document.getElementById(btn.dataset.modal);
            if (target) target.classList.add('open');
        });
    });

    document.querySelectorAll('[data-close-modal]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            btn.closest('.modal-backdrop').classList.remove('open');
        });
    });

    // Close modal when clicking the backdrop
    document.querySelectorAll('.modal-backdrop').forEach(function (backdrop) {
        backdrop.addEventListener('click', function (e) {
            if (e.target === backdrop) backdrop.classList.remove('open');
        });
    });

    // Perm-check pill toggle (employee permissions)
    document.querySelectorAll('.perm-check').forEach(function(pill) {
        var cb = pill.querySelector('input[type="checkbox"]');
        if (!cb) return;
        function sync() { pill.classList.toggle('checked', cb.checked); }
        sync();
        cb.addEventListener('change', sync);
        pill.addEventListener('click', function(e) {
            if (e.target !== cb) { cb.checked = !cb.checked; cb.dispatchEvent(new Event('change')); }
        });
    });
});

/* ============================================================
   NOTIFICATION BADGE
   ============================================================ */
(function() {
    function updateNotifBadge() {
        fetch('/notifications/count')
            .then(function(r){ return r.ok ? r.json() : {count:0}; })
            .then(function(data) {
                var count = data.count || 0;
                ['mobileNotifBadge','sidebarNotifBadge'].forEach(function(id) {
                    var el = document.getElementById(id);
                    if (!el) return;
                    if (count > 0) { el.textContent = count > 99 ? '99+' : count; el.style.display = 'flex'; }
                    else { el.style.display = 'none'; }
                });
            }).catch(function(){});
    }
    updateNotifBadge();
    setInterval(updateNotifBadge, 60000);
})();

/* ============================================================
   LIST MANAGER
   Universal client-side search / sort / filter / paginate.

   Usage from any view:
     ListManager.init('tableId', { dateCol: 3, searchCols: [0,1,2] });

   Hook up controls:
     <input oninput="ListManager.setSearch('tableId', this.value)">
     <select onchange="ListManager.setFilter('tableId', this.value)">
   ============================================================ */
var ListManager = (function() {

    var instances = {};

    function init(tableId, opts) {
        var table = document.getElementById(tableId);
        if (!table) return;

        opts = Object.assign({
            perPage: 20,
            dateCol: null,          // column index (0-based) with a date for month separators
            searchCols: null,       // null = all columns
            sortable: true,
            monthSep: true,
            filterAttr: 'data-filter',
        }, opts || {});

        var tbody = table.querySelector('tbody');
        if (!tbody) return;

        // Snapshot original rows, ignoring any separator rows
        var allRows = Array.from(tbody.querySelectorAll('tr:not(.month-sep):not(.lm-no-results)'));

        var state = {
            search: '',
            filter: '__all__',
            sortCol: null,
            sortDir: 'asc',
            page: 1,
            perPage: opts.perPage,
        };

        var paginationEl = document.getElementById(tableId + '-pagination');
        if (!paginationEl) {
            paginationEl = document.createElement('div');
            paginationEl.id = tableId + '-pagination';
            table.parentNode.insertBefore(paginationEl, table.nextSibling);
        }

        instances[tableId] = {
            state: state, opts: opts, table: table,
            tbody: tbody, allRows: allRows, paginationEl: paginationEl
        };

        // Set up sortable headers
        if (opts.sortable) {
            var ths = table.querySelectorAll('thead th[data-sort]');
            ths.forEach(function(th) {
                var span = document.createElement('span');
                span.className = 'sort-icon';
                th.appendChild(span);
                th.style.cursor = 'pointer';
                th.addEventListener('click', function() {
                    var col = parseInt(th.getAttribute('data-sort'));
                    if (state.sortCol === col) {
                        state.sortDir = state.sortDir === 'asc' ? 'desc' : 'asc';
                    } else {
                        state.sortCol = col;
                        state.sortDir = 'asc';
                    }
                    ths.forEach(function(t) { t.classList.remove('sort-asc','sort-desc'); });
                    th.classList.add(state.sortDir === 'asc' ? 'sort-asc' : 'sort-desc');
                    state.page = 1;
                    renderInstance(tableId);
                });
            });
        }

        renderInstance(tableId);
    }

    function setSearch(tableId, query) {
        var inst = instances[tableId]; if (!inst) return;
        inst.state.search = (query || '').toLowerCase().trim();
        inst.state.page = 1;
        renderInstance(tableId);
    }

    function setFilter(tableId, value) {
        var inst = instances[tableId]; if (!inst) return;
        inst.state.filter = value || '__all__';
        inst.state.page = 1;
        renderInstance(tableId);
    }

    function setPerPage(tableId, n) {
        var inst = instances[tableId]; if (!inst) return;
        inst.state.perPage = (n === 'all' || n === '') ? Infinity : (parseInt(n) || 20);
        inst.state.page = 1;
        renderInstance(tableId);
    }

    function setPage(tableId, p) {
        var inst = instances[tableId]; if (!inst) return;
        inst.state.page = p;
        renderInstance(tableId);
    }

    function renderInstance(tableId) {
        var inst = instances[tableId]; if (!inst) return;
        var state = inst.state, opts = inst.opts;
        var tbody = inst.tbody, allRows = inst.allRows;

        // 1. FILTER
        var filtered = allRows.filter(function(row) {
            if (state.filter !== '__all__') {
                var fa = (row.getAttribute(opts.filterAttr) || '').split(',').map(function(s){ return s.trim(); });
                if (fa.indexOf(state.filter) === -1) return false;
            }
            if (state.search) {
                var cells = Array.from(row.querySelectorAll('td'));
                var searchIn = opts.searchCols ? opts.searchCols.map(function(i){ return cells[i]; }).filter(Boolean) : cells;
                var txt = searchIn.map(function(c){ return c.textContent; }).join(' ').toLowerCase();
                if (txt.indexOf(state.search) === -1) return false;
            }
            return true;
        });

        // 2. SORT
        if (state.sortCol !== null) {
            filtered.sort(function(a, b) {
                var ca = getText(a, state.sortCol);
                var cb = getText(b, state.sortCol);
                var na = parseFloat(ca.replace(/[৳$€£,\s]/g, '').replace(/[^0-9.-]/g,''));
                var nb = parseFloat(cb.replace(/[৳$€£,\s]/g, '').replace(/[^0-9.-]/g,''));
                var cmp = 0;
                if (!isNaN(na) && !isNaN(nb)) {
                    cmp = na - nb;
                } else {
                    var da = parseDate(ca), db = parseDate(cb);
                    if (da && db) { cmp = da - db; }
                    else { cmp = ca.localeCompare(cb, undefined, {sensitivity:'base', numeric:true}); }
                }
                return state.sortDir === 'asc' ? cmp : -cmp;
            });
        }

        // 3. PAGINATE
        var total = filtered.length;
        var perPage = state.perPage;
        var totalPages = (perPage === Infinity) ? 1 : Math.max(1, Math.ceil(total / perPage));
        if (state.page > totalPages) state.page = totalPages;
        if (state.page < 1) state.page = 1;
        var start = (perPage === Infinity) ? 0 : (state.page - 1) * perPage;
        var end   = (perPage === Infinity) ? total : Math.min(start + perPage, total);
        var pageRows = filtered.slice(start, end);

        // 4. RENDER ROWS
        while (tbody.firstChild) tbody.removeChild(tbody.firstChild);
        var colCount = (inst.table.querySelector('thead tr') || {children:{length:5}}).children.length;

        if (pageRows.length === 0) {
            var noRow = document.createElement('tr');
            noRow.className = 'lm-no-results';
            var noTd = document.createElement('td');
            noTd.setAttribute('colspan', colCount);
            noTd.innerHTML = '<i class="fa-solid fa-search" style="display:block;font-size:28px;opacity:.2;margin-bottom:8px"></i>'
                + (state.search ? 'No results for &ldquo;' + escHtml(state.search) + '&rdquo;' : 'No records found.');
            noRow.appendChild(noTd);
            tbody.appendChild(noRow);
        } else {
            var lastMonthKey = null;
            pageRows.forEach(function(row) {
                // Month separator
                if (opts.monthSep && opts.dateCol !== null) {
                    var dc = row.querySelectorAll('td')[opts.dateCol];
                    var rawDate = dc ? (dc.getAttribute('data-date') || dc.textContent.trim()) : '';
                    var d = parseDate(rawDate);
                    if (d) {
                        var mk = d.getFullYear() + '-' + d.getMonth();
                        if (mk !== lastMonthKey) {
                            lastMonthKey = mk;
                            var sep = document.createElement('tr');
                            sep.className = 'month-sep';
                            var std = document.createElement('td');
                            std.setAttribute('colspan', colCount);
                            std.textContent = d.toLocaleDateString('en-GB', {month:'long', year:'numeric'});
                            sep.appendChild(std);
                            tbody.appendChild(sep);
                        }
                    }
                }
                tbody.appendChild(row);
            });
        }

        // 5. RENDER PAGINATION
        renderPagination(inst, total, totalPages, start, end, perPage);
    }

    function renderPagination(inst, total, totalPages, start, end, perPage) {
        var el = inst.paginationEl; if (!el) return;
        el.innerHTML = '';
        var tableId = inst.table.id;

        var wrap = document.createElement('div');
        wrap.className = 'lm-pagination';

        // Info
        var info = document.createElement('div');
        info.className = 'lm-page-info';
        info.textContent = total === 0 ? 'No results'
            : perPage === Infinity ? 'Showing all ' + total + ' record' + (total!==1?'s':'')
            : 'Showing ' + (start+1) + '–' + end + ' of ' + total;
        wrap.appendChild(info);

        // Page buttons
        if (totalPages > 1) {
            var pages = document.createElement('div');
            pages.className = 'lm-pages';

            var prev = mkBtn('‹', function(){ setPage(tableId, inst.state.page-1); });
            if (inst.state.page <= 1) prev.disabled = true;
            pages.appendChild(prev);

            getPageNums(inst.state.page, totalPages).forEach(function(p) {
                var btn = mkBtn(p === '…' ? '…' : p, p === '…' ? null : function(pg){ return function(){ setPage(tableId, pg); }; }(p));
                if (p === '…') btn.classList.add('lm-ellipsis');
                if (p === inst.state.page) btn.classList.add('active');
                pages.appendChild(btn);
            });

            var nxt = mkBtn('›', function(){ setPage(tableId, inst.state.page+1); });
            if (inst.state.page >= totalPages) nxt.disabled = true;
            pages.appendChild(nxt);
            wrap.appendChild(pages);
        }

        // Per-page selector
        var ppWrap = document.createElement('div');
        ppWrap.className = 'lm-per-page-wrap';
        var lbl = document.createElement('span'); lbl.textContent = 'Show ';
        var sel = document.createElement('select');
        sel.className = 'lm-select';
        sel.style.padding = '4px 8px';
        sel.style.marginLeft = '4px';
        sel.style.marginRight = '4px';
        [20,50,100,'all'].forEach(function(v) {
            var opt = document.createElement('option');
            opt.value = v; opt.textContent = v === 'all' ? 'All' : v;
            if ((perPage === Infinity && v === 'all') || perPage === v) opt.selected = true;
            sel.appendChild(opt);
        });
        sel.addEventListener('change', function() { setPerPage(tableId, sel.value); });
        var lbl2 = document.createElement('span'); lbl2.textContent = ' per page';
        ppWrap.appendChild(lbl); ppWrap.appendChild(sel); ppWrap.appendChild(lbl2);
        wrap.appendChild(ppWrap);

        el.appendChild(wrap);
    }

    function mkBtn(label, onClick) {
        var btn = document.createElement('button');
        btn.className = 'lm-page-btn';
        btn.textContent = label;
        if (onClick) btn.addEventListener('click', onClick);
        return btn;
    }

    function getPageNums(cur, total) {
        if (total <= 7) { var a=[]; for(var i=1;i<=total;i++) a.push(i); return a; }
        var p = [1];
        if (cur > 3) p.push('…');
        for (var i = Math.max(2,cur-1); i <= Math.min(total-1,cur+1); i++) p.push(i);
        if (cur < total-2) p.push('…');
        p.push(total);
        return p;
    }

    function getText(row, col) {
        var c = row.querySelectorAll('td')[col];
        return c ? c.textContent.trim() : '';
    }

    function parseDate(s) {
        if (!s) return null;
        // dd Mon YYYY (e.g. 15 May 2025)
        var m = s.match(/(\d{1,2})\s+([A-Za-z]+)\s+(\d{4})/);
        if (m) { var d = new Date(m[3]+'-'+monthNum(m[2])+'-'+m[1].padStart(2,'0')); return isNaN(d)?null:d; }
        // YYYY-MM-DD
        var d2 = new Date(s);
        return isNaN(d2) ? null : d2;
    }

    function monthNum(name) {
        var months = {jan:'01',feb:'02',mar:'03',apr:'04',may:'05',jun:'06',
                      jul:'07',aug:'08',sep:'09',oct:'10',nov:'11',dec:'12'};
        return months[(name||'').toLowerCase().slice(0,3)] || '01';
    }

    function escHtml(s) {
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    return { init:init, setSearch:setSearch, setFilter:setFilter, setPerPage:setPerPage, setPage:setPage };

})();

/* ============================================================
   COPY LINK HELPER (invoices)
   ============================================================ */
function copyLink(url) {
    if (navigator.clipboard) {
        navigator.clipboard.writeText(url).then(function() { showToast('Link copied!'); }).catch(function(){ _fallbackCopy(url); });
    } else { _fallbackCopy(url); }
}
function _fallbackCopy(url) {
    var el = document.createElement('textarea');
    el.value = url; el.style.cssText = 'position:fixed;opacity:0';
    document.body.appendChild(el); el.select();
    try { document.execCommand('copy'); showToast('Link copied!'); } catch(e){}
    document.body.removeChild(el);
}
function showToast(msg) {
    var t = document.createElement('div');
    t.textContent = msg;
    t.style.cssText = 'position:fixed;bottom:24px;left:50%;transform:translateX(-50%);background:#1a202c;color:#fff;padding:8px 20px;border-radius:99px;font-size:13px;z-index:9999;box-shadow:0 4px 16px rgba(0,0,0,.25);pointer-events:none';
    document.body.appendChild(t);
    setTimeout(function(){ t.style.transition='opacity .3s'; t.style.opacity='0'; setTimeout(function(){ t.remove(); }, 350); }, 2000);
}


/* Month navigator for a list page.
   Call: MonthNav.init('myTableId', 'myNavId', dateAttr='data-date')
   Rows must have data-date="YYYY-MM-DD"
*/
(function(global){
var MonthNav = {
    instances: {},
    init: function(tableId, navId, dateAttr) {
        dateAttr = dateAttr || 'data-date';
        var rows = Array.from(document.querySelectorAll('#'+tableId+' tbody tr'));
        var months = {};
        rows.forEach(function(r){
            var v = r.getAttribute(dateAttr);
            if(!v) return;
            var d = new Date(v);
            if(isNaN(d)) return;
            var k = d.getFullYear()+'-'+(d.getMonth()<9?'0':'')+(d.getMonth()+1);
            if(!months[k]) months[k]=[];
            months[k].push(r);
        });
        var keys = Object.keys(months).sort().reverse();  // newest first
        if(!keys.length) return;
        var cur = 0;
        this.instances[tableId] = {rows:rows, months:months, keys:keys, cur:cur, navId:navId, tableId:tableId};
        this._render(tableId);
    },
    _render: function(tableId) {
        var inst = this.instances[tableId]; if(!inst) return;
        var keys = inst.keys, cur = inst.cur, months = inst.months;
        var nav = document.getElementById(inst.navId); 
        var key = keys[cur];
        var d = new Date(key+'-01');
        var label = d.toLocaleDateString('en-GB',{month:'long',year:'numeric'});
        if(nav) {
            nav.querySelector('.mn-label').textContent = label;
            nav.querySelector('.mn-prev').disabled = cur >= keys.length-1;
            nav.querySelector('.mn-next').disabled = cur <= 0;
            nav.querySelector('.mn-count').textContent = (months[key]||[]).length + ' record'+(months[key].length!==1?'s':'');
        }
        // show/hide rows
        inst.rows.forEach(function(r){ r.style.display='none'; });
        (months[key]||[]).forEach(function(r){ r.style.display=''; });
    },
    prev: function(tableId) {
        var inst = this.instances[tableId]; if(!inst) return;
        if(inst.cur < inst.keys.length-1) { inst.cur++; this._render(tableId); }
    },
    next: function(tableId) {
        var inst = this.instances[tableId]; if(!inst) return;
        if(inst.cur > 0) { inst.cur--; this._render(tableId); }
    }
};
global.MonthNav = MonthNav;
})(window);

// ─── Browser timezone detection ───────────────────────────────────────────────
// Runs on every page load. Detects the browser's IANA timezone and stores it
// in a cookie so PHP can call date_default_timezone_set() for ALL date() calls.
(function () {
    try {
        var tz = Intl.DateTimeFormat().resolvedOptions().timeZone;
        if (!tz) return;
        var exp = new Date(Date.now() + 365 * 24 * 60 * 60 * 1000).toUTCString();
        document.cookie = 'byabsayee_tz=' + encodeURIComponent(tz)
            + '; expires=' + exp + '; path=/; SameSite=Lax';
    } catch (e) {}
})();
