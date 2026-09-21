import 'package:flutter/material.dart';

class OvanieColors {
  OvanieColors._();

  static const navy = Color(0xFF041A3A);
  static const navyDark = Color(0xFF021126);
  static const blue = Color(0xFF0759C7);
  static const orange = Color(0xFFFF6500);
  static const background = Color(0xFFF4F7FB);
  static const surface = Colors.white;
  static const text = Color(0xFF13213A);
  static const muted = Color(0xFF6E7B90);
  static const border = Color(0xFFE4EAF2);
  static const success = Color(0xFF16845B);
  static const warning = Color(0xFFF4A100);
  static const danger = Color(0xFFD92D20);
}

class OvanieTheme {
  OvanieTheme._();

  static ThemeData get light {
    final scheme = ColorScheme.fromSeed(
      seedColor: OvanieColors.blue,
      brightness: Brightness.light,
      primary: OvanieColors.blue,
      secondary: OvanieColors.orange,
      surface: OvanieColors.surface,
    );

    return ThemeData(
      useMaterial3: true,
      colorScheme: scheme,
      scaffoldBackgroundColor: OvanieColors.background,
      fontFamily: 'Roboto',
      appBarTheme: const AppBarTheme(
        centerTitle: false,
        elevation: 0,
        backgroundColor: OvanieColors.surface,
        foregroundColor: OvanieColors.text,
        surfaceTintColor: Colors.transparent,
      ),
      cardTheme: CardThemeData(
        color: OvanieColors.surface,
        elevation: 0,
        margin: EdgeInsets.zero,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(18),
          side: const BorderSide(color: OvanieColors.border),
        ),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: Colors.white,
        contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(14),
          borderSide: const BorderSide(color: OvanieColors.border),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(14),
          borderSide: const BorderSide(color: OvanieColors.border),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(14),
          borderSide: const BorderSide(color: OvanieColors.blue, width: 1.4),
        ),
      ),
    );
  }
}
