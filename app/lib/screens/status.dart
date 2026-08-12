import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:url_launcher/url_launcher.dart';

import '../api.dart';
import '../config.dart';
import '../state.dart';
import '../theme.dart';
import '../widgets/common.dart';

/// "Where is my valuation?" — six characters, no password.
///
/// The website works this way and the app must not invent a login the customer
/// was never given. What the app *can* add is memory: every code sent from this
/// phone is offered as a chip, so nobody has to find the email again.
class StatusScreen extends StatefulWidget {
  const StatusScreen({
    super.key,
    required this.api,
    required this.config,
    required this.prefs,
  });

  final Api api;
  final AppConfig config;
  final Prefs prefs;

  @override
  State<StatusScreen> createState() => _StatusScreenState();
}

class _StatusScreenState extends State<StatusScreen> {
  final _c = TextEditingController();
  bool busy = false;
  String error = '';
  Map<String, dynamic>? result;

  String get lang => widget.prefs.lang;
  String t(String k) => widget.config.t(k, lang);

  @override
  void initState() {
    super.initState();
    widget.prefs.addListener(_r);
    // One saved code and nothing typed: look it up without being asked.
    final ids = widget.prefs.myIds;
    if (ids.length == 1) {
      _c.text = ids.first;
      WidgetsBinding.instance.addPostFrameCallback((_) => _check());
    }
  }

  @override
  void dispose() {
    widget.prefs.removeListener(_r);
    _c.dispose();
    super.dispose();
  }

  void _r() {
    if (mounted) setState(() {});
  }

  Future<void> _check() async {
    final id = _c.text.trim().toUpperCase();
    if (id.length < 6) {
      setState(() => error = t('errId'));
      return;
    }
    setState(() {
      busy = true;
      error = '';
      result = null;
    });
    try {
      final r = await widget.api.status(id);
      if (r['ok'] != true) {
        setState(() => error = t('errNotFound'));
      } else {
        setState(() => result = r);
        await widget.prefs.remember(id);
      }
    } on Object {
      setState(() => error = t('errNet'));
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return ListView(
      padding: const EdgeInsets.fromLTRB(16, 16, 16, 28),
      children: [
        Text(t('stTitle'), style: theme.textTheme.headlineSmall),
        const SizedBox(height: 6),
        Text(t('stSub'), style: theme.textTheme.bodySmall),
        const SizedBox(height: 18),

        ErrorBox(message: error),

        TextField(
          controller: _c,
          textCapitalization: TextCapitalization.characters,
          textDirection: TextDirection.ltr,
          maxLength: 6,
          inputFormatters: [
            FilteringTextInputFormatter.allow(RegExp('[a-zA-Z0-9]')),
            TextInputFormatter.withFunction(
              (_, n) => n.copyWith(text: n.text.toUpperCase()),
            ),
          ],
          decoration: InputDecoration(
            labelText: t('fId'),
            counterText: '',
            prefixIcon: const Icon(Icons.confirmation_number_outlined),
          ),
          style: const TextStyle(fontSize: 22, letterSpacing: 4, fontWeight: FontWeight.w700),
          onSubmitted: (_) => _check(),
        ),
        const SizedBox(height: 12),

        if (widget.prefs.myIds.isNotEmpty) ...[
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              for (final id in widget.prefs.myIds)
                ActionChip(
                  label: Text(id, textDirection: TextDirection.ltr),
                  onPressed: () {
                    _c.text = id;
                    _check();
                  },
                ),
            ],
          ),
          const SizedBox(height: 12),
        ],

        FilledButton(
          onPressed: busy ? null : _check,
          child: Text(busy ? t('checking') : t('check')),
        ),

        if (result != null) ...[
          const SizedBox(height: 22),
          _Result(config: widget.config, lang: lang, r: result!),
        ],
      ],
    );
  }
}

class _Result extends StatelessWidget {
  const _Result({required this.config, required this.lang, required this.r});

  final AppConfig config;
  final String lang;
  final Map<String, dynamic> r;

  @override
  Widget build(BuildContext context) {
    String t(String k) => config.t(k, lang);
    final theme = Theme.of(context);

    final done = '${r['status']}' == 'done' && '${r['price_text'] ?? ''}'.isNotEmpty;
    final colour = done ? Brand.green : Brand.amber;
    final pdf = '${r['pdf'] ?? ''}';

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        // The traffic light the website uses: red while under review, green
        // when the price is in. It is understood before anything is read.
        Container(
          padding: const EdgeInsets.all(18),
          decoration: BoxDecoration(
            color: colour.withValues(alpha: 0.10),
            borderRadius: BorderRadius.circular(16),
            border: Border.all(color: colour.withValues(alpha: 0.4)),
          ),
          child: Row(
            children: [
              Icon(done ? Icons.check_circle : Icons.hourglass_top, color: colour, size: 30),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      done ? t('ready') : t('underReview'),
                      style: theme.textTheme.titleMedium?.copyWith(color: colour),
                    ),
                    const SizedBox(height: 3),
                    Text(done ? t('readySub') : t('underReviewSub'),
                        style: theme.textTheme.bodySmall),
                  ],
                ),
              ),
            ],
          ),
        ),

        if (done) ...[
          const SizedBox(height: 14),
          Card(
            child: Padding(
              padding: const EdgeInsets.symmetric(vertical: 22, horizontal: 16),
              child: Column(
                children: [
                  Text(
                    r['price_range'] == true ? t('priceRange') : t('priceLabel'),
                    style: theme.textTheme.bodySmall,
                  ),
                  const SizedBox(height: 8),
                  Text(
                    '${r['price_text']}',
                    textAlign: TextAlign.center,
                    style: const TextStyle(fontSize: 30, fontWeight: FontWeight.w700),
                  ),
                  const SizedBox(height: 4),
                  Text(config.currency(lang), style: theme.textTheme.bodySmall),
                ],
              ),
            ),
          ),
        ],

        const SizedBox(height: 14),
        SectionCard(
          child: Column(
            children: [
              _row(context, t('idLabel'), '${r['id']}'),
              _row(context, t('carLabel'), '${r['car'] ?? ''}'),
              _row(context, t('sentAt'), _date('${r['created'] ?? ''}')),
              _row(context, t('filesUntil'), _date('${r['expires'] ?? ''}')),
              _row(
                context,
                t('filesCount'),
                '${r['photos'] ?? 0} ${t('photosW')} · ${r['videos'] ?? 0} ${t('videosW')}',
              ),
            ],
          ),
        ),

        if ('${r['note_$lang'] ?? ''}'.trim().isNotEmpty) ...[
          const SizedBox(height: 14),
          SectionCard(
            title: t('noteLabel'),
            child: Text('${r['note_$lang']}', style: theme.textTheme.bodyMedium),
          ),
        ],

        if (pdf.isNotEmpty) ...[
          const SizedBox(height: 14),
          FilledButton.icon(
            onPressed: () => launchUrl(
              Uri.parse('${config.site()}/$pdf'),
              mode: LaunchMode.externalApplication,
            ),
            icon: const Icon(Icons.picture_as_pdf_outlined),
            label: Text(t('pdfDownload')),
          ),
        ],
      ],
    );
  }

  Widget _row(BuildContext context, String k, String v) {
    if (v.trim().isEmpty) return const SizedBox.shrink();
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Expanded(
            flex: 2,
            child: Text(k, style: Theme.of(context).textTheme.bodySmall),
          ),
          Expanded(
            flex: 3,
            child: Text(v, style: const TextStyle(fontWeight: FontWeight.w600)),
          ),
        ],
      ),
    );
  }

  /// The server sends ISO-8601 UTC. Show it in the phone's own time — a
  /// customer in Doha should not have to add three hours in his head.
  static String _date(String iso) {
    if (iso.isEmpty) return '';
    final d = DateTime.tryParse(iso);
    if (d == null) return iso;
    final l = d.toLocal();
    String two(int n) => n.toString().padLeft(2, '0');
    return '${two(l.day)}/${two(l.month)}/${l.year} · ${two(l.hour)}:${two(l.minute)}';
  }
}
