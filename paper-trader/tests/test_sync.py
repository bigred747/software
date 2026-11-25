from __future__ import annotations

import json
import tempfile
import unittest
from pathlib import Path
from unittest import mock

from paper_trader import sync


class SyncTests(unittest.TestCase):
    def test_ensure_code_writes_env(self) -> None:
        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp)
            code = sync.ensure_code(root)
            self.assertGreaterEqual(len(code), 6)
            text = (root / ".env").read_text(encoding="utf-8")
            self.assertIn(f"PAPER_SYNC_CODE={code}", text)
            self.assertEqual(sync.read_code(root), code)

    def test_push_and_pull_round_trip(self) -> None:
        book = {"version": 1, "sessions": {"SPY": {"shares": 1, "cash": 1000}}, "markets": 1}

        def fake_urlopen(req, timeout=45):
            class Resp:
                def __enter__(self):
                    return self

                def __exit__(self, *args):
                    return False

                def read(self):
                    if req.get_method() == "PUT":
                        return json.dumps({"ok": True, "markets": 1}).encode()
                    return json.dumps({"ok": True, "markets": 1, "book": book}).encode()

            return Resp()

        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp)
            with mock.patch("urllib.request.urlopen", side_effect=fake_urlopen):
                pushed = sync.push_book(book, root)
                pulled = sync.pull_book(root)
            self.assertTrue(pushed["ok"])
            self.assertEqual(pulled["book"]["markets"], 1)


if __name__ == "__main__":
    unittest.main()
