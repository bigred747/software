# Paper Trader

A local trading desk that replays built-in sample prices. It shows a candle chart, a moving-average signal, RSI, a simulated account, and a full-sample backtest.

The desk listens only on this computer. It does not place broker orders, connect to an exchange, or collect payments.

Sample prices are a teaching series. They are not live quotes, and the sample result is not a forecast.

## Run it

Install Python 3.11 or newer, then:

```bash
cd paper-trader
python -m paper_trader serve
```

On Windows, double-click `Start-Paper-Trader.bat`.

The browser opens to `http://127.0.0.1:8765/`.

- **Next day** advances the sample by one session and applies the prior close's signal at the next open.
- **Run remaining days** finishes the sample.
- **Reset session** starts again and applies the strategy settings.
- **Run full backtest** replays the whole sample without moving the session day.

Markets included: Bitcoin, Ether, Euro / US dollar, gold, and Apple. All five are sample series.

## Rules

Long only. The fast average crossing above the slow average can open a simulated position when RSI is under 70. A cross back down, RSI at 75 or above, or the stop closes it. The default stop is 8 percent. Set the stop or RSI length to 0 to turn that rule off. The default fee is 5 basis points.

Signals are decided on the completed sample day and filled at the next sample open.

## Check

```bash
cd paper-trader
python -m unittest discover -s tests -v
python -m paper_trader backtest --symbol BTC-USD
```
