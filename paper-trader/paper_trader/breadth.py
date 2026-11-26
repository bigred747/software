"""NYSE market breadth + VIX for Chart boxes and Edge. Never invent TICK / ADD / TRIN / VOLD."""

from __future__ import annotations

from paper_trader import quotes

NYSE_KEYS = ("TICK", "ADD", "TRIN", "VOLD")
KEYS = NYSE_KEYS + ("VIX",)

# Feeds this desk uses:
# - Yahoo / CNBC: no real NYSE internals (Yahoo "TRIN" is an equity — never treat as Arms Index).
# - Schwab Developer OAuth (computer Connect): $TICK · $ADD · $TRIN · $VOLD when entitled.
# - VIX is real on Yahoo (^VIX) and CNBC (.VIX).
#
# TradingView Market Internals Pine is not wired (no scrape). Edge VIX gate stays closed-bar only.

NOTE_EMPTY = (
    "NYSE TICK, ADD, TRIN, and VOLD stay No feed until Schwab Connect returns real "
    "$TICK / $ADD / $TRIN / $VOLD quotes — Yahoo/CNBC do not carry them, and this desk "
    "will not paint fake numbers. VIX paints and gates Edge only from a real ^VIX / .VIX quote."
)

NOTE_SCHWAB = (
    "NYSE boxes paint from Schwab Connect quotes ($TICK · $ADD · $TRIN · $VOLD) when the "
    "session is entitled. Missing symbols stay No feed — never invented. VIX still comes "
    "from a real ^VIX / .VIX quote for Edge."
)

NEXT = (
    "Computer: Connect Schwab, then press Test — breadth chips read $TICK/$ADD/$TRIN/$VOLD. "
    "If a chip stays No feed, the Developer app key needs market-indicator entitlement. "
    "Phone Private has no Schwab token, so those four stay No feed there."
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


def _apply_schwab_nyse(values: dict, changes: dict, tones: dict, sources: list[str]) -> None:
    """Paint TICK/ADD/TRIN/VOLD only from finite Schwab quotes. Fail closed otherwise."""
    try:
        from paper_trader.schwab_auth import fetch_breadth_quotes
    except Exception:
        return
    try:
        rows = fetch_breadth_quotes()
    except Exception:
        return
    if not rows:
        return
    painted: list[str] = []
    for key in NYSE_KEYS:
        row = rows.get(key) or {}
        value = row.get("value")
        change = row.get("change")
        if value is None:
            continue
        try:
            number = float(value)
        except (TypeError, ValueError):
            continue
        if number != number:
            continue
        values[key] = number
        try:
            changes[key] = float(change) if change is not None and change == change else None
        except (TypeError, ValueError):
            changes[key] = None
        tones[key] = tone_for(key, number, changes[key])
        painted.append(key)
    if painted:
        sources.append("schwab:" + "+".join(painted))


def snapshot(refresh: bool = False) -> dict:
    """Schwab paints NYSE internals when connected+entitled. VIX from Yahoo. Never invent."""
    values = {key: None for key in KEYS}
    changes = {key: None for key in KEYS}
    tones = {key: None for key in KEYS}
    sources: list[str] = []
    vix_bars: list[dict] = []

    _apply_schwab_nyse(values, changes, tones, sources)

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

    nyse_any = any(values.get(key) is not None for key in NYSE_KEYS)
    return {
        "ready": values["VIX"] is not None or nyse_any,
        "source": ",".join(sources) if sources else None,
        "values": values,
        "changes": changes,
        "tones": tones,
        "vixBars": vix_bars,
        "note": NOTE_SCHWAB if nyse_any else NOTE_EMPTY,
        "next": NEXT,
        "will_not_invent": True,
        "edge_vix_rule": (
            "VIX gate ON when the latest closed daily VIX close on/before the signal bar "
            "is strictly lower than the prior daily VIX close. Fail closed without a real series."
        ),
    }
