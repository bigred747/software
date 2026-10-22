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
if exist "%RUN%\runtime\python.exe" (
  start "Paper Trader" "%RUN%\runtime\python.exe" "%RUN%\launch.py" serve
) else (
  start "Paper Trader" python "%RUN%\launch.py" serve
)
echo Paper Trader is opening. Leave the Paper Trader window open.
echo Microsoft Edge opens the desk. The header says Desk is working.
pause
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
