#!/usr/bin/env python3
"""Build WordPress-uploadable plugin zips (no Unix extras, files only)."""
from __future__ import annotations

import hashlib
import stat
import time
import zipfile
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
DIST = ROOT / "dist"

PLUGINS = (
    ("plugins/luxe-operator-dashboard", "Luxe-Operator-Dashboard-v1.0.0.zip", "luxe-operator-dashboard"),
    (
        "plugins/richard-brummer-seo-mission-control-stay",
        "Richard-Brummer-SEO-Mission-Control-Stay-Repair-v1.9.3.zip",
        "richard-brummer-seo-mission-control-stay",
    ),
)


def add_file(zf: zipfile.ZipFile, full: Path, arcname: str) -> None:
    info = zipfile.ZipInfo(arcname)
    info.compress_type = zipfile.ZIP_DEFLATED
    info.create_system = 0
    info.external_attr = (stat.S_IFREG | 0o644) << 16
    info.date_time = time.localtime(full.stat().st_mtime)[:6]
    zf.writestr(info, full.read_bytes())


def build(src_rel: str, zip_name: str, folder: str) -> None:
    src = (ROOT / src_rel).resolve()
    DIST.mkdir(parents=True, exist_ok=True)
    dest = DIST / zip_name
    if dest.exists():
        dest.unlink()
    with zipfile.ZipFile(dest, "w") as zf:
        for path in sorted(src.rglob("*")):
            if not path.is_file():
                continue
            if path.name.startswith(".") or path.name in {"Thumbs.db", ".DS_Store"}:
                continue
            rel = path.relative_to(src).as_posix()
            add_file(zf, path, f"{folder}/{rel}")
    digest = hashlib.sha256(dest.read_bytes()).hexdigest()
    print(f"{dest.name} {dest.stat().st_size} {digest}")


def main() -> None:
    for src_rel, zip_name, folder in PLUGINS:
        build(src_rel, zip_name, folder)


if __name__ == "__main__":
    main()
