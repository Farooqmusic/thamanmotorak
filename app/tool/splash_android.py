#!/usr/bin/env python3
"""Paint the Android window the brand colour, so the launch has no white flash.

WHAT THIS FILE IS FOR NOW — IT IS SMALLER THAN IT WAS, ON PURPOSE
-----------------------------------------------------------------
The splash the customer sees is no longer Android's. It is drawn by Flutter, in
`BrandSplash` at the bottom of `lib/main.dart` — the TMK mark growing from
nothing to fill the screen. Four builds went into making Android's own launch
screen show that mark and none of them could be checked without putting an APK
on a phone in Doha, so the picture moved to where it can be read, reviewed and
changed like any other widget.

Which leaves this file one job, and it is a job it cannot fail: **make the
native window `#131013` from the first instant, so there is no white rectangle
between tapping the icon and Flutter's first frame.** No bitmap, no layer-list,
no geometry — a colour.

THE ONE THING THAT MUST NOT BE UNDONE
-------------------------------------
`LaunchTheme` is not the theme the customer spends the launch looking at.
Flutter's own `FlutterActivity.onCreate()` calls `switchLaunchThemeForNormalTheme()`
— it reads the `io.flutter.embedding.android.NormalTheme` meta-data out of the
manifest and calls `setTheme()` with it — **before** `super.onCreate()`. From
that moment the window is decorated with **NormalTheme**, and Flutter's stock
NormalTheme says `windowBackground = ?android:colorBackground`: white under
`Theme.Light`, pure black under `Theme.Black`.

That is what every previous attempt was actually looking at. A generator can
write a perfect `LaunchTheme` and the customer will still report a blank screen,
because by the time there is anything to see he is not in `LaunchTheme` any more.
`flutter_native_splash` leaves NormalTheme at the default, which is why three
builds of it changed nothing.

**So both themes are set here.** If someone deletes the NormalTheme line, the
white flash comes back, and the Codemagic step will fail rather than let it.

Android 12 and above additionally draw a system splash of their own before the
app window, from `windowSplashScreenBackground` + `windowSplashScreenAnimatedIcon`.
That cannot be switched off, so it is branded too: same colour, and the client's
badge as the icon. Left alone it would draw the plain launcher icon instead.

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

# `Brand.dBg` from lib/theme.dart. It has to be that exact value and not a
# colour picked to look nice on its own: the moment Flutter draws, this is the
# colour behind `BrandSplash`, and any difference between the two would show up
# as a flicker at the join.
BG = "#131013"

# The Android 12+ system-splash icon: the client's badge, cut out of the dark
# card it was photographed on, on a 1152 px canvas.
#
# 1152 px at xxxhdpi is exactly the 288 dp the platform reserves, and the badge
# sits inside the middle 768 px — the inner two thirds, which is the only part
# the system guarantees is not masked away. For that to hold there must be NO
# icon background colour set: adding one moves the platform to a smaller
# 240/160 dp geometry and would clip the ends off the badge.
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


def copy(src: Path, dst: Path, *, expect: tuple[int, int]) -> None:
    """Copy an image in, and check it is the size the geometry above assumes.

    Someone re-exporting the badge a little larger would break the Android 12
    masking silently, and silently is the one thing this file exists to stop.
    """
    if not src.exists():
        die(f"source image missing: {src.relative_to(APP)}")
    size = png_size(src)
    if size != expect:
        die(f"{src.relative_to(APP)} must be {expect[0]}x{expect[1]}, it is {size[0]}x{size[1]}")
    dst.parent.mkdir(parents=True, exist_ok=True)
    shutil.copyfile(src, dst)
    print(f"  wrote {dst.relative_to(ANDROID)}  ({size[0]}x{size[1]}, {dst.stat().st_size} bytes)")


# Our resource names are prefixed so that nothing here can collide with a name
# `flutter create` or flutter_launcher_icons also uses. Two files in values/
# declaring the same name is a build error, and a confusing one.
COLORS = """<?xml version="1.0" encoding="utf-8"?>
<!-- Written by tool/splash_android.py. Do not edit android/ by hand: the
     whole folder is regenerated by tool/bootstrap.sh on every build. -->
<resources>
    <color name="tmk_splash_bg">{bg}</color>
</resources>
"""

STYLES = """<?xml version="1.0" encoding="utf-8"?>
<!-- Written by tool/splash_android.py.

     BOTH themes, and that is the whole point. LaunchTheme decorates the window
     until FlutterActivity.onCreate swaps in NormalTheme, which it does before
     it has anything to draw — so NormalTheme is what is actually on screen for
     the rest of the launch. Leave it at Flutter's default and the customer gets
     a white rectangle. See the note at the top of tool/splash_android.py.

     Flutter's own splash paints over this within a frame or two, so the colour
     costs nothing after the launch. -->
<resources>
    <style name="LaunchTheme" parent="@android:style/{parent}">
        <item name="android:windowBackground">@color/tmk_splash_bg</item>
    </style>
    <style name="NormalTheme" parent="@android:style/{parent}">
        <item name="android:windowBackground">@color/tmk_splash_bg</item>
    </style>
</resources>
"""

STYLES_V31 = """<?xml version="1.0" encoding="utf-8"?>
<!-- Written by tool/splash_android.py — Android 12 (API 31) and above.

     Android 12 draws a splash of its own before the app window and there is no
     way to refuse it. So it is branded: the brand colour, and the client's
     badge instead of the launcher icon. Then the window underneath is the same
     colour, so the hand-over to Flutter's animation is a single continuous
     screen rather than three different ones.

     No windowSplashScreenIconBackgroundColor. Setting one moves the platform to
     a smaller icon geometry and would mask the ends off the badge. -->
<resources>
    <style name="LaunchTheme" parent="@android:style/{parent}">
        <item name="android:windowSplashScreenBackground">@color/tmk_splash_bg</item>
        <item name="android:windowSplashScreenAnimatedIcon">@drawable/tmk_splash_icon</item>
        <item name="android:windowBackground">@color/tmk_splash_bg</item>
    </style>
    <style name="NormalTheme" parent="@android:style/{parent}">
        <item name="android:windowBackground">@color/tmk_splash_bg</item>
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

    print("splash_android: painting the launch window")

    write(RES / "values/tmk_splash.xml", COLORS.format(bg=BG))
    copy(ICON12, RES / DENSITY / "tmk_splash_icon.png", expect=(1152, 1152))

    write(RES / "values/styles.xml", STYLES.format(parent=LIGHT))
    write(RES / "values-night/styles.xml", STYLES.format(parent=DARK))
    write(RES / "values-v31/styles.xml", STYLES_V31.format(parent=LIGHT))
    write(RES / "values-night-v31/styles.xml", STYLES_V31.format(parent=DARK))

    # ------------------------------------------------------------- proof
    #
    # A build that ships without this must fail here, on the build machine, and
    # not on a phone in Doha.
    manifest = ANDROID / "app/src/main/AndroidManifest.xml"
    if not manifest.exists():
        die("AndroidManifest.xml is missing")
    src = manifest.read_text(encoding="utf-8")
    if "@style/LaunchTheme" not in src:
        die("the activity does not use @style/LaunchTheme — Flutter's template changed")
    if "@style/NormalTheme" not in src:
        die("the NormalTheme meta-data is gone — Flutter's template changed")

    required = [
        RES / "values/tmk_splash.xml",
        RES / DENSITY / "tmk_splash_icon.png",
        RES / "values/styles.xml",
        RES / "values-night/styles.xml",
        RES / "values-v31/styles.xml",
        RES / "values-night-v31/styles.xml",
    ]
    missing = [p for p in required if not p.exists() or p.stat().st_size == 0]
    if missing:
        die("these should exist and do not: " + ", ".join(str(p) for p in missing))

    for p in (RES / "values/styles.xml", RES / "values-v31/styles.xml"):
        if p.read_text(encoding="utf-8").count("tmk_splash_bg") < 2:
            die(f"{p.name} does not brand BOTH themes — that is the white flash coming back")

    print(f"splash_android: ok — 6 files, window is {BG} from the first instant")


if __name__ == "__main__":
    main()
