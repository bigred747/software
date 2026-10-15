# software

WordPress plugins for [luxetrendsetters.com](https://luxetrendsetters.com/).

## Luxe Stack Conductor 1.0.2

One queue for every 24/7 learner. A green signal is stored only when the check is true. Opening the screen replaces a stalled hook, including `luxe_wzs_watchdog_tick`, with a real next run, then reads that run.

Upload this zip:

[dist/Luxe-Stack-Conductor-v1.0.2.zip](dist/Luxe-Stack-Conductor-v1.0.2.zip)

SHA-256 `063f345a8f4068c1eed4e9019f6892d074a96f47ff0f7d33dfcf4fc1a34f616d`

Direct download:

https://github.com/bigred747/software/raw/cursor/stack-conductor-0963/dist/Luxe-Stack-Conductor-v1.0.2.zip

Then activate it and open **Luxe Conductor**. Leave the rest of the stack active. Point Hostinger cron at `wp-cron.php` every 5 minutes.

## Luxe Mission Score Repair 1.0.0

Repairs the four Mission Control 1.8.0 checks that hold the verified score at 93. It does not publish and does not rewrite Amazon retail URLs.

Upload this zip:

[dist/Luxe-Mission-Score-Repair-v1.0.0.zip](dist/Luxe-Mission-Score-Repair-v1.0.0.zip)

SHA-256 `92e9ed9e7721f1b79eec9355a79e45c9ddb8dd555a74f1362151c5e55fc64fa3`

Then activate it, open **Tools → Luxe Mission Score**, load the homepage once, and run the Mission Control audit.

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
