@echo off
setlocal EnableExtensions EnableDelayedExpansion
cd /d "%~dp0"
echo Step 1 of 2. Install the newest Paper Trader and remove older copies.
echo %~dp0 | findstr /I /C:"\AppData\Local\Temp\" | findstr /I /C:".zip." >nul
if not errorlevel 1 (
  echo This copy is still inside the zip. Right-click Paper-Trader.zip, choose Extract All, then run this file from the extracted folder.
  pause
  exit /b 1
)
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
