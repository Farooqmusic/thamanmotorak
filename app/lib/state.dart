import 'dart:convert';
import 'dart:io';

import 'package:flutter/foundation.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// One valuation being filled in.
///
/// It is saved to disk after every change. Filling this form means walking
/// round a car in the sun taking eight photographs — a phone call, a low
/// battery or a switch to the camera app in the middle of that must not throw
/// the work away. The photo *paths* are saved too; the files themselves live in
/// the phone's cache until the request is sent.
class Draft extends ChangeNotifier {
  Draft();

  static const _key = 'draft_v1';

  // step 1 — the car
  String make = '';
  String model = '';
  String carClass = '';
  String year = '';
  String mileage = '';
  String registration = '';
  String chassis = '';

  // step 2 — condition
  String paintStatus = '';
  String paintExtent = '';
  Map<String, String> panels = {}; // part key -> 'painted' | 'accident'
  Map<String, String> quality = {}; // 'interior' | 'engine' | 'gearbox' -> value

  // step 3 — pictures
  Map<String, String> photoPaths = {}; // slot key -> file path
  List<String> videoPaths = [];

  /// The inspection report — «فحص» or any third party's. A PDF or a photo of
  /// the paper; the server treats both the same and so does this.
  List<String> reportPaths = [];

  // step 4 — the person
  String name = '';
  String phone = '';
  String email = '';
  String notes = '';
  int retention = 3;

  Map<String, File> get photoFiles =>
      photoPaths.map((k, v) => MapEntry(k, File(v)))
        ..removeWhere((_, f) => !f.existsSync());

  List<File> get videoFiles =>
      videoPaths.map(File.new).where((f) => f.existsSync()).toList();

  List<File> get reportFiles =>
      reportPaths.map(File.new).where((f) => f.existsSync()).toList();

  /// Exactly the field names `api.php?do=submit` reads. Keeping the mapping in
  /// one place means a rename on the server is a one-line change here, not a
  /// hunt through four screens.
  Map<String, String> toFields(String lang) => {
        'lang': lang,
        'name': name,
        'phone': phone,
        'email': email,
        'car_make': make,
        'car_class': carClass,
        'car_model': model,
        'car_year': year,
        'mileage': mileage,
        'registration': registration,
        'chassis': chassis,
        'notes': notes,
        'retention': '$retention',
        'paint_status': paintStatus,
        'paint_extent': paintExtent,
        'panels': jsonEncode(panels),
        for (final e in quality.entries)
          if (e.value.isNotEmpty) 'q_${e.key}': e.value,
      };

  void set(void Function() change) {
    change();
    notifyListeners();
    save();
  }

  /// Tap once for repainted, twice for accident repair, a third time to clear.
  /// The same three-state cycle the website uses, so a customer who has tried
  /// it on a laptop already knows how it works.
  void cyclePanel(String part, List<String> order) {
    final cur = panels[part];
    if (cur == null) {
      panels[part] = order.isNotEmpty ? order.first : 'painted';
    } else {
      final i = order.indexOf(cur);
      if (i < 0 || i + 1 >= order.length) {
        panels.remove(part);
      } else {
        panels[part] = order[i + 1];
      }
    }
    notifyListeners();
    save();
  }

  // -------------------------------------------------------------- storage

  Future<void> save() async {
    final p = await SharedPreferences.getInstance();
    await p.setString(
      _key,
      jsonEncode({
        'make': make, 'model': model, 'carClass': carClass, 'year': year,
        'mileage': mileage, 'registration': registration, 'chassis': chassis,
        'paintStatus': paintStatus, 'paintExtent': paintExtent,
        'panels': panels, 'quality': quality,
        'photoPaths': photoPaths, 'videoPaths': videoPaths,
        'reportPaths': reportPaths,
        'name': name, 'phone': phone, 'email': email, 'notes': notes,
        'retention': retention,
      }),
    );
  }

  Future<void> load() async {
    final p = await SharedPreferences.getInstance();
    final s = p.getString(_key);
    if (s == null) return;
    try {
      final m = jsonDecode(s) as Map<String, dynamic>;
      make = '${m['make'] ?? ''}';
      model = '${m['model'] ?? ''}';
      carClass = '${m['carClass'] ?? ''}';
      year = '${m['year'] ?? ''}';
      mileage = '${m['mileage'] ?? ''}';
      registration = '${m['registration'] ?? ''}';
      chassis = '${m['chassis'] ?? ''}';
      paintStatus = '${m['paintStatus'] ?? ''}';
      paintExtent = '${m['paintExtent'] ?? ''}';
      panels = _ss(m['panels']);
      quality = _ss(m['quality']);
      photoPaths = _ss(m['photoPaths'])
        ..removeWhere((_, v) => !File(v).existsSync());
      videoPaths = (m['videoPaths'] as List? ?? [])
          .map((e) => '$e')
          .where((v) => File(v).existsSync())
          .toList();
      // A draft saved before this field existed simply has no key, and a file
      // the system has since cleaned out of the cache is dropped rather than
      // resurrected as a path that will fail at upload time.
      reportPaths = (m['reportPaths'] as List? ?? [])
          .map((e) => '$e')
          .where((v) => File(v).existsSync())
          .toList();
      name = '${m['name'] ?? ''}';
      phone = '${m['phone'] ?? ''}';
      email = '${m['email'] ?? ''}';
      notes = '${m['notes'] ?? ''}';
      retention = m['retention'] is int ? m['retention'] as int : 3;
      notifyListeners();
    } on Object {
      await p.remove(_key);
    }
  }

  /// Called once the request is safely on the server — never before.
  Future<void> clear() async {
    make = model = carClass = year = mileage = registration = chassis = '';
    paintStatus = paintExtent = '';
    panels = {};
    quality = {};
    photoPaths = {};
    videoPaths = [];
    reportPaths = [];
    name = phone = email = notes = '';
    retention = 3;
    final p = await SharedPreferences.getInstance();
    await p.remove(_key);
    notifyListeners();
  }

  /// True when there is enough here to be worth offering to continue.
  bool get hasContent =>
      make.isNotEmpty ||
      photoPaths.isNotEmpty ||
      reportPaths.isNotEmpty ||
      name.isNotEmpty;

  static Map<String, String> _ss(Object? v) => v is Map
      ? v.map((k, val) => MapEntry('$k', '$val'))
      : <String, String>{};
}

/// Language and theme, remembered between launches.
class Prefs extends ChangeNotifier {
  String lang = 'ar';
  bool get isRtl => lang == 'ar';

  /// The codes of valuations sent from this phone, so the status screen can
  /// offer them instead of asking a customer to remember six characters.
  List<String> myIds = [];

  Future<void> load() async {
    final p = await SharedPreferences.getInstance();
    lang = p.getString('lang') ?? 'ar';
    myIds = p.getStringList('my_ids') ?? [];
    notifyListeners();
  }

  Future<void> setLang(String v) async {
    lang = v == 'en' ? 'en' : 'ar';
    final p = await SharedPreferences.getInstance();
    await p.setString('lang', lang);
    notifyListeners();
  }

  Future<void> remember(String id) async {
    if (id.isEmpty || myIds.contains(id)) return;
    myIds = [id, ...myIds].take(20).toList();
    final p = await SharedPreferences.getInstance();
    await p.setStringList('my_ids', myIds);
    notifyListeners();
  }
}
