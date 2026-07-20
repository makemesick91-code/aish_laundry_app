import 'package:meta/meta.dart';

import 'sync_state.dart';

/// A stable, client-generated reference that makes an operation idempotent.
///
/// THE DEFINING RULE OF THE OFFLINE DESIGN: a [ClientReference] is generated
/// ONCE, persisted alongside the operation, and reused on EVERY retry. Producing
/// a fresh one on retry defeats the entire mechanism and is how a duplicate
/// payment reaches production (Rule 07 rule 1, Rule 20 rule 13).
///
/// This type has no `regenerate` method and no mutable field. That is the point:
/// the API offers no way to do the wrong thing.
@immutable
final class ClientReference {
  const ClientReference(this.value);

  final String value;

  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is ClientReference && other.value == value);

  @override
  int get hashCode => value.hashCode;

  @override
  String toString() => value;
}

/// One queued operation, described abstractly.
///
/// Step 3 defines NO concrete operation. There is no create-order operation and
/// no record-payment operation here, because neither feature exists. Step 5 and
/// later supply the implementations.
@immutable
abstract class SyncOperation {
  const SyncOperation({
    required this.clientReference,
    required this.tenantId,
    required this.createdAtUtc,
    required this.state,
  });

  /// Reused on every retry. Never regenerated.
  final ClientReference clientReference;

  /// The tenant this operation belongs to. Carried EXPLICITLY rather than
  /// inferred from "the current context": a queue replayed after a tenant
  /// switch must not be attributed to whichever tenant happens to be active
  /// (Rule 20 rule 6).
  final String tenantId;

  /// Client-side creation instant. Ordering is decided by the SERVER, whose
  /// clock is authoritative; this value is untrusted metadata.
  final DateTime createdAtUtc;

  final SyncState state;

  /// Whether this operation affects money.
  ///
  /// A financial operation is never cleared by a cache flush, a logout, or a
  /// version upgrade, and a conflict on one is escalated to a human rather than
  /// resolved automatically (Rule 07 rules 4 and 5).
  bool get isFinancial;
}
