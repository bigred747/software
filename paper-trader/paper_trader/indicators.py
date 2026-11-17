"""Averages, RSI, and a band-compression squeeze. Each value uses only past bars."""

from __future__ import annotations

import math


def sma(values: list[float], window: int) -> list[float | None]:
    if window < 1:
        raise ValueError("Average window must be at least 1")
    out: list[float | None] = [None] * len(values)
    total = 0.0
    for index, value in enumerate(values):
        total += value
        if index >= window:
            total -= values[index - window]
        if index >= window - 1:
            out[index] = total / window
    return out


def rsi(values: list[float], period: int) -> list[float | None]:
    out: list[float | None] = [None] * len(values)
    if period < 2 or len(values) <= period:
        return out
    gains: list[float] = []
    losses: list[float] = []
    for index in range(1, len(values)):
        change = values[index] - values[index - 1]
        gains.append(max(change, 0.0))
        losses.append(max(-change, 0.0))
    average_gain = sum(gains[:period]) / period
    average_loss = sum(losses[:period]) / period
    out[period] = _rsi_value(average_gain, average_loss)
    for index in range(period, len(gains)):
        average_gain = (average_gain * (period - 1) + gains[index]) / period
        average_loss = (average_loss * (period - 1) + losses[index]) / period
        out[index + 1] = _rsi_value(average_gain, average_loss)
    return out


def _rsi_value(average_gain: float, average_loss: float) -> float:
    if average_loss == 0:
        return 100.0
    relative = average_gain / average_loss
    return 100.0 - (100.0 / (1.0 + relative))


def squeeze(highs: list[float], lows: list[float], closes: list[float], length: int = 20) -> dict[str, list]:
    """Bollinger bands inside Keltner channels, plus a momentum line.

    Level 1 is a wide compression, 2 is normal, and 3 is tight. Level 0 is off.
    """
    count = len(closes)
    momentum: list[float | None] = [None] * count
    level = [0] * count
    if length < 5 or count < length:
        return {"momentum": momentum, "level": level}
    basis = sma(closes, length)
    deviation = _stdev(closes, length)
    width = _atr(highs, lows, closes, length)
    delta = [0.0] * count
    for index in range(length - 1, count):
        if basis[index] is None:
            continue
        highest = max(highs[index - length + 1 : index + 1])
        lowest = min(lows[index - length + 1 : index + 1])
        midpoint = ((highest + lowest) / 2 + float(basis[index])) / 2
        delta[index] = closes[index] - midpoint
        if deviation[index] is None or width[index] is None:
            continue
        upper = float(basis[index]) + 2 * float(deviation[index])
        lower = float(basis[index]) - 2 * float(deviation[index])
        band = float(width[index])
        center = float(basis[index])
        squeeze_level = 0
        for multiple, code in ((2.0, 1), (1.5, 2), (1.0, 3)):
            if upper < center + multiple * band and lower > center - multiple * band:
                squeeze_level = code
        level[index] = squeeze_level
    momentum = _linreg_end(delta, length)
    for index in range(length - 1):
        momentum[index] = None
    return {"momentum": momentum, "level": level}


def squeeze_alert(level: list[int], momentum: list[float | None]) -> dict[str, str | int]:
    """A warning before the release, and a sell when momentum turns down.

    Five squeeze dots means compression has built up. The warning is the fifth
    dot, before the squeeze releases. A later fade, while the dots are still on
    or on the bar after they turn off, is the squeeze running out of steam.
    """
    dots = 0
    for value in reversed(level):
        if int(value) <= 0:
            break
        dots += 1
    recent = dots > 0 or (len(level) >= 2 and int(level[-2]) > 0)
    if recent and _momentum_fading(momentum):
        return {"side": "sell", "kind": "steam", "dots": dots, "index": max(0, len(level) - 1)}
    if dots >= 5:
        side = "buy" if _momentum_rising(momentum) else "sell"
        index = len(level) - dots + 4
        return {"side": side, "kind": "dots", "dots": dots, "index": index}
    return {"side": "", "kind": "", "dots": dots, "index": -1}


def _momentum_rising(momentum: list[float | None]) -> bool:
    if len(momentum) < 2:
        return False
    now = momentum[-1]
    previous = momentum[-2]
    return now is not None and previous is not None and now > 0 and now >= previous


def _momentum_fading(momentum: list[float | None]) -> bool:
    """True on the first bar where positive momentum turns down."""
    if len(momentum) < 2:
        return False
    now = momentum[-1]
    previous = momentum[-2]
    if now is None or previous is None or not (previous > 0 and now < previous):
        return False
    if len(momentum) >= 3:
        older = momentum[-3]
        if older is not None and older > 0 and previous < older:
            return False
    return True


def squeeze_marks(
    highs: list[float], lows: list[float], closes: list[float], length: int = 20
) -> tuple[set[int], set[int], dict[str, list]]:
    """Buy when a compression releases upward. Sell when momentum turns down."""
    state = squeeze(highs, lows, closes, length)
    buys: set[int] = set()
    sells: set[int] = set()
    momentum = state["momentum"]
    level = state["level"]
    for index in range(1, len(closes)):
        now = momentum[index]
        previous = momentum[index - 1]
        if now is None or previous is None:
            continue
        released = level[index - 1] > 0 and level[index] == 0
        if released and now > 0 and now > previous:
            buys.add(index)
        if previous >= 0 and now < 0:
            sells.add(index)
        elif released and now < 0 and now < previous:
            sells.add(index)
    return buys, sells, state


def _stdev(values: list[float], window: int) -> list[float | None]:
    out: list[float | None] = [None] * len(values)
    for index in range(window - 1, len(values)):
        chunk = values[index - window + 1 : index + 1]
        mean = sum(chunk) / window
        variance = sum((value - mean) ** 2 for value in chunk) / window
        out[index] = math.sqrt(variance)
    return out


def _atr(highs: list[float], lows: list[float], closes: list[float], window: int) -> list[float | None]:
    ranges: list[float] = []
    for index, close in enumerate(closes):
        span = highs[index] - lows[index]
        if index == 0:
            ranges.append(span)
            continue
        ranges.append(max(span, abs(highs[index] - closes[index - 1]), abs(lows[index] - closes[index - 1])))
    return sma(ranges, window)


def _linreg_end(values: list[float], window: int) -> list[float | None]:
    out: list[float | None] = [None] * len(values)
    sum_x = window * (window - 1) / 2
    sum_x2 = (window - 1) * window * (2 * window - 1) / 6
    denominator = window * sum_x2 - sum_x * sum_x
    if denominator == 0:
        return out
    for index in range(window - 1, len(values)):
        chunk = values[index - window + 1 : index + 1]
        sum_y = sum(chunk)
        sum_xy = sum(offset * value for offset, value in enumerate(chunk))
        slope = (window * sum_xy - sum_x * sum_y) / denominator
        intercept = (sum_y - slope * sum_x) / window
        out[index] = intercept + slope * (window - 1)
    return out
