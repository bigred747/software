"""Closed-bar scores stay on bars that have already printed."""

from __future__ import annotations

import sys
import unittest
from datetime import date, timedelta
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))

from paper_trader.learn import choose, fit, mood
from paper_trader.model import Bar


def bars_from(closes: list[float], opens: list[float] | None = None) -> list[Bar]:
    start = date(2024, 1, 1)
    rows: list[Bar] = []
    for index, close in enumerate(closes):
        open_ = opens[index] if opens else close - 1
        high = max(open_, close) + 0.5
        low = min(open_, close) - 0.5
        rows.append(Bar((start + timedelta(days=index)).isoformat(), open_, high, low, close, 1))
    return rows


class LearnTests(unittest.TestCase):
    def test_fear_and_greed_follow_the_recent_range(self) -> None:
        rising = bars_from([100 + index for index in range(25)])
        falling = bars_from([200 - index for index in range(25)])
        greed = mood(rising)
        fear = mood(falling)
        self.assertEqual(greed["label"], "Greed")
        self.assertGreaterEqual(greed["score"], 65)
        self.assertEqual(fear["label"], "Fear")
        self.assertLessEqual(fear["score"], 35)
        self.assertIn("Not a forecast", greed["note"])
        self.assertIsNone(mood(rising[:5])["score"])

    def test_choose_keeps_the_default_until_a_score_is_better_than_a_coin_flip(self) -> None:
        book = {
            "True momentum 5/3 with the candle": {"seen": 4, "matched": 4},
            "Squeeze 14": {"seen": 10, "matched": 5},
            "Average 21": {"seen": 12, "matched": 9},
        }
        name, row = choose(book, "True momentum 5/3 with the candle")
        self.assertEqual(name, "Average 21")
        self.assertEqual(row["matched"], 9)
        weak, _row = choose({"Squeeze 14": {"seen": 10, "matched": 5}}, "True momentum 5/3 with the candle")
        self.assertEqual(weak, "True momentum 5/3 with the candle")

    def test_last_bar_gets_no_arrow_and_a_newer_close_does_not_move_older_arrows(self) -> None:
        closes = []
        for index in range(48):
            closes.append(100 + (index % 6) - 3 + index // 6)
        original = bars_from(closes)
        fitted = fit(original)
        self.assertNotIn(original[-1].date, [mark["date"] for mark in fitted["arrows"]])
        self.assertIn(fitted["average"], (21, 34, 55))
        self.assertIn(fitted["squeeze"], (14, 20, 30))
        self.assertIn(fitted["calc"], (3, 5, 8))
        self.assertIn(fitted["smooth"], (2, 3, 5))
        self.assertLessEqual(fitted["matched"], fitted["seen"])
        self.assertIn("already printed", fitted["note"])
        self.assertIn("Not a forecast", fitted["note"])
        last = original[-1]
        nudged = original[:-1] + [Bar(last.date, last.open, last.high, last.low, last.close * 1.25, last.volume)]
        again = fit(nudged)
        self.assertEqual(fitted["arrows"], again["arrows"])
        self.assertNotEqual(fitted["mood"]["score"], again["mood"]["score"])
