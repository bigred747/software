"""Load Schwab app credentials from the install folder .env. No API calls."""

from __future__ import annotations

import os
from pathlib import Path

INSTALL_ROOT = Path(__file__).resolve().parents[1]
ENV_PATH = INSTALL_ROOT / ".env"
KEYS_TEMPLATE = INSTALL_ROOT / "SCHWAB-KEYS.txt"
ENV_EXAMPLE = INSTALL_ROOT / ".env.example"

KEYS = (
    "SCHWAB_APP_KEY",
    "SCHWAB_APP_SECRET",
    "SCHWAB_CALLBACK_URL",
    "SCHWAB_APP_NAME",
)

PLACEHOLDER_VALUES = {
    "",
    "paste-app-key-here",
    "paste-app-secret-here",
    "your-app-key",
    "your-app-secret",
}

DEFAULT_ENV_TEXT = (
    "# Schwab keys — paste App Key and App Secret below\n"
    "# File: Documents\\Paper Trader\\.env\n"
    "# Never commit this file after pasting real secrets.\n"
    "\n"
    "SCHWAB_APP_KEY=paste-app-key-here\n"
    "SCHWAB_APP_SECRET=paste-app-secret-here\n"
    "SCHWAB_CALLBACK_URL=https://127.0.0.1\n"
    "SCHWAB_APP_NAME=personal-market-data\n"
)


def parse_env_text(text: str) -> dict[str, str]:
    """Tiny KEY=VALUE parser. Stdlib only. Ignores blank lines and # comments."""
    values: dict[str, str] = {}
    for raw in text.splitlines():
        line = raw.strip()
        if not line or line.startswith("#"):
            continue
        if line.startswith("export "):
            line = line[7:].strip()
        if "=" not in line:
            continue
        key, _, rest = line.partition("=")
        key = key.strip()
        if not key:
            continue
        value = rest.strip()
        if len(value) >= 2 and value[0] == value[-1] and value[0] in {"'", '"'}:
            value = value[1:-1]
        values[key] = value
    return values


def load_dotenv(path: Path | None = None) -> dict[str, str]:
    """Read .env into os.environ when the key is not already set. Missing file is fine."""
    target = path if path is not None else ENV_PATH
    if not target.is_file():
        return {}
    try:
        text = target.read_text(encoding="utf-8")
    except OSError:
        return {}
    parsed = parse_env_text(text)
    for key, value in parsed.items():
        if key in KEYS and key not in os.environ:
            os.environ[key] = value
    return {key: parsed[key] for key in KEYS if key in parsed}


def _configured_value(name: str) -> str:
    return (os.environ.get(name) or "").strip()


def _is_real_secret(value: str) -> bool:
    text = (value or "").strip()
    return bool(text) and text.lower() not in PLACEHOLDER_VALUES


def ensure_env_file(folder: Path | None = None) -> Path:
    """Create .env from SCHWAB-KEYS.txt / .env.example when missing. Never overwrites."""
    root = folder if folder is not None else INSTALL_ROOT
    target = root / ".env"
    if target.is_file():
        return target
    for name in ("SCHWAB-KEYS.txt", ".env.example"):
        source = root / name
        if not source.is_file():
            continue
        try:
            target.write_text(source.read_text(encoding="utf-8"), encoding="utf-8")
            return target
        except OSError:
            continue
    try:
        target.write_text(DEFAULT_ENV_TEXT, encoding="utf-8")
    except OSError:
        pass
    return target


def credentials_ready() -> bool:
    """True when App Key and App Secret are real (not empty/placeholder). No API call."""
    ensure_env_file()
    load_dotenv()
    return _is_real_secret(_configured_value("SCHWAB_APP_KEY")) and _is_real_secret(
        _configured_value("SCHWAB_APP_SECRET")
    )


def status_line() -> str:
    """Desk-facing status: missing | ready (not connected). Never enables Live."""
    if credentials_ready():
        return "ready (not connected)"
    return "missing"


def status_report() -> dict[str, object]:
    """Safe-check / CLI payload. Connection state comes from the local token store."""
    load_dotenv()
    ready = credentials_ready()
    connected = False
    connect_detail = "not connected"
    try:
        from paper_trader.schwab_auth import connection_status

        link = connection_status()
        connected = bool(link.get("connected"))
        connect_detail = str(link.get("detail") or connect_detail)
    except Exception:
        connected = False
    detail = status_line() if not connected else connect_detail
    return {
        "name": "Schwab credentials",
        "ok": True,
        "ready": ready,
        "detail": detail,
        "kind": "health",
        "env_path": str(ENV_PATH),
        "callback": _configured_value("SCHWAB_CALLBACK_URL") or "https://127.0.0.1",
        "app_name": _configured_value("SCHWAB_APP_NAME") or "personal-market-data",
        "connected": connected,
        "live": False,
    }
