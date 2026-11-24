"""Closed-bar strategy snapshots for the background learner.

Paper only. Looks backward on bars that have already printed. Not a forecast.
Used while the bot score process is on, even when desk pages are closed.
"""

from __future__ import annotations

from paper_trader.indicators import rsi, sma, squeeze
from paper_trader.model import Bar

# Builders registered here are scored by the bot-on learner for every watched market.
# Ship a new classic strategy by appending with register_strategy(id, name, build_fn).
_STRATEGY_BUILDERS: list[tuple[str, str, object]] = []


def register_strategy(strategy_id: str, name: str, build) -> None:
    """Register a closed-bar strategy for bot-on learning. Idempotent by id."""
    key = str(strategy_id or "").strip()
    if not key or not callable(build):
        return
    for existing, _name, _build in _STRATEGY_BUILDERS:
        if existing == key:
            return
    _STRATEGY_BUILDERS.append((key, str(name or key), build))


def strategy_ids() -> list[str]:
    return [row[0] for row in _STRATEGY_BUILDERS]


def _ema(values: list[float], length: int) -> list[float | None]:
    out: list[float | None] = [None] * len(values)
    if length < 1 or not values:
        return out
    alpha = 2.0 / (length + 1.0)
    previous: float | None = None
    for index, value in enumerate(values):
        previous = value if previous is None else value * alpha + previous * (1.0 - alpha)
        if index >= length - 1:
            out[index] = previous
    return out


_RECENT_TRADE_WINDOW = 8


def _trade_book_stats(trades: list[dict]) -> dict:
    """Closed-bar trade book: Factor, win rate, max drawdown. Own desk math."""
    wins = 0
    gross_win = 0.0
    gross_loss = 0.0
    growth = 1.0
    worst = 0.0
    equity = 1.0
    peak = 1.0
    max_dd = 0.0
    for trade in trades or []:
        pct = float(trade.get("pct") or 0.0)
        if pct > 0:
            wins += 1
            gross_win += pct
        elif pct < 0:
            gross_loss += -pct
        growth *= 1.0 + pct
        equity *= 1.0 + pct
        if equity > peak:
            peak = equity
        max_dd = min(max_dd, equity / peak - 1.0)
        worst = min(worst, pct)
    count = len(trades or [])
    if gross_loss > 0:
        profit_factor: float | None = gross_win / gross_loss
    elif gross_win > 0:
        # Finite cap — Infinity breaks JSON (allow_nan=False) on /api/learn.
        profit_factor = 99.0
    else:
        profit_factor = None
    if profit_factor is not None:
        if profit_factor != profit_factor or profit_factor in (float("inf"), float("-inf")):
            profit_factor = 99.0
        elif profit_factor > 99.0:
            profit_factor = 99.0
    return {
        "trades": count,
        "wins": wins,
        "winRate": (wins / count) if count else 0.0,
        "total": growth - 1.0,
        "worst": worst,
        "maxDD": max_dd,
        "profitFactor": profit_factor,
    }


def _run_rule(bars: list[Bar], warm: int, enter, exit) -> dict:
    """Long-only replay. Enter/exit on closed bars; fill at the next open."""
    count = len(bars)
    end = count
    trades: list[dict] = []
    holding = None
    pending = ""
    equity = 1.0
    peak = 1.0
    worst = 0.0
    for index in range(max(1, warm), count):
        bar = bars[index]
        if pending == "buy" and holding is None:
            holding = {"entry": bar.open, "entry_index": index, "date": bar.date}
            pending = ""
        elif pending == "sell" and holding is not None:
            pct = bar.open / holding["entry"] - 1.0
            trades.append(
                {
                    "entry": holding["entry"],
                    "exit": bar.open,
                    "pct": pct,
                    "entry_date": holding["date"],
                    "exit_date": bar.date,
                }
            )
            equity *= 1.0 + pct
            if equity > peak:
                peak = equity
            worst = min(worst, equity / peak - 1.0)
            holding = None
            pending = ""
        if index >= end:
            continue
        if holding is not None:
            if exit(index, holding):
                pending = "sell"
                holding["why"] = "Exit rule"
        else:
            plan = enter(index)
            if plan:
                pending = "buy"
    book = _trade_book_stats(trades)
    recent = _trade_book_stats(trades[-_RECENT_TRADE_WINDOW:])
    wins = book["wins"]
    call = "WAIT"
    if pending == "buy":
        call = "BUY"
    elif pending == "sell":
        call = "SELL"
    elif holding is not None:
        call = "HOLD"
    hold = bars[end - 1].close / bars[max(0, warm)].open - 1.0 if end > warm else 0.0
    return {
        "ready": end > warm + 1,
        "call": call,
        "trades": len(trades),
        "wins": wins,
        "winRate": (wins / len(trades)) if trades else 0.0,
        "total": equity - 1.0,
        "hold": hold,
        "worst": worst,
        "maxDD": book["maxDD"],
        "profitFactor": book["profitFactor"],
        "recent": recent,
    }


def _seed_strategies() -> None:
    """Built-in classic strategies. New ships call register_strategy(...)."""
    if _STRATEGY_BUILDERS:
        return

    def build_squeeze(list_bars: list[Bar], closes: list[float], count: int):
        if count < 60:
            return None, "Needs at least 60 bars."
        state = squeeze([bar.high for bar in list_bars], [bar.low for bar in list_bars], closes, 20)
        mom = state["momentum"]
        on = [int(level) >= 2 for level in state["level"]]

        def rising(index: int) -> bool:
            return mom[index] is not None and mom[index - 1] is not None and mom[index] > 0 and mom[index] > mom[index - 1]

        return (
            _run_rule(
                list_bars,
                40,
                lambda index: {"stop": list_bars[index].low} if (not on[index] and on[index - 1] and rising(index)) else None,
                lambda index, _open: mom[index] is not None
                and mom[index - 1] is not None
                and mom[index - 2] is not None
                and mom[index] < mom[index - 1]
                and mom[index - 1] < mom[index - 2],
            ),
            "",
        )

    def build_donchian(list_bars: list[Bar], closes: list[float], count: int):
        if count < 40:
            return None, "Needs at least 40 bars."

        def enter(index: int):
            top = max(list_bars[index - back].high for back in range(1, 21))
            return {"stop": 0} if list_bars[index].close > top else None

        def exit_rule(index: int, _open):
            floor = min(list_bars[index - back].low for back in range(1, 11))
            return list_bars[index].close < floor

        return _run_rule(list_bars, 25, enter, exit_rule), ""

    def build_rsi2(list_bars: list[Bar], closes: list[float], count: int):
        if count < 230:
            return None, "Needs at least 230 bars."
        strength = rsi(closes, 2)
        long = sma(closes, 200)
        short = sma(closes, 5)
        return (
            _run_rule(
                list_bars,
                205,
                lambda index: {"stop": 0}
                if long[index] is not None and closes[index] > long[index] and strength[index] is not None and strength[index] < 10
                else None,
                lambda index, _open: short[index] is not None and closes[index] > short[index],
            ),
            "",
        )

    def build_golden(list_bars: list[Bar], closes: list[float], count: int):
        if count < 220:
            return None, "Needs at least 220 bars."
        fast = sma(closes, 50)
        slow = sma(closes, 200)
        return (
            _run_rule(
                list_bars,
                205,
                lambda index: {"stop": 0}
                if fast[index] is not None
                and slow[index] is not None
                and fast[index - 1] is not None
                and slow[index - 1] is not None
                and fast[index] > slow[index]
                and fast[index - 1] <= slow[index - 1]
                else None,
                lambda index, _open: fast[index] is not None
                and slow[index] is not None
                and fast[index - 1] is not None
                and slow[index - 1] is not None
                and fast[index] < slow[index]
                and fast[index - 1] >= slow[index - 1],
            ),
            "",
        )

    def build_dualema(list_bars: list[Bar], closes: list[float], count: int):
        if count < 80:
            return None, "Needs at least 80 bars."
        fast = _ema(closes, 20)
        slow = _ema(closes, 50)
        return (
            _run_rule(
                list_bars,
                55,
                lambda index: {"stop": 0}
                if fast[index] is not None
                and slow[index] is not None
                and fast[index - 1] is not None
                and slow[index - 1] is not None
                and fast[index] > slow[index]
                and fast[index - 1] <= slow[index - 1]
                else None,
                lambda index, _open: fast[index] is not None
                and slow[index] is not None
                and fast[index - 1] is not None
                and slow[index - 1] is not None
                and fast[index] < slow[index]
                and fast[index - 1] >= slow[index - 1],
            ),
            "",
        )

    def build_weeklow(list_bars: list[Bar], closes: list[float], count: int):
        if count < 220:
            return None, "Needs at least 220 bars."
        long = sma(closes, 200)

        def enter(index: int):
            if long[index] is None or closes[index] <= long[index]:
                return None
            floor = min(closes[index - back] for back in range(7))
            return {"stop": 0} if closes[index] == floor else None

        def exit_rule(index: int, _open):
            top = max(closes[index - back] for back in range(7))
            return closes[index] == top

        return _run_rule(list_bars, 205, enter, exit_rule), ""

    register_strategy("squeeze", "Squeeze release", build_squeeze)
    register_strategy("donchian", "Donchian breakout", build_donchian)
    register_strategy("rsi2", "RSI(2) pullback", build_rsi2)
    register_strategy("golden", "Golden cross", build_golden)
    register_strategy("dualema", "Dual EMA cross", build_dualema)
    register_strategy("weeklow", "7-day low pullback", build_weeklow)


def strategy_snapshot(bars: list[Bar]) -> dict:
    """Score every registered classic strategy on closed bars for one market."""
    _seed_strategies()
    list_bars = list(bars or [])
    count = len(list_bars)
    rows: list[dict] = []
    closes = [bar.close for bar in list_bars]

    for strategy_id, name, build in list(_STRATEGY_BUILDERS):
        try:
            run, why = build(list_bars, closes, count)
        except Exception:
            run, why = None, "Strategy failed on closed bars."
        if run is None:
            rows.append(
                {
                    "id": strategy_id,
                    "name": name,
                    "ready": False,
                    "call": "WAIT",
                    "trades": 0,
                    "wins": 0,
                    "winRate": 0.0,
                    "total": 0.0,
                    "hold": 0.0,
                    "worst": 0.0,
                    "why": why or "Not enough bars.",
                }
            )
        else:
            rows.append({"id": strategy_id, "name": name, **run})

    ranked = [row for row in rows if row.get("ready") and int(row.get("trades") or 0) >= 5]
    ranked.sort(key=lambda row: (-float(row.get("total") or 0), -float(row.get("winRate") or 0)))
    return {
        "as_of": list_bars[-1].date if list_bars else "",
        "best": ranked[0]["id"] if ranked else "",
        "rows": rows,
        "ids": [row[0] for row in _STRATEGY_BUILDERS],
        "source": "bot-on",
    }
