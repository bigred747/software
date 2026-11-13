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
        for name in ("Paper", "Auto", "Trade", "Ticker", "Chart", "Markets", "Broker", "Install", "Learning", "Fear and greed", "Zoom"):
            self.assertIn(name, names)
        for item in payload["items"]:
            self.assertTrue(item["ok"])
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
        for name in ("This computer only", "Strangers blocked", "Live locked", "Quote hosts", "Circuit breaker", "Order rules", "Journal saves", "Program unchanged", "Launchers clean", "Quiet installer"):
            self.assertIn(name, names)
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
        temp_zip = Path(r"C:\Users\rick\AppData\Local\Temp\abc_pack.zip.43d\app")
        self.assertTrue(is_temp_zip_extract(temp_zip))
        self.assertFalse(is_temp_zip_extract(Path(r"C:\Users\rick\Documents\paper-trader")))
        with self.assertRaises(ValueError):
            assert_safe_launch([temp_zip])
        program = Path(r"C:\Users\rick\Documents\Paper Trader")
        self.assertFalse(is_temp_zip_extract(program))
        assert_safe_launch([program])
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
        banned = ("quantumtrade", "paypal", "seed phrase", "mnemonic", "guaranteed profit", "deposit")
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
            live = packed.read("paper-trader/web/live.js")
            journal = packed.getinfo("paper-trader/paper_trader/journal.py")
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
        self.assertIn(b"B4 readings", page)
        self.assertIn(b"drawArrow", script)
        self.assertIn("paper-trader/paper_trader/catalog.txt", names)
        self.assertIn("paper-trader/web/study.js", names)
        self.assertIn("paper-trader/web/frames.js", names)
        self.assertIn("paper-trader/web/frame.html", names)
        self.assertIn("paper-trader/iphone/PaperTrader.html", names)
        self.assertIn(b"Install study", page)
        self.assertIn(b"Open all 10 windows", page)
        self.assertIn(b"..", path_file)
        self.assertIn(b"1.9.41", script)
        self.assertIn(b"drawPriceLine", script)
        self.assertIn(b'id="edge-call"', page)
        self.assertIn(b"It is not a forecast.", page)
        self.assertIn(b"Dark pool books", page)
        self.assertIn(b"function hiddenTape", phone)
        self.assertIn(b"function edge(bars, options)", phone)
        self.assertIn(b"SCAN_READERS = 3", script)
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
        self.assertIn(b"Download Linked File", phone)
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
        self.assertIn(b"Candles are green when the close is at or above the open", page)
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
