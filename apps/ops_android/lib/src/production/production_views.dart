import 'package:aish_design_system/aish_design_system.dart';
import 'package:aish_domain/aish_domain.dart';
import 'package:aish_offline_sync/aish_offline_sync.dart';
import 'package:flutter/material.dart';

/// The status chip for a production job STATE (server-authoritative).
StatusChip productionStateChip(ProductionState state) => StatusChip(
  label: state.label,
  icon: _stateIcon(state),
  tone: _stateTone(state),
);

IconData _stateIcon(ProductionState state) => switch (state) {
  ProductionState.created => Icons.playlist_add_check_outlined,
  ProductionState.inProgress => Icons.local_laundry_service_outlined,
  ProductionState.blocked => Icons.pause_circle_outline,
  ProductionState.awaitingQc => Icons.fact_check_outlined,
  ProductionState.reworkInProgress => Icons.restart_alt_outlined,
  ProductionState.closed => Icons.inventory_2_outlined,
  ProductionState.abandoned => Icons.cancel_outlined,
};

// Tone reinforces the text and icon; it is never the sole carrier of meaning
// (Rule 27 hard rule 3). Adjacent workflow states differ in SHAPE (icon), not
// only hue.
StatusTone _stateTone(ProductionState state) => switch (state) {
  ProductionState.blocked => StatusTone.warning,
  ProductionState.awaitingQc => StatusTone.warning,
  ProductionState.reworkInProgress => StatusTone.warning,
  ProductionState.closed => StatusTone.success,
  ProductionState.abandoned => StatusTone.neutral,
  ProductionState.created => StatusTone.neutral,
  ProductionState.inProgress => StatusTone.neutral,
};

/// The HONEST sync-state chip for a queued command. Only a SYNCED command may
/// render as success — a queued or in-flight command is never shown as
/// committed (Rule 29 hard rule 1). The label comes from the canonical
/// [SyncState], so two screens cannot describe the same state differently.
StatusChip commandStatusChip(ProductionCommandStatus status) {
  final sync = status.syncState;
  return StatusChip(
    label: sync.label,
    icon: _commandIcon(status),
    tone: _commandTone(status),
  );
}

IconData _commandIcon(ProductionCommandStatus status) => switch (status) {
  ProductionCommandStatus.pending => Icons.save_outlined,
  ProductionCommandStatus.syncing => Icons.sync_outlined,
  ProductionCommandStatus.retryWait => Icons.schedule_outlined,
  ProductionCommandStatus.synced => Icons.cloud_done_outlined,
  ProductionCommandStatus.conflict => Icons.sync_problem_outlined,
  ProductionCommandStatus.failedPermanent => Icons.error_outline,
};

StatusTone _commandTone(ProductionCommandStatus status) => switch (status) {
  ProductionCommandStatus.pending => StatusTone.neutral,
  ProductionCommandStatus.syncing => StatusTone.syncing,
  ProductionCommandStatus.retryWait => StatusTone.syncing,
  // The ONLY success tone. Everything else is deliberately not-success.
  ProductionCommandStatus.synced => StatusTone.success,
  ProductionCommandStatus.conflict => StatusTone.warning,
  ProductionCommandStatus.failedPermanent => StatusTone.danger,
};

/// A short Bahasa Indonesia label for a command type, for the sync centre.
String productionCommandLabel(ProductionCommandType type) => switch (type) {
  ProductionCommandType.advance => 'Lanjutkan tahap',
  ProductionCommandType.block => 'Tahan pekerjaan',
  ProductionCommandType.resume => 'Lanjutkan kembali',
  ProductionCommandType.sendToQc => 'Kirim ke kendali mutu',
  ProductionCommandType.recordQc => 'Catat kendali mutu',
  ProductionCommandType.completeRework => 'Selesaikan pengerjaan ulang',
  ProductionCommandType.markReady => 'Tandai siap diambil',
};
