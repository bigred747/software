"""Schwab OAuth connect + local token store. No broker order placement."""

from __future__ import annotations

import base64
import json
import secrets
import time
import urllib.error
import urllib.parse
import urllib.request
from pathlib import Path
from typing import Any

from paper_trader.schwab_env import (
    ENV_PATH,
    _configured_value,
    credentials_ready,
    load_dotenv,
)

INSTALL_ROOT = Path(__file__).resolve().parents[1]
TOKEN_PATH = INSTALL_ROOT / "schwab_tokens.json"

AUTH_URL = "https://api.schwabapi.com/v1/oauth/authorize"
TOKEN_URL = "https://api.schwabapi.com/v1/oauth/token"
# Soft refresh skew so the desk renews before Schwab rejects the access token.
REFRESH_SKEW_SEC = 60


def _callback_url() -> str:
    load_dotenv()
    return _configured_value("SCHWAB_CALLBACK_URL") or "https://127.0.0.1"


def _app_key() -> str:
    load_dotenv()
    return _configured_value("SCHWAB_APP_KEY")


def _app_secret() -> str:
    load_dotenv()
    return _configured_value("SCHWAB_APP_SECRET")


def _default_store() -> dict[str, Any]:
    return {
        "access_token": "",
        "refresh_token": "",
        "token_type": "Bearer",
        "expires_at": 0,
        "market_data": False,
        "live_trading": False,
        "updated_at": 0,
        "last_error": "",
    }


def load_store(path: Path | None = None) -> dict[str, Any]:
    target = path if path is not None else TOKEN_PATH
    data = _default_store()
    if not target.is_file():
        return data
    try:
        raw = json.loads(target.read_text(encoding="utf-8"))
    except (OSError, ValueError):
        return data
    if not isinstance(raw, dict):
        return data
    for key in data:
        if key in raw:
            data[key] = raw[key]
    data["market_data"] = bool(data.get("market_data"))
    data["live_trading"] = bool(data.get("live_trading"))
    try:
        data["expires_at"] = int(data.get("expires_at") or 0)
    except (TypeError, ValueError):
        data["expires_at"] = 0
    data["access_token"] = str(data.get("access_token") or "")
    data["refresh_token"] = str(data.get("refresh_token") or "")
    data["last_error"] = str(data.get("last_error") or "")
    return data


def save_store(data: dict[str, Any], path: Path | None = None) -> dict[str, Any]:
    target = path if path is not None else TOKEN_PATH
    payload = _default_store()
    payload.update(data)
    payload["updated_at"] = int(time.time())
    payload["market_data"] = bool(payload.get("market_data"))
    payload["live_trading"] = bool(payload.get("live_trading"))
    target.write_text(json.dumps(payload, indent=2, sort_keys=True) + "\n", encoding="utf-8")
    return payload


def clear_tokens(path: Path | None = None, *, keep_prefs: bool = True) -> dict[str, Any]:
    current = load_store(path)
    next_data = _default_store()
    if keep_prefs:
        next_data["market_data"] = False
        next_data["live_trading"] = False
    return save_store(next_data, path)


def authorize_url(state: str | None = None) -> dict[str, str]:
    """Build the Schwab authorize URL. Does not open a browser or call the API."""
    if not credentials_ready():
        raise ValueError("Schwab App Key and App Secret are not set yet.")
    nonce = state or secrets.token_urlsafe(16)
    query = urllib.parse.urlencode(
        {
            "client_id": _app_key(),
            "redirect_uri": _callback_url(),
            "response_type": "code",
            "state": nonce,
        }
    )
    return {"authorize_url": f"{AUTH_URL}?{query}", "state": nonce, "callback": _callback_url()}


def _clean_paste(raw: str) -> str:
    """Normalize a pasted redirect URL / code from Edge or CMD."""
    text = (raw or "").strip()
    # Strip wrapping quotes Edge / Notepad sometimes add.
    if len(text) >= 2 and text[0] == text[-1] and text[0] in {"'", '"'}:
        text = text[1:-1].strip()
    # Collapse accidental newlines from multi-line paste.
    text = "".join(text.split())
    return text


def extract_code(raw: str) -> str:
    """Pull the OAuth code from a pasted redirect URL or a bare code string.

    Schwab codes often end with @ (shown as %40 in the address bar). parse_qs
    decodes that; a bare %40 paste is decoded here too.
    """
    text = _clean_paste(raw)
    if not text:
        raise ValueError("Paste the full redirect URL from the browser address bar after you sign in.")
    # Some browsers put params in the fragment.
    if "://" in text or text.startswith("?") or text.startswith("code="):
        if "://" not in text and not text.startswith("?"):
            text = "?" + text
        parsed = urllib.parse.urlparse(text if "://" in text else f"https://127.0.0.1{text}")
        query = parsed.query or parsed.fragment
        params = urllib.parse.parse_qs(query)
        if params.get("error"):
            desc = (params.get("error_description") or params.get("error") or ["error"])[0]
            raise ValueError(f"Schwab returned an error: {desc}")
        code = (params.get("code") or [""])[0].strip()
        if not code:
            raise ValueError("No code= was found in that URL. Finish sign-in, then copy the whole address bar.")
        return code
    if "%" in text:
        text = urllib.parse.unquote(text)
    return text


def _basic_auth_header() -> str:
    pair = f"{_app_key()}:{_app_secret()}".encode("utf-8")
    return "Basic " + base64.b64encode(pair).decode("ascii")


def _read_http_body(response) -> bytes:
    """Read Schwab HTTP body; decode gzip when the server ignores Accept-Encoding."""
    import gzip

    raw = response.read()
    encoding = (response.headers.get("Content-Encoding") or "").lower()
    if "gzip" in encoding:
        try:
            return gzip.decompress(raw)
        except OSError:
            return raw
    if len(raw) >= 2 and raw[0] == 0x1F and raw[1] == 0x8B:
        try:
            return gzip.decompress(raw)
        except OSError:
            return raw
    return raw


def _token_request(form: dict[str, str]) -> dict[str, Any]:
    body = urllib.parse.urlencode(form).encode("utf-8")
    request = urllib.request.Request(
        TOKEN_URL,
        data=body,
        method="POST",
        headers={
            "Authorization": _basic_auth_header(),
            "Content-Type": "application/x-www-form-urlencoded",
            "Accept": "application/json",
            "Accept-Encoding": "identity",
        },
    )
    try:
        with urllib.request.urlopen(request, timeout=30) as response:
            payload = json.loads(_read_http_body(response).decode("utf-8"))
    except urllib.error.HTTPError as exc:
        detail = _read_http_body(exc).decode("utf-8", errors="ignore")
        try:
            err = json.loads(detail)
            message = str(err.get("error_description") or err.get("error") or detail or exc.reason)
        except ValueError:
            message = detail or str(exc.reason)
        lower = message.lower()
        if "invalid_grant" in lower or "code" in lower:
            message = (
                f"{message} — the code was already used or expired. "
                "Run Connect again, sign in, and paste the new address bar URL right away."
            )
        if "redirect" in lower or "mismatch" in lower:
            message = (
                f"{message} — SCHWAB_CALLBACK_URL in .env must match the Developer Portal "
                "exactly (usually https://127.0.0.1 with no trailing slash)."
            )
        raise ValueError(f"Schwab token request failed: {message}") from exc
    except urllib.error.URLError as exc:
        raise ValueError(f"Could not reach Schwab: {exc.reason}") from exc
    if not isinstance(payload, dict) or not payload.get("access_token"):
        raise ValueError("Schwab did not return an access token")
    return payload


def _apply_token_payload(store: dict[str, Any], payload: dict[str, Any]) -> dict[str, Any]:
    expires_in = int(payload.get("expires_in") or 1800)
    store["access_token"] = str(payload.get("access_token") or "")
    if payload.get("refresh_token"):
        store["refresh_token"] = str(payload.get("refresh_token") or "")
    store["token_type"] = str(payload.get("token_type") or "Bearer")
    store["expires_at"] = int(time.time()) + max(60, expires_in)
    store["last_error"] = ""
    return store


def finish_oauth(raw_redirect: str, path: Path | None = None) -> dict[str, Any]:
    """Exchange the pasted redirect URL / code for tokens and save them locally."""
    if not credentials_ready():
        raise ValueError("Schwab App Key and App Secret are not set yet.")
    code = extract_code(raw_redirect)
    payload = _token_request(
        {
            "grant_type": "authorization_code",
            "code": code,
            "redirect_uri": _callback_url(),
        }
    )
    store = load_store(path)
    store = _apply_token_payload(store, payload)
    # Fresh connect: Market Data stays off until Rick presses that button; Live stays off.
    store["market_data"] = False
    store["live_trading"] = False
    return save_store(store, path)


def refresh_access(path: Path | None = None) -> dict[str, Any]:
    store = load_store(path)
    refresh = str(store.get("refresh_token") or "")
    if not refresh:
        raise ValueError("Not connected to Schwab")
    if not credentials_ready():
        raise ValueError("Schwab App Key and App Secret are not set yet.")
    try:
        payload = _token_request(
            {
                "grant_type": "refresh_token",
                "refresh_token": refresh,
            }
        )
    except ValueError as exc:
        store["access_token"] = ""
        store["refresh_token"] = ""
        store["expires_at"] = 0
        store["last_error"] = str(exc)
        store["market_data"] = False
        store["live_trading"] = False
        save_store(store, path)
        raise
    store = _apply_token_payload(store, payload)
    return save_store(store, path)


def ensure_session(path: Path | None = None) -> dict[str, Any]:
    """Return a store with a non-expired access token, refreshing when needed."""
    store = load_store(path)
    if not store.get("access_token") and not store.get("refresh_token"):
        return store
    expires_at = int(store.get("expires_at") or 0)
    if store.get("access_token") and expires_at > int(time.time()) + REFRESH_SKEW_SEC:
        return store
    if not store.get("refresh_token"):
        store["access_token"] = ""
        store["last_error"] = "Session expired. Connect to Schwab again."
        return save_store(store, path)
    try:
        return refresh_access(path)
    except ValueError:
        return load_store(path)


def set_market_data(enabled: bool, path: Path | None = None) -> dict[str, Any]:
    status = connection_status(path)
    if enabled and not status["connected"]:
        raise ValueError("Connect to Schwab before turning Market Data on")
    store = load_store(path)
    store["market_data"] = bool(enabled)
    if not enabled:
        # Turning market data off also clears live trading preference.
        store["live_trading"] = False
    return save_store(store, path)


def set_live_trading(enabled: bool, *, confirmed: bool = False, path: Path | None = None) -> dict[str, Any]:
    """Live Trading stays locked off. Enabling is refused; only disarm is allowed."""
    del confirmed  # kept for API shape; ON is never allowed
    if enabled:
        raise ValueError(
            "Live Trading is locked. This desk stays paper only. No broker orders. "
            "Use your broker's own app for real money."
        )
    store = load_store(path)
    store["live_trading"] = False
    return save_store(store, path)


def disconnect(path: Path | None = None) -> dict[str, Any]:
    return clear_tokens(path)


def _detail_for(store: dict[str, Any], connected: bool, credentials: bool) -> str:
    if connected:
        bits = ["connected"]
        if store.get("market_data"):
            bits.append("market data on")
        bits.append("Live locked · paper only")
        return " · ".join(bits)
    if store.get("last_error"):
        return "not connected · " + str(store["last_error"])
    if not credentials:
        return "not connected · missing keys"
    if store.get("refresh_token") or store.get("access_token"):
        return "not connected · expired"
    return "not connected"


def connection_status(path: Path | None = None) -> dict[str, Any]:
    """Desk status for Signals / buttons. May refresh tokens; never places orders."""
    credentials = credentials_ready()
    store = ensure_session(path)
    connected = bool(store.get("access_token") and int(store.get("expires_at") or 0) > int(time.time()))
    dirty = False
    # Max Safe: clear any stale Live preference so the flag can never stick on.
    if store.get("live_trading"):
        store["live_trading"] = False
        dirty = True
    if not connected and store.get("market_data"):
        store["market_data"] = False
        dirty = True
    if dirty:
        store = save_store(store, path)
    detail = _detail_for(store, connected, credentials)
    return {
        "name": "Schwab",
        "ok": connected,
        "tone": "strict",
        "detail": detail,
        "connected": connected,
        "credentials": credentials,
        "market_data": bool(store.get("market_data")) and connected,
        "live_trading": False,
        "env_path": str(ENV_PATH),
        "token_path": str(path or TOKEN_PATH),
        "callback": _callback_url(),
        "live_orders": False,
        "paper_default": True,
        "live_locked": True,
    }


def signal_item(path: Path | None = None) -> dict[str, Any]:
    status = connection_status(path)
    return {
        "name": "Schwab",
        "ok": bool(status["ok"]),
        "detail": str(status["detail"]),
        "tone": "strict",
    }


QUOTE_TEST_URL = "https://api.schwabapi.com/marketdata/v1/quotes?symbols=SPY"

# NYSE market internals (StreetSmart / thinkorswim index tickers). Not equities.
BREADTH_SYMBOLS = {
    "TICK": "$TICK",
    "ADD": "$ADD",
    "TRIN": "$TRIN",
    "VOLD": "$VOLD",
}


def _bearer_get(url: str, access_token: str) -> tuple[int, str]:
    """GET one Schwab URL with the access token. Returns status and body text."""
    request = urllib.request.Request(
        url,
        method="GET",
        headers={
            "Authorization": f"Bearer {access_token}",
            "Accept": "application/json",
            "Accept-Encoding": "identity",
        },
    )
    try:
        with urllib.request.urlopen(request, timeout=20) as response:
            body = _read_http_body(response).decode("utf-8", errors="ignore")
            return int(response.status), body
    except urllib.error.HTTPError as exc:
        body = _read_http_body(exc).decode("utf-8", errors="ignore")
        return int(exc.code), body
    except urllib.error.URLError as exc:
        raise ValueError(f"Could not reach Schwab: {exc.reason}") from exc


def _finite_number(raw: Any) -> float | None:
    if raw is None or raw == "":
        return None
    try:
        number = float(raw)
    except (TypeError, ValueError):
        return None
    if number != number:  # NaN
        return None
    return number


def _quote_last_and_change(entry: Any) -> tuple[float | None, float | None]:
    """Pull last + net change from one Schwab quote map entry. Fail closed on junk."""
    if not isinstance(entry, dict):
        return None, None
    # Invalid / missing symbols come back as error-shaped entries without a quote block.
    if entry.get("errors") or entry.get("invalidSymbols"):
        return None, None
    quote = entry.get("quote") if isinstance(entry.get("quote"), dict) else entry
    if not isinstance(quote, dict):
        return None, None
    last = None
    for key in ("lastPrice", "mark", "closePrice", "last", "regularMarketLastPrice"):
        last = _finite_number(quote.get(key))
        if last is not None:
            break
    change = None
    for key in ("netChange", "netPercentChange", "markChange", "regularMarketNetChange"):
        # Prefer dollar net change; skip percent-looking keys if last is huge (TICK/ADD/VOLD).
        if key == "netPercentChange":
            continue
        change = _finite_number(quote.get(key))
        if change is not None:
            break
    return last, change


def fetch_breadth_quotes(path: Path | None = None) -> dict[str, dict[str, float | None]]:
    """Real $TICK / $ADD / $TRIN / $VOLD quotes when Schwab is connected. Empty if not entitled."""
    out: dict[str, dict[str, float | None]] = {}
    status = connection_status(path)
    if not status.get("connected"):
        return out
    store = ensure_session(path)
    token = str(store.get("access_token") or "")
    if not token:
        return out
    symbols = ",".join(BREADTH_SYMBOLS.values())
    url = "https://api.schwabapi.com/marketdata/v1/quotes?" + urllib.parse.urlencode(
        {"symbols": symbols, "fields": "quote,reference"}
    )
    try:
        code, body = _bearer_get(url, token)
    except ValueError:
        return out
    if not (200 <= code < 300) or not body.strip():
        return out
    try:
        payload = json.loads(body)
    except json.JSONDecodeError:
        return out
    if not isinstance(payload, dict):
        return out
    # Schwab keys by the requested symbol; also accept bare names without $.
    lower_map = {str(k).upper(): v for k, v in payload.items()}
    for key, symbol in BREADTH_SYMBOLS.items():
        entry = lower_map.get(symbol.upper())
        if entry is None:
            entry = lower_map.get(symbol.lstrip("$").upper())
        if entry is None:
            continue
        last, change = _quote_last_and_change(entry)
        if last is None:
            continue
        out[key] = {"value": last, "change": change}
    return out


def test_connection(path: Path | None = None) -> dict[str, Any]:
    """Desk Test button: keys file, token session, optional live quote ping. No orders."""
    status = connection_status(path)
    key_len = len(_app_key())
    secret_len = len(_app_secret())
    result: dict[str, Any] = {
        "ok": False,
        "name": "Schwab test",
        "credentials": bool(status["credentials"]),
        "connected": bool(status["connected"]),
        "env_path": str(status["env_path"]),
        "callback": str(status["callback"]),
        "key_length": key_len,
        "secret_length": secret_len,
        "quote_ok": False,
        "detail": "",
        "live_locked": True,
        "paper_default": True,
    }
    if not status["credentials"]:
        result["detail"] = (
            f"Keys missing in {status['env_path']}. "
            "Set SCHWAB_APP_KEY and SCHWAB_APP_SECRET (not paste-...), then press Test again."
        )
        return result
    if not status["connected"]:
        result["ok"] = True
        result["detail"] = (
            f"Keys ready (key {key_len} · secret {secret_len}) · callback {status['callback']} · "
            "not connected yet. Press Connect, sign in, paste the redirect URL, then Test again."
        )
        return result
    store = ensure_session(path)
    token = str(store.get("access_token") or "")
    if not token:
        result["detail"] = "Session expired. Press Connect and sign in again."
        return result
    try:
        code, body = _bearer_get(QUOTE_TEST_URL, token)
    except ValueError as exc:
        result["detail"] = str(exc)
        return result
    if 200 <= code < 300:
        result["ok"] = True
        result["connected"] = True
        result["quote_ok"] = True
        result["detail"] = (
            f"Connected · token works · SPY quote ping HTTP {code} · "
            f"callback {status['callback']} · Live locked · paper only."
        )
        return result
    snippet = body.strip().replace("\n", " ")[:160]
    result["detail"] = (
        f"Connected locally but Schwab quote ping failed HTTP {code}"
        + (f" · {snippet}" if snippet else "")
        + ". Try Connect again if the session expired."
    )
    return result
