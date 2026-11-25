@echo off
setlocal EnableExtensions EnableDelayedExpansion
cd /d "%~dp0"
title Paper Trader ONE-CLICK INSTALL
echo.
echo ============================================================
echo  Paper Trader — one-click install
echo ============================================================
echo.
echo Close every Paper Trader window and black cmd desk window.
echo.

set "ZIP="
set "PY="
if exist "%~dp0runtime\python.exe" set "PY=%~dp0runtime\python.exe"
if not defined PY if exist "%USERPROFILE%\Documents\Paper Trader\runtime\python.exe" set "PY=%USERPROFILE%\Documents\Paper Trader\runtime\python.exe"
if not defined PY set "PY=python"

if exist "%~dp0tools\pick_upgrade_zip.py" (
  for /f "delims=" %%Z in ('"%PY%" "%~dp0tools\pick_upgrade_zip.py" 2^>nul') do set "ZIP=%%Z"
)
if not defined ZIP if exist "%USERPROFILE%\Downloads\Paper-Trader.zip" set "ZIP=%USERPROFILE%\Downloads\Paper-Trader.zip"
if not defined ZIP if exist "%~dp0..\Paper-Trader.zip" set "ZIP=%~dp0..\Paper-Trader.zip"
if not defined ZIP goto NoZip
if /I "!ZIP!"=="NONE" goto NoZip

echo Using zip:
echo   !ZIP!
echo.

rem Extract outside Documents/OneDrive so locks cannot block the unpack.
set "STAGE=%TEMP%\PaperTrader-install-%RANDOM%%RANDOM%"
mkdir "%STAGE%" 2>nul
if not exist "%STAGE%\." goto BadStage

echo Unpacking with Python (not Git tar)...
"%PY%" "%~dp0tools\extract_zip.py" "!ZIP!" "%STAGE%" > "%TEMP%\paper-trader-extract.txt" 2>&1
set "ERR=!errorlevel!"
type "%TEMP%\paper-trader-extract.txt"
if not "!ERR!"=="0" goto BadZip
if not exist "%STAGE%\paper-trader\launch.py" goto BadZip
if not exist "%STAGE%\paper-trader\FORCE-UPGRADE.bat" goto BadZip

echo.
echo Installing into Documents\Paper Trader ...
call "%STAGE%\paper-trader\FORCE-UPGRADE.bat"
exit /b %errorlevel%

:NoZip
echo Could not find Paper-Trader.zip in Downloads.
echo Save the zip to Downloads, then double-click this INSTALL file again.
pause
exit /b 1

:BadStage
echo Could not create a temp install folder.
pause
exit /b 1

:BadZip
echo.
echo ONE-CLICK unpack failed.
echo The zip may be incomplete, or Python was not found.
echo.
echo Fallback: right-click Paper-Trader.zip - Extract All -
echo then open paper-trader and run FORCE-UPGRADE.bat
echo.
pause
exit /b 1
