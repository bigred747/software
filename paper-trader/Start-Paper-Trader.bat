@echo off
setlocal EnableExtensions EnableDelayedExpansion
cd /d "%~dp0"
echo %~dp0 | findstr /I /C:"\Temp" | findstr /I /C:".zip." >nul
if not errorlevel 1 goto FromZip
goto Normal

:FromZip
echo This file was opened inside the zip. Extracting the whole Paper Trader now.
set "ZIP="
for %%R in ("%USERPROFILE%\Downloads" "%USERPROFILE%\Desktop" "%USERPROFILE%\Documents") do for /f "delims=" %%F in ('dir /b /a-d /o-d "%%~R\Paper-Trader*.zip" 2^>nul') do if not defined ZIP set "ZIP=%%~R\%%F"
if not defined ZIP goto NoZip
if not exist "%USERPROFILE%\Documents" mkdir "%USERPROFILE%\Documents"
taskkill /F /FI "WINDOWTITLE eq Paper Trader" >nul 2>&1
set "STAGE=%USERPROFILE%\Documents\PaperTrader-setup"
if exist "%STAGE%" rmdir /s /q "%STAGE%"
if exist "%STAGE%" set "STAGE=%USERPROFILE%\Documents\PaperTrader-setup-%RANDOM%"
mkdir "%STAGE%"
tar -xf "!ZIP!" -C "%STAGE%"
if errorlevel 1 goto BadZip
if not exist "%STAGE%\paper-trader\launch.py" goto BadZip
set "RUN=%STAGE%\paper-trader"
set "PY=%RUN%\runtime\python.exe"
if not exist "%PY%" set "PY=python"
"%PY%" "%RUN%\launch.py" install > "%TEMP%\paper-trader-install.txt" 2>&1
if not errorlevel 1 if exist "%USERPROFILE%\Documents\Paper Trader\runtime\python.exe" set "RUN=%USERPROFILE%\Documents\Paper Trader"
if errorlevel 1 (
  echo The older folder stayed in place. Opening the new copy.
  type "%TEMP%\paper-trader-install.txt"
)
set "VBS=%TEMP%\paper-trader-shortcut.vbs"
> "%VBS%" echo Set shell = CreateObject("WScript.Shell"^)
>> "%VBS%" echo desk = shell.SpecialFolders("Desktop"^)
>> "%VBS%" echo Set link = shell.CreateShortcut(desk ^& "\Paper Trader.lnk"^)
>> "%VBS%" echo link.TargetPath = "%RUN%\2 Open Paper Trader.bat"
>> "%VBS%" echo link.WorkingDirectory = "%RUN%"
>> "%VBS%" echo link.Save
cscript //nologo "%VBS%"
set "CMD=%TEMP%\paper-trader-run.bat"
> "%CMD%" echo @echo off
>> "%CMD%" echo title Paper Trader
>> "%CMD%" echo cd /d "%RUN%"
>> "%CMD%" echo echo Leave this window open. Microsoft Edge opens the desk.
>> "%CMD%" echo echo The score keeps running after this window closes. It starts again when you sign in to Windows.
>> "%CMD%" echo if not exist "%RUN%\runtime\python.exe" goto UsePy
>> "%CMD%" echo "%RUN%\runtime\python.exe" "%RUN%\launch.py" serve
>> "%CMD%" echo goto After
>> "%CMD%" echo :UsePy
>> "%CMD%" echo python "%RUN%\launch.py" serve
>> "%CMD%" echo :After
>> "%CMD%" echo echo.
>> "%CMD%" echo echo Paper Trader stopped. Read the message above.
>> "%CMD%" echo pause
start "Paper Trader" cmd /c "%CMD%"
echo Setup is done. Leave the window named Paper Trader open.
echo Microsoft Edge opens when the desk answers. The header says Desk is working.
echo The score keeps running after that window closes. It starts again when you sign in to Windows.
echo Press any key to close this setup window.
pause >nul
exit /b 0

:NoZip
echo Could not find Paper-Trader.zip in Downloads, Desktop, or Documents.
echo Save the zip in Downloads, then double-click this file again.
pause
exit /b 1

:BadZip
echo Could not extract Paper-Trader.zip.
pause
exit /b 1

:Normal
echo Paper Trader stays on this computer. It loads market quotes and does not place broker orders.
echo Microsoft Edge opens the desk.
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
