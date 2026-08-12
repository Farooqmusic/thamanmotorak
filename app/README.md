# ثـمـــن مــوتــرك — the app

The Android and iOS app for [thamanmotorak.com](https://www.thamanmotorak.com).
Flutter, one codebase, both stores.

It is not a wrapper around the website. It is a native client for the *same
backend*: `api.php` was already a JSON API — `?do=submit`, `?do=status`,
`?do=support` — so a valuation sent from a phone lands in `requests.json`
looking exactly like one sent from a laptop, reaches Khalid in the same email
and produces the same PDF. There is no second server to keep in step.

## The one idea worth knowing

**The app ships with no content of its own.** Not one car model, not one Arabic
label, not one polygon of the condition diagram. All of it arrives from
`api.php?do=config` on launch and is cached on the phone.

That means Khalid edits a word in the control panel and every phone shows it
the next morning. A car model added to `assets/cars.js` is offered by the app
without a release. A panel reshaped in `carmap.php` reshapes in the app. One
source of truth, now with three renderers: the website's SVG, the PDF's GD, and
`lib/widgets/car_map.dart`.

The only strings compiled in are the website's own UI table in
`lib/strings.dart` — buttons, validation messages, step titles — and even those
lose to anything the control panel sets.

## Running it

```bash
cd app
./tool/bootstrap.sh     # generates android/ and ios/ — needed once after cloning
flutter pub get
flutter run
```

`android/` and `ios/` are **not** in git. They are Gradle and Xcode scaffolding
that Flutter regenerates for every SDK release, and a committed snapshot of them
is how a project ends up failing six months later on a version mismatch nobody
wrote. `tool/bootstrap.sh` generates them from whichever Flutter you have and
then applies our decisions — package id, the app name in both languages, the
INTERNET permission, the signing config — through `tool/patch_android.py`, which
fails loudly if Flutter's template has moved under it.

## Layout

| | |
|---|---|
| `lib/api.dart` | every call to the server, and upload progress |
| `lib/config.dart` | the `?do=config` payload, read defensively |
| `lib/state.dart` | the draft being filled in, saved after every keystroke |
| `lib/strings.dart` | generated from `assets/app.js` — do not hand-edit |
| `lib/widgets/car_map.dart` | the 15-panel diagram, drawn from the server's polygons |
| `lib/screens/wizard.dart` | the four steps |
| `tool/` | platform-folder generation and patching |

## Things that were deliberate

**The draft is saved constantly and cleared only after the server confirms.**
Filling this form means walking round a car in the sun taking eight
photographs. A phone call in the middle of that must not cost the work, and a
draft cleared one moment too early costs it all.

**Photographs are resized as they are picked**, to 2000 px at quality 82. A
modern phone produces 6–8 MB a shot; eight of those is 60 MB over a mobile
connection, and the server refuses anything above 12 MB anyway.

**Upload progress is a real percentage.** Without it, a customer on a weak
connection concludes the app has frozen and kills it — losing a submission that
was 80 % sent.

**Terms and Privacy open on the website** rather than being copied in. Legal
wording that lives in two places will one day disagree with itself, and a phone
that has not been updated for six months would be showing the old version.

**No accounts, no passwords.** Status is by the six-character code, exactly as
on the website. The app's one addition is memory: codes sent from this phone are
offered as chips so nobody has to find the email again.

## Builds

Every push to `app/` builds a signed APK in GitHub Actions and attaches it to
the run. Download it from *Artifacts* at the bottom of the run page and install
it directly — no store involved.

Repository secrets the release build needs:

| Secret | |
|---|---|
| `ANDROID_KEYSTORE_BASE64` | the upload keystore, base64 encoded |
| `ANDROID_KEYSTORE_PASSWORD` | |
| `ANDROID_KEY_PASSWORD` | |
| `ANDROID_KEY_ALIAS` | |

Without them the build still runs and still produces an APK — debug-signed,
fine for testing, not accepted by Play.

> **The upload key cannot be replaced.** Lose it and this app can never be
> updated on Play again; a new key means a new listing and every install and
> review is gone. Keep the original `.jks` somewhere offline, not only in
> GitHub.
