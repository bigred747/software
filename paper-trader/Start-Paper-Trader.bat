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
for %%R in ("%USERPROFILE%\Downloads" "%USERPROFILE%\Desktop" "%USERPROFILE%\Documents" "%USERPROFILE%\OneDrive\Downloads" "%USERPROFILE%\OneDrive\Desktop" "%USERPROFILE%\OneDrive\Documents") do if exist "%%~R" for /f "delims=" %%F in ('dir /b /a-d /o-d "%%~R\Paper-Trader*.zip" 2^>nul') do if not defined ZIP set "ZIP=%%~R\%%F"
if not defined ZIP if defined OneDrive for %%R in ("%OneDrive%\Downloads" "%OneDrive%\Desktop" "%OneDrive%\Documents") do if exist "%%~R" for /f "delims=" %%F in ('dir /b /a-d /o-d "%%~R\Paper-Trader*.zip" 2^>nul') do if not defined ZIP set "ZIP=%%~R\%%F"
if not defined ZIP goto NoZip
if not exist "%USERPROFILE%\Documents" mkdir "%USERPROFILE%\Documents"
set "STAGE=%USERPROFILE%\Documents\PaperTrader-setup"
if exist "%STAGE%" rmdir /s /q "%STAGE%"
if exist "%STAGE%" set "STAGE=%USERPROFILE%\Documents\PaperTrader-setup-%RANDOM%"
mkdir "%STAGE%"
mkdir "%STAGE%\paper-trader" 2>nul
mkdir "%STAGE%\paper-trader\paper_trader" 2>nul
mkdir "%STAGE%\paper-trader\web" 2>nul
mkdir "%STAGE%\paper-trader\tools" 2>nul
mkdir "%STAGE%\paper-trader\iphone" 2>nul
mkdir "%STAGE%\paper-trader\runtime" 2>nul
tar -xf "!ZIP!" -C "%STAGE%"
if errorlevel 1 goto BadZip
if not exist "%STAGE%\paper-trader\launch.py" goto BadZip
set "RUN=%STAGE%\paper-trader"
set "PY=%RUN%\runtime\python.exe"
if not exist "%PY%" set "PY=python"
"%PY%" "%RUN%\launch.py" install > "%USERPROFILE%\Documents\paper-trader-install.txt" 2>&1
if not errorlevel 1 if exist "%USERPROFILE%\Documents\Paper Trader\runtime\python.exe" set "RUN=%USERPROFILE%\Documents\Paper Trader"
if errorlevel 1 (
  echo The older folder stayed in place. Opening the new copy.
  type "%USERPROFILE%\Documents\paper-trader-install.txt"
)
rem Keep Documents\Paper Trader only — drop launcher staging folders (and old setup-* leftovers).
if /I "%RUN%"=="%USERPROFILE%\Documents\Paper Trader" for /d %%D in ("%USERPROFILE%\Documents\PaperTrader-setup*") do rmdir /s /q "%%D" 2>nul
echo Setup is done. Leave the window named Paper Trader open.
echo Microsoft Edge opens the computer desk at http://127.0.0.1:8765/ when the desk answers.
echo Schwab Connect only works on that computer desk — not Documents\iPhone\PaperTrader.html.
echo The score keeps running after that window closes. Starting it at sign-in is optional. See Windows extras in the Safe check.
echo The desk folder is:
echo %RUN%
call :DeskLink "%RUN%"
call :MakePhone "%RUN%"
start "Paper Trader" /D "%RUN%" cmd /c "Start-Paper-Trader.bat"
echo Press any key to close this setup window.
pause >nul
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
echo Paper Trader stays on this computer. It loads market quotes and does not place broker orders.
echo Microsoft Edge opens the computer desk at http://127.0.0.1:8765/
echo Schwab Connect only works on that computer desk — not the mobile PaperTrader.html copy.
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
echo Python was not found. Extract Paper-Trader.zip again, then run 1 Allow Paper Trader.bat.
:done
pause
exit /b 0

:DeskLink
call :WriteLink "%USERPROFILE%\Desktop" "%~1"
call :WriteLink "%USERPROFILE%\OneDrive\Desktop" "%~1"
if defined OneDrive call :WriteLink "%OneDrive%\Desktop" "%~1"
echo Desktop shortcut: Paper Trader.bat — double-click it to open http://127.0.0.1:8765/
exit /b 0

:WriteLink
if not exist "%~1" exit /b 0
> "%~1\Paper Trader.bat" echo @echo off
>> "%~1\Paper Trader.bat" echo cd /d "%~2"
>> "%~1\Paper Trader.bat" echo title Paper Trader
>> "%~1\Paper Trader.bat" echo rem Opens the computer desk on 127.0.0.1 — not the mobile HTML copy.
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
if exist "%PHONE%\PaperTrader.html" (
  echo Phone copy updated quietly in Documents\iPhone — not opened.
  echo Use Desktop "Paper Trader.bat" for Schwab Connect on 127.0.0.1.
) else (
  echo The iPhone file was not in this copy.
)
exit /b 0
