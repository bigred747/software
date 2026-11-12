"""Built-in daily sample markets so the desk can run offline."""

from __future__ import annotations

import math
from datetime import date, timedelta

from paper_trader.model import Bar, validate_bars

# Core sample walk stays byte-stable. ETF rows are teaching series only.
CORE_SYMBOLS = ("BTC-USD", "ETH-USD", "EUR-USD", "XAU-USD", "AAPL")

SPECS: dict[str, dict[str, float | str]] = {
    "BTC-USD": {
        "name": "Bitcoin",
        "start": 42000.0,
        "drift": 0.00025,
        "floor": 100.0,
        "decimals": 2,
        "group": "Core samples",
    },
    "ETH-USD": {
        "name": "Ether",
        "start": 2300.0,
        "drift": 0.0002,
        "floor": 10.0,
        "decimals": 2,
        "group": "Core samples",
    },
    "EUR-USD": {
        "name": "Euro / US dollar",
        "start": 1.085,
        "drift": 0.00002,
        "floor": 0.2,
        "decimals": 5,
        "group": "Core samples",
    },
    "XAU-USD": {
        "name": "Gold",
        "start": 1980.0,
        "drift": 0.00012,
        "floor": 100.0,
        "decimals": 2,
        "group": "Core samples",
    },
    "AAPL": {
        "name": "Apple",
        "start": 175.0,
        "drift": 0.00015,
        "floor": 5.0,
        "decimals": 2,
        "group": "Core samples",
    },
    "VOO": {
        "name": "S&P 500",
        "start": 705.92,
        "drift": 0.00018,
        "floor": 50.0,
        "decimals": 2,
        "group": "Core ETFs",
        "expense_pct": 0.03,
    },
    "VTI": {
        "name": "Total Stock Market",
        "start": 375.89,
        "drift": 0.00017,
        "floor": 20.0,
        "decimals": 2,
        "group": "Core ETFs",
        "expense_pct": 0.03,
    },
    "VXUS": {
        "name": "Total Intl Stock",
        "start": 83.62,
        "drift": 0.00016,
        "floor": 10.0,
        "decimals": 2,
        "group": "Core ETFs",
        "expense_pct": 0.07,
    },
    "QQQM": {
        "name": "Nasdaq 100",
        "start": 301.09,
        "drift": 0.00022,
        "floor": 20.0,
        "decimals": 2,
        "group": "Core ETFs",
        "expense_pct": 0.15,
    },
    "SCHD": {
        "name": "Dividend Equity",
        "start": 33.62,
        "drift": 0.00014,
        "floor": 5.0,
        "decimals": 2,
        "group": "Core ETFs",
        "expense_pct": 0.06,
    },
    "SMH": {
        "name": "Semiconductors",
        "start": 265.0,
        "drift": 0.00032,
        "floor": 20.0,
        "decimals": 2,
        "group": "Growth ETFs",
        "expense_pct": 0.3,
        "sample_10y_pct": 29.8,
    },
    "XLK": {
        "name": "Tech Sector",
        "start": 228.0,
        "drift": 0.00024,
        "floor": 15.0,
        "decimals": 2,
        "group": "Growth ETFs",
        "expense_pct": 0.1,
        "sample_10y_pct": 17.1,
    },
    "VGT": {
        "name": "Tech Growth",
        "start": 610.0,
        "drift": 0.00023,
        "floor": 40.0,
        "decimals": 2,
        "group": "Growth ETFs",
        "expense_pct": 0.1,
        "sample_10y_pct": 16.2,
    },
    "QQQ": {
        "name": "Nasdaq 100",
        "start": 498.0,
        "drift": 0.00021,
        "floor": 30.0,
        "decimals": 2,
        "group": "Growth ETFs",
        "expense_pct": 0.2,
        "sample_10y_pct": 14.1,
    },
    "IVW": {
        "name": "S&P Growth",
        "start": 118.0,
        "drift": 0.0002,
        "floor": 10.0,
        "decimals": 2,
        "group": "Growth ETFs",
        "expense_pct": 0.2,
        "sample_10y_pct": 13.4,
    },
    "SCHG": {
        "name": "Large Cap Growth",
        "start": 32.4,
        "drift": 0.0002,
        "floor": 5.0,
        "decimals": 2,
        "group": "Growth ETFs",
        "expense_pct": 0.4,
        "sample_10y_pct": 13.3,
    },
    "IWF": {
        "name": "Russell Growth",
        "start": 378.0,
        "drift": 0.00019,
        "floor": 20.0,
        "decimals": 2,
        "group": "Growth ETFs",
        "expense_pct": 0.2,
        "sample_10y_pct": 13.1,
    },
    "VUG": {
        "name": "Broad Growth",
        "start": 412.0,
        "drift": 0.00019,
        "floor": 20.0,
        "decimals": 2,
        "group": "Growth ETFs",
        "expense_pct": 0.3,
        "sample_10y_pct": 12.6,
    },
    "QUAL": {
        "name": "Quality Factor",
        "start": 186.0,
        "drift": 0.00017,
        "floor": 15.0,
        "decimals": 2,
        "group": "Growth ETFs",
        "expense_pct": 0.1,
        "sample_10y_pct": 11.3,
    },
    "IJH": {
        "name": "Mid Caps",
        "start": 64.8,
        "drift": 0.00015,
        "floor": 8.0,
        "decimals": 2,
        "group": "Growth ETFs",
        "expense_pct": 0.1,
        "sample_10y_pct": 7.3,
    },
}

SAMPLE_COUNT = 220
_CACHE: dict[str, list[Bar]] = {}


def symbols() -> list[dict[str, str | int | float | None]]:
    rows: list[dict[str, str | int | float | None]] = []
    for symbol, spec in SPECS.items():
        bars = _CACHE.get(symbol)
        last = bars[-1].close if bars else float(spec["start"])
        rows.append(
            {
                "symbol": symbol,
                "name": str(spec["name"]),
                "decimals": int(spec["decimals"]),
                "group": str(spec["group"]),
                "expense_pct": spec.get("expense_pct"),
                "sample_10y_pct": spec.get("sample_10y_pct"),
                "last": last,
            }
        )
    return rows


def cached_ready() -> bool:
    return len(_CACHE) == len(SPECS) and all(len(bars) >= 200 for bars in _CACHE.values())


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
    phase = 0.0 if symbol in CORE_SYMBOLS else (sum(ord(char) for char in symbol) % 17) / 7.0
    day = date(2024, 1, 2)
    bars: list[Bar] = []
    previous = start
    made = 0
    while made < count:
        if day.weekday() < 5:
            cycle = 1 + 0.07 * math.sin(made / 8.0 + phase) + 0.035 * math.sin(made / 22.0 + phase)
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
