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
echo Close every Paper Trader window and black cmd desk window first.
if not exist "%USERPROFILE%\Documents" mkdir "%USERPROFILE%\Documents"
rem Never reuse Documents\PaperTrader-setup — a running desk locks learn.lock there.
set "STAGE=%USERPROFILE%\Documents\PaperTrader-setup-%RANDOM%%RANDOM%"
mkdir "%STAGE%" 2>nul
if not exist "%STAGE%\." set "STAGE=%USERPROFILE%\Documents\PaperTrader-setup-%RANDOM%%RANDOM%"
mkdir "%STAGE%" 2>nul
if not exist "%STAGE%\." goto BadZip
echo Extracting into: !STAGE!
tar -xf "!ZIP!" -C "!STAGE!"
if errorlevel 1 goto BadZip
if not exist "!STAGE!\paper-trader\launch.py" goto BadZip
set "RUN=!STAGE!\paper-trader"
set "PY=!RUN!\runtime\python.exe"
if not exist "!PY!" set "PY=python"
"!PY!" "!RUN!\launch.py" install > "%USERPROFILE%\Documents\paper-trader-install.txt" 2>&1
if exist "%USERPROFILE%\Documents\Paper Trader\launch.py" set "RUN=%USERPROFILE%\Documents\Paper Trader"
if errorlevel 1 (
  echo Install note:
  type "%USERPROFILE%\Documents\paper-trader-install.txt"
)
if exist "%USERPROFILE%\Documents\Paper Trader\launch.py" set "RUN=%USERPROFILE%\Documents\Paper Trader"
"!PY!" "!RUN!\launch.py" erase-others > "%USERPROFILE%\Documents\paper-trader-erase.txt" 2>&1
echo Setup is done. Leave the window named Paper Trader open.
echo Microsoft Edge opens when the desk answers.
echo The desk folder is:
echo !RUN!
call :DeskLink "!RUN!"
start "Paper Trader" /D "!RUN!" cmd /c "2 Open Paper Trader.bat"
echo Press any key to close this setup window.
pause >nul
exit /b 0

:NoZip
echo Could not find Paper-Trader.zip in Downloads.
echo Save the FULL Paper-Trader.zip in Downloads, then try again.
pause
exit /b 1

:BadZip
echo Could not extract. Close EVERY Paper Trader window, then try again.
echo Or right-click the zip - Extract All - then open FORCE-UPGRADE.bat.
pause
exit /b 1

:Normal
echo Unblock
echo If Windows shows an Unblock box, check Unblock and open this file.
set "SEAL="
if exist "%~dp0VERSION" set /p SEAL=<"%~dp0VERSION"
echo This folder seal: !SEAL!
if exist "%~dp0SEAL.txt" type "%~dp0SEAL.txt"

rem Complete desk in THIS folder (Documents or Extract All with runtime) — open like 1.10.40.
rem Do NOT compare path strings to Documents (OneDrive breaks that and blocks open).
if exist "%~dp0launch.py" if exist "%~dp0VERSION" if exist "%~dp0web\app.js" goto ServeHere

rem Incomplete extract: install into Documents, then open Documents.
set "DEST=%USERPROFILE%\Documents\Paper Trader"
echo Installing into Documents\Paper Trader...
set "PY=%~dp0runtime\python.exe"
if not exist "!PY!" set "PY=python"
"!PY!" "%~dp0launch.py" install > "%USERPROFILE%\Documents\paper-trader-install.txt" 2>&1
set "ERR=!errorlevel!"
type "%USERPROFILE%\Documents\paper-trader-install.txt"
if not "!ERR!"=="0" (
  echo Install failed. Close every desk window and run FORCE-UPGRADE.bat from a FULL zip Extract All.
  pause
  exit /b 1
)
if not exist "!DEST!\launch.py" (
  echo Documents\Paper Trader missing. Run FORCE-UPGRADE.bat from the FULL zip extract.
  pause
  exit /b 1
)
start "Paper Trader" /D "!DEST!" cmd /c "2 Open Paper Trader.bat"
exit /b 0

:ServeHere
echo Paper Trader stays on this computer. Paper orders only. Live locked.
echo Microsoft Edge opens the desk. Leave this black window open.
call :DeskLink "%~dp0"
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
echo.
echo Python was not found — this copy has no runtime\python.exe.
echo Fix: download the FULL zip, Extract All, run FORCE-UPGRADE.bat once.
echo FULL zip: https://github.com/bigred747/software/raw/main/Paper-Trader.zip
echo Then Connect: double-click Connect-Schwab.bat in Documents\Paper Trader
:done
pause
exit /b 0

:DeskLink
call :WriteLink "%USERPROFILE%\Desktop" "%~1"
call :WriteLink "%USERPROFILE%\OneDrive\Desktop" "%~1"
if defined OneDrive call :WriteLink "%OneDrive%\Desktop" "%~1"
echo Desktop Paper Trader.bat rewritten to open this desk.
exit /b 0

:WriteLink
if not exist "%~1" exit /b 0
> "%~1\Paper Trader.bat" echo @echo off
>> "%~1\Paper Trader.bat" echo cd /d "%~2"
>> "%~1\Paper Trader.bat" echo title Paper Trader
>> "%~1\Paper Trader.bat" echo call "2 Open Paper Trader.bat"
exit /b 0
