"""Long-only replay. Signals use the completed bar and fill on the next open."""

from __future__ import annotations

from dataclasses import dataclass

from paper_trader.indicators import rsi, sma, squeeze_marks
from paper_trader.model import Bar, Params, Trade, validate_bars, validate_params


@dataclass
class Result:
    trades: list[Trade]
    cash: float
    shares: float
    entry_price: float | None
    entry_date: str | None
    pending: str | None
    equity_curve: list[tuple[str, float]]
    last_fast: float | None
    last_slow: float | None
    last_rsi: float | None
    last_close: float | None
    start_cash: float


def max_drawdown(values: list[float]) -> float:
    if not values:
        return 0.0
    peak = values[0]
    worst = 0.0
    for value in values:
        peak = max(peak, value)
        if peak > 0:
            worst = max(worst, (peak - value) / peak)
    return worst


def run_backtest(
    bars: list[Bar],
    params: Params,
    end: int | None = None,
    manual: dict[int, str] | None = None,
    close_orders: list[tuple] | None = None,
    halt_days: set[str] | None = None,
) -> Result:
    validate_params(params)
    if end is None:
        end = len(bars)
    if end < 1 or end > len(bars):
        raise ValueError("Replay length is outside the market history")
    view = bars[:end]
    validate_bars(view)
    closes = [bar.close for bar in view]
    fast = sma(closes, params.fast)
    slow = sma(closes, params.slow)
    rsi_values = rsi(closes, params.rsi_period) if params.rsi_period else [None] * len(view)
    squeeze_buys, squeeze_sells, _squeeze = squeeze_marks(
        [bar.high for bar in view],
        [bar.low for bar in view],
        closes,
    )
    fee_rate = params.fee_bps / 10_000.0
    stop_fraction = params.stop_pct / 100.0
    cash = params.cash
    shares = 0.0
    entry_price: float | None = None
    entry_date: str | None = None
    cost = 0.0
    pending: str | None = None
    pending_reason = "signal"
    trades: list[Trade] = []
    curve: list[tuple[str, float]] = []
    orders = manual or {}
    fills = close_orders or []
    halts = halt_days or set()

    for index, bar in enumerate(view):
        halted = bar.date in halts
        manual_side = orders.get(index)
        if halted and pending == "buy":
            pending = None
        if manual_side == "buy" and shares == 0 and cash > 0 and not halted:
            shares, cash, cost, entry_price = _open_long(cash, bar.open, fee_rate)
            entry_date = bar.date
            pending = None
        elif manual_side == "sell" and shares > 0 and entry_price is not None:
            cash, pnl = _close_long(cash, shares, bar.open, fee_rate, cost)
            trades.append(
                Trade(entry_date or bar.date, bar.date, entry_price, bar.open, shares, pnl, "trade")
            )
            shares = 0.0
            entry_price = None
            entry_date = None
            cost = 0.0
            pending = None
        elif pending == "buy" and shares == 0 and cash > 0:
            shares, cash, cost, entry_price = _open_long(cash, bar.open, fee_rate)
            entry_date = bar.date
            pending = None
        elif pending == "sell" and shares > 0 and entry_price is not None:
            cash, pnl = _close_long(cash, shares, bar.open, fee_rate, cost)
            trades.append(
                Trade(entry_date or bar.date, bar.date, entry_price, bar.open, shares, pnl, pending_reason)
            )
            shares = 0.0
            entry_price = None
            entry_date = None
            cost = 0.0
            pending = None

        if shares > 0 and stop_fraction > 0 and entry_price is not None:
            stop_price = entry_price * (1 - stop_fraction)
            if bar.low <= stop_price:
                exit_price = bar.open if bar.open < stop_price else stop_price
                cash, pnl = _close_long(cash, shares, exit_price, fee_rate, cost)
                trades.append(
                    Trade(entry_date or bar.date, bar.date, entry_price, exit_price, shares, pnl, "stop")
                )
                shares = 0.0
                entry_price = None
                entry_date = None
                cost = 0.0
                pending = None

        if (
            index > 0
            and fast[index] is not None
            and slow[index] is not None
            and fast[index - 1] is not None
            and slow[index - 1] is not None
        ):
            crossed_up = fast[index - 1] <= slow[index - 1] and fast[index] > slow[index]
            crossed_down = fast[index - 1] >= slow[index - 1] and fast[index] < slow[index]
            rsi_now = rsi_values[index]
            buy_blocked = (
                params.rsi_period > 0
                and rsi_now is not None
                and rsi_now >= params.rsi_buy_max
            )
            rsi_exit = (
                params.rsi_period > 0
                and rsi_now is not None
                and rsi_now >= params.rsi_sell_min
            )
            if crossed_up and shares == 0 and not buy_blocked:
                pending = "buy"
                pending_reason = "signal"
            elif shares > 0 and (crossed_down or rsi_exit):
                pending = "sell"
                pending_reason = "signal"
        if index in squeeze_buys and shares == 0:
            pending = "buy"
            pending_reason = "squeeze"
        elif index in squeeze_sells and shares > 0:
            pending = "sell"
            pending_reason = "squeeze"
        for item in [item for item in fills if item[0] == bar.date]:
            side = item[1]
            price = float(item[2]) if len(item) > 2 and item[2] else bar.close
            if side == "buy" and shares == 0 and cash > 0 and not halted:
                shares, cash, cost, entry_price = _open_long(cash, price, fee_rate)
                entry_date = bar.date
                pending = None
            elif side == "sell" and shares > 0 and entry_price is not None:
                cash, pnl = _close_long(cash, shares, price, fee_rate, cost)
                trades.append(
                    Trade(entry_date or bar.date, bar.date, entry_price, price, shares, pnl, "trade")
                )
                shares = 0.0
                entry_price = None
                entry_date = None
                cost = 0.0
                pending = None
        if halted and pending == "buy":
            pending = None
        curve.append((bar.date, cash + shares * bar.close))

    return Result(
        trades=trades,
        cash=cash,
        shares=shares,
        entry_price=entry_price,
        entry_date=entry_date,
        pending=pending,
        equity_curve=curve,
        last_fast=fast[-1] if fast else None,
        last_slow=slow[-1] if slow else None,
        last_rsi=rsi_values[-1] if rsi_values else None,
        last_close=view[-1].close,
        start_cash=params.cash,
    )


def signal_name(result: Result) -> str:
    if result.pending == "buy":
        return "BUY"
    if result.pending == "sell":
        return "SELL"
    if result.shares > 0:
        return "HOLD LONG"
    return "HOLD"


def present(result: Result, symbol: str, total_bars: int, params: Params, cursor: int) -> dict:
    ending = result.equity_curve[-1][1] if result.equity_curve else result.start_cash
    wins = [trade for trade in result.trades if trade.pnl > 0]
    losses = [trade for trade in result.trades if trade.pnl < 0]
    gross_profit = sum(trade.pnl for trade in wins)
    gross_loss = -sum(trade.pnl for trade in losses)
    profit_factor = None if gross_loss == 0 else gross_profit / gross_loss
    points: list[dict[str, float | str]] = []
    for trade in result.trades:
        points.append({"date": trade.entry_date, "price": trade.entry_price, "side": "buy"})
        points.append({"date": trade.exit_date, "price": trade.exit_price, "side": "sell"})
    if result.shares > 0 and result.entry_date and result.entry_price is not None:
        points.append({"date": result.entry_date, "price": result.entry_price, "side": "buy"})
    open_pnl = 0.0
    if result.shares > 0 and result.entry_price is not None and result.last_close is not None:
        open_pnl = result.shares * (result.last_close - result.entry_price)
    return {
        "symbol": symbol,
        "cursor": cursor,
        "total_bars": total_bars,
        "cash": result.cash,
        "shares": result.shares,
        "entry_price": result.entry_price,
        "entry_date": result.entry_date,
        "equity": ending,
        "start_cash": result.start_cash,
        "return_pct": (ending / result.start_cash - 1.0) * 100.0 if result.start_cash else 0.0,
        "max_drawdown_pct": max_drawdown([value for _, value in result.equity_curve]) * 100.0,
        "pending": result.pending,
        "signal": signal_name(result),
        "fast": result.last_fast,
        "slow": result.last_slow,
        "rsi": result.last_rsi,
        "last_close": result.last_close,
        "last_date": result.equity_curve[-1][0] if result.equity_curve else None,
        "open_pnl": open_pnl,
        "trades": [trade.to_dict() for trade in result.trades],
        "trade_count": len(result.trades),
        "win_rate_pct": (len(wins) / len(result.trades) * 100.0) if result.trades else 0.0,
        "profit_factor": profit_factor,
        "equity_curve": [{"date": day, "equity": value} for day, value in result.equity_curve],
        "markers": points,
        "params": params.to_dict(),
        "finished": cursor >= total_bars,
        "mode": "paper",
        "paper": True,
    }


def _open_long(cash: float, price: float, fee_rate: float) -> tuple[float, float, float, float]:
    shares = cash / (price * (1.0 + fee_rate))
    notional = shares * price
    fee = notional * fee_rate
    spent = notional + fee
    return shares, cash - spent, spent, price


def _close_long(
    cash: float, shares: float, price: float, fee_rate: float, cost: float
) -> tuple[float, float]:
    notional = shares * price
    fee = notional * fee_rate
    proceeds = notional - fee
    return cash + proceeds, proceeds - cost
