import 'package:flutter/material.dart';

import '../generated/tokens.dart';

/// The kinds of state a [StatusChip] may express.
///
/// Deliberately generic — connectivity, sync, permission — because Step 3 has no
/// order, payment or quality-control status to render. Those arrive with the
/// Steps that own them and render through this same component.
enum StatusTone {
  neutral,
  information,
  success,
  warning,
  danger,
  offline,
  syncing,
}

/// THE centralised status renderer. No screen improvises a chip.
///
/// Rule 34 rule 19 requires status rendering to be centralised so a change to
/// status presentation cannot diverge across surfaces, and Rule 27 rule 3
/// requires that status never depend on colour alone.
///
/// Both are enforced STRUCTURALLY here rather than by convention:
///
///   * [label] is required and non-empty. There is no way to construct a chip
///     that shows only a colour.
///   * [icon] is required. Two statuses adjacent in a workflow must differ by
///     shape as well as by hue, because a cheap screen in direct sunlight
///     flattens hue differences long before it flattens shape.
///   * The accessible label names the STATUS, not the colour.
class StatusChip extends StatelessWidget {
  const StatusChip({
    required this.label,
    required this.icon,
    this.tone = StatusTone.neutral,
    super.key,
  }) : assert(label != '', 'A status must always carry a text label.');

  /// Bahasa Indonesia text. Always rendered; never optional.
  final String label;

  /// Shape reinforcement. Always rendered; never optional.
  final IconData icon;

  final StatusTone tone;

  @override
  Widget build(BuildContext context) {
    final (foreground, background) = _colors(tone);
    return Semantics(
      label: 'Status: $label',
      readOnly: true,
      child: Container(
        padding: EdgeInsets.symmetric(
          horizontal: AishSpacing.space3,
          vertical: AishSpacing.space1,
        ),
        constraints: BoxConstraints(minHeight: AishSizing.sizeControlXs),
        decoration: BoxDecoration(
          color: background,
          borderRadius: BorderRadius.circular(AishRadius.radiusFull),
          border: Border.all(
            color: foreground,
            width: AishBorders.borderWidthHairline,
          ),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: <Widget>[
            // Excluded from semantics: the icon duplicates the label, and
            // announcing it twice is noise that degrades the experience it was
            // meant to improve.
            ExcludeSemantics(
              child: Icon(icon, size: AishSizing.sizeIconSm, color: foreground),
            ),
            SizedBox(width: AishSpacing.space2),
            Flexible(
              child: Text(
                label,
                style: Theme.of(context).textTheme.labelMedium?.copyWith(
                  color: foreground,
                  fontWeight: FontWeight.w600,
                ),
                // Wraps rather than truncating: a status is critical
                // information and truncating it at a large font size is a
                // defect, not a layout compromise (Rule 27 rule 7).
                softWrap: true,
              ),
            ),
          ],
        ),
      ),
    );
  }

  static (Color, Color) _colors(StatusTone tone) => switch (tone) {
    StatusTone.neutral => (
      AishSemanticColors.colorSemanticNeutral,
      AishSemanticColors.colorSemanticNeutralSubtle,
    ),
    StatusTone.information => (
      AishSemanticColors.colorSemanticInformation,
      AishSemanticColors.colorSemanticInformationSurface,
    ),
    StatusTone.success => (
      AishSemanticColors.colorSemanticSuccess,
      AishSemanticColors.colorSemanticSuccessSurface,
    ),
    StatusTone.warning => (
      AishSemanticColors.colorSemanticWarning,
      AishSemanticColors.colorSemanticWarningSurface,
    ),
    StatusTone.danger => (
      AishSemanticColors.colorSemanticDanger,
      AishSemanticColors.colorSemanticDangerSurface,
    ),
    StatusTone.offline => (
      AishSemanticColors.colorSemanticOffline,
      AishSemanticColors.colorSemanticNeutralSubtle,
    ),
    StatusTone.syncing => (
      AishSemanticColors.colorSemanticSyncing,
      AishSemanticColors.colorSemanticSyncingSurface,
    ),
  };
}
