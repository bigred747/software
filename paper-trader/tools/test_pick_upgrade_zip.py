"""Quick check: plain old Paper-Trader.zip loses to a newer sealed zip."""

from __future__ import annotations

import tempfile
import zipfile
from pathlib import Path

from pick_upgrade_zip import pick


def _zip_with_version(path: Path, version: str) -> None:
    with zipfile.ZipFile(path, "w") as packed:
        packed.writestr("paper-trader/VERSION", version + "\n")


def main() -> None:
    with tempfile.TemporaryDirectory() as folder:
        home = Path(folder)
        downloads = home / "Downloads"
        downloads.mkdir()
        old = downloads / "Paper-Trader.zip"
        new = downloads / "Paper-Trader (3).zip"
        _zip_with_version(old, "1.9.17")
        _zip_with_version(new, "1.10.57")
        chosen = pick(home)
        assert chosen == new, chosen
        print("ok", chosen.name)


if __name__ == "__main__":
    main()
