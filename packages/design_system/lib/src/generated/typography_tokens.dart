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

/// Typography primitives. System-first: no font binary is committed, so every surface renders in the platform UI face.
abstract final class AishTypography {
  /// System-first sans stack. No font binary is committed to this repository;
  /// every surface uses the platform's own UI face. This keeps the download
  /// small on low-end Android devices and avoids shipping a licensed binary in
  /// a PUBLIC repository.
  static const List<String> fontFamilySans = <String>[
    'system-ui',
    '-apple-system',
    'Segoe UI',
    'Roboto',
    'Noto Sans',
    'Helvetica Neue',
    'Arial',
    'sans-serif',
  ];

  /// Monospace stack for receipt previews and any place a fixed column grid
  /// carries meaning.
  static const List<String> fontFamilyMono = <String>[
    'ui-monospace',
    'Roboto Mono',
    'DejaVu Sans Mono',
    'Courier New',
    'monospace',
  ];

  /// OpenType feature enabling tabular (fixed-width) figures. Mandatory
  /// wherever integer Rupiah amounts, weights, quantities or timestamps are
  /// stacked in a column, so digits align and a misread total is harder to
  /// produce.
  static const String fontFeatureTabularNumbers = 'tnum';

  /// Font size for the caption role. Scales with the platform text-size
  /// setting; layouts must survive 200% scaling without losing a primary
  /// action.
  static const double fontSizeCaption = 12.0;

  /// Line height paired with font.size.caption. Ratio 1.33.
  static const double fontLineHeightCaption = 16.0;

  /// Font size for the label role. Scales with the platform text-size setting;
  /// layouts must survive 200% scaling without losing a primary action.
  static const double fontSizeLabel = 14.0;

  /// Line height paired with font.size.label. Ratio 1.43.
  static const double fontLineHeightLabel = 20.0;

  /// Font size for the body.sm role. Scales with the platform text-size
  /// setting; layouts must survive 200% scaling without losing a primary
  /// action.
  static const double fontSizeBodySm = 14.0;

  /// Line height paired with font.size.body.sm. Ratio 1.43.
  static const double fontLineHeightBodySm = 20.0;

  /// Font size for the body.md role. Scales with the platform text-size
  /// setting; layouts must survive 200% scaling without losing a primary
  /// action.
  static const double fontSizeBodyMd = 16.0;

  /// Line height paired with font.size.body.md. Ratio 1.5.
  static const double fontLineHeightBodyMd = 24.0;

  /// Font size for the body.lg role. Scales with the platform text-size
  /// setting; layouts must survive 200% scaling without losing a primary
  /// action.
  static const double fontSizeBodyLg = 18.0;

  /// Line height paired with font.size.body.lg. Ratio 1.44.
  static const double fontLineHeightBodyLg = 26.0;

  /// Font size for the title.sm role. Scales with the platform text-size
  /// setting; layouts must survive 200% scaling without losing a primary
  /// action.
  static const double fontSizeTitleSm = 18.0;

  /// Line height paired with font.size.title.sm. Ratio 1.33.
  static const double fontLineHeightTitleSm = 24.0;

  /// Font size for the title.md role. Scales with the platform text-size
  /// setting; layouts must survive 200% scaling without losing a primary
  /// action.
  static const double fontSizeTitleMd = 20.0;

  /// Line height paired with font.size.title.md. Ratio 1.4.
  static const double fontLineHeightTitleMd = 28.0;

  /// Font size for the title.lg role. Scales with the platform text-size
  /// setting; layouts must survive 200% scaling without losing a primary
  /// action.
  static const double fontSizeTitleLg = 24.0;

  /// Line height paired with font.size.title.lg. Ratio 1.33.
  static const double fontLineHeightTitleLg = 32.0;

  /// Font size for the headline.sm role. Scales with the platform text-size
  /// setting; layouts must survive 200% scaling without losing a primary
  /// action.
  static const double fontSizeHeadlineSm = 28.0;

  /// Line height paired with font.size.headline.sm. Ratio 1.29.
  static const double fontLineHeightHeadlineSm = 36.0;

  /// Font size for the headline.md role. Scales with the platform text-size
  /// setting; layouts must survive 200% scaling without losing a primary
  /// action.
  static const double fontSizeHeadlineMd = 32.0;

  /// Line height paired with font.size.headline.md. Ratio 1.25.
  static const double fontLineHeightHeadlineMd = 40.0;

  /// Font size for the display role. Scales with the platform text-size
  /// setting; layouts must survive 200% scaling without losing a primary
  /// action.
  static const double fontSizeDisplay = 40.0;

  /// Line height paired with font.size.display. Ratio 1.2.
  static const double fontLineHeightDisplay = 48.0;

  /// Regular weight. Weight alone never conveys status.
  static const int fontWeightRegular = 400;

  /// Medium weight. Weight alone never conveys status.
  static const int fontWeightMedium = 500;

  /// Semibold weight. Weight alone never conveys status.
  static const int fontWeightSemibold = 600;

  /// Bold weight. Weight alone never conveys status.
  static const int fontWeightBold = 700;

  /// Tight letter spacing.
  static const double fontLetterSpacingTight = -0.2;

  /// Normal letter spacing.
  static const double fontLetterSpacingNormal = 0.0;

  /// Wide letter spacing.
  static const double fontLetterSpacingWide = 0.4;

  /// Maximum comfortable measure for body copy, in characters. Longer measures
  /// reduce reading accuracy on wide Console Web layouts.
  static const double fontMaxLineLengthBody = 72.0;

  /// Font size for the label.sm role. Scales with the platform text-size
  /// setting; layouts must survive 200% scaling without losing a primary
  /// action.
  static const double fontSizeLabelSm = 12.0;

  /// Line height paired with font.size.label.sm. Ratio 1.33.
  static const double fontLineHeightLabelSm = 16.0;

  /// Font size for the label.md role. Scales with the platform text-size
  /// setting; layouts must survive 200% scaling without losing a primary
  /// action.
  static const double fontSizeLabelMd = 14.0;

  /// Line height paired with font.size.label.md. Ratio 1.43.
  static const double fontLineHeightLabelMd = 20.0;

  /// Font size for the label.lg role. Scales with the platform text-size
  /// setting; layouts must survive 200% scaling without losing a primary
  /// action.
  static const double fontSizeLabelLg = 16.0;

  /// Line height paired with font.size.label.lg. Ratio 1.38.
  static const double fontLineHeightLabelLg = 22.0;

  /// Font size for the display.md role. Scales with the platform text-size
  /// setting; layouts must survive 200% scaling without losing a primary
  /// action.
  static const double fontSizeDisplayMd = 40.0;

  /// Line height paired with font.size.display.md. Ratio 1.2.
  static const double fontLineHeightDisplayMd = 48.0;

  /// Font size for the display.lg role. Scales with the platform text-size
  /// setting; layouts must survive 200% scaling without losing a primary
  /// action.
  static const double fontSizeDisplayLg = 48.0;

  /// Line height paired with font.size.display.lg. Ratio 1.17.
  static const double fontLineHeightDisplayLg = 56.0;

  /// Font size for the headline.lg role. Scales with the platform text-size
  /// setting; layouts must survive 200% scaling without losing a primary
  /// action.
  static const double fontSizeHeadlineLg = 36.0;

  /// Line height paired with font.size.headline.lg. Ratio 1.22.
  static const double fontLineHeightHeadlineLg = 44.0;
}
