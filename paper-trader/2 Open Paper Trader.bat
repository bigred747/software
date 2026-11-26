@echo off
setlocal EnableExtensions EnableDelayedExpansion
cd /d "%~dp0"
title Paper Trader
echo %~dp0 | findstr /I /C:"\Temp" | findstr /I /C:".zip." >nul
if not errorlevel 1 goto FromZip
goto Normal

:FromZip
echo Opened from inside the zip — unpacking once, then starting.
set "ZIP="
set "PY="
if exist "%~dp0runtime\python.exe" set "PY=%~dp0runtime\python.exe"
if not defined PY if exist "%USERPROFILE%\Documents\Paper Trader\runtime\python.exe" set "PY=%USERPROFILE%\Documents\Paper Trader\runtime\python.exe"
if not defined PY set "PY=python"
if exist "%~dp0tools\pick_upgrade_zip.py" for /f "delims=" %%Z in ('"%PY%" "%~dp0tools\pick_upgrade_zip.py" 2^>nul') do set "ZIP=%%Z"
if not defined ZIP if exist "%USERPROFILE%\Downloads\Paper-Trader.zip" set "ZIP=%USERPROFILE%\Downloads\Paper-Trader.zip"
if not defined ZIP goto NoZip
if /I "!ZIP!"=="NONE" goto NoZip
if not exist "%USERPROFILE%\Documents" mkdir "%USERPROFILE%\Documents"
set "STAGE=%USERPROFILE%\Documents\PaperTrader-setup"
if exist "%STAGE%" set "STAGE=%USERPROFILE%\Documents\PaperTrader-setup-%RANDOM%%RANDOM%"
mkdir "%STAGE%" 2>nul
if not exist "%STAGE%\." goto BadZip
if exist "%~dp0tools\extract_zip.py" (
  "%PY%" "%~dp0tools\extract_zip.py" "!ZIP!" "!STAGE!" > "%TEMP%\paper-trader-extract.txt" 2>&1
) else (
  "%PY%" -c "import zipfile,sys; zipfile.ZipFile(sys.argv[1]).extractall(sys.argv[2])" "!ZIP!" "!STAGE!" > "%TEMP%\paper-trader-extract.txt" 2>&1
)
if errorlevel 1 goto BadZip
if not exist "!STAGE!\paper-trader\Start-Paper-Trader.bat" goto BadZip
start "Paper Trader" /D "!STAGE!\paper-trader" cmd /c "Start-Paper-Trader.bat"
exit /b 0

:NoZip
echo Could not find Paper-Trader.zip. Extract All, then double-click Start-Paper-Trader.bat.
pause
exit /b 1

:BadZip
echo Could not unpack. Extract All, then double-click Start-Paper-Trader.bat.
pause
exit /b 1

:Normal
rem One click through to Start — opens the desk.
call "%~dp0Start-Paper-Trader.bat"
