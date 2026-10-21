"""Start Paper Trader from this folder, even when Windows uses another Python."""

from __future__ import annotations

import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent
sys.path.insert(0, str(ROOT))

try:
    from paper_trader.cli import main
except ModuleNotFoundError:
    print("Paper Trader could not find its program files in:")
    print(ROOT)
    print("Right-click Paper-Trader.zip, choose Extract All, then run 1 Allow Paper Trader.bat.")
    raise SystemExit(1)

raise SystemExit(main(sys.argv[1:] or ["serve"]))
