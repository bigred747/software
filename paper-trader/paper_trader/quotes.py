"""Market quotes for the paper desk. Orders are never sent to a broker."""

from __future__ import annotations

import json
import threading
import time
import urllib.error
import urllib.request
from datetime import datetime, timedelta, timezone

from paper_trader.model import Bar, validate_bars
from paper_trader.samples import market_row

_CACHE: dict[tuple[str, str, str], tuple[float, dict]] = {}
_LOCK = threading.Lock()
_TTL = 15.0
_URL = "https://query1.finance.yahoo.com/v8/finance/chart/{symbol}?interval={interval}&range={span}&includePrePost=false"

_FRAMES = {
    "1m": ("1m", "5d"),
    "5m": ("5m", "1mo"),
    "15m": ("15m", "1mo"),
    "30m": ("30m", "60d"),
    "1h": ("60m", "3mo"),
    "4h": ("60m", "6mo"),
    "1D": ("1d", "2y"),
    "1W": ("1wk", "5y"),
    "1M": ("1mo", "max"),
    "1Q": ("1mo", "max"),
}


def frame_ids() -> tuple[str, ...]:
    return tuple(_FRAMES)


def yahoo_symbol(symbol: str) -> str:
    row = market_row(symbol)
    if str(row["category"]) == "future":
        return symbol + "=F"
    return symbol


def load_bars(symbol: str, refresh: bool = False) -> list[Bar]:
    payload = fetch_frame(symbol, "1D", refresh=refresh)
    bars = [
        Bar(str(bar["date"]), float(bar["open"]), float(bar["high"]), float(bar["low"]), float(bar["close"]), float(bar["volume"]))
        for bar in payload["bars"]
    ]
    if len(bars) < 40:
        raise ValueError(f"The quote for {symbol} does not have enough daily bars yet")
    validate_bars(bars)
    return bars


def ping() -> str:
    bars = load_bars("SPY")
    if len(bars) < 30:
        raise ValueError("The quote feed returned too little history")
    return bars[-1].date


def fetch_frame(symbol: str, frame: str, refresh: bool = False) -> dict:
    if frame not in _FRAMES:
        raise ValueError("Unknown timeframe")
    interval, span = _FRAMES[frame]
    yahoo = yahoo_symbol(symbol)
    raw = _request(yahoo, interval, span, refresh=refresh)
    bars, meta = parse_chart(raw, intraday=interval not in {"1d", "1wk", "1mo"})
    if frame == "4h":
        bars = aggregate_hours(bars, 4)
    elif frame == "1Q":
        bars = aggregate_quarters(bars)
    if not bars:
        raise ValueError(f"No market quote for {symbol}")
    return {
        "symbol": symbol,
        "frame": frame,
        "bars": [_bar_dict(bar) for bar in bars],
        "as_of": meta["as_of"],
        "price": meta["price"],
        "exchange": meta["exchange"],
        "note": "Market quote. Paper orders stay on this device.",
    }


def parse_chart(payload: dict, intraday: bool) -> tuple[list[Bar], dict]:
    chart = payload.get("chart") or {}
    rows = chart.get("result") or []
    if not rows:
        error = chart.get("error") or {}
        detail = error.get("description") if isinstance(error, dict) else None
        raise ValueError(detail or "No market quote")
    result = rows[0]
    meta = result.get("meta") or {}
    quote = ((result.get("indicators") or {}).get("quote") or [{}])[0]
    stamps = result.get("timestamp") or []
    opens = quote.get("open") or []
    highs = quote.get("high") or []
    lows = quote.get("low") or []
    closes = quote.get("close") or []
    volumes = quote.get("volume") or []
    offset = int(meta.get("gmtoffset") or 0)
    zone = timezone(timedelta(seconds=offset))
    bars: list[Bar] = []
    previous = ""
    for index, stamp in enumerate(stamps):
        if index >= len(opens) or index >= len(highs) or index >= len(lows) or index >= len(closes):
            continue
        if None in (opens[index], highs[index], lows[index], closes[index]):
            continue
        moment = datetime.fromtimestamp(int(stamp), zone)
        label = moment.strftime("%Y-%m-%d %H:%M") if intraday else moment.strftime("%Y-%m-%d")
        if label <= previous:
            continue
        open_ = float(opens[index])
        high = float(highs[index])
        low = float(lows[index])
        close = float(closes[index])
        if min(open_, high, low, close) <= 0:
            continue
        high = max(high, open_, close)
        low = min(low, open_, close)
        volume = float(volumes[index] or 0) if index < len(volumes) else 0.0
        if volume < 0:
            volume = 0.0
        bars.append(Bar(label, open_, high, low, close, volume))
        previous = label
    as_of, live = _latest_price(meta, stamps)
    if bars and live > 0 and not intraday:
        last = bars[-1]
        bars[-1] = Bar(
            last.date,
            last.open,
            max(last.high, live, last.open),
            min(last.low, live, last.open),
            live,
            last.volume,
        )
    shown = live if live > 0 else (bars[-1].close if bars else 0.0)
    return bars, {
        "as_of": as_of,
        "price": shown,
        "exchange": str(meta.get("exchangeName") or ""),
    }


def _latest_price(meta: dict, stamps: list) -> tuple[int, float]:
    """The newest price the feed published: regular session, or a later pre/post trade."""
    regular_time = int(meta.get("regularMarketTime") or (stamps[-1] if stamps else 0))
    choices: list[tuple[int, float]] = []
    regular = meta.get("regularMarketPrice")
    if regular is not None:
        try:
            price = float(regular)
        except (TypeError, ValueError):
            price = 0.0
        if price > 0:
            choices.append((regular_time, price))
    for time_key, price_key in (("postMarketTime", "postMarketPrice"), ("preMarketTime", "preMarketPrice")):
        try:
            moment = int(meta.get(time_key) or 0)
            price = float(meta.get(price_key) or 0)
        except (TypeError, ValueError):
            continue
        if moment > 0 and price > 0:
            choices.append((moment, price))
    if not choices:
        return regular_time, 0.0
    return max(choices, key=lambda item: item[0])


def aggregate_hours(bars: list[Bar], size: int) -> list[Bar]:
    grouped: list[Bar] = []
    chunk: list[Bar] = []
    for bar in bars:
        chunk.append(bar)
        if len(chunk) == size:
            grouped.append(_merge(chunk))
            chunk = []
    if chunk:
        grouped.append(_merge(chunk))
    return grouped


def aggregate_quarters(bars: list[Bar]) -> list[Bar]:
    groups: dict[str, list[Bar]] = {}
    order: list[str] = []
    for bar in bars:
        month = int(bar.date[5:7])
        key = bar.date[:4] + "-Q" + str((month - 1) // 3 + 1)
        if key not in groups:
            groups[key] = []
            order.append(key)
        groups[key].append(bar)
    return [_merge(groups[key], label=key) for key in order]


def _merge(chunk: list[Bar], label: str | None = None) -> Bar:
    return Bar(
        label or chunk[-1].date,
        chunk[0].open,
        max(bar.high for bar in chunk),
        min(bar.low for bar in chunk),
        chunk[-1].close,
        sum(bar.volume for bar in chunk),
    )


def _bar_dict(bar: Bar) -> dict[str, float | str]:
    return {
        "date": bar.date,
        "open": bar.open,
        "high": bar.high,
        "low": bar.low,
        "close": bar.close,
        "volume": bar.volume,
    }


def _request(yahoo: str, interval: str, span: str, refresh: bool) -> dict:
    key = (yahoo, interval, span)
    now = time.monotonic()
    with _LOCK:
        cached = _CACHE.get(key)
        if cached and not refresh and now - cached[0] < _TTL:
            return cached[1]
    url = _URL.format(symbol=urllib.request.quote(yahoo), interval=interval, span=span)
    request = urllib.request.Request(url, headers={"User-Agent": "Mozilla/5.0", "Accept": "application/json"})
    try:
        with urllib.request.urlopen(request, timeout=12) as response:
            payload = json.loads(response.read().decode("utf-8"))
    except urllib.error.HTTPError as exc:
        raise ValueError(f"No market quote for {yahoo}") from exc
    except (urllib.error.URLError, TimeoutError, json.JSONDecodeError) as exc:
        raise ValueError("The quote feed did not answer. Check this computer's connection.") from exc
    if not isinstance(payload, dict):
        raise ValueError("The quote feed returned an unexpected response")
    with _LOCK:
        _CACHE[key] = (now, payload)
    return payload
