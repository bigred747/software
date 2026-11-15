# Strategies bot

Ten classic **public closed-bar** rules. Each is scored on the selected market's own history. The ranking looks backward — past bars only. Not a forecast.

## Strategies

| Strategy | Style |
|---|---|
| **Edge · five checks** | This bot — squeeze release, TMO-style momentum, four-factor vote, directional strength, higher-span / MTF agreement |
| Squeeze release | Volatility compression |
| 20-day breakout | Range breakout |
| RSI 2 pullback | Short pullback |
| 50/200 average cross | Long trend |
| 20/50 EMA cross | Swing trend |
| MACD cross | Momentum cross |
| Bollinger reversion | Mean reversion |
| EMA trend · ATR trail | Volatility trail |
| 7-day low pullback | Weekly pullback |

None of these is a third-party or trademarked system pack — public closed-bar formulas only.

## Run locally

```bash
cd strategies-bot
python3 -m http.server 8765 --bind 127.0.0.1
```

Open `http://127.0.0.1:8765`

## Install Edge into your bot (Install study)

Your bot’s Install study box only allows **Average, RSI, Highest, Lowest, if/then**.

1. **Name:** `Edge five checks`
2. **Formula:** paste from `thinkscript/INSTALL_STUDY_Edge_Five_Checks.txt` (formula section only)
3. Click **Install study**

Success looks like: `Installed … on this device. 1 plots. No broker order was sent.`

Plot values: `1` buy bias · `-1` sell bias · `0` wait

Same formula also lives in `thinkscript/Edge_Five_Checks_Strategy.ts`.

## Paper Buy / Sell

Buy and Sell ask for a second confirmation, then log a paper ticket for the selected market/strategy. Sample OHLC is used until you wire a live bar feed.
