"""Fibs Lab stays side-by-side and never hijacks the working desk."""

from __future__ import annotations

import tempfile
import unittest
from pathlib import Path

from paper_trader.install import (
    LAB_OPENER_NAME,
    OPENER_NAME,
    is_fibs_lab,
    lab_folder,
    write_desktop_opener,
)


class FibsLabTests(unittest.TestCase):
    def _seed(self, folder: Path, *, lab: bool) -> None:
        folder.mkdir(parents=True, exist_ok=True)
        (folder / "Start-Paper-Trader.bat").write_text("@echo off\r\n", encoding="utf-8")
        (folder / "Start-Fibs-Lab.bat").write_text("@echo off\r\n", encoding="utf-8")
        (folder / "launch.py").write_text("print('ok')\n", encoding="utf-8")
        if lab:
            (folder / "FIBS-LAB.txt").write_text("lab\n", encoding="utf-8")

    def test_lab_marker_and_folder(self) -> None:
        with tempfile.TemporaryDirectory() as raw:
            home = Path(raw)
            lab = home / "pack"
            self._seed(lab, lab=True)
            self.assertTrue(is_fibs_lab(lab))
            self.assertEqual(lab_folder(home), home / "Documents" / "PaperTrader-Fibs-Lab" / "paper-trader")

    def test_lab_desktop_shortcut_does_not_overwrite_main(self) -> None:
        with tempfile.TemporaryDirectory() as raw:
            home = Path(raw)
            desk = home / "Desktop"
            desk.mkdir(parents=True)
            main_link = desk / OPENER_NAME
            main_link.write_text(
                "@echo off\r\ncd /d C:\\Users\\rick\\Documents\\PaperTrader-setup-GOOD\\paper-trader\r\n"
                "call Start-Paper-Trader.bat\r\n",
                encoding="utf-8",
            )
            lab = home / "lab"
            self._seed(lab, lab=True)
            written = write_desktop_opener(lab, home)
            names = {path.name for path in written}
            self.assertIn(LAB_OPENER_NAME, names)
            self.assertNotIn(OPENER_NAME, names)
            # Working shortcut text unchanged.
            self.assertIn("PaperTrader-setup-GOOD", main_link.read_text(encoding="utf-8"))
            self.assertNotIn("lab", main_link.read_text(encoding="utf-8").lower())

    def test_main_desk_still_writes_normal_shortcut(self) -> None:
        with tempfile.TemporaryDirectory() as raw:
            home = Path(raw)
            (home / "Desktop").mkdir(parents=True)
            pack = home / "main"
            self._seed(pack, lab=False)
            written = write_desktop_opener(pack, home)
            names = {path.name for path in written}
            self.assertIn(OPENER_NAME, names)
            self.assertNotIn(LAB_OPENER_NAME, names)


if __name__ == "__main__":
    unittest.main()
