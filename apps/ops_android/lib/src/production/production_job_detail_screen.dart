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

/// The canonical item stages an operator may advance through (Rule 19 production
/// stages). These are the exact strings the backend records and its tests use.
const List<String> _stages = <String>[
  'SORTING',
  'WASHING',
  'DRYING',
  'FINISHING',
];

/// ONE PRODUCTION JOB — its state, items, append-only timeline, and the
/// state-valid, permission-gated actions an operator may take against it.
///
/// OFFLINE-FIRST. An action ENQUEUES a durable command and drives the sync
/// worker; it never claims success from local state. The job's server state,
/// the READY_FOR_PICKUP transition included, is shown only after the command
/// reaches a canonical SYNCED (Rule 29 hard rules 1 and 4). While a command for
/// this job is unsynced, new actions are withheld and the operator is pointed at
/// the honest sync state instead of being allowed to stack commands on a version
/// that has already moved.
class ProductionJobDetailScreen extends ConsumerStatefulWidget {
  const ProductionJobDetailScreen({required this.jobId, super.key});

  final String jobId;

  @override
  ConsumerState<ProductionJobDetailScreen> createState() =>
      _ProductionJobDetailScreenState();
}

class _ProductionJobDetailScreenState
    extends ConsumerState<ProductionJobDetailScreen> {
  ProductionJobDetail? _detail;
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

    final detailResult = await repository.job(widget.jobId);
    ProductionCommand? pending;
    if (runtime != null) {
      final all = await runtime.queue.all();
      pending = all.valueOrNull
          ?.where(
            (c) =>
                c.jobId == widget.jobId &&
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
      title: _detail == null
          ? 'Pekerjaan produksi'
          : 'Pesanan ${_detail!.summary.orderId}',
      session: session,
      onBack: () => context.go(OpsRoutes.production),
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
      // DENIED is indistinguishable from absent across a tenant boundary
      // (Rule 48, Rule 32): a foreign job renders exactly like a missing one.
      final denied = failure?.kind == FailureKind.authorization;
      return StateMessage(
        title: denied
            ? 'Anda tidak memiliki akses ke pekerjaan ini'
            : 'Pekerjaan tidak dapat dimuat',
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

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        padding: EdgeInsets.all(AishSpacing.space4),
        children: <Widget>[
          Row(
            children: <Widget>[
              Expanded(
                child: Text(
                  'Pesanan ${detail.summary.orderId}',
                  style: Theme.of(context).textTheme.titleLarge,
                  overflow: TextOverflow.ellipsis,
                ),
              ),
              SizedBox(width: AishSpacing.space2),
              productionStateChip(detail.state),
            ],
          ),
          if (detail.summary.blockReasonCode != null) ...<Widget>[
            SizedBox(height: AishSpacing.space2),
            Text('Alasan ditahan: ${detail.summary.blockReasonCode}'),
          ],
          if (_pending != null) ...<Widget>[
            SizedBox(height: AishSpacing.space3),
            _PendingCommandCard(
              command: _pending!,
              onReload: _load,
              onOpenSync: () => context.go(OpsRoutes.productionSync),
            ),
          ],
          SizedBox(height: AishSpacing.space4),
          Text('Item', style: Theme.of(context).textTheme.titleMedium),
          ...detail.items.map(
            (item) => ListTile(
              dense: true,
              title: Text(item.serviceType.label),
              subtitle: Text(
                item.serviceType == ProductionServiceType.kiloan
                    ? 'Tahap ${item.stage ?? '—'} · ${item.quantityDone ?? '0'} kg'
                    : 'Tahap ${item.stage ?? '—'} · ${item.unitsDone}/${item.unitsTotal} unit',
              ),
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
          _actions(context, session, detail),
        ],
      ),
    );
  }

  Widget _actions(
    BuildContext context,
    SessionState session,
    ProductionJobDetail detail,
  ) {
    // While a command for this job is unsynced, do not offer new actions: they
    // would be issued against a version that has already moved. The pending card
    // above already names the honest state and its recovery.
    if (_pending != null) {
      return const SizedBox.shrink();
    }

    final canOperate = session.allows(Permission.productionOperate);
    final canQc = session.allows(Permission.productionQc);
    final state = detail.state;

    final buttons = <Widget>[];

    if (canOperate) {
      switch (state) {
        case ProductionState.created:
        case ProductionState.inProgress:
          buttons.add(
            _action(
              'Lanjutkan tahap',
              Icons.arrow_forward,
              () => _advance(detail),
            ),
          );
          if (state == ProductionState.inProgress) {
            buttons.add(
              _action(
                'Kirim ke kendali mutu',
                Icons.fact_check_outlined,
                () => _sendToQc(detail),
              ),
            );
            buttons.add(
              _action(
                'Tahan pekerjaan',
                Icons.pause_circle_outline,
                () => _block(detail),
              ),
            );
          }
        case ProductionState.blocked:
          buttons.add(
            _action(
              'Lanjutkan kembali',
              Icons.play_arrow_outlined,
              () => _resume(detail),
            ),
          );
        case ProductionState.closed:
          buttons.add(
            _action(
              'Tandai siap diambil',
              Icons.inventory_2_outlined,
              () => _markReady(detail),
            ),
          );
        case ProductionState.awaitingQc:
        case ProductionState.reworkInProgress:
        case ProductionState.abandoned:
          break;
      }
    }

    if (canQc) {
      switch (state) {
        case ProductionState.awaitingQc:
          buttons.add(
            _action(
              'Catat kendali mutu',
              Icons.rule_folder_outlined,
              () => _recordQc(detail),
            ),
          );
        case ProductionState.reworkInProgress:
          buttons.add(
            _action(
              'Selesaikan pengerjaan ulang',
              Icons.restart_alt_outlined,
              () => _completeRework(detail),
            ),
          );
        default:
          break;
      }
    }

    if (buttons.isEmpty) {
      return const SizedBox.shrink();
    }

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        for (final b in buttons) ...<Widget>[
          b,
          SizedBox(height: AishSpacing.space2),
        ],
      ],
    );
  }

  Widget _action(String label, IconData icon, VoidCallback onPressed) =>
      PrimaryAction(
        label: label,
        icon: icon,
        isBusy: _busy,
        onPressed: _busy ? () {} : onPressed,
      );

  // --- action handlers: each ENQUEUES a durable command, then syncs ----------

  ProductionCommand _baseCommand(
    ProductionJobDetail detail,
    ProductionCommandType type,
    Map<String, Object?> payload,
  ) {
    final runtime = ref.read(productionRuntimeProvider)!;
    return ProductionCommand(
      clientReference: newClientReference(),
      tenantId: runtime.tenantId,
      userId: runtime.userId,
      jobId: widget.jobId,
      outletId: runtime.outletId,
      orderId: detail.summary.orderId,
      type: type,
      createdAtUtc: DateTime.now().toUtc(),
      expectedVersion: detail.version,
      payload: payload,
    );
  }

  Future<void> _advance(ProductionJobDetail detail) async {
    final stage = await showModalBottomSheet<String>(
      context: context,
      builder: (_) => _StagePicker(
        current: detail.items.isEmpty ? null : detail.items.first.stage,
      ),
    );
    if (stage == null || !mounted) {
      return;
    }
    await _enqueue(
      _baseCommand(detail, ProductionCommandType.advance, <String, Object?>{
        'stage': stage,
      }),
    );
  }

  Future<void> _sendToQc(ProductionJobDetail detail) => _enqueue(
    _baseCommand(
      detail,
      ProductionCommandType.sendToQc,
      const <String, Object?>{},
    ),
  );

  Future<void> _resume(ProductionJobDetail detail) => _enqueue(
    _baseCommand(
      detail,
      ProductionCommandType.resume,
      const <String, Object?>{},
    ),
  );

  Future<void> _markReady(ProductionJobDetail detail) => _enqueue(
    _baseCommand(
      detail,
      ProductionCommandType.markReady,
      const <String, Object?>{},
    ),
  );

  Future<void> _block(ProductionJobDetail detail) async {
    final reason = await _promptReason(
      title: 'Tahan pekerjaan',
      hint: 'Kode alasan (mis. MENUNGGU_ITEM)',
    );
    if (reason == null || !mounted) {
      return;
    }
    await _enqueue(
      _baseCommand(detail, ProductionCommandType.block, <String, Object?>{
        'reason_code': reason,
      }),
    );
  }

  Future<void> _completeRework(ProductionJobDetail detail) async {
    final reason = await _promptReason(
      title: 'Selesaikan pengerjaan ulang',
      hint: 'Kode alasan (mis. SUDAH_DIULANG)',
    );
    if (reason == null || !mounted) {
      return;
    }
    await _enqueue(
      _baseCommand(
        detail,
        ProductionCommandType.completeRework,
        <String, Object?>{'reason_code': reason},
      ),
    );
  }

  Future<void> _recordQc(ProductionJobDetail detail) async {
    final outcome = await showModalBottomSheet<_QcOutcome>(
      context: context,
      isScrollControlled: true,
      builder: (_) => const _QcSheet(),
    );
    if (outcome == null || !mounted) {
      return;
    }
    await _enqueue(
      _baseCommand(detail, ProductionCommandType.recordQc, <String, Object?>{
        'verdict': outcome.verdict.wireValue,
        if (outcome.defectReasonCode != null)
          'defect_reason_code': outcome.defectReasonCode,
        if (outcome.defectReason != null) 'defect_reason': outcome.defectReason,
      }),
    );
  }

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
    // Queued and durable now. Attempt to sync, then reload and report the ACTUAL
    // resulting state — never a fabricated success.
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

  Future<String?> _promptReason({
    required String title,
    required String hint,
  }) async {
    final controller = TextEditingController();
    final value = await showDialog<String>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(title),
        content: TextField(
          controller: controller,
          autofocus: true,
          decoration: InputDecoration(labelText: hint, hintText: 'Wajib diisi'),
        ),
        actions: <Widget>[
          TextButton(
            onPressed: () => Navigator.of(dialogContext).pop(),
            child: const Text('Tutup'),
          ),
          FilledButton(
            onPressed: () =>
                Navigator.of(dialogContext).pop(controller.text.trim()),
            child: const Text('Lanjutkan'),
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

/// The honest pending/conflict/failed card for a command on this job. It never
/// shows success styling for anything but a synced command (Rule 29).
class _PendingCommandCard extends StatelessWidget {
  const _PendingCommandCard({
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
      'Data pekerjaan ini sudah berubah sejak Anda membukanya. Perubahan Anda '
          'BELUM diterapkan. Muat ulang untuk melihat keadaan terbaru, lalu '
          'terapkan kembali bila masih diperlukan.',
    ProductionCommandStatus.failedPermanent =>
      'Server menolak perintah ini dan tidak akan mencobanya lagi. Buka pusat '
          'sinkronisasi untuk menyelesaikannya.',
  };
}

class _StagePicker extends StatelessWidget {
  const _StagePicker({this.current});
  final String? current;

  @override
  Widget build(BuildContext context) {
    return SafeArea(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: <Widget>[
          Padding(
            padding: EdgeInsets.all(AishSpacing.space4),
            child: Text(
              'Pilih tahap berikutnya',
              style: Theme.of(context).textTheme.titleMedium,
            ),
          ),
          for (final stage in _stages)
            ListTile(
              title: Text(stage),
              trailing: stage == current ? const Icon(Icons.check) : null,
              onTap: () => Navigator.of(context).pop(stage),
            ),
        ],
      ),
    );
  }
}

class _QcOutcome {
  const _QcOutcome({
    required this.verdict,
    this.defectReasonCode,
    this.defectReason,
  });
  final QualityControlVerdict verdict;
  final String? defectReasonCode;
  final String? defectReason;
}

/// The QC form. A FAILED verdict REQUIRES a defect reason code — collected here
/// before sending, so the operator learns the requirement at the form rather
/// than from a 422 (the server enforces it regardless).
class _QcSheet extends StatefulWidget {
  const _QcSheet();
  @override
  State<_QcSheet> createState() => _QcSheetState();
}

class _QcSheetState extends State<_QcSheet> {
  QualityControlVerdict _verdict = QualityControlVerdict.passed;
  final TextEditingController _reasonCode = TextEditingController();
  final TextEditingController _reason = TextEditingController();

  @override
  void dispose() {
    _reasonCode.dispose();
    _reason.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final requiresReason = _verdict.requiresDefectReason;
    return Padding(
      padding: EdgeInsets.only(
        left: AishSpacing.space4,
        right: AishSpacing.space4,
        top: AishSpacing.space4,
        bottom: MediaQuery.of(context).viewInsets.bottom + AishSpacing.space4,
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          Text(
            'Catat kendali mutu',
            style: Theme.of(context).textTheme.titleMedium,
          ),
          SizedBox(height: AishSpacing.space3),
          for (final verdict in QualityControlVerdict.values)
            ListTile(
              leading: Icon(
                verdict == _verdict
                    ? Icons.radio_button_checked
                    : Icons.radio_button_unchecked,
              ),
              title: Text(verdict.label),
              onTap: () => setState(() => _verdict = verdict),
            ),
          if (requiresReason) ...<Widget>[
            SizedBox(height: AishSpacing.space2),
            TextField(
              controller: _reasonCode,
              decoration: const InputDecoration(
                labelText: 'Kode alasan cacat',
                hintText: 'Wajib untuk verdict gagal',
              ),
            ),
            SizedBox(height: AishSpacing.space2),
            TextField(
              controller: _reason,
              decoration: const InputDecoration(
                labelText: 'Catatan (opsional)',
              ),
            ),
          ],
          SizedBox(height: AishSpacing.space4),
          PrimaryAction(
            label: 'Simpan verdict',
            icon: Icons.check,
            onPressed: _submit,
          ),
        ],
      ),
    );
  }

  void _submit() {
    if (_verdict.requiresDefectReason && _reasonCode.text.trim().isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Kode alasan cacat wajib diisi untuk verdict gagal.'),
        ),
      );
      return;
    }
    Navigator.of(context).pop(
      _QcOutcome(
        verdict: _verdict,
        defectReasonCode: _verdict.requiresDefectReason
            ? _reasonCode.text.trim()
            : null,
        defectReason: _reason.text.trim().isEmpty ? null : _reason.text.trim(),
      ),
    );
  }
}
