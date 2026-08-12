import 'package:flutter_test/flutter_test.dart';
import 'package:thaman_app/config.dart';

/// The app trusts one thing completely: the JSON from `api.php?do=config`.
///
/// These tests are about what happens when that trust is misplaced — a field
/// missing after a server change, a number arriving as a string because PHP
/// wrote it that way, a whole section absent because the request half-failed.
/// None of those may take a screen down; the app should degrade to a sensible
/// default and carry on.
void main() {
  group('AppConfig survives a malformed payload', () {
    test('an empty payload still answers every getter', () {
      final c = AppConfig(const {});
      expect(c.minPhotos, 5);
      expect(c.maxPhotos, 8);
      expect(c.retentionDays, [3, 7]);
      expect(c.makes, isEmpty);
      expect(c.slots, isEmpty);
      expect(c.map.width, 800);
      expect(c.condition.paintOrder, isEmpty);
      expect(c.contact, isEmpty);
    });

    test('numbers written as strings are read as numbers', () {
      // PHP's json_encode will do this whenever a value came from a form or a
      // text file rather than a cast — it has bitten this project before.
      final c = AppConfig(const {
        'limits': {'minPhotos': '6', 'maxPhotoMB': '12', 'retentionDays': ['3', '7']},
      });
      expect(c.minPhotos, 6);
      expect(c.maxPhotoMB, 12);
      expect(c.retentionDays, [3, 7]);
    });
  });

  group('years', () {
    test('an open-ended model runs to next year', () {
      final c = AppConfig(const {
        'cars': {
          'Toyota': {
            'Land Cruiser': [1985, 0],
          },
        },
      });
      final years = c.yearsOf('Toyota', 'Land Cruiser', nowYear: 2026);
      expect(years.first, 2027, reason: 'newest first, and next year is on sale');
      expect(years.last, 1985);
    });

    test('a retired model stops at its last year', () {
      final c = AppConfig(const {
        'cars': {
          'Toyota': {
            'FJ Cruiser': [2007, 2023],
          },
        },
      });
      final years = c.yearsOf('Toyota', 'FJ Cruiser', nowYear: 2026);
      expect(years.first, 2023);
      expect(years.last, 2007);
    });

    test('an unknown model does not crash, it offers a sane range', () {
      final c = AppConfig(const {'cars': {}});
      final years = c.yearsOf('Nothing', 'Nowhere', nowYear: 2026);
      expect(years, isNotEmpty);
      expect(years.first, greaterThanOrEqualTo(2026));
    });
  });

  group('text', () {
    test('the control panel beats the built-in wording', () {
      final c = AppConfig(const {
        'i18n': {
          'ar': {'heroTitle': 'نص من لوحة التحكم'},
        },
      });
      expect(c.t('heroTitle', 'ar'), 'نص من لوحة التحكم');
    });

    test('a key the panel does not set falls back to the shipped table', () {
      final c = AppConfig(const {'i18n': {'ar': {}}});
      expect(c.t('next', 'ar'), isNotEmpty);
      expect(c.t('next', 'en'), isNotEmpty);
    });

    test('an unknown key is empty, not an exception', () {
      final c = AppConfig(const {});
      expect(c.t('no_such_key_anywhere', 'ar'), '');
    });

    test('placeholders are filled', () {
      final c = AppConfig(const {
        'i18n': {
          'en': {'errBig': 'The file "{f}" is too big (limit {n} MB).'},
        },
      });
      expect(
        c.f('errBig', 'en', {'f': 'front.jpg', 'n': 12}),
        'The file "front.jpg" is too big (limit 12 MB).',
      );
    });
  });

  group('the panel diagram', () {
    test('polygons arrive as usable points', () {
      final c = AppConfig(const {
        'map': {
          'width': 800,
          'height': 764,
          'order': ['hood'],
          'parts': {
            'hood': {
              'ar': 'غطاء المحرك',
              'en': 'Bonnet',
              'poly': [
                [10, 20],
                [30, 40],
                [50, 60],
              ],
            },
          },
        },
      });
      final poly = c.map.polygon('hood');
      expect(poly.length, 3);
      expect(poly.first.dx, 10);
      expect(poly.first.dy, 20);
      expect(c.map.partLabel('hood', 'ar'), 'غطاء المحرك');
      expect(c.map.partLabel('hood', 'en'), 'Bonnet');
    });

    test('a colour arrives as #rrggbb and comes out opaque', () {
      final c = AppConfig(const {
        'cond': {
          'states': {
            'painted': {'ar': 'صبغ', 'en': 'Repainted', 'color': '#e0a12c'},
          },
        },
      });
      final col = c.condition.stateColor('painted');
      expect(col.toARGB32(), 0xFFE0A12C);
    });
  });
}
