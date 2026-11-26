"""Windows embeddable Python has no tzdata — packaged zones must still load."""

from __future__ import annotations

import sys
import unittest
from datetime import datetime, timezone
from pathlib import Path
from unittest.mock import patch
from zoneinfo import ZoneInfo, ZoneInfoNotFoundError

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))

import paper_trader.zones as zones


class ZoneTests(unittest.TestCase):
    def tearDown(self) -> None:
        zones.zone.cache_clear()

    def test_denver_and_new_york_load(self) -> None:
        denver = zones.zone("America/Denver")
        york = zones.zone("America/New_York")
        self.assertIsInstance(denver, ZoneInfo)
        self.assertIsInstance(york, ZoneInfo)
        stamp = datetime(2026, 7, 4, 18, 0, tzinfo=timezone.utc)
        self.assertEqual(stamp.astimezone(denver).hour, 12)
        self.assertEqual(stamp.astimezone(york).hour, 14)

    def test_packaged_files_used_when_system_zones_missing(self) -> None:
        zones.zone.cache_clear()
        real = ZoneInfo

        def boom(key: str):
            raise ZoneInfoNotFoundError(key)

        boom.from_file = real.from_file  # type: ignore[attr-defined]
        with patch.object(zones, "ZoneInfo", boom):
            loaded = zones.zone("America/Denver")
        self.assertEqual(str(loaded), "America/Denver")
        stamp = datetime(2026, 1, 15, 18, 0, tzinfo=timezone.utc)
        self.assertEqual(stamp.astimezone(loaded).hour, 11)


if __name__ == "__main__":
    unittest.main()
