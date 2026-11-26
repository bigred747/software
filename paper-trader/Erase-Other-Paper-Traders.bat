@echo off
setlocal EnableExtensions
cd /d "%~dp0"
title Paper Trader — optional erase extras
echo.
echo  OPTIONAL: erase other Paper Trader installs
echo  Keeps Documents\Paper Trader (this seal) and can remove extras.
echo  Your working copies are NOT erased when you only open Start / Open.
echo  Run this ONLY if you want the extras gone.
echo.
set /p CONFIRM=Type YES to erase other installs: 
if /I not "%CONFIRM%"=="YES" (
  echo Cancelled. Nothing was erased.
  pause
  exit /b 0
)
if exist "%~dp0runtime\python.exe" (
  "%~dp0runtime\python.exe" "%~dp0launch.py" erase-others
  goto After
)
where python >nul 2>nul
if not errorlevel 1 (
  python "%~dp0launch.py" erase-others
  goto After
)
where py >nul 2>nul
if not errorlevel 1 (
  py -3 "%~dp0launch.py" erase-others
  goto After
)
echo Python was not found. Open this from Documents\Paper Trader after 1 Allow.
pause
exit /b 1

:After
if errorlevel 1 (
  echo Could not erase the other copies.
  pause
  exit /b 1
)
echo.
echo Done. Documents\Paper Trader should remain.
echo Open Paper Trader.bat on the desktop, then Connect + Test for Schwab.
pause
exit /b 0
