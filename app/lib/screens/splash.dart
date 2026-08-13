import 'dart:ui' show ImageFilter;

import 'package:flutter/material.dart';

import '../config.dart';
import '../state.dart';
import '../theme.dart';

/// تصميم اليوم — the concept car the site opens on, now opening the app too.
///
/// Two demands that sound like one: the screen must be **full**, and the car
/// must be **whole**. A single image can only do one — `cover` fills the screen
/// and slices the nose off the car, `contain` keeps the car and leaves bars.
///
/// So the photograph is painted twice. Once blurred and cropped to `cover`,
/// filling every pixel behind everything; once sharp and `contain`ed on top,
/// edge to edge across the full width of the phone, whole. The blur is the
/// car's own colours, so the two read as one picture and there is no bar, no
/// border and no frame — nothing that makes it look like a photograph sitting
/// in a card.
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

    // The words are inset from the edges. The car is not — it runs the full
    // width of the phone, so padding is put on each text widget rather than
    // on the column that holds them all.
    const side = EdgeInsets.symmetric(horizontal: 20);

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
            // pixel. It is never looked at directly; it exists so the screen
            // has no empty edges and no black bars.
            Image.network(
              concept.image,
              fit: BoxFit.cover,
              errorBuilder: (context, _, __) => const SizedBox.shrink(),
            ),
            BackdropFilter(
              filter: ImageFilter.blur(sigmaX: 38, sigmaY: 38),
              child: Container(color: Brand.dBg.withValues(alpha: 0.72)),
            ),

            // Layer 2 — everything you actually read, over the top.
            SafeArea(
              child: LayoutBuilder(
                builder: (context, box) {
                  final short = box.maxHeight < 640;
                  final logo = (box.maxHeight * 0.075).clamp(40.0, 78.0);
                  final hero = (box.maxWidth * 0.062).clamp(19.0, 27.0);

                  return Padding(
                    padding: EdgeInsets.only(
                        top: short ? 10 : 18, bottom: short ? 12 : 20),
                    child: Column(
                      children: [
                        // ------------------------------------------- the mark
                        Image.asset('assets/brand/logo-mark.png', height: logo),
                        SizedBox(height: short ? 4 : 8),
                        Padding(
                          padding: side,
                          child: Text(
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
                        ),
                        if (!short) ...[
                          const SizedBox(height: 2),
                          Padding(
                            padding: side,
                            child: Text(
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
                          ),
                        ],

                        // -------------------------------------------- the car
                        //
                        // Every pixel left between the name and the badge, the
                        // full width of the phone, and not one pixel of the car
                        // cropped. No rounded corners and no inset: those are
                        // what made it look like a picture in a frame.
                        Expanded(
                          child: SizedBox(
                            width: double.infinity,
                            child: Image.network(
                              concept.image,
                              fit: BoxFit.contain,
                              frameBuilder: (context, child, frame, wasSync) =>
                                  AnimatedOpacity(
                                opacity: (wasSync || frame != null) ? 1 : 0,
                                duration: const Duration(milliseconds: 300),
                                child: child,
                              ),
                              loadingBuilder: (context, child, progress) =>
                                  progress == null
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
                        ),

                        // ------------------------------------------ the words
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

                        Padding(
                          padding: side,
                          child: Text(
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
                        ),
                        SizedBox(height: short ? 6 : 8),

                        // One line, whatever it takes.
                        //
                        // This sentence is edited in the control panel, so its
                        // length is not ours to decide. Wrapping it broke the
                        // shape of the screen; ellipsis threw away the end of
                        // Khalid's own words. FittedBox keeps every word and
                        // shrinks the type until it fits the width.
                        Padding(
                          padding: side,
                          child: FittedBox(
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
                        ),
                        SizedBox(height: short ? 10 : 14),

                        // ----------------------------------------- the button
                        Padding(
                          padding: side,
                          child: SizedBox(
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
