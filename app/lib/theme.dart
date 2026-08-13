import 'package:flutter/material.dart';

/// The website's palette, moved across unchanged.
///
/// The values are lifted from the custom properties at the top of
/// `assets/app.css`, including the dark set, so a customer who has seen the
/// site recognises the app as the same company rather than a lookalike.
class Brand {
  // light — Qatar maroon
  static const brand = Color(0xFF8A1538);
  static const brand2 = Color(0xFFA81C46);
  static const gold = Color(0xFFC9A227);
  static const bg = Color(0xFFF7F4F5);
  static const card = Color(0xFFFFFFFF);
  static const surface2 = Color(0xFFFBF8F9);
  static const surface3 = Color(0xFFF0E9EC);
  static const tint = Color(0xFFFDF2F5);
  static const ink = Color(0xFF1B1418);
  static const muted = Color(0xFF6E5C63);
  static const line = Color(0xFFECE2E6);
  static const green = Color(0xFF1A8F52);
  static const red = Color(0xFFC62B3B);
  static const amber = Color(0xFFC98A1E);

  // dark
  static const dBrand = Color(0xFFC02A55);
  static const dGold = Color(0xFFE0B93F);
  static const dBg = Color(0xFF131013);
  static const dCard = Color(0xFF1C181C);
  static const dSurface2 = Color(0xFF241F25);
  static const dSurface3 = Color(0xFF2B252C);
  static const dTint = Color(0xFF2E1A22);
  static const dInk = Color(0xFFF2ECEF);
  static const dMuted = Color(0xFFB6A5AC);
  static const dLine = Color(0xFF3A323B);

  /// The maroon is a **fill** colour, not a text colour, on a dark screen.
  ///
  /// `dBrand` on `dBg` measures 3.3:1 — below the 4.5:1 that small text needs,
  /// and it showed: the language button in the corner was almost invisible in
  /// dark mode. This lighter tint measures 7.9:1 and is used wherever the
  /// brand colour has to be *read* rather than filled.
  static const dBrandInk = Color(0xFFFF7FA3);

  /// Same problem in reverse: gold on white is far too pale to read.
  static const goldInk = Color(0xFF7D6410);
}

/// Which face to use, and how much room to give it.
///
/// The website ships two families and so does the app: **Noto Naskh Arabic**
/// for Arabic and **Poppins** for English. v1 used Naskh for both, and Latin
/// text set in an Arabic naskh face looks thin and slightly wrong — the letters
/// are there, but nobody would choose it to read a paragraph in English.
///
/// The two scripts also want different room. Arabic naskh carries its meaning
/// in marks that sit above and below the line, so it needs a larger size and
/// more leading than Latin at the same nominal point size to be equally
/// comfortable. That is why the sizes below are not the same in both.
class Fonts {
  const Fonts._(this.family, this.fallback, this.scale, this.height);

  static const arabic = Fonts._('Naskh', ['Poppins'], 1.06, 1.85);
  static const latin = Fonts._('Poppins', ['Naskh'], 1.0, 1.55);

  static Fonts of(bool isArabic) => isArabic ? arabic : latin;

  final String family;
  final List<String> fallback;

  /// Arabic is set slightly larger for the same perceived size.
  final double scale;

  /// Line height for body copy.
  final double height;

  double size(double base) => base * scale;
}

ThemeData buildTheme({required bool dark, required bool arabic}) {
  final f = Fonts.of(arabic);

  final scheme = dark
      ? const ColorScheme.dark(
          primary: Brand.dBrand,
          onPrimary: Colors.white,
          secondary: Brand.dGold,
          surface: Brand.dCard,
          error: Brand.red,
        )
      : const ColorScheme.light(
          primary: Brand.brand,
          onPrimary: Colors.white,
          secondary: Brand.gold,
          surface: Brand.card,
          error: Brand.red,
        );

  final ink = dark ? Brand.dInk : Brand.ink;
  final muted = dark ? Brand.dMuted : Brand.muted;
  final line = dark ? Brand.dLine : Brand.line;
  final field = dark ? Brand.dSurface2 : Brand.surface2;

  TextStyle t(
    double size, {
    FontWeight weight = FontWeight.w400,
    Color? color,
    double? height,
  }) =>
      TextStyle(
        fontFamily: f.family,
        fontFamilyFallback: f.fallback,
        fontSize: f.size(size),
        fontWeight: weight,
        color: color ?? ink,
        height: height ?? f.height,
      );

  OutlineInputBorder border(Color c, [double w = 1]) => OutlineInputBorder(
        borderRadius: BorderRadius.circular(14),
        borderSide: BorderSide(color: c, width: w),
      );

  final textTheme = TextTheme(
    headlineSmall: t(23, weight: FontWeight.w700, height: 1.4),
    titleLarge: t(19, weight: FontWeight.w700, height: 1.45),
    titleMedium: t(16.5, weight: FontWeight.w700, height: 1.45),
    titleSmall: t(15, weight: FontWeight.w700, height: 1.45),
    bodyLarge: t(16),
    bodyMedium: t(15),
    // Small print is where an unreadable theme actually bites: hints, counters,
    // the privacy note. It is one step larger than Material's default and, in
    // dark mode, uses a lighter grey than v1 did.
    bodySmall: t(13.5, color: muted, height: 1.6),
    labelLarge: t(16, weight: FontWeight.w700, height: 1.2),
    labelMedium: t(12.5, weight: FontWeight.w700, height: 1.2),
    labelSmall: t(11.5, weight: FontWeight.w600, height: 1.2),
  );

  return ThemeData(
    useMaterial3: true,
    colorScheme: scheme,
    scaffoldBackgroundColor: dark ? Brand.dBg : Brand.bg,
    fontFamily: f.family,
    fontFamilyFallback: f.fallback,
    textTheme: textTheme,
    cardTheme: CardThemeData(
      color: dark ? Brand.dCard : Brand.card,
      elevation: 0,
      margin: EdgeInsets.zero,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(16),
        side: BorderSide(color: line),
      ),
    ),
    appBarTheme: AppBarTheme(
      backgroundColor: dark ? Brand.dBg : Brand.bg,
      foregroundColor: ink,
      elevation: 0,
      scrolledUnderElevation: 0.5,
      centerTitle: true,
      titleTextStyle: textTheme.titleLarge,
    ),
    inputDecorationTheme: InputDecorationTheme(
      filled: true,
      fillColor: field,
      contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 16),
      border: border(line),
      enabledBorder: border(line),
      focusedBorder: border(dark ? Brand.dBrandInk : Brand.brand, 1.8),
      errorBorder: border(Brand.red),
      focusedErrorBorder: border(Brand.red, 1.8),
      labelStyle: textTheme.bodyMedium?.copyWith(color: muted),

      /// The name of the field, once it has floated above the box.
      ///
      /// It used to be the brand pink on every field all of the time. Pink on
      /// a near-black screen at label size is a colour you can see but not
      /// comfortably read, and eight of them stacked down a form read as
      /// decoration rather than as the questions being asked.
      ///
      /// Now the label is simply the text colour — white on dark, near-black
      /// on light — at a size that can be read at arm's length, and it turns
      /// brand pink only on the field the customer is actually in. The colour
      /// went from being everywhere, meaning nothing, to marking one thing.
      floatingLabelStyle: WidgetStateTextStyle.resolveWith((states) {
        final focused = states.contains(WidgetState.focused);
        final errored = states.contains(WidgetState.error);
        return TextStyle(
          fontFamily: f.family,
          fontFamilyFallback: f.fallback,
          fontSize: f.size(14),
          fontWeight: FontWeight.w700,
          height: 1.2,
          color: errored
              ? Brand.red
              : focused
                  ? (dark ? Brand.dBrandInk : Brand.brand)
                  : ink,
        );
      }),
      hintStyle: textTheme.bodyMedium?.copyWith(color: muted),
      // Typed answers should look more solid than the labels around them.
      // 16 px is also the size below which iOS zooms the page on focus.
      suffixStyle: textTheme.bodyMedium,
    ),
    // Arabic and Latin both need a comfortable target; 52 is the site's.
    filledButtonTheme: FilledButtonThemeData(
      style: FilledButton.styleFrom(
        minimumSize: const Size.fromHeight(54),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
        textStyle: textTheme.labelLarge,
      ),
    ),
    outlinedButtonTheme: OutlinedButtonThemeData(
      style: OutlinedButton.styleFrom(
        minimumSize: const Size.fromHeight(54),
        side: BorderSide(color: line),
        foregroundColor: ink,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
        textStyle: textTheme.labelLarge,
      ),
    ),
    textButtonTheme: TextButtonThemeData(
      style: TextButton.styleFrom(
        // Not the fill colour — see Brand.dBrandInk.
        foregroundColor: dark ? Brand.dBrandInk : Brand.brand,
        textStyle: textTheme.labelLarge,
      ),
    ),
    navigationBarTheme: NavigationBarThemeData(
      backgroundColor: dark ? Brand.dCard : Brand.card,
      indicatorColor: dark ? Brand.dTint : Brand.tint,
      height: 68,
      labelTextStyle: WidgetStateProperty.resolveWith(
        (states) => textTheme.labelMedium?.copyWith(
          color: states.contains(WidgetState.selected)
              ? (dark ? Brand.dBrandInk : Brand.brand)
              : muted,
        ),
      ),
      iconTheme: WidgetStateProperty.resolveWith(
        (states) => IconThemeData(
          size: 24,
          color: states.contains(WidgetState.selected)
              ? (dark ? Brand.dBrandInk : Brand.brand)
              : muted,
        ),
      ),
    ),
    chipTheme: ChipThemeData(
      labelStyle: textTheme.labelLarge?.copyWith(fontSize: f.size(14)),
      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 8),
    ),
    dividerTheme: DividerThemeData(color: line, thickness: 1, space: 1),
    snackBarTheme: SnackBarThemeData(
      behavior: SnackBarBehavior.floating,
      insetPadding: const EdgeInsets.all(16),
      contentTextStyle: textTheme.bodyMedium?.copyWith(color: Colors.white),
    ),
    listTileTheme: ListTileThemeData(
      titleTextStyle: textTheme.bodyLarge,
      subtitleTextStyle: textTheme.bodySmall,
      iconColor: muted,
    ),
  );
}
