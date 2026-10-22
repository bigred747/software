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
for %%R in ("%USERPROFILE%\Downloads" "%USERPROFILE%\Desktop" "%USERPROFILE%\Documents" "%USERPROFILE%\OneDrive\Downloads" "%USERPROFILE%\OneDrive\Desktop" "%USERPROFILE%\OneDrive\Documents") do if exist "%%~R" for /f "delims=" %%F in ('dir /b /a-d /o-d "%%~R\Paper-Trader*.zip" 2^>nul') do if not defined ZIP set "ZIP=%%~R\%%F"
if not defined ZIP if defined OneDrive for %%R in ("%OneDrive%\Downloads" "%OneDrive%\Desktop" "%OneDrive%\Documents") do if exist "%%~R" for /f "delims=" %%F in ('dir /b /a-d /o-d "%%~R\Paper-Trader*.zip" 2^>nul') do if not defined ZIP set "ZIP=%%~R\%%F"
if not defined ZIP goto NoZip
if not exist "%USERPROFILE%\Documents" mkdir "%USERPROFILE%\Documents"
set "STAGE=%USERPROFILE%\Documents\PaperTrader-setup"
if exist "%STAGE%" rmdir /s /q "%STAGE%"
if exist "%STAGE%" set "STAGE=%USERPROFILE%\Documents\PaperTrader-setup-%RANDOM%"
mkdir "%STAGE%"
mkdir "%STAGE%\paper-trader" 2>nul
mkdir "%STAGE%\paper-trader\paper_trader" 2>nul
mkdir "%STAGE%\paper-trader\web" 2>nul
mkdir "%STAGE%\paper-trader\tools" 2>nul
mkdir "%STAGE%\paper-trader\iphone" 2>nul
mkdir "%STAGE%\paper-trader\runtime" 2>nul
tar -xf "!ZIP!" -C "%STAGE%"
if errorlevel 1 goto BadZip
if not exist "%STAGE%\paper-trader\launch.py" goto BadZip
start "" "%STAGE%\paper-trader\1 Allow Paper Trader.bat"
echo The full copy is in Documents. Use the window that just opened.
pause
exit /b 0

:NoZip
echo Could not find Paper-Trader.zip in Downloads, Desktop, Documents, or OneDrive.
echo Save the zip in Downloads, then double-click this file again.
pause
exit /b 1

:BadZip
echo Could not extract Paper-Trader.zip.
echo The program files could not be written into Documents.
echo If a security program blocked them, allow Paper Trader, then double-click this file again.
pause
exit /b 1

:Normal
echo Unblock
echo If Windows shows an Unblock box, check Unblock and open this file.
echo That box lets this copy open. It stays on this computer.
echo Step 1 of 2. Install the newest Paper Trader and remove older copies.
set "PY=%~dp0runtime\python.exe"
if not exist "%PY%" set "PY=python"
if not exist "%USERPROFILE%\Documents" mkdir "%USERPROFILE%\Documents"
"%PY%" "%~dp0launch.py" install > "%USERPROFILE%\Documents\paper-trader-install.txt"
if errorlevel 1 (
  echo Could not install the newest copy.
  pause
  exit /b 1
)
set "DEST="
set "ACTION="
for /f "usebackq delims=" %%A in ("%USERPROFILE%\Documents\paper-trader-install.txt") do (
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
call :DeskLink "%DEST%"
echo The newest Paper Trader is the only copy in Documents.
echo Step 2 of 2: double-click Paper Trader.bat on the desktop.
pause
if /I "!ACTION!"=="remove-source" echo The older copy is no longer the one that opens.
exit /b 0

:DeskLink
call :WriteLink "%USERPROFILE%\Desktop" "%~1"
call :WriteLink "%USERPROFILE%\OneDrive\Desktop" "%~1"
if defined OneDrive call :WriteLink "%OneDrive%\Desktop" "%~1"
echo Paper Trader.bat is on the desktop. Double-click it to open the desk.
exit /b 0

:WriteLink
if not exist "%~1" exit /b 0
> "%~1\Paper Trader.bat" echo @echo off
>> "%~1\Paper Trader.bat" echo cd /d "%~2"
>> "%~1\Paper Trader.bat" echo title Paper Trader
>> "%~1\Paper Trader.bat" echo call "2 Open Paper Trader.bat"
exit /b 0
