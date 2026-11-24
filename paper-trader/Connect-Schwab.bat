@echo off
setlocal EnableExtensions
cd /d "%~dp0"
title Paper Trader — Connect Schwab
echo ============================================================
echo  Schwab Connect from CMD
echo  Desk folder: %CD%
echo  Keys file:   %CD%\.env
echo ============================================================
echo.
if exist "%~dp0runtime\python.exe" (
  "%~dp0runtime\python.exe" "%~dp0launch.py" schwab-status
  echo.
  "%~dp0runtime\python.exe" "%~dp0launch.py" schwab-connect %*
  goto done
)
where python >nul 2>&1
if not errorlevel 1 (
  python "%~dp0launch.py" schwab-status
  echo.
  python "%~dp0launch.py" schwab-connect %*
  goto done
)
where py >nul 2>&1
if not errorlevel 1 (
  py -3 "%~dp0launch.py" schwab-status
  echo.
  py -3 "%~dp0launch.py" schwab-connect %*
  goto done
)
echo Python was not found. Run Start-Paper-Trader.bat once first.
:done
echo.
pause
