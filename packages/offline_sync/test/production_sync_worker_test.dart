import 'dart:async';

import 'package:aish_core/aish_core.dart';
import 'package:aish_networking/aish_networking.dart';
import 'package:aish_offline_sync/aish_offline_sync.dart';
import 'package:dio/dio.dart';
import 'package:test/test.dart';

// --- test doubles ------------------------------------------------------------

final DateTime _t0 = DateTime.utc(2026, 7, 24, 9);

class _TestClock implements Clock {
  _TestClock(this._now);
  DateTime _now;
  @override
  DateTime nowUtc() => _now;
  void set(DateTime t) => _now = t;
  void advance(Duration d) => _now = _now.add(d);
}

class _Conn implements ConnectivityMonitor {
  _Conn({this.online = true});
  bool online;
  final StreamController<bool> _controller = StreamController<bool>.broadcast();
  @override
  Future<bool> isOnline() async => online;
  @override
  Stream<bool> get onlineChanges => _controller.stream;
  void goOnline() {
    online = true;
    _controller.add(true);
  }
}

/// One scripted server outcome: a body+status, or a thrown transport error.
class _Step {
  const _Step.ok(this.status, this.body) : throwType = null;
  const _Step.throwing(this.throwType) : status = null, body = null;
  final int? status;
  final String? body;
  final DioExceptionType? throwType;
}

/// Replies to each call from a script; repeats the LAST step once exhausted, so
/// an idempotent replay on the next drain sees the same (or a follow-up) result.
class _ScriptedAdapter implements HttpClientAdapter {
  _ScriptedAdapter(this._steps);
  final List<_Step> _steps;
  int _i = 0;
  final List<RequestOptions> requests = <RequestOptions>[];

  @override
  Future<ResponseBody> fetch(
    RequestOptions options,
    Stream<List<int>>? requestStream,
    Future<void>? cancelFuture,
  ) async {
    requests.add(options);
    final step = _i < _steps.length ? _steps[_i] : _steps.last;
    _i++;
    if (step.throwType != null) {
      throw DioException(requestOptions: options, type: step.throwType!);
    }
    return ResponseBody.fromString(
      step.body!,
      step.status!,
      headers: <String, List<String>>{
        Headers.contentTypeHeader: <String>[Headers.jsonContentType],
      },
    );
  }

  @override
  void close({bool force = false}) {}
}

Environment _env() => Environment.validate(
  environmentName: 'production',
  apiBaseUrl: 'https://api.contoh-fiktif.id/api/v1',
  appName: 'Uji',
).valueOrNull!;

ProductionRepository _repoWith(_ScriptedAdapter adapter) {
  final dio = Dio()..httpClientAdapter = adapter;
  return ProductionRepository(
    ApiClient(
      environment: _env(),
      transport: CredentialTransport.bearerToken,
      dio: dio,
    ),
  );
}

ProductionCommandQueue _queue(SecureCredentialStore store) =>
    ProductionCommandQueue(store: store, userId: 'usr-1', tenantId: 'ten-A');

ProductionCommand _advance({
  String reference = 'r1',
  String jobId = 'job-1',
  int? expectedVersion = 3,
}) => ProductionCommand(
  clientReference: reference,
  tenantId: 'ten-A',
  userId: 'usr-1',
  jobId: jobId,
  outletId: 'out-1',
  type: ProductionCommandType.advance,
  createdAtUtc: _t0,
  expectedVersion: expectedVersion,
  payload: const <String, Object?>{'stage': 'WASHING'},
);

// --- scripted server bodies --------------------------------------------------

const String _summaryOk =
    '{"data":{"job":{"id":"job-1","order_id":"o1","outlet_id":"out-1",'
    '"state":"IN_PROGRESS","version":4,"block_reason_code":null,'
    '"updated_at":null}},"meta":{}}';

String _conflict(String key, String marker) =>
    '{"error":{"code":"CONFLICT","message":"konflik",'
    '"details":{"$key":["$marker"]}},"meta":{}}';

const String _forbidden =
    '{"error":{"code":"FORBIDDEN","message":"tidak boleh"},"meta":{}}';
const String _sessionExpired =
    '{"error":{"code":"SESSION_EXPIRED","message":"kadaluarsa"},"meta":{}}';
const String _validation =
    '{"error":{"code":"VALIDATION_FAILED","message":"tidak valid"},"meta":{}}';
const String _serviceUnavailable =
    '{"error":{"code":"SERVICE_UNAVAILABLE","message":"gangguan"},"meta":{}}';

// --- FR-074 batch commands ---------------------------------------------------

const String _batchOk =
    '{"data":{"batch":{"id":"bat-1","code":"BATCH-1","stage":"SORTING",'
    '"status":"open","version":2,"outlet_id":"out-1","item_count":1,'
    '"closed_at":null,"updated_at":null}},"meta":{}}';

ProductionCommand _createBatch({String reference = 'cb'}) => ProductionCommand(
  clientReference: reference,
  tenantId: 'ten-A',
  userId: 'usr-1',
  outletId: 'out-1',
  type: ProductionCommandType.createBatch,
  createdAtUtc: _t0,
  payload: const <String, Object?>{'code': 'BATCH-1', 'stage': 'SORTING'},
);

ProductionCommand _addBatchItem({
  String reference = 'add',
  String batchId = 'bat-1',
  String itemId = 'i1',
  DateTime? at,
  int? expectedVersion = 1,
}) => ProductionCommand(
  clientReference: reference,
  tenantId: 'ten-A',
  userId: 'usr-1',
  batchId: batchId,
  itemId: itemId,
  type: ProductionCommandType.addBatchItem,
  createdAtUtc: at ?? _t0,
  expectedVersion: expectedVersion,
);

ProductionCommand _closeBatch({
  String reference = 'close',
  String batchId = 'bat-1',
  DateTime? at,
  int? expectedVersion = 1,
}) => ProductionCommand(
  clientReference: reference,
  tenantId: 'ten-A',
  userId: 'usr-1',
  batchId: batchId,
  type: ProductionCommandType.closeBatch,
  createdAtUtc: at ?? _t0,
  expectedVersion: expectedVersion,
);

void main() {
  ProductionSyncWorker workerWith({
    required _ScriptedAdapter adapter,
    required ProductionCommandQueue queue,
    required _TestClock clock,
    _Conn? conn,
  }) => ProductionSyncWorker(
    repository: _repoWith(adapter),
    queue: queue,
    clock: clock,
    connectivity: conn ?? _Conn(),
    baseBackoff: const Duration(seconds: 2),
    maxBackoff: const Duration(minutes: 5),
    maxAttempts: 4,
  );

  group('happy path and acknowledgement', () {
    test(
      'a queued command syncs and stores the canonical server version',
      () async {
        final store = InMemoryCredentialStore();
        final queue = _queue(store);
        await queue.enqueue(_advance());
        final adapter = _ScriptedAdapter(<_Step>[
          const _Step.ok(200, _summaryOk),
        ]);
        final clock = _TestClock(_t0);

        final pass = await workerWith(
          adapter: adapter,
          queue: queue,
          clock: clock,
        ).drain();

        expect(pass.synced, 1);
        final stored = (await queue.byReference('r1')).valueOrNull!;
        expect(stored.status, ProductionCommandStatus.synced);
        expect(stored.serverVersion, 4);
        expect(stored.acknowledgement, isNotNull);
      },
    );
  });

  group('connectivity', () {
    test(
      'offline: nothing is attempted and the command stays pending',
      () async {
        final store = InMemoryCredentialStore();
        final queue = _queue(store);
        await queue.enqueue(_advance());
        final adapter = _ScriptedAdapter(<_Step>[
          const _Step.ok(200, _summaryOk),
        ]);

        final pass = await workerWith(
          adapter: adapter,
          queue: queue,
          clock: _TestClock(_t0),
          conn: _Conn(online: false),
        ).drain();

        expect(pass.offline, isTrue);
        expect(adapter.requests, isEmpty);
        expect(
          (await queue.byReference('r1')).valueOrNull!.status,
          ProductionCommandStatus.pending,
        );
      },
    );

    test('reconnect: draining once back online syncs the command', () async {
      final store = InMemoryCredentialStore();
      final queue = _queue(store);
      await queue.enqueue(_advance());
      final adapter = _ScriptedAdapter(<_Step>[
        const _Step.ok(200, _summaryOk),
      ]);
      final conn = _Conn(online: false);
      final worker = workerWith(
        adapter: adapter,
        queue: queue,
        clock: _TestClock(_t0),
        conn: conn,
      );

      expect((await worker.drain()).offline, isTrue);
      conn.online = true;
      final pass = await worker.drain();
      expect(pass.synced, 1);
    });
  });

  group('transient failure, backoff and reconciliation', () {
    test('network loss -> retryWait with the base backoff delay', () async {
      final store = InMemoryCredentialStore();
      final queue = _queue(store);
      await queue.enqueue(_advance());
      final adapter = _ScriptedAdapter(<_Step>[
        const _Step.throwing(DioExceptionType.connectionError),
      ]);
      final clock = _TestClock(_t0);

      final pass = await workerWith(
        adapter: adapter,
        queue: queue,
        clock: clock,
      ).drain();

      expect(pass.deferred, 1);
      final stored = (await queue.byReference('r1')).valueOrNull!;
      expect(stored.status, ProductionCommandStatus.retryWait);
      expect(stored.attemptCount, 1);
      expect(stored.nextRetryAtUtc, _t0.add(const Duration(seconds: 2)));
    });

    test(
      'a not-yet-due retryWait command is skipped, then backoff doubles',
      () async {
        final store = InMemoryCredentialStore();
        final queue = _queue(store);
        await queue.enqueue(_advance());
        final adapter = _ScriptedAdapter(<_Step>[
          const _Step.throwing(DioExceptionType.connectionError),
        ]);
        final clock = _TestClock(_t0);
        final worker = workerWith(adapter: adapter, queue: queue, clock: clock);

        await worker.drain(); // attempt 1 -> next at t0+2s
        // Not yet due: a drain at t0 attempts nothing.
        final skipped = await worker.drain();
        expect(skipped.attempted, 0);

        // Advance past the window; attempt 2 -> delay doubles to 4s.
        clock.set(_t0.add(const Duration(seconds: 2)));
        await worker.drain();
        final stored = (await queue.byReference('r1')).valueOrNull!;
        expect(stored.attemptCount, 2);
        expect(
          stored.nextRetryAtUtc,
          _t0.add(const Duration(seconds: 2)).add(const Duration(seconds: 4)),
        );
      },
    );

    test(
      'bounded retries: exhausting maxAttempts becomes a permanent failure',
      () async {
        final store = InMemoryCredentialStore();
        final queue = _queue(store);
        await queue.enqueue(_advance());
        final adapter = _ScriptedAdapter(<_Step>[
          const _Step.throwing(DioExceptionType.connectionError),
        ]);
        final clock = _TestClock(_t0);
        final worker = workerWith(adapter: adapter, queue: queue, clock: clock);

        // maxAttempts = 4. Drain repeatedly, advancing past each backoff window.
        for (var i = 0; i < 4; i++) {
          await worker.drain();
          clock.advance(const Duration(minutes: 10));
        }
        final stored = (await queue.byReference('r1')).valueOrNull!;
        expect(stored.status, ProductionCommandStatus.failedPermanent);
      },
    );

    test(
      'timeout AFTER a server commit reconciles via idempotent replay (same reference)',
      () async {
        final store = InMemoryCredentialStore();
        final queue = _queue(store);
        await queue.enqueue(_advance());
        // First attempt times out (the server may or may not have committed);
        // the replay returns the canonical result.
        final adapter = _ScriptedAdapter(<_Step>[
          const _Step.throwing(DioExceptionType.receiveTimeout),
          const _Step.ok(200, _summaryOk),
        ]);
        final clock = _TestClock(_t0);
        final worker = workerWith(adapter: adapter, queue: queue, clock: clock);

        await worker.drain(); // times out -> retryWait
        clock.advance(const Duration(seconds: 5));
        final pass = await worker.drain(); // idempotent replay -> synced

        expect(pass.synced, 1);
        expect(
          (await queue.byReference('r1')).valueOrNull!.status,
          ProductionCommandStatus.synced,
        );
        // BOTH attempts carried the SAME client_reference (never regenerated).
        final refs = adapter.requests
            .map((r) => (r.data as Map)['client_reference'])
            .toSet();
        expect(refs, <String>{'r1'});
      },
    );

    test('a 503 is transient (retryWait), a plain network loss too', () async {
      final store = InMemoryCredentialStore();
      final queue = _queue(store);
      await queue.enqueue(_advance());
      final adapter = _ScriptedAdapter(<_Step>[
        const _Step.ok(503, _serviceUnavailable),
      ]);
      final pass = await workerWith(
        adapter: adapter,
        queue: queue,
        clock: _TestClock(_t0),
      ).drain();
      expect(pass.deferred, 1);
      expect(
        (await queue.byReference('r1')).valueOrNull!.status,
        ProductionCommandStatus.retryWait,
      );
    });
  });

  group('conflict and permanent classification', () {
    test(
      'a stale version -> CONFLICT (needs human), server state not overwritten',
      () async {
        final store = InMemoryCredentialStore();
        final queue = _queue(store);
        await queue.enqueue(_advance());
        final adapter = _ScriptedAdapter(<_Step>[
          _Step.ok(409, _conflict('version', 'version_conflict')),
        ]);
        final pass = await workerWith(
          adapter: adapter,
          queue: queue,
          clock: _TestClock(_t0),
        ).drain();

        expect(pass.conflicts, 1);
        final stored = (await queue.byReference('r1')).valueOrNull!;
        expect(stored.status, ProductionCommandStatus.conflict);
        expect(stored.conflictCode, ProductionConflict.staleVersion.name);
      },
    );

    test(
      'a reused reference with a different payload -> permanent, never retried',
      () async {
        final store = InMemoryCredentialStore();
        final queue = _queue(store);
        await queue.enqueue(_advance());
        final adapter = _ScriptedAdapter(<_Step>[
          _Step.ok(
            409,
            _conflict('client_reference', 'reused_different_payload'),
          ),
        ]);
        final pass = await workerWith(
          adapter: adapter,
          queue: queue,
          clock: _TestClock(_t0),
        ).drain();

        expect(pass.permanentFailures, 1);
        expect(
          (await queue.byReference('r1')).valueOrNull!.status,
          ProductionCommandStatus.failedPermanent,
        );
      },
    );

    test(
      'a forged/malformed 200 body never fakes a SYNCED (honest acknowledgement)',
      () async {
        final store = InMemoryCredentialStore();
        final queue = _queue(store);
        await queue.enqueue(_advance());
        // A 200 whose "job" is structurally wrong (unknown state). The repository
        // returns an Err rather than a parsed job, so the worker must NOT store
        // this as a canonical success.
        final adapter = _ScriptedAdapter(<_Step>[
          const _Step.ok(
            200,
            '{"data":{"job":{"id":"job-1","order_id":"o1","outlet_id":"out-1",'
            '"state":"FORGED","version":9,"block_reason_code":null,'
            '"updated_at":null}},"meta":{}}',
          ),
        ]);
        final pass = await workerWith(
          adapter: adapter,
          queue: queue,
          clock: _TestClock(_t0),
        ).drain();

        expect(pass.synced, 0);
        expect(
          (await queue.byReference('r1')).valueOrNull!.status,
          isNot(ProductionCommandStatus.synced),
        );
      },
    );

    test('a 422 validation refusal is permanent, not retried', () async {
      final store = InMemoryCredentialStore();
      final queue = _queue(store);
      await queue.enqueue(_advance());
      final adapter = _ScriptedAdapter(<_Step>[
        const _Step.ok(422, _validation),
      ]);
      final pass = await workerWith(
        adapter: adapter,
        queue: queue,
        clock: _TestClock(_t0),
      ).drain();
      expect(pass.permanentFailures, 1);
    });

    test('a 403 (permission revoked / foreign job) is permanent', () async {
      final store = InMemoryCredentialStore();
      final queue = _queue(store);
      await queue.enqueue(_advance());
      final adapter = _ScriptedAdapter(<_Step>[
        const _Step.ok(403, _forbidden),
      ]);
      final pass = await workerWith(
        adapter: adapter,
        queue: queue,
        clock: _TestClock(_t0),
      ).drain();
      expect(pass.permanentFailures, 1);
    });
  });

  group('authentication and work protection', () {
    test(
      'a 401 stops the drain and returns the command to pending (work kept)',
      () async {
        final store = InMemoryCredentialStore();
        final queue = _queue(store);
        await queue.enqueue(_advance());
        final adapter = _ScriptedAdapter(<_Step>[
          const _Step.ok(401, _sessionExpired),
        ]);
        final pass = await workerWith(
          adapter: adapter,
          queue: queue,
          clock: _TestClock(_t0),
        ).drain();

        expect(pass.authenticationRequired, isTrue);
        // The queued work is NOT discarded on session expiry (Rule 29).
        expect(
          (await queue.byReference('r1')).valueOrNull!.status,
          ProductionCommandStatus.pending,
        );
      },
    );
  });

  group('ordering and races', () {
    test('a same-job successor is held when its predecessor fails', () async {
      final store = InMemoryCredentialStore();
      final queue = _queue(store);
      // Two commands for the SAME job; the first (older) fails transiently.
      await queue.enqueue(_advance(reference: 'first'));
      await queue.enqueue(
        ProductionCommand(
          clientReference: 'second',
          tenantId: 'ten-A',
          userId: 'usr-1',
          jobId: 'job-1',
          type: ProductionCommandType.sendToQc,
          createdAtUtc: _t0.add(const Duration(minutes: 1)),
          expectedVersion: 3,
        ),
      );
      final adapter = _ScriptedAdapter(<_Step>[
        const _Step.throwing(DioExceptionType.connectionError),
      ]);
      await workerWith(
        adapter: adapter,
        queue: queue,
        clock: _TestClock(_t0),
      ).drain();

      // Only ONE request was made — the successor for the same job was held.
      expect(adapter.requests, hasLength(1));
      expect(
        (await queue.byReference('second')).valueOrNull!.status,
        ProductionCommandStatus.pending,
      );
    });

    test(
      'two workers draining concurrently produce one canonical synced result',
      () async {
        final store = InMemoryCredentialStore();
        final queue = _queue(store);
        await queue.enqueue(_advance());
        // Both calls succeed — the server dedupes on client_reference, so a
        // replay returns the original. Whichever worker writes synced wins.
        final adapter = _ScriptedAdapter(<_Step>[
          const _Step.ok(200, _summaryOk),
          const _Step.ok(200, _summaryOk),
        ]);
        final clock = _TestClock(_t0);
        final a = workerWith(adapter: adapter, queue: queue, clock: clock);
        final b = workerWith(adapter: adapter, queue: queue, clock: clock);

        await Future.wait(<Future<SyncPass>>[a.drain(), b.drain()]);

        expect(
          (await queue.byReference('r1')).valueOrNull!.status,
          ProductionCommandStatus.synced,
        );
      },
    );

    test(
      'a fresh worker over a revived queue drains it (app restart)',
      () async {
        final store = InMemoryCredentialStore();
        await _queue(store).enqueue(_advance());

        // Restart: brand-new queue + worker over the same store.
        final revived = _queue(store);
        final adapter = _ScriptedAdapter(<_Step>[
          const _Step.ok(200, _summaryOk),
        ]);
        final pass = await workerWith(
          adapter: adapter,
          queue: revived,
          clock: _TestClock(_t0),
        ).drain();
        expect(pass.synced, 1);
      },
    );
  });

  group('manual retry', () {
    test('retryNow forces a retryWait command due and drains it', () async {
      final store = InMemoryCredentialStore();
      final queue = _queue(store);
      await queue.enqueue(_advance());
      final adapter = _ScriptedAdapter(<_Step>[
        const _Step.throwing(DioExceptionType.connectionError),
        const _Step.ok(200, _summaryOk),
      ]);
      final clock = _TestClock(_t0);
      final worker = workerWith(adapter: adapter, queue: queue, clock: clock);

      await worker.drain(); // -> retryWait, not due until t0+2s
      // Manual "sync now" without waiting for the backoff window.
      final pass = await worker.retryNow('r1');
      expect(pass.synced, 1);
    });

    test(
      'retryNow refuses a conflicted command (reload-and-reissue, not resend)',
      () async {
        final store = InMemoryCredentialStore();
        final queue = _queue(store);
        await queue.enqueue(_advance());
        final adapter = _ScriptedAdapter(<_Step>[
          _Step.ok(409, _conflict('version', 'version_conflict')),
        ]);
        final worker = workerWith(
          adapter: adapter,
          queue: queue,
          clock: _TestClock(_t0),
        );
        await worker.drain(); // -> conflict

        final before = adapter.requests.length;
        final pass = await worker.retryNow('r1');
        // No new request: a conflict is not silently re-sent.
        expect(adapter.requests.length, before);
        expect(pass.attempted, 0);
      },
    );
  });

  group('FR-074 batch commands', () {
    test(
      'a queued create-batch syncs and stores the canonical version',
      () async {
        final store = InMemoryCredentialStore();
        final queue = _queue(store);
        await queue.enqueue(_createBatch());
        final adapter = _ScriptedAdapter(<_Step>[
          const _Step.ok(201, _batchOk),
        ]);

        final pass = await workerWith(
          adapter: adapter,
          queue: queue,
          clock: _TestClock(_t0),
        ).drain();

        expect(pass.synced, 1);
        final stored = (await queue.byReference('cb')).valueOrNull!;
        expect(stored.status, ProductionCommandStatus.synced);
        expect(stored.serverVersion, 2);
        expect(adapter.requests.single.path, contains('production/batches'));
      },
    );

    test('a same-batch successor is held when its predecessor fails', () async {
      final store = InMemoryCredentialStore();
      final queue = _queue(store);
      // add then close for the SAME batch; the older add fails transiently.
      await queue.enqueue(_addBatchItem(reference: 'add'));
      await queue.enqueue(
        _closeBatch(
          reference: 'close',
          at: _t0.add(const Duration(minutes: 1)),
        ),
      );
      final adapter = _ScriptedAdapter(<_Step>[
        const _Step.throwing(DioExceptionType.connectionError),
      ]);

      await workerWith(
        adapter: adapter,
        queue: queue,
        clock: _TestClock(_t0),
      ).drain();

      // Only ONE request: the close was held behind the failed add (same batch).
      expect(adapter.requests, hasLength(1));
      expect(
        (await queue.byReference('close')).valueOrNull!.status,
        ProductionCommandStatus.pending,
      );
    });

    test(
      'a batch_closed conflict surfaces for a human (reload and reconcile)',
      () async {
        final store = InMemoryCredentialStore();
        final queue = _queue(store);
        await queue.enqueue(_addBatchItem());
        final adapter = _ScriptedAdapter(<_Step>[
          _Step.ok(409, _conflict('status', 'batch_closed')),
        ]);

        final pass = await workerWith(
          adapter: adapter,
          queue: queue,
          clock: _TestClock(_t0),
        ).drain();

        expect(pass.conflicts, 1);
        expect(
          (await queue.byReference('add')).valueOrNull!.status,
          ProductionCommandStatus.conflict,
        );
      },
    );

    test(
      'a batch command timeout AFTER commit reconciles via idempotent replay',
      () async {
        final store = InMemoryCredentialStore();
        final queue = _queue(store);
        await queue.enqueue(_addBatchItem());
        final adapter = _ScriptedAdapter(<_Step>[
          const _Step.throwing(DioExceptionType.receiveTimeout),
          const _Step.ok(201, _batchOk),
        ]);
        final clock = _TestClock(_t0);
        final worker = workerWith(adapter: adapter, queue: queue, clock: clock);

        await worker.drain(); // times out -> retryWait
        clock.advance(const Duration(seconds: 5));
        final pass = await worker.drain(); // replay -> synced

        expect(pass.synced, 1);
        // BOTH attempts reused the SAME client_reference.
        final refs = adapter.requests
            .map((r) => (r.data as Map)['client_reference'])
            .toSet();
        expect(refs, <String>{'add'});
      },
    );

    test(
      'a fresh worker drains a persisted batch command (app restart)',
      () async {
        final store = InMemoryCredentialStore();
        await _queue(store).enqueue(_createBatch());
        // Restart: brand-new queue + worker over the same store. This also proves
        // batchId/payload survive the toJson/fromJson round-trip on device.
        final revived = _queue(store);
        final adapter = _ScriptedAdapter(<_Step>[
          const _Step.ok(201, _batchOk),
        ]);
        final pass = await workerWith(
          adapter: adapter,
          queue: revived,
          clock: _TestClock(_t0),
        ).drain();
        expect(pass.synced, 1);
      },
    );
  });

  group('FR-083 QC defect-photo upload', () {
    const evidenceOk =
        '{"data":{"evidence":{"id":"ev1","inspection_id":"insp-1",'
        '"content_type":"image/png","byte_size":9,'
        '"checksum_sha256":"abc","status":"stored","uploaded_at":null}},"meta":{}}';

    ProductionCommand uploadCommand({String reference = 'up'}) =>
        ProductionCommand(
          clientReference: reference,
          tenantId: 'ten-A',
          userId: 'usr-1',
          jobId: 'job-1',
          type: ProductionCommandType.uploadQcEvidence,
          createdAtUtc: _t0,
          payload: <String, Object?>{
            'inspection_id': 'insp-1',
            'filename': 'defect.png',
            // A tiny base64 blob stands in for the photo bytes on device.
            'photo_base64': 'iVBORw0KGgo=',
          },
        );

    test(
      'a queued upload syncs to a durable, server-confirmed evidence record',
      () async {
        final store = InMemoryCredentialStore();
        final queue = _queue(store);
        await queue.enqueue(uploadCommand());
        final adapter = _ScriptedAdapter(<_Step>[
          const _Step.ok(201, evidenceOk),
        ]);

        final pass = await workerWith(
          adapter: adapter,
          queue: queue,
          clock: _TestClock(_t0),
        ).drain();

        expect(pass.synced, 1);
        final stored = (await queue.byReference('up')).valueOrNull!;
        expect(stored.status, ProductionCommandStatus.synced);
        expect(stored.acknowledgement?['evidence_id'], 'ev1');
        expect(
          adapter.requests.single.path,
          contains('quality-control/insp-1/evidence'),
        );
      },
    );

    test(
      'an upload survives a restart and replays idempotently after a timeout',
      () async {
        final store = InMemoryCredentialStore();
        await _queue(store).enqueue(uploadCommand());
        // Restart: a fresh queue+worker over the same store proves the photo
        // payload persisted encrypted-at-rest.
        final revived = _queue(store);
        final adapter = _ScriptedAdapter(<_Step>[
          const _Step.throwing(DioExceptionType.receiveTimeout),
          const _Step.ok(201, evidenceOk),
        ]);
        final clock = _TestClock(_t0);
        final worker = workerWith(
          adapter: adapter,
          queue: revived,
          clock: clock,
        );

        await worker.drain(); // times out -> retryWait
        clock.advance(const Duration(seconds: 5));
        final pass = await worker.drain(); // replay -> synced

        expect(pass.synced, 1);
        // BOTH attempts reused the SAME client_reference (idempotent upload).
        final refs = adapter.requests
            .map((r) => r.uri.pathSegments.contains('evidence'))
            .toList();
        expect(refs.every((hit) => hit), isTrue);
      },
    );
  });
}
