"""Push / pull the paper book between computer and phone via the sync bridge."""

from __future__ import annotations

import json
import os
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
    if len(token) >= 48:
        return token
    raise ValueError(
        "Missing PAPER_SYNC_TOKEN in .env. Open the Paper Desk Sync app signed into Cursor, "
        "tap Create computer link, then paste PAPER_SYNC_TOKEN=... into Documents\\Paper Trader\\.env."
    )


def _request(method: str, token: str, payload: dict | None = None) -> dict:
    url = f"{sync_url()}/api/book"
    data = None
    headers = {
        "Accept": "application/json",
        "Authorization": f"Bearer {token}",
        "User-Agent": "PaperTrader-Desk/1.10.40",
    }
    if payload is not None:
        data = json.dumps({"book": payload}).encode("utf-8")
        headers["Content-Type"] = "application/json"
    req = urllib.request.Request(url, data=data, headers=headers, method=method)
    try:
        with urllib.request.urlopen(req, timeout=45) as resp:
            body = json.loads(resp.read().decode("utf-8"))
    except urllib.error.HTTPError as err:
        try:
            body = json.loads(err.read().decode("utf-8"))
        except Exception:
            body = {"ok": False, "error": f"Sync HTTP {err.code}"}
        raise ValueError(body.get("error") or f"Sync HTTP {err.code}") from None
    except urllib.error.URLError as err:
        raise ValueError(f"Could not reach sync bridge: {err.reason}") from None
    if not isinstance(body, dict):
        raise ValueError("Sync bridge returned a bad reply.")
    if body.get("ok") is False:
        raise ValueError(body.get("error") or "Sync failed.")
    return body


def push_book(book: dict, folder: Path | None = None) -> dict:
    token = ensure_token(folder)
    result = _request("PUT", token, book)
    return {
        "ok": True,
        "action": "push",
        "markets": result.get("markets") or len((book.get("sessions") or {})),
        "sync_url": sync_url(),
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
    }


def status(folder: Path | None = None) -> dict:
    folder = folder or _root()
    token = read_token(folder)
    return {
        "ok": True,
        "has_token": len(token) >= 48,
        "sync_url": sync_url(),
        "hint": (
            "PAPER_SYNC_TOKEN is set."
            if len(token) >= 48
            else "Open Paper Desk Sync signed into Cursor → Create computer link → put PAPER_SYNC_TOKEN in .env."
        ),
    }
