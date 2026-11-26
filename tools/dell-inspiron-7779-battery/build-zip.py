#!/usr/bin/env python3
"""Build the single-file Windows launcher and the download zip.

Windows runs a file opened from inside a zip by copying that one file to
Temp. The .cmd therefore carries the PowerShell check after a marker so
the other files do not have to be extracted first.
"""
from __future__ import annotations

import zipfile
from pathlib import Path

ROOT = Path(__file__).resolve().parent
PS1_NAME = "Fix-Dell-Inspiron-7779-Battery.ps1"
CMD_NAME = "Run-Battery-Connection-Check.cmd"
README_NAME = "README.txt"
MARKER = "###BATTERY-SCRIPT###"
PREFIX = "Fix-Dell-Inspiron-17-7779-Battery/"
ZIPS = [
    ROOT.parents[1] / "Fix-Dell-Inspiron-17-7779-Battery-Connection.zip",
    ROOT.parents[1] / "dist" / "Fix-Dell-Inspiron-17-7779-Battery-Connection.zip",
]

HEADER = r"""@echo off
setlocal
title Dell Inspiron 17 7779 battery connection
cd /d "%~dp0"
net session >nul 2>&1
if not "%errorlevel%"=="0" (
  echo Requesting administrator permission...
  powershell.exe -NoProfile -Command "Start-Process -FilePath '%~f0' -Verb RunAs"
  exit /b
)
echo.
echo Dell Inspiron 17 7779  --  65W charge path
echo Leave the 65W cord plugged into the wall and the laptop.
echo.
set BATTERY_CHECK_NO_PAUSE=1
set "SELF=%~f0"
if exist "%~dp0Fix-Dell-Inspiron-7779-Battery.ps1" goto runfile
echo Preparing the check...
set "OUT=%TEMP%\Fix-Dell-Inspiron-7779-Battery.ps1"
powershell.exe -NoProfile -ExecutionPolicy Bypass -Command "$raw = Get-Content -LiteralPath $env:SELF -Raw; $mark = '###BATTERY-SCRIPT###'; $i = $raw.LastIndexOf($mark); if ($i -lt 0) { Write-Host 'Payload missing'; exit 1 }; $script = $raw.Substring($i + $mark.Length).Trim(); [System.IO.File]::WriteAllText($env:OUT, $script)"
if not exist "%OUT%" (
  echo The check could not be prepared. Right-click the zip, choose Extract All, then run this file from the folder.
  goto done
)
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%OUT%"
goto done
:runfile
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0Fix-Dell-Inspiron-7779-Battery.ps1"
:done
echo.
pause
exit /b
"""


def crlf(text: str) -> bytes:
    return text.replace("\r\n", "\n").replace("\n", "\r\n").encode("ascii")


def build_cmd(script: str) -> bytes:
    if MARKER in script:
        raise SystemExit("marker collides with the PowerShell script")
    body = HEADER
    if not body.endswith("\n"):
        body += "\n"
    body += MARKER + "\n" + script
    if not body.endswith("\n"):
        body += "\n"
    return crlf(body)


def main() -> None:
    script = (ROOT / PS1_NAME).read_text(encoding="utf-8")
    readme = (ROOT / README_NAME).read_text(encoding="utf-8")
    cmd = build_cmd(script)
    (ROOT / CMD_NAME).write_bytes(cmd)
    payload = {
        PREFIX + README_NAME: crlf(readme),
        PREFIX + CMD_NAME: cmd,
        PREFIX + PS1_NAME: crlf(script),
    }
    for dest in ZIPS:
        dest.parent.mkdir(parents=True, exist_ok=True)
        with zipfile.ZipFile(dest, "w", compression=zipfile.ZIP_DEFLATED) as zf:
            for name, data in payload.items():
                zf.writestr(name, data)
        print(f"{dest} {dest.stat().st_size} bytes")


if __name__ == "__main__":
    main()
