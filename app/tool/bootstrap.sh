#!/usr/bin/env bash
#
# Generate the android/ and ios/ folders, then apply our changes to them.
#
# Why generate rather than commit them: those folders are Gradle and Xcode
# scaffolding that Flutter regenerates for every SDK release. Committing a
# snapshot means that six months from now the build fails on a Gradle version
# mismatch nobody wrote and nobody can read. Generating them from whichever
# Flutter the build is using means that mismatch cannot happen.
#
# Everything we actually decided — the package id, the app name in two
# languages, the internet permission, the signing config — is applied here, in
# one readable list, instead of being buried in a thousand generated lines.
#
# Safe to run repeatedly. Run it once after cloning, before opening the project
# in Android Studio.
set -euo pipefail

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PKG="com.thamanmotorak.app"
cd "$APP_DIR"

echo "==> generating platform folders with $(flutter --version | head -1)"

TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

# A throwaway project, only so Flutter writes us a matching scaffold.
flutter create \
  --org com.thamanmotorak \
  --project-name thaman_app \
  --platforms=android,ios \
  --no-pub \
  "$TMP/scaffold" > /dev/null

rm -rf android ios
cp -r "$TMP/scaffold/android" ./android
cp -r "$TMP/scaffold/ios" ./ios

echo "==> applying our settings"
python3 tool/patch_android.py "$PKG"

# iOS gets the same treatment, and for the same reason: ios/ was thrown away
# and rewritten three lines ago, so anything we decided about it has to be
# applied here. Chiefly the camera and photo-library usage descriptions —
# without them iOS terminates the app the instant a camera button is pressed.
python3 tool/patch_ios.py "$PKG"

echo "==> done. Package: $PKG"
