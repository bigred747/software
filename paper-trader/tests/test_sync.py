from __future__ import annotations

import json
import tempfile
import unittest
from pathlib import Path
from unittest import mock

from paper_trader import sync


class SyncTests(unittest.TestCase):
    def test_status_without_token(self) -> None:
        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp)
            report = sync.status(root)
            self.assertFalse(report["has_token"])
            self.assertEqual(report["hardening"], "fort-knox")

    def test_push_and_pull_round_trip(self) -> None:
        book = {"version": 1, "sessions": {"SPY": {"shares": 1, "cash": 1000}}, "markets": 1}
        token = "a" * 64

        def fake_urlopen(req, timeout=45):
            auth = req.get_header("Authorization") or ""
            self.assertTrue(auth.startswith("Bearer "))
            self.assertTrue(req.get_header("X-desk-timestamp") or req.get_header("X-Desk-Timestamp"))
            self.assertTrue(req.get_header("X-desk-signature") or req.get_header("X-Desk-Signature"))
            self.assertTrue(req.get_header("X-desk-nonce") or req.get_header("X-Desk-Nonce"))

            class Resp:
                def __enter__(self):
                    return self

                def __exit__(self, *args):
                    return False

                def read(self):
                    if req.get_method() == "PUT":
                        return json.dumps({"ok": True, "markets": 1, "vault": "sealed"}).encode()
                    return json.dumps({"ok": True, "markets": 1, "book": book, "vault": "sealed"}).encode()

            return Resp()

        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp)
            (root / ".env").write_text(f"PAPER_SYNC_TOKEN={token}\n", encoding="utf-8")
            with mock.patch("urllib.request.urlopen", side_effect=fake_urlopen):
                pushed = sync.push_book(book, root)
                pulled = sync.pull_book(root)
            self.assertTrue(pushed["ok"])
            self.assertEqual(pulled["book"]["markets"], 1)


if __name__ == "__main__":
    unittest.main()
