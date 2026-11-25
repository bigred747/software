@echo off
setlocal EnableExtensions EnableDelayedExpansion
cd /d "%~dp0"
title Paper Trader FORCE UPGRADE
echo.
echo ============================================================
echo  FORCE UPGRADE — replaces Documents\Paper Trader completely
echo ============================================================
echo.
set "PY=%~dp0runtime\python.exe"
if not exist "%PY%" set "PY=python"
if not exist "%USERPROFILE%\Documents" mkdir "%USERPROFILE%\Documents"

set "SEAL="
if exist "%~dp0VERSION" set /p SEAL=<"%~dp0VERSION"
if not defined SEAL (
  echo This FORCE-UPGRADE.bat must sit next to VERSION inside the extracted zip.
  echo Right-click Paper-Trader.zip - Extract All - open that folder - run FORCE-UPGRADE.bat
  pause
  exit /b 1
)
echo This pack seal is !SEAL!
echo.
echo 1. Close EVERY Paper Trader window and Edge desk tab now.
echo 2. This will replace Documents\Paper Trader with seal !SEAL!
echo.
timeout /t 4 >nul

"%PY%" "%~dp0launch.py" force-upgrade > "%USERPROFILE%\Documents\paper-trader-force-upgrade.txt" 2>&1
if errorlevel 1 (
  echo.
  echo FORCE UPGRADE FAILED.
  type "%USERPROFILE%\Documents\paper-trader-force-upgrade.txt"
  echo.
  echo If it says close windows / file in use: close Paper Trader + Edge, then run again.
  pause
  exit /b 1
)

set "DEST=%USERPROFILE%\Documents\Paper Trader"
set "CHECK="
if exist "!DEST!\VERSION" set /p CHECK=<"!DEST!\VERSION"
echo.
if /I not "!CHECK!"=="!SEAL!" (
  echo UPGRADE DID NOT LAND. Documents\Paper Trader still shows: !CHECK!
  echo Expected: !SEAL!
  type "%USERPROFILE%\Documents\paper-trader-force-upgrade.txt"
  pause
  exit /b 1
)

echo SUCCESS — Documents\Paper Trader is now !CHECK!
> "%USERPROFILE%\Desktop\Paper Trader.bat" echo @echo off
>> "%USERPROFILE%\Desktop\Paper Trader.bat" echo cd /d "!DEST!"
>> "%USERPROFILE%\Desktop\Paper Trader.bat" echo title Paper Trader !CHECK!
>> "%USERPROFILE%\Desktop\Paper Trader.bat" echo call "Start-Paper-Trader.bat"
if exist "%USERPROFILE%\OneDrive\Desktop" (
  > "%USERPROFILE%\OneDrive\Desktop\Paper Trader.bat" echo @echo off
  >> "%USERPROFILE%\OneDrive\Desktop\Paper Trader.bat" echo cd /d "!DEST!"
  >> "%USERPROFILE%\OneDrive\Desktop\Paper Trader.bat" echo title Paper Trader !CHECK!
  >> "%USERPROFILE%\OneDrive\Desktop\Paper Trader.bat" echo call "Start-Paper-Trader.bat"
)
echo.
echo Close every old desk window.
echo Opening the new desk now — seal must say Paper !CHECK!
echo.
start "" "!DEST!\Start-Paper-Trader.bat"
pause
exit /b 0
