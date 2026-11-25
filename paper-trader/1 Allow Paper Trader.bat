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
echo This file was opened inside the zip. Extracting the whole Paper Trader now.
set "ZIP="
set "PYTRY=python"
if exist "%~dp0runtime\python.exe" set "PYTRY=%~dp0runtime\python.exe"
for /f "delims=" %%Z in ('"%PYTRY%" "%~dp0tools\pick_upgrade_zip.py" 2^>nul') do set "ZIP=%%Z"
if not defined ZIP if exist "%USERPROFILE%\Downloads\Paper-Trader.zip" set "ZIP=%USERPROFILE%\Downloads\Paper-Trader.zip"
if not defined ZIP goto NoZip
echo Using zip: !ZIP!
echo Close every Paper Trader window and black cmd desk window first.
if not exist "%USERPROFILE%\Documents" mkdir "%USERPROFILE%\Documents"
rem Never reuse Documents\PaperTrader-setup — a running desk locks learn.lock there.
set "STAGE=%USERPROFILE%\Documents\PaperTrader-setup-%RANDOM%%RANDOM%"
mkdir "%STAGE%" 2>nul
if not exist "%STAGE%\." set "STAGE=%USERPROFILE%\Documents\PaperTrader-setup-%RANDOM%%RANDOM%"
mkdir "%STAGE%" 2>nul
if not exist "%STAGE%\." goto BadZip
echo Extracting into: !STAGE!
tar -xf "!ZIP!" -C "!STAGE!"
if errorlevel 1 goto BadZip
if not exist "!STAGE!\paper-trader\FORCE-UPGRADE.bat" goto BadZip
start "" "!STAGE!\paper-trader\FORCE-UPGRADE.bat"
echo Extracted. The FORCE-UPGRADE window will replace Documents\Paper Trader.
pause
exit /b 0

:NoZip
echo Could not find Paper-Trader.zip in Downloads, Desktop, Documents, or OneDrive.
echo Save the zip in Downloads, then double-click this file again.
pause
exit /b 1

:BadZip
echo Could not extract Paper-Trader.zip.
echo Usually Documents\PaperTrader-setup is locked by a running Paper Trader.
echo Close EVERY Paper Trader window and black cmd window, then try again.
echo Or right-click the zip - Extract All - then open FORCE-UPGRADE.bat.
pause
exit /b 1

:Normal
echo Unblock
echo If Windows shows an Unblock box, check Unblock and open this file.
echo Prefer FORCE-UPGRADE.bat when Documents\Paper Trader is stuck on an old seal.
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
start "" "!DEST!\Start-Paper-Trader.bat"
pause
exit /b 0
