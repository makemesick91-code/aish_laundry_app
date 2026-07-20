// GENERATED — DO NOT EDIT.
//
// Produced by scripts/generate-design-tokens.py from the canonical token
// JSON under docs/design/tokens/. Edit the JSON and regenerate; an edit made
// here is destroyed by the next run.
//
// Light theme only. Dark mode is DEFERRED — no dark mapping exists.
//
// Source files and their SHA256 at generation time:
//   border.json = 266107ac056598118476c801921989496531fa47c7860835f5a916cf2493f961
//   elevation.json = bad7238c7269482cc16914c9bd3c4411f56a032fd4426d8dc3f5ba0b8740d9f5
//   motion.json = 57b5d26e3acb5ff96686b0640e634b8a2b24413e3a0dfe51d0c0999ea096949f
//   opacity.json = 27d5732837b224b199b7104ff9f345d1e4f30a58193f5f4c154cae6ea1ad174f
//   primitives.json = 796062e1150d1879abd12d19e6988c3baf8bf406975c3871b587fdde6549e36f
//   radius.json = bf3590e3a5c284bf426d5383271c95c71d8352aa02aad80e10fdd203513d7cc8
//   semantic-light.json = 661b4390fe1e3b4435c5efe5a78901854880fa7b099df94eb8bd363b31da7e3a
//   sizing.json = 7bc18901cceb232c5e8eebdcb0ab97a1e90d6338ff3ede2cc3147f5190b7f8d3
//   spacing.json = c181ac1f010cbe53f37803848bba73590232ca31a99bbb668f3d3973bacb41a5
//   typography.json = 0b4ef5d1cf0b6082708a50f9e306f513c464c817f9f02f0a3a3303f5bfbc581a

// ignore_for_file: public_member_api_docs

/// Spacing scale on a strict 4px grid.
abstract final class AishSpacing {
  /// Spacing step 0 on the 4px grid (0px). All spacing derives from this scale;
  /// arbitrary spacing values are forbidden.
  static const double space0 = 0.0;

  /// Spacing step 1 on the 4px grid (4px). All spacing derives from this scale;
  /// arbitrary spacing values are forbidden.
  static const double space1 = 4.0;

  /// Spacing step 2 on the 4px grid (8px). All spacing derives from this scale;
  /// arbitrary spacing values are forbidden.
  static const double space2 = 8.0;

  /// Spacing step 3 on the 4px grid (12px). All spacing derives from this
  /// scale; arbitrary spacing values are forbidden.
  static const double space3 = 12.0;

  /// Spacing step 4 on the 4px grid (16px). All spacing derives from this
  /// scale; arbitrary spacing values are forbidden.
  static const double space4 = 16.0;

  /// Spacing step 5 on the 4px grid (20px). All spacing derives from this
  /// scale; arbitrary spacing values are forbidden.
  static const double space5 = 20.0;

  /// Spacing step 6 on the 4px grid (24px). All spacing derives from this
  /// scale; arbitrary spacing values are forbidden.
  static const double space6 = 24.0;

  /// Spacing step 8 on the 4px grid (32px). All spacing derives from this
  /// scale; arbitrary spacing values are forbidden.
  static const double space8 = 32.0;

  /// Spacing step 10 on the 4px grid (40px). All spacing derives from this
  /// scale; arbitrary spacing values are forbidden.
  static const double space10 = 40.0;

  /// Spacing step 12 on the 4px grid (48px). All spacing derives from this
  /// scale; arbitrary spacing values are forbidden.
  static const double space12 = 48.0;

  /// Spacing step 16 on the 4px grid (64px). All spacing derives from this
  /// scale; arbitrary spacing values are forbidden.
  static const double space16 = 64.0;

  /// Spacing step 20 on the 4px grid (80px). All spacing derives from this
  /// scale; arbitrary spacing values are forbidden.
  static const double space20 = 80.0;

  /// Spacing step 24 on the 4px grid (96px). All spacing derives from this
  /// scale; arbitrary spacing values are forbidden.
  static const double space24 = 96.0;

  /// The base unit of the spacing grid. Every spacing token is an integer
  /// multiple of this value.
  static const double spaceGridBase = 4.0;
}

/// Corner radius scale. Radius is decorative and never conveys state.
abstract final class AishRadius {
  /// Corner radius none. Radius is decorative and never conveys state.
  static const double radiusNone = 0.0;

  /// Corner radius xs. Radius is decorative and never conveys state.
  static const double radiusXs = 2.0;

  /// Corner radius sm. Radius is decorative and never conveys state.
  static const double radiusSm = 4.0;

  /// Corner radius md. Radius is decorative and never conveys state.
  static const double radiusMd = 8.0;

  /// Corner radius lg. Radius is decorative and never conveys state.
  static const double radiusLg = 12.0;

  /// Corner radius xl. Radius is decorative and never conveys state.
  static const double radiusXl = 16.0;

  /// Corner radius pill. Radius is decorative and never conveys state.
  static const double radiusPill = 999.0;

  /// Fully rounded corner for pills, avatars and circular icon buttons. Synonym
  /// of radius.pill; components may name either. Radius is decorative and never
  /// conveys state.
  static const double radiusFull = 999.0;
}

/// Sizing scale, including the non-negotiable 48x48 minimum touch target.
abstract final class AishSizing {
  /// Minimum interactive target on every touch surface, in logical pixels.
  /// Applies to buttons, list rows, checkboxes, chips, icon buttons and any
  /// tappable region — including targets whose painted area is smaller, which
  /// must still expose a 48x48 hit area.
  static const double sizeTouchMin = 48.0;

  /// Minimum gap between two adjacent touch targets, so a mis-tap does not
  /// trigger the wrong action.
  static const double sizeTouchSpacingMin = 8.0;

  /// Minimum interactive target on pointer-driven Console Web surfaces, where a
  /// 48px target would waste dense table space. Console Web rows still expose a
  /// 32px minimum row height.
  static const double sizePointerMin = 24.0;

  /// Icon box size for the sm role. An icon is never the only carrier of
  /// meaning.
  static const double sizeIconSm = 16.0;

  /// Icon box size for the md role. An icon is never the only carrier of
  /// meaning.
  static const double sizeIconMd = 20.0;

  /// Icon box size for the lg role. An icon is never the only carrier of
  /// meaning.
  static const double sizeIconLg = 24.0;

  /// Icon box size for the xl role. An icon is never the only carrier of
  /// meaning.
  static const double sizeIconXl = 32.0;

  /// Control height for the sm role. On touch surfaces only control.lg and
  /// control.xl may be used for primary interactive controls, because they
  /// satisfy size.touch.min.
  static const double sizeControlSm = 32.0;

  /// Control height for the md role. On touch surfaces only control.lg and
  /// control.xl may be used for primary interactive controls, because they
  /// satisfy size.touch.min.
  static const double sizeControlMd = 40.0;

  /// Control height for the lg role. On touch surfaces only control.lg and
  /// control.xl may be used for primary interactive controls, because they
  /// satisfy size.touch.min.
  static const double sizeControlLg = 48.0;

  /// Control height for the xl role. On touch surfaces only control.lg and
  /// control.xl may be used for primary interactive controls, because they
  /// satisfy size.touch.min.
  static const double sizeControlXl = 56.0;

  /// Default avatar diameter.
  static const double sizeAvatarMd = 40.0;

  /// Maximum content width on wide Console Web layouts before the container
  /// centres.
  static const double sizeMaxWidthContent = 1280.0;

  /// Maximum width of a long-form reading column.
  static const double sizeMaxWidthReading = 720.0;

  /// App bar height on the Android surfaces.
  static const double sizeAppbarHeight = 56.0;

  /// App bar height on Console Web, taller to accommodate the tenant and outlet
  /// context bar.
  static const double sizeAppbarHeightWeb = 64.0;

  /// Bottom navigation height. Exceeds size.touch.min so each destination
  /// clears the touch floor with its label.
  static const double sizeBottomnavHeight = 64.0;

  /// Navigation rail width at the medium breakpoint and above.
  static const double sizeNavrailWidth = 80.0;

  /// Expanded side navigation width on Console Web.
  static const double sizeSidenavWidth = 256.0;

  /// Collapsed side navigation width, still wide enough for an icon target and
  /// its focus ring.
  static const double sizeSidenavWidthCollapsed = 72.0;

  /// Maximum bottom sheet width; beyond this the sheet centres rather than
  /// stretching.
  static const double sizeBottomsheetMax = 640.0;

  /// Default dialog width.
  static const double sizeDialogWidth = 560.0;

  /// Narrow dialog width for a single confirmation.
  static const double sizeDialogWidthSm = 400.0;

  /// Compact table row height. Pointer-only: it sits below size.touch.min and
  /// is never used on a touch surface.
  static const double sizeRowCompact = 40.0;

  /// Small avatar diameter, used inside dense lists and tables.
  static const double sizeAvatarSm = 32.0;

  /// Large avatar diameter, used on profile headers.
  static const double sizeAvatarLg = 56.0;

  /// Extra-small icon box, used only inside a dense chip or an inline badge.
  /// Never a tap target on its own.
  static const double sizeIconXs = 12.0;

  /// Extra-small control height for pointer-driven Console Web affordances such
  /// as an inline table filter chip.
  static const double sizeControlXs = 24.0;
}

/// Border widths and focus-ring geometry.
abstract final class AishBorders {
  /// Border width none.
  static const double borderWidthNone = 0.0;

  /// Border width hairline.
  static const double borderWidthHairline = 1.0;

  /// Border width thin.
  static const double borderWidthThin = 1.5;

  /// Border width thick.
  static const double borderWidthThick = 2.0;

  /// Border width focus.
  static const double borderWidthFocus = 2.0;

  /// Offset between a focused element and its focus ring, so the ring stays
  /// visible against a filled control.
  static const double borderFocusOffset = 2.0;

  /// Heavy border for a high-attention container, such as a conflict panel that
  /// must not be skimmed past.
  static const double borderWidthHeavy = 3.0;
}

/// Opacity scale. Opacity never substitutes for a contrast-compliant colour.
abstract final class AishOpacity {
  /// Opacity transparent.
  static const double opacityTransparent = 0.0;

  /// Opacity subtle.
  static const double opacitySubtle = 0.08;

  /// Opacity muted.
  static const double opacityMuted = 0.32;

  /// Opacity disabled.
  static const double opacityDisabled = 0.38;

  /// Opacity scrim.
  static const double opacityScrim = 0.56;

  /// Opacity opaque.
  static const double opacityOpaque = 1.0;
}
