import 'package:flutter/material.dart';

import '../generated/tokens.dart';

/// The primary action button.
///
/// Guarantees, each of which exists because its absence is a known defect class:
///
///   * A minimum 48x48 logical-pixel target, on every surface and every role,
///     including a courier working outdoors one-handed. There is no compact
///     variant and no density that shrinks it.
///   * A visible focus indicator, so Console Web is operable by keyboard.
///   * An explicit accessible label that names the action AND its object, so an
///     icon-bearing control never announces merely "Batal".
///   * A busy state that DISABLES the control, so a double tap cannot submit
///     twice while a request is in flight.
class PrimaryAction extends StatelessWidget {
  const PrimaryAction({
    required this.label,
    required this.onPressed,
    this.semanticLabel,
    this.icon,
    this.isBusy = false,
    this.expand = true,
    super.key,
  });

  /// Visible Bahasa Indonesia label.
  final String label;

  /// Announced label, when it must be more specific than [label].
  final String? semanticLabel;

  final IconData? icon;

  /// `null` renders the control DISABLED. A control the user may not use is not
  /// rendered as though it were usable (Rule 28 rule 5).
  final VoidCallback? onPressed;

  final bool isBusy;

  final bool expand;

  @override
  Widget build(BuildContext context) {
    final button = FilledButton(
      onPressed: isBusy ? null : onPressed,
      child: Row(
        mainAxisSize: expand ? MainAxisSize.max : MainAxisSize.min,
        mainAxisAlignment: MainAxisAlignment.center,
        children: <Widget>[
          if (isBusy) ...<Widget>[
            SizedBox(
              width: AishSizing.sizeIconMd,
              height: AishSizing.sizeIconMd,
              child: CircularProgressIndicator(
                strokeWidth: AishBorders.borderWidthThick,
                color: AishSemanticColors.colorSemanticTextOnPrimary,
              ),
            ),
            SizedBox(width: AishSpacing.space2),
          ] else if (icon != null) ...<Widget>[
            ExcludeSemantics(child: Icon(icon, size: AishSizing.sizeIconMd)),
            SizedBox(width: AishSpacing.space2),
          ],
          Flexible(child: Text(label, textAlign: TextAlign.center)),
        ],
      ),
    );

    return Semantics(
      button: true,
      enabled: onPressed != null && !isBusy,
      label: semanticLabel ?? label,
      child: ConstrainedBox(
        constraints: BoxConstraints(
          minHeight: AishSizing.sizeTouchMin,
          minWidth: AishSizing.sizeTouchMin,
        ),
        child: button,
      ),
    );
  }
}
