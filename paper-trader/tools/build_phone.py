"""Build the single-file mobile page. It runs in Microsoft Edge with no server and no broker."""

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
                "Paper Trader on a phone or tablet",
                "",
                "1. Install Microsoft Edge if it is not already on the device. Use the App Store on iPhone or iPad, or the Play Store on Android.",
                "2. Copy PaperTrader.html onto the device. If it arrives as a text file, rename it to PaperTrader.html.",
                "3. On iPhone or iPad, open the Files app, tap the Share button on PaperTrader.html, then tap Edge.",
                "4. On Android or another phone or tablet, open Files or Downloads, tap PaperTrader.html, choose Open with, then tap Edge.",
                "5. The desk runs in Edge. Crypto quotes are live. Stocks, ETFs, and futures load on the computer copy.",
                "6. Nothing is sent to a broker. This desk is not an app-store app. The page cannot switch to Edge by itself.",
                "",
                "The computer copy is started with Start-Paper-Trader.bat and opens in Microsoft Edge on 127.0.0.1.",
                "That computer keeps scoring after the Paper Trader window closes. It starts again when you sign in to Windows, while the computer is on and you are signed in.",
                "",
            ]
        ),
        encoding="utf-8",
    )
    return out


if __name__ == "__main__":
    path = build()
    print(path, path.stat().st_size)
