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
    SECTORS,
    WATCH,
    Watcher,
    allow_entry,
    choose,
    closed_bars,
    score_row,
    ensure_learner,
    fit,
    learner_command,
    learning_report,
    mood,
    rank_leaders,
    rank_today,
    remember_scan_switch,
    remember_symbols,
    scan_note,
    scan_report,
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
        self.assertIn("Paper Trader Learn", script)
        self.assertIn(str(python), script)
        self.assertNotIn("wscript", script.lower())
        self.assertNotIn("powershell", script.lower())
        self.assertNotIn("/min", script.lower())
        for blocked in ("reg add", "netsh", "pip", "schtasks"):
            self.assertNotIn(blocked, script.lower())
        source = (Path(__file__).resolve().parents[1] / "paper_trader" / "learn.py").read_text(encoding="utf-8")
        self.assertNotIn("WScript", source)
        self.assertNotIn("shell.Run", source)
        self.assertNotIn("0x08000000", source)
        self.assertNotIn("/MIN", source)
        with tempfile.TemporaryDirectory() as folder:
            folder_path = Path(folder)
            (folder_path / "Paper Trader Learn.vbs").write_text("old\r\n", encoding="utf-8")
            written = write_startup(folder_path, folder_path)
            self.assertIsNotNone(written)
            assert written is not None
            self.assertEqual(written.suffix, ".bat")
            self.assertIn("learn", written.read_text(encoding="utf-8"))
            self.assertFalse((folder_path / "Paper Trader Learn.vbs").exists())
        command = learner_command(Path(r"C:\Users\rick\Documents\Paper Trader"), windows=True)
        self.assertIn("Paper Trader Learn", command)
        self.assertIn("learn", command)
        self.assertNotIn("powershell", " ".join(command).lower())

    def test_watch_list_drops_bad_symbols(self) -> None:
        with tempfile.TemporaryDirectory() as folder:
            path = Path(folder) / "learn_watch.json"
            remember_symbols(path, ["BTC-USD", "bad symbol", "XRP-USD", "BTC-USD"] + [f"S{index}" for index in range(30)])
            from paper_trader.learn import read_watch

            _focus, symbols = read_watch(path)
            self.assertIsNotNone(symbols)
            assert symbols is not None
            self.assertNotIn("bad symbol", symbols)
            self.assertEqual(symbols[0], "BTC-USD")
            self.assertEqual(len(symbols), 24)
            closed = learning_report(Path(folder) / "learn_state.json", expect=False)
            self.assertTrue(closed["ok"])
            self.assertIn("after that window closes", str(closed["detail"]))

    def test_wall_power_keeps_windows_awake_and_battery_lets_it_sleep(self) -> None:
        from paper_trader.learn import awake_note, awake_state, hold_awake

        class FakeKernel:
            def __init__(self, line: int) -> None:
                self.line = line
                self.flags: list[int] = []

            def GetSystemPowerStatus(self, pointer) -> int:
                pointer._obj.ACLineStatus = self.line
                return 1

            def SetThreadExecutionState(self, flags: int) -> int:
                self.flags.append(flags)
                return 0x80000000

        plugged = FakeKernel(1)
        self.assertEqual(awake_state(plugged), "wall")
        self.assertEqual(plugged.flags[-1], 0x80000001)
        self.assertIn("awake on wall power", awake_note("wall"))
        battery = FakeKernel(0)
        self.assertEqual(awake_state(battery), "battery")
        self.assertEqual(battery.flags[-1], 0x80000000)
        self.assertIn("can still sleep", awake_note("battery"))
        self.assertTrue(hold_awake(False, plugged))
        self.assertEqual(plugged.flags[-1], 0x80000000)
        if os.name != "nt":
            self.assertEqual(awake_state(), "")
            self.assertFalse(hold_awake(True))


def _row(symbol: str, seen: int, matched: int, day: str = "2026-10-22", setup: str = "buy") -> dict:
    return {
        "symbol": symbol,
        "seen": seen,
        "matched": matched,
        "as_of": day,
        "setup": setup,
        "rule": "Squeeze 20",
    }


class ScanTests(unittest.TestCase):
    def test_sector_names_stay_on_the_watch(self) -> None:
        for symbol in ("SMH", "VGT", "ARKW", "QQQ", "SPMO", "COPX", "ARKQ", "MGK", "CIBR", "UGA", "AIRR", "GRID"):
            self.assertIn(symbol, SECTORS)
            self.assertIn(symbol, WATCH)

    def test_today_keeps_five_buy_setups_and_drops_the_rest(self) -> None:
        rows = [
            _row("LOW", 20, 11),
            _row("SHORT", 19, 19),
            _row("OLD", 40, 40, "2026-10-21"),
            _row("FLAT", 30, 24, setup=""),
            _row("A", 20, 16),
            _row("B", 40, 32),
            _row("C", 25, 20),
            _row("D", 22, 16),
            _row("E", 21, 15),
            _row("F", 50, 40),
        ]
        picks = rank_today(rows)
        self.assertEqual([row["symbol"] for row in picks], ["F", "B", "FLAT", "C", "A"])
        self.assertEqual(picks[0]["rate"], 80)
        self.assertNotIn("D", [row["symbol"] for row in picks])
        self.assertLessEqual(len(picks), 5)
        self.assertEqual(allow_entry("SPY", picks, False), "This market is not in today's best.")
        self.assertEqual(allow_entry("F", picks, True), "This market already has a paper buy today.")
        self.assertEqual(allow_entry("F", picks, False), "")
        self.assertEqual(allow_entry("F", picks, False, 5), "Today already has five paper buys.")
        self.assertIn("still walking", allow_entry("F", picks, False, 0, False))
        tied = [_row(f"T{index}", 40, 32) for index in range(6)]
        self.assertEqual(len(rank_today(tied)), 5)
        thin = [_row("TOP", 40, 36), _row("NEAR", 20, 17), _row("FAR", 20, 12)]
        self.assertEqual([row["symbol"] for row in rank_today(thin)], ["TOP", "NEAR", "FAR"])
        wide = [_row("BEST", 50, 41), _row("CLOSE", 40, 31), _row("THIRD", 40, 30), _row("OFF", 40, 28)]
        self.assertEqual([row["symbol"] for row in rank_today(wide)], ["BEST", "CLOSE", "THIRD"])

    def test_leaders_are_the_highest_closed_bar_scores(self) -> None:
        rows = [_row("SHORT", 8, 8), _row("WEAK", 20, 11, setup="")]
        rows.extend(_row(f"S{index:02d}", 20, 12 + (index % 5), setup="" if index % 2 else "buy") for index in range(14))
        leaders = rank_leaders(rows)
        self.assertEqual(len(leaders), 10)
        self.assertGreaterEqual(leaders[0]["rate"], leaders[-1]["rate"])
        self.assertNotIn("SHORT", [row["symbol"] for row in leaders])
        self.assertNotIn("WEAK", [row["symbol"] for row in leaders])
        self.assertIn("Not a forecast", scan_note(4, 7504, False, 0, leaders))

    def test_a_feed_miss_is_skipped_and_a_dead_feed_waits(self) -> None:
        with tempfile.TemporaryDirectory() as folder:
            path = Path(folder) / "learn_state.json"
            watcher = Watcher(path)
            self.assertTrue(watcher.acquire())
            try:
                def load(symbol: str, refresh: bool = False):
                    if symbol == "BBB":
                        raise ValueError("No market quote for BBB")
                    if symbol == "CCC":
                        raise ValueError("The quote feed did not answer.")
                    return bars_from([100 + index * 0.1 for index in range(40)])

                with patch("paper_trader.learn.desk_day", return_value="2026-10-22"), patch(
                    "paper_trader.samples.all_symbols", return_value=["AAA", "BBB", "CCC"]
                ), patch("paper_trader.learn.quotes.load_bars", side_effect=load):
                    watcher._scan_one()
                    watcher._scan_one()
                    watcher._scan_one()
                    stalled = scan_report(watcher.scan_path)
                    self.assertEqual(stalled["scanned"], 2)
                    self.assertEqual(stalled["errors"], 1)
                    self.assertIn("did not answer", stalled["note"])
                    self.assertIn("AAA", stalled["rows"])
                    self.assertNotIn("BBB", stalled["rows"])
                    watcher._scan["day"] = "2000-01-01"
                    watcher._scan["rows"] = {"OLD": {"seen": 30, "matched": 20, "as_of": "2000-01-01", "setup": "buy", "scanned_on": "2000-01-01"}}
                    watcher._scan["cursor"] = 9
                    watcher._scan["done"] = True
                    watcher._names_day = ""
                    with patch("paper_trader.samples.all_symbols", return_value=["AAA"]):
                        watcher._scan_one()
                    fresh = watcher._scan
                    self.assertEqual(fresh["day"], "2026-10-22")
                    self.assertNotIn("OLD", fresh["rows"])
                    self.assertIn("AAA", fresh["rows"])
            finally:
                watcher.release()

    def test_scanner_off_does_not_fetch_or_advance(self) -> None:
        with tempfile.TemporaryDirectory() as folder:
            path = Path(folder) / "learn_state.json"
            watcher = Watcher(path)
            self.assertTrue(watcher.acquire())
            try:
                remember_scan_switch(watcher.scan_path, False)
                watcher._scan["cursor"] = 3
                watcher._scan["day"] = "2026-10-22"
                with patch("paper_trader.learn.quotes.load_bars") as load:
                    pause = watcher._scan_one()
                load.assert_not_called()
                self.assertEqual(pause, 2.0)
                self.assertEqual(watcher._scan["cursor"], 3)
                self.assertFalse(scan_report(watcher.scan_path)["on"])
                self.assertEqual(scan_note(0, 0, False, 0, [], "", False), "Scanner is off.")
                self.assertEqual(scan_note(3, 10, False, 0, [], "", False), "Scanner is off. Scanned 3 of 10.")
                self.assertIn("Not a forecast", scan_note(4, 7504, False, 0, [{"symbol": "AAA"}]))
            finally:
                watcher.release()

class ClosedBarTests(unittest.TestCase):
    def test_todays_bar_never_makes_a_signal(self) -> None:
        from datetime import datetime, timezone

        start = date(2026, 6, 1)
        bars = [Bar((start + timedelta(days=index)).isoformat(), 100.0, 101.0, 99.0, 100.0 + index % 3, 1.0) for index in range(144)]
        last = bars[-1].date
        noon = datetime.fromisoformat(last + "T18:00:00+00:00")
        self.assertEqual(len(closed_bars(bars, "BTC-USD", noon)), len(bars) - 1)
        after = datetime.fromisoformat(last + "T00:30:00+00:00") + timedelta(days=1)
        self.assertEqual(len(closed_bars(bars, "BTC-USD", after)), len(bars))
        evening = datetime.fromisoformat(last + "T23:00:00+00:00")
        self.assertEqual(len(closed_bars(bars, "AAPL", evening)), len(bars) - 1)
        next_morning = datetime.fromisoformat(last + "T13:00:00+00:00") + timedelta(days=1)
        self.assertEqual(len(closed_bars(bars, "AAPL", next_morning)), len(bars))
        today = datetime.now(timezone.utc).date()
        fresh = bars[:-1] + [Bar(today.isoformat(), 100.0, 300.0, 50.0, 250.0, 1.0)]
        with_today = score_row(fresh, "BTC-USD")
        without = score_row(fresh[:-1])
        self.assertEqual(with_today["as_of"], fresh[-2].date)
        self.assertEqual((with_today["seen"], with_today["matched"], with_today["setup"]), (without["seen"], without["matched"], without["setup"]))

