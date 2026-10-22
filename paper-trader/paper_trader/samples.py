"""Built-in daily sample markets so the desk can run offline."""

from __future__ import annotations

import math
import zlib
from datetime import date, timedelta
from pathlib import Path

from paper_trader.model import Bar, validate_bars

SAMPLE_COUNT = 220
_CACHE: dict[str, list[Bar]] = {}
_CATALOG: list[dict[str, str | int]] | None = None
_INDEX: dict[str, dict[str, str | int]] | None = None

# Familiar sample anchors. Every other name still gets its own deterministic series.
ANCHORS: dict[str, tuple[float, int]] = {
    "BTC-USD": (42000.0, 2),
    "ETH-USD": (2300.0, 2),
    "XRP-USD": (0.58, 4),
    "XLM-USD": (0.11, 4),
    "SOL-USD": (145.0, 2),
    "ADA-USD": (0.45, 4),
    "DOGE-USD": (0.12, 5),
    "BNB-USD": (580.0, 2),
    "AVAX-USD": (35.0, 2),
    "LINK-USD": (14.0, 2),
    "LTC-USD": (75.0, 2),
    "BCH-USD": (340.0, 2),
    "ES": (5200.0, 2),
    "NQ": (18500.0, 2),
    "YM": (40000.0, 2),
    "RTY": (2100.0, 2),
    "CL": (78.0, 2),
    "GC": (2350.0, 2),
    "SI": (28.0, 2),
    "SPY": (450.0, 2),
    "QQQ": (430.0, 2),
    "AAPL": (175.0, 2),
}


def catalog_size() -> int:
    return len(_catalog())


def symbols() -> list[dict[str, str | int]]:
    return search_symbols()["symbols"]


def search_symbols(query: str = "", category: str = "", limit: int = 40) -> dict[str, object]:
    """Return a short page. The full catalog stays on this computer."""
    size = max(1, min(int(limit), 80))
    kind = category.strip().lower()
    needle = query.strip().lower()
    pool = _catalog()
    if kind in {"crypto", "stock", "etf", "future"}:
        pool = [row for row in pool if row["category"] == kind]
    if needle:
        ranked: list[tuple[int, str, dict[str, str | int]]] = []
        for row in pool:
            symbol = str(row["symbol"]).lower()
            name = str(row["name"]).lower()
            if symbol == needle or name == needle:
                rank = 0
            elif symbol.startswith(needle):
                rank = 1
            elif name.startswith(needle):
                rank = 2
            elif needle in symbol:
                rank = 3
            elif needle in name:
                rank = 4
            else:
                continue
            ranked.append((rank, symbol, row))
        ranked.sort(key=lambda item: (item[0], item[1]))
        found = [row for _, _, row in ranked[:size]]
        matched = len(ranked)
    else:
        found = pool[:size]
        matched = len(pool)
    return {
        "total": catalog_size(),
        "matched": matched,
        "symbols": [
            {
                "symbol": row["symbol"],
                "name": row["name"],
                "category": row["category"],
                "decimals": row["decimals"],
            }
            for row in found
        ],
    }


def market_row(symbol: str) -> dict[str, str | int]:
    row = _index().get(symbol)
    if row is None:
        raise ValueError(f"Unknown market {symbol}. Search the list.")
    return row


def warm() -> None:
    """Load the market list. Quotes are fetched when a market is opened."""
    _catalog()


def load_symbol(symbol: str, count: int = SAMPLE_COUNT) -> list[Bar]:
    if symbol not in _index():
        raise ValueError(f"Unknown market {symbol}. Search the list.")
    if count == SAMPLE_COUNT and symbol in _CACHE:
        return _CACHE[symbol]
    bars = _build(symbol, count)
    if count == SAMPLE_COUNT:
        _CACHE[symbol] = bars
    return bars


def _catalog() -> list[dict[str, str | int]]:
    global _CATALOG, _INDEX
    if _CATALOG is not None:
        return _CATALOG
    path = Path(__file__).with_name("catalog.txt")
    rows: list[dict[str, str | int]] = []
    index: dict[str, dict[str, str | int]] = {}
    for line in path.read_text(encoding="utf-8").splitlines():
        if not line.strip():
            continue
        symbol, name, category = line.split("\t")
        start, decimals = _profile(symbol, category)
        row: dict[str, str | int] = {
            "symbol": symbol,
            "name": name,
            "category": category,
            "decimals": decimals,
            "start": start,
        }
        rows.append(row)
        index[symbol] = row
    _CATALOG = rows
    _INDEX = index
    return rows


def _index() -> dict[str, dict[str, str | int]]:
    _catalog()
    assert _INDEX is not None
    return _INDEX


def _profile(symbol: str, category: str) -> tuple[float, int]:
    if symbol in ANCHORS:
        return ANCHORS[symbol]
    seed = zlib.crc32(symbol.encode("utf-8"))
    if category == "crypto":
        start = round(0.02 + (seed % 8000) / 100, 4)
        decimals = 4 if start < 2 else 2
    elif category == "future":
        start = float(15 + seed % 8000)
        decimals = 2
    else:
        start = float(6 + seed % 420)
        decimals = 2
    return start, decimals


def _build(symbol: str, count: int) -> list[Bar]:
    row = _index()[symbol]
    start = float(row["start"])
    floor = max(start * 0.05, 0.00001)
    seed = zlib.crc32(symbol.encode("utf-8"))
    phase = (seed % 628) / 100.0
    amp = 0.035 + (seed % 35) / 1000.0
    drift = 0.00004 + (seed % 25) / 1_000_000.0
    direction = 1.0 if (seed // 3) % 2 == 0 else -1.0
    day = date(2024, 1, 2)
    bars: list[Bar] = []
    previous = start
    made = 0
    while made < count:
        if day.weekday() < 5:
            slot = made % 90
            cycle = 1 + amp * math.sin(made / 8.0 + phase) + amp * 0.45 * math.sin(made / 22.0 + phase / 2)
            shock = 0.0
            if slot >= 55:
                shock = direction * 0.004 * (slot - 54)
            close = max(floor, start * (1 + made * drift) * cycle * (1 + shock))
            open_ = previous
            if 40 <= slot < 55:
                width = 0.0008
            elif slot >= 55:
                width = 0.012
            else:
                width = 0.004
            high = max(open_, close) * (1 + width)
            low = min(open_, close) * (1 - width)
            low = max(floor * 0.5, low)
            bars.append(Bar(day.isoformat(), open_, high, low, close, 1_000 + made * 10))
            previous = close
            made += 1
        day += timedelta(days=1)
    validate_bars(bars)
    return bars
