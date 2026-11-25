@echo off
setlocal EnableExtensions EnableDelayedExpansion
cd /d "%~dp0"
title Paper Trader FORCE UPGRADE
echo.
echo ============================================================
echo  FORCE UPGRADE — moves old Documents\Paper Trader aside
echo  then installs THIS pack cleanly
echo ============================================================
echo.
set "PY=%~dp0runtime\python.exe"
if not exist "%PY%" set "PY=python"
if not exist "%USERPROFILE%\Documents" mkdir "%USERPROFILE%\Documents"

set "SEAL="
if exist "%~dp0VERSION" set /p SEAL=<"%~dp0VERSION"
if not defined SEAL (
  echo FORCE-UPGRADE.bat must sit next to VERSION in the extracted zip folder.
  echo Right-click Paper-Trader.zip - Extract All - open paper-trader - run this file.
  pause
  exit /b 1
)
echo This extracted pack seal is: !SEAL!
echo.
echo CLOSE now:
echo  - every Paper Trader window
echo  - every Edge tab on 127.0.0.1
echo  - any black cmd window titled Paper Trader
echo.
timeout /t 5 >nul

"%PY%" "%~dp0launch.py" force-upgrade > "%USERPROFILE%\Documents\paper-trader-force-upgrade.txt" 2>&1
set "ERR=!errorlevel!"
type "%USERPROFILE%\Documents\paper-trader-force-upgrade.txt"
echo.
if not "!ERR!"=="0" (
  echo FORCE UPGRADE FAILED.
  echo Close all desk windows and run this again from the NEW extract.
  pause
  exit /b 1
)

"%PY%" "%~dp0launch.py" check-seal > "%USERPROFILE%\Documents\paper-trader-check-seal.txt" 2>&1
type "%USERPROFILE%\Documents\paper-trader-check-seal.txt"
echo.

set "DEST=%USERPROFILE%\Documents\Paper Trader"
set "CHECK="
if exist "!DEST!\VERSION" set /p CHECK=<"!DEST!\VERSION"
if /I not "!CHECK!"=="!SEAL!" (
  echo STILL NOT !SEAL! — Documents shows: !CHECK!
  echo You must close the running desk so files can be replaced.
  pause
  exit /b 1
)

echo SUCCESS — Documents\Paper Trader is !CHECK!
> "%USERPROFILE%\Desktop\Paper Trader.bat" (
  echo @echo off
  echo cd /d "!DEST!"
  echo title Paper Trader !CHECK!
  echo if exist "SEAL.txt" type "SEAL.txt"
  echo call "Start-Paper-Trader.bat"
)
echo Desktop Paper Trader.bat rewritten to Documents\Paper Trader.
echo.
echo Opening the new desk. Seal must say Paper !CHECK!
echo If you still see 1.10.40, you clicked an old Edge tab — close it.
echo.
start "" "!DEST!\Start-Paper-Trader.bat"
pause
exit /b 0
