"""Score closed bars and remember which study matched the next close.

The score uses bars that have already printed. It is not a forecast.
The last bar never receives an arrow. Orders stay on the paper account.

The scorer is its own process. Closing the desk window does not stop it.
On Windows it starts again each time you sign in, and it keeps going while
the computer is on and you are signed in.
"""

from __future__ import annotations

import json
import os
import re
import signal
import subprocess
import sys
import threading
import time
from datetime import datetime, timezone
from pathlib import Path
from zoneinfo import ZoneInfo

from paper_trader.indicators import squeeze, squeeze_alert
from paper_trader.model import Bar
from paper_trader import quotes

SECTORS = ("SMH", "VGT", "ARKW", "QQQ", "SPMO", "COPX", "ARKQ", "MGK", "CIBR", "UGA", "AIRR", "GRID")
WATCH = ("BTC-USD", "ETH-USD", "XRP-USD", "XLM-USD", "SOL-USD", "SPY") + SECTORS
MIN_SEEN = 8
PRECISE_SEEN = 20
PRECISE_RATE = 0.60
PICK_LIMIT = 5
PICK_AIM = 3
PICK_GAP = 5
SCAN_LIMIT = 10
WATCH_LIMIT = 24
PICK_NOTE = "Scan finished. Green signal on each name. Three to five, and no more. Not a forecast."
EMPTY_NOTE = "No market cleared the bar today."
WALK_NOTE = "Ten names, then three to five. Green signals come on when the scan finishes."
SCAN_PAUSE = 0.15
INTERVAL = 60.0
STALE_SECONDS = 180.0
_DENVER = ZoneInfo("America/Denver")
_NEW_YORK = ZoneInfo("America/New_York")
DEFAULT_RULE = "True momentum 5/3 with the candle"
_SYMBOL = re.compile(r"^[A-Za-z0-9.^=-]{1,16}$")
_WATCH_LOCK = threading.Lock()
_ES_CONTINUOUS = 0x80000000
_ES_SYSTEM_REQUIRED = 0x00000001


def _kernel():
    if os.name != "nt":
        return None
    try:
        import ctypes

        return ctypes.windll.kernel32
    except (ImportError, AttributeError, OSError):
        return None


def wall_power(kernel=None) -> bool:
    """True on wall power or when Windows does not say. False only on battery."""
    kernel = kernel if kernel is not None else _kernel()
    if kernel is None:
        return False
    import ctypes

    class _Power(ctypes.Structure):
        _fields_ = [
            ("ACLineStatus", ctypes.c_ubyte),
            ("BatteryFlag", ctypes.c_ubyte),
            ("BatteryLifePercent", ctypes.c_ubyte),
            ("SystemStatusFlag", ctypes.c_ubyte),
            ("BatteryLifeTime", ctypes.c_ulong),
            ("BatteryFullLifeTime", ctypes.c_ulong),
        ]

    status = _Power()
    if not kernel.GetSystemPowerStatus(ctypes.byref(status)):
        return True
    return status.ACLineStatus != 0


def hold_awake(on: bool, kernel=None) -> bool:
    """Ask Windows not to sleep while this thread runs. The screen can still turn off."""
    kernel = kernel if kernel is not None else _kernel()
    if kernel is None:
        return False
    flags = _ES_CONTINUOUS | (_ES_SYSTEM_REQUIRED if on else 0)
    return bool(kernel.SetThreadExecutionState(flags))


def awake_state(kernel=None) -> str:
    kernel = kernel if kernel is not None else _kernel()
    if kernel is None:
        return ""
    if wall_power(kernel):
        return "wall" if hold_awake(True, kernel) else ""
    hold_awake(False, kernel)
    return "battery"


def awake_note(state: str) -> str:
    if state == "wall":
        return " · Keeps this computer awake on wall power"
    if state == "battery":
        return " · On battery, Windows can still sleep"
    return ""


def mood(bars: list[Bar], length: int = 20) -> dict:
    """Fear, neutral, or greed from where the last close sits in the recent range."""
    window = max(5, int(length))
    if len(bars) < window:
        return {"score": None, "label": "Waiting", "note": "Not enough closed bars for a fear or greed score."}
    recent = bars[-window:]
    high = max(bar.high for bar in recent)
    low = min(bar.low for bar in recent)
    span = high - low
    last = recent[-1].close
    position = (last - low) / span if span > 0 else 0.5
    earlier = recent[0].close
    change = (last - earlier) / earlier if earlier else 0.0
    thrust = min(1.0, max(0.0, 0.5 + change / 0.3))
    score = int(round(100 * (0.65 * position + 0.35 * thrust)))
    if score <= 35:
        label = "Fear"
    elif score >= 65:
        label = "Greed"
    else:
        label = "Neutral"
    return {
        "score": score,
        "label": label,
        "note": f"{label} {score}. The last close sits in the recent range. Not a forecast.",
    }


def closed_bars(bars: list[Bar], symbol: str, now: datetime | None = None) -> list[Bar]:
    """Bars whose day is over. Today's bar still moves, so it never makes a signal.

    Crypto days end at midnight UTC. Stocks, ETFs, and futures use the New York date,
    and today's bar waits for the next day so an after-hours print cannot change it.
    """
    if not bars:
        return bars
    moment = now or datetime.now(timezone.utc)
    if moment.tzinfo is None:
        moment = moment.replace(tzinfo=timezone.utc)
    zone = timezone.utc if str(symbol or "").upper().endswith("-USD") else _NEW_YORK
    today = moment.astimezone(zone).date().isoformat()
    return bars[:-1] if bars[-1].date >= today else bars


def buy_setup(bars: list[Bar]) -> str:
    """A daily buy only when five squeeze dots are on and momentum is rising.

    A fade is an exit, so it does not count as a new buy setup.
    """
    if len(bars) < 5:
        return ""
    state = squeeze([bar.high for bar in bars], [bar.low for bar in bars], [bar.close for bar in bars])
    alert = squeeze_alert(state["level"], state["momentum"])
    if alert["kind"] == "dots" and alert["side"] == "buy":
        return "buy"
    return ""


def score_row(bars: list[Bar], symbol: str = "") -> dict:
    """Closed-bar score plus the latest closed bar's setup. Today's moving bar is left out."""
    if symbol:
        bars = closed_bars(bars, symbol)
    lesson = fit(bars)
    return {
        "rule": lesson["rule"],
        "seen": lesson["seen"],
        "matched": lesson["matched"],
        "average": lesson["average"],
        "squeeze": lesson["squeeze"],
        "calc": lesson["calc"],
        "smooth": lesson["smooth"],
        "candle": lesson["candle"],
        "mood": lesson["mood"],
        "as_of": bars[-1].date if bars else "",
        "setup": buy_setup(bars),
    }


def precise_rate(seen: int, matched: int) -> float | None:
    """The past match rate once a reading has enough closed bars. Not a forecast."""
    if seen < PRECISE_SEEN:
        return None
    rate = matched / seen
    if rate < PRECISE_RATE:
        return None
    return rate


def best_picks(qualified: list[dict]) -> list[dict]:
    """Three to five names from the ten. Never more than five.

    The ten are the highest closed-bar scores. Names tied with the best
    score stay. A name within 5 points stays while there is room under five.
    When fewer than three are that close, the next names from the ten fill
    the seats until there are three. Once three seats are filled, a name
    more than 5 points below the best stays off.
    """
    if not qualified:
        return []
    field = qualified[:SCAN_LIMIT]
    best = int(field[0]["rate"])
    kept: list[dict] = []
    seen_names: set[str] = set()
    for item in field:
        symbol = str(item.get("symbol") or "")
        if not symbol or symbol in seen_names:
            continue
        if len(kept) >= PICK_LIMIT:
            break
        rate = int(item["rate"])
        fill = len(kept) < PICK_AIM
        close_enough = best - rate <= PICK_GAP
        if rate == best or fill or close_enough:
            seen_names.add(symbol)
            kept.append(item)
            continue
        break
    return kept


def rank_today(rows: list[dict]) -> list[dict]:
    """The ten highest closed-bar scores, narrowed to three to five."""
    return best_picks(rank_leaders(rows))


def pick_note(picks: list[dict], done: bool = True) -> str:
    if not done:
        return WALK_NOTE
    if picks:
        return PICK_NOTE
    return EMPTY_NOTE


def desk_day(now: datetime | None = None) -> str:
    """The paper desk's calendar day in America/Denver."""
    moment = now or datetime.now(timezone.utc)
    if moment.tzinfo is None:
        moment = moment.replace(tzinfo=timezone.utc)
    return moment.astimezone(_DENVER).date().isoformat()


def _current_rows(rows: list[dict]) -> list[dict]:
    """Rows from the newest scan day. Older saved rows stay out of the board."""
    if any(str(row.get("scanned_on") or "") for row in rows):
        day = max(str(row.get("scanned_on") or "") for row in rows)
        return [row for row in rows if str(row.get("scanned_on") or "") == day]
    dated = [row for row in rows if str(row.get("as_of") or "")]
    if not dated:
        return []
    day = max(str(row.get("as_of") or "") for row in dated)
    return [row for row in dated if str(row.get("as_of") or "") == day]


def rank_leaders(rows: list[dict]) -> list[dict]:
    """The strongest closed-bar scores from the daily scan. At most ten.

    A reading needs 20 closed bars and a match rate of at least 60 percent.
    The number is the past match rate. It is not a forecast.
    """
    qualified: list[dict] = []
    for row in _current_rows(rows):
        symbol = str(row.get("symbol") or "")
        if not symbol:
            continue
        seen = int(row.get("seen") or 0)
        matched = int(row.get("matched") or 0)
        rate = precise_rate(seen, matched)
        if rate is None:
            continue
        qualified.append({
            "symbol": symbol,
            "rate": int(round(rate * 100)),
            "seen": seen,
            "matched": matched,
            "as_of": str(row.get("as_of") or ""),
            "rule": str(row.get("rule") or ""),
            "setup": "buy" if str(row.get("setup") or "") == "buy" else "",
        })
    qualified.sort(key=lambda item: (-int(item["rate"]), -int(item["seen"]), str(item["symbol"])))
    kept: list[dict] = []
    seen_names: set[str] = set()
    for item in qualified:
        if item["symbol"] in seen_names:
            continue
        seen_names.add(item["symbol"])
        kept.append(item)
        if len(kept) >= SCAN_LIMIT:
            break
    return kept


def scan_note(scanned: int, total: int, done: bool, errors: int, leaders: list[dict], last_error: str = "", enabled: bool = True) -> str:
    if not enabled:
        if scanned <= 0:
            return "Scanner is off."
        return f"Scanner is off. Scanned {scanned} of {total}."
    if total <= 0 and scanned <= 0:
        progress = "Daily scan has not started."
    elif done:
        progress = f"Scanned {scanned} of {total}."
    else:
        progress = f"Scanned {scanned} of {total}. The daily scan is still walking the catalog."
    if leaders:
        score = "Highest closed-bar scores. Not a forecast."
    else:
        score = "No market cleared the bar yet. Not a forecast."
    if last_error and not done:
        return f"{progress} {last_error}"
    extra = f" {errors} quotes did not answer." if errors else ""
    return f"{progress} {score}{extra}"


def symbol_rows(symbols: dict) -> list[dict]:
    rows: list[dict] = []
    for name, row in symbols.items():
        if not isinstance(row, dict):
            continue
        item = dict(row)
        item["symbol"] = str(name)
        rows.append(item)
    return rows


def scan_fields(lesson: dict, day: str) -> dict:
    return {
        "rule": str(lesson.get("rule") or ""),
        "seen": int(lesson.get("seen") or 0),
        "matched": int(lesson.get("matched") or 0),
        "as_of": str(lesson.get("as_of") or ""),
        "setup": "buy" if lesson.get("setup") == "buy" else "",
        "scanned_on": day,
    }


def _switch_path(scan_path: Path) -> Path:
    return scan_path.with_name("scan_switch.json")


def scan_is_on(scan_path: Path) -> bool:
    path = _switch_path(scan_path)
    if not path.is_file():
        return True
    try:
        raw = json.loads(path.read_text(encoding="utf-8"))
    except (OSError, json.JSONDecodeError):
        return True
    if not isinstance(raw, dict) or "on" not in raw:
        return True
    return bool(raw.get("on"))


def remember_scan_switch(scan_path: Path, on: bool) -> None:
    _store(_switch_path(scan_path), {"on": bool(on)})


def scan_report(path: Path) -> dict:
    raw = _load_scan(path)
    rows = raw.get("rows") if isinstance(raw.get("rows"), dict) else {}
    leaders = rank_leaders(symbol_rows(rows))
    scanned = int(raw.get("cursor") or 0)
    total = int(raw.get("total") or 0)
    done = bool(raw.get("done"))
    errors = int(raw.get("errors") or 0)
    last_error = str(raw.get("last_error") or "")
    enabled = scan_is_on(path)
    return {
        "leaders": leaders,
        "rows": rows,
        "scanned": scanned,
        "total": total,
        "done": done,
        "errors": errors,
        "last_error": last_error,
        "on": enabled,
        "note": scan_note(scanned, total, done, errors, leaders, last_error, enabled),
    }


def allow_entry(symbol: str, picks: list[dict], bought_today: bool, buys_today: int = 0, scan_done: bool = True) -> str:
    """A new paper buy needs a green seat after the scan, with at most five new buys a day."""
    if not scan_done:
        return "The scan is still walking. Green signals come on when it finishes."
    if int(buys_today) >= PICK_LIMIT:
        return "Today already has five paper buys."
    names = {str(row.get("symbol") or "") for row in picks}
    if symbol not in names:
        return "This market is not in today's best."
    if bought_today:
        return "This market already has a paper buy today."
    return ""


def buys_on(path: Path, day: str) -> int:
    raw = _load_day(path)
    if str(raw.get("date") or "") != day:
        return 0
    return len({str(item) for item in list(raw.get("symbols") or []) if str(item)})


def bought_on(path: Path, symbol: str, day: str) -> bool:
    raw = _load_day(path)
    if str(raw.get("date") or "") != day:
        return False
    return symbol in [str(item) for item in list(raw.get("symbols") or [])]


def remember_day_buy(path: Path, symbol: str, day: str) -> None:
    raw = _load_day(path)
    if str(raw.get("date") or "") == day:
        symbols = [str(item) for item in list(raw.get("symbols") or [])]
    else:
        symbols = []
    if symbol not in symbols:
        symbols.append(symbol)
    _store(path, {"date": day, "symbols": symbols})


def choose(book: dict[str, dict[str, int]], default: str) -> tuple[str, dict[str, int]]:
    """Pick the reading with the highest next-close match rate.

    A reading needs at least eight closed bars and a rate above one half.
    Otherwise the desk keeps the default.
    """
    winner = default
    best_rate = -1.0
    best_seen = -1
    for name, row in book.items():
        seen = int(row.get("seen") or 0)
        matched = int(row.get("matched") or 0)
        if seen < MIN_SEEN:
            continue
        rate = matched / seen
        if rate <= 0.5:
            continue
        if rate > best_rate or (rate == best_rate and seen > best_seen):
            winner = name
            best_rate = rate
            best_seen = seen
    row = book.get(winner) or {"seen": 0, "matched": 0}
    return winner, {"seen": int(row.get("seen") or 0), "matched": int(row.get("matched") or 0)}


def fit(bars: list[Bar]) -> dict:
    count = len(bars)
    signals = _signals(bars) if count else {}
    book = {name: {"seen": 0, "matched": 0} for name in signals}
    choice: list[str] = []
    for index in range(count):
        rule, _row = choose(book, DEFAULT_RULE)
        choice.append(rule)
        if index < 1:
            continue
        earlier = index - 1
        move = bars[index].close - bars[earlier].close
        for name, (up, down) in signals.items():
            if up[earlier] and not down[earlier]:
                side = 1
            elif down[earlier] and not up[earlier]:
                side = -1
            else:
                continue
            book[name]["seen"] += 1
            if (side > 0 and move > 0) or (side < 0 and move < 0):
                book[name]["matched"] += 1
    rule, row = choose(book, DEFAULT_RULE)
    tmo_name, _tmo_row = choose(_family(book, "True momentum"), "True momentum 5/3 with the candle")
    squeeze_name, _squeeze_row = choose(_family(book, "Squeeze"), "Squeeze 20")
    average_name, _average_row = choose(_family(book, "Average"), "Average 34")
    tmo = _parse(tmo_name)
    squeeze_choice = _parse(squeeze_name)
    average = _parse(average_name)
    arrows: list[dict[str, str]] = []
    if signals:
        for index in range(max(0, count - 1)):
            up, down = signals[choice[index]]
            if up[index] and not down[index]:
                arrows.append({"date": bars[index].date, "side": "buy"})
            elif down[index] and not up[index]:
                arrows.append({"date": bars[index].date, "side": "sell"})
    seen = row["seen"]
    matched = row["matched"]
    return {
        "rule": rule,
        "seen": seen,
        "matched": matched,
        "calc": int(tmo.get("calc", 5)),
        "smooth": int(tmo.get("smooth", 3)),
        "squeeze": int(squeeze_choice.get("squeeze", 20)),
        "average": int(average.get("average", 34)),
        "candle": rule.endswith(" with the candle"),
        "arrows": arrows,
        "mood": mood(bars),
        "note": f"{matched} of {seen} next closes matched {rule}. Score of bars already printed. Not a forecast.",
    }


def startup_folder() -> Path | None:
    """The Windows sign-in folder. Empty on any other system."""
    if os.name != "nt":
        return None
    appdata = os.environ.get("APPDATA")
    if not appdata:
        return None
    return Path(appdata) / "Microsoft" / "Windows" / "Start Menu" / "Programs" / "Startup"


def startup_script(python: Path, launch: Path) -> str:
    """Sign-in launch in a normal window named Paper Trader Learn.

    It does not hide the window, and it does not change firewall, registry, or exclusions.
    """
    root = launch.parent
    return (
        "@echo off\r\n"
        f'cd /d "{root}"\r\n'
        "title Paper Trader Learn\r\n"
        f'start "Paper Trader Learn" "{python}" "{launch}" learn\r\n'
    )


def write_startup(root: Path, folder: Path | None = None) -> Path | None:
    destination = folder if folder is not None else startup_folder()
    if destination is None:
        return None
    python = root / "runtime" / "python.exe"
    program = python if python.is_file() else Path(sys.executable)
    try:
        destination.mkdir(parents=True, exist_ok=True)
        hidden = destination / "Paper Trader Learn.vbs"
        if hidden.is_file():
            hidden.unlink()
        path = destination / "Paper Trader Learn.bat"
        path.write_text(startup_script(program, root / "launch.py"), encoding="utf-8")
    except OSError:
        return None
    return path


def read_watch(path: Path) -> tuple[str, tuple[str, ...] | None]:
    if not path.is_file():
        return "", None
    try:
        raw = json.loads(path.read_text(encoding="utf-8"))
    except (OSError, json.JSONDecodeError):
        return "", None
    if not isinstance(raw, dict):
        return "", None
    focus = raw.get("focus") if isinstance(raw.get("focus"), str) else ""
    if focus and not _SYMBOL.fullmatch(focus):
        focus = ""
    symbols = raw.get("symbols")
    if not isinstance(symbols, list):
        return focus, None
    cleaned: list[str] = []
    for symbol in symbols:
        if isinstance(symbol, str) and _SYMBOL.fullmatch(symbol) and symbol not in cleaned:
            cleaned.append(symbol)
        if len(cleaned) >= WATCH_LIMIT:
            break
    return focus, tuple(cleaned)


def write_watch(path: Path, focus: str, symbols: tuple[str, ...] | None) -> None:
    current_focus, current_symbols = read_watch(path)
    payload = {
        "focus": focus or current_focus or "BTC-USD",
        "symbols": list(symbols if symbols is not None else (current_symbols or ())),
    }
    _store(path, payload)


def remember_focus(path: Path, symbol: str) -> None:
    if not _SYMBOL.fullmatch(symbol or ""):
        return
    with _WATCH_LOCK:
        _focus, extra = read_watch(path)
        write_watch(path, symbol, extra)


def remember_symbols(path: Path, symbols: list) -> None:
    cleaned: list[str] = []
    for symbol in list(symbols or []):
        if not isinstance(symbol, str) or not _SYMBOL.fullmatch(symbol):
            continue
        if symbol not in cleaned:
            cleaned.append(symbol)
        if len(cleaned) >= WATCH_LIMIT:
            break
    with _WATCH_LOCK:
        focus, _extra = read_watch(path)
        write_watch(path, focus, tuple(cleaned))


def _prepare_lock_file(fd: int) -> None:
    if os.lseek(fd, 0, os.SEEK_END) < 1:
        os.write(fd, b"\0")


def _try_lock(fd: int) -> bool:
    try:
        if os.name == "nt":
            import msvcrt

            os.lseek(fd, 0, os.SEEK_SET)
            msvcrt.locking(fd, msvcrt.LK_NBLCK, 1)
            return True
        import fcntl

        fcntl.flock(fd, fcntl.LOCK_EX | fcntl.LOCK_NB)
        return True
    except OSError:
        return False


def _unlock(fd: int) -> None:
    try:
        if os.name == "nt":
            import msvcrt

            os.lseek(fd, 0, os.SEEK_SET)
            msvcrt.locking(fd, msvcrt.LK_UNLCK, 1)
            return
        import fcntl

        fcntl.flock(fd, fcntl.LOCK_UN)
    except OSError:
        return


def _write_pid(fd: int, pid: int) -> None:
    os.lseek(fd, 1, os.SEEK_SET)
    os.write(fd, f"{pid}\n".encode("ascii"))


def _read_pid(fd: int) -> int | None:
    try:
        os.lseek(fd, 1, os.SEEK_SET)
        raw = os.read(fd, 32)
        return int(raw.split()[0])
    except (OSError, ValueError, IndexError):
        return None


def lock_holder(lock_path: Path) -> int | None:
    """The scorer's process id while its lock is held. None when it is not running."""
    if not lock_path.is_file():
        return None
    try:
        fd = os.open(str(lock_path), os.O_RDWR)
    except OSError:
        return None
    try:
        _prepare_lock_file(fd)
        if _try_lock(fd):
            _unlock(fd)
            return None
        return _read_pid(fd)
    finally:
        os.close(fd)


def _fresh(saved: dict, now: float) -> bool:
    try:
        updated = float(saved.get("updated") or 0)
    except (TypeError, ValueError):
        return False
    if updated <= 0:
        return False
    return (now - updated) <= STALE_SECONDS


def learner_healthy(state_path: Path, now: float | None = None) -> bool:
    holder = lock_holder(state_path.with_name("learn.lock"))
    if not holder:
        return False
    saved = _load(state_path)
    try:
        pid = int(saved.get("pid") or 0)
    except (TypeError, ValueError):
        return False
    return holder == pid and _fresh(saved, time.time() if now is None else now)


def learning_report(state_path: Path, *, expect: bool, now: float | None = None) -> dict[str, str | bool]:
    """Green only while the scorer process is alive and its heartbeat is fresh."""
    saved = _load(state_path)
    moment = time.time() if now is None else now
    holder = lock_holder(state_path.with_name("learn.lock"))
    fresh = _fresh(saved, moment)
    try:
        pid = int(saved.get("pid") or 0)
    except (TypeError, ValueError):
        pid = 0
    running = bool(holder) and holder == pid and fresh
    error = str(saved.get("last_error") or "")
    symbol = str(saved.get("last_symbol") or "")
    passes = int(saved.get("passes") or 0)
    row = dict((saved.get("symbols") or {}).get(symbol) or {})
    awake = awake_note(str(saved.get("awake") or ""))
    if running and error:
        return {"ok": False, "running": True, "detail": error}
    if running and passes < 1:
        return {"ok": True, "running": True, "detail": "All day, all week" + awake}
    if running:
        tone = row.get("mood") or {}
        label = str(tone.get("label") or "")
        score = tone.get("score")
        mood_bit = f" · {label} {score}" if label and score is not None and label != "Waiting" else ""
        return {"ok": True, "running": True, "detail": f"All day, all week · Watching {symbol}{mood_bit}{awake}"}
    if holder and not fresh:
        return {"ok": False, "running": False, "detail": "The all-day score stopped answering"}
    if expect and error:
        return {"ok": False, "running": False, "detail": error}
    if expect:
        return {"ok": False, "running": False, "detail": "The all-day score is not running"}
    return {
        "ok": True,
        "running": False,
        "detail": "Starts when you open the desk, then keeps scoring after that window closes",
    }


def book_view(state_path: Path, *, expect: bool) -> dict:
    saved = _load(state_path)
    report = learning_report(state_path, expect=expect)
    return {
        "running": bool(report["running"]),
        "passes": int(saved.get("passes") or 0),
        "last_symbol": str(saved.get("last_symbol") or ""),
        "last_error": str(saved.get("last_error") or ""),
        "symbols": json.loads(json.dumps(saved.get("symbols") or {})),
        "ok": bool(report["ok"]),
        "detail": str(report["detail"]),
    }


def _terminate(pid: int) -> None:
    try:
        os.kill(pid, signal.SIGTERM)
    except OSError:
        return


def stop_learner(state_path: Path, killer=None) -> int | None:
    """Stop the scorer recorded in this folder. Leave every other process alone."""
    lock_path = state_path.with_name("learn.lock")
    pid = lock_holder(lock_path)
    if not pid or pid == os.getpid():
        return None
    end = killer or _terminate
    end(pid)
    deadline = time.monotonic() + 2
    while time.monotonic() < deadline:
        if lock_holder(lock_path) != pid:
            return pid
        time.sleep(0.05)
    end(pid)
    return pid


def stop_learners(home: Path, source: Path | None = None, killer=None) -> list[int]:
    from paper_trader.install import desk_folders

    folders = list(desk_folders(home))
    if source is not None:
        folders.append(source)
    stopped: list[int] = []
    seen: set[Path] = set()
    for folder in folders:
        resolved = folder.resolve()
        if resolved in seen:
            continue
        seen.add(resolved)
        pid = stop_learner(resolved / "learn_state.json", killer=killer)
        if pid:
            stopped.append(pid)
    return stopped


def learner_command(root: Path, windows: bool | None = None) -> list[str]:
    """The score's launch command. On Windows the window is named Paper Trader Learn."""
    python = root / "runtime" / "python.exe"
    program = str(python if python.is_file() else Path(sys.executable))
    launch = str(root / "launch.py")
    if os.name == "nt" if windows is None else windows:
        return ["cmd", "/c", "start", "Paper Trader Learn", program, launch, "learn"]
    return [program, launch, "learn"]


def spawn_learner(root: Path) -> None:
    command = learner_command(root)
    options: dict = {
        "cwd": str(root),
        "stdin": subprocess.DEVNULL,
        "stdout": subprocess.DEVNULL,
        "stderr": subprocess.DEVNULL,
        "close_fds": True,
    }
    if os.name != "nt":
        options["start_new_session"] = True
    subprocess.Popen(command, **options)


def ensure_learner(root: Path, state_path: Path, spawn=None, wait: float = 2.0) -> bool:
    """Start the scorer when it is not already running. Leave a live one alone."""
    write_startup(root)
    if learner_healthy(state_path):
        return True
    if lock_holder(state_path.with_name("learn.lock")):
        return False
    start = spawn or (lambda: spawn_learner(root))
    try:
        start()
    except OSError:
        return False
    deadline = time.monotonic() + wait
    while time.monotonic() < deadline:
        if learner_healthy(state_path):
            return True
        time.sleep(0.05)
    return learner_healthy(state_path)


def run_learner(state_path: Path) -> int:
    watcher = Watcher(state_path)
    if not watcher.acquire():
        return 0
    try:
        watcher.run_forever()
    except KeyboardInterrupt:
        return 0
    finally:
        watcher.release()
    return 0


class Watcher:
    """Score a small book of markets until this process is stopped."""

    def __init__(self, path: Path) -> None:
        self.path = path
        self.watch_path = path.with_name("learn_watch.json")
        self.scan_path = path.with_name("scan_state.json")
        self.lock_path = path.with_name("learn.lock")
        self._names_day = ""
        self._names_list: list[str] = []
        self._scan = _load_scan(self.scan_path)
        self._lock = threading.Lock()
        self._stop = threading.Event()
        self._focus = "BTC-USD"
        self._extra: tuple[str, ...] = ()
        self._saved = _load(path)
        self._lock_fd: int | None = None
        self._kernel = _kernel()
        self._awake = ""

    def acquire(self) -> bool:
        self.path.parent.mkdir(parents=True, exist_ok=True)
        fd = os.open(str(self.lock_path), os.O_RDWR | os.O_CREAT, 0o644)
        _prepare_lock_file(fd)
        if not _try_lock(fd):
            os.close(fd)
            return False
        _write_pid(fd, os.getpid())
        self._lock_fd = fd
        return True

    def release(self) -> None:
        hold_awake(False, self._kernel)
        self._awake = ""
        with self._lock:
            self._saved["awake"] = ""
            if int(self._saved.get("pid") or 0) == os.getpid():
                self._saved["pid"] = 0
                self._saved["updated"] = time.time()
                _store(self.path, self._saved)
        if self._lock_fd is None:
            return
        _unlock(self._lock_fd)
        os.close(self._lock_fd)
        self._lock_fd = None

    def run_forever(self) -> None:
        self._beat()
        self._loop()

    def _beat(self) -> None:
        with self._lock:
            self._saved["pid"] = os.getpid()
            self._saved["updated"] = time.time()
            self._saved["awake"] = self._awake
            _store(self.path, self._saved)

    def _keep_awake(self) -> None:
        if self._kernel is None:
            return
        try:
            self._awake = awake_state(self._kernel)
        except (OSError, ValueError, AttributeError):
            self._awake = ""

    def _loop(self) -> None:
        while not self._stop.is_set():
            self._keep_awake()
            self._pass()
            if self._stop.is_set():
                return
            gap = 2.0 if int(self._saved.get("passes") or 0) <= len(WATCH) else INTERVAL
            self._scan_until(time.monotonic() + gap)

    def _pass(self) -> None:
        try:
            self._once()
        except (OSError, ValueError) as exc:
            self._fail(str(exc) or "The quote feed did not answer.")
        except Exception as exc:
            self._fail(str(exc) or "The score stopped on this market.")

    def _targets(self) -> tuple[str, tuple[str, ...], int]:
        focus, extra = read_watch(self.watch_path)
        with self._lock:
            if focus:
                self._focus = focus
            if extra is not None:
                self._extra = extra
            cursor = int(self._saved.get("cursor") or 0)
            return self._focus, self._extra, cursor

    def _once(self) -> None:
        focus, extra, cursor = self._targets()
        queue = list(WATCH)
        for symbol in (focus,) + extra:
            if symbol and symbol not in queue:
                queue.append(symbol)
        symbol = queue[cursor % len(queue)]
        try:
            bars = quotes.load_bars(symbol, refresh=True)
            lesson = score_row(bars, symbol)
        except ValueError as exc:
            self._fail(str(exc), symbol, cursor + 1)
            return
        summary = {
            "rule": lesson["rule"],
            "seen": lesson["seen"],
            "matched": lesson["matched"],
            "average": lesson["average"],
            "squeeze": lesson["squeeze"],
            "calc": lesson["calc"],
            "smooth": lesson["smooth"],
            "candle": lesson["candle"],
            "mood": lesson["mood"],
            "as_of": lesson["as_of"],
            "setup": lesson["setup"],
        }
        with self._lock:
            symbols = dict(self._saved.get("symbols") or {})
            symbols[symbol] = summary
            self._saved = {
                "version": 1,
                "passes": int(self._saved.get("passes") or 0) + 1,
                "cursor": cursor + 1,
                "last_error": "",
                "last_symbol": symbol,
                "pid": os.getpid(),
                "updated": time.time(),
                "awake": self._awake,
                "symbols": symbols,
            }
            _store(self.path, self._saved)

    def _catalog_names(self) -> list[str]:
        day = desk_day()
        if self._names_day != day or not self._names_list:
            from paper_trader.samples import all_symbols

            self._names_list = all_symbols()
            self._names_day = day
        return self._names_list

    def _scan_until(self, deadline: float) -> None:
        while time.monotonic() < deadline and not self._stop.is_set():
            if self._scan_finished():
                return
            pause = self._scan_one()
            if self._stop.wait(pause):
                return

    def _scan_finished(self) -> bool:
        raw = self._scan
        return bool(raw.get("done")) and str(raw.get("day") or "") == desk_day()

    def _scan_one(self) -> float:
        """Score the next catalog market. Returns how long to wait before the next one."""
        if not scan_is_on(self.scan_path):
            return 2.0
        day = desk_day()
        names = self._catalog_names()
        total = len(names)
        raw = self._scan
        if str(raw.get("day") or "") != day:
            raw = _empty_scan(day)
        cursor = int(raw.get("cursor") or 0)
        if total < 1 or cursor >= total:
            raw["done"] = True
            raw["day"] = day
            raw["total"] = total
            raw["cursor"] = cursor
            raw["updated"] = time.time()
            self._scan = raw
            _store(self.scan_path, raw)
            self._beat()
            return SCAN_PAUSE
        symbol = names[cursor]
        row = None
        stall = False
        message = ""
        try:
            bars = quotes.load_bars(symbol, refresh=False)
            row = scan_fields(score_row(bars, symbol), day)
        except ValueError as exc:
            message = str(exc) or "The quote feed did not answer."
            stall = "did not answer" in message or "unexpected response" in message
        if stall:
            raw["last_error"] = message
            raw["total"] = total
            raw["updated"] = time.time()
            raw["day"] = day
            self._scan = raw
            _store(self.scan_path, raw)
            self._beat()
            return 5.0
        rows = dict(raw.get("rows") or {})
        if row:
            rows[symbol] = row
            raw["last_error"] = ""
        else:
            raw["errors"] = int(raw.get("errors") or 0) + 1
            raw["last_error"] = ""
        raw["rows"] = rows
        raw["cursor"] = cursor + 1
        raw["total"] = total
        raw["done"] = raw["cursor"] >= total
        raw["day"] = day
        raw["updated"] = time.time()
        self._scan = raw
        if raw["done"] or raw["cursor"] <= 5 or raw["cursor"] % 25 == 0:
            _store(self.scan_path, raw)
        self._beat()
        return SCAN_PAUSE

    def _fail(self, message: str, symbol: str | None = None, cursor: int | None = None) -> None:
        with self._lock:
            self._saved["last_error"] = message
            self._saved["pid"] = os.getpid()
            self._saved["updated"] = time.time()
            if symbol:
                self._saved["last_symbol"] = symbol
            if cursor is not None:
                self._saved["cursor"] = cursor
            _store(self.path, self._saved)


def _family(book: dict[str, dict[str, int]], prefix: str) -> dict[str, dict[str, int]]:
    return {name: row for name, row in book.items() if name.startswith(prefix)}


def _parse(name: str) -> dict[str, int | bool]:
    candle = name.endswith(" with the candle")
    parts = name.split()
    if name.startswith("True momentum") and len(parts) >= 3 and "/" in parts[2]:
        calc, smooth = parts[2].split("/")
        return {"calc": int(calc), "smooth": int(smooth), "candle": candle}
    if name.startswith("Squeeze") and len(parts) >= 2 and parts[1].isdigit():
        return {"squeeze": int(parts[1]), "candle": candle}
    if name.startswith("Average") and len(parts) >= 2 and parts[1].isdigit():
        return {"average": int(parts[1]), "candle": candle}
    return {"candle": candle}


def _ema(values: list[float], length: int) -> list[float]:
    alpha = 2.0 / (length + 1.0)
    out: list[float] = []
    previous: float | None = None
    for value in values:
        previous = value if previous is None else value * alpha + previous * (1.0 - alpha)
        out.append(previous)
    return out


def _tmo(bars: list[Bar], length: int, calc: int, smooth: int) -> tuple[list[bool], list[bool]]:
    count = len(bars)
    data: list[float] = []
    for index, bar in enumerate(bars):
        score = 0
        for back in range(length):
            open_ = bars[max(0, index - back)].open
            if bar.close > open_:
                score += 1
            elif bar.close < open_:
                score -= 1
        data.append(float(score))
    main = _ema(_ema(data, calc), smooth)
    signal = _ema(main, smooth)
    up = [False] * count
    down = [False] * count
    for index in range(2, count):
        if main[index - 1] > signal[index - 1] and main[index - 2] <= signal[index - 2]:
            up[index - 1] = True
        if main[index - 1] < signal[index - 1] and main[index - 2] >= signal[index - 2]:
            down[index - 1] = True
    return up, down


def _average_marks(bars: list[Bar], length: int) -> tuple[list[bool], list[bool]]:
    highs = _ema([bar.high for bar in bars], length)
    lows = _ema([bar.low for bar in bars], length)
    up = [False] * len(bars)
    down = [False] * len(bars)
    for index in range(1, len(bars)):
        if bars[index].close > highs[index] and bars[index - 1].close <= highs[index - 1]:
            up[index] = True
        if bars[index].close < lows[index] and bars[index - 1].close >= lows[index - 1]:
            down[index] = True
    return up, down


def _squeeze_marks(bars: list[Bar], length: int) -> tuple[list[bool], list[bool]]:
    state = squeeze([bar.high for bar in bars], [bar.low for bar in bars], [bar.close for bar in bars], length)
    momentum = state["momentum"]
    level = state["level"]
    up = [False] * len(bars)
    down = [False] * len(bars)
    for index in range(1, len(bars)):
        now = momentum[index]
        previous = momentum[index - 1]
        if now is None or previous is None:
            continue
        released = level[index - 1] > 0 and level[index] == 0
        if released and now > 0 and now > previous:
            up[index] = True
        if previous >= 0 and now < 0:
            down[index] = True
        elif released and now < 0 and now < previous:
            down[index] = True
    return up, down


def _with_candle(up: list[bool], down: list[bool], bars: list[Bar]) -> tuple[list[bool], list[bool]]:
    filtered_up: list[bool] = []
    filtered_down: list[bool] = []
    for index, bar in enumerate(bars):
        green = bar.close >= bar.open
        filtered_up.append(bool(up[index] and green))
        filtered_down.append(bool(down[index] and not green))
    return filtered_up, filtered_down


def _signals(bars: list[Bar]) -> dict[str, tuple[list[bool], list[bool]]]:
    signals: dict[str, tuple[list[bool], list[bool]]] = {}

    def add(name: str, pair: tuple[list[bool], list[bool]]) -> None:
        signals[name] = pair

    base_up, base_down = _tmo(bars, 14, 5, 3)
    add("True momentum 5/3", (base_up, base_down))
    add("True momentum 5/3 with the candle", _with_candle(base_up, base_down, bars))
    for calc in (3, 5, 8):
        for smooth in (2, 3, 5):
            if calc == 5 and smooth == 3:
                continue
            up, down = _tmo(bars, 14, calc, smooth)
            name = f"True momentum {calc}/{smooth}"
            add(name, (up, down))
            add(name + " with the candle", _with_candle(up, down, bars))
    for length in (20, 14, 30):
        up, down = _squeeze_marks(bars, length)
        name = f"Squeeze {length}"
        add(name, (up, down))
        add(name + " with the candle", _with_candle(up, down, bars))
    for length in (34, 21, 55):
        up, down = _average_marks(bars, length)
        name = f"Average {length}"
        add(name, (up, down))
        add(name + " with the candle", _with_candle(up, down, bars))
    return signals


def _empty_scan(day: str) -> dict:
    return {
        "day": day,
        "cursor": 0,
        "total": 0,
        "done": False,
        "errors": 0,
        "last_error": "",
        "updated": 0,
        "rows": {},
    }


def _load_scan(path: Path) -> dict:
    if not path.is_file():
        return _empty_scan("")
    try:
        raw = json.loads(path.read_text(encoding="utf-8"))
    except (OSError, json.JSONDecodeError):
        return _empty_scan("")
    if not isinstance(raw, dict):
        return _empty_scan("")
    if not isinstance(raw.get("rows"), dict):
        raw["rows"] = {}
    return raw


def _load_day(path: Path) -> dict:
    if not path.is_file():
        return {"date": "", "symbols": []}
    try:
        raw = json.loads(path.read_text(encoding="utf-8"))
    except (OSError, json.JSONDecodeError):
        return {"date": "", "symbols": []}
    if not isinstance(raw, dict):
        return {"date": "", "symbols": []}
    return raw


def _load(path: Path) -> dict:
    if not path.is_file():
        return _empty_book()
    try:
        raw = json.loads(path.read_text(encoding="utf-8"))
    except (OSError, json.JSONDecodeError):
        return _empty_book()
    if not isinstance(raw, dict):
        return _empty_book()
    raw.setdefault("symbols", {})
    raw.setdefault("passes", 0)
    raw.setdefault("cursor", 0)
    raw.setdefault("last_error", "")
    raw.setdefault("last_symbol", "")
    raw.setdefault("pid", 0)
    raw.setdefault("updated", 0)
    return raw


def _empty_book() -> dict:
    return {
        "version": 1,
        "passes": 0,
        "cursor": 0,
        "last_error": "",
        "last_symbol": "",
        "pid": 0,
        "updated": 0,
        "symbols": {},
    }


def _store(path: Path, payload: dict) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    temporary = path.with_suffix(path.suffix + ".tmp")
    temporary.write_text(json.dumps(payload, indent=2) + "\n", encoding="utf-8")
    temporary.replace(path)
