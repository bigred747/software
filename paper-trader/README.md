# Paper Trader

A local paper desk. The computer copy loads market quotes for 7,500 stocks, ETFs, futures, and crypto names, including Bitcoin, XRP, and Stellar. It shows the quote time, a candle chart, buy and sell arrows, squeeze compression, four indicator readings, and a simulated account.

Orders stay on the paper account. The desk listens only on this computer. It does not place broker orders, connect to an exchange account, or collect payments.

The quote is the market's latest price from the feed, not a generated series and not a forecast. The header shows when that price was quoted. US stocks and ETFs after the cash close show that session's closing price. Crypto and many futures keep updating outside the stock session. If the feed does not answer, the desk shows the error and does not invent a price.

## Run it

Right-click `Paper-Trader.zip`, choose Extract All, and put the folder in Documents. Then two clicks:

1. Double-click `1 Allow Paper Trader.bat`. That clears the download block, puts the newest copy in Documents, removes older Paper Trader folders from Documents, Desktop, and Downloads, and puts **Paper Trader** on the desktop.
2. Double-click **Paper Trader** on the desktop. The desk opens, **Auto** starts, and the **Signals** list turns green for each part that is working. Click **Auto** again to stop.

If a file is opened from inside the zip, this version extracts the whole zip into Documents and opens that copy. The green Signals list shows version 1.9.0 when this copy is running.

From a source checkout, Python 3.11 or newer is enough. The desk uses the Python standard library, so startup does not install packages.

```bash
cd paper-trader
python -m paper_trader serve
```

The browser opens to `http://127.0.0.1:8765/`.

- **Auto** starts with the desk and refreshes the market quote. Click it again to stop. Each fill stays simulated.
- **Trade** places one simulated order on the paper account at the latest quote. It buys when the account is flat and sells when a position is open. It does not send an order to a broker.
- **Paper** turns the paper account on. The button stays pressed, and the desk refuses any other account.
- **Reset session** starts again on the latest quote and applies the strategy settings.
- **Run full backtest** replays the loaded market history without leaving the latest quote.

The catalog has 7,500 markets: stocks, ETFs, futures, and crypto. Bitcoin, XRP, and Stellar are included. Search the list, or tap a pinned name. Bitcoin, Ether, XRP, Stellar, Solana, and SPY start pinned. Pin keeps the market you are on, and Unpin removes it. The phone copy remembers the last crypto you opened.

The ticket shows the paper result against the starting cash, plus how many simulated trades have closed and how many of those made money.

The desk is a wide layout on a computer: markets on the left, the daily chart in the center, the paper ticket on the right, and tools along the bottom. The header shows the symbol, last price, change, and quote time. On a phone the same page uses a bottom bar: Markets, Chart, Trade, and Study. The chart draws the latest 180 daily bars so the current price stays readable. The paper replay still uses the full history.

## Timeframes

Ten timeframes sit under the chart: 1 minute, 5 minute, 15 minute, 30 minute, 1 hour, 4 hour, Daily, Weekly, Monthly, and Quarterly. Click one to open it in its own window, or open all ten at once. Each window loads that timeframe's market bars. The Timeframes panel keeps a written brief and a preview. These bars are not a forecast. The center chart stays on the daily session so the paper arrows stay lined up with the trades.

## Study formulas

Open **Study**, paste a formula, and click **Install study**. The formula runs in the page on the quotes already loaded. It is stored on this device. It cannot place a broker order, open a connection, or read files.

A formula can use `input`, `def`, `plot`, `if/then/else`, bar offsets such as `close[1]`, and math such as `Average`, `ExpAverage`, `RSI`, `ATR`, `Highest`, `Lowest`, `Sum`, and `crosses above`. An `AddOrder` line only paints a paper arrow. Fold, profile, and brokerage calls are refused.

## iPhone

The zip contains `iphone/PaperTrader.html`. Copy that one file to the iPhone, open it from the Files app in Safari, and the desk runs there. There is no app-store install and no broker connection.

The phone file loads live crypto quotes, including Bitcoin, Ether, XRP, Stellar, and Solana. Stocks, ETFs, and futures need the computer copy, because those quotes are loaded by the desk on 127.0.0.1 and the phone file cannot reach that address. If a crypto quote does not load, the phone shows the error and does not invent a price.

## Rules

Long only. A squeeze release with rising momentum opens a simulated position. Momentum turning down closes it. The fast average crossing above the slow average can also open a simulated position when RSI is under 70. A cross back down, RSI at 75 or above, or the stop closes it. The default stop is 8 percent. Set the stop or RSI length to 0 to turn that rule off. The default fee is 5 basis points.

B4 readings are the four numbers under the chart: Squeeze, Momentum, RSI, and Averages. Green arrows are simulated buys. Red arrows are simulated sells. Squares under the candles show squeeze compression.

A **Trade** click fills at the latest quote's close on the paper account.

## Check

```bash
cd paper-trader
python -m unittest discover -s tests -v
python -m paper_trader backtest --symbol BTC-USD
```
