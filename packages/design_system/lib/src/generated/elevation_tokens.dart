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

import 'package:flutter/painting.dart';

/// Light-theme elevation. Elevation is spatial and never semantic.
abstract final class AishElevation {
  /// Elevation level 0. Flat. The default for content on the page surface.
  /// Shadow values are tuned for the light theme only; a dark theme would
  /// require its own values and is PLANNED, NOT IMPLEMENTED.
  static const List<BoxShadow> elevation0 = <BoxShadow>[];

  /// Elevation level 1. Cards and list containers at rest. Shadow values are
  /// tuned for the light theme only; a dark theme would require its own values
  /// and is PLANNED, NOT IMPLEMENTED.
  static const List<BoxShadow> elevation1 = <BoxShadow>[
    BoxShadow(
      color: Color.fromRGBO(23, 28, 36, 0.08),
      offset: Offset(0.0, 1.0),
      blurRadius: 2.0,
    ),
  ];

  /// Elevation level 2. Raised cards, sticky headers. Shadow values are tuned
  /// for the light theme only; a dark theme would require its own values and is
  /// PLANNED, NOT IMPLEMENTED.
  static const List<BoxShadow> elevation2 = <BoxShadow>[
    BoxShadow(
      color: Color.fromRGBO(23, 28, 36, 0.1),
      offset: Offset(0.0, 2.0),
      blurRadius: 4.0,
    ),
  ];

  /// Elevation level 3. Bottom sheets, dropdown menus, popovers. Shadow values
  /// are tuned for the light theme only; a dark theme would require its own
  /// values and is PLANNED, NOT IMPLEMENTED.
  static const List<BoxShadow> elevation3 = <BoxShadow>[
    BoxShadow(
      color: Color.fromRGBO(23, 28, 36, 0.12),
      offset: Offset(0.0, 4.0),
      blurRadius: 8.0,
    ),
  ];

  /// Elevation level 4. Dialogs and modal surfaces. Shadow values are tuned for
  /// the light theme only; a dark theme would require its own values and is
  /// PLANNED, NOT IMPLEMENTED.
  static const List<BoxShadow> elevation4 = <BoxShadow>[
    BoxShadow(
      color: Color.fromRGBO(23, 28, 36, 0.14),
      offset: Offset(0.0, 8.0),
      blurRadius: 16.0,
    ),
  ];

  /// Elevation level 5. Reserved. Not used by any Step 2 component. Shadow
  /// values are tuned for the light theme only; a dark theme would require its
  /// own values and is PLANNED, NOT IMPLEMENTED.
  static const List<BoxShadow> elevation5 = <BoxShadow>[
    BoxShadow(
      color: Color.fromRGBO(23, 28, 36, 0.16),
      offset: Offset(0.0, 16.0),
      blurRadius: 32.0,
    ),
  ];
}
