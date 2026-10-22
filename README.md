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

SHA-256 `e53a3896a8a021992d1c675bda711c9fb68c10560b89af0699f422ab2cdf26f0`

Size **11.7 MB**. Python 3.13 for Windows is inside the zip. The phone and tablet page is `iphone/PaperTrader.html` inside the same zip. Opening the computer copy also puts that file in Documents\iPhone and opens that folder.

**Phone or tablet file:**

[PaperTrader.html](https://github.com/bigred747/software/raw/refs/heads/cursor/paper-trader-markets-1106/paper-trader/iphone/PaperTrader.html)

On iPhone, press and hold that link, choose Download Linked File, and rename it to `PaperTrader.html` if the name ends in .txt. Open Files, tap Share, then Edge. Opening the link in the browser leaves the chart, the fear and greed clock, and the scanner off. Once the file is open in Edge, those three load live crypto. The phone file carries the crypto list so the page can start, and a quote that does not answer is written on the chart. A copied folder loads the chart files beside the page. Stocks and the full catalog scan stay on the computer.

Save the zip in Downloads. Double-click `Start-Paper-Trader.bat` inside it. If Windows shows an Unblock box, check Unblock and open the file. That box lets this copy open. Version 1.9.22 looks in Downloads, Desktop, Documents, and OneDrive, makes the folders in Documents first, then extracts the copy and opens a window named Paper Trader. It copies PaperTrader.html into Documents\iPhone and opens that folder. It puts Paper Trader.bat on the desktop. Double-click that file to open the desk. When this copy is open, older Paper Trader folders are removed, so Install says the newest copy only. The chart leaves open space on the right of the last candle. Voice is on. It speaks a buy or sell warning when five daily squeeze dots print, before the release, and it speaks sell when that squeeze runs out of steam. Other buy and sell marks stay quiet. Trade stays on the paper account. A new buy is allowed only for a market in today's five: a daily buy setup and a closed-bar score of at least 60 percent on 20 bars, at most five markets, one new buy per market per day. A sell is always allowed. That number is the past match rate. It is not a forecast, and this desk does not connect to a broker. The Daily scan walks every market in the catalog once each Denver day while the computer is on and you are signed in, and lists up to 12 names that cleared that bar. A missed quote is skipped. The list does not invent a price. SMH, VGT, ARKW, QQQ, SPMO, COPX, ARKQ, MGK, CIBR, UGA, AIRR, and GRID stay on the watch list too. The score is already its own process: closing that window does not stop it. A normal window named Paper Trader Learn starts the score again when you sign in to Windows, and it keeps scoring while the computer is on and you are signed in. It does not run while the computer is off. This copy does not run PowerShell, does not hide a window, and does not change Windows security settings. Microsoft Edge opens when the desk answers. The header says Desk is working. The green signals sit on the chart and show **1.9.22** when this copy is running. Today's five sit under the pinned markets. Learning, Fear and greed, and Zoom each have a green dot. Learning says all day, all week while the score is running. A clock in the header is the fear or greed pie, and its arrow points at the number. Candles are green or red. Resistance, support, the averages, momentum, squeeze dots, the true-momentum study, and the lesson arrows are on the chart. Zoom in and Zoom out change how many bars a chart shows. The setup window can close. Click a candle and that bar's prices appear on the chart. Download this zip.

On a phone or tablet, install Microsoft Edge if it is not already there. Press and hold the HTML link, choose Download Linked File, and rename the file to `.html` if it saved as text. On iPhone or iPad, open the Files app, tap the Share button on `PaperTrader.html`, and tap Edge. On Android, open Files or Downloads, choose Open with, and tap Edge. The page cannot switch to Edge by itself. The mobile copy loads live crypto quotes. Stocks, ETFs, and futures load on the computer copy. Studies stay on the device.
