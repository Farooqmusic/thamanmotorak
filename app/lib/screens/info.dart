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

    return ListView(
      padding: const EdgeInsets.fromLTRB(16, 16, 16, 28),
      children: [
        if (t('overviewBody').trim().isNotEmpty) ...[
          SectionCard(
            title: t('overviewTitle'),
            child: Text(t('overviewBody'), style: theme.textTheme.bodyMedium),
          ),
          const SizedBox(height: 14),
        ],

        SectionCard(
          title: t('guideTitle'),
          subtitle: t('guideSub'),
          child: OutlinedButton.icon(
            onPressed: () => open(config.page('guide')),
            icon: const Icon(Icons.menu_book_outlined),
            label: Text(t('guideTitle')),
          ),
        ),
        const SizedBox(height: 14),

        SectionCard(
          title: t('privTitle'),
          child: Text(t('privBody'), style: theme.textTheme.bodyMedium),
        ),
        const SizedBox(height: 14),

        // Only what the control panel actually holds, in the order it holds
        // it. A field Khalid clears disappears here too — the same rule the
        // emails and the PDF follow, so a number cleared once is cleared
        // everywhere.
        if (contact.isNotEmpty) ...[
          SectionCard(
            title: t('cTitle'),
            child: Column(
              children: [
                for (final c in contact)
                  ListTile(
                    contentPadding: EdgeInsets.zero,
                    leading: Icon(_iconFor(c.kind)),
                    title: Text(
                      c.label,
                      // Phone numbers, addresses and handles are Latin even in
                      // an Arabic layout; letting them inherit RTL puts the
                      // country code on the wrong end.
                      textDirection: TextDirection.ltr,
                      textAlign: prefs.isRtl ? TextAlign.right : TextAlign.left,
                    ),
                    subtitle: c.kind.isEmpty ? null : Text(c.kind),
                    trailing: const Icon(Icons.open_in_new, size: 17),
                    onTap: c.href.isEmpty ? null : () => open(c.href),
                  ),
              ],
            ),
          ),
          const SizedBox(height: 14),
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
