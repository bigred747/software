"""NYSE market breadth + VIX for Chart boxes and Edge. Never invent TICK / ADD / TRIN / VOLD."""

from __future__ import annotations

from paper_trader import quotes

NYSE_KEYS = ("TICK", "ADD", "TRIN", "VOLD")
KEYS = NYSE_KEYS + ("VIX",)

# Probed 2026-11-19 against feeds this desk already uses:
# - Yahoo Finance chart API: ^TICK, $TICK, ^ADD, ^VOLD, ^TRIN, and close cousins → 404.
#   Plain "TRIN" resolves to Trinity Capital Inc. equity — never treat that as Arms Index.
# - CNBC quote webservice (phone path): .TICK / $TICK / ADD / VOLD / TRIN internals → code 1 / none.
# - VIX is real on Yahoo (^VIX) and CNBC (.VIX) — safe to paint and gate Edge when the feed answers.
#
# TradingView script https://www.tradingview.com/script/aNrCpO4C-Market-Internals-TICK-ADD-VOLD-TRIN-VIX/
# uses USI: / INDEX: internals. Pine cannot run here; scraping TradingView is not allowed.
#
# Edge VIX closed-bar gate (1.9.90): ON when the latest daily VIX close on/before the signal
# bar date is strictly lower than the prior daily VIX close. Fail closed without a real series.

NOTE = (
    "NYSE TICK, ADD, TRIN, and VOLD stay No feed — no Yahoo/CNBC internals, no TradingView scrape, "
    "and this desk will not paint fake numbers. VIX paints and gates Edge only from a real ^VIX / .VIX quote."
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


def _vix_payload(refresh: bool = False) -> dict | None:
    try:
        return quotes.fetch_frame("^VIX", "1D", refresh=refresh)
    except ValueError:
        return None


def _vix_bars(payload: dict | None) -> list[dict]:
    if not payload:
        return []
    out: list[dict] = []
    for bar in payload.get("bars") or []:
        try:
            date = str(bar.get("date") or "")[:10]
            close = float(bar.get("close") or 0)
        except (TypeError, ValueError):
            continue
        if date and close > 0:
            out.append({"date": date, "close": close})
    return out


def snapshot(refresh: bool = False) -> dict:
    """Fail closed for NYSE internals. VIX only when Yahoo answers with a real price."""
    values = {key: None for key in KEYS}
    changes = {key: None for key in KEYS}
    tones = {key: None for key in KEYS}
    sources: list[str] = []
    vix_bars: list[dict] = []

    payload = _vix_payload(refresh=refresh)
    vix_bars = _vix_bars(payload)
    if payload:
        try:
            price = float(payload.get("price") or 0)
        except (TypeError, ValueError):
            price = 0.0
        if price <= 0 and vix_bars:
            price = float(vix_bars[-1]["close"])
        if price > 0:
            values["VIX"] = price
            change = None
            if len(vix_bars) >= 2:
                prior = float(vix_bars[-2]["close"])
                if prior > 0:
                    change = price - prior
            changes["VIX"] = change
            tones["VIX"] = tone_for("VIX", price, change)
            sources.append("yahoo:^VIX")

    return {
        "ready": values["VIX"] is not None,
        "source": ",".join(sources) if sources else None,
        "values": values,
        "changes": changes,
        "tones": tones,
        "vixBars": vix_bars,
        "note": NOTE,
        "next": NEXT,
        "will_not_invent": True,
        "edge_vix_rule": (
            "VIX gate ON when the latest closed daily VIX close on/before the signal bar "
            "is strictly lower than the prior daily VIX close. Fail closed without a real series."
        ),
    }
