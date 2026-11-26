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
    """Places we look for Paper Trader copies (and leftover Bot scores)."""
    roots = [
        home / "Documents",
        home / "Desktop",
        home / "Downloads",
        home / "OneDrive" / "Documents",
        home / "OneDrive" / "Desktop",
        home / "OneDrive" / "Downloads",
    ]
    one_drive = os.environ.get("OneDrive")
    if one_drive:
        roots.extend(
            [
                Path(one_drive) / "Documents",
                Path(one_drive) / "Desktop",
                Path(one_drive) / "Downloads",
            ]
        )
    found: list[Path] = []
    seen: set[Path] = set()
    for folder in roots:
        try:
            if not folder.is_dir():
                continue
            resolved = folder.resolve()
        except OSError:
            continue
        if resolved in seen:
            continue
        seen.add(resolved)
        found.append(folder)
    return found


def is_install(folder: Path) -> bool:
    """A real desk copy: launcher + package + VERSION. Half-deleted extracts do not count."""
    if path_looks_foreign(folder):
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


def install_newest(source: Path, home: Path, *, force: bool = False) -> tuple[Path, str]:
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
        # Force upgrade always replaces Documents\Paper Trader from this pack.
        if not force and source != dest and dest_version > source_version and is_install(dest):
            _resume_learner(dest)
            return dest, "remove-source"
        if source == dest and not force:
            # Running Allow from the already-installed folder cannot pull newer files.
            dest.mkdir(parents=True, exist_ok=True)
            (dest / "VERSION").write_text(__version__ + "\n", encoding="utf-8")
            from paper_trader.schwab_env import ensure_env_file

            ensure_env_file(dest)
            _resume_learner(dest)
            return dest, "keep-source"
        if source != dest:
            _replace_install(source, dest)
        elif force:
            # Same path + force: still refresh VERSION / env markers.
            pass
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


def force_upgrade(source: Path, home: Path) -> Path:
    """Replace Documents\\Paper Trader by moving the old folder aside, then copying this pack in."""
    import time

    source = source.resolve()
    dest = canonical_folder(home).resolve()
    if not is_install(source):
        raise ValueError("This folder is not a Paper Trader install")
    if source == dest:
        raise ValueError(
            "Run FORCE-UPGRADE.bat from the NEW extracted zip folder, not from Documents\\Paper Trader. "
            "Right-click Paper-Trader.zip → Extract All → open that folder → FORCE-UPGRADE.bat"
        )
    # Source pack itself must already be the new seal.
    src_ver = read_install_version(source) or ""
    if src_ver and src_ver != __version__:
        raise ValueError(
            f"This extracted folder is seal {src_ver}, but the package code is {__version__}. "
            "Extract a fresh Paper-Trader.zip and run FORCE-UPGRADE.bat from that new folder."
        )
    app_src = (source / "web" / "app.js").read_text(encoding="utf-8", errors="ignore")
    if f'const VERSION = "{__version__}"' not in app_src:
        raise ValueError(
            f"This extracted folder's web/app.js is not {__version__}. "
            "You opened an old extract. Delete old Paper-Trader (1).zip extracts, download again, Extract All, retry."
        )
    assert_safe_install(source, dest)
    from paper_trader.learn import ask_learners_to_close, end_learner_pid, stop_learner

    # Politely ask, then force-end Bot score processes so Documents can be renamed.
    ask_learners_to_close(home, source, killer=end_learner_pid)
    if dest.exists():
        try:
            stop_learner(dest / "learn_state.json", killer=end_learner_pid)
        except OSError:
            pass

    saved: dict[str, bytes] = {}
    old_seal = read_install_version(dest) if dest.exists() else ""
    if dest.exists():
        for name in SKIP_FILES:
            path = dest / name
            if path.is_file():
                try:
                    saved[name] = path.read_bytes()
                except OSError:
                    continue
        aside = dest.parent / f"Paper Trader-OLD-{old_seal or 'unknown'}-{int(time.time())}"
        renamed = False
        last_exc: OSError | None = None
        for _attempt in range(6):
            try:
                dest.rename(aside)
                renamed = True
                break
            except OSError as exc:
                last_exc = exc
                time.sleep(0.75)
                try:
                    stop_learner(dest / "learn_state.json", killer=end_learner_pid)
                except OSError:
                    pass
        if not renamed:
            # Files locked by a running desk — fall back to in-place replace.
            try:
                _replace_install(source, dest)
            except OSError as inner:
                raise ValueError(
                    "Could not move or replace Documents\\Paper Trader. "
                    "Close EVERY Paper Trader window, Edge desk tab, and black cmd window, then run FORCE-UPGRADE again. "
                    f"({last_exc}; {inner})"
                ) from inner
        else:
            try:
                shutil.copytree(
                    source,
                    dest,
                    ignore=shutil.ignore_patterns(*SKIP_DIRS, "*.pyc", *SKIP_FILES),
                )
            except OSError as exc:
                # Roll the old desk back if the fresh copy failed.
                try:
                    if dest.exists():
                        shutil.rmtree(dest)
                    aside.rename(dest)
                except OSError:
                    pass
                raise ValueError(
                    "Could not copy the new pack into Documents\\Paper Trader. "
                    f"Close Paper Trader windows and retry. ({exc})"
                ) from exc
    else:
        shutil.copytree(
            source,
            dest,
            ignore=shutil.ignore_patterns(*SKIP_DIRS, "*.pyc", *SKIP_FILES),
        )

    dest.mkdir(parents=True, exist_ok=True)
    for name, payload in saved.items():
        try:
            (dest / name).write_bytes(payload)
        except OSError:
            continue
    (dest / "VERSION").write_text(__version__ + "\n", encoding="utf-8")
    (dest / "SEAL.txt").write_text(
        f"Paper Trader {__version__}\nInstalled to: {dest}\nIf the desk seal still shows an older number, you opened an old window.\n",
        encoding="utf-8",
    )
    from paper_trader.schwab_env import ensure_env_file

    ensure_env_file(dest)
    app_js = (dest / "web" / "app.js").read_text(encoding="utf-8", errors="ignore")
    if f'const VERSION = "{__version__}"' not in app_js:
        raise ValueError(f"Upgrade wrote VERSION {__version__} but web/app.js did not update. Close Paper Trader and retry.")
    html = (dest / "web" / "index.html").read_text(encoding="utf-8", errors="ignore")
    if f'content="{__version__}"' not in html:
        raise ValueError(f"Upgrade wrote VERSION {__version__} but web/index.html did not update. Close Paper Trader and retry.")
    init = (dest / "paper_trader" / "__init__.py").read_text(encoding="utf-8", errors="ignore")
    if f'__version__ = "{__version__}"' not in init:
        raise ValueError(f"Upgrade wrote VERSION {__version__} but paper_trader/__init__.py did not update. Retry FORCE-UPGRADE.")
    # Do not erase older Paper Trader folders. Erase-Other-Paper-Traders.bat is opt-in only.
    _resume_learner(dest)
    write_desktop_opener(dest, home)
    return dest


def desk_seal_report(home: Path | None = None) -> str:
    """Plain-text report of what Documents\\Paper Trader actually contains."""
    home = Path(home or Path.home())
    dest = canonical_folder(home)
    lines = [
        f"Expected package seal: {__version__}",
        f"Desk folder: {dest}",
        f"Folder exists: {dest.is_dir()}",
    ]
    if not dest.is_dir():
        lines.append("RESULT: Documents\\Paper Trader is missing. Run FORCE-UPGRADE.bat from a fresh extract.")
        return "\n".join(lines)
    ver = read_install_version(dest)
    lines.append(f"VERSION file: {ver or '(missing)'}")
    app = dest / "web" / "app.js"
    html = dest / "web" / "index.html"
    init = dest / "paper_trader" / "__init__.py"
    for label, path, needle in (
        ("web/app.js", app, 'const VERSION = "'),
        ("web/index.html", html, 'content="'),
        ("paper_trader/__init__.py", init, '__version__ = "'),
    ):
        if not path.is_file():
            lines.append(f"{label}: MISSING")
            continue
        text = path.read_text(encoding="utf-8", errors="ignore")
        found = ""
        start = text.find(needle)
        if start >= 0:
            chunk = text[start + len(needle) : start + len(needle) + 16]
            found = chunk.split('"', 1)[0]
        lines.append(f"{label}: {found or '(no version string)'}")
    ok = ver == __version__ and all(
        f"{__version__}" in (dest / rel).read_text(encoding="utf-8", errors="ignore")
        for rel in ("web/app.js", "web/index.html", "paper_trader/__init__.py")
        if (dest / rel).is_file()
    )
    lines.append("RESULT: " + ("OK — desk is " + __version__ if ok else "STUCK — still not " + __version__ + ". Close all desk windows and run FORCE-UPGRADE.bat again from a NEW extract."))
    return "\n".join(lines)


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
    """A visible desktop file that one-click opens this copy via Start-Paper-Trader.bat."""
    opener = install / "Start-Paper-Trader.bat"
    if not opener.is_file():
        opener = install / "2 Open Paper Trader.bat"
    if not opener.is_file():
        return []
    script = (
        "@echo off\r\n"
        f'cd /d "{install}"\r\n'
        "title Paper Trader\r\n"
        f'call "{opener.name}"\r\n'
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
    """Install count. Multiple copies are allowed; erase is opt-in only."""
    copies = sorted(find_installs(home), key=_installed_version, reverse=True)
    if not copies:
        return {"name": "Install", "ok": True, "detail": "No extra copies"}
    if len(copies) == 1:
        return {"name": "Install", "ok": True, "detail": "One copy · " + _copy_label(copies[0])}
    newest = copies[0]
    extras = copies[1:]
    shown = ", ".join(_copy_label(path) for path in extras[:4])
    more = f" +{len(extras) - 4} more" if len(extras) > 4 else ""
    detail = (
        f"{len(copies)} copies · this seal {__version__} · newest {_copy_label(newest)} · also {shown}{more}. "
        "Older copies stay. Erase-Other-Paper-Traders.bat is optional."
    )
    return {"name": "Install", "ok": True, "detail": detail}


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
                if "Start-Paper-Trader.bat" in text or "2 Open Paper Trader.bat" in text:
                    path.unlink()
                    removed.append(path)
        except OSError:
            continue
    return removed


KEEP_VERSION = "1.10.55"


def read_install_version(folder: Path) -> str:
    version_file = folder / "VERSION"
    if not version_file.is_file():
        return ""
    try:
        return version_file.read_text(encoding="utf-8").strip()
    except OSError:
        return ""


def _zip_version_label(path: Path) -> str:
    name = path.name.lower()
    # Paper-Trader-1.10.40-slim.zip or Paper-Trader-1.10.40.zip
    marker = "paper-trader-"
    if marker not in name:
        return ""
    after = name.split(marker, 1)[1]
    after = after.removesuffix(".zip")
    if after.endswith("-slim"):
        after = after[: -len("-slim")]
    return after


def erase_other_installs(
    home: Path,
    keep: Path | None = None,
    *,
    keep_version: str = KEEP_VERSION,
    killer=None,
) -> dict[str, object]:
    """Keep only Paper Trader keep_version (default 1.10.55). Erase other installs, zips, and extra Bots."""
    from paper_trader.learn import end_learner_pid, stop_learner

    end_pid = killer or end_learner_pid
    home = Path(home)
    keep_path = (keep or canonical_folder(home)).resolve()
    kept_version = read_install_version(keep_path)
    if kept_version and kept_version != keep_version:
        # Prefer any install that still has the requested seal.
        matches = [
            folder
            for folder in find_installs(home)
            if read_install_version(folder) == keep_version
        ]
        if matches:
            # Prefer Documents\\Paper Trader when it is already the seal.
            preferred = canonical_folder(home).resolve()
            keep_path = preferred if preferred in matches else matches[0]

    closed: list[int] = []
    erased: list[str] = []
    zips_removed: list[str] = []

    for folder in find_installs(home):
        try:
            resolved = folder.resolve()
        except OSError:
            continue
        if resolved == keep_path:
            continue
        pid = stop_learner(resolved / "learn_state.json", killer=end_pid)
        if pid:
            closed.append(pid)
        try:
            shutil.rmtree(resolved)
            erased.append(str(resolved))
        except OSError:
            continue

    # Drop staging leftovers and non-keep zips under the usual user folders.
    for root in search_roots(home):
        try:
            children = list(root.iterdir())
        except OSError:
            continue
        for child in children:
            name = child.name.lower()
            if child.is_dir() and name.startswith("papertrader-setup"):
                try:
                    # Stop a bot left in staging, then erase the staging tree.
                    for nested in child.rglob("learn_state.json"):
                        pid = stop_learner(nested, killer=end_pid)
                        if pid:
                            closed.append(pid)
                    shutil.rmtree(child)
                    erased.append(str(child.resolve()))
                except OSError:
                    continue
            if not child.is_file():
                continue
            if not name.startswith("paper-trader") or not name.endswith(".zip"):
                continue
            label = _zip_version_label(child)
            # Keep the plain Paper-Trader.zip only when this keep folder is the seal,
            # and keep versioned zips that match keep_version.
            if name == "paper-trader.zip":
                if read_install_version(keep_path) == keep_version:
                    continue
            elif label == keep_version:
                continue
            try:
                child.unlink()
                zips_removed.append(str(child.resolve()))
            except OSError:
                continue

    # One desktop shortcut → the kept copy.
    if is_install(keep_path):
        write_desktop_opener(keep_path, home)
        # Resume the single Bot for the kept desk.
        _resume_learner(keep_path)
        # Close a stray Bot still attached to the keep folder's old lock if needed — leave healthy alone.

    return {
        "keep": str(keep_path),
        "keep_version": read_install_version(keep_path) or keep_version,
        "closed": closed,
        "erased": erased,
        "zips_removed": zips_removed,
    }
