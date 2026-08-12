import 'package:flutter/material.dart';

import '../config.dart';

/// The exploded top view of the car, drawn from the server's polygons.
///
/// The website draws these same numbers as SVG and the PDF draws them with GD.
/// This is the third renderer, and it is deliberately dumb: it owns no shapes
/// of its own, so a panel reshaped in `carmap.php` reshapes here at the next
/// launch with no release.
///
/// Tap once for repainted, twice for accident repair, a third time to clear.
class CarMapView extends StatelessWidget {
  const CarMapView({
    super.key,
    required this.map,
    required this.cond,
    required this.marks,
    required this.lang,
    this.onTapPart,
    this.enabled = true,
  });

  final CarMap map;
  final Condition cond;

  /// part key -> state key ('painted' | 'accident')
  final Map<String, String> marks;
  final String lang;
  final void Function(String partKey)? onTapPart;

  /// False once the customer says the car is fully original — an untouched car
  /// cannot carry marks, so the diagram greys out rather than lying.
  final bool enabled;

  @override
  Widget build(BuildContext context) {
    final dark = Theme.of(context).brightness == Brightness.dark;

    return LayoutBuilder(
      builder: (context, box) {
        final w = box.maxWidth;
        final h = w * (map.height / map.width);

        return Semantics(
          label: lang == 'ar' ? 'أجزاء السيارة' : 'Car panels',
          child: Opacity(
            opacity: enabled ? 1 : 0.45,
            child: GestureDetector(
              behavior: HitTestBehavior.opaque,
              onTapUp: enabled && onTapPart != null
                  ? (d) {
                      final scale = map.width / w;
                      final p = d.localPosition * scale;
                      final hit = _hitTest(p);
                      if (hit != null) onTapPart!(hit);
                    }
                  : null,
              child: CustomPaint(
                size: Size(w, h),
                painter: _CarMapPainter(
                  map: map,
                  cond: cond,
                  marks: enabled ? marks : const {},
                  dark: dark,
                ),
              ),
            ),
          ),
        );
      },
    );
  }

  /// Which panel is under the finger, in diagram coordinates.
  ///
  /// Walked in reverse so that where two shapes overlap the one drawn last —
  /// the one the eye sees on top — is the one that answers.
  String? _hitTest(Offset p) {
    final keys = map.order;
    for (var i = keys.length - 1; i >= 0; i--) {
      final poly = map.polygon(keys[i]);
      if (poly.length >= 3 && _inside(p, poly)) return keys[i];
    }
    return null;
  }

  /// Ray casting. The polygons are convex-ish rounded rectangles and arcs, but
  /// this handles any of them without caring which.
  static bool _inside(Offset p, List<Offset> poly) {
    var hit = false;
    for (var i = 0, j = poly.length - 1; i < poly.length; j = i++) {
      final a = poly[i], b = poly[j];
      if ((a.dy > p.dy) != (b.dy > p.dy)) {
        final x = (b.dx - a.dx) * (p.dy - a.dy) / (b.dy - a.dy) + a.dx;
        if (p.dx < x) hit = !hit;
      }
    }
    return hit;
  }
}

class _CarMapPainter extends CustomPainter {
  _CarMapPainter({
    required this.map,
    required this.cond,
    required this.marks,
    required this.dark,
  });

  final CarMap map;
  final Condition cond;
  final Map<String, String> marks;
  final bool dark;

  @override
  void paint(Canvas canvas, Size size) {
    canvas.save();
    canvas.scale(size.width / map.width);

    final blank = dark ? const Color(0xFF2B252C) : map.blank;
    final line = dark ? const Color(0xFF4A414B) : map.line;

    final stroke = Paint()
      ..style = PaintingStyle.stroke
      ..strokeWidth = 2
      ..strokeJoin = StrokeJoin.round
      ..color = line;

    // wheels sit behind the body, exactly as in the SVG
    for (final w in map.wheels) {
      if (w.length < 3) continue;
      final c = Offset(w[0], w[1]);
      canvas.drawCircle(c, w[2], Paint()..color = blank);
      canvas.drawCircle(c, w[2], stroke);
    }

    // the fifteen panels
    for (final key in map.order) {
      final poly = map.polygon(key);
      if (poly.length < 3) continue;
      final state = marks[key];
      final fill = state == null ? blank : cond.stateColor(state);
      final path = _path(poly);
      canvas.drawPath(path, Paint()..color = fill);
      canvas.drawPath(path, stroke);
    }

    // glass and door handles — decoration only, never tappable
    final glassPaint = Paint()
      ..color = dark ? const Color(0xFF3A3140) : const Color(0xFFDCD3E4);
    for (final g in map.glass) {
      if (g.length < 3) continue;
      canvas.drawPath(_path(g), glassPaint);
    }
    final grabPaint = Paint()..color = line;
    for (final g in map.grab) {
      if (g.length < 3) continue;
      canvas.drawPath(_path(g), grabPaint);
    }

    canvas.restore();
  }

  Path _path(List<Offset> pts) {
    final p = Path()..moveTo(pts.first.dx, pts.first.dy);
    for (var i = 1; i < pts.length; i++) {
      p.lineTo(pts[i].dx, pts[i].dy);
    }
    return p..close();
  }

  @override
  bool shouldRepaint(_CarMapPainter old) =>
      old.dark != dark || !_sameMarks(old.marks, marks) || old.map != map;

  static bool _sameMarks(Map<String, String> a, Map<String, String> b) {
    if (a.length != b.length) return false;
    for (final e in a.entries) {
      if (b[e.key] != e.value) return false;
    }
    return true;
  }
}

/// The marked panels written out underneath the diagram.
///
/// The picture alone is not an answer a customer can check — a mis-tap on a
/// small screen is invisible until it is named. Each pill says which panel and
/// which side, in words.
class MarkedPanelPills extends StatelessWidget {
  const MarkedPanelPills({
    super.key,
    required this.map,
    required this.cond,
    required this.marks,
    required this.lang,
    this.onRemove,
  });

  final CarMap map;
  final Condition cond;
  final Map<String, String> marks;
  final String lang;
  final void Function(String partKey)? onRemove;

  @override
  Widget build(BuildContext context) {
    if (marks.isEmpty) return const SizedBox.shrink();

    // grouped by state so "3 panels repainted, 1 accident" reads at a glance
    final ordered = <MapEntry<String, String>>[];
    for (final state in cond.stateOrder) {
      for (final key in map.order) {
        if (marks[key] == state) ordered.add(MapEntry(key, state));
      }
    }

    return Wrap(
      spacing: 8,
      runSpacing: 8,
      children: [
        for (final e in ordered)
          Chip(
            label: Text(
              '${map.partLabel(e.key, lang)} · ${cond.stateLabel(e.value, lang)}',
              style: TextStyle(
                fontSize: 12.5,
                fontWeight: FontWeight.w700,
                color: cond.stateInk(e.value),
              ),
            ),
            backgroundColor: cond.stateColor(e.value),
            side: BorderSide.none,
            visualDensity: VisualDensity.compact,
            materialTapTargetSize: MaterialTapTargetSize.shrinkWrap,
            onDeleted: onRemove == null ? null : () => onRemove!(e.key),
            deleteIconColor: cond.stateInk(e.value),
          ),
      ],
    );
  }
}
