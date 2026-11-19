"""NYSE market breadth + VIX for Chart boxes. Never invent TICK / ADD / TRIN / VOLD."""

from __future__ import annotations

from paper_trader import quotes

NYSE_KEYS = ("TICK", "ADD", "TRIN", "VOLD")
KEYS = NYSE_KEYS + ("VIX",)

# Probed 2026-11-19 against feeds this desk already uses:
# - Yahoo Finance chart API: ^TICK, $TICK, ^ADD, ^VOLD, ^TRIN, and close cousins → 404.
#   Plain "TRIN" resolves to Trinity Capital Inc. equity — never treat that as Arms Index.
# - CNBC quote webservice (phone path): .TICK / $TICK / ADD / VOLD / TRIN internals → code 1 / none.
# - Stooq: same symbols unavailable.
# - VIX is real on Yahoo (^VIX) and CNBC (.VIX) — safe to paint when the feed answers.
#
# TradingView script https://www.tradingview.com/script/aNrCpO4C-Market-Internals-TICK-ADD-VOLD-TRIN-VIX/
# uses USI: / INDEX: internals (TICK, ADD, VOLD, TRIN, VIX, ABVD, TKCD). Pine cannot run in this
# Private web app the way TradingView runs it, and scraping TradingView is not allowed.
#
# Best real next step for NYSE boxes (not wired here — needs broker OAuth / paid entitlement):
# - Interactive Brokers market indicators: TICK-NYSE, AD-NYSE, TRIN-NYSE, VOL-NYSE
# - Or Schwab Developer quotes for $TICK / $ADD / $TRIN / $VOLD after entitlement is verified
# - Or a paid breadth vendor with a documented API

NOTE = (
    "NYSE TICK, ADD, TRIN, and VOLD stay No feed — no Yahoo/CNBC internals, no TradingView scrape, "
    "and this desk will not paint fake numbers. VIX paints only from a real ^VIX / .VIX quote."
)

NEXT = (
    "Prefer Interactive Brokers market indicators (TICK-NYSE, AD-NYSE, TRIN-NYSE, VOL-NYSE) "
    "or a paid breadth feed; Schwab Developer $TICK/$ADD/$TRIN/$VOLD only after OAuth entitlement."
)


def chip_value(raw) -> str | None:
    """Return a display string only for a finite real reading; else None → UI shows No feed."""
    if raw is None or raw == "":
        return None
    try:
        number = float(raw)
    except (TypeError, ValueError):
        return None
    if number != number:  # NaN
        return None
    return f"{number:g}"


def tone_for(key: str, value, change) -> str | None:
    """Green/red tone only when a real number exists. VIX is inverse (down = bull)."""
    if value is None:
        return None
    try:
        number = float(value)
    except (TypeError, ValueError):
        return None
    if number != number:
        return None
    if key == "VIX":
        if change is None:
            return "mixed"
        try:
            delta = float(change)
        except (TypeError, ValueError):
            return "mixed"
        if delta != delta:
            return "mixed"
        if delta < 0:
            return "bull"
        if delta > 0:
            return "bear"
        return "mixed"
    if key == "TRIN":
        if number < 1:
            return "bull"
        if number > 1:
            return "bear"
        return "mixed"
    if number > 0:
        return "bull"
    if number < 0:
        return "bear"
    return "mixed"


def _vix_from_yahoo(refresh: bool = False) -> tuple[float | None, float | None, str | None]:
    try:
        payload = quotes.fetch_frame("^VIX", "1D", refresh=refresh)
    except ValueError:
        return None, None, None
    try:
        price = float(payload.get("price") or 0)
    except (TypeError, ValueError):
        return None, None, None
    if price <= 0:
        return None, None, None
    change = None
    bars = payload.get("bars") or []
    if len(bars) >= 2:
        try:
            prior = float(bars[-2]["close"])
        except (TypeError, ValueError, KeyError):
            prior = 0.0
        if prior > 0:
            change = price - prior
    return price, change, "yahoo:^VIX"


def snapshot(refresh: bool = False) -> dict:
    """Fail closed for NYSE internals. VIX only when Yahoo answers with a real price."""
    values = {key: None for key in KEYS}
    changes = {key: None for key in KEYS}
    tones = {key: None for key in KEYS}
    sources: list[str] = []

    price, change, source = _vix_from_yahoo(refresh=refresh)
    if price is not None:
        values["VIX"] = price
        changes["VIX"] = change
        tones["VIX"] = tone_for("VIX", price, change)
        if source:
            sources.append(source)

    return {
        "ready": values["VIX"] is not None,
        "source": ",".join(sources) if sources else None,
        "values": values,
        "changes": changes,
        "tones": tones,
        "note": NOTE,
        "next": NEXT,
        "will_not_invent": True,
    }
