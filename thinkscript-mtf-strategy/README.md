# ThinkScript + Strategies bot

## What to install in Thinkorswim Strategies tab

Copy/paste this file into **Studies → Edit Studies → Strategies → Create...**:

- `../strategies-bot/thinkscript/Edge_Five_Checks_Strategy.ts`

Or the mirror copy:

- `COPY_PASTE_INTO_STRATEGIES.ts`

Name it `Edge_Five_Checks`.

## Strategies ranking bot (web)

The installable bot UI lives in `../strategies-bot/`:

- Ten classic public closed-bar rules
- **Edge · five checks** = squeeze release + TMO-style + four-factor vote + directional strength + MTF
- Ranked on each market's own history (past bars only, not a forecast)
- Paper Buy / Sell with a second confirmation

```bash
cd ../strategies-bot
python3 -m http.server 8765 --bind 127.0.0.1
```

## Optional chart companions

- `studies/MTF_Combo_Dashboard.ts` — upper labels / paint bars
- `studies/MTF_Combo_Lower.ts` — squeeze histogram + TMO cloud
