import 'dart:ui' show ImageFilter;

import 'package:flutter/material.dart';

import '../config.dart';
import '../state.dart';
import '../theme.dart';

/// تصميم اليوم — the concept car the site opens on, now opening the app too.
///
/// The picture owns the screen. It is not given the space left over after the
/// words have taken theirs — that was the mistake in the first two attempts,
/// and it is why the car sat in the middle with dark bands down each side no
/// matter what size the photograph was. The layout was the problem, not the
/// image.
///
/// So: the photograph is painted across the full width of the phone, edge to
/// edge, and everything readable floats on top of it. A blurred, cropped copy
/// of the same photograph sits behind to fill the strips above and below —
/// its own colours, so the screen reads as one picture rather than a picture
/// with margins. Nothing of the car is cropped and nothing is letterboxed.
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
            // Layer 1 — the same picture, blurred and cropped to fill every
            // pixel. It is never looked at directly; it is there so the strips
            // above and below the car are the car's own colours instead of a
            // black bar.
            Image.network(
              concept.image,
              fit: BoxFit.cover,
              errorBuilder: (context, _, __) => const SizedBox.shrink(),
            ),
            BackdropFilter(
              filter: ImageFilter.blur(sigmaX: 42, sigmaY: 42),
              child: Container(color: Brand.dBg.withValues(alpha: 0.55)),
            ),

            // Layer 2 — the car itself, sharp, across the whole width of the
            // phone. `fitWidth` is the point: the picture is as wide as the
            // screen and as tall as that makes it, so no pixel of the car is
            // ever cut off the sides and there is no box around it.
            // Anchored to the TOP, not the middle.
            //
            // The picture is 768×1376 and the phone is 945×2048 — a taller
            // shape. Made as wide as the screen it still comes up about 350px
            // short, and centred that left a visible strip of blur above the
            // logo which read as a gap. Pushed to the top there is no strip up
            // there at all: the photograph starts at the very first pixel,
            // behind the status bar, and what is left over falls to the bottom
            // where the scrim is nearly black and the button sits on top of it.
            Positioned.fill(
              child: Image.network(
                concept.image,
                fit: BoxFit.fitWidth,
                alignment: Alignment.topCenter,
                frameBuilder: (context, child, frame, wasSync) =>
                    AnimatedOpacity(
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
            ),

            // Layer 3 — the scrim.
            //
            // White type on a photograph is unreadable wherever the photograph
            // happens to be pale. This darkens the two bands the words sit in
            // and leaves the middle alone, so the car is not dimmed for the
            // sake of a caption.
            const DecoratedBox(
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  begin: Alignment.topCenter,
                  end: Alignment.bottomCenter,
                  colors: [
                    Color(0xE6000000),
                    Color(0x99000000),
                    Color(0x00000000),
                    Color(0x00000000),
                    Color(0xA6000000),
                    Color(0xF2000000),
                  ],
                  stops: [0.0, 0.14, 0.30, 0.52, 0.74, 1.0],
                ),
              ),
            ),

            // Layer 4 — everything you actually read.
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
                            shadows: const [
                              Shadow(blurRadius: 12, color: Color(0xCC000000)),
                            ],
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
                              color: const Color(0xCCFFFFFF),
                              shadows: const [
                                Shadow(blurRadius: 10, color: Color(0xB3000000)),
                              ],
                            ),
                          ),
                        ],

                        // The car shows through everything between here…
                        const Spacer(),

                        // …and here.
                        Container(
                          padding: const EdgeInsets.symmetric(
                              horizontal: 14, vertical: 6),
                          decoration: BoxDecoration(
                            color: Brand.gold.withValues(alpha: 0.20),
                            borderRadius: BorderRadius.circular(999),
                            border: Border.all(
                                color: Brand.gold.withValues(alpha: 0.55)),
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
                            shadows: const [
                              Shadow(blurRadius: 14, color: Color(0xCC000000)),
                            ],
                          ),
                        ),
                        SizedBox(height: short ? 6 : 8),

                        // One line, whatever it takes. The sentence is edited
                        // in the control panel, so its length is not ours to
                        // decide — the type shrinks rather than wrapping or
                        // throwing away the end of Khalid's own words.
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
                              color: const Color(0xE6FFFFFF),
                              shadows: const [
                                Shadow(blurRadius: 10, color: Color(0xB3000000)),
                              ],
                            ),
                          ),
                        ),
                        SizedBox(height: short ? 10 : 14),

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
