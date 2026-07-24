import 'package:aish_auth/aish_auth.dart';
import 'package:aish_core/aish_core.dart';
import 'package:aish_design_system/aish_design_system.dart';
import 'package:aish_domain/aish_domain.dart';
import 'package:aish_offline_sync/aish_offline_sync.dart';
import 'package:aish_networking/aish_networking.dart';
import 'package:aish_ops_android/src/app.dart';
import 'package:aish_ops_android/src/production/production_batch_detail_screen.dart';
import 'package:aish_ops_android/src/production/production_batch_list_screen.dart';
import 'package:aish_ops_android/src/production/production_providers.dart';
import 'package:aish_testing/aish_testing.dart';
import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

/// STEP 6 · FR-074 — the Ops batch surface. Every fixture is fictional (Rule 23).
/// OFFLINE-FIRST: a batch action is never claimed successful from local state;
/// only a server-confirmed command reads as done (Rule 29). RBAC: a role without
/// production.operate is offered no mutating control.
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

Future<void> pump(
  WidgetTester tester,
  Widget screen,
  ApiClient client,
  FakeAuthService auth, {
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
      ],
      child: MaterialApp(theme: AishTheme.light(), home: screen),
    ),
  );
  await tester.pumpAndSettle();
}

// --- fixtures ----------------------------------------------------------------

String batchSummary(String status, {int version = 1, int itemCount = 2}) =>
    '{"id":"b1","code":"BATCH-1","stage":"SORTING","status":"$status",'
    '"version":$version,"outlet_id":"otl_fiktif","item_count":$itemCount,'
    '"closed_at":${status == 'closed' ? '"2026-07-24T11:00:00+00:00"' : 'null'},'
    '"updated_at":"2026-07-24T10:00:00+00:00"}';

String batchListBody(String status) =>
    '{"data":{"batches":[${batchSummary(status)}]},'
    '"meta":{"page":1,"per_page":100,"total":1}}';

const String _batchListEmpty = '{"data":{"batches":[]},"meta":{}}';

String batchDetailBody(String status) =>
    '{"data":{"batch":{"id":"b1","code":"BATCH-1","stage":"SORTING",'
    '"status":"$status","version":1,"outlet_id":"otl_fiktif","item_count":1,'
    '"closed_at":${status == 'closed' ? '"2026-07-24T11:00:00+00:00"' : 'null'},'
    '"updated_at":null,"items":[{"production_item_id":"item-1",'
    '"service_type":"kiloan","stage":"SORTING",'
    '"added_at":"2026-07-24T10:00:00+00:00"}]},'
    '"timeline":[{"type":"BatchItemAdded","actor_membership_id":"m1",'
    '"production_item_id":"item-1","occurred_at":"2026-07-24T10:00:00+00:00"}]},'
    '"meta":{}}';

void main() {
  group('batch list', () {
    testWidgets('lists a batch with its status chip and the create action', (
      tester,
    ) async {
      final s = scripted(<(bool Function(RequestOptions), int, String)>[
        on('GET', 200, batchListBody('open'), pathContains: 'production/batches'),
      ]);
      await pump(tester, const ProductionBatchListScreen(), s.client, ownerAuth());

      expect(find.text('BATCH-1'), findsOneWidget);
      expect(find.text('Terbuka'), findsWidgets);
      // A role holding production.operate is offered the create control.
      expect(find.byKey(const Key('batch-create-fab')), findsOneWidget);
    });

    testWidgets('a cashier is offered no create control (RBAC)', (tester) async {
      // A cashier holds no production permission; the list read is denied and the
      // create FAB is not rendered — a control the operator may not use is absent.
      final s = scripted(<(bool Function(RequestOptions), int, String)>[
        on('GET', 403,
            '{"error":{"code":"FORBIDDEN","message":"x"},"meta":{}}',
            pathContains: 'production/batches'),
      ]);
      await pump(tester, const ProductionBatchListScreen(), s.client, cashierAuth());

      expect(find.byKey(const Key('batch-create-fab')), findsNothing);
    });

    testWidgets('shows the empty state when there are no batches', (
      tester,
    ) async {
      final s = scripted(<(bool Function(RequestOptions), int, String)>[
        on('GET', 200, _batchListEmpty, pathContains: 'production/batches'),
      ]);
      await pump(tester, const ProductionBatchListScreen(), s.client, ownerAuth());

      expect(find.text('Belum ada batch'), findsOneWidget);
    });

    testWidgets('tapping create opens the code + stage dialog', (tester) async {
      final s = scripted(<(bool Function(RequestOptions), int, String)>[
        on('GET', 200, _batchListEmpty, pathContains: 'production/batches'),
      ]);
      await pump(tester, const ProductionBatchListScreen(), s.client, ownerAuth());

      await tester.tap(find.byKey(const Key('batch-create-fab')));
      await tester.pumpAndSettle();

      expect(find.byKey(const Key('batch-code-field')), findsOneWidget);
      expect(find.byKey(const Key('batch-stage-field')), findsOneWidget);
    });
  });

  group('batch detail — state-valid, permission-gated actions', () {
    testWidgets('an OPEN batch offers add-item and close, and lists members', (
      tester,
    ) async {
      final s = scripted(<(bool Function(RequestOptions), int, String)>[
        on('GET', 200, batchDetailBody('open'),
            pathContains: 'production/batches/b1'),
      ]);
      await pump(
        tester,
        const ProductionBatchDetailScreen(batchId: 'b1'),
        s.client,
        ownerAuth(),
      );

      expect(find.text('BATCH-1'), findsWidgets);
      expect(find.text('item-1'), findsOneWidget); // a member row
      expect(find.byKey(const Key('batch-add-item')), findsOneWidget);
      expect(find.byKey(const Key('batch-close')), findsOneWidget);
    });

    testWidgets('a CLOSED batch offers no mutating action (immutable)', (
      tester,
    ) async {
      final s = scripted(<(bool Function(RequestOptions), int, String)>[
        on('GET', 200, batchDetailBody('closed'),
            pathContains: 'production/batches/b1'),
      ]);
      await pump(
        tester,
        const ProductionBatchDetailScreen(batchId: 'b1'),
        s.client,
        ownerAuth(),
      );

      expect(find.byKey(const Key('batch-add-item')), findsNothing);
      expect(find.byKey(const Key('batch-close')), findsNothing);
      expect(
        find.text('Batch sudah ditutup dan tidak dapat diubah.'),
        findsOneWidget,
      );
    });
  });

  group('offline-first honesty', () {
    testWidgets(
      'a close enqueued while offline is shown as pending, never as success',
      (tester) async {
        final s = scripted(<(bool Function(RequestOptions), int, String)>[
          on('GET', 200, batchDetailBody('open'),
              pathContains: 'production/batches/b1'),
        ]);
        await pump(
          tester,
          const ProductionBatchDetailScreen(batchId: 'b1'),
          s.client,
          ownerAuth(),
          online: false,
        );

        // Close is a direct action (no dialog). Offline, the command is stored on
        // device and honestly labelled so — never rendered as a committed success
        // (Rule 29 hard rule 1).
        await tester.tap(find.byKey(const Key('batch-close')));
        await tester.pumpAndSettle();

        expect(find.text(SyncState.storedOnDevice.label), findsWidgets);
        expect(find.text(SyncState.synced.label), findsNothing);
      },
    );
  });
}
