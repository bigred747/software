"""Desk Pool — public Form Prints and Civic Trades. Private dark-pool books stay fail-closed."""

from __future__ import annotations

import json
import threading
import time
import urllib.error
import urllib.parse
import urllib.request
from typing import Any

# Public disclosure compilations (keyless). Attribution required when rows are shown.
_FORM_URL = "https://tracefour.com/v1/filings"
_HOUSE_URL = "https://mcp.optimistfi.com/datasets/house/{year}.json"
_CONGRESS_URL = "https://tracefour.com/data/congress/{slug}.json"

# Senate + active House members Tracefour publishes as static CORS-open JSON.
_CIVIC_MEMBERS = (
    "nancy-pelosi",
    "tommy-tuberville",
    "josh-gottheimer",
    "ro-khanna",
    "mark-warner",
    "dan-crenshaw",
    "michael-rulli",
    "ted-cruz",
    "thomas-massie",
    "byron-donalds",
    "french-hill",
    "steve-cohen",
)

_UA = {
    "User-Agent": "Mozilla/5.0 PaperTrader-DeskPool/1.10.19",
    "Accept": "application/json",
}

_LOCK = threading.Lock()
_CACHE: dict[str, tuple[float, Any]] = {}
_FORM_TTL = 300.0
_CIVIC_TTL = 900.0


def _get(key: str) -> Any | None:
    row = _CACHE.get(key)
    if not row:
        return None
    expires, value = row
    if time.time() > expires:
        return None
    return value


def _put(key: str, value: Any, ttl: float) -> Any:
    _CACHE[key] = (time.time() + ttl, value)
    return value


def _fetch_json(url: str, timeout: float = 18.0) -> Any:
    request = urllib.request.Request(url, headers=_UA)
    with urllib.request.urlopen(request, timeout=timeout) as response:
        return json.loads(response.read().decode("utf-8"))


def pool_gates() -> dict[str, Any]:
    """Honest fail-closed slots for private books. Never invent resting liquidity."""
    return {
        "darkPoolBook": {
            "ready": False,
            "label": "Pool Book",
            "status": "No feed",
            "note": "True dark-pool books are private. This desk does not invent resting hidden orders.",
        },
        "icebergBook": {
            "ready": False,
            "label": "Iceberg Book",
            "status": "No feed",
            "note": "Resting iceberg size is private. Iceberg Proxy on the public tape is high volume with a tight closed-bar range — not the hidden book.",
        },
        "institutionalBlotter": {
            "ready": False,
            "label": "Blotter",
            "status": "No feed",
            "note": "Institutional blotters and block-desk prints stay behind paid feeds. Big Prints here use public relative volume only.",
        },
        "paperOnly": True,
        "liveLocked": True,
    }


def form_prints(symbol: str, limit: int = 20) -> dict[str, Any]:
    """SEC Form 4 open-market rows for one ticker via keyless Tracefour JSON."""
    ticker = str(symbol or "").strip().upper()
    if not ticker or ticker.startswith("^") or "-" in ticker:
        return {
            "ready": False,
            "symbol": ticker,
            "rows": [],
            "note": "Form Prints need a stock or ETF ticker.",
            "source": "",
        }
    cache_key = f"form:{ticker}:{limit}"
    with _LOCK:
        hit = _get(cache_key)
        if hit is not None:
            return hit
    try:
        query = urllib.parse.urlencode({"ticker": ticker, "limit": max(1, min(int(limit), 50))})
        payload = _fetch_json(f"{_FORM_URL}?{query}")
    except (urllib.error.URLError, urllib.error.HTTPError, TimeoutError, ValueError, json.JSONDecodeError) as exc:
        return {
            "ready": False,
            "symbol": ticker,
            "rows": [],
            "note": "Form Prints feed did not answer. " + str(exc)[:120],
            "source": "tracefour",
        }
    raw = payload.get("data") if isinstance(payload, dict) else None
    if not isinstance(raw, list):
        return {
            "ready": False,
            "symbol": ticker,
            "rows": [],
            "note": "Form Prints returned no rows.",
            "source": "tracefour",
        }
    rows = []
    for item in raw:
        if not isinstance(item, dict):
            continue
        code = str(item.get("transactionCode") or "").upper()
        if code not in {"P", "S"}:
            # Keep other codes labeled; still public Form 4.
            pass
        side = "Buy" if code == "P" else "Sell" if code == "S" else code or "—"
        rows.append(
            {
                "symbol": str(item.get("issuerTicker") or ticker).upper(),
                "name": str(item.get("ownerName") or "—"),
                "title": str(item.get("ownerTitle") or ""),
                "side": side,
                "code": code or "—",
                "shares": item.get("shares"),
                "price": item.get("pricePerShare"),
                "value": item.get("totalValue"),
                "filed": str(item.get("filedAt") or "")[:10],
                "traded": str(item.get("transactionDate") or item.get("periodOfReport") or "")[:10],
                "url": str(item.get("rawFilingUrl") or item.get("url") or ""),
                "plan10b5": bool(item.get("is10b5_1")),
            }
        )
    meta = payload.get("meta") if isinstance(payload, dict) else {}
    attribution = ""
    if isinstance(meta, dict):
        attr = meta.get("attribution") or {}
        if isinstance(attr, dict):
            attribution = str(attr.get("text") or attr.get("page") or "")
    result = {
        "ready": True,
        "symbol": ticker,
        "rows": rows,
        "note": (
            "SEC Form 4 open-market disclosures for "
            + ticker
            + ". Lagged filings, not live prints. Paper only. Not a forecast."
        ),
        "source": "tracefour",
        "attribution": attribution or "Underlying filings are public-domain government works. Tracefour compilation CC BY 4.0.",
        "asOf": time.strftime("%Y-%m-%dT%H:%M:%SZ", time.gmtime()),
    }
    with _LOCK:
        return _put(cache_key, result, _FORM_TTL)


def _normalize_civic_row(item: dict[str, Any], source: str) -> dict[str, Any] | None:
    ticker = str(item.get("ticker") or "").strip().upper()
    if not ticker:
        return None
    side_raw = str(item.get("type") or item.get("txn_type") or "").strip().lower()
    if side_raw in {"purchase", "buy", "p"}:
        side = "Buy"
    elif side_raw in {"sale", "sell", "partial_sale", "s"}:
        side = "Sell"
    elif side_raw in {"exchange"}:
        side = "Exchange"
    else:
        side = side_raw.title() or "—"
    amount = str(item.get("amountLabel") or "").strip()
    if not amount and item.get("amount_low") is not None:
        low = item.get("amount_low")
        high = item.get("amount_high")
        if high is None:
            amount = f"${low:,}+"
        else:
            amount = f"${low:,} – ${high:,}"
    if not amount and item.get("amountMin") is not None and item.get("amountMax") is not None:
        amount = f"${item.get('amountMin'):,} – ${item.get('amountMax'):,}"
    member = str(item.get("memberName") or item.get("politician") or item.get("member") or "—")
    return {
        "symbol": ticker,
        "member": member,
        "chamber": str(item.get("chamber") or ""),
        "party": str(item.get("party") or ""),
        "side": side,
        "amount": amount,
        "traded": str(item.get("transactionDate") or item.get("txn_date") or "")[:10],
        "disclosed": str(item.get("disclosureDate") or item.get("disclosure_date") or item.get("filing_date") or "")[:10],
        "owner": str(item.get("owner") or ""),
        "asset": str(item.get("assetDescription") or item.get("asset") or ""),
        "url": str(item.get("sourceLink") or ""),
        "source": source,
    }


def _house_year_rows(year: int) -> list[dict[str, Any]]:
    cache_key = f"house:{year}"
    with _LOCK:
        hit = _get(cache_key)
        if hit is not None:
            return hit
    try:
        payload = _fetch_json(_HOUSE_URL.format(year=year), timeout=30.0)
    except (urllib.error.URLError, urllib.error.HTTPError, TimeoutError, ValueError, json.JSONDecodeError):
        with _LOCK:
            return _put(cache_key, [], 120.0)
    raw = payload.get("transactions") if isinstance(payload, dict) else None
    rows: list[dict[str, Any]] = []
    if isinstance(raw, list):
        for item in raw:
            if not isinstance(item, dict):
                continue
            if str(item.get("filing_status") or "").lower() == "deleted":
                continue
            row = _normalize_civic_row(item, "optimistfi-house")
            if row:
                rows.append(row)
    with _LOCK:
        return _put(cache_key, rows, _CIVIC_TTL)


def _member_rows(slug: str) -> list[dict[str, Any]]:
    cache_key = f"member:{slug}"
    with _LOCK:
        hit = _get(cache_key)
        if hit is not None:
            return hit
    try:
        payload = _fetch_json(_CONGRESS_URL.format(slug=slug), timeout=18.0)
    except (urllib.error.URLError, urllib.error.HTTPError, TimeoutError, ValueError, json.JSONDecodeError):
        with _LOCK:
            return _put(cache_key, [], 120.0)
    data = payload.get("data") if isinstance(payload, dict) else None
    trades = data.get("trades") if isinstance(data, dict) else None
    rows: list[dict[str, Any]] = []
    if isinstance(trades, list):
        for item in trades:
            if isinstance(item, dict):
                row = _normalize_civic_row(item, "tracefour-congress")
                if row:
                    rows.append(row)
    with _LOCK:
        return _put(cache_key, rows, _CIVIC_TTL)


def civic_trades(symbol: str = "", limit: int = 40) -> dict[str, Any]:
    """STOCK Act disclosures. Prefer open ticker; otherwise newest desk-wide rows."""
    ticker = str(symbol or "").strip().upper()
    cap = max(1, min(int(limit), 80))
    years = [time.gmtime().tm_year, time.gmtime().tm_year - 1]
    collected: list[dict[str, Any]] = []
    notes: list[str] = []
    for year in years:
        collected.extend(_house_year_rows(year))
    if not collected:
        notes.append("House year file did not answer; trying curated member files.")
    for slug in _CIVIC_MEMBERS:
        collected.extend(_member_rows(slug))
    # De-dupe on member|ticker|traded|side|amount
    seen: set[str] = set()
    unique: list[dict[str, Any]] = []
    for row in collected:
        key = "|".join(
            [
                row.get("member") or "",
                row.get("symbol") or "",
                row.get("traded") or "",
                row.get("side") or "",
                row.get("amount") or "",
            ]
        )
        if key in seen:
            continue
        seen.add(key)
        unique.append(row)
    unique.sort(key=lambda row: (row.get("disclosed") or row.get("traded") or ""), reverse=True)
    if ticker:
        matched = [row for row in unique if row.get("symbol") == ticker]
        scope = ticker
    else:
        matched = unique
        scope = "desk"
    ready = bool(matched) or bool(unique)
    if ticker and not matched and unique:
        note = (
            "No Civic Trades for "
            + ticker
            + " in the loaded disclosures. Showing newest desk-wide rows instead. Disclosure can lag the trade by weeks."
        )
        matched = unique[:cap]
    else:
        matched = matched[:cap]
        note = (
            "Public STOCK Act disclosures (House year files + curated member files). "
            "Amounts are brackets as filed. Lagged, not live. Paper only. Not a forecast."
        )
    if not ready:
        return {
            "ready": False,
            "symbol": ticker,
            "rows": [],
            "note": "Civic Trades feed did not answer. " + (" ".join(notes) if notes else "No feed."),
            "source": "",
            "attribution": "",
        }
    return {
        "ready": True,
        "symbol": scope,
        "rows": matched,
        "note": note,
        "source": "optimistfi+tracefour",
        "attribution": (
            "House PTRs via OptimistFi (CC BY 4.0). Member files via Tracefour (CC BY 4.0). "
            "Underlying filings are public government works."
        ),
        "asOf": time.strftime("%Y-%m-%dT%H:%M:%SZ", time.gmtime()),
        "loaded": len(unique),
    }


def desk_pool_open(symbol: str) -> dict[str, Any]:
    """One payload for the Desk Pool Tools page on the open market."""
    ticker = str(symbol or "").strip().upper()
    return {
        "symbol": ticker,
        "gates": pool_gates(),
        "forms": form_prints(ticker, 20),
        "civic": civic_trades(ticker, 30),
        "version": "1.10.19",
    }
