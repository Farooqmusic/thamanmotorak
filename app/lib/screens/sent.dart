import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../config.dart';
import '../state.dart';
import '../theme.dart';
import '../widgets/common.dart';

/// "We have your request, here is your number."
///
/// The six-character code is the only key a customer ever gets — there is no
/// password and no account — so this screen exists to make it very hard to
/// lose: large, copyable, and repeated in the email.
class SentScreen extends StatelessWidget {
  const SentScreen({
    super.key,
    required this.config,
    required this.prefs,
    required this.id,
    required this.expires,
  });

  final AppConfig config;
  final Prefs prefs;
  final String id;
  final String expires;

  @override
  Widget build(BuildContext context) {
    final lang = prefs.lang;
    String t(String k) => config.t(k, lang);
    final theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(
        title: Text(t('sentTitle')),
        automaticallyImplyLeading: false,
      ),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(16, 20, 16, 28),
        children: [
          const Center(child: Icon(Icons.check_circle, size: 68, color: Brand.green)),
          const SizedBox(height: 18),
          Text(t('sentTitle'),
              textAlign: TextAlign.center, style: theme.textTheme.headlineSmall),
          const SizedBox(height: 8),
          Text(t('sentSub'), textAlign: TextAlign.center, style: theme.textTheme.bodyMedium),
          const SizedBox(height: 24),

          Card(
            child: Padding(
              padding: const EdgeInsets.symmetric(vertical: 22, horizontal: 16),
              child: Column(
                children: [
                  Text(t('yourId'), style: theme.textTheme.bodySmall),
                  const SizedBox(height: 10),
                  SelectableText(
                    id,
                    textDirection: TextDirection.ltr,
                    style: const TextStyle(
                      fontSize: 38,
                      fontWeight: FontWeight.w700,
                      letterSpacing: 6,
                    ),
                  ),
                  const SizedBox(height: 12),
                  OutlinedButton.icon(
                    onPressed: () async {
                      await Clipboard.setData(ClipboardData(text: id));
                      if (context.mounted) {
                        ScaffoldMessenger.of(context).showSnackBar(
                          SnackBar(
                            content: Text(lang == 'ar' ? 'تم النسخ' : 'Copied'),
                          ),
                        );
                      }
                    },
                    icon: const Icon(Icons.copy, size: 18),
                    label: Text(lang == 'ar' ? 'نسخ الرقم' : 'Copy number'),
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 14),

          Text(t('sentMail'), textAlign: TextAlign.center, style: theme.textTheme.bodySmall),
          const SizedBox(height: 20),

          // The junk-mail note is not an apology, it is instructions: the
          // domain is young and Hotmail in particular files these messages
          // away. A customer who presses "Not junk" once fixes it for good.
          SectionCard(
            title: t('junkT'),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(t('junkB'), style: theme.textTheme.bodyMedium),
                const SizedBox(height: 8),
                Text(t('junkS'), style: theme.textTheme.bodySmall),
              ],
            ),
          ),
          const SizedBox(height: 24),

          FilledButton(
            onPressed: () => Navigator.of(context).popUntil((r) => r.isFirst),
            child: Text(t('checkNow')),
          ),
          const SizedBox(height: 10),
          OutlinedButton(
            onPressed: () => Navigator.of(context).popUntil((r) => r.isFirst),
            child: Text(t('another')),
          ),
        ],
      ),
    );
  }
}
