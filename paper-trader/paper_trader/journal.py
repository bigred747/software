"""Paper session: which sample day the desk has reached."""

from __future__ import annotations

import json
from dataclasses import dataclass, field
from pathlib import Path

from paper_trader.backtest import present, run_backtest
from paper_trader.indicators import rsi, sma
from paper_trader.model import Bar, Params, params_from_dict
from paper_trader.samples import SPECS, load_symbol

SIDES = {"buy", "sell"}
KINDS = {"market", "limit", "stop", "stop_limit"}
TIFS = {"DAY", "GTC"}


@dataclass
class Journal:
    symbol: str
    params: Params
    cursor: int
    orders: dict[int, str] = field(default_factory=dict)
    working: list[dict] = field(default_factory=list)
    next_order_id: int = 1


def default_journal() -> Journal:
    params = Params()
    return Journal("BTC-USD", params, params.slow + 1)


def make_ticket(raw: dict) -> dict:
    side = str(raw.get("side") or "").strip().lower()
    kind = str(raw.get("kind") or "market").strip().lower().replace(" ", "_").replace("-", "_")
    tif = str(raw.get("tif") or "DAY").strip().upper()
    if side not in SIDES:
        raise ValueError("Side must be buy or sell")
    if kind not in KINDS:
        raise ValueError("Order type must be market, limit, stop, or stop limit")
    if tif not in TIFS:
        raise ValueError("Time in force must be DAY or GTC")
    try:
        qty = float(raw.get("qty") or 0)
        limit = float(raw.get("limit") or 0)
        stop = float(raw.get("stop") or 0)
    except (TypeError, ValueError) as exc:
        raise ValueError("Quantity and prices must be numbers") from exc
    if qty < 0:
        raise ValueError("Quantity cannot be negative")
    if kind in {"limit", "stop_limit"} and limit <= 0:
        raise ValueError("Limit price is required")
    if kind in {"stop", "stop_limit"} and stop <= 0:
        raise ValueError("Stop price is required")
    return {
        "side": side,
        "kind": kind,
        "tif": tif,
        "qty": qty,
        "limit": limit,
        "stop": stop,
    }


def ticket_hits(ticket: dict, bar: Bar) -> bool:
    kind = ticket["kind"]
    side = ticket["side"]
    if kind == "market":
        return True
    if kind == "limit":
        return bar.low <= ticket["limit"] if side == "buy" else bar.high >= ticket["limit"]
    if kind == "stop":
        return bar.high >= ticket["stop"] if side == "buy" else bar.low <= ticket["stop"]
    triggered = bar.high >= ticket["stop"] if side == "buy" else bar.low <= ticket["stop"]
    if not triggered:
        return False
    return bar.low <= ticket["limit"] if side == "buy" else bar.high >= ticket["limit"]


def load_journal(path: Path) -> Journal:
    if not path.exists():
        return default_journal()
    try:
        raw = json.loads(path.read_text(encoding="utf-8"))
        params = params_from_dict(raw["params"])
        symbol = str(raw["symbol"])
        bars = load_symbol(symbol)
        cursor = int(raw["cursor"])
        cursor = max(params.slow + 1, min(len(bars), cursor))
        orders: dict[int, str] = {}
        for key, side in dict(raw.get("orders") or {}).items():
            if side in SIDES:
                orders[int(key)] = str(side)
        working = []
        for item in list(raw.get("working") or []):
            if not isinstance(item, dict):
                continue
            try:
                ticket = make_ticket(item)
            except ValueError:
                continue
            ticket["id"] = str(item.get("id") or "")
            if ticket["id"]:
                working.append(ticket)
        next_order_id = max(int(raw.get("next_order_id") or 1), 1)
        return Journal(symbol, params, cursor, orders, working, next_order_id)
    except (OSError, ValueError, KeyError, TypeError, json.JSONDecodeError):
        return default_journal()


def save_journal(path: Path, journal: Journal) -> None:
    payload = {
        "version": 2,
        "symbol": journal.symbol,
        "cursor": journal.cursor,
        "params": journal.params.to_dict(),
        "orders": {str(index): side for index, side in sorted(journal.orders.items())},
        "working": journal.working,
        "next_order_id": journal.next_order_id,
    }
    path.parent.mkdir(parents=True, exist_ok=True)
    temporary = path.with_suffix(path.suffix + ".tmp")
    temporary.write_text(json.dumps(payload, indent=2) + "\n", encoding="utf-8")
    temporary.replace(path)


def view_journal(journal: Journal) -> dict:
    bars = load_symbol(journal.symbol)
    result = run_backtest(bars, journal.params, end=journal.cursor, manual=journal.orders)
    payload = present(result, journal.symbol, len(bars), journal.params, journal.cursor)
    payload["working"] = list(journal.working)
    payload["live"] = False
    payload["live_note"] = "Live stays locked. This desk never sends a broker order."
    if journal.cursor > 0:
        bar = bars[journal.cursor - 1]
        payload["quote"] = {
            "date": bar.date,
            "open": bar.open,
            "high": bar.high,
            "low": bar.low,
            "last": bar.close,
            "volume": bar.volume,
            "note": "Built-in sample prices. Not live quotes.",
        }
    else:
        payload["quote"] = None
    return payload


def market_view(symbol: str, params: Params) -> dict:
    bars = load_symbol(symbol)
    closes = [bar.close for bar in bars]
    fast = sma(closes, params.fast)
    slow = sma(closes, params.slow)
    rsi_values = rsi(closes, params.rsi_period) if params.rsi_period else [None] * len(bars)
    spec = SPECS.get(symbol, {})
    return {
        "symbol": symbol,
        "note": "Built-in sample prices. Not live quotes.",
        "group": spec.get("group"),
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
        "params": params.to_dict(),
    }
