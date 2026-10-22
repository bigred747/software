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
taskkill /F /FI "WINDOWTITLE eq Paper Trader" >nul 2>&1
set "STAGE=%USERPROFILE%\Documents\PaperTrader-setup"
if exist "%STAGE%" rmdir /s /q "%STAGE%"
if exist "%STAGE%" set "STAGE=%USERPROFILE%\Documents\PaperTrader-setup-%RANDOM%"
mkdir "%STAGE%"
tar -xf "!ZIP!" -C "%STAGE%"
if errorlevel 1 goto BadZip
if not exist "%STAGE%\paper-trader\launch.py" goto BadZip
start "" "%STAGE%\paper-trader\1 Allow Paper Trader.bat"
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
pause
exit /b 1

:Normal
echo Step 1 of 2. Install the newest Paper Trader and remove older copies.
powershell -NoProfile -Command "Get-ChildItem -LiteralPath $env:CD -Recurse -File | Unblock-File"
set "PY=%~dp0runtime\python.exe"
if not exist "%PY%" set "PY=python"
"%PY%" "%~dp0launch.py" install > "%TEMP%\paper-trader-install.txt"
if errorlevel 1 (
  echo Could not install the newest copy.
  pause
  exit /b 1
)
set "DEST="
set "ACTION="
for /f "usebackq delims=" %%A in ("%TEMP%\paper-trader-install.txt") do (
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
powershell -NoProfile -Command "$dest = $env:DEST; $desk = [Environment]::GetFolderPath('Desktop'); $lnk = Join-Path $desk 'Paper Trader.lnk'; $sc = (New-Object -ComObject WScript.Shell).CreateShortcut($lnk); $sc.TargetPath = (Join-Path $dest '2 Open Paper Trader.bat'); $sc.WorkingDirectory = $dest; $sc.WindowStyle = 1; $sc.Description = 'Open Paper Trader'; $sc.Save()"
if errorlevel 1 (
  echo Windows did not allow the folder. Right-click this file, open Properties, check Unblock, click OK, and run this file again.
  pause
  exit /b 1
)
echo The newest Paper Trader is the only copy in Documents.
echo Step 2 of 2: click Paper Trader on the desktop.
pause
if /I "!ACTION!"=="remove-source" (
  set "OLD=%~dp0"
  if "!OLD:~-1!"=="\" set "OLD=!OLD:~0,-1!"
  > "%TEMP%\remove-old-paper-trader.bat" echo @echo off
  >> "%TEMP%\remove-old-paper-trader.bat" echo rd /s /q "!OLD!"
  start "" cmd /c "timeout /t 1 /nobreak >nul & call \"%TEMP%\remove-old-paper-trader.bat\""
)
