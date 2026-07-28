import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

class AppTheme {
  // Brand Colors
  static const Color primaryPurple = Color(0xFF6C5CE7);
  static const Color secondaryPurple = Color(0xFFa29bfe);
  static const Color accentIndigo = Color(0xFF3F51B5);
  static const Color accentTeal = Color(0xFF00D2D3);
  static const Color priorityOrange = Color(0xFFFF7675);
  
  // Neutral Colors (Dark Mode)
  static const Color obsidianBlack = Color(0xFF090A0F);
  static const Color slateCard = Color(0xFF161925);
  static const Color slateBorder = Color(0xFF262A3C);
  static const Color textPrimaryDark = Color(0xFFF1F2F6);
  static const Color textSecondaryDark = Color(0xFFA4B0BE);

  // Neutral Colors (Light Mode)
  static const Color iceWhite = Color(0xFFF8F9FA);
  static const Color pureWhite = Color(0xFFFFFFFF);
  static const Color lightCard = Color(0xFFF1F2F6);
  static const Color lightBorder = Color(0xFFDFE4EA);
  static const Color textPrimaryLight = Color(0xFF2F3542);
  static const Color textSecondaryLight = Color(0xFF747D8C);

  // Gradients
  static const LinearGradient premiumPurpleGradient = LinearGradient(
    colors: [primaryPurple, Color(0xFF8E2DE2)],
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
  );

  static const LinearGradient premiumDarkGradient = LinearGradient(
    colors: [obsidianBlack, Color(0xFF1F1C2C)],
    begin: Alignment.topCenter,
    end: Alignment.bottomCenter,
  );

  static const LinearGradient glassmorphismGradient = LinearGradient(
    colors: [
      Color(0x1AFFFFFF),
      Color(0x0DFFFFFF),
    ],
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
  );

  static LinearGradient bgGradient(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    return isDark ? premiumDarkGradient : const LinearGradient(
      colors: [iceWhite, pureWhite],
      begin: Alignment.topCenter,
      end: Alignment.bottomCenter,
    );
  }

  static BoxDecoration glassBoxAdaptive(BuildContext context, {
    double borderRadius = 16.0,
  }) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    return BoxDecoration(
      color: isDark ? Colors.white.withOpacity(0.06) : Colors.black.withOpacity(0.03),
      borderRadius: BorderRadius.circular(borderRadius),
      border: Border.all(
        color: isDark ? Colors.white.withOpacity(0.12) : Colors.black.withOpacity(0.06),
        width: 1.0,
      ),
      boxShadow: [
        BoxShadow(
          color: isDark ? Colors.black.withOpacity(0.15) : Colors.black.withOpacity(0.02),
          blurRadius: 10.0,
          spreadRadius: -2.0,
        ),
      ],
    );
  }

  // Glassmorphism Box Decoration
  static BoxDecoration glassBox({
    Color? borderStyleColor,
    double borderRadius = 16.0,
  }) {
    return BoxDecoration(
      color: Colors.white.withOpacity(0.06),
      borderRadius: BorderRadius.circular(borderRadius),
      border: Border.all(
        color: borderStyleColor ?? Colors.white.withOpacity(0.12),
        width: 1.0,
      ),
      boxShadow: [
        BoxShadow(
          color: Colors.black.withOpacity(0.15),
          blurRadius: 10.0,
          spreadRadius: -2.0,
        ),
      ],
    );
  }

  // Light Theme
  static ThemeData get lightTheme {
    return ThemeData(
      useMaterial3: true,
      brightness: Brightness.light,
      colorScheme: ColorScheme.fromSeed(
        seedColor: primaryPurple,
        brightness: Brightness.light,
        primary: primaryPurple,
        secondary: secondaryPurple,
        background: iceWhite,
        surface: pureWhite,
      ),
      scaffoldBackgroundColor: iceWhite,
      cardColor: pureWhite,
      appBarTheme: const AppBarTheme(
        backgroundColor: iceWhite,
        elevation: 0,
        iconTheme: IconThemeData(color: textPrimaryLight),
        titleTextStyle: TextStyle(
          color: textPrimaryLight,
          fontSize: 20,
          fontWeight: FontWeight.bold,
        ),
      ),
      textTheme: GoogleFonts.outfitTextTheme().copyWith(
        displayLarge: GoogleFonts.outfit(
          fontSize: 32,
          fontWeight: FontWeight.bold,
          color: textPrimaryLight,
        ),
        titleLarge: GoogleFonts.outfit(
          fontSize: 20,
          fontWeight: FontWeight.w600,
          color: textPrimaryLight,
        ),
        bodyLarge: GoogleFonts.inter(
          fontSize: 16,
          fontWeight: FontWeight.normal,
          color: textPrimaryLight,
        ),
        bodyMedium: GoogleFonts.inter(
          fontSize: 14,
          fontWeight: FontWeight.normal,
          color: textSecondaryLight,
        ),
      ),
      cardTheme: CardThemeData(
        color: pureWhite,
        elevation: 2,
        shadowColor: Colors.black12,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(16),
          side: const BorderSide(color: lightBorder),
        ),
      ),
    );
  }

  // Dark Theme
  static ThemeData get darkTheme {
    return ThemeData(
      useMaterial3: true,
      brightness: Brightness.dark,
      colorScheme: ColorScheme.fromSeed(
        seedColor: primaryPurple,
        brightness: Brightness.dark,
        primary: primaryPurple,
        secondary: secondaryPurple,
        background: obsidianBlack,
        surface: slateCard,
      ),
      scaffoldBackgroundColor: obsidianBlack,
      cardColor: slateCard,
      appBarTheme: const AppBarTheme(
        backgroundColor: obsidianBlack,
        elevation: 0,
        iconTheme: IconThemeData(color: textPrimaryDark),
        titleTextStyle: TextStyle(
          color: textPrimaryDark,
          fontSize: 20,
          fontWeight: FontWeight.bold,
        ),
      ),
      textTheme: GoogleFonts.outfitTextTheme(ThemeData.dark().textTheme).copyWith(
        displayLarge: GoogleFonts.outfit(
          fontSize: 32,
          fontWeight: FontWeight.bold,
          color: textPrimaryDark,
        ),
        titleLarge: GoogleFonts.outfit(
          fontSize: 20,
          fontWeight: FontWeight.w600,
          color: textPrimaryDark,
        ),
        bodyLarge: GoogleFonts.inter(
          fontSize: 16,
          fontWeight: FontWeight.normal,
          color: textPrimaryDark,
        ),
        bodyMedium: GoogleFonts.inter(
          fontSize: 14,
          fontWeight: FontWeight.normal,
          color: textSecondaryDark,
        ),
      ),
      cardTheme: CardThemeData(
        color: slateCard,
        elevation: 0,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(16),
          side: const BorderSide(color: slateBorder),
        ),
      ),
    );
  }
}
