"""Launch and folder checks. Keep Paper Trader away from foreign bots and temp zips."""

from __future__ import annotations

from pathlib import Path

# Path-segment markers that must never share a folder with this desk.
# Own desk only — refuse Artex and other foreign-bot / stray-hack folder names.
FOREIGN_MARKERS = (
    "artex",
    "art-ex",
    "art_ex",
    "art ex",
    "artexbot",
    "artex-bot",
    "artex_bot",
    "quantumtrade",
    "quantum-trade",
    "quantum_trade",
)

# Script suffixes that do not belong next to this desk's launchers.
FOREIGN_SCRIPT_SUFFIXES = (".ps1", ".vbs", ".wsf", ".jscript", ".hta")


def path_text(path: Path) -> str:
    return str(path).replace("/", "\\").lower()


def is_temp_zip_extract(path: Path) -> bool:
    """True when Windows opened a file from inside a zip into Temp."""
    text = path_text(path)
    return "\\temp\\" in text and ".zip." in text


def path_looks_foreign(path: Path) -> bool:
    """True when a path segment names Artex or another blocked foreign bot."""
    text = path_text(path)
    parts = [piece for piece in text.replace("/", "\\").split("\\") if piece]
    for part in parts:
        cleaned = part.replace("_", " ").replace("-", " ")
        compact = part.replace("_", "").replace("-", "").replace(" ", "")
        for marker in FOREIGN_MARKERS:
            token = marker.replace(" ", "")
            if marker == cleaned or marker == part or token == compact:
                return True
            if token and len(token) >= 5 and token in compact:
                # Catch folder names like ArtexBot, MyArtex, artex-trader.
                if compact.startswith(token) or compact.endswith(token) or token in compact:
                    return True
    return False


def foreign_marker_hit(path: Path) -> str:
    text = path_text(path)
    compact = text.replace("\\", "").replace("/", "").replace("_", "").replace("-", "").replace(" ", "")
    for marker in FOREIGN_MARKERS:
        token = marker.replace(" ", "")
        if token and token in compact:
            return marker
    return ""


def assert_safe_launch(paths: list[Path]) -> None:
    for path in paths:
        if is_temp_zip_extract(path):
            raise ValueError(
                "Paper Trader will not start from a temporary zip folder. "
                "Copy the paper-trader folder to Documents, then double-click Start-Paper-Trader.bat there."
            )
        if path_looks_foreign(path):
            hit = foreign_marker_hit(path) or "artex"
            raise ValueError(
                "Paper Trader will not run from an "
                + hit
                + " folder or any foreign-bot path. "
                "Use Documents\\Paper Trader only. Artex cannot share this desk's files or place trades."
            )


def assert_safe_install(source: Path, dest: Path) -> None:
    """Refuse to install from or into an Artex / foreign-bot folder."""
    for path, label in ((source, "source"), (dest, "destination")):
        if path_looks_foreign(path):
            hit = foreign_marker_hit(path) or "artex"
            raise ValueError(
                "Paper Trader will not install "
                + label
                + " through an "
                + hit
                + " folder. Keep this desk in Documents\\Paper Trader only."
            )
        if is_temp_zip_extract(path):
            raise ValueError(
                "Paper Trader will not install from a temporary zip folder. "
                "Extract to Documents first, then run 1 Allow Paper Trader.bat."
            )


def isolation_status(paths: list[Path]) -> dict[str, str | bool]:
    """Safe-check row: foreign bots (Artex) kept out of this desk's folders."""
    for path in paths:
        if path_looks_foreign(path):
            hit = foreign_marker_hit(path) or "artex"
            return {
                "name": "Foreign bots kept out",
                "ok": False,
                "detail": "This path looks like "
                + hit
                + ". Move Paper Trader to Documents\\Paper Trader and reopen",
            }
        if is_temp_zip_extract(path):
            return {
                "name": "Foreign bots kept out",
                "ok": False,
                "detail": "Running from a temporary zip fold. Extract to Documents\\Paper Trader first",
            }
    return {
        "name": "Foreign bots kept out",
        "ok": True,
        "detail": "Artex and other foreign-bot folders cannot share this desk's files or place trades",
    }


def artex_blocked_status(paths: list[Path]) -> dict[str, str | bool]:
    """Dedicated Safe row: Artex path blocked (green when clear)."""
    for path in paths:
        hit = foreign_marker_hit(path)
        if hit and "artex" in hit.replace(" ", "").replace("-", "").replace("_", ""):
            return {
                "name": "Artex blocked",
                "ok": False,
                "detail": "Path looks like Artex. Move to Documents\\Paper Trader only",
            }
        if path_looks_foreign(path) and "artex" in path_text(path):
            return {
                "name": "Artex blocked",
                "ok": False,
                "detail": "Path looks like Artex. Move to Documents\\Paper Trader only",
            }
    return {
        "name": "Artex blocked",
        "ok": True,
        "detail": "Will not start, install, or share files from an Artex folder",
    }


def temp_zip_blocked_status(paths: list[Path]) -> dict[str, str | bool]:
    for path in paths:
        if is_temp_zip_extract(path):
            return {
                "name": "Temp zips blocked",
                "ok": False,
                "detail": "Running from a temporary zip fold. Extract to Documents first",
            }
    return {
        "name": "Temp zips blocked",
        "ok": True,
        "detail": "Will not start from a Temp unzip fold",
    }


def desk_files_alone_status(desk_root: Path, file_paths: list[Path]) -> dict[str, str | bool]:
    """Paper book · breaker · learn stay with this desk — never under a foreign path."""
    root = desk_root.resolve()
    for path in file_paths:
        resolved = path.resolve()
        if path_looks_foreign(resolved) or is_temp_zip_extract(resolved):
            hit = foreign_marker_hit(resolved) or "foreign"
            return {
                "name": "Desk files alone",
                "ok": False,
                "detail": "A desk file sits under "
                + hit
                + ". Keep paper book, breaker, and learn with this desk only",
            }
        try:
            resolved.relative_to(root)
        except ValueError:
            # State may live next to the package (home shelf) — still refuse foreign markers above.
            pass
    return {
        "name": "Desk files alone",
        "ok": True,
        "detail": "Paper book, breaker, and learn stay with this desk only — not shared with foreign bots",
    }


def foreign_scripts_status(desk_root: Path) -> dict[str, str | bool]:
    """Refuse PowerShell / VBS / HTA scripts sitting next to the desk launchers."""
    root = desk_root.resolve()
    hits: list[str] = []
    try:
        for path in root.iterdir():
            if not path.is_file():
                continue
            lower = path.name.lower()
            if any(lower.endswith(suffix) for suffix in FOREIGN_SCRIPT_SUFFIXES):
                hits.append(path.name)
            # Block unsigned side-car .cmd next to Start bats (bats themselves are scanned elsewhere).
            if lower.endswith(".cmd") and "paper" not in lower:
                hits.append(path.name)
    except OSError:
        return {
            "name": "Foreign scripts refused",
            "ok": False,
            "detail": "Could not read the desk folder for script checks",
        }
    if hits:
        return {
            "name": "Foreign scripts refused",
            "ok": False,
            "detail": "Remove foreign script(s) from the desk folder: " + ", ".join(hits[:6]),
        }
    return {
        "name": "Foreign scripts refused",
        "ok": True,
        "detail": "No PowerShell, VBS, or HTA scripts next to the launchers",
    }
