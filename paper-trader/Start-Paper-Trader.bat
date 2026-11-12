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
if exist "%SETUP%\paper-trader\runtime\python.exe" (
  "%SETUP%\paper-trader\runtime\python.exe" "%SETUP%\paper-trader\launch.py" install
) else (
  python "%SETUP%\paper-trader\launch.py" install
)
if errorlevel 1 goto BadZip
start "" "%USERPROFILE%\Documents\Paper Trader\Start-Paper-Trader.bat"
echo Paper Trader is opening from Documents. You can close this window.
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
echo Paper Trader stays on this computer. It replays sample prices and does not place broker orders.
if exist "%~dp0runtime\python.exe" (
  "%~dp0runtime\python.exe" "%~dp0launch.py" serve
  goto done
)
where python >nul 2>&1
if not errorlevel 1 (
  python "%~dp0launch.py" serve
  goto done
)
where py >nul 2>&1
if not errorlevel 1 (
  py -3 "%~dp0launch.py" serve
  goto done
)
echo Python was not found. Extract Paper-Trader.zip again, then run 1 Allow Paper Trader.bat.
:done
pause
