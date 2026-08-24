#!/usr/bin/env python3
"""Build a Windows 11 / WordPress PclZip plugin zip (PKZIP 2.0, MS-DOS)."""
from __future__ import annotations

import hashlib
import io
import struct
import time
import zlib
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
SLUG = "luxe-blog-master"
DEST_DIR = ROOT.parents[1]
OUTPUTS = [
    DEST_DIR / "luxe-blog-master.zip",
    DEST_DIR / "Luxe-Blog-Master.zip",
    DEST_DIR / "dist" / "Luxe-Blog-Master-v2.8.1.zip",
]
SKIP_PARTS = {"tests", "bin"}


def dos_datetime(ts: float | None = None) -> tuple[int, int]:
    t = time.localtime(ts if ts is not None else time.time())
    date = ((t.tm_year - 1980) << 9) | (t.tm_mon << 5) | t.tm_mday
    dostime = (t.tm_hour << 11) | (t.tm_min << 5) | (t.tm_sec // 2)
    return dostime, date


def collect_files() -> list[tuple[str, bytes, bool]]:
    rows: list[tuple[str, bytes, bool]] = []
    for path in sorted(ROOT.rglob("*")):
        if not path.is_file():
            continue
        rel = path.relative_to(ROOT)
        if SKIP_PARTS.intersection(rel.parts) or path.name == ".DS_Store":
            continue
        data = path.read_bytes()
        store = path.suffix.lower() in {".png", ".jpg", ".jpeg", ".webp", ".gif"}
        rows.append((f"{SLUG}/{rel.as_posix()}", data, store))
    return rows


def deflate(data: bytes) -> bytes:
    compressor = zlib.compressobj(6, zlib.DEFLATED, -zlib.MAX_WBITS)
    return compressor.compress(data) + compressor.flush()


def build_zip(files: list[tuple[str, bytes, bool]]) -> bytes:
    dostime, dosdate = dos_datetime()
    buf = io.BytesIO()
    central = io.BytesIO()
    count = 0
    written: list[tuple[str, bytes, bool]] = []
    dirs = []
    seen = set()
    for name, _data, _store in files:
        parts = name.split("/")
        acc = []
        for part in parts[:-1]:
            acc.append(part)
            d = "/".join(acc) + "/"
            if d not in seen:
                seen.add(d)
                dirs.append(d)
    for d in dirs:
        written.append((d, b"", True))
    written.extend(files)
    for name, data, store in written:
        name_b = name.encode("ascii")
        crc = zlib.crc32(data) & 0xFFFFFFFF
        is_dir = name.endswith("/")
        compressed = data if store or is_dir else deflate(data)
        method = 0 if store or is_dir else 8
        offset = buf.tell()
        buf.write(b"PK\x03\x04")
        buf.write(struct.pack("<HHHHHIIIHH", 20, 0, method, dostime, dosdate, crc, len(compressed), len(data), len(name_b), 0))
        buf.write(name_b)
        buf.write(compressed)
        ext_attr = 0x10 if is_dir else 0x20
        central.write(b"PK\x01\x02")
        central.write(struct.pack("<HHHHHHIIIHHHHHII", 20, 20, 0, method, dostime, dosdate, crc, len(compressed), len(data), len(name_b), 0, 0, 0, 0, ext_attr, offset))
        central.write(name_b)
        count += 1
    cd = central.getvalue()
    cd_offset = buf.tell()
    buf.write(cd)
    buf.write(b"PK\x05\x06")
    buf.write(struct.pack("<HHHHIIH", 0, 0, count, count, len(cd), cd_offset, 0))
    return buf.getvalue()


def main() -> None:
    raise SystemExit(
        "Do not pack luxe-blog-master.zip. Live Luxe Blog Master is 2.9.6; "
        "this identity plugin is 2.8.1 (older). WordPress correctly blocks the replace."
    )


if __name__ == "__main__":
    main()
