@echo off
setlocal EnableExtensions EnableDelayedExpansion
cd /d "%~dp0"
title Paper Trader
echo %~dp0 | findstr /I /C:"\Temp" | findstr /I /C:".zip." >nul
if not errorlevel 1 goto FromZip
goto Normal

:FromZip
echo Unblock
echo If Windows shows an Unblock box, check Unblock and open this file.
echo That box lets this copy open. It stays on this computer.
echo This file was opened inside the zip. Extracting the whole Paper Trader now.
set "ZIP="
set "PYTRY=python"
if exist "%~dp0runtime\python.exe" set "PYTRY=%~dp0runtime\python.exe"
for /f "delims=" %%Z in ('"%PYTRY%" "%~dp0tools\pick_upgrade_zip.py" 2^>nul') do set "ZIP=%%Z"
if not defined ZIP if exist "%USERPROFILE%\Downloads\Paper-Trader.zip" set "ZIP=%USERPROFILE%\Downloads\Paper-Trader.zip"
if not defined ZIP goto NoZip
if /I "!ZIP!"=="NONE" goto NoZip
echo Using zip: !ZIP!
if not exist "%USERPROFILE%\Documents" mkdir "%USERPROFILE%\Documents"
set "STAGE=%USERPROFILE%\Documents\PaperTrader-setup"
if exist "%STAGE%" rmdir /s /q "%STAGE%"
if exist "%STAGE%" set "STAGE=%USERPROFILE%\Documents\PaperTrader-setup-%RANDOM%"
mkdir "%STAGE%"
tar -xf "!ZIP!" -C "%STAGE%"
if errorlevel 1 goto BadZip
if not exist "%STAGE%\paper-trader\FORCE-UPGRADE.bat" goto BadZip
start "" "%STAGE%\paper-trader\FORCE-UPGRADE.bat"
echo Extracted. FORCE-UPGRADE will replace Documents\Paper Trader.
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
set "SEAL="
if exist "%~dp0VERSION" set /p SEAL=<"%~dp0VERSION"
echo This folder seal: !SEAL!
if exist "%~dp0SEAL.txt" type "%~dp0SEAL.txt"

set "DEST=%USERPROFILE%\Documents\Paper Trader"
set "HERE=%~dp0"
if "!HERE:~-1!"=="\" set "HERE=!HERE:~0,-1!"
set "DESTN=!DEST!"

rem If this is not Documents\Paper Trader, force-upgrade Documents first, then open that copy.
if /I not "!HERE!"=="!DESTN!" (
  echo.
  echo This extract is not Documents\Paper Trader.
  echo Upgrading Documents\Paper Trader to seal !SEAL! now...
  echo Close every desk window / Edge 127.0.0.1 tab if prompted.
  echo.
  set "PY=%~dp0runtime\python.exe"
  if not exist "!PY!" set "PY=python"
  "!PY!" "%~dp0launch.py" force-upgrade > "%USERPROFILE%\Documents\paper-trader-force-upgrade.txt" 2>&1
  set "ERR=!errorlevel!"
  type "%USERPROFILE%\Documents\paper-trader-force-upgrade.txt"
  if not "!ERR!"=="0" (
    echo FORCE UPGRADE FAILED — Documents stayed on the old seal.
    echo Close EVERY Paper Trader window and run FORCE-UPGRADE.bat again.
    pause
    exit /b 1
  )
  set "CHECK="
  if exist "!DEST!\VERSION" set /p CHECK=<"!DEST!\VERSION"
  if /I not "!CHECK!"=="!SEAL!" (
    echo Documents\Paper Trader\VERSION is !CHECK! — wanted !SEAL!
    echo Close the running desk and run FORCE-UPGRADE.bat from this extract.
    pause
    exit /b 1
  )
  echo Documents\Paper Trader is now !CHECK!
  start "" "!DEST!\Start-Paper-Trader.bat"
  exit /b 0
)

echo Paper Trader stays on this computer. It loads market quotes and does not place broker orders.
echo Microsoft Edge opens the desk.
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
echo Python was not found. Extract Paper-Trader.zip again, then run FORCE-UPGRADE.bat.
:done
pause
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

:MakePhone
set "PHONE=%USERPROFILE%\Documents\iPhone"
if not exist "%USERPROFILE%\Documents" mkdir "%USERPROFILE%\Documents"
if not exist "%PHONE%" mkdir "%PHONE%"
set "SRC=%~1"
if "!SRC:~-1!"=="\" set "SRC=!SRC:~0,-1!"
if exist "%SRC%\iphone\PaperTrader.html" copy /y "%SRC%\iphone\PaperTrader.html" "%PHONE%\PaperTrader.html" >nul
if exist "%SRC%\iphone\README.txt" copy /y "%SRC%\iphone\README.txt" "%PHONE%\README.txt" >nul
if exist "%PHONE%\PaperTrader.html" (
  echo The iPhone folder is open.
  echo %PHONE%
  start "" explorer "%PHONE%"
) else (
  echo The iPhone file was not in this copy.
)
exit /b 0
