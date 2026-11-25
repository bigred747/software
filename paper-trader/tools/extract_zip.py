"""Extract Paper-Trader.zip with Python (Windows Git tar cannot open zip files)."""

from __future__ import annotations

import sys
import zipfile
from pathlib import Path


def main(argv: list[str]) -> int:
    if len(argv) != 2:
        print("usage: extract_zip.py ZIPDIR DESTDIR", file=sys.stderr)
        return 2
    source = Path(argv[0])
    dest = Path(argv[1])
    if not source.is_file():
        print(f"missing zip: {source}", file=sys.stderr)
        return 1
    dest.mkdir(parents=True, exist_ok=True)
    try:
        with zipfile.ZipFile(source) as packed:
            packed.extractall(dest)
    except (OSError, zipfile.BadZipFile) as exc:
        print(f"extract failed: {exc}", file=sys.stderr)
        return 1
    launch = dest / "paper-trader" / "launch.py"
    if not launch.is_file():
        print(f"extract missing launch.py under {dest}", file=sys.stderr)
        return 1
    print(str(dest / "paper-trader"))
    return 0


if __name__ == "__main__":
    raise SystemExit(main(sys.argv[1:]))
