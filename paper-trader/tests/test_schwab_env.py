"""Schwab credential scaffolding: load .env only, never call the API."""

from __future__ import annotations

import os
import sys
import tempfile
import unittest
from pathlib import Path
from unittest.mock import patch

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))

from paper_trader import schwab_env
from paper_trader.cli import main


class SchwabEnvTests(unittest.TestCase):
    def setUp(self) -> None:
        self._saved = {key: os.environ.get(key) for key in schwab_env.KEYS}
        for key in schwab_env.KEYS:
            os.environ.pop(key, None)

    def tearDown(self) -> None:
        for key, value in self._saved.items():
            if value is None:
                os.environ.pop(key, None)
            else:
                os.environ[key] = value

    def test_parse_env_text_ignores_comments_and_quotes(self) -> None:
        text = (
            "# comment\n"
            "SCHWAB_APP_KEY=abc\n"
            "SCHWAB_APP_SECRET=\"sec ret\"\n"
            "export SCHWAB_CALLBACK_URL=https://127.0.0.1\n"
            "SCHWAB_APP_NAME=personal-market-data\n"
            "OTHER=ignored\n"
        )
        parsed = schwab_env.parse_env_text(text)
        self.assertEqual(parsed["SCHWAB_APP_KEY"], "abc")
        self.assertEqual(parsed["SCHWAB_APP_SECRET"], "sec ret")
        self.assertEqual(parsed["SCHWAB_CALLBACK_URL"], "https://127.0.0.1")
        self.assertEqual(parsed["SCHWAB_APP_NAME"], "personal-market-data")
        self.assertEqual(parsed["OTHER"], "ignored")

    def test_missing_env_reports_missing(self) -> None:
        with tempfile.TemporaryDirectory() as folder:
            path = Path(folder) / ".env"
            with patch.object(schwab_env, "ENV_PATH", path):
                self.assertFalse(schwab_env.credentials_ready())
                self.assertEqual(schwab_env.status_line(), "missing")
                report = schwab_env.status_report()
        self.assertEqual(report["detail"], "missing")
        self.assertFalse(report["ready"])
        self.assertFalse(report["connected"])
        self.assertFalse(report["live"])
        self.assertEqual(report["kind"], "health")

    def test_env_file_marks_ready_without_connecting(self) -> None:
        with tempfile.TemporaryDirectory() as folder:
            path = Path(folder) / ".env"
            path.write_text(
                "SCHWAB_APP_KEY=key123\nSCHWAB_APP_SECRET=secret456\n",
                encoding="utf-8",
            )
            with patch.object(schwab_env, "ENV_PATH", path):
                self.assertTrue(schwab_env.credentials_ready())
                self.assertEqual(schwab_env.status_line(), "ready (not connected)")
                report = schwab_env.status_report()
        self.assertEqual(report["detail"], "ready (not connected)")
        self.assertTrue(report["ready"])
        self.assertFalse(report["connected"])
        self.assertFalse(report["live"])

    def test_cli_schwab_status_prints_line(self) -> None:
        with tempfile.TemporaryDirectory() as folder:
            path = Path(folder) / ".env"
            with patch.object(schwab_env, "ENV_PATH", path):
                code = main(["schwab-status"])
        self.assertEqual(code, 0)

    def test_example_env_lists_required_keys(self) -> None:
        example = Path(__file__).resolve().parents[1] / ".env.example"
        text = example.read_text(encoding="utf-8")
        for key in schwab_env.KEYS:
            self.assertIn(f"{key}=", text)
        self.assertIn("https://127.0.0.1", text)
        self.assertIn("personal-market-data", text)
        self.assertIn("OAuth", text)

    def test_gitignore_covers_dotenv(self) -> None:
        ignore = (Path(__file__).resolve().parents[1] / ".gitignore").read_text(encoding="utf-8")
        self.assertIn(".env", ignore.splitlines())


if __name__ == "__main__":
    unittest.main()
