#!/usr/bin/env python3
"""Apply our decisions to the freshly generated ios/ folder.

The twin of `patch_android.py`, and it exists for the same reason: `ios/` is
regenerated from scratch by `tool/bootstrap.sh` on every single build, so
anything we decided about it has to be written *during* the build or it is not
there at all.

WHAT THIS FIXES, AND WHY IT WOULD OTHERWISE CRASH IN FRONT OF A CUSTOMER
-----------------------------------------------------------------------
iOS does not ask permission for the camera the way Android does. It reads a
sentence out of `Info.plist` and shows it to the owner of the phone — and if
that sentence is **missing**, iOS does not fall back and does not warn: it
terminates the app on the spot, at the instant the camera is opened.

`flutter create`'s template `Info.plist` contains none of these strings. So
every camera button in this app — the eight photo slots on step 3 and the new
inspection-report button beside them — would have killed the app the first time
anyone pressed one, on a real iPhone, in front of whoever was being shown it.
It is also an automatic App Store rejection.

Nothing about this is visible on Android, which is why it survived this long.

AND THE SECOND THING, WHICH COST A BUILD
----------------------------------------
`flutter create` derives the iOS bundle identifier from the project name, so a
freshly generated `ios/` says **com.thamanmotorak.thamanApp** — not the
com.thamanmotorak.app that Android uses, that the App Store record was created
with, and that the provisioning profile is issued for.

`patch_android.py` has always corrected its side. Nothing corrected this one, so
Xcode archived an app nobody had a profile for and the failure came back as
"No valid code signing certificates were found" — which sends you hunting
through certificates for an hour when the certificate was never the problem.

The two ids have to match, and now they do.

Run by tool/bootstrap.sh, straight after patch_android.py. Idempotent.
"""
from __future__ import annotations

import plistlib
import re
import sys
from pathlib import Path

APP = Path(__file__).resolve().parent.parent
PLIST = APP / "ios/Runner/Info.plist"
PBXPROJ = APP / "ios/Runner.xcodeproj/project.pbxproj"
PODFILE = APP / "ios/Podfile"

# What `flutter create --org com.thamanmotorak --project-name thaman_app` writes
# into the Xcode project. Android's generated name is different again
# (com.thamanmotorak.thaman_app) — see patch_android.py.
GENERATED = "com.thamanmotorak.thamanApp"

# The name under the icon. Android says this too — see patch_android.py — and a
# launcher that says one thing on one phone and something else on another makes
# the same app look like two.
DISPLAY_NAME = "Thamanmotorak"

# The oldest iPhone this app will install on.
#
# App Store Connect warns on every upload that 12.0 is too low: 13.0 is
# required later this year and 15.0 in spring 2027. Flutter's template writes
# whatever its own floor happens to be, which is how 12.0 reached build 7
# without anyone choosing it. Chosen here instead, and asserted after writing.
DEPLOYMENT_TARGET = "13.0"

# iPhone only.
#
# Flutter's template ships TARGETED_DEVICE_FAMILY = "1,2", which tells Apple the
# app supports iPad — and App Store Connect then *requires* a set of 13-inch
# iPad screenshots before it will accept a submission. Nothing in this app was
# designed for an iPad: it is a camera-and-form workflow built for a phone.
# Declaring iPhone only removes that requirement; iPads can still install it in
# compatibility mode.
DEVICE_FAMILY = "1"

# Both languages in one sentence, because the phone shows exactly this text and
# we do not know which language its owner reads. The Arabic comes first: this is
# a Qatari product and most of its customers are Arabic speakers.
PERMISSIONS = {
    "NSCameraUsageDescription":
        "لتصوير سيارتك وتقرير الفحص — To photograph your car and its inspection report.",
    "NSPhotoLibraryUsageDescription":
        "لاختيار صور سيارتك من الاستوديو — To choose photos of your car from your library.",
    # The app never records sound. image_picker links the video APIs regardless,
    # and Apple's static analysis flags the missing key on a plugin that could
    # reach the microphone. A string that is never shown costs nothing; a
    # rejection two days before a demo costs a great deal.
    "NSMicrophoneUsageDescription":
        "لا يسجّل التطبيق أي صوت — This app does not record audio.",
}


def die(msg: str) -> None:
    print(f"patch_ios: FAILED — {msg}", file=sys.stderr)
    raise SystemExit(1)


def fix_bundle_id(pkg: str) -> None:
    """Make Xcode build the app the provisioning profile is actually for.

    A blunt string replacement rather than a regex over PRODUCT_BUNDLE_IDENTIFIER,
    because the same prefix also appears on the test target
    (…thamanApp.RunnerTests) and replacing the prefix keeps that suffix intact
    while a per-setting rewrite would flatten both to the same id — which Xcode
    rejects.
    """
    if not PBXPROJ.exists():
        die("ios/Runner.xcodeproj/project.pbxproj is missing")
    s = PBXPROJ.read_text(encoding="utf-8")
    if GENERATED not in s:
        # Either Flutter changed how it derives the id, or someone already
        # changed it by hand. Both are worth stopping for: guessing here is how
        # you ship an app signed for the wrong identifier.
        if pkg in s:
            print(f"  bundle id already {pkg}")
            return
        die(f"neither {GENERATED} nor {pkg} is in project.pbxproj — "
            "Flutter's template changed, look at it before building")
    PBXPROJ.write_text(s.replace(GENERATED, pkg), encoding="utf-8")

    left = [ln.strip() for ln in PBXPROJ.read_text(encoding="utf-8").splitlines()
            if "PRODUCT_BUNDLE_IDENTIFIER" in ln]
    if any(GENERATED in ln for ln in left):
        die("some PRODUCT_BUNDLE_IDENTIFIER lines still carry the generated id")
    print(f"  bundle id -> {pkg}")
    for ln in sorted(set(left)):
        print(f"    {ln}")


def set_deployment_target() -> None:
    """Raise the iOS floor in both places that decide it.

    The Xcode project is what MinimumOSVersion in the shipped Info.plist is
    derived from, so it is the one Apple reads. The Podfile matters too:
    CocoaPods builds every dependency against the platform declared there, and
    a Podfile left at Flutter's commented-out default quietly builds pods for
    an older iOS than the app itself.
    """
    if not PBXPROJ.exists():
        die("ios/Runner.xcodeproj/project.pbxproj is missing")

    s = PBXPROJ.read_text(encoding="utf-8")
    s, n = re.subn(r"IPHONEOS_DEPLOYMENT_TARGET = [0-9.]+;",
                   f"IPHONEOS_DEPLOYMENT_TARGET = {DEPLOYMENT_TARGET};", s)
    if n == 0:
        die("no IPHONEOS_DEPLOYMENT_TARGET in project.pbxproj — "
            "Flutter's template changed, look at it before building")
    s, n2 = re.subn(r'TARGETED_DEVICE_FAMILY = "[0-9,]+";',
                    f'TARGETED_DEVICE_FAMILY = "{DEVICE_FAMILY}";', s)
    if n2 == 0:
        die("no TARGETED_DEVICE_FAMILY in project.pbxproj — "
            "Flutter's template changed, look at it before building")
    PBXPROJ.write_text(s, encoding="utf-8")
    print(f"  device family -> {DEVICE_FAMILY} (iPhone only, {n2} build configurations)")

    # Proof: read it back and make sure nothing lower survived anywhere.
    left = {ln.strip() for ln in PBXPROJ.read_text(encoding="utf-8").splitlines()
            if "IPHONEOS_DEPLOYMENT_TARGET" in ln}
    wrong = [ln for ln in left if f"= {DEPLOYMENT_TARGET};" not in ln]
    if wrong:
        die("these deployment targets did not take: " + "; ".join(sorted(wrong)))
    print(f"  deployment target -> {DEPLOYMENT_TARGET} ({n} build configurations)")

    if PODFILE.exists():
        t = PODFILE.read_text(encoding="utf-8")
        t, n = re.subn(r"^\s*#?\s*platform :ios, ['\"][0-9.]+['\"]",
                       f"platform :ios, '{DEPLOYMENT_TARGET}'", t, count=1,
                       flags=re.MULTILINE)
        if n == 0:
            t = f"platform :ios, '{DEPLOYMENT_TARGET}'\n" + t
        PODFILE.write_text(t, encoding="utf-8")
        if f"platform :ios, '{DEPLOYMENT_TARGET}'" not in PODFILE.read_text(encoding="utf-8"):
            die("Podfile platform line did not take")
        print(f"  Podfile platform -> ios {DEPLOYMENT_TARGET}")


def main() -> None:
    pkg = sys.argv[1] if len(sys.argv) > 1 else "com.thamanmotorak.app"

    fix_bundle_id(pkg)
    set_deployment_target()

    if not PLIST.exists():
        die("ios/Runner/Info.plist does not exist — run tool/bootstrap.sh first")

    try:
        info = plistlib.loads(PLIST.read_bytes())
    except Exception as exc:  # noqa: BLE001 — any parse failure is fatal here
        die(f"could not read Info.plist: {exc}")

    # plistlib rather than a regular expression, deliberately. Info.plist is XML
    # holding Xcode build-setting placeholders like $(EXECUTABLE_NAME); reading
    # and rewriting it properly cannot corrupt one, and cannot half-apply if the
    # template changes shape.
    for key, text in PERMISSIONS.items():
        info[key] = text

    info["CFBundleDisplayName"] = DISPLAY_NAME

    # Answers, once and in the project, the export-compliance question that
    # otherwise interrupts every single upload to App Store Connect. The app
    # talks to thamanmotorak.com over HTTPS and does nothing else with
    # cryptography, which is the standard exemption.
    info["ITSAppUsesNonExemptEncryption"] = False

    PLIST.write_bytes(plistlib.dumps(info, sort_keys=True))

    # ------------------------------------------------------------- proof
    check = plistlib.loads(PLIST.read_bytes())
    missing = [k for k in PERMISSIONS if not str(check.get(k, "")).strip()]
    if missing:
        die("these did not survive the write: " + ", ".join(missing))

    print(f"patch_ios: ok — {pkg}, {DISPLAY_NAME}, "
          f"iOS {DEPLOYMENT_TARGET}+, "
          f"{len(PERMISSIONS)} usage descriptions, export compliance answered")
    for key in sorted(PERMISSIONS):
        print(f"  {key}")


if __name__ == "__main__":
    main()
