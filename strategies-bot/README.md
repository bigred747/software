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

## Install Edge into Thinkorswim Strategies tab

1. Open `thinkscript/Edge_Five_Checks_Strategy.ts`
2. Thinkorswim → **Studies → Edit Studies → Strategies**
3. **Create...** → paste entire script → name `Edge_Five_Checks`
4. Apply to chart

Or click **Copy Edge ThinkScript for Strategies tab** in the bot UI.

## Paper Buy / Sell

Buy and Sell ask for a second confirmation, then log a paper ticket for the selected market/strategy. Sample OHLC is used until you wire a live bar feed.
