"""Keep one Paper Trader folder: the newest copy in Documents."""

from __future__ import annotations

import os
import shutil
from pathlib import Path

from paper_trader import __version__
from paper_trader.guard import assert_safe_install, path_looks_foreign

LAUNCHER = "Start-Paper-Trader.bat"
PACKAGE = Path("paper_trader") / "__init__.py"
SKIP_DIRS = {"__pycache__", "tests", ".git"}
SKIP_FILES = {
    ".env",
    "schwab_tokens.json",
    "paper_state.json",
    "learn_state.json",
    "learn_watch.json",
    "breaker.json",
    "paper_book.json",
}


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


def is_staging_extract(folder: Path) -> bool:
    """Launcher unzip staging: Documents\\PaperTrader-setup[-NNNN]\\paper-trader. Not a kept desk."""
    try:
        parts = [part.lower() for part in Path(folder).parts]
    except (OSError, TypeError, ValueError):
        return False
    for part in parts:
        if part == "papertrader-setup" or part.startswith("papertrader-setup-"):
            return True
    return False


def is_recycle_path(folder: Path) -> bool:
    """Recycle Bin / trash — deleted copies must not count as installs."""
    try:
        parts = [part.lower() for part in Path(folder).parts]
    except (OSError, TypeError, ValueError):
        return False
    for part in parts:
        if part in {"$recycle.bin", "recycle.bin", "recycler", "recycle bin", ".trash", "trash"}:
            return True
        if part.startswith("$r"):
            # Windows recycle folder names like $Rxxxx stay under $Recycle.Bin; belt-and-suspenders.
            continue
    return False


def is_install(folder: Path) -> bool:
    """A real desk copy: launcher + package + VERSION. Half-deleted extracts do not count."""
    if path_looks_foreign(folder):
        return False
    if is_recycle_path(folder) or is_staging_extract(folder):
        return False
    name = folder.name.lower()
    if name in {"__macosx", "runtime", "node_modules", "tests", ".git"}:
        return False
    if not (folder / LAUNCHER).is_file():
        return False
    if not (folder / PACKAGE).is_file():
        return False
    # VERSION marks a finished install. Incomplete unzip leftovers inflate the old count.
    if not (folder / "VERSION").is_file():
        return False
    return True


def find_installs(home: Path) -> list[Path]:
    found: list[Path] = []
    seen: set[Path] = set()
    for root in search_roots(home):
        for folder in _nearby_dirs(root):
            if not is_install(folder):
                continue
            try:
                resolved = folder.resolve()
            except OSError:
                continue
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
    dest = canonical_folder(home).resolve()
    assert_safe_install(source, dest)
    if not is_install(source):
        raise ValueError("This folder is not a Paper Trader install")
    from paper_trader.learn import ask_learners_to_close

    ask_learners_to_close(home, source)
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
        from paper_trader.schwab_env import ensure_env_file

        ensure_env_file(dest)
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
        "rem Opens the computer desk on http://127.0.0.1:8765/ — not the mobile HTML copy.\r\n"
        'call "Start-Paper-Trader.bat"\r\n'
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


def _copy_label(folder: Path) -> str:
    version = "—"
    version_file = folder / "VERSION"
    if version_file.is_file():
        try:
            version = version_file.read_text(encoding="utf-8").strip() or "—"
        except OSError:
            version = "—"
    try:
        home = Path.home().resolve()
        shown = str(folder.resolve().relative_to(home))
    except (OSError, ValueError):
        shown = folder.name
    return f"{shown} ({version})"


def single_copy_status(home: Path) -> dict[str, str | bool]:
    """Accurate install count. Never deletes. Guides keep Documents\\Paper Trader only."""
    copies = sorted(find_installs(home), key=_installed_version, reverse=True)
    if not copies:
        return {"name": "Install", "ok": True, "detail": "No extra copies"}
    if len(copies) == 1:
        return {"name": "Install", "ok": True, "detail": "Newest copy only · " + _copy_label(copies[0])}
    newest = copies[0]
    extras = copies[1:]
    shown = ", ".join(_copy_label(path) for path in extras[:4])
    more = f" +{len(extras) - 4} more" if len(extras) > 4 else ""
    detail = (
        f"{len(copies)} copies · keep latest Documents\\Paper Trader · newest {_copy_label(newest)} · also {shown}{more}. "
        "PaperTrader-setup staging and Recycle Bin are ignored. Nothing else is deleted for you."
    )
    return {"name": "Install", "ok": False, "detail": detail}


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
            if path.is_file():
                text = path.read_text(encoding="utf-8", errors="ignore")
                if "2 Open Paper Trader.bat" in text or "Start-Paper-Trader.bat" in text:
                    path.unlink()
                    removed.append(path)
        except OSError:
            continue
    return removed
