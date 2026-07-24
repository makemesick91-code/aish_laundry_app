import 'dart:async';

import 'package:aish_design_system/aish_design_system.dart';
import 'package:aish_offline_sync/aish_offline_sync.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../app.dart';
import '../master_data/ops_master_data_scaffold.dart';
import '../routing/ops_routes.dart';
import 'production_providers.dart';
import 'production_views.dart';

/// THE PRODUCTION SYNC CENTRE — the always-reachable honest view of what is
/// pending, syncing, or needs a human (Rule 29 rule 2). A queued command is
/// never shown as committed; only a SYNCED command reads as done.
///
/// A conflict is NOT silently retried here: the recovery is to reload the job
/// and re-issue, or to resolve the command with a recorded reason. A permanent
/// failure is resolved manually. There is no bulk clear — removing a queued
/// command is a deliberate, reasoned act (Rule 07 rule 4's principle).
class ProductionSyncCenterScreen extends ConsumerStatefulWidget {
  const ProductionSyncCenterScreen({super.key});

  @override
  ConsumerState<ProductionSyncCenterScreen> createState() =>
      _ProductionSyncCenterScreenState();
}

class _ProductionSyncCenterScreenState
    extends ConsumerState<ProductionSyncCenterScreen> {
  List<ProductionCommand> _commands = const <ProductionCommand>[];
  bool _loading = true;
  bool _busy = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => unawaited(_load()));
  }

  Future<void> _load() async {
    final runtime = ref.read(productionRuntimeProvider);
    if (runtime == null) {
      return;
    }
    setState(() => _loading = true);
    final all = await runtime.queue.all();
    if (!mounted) {
      return;
    }
    setState(() {
      _loading = false;
      // Show everything not yet a canonical success; synced rows are done.
      _commands = (all.valueOrNull ?? const <ProductionCommand>[])
          .where((c) => c.status != ProductionCommandStatus.synced)
          .toList(growable: false);
    });
  }

  Future<void> _syncNow() async {
    final runtime = ref.read(productionRuntimeProvider);
    if (runtime == null || _busy) {
      return;
    }
    setState(() => _busy = true);
    await runtime.worker.drain();
    await _load();
    if (mounted) {
      setState(() => _busy = false);
    }
  }

  Future<void> _retry(ProductionCommand command) async {
    final runtime = ref.read(productionRuntimeProvider);
    if (runtime == null) {
      return;
    }
    setState(() => _busy = true);
    await runtime.worker.retryNow(command.clientReference);
    await _load();
    if (mounted) {
      setState(() => _busy = false);
    }
  }

  Future<void> _resolve(ProductionCommand command) async {
    final reason = await _promptReason();
    if (reason == null || !mounted) {
      return;
    }
    final runtime = ref.read(productionRuntimeProvider);
    if (runtime == null) {
      return;
    }
    await runtime.queue.resolveManually(
      clientReference: command.clientReference,
      reason: reason,
    );
    await _load();
  }

  @override
  Widget build(BuildContext context) {
    final session = ref.watch(authServiceProvider).current.session;
    if (session == null || !session.hasTenantContext) {
      return const SizedBox.shrink();
    }

    return OpsMasterDataScaffold(
      title: 'Pusat sinkronisasi',
      session: session,
      onBack: () => context.go(OpsRoutes.production),
      actions: <Widget>[
        IconButton(
          tooltip: 'Sinkronkan sekarang',
          icon: const Icon(Icons.sync_outlined),
          onPressed: _busy ? null : () => unawaited(_syncNow()),
        ),
      ],
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _commands.isEmpty
          ? const StateMessage(
              title: 'Semua perintah tersinkron',
              description:
                  'Tidak ada perintah produksi yang menunggu. Perintah yang '
                  'Anda buat saat luring akan muncul di sini hingga tersinkron.',
              icon: Icons.cloud_done_outlined,
              tone: StatusTone.success,
            )
          : RefreshIndicator(
              onRefresh: _load,
              child: ListView.separated(
                itemCount: _commands.length,
                separatorBuilder: (_, _) => const Divider(height: 1),
                itemBuilder: (context, index) =>
                    _commandTile(context, _commands[index]),
              ),
            ),
    );
  }

  Widget _commandTile(BuildContext context, ProductionCommand command) {
    return Padding(
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
              commandStatusChip(command.status),
            ],
          ),
          Text(
            'Pesanan ${command.orderId ?? command.jobId}',
            style: Theme.of(context).textTheme.bodySmall,
          ),
          SizedBox(height: AishSpacing.space2),
          Wrap(
            spacing: AishSpacing.space2,
            children: <Widget>[
              if (command.status == ProductionCommandStatus.retryWait ||
                  command.status == ProductionCommandStatus.pending)
                TextButton.icon(
                  onPressed: _busy ? null : () => unawaited(_retry(command)),
                  icon: const Icon(Icons.refresh_outlined),
                  label: const Text('Coba lagi'),
                ),
              if (command.status == ProductionCommandStatus.conflict &&
                  command.jobId != null)
                TextButton.icon(
                  onPressed: () => context.go(
                    OpsRoutes.productionJobDetailFor(command.jobId!),
                  ),
                  icon: const Icon(Icons.open_in_new_outlined),
                  label: const Text('Buka pekerjaan'),
                ),
              // FR-074: a batch command in conflict opens the batch, not a job.
              if (command.status == ProductionCommandStatus.conflict &&
                  command.batchId != null)
                TextButton.icon(
                  onPressed: () => context.go(
                    OpsRoutes.productionBatchDetailFor(command.batchId!),
                  ),
                  icon: const Icon(Icons.open_in_new_outlined),
                  label: const Text('Buka batch'),
                ),
              if (command.status.needsHuman)
                TextButton.icon(
                  onPressed: () => unawaited(_resolve(command)),
                  icon: const Icon(Icons.delete_outline),
                  label: const Text('Selesaikan (hapus)'),
                ),
            ],
          ),
        ],
      ),
    );
  }

  Future<String?> _promptReason() async {
    final controller = TextEditingController();
    final value = await showDialog<String>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Selesaikan perintah'),
        content: TextField(
          controller: controller,
          autofocus: true,
          decoration: const InputDecoration(
            labelText: 'Alasan menghapus perintah',
            hintText: 'Wajib diisi',
          ),
        ),
        actions: <Widget>[
          TextButton(
            onPressed: () => Navigator.of(dialogContext).pop(),
            child: const Text('Tutup'),
          ),
          FilledButton(
            onPressed: () =>
                Navigator.of(dialogContext).pop(controller.text.trim()),
            child: const Text('Hapus perintah'),
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
