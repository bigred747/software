"""Pick the newest Paper Trader zip for Allow / Force Upgrade.

Prefers plain Paper-Trader.zip, then the highest Paper-Trader-X.Y.Z(-slim).zip.
Never silently picks an older slim over a newer full zip.
"""

from __future__ import annotations

import re
import sys
from pathlib import Path


def version_key(name: str) -> tuple[int, ...]:
    match = re.search(r"(\d+(?:\.\d+)+)", name)
    if not match:
        return (0,)
    return tuple(int(part) for part in match.group(1).split("."))


def score(path: Path) -> tuple:
    name = path.name.lower()
    exact = 1 if name == "paper-trader.zip" else 0
    slim = 0 if "-slim" in name else 1
    return (exact, version_key(name), slim, path.stat().st_mtime)


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
