"""Local-only desk. Listens on 127.0.0.1 and serves market quotes."""

from __future__ import annotations

import json
import os
import shutil
import subprocess
import sys
import threading
import time
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path
from urllib.parse import parse_qs, unquote, urlparse

from paper_trader import __version__
from paper_trader.backtest import present, run_backtest
from paper_trader.install import keep_only_running, single_copy_status, write_desktop_opener
from paper_trader.journal import Journal, load_journal, market_view, save_journal, view_journal
from paper_trader.learn import (
    allow_entry,
    buys_on,
    book_view,
    bought_on,
    closed_bars,
    desk_day,
    ensure_learner,
    mood,
    pick_note,
    rank_leaders,
    rank_today,
    remember_day_buy,
    remember_focus,
    remember_scan_switch,
    remember_symbols,
    scan_report,
    score_row,
    symbol_rows,
)
from paper_trader.model import params_from_dict
from paper_trader.indicators import squeeze, squeeze_alert
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


BREAKER_DROP = 0.05
PAPER_SLIP_BPS = 5.0


def fill_price(last: float, side: str) -> float:
    """The latest trade moved against the order by the paper slippage, as a market order fills."""
    slip = PAPER_SLIP_BPS / 10_000.0
    return last * (1 + slip) if side == "buy" else last * (1 - slip)


def fill_note(side: str, last: float, price: float, fee_bps: float) -> dict:
    return {
        "side": side,
        "quote": last,
        "price": price,
        "slip_bps": PAPER_SLIP_BPS,
        "fee_bps": fee_bps,
        "at": int(time.time()),
    }
LIVE_NOTE = "Live is locked. This desk has no broker or bank link, so every order stays paper."


def load_breaker(path: Path) -> dict:
    try:
        raw = json.loads(path.read_text(encoding="utf-8"))
    except (OSError, ValueError):
        return {}
    return raw if isinstance(raw, dict) else {}


def save_breaker(path: Path, data: dict) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    temporary = path.with_suffix(path.suffix + ".tmp")
    temporary.write_text(json.dumps(data, indent=2) + "\n", encoding="utf-8")
    temporary.replace(path)


def breaker_view(data: dict, day: str) -> dict:
    tripped = str(data.get("day") or "") == day
    start = float(data.get("start_equity") or 0) if str(data.get("start_day") or "") == day else 0.0
    return {
        "tripped": tripped,
        "reason": str(data.get("reason") or "") if tripped else "",
        "at": int(data.get("at") or 0) if tripped else 0,
        "drop_pct": round(BREAKER_DROP * 100, 2),
        "start_equity": start,
        "day": day,
    }


def _ticker_ok(symbol: str) -> bool:
    if not symbol:
        return False
    found = search_symbols(symbol, "", 8)
    rows = found.get("symbols")
    return isinstance(rows, list) and any(row.get("symbol") == symbol for row in rows)


class Desk:
    def __init__(self, state_path: Path) -> None:
        self.state_path = state_path
        self.lock = threading.Lock()
        self.journal = load_journal(state_path)
        self.learn_path = state_path.with_name("learn_state.json")
        self.watch_path = state_path.with_name("learn_watch.json")
        self.breaker_path = state_path.with_name("breaker.json")
        self.expect_learner = False

    def snapshot(self) -> dict:
        with self.lock:
            return self._guard(view_journal(self.journal))

    def breaker(self) -> dict:
        return breaker_view(load_breaker(self.breaker_path), desk_day())

    def halt(self, reason: str = "") -> dict:
        with self.lock:
            return self._halt_locked(reason or "Sell all pressed")

    def _halt_locked(self, reason: str) -> dict:
        bars = quotes.load_bars(self.journal.symbol, refresh=True)
        held = view_journal(self.journal)["shares"] > 0
        last = bars[-1].close
        if held:
            self.journal.close_orders.append((bars[-1].date, "sell", fill_price(last, "sell")))
        if bars[-1].date not in self.journal.halt_days:
            self.journal.halt_days.append(bars[-1].date)
        save_journal(self.state_path, self.journal)
        day = desk_day()
        data = load_breaker(self.breaker_path)
        data.update({"day": day, "reason": reason, "at": int(time.time())})
        save_breaker(self.breaker_path, data)
        payload = view_journal(self.journal)
        payload["last_action"] = "sell" if held else ""
        if held:
            payload["fill"] = fill_note("sell", last, fill_price(last, "sell"), self.journal.params.fee_bps)
        payload["breaker"] = breaker_view(data, day)
        return payload

    def _guard(self, payload: dict, fresh_start: bool = False) -> dict:
        """Hold the breaker for the Denver day and trip it on a 5% daily paper drop."""
        day = desk_day()
        data = load_breaker(self.breaker_path)
        state = breaker_view(data, day)
        if state["tripped"]:
            last = payload.get("last_date")
            if last and last not in self.journal.halt_days:
                self.journal.halt_days.append(str(last))
                save_journal(self.state_path, self.journal)
                payload = view_journal(self.journal)
        equity = float(payload.get("equity") or 0)
        start = state["start_equity"]
        if fresh_start or not start > 0:
            if equity > 0:
                data.update({"start_day": day, "start_equity": equity})
                save_breaker(self.breaker_path, data)
        elif not state["tripped"] and equity <= start * (1 - BREAKER_DROP):
            fell = (1 - equity / start) * 100
            return self._halt_locked(f"Paper equity fell {fell:.1f}% today")
        payload["breaker"] = breaker_view(data, day)
        return payload

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
            {"name": "Ticker", "ok": _ticker_ok(str(view.get("symbol") or "")), "detail": "Click opens the chart"},
            {
                "name": "Chart",
                "ok": quote_ok and int(view.get("total_bars") or 0) >= 40,
                "detail": f"{int(view.get('total_bars') or 0)} bars" if quote_ok else "Waiting on the quote",
            },
            {"name": "Markets", "ok": listed >= 7500, "detail": f"{listed} markets"},
            {"name": "Quotes", "ok": quote_ok, "detail": quote_detail},
            {"name": "Squeeze", "ok": quote_ok, "detail": "Ready" if quote_ok else "Waiting on the quote"},
            {"name": "Arrows", "ok": quote_ok, "detail": "Ready" if quote_ok else "Waiting on the quote"},
            {"name": "Study", "ok": True, "detail": "On this device"},
            self._learning_check(),
            self._mood_check(str(view.get("symbol") or ""), quote_ok),
            {"name": "Zoom", "ok": True, "detail": "In and out"},
            {"name": "Timeframes", "ok": len(quotes.frame_ids()) == 10, "detail": f"{len(quotes.frame_ids())} windows"},
            {"name": "Phone", "ok": True, "detail": "Layout adapts"},
            self._breaker_check(),
            {"name": "Broker", "ok": True, "detail": "Not connected"},
            {"name": "Version", "ok": True, "detail": __version__},
            single_copy_status(Path.home()),
        ]
        return {"ok": all(bool(item["ok"]) for item in items), "items": items}

    def _breaker_check(self) -> dict:
        state = self.breaker()
        if state["tripped"]:
            return {"name": "Breaker", "ok": False, "detail": "Tripped. Buys stop until tomorrow"}
        return {"name": "Breaker", "ok": True, "detail": f"Armed at {state['drop_pct']:g}% a day"}

    def reset(self, raw: dict) -> dict:
        with self.lock:
            params = params_from_dict(raw)
            symbol = str(raw.get("symbol") or self.journal.symbol)
            bars = quotes.load_bars(symbol, refresh=True)
            if params.slow + 1 >= len(bars):
                raise ValueError("The slow average is longer than the market history")
            self.journal = Journal(symbol, params, len(bars))
            save_journal(self.state_path, self.journal)
            return self._guard(view_journal(self.journal), fresh_start=True)

    def step(self, days: int) -> dict:
        if days < 1 or days > 500:
            raise ValueError("Step must be from 1 to 500 days")
        with self.lock:
            bars = quotes.load_bars(self.journal.symbol, refresh=True)
            self.journal.cursor = len(bars)
            save_journal(self.state_path, self.journal)
            return self._guard(view_journal(self.journal))

    def trade(self) -> dict:
        with self.lock:
            bars = quotes.load_bars(self.journal.symbol, refresh=True)
            held = view_journal(self.journal)["shares"] > 0
            side = "sell" if held else "buy"
            if side == "buy" and self.breaker()["tripped"]:
                raise ValueError("The circuit breaker is on. Buys stop until tomorrow. Sells still work.")
            if side == "buy":
                day = bars[-1].date
                scan = scan_report(self._scan_path())
                walking = int(scan.get("total") or 0) > 0 and not bool(scan.get("done"))
                reason = allow_entry(
                    self.journal.symbol,
                    self._picks(self.journal.symbol, bars),
                    self._already_bought(day),
                    buys_on(self._day_path(), day),
                    not walking,
                )
                if reason:
                    raise ValueError(reason)
                remember_day_buy(self._day_path(), self.journal.symbol, day)
            last = bars[-1].close
            price = fill_price(last, side)
            self.journal.close_orders.append((bars[-1].date, side, price))
            save_journal(self.state_path, self.journal)
            payload = self._guard(view_journal(self.journal))
            payload["last_action"] = side
            payload["fill"] = fill_note(side, last, price, self.journal.params.fee_bps)
            return payload

    def run_rest(self) -> dict:
        with self.lock:
            bars = quotes.load_bars(self.journal.symbol, refresh=True)
            self.journal.cursor = len(bars)
            save_journal(self.state_path, self.journal)
            return self._guard(view_journal(self.journal))

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
        remember_focus(self.watch_path, symbol)
        params = params_from_dict(raw)
        return market_view(symbol, params)

    def watch(self, symbols: list) -> None:
        remember_symbols(self.watch_path, symbols)

    def learn_view(self) -> dict:
        with self.lock:
            symbol = self.journal.symbol
        book = book_view(self.learn_path, expect=self.expect_learner)
        try:
            bars = quotes.load_bars(symbol)
        except ValueError:
            bars = []
        rows = self._board(symbol, bars)
        scan = scan_report(self._scan_path())
        book["picks"] = rank_today(rows)
        finished = int(scan.get("total") or 0) > 0 and bool(scan.get("done"))
        book["pick_note"] = pick_note(book["picks"], finished)
        book["leaders"] = rank_leaders(rows)
        book["scan_note"] = scan["note"]
        book["scanned"] = scan["scanned"]
        book["scan_total"] = scan["total"]
        book["scan_on"] = bool(scan.get("on", True))
        return book

    def set_scan(self, on: bool) -> dict:
        remember_scan_switch(self._scan_path(), on)
        return self.learn_view()

    def _day_path(self) -> Path:
        return self.state_path.with_name("day_buys.json")

    def _already_bought(self, day: str) -> bool:
        symbol = self.journal.symbol
        if any(order_day == day and order_side == "buy" for order_day, order_side, *_ in self.journal.close_orders):
            return True
        return bought_on(self._day_path(), symbol, day)

    def _scan_path(self) -> Path:
        return self.state_path.with_name("scan_state.json")

    def _board(self, symbol: str, bars: list) -> list[dict]:
        scan = scan_report(self._scan_path())
        board = dict(scan.get("rows") or {})
        saved = book_view(self.learn_path, expect=self.expect_learner)
        for name, row in dict(saved.get("symbols") or {}).items():
            if isinstance(row, dict):
                board.setdefault(str(name), row)
        if bars and symbol:
            live = score_row(bars, symbol)
            live["scanned_on"] = desk_day()
            board[symbol] = live
        return symbol_rows(board)

    def _picks(self, symbol: str, bars: list) -> list[dict]:
        return rank_today(self._board(symbol, bars))

    def voice(self, symbols: list[str]) -> dict:
        """Daily squeeze warnings for the markets on the desk. Paper only."""
        items: list[dict[str, str | int]] = []
        seen: set[str] = set()
        for raw in symbols:
            symbol = str(raw or "").strip().upper()
            if not symbol or symbol in seen or not _ticker_ok(symbol):
                continue
            if len(seen) >= 24:
                break
            seen.add(symbol)
            try:
                bars = quotes.load_bars(symbol)
            except ValueError as exc:
                items.append({"symbol": symbol, "side": "", "kind": "", "dots": 0, "date": "", "detail": str(exc)})
                continue
            bars = closed_bars(bars, symbol)
            state = squeeze([bar.high for bar in bars], [bar.low for bar in bars], [bar.close for bar in bars])
            alert = squeeze_alert(state["level"], state["momentum"])
            index = int(alert["index"])
            date = bars[index].date if 0 <= index < len(bars) else ""
            detail = _voice_line(symbol, str(alert["side"]), str(alert["kind"]), int(alert["dots"]))
            items.append({
                "symbol": symbol,
                "side": alert["side"],
                "kind": alert["kind"],
                "dots": alert["dots"],
                "date": date,
                "detail": detail,
            })
        return {"frame": "1D", "items": items}

    def _learning_check(self) -> dict[str, str | bool]:
        report = book_view(self.learn_path, expect=self.expect_learner)
        return {"name": "Learning", "ok": bool(report["ok"]), "detail": str(report["detail"])}

    def _mood_check(self, symbol: str, quote_ok: bool) -> dict[str, str | bool]:
        if not quote_ok or not symbol:
            return {"name": "Fear and greed", "ok": False, "detail": "Waiting on the quote"}
        try:
            row = mood(quotes.load_bars(symbol))
        except ValueError as exc:
            return {"name": "Fear and greed", "ok": False, "detail": str(exc)}
        if row["score"] is None:
            return {"name": "Fear and greed", "ok": False, "detail": str(row["note"])}
        return {"name": "Fear and greed", "ok": True, "detail": f"{row['label']} {row['score']}"}


def _voice_line(symbol: str, side: str, kind: str, dots: int) -> str:
    name = symbol[:-4] if symbol.endswith("-USD") else symbol
    if kind == "steam" and side == "sell":
        return f"Sell. {name}. The daily squeeze ran out of steam."
    if kind == "dots" and side == "buy":
        return f"Buy warning. {name}. Five squeeze dots on the daily."
    if kind == "dots" and side == "sell":
        return f"Sell warning. {name}. Five squeeze dots on the daily."
    if dots > 0:
        return f"{name} has {dots} squeeze dots."
    return ""


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
        if method == "GET" and path == "/api/learn":
            self._json(self.desk.learn_view())
            return
        if method == "GET" and path == "/api/voice":
            symbols = [part for part in str(query.get("symbols") or "").split(",")]
            self._json(self.desk.voice(symbols))
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
        if method == "POST" and path == "/api/learn/scan":
            self._json(self.desk.set_scan(bool(body.get("on"))))
            return
        if method == "POST" and path == "/api/learn/watch":
            self.desk.watch(body.get("symbols") or [])
            self._json(self.desk.learn_view())
            return
        if method == "POST" and path == "/api/paper/mode":
            if str(body.get("mode") or "") == "live":
                raise ValueError(LIVE_NOTE)
            if str(body.get("mode") or "") != "paper":
                raise ValueError("This desk only runs in paper mode")
            self._json(self.desk.snapshot())
            return
        if method == "POST" and path == "/api/paper/halt":
            self._json(self.desk.halt())
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


def _edge_commands(url: str, platform: str | None = None) -> list[list[str]]:
    system = platform or sys.platform
    commands: list[list[str]] = []
    if system == "win32":
        roots = [
            os.environ.get("PROGRAMFILES(X86)", r"C:\Program Files (x86)"),
            os.environ.get("PROGRAMFILES", r"C:\Program Files"),
            os.environ.get("LOCALAPPDATA", ""),
        ]
        for root in roots:
            if root:
                commands.append([os.path.join(root, "Microsoft", "Edge", "Application", "msedge.exe"), url])
        commands.append(["msedge", url])
        commands.append(["cmd", "/c", "start", "", "microsoft-edge:" + url])
        return commands
    for name in ("microsoft-edge", "microsoft-edge-stable", "msedge"):
        commands.append([name, url])
    return commands


def _launch_edge(url: str, commands: list[list[str]], which, popen) -> str:
    import webbrowser

    for command in commands:
        program = command[0]
        named = program in {"msedge", "microsoft-edge", "microsoft-edge-stable"}
        if named and which(program) is None:
            continue
        if not named and program != "cmd" and not os.path.isfile(program):
            continue
        try:
            popen(command, close_fds=True, creationflags=_window_flags(program))
        except (OSError, ValueError):
            continue
        return "Microsoft Edge"
    try:
        webbrowser.open(url)
    except OSError:
        return "the default browser. Microsoft Edge was not found"
    return "the default browser. Microsoft Edge was not found"


def _window_flags(program: str, platform: str | None = None) -> int:
    system = platform or sys.platform
    if system == "win32" and program == "cmd":
        return int(getattr(subprocess, "CREATE_NO_WINDOW", 0x08000000))
    return 0


def _wait_for_desk(url: str, probe, sleep, tries: int = 25) -> bool:
    health = url.rstrip("/") + "/api/health"
    for _ in range(tries):
        try:
            if probe(health):
                return True
        except OSError:
            pass
        sleep(0.1)
    return False


def open_edge(url: str) -> str:
    return _launch_edge(url, _edge_commands(url), shutil.which, subprocess.Popen)


def _probe_health(url: str) -> bool:
    from urllib.request import urlopen

    with urlopen(url, timeout=0.4) as response:
        return response.status == 200


def serve(state_path: Path, port: int = 8765, open_browser: bool = True) -> None:
    root = Path(__file__).resolve().parents[1]
    learn_path = state_path.with_name("learn_state.json")
    try:
        server = create_server(state_path, port)
    except OSError as exc:
        busy = getattr(exc, "winerror", None) == 10048 or exc.errno in {98, 10048}
        if not busy:
            raise
        ensure_learner(root, learn_path)
        print("Paper Trader is already open on this computer.")
        print("Close that window, then start Paper Trader again.")
        print("The score keeps running after that window closes. It starts again when you sign in to Windows.")
        return
    ensure_learner(root, learn_path)
    server.desk.expect_learner = True
    try:
        keep_only_running(Path.home(), root)
        shortcuts = write_desktop_opener(root, Path.home())
    except OSError:
        shortcuts = []
    if shortcuts:
        print("Paper Trader.bat is on the desktop.")
    host, bound = server.server_address[:2]
    url = f"http://{host}:{bound}/"
    print(f"Paper Trader is running at {url}")
    print("Market quotes. Paper orders only. Leave this window open.")
    print("The score keeps running after this window closes. It starts again when you sign in to Windows.")
    print("It runs while this computer is on and you are signed in.")
    worker = threading.Thread(target=server.serve_forever, name="paper-trader-http", daemon=True)
    worker.start()
    if open_browser and url.startswith("http://127.0.0.1:"):
        if _wait_for_desk(url, _probe_health, time.sleep):
            print("Opening in " + open_edge(url) + ".")
        else:
            print("The desk did not answer. Open " + url + " in Microsoft Edge.")
    try:
        while worker.is_alive():
            worker.join(0.5)
    except KeyboardInterrupt:
        print("\nStopped.")
        server.shutdown()
        worker.join(2)
    finally:
        server.server_close()
