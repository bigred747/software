@echo off
setlocal EnableExtensions EnableDelayedExpansion
cd /d "%~dp0"
echo %~dp0 | findstr /I /C:"\Temp" | findstr /I /C:".zip." >nul
if not errorlevel 1 goto FromZip
goto Normal

:FromZip
echo Unblock
echo If Windows shows an Unblock box, check Unblock and open this file.
echo That box lets this copy open. It stays on this computer.
echo This file was opened inside the zip. ONE-CLICK unpack starting.
set "ZIP="
set "PY="
if exist "%~dp0runtime\python.exe" set "PY=%~dp0runtime\python.exe"
if not defined PY if exist "%USERPROFILE%\Documents\Paper Trader\runtime\python.exe" set "PY=%USERPROFILE%\Documents\Paper Trader\runtime\python.exe"
if not defined PY set "PY=python"
if exist "%~dp0tools\pick_upgrade_zip.py" for /f "delims=" %%Z in ('"%PY%" "%~dp0tools\pick_upgrade_zip.py" 2^>nul') do set "ZIP=%%Z"
if not defined ZIP if exist "%USERPROFILE%\Downloads\Paper-Trader.zip" set "ZIP=%USERPROFILE%\Downloads\Paper-Trader.zip"
if not defined ZIP goto NoZip
if /I "!ZIP!"=="NONE" goto NoZip
echo Using zip: !ZIP!
echo Close every Paper Trader window and black cmd desk window first.
rem Unpack in %%TEMP%% — Git tar cannot open zip; Documents/OneDrive can lock.
set "STAGE=%TEMP%\PaperTrader-install-%RANDOM%%RANDOM%"
mkdir "%STAGE%" 2>nul
if not exist "%STAGE%\." goto BadZip
echo Extracting into: !STAGE!
if exist "%~dp0tools\extract_zip.py" (
  "%PY%" "%~dp0tools\extract_zip.py" "!ZIP!" "!STAGE!" > "%TEMP%\paper-trader-extract.txt" 2>&1
) else (
  "%PY%" -c "import zipfile,sys; zipfile.ZipFile(sys.argv[1]).extractall(sys.argv[2])" "!ZIP!" "!STAGE!" > "%TEMP%\paper-trader-extract.txt" 2>&1
)
set "ERR=!errorlevel!"
type "%TEMP%\paper-trader-extract.txt"
if not "!ERR!"=="0" goto BadZip
if not exist "!STAGE!\paper-trader\FORCE-UPGRADE.bat" goto BadZip
start "" "!STAGE!\paper-trader\FORCE-UPGRADE.bat"
echo Extracted. FORCE-UPGRADE will replace Documents\Paper Trader.
echo Opening the new copy.
echo Press any key to close this setup window.
pause >nul
exit /b 0

:NoZip
echo Could not find Paper-Trader.zip in Downloads, Desktop, Documents, or OneDrive.
echo Save the zip in Downloads, then double-click 1 INSTALL Paper Trader.bat.
pause
exit /b 1

:BadZip
echo Could not extract Paper-Trader.zip.
echo Windows Git tar cannot unpack this zip — this installer uses Python instead.
echo Close EVERY Paper Trader window, then run 1 INSTALL Paper Trader.bat from Downloads extract.
echo Or right-click the zip - Extract All - then open FORCE-UPGRADE.bat.
pause
exit /b 1

:Normal
echo Unblock
echo If Windows shows an Unblock box, check Unblock and open this file.
echo Prefer 1 INSTALL Paper Trader.bat or FORCE-UPGRADE.bat for a full replace.
echo.
if exist "%~dp0FORCE-UPGRADE.bat" (
  echo Handing off to FORCE-UPGRADE.bat for a full replace.
  start "" "%~dp0FORCE-UPGRADE.bat"
  exit /b 0
)
echo FORCE-UPGRADE.bat missing — falling back to install.
set "PY=%~dp0runtime\python.exe"
if not exist "%PY%" set "PY=python"
"%PY%" "%~dp0launch.py" force-upgrade > "%USERPROFILE%\Documents\paper-trader-force-upgrade.txt" 2>&1
if errorlevel 1 (
  echo Could not force-upgrade. See Documents\paper-trader-force-upgrade.txt
  type "%USERPROFILE%\Documents\paper-trader-force-upgrade.txt"
  pause
  exit /b 1
)
set "DEST=%USERPROFILE%\Documents\Paper Trader"
set "WANT="
if exist "%~dp0VERSION" set /p WANT=<"%~dp0VERSION"
set "SEAL="
if exist "!DEST!\VERSION" set /p SEAL=<"!DEST!\VERSION"
if /I not "!SEAL!"=="!WANT!" (
  echo INSTALL DID NOT LAND !WANT!. Still shows: !SEAL!
  pause
  exit /b 1
)
echo Seal check OK — Documents\Paper Trader is !SEAL!
echo remove-source
start "" "!DEST!\Start-Paper-Trader.bat"
pause
exit /b 0
