import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';

import 'api.dart';
import 'config.dart';
import 'screens/home.dart';
import 'state.dart';
import 'theme.dart';

void main() {
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
      title: 'Thaman Motorak',
      debugShowCheckedModeBanner: false,
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
      builder: (context, child) => Directionality(
        textDirection: rtl ? TextDirection.rtl : TextDirection.ltr,
        child: child ?? const SizedBox.shrink(),
      ),
      home: _root(),
    );
  }

  Widget _root() {
    if (config != null) {
      return HomeScreen(api: api, config: config!, prefs: prefs, draft: draft);
    }
    if (loading) return const _Splash();
    return _FirstRunFailed(lang: prefs.lang, onRetry: _boot, error: error);
  }
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
