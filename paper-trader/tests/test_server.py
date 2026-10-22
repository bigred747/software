"""The desk stays on this computer and serves the paper session."""

from __future__ import annotations

import json
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
        status, traded = self.open("/api/paper/trade", "POST", {})
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
                self.assertTrue(any(row["symbol"] == "BTC-USD" for row in view["leaders"]))

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
        self.assertIn(b"1.9.21", script)
        self.assertIn(b"This phone scans live crypto", script)
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
