import 'dart:async';

import 'package:aish_design_system/aish_design_system.dart';
import 'package:aish_domain/aish_domain.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../app.dart';
import '../master_data/master_data_views.dart';
import '../master_data/ops_master_data_scaffold.dart';
import '../routing/ops_routes.dart';
import 'production_providers.dart';
import 'production_views.dart';

/// THE PRODUCTION QUEUE (FR-071) — the operator's work list.
///
/// The list itself is a SERVER read, scoped to the active tenant and outlet by
/// the server (Rule 02); there is no client-side filtering by tenant. The
/// operator's WRITES are offline-first: they enqueue on the device and this
/// screen drives the sync worker and shows, honestly, what is pending, syncing,
/// or needs a human (Rule 29 rule 2).
class ProductionQueueScreen extends ConsumerStatefulWidget {
  const ProductionQueueScreen({
    this.initialFilter,
    this.title = 'Produksi',
    super.key,
  });

  /// When set, the queue opens pre-filtered — the quality-control worklist opens
  /// on [ProductionState.awaitingQc].
  final ProductionState? initialFilter;
  final String title;

  @override
  ConsumerState<ProductionQueueScreen> createState() =>
      _ProductionQueueScreenState();
}

class _ProductionQueueScreenState extends ConsumerState<ProductionQueueScreen> {
  final GlobalKey<OpsAsyncSectionState<ProductionJobSummary>> _sectionKey =
      GlobalKey<OpsAsyncSectionState<ProductionJobSummary>>();

  ProductionState? _filter;
  int _pending = 0;
  int _attention = 0;
  Timer? _timer;
  bool _syncing = false;

  @override
  void initState() {
    super.initState();
    _filter = widget.initialFilter;
    // The worker's reconnect hook, a first drain, and a periodic drain while the
    // production surface is open. Durable work outlives this screen; automatic
    // background sync while the surface is closed is deferred (not claimed).
    WidgetsBinding.instance.addPostFrameCallback((_) {
      final runtime = ref.read(productionRuntimeProvider);
      runtime?.worker.start();
      unawaited(_syncNow());
      _timer = Timer.periodic(
        const Duration(seconds: 20),
        (_) => unawaited(_syncNow()),
      );
    });
  }

  @override
  void dispose() {
    _timer?.cancel();
    super.dispose();
  }

  Future<void> _syncNow() async {
    final runtime = ref.read(productionRuntimeProvider);
    if (runtime == null || _syncing) {
      return;
    }
    setState(() => _syncing = true);
    await runtime.worker.drain();
    await _refreshBadges();
    if (!mounted) {
      return;
    }
    setState(() => _syncing = false);
    // A drain may have advanced a job's server state; reload the list so it is
    // never shown staler than the queue knows it to be.
    _sectionKey.currentState?.reload();
  }

  Future<void> _refreshBadges() async {
    final runtime = ref.read(productionRuntimeProvider);
    if (runtime == null) {
      return;
    }
    final unsynced = await runtime.queue.unsynced();
    final attention = await runtime.queue.needingAttention();
    if (!mounted) {
      return;
    }
    setState(() {
      _pending = unsynced.valueOrNull?.length ?? 0;
      _attention = attention.valueOrNull?.length ?? 0;
    });
  }

  @override
  Widget build(BuildContext context) {
    final session = ref.watch(authServiceProvider).current.session;
    final runtime = ref.watch(productionRuntimeProvider);
    if (session == null || !session.hasTenantContext || runtime == null) {
      return const SizedBox.shrink();
    }
    final repository = ref.watch(productionRepositoryProvider);
    final outletId = session.activeOutlet?.id;

    return OpsMasterDataScaffold(
      title: widget.title,
      session: session,
      onBack: () => context.go(OpsRoutes.home),
      actions: <Widget>[
        IconButton(
          tooltip: 'Sinkronkan sekarang',
          icon: _syncing
              ? const SizedBox(
                  width: 20,
                  height: 20,
                  child: CircularProgressIndicator(strokeWidth: 2),
                )
              : const Icon(Icons.sync_outlined),
          onPressed: _syncing ? null : () => unawaited(_syncNow()),
        ),
      ],
      body: Column(
        children: <Widget>[
          _FilterBar(
            selected: _filter,
            onChanged: (value) {
              setState(() => _filter = value);
            },
          ),
          if (_pending > 0 || _attention > 0)
            _PendingBanner(
              pending: _pending,
              attention: _attention,
              onOpen: () => context.go(OpsRoutes.productionSync),
            ),
          Expanded(
            child: OpsAsyncSection<ProductionJobSummary>(
              key: _sectionKey,
              // Reload when the outlet or the filter changes.
              queryKey: '$outletId|${_filter?.wireValue ?? 'all'}',
              load: () => repository.queue(state: _filter, perPage: 100),
              emptyTitle: 'Belum ada pekerjaan produksi',
              emptyDescription: _filter == null
                  ? 'Pekerjaan produksi untuk outlet ini akan muncul di sini.'
                  : 'Tidak ada pekerjaan pada tahap "${_filter!.label}".',
              builder: (context, items) => RefreshIndicator(
                onRefresh: () async => _sectionKey.currentState?.reload(),
                child: ListView.separated(
                  itemCount: items.length,
                  separatorBuilder: (_, _) => const Divider(height: 1),
                  itemBuilder: (context, index) {
                    final job = items[index];
                    return ListTile(
                      title: Text('Pesanan ${job.orderId}'),
                      subtitle: Text('Versi ${job.version}'),
                      trailing: productionStateChip(job.state),
                      onTap: () =>
                          context.go(OpsRoutes.productionJobDetailFor(job.id)),
                    );
                  },
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _FilterBar extends StatelessWidget {
  const _FilterBar({required this.selected, required this.onChanged});

  final ProductionState? selected;
  final ValueChanged<ProductionState?> onChanged;

  @override
  Widget build(BuildContext context) {
    return SingleChildScrollView(
      scrollDirection: Axis.horizontal,
      padding: EdgeInsets.symmetric(
        horizontal: AishSpacing.space3,
        vertical: AishSpacing.space2,
      ),
      child: Row(
        children: <Widget>[
          ChoiceChip(
            label: const Text('Semua'),
            selected: selected == null,
            onSelected: (_) => onChanged(null),
          ),
          for (final state in ProductionState.values) ...<Widget>[
            SizedBox(width: AishSpacing.space2),
            ChoiceChip(
              label: Text(state.label),
              selected: selected == state,
              onSelected: (_) => onChanged(state),
            ),
          ],
        ],
      ),
    );
  }
}

class _PendingBanner extends StatelessWidget {
  const _PendingBanner({
    required this.pending,
    required this.attention,
    required this.onOpen,
  });

  final int pending;
  final int attention;
  final VoidCallback onOpen;

  @override
  Widget build(BuildContext context) {
    final needsHuman = attention > 0;
    return Material(
      color: needsHuman
          ? Theme.of(context).colorScheme.errorContainer
          : Theme.of(context).colorScheme.surfaceContainerHighest,
      child: InkWell(
        onTap: onOpen,
        child: Padding(
          padding: EdgeInsets.all(AishSpacing.space3),
          child: Row(
            children: <Widget>[
              Icon(
                needsHuman ? Icons.priority_high_outlined : Icons.sync_outlined,
              ),
              SizedBox(width: AishSpacing.space2),
              Expanded(
                child: Text(
                  needsHuman
                      ? '$attention perintah perlu tindakan Anda. Ketuk untuk membuka pusat sinkronisasi.'
                      : '$pending perintah menunggu sinkronisasi. Ketuk untuk memantau.',
                ),
              ),
              const Icon(Icons.chevron_right),
            ],
          ),
        ),
      ),
    );
  }
}
