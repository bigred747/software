"""Pick the newest Paper Trader zip for Allow / Force Upgrade.

Reads paper-trader/VERSION inside each zip so an old plain Paper-Trader.zip
cannot beat a newer Paper-Trader (1).zip or versioned zip by filename alone.
"""

from __future__ import annotations

import re
import sys
import zipfile
from pathlib import Path


def version_key(text: str) -> tuple[int, ...]:
    match = re.search(r"(\d+(?:\.\d+)+)", text or "")
    if not match:
        return (0,)
    return tuple(int(part) for part in match.group(1).split("."))


def internal_version(path: Path) -> tuple[int, ...]:
    try:
        with zipfile.ZipFile(path) as packed:
            raw = packed.read("paper-trader/VERSION").decode("utf-8", errors="ignore").strip()
            return version_key(raw)
    except (OSError, KeyError, zipfile.BadZipFile, UnicodeError):
        return version_key(path.name)


def score(path: Path) -> tuple:
    name = path.name.lower()
    exact = 1 if name == "paper-trader.zip" else 0
    # Prefer names without " (1)" / " (2)" when seals tie.
    clean = 1 if "(" not in name else 0
    slim = 0 if "-slim" in name else 1
    return (internal_version(path), exact, clean, slim, path.stat().st_mtime)


def candidates(homes: list[Path]) -> list[Path]:
    found: list[Path] = []
    for folder in homes:
        if not folder.is_dir():
            continue
        for path in folder.glob("Paper-Trader*.zip"):
            if path.is_file():
                found.append(path)
    return found


def main() -> int:
    home = Path.home()
    roots = [
        home / "Downloads",
        home / "Desktop",
        home / "Documents",
        home / "OneDrive" / "Downloads",
        home / "OneDrive" / "Desktop",
        home / "OneDrive" / "Documents",
    ]
    one = __import__("os").environ.get("OneDrive")
    if one:
        roots.extend(
            [
                Path(one) / "Downloads",
                Path(one) / "Desktop",
                Path(one) / "Documents",
            ]
        )
    rows = candidates(roots)
    if not rows:
        print("NONE")
        return 1
    best = max(rows, key=score)
    print(str(best))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
