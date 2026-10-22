"""The newest Paper Trader copy replaces older ones in the home folders."""

from __future__ import annotations

import sys
import tempfile
import unittest
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))

from paper_trader.install import canonical_folder, find_installs, install_newest, is_install, running_desk_pids, stop_open_desks


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


if __name__ == "__main__":
    unittest.main()
