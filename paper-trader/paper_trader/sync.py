"""Push / pull the paper book between computer and phone via the sync bridge."""

from __future__ import annotations

import json
import os
import secrets
import urllib.error
import urllib.parse
import urllib.request
from pathlib import Path

DEFAULT_SYNC_URL = "https://paper-desk-sync.apps.grok.bot"
ENV_CODE = "PAPER_SYNC_CODE"
ENV_URL = "PAPER_SYNC_URL"


def _root() -> Path:
    return Path(__file__).resolve().parents[1]


def sync_url() -> str:
    return (os.environ.get(ENV_URL) or DEFAULT_SYNC_URL).rstrip("/")


def read_code(folder: Path | None = None) -> str:
    folder = folder or _root()
    env_path = folder / ".env"
    if env_path.is_file():
        try:
            text = env_path.read_text(encoding="utf-8", errors="ignore")
        except OSError:
            text = ""
        for line in text.splitlines():
            if line.startswith(f"{ENV_CODE}="):
                return line.split("=", 1)[1].strip().strip('"').strip("'").upper()
    return (os.environ.get(ENV_CODE) or "").strip().upper()


def ensure_code(folder: Path | None = None) -> str:
    folder = folder or _root()
    code = read_code(folder)
    if len(code) >= 6:
        return code
    code = "DESK-" + secrets.token_hex(3).upper()
    env_path = folder / ".env"
    try:
        existing = env_path.read_text(encoding="utf-8") if env_path.is_file() else ""
    except OSError:
        existing = ""
    if f"{ENV_CODE}=" in existing:
        lines = []
        for line in existing.splitlines():
            if line.startswith(f"{ENV_CODE}="):
                lines.append(f"{ENV_CODE}={code}")
            else:
                lines.append(line)
        text = "\n".join(lines) + ("\n" if existing.endswith("\n") else "")
    else:
        block = (
            f"\n# Same code on iPhone Journal → desk sync code\n"
            f"{ENV_CODE}={code}\n"
            f"{ENV_URL}={DEFAULT_SYNC_URL}\n"
        )
        text = existing.rstrip() + block
    try:
        env_path.write_text(text, encoding="utf-8")
    except OSError:
        pass
    os.environ[ENV_CODE] = code
    return code


def _request(method: str, code: str, payload: dict | None = None) -> dict:
    url = f"{sync_url()}/api/book?code={urllib.parse.quote(code)}"
    data = None
    headers = {
        "Accept": "application/json",
        "X-Desk-Code": code,
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
    code = ensure_code(folder)
    result = _request("PUT", code, book)
    return {
        "ok": True,
        "action": "push",
        "desk_code": code,
        "markets": result.get("markets") or len((book.get("sessions") or {})),
        "sync_url": sync_url(),
    }


def pull_book(folder: Path | None = None) -> dict:
    code = ensure_code(folder)
    result = _request("GET", code)
    book = result.get("book")
    if not isinstance(book, dict):
        raise ValueError("Sync bridge returned no paper book.")
    return {
        "ok": True,
        "action": "pull",
        "desk_code": code,
        "markets": result.get("markets") or len((book.get("sessions") or {})),
        "updated_at": result.get("updated_at"),
        "book": book,
        "sync_url": sync_url(),
    }


def status(folder: Path | None = None) -> dict:
    code = ensure_code(folder)
    return {"ok": True, "desk_code": code, "sync_url": sync_url()}
