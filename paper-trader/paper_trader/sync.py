"""Push / pull the paper book via the Fort Knox sync bridge."""

from __future__ import annotations

import hashlib
import hmac
import json
import os
import secrets
import time
import urllib.error
import urllib.request
from pathlib import Path

DEFAULT_SYNC_URL = "https://paper-desk-sync.apps.grok.bot"
ENV_TOKEN = "PAPER_SYNC_TOKEN"
ENV_URL = "PAPER_SYNC_URL"


def _root() -> Path:
    return Path(__file__).resolve().parents[1]


def sync_url() -> str:
    return (os.environ.get(ENV_URL) or DEFAULT_SYNC_URL).rstrip("/")


def _env_value(folder: Path, key: str) -> str:
    env_path = folder / ".env"
    if env_path.is_file():
        try:
            text = env_path.read_text(encoding="utf-8", errors="ignore")
        except OSError:
            text = ""
        for line in text.splitlines():
            if line.startswith(f"{key}="):
                return line.split("=", 1)[1].strip().strip('"').strip("'")
    return (os.environ.get(key) or "").strip()


def read_token(folder: Path | None = None) -> str:
    folder = folder or _root()
    return _env_value(folder, ENV_TOKEN)


def ensure_token(folder: Path | None = None) -> str:
    folder = folder or _root()
    token = read_token(folder)
    if len(token) == 64 and all(ch in "0123456789abcdefABCDEF" for ch in token):
        return token
    raise ValueError(
        "Missing PAPER_SYNC_TOKEN in .env. Open Paper Desk Sync signed into Cursor, "
        "tap Create computer link, then paste PAPER_SYNC_TOKEN=... into Documents\\Paper Trader\\.env."
    )


def _sign(token: str, method: str, path: str, timestamp: str, body: str) -> str:
    message = f"{method.upper()}\n{path}\n{timestamp}\n{body}".encode("utf-8")
    return hmac.new(token.encode("utf-8"), message, hashlib.sha256).hexdigest()


def _request(method: str, token: str, payload: dict | None = None) -> dict:
    path = "/api/book"
    url = f"{sync_url()}{path}"
    body = ""
    data = None
    headers = {
        "Accept": "application/json",
        "Authorization": f"Bearer {token}",
        "User-Agent": "PaperTrader-Desk/1.10.51",
    }
    if payload is not None:
        body = json.dumps({"book": payload}, separators=(",", ":"))
        data = body.encode("utf-8")
        headers["Content-Type"] = "application/json"
    timestamp = str(int(time.time() * 1000))
    nonce = secrets.token_hex(16)
    headers["X-Desk-Timestamp"] = timestamp
    headers["X-Desk-Nonce"] = nonce
    headers["X-Desk-Signature"] = _sign(token, method, path, timestamp, body)
    req = urllib.request.Request(url, data=data, headers=headers, method=method)
    try:
        with urllib.request.urlopen(req, timeout=45) as resp:
            parsed = json.loads(resp.read().decode("utf-8"))
    except urllib.error.HTTPError as err:
        try:
            parsed = json.loads(err.read().decode("utf-8"))
        except Exception:
            parsed = {"ok": False, "error": f"Sync HTTP {err.code}"}
        raise ValueError(parsed.get("error") or f"Sync HTTP {err.code}") from None
    except urllib.error.URLError as err:
        raise ValueError(f"Could not reach sync bridge: {err.reason}") from None
    if not isinstance(parsed, dict):
        raise ValueError("Sync bridge returned a bad reply.")
    if parsed.get("ok") is False:
        raise ValueError(parsed.get("error") or "Sync failed.")
    return parsed


def push_book(book: dict, folder: Path | None = None) -> dict:
    token = ensure_token(folder)
    result = _request("PUT", token, book)
    return {
        "ok": True,
        "action": "push",
        "markets": result.get("markets") or len((book.get("sessions") or {})),
        "sync_url": sync_url(),
        "vault": result.get("vault"),
    }


def pull_book(folder: Path | None = None) -> dict:
    token = ensure_token(folder)
    result = _request("GET", token)
    book = result.get("book")
    if not isinstance(book, dict):
        raise ValueError("Sync bridge returned no paper book.")
    return {
        "ok": True,
        "action": "pull",
        "markets": result.get("markets") or len((book.get("sessions") or {})),
        "updated_at": result.get("updated_at"),
        "book": book,
        "sync_url": sync_url(),
        "vault": result.get("vault"),
    }


def status(folder: Path | None = None) -> dict:
    folder = folder or _root()
    token = read_token(folder)
    ready = len(token) == 64 and all(ch in "0123456789abcdefABCDEF" for ch in token)
    return {
        "ok": True,
        "has_token": ready,
        "sync_url": sync_url(),
        "hardening": "fort-knox",
        "hint": (
            "PAPER_SYNC_TOKEN is set. Push / Pull uses signed vault requests."
            if ready
            else "Open Paper Desk Sync signed into Cursor → Create computer link → put PAPER_SYNC_TOKEN in .env."
        ),
    }
