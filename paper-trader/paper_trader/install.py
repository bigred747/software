"""Keep one Paper Trader folder: the newest copy in Documents."""

from __future__ import annotations

import os
import shutil
from pathlib import Path

from paper_trader import __version__

LAUNCHER = "Start-Paper-Trader.bat"
PACKAGE = Path("paper_trader") / "__init__.py"
SKIP_DIRS = {"__pycache__", "tests", ".git"}
SKIP_FILES = {"paper_state.json", "learn_state.json", "learn_watch.json"}


def version_key(text: str) -> tuple[int, ...]:
    parts: list[int] = []
    for piece in text.strip().split("."):
        digits = ""
        for char in piece:
            if char.isdigit():
                digits += char
            else:
                break
        parts.append(int(digits or "0"))
    return tuple(parts or (0,))


def canonical_folder(home: Path) -> Path:
    return home / "Documents" / "Paper Trader"


def search_roots(home: Path) -> list[Path]:
    return [home / "Documents", home / "Desktop", home / "Downloads"]


def is_install(folder: Path) -> bool:
    return (folder / LAUNCHER).is_file() and (folder / PACKAGE).is_file()


def find_installs(home: Path) -> list[Path]:
    found: list[Path] = []
    seen: set[Path] = set()
    for root in search_roots(home):
        for folder in _nearby_dirs(root):
            if not is_install(folder):
                continue
            resolved = folder.resolve()
            if resolved in seen:
                continue
            seen.add(resolved)
            found.append(resolved)
    return found


def desk_folders(home: Path) -> list[Path]:
    documents = home / "Documents"
    return [
        documents / "paper-trader",
        documents / "Paper Trader",
        documents / "PaperTrader-setup" / "paper-trader",
    ]


def install_newest(source: Path, home: Path) -> tuple[Path, str]:
    source = source.resolve()
    if not is_install(source):
        raise ValueError("This folder is not a Paper Trader install")
    from paper_trader.learn import ask_learners_to_close

    ask_learners_to_close(home, source)
    dest = canonical_folder(home).resolve()
    dest_version = _installed_version(dest)
    source_version = version_key(__version__)
    try:
        if source != dest and dest_version > source_version and is_install(dest):
            _resume_learner(dest)
            return dest, "remove-source"
        if source != dest:
            _replace_install(source, dest)
        dest.mkdir(parents=True, exist_ok=True)
        (dest / "VERSION").write_text(__version__ + "\n", encoding="utf-8")
    except OSError as exc:
        raise ValueError("Could not replace the older copy. Opening the new copy instead.") from exc
    _resume_learner(dest)
    if source == dest:
        return dest, "keep-source"
    return dest, "remove-source"


def _resume_learner(dest: Path) -> None:
    """Windows sign-in starts the score again. Other systems leave that to the desk window."""
    if os.name != "nt":
        return
    from paper_trader.learn import ensure_learner

    try:
        ensure_learner(dest, dest / "learn_state.json")
    except OSError:
        return


def desktop_folders(home: Path) -> list[Path]:
    names = [home / "Desktop", home / "OneDrive" / "Desktop"]
    one_drive = os.environ.get("OneDrive")
    if one_drive:
        names.append(Path(one_drive) / "Desktop")
    found: list[Path] = []
    seen: set[Path] = set()
    for folder in names:
        if not folder.is_dir():
            continue
        resolved = folder.resolve()
        if resolved in seen:
            continue
        seen.add(resolved)
        found.append(folder)
    return found


def write_desktop_opener(install: Path, home: Path) -> list[Path]:
    """A visible desktop file that opens this copy. No script host and no PowerShell."""
    opener = install / "2 Open Paper Trader.bat"
    if not opener.is_file():
        return []
    script = (
        "@echo off\r\n"
        f'cd /d "{install}"\r\n'
        "title Paper Trader\r\n"
        'call "2 Open Paper Trader.bat"\r\n'
    )
    written: list[Path] = []
    for folder in desktop_folders(home):
        path = folder / OPENER_NAME
        try:
            path.write_text(script, encoding="utf-8")
        except OSError:
            continue
        written.append(path)
    return written


def other_copies(home: Path, running: Path) -> list[Path]:
    """Other Paper Trader folders on this computer. This only lists them and never deletes anything."""
    running = running.resolve()
    found = [folder for folder in find_installs(home) if folder != running]
    return found


def single_copy_status(home: Path) -> dict[str, str | bool]:
    copies = find_installs(home)
    if len(copies) <= 1:
        detail = "Newest copy only" if copies else "No extra copies"
        return {"name": "Install", "ok": True, "detail": detail}
    return {"name": "Install", "ok": False, "detail": f"{len(copies)} copies found"}


def _installed_version(folder: Path) -> tuple[int, ...]:
    version_file = folder / "VERSION"
    if not version_file.is_file():
        return (0,)
    return version_key(version_file.read_text(encoding="utf-8"))


def _nearby_dirs(root: Path) -> list[Path]:
    if not root.is_dir():
        return []
    found = [root]
    children = _child_dirs(root)
    found.extend(children)
    for child in children:
        found.extend(_child_dirs(child))
    return found


def _child_dirs(folder: Path) -> list[Path]:
    try:
        return [path for path in folder.iterdir() if path.is_dir() and not path.name.startswith(".")]
    except OSError:
        return []


def _replace_install(source: Path, dest: Path) -> None:
    saved = {}
    if dest.exists():
        for name in SKIP_FILES:
            path = dest / name
            if path.is_file():
                saved[name] = path.read_bytes()
        for child in list(dest.iterdir()):
            if child.name in SKIP_FILES:
                continue
            if child.is_dir():
                shutil.rmtree(child)
            else:
                child.unlink()
    else:
        dest.mkdir(parents=True)
    for path in source.rglob("*"):
        if not path.is_file():
            continue
        if any(part in SKIP_DIRS for part in path.parts) or path.suffix == ".pyc":
            continue
        if path.name in SKIP_FILES:
            continue
        target = dest / path.relative_to(source)
        target.parent.mkdir(parents=True, exist_ok=True)
        shutil.copy2(path, target)
    for name, payload in saved.items():
        (dest / name).write_bytes(payload)


OPENER_NAME = "Paper Trader.bat"


def desktop_opener_present(home: Path) -> bool:
    return any((folder / OPENER_NAME).is_file() for folder in desktop_folders(home))


def remove_desktop_opener(home: Path) -> list[Path]:
    """Remove only the opener this program wrote. Any other file with that name stays."""
    removed: list[Path] = []
    for folder in desktop_folders(home):
        path = folder / OPENER_NAME
        try:
            if path.is_file() and "2 Open Paper Trader.bat" in path.read_text(encoding="utf-8", errors="ignore"):
                path.unlink()
                removed.append(path)
        except OSError:
            continue
    return removed
