# Paper Trader

A local paper desk. The computer copy loads market quotes for 7,500 stocks, ETFs, futures, and crypto names, including Bitcoin, XRP, and Stellar. It shows the quote time, a candle chart, buy and sell arrows, squeeze compression, four indicator readings, and a simulated account.

Orders stay on the paper account. The desk listens only on this computer. It does not place broker orders, connect to an exchange account, or collect payments.

The quote is the market's latest price from the feed, not a generated series and not a forecast. The header shows when that price was quoted. US stocks and ETFs after the cash close show that session's closing price. Crypto and many futures keep updating outside the stock session. If the feed does not answer, the desk shows the error and does not invent a price.

## Run it

Right-click `Paper-Trader.zip`, choose Extract All, and put the folder in Documents. Then two clicks:

1. Double-click `1 Allow Paper Trader.bat`. That clears the download block, puts the newest copy in Documents, leaves any older Paper Trader folders where they are (the Safe check lists them so you can delete them yourself), and puts **Paper Trader** on the desktop.
2. Double-click **Paper Trader** on the desktop. The desk opens, **Auto** starts, and the **Signals** list turns green for each part that is working. Click **Auto** again to stop.

If a file is opened from inside the zip, version 1.9.38 looks for Paper-Trader.zip in Downloads, Desktop, Documents, and OneDrive, makes the folders in Documents first, then extracts the whole zip and opens a window named Paper Trader. It puts Paper Trader.bat on the desktop, including the OneDrive desktop. It also copies PaperTrader.html into Documents\iPhone and opens that folder. Older Paper Trader folders are never deleted for you. The chart leaves open space on the right of the last candle and shows the last price there. Click a candle to drop a vertical price line on that bar; the close is marked on the right. Voice speaks only for the daily squeeze: a buy or sell warning when five squeeze dots print, before the release, and sell when that momentum turns down. This copy does not run PowerShell, does not hide a window, and does not change Windows security settings. If Windows shows an Unblock box, check Unblock and open the file. That box lets this copy open. Leave that window open so the desk stays on the screen. Microsoft Edge opens when the desk answers. The header says Desk is working when the page has started. The green signals sit on the chart and show version 1.9.38 when this copy is running. The Daily scan section lists the strongest closed-bar scores from the full catalog, and today's three to five best names sit under that list. Scanner on walks the catalog. Scanner off pauses that walk. The chart quote and the Paper Trader Learn window keep going, and names already found stay listed. A new paper buy has to be one of those names, and the day stops at five new buys. On iPhone the same page scans live crypto into that section. Opening the GitHub link in the browser leaves the charts off. Download the file, open Files, tap Share, then Edge. SMH, VGT, ARKW, QQQ, SPMO, COPX, ARKQ, MGK, CIBR, UGA, AIRR, and GRID stay on the watch list with the other pinned markets. Learning, Fear and greed, and Zoom each have their own green dot. The header clock is a pie of that score, with an arrow pointing at the number. Candles are green or red. The chart shows the high, close, and low averages, momentum, squeeze dots, resistance and support, and a true-momentum study under the price. The score is its own process. Closing the Paper Trader window does not stop it. Starting that window at sign-in is optional: tick Start the score when I sign in to Windows under Windows extras in the Safe check, and untick it to remove the file. It is off until you tick it. The score keeps scoring while the computer is on and you are signed in. It does not run while the computer is off. While that window is open on wall power, it asks Windows not to sleep, so scoring keeps going all day and all week. The screen can still turn off, and the lock screen still works. On battery, Windows can still sleep. Closing the window ends that request. The desk does not hide that window and does not change Windows security settings. A feed error stays on the Learning signal. The setup window can close. When the older folder is still locked, the new copy opens from Documents\PaperTrader-setup anyway. Click a candle and that bar's prices appear on the chart. Zoom in and Zoom out change how many bars a chart shows.

From a source checkout, Python 3.11 or newer is enough. The desk uses the Python standard library, so startup does not install packages.

```bash
cd paper-trader
python -m paper_trader serve
```

Microsoft Edge opens to `http://127.0.0.1:8765/`.

- **Auto** starts with the desk and refreshes the market quote. Click it again to stop. Each fill stays simulated.
- **Trade** places one simulated order on the paper account at the latest quote. A new buy is allowed only after the scan finishes, for a name in the green list: the ten highest closed-bar scores narrowed to three to five. A sixth new buy is refused. One new buy per market per day. A sell is always allowed. That score is the past match rate. It is not a forecast. It does not send an order to a broker.
- **Daily scan** walks every market in the catalog once each Denver day, while this computer is on and you are signed in. The Daily scan section lists ten markets that cleared that same closed-bar bar, then narrows those ten to three to five. A green signal lights on each of those names when the scan finishes. It does not invent a price when a quote fails. The list is not a forecast. **Scanner on** walks the catalog. **Scanner off** pauses that walk. The chart quote and the Paper Trader Learn window keep going, and names already found stay listed.
- **Paper** turns the paper account on. The button stays pressed, and the desk refuses any other account.
- **Fills** work like a live market order. Trade and Sell all fill at the latest real trade from the quote feed at the moment you click, moved 5 basis points against you for slippage, plus the fee. That price is locked into the paper record, so it does not drift as the day's quote keeps moving. The status line and the ticket show the fill price, the last trade, the slippage, the fee, and the time in Denver time. The paper account has no margin and no shorting: a buy uses the paper cash, and a sell closes the whole position.
- **Live** is locked. This desk has no broker or bank link, so it cannot connect to an institution and every order stays paper. Clicking Live says so and changes nothing. To trade real money, use your broker's own app.
- **Sell all · Stop buys** is the circuit breaker. One click sells the open paper position at the latest quote, turns Auto off, and refuses new paper buys, including strategy buys, until the next Denver day. Sells still work. The breaker also trips by itself when paper equity falls 5% from where it started that Denver day. The Trade panel shows when it tripped and why, and the Signals list shows Breaker as armed or tripped. It works even while a scan or a quote refresh is running.
- **Reset session** starts again on the latest quote and applies the strategy settings.
- **Run full backtest** replays the loaded market history without leaving the latest quote.

The catalog has 7,500 markets: stocks, ETFs, futures, and crypto. Bitcoin, XRP, and Stellar are included. Search the list, or tap a pinned name. Bitcoin, Ether, XRP, Stellar, Solana, and SPY start pinned. Pin keeps the market you are on, and Unpin removes it. The mobile copy remembers the last crypto you opened.

The ticket shows the paper result against the starting cash, plus how many simulated trades have closed and how many of those made money.

The desk is a wide layout on a computer: markets on the left, the daily chart in the center, the paper ticket on the right, and tools along the bottom. The header shows a Paper mark, the symbol, last price, change, and quote time. On an iPhone 14 Plus the same page fits one screen: the price, the change, and the fear clock each keep their own place, and the chart fills the space above the tabs. One line on the phone chart says whether every check is green, and tapping it opens the full Signals list. The phone scan reads three markets at a time, with a short pause after each one, and the ten, the three to five, and the green signals follow the same rule. The chart key stays closed until it is opened. A quote refresh leaves a list alone when that list has not changed. On a phone the same page uses a bottom bar: Markets, Chart, Trade, and Study. The chart draws the latest 180 daily bars so the current price stays readable. The paper replay still uses the full history.

## Edge

Edge is the desk's buy and sell indicator. It joins the 5-dot squeeze, true momentum (14, 5, 3), and a Bollinger filter. Edge buys at the next bar's open when five checks turn on together on a closed bar: five or more squeeze dots, squeeze momentum above zero, squeeze momentum rising, the true momentum main line above its signal line, and the close under the upper Bollinger band (20 days, two deviations). The starting stop is two average ranges under that open. While it holds, the stop trails two average ranges under the best close and never moves down. A close under that stop, or squeeze momentum dropping below zero, sells at the next open.

These settings were tested on two years of real daily bars with 10 basis points each way for the fee and slippage. On BTC, ETH, XRP, XLM, SOL, SPY, QQQ, AAPL, NVDA, MSFT, TSLA, and ES, Edge averaged +14.3% while buy-and-hold averaged −2.2%, and it was positive on 10 of those 12. On 20 other markets that were not used to pick the settings, Edge averaged +28.1% while buy-and-hold averaged +19.3%, and it was positive on 17 of those 20. About one trade in three made money, so most of the gain came from a few large wins. The worst drop averaged about 22% to 25%. Adding the Bollinger check lifted those two sets from +10.2% and +27.1%. A 200-day EMA filter hurt. MACD, VWAP, and POC filters were mixed. ATR profit targets and extra volatility filters added noise. Changing the dots to 4 or 6, or the true momentum length to 10 or 21, made no reliable difference, so 5 dots and true momentum 14 stay. That is the past. It is not a forecast.

The Trade panel shows the call (Buy, Hold, Sell, or Wait), which of the five checks are on, the stop and how far price is from it, a 1% risk size from ATR, and the record on this market's past bars: Edge's return, buy-and-hold over the same bars, the worst drop, the number of trades, the share that made money, and the time in the market. Signals use closed bars only. Today's forming bar can still print on the chart, but it does not change the Edge score, the lesson, or the arrows. The chart's clean view draws the candles, paper trades, the Edge stop, the 200-day EMA, and the volume profile. Layers turns VWAP, Bollinger bands, averages, pivots, and lesson arrows on or off. The true momentum panel marks only zero-line crosses after a bar closes. On the phone each Daily scan name also shows its Edge call. B4 readings add the 200 and 50 EMAs, VWAP, POC, MACD, RSI 14, Bollinger, yearly volatility, and ATR size. Edge does not place an order. Trade and Auto keep their own rules.

## Hidden tape

Dark pool books, iceberg orders, and institutional blotters are private. This desk cannot see resting hidden orders and does not invent dark-pool prints.

B4 readings show public-tape proxies from the same real bars and quote the page already loads. Relative volume is the last closed bar versus the prior 20 closed days. Absorption is a high-volume bar whose range stayed tight; initiative is a high-volume bar that traveled. Tape pressure is where that close sat in the bar, plus up versus down volume over the last 20 closed days. Stocks and ETFs on the phone also show the quote feed's 10-day relative volume and after-hours volume when that feed publishes them. Crypto can show the visible bid and ask from the same exchange ticker. A missing field stays blank. These lines do not paint arrows and are not a forecast.

## Timeframes

Ten timeframes sit under the chart: 1 minute, 5 minute, 15 minute, 30 minute, 1 hour, 4 hour, Daily, Weekly, Monthly, and Quarterly. Click one to open it in its own window, or open all ten at once. Each window loads that timeframe's market bars. The Timeframes panel keeps a written brief and a preview. These bars are not a forecast. The center chart stays on the daily session so the paper arrows stay lined up with the trades.

## Study formulas

Open **Study**, paste a formula, and click **Install study**. The formula runs in the page on the quotes already loaded. It is stored on this device. It cannot place a broker order, open a connection, or read files.

A formula can use `input`, `def`, `plot`, `if/then/else`, bar offsets such as `close[1]`, and math such as `Average`, `ExpAverage`, `RSI`, `ATR`, `Highest`, `Lowest`, `Sum`, and `crosses above`. An `AddOrder` line only paints a paper arrow. Fold, profile, and brokerage calls are refused.

## Phone and tablet

The zip contains `iphone/PaperTrader.html`. Opening the computer copy also puts that file in `Documents\iPhone` and opens that folder. That one file is the mobile copy for an iPhone, iPad, Android phone, or other tablet. Install Microsoft Edge from the App Store or the Play Store if it is not already on the device. Copy `Documents\iPhone\PaperTrader.html` onto the phone. On iPhone or iPad, open the Files app, tap the Share button on `PaperTrader.html`, and tap Edge. On Android, open Files or Downloads, choose Open with, and tap Edge. The page cannot switch to Edge by itself. There is no app-store install of this desk and no broker connection.

The mobile file loads live quotes for crypto, stocks, ETFs, and futures, including Bitcoin, Ether, XRP, Stellar, Solana, AAPL, SPY, and ES. Crypto uses the exchange's latest trade. Stocks, ETFs, and futures use the public quote feed's latest trade, including a later after-hours trade when that feed publishes one. The time under the price is when that trade printed. Auto refreshes it. The Type menu stays on the market you pick, and that choice is the list and the scan. Version 1.9.38 asks for that quote the plain way the iPhone allows. That file carries the full catalog so the chart, the fear and greed clock, and the Daily scan can start. Scanner off pauses the phone's walk and keeps names already found. If one live crypto quote does not answer, the page asks the next live crypto feed. If a quote still does not answer, the error is written on the chart and the page does not invent a price. A copied folder loads the chart files that sit beside the page. The computer copy still walks the full catalog through its own quote feed on 127.0.0.1.

## Rules

Long only. A squeeze release with rising momentum opens a simulated position. Momentum turning down closes it. The fast average crossing above the slow average can also open a simulated position when RSI is under 70. A cross back down, RSI at 75 or above, or the stop closes it. The default stop is 8 percent. Set the stop or RSI length to 0 to turn that rule off. The default fee is 5 basis points.

Candles are green when the close is at or above the open, and red when it is below. The chart draws the high, close, and low averages. A red line marks resistance and a green line marks support, with a confirmation line and a dashed risk-off line beside each. Momentum is the lower panel. Dots show squeeze compression. B4 readings list the candle color, squeeze, momentum, price versus those averages, RSI, the fast and slow averages, the pivot prices, true momentum, the lesson, and fear or greed. Green arrows touching a candle are simulated buys. Red arrows touching a candle are simulated sells. Arrows farther from the candle are the lesson: the study setting that matched the next close most often on bars already printed. That score is not a forecast, and it does not place an order. Fear, neutral, or greed says where the last close sits in the recent range of that product. Each market you open gets its own score. The computer scores a book of markets, including the one on the chart, all day and all week while the computer is on and you are signed in to Windows. Closing the desk window does not stop that score, and signing in starts it again. It does not run while the computer is off. While that window is open on wall power, it asks Windows not to sleep, so scoring keeps going all day and all week. The screen can still turn off, and the lock screen still works. On battery, Windows can still sleep. Closing the window ends that request. Zoom in shows fewer bars. Zoom out shows more. The price chart and the study under it zoom together. Each timeframe window has its own zoom.

A **Trade** click fills at the latest quote's close on the paper account.

## Order ticket and local-only lock

Version 1.9.38 adds a broker-style paper order ticket under the Trade buttons: **Buy / Sell**, **Market, Limit, Stop, Stop Limit**, **DAY / GTC**, a **Confirm and Send** box, a **Working orders** table with Cancel and Cancel all, and a price you can type for each leg. Everything is paper.

- A buy uses all paper cash. A sell closes the whole paper position. The ticket has no size box on purpose, because the paper account is one position at a time.
- Market orders fill at the latest real trade plus 5 bps of slippage and the fee, like before.
- A limit waits until the real quote reaches it and never fills worse than its limit.
- A stop becomes a market order when the real quote crosses it. A buy stop must sit above the last price and a sell stop below it, so a stop never fires the moment you place it.
- A stop limit waits for its stop, then follows its limit.
- Working orders are checked on every quote refresh while Auto is on, and when you press Next day. DAY orders expire at the next Denver day. GTC orders stay until filled or canceled.
- **Sell all · Stop buys** and the 5% daily breaker cancel every working order, and the breaker refuses ticket buys until tomorrow. Sells still work.
- **Live** stays locked. No order leaves this computer.

The ticket buys on your own call. The Trade button still follows the scanner's green list.

The desk only answers programs on this computer. It refuses any client that is not 127.0.0.1, any Host that is not 127.0.0.1 or localhost, and any Origin or Referer from another site, so a web page you visit cannot place orders. The launcher files do not run PowerShell, VBS, or Temp installers.

## Edge on every timeframe and the price box (1.9.41)

**Edge reads all ten timeframes.** Under the Edge box, a row of chips shows Edge's call on 1m, 5m, 15m, 30m, 1h, 4h, daily, weekly, monthly, and quarterly, each read from that timeframe's own closed bars. The Timeframes tab has the same list as a table with checks, stop, past trades, win rate, and result. Click a row to put that timeframe in the preview. A timeframe with fewer than 52 bars shows a dash instead of a guess. The summary line says how many timeframes are buying or holding, selling, or waiting. The second-warning window lists that count as a line-up item. Auto and the strategies still read the daily bars only. The preview and every timeframe window also draw the Edge stop line and Edge buy and sell dots, and say Edge's current call.

**Price box.** Click any candle on the main chart, the lower study, the Timeframes preview, or any timeframe window and a box shows the date and time, open, high, low, close, change from the bar before, range, volume, and how many of Edge's five checks were on for that bar and which were missing. A dashed line marks the bar. Close the box with the x or Escape.

## Second warning and strategy testing (1.9.40)

**Every buy and sell asks twice.** Pressing Trade, Send on the ticket, Sell all, or arming an opening-bar order opens a window that says what will happen, the market, the latest quote, your paper cash and shares, who started it, and a line-up list: Paper only, Safe check, Circuit breaker (buys), Internals (buys), and whether the chosen strategy agrees. A STOP line (paper is off, the Safe check failed, the breaker is on) disables the Yes button. No is the default, and Escape or a click outside also cancels. The desk itself refuses a buy or sell that does not carry the confirmation, so nothing, not even a script, can trade without it.

**Auto signals ask too.** When the strategy you picked gives a fresh buy or sell on a closed bar, the same window opens and nothing trades until you press Yes. Pressing No skips that signal for good. When you press Yes the desk checks the signal is still on, and if it is not, nothing trades. Working limit and stop orders were approved when you placed them, and that OK covers their later fill. The circuit breaker is the one automatic sell kept on purpose: a 5% daily paper drop sells everything to cash and stops buys until tomorrow.

**Click a strategy to test it.** In the Strategies tab, click any strategy row. A panel shows that strategy's own past trades on the market you are on (dates, entry, exit, result, why it closed). Press Run on the list to run it on a list of markets from the starter, sector, crypto, or futures lists, ranked by result, and click a market in that list to open it. It reads real daily bars and places nothing. These are past closed bars, not a forecast.

## Quiet installer (1.9.39)

Security programs watch for programs that end other programs, delete folders they did not make, or write sign-in files without asking. This copy does none of those. The installer never ends another program and never deletes another folder. When a Paper Trader Learn window from an older copy is open, it leaves a small request file and that window closes itself. Other copies are listed in the Safe check as a note. The sign-in file and the Desktop file made by the desk are off until you tick them under Windows extras in the Safe check, and each can be removed by unticking. The Safe check item Quiet installer scans this program's own files for calls that end programs, edit the registry, or schedule tasks.

## Journal, Safe check, Strategies, and Squeeze scan (1.9.39)

**Journal.** Every market keeps its own paper account. Switching markets no longer drops an open position. The Journal tab now lists **Open positions** across every market, **Every fill** with its Denver time and why it happened (your trade, a ticket, a working order, Sell all, or Auto), and **Closed trades**. On the computer the book is saved next to the journal. On the phone page it is saved in that browser's storage. Press **Reset session** to wipe only the market you are on. On the computer, a position the history replay opened is labeled "History replay" instead of "Your fill or Auto".

**Safe check.** The green button under Sell all runs these safety checks, and again every minute: the desk listens on 127.0.0.1 only, other computers and other web pages are refused, Live is locked, quotes come from the one allowed feed host, the circuit breaker is readable, the order rules pass a built-in test (limit, stop, market, and a buy then sell round trip), the journal can write and read back, the program files are the same as when it opened, the launcher files contain nothing that runs scripts or changes Defender, and only one copy is installed. Any failed safety item turns the button red and stops Auto. Quote and learning status only turn it amber. Green means those checks passed at that moment. It does not mean a market will go up.

**Strategies tab.** Four strategies read closed bars only and are scored on the market you are on: Edge, a squeeze release (Bollinger Bands back outside Keltner Channels with momentum rising, stop under that bar), a 20-bar range breakout, and a short RSI pullback in an uptrend. The table shows what each says now, its past trades, win rate, result against holding, and its worst trade, and marks the best past result once a strategy has five trades. That ranking looks backward and picking the winner after the fact flatters it. **Auto follows** lets Auto place paper trades from the strategy you pick, once per closed-bar signal, through the same scanner rules and circuit breaker. A 15-minute opening bar panel reads the real 15-minute bars, shows the buy stop above the first bar's high, the protective stop at its low, and a target, and can arm those as working paper orders. This desk is long only, so a break below the low is shown as a bearish break and no short is opened.

**Squeeze scan tab.** Reads real daily bars for a list (starter list, sector funds and big holdings, crypto, futures, or your own) and ranks the squeezes. Points go to a squeeze that is on or just fired, more bars of built-up energy, a tight squeeze, moving averages (8, 21, 50) stacked with momentum, a clean squeeze where momentum never crossed zero, momentum building, a weekly squeeze too, and the same squeeze in a stock, its sector fund, and its index fund. Names that are not in this bot's market list are skipped and listed, never guessed. Each row opens that market.

**Internals tab.** Reads the fear gauges (VIX, 9-day VIX, VXN), SPY and QQQ trend, how many of the ten sector funds sit above their 21-day average, equal weight against cap weight, small against large, offense against defense, credit (HYG against IEF), the 10-year yield, and the dollar, all from real closed daily bars, and rolls them into Supportive, Mixed, or Defensive with the reason for every point. A pill under the Trade buttons shows it everywhere. **Respect internals** (on by default) makes Auto skip new buys in stocks, ETFs, and futures while internals are Defensive. Sells are never blocked, crypto is not gated, and a gauge that does not answer is listed as not read, never guessed. NYSE TICK, ADD, TRIN, and VOLD are not included because the free feeds do not carry them. The Safe check lists internals as a health item.

**Where this comes from.** All code and wording here is original. The math is general and public: Bollinger Bands, Keltner Channels, moving averages, RSI, price ranges, and opening ranges. No vendor's indicator code, scripts, scoring, text, logos, or product names were copied or used, and this project has no affiliation with any trading education company or broker. The point values in the scan are this project's own choices, not anyone else's formula.

Markets: all 7,510 from the catalog (crypto including XRP and Stellar, stocks, ETFs, and futures). Added in 1.9.38: VXUS, QQQM, SCHD, SCHG, VUG, and QUAL, so the core ETF list (VOO, VTI, VXUS, QQQM, SCHD) and the growth list (SMH, XLK, VGT, QQQ, IVW, SCHG, IWF, VUG, QUAL, IJH) are all searchable.

## Check

```bash
cd paper-trader
python -m unittest discover -s tests -v
python -m paper_trader backtest --symbol BTC-USD
```
