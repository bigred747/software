"""Load Schwab app credentials from the install folder .env. No API calls."""

from __future__ import annotations

import os
from pathlib import Path

INSTALL_ROOT = Path(__file__).resolve().parents[1]
ENV_PATH = INSTALL_ROOT / ".env"

KEYS = (
    "SCHWAB_APP_KEY",
    "SCHWAB_APP_SECRET",
    "SCHWAB_CALLBACK_URL",
    "SCHWAB_APP_NAME",
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


def credentials_ready() -> bool:
    """True when App Key and App Secret are both non-empty. Does not contact Schwab."""
    load_dotenv()
    return bool(_configured_value("SCHWAB_APP_KEY") and _configured_value("SCHWAB_APP_SECRET"))


def status_line() -> str:
    """Desk-facing status: missing | ready (not connected). Never enables Live."""
    if credentials_ready():
        return "ready (not connected)"
    return "missing"


def status_report() -> dict[str, object]:
    """Safe-check / CLI payload. No broker link and no OAuth token exchange."""
    load_dotenv()
    ready = credentials_ready()
    return {
        "name": "Schwab credentials",
        "ok": True,
        "ready": ready,
        "detail": status_line(),
        "kind": "health",
        "env_path": str(ENV_PATH),
        "callback": _configured_value("SCHWAB_CALLBACK_URL") or "https://127.0.0.1",
        "app_name": _configured_value("SCHWAB_APP_NAME") or "personal-market-data",
        "connected": False,
        "live": False,
    }
