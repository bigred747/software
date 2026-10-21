# Paper Trader

A local trading desk that replays built-in sample prices. It shows a candle chart, buy and sell arrows, a squeeze compression reading, four indicator readings, a simulated account, and a full-sample backtest.

The desk listens only on this computer. It does not place broker orders, connect to an exchange, or collect payments.

Sample prices are a teaching series. They are not live quotes, and the sample result is not a forecast.

## Run it

Right-click `Paper-Trader.zip`, choose Extract All, and put the folder in Documents. Then two clicks:

1. Double-click `1 Allow Paper Trader.bat`. That clears the download block, puts the newest copy in Documents, removes older Paper Trader folders from Documents, Desktop, and Downloads, and puts **Paper Trader** on the desktop.
2. Double-click **Paper Trader** on the desktop. The desk opens, **Auto** starts, and the **Signals** list turns green for each part that is working. Click **Auto** again to stop.

If a file is opened from inside the zip, this version extracts the whole zip into Documents and opens that copy. The green Signals list shows version 1.7.0 when this copy is running.

From a source checkout, Python 3.11 or newer is enough. The desk uses the Python standard library, so startup does not install packages and does not wait on a download.

```bash
cd paper-trader
python -m paper_trader serve
```

The browser opens to `http://127.0.0.1:8765/`.

- **Auto** starts with the desk and steps the sample one day at a time. Click it again to stop. Each fill stays simulated.
- **Trade** places one simulated order on the paper account. It buys when the account is flat and sells when a position is open. It does not send an order to a broker.
- **Paper** turns the paper account on. The button stays pressed, and the desk refuses any other account.
- **Next day** advances the sample by one session and applies the prior close's signal at the next open.
- **Run remaining days** finishes the sample.
- **Reset session** starts again and applies the strategy settings.
- **Run full backtest** replays the whole sample without moving the session day.

The catalog has 7,500 sample markets: stocks, ETFs, futures, and crypto. Bitcoin, XRP, and Stellar are included. Search the list. These series are generated on this computer. They are not live quotes.

The desk is a wide layout on a computer: markets on the left, the daily session chart in the center, the paper ticket on the right, and tools along the bottom. The header shows the sample symbol, last price, and change. On a phone the same page uses a bottom bar: Markets, Chart, Trade, and Study.

## Timeframes

Ten sample timeframes sit under the chart: 1 minute, 5 minute, 15 minute, 30 minute, 1 hour, 4 hour, Daily, Weekly, Monthly, and Quarterly. Click one to open it in its own window, or open all ten at once. The Timeframes panel keeps a written brief and a preview when the browser blocks a window. Intraday bars are built from the daily sample. They are not a live tape, and they are not a forecast. The center chart stays on the daily session so the paper arrows stay lined up with the trades.

## Study formulas

Open **Study**, paste a formula, and click **Install study**. The formula runs in the page on the sample prices. It is stored on this device. It cannot place a broker order, open a connection, or read files.

A formula can use `input`, `def`, `plot`, `if/then/else`, bar offsets such as `close[1]`, and math such as `Average`, `ExpAverage`, `RSI`, `ATR`, `Highest`, `Lowest`, `Sum`, and `crosses above`. An `AddOrder` line only paints a paper arrow. Fold, profile, and brokerage calls are refused.

## iPhone

The zip contains `iphone/PaperTrader.html`. Copy that file to the iPhone, open it from the Files app in Safari, and the same desk runs there. Sample prices and formulas stay on the phone. There is no app-store install and no broker connection. The computer copy still starts with `Start-Paper-Trader.bat` and listens only on 127.0.0.1.

## Rules

Long only. A squeeze release with rising momentum opens a simulated position. Momentum turning down closes it. The fast average crossing above the slow average can also open a simulated position when RSI is under 70. A cross back down, RSI at 75 or above, or the stop closes it. The default stop is 8 percent. Set the stop or RSI length to 0 to turn that rule off. The default fee is 5 basis points.

B4 readings are the four numbers under the chart: Squeeze, Momentum, RSI, and Averages. Green arrows are simulated buys. Red arrows are simulated sells. Squares under the candles show squeeze compression.

Signals are decided on the completed sample day and filled at the next sample open.

## Check

```bash
cd paper-trader
python -m unittest discover -s tests -v
python -m paper_trader backtest --symbol BTC-USD
```
