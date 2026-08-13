#!/usr/bin/env python3
"""Write the Android launch screen ourselves, instead of hoping a plugin did.

WHY THIS FILE EXISTS
--------------------
Three builds in a row shipped `flutter_native_splash` correctly configured in
`pubspec.yaml`, with `dart run flutter_native_splash:create` in the Codemagic
script list, and three builds in a row showed nothing branded between tapping
the icon and the app appearing. A generator that can quietly do nothing is not
something to debug a fourth time on somebody else's phone.

So the launch screen is now written here, in about a hundred readable lines,
by the same approach the project already takes for the INTERNET permission:
decide it, write it, and fail the build loudly if it could not be written.
Nothing about it is conditional and nothing about it is silent — the script
prints every file it wrote and the size of every image, so the build log
proves the drawable is there before anybody installs anything.

WHAT ANDROID ACTUALLY NEEDS — it is two different mechanisms
------------------------------------------------------------
*Android 11 and below* draw the activity's `android:windowBackground` for as
long as the window exists but has not been painted. So the branded screen is a
`<layer-list>`: the brand's near-black, with the wordmark centred on top.

*Android 12 and above* took that away. The system draws its own splash from
`windowSplashScreenBackground` + `windowSplashScreenAnimatedIcon`, plays it for
about a second, and then hands over to the app window.

AND THE PART THAT ACTUALLY BIT US — READ THIS BEFORE CHANGING ANYTHING BELOW
----------------------------------------------------------------------------
`LaunchTheme` is not the theme the customer spends the launch looking at.
Flutter's own `FlutterActivity.onCreate()` calls `switchLaunchThemeForNormalTheme()`
— it reads the `io.flutter.embedding.android.NormalTheme` meta-data out of the
manifest and calls `setTheme()` with it — **before** `super.onCreate()`. From
that moment the window is decorated with **NormalTheme**, and Flutter's stock
NormalTheme says `windowBackground = ?android:colorBackground`. Which is white
under `Theme.Light` and pure black under `Theme.Black`.

So the sequence was: the branded LaunchTheme background for the few tens of
milliseconds before the process starts, and then a plain white or plain black
rectangle for the entire 900 ms that `main.dart` holds the first frame back.
Every generator in the world can write a perfect `launch_background.xml` and
the customer will still report that nothing appears — because he is not looking
at LaunchTheme by the time there is anything to see. `flutter_native_splash`
leaves NormalTheme at the default, which is why three builds of it changed
nothing.

**Both themes get the branded background here.** That is the fix. It is one
line in each style block and it is the only line that had to be there.

`main.dart` holds the window up (`FlutterNativeSplash.preserve` / `remove`,
900 ms floor, 4 s ceiling). Those two calls are pure Dart — `deferFirstFrame()`
and `allowFirstFrame()` — so they keep working no matter what is written here.

Run after `flutter pub get`. Idempotent.
"""
from __future__ import annotations

import shutil
import struct
import sys
from pathlib import Path

APP = Path(__file__).resolve().parent.parent
ANDROID = APP / "android"
RES = ANDROID / "app/src/main/res"

# The app's own background. The join between the launch screen and تصميم اليوم
# has to be invisible, so this is `Brand.dBg` from lib/theme.dart, not a colour
# picked to look nice on its own.
BG = "#131013"

# The wide wordmark, drawn centred on Android 11 and below.
# Placed in drawable-xxxhdpi so 900x698 real pixels render as 225x175 dp —
# a little over half the width of a normal phone. Dropping it in plain
# `drawable/` would treat it as mdpi and blow it up to 900 dp.
LOGO = APP / "assets/brand/logo-splash.png"

# The Android 12+ icon. 1152 px at xxxhdpi is exactly the 288 dp the platform
# reserves, and the wordmark sits inside the middle 768 px — the inner two
# thirds, which is the only part the system guarantees is not masked away.
# For that to hold there must be NO icon background colour set: adding one
# switches the platform to the smaller 240/160 dp geometry and would clip both
# ends off the wordmark again.
ICON12 = APP / "tool/splash/logo-splash-android12.png"

DENSITY = "drawable-xxxhdpi"


def die(msg: str) -> None:
    print(f"splash_android: FAILED — {msg}", file=sys.stderr)
    raise SystemExit(1)


def write(path: Path, text: str) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(text, encoding="utf-8")
    print(f"  wrote {path.relative_to(ANDROID)}  ({len(text)} chars)")


def png_size(p: Path) -> tuple[int, int]:
    """Width and height straight out of the PNG header."""
    head = p.read_bytes()[:24]
    if head[:8] != b"\x89PNG\r\n\x1a\n":
        die(f"{p.name} is not a PNG")
    w, h = struct.unpack(">II", head[16:24])
    return w, h


def copy(src: Path, dst: Path, *, expect: tuple[int, int] | None = None) -> None:
    """Copy an image in, and check it is the size the geometry above assumes.

    Every claim in this file's header is a claim about pixels — 1152 is 288 dp,
    the wordmark sits inside the middle 768. Someone re-exporting the artwork a
    little larger would break the Android 12 masking silently, and silently is
    the one thing this file exists to stop.
    """
    if not src.exists():
        die(f"source image missing: {src.relative_to(APP)}")
    size = png_size(src)
    if expect is not None and size != expect:
        die(f"{src.relative_to(APP)} must be {expect[0]}x{expect[1]}, it is {size[0]}x{size[1]}")
    dst.parent.mkdir(parents=True, exist_ok=True)
    shutil.copyfile(src, dst)
    print(f"  wrote {dst.relative_to(ANDROID)}  ({size[0]}x{size[1]}, {dst.stat().st_size} bytes)")


# Our resource names are prefixed so that nothing here can collide with a name
# `flutter create`, flutter_launcher_icons or flutter_native_splash also uses.
# Two files in values/ declaring the same name is a build error, and it would
# be a confusing one.
COLORS = """<?xml version="1.0" encoding="utf-8"?>
<!-- Written by tool/splash_android.py. Do not edit android/ by hand: the
     whole folder is regenerated by tool/bootstrap.sh on every build. -->
<resources>
    <color name="tmk_splash_bg">{bg}</color>
</resources>
"""

LAUNCH_BG = """<?xml version="1.0" encoding="utf-8"?>
<!-- Written by tool/splash_android.py.
     The brand's near-black, with the wordmark centred on it. This is the
     activity's windowBackground, which is what Android shows for as long as
     the window is up and Flutter has not painted a frame yet. -->
<layer-list xmlns:android="http://schemas.android.com/apk/res/android">
    <item android:drawable="@color/tmk_splash_bg" />
    <item>
        <bitmap
            android:gravity="center"
            android:src="@drawable/tmk_splash_logo" />
    </item>
</layer-list>
"""

STYLES = """<?xml version="1.0" encoding="utf-8"?>
<!-- Written by tool/splash_android.py.

     LaunchTheme decorates the window until FlutterActivity.onCreate swaps in
     NormalTheme, which it does before it has anything to draw. NormalTheme
     therefore has to carry the same background — otherwise the launch is
     branded for a few tens of milliseconds and blank for the second that
     follows. See the note at the top of tool/splash_android.py.

     Flutter drops an opaque view over this the moment it has a frame, so the
     background costs nothing after the launch. -->
<resources>
    <style name="LaunchTheme" parent="@android:style/{parent}">
        <item name="android:windowBackground">@drawable/tmk_launch_background</item>
    </style>
    <style name="NormalTheme" parent="@android:style/{parent}">
        <item name="android:windowBackground">@drawable/tmk_launch_background</item>
    </style>
</resources>
"""

STYLES_V31 = """<?xml version="1.0" encoding="utf-8"?>
<!-- Written by tool/splash_android.py — Android 12 (API 31) and above.

     Android 12 draws the splash itself from the two windowSplashScreen*
     attributes and then hands the screen back to the app window. NormalTheme is
     what that window is wearing by then — see the note at the top of
     tool/splash_android.py — so it carries the branded background here too, and
     the hand-over from the system splash to the held first frame is invisible.

     No windowSplashScreenIconBackgroundColor. Setting one moves the platform to
     a smaller icon geometry and would mask the ends off the wordmark. -->
<resources>
    <style name="LaunchTheme" parent="@android:style/{parent}">
        <item name="android:windowSplashScreenBackground">@color/tmk_splash_bg</item>
        <item name="android:windowSplashScreenAnimatedIcon">@drawable/tmk_splash_icon</item>
        <item name="android:windowBackground">@drawable/tmk_launch_background</item>
    </style>
    <style name="NormalTheme" parent="@android:style/{parent}">
        <item name="android:windowBackground">@drawable/tmk_launch_background</item>
    </style>
</resources>
"""

# Light and dark keep Flutter's own parents so that nothing else about the
# window changes; only the background does.
LIGHT = "Theme.Light.NoTitleBar"
DARK = "Theme.Black.NoTitleBar"


def main() -> None:
    if not RES.exists():
        die("android/app/src/main/res does not exist — run tool/bootstrap.sh first")

    print("splash_android: writing the launch screen")

    write(RES / "values/tmk_splash.xml", COLORS.format(bg=BG))
    write(RES / "drawable/tmk_launch_background.xml", LAUNCH_BG)

    copy(LOGO, RES / DENSITY / "tmk_splash_logo.png", expect=(900, 698))
    copy(ICON12, RES / DENSITY / "tmk_splash_icon.png", expect=(1152, 1152))

    write(RES / "values/styles.xml", STYLES.format(parent=LIGHT))
    write(RES / "values-night/styles.xml", STYLES.format(parent=DARK))
    write(RES / "values-v31/styles.xml", STYLES_V31.format(parent=LIGHT))
    write(RES / "values-night-v31/styles.xml", STYLES_V31.format(parent=DARK))

    # ------------------------------------------------------------- proof
    #
    # A build that ships without the launch screen must fail here, on the build
    # machine, and not on a phone in Doha.
    manifest = ANDROID / "app/src/main/AndroidManifest.xml"
    if not manifest.exists():
        die("AndroidManifest.xml is missing")
    src = manifest.read_text(encoding="utf-8")
    if '@style/LaunchTheme' not in src:
        die("the activity does not use @style/LaunchTheme — Flutter's template changed")
    if '@style/NormalTheme' not in src:
        die("the NormalTheme meta-data is gone — Flutter's template changed")

    required = [
        RES / "values/tmk_splash.xml",
        RES / "drawable/tmk_launch_background.xml",
        RES / DENSITY / "tmk_splash_logo.png",
        RES / DENSITY / "tmk_splash_icon.png",
        RES / "values/styles.xml",
        RES / "values-night/styles.xml",
        RES / "values-v31/styles.xml",
        RES / "values-night-v31/styles.xml",
    ]
    missing = [p for p in required if not p.exists() or p.stat().st_size == 0]
    if missing:
        die("these should exist and do not: " + ", ".join(str(p) for p in missing))

    print("splash_android: ok — 8 files, launch screen is in this build")


if __name__ == "__main__":
    main()
