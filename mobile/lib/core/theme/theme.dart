import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

class AppTheme {
  // Brand Color Palette matching Mockups
  static const Color primaryNavy = Color(0xFF0F172A);
  static const Color brandBlue = Color(0xFF2563EB);
  static const Color brandBlueLight = Color(0xFF3B82F6);
  static const Color lightBlueBg = Color(0xFFEFF6FF);
  static const Color lightGreenBg = Color(0xFFECFDF5);
  
  // Priority & Status Colors
  static const Color redPriority = Color(0xFFEF4444);
  static const Color yellowPriority = Color(0xFFF59E0B);
  static const Color greenWhatsApp = Color(0xFF10B981);
  static const Color priorityOrange = Color(0xFFEF4444);

  // Legacy Aliases for Compatibility
  static const Color primaryPurple = brandBlue;
  static const Color secondaryPurple = brandBlueLight;
  static const Color accentTeal = greenWhatsApp;
  static const Color iceWhite = Color(0xFFF8FAFC);
  static const Color pureWhite = Color(0xFFFFFFFF);
  static const Color lightBorder = Color(0xFFE2E8F0);
  static const Color lightCard = Color(0xFFF1F5F9);
  
  // Neutral Colors (Light Mode)
  static const Color bgSlate = Color(0xFFF8FAFC);
  static const Color cardWhite = Color(0xFFFFFFFF);
  static const Color borderSlate = Color(0xFFE2E8F0);
  static const Color textPrimaryLight = Color(0xFF0F172A);
  static const Color textSecondaryLight = Color(0xFF64748B);

  // Neutral Colors (Dark Mode)
  static const Color obsidianBlack = Color(0xFF090A0F);
  static const Color slateCard = Color(0xFF1E293B);
  static const Color slateBorder = Color(0xFF334155);
  static const Color textPrimaryDark = Color(0xFFF8FAFC);
  static const Color textSecondaryDark = Color(0xFF94A3B8);

  static const LinearGradient premiumPurpleGradient = LinearGradient(
    colors: [brandBlue, Color(0xFF8E2DE2)],
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
  );

  static const LinearGradient premiumDarkGradient = LinearGradient(
    colors: [obsidianBlack, Color(0xFF1E293B)],
    begin: Alignment.topCenter,
    end: Alignment.bottomCenter,
  );

  static BoxDecoration glassBox({
    Color? borderStyleColor,
    double borderRadius = 16.0,
  }) {
    return BoxDecoration(
      color: Colors.white.withValues(alpha: 0.06),
      borderRadius: BorderRadius.circular(borderRadius),
      border: Border.all(
        color: borderStyleColor ?? Colors.white.withValues(alpha: 0.12),
        width: 1.0,
      ),
    );
  }

  // Light Theme
  static ThemeData get lightTheme {
    return ThemeData(
      useMaterial3: true,
      brightness: Brightness.light,
      colorScheme: ColorScheme.fromSeed(
        seedColor: brandBlue,
        brightness: Brightness.light,
        primary: brandBlue,
        secondary: primaryNavy,
        background: bgSlate,
        surface: cardWhite,
      ),
      scaffoldBackgroundColor: bgSlate,
      cardColor: cardWhite,
      appBarTheme: const AppBarTheme(
        backgroundColor: bgSlate,
        elevation: 0,
        scrolledUnderElevation: 0,
        centerTitle: false,
        iconTheme: IconThemeData(color: textPrimaryLight),
        titleTextStyle: TextStyle(
          color: textPrimaryLight,
          fontSize: 22,
          fontWeight: FontWeight.bold,
        ),
      ),
      textTheme: GoogleFonts.interTextTheme().copyWith(
        displayLarge: GoogleFonts.inter(
          fontSize: 32,
          fontWeight: FontWeight.bold,
          color: textPrimaryLight,
        ),
        titleLarge: GoogleFonts.inter(
          fontSize: 20,
          fontWeight: FontWeight.w700,
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
        color: cardWhite,
        elevation: 0,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(16),
          side: const BorderSide(color: borderSlate),
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
        seedColor: brandBlue,
        brightness: Brightness.dark,
        primary: brandBlue,
        secondary: primaryNavy,
        background: obsidianBlack,
        surface: slateCard,
      ),
      scaffoldBackgroundColor: obsidianBlack,
      cardColor: slateCard,
      appBarTheme: const AppBarTheme(
        backgroundColor: obsidianBlack,
        elevation: 0,
        scrolledUnderElevation: 0,
        centerTitle: false,
        iconTheme: IconThemeData(color: textPrimaryDark),
        titleTextStyle: TextStyle(
          color: textPrimaryDark,
          fontSize: 22,
          fontWeight: FontWeight.bold,
        ),
      ),
      textTheme: GoogleFonts.interTextTheme(ThemeData.dark().textTheme).copyWith(
        displayLarge: GoogleFonts.inter(
          fontSize: 32,
          fontWeight: FontWeight.bold,
          color: textPrimaryDark,
        ),
        titleLarge: GoogleFonts.inter(
          fontSize: 20,
          fontWeight: FontWeight.w700,
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
