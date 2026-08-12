import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:image_picker/image_picker.dart';

import '../api.dart';
import '../config.dart';
import '../state.dart';
import '../theme.dart';
import '../widgets/car_map.dart';
import '../widgets/common.dart';
import 'sent.dart';

/// The four steps, in the order the website asks them:
/// car details → condition → photos → your details.
///
/// One screen with a [PageView] rather than four routes, because the answers
/// belong to one object and the customer must be able to walk backwards
/// through them without losing anything.
class WizardScreen extends StatefulWidget {
  const WizardScreen({
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
  State<WizardScreen> createState() => _WizardScreenState();
}

class _WizardScreenState extends State<WizardScreen> {
  final _page = PageController();
  int step = 0;
  String error = '';

  bool sending = false;
  double progress = 0;

  AppConfig get cfg => widget.config;
  Draft get d => widget.draft;
  String get lang => widget.prefs.lang;
  String t(String k) => cfg.t(k, lang);

  @override
  void initState() {
    super.initState();
    widget.prefs.addListener(_r);
    d.addListener(_r);
  }

  @override
  void dispose() {
    widget.prefs.removeListener(_r);
    d.removeListener(_r);
    _page.dispose();
    super.dispose();
  }

  void _r() {
    if (mounted) setState(() {});
  }

  // ------------------------------------------------------------ navigation

  void _go(int to) {
    setState(() {
      error = '';
      step = to;
    });
    _page.animateToPage(to, duration: const Duration(milliseconds: 260), curve: Curves.easeOut);
  }

  void _next() {
    final problem = _validate(step);
    if (problem != null) {
      setState(() => error = problem);
      return;
    }
    if (step < 3) {
      _go(step + 1);
    } else {
      _send();
    }
  }

  /// The same rules `api.php` enforces, checked here first so a customer on a
  /// slow connection is never told what is wrong *after* uploading 40 MB of
  /// photographs.
  String? _validate(int s) {
    switch (s) {
      case 0:
        if (d.make.isEmpty || d.carClass.isEmpty || d.year.isEmpty) return t('errFields');
        if (d.model.trim().isEmpty) return t('errModel');
        if (d.mileage.replaceAll(RegExp(r'[^0-9]'), '').isEmpty) return t('errKm');
        return null;
      case 1:
        if (d.paintStatus.isEmpty) return t('errPaint');
        if ((d.paintStatus == 'repaint' || d.paintStatus == 'accident') &&
            d.paintExtent.isEmpty) {
          return t('errExtent');
        }
        return null;
      case 2:
        final missing = cfg.slots
            .where((sl) => sl.required && !d.photoPaths.containsKey(sl.key))
            .map((sl) => sl.label(lang))
            .toList();
        if (missing.isNotEmpty) {
          return cfg.f('errMissing', lang, {'list': missing.join('، ')});
        }
        if (d.photoPaths.length < cfg.minPhotos) {
          return cfg.f('counterBad', lang, {'a': d.photoPaths.length, 'b': cfg.minPhotos});
        }
        return null;
      case 3:
        if (d.name.trim().isEmpty) return t('errName');
        if (d.phone.replaceAll(RegExp(r'[^0-9]'), '').length < 7) return t('errPhone');
        if (!RegExp(r'^[^@\s]+@[^@\s]+\.[^@\s]+$').hasMatch(d.email.trim())) {
          return t('errEmail');
        }
        return null;
    }
    return null;
  }

  // ---------------------------------------------------------------- submit

  Future<void> _send() async {
    setState(() {
      sending = true;
      progress = 0;
      error = '';
    });

    try {
      final res = await widget.api.submit(
        fields: d.toFields(lang),
        photos: d.photoFiles,
        videos: d.videoFiles,
        onProgress: (sent, total) {
          if (!mounted || total <= 0) return;
          setState(() => progress = sent / total);
        },
      );

      if (res['ok'] != true) {
        setState(() {
          sending = false;
          error = _serverError('${res['error']}', res);
        });
        return;
      }

      final id = '${res['id']}';
      await widget.prefs.remember(id);
      // Only now — a draft cleared before the server has the request is a
      // customer who has to photograph the car all over again.
      await d.clear();

      if (!mounted) return;
      Navigator.of(context).pushReplacement(
        MaterialPageRoute<void>(
          builder: (_) => SentScreen(
            config: cfg,
            prefs: widget.prefs,
            id: id,
            expires: '${res['expires'] ?? ''}',
          ),
        ),
      );
    } on Object catch (e) {
      if (!mounted) return;
      setState(() {
        sending = false;
        error = e is ApiException && e.code != 'bad_response'
            ? _serverError(e.code, const {})
            : t('errNet');
      });
    }
  }

  /// Turn the server's short error code into the sentence the website would
  /// have shown for it.
  String _serverError(String code, Map<String, dynamic> res) {
    switch (code) {
      case 'fields':
        return t('errFields');
      case 'car_model':
        return t('errModel');
      case 'mileage':
        return t('errKm');
      case 'paint_status':
        return t('errPaint');
      case 'paint_extent':
        return t('errExtent');
      case 'missing_slot':
        return cfg.f('errMissing', lang, {
          'list': cfg.slots
              .firstWhere((s) => s.key == '${res['slot']}',
                  orElse: () => PhotoSlot(const {}))
              .label(lang),
        });
      case 'photo_count':
        return cfg.f('counterBad', lang, {'a': d.photoPaths.length, 'b': cfg.minPhotos});
      case 'photo_big':
        return cfg.f('errBig', lang, {'f': '', 'n': cfg.maxPhotoMB});
      case 'video_big':
        return cfg.f('errBig', lang, {'f': '', 'n': cfg.maxVideoMB});
      case 'video_count':
        return cfg.f('errVideoMax', lang, {'n': cfg.maxVideos});
      default:
        return t('errNet');
    }
  }

  // ------------------------------------------------------------------ view

  @override
  Widget build(BuildContext context) {
    if (sending) return _Sending(config: cfg, lang: lang, progress: progress);

    final titles = ['s1Title', 's2Title', 's3Title', 's4Title'];
    final subs = ['s1Sub', 's2Sub', 's3Sub', 's4Sub'];

    return PopScope(
      canPop: step == 0,
      onPopInvokedWithResult: (didPop, _) {
        if (!didPop) _go(step - 1);
      },
      child: Scaffold(
        appBar: AppBar(title: Text(t(titles[step]))),
        body: Column(
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 12),
              child: StepHeader(
                title: t(titles[step]),
                subtitle: t(subs[step]),
                index: step,
                total: 4,
              ),
            ),
            Expanded(
              child: PageView(
                controller: _page,
                physics: const NeverScrollableScrollPhysics(),
                children: [
                  _StepCar(cfg: cfg, d: d, lang: lang, error: error),
                  _StepCondition(cfg: cfg, d: d, lang: lang, error: error),
                  _StepPhotos(cfg: cfg, d: d, lang: lang, error: error),
                  _StepContact(cfg: cfg, d: d, lang: lang, error: error),
                ],
              ),
            ),
            SafeArea(
              top: false,
              child: Padding(
                padding: const EdgeInsets.fromLTRB(16, 8, 16, 12),
                child: Row(
                  children: [
                    if (step > 0) ...[
                      Expanded(
                        child: OutlinedButton(
                          onPressed: () => _go(step - 1),
                          child: Text(t('back')),
                        ),
                      ),
                      const SizedBox(width: 12),
                    ],
                    Expanded(
                      flex: 2,
                      child: FilledButton(
                        onPressed: _next,
                        child: Text(step == 3 ? t('send') : t('next')),
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

// ============================================================ step 1 — car

class _StepCar extends StatelessWidget {
  const _StepCar({required this.cfg, required this.d, required this.lang, required this.error});

  final AppConfig cfg;
  final Draft d;
  final String lang;
  final String error;

  static const _other = '__other__';

  @override
  Widget build(BuildContext context) {
    String t(String k) => cfg.t(k, lang);

    final makes = cfg.makes;
    final classes = d.make.isEmpty || d.make == _other ? <String>[] : cfg.classesOf(d.make);
    final years = (d.make.isEmpty || d.carClass.isEmpty || d.make == _other)
        ? <int>[for (var y = DateTime.now().year + 1; y >= 1990; y--) y]
        : cfg.yearsOf(d.make, d.carClass);

    return ListView(
      padding: const EdgeInsets.fromLTRB(16, 0, 16, 24),
      children: [
        ErrorBox(message: error),

        _Dropdown(
          label: t('fMake'),
          hint: t('selMake'),
          value: makes.contains(d.make) ? d.make : null,
          items: makes,
          onChanged: (v) => d.set(() {
            d.make = v ?? '';
            d.carClass = '';
            d.year = '';
          }),
        ),
        const SizedBox(height: 14),

        _Dropdown(
          label: t('fClass'),
          hint: t('selClass'),
          value: classes.contains(d.carClass) ? d.carClass : null,
          items: classes,
          enabled: classes.isNotEmpty,
          onChanged: (v) => d.set(() {
            d.carClass = v ?? '';
            d.year = '';
          }),
        ),
        const SizedBox(height: 14),

        // Free text, and required: «الموديل / الفئة الفرعية» is the trim —
        // GXR, VXR, Limited — which no database can list for Qatar reliably.
        _Field(
          label: t('fModel'),
          value: d.model,
          onChanged: (v) => d.set(() => d.model = v),
        ),
        const SizedBox(height: 14),

        _Dropdown(
          label: t('fYear'),
          hint: t('selYear'),
          value: years.map((e) => '$e').contains(d.year) ? d.year : null,
          items: years.map((e) => '$e').toList(),
          onChanged: (v) => d.set(() => d.year = v ?? ''),
        ),
        const SizedBox(height: 14),

        _Field(
          label: t('fKm'),
          value: d.mileage,
          keyboard: TextInputType.number,
          formatters: [FilteringTextInputFormatter.digitsOnly],
          suffix: lang == 'ar' ? 'كم' : 'km',
          onChanged: (v) => d.set(() => d.mileage = v),
        ),
        const SizedBox(height: 14),

        _Field(
          label: t('fReg'),
          value: d.registration,
          onChanged: (v) => d.set(() => d.registration = v),
        ),
        const SizedBox(height: 14),

        _Field(
          label: t('fVin'),
          value: d.chassis,
          onChanged: (v) => d.set(() => d.chassis = v),
        ),
        // The notes box is deliberately NOT here. It lives on step 2, right
        // after the paint questions — see _StepCondition.
      ],
    );
  }
}

// ====================================================== step 2 — condition

class _StepCondition extends StatelessWidget {
  const _StepCondition({required this.cfg, required this.d, required this.lang, required this.error});

  final AppConfig cfg;
  final Draft d;
  final String lang;
  final String error;

  @override
  Widget build(BuildContext context) {
    String t(String k) => cfg.t(k, lang);
    final cond = cfg.condition;
    final map = cfg.map;
    final locked = d.paintStatus == 'original';

    return ListView(
      padding: const EdgeInsets.fromLTRB(16, 0, 16, 24),
      children: [
        ErrorBox(message: error),

        SectionCard(
          title: t('cmTitle'),
          subtitle: locked ? t('cmLocked') : t('cmHint'),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              CarMapView(
                map: map,
                cond: cond,
                marks: d.panels,
                lang: lang,
                enabled: !locked,
                onTapPart: (part) {
                  d.cyclePanel(part, cond.stateOrder);
                  // Marking a panel as accident damage is the more specific
                  // statement, so it wins over "repainted only" — exactly as
                  // the website resolves the same contradiction.
                  if (d.panels[part] == 'accident' && d.paintStatus == 'repaint') {
                    d.set(() => d.paintStatus = 'accident');
                    final m = ScaffoldMessenger.maybeOf(context);
                    m?.showSnackBar(SnackBar(content: Text(t('cpMoved'))));
                  }
                },
              ),
              const SizedBox(height: 12),

              // The legend, so the two colours mean something before the first
              // tap rather than after it.
              Wrap(
                spacing: 14,
                runSpacing: 8,
                children: [
                  for (final s in cond.stateOrder)
                    Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Container(
                          width: 14,
                          height: 14,
                          decoration: BoxDecoration(
                            color: cond.stateColor(s),
                            borderRadius: BorderRadius.circular(4),
                          ),
                        ),
                        const SizedBox(width: 6),
                        Text(cond.stateLabel(s, lang),
                            style: Theme.of(context).textTheme.bodySmall),
                      ],
                    ),
                ],
              ),

              if (d.panels.isNotEmpty) ...[
                const SizedBox(height: 14),
                MarkedPanelPills(
                  map: map,
                  cond: cond,
                  marks: d.panels,
                  lang: lang,
                  onRemove: (k) => d.set(() => d.panels.remove(k)),
                ),
                const SizedBox(height: 10),
                Align(
                  alignment: AlignmentDirectional.centerStart,
                  child: TextButton.icon(
                    onPressed: () => d.set(() => d.panels.clear()),
                    icon: const Icon(Icons.layers_clear_outlined, size: 18),
                    label: Text(t('cmClear')),
                  ),
                ),
              ],
            ],
          ),
        ),
        const SizedBox(height: 14),

        SectionCard(
          title: t('cpTitle'),
          child: Column(
            children: [
              for (final k in cond.paintOrder)
                ChoiceRow(
                  label: cond.paintLabel(k, lang),
                  hint: cond.paintHint(k, lang),
                  selected: d.paintStatus == k,
                  onTap: () => d.set(() {
                    d.paintStatus = k;
                    if (k == 'original') {
                      // An untouched car cannot carry marks.
                      d.panels.clear();
                      d.paintExtent = '';
                    }
                  }),
                ),
            ],
          ),
        ),

        if (d.paintStatus == 'repaint' || d.paintStatus == 'accident') ...[
          const SizedBox(height: 14),
          SectionCard(
            title: t('ceTitle'),
            child: Column(
              children: [
                for (final k in cond.extentOrder)
                  ChoiceRow(
                    label: cond.extentLabel(k, lang),
                    selected: d.paintExtent == k,
                    onTap: () => d.set(() => d.paintExtent = k),
                  ),
              ],
            ),
          ),
        ],

        // The customer's own words sit here, right after the paint questions
        // and before the three scales — the same place the website puts them,
        // and for the same reason: this is the moment he is thinking about the
        // condition of the car, not while he is typing a chassis number.
        const SizedBox(height: 14),
        SectionCard(
          title: t('fNotes'),
          child: _Field(
            label: '',
            hint: t('phNotes'),
            value: d.notes,
            lines: 4,
            onChanged: (v) => d.set(() => d.notes = v),
          ),
        ),

        // interior · engine · gearbox — all three optional
        for (final scale in cond.scaleOrder) ...[
          const SizedBox(height: 14),
          SectionCard(
            title: cond.scaleLabel(scale, lang),
            subtitle: cond.scaleSub(scale, lang),
            child: ChoiceChips(
              options: cond.scaleOptions(scale),
              labelOf: (o) => cond.scaleOptionLabel(scale, o, lang),
              value: d.quality[scale],
              onChanged: (v) => d.set(() {
                if (v == null) {
                  d.quality.remove(scale);
                } else {
                  d.quality[scale] = v;
                }
              }),
            ),
          ),
        ],
      ],
    );
  }
}

// ========================================================= step 3 — photos

class _StepPhotos extends StatefulWidget {
  const _StepPhotos({required this.cfg, required this.d, required this.lang, required this.error});

  final AppConfig cfg;
  final Draft d;
  final String lang;
  final String error;

  @override
  State<_StepPhotos> createState() => _StepPhotosState();
}

class _StepPhotosState extends State<_StepPhotos> {
  final _picker = ImagePicker();
  bool busy = false;

  AppConfig get cfg => widget.cfg;
  Draft get d => widget.d;
  String get lang => widget.lang;
  String t(String k) => cfg.t(k, lang);

  /// Photographs are resized as they are picked, not before upload.
  ///
  /// A modern phone produces 6–8 MB per shot; eight of those is 60 MB over a
  /// Qatari mobile connection, and the server rejects anything over 12 MB
  /// anyway. 2000 px at quality 82 is far more than enough to see a scratch,
  /// and it turns a five-minute upload into a twenty-second one.
  Future<void> _pick(String slot, ImageSource source) async {
    setState(() => busy = true);
    try {
      final x = await _picker.pickImage(
        source: source,
        maxWidth: 2000,
        maxHeight: 2000,
        imageQuality: 82,
      );
      if (x == null) return;

      final size = await File(x.path).length();
      if (size > cfg.maxPhotoMB * 1024 * 1024) {
        _say(cfg.f('errBig', lang, {'f': x.name, 'n': cfg.maxPhotoMB}));
        return;
      }
      d.set(() => d.photoPaths[slot] = x.path);
    } on PlatformException catch (e) {
      // Camera permission refused, or no camera on the device.
      _say(e.message ?? t('errNet'));
    } on Object {
      _say(t('errNet'));
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  Future<void> _pickVideo() async {
    if (d.videoPaths.length >= cfg.maxVideos) {
      _say(cfg.f('errVideoMax', lang, {'n': cfg.maxVideos}));
      return;
    }
    setState(() => busy = true);
    try {
      final x = await _picker.pickVideo(
        source: ImageSource.gallery,
        maxDuration: const Duration(minutes: 2),
      );
      if (x == null) return;
      final size = await File(x.path).length();
      if (size > cfg.maxVideoMB * 1024 * 1024) {
        _say(cfg.f('errBig', lang, {'f': x.name, 'n': cfg.maxVideoMB}));
        return;
      }
      d.set(() => d.videoPaths.add(x.path));
    } on Object {
      _say(t('errNet'));
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  void _say(String m) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(m)));
  }

  @override
  Widget build(BuildContext context) {
    final have = d.photoPaths.length;
    final needed = cfg.slots.where((s) => s.required).length;
    final complete = cfg.slots.every((s) => !s.required || d.photoPaths.containsKey(s.key));

    return Stack(
      children: [
        ListView(
          padding: const EdgeInsets.fromLTRB(16, 0, 16, 24),
          children: [
            ErrorBox(message: widget.error),

            Container(
              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 11),
              decoration: BoxDecoration(
                color: (complete ? Brand.green : Brand.amber).withValues(alpha: 0.11),
                borderRadius: BorderRadius.circular(12),
              ),
              child: Row(
                children: [
                  Icon(complete ? Icons.check_circle : Icons.info_outline,
                      color: complete ? Brand.green : Brand.amber, size: 20),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Text(
                      complete
                          ? cfg.f('counterOk', lang, {'a': have})
                          : cfg.f('counterBad', lang, {'a': have, 'b': needed}),
                      style: TextStyle(
                        fontWeight: FontWeight.w700,
                        color: complete ? Brand.green : Brand.amber,
                      ),
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 12),
            Text(t('slotHint'), style: Theme.of(context).textTheme.bodySmall),
            const SizedBox(height: 16),

            GridView.count(
              crossAxisCount: 2,
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              mainAxisSpacing: 12,
              crossAxisSpacing: 12,
              childAspectRatio: 0.82,
              children: [
                for (final slot in cfg.slots)
                  _SlotTile(
                    slot: slot,
                    lang: lang,
                    path: d.photoPaths[slot.key],
                    cameraLabel: t('camera'),
                    deviceLabel: t('device'),
                    reqLabel: slot.required ? t('reqTag') : t('optTag'),
                    onCamera: () => _pick(slot.key, ImageSource.camera),
                    onGallery: () => _pick(slot.key, ImageSource.gallery),
                    onClear: () => d.set(() => d.photoPaths.remove(slot.key)),
                  ),
              ],
            ),
            const SizedBox(height: 20),

            SectionCard(
              title: t('addVideos'),
              subtitle: t('videoRule'),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  for (var i = 0; i < d.videoPaths.length; i++)
                    ListTile(
                      contentPadding: EdgeInsets.zero,
                      leading: const Icon(Icons.movie_outlined),
                      title: Text(d.videoPaths[i].split(Platform.pathSeparator).last,
                          maxLines: 1, overflow: TextOverflow.ellipsis),
                      trailing: IconButton(
                        icon: const Icon(Icons.close),
                        onPressed: () => d.set(() => d.videoPaths.removeAt(i)),
                      ),
                    ),
                  if (d.videoPaths.length < cfg.maxVideos)
                    OutlinedButton.icon(
                      onPressed: _pickVideo,
                      icon: const Icon(Icons.video_call_outlined),
                      label: Text(t('addVideos')),
                    ),
                ],
              ),
            ),
          ],
        ),
        if (busy)
          const Positioned.fill(
            child: ColoredBox(
              color: Color(0x33000000),
              child: Center(child: CircularProgressIndicator()),
            ),
          ),
      ],
    );
  }
}

class _SlotTile extends StatelessWidget {
  const _SlotTile({
    required this.slot,
    required this.lang,
    required this.path,
    required this.cameraLabel,
    required this.deviceLabel,
    required this.reqLabel,
    required this.onCamera,
    required this.onGallery,
    required this.onClear,
  });

  final PhotoSlot slot;
  final String lang;
  final String? path;
  final String cameraLabel;
  final String deviceLabel;
  final String reqLabel;
  final VoidCallback onCamera;
  final VoidCallback onGallery;
  final VoidCallback onClear;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final dark = theme.brightness == Brightness.dark;
    final filled = path != null;

    return Container(
      decoration: BoxDecoration(
        color: dark ? Brand.dSurface2 : Brand.surface2,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(
          color: filled ? Brand.green : (theme.dividerTheme.color ?? Brand.line),
          width: filled ? 1.6 : 1,
        ),
      ),
      clipBehavior: Clip.antiAlias,
      child: Column(
        children: [
          Expanded(
            child: filled
                ? Stack(
                    fit: StackFit.expand,
                    children: [
                      Image.file(File(path!), fit: BoxFit.cover),
                      PositionedDirectional(
                        top: 4,
                        end: 4,
                        child: Material(
                          color: Colors.black54,
                          shape: const CircleBorder(),
                          child: InkWell(
                            customBorder: const CircleBorder(),
                            onTap: onClear,
                            child: const Padding(
                              padding: EdgeInsets.all(5),
                              child: Icon(Icons.close, size: 16, color: Colors.white),
                            ),
                          ),
                        ),
                      ),
                    ],
                  )
                : Center(
                    child: Icon(Icons.add_a_photo_outlined,
                        size: 30, color: theme.textTheme.bodySmall?.color),
                  ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(8, 7, 8, 4),
            child: Column(
              children: [
                Text(
                  slot.label(lang),
                  textAlign: TextAlign.center,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 12.5),
                ),
                Text(reqLabel,
                    style: TextStyle(
                      fontSize: 10.5,
                      color: slot.required ? Brand.red : theme.textTheme.bodySmall?.color,
                    )),
              ],
            ),
          ),
          Row(
            children: [
              Expanded(
                child: TextButton(
                  onPressed: onCamera,
                  style: TextButton.styleFrom(
                    padding: const EdgeInsets.symmetric(vertical: 6),
                    minimumSize: Size.zero,
                  ),
                  child: Text(cameraLabel, style: const TextStyle(fontSize: 12)),
                ),
              ),
              Container(width: 1, height: 18, color: theme.dividerTheme.color),
              Expanded(
                child: TextButton(
                  onPressed: onGallery,
                  style: TextButton.styleFrom(
                    padding: const EdgeInsets.symmetric(vertical: 6),
                    minimumSize: Size.zero,
                  ),
                  child: Text(deviceLabel, style: const TextStyle(fontSize: 12)),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

// ======================================================== step 4 — contact

class _StepContact extends StatelessWidget {
  const _StepContact({required this.cfg, required this.d, required this.lang, required this.error});

  final AppConfig cfg;
  final Draft d;
  final String lang;
  final String error;

  @override
  Widget build(BuildContext context) {
    String t(String k) => cfg.t(k, lang);

    return ListView(
      padding: const EdgeInsets.fromLTRB(16, 0, 16, 24),
      children: [
        ErrorBox(message: error),

        _Field(label: t('fName'), value: d.name, onChanged: (v) => d.set(() => d.name = v)),
        const SizedBox(height: 14),
        _Field(
          label: t('fPhone'),
          value: d.phone,
          keyboard: TextInputType.phone,
          onChanged: (v) => d.set(() => d.phone = v),
        ),
        const SizedBox(height: 14),
        _Field(
          label: t('fEmail'),
          value: d.email,
          keyboard: TextInputType.emailAddress,
          onChanged: (v) => d.set(() => d.email = v),
        ),
        const SizedBox(height: 20),

        SectionCard(
          title: t('fKeep'),
          subtitle: t('keepNote'),
          child: ChoiceChips(
            options: cfg.retentionDays.map((e) => '$e').toList(),
            labelOf: (o) => t(o == '3' ? 'd3' : 'd7'),
            value: '${d.retention}',
            onChanged: (v) => d.set(() => d.retention = int.tryParse(v ?? '3') ?? 3),
          ),
        ),
      ],
    );
  }
}

// ================================================================== pieces

class _Field extends StatefulWidget {
  const _Field({
    required this.label,
    required this.value,
    required this.onChanged,
    this.hint,
    this.lines = 1,
    this.keyboard,
    this.formatters,
    this.suffix,
  });

  final String label;
  final String? hint;
  final String value;
  final int lines;
  final TextInputType? keyboard;
  final List<TextInputFormatter>? formatters;
  final String? suffix;
  final ValueChanged<String> onChanged;

  @override
  State<_Field> createState() => _FieldState();
}

class _FieldState extends State<_Field> {
  late final TextEditingController c = TextEditingController(text: widget.value);

  @override
  void dispose() {
    c.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return TextField(
      controller: c,
      onChanged: widget.onChanged,
      maxLines: widget.lines,
      keyboardType: widget.keyboard,
      inputFormatters: widget.formatters,
      textInputAction: widget.lines > 1 ? TextInputAction.newline : TextInputAction.next,
      decoration: InputDecoration(
        // An empty label is not the same as no label: the first floats an
        // empty box above the field. The card heading already names this one.
        labelText: widget.label.isEmpty ? null : widget.label,
        hintText: widget.hint,
        suffixText: widget.suffix,
        alignLabelWithHint: widget.lines > 1,
      ),
    );
  }
}

class _Dropdown extends StatelessWidget {
  const _Dropdown({
    required this.label,
    required this.hint,
    required this.value,
    required this.items,
    required this.onChanged,
    this.enabled = true,
  });

  final String label;
  final String hint;
  final String? value;
  final List<String> items;
  final bool enabled;
  final ValueChanged<String?> onChanged;

  @override
  Widget build(BuildContext context) {
    return DropdownButtonFormField<String>(
      value: value,
      isExpanded: true,
      decoration: InputDecoration(labelText: label),
      hint: Text(hint, style: Theme.of(context).textTheme.bodySmall),
      items: [
        for (final i in items)
          DropdownMenuItem(value: i, child: Text(i, overflow: TextOverflow.ellipsis)),
      ],
      onChanged: enabled ? onChanged : null,
    );
  }
}

/// Shown for as long as the upload takes, and it must not be dismissible:
/// leaving this screen mid-upload loses the request.
class _Sending extends StatelessWidget {
  const _Sending({required this.config, required this.lang, required this.progress});

  final AppConfig config;
  final String lang;
  final double progress;

  @override
  Widget build(BuildContext context) {
    String t(String k) => config.t(k, lang);
    final done = progress >= 0.999;

    return PopScope(
      canPop: false,
      child: Scaffold(
        body: SafeArea(
          child: Center(
            child: Padding(
              padding: const EdgeInsets.all(32),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  SizedBox(
                    width: 74,
                    height: 74,
                    child: CircularProgressIndicator(
                      value: done ? null : progress.clamp(0.02, 1),
                      strokeWidth: 5,
                    ),
                  ),
                  const SizedBox(height: 26),
                  Text(
                    done ? t('sendingWork') : t('sendingTitle'),
                    style: Theme.of(context).textTheme.titleMedium,
                    textAlign: TextAlign.center,
                  ),
                  const SizedBox(height: 8),
                  Text(
                    done ? t('sendingWorkSub') : t('sendingWarn'),
                    style: Theme.of(context).textTheme.bodySmall,
                    textAlign: TextAlign.center,
                  ),
                  if (!done) ...[
                    const SizedBox(height: 14),
                    Text('${(progress * 100).round()}%',
                        style: const TextStyle(fontWeight: FontWeight.w700)),
                  ],
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}
