<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Print Coupons — <?= e($bizName) ?></title>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<style>
:root { --theme: <?= e($themeColor) ?>; }

* { box-sizing: border-box; margin: 0; padding: 0; }
body {
    font-family: Arial, sans-serif;
    background: #e8e8e8;
    padding: 16px;
}

/* ── Controls bar ── */
.controls {
    background: #fff;
    border-radius: 8px;
    padding: 14px 18px;
    margin-bottom: 16px;
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    align-items: center;
    box-shadow: 0 2px 8px rgba(0,0,0,.1);
}
.controls strong { font-size: 15px; margin-right: auto; }
.ctrl-label { font-size: 13px; color: #444; }
.ctrl-input {
    width: 65px;
    padding: 5px 8px;
    border: 1.5px solid #ddd;
    border-radius: 6px;
    font-size: 13px;
    text-align: center;
}
.btn-print {
    padding: 8px 20px;
    background: var(--theme);
    color: #fff;
    border: none;
    border-radius: 7px;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
}
.btn-back {
    padding: 8px 16px;
    background: #fff;
    color: #333;
    border: 1.5px solid #ccc;
    border-radius: 7px;
    font-size: 13px;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

/* ── A4 Preview ── */
.a4-preview {
    background: #fff;
    width: 210mm;
    min-height: 297mm;
    margin: 0 auto;
    box-shadow: 0 4px 20px rgba(0,0,0,.15);
    border-radius: 4px;
}

    .coupon-grid {
    display: grid;
    grid-template-columns: repeat(3, 69mm);
    gap: 0;
}

/* ── Single coupon: exactly 69×41mm ── */
.coupon {
    width: 69mm;
    height: 41mm;
    border: 5px solid var(--theme);
    border-radius: 5px;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    background: #fff;
    position: relative;
    page-break-inside: avoid;
    margin-left: 1px;
    margin-top: 2px;
}

.coupon-inner {
    display: flex;
    flex-direction: column;
    height: 100%;
}

/* ── Top row: logo | biz name | QR ── */
.coupon-top {
    display: flex;
    align-items: center;
    height: 50px;
    background: var(--theme);
    padding: 0px 10px 5px 10px;
}
.coupon-top img {
    height: 35px;
    width: auto;
    margin: 0px 5px 5px 5px;
}

.coupon-top p {
    flex: 1;
    font-size: 14px;
    font-weight: bolder;
    color: #ffffff;
    overflow: hidden;
    word-break: break-word;
    text-align: center;
}
.coupon-qr {
    width: 11mm;
    height: 11mm;
    flex-shrink: 0;
}
.coupon-qr canvas,
.coupon-qr img {
    width: 11mm !important;
    height: 11mm !important;
    border: 2px solid #fff;
}

/* ── Coupon title ── */
.coupon-title {
    font-size: 16px;
    font-weight: bolder;
    color: var(--theme);
    text-align: center;
    letter-spacing: 0.5px;
    line-height: 1;
    margin: 8px 0px 0px 0px;
}

/* ── Divider ── */
.coupon-divider {
    border: none;
    border-top: 2px dashed var(--theme);
    margin: 6px 10px 6px 10px;
}

/* ── Code ── */
.coupon-code {
    font-size: 10pt;
    font-weight: 900;
    letter-spacing: 2px;
    color: #000;
    text-align: center;
    align-items: center;
    line-height: 1;
    display: flex;
    margin: 0 auto;
}

.code-bg{
    background: var(--theme);
    color: #fff;
    height: 20px;
    width: max-content;
    padding: 5px 10px 20px 0px;
    border-radius: 10px;
    margin-left: 2px;
    align-items: center;
}

/* ── Expiry ── */
.coupon-expiry {
    font-size: 6px;
    color: #555;
    text-align: center;
    margin-top: 5px;
    line-height: 1;
}

/* ── Footer ── */
.coupon-footer {
    margin-right: 10px;
    margin-bottom: 5px;
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 5px;
}
.coupon-footer p {
    font-size: 8px;
    color: #000000;
}
.coupon-footer img {
    height: 15px;
    width: auto;
}

/* ── Print ── */
@media print {
    body { background: #fff; padding: 0; }
    .controls { display: none !important; }
    .a4-preview {
        box-shadow: none;
        border-radius: 0;
        padding: 5mm;
        width: 100%;
    }
    * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
}
@page { size: A4; margin: 0; }
</style>
</head>
<body>

<div class="controls">
    <strong>Print Coupons</strong>

    <label class="ctrl-label">
        Copies per coupon:
        <input type="number" id="copiesInput" class="ctrl-input" value="1" min="1" max="21">
    </label>

    <button class="btn-print" onclick="doPrint()"><i class="fa-solid fa-print"></i> Print</button>
    <a href="/books/<?= $book['id'] ?>/coupons" class="btn-back">← Back</a>
</div>

<div class="a4-preview">
    <div class="coupon-grid" id="couponGrid">
        <!-- filled by JS -->
    </div>
</div>

<script>
const THEME   = <?= json_encode($themeColor) ?>;
const BIZNAME = <?= json_encode($bizName) ?>;
const LOGOURL = <?= json_encode($logoUrl) ?>;
const COUPONS = <?= json_encode(array_map(fn($c) => [
    'id'             => $c['id'],
    'code'           => $c['code'],
    'name'           => $c['name'],
    'discount_type'  => $c['discount_type'],
    'discount_value' => $c['discount_value'],
    'expires_at'     => $c['expires_at'],
], $coupons), JSON_UNESCAPED_UNICODE) ?>;

function formatExpiry(expiresAt) {
    if (!expiresAt) return 'Permanent';
    const d = new Date(expiresAt);
    return 'Expiration Date: ' +
        String(d.getDate()).padStart(2,'0') + ' / ' +
        String(d.getMonth()+1).padStart(2,'0') + ' / ' +
        d.getFullYear();
}

function buildCouponHtml(c, idx) {
    const logoHtml = LOGOURL
        ? `<img class="coupon-logo" src="${LOGOURL}" alt="logo">`
        : `<div class="coupon-logo-placeholder">${BIZNAME.substring(0,3).toUpperCase()}</div>`;

    return `
    <div class="coupon">
        <div class="coupon-inner">
            <div class="coupon-top">
                ${logoHtml}
                <p>${esc(BIZNAME)}</p>
                <div class="coupon-qr" id="qr_${idx}_${c.id}"></div>
            </div>
            <div class="coupon-title">!!! Coupon !!!</div>
            <hr class="coupon-divider">
            <div class="coupon-code">Code: <div class="code-bg">&nbsp; ${esc(c.code)}</div></div>
            <div class="coupon-expiry">Expiration: ${esc(formatExpiry(c.expires_at))}</div>
            <div class="coupon-footer">
                <p>Generated Using: Byabsayee</p>
                <img src="<?= asset('assets/images/ByabsayeeLogo.png') ?>" alt="">
            </div>
        </div>
    </div>`;
}

function esc(s) {
    return String(s)
        .replace(/&/g,'&amp;').replace(/</g,'&lt;')
        .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function render() {
    const copies = Math.max(1, Math.min(21, parseInt(document.getElementById('copiesInput').value)||1));
    const grid   = document.getElementById('couponGrid');
    grid.innerHTML = '';

    const expanded = [];
    COUPONS.forEach(c => {
        for (let i = 0; i < copies; i++) expanded.push({c, idx: expanded.length});
    });

    expanded.forEach(({c, idx}) => {
        const tmp = document.createElement('div');
        tmp.innerHTML = buildCouponHtml(c, idx);
        const el = tmp.firstElementChild;
        grid.appendChild(el);

        // Generate QR code
        const qrEl = el.querySelector('#qr_' + idx + '_' + c.id);
        if (qrEl) {
            new QRCode(qrEl, {
                text: c.code,
                width: 42, height: 42,
                colorDark: THEME,
                colorLight: '#ffffff',
                correctLevel: QRCode.CorrectLevel.M
            });
        }
    });
}

function doPrint() {
    render();
    setTimeout(() => window.print(), 400);
}

document.getElementById('copiesInput').addEventListener('change', render);
render();
</script>
</body>
</html>
