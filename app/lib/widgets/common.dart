import 'package:flutter/material.dart';

import '../theme.dart';

/// A titled white card — the site's `.card`, and the only container the app
/// uses. Consistency here is most of what makes the two feel like one product.
class SectionCard extends StatelessWidget {
  const SectionCard({super.key, required this.child, this.title, this.subtitle, this.padding});

  final Widget child;
  final String? title;
  final String? subtitle;
  final EdgeInsets? padding;

  @override
  Widget build(BuildContext context) {
    final t = Theme.of(context);
    return Card(
      child: Padding(
        padding: padding ?? const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            if (title != null && title!.isNotEmpty) ...[
              Text(title!, style: t.textTheme.titleMedium),
              if (subtitle != null && subtitle!.isNotEmpty) ...[
                const SizedBox(height: 4),
                Text(subtitle!, style: t.textTheme.bodySmall),
              ],
              const SizedBox(height: 14),
            ],
            child,
          ],
        ),
      ),
    );
  }
}

/// The selectable answer used everywhere a question has three or four options:
/// paint status, partial/full, and the three quality scales.
///
/// It is a full-width row rather than a chip when it carries an explanation,
/// because three explanations side by side on a 390 px screen reflow into one
/// unreadable paragraph — which is exactly the bug the website hit in v9.
class ChoiceRow extends StatelessWidget {
  const ChoiceRow({
    super.key,
    required this.label,
    required this.selected,
    required this.onTap,
    this.hint,
    this.enabled = true,
  });

  final String label;
  final String? hint;
  final bool selected;
  final VoidCallback onTap;

  /// A locked answer is shown, not hidden. An option that vanishes leaves the
  /// customer wondering what he did; one that is visibly unavailable, with the
  /// reason underneath, answers the question before he asks it.
  final bool enabled;

  @override
  Widget build(BuildContext context) {
    final t = Theme.of(context);
    final dark = t.brightness == Brightness.dark;
    final accent = t.colorScheme.primary;

    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Opacity(
        opacity: enabled ? 1 : 0.42,
        child: Material(
        color: selected ? (dark ? Brand.dTint : Brand.tint) : (dark ? Brand.dSurface2 : Brand.surface2),
        borderRadius: BorderRadius.circular(14),
        child: InkWell(
          borderRadius: BorderRadius.circular(14),
          onTap: enabled ? onTap : null,
          child: Container(
            width: double.infinity,
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 13),
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(14),
              border: Border.all(
                color: selected ? accent : (dark ? Brand.dLine : Brand.line),
                width: selected ? 2 : 1,
              ),
            ),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Icon(
                  selected ? Icons.radio_button_checked : Icons.radio_button_unchecked,
                  size: 21,
                  color: selected ? accent : t.textTheme.bodySmall?.color,
                ),
                const SizedBox(width: 11),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        label,
                        style: t.textTheme.bodyLarge?.copyWith(
                          fontWeight: selected ? FontWeight.w700 : FontWeight.w600,
                          decoration: enabled ? null : TextDecoration.lineThrough,
                        ),
                      ),
                      if (hint != null && hint!.isNotEmpty) ...[
                        const SizedBox(height: 5),
                        Divider(height: 9, color: t.dividerTheme.color),
                        const SizedBox(height: 5),
                        Text(hint!, style: t.textTheme.bodySmall),
                      ],
                    ],
                  ),
                ),
              ],
            ),
          ),
        ),
        ),
      ),
    );
  }
}

/// The compact version, for answers that are just a number: 90 %, 75 %, 50 %.
class ChoiceChips extends StatelessWidget {
  const ChoiceChips({
    super.key,
    required this.options,
    required this.labelOf,
    required this.value,
    required this.onChanged,
  });

  final List<String> options;
  final String Function(String) labelOf;
  final String? value;
  final void Function(String?) onChanged;

  @override
  Widget build(BuildContext context) {
    final t = Theme.of(context);
    return Wrap(
      spacing: 8,
      runSpacing: 8,
      children: [
        for (final o in options)
          ChoiceChip(
            label: Text(labelOf(o)),
            selected: value == o,
            onSelected: (v) => onChanged(v ? o : null),
            labelStyle: TextStyle(
              fontWeight: FontWeight.w700,
              color: value == o ? t.colorScheme.onPrimary : t.textTheme.bodyLarge?.color,
            ),
            selectedColor: t.colorScheme.primary,
            backgroundColor: t.brightness == Brightness.dark ? Brand.dSurface2 : Brand.surface2,
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(11),
              side: BorderSide(color: t.dividerTheme.color ?? Brand.line),
            ),
            showCheckmark: false,
          ),
      ],
    );
  }
}

/// A red error box that names what is missing, in the customer's language.
class ErrorBox extends StatelessWidget {
  const ErrorBox({super.key, required this.message});
  final String message;

  @override
  Widget build(BuildContext context) {
    if (message.isEmpty) return const SizedBox.shrink();
    return Container(
      width: double.infinity,
      margin: const EdgeInsets.only(bottom: 14),
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
      decoration: BoxDecoration(
        color: Brand.red.withValues(alpha: 0.09),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: Brand.red.withValues(alpha: 0.35)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Icon(Icons.error_outline, color: Brand.red, size: 20),
          const SizedBox(width: 10),
          Expanded(
            child: Text(
              message,
              style: const TextStyle(color: Brand.red, fontWeight: FontWeight.w600, height: 1.5),
            ),
          ),
        ],
      ),
    );
  }
}

/// «الخطوة 2 من 4» plus the four dots, so progress is legible without reading.
class StepHeader extends StatelessWidget {
  const StepHeader({
    super.key,
    required this.title,
    required this.subtitle,
    required this.index,
    required this.total,
  });

  final String title;
  final String subtitle;
  final int index; // zero based
  final int total;

  @override
  Widget build(BuildContext context) {
    final t = Theme.of(context);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            for (var i = 0; i < total; i++)
              Expanded(
                child: Container(
                  height: 5,
                  margin: EdgeInsetsDirectional.only(end: i == total - 1 ? 0 : 6),
                  decoration: BoxDecoration(
                    color: i <= index ? t.colorScheme.primary : t.dividerTheme.color,
                    borderRadius: BorderRadius.circular(3),
                  ),
                ),
              ),
          ],
        ),
        const SizedBox(height: 14),
        Text(title, style: t.textTheme.headlineSmall),
        const SizedBox(height: 3),
        Text(subtitle, style: t.textTheme.bodySmall),
      ],
    );
  }
}
