/// Offline synchronization interfaces, state taxonomy, AND the Step 6 production
/// implementation.
///
/// Step 3 defined the shape (`SyncOperation`, `SyncQueue`, `SyncState`) so that
/// each surface could not invent its own. Step 6 (DEC-0037) fills it for the Ops
/// production surface: a durable, encrypted-at-rest [ProductionCommandQueue] and
/// the connectivity-aware [ProductionSyncWorker] that drains it against the
/// server-authoritative production API, reusing each command's original
/// `client_reference` on every retry.
///
/// The order and payment operations remain unbuilt here: Step 5's POS surface is
/// online-only, and a queued financial operation is Step 7+ scope.
library;

export 'src/production_command.dart';
export 'src/production_command_queue.dart';
export 'src/sync_operation.dart';
export 'src/sync_queue.dart';
export 'src/sync_state.dart';
