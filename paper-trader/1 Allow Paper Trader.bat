@echo off
setlocal
cd /d "%~dp0"
echo Step 1 of 2. Allow Paper Trader so this computer recognizes the folder.
echo %~dp0 | findstr /I /C:"\AppData\Local\Temp\" | findstr /I /C:".zip." >nul
if not errorlevel 1 (
  echo This copy is still inside the zip. Right-click Paper-Trader.zip, choose Extract All, then run this file from the extracted folder.
  pause
  exit /b 1
)
powershell -NoProfile -Command "Get-ChildItem -LiteralPath $env:CD -Recurse -File | Unblock-File; $desk = [Environment]::GetFolderPath('Desktop'); $lnk = Join-Path $desk 'Paper Trader.lnk'; $sc = (New-Object -ComObject WScript.Shell).CreateShortcut($lnk); $sc.TargetPath = (Join-Path $env:CD '2 Open Paper Trader.bat'); $sc.WorkingDirectory = $env:CD; $sc.WindowStyle = 1; $sc.Description = 'Open Paper Trader'; $sc.Save()"
if errorlevel 1 (
  echo Windows did not allow the folder. Right-click this file, open Properties, check Unblock, click OK, and run this file again.
  pause
  exit /b 1
)
echo This computer recognizes Paper Trader.
echo Step 2 of 2: click Paper Trader on the desktop.
pause
