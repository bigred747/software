@echo off
setlocal EnableExtensions EnableDelayedExpansion
cd /d "%~dp0"
title Paper Trader Fibs Lab
echo Starting Paper Trader Fibs Lab (side-by-side)...
echo This does NOT open or change your working Paper Trader desk.
echo Folder: %CD%
echo Port: 8787
echo Desktop shortcut name: Paper Trader Fibs Lab.bat
echo.

if not exist "%~dp0FIBS-LAB.txt" (
  echo FIBS-LAB.txt missing — refusing to run as Fibs Lab.
  pause
  exit /b 1
)

set "PY="
if exist "%~dp0runtime\python.exe" set "PY=%~dp0runtime\python.exe"
if not defined PY (
  where python >nul 2>&1
  if not errorlevel 1 set "PY=python"
)
if not defined PY (
  where py >nul 2>&1
  if not errorlevel 1 set "PY=py -3"
)
if not defined PY (
  echo Python was not found. Extract All this Fibs Lab zip, then open Start-Fibs-Lab.bat.
  pause
  exit /b 1
)

"%PY%" "%~dp0launch.py" desktop-link
call :LabLink "%USERPROFILE%\Desktop"
call :LabLink "%USERPROFILE%\OneDrive\Desktop"
if defined OneDrive call :LabLink "%OneDrive%\Desktop"
if exist "%USERPROFILE%\Desktop\Paper Trader Fibs Lab.bat" (
  echo Desktop shortcut ready: %USERPROFILE%\Desktop\Paper Trader Fibs Lab.bat
)

echo.
echo Working desk shortcut "Paper Trader.bat" is left alone.
echo.
"%PY%" "%~dp0launch.py" serve --port 8787
pause
exit /b 0

:LabLink
if not exist "%~1" mkdir "%~1" 2>nul
if not exist "%~1" exit /b 0
> "%~1\Paper Trader Fibs Lab.bat" echo @echo off
>> "%~1\Paper Trader Fibs Lab.bat" echo cd /d "%CD%"
>> "%~1\Paper Trader Fibs Lab.bat" echo title Paper Trader Fibs Lab
>> "%~1\Paper Trader Fibs Lab.bat" echo call "Start-Fibs-Lab.bat"
exit /b 0
