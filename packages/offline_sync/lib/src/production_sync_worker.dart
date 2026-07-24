// The worker's collaborators are injected through PUBLIC named parameters
// mapped to PRIVATE fields. An initializing formal would name the parameter
// `_repo`, which an external caller cannot pass — so plain formals plus an
// initializer list are the correct shape here, exactly as `ApiClient` does it.
// ignore_for_file: prefer_initializing_formals
import 'dart:async';

import 'package:aish_core/aish_core.dart';
import 'package:aish_domain/aish_domain.dart';
import 'package:aish_networking/aish_networking.dart';

import 'production_command.dart';
import 'production_command_queue.dart';

/// Whether the device currently has a usable network path.
///
/// An ABSTRACTION on purpose: this package adds no connectivity plugin (that is
/// an owner-approved dependency decision, Rule 37). The app injects a real
/// monitor; tests inject a fake. The default [OptimisticConnectivityMonitor]
/// makes NO false claim of detection — it reports "online" and lets a send
/// attempt discover the truth, backing off on a transport failure.
abstract interface class ConnectivityMonitor {
  Future<bool> isOnline();

  /// Emits whenever connectivity flips. A worker may listen to drain on
  /// reconnect. An implementation that cannot detect changes emits nothing.
  Stream<bool> get onlineChanges;
}

/// The honest default: assume online, discover offline from a failed send.
final class OptimisticConnectivityMonitor implements ConnectivityMonitor {
  const OptimisticConnectivityMonitor();

  @override
  Future<bool> isOnline() async => true;

  @override
  Stream<bool> get onlineChanges => const Stream<bool>.empty();
}

/// The summary of one drain pass — what the always-visible indicator and the
/// conflict centre render (Rule 29).
final class SyncPass {
  const SyncPass({
    this.attempted = 0,
    this.synced = 0,
    this.conflicts = 0,
    this.permanentFailures = 0,
    this.deferred = 0,
    this.authenticationRequired = false,
    this.offline = false,
    this.storageError = false,
  });

  const SyncPass.offline() : this(offline: true);
  const SyncPass.storageFailure() : this(storageError: true);

  /// Commands a send was attempted for this pass.
  final int attempted;

  /// Commands that reached a canonical SYNCED this pass.
  final int synced;

  /// Commands that hit a CONFLICT needing a human (reload and reconcile).
  final int conflicts;

  /// Commands that reached a permanent, non-retryable failure this pass.
  final int permanentFailures;

  /// Commands left in retryWait (transient failure) or held behind an
  /// earlier same-job failure to preserve order.
  final int deferred;

  /// The session/credential was rejected: the app must re-authenticate. The
  /// affected command was returned to `pending`, never discarded (Rule 29).
  final bool authenticationRequired;

  /// No network path; nothing was attempted.
  final bool offline;

  /// Local storage could not be read; the pass could not run.
  final bool storageError;

  bool get madeProgress => synced > 0;
}

/// Drains the [ProductionCommandQueue] against the server-authoritative
/// production API, reusing each command's original `client_reference` on every
/// retry (Rule 07 rule 1) and NEVER overwriting server state on a conflict.
///
/// The reconciliation contract:
///
///   * SUCCESS → the command is stored SYNCED with the server's canonical
///     version. SYNCED is terminal-immutable (the queue's guard).
///   * TIMEOUT AFTER A COMMIT → resolved for free by the idempotent replay: the
///     next pass re-sends the SAME `client_reference`, the server returns the
///     ORIGINAL result, and the command reconciles to SYNCED. No local guess is
///     made about whether the first attempt committed.
///   * TRANSIENT (network/timeout/5xx/rate-limit) → retryWait with bounded
///     exponential backoff; after [maxAttempts] it becomes a permanent failure
///     needing a human.
///   * CONFLICT/version or /state → CONFLICT status: surfaced to a human,
///     server state is NOT overwritten and the local intent is NOT discarded
///     (Rule 07 rule 5's principle). Recovery is reload-and-reconcile in the UI,
///     which issues a FRESH command — the worker never auto-bumps the version.
///   * CONFLICT/reused-reference-different-payload, VALIDATION, and
///     AUTHORIZATION/NOT_FOUND → permanent failure: the same request will be
///     refused identically forever, so it is never retried.
///   * AUTHENTICATION (expired/revoked session or device) → the drain STOPS, the
///     command is returned to `pending` (work is protected), and the pass
///     reports `authenticationRequired` so the app can re-authenticate.
///
/// PER-JOB ORDERING. Commands are processed oldest-first. If a command for a job
/// does not reach SYNCED, later commands for that SAME job are held this pass —
/// a resume must not be sent before the block it depends on succeeds. Commands
/// for OTHER jobs are independent and continue.
final class ProductionSyncWorker {
  ProductionSyncWorker({
    required ProductionRepository repository,
    required ProductionCommandQueue queue,
    required Clock clock,
    ConnectivityMonitor connectivity = const OptimisticConnectivityMonitor(),
    Duration baseBackoff = const Duration(seconds: 2),
    Duration maxBackoff = const Duration(minutes: 5),
    int maxAttempts = 8,
  }) : _repo = repository,
       _queue = queue,
       _clock = clock,
       _connectivity = connectivity,
       _baseBackoff = baseBackoff,
       _maxBackoff = maxBackoff,
       _maxAttempts = maxAttempts;

  final ProductionRepository _repo;
  final ProductionCommandQueue _queue;
  final Clock _clock;
  final ConnectivityMonitor _connectivity;
  final Duration _baseBackoff;
  final Duration _maxBackoff;
  final int _maxAttempts;

  StreamSubscription<bool>? _reconnectSub;
  bool _draining = false;

  /// Begin auto-draining on reconnect. Idempotent. The app also calls [drain]
  /// on a periodic timer and on user "sync now"; this only adds reconnect.
  void start() {
    _reconnectSub ??= _connectivity.onlineChanges.listen((online) {
      if (online) {
        unawaited(drain());
      }
    });
  }

  Future<void> stop() async {
    await _reconnectSub?.cancel();
    _reconnectSub = null;
  }

  /// One drain pass. Safe to call concurrently with itself and with a manual
  /// retry: the queue serialises every write and SYNCED is immovable, so a race
  /// produces at most one canonical server effect (the server dedupes on
  /// `client_reference`) and the loser can never clobber the winner.
  Future<SyncPass> drain() async {
    if (_draining) {
      // A pass is already running on this worker; let it do the work rather
      // than interleave two loops over the same queue.
      return const SyncPass();
    }
    _draining = true;
    try {
      return await _drainOnce();
    } finally {
      _draining = false;
    }
  }

  Future<SyncPass> _drainOnce() async {
    if (!await _connectivity.isOnline()) {
      return const SyncPass.offline();
    }

    final listResult = await _queue.all();
    if (listResult.isErr) {
      return const SyncPass.storageFailure();
    }
    final commands = listResult.valueOrNull!;
    final now = _clock.nowUtc();

    final blockedJobs = <String>{};
    var attempted = 0;
    var synced = 0;
    var conflicts = 0;
    var permanent = 0;
    var deferred = 0;
    var authRequired = false;

    for (final command in commands) {
      if (!_isSyncable(command, now)) {
        continue;
      }
      if (blockedJobs.contains(command.jobId)) {
        // A predecessor for this job did not succeed; hold this one to preserve
        // order (no silent reordering).
        deferred++;
        continue;
      }

      attempted++;
      await _queue.updateGuarded(
        command.clientReference,
        (c) => c.copyWith(status: ProductionCommandStatus.syncing),
      );

      final Result<_Ack> result = await _dispatch(command);

      if (result.isOk) {
        final ack = result.valueOrNull!;
        await _queue.updateGuarded(
          command.clientReference,
          (c) => c.copyWith(
            status: ProductionCommandStatus.synced,
            acknowledgement: ack.data,
            serverVersion: ack.serverVersion,
            nextRetryAtUtc: null,
          ),
        );
        synced++;
        continue;
      }

      final resolution = _classify(result.failureOrNull!);
      switch (resolution.kind) {
        case _ResolutionKind.transient:
          final nextAttempt = command.attemptCount + 1;
          if (nextAttempt >= _maxAttempts) {
            await _queue.updateGuarded(
              command.clientReference,
              (c) => c.copyWith(
                status: ProductionCommandStatus.failedPermanent,
                attemptCount: nextAttempt,
                permanentFailureDetail: 'retries_exhausted',
              ),
            );
            permanent++;
          } else {
            final delay = _backoff(nextAttempt);
            await _queue.updateGuarded(
              command.clientReference,
              (c) => c.copyWith(
                status: ProductionCommandStatus.retryWait,
                attemptCount: nextAttempt,
                nextRetryAtUtc: now.add(delay),
              ),
            );
            deferred++;
          }
          blockedJobs.add(command.jobId);

        case _ResolutionKind.conflict:
          await _queue.updateGuarded(
            command.clientReference,
            (c) => c.copyWith(
              status: ProductionCommandStatus.conflict,
              conflictCode: resolution.detail,
              conflictDetails: resolution.details,
            ),
          );
          conflicts++;
          blockedJobs.add(command.jobId);

        case _ResolutionKind.permanent:
          await _queue.updateGuarded(
            command.clientReference,
            (c) => c.copyWith(
              status: ProductionCommandStatus.failedPermanent,
              permanentFailureDetail: resolution.detail,
            ),
          );
          permanent++;
          blockedJobs.add(command.jobId);

        case _ResolutionKind.reauthenticate:
          // The session is bad for EVERY command, not just this one. Return the
          // command to pending (never discard queued work — Rule 29), stop the
          // pass, and let the app re-authenticate.
          await _queue.updateGuarded(
            command.clientReference,
            (c) => c.copyWith(status: ProductionCommandStatus.pending),
          );
          authRequired = true;
      }

      if (authRequired) {
        break;
      }
    }

    return SyncPass(
      attempted: attempted,
      synced: synced,
      conflicts: conflicts,
      permanentFailures: permanent,
      deferred: deferred,
      authenticationRequired: authRequired,
    );
  }

  /// Force a retryWait command due now and drain. For a CONFLICT the UI must
  /// instead reload and issue a FRESH command — retrying the same payload with
  /// a stale version would only conflict again — so this refuses a conflicted
  /// command rather than silently re-sending it.
  Future<SyncPass> retryNow(String clientReference) async {
    final read = await _queue.byReference(clientReference);
    final command = read.valueOrNull;
    if (command == null) {
      return const SyncPass();
    }
    if (command.status == ProductionCommandStatus.conflict ||
        command.status == ProductionCommandStatus.failedPermanent) {
      // Not retry-safe here: a conflict is reloaded-and-reissued in the UI, and
      // a permanent failure is resolved manually. Do not silently re-send.
      return const SyncPass();
    }
    await _queue.updateGuarded(
      clientReference,
      (c) => c.copyWith(
        status: ProductionCommandStatus.pending,
        nextRetryAtUtc: null,
      ),
    );
    return drain();
  }

  bool _isSyncable(
    ProductionCommand command,
    DateTime now,
  ) => switch (command.status) {
    // `syncing` is included so a command left mid-flight by an app kill is
    // re-driven; the idempotent replay makes that safe.
    ProductionCommandStatus.pending || ProductionCommandStatus.syncing => true,
    ProductionCommandStatus.retryWait =>
      command.nextRetryAtUtc == null || !command.nextRetryAtUtc!.isAfter(now),
    ProductionCommandStatus.synced ||
    ProductionCommandStatus.conflict ||
    ProductionCommandStatus.failedPermanent => false,
  };

  Duration _backoff(int attempt) {
    // Pure exponential (base * 2^(attempt-1)), capped. Deterministic — the tests
    // pin the clock and assert the exact next-retry instant.
    var delayMs = _baseBackoff.inMilliseconds;
    for (var i = 1; i < attempt; i++) {
      delayMs *= 2;
      if (delayMs >= _maxBackoff.inMilliseconds) {
        return _maxBackoff;
      }
    }
    final capped = delayMs > _maxBackoff.inMilliseconds
        ? _maxBackoff.inMilliseconds
        : delayMs;
    return Duration(milliseconds: capped);
  }

  Future<Result<_Ack>> _dispatch(ProductionCommand command) async {
    try {
      switch (command.type) {
        case ProductionCommandType.advance:
          final r = await _repo.advance(
            command.jobId,
            stage: _required(command, 'stage'),
            clientReference: command.clientReference,
            expectedVersion: command.expectedVersion,
          );
          return r.map(_summaryToAck);

        case ProductionCommandType.block:
          final r = await _repo.block(
            command.jobId,
            reasonCode: _required(command, 'reason_code'),
            reason: command.payload['reason'] as String?,
            clientReference: command.clientReference,
            expectedVersion: command.expectedVersion,
          );
          return r.map(_summaryToAck);

        case ProductionCommandType.resume:
          final r = await _repo.resume(
            command.jobId,
            clientReference: command.clientReference,
            expectedVersion: command.expectedVersion,
          );
          return r.map(_summaryToAck);

        case ProductionCommandType.sendToQc:
          final r = await _repo.sendToQualityControl(
            command.jobId,
            clientReference: command.clientReference,
            expectedVersion: command.expectedVersion,
          );
          return r.map(_summaryToAck);

        case ProductionCommandType.recordQc:
          final r = await _repo.recordQualityControl(
            command.jobId,
            verdict: QualityControlVerdict.parse(_required(command, 'verdict')),
            defectReasonCode: command.payload['defect_reason_code'] as String?,
            defectReason: command.payload['defect_reason'] as String?,
            clientReference: command.clientReference,
            expectedVersion: command.expectedVersion,
          );
          return r.map(
            (v) => _Ack(
              serverVersion: v.job.version,
              data: <String, Object?>{
                'inspection_id': v.inspectionId,
                'verdict': v.verdict.wireValue,
                'state': v.job.state.wireValue,
                'version': v.job.version,
              },
            ),
          );

        case ProductionCommandType.completeRework:
          final r = await _repo.completeRework(
            command.jobId,
            reasonCode: _required(command, 'reason_code'),
            clientReference: command.clientReference,
            expectedVersion: command.expectedVersion,
          );
          return r.map(_summaryToAck);

        case ProductionCommandType.markReady:
          final r = await _repo.markReady(
            command.jobId,
            clientReference: command.clientReference,
          );
          return r.map(
            (v) => _Ack(
              serverVersion: null,
              data: <String, Object?>{'order_status': v.orderStatus.name},
            ),
          );
      }
    } on _MalformedCommand {
      // A command whose payload is missing a required field can never succeed;
      // classify it as a permanent validation failure, never a retry.
      return const Result<_Ack>.err(
        Failure(
          kind: FailureKind.validation,
          message: 'malformed_command',
          code: 'VALIDATION_FAILED',
        ),
      );
    }
  }

  String _required(ProductionCommand command, String key) {
    final value = command.payload[key];
    if (value is String && value.isNotEmpty) {
      return value;
    }
    throw const _MalformedCommand();
  }

  _Ack _summaryToAck(ProductionJobSummary s) => _Ack(
    serverVersion: s.version,
    data: <String, Object?>{'state': s.state.wireValue, 'version': s.version},
  );

  _Resolution _classify(Failure failure) {
    final conflict = ProductionConflict.fromFailure(failure);
    if (conflict != null) {
      if (conflict.isPermanent) {
        return const _Resolution(
          _ResolutionKind.permanent,
          'reused_reference_different_payload',
        );
      }
      return _Resolution(
        _ResolutionKind.conflict,
        conflict.name,
        details: failure.details,
      );
    }

    return switch (failure.kind) {
      FailureKind.authentication => const _Resolution(
        _ResolutionKind.reauthenticate,
        'authentication',
      ),
      // Permission revoked, or a foreign/absent job (NOT_FOUND maps to
      // authorization): the same request will be refused identically forever.
      FailureKind.authorization => _Resolution(
        _ResolutionKind.permanent,
        'authorization:${failure.code ?? 'unknown'}',
      ),
      FailureKind.validation => _Resolution(
        _ResolutionKind.permanent,
        'validation:${failure.code ?? 'unknown'}',
      ),
      FailureKind.network ||
      FailureKind.timeout ||
      FailureKind.rateLimited ||
      FailureKind.serviceUnavailable ||
      FailureKind.unexpected => const _Resolution(
        _ResolutionKind.transient,
        '',
      ),
      // A local storage/configuration failure is not the server's doing; retry.
      FailureKind.storage || FailureKind.configuration => const _Resolution(
        _ResolutionKind.transient,
        '',
      ),
    };
  }
}

/// The canonical acknowledgement extracted from a successful command.
final class _Ack {
  const _Ack({required this.serverVersion, required this.data});
  final int? serverVersion;
  final Map<String, Object?> data;
}

/// Thrown when a queued command is structurally unusable (missing payload).
final class _MalformedCommand implements Exception {
  const _MalformedCommand();
}

enum _ResolutionKind { transient, conflict, permanent, reauthenticate }

final class _Resolution {
  const _Resolution(this.kind, this.detail, {this.details});
  final _ResolutionKind kind;
  final String detail;
  final Map<String, Object?>? details;
}
