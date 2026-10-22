# Paper Trader

A local paper desk. The computer copy loads market quotes for 7,500 stocks, ETFs, futures, and crypto names, including Bitcoin, XRP, and Stellar. It shows the quote time, a candle chart, buy and sell arrows, squeeze compression, four indicator readings, and a simulated account.

Orders stay on the paper account. The desk listens only on this computer. It does not place broker orders, connect to an exchange account, or collect payments.

The quote is the market's latest price from the feed, not a generated series and not a forecast. The header shows when that price was quoted. US stocks and ETFs after the cash close show that session's closing price. Crypto and many futures keep updating outside the stock session. If the feed does not answer, the desk shows the error and does not invent a price.

## Run it

Right-click `Paper-Trader.zip`, choose Extract All, and put the folder in Documents. Then two clicks:

1. Double-click `1 Allow Paper Trader.bat`. That clears the download block, puts the newest copy in Documents, removes older Paper Trader folders from Documents, Desktop, and Downloads, and puts **Paper Trader** on the desktop.
2. Double-click **Paper Trader** on the desktop. The desk opens, **Auto** starts, and the **Signals** list turns green for each part that is working. Click **Auto** again to stop.

If a file is opened from inside the zip, version 1.9.16 looks for Paper-Trader.zip in Downloads, Desktop, Documents, and OneDrive, makes the folders in Documents first, then extracts the whole zip and opens a window named Paper Trader. It also copies PaperTrader.html into Documents\iPhone and opens that folder. If Windows shows an Unblock box, check Unblock and open the file. That box lets this copy open. Leave that window open so the desk stays on the screen. Microsoft Edge opens when the desk answers. The header says Desk is working when the page has started. The green signals sit on the chart and show version 1.9.16 when this copy is running. Learning, Fear and greed, and Zoom each have their own green dot. The header clock is a pie of that score, with an arrow pointing at the number. Candles are green or red. The chart shows the high, close, and low averages, momentum, squeeze dots, resistance and support, and a true-momentum study under the price. The score is its own process. Closing the Paper Trader window does not stop it. On Windows a normal window named Paper Trader Learn starts the score again each time you sign in, and it keeps scoring while the computer is on and you are signed in. It does not run while the computer is off. The desk does not hide that window and does not change Windows security settings. A feed error stays on the Learning signal. The setup window can close. When the older folder is still locked, the new copy opens from Documents\PaperTrader-setup anyway. Click a candle and that bar's prices appear on the chart. Zoom in and Zoom out change how many bars a chart shows.

From a source checkout, Python 3.11 or newer is enough. The desk uses the Python standard library, so startup does not install packages.

```bash
cd paper-trader
python -m paper_trader serve
```

Microsoft Edge opens to `http://127.0.0.1:8765/`.

- **Auto** starts with the desk and refreshes the market quote. Click it again to stop. Each fill stays simulated.
- **Trade** places one simulated order on the paper account at the latest quote. It buys when the account is flat and sells when a position is open. It does not send an order to a broker.
- **Paper** turns the paper account on. The button stays pressed, and the desk refuses any other account.
- **Reset session** starts again on the latest quote and applies the strategy settings.
- **Run full backtest** replays the loaded market history without leaving the latest quote.

The catalog has 7,500 markets: stocks, ETFs, futures, and crypto. Bitcoin, XRP, and Stellar are included. Search the list, or tap a pinned name. Bitcoin, Ether, XRP, Stellar, Solana, and SPY start pinned. Pin keeps the market you are on, and Unpin removes it. The mobile copy remembers the last crypto you opened.

The ticket shows the paper result against the starting cash, plus how many simulated trades have closed and how many of those made money.

The desk is a wide layout on a computer: markets on the left, the daily chart in the center, the paper ticket on the right, and tools along the bottom. The header shows the symbol, last price, change, and quote time. On a phone the same page uses a bottom bar: Markets, Chart, Trade, and Study. The chart draws the latest 180 daily bars so the current price stays readable. The paper replay still uses the full history.

## Timeframes

Ten timeframes sit under the chart: 1 minute, 5 minute, 15 minute, 30 minute, 1 hour, 4 hour, Daily, Weekly, Monthly, and Quarterly. Click one to open it in its own window, or open all ten at once. Each window loads that timeframe's market bars. The Timeframes panel keeps a written brief and a preview. These bars are not a forecast. The center chart stays on the daily session so the paper arrows stay lined up with the trades.

## Study formulas

Open **Study**, paste a formula, and click **Install study**. The formula runs in the page on the quotes already loaded. It is stored on this device. It cannot place a broker order, open a connection, or read files.

A formula can use `input`, `def`, `plot`, `if/then/else`, bar offsets such as `close[1]`, and math such as `Average`, `ExpAverage`, `RSI`, `ATR`, `Highest`, `Lowest`, `Sum`, and `crosses above`. An `AddOrder` line only paints a paper arrow. Fold, profile, and brokerage calls are refused.

## Phone and tablet

The zip contains `iphone/PaperTrader.html`. Opening the computer copy also puts that file in `Documents\iPhone` and opens that folder. That one file is the mobile copy for an iPhone, iPad, Android phone, or other tablet. Install Microsoft Edge from the App Store or the Play Store if it is not already on the device. Copy `Documents\iPhone\PaperTrader.html` onto the phone. On iPhone or iPad, open the Files app, tap the Share button on `PaperTrader.html`, and tap Edge. On Android, open Files or Downloads, choose Open with, and tap Edge. The page cannot switch to Edge by itself. There is no app-store install of this desk and no broker connection.

The mobile file loads live crypto quotes, including Bitcoin, Ether, XRP, Stellar, and Solana. Stocks, ETFs, and futures need the computer copy, because those quotes are loaded by the desk on 127.0.0.1 and the mobile file cannot reach that address. If a crypto quote does not load, the page shows the error and does not invent a price.

## Rules

Long only. A squeeze release with rising momentum opens a simulated position. Momentum turning down closes it. The fast average crossing above the slow average can also open a simulated position when RSI is under 70. A cross back down, RSI at 75 or above, or the stop closes it. The default stop is 8 percent. Set the stop or RSI length to 0 to turn that rule off. The default fee is 5 basis points.

Candles are green when the close is at or above the open, and red when it is below. The chart draws the high, close, and low averages. A red line marks resistance and a green line marks support, with a confirmation line and a dashed risk-off line beside each. Momentum is the lower panel. Dots show squeeze compression. B4 readings list the candle color, squeeze, momentum, price versus those averages, RSI, the fast and slow averages, the pivot prices, true momentum, the lesson, and fear or greed. Green arrows touching a candle are simulated buys. Red arrows touching a candle are simulated sells. Arrows farther from the candle are the lesson: the study setting that matched the next close most often on bars already printed. That score is not a forecast, and it does not place an order. Fear, neutral, or greed says where the last close sits in the recent range of that product. Each market you open gets its own score. The computer scores a book of markets, including the one on the chart, all day and all week while the computer is on and you are signed in to Windows. Closing the desk window does not stop that score, and signing in starts it again. It does not run while the computer is off. Zoom in shows fewer bars. Zoom out shows more. The price chart and the study under it zoom together. Each timeframe window has its own zoom.

A **Trade** click fills at the latest quote's close on the paper account.

## Check

```bash
cd paper-trader
python -m unittest discover -s tests -v
python -m paper_trader backtest --symbol BTC-USD
```
