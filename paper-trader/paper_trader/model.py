"""Prices, settings, and closed-trade records."""

from __future__ import annotations

import math
from dataclasses import asdict, dataclass
from datetime import datetime


@dataclass(frozen=True)
class Bar:
    date: str
    open: float
    high: float
    low: float
    close: float
    volume: float


@dataclass(frozen=True)
class Params:
    fast: int = 10
    slow: int = 30
    rsi_period: int = 14
    rsi_buy_max: float = 70.0
    rsi_sell_min: float = 75.0
    cash: float = 10_000.0
    fee_bps: float = 5.0
    stop_pct: float = 8.0

    def to_dict(self) -> dict[str, float | int]:
        return asdict(self)


@dataclass(frozen=True)
class Trade:
    entry_date: str
    exit_date: str
    entry_price: float
    exit_price: float
    shares: float
    pnl: float
    reason: str

    def to_dict(self) -> dict[str, float | str]:
        return asdict(self)


def _finite_positive(value: float, label: str) -> None:
    if not math.isfinite(value) or value <= 0:
        raise ValueError(f"{label} must be a positive number")


def validate_bars(bars: list[Bar]) -> None:
    if not bars:
        raise ValueError("No price bars")
    previous: str | None = None
    for bar in bars:
        datetime.strptime(bar.date, "%Y-%m-%d")
        _finite_positive(bar.open, f"{bar.date} open")
        _finite_positive(bar.high, f"{bar.date} high")
        _finite_positive(bar.low, f"{bar.date} low")
        _finite_positive(bar.close, f"{bar.date} close")
        if bar.volume < 0 or not math.isfinite(bar.volume):
            raise ValueError(f"{bar.date} volume is invalid")
        if bar.high < max(bar.open, bar.close) or bar.low > min(bar.open, bar.close):
            raise ValueError(f"{bar.date} high/low does not contain open and close")
        if previous is not None and bar.date <= previous:
            raise ValueError("Dates must be strictly increasing")
        previous = bar.date


def validate_params(params: Params) -> None:
    if params.fast < 2 or params.fast > 80:
        raise ValueError("Fast average must be from 2 to 80")
    if params.slow <= params.fast or params.slow > 200:
        raise ValueError("Slow average must be longer than the fast average and at most 200")
    if params.rsi_period != 0 and not 2 <= params.rsi_period <= 50:
        raise ValueError("RSI length must be 0 (off) or from 2 to 50")
    if not 0 <= params.rsi_buy_max <= 100 or not 0 <= params.rsi_sell_min <= 100:
        raise ValueError("RSI limits must be from 0 to 100")
    _finite_positive(params.cash, "Starting cash")
    if params.cash > 1_000_000_000:
        raise ValueError("Starting cash is too large")
    if not 0 <= params.fee_bps <= 100:
        raise ValueError("Fee must be from 0 to 100 basis points")
    if not 0 <= params.stop_pct <= 50:
        raise ValueError("Stop must be from 0 to 50 percent")


def params_from_dict(raw: dict) -> Params:
    base = Params()
    params = Params(
        fast=int(raw.get("fast", base.fast)),
        slow=int(raw.get("slow", base.slow)),
        rsi_period=int(raw.get("rsi", raw.get("rsi_period", base.rsi_period))),
        rsi_buy_max=float(raw.get("rsi_buy_max", base.rsi_buy_max)),
        rsi_sell_min=float(raw.get("rsi_sell_min", base.rsi_sell_min)),
        cash=float(raw.get("cash", base.cash)),
        fee_bps=float(raw.get("fee_bps", base.fee_bps)),
        stop_pct=float(raw.get("stop_pct", base.stop_pct)),
    )
    validate_params(params)
    return params
