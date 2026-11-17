"""Paper session stored on this computer. Prices come from the quote feed."""

from __future__ import annotations

import json
from dataclasses import dataclass, field
from pathlib import Path

from paper_trader.backtest import present, run_backtest, signal_name
from paper_trader.indicators import rsi, sma, squeeze
from paper_trader.learn import closed_bars, fit
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
    working: list[dict] = field(default_factory=list)
    next_order_id: int = 1
    fills: list[dict] = field(default_factory=list)


SIDES = {"buy", "sell"}
KINDS = {"market", "limit", "stop", "stop_limit"}
TIFS = {"DAY", "GTC"}


def make_ticket(raw: dict) -> dict:
    """One paper order ticket. Prices are checked here; nothing leaves this computer."""
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
        limit = float(raw.get("limit") or 0)
        stop = float(raw.get("stop") or 0)
    except (TypeError, ValueError) as exc:
        raise ValueError("Prices must be numbers") from exc
    for label, value in (("Limit", limit), ("Stop", stop)):
        if value != value or value in (float("inf"), float("-inf")) or value < 0:
            raise ValueError(f"{label} price must be a positive number")
    if kind in {"limit", "stop_limit"} and limit <= 0:
        raise ValueError("A limit price is required")
    if kind in {"stop", "stop_limit"} and stop <= 0:
        raise ValueError("A stop price is required")
    return {
        "side": side,
        "kind": kind,
        "tif": tif,
        "limit": limit if kind in {"limit", "stop_limit"} else 0.0,
        "stop": stop if kind in {"stop", "stop_limit"} else 0.0,
        "triggered": False,
    }


def ticket_decision(ticket: dict, last: float, market_price: float) -> tuple[bool, float, bool]:
    """Decide on the latest real trade. Returns (fills, price, triggered).

    A limit never fills worse than its limit. A stop becomes a market order at
    the latest trade plus the paper slippage. A stop limit waits for its stop,
    then follows its limit.
    """
    side = ticket["side"]
    kind = ticket["kind"]
    limit = float(ticket.get("limit") or 0)
    stop = float(ticket.get("stop") or 0)
    buy = side == "buy"
    if kind == "market":
        return True, market_price, False
    if kind == "limit":
        reached = last <= limit if buy else last >= limit
        return reached, (min(last, limit) if buy else max(last, limit)), False
    stop_hit = last >= stop if buy else last <= stop
    if kind == "stop":
        return stop_hit, market_price, stop_hit
    triggered = bool(ticket.get("triggered")) or stop_hit
    if not triggered:
        return False, 0.0, False
    reached = last <= limit if buy else last >= limit
    return reached, (min(last, limit) if buy else max(last, limit)), True


def default_journal() -> Journal:
    params = Params()
    return Journal("BTC-USD", params, params.slow + 1)


def clean_fill(item: object) -> dict | None:
    if not isinstance(item, dict):
        return None
    try:
        side = str(item["side"])
        if side not in SIDES:
            return None
        row = {
            "at": int(item.get("at") or 0),
            "symbol": str(item.get("symbol") or ""),
            "side": side,
            "qty": float(item["qty"]),
            "price": float(item["price"]),
            "quote": float(item.get("quote") or item["price"]),
            "fee_bps": float(item.get("fee_bps") or 0),
            "pnl": float(item.get("pnl") or 0),
            "reason": str(item.get("reason") or "trade")[:40],
        }
        process = str(item.get("process") or "").strip()
        if process:
            row["process"] = process[:80]
        return row
    except (KeyError, TypeError, ValueError):
        return None


def journal_from_dict(raw: dict) -> Journal:
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
    working: list[dict] = []
    for item in list(raw.get("working") or []):
        if not isinstance(item, dict) or not str(item.get("id") or ""):
            continue
        try:
            ticket = make_ticket(item)
        except ValueError:
            continue
        ticket["id"] = str(item["id"])
        ticket["day"] = str(item.get("day") or "")
        ticket["triggered"] = bool(item.get("triggered"))
        working.append(ticket)
    next_order_id = max(int(raw.get("next_order_id") or 1), 1)
    fills = [row for row in (clean_fill(item) for item in list(raw.get("fills") or [])) if row][-500:]
    return Journal(symbol, params, cursor, orders, close_orders, halt_days, working, next_order_id, fills)


def journal_to_dict(journal: Journal) -> dict:
    return {
        "version": 1,
        "symbol": journal.symbol,
        "cursor": journal.cursor,
        "params": journal.params.to_dict(),
        "orders": {str(index): side for index, side in sorted(journal.orders.items())},
        "close_orders": [list(item) for item in journal.close_orders],
        "halt_days": list(journal.halt_days),
        "working": list(journal.working),
        "next_order_id": journal.next_order_id,
        "fills": list(journal.fills[-500:]),
    }


def write_json(path: Path, payload: dict) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    temporary = path.with_suffix(path.suffix + ".tmp")
    temporary.write_text(json.dumps(payload, indent=2) + "\n", encoding="utf-8")
    temporary.replace(path)


def load_journal(path: Path) -> Journal:
    if not path.exists():
        return default_journal()
    try:
        return journal_from_dict(json.loads(path.read_text(encoding="utf-8")))
    except (OSError, ValueError, KeyError, TypeError, json.JSONDecodeError):
        return default_journal()


def save_journal(path: Path, journal: Journal) -> None:
    write_json(path, journal_to_dict(journal))


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
    payload["working"] = [dict(item) for item in journal.working]
    payload["live"] = False
    settled = closed_bars(bars, journal.symbol)
    payload["signal_bar"] = settled[-1].date if settled else None
    if len(settled) < len(bars) and len(settled) > journal.params.slow + 1:
        closed = run_backtest(
            settled,
            journal.params,
            close_orders=journal.close_orders,
            halt_days=set(journal.halt_days),
        )
        signal = signal_name(closed)
        if result.shares > 0 and signal != "SELL":
            signal = "HOLD LONG"
        elif result.shares <= 0 and signal == "HOLD LONG":
            signal = "HOLD"
        payload["signal"] = signal
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
    lesson = fit(closed_bars(bars, symbol))
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
