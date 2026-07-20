import 'package:flutter/material.dart';

import '../generated/tokens.dart';

/// The single, literal, unhedged statement rendered by every future-feature
/// route.
///
/// This exact string appears verbatim on screen. It is not softened to "coming
/// soon", not decorated with a date, and not replaced by a plausible-looking
/// mock. Rule 01 rule 2 forbids claiming an implementation that does not exist,
/// and a convincing placeholder screen is precisely such a claim — a reviewer
/// scrolling an app cannot tell a mock from a feature.
const String kFutureStepNotice =
    'NOT IMPLEMENTED — OWNED BY FUTURE CANONICAL STEP';

/// Renders [kFutureStepNotice] for a route whose feature belongs to a later
/// canonical Step.
///
/// It shows no field, no list, no total, no button that pretends to act, and no
/// sample datum. There is nothing here that could be screenshotted and mistaken
/// for a working surface.
class FutureStepPlaceholder extends StatelessWidget {
  const FutureStepPlaceholder({
    required this.featureName,
    required this.owningStep,
    super.key,
  });

  /// What the route WOULD be, named honestly.
  final String featureName;

  /// The canonical Step that owns it, e.g. 'Step 5'.
  final String owningStep;

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
            children: <Widget>[
              ExcludeSemantics(
                child: Icon(
                  Icons.construction_outlined,
                  size: AishSizing.sizeIconXl,
                  color: AishSemanticColors.colorSemanticTextSecondary,
                ),
              ),
              SizedBox(height: AishSpacing.space4),
              Semantics(
                header: true,
                child: Text(
                  featureName,
                  style: textTheme.titleMedium,
                  textAlign: TextAlign.center,
                ),
              ),
              SizedBox(height: AishSpacing.space4),
              Container(
                padding: EdgeInsets.all(AishSpacing.space4),
                decoration: BoxDecoration(
                  color: AishSemanticColors.colorSemanticWarningSurface,
                  borderRadius: BorderRadius.circular(AishRadius.radiusMd),
                  border: Border.all(
                    color: AishSemanticColors.colorSemanticWarning,
                    width: AishBorders.borderWidthHairline,
                  ),
                ),
                child: Text(
                  kFutureStepNotice,
                  style: textTheme.labelLarge?.copyWith(
                    color: AishSemanticColors.colorSemanticTextPrimary,
                    fontWeight: FontWeight.w700,
                  ),
                  textAlign: TextAlign.center,
                ),
              ),
              SizedBox(height: AishSpacing.space4),
              Text(
                'Kemampuan ini dimiliki oleh $owningStep dan belum dibangun. '
                'Tidak ada data, tindakan, atau hasil apa pun di halaman ini.',
                style: textTheme.bodyMedium?.copyWith(
                  color: AishSemanticColors.colorSemanticTextSecondary,
                ),
                textAlign: TextAlign.center,
              ),
            ],
          ),
        ),
      ),
    );
  }
}
