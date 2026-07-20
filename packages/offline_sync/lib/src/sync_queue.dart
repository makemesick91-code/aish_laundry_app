import 'package:aish_core/aish_core.dart';

import 'sync_operation.dart';
import 'sync_state.dart';

/// The contract a persistent operation queue must satisfy.
///
/// **NOT IMPLEMENTED in Step 3.** No class in this repository implements this
/// interface. It is recorded now so that the surfaces built in Step 5 and later
/// cannot each invent a weaker guarantee, and so that the guarantees are written
/// down before there is schedule pressure to soften them.
abstract interface class SyncQueue {
  /// Everything not yet server-confirmed.
  Future<Result<List<SyncOperation>>> pending();

  /// Overall health, for the always-visible indicator (Rule 29 rule 2).
  Stream<SyncHealth> get health;

  /// Enqueue an operation. The implementation MUST persist it before returning,
  /// so that an app kill immediately afterwards cannot lose it.
  Future<Result<void>> enqueue(SyncOperation operation);

  /// Attempt to drain the queue with bounded exponential backoff.
  ///
  /// The implementation MUST reuse each operation's original
  /// [ClientReference], MUST preserve ordering between dependent operations,
  /// and MUST NOT let an operation whose predecessor failed jump ahead.
  Future<Result<void>> drain();

  /// Remove an operation that a human has explicitly resolved.
  ///
  /// [reason] is mandatory and is recorded. There is deliberately no
  /// `clear()` and no bulk discard: removing a queued FINANCIAL operation is a
  /// permissioned, audited act, not a developer convenience (Rule 07 rule 4).
  Future<Result<void>> resolveManually({
    required ClientReference reference,
    required String reason,
  });
}
