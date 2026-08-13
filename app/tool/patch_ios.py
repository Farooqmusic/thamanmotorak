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

Run by tool/bootstrap.sh, straight after patch_android.py. Idempotent.
"""
from __future__ import annotations

import plistlib
import sys
from pathlib import Path

APP = Path(__file__).resolve().parent.parent
PLIST = APP / "ios/Runner/Info.plist"

# The name under the icon. Android says this too — see patch_android.py — and a
# launcher that says one thing on one phone and something else on another makes
# the same app look like two.
DISPLAY_NAME = "Thamanmotorak"

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


def main() -> None:
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

    print(f"patch_ios: ok — {DISPLAY_NAME}, "
          f"{len(PERMISSIONS)} usage descriptions, export compliance answered")
    for key in sorted(PERMISSIONS):
        print(f"  {key}")


if __name__ == "__main__":
    main()
