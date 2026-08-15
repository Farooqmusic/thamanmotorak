#!/usr/bin/env python3
"""Apply our decisions to the freshly generated android/ folder.

Every change is listed here rather than committed as a modified copy of a
generated file, so upgrading Flutter never silently reverts one of them and
never produces a merge conflict in a file nobody wrote.

Run by tool/bootstrap.sh. Idempotent.
"""
from __future__ import annotations

import re
import shutil
import sys
from pathlib import Path

APP = Path(__file__).resolve().parent.parent
ANDROID = APP / "android"

# The name under the icon, and the name the store shows.
# One word in both languages on purpose: it is the brand, it is the domain,
# and a launcher that says one thing in Arabic and another in English makes
# the same app look like two.
LABEL_EN = "Thamanmotorak"
LABEL_AR = "Thamanmotorak"

# The API level the app compiles against and declares it was tested on.
#
# Google Play refuses an update whose targetSdk is more than one major release
# behind Android: from 31 August 2026 that means API 36 (Android 16). Flutter's
# generated build.gradle.kts writes `flutter.targetSdkVersion`, whose value
# moves with whichever Flutter version CI happens to install — so the single
# number that decides whether Play accepts the upload was not written down
# anywhere in this repository. It is now, and step 5 fails the build if
# Flutter's template ever changes shape underneath it.
COMPILE_SDK = 36
TARGET_SDK = 36


def die(msg: str) -> None:
    print(f"patch_android: {msg}", file=sys.stderr)
    raise SystemExit(1)


def read(p: Path) -> str:
    return p.read_text(encoding="utf-8")


def write(p: Path, s: str) -> None:
    p.parent.mkdir(parents=True, exist_ok=True)
    p.write_text(s, encoding="utf-8")


def sub_in_file(p: Path, pattern: str, repl: str, *, required: bool = True) -> None:
    """Replace and shout if the pattern is gone.

    A silent no-op here is the worst outcome: the build succeeds and ships with
    the wrong package id or no internet permission. Better to fail the build
    and be told which assumption Flutter changed.
    """
    if not p.exists():
        if required:
            die(f"expected file missing: {p.relative_to(APP)}")
        return
    s = read(p)
    new, n = re.subn(pattern, repl, s)
    if n == 0:
        if required:
            die(f"pattern not found in {p.relative_to(APP)}: {pattern}")
        return
    write(p, new)


def main() -> None:
    pkg = sys.argv[1] if len(sys.argv) > 1 else "com.thamanmotorak.app"
    generated = "com.thamanmotorak.thaman_app"

    if not ANDROID.exists():
        die("android/ does not exist — run tool/bootstrap.sh first")

    # ---------------------------------------------------------------- 1. id
    #
    # The application id is permanent once the app is on Play: it cannot be
    # changed afterwards without publishing a different app and losing every
    # install and review. So it is set deliberately here, not left as whatever
    # `flutter create` derived from the project name.
    for path in ANDROID.rglob("*"):
        if path.is_file() and path.suffix in {".kts", ".gradle", ".xml", ".kt", ".java"}:
            s = read(path)
            if generated in s:
                write(path, s.replace(generated, pkg))

    # MainActivity has to live in a directory matching its package.
    kotlin_root = ANDROID / "app/src/main/kotlin"
    old_dir = kotlin_root / Path(*generated.split("."))
    new_dir = kotlin_root / Path(*pkg.split("."))
    if old_dir.exists() and old_dir != new_dir:
        new_dir.mkdir(parents=True, exist_ok=True)
        for f in old_dir.iterdir():
            shutil.move(str(f), str(new_dir / f.name))
        # tidy the now-empty package directories
        d = old_dir
        while d != kotlin_root and d.exists() and not any(d.iterdir()):
            d.rmdir()
            d = d.parent

    # ------------------------------------------------------------- 2. names
    #
    # Two locales, so the launcher and the store both show the Arabic name to
    # an Arabic phone and the English one otherwise.
    res = ANDROID / "app/src/main/res"
    write(
        res / "values/strings.xml",
        '<?xml version="1.0" encoding="utf-8"?>\n'
        "<resources>\n"
        f"    <string name=\"app_name\">{LABEL_EN}</string>\n"
        "</resources>\n",
    )
    write(
        res / "values-ar/strings.xml",
        '<?xml version="1.0" encoding="utf-8"?>\n'
        "<resources>\n"
        f"    <string name=\"app_name\">{LABEL_AR}</string>\n"
        "</resources>\n",
    )

    manifest = ANDROID / "app/src/main/AndroidManifest.xml"
    sub_in_file(manifest, r'android:label="[^"]*"', 'android:label="@string/app_name"')

    # ------------------------------------------------------- 3. permissions
    #
    # `flutter create` puts INTERNET only in the debug and profile manifests.
    # Leave it at that and the release build installs fine, opens fine, and
    # then cannot reach the server at all — a failure that never shows up until
    # the first real APK is on a real phone.
    src = read(manifest)
    if "android.permission.INTERNET" not in src:
        src = src.replace(
            "<manifest",
            "<manifest",
            1,
        )
        insert = (
            "    <!-- The app is a client for thamanmotorak.com; without this the\n"
            "         release build cannot reach it. Debug builds get it for free,\n"
            "         which is exactly why this is easy to miss. -->\n"
            '    <uses-permission android:name="android.permission.INTERNET"/>\n'
            "\n"
            "    <!-- Photographs are taken in the camera app, so no CAMERA permission\n"
            "         is required — but a device with no camera should still be able to\n"
            "         install and pick pictures from the gallery. -->\n"
            '    <uses-feature android:name="android.hardware.camera" android:required="false"/>\n'
            '    <uses-feature android:name="android.hardware.camera.autofocus" android:required="false"/>\n'
        )
        m = re.search(r"(<manifest[^>]*>\s*\n)", src)
        if not m:
            die("could not find the <manifest> element")
        src = src[: m.end()] + insert + src[m.end():]
        write(manifest, src)

    # ---------------------------------------------------------- 4. signing
    #
    # A release APK must be signed with a key we keep. The keystore never goes
    # near the repository — CI writes it from a secret before the build, and if
    # it is not there we fall back to debug signing so that a pull request from
    # anyone still compiles.
    gradle = ANDROID / "app/build.gradle.kts"
    if gradle.exists():
        s = read(gradle)
        if "keystore.properties" not in s:
            head = (
                "import java.util.Properties\n"
                "import java.io.FileInputStream\n\n"
                "// Written by CI from the ANDROID_KEYSTORE_* secrets. Absent on a\n"
                "// developer machine and in pull requests, where debug signing is fine.\n"
                "val keystoreProperties = Properties()\n"
                "val keystorePropertiesFile = rootProject.file(\"key.properties\")\n"
                "if (keystorePropertiesFile.exists()) {\n"
                "    keystoreProperties.load(FileInputStream(keystorePropertiesFile))\n"
                "}\n\n"
            )
            s = head + s

            s = s.replace(
                "    buildTypes {",
                "    signingConfigs {\n"
                "        create(\"release\") {\n"
                "            if (keystorePropertiesFile.exists()) {\n"
                "                keyAlias = keystoreProperties[\"keyAlias\"] as String\n"
                "                keyPassword = keystoreProperties[\"keyPassword\"] as String\n"
                "                // rootProject.file, not file(): this block is evaluated\n"
                "                // inside android/app/, and a bare file() would look for the\n"
                "                // keystore one directory below where CI actually writes it.\n"
                "                storeFile = rootProject.file(keystoreProperties[\"storeFile\"] as String)\n"
                "                storePassword = keystoreProperties[\"storePassword\"] as String\n"
                "            }\n"
                "        }\n"
                "    }\n\n"
                "    buildTypes {",
                1,
            )
            s = re.sub(
                r'signingConfig = signingConfigs\.getByName\("debug"\)',
                'signingConfig = if (keystorePropertiesFile.exists()) '
                'signingConfigs.getByName("release") else signingConfigs.getByName("debug")',
                s,
            )
            write(gradle, s)
    else:
        die("android/app/build.gradle.kts not found — Flutter's template changed")

    # ------------------------------------------------ 5. target API level
    #
    # Pinned rather than inherited — see COMPILE_SDK / TARGET_SDK above. Play
    # rejects the upload outright if this is wrong, and the rejection arrives
    # only after the whole build has already run.
    if gradle.exists():
        s = read(gradle)
        s = re.sub(r"compileSdk\s*=\s*flutter\.compileSdkVersion",
                   f"compileSdk = {COMPILE_SDK}", s)
        s = re.sub(r"targetSdk\s*=\s*flutter\.targetSdkVersion",
                   f"targetSdk = {TARGET_SDK}", s)
        write(gradle, s)

        # Proof, not assumption: read it back off disk.
        s = read(gradle)
        for want in (f"compileSdk = {COMPILE_SDK}", f"targetSdk = {TARGET_SDK}"):
            if want not in s:
                die(f"could not pin '{want}' in android/app/build.gradle.kts — "
                    "Flutter's template changed, look at it before building")
        print(f"  compileSdk / targetSdk pinned to {COMPILE_SDK} / {TARGET_SDK}")

    print(f"patch_android: ok — {pkg}")


if __name__ == "__main__":
    main()
