"""Build the single-file phone page. It runs in Safari with no server and no broker."""

from __future__ import annotations

import json
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def build() -> Path:
    web = ROOT / "web"
    html = (web / "index.html").read_text(encoding="utf-8")
    css = (web / "app.css").read_text(encoding="utf-8")
    html = html.replace('<link rel="stylesheet" href="/app.css">', "<style>\n" + css + "\n</style>")
    catalog = (ROOT / "paper_trader" / "catalog.txt").read_text(encoding="utf-8")
    live = (web / "live.js").read_text(encoding="utf-8")
    engine = (web / "engine.js").read_text(encoding="utf-8")
    study = (web / "study.js").read_text(encoding="utf-8")
    frames = (web / "frames.js").read_text(encoding="utf-8")
    app = (web / "app.js").read_text(encoding="utf-8")
    html = html.replace('<script src="/live.js"></script>', "<script>\n" + live + "\n</script>")
    html = html.replace(
        '<script src="/engine.js"></script>',
        "<script>globalThis.PAPER_CATALOG = " + json.dumps(catalog) + ";</script>\n<script>\n" + engine + "\n</script>",
    )
    html = html.replace('<script src="/study.js"></script>', "<script>\n" + study + "\n</script>")
    html = html.replace('<script src="/frames.js"></script>', "<script>\n" + frames + "\n</script>")
    html = html.replace('<script src="/app.js"></script>', "<script>\n" + app + "\n</script>")
    for name in ("live.js", "engine.js", "study.js", "frames.js", "app.js"):
        if f"/{name}" in html:
            raise RuntimeError(f"{name} was not inlined")
    out = ROOT / "iphone" / "PaperTrader.html"
    out.parent.mkdir(exist_ok=True)
    out.write_text(html, encoding="utf-8")
    note = ROOT / "iphone" / "README.txt"
    note.write_text(
        "\n".join(
            [
                "Paper Trader on iPhone",
                "",
                "1. Copy PaperTrader.html onto the iPhone (AirDrop, Files, or a cable).",
                "2. Open the Files app, tap PaperTrader.html, and choose Safari.",
                "3. The desk runs in Safari. Crypto quotes are live. Stocks, ETFs, and futures load on the computer copy.",
                "4. Nothing is sent to a broker. There is no app-store install.",
                "",
                "The computer copy is started with Start-Paper-Trader.bat and stays on 127.0.0.1.",
                "",
            ]
        ),
        encoding="utf-8",
    )
    return out


if __name__ == "__main__":
    path = build()
    print(path, path.stat().st_size)
