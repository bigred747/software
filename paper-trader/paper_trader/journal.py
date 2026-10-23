"""Paper session stored on this computer. Prices come from the quote feed."""

from __future__ import annotations

import json
from dataclasses import dataclass, field
from pathlib import Path

from paper_trader.backtest import present, run_backtest
from paper_trader.indicators import rsi, sma, squeeze
from paper_trader.learn import fit
from paper_trader.model import Bar, Params, params_from_dict
from paper_trader import quotes


@dataclass
class Journal:
    symbol: str
    params: Params
    cursor: int
    orders: dict[int, str] = field(default_factory=dict)
    close_orders: list[tuple] = field(default_factory=list)
    halt_days: list[str] = field(default_factory=list)


def default_journal() -> Journal:
    params = Params()
    return Journal("BTC-USD", params, params.slow + 1)


def load_journal(path: Path) -> Journal:
    if not path.exists():
        return default_journal()
    try:
        raw = json.loads(path.read_text(encoding="utf-8"))
        params = params_from_dict(raw["params"])
        symbol = str(raw["symbol"])
        cursor = max(params.slow + 1, int(raw["cursor"]))
        orders: dict[int, str] = {}
        for key, side in dict(raw.get("orders") or {}).items():
            if side in {"buy", "sell"}:
                orders[int(key)] = str(side)
        close_orders: list[tuple] = []
        for item in list(raw.get("close_orders") or []):
            if isinstance(item, list) and len(item) == 2 and item[1] in {"buy", "sell"}:
                close_orders.append((str(item[0]), str(item[1])))
            elif isinstance(item, list) and len(item) == 3 and item[1] in {"buy", "sell"} and float(item[2]) > 0:
                close_orders.append((str(item[0]), str(item[1]), float(item[2])))
        halt_days = [str(day) for day in list(raw.get("halt_days") or []) if isinstance(day, str)]
        return Journal(symbol, params, cursor, orders, close_orders, halt_days)
    except (OSError, ValueError, KeyError, TypeError, json.JSONDecodeError):
        return default_journal()


def save_journal(path: Path, journal: Journal) -> None:
    payload = {
        "version": 1,
        "symbol": journal.symbol,
        "cursor": journal.cursor,
        "params": journal.params.to_dict(),
        "orders": {str(index): side for index, side in sorted(journal.orders.items())},
        "close_orders": [list(item) for item in journal.close_orders],
        "halt_days": list(journal.halt_days),
    }
    path.parent.mkdir(parents=True, exist_ok=True)
    temporary = path.with_suffix(path.suffix + ".tmp")
    temporary.write_text(json.dumps(payload, indent=2) + "\n", encoding="utf-8")
    temporary.replace(path)


def view_journal(journal: Journal) -> dict:
    bars = quotes.load_bars(journal.symbol)
    journal.cursor = len(bars)
    result = run_backtest(
        bars,
        journal.params,
        end=journal.cursor,
        manual=journal.orders,
        close_orders=journal.close_orders,
        halt_days=set(journal.halt_days),
    )
    payload = present(result, journal.symbol, len(bars), journal.params, journal.cursor)
    payload["quote"] = "market"
    return payload


def market_view(symbol: str, params: Params) -> dict:
    quote = quotes.fetch_frame(symbol, "1D")
    bars = [
        Bar(str(bar["date"]), float(bar["open"]), float(bar["high"]), float(bar["low"]), float(bar["close"]), float(bar["volume"]))
        for bar in quote["bars"]
    ]
    closes = [bar.close for bar in bars]
    fast = sma(closes, params.fast)
    slow = sma(closes, params.slow)
    rsi_values = rsi(closes, params.rsi_period) if params.rsi_period else [None] * len(bars)
    bands = squeeze([bar.high for bar in bars], [bar.low for bar in bars], closes)
    lesson = fit(bars)
    return {
        "symbol": symbol,
        "note": "Market quote. Paper orders stay on this device.",
        "as_of": quote["as_of"],
        "price": quote["price"],
        "exchange": quote["exchange"],
        "bars": [
            {
                "date": bar.date,
                "open": bar.open,
                "high": bar.high,
                "low": bar.low,
                "close": bar.close,
                "volume": bar.volume,
            }
            for bar in bars
        ],
        "fast": fast,
        "slow": slow,
        "rsi": rsi_values,
        "squeeze": bands["level"],
        "momentum": bands["momentum"],
        "lesson": lesson,
        "mood": lesson["mood"],
        "params": params.to_dict(),
    }
