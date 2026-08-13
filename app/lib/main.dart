import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';

import 'api.dart';
import 'config.dart';
import 'screens/home.dart';
import 'screens/splash.dart';
import 'state.dart';
import 'theme.dart';

/// STOP FIGHTING THE NATIVE LAUNCH SCREEN — READ THIS BEFORE PUTTING IT BACK
/// ------------------------------------------------------------------------
/// Four builds were spent trying to make Android's own launch screen show the
/// mark, and every one of them was judged on a phone in Doha because there is
/// no way to see it from here. The window theme, the Android 12 system splash,
/// `flutter_native_splash`, `preserve()` and `remove()` — each is a place the
/// picture can silently not appear, and between them they cost two days.
///
/// So the splash is now **drawn by Flutter**, in [BrandSplash] at the bottom of
/// this file: the mark grows from nothing to full width on the brand's
/// near-black, holds, and dissolves into تصميم اليوم. It is ordinary widget
/// code. It cannot be skipped by a build step, it does not depend on a theme
/// attribute, and it behaves identically on every Android version and on iOS.
///
/// The native side still has one job and it is a job it cannot fail: paint
/// `#131013` while the process starts, so there is no white flash before
/// Flutter's first frame. `tool/splash_android.py` does that.
///
/// **`FlutterNativeSplash.preserve()` is deliberately gone.** It held Flutter's
/// first frame back for 900 ms — which is exactly the 900 ms this animation
/// wants to be running in. The package stays in `pubspec.yaml` for the iOS
/// storyboard; nothing in the Dart calls it any more.
void main() {
  WidgetsFlutterBinding.ensureInitialized();
  runApp(const ThamanApp());
}

/// The shell.
///
/// Three things are held here and handed down: the API client, the config the
/// server sent, and the customer's language. Everything else is local to a
/// screen. The app is small enough that a state-management package would cost
/// more than it saves, and one less dependency is one less thing that can hold
/// up a store review.
class ThamanApp extends StatefulWidget {
  const ThamanApp({super.key});

  @override
  State<ThamanApp> createState() => _ThamanAppState();
}

class _ThamanAppState extends State<ThamanApp> {
  final api = Api();
  final prefs = Prefs();
  final draft = Draft();

  AppConfig? config;
  Object? error;
  bool loading = true;

  /// The concept picture shows once per launch, not once per install: it is
  /// a different car every day and people come back to see it.
  bool conceptSeen = false;

  /// False until the brand animation has played and faded out.
  ///
  /// It runs on its own clock, not on the network's. Whether the config comes
  /// from the cache in four milliseconds or from Doha in three seconds, the
  /// customer sees the same opening.
  bool brandSplashDone = false;

  @override
  void initState() {
    super.initState();
    prefs.addListener(_refresh);
    _boot();
  }

  @override
  void dispose() {
    prefs.removeListener(_refresh);
    api.close();
    super.dispose();
  }

  void _refresh() => setState(() {});

  /// Open on whatever we already know, then quietly catch up.
  ///
  /// A customer standing in a car park with one bar should see the app, not a
  /// spinner. The cached config is a complete one — it was a real answer from
  /// the server on some earlier launch.
  Future<void> _boot() async {
    await prefs.load();
    await draft.load();

    final cached = await AppConfig.cached();
    if (cached != null && mounted) {
      setState(() {
        config = cached;
        loading = false;
      });
    }

    try {
      final fresh = await AppConfig.fetch(api);
      if (mounted) {
        setState(() {
          config = fresh;
          error = null;
          loading = false;
        });
      }
    } on Object catch (e) {
      if (mounted) {
        setState(() {
          // Only an error if we have nothing at all to show.
          if (config == null) error = e;
          loading = false;
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final rtl = prefs.isRtl;

    return MaterialApp(
      title: 'Thamanmotorak',
      debugShowCheckedModeBanner: false,
      scrollBehavior: const _SteadyScroll(),
      // The theme depends on the language, not only the brightness: Arabic and
      // English are set in different faces and at different sizes.
      theme: buildTheme(dark: false, arabic: rtl),
      darkTheme: buildTheme(dark: true, arabic: rtl),
      locale: Locale(prefs.lang),
      supportedLocales: const [Locale('ar'), Locale('en')],
      localizationsDelegates: const [
        GlobalMaterialLocalizations.delegate,
        GlobalWidgetsLocalizations.delegate,
        GlobalCupertinoLocalizations.delegate,
      ],
      builder: (context, child) {
        final mq = MediaQuery.of(context);

        // Two things happen to a layout on somebody else's phone: the screen is
        // narrower than the one it was designed on, and the owner has turned
        // their system font up. Together they are what actually breaks a
        // screen — a 5-inch phone at 1.5× font scale has roughly half the room
        // this app was drawn in.
        //
        // The scale is clamped rather than ignored: a customer who enlarged
        // their type did it because they need it, so refusing entirely would be
        // worse than a tight layout. 1.25 is as far as these screens stretch
        // before the four-step header and the photo grid start to fight.
        final scale = mq.textScaler.clamp(
          minScaleFactor: 0.9,
          maxScaleFactor: 1.25,
        );

        return MediaQuery(
          data: mq.copyWith(textScaler: scale),
          child: Directionality(
            textDirection: rtl ? TextDirection.rtl : TextDirection.ltr,
            child: child ?? const SizedBox.shrink(),
          ),
        );
      },
      // The brand animation sits over everything, including تصميم اليوم, and
      // dissolves to reveal whichever of them is ready underneath. Because the
      // app is being built the whole time it is playing, lifting it shows a
      // finished screen rather than the start of one.
      home: Stack(
        children: [
          _root(),
          if (!brandSplashDone)
            BrandSplash(
              onDone: () {
                if (mounted && !brandSplashDone) {
                  setState(() => brandSplashDone = true);
                }
              },
            ),
        ],
      ),
    );
  }

  Widget _root() {
    if (config != null) {
      final home = HomeScreen(api: api, config: config!, prefs: prefs, draft: draft);

      // The concept picture sits over a home screen that is already built, so
      // lifting it reveals a finished app rather than starting to load one.
      // Once per launch — a splash you cannot get past is an advertisement.
      if (!conceptSeen && config!.concept != null) {
        return Stack(
          children: [
            home,
            ConceptSplash(
              config: config!,
              prefs: prefs,
              onDismiss: () {
                if (mounted && !conceptSeen) setState(() => conceptSeen = true);
              },
            ),
          ],
        );
      }
      return home;
    }
    if (loading) return const _Splash();
    return _FirstRunFailed(lang: prefs.lang, onRetry: _boot, error: error);
  }
}

/// Why the forms stopped drifting.
///
/// Farooq's words: *"whole form is feeling floating, it is like moving slightly
/// up and down"*. He was describing Android's overscroll: Material 3 answers a
/// drag that cannot scroll anywhere by **stretching the whole page** and
/// letting it spring back. On a screen full of boxes with a fixed button at the
/// foot, every touch made the form breathe, and on the short steps — where
/// there is nothing to scroll at all — the page moved when it should have been
/// perfectly still.
///
/// It is worth being exact about which half of the scrolling machinery is at
/// fault, because it is not the one you would guess. Android's physics were
/// already clamped — the offset never actually went past the end. What moved
/// was the *indicator*: Material 3 answers an over-drag by stretching the
/// rendered content and springing it back, offset unchanged. So the fix is to
/// take the indicator away and leave the physics alone, which also means iOS
/// keeps the bounce Apple users expect when that build comes.
///
/// A list longer than the screen scrolls exactly as it did.
class _SteadyScroll extends MaterialScrollBehavior {
  const _SteadyScroll();

  @override
  Widget buildOverscrollIndicator(
    BuildContext context,
    Widget child,
    ScrollableDetails details,
  ) =>
      child;
}

class _Splash extends StatelessWidget {
  const _Splash();

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Image.asset('assets/brand/logo-mark.png', width: 120),
            const SizedBox(height: 28),
            const SizedBox(
              width: 22,
              height: 22,
              child: CircularProgressIndicator(strokeWidth: 2.4),
            ),
          ],
        ),
      ),
    );
  }
}

/// The only screen that has to speak without the server's help.
///
/// It happens exactly once — a brand-new install with no connection — and it is
/// the worst possible first impression, so it says what is wrong in both
/// languages rather than picking one and hoping.
class _FirstRunFailed extends StatelessWidget {
  const _FirstRunFailed({required this.lang, required this.onRetry, this.error});

  final String lang;
  final VoidCallback onRetry;
  final Object? error;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SafeArea(
        child: Center(
          child: Padding(
            padding: const EdgeInsets.all(28),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Image.asset('assets/brand/logo-mark.png', width: 88),
                const SizedBox(height: 24),
                const Text(
                  'تعذّر الاتصال بالخادم.\nتأكد من اتصالك بالإنترنت ثم أعد المحاولة.',
                  textAlign: TextAlign.center,
                  textDirection: TextDirection.rtl,
                  style: TextStyle(fontSize: 16, height: 1.6),
                ),
                const SizedBox(height: 14),
                const Text(
                  'Could not reach the server.\nCheck your connection and try again.',
                  textAlign: TextAlign.center,
                  textDirection: TextDirection.ltr,
                  style: TextStyle(fontSize: 15, height: 1.6),
                ),
                const SizedBox(height: 28),
                FilledButton(
                  onPressed: onRetry,
                  child: const Text('إعادة المحاولة · Try again'),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}


/// The splash the customer actually sees — drawn by Flutter, not by Android.
///
/// Farooq, about his client: *"he likes splash splash"*. Four builds went into
/// Android's own launch screen and none of them could be checked without
/// putting an APK on a phone in Doha. This one is widget code: it runs the same
/// on Android 9 and Android 15, it runs on iOS, and there is no build step that
/// can quietly leave it out.
///
/// **The move he asked for — «coming from nothing to big on screen».** The mark
/// starts at a quarter size and invisible, grows to full width over nine tenths
/// of a second on an ease-out so it arrives softly rather than snapping, keeps
/// swelling very slightly while it is held — enough that the screen is alive
/// and not a photograph — and then blooms a little further as the whole thing
/// dissolves into تصميم اليوم. One movement from start to finish, no bounce, no
/// spin. About 1.9 seconds, which is long enough to be a splash and short
/// enough not to be a toll gate.
///
/// It is drawn on `Brand.dBg`, the same near-black the native window is painted
/// by `tool/splash_android.py`, so there is no seam where one hands over to the
/// other and no white flash at any point of the launch.
///
/// **The artwork is `assets/brand/splash-mark.png` and it has no background of
/// its own.** Farooq: *"even when you trying splash you cover his complete logo
/// with black blurry cloud"*. He was right, and the cause was the picture, not
/// the code — the file we were given is the gold TMK badge photographed on a
/// dark textured card, so painting it on the app's own near-black left a faint
/// rectangle of somebody else's grey sitting behind the client's logo. The gold
/// has been cut off that card: the badge is warm and the card is neutral grey,
/// which separates them cleanly, and every backdrop pixel came out fully
/// transparent. What is on screen now is the mark itself, on the brand colour,
/// and nothing else.
class BrandSplash extends StatefulWidget {
  const BrandSplash({super.key, required this.onDone});

  /// Called once, after the fade has finished, so the parent can drop this
  /// widget out of the tree.
  final VoidCallback onDone;

  @override
  State<BrandSplash> createState() => _BrandSplashState();
}

class _BrandSplashState extends State<BrandSplash>
    with SingleTickerProviderStateMixin {
  /// grow 900ms · hold 600ms · dissolve 400ms.
  static const _total = Duration(milliseconds: 1900);

  late final AnimationController _c = AnimationController(
    vsync: this,
    duration: _total,
  )
    ..addStatusListener((status) {
      if (status != AnimationStatus.completed) return;
      // After the frame, not inside it: this callback ends with the parent
      // removing this widget, which disposes the controller that is currently
      // notifying us.
      WidgetsBinding.instance.addPostFrameCallback((_) => widget.onDone());
    })
    ..forward();

  /// 0.22 → 1.00 → 1.05 → 1.14, across the three phases. It ends larger than
  /// it settles, so the last thing that happens is the mark opening out into
  /// the screen rather than simply switching off.
  late final Animation<double> _scale = TweenSequence<double>([
    TweenSequenceItem(
      tween: Tween<double>(begin: 0.22, end: 1.0)
          .chain(CurveTween(curve: Curves.easeOutCubic)),
      weight: 47,
    ),
    TweenSequenceItem(
      tween: Tween<double>(begin: 1.0, end: 1.05)
          .chain(CurveTween(curve: Curves.easeInOut)),
      weight: 32,
    ),
    TweenSequenceItem(
      tween: Tween<double>(begin: 1.05, end: 1.14)
          .chain(CurveTween(curve: Curves.easeIn)),
      weight: 21,
    ),
  ]).animate(_c);

  /// The mark arrives a touch after the movement starts, so it reads as
  /// emerging rather than as being switched on.
  late final Animation<double> _markIn = CurvedAnimation(
    parent: _c,
    curve: const Interval(0.02, 0.34, curve: Curves.easeOut),
  );

  /// The whole screen, dissolving.
  late final Animation<double> _out = CurvedAnimation(
    parent: _c,
    curve: const Interval(0.79, 1, curve: Curves.easeIn),
  );

  @override
  void dispose() {
    _c.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final width = MediaQuery.sizeOf(context).width;

    return AnimatedBuilder(
      animation: _c,
      builder: (context, _) {
        final gone = _out.value;
        if (gone >= 1) return const SizedBox.shrink();

        return Opacity(
          opacity: 1 - gone,
          child: Material(
            // Opaque: nothing behind this may show through while it plays.
            color: Brand.dBg,
            child: Center(
              child: Opacity(
                opacity: _markIn.value,
                child: Transform.scale(
                  scale: _scale.value,
                  child: SizedBox(
                    width: width * 0.72,
                    child: Image.asset(
                      'assets/brand/splash-mark.png',
                      fit: BoxFit.contain,
                      // The mark is a fixed asset at a known size; letting it
                      // filter smoothly matters more than the last microsecond.
                      filterQuality: FilterQuality.medium,
                    ),
                  ),
                ),
              ),
            ),
          ),
        );
      },
    );
  }
}
