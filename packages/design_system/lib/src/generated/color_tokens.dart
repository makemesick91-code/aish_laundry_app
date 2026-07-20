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

import 'dart:ui' show Color;

/// Primitive colour ramp. Carries no meaning. A widget must never reference one of these directly — reference AishSemanticColors instead.
abstract final class AishColorPrimitives {
  /// Blue ramp step 50. Brand spine. 700 is the canonical primary. 50-200 are
  /// surfaces and selected backgrounds. 800-900 are pressed states and dark
  /// headers.
  static const Color colorBlue50 = Color(0xFFF0F6FC);

  /// Blue ramp step 100. Brand spine. 700 is the canonical primary. 50-200 are
  /// surfaces and selected backgrounds. 800-900 are pressed states and dark
  /// headers.
  static const Color colorBlue100 = Color(0xFFDBE9F6);

  /// Blue ramp step 200. Brand spine. 700 is the canonical primary. 50-200 are
  /// surfaces and selected backgrounds. 800-900 are pressed states and dark
  /// headers.
  static const Color colorBlue200 = Color(0xFFB7D2EC);

  /// Blue ramp step 300. Brand spine. 700 is the canonical primary. 50-200 are
  /// surfaces and selected backgrounds. 800-900 are pressed states and dark
  /// headers.
  static const Color colorBlue300 = Color(0xFF8AB6DF);

  /// Blue ramp step 400. Brand spine. 700 is the canonical primary. 50-200 are
  /// surfaces and selected backgrounds. 800-900 are pressed states and dark
  /// headers.
  static const Color colorBlue400 = Color(0xFF5A95CC);

  /// Blue ramp step 500. Brand spine. 700 is the canonical primary. 50-200 are
  /// surfaces and selected backgrounds. 800-900 are pressed states and dark
  /// headers.
  static const Color colorBlue500 = Color(0xFF2A6FA9);

  /// Blue ramp step 600. Brand spine. 700 is the canonical primary. 50-200 are
  /// surfaces and selected backgrounds. 800-900 are pressed states and dark
  /// headers.
  static const Color colorBlue600 = Color(0xFF1F5E96);

  /// Blue ramp step 700. Brand spine. 700 is the canonical primary. 50-200 are
  /// surfaces and selected backgrounds. 800-900 are pressed states and dark
  /// headers.
  static const Color colorBlue700 = Color(0xFF0A4F8F);

  /// Blue ramp step 800. Brand spine. 700 is the canonical primary. 50-200 are
  /// surfaces and selected backgrounds. 800-900 are pressed states and dark
  /// headers.
  static const Color colorBlue800 = Color(0xFF083D70);

  /// Blue ramp step 900. Brand spine. 700 is the canonical primary. 50-200 are
  /// surfaces and selected backgrounds. 800-900 are pressed states and dark
  /// headers.
  static const Color colorBlue900 = Color(0xFF0A2540);

  /// Gold ramp step 50. Restrained brand accent. 300-400 are decorative fills
  /// only. 600 is the only gold permitted to carry text or a meaning-bearing
  /// boundary.
  static const Color colorGold50 = Color(0xFFFBF6E9);

  /// Gold ramp step 100. Restrained brand accent. 300-400 are decorative fills
  /// only. 600 is the only gold permitted to carry text or a meaning-bearing
  /// boundary.
  static const Color colorGold100 = Color(0xFFF5E9C4);

  /// Gold ramp step 200. Restrained brand accent. 300-400 are decorative fills
  /// only. 600 is the only gold permitted to carry text or a meaning-bearing
  /// boundary.
  static const Color colorGold200 = Color(0xFFEBD595);

  /// Gold ramp step 300. Restrained brand accent. 300-400 are decorative fills
  /// only. 600 is the only gold permitted to carry text or a meaning-bearing
  /// boundary.
  static const Color colorGold300 = Color(0xFFDDBC5F);

  /// Gold ramp step 400. Restrained brand accent. 300-400 are decorative fills
  /// only. 600 is the only gold permitted to carry text or a meaning-bearing
  /// boundary.
  static const Color colorGold400 = Color(0xFFC79A2B);

  /// Gold ramp step 500. Restrained brand accent. 300-400 are decorative fills
  /// only. 600 is the only gold permitted to carry text or a meaning-bearing
  /// boundary.
  static const Color colorGold500 = Color(0xFFA87F17);

  /// Gold ramp step 600. Restrained brand accent. 300-400 are decorative fills
  /// only. 600 is the only gold permitted to carry text or a meaning-bearing
  /// boundary.
  static const Color colorGold600 = Color(0xFF8A6710);

  /// Gold ramp step 700. Restrained brand accent. 300-400 are decorative fills
  /// only. 600 is the only gold permitted to carry text or a meaning-bearing
  /// boundary.
  static const Color colorGold700 = Color(0xFF6B4F0B);

  /// Neutral ramp step 0. Surfaces, text, borders and dividers. 0 is the
  /// canonical page background.
  static const Color colorNeutral0 = Color(0xFFFFFFFF);

  /// Neutral ramp step 50. Surfaces, text, borders and dividers. 0 is the
  /// canonical page background.
  static const Color colorNeutral50 = Color(0xFFF7F8FA);

  /// Neutral ramp step 100. Surfaces, text, borders and dividers. 0 is the
  /// canonical page background.
  static const Color colorNeutral100 = Color(0xFFEFF1F4);

  /// Neutral ramp step 200. Surfaces, text, borders and dividers. 0 is the
  /// canonical page background.
  static const Color colorNeutral200 = Color(0xFFDFE3E8);

  /// Neutral ramp step 300. Surfaces, text, borders and dividers. 0 is the
  /// canonical page background.
  static const Color colorNeutral300 = Color(0xFFC4CAD3);

  /// Neutral ramp step 400. Surfaces, text, borders and dividers. 0 is the
  /// canonical page background.
  static const Color colorNeutral400 = Color(0xFF9AA3B0);

  /// Neutral ramp step 500. Surfaces, text, borders and dividers. 0 is the
  /// canonical page background.
  static const Color colorNeutral500 = Color(0xFF737D8C);

  /// Neutral ramp step 600. Surfaces, text, borders and dividers. 0 is the
  /// canonical page background.
  static const Color colorNeutral600 = Color(0xFF566070);

  /// Neutral ramp step 700. Surfaces, text, borders and dividers. 0 is the
  /// canonical page background.
  static const Color colorNeutral700 = Color(0xFF3E4756);

  /// Neutral ramp step 800. Surfaces, text, borders and dividers. 0 is the
  /// canonical page background.
  static const Color colorNeutral800 = Color(0xFF2A313D);

  /// Neutral ramp step 900. Surfaces, text, borders and dividers. 0 is the
  /// canonical page background.
  static const Color colorNeutral900 = Color(0xFF171C24);

  /// Green ramp step 50. Success semantics only. Never a decorative fill.
  static const Color colorGreen50 = Color(0xFFE8F5EC);

  /// Green ramp step 100. Success semantics only. Never a decorative fill.
  static const Color colorGreen100 = Color(0xFFC2E5CE);

  /// Green ramp step 500. Success semantics only. Never a decorative fill.
  static const Color colorGreen500 = Color(0xFF0F7A3D);

  /// Green ramp step 600. Success semantics only. Never a decorative fill.
  static const Color colorGreen600 = Color(0xFF0B5C2E);

  /// Green ramp step 700. Success semantics only. Never a decorative fill.
  static const Color colorGreen700 = Color(0xFF08431F);

  /// Amber ramp step 50. Warning semantics only. Never the sole indicator of a
  /// warning.
  static const Color colorAmber50 = Color(0xFFFDF3E4);

  /// Amber ramp step 100. Warning semantics only. Never the sole indicator of a
  /// warning.
  static const Color colorAmber100 = Color(0xFFF8E0B8);

  /// Amber ramp step 500. Warning semantics only. Never the sole indicator of a
  /// warning.
  static const Color colorAmber500 = Color(0xFF9A5B00);

  /// Amber ramp step 600. Warning semantics only. Never the sole indicator of a
  /// warning.
  static const Color colorAmber600 = Color(0xFF7A4800);

  /// Amber ramp step 700. Warning semantics only. Never the sole indicator of a
  /// warning.
  static const Color colorAmber700 = Color(0xFF5C3600);

  /// Red ramp step 50. Danger and destructive semantics only. Never a
  /// decorative fill.
  static const Color colorRed50 = Color(0xFFFCEBEA);

  /// Red ramp step 100. Danger and destructive semantics only. Never a
  /// decorative fill.
  static const Color colorRed100 = Color(0xFFF7C9C5);

  /// Red ramp step 500. Danger and destructive semantics only. Never a
  /// decorative fill.
  static const Color colorRed500 = Color(0xFFB3261E);

  /// Red ramp step 600. Danger and destructive semantics only. Never a
  /// decorative fill.
  static const Color colorRed600 = Color(0xFF8C1D17);

  /// Red ramp step 700. Danger and destructive semantics only. Never a
  /// decorative fill.
  static const Color colorRed700 = Color(0xFF6B1611);

  /// Teal ramp step 50. Synchronisation-in-progress semantics only.
  static const Color colorTeal50 = Color(0xFFE6F3F5);

  /// Teal ramp step 100. Synchronisation-in-progress semantics only.
  static const Color colorTeal100 = Color(0xFFBCDFE4);

  /// Teal ramp step 500. Synchronisation-in-progress semantics only.
  static const Color colorTeal500 = Color(0xFF0F6E7B);

  /// Teal ramp step 600. Synchronisation-in-progress semantics only.
  static const Color colorTeal600 = Color(0xFF0B535D);

  /// Teal ramp step 700. Synchronisation-in-progress semantics only.
  static const Color colorTeal700 = Color(0xFF083E45);

  /// Violet ramp step 50. Conflict semantics only. Chosen to be distinguishable
  /// from warning and danger for the most common forms of colour vision
  /// deficiency.
  static const Color colorViolet50 = Color(0xFFF3EBF6);

  /// Violet ramp step 100. Conflict semantics only. Chosen to be
  /// distinguishable from warning and danger for the most common forms of
  /// colour vision deficiency.
  static const Color colorViolet100 = Color(0xFFE0C9E8);

  /// Violet ramp step 500. Conflict semantics only. Chosen to be
  /// distinguishable from warning and danger for the most common forms of
  /// colour vision deficiency.
  static const Color colorViolet500 = Color(0xFF7A3B8F);

  /// Violet ramp step 600. Conflict semantics only. Chosen to be
  /// distinguishable from warning and danger for the most common forms of
  /// colour vision deficiency.
  static const Color colorViolet600 = Color(0xFF5E2D6E);

  /// Violet ramp step 700. Conflict semantics only. Chosen to be
  /// distinguishable from warning and danger for the most common forms of
  /// colour vision deficiency.
  static const Color colorViolet700 = Color(0xFF46224F);
}

/// Semantic colour roles for the canonical light theme. This is the only colour surface a widget may reference.
abstract final class AishSemanticColors {
  /// Primary actions, active navigation, primary buttons, focused field
  /// borders.
  static const Color colorSemanticPrimary = Color(0xFF0A4F8F);

  /// Hover and pressed treatment for primary actions on pointer devices.
  static const Color colorSemanticPrimaryHover = Color(0xFF083D70);

  /// Tinted surface behind primary-flavoured content, selected rows, and active
  /// navigation items.
  static const Color colorSemanticPrimarySurface = Color(0xFFF0F6FC);

  /// Secondary emphasis, links inside dense content, secondary buttons.
  static const Color colorSemanticSecondary = Color(0xFF2A6FA9);

  /// Restrained brand accent: a thin rule, a small badge fill, a wordmark
  /// flourish.
  static const Color colorSemanticAccent = Color(0xFFC79A2B);

  /// The only gold permitted to carry text or a meaning-bearing boundary.
  static const Color colorSemanticAccentStrong = Color(0xFF8A6710);

  /// Successful completion: payment recorded, sync acknowledged by the server,
  /// QC passed.
  static const Color colorSemanticSuccess = Color(0xFF0F7A3D);

  /// Background of success banners and success status chips.
  static const Color colorSemanticSuccessSurface = Color(0xFFE8F5EC);

  /// Attention needed but not yet failed: approaching a limit, order ageing,
  /// reminder due.
  static const Color colorSemanticWarning = Color(0xFF9A5B00);

  /// Background of warning banners and warning status chips.
  static const Color colorSemanticWarningSurface = Color(0xFFFDF3E4);

  /// Destructive and failed states: void, refund, failed delivery, failed sync,
  /// validation error.
  static const Color colorSemanticDanger = Color(0xFFB3261E);

  /// Background of error banners and danger status chips.
  static const Color colorSemanticDangerSurface = Color(0xFFFCEBEA);

  /// Neutral informational messages and help text that must be noticed.
  static const Color colorSemanticInformation = Color(0xFF1F5E96);

  /// Background of informational banners.
  static const Color colorSemanticInformationSurface = Color(0xFFDBE9F6);

  /// Neutral status and secondary metadata that must remain readable.
  static const Color colorSemanticNeutral = Color(0xFF566070);

  /// The focus ring. Rendered as a 2px outline with a 2px offset on every
  /// focusable element.
  static const Color colorSemanticFocus = Color(0xFF0A4F8F);

  /// Selection state for rows, chips, list items and segmented controls.
  static const Color colorSemanticSelected = Color(0xFF1F5E96);

  /// Background fill of a selected row or chip.
  static const Color colorSemanticSelectedSurface = Color(0xFFDBE9F6);

  /// Inactive controls and their labels.
  static const Color colorSemanticDisabled = Color(0xFF9AA3B0);

  /// The device has no usable connection. Distinct from PENDING, SYNCING and
  /// FAILED.
  static const Color colorSemanticOffline = Color(0xFF3E4756);

  /// A queued operation is currently being sent to the server. Not yet
  /// acknowledged.
  static const Color colorSemanticSyncing = Color(0xFF0F6E7B);

  /// Background of the sync indicator chip.
  static const Color colorSemanticSyncingSurface = Color(0xFFE6F3F5);

  /// Local and server state disagree and a human must decide. Bahasa Indonesia
  /// label: Perlu Diperiksa.
  static const Color colorSemanticConflict = Color(0xFF7A3B8F);

  /// Background of the conflict panel.
  static const Color colorSemanticConflictSurface = Color(0xFFF3EBF6);

  /// Canonical page background for the light theme.
  static const Color colorSemanticSurfacePage = Color(0xFFFFFFFF);

  /// Cards, sheets and raised containers.
  static const Color colorSemanticSurfaceRaised = Color(0xFFF7F8FA);

  /// Sunken wells, table header rows, and inactive tab strips.
  static const Color colorSemanticSurfaceSunken = Color(0xFFEFF1F4);

  /// Primary body and heading text.
  static const Color colorSemanticTextPrimary = Color(0xFF171C24);

  /// Secondary text, metadata, timestamps and helper text.
  static const Color colorSemanticTextSecondary = Color(0xFF3E4756);

  /// Text and icons placed on color.semantic.primary.
  static const Color colorSemanticTextOnPrimary = Color(0xFFFFFFFF);

  /// Decorative dividers and non-meaning-bearing separators.
  static const Color colorSemanticBorderSubtle = Color(0xFFC4CAD3);

  /// The resting boundary of every interactive control: inputs, checkboxes,
  /// radios, switches, bordered buttons.
  static const Color colorSemanticBorderInteractive = Color(0xFF737D8C);

  /// The default border of an interactive control. Synonym-free alias of the
  /// interactive boundary; components may name either.
  static const Color colorSemanticBorder = Color(0xFF737D8C);

  /// A heavier boundary for emphasis: a focused table cell, a selected card
  /// outline, a high-attention container.
  static const Color colorSemanticBorderStrong = Color(0xFF3E4756);

  /// The pressed state of a primary action, one step darker than the hover
  /// treatment.
  static const Color colorSemanticPrimaryPressed = Color(0xFF0A2540);

  /// The default surface. Alias of the page surface for components that do not
  /// distinguish page from container.
  static const Color colorSemanticSurface = Color(0xFFFFFFFF);

  /// An inverted surface: tooltips, snackbars, and the dark app bar variant.
  static const Color colorSemanticSurfaceInverse = Color(0xFF171C24);

  /// Text placed on color.semantic.surface.inverse.
  static const Color colorSemanticTextInverse = Color(0xFFFFFFFF);

  /// The label of an inactive control.
  static const Color colorSemanticTextDisabled = Color(0xFF9AA3B0);

  /// A quiet neutral fill: a metadata chip, an inactive tab, a zebra table row.
  static const Color colorSemanticNeutralSubtle = Color(0xFFEFF1F4);

  /// A quieter conflict fill for a row that needs review without shouting
  /// across the whole table.
  static const Color colorSemanticConflictSubtle = Color(0xFFE0C9E8);
}
