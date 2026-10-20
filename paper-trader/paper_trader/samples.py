"""Built-in daily sample markets so the desk can run offline."""

from __future__ import annotations

import math
from datetime import date, timedelta

from paper_trader.model import Bar, validate_bars

SPECS: dict[str, dict[str, float | str]] = {
    "BTC-USD": {"name": "Bitcoin", "start": 42000.0, "drift": 0.00025, "floor": 100.0, "decimals": 2},
    "ETH-USD": {"name": "Ether", "start": 2300.0, "drift": 0.0002, "floor": 10.0, "decimals": 2},
    "EUR-USD": {"name": "Euro / US dollar", "start": 1.085, "drift": 0.00002, "floor": 0.2, "decimals": 5},
    "XAU-USD": {"name": "Gold", "start": 1980.0, "drift": 0.00012, "floor": 100.0, "decimals": 2},
    "AAPL": {"name": "Apple", "start": 175.0, "drift": 0.00015, "floor": 5.0, "decimals": 2},
}

SAMPLE_COUNT = 220
_CACHE: dict[str, list[Bar]] = {}


def symbols() -> list[dict[str, str | int]]:
    return [
        {"symbol": symbol, "name": str(spec["name"]), "decimals": int(spec["decimals"])}
        for symbol, spec in SPECS.items()
    ]


def warm() -> None:
    """Build every sample once so the first page does not wait."""
    for symbol in SPECS:
        load_symbol(symbol)


def load_symbol(symbol: str, count: int = SAMPLE_COUNT) -> list[Bar]:
    if symbol not in SPECS:
        known = ", ".join(SPECS)
        raise ValueError(f"Unknown market {symbol}. Choose one of: {known}")
    if count == SAMPLE_COUNT and symbol in _CACHE:
        return _CACHE[symbol]
    bars = _build(symbol, count)
    if count == SAMPLE_COUNT:
        _CACHE[symbol] = bars
    return bars


def _build(symbol: str, count: int) -> list[Bar]:
    spec = SPECS[symbol]
    start = float(spec["start"])
    drift = float(spec["drift"])
    floor = float(spec["floor"])
    day = date(2024, 1, 2)
    bars: list[Bar] = []
    previous = start
    made = 0
    while made < count:
        if day.weekday() < 5:
            cycle = 1 + 0.07 * math.sin(made / 8.0) + 0.035 * math.sin(made / 22.0)
            close = max(floor, start * (1 + made * drift) * cycle)
            open_ = previous
            high = max(open_, close) * 1.003
            low = min(open_, close) * 0.997
            bars.append(Bar(day.isoformat(), open_, high, low, close, 1_000 + made * 10))
            previous = close
            made += 1
        day += timedelta(days=1)
    validate_bars(bars)
    return bars
