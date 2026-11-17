"""Launch and folder checks. Keep Paper Trader away from foreign bots and temp zips."""

from __future__ import annotations

from pathlib import Path

# Names that must never share a folder with this desk (path segment match).
FOREIGN_MARKERS = (
    "artex",
    "art-ex",
    "art_ex",
    "art ex",
)


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
            if marker.replace(" ", "") == compact or marker == cleaned or marker == part:
                return True
            if marker.replace(" ", "") in compact and len(marker.replace(" ", "")) >= 5:
                # Catch folder names like ArtexBot, MyArtex, artex-trader.
                if compact.startswith(marker.replace(" ", "")) or compact.endswith(marker.replace(" ", "")):
                    return True
                if marker.replace(" ", "") in compact:
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
                "Use Documents\\Paper Trader only. Artex cannot share this desk's files."
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
        "detail": "Artex and other foreign-bot folders cannot share this desk's files",
    }
