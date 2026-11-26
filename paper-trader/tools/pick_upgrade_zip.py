"""Pick the newest Paper Trader zip by internal VERSION seal.

Never prefer a plain Paper-Trader.zip just because of its filename.
An old 1.9.x Paper-Trader.zip must lose to Paper-Trader (3).zip with 1.10.x.
"""

from __future__ import annotations

import re
import sys
import zipfile
from pathlib import Path

# Refuse to auto-pick desks older than this (1.10.40 was Rick's last known-good open).
MIN_SEAL = (1, 10, 40)


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
        return (0,)


def score(path: Path) -> tuple:
    ver = internal_version(path)
    name = path.name.lower()
    # Full packs beat slim. Newer mtime breaks ties. Filename is last resort only.
    slim = 0 if "-slim" in name else 1
    return (ver, slim, path.stat().st_mtime, name)


def candidates(homes: list[Path]) -> list[Path]:
    found: list[Path] = []
    for folder in homes:
        if not folder.is_dir():
            continue
        for path in folder.glob("Paper-Trader*.zip"):
            if path.is_file():
                found.append(path)
    return found


def roots_for(home: Path) -> list[Path]:
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
    return roots


def pick(home: Path | None = None, *, min_seal: tuple[int, ...] = MIN_SEAL) -> Path | None:
    home = Path(home or Path.home())
    rows = candidates(roots_for(home))
    if not rows:
        return None
    ranked = sorted(rows, key=score, reverse=True)
    for path in ranked:
        if internal_version(path) >= min_seal:
            return path
    # Nothing new enough — still return the highest seal so the caller can warn.
    return ranked[0]


def main() -> int:
    best = pick()
    if best is None:
        print("NONE")
        return 1
    ver = internal_version(best)
    if ver < MIN_SEAL:
        # Print path anyway but exit 2 so bats can refuse ancient zips.
        print(str(best))
        print(f"OLD_SEAL:{'.'.join(str(part) for part in ver)}", file=sys.stderr)
        return 2
    print(str(best))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
