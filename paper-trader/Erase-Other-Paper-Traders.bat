@echo off
setlocal EnableExtensions
cd /d "%~dp0"
title Paper Trader — keep 1.10.47 only
echo.
echo  Keep Paper Trader 1.10.47 in Documents\Paper Trader
echo  Erase other installs (PaperTrader-setup-*), leftover zips, extra Bots
echo.
if exist "%~dp0runtime\python.exe" (
  "%~dp0runtime\python.exe" "%~dp0launch.py" erase-others
  goto After
)
where python >nul 2>nul
if not errorlevel 1 (
  python "%~dp0launch.py" erase-others
  goto After
)
where py >nul 2>nul
if not errorlevel 1 (
  py -3 "%~dp0launch.py" erase-others
  goto After
)
echo Python was not found. Open this from Documents\Paper Trader after 1 Allow.
pause
exit /b 1

:After
if errorlevel 1 (
  echo Could not erase the other copies.
  pause
  exit /b 1
)
echo.
echo Done. Only Documents\Paper Trader should remain.
echo Open Paper Trader.bat on the desktop, then Connect-Schwab.bat for Schwab.
pause
exit /b 0
