@echo off
setlocal EnableExtensions EnableDelayedExpansion
cd /d "%~dp0"
title Paper Trader
echo %~dp0 | findstr /I /C:"\Temp" | findstr /I /C:".zip." >nul
if not errorlevel 1 goto FromZip
goto Normal

:FromZip
rem Hand off to Start so rescue + desktop shortcut + open happen once.
call "%~dp0Start-Paper-Trader.bat"
exit /b %errorlevel%

:Normal
call "%~dp0Start-Paper-Trader.bat"
