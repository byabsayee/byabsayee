<?php
/**
 * views/partials/print-modal.php
 *
 * Required variables:
 *   $book          — current book array
 *   $printCategory — string key e.g. 'invoices', 'products', 'customers', …
 *
 * Trigger button:
 *   <button class="btn btn-secondary btn-sm" data-modal="printModal">
 *       <i class="fa-solid fa-print"></i> Print PDF
 *   </button>
 */

// Static (list) categories — no date-range filter needed
$_pmStaticCategories = ['products', 'customers', 'suppliers', 'employees', 'contacts', 'coupons', 'privileges'];
$_pmIsStatic = in_array($printCategory, $_pmStaticCategories);
?>
<div class="modal-backdrop" id="printModal">
  <div class="modal" style="max-width:400px">
    <div class="modal-title">
      <i class="fa-solid fa-print" style="color:var(--brand)"></i>
      Print / Export PDF
      <button type="button" class="modal-close" data-close-modal>✕</button>
    </div>
    <div style="padding:18px 20px">

      <div style="margin-bottom:14px">
        <label style="font-size:12px;font-weight:600;color:var(--text-muted);display:block;margin-bottom:6px">PRINT MODE</label>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">

          <?php if ($_pmIsStatic): ?>
            <!-- Static categories: only "Last N" and "All" -->
            <label class="pm-mode-card" data-mode="days">
              <input type="radio" name="pm_mode" value="days">
              <i class="fa-solid fa-hashtag"></i>
              <span class="pm-mode-label">Last N</span>
              <span class="pm-mode-sub">Items from last X days</span>
            </label>
            <label class="pm-mode-card selected" data-mode="all">
              <input type="radio" name="pm_mode" value="all" checked>
              <i class="fa-solid fa-layer-group"></i>
              <span class="pm-mode-label">All</span>
              <span class="pm-mode-sub">Every record</span>
            </label>

          <?php else: ?>
            <!-- Transactional categories: full date options + All -->
            <label class="pm-mode-card" data-mode="days">
              <input type="radio" name="pm_mode" value="days">
              <i class="fa-solid fa-hashtag"></i>
              <span class="pm-mode-label">Last N Days</span>
              <span class="pm-mode-sub">Sum over last X days</span>
            </label>
            <label class="pm-mode-card" data-mode="date">
              <input type="radio" name="pm_mode" value="date">
              <i class="fa-solid fa-calendar-day"></i>
              <span class="pm-mode-label">Single Day</span>
              <span class="pm-mode-sub">All entries on one day</span>
            </label>
            <label class="pm-mode-card selected" data-mode="month">
              <input type="radio" name="pm_mode" value="month" checked>
              <i class="fa-solid fa-calendar"></i>
              <span class="pm-mode-label">By Month</span>
              <span class="pm-mode-sub">Daily totals for a month</span>
            </label>
            <label class="pm-mode-card" data-mode="year">
              <input type="radio" name="pm_mode" value="year">
              <i class="fa-solid fa-calendar-check"></i>
              <span class="pm-mode-label">By Year</span>
              <span class="pm-mode-sub">Monthly totals for a year</span>
            </label>
            <label class="pm-mode-card" data-mode="all" style="grid-column:span 2">
              <input type="radio" name="pm_mode" value="all">
              <i class="fa-solid fa-layer-group"></i>
              <span class="pm-mode-label">All Time</span>
              <span class="pm-mode-sub">Every record ever, no date filter</span>
            </label>
          <?php endif; ?>

        </div>
      </div>

      <!-- Dynamic input section — hidden for 'all' mode -->
      <div id="pm_input_days" class="pm-input-section" style="display:none">
        <label style="font-size:12px;font-weight:600;color:var(--text-muted);display:block;margin-bottom:6px">NUMBER OF DAYS</label>
        <input type="number" id="pm_days_val" class="form-control" value="30" min="1" max="365"
               style="width:100%;padding:9px 12px;border:1.5px solid var(--border);border-radius:var(--radius-sm);font-size:14px">
        <div style="font-size:11px;color:var(--text-muted);margin-top:4px">e.g. 7 = last 7 days, 30 = last month</div>
      </div>

      <?php if (!$_pmIsStatic): ?>
      <div id="pm_input_date" class="pm-input-section" style="display:none">
        <label style="font-size:12px;font-weight:600;color:var(--text-muted);display:block;margin-bottom:6px">SELECT DATE</label>
        <input type="date" id="pm_date_val" class="form-control"
               value="<?= date('Y-m-d') ?>"
               style="width:100%;padding:9px 12px;border:1.5px solid var(--border);border-radius:var(--radius-sm);font-size:14px">
      </div>

      <div id="pm_input_month" class="pm-input-section">
        <label style="font-size:12px;font-weight:600;color:var(--text-muted);display:block;margin-bottom:6px">SELECT MONTH</label>
        <input type="month" id="pm_month_val" class="form-control"
               value="<?= date('Y-m') ?>"
               style="width:100%;padding:9px 12px;border:1.5px solid var(--border);border-radius:var(--radius-sm);font-size:14px">
      </div>

      <div id="pm_input_year" class="pm-input-section" style="display:none">
        <label style="font-size:12px;font-weight:600;color:var(--text-muted);display:block;margin-bottom:6px">SELECT YEAR</label>
        <select id="pm_year_val" class="form-control"
                style="width:100%;padding:9px 12px;border:1.5px solid var(--border);border-radius:var(--radius-sm);font-size:14px">
          <?php for ($y = date('Y'); $y >= max(date('Y')-5, 2020); $y--): ?>
          <option value="<?= $y ?>" <?= $y == date('Y') ? 'selected' : '' ?>><?= $y ?></option>
          <?php endfor; ?>
        </select>
      </div>
      <?php endif; ?>

      <div style="margin-top:18px;display:flex;gap:8px">
        <button type="button" class="btn btn-secondary" data-close-modal style="flex:1">Cancel</button>
        <button type="button" class="btn btn-primary" id="pm_generate_btn" style="flex:2">
          <i class="fa-solid fa-file-pdf"></i> Generate PDF
        </button>
      </div>
    </div>
  </div>
</div>

<style>
.pm-mode-card {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 2px;
  padding: 10px 12px;
  border: 1.5px solid var(--border);
  border-radius: var(--radius-sm);
  cursor: pointer;
  transition: border-color .15s, background .15s;
  background: var(--white);
}
.pm-mode-card:hover { border-color: var(--brand); background: var(--brand-light); }
.pm-mode-card input[type=radio] { display: none; }
.pm-mode-card.selected {
  border-color: var(--brand);
  background: var(--brand-light);
}
.pm-mode-card i { font-size: 16px; color: var(--brand); margin-bottom: 2px; }
.pm-mode-label { font-size: 13px; font-weight: 600; color: var(--text); }
.pm-mode-sub   { font-size: 11px; color: var(--text-muted); }
</style>

<script>
(function(){
  var category   = <?= json_encode($printCategory) ?>;
  var bookId     = <?= json_encode((string)$book['id']) ?>;
  var isStatic   = <?= json_encode($_pmIsStatic) ?>;
  var baseUrl    = '/books/' + bookId + '/print/' + category;
  var defaultMode = isStatic ? 'all' : 'month';

  var modeCards  = document.querySelectorAll('.pm-mode-card');
  var inputIds   = { days:'pm_input_days', date:'pm_input_date', month:'pm_input_month', year:'pm_input_year' };
  var currentMode = defaultMode;

  function showMode(mode) {
    currentMode = mode;
    modeCards.forEach(function(c){ c.classList.toggle('selected', c.dataset.mode === mode); });
    Object.keys(inputIds).forEach(function(k){
      var el = document.getElementById(inputIds[k]);
      if (el) el.style.display = (k === mode) ? '' : 'none';
    });
  }

  modeCards.forEach(function(card){
    card.addEventListener('click', function(){ showMode(card.dataset.mode); });
  });
  showMode(defaultMode);

  document.getElementById('pm_generate_btn').addEventListener('click', function(){
    var val = 'all';
    if (currentMode === 'days')  val = (document.getElementById('pm_days_val')  || {}).value || '30';
    if (currentMode === 'date')  val = (document.getElementById('pm_date_val')  || {}).value || '<?= date('Y-m-d') ?>';
    if (currentMode === 'month') val = (document.getElementById('pm_month_val') || {}).value || '<?= date('Y-m') ?>';
    if (currentMode === 'year')  val = (document.getElementById('pm_year_val')  || {}).value || '<?= date('Y') ?>';

    var url = baseUrl + '?mode=' + currentMode + '&value=' + encodeURIComponent(val);
    window.open(url, '_blank');
    document.getElementById('printModal').classList.remove('open');
  });
})();
</script>
