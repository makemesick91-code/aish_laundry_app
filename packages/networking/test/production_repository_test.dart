import 'dart:convert';

import 'package:aish_core/aish_core.dart';
import 'package:aish_domain/aish_domain.dart';
import 'package:aish_networking/aish_networking.dart';
import 'package:dio/dio.dart';
import 'package:test/test.dart';

Environment env() => Environment.validate(
  environmentName: 'production',
  apiBaseUrl: 'https://api.contoh-fiktif.id/api/v1',
  appName: 'Uji',
).valueOrNull!;

/// Captures each request and replies with a scripted, pre-encoded JSON envelope.
class _Adapter implements HttpClientAdapter {
  _Adapter(this.statusCode, this.body);

  int statusCode;
  String body;
  RequestOptions? last;

  @override
  Future<ResponseBody> fetch(
    RequestOptions options,
    Stream<List<int>>? requestStream,
    Future<void>? cancelFuture,
  ) async {
    last = options;
    return ResponseBody.fromString(
      body,
      statusCode,
      headers: <String, List<String>>{
        Headers.contentTypeHeader: <String>[Headers.jsonContentType],
      },
    );
  }

  @override
  void close({bool force = false}) {}
}

// ignore: library_private_types_in_public_api
ApiClient clientWith(_Adapter adapter) {
  final dio = Dio()..httpClientAdapter = adapter;
  return ApiClient(
    environment: env(),
    transport: CredentialTransport.bearerToken,
    dio: dio,
  );
}

const String _summary =
    '{"id":"j1","order_id":"o1","outlet_id":"ot1","state":"IN_PROGRESS",'
    '"version":3,"block_reason_code":null,"updated_at":"2026-07-24T10:00:00+00:00"}';

const String _queueBody =
    '{"data":{"jobs":[$_summary]},'
    '"meta":{"request_id":"req_fiktif","page":1,"per_page":25,"total":1}}';

const String _detailBody =
    '{"data":{"job":{"id":"j1","order_id":"o1","outlet_id":"ot1",'
    '"state":"IN_PROGRESS","version":3,"block_reason_code":null,'
    '"updated_at":"2026-07-24T10:00:00+00:00","items":['
    '{"id":"i1","order_line_id":"l1","service_type":"kiloan","stage":"WASHING",'
    '"quantity_done":"2.500","units_total":0,"units_done":0},'
    '{"id":"i2","order_line_id":"l2","service_type":"satuan","stage":null,'
    '"quantity_done":null,"units_total":5,"units_done":2}]},'
    '"timeline":[{"type":"STAGE_STARTED","actor_membership_id":"m1",'
    '"occurred_at":"2026-07-24T09:00:00+00:00"}]},"meta":{"request_id":"r"}}';

const String _commandBody = '{"data":{"job":$_summary},"meta":{}}';

String conflict(String detailKey, String marker) =>
    '{"error":{"code":"CONFLICT","message":"konflik",'
    '"details":{"$detailKey":["$marker"]}},"meta":{"request_id":"r"}}';

void main() {
  group('ProductionRepository — reads', () {
    test(
      'queue() sends the state filter and parses the summary list',
      () async {
        final adapter = _Adapter(200, _queueBody);
        final repo = ProductionRepository(clientWith(adapter));

        final result = await repo.queue(state: ProductionState.inProgress);

        expect(result.isOk, isTrue);
        final jobs = result.valueOrNull!;
        expect(jobs.single.id, 'j1');
        expect(jobs.single.state, ProductionState.inProgress);
        // The version is an int, preserved exactly for optimistic concurrency.
        expect(jobs.single.version, 3);
        expect(adapter.last!.queryParameters['state'], 'IN_PROGRESS');
        expect(adapter.last!.path, contains('production/queue'));
      },
    );

    test('queue() bounds per_page to the server maximum', () async {
      final adapter = _Adapter(200, _queueBody);
      final repo = ProductionRepository(clientWith(adapter));

      await repo.queue(perPage: 9999);
      expect(adapter.last!.queryParameters['per_page'], 100);
    });

    test('job() parses detail items and the sibling timeline', () async {
      final adapter = _Adapter(200, _detailBody);
      final repo = ProductionRepository(clientWith(adapter));

      final result = await repo.job('j1');
      expect(result.isOk, isTrue);
      final detail = result.valueOrNull!;
      expect(detail.items, hasLength(2));

      final kiloan = detail.items.first;
      expect(kiloan.serviceType, ProductionServiceType.kiloan);
      expect(kiloan.stage, 'WASHING');
      // Decimal progress is preserved as the EXACT server string, never a double.
      expect(kiloan.quantityDone, '2.500');

      final satuan = detail.items[1];
      expect(satuan.serviceType, ProductionServiceType.satuan);
      expect(satuan.unitsTotal, 5);
      expect(satuan.unitsDone, 2);

      expect(detail.timeline.single.type, 'STAGE_STARTED');
      expect(detail.timeline.single.actorMembershipId, 'm1');
    });
  });

  group('ProductionRepository — commands', () {
    test(
      'advance() sends stage, client_reference and expected_version in the BODY',
      () async {
        final adapter = _Adapter(200, _commandBody);
        final repo = ProductionRepository(clientWith(adapter));

        final result = await repo.advance(
          'j1',
          stage: 'WASHING',
          clientReference: 'ref-1',
          expectedVersion: 3,
        );

        expect(result.isOk, isTrue);
        final body = adapter.last!.data as Map<String, Object?>;
        expect(adapter.last!.path, contains('production/jobs/j1/advance'));
        expect(body['stage'], 'WASHING');
        expect(body['client_reference'], 'ref-1');
        // Optimistic concurrency travels in the BODY for production, NOT the
        // If-Unmodified-Since-Version header the master-data surface uses.
        expect(body['expected_version'], 3);
        expect(
          adapter.last!.headers.containsKey('If-Unmodified-Since-Version'),
          isFalse,
        );
      },
    );

    test('block() sends the mandatory reason code', () async {
      final adapter = _Adapter(200, _commandBody);
      final repo = ProductionRepository(clientWith(adapter));

      await repo.block(
        'j1',
        reasonCode: 'MISSING_ITEM',
        reason: 'Satu item hilang',
        clientReference: 'ref-2',
        expectedVersion: 3,
      );
      final body = adapter.last!.data as Map<String, Object?>;
      expect(adapter.last!.path, contains('production/jobs/j1/block'));
      expect(body['reason_code'], 'MISSING_ITEM');
      expect(body['reason'], 'Satu item hilang');
    });

    test(
      'resume() and sendToQualityControl() post the client_reference',
      () async {
        final adapter = _Adapter(200, _commandBody);
        final repo = ProductionRepository(clientWith(adapter));

        await repo.resume('j1', clientReference: 'ref-3');
        expect(adapter.last!.path, contains('production/jobs/j1/resume'));
        expect((adapter.last!.data as Map)['client_reference'], 'ref-3');

        await repo.sendToQualityControl('j1', clientReference: 'ref-4');
        expect(
          adapter.last!.path,
          contains('production/jobs/j1/quality-control/send'),
        );
        expect((adapter.last!.data as Map)['client_reference'], 'ref-4');
      },
    );

    test(
      'recordQualityControl() PASSED parses the inspection and new job summary',
      () async {
        final adapter = _Adapter(
          201,
          '{"data":{"inspection":{"id":"insp1","verdict":"PASSED"},'
          '"job":{"id":"j1","order_id":"o1","outlet_id":"ot1","state":"CLOSED",'
          '"version":4,"block_reason_code":null,"updated_at":null}},"meta":{}}',
        );
        final repo = ProductionRepository(clientWith(adapter));

        final result = await repo.recordQualityControl(
          'j1',
          verdict: QualityControlVerdict.passed,
          clientReference: 'ref-5',
          expectedVersion: 3,
        );
        expect(result.isOk, isTrue);
        final qc = result.valueOrNull!;
        expect(qc.inspectionId, 'insp1');
        expect(qc.verdict, QualityControlVerdict.passed);
        expect(qc.job.state, ProductionState.closed);

        final body = adapter.last!.data as Map<String, Object?>;
        expect(body['verdict'], 'PASSED');
        expect(body.containsKey('defect_reason_code'), isFalse);
      },
    );

    test('recordQualityControl() FAILED sends the defect reason code', () async {
      final adapter = _Adapter(
        201,
        '{"data":{"inspection":{"id":"insp2","verdict":"FAILED_REWORK_REQUIRED"},'
        '"job":{"id":"j1","order_id":"o1","outlet_id":"ot1",'
        '"state":"REWORK_IN_PROGRESS","version":4,"block_reason_code":null,'
        '"updated_at":null}},"meta":{}}',
      );
      final repo = ProductionRepository(clientWith(adapter));

      final result = await repo.recordQualityControl(
        'j1',
        verdict: QualityControlVerdict.failedReworkRequired,
        defectReasonCode: 'STAIN',
        defectReason: 'Noda belum hilang',
        clientReference: 'ref-6',
      );
      expect(result.isOk, isTrue);
      final body = adapter.last!.data as Map<String, Object?>;
      expect(body['verdict'], 'FAILED_REWORK_REQUIRED');
      expect(body['defect_reason_code'], 'STAIN');
    });

    test('completeRework() posts the reason code', () async {
      final adapter = _Adapter(200, _commandBody);
      final repo = ProductionRepository(clientWith(adapter));

      await repo.completeRework(
        'j1',
        reasonCode: 'REDONE',
        clientReference: 'ref-7',
      );
      expect(
        adapter.last!.path,
        contains('production/jobs/j1/rework/complete'),
      );
      expect((adapter.last!.data as Map)['reason_code'], 'REDONE');
    });

    test(
      'markReady() posts the client_reference and parses order status',
      () async {
        final adapter = _Adapter(
          200,
          '{"data":{"order":{"id":"o1","status":"READY_FOR_PICKUP"}},"meta":{}}',
        );
        final repo = ProductionRepository(clientWith(adapter));

        final result = await repo.markReady('j1', clientReference: 'ref-8');
        expect(result.isOk, isTrue);
        final ready = result.valueOrNull!;
        expect(ready.orderId, 'o1');
        expect(ready.orderStatus, OrderStatus.readyForPickup);
        expect(adapter.last!.path, contains('production/jobs/j1/ready'));
      },
    );

    test(
      'advance() omits expected_version from the body when none is supplied',
      () async {
        final adapter = _Adapter(200, _commandBody);
        final repo = ProductionRepository(clientWith(adapter));

        await repo.advance('j1', stage: 'DRYING', clientReference: 'ref-9');
        final body = adapter.last!.data as Map<String, Object?>;
        expect(body.containsKey('expected_version'), isFalse);
      },
    );
  });

  group('ProductionRepository — error and malformed handling', () {
    test(
      'a server error envelope becomes an Err, never an exception',
      () async {
        final adapter = _Adapter(
          403,
          '{"error":{"code":"FORBIDDEN","message":"tidak boleh"},"meta":{}}',
        );
        final repo = ProductionRepository(clientWith(adapter));

        final result = await repo.advance(
          'j1',
          stage: 'WASHING',
          clientReference: 'r',
        );
        expect(result.isErr, isTrue);
        expect(result.failureOrNull!.kind, FailureKind.authorization);
      },
    );

    test('a NOT_FOUND is authorization-shaped (foreign == absent)', () async {
      final adapter = _Adapter(
        404,
        '{"error":{"code":"NOT_FOUND","message":"tidak ada"},"meta":{}}',
      );
      final repo = ProductionRepository(clientWith(adapter));

      final result = await repo.job('j-other-tenant');
      expect(result.isErr, isTrue);
      // A foreign job is indistinguishable from an absent one (Rule 48).
      expect(
        ApiErrorMapper.consequenceOf(result.failureOrNull!),
        ClientErrorConsequence.accessDenied,
      );
    });

    test(
      'an unknown production state fails SAFE — an Err, not a thrown ArgumentError',
      () async {
        final adapter = _Adapter(
          200,
          '{"data":{"jobs":[{"id":"j1","order_id":"o1","outlet_id":"ot1",'
          '"state":"TELEPORTED","version":1,"block_reason_code":null,'
          '"updated_at":null}]},"meta":{}}',
        );
        final repo = ProductionRepository(clientWith(adapter));

        final result = await repo.queue();
        expect(result.isErr, isTrue);
        expect(result.failureOrNull!.kind, FailureKind.unexpected);
      },
    );
  });

  group('ProductionConflict — 409 disambiguation', () {
    ProductionConflict? classify(String detailKey, String marker) {
      final (failure, _) = ApiErrorMapper.fromEnvelope(
        statusCode: 409,
        body: _json(conflict(detailKey, marker)),
      );
      return ProductionConflict.fromFailure(failure);
    }

    test('version_conflict -> staleVersion (reload and reconcile)', () {
      final c = classify('version', 'version_conflict');
      expect(c, ProductionConflict.staleVersion);
      expect(c!.isPermanent, isFalse);
    });

    test('invalid_transition -> invalidState (reload and reconcile)', () {
      expect(
        classify('state', 'invalid_transition'),
        ProductionConflict.invalidState,
      );
      expect(classify('state', 'not_closed'), ProductionConflict.invalidState);
      expect(
        classify('state', 'not_in_rework'),
        ProductionConflict.invalidState,
      );
    });

    test(
      'reused_different_payload -> duplicate, and it is PERMANENT (never retry)',
      () {
        final c = classify('client_reference', 'reused_different_payload');
        expect(c, ProductionConflict.duplicateReferenceDifferentPayload);
        expect(c!.isPermanent, isTrue);
      },
    );

    test('a non-conflict failure returns null', () {
      final (failure, _) = ApiErrorMapper.fromEnvelope(
        statusCode: 422,
        body: _json(
          '{"error":{"code":"VALIDATION_FAILED","message":"x"},"meta":{}}',
        ),
      );
      expect(ProductionConflict.fromFailure(failure), isNull);
    });

    test(
      'an unclassifiable conflict fails safe to unknown (not permanent)',
      () {
        final c = classify('mystery', 'something_new');
        expect(c, ProductionConflict.unknown);
        expect(c!.isPermanent, isFalse);
      },
    );

    // FR-074 batch state conflicts both mean "reload and reconcile".
    test('batch_closed (status) -> invalidState', () {
      expect(
        classify('status', 'batch_closed'),
        ProductionConflict.invalidState,
      );
    });

    test('already_member (production_item_id) -> invalidState', () {
      expect(
        classify('production_item_id', 'already_member'),
        ProductionConflict.invalidState,
      );
    });
  });

  group('ProductionRepository — batch operations (FR-074)', () {
    const batchSummary =
        '{"id":"b1","code":"BATCH-1","stage":"SORTING","status":"open",'
        '"version":1,"outlet_id":"ot1","item_count":0,"closed_at":null,'
        '"updated_at":"2026-07-24T10:00:00+00:00"}';
    const batchCommandBody = '{"data":{"batch":$batchSummary},"meta":{}}';

    test(
      'createBatch() posts code/stage/client_reference and parses the summary',
      () async {
        final adapter = _Adapter(201, batchCommandBody);
        final repo = ProductionRepository(clientWith(adapter));

        final result = await repo.createBatch(
          code: 'BATCH-1',
          stage: 'SORTING',
          clientReference: 'ref-b1',
        );
        expect(result.isOk, isTrue);
        expect(result.valueOrNull!.status, ProductionBatchStatus.open);
        expect(result.valueOrNull!.code, 'BATCH-1');
        final body = adapter.last!.data as Map<String, Object?>;
        expect(adapter.last!.path, contains('production/batches'));
        expect(body['code'], 'BATCH-1');
        expect(body['stage'], 'SORTING');
        expect(body['client_reference'], 'ref-b1');
      },
    );

    test(
      'addBatchItem() posts production_item_id and expected_version in the BODY',
      () async {
        final adapter = _Adapter(201, batchCommandBody);
        final repo = ProductionRepository(clientWith(adapter));

        await repo.addBatchItem(
          'b1',
          productionItemId: 'i1',
          clientReference: 'ref-b2',
          expectedVersion: 1,
        );
        final body = adapter.last!.data as Map<String, Object?>;
        expect(adapter.last!.path, contains('production/batches/b1/items'));
        expect(body['production_item_id'], 'i1');
        expect(body['expected_version'], 1);
      },
    );

    test(
      'removeBatchItem() DELETEs with the client_reference in the body',
      () async {
        final adapter = _Adapter(200, batchCommandBody);
        final repo = ProductionRepository(clientWith(adapter));

        final result = await repo.removeBatchItem(
          'b1',
          'i1',
          clientReference: 'ref-b3',
        );
        expect(result.isOk, isTrue);
        expect(adapter.last!.method, 'DELETE');
        expect(adapter.last!.path, contains('production/batches/b1/items/i1'));
        expect((adapter.last!.data as Map)['client_reference'], 'ref-b3');
      },
    );

    test('closeBatch() parses the closed status', () async {
      final adapter = _Adapter(
        200,
        '{"data":{"batch":{"id":"b1","code":"BATCH-1","stage":"SORTING",'
        '"status":"closed","version":2,"outlet_id":"ot1","item_count":0,'
        '"closed_at":"2026-07-24T11:00:00+00:00","updated_at":null}},"meta":{}}',
      );
      final repo = ProductionRepository(clientWith(adapter));

      final result = await repo.closeBatch('b1', clientReference: 'ref-b4');
      expect(result.isOk, isTrue);
      expect(result.valueOrNull!.status, ProductionBatchStatus.closed);
      expect(adapter.last!.path, contains('production/batches/b1/close'));
    });

    test('batches() sends the status filter and bounds per_page', () async {
      final adapter = _Adapter(
        200,
        '{"data":{"batches":[$batchSummary]},'
        '"meta":{"page":1,"per_page":100,"total":1}}',
      );
      final repo = ProductionRepository(clientWith(adapter));

      final result = await repo.batches(
        status: ProductionBatchStatus.open,
        perPage: 9999,
      );
      expect(result.isOk, isTrue);
      expect(result.valueOrNull!.single.id, 'b1');
      expect(adapter.last!.queryParameters['status'], 'open');
      expect(adapter.last!.queryParameters['per_page'], 100);
    });

    test('batch() parses members and the sibling timeline', () async {
      final adapter = _Adapter(
        200,
        '{"data":{"batch":{"id":"b1","code":"BATCH-1","stage":"SORTING",'
        '"status":"open","version":1,"outlet_id":"ot1","item_count":1,'
        '"closed_at":null,"updated_at":null,"items":['
        '{"production_item_id":"i1","service_type":"kiloan","stage":"SORTING",'
        '"added_at":"2026-07-24T10:00:00+00:00"}]},'
        '"timeline":[{"type":"BatchItemAdded","actor_membership_id":"m1",'
        '"production_item_id":"i1","occurred_at":"2026-07-24T10:00:00+00:00"}]},'
        '"meta":{}}',
      );
      final repo = ProductionRepository(clientWith(adapter));

      final result = await repo.batch('b1');
      expect(result.isOk, isTrue);
      final detail = result.valueOrNull!;
      expect(detail.items.single.productionItemId, 'i1');
      expect(detail.items.single.serviceType, ProductionServiceType.kiloan);
      expect(detail.timeline.single.type, 'BatchItemAdded');
      expect(detail.timeline.single.productionItemId, 'i1');
    });

    test(
      'an unknown batch status fails SAFE — an Err, not a thrown error',
      () async {
        final adapter = _Adapter(
          200,
          '{"data":{"batches":[{"id":"b1","code":"B","stage":"SORTING",'
          '"status":"VAPORISED","version":1,"outlet_id":"ot1","item_count":0,'
          '"closed_at":null,"updated_at":null}]},"meta":{}}',
        );
        final repo = ProductionRepository(clientWith(adapter));

        final result = await repo.batches();
        expect(result.isErr, isTrue);
        expect(result.failureOrNull!.kind, FailureKind.unexpected);
      },
    );
  });

  group('ProductionRepository — QC defect-photo evidence (FR-083)', () {
    test('uploadQcEvidence() posts multipart to the inspection path', () async {
      final adapter = _Adapter(
        201,
        '{"data":{"evidence":{"id":"ev1","inspection_id":"insp-1",'
        '"content_type":"image/png","byte_size":9,"checksum_sha256":"abc",'
        '"status":"stored","uploaded_at":null}},"meta":{}}',
      );
      final repo = ProductionRepository(clientWith(adapter));

      final result = await repo.uploadQcEvidence(
        'job-1',
        'insp-1',
        bytes: const <int>[1, 2, 3, 4, 5, 6, 7, 8, 9],
        filename: 'defect.png',
        clientReference: 'ref-e1',
      );
      expect(result.isOk, isTrue);
      final evidence = result.valueOrNull!;
      expect(evidence.id, 'ev1');
      expect(evidence.contentType, 'image/png');
      expect(evidence.checksumSha256, 'abc');
      expect(
        adapter.last!.path,
        contains('jobs/job-1/quality-control/insp-1/evidence'),
      );
      // A multipart body carries the file, not a JSON map.
      expect(adapter.last!.data, isA<FormData>());
    });

    test('qcEvidenceUrl() returns the short-lived signed URL', () async {
      final adapter = _Adapter(
        200,
        '{"data":{"url":"https://minio.local/aish-evidence-dev/x?X-Amz-Signature=y",'
        '"expires_in":300},"meta":{}}',
      );
      final repo = ProductionRepository(clientWith(adapter));

      final result = await repo.qcEvidenceUrl('job-1', 'insp-1', 'ev1');
      expect(result.isOk, isTrue);
      expect(result.valueOrNull!.expiresInSeconds, 300);
      expect(result.valueOrNull!.url, contains('X-Amz-Signature'));
      expect(
        adapter.last!.path,
        contains('quality-control/insp-1/evidence/ev1/url'),
      );
    });

    test(
      'an upload authorization failure becomes an Err, not an exception',
      () async {
        final adapter = _Adapter(
          403,
          '{"error":{"code":"FORBIDDEN","message":"tidak boleh"},"meta":{}}',
        );
        final repo = ProductionRepository(clientWith(adapter));

        final result = await repo.uploadQcEvidence(
          'job-1',
          'insp-1',
          bytes: const <int>[1, 2, 3],
          filename: 'x.png',
          clientReference: 'r',
        );
        expect(result.isErr, isTrue);
        expect(result.failureOrNull!.kind, FailureKind.authorization);
      },
    );
  });
}

Map<String, Object?> _json(String raw) =>
    (jsonDecode(raw) as Map).cast<String, Object?>();
