import 'dart:convert';
import 'dart:ui';

import 'package:shared_preferences/shared_preferences.dart';

import 'api.dart';
import 'strings.dart';

/// The server's answer to `api.php?do=config`, in a shape the widgets can use.
///
/// Nothing in this app is hard-coded: not one car model, not one Arabic label,
/// not one polygon of the condition diagram. All of it arrives from the site
/// that already owns it, which is why a word Khalid edits in the control panel
/// shows up on every phone at the next launch and never needs a store update.
///
/// The last good answer is kept on disk, so the app opens and works on a phone
/// with no signal — it simply cannot submit until there is one.
class AppConfig {
  AppConfig(this.raw);

  final Map<String, dynamic> raw;

  static const _cacheKey = 'config_json_v1';

  /// Read from disk first so the first frame is never a spinner, then refresh
  /// from the network in the background.
  static Future<AppConfig?> cached() async {
    final p = await SharedPreferences.getInstance();
    final s = p.getString(_cacheKey);
    if (s == null) return null;
    try {
      return AppConfig(jsonDecode(s) as Map<String, dynamic>);
    } on Object {
      await p.remove(_cacheKey);
      return null;
    }
  }

  static Future<AppConfig> fetch(Api api) async {
    final data = await api.config();
    if (data['ok'] != true) throw ApiException('config_not_ok');
    final p = await SharedPreferences.getInstance();
    await p.setString(_cacheKey, jsonEncode(data));
    return AppConfig(data);
  }

  // ---------------------------------------------------------------- limits

  Map<String, dynamic> get _limits => _map(raw['limits']);

  int get minPhotos => _int(_limits['minPhotos'], 5);
  int get maxPhotos => _int(_limits['maxPhotos'], 8);
  int get maxVideos => _int(_limits['maxVideos'], 2);
  int get maxPhotoMB => _int(_limits['maxPhotoMB'], 12);
  int get maxVideoMB => _int(_limits['maxVideoMB'], 60);

  List<int> get retentionDays {
    final v = _limits['retentionDays'];
    if (v is List && v.isNotEmpty) return v.map((e) => _int(e, 3)).toList();
    return const [3, 7];
  }

  String currency(String lang) => _str(_map(raw['currency'])[lang]);

  String site() => _str(raw['site'], Api.defaultBase);

  String page(String key) => _str(_map(raw['pages'])[key]);

  /// WhatsApp, email, Instagram, website and whatever else Khalid has put in
  /// the panel — in his order, and only the ones he has filled in.
  List<ContactRow> get contact {
    final v = raw['contact'];
    if (v is! List) return const [];
    return v
        .map((e) => ContactRow(_map(e)))
        .where((c) => c.label.isNotEmpty)
        .toList();
  }

  /// «تطوير: فاروق» and the address behind it.
  ({String label, String url}) devCredit(String lang) {
    final m = _map(raw['devCredit']);
    return (label: _str(m[lang], t('devCredit', lang)), url: _str(m['url']));
  }

  // ----------------------------------------------------------------- cars

  /// `{ "Toyota": { "Land Cruiser": [1985, 0], … } }` — the second number is
  /// 0 while the model is still sold new, so the year list runs to next year
  /// on its own and the database never needs editing just because time passed.
  Map<String, dynamic> get cars => _map(raw['cars']);

  List<String> get makes {
    final l = cars.keys.toList()..sort();
    return l;
  }

  /// The models a make sells — «الفئة» on the website, `car_class` on the
  /// wire. The free-text trim the customer types after this is `car_model`.
  List<String> classesOf(String make) {
    final m = _map(cars[make]);
    final l = m.keys.toList()..sort();
    return l;
  }

  /// Years offered for one model, newest first — that is the order a seller
  /// thinks in, and it puts the likely answers under the thumb.
  List<int> yearsOf(String make, String carClass, {int? nowYear}) {
    final span = _map(cars[make])[carClass];
    final now = nowYear ?? DateTime.now().year;
    var from = 1990, to = now + 1;
    if (span is List && span.length >= 2) {
      from = _int(span[0], 1990);
      final last = _int(span[1], 0);
      to = last == 0 ? now + 1 : last;
    }
    if (to < from) to = from;
    return [for (var y = to; y >= from; y--) y];
  }

  // ------------------------------------------------------------- the form

  /// The eight photo positions, five of them required. Same list, same keys,
  /// same order as the website — `slots()` in `lib.php`.
  List<PhotoSlot> get slots {
    final v = raw['slots'];
    if (v is! List) return const [];
    return v.map((e) => PhotoSlot(_map(e))).toList();
  }

  /// The trim list for «الموديل / الفئة الفرعية».
  ///
  /// Two groups, because sellers in Qatar use two different things to answer
  /// this: the factory badge (GXR, LTZ) and how loaded the car is (فل كامل).
  /// Both are offered, and «أخرى» always opens a free-text box so no car can
  /// be impossible to describe.
  List<TrimGroup> trimGroups(String lang) {
    final t = _map(raw['trims']);
    final out = <TrimGroup>[];

    void add(String key, String ar, String en) {
      final v = t[key];
      if (v is! List || v.isEmpty) return;
      out.add(TrimGroup(
        lang == 'ar' ? ar : en,
        v.map((e) => _map(e)).map((m) {
          final label = _str(m[lang], _str(m['en']));
          return TrimOption(_str(m['key'], label), label);
        }).toList(),
      ));
    }

    add('badges', 'الفئة', 'Trim');
    add('levels', 'مستوى التجهيز', 'Equipment level');
    return out;
  }

  String trimOtherLabel(String lang) {
    final v = _map(_map(raw['trims'])['other'])[lang];
    if (v is String && v.isNotEmpty) return v;
    return lang == 'ar' ? 'أخرى — اكتب الفئة' : 'Other — type it';
  }

  /// True when the answer is not one of the offered options, so the free-text
  /// box has to be shown for it.
  bool isKnownTrim(String value) {
    if (value.isEmpty) return false;
    for (final g in trimGroups('en')) {
      for (final o in g.options) {
        if (o.label == value) return true;
      }
    }
    for (final g in trimGroups('ar')) {
      for (final o in g.options) {
        if (o.label == value) return true;
      }
    }
    return false;
  }

  /// تصميم اليوم — the concept car the website is showing right now, chosen by
  /// the server so a phone and a laptop in the same room agree.
  ConceptCar? get concept {
    final m = _map(raw['concept']);
    final url = _str(m['image']);
    if (url.isEmpty) return null;
    return ConceptCar(m);
  }

  Condition get condition => Condition(_map(raw['cond']));

  CarMap get map => CarMap(_map(raw['map']));

  Map<String, dynamic> get supportKinds => _map(raw['supportKinds']);

  // ------------------------------------------------------------------ text

  /// A label, in the language asked for.
  ///
  /// Four places are tried in turn, most authoritative first: what Khalid
  /// typed in the control panel, the built-in condition wording, the website's
  /// own string table shipped with the app, and finally English. The order
  /// matters — an edit in the panel must beat anything compiled in, or the app
  /// would quietly ignore him.
  String t(String key, String lang) {
    for (final src in [raw['i18n'], raw['condI18n']]) {
      final v = _map(_map(src)[lang])[key];
      if (v is String && v.trim().isNotEmpty) return v;
    }
    final built = uiStrings[lang]?[key];
    if (built != null && built.isNotEmpty) return built;
    return uiStrings['en']?[key] ?? '';
  }

  /// Same, with `{name}` placeholders filled in — `errBig`, `counterOk` and
  /// the like carry them.
  String f(String key, String lang, Map<String, Object?> vars) {
    var s = t(key, lang);
    vars.forEach((k, v) => s = s.replaceAll('{$k}', '$v'));
    return s;
  }
}

// ===================================================================== bits

class ContactRow {
  ContactRow(this._m);
  final Map<String, dynamic> _m;

  /// 'WhatsApp' | 'Email' | 'Instagram' | 'Website' | a custom label
  String get kind => _str(_m['kind']);
  String get label => _str(_m['label']);
  String get href => _str(_m['href']);
}

class TrimGroup {
  const TrimGroup(this.title, this.options);
  final String title;
  final List<TrimOption> options;
}

class TrimOption {
  const TrimOption(this.key, this.label);
  final String key;
  final String label;
}

class ConceptCar {
  ConceptCar(this._m);
  final Map<String, dynamic> _m;

  String get name => _str(_m['name']);
  String get image => _str(_m['image']);
  String get imageSmall => _str(_m['image_sm'], _str(_m['image']));
  double get aspect {
    final w = _dbl(_m['width'], 0), h = _dbl(_m['height'], 0);
    return (w > 0 && h > 0) ? w / h : 1.67;
  }

  String button(String lang) => _str(_map(_m['button'])[lang]);
}

class PhotoSlot {
  PhotoSlot(this._m);
  final Map<String, dynamic> _m;

  String get key => _str(_m['key']);
  bool get required => _m['req'] == true;
  String label(String lang) => _str(_m[lang], _str(_m['en']));
}

/// The condition step: paint status, how much of the car, the three quality
/// scales, and the two colours a marked panel can take.
class Condition {
  Condition(this._m);
  final Map<String, dynamic> _m;

  List<String> get paintOrder => _strList(_m['paint_order']);
  List<String> get extentOrder => _strList(_m['extent_order']);
  List<String> get scaleOrder => _strList(_m['scale_order']);
  List<String> get stateOrder => _strList(_m['state_order']);

  String paintLabel(String k, String lang) => _str(_map(_map(_m['paint'])[k])[lang]);
  String paintHint(String k, String lang) =>
      _str(_map(_map(_m['paint'])[k])['hint_$lang']);
  String extentLabel(String k, String lang) => _str(_map(_map(_m['extent'])[k])[lang]);

  String scaleLabel(String k, String lang) => _str(_map(_map(_m['scales'])[k])[lang]);
  String scaleSub(String k, String lang) => _str(_map(_map(_m['scales'])[k])['sub_$lang']);
  List<String> scaleOptions(String k) => _strList(_map(_map(_m['scales'])[k])['order']);
  String scaleOptionLabel(String scale, String opt, String lang) =>
      _str(_map(_map(_map(_map(_m['scales'])[scale])['opts'])[opt])[lang]);

  String stateLabel(String k, String lang) => _str(_map(_map(_m['states'])[k])[lang]);

  Color stateColor(String k) => _color(_map(_map(_m['states'])[k])['color'], 0xffe0a12c);
  Color stateInk(String k) => _color(_map(_map(_m['states'])[k])['ink'], 0xff000000);
}

/// The exploded top view of the car: fifteen tappable panels, plus the glass,
/// door handles and wheels that make it readable as a car.
///
/// The polygons are the *same numbers* `carmap.php` feeds to the website's SVG
/// and to the PDF renderer. Change a shape once on the server and all three
/// follow — the app included, with no release.
class CarMap {
  CarMap(this._m);
  final Map<String, dynamic> _m;

  double get width => _dbl(_m['width'], 800);
  double get height => _dbl(_m['height'], 764);

  Color get blank => _color(_m['blank'], 0xfff0eaed);
  Color get line => _color(_m['line'], 0xffb3a3aa);

  List<String> get order => _strList(_m['order']);

  String partLabel(String key, String lang) => _str(_map(_map(_m['parts'])[key])[lang]);

  List<Offset> polygon(String key) => _points(_map(_map(_m['parts'])[key])['poly']);

  List<List<Offset>> get glass => _polyList(_m['glass']);
  List<List<Offset>> get grab => _polyList(_m['grab']);

  /// `[centreX, centreY, radius]` for each of the four wheels.
  List<List<double>> get wheels {
    final v = _m['wheels'];
    if (v is! List) return const [];
    return v
        .whereType<List>()
        .map((w) => w.map((n) => _dbl(n, 0)).toList())
        .toList();
  }

  static List<List<Offset>> _polyList(Object? v) {
    if (v is! List) return const [];
    return v.map(_points).toList();
  }

  static List<Offset> _points(Object? v) {
    if (v is! List) return const [];
    return v
        .whereType<List>()
        .where((p) => p.length >= 2)
        .map((p) => Offset(_dbl(p[0], 0), _dbl(p[1], 0)))
        .toList();
  }
}

// ================================================================== helpers
//
// The payload is JSON from a PHP array, so a number can arrive as an int, a
// double or a string depending on how it was written. These readers never
// throw: a malformed field falls back rather than taking the screen down.

Map<String, dynamic> _map(Object? v) =>
    v is Map ? v.map((k, val) => MapEntry(k.toString(), val)) : <String, dynamic>{};

String _str(Object? v, [String or = '']) =>
    v is String && v.isNotEmpty ? v : (v == null ? or : (v.toString().isEmpty ? or : v.toString()));

int _int(Object? v, int or) =>
    v is int ? v : (v is num ? v.toInt() : int.tryParse('$v') ?? or);

double _dbl(Object? v, double or) =>
    v is double ? v : (v is num ? v.toDouble() : double.tryParse('$v') ?? or);

List<String> _strList(Object? v) =>
    v is List ? v.map((e) => e.toString()).toList() : const [];

Color _color(Object? v, int or) {
  final s = v is String ? v.replaceAll('#', '').trim() : '';
  if (s.length == 6) {
    final n = int.tryParse(s, radix: 16);
    if (n != null) return Color(0xff000000 | n);
  }
  return Color(or);
}
