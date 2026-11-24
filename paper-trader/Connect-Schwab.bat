@echo off
setlocal EnableExtensions
cd /d "%~dp0"
title Paper Trader — Connect Schwab
echo ============================================================
echo  Schwab Connect from CMD
echo  Desk folder: %CD%
echo  Keys file:   %CD%\.env
echo  Keys stay in that file on disk. CMD does not store them.
echo ============================================================
echo.
echo --- Key status ---
if exist "%~dp0runtime\python.exe" (
  "%~dp0runtime\python.exe" "%~dp0launch.py" schwab-status
  echo.
  echo --- Connect ---
  "%~dp0runtime\python.exe" "%~dp0launch.py" schwab-connect %*
  goto done
)
where python >nul 2>&1
if not errorlevel 1 (
  python "%~dp0launch.py" schwab-status
  echo.
  echo --- Connect ---
  python "%~dp0launch.py" schwab-connect %*
  goto done
)
where py >nul 2>&1
if not errorlevel 1 (
  py -3 "%~dp0launch.py" schwab-status
  echo.
  echo --- Connect ---
  py -3 "%~dp0launch.py" schwab-connect %*
  goto done
)
echo Python was not found. Run Start-Paper-Trader.bat once first.
:done
echo.
echo If status said missing keys, run:  notepad .env
echo   SCHWAB_APP_KEY=your-client-id
echo   SCHWAB_APP_SECRET=your-client-secret
echo   SCHWAB_CALLBACK_URL=https://127.0.0.1
echo Save, then run Connect-Schwab.bat again.
echo If no browser opened, copy the authorize URL into Edge yourself.
pause
exit /b 0
