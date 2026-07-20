import 'package:flutter/material.dart';

import '../generated/tokens.dart';

/// Renders the active tenant, and the active outlet when there is one.
///
/// Rule 28 hard rule 1 and Rule 32 hard rule 1: tenant context must be visible
/// on every authenticated screen, as TEXT, in the primary chrome — never a
/// colour swatch alone, never an avatar alone, never only inside a collapsed
/// menu, never only on a settings page.
///
/// [tenantName] is therefore a required, non-empty String. There is no variant
/// of this component that renders a colour or an initial instead, because the
/// existence of such a variant is how the rule gets broken by a designer who
/// wanted a tidier header.
class ContextBanner extends StatelessWidget {
  const ContextBanner({
    required this.tenantName,
    this.outletName,
    this.onSwitchTenant,
    this.isOffline = false,
    super.key,
  }) : assert(
         tenantName != '',
         'Tenant context is never rendered as blank. If it is unknown, the '
         'screen is not ready to be shown.',
       );

  final String tenantName;

  /// Rendered whenever the user has access to more than one outlet. The caller
  /// passes `null` only when the user genuinely has a single outlet.
  final String? outletName;

  /// Shown only where the user belongs to more than one tenant.
  final VoidCallback? onSwitchTenant;

  final bool isOffline;

  @override
  Widget build(BuildContext context) {
    final textTheme = Theme.of(context).textTheme;
    final contextText = outletName == null
        ? tenantName
        : '$tenantName · ${outletName!}';

    return Semantics(
      // A single, explicit announcement. Assistive technology reads the working
      // context as one phrase rather than as two orphan labels.
      label: outletName == null
          ? 'Konteks aktif. Tenant $tenantName.'
          : 'Konteks aktif. Tenant $tenantName, outlet ${outletName!}.',
      container: true,
      child: Container(
        width: double.infinity,
        padding: EdgeInsets.symmetric(
          horizontal: AishSpacing.space4,
          vertical: AishSpacing.space2,
        ),
        color: AishSemanticColors.colorSemanticPrimarySurface,
        child: Row(
          children: <Widget>[
            ExcludeSemantics(
              child: Icon(
                Icons.apartment_outlined,
                size: AishSizing.sizeIconSm,
                color: AishSemanticColors.colorSemanticPrimary,
              ),
            ),
            SizedBox(width: AishSpacing.space2),
            Expanded(
              child: Text(
                contextText,
                style: textTheme.labelMedium?.copyWith(
                  color: AishSemanticColors.colorSemanticTextPrimary,
                  fontWeight: FontWeight.w600,
                ),
                // Two lines rather than an ellipsis: the tenant name is the one
                // thing on this bar that must never be truncated away.
                maxLines: 2,
              ),
            ),
            if (isOffline) ...<Widget>[
              SizedBox(width: AishSpacing.space2),
              // Text plus icon, never colour alone.
              Semantics(
                label: 'Status jaringan: luring',
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: <Widget>[
                    ExcludeSemantics(
                      child: Icon(
                        Icons.cloud_off_outlined,
                        size: AishSizing.sizeIconSm,
                        color: AishSemanticColors.colorSemanticOffline,
                      ),
                    ),
                    SizedBox(width: AishSpacing.space1),
                    ExcludeSemantics(
                      child: Text(
                        'Luring',
                        style: textTheme.labelSmall?.copyWith(
                          color: AishSemanticColors.colorSemanticOffline,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ],
            if (onSwitchTenant != null) ...<Widget>[
              SizedBox(width: AishSpacing.space2),
              ConstrainedBox(
                constraints: BoxConstraints(
                  minWidth: AishSizing.sizeTouchMin,
                  minHeight: AishSizing.sizeTouchMin,
                ),
                child: TextButton(
                  onPressed: onSwitchTenant,
                  child: const Text('Ganti tenant'),
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}
