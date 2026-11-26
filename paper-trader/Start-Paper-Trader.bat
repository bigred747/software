@echo off
setlocal EnableExtensions EnableDelayedExpansion
cd /d "%~dp0"
title Paper Trader
echo %~dp0 | findstr /I /C:"\Temp" | findstr /I /C:".zip." >nul
if not errorlevel 1 goto FromZip
goto Normal

:FromZip
echo Opened from inside the zip Temp folder.
echo Copying this pack into Documents, writing the desktop shortcut, then starting.
set "PY=%~dp0runtime\python.exe"
if not exist "%PY%" set "PY=python"
"%PY%" "%~dp0launch.py" rescue > "%TEMP%\paper-trader-rescue.txt" 2>&1
set "ERR=!errorlevel!"
type "%TEMP%\paper-trader-rescue.txt"
if not "!ERR!"=="0" (
  echo Rescue failed. Right-click Paper-Trader.zip - Extract All - then open Start-Paper-Trader.bat there.
  pause
  exit /b 1
)
set "RUN="
for /f "usebackq delims=" %%L in ("%TEMP%\paper-trader-rescue.txt") do (
  if not defined RUN set "RUN=%%L"
)
if not defined RUN goto RescueBad
if not exist "!RUN!\Start-Paper-Trader.bat" goto RescueBad
echo Desk folder: !RUN!
echo Desktop shortcut: Paper Trader.bat
start "Paper Trader" /D "!RUN!" cmd /c "Start-Paper-Trader.bat"
exit /b 0

:RescueBad
echo Could not find the rescued Start-Paper-Trader.bat.
pause
exit /b 1

:Normal
rem ONE CLICK: open THIS folder's desk in Edge. Older installs stay.
set "SEAL="
if exist "%~dp0VERSION" set /p SEAL=<"%~dp0VERSION"
echo Starting Paper Trader !SEAL! ...
set "PY=%~dp0runtime\python.exe"
if not exist "%PY%" set "PY=python"
"%PY%" "%~dp0launch.py" desktop-link > "%TEMP%\paper-trader-desktop.txt" 2>&1
type "%TEMP%\paper-trader-desktop.txt"
call :DeskLink "%~dp0"
call :MakePhone "%~dp0"
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
echo Python was not found in this folder. Extract All Paper-Trader.zip, then open Start-Paper-Trader.bat.
:done
pause
exit /b 0

:DeskLink
if not exist "%USERPROFILE%\Desktop" mkdir "%USERPROFILE%\Desktop" 2>nul
call :WriteLink "%USERPROFILE%\Desktop" "%~1"
call :WriteLink "%USERPROFILE%\OneDrive\Desktop" "%~1"
if defined OneDrive call :WriteLink "%OneDrive%\Desktop" "%~1"
if exist "%USERPROFILE%\Desktop\Paper Trader.bat" (
  echo Desktop shortcut ready: %USERPROFILE%\Desktop\Paper Trader.bat
) else (
  echo Desktop shortcut write tried. If you use OneDrive Desktop, look there for Paper Trader.bat
)
exit /b 0

:WriteLink
if not exist "%~1" mkdir "%~1" 2>nul
if not exist "%~1" exit /b 0
> "%~1\Paper Trader.bat" echo @echo off
>> "%~1\Paper Trader.bat" echo cd /d "%~2"
>> "%~1\Paper Trader.bat" echo title Paper Trader
>> "%~1\Paper Trader.bat" echo call "Start-Paper-Trader.bat"
exit /b 0

:MakePhone
rem Quiet copy only - no folder window.
set "PHONE=%USERPROFILE%\Documents\iPhone"
if not exist "%USERPROFILE%\Documents" mkdir "%USERPROFILE%\Documents"
if not exist "%PHONE%" mkdir "%PHONE%"
set "SRC=%~1"
if "!SRC:~-1!"=="\" set "SRC=!SRC:~0,-1!"
if exist "%SRC%\iphone\PaperTrader.html" copy /y "%SRC%\iphone\PaperTrader.html" "%PHONE%\PaperTrader.html" >nul
if exist "%SRC%\iphone\README.txt" copy /y "%SRC%\iphone\README.txt" "%PHONE%\README.txt" >nul
exit /b 0
