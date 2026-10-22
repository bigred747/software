"""The newest Paper Trader copy replaces older ones in the home folders."""

from __future__ import annotations

import sys
import tempfile
import unittest
from pathlib import Path
from unittest.mock import patch

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))

from paper_trader.install import (
    canonical_folder,
    find_installs,
    install_newest,
    is_install,
    keep_only_running,
    running_desk_pids,
    stop_open_desks,
    write_desktop_opener,
)


def make_install(folder: Path, version: str, marker: str = "") -> None:
    folder.mkdir(parents=True, exist_ok=True)
    (folder / "Start-Paper-Trader.bat").write_text("@echo off\r\n", encoding="utf-8")
    package = folder / "paper_trader"
    package.mkdir()
    (package / "__init__.py").write_text(f'__version__ = "{version}"\n', encoding="utf-8")
    (folder / "VERSION").write_text(version + "\n", encoding="utf-8")
    if marker:
        (folder / marker).write_text("keep\n", encoding="utf-8")


class InstallTests(unittest.TestCase):
    def test_newest_copy_replaces_older_copies_and_leaves_other_files(self) -> None:
        with tempfile.TemporaryDirectory() as folder:
            home = Path(folder)
            old = home / "Downloads" / "paper-trader"
            source = home / "Desktop" / "Paper-Trader" / "paper-trader"
            notes = home / "Downloads" / "notes"
            make_install(old, "1.0.0")
            make_install(source, "1.4.0")
            notes.mkdir(parents=True)
            (notes / "keep.txt").write_text("leave this\n", encoding="utf-8")
            (home / "Documents").mkdir()
            dest, action = install_newest(source, home)
            self.assertEqual(dest, canonical_folder(home).resolve())
            self.assertEqual(action, "remove-source")
            self.assertTrue(is_install(dest))
            self.assertFalse(old.exists())
            self.assertTrue(source.exists())
            self.assertEqual((notes / "keep.txt").read_text(encoding="utf-8"), "leave this\n")
            self.assertEqual(set(find_installs(home)), {dest, source.resolve()})

    def test_older_launch_does_not_replace_the_newest_copy(self) -> None:
        with tempfile.TemporaryDirectory() as folder:
            home = Path(folder)
            source = home / "Downloads" / "paper-trader"
            dest = canonical_folder(home)
            make_install(source, "1.0.0")
            make_install(dest, "9.0.0", marker="KEEP")
            installed, action = install_newest(source, home)
            self.assertEqual(installed, dest.resolve())
            self.assertEqual(action, "remove-source")
            self.assertTrue((dest / "KEEP").is_file())
            self.assertEqual((dest / "VERSION").read_text(encoding="utf-8").strip(), "9.0.0")

    def test_documents_folder_itself_is_not_removed(self) -> None:
        with tempfile.TemporaryDirectory() as folder:
            home = Path(folder)
            documents = home / "Documents"
            make_install(documents, "1.0.0")
            source = home / "Downloads" / "paper-trader"
            make_install(source, "1.4.0")
            dest, _action = install_newest(source, home)
            self.assertTrue(documents.is_dir())
            self.assertTrue((documents / "Start-Paper-Trader.bat").is_file())
            self.assertTrue(is_install(dest))

    def test_only_the_open_paper_trader_is_closed(self) -> None:
        home = Path(r"C:\Users\rick")
        images = [
            (10, r"C:\Users\rick\Documents\paper-trader\runtime\python.exe"),
            (11, r"C:\Users\rick\AppData\Local\Programs\Python\Python314\python.exe"),
            (12, r"C:\Users\rick\Documents\Paper Trader\runtime\python.exe"),
        ]
        killed: list[int] = []
        stopped = stop_open_desks(home, images, killed.append)
        self.assertEqual(stopped, [10, 12])
        self.assertEqual(killed, [10, 12])
        self.assertEqual(running_desk_pids([(4, r"C:\Other\python.exe")], [home / "Documents" / "paper-trader"]), [])

    def test_install_does_not_walk_every_python_process(self) -> None:
        with tempfile.TemporaryDirectory() as folder:
            self.assertEqual(stop_open_desks(Path(folder)), [])

    def test_the_open_newest_copy_removes_the_other_one(self) -> None:
        with tempfile.TemporaryDirectory() as folder:
            home = Path(folder)
            setup = home / "Documents" / "PaperTrader-setup" / "paper-trader"
            dest = canonical_folder(home)
            notes = home / "Documents" / "taxes"
            make_install(setup, "1.9.17")
            make_install(dest, "1.9.18")
            notes.mkdir()
            (notes / "keep.txt").write_text("leave\n", encoding="utf-8")
            removed = keep_only_running(home, dest)
            self.assertEqual(removed, [setup.resolve()])
            self.assertFalse((home / "Documents" / "PaperTrader-setup").exists())
            self.assertTrue(is_install(dest))
            self.assertEqual((notes / "keep.txt").read_text(encoding="utf-8"), "leave\n")
            self.assertEqual(find_installs(home), [dest.resolve()])

    def test_an_older_open_copy_leaves_the_newer_one_in_place(self) -> None:
        with tempfile.TemporaryDirectory() as folder:
            home = Path(folder)
            setup = home / "Documents" / "PaperTrader-setup" / "paper-trader"
            dest = canonical_folder(home)
            make_install(setup, "1.9.17")
            make_install(dest, "1.9.18")
            self.assertEqual(keep_only_running(home, setup), [])
            self.assertTrue(is_install(dest))
            self.assertTrue(is_install(setup))

    def test_desktop_opener_is_a_plain_file_on_each_desktop(self) -> None:
        with tempfile.TemporaryDirectory() as folder:
            home = Path(folder)
            dest = canonical_folder(home)
            make_install(dest, "1.9.18")
            (dest / "2 Open Paper Trader.bat").write_text("@echo off\r\n", encoding="utf-8")
            (home / "Desktop").mkdir()
            (home / "OneDrive" / "Desktop").mkdir(parents=True)
            written = write_desktop_opener(dest, home)
            self.assertEqual(len(written), 2)
            text = (home / "Desktop" / "Paper Trader.bat").read_text(encoding="utf-8").lower()
            self.assertIn("2 open paper trader.bat", text)
            self.assertIn(str(dest).lower(), text)
            self.assertNotIn("powershell", text)
            self.assertNotIn("wscript", text)

    def test_a_locked_folder_reports_a_clean_failure(self) -> None:
        with tempfile.TemporaryDirectory() as folder:
            home = Path(folder)
            source = home / "Desktop" / "paper-trader"
            make_install(source, "1.9.6")
            (home / "Documents").mkdir()
            with patch("paper_trader.install._replace_install", side_effect=PermissionError("locked")):
                with self.assertRaises(ValueError) as caught:
                    install_newest(source, home)
            self.assertIn("Opening the new copy instead.", str(caught.exception))


if __name__ == "__main__":
    unittest.main()
