import 'dart:async';

import 'package:aish_design_system/aish_design_system.dart';
import 'package:aish_domain/aish_domain.dart';
import 'package:aish_offline_sync/aish_offline_sync.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../app.dart';
import '../master_data/master_data_views.dart';
import '../master_data/ops_master_data_scaffold.dart';
import '../routing/ops_routes.dart';
import 'production_ids.dart';
import 'production_providers.dart';
import 'production_views.dart';

/// THE PRODUCTION BATCH LIST (FR-074) — the operator's batches for the active
/// outlet.
///
/// The list is a SERVER read, scoped to the active tenant and outlet by the
/// server (Rule 02). Creating a batch is OFFLINE-FIRST: it enqueues on the device
/// and this screen drives the sync worker and shows, honestly, what is pending or
/// needs a human (Rule 29 rule 2). Creating requires an active outlet — a batch
/// groups items of one outlet.
class ProductionBatchListScreen extends ConsumerStatefulWidget {
  const ProductionBatchListScreen({super.key});

  @override
  ConsumerState<ProductionBatchListScreen> createState() =>
      _ProductionBatchListScreenState();
}

class _ProductionBatchListScreenState
    extends ConsumerState<ProductionBatchListScreen> {
  final GlobalKey<OpsAsyncSectionState<ProductionBatchSummary>> _sectionKey =
      GlobalKey<OpsAsyncSectionState<ProductionBatchSummary>>();

  int _pending = 0;
  int _attention = 0;
  bool _syncing = false;
  Timer? _timer;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      ref.read(productionRuntimeProvider)?.worker.start();
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

  Future<void> _createBatch() async {
    final runtime = ref.read(productionRuntimeProvider);
    if (runtime == null) {
      return;
    }
    if (runtime.outletId == null) {
      _snack('Pilih outlet aktif sebelum membuat batch.');
      return;
    }
    final input = await showDialog<({String code, String stage})>(
      context: context,
      builder: (_) => const _CreateBatchDialog(),
    );
    if (input == null) {
      return;
    }
    // Enqueue an offline create-batch command; the worker sends it and the
    // server owns the result. Nothing is shown as created until it syncs.
    await runtime.queue.enqueue(
      ProductionCommand(
        clientReference: newClientReference(),
        tenantId: runtime.tenantId,
        userId: runtime.userId,
        outletId: runtime.outletId,
        type: ProductionCommandType.createBatch,
        createdAtUtc: DateTime.now().toUtc(),
        payload: <String, Object?>{'code': input.code, 'stage': input.stage},
      ),
    );
    if (!mounted) {
      return;
    }
    _snack('Batch disimpan di perangkat dan sedang disinkronkan.');
    await _syncNow();
  }

  void _snack(String message) {
    if (!mounted) {
      return;
    }
    ScaffoldMessenger.of(
      context,
    ).showSnackBar(SnackBar(content: Text(message)));
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
    final canOperate = session.allows(Permission.productionOperate);

    return OpsMasterDataScaffold(
      title: 'Batch produksi',
      session: session,
      onBack: () => context.go(OpsRoutes.production),
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
      // A control the operator may not use is not rendered (Rule 28 rule 5):
      // the create FAB appears only for a role holding production.operate.
      floatingAction: canOperate
          ? FloatingActionButton.extended(
              key: const Key('batch-create-fab'),
              onPressed: () => unawaited(_createBatch()),
              icon: const Icon(Icons.add),
              label: const Text('Batch baru'),
            )
          : null,
      body: Column(
        children: <Widget>[
          if (_pending > 0 || _attention > 0)
            _BatchPendingBanner(
              pending: _pending,
              attention: _attention,
              onOpen: () => context.go(OpsRoutes.productionSync),
            ),
          Expanded(
            child: OpsAsyncSection<ProductionBatchSummary>(
              key: _sectionKey,
              queryKey: '$outletId|batches',
              load: () => repository.batches(perPage: 100),
              emptyTitle: 'Belum ada batch',
              emptyDescription:
                  'Batch untuk memproses beberapa item sekaligus akan muncul di sini.',
              builder: (context, items) => RefreshIndicator(
                onRefresh: () async => _sectionKey.currentState?.reload(),
                child: ListView.separated(
                  itemCount: items.length,
                  separatorBuilder: (_, _) => const Divider(height: 1),
                  itemBuilder: (context, index) {
                    final batch = items[index];
                    return ListTile(
                      key: Key('batch-row-${batch.id}'),
                      title: Text(batch.code),
                      subtitle: Text(
                        'Tahap ${batch.stage} · ${batch.itemCount} item',
                      ),
                      trailing: batchStatusChip(batch.status),
                      onTap: () => context.go(
                        OpsRoutes.productionBatchDetailFor(batch.id),
                      ),
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

class _CreateBatchDialog extends StatefulWidget {
  const _CreateBatchDialog();

  @override
  State<_CreateBatchDialog> createState() => _CreateBatchDialogState();
}

class _CreateBatchDialogState extends State<_CreateBatchDialog> {
  final TextEditingController _code = TextEditingController();
  String _stage = _stages.first;

  static const List<String> _stages = <String>[
    'SORTING',
    'WASHING',
    'DRYING',
    'FINISHING',
  ];

  @override
  void dispose() {
    _code.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: const Text('Batch baru'),
      content: Column(
        mainAxisSize: MainAxisSize.min,
        children: <Widget>[
          TextField(
            key: const Key('batch-code-field'),
            controller: _code,
            decoration: const InputDecoration(labelText: 'Kode batch'),
          ),
          SizedBox(height: AishSpacing.space3),
          DropdownButtonFormField<String>(
            key: const Key('batch-stage-field'),
            initialValue: _stage,
            decoration: const InputDecoration(labelText: 'Tahap'),
            items: <DropdownMenuItem<String>>[
              for (final stage in _stages)
                DropdownMenuItem<String>(value: stage, child: Text(stage)),
            ],
            onChanged: (value) => setState(() => _stage = value ?? _stage),
          ),
        ],
      ),
      actions: <Widget>[
        TextButton(
          onPressed: () => Navigator.of(context).pop(),
          child: const Text('Batal'),
        ),
        FilledButton(
          key: const Key('batch-create-confirm'),
          onPressed: () {
            final code = _code.text.trim();
            if (code.isEmpty) {
              return;
            }
            Navigator.of(context).pop((code: code, stage: _stage));
          },
          child: const Text('Simpan'),
        ),
      ],
    );
  }
}

class _BatchPendingBanner extends StatelessWidget {
  const _BatchPendingBanner({
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
