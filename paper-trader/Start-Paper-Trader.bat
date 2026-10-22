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
if exist "%STAGE%\paper-trader\runtime\python.exe" (
  "%STAGE%\paper-trader\runtime\python.exe" "%STAGE%\paper-trader\launch.py" install
) else (
  python "%STAGE%\paper-trader\launch.py" install
)
if errorlevel 1 goto BadInstall
set "VBS=%TEMP%\paper-trader-shortcut.vbs"
> "%VBS%" echo Set shell = CreateObject("WScript.Shell"^)
>> "%VBS%" echo desk = shell.SpecialFolders("Desktop"^)
>> "%VBS%" echo Set link = shell.CreateShortcut(desk ^& "\Paper Trader.lnk"^)
>> "%VBS%" echo link.TargetPath = "%USERPROFILE%\Documents\Paper Trader\2 Open Paper Trader.bat"
>> "%VBS%" echo link.WorkingDirectory = "%USERPROFILE%\Documents\Paper Trader"
>> "%VBS%" echo link.Save
cscript //nologo "%VBS%"
if exist "%USERPROFILE%\Documents\Paper Trader\runtime\python.exe" (
  start "Paper Trader" "%USERPROFILE%\Documents\Paper Trader\runtime\python.exe" "%USERPROFILE%\Documents\Paper Trader\launch.py" serve
) else (
  start "Paper Trader" python "%USERPROFILE%\Documents\Paper Trader\launch.py" serve
)
echo Paper Trader is opening from Documents. You can close this window.
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

:BadInstall
echo Could not replace the older copy.
echo Close any open Paper Trader window, then double-click this file again.
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
