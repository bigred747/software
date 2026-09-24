# software

WordPress plugins for [luxetrendsetters.com](https://luxetrendsetters.com/).

## Luxe Download Scout 1.1.1

Puts only the 100-score WZone rows into WooCommerce as unpublished drafts, in the matching category. A 95-score row, an unknown brand, a watch band, and a renewed item are not added. Glued titles such as AppleWatch and GooglePixel are read as Apple and Google. Drafts stay off the Product Scout scoreboard until you publish them.

**Upload this file from Windows 11 into WordPress:**

[luxe-download-scout.zip](https://github.com/bigred747/software/raw/refs/heads/cursor/download-scout-8474/luxe-download-scout.zip)

SHA-256 `4e7162c153d6b54282d84b4607af9d98a7b2cd890bfac1e2d39fc0d49ab9e3dc`

Product Scout 5.6.8 must already be active. Plugins → Add Plugin → Upload Plugin → Install Now → Replace current with uploaded → Activate. Open **Luxe Download Scout**, paste the same WZone list, and click **Add the very best to Products**. Do not unzip the file on the PC.

### Very best from the current drone page

These go in as drafts under **Drones**. Add the ASIN and primary image on each draft before you publish it.

| Product | Price | Category |
|---|---:|---|
| Bwine F7GB2 Pro | $441.79 | Drones |
| Potensic ATOM 2 Fly More Combo | $439.99 | Drones |
| Autel Robotics EVO 2 Pro V3 | $2,099.00 | Drones |
| Holy Stone Saturn T60A | $499.99 | Drones |
| Autel Robotics EVO II Dual 640T V3 | $4,799.00 | Drones |
| Autel Robotics EVO II Dual 640T Enterprise V3 | $5,299.00 | Drones |
| Holy Stone HS360S | $179.99 | Drones |

The Ruko 4K / 8K drone can reach 95 only, because the model name is not on the title, so it is not added.

## Luxe Affiliate Product Scout 5.6.8

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
