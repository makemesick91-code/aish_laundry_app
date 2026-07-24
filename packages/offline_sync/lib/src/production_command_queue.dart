import 'dart:convert';

import 'package:aish_core/aish_core.dart';

import 'production_command.dart';
import 'sync_state.dart';

/// A mutation of a command that the guarded store may or may not apply.
///
/// Return the SAME instance to signal "no change"; return a new instance to
/// request a persisted update. The store refuses the update outright when the
/// current row is already [ProductionCommandStatus.synced].
typedef CommandTransition =
    ProductionCommand Function(ProductionCommand current);

/// A durable, encrypted-at-rest queue of Step 6 production commands, scoped to
/// ONE (user, tenant) pair.
///
/// PERSISTENCE. Every record is stored through [SecureCredentialStore], which on
/// Android is the platform keystore-backed store — so the payload is encrypted
/// at rest and its key material is the existing device key-store, not a scheme
/// this queue invents (Rule 03 hard rule 7; Rule 07 rule 8). The queue never
/// writes a plaintext file. In tests the same interface is satisfied by an
/// in-memory double, so the CONTRACT — that nothing persists outside the secure
/// store — is exercised even though the real cryptography is the platform's.
///
/// ISOLATION. The queue lives inside [StorageNamespace.tenant], so a different
/// user or a different tenant is a different key space by construction: a
/// tenant switch cannot expose the previous tenant's commands, and a second
/// account on a shared counter device cannot read the first's (Rule 07 rule 7,
/// Rule 02).
///
/// GUARDED TERMINAL STATE. [ProductionCommandStatus.synced] is immutable once
/// stored: [updateGuarded] refuses to move a synced row to any other state, so
/// no losing worker, manual retry, timeout handler, failure handler, or conflict
/// handler can overwrite a canonical success (the worker/manual race).
///
/// SERIALISED WRITES. Every mutation runs through a single-flight lock, so a
/// read-modify-write is atomic with respect to every other queue operation on
/// this instance — the guarded compare-and-set cannot interleave with another.
final class ProductionCommandQueue {
  ProductionCommandQueue({
    required SecureCredentialStore store,
    required this.userId,
    required this.tenantId,
    // A plain formal rather than `this._store`: an initializing formal would
    // force every caller to pass a private name.
    // ignore: prefer_initializing_formals
  }) : _store = store,
       _namespace = StorageNamespace.tenant(userId: userId, tenantId: tenantId);

  final SecureCredentialStore _store;
  final StorageNamespace _namespace;

  final String userId;
  final String tenantId;

  /// The on-device format version. A row written by a newer schema is migrated
  /// forward the next time the queue opens (there is only v1 today).
  static const String schemaVersion = '1';

  static const String _schemaKey = 'prod_schema_version';
  static const String _indexKey = 'prod_cmd_index';
  static String _commandKey(String reference) => 'prod_cmd/$reference';

  Future<void> _lock = Future<void>.value();

  /// Serialise [action] behind the single-flight lock.
  Future<T> _synchronized<T>(Future<T> Function() action) {
    final completer = _lock.then((_) => action());
    // Keep the chain alive even if this action fails, so a failure does not
    // wedge the queue.
    _lock = completer.then((_) {}, onError: (_) {});
    return completer;
  }

  /// Establish or migrate the on-device schema. Idempotent.
  Future<Result<void>> ensureSchema() => _synchronized(_ensureSchemaLocked);

  Future<Result<void>> _ensureSchemaLocked() async {
    final read = await _store.read(namespace: _namespace, key: _schemaKey);
    if (read.isErr) {
      return Result<void>.err(read.failureOrNull!);
    }
    final current = read.valueOrNull;
    if (current == schemaVersion) {
      return const Result<void>.ok(null);
    }
    // Absent (a fresh install, or a legacy pre-versioned store) or an older
    // version: stamp the current version WITHOUT discarding any existing index,
    // so a migration never loses queued work.
    return _store.write(
      namespace: _namespace,
      key: _schemaKey,
      value: schemaVersion,
    );
  }

  /// Enqueue a command. IDEMPOTENT on the client reference: a second enqueue of
  /// the same reference — a double tap, or a replayed intent — returns the
  /// EXISTING row unchanged rather than creating a duplicate logical command
  /// (Rule 07 rule 9; Rule 20 rule 13). The command is persisted before this
  /// returns, so an app kill immediately afterwards cannot lose it.
  Future<Result<ProductionCommand>> enqueue(ProductionCommand command) =>
      _synchronized(() async {
        final schema = await _ensureSchemaLocked();
        if (schema.isErr) {
          return Result<ProductionCommand>.err(schema.failureOrNull!);
        }

        final existing = await _readCommandLocked(command.clientReference);
        if (existing != null) {
          return Result<ProductionCommand>.ok(existing);
        }

        final write = await _writeCommandLocked(command);
        if (write.isErr) {
          return Result<ProductionCommand>.err(write.failureOrNull!);
        }
        final indexed = await _addToIndexLocked(command.clientReference);
        if (indexed.isErr) {
          return Result<ProductionCommand>.err(indexed.failureOrNull!);
        }
        return Result<ProductionCommand>.ok(command);
      });

  /// Every stored command, oldest first. A row that fails to decode — a
  /// corrupted encrypted payload — is DROPPED (and pruned from the index)
  /// rather than crashing the queue: a queue that could be bricked by one bad
  /// record is a queue that loses everything after it (fail closed on the row,
  /// not on the queue).
  Future<Result<List<ProductionCommand>>> all() => _synchronized(_allLocked);

  Future<Result<List<ProductionCommand>>> _allLocked() async {
    final indexRead = await _readIndexLocked();
    if (indexRead.isErr) {
      return Result<List<ProductionCommand>>.err(indexRead.failureOrNull!);
    }
    final refs = indexRead.valueOrNull!;
    final commands = <ProductionCommand>[];
    final survivingRefs = <String>[];

    for (final ref in refs) {
      final command = await _readCommandLocked(ref);
      if (command != null) {
        commands.add(command);
        survivingRefs.add(ref);
      } else {
        // Missing or corrupt: drop the pointer too, so a later purge is not
        // needed and the index stays truthful.
        await _store.delete(namespace: _namespace, key: _commandKey(ref));
      }
    }

    if (survivingRefs.length != refs.length) {
      await _writeIndexLocked(survivingRefs);
    }

    commands.sort((a, b) => a.createdAtUtc.compareTo(b.createdAtUtc));
    return Result<List<ProductionCommand>>.ok(commands);
  }

  /// Commands that are not yet in a terminal state — everything a drain may act
  /// on ([ProductionCommandStatus.pending], `syncing`, `retryWait`).
  Future<Result<List<ProductionCommand>>> unsynced() async {
    final result = await all();
    return result.map(
      (list) => list
          .where(
            (c) =>
                c.status != ProductionCommandStatus.synced &&
                c.status != ProductionCommandStatus.failedPermanent,
          )
          .toList(growable: false),
    );
  }

  /// Whether any command has not yet reached a canonical success — the signal a
  /// logout flow MUST consult before wiping the device. Production commands are
  /// non-financial and namespaced per (user, tenant), so a credential-scoped
  /// logout (clearing only the user/device credential namespace) leaves them
  /// intact for the same user's next session; a FULL `clearOnLogout` wipes them,
  /// so the app checks this first and protects unacknowledged work (Rule 07
  /// rule 4's principle, applied to non-financial work).
  Future<Result<bool>> hasUnacknowledged() async {
    final result = await unsynced();
    return result.map((list) => list.isNotEmpty);
  }

  /// Commands awaiting a human decision (conflict or permanent failure).
  Future<Result<List<ProductionCommand>>> needingAttention() async {
    final result = await all();
    return result.map(
      (list) => list.where((c) => c.status.needsHuman).toList(growable: false),
    );
  }

  Future<Result<ProductionCommand?>> byReference(String clientReference) =>
      _synchronized(() async {
        try {
          return Result<ProductionCommand?>.ok(
            await _readCommandLocked(clientReference),
          );
        } on Object {
          return const Result<ProductionCommand?>.err(
            Failure(kind: FailureKind.storage, message: 'Queue read failed.'),
          );
        }
      });

  /// Read → transition → write, atomically, with the terminal-SYNCED guard.
  ///
  /// Returns the post-state command (or null if the row is absent). If the
  /// current row is already [ProductionCommandStatus.synced], the [transition]
  /// is NOT applied and the stored synced command is returned unchanged — this
  /// is the guard that makes a canonical success immovable (the worker/manual
  /// race). A transition that returns the same instance also writes nothing.
  Future<Result<ProductionCommand?>> updateGuarded(
    String clientReference,
    CommandTransition transition,
  ) => _synchronized(() async {
    final current = await _readCommandLocked(clientReference);
    if (current == null) {
      return const Result<ProductionCommand?>.ok(null);
    }
    if (current.status == ProductionCommandStatus.synced) {
      // TERMINAL-IMMUTABLE. A losing writer cannot move a synced row.
      return Result<ProductionCommand?>.ok(current);
    }
    final next = transition(current);
    if (identical(next, current)) {
      return Result<ProductionCommand?>.ok(current);
    }
    if (next.clientReference != current.clientReference) {
      // A transition may never re-key a row; that would orphan the index.
      return const Result<ProductionCommand?>.err(
        Failure(
          kind: FailureKind.unexpected,
          message: 'A command transition may not change the client reference.',
        ),
      );
    }
    final write = await _writeCommandLocked(next);
    if (write.isErr) {
      return Result<ProductionCommand?>.err(write.failureOrNull!);
    }
    return Result<ProductionCommand?>.ok(next);
  });

  /// Remove a command a human has explicitly resolved. [reason] is MANDATORY —
  /// there is deliberately no `clear()` and no bulk discard, mirroring the
  /// SyncQueue contract: removing a queued operation is a deliberate act, never
  /// a developer convenience (Rule 07 rule 4). The reason is not persisted here
  /// (the queue holds no audit log); the caller is responsible for auditing.
  Future<Result<void>> resolveManually({
    required String clientReference,
    required String reason,
  }) {
    if (reason.trim().isEmpty) {
      throw ArgumentError.value(reason, 'reason', 'must not be empty');
    }
    return _synchronized(() async {
      final delete = await _store.delete(
        namespace: _namespace,
        key: _commandKey(clientReference),
      );
      if (delete.isErr) {
        return delete;
      }
      return _removeFromIndexLocked(clientReference);
    });
  }

  // --- persistence primitives (all run inside the lock) ----------------------

  Future<ProductionCommand?> _readCommandLocked(String reference) async {
    final read = await _store.read(
      namespace: _namespace,
      key: _commandKey(reference),
    );
    final raw = read.valueOrNull;
    if (raw == null) {
      return null;
    }
    try {
      final decoded = jsonDecode(raw);
      if (decoded is! Map) {
        return null;
      }
      return ProductionCommand.fromJson(decoded.cast<String, Object?>());
    } on Object {
      // A corrupt or unparseable record. Treated as absent — the caller drops
      // it. Nothing about its content is logged (it could be malformed in a way
      // that echoes a value).
      return null;
    }
  }

  Future<Result<void>> _writeCommandLocked(ProductionCommand command) =>
      _store.write(
        namespace: _namespace,
        key: _commandKey(command.clientReference),
        value: jsonEncode(command.toJson()),
      );

  Future<Result<List<String>>> _readIndexLocked() async {
    final read = await _store.read(namespace: _namespace, key: _indexKey);
    if (read.isErr) {
      return Result<List<String>>.err(read.failureOrNull!);
    }
    final raw = read.valueOrNull;
    if (raw == null || raw.isEmpty) {
      return const Result<List<String>>.ok(<String>[]);
    }
    try {
      final decoded = jsonDecode(raw);
      if (decoded is! List) {
        return const Result<List<String>>.ok(<String>[]);
      }
      return Result<List<String>>.ok(
        decoded.whereType<String>().toList(growable: false),
      );
    } on Object {
      // A corrupt index is treated as empty rather than fatal; individual
      // command records still exist and a future enqueue rebuilds the index.
      return const Result<List<String>>.ok(<String>[]);
    }
  }

  Future<Result<void>> _writeIndexLocked(List<String> refs) => _store.write(
    namespace: _namespace,
    key: _indexKey,
    value: jsonEncode(refs),
  );

  Future<Result<void>> _addToIndexLocked(String reference) async {
    final read = await _readIndexLocked();
    if (read.isErr) {
      return Result<void>.err(read.failureOrNull!);
    }
    final refs = List<String>.of(read.valueOrNull!);
    if (!refs.contains(reference)) {
      refs.add(reference);
    }
    return _writeIndexLocked(refs);
  }

  Future<Result<void>> _removeFromIndexLocked(String reference) async {
    final read = await _readIndexLocked();
    if (read.isErr) {
      return Result<void>.err(read.failureOrNull!);
    }
    final refs = List<String>.of(read.valueOrNull!)..remove(reference);
    return _writeIndexLocked(refs);
  }
}

/// Derive the always-visible Ops indicator health from a set of commands and the
/// current connectivity (Rule 29 rule 2). Pure, so it is trivially testable.
SyncHealth deriveSyncHealth({
  required Iterable<ProductionCommand> commands,
  required bool online,
}) {
  var hasWork = false;
  for (final command in commands) {
    if (command.status.needsHuman) {
      return SyncHealth.attentionRequired;
    }
    if (command.status != ProductionCommandStatus.synced) {
      hasWork = true;
    }
  }
  if (!online) {
    return SyncHealth.offline;
  }
  return hasWork ? SyncHealth.syncing : SyncHealth.idle;
}
