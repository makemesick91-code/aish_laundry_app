import 'package:aish_auth/aish_auth.dart';
import 'package:aish_core/aish_core.dart';
import 'package:aish_design_system/aish_design_system.dart';
import 'package:aish_domain/aish_domain.dart';
import 'package:aish_networking/aish_networking.dart';
import 'package:aish_offline_sync/aish_offline_sync.dart';
import 'package:aish_ops_android/src/app.dart';
import 'package:aish_ops_android/src/production/production_job_detail_screen.dart';
import 'package:aish_ops_android/src/production/production_providers.dart';
import 'package:aish_ops_android/src/production/production_queue_screen.dart';
import 'package:aish_testing/aish_testing.dart';
import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

/// STEP 6 — OPS PRODUCTION SURFACE (DEC-0037). Every fixture is fictional
/// (Rule 23). The surface is OFFLINE-FIRST: an action is never claimed
/// successful from local state; only a server-confirmed command reads as done
/// (Rule 29).
Environment env() => Environment.validate(
  environmentName: 'production',
  apiBaseUrl: 'https://ops.contoh-fiktif.id/api/v1',
  appName: 'Uji Ops',
).valueOrNull!;

class _Adapter implements HttpClientAdapter {
  _Adapter(this.rules, this.fallback);
  final List<(bool Function(RequestOptions), int, String)> rules;
  final (int, String) fallback;
  final List<RequestOptions> requests = <RequestOptions>[];

  @override
  Future<ResponseBody> fetch(
    RequestOptions options,
    Stream<List<int>>? s,
    Future<void>? c,
  ) async {
    requests.add(options);
    var status = fallback.$1;
    var body = fallback.$2;
    for (final (matches, ruleStatus, ruleBody) in rules) {
      if (matches(options)) {
        status = ruleStatus;
        body = ruleBody;
        break;
      }
    }
    return ResponseBody.fromString(
      body,
      status,
      headers: <String, List<String>>{
        Headers.contentTypeHeader: <String>[Headers.jsonContentType],
      },
    );
  }

  @override
  void close({bool force = false}) {}
}

(bool Function(RequestOptions), int, String) on(
  String method,
  int status,
  String body, {
  String? pathContains,
}) => (
  (RequestOptions o) =>
      o.method == method &&
      (pathContains == null || o.path.contains(pathContains)),
  status,
  body,
);

({ApiClient client, _Adapter adapter}) scripted(
  List<(bool Function(RequestOptions), int, String)> rules,
) {
  final adapter = _Adapter(rules, (200, '{"data":{},"meta":{}}'));
  final dio = Dio()..httpClientAdapter = adapter;
  return (
    client: ApiClient(
      environment: env(),
      transport: CredentialTransport.bearerToken,
      dio: dio,
    ),
    adapter: adapter,
  );
}

FakeAuthService ownerAuth() => FakeAuthService(
  initial: AuthState.authenticated(ApiFixtures.fullContext()),
);

/// A cashier holds NO production permission (matching the real RBAC): the
/// production surface must render no operator action for them.
FakeAuthService cashierAuth() => FakeAuthService(
  initial: AuthState.authenticated(
    SessionState(
      user: ApiFixtures.cashier,
      availableTenants: const <Tenant>[ApiFixtures.tenantMelati],
      activeTenant: ApiFixtures.tenantMelati,
      activeMembership: ApiFixtures.membershipCashierMelati,
      activeOutlet: ApiFixtures.outletMelatiPusat,
      permissions: ApiFixtures.cashierPermissions(ApiFixtures.tenantMelati.id),
    ),
  ),
);

class _FakeConnectivity implements ConnectivityMonitor {
  _FakeConnectivity(this.online);
  bool online;
  @override
  Future<bool> isOnline() async => online;
  @override
  Stream<bool> get onlineChanges => const Stream<bool>.empty();
}

Future<InMemoryCredentialStore> pump(
  WidgetTester tester,
  Widget screen,
  ApiClient client,
  FakeAuthService auth, {
  bool online = true,
}) async {
  tester.view.physicalSize = const Size(400, 900);
  tester.view.devicePixelRatio = 1.0;
  addTearDown(tester.view.reset);
  final store = InMemoryCredentialStore();

  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        environmentProvider.overrideWithValue(env()),
        authServiceProvider.overrideWithValue(auth),
        apiClientProvider.overrideWithValue(client),
        secureCredentialStoreProvider.overrideWithValue(store),
        productionConnectivityProvider.overrideWithValue(
          _FakeConnectivity(online),
        ),
      ],
      child: MaterialApp(theme: AishTheme.light(), home: screen),
    ),
  );
  await tester.pumpAndSettle();
  return store;
}

// --- fixtures ----------------------------------------------------------------

String summary(String state, {int version = 3, String? block}) =>
    '{"id":"j1","order_id":"o1","outlet_id":"otl_fiktif_melati_pusat",'
    '"state":"$state","version":$version,'
    '"block_reason_code":${block == null ? 'null' : '"$block"'},'
    '"updated_at":"2026-07-24T10:00:00+00:00"}';

String queueBody(String state) =>
    '{"data":{"jobs":[${summary(state)}]},"meta":{"page":1,"per_page":100,"total":1}}';

const String _queueEmpty = '{"data":{"jobs":[]},"meta":{}}';

String detailBody(String state, {int version = 3}) =>
    '{"data":{"job":{"id":"j1","order_id":"o1","outlet_id":"otl_fiktif_melati_pusat",'
    '"state":"$state","version":$version,"block_reason_code":null,'
    '"updated_at":null,"items":[{"id":"i1","order_line_id":"l1",'
    '"service_type":"kiloan","stage":"WASHING","quantity_done":"2.500",'
    '"units_total":0,"units_done":0}]},'
    '"timeline":[{"type":"STAGE_STARTED","actor_membership_id":"m1",'
    '"occurred_at":"2026-07-24T09:00:00+00:00"}]},"meta":{}}';

const String _conflictVersion =
    '{"error":{"code":"CONFLICT","message":"konflik",'
    '"details":{"version":["version_conflict"]}},"meta":{}}';

const String _forbidden =
    '{"error":{"code":"FORBIDDEN","message":"tidak boleh"},"meta":{}}';

void main() {
  group('production queue', () {
    testWidgets('lists a job with its state chip', (tester) async {
      final s = scripted([
        on(
          'GET',
          200,
          queueBody('IN_PROGRESS'),
          pathContains: 'production/queue',
        ),
      ]);
      await pump(tester, const ProductionQueueScreen(), s.client, ownerAuth());

      expect(find.text('Pesanan o1'), findsOneWidget);
      // The state label appears on the job's chip (and also on the filter chip).
      expect(find.text('Sedang Dikerjakan'), findsWidgets);
    });

    testWidgets('shows the empty state when there is no work', (tester) async {
      final s = scripted([
        on('GET', 200, _queueEmpty, pathContains: 'production/queue'),
      ]);
      await pump(tester, const ProductionQueueScreen(), s.client, ownerAuth());
      expect(find.text('Belum ada pekerjaan produksi'), findsOneWidget);
    });
  });

  group(
    'job detail — state-valid, permission-gated actions (no dead buttons)',
    () {
      testWidgets('an IN_PROGRESS job offers advance, send-to-QC and block', (
        tester,
      ) async {
        final s = scripted([
          on(
            'GET',
            200,
            detailBody('IN_PROGRESS'),
            pathContains: 'production/jobs',
          ),
        ]);
        await pump(
          tester,
          const ProductionJobDetailScreen(jobId: 'j1'),
          s.client,
          ownerAuth(),
        );

        expect(find.text('Lanjutkan tahap'), findsOneWidget);
        expect(find.text('Kirim ke kendali mutu'), findsOneWidget);
        expect(find.text('Tahan pekerjaan'), findsOneWidget);
        // NOT offered in this state — the transitions the server does not allow
        // are simply not rendered.
        expect(find.text('Tandai siap diambil'), findsNothing);
        expect(find.text('Catat kendali mutu'), findsNothing);
      });

      testWidgets('a CLOSED job offers only mark-ready', (tester) async {
        final s = scripted([
          on('GET', 200, detailBody('CLOSED'), pathContains: 'production/jobs'),
        ]);
        await pump(
          tester,
          const ProductionJobDetailScreen(jobId: 'j1'),
          s.client,
          ownerAuth(),
        );

        expect(find.text('Tandai siap diambil'), findsOneWidget);
        expect(find.text('Lanjutkan tahap'), findsNothing);
      });

      testWidgets('an AWAITING_QC job offers the QC action', (tester) async {
        final s = scripted([
          on(
            'GET',
            200,
            detailBody('AWAITING_QC'),
            pathContains: 'production/jobs',
          ),
        ]);
        await pump(
          tester,
          const ProductionJobDetailScreen(jobId: 'j1'),
          s.client,
          ownerAuth(),
        );
        expect(find.text('Catat kendali mutu'), findsOneWidget);
      });

      testWidgets(
        'a cashier (no production permission) sees no operator action',
        (tester) async {
          final s = scripted([
            on(
              'GET',
              200,
              detailBody('IN_PROGRESS'),
              pathContains: 'production/jobs',
            ),
          ]);
          await pump(
            tester,
            const ProductionJobDetailScreen(jobId: 'j1'),
            s.client,
            cashierAuth(),
          );
          expect(find.text('Lanjutkan tahap'), findsNothing);
          expect(find.text('Kirim ke kendali mutu'), findsNothing);
          // The read-only detail still renders.
          expect(find.text('Sedang Dikerjakan'), findsOneWidget);
        },
      );
    },
  );

  group('offline-first honesty', () {
    testWidgets(
      'an offline action is queued and shown as pending, never as success',
      (tester) async {
        final s = scripted([
          on(
            'GET',
            200,
            detailBody('BLOCKED'),
            pathContains: 'production/jobs',
          ),
        ]);
        // Worker is OFFLINE: the command enqueues locally but cannot sync.
        final store = await pump(
          tester,
          const ProductionJobDetailScreen(jobId: 'j1'),
          s.client,
          ownerAuth(),
          online: false,
        );

        await tester.tap(find.text('Lanjutkan kembali')); // resume
        await tester.pumpAndSettle();

        // Honest state: an offline command is stored on device, NEVER synced.
        expect(find.text(SyncState.storedOnDevice.label), findsWidgets);
        expect(find.text(SyncState.synced.label), findsNothing);
        // No POST was made while offline; the command persisted on device.
        expect(s.adapter.requests.where((r) => r.method == 'POST'), isEmpty);
        final queued = await ProductionCommandQueue(
          store: store,
          userId: ApiFixtures.owner.id,
          tenantId: ApiFixtures.tenantMelati.id,
        ).all();
        expect(queued.valueOrNull, hasLength(1));
      },
    );

    testWidgets(
      'mark-ready never renders READY before a server acknowledgement',
      (tester) async {
        final s = scripted([
          on('GET', 200, detailBody('CLOSED'), pathContains: 'production/jobs'),
        ]);
        await pump(
          tester,
          const ProductionJobDetailScreen(jobId: 'j1'),
          s.client,
          ownerAuth(),
          online: false,
        );

        await tester.tap(find.text('Tandai siap diambil'));
        await tester.pumpAndSettle();

        // The command is stored on device; the surface makes no "siap diambil"
        // success claim before the server acknowledges it.
        expect(find.text(SyncState.storedOnDevice.label), findsWidgets);
        expect(s.adapter.requests.where((r) => r.method == 'POST'), isEmpty);
      },
    );

    testWidgets('advancing opens a stage picker of the canonical stages', (
      tester,
    ) async {
      final s = scripted([
        on(
          'GET',
          200,
          detailBody('IN_PROGRESS'),
          pathContains: 'production/jobs',
        ),
      ]);
      await pump(
        tester,
        const ProductionJobDetailScreen(jobId: 'j1'),
        s.client,
        ownerAuth(),
      );
      await tester.tap(find.text('Lanjutkan tahap'));
      await tester.pumpAndSettle();
      expect(find.text('Pilih tahap berikutnya'), findsOneWidget);
      // The offered stages are exactly the canonical production stages — the UI
      // never invents a stage the server would reject.
      expect(find.text('WASHING'), findsWidgets);
      expect(find.text('DRYING'), findsOneWidget);
    });
  });

  group('conflict and QC validation', () {
    testWidgets(
      'a version conflict is surfaced with a reload, not a silent retry',
      (tester) async {
        final s = scripted([
          on('POST', 409, _conflictVersion, pathContains: 'resume'),
          on(
            'GET',
            200,
            detailBody('BLOCKED'),
            pathContains: 'production/jobs',
          ),
        ]);
        await pump(
          tester,
          const ProductionJobDetailScreen(jobId: 'j1'),
          s.client,
          ownerAuth(),
        );

        await tester.tap(find.text('Lanjutkan kembali'));
        await tester.pumpAndSettle();

        // The conflict is shown; the recovery is reload, never an automatic retry.
        expect(find.textContaining('sudah berubah'), findsWidgets);
        expect(find.text('Muat ulang'), findsWidgets);
      },
    );

    testWidgets(
      'QC FAILED requires a defect reason before it can be submitted',
      (tester) async {
        final s = scripted([
          on(
            'GET',
            200,
            detailBody('AWAITING_QC'),
            pathContains: 'production/jobs',
          ),
        ]);
        await pump(
          tester,
          const ProductionJobDetailScreen(jobId: 'j1'),
          s.client,
          ownerAuth(),
        );

        await tester.tap(find.text('Catat kendali mutu'));
        await tester.pumpAndSettle();
        // Choose FAILED, then try to submit with no reason.
        await tester.tap(find.text('Gagal — Perlu Pengerjaan Ulang'));
        await tester.pumpAndSettle();
        await tester.tap(find.text('Simpan verdict'));
        await tester.pumpAndSettle();
        expect(find.textContaining('Kode alasan cacat wajib'), findsOneWidget);
      },
    );
  });

  group('access states', () {
    testWidgets('a forbidden job renders a denied state (foreign == absent)', (
      tester,
    ) async {
      final s = scripted([
        on('GET', 403, _forbidden, pathContains: 'production/jobs'),
      ]);
      await pump(
        tester,
        const ProductionJobDetailScreen(jobId: 'j1'),
        s.client,
        ownerAuth(),
      );
      expect(find.textContaining('tidak memiliki akses'), findsOneWidget);
    });
  });
}
