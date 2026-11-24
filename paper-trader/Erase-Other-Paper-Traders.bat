@echo off
setlocal EnableExtensions
cd /d "%~dp0"
title Paper Trader — keep 1.10.40 only
echo.
echo  Keep Paper Trader 1.10.40
echo  Erase other installs, leftover zips, and extra Bot scores
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
echo Done. Documents\Paper Trader 1.10.40 is the only desk.
echo Desktop shortcut Paper Trader.bat points at that copy.
echo Double-click Paper Trader.bat to open.
pause
exit /b 0
