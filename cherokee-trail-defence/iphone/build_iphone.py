#!/usr/bin/env python3
"""Build the iPhone web clip and the phone zip from Ashton-Torres.html."""

import gzip
import plistlib
import uuid
import zipfile
from pathlib import Path
from urllib.parse import quote

ROOT = Path(__file__).resolve().parents[2]
APP = ROOT / "cherokee-trail-defence"
HERE = Path(__file__).resolve().parent
HTML_PATH = APP / "Ashton-Torres.html"
ICON_PATH = APP / "icon.png"
PROFILE_PATH = HERE / "Install Ashton Torres.mobileconfig"
ZIP_PATH = ROOT / "Ashton-Torres-Mobile.zip"
START_PATH = ROOT / "cherokee-trail-defence" / "phone" / "Start here.txt"
APK_PATH = ROOT / "cherokee-trail-defence" / "android" / "Ashton-Torres.apk"
BOOT_PATH = Path("/tmp/ashton-boot.html")


def bootstrap(html_bytes):
    packed = gzip.compress(html_bytes, mtime=0)
    payload = __import__("base64").b64encode(packed).decode("ascii")
    page = """<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="Ashton Torres">
<meta name="theme-color" content="#10283f">
<title>Ashton Torres</title>
<style>body{margin:0;background:#10283f;color:#f7fafc;font:20px -apple-system,system-ui,sans-serif;padding:28px}</style>
</head>
<body>
<p id="m">Opening Ashton Torres.</p>
<script>
(async function () {
  var packed = "%s";
  try {
    var raw = atob(packed);
    var bytes = new Uint8Array(raw.length);
    for (var i = 0; i < raw.length; i++) bytes[i] = raw.charCodeAt(i);
    var html = await new Response(new Blob([bytes]).stream().pipeThrough(new DecompressionStream("gzip"))).text();
    document.open();
    document.write(html);
    document.close();
  } catch (err) {
    var note = document.getElementById("m");
    if (note) note.textContent = "This iPhone could not open the call sheet. Use Ashton-Torres.html on the computer.";
  }
})();
</script>
</body>
</html>
""" % payload
    return page


def profile(url, icon):
    clip_id = str(uuid.uuid4()).upper()
    root_id = str(uuid.uuid4()).upper()
    description = (
        "This adds the Ashton Torres icon to the Home Screen. "
        "It does not change other settings, and it does not ask for an Apple ID."
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
    page = bootstrap(html_bytes)
    BOOT_PATH.write_text(page, encoding="utf-8")
    url = "data:text/html;charset=utf-8," + quote(page, safe="")
    data = profile(url, icon)
    blob = plistlib.dumps(data, fmt=plistlib.FMT_XML, sort_keys=False)
    PROFILE_PATH.write_bytes(blob)
    with zipfile.ZipFile(ZIP_PATH, "w", compression=zipfile.ZIP_DEFLATED) as zf:
        for path in (START_PATH, APK_PATH, PROFILE_PATH, HTML_PATH):
            if not path.exists():
                raise SystemExit("missing " + str(path))
            zf.write(path, arcname=path.name)
    print("html", len(html_bytes))
    print("bootstrap", len(page.encode()))
    print("url", len(url))
    print("profile", PROFILE_PATH.stat().st_size)
    print("zip", ZIP_PATH.stat().st_size)
    with zipfile.ZipFile(ZIP_PATH) as zf:
        names = zf.namelist()
    expected = ["Start here.txt", "Ashton-Torres.apk", "Install Ashton Torres.mobileconfig", "Ashton-Torres.html"]
    if names != expected:
        raise SystemExit("zip names " + str(names))
    loaded = plistlib.loads(PROFILE_PATH.read_bytes())
    got = loaded["PayloadContent"][0]["URL"]
    if got != url:
        raise SystemExit("profile URL did not round-trip")
    if loaded["PayloadDisplayName"] != "Ashton Torres Defense":
        raise SystemExit("display name missing")
    if b"Ashton Torres" not in loaded["PayloadContent"][0]["Icon"]:
        # PNG signature is enough; the name is not inside the icon bytes.
        pass
    if loaded["PayloadContent"][0]["Icon"][:8] != b"\x89PNG\r\n\x1a\n":
        raise SystemExit("icon is not a png")
    print("plist ok")


if __name__ == "__main__":
    main()
