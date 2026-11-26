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

# Schwab Developer Portal labels these Client ID / Client Secret.
ALIAS_TO_CANONICAL = {
    "SCHWAB_CLIENT_ID": "SCHWAB_APP_KEY",
    "SCHWAB_CLIENT_SECRET": "SCHWAB_APP_SECRET",
}

PLACEHOLDER_VALUES = {
    "",
    "paste-app-key-here",
    "paste-app-secret-here",
    "paste-client-id-here",
    "paste-client-secret-here",
    "your-app-key",
    "your-app-secret",
    "your-client-id",
    "your-client-secret",
}

DEFAULT_ENV_TEXT = (
    "# Schwab keys — paste Client ID and Client Secret from the Developer Portal\n"
    "# File: Documents\\Paper Trader\\.env\n"
    "# Portal Client ID     → SCHWAB_APP_KEY\n"
    "# Portal Client Secret → SCHWAB_APP_SECRET\n"
    "# Never commit this file after pasting real secrets.\n"
    "\n"
    "SCHWAB_APP_KEY=paste-app-key-here\n"
    "SCHWAB_APP_SECRET=paste-app-secret-here\n"
    "SCHWAB_CALLBACK_URL=https://127.0.0.1\n"
    "SCHWAB_APP_NAME=personal-market-data\n"
)

REQUIRED_DEFAULTS = {
    "SCHWAB_APP_KEY": "paste-app-key-here",
    "SCHWAB_APP_SECRET": "paste-app-secret-here",
    "SCHWAB_CALLBACK_URL": "https://127.0.0.1",
    "SCHWAB_APP_NAME": "personal-market-data",
}


def read_env_text(path: Path) -> str:
    """Read .env even when Notepad saved it as UTF-16 (common on Windows)."""
    raw = path.read_bytes()
    if raw.startswith(b"\xff\xfe") or raw.startswith(b"\xfe\xff"):
        return raw.decode("utf-16")
    if raw.startswith(b"\xef\xbb\xbf"):
        return raw.decode("utf-8-sig")
    # UTF-16 LE without BOM often has NULs between ASCII chars.
    if b"\x00" in raw[:64]:
        try:
            return raw.decode("utf-16-le")
        except UnicodeDecodeError:
            pass
    for encoding in ("utf-8-sig", "utf-8", "cp1252"):
        try:
            return raw.decode(encoding)
        except UnicodeDecodeError:
            continue
    return raw.decode("utf-8", errors="replace")


def parse_env_text(text: str) -> dict[str, str]:
    """Tiny KEY=VALUE parser. Stdlib only. Ignores blank lines and # comments."""
    values: dict[str, str] = {}
    # Strip NULs left over from a bad encoding guess.
    text = text.replace("\x00", "")
    for raw in text.splitlines():
        line = raw.strip()
        if not line or line.startswith("#"):
            continue
        if line.startswith("export "):
            line = line[7:].strip()
        if "=" not in line:
            continue
        key, _, rest = line.partition("=")
        key = key.strip().lstrip("\ufeff")
        if not key:
            continue
        value = rest.strip()
        if len(value) >= 2 and value[0] == value[-1] and value[0] in {"'", '"'}:
            value = value[1:-1]
        values[key] = value
    return values


def canonicalize_credentials(values: dict[str, str]) -> dict[str, str]:
    """Map portal Client ID/Secret aliases onto SCHWAB_APP_KEY / SCHWAB_APP_SECRET."""
    out = dict(values)
    for alias, canonical in ALIAS_TO_CANONICAL.items():
        alias_value = (out.get(alias) or "").strip()
        if not _is_real_secret(alias_value):
            continue
        current = (out.get(canonical) or "").strip()
        if not _is_real_secret(current):
            out[canonical] = alias_value
    return out


def _env_file_values(path: Path | None = None) -> dict[str, str]:
    """Read canonical Schwab keys from the .env file only (ignore process env)."""
    target = path if path is not None else ENV_PATH
    if not target.is_file():
        return {}
    try:
        text = read_env_text(target)
    except OSError:
        return {}
    return canonicalize_credentials(parse_env_text(text))


def load_dotenv(path: Path | None = None) -> dict[str, str]:
    """Read .env into os.environ. Real .env secrets replace sticky placeholders."""
    target = path if path is not None else ENV_PATH
    parsed = _env_file_values(target)
    if not parsed:
        return {}
    for key, value in parsed.items():
        if key not in KEYS:
            continue
        # Never sticky-write placeholders into the process env (tests + multi-folder installs).
        if key in {"SCHWAB_APP_KEY", "SCHWAB_APP_SECRET"} and not _is_real_secret(value):
            continue
        current = (os.environ.get(key) or "").strip()
        # Prefer the file when the process still holds paste-... leftovers.
        if key in os.environ and _is_real_secret(current) and not _is_real_secret(value):
            continue
        if key in os.environ and _is_real_secret(current) and current == value:
            continue
        os.environ[key] = value
    for alias, canonical in ALIAS_TO_CANONICAL.items():
        if alias in os.environ and _is_real_secret(os.environ[alias]):
            if canonical not in os.environ or not _is_real_secret(os.environ.get(canonical, "")):
                os.environ[canonical] = os.environ[alias]
        if alias in parsed and _is_real_secret(parsed[alias]):
            if canonical not in os.environ or not _is_real_secret(os.environ.get(canonical, "")):
                os.environ[canonical] = parsed[alias]
    return {key: parsed[key] for key in KEYS if key in parsed}


def _configured_value(name: str) -> str:
    """Prefer real secrets from .env over sticky placeholder values in os.environ."""
    file_values = _env_file_values()
    from_file = (file_values.get(name) or "").strip()
    if _is_real_secret(from_file):
        return from_file
    for alias, canonical in ALIAS_TO_CANONICAL.items():
        if canonical != name:
            continue
        alias_file = (file_values.get(alias) or "").strip()
        if _is_real_secret(alias_file):
            return alias_file
    direct = (os.environ.get(name) or "").strip()
    if _is_real_secret(direct):
        return direct
    for alias, canonical in ALIAS_TO_CANONICAL.items():
        if canonical != name:
            continue
        alias_value = (os.environ.get(alias) or "").strip()
        if _is_real_secret(alias_value):
            return alias_value
    return from_file or direct


def _is_real_secret(value: str) -> bool:
    text = (value or "").strip()
    return bool(text) and text.lower() not in PLACEHOLDER_VALUES


def ensure_env_file(folder: Path | None = None) -> Path:
    """Create .env from SCHWAB-KEYS.txt / .env.example when missing. Never overwrites secrets."""
    root = folder if folder is not None else INSTALL_ROOT
    target = root / ".env"
    if target.is_file():
        sync_env_from_keys_file(root)
        return install_schwab_env(root)
    for name in ("SCHWAB-KEYS.txt", ".env.example"):
        source = root / name
        if not source.is_file():
            continue
        try:
            text = read_env_text(source)
            canonical = canonicalize_credentials(parse_env_text(text))
            # Prefer a normalized .env so APP_KEY/SECRET are always present.
            if any(_is_real_secret(canonical.get(key, "")) for key in ("SCHWAB_APP_KEY", "SCHWAB_APP_SECRET")):
                target.write_text(_format_env(canonical, text), encoding="utf-8")
            else:
                target.write_text(text if text.strip() else DEFAULT_ENV_TEXT, encoding="utf-8")
            sync_env_from_keys_file(root)
            return install_schwab_env(root)
        except OSError:
            continue
    try:
        target.write_text(DEFAULT_ENV_TEXT, encoding="utf-8")
    except OSError:
        pass
    sync_env_from_keys_file(root)
    return install_schwab_env(root)


def install_schwab_env(folder: Path | None = None) -> Path:
    """Install the four Schwab keys into .env. Fills missing lines only; never clobbers real secrets."""
    root = folder if folder is not None else INSTALL_ROOT
    target = root / ".env"
    current: dict[str, str] = {}
    if target.is_file():
        try:
            current = canonicalize_credentials(parse_env_text(read_env_text(target)))
        except OSError:
            current = {}
    changed = False
    for key, default in REQUIRED_DEFAULTS.items():
        value = (current.get(key) or "").strip()
        if not value:
            current[key] = default
            changed = True
    if not target.is_file() or changed:
        try:
            target.write_text(_format_env(current), encoding="utf-8")
        except OSError:
            return target
    load_dotenv(target)
    return target


def offer_env_editor(folder: Path | None = None) -> bool:
    """On Windows, open only .env in Notepad once when keys are still placeholders.

    Never opens SCHWAB-KEYS.txt — that file is a template in the bot folder.
    Real keys live in Documents\\Paper Trader\\.env.
    """
    root = folder if folder is not None else INSTALL_ROOT
    target = install_schwab_env(root)
    marker = root / ".schwab_env_opened"
    if marker.is_file():
        return False
    load_dotenv(target)
    if credentials_ready():
        return False
    if os.name != "nt":
        return False
    try:
        import subprocess

        # Only .env — never SCHWAB-KEYS.txt (placeholders / template).
        subprocess.Popen(["notepad.exe", str(target)], close_fds=True)
        marker.write_text("opened\n", encoding="utf-8")
        return True
    except OSError:
        return False


def _format_env(values: dict[str, str], template_text: str = "") -> str:
    """Write a small .env with canonical keys. Keep helpful comments when possible."""
    callback = values.get("SCHWAB_CALLBACK_URL") or "https://127.0.0.1"
    app_name = values.get("SCHWAB_APP_NAME") or "personal-market-data"
    header = (
        "# Schwab keys — Client ID / Client Secret from Developer Portal\n"
        "# Portal Client ID     → SCHWAB_APP_KEY (alias: SCHWAB_CLIENT_ID)\n"
        "# Portal Client Secret → SCHWAB_APP_SECRET (alias: SCHWAB_CLIENT_SECRET)\n"
        "# Callback must match the portal exactly (https://127.0.0.1).\n"
        "# Never commit this file after pasting real secrets.\n"
        "\n"
    )
    if "SCHWAB_APP_KEY" in template_text or "SCHWAB_CLIENT_ID" in template_text:
        # Keep a short header even when copying from keys file.
        pass
    body = (
        f"SCHWAB_APP_KEY={values.get('SCHWAB_APP_KEY', 'paste-app-key-here')}\n"
        f"SCHWAB_APP_SECRET={values.get('SCHWAB_APP_SECRET', 'paste-app-secret-here')}\n"
        f"SCHWAB_CALLBACK_URL={callback}\n"
        f"SCHWAB_APP_NAME={app_name}\n"
    )
    return header + body


def sync_env_from_keys_file(folder: Path | None = None) -> Path:
    """If SCHWAB-KEYS.txt has real secrets and .env still has placeholders, copy them in.

    Reinstalls keep .env, but people often paste into SCHWAB-KEYS.txt as the comments say.
    This one-way sync never overwrites real .env secrets with placeholders.
    """
    root = folder if folder is not None else INSTALL_ROOT
    env_path = root / ".env"
    keys_path = root / "SCHWAB-KEYS.txt"
    if not env_path.is_file() or not keys_path.is_file():
        return env_path
    try:
        env_values = canonicalize_credentials(parse_env_text(read_env_text(env_path)))
        key_values = canonicalize_credentials(parse_env_text(read_env_text(keys_path)))
    except OSError:
        return env_path
    changed = False
    for key in ("SCHWAB_APP_KEY", "SCHWAB_APP_SECRET", "SCHWAB_CALLBACK_URL", "SCHWAB_APP_NAME"):
        incoming = (key_values.get(key) or "").strip()
        current = (env_values.get(key) or "").strip()
        if key in {"SCHWAB_APP_KEY", "SCHWAB_APP_SECRET"}:
            if _is_real_secret(incoming) and not _is_real_secret(current):
                env_values[key] = incoming
                changed = True
        elif incoming and (not current or current in PLACEHOLDER_VALUES):
            env_values[key] = incoming
            changed = True
    if not changed:
        return env_path
    try:
        env_path.write_text(_format_env(env_values), encoding="utf-8")
    except OSError:
        return env_path
    # Refresh process env for this run when keys were only placeholders.
    for key in KEYS:
        value = (env_values.get(key) or "").strip()
        if _is_real_secret(value) or key in {"SCHWAB_CALLBACK_URL", "SCHWAB_APP_NAME"}:
            if key not in os.environ or not _is_real_secret(os.environ.get(key, "")):
                os.environ[key] = value
    return env_path


def heal_env_from_documents(folder: Path | None = None) -> Path:
    """If this folder's .env still has placeholders, copy real keys from Documents\\Paper Trader\\.env."""
    root = folder if folder is not None else INSTALL_ROOT
    target = root / ".env"
    ensure_env_file(root)
    current = _env_file_values(target)
    if _is_real_secret(current.get("SCHWAB_APP_KEY", "")) and _is_real_secret(
        current.get("SCHWAB_APP_SECRET", "")
    ):
        load_dotenv(target)
        return target
    home = Path.home()
    candidates = [
        home / "Documents" / "Paper Trader" / ".env",
        home / "OneDrive" / "Documents" / "Paper Trader" / ".env",
    ]
    one_drive = os.environ.get("OneDrive")
    if one_drive:
        candidates.append(Path(one_drive) / "Documents" / "Paper Trader" / ".env")
    # Also READ from side-by-side setup folders (working 1.10.59 desks). Never write them.
    for base in (home / "Documents", home / "OneDrive" / "Documents"):
        if not base.is_dir():
            continue
        try:
            for child in sorted(base.glob("PaperTrader-setup-*/paper-trader/.env")):
                candidates.append(child)
            for child in sorted(base.glob("PaperTrader-*/paper-trader/.env")):
                candidates.append(child)
        except OSError:
            pass
    donor: dict[str, str] = {}
    donor_path: Path | None = None
    for path in candidates:
        try:
            if not path.is_file():
                continue
            if path.resolve() == target.resolve():
                continue
        except OSError:
            continue
        values = _env_file_values(path)
        if _is_real_secret(values.get("SCHWAB_APP_KEY", "")) and _is_real_secret(
            values.get("SCHWAB_APP_SECRET", "")
        ):
            donor = values
            donor_path = path
            break
    if not donor:
        load_dotenv(target)
        return target
    merged = dict(current)
    for key in KEYS:
        incoming = (donor.get(key) or "").strip()
        if key in {"SCHWAB_APP_KEY", "SCHWAB_APP_SECRET"}:
            if _is_real_secret(incoming):
                merged[key] = incoming
        elif incoming and not (merged.get(key) or "").strip():
            merged[key] = incoming
    try:
        # Rewrite THIS folder only as UTF-8. Never modify the donor .env.
        target.write_text(_format_env(merged), encoding="utf-8", newline="\n")
    except OSError:
        return target
    # Optional: copy Schwab tokens into THIS folder only when missing (never touch donor).
    try:
        token_dest = root / "schwab_tokens.json"
        if donor_path is not None and not token_dest.is_file():
            token_src = donor_path.with_name("schwab_tokens.json")
            if token_src.is_file() and token_src.resolve() != token_dest.resolve():
                token_dest.write_bytes(token_src.read_bytes())
    except OSError:
        pass
    load_dotenv(target)
    return target


def credentials_ready() -> bool:
    """True when App Key and App Secret are real (not empty/placeholder). No API call."""
    ensure_env_file(ENV_PATH.parent)
    heal_env_from_documents(ENV_PATH.parent)
    load_dotenv(ENV_PATH)
    # Also accept a good Documents\\Paper Trader\\.env even if this folder's file lagged.
    if _is_real_secret(_configured_value("SCHWAB_APP_KEY")) and _is_real_secret(
        _configured_value("SCHWAB_APP_SECRET")
    ):
        return True
    desk = Path.home() / "Documents" / "Paper Trader" / ".env"
    if desk.is_file() and desk.resolve() != ENV_PATH.resolve():
        values = _env_file_values(desk)
        return _is_real_secret(values.get("SCHWAB_APP_KEY", "")) and _is_real_secret(
            values.get("SCHWAB_APP_SECRET", "")
        )
    return False


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
