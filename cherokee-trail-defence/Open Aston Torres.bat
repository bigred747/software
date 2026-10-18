@echo off
setlocal EnableDelayedExpansion
cd /d "%~dp0"

rem Opening the bat from inside the zip uses a temp folder that disappears.
rem Copy the program to a real folder, then open that copy.
echo %~dp0 | findstr /i /c:".zip" /c:"\Temp\" /c:"\Temporary Internet Files\" >nul
if not errorlevel 1 (
  set "HOME=%USERPROFILE%\AstonTorres"
  if not exist "!HOME!\" mkdir "!HOME!"
  xcopy "%~dp0*.*" "!HOME!\" /E /I /Y /Q >nul
  if exist "!HOME!\index.html" (
    start "" /D "!HOME!" "!HOME!\Open Aston Torres.bat"
    exit /b 0
  )
)

set "PAGE=%~dp0index.html"
if not exist "%PAGE%" goto missing

rem Build a file URL that keeps spaces, so Edge does not report File not found.
set "URL=!PAGE:\=/!"
set "URL=!URL: =%%20!"
set "URL=!URL:&=%%26!"
set "URL=!URL:#=%%23!"
set "URL=file:///!URL!"

set "EDGE=%ProgramFiles(x86)%\Microsoft\Edge\Application\msedge.exe"
if not exist "%EDGE%" set "EDGE=%ProgramFiles%\Microsoft\Edge\Application\msedge.exe"
if exist "%EDGE%" goto edge

set "CHROME=%ProgramFiles%\Google\Chrome\Application\chrome.exe"
if not exist "%CHROME%" set "CHROME=%LocalAppData%\Google\Chrome\Application\chrome.exe"
if exist "%CHROME%" goto chrome

start "" "%PAGE%"
exit /b 0

:edge
start "" "!EDGE!" --app="!URL!"
exit /b 0

:chrome
start "" "!CHROME!" --app="!URL!"
exit /b 0

:missing
echo.
echo Unzip the whole folder onto the computer first.
echo Then open that folder and double-click Open Aston Torres.
echo Do not run this from inside the zip.
echo.
pause
exit /b 1
