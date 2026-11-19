"""Breadth boxes fail closed for NYSE internals. VIX only from a real quote."""

from __future__ import annotations

import sys
import unittest
from pathlib import Path
from unittest.mock import patch

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))

from paper_trader.breadth import KEYS, NYSE_KEYS, chip_value, snapshot, tone_for


class BreadthFailClosedTests(unittest.TestCase):
    def test_snapshot_keeps_nyse_null_even_when_vix_answers(self) -> None:
        fake = {
            "price": 18.5,
            "bars": [
                {"date": "2026-11-17", "open": 17, "high": 19, "low": 16, "close": 17.2, "volume": 0},
                {"date": "2026-11-18", "open": 18, "high": 19, "low": 17, "close": 18.5, "volume": 0},
            ],
        }
        with patch("paper_trader.breadth.quotes.fetch_frame", return_value=fake):
            payload = snapshot()
        self.assertTrue(payload["ready"])
        self.assertTrue(payload["will_not_invent"])
        self.assertEqual(payload["source"], "yahoo:^VIX")
        self.assertEqual(payload["values"]["VIX"], 18.5)
        self.assertAlmostEqual(payload["changes"]["VIX"], 1.3)
        self.assertEqual(payload["tones"]["VIX"], "bear")
        self.assertEqual(len(payload["vixBars"]), 2)
        self.assertIn("strictly lower", payload["edge_vix_rule"])
        for key in NYSE_KEYS:
            self.assertIsNone(payload["values"][key])
            self.assertIsNone(payload["tones"][key])
        self.assertIn("will not paint fake numbers", payload["note"])

    def test_snapshot_stays_empty_when_vix_feed_fails(self) -> None:
        with patch("paper_trader.breadth.quotes.fetch_frame", side_effect=ValueError("No market quote")):
            payload = snapshot()
        self.assertFalse(payload["ready"])
        self.assertIsNone(payload["source"])
        for key in KEYS:
            self.assertIsNone(payload["values"][key])

    def test_chip_value_rejects_non_numbers(self) -> None:
        self.assertIsNone(chip_value(None))
        self.assertIsNone(chip_value(""))
        self.assertIsNone(chip_value("No feed"))
        self.assertIsNone(chip_value(float("nan")))
        self.assertEqual(chip_value(12.5), "12.5")
        self.assertEqual(chip_value(-842), "-842")

    def test_tone_rules(self) -> None:
        self.assertEqual(tone_for("VIX", 20, -1.2), "bull")
        self.assertEqual(tone_for("VIX", 20, 1.2), "bear")
        self.assertEqual(tone_for("TRIN", 0.7, None), "bull")
        self.assertEqual(tone_for("TRIN", 1.4, None), "bear")
        self.assertEqual(tone_for("TICK", 400, None), "bull")
        self.assertEqual(tone_for("ADD", -200, None), "bear")
        self.assertIsNone(tone_for("TICK", None, None))


if __name__ == "__main__":
    unittest.main()
