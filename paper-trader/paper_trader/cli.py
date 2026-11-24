"""Command line entry for the paper desk."""

from __future__ import annotations

import argparse
import os
import sys
from pathlib import Path

from paper_trader.backtest import run_backtest, signal_name
from paper_trader.guard import assert_safe_launch
from paper_trader.install import erase_other_installs, install_newest
from paper_trader.model import Params
from paper_trader import quotes
from paper_trader.learn import run_learner
from paper_trader.server import serve

STATE_PATH = Path(__file__).resolve().parents[1] / "paper_state.json"


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(
        prog="paper_trader",
        description="Local paper trading desk. Market quotes. No broker orders.",
    )
    commands = parser.add_subparsers(dest="command", required=True)
    server = commands.add_parser("serve", help="Open the desk in a browser on this computer")
    server.add_argument("--port", type=int, default=8765)
    server.add_argument("--no-browser", action="store_true")
    install = commands.add_parser("install", help="Keep only the newest copy in Documents")
    install.add_argument("--home", type=Path, default=None)
    erase = commands.add_parser(
        "erase-others",
        help="Keep Paper Trader 1.10.40 only; erase other installs, zips, and extra Bots",
    )
    erase.add_argument("--home", type=Path, default=None)
    commands.add_parser("learn", help="Score closed bars all day on this computer")
    commands.add_parser(
        "schwab-status",
        help="Show whether Schwab App Key/Secret are in .env (no API call; Live stays locked)",
    )
    connect = commands.add_parser(
        "schwab-connect",
        help="Connect Schwab from CMD: open sign-in, then paste the https://127.0.0.1/?code=... URL",
    )
    connect.add_argument(
        "--redirect",
        default="",
        help="Optional full redirect URL (skip the paste prompt)",
    )
    connect.add_argument(
        "--no-browser",
        action="store_true",
        help="Print the authorize URL only; do not open a browser",
    )
    check = commands.add_parser("backtest", help="Replay one market's quotes and print the paper result")
    check.add_argument("--symbol", default="BTC-USD")
    check.add_argument("--fast", type=int, default=10)
    check.add_argument("--slow", type=int, default=30)
    check.add_argument("--cash", type=float, default=10_000.0)
    check.add_argument("--fee-bps", type=float, default=5.0)
    check.add_argument("--stop-pct", type=float, default=8.0)
    check.add_argument("--rsi", type=int, default=14)
    args = parser.parse_args(argv)
    try:
        if args.command == "install":
            home = args.home or Path.home()
            folder = Path(__file__).resolve().parents[1]
            dest, action = install_newest(folder, home)
            print(dest)
            print(action)
            return 0
        if args.command == "erase-others":
            home = args.home or Path.home()
            folder = Path(__file__).resolve().parents[1]
            report = erase_other_installs(home, keep=folder)
            print(report["keep"])
            print(report["keep_version"])
            print(f"erased:{len(report['erased'])}")
            print(f"bots_closed:{len(report['closed'])}")
            print(f"zips_removed:{len(report['zips_removed'])}")
            return 0
        if args.command == "serve":
            if not 1024 <= args.port <= 65535:
                raise ValueError("Port must be from 1024 to 65535")
            install_root = Path(__file__).resolve().parents[1]
            os.chdir(install_root)
            assert_safe_launch([install_root])
            from paper_trader.schwab_env import ensure_env_file

            ensure_env_file(install_root)
            serve(STATE_PATH, args.port, open_browser=not args.no_browser)
            return 0
        if args.command == "learn":
            install_root = Path(__file__).resolve().parents[1]
            os.chdir(install_root)
            assert_safe_launch([install_root])
            return run_learner(STATE_PATH.with_name("learn_state.json"))
        if args.command == "schwab-status":
            from paper_trader.schwab_auth import connection_status
            from paper_trader.schwab_env import status_report

            creds = status_report()
            link = connection_status()
            print(f"Schwab credentials: {creds['detail']}")
            print(f"Schwab connection: {link['detail']}")
            print(f".env path: {creds['env_path']}")
            print(f"tokens path: {link['token_path']}")
            print("Paper stays the default. No broker orders are sent.")
            return 0
        if args.command == "schwab-connect":
            return _schwab_connect_cmd(redirect=args.redirect, open_browser=not args.no_browser)
        params = Params(
            fast=args.fast,
            slow=args.slow,
            rsi_period=args.rsi,
            cash=args.cash,
            fee_bps=args.fee_bps,
            stop_pct=args.stop_pct,
        )
        bars = quotes.load_bars(args.symbol)
        result = run_backtest(bars, params)
        ending = result.equity_curve[-1][1]
        print(f"Paper Trader market replay: {args.symbol}")
        print(f"Bars: {len(bars)}   {bars[0].date} -> {bars[-1].date}")
        print(f"Closed trades: {len(result.trades)}")
        print(f"Ending equity: {ending:.2f}")
        print(f"Return: {(ending / params.cash - 1) * 100:.2f}%")
        print(f"Last signal: {signal_name(result)}")
        return 0
    except ValueError as exc:
        print(str(exc), file=sys.stderr)
        return 2


def _schwab_connect_cmd(*, redirect: str = "", open_browser: bool = True) -> int:
    """CMD Connect flow: authorize URL → paste redirect → save tokens locally."""
    import webbrowser

    from paper_trader.schwab_auth import authorize_url, connection_status, finish_oauth
    from paper_trader.schwab_env import credentials_ready, install_schwab_env

    install_root = Path(__file__).resolve().parents[1]
    os.chdir(install_root)
    env_path = install_schwab_env(install_root)
    if not credentials_ready():
        print("Schwab keys are missing.")
        print(f"Edit {env_path}")
        print("Paste Client ID → SCHWAB_APP_KEY and Client Secret → SCHWAB_APP_SECRET.")
        print("Then run: Connect-Schwab.bat")
        return 2
    start = authorize_url()
    print("Schwab authorize URL:")
    print(start["authorize_url"])
    print("Callback must stay https://127.0.0.1")
    if open_browser:
        try:
            webbrowser.open(start["authorize_url"])
            print("Opened Schwab sign-in in your browser.")
        except OSError:
            print("Could not open a browser. Copy the URL above into Edge.")
    raw = (redirect or "").strip()
    if not raw:
        print("After sign-in, the browser lands on a broken https://127.0.0.1/?code=... page.")
        print("Copy that whole address bar URL and paste it here, then press Enter:")
        try:
            raw = input().strip()
        except EOFError:
            raw = ""
    if not raw:
        print("No redirect URL pasted. Connection not finished.")
        return 2
    finish_oauth(raw)
    link = connection_status()
    print(f"Schwab connection: {link['detail']}")
    print(f"tokens path: {link['token_path']}")
    print("Paper stays the default. Live stays locked. No broker orders.")
    return 0 if link.get("connected") else 2


if __name__ == "__main__":
    raise SystemExit(main())
