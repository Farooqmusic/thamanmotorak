import 'package:flutter/material.dart';

import '../config.dart';
import '../state.dart';
import '../theme.dart';

/// تصميم اليوم — the concept car the site opens on, now opening the app too.
///
/// The picture fills the screen. Edge to edge, behind the status bar, no
/// letterbox and no frame — `BoxFit.cover` over the whole surface.
///
/// This is only possible because the pool in `assets/concepts-app/` is shot
/// portrait, 1080×1935 and 768×1376. The earlier two-layer trick — a blurred
/// `cover` behind a sharp `contain` — existed to stop a *wide* studio
/// photograph being cropped to a wheel arch on a tall phone. With a portrait
/// source there is nothing left to protect against, and the extra layer only
/// stood between the customer and the car.
///
/// Everything readable sits on top, over a scrim that darkens the top and the
/// bottom and leaves the middle of the car alone.
///
/// The gold button is the last thing on the screen, where a thumb already rests.
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

    String t(String k) => widget.config.t(k, lang);
    final font = lang == 'ar' ? 'Naskh' : 'Poppins';

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
            // ------------------------------------------------------- the car
            //
            // Every pixel of the screen, including behind the status bar and
            // the navigation bar. Nothing is cropped that matters: the source
            // is already the shape of a phone.
            Image.network(
              concept.image,
              fit: BoxFit.cover,
              frameBuilder: (context, child, frame, wasSync) => AnimatedOpacity(
                opacity: (wasSync || frame != null) ? 1 : 0,
                duration: const Duration(milliseconds: 300),
                child: child,
              ),
              loadingBuilder: (context, child, progress) => progress == null
                  ? child
                  : const Center(
                      child: SizedBox(
                        width: 26,
                        height: 26,
                        child: CircularProgressIndicator(
                          strokeWidth: 2.2,
                          color: Brand.gold,
                        ),
                      ),
                    ),
              errorBuilder: (context, _, __) {
                WidgetsBinding.instance.addPostFrameCallback(
                  (_) => setState(() => failed = true),
                );
                return const SizedBox.shrink();
              },
            ),

            // ------------------------------------------------------ the scrim
            //
            // White text on a photograph is unreadable wherever the photograph
            // happens to be pale. This darkens only the two bands the words
            // actually sit in and stays out of the middle, so the car is not
            // dimmed for the sake of a caption.
            const DecoratedBox(
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  begin: Alignment.topCenter,
                  end: Alignment.bottomCenter,
                  colors: [
                    Color(0xCC000000),
                    Color(0x40000000),
                    Color(0x00000000),
                    Color(0x8C000000),
                    Color(0xE6000000),
                  ],
                  stops: [0.0, 0.16, 0.40, 0.74, 1.0],
                ),
              ),
            ),

            // ------------------------------------------------------ the words
            SafeArea(
              child: LayoutBuilder(
                builder: (context, box) {
                  final short = box.maxHeight < 640;
                  final logo = (box.maxHeight * 0.075).clamp(40.0, 78.0);
                  final hero = (box.maxWidth * 0.062).clamp(19.0, 27.0);

                  return Padding(
                    padding: EdgeInsets.fromLTRB(
                        20, short ? 10 : 18, 20, short ? 12 : 20),
                    child: Column(
                      children: [
                        // ------------------------------------------- the mark
                        Image.asset('assets/brand/logo-mark.png', height: logo),
                        SizedBox(height: short ? 4 : 8),
                        Text(
                          t('appName'),
                          textAlign: TextAlign.center,
                          maxLines: 1,
                          style: TextStyle(
                            fontFamily: font,
                            fontSize: (box.maxWidth * 0.055).clamp(17.0, 24.0),
                            fontWeight: FontWeight.w700,
                            color: Colors.white,
                          ),
                        ),
                        if (!short) ...[
                          const SizedBox(height: 2),
                          Text(
                            t('tagline'),
                            textAlign: TextAlign.center,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: TextStyle(
                              fontFamily: font,
                              fontSize: 12.5,
                              color: const Color(0xB3FFFFFF),
                            ),
                          ),
                        ],

                        // the car shows through everything between here…
                        const Spacer(),

                        // …and here.
                        Container(
                          padding: const EdgeInsets.symmetric(
                              horizontal: 14, vertical: 6),
                          decoration: BoxDecoration(
                            color: Brand.gold.withValues(alpha: 0.17),
                            borderRadius: BorderRadius.circular(999),
                            border: Border.all(
                                color: Brand.gold.withValues(alpha: 0.5)),
                          ),
                          child: Text(
                            t('freeBadge'),
                            style: TextStyle(
                              fontFamily: font,
                              fontSize: 13,
                              fontWeight: FontWeight.w700,
                              color: Brand.dGold,
                            ),
                          ),
                        ),
                        SizedBox(height: short ? 8 : 12),

                        Text(
                          t('heroTitle'),
                          textAlign: TextAlign.center,
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          style: TextStyle(
                            fontFamily: font,
                            fontSize: hero,
                            height: 1.35,
                            fontWeight: FontWeight.w700,
                            color: Colors.white,
                          ),
                        ),
                        SizedBox(height: short ? 6 : 8),

                        // One line, whatever it takes.
                        //
                        // This sentence is edited in the control panel, so its
                        // length is not ours to decide. Wrapping it broke the
                        // shape of the screen; ellipsis threw away the end of
                        // Khalid's own words. FittedBox keeps every word and
                        // shrinks the type until it fits the width — the same
                        // answer the contact rows use on the Info screen.
                        FittedBox(
                          fit: BoxFit.scaleDown,
                          child: Text(
                            t('heroSub'),
                            textAlign: TextAlign.center,
                            maxLines: 1,
                            softWrap: false,
                            style: TextStyle(
                              fontFamily: font,
                              fontSize: 13.5,
                              height: 1.5,
                              color: const Color(0xCCFFFFFF),
                            ),
                          ),
                        ),
                        SizedBox(height: short ? 10 : 16),

                        // ----------------------------------------- the button
                        SizedBox(
                          width: double.infinity,
                          child: FilledButton(
                            onPressed: _lift,
                            style: FilledButton.styleFrom(
                              backgroundColor: Brand.gold,
                              foregroundColor: Brand.goldInk,
                              minimumSize: Size.fromHeight(short ? 52 : 58),
                            ),
                            child: Text(
                              label,
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: TextStyle(
                                fontFamily: font,
                                fontSize: short ? 15 : 17,
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                          ),
                        ),
                      ],
                    ),
                  );
                },
              ),
            ),
          ],
        ),
      ),
    );
  }
}
