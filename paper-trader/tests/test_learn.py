"""Closed-bar scores stay on bars that have already printed."""

from __future__ import annotations

import os
import sys
import tempfile
import time
import unittest
from datetime import date, timedelta
from pathlib import Path
from unittest.mock import patch

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))

from paper_trader.learn import (
    Watcher,
    choose,
    ensure_learner,
    fit,
    learning_report,
    mood,
    remember_symbols,
    startup_script,
    stop_learner,
    write_startup,
)
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


class AlwaysOnTests(unittest.TestCase):
    def test_a_live_score_is_green_and_a_feed_error_stays_red(self) -> None:
        with tempfile.TemporaryDirectory() as folder:
            path = Path(folder) / "learn_state.json"
            watcher = Watcher(path)
            self.assertTrue(watcher.acquire())
            try:
                watcher._saved["last_symbol"] = "XRP-USD"
                watcher._saved["passes"] = 1
                watcher._saved["symbols"] = {"XRP-USD": {"mood": {"label": "Fear", "score": 22}}}
                watcher._beat()
                report = learning_report(path, expect=True)
                self.assertTrue(report["ok"])
                self.assertTrue(report["running"])
                self.assertIn("All day, all week", str(report["detail"]))
                self.assertIn("XRP-USD", str(report["detail"]))
                self.assertIn("Fear 22", str(report["detail"]))
                watcher._saved["last_error"] = "The quote feed did not answer. Check this computer's connection."
                watcher._beat()
                failed = learning_report(path, expect=True)
                self.assertFalse(failed["ok"])
                self.assertIn("quote feed", str(failed["detail"]))
                watcher._saved["last_error"] = ""
            finally:
                watcher.release()
            idle = learning_report(path, expect=True)
            self.assertFalse(idle["ok"])
            self.assertIn("not running", str(idle["detail"]))

    def test_a_second_scorer_leaves_the_first_one_running(self) -> None:
        with tempfile.TemporaryDirectory() as folder:
            path = Path(folder) / "learn_state.json"
            first = Watcher(path)
            second = Watcher(path)
            self.assertTrue(first.acquire())
            try:
                self.assertFalse(second.acquire())
            finally:
                first.release()
            self.assertTrue(second.acquire())
            second.release()

    def test_a_stuck_score_is_not_started_again(self) -> None:
        with tempfile.TemporaryDirectory() as folder:
            path = Path(folder) / "learn_state.json"
            watcher = Watcher(path)
            self.assertTrue(watcher.acquire())
            try:
                watcher._saved["pid"] = os.getpid()
                watcher._saved["updated"] = time.time() - 1000
                from paper_trader.learn import _store

                _store(path, watcher._saved)
                report = learning_report(path, expect=True)
                self.assertFalse(report["ok"])
                self.assertIn("stopped answering", str(report["detail"]))
                spawned: list[str] = []
                self.assertFalse(ensure_learner(Path(folder), path, spawn=lambda: spawned.append("started"), wait=0.1))
                self.assertEqual(spawned, [])
            finally:
                watcher.release()

    def test_a_stopped_score_is_started_once(self) -> None:
        with tempfile.TemporaryDirectory() as folder:
            path = Path(folder) / "learn_state.json"
            started = Watcher(path)

            def spawn() -> None:
                self.assertTrue(started.acquire())
                started._beat()

            try:
                self.assertTrue(ensure_learner(Path(folder), path, spawn=spawn, wait=1))
                again: list[str] = []
                self.assertTrue(ensure_learner(Path(folder), path, spawn=lambda: again.append("again"), wait=0.1))
                self.assertEqual(again, [])
            finally:
                started.release()

    def test_stop_learner_ends_only_the_recorded_process(self) -> None:
        with tempfile.TemporaryDirectory() as folder:
            path = Path(folder) / "learn_state.json"
            killed: list[int] = []
            with patch("paper_trader.learn.lock_holder", side_effect=[4242, None]):
                self.assertEqual(stop_learner(path, killer=killed.append), 4242)
            self.assertEqual(killed, [4242])
            with patch("paper_trader.learn.lock_holder", return_value=os.getpid()):
                self.assertIsNone(stop_learner(path, killer=killed.append))
            self.assertEqual(killed, [4242])

    def test_sign_in_script_only_starts_the_score(self) -> None:
        python = Path(r"C:\Users\rick\Documents\Paper Trader\runtime\python.exe")
        launch = Path(r"C:\Users\rick\Documents\Paper Trader\launch.py")
        script = startup_script(python, launch)
        self.assertIn("learn", script)
        self.assertIn(str(python), script)
        self.assertNotIn("powershell", script.lower())
        for blocked in ("reg add", "netsh", "pip", "schtasks"):
            self.assertNotIn(blocked, script.lower())
        with tempfile.TemporaryDirectory() as folder:
            written = write_startup(Path(folder), Path(folder))
            self.assertIsNotNone(written)
            self.assertIn("learn", written.read_text(encoding="utf-8"))

    def test_watch_list_drops_bad_symbols(self) -> None:
        with tempfile.TemporaryDirectory() as folder:
            path = Path(folder) / "learn_watch.json"
            remember_symbols(path, ["BTC-USD", "bad symbol", "XRP-USD", "BTC-USD"] + [f"S{index}" for index in range(20)])
            from paper_trader.learn import read_watch

            _focus, symbols = read_watch(path)
            self.assertIsNotNone(symbols)
            assert symbols is not None
            self.assertNotIn("bad symbol", symbols)
            self.assertEqual(symbols[0], "BTC-USD")
            self.assertEqual(len(symbols), 12)
            closed = learning_report(Path(folder) / "learn_state.json", expect=False)
            self.assertTrue(closed["ok"])
            self.assertIn("after that window closes", str(closed["detail"]))
