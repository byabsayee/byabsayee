<?php
/**
 * "Still in development" notice, shown on every Byabsayee page (included right before </body>).
 * Self-contained (inline CSS, no JS, no dependency on app.css) so it also shows on login, public and error pages.
 * Never printed: invoices, receipts and barcode sheets stay clean.
 */
?>
<style>
  .bsy-devwarn{position:fixed;left:0;right:0;bottom:0;z-index:2147483000;background:#7c2d12;color:#fff;font:600 12px/1.45 system-ui,-apple-system,'Segoe UI',Roboto,sans-serif;padding:7px 14px;text-align:center;box-shadow:0 -2px 10px rgba(0,0,0,.25)}
  .bsy-devwarn b{background:#fbbf24;color:#451a03;border-radius:4px;padding:1px 6px;margin-right:6px;letter-spacing:.04em;font-size:11px}
  body{padding-bottom:42px}
  @media (max-width:640px){.bsy-devwarn{font-size:11px;padding:6px 10px}body{padding-bottom:64px}}
  .bsy-short{display:none}
  /* App shell on phones: one slim line sitting above the bottom bar; modals/drawer/notifications cover it */
  @media (max-width:768px){
    body.has-bottom-bar{padding-bottom:0}
    body.has-bottom-bar .bsy-devwarn{bottom:calc(60px + env(safe-area-inset-bottom,0px));z-index:180;padding:3px 8px;font-size:10.5px;box-shadow:none}
    body.has-bottom-bar .bsy-long{display:none}
    body.has-bottom-bar .bsy-short{display:inline}
    body.has-bottom-bar .fab{bottom:calc(60px + 36px + env(safe-area-inset-bottom,0px))}
    body.has-bottom-bar .app-content{padding-bottom:calc(60px + 56px + env(safe-area-inset-bottom,0px))}
  }
  @media print{.bsy-devwarn{display:none!important}body{padding-bottom:0!important}}
</style>
<div class="bsy-devwarn" role="note" aria-label="Development notice">
  <b>⚠ IN DEVELOPMENT</b><span class="bsy-short">Testing only — not for real work.</span><span class="bsy-long">Byabsayee is still in development and is updated often. Please do <u>not</u> use it for real work yet — it is unstable and could lose or destroy valuable data. For testing only.</span>
</div>
