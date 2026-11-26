@echo off
setlocal EnableExtensions EnableDelayedExpansion
cd /d "%~dp0"
title Paper Trader
echo %~dp0 | findstr /I /C:"\Temp" | findstr /I /C:".zip." >nul
if not errorlevel 1 goto FromZip
goto Normal

:FromZip
echo Opened from inside the zip — unpacking once, then starting the desk.
set "ZIP="
set "PY="
if exist "%~dp0runtime\python.exe" set "PY=%~dp0runtime\python.exe"
if not defined PY if exist "%USERPROFILE%\Documents\Paper Trader\runtime\python.exe" set "PY=%USERPROFILE%\Documents\Paper Trader\runtime\python.exe"
if not defined PY set "PY=python"
if exist "%~dp0tools\pick_upgrade_zip.py" for /f "delims=" %%Z in ('"%PY%" "%~dp0tools\pick_upgrade_zip.py" 2^>nul') do set "ZIP=%%Z"
if not defined ZIP if exist "%USERPROFILE%\Downloads\Paper-Trader.zip" set "ZIP=%USERPROFILE%\Downloads\Paper-Trader.zip"
if not defined ZIP goto NoZip
if /I "!ZIP!"=="NONE" goto NoZip
if not exist "%USERPROFILE%\Documents" mkdir "%USERPROFILE%\Documents"
set "STAGE=%USERPROFILE%\Documents\PaperTrader-setup"
if exist "%STAGE%" set "STAGE=%USERPROFILE%\Documents\PaperTrader-setup-%RANDOM%%RANDOM%"
mkdir "%STAGE%" 2>nul
if not exist "%STAGE%\." goto BadZip
if exist "%~dp0tools\extract_zip.py" (
  "%PY%" "%~dp0tools\extract_zip.py" "!ZIP!" "!STAGE!" > "%TEMP%\paper-trader-extract.txt" 2>&1
) else (
  "%PY%" -c "import zipfile,sys; zipfile.ZipFile(sys.argv[1]).extractall(sys.argv[2])" "!ZIP!" "!STAGE!" > "%TEMP%\paper-trader-extract.txt" 2>&1
)
if errorlevel 1 goto BadZip
if not exist "!STAGE!\paper-trader\launch.py" goto BadZip
set "RUN=!STAGE!\paper-trader"
set "PY=!RUN!\runtime\python.exe"
if not exist "!PY!" set "PY=python"
"!PY!" "!RUN!\launch.py" install > "%USERPROFILE%\Documents\paper-trader-install.txt" 2>&1
if exist "%USERPROFILE%\Documents\Paper Trader\launch.py" set "RUN=%USERPROFILE%\Documents\Paper Trader"
call :DeskLink "!RUN!"
call :MakePhone "!RUN!"
rem One click: open the desk window and close this setup window.
start "Paper Trader" /D "!RUN!" cmd /c "Start-Paper-Trader.bat"
exit /b 0

:NoZip
echo Could not find Paper-Trader.zip. Save it in Downloads, Extract All, then double-click Start-Paper-Trader.bat.
pause
exit /b 1

:BadZip
echo Could not unpack Paper-Trader.zip. Extract All, then double-click Start-Paper-Trader.bat in paper-trader.
pause
exit /b 1

:Normal
rem ONE CLICK — open THIS folder's desk in Edge. Older installs stay.
set "SEAL="
if exist "%~dp0VERSION" set /p SEAL=<"%~dp0VERSION"
echo Starting Paper Trader !SEAL! ...
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
echo Python was not found in this folder. Extract a fresh Paper-Trader.zip and open Start-Paper-Trader.bat again.
:done
pause
exit /b 0

:DeskLink
call :WriteLink "%USERPROFILE%\Desktop" "%~1"
call :WriteLink "%USERPROFILE%\OneDrive\Desktop" "%~1"
if defined OneDrive call :WriteLink "%OneDrive%\Desktop" "%~1"
exit /b 0

:WriteLink
if not exist "%~1" exit /b 0
> "%~1\Paper Trader.bat" echo @echo off
>> "%~1\Paper Trader.bat" echo cd /d "%~2"
>> "%~1\Paper Trader.bat" echo title Paper Trader
>> "%~1\Paper Trader.bat" echo call "Start-Paper-Trader.bat"
exit /b 0

:MakePhone
rem Quiet copy only — no folder window (keeps Start one-click).
set "PHONE=%USERPROFILE%\Documents\iPhone"
if not exist "%USERPROFILE%\Documents" mkdir "%USERPROFILE%\Documents"
if not exist "%PHONE%" mkdir "%PHONE%"
set "SRC=%~1"
if "!SRC:~-1!"=="\" set "SRC=!SRC:~0,-1!"
if exist "%SRC%\iphone\PaperTrader.html" copy /y "%SRC%\iphone\PaperTrader.html" "%PHONE%\PaperTrader.html" >nul
if exist "%SRC%\iphone\README.txt" copy /y "%SRC%\iphone\README.txt" "%PHONE%\README.txt" >nul
exit /b 0
