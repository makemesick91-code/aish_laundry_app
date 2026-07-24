import 'package:aish_core/aish_core.dart';
import 'package:aish_domain/aish_domain.dart';
import 'package:dio/dio.dart';

import 'api_client.dart';
import 'api_endpoints.dart';
import 'api_error_code.dart';
import 'api_response.dart';

/// The finer meaning of a production `CONFLICT` (HTTP 409).
///
/// The server overloads `CONFLICT` for three distinct situations, distinguished
/// ONLY by `error.details` (see `ProductionRegistry`/`QualityControlService`):
///
///   * a stale `expected_version` (`{"version": ["version_conflict"]}`);
///   * a transition the job's CURRENT state does not allow
///     (`{"state": ["invalid_transition" | "not_closed" | "not_in_rework"]}`);
///   * the same `client_reference` reused for a DIFFERENT payload
///     (`{"client_reference": ["reused_different_payload"]}`).
///
/// The recovery differs per case and the sync layer MUST branch on it:
///   * [staleVersion] and [invalidState] → reload the job and reconcile; the
///     command as sent is no longer valid, so a plain retry is wrong.
///   * [duplicateReferenceDifferentPayload] → a CLIENT bug: two different
///     commands were given the same reference. It is PERMANENT — never retried,
///     because the server will refuse it identically forever.
///
/// [unknown] is the fail-safe: a `CONFLICT` this build cannot classify is
/// treated as reload-and-reconcile, never as retry-the-same-payload.
enum ProductionConflict {
  staleVersion,
  invalidState,
  duplicateReferenceDifferentPayload,
  unknown;

  /// Classify a [Failure]. Returns null when the failure is not a production
  /// `CONFLICT` at all (a caller uses that to know a conflict-specific recovery
  /// does not apply).
  static ProductionConflict? fromFailure(Failure failure) {
    if (ApiErrorCode.parse(failure.code) != ApiErrorCode.conflict) {
      return null;
    }
    final details = failure.details;

    if (_hasMarker(details['client_reference'], 'reused_different_payload')) {
      return ProductionConflict.duplicateReferenceDifferentPayload;
    }
    if (_hasMarker(details['version'], 'version_conflict')) {
      return ProductionConflict.staleVersion;
    }
    final stateMarkers = details['state'];
    if (_hasAnyMarker(stateMarkers, const <String>{
      'invalid_transition',
      'not_closed',
      'not_in_rework',
      'not_awaiting_qc',
    })) {
      return ProductionConflict.invalidState;
    }
    // FR-074 batch state conflicts: a closed batch, or an item added by a
    // concurrent command, both mean "reload and reconcile", not "retry as-is".
    if (_hasMarker(details['status'], 'batch_closed')) {
      return ProductionConflict.invalidState;
    }
    if (_hasMarker(details['production_item_id'], 'already_member')) {
      return ProductionConflict.invalidState;
    }
    return ProductionConflict.unknown;
  }

  /// Whether the command that produced this conflict may EVER be retried as-is.
  ///
  /// Only [duplicateReferenceDifferentPayload] is permanently unretryable. The
  /// others are "reload and reconcile", which is not the same as "retry the
  /// same payload" — the caller reloads first and issues a fresh command.
  bool get isPermanent =>
      this == ProductionConflict.duplicateReferenceDifferentPayload;

  static bool _hasMarker(Object? node, String marker) =>
      _hasAnyMarker(node, <String>{marker});

  static bool _hasAnyMarker(Object? node, Set<String> markers) {
    if (node is List) {
      return node.any((e) => e is String && markers.contains(e));
    }
    if (node is String) {
      return markers.contains(node);
    }
    return false;
  }
}

/// Read/command access to the Step 6 production surface (FR-071 … FR-085,
/// DEC-0037).
///
/// ONE PLACE THAT KNOWS THE ENDPOINTS. A screen — or the sync worker — asks for
/// a typed result; it never builds a path, decodes an envelope, or decides a
/// transition. The server owns every transition (Rule 19 hard rule 4); this
/// repository sends a COMMAND and reads back the server's authoritative result.
///
/// `client_reference` is threaded through every write for idempotency (FR-062
/// discipline): the SAME reference with the SAME payload on a retry yields the
/// ORIGINAL result, never a second application. `expected_version` is the
/// optimistic-concurrency token; both travel in the request BODY, matching the
/// production controller (they are NOT the `If-Unmodified-Since-Version` header
/// the master-data surface uses).
///
/// PAID/READY STATE IS NEVER CLAIMED LOCALLY. `markReady` returns only after the
/// server has written the immutable first-ready anchor (FR-076); a caller must
/// not render READY_FOR_PICKUP before this acknowledgement.
final class ProductionRepository {
  const ProductionRepository(this._client);

  final ApiClient _client;

  static const int maxPerPage = 100;
  static const int defaultPageSize = 25;

  /// The production queue for the active tenant (and outlet, when one is
  /// selected — the server scopes it). Optionally filtered by [state].
  Future<Result<List<ProductionJobSummary>>> queue({
    ProductionState? state,
    int perPage = defaultPageSize,
    String? sort,
  }) async {
    final result = await _client.get(
      ApiEndpoints.productionQueue,
      query: <String, Object?>{
        if (state != null) 'state': state.wireValue,
        if (sort != null && sort.isNotEmpty) 'sort': sort,
        'per_page': _boundedPerPage(perPage),
      },
    );

    return _decode(
      result,
      (ApiSuccess success) =>
          _list(success, 'jobs', ProductionJobSummary.fromJson),
    );
  }

  /// One job's full detail plus its append-only timeline.
  Future<Result<ProductionJobDetail>> job(String id) async {
    final result = await _client.get(ApiEndpoints.productionJob(id));
    return _decode(
      result,
      (ApiSuccess success) =>
          ProductionJobDetail.fromResponse(success.dataAsMap),
    );
  }

  /// Start (or continue) a production stage: CREATED/IN_PROGRESS → IN_PROGRESS
  /// (`advance`). The server enumerates the legal transition; an illegal one is
  /// refused with `CONFLICT`/invalid_transition and changes nothing.
  Future<Result<ProductionJobSummary>> advance(
    String id, {
    required String stage,
    required String clientReference,
    int? expectedVersion,
  }) => _command(ApiEndpoints.productionJobAdvance(id), <String, Object?>{
    'stage': stage,
    'client_reference': clientReference,
    'expected_version': ?expectedVersion,
  });

  /// Place a job on hold with a mandatory reason code: IN_PROGRESS → BLOCKED.
  Future<Result<ProductionJobSummary>> block(
    String id, {
    required String reasonCode,
    required String clientReference,
    String? reason,
    int? expectedVersion,
  }) => _command(ApiEndpoints.productionJobBlock(id), <String, Object?>{
    'reason_code': reasonCode,
    if (reason != null && reason.isNotEmpty) 'reason': reason,
    'client_reference': clientReference,
    'expected_version': ?expectedVersion,
  });

  /// Resume a held job: BLOCKED → IN_PROGRESS.
  Future<Result<ProductionJobSummary>> resume(
    String id, {
    required String clientReference,
    int? expectedVersion,
  }) => _command(ApiEndpoints.productionJobResume(id), <String, Object?>{
    'client_reference': clientReference,
    'expected_version': ?expectedVersion,
  });

  /// Send a job to quality control: IN_PROGRESS → AWAITING_QC.
  Future<Result<ProductionJobSummary>> sendToQualityControl(
    String id, {
    required String clientReference,
    int? expectedVersion,
  }) => _command(ApiEndpoints.productionJobQcSend(id), <String, Object?>{
    'client_reference': clientReference,
    'expected_version': ?expectedVersion,
  });

  /// Record a QC verdict against an AWAITING_QC job. A FAILED verdict requires
  /// [defectReasonCode] (the server rejects it otherwise); PASSED/WAIVED close
  /// the job, FAILED opens a rework cycle. Returns the inspection and the job's
  /// new summary (server responds 201).
  Future<Result<QcInspectionResult>> recordQualityControl(
    String id, {
    required QualityControlVerdict verdict,
    required String clientReference,
    String? defectReasonCode,
    String? defectReason,
    int? expectedVersion,
  }) async {
    final result = await _client.post(
      ApiEndpoints.productionJobQcRecord(id),
      body: <String, Object?>{
        'verdict': verdict.wireValue,
        if (defectReasonCode != null && defectReasonCode.isNotEmpty)
          'defect_reason_code': defectReasonCode,
        if (defectReason != null && defectReason.isNotEmpty)
          'defect_reason': defectReason,
        'client_reference': clientReference,
        'expected_version': ?expectedVersion,
      },
    );
    return _decode(
      result,
      (ApiSuccess success) =>
          QcInspectionResult.fromResponse(success.dataAsMap),
    );
  }

  /// Complete an open rework cycle with a mandatory reason code:
  /// REWORK_IN_PROGRESS → AWAITING_QC.
  Future<Result<ProductionJobSummary>> completeRework(
    String id, {
    required String reasonCode,
    required String clientReference,
    int? expectedVersion,
  }) =>
      _command(ApiEndpoints.productionJobReworkComplete(id), <String, Object?>{
        'reason_code': reasonCode,
        'client_reference': clientReference,
        'expected_version': ?expectedVersion,
      });

  /// Mark a CLOSED job READY_FOR_PICKUP, writing the immutable first-ready
  /// anchor (FR-076). Returns the order's authoritative new status. Idempotent
  /// on [clientReference].
  Future<Result<ProductionReadyResult>> markReady(
    String id, {
    required String clientReference,
  }) async {
    final result = await _client.post(
      ApiEndpoints.productionJobReady(id),
      body: <String, Object?>{'client_reference': clientReference},
    );
    return _decode(
      result,
      (ApiSuccess success) =>
          ProductionReadyResult.fromResponse(success.dataAsMap),
    );
  }

  // --- FR-074 batch operations ---------------------------------------------

  /// The batches for the active tenant (and outlet, when one is selected — the
  /// server scopes it). Optionally filtered by [status] or [stage].
  Future<Result<List<ProductionBatchSummary>>> batches({
    ProductionBatchStatus? status,
    String? stage,
    int perPage = defaultPageSize,
    String? sort,
  }) async {
    final result = await _client.get(
      ApiEndpoints.productionBatches,
      query: <String, Object?>{
        if (status != null) 'status': status.wireValue,
        if (stage != null && stage.isNotEmpty) 'stage': stage,
        if (sort != null && sort.isNotEmpty) 'sort': sort,
        'per_page': _boundedPerPage(perPage),
      },
    );

    return _decode(
      result,
      (ApiSuccess success) =>
          _list(success, 'batches', ProductionBatchSummary.fromJson),
    );
  }

  /// One batch's detail (its current members) plus its append-only timeline.
  Future<Result<ProductionBatchDetail>> batch(String id) async {
    final result = await _client.get(ApiEndpoints.productionBatch(id));
    return _decode(
      result,
      (ApiSuccess success) =>
          ProductionBatchDetail.fromResponse(success.dataAsMap),
    );
  }

  /// Create an OPEN batch for the active outlet. Idempotent on [clientReference].
  Future<Result<ProductionBatchSummary>> createBatch({
    required String code,
    required String stage,
    required String clientReference,
  }) => _batchCommand(ApiEndpoints.productionBatches, <String, Object?>{
    'code': code,
    'stage': stage,
    'client_reference': clientReference,
  });

  /// Add an eligible item to an OPEN batch. Refused (VALIDATION/CONFLICT) when
  /// the item is at a different stage/outlet, its job is terminal, or it is
  /// already a member.
  Future<Result<ProductionBatchSummary>> addBatchItem(
    String batchId, {
    required String productionItemId,
    required String clientReference,
    int? expectedVersion,
  }) => _batchCommand(
    ApiEndpoints.productionBatchItems(batchId),
    <String, Object?>{
      'production_item_id': productionItemId,
      'client_reference': clientReference,
      'expected_version': ?expectedVersion,
    },
  );

  /// Remove a member from an OPEN batch. The current membership row is deleted;
  /// the fact is preserved in the append-only timeline (FR-074).
  Future<Result<ProductionBatchSummary>> removeBatchItem(
    String batchId,
    String productionItemId, {
    required String clientReference,
    int? expectedVersion,
  }) async {
    final result = await _client.delete(
      ApiEndpoints.productionBatchItem(batchId, productionItemId),
      body: <String, Object?>{
        'client_reference': clientReference,
        'expected_version': ?expectedVersion,
      },
    );
    return _decode(
      result,
      (ApiSuccess success) =>
          ProductionBatchSummary.fromJson(_object(success, 'batch')),
    );
  }

  /// Close an OPEN batch; a CLOSED batch is immutable thereafter. Idempotent on
  /// [clientReference].
  Future<Result<ProductionBatchSummary>> closeBatch(
    String batchId, {
    required String clientReference,
    int? expectedVersion,
  }) => _batchCommand(
    ApiEndpoints.productionBatchClose(batchId),
    <String, Object?>{
      'client_reference': clientReference,
      'expected_version': ?expectedVersion,
    },
  );

  // --- FR-083 QC defect-photo evidence -------------------------------------

  /// Upload a defect photo (multipart) against a FAILED QC inspection. Idempotent
  /// on [clientReference]: a replayed upload returns the ORIGINAL evidence and
  /// stores no second object. The server validates the bytes by content and
  /// rejects a non-image, malformed, mistyped, oversized, or wrong-dimension file.
  Future<Result<QcEvidence>> uploadQcEvidence(
    String jobId,
    String inspectionId, {
    required List<int> bytes,
    required String filename,
    required String clientReference,
  }) async {
    final form = FormData.fromMap(<String, Object?>{
      'client_reference': clientReference,
      'photo': MultipartFile.fromBytes(bytes, filename: filename),
    });
    final result = await _client.postMultipart(
      ApiEndpoints.productionQcEvidence(jobId, inspectionId),
      formData: form,
    );
    return _decode(
      result,
      (ApiSuccess success) => QcEvidence.fromJson(_object(success, 'evidence')),
    );
  }

  /// A short-lived signed URL for one stored evidence object. The bytes never
  /// pass through the app — the client fetches them directly from the signed URL.
  Future<Result<QcEvidenceUrl>> qcEvidenceUrl(
    String jobId,
    String inspectionId,
    String evidenceId,
  ) async {
    final result = await _client.get(
      ApiEndpoints.productionQcEvidenceUrl(jobId, inspectionId, evidenceId),
    );
    return _decode(
      result,
      (ApiSuccess success) => QcEvidenceUrl.fromJson(success.dataAsMap),
    );
  }

  /// The shared batch command shape: POST a body, read back `data.batch` as a
  /// summary (a detail response is a superset and parses the same).
  Future<Result<ProductionBatchSummary>> _batchCommand(
    String path,
    Map<String, Object?> body,
  ) async {
    final result = await _client.post(path, body: body);
    return _decode(
      result,
      (ApiSuccess success) =>
          ProductionBatchSummary.fromJson(_object(success, 'batch')),
    );
  }

  /// The shared command shape: POST a body, read back `data.job` as a summary.
  Future<Result<ProductionJobSummary>> _command(
    String path,
    Map<String, Object?> body,
  ) async {
    final result = await _client.post(path, body: body);
    return _decode(
      result,
      (ApiSuccess success) =>
          ProductionJobSummary.fromJson(_object(success, 'job')),
    );
  }

  /// Decode a success envelope into a typed value, converting a MALFORMED or
  /// unrecognised-enum payload into an `Err` rather than letting the parse
  /// exception escape as an unhandled async error. A production state or verdict
  /// the server sends that this build does not recognise fails SAFE here: the
  /// caller sees a `Failure`, never a thrown `ArgumentError`, and never a
  /// half-parsed job rendered as though it were understood (F1 — reject unknown
  /// lifecycle states safely).
  static Result<T> _decode<T>(
    Result<ApiSuccess> result,
    T Function(ApiSuccess) transform,
  ) => result.flatMap((ApiSuccess success) {
    try {
      return Result<T>.ok(transform(success));
    } on Object catch (error) {
      // The message names the failure TYPE, never the payload: a projection is
      // minimal-exposure, but a parse error message could still echo a server
      // value into diagnostics, so only the type is recorded.
      return Result<T>.err(
        Failure(
          kind: FailureKind.unexpected,
          message: 'Malformed production response (${error.runtimeType}).',
        ),
      );
    }
  });

  static int _boundedPerPage(int requested) =>
      requested < 1 ? 1 : (requested > maxPerPage ? maxPerPage : requested);

  static Map<String, Object?> _object(ApiSuccess success, String key) {
    final raw = success.dataAsMap[key];
    return raw is Map<String, Object?> ? raw : const <String, Object?>{};
  }

  static List<T> _list<T>(
    ApiSuccess success,
    String key,
    T Function(Map<String, Object?>) parse,
  ) {
    final raw = success.dataAsMap[key];
    if (raw is! List) {
      return const <Never>[];
    }
    return raw.cast<Map<String, Object?>>().map(parse).toList(growable: false);
  }
}
