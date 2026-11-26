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
set "PY="
if exist "%~dp0runtime\python.exe" set "PY=%~dp0runtime\python.exe"
if not defined PY if exist "%USERPROFILE%\Documents\Paper Trader\runtime\python.exe" set "PY=%USERPROFILE%\Documents\Paper Trader\runtime\python.exe"
if not defined PY set "PY=python"
if exist "%~dp0tools\pick_upgrade_zip.py" for /f "delims=" %%Z in ('"%PY%" "%~dp0tools\pick_upgrade_zip.py" 2^>nul') do set "ZIP=%%Z"
if not defined ZIP if exist "%USERPROFILE%\Downloads\Paper-Trader.zip" set "ZIP=%USERPROFILE%\Downloads\Paper-Trader.zip"
if not defined ZIP goto NoZip
if /I "!ZIP!"=="NONE" goto NoZip
echo Using zip: !ZIP!
if not exist "%USERPROFILE%\Documents" mkdir "%USERPROFILE%\Documents"
set "STAGE=%USERPROFILE%\Documents\PaperTrader-setup"
if exist "%STAGE%" set "STAGE=%USERPROFILE%\Documents\PaperTrader-setup-%RANDOM%%RANDOM%"
mkdir "%STAGE%" 2>nul
if not exist "%STAGE%\." goto BadZip
echo Extracting into: !STAGE!
if exist "%~dp0tools\extract_zip.py" (
  "%PY%" "%~dp0tools\extract_zip.py" "!ZIP!" "!STAGE!" > "%TEMP%\paper-trader-extract.txt" 2>&1
) else (
  "%PY%" -c "import zipfile,sys; zipfile.ZipFile(sys.argv[1]).extractall(sys.argv[2])" "!ZIP!" "!STAGE!" > "%TEMP%\paper-trader-extract.txt" 2>&1
)
set "ERR=!errorlevel!"
type "%TEMP%\paper-trader-extract.txt"
if not "!ERR!"=="0" goto BadZip
if not exist "!STAGE!\paper-trader\launch.py" goto BadZip
set "RUN=!STAGE!\paper-trader"
set "PY=!RUN!\runtime\python.exe"
if not exist "!PY!" set "PY=python"
"!PY!" "!RUN!\launch.py" install > "%USERPROFILE%\Documents\paper-trader-install.txt" 2>&1
if exist "%USERPROFILE%\Documents\Paper Trader\launch.py" set "RUN=%USERPROFILE%\Documents\Paper Trader"
if errorlevel 1 (
  echo The older folder stayed in place. Opening this extracted copy.
  type "%USERPROFILE%\Documents\paper-trader-install.txt"
)
if exist "%USERPROFILE%\Documents\Paper Trader\launch.py" set "RUN=%USERPROFILE%\Documents\Paper Trader"
echo Setup is done. Leave the window named Paper Trader open.
echo Microsoft Edge opens when the desk answers. The header says Desk is working.
echo Older Paper Trader folders are left alone. Use Erase-Other-Paper-Traders.bat only if you want that.
echo The desk folder is:
echo !RUN!
call :DeskLink "!RUN!"
call :MakePhone "!RUN!"
start "Paper Trader" /D "!RUN!" cmd /c "2 Open Paper Trader.bat"
echo Opening the new copy.
echo Press any key to close this setup window.
pause >nul
exit /b 0

:NoZip
echo Could not find Paper-Trader.zip in Downloads, Desktop, Documents, or OneDrive.
echo Save the zip in Downloads, then double-click 1 INSTALL Paper Trader.bat.
pause
exit /b 1

:BadZip
echo Could not extract Paper-Trader.zip.
echo Windows Git tar cannot unpack this zip — this installer uses Python instead.
echo Close EVERY Paper Trader window, then run 1 INSTALL Paper Trader.bat.
echo Or right-click the zip - Extract All - then open Start-Paper-Trader.bat.
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
echo Paper Trader stays on this computer. It loads market quotes and does not place broker orders.
echo Microsoft Edge opens the desk.
echo Older installs are not deleted. This window opens THIS folder only.
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
echo Python was not found. Run 1 INSTALL Paper Trader.bat from the extracted folder.
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
