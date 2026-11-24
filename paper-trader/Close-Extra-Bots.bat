@echo off
setlocal EnableExtensions
cd /d "%~dp0"
title Paper Trader — Close Extra Bots
echo ============================================================
echo  Keep ONE Bot score in Documents\Paper Trader
echo  Close extra score processes from older installs
echo ============================================================
echo.
if exist "%~dp0runtime\python.exe" (
  "%~dp0runtime\python.exe" "%~dp0launch.py" close-extra-bots
  goto done
)
where python >nul 2>&1
if not errorlevel 1 (
  python "%~dp0launch.py" close-extra-bots
  goto done
)
where py >nul 2>&1
if not errorlevel 1 (
  py -3 "%~dp0launch.py" close-extra-bots
  goto done
)
echo Python was not found. Run Start-Paper-Trader.bat once first.
:done
echo.
pause
