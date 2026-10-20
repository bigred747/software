"""Command line entry for the paper desk."""

from __future__ import annotations

import argparse
import sys
from pathlib import Path

from paper_trader.backtest import run_backtest, signal_name
from paper_trader.guard import assert_safe_launch
from paper_trader.model import Params
from paper_trader.samples import load_symbol
from paper_trader.server import serve

STATE_PATH = Path(__file__).resolve().parents[1] / "paper_state.json"


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(
        prog="paper_trader",
        description="Local paper trading desk. Sample prices. No broker orders.",
    )
    commands = parser.add_subparsers(dest="command", required=True)
    server = commands.add_parser("serve", help="Open the desk in a browser on this computer")
    server.add_argument("--port", type=int, default=8765)
    server.add_argument("--no-browser", action="store_true")
    check = commands.add_parser("backtest", help="Replay one sample market and print the result")
    check.add_argument("--symbol", default="BTC-USD")
    check.add_argument("--fast", type=int, default=10)
    check.add_argument("--slow", type=int, default=30)
    check.add_argument("--cash", type=float, default=10_000.0)
    check.add_argument("--fee-bps", type=float, default=5.0)
    check.add_argument("--stop-pct", type=float, default=8.0)
    check.add_argument("--rsi", type=int, default=14)
    args = parser.parse_args(argv)
    try:
        if args.command == "serve":
            if not 1024 <= args.port <= 65535:
                raise ValueError("Port must be from 1024 to 65535")
            assert_safe_launch([Path(__file__).resolve(), Path.cwd()])
            serve(STATE_PATH, args.port, open_browser=not args.no_browser)
            return 0
        params = Params(
            fast=args.fast,
            slow=args.slow,
            rsi_period=args.rsi,
            cash=args.cash,
            fee_bps=args.fee_bps,
            stop_pct=args.stop_pct,
        )
        bars = load_symbol(args.symbol)
        result = run_backtest(bars, params)
        ending = result.equity_curve[-1][1]
        print(f"Paper Trader sample replay: {args.symbol}")
        print(f"Bars: {len(bars)}   {bars[0].date} -> {bars[-1].date}")
        print(f"Closed trades: {len(result.trades)}")
        print(f"Ending equity: {ending:.2f}")
        print(f"Return: {(ending / params.cash - 1) * 100:.2f}%")
        print(f"Last signal: {signal_name(result)}")
        return 0
    except ValueError as exc:
        print(str(exc), file=sys.stderr)
        return 2


if __name__ == "__main__":
    raise SystemExit(main())
