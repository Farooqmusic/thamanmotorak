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
  static const muted = Color(0xFF7C6A71);
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
  static const dMuted = Color(0xFFA9979F);
  static const dLine = Color(0xFF332C34);
}

/// One radius, one font, two schemes.
///
/// `Naskh` is the same Noto Naskh Arabic the site and the PDF use. It is set
/// for Latin text as well: an app that switches typeface when the language
/// changes feels like two different apps.
ThemeData buildTheme({required bool dark}) {
  final scheme = dark
      ? const ColorScheme.dark(
          primary: Brand.dBrand,
          secondary: Brand.dGold,
          surface: Brand.dCard,
          error: Brand.red,
        )
      : const ColorScheme.light(
          primary: Brand.brand,
          secondary: Brand.gold,
          surface: Brand.card,
          error: Brand.red,
        );

  final ink = dark ? Brand.dInk : Brand.ink;
  final muted = dark ? Brand.dMuted : Brand.muted;
  final line = dark ? Brand.dLine : Brand.line;
  final field = dark ? Brand.dSurface2 : Brand.surface2;

  OutlineInputBorder border(Color c, [double w = 1]) => OutlineInputBorder(
        borderRadius: BorderRadius.circular(14),
        borderSide: BorderSide(color: c, width: w),
      );

  return ThemeData(
    useMaterial3: true,
    colorScheme: scheme,
    scaffoldBackgroundColor: dark ? Brand.dBg : Brand.bg,
    fontFamily: 'Naskh',
    textTheme: TextTheme(
      headlineSmall: TextStyle(fontWeight: FontWeight.w700, color: ink, height: 1.35),
      titleMedium: TextStyle(fontWeight: FontWeight.w700, color: ink, height: 1.4),
      bodyLarge: TextStyle(color: ink, height: 1.55),
      bodyMedium: TextStyle(color: ink, height: 1.55),
      bodySmall: TextStyle(color: muted, height: 1.5),
      labelLarge: const TextStyle(fontWeight: FontWeight.w700),
    ),
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
      titleTextStyle: TextStyle(
        fontFamily: 'Naskh',
        fontSize: 18,
        fontWeight: FontWeight.w700,
        color: ink,
      ),
    ),
    inputDecorationTheme: InputDecorationTheme(
      filled: true,
      fillColor: field,
      contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
      border: border(line),
      enabledBorder: border(line),
      focusedBorder: border(dark ? Brand.dBrand : Brand.brand, 1.6),
      errorBorder: border(Brand.red),
      focusedErrorBorder: border(Brand.red, 1.6),
      labelStyle: TextStyle(color: muted),
      hintStyle: TextStyle(color: muted),
    ),
    filledButtonTheme: FilledButtonThemeData(
      style: FilledButton.styleFrom(
        minimumSize: const Size.fromHeight(52),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
        textStyle: const TextStyle(
          fontFamily: 'Naskh',
          fontSize: 16,
          fontWeight: FontWeight.w700,
        ),
      ),
    ),
    outlinedButtonTheme: OutlinedButtonThemeData(
      style: OutlinedButton.styleFrom(
        minimumSize: const Size.fromHeight(52),
        side: BorderSide(color: line),
        foregroundColor: ink,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
        textStyle: const TextStyle(
          fontFamily: 'Naskh',
          fontSize: 16,
          fontWeight: FontWeight.w700,
        ),
      ),
    ),
    dividerTheme: DividerThemeData(color: line, thickness: 1, space: 1),
    snackBarTheme: const SnackBarThemeData(
      behavior: SnackBarBehavior.floating,
      insetPadding: EdgeInsets.all(16),
    ),
  );
}
