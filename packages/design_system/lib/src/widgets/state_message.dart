import 'package:flutter/material.dart';

import '../generated/tokens.dart';
import 'primary_action.dart';
import 'status_chip.dart';

/// A full-surface message for a non-`LOADED` state.
///
/// Rule 29 rule 8: every screen that takes money, transfers custody, changes
/// access, or changes order status specifies its error state AND the recovery
/// action available from it. An error state with no recovery path is incomplete.
///
/// This component makes that structural. [recoveryLabel] and [onRecover] are
/// either BOTH supplied or both absent, and the assertion below rejects a
/// half-specified recovery at construction. A screen that genuinely has no
/// recovery must say so in [description] — in words the user can act on — rather
/// than leaving a dead end.
class StateMessage extends StatelessWidget {
  const StateMessage({
    required this.title,
    required this.description,
    required this.icon,
    this.tone = StatusTone.neutral,
    this.statusLabel,
    this.recoveryLabel,
    this.onRecover,
    this.supportReference,
    super.key,
  }) : assert(
         (recoveryLabel == null) == (onRecover == null),
         'A recovery action needs both a label and a callback. A labelled '
         'button that does nothing is worse than no button.',
       );

  /// Short Bahasa Indonesia heading naming WHAT happened.
  final String title;

  /// What the user should do next, in plain Bahasa Indonesia. An error code
  /// alone is never acceptable (Rule 30 rule 5).
  final String description;

  final IconData icon;

  final StatusTone tone;

  /// Optional chip, e.g. the sync state.
  final String? statusLabel;

  final String? recoveryLabel;

  final VoidCallback? onRecover;

  /// A correlation identifier the user can quote to support. NOT a credential —
  /// it grants nothing, which is exactly why this is the value shown and a token
  /// never is.
  final String? supportReference;

  @override
  Widget build(BuildContext context) {
    final textTheme = Theme.of(context).textTheme;
    return Center(
      child: SingleChildScrollView(
        padding: EdgeInsets.all(AishSpacing.space6),
        child: ConstrainedBox(
          constraints: BoxConstraints(maxWidth: AishSizing.sizeMaxWidthReading),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.center,
            children: <Widget>[
              ExcludeSemantics(
                child: Icon(
                  icon,
                  size: AishSizing.sizeIconXl,
                  color: AishSemanticColors.colorSemanticTextSecondary,
                ),
              ),
              SizedBox(height: AishSpacing.space4),
              if (statusLabel != null) ...<Widget>[
                StatusChip(label: statusLabel!, icon: icon, tone: tone),
                SizedBox(height: AishSpacing.space4),
              ],
              // The heading is a semantic header so a screen reader can jump to
              // it, and so a route change can move focus here.
              Semantics(
                header: true,
                child: Text(
                  title,
                  style: textTheme.titleMedium,
                  textAlign: TextAlign.center,
                ),
              ),
              SizedBox(height: AishSpacing.space2),
              Text(
                description,
                style: textTheme.bodyMedium?.copyWith(
                  color: AishSemanticColors.colorSemanticTextSecondary,
                ),
                textAlign: TextAlign.center,
              ),
              if (recoveryLabel != null) ...<Widget>[
                SizedBox(height: AishSpacing.space6),
                PrimaryAction(
                  label: recoveryLabel!,
                  onPressed: onRecover,
                  expand: false,
                ),
              ],
              if (supportReference != null) ...<Widget>[
                SizedBox(height: AishSpacing.space6),
                SelectableText(
                  'Kode rujukan: $supportReference',
                  style: textTheme.bodySmall?.copyWith(
                    color: AishSemanticColors.colorSemanticTextSecondary,
                  ),
                  textAlign: TextAlign.center,
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}
