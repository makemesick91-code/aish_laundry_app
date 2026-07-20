import 'package:flutter/material.dart';

import '../generated/tokens.dart';

/// The canonical light theme.
///
/// A [ThemeData] built ENTIRELY from generated tokens. No value below is typed
/// by hand; each one names the token it came from, so a change to the token JSON
/// propagates rather than needing to be chased through widgets.
///
/// There is no `dark()` factory. Dark mode is DEFERRED, and providing an empty
/// or approximated dark theme would let a surface opt into something that was
/// never specified (Rule 26 rule 6).
abstract final class AishTheme {
  /// Minimum interactive target on every surface and every role, in logical
  /// pixels. Exposed so a widget cannot re-derive it from a guess.
  static double get minimumTouchTarget => AishSizing.sizeTouchMin;

  static ThemeData light() {
    final colorScheme = ColorScheme(
      brightness: Brightness.light,
      primary: AishSemanticColors.colorSemanticPrimary,
      onPrimary: AishSemanticColors.colorSemanticTextOnPrimary,
      primaryContainer: AishSemanticColors.colorSemanticPrimarySurface,
      onPrimaryContainer: AishSemanticColors.colorSemanticTextPrimary,
      secondary: AishSemanticColors.colorSemanticSecondary,
      onSecondary: AishSemanticColors.colorSemanticTextOnPrimary,
      tertiary: AishSemanticColors.colorSemanticAccent,
      onTertiary: AishSemanticColors.colorSemanticTextPrimary,
      error: AishSemanticColors.colorSemanticDanger,
      onError: AishSemanticColors.colorSemanticTextOnPrimary,
      errorContainer: AishSemanticColors.colorSemanticDangerSurface,
      onErrorContainer: AishSemanticColors.colorSemanticTextPrimary,
      surface: AishSemanticColors.colorSemanticSurfacePage,
      onSurface: AishSemanticColors.colorSemanticTextPrimary,
      surfaceContainerHighest: AishSemanticColors.colorSemanticSurfaceSunken,
      onSurfaceVariant: AishSemanticColors.colorSemanticTextSecondary,
      outline: AishSemanticColors.colorSemanticBorder,
      outlineVariant: AishSemanticColors.colorSemanticBorderSubtle,
      inverseSurface: AishSemanticColors.colorSemanticSurfaceInverse,
      onInverseSurface: AishSemanticColors.colorSemanticTextInverse,
    );

    return ThemeData(
      useMaterial3: true,
      colorScheme: colorScheme,
      scaffoldBackgroundColor: AishSemanticColors.colorSemanticSurfacePage,
      // System-first typography: no font binary is committed, so the platform
      // face is used and only the metrics are ours.
      fontFamilyFallback: AishTypography.fontFamilySans,
      textTheme: _textTheme(),
      // Every tappable control gets the 48x48 hit area, at the theme level,
      // so an individual widget cannot quietly opt out of it.
      materialTapTargetSize: MaterialTapTargetSize.padded,
      visualDensity: VisualDensity.standard,
      appBarTheme: AppBarTheme(
        backgroundColor: AishSemanticColors.colorSemanticSurfacePage,
        foregroundColor: AishSemanticColors.colorSemanticTextPrimary,
        surfaceTintColor: Colors.transparent,
        toolbarHeight: AishSizing.sizeAppbarHeight,
        elevation: 0,
        titleTextStyle: TextStyle(
          color: AishSemanticColors.colorSemanticTextPrimary,
          fontSize: AishTypography.fontSizeTitleMd,
          height:
              AishTypography.fontLineHeightTitleMd /
              AishTypography.fontSizeTitleMd,
          fontWeight: FontWeight.w600,
          fontFamilyFallback: AishTypography.fontFamilySans,
        ),
      ),
      dividerTheme: DividerThemeData(
        color: AishSemanticColors.colorSemanticBorderSubtle,
        thickness: AishBorders.borderWidthHairline,
        space: AishSpacing.space4,
      ),
      cardTheme: CardThemeData(
        color: AishSemanticColors.colorSemanticSurfaceRaised,
        elevation: 0,
        margin: EdgeInsets.zero,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(AishRadius.radiusMd),
          side: BorderSide(
            color: AishSemanticColors.colorSemanticBorderSubtle,
            width: AishBorders.borderWidthHairline,
          ),
        ),
      ),
      filledButtonTheme: FilledButtonThemeData(style: _primaryButtonStyle()),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: _secondaryButtonStyle(),
      ),
      textButtonTheme: TextButtonThemeData(style: _textButtonStyle()),
      inputDecorationTheme: _inputTheme(),
      listTileTheme: ListTileThemeData(
        minTileHeight: AishSizing.sizeTouchMin,
        contentPadding: EdgeInsets.symmetric(
          horizontal: AishSpacing.space4,
          vertical: AishSpacing.space2,
        ),
      ),
      // Motion is restrained by policy, not by taste. Nothing here communicates
      // state; transitions only aid comprehension.
      pageTransitionsTheme: const PageTransitionsTheme(
        builders: <TargetPlatform, PageTransitionsBuilder>{
          TargetPlatform.android: FadeForwardsPageTransitionsBuilder(),
        },
      ),
    );
  }

  static TextTheme _textTheme() {
    TextStyle style(double size, double lineHeight, FontWeight weight) =>
        TextStyle(
          fontSize: size,
          height: lineHeight / size,
          fontWeight: weight,
          color: AishSemanticColors.colorSemanticTextPrimary,
          fontFamilyFallback: AishTypography.fontFamilySans,
        );

    return TextTheme(
      displayLarge: style(
        AishTypography.fontSizeDisplayLg,
        AishTypography.fontLineHeightDisplayLg,
        FontWeight.w700,
      ),
      displayMedium: style(
        AishTypography.fontSizeDisplayMd,
        AishTypography.fontLineHeightDisplayMd,
        FontWeight.w700,
      ),
      headlineLarge: style(
        AishTypography.fontSizeHeadlineLg,
        AishTypography.fontLineHeightHeadlineLg,
        FontWeight.w600,
      ),
      headlineMedium: style(
        AishTypography.fontSizeHeadlineMd,
        AishTypography.fontLineHeightHeadlineMd,
        FontWeight.w600,
      ),
      headlineSmall: style(
        AishTypography.fontSizeHeadlineSm,
        AishTypography.fontLineHeightHeadlineSm,
        FontWeight.w600,
      ),
      titleLarge: style(
        AishTypography.fontSizeTitleLg,
        AishTypography.fontLineHeightTitleLg,
        FontWeight.w600,
      ),
      titleMedium: style(
        AishTypography.fontSizeTitleMd,
        AishTypography.fontLineHeightTitleMd,
        FontWeight.w600,
      ),
      titleSmall: style(
        AishTypography.fontSizeTitleSm,
        AishTypography.fontLineHeightTitleSm,
        FontWeight.w600,
      ),
      bodyLarge: style(
        AishTypography.fontSizeBodyLg,
        AishTypography.fontLineHeightBodyLg,
        FontWeight.w400,
      ),
      bodyMedium: style(
        AishTypography.fontSizeBodyMd,
        AishTypography.fontLineHeightBodyMd,
        FontWeight.w400,
      ),
      bodySmall: style(
        AishTypography.fontSizeBodySm,
        AishTypography.fontLineHeightBodySm,
        FontWeight.w400,
      ),
      labelLarge: style(
        AishTypography.fontSizeLabelLg,
        AishTypography.fontLineHeightLabelLg,
        FontWeight.w500,
      ),
      labelMedium: style(
        AishTypography.fontSizeLabelMd,
        AishTypography.fontLineHeightLabelMd,
        FontWeight.w500,
      ),
      labelSmall: style(
        AishTypography.fontSizeLabelSm,
        AishTypography.fontLineHeightLabelSm,
        FontWeight.w500,
      ),
    );
  }

  static ButtonStyle _primaryButtonStyle() => ButtonStyle(
    minimumSize: WidgetStatePropertyAll<Size>(
      Size(AishSizing.sizeTouchMin * 2, AishSizing.sizeControlLg),
    ),
    backgroundColor: WidgetStateProperty.resolveWith<Color>((states) {
      if (states.contains(WidgetState.disabled)) {
        return AishSemanticColors.colorSemanticDisabled;
      }
      if (states.contains(WidgetState.pressed)) {
        return AishSemanticColors.colorSemanticPrimaryPressed;
      }
      if (states.contains(WidgetState.hovered)) {
        return AishSemanticColors.colorSemanticPrimaryHover;
      }
      return AishSemanticColors.colorSemanticPrimary;
    }),
    foregroundColor: WidgetStatePropertyAll<Color>(
      AishSemanticColors.colorSemanticTextOnPrimary,
    ),
    shape: WidgetStatePropertyAll<OutlinedBorder>(
      RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(AishRadius.radiusMd),
      ),
    ),
    // A focus indicator is REQUIRED and is never removed for aesthetics.
    // It is what makes keyboard operation possible on Console Web.
    side: WidgetStateProperty.resolveWith<BorderSide?>((states) {
      if (states.contains(WidgetState.focused)) {
        return BorderSide(
          color: AishSemanticColors.colorSemanticFocus,
          width: AishBorders.borderWidthFocus,
        );
      }
      return null;
    }),
  );

  static ButtonStyle _secondaryButtonStyle() => ButtonStyle(
    minimumSize: WidgetStatePropertyAll<Size>(
      Size(AishSizing.sizeTouchMin * 2, AishSizing.sizeControlLg),
    ),
    foregroundColor: WidgetStatePropertyAll<Color>(
      AishSemanticColors.colorSemanticPrimary,
    ),
    side: WidgetStateProperty.resolveWith<BorderSide>((states) {
      if (states.contains(WidgetState.focused)) {
        return BorderSide(
          color: AishSemanticColors.colorSemanticFocus,
          width: AishBorders.borderWidthFocus,
        );
      }
      return BorderSide(
        color: AishSemanticColors.colorSemanticBorderInteractive,
        width: AishBorders.borderWidthHairline,
      );
    }),
    shape: WidgetStatePropertyAll<OutlinedBorder>(
      RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(AishRadius.radiusMd),
      ),
    ),
  );

  static ButtonStyle _textButtonStyle() => ButtonStyle(
    minimumSize: WidgetStatePropertyAll<Size>(
      Size(AishSizing.sizeTouchMin, AishSizing.sizeTouchMin),
    ),
    foregroundColor: WidgetStatePropertyAll<Color>(
      AishSemanticColors.colorSemanticPrimary,
    ),
  );

  static InputDecorationTheme _inputTheme() => InputDecorationTheme(
    filled: true,
    fillColor: AishSemanticColors.colorSemanticSurfacePage,
    contentPadding: EdgeInsets.symmetric(
      horizontal: AishSpacing.space4,
      vertical: AishSpacing.space3,
    ),
    border: OutlineInputBorder(
      borderRadius: BorderRadius.circular(AishRadius.radiusMd),
      borderSide: BorderSide(
        color: AishSemanticColors.colorSemanticBorder,
        width: AishBorders.borderWidthHairline,
      ),
    ),
    enabledBorder: OutlineInputBorder(
      borderRadius: BorderRadius.circular(AishRadius.radiusMd),
      borderSide: BorderSide(
        color: AishSemanticColors.colorSemanticBorder,
        width: AishBorders.borderWidthHairline,
      ),
    ),
    focusedBorder: OutlineInputBorder(
      borderRadius: BorderRadius.circular(AishRadius.radiusMd),
      borderSide: BorderSide(
        color: AishSemanticColors.colorSemanticFocus,
        width: AishBorders.borderWidthFocus,
      ),
    ),
    errorBorder: OutlineInputBorder(
      borderRadius: BorderRadius.circular(AishRadius.radiusMd),
      borderSide: BorderSide(
        color: AishSemanticColors.colorSemanticDanger,
        width: AishBorders.borderWidthThick,
      ),
    ),
  );
}
