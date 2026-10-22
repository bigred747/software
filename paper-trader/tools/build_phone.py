"""Build the single-file mobile page. It runs in Microsoft Edge with no server and no broker."""

from __future__ import annotations

import json
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def build() -> Path:
    web = ROOT / "web"
    html = (web / "index.html").read_text(encoding="utf-8")
    css = (web / "app.css").read_text(encoding="utf-8")
    style = "<style>\n" + css + "\n</style>"
    html = html.replace('<link rel="stylesheet" href="/app.css">', style)
    html = html.replace('<link rel="stylesheet" href="app.css">', style)
    raw = (ROOT / "paper_trader" / "catalog.txt").read_text(encoding="utf-8")
    crypto_lines = [line for line in raw.splitlines() if line.endswith("\tcrypto")]
    if len(crypto_lines) < 5:
        raise RuntimeError("crypto catalog is empty")
    catalog = "\n".join(crypto_lines) + "\n"
    live = (web / "live.js").read_text(encoding="utf-8")
    engine = (web / "engine.js").read_text(encoding="utf-8")
    study = (web / "study.js").read_text(encoding="utf-8")
    frames = (web / "frames.js").read_text(encoding="utf-8")
    app = (web / "app.js").read_text(encoding="utf-8")

    def inline(page: str, name: str, body: str) -> str:
        block = "<script>\n" + body + "\n</script>"
        page = page.replace(f'<script src="/{name}"></script>', block)
        return page.replace(f'<script src="{name}"></script>', block)

    html = inline(html, "live.js", live)
    catalog_script = "<script>globalThis.PAPER_CATALOG = " + json.dumps(catalog) + ";</script>\n"
    html = html.replace('<script src="/engine.js"></script>', catalog_script + "<script>\n" + engine + "\n</script>")
    html = html.replace('<script src="engine.js"></script>', catalog_script + "<script>\n" + engine + "\n</script>")
    html = inline(html, "study.js", study)
    html = inline(html, "frames.js", frames)
    html = inline(html, "app.js", app)
    for name in ("live.js", "engine.js", "study.js", "frames.js", "app.js", "app.css"):
        if f'src="/{name}"' in html or f'src="{name}"' in html or f'href="/{name}"' in html or f'href="{name}"' in html:
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
                "5. The desk runs in Edge. The chart, the fear and greed clock, and the Daily scan use live crypto. If one quote does not answer, the page asks the next live crypto feed. If the quote still does not answer, that error is written on the chart. Stocks, ETFs, futures, and the full catalog scan stay on the computer copy.",
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
