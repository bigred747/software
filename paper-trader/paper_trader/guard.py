"""Launch checks. The desk stays in a normal folder on this computer."""

from __future__ import annotations

from pathlib import Path


def is_temp_zip_extract(path: Path) -> bool:
    """True when Windows opened a file from inside a zip into Temp."""
    text = str(path).replace("/", "\\").lower()
    return "\\temp\\" in text and ".zip." in text


def assert_safe_launch(paths: list[Path]) -> None:
    for path in paths:
        if is_temp_zip_extract(path):
            raise ValueError(
                "Paper Trader will not start from a temporary zip folder. "
                "Copy the paper-trader folder to Documents, then double-click Start-Paper-Trader.bat there."
            )
