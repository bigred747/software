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

echo %~dp0 | findstr /I /C:"\Temp" | findstr /I /C:".zip." >nul
if not errorlevel 1 goto FromTempZip

if not exist "%~dp0launch.py" goto MissingLaunch
if not exist "%~dp0VERSION" goto MissingLaunch
if not exist "%~dp0web\app.js" goto MissingLaunch
goto Ready

:FromTempZip
echo STOP — you opened FORCE-UPGRADE from inside the zip Temp folder.
echo Windows path looks like:
echo   AppData\Local\Temp\..._Paper-Trader....zip...\paper-trader
echo That Temp extract does not have a full working copy.
echo.
echo Do this exactly:
echo   1. Close this window
echo   2. Open Downloads
echo   3. Right-click Paper-Trader.zip  (not Paper-Trader ^(1^).zip if you can avoid it)
echo   4. Choose Extract All
echo   5. Extract to: Downloads\Paper-Trader
echo   6. Open Downloads\Paper-Trader\paper-trader
echo   7. Double-click FORCE-UPGRADE.bat in THAT folder
echo.
pause
exit /b 1

:MissingLaunch
echo STOP — this folder is incomplete (launch.py / VERSION / web missing).
echo Right-click the zip - Extract All - then run FORCE-UPGRADE from the extract.
pause
exit /b 1

:Ready
set "PY=%~dp0runtime\python.exe"
if not exist "%PY%" set "PY=python"
if not exist "%USERPROFILE%\Documents" mkdir "%USERPROFILE%\Documents"

set "SEAL="
set /p SEAL=<"%~dp0VERSION"
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
  echo Close all desk windows and run this again from the Extract All folder.
  pause
  exit /b 1
)

set "DEST=%USERPROFILE%\Documents\Paper Trader"
set "CHECK="
if exist "!DEST!\VERSION" set /p CHECK=<"!DEST!\VERSION"
echo.
echo Documents\Paper Trader\VERSION now:
echo !CHECK!
echo.
if /I not "!CHECK!"=="!SEAL!" (
  echo STILL NOT !SEAL! — Documents shows: !CHECK!
  echo Close the running desk so files can be replaced, then retry.
  pause
  exit /b 1
)

findstr /C:"const VERSION = \"!SEAL!\"" "!DEST!\web\app.js" >nul
if errorlevel 1 (
  echo web\app.js did not update to !SEAL!.
  pause
  exit /b 1
)

echo SUCCESS — Documents\Paper Trader is !CHECK!
> "%USERPROFILE%\Desktop\Paper Trader.bat" (
  echo @echo off
  echo cd /d "!DEST!"
  echo title Paper Trader !CHECK!
  echo if exist "SEAL.txt" type "SEAL.txt"
  echo call "2 Open Paper Trader.bat"
)
echo Desktop Paper Trader.bat rewritten.
echo.
echo Opening the new desk. Seal must say Paper !CHECK!
echo If you still see an old seal, close that old Edge tab.
echo.
start "Paper Trader" /D "!DEST!" cmd /c "2 Open Paper Trader.bat"
pause
exit /b 0
