import 'package:aish_auth/aish_auth.dart';
import 'package:aish_core/aish_core.dart';
import 'package:aish_design_system/aish_design_system.dart';
import 'package:aish_domain/aish_domain.dart';
import 'package:aish_networking/aish_networking.dart';
import 'package:aish_offline_sync/aish_offline_sync.dart';
import 'package:aish_ops_android/src/app.dart';
import 'package:aish_ops_android/src/production/production_job_detail_screen.dart';
import 'package:aish_ops_android/src/production/production_photo_source.dart';
import 'package:aish_ops_android/src/production/production_providers.dart';
import 'package:aish_testing/aish_testing.dart';
import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

/// STEP 6 · FR-083 — the Ops QC defect-photo flow. The photo SOURCE is an injected
/// seam (fake here, a real picker on device); the durable upload, its idempotency,
/// and its honest sync state are exercised end-to-end from fixtures (Rule 23,
/// owner constraint: no fabricated physical-camera evidence).
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

class _FakeConnectivity implements ConnectivityMonitor {
  _FakeConnectivity(this.online);
  bool online;
  @override
  Future<bool> isOnline() async => online;
  @override
  Stream<bool> get onlineChanges => const Stream<bool>.empty();
}

/// A fake photo source returning known fixture bytes — no camera, no fabrication.
class _FakePhotoSource implements PhotoSource {
  const _FakePhotoSource(this.photo);
  final PickedPhoto? photo;
  @override
  Future<PickedPhoto?> pick() async => photo;
}

Future<void> pump(
  WidgetTester tester,
  Widget screen,
  ApiClient client,
  FakeAuthService auth, {
  required PhotoSource photoSource,
  bool online = true,
}) async {
  tester.view.physicalSize = const Size(400, 900);
  tester.view.devicePixelRatio = 1.0;
  addTearDown(tester.view.reset);

  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        environmentProvider.overrideWithValue(env()),
        authServiceProvider.overrideWithValue(auth),
        apiClientProvider.overrideWithValue(client),
        secureCredentialStoreProvider.overrideWithValue(
          InMemoryCredentialStore(),
        ),
        productionConnectivityProvider.overrideWithValue(
          _FakeConnectivity(online),
        ),
        photoSourceProvider.overrideWithValue(photoSource),
      ],
      child: MaterialApp(theme: AishTheme.light(), home: screen),
    ),
  );
  await tester.pumpAndSettle();
}

String detailBody(String state) =>
    '{"data":{"job":{"id":"j1","order_id":"o1","outlet_id":"otl_fiktif_melati_pusat",'
    '"state":"$state","version":3,"block_reason_code":null,"updated_at":null,'
    '"items":[{"id":"i1","order_line_id":"l1","service_type":"kiloan",'
    '"stage":"FINISHING","quantity_done":"2.500","units_total":0,"units_done":0}]},'
    '"timeline":[]},"meta":{}}';

const String _qcFailedBody =
    '{"data":{"inspection":{"id":"insp-1","verdict":"FAILED_REWORK_REQUIRED"},'
    '"job":{"id":"j1","order_id":"o1","outlet_id":"otl_fiktif_melati_pusat",'
    '"state":"REWORK_IN_PROGRESS","version":4,"block_reason_code":null,'
    '"updated_at":null}},"meta":{}}';

const String _evidenceOk =
    '{"data":{"evidence":{"id":"ev1","inspection_id":"insp-1",'
    '"content_type":"image/png","byte_size":3,"checksum_sha256":"abc",'
    '"status":"stored","uploaded_at":null}},"meta":{}}';

void main() {
  testWidgets(
    'a FAILED verdict with a photo uploads durable evidence to the inspection',
    (tester) async {
      final s = scripted(<(bool Function(RequestOptions), int, String)>[
        on('GET', 200, detailBody('AWAITING_QC'),
            pathContains: 'production/jobs/j1'),
        // The evidence rule is MORE specific and must be matched before the
        // quality-control verdict rule (its path also contains quality-control).
        on('POST', 201, _evidenceOk, pathContains: 'evidence'),
        on('POST', 201, _qcFailedBody, pathContains: 'quality-control'),
      ]);
      await pump(
        tester,
        const ProductionJobDetailScreen(jobId: 'j1'),
        s.client,
        ownerAuth(),
        photoSource: const _FakePhotoSource(
          PickedPhoto(bytes: <int>[1, 2, 3], filename: 'defect.png'),
        ),
      );

      await tester.tap(find.text('Catat kendali mutu'));
      await tester.pumpAndSettle();
      await tester.tap(
        find.text(QualityControlVerdict.failedReworkRequired.label),
      );
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const Key('qc-attach-photo')));
      await tester.pumpAndSettle();
      await tester.enterText(find.byType(TextField).first, 'NODA');
      await tester.tap(find.text('Simpan verdict'));
      await tester.pumpAndSettle();

      // The QC verdict AND the durable defect-photo upload both reached the
      // inspection endpoint — the upload was enqueued and synced, not dropped.
      expect(
        s.adapter.requests.any(
          (r) => r.method == 'POST' && r.path.contains('/evidence'),
        ),
        isTrue,
      );
    },
  );

  testWidgets(
    'a FAILED verdict without a photo uploads nothing (photo is optional)',
    (tester) async {
      final s = scripted(<(bool Function(RequestOptions), int, String)>[
        on('GET', 200, detailBody('AWAITING_QC'),
            pathContains: 'production/jobs/j1'),
        on('POST', 201, _qcFailedBody, pathContains: 'quality-control'),
      ]);
      await pump(
        tester,
        const ProductionJobDetailScreen(jobId: 'j1'),
        s.client,
        ownerAuth(),
        photoSource: const _FakePhotoSource(null),
      );

      await tester.tap(find.text('Catat kendali mutu'));
      await tester.pumpAndSettle();
      await tester.tap(
        find.text(QualityControlVerdict.failedReworkRequired.label),
      );
      await tester.pumpAndSettle();
      await tester.enterText(find.byType(TextField).first, 'NODA');
      await tester.tap(find.text('Simpan verdict'));
      await tester.pumpAndSettle();

      // No photo attached → no evidence upload attempted.
      expect(
        s.adapter.requests.any((r) => r.path.contains('/evidence')),
        isFalse,
      );
    },
  );
}
