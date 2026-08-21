# software

WordPress plugins for [luxetrendsetters.com](https://luxetrendsetters.com/).

## 1. Luxe Public Finish 1.0.0 — THIS IS THE PUBLIC 10/10 LAST PASS

Visible in WordPress as **Luxe Finish** (left admin menu). Plugin list name: **Luxe Public Finish**. New folder `luxe-public-finish`. Do **not** upload this zip over Luxe SEO, Theme Guard, Mission Control, Stay Repair, Product Scout, Keyword Autopilot, Reader-Love, or Hard Rescue.

Windows 11 / WordPress zip. Size **145KB**.

**Upload this file from Windows 11 into WordPress:**

[luxe-public-finish.zip](https://github.com/bigred747/software/raw/refs/heads/cursor/public-finish-0935/luxe-public-finish.zip)

SHA-256 `3c2d57badc1e30535ab2a944ee5631cfe7cb4c7ac671f40cec6c3b507ecca560`

Also named [Luxe-Public-Finish.zip](https://github.com/bigred747/software/raw/refs/heads/cursor/public-finish-0935/Luxe-Public-Finish.zip)

Plugins → Add Plugin → Upload Plugin → Install Now → **Install as a new plugin** → Activate.

Then open the left menu **Luxe Finish**. Leave every process On. Click **Run finish learning now**. Purge LiteSpeed if the Seiko tab still says `| Pr`.

What it does:

- Restores clipped browser / Open Graph titles (`| Pr`, `Review & Buyer Che`) on a 70-character word boundary
- Unique listing labels: a second card with the same title gets `· SKU` (HTML only, never writes `post_title`)
- One official Amazon Associate identification per page
- One Organization JSON-LD graph
- 12 green signals, 15-minute 24/7 learning, LiteSpeed purge
- **Zero** frontend JavaScript or CSS (does not add to the homepage’s 45 scripts)
- Never writes post content, never publishes, never rewrites Amazon URLs, never copies Instagram video

Honest 10/10: this plugin finishes titles, unique labels, disclosures, and duplicate schema. It does **not** strip Flatsome/LiteSpeed inline scripts. That would risk the keep-set. Luxe SEO 1.4.2 stays the SEO plugin. Theme Guard 1.3.1 stays the Flatsome plugin.

## 2. Luxe Affiliate Product Scout 5.6.8

24/7 catalog learning. One product per 5-minute cycle, hourly read-only snapshot, honest 95–100 scores. Windows 11 / WordPress PclZip package. Size **145KB**.

**Upload this file from Windows 11 into WordPress:**

[luxe-affiliate-product-scout.zip](https://github.com/bigred747/software/raw/refs/heads/cursor/product-scout-95-100-e8f3/luxe-affiliate-product-scout.zip)

SHA-256 `e5d83a29a33be7017220aad3c98964fe3b719bb656fbe5ce60a72cfd96ca2342`

If the last upload caused a critical error: in WordPress delete Product Scout (and any leftover `luxe-affiliate-product-scout*` folders in `wp-content/plugins/`), then upload this zip. Do not unzip it on the PC.

Plugins → Add Plugin → Upload Plugin → Install Now → Replace current with uploaded → Activate.

### After you activate 5.6.8

1. Confirm **Plugins** shows **Version 5.6.8**.
2. Open **Luxe Product Scout**.
3. Confirm **24/7 learning is ON**.
4. Click **Repair ALL Catalog Data + Recalculate** once so the **full catalog** scoreboard is stored (233 products, not 25).
5. Click **Run Full Catalog Audit** or **Run learning snapshot**. The board must show the full catalog, not a 25-product batch.
6. Leave the site running. WP-Cron learns **one product every 5 minutes**. The hourly snapshot refreshes averages **without replacing the catalog with a tiny batch**.

Your last 5.6.7 run scanned all **233** products. A later 25-product batch overwrote the board to 25/25. **5.6.8 stops that.** Tiny batches never replace the full-catalog scoreboard.

### 24/7 learning

| Cycle | What it does |
|---|---|
| Every 5 minutes | Repair + rescore **one** product. Log it. Never publishes. |
| Every hour | Read-only snapshot of stored scores for the **full catalog**. Updates the board averages. Does not import. |
| Repair next batch | Still 25 products for a manual catch-up. **Does not overwrite** the 233-product board. |
| Repair ALL / Full Audit | Only these (and the hourly snapshot) write the full catalog scoreboard. |

WP-Cron is kept alive on `init` / `shutdown` **without running repair on public page views**. If Hostinger has a real cron job, point it at `wp-cron.php` every 5 minutes.

### What scores 95–100

A product is 95–100 when it has **supported brand + ASIN + price + primary image** and is **not HOLD**. Missing ratings, seller, or warranty do **not** invent evidence and do **not** drop a valid product below 95.

- **Integrity 100** when a model signal is present.
- **Integrity 95** when the rest of the gate is met.
- Taxonomy leftovers are repaired. They **do not cap** a valid product at 94.
- Unknown brand, renewed, used, refurbished stay **HOLD** and stay below 95.
