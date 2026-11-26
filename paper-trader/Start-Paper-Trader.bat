@echo off
setlocal EnableExtensions EnableDelayedExpansion
cd /d "%~dp0"
rem Fibs Lab pack: never hijack the working Paper Trader.bat shortcut.
if exist "%~dp0FIBS-LAB.txt" (
  if exist "%~dp0Start-Fibs-Lab.bat" (
    call "%~dp0Start-Fibs-Lab.bat"
    exit /b %ERRORLEVEL%
  )
)
title Paper Trader
echo %~dp0 | findstr /I /C:"\Temp" | findstr /I /C:".zip." >nul
if not errorlevel 1 goto FromZip
goto Normal

:FromZip
echo Opened from inside the zip Temp folder.
echo Finding the NEWEST Paper-Trader zip by VERSION seal...
set "PY="
if exist "%~dp0runtime\python.exe" set "PY=%~dp0runtime\python.exe"
if not defined PY if exist "%USERPROFILE%\Documents\Paper Trader\runtime\python.exe" set "PY=%USERPROFILE%\Documents\Paper Trader\runtime\python.exe"
if not defined PY (
  where python >nul 2>&1
  if not errorlevel 1 set "PY=python"
)
if not defined PY (
  where py >nul 2>&1
  if not errorlevel 1 set "PY=py -3"
)
if not defined PY (
  echo Python was not found. Right-click the NEW zip - Extract All - open Start-Paper-Trader.bat there.
  pause
  exit /b 1
)

rem Full modern Temp pack can rescue itself.
if exist "%~dp0launch.py" if exist "%~dp0VERSION" if exist "%~dp0paper_trader\__init__.py" (
  set /p TEMPSEAL=<"%~dp0VERSION"
  echo Temp pack seal: !TEMPSEAL!
  echo !TEMPSEAL! | findstr /B /C:"1.9." >nul
  if errorlevel 1 (
    "%PY%" "%~dp0launch.py" rescue > "%TEMP%\paper-trader-rescue.txt" 2>&1
    if not errorlevel 1 (
      type "%TEMP%\paper-trader-rescue.txt"
      goto AfterRescue
    )
  ) else (
    echo Temp pack !TEMPSEAL! is ancient - ignoring it.
  )
)

rem Build a tiny picker in TEMP so we do not need tools\ from the incomplete zip Temp.
> "%TEMP%\paper-trader-pick-zip.py" echo import os, re, sys, zipfile
>> "%TEMP%\paper-trader-pick-zip.py" echo from pathlib import Path
>> "%TEMP%\paper-trader-pick-zip.py" echo home = Path.home()
>> "%TEMP%\paper-trader-pick-zip.py" echo roots = [home/"Downloads", home/"Desktop", home/"Documents"]
>> "%TEMP%\paper-trader-pick-zip.py" echo od = os.environ.get("OneDrive")
>> "%TEMP%\paper-trader-pick-zip.py" echo roots += [Path(od)/x for x in ("Downloads","Desktop","Documents")] if od else []
>> "%TEMP%\paper-trader-pick-zip.py" echo rows = [p for r in roots if r.is_dir() for p in r.glob("Paper-Trader*.zip") if p.is_file()]
>> "%TEMP%\paper-trader-pick-zip.py" echo def ver(p):
>> "%TEMP%\paper-trader-pick-zip.py" echo     try:
>> "%TEMP%\paper-trader-pick-zip.py" echo         raw = zipfile.ZipFile(p).read("paper-trader/VERSION").decode("utf-8", "ignore").strip()
>> "%TEMP%\paper-trader-pick-zip.py" echo         m = re.search(r"(\d+(?:\.\d+)+)", raw)
>> "%TEMP%\paper-trader-pick-zip.py" echo         return tuple(int(x) for x in m.group(1).split(".")) if m else (0,)
>> "%TEMP%\paper-trader-pick-zip.py" echo     except Exception:
>> "%TEMP%\paper-trader-pick-zip.py" echo         return (0,)
>> "%TEMP%\paper-trader-pick-zip.py" echo if not rows: print("NONE"); raise SystemExit(1)
>> "%TEMP%\paper-trader-pick-zip.py" echo best = max(rows, key=lambda p: (ver(p), 0 if "-slim" in p.name.lower() else 1, p.stat().st_mtime))
>> "%TEMP%\paper-trader-pick-zip.py" echo print(best)
>> "%TEMP%\paper-trader-pick-zip.py" echo raise SystemExit(0 if ver(best) ^>= (1, 10, 40) else 2)

if exist "%~dp0tools\pick_upgrade_zip.py" (
  "%PY%" "%~dp0tools\pick_upgrade_zip.py" > "%TEMP%\paper-trader-pick.txt" 2> "%TEMP%\paper-trader-pick-err.txt"
) else (
  "%PY%" "%TEMP%\paper-trader-pick-zip.py" > "%TEMP%\paper-trader-pick.txt" 2> "%TEMP%\paper-trader-pick-err.txt"
)
set "PICKERR=!errorlevel!"
set "ZIP="
set /p ZIP=<"%TEMP%\paper-trader-pick.txt"

if not defined ZIP goto NoZip
if /I "!ZIP!"=="NONE" goto NoZip
if "!PICKERR!"=="1" goto NoZip
if "!PICKERR!"=="2" (
  echo Newest zip found is too old ^(need seal 1.10.40+^):
  echo   !ZIP!
  echo Delete old Paper-Trader.zip copies in Downloads, download the latest, retry.
  pause
  exit /b 1
)

echo Using zip:
echo   !ZIP!
if not exist "%USERPROFILE%\Documents" mkdir "%USERPROFILE%\Documents"
set "STAGE=%USERPROFILE%\Documents\PaperTrader-setup-%RANDOM%%RANDOM%"
mkdir "%STAGE%" 2>nul
if not exist "%STAGE%\." goto BadZip

echo Unpacking full pack...
"%PY%" -c "import zipfile,sys;zipfile.ZipFile(sys.argv[1]).extractall(sys.argv[2]);print(sys.argv[2])" "!ZIP!" "!STAGE!" > "%TEMP%\paper-trader-extract.txt" 2>&1
if errorlevel 1 (
  type "%TEMP%\paper-trader-extract.txt"
  goto BadZip
)
if not exist "!STAGE!\paper-trader\Start-Paper-Trader.bat" goto BadZip
if not exist "!STAGE!\paper-trader\launch.py" goto BadZip
if not exist "!STAGE!\paper-trader\VERSION" goto BadZip
set /p NEWSEAL=<"!STAGE!\paper-trader\VERSION"
echo Unpacked seal: !NEWSEAL!
echo !NEWSEAL! | findstr /B /C:"1.9." >nul
if not errorlevel 1 (
  echo REFUSING seal !NEWSEAL! - that is the old desk.
  echo Download the latest zip and delete old Paper-Trader.zip files in Downloads.
  pause
  exit /b 1
)

set "RUN=!STAGE!\paper-trader"
set "PY2=!RUN!\runtime\python.exe"
if not exist "!PY2!" set "PY2=!PY!"
"!PY2!" "!RUN!\launch.py" desktop-link > "%TEMP%\paper-trader-desktop.txt" 2>&1
type "%TEMP%\paper-trader-desktop.txt"
echo Desk folder: !RUN!
echo Starting Paper Trader !NEWSEAL! ...
start "Paper Trader" /D "!RUN!" cmd /c "Start-Paper-Trader.bat"
exit /b 0

:AfterRescue
set "RUN="
for /f "usebackq delims=" %%L in ("%TEMP%\paper-trader-rescue.txt") do (
  if not defined RUN set "RUN=%%L"
)
if not defined RUN goto BadZip
if not exist "!RUN!\Start-Paper-Trader.bat" goto BadZip
echo Desk folder: !RUN!
start "Paper Trader" /D "!RUN!" cmd /c "Start-Paper-Trader.bat"
exit /b 0

:NoZip
echo Could not find Paper-Trader*.zip with seal 1.10.40 or newer.
echo Download the latest zip into Downloads, Extract All, open paper-trader\Start-Paper-Trader.bat
pause
exit /b 1

:BadZip
echo Could not unpack a modern Paper Trader.
echo Right-click the NEW zip - Extract All - open Start-Paper-Trader.bat in paper-trader.
pause
exit /b 1

:Normal
set "SEAL="
if exist "%~dp0VERSION" set /p SEAL=<"%~dp0VERSION"
echo Starting Paper Trader !SEAL! ...
if not defined SEAL (
  echo No VERSION file. Extract All the latest zip and open that Start-Paper-Trader.bat.
  pause
  exit /b 1
)
echo !SEAL! | findstr /B /C:"1.9." >nul
if not errorlevel 1 (
  echo.
  echo STOP - this folder is seal !SEAL! ^(old 1.9 desk^).
  echo Your good builds were 1.10.40 / 1.10.51+.
  echo Download the latest zip, Extract All, open that Start-Paper-Trader.bat.
  pause
  exit /b 1
)

set "PY=%~dp0runtime\python.exe"
if not exist "%PY%" set "PY=python"
if exist "%~dp0launch.py" (
  "%PY%" "%~dp0launch.py" desktop-link > "%TEMP%\paper-trader-desktop.txt" 2>&1
  type "%TEMP%\paper-trader-desktop.txt"
)
call :DeskLink "%~dp0"
call :MakePhone "%~dp0"
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
echo Python was not found. Extract All the latest zip, then open Start-Paper-Trader.bat.
:done
pause
exit /b 0

:DeskLink
if not exist "%USERPROFILE%\Desktop" mkdir "%USERPROFILE%\Desktop" 2>nul
call :WriteLink "%USERPROFILE%\Desktop" "%~1"
call :WriteLink "%USERPROFILE%\OneDrive\Desktop" "%~1"
if defined OneDrive call :WriteLink "%OneDrive%\Desktop" "%~1"
if exist "%USERPROFILE%\Desktop\Paper Trader.bat" (
  echo Desktop shortcut ready: %USERPROFILE%\Desktop\Paper Trader.bat
) else (
  echo Check OneDrive Desktop for Paper Trader.bat
)
exit /b 0

:WriteLink
if not exist "%~1" mkdir "%~1" 2>nul
if not exist "%~1" exit /b 0
> "%~1\Paper Trader.bat" echo @echo off
>> "%~1\Paper Trader.bat" echo cd /d "%~2"
>> "%~1\Paper Trader.bat" echo title Paper Trader
>> "%~1\Paper Trader.bat" echo call "Start-Paper-Trader.bat"
exit /b 0

:MakePhone
set "PHONE=%USERPROFILE%\Documents\iPhone"
if not exist "%USERPROFILE%\Documents" mkdir "%USERPROFILE%\Documents"
if not exist "%PHONE%" mkdir "%PHONE%"
set "SRC=%~1"
if "!SRC:~-1!"=="\" set "SRC=!SRC:~0,-1!"
if exist "%SRC%\iphone\PaperTrader.html" copy /y "%SRC%\iphone\PaperTrader.html" "%PHONE%\PaperTrader.html" >nul
if exist "%SRC%\iphone\README.txt" copy /y "%SRC%\iphone\README.txt" "%PHONE%\README.txt" >nul
exit /b 0
