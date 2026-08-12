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
      appBar: AppBar(
        title: Text(t('appName')),
        actions: [_LanguageButton(prefs: widget.prefs)],
      ),
      body: IndexedStack(index: tab, children: pages),
      bottomNavigationBar: NavigationBar(
        selectedIndex: tab,
        onDestinationSelected: (i) => setState(() => tab = i),
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
        ],
      ),
    );
  }
}

/// The language switch.
///
/// It was plain maroon text in the corner, which measured 3.3:1 against the
/// dark background — below the 4.5:1 small text needs, and in practice almost
/// invisible at night. It is now a bordered chip: legible in both themes, and
/// it reads as something you can press rather than a label.
///
/// The word shown is always the language you would switch **to**. That is the
/// only version people read correctly.
class _LanguageButton extends StatelessWidget {
  const _LanguageButton({required this.prefs});

  final Prefs prefs;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final dark = theme.brightness == Brightness.dark;
    final ink = dark ? Brand.dBrandInk : Brand.brand;
    final toArabic = prefs.lang == 'en';

    return Padding(
      padding: const EdgeInsetsDirectional.only(end: 10),
      child: Material(
        color: dark ? Brand.dTint : Brand.tint,
        borderRadius: BorderRadius.circular(11),
        child: InkWell(
          borderRadius: BorderRadius.circular(11),
          onTap: () => prefs.setLang(toArabic ? 'ar' : 'en'),
          child: Container(
            constraints: const BoxConstraints(minWidth: 52, minHeight: 38),
            alignment: Alignment.center,
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(11),
              border: Border.all(color: ink.withValues(alpha: 0.55)),
            ),
            child: Text(
              toArabic ? 'ع' : 'EN',
              // Each label is set in the face it is written in, whatever the
              // app's current language — an "EN" in a naskh face looks wrong,
              // and a lone "ع" in Poppins looks worse.
              style: TextStyle(
                fontFamily: toArabic ? 'Naskh' : 'Poppins',
                fontWeight: FontWeight.w700,
                fontSize: toArabic ? 19 : 15,
                height: 1.1,
                color: ink,
              ),
            ),
          ),
        ),
      ),
    );
  }
}

/// The first thing anyone sees: what this is, that it is free, and one button.
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

    return ListView(
      padding: const EdgeInsets.fromLTRB(16, 8, 16, 28),
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

        // Picking the form back up matters more than starting it: someone who
        // took five photographs and was interrupted will not do it twice.
        ListenableBuilder(
          listenable: draft,
          builder: (context, _) {
            final resume = draft.hasContent;
            return Column(
              children: [
                FilledButton.icon(
                  onPressed: () => _open(context),
                  icon: const Icon(Icons.photo_camera_outlined),
                  label: Text(resume
                      ? (lang == 'ar' ? 'متابعة الطلب' : 'Continue your request')
                      : t('navEval')),
                ),
                if (resume) ...[
                  const SizedBox(height: 10),
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
        const SizedBox(height: 26),

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
        const SizedBox(height: 14),

        SectionCard(
          title: t('privTitle'),
          child: Text(t('privBody'), style: theme.textTheme.bodyMedium),
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
