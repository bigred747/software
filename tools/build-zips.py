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
    ("plugins/luxe-operator-dashboard", "Luxe-Operator-Dashboard-v1.1.1.zip", "luxe-operator-dashboard"),
    (
        "plugins/richard-brummer-seo-mission-control-stay",
        "Richard-Brummer-SEO-Mission-Control-Stay-Repair-v1.9.5.zip",
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


PACK_NAME = "Luxe-Dashboard-Fix-v1.1.1.zip"

INSTALL_TXT = """Luxe Dashboard Fix v1.1.1

Do not upload THIS pack zip into WordPress. Unzip it first, then upload the two plugin zips.

WordPress → Plugins → Add Plugin → Upload Plugin

1. Luxe-Operator-Dashboard-v1.1.1.zip
   Replace 1.1.0 if installed. Keep Luxe Hard Rescue Admin Cleaner active.

2. Richard-Brummer-SEO-Mission-Control-Stay-Repair-v1.9.5.zip
   Upload over Stay Repair 1.9.4. Do not replace Mission Control 1.8.1.
   This version stops the 1-second Guest Mode reload and adds a five-minute
   compare hub on /product-tag/ and /brand/ archives.

Then: LiteSpeed Guest Mode OFF, Crawler OFF, Purge All.
Open /product-tag/apple/ as a logged-out visitor and browse for a few minutes.
Site Kit 1s will not change today (28-day average). Trust Stay Repair dwell samples.
"""


def build_pack() -> None:
    dest = DIST / PACK_NAME
    if dest.exists():
        dest.unlink()
    members = [
        DIST / "Luxe-Operator-Dashboard-v1.1.1.zip",
        DIST / "Richard-Brummer-SEO-Mission-Control-Stay-Repair-v1.9.5.zip",
    ]
    with zipfile.ZipFile(dest, "w") as zf:
        info = zipfile.ZipInfo("INSTALL.txt")
        info.compress_type = zipfile.ZIP_DEFLATED
        info.create_system = 0
        info.external_attr = (stat.S_IFREG | 0o644) << 16
        info.date_time = time.localtime()[:6]
        zf.writestr(info, INSTALL_TXT)
        for member in members:
            add_file(zf, member, member.name)
    digest = hashlib.sha256(dest.read_bytes()).hexdigest()
    print(f"{dest.name} {dest.stat().st_size} {digest}")


def main() -> None:
    for src_rel, zip_name, folder in PLUGINS:
        build(src_rel, zip_name, folder)
    build_pack()


if __name__ == "__main__":
    main()
