@echo off
setlocal
cd /d "%~dp0"
echo Paper Trader stays on this computer. It replays sample prices and does not place broker orders.
echo %~dp0 | findstr /I /C:"\AppData\Local\Temp\" | findstr /I /C:".zip." >nul
if not errorlevel 1 (
  echo This copy is still inside the zip. Right-click Paper-Trader.zip, choose Extract All, put the folder in Documents, and start it there.
  pause
  exit /b 1
)
if exist "%~dp0runtime\python.exe" (
  "%~dp0runtime\python.exe" -m paper_trader serve
  goto done
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
echo The Windows copy is missing runtime\python.exe. Download Paper-Trader.zip again and use Extract All.
:done
pause
