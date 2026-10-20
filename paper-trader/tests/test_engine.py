"""Replay rules for the paper desk."""

from __future__ import annotations

import sys
import tempfile
import unittest
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))

from paper_trader.backtest import max_drawdown, run_backtest
from paper_trader.indicators import rsi, sma
from paper_trader.journal import load_journal, save_journal, view_journal
from paper_trader.model import Bar, Params
from paper_trader.samples import load_symbol, symbols


def bar(day: str, price: float, low: float | None = None, high: float | None = None, open_: float | None = None) -> Bar:
    open_ = price if open_ is None else open_
    high = price if high is None else high
    low = price if low is None else low
    high = max(high, open_, price)
    low = min(low, open_, price)
    return Bar(day, open_, high, low, price, 1)


def hand_bars() -> list[Bar]:
    prices = [10, 10, 10, 14, 14, 8, 8]
    return [bar(f"2024-01-0{index}", price) for index, price in enumerate(prices, start=1)]


class IndicatorTests(unittest.TestCase):
    def test_sma_uses_only_completed_bars(self) -> None:
        self.assertEqual(sma([1, 2, 3, 4], 2), [None, 1.5, 2.5, 3.5])

    def test_rsi_extremes(self) -> None:
        rising = list(range(1, 18))
        falling = list(range(18, 0, -1))
        self.assertEqual(rsi(rising, 14)[-1], 100)
        self.assertEqual(rsi(falling, 14)[-1], 0)

    def test_drawdown(self) -> None:
        self.assertAlmostEqual(max_drawdown([100, 120, 90]), 0.25)


class BacktestTests(unittest.TestCase):
    def test_crossover_fills_on_the_next_open(self) -> None:
        params = Params(fast=2, slow=3, rsi_period=0, cash=10_000, fee_bps=0, stop_pct=0)
        result = run_backtest(hand_bars(), params)
        self.assertEqual(len(result.trades), 1)
        trade = result.trades[0]
        self.assertEqual(trade.entry_price, 14)
        self.assertEqual(trade.exit_price, 8)
        self.assertEqual(trade.reason, "signal")
        self.assertAlmostEqual(result.cash, 10_000 * 8 / 14)

    def test_fee_reduces_cash(self) -> None:
        free = Params(fast=2, slow=3, rsi_period=0, cash=10_000, fee_bps=0, stop_pct=0)
        paid = Params(fast=2, slow=3, rsi_period=0, cash=10_000, fee_bps=100, stop_pct=0)
        free_cash = run_backtest(hand_bars(), free).cash
        paid_result = run_backtest(hand_bars(), paid)
        shares = 10_000 / (14 * 1.01)
        self.assertAlmostEqual(paid_result.cash, shares * 8 * 0.99)
        self.assertLess(paid_result.cash, free_cash)

    def test_stop_exits_inside_the_bar(self) -> None:
        bars = hand_bars()
        bars[5] = bar("2024-01-06", 8, low=1, high=14, open_=14)
        params = Params(fast=2, slow=3, rsi_period=0, cash=10_000, fee_bps=0, stop_pct=10)
        result = run_backtest(bars, params)
        self.assertEqual(len(result.trades), 1)
        self.assertEqual(result.trades[0].reason, "stop")
        self.assertAlmostEqual(result.trades[0].exit_price, 12.6)

    def test_gap_through_stop_fills_at_the_open(self) -> None:
        bars = hand_bars()
        bars[5] = bar("2024-01-06", 9, low=9, high=10, open_=10)
        params = Params(fast=2, slow=3, rsi_period=0, cash=10_000, fee_bps=0, stop_pct=10)
        result = run_backtest(bars, params)
        self.assertAlmostEqual(result.trades[0].exit_price, 10)

    def test_later_bars_do_not_change_closed_trades(self) -> None:
        bars = hand_bars() + [bar("2024-01-08", 8), bar("2024-01-09", 7)]
        params = Params(fast=2, slow=3, rsi_period=0, cash=10_000, fee_bps=0, stop_pct=0)
        early = run_backtest(bars[:7], params)
        later = run_backtest(bars, params)
        self.assertEqual(later.trades[: len(early.trades)], early.trades)

    def test_bad_settings_are_rejected(self) -> None:
        with self.assertRaises(ValueError):
            run_backtest(hand_bars(), Params(fast=10, slow=5, rsi_period=0))


class SampleTests(unittest.TestCase):
    def test_samples_are_weekday_bars_and_can_trade(self) -> None:
        self.assertEqual(len(symbols()), 5)
        for item in symbols():
            bars = load_symbol(str(item["symbol"]))
            self.assertGreaterEqual(len(bars), 200)
            dates = [bar.date for bar in bars]
            self.assertEqual(dates, sorted(set(dates)))
        result = run_backtest(load_symbol("BTC-USD"), Params())
        self.assertGreaterEqual(len(result.trades), 1)
        self.assertGreater(result.equity_curve[-1][1], 0)

    def test_partial_replay_matches_the_full_prefix(self) -> None:
        bars = load_symbol("AAPL")
        params = Params()
        partial = run_backtest(bars, params, end=80)
        full = run_backtest(bars, params)
        self.assertEqual(full.trades[: len(partial.trades)], partial.trades)

    def test_journal_round_trip(self) -> None:
        with tempfile.TemporaryDirectory() as folder:
            path = Path(folder) / "paper_state.json"
            journal = load_journal(path)
            journal.cursor += 5
            save_journal(path, journal)
            loaded = load_journal(path)
            self.assertEqual(loaded.cursor, journal.cursor)
            view = view_journal(loaded)
            self.assertEqual(view["symbol"], "BTC-USD")
            self.assertEqual(view["cursor"], loaded.cursor)


if __name__ == "__main__":
    unittest.main()
