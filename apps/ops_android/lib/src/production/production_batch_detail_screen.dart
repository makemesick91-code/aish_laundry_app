import 'dart:async';

import 'package:aish_core/aish_core.dart';
import 'package:aish_design_system/aish_design_system.dart';
import 'package:aish_domain/aish_domain.dart';
import 'package:aish_offline_sync/aish_offline_sync.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../app.dart';
import '../master_data/ops_master_data_scaffold.dart';
import '../routing/ops_routes.dart';
import 'production_ids.dart';
import 'production_providers.dart';
import 'production_views.dart';

/// ONE PRODUCTION BATCH (FR-074) — its status, current members, append-only
/// timeline, and the state-valid, permission-gated actions an operator may take.
///
/// OFFLINE-FIRST. Add-item, remove-item and close each ENQUEUE a durable command
/// and drive the sync worker; none claims success from local state (Rule 29 hard
/// rule 1). While a command for this batch is unsynced, new actions are withheld
/// and the operator is pointed at the honest sync state. A CLOSED batch is
/// immutable — no mutating action is offered.
class ProductionBatchDetailScreen extends ConsumerStatefulWidget {
  const ProductionBatchDetailScreen({required this.batchId, super.key});

  final String batchId;

  @override
  ConsumerState<ProductionBatchDetailScreen> createState() =>
      _ProductionBatchDetailScreenState();
}

class _ProductionBatchDetailScreenState
    extends ConsumerState<ProductionBatchDetailScreen> {
  ProductionBatchDetail? _detail;
  ProductionCommand? _pending;
  Failure? _failure;
  bool _loading = true;
  bool _busy = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => unawaited(_load()));
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _failure = null;
    });
    final repository = ref.read(productionRepositoryProvider);
    final runtime = ref.read(productionRuntimeProvider);

    final detailResult = await repository.batch(widget.batchId);
    ProductionCommand? pending;
    if (runtime != null) {
      final all = await runtime.queue.all();
      pending = all.valueOrNull
          ?.where(
            (c) =>
                c.batchId == widget.batchId &&
                c.status != ProductionCommandStatus.synced,
          )
          .fold<ProductionCommand?>(
            null,
            (latest, c) => latest == null
                ? c
                : (c.createdAtUtc.isAfter(latest.createdAtUtc) ? c : latest),
          );
    }

    if (!mounted) {
      return;
    }
    setState(() {
      _loading = false;
      _detail = detailResult.valueOrNull;
      _failure = detailResult.failureOrNull;
      _pending = pending;
    });
  }

  @override
  Widget build(BuildContext context) {
    final session = ref.watch(authServiceProvider).current.session;
    if (session == null || !session.hasTenantContext) {
      return const SizedBox.shrink();
    }
    return OpsMasterDataScaffold(
      title: _detail == null ? 'Batch produksi' : 'Batch ${_detail!.summary.code}',
      session: session,
      onBack: () => context.go(OpsRoutes.productionBatches),
      body: _buildBody(context, session),
    );
  }

  Widget _buildBody(BuildContext context, SessionState session) {
    if (_loading) {
      return const Center(child: CircularProgressIndicator());
    }
    final detail = _detail;
    if (detail == null) {
      final failure = _failure;
      final denied = failure?.kind == FailureKind.authorization;
      return StateMessage(
        title: denied
            ? 'Anda tidak memiliki akses ke batch ini'
            : 'Batch tidak dapat dimuat',
        description: denied
            ? 'Hubungi admin tenant Anda bila Anda memerlukan akses.'
            : 'Muat ulang layar ini; bila berulang, hubungi admin tenant Anda.',
        icon: denied ? Icons.lock_outline : Icons.error_outline,
        tone: denied ? StatusTone.warning : StatusTone.danger,
        recoveryLabel: denied ? null : 'Muat ulang',
        onRecover: denied ? null : _load,
        supportReference: failure?.correlationId,
      );
    }

    final canOperate = session.allows(Permission.productionOperate);
    final isOpen = detail.summary.isOpen;
    final mutable = canOperate && isOpen && _pending == null;

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        padding: EdgeInsets.all(AishSpacing.space4),
        children: <Widget>[
          Row(
            children: <Widget>[
              Expanded(
                child: Text(
                  detail.summary.code,
                  style: Theme.of(context).textTheme.titleLarge,
                  overflow: TextOverflow.ellipsis,
                ),
              ),
              SizedBox(width: AishSpacing.space2),
              batchStatusChip(detail.summary.status),
            ],
          ),
          SizedBox(height: AishSpacing.space1),
          Text('Tahap ${detail.summary.stage} · versi ${detail.summary.version}'),
          if (_pending != null) ...<Widget>[
            SizedBox(height: AishSpacing.space3),
            _PendingBatchCommandCard(
              command: _pending!,
              onReload: _load,
              onOpenSync: () => context.go(OpsRoutes.productionSync),
            ),
          ],
          SizedBox(height: AishSpacing.space4),
          Row(
            children: <Widget>[
              Expanded(
                child: Text(
                  'Item (${detail.items.length})',
                  style: Theme.of(context).textTheme.titleMedium,
                ),
              ),
            ],
          ),
          if (detail.items.isEmpty)
            const Padding(
              padding: EdgeInsets.symmetric(vertical: 8),
              child: Text('Belum ada item di batch ini.'),
            )
          else
            ...detail.items.map(
              (item) => ListTile(
                key: Key('batch-member-${item.productionItemId}'),
                dense: true,
                title: Text(item.productionItemId),
                subtitle: Text(
                  'Tahap ${item.stage ?? '—'}'
                  '${item.serviceType == null ? '' : ' · ${item.serviceType!.label}'}',
                ),
                trailing: mutable
                    ? IconButton(
                        tooltip: 'Keluarkan item ${item.productionItemId}',
                        icon: const Icon(Icons.remove_circle_outline),
                        onPressed: () =>
                            unawaited(_removeItem(detail, item.productionItemId)),
                      )
                    : null,
              ),
            ),
          SizedBox(height: AishSpacing.space4),
          Text('Riwayat', style: Theme.of(context).textTheme.titleMedium),
          if (detail.timeline.isEmpty)
            const Padding(
              padding: EdgeInsets.symmetric(vertical: 8),
              child: Text('Belum ada riwayat.'),
            )
          else
            ...detail.timeline.map(
              (e) => ListTile(
                dense: true,
                leading: const Icon(Icons.history_outlined, size: 20),
                title: Text(e.type),
                subtitle: Text(e.occurredAt ?? ''),
              ),
            ),
          SizedBox(height: AishSpacing.space4),
          if (mutable) ...<Widget>[
            PrimaryAction(
              key: const Key('batch-add-item'),
              label: 'Tambahkan item ke batch',
              icon: Icons.add,
              isBusy: _busy,
              onPressed: _busy ? () {} : () => unawaited(_addItem(detail)),
            ),
            SizedBox(height: AishSpacing.space2),
            PrimaryAction(
              key: const Key('batch-close'),
              label: 'Tutup batch',
              icon: Icons.inventory_2_outlined,
              isBusy: _busy,
              onPressed: _busy ? () {} : () => unawaited(_close(detail)),
            ),
          ] else if (!isOpen)
            const Text('Batch sudah ditutup dan tidak dapat diubah.'),
        ],
      ),
    );
  }

  // --- action handlers: each ENQUEUES a durable command, then syncs ----------

  ProductionCommand _base(
    ProductionBatchDetail detail,
    ProductionCommandType type, {
    String? itemId,
    Map<String, Object?> payload = const <String, Object?>{},
  }) {
    final runtime = ref.read(productionRuntimeProvider)!;
    return ProductionCommand(
      clientReference: newClientReference(),
      tenantId: runtime.tenantId,
      userId: runtime.userId,
      batchId: widget.batchId,
      itemId: itemId,
      outletId: runtime.outletId,
      type: type,
      createdAtUtc: DateTime.now().toUtc(),
      expectedVersion: detail.summary.version,
      payload: payload,
    );
  }

  Future<void> _addItem(ProductionBatchDetail detail) async {
    final itemId = await _promptItemId();
    if (itemId == null || !mounted) {
      return;
    }
    await _enqueue(
      _base(detail, ProductionCommandType.addBatchItem, itemId: itemId),
    );
  }

  Future<void> _removeItem(
    ProductionBatchDetail detail,
    String productionItemId,
  ) => _enqueue(
    _base(
      detail,
      ProductionCommandType.removeBatchItem,
      itemId: productionItemId,
    ),
  );

  Future<void> _close(ProductionBatchDetail detail) =>
      _enqueue(_base(detail, ProductionCommandType.closeBatch));

  Future<void> _enqueue(ProductionCommand command) async {
    final runtime = ref.read(productionRuntimeProvider);
    if (runtime == null) {
      return;
    }
    setState(() => _busy = true);
    final enqueued = await runtime.queue.enqueue(command);
    if (enqueued.isErr) {
      if (!mounted) {
        return;
      }
      setState(() => _busy = false);
      _snack('Perintah gagal disimpan di perangkat. Coba lagi.');
      return;
    }
    await runtime.worker.drain();
    final resolved = await runtime.queue.byReference(command.clientReference);
    await _load();
    if (!mounted) {
      return;
    }
    setState(() => _busy = false);
    _announce(resolved.valueOrNull?.status);
  }

  void _announce(ProductionCommandStatus? status) {
    final message = switch (status) {
      ProductionCommandStatus.synced => 'Perubahan tersinkron.',
      ProductionCommandStatus.pending ||
      ProductionCommandStatus.syncing ||
      ProductionCommandStatus.retryWait =>
        'Tersimpan di perangkat, menunggu sinkronisasi.',
      ProductionCommandStatus.conflict =>
        'Data sudah diubah di tempat lain. Muat ulang lalu terapkan kembali.',
      ProductionCommandStatus.failedPermanent =>
        'Perintah ditolak server. Buka pusat sinkronisasi untuk menyelesaikannya.',
      null => null,
    };
    if (message != null) {
      _snack(message);
    }
  }

  void _snack(String message) => ScaffoldMessenger.of(
    context,
  ).showSnackBar(SnackBar(content: Text(message)));

  Future<String?> _promptItemId() async {
    final controller = TextEditingController();
    final value = await showDialog<String>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Tambahkan item'),
        content: TextField(
          key: const Key('batch-add-item-field'),
          controller: controller,
          autofocus: true,
          decoration: const InputDecoration(
            labelText: 'ID item produksi',
            hintText: 'Item yang sedang pada tahap yang sama',
          ),
        ),
        actions: <Widget>[
          TextButton(
            onPressed: () => Navigator.of(dialogContext).pop(),
            child: const Text('Batal'),
          ),
          FilledButton(
            key: const Key('batch-add-item-confirm'),
            onPressed: () =>
                Navigator.of(dialogContext).pop(controller.text.trim()),
            child: const Text('Tambahkan'),
          ),
        ],
      ),
    );
    controller.dispose();
    if (value == null || value.isEmpty) {
      return null;
    }
    return value;
  }
}

/// The honest pending/conflict/failed card for a command on this batch — never
/// success styling for anything but a synced command (Rule 29).
class _PendingBatchCommandCard extends StatelessWidget {
  const _PendingBatchCommandCard({
    required this.command,
    required this.onReload,
    required this.onOpenSync,
  });

  final ProductionCommand command;
  final VoidCallback onReload;
  final VoidCallback onOpenSync;

  @override
  Widget build(BuildContext context) {
    final status = command.status;
    final needsHuman = status.needsHuman;
    return Semantics(
      liveRegion: true,
      container: true,
      child: Card(
        margin: EdgeInsets.zero,
        child: Padding(
          padding: EdgeInsets.all(AishSpacing.space3),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              Wrap(
                spacing: AishSpacing.space2,
                runSpacing: AishSpacing.space1,
                crossAxisAlignment: WrapCrossAlignment.center,
                children: <Widget>[
                  Text(productionCommandLabel(command.type)),
                  commandStatusChip(status),
                ],
              ),
              SizedBox(height: AishSpacing.space2),
              Text(_message(status)),
              if (needsHuman) ...<Widget>[
                SizedBox(height: AishSpacing.space2),
                Wrap(
                  spacing: AishSpacing.space2,
                  children: <Widget>[
                    if (status == ProductionCommandStatus.conflict)
                      TextButton.icon(
                        onPressed: onReload,
                        icon: const Icon(Icons.refresh_outlined),
                        label: const Text('Muat ulang'),
                      ),
                    TextButton.icon(
                      onPressed: onOpenSync,
                      icon: const Icon(Icons.sync_problem_outlined),
                      label: const Text('Pusat sinkronisasi'),
                    ),
                  ],
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }

  String _message(ProductionCommandStatus status) => switch (status) {
    ProductionCommandStatus.pending =>
      'Perintah tersimpan di perangkat dan belum dikirim ke server.',
    ProductionCommandStatus.syncing => 'Sedang dikirim ke server…',
    ProductionCommandStatus.retryWait =>
      'Pengiriman gagal sementara. Akan dicoba lagi otomatis.',
    ProductionCommandStatus.synced => 'Tersinkron.',
    ProductionCommandStatus.conflict =>
      'Batch ini sudah berubah sejak Anda membukanya. Perubahan Anda BELUM '
          'diterapkan. Muat ulang untuk melihat keadaan terbaru, lalu terapkan '
          'kembali bila masih diperlukan.',
    ProductionCommandStatus.failedPermanent =>
      'Server menolak perintah ini dan tidak akan mencobanya lagi. Buka pusat '
          'sinkronisasi untuk menyelesaikannya.',
  };
}
