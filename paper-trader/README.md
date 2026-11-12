# Paper Trader

A local trading desk that replays built-in sample prices. It shows a Thinkorswim-style paper ticket, a candle chart, a moving-average signal, RSI, a simulated account, and a full-sample backtest.

The desk listens only on this computer (`127.0.0.1`). Other machines cannot open it. It does not place broker orders, connect to an exchange, or collect payments. **Live** stays locked.

Sample prices are a teaching series. They are not live quotes, and the sample result is not a forecast.

## Run it

Right-click `Paper-Trader.zip`, choose Extract All, and put the folder in Documents. Then two clicks:

1. Double-click `1 Allow Paper Trader.bat`. That puts the newest copy in Documents and writes **Paper Trader.bat** on the desktop. It does not run PowerShell, scripts, or Temp installers, so Bitdefender can leave it alone.
2. Double-click **Paper Trader.bat** on the desktop. The desk opens, **Auto** starts, and the **Signals** list turns green for each part that is working. Click **Auto** again to stop.

If a file is opened from inside the zip, this version extracts into `Documents\PaperTrader-setup` and opens that copy. The green Signals list shows version 1.5.0 when this copy is running.

From a source checkout, Python 3.11 or newer is enough. The desk uses the Python standard library, so startup does not install packages and does not wait on a download.

```bash
cd paper-trader
python -m paper_trader serve
```

The browser opens to `http://127.0.0.1:8765/`.

- **Auto** starts with the desk and steps the sample one day at a time. Click it again to stop. Each fill stays simulated.
- **Trade** places one simulated order on the paper account. It buys when the account is flat and sells when a position is open. It does not send an order to a broker.
- **Paper** turns the paper account on. The button stays pressed, and the desk refuses any other account.
- **Live** stays locked. It never connects to a broker.
- The **order ticket** looks like Thinkorswim: Buy / Sell, Market, Limit, Stop, Stop Limit, DAY / GTC, Confirm and Send, Flatten, and working orders. Fills are paper only, against the sample bars.
- **Next day** advances the sample by one session and applies the prior close's signal at the next open.
- **Run remaining days** finishes the sample.
- **Reset session** starts again and applies the strategy settings.
- **Run full backtest** replays the whole sample without moving the session day.

Markets included: Bitcoin, Ether, Euro / US dollar, gold, Apple, plus sample ETF walks for VOO, VTI, VXUS, QQQM, SCHD, SMH, XLK, VGT, QQQ, IVW, SCHG, IWF, VUG, QUAL, and IJH. All of them are sample series, not live quotes.

## Rules

Long only. The fast average crossing above the slow average can open a simulated position when RSI is under 70. A cross back down, RSI at 75 or above, or the stop closes it. The default stop is 8 percent. Set the stop or RSI length to 0 to turn that rule off. The default fee is 5 basis points.

Signals are decided on the completed sample day and filled at the next sample open.

A paper buy uses full cash. A paper sell closes the full simulated position. Working limits and stops wait for the sample bar to trade through the price, then fill the same way.

## Check

```bash
cd paper-trader
python -m unittest discover -s tests -v
python -m paper_trader backtest --symbol BTC-USD
```
