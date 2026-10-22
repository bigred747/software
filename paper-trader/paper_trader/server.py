"""Local-only desk. Listens on 127.0.0.1 and serves market quotes."""

from __future__ import annotations

import json
import threading
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path
from urllib.parse import parse_qs, unquote, urlparse

from paper_trader import __version__
from paper_trader.backtest import present, run_backtest
from paper_trader.install import single_copy_status
from paper_trader.journal import Journal, load_journal, market_view, save_journal, view_journal
from paper_trader.model import params_from_dict
from paper_trader import quotes
from paper_trader.samples import catalog_size, search_symbols, warm

WEB_ROOT = Path(__file__).resolve().parents[1] / "web"
STATIC = {
    "/index.html": "index.html",
    "/": "index.html",
    "/app.js": "app.js",
    "/app.css": "app.css",
    "/engine.js": "engine.js",
    "/live.js": "live.js",
    "/study.js": "study.js",
    "/frames.js": "frames.js",
    "/frame.js": "frame.js",
    "/frame.html": "frame.html",
    "/manifest.webmanifest": "manifest.webmanifest",
    "/icon.png": "icon.png",
}
TYPES = {
    "index.html": "text/html; charset=utf-8",
    "app.js": "text/javascript; charset=utf-8",
    "app.css": "text/css; charset=utf-8",
    "engine.js": "text/javascript; charset=utf-8",
    "live.js": "text/javascript; charset=utf-8",
    "study.js": "text/javascript; charset=utf-8",
    "frames.js": "text/javascript; charset=utf-8",
    "frame.js": "text/javascript; charset=utf-8",
    "frame.html": "text/html; charset=utf-8",
    "manifest.webmanifest": "application/manifest+json",
    "icon.png": "image/png",
}
_STATIC_BYTES: dict[str, bytes] = {}
_LOCAL_HOSTS = {"127.0.0.1", "localhost"}
_SECURITY_HEADERS = (
    ("X-Content-Type-Options", "nosniff"),
    ("Referrer-Policy", "no-referrer"),
    ("X-Frame-Options", "DENY"),
    ("Content-Security-Policy", "default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'"),
    ("Cache-Control", "no-store"),
)


class Desk:
    def __init__(self, state_path: Path) -> None:
        self.state_path = state_path
        self.lock = threading.Lock()
        self.journal = load_journal(state_path)

    def snapshot(self) -> dict:
        with self.lock:
            return view_journal(self.journal)

    def checks(self) -> dict:
        view: dict = {"paper": True, "mode": "paper"}
        quote_detail = "Waiting on the quote"
        quote_ok = False
        try:
            with self.lock:
                view = view_journal(self.journal)
        except ValueError as exc:
            quote_detail = str(exc)
        else:
            try:
                quote_detail = quotes.ping()
                quote_ok = True
            except ValueError as exc:
                quote_detail = str(exc)
        listed = catalog_size()
        items: list[dict[str, str | bool]] = [
            {"name": "This computer", "ok": True, "detail": "127.0.0.1"},
            {"name": "Paper", "ok": view.get("paper") is True and view.get("mode") == "paper", "detail": "On"},
            {"name": "Auto", "ok": True, "detail": "Ready"},
            {"name": "Trade", "ok": quote_ok and view.get("paper") is True, "detail": "Ready" if quote_ok else "Waiting on the quote"},
            {"name": "Markets", "ok": listed >= 7500, "detail": f"{listed} markets"},
            {"name": "Quotes", "ok": quote_ok, "detail": quote_detail},
            {"name": "Squeeze", "ok": quote_ok, "detail": "Ready" if quote_ok else "Waiting on the quote"},
            {"name": "Arrows", "ok": quote_ok, "detail": "Ready" if quote_ok else "Waiting on the quote"},
            {"name": "Study", "ok": True, "detail": "On this device"},
            {"name": "Timeframes", "ok": True, "detail": "10 windows"},
            {"name": "Phone", "ok": True, "detail": "Layout adapts"},
            {"name": "Broker", "ok": True, "detail": "Not connected"},
            {"name": "Version", "ok": True, "detail": __version__},
            single_copy_status(Path.home()),
        ]
        return {"ok": all(bool(item["ok"]) for item in items), "items": items}

    def reset(self, raw: dict) -> dict:
        with self.lock:
            params = params_from_dict(raw)
            symbol = str(raw.get("symbol") or self.journal.symbol)
            bars = quotes.load_bars(symbol, refresh=True)
            if params.slow + 1 >= len(bars):
                raise ValueError("The slow average is longer than the market history")
            self.journal = Journal(symbol, params, len(bars))
            save_journal(self.state_path, self.journal)
            return view_journal(self.journal)

    def step(self, days: int) -> dict:
        if days < 1 or days > 500:
            raise ValueError("Step must be from 1 to 500 days")
        with self.lock:
            bars = quotes.load_bars(self.journal.symbol, refresh=True)
            self.journal.cursor = len(bars)
            save_journal(self.state_path, self.journal)
            return view_journal(self.journal)

    def trade(self) -> dict:
        with self.lock:
            bars = quotes.load_bars(self.journal.symbol, refresh=True)
            held = view_journal(self.journal)["shares"] > 0
            side = "sell" if held else "buy"
            self.journal.close_orders.append((bars[-1].date, side))
            save_journal(self.state_path, self.journal)
            payload = view_journal(self.journal)
            payload["last_action"] = side
            return payload

    def run_rest(self) -> dict:
        with self.lock:
            bars = quotes.load_bars(self.journal.symbol, refresh=True)
            self.journal.cursor = len(bars)
            save_journal(self.state_path, self.journal)
            return view_journal(self.journal)

    def bars(self, symbol: str, frame: str) -> dict:
        return quotes.fetch_frame(symbol, frame)

    def backtest(self, raw: dict) -> dict:
        params = params_from_dict(raw)
        symbol = str(raw.get("symbol") or "BTC-USD")
        bars = quotes.load_bars(symbol)
        result = run_backtest(bars, params)
        payload = present(result, symbol, len(bars), params, len(bars))
        payload["note"] = "Replay of market history. The paper session was left where it is."
        return payload

    def market(self, symbol: str, raw: dict) -> dict:
        params = params_from_dict(raw)
        return market_view(symbol, params)


def _local_host(header: str) -> bool:
    name = header.strip().lower().split(":", 1)[0]
    return name in _LOCAL_HOSTS


def load_static() -> None:
    if _STATIC_BYTES:
        return
    root = WEB_ROOT.resolve()
    for name in TYPES:
        file_path = (WEB_ROOT / name).resolve()
        if file_path.parent != root or not file_path.is_file():
            raise FileNotFoundError(name)
        _STATIC_BYTES[name] = file_path.read_bytes()


def create_server(state_path: Path, port: int = 8765) -> ThreadingHTTPServer:
    warm()
    load_static()
    desk = Desk(state_path)
    handler = type("DeskHandler", (DeskHandler,), {"desk": desk})
    server = ThreadingHTTPServer(("127.0.0.1", port), handler)
    if server.server_address[0] != "127.0.0.1":
        server.server_close()
        raise RuntimeError("The desk refused to listen outside this computer")
    server.desk = desk  # type: ignore[attr-defined]
    return server


class DeskHandler(BaseHTTPRequestHandler):
    desk: Desk
    protocol_version = "HTTP/1.1"

    def do_GET(self) -> None:  # noqa: N802
        try:
            self._route("GET")
        except ValueError as exc:
            self._json({"error": str(exc)}, 400)
        except Exception:
            self._json({"error": "The desk could not complete that request"}, 500)

    def do_POST(self) -> None:  # noqa: N802
        try:
            self._route("POST")
        except ValueError as exc:
            self._json({"error": str(exc)}, 400)
        except Exception:
            self._json({"error": "The desk could not complete that request"}, 500)

    def log_message(self, fmt: str, *args) -> None:
        return

    def address_string(self) -> str:
        return self.client_address[0]

    def _route(self, method: str) -> None:
        if not _local_host(self.headers.get("Host", "")):
            self._json({"error": "Not found"}, 404)
            return
        parsed = urlparse(self.path)
        if parsed.scheme or parsed.netloc:
            self._json({"error": "Not found"}, 404)
            return
        path = unquote(parsed.path)
        if ".." in path or path.startswith("//"):
            self._json({"error": "Not found"}, 404)
            return
        query = {key: values[-1] for key, values in parse_qs(parsed.query).items()}
        if path == "/favicon.ico":
            self.send_response(204)
            self.send_header("Content-Length", "0")
            self.end_headers()
            return
        if method == "GET" and path == "/catalog.txt":
            body = (Path(__file__).resolve().parent / "catalog.txt").read_bytes()
            self.send_response(200)
            self.send_header("Content-Type", "text/plain; charset=utf-8")
            self.send_header("Content-Length", str(len(body)))
            self._security_headers()
            self.end_headers()
            self.wfile.write(body)
            return
        if method == "GET" and path in STATIC:
            self._static(STATIC[path])
            return
        if method == "GET" and path == "/api/health":
            self._json({"ok": True, "bind": "127.0.0.1", "version": __version__})
            return
        if method == "GET" and path == "/api/checks":
            self._json(self.desk.checks())
            return
        if method == "GET" and path == "/api/symbols":
            try:
                limit = int(query.get("limit", "40"))
            except ValueError:
                limit = 40
            self._json(search_symbols(query.get("q", ""), query.get("category", ""), limit))
            return
        if method == "GET" and path == "/api/paper":
            self._json(self.desk.snapshot())
            return
        if method == "GET" and path == "/api/market":
            symbol = query.get("symbol") or self.desk.journal.symbol
            raw = {**self.desk.journal.params.to_dict(), **query}
            self._json(self.desk.market(symbol, raw))
            return
        if method == "GET" and path == "/api/bars":
            symbol = query.get("symbol") or self.desk.journal.symbol
            self._json(self.desk.bars(symbol, query.get("frame") or "1D"))
            return
        if method == "GET" and path == "/api/backtest":
            raw = {**self.desk.journal.params.to_dict(), **query, "symbol": query.get("symbol", self.desk.journal.symbol)}
            self._json(self.desk.backtest(raw))
            return
        body = self._body() if method == "POST" else {}
        if method == "POST" and path == "/api/paper/mode":
            if str(body.get("mode") or "") != "paper":
                raise ValueError("This desk only runs in paper mode")
            self._json(self.desk.snapshot())
            return
        if method == "POST" and path == "/api/paper/reset":
            self._json(self.desk.reset(body))
            return
        if method == "POST" and path == "/api/paper/trade":
            self._json(self.desk.trade())
            return
        if method == "POST" and path == "/api/paper/step":
            self._json(self.desk.step(int(body.get("days", 1))))
            return
        if method == "POST" and path == "/api/paper/run":
            self._json(self.desk.run_rest())
            return
        if method == "POST" and path == "/api/backtest":
            self._json(self.desk.backtest(body))
            return
        self._json({"error": "Not found"}, 404)

    def _body(self) -> dict:
        length = int(self.headers.get("Content-Length", "0") or 0)
        if length > 100_000:
            raise ValueError("Request is too large")
        if length <= 0:
            return {}
        raw = self.rfile.read(length)
        data = json.loads(raw.decode("utf-8"))
        if not isinstance(data, dict):
            raise ValueError("Request must be a JSON object")
        return data

    def _json(self, payload: dict, status: int = 200) -> None:
        body = json.dumps(payload, allow_nan=False).encode("utf-8")
        self.send_response(status)
        self.send_header("Content-Type", "application/json; charset=utf-8")
        self.send_header("Content-Length", str(len(body)))
        self._security_headers()
        self.end_headers()
        self.wfile.write(body)

    def _security_headers(self) -> None:
        for name, value in _SECURITY_HEADERS:
            self.send_header(name, value)

    def _static(self, name: str) -> None:
        body = _STATIC_BYTES.get(name)
        if body is None:
            self._json({"error": "Not found"}, 404)
            return
        self.send_response(200)
        self.send_header("Content-Type", TYPES[name])
        self.send_header("Content-Length", str(len(body)))
        self._security_headers()
        self.end_headers()
        self.wfile.write(body)


def serve(state_path: Path, port: int = 8765, open_browser: bool = True) -> None:
    import webbrowser

    server = create_server(state_path, port)
    host, bound = server.server_address[:2]
    url = f"http://{host}:{bound}/"
    print(f"Paper Trader is running at {url}")
    print("Market quotes. Paper orders only. Close this window to stop the desk.")
    if open_browser and url.startswith("http://127.0.0.1:"):
        webbrowser.open(url)
    try:
        server.serve_forever()
    except KeyboardInterrupt:
        print("\nStopped.")
    finally:
        server.server_close()
