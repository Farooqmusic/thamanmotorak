import 'package:flutter/material.dart';

import '../config.dart';
import '../state.dart';
import '../theme.dart';

/// تصميم اليوم — the concept car the site opens on, now opening the app too.
///
/// The picture is chosen by the server, three times a day, the same car for
/// everybody. That matters: a customer who saw a car on the website in the
/// morning and opens the app in the afternoon should see the app he was
/// promised, not a different showroom.
///
/// It sits *over* the home screen rather than before it, so nothing is loading
/// behind the button — lifting the picture reveals a screen that is already
/// there, exactly as the website does it.
class ConceptSplash extends StatefulWidget {
  const ConceptSplash({
    super.key,
    required this.config,
    required this.prefs,
    required this.onDismiss,
  });

  final AppConfig config;
  final Prefs prefs;
  final VoidCallback onDismiss;

  @override
  State<ConceptSplash> createState() => _ConceptSplashState();
}

class _ConceptSplashState extends State<ConceptSplash>
    with SingleTickerProviderStateMixin {
  late final AnimationController _c = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 420),
  );

  /// If the picture cannot be fetched — a new install on a bad connection —
  /// the splash gets out of the way rather than holding a blank screen in
  /// front of a working app.
  bool failed = false;

  @override
  void dispose() {
    _c.dispose();
    super.dispose();
  }

  Future<void> _lift() async {
    await _c.forward();
    widget.onDismiss();
  }

  @override
  Widget build(BuildContext context) {
    final concept = widget.config.concept;
    final lang = widget.prefs.lang;

    if (concept == null || failed) {
      WidgetsBinding.instance.addPostFrameCallback((_) => widget.onDismiss());
      return const SizedBox.shrink();
    }

    final label = concept.button(lang).isNotEmpty
        ? concept.button(lang)
        : (lang == 'ar' ? 'ابدأ التقييم المجاني' : 'Start free valuation');

    return AnimatedBuilder(
      animation: _c,
      builder: (context, child) => Opacity(
        opacity: 1 - _c.value,
        child: Transform.translate(
          offset: Offset(0, -_c.value * 90),
          child: child,
        ),
      ),
      child: Material(
        color: Brand.dBg,
        child: Stack(
          fit: StackFit.expand,
          children: [
            Image.network(
              concept.image,
              fit: BoxFit.cover,
              // The picture is the whole screen, so a half-drawn one is worse
              // than none: hold the dark background until it is fully in.
              frameBuilder: (context, child, frame, wasSync) => AnimatedOpacity(
                opacity: (wasSync || frame != null) ? 1 : 0,
                duration: const Duration(milliseconds: 300),
                child: child,
              ),
              errorBuilder: (context, _, __) {
                WidgetsBinding.instance
                    .addPostFrameCallback((_) => setState(() => failed = true));
                return const SizedBox.shrink();
              },
            ),

            // A photograph is not a background. Without this the gold button
            // and the company name land on whatever happens to be behind them,
            // and one car in seven has a bright sky exactly there.
            const DecoratedBox(
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  begin: Alignment.topCenter,
                  end: Alignment.bottomCenter,
                  colors: [
                    Color(0xCC000000),
                    Color(0x33000000),
                    Color(0x00000000),
                    Color(0x99000000),
                    Color(0xF2000000),
                  ],
                  stops: [0, 0.22, 0.45, 0.78, 1],
                ),
              ),
            ),

            // Everything here is sized from the screen it is actually on. This
            // is the one screen with no scrollbar to fall back on, so a short
            // phone must shrink the picture furniture rather than overflow it.
            LayoutBuilder(builder: (context, box) {
              final short = box.maxHeight < 620;
              final logo = (box.maxHeight * 0.085).clamp(38.0, 72.0);
              final title = (box.maxWidth * 0.058).clamp(17.0, 25.0);
              final hero = (box.maxWidth * 0.068).clamp(19.0, 29.0);

              return SafeArea(
              child: Padding(
                padding: EdgeInsets.fromLTRB(24, short ? 6 : 12, 24, short ? 16 : 26),
                child: Column(
                  children: [
                    Image.asset('assets/brand/logo-mark.png', height: logo),
                    SizedBox(height: short ? 6 : 10),
                    Text(
                      widget.config.t('appName', lang),
                      textAlign: TextAlign.center,
                      maxLines: 1,
                      style: TextStyle(
                        fontFamily: lang == 'ar' ? 'Naskh' : 'Poppins',
                        fontSize: title,
                        fontWeight: FontWeight.w700,
                        color: Colors.white,
                        shadows: const [
                          Shadow(blurRadius: 12, color: Color(0xCC000000)),
                        ],
                      ),
                    ),

                    const Spacer(),

                    Flexible(
                      child: Text(
                        widget.config.t('heroTitle', lang),
                        textAlign: TextAlign.center,
                        maxLines: 3,
                        overflow: TextOverflow.ellipsis,
                        style: TextStyle(
                          fontFamily: lang == 'ar' ? 'Naskh' : 'Poppins',
                          fontSize: hero,
                          height: 1.4,
                          fontWeight: FontWeight.w700,
                          color: Colors.white,
                          shadows: const [
                            Shadow(blurRadius: 16, color: Color(0xE6000000)),
                          ],
                        ),
                      ),
                    ),
                    SizedBox(height: short ? 14 : 22),

                    // One gold button lifts the picture away. Gold on a dark
                    // photograph, with dark ink on it — the only pairing here
                    // that stays readable whatever car is underneath.
                    SizedBox(
                      width: double.infinity,
                      child: FilledButton(
                        onPressed: _lift,
                        style: FilledButton.styleFrom(
                          backgroundColor: Brand.gold,
                          foregroundColor: Brand.goldInk,
                          minimumSize: Size.fromHeight(short ? 50 : 56),
                        ),
                        child: Text(
                          label,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: TextStyle(
                            fontFamily: lang == 'ar' ? 'Naskh' : 'Poppins',
                            fontSize: short ? 15 : 17,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                      ),
                    ),
                    SizedBox(height: short ? 6 : 10),
                    Text(
                      lang == 'ar' ? 'تصميم اليوم' : 'Concept of the day',
                      style: const TextStyle(
                        fontSize: 12,
                        color: Color(0xB3FFFFFF),
                        letterSpacing: 0.6,
                      ),
                    ),
                  ],
                ),
              ),
              );
            }),
          ],
        ),
      ),
    );
  }
}
