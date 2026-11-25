@echo off
setlocal EnableExtensions
cd /d "%~dp0"
title Paper Trader CHECK SEAL
set "PY=%~dp0runtime\python.exe"
if not exist "%PY%" set "PY=python"
echo.
echo Checking Documents\Paper Trader seal...
echo.
"%PY%" "%~dp0launch.py" check-seal
echo.
echo Also showing Documents\Paper Trader\VERSION file:
if exist "%USERPROFILE%\Documents\Paper Trader\VERSION" (
  type "%USERPROFILE%\Documents\Paper Trader\VERSION"
) else (
  echo VERSION file missing
)
echo.
pause
