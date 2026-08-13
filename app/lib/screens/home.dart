import 'package:flutter/material.dart';

import '../api.dart';
import '../config.dart';
import '../state.dart';
import '../theme.dart';
import '../widgets/common.dart';
import 'info.dart';
import 'status.dart';
import 'support.dart';
import 'wizard.dart';

/// The shell: four tabs, the same four the website's bottom bar has —
/// تقييم · حالة الطلب · معلومات · الدعم.
class HomeScreen extends StatefulWidget {
  const HomeScreen({
    super.key,
    required this.api,
    required this.config,
    required this.prefs,
    required this.draft,
  });

  final Api api;
  final AppConfig config;
  final Prefs prefs;
  final Draft draft;

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  int tab = 0;

  String get lang => widget.prefs.lang;
  String t(String k) => widget.config.t(k, lang);

  @override
  Widget build(BuildContext context) {
    final pages = [
      _EvaluateTab(
        config: widget.config,
        prefs: widget.prefs,
        draft: widget.draft,
        api: widget.api,
      ),
      StatusScreen(api: widget.api, config: widget.config, prefs: widget.prefs),
      InfoScreen(config: widget.config, prefs: widget.prefs),
      SupportScreen(api: widget.api, config: widget.config, prefs: widget.prefs),
    ];

    return Scaffold(
      appBar: AppBar(title: Text(t('appName'))),
      body: IndexedStack(index: tab, children: pages),
      bottomNavigationBar: NavigationBar(
        selectedIndex: tab,
        // The globe is the fifth destination but it is not a page: tapping it
        // switches language and leaves you exactly where you were. Putting it
        // in the bar rather than the corner means one thumb reaches everything,
        // and in Arabic it lands on the far left, where the row ends.
        onDestinationSelected: (i) {
          if (i == 4) {
            widget.prefs.setLang(lang == 'ar' ? 'en' : 'ar');
            return;
          }
          setState(() => tab = i);
        },
        destinations: [
          NavigationDestination(
            icon: const Icon(Icons.directions_car_outlined),
            selectedIcon: const Icon(Icons.directions_car),
            label: t('navEval'),
          ),
          NavigationDestination(
            icon: const Icon(Icons.receipt_long_outlined),
            selectedIcon: const Icon(Icons.receipt_long),
            label: t('navStatus'),
          ),
          NavigationDestination(
            icon: const Icon(Icons.info_outline),
            selectedIcon: const Icon(Icons.info),
            label: t('navInfo'),
          ),
          NavigationDestination(
            icon: const Icon(Icons.support_agent_outlined),
            selectedIcon: const Icon(Icons.support_agent),
            label: t('navSupport'),
          ),
          NavigationDestination(
            icon: const Icon(Icons.language),
            selectedIcon: const Icon(Icons.language),
            // The label names the language you would switch TO — the only
            // version people read correctly.
            label: lang == 'ar' ? 'English' : 'العربية',
          ),
        ],
      ),
    );
  }
}

/// The first thing anyone sees: what this is, that it is free, and one button.
///
/// The button does not scroll. It is pinned to the foot of the screen, above
/// the tab bar, where a thumb already is — reading the four steps should never
/// be what stands between someone and starting. Everything above it scrolls
/// under it.
///
/// The privacy card that used to sit at the bottom is gone. The same words are
/// on the Info tab, and saying them twice in one app made the first screen
/// longer without telling anyone anything new.
class _EvaluateTab extends StatelessWidget {
  const _EvaluateTab({
    required this.config,
    required this.prefs,
    required this.draft,
    required this.api,
  });

  final AppConfig config;
  final Prefs prefs;
  final Draft draft;
  final Api api;

  @override
  Widget build(BuildContext context) {
    final lang = prefs.lang;
    String t(String k) => config.t(k, lang);
    final theme = Theme.of(context);

    return Column(
      children: [
        // ------------------------------------------------ everything that reads
        Expanded(
          child: ListView(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 8),
            children: [
              Center(child: Image.asset('assets/brand/logo-mark.png', width: 96)),
              const SizedBox(height: 18),

              Center(
                child: Builder(
                  builder: (context) {
                    final dark = theme.brightness == Brightness.dark;
                    // Gold is a fill, not an ink. On the pale tint it sits on, the
                    // gold itself is unreadable in light mode; the dark olive is the
                    // same colour family and can actually be read.
                    final ink = dark ? Brand.dGold : Brand.goldInk;
                    return Container(
                      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                      decoration: BoxDecoration(
                        color: (dark ? Brand.dGold : Brand.gold).withValues(alpha: 0.16),
                        borderRadius: BorderRadius.circular(999),
                        border: Border.all(color: ink.withValues(alpha: 0.45)),
                      ),
                      child: Text(
                        t('freeBadge'),
                        style: theme.textTheme.labelLarge?.copyWith(color: ink),
                      ),
                    );
                  },
                ),
              ),
              const SizedBox(height: 16),

              Text(
                t('heroTitle'),
                textAlign: TextAlign.center,
                style: theme.textTheme.headlineSmall?.copyWith(fontSize: 26),
              ),
              const SizedBox(height: 10),
              Text(t('heroSub'), textAlign: TextAlign.center, style: theme.textTheme.bodyMedium),
              const SizedBox(height: 24),

              SectionCard(
                title: t('infoTitle'),
                child: Column(
                  children: [
                    for (var i = 1; i <= 4; i++)
                      Padding(
                        padding: const EdgeInsets.only(bottom: 12),
                        child: Row(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Container(
                              width: 28,
                              height: 28,
                              alignment: Alignment.center,
                              decoration: BoxDecoration(
                                color: theme.colorScheme.primary,
                                shape: BoxShape.circle,
                              ),
                              child: Text(
                                t('step${i}k'),
                                style: const TextStyle(
                                  color: Colors.white,
                                  fontWeight: FontWeight.w700,
                                ),
                              ),
                            ),
                            const SizedBox(width: 12),
                            Expanded(child: Text(t('step${i}v'), style: theme.textTheme.bodyMedium)),
                          ],
                        ),
                      ),
                  ],
                ),
              ),
            ],
          ),
        ),

        // ------------------------------------------------------------ the button
        //
        // Outside the scroll view on purpose: it is always on the screen, at the
        // foot of it, whatever the customer has scrolled to.
        //
        // Picking the form back up matters more than starting it: someone who
        // took five photographs and was interrupted will not do it twice.
        SafeArea(
          top: false,
          child: Padding(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 12),
            child: ListenableBuilder(
              listenable: draft,
              builder: (context, _) {
                final resume = draft.hasContent;
                return Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    SizedBox(
                      width: double.infinity,
                      child: FilledButton.icon(
                        onPressed: () => _open(context),
                        icon: const Icon(Icons.photo_camera_outlined),
                        label: Text(resume
                            ? (lang == 'ar' ? 'متابعة الطلب' : 'Continue your request')
                            : t('navEval')),
                      ),
                    ),
                    if (resume) ...[
                      const SizedBox(height: 4),
                      TextButton(
                        onPressed: () async {
                          await draft.clear();
                          if (context.mounted) _open(context);
                        },
                        child: Text(lang == 'ar' ? 'ابدأ من جديد' : 'Start over'),
                      ),
                    ],
                  ],
                );
              },
            ),
          ),
        ),
      ],
    );
  }

  void _open(BuildContext context) {
    Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (_) => WizardScreen(
          api: api,
          config: config,
          prefs: prefs,
          draft: draft,
        ),
      ),
    );
  }
}
