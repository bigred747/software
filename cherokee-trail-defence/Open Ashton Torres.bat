@echo off
cd /d "%~dp0"
if exist "%~dp0Ashton-Torres.html" (
  start "" "%~dp0Ashton-Torres.html"
  exit /b 0
)
if exist "%~dp0index.html" (
  start "" "%~dp0index.html"
  exit /b 0
)
echo.
echo Double-click Ashton-Torres.html
echo If you only see this message, unzip the whole folder onto the Desktop first.
echo.
pause
exit /b 1
