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

Local paper desk in [paper-trader](paper-trader/README.md). The computer copy loads market quotes for 7,500 stocks, ETFs, futures, and crypto, including Bitcoin, XRP, and Stellar. It shows the quote time, squeeze compression, and buy/sell arrows, and keeps a simulated account. **Auto** refreshes the quote. **Trade** places one simulated order at the latest price. **Paper** stays on. It does not place broker orders, collect payments, or send the order to an exchange. If the quote feed does not answer, the desk shows the error and does not invent a price.

The header shows the symbol, last price, change, and quote time. Bitcoin, Ether, XRP, Stellar, Solana, and SPY are pinned. The ticket shows the paper result, closed trades, and wins. Ten timeframes, from 1 minute through quarterly, open in their own windows on the market's bars. US stocks after the cash close show that session's closing price. Crypto and many futures keep updating. This desk is an original layout. It is not a copy of another platform.

**Download the whole bot:**

[Paper-Trader.zip](https://github.com/bigred747/software/raw/refs/heads/cursor/paper-trader-markets-1106/Paper-Trader.zip)

SHA-256 `2fdc08590e76def0a34e3d86390a3ddac915c6bcff8d45026c569fe432bcd018`

Size **11.7 MB**. Python 3.13 for Windows is inside the zip. The phone and tablet page is `iphone/PaperTrader.html` inside the same zip.

**Phone or tablet file:**

[PaperTrader.html](https://github.com/bigred747/software/raw/refs/heads/cursor/paper-trader-markets-1106/paper-trader/iphone/PaperTrader.html)

Save the zip in Downloads. Double-click `Start-Paper-Trader.bat` inside it. Version 1.9.4 extracts the whole zip into Documents and opens that copy in Microsoft Edge. The header says Desk is working. The green signals sit on the chart and show **1.9.4** when this copy is running. Click a candle and that bar's prices appear on the chart.

On a phone or tablet, install Microsoft Edge if it is not already there. Press and hold the HTML link, choose Download Linked File, and rename the file to `.html` if it saved as text. On iPhone or iPad, open the Files app, tap the Share button on `PaperTrader.html`, and tap Edge. On Android, open Files or Downloads, choose Open with, and tap Edge. The page cannot switch to Edge by itself. The mobile copy loads live crypto quotes. Stocks, ETFs, and futures load on the computer copy. Studies stay on the device.
