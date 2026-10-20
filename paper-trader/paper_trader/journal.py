"""Paper session: which sample day the desk has reached."""

from __future__ import annotations

import json
from dataclasses import dataclass
from pathlib import Path

from paper_trader.backtest import present, run_backtest
from paper_trader.indicators import rsi, sma
from paper_trader.model import Params, params_from_dict
from paper_trader.samples import load_symbol


@dataclass
class Journal:
    symbol: str
    params: Params
    cursor: int


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
        bars = load_symbol(symbol)
        cursor = int(raw["cursor"])
        cursor = max(params.slow + 1, min(len(bars), cursor))
        return Journal(symbol, params, cursor)
    except (OSError, ValueError, KeyError, TypeError, json.JSONDecodeError):
        return default_journal()


def save_journal(path: Path, journal: Journal) -> None:
    payload = {
        "version": 1,
        "symbol": journal.symbol,
        "cursor": journal.cursor,
        "params": journal.params.to_dict(),
    }
    path.parent.mkdir(parents=True, exist_ok=True)
    temporary = path.with_suffix(path.suffix + ".tmp")
    temporary.write_text(json.dumps(payload, indent=2) + "\n", encoding="utf-8")
    temporary.replace(path)


def view_journal(journal: Journal) -> dict:
    bars = load_symbol(journal.symbol)
    result = run_backtest(bars, journal.params, end=journal.cursor)
    return present(result, journal.symbol, len(bars), journal.params, journal.cursor)


def market_view(symbol: str, params: Params) -> dict:
    bars = load_symbol(symbol)
    closes = [bar.close for bar in bars]
    fast = sma(closes, params.fast)
    slow = sma(closes, params.slow)
    rsi_values = rsi(closes, params.rsi_period) if params.rsi_period else [None] * len(bars)
    return {
        "symbol": symbol,
        "note": "Built-in sample prices. Not live quotes.",
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
