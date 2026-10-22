"""Keep one Paper Trader folder: the newest copy in Documents."""

from __future__ import annotations

import os
import shutil
import subprocess
import time
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
    own_lookup = images is None
    if images is None:
        if os.name != "nt":
            return []
        images = _windows_python_images()
    pids = running_desk_pids(images, desk_folders(home))
    end = killer or _taskkill
    for pid in pids:
        end(pid)
    if own_lookup and pids:
        time.sleep(0.4)
    return pids


def _taskkill(pid: int) -> None:
    subprocess.run(["taskkill", "/F", "/PID", str(pid)], check=False, capture_output=True)


def _windows_python_images() -> list[tuple[int, str]]:
    import ctypes
    from ctypes import wintypes

    kernel = ctypes.WinDLL("kernel32", use_last_error=True)
    snapshot_flags = 0x00000002
    query_flag = 0x1000

    class PROCESSENTRY32W(ctypes.Structure):
        _fields_ = [
            ("dwSize", wintypes.DWORD),
            ("cntUsage", wintypes.DWORD),
            ("th32ProcessID", wintypes.DWORD),
            ("th32DefaultHeapID", ctypes.c_size_t),
            ("th32ModuleID", wintypes.DWORD),
            ("cntThreads", wintypes.DWORD),
            ("th32ParentProcessID", wintypes.DWORD),
            ("pcPriClassBase", wintypes.LONG),
            ("dwFlags", wintypes.DWORD),
            ("szExeFile", wintypes.WCHAR * 260),
        ]

    kernel.CreateToolhelp32Snapshot.argtypes = [wintypes.DWORD, wintypes.DWORD]
    kernel.CreateToolhelp32Snapshot.restype = wintypes.HANDLE
    kernel.Process32FirstW.argtypes = [wintypes.HANDLE, ctypes.POINTER(PROCESSENTRY32W)]
    kernel.Process32NextW.argtypes = [wintypes.HANDLE, ctypes.POINTER(PROCESSENTRY32W)]
    kernel.OpenProcess.argtypes = [wintypes.DWORD, wintypes.BOOL, wintypes.DWORD]
    kernel.OpenProcess.restype = wintypes.HANDLE
    kernel.QueryFullProcessImageNameW.argtypes = [
        wintypes.HANDLE,
        wintypes.DWORD,
        wintypes.LPWSTR,
        ctypes.POINTER(wintypes.DWORD),
    ]
    kernel.QueryFullProcessImageNameW.restype = wintypes.BOOL
    kernel.CloseHandle.argtypes = [wintypes.HANDLE]
    snapshot = kernel.CreateToolhelp32Snapshot(snapshot_flags, 0)
    if not snapshot or snapshot == wintypes.HANDLE(-1).value:
        return []
    found: list[tuple[int, str]] = []
    try:
        entry = PROCESSENTRY32W()
        entry.dwSize = ctypes.sizeof(PROCESSENTRY32W)
        ok = kernel.Process32FirstW(snapshot, ctypes.byref(entry))
        while ok:
            name = entry.szExeFile.lower()
            if name in {"python.exe", "pythonw.exe"}:
                handle = kernel.OpenProcess(query_flag, False, int(entry.th32ProcessID))
                if handle:
                    try:
                        size = wintypes.DWORD(32768)
                        buf = ctypes.create_unicode_buffer(size.value)
                        if kernel.QueryFullProcessImageNameW(handle, 0, buf, ctypes.byref(size)):
                            found.append((int(entry.th32ProcessID), buf.value))
                    finally:
                        kernel.CloseHandle(handle)
            ok = kernel.Process32NextW(snapshot, ctypes.byref(entry))
    finally:
        kernel.CloseHandle(snapshot)
    return found


def install_newest(source: Path, home: Path) -> tuple[Path, str]:
    source = source.resolve()
    if not is_install(source):
        raise ValueError("This folder is not a Paper Trader install")
    stop_open_desks(home)
    dest = canonical_folder(home).resolve()
    dest_version = _installed_version(dest)
    source_version = version_key(__version__)
    if source != dest and dest_version > source_version and is_install(dest):
        _remove_older(home, dest, source)
        return dest, "remove-source"
    if source != dest:
        _replace_install(source, dest)
    dest.mkdir(parents=True, exist_ok=True)
    (dest / "VERSION").write_text(__version__ + "\n", encoding="utf-8")
    _remove_older(home, dest, source)
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
