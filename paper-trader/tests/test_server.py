"""The desk stays on this computer and serves the sample session."""

from __future__ import annotations

import json
import sys
import tempfile
import threading
import unittest
from pathlib import Path
from urllib.error import HTTPError
from urllib.request import Request, urlopen

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))

from paper_trader.cli import main
from paper_trader.guard import assert_safe_launch, is_temp_zip_extract
from paper_trader.server import create_server

ROOT = Path(__file__).resolve().parents[1]


class ServerTests(unittest.TestCase):
    def setUp(self) -> None:
        self.folder = tempfile.TemporaryDirectory()
        state = Path(self.folder.name) / "paper_state.json"
        self.server = create_server(state, 0)
        self.thread = threading.Thread(target=self.server.serve_forever, daemon=True)
        self.thread.start()
        self.base = f"http://127.0.0.1:{self.server.server_address[1]}"

    def tearDown(self) -> None:
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
        self.assertIn(b'id="paper"', page)
        self.assertIn(b">Auto<", page)
        self.assertIn(b">Paper<", page)
        self.assertNotIn(b"deposit", page.lower())

    def test_paper_button_engages_paper_and_rejects_another_mode(self) -> None:
        status, raw = self.open("/api/paper/mode", "POST", {"mode": "paper"})
        self.assertEqual(status, 200)
        payload = json.loads(raw)
        self.assertEqual(payload["mode"], "paper")
        self.assertTrue(payload["paper"])
        self.assertEqual(self.open("/api/paper/mode", "POST", {"mode": "live"})[0], 400)

    def test_step_and_backtest(self) -> None:
        status, raw = self.open("/api/paper")
        self.assertEqual(status, 200)
        before = json.loads(raw)["cursor"]
        status, stepped = self.open("/api/paper/step", "POST", {"days": 1})
        self.assertEqual(status, 200)
        self.assertEqual(json.loads(stepped)["cursor"], before + 1)
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
        self.assertEqual(main(["serve", "--port", "80", "--no-browser"]), 2)

    def test_package_does_not_ask_for_money_or_keys(self) -> None:
        banned = ("quantumtrade", "paypal", "seed phrase", "mnemonic", "guaranteed profit", "deposit")
        roots = [ROOT / "paper_trader", ROOT / "web", ROOT / "README.md", ROOT / "Start-Paper-Trader.bat"]
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
        self.assertIn("python -m paper_trader serve", bat)
        self.assertIn("if not errorlevel 1", bat)
        self.assertNotIn("powershell", bat)
        self.assertNotIn("pip", bat)
        self.assertNotIn("requirements", bat)


if __name__ == "__main__":
    unittest.main()
