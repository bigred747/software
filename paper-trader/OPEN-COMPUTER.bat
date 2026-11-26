@echo off
setlocal EnableExtensions EnableDelayedExpansion
title Paper Trader — open computer desk
cd /d "%~dp0"

set "DESK=%USERPROFILE%\Documents\Paper Trader"
set "HERE=%~dp0"
if "!HERE:~-1!"=="\" set "HERE=!HERE:~0,-1!"

rem Prefer Documents desk when it exists; else open THIS extract.
if exist "%DESK%\launch.py" (
  echo Opening Documents\Paper Trader...
  cd /d "%DESK%"
) else (
  echo Documents desk missing — opening this folder.
)

set "SEAL="
if exist "VERSION" set /p SEAL=<"VERSION"
echo Seal: !SEAL!
if not exist "launch.py" (
  echo No launch.py here. Download FULL Paper-Trader.zip, Extract All, run FORCE-UPGRADE.bat.
  pause
  exit /b 1
)

> "%USERPROFILE%\Desktop\Paper Trader.bat" (
  echo @echo off
  echo cd /d "%CD%"
  echo title Paper Trader
  echo call "2 Open Paper Trader.bat"
)

echo Leave this black window open. Edge opens the desk.
echo After it opens: Connect button on the desk, or Connect-Schwab.bat for keys.
echo.

if exist "runtime\python.exe" (
  "runtime\python.exe" "launch.py" serve
  goto done
)
where python >nul 2>&1
if not errorlevel 1 (
  python "launch.py" serve
  goto done
)
where py >nul 2>&1
if not errorlevel 1 (
  py -3 "launch.py" serve
  goto done
)
echo Python / runtime missing. Use FULL zip + FORCE-UPGRADE.bat:
echo https://github.com/bigred747/software/raw/main/Paper-Trader.zip
:done
pause
exit /b 0
