"""NYSE market breadth for Chart boxes. Never invent TICK / ADD / TRIN / VOLD."""

from __future__ import annotations

KEYS = ("TICK", "ADD", "TRIN", "VOLD")

# Probed 2026-11-19 against feeds this desk already uses:
# - Yahoo Finance chart API: ^TICK, $TICK, ^ADD, ^VOLD, ^TRIN, and close cousins → 404.
#   Plain "TRIN" resolves to Trinity Capital Inc. equity — never treat that as Arms Index.
# - CNBC quote webservice (phone path): .TICK / $TICK / ADD / VOLD / TRIN internals → code 1 / none.
# - Stooq: same symbols unavailable.
#
# TradingView script https://www.tradingview.com/script/aNrCpO4C-Market-Internals-TICK-ADD-VOLD-TRIN-VIX/
# uses USI: / INDEX: internals (TICK, ADD, VOLD, TRIN, VIX, ABVD, TKCD). Pine cannot run in this
# Private web app the way TradingView runs it, and scraping TradingView is not allowed.
#
# Best real next step (not wired here — needs broker OAuth / paid entitlement):
# - Interactive Brokers market indicators: TICK-NYSE, AD-NYSE, TRIN-NYSE, VOL-NYSE
# - Or Schwab Developer quotes for $TICK / $ADD / $TRIN / $VOLD after entitlement is verified
# - Or a paid breadth vendor with a documented API

NOTE = (
    "No real public feed for NYSE TICK, ADD, TRIN, or VOLD on Yahoo or CNBC. "
    "TradingView Pine / USI symbols are not used (no scrape; Pine cannot run here). "
    "Chart boxes stay No feed until a broker or paid feed is wired."
)

NEXT = (
    "Prefer Interactive Brokers market indicators (TICK-NYSE, AD-NYSE, TRIN-NYSE, VOL-NYSE) "
    "or a paid breadth feed; Schwab Developer $TICK/$ADD/$TRIN/$VOLD only after OAuth entitlement."
)


def snapshot() -> dict:
    """Fail closed: every value is null until a verified real source is wired."""
    return {
        "ready": False,
        "source": None,
        "values": {key: None for key in KEYS},
        "note": NOTE,
        "next": NEXT,
    }


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
