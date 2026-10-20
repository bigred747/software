@echo off
cd /d "%~dp0"
echo Paper Trader stays on this computer. It replays sample prices and does not place broker orders.
where python >nul 2>&1 (
  python -m paper_trader serve
  goto done
)
where py >nul 2>&1 (
  py -3 -m paper_trader serve
  goto done
)
echo Install Python 3 from https://www.python.org/downloads/ and run this file again.
:done
pause
