import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../config.dart';
import '../state.dart';
import '../theme.dart';
import '../widgets/common.dart';

/// Written in by Codemagic at build time:
///   flutter build apk --dart-define=APP_VERSION=1.0.$BUILD_NUMBER
/// A local `flutter run` has no such flag, so it says "dev" — which is exactly
/// what you want to see if a screenshot arrives from someone's laptop.
const appVersion = String.fromEnvironment('APP_VERSION', defaultValue: 'dev');

/// About, privacy, how it works, and how to reach a human.
///
/// The Terms and the Privacy Policy open on the website rather than being
/// copied in here. Legal wording that lives in two places is legal wording that
/// will one day disagree with itself, and a phone that has not been updated for
/// six months would be showing the old version.
class InfoScreen extends StatelessWidget {
  const InfoScreen({super.key, required this.config, required this.prefs});

  final AppConfig config;
  final Prefs prefs;

  @override
  Widget build(BuildContext context) {
    final lang = prefs.lang;
    String t(String k) => config.t(k, lang);
    final theme = Theme.of(context);
    final contact = config.contact;

    Future<void> open(String url) async {
      if (url.isEmpty) return;
      await launchUrl(Uri.parse(url), mode: LaunchMode.externalApplication);
    }

    // Nothing on this tab has been removed — it is the same four cards with
    // the same words. What changed is how much of the screen they take before
    // the customer has asked for any of it: the two long paragraphs open
    // folded to four lines with a «المزيد» underneath, the guide card is one
    // tappable row instead of a heading, a sentence and a full-width button,
    // and the padding and the gaps are a couple of pixels tighter throughout.
    // Together that is roughly a screen and a half down to under one.
    const cardPad = EdgeInsets.fromLTRB(14, 14, 14, 12);
    const gap = SizedBox(height: 10);

    return ListView(
      padding: const EdgeInsets.fromLTRB(16, 14, 16, 24),
      children: [
        if (t('overviewBody').trim().isNotEmpty) ...[
          SectionCard(
            padding: cardPad,
            title: t('overviewTitle'),
            child: _Folded(text: t('overviewBody'), lines: 4, lang: lang),
          ),
          gap,
        ],

        // One row, the same shape as the contact rows below it. It was a
        // heading, a sentence and a full-width button — three stacked things
        // to say "tap here to read the guide".
        Card(
          clipBehavior: Clip.antiAlias,
          child: ListTile(
            contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 2),
            leading: const Icon(Icons.menu_book_outlined),
            title: Text(t('guideTitle'), style: theme.textTheme.titleSmall),
            subtitle: Text(t('guideSub'), style: theme.textTheme.bodySmall),
            trailing: const Icon(Icons.open_in_new, size: 17),
            onTap: () => open(config.page('guide')),
          ),
        ),
        gap,

        SectionCard(
          padding: cardPad,
          title: t('privTitle'),
          child: _Folded(text: t('privBody'), lines: 4, lang: lang),
        ),
        gap,

        // Only what the control panel actually holds, in the order it holds
        // it. A field Khalid clears disappears here too — the same rule the
        // emails and the PDF follow, so a number cleared once is cleared
        // everywhere.
        if (contact.isNotEmpty) ...[
          SectionCard(
            padding: cardPad,
            title: t('cTitle'),
            child: Column(
              children: [
                for (final c in contact)
                  ListTile(
                    contentPadding: EdgeInsets.zero,
                    dense: true,
                    visualDensity: VisualDensity.compact,
                    minLeadingWidth: 28,
                    leading: Icon(_iconFor(c.kind), size: 21),
                    // One line, on any phone. An address is a link — nobody
                    // reads it, they tap it — so shrinking it to fit beats
                    // wrapping "contact@thamanmotorak.c / om" across two lines.
                    title: Align(
                      alignment: prefs.isRtl
                          ? Alignment.centerRight
                          : Alignment.centerLeft,
                      child: FittedBox(
                        fit: BoxFit.scaleDown,
                        alignment: prefs.isRtl
                            ? Alignment.centerRight
                            : Alignment.centerLeft,
                        child: Text(
                          c.label,
                          // Phone numbers, addresses and handles are Latin even
                          // in an Arabic layout; letting them inherit RTL puts
                          // the country code on the wrong end.
                          textDirection: TextDirection.ltr,
                          maxLines: 1,
                          softWrap: false,
                        ),
                      ),
                    ),
                    subtitle: c.kind.isEmpty ? null : Text(c.kind),
                    trailing: const Icon(Icons.open_in_new, size: 17),
                    onTap: c.href.isEmpty ? null : () => open(c.href),
                  ),
              ],
            ),
          ),
          gap,
        ],

        Row(
          children: [
            Expanded(
              child: TextButton(
                onPressed: () => open(config.page('terms')),
                child: Text(t('termsTitle'), textAlign: TextAlign.center),
              ),
            ),
            Expanded(
              child: TextButton(
                onPressed: () => open(config.page('privacy')),
                child: Text(t('privacyTitle'), textAlign: TextAlign.center),
              ),
            ),
          ],
        ),
        const SizedBox(height: 8),

        // The version, so "it's not working" and "which build?" stop being two
        // separate conversations. Stamped in at build time by Codemagic rather
        // than read through a plugin — one less native dependency, and one less
        // thing for a store reviewer to ask about.
        Center(
          child: SelectableText(
            'v$appVersion',
            style: theme.textTheme.bodySmall?.copyWith(letterSpacing: 0.8),
          ),
        ),
        const SizedBox(height: 4),

        // «تطوير: فاروق» — a credit that leads somewhere. The address itself
        // is never shown, only the line, exactly as the control panel says.
        Center(
          child: Builder(
            builder: (context) {
              final credit = config.devCredit(lang);
              if (credit.label.trim().isEmpty) return const SizedBox.shrink();
              if (credit.url.isEmpty) {
                return Text(credit.label, style: theme.textTheme.bodySmall);
              }
              return TextButton(
                onPressed: () => open(credit.url),
                style: TextButton.styleFrom(
                  textStyle: theme.textTheme.bodySmall,
                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                  minimumSize: Size.zero,
                ),
                child: Text(
                  credit.label,
                  style: theme.textTheme.bodySmall?.copyWith(
                    color: theme.brightness == Brightness.dark
                        ? Brand.dBrandInk
                        : Brand.brand,
                    decoration: TextDecoration.underline,
                    decorationColor: (theme.brightness == Brightness.dark
                            ? Brand.dBrandInk
                            : Brand.brand)
                        .withValues(alpha: 0.5),
                  ),
                ),
              );
            },
          ),
        ),
      ],
    );
  }

  static IconData _iconFor(String kind) {
    switch (kind.toLowerCase()) {
      case 'whatsapp':
        return Icons.chat_bubble_outline;
      case 'email':
        return Icons.mail_outline;
      case 'instagram':
        return Icons.camera_alt_outlined;
      case 'website':
        return Icons.language;
      default:
        return Icons.link;
    }
  }
}

/// A paragraph that opens folded, with a way to unfold it.
///
/// The two long blocks on this tab — what the service is, and what happens to
/// your photographs — are worth reading once and worth almost nothing on the
/// ninety-ninth visit, and between them they filled the screen before there
/// was anything on it to tap. Four lines each now, and «المزيد» for the rest.
///
/// The toggle only appears when the text really is longer than four lines. A
/// "more" that opens nothing is worse than no "more" at all, so this measures
/// the actual words at the actual width, in whatever type size the owner of
/// the phone has chosen, instead of guessing from the number of characters.
class _Folded extends StatefulWidget {
  const _Folded({required this.text, required this.lines, required this.lang});

  final String text;
  final int lines;
  final String lang;

  @override
  State<_Folded> createState() => _FoldedState();
}

class _FoldedState extends State<_Folded> {
  bool open = false;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final style = theme.textTheme.bodyMedium;
    final ar = widget.lang == 'ar';

    return LayoutBuilder(
      builder: (context, box) {
        final painter = TextPainter(
          text: TextSpan(text: widget.text, style: style),
          maxLines: widget.lines,
          textDirection: Directionality.of(context),
          textScaler: MediaQuery.textScalerOf(context),
        )..layout(maxWidth: box.maxWidth);
        final longer = painter.didExceedMaxLines;
        painter.dispose();

        return Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              widget.text,
              style: style,
              maxLines: open || !longer ? null : widget.lines,
              overflow: open || !longer
                  ? TextOverflow.clip
                  : TextOverflow.ellipsis,
            ),
            if (longer)
              Align(
                alignment: AlignmentDirectional.centerStart,
                child: TextButton(
                  onPressed: () => setState(() => open = !open),
                  style: TextButton.styleFrom(
                    padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                    minimumSize: Size.zero,
                    tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                    textStyle: theme.textTheme.bodySmall,
                  ),
                  child: Text(
                    open
                        ? (ar ? 'أقل' : 'Less')
                        : (ar ? 'المزيد' : 'More'),
                  ),
                ),
              ),
          ],
        );
      },
    );
  }
}
