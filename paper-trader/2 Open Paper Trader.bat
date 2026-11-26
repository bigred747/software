@echo off
setlocal EnableExtensions EnableDelayedExpansion
cd /d "%~dp0"
title Paper Trader
echo %~dp0 | findstr /I /C:"\Temp" | findstr /I /C:".zip." >nul
if not errorlevel 1 goto FromZip
goto Normal

:FromZip
echo Unblock
echo If Windows shows an Unblock box, check Unblock and open this file.
echo That box lets this copy open. It stays on this computer.
echo This file was opened inside the zip. Extracting the whole Paper Trader now.
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
if not exist "%USERPROFILE%\Documents" mkdir "%USERPROFILE%\Documents"
set "STAGE=%USERPROFILE%\Documents\PaperTrader-setup"
if exist "%STAGE%" set "STAGE=%USERPROFILE%\Documents\PaperTrader-setup-%RANDOM%%RANDOM%"
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
if not exist "!STAGE!\paper-trader\launch.py" goto BadZip
start "" "!STAGE!\paper-trader\Start-Paper-Trader.bat"
echo The full copy is in Documents. Use the window that just opened.
echo Older Paper Trader folders are left alone.
echo Opening the new copy.
pause
exit /b 0

:NoZip
echo Could not find Paper-Trader.zip in Downloads, Desktop, Documents, or OneDrive.
echo Save the zip in Downloads, then double-click 1 INSTALL Paper Trader.bat.
pause
exit /b 1

:BadZip
echo Could not extract Paper-Trader.zip.
echo Windows Git tar cannot unpack this zip — this installer uses Python instead.
echo Close EVERY Paper Trader window, then run 1 INSTALL Paper Trader.bat.
echo Or right-click the zip - Extract All - then open Start-Paper-Trader.bat.
pause
exit /b 1

:Normal
call "%~dp0Start-Paper-Trader.bat"
