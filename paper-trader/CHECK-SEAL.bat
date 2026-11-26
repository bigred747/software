@echo off
setlocal EnableExtensions EnableDelayedExpansion
cd /d "%~dp0"
title Paper Trader CHECK SEAL
echo.
echo ============================================================
echo  CHECK SEAL — what Documents\Paper Trader actually is
echo ============================================================
echo.

echo %~dp0 | findstr /I /C:"\Temp" | findstr /I /C:".zip." >nul
if not errorlevel 1 (
  echo STOP — you opened this from inside the zip Temp folder.
  echo Windows path looks like AppData\Local\Temp\...Paper-Trader....zip...\paper-trader
  echo.
  echo Do this instead:
  echo   1. Close this window
  echo   2. In Downloads, right-click Paper-Trader.zip
  echo   3. Choose Extract All - extract to Downloads\Paper-Trader
  echo   4. Open that extracted paper-trader folder
  echo   5. Double-click CHECK-SEAL.bat or FORCE-UPGRADE.bat there
  echo.
  pause
  exit /b 1
)

if not exist "%~dp0launch.py" (
  echo STOP — launch.py is missing next to this bat.
  echo You must run this from a full Extract All folder, not from inside the zip.
  pause
  exit /b 1
)

set "DEST=%USERPROFILE%\Documents\Paper Trader"
echo Desk folder:
echo   !DEST!
echo.

if not exist "!DEST!\VERSION" (
  echo VERSION file: MISSING
  echo Documents\Paper Trader is not installed yet. Run FORCE-UPGRADE.bat
  echo.
  pause
  exit /b 1
)

echo VERSION file:
type "!DEST!\VERSION"
echo.

if exist "!DEST!\SEAL.txt" (
  echo SEAL.txt:
  type "!DEST!\SEAL.txt"
  echo.
)

if exist "!DEST!\web\app.js" (
  echo web\app.js VERSION line:
  findstr /C:"const VERSION" "!DEST!\web\app.js"
  echo.
) else (
  echo web\app.js: MISSING
  echo.
)

if exist "!DEST!\paper_trader\__init__.py" (
  echo paper_trader\__init__.py version line:
  findstr /C:"__version__" "!DEST!\paper_trader\__init__.py"
  echo.
) else (
  echo paper_trader\__init__.py: MISSING
  echo.
)

set "PY=%~dp0runtime\python.exe"
if not exist "%PY%" set "PY=python"
"%PY%" "%~dp0launch.py" check-seal 2>nul
echo.
echo If VERSION still says 1.10.40, close every desk window and run FORCE-UPGRADE.bat
echo from the extracted folder (not from Temp / not from inside the zip).
echo.
pause
