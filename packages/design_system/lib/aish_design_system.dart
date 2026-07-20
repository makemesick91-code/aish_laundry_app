/// The Aish Laundry App design system.
///
/// Every colour, dimension, type size, shadow and duration in this package comes
/// from `lib/src/generated/`, which is produced from the canonical token JSON by
/// `scripts/generate-design-tokens.py`. A raw hex literal, a magic spacing
/// number, or a hand-written duration anywhere outside the generated directory
/// is a defect, and a test enforces it.
///
/// LIGHT THEME ONLY. Dark mode is DEFERRED. No dark mapping exists, and the
/// presence of a semantic token layer does not mean a second theme is available.
library;

export 'src/generated/tokens.dart';
export 'src/theme/aish_theme.dart';
export 'src/widgets/aish_scaffold.dart';
export 'src/widgets/context_banner.dart';
export 'src/widgets/future_step_placeholder.dart';
export 'src/widgets/primary_action.dart';
export 'src/widgets/state_message.dart';
export 'src/widgets/status_chip.dart';
