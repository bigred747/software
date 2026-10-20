@echo off
setlocal
cd /d "%~dp0"
echo Paper Trader stays on this computer. It replays sample prices and does not place broker orders.
echo %~dp0 | findstr /I /C:"\AppData\Local\Temp\" | findstr /I /C:".zip." >nul
if not errorlevel 1 (
  echo This copy is still inside a temporary zip folder. Copy the paper-trader folder to Documents and start it there.
  pause
  exit /b 1
)
where python >nul 2>&1
if not errorlevel 1 (
  python -m paper_trader serve
  goto done
)
where py >nul 2>&1
if not errorlevel 1 (
  py -3 -m paper_trader serve
  goto done
)
echo Install Python 3 from https://www.python.org/downloads/ and check "Add python.exe to PATH". Then run this file again.
:done
pause
