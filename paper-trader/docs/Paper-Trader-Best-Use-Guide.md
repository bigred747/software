# Paper Trader 1.10.10 — Best Use Guide

Rick’s desk. Closed bars only. Paper only. Live locked. Own names only. Not a forecast.

Seal should read **Paper 1.10.10**. Private phone: https://paper-trader.apps.grok.bot  
Computer zip: `Paper-Trader-1.10.10.zip` (or latest `Paper-Trader.zip` from the repo).

---

## 1. Open the desk

### Private phone (iPhone)

1. Stay signed into Cursor (same account that owns the app).
2. Open https://paper-trader.apps.grok.bot
3. Confirm the seal: **Paper 1.10.10**
4. After any Private update: delete the old Home Screen icon, open the live app, then Share → **Add to Home Screen** again. An old icon can keep an older seal.

### Windows zip (computer)

1. Use **Paper-Trader-1.10.10.zip** (Market Forecaster build).
2. Extract → double-click **1 Allow Paper Trader.bat**, then open the desk from the desktop shortcut / **2 Open Paper Trader.bat**.
3. Under Safe / Windows extras, tick **Start the score** if you want learning to keep going after the window closes (while the PC is on and you are signed in).
4. Artex / foreign-bot folders stay out — do not launch from a zip temp fold or another bot’s folder.

---

## 2. Safety (always on)

| Rule | What it means |
| --- | --- |
| **Live locked** | No broker live fills until you choose Live later. Paper only now. |
| **Yes confirm** | Every paper buy/sell asks Yes. Keep that. |
| **Artex** | Foreign bots / temp zip folds do not run this desk. |
| **No invented breadth** | TICK / ADD / TRIN / VOLD stay blank without a real feed. Never invent them. |
| **VIX** | Real ^VIX / .VIX only. Edge needs VIX falling on closed daily bars (fail closed if missing). |
| **Breaker** | Safe check / Signals show what is working. Fix reds before forcing trades. |

Paper fills are simulated. Quotes are market prices. **Not a forecast.**

---

## 3. Layout you want

- **Boxes above, charts below** — scroll; do not cover candles with sticky chrome.
- Top tape: **YM · RUT (RTY) · NQ · ES** — green/red ±%; tap or Tools **Open** to load the chart.
- **Tools** → full desk pages; **Back** on every Tools page.
- Chart **Next pages** (second page): Strategies, Internals, Big 3, Cycle Desk, **Market Forecaster**, Journal, etc.
- Chart stack: price → true momentum (TMO) → squeeze → CCI 50 · 14 · 5. Closed-bar paint only.

---

## 4. Daily best workflow (in order)

Do this in order. Skip a buy when any gate says wait.

### Step A — Market Outlook

1. Read **Day · Week · Month** (SPY closed bars): color, score 1–10, stance.
2. Soft / short-bias day → size down or stay flat. Outlook is guidance, not a lock.

### Step B — Tide · Go Long 5

1. Refresh **Tide**.
2. If **Tide Alert** is on (Sector Drain + Weekly Tide or Index Soft): hold new Edge longs; review **Go Long 5** pressure names only (not a second Fire list).
3. Long-only desk — no short fills.

### Step C — Cycle Desk

1. Refresh **Cycle Desk**.
2. Note stage / top / bottom / cycle-finished (our names only).
3. **Defensive Shield** when the market is soft; **Risk Rebound** (non-defensive) only when the market tanks.
4. Guidance only — Edge still gates buys.

### Step D — Daily scan

1. Leave **Scanner on** so the catalog walk continues (phone: while the tab is open).
2. Use the strong closed-bar list and pins. Do not chase names that never cleared the bar.

### Step E — Fire 5

1. Review up to five green **Fire** names (best Edge longs).
2. Tap a name → chart. Tide on → Fire longs wait; Fire 5 is not Go Long 5.

### Step F — Edge 7/7 (buy gates)

All seven must be green for **Fire** (closed bars):

1. TMO above −10  
2. TMO turns green (main above signal)  
3. Five squeeze dots **or** in a squeeze  
4. Squeeze momentum above zero  
5. CCI 50 · 14 · 5 into +100 on a closed-bar cross  
6. **Desk Waves green / up** (Volume Wave under CCI; fail closed without volume)  
7. **VIX falling** (real VIX only)

Ready = 5/7 · Aim = 6/7 · Fire = 7/7. Fill is the next open. Same gates in Squeeze scan / Fire 5 / Big 3 A+.

### Step G — Best setup Auto

1. Keep **Best setup Auto** on — that is the system.
2. It only allows the strictest paper buys: Edge 7/7, multi-TF lined up, internals supportive, desk Squeeze · TMO · big4indicator · stack · MTF, today’s green scan list, day buy cap.
3. Still asks **Yes**. If it says WAIT, leave Auto on and wait — do not turn Auto off to force a buy.

### Step H — Journal

1. Open **Journal** each day.
2. Buy names: Fire 5 / today’s best (held back under Tide). Sell names: open paper longs with an Edge exit.
3. Review Lane Align · Range Seat · Week Board tags. Tap a product → chart. Auto still asks Yes.

---

## 5. Market Forecaster (Tools · Next pages)

Use after Outlook — it **complements** Outlook; it does not replace it.

1. Open **Market Forecaster** (or Refresh on Markets).
2. It reads **open · pins · scan** names on closed daily bars.
3. For each: **base · bull · bear**, ~3 month / ~1 year horizons, assumptions, SPY compare, what would prove it wrong, paper size / potential-loss hint.
4. Desk score blends Edge · Squeeze · Cycle · strength (our math only).
5. **Not a crystal ball. Not a forecast guarantee.** Edge · Best setup · Auto still gate buys.

---

## 6. When WAIT is correct vs Fire

| You see | Do this |
| --- | --- |
| Edge Ready / Aim / WAIT | Correct — not Fire yet. Keep Auto on. |
| Tide Alert on | Hold new Edge longs. Review Go Long 5 only. |
| Best setup WAIT | Correct — a gate is off (Edge, TF, internals, scan, or day cap). |
| Signals not all green | Expected when Fire is not on. |
| Edge Fire 6/6 + Best setup ready + Yes | Then take the paper buy if you want. |

Waiting is the desk working. Forcing a buy past WAIT is not the best use.

---

## 7. Learning

### Phone / Private web

- Page-open bot: scan + strategies + every Tools / Next page while the tab stays open.
- Leave the tab (or close the Home Screen app) → phone learning pauses.
- Wake lock helps while Auto is on and the tab stays open.

### Windows zip

- Tick **Start the score** for continuous scoring after the desk window closes (PC on, signed in).
- That is true **24/7** after-close learning. Bot-on covers every desk section, including Cycle Desk, Market Forecaster, Outlook, Tools home.

Closed bars only. Learning looks backward. Not a forecast.

---

## 8. Connect / Schwab (optional)

1. Signals / Safe: Schwab or broker connect stays **off** until you choose it.
2. Waiting for keys → Best setup WAIT and Schwab off is expected.
3. Prefer official API paths only — no desktop scrape.
4. **Live stays locked** until you explicitly choose Live later. Until then: paper Yes confirm only.

---

## Quick daily card

1. Open desk → seal **1.10.10**  
2. Outlook → Tide → Cycle → Scan → Fire 5  
3. Chart: Edge 6/6 (TMO · Squeeze · CCI · VIX)  
4. Best setup Auto ON → Yes only when ready  
5. Journal check  
6. Forecaster for scenario review (not a buy lock)  
7. Windows: Start the score for after-close learning  

**Live locked · Paper only · Closed bars · Own names · Not a forecast.**
