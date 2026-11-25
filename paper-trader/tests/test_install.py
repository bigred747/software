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
    erase_other_installs,
    find_installs,
    install_newest,
    is_install,
    other_copies,
    read_install_version,
    remove_desktop_opener,
    single_copy_status,
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

    def test_incomplete_extracts_without_version_do_not_inflate_copy_count(self) -> None:
        with tempfile.TemporaryDirectory() as folder:
            home = Path(folder)
            dest = canonical_folder(home)
            junk = home / "Downloads" / "paper-trader"
            make_install(dest, "1.10.26")
            junk.mkdir(parents=True)
            (junk / "Start-Paper-Trader.bat").write_text("@echo off\r\n", encoding="utf-8")
            package = junk / "paper_trader"
            package.mkdir()
            (package / "__init__.py").write_text('__version__ = "1.0.0"\n', encoding="utf-8")
            # No VERSION file — leftover unzip debris.
            self.assertFalse(is_install(junk))
            status = single_copy_status(home)
            self.assertTrue(status["ok"])
            self.assertIn("One copy", str(status["detail"]))
            self.assertEqual(find_installs(home), [dest.resolve()])

    def test_installing_never_ends_programs_or_deletes_other_folders(self) -> None:
        with tempfile.TemporaryDirectory() as folder:
            home = Path(folder)
            source = home / "Downloads" / "paper-trader"
            old = home / "Documents" / "paper-trader"
            make_install(source, "1.10.4")
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

    def test_artex_folders_cannot_install_or_count_as_paper_trader(self) -> None:
        with tempfile.TemporaryDirectory() as folder:
            home = Path(folder)
            foreign = home / "Documents" / "Artex" / "paper-trader"
            make_install(foreign, "9.9.9")
            (home / "Documents").mkdir(exist_ok=True)
            self.assertFalse(is_install(foreign))
            with self.assertRaises(ValueError) as blocked:
                install_newest(foreign, home)
            self.assertIn("artex", str(blocked.exception).lower())

    def test_erase_others_keeps_current_seal_and_deletes_the_rest(self) -> None:
        from paper_trader.install import KEEP_VERSION

        with tempfile.TemporaryDirectory() as folder:
            home = Path(folder)
            keep = canonical_folder(home)
            old = home / "Downloads" / "paper-trader"
            newer = home / "Desktop" / "Paper Trader 1.10.50"
            notes = home / "Documents" / "taxes"
            make_install(keep, KEEP_VERSION)
            (keep / "2 Open Paper Trader.bat").write_text("@echo off\r\n", encoding="utf-8")
            make_install(old, "1.10.39")
            make_install(newer, "1.10.50")
            notes.mkdir(parents=True)
            (notes / "keep.txt").write_text("leave\n", encoding="utf-8")
            (home / "Desktop").mkdir(exist_ok=True)
            (home / "Downloads" / "Paper-Trader-1.10.50-slim.zip").write_bytes(b"zip")
            (home / "Downloads" / f"Paper-Trader-{KEEP_VERSION}-slim.zip").write_bytes(b"keep")
            (home / "Documents" / "PaperTrader-setup").mkdir()
            make_install(home / "Documents" / "PaperTrader-setup" / "paper-trader", "1.10.48")
            closed: list[int] = []

            def fake_killer(pid: int) -> None:
                closed.append(pid)

            with patch("paper_trader.learn.stop_learner", side_effect=lambda state, killer=None: 4242):
                with patch("paper_trader.install._resume_learner"):
                    report = erase_other_installs(home, keep=keep, killer=fake_killer)
            self.assertEqual(Path(str(report["keep"])), keep.resolve())
            self.assertEqual(report["keep_version"], KEEP_VERSION)
            self.assertFalse(old.exists())
            self.assertFalse(newer.exists())
            self.assertFalse((home / "Documents" / "PaperTrader-setup").exists())
            self.assertTrue(is_install(keep))
            self.assertEqual((notes / "keep.txt").read_text(encoding="utf-8"), "leave\n")
            self.assertFalse((home / "Downloads" / "Paper-Trader-1.10.50-slim.zip").exists())
            self.assertTrue((home / "Downloads" / f"Paper-Trader-{KEEP_VERSION}-slim.zip").is_file())
            self.assertEqual(find_installs(home), [keep.resolve()])
            opener = home / "Desktop" / "Paper Trader.bat"
            self.assertTrue(opener.is_file())
            self.assertIn(str(keep), opener.read_text(encoding="utf-8"))

    def test_force_upgrade_replaces_old_documents_desk(self) -> None:
        from paper_trader.install import KEEP_VERSION, force_upgrade

        with tempfile.TemporaryDirectory() as folder:
            home = Path(folder)
            pack = home / "extracted" / "paper-trader"
            make_install(pack, KEEP_VERSION)
            (pack / "web").mkdir(parents=True, exist_ok=True)
            (pack / "web" / "app.js").write_text(f'const VERSION = "{KEEP_VERSION}";\n', encoding="utf-8")
            (pack / "web" / "index.html").write_text(
                f'<meta name="paper-build" content="{KEEP_VERSION}">\n', encoding="utf-8"
            )
            dest = canonical_folder(home)
            make_install(dest, "1.10.40")
            (dest / "web").mkdir(parents=True, exist_ok=True)
            (dest / "web" / "app.js").write_text('const VERSION = "1.10.40";\n', encoding="utf-8")
            (dest / "web" / "index.html").write_text(
                '<meta name="paper-build" content="1.10.40">\n', encoding="utf-8"
            )
            with patch("paper_trader.install._resume_learner"), patch(
                "paper_trader.install.ask_learners_to_close", create=True
            ), patch("paper_trader.learn.ask_learners_to_close"), patch(
                "paper_trader.learn.end_learner_pid"
            ), patch("paper_trader.schwab_env.ensure_env_file"):
                out = force_upgrade(pack, home)
            self.assertEqual(out, dest.resolve())
            self.assertEqual(read_install_version(dest), KEEP_VERSION)
            self.assertIn(f'const VERSION = "{KEEP_VERSION}"', (dest / "web" / "app.js").read_text(encoding="utf-8"))


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
