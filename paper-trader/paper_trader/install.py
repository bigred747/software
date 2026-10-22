"""Keep one Paper Trader folder: the newest copy in Documents."""

from __future__ import annotations

import os
import shutil
import subprocess
from pathlib import Path

from paper_trader import __version__

LAUNCHER = "Start-Paper-Trader.bat"
PACKAGE = Path("paper_trader") / "__init__.py"
SKIP_DIRS = {"__pycache__", "tests", ".git"}
SKIP_FILES = {"paper_state.json"}


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


def running_desk_pids(images: list[tuple[int, str]], roots: list[Path]) -> list[int]:
    folders = [str(root).replace("/", "\\").rstrip("\\").lower() + "\\" for root in roots]
    pids: list[int] = []
    for pid, image in images:
        if not image:
            continue
        low = image.replace("/", "\\").lower()
        if any(low.startswith(folder) for folder in folders):
            pids.append(pid)
    return pids


def stop_open_desks(home: Path, images: list[tuple[int, str]] | None = None, killer=None) -> list[int]:
    if images is None:
        _close_titled_window()
        return []
    pids = running_desk_pids(images, desk_folders(home))
    end = killer or _taskkill
    for pid in pids:
        end(pid)
    return pids


def _taskkill(pid: int) -> None:
    subprocess.run(["taskkill", "/F", "/PID", str(pid)], check=False, capture_output=True)


def _close_titled_window() -> None:
    if os.name != "nt":
        return
    try:
        subprocess.run(
            ["taskkill", "/F", "/FI", "WINDOWTITLE eq Paper Trader"],
            check=False,
            capture_output=True,
        )
    except OSError:
        return


def install_newest(source: Path, home: Path) -> tuple[Path, str]:
    source = source.resolve()
    if not is_install(source):
        raise ValueError("This folder is not a Paper Trader install")
    stop_open_desks(home)
    dest = canonical_folder(home).resolve()
    dest_version = _installed_version(dest)
    source_version = version_key(__version__)
    try:
        if source != dest and dest_version > source_version and is_install(dest):
            _remove_older(home, dest, source)
            return dest, "remove-source"
        if source != dest:
            _replace_install(source, dest)
        dest.mkdir(parents=True, exist_ok=True)
        (dest / "VERSION").write_text(__version__ + "\n", encoding="utf-8")
        _remove_older(home, dest, source)
    except OSError as exc:
        raise ValueError("Could not replace the older copy. Opening the new copy instead.") from exc
    if source == dest:
        return dest, "keep-source"
    return dest, "remove-source"


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
    state_path = dest / "paper_state.json"
    saved_state = state_path.read_bytes() if state_path.is_file() else None
    if dest.exists():
        for child in list(dest.iterdir()):
            if child.name == "paper_state.json":
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
    if saved_state is not None:
        state_path.write_bytes(saved_state)


def _remove_older(home: Path, keep: Path, source: Path) -> None:
    spared = {keep.resolve(), source.resolve()}
    for folder in find_installs(home):
        if folder in spared:
            continue
        if _safe_to_remove(folder, home, keep):
            try:
                shutil.rmtree(folder)
            except OSError:
                continue


def _safe_to_remove(folder: Path, home: Path, keep: Path) -> bool:
    resolved = folder.resolve()
    if resolved == keep.resolve() or not is_install(resolved):
        return False
    for root in search_roots(home):
        if not root.exists():
            continue
        root_resolved = root.resolve()
        if resolved == root_resolved:
            return False
        try:
            resolved.relative_to(root_resolved)
        except ValueError:
            continue
        return True
    return False
