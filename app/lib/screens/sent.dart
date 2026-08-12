import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../config.dart';
import '../state.dart';
import '../theme.dart';

/// "We have your request, here is your number."
///
/// The six-character code is the only key a customer ever gets — there is no
/// password and no account — so this screen exists to make it very hard to
/// lose: large, copyable, and repeated in the email.
///
/// **One screen, no scrolling.** The first version pushed the two buttons below
/// the fold behind a paragraph about junk mail, so the last thing a customer
/// saw after finishing was a warning, and the thing they needed to press was
/// off-screen. The junk-mail note is gone from here — it is still in the
/// confirmation email, which is where somebody who has not received the email
/// is not reading anyway.
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
      body: SafeArea(
        top: false,
        child: LayoutBuilder(
          builder: (context, box) {
            // Everything is sized from the room actually available, so the two
            // buttons stay on screen on a small phone instead of being pushed
            // under the fold.
            final short = box.maxHeight < 620;
            final tick = (box.maxHeight * 0.09).clamp(46.0, 74.0);
            final code = (box.maxWidth * 0.105).clamp(30.0, 44.0);
            final gap = short ? 10.0 : 18.0;

            return Padding(
              padding: EdgeInsets.fromLTRB(16, gap, 16, gap),
              child: Column(
                children: [
                  Icon(Icons.check_circle, size: tick, color: Brand.green),
                  SizedBox(height: gap * 0.7),

                  Text(
                    t('sentTitle'),
                    textAlign: TextAlign.center,
                    maxLines: 1,
                    style: theme.textTheme.headlineSmall,
                  ),
                  SizedBox(height: gap * 0.35),
                  Text(
                    t('sentSub'),
                    textAlign: TextAlign.center,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: theme.textTheme.bodySmall,
                  ),

                  const Spacer(),

                  Card(
                    child: Padding(
                      padding: EdgeInsets.symmetric(vertical: short ? 16 : 24, horizontal: 16),
                      child: Column(
                        children: [
                          Text(t('yourId'), style: theme.textTheme.bodySmall),
                          SizedBox(height: short ? 6 : 10),
                          FittedBox(
                            child: SelectableText(
                              id,
                              textDirection: TextDirection.ltr,
                              style: TextStyle(
                                fontFamily: 'Poppins',
                                fontSize: code,
                                fontWeight: FontWeight.w700,
                                letterSpacing: 6,
                              ),
                            ),
                          ),
                          SizedBox(height: short ? 8 : 14),
                          OutlinedButton.icon(
                            onPressed: () async {
                              await Clipboard.setData(ClipboardData(text: id));
                              if (context.mounted) {
                                ScaffoldMessenger.of(context).showSnackBar(
                                  SnackBar(
                                    content: Text(
                                      lang == 'ar' ? 'تم النسخ' : 'Copied',
                                    ),
                                  ),
                                );
                              }
                            },
                            style: OutlinedButton.styleFrom(
                              minimumSize: Size.fromHeight(short ? 44 : 50),
                            ),
                            icon: const Icon(Icons.copy, size: 18),
                            label: Text(lang == 'ar' ? 'نسخ الرقم' : 'Copy number'),
                          ),
                        ],
                      ),
                    ),
                  ),
                  SizedBox(height: gap * 0.6),

                  Text(
                    t('sentMail'),
                    textAlign: TextAlign.center,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: theme.textTheme.bodySmall,
                  ),

                  const Spacer(),

                  FilledButton(
                    onPressed: () => Navigator.of(context).popUntil((r) => r.isFirst),
                    style: FilledButton.styleFrom(
                      minimumSize: Size.fromHeight(short ? 48 : 54),
                    ),
                    child: Text(t('checkNow')),
                  ),
                  SizedBox(height: short ? 8 : 10),
                  OutlinedButton(
                    onPressed: () => Navigator.of(context).popUntil((r) => r.isFirst),
                    style: OutlinedButton.styleFrom(
                      minimumSize: Size.fromHeight(short ? 48 : 54),
                    ),
                    child: Text(t('another')),
                  ),
                ],
              ),
            );
          },
        ),
      ),
    );
  }
}
