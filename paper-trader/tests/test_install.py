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
    other_copies,
    remove_desktop_opener,
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
    def test_newest_copy_goes_to_documents_and_other_folders_stay(self) -> None:
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
            self.assertTrue(is_install(old))
            self.assertTrue(source.exists())
            self.assertEqual((notes / "keep.txt").read_text(encoding="utf-8"), "leave this\n")
            self.assertEqual(set(find_installs(home)), {dest, source.resolve(), old.resolve()})

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

    def test_other_copies_are_listed_and_never_deleted(self) -> None:
        with tempfile.TemporaryDirectory() as folder:
            home = Path(folder)
            setup = home / "Documents" / "PaperTrader-setup" / "paper-trader"
            dest = canonical_folder(home)
            notes = home / "Documents" / "taxes"
            make_install(setup, "1.9.17")
            make_install(dest, "1.9.18")
            notes.mkdir()
            (notes / "keep.txt").write_text("leave\n", encoding="utf-8")
            self.assertEqual(other_copies(home, dest), [setup.resolve()])
            self.assertEqual(other_copies(home, setup), [dest.resolve()])
            self.assertTrue(is_install(setup))
            self.assertTrue(is_install(dest))
            self.assertEqual((notes / "keep.txt").read_text(encoding="utf-8"), "leave\n")

    def test_installing_never_ends_programs_or_deletes_other_folders(self) -> None:
        with tempfile.TemporaryDirectory() as folder:
            home = Path(folder)
            source = home / "Downloads" / "paper-trader"
            old = home / "Documents" / "paper-trader"
            make_install(source, "1.9.99")
            make_install(old, "1.0.0")
            with patch("subprocess.Popen") as popen, patch("subprocess.run") as run, patch("os.kill") as kill:
                install_newest(source, home)
            popen.assert_not_called()
            run.assert_not_called()
            kill.assert_not_called()
            self.assertTrue(is_install(source))
            self.assertTrue(is_install(old))

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

    def test_desktop_opener_can_be_removed_and_only_ours(self) -> None:
        with tempfile.TemporaryDirectory() as folder:
            home = Path(folder)
            dest = canonical_folder(home)
            make_install(dest, "1.9.18")
            (dest / "2 Open Paper Trader.bat").write_text("@echo off\r\n", encoding="utf-8")
            (home / "Desktop").mkdir()
            write_desktop_opener(dest, home)
            self.assertEqual(len(remove_desktop_opener(home)), 1)
            self.assertFalse((home / "Desktop" / "Paper Trader.bat").exists())
            (home / "Desktop" / "Paper Trader.bat").write_text("someone else's file\n", encoding="utf-8")
            self.assertEqual(remove_desktop_opener(home), [])
            self.assertTrue((home / "Desktop" / "Paper Trader.bat").exists())
