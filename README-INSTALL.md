# Byabsayee ← → Online store (Kafeel) integration — Byabsayee side

56 files: 38 new, the rest are small edits to existing files. Nothing here touches your data until you run the migration.

## 1. Before you start
- Back up the database.
- Your Byabsayee must be reachable from the internet over **HTTPS** (the website calls it), and **APP_URL** must be that public address, e.g. `https://web.byabsayee.com` (not `http://IP:1021`).
- The reverse proxy / tunnel in front of nginx must send `X-Forwarded-Proto: https` (Cloudflare Tunnel and Nginx Proxy Manager do by default).

## 2. Put the files in place
Unzip so the folders line up with your project root (`/Sites/byabsayee`). Replace/add files only — do not delete anything.
`supervisord.conf` and `Dockerfile`-level files are baked into the image, so the cleanest way is: commit everything to the `byabsayee` GitHub repo, let the Action build the image to GHCR, then **redeploy the stack in Portainer**. (Without the rebuild everything works except the background worker — see step 5.)

## 3. Set the secret key (once)
Generate: `php -r "echo bin2hex(random_bytes(32));"`
Add to your Portainer env (Advanced mode) / `.env`:  `INTEGRATION_SECRET_KEY=<that value>`
Never change it later. The page warns you if it's missing.

## 4. Run the migration (once)
```
docker exec -it <byabsayee-container> php public/migrate_integrations.php
```
It prints `ok` / `--` per step and is safe to re-run. When it says "Done", delete `public/migrate_integrations.php` from the server (same as your other migrate_*.php scripts).

## 5. The background worker
`supervisord.conf` now starts `integration-worker` (every 30 s: delivers queued changes with retry/backoff, retries domain verification, prunes old logs). Check it in the container logs after redeploy. If you can't rebuild yet, nothing breaks: changes are also sent right after each request; only retries wait.

## 6. Connect your store
1. Byabsayee → open the business book → **Online store** (sidebar, under Book Settings).
2. Enter the website address (`https://shop.example.com`, its own domain/subdomain), choose who wins for currency/timezone/tax, pick what to sync → **Create link**.
3. A pairing code is shown **once**. In Kafeel admin → **ERP / Connect**: enter this book's address (your APP_URL) and the code.
4. Back in Byabsayee the status becomes *Verifying*; click **Verify now** if it doesn't turn to *Connected* within a minute.
5. In Kafeel finish the **setup review** (match/link products, customers, categories). From then on both sides sync.

## 7. What now works (tested end-to-end against the real Kafeel code)
Pairing + domain-ownership proof, signed requests, replay protection; products, categories, customers, coupons, payment methods, tax, delivery charges; orders ⇄ sales invoices (stock, FIFO/LIFO layers, dues all updated); payments (void instead of edit); returns with restock; stock as movements; conflicts queue; dead-letter retry; pause/resume; key rotation; disconnect & same-pair reconnect; reconciliation feed for the store.

## 8. Bugs I fixed on the way
- **Deleting an invoice left stock, dues and payments untouched** → now reverses all of it (online orders are cancelled and kept on record).
- **POS sales and returns bypassed the FIFO/LIFO cost layers** → now go through one stock ledger.
- **Manual stock removal left cost layers untouched** → fixed.
- **Deleting a return didn't undo its stock** → fixed.
- **Payments could be recorded on a cancelled invoice** → blocked.
- **Coupons ignored min spend / max discount / start date / usage limit** → now enforced (needed by the store's coupons).
- **Session cookie was never `Secure` behind HTTPS** → now Secure when the request is HTTPS.
- Machine API returns JSON for every error (no HTML pages).

## 9. Behaviour you should know
- Only sales you tick **"Also list this sale as an order on the online store"** (needs a customer with a phone) become website orders. Every other Byabsayee sale/purchase/adjustment still tells the store about the **stock change**.
- Deleting a coupon the store knows archives it instead of erasing it.
- A product's variants are the store's business; Byabsayee tracks the product total.
- Quantities sync as whole numbers (the store has no fractions).
- Payment methods = your invoice "payment methods" list (there are no fund accounts in Byabsayee). Methods added in the store appear here automatically.
