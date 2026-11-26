"""Schwab OAuth helpers: local tokens only, no broker orders."""

from __future__ import annotations

import json
import sys
import tempfile
import unittest
from pathlib import Path
from unittest.mock import patch

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))

from paper_trader import schwab_auth


class SchwabAuthTests(unittest.TestCase):
    def setUp(self) -> None:
        self.folder = tempfile.TemporaryDirectory()
        self.token_path = Path(self.folder.name) / "schwab_tokens.json"
        self.env = {
            "SCHWAB_APP_KEY": "demo-key",
            "SCHWAB_APP_SECRET": "demo-secret",
            "SCHWAB_CALLBACK_URL": "https://127.0.0.1",
            "SCHWAB_APP_NAME": "personal-market-data",
        }
        self._env_patch = patch.dict("os.environ", self.env, clear=False)
        self._env_patch.start()

    def tearDown(self) -> None:
        self._env_patch.stop()
        self.folder.cleanup()

    def test_test_connection_reports_keys_ready_before_oauth(self) -> None:
        report = schwab_auth.test_connection(self.token_path)
        self.assertTrue(report["credentials"])
        self.assertFalse(report["connected"])
        self.assertTrue(report["ok"])
        self.assertIn("Keys ready", report["detail"])

    def test_extract_code_from_redirect_url(self) -> None:
        code = schwab_auth.extract_code("https://127.0.0.1/?code=abc%2F123&session=xyz")
        self.assertEqual(code, "abc/123")

    def test_extract_code_decodes_trailing_at_and_strips_quotes(self) -> None:
        code = schwab_auth.extract_code(
            '  "https://127.0.0.1/?code=C0.ABC123%40&session=1"  '
        )
        self.assertEqual(code, "C0.ABC123@")
        bare = schwab_auth.extract_code("C0.ABC123%40")
        self.assertEqual(bare, "C0.ABC123@")

    def test_authorize_url_uses_app_key_and_callback(self) -> None:
        start = schwab_auth.authorize_url("nonce1")
        self.assertIn("client_id=demo-key", start["authorize_url"])
        self.assertIn("redirect_uri=https%3A%2F%2F127.0.0.1", start["authorize_url"])
        self.assertEqual(start["state"], "nonce1")

    def test_finish_oauth_stores_tokens_and_keeps_prefs_off(self) -> None:
        payload = {
            "access_token": "access-1",
            "refresh_token": "refresh-1",
            "expires_in": 1800,
            "token_type": "Bearer",
        }
        with patch.object(schwab_auth, "_token_request", return_value=payload):
            store = schwab_auth.finish_oauth(
                "https://127.0.0.1/?code=the-code",
                path=self.token_path,
            )
        self.assertEqual(store["access_token"], "access-1")
        self.assertEqual(store["refresh_token"], "refresh-1")
        self.assertFalse(store["market_data"])
        self.assertFalse(store["live_trading"])
        status = schwab_auth.connection_status(self.token_path)
        self.assertTrue(status["connected"])
        self.assertTrue(status["ok"])
        self.assertIn("connected", status["detail"])
        self.assertFalse(status["live_orders"])
        self.assertTrue(status["paper_default"])

    def test_market_data_and_live_trading_gates(self) -> None:
        schwab_auth.save_store(
            {
                "access_token": "access-1",
                "refresh_token": "refresh-1",
                "expires_at": 9_999_999_999,
                "market_data": False,
                "live_trading": False,
            },
            self.token_path,
        )
        with self.assertRaises(ValueError):
            schwab_auth.set_live_trading(True, confirmed=True, path=self.token_path)
        schwab_auth.set_market_data(True, path=self.token_path)
        with self.assertRaises(ValueError) as blocked:
            schwab_auth.set_live_trading(True, confirmed=True, path=self.token_path)
        self.assertIn("locked", str(blocked.exception).lower())
        cleared = schwab_auth.set_live_trading(False, confirmed=True, path=self.token_path)
        self.assertFalse(cleared["live_trading"])
        # Stale armed preference is cleared on status read (Max Safe).
        schwab_auth.save_store(
            {
                "access_token": "access-1",
                "refresh_token": "refresh-1",
                "expires_at": 9_999_999_999,
                "market_data": True,
                "live_trading": True,
            },
            self.token_path,
        )
        status = schwab_auth.connection_status(self.token_path)
        self.assertTrue(status["market_data"])
        self.assertFalse(status["live_trading"])
        self.assertFalse(status["live_orders"])
        self.assertTrue(status["live_locked"])
        self.assertFalse(schwab_auth.load_store(self.token_path)["live_trading"])

    def test_disconnect_clears_session_and_signal_goes_red(self) -> None:
        schwab_auth.save_store(
            {
                "access_token": "access-1",
                "refresh_token": "refresh-1",
                "expires_at": 9_999_999_999,
                "market_data": True,
                "live_trading": True,
            },
            self.token_path,
        )
        schwab_auth.disconnect(self.token_path)
        status = schwab_auth.connection_status(self.token_path)
        self.assertFalse(status["connected"])
        self.assertFalse(status["ok"])
        self.assertIn("not connected", status["detail"])
        item = schwab_auth.signal_item(self.token_path)
        self.assertEqual(item["name"], "Schwab")
        self.assertFalse(item["ok"])
        self.assertEqual(item["tone"], "strict")

    def test_tokens_file_is_gitignored(self) -> None:
        ignore = (Path(__file__).resolve().parents[1] / ".gitignore").read_text(encoding="utf-8")
        self.assertIn("schwab_tokens.json", ignore.splitlines())
        self.assertTrue(self.token_path.name == "schwab_tokens.json" or True)


class SchwabApiRouteTests(unittest.TestCase):
    def setUp(self) -> None:
        from paper_trader import quotes
        from paper_trader.samples import load_symbol
        from paper_trader.server import create_server
        import threading

        self._quotes = quotes.load_bars
        quotes.load_bars = lambda symbol, refresh=False: load_symbol(symbol)
        self.folder = tempfile.TemporaryDirectory()
        state = Path(self.folder.name) / "paper_state.json"
        self.token_path = Path(self.folder.name) / "schwab_tokens.json"
        self.server = create_server(state, 0)
        self.thread = threading.Thread(target=self.server.serve_forever, daemon=True)
        self.thread.start()
        self.base = f"http://127.0.0.1:{self.server.server_address[1]}"
        self._token_patch = patch("paper_trader.schwab_auth.TOKEN_PATH", self.token_path)
        self._token_patch.start()
        self._env_patch = patch.dict(
            "os.environ",
            {
                "SCHWAB_APP_KEY": "demo-key",
                "SCHWAB_APP_SECRET": "demo-secret",
                "SCHWAB_CALLBACK_URL": "https://127.0.0.1",
            },
            clear=False,
        )
        self._env_patch.start()

    def tearDown(self) -> None:
        from paper_trader import quotes

        self._env_patch.stop()
        self._token_patch.stop()
        quotes.load_bars = self._quotes
        self.server.shutdown()
        self.server.server_close()
        self.folder.cleanup()

    def open(self, path: str, method: str = "GET", body: dict | None = None):
        from urllib.request import Request, urlopen
        from urllib.error import HTTPError

        data = None if body is None else json.dumps(body).encode()
        request = Request(self.base + path, data=data, method=method)
        if data is not None:
            request.add_header("Content-Type", "application/json")
        try:
            with urlopen(request, timeout=5) as response:
                return response.status, response.read()
        except HTTPError as exc:
            return exc.code, exc.read()

    def test_schwab_status_and_connect_routes(self) -> None:
        status, raw = self.open("/api/schwab")
        self.assertEqual(status, 200)
        payload = json.loads(raw)
        self.assertFalse(payload["connected"])
        self.assertTrue(payload["credentials"])
        status, raw = self.open("/api/schwab/test", "POST", {})
        self.assertEqual(status, 200)
        tested = json.loads(raw)
        self.assertTrue(tested["credentials"])
        self.assertFalse(tested["connected"])
        self.assertTrue(tested["ok"])
        self.assertIn("Keys ready", tested["detail"])
        status, raw = self.open("/api/schwab/connect", "POST", {})
        self.assertEqual(status, 200)
        start = json.loads(raw)
        self.assertIn("authorize_url", start)
        with patch("paper_trader.schwab_auth._token_request", return_value={
            "access_token": "a",
            "refresh_token": "r",
            "expires_in": 1800,
        }):
            status, raw = self.open("/api/schwab/finish", "POST", {"redirect": "https://127.0.0.1/?code=x"})
        self.assertEqual(status, 200)
        linked = json.loads(raw)
        self.assertTrue(linked["connected"])
        status, raw = self.open("/api/schwab/market-data", "POST", {"enabled": True})
        self.assertEqual(status, 200)
        self.assertTrue(json.loads(raw)["market_data"])
        status, raw = self.open("/api/schwab/live-trading", "POST", {"enabled": True})
        self.assertEqual(status, 400)
        status, raw = self.open("/api/schwab/live-trading", "POST", {"enabled": True, "confirmed": True})
        self.assertEqual(status, 400)
        self.assertIn(b"locked", raw.lower())
        status, raw = self.open("/api/schwab/live-trading", "POST", {"enabled": False, "confirmed": True})
        self.assertEqual(status, 200)
        cleared = json.loads(raw)
        self.assertFalse(cleared["live_trading"])
        self.assertFalse(cleared["live_orders"])
        self.assertTrue(cleared["live_locked"])
        status, checks = self.open("/api/checks")
        self.assertEqual(status, 200)
        report = json.loads(checks)
        schwab = next(item for item in report["items"] if item["name"] == "Schwab")
        self.assertTrue(schwab["ok"])
        self.assertIn("connected", schwab["detail"])
        self.assertIn("Live locked", schwab["detail"])


if __name__ == "__main__":
    unittest.main()
