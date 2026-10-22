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
for %%R in ("%USERPROFILE%\Downloads" "%USERPROFILE%\Desktop" "%USERPROFILE%\Documents") do for /f "delims=" %%F in ('dir /b /a-d /o-d "%%~R\Paper-Trader*.zip" 2^>nul') do if not defined ZIP set "ZIP=%%~R\%%F"
if not defined ZIP goto NoZip
if not exist "%USERPROFILE%\Documents" mkdir "%USERPROFILE%\Documents"
taskkill /F /FI "WINDOWTITLE eq Paper Trader" >nul 2>&1
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
if defined ZIP del /f /a "!ZIP!:Zone.Identifier" >nul 2>&1
tar -xf "!ZIP!" -C "%STAGE%"
if errorlevel 1 goto BadZip
for /r "%STAGE%" %%F in (*) do del /f /a "%%F:Zone.Identifier" >nul 2>&1
if not exist "%STAGE%\paper-trader\launch.py" goto BadZip
start "" "%STAGE%\paper-trader\Start-Paper-Trader.bat"
echo The full copy is in Documents. Use the window that just opened.
pause
exit /b 0

:NoZip
echo Could not find Paper-Trader.zip in Downloads, Desktop, or Documents.
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
call "%~dp0Start-Paper-Trader.bat"
