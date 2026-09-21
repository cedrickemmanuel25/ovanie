import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

/// Palette OVANIE Logistics (vert), cohérente avec le portail web
/// `resources/views/logistics` du backend Laravel.
class OvanieColors {
  OvanieColors._();

  static const green = Color(0xFF009653);
  static const greenDark = Color(0xFF00733F);
  static const greenLight = Color(0xFFE6F6EE);
  static const background = Color(0xFFEDFAF3);
  static const surface = Colors.white;
  static const text = Color(0xFF10131B);
  static const muted = Color(0xFF6B7587);
  static const border = Color(0xFFD3DAE4);
  static const danger = Color(0xFFD92D20);
  static const warning = Color(0xFFB07600);
}

class OvanieTheme {
  OvanieTheme._();

  static ThemeData get light {
    final scheme = ColorScheme.fromSeed(
      seedColor: OvanieColors.green,
      brightness: Brightness.light,
      primary: OvanieColors.green,
      secondary: OvanieColors.greenDark,
      surface: OvanieColors.surface,
    );

    final textTheme = GoogleFonts.plusJakartaSansTextTheme();

    return ThemeData(
      useMaterial3: true,
      colorScheme: scheme,
      textTheme: textTheme.apply(
        bodyColor: OvanieColors.text,
        displayColor: OvanieColors.text,
      ),
      fontFamily: GoogleFonts.plusJakartaSans().fontFamily,
      scaffoldBackgroundColor: OvanieColors.background,
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
          borderRadius: BorderRadius.circular(20),
          side: BorderSide(color: OvanieColors.border.withValues(alpha: .6)),
        ),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: Colors.white,
        contentPadding: const EdgeInsets.symmetric(
          horizontal: 18,
          vertical: 17,
        ),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(15),
          borderSide: const BorderSide(color: OvanieColors.border),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(15),
          borderSide: const BorderSide(color: OvanieColors.border),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(15),
          borderSide: const BorderSide(color: OvanieColors.green, width: 1.8),
        ),
        errorBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(15),
          borderSide: const BorderSide(color: OvanieColors.danger, width: 1.4),
        ),
        focusedErrorBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(15),
          borderSide: const BorderSide(color: OvanieColors.danger, width: 1.8),
        ),
        labelStyle: const TextStyle(
          color: OvanieColors.text,
          fontWeight: FontWeight.w700,
          fontSize: 13.5,
        ),
        hintStyle: TextStyle(
          color: OvanieColors.muted.withValues(alpha: .7),
          fontWeight: FontWeight.w500,
        ),
      ),
    );
  }
}
