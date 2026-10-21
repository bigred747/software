# software

WordPress plugins for [luxetrendsetters.com](https://luxetrendsetters.com/).

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

## Paper Trader

Local sample trading desk in [paper-trader](paper-trader/README.md). It replays 7,500 built-in markets (stocks, ETFs, futures, and crypto, including Bitcoin, XRP, and Stellar), shows squeeze compression and buy/sell arrows, and keeps a simulated account. **Auto** steps the sample. **Trade** places one simulated order. **Paper** stays on. It does not place broker orders, collect payments, or connect to an exchange. Prices are samples, not live quotes.

The header shows the sample symbol, last price, and change. Ten sample timeframes (1 minute through quarterly) open in their own windows, with a written brief in the Timeframes panel. Intraday bars are built from the daily sample. They are not a live tape and they are not a forecast. This desk is an original layout. It is not a copy of another platform.

**Download the whole bot:**

[Paper-Trader.zip](https://github.com/bigred747/software/raw/refs/heads/cursor/paper-trader-markets-1106/Paper-Trader.zip)

SHA-256 `2975c16d3494de4c4f5161b529edd817e6883ecc6485285aaa136ae146251c4f`

Size **11.7 MB**. Python 3.13 for Windows is inside the zip. The iPhone page is `iphone/PaperTrader.html` inside the same zip.

Save the zip in Downloads. Double-click `Start-Paper-Trader.bat` inside it. Version 1.7.0 extracts the whole zip into Documents and opens that copy. Signals show **1.7.0** when this copy is running.

On an iPhone, copy `iphone/PaperTrader.html` from the zip into the Files app and open it in Safari. The desk runs in the browser. Studies stay on the device.
