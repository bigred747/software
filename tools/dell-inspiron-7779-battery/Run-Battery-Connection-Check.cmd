@echo off
setlocal
title Dell Inspiron 17 7779 battery connection
cd /d "%~dp0"
net session >nul 2>&1
if not "%errorlevel%"=="0" (
  echo Requesting administrator permission...
  powershell.exe -NoProfile -Command "Start-Process -FilePath '%~f0' -Verb RunAs"
  exit /b
)
echo.
echo Dell Inspiron 17 7779  --  65W charge path
echo Leave the 65W cord plugged into the wall and the laptop.
echo.
set BATTERY_CHECK_NO_PAUSE=1
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0Fix-Dell-Inspiron-7779-Battery.ps1"
echo.
pause
endlocal
