import 'package:flutter/material.dart';

import '../api.dart';
import '../config.dart';
import '../state.dart';
import '../theme.dart';
import '../widgets/common.dart';

/// "Something is wrong / I have a suggestion", and the follow-up screen that
/// answers "did anyone read it?".
///
/// Posts to the same `?do=support` the website uses, so a message from the app
/// lands in `support.json` beside the others and Khalid has one inbox.
class SupportScreen extends StatefulWidget {
  const SupportScreen({
    super.key,
    required this.api,
    required this.config,
    required this.prefs,
  });

  final Api api;
  final AppConfig config;
  final Prefs prefs;

  @override
  State<SupportScreen> createState() => _SupportScreenState();
}

class _SupportScreenState extends State<SupportScreen> {
  final _name = TextEditingController();
  final _email = TextEditingController();
  final _phone = TextEditingController();
  final _ref = TextEditingController();
  final _msg = TextEditingController();
  final _follow = TextEditingController();

  String kind = 'problem';
  bool busy = false;
  String error = '';
  String? sentId;
  Map<String, dynamic>? followResult;
  String followError = '';

  String get lang => widget.prefs.lang;
  String t(String k) => widget.config.t(k, lang);

  @override
  void initState() {
    super.initState();
    widget.prefs.addListener(_r);
  }

  @override
  void dispose() {
    widget.prefs.removeListener(_r);
    for (final c in [_name, _email, _phone, _ref, _msg, _follow]) {
      c.dispose();
    }
    super.dispose();
  }

  void _r() {
    if (mounted) setState(() {});
  }

  Future<void> _send() async {
    if (_msg.text.trim().length < 10) {
      setState(() => error = t('errSupMsg'));
      return;
    }
    final hasEmail =
        RegExp(r'^[^@\s]+@[^@\s]+\.[^@\s]+$').hasMatch(_email.text.trim());
    final hasPhone = _phone.text.replaceAll(RegExp(r'[^0-9]'), '').length >= 7;
    if (!hasEmail && !hasPhone) {
      setState(() => error = t('errSupContact'));
      return;
    }

    setState(() {
      busy = true;
      error = '';
    });
    try {
      final r = await widget.api.support({
        'lang': lang,
        'sup_kind': kind,
        's_name': _name.text.trim(),
        's_email': _email.text.trim(),
        's_phone': _phone.text.trim(),
        's_ref': _ref.text.trim().toUpperCase(),
        's_msg': _msg.text.trim(),
        's_page': 'app',
        // The honeypot the website uses. Empty is the honest answer; the field
        // exists so the two clients look identical to the server.
        'eyc_hp': '',
      });
      if (r['ok'] == true) {
        setState(() => sentId = '${r['id']}');
      } else {
        setState(() => error =
            '${r['error']}' == 'too_many' ? t('errSupMany') : t('errNet'));
      }
    } on Object {
      setState(() => error = t('errNet'));
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  Future<void> _checkFollow() async {
    final id = _follow.text.trim().toUpperCase();
    if (id.length < 6) {
      setState(() => followError = t('errSupId'));
      return;
    }
    setState(() {
      followError = '';
      followResult = null;
    });
    try {
      final r = await widget.api.supportStatus(id);
      if (r['ok'] != true) {
        setState(() => followError = t('errSupNotFound'));
      } else {
        setState(() => followResult = r);
      }
    } on Object {
      setState(() => followError = t('errNet'));
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    if (sentId != null) return _thanks(context);

    final kinds = widget.config.supportKinds;

    String kindLabel(String k) {
      final v = kinds[k];
      if (v is Map) return '${v[lang] ?? v['en'] ?? k}';
      return '$v';
    }

    return ListView(
      padding: const EdgeInsets.fromLTRB(16, 14, 16, 28),
      children: [
        Text(t('supportTitle'), style: theme.textTheme.headlineSmall),
        const SizedBox(height: 4),
        Text(t('supportIntro'), style: theme.textTheme.bodySmall),
        const SizedBox(height: 14),

        ErrorBox(message: error),

        // «نوع الرسالة» was a titled card holding three full-width buttons —
        // about a fifth of the screen spent on a question with three answers,
        // and it pushed the message box, which is the actual point of the
        // page, below the fold. One line does the same job.
        DropdownButtonFormField<String>(
          value: kinds.containsKey(kind) ? kind : null,
          isExpanded: true,
          decoration: InputDecoration(labelText: t('supKind')),
          items: [
            for (final k in kinds.keys)
              DropdownMenuItem(
                value: k,
                child: Text(kindLabel(k), overflow: TextOverflow.ellipsis),
              ),
          ],
          onChanged: (v) => setState(() => kind = v ?? kind),
        ),
        const SizedBox(height: 12),

        // Starts two lines tall and grows as it is typed into, up to eight.
        // A box that is five lines of empty grey before anybody has written a
        // word is five lines of nothing; one that grows shows the customer
        // that what he is writing is being kept.
        TextField(
          controller: _msg,
          minLines: 2,
          maxLines: 8,
          keyboardType: TextInputType.multiline,
          decoration: InputDecoration(
            labelText: t('supMsg'),
            hintText: t('supMsgPh'),
            alignLabelWithHint: true,
          ),
        ),
        const SizedBox(height: 12),

        Text(t('supReach'), style: theme.textTheme.bodySmall),
        const SizedBox(height: 8),

        TextField(controller: _name, decoration: InputDecoration(labelText: t('fName'))),
        const SizedBox(height: 10),
        TextField(
          controller: _email,
          keyboardType: TextInputType.emailAddress,
          decoration: InputDecoration(labelText: t('fEmail')),
        ),
        const SizedBox(height: 10),
        TextField(
          controller: _phone,
          keyboardType: TextInputType.phone,
          decoration: InputDecoration(labelText: t('fPhone')),
        ),
        const SizedBox(height: 10),
        TextField(
          controller: _ref,
          textCapitalization: TextCapitalization.characters,
          decoration: InputDecoration(labelText: t('supRef')),
        ),
        const SizedBox(height: 16),

        FilledButton(
          onPressed: busy ? null : _send,
          child: Text(busy ? t('supSending') : t('supSend')),
        ),

        const SizedBox(height: 22),
        Divider(color: theme.dividerTheme.color),
        const SizedBox(height: 14),

        Text(t('supFollowT'), style: theme.textTheme.titleMedium),
        const SizedBox(height: 4),
        Text(t('supFollowS'), style: theme.textTheme.bodySmall),
        const SizedBox(height: 12),

        ErrorBox(message: followError),

        TextField(
          controller: _follow,
          textCapitalization: TextCapitalization.characters,
          textDirection: TextDirection.ltr,
          decoration: InputDecoration(labelText: t('supRefNo')),
          onSubmitted: (_) => _checkFollow(),
        ),
        const SizedBox(height: 12),
        OutlinedButton(onPressed: _checkFollow, child: Text(t('check'))),

        if (followResult != null) ...[
          const SizedBox(height: 18),
          _followCard(context, followResult!),
        ],
      ],
    );
  }

  Widget _followCard(BuildContext context, Map<String, dynamic> r) {
    final theme = Theme.of(context);
    final replied = '${r['reply'] ?? ''}'.trim().isNotEmpty;
    final seen = r['seen'] == true;

    final label = replied
        ? t('supStReplied')
        : seen
            ? t('supStSeen')
            : t('supStNew');
    final colour = replied ? Brand.green : (seen ? Brand.amber : Brand.muted);

    return SectionCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(replied ? Icons.mark_email_read : Icons.schedule, color: colour, size: 20),
              const SizedBox(width: 8),
              Text(label, style: theme.textTheme.titleMedium?.copyWith(color: colour)),
            ],
          ),
          const SizedBox(height: 14),
          Text(t('supYourMsg'), style: theme.textTheme.bodySmall),
          const SizedBox(height: 4),
          Text('${r['msg'] ?? ''}', style: theme.textTheme.bodyMedium),
          if (replied) ...[
            const SizedBox(height: 14),
            Text(t('supOurReply'), style: theme.textTheme.bodySmall),
            const SizedBox(height: 4),
            Text('${r['reply']}', style: theme.textTheme.bodyMedium),
          ],
        ],
      ),
    );
  }

  Widget _thanks(BuildContext context) {
    final theme = Theme.of(context);
    return ListView(
      padding: const EdgeInsets.fromLTRB(16, 40, 16, 28),
      children: [
        const Center(child: Icon(Icons.check_circle, size: 64, color: Brand.green)),
        const SizedBox(height: 18),
        Text(t('supThanksT'),
            textAlign: TextAlign.center, style: theme.textTheme.headlineSmall),
        const SizedBox(height: 8),
        Text(t('supportThanks'),
            textAlign: TextAlign.center, style: theme.textTheme.bodyMedium),
        const SizedBox(height: 22),
        if ((sentId ?? '').isNotEmpty)
          Card(
            child: Padding(
              padding: const EdgeInsets.symmetric(vertical: 20),
              child: Column(
                children: [
                  Text(t('supRefNo'), style: theme.textTheme.bodySmall),
                  const SizedBox(height: 8),
                  SelectableText(
                    sentId!,
                    textDirection: TextDirection.ltr,
                    style: const TextStyle(
                        fontSize: 26, fontWeight: FontWeight.w700, letterSpacing: 3),
                  ),
                  const SizedBox(height: 10),
                  Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 20),
                    child: Text(t('supKeep'),
                        textAlign: TextAlign.center, style: theme.textTheme.bodySmall),
                  ),
                ],
              ),
            ),
          ),
        const SizedBox(height: 22),
        OutlinedButton(
          onPressed: () => setState(() {
            sentId = null;
            _msg.clear();
            _ref.clear();
          }),
          child: Text(t('supAnother')),
        ),
      ],
    );
  }
}
