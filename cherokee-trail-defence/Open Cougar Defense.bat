@echo off
setlocal
cd /d "%~dp0"
set "PAGE=%~dp0index.html"
set "URL=file:///%PAGE:\=/%"

set "EDGE=%ProgramFiles(x86)%\Microsoft\Edge\Application\msedge.exe"
if not exist "%EDGE%" set "EDGE=%ProgramFiles%\Microsoft\Edge\Application\msedge.exe"
if exist "%EDGE%" (
  start "" "%EDGE%" --app="%URL%"
  exit /b 0
)

set "CHROME=%ProgramFiles%\Google\Chrome\Application\chrome.exe"
if not exist "%CHROME%" set "CHROME=%LocalAppData%\Google\Chrome\Application\chrome.exe"
if exist "%CHROME%" (
  start "" "%CHROME%" --app="%URL%"
  exit /b 0
)

start "" "%PAGE%"
