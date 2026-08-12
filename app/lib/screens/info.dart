import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../config.dart';
import '../state.dart';
import '../widgets/common.dart';

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

        // Only what the control panel actually holds. A blank field in the
        // panel means the row disappears here too — the same rule the emails
        // and the PDF follow, so a number cleared once is cleared everywhere.
        SectionCard(
          title: t('cTitle'),
          child: Column(
            children: [
              if ((contact['phone'] ?? '').isNotEmpty)
                ListTile(
                  contentPadding: EdgeInsets.zero,
                  leading: const Icon(Icons.phone_outlined),
                  title: Text(contact['phone']!, textDirection: TextDirection.ltr),
                  onTap: () => open('tel:${contact['phone']}'),
                ),
              if ((contact['email'] ?? '').isNotEmpty)
                ListTile(
                  contentPadding: EdgeInsets.zero,
                  leading: const Icon(Icons.mail_outline),
                  title: Text(contact['email']!, textDirection: TextDirection.ltr),
                  onTap: () => open('mailto:${contact['email']}'),
                ),
              if ((contact['instagram'] ?? '').isNotEmpty)
                ListTile(
                  contentPadding: EdgeInsets.zero,
                  leading: const Icon(Icons.camera_alt_outlined),
                  title: Text(contact['instagram']!, textDirection: TextDirection.ltr),
                  onTap: () => open(contact['instagram']!),
                ),
              if ((contact['website'] ?? '').isNotEmpty)
                ListTile(
                  contentPadding: EdgeInsets.zero,
                  leading: const Icon(Icons.language),
                  title: Text(contact['website']!, textDirection: TextDirection.ltr),
                  onTap: () => open(contact['website']!),
                ),
            ],
          ),
        ),
        const SizedBox(height: 14),

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
        Center(child: Text(t('devCredit'), style: theme.textTheme.bodySmall)),
      ],
    );
  }
}
