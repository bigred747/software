#!/usr/bin/env python3
"""Build the iPhone web clip and the phone zip from Ashton-Torres.html.

The Home Screen icon opens a normal page. A data URL opens blank on the phone,
and tapping the zip, the apk, or the profile on a computer does not start the call sheet.
"""

import plistlib
import uuid
import zipfile
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
APP = ROOT / "cherokee-trail-defence"
HERE = Path(__file__).resolve().parent
HTML_PATH = APP / "Ashton-Torres.html"
ICON_PATH = APP / "icon.png"
PROFILE_PATH = HERE / "Install Ashton Torres.mobileconfig"
ZIP_PATH = ROOT / "Ashton-Torres-Mobile.zip"
START_PATH = APP / "phone" / "Start here.txt"
APK_PATH = APP / "android" / "Ashton-Torres.apk"
BAT_PATH = APP / "Open Ashton Torres.bat"
OPEN_URL = (
    "https://htmlpreview.github.io/?https://github.com/bigred747/software/blob/"
    "cursor/cougar-defence-call-sheet-1a2c/cherokee-trail-defence/Ashton-Torres.html"
)


def profile(url, icon):
    clip_id = str(uuid.uuid4()).upper()
    root_id = str(uuid.uuid4()).upper()
    description = (
        "This adds the Ashton Torres icon to the Home Screen. "
        "It does not change other settings, and it does not ask for an Apple ID. "
        "The icon opens the call sheet when the phone has a signal."
    )
    return {
        "PayloadContent": [{
            "FullScreen": True,
            "Icon": icon,
            "IgnoreManifestScope": True,
            "IsRemovable": True,
            "Label": "Ashton Torres",
            "PayloadDescription": "Adds the Ashton Torres icon. It does not change any other setting.",
            "PayloadDisplayName": "Ashton Torres",
            "PayloadIdentifier": "com.ashtontorres.defense.webclip",
            "PayloadType": "com.apple.webClip.managed",
            "PayloadUUID": clip_id,
            "PayloadVersion": 1,
            "Precomposed": True,
            "URL": url,
        }],
        "PayloadDescription": description,
        "PayloadDisplayName": "Ashton Torres Defense",
        "PayloadIdentifier": "com.ashtontorres.defense",
        "PayloadOrganization": "Ashton Torres",
        "PayloadRemovalDisallowed": False,
        "PayloadType": "Configuration",
        "PayloadUUID": root_id,
        "PayloadVersion": 1,
    }


def main():
    html_bytes = HTML_PATH.read_bytes()
    icon = ICON_PATH.read_bytes()
    start = START_PATH.read_text(encoding="utf-8")
    if "Clicking this zip does not open the call sheet." not in start:
        raise SystemExit("Start here does not say the zip itself does not open")
    if OPEN_URL not in start:
        raise SystemExit("Start here is missing the open link")
    data = profile(OPEN_URL, icon)
    blob = plistlib.dumps(data, fmt=plistlib.FMT_XML, sort_keys=False)
    PROFILE_PATH.write_bytes(blob)
    names = []
    with zipfile.ZipFile(ZIP_PATH, "w", compression=zipfile.ZIP_DEFLATED) as zf:
        for path in (START_PATH, BAT_PATH, HTML_PATH, APK_PATH, PROFILE_PATH):
            if not path.exists():
                raise SystemExit("missing " + str(path))
            zf.write(path, arcname=path.name)
            names.append(path.name)
    print("html", len(html_bytes))
    print("profile", PROFILE_PATH.stat().st_size)
    print("zip", ZIP_PATH.stat().st_size)
    with zipfile.ZipFile(ZIP_PATH) as zf:
        packed = zf.namelist()
    if packed != names:
        raise SystemExit("zip names " + str(packed))
    loaded = plistlib.loads(PROFILE_PATH.read_bytes())
    got = loaded["PayloadContent"][0]["URL"]
    if got != OPEN_URL:
        raise SystemExit("profile URL did not round-trip")
    if got.startswith("data:"):
        raise SystemExit("profile still uses a data URL")
    if loaded["PayloadDisplayName"] != "Ashton Torres Defense":
        raise SystemExit("display name missing")
    if loaded["PayloadContent"][0]["Icon"][:8] != b"\x89PNG\r\n\x1a\n":
        raise SystemExit("icon is not a png")
    print("plist ok")


if __name__ == "__main__":
    main()
