#!/bin/bash
# Build Ashton-Torres.apk. Needs Android platform 34 and build-tools 34.
set -euo pipefail
HERE="$(cd "$(dirname "$0")" && pwd)"
APP="$(cd "$HERE/.." && pwd)"
SDK="${ANDROID_SDK:-/tmp/android-sdk}"
BT="$(find "$SDK" -type f -name aapt2 | sort | tail -n 1)"
JAR="$(find "$SDK" -type f -name android.jar | head -n 1)"
if [[ -z "$BT" || -z "$JAR" ]]; then
  echo "Android SDK not found under $SDK" >&2
  exit 1
fi
BIN="$(dirname "$BT")"
WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT
mkdir -p "$HERE/res/drawable" "$WORK/classes" "$WORK/compiled" "$WORK/assets"
cp "$APP/icon.png" "$HERE/res/drawable/ic_launcher.png"
cp "$APP/Ashton-Torres.html" "$WORK/assets/Ashton-Torres.html"
javac --release 11 -classpath "$JAR" -d "$WORK/classes" "$HERE/src/com/ashtontorres/defense/MainActivity.java"
"$BIN/d8" --min-api 24 --output "$WORK" "$WORK/classes/com/ashtontorres/defense/"*.class
"$BIN/aapt2" compile --dir "$HERE/res" -o "$WORK/compiled/res.zip"
"$BIN/aapt2" link -o "$WORK/unsigned.apk" \
  --manifest "$HERE/AndroidManifest.xml" \
  -I "$JAR" \
  --min-sdk-version 24 \
  --target-sdk-version 34 \
  --auto-add-overlay \
  "$WORK/compiled/res.zip"
( cd "$WORK" && zip -q -u unsigned.apk classes.dex && zip -q -r unsigned.apk assets )
if [[ ! -f "$HERE/ashton.keystore" ]]; then
  keytool -genkeypair -keystore "$HERE/ashton.keystore" -storepass ashtontorres -keypass ashtontorres \
    -alias ashton -keyalg RSA -keysize 2048 -validity 10000 \
    -dname "CN=Ashton Torres, O=Ashton Torres, C=US"
fi
"$BIN/zipalign" -p -f 4 "$WORK/unsigned.apk" "$WORK/aligned.apk"
"$BIN/apksigner" sign --ks "$HERE/ashton.keystore" --ks-pass pass:ashtontorres --key-pass pass:ashtontorres \
  --out "$HERE/Ashton-Torres.apk" "$WORK/aligned.apk"
"$BIN/apksigner" verify --verbose "$HERE/Ashton-Torres.apk"
echo "wrote $HERE/Ashton-Torres.apk"
