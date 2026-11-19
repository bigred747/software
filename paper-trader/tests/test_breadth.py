"""Breadth boxes fail closed. Never invent TICK / ADD / TRIN / VOLD."""

from __future__ import annotations

import sys
import unittest
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))

from paper_trader.breadth import KEYS, chip_value, snapshot


class BreadthFailClosedTests(unittest.TestCase):
    def test_snapshot_has_null_values_and_is_not_ready(self) -> None:
        payload = snapshot()
        self.assertFalse(payload["ready"])
        self.assertIsNone(payload["source"])
        self.assertEqual(tuple(payload["values"]), KEYS)
        for key in KEYS:
            self.assertIsNone(payload["values"][key])
        self.assertIn("TradingView", payload["note"])
        self.assertIn("Interactive Brokers", payload["next"])

    def test_chip_value_rejects_non_numbers(self) -> None:
        self.assertIsNone(chip_value(None))
        self.assertIsNone(chip_value(""))
        self.assertIsNone(chip_value("No feed"))
        self.assertIsNone(chip_value(float("nan")))
        self.assertEqual(chip_value(12.5), "12.5")
        self.assertEqual(chip_value(-842), "-842")


if __name__ == "__main__":
    unittest.main()
