@echo off
setlocal EnableExtensions
title Paper Trader — fix desktop shortcut
set "DESK=%USERPROFILE%\Documents\Paper Trader"
if not exist "%DESK%\launch.py" (
  echo Documents\Paper Trader is missing. Run 1 Allow Paper Trader.bat first.
  pause
  exit /b 1
)
echo Pointing desktop shortcut at:
echo %DESK%
> "%USERPROFILE%\Desktop\Paper Trader.bat" echo @echo off
>> "%USERPROFILE%\Desktop\Paper Trader.bat" echo cd /d "%DESK%"
>> "%USERPROFILE%\Desktop\Paper Trader.bat" echo title Paper Trader
>> "%USERPROFILE%\Desktop\Paper Trader.bat" echo call "Start-Paper-Trader.bat"
if exist "%USERPROFILE%\OneDrive\Desktop" (
  > "%USERPROFILE%\OneDrive\Desktop\Paper Trader.bat" echo @echo off
  >> "%USERPROFILE%\OneDrive\Desktop\Paper Trader.bat" echo cd /d "%DESK%"
  >> "%USERPROFILE%\OneDrive\Desktop\Paper Trader.bat" echo title Paper Trader
  >> "%USERPROFILE%\OneDrive\Desktop\Paper Trader.bat" echo call "Start-Paper-Trader.bat"
)
echo Removing leftover PaperTrader-setup folders...
for /d %%D in ("%USERPROFILE%\Documents\PaperTrader-setup*") do rmdir /s /q "%%D" 2>nul
echo Done. Double-click Paper Trader.bat on the desktop.
pause
exit /b 0
