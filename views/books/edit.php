<?php
$pageTitle = 'Settings — ' . e($book['name']);
$fonts = ['DejaVu Sans','DejaVu Serif','DejaVu Sans Mono'];
$isPersonal = $book['type'] !== 'business';
ob_start();
?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
.settings-wrap{display:grid;grid-template-columns:220px 1fr;gap:28px;max-width:960px;align-items:start}
.settings-nav{position:sticky;top:20px;background:#fff;border:1.5px solid var(--border);border-radius:16px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.06)}
.settings-nav-book{padding:20px 18px 16px;border-bottom:1.5px solid var(--border);display:flex;align-items:center;gap:12px}
.book-avatar{width:42px;height:42px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-family:'Outfit',sans-serif;font-size:18px;font-weight:800;color:#fff;flex-shrink:0;transition:background .3s}
.snb-name{font-family:'Outfit',sans-serif;font-size:14px;font-weight:700;line-height:1.2;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.snb-type{font-size:10px;color:var(--text-muted);font-weight:600;text-transform:uppercase;letter-spacing:.5px;margin-top:2px}
.stab-list{padding:8px}
.stab{display:flex;align-items:center;gap:10px;padding:9px 12px;border-radius:10px;cursor:pointer;font-size:13px;font-weight:500;color:var(--text-muted);transition:all .15s;border:none;background:none;width:100%;text-align:left}
.stab:hover{background:var(--bg);color:var(--text)}
.stab.active{background:var(--brand-light);color:var(--brand);font-weight:700}
.stab i{width:16px;text-align:center;font-size:13px}
.stab-badge{margin-left:auto;background:var(--brand);color:#fff;border-radius:20px;font-size:9px;font-weight:800;padding:1px 6px}
.stab-sep{height:1px;background:var(--border);margin:6px 8px}
.s-save-btn{margin:8px;width:calc(100% - 16px);padding:10px;background:var(--brand);color:#fff;border:none;border-radius:10px;font-size:13px;font-weight:700;cursor:pointer;transition:all .2s;display:flex;align-items:center;justify-content:center;gap:7px;font-family:inherit}
.s-save-btn:hover{background:var(--brand-dark);transform:translateY(-1px);box-shadow:0 4px 14px rgba(26,107,74,.35)}
.s-save-btn.dirty{animation:psave 1.8s infinite}
@keyframes psave{0%,100%{box-shadow:0 0 0 0 rgba(26,107,74,.4)}50%{box-shadow:0 0 0 7px rgba(26,107,74,0)}}
.settings-content{display:flex;flex-direction:column;gap:0}
.s-sec{display:none;flex-direction:column;gap:18px;animation:fsec .2s ease}
.s-sec.active{display:flex}
@keyframes fsec{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:translateY(0)}}
.sc{background:#fff;border:1.5px solid var(--border);border-radius:16px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.04)}
.sc-head{padding:18px 22px 14px;border-bottom:1.5px solid var(--border);display:flex;align-items:center;gap:14px}
.sc-icon{width:38px;height:38px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0}
.sc-head h3{font-family:'Outfit',sans-serif;font-size:15px;font-weight:700;line-height:1.2}
.sc-head p{font-size:12px;color:var(--text-muted);margin-top:2px}
.sc-body{padding:22px}
.sf{margin-bottom:18px}
.sf:last-child{margin-bottom:0}
.sf label{display:block;font-size:11px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:7px}
.sf input[type=text],.sf input[type=email],.sf select,.sf textarea{width:100%;padding:10px 13px;border:1.5px solid var(--border);border-radius:10px;font-size:13px;font-family:inherit;outline:none;background:#fff;transition:border-color .15s,box-shadow .15s;color:var(--text)}
.sf input:focus,.sf select:focus,.sf textarea:focus{border-color:var(--brand);box-shadow:0 0 0 3px rgba(26,107,74,.12)}
.sf-hint{font-size:11px;color:var(--text-muted);margin-top:5px;line-height:1.5}
.sf-row{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.color-wrap{display:flex;align-items:center;gap:12px}
.cswatch{width:44px;height:44px;border-radius:12px;border:2px solid rgba(0,0,0,.08);cursor:pointer;flex-shrink:0;position:relative;overflow:hidden;transition:transform .15s,box-shadow .15s}
.cswatch:hover{transform:scale(1.06);box-shadow:0 4px 14px rgba(0,0,0,.18)}
.cswatch input[type=color]{position:absolute;inset:0;width:100%;height:100%;opacity:0;cursor:pointer;border:none;padding:0}
.chex{font-family:'Courier New',monospace;font-size:13px;font-weight:700;letter-spacing:.5px}
.cname{font-size:11px;color:var(--text-muted);margin-top:2px}
.inv-prev{border-radius:12px;overflow:hidden;border:1.5px solid var(--border);margin-top:10px}
.inv-prev-head{padding:10px 14px;display:flex;justify-content:space-between;align-items:center}
.inv-prev-head span{color:#fff;font-size:11px;font-weight:700}
.inv-prev-body{padding:10px 14px;background:#f9f9f9;display:flex;gap:8px}
.inv-prev-row{flex:1;background:#fff;border-radius:6px;padding:6px 8px}
.inv-prev-row .t{font-size:9px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.3px}
.inv-prev-row .v{font-size:12px;font-weight:700;color:var(--text);font-family:'Outfit',sans-serif}
.logo-zone{border:2px dashed var(--border);border-radius:12px;padding:20px;text-align:center;cursor:pointer;transition:all .2s;background:var(--bg);position:relative}
.logo-zone:hover{border-color:var(--brand);background:var(--brand-light)}
.logo-zone p{font-size:12px;color:var(--text-muted)}
.logo-zone p strong{color:var(--brand)}
#logoPreview{max-height:60px;max-width:200px;object-fit:contain;margin:0 auto 8px;display:none}
.imc-wrap{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.imc{border:2px solid var(--border);border-radius:14px;padding:18px 16px;cursor:pointer;transition:all .2s;position:relative;background:#fff;display:block}
.imc:hover{border-color:var(--brand);background:var(--brand-light)}
.imc.sel{border-color:var(--brand);background:var(--brand-light)}
.imc input[type=radio]{position:absolute;opacity:0}
.imc-badge{display:inline-flex;align-items:center;justify-content:center;width:40px;height:40px;border-radius:10px;font-size:20px;margin-bottom:10px}
.imc h4{font-family:'Outfit',sans-serif;font-size:16px;font-weight:800;margin-bottom:3px}
.imc .imc-sub{font-size:11px;color:var(--text-muted);line-height:1.5}
.imc .imc-ck{position:absolute;top:12px;right:12px;width:20px;height:20px;border-radius:50%;background:var(--brand);display:flex;align-items:center;justify-content:center;color:#fff;font-size:10px;opacity:0;transition:opacity .15s}
.imc.sel .imc-ck{opacity:1}
.cur-row{display:flex;gap:8px;align-items:center;padding:10px 12px;background:var(--bg);border-radius:10px;border:1.5px solid var(--border);margin-bottom:8px;transition:border-color .15s}
.cur-row:hover{border-color:var(--border-dark)}
.cur-row input[type=text]{padding:6px 9px;border:1.5px solid var(--border);border-radius:8px;font-size:13px;font-family:inherit;outline:none;background:#fff;transition:border-color .15s}
.cur-row input:focus{border-color:var(--brand)}
.def-radio{display:flex;align-items:center;gap:5px;font-size:11px;font-weight:600;color:var(--text-muted);cursor:pointer;white-space:nowrap;padding:5px 8px;border-radius:8px;border:1.5px solid var(--border);transition:all .15s}
.def-radio:has(input:checked){background:var(--green-bg);color:var(--green);border-color:var(--green)}
.def-radio input{accent-color:var(--green)}
.del-btn{width:28px;height:28px;border:none;background:none;cursor:pointer;color:var(--text-muted);font-size:18px;line-height:1;border-radius:6px;transition:all .15s;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.del-btn:hover{background:var(--red-bg);color:var(--red)}
.mrow{display:flex;gap:8px;align-items:center;margin-bottom:8px}
.mrow input{flex:1;padding:9px 13px;border:1.5px solid var(--border);border-radius:10px;font-size:13px;font-family:inherit;outline:none;background:#fff;transition:border-color .15s}
.mrow input:focus{border-color:var(--brand)}
.pfx-prev{display:inline-flex;align-items:center;border-radius:8px;overflow:hidden;border:1.5px solid var(--border);font-size:12px;font-weight:700;font-family:'Courier New',monospace;margin-top:6px}
.pfx-prev .pp{background:var(--brand);color:#fff;padding:4px 8px}
.pfx-prev .pn{background:var(--bg);color:var(--text-muted);padding:4px 8px}
.dcard{background:#fff;border:2px solid #fecaca;border-radius:16px;overflow:hidden;box-shadow:0 2px 12px rgba(220,38,38,.06)}
.dcard-head{padding:16px 22px;background:linear-gradient(135deg,#fff5f5,#fff);border-bottom:1.5px solid #fecaca;display:flex;align-items:center;gap:12px}
.dcard-head h3{font-family:'Outfit',sans-serif;font-size:15px;font-weight:700;color:var(--red)}
@media(max-width:720px){
  .settings-wrap{grid-template-columns:1fr}
  .settings-nav{position:static}
  .stab-list{display:flex;overflow-x:auto;gap:4px}
  .stab{white-space:nowrap;flex-shrink:0}
  .stab-sep{display:none}
  .sf-row{grid-template-columns:1fr}
  .imc-wrap{grid-template-columns:1fr}
}
</style>

<form action="/books/<?= $book['id'] ?>/edit" method="POST" enctype="multipart/form-data" id="sForm">
<input type="hidden" name="_csrf" value="<?= csrf_token() ?>">

<div class="settings-wrap">

<!-- ══ NAV ══ -->
<nav class="settings-nav">
  <div class="settings-nav-book">
    <div class="book-avatar" id="navAvatar" style="background:<?= e($book['color']??'#1a6b4a') ?>">
      <?= mb_strtoupper(mb_substr($book['name'],0,1)) ?>
    </div>
    <div>
      <div class="snb-name"><?= e($book['name']) ?></div>
      <div class="snb-type"><?= $book['type']==='business'?'Business':'Personal' ?></div>
    </div>
  </div>
  <div class="stab-list">
    <button type="button" class="stab active" onclick="sw('general',this)"><i class="fa-solid fa-sliders"></i> General</button>
    <?php if(!$isPersonal): ?>
    <button type="button" class="stab" onclick="sw('business',this)"><i class="fa-solid fa-building"></i> Business</button>
    <button type="button" class="stab" onclick="sw('invoice',this)"><i class="fa-solid fa-file-invoice"></i> Invoice</button>
    <div class="stab-sep"></div>
    <button type="button" class="stab" onclick="sw('currencies',this)">
      <i class="fa-solid fa-coins"></i> Currencies
      <?php if(!empty($currencies)): ?><span class="stab-badge"><?= count($currencies) ?></span><?php endif; ?>
    </button>
    <button type="button" class="stab" onclick="sw('methods',this)"><i class="fa-solid fa-truck-fast"></i> Methods</button>
    <div class="stab-sep"></div>
    <a href="/books/<?= $book['id'] ?>/business-profile" class="stab" style="text-decoration:none"><i class="fa-solid fa-id-badge"></i> Public Profile</a>
    <div class="stab-sep"></div>
    <?php endif; ?>
    <button type="button" class="stab" onclick="sw('danger',this)" style="color:var(--red)"><i class="fa-solid fa-triangle-exclamation"></i> Danger Zone</button>
  </div>
  <button type="submit" class="s-save-btn" id="saveBtn"><i class="fa-solid fa-check"></i> Save Changes</button>
</nav>

<!-- ══ CONTENT ══ -->
<div class="settings-content">

<!-- GENERAL -->
<div class="s-sec active" id="sec-general">
  <div class="sc">
    <div class="sc-head">
      <div class="sc-icon" style="background:#f0fdf4;color:var(--brand)"><i class="fa-solid fa-book"></i></div>
      <div><h3>Book Identity</h3><p>Name and colour shown on your dashboard</p></div>
    </div>
    <div class="sc-body">
      <div class="sf">
        <label>Book Name</label>
        <input type="text" name="name" value="<?= e($book['name']) ?>" required
               oninput="document.querySelector('.snb-name').textContent=this.value||'…';document.getElementById('navAvatar').textContent=(this.value||'B')[0].toUpperCase();document.getElementById('bCardName').textContent=this.value||'Book';markDirty()">
      </div>
      <div class="sf">
        <label>Card Colour</label>
        <div class="color-wrap">
          <div class="cswatch" style="background:<?= e($book['color']??'#1a6b4a') ?>">
            <input type="color" name="color" value="<?= e($book['color']??'#1a6b4a') ?>" oninput="updBookColor(this.value)">
          </div>
          <div><div class="chex" id="bColorHex"><?= strtoupper($book['color']??'#1a6b4a') ?></div><div class="cname">Dashboard card accent</div></div>
        </div>
        <div style="margin-top:12px;border-radius:12px;overflow:hidden;border:1.5px solid var(--border)">
          <div id="bCardPreview" style="background:<?= e($book['color']??'#1a6b4a') ?>;padding:14px 18px;display:flex;justify-content:space-between;align-items:center;transition:background .3s">
            <div>
              <div style="font-family:'Outfit',sans-serif;font-size:16px;font-weight:800;color:#fff" id="bCardName"><?= e($book['name']) ?></div>
              <div style="font-size:11px;color:rgba(255,255,255,.7);margin-top:2px"><?= $book['type']==='business'?'Business Book':'Personal Book' ?></div>
            </div>
            <div style="font-size:22px;opacity:.5"><i class="fa-solid fa-book" style="color: #ffffff;"></i></div>
          </div>
          <div style="padding:8px 18px;background:#fafafa;font-size:11px;color:var(--text-muted)">Dashboard preview</div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php if(!$isPersonal): ?>

<!-- BUSINESS -->
<div class="s-sec" id="sec-business">
  <div class="sc">
    <div class="sc-head">
      <div class="sc-icon" style="background:#eff6ff;color:var(--blue)"><i class="fa-solid fa-building"></i></div>
      <div><h3>Business Information</h3><p>Appears on invoices and public documents</p></div>
    </div>
    <div class="sc-body">
      <div class="sf">
        <label>Business / Shop Name</label>
        <input type="text" name="business_name" value="<?= e($details['business_name']??$book['name']) ?>"
               oninput="document.getElementById('iPrevBiz').textContent=this.value||'Business';markDirty()">
      </div>
      <div class="sf-row">
        <div class="sf"><label>Phone</label>
          <input type="text" name="phone" value="<?= e($book['phone']??$details['phone']??'') ?>" oninput="markDirty()" placeholder="+880 1XXX-XXXXXX">
        </div>
        <div class="sf"><label>Email</label>
          <input type="email" name="email" value="<?= e($book['email']??'') ?>" oninput="markDirty()" placeholder="shop@example.com">
        </div>
      </div>
      <div class="sf">
        <label>Address</label>
        <textarea name="address" rows="3" oninput="markDirty()" placeholder="Street, City, Postcode…"><?= e($book['address']??$details['address']??'') ?></textarea>
      </div>
      <div class="sf">
        <label>Business Logo <span style="font-weight:400;text-transform:none;letter-spacing:0;color:var(--text-muted)">— PNG/JPG/SVG, max 2 MB</span></label>
        <?php if(!empty($book['logo'])): ?>
        <div style="margin-bottom:10px;display:flex;align-items:center;gap:12px;padding:10px 14px;background:var(--bg);border-radius:10px;border:1.5px solid var(--border)">
          <img src="<?= asset('uploads/'.$book['logo']) ?>" style="max-height:48px;max-width:140px;object-fit:contain;border-radius:6px" onerror="this.parentElement.style.display='none'">
          <div style="font-size:12px;color:var(--text-muted)">Current logo<br><span style="font-size:11px">Upload new to replace</span></div>
        </div>
        <?php endif; ?>
        <div class="logo-zone" id="logoZone" onclick="document.getElementById('logoInput').click()">
          <img id="logoPreview" src="" alt="">
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
<div class="s-sec" id="sec-invoice">

  <!-- Numbering -->
  <div class="sc">
    <div class="sc-head">
      <div class="sc-icon" style="background:#fffbeb;color:var(--amber)"><i class="fa-solid fa-hashtag"></i></div>
      <div><h3>Invoice Numbering</h3><p>Prefix controls how invoice numbers are formatted</p></div>
    </div>
    <div class="sc-body">
      <div class="sf-row">
        <div class="sf">
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
        <div class="sf">
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
  <div class="sc">
    <div class="sc-head">
      <div class="sc-icon" style="background:#fdf4ff;color:#9333ea"><i class="fa-solid fa-palette"></i></div>
      <div><h3>Invoice Appearance</h3><p>Colour and font used on printed invoices</p></div>
    </div>
    <div class="sc-body">
      <div class="sf">
        <label>Invoice Theme Colour</label>
        <div class="color-wrap">
          <div class="cswatch" style="background:<?= e($book['theme_color']??'#1a6b4a') ?>">
            <input type="color" name="theme_color" value="<?= e($book['theme_color']??'#1a6b4a') ?>" oninput="updThemeColor(this.value)">
          </div>
          <div><div class="chex" id="tColorHex"><?= strtoupper($book['theme_color']??'#1a6b4a') ?></div><div class="cname">Invoice header, totals &amp; accents</div></div>
        </div>
        <div class="inv-prev">
          <div class="inv-prev-head" id="iPrevHead" style="background:<?= e($book['theme_color']??'#1a6b4a') ?>">
            <span>INVOICE</span>
            <span id="iPrevBiz"><?= e($details['business_name']??$book['name']) ?></span>
          </div>
          <div class="inv-prev-body">
            <div class="inv-prev-row"><div class="t">Invoice No</div><div class="v"><?= e($details['invoice_prefix']??'INV') ?>-000001</div></div>
            <div class="inv-prev-row"><div class="t">Total</div><div class="v" id="iPrevTotal" style="color:<?= e($book['theme_color']??'#1a6b4a') ?>">৳ 5,000</div></div>
            <div class="inv-prev-row"><div class="t">Status</div><div class="v" style="color:#16a34a">Paid ✓</div></div>
          </div>
        </div>
      </div>
      <div class="sf" style="margin-top:18px">
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
  <div class="sc">
    <div class="sc-head">
      <div class="sc-icon" style="background:#f0fdf4;color:var(--green)"><i class="fa-solid fa-layer-group"></i></div>
      <div><h3>Inventory Method</h3><p>Which stock batch is consumed first when making a sale</p></div>
    </div>
    <div class="sc-body">
      <?php $cm = $details['inventory_method']??'FIFO'; ?>
      <div class="imc-wrap">
        <label class="imc <?= $cm==='FIFO'?'sel':'' ?>" id="fifoCard" onclick="selMethod('FIFO')">
          <input type="radio" name="inventory_method" value="FIFO" <?= $cm==='FIFO'?'checked':'' ?>>
          <div class="imc-badge" style="background:#e0f2fe"><i class="fa-solid fa-arrows-spin" style="color: var(--brand);"></i></div>
          <h4>FIFO</h4>
          <div style="font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.5px;color:var(--brand);margin-bottom:5px">First In, First Out</div>
          <div class="imc-sub">Oldest purchased stock is sold first. Standard for most retail businesses.</div>
          <div class="imc-ck"><i class="fa-solid fa-check"></i></div>
        </label>
        <label class="imc <?= $cm==='LIFO'?'sel':'' ?>" id="lifoCard" onclick="selMethod('LIFO')">
          <input type="radio" name="inventory_method" value="LIFO" <?= $cm==='LIFO'?'checked':'' ?>>
          <div class="imc-badge" style="background:#fef3c7"><i class="fa-solid fa-arrows-turn-to-dots" style="color: var(--brand);"></i></div>
          <h4>LIFO</h4>
          <div style="font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.5px;color:var(--amber);margin-bottom:5px">Last In, First Out</div>
          <div class="imc-sub">Most recently purchased stock is sold first. Common in certain tax strategies.</div>
          <div class="imc-ck" style="background:var(--amber)"><i class="fa-solid fa-check"></i></div>
        </label>
      </div>
    </div>
  </div>

  <!-- Footer note -->
  <div class="sc">
    <div class="sc-head">
      <div class="sc-icon" style="background:#f8fafc;color:var(--text-muted)"><i class="fa-solid fa-align-left"></i></div>
      <div><h3>Invoice Footer</h3><p>Optional tagline printed at the bottom of every invoice</p></div>
    </div>
    <div class="sc-body">
      <div class="sf">
        <label>Footer Note</label>
        <textarea name="footer_note" rows="2" placeholder="e.g. Thank you for your business! All sales are final." oninput="markDirty()"><?= e($details['footer_note']??'') ?></textarea>
      </div>
    </div>
  </div>

</div><!-- /sec-invoice -->

<!-- CURRENCIES -->
<div class="s-sec" id="sec-currencies">
  <div class="sc">
    <div class="sc-head">
      <div class="sc-icon" style="background:#fffbeb;color:var(--amber)"><i class="fa-solid fa-coins"></i></div>
      <div><h3>Currencies</h3><p>The default currency symbol appears next to every amount</p></div>
    </div>
    <div class="sc-body">
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
<div class="s-sec" id="sec-methods">
  <div class="sc">
    <div class="sc-head">
      <div class="sc-icon" style="background:#f0fdf4;color:var(--green)"><i class="fa-solid fa-truck-fast"></i></div>
      <div><h3>Delivery Methods</h3><p>Options in the delivery dropdown on new invoices</p></div>
    </div>
    <div class="sc-body">
      <div id="deliveryList">
        <?php foreach($deliveryMethods as $m): ?>
        <div class="mrow"><input type="text" name="delivery_methods[]" value="<?= e($m['label']) ?>" oninput="markDirty()">
          <button type="button" class="del-btn" onclick="this.closest('.mrow').remove();markDirty()">×</button></div>
        <?php endforeach; ?>
      </div>
      <button type="button" onclick="addM('deliveryList','delivery_methods[]')" class="btn btn-sm btn-secondary" style="margin-top:4px"><i class="fa-solid fa-plus"></i> Add Option</button>
    </div>
  </div>
  <div class="sc">
    <div class="sc-head">
      <div class="sc-icon" style="background:#eff6ff;color:var(--blue)"><i class="fa-solid fa-credit-card"></i></div>
      <div><h3>Payment Methods</h3><p>Options in the payment dropdown on new invoices</p></div>
    </div>
    <div class="sc-body">
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
<div class="s-sec" id="sec-danger">
  <div class="dcard">
    <div class="dcard-head">
      <div style="width:38px;height:38px;border-radius:10px;background:#fef2f2;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0">⚠️</div>
      <div><h3>Danger Zone</h3><p style="font-size:12px;color:var(--text-muted);margin-top:2px">These actions cannot be undone</p></div>
    </div>
    <div style="padding:22px">
      <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;padding:16px;background:#fef2f2;border-radius:12px;border:1px solid #fecaca">
        <div>
          <div style="font-weight:700;font-size:14px">Delete "<?= e($book['name']) ?>"</div>
          <div style="font-size:12px;color:var(--text-muted);margin-top:2px">Permanently hides this book — invoices, products, customers, everything.</div>
        </div>
        <button type="button" class="btn btn-danger" style="white-space:nowrap;flex-shrink:0" onclick="confirmDeleteBook()">
          <i class="fa-solid fa-trash"></i> Delete Book
        </button>
      </div>
    </div>
  </div>
</div>

</div><!-- .settings-content -->
</div><!-- .settings-wrap -->
</form>

<script>
function sw(name,btn){
  document.querySelectorAll('.s-sec').forEach(s=>s.classList.remove('active'));
  document.querySelectorAll('.stab').forEach(b=>b.classList.remove('active'));
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
  document.querySelectorAll('[name=color]~*,.cswatch').forEach(()=>{});
  document.querySelector('[name=color]').closest('.cswatch').style.background=v;
}
function updThemeColor(v){
  markDirty();
  document.getElementById('tColorHex').textContent=v.toUpperCase();
  document.getElementById('iPrevHead').style.background=v;
  document.getElementById('iPrevTotal').style.color=v;
  document.querySelector('[name=theme_color]').closest('.cswatch').style.background=v;
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
