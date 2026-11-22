"""The desk stays on this computer and serves the paper session."""

from __future__ import annotations

import json
import os
import sys
import tempfile
import threading
import unittest
import zipfile
from pathlib import Path
from unittest.mock import patch
from urllib.error import HTTPError
from urllib.request import Request, urlopen

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))

from paper_trader.cli import main
from paper_trader.guard import assert_safe_launch, is_temp_zip_extract
from paper_trader.server import _edge_commands, _launch_edge, _wait_for_desk, _window_flags, create_server

ROOT = Path(__file__).resolve().parents[1]


class ServerTests(unittest.TestCase):
    def setUp(self) -> None:
        from paper_trader import quotes
        from paper_trader.samples import load_symbol

        self._quotes = quotes.load_bars
        quotes.load_bars = lambda symbol, refresh=False: load_symbol(symbol)
        self.folder = tempfile.TemporaryDirectory()
        state = Path(self.folder.name) / "paper_state.json"
        self.server = create_server(state, 0)
        self.thread = threading.Thread(target=self.server.serve_forever, daemon=True)
        self.thread.start()
        self.base = f"http://127.0.0.1:{self.server.server_address[1]}"

    def tearDown(self) -> None:
        from paper_trader import quotes

        quotes.load_bars = self._quotes
        self.server.shutdown()
        self.server.server_close()
        self.folder.cleanup()

    def open(self, path: str, method: str = "GET", body: dict | None = None, host: str | None = None, headers: bool = False):
        data = None if body is None else json.dumps(body).encode()
        request = Request(self.base + path, data=data, method=method)
        if data is not None:
            request.add_header("Content-Type", "application/json")
        if host is not None:
            request.add_header("Host", host)
        try:
            with urlopen(request, timeout=5) as response:
                payload = response.read()
                if headers:
                    return response.status, payload, response.headers
                return response.status, payload
        except HTTPError as exc:
            payload = exc.read()
            if headers:
                return exc.code, payload, exc.headers
            return exc.code, payload

    def test_breadth_endpoint_fails_closed(self) -> None:
        from unittest.mock import patch

        fake = {
            "price": 22.1,
            "bars": [
                {"date": "2026-11-17", "open": 21, "high": 23, "low": 20, "close": 21.0, "volume": 0},
                {"date": "2026-11-18", "open": 21.5, "high": 22.5, "low": 21, "close": 22.1, "volume": 0},
            ],
        }
        with patch("paper_trader.breadth.quotes.fetch_frame", return_value=fake):
            status, raw = self.open("/api/breadth")
        self.assertEqual(status, 200)
        payload = json.loads(raw)
        self.assertTrue(payload["ready"])
        self.assertTrue(payload["will_not_invent"])
        for key in ("TICK", "ADD", "TRIN", "VOLD"):
            self.assertIsNone(payload["values"][key])
        self.assertEqual(payload["values"]["VIX"], 22.1)
        self.assertIn("will not paint fake numbers", payload["note"])

    def test_server_is_local_and_pages_load(self) -> None:
        self.assertEqual(self.server.server_address[0], "127.0.0.1")
        status, health, headers = self.open("/api/health", headers=True)
        self.assertEqual(status, 200)
        payload = json.loads(health)
        self.assertTrue(payload["ok"])
        self.assertEqual(payload["bind"], "127.0.0.1")
        self.assertEqual(headers["X-Content-Type-Options"], "nosniff")
        self.assertIn("default-src 'self'", headers["Content-Security-Policy"])
        status, page = self.open("/")
        self.assertEqual(status, 200)
        self.assertIn(b"Paper Trader", page)
        self.assertIn(b'id="auto"', page)
        self.assertIn(b'id="trade"', page)
        self.assertIn(b'id="paper"', page)
        self.assertIn(b'id="signals"', page)
        self.assertIn(b'id="signal-strip"', page)
        self.assertIn(b'id="chart-read"', page)
        self.assertIn(b">Auto<", page)
        self.assertIn(b">Trade<", page)
        self.assertIn(b">Paper<", page)
        self.assertNotIn(b"deposit", page.lower())

    def test_paper_button_engages_paper_and_rejects_another_mode(self) -> None:
        status, raw = self.open("/api/paper/mode", "POST", {"mode": "paper"})
        self.assertEqual(status, 200)
        payload = json.loads(raw)
        self.assertEqual(payload["mode"], "paper")
        self.assertTrue(payload["paper"])
        self.assertEqual(self.open("/api/paper/mode", "POST", {"mode": "live"})[0], 400)

    def test_launch_finds_the_program_from_another_folder(self) -> None:
        import subprocess

        completed = subprocess.run(
            [sys.executable, str(ROOT / "launch.py"), "backtest", "--symbol", "BTC-USD"],
            cwd=Path("/tmp"),
            capture_output=True,
            text=True,
            timeout=30,
            check=False,
        )
        self.assertEqual(completed.returncode, 0, completed.stderr)
        self.assertIn("Paper Trader market replay", completed.stdout)

    def test_signals_are_green_when_the_desk_is_healthy(self) -> None:
        status, raw = self.open("/api/checks")
        self.assertEqual(status, 200)
        payload = json.loads(raw)
        self.assertTrue(payload["ok"])
        names = {item["name"] for item in payload["items"]}
        for name in ("Paper", "Auto", "Trade", "Ticker", "Chart", "Markets", "Broker", "Schwab", "Install", "Learning", "Fear and greed", "Zoom"):
            self.assertIn(name, names)
        schwab = next(item for item in payload["items"] if item["name"] == "Schwab")
        self.assertFalse(schwab["ok"])
        self.assertIn("not connected", schwab["detail"])
        for item in payload["items"]:
            if item["name"] == "Schwab":
                continue
            self.assertTrue(item["ok"], item)
            self.assertTrue(item["detail"])

    def test_trade_button_places_a_simulated_order(self) -> None:
        status, raw = self.open("/api/paper")
        self.assertEqual(status, 200)
        before = json.loads(raw)
        status, traded = self.open("/api/paper/trade", "POST", {"confirmed": True})
        if status == 400:
            self.assertIn(b"today", traded)
            self.assertEqual(json.loads(self.open("/api/paper")[1])["cursor"], before["cursor"])
            return
        self.assertEqual(status, 200)
        payload = json.loads(traded)
        self.assertEqual(payload["mode"], "paper")
        self.assertTrue(payload["paper"])
        self.assertEqual(payload["cursor"], before["cursor"])
        self.assertEqual(payload["cursor"], payload["total_bars"])
        self.assertIn(payload["last_action"], {"buy", "sell"})
        if payload["last_action"] == "buy":
            self.assertGreater(payload["shares"], 0)
        else:
            self.assertEqual(payload["shares"], 0)

    def test_a_scanned_leader_can_buy_once_and_can_always_sell(self) -> None:
        from datetime import date, timedelta

        from paper_trader.model import Bar
        from paper_trader.server import Desk

        start = date(2024, 1, 1)
        bars = []
        for index in range(40):
            bars.append(Bar((start + timedelta(days=index)).isoformat(), 100.0, 101.0, 99.0, 100.0, 1.0))
        lesson = {
            "rule": "Squeeze 20",
            "seen": 20,
            "matched": 16,
            "as_of": bars[-1].date,
            "setup": "buy",
            "mood": {"label": "Neutral", "score": 50},
        }
        with tempfile.TemporaryDirectory() as folder:
            desk = Desk(Path(folder) / "paper_state.json")
            with patch("paper_trader.quotes.load_bars", return_value=bars), patch(
                "paper_trader.server.score_row", return_value=dict(lesson)
            ):
                bought = desk.trade()
                self.assertEqual(bought["last_action"], "buy")
                self.assertGreater(bought["shares"], 0)
                sold = desk.trade()
                self.assertEqual(sold["last_action"], "sell")
                self.assertEqual(sold["shares"], 0)
                with self.assertRaises(ValueError) as blocked:
                    desk.trade()
                self.assertIn("already has a paper buy today", str(blocked.exception))
                view = desk.learn_view()
                self.assertIn("leaders", view)
                self.assertIn("Daily scan", view["scan_note"])
                self.assertTrue(view["scan_on"])
                self.assertTrue(any(row["symbol"] == "BTC-USD" for row in view["leaders"]))
                paused = desk.set_scan(False)
                self.assertFalse(paused["scan_on"])
                self.assertIn("Scanner is off", paused["scan_note"])
                self.assertTrue(any(row["symbol"] == "BTC-USD" for row in paused["leaders"]))

    def test_learn_touch_scores_the_open_market_when_a_tab_is_opened(self) -> None:
        from paper_trader.server import Desk

        with tempfile.TemporaryDirectory() as folder:
            desk = Desk(Path(folder) / "paper_state.json")
            bars = self._flat_bars(100.0)
            with patch("paper_trader.quotes.load_bars", return_value=bars):
                touched = desk.learn_touch({"tab": "strategies", "symbols": [desk.journal.symbol, "ETH-USD"]})
                self.assertIn(desk.journal.symbol, touched.get("touched") or [])
                note = touched.get("touch_note") or ""
                self.assertTrue("strategies" in note or "New process" in note, note)
                self.assertEqual(touched.get("touch_tab"), "strategies")
                self.assertIn("new_processes", touched)
            status, raw = self.open("/api/learn/touch", "POST", {"tab": "journal", "symbols": ["BTC-USD"]})
            self.assertEqual(status, 200)
            payload = json.loads(raw)
            self.assertIn("touch_note", payload)
            self.assertTrue(payload.get("touched"))

    def test_learn_touch_scan_cycle_scores_top_scan_names_not_only_focus(self) -> None:
        from paper_trader.server import Desk

        with tempfile.TemporaryDirectory() as folder:
            desk = Desk(Path(folder) / "paper_state.json")
            bars = self._flat_bars(100.0)
            with patch("paper_trader.quotes.load_bars", return_value=bars):
                touched = desk.learn_touch({"tab": "scan", "symbols": ["ETH-USD", "XRP-USD", "SOL-USD"]})
                scored = touched.get("touched") or []
                self.assertTrue(touched.get("scan_cycle"))
                self.assertIn("ETH-USD", scored)
                self.assertIn("XRP-USD", scored)
                self.assertNotIn(desk.journal.symbol, scored)
                note = touched.get("touch_note") or ""
                self.assertTrue("Scan learn scored" in note or "New process" in note, note)
                again = desk.learn_touch({"tab": "auto", "symbols": ["BTC-USD", "SPY"]})
                self.assertTrue(again.get("scan_cycle"))
                self.assertEqual(again.get("touched"), ["BTC-USD", "SPY"])

    def test_paper_fills_teach_timing_on_closed_bars(self) -> None:
        from paper_trader.learn import record_fill_lesson
        from paper_trader.server import Desk

        with tempfile.TemporaryDirectory() as folder:
            path = Path(folder) / "learn_state.json"
            bars = self._flat_bars(100.0)
            taught = record_fill_lesson(path, "BTC-USD", "buy", 101.0, reason="auto", pnl=0.0, bars=bars)
            self.assertEqual(taught["fill"]["side"], "buy")
            self.assertIn("timing", taught)
            self.assertIn("note", taught["timing"])
            desk = Desk(Path(folder) / "paper_state.json")
            desk.learn_path = path
            with patch("paper_trader.quotes.load_bars", return_value=bars):
                desk._log_fill("buy", 100.0, 101.0, 0.0, "auto")
            self.assertTrue(desk.journal.fills)
            self.assertIn("as_of", desk.journal.fills[-1])
            book = desk.learn_view()
            self.assertTrue(book.get("last_fill"))
            self.assertIn("timing", book)

    def test_bot_on_desk_pages_and_strategies_auto_register(self) -> None:
        from paper_trader.learn import desk_pages, page_snapshot, register_desk_page
        from paper_trader.strategy_board import register_strategy, strategy_ids, strategy_snapshot

        pages = desk_pages()
        ids = [row[0] for row in pages]
        for needed in (
            "markets",
            "chart",
            "trade",
            "study",
            "strategies",
            "squeeze",
            "scorecard",
            "cycle",
            "forecast",
            "home",
            "outlook",
            "internals",
            "frames",
            "readings",
            "journal",
            "backtest",
            "signals",
        ):
            self.assertIn(needed, ids)
        register_desk_page("custom-ship", "Custom shipped page")
        self.assertIn("custom-ship", [row[0] for row in desk_pages()])
        snap = page_snapshot(
            "custom-ship",
            symbol="BTC-USD",
            lesson={"as_of": "2024-01-01", "rule": "Squeeze 20", "setup": "buy"},
            board={"best": "squeeze", "rows": []},
        )
        self.assertEqual(snap["page"], "custom-ship")
        self.assertTrue(snap.get("auto"))

        before = set(strategy_ids())
        register_strategy("demo", "Demo strategy", lambda bars, closes, count: (None, "Needs bars."))
        self.assertIn("demo", strategy_ids())
        self.assertTrue(before.issubset(set(strategy_ids())))
        board = strategy_snapshot([])
        self.assertIn("demo", board.get("ids") or [])
        self.assertTrue(any(row.get("id") == "demo" for row in board.get("rows") or []))

    def test_new_process_updates_fills_and_lights_a_green_signal(self) -> None:
        from paper_trader.server import Desk

        with tempfile.TemporaryDirectory() as folder:
            desk = Desk(Path(folder) / "paper_state.json")
            bars = self._flat_bars(100.0)
            desk.journal.fills = [
                {
                    "at": 1,
                    "symbol": desk.journal.symbol,
                    "side": "buy",
                    "qty": 1,
                    "price": 100,
                    "quote": 100,
                    "fee_bps": 5,
                    "pnl": 0,
                    "reason": "trade",
                }
            ]
            with patch("paper_trader.quotes.load_bars", return_value=bars), patch(
                "paper_trader.server.touch_symbol",
                return_value={
                    "rule": "Squeeze 20",
                    "previous_rule": "True momentum 5/3 with the candle",
                    "new_process": True,
                    "seen": 20,
                    "matched": 12,
                },
            ):
                touched = desk.learn_touch({"tab": "strategies", "symbols": [desk.journal.symbol]})
                self.assertEqual(len(touched.get("new_processes") or []), 1)
                self.assertIn("New process", touched.get("touch_note") or "")
                self.assertEqual(desk.journal.fills[0].get("process"), "Squeeze 20")
                self.assertGreaterEqual(touched.get("fills_updated") or 0, 1)
            checks = desk.checks()
            names = {item["name"]: item for item in checks["items"]}
            self.assertIn("New process", names)
            self.assertTrue(names["New process"]["ok"])
            self.assertIn("Squeeze 20", names["New process"]["detail"])

    def test_sell_all_sells_everything_and_stops_buys_for_the_denver_day(self) -> None:
        from datetime import date, timedelta

        from paper_trader.model import Bar
        from paper_trader.server import Desk

        start = date(2024, 1, 1)
        bars = [Bar((start + timedelta(days=index)).isoformat(), 100.0, 101.0, 99.0, 100.0, 1.0) for index in range(40)]
        lesson = {"rule": "Squeeze 20", "seen": 20, "matched": 16, "as_of": bars[-1].date, "setup": "buy", "mood": {"label": "Neutral", "score": 50}}
        with tempfile.TemporaryDirectory() as folder:
            desk = Desk(Path(folder) / "paper_state.json")
            with patch("paper_trader.quotes.load_bars", return_value=bars), patch(
                "paper_trader.server.score_row", return_value=dict(lesson)
            ):
                self.assertFalse(desk.snapshot()["breaker"]["tripped"])
                bought = desk.trade()
                self.assertGreater(bought["shares"], 0)
                self.assertAlmostEqual(bought["fill"]["price"], 100.05)
                self.assertEqual(bought["fill"]["quote"], 100.0)
                self.assertEqual(bought["fill"]["slip_bps"], 5.0)
                self.assertEqual(desk.journal.close_orders[-1][2], bought["fill"]["price"])
                halted = desk.halt()
                self.assertEqual(halted["last_action"], "sell")
                self.assertAlmostEqual(halted["fill"]["price"], 99.95)
                self.assertEqual(halted["shares"], 0)
                self.assertTrue(halted["breaker"]["tripped"])
                self.assertIn(bars[-1].date, desk.journal.halt_days)
                with self.assertRaises(ValueError) as blocked:
                    desk.trade()
                self.assertIn("circuit breaker", str(blocked.exception))
                again = desk.halt()
                self.assertEqual(again["last_action"], "")
                row = desk._breaker_check()
                self.assertFalse(row["ok"])
                self.assertIn("until tomorrow", row["detail"])

    def test_breaker_trips_itself_on_a_five_percent_daily_paper_drop(self) -> None:
        from datetime import date, timedelta

        from paper_trader.learn import desk_day
        from paper_trader.model import Bar
        from paper_trader.server import Desk, load_breaker, save_breaker

        start = date(2024, 1, 1)
        bars = [Bar((start + timedelta(days=index)).isoformat(), 100.0, 101.0, 99.0, 100.0, 1.0) for index in range(40)]
        lesson = {"rule": "Squeeze 20", "seen": 20, "matched": 16, "as_of": bars[-1].date, "setup": "buy", "mood": {"label": "Neutral", "score": 50}}
        with tempfile.TemporaryDirectory() as folder:
            desk = Desk(Path(folder) / "paper_state.json")
            with patch("paper_trader.quotes.load_bars", return_value=bars), patch(
                "paper_trader.server.score_row", return_value=dict(lesson)
            ):
                bought = desk.trade()
                self.assertGreater(bought["shares"], 0)
                self.assertFalse(bought["breaker"]["tripped"])
                data = load_breaker(desk.breaker_path)
                self.assertEqual(data["start_day"], desk_day())
                save_breaker(desk.breaker_path, {**data, "start_equity": bought["equity"] / 0.94})
                tripped = desk.step(1)
                self.assertEqual(tripped["shares"], 0)
                self.assertTrue(tripped["breaker"]["tripped"])
                self.assertIn("fell", tripped["breaker"]["reason"])

    def test_live_stays_locked_and_sell_all_answers(self) -> None:
        status, raw = self.open("/api/paper/mode", "POST", {"mode": "live"})
        self.assertEqual(status, 400)
        self.assertIn(b"Live is locked", raw)
        status, raw = self.open("/api/paper/halt", "POST", {"confirmed": True})
        self.assertEqual(status, 200)
        payload = json.loads(raw)
        self.assertEqual(payload["shares"], 0)
        self.assertTrue(payload["paper"])
        self.assertTrue(payload["breaker"]["tripped"])
        status, page = self.open("/")
        for marker in (b'id="halt"', b'id="live"', b">Live<", b"Sell all", b'id="breaker-note"'):
            self.assertIn(marker, page)

    def _flat_bars(self, last: float = 100.0, low: float | None = None):
        from datetime import date, timedelta

        from paper_trader.model import Bar

        start = date(2024, 1, 1)
        bars = [Bar((start + timedelta(days=index)).isoformat(), 100.0, 101.0, 99.0, 100.0, 1.0) for index in range(40)]
        floor = min(99.0, last) if low is None else low
        bars[-1] = Bar(bars[-1].date, 100.0, max(101.0, last), floor, last, 1.0)
        return bars

    def test_switching_markets_keeps_the_open_position_and_logs_every_fill(self) -> None:
        from paper_trader.server import Desk

        with tempfile.TemporaryDirectory() as folder:
            desk = Desk(Path(folder) / "paper_state.json")
            with patch("paper_trader.quotes.load_bars", return_value=self._flat_bars(100.0)), patch(
                "paper_trader.server.allow_entry", return_value=""
            ):
                first = desk.trade("auto")
                self.assertGreater(first["shares"], 0)
                self.assertEqual(first["book"]["fills"][0]["reason"], "auto")
                self.assertEqual(first["book"]["fills"][0]["side"], "buy")
                symbol = first["symbol"]
                other = "ETH-USD" if symbol != "ETH-USD" else "BTC-USD"
                away = desk.reset({"symbol": other, "switch": True})
                self.assertEqual(away["symbol"], other)
                self.assertEqual(away["shares"], 0)
                held = [row for row in away["book"]["positions"] if row["symbol"] == symbol]
                self.assertEqual(len(held), 1)
                self.assertGreater(held[0]["shares"], 0)
                self.assertEqual(len(away["book"]["fills"]), 1)
                back = desk.reset({"symbol": symbol, "switch": True})
                self.assertGreater(back["shares"], 0)
                sold = desk.trade()
                self.assertEqual(sold["shares"], 0)
                reasons = [row["reason"] for row in sold["book"]["fills"]]
                self.assertEqual(reasons, ["trade", "auto"])
                wiped = desk.reset({"symbol": symbol})
                self.assertEqual(wiped["shares"], 0)
                reloaded = Desk(Path(folder) / "paper_state.json")
                self.assertEqual(reloaded.journal.symbol, symbol)

    def test_book_survives_a_restart(self) -> None:
        from paper_trader.server import Desk

        with tempfile.TemporaryDirectory() as folder:
            path = Path(folder) / "paper_state.json"
            desk = Desk(path)
            with patch("paper_trader.quotes.load_bars", return_value=self._flat_bars(100.0)), patch(
                "paper_trader.server.allow_entry", return_value=""
            ):
                desk.trade()
                desk.reset({"symbol": "ETH-USD" if desk.journal.symbol != "ETH-USD" else "BTC-USD", "switch": True})
                again = Desk(path)
                view = again.snapshot()
                self.assertTrue(any(row["shares"] > 0 for row in view["book"]["positions"]))
                self.assertEqual(len(view["book"]["fills"]), 1)

    def test_every_element_the_page_script_looks_up_exists(self) -> None:
        import re

        root = Path(__file__).resolve().parents[1] / "web"
        script = (root / "app.js").read_text(encoding="utf-8")
        page = (root / "index.html").read_text(encoding="utf-8")
        used = set(re.findall(r'\$\("([A-Za-z0-9_-]+)"\)', script))
        present = set(re.findall(r'\bid="([^"]+)"', page))
        self.assertEqual(sorted(used - present), [])
        for button in re.findall(r'<button[^>]*\bid="([^"]+)"', page):
            self.assertIn(f'"{button}"', script, f"button {button} has no code behind it")
        tabs = re.findall(r'data-dock="([a-z]+)"', page)
        pages = re.findall(r'data-dock-page="([a-z]+)"', page)
        self.assertEqual(sorted(tabs), sorted(pages))
        for name in ("strategies", "squeeze", "journal"):
            self.assertIn(name, tabs)

    def test_internal_gauges_are_readable_without_being_in_the_market_list(self) -> None:
        from paper_trader import quotes

        for symbol in ("^VIX", "^VXN", "^VIX9D", "^TNX", "DX-Y.NYB", "RSP"):
            self.assertEqual(quotes.yahoo_symbol(symbol), symbol)
        with self.assertRaises(ValueError):
            quotes.yahoo_symbol("^NOTAGAUGE")
        for name in ("internals", "int-gate", "int-refresh", "internals-pill"):
            page = (Path(__file__).resolve().parents[1] / "web" / "index.html").read_text(encoding="utf-8")
            self.assertIn(name, page)

    def test_safe_check_answers_green_and_names_every_item(self) -> None:
        with patch("paper_trader.quotes.ping", return_value="Market quote ready"):
            status, raw = self.open("/api/safe")
        self.assertEqual(status, 200)
        report = json.loads(raw)
        self.assertTrue(report["safe"], report)
        names = {item["name"] for item in report["items"]}
        for name in (
            "This computer only",
            "Strangers blocked",
            "Artex blocked",
            "Foreign bots kept out",
            "Temp zips blocked",
            "Desk files alone",
            "Foreign scripts refused",
            "Live locked",
            "Paper only",
            "Second warning",
            "No invented breadth",
            "Desk Pool fail-closed",
            "Quote hosts",
            "Circuit breaker",
            "Order rules",
            "Journal saves",
            "Program unchanged",
            "Launchers clean",
            "Quiet installer",
            "Schwab credentials",
        ):
            self.assertIn(name, names)
        live = next(item for item in report["items"] if item["name"] == "Live locked")
        self.assertTrue(live["ok"])
        self.assertIn("cannot arm", live["detail"])
        scripts = next(item for item in report["items"] if item["name"] == "Foreign scripts refused")
        self.assertTrue(scripts["ok"])
        schwab = next(item for item in report["items"] if item["name"] == "Schwab credentials")
        self.assertEqual(schwab["kind"], "health")
        self.assertTrue(schwab["ok"])
        self.assertIn(schwab["detail"], ("missing", "ready (not connected)"))
        self.assertTrue(all(item["ok"] for item in report["items"] if item["kind"] == "safety"))

    def test_windows_extras_are_off_by_default_and_wired(self) -> None:
        from paper_trader.server import Desk

        with tempfile.TemporaryDirectory() as folder:
            desk = Desk(Path(folder) / "paper_state.json")
            view = desk.windows_view()
            self.assertFalse(view["startup"])
            self.assertFalse(view["desktop"])
            if os.name != "nt":
                self.assertFalse(view["windows"])
                with self.assertRaises(ValueError):
                    desk.windows_set({"startup": True})
        status, raw = self.open("/api/windows")
        self.assertEqual(status, 200)
        self.assertIn("startup", json.loads(raw))
        page = (Path(__file__).resolve().parents[1] / "web" / "index.html").read_text(encoding="utf-8")
        for name in ("win-box", "win-startup", "win-desktop"):
            self.assertIn(name, page)

    def test_safe_check_turns_red_when_a_program_file_changes_after_start(self) -> None:
        from paper_trader.server import Desk

        with tempfile.TemporaryDirectory() as folder:
            desk = Desk(Path(folder) / "paper_state.json")
            desk.fingerprint = "changed"
            with patch("paper_trader.quotes.ping", return_value="ok"):
                report = desk.safe_report()
            self.assertFalse(report["safe"])
            self.assertIn("Program unchanged", [item["name"] for item in report["items"] if not item["ok"]])
            report = desk.safe_report("0.0.0.0")
            self.assertFalse(report["safe"])

    def test_ticket_limit_waits_for_the_real_quote_then_fills_on_paper(self) -> None:
        from paper_trader.server import Desk

        with tempfile.TemporaryDirectory() as folder:
            desk = Desk(Path(folder) / "paper_state.json")
            with patch("paper_trader.quotes.load_bars", return_value=self._flat_bars(100.0)):
                placed = desk.place({"side": "buy", "kind": "limit", "limit": 95, "tif": "GTC"})
                self.assertEqual(placed["last_action"], "working")
                self.assertEqual(len(placed["working"]), 1)
                self.assertEqual(placed["shares"], 0)
                self.assertFalse(placed["live"])
                self.assertEqual(desk.snapshot()["shares"], 0)
            with patch("paper_trader.quotes.load_bars", return_value=self._flat_bars(94.0)):
                filled = desk.step(1)
                self.assertGreater(filled["shares"], 0)
                self.assertEqual(filled["working"], [])
                self.assertAlmostEqual(desk.journal.close_orders[-1][2], 94.0)
                self.assertEqual(filled["ticket_fills"][-1]["kind"], "limit")

    def test_ticket_stop_rules_and_stop_limit_trigger(self) -> None:
        from paper_trader.server import Desk

        with tempfile.TemporaryDirectory() as folder:
            desk = Desk(Path(folder) / "paper_state.json")
            with patch("paper_trader.quotes.load_bars", return_value=self._flat_bars(100.0)):
                with self.assertRaises(ValueError):
                    desk.place({"side": "buy", "kind": "stop", "stop": 99})
                with self.assertRaises(ValueError):
                    desk.place({"side": "sell", "kind": "limit", "limit": 101})
                with self.assertRaises(ValueError):
                    desk.place({"side": "buy", "kind": "limit"})
                desk.place({"side": "buy", "kind": "stop_limit", "stop": 102, "limit": 103, "tif": "GTC"})
            with patch("paper_trader.quotes.load_bars", return_value=self._flat_bars(104.0)):
                held = desk.step(1)
                self.assertEqual(held["shares"], 0)
                self.assertTrue(held["working"][0]["triggered"])
            with patch("paper_trader.quotes.load_bars", return_value=self._flat_bars(102.5)):
                filled = desk.step(1)
                self.assertGreater(filled["shares"], 0)
                self.assertAlmostEqual(desk.journal.close_orders[-1][2], 102.5)
                sold = desk.place({"side": "sell", "kind": "stop", "stop": 100, "tif": "GTC"})
                self.assertEqual(sold["last_action"], "working")
            with patch("paper_trader.quotes.load_bars", return_value=self._flat_bars(99.0)):
                out = desk.step(1)
                self.assertEqual(out["shares"], 0)
                self.assertAlmostEqual(desk.journal.close_orders[-1][2], 99.0 * (1 - 0.0005))

    def test_sell_all_cancels_working_orders_and_the_breaker_blocks_ticket_buys(self) -> None:
        from paper_trader.server import Desk

        with tempfile.TemporaryDirectory() as folder:
            desk = Desk(Path(folder) / "paper_state.json")
            with patch("paper_trader.quotes.load_bars", return_value=self._flat_bars(100.0)):
                desk.place({"side": "buy", "kind": "limit", "limit": 90, "tif": "GTC"})
                halted = desk.halt()
                self.assertEqual(halted["working"], [])
                with self.assertRaises(ValueError) as blocked:
                    desk.place({"side": "buy", "kind": "market", "confirmed": True})
                self.assertIn("circuit breaker", str(blocked.exception))

    def test_day_orders_expire_and_gtc_orders_stay(self) -> None:
        from paper_trader.server import Desk

        with tempfile.TemporaryDirectory() as folder:
            desk = Desk(Path(folder) / "paper_state.json")
            with patch("paper_trader.quotes.load_bars", return_value=self._flat_bars(100.0)):
                desk.place({"side": "buy", "kind": "limit", "limit": 90, "tif": "DAY"})
                desk.place({"side": "buy", "kind": "limit", "limit": 91, "tif": "GTC"})
                for ticket in desk.journal.working:
                    ticket["day"] = "2000-01-01"
                view = desk.snapshot()
                self.assertEqual([item["tif"] for item in view["working"]], ["GTC"])
                canceled = desk.cancel({"id": view["working"][0]["id"]})
                self.assertEqual(canceled["working"], [])

    def test_paper_book_export_includes_working_orders_for_the_phone(self) -> None:
        from paper_trader.server import Desk

        with tempfile.TemporaryDirectory() as folder:
            desk = Desk(Path(folder) / "paper_state.json")
            with patch("paper_trader.quotes.load_bars", return_value=self._flat_bars(100.0)):
                desk.place({"side": "buy", "kind": "market", "tif": "DAY"})
                desk.place({"side": "sell", "kind": "limit", "limit": 120, "tif": "GTC"})
                book = desk.export_book()
                self.assertEqual(book["version"], 1)
                self.assertGreaterEqual(book["markets"], 1)
                row = book["sessions"][desk.journal.symbol]
                self.assertGreater(row["shares"], 0)
                self.assertTrue(any(item.get("kind") == "limit" for item in row["working"]))
            status, raw = self.open("/api/paper/book")
            self.assertEqual(status, 200)
            payload = json.loads(raw)
            self.assertEqual(payload["version"], 1)
            self.assertIn("sessions", payload)

    def test_paper_book_import_merges_open_trades_onto_the_computer(self) -> None:
        from paper_trader.server import Desk

        with tempfile.TemporaryDirectory() as folder:
            desk = Desk(Path(folder) / "paper_state.json")
            bars = self._flat_bars(100.0)
            with patch("paper_trader.quotes.load_bars", return_value=bars):
                payload = {
                    "version": 1,
                    "sessions": {
                        desk.journal.symbol: {
                            "symbol": desk.journal.symbol,
                            "shares": 10,
                            "cash": 100,
                            "entryPrice": 100,
                            "entryDate": bars[-1].date,
                            "working": [
                                {
                                    "id": "W9",
                                    "side": "sell",
                                    "kind": "limit",
                                    "limit": 120,
                                    "stop": 0,
                                    "tif": "GTC",
                                    "day": bars[-1].date,
                                }
                            ],
                            "fills": [],
                            "nextId": 10,
                            "params": desk.journal.params.to_dict(),
                            "last": 100,
                        }
                    },
                }
                view = desk.import_book(payload)
                self.assertGreaterEqual(view.get("book_loaded") or 0, 1)
                self.assertGreater(float(view.get("shares") or 0), 0)
                self.assertTrue(any(item.get("id") == "W9" for item in view.get("working") or []))
            status, raw = self.open(
                "/api/paper/book",
                "POST",
                {
                    "version": 1,
                    "sessions": {
                        "BTC-USD": {
                            "symbol": "BTC-USD",
                            "shares": 0,
                            "cash": 1000,
                            "working": [
                                {
                                    "id": "P1",
                                    "side": "buy",
                                    "kind": "limit",
                                    "limit": 1,
                                    "stop": 0,
                                    "tif": "GTC",
                                    "day": "2000-01-01",
                                }
                            ],
                            "fills": [],
                            "nextId": 2,
                            "last": 1,
                        }
                    },
                },
            )
            self.assertEqual(status, 200)
            body = json.loads(raw)
            self.assertGreaterEqual(body.get("book_loaded") or 0, 1)

    def test_ticket_market_order_fills_with_slippage_and_http_routes_answer(self) -> None:
        status, raw = self.open("/api/paper")
        self.assertEqual(status, 200)
        status, placed = self.open(
            "/api/paper/ticket",
            "POST",
            {"side": "buy", "kind": "limit", "limit": 0.01, "tif": "GTC", "confirmed": True},
        )
        self.assertEqual(status, 200)
        payload = json.loads(placed)
        self.assertEqual(payload["last_action"], "working")
        status, canceled = self.open("/api/paper/cancel", "POST", {"id": "all"})
        self.assertEqual(status, 200)
        self.assertEqual(json.loads(canceled)["working"], [])
        status, bought = self.open("/api/paper/ticket", "POST", {"side": "buy", "kind": "market", "confirmed": True})
        self.assertEqual(status, 200)
        fill = json.loads(bought)["fill"]
        self.assertEqual(fill["slip_bps"], 5.0)
        self.assertGreater(fill["price"], fill["quote"])
        self.assertEqual(self.open("/api/paper/ticket", "POST", {"side": "buy", "kind": "market", "confirmed": True})[0], 400)

    def test_every_buy_and_sell_needs_the_second_warning(self) -> None:
        before = json.loads(self.open("/api/paper")[1])
        for path, body in (
            ("/api/paper/trade", {}),
            ("/api/paper/trade", {"source": "auto"}),
            ("/api/paper/trade", {"confirmed": "yes"}),
            ("/api/paper/ticket", {"side": "buy", "kind": "market"}),
            ("/api/paper/halt", {}),
        ):
            status, raw = self.open(path, "POST", body)
            self.assertEqual(status, 400, path)
            self.assertIn(b"Confirm first", raw)
        after = json.loads(self.open("/api/paper")[1])
        self.assertEqual(after["shares"], before["shares"])
        self.assertEqual(after.get("fills"), before.get("fills"))

    def test_page_has_the_second_warning_and_strategy_testing_wired(self) -> None:
        web = Path(__file__).resolve().parents[1] / "web"
        page = (web / "index.html").read_text(encoding="utf-8")
        script = (web / "app.js").read_text(encoding="utf-8")
        for name in ("confirm-modal", "cm-yes", "cm-no", "cm-checks", "strat-detail", "strat-log", "strat-sweep", "strat-sweep-body"):
            self.assertIn(f'id="{name}"', page)
        for call in ("/api/paper/trade", "/api/paper/ticket", "/api/paper/halt"):
            for line in script.splitlines():
                if f'paperCall("{call}"' in line:
                    self.assertIn("confirmed: true", line, line)
        self.assertIn("answerAuto", script)
        self.assertNotIn('paperCall("/api/paper/trade", { source: "auto" })', script)

    def test_edge_on_every_timeframe_and_the_price_box_are_wired(self) -> None:
        web = Path(__file__).resolve().parents[1] / "web"
        page = (web / "index.html").read_text(encoding="utf-8")
        script = (web / "app.js").read_text(encoding="utf-8")
        frames = (web / "frames.js").read_text(encoding="utf-8")
        popup = (web / "frame.js").read_text(encoding="utf-8")
        for name in ("edge-frames", "edge-frames-line", "edge-frames-body", "edge-frames-refresh", "edge-frames-note"):
            self.assertIn(f'id="{name}"', page)
        for word in ("loadEdgeFrames", "renderEdgeFrames", "showChartTip", "state.tmoMap", "attachPick"):
            self.assertIn(word, script)
        for word in ("function describeBar", "function showTip", "function attachPick", "function edgeLine", "paperPick"):
            self.assertIn(word, frames)
        self.assertIn("drawEdgeCalls", script)
        self.assertIn("function drawEdgeCalls", frames)
        self.assertIn("attachPick", popup)
        self.assertIn("edgeLine", popup)

    def test_only_this_computer_can_reach_the_desk(self) -> None:
        from paper_trader.server import _local_client, _local_origin

        self.assertTrue(_local_client(("127.0.0.1", 5000)))
        self.assertTrue(_local_client(("::1", 5000, 0, 0)))
        self.assertFalse(_local_client(("192.168.1.25", 5000)))
        self.assertFalse(_local_client(("203.0.113.9", 5000)))
        self.assertTrue(_local_origin(""))
        self.assertTrue(_local_origin("http://127.0.0.1:8765"))
        self.assertTrue(_local_origin("http://localhost:8765/"))
        self.assertFalse(_local_origin("https://evil.example"))
        self.assertFalse(_local_origin("http://127.0.0.1.evil.example"))
        request = Request(self.base + "/api/health", headers={"Origin": "https://evil.example"})
        with self.assertRaises(HTTPError) as blocked:
            urlopen(request, timeout=5)
        self.assertEqual(blocked.exception.code, 404)
        status, health = self.open("/api/health")
        self.assertTrue(json.loads(health)["loopback"])

    def test_page_has_the_order_ticket(self) -> None:
        status, page = self.open("/")
        for marker in (
            b'id="ticket"',
            b"Order ticket",
            b'id="kind"',
            b"Stop Limit",
            b'id="tif"',
            b">GTC<",
            b"Confirm and Send",
            b"Working orders",
            b'id="working-body"',
            b'id="side-buy"',
            b'id="side-sell"',
            b'id="halt"',
        ):
            self.assertIn(marker, page)
        status, script = self.open("/app.js")
        for marker in (b"/api/paper/ticket", b"/api/paper/cancel", b"PaperEngine.place"):
            self.assertIn(marker, script)

    def test_step_and_backtest(self) -> None:
        status, raw = self.open("/api/paper")
        self.assertEqual(status, 200)
        before = json.loads(raw)["cursor"]
        status, stepped = self.open("/api/paper/step", "POST", {"days": 1})
        self.assertEqual(status, 200)
        self.assertEqual(json.loads(stepped)["cursor"], before)
        self.assertEqual(json.loads(stepped)["cursor"], json.loads(stepped)["total_bars"])
        status, report = self.open("/api/backtest", "POST", {"symbol": "ETH-USD", "fast": 8, "slow": 21, "rsi": 14})
        self.assertEqual(status, 200)
        self.assertGreaterEqual(json.loads(report)["trade_count"], 1)

    def test_rejects_path_escape_and_unknown_market(self) -> None:
        self.assertEqual(self.open("/../README.md")[0], 404)
        self.assertEqual(self.open("/api/market?symbol=NOPE")[0], 400)
        self.assertEqual(self.open("/api/health", host="example.test")[0], 404)

    def test_refuses_a_temporary_zip_folder_and_a_low_port(self) -> None:
        from paper_trader.guard import foreign_scripts_status, path_looks_foreign

        temp_zip = Path(r"C:\Users\rick\AppData\Local\Temp\abc_pack.zip.43d\app")
        self.assertTrue(is_temp_zip_extract(temp_zip))
        self.assertFalse(is_temp_zip_extract(Path(r"C:\Users\rick\Documents\paper-trader")))
        with self.assertRaises(ValueError):
            assert_safe_launch([temp_zip])
        program = Path(r"C:\Users\rick\Documents\Paper Trader")
        self.assertFalse(is_temp_zip_extract(program))
        assert_safe_launch([program])
        artex = Path(r"C:\Users\rick\Documents\Artex\paper-trader")
        self.assertTrue(path_looks_foreign(artex))
        with self.assertRaises(ValueError) as blocked:
            assert_safe_launch([artex])
        self.assertIn("artex", str(blocked.exception).lower())
        quantum = Path(r"C:\Users\rick\Documents\QuantumTrade\paper-trader")
        self.assertTrue(path_looks_foreign(quantum))
        with self.assertRaises(ValueError):
            assert_safe_launch([quantum])
        clean = foreign_scripts_status(ROOT)
        self.assertTrue(clean["ok"], clean)
        with tempfile.TemporaryDirectory() as folder:
            dirty_root = Path(folder)
            (dirty_root / "hack.ps1").write_text("Write-Host bad", encoding="utf-8")
            dirty = foreign_scripts_status(dirty_root)
            self.assertFalse(dirty["ok"])
            self.assertIn("hack.ps1", str(dirty["detail"]))
        self.assertEqual(main(["serve", "--port", "80", "--no-browser"]), 2)

    def test_serve_leaves_the_shell_folder_before_it_starts(self) -> None:
        import os
        from unittest.mock import patch

        previous = os.getcwd()
        try:
            os.chdir("/tmp")
            with patch("paper_trader.cli.serve") as serve:
                self.assertEqual(main(["serve", "--no-browser"]), 0)
            serve.assert_called_once()
            self.assertEqual(Path.cwd().resolve(), ROOT.resolve())
        finally:
            os.chdir(previous)
        cli = (ROOT / "paper_trader" / "cli.py").read_text(encoding="utf-8")
        self.assertNotIn("Path.cwd()", cli)

    def test_package_does_not_ask_for_money_or_keys(self) -> None:
        # quantumtrade may appear only as a blocked foreign-bot marker in guard text.
        banned = ("paypal", "seed phrase", "mnemonic", "guaranteed profit", "deposit")
        roots = [
            ROOT / "paper_trader",
            ROOT / "web",
            ROOT / "README.md",
            ROOT / "Start-Paper-Trader.bat",
            ROOT / "1 Allow Paper Trader.bat",
            ROOT / "2 Open Paper Trader.bat",
        ]
        text = []
        for root in roots:
            files = [root] if root.is_file() else root.rglob("*")
            for path in files:
                if path.is_file() and path.suffix.lower() in {".py", ".js", ".html", ".css", ".md", ".bat"}:
                    text.append(path.read_text(encoding="utf-8").lower())
        blob = "\n".join(text)
        for word in banned:
            self.assertNotIn(word, blob)
        bat = (ROOT / "Start-Paper-Trader.bat").read_text(encoding="utf-8").lower()
        self.assertIn("runtime\\python.exe", bat)
        self.assertIn("launch.py", bat)
        self.assertIn("serve", bat)
        self.assertIn("if not errorlevel 1", bat)
        self.assertIn("opening the new copy", bat)
        self.assertIn("close this setup window", bat)
        self.assertNotIn("goto badinstall", bat)
        self.assertIn("unblock", bat)
        self.assertIn("documents\\iphone", bat)
        self.assertIn("papertrader.html", bat)
        self.assertIn("explorer", bat)
        self.assertIn("paper trader.bat", bat)
        self.assertNotIn("pip", bat)
        self.assertNotIn("requirements", bat)
        allow = (ROOT / "1 Allow Paper Trader.bat").read_text(encoding="utf-8").lower()
        opener = (ROOT / "2 Open Paper Trader.bat").read_text(encoding="utf-8").lower()
        self.assertIn("launch.py", allow)
        self.assertIn("install", allow)
        self.assertIn("paper trader.bat", allow)
        for name in ("Start-Paper-Trader.bat", "1 Allow Paper Trader.bat", "2 Open Paper Trader.bat"):
            text = (ROOT / name).read_text(encoding="utf-8")
            self.assertIn("tar -xf", text)
            self.assertIn('.zip.', text)
            self.assertIn("PaperTrader-setup", text)
            self.assertNotIn('Temp\\"', text)
            lowered = text.lower()
            for blocked in ("powershell", "cscript", "wscript", "zone.identifier", "unblock-file", ".vbs", "taskkill"):
                self.assertNotIn(blocked, lowered)
        self.assertIn("remove-source", allow)
        self.assertIn("start-paper-trader.bat", opener)
        for blocked in ("add-mpexclusion", "set-mppreference", "exclusionpath", "reg add", "netsh"):
            self.assertNotIn(blocked, allow)

    def test_windows_zip_includes_its_own_python(self) -> None:
        archive = ROOT.parent / "Paper-Trader.zip"
        self.assertTrue(archive.is_file())
        self.assertGreater(archive.stat().st_size, 8_000_000)
        with zipfile.ZipFile(archive) as packed:
            names = packed.namelist()
            page = packed.read("paper-trader/web/index.html")
            path_file = packed.read("paper-trader/runtime/python313._pth")
            script = packed.read("paper-trader/web/app.js")
            phone = packed.read("paper-trader/iphone/PaperTrader.html")
            note = packed.read("paper-trader/iphone/README.txt")
            desk = packed.read("paper-trader/paper_trader/server.py")
            learn = packed.read("paper-trader/paper_trader/learn.py")
            board = packed.read("paper-trader/paper_trader/strategy_board.py")
            live = packed.read("paper-trader/web/live.js")
            frames = packed.read("paper-trader/web/frames.js")
            journal = packed.getinfo("paper-trader/paper_trader/journal.py")
        self.assertIn("paper-trader/paper_trader/strategy_board.py", names)
        self.assertIn(b"register_strategy", board)
        self.assertIn(b"strategy_snapshot", board)
        self.assertIn(b"register_desk_page", learn)
        self.assertIn("paper-trader/runtime/python.exe", names)
        self.assertIn("paper-trader/Start-Paper-Trader.bat", names)
        self.assertIn("paper-trader/1 Allow Paper Trader.bat", names)
        self.assertIn("paper-trader/2 Open Paper Trader.bat", names)
        self.assertIn(b"state.auto = true", script)
        self.assertIn(b"/api/paper/trade", script)
        self.assertIn(b"/api/checks", script)
        self.assertIn(b">Auto<", page)
        self.assertIn(b">Trade<", page)
        self.assertIn(b">Paper<", page)
        self.assertIn(b'id="signals"', page)
        self.assertIn(b"big4indicator", page)
        self.assertIn(b"drawArrow", script)
        self.assertIn("paper-trader/paper_trader/catalog.txt", names)
        self.assertIn("paper-trader/web/study.js", names)
        self.assertIn("paper-trader/web/frames.js", names)
        self.assertIn("paper-trader/web/frame.html", names)
        self.assertIn("paper-trader/iphone/PaperTrader.html", names)
        self.assertIn(b"Install study", page)
        self.assertIn(b"Open all 10 windows", page)
        self.assertIn(b"..", path_file)
        self.assertIn(b"1.10.27", script)
        self.assertIn(b"shortLearnStatus", script)
        self.assertIn(b"statusLooksLikeOpen", script)
        self.assertIn(b"learn_banner_lines", learn)
        self.assertIn(b"Paper Trader Learn", learn)
        self.assertIn(b"indexTapeTrade", script)
        self.assertIn(b"tradeFromBars", script)
        self.assertIn(b"/api/market?symbol=", script)
        self.assertIn(b"Swing Guard", page)
        self.assertIn(b"Risk Seat", page)
        self.assertIn(b"Name Pulse", page)
        self.assertIn(b"Book Heat", page)
        self.assertIn(b"Reclaim Cue", page)
        self.assertIn(b"Season Lean", page)
        self.assertIn(b"Swing Guard", script)
        self.assertIn(b"Risk Seat", script)
        self.assertIn(b"Name Pulse", script)
        self.assertIn(b"Book Heat", script)
        self.assertIn(b"Reclaim Cue", script)
        self.assertIn(b"forecast-levels", page)
        self.assertIn(b"Price confidence", page)
        self.assertNotIn(b"TradeSmith", page)
        self.assertNotIn(b"TradeStops", page)
        self.assertNotIn(b"TradeSmith", script)
        self.assertNotIn(b"TradeSmith", frames)
        self.assertIn(b"seatrail", script)
        self.assertIn(b"seatTrailRead", script)
        self.assertIn(b"Seat Trail", script)
        self.assertIn(b"Desk Waves green / up", script)
        self.assertIn(b'"wave"', script)
        self.assertIn(b"holdLearnAwake", script)
        self.assertIn(b'id="index-tape"', page)
        self.assertIn(b"refreshIndexTape", script)
        self.assertIn(b'data-index-symbol="YM"', page)
        self.assertIn(b'data-index-symbol="RTY"', page)
        self.assertIn(b'data-index-symbol="NQ"', page)
        self.assertIn(b'data-index-symbol="ES"', page)
        self.assertIn(b"runCycleDesk", script)
        self.assertIn(b'data-dock="cycle"', page)
        self.assertIn(b">Cycle Desk<", page)
        self.assertIn(b"runForecast", script)
        self.assertIn(b"marketForecastRead", script)
        self.assertIn(b'data-dock="forecast"', page)
        self.assertIn(b">Forecaster<", page)
        self.assertNotIn(b">Market Forecaster<", page)
        self.assertIn(b"Open Forecaster", page)
        self.assertIn(b"No duplicate Forecaster panel", page)
        self.assertEqual(page.count(b'data-dock="forecast"'), 1)
        self.assertIn(b"Not a crystal ball", page)
        self.assertNotIn(b"Danelfin", page)
        self.assertNotIn(b"TrendSpider", page)
        self.assertNotIn(b"QuantConnect", page)
        self.assertNotIn(b"Trade Ideas", page)
        self.assertNotIn(b"AnaChart", page)
        self.assertNotIn(b"OfficeChai", page)
        self.assertNotIn(b"David Elliott", page)
        self.assertNotIn(b"kickmarketers", page.lower())
        self.assertIn(b"/api/breadth", script)
        self.assertIn(b"loadBreadth", script)
        self.assertIn(b"will not invent", script)
        self.assertIn(b"breadthTone", script)
        self.assertIn(b'"VIX"', script)
        self.assertIn(b"vixBars", script)
        self.assertIn(b"VIX falling", script)
        self.assertIn(b"function runScorecard", script)
        self.assertIn(b"function scorecardPublish", script)
        self.assertIn(b"function bestFires", script)
        self.assertIn(b"function fireAutoTrade", script)
        self.assertIn(b"function renderJournalCheck", script)
        self.assertIn(b"function refreshJournalSells", script)
        frames = Path(__file__).resolve().parents[1].joinpath("web", "frames.js").read_bytes()
        learn = Path(__file__).resolve().parents[1].joinpath("paper_trader", "learn.py").read_bytes()
        self.assertIn(b"PASS_BATCH", learn)
        self.assertIn(b'("cycle",', learn)
        self.assertIn(b'("forecast",', learn)
        self.assertIn(b"function cycleMapRead", frames)
        self.assertIn(b"function marketForecastRead", frames)
        self.assertIn(b"function forecastBoard", frames)
        self.assertIn(b"function swingGuardRead", frames)
        self.assertIn(b"function forecastBookHeat", frames)
        self.assertIn(b"confidence", frames)
        self.assertIn(b"invalidatePx", frames)
        self.assertIn(b"next support", frames)
        self.assertIn(b"function defensiveShieldScan", frames)
        self.assertIn(b"function riskReboundScan", frames)
        self.assertIn(b"function tideRead", frames)
        self.assertIn(b"function fadeRead", frames)
        self.assertIn(b"function bestFades", frames)
        self.assertIn(b"function laneAlign", frames)
        self.assertIn(b"function rangeSeat", frames)
        self.assertIn(b"function laneAllowsAuto", frames)
        self.assertIn(b"function refreshTideFade", script)
        self.assertIn(b"function rememberWeekBoard", script)
        self.assertIn(b"function renderWeekBoard", script)
        self.assertIn(b"function laneBuyReason", script)
        self.assertIn(b"Tide Alert", page)
        self.assertIn(b"Go Long 5", page)
        self.assertIn(b"Lane Align", page)
        self.assertIn(b"Range Seat", page)
        self.assertIn(b"Week Board", page)
        self.assertNotIn(b"Fade 5", page)
        self.assertNotIn(b"Simpler", page)
        self.assertNotIn(b"SimplerTrading", page)
        self.assertIn(b'id="go-long-five"', page)
        self.assertIn(b'id="tide-run"', page)
        self.assertIn(b'id="fire-five"', page)
        self.assertIn(b'id="fire-auto"', page)
        self.assertIn(b'id="week-board"', page)
        self.assertIn(b'id="journal-buys"', page)
        self.assertIn(b'id="journal-sells"', page)
        self.assertIn(b'id="journal-filled"', page)
        self.assertIn(b"Today's check", page)
        self.assertIn(b"function marketScorecard", Path(__file__).resolve().parents[1].joinpath("web", "frames.js").read_bytes())
        self.assertIn(b"Big 3 Market Scorecard", page)
        self.assertIn(b'data-dock="scorecard"', page)
        self.assertIn(b"No invented breadth", script)
        self.assertIn(b"Live locked", script)
        self.assertIn(b"Foreign bots kept out", script)
        self.assertIn(b"deskHookHealthItems", script)
        self.assertIn(b"Artex blocked", script)
        self.assertIn(b"Foreign scripts refused", script)
        self.assertIn(b"Desk Pool fail-closed", script)
        self.assertIn("Safe · green".encode("utf-8"), script)
        self.assertIn(b"ensureCatalog", script)
        self.assertIn(b"BAR_PARALLEL", script)
        self.assertIn(b"paintSqueezeReadyNote", script)
        self.assertIn(b"cachedDeskBars", script)
        self.assertIn(b"Live Trading is locked", Path(__file__).resolve().parents[1].joinpath("paper_trader", "schwab_auth.py").read_bytes())
        self.assertIn(b"studies-head", script)
        self.assertIn(b'id="chart-tools"', page)
        self.assertIn(b'id="chart-boxes"', page)
        self.assertIn(b">VIX<", page)
        self.assertIn(b'id="study-pages"', page)
        self.assertIn(b">Tools<", page)
        self.assertIn(b'data-dock="home"', page)
        self.assertIn(b"dock-back", page)
        self.assertIn(b"paintChartBoxes", script)
        self.assertIn(b"No feed", page)
        self.assertIn(b'data-dock="deskpool"', page)
        self.assertIn(b"Desk Pool", page)
        self.assertIn(b"Big Prints", page)
        self.assertIn(b"Form Prints", page)
        self.assertIn(b"Civic Trades", page)
        self.assertIn(b"function bigPrintRead", frames)
        self.assertIn(b"function scanDeskPool", script)
        self.assertIn(b"will not paint fake numbers", page)
        self.assertIn(b'class="seal"', page)
        self.assertNotIn(b"LuxeTrendsetters", page)
        self.assertNotIn(b"logo.png", page)
        self.assertNotIn("paper-trader/web/logo.png", names)
        self.assertIn(b"function paintClearEdgeArrows", script)
        self.assertIn(b"function paintReadyAimStages", script)
        self.assertIn(b"big4indicator", script)
        self.assertIn(b"function cycleScanLearn", script)
        self.assertIn(b"function topScanLearnNames", script)
        self.assertIn(b"function startScanLearnLoop", script)
        self.assertIn(b"function cycleStrategyLearn", script)
        self.assertIn(b"function learnFromPaperFill", script)
        self.assertIn(b"function preferFireFromFills", script)
        self.assertIn(b"function deskLearnPages", script)
        self.assertIn(b"function cycleDeskPageLearn", script)
        self.assertIn(b'id="desk-clear"', page)
        self.assertIn(b"best-setup", page)
        self.assertIn(b"Best setup Auto", page)
        self.assertIn(b"best-setup-gates", page)
        self.assertIn(b'id="sqz-chart"', page)
        self.assertIn(b'id="cci-chart"', page)
        self.assertIn(b'id="vol-chart"', page)
        self.assertIn(b"function drawVolumeWave", script)
        self.assertIn(b"Volume Wave", page)
        self.assertIn(b'id="chart-flags"', page)
        self.assertIn(b'id="studies-window"', page)
        self.assertIn(b"function openStudiesWindow", script)
        self.assertIn(b"function drawCci", script)
        self.assertIn(b"trig-best", page)
        self.assertIn(b"BEST_MAX_BUYS = 3", script)
        self.assertIn(b"function bestSetupStatus", script)
        self.assertIn(b"Not extreme greed", script)
        self.assertIn(b"Fresh quote", script)
        self.assertIn(b"TMO above", script)
        self.assertIn(b"TMO turns green", script)
        self.assertIn(b"VIX falling (closed daily vs prior)", script)
        self.assertIn(b"CCI 50 \xc2\xb7 14 \xc2\xb7 5 into zone +100", script)
        self.assertIn(b"5 squeeze dots or in a squeeze", script)
        self.assertNotIn(b"Under the upper Bollinger band", script)
        self.assertIn(b"EDGE_TRAIL = 2.5", Path(__file__).resolve().parents[1].joinpath("web", "frames.js").read_bytes())
        framesEdge = Path(__file__).resolve().parents[1].joinpath("web", "frames.js").read_bytes()
        self.assertIn(b"dots[index] >= EDGE_DOTS || study.level[index] > 0", framesEdge)
        self.assertIn(b"cci(list, 14)", framesEdge)
        self.assertIn(b"main > tmo.os", framesEdge)
        self.assertIn(b"Edge Fire (TMO \xc2\xb7 Squeeze \xc2\xb7 CCI \xc2\xb7 VIX)", framesEdge)
        self.assertIn(b"TMO turns green", framesEdge)
        self.assertNotIn(b"under the upper Bollinger band", framesEdge)
        frames = Path(__file__).resolve().parents[1].joinpath("web", "frames.js").read_bytes()
        self.assertIn(b"function deskClearRead", frames)
        self.assertIn(b"function cciPane", frames)
        self.assertIn(b"function volumeWave", frames)
        self.assertIn(b"multiFrameLongFlags", frames)
        self.assertIn("Squeeze · TMO · big4indicator · stack · MTF".encode(), frames)
        self.assertIn(b"function big4Indicator", frames)
        self.assertIn(b"function deskInternals", frames)
        self.assertIn(b"syncDeskInternals", script)
        self.assertIn(b"bestBlocks", script)
        self.assertNotIn(b"until the STOP line is fixed", script)
        self.assertNotIn(b"Squeeze Pro", frames)
        self.assertNotIn(b"Ten X", script)
        self.assertIn(b"strat-board-table", page)
        self.assertIn(b"call-pill.wait", Path(__file__).resolve().parents[1].joinpath("web", "app.css").read_bytes())
        self.assertIn(b"ccizoom", script)
        self.assertIn(b"ccisnap", script)
        self.assertIn(b'value="ccizoom"', page)
        self.assertIn(b'value="ccisnap"', page)
        self.assertNotIn(b"SnapBack", script)
        self.assertNotIn(b"David Elliott", script)
        self.assertNotIn(b"David Elliot", script)
        self.assertIn(b"golden", script)
        self.assertIn(b"weeklow", script)
        self.assertIn(b"warnDesk", script)
        self.assertIn(b'id="trig-edge"', page)
        self.assertIn(b"macdx", page)
        self.assertIn(b'id="strat-buy"', page)
        self.assertIn(b'id="strat-sell"', page)
        self.assertIn(b"stratTrade", script)
        self.assertIn(b'"quad"', script)
        self.assertIn(b'value="quad"', page)
        self.assertIn("Squeeze · TMO · big4indicator · stack · MTF".encode("utf-8"), page)
        self.assertNotIn(b"Squeeze Pro", script)
        self.assertNotIn(b"Squeeze Pro", page)
        self.assertIn(b"nudgeLearn", script)
        self.assertIn(b"announceNewProcess", script)
        self.assertIn(b"New process", script)
        self.assertIn(b"/api/learn/touch", desk)
        self.assertIn(b"def stamp_process_on_fills", learn)
        self.assertIn(b"stackAverages", script)
        self.assertIn(b"paintCandles", script)
        self.assertIn(b"SMA 5", script)
        self.assertIn(b"SMA stack", page)
        self.assertIn(b'id="book-share"', page)
        self.assertIn(b'id="book-file"', page)
        self.assertIn(b'id="position-chip"', page)
        self.assertIn(b'id="desk-status"', page)
        self.assertIn(b'id="day-strip"', page)
        self.assertIn(b'id="ticket-preview"', page)
        self.assertIn(b"quoteAgeText", script)
        self.assertIn(b"paintTicketPreview", script)
        self.assertIn(b"scrubReadout", script)
        self.assertIn(b"/api/paper/book", desk)
        self.assertIn(b"def import_book", desk)
        self.assertIn(b"importPaperBook", script)
        self.assertNotIn(b"Mobius", script)
        self.assertNotIn(b"Spot_Inflow", script)
        self.assertNotIn(b"avgs_mtf_10x", script)
        self.assertIn(b"drawPriceLine", script)
        self.assertIn(b'id="edge-call"', page)
        self.assertIn(b"It is not a forecast.", page)
        self.assertIn(b"Dark pool books", page)
        self.assertIn(b"function hiddenTape", phone)
        self.assertIn(b"function edge(bars, options)", phone)
        self.assertIn(b"SCAN_READERS = 1", script)
        self.assertIn(b"SCAN_GAP = 300", script)
        self.assertIn(b"publishSqueeze", script)
        self.assertIn(b'value="stocks"', page)
        self.assertIn(b'value="main_etfs"', page)
        self.assertIn(b"13 main ETFs", page)
        self.assertIn(b'id="journal-working-body"', page)
        self.assertIn(b'id="chart-back"', page)
        self.assertIn(b"Add to Home Screen", page)
        self.assertIn(b"It is not a third-party study", page)
        self.assertNotIn(b"SqueezePRO", script)
        self.assertNotIn(b"Simpler Trading", script)
        self.assertNotIn(b"TTM_Squeeze", script)
        self.assertNotIn(b"worker(), worker(), worker()", script)
        self.assertNotIn(b'document.write("<!DOCTYPE html><title>" +', script)
        self.assertIn(b'id="signal-sum"', page)
        self.assertIn(b'class="seal"', page)
        self.assertIn(b"prefers-reduced-motion", phone)
        self.assertIn(b"/ticker", live)
        self.assertIn(b'id="scan-toggle"', page)
        self.assertIn(b"Scanner on", page)
        self.assertIn(b'id="scan-toggle"', phone)
        self.assertIn(b"Scanner on", phone)
        self.assertIn(b"www.bitstamp.net", phone)
        self.assertNotIn(b"cache: \"no-store\"", live)
        self.assertIn(b"fetch(url)", live)
        self.assertIn(b"api.gemini.com", phone)
        self.assertNotIn(b"AbortController", phone)
        self.assertIn(b"A github.com link to this file shows the code", phone)
        self.assertIn(b'id="open-help"', page)
        self.assertIn(b"BTC-USD\\tBitcoin\\tcrypto", phone)
        self.assertIn(b"VGT\\tVanguard", phone)
        self.assertIn(b"ES\\tE-mini S&P 500\\tfuture", phone)
        self.assertIn(b"quote.cnbc.com", phone)
        self.assertIn(b"ts-api.cnbc.com", phone)
        self.assertNotIn(b'$("category").value = ""', script)
        self.assertIn(b"This phone scans live crypto", script)
        self.assertIn(b"This phone scans live stocks", script)
        self.assertIn(b"Green signal on each name", script)
        self.assertIn(b'classList.add("go")', script)
        self.assertIn(b"Daily scan", page)
        self.assertIn(b"id=\"scan\"", page)
        self.assertIn(b">Voice<", page)
        self.assertIn(b"/api/voice", script)
        self.assertIn(b"Five squeeze dots", script)
        self.assertEqual(((journal.external_attr >> 16) & 0o170000), 0o100000)
        self.assertNotEqual(journal.date_time, (1980, 1, 1, 0, 0, 0))
        self.assertIn("paper-trader/", names)
        self.assertIn("paper-trader/paper_trader/", names)
        self.assertIn("paper-trader/web/", names)
        self.assertIn(b"Fear and greed", script)
        self.assertIn(b"Zoom in", page)
        self.assertIn(b"quote-mood", page)
        self.assertIn(b"mood-clock", page)
        self.assertIn(b"true momentum", page)
        self.assertIn(b"big4indicator on, candles paint all green", page)
        self.assertIn("Ready · Aim · Fire".encode("utf-8"), page)
        self.assertIn(b"Desk is working.", script)
        self.assertIn(b'id="signal-strip"', page)
        self.assertIn(b'id="chart-read"', page)
        self.assertIn(b'id="edge-note"', phone)
        self.assertIn(b"any phone or tablet", phone)
        self.assertIn(b"Open with, then Edge", phone)
        self.assertIn(b"Share button on PaperTrader.html, then tap Edge", note)
        self.assertIn(b"choose Open with, then tap Edge", note)
        self.assertNotIn(b"choose Safari", note)
        self.assertIn(b"microsoft-edge:", desk)

    def test_edge_window_stays_visible_and_the_desk_answers_first(self) -> None:
        self.assertEqual(_window_flags(r"C:\Program Files\Microsoft\Edge\Application\msedge.exe", "win32"), 0)
        self.assertEqual(_window_flags("msedge", "win32"), 0)
        self.assertEqual(_window_flags("cmd", "win32"), 0x08000000)
        self.assertEqual(_window_flags("microsoft-edge", "linux"), 0)
        calls = []

        def probe(url: str) -> bool:
            calls.append(url)
            return len(calls) >= 2

        self.assertTrue(_wait_for_desk("http://127.0.0.1:8765/", probe, lambda _seconds: None, tries=4))
        self.assertEqual(calls, ["http://127.0.0.1:8765/api/health", "http://127.0.0.1:8765/api/health"])

        def fail(url: str) -> bool:
            raise OSError("down")

        self.assertFalse(_wait_for_desk("http://127.0.0.1:9/", fail, lambda _seconds: None, tries=2))

    def test_windows_edge_commands_name_edge_and_stay_local(self) -> None:
        url = "http://127.0.0.1:8765/"
        commands = _edge_commands(url, "win32")
        self.assertTrue(any(command[0].endswith("msedge.exe") and "Microsoft" in command[0] and command[1] == url for command in commands))
        self.assertIn(["msedge", url], commands)
        self.assertIn(["cmd", "/c", "start", "", "microsoft-edge:" + url], commands)
        blob = " ".join(" ".join(command) for command in commands)
        self.assertNotIn("0.0.0.0", blob)
        self.assertNotIn("192.168.", blob)
        self.assertEqual(
            [command[0] for command in _edge_commands(url, "linux")],
            ["microsoft-edge", "microsoft-edge-stable", "msedge"],
        )

    def test_edge_launcher_starts_a_present_program(self) -> None:
        url = "http://127.0.0.1:8765/"
        with tempfile.TemporaryDirectory() as folder:
            exe = Path(folder) / "msedge.exe"
            exe.write_bytes(b"")
            calls = []

            def popen(command, close_fds, creationflags):
                calls.append(command)
                return object()

            with patch("webbrowser.open") as opened:
                text = _launch_edge(url, [[str(exe), url]], lambda name: None, popen)
        self.assertEqual(text, "Microsoft Edge")
        self.assertEqual(calls, [[str(exe), url]])
        opened.assert_not_called()

    def test_edge_launcher_skips_a_missing_program(self) -> None:
        url = "http://127.0.0.1:8765/"
        calls = []

        def popen(command, close_fds, creationflags):
            calls.append(command)
            raise OSError("absent")

        with patch("webbrowser.open", return_value=True) as opened:
            text = _launch_edge(url, [["/no/such/msedge.exe", url], ["msedge", url]], lambda name: None, popen)
        self.assertEqual(calls, [])
        self.assertEqual(text, "the default browser. Microsoft Edge was not found")
        opened.assert_called_once_with(url)

    def test_edge_launcher_tries_the_next_command(self) -> None:
        url = "http://127.0.0.1:9/"
        calls = []

        def popen(command, close_fds, creationflags):
            calls.append(command[0])
            if len(calls) == 1:
                raise OSError("busy")
            return object()

        with tempfile.TemporaryDirectory() as folder:
            first = Path(folder) / "a.exe"
            second = Path(folder) / "b.exe"
            first.write_bytes(b"")
            second.write_bytes(b"")
            with patch("webbrowser.open") as opened:
                text = _launch_edge(url, [[str(first), url], [str(second), url]], lambda name: None, popen)
        self.assertEqual(text, "Microsoft Edge")
        self.assertEqual(calls, [str(first), str(second)])
        opened.assert_not_called()


if __name__ == "__main__":
    unittest.main()
