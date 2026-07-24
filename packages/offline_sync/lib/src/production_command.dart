import 'package:meta/meta.dart';

import 'sync_state.dart';

/// The seven production commands the Ops surface may queue (Step 6, DEC-0037).
///
/// Exactly one per WRITE endpoint the backend registers. There is deliberately
/// NO create-job and NO abandon command, because the backend exposes neither as
/// an operator HTTP action — a queue that could enqueue one would be a command
/// with nowhere to go (a dead action, Rule 01).
enum ProductionCommandType {
  advance('advance'),
  block('block'),
  resume('resume'),
  sendToQc('send_to_qc'),
  recordQc('record_qc'),
  completeRework('complete_rework'),
  markReady('mark_ready');

  const ProductionCommandType(this.wire);

  /// Stable persistence token. Parsing an unknown one throws rather than
  /// inventing a member, so a queue row written by a newer build is never
  /// silently mis-dispatched by an older one.
  final String wire;

  static ProductionCommandType parse(String value) => values.firstWhere(
    (t) => t.wire == value,
    orElse: () => throw ArgumentError.value(
      value,
      'type',
      'Jenis perintah produksi tidak dikenal',
    ),
  );
}

/// The lifecycle of ONE queued command, from enqueue to a terminal outcome.
///
/// This is the INTERNAL queue lifecycle (six states). It is distinct from, and
/// maps onto, the four-value [SyncState] the always-visible Ops indicator
/// renders — so the honest "never render a queued op as committed" rule (Rule 29
/// hard rule 1) holds by construction: only [synced] maps to a success state.
enum ProductionCommandStatus {
  /// Written to the device, not yet offered to the server.
  pending,

  /// Currently in flight to the server. NOT success.
  syncing,

  /// A transient failure occurred; waiting for the backoff window to elapse.
  retryWait,

  /// Server-confirmed. TERMINAL and IMMUTABLE — once here, no worker, retry,
  /// timeout, failure or conflict handler may move the row to any other state.
  synced,

  /// The server refused with a CONFLICT. Needs a human decision (reload and
  /// reconcile, or — where the backend contract says retry is safe — retry).
  /// Never auto-overwrites server state.
  conflict,

  /// A permanent, non-retryable failure (a validation refusal, or a reused
  /// client_reference with a different payload). Needs a human; never retried.
  failedPermanent;

  /// The honest UI state (Rule 29). Only [synced] may render as success.
  SyncState get syncState => switch (this) {
    ProductionCommandStatus.pending => SyncState.storedOnDevice,
    ProductionCommandStatus.syncing => SyncState.awaitingSync,
    ProductionCommandStatus.retryWait => SyncState.awaitingSync,
    ProductionCommandStatus.synced => SyncState.synced,
    ProductionCommandStatus.conflict => SyncState.failedNeedsAction,
    ProductionCommandStatus.failedPermanent => SyncState.failedNeedsAction,
  };

  /// [synced] is the only terminal-IMMUTABLE state: it is the stored canonical
  /// success and the queue's guarded write refuses to overwrite it.
  bool get isTerminalSuccess => this == ProductionCommandStatus.synced;

  /// Whether a human must act on this command before it can progress.
  bool get needsHuman =>
      this == ProductionCommandStatus.conflict ||
      this == ProductionCommandStatus.failedPermanent;
}

/// One durable, queued production command.
///
/// IDENTITY. [localId] IS [clientReference] by construction: one logical
/// command has exactly one stable identifier, generated ONCE and reused on every
/// retry (Rule 07 rule 1). There is deliberately no second identifier that could
/// drift from it, and no `regenerate` — producing a fresh reference on retry is
/// the defining failure of the offline design and this type offers no way to do
/// it.
///
/// TENANT/USER/OUTLET are carried EXPLICITLY, never inferred from "the current
/// context": a queue drained after a tenant switch must be attributed to the
/// tenant it was created in (Rule 20 rule 6, Rule 02).
@immutable
final class ProductionCommand {
  const ProductionCommand({
    required this.clientReference,
    required this.tenantId,
    required this.userId,
    required this.jobId,
    required this.type,
    required this.createdAtUtc,
    this.outletId,
    this.orderId,
    this.itemId,
    this.expectedVersion,
    this.payload = const <String, Object?>{},
    this.status = ProductionCommandStatus.pending,
    this.attemptCount = 0,
    this.nextRetryAtUtc,
    this.acknowledgement,
    this.serverVersion,
    this.conflictCode,
    this.conflictDetails,
    this.permanentFailureDetail,
  });

  /// The stable idempotency key, reused on every retry.
  final String clientReference;

  /// The storage identity. Equal to [clientReference] by construction.
  String get localId => clientReference;

  final String tenantId;
  final String userId;
  final String jobId;
  final ProductionCommandType type;
  final DateTime createdAtUtc;

  final String? outletId;
  final String? orderId;
  final String? itemId;

  /// The optimistic-concurrency token captured when the command was enqueued.
  final int? expectedVersion;

  /// The type-specific request body (stage, reason_code, verdict, …). The
  /// worker adds `client_reference` and `expected_version` around it; they are
  /// NOT duplicated here.
  final Map<String, Object?> payload;

  final ProductionCommandStatus status;
  final int attemptCount;
  final DateTime? nextRetryAtUtc;

  /// The canonical server `data` payload of a SUCCESSFUL apply. Non-null only
  /// when [status] is [ProductionCommandStatus.synced].
  final Map<String, Object?>? acknowledgement;

  /// The canonical server version after a successful apply, when the response
  /// carried a job summary.
  final int? serverVersion;

  /// The [ProductionConflict] name, when [status] is
  /// [ProductionCommandStatus.conflict].
  final String? conflictCode;

  /// The server's conflict `details`, preserved so the UI can explain what
  /// changed. NON-SENSITIVE (a production projection carries no PII, Rule 32).
  final Map<String, Object?>? conflictDetails;

  /// A short, non-sensitive description of a permanent failure.
  final String? permanentFailureDetail;

  /// Production commands never move money. A financial operation is never
  /// cleared by a cache flush or logout and its conflict escalates to a human;
  /// these do not, but they are still never silently dropped.
  bool get isFinancial => false;

  ProductionCommand copyWith({
    ProductionCommandStatus? status,
    int? attemptCount,
    Object? nextRetryAtUtc = _unset,
    Object? acknowledgement = _unset,
    Object? serverVersion = _unset,
    Object? conflictCode = _unset,
    Object? conflictDetails = _unset,
    Object? permanentFailureDetail = _unset,
  }) => ProductionCommand(
    clientReference: clientReference,
    tenantId: tenantId,
    userId: userId,
    jobId: jobId,
    type: type,
    createdAtUtc: createdAtUtc,
    outletId: outletId,
    orderId: orderId,
    itemId: itemId,
    expectedVersion: expectedVersion,
    payload: payload,
    status: status ?? this.status,
    attemptCount: attemptCount ?? this.attemptCount,
    nextRetryAtUtc: identical(nextRetryAtUtc, _unset)
        ? this.nextRetryAtUtc
        : nextRetryAtUtc as DateTime?,
    acknowledgement: identical(acknowledgement, _unset)
        ? this.acknowledgement
        : acknowledgement as Map<String, Object?>?,
    serverVersion: identical(serverVersion, _unset)
        ? this.serverVersion
        : serverVersion as int?,
    conflictCode: identical(conflictCode, _unset)
        ? this.conflictCode
        : conflictCode as String?,
    conflictDetails: identical(conflictDetails, _unset)
        ? this.conflictDetails
        : conflictDetails as Map<String, Object?>?,
    permanentFailureDetail: identical(permanentFailureDetail, _unset)
        ? this.permanentFailureDetail
        : permanentFailureDetail as String?,
  );

  Map<String, Object?> toJson() => <String, Object?>{
    'client_reference': clientReference,
    'tenant_id': tenantId,
    'user_id': userId,
    'job_id': jobId,
    'type': type.wire,
    'created_at': createdAtUtc.toUtc().toIso8601String(),
    'outlet_id': outletId,
    'order_id': orderId,
    'item_id': itemId,
    'expected_version': expectedVersion,
    'payload': payload,
    'status': status.name,
    'attempt_count': attemptCount,
    'next_retry_at': nextRetryAtUtc?.toUtc().toIso8601String(),
    'acknowledgement': acknowledgement,
    'server_version': serverVersion,
    'conflict_code': conflictCode,
    'conflict_details': conflictDetails,
    'permanent_failure_detail': permanentFailureDetail,
  };

  /// Rebuild from persisted JSON. Throws on a structurally invalid record so
  /// the store can drop a corrupt row rather than resurrect a half-command.
  factory ProductionCommand.fromJson(Map<String, Object?> json) =>
      ProductionCommand(
        clientReference: json['client_reference']! as String,
        tenantId: json['tenant_id']! as String,
        userId: json['user_id']! as String,
        jobId: json['job_id']! as String,
        type: ProductionCommandType.parse(json['type']! as String),
        createdAtUtc: DateTime.parse(json['created_at']! as String).toUtc(),
        outletId: json['outlet_id'] as String?,
        orderId: json['order_id'] as String?,
        itemId: json['item_id'] as String?,
        expectedVersion: (json['expected_version'] as num?)?.toInt(),
        payload: _map(json['payload']),
        status: ProductionCommandStatus.values.byName(json['status']! as String),
        attemptCount: (json['attempt_count'] as num?)?.toInt() ?? 0,
        nextRetryAtUtc: _dateOrNull(json['next_retry_at']),
        acknowledgement: _mapOrNull(json['acknowledgement']),
        serverVersion: (json['server_version'] as num?)?.toInt(),
        conflictCode: json['conflict_code'] as String?,
        conflictDetails: _mapOrNull(json['conflict_details']),
        permanentFailureDetail: json['permanent_failure_detail'] as String?,
      );

  static const Object _unset = Object();

  static Map<String, Object?> _map(Object? value) =>
      value is Map ? value.cast<String, Object?>() : const <String, Object?>{};

  static Map<String, Object?>? _mapOrNull(Object? value) =>
      value is Map ? value.cast<String, Object?>() : null;

  static DateTime? _dateOrNull(Object? value) =>
      value is String ? DateTime.parse(value).toUtc() : null;
}
