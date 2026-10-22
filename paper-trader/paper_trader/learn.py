"""Score closed bars and remember which study matched the next close.

The score uses bars that have already printed. It is not a forecast.
The last bar never receives an arrow. Orders stay on the paper account.
"""

from __future__ import annotations

import json
import re
import threading
from pathlib import Path

from paper_trader.indicators import squeeze
from paper_trader.model import Bar
from paper_trader import quotes

WATCH = ("BTC-USD", "ETH-USD", "XRP-USD", "XLM-USD", "SOL-USD", "SPY", "QQQ")
MIN_SEEN = 8
INTERVAL = 60.0
DEFAULT_RULE = "True momentum 5/3 with the candle"
_SYMBOL = re.compile(r"^[A-Za-z0-9.^=-]{1,16}$")


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


class Watcher:
    """Keep scoring a small book of markets while the desk window stays open."""

    def __init__(self, path: Path) -> None:
        self.path = path
        self._lock = threading.Lock()
        self._stop = threading.Event()
        self._thread: threading.Thread | None = None
        self._focus = "BTC-USD"
        self._extra: tuple[str, ...] = ()
        self._saved = _load(path)

    def focus(self, symbol: str) -> None:
        if _SYMBOL.fullmatch(symbol or ""):
            with self._lock:
                self._focus = symbol

    def watch(self, symbols: list) -> None:
        extra: list[str] = []
        for symbol in list(symbols or []):
            if not isinstance(symbol, str) or not _SYMBOL.fullmatch(symbol):
                continue
            if symbol not in extra:
                extra.append(symbol)
            if len(extra) >= 12:
                break
        with self._lock:
            self._extra = tuple(extra)

    def start(self) -> None:
        if self._thread is not None and self._thread.is_alive():
            return
        self._stop.clear()
        self._thread = threading.Thread(target=self._loop, name="paper-trader-learn", daemon=True)
        self._thread.start()

    def stop(self) -> None:
        self._stop.set()

    def running(self) -> bool:
        return self._thread is not None and self._thread.is_alive()

    def status(self) -> dict[str, str | bool]:
        with self._lock:
            error = str(self._saved.get("last_error") or "")
            symbol = str(self._saved.get("last_symbol") or "")
            passes = int(self._saved.get("passes") or 0)
            row = dict((self._saved.get("symbols") or {}).get(symbol) or {})
        if not self.running():
            return {"ok": True, "detail": "Starts when the desk window is open"}
        if error and passes < 1:
            return {"ok": False, "detail": error}
        if error:
            return {"ok": False, "detail": error}
        if passes < 1:
            return {"ok": True, "detail": "Watching closed bars"}
        tone = row.get("mood") or {}
        label = str(tone.get("label") or "")
        score = tone.get("score")
        mood_bit = f" · {label} {score}" if label and score is not None and label != "Waiting" else ""
        return {"ok": True, "detail": f"Watching {symbol}{mood_bit}"}

    def view(self) -> dict:
        with self._lock:
            symbols = json.loads(json.dumps(self._saved.get("symbols") or {}))
            payload = {
                "running": self.running(),
                "passes": int(self._saved.get("passes") or 0),
                "last_symbol": str(self._saved.get("last_symbol") or ""),
                "last_error": str(self._saved.get("last_error") or ""),
                "symbols": symbols,
            }
        report = self.status()
        payload["ok"] = bool(report["ok"])
        payload["detail"] = str(report["detail"])
        return payload

    def _loop(self) -> None:
        for _ in range(len(WATCH)):
            if self._stop.is_set():
                return
            try:
                self._once()
            except (OSError, ValueError) as exc:
                self._fail(str(exc) or "The quote feed did not answer.")
            if self._stop.wait(2):
                return
        while not self._stop.is_set():
            try:
                self._once()
            except (OSError, ValueError) as exc:
                self._fail(str(exc) or "The quote feed did not answer.")
            if self._stop.wait(INTERVAL):
                return

    def _once(self) -> None:
        with self._lock:
            focus = self._focus
            extra = self._extra
            cursor = int(self._saved.get("cursor") or 0)
        queue = list(WATCH)
        for symbol in (focus,) + extra:
            if symbol and symbol not in queue:
                queue.append(symbol)
        symbol = queue[cursor % len(queue)]
        try:
            bars = quotes.load_bars(symbol, refresh=True)
            lesson = fit(bars)
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
            "as_of": bars[-1].date,
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
                "symbols": symbols,
            }
            _store(self.path, self._saved)

    def _fail(self, message: str, symbol: str | None = None, cursor: int | None = None) -> None:
        with self._lock:
            self._saved["last_error"] = message
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


def _load(path: Path) -> dict:
    if not path.is_file():
        return {"version": 1, "passes": 0, "cursor": 0, "last_error": "", "last_symbol": "", "symbols": {}}
    try:
        raw = json.loads(path.read_text(encoding="utf-8"))
    except (OSError, json.JSONDecodeError):
        return {"version": 1, "passes": 0, "cursor": 0, "last_error": "", "last_symbol": "", "symbols": {}}
    if not isinstance(raw, dict):
        return {"version": 1, "passes": 0, "cursor": 0, "last_error": "", "last_symbol": "", "symbols": {}}
    raw.setdefault("symbols", {})
    raw.setdefault("passes", 0)
    raw.setdefault("cursor", 0)
    raw.setdefault("last_error", "")
    raw.setdefault("last_symbol", "")
    return raw


def _store(path: Path, payload: dict) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    temporary = path.with_suffix(path.suffix + ".tmp")
    temporary.write_text(json.dumps(payload, indent=2) + "\n", encoding="utf-8")
    temporary.replace(path)
