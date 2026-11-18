@echo off
setlocal EnableExtensions
title Install CFP Re-Rank Board
echo.
echo  Installing latest CFP Re-Rank Board...
echo  Desktop name: CFP Re-Rank Board
echo.
powershell -NoProfile -ExecutionPolicy Bypass -Command ^
  "$ErrorActionPreference='Stop';" ^
  "$zipUrl='https://raw.githubusercontent.com/bigred747/software/main/downloads/CFP-Re-Rank-Board-Windows11.zip';" ^
  "$permanent=Join-Path $env:USERPROFILE 'Documents\CFP Re-Rank Board';" ^
  "$name='CFP Re-Rank Board';" ^
  "$zipPath=Join-Path $env:TEMP 'CFP-Re-Rank-Board-Windows11-latest.zip';" ^
  "$extract=Join-Path $env:TEMP ('CFP-Re-Rank-Extract-' + [guid]::NewGuid().ToString('N'));" ^
  "Write-Host 'Downloading latest package...';" ^
  "Invoke-WebRequest -Uri $zipUrl -OutFile $zipPath -UseBasicParsing;" ^
  "if (Test-Path $extract) { Remove-Item -Recurse -Force $extract };" ^
  "Expand-Archive -LiteralPath $zipPath -DestinationPath $extract -Force;" ^
  "$root = Get-ChildItem -Path $extract -Recurse -Directory | Where-Object { (Test-Path (Join-Path $_.FullName 'Start CFP Re-Rank.bat')) -and (Test-Path (Join-Path $_.FullName 'cfp-logo.ico')) -and (Test-Path (Join-Path $_.FullName 'app')) } | Select-Object -First 1;" ^
  "if (-not $root) { throw 'Downloaded zip was incomplete.' };" ^
  "New-Item -ItemType Directory -Force -Path $permanent | Out-Null;" ^
  "robocopy $root.FullName $permanent /E /NFL /NDL /NJH /NJS /nc /ns /np | Out-Null;" ^
  "if ($LASTEXITCODE -ge 8) { throw ('Copy failed: ' + $LASTEXITCODE) };" ^
  "$nested=Join-Path $permanent 'app\CFP-Re-Rank-Board-Windows11.zip'; if (Test-Path $nested) { Remove-Item -Force $nested };" ^
  "$dl=Join-Path $permanent 'app\download-windows.html'; if (Test-Path $dl) { Remove-Item -Force $dl };" ^
  "$startBat=Join-Path $permanent 'Start CFP Re-Rank.bat'; $icon=Join-Path $permanent 'cfp-logo.ico';" ^
  "$desktops=@(); foreach ($p in @([Environment]::GetFolderPath('Desktop'), (Join-Path $env:USERPROFILE 'Desktop'), (Join-Path $env:USERPROFILE 'OneDrive\Desktop'), (Join-Path $env:USERPROFILE 'OneDrive - Personal\Desktop'), $(if($env:OneDrive){Join-Path $env:OneDrive 'Desktop'}), [Environment]::GetFolderPath('CommonDesktopDirectory'))) { if ($p -and (Test-Path $p)) { $f=[IO.Path]::GetFullPath($p); if ($desktops -notcontains $f) { $desktops += $f } } };" ^
  "$shell=New-Object -ComObject WScript.Shell; $primary=$null;" ^
  "foreach ($d in $desktops) { foreach ($old in @('CFP Re-Rank.lnk','CFP Re-Rank Board.lnk')) { $op=Join-Path $d $old; if (Test-Path $op) { Remove-Item -Force $op -ErrorAction SilentlyContinue } }; $sp=Join-Path $d ($name + '.lnk'); $s=$shell.CreateShortcut($sp); $s.TargetPath=$startBat; $s.WorkingDirectory=$permanent; $s.IconLocation=($icon + ',0'); $s.Description='Open CFP Re-Rank Board'; $s.Save(); if (-not $primary) { $primary=$sp } };" ^
  "Start-Process explorer.exe -ArgumentList @('/select,' + $primary);" ^
  "Add-Type -AssemblyName PresentationFramework;" ^
  "[Windows.MessageBox]::Show(('Installed.' + [char]13 + [char]10 + [char]13 + [char]10 + 'Desktop name: ' + $name + [char]13 + [char]10 + 'Folder: ' + $permanent),'CFP Re-Rank Board') | Out-Null;" ^
  "Remove-Item -Recurse -Force $extract -ErrorAction SilentlyContinue; Remove-Item -Force $zipPath -ErrorAction SilentlyContinue"
if errorlevel 1 (
  echo Install failed. Check your internet connection and try again.
) else (
  echo.
  echo  Done. Double-click Desktop icon: CFP Re-Rank Board
)
echo.
pause
