@echo off
setlocal EnableExtensions EnableDelayedExpansion
cd /d "%~dp0"
title Paper Trader
echo %~dp0 | findstr /I /C:"\Temp" | findstr /I /C:".zip." >nul
if not errorlevel 1 goto FromZip
goto Normal

:FromZip
echo Opened from inside the zip Temp folder.
echo Windows often only drops the .bat there - launching a full install from your Downloads zip.
set "PY="
if exist "%~dp0runtime\python.exe" set "PY=%~dp0runtime\python.exe"
if not defined PY if exist "%USERPROFILE%\Documents\Paper Trader\runtime\python.exe" set "PY=%USERPROFILE%\Documents\Paper Trader\runtime\python.exe"
if not defined PY (
  where python >nul 2>&1
  if not errorlevel 1 set "PY=python"
)
if not defined PY (
  where py >nul 2>&1
  if not errorlevel 1 set "PY=py -3"
)
if not defined PY (
  echo Python was not found. Right-click Paper-Trader.zip - Extract All -
  echo then open Start-Paper-Trader.bat in the extracted paper-trader folder
  echo ^(that folder includes runtime\python.exe^).
  pause
  exit /b 1
)

rem If this Temp fold already has a full pack, rescue it.
if exist "%~dp0launch.py" if exist "%~dp0paper_trader\__init__.py" (
  "%PY%" "%~dp0launch.py" rescue > "%TEMP%\paper-trader-rescue.txt" 2>&1
  set "ERR=!errorlevel!"
  type "%TEMP%\paper-trader-rescue.txt"
  if "!ERR!"=="0" goto AfterRescue
)

rem Temp fold is incomplete. Find Paper-Trader*.zip and unpack a FULL copy.
set "ZIP="
if exist "%~dp0tools\pick_upgrade_zip.py" (
  for /f "delims=" %%Z in ('"%PY%" "%~dp0tools\pick_upgrade_zip.py" 2^>nul') do set "ZIP=%%Z"
)
if not defined ZIP if exist "%USERPROFILE%\Downloads\Paper-Trader.zip" set "ZIP=%USERPROFILE%\Downloads\Paper-Trader.zip"
if not defined ZIP if exist "%USERPROFILE%\Downloads\Paper-Trader (1).zip" set "ZIP=%USERPROFILE%\Downloads\Paper-Trader (1).zip"
if not defined ZIP if exist "%USERPROFILE%\Downloads\Paper-Trader (2).zip" set "ZIP=%USERPROFILE%\Downloads\Paper-Trader (2).zip"
if not defined ZIP if exist "%USERPROFILE%\Downloads\Paper-Trader (3).zip" set "ZIP=%USERPROFILE%\Downloads\Paper-Trader (3).zip"
if not defined ZIP (
  for %%R in ("%USERPROFILE%\Downloads" "%USERPROFILE%\Desktop" "%USERPROFILE%\Documents") do if exist "%%~R" for /f "delims=" %%F in ('dir /b /a-d /o-d "%%~R\Paper-Trader*.zip" 2^>nul') do if not defined ZIP set "ZIP=%%~R\%%F"
)
if not defined ZIP goto NoZip
if /I "!ZIP!"=="NONE" goto NoZip

echo Using zip:
echo   !ZIP!
if not exist "%USERPROFILE%\Documents" mkdir "%USERPROFILE%\Documents"
set "STAGE=%USERPROFILE%\Documents\PaperTrader-setup-%RANDOM%%RANDOM%"
mkdir "%STAGE%" 2>nul
if not exist "%STAGE%\." goto BadZip

echo Unpacking full pack into Documents...
"%PY%" -c "import zipfile,sys; z=sys.argv[1]; d=sys.argv[2]; zipfile.ZipFile(z).extractall(d); print(d)" "!ZIP!" "!STAGE!" > "%TEMP%\paper-trader-extract.txt" 2>&1
set "ERR=!errorlevel!"
type "%TEMP%\paper-trader-extract.txt"
if not "!ERR!"=="0" goto BadZip
if not exist "!STAGE!\paper-trader\Start-Paper-Trader.bat" goto BadZip
if not exist "!STAGE!\paper-trader\launch.py" goto BadZip

set "RUN=!STAGE!\paper-trader"
set "PY2=!RUN!\runtime\python.exe"
if not exist "!PY2!" set "PY2=!PY!"
"!PY2!" "!RUN!\launch.py" desktop-link > "%TEMP%\paper-trader-desktop.txt" 2>&1
type "%TEMP%\paper-trader-desktop.txt"
echo Desk folder: !RUN!
echo Starting Paper Trader from the full extract...
start "Paper Trader" /D "!RUN!" cmd /c "Start-Paper-Trader.bat"
exit /b 0

:AfterRescue
set "RUN="
for /f "usebackq delims=" %%L in ("%TEMP%\paper-trader-rescue.txt") do (
  if not defined RUN set "RUN=%%L"
)
if not defined RUN goto BadZip
if not exist "!RUN!\Start-Paper-Trader.bat" goto BadZip
echo Desk folder: !RUN!
start "Paper Trader" /D "!RUN!" cmd /c "Start-Paper-Trader.bat"
exit /b 0

:NoZip
echo Could not find Paper-Trader*.zip in Downloads / Desktop / Documents.
echo 1. Save the zip in Downloads
echo 2. Right-click it - Extract All
echo 3. Open the extracted paper-trader folder
echo 4. Double-click Start-Paper-Trader.bat
pause
exit /b 1

:BadZip
echo Could not unpack a full Paper Trader from the zip.
echo Right-click Paper-Trader.zip - Extract All - then open Start-Paper-Trader.bat in paper-trader.
pause
exit /b 1

:Normal
rem ONE CLICK: open THIS folder's desk in Edge. Older installs stay.
set "SEAL="
if exist "%~dp0VERSION" set /p SEAL=<"%~dp0VERSION"
echo Starting Paper Trader !SEAL! ...
set "PY=%~dp0runtime\python.exe"
if not exist "%PY%" set "PY=python"
if exist "%~dp0launch.py" (
  "%PY%" "%~dp0launch.py" desktop-link > "%TEMP%\paper-trader-desktop.txt" 2>&1
  type "%TEMP%\paper-trader-desktop.txt"
)
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
  echo Desktop shortcut write tried. Check OneDrive Desktop for Paper Trader.bat
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
set "PHONE=%USERPROFILE%\Documents\iPhone"
if not exist "%USERPROFILE%\Documents" mkdir "%USERPROFILE%\Documents"
if not exist "%PHONE%" mkdir "%PHONE%"
set "SRC=%~1"
if "!SRC:~-1!"=="\" set "SRC=!SRC:~0,-1!"
if exist "%SRC%\iphone\PaperTrader.html" copy /y "%SRC%\iphone\PaperTrader.html" "%PHONE%\PaperTrader.html" >nul
if exist "%SRC%\iphone\README.txt" copy /y "%SRC%\iphone\README.txt" "%PHONE%\README.txt" >nul
exit /b 0
