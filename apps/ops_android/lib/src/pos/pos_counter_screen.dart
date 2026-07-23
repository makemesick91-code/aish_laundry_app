import 'package:aish_design_system/aish_design_system.dart';
import 'package:aish_domain/aish_domain.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../app.dart';
import '../master_data/master_data_views.dart';
import '../master_data/ops_master_data_scaffold.dart';
import '../routing/ops_routes.dart';
import 'pos_providers.dart';

/// THE COUNTER'S ORDER LIST (FR-057) — the POS home for a cashier.
///
/// Scoped to the ACTIVE OUTLET: a cashier works one counter, and the list they
/// see is that counter's orders. Scoping is a server concern — the request
/// carries the outlet and the server returns only that tenant's rows (Rule 02);
/// there is no client-side filtering here.
///
/// EVERY AMOUNT IS AN INTEGER RUPIAH shown through [Rupiah.formatted]. Nothing on
/// this screen computes a total — the figure is the one the server stored
/// (Rule 04, FR-051).
class PosCounterScreen extends ConsumerStatefulWidget {
  const PosCounterScreen({super.key});

  @override
  ConsumerState<PosCounterScreen> createState() => _PosCounterScreenState();
}

class _PosCounterScreenState extends ConsumerState<PosCounterScreen> {
  final GlobalKey<OpsAsyncSectionState<OrderSummary>> _sectionKey =
      GlobalKey<OpsAsyncSectionState<OrderSummary>>();

  @override
  Widget build(BuildContext context) {
    final session = ref.watch(authServiceProvider).current.session;
    final repository = ref.watch(ordersRepositoryProvider);

    if (session == null || !session.hasTenantContext) {
      return const SizedBox.shrink();
    }

    final outletId = session.activeOutlet?.id;

    return OpsMasterDataScaffold(
      title: 'Kasir — Pesanan',
      session: session,
      onBack: () => context.go(OpsRoutes.home),
      floatingAction: FloatingActionButton.extended(
        onPressed: () => context.go(OpsRoutes.counterNewOrder),
        icon: const Icon(Icons.add),
        label: const Text('Pesanan baru'),
      ),
      body: OpsAsyncSection<OrderSummary>(
        key: _sectionKey,
        queryKey: outletId,
        load: () => repository.orders(outletId: outletId),
        emptyTitle: 'Belum ada pesanan',
        emptyDescription:
            'Pesanan yang dibuat di outlet ini akan muncul di sini. Ketuk '
            '"Pesanan baru" untuk memulai.',
        builder: (context, items) => RefreshIndicator(
          onRefresh: () async => _sectionKey.currentState?.reload(),
          child: ListView.separated(
            itemCount: items.length,
            separatorBuilder: (_, _) => const Divider(height: 1),
            itemBuilder: (context, index) {
              final order = items[index];
              return ListTile(
                title: Text(order.orderNumber),
                subtitle: Text(order.total.formatted),
                trailing: StatusChip(
                  label: order.status.label,
                  icon: _statusIcon(order.status),
                  tone: _statusTone(order.status),
                ),
                onTap: () => context.go(
                  OpsRoutes.counterOrderDetailFor(order.id),
                ),
              );
            },
          ),
        ),
      ),
    );
  }

  IconData _statusIcon(OrderStatus status) => switch (status) {
    OrderStatus.draft => Icons.edit_note,
    OrderStatus.cancelled => Icons.cancel_outlined,
    OrderStatus.completed => Icons.check_circle_outline,
    _ => Icons.receipt_long_outlined,
  };

  StatusTone _statusTone(OrderStatus status) => switch (status) {
    OrderStatus.cancelled => StatusTone.neutral,
    OrderStatus.completed => StatusTone.neutral,
    _ => StatusTone.neutral,
  };
}
