import 'package:aish_design_system/aish_design_system.dart';
import 'package:aish_domain/aish_domain.dart';
import 'package:aish_offline_sync/aish_offline_sync.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../app.dart';
import '../routing/ops_routes.dart';

/// The authenticated Ops shell.
///
/// Navigation is ROLE-AWARE: an entry is rendered only when the server-reported
/// permission set allows it. Two things that must not be confused:
///
///   * Hiding an entry is a courtesy. It stops a cashier from tapping into a
///     screen that would refuse them.
///   * It is NOT an access control. The permission set came from the server and
///     is re-checked by the server on every request. If this predicate were
///     inverted tomorrow, a cashier would see more menu items and gain exactly
///     nothing (Rule 28 rule 6).
class OpsHomeScreen extends ConsumerWidget {
  const OpsHomeScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final session = ref.watch(authServiceProvider).current.session;
    final SyncHealth health = ref.watch(syncHealthProvider);

    if (session == null || !session.hasTenantContext) {
      return const Scaffold(body: SizedBox.shrink());
    }

    final textTheme = Theme.of(context).textTheme;

    return AishScaffold(
      title: 'Beranda',
      tenantName: session.activeTenant!.name,
      outletName: session.activeOutlet?.name,
      isOffline: health == SyncHealth.offline,
      onSwitchTenant: session.needsTenantSwitcher
          ? () => _switchTenant(context, ref)
          : null,
      actions: <Widget>[
        IconButton(
          tooltip: 'Keluar dari akun ${session.user.displayName}',
          icon: const Icon(Icons.logout_outlined),
          onPressed: () async {
            await ref.read(authServiceProvider).signOut();
            if (context.mounted) {
              context.go(OpsRoutes.signIn);
            }
          },
        ),
      ],
      body: ListView(
        padding: EdgeInsets.all(AishSpacing.space4),
        children: <Widget>[
          _SyncIndicator(health: health),
          SizedBox(height: AishSpacing.space4),
          Text(
            'Halo, ${session.user.displayName}',
            style: textTheme.titleLarge,
          ),
          SizedBox(height: AishSpacing.space2),
          Text(
            'Data induk sudah dapat dikelola. Transaksi, produksi, dan '
            'pengantaran belum dibangun — menu bertanda "Belum tersedia" '
            'menunjukkan cakupan yang akan datang, bukan kemampuan yang sudah '
            'tersedia.',
            style: textTheme.bodyMedium?.copyWith(
              color: AishSemanticColors.colorSemanticTextSecondary,
            ),
          ),
          SizedBox(height: AishSpacing.space6),

          // ------------------------------------------------------------------
          // STEP 4 — LAUNDRY MASTER DATA. These entries reach real screens.
          //
          // Each is gated on a permission the SERVER reported. That is a
          // courtesy — it stops a cashier tapping into a screen that would
          // refuse them — and it is NOT an access control: the permission set
          // came from the server and is re-checked by the server on every
          // request. Inverting a predicate here would show more menu items and
          // grant exactly nothing (Rule 28 hard rule 6).
          // ------------------------------------------------------------------
          Text('Data induk', style: textTheme.titleMedium),
          SizedBox(height: AishSpacing.space2),
          if (session.allows(Permission.customerView))
            _NavEntry(
              label: 'Pelanggan',
              icon: Icons.people_outline,
              route: OpsRoutes.customers,
              available: true,
            ),
          if (session.allows(Permission.serviceView) ||
              session.allows(Permission.priceListView))
            _NavEntry(
              label: 'Layanan dan harga',
              icon: Icons.local_offer_outlined,
              route: OpsRoutes.catalogue,
              available: true,
            ),
          if (session.allows(Permission.outletView))
            _NavEntry(
              label: 'Data outlet',
              icon: Icons.storefront_outlined,
              route: OpsRoutes.outletMasterData,
              available: true,
            ),
          if (session.allows(Permission.membershipView))
            _NavEntry(
              label: 'Staf dan peran',
              icon: Icons.badge_outlined,
              route: OpsRoutes.staffRoster,
              available: true,
            ),

          SizedBox(height: AishSpacing.space6),
          Text('Belum tersedia', style: textTheme.titleMedium),
          SizedBox(height: AishSpacing.space2),
          if (session.allows(Permission.outletView))
            _NavEntry(
              label: 'Kasir',
              icon: Icons.point_of_sale_outlined,
              route: OpsRoutes.futureCounter,
            ),
          if (session.allows(Permission.outletView))
            _NavEntry(
              label: 'Produksi',
              icon: Icons.local_laundry_service_outlined,
              route: OpsRoutes.futureProduction,
            ),
          if (session.allows(Permission.outletView))
            _NavEntry(
              label: 'Kendali mutu',
              icon: Icons.fact_check_outlined,
              route: OpsRoutes.futureQualityControl,
            ),
          if (session.allows(Permission.outletView))
            _NavEntry(
              label: 'Kurir',
              icon: Icons.two_wheeler_outlined,
              route: OpsRoutes.futureCourier,
            ),
          if (session.allows(Permission.auditView))
            _NavEntry(
              label: 'Laporan',
              icon: Icons.insights_outlined,
              route: OpsRoutes.futureReports,
            ),
        ],
      ),
    );
  }

  /// Switching tenants clears the visible working set before anything else.
  Future<void> _switchTenant(BuildContext context, WidgetRef ref) async {
    final service = ref.read(authServiceProvider);
    final session = service.current.session;
    if (session == null) {
      return;
    }
    // Selecting a different tenant rebuilds the session from a cleared context,
    // so the previous tenant's outlet and permissions cannot survive the switch.
    if (context.mounted) {
      context.go(OpsRoutes.selectTenant);
    }
  }
}

/// The always-visible connectivity indicator.
///
/// Text AND icon, never colour alone. It reports CONNECTIVITY only. Step 3 has
/// no queue, so it never claims a pending count or a completed synchronisation
/// that did not happen.
class _SyncIndicator extends StatelessWidget {
  const _SyncIndicator({required this.health});

  final SyncHealth health;

  @override
  Widget build(BuildContext context) {
    final (label, icon, tone) = switch (health) {
      SyncHealth.idle => (
        'Terhubung',
        Icons.cloud_done_outlined,
        StatusTone.success,
      ),
      SyncHealth.syncing => (
        'Menyinkronkan',
        Icons.sync_outlined,
        StatusTone.syncing,
      ),
      SyncHealth.offline => (
        'Luring',
        Icons.cloud_off_outlined,
        StatusTone.offline,
      ),
      SyncHealth.attentionRequired => (
        'Perlu tindakan',
        Icons.priority_high_outlined,
        StatusTone.danger,
      ),
    };
    return Align(
      alignment: Alignment.centerLeft,
      child: StatusChip(label: label, icon: icon, tone: tone),
    );
  }
}

class _NavEntry extends StatelessWidget {
  const _NavEntry({
    required this.label,
    required this.icon,
    required this.route,
    this.available = false,
  });

  final String label;
  final IconData icon;
  final String route;

  /// Whether the destination is a BUILT screen or a Step-5+ placeholder.
  ///
  /// It changes what assistive technology announces, and it must stay truthful:
  /// announcing a built screen as "belum tersedia" would be as wrong as
  /// announcing a placeholder as available (Rule 01).
  final bool available;

  @override
  Widget build(BuildContext context) => Padding(
    padding: EdgeInsets.only(bottom: AishSpacing.space2),
    child: ConstrainedBox(
      constraints: BoxConstraints(minHeight: AishSizing.sizeTouchMin),
      child: Semantics(
        button: true,
        label: available ? label : '$label. Belum tersedia.',
        child: ExcludeSemantics(
          child: ListTile(
            leading: Icon(icon),
            title: Text(label),
            trailing: const Icon(Icons.chevron_right),
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(AishRadius.radiusMd),
              side: BorderSide(
                color: AishSemanticColors.colorSemanticBorderSubtle,
                width: AishBorders.borderWidthHairline,
              ),
            ),
            onTap: () => context.go(route),
          ),
        ),
      ),
    ),
  );
}
