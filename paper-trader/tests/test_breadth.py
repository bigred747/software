"""Breadth boxes: Schwab paints NYSE internals when connected; else fail closed. VIX from Yahoo."""

from __future__ import annotations

import sys
import unittest
from pathlib import Path
from unittest.mock import patch

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))

from paper_trader.breadth import KEYS, NYSE_KEYS, chip_value, snapshot, tone_for
from paper_trader.schwab_auth import _quote_last_and_change, fetch_breadth_quotes


class BreadthFailClosedTests(unittest.TestCase):
    def test_snapshot_keeps_nyse_null_even_when_vix_answers(self) -> None:
        fake = {
            "price": 18.5,
            "bars": [
                {"date": "2026-11-17", "open": 17, "high": 19, "low": 16, "close": 17.2, "volume": 0},
                {"date": "2026-11-18", "open": 18, "high": 19, "low": 17, "close": 18.5, "volume": 0},
            ],
        }
        with (
            patch("paper_trader.breadth.quotes.fetch_frame", return_value=fake),
            patch("paper_trader.schwab_auth.fetch_breadth_quotes", return_value={}),
        ):
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

    def test_snapshot_paints_schwab_nyse_with_vix(self) -> None:
        fake = {
            "price": 16.2,
            "bars": [
                {"date": "2026-11-17", "open": 17, "high": 19, "low": 16, "close": 17.0, "volume": 0},
                {"date": "2026-11-18", "open": 16, "high": 17, "low": 15, "close": 16.2, "volume": 0},
            ],
        }
        schwab = {
            "TICK": {"value": 842.0, "change": 120.0},
            "ADD": {"value": -210.0, "change": -40.0},
            "TRIN": {"value": 0.72, "change": -0.05},
            "VOLD": {"value": -1.25e8, "change": None},
        }
        with (
            patch("paper_trader.breadth.quotes.fetch_frame", return_value=fake),
            patch("paper_trader.schwab_auth.fetch_breadth_quotes", return_value=schwab),
        ):
            payload = snapshot()
        self.assertTrue(payload["ready"])
        self.assertTrue(payload["will_not_invent"])
        self.assertEqual(payload["values"]["TICK"], 842.0)
        self.assertEqual(payload["tones"]["TICK"], "bull")
        self.assertEqual(payload["values"]["ADD"], -210.0)
        self.assertEqual(payload["tones"]["ADD"], "bear")
        self.assertEqual(payload["values"]["TRIN"], 0.72)
        self.assertEqual(payload["tones"]["TRIN"], "bull")
        self.assertEqual(payload["values"]["VOLD"], -1.25e8)
        self.assertEqual(payload["tones"]["VOLD"], "bear")
        self.assertEqual(payload["values"]["VIX"], 16.2)
        self.assertIn("schwab:TICK+ADD+TRIN+VOLD", payload["source"])
        self.assertIn("yahoo:^VIX", payload["source"])
        self.assertIn("Schwab Connect", payload["note"])

    def test_snapshot_stays_empty_when_vix_feed_fails(self) -> None:
        with (
            patch("paper_trader.breadth.quotes.fetch_frame", side_effect=ValueError("No market quote")),
            patch("paper_trader.schwab_auth.fetch_breadth_quotes", return_value={}),
        ):
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

    def test_quote_parser_reads_schwab_shape(self) -> None:
        last, change = _quote_last_and_change(
            {"symbol": "$TICK", "quote": {"lastPrice": 512.0, "netChange": -88.0}}
        )
        self.assertEqual(last, 512.0)
        self.assertEqual(change, -88.0)
        self.assertEqual(_quote_last_and_change({"errors": ["bad"]}), (None, None))

    def test_fetch_breadth_skips_when_disconnected(self) -> None:
        with patch(
            "paper_trader.schwab_auth.connection_status",
            return_value={"connected": False},
        ):
            self.assertEqual(fetch_breadth_quotes(), {})


if __name__ == "__main__":
    unittest.main()
