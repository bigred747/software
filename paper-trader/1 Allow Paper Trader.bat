@echo off
setlocal EnableExtensions EnableDelayedExpansion
cd /d "%~dp0"
echo %~dp0 | findstr /I /C:"\Temp" | findstr /I /C:".zip." >nul
if not errorlevel 1 goto FromZip
goto Normal

:FromZip
echo This file was opened inside the zip. Extracting the whole Paper Trader now.
set "ZIP="
for %%R in ("%USERPROFILE%\Downloads" "%USERPROFILE%\Desktop" "%USERPROFILE%\Documents") do for /f "delims=" %%F in ('dir /b /a-d /o-d "%%~R\Paper-Trader*.zip" 2^>nul') do if not defined ZIP set "ZIP=%%~R\%%F"
if not defined ZIP goto NoZip
if not exist "%USERPROFILE%\Documents" mkdir "%USERPROFILE%\Documents"
set "SETUP=%USERPROFILE%\Documents\PaperTrader-setup"
if not exist "%SETUP%" mkdir "%SETUP%"
tar -xf "!ZIP!" -C "%SETUP%"
if errorlevel 1 goto BadZip
if not exist "%SETUP%\paper-trader\launch.py" goto BadZip
if exist "%USERPROFILE%\Documents\iphone" echo Documents\iphone is left in place.
start "" "%SETUP%\paper-trader\1 Allow Paper Trader.bat"
echo The full copy is in Documents\PaperTrader-setup. Use the window that just opened.
pause
exit /b 0

:NoZip
echo Could not find Paper-Trader.zip in Downloads, Desktop, or Documents.
echo Save the zip in Downloads, then double-click this file again.
pause
exit /b 1

:BadZip
echo Could not extract Paper-Trader.zip.
pause
exit /b 1

:Normal
echo Step 1 of 2. Install the newest Paper Trader in Documents.
set "PY=%~dp0runtime\python.exe"
if not exist "%PY%" set "PY=python"
if not exist "%USERPROFILE%\Documents" mkdir "%USERPROFILE%\Documents"
set "LOG=%USERPROFILE%\Documents\paper-trader-install.txt"
"%PY%" "%~dp0launch.py" install > "%LOG%"
if errorlevel 1 (
  echo Could not install the newest copy.
  pause
  exit /b 1
)
set "DEST="
set "ACTION="
for /f "usebackq delims=" %%A in ("%LOG%") do (
  if not defined DEST (
    set "DEST=%%A"
  ) else if not defined ACTION (
    set "ACTION=%%A"
  )
)
if not defined DEST (
  echo Could not find the installed folder.
  pause
  exit /b 1
)
echo The newest Paper Trader is in Documents.
echo Step 2 of 2: double-click Paper Trader.bat on the desktop.
pause
if /I "!ACTION!"=="remove-source" echo The extra download copy can be deleted by hand.
