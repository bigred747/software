"""Stamp one VERSION into computer + iPhone sources, then rebuild phone + zips.

Usage:
  python3 tools/stamp_version.py              # stamp current VERSION file
  python3 tools/stamp_version.py --bump       # bump patch (1.10.41 -> 1.10.42) then stamp
  python3 tools/stamp_version.py 1.10.42      # set exact version then stamp

Every new download must change the number — use --bump before shipping.
"""

from __future__ import annotations

import argparse
import re
import sys
import zipfile
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
REPO = ROOT.parent


def parse_version(text: str) -> tuple[int, ...]:
    parts = [int(piece) for piece in str(text).strip().split(".") if piece.isdigit()]
    if len(parts) < 2:
        raise ValueError(f"Bad version: {text!r}")
    return tuple(parts)


def format_version(parts: tuple[int, ...]) -> str:
    return ".".join(str(piece) for piece in parts)


def bump_patch(version: str) -> str:
    parts = list(parse_version(version))
    while len(parts) < 3:
        parts.append(0)
    parts[-1] += 1
    return format_version(tuple(parts))


def cache_tag(version: str) -> str:
    return "".join(ch for ch in version if ch.isdigit())


def replace_file(path: Path, pattern: str, repl: str, *, flags: int = 0, required: bool = True) -> None:
    text = path.read_text(encoding="utf-8")
    next_text, count = re.subn(pattern, repl, text, flags=flags)
    if required and count < 1:
        raise RuntimeError(f"No match in {path} for {pattern!r}")
    path.write_text(next_text, encoding="utf-8")


def stamp(version: str) -> None:
    parse_version(version)  # validate
    tag = cache_tag(version)
    (ROOT / "VERSION").write_text(version + "\n", encoding="utf-8")

    replace_file(ROOT / "paper_trader" / "__init__.py", r'__version__\s*=\s*"[^"]*"', f'__version__ = "{version}"')
    replace_file(ROOT / "web" / "app.js", r'const VERSION = "[^"]*";', f'const VERSION = "{version}";')
    replace_file(
        ROOT / "web" / "index.html",
        r'<meta name="paper-build" content="[^"]*">',
        f'<meta name="paper-build" content="{version}">',
    )
    replace_file(
        ROOT / "web" / "index.html",
        r'(<span id="build-tag" class="build-tag">)[^<]*(</span>)',
        rf"\g<1>{version}\g<2>",
    )
    replace_file(
        ROOT / "web" / "index.html",
        r'(src="(?:live|engine|study|frames|booster|app)\.js)\?v=[^"]*"',
        rf'\1?v={tag}"',
    )
    replace_file(ROOT / "paper_trader" / "install.py", r'KEEP_VERSION = "[^"]*"', f'KEEP_VERSION = "{version}"')
    # Keep Windows path backslashes doubled in source (Documents\\Paper Trader).
    replace_file(
        ROOT / "paper_trader" / "install.py",
        r"(Documents(?:\\\\)+Paper Trader \()1\.\d+\.\d+(\))",
        rf"\g<1>{version}\g<2>",
        required=False,
    )
    replace_file(
        ROOT / "paper_trader" / "install.py",
        r"(Guides keep Documents(?:\\\\)+Paper Trader )1\.\d+\.\d+( only\.)",
        rf"\g<1>{version}\g<2>",
        required=False,
    )
    replace_file(
        ROOT / "paper_trader" / "install.py",
        r"(default )1\.\d+\.\d+",
        rf"\g<1>{version}",
        required=False,
    )
    replace_file(
        ROOT / "paper_trader" / "cli.py",
        r"Keep Paper Trader 1\.\d+\.\d+ only",
        f"Keep Paper Trader {version} only",
    )
    replace_file(
        ROOT / "paper_trader" / "sync.py",
        r'PaperTrader-Desk/1\.\d+\.\d+',
        f"PaperTrader-Desk/{version}",
    )
    replace_file(
        ROOT / "paper_trader" / "desk_pool.py",
        r'PaperTrader-DeskPool/1\.\d+\.\d+',
        f"PaperTrader-DeskPool/{version}",
    )
    replace_file(
        ROOT / "paper_trader" / "desk_pool.py",
        r'"version": "1\.\d+\.\d+"',
        f'"version": "{version}"',
    )

    for bat_name, patterns in (
        (
            "Erase-Other-Paper-Traders.bat",
            [
                (r"keep 1\.\d+\.\d+ only", f"keep {version} only"),
                (r"Keep Paper Trader 1\.\d+\.\d+", f"Keep Paper Trader {version}"),
            ],
        ),
        (
            "1 Allow Paper Trader.bat",
            [
                (r"Paper Trader 1\.\d+\.\d+", f"Paper Trader {version}"),
            ],
        ),
    ):
        path = ROOT / bat_name
        if not path.is_file():
            continue
        text = path.read_text(encoding="utf-8", errors="ignore")
        for pattern, repl in patterns:
            text, _ = re.subn(pattern, repl, text)
        path.write_text(text, encoding="utf-8")

    note = ROOT / f"Paper-Trader-{version}-slim.txt"
    note.write_text(
        f"Paper Trader {version} — computer zip + iPhone single-file desk. Seal must read {version}.\n",
        encoding="utf-8",
    )


def build_phone() -> None:
    from tools.build_phone import build

    out = build()
    print(out, out.stat().st_size)


def build_zips(version: str) -> None:
    full_zip = REPO / "Paper-Trader.zip"
    slim_zip = ROOT / f"Paper-Trader-{version}-slim.zip"
    update = [
        "web/frames.js",
        "web/app.js",
        "web/index.html",
        "web/app.css",
        "web/booster.js",
        "web/frames.test.mjs",
        "iphone/index.html",
        "paper_trader/quotes.py",
        "paper_trader/server.py",
        "paper_trader/__init__.py",
        "paper_trader/install.py",
        "paper_trader/cli.py",
        "paper_trader/sync.py",
        "paper_trader/desk_pool.py",
        "VERSION",
        "tools/build_phone.py",
        "tools/stamp_version.py",
        "1 Allow Paper Trader.bat",
        "Erase-Other-Paper-Traders.bat",
    ]

    def refresh(zip_path: Path, slim: bool, base: Path | None = None) -> None:
        source = base if base and base.exists() else zip_path
        if not source.exists():
            raise FileNotFoundError(f"Missing zip base: {source}")
        tmp = zip_path.with_suffix(zip_path.suffix + ".tmp")
        skip = {("paper-trader/" + rel).replace("\\", "/") for rel in update}
        with zipfile.ZipFile(source, "r") as zin, zipfile.ZipFile(tmp, "w", compression=zipfile.ZIP_DEFLATED) as zout:
            for info in zin.infolist():
                name = info.filename.replace("\\", "/")
                if name in skip or name.rstrip("/") in skip:
                    continue
                if slim and name.startswith("paper-trader/runtime/"):
                    continue
                # Drop prior slim notes / versioned slim zips from the archive payload itself.
                if name.startswith("paper-trader/Paper-Trader-") and name.endswith((".zip", ".txt")):
                    continue
                zout.writestr(info, zin.read(info.filename))
            for rel in update:
                path = ROOT / rel
                if path.exists():
                    zout.write(path, "paper-trader/" + rel)
            note = ROOT / f"Paper-Trader-{version}-slim.txt"
            if note.exists():
                zout.write(note, "paper-trader/" + note.name)
        tmp.replace(zip_path)
        print(zip_path.name, zip_path.stat().st_size)

    refresh(full_zip, False)
    # Prefer refreshing slim from previous slim if present, else from full.
    old_slims = sorted(ROOT.glob("Paper-Trader-*-slim.zip"))
    base_slim = None
    for path in reversed(old_slims):
        if path.name != slim_zip.name:
            base_slim = path
            break
    refresh(slim_zip, True, base_slim or full_zip)


def verify(version: str) -> None:
    tag = cache_tag(version)
    checks = [
        (ROOT / "VERSION", version),
        (ROOT / "paper_trader" / "__init__.py", f'__version__ = "{version}"'),
        (ROOT / "web" / "app.js", f'const VERSION = "{version}"'),
        (ROOT / "web" / "index.html", f'content="{version}"'),
        (ROOT / "web" / "index.html", f"?v={tag}"),
        (ROOT / "iphone" / "index.html", f'content="{version}"'),
        (ROOT / "iphone" / "index.html", f'const VERSION = "{version}"'),
        (ROOT / "paper_trader" / "install.py", f'KEEP_VERSION = "{version}"'),
    ]
    for path, needle in checks:
        text = path.read_text(encoding="utf-8")
        if needle not in text:
            raise RuntimeError(f"Missing {needle!r} in {path}")
    with zipfile.ZipFile(REPO / "Paper-Trader.zip") as zin:
        ver = zin.read("paper-trader/VERSION").decode().strip()
        html = zin.read("paper-trader/web/index.html").decode()
        phone = zin.read("paper-trader/iphone/index.html").decode()
        init = zin.read("paper-trader/paper_trader/__init__.py").decode()
    if ver != version:
        raise RuntimeError(f"zip VERSION {ver} != {version}")
    if f'__version__ = "{version}"' not in init:
        raise RuntimeError("zip __init__ version mismatch")
    if f'content="{version}"' not in html or f'content="{version}"' not in phone:
        raise RuntimeError("zip html/phone seal mismatch")
    print("verify ok", version)


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("version", nargs="?", help="Exact version to stamp")
    parser.add_argument("--bump", action="store_true", help="Bump patch on current VERSION first")
    parser.add_argument("--no-zip", action="store_true", help="Stamp + phone only")
    args = parser.parse_args(argv)

    current = (ROOT / "VERSION").read_text(encoding="utf-8").strip() if (ROOT / "VERSION").is_file() else "1.10.0"
    if args.version:
        version = args.version.strip()
    elif args.bump:
        version = bump_patch(current)
    else:
        version = current

    if args.bump and args.version:
        raise SystemExit("Use either --bump or an exact version, not both")

    print("stamping", version, "(was", current + ")")
    stamp(version)
    # Ensure import path for build_phone
    sys.path.insert(0, str(ROOT))
    build_phone()
    if not args.no_zip:
        build_zips(version)
    verify(version)
    print("computer:", REPO / "Paper-Trader.zip")
    print("slim:", ROOT / f"Paper-Trader-{version}-slim.zip")
    print("iphone:", ROOT / "iphone" / "index.html")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
