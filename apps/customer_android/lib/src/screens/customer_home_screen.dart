import 'package:aish_auth/aish_auth.dart';
import 'package:aish_design_system/aish_design_system.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../app.dart';
import '../routing/customer_routes.dart';

/// The authenticated customer shell.
///
/// It renders the user's identity and a set of entry points, EVERY ONE of which
/// leads to a route that states it is not implemented. It shows no order, no
/// status, no total and no sample datum, because none of those exist. A shell
/// populated with plausible placeholder content is indistinguishable from a
/// working product in a screenshot, and that is the false claim Rule 01 forbids.
class CustomerHomeScreen extends ConsumerWidget {
  const CustomerHomeScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final AuthState auth = ref.watch(authServiceProvider).current;
    final session = auth.session;

    if (session == null) {
      // The guard should have redirected already; rendering nothing rather
      // than improvising a shell keeps a transient frame from leaking chrome.
      return const Scaffold(body: SizedBox.shrink());
    }

    final textTheme = Theme.of(context).textTheme;

    return Scaffold(
      appBar: AppBar(
        title: Semantics(header: true, child: const Text('Beranda')),
        actions: <Widget>[
          IconButton(
            // Names the action AND its object, never a bare "Keluar" icon.
            tooltip: 'Keluar dari akun ${session.user.displayName}',
            icon: const Icon(Icons.logout_outlined),
            onPressed: () async {
              await ref.read(authServiceProvider).signOut();
              if (context.mounted) {
                context.go(CustomerRoutes.signIn);
              }
            },
          ),
        ],
      ),
      body: ListView(
        padding: EdgeInsets.all(AishSpacing.space4),
        children: <Widget>[
          Text(
            'Halo, ${session.user.displayName}',
            style: textTheme.titleLarge,
          ),
          SizedBox(height: AishSpacing.space2),
          Text(
            'Belum ada fitur pelanggan yang dibangun. Menu di bawah ini '
            'menunjukkan cakupan yang akan datang, bukan kemampuan yang sudah '
            'tersedia.',
            style: textTheme.bodyMedium?.copyWith(
              color: AishSemanticColors.colorSemanticTextSecondary,
            ),
          ),
          SizedBox(height: AishSpacing.space6),
          _FutureEntry(
            label: 'Pesanan saya',
            icon: Icons.receipt_long_outlined,
            route: CustomerRoutes.futureOrders,
          ),
          _FutureEntry(
            label: 'Lacak cucian',
            icon: Icons.local_shipping_outlined,
            route: CustomerRoutes.futureTracking,
          ),
          _FutureEntry(
            label: 'Penjemputan',
            icon: Icons.event_available_outlined,
            route: CustomerRoutes.futurePickup,
          ),
          _FutureEntry(
            label: 'Tagihan',
            icon: Icons.payments_outlined,
            route: CustomerRoutes.futureInvoices,
          ),
          SizedBox(height: AishSpacing.space6),
          _FutureEntry(
            label: 'Pratinjau design system',
            icon: Icons.palette_outlined,
            route: CustomerRoutes.designSmoke,
          ),
        ],
      ),
    );
  }
}

/// A navigation row that meets the 48x48 minimum and announces its destination
/// as not implemented, so the notice is not a surprise on arrival.
class _FutureEntry extends StatelessWidget {
  const _FutureEntry({
    required this.label,
    required this.icon,
    required this.route,
  });

  final String label;
  final IconData icon;
  final String route;

  @override
  Widget build(BuildContext context) => Padding(
    padding: EdgeInsets.only(bottom: AishSpacing.space2),
    child: ConstrainedBox(
      constraints: BoxConstraints(minHeight: AishSizing.sizeTouchMin),
      child: Semantics(
        button: true,
        label: '$label. Belum tersedia.',
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
