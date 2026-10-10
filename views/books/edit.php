<?php
$pageTitle = 'Settings — ' . e($book['name']);
$fonts = ['DejaVu Sans','DejaVu Serif','DejaVu Sans Mono'];
$isPersonal = $book['type'] !== 'business';
ob_start();
?>
<style>
/* page-specific widgets (shell, forms, uploads, colour picker come from settings.css) */
.inv-prev{border-radius:10px;overflow:hidden;border:1px solid var(--border);margin-top:10px}
.inv-prev-head{padding:10px 14px;display:flex;justify-content:space-between;align-items:center}
.inv-prev-head span{color:#fff;font-size:11px;font-weight:700}
.inv-prev-body{padding:10px 14px;background:var(--bg);display:flex;gap:8px}
.inv-prev-row{flex:1;background:var(--card-bg);border-radius:6px;padding:6px 8px;border:1px solid var(--border)}
.inv-prev-row .t{font-size:9px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.3px}
.inv-prev-row .v{font-size:12px;font-weight:700;color:var(--text)}
.imc-wrap{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.imc{border:2px solid var(--border);border-radius:10px;padding:18px 16px;cursor:pointer;transition:all .2s;position:relative;background:var(--card-bg);display:block}
.imc:hover,.imc.sel{border-color:var(--brand);background:var(--brand-light)}
.imc input[type=radio]{position:absolute;opacity:0}
.imc-badge{display:inline-flex;align-items:center;justify-content:center;width:40px;height:40px;border-radius:10px;font-size:20px;margin-bottom:10px}
.imc h4{font-size:16px;font-weight:700;margin-bottom:3px;color:var(--text)}
.imc .imc-sub{font-size:12px;color:var(--text-muted);line-height:1.5}
.imc .imc-ck{position:absolute;top:12px;right:12px;width:20px;height:20px;border-radius:50%;background:var(--brand);display:flex;align-items:center;justify-content:center;color:#fff;font-size:10px;opacity:0;transition:opacity .2s}
.imc.sel .imc-ck{opacity:1}
.cur-row{display:flex;gap:8px;align-items:center;padding:10px 12px;background:var(--bg);border-radius:8px;border:1px solid var(--border);margin-bottom:8px;transition:border-color .15s}
.cur-row:hover{border-color:var(--border-dark)}
.cur-row input[type=text]{padding:6px 9px;border:1px solid var(--border);border-radius:6px;font-size:13px;font-family:inherit;outline:none;background:var(--input-bg,var(--card-bg));color:var(--text);transition:border-color .15s;box-sizing:border-box}
.cur-row input:focus{border-color:var(--brand)}
.def-radio{display:flex;align-items:center;gap:5px;font-size:11px;font-weight:600;color:var(--text-muted);cursor:pointer;white-space:nowrap;padding:5px 8px;border-radius:8px;border:1px solid var(--border);transition:all .15s}
.def-radio:has(input:checked){background:var(--green-bg);color:var(--green);border-color:var(--green)}
.def-radio input{accent-color:var(--green)}
.del-btn{width:28px;height:28px;border:none;background:none;cursor:pointer;color:var(--text-muted);font-size:18px;line-height:1;border-radius:6px;transition:all .15s;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.del-btn:hover{background:var(--red-bg);color:var(--red)}
.mrow{display:flex;gap:8px;align-items:center;margin-bottom:8px}
.mrow input{flex:1;min-width:0;padding:9px 12px;border:1px solid var(--border);border-radius:8px;font-size:14px;font-family:inherit;outline:none;background:var(--input-bg,var(--bg));color:var(--text);transition:border-color .15s;box-sizing:border-box}
.mrow input:focus{border-color:var(--brand)}
.pfx-prev{display:inline-flex;align-items:center;border-radius:8px;overflow:hidden;border:1px solid var(--border);font-size:12px;font-weight:700;font-family:'Courier New',monospace;margin-top:6px}
.pfx-prev .pp{background:var(--brand);color:#fff;padding:4px 8px}
.pfx-prev .pn{background:var(--bg);color:var(--text-muted);padding:4px 8px}
@media(max-width:720px){.imc-wrap{grid-template-columns:1fr}.cur-row{flex-wrap:wrap}}
</style>

<div class="page-header">
    <div class="page-header-left">
        <div class="breadcrumb">
            <a href="/books/<?= $book['id'] ?>">Dashboard</a> <span>›</span>
            <span>Book Settings</span>
        </div>
        <h1><i class="fa-solid fa-gear" style="color:var(--brand)"></i> Book Settings</h1>
        <p>Name, invoice look and numbering, currencies and methods for <?= e($book['name']) ?></p>
    </div>
</div>

<form action="/books/<?= $book['id'] ?>/edit" method="POST" enctype="multipart/form-data" id="sForm">
<input type="hidden" name="_csrf" value="<?= csrf_token() ?>">

<div class="st-wrap">

<!-- ══ NAV ══ -->
<nav class="st-nav">
  <div class="st-nav-head">
    <div class="st-avatar" id="navAvatar" style="background:<?= e($book['color']??'#1a6b4a') ?>">
      <?= mb_strtoupper(mb_substr($book['name'],0,1)) ?>
    </div>
    <div>
      <div class="st-nav-name"><?= e($book['name']) ?></div>
      <div class="st-nav-sub"><?= $book['type']==='business'?'Business':'Personal' ?></div>
    </div>
  </div>
      <button type="button" class="st-tab active" onclick="sw('general',this)"><i class="fa-solid fa-sliders"></i> General</button>
    <?php if(!$isPersonal): ?>
    <button type="button" class="st-tab" onclick="sw('business',this)"><i class="fa-solid fa-building"></i> Business</button>
    <button type="button" class="st-tab" onclick="sw('invoice',this)"><i class="fa-solid fa-file-invoice"></i> Invoice</button>
    <div class="st-sep"></div>
    <button type="button" class="st-tab" onclick="sw('currencies',this)">
      <i class="fa-solid fa-coins"></i> Currencies
      <?php if(!empty($currencies)): ?><span class="st-badge"><?= count($currencies) ?></span><?php endif; ?>
    </button>
    <button type="button" class="st-tab" onclick="sw('methods',this)"><i class="fa-solid fa-truck-fast"></i> Methods</button>
    <div class="st-sep"></div>
    <a href="/books/<?= $book['id'] ?>/business-profile" class="st-tab"><i class="fa-solid fa-id-badge"></i> Public Profile</a>
    <div class="st-sep"></div>
    <?php endif; ?>
    <button type="button" class="st-tab danger" onclick="sw('danger',this)"><i class="fa-solid fa-triangle-exclamation"></i> Danger Zone</button>
  <button type="submit" class="st-save" id="saveBtn"><i class="fa-solid fa-check"></i> Save Changes</button>
</nav>

<!-- ══ CONTENT ══ -->
<div class="st-body">

<!-- GENERAL -->
<div class="st-sec active" id="sec-general">
  <div class="st-panel">
    <div class="st-head">
      <div class="st-icon"><i class="fa-solid fa-book"></i></div>
      <div><h2>Book Identity</h2><p class="panel-desc">Name and colour shown on your dashboard</p></div>
    </div>
    <div class="st-panel-body">
      <div class="fg">
        <label>Book Name</label>
        <input type="text" name="name" value="<?= e($book['name']) ?>" required
               oninput="document.querySelector('.st-nav-name').textContent=this.value||'…';document.getElementById('navAvatar').textContent=(this.value||'B')[0].toUpperCase();document.getElementById('bCardName').textContent=this.value||'Book';markDirty()">
      </div>
      <div class="fg">
        <label>Card Colour</label>
        <div class="color-pick-row">
          <div class="color-swatch" style="background:<?= e($book['color']??'#1a6b4a') ?>">
            <input type="color" name="color" value="<?= e($book['color']??'#1a6b4a') ?>" oninput="updBookColor(this.value)">
          </div>
          <div><div class="color-hex" id="bColorHex"><?= strtoupper($book['color']??'#1a6b4a') ?></div><div class="color-name">Dashboard card accent</div></div>
        </div>
        <div style="margin-top:12px;border-radius:12px;overflow:hidden;border:1.5px solid var(--border)">
          <div id="bCardPreview" style="background:<?= e($book['color']??'#1a6b4a') ?>;padding:14px 18px;display:flex;justify-content:space-between;align-items:center;transition:background .3s">
            <div>
              <div style="font-size:16px;font-weight:700;color:#fff" id="bCardName"><?= e($book['name']) ?></div>
              <div style="font-size:11px;color:rgba(255,255,255,.7);margin-top:2px"><?= $book['type']==='business'?'Business Book':'Personal Book' ?></div>
            </div>
            <div style="font-size:22px;opacity:.5"><i class="fa-solid fa-book" style="color: #ffffff;"></i></div>
          </div>
          <div style="padding:8px 18px;background:var(--bg);font-size:11px;color:var(--text-muted)">Dashboard preview</div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php if(!$isPersonal): ?>

<!-- BUSINESS -->
<div class="st-sec" id="sec-business">
  <div class="st-panel">
    <div class="st-head">
      <div class="st-icon blue"><i class="fa-solid fa-building"></i></div>
      <div><h2>Business Information</h2><p class="panel-desc">Appears on invoices and public documents</p></div>
    </div>
    <div class="st-panel-body">
      <div class="fg">
        <label>Business / Shop Name</label>
        <input type="text" name="business_name" value="<?= e($details['business_name']??$book['name']) ?>"
               oninput="document.getElementById('iPrevBiz').textContent=this.value||'Business';markDirty()">
      </div>
      <div class="fg-row">
        <div class="fg"><label>Phone</label>
          <input type="text" name="phone" value="<?= e($book['phone']??$details['phone']??'') ?>" oninput="markDirty()" placeholder="+880 1XXX-XXXXXX">
        </div>
        <div class="fg"><label>Email</label>
          <input type="email" name="email" value="<?= e($book['email']??'') ?>" oninput="markDirty()" placeholder="shop@example.com">
        </div>
      </div>
      <div class="fg">
        <label>Address</label>
        <textarea name="address" rows="3" oninput="markDirty()" placeholder="Street, City, Postcode…"><?= e($book['address']??$details['address']??'') ?></textarea>
      </div>
      <div class="fg">
        <label>Business Logo <span style="font-weight:400;text-transform:none;letter-spacing:0;color:var(--text-muted)">— PNG/JPG/SVG, max 2 MB</span></label>
        <?php if(!empty($book['logo'])): ?>
        <div style="margin-bottom:10px;display:flex;align-items:center;gap:12px;padding:10px 14px;background:var(--bg);border-radius:10px;border:1.5px solid var(--border)">
          <img src="<?= asset('uploads/'.$book['logo']) ?>" style="max-height:48px;max-width:140px;object-fit:contain;border-radius:6px" onerror="this.parentElement.style.display='none'">
          <div style="font-size:12px;color:var(--text-muted)">Current logo<br><span style="font-size:11px">Upload new to replace</span></div>
        </div>
        <?php endif; ?>
        <div class="logo-zone" id="logoZone" onclick="document.getElementById('logoInput').click()">
          <img class="logo-preview" id="logoPreview" src="" alt="">
          <div id="logoIcon" style="font-size:28px;margin-bottom:6px"><i class="fa-solid fa-image" style="color: var(--brand);"></i></div>
          <p><strong>Click to upload</strong> or drag &amp; drop</p>
          <p style="margin-top:3px;font-size:11px">PNG, JPG, SVG up to 2 MB</p>
        </div>
        <input type="file" name="logo" id="logoInput" accept="image/png,image/jpeg,image/webp,image/svg+xml" onchange="prevLogo(this)" style="display:none">
      </div>
    </div>
  </div>
</div>

<!-- INVOICE -->
<div class="st-sec" id="sec-invoice">

  <!-- Numbering -->
  <div class="st-panel">
    <div class="st-head">
      <div class="st-icon amber"><i class="fa-solid fa-hashtag"></i></div>
      <div><h2>Invoice Numbering</h2><p class="panel-desc">Prefix controls how invoice numbers are formatted</p></div>
    </div>
    <div class="st-panel-body">
      <div class="fg-row">
        <div class="fg">
          <label>Sale Invoice Prefix</label>
          <input type="text" name="invoice_prefix" value="<?= e($details['invoice_prefix']??'INV') ?>"
                 style="text-transform:uppercase;font-family:'Courier New',monospace;font-weight:700;letter-spacing:1px"
                 maxlength="10" placeholder="INV" id="sPfx"
                 oninput="this.value=this.value.toUpperCase();updPfx('sPfxPrev',this.value,'<?= str_pad($details['invoice_counter']??1,6,'0',STR_PAD_LEFT) ?>');markDirty()">
          <div class="pfx-prev" id="sPfxPrev">
            <span class="pp"><?= e($details['invoice_prefix']??'INV') ?></span>
            <span class="pn">-<?= str_pad($details['invoice_counter']??1,6,'0',STR_PAD_LEFT) ?></span>
          </div>
        </div>
        <div class="fg">
          <label>Purchase Invoice Prefix</label>
          <input type="text" name="invoice_prefix_purchase" value="<?= e($details['invoice_prefix_purchase']??'PUR') ?>"
                 style="text-transform:uppercase;font-family:'Courier New',monospace;font-weight:700;letter-spacing:1px"
                 maxlength="10" placeholder="PUR" id="pPfx"
                 oninput="this.value=this.value.toUpperCase();updPfx('pPfxPrev',this.value,'<?= str_pad($details['invoice_counter_purchase']??1,6,'0',STR_PAD_LEFT) ?>');markDirty()">
          <div class="pfx-prev" id="pPfxPrev">
            <span class="pp"><?= e($details['invoice_prefix_purchase']??'PUR') ?></span>
            <span class="pn">-<?= str_pad($details['invoice_counter_purchase']??1,6,'0',STR_PAD_LEFT) ?></span>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Appearance -->
  <div class="st-panel">
    <div class="st-head">
      <div class="st-icon"><i class="fa-solid fa-palette"></i></div>
      <div><h2>Invoice Appearance</h2><p class="panel-desc">Colour and font used on printed invoices</p></div>
    </div>
    <div class="st-panel-body">
      <div class="fg">
        <label>Invoice Theme Colour</label>
        <div class="color-pick-row">
          <div class="color-swatch" style="background:<?= e($book['theme_color']??'#1a6b4a') ?>">
            <input type="color" name="theme_color" value="<?= e($book['theme_color']??'#1a6b4a') ?>" oninput="updThemeColor(this.value)">
          </div>
          <div><div class="color-hex" id="tColorHex"><?= strtoupper($book['theme_color']??'#1a6b4a') ?></div><div class="color-name">Invoice header, totals &amp; accents</div></div>
        </div>
        <div class="inv-prev">
          <div class="inv-prev-head" id="iPrevHead" style="background:<?= e($book['theme_color']??'#1a6b4a') ?>">
            <span>INVOICE</span>
            <span id="iPrevBiz"><?= e($details['business_name']??$book['name']) ?></span>
          </div>
          <div class="inv-prev-body">
            <div class="inv-prev-row"><div class="t">Invoice No</div><div class="v"><?= e($details['invoice_prefix']??'INV') ?>-000001</div></div>
            <div class="inv-prev-row"><div class="t">Total</div><div class="v" id="iPrevTotal" style="color:<?= e($book['theme_color']??'#1a6b4a') ?>">৳ 5,000</div></div>
            <div class="inv-prev-row"><div class="t">Status</div><div class="v" style="color:var(--green)">Paid ✓</div></div>
          </div>
        </div>
      </div>
      <div class="fg" style="margin-top:18px">
        <label>Invoice Font</label>
        <select name="invoice_font" onchange="markDirty()">
          <?php foreach($fonts as $f): ?>
          <option value="<?= e($f) ?>" <?= ($details['invoice_font']??'DejaVu Sans')===$f?'selected':'' ?>><?= e($f) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
  </div>

  <!-- Inventory method -->
  <div class="st-panel">
    <div class="st-head">
      <div class="st-icon green"><i class="fa-solid fa-layer-group"></i></div>
      <div><h2>Inventory Method</h2><p class="panel-desc">Which stock batch is consumed first when making a sale</p></div>
    </div>
    <div class="st-panel-body">
      <?php $cm = $details['inventory_method']??'FIFO'; ?>
      <div class="imc-wrap">
        <label class="imc <?= $cm==='FIFO'?'sel':'' ?>" id="fifoCard" onclick="selMethod('FIFO')">
          <input type="radio" name="inventory_method" value="FIFO" <?= $cm==='FIFO'?'checked':'' ?>>
          <div class="imc-badge" style="background:var(--blue-bg)"><i class="fa-solid fa-arrows-spin" style="color: var(--brand);"></i></div>
          <h4>FIFO</h4>
          <div style="font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.5px;color:var(--brand);margin-bottom:5px">First In, First Out</div>
          <div class="imc-sub">Oldest purchased stock is sold first. Standard for most retail businesses.</div>
          <div class="imc-ck"><i class="fa-solid fa-check"></i></div>
        </label>
        <label class="imc <?= $cm==='LIFO'?'sel':'' ?>" id="lifoCard" onclick="selMethod('LIFO')">
          <input type="radio" name="inventory_method" value="LIFO" <?= $cm==='LIFO'?'checked':'' ?>>
          <div class="imc-badge" style="background:var(--amber-bg)"><i class="fa-solid fa-arrows-turn-to-dots" style="color: var(--brand);"></i></div>
          <h4>LIFO</h4>
          <div style="font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.5px;color:var(--amber);margin-bottom:5px">Last In, First Out</div>
          <div class="imc-sub">Most recently purchased stock is sold first. Common in certain tax strategies.</div>
          <div class="imc-ck" style="background:var(--amber)"><i class="fa-solid fa-check"></i></div>
        </label>
      </div>
    </div>
  </div>

  <!-- Footer note -->
  <div class="st-panel">
    <div class="st-head">
      <div class="st-icon muted"><i class="fa-solid fa-align-left"></i></div>
      <div><h2>Invoice Footer</h2><p class="panel-desc">Optional tagline printed at the bottom of every invoice</p></div>
    </div>
    <div class="st-panel-body">
      <div class="fg">
        <label>Footer Note</label>
        <textarea name="footer_note" rows="2" placeholder="e.g. Thank you for your business! All sales are final." oninput="markDirty()"><?= e($details['footer_note']??'') ?></textarea>
      </div>
    </div>
  </div>

</div><!-- /sec-invoice -->

<!-- CURRENCIES -->
<div class="st-sec" id="sec-currencies">
  <div class="st-panel">
    <div class="st-head">
      <div class="st-icon amber"><i class="fa-solid fa-coins"></i></div>
      <div><h2>Currencies</h2><p class="panel-desc">The default currency symbol appears next to every amount</p></div>
    </div>
    <div class="st-panel-body">
      <div style="display:grid;grid-template-columns:60px 50px 1fr auto auto;gap:0 4px;padding:0 4px 6px;font-size:10px;font-weight:700;text-transform:uppercase;color:var(--text-muted);letter-spacing:.4px">
        <span>Code</span><span>Sym</span><span>Name</span><span style="margin-right:8px">Default</span><span></span>
      </div>
      <div id="currencyList">
        <?php
        $dcs = !empty($currencies)?$currencies:[['code'=>'BDT','symbol'=>'৳','name'=>'Bangladeshi Taka','is_default'=>true]];
        foreach($dcs as $ci=>$cur):
        ?>
        <div class="cur-row">
          <input type="text" name="currencies[<?=$ci?>][code]"   style="width:60px;text-transform:uppercase;font-weight:700" value="<?= e($cur['code']) ?>"   placeholder="BDT" maxlength="10" oninput="markDirty()">
          <input type="text" name="currencies[<?=$ci?>][symbol]" style="width:50px;text-align:center"                        value="<?= e($cur['symbol']) ?>" placeholder="৳"   maxlength="10" oninput="markDirty()">
          <input type="text" name="currencies[<?=$ci?>][name]"   style="flex:1"                                              value="<?= e($cur['name']) ?>"   placeholder="Currency name" oninput="markDirty()">
          <label class="def-radio">
            <input type="radio" name="default_currency" value="<?=$ci?>" <?= $cur['is_default']?'checked':'' ?> onchange="setDef(<?=$ci?>)"> Default
          </label>
          <input type="hidden" name="currencies[<?=$ci?>][is_default]" id="cur_def_<?=$ci?>" value="<?= $cur['is_default']?'1':'0' ?>">
          <button type="button" class="del-btn" onclick="this.closest('.cur-row').remove();markDirty()" title="Remove">×</button>
        </div>
        <?php endforeach; ?>
      </div>
      <button type="button" onclick="addCur()" class="btn btn-sm btn-secondary" style="margin-top:4px"><i class="fa-solid fa-plus"></i> Add Currency</button>
    </div>
  </div>
</div>

<!-- METHODS -->
<div class="st-sec" id="sec-methods">
  <div class="st-panel">
    <div class="st-head">
      <div class="st-icon green"><i class="fa-solid fa-truck-fast"></i></div>
      <div><h2>Delivery Methods</h2><p class="panel-desc">Options in the delivery dropdown on new invoices</p></div>
    </div>
    <div class="st-panel-body">
      <div id="deliveryList">
        <?php foreach($deliveryMethods as $m): ?>
        <div class="mrow"><input type="text" name="delivery_methods[]" value="<?= e($m['label']) ?>" oninput="markDirty()">
          <button type="button" class="del-btn" onclick="this.closest('.mrow').remove();markDirty()">×</button></div>
        <?php endforeach; ?>
      </div>
      <button type="button" onclick="addM('deliveryList','delivery_methods[]')" class="btn btn-sm btn-secondary" style="margin-top:4px"><i class="fa-solid fa-plus"></i> Add Option</button>
    </div>
  </div>
  <div class="st-panel">
    <div class="st-head">
      <div class="st-icon blue"><i class="fa-solid fa-credit-card"></i></div>
      <div><h2>Payment Methods</h2><p class="panel-desc">Options in the payment dropdown on new invoices</p></div>
    </div>
    <div class="st-panel-body">
      <div id="paymentList">
        <?php foreach($paymentMethods as $m): ?>
        <div class="mrow"><input type="text" name="payment_methods[]" value="<?= e($m['label']) ?>" oninput="markDirty()">
          <button type="button" class="del-btn" onclick="this.closest('.mrow').remove();markDirty()">×</button></div>
        <?php endforeach; ?>
      </div>
      <button type="button" onclick="addM('paymentList','payment_methods[]')" class="btn btn-sm btn-secondary" style="margin-top:4px"><i class="fa-solid fa-plus"></i> Add Option</button>
    </div>
  </div>
</div>

<?php endif; ?>

<!-- DANGER -->
<div class="st-sec" id="sec-danger">
  <div class="st-panel danger">
    <div class="st-head">
      <div class="st-icon red"><i class="fa-solid fa-triangle-exclamation"></i></div>
      <div><h2>Danger Zone</h2><p class="panel-desc">These actions cannot be undone</p></div>
    </div>
    <div class="st-danger-row">
      <div>
        <strong>Delete "<?= e($book['name']) ?>"</strong>
        <span>Permanently hides this book — invoices, products, customers, everything.</span>
      </div>
      <button type="button" class="btn btn-danger" style="white-space:nowrap;flex-shrink:0" onclick="confirmDeleteBook()">
        <i class="fa-solid fa-trash"></i> Delete Book
      </button>
    </div>
  </div>
</div>

</div><!-- .st-body -->
</div><!-- .st-wrap -->
</form>

<script>
function sw(name,btn){
  document.querySelectorAll('.st-sec').forEach(s=>s.classList.remove('active'));
  document.querySelectorAll('.st-tab').forEach(b=>b.classList.remove('active'));
  const s=document.getElementById('sec-'+name);
  if(s)s.classList.add('active');
  if(btn)btn.classList.add('active');
  window.scrollTo({top:0,behavior:'smooth'});
}
let _dirty=false;
function markDirty(){
  if(!_dirty){_dirty=true;const b=document.getElementById('saveBtn');b.classList.add('dirty');b.innerHTML='<i class="fa-solid fa-circle-dot" style="color:#fbbf24"></i> Unsaved Changes';}
}
document.getElementById('sForm').addEventListener('submit',()=>{
  _dirty=false;const b=document.getElementById('saveBtn');b.classList.remove('dirty');b.innerHTML='<i class="fa-solid fa-spinner fa-spin"></i> Saving…';
});
function updBookColor(v){
  markDirty();
  ['#navAvatar','#bCardPreview','#bColorSwatch??'].forEach(()=>{});
  document.getElementById('navAvatar').style.background=v;
  document.getElementById('bCardPreview').style.background=v;
  document.getElementById('bColorHex').textContent=v.toUpperCase();
  document.querySelectorAll('[name=color]~*,.color-swatch').forEach(()=>{});
  document.querySelector('[name=color]').closest('.color-swatch').style.background=v;
}
function updThemeColor(v){
  markDirty();
  document.getElementById('tColorHex').textContent=v.toUpperCase();
  document.getElementById('iPrevHead').style.background=v;
  document.getElementById('iPrevTotal').style.color=v;
  document.querySelector('[name=theme_color]').closest('.color-swatch').style.background=v;
}
function updPfx(id,pfx,num){const e=document.getElementById(id);if(!e)return;e.querySelector('.pp').textContent=pfx||'INV';e.querySelector('.pn').textContent='-'+num;}
function prevLogo(input){
  markDirty();
  if(!input.files||!input.files[0])return;
  const r=new FileReader();
  r.onload=e=>{const p=document.getElementById('logoPreview');p.src=e.target.result;p.style.display='block';document.getElementById('logoIcon').style.display='none';};
  r.readAsDataURL(input.files[0]);
}
const lz=document.getElementById('logoZone');
if(lz){
  lz.addEventListener('dragover',e=>{e.preventDefault();lz.style.borderColor='var(--brand)'});
  lz.addEventListener('dragleave',()=>lz.style.borderColor='');
  lz.addEventListener('drop',e=>{e.preventDefault();lz.style.borderColor='';if(e.dataTransfer.files[0]){document.getElementById('logoInput').files=e.dataTransfer.files;prevLogo(document.getElementById('logoInput'));}});
}
function selMethod(v){
  markDirty();
  document.querySelectorAll('.imc').forEach(c=>c.classList.remove('sel'));
  document.getElementById(v.toLowerCase()+'Card').classList.add('sel');
  document.querySelector(`input[name="inventory_method"][value="${v}"]`).checked=true;
}
let curIdx=<?= count(!empty($currencies)?$currencies:[['x']]) ?>;
function addCur(){
  markDirty();
  const list=document.getElementById('currencyList');
  const i=curIdx++;
  const d=document.createElement('div');d.className='cur-row';
  d.innerHTML=`<input type="text" name="currencies[${i}][code]"   style="width:60px;text-transform:uppercase;font-weight:700" placeholder="USD" maxlength="10" oninput="markDirty()">
    <input type="text" name="currencies[${i}][symbol]" style="width:50px;text-align:center" placeholder="$" maxlength="10" oninput="markDirty()">
    <input type="text" name="currencies[${i}][name]"   style="flex:1" placeholder="Currency name" oninput="markDirty()">
    <label class="def-radio"><input type="radio" name="default_currency" value="${i}" onchange="setDef(${i})"> Default</label>
    <input type="hidden" name="currencies[${i}][is_default]" id="cur_def_${i}" value="0">
    <button type="button" class="del-btn" onclick="this.closest('.cur-row').remove();markDirty()">×</button>`;
  list.appendChild(d);d.querySelector('input').focus();
}
function setDef(si){document.querySelectorAll('[id^="cur_def_"]').forEach(e=>{e.value=parseInt(e.id.replace('cur_def_',''))===si?'1':'0';});markDirty();}
function addM(lid,fname){
  markDirty();
  const list=document.getElementById(lid);
  const d=document.createElement('div');d.className='mrow';
  d.innerHTML=`<input type="text" name="${fname}" placeholder="New option…" oninput="markDirty()"><button type="button" class="del-btn" onclick="this.closest('.mrow').remove();markDirty()">×</button>`;
  list.appendChild(d);d.querySelector('input').focus();
}
window.addEventListener('beforeunload',e=>{if(_dirty){e.preventDefault();e.returnValue='';}});
function confirmDeleteBook(){
  if(!confirm('Delete "<?= e(addslashes($book['name'])) ?>"? This cannot be undone.'))return;
  var f=document.createElement('form');
  f.method='POST';
  f.action='/books/<?= $book['id'] ?>/delete';
  var c=document.createElement('input');
  c.type='hidden';c.name='_csrf';c.value='<?= csrf_token() ?>';
  f.appendChild(c);document.body.appendChild(f);f.submit();
}
</script>

<?php $content=ob_get_clean(); require BASE_PATH.'/views/partials/layout.php'; ?>
