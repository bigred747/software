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
        self._watched = list(schwab_env.KEYS) + list(schwab_env.ALIAS_TO_CANONICAL)
        self._saved = {key: os.environ.get(key) for key in self._watched}
        for key in self._watched:
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

    def test_portal_client_id_aliases_are_ready(self) -> None:
        with tempfile.TemporaryDirectory() as folder:
            path = Path(folder) / ".env"
            path.write_text(
                "SCHWAB_CLIENT_ID=portal-client-id\n"
                "SCHWAB_CLIENT_SECRET=portal-client-secret\n"
                "SCHWAB_CALLBACK_URL=https://127.0.0.1\n",
                encoding="utf-8",
            )
            with patch.object(schwab_env, "ENV_PATH", path), patch.object(
                schwab_env, "INSTALL_ROOT", Path(folder)
            ):
                self.assertTrue(schwab_env.credentials_ready())
                self.assertEqual(schwab_env._configured_value("SCHWAB_APP_KEY"), "portal-client-id")
                self.assertEqual(
                    schwab_env._configured_value("SCHWAB_APP_SECRET"), "portal-client-secret"
                )

    def test_keys_file_syncs_into_placeholder_env(self) -> None:
        with tempfile.TemporaryDirectory() as folder:
            root = Path(folder)
            (root / ".env").write_text(
                "SCHWAB_APP_KEY=paste-app-key-here\nSCHWAB_APP_SECRET=paste-app-secret-here\n",
                encoding="utf-8",
            )
            (root / "SCHWAB-KEYS.txt").write_text(
                "SCHWAB_CLIENT_ID=real-client-id\nSCHWAB_CLIENT_SECRET=real-client-secret\n",
                encoding="utf-8",
            )
            with patch.object(schwab_env, "ENV_PATH", root / ".env"), patch.object(
                schwab_env, "INSTALL_ROOT", root
            ):
                schwab_env.ensure_env_file(root)
                self.assertTrue(schwab_env.credentials_ready())
            text = (root / ".env").read_text(encoding="utf-8")
            self.assertIn("SCHWAB_APP_KEY=real-client-id", text)
            self.assertIn("SCHWAB_APP_SECRET=real-client-secret", text)

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
        root = Path(__file__).resolve().parents[1]
        for name in (".env.example", "SCHWAB-KEYS.txt"):
            text = (root / name).read_text(encoding="utf-8")
            for key in schwab_env.KEYS:
                self.assertIn(f"{key}=", text)
            self.assertIn("paste-app-key-here", text)
            self.assertIn("paste-app-secret-here", text)
            self.assertIn("https://127.0.0.1", text)
            self.assertIn("personal-market-data", text)

    def test_ensure_env_file_copies_template_once(self) -> None:
        with tempfile.TemporaryDirectory() as folder:
            root = Path(folder)
            (root / "SCHWAB-KEYS.txt").write_text(
                "SCHWAB_APP_KEY=paste-app-key-here\nSCHWAB_APP_SECRET=paste-app-secret-here\n",
                encoding="utf-8",
            )
            created = schwab_env.ensure_env_file(root)
            self.assertEqual(created, root / ".env")
            self.assertTrue(created.is_file())
            self.assertIn("paste-app-key-here", created.read_text(encoding="utf-8"))
            created.write_text("SCHWAB_APP_KEY=real\nSCHWAB_APP_SECRET=secret\n", encoding="utf-8")
            again = schwab_env.ensure_env_file(root)
            self.assertEqual(again.read_text(encoding="utf-8"), "SCHWAB_APP_KEY=real\nSCHWAB_APP_SECRET=secret\n")

    def test_placeholders_are_not_ready(self) -> None:
        with tempfile.TemporaryDirectory() as folder:
            path = Path(folder) / ".env"
            path.write_text(
                "SCHWAB_APP_KEY=paste-app-key-here\nSCHWAB_APP_SECRET=paste-app-secret-here\n",
                encoding="utf-8",
            )
            with patch.object(schwab_env, "ENV_PATH", path), patch.object(schwab_env, "INSTALL_ROOT", Path(folder)):
                self.assertFalse(schwab_env.credentials_ready())

    def test_gitignore_covers_dotenv(self) -> None:
        ignore = (Path(__file__).resolve().parents[1] / ".gitignore").read_text(encoding="utf-8")
        self.assertIn(".env", ignore.splitlines())

    def test_connect_button_is_in_the_page_header(self) -> None:
        page = (Path(__file__).resolve().parents[1] / "web" / "index.html").read_text(encoding="utf-8")
        # Connect lives in the top desk rail (next to Outlook), not inside <header>.
        rail = page.split('class="desk-rail"', 1)[1].split('id="market-outlook"', 1)[0]
        self.assertIn('id="schwab-connect"', rail)
        self.assertIn('id="schwab-top-dot"', rail)
        self.assertNotIn("Paste keys into Documents\\Paper Trader\\.env", page)
        self.assertNotIn("SCHWAB-KEYS.txt", page)
        self.assertNotIn("Documents\\Paper Trader\\.env", page)
        self.assertIn('id="schwab-market"', page)
        self.assertIn('id="schwab-live"', page)
        # Connect belongs at the top, not only under Trade.
        trade = page.split('id="schwab-box"', 1)[1]
        self.assertNotIn('id="schwab-connect"', trade)

    def test_ui_script_does_not_advertise_secret_paths(self) -> None:
        script = (Path(__file__).resolve().parents[1] / "web" / "app.js").read_text(encoding="utf-8")
        self.assertNotIn("Paste keys into Documents\\Paper Trader\\.env", script)
        self.assertNotIn("Documents\\Paper Trader\\.env", script)
        self.assertNotIn("SCHWAB-KEYS.txt", script)


if __name__ == "__main__":
    unittest.main()
