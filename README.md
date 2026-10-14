# software

WordPress plugins for [luxetrendsetters.com](https://luxetrendsetters.com/).

## Luxe Overlap Remover 1.0.0

Deletes 18 named overlap plugins after a Snapshot backup checkbox. It does not hook the public site, does not delete on activation, and cannot match WooCommerce, WZone, Rank Math, LiteSpeed, Wordfence, Amazon Bridge, Product Scout, Ultimate Authority, or the homepage keeper.

**Upload this file into WordPress:**

[luxe-overlap-remover.zip](https://github.com/bigred747/software/raw/refs/heads/cursor/overlap-remover-9272/luxe-overlap-remover.zip)

Size **12KB**. SHA-256 `8986a17b008b5d1e3092f82a5553fa64f90f2dd8f3039b07ff94905e870956c3`

Plugins → Add Plugin → Upload Plugin → Install Now → Activate. Open **Tools → Overlap Remover**.

Before any scan the board reads **8 / 26 processes green**. Those 8 are the safety locks. The 18 plugin rows stay yellow until each folder is gone. After a successful delete the board reads **26 / 26**.

### Safety signals (green as soon as you open the page)

| Signal | What it locks |
|---|---|
| Backup gate | Delete stays off until the Snapshot backup box is checked. |
| Exact folder lock | Only the 18 named folders can match. |
| Protected stack | WooCommerce, WZone, Rank Math, LiteSpeed, Wordfence, Amazon Bridge, Product Scout, Ultimate Authority, and Mirror Master cannot match. |
| Self lock | This remover cannot delete itself. |
| No delete on activate | Activation only stores the board. |
| Deactivate first | Each match is deactivated before its folder is removed. |
| Plugins directory jail | A folder is removed only when its real path stays inside the plugins directory. |
| Public site lock | No public, content, cron, or Amazon URL hooks. |

### Plugin signals

Yellow means still installed. Green means that folder is gone. Red means that one delete failed and the folder was left in place.

1. Luxe Reader-Love Unified
2. Luxe Keyword Intelligence Autopilot
3. Luxe Blog Master Command Center
4. Richard Brummer SEO Mission Control
5. Luxe AI Readiness Autopilot 100
6. Luxe Performance Core Fusion
7. Luxe Performance Link Guardian Suite
8. Luxe Unified Site Guardian
9. Luxe Index Recovery Guard
10. Luxe Rank Math Schema Guard
11. Amazon Affiliate Autopilot
12. Luxe Amazon Correct Tag + Green Signals
13. Luxe Amazon + Mobile Recovery
14. Luxe CRON Mobilizer Pro
15. Action Scheduler Healer PRO
16. Luxe Master Plugin Orchestrator
17. Luxe WOW Homepage Rotator
18. Luxe Category Authority Lock

Hard Rescue, the Optimizer, the Duplicate Cleaner, Safe Trash, WZone Stats, and Luxe Theme are not in this list. Their notices stay until you handle those plugins yourself.

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
