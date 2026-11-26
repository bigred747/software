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
    install = commands.add_parser("install", help="Install or update Documents\\Paper Trader from this folder")
    install.add_argument("--home", type=Path, default=None)
    force = commands.add_parser(
        "force-upgrade",
        help="Replace Documents\\Paper Trader from this extracted pack (FORCE-UPGRADE.bat). Does not erase other folders.",
    )
    force.add_argument("--home", type=Path, default=None)
    commands.add_parser(
        "check-seal",
        help="Print what Documents\\Paper Trader VERSION / web files actually are",
    )
    erase = commands.add_parser(
        "erase-others",
        help="OPTIONAL: erase other installs/zips. Open/Start never does this.",
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
        if args.command == "force-upgrade":
            from paper_trader.install import force_upgrade

            home = args.home or Path.home()
            folder = Path(__file__).resolve().parents[1]
            dest = force_upgrade(folder, home)
            print(dest)
            print("force-upgrade")
            return 0
        if args.command == "check-seal":
            from paper_trader.install import desk_seal_report

            print(desk_seal_report(Path.home()))
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
            from paper_trader import __version__
            from paper_trader.install import (
                canonical_folder,
                install_newest,
                write_desktop_opener,
            )
            from paper_trader.schwab_env import ensure_env_file, heal_env_from_documents

            home = Path.home()
            desk = canonical_folder(home)
            staging = "papertrader-setup" in str(install_root).lower().replace(" ", "")
            # Zip setup folders only: copy into Documents\Paper Trader, then open that desk.
            # Any other folder (including a side-by-side 1.10.40 / 1.10.53 extract) opens itself.
            # Never erase other installs on open — Erase-Other-Paper-Traders.bat is opt-in.
            if staging:
                try:
                    desk, _action = install_newest(install_root, home)
                except ValueError:
                    desk = canonical_folder(home)
                launch = desk / "launch.py"
                if launch.is_file() and desk.resolve() != install_root.resolve():
                    write_desktop_opener(desk, home)
                    cmd = [sys.executable, str(launch), "serve", "--port", str(args.port)]
                    if args.no_browser:
                        cmd.append("--no-browser")
                    os.chdir(desk)
                    os.execv(sys.executable, cmd)
            os.chdir(install_root)
            assert_safe_launch([install_root])
            global STATE_PATH
            STATE_PATH = install_root / "paper_state.json"
            try:
                (install_root / "VERSION").write_text(__version__ + "\n", encoding="utf-8")
            except OSError:
                pass
            try:
                write_desktop_opener(install_root, home)
            except OSError:
                pass
            ensure_env_file(install_root)
            heal_env_from_documents(install_root)
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


def _open_schwab_authorize(url: str) -> str:
    """Open Schwab sign-in. Prefer Edge on Windows; always print the URL for copy/paste."""
    import subprocess
    import webbrowser

    if os.name == "nt":
        try:
            os.startfile(url)  # type: ignore[attr-defined]
            return "Opened Schwab sign-in (Windows startfile)."
        except OSError:
            pass
        for command in (
            ["cmd", "/c", "start", "", url],
            ["cmd", "/c", "start", "msedge", url],
            ["cmd", "/c", "start", "microsoft-edge:" + url],
        ):
            try:
                completed = subprocess.run(command, check=False, shell=False)
                if completed.returncode == 0:
                    return "Opened Schwab sign-in in your browser."
            except OSError:
                continue
        try:
            # shell=True helps when start.exe needs the empty title argument.
            completed = subprocess.run(f'start "" "{url}"', shell=True, check=False)
            if completed.returncode == 0:
                return "Opened Schwab sign-in via CMD start."
        except OSError:
            pass
    try:
        if webbrowser.open(url, new=1, autoraise=True):
            return "Opened Schwab sign-in in your browser."
    except OSError:
        pass
    return "Could not open a browser. Copy the authorize URL above into Edge yourself."


def _schwab_connect_cmd(*, redirect: str = "", open_browser: bool = True) -> int:
    """CMD Connect flow: authorize URL → paste redirect → save tokens locally."""
    from paper_trader.schwab_auth import authorize_url, connection_status, finish_oauth
    from paper_trader.schwab_env import (
        _configured_value,
        credentials_ready,
        install_schwab_env,
        load_dotenv,
        status_report,
    )

    install_root = Path(__file__).resolve().parents[1]
    os.chdir(install_root)
    env_path = install_schwab_env(install_root)
    load_dotenv(env_path)
    creds = status_report()
    key = _configured_value("SCHWAB_APP_KEY")
    secret = _configured_value("SCHWAB_APP_SECRET")
    print(f".env path: {env_path}")
    print(f"Schwab credentials: {creds['detail']}")
    print(f"App Key length: {len(key)} · App Secret length: {len(secret)}")
    if not credentials_ready():
        print("Schwab keys are missing or still placeholders.")
        print(f"Edit {env_path}")
        print("Lines must look like:")
        print("  SCHWAB_APP_KEY=your-client-id")
        print("  SCHWAB_APP_SECRET=your-client-secret")
        print("  SCHWAB_CALLBACK_URL=https://127.0.0.1")
        print("No spaces around =. No quotes. Then run Connect-Schwab.bat again.")
        return 2
    start = authorize_url()
    print("Schwab authorize URL:")
    print(start["authorize_url"])
    print("Callback must stay https://127.0.0.1")
    if open_browser:
        print(_open_schwab_authorize(start["authorize_url"]))
    raw = (redirect or "").strip()
    if not raw:
        print("After sign-in, the browser lands on a broken https://127.0.0.1/?code=... page.")
        print("That is normal. Copy that WHOLE address bar URL (includes code= and usually %40).")
        print("Paste it here within about 30 seconds, then press Enter:")
        try:
            raw = input().strip()
        except EOFError:
            raw = ""
    if not raw:
        print("No redirect URL pasted. Connection not finished.")
        print("If Edge did not open, copy the authorize URL above into Edge yourself.")
        return 2
    try:
        finish_oauth(raw)
    except ValueError as exc:
        print(str(exc))
        print("Tip: run Connect-Schwab.bat again for a fresh code; paste the new URL immediately.")
        return 2
    link = connection_status()
    print(f"Schwab connection: {link['detail']}")
    print(f"tokens path: {link['token_path']}")
    print("Paper stays the default. Live stays locked. No broker orders.")
    return 0 if link.get("connected") else 2


if __name__ == "__main__":
    raise SystemExit(main())
