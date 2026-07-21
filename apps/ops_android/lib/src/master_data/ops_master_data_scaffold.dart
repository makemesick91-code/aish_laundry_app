import 'package:aish_design_system/aish_design_system.dart';
import 'package:aish_domain/aish_domain.dart';
import 'package:aish_offline_sync/aish_offline_sync.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../app.dart';

/// The chrome every Ops master-data screen wears.
///
/// ITS WHOLE JOB IS THE CONTEXT LINE.
/// Rule 28 hard rule 1 requires the active tenant to be rendered persistently,
/// as TEXT, in the primary chrome of every authenticated screen — never a colour
/// swatch, never only inside a menu — and the outlet alongside it wherever the
/// user has more than one. A screen that lets a staff member edit a price or a
/// roster without showing whose data it is is a tenant-isolation design defect,
/// not a layout preference. Centralising it here is what stops one screen from
/// forgetting.
///
/// It is presentation, and only presentation. The server re-derives tenant,
/// membership and permission on every request; this widget cannot grant access
/// to anything and cannot be relied upon to withhold it.
class OpsMasterDataScaffold extends ConsumerWidget {
  const OpsMasterDataScaffold({
    required this.title,
    required this.session,
    required this.body,
    this.onBack,
    this.floatingAction,
    this.actions = const <Widget>[],
    super.key,
  });

  final String title;
  final SessionState session;
  final Widget body;
  final VoidCallback? onBack;
  final Widget? floatingAction;
  final List<Widget> actions;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final SyncHealth health = ref.watch(syncHealthProvider);

    return Scaffold(
      appBar: AppBar(
        title: Text(title),
        leading: onBack == null
            ? null
            : IconButton(
                tooltip: 'Kembali',
                icon: const Icon(Icons.arrow_back),
                onPressed: onBack,
              ),
        actions: actions,
        bottom: PreferredSize(
          preferredSize: const Size.fromHeight(kToolbarHeight * 0.6),
          child: ContextBanner(
            tenantName: session.activeTenant!.name,
            outletName: session.activeOutlet?.name,
            isOffline: health == SyncHealth.offline,
          ),
        ),
      ),
      floatingActionButton: floatingAction,
      body: SafeArea(child: body),
    );
  }
}

/// Restates the tenant inside the same visual block as a committing action.
///
/// Rule 28 hard rule 2: chrome-level context is not sufficient at the MOMENT OF
/// COMMITMENT. An operator who works for two competing laundries must be told
/// whose price list they are about to publish in the same glance as the button
/// that publishes it — not by looking back up at a header they stopped reading
/// an hour ago.
class CommitContextLine extends StatelessWidget {
  const CommitContextLine({
    required this.session,
    required this.action,
    super.key,
  });

  final SessionState session;

  /// What is about to happen, in the imperative — "Simpan perubahan outlet".
  final String action;

  @override
  Widget build(BuildContext context) {
    final outlet = session.activeOutlet;
    final where = outlet == null
        ? session.activeTenant!.name
        : '${session.activeTenant!.name} · ${outlet.name}';

    return Semantics(
      container: true,
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Icon(
            Icons.business_outlined,
            size: AishSizing.sizeIconSm,
            color: AishSemanticColors.colorSemanticTextSecondary,
          ),
          SizedBox(width: AishSpacing.space2),
          Expanded(
            child: Text(
              '$action pada $where',
              style: Theme.of(context).textTheme.bodySmall?.copyWith(
                color: AishSemanticColors.colorSemanticTextSecondary,
              ),
            ),
          ),
        ],
      ),
    );
  }
}
